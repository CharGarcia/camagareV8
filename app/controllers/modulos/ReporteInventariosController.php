<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\ReporteInventarioRepository;
use App\repositories\modulos\InventarioRepository;
use App\repositories\modulos\BodegaRepository;
use App\repositories\modulos\CategoriaRepository;
use App\repositories\modulos\MarcaRepository;
use App\repositories\modulos\VendedorRepository;
use App\repositories\modulos\ProductoRepository;
use App\repositories\modulos\ClienteRepository;
use App\Services\modulos\InventarioService;
use App\Services\LogSistemaService;
use App\models\Empresa;

class ReporteInventariosController extends BaseModuloController
{
    private ReporteInventarioRepository $repository;
    /** Ids de bodegas sin acceso para el usuario de la sesión; null = todavía sin consultar. */
    private ?array $bodegasDenegadas = null;
    private const RUTA_MODULO = 'modulos/reporte_inventarios';

    /** Desgloses de Existencias por debajo de producto×bodega, calculados desde el kardex
     *  (ver resolverDesglose()). No incluye LOTE_CONSIGNACION, que sale de otra fuente. */
    private const DESGLOSES_EXISTENCIAS = ['LOTE', 'CADUCIDAD', 'LOTE_CADUCIDAD'];

    /** Desglose de Existencias que no sale del kardex sino de las líneas de consignación:
     *  una fila por lote/NUP entregado, con el documento, el cliente y su saldo. */
    private const DESGLOSE_CONSIGNACION = 'LOTE_CONSIGNACION';

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    /**
     * Módulos dueños de la información de cada pestaña. El permiso de VER el
     * reporte abre la página, pero cada pestaña se muestra solo si el usuario
     * puede VER el módulo del que sale su información: Existencias, Movimientos,
     * Valorización y Auditoría leen el kardex/stock (Inventario); Consignaciones
     * lee las consignaciones de venta. Nivel 3 ve todo (Permisos::porRuta).
     * Las rutas son las de getRutaModulo() de InventarioController y
     * ConsignacionesVentasController.
     */
    private const RUTA_INVENTARIO     = 'modulos/inventario';
    private const RUTA_CONSIGNACIONES = 'modulos/consignaciones-ventas';
    private const MODULO_POR_PESTANA  = [
        'existencias'    => self::RUTA_INVENTARIO,
        'movimientos'    => self::RUTA_INVENTARIO,
        'valorizacion'   => self::RUTA_INVENTARIO,
        'consignaciones' => self::RUTA_CONSIGNACIONES,
        'auditoria'      => self::RUTA_INVENTARIO,
    ];

    /** Pestaña pedida por la URL; cualquier valor desconocido cae en Existencias (como el dispatcher). */
    private function normalizarPestana(?string $tab): string
    {
        $tab = (string) $tab;
        return isset(self::MODULO_POR_PESTANA[$tab]) ? $tab : 'existencias';
    }

    /** @return array<string,bool> pestaña => si el usuario puede verla (en el orden de la barra). */
    private function pestanasPermitidas(): array
    {
        $out = [];
        foreach (self::MODULO_POR_PESTANA as $tab => $ruta) {
            $out[$tab] = !empty($this->permisosModuloPorRuta($ruta)['ver']);
        }
        return $out;
    }

    /**
     * Guard de cada pestaña: la misma regla que decide si se dibuja en la barra se
     * aplica a sus datos, exportaciones y acciones, para que una URL armada a mano
     * no sirva lo que la pantalla oculta. Responde 403 JSON en AJAX o redirige.
     */
    private function requirePestana(string $tab): void
    {
        $this->requirePermisoVerModulo(self::MODULO_POR_PESTANA[$this->normalizarPestana($tab)]);
    }

    public function __construct()
    {
        parent::__construct();
        $this->repository = new ReporteInventarioRepository();
    }

    /**
     * Bodegas que este usuario NO puede ver, según Bodegas → pestaña "Accesos"
     * (usuarios_bodegas.denegado = true; niveles 2 y 3 ven todas). Viajan dentro de los
     * filtros de cada pestaña para que el repositorio las descarte en TODAS sus consultas
     * —pantalla, KPIs y exportaciones— y no solo en el selector de bodega de la vista:
     * el selector ya venía filtrado, pero bastaba con dejarlo en "Todas", o escribir el
     * id de la bodega ajena en la URL de una exportación, para ver su stock igual.
     * Se resuelve una sola vez por petición; lista vacía (lo normal) = ve todas.
     */
    private function bodegasDenegadas(): array
    {
        if ($this->bodegasDenegadas === null) {
            $this->bodegasDenegadas = (new BodegaRepository())->getIdsBodegasDenegadas(
                (int) $_SESSION['id_usuario'],
                (int) $_SESSION['id_empresa'],
                (int) ($_SESSION['nivel'] ?? 1)
            );
        }
        return $this->bodegasDenegadas;
    }

    /**
     * Corta una acción que apunta a una bodega concreta (ajuste, mínimo/máximo, corrección
     * de stock). Los listados ya no la muestran, pero la petición llega con el id_bodega
     * puesto a mano y escribe en el inventario: se valida antes de tocar nada.
     */
    private function requireBodegaPermitida(int $idBodega): void
    {
        if (in_array($idBodega, $this->bodegasDenegadas(), true)) {
            throw new \RuntimeException('No tiene acceso a esa bodega.');
        }
    }

    /**
     * Suelta el candado del archivo de sesión. PHP lo mantiene tomado durante toda la
     * petición, así que mientras corre un "Mostrar" o una exportación de varios segundos,
     * cualquier otra petición del mismo usuario (abrir otro módulo, el buscador de
     * productos, los contadores del navbar) queda esperando en fila. Llamarlo solo en
     * acciones de lectura y DESPUÉS de los guards: $_SESSION se sigue pudiendo leer, pero
     * lo que se escriba después ya no se guarda.
     */
    private function liberarSesion(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    /**
     * Comprime con gzip la respuesta JSON si el navegador lo acepta. Un "Mostrar" con 5.000
     * filas son ~5 MB de HTML y la configuración por defecto de Apache no comprime
     * application/json: comprimido baja a unos cientos de KB. Si ya hay compresión de PHP
     * activa, no se apila otra.
     */
    private function comprimirRespuesta(): void
    {
        if (!headers_sent() && extension_loaded('zlib') && !ini_get('zlib.output_compression')) {
            ob_start('ob_gzhandler');
        }
    }

    // ────────────────────────────────────────────────────────────────
    // INDEX
    // ────────────────────────────────────────────────────────────────
    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $nivel     = (int) ($_SESSION['nivel'] ?? 1);

        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);

        $bodegas    = (new BodegaRepository())->getBodegasPermitidas($idUsuario, $idEmpresa, $nivel);
        $categorias = (new CategoriaRepository())->getCombo($idEmpresa);
        $marcas     = (new MarcaRepository())->getCombo($idEmpresa);
        $vendedores    = (new VendedorRepository())->getVendedoresActivos($idEmpresa);
        $inventarioRepo = new InventarioRepository();
        $usuarios      = $inventarioRepo->getUsuariosConMovimientos($idEmpresa);
        $origenes      = $inventarioRepo->getTiposReferencia($idEmpresa);
        $anios         = $this->repository->getAniosMovimientos($idEmpresa);
        $responsables  = (new \App\repositories\modulos\ResponsableTrasladoRepository())->listarPorEmpresa($idEmpresa);

        // Pestañas visibles y cuál arranca activa (la primera permitida, en el orden de la barra).
        $pestanas       = $this->pestanasPermitidas();
        $pestanaInicial = (string) (array_key_first(array_filter($pestanas)) ?? '');

        $this->viewWithLayout('layouts.main', 'modulos/reporte_inventarios/index', [
            'titulo'     => 'Reporte de Inventarios',
            'perm'       => $this->getPermisos(),
            'pestanas'       => $pestanas,
            'pestanaInicial' => $pestanaInicial,
            'vistaConfig'=> $prefsVista,
            'rutaModulo' => self::RUTA_MODULO,
            'bodegas'    => $bodegas,
            'categorias' => $categorias,
            'marcas'     => $marcas,
            'vendedores' => $vendedores,
            'usuarios'   => $usuarios,
            'origenes'   => $origenes,
            'anios'      => $anios,
            'responsables' => $responsables,
            'fullWidth'  => true,
            'base'       => BASE_URL,
        ]);
    }

    // ────────────────────────────────────────────────────────────────
    // FILTROS POR PESTAÑA
    // ────────────────────────────────────────────────────────────────
    /**
     * Nivel de desglose de la pestaña Existencias: GENERAL (una fila por
     * producto×bodega, manda el selector "Agrupar por") o uno de los tres
     * desgloses por debajo de ese nivel (LOTE, CADUCIDAD, LOTE_CADUCIDAD),
     * que definen las filas por sí solos.
     *
     * Acepta además los valores antiguos LOTE/NUP/CADUCIDAD que llegaban por
     * `agrupar_por` (enlaces de exportación o pestañas abiertas antes del
     * cambio): los tres hacían el desglose máximo, así que se mapean a
     * LOTE_CADUCIDAD y siguen mostrando exactamente lo mismo.
     */
    private function resolverDesglose(): string
    {
        $desglose = strtoupper(trim((string) ($_REQUEST['desglose'] ?? '')));
        if (in_array($desglose, self::DESGLOSES_EXISTENCIAS, true) || $desglose === self::DESGLOSE_CONSIGNACION) {
            return $desglose;
        }
        $agrupar = strtoupper(trim((string) ($_REQUEST['agrupar_por'] ?? '')));
        if (in_array($agrupar, ['LOTE', 'NUP', 'CADUCIDAD'], true)) {
            return 'LOTE_CADUCIDAD';
        }
        return 'GENERAL';
    }

    private function getFiltrosExistencias(): array
    {
        return [
            'desglose'     => $this->resolverDesglose(),
            'agrupar_por'  => $_REQUEST['agrupar_por'] ?? 'NINGUNO',
            'id_bodega'    => $_REQUEST['id_bodega']    ?? '',
            'id_categoria' => $_REQUEST['id_categoria'] ?? '',
            'id_marca'     => $_REQUEST['id_marca']     ?? '',
            'id_producto'  => $_REQUEST['id_producto']  ?? '',
            'estado_stock' => $_REQUEST['estado_stock'] ?? '',
            'consignado'   => $_REQUEST['consignado'] ?? '',
            'fecha_corte'  => $_REQUEST['fecha_corte'] ?? '',
            'numero_lote'  => trim($_REQUEST['numero_lote'] ?? ''),
            'nup'          => trim($_REQUEST['nup'] ?? ''),
            'fecha_caducidad_desde' => $_REQUEST['fecha_caducidad_desde'] ?? '',
            'fecha_caducidad_hasta' => $_REQUEST['fecha_caducidad_hasta'] ?? '',
            'orden'        => $_REQUEST['orden'] ?? '',
            'dir'          => $_REQUEST['dir'] ?? 'ASC',
            'buscar'       => trim($_REQUEST['buscar']  ?? ''),
            'bodegas_denegadas' => $this->bodegasDenegadas(),
        ];
    }

    /**
     * Traduce los filtros de Existencias a los que entiende la consulta de consignaciones,
     * para el desglose "Lote + consignación". Los de esa pestaña que no tienen equivalente
     * (estado de stock, agrupación, orden) no se trasladan: ahí la fila es una línea de
     * consignación, no un par producto×bodega. La fecha de corte se traduce a "emitidas
     * hasta esa fecha", que es lo que significa un corte en este reporte.
     */
    private static function filtrosConsignacionDesdeExistencias(array $filtros): array
    {
        return [
            'estado'       => 'TODOS',
            'id_bodega'    => $filtros['id_bodega']    ?? '',
            'id_categoria' => $filtros['id_categoria'] ?? '',
            'id_marca'     => $filtros['id_marca']     ?? '',
            'id_producto'  => $filtros['id_producto']  ?? '',
            'buscar'       => $filtros['buscar']       ?? '',
            'numero_lote'  => $filtros['numero_lote']  ?? '',
            'nup'          => $filtros['nup']          ?? '',
            'fecha_caducidad_desde' => $filtros['fecha_caducidad_desde'] ?? '',
            'fecha_caducidad_hasta' => $filtros['fecha_caducidad_hasta'] ?? '',
            'fecha_hasta'  => $filtros['fecha_corte'] ?? '',
            'bodegas_denegadas' => $filtros['bodegas_denegadas'] ?? [],
        ];
    }

    private function getFiltrosMovimientos(): array
    {
        return [
            'agrupar_por'     => $_REQUEST['agrupar_por'] ?? 'NINGUNO',
            'fecha_desde'     => $_REQUEST['fecha_desde'] ?? '',
            'fecha_hasta'     => $_REQUEST['fecha_hasta'] ?? '',
            'id_bodega'       => $_REQUEST['id_bodega']    ?? '',
            'id_producto'     => $_REQUEST['id_producto']  ?? '',
            'id_categoria'    => $_REQUEST['id_categoria'] ?? '',
            'id_marca'        => $_REQUEST['id_marca']     ?? '',
            'tipo_movimiento' => $_REQUEST['tipo_movimiento'] ?? '',
            'referencia_tipo' => $_REQUEST['referencia_tipo'] ?? '',
            'id_usuario'      => $_REQUEST['id_usuario']   ?? '',
            'numero_lote'     => trim($_REQUEST['numero_lote'] ?? ''),
            'nup'             => trim($_REQUEST['nup'] ?? ''),
            'fecha_caducidad_desde' => $_REQUEST['fecha_caducidad_desde'] ?? '',
            'fecha_caducidad_hasta' => $_REQUEST['fecha_caducidad_hasta'] ?? '',
            'observaciones'   => trim($_REQUEST['observaciones'] ?? ''),
            'buscar'          => trim($_REQUEST['buscar'] ?? ''),
            'bodegas_denegadas' => $this->bodegasDenegadas(),
        ];
    }

    private function getFiltrosValorizacion(): array
    {
        return [
            'agrupar_por'  => $_REQUEST['agrupar_por'] ?? 'PRODUCTO',
            'id_bodega'    => $_REQUEST['id_bodega']    ?? '',
            'id_categoria' => $_REQUEST['id_categoria'] ?? '',
            'id_marca'     => $_REQUEST['id_marca']     ?? '',
            'id_producto'  => $_REQUEST['id_producto']  ?? '',
            'bodegas_denegadas' => $this->bodegasDenegadas(),
        ];
    }

    private function getFiltrosConsignaciones(): array
    {
        return [
            'agrupar_por'        => $_REQUEST['agrupar_por'] ?? 'NINGUNO',
            'estado'             => $_REQUEST['estado'] ?? 'TODOS',
            'id_cliente'         => $_REQUEST['id_cliente']  ?? '',
            'id_producto'        => $_REQUEST['id_producto'] ?? '',
            'id_bodega'          => $_REQUEST['id_bodega']   ?? '',
            'id_vendedor'        => $_REQUEST['id_vendedor'] ?? '',
            'id_responsable_traslado' => $_REQUEST['id_responsable_traslado'] ?? '',
            'fecha_desde'        => $_REQUEST['fecha_desde'] ?? '',
            'fecha_hasta'        => $_REQUEST['fecha_hasta'] ?? '',
            'numero_lote'        => trim($_REQUEST['numero_lote'] ?? ''),
            'nup'                => trim($_REQUEST['nup'] ?? ''),
            'fecha_caducidad_desde' => $_REQUEST['fecha_caducidad_desde'] ?? '',
            'fecha_caducidad_hasta' => $_REQUEST['fecha_caducidad_hasta'] ?? '',
            'secuencial'         => trim($_REQUEST['secuencial'] ?? ''),
            'bodegas_denegadas' => $this->bodegasDenegadas(),
        ];
    }

    private function getFiltrosAuditoria(): array
    {
        return [
            'id_bodega'      => $_REQUEST['id_bodega']   ?? '',
            'id_producto'    => $_REQUEST['id_producto'] ?? '',
            'buscar'         => trim($_REQUEST['buscar'] ?? ''),
            'bodegas_denegadas' => $this->bodegasDenegadas(),
        ];
    }

    // ────────────────────────────────────────────────────────────────
    // GENERAR (AJAX) — dispatcher por pestaña
    // ────────────────────────────────────────────────────────────────
    public function generarAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $tab = $this->normalizarPestana($_REQUEST['tab'] ?? 'existencias');
            $this->requirePestana($tab);
            $this->liberarSesion();
            $this->comprimirRespuesta();

            $resultado = match ($tab) {
                'movimientos'    => $this->generarMovimientos($idEmpresa),
                'valorizacion'   => $this->generarValorizacion($idEmpresa),
                'consignaciones' => $this->generarConsignaciones($idEmpresa),
                'auditoria'      => $this->generarAuditoria($idEmpresa),
                default          => $this->generarExistencias($idEmpresa),
            };

            // El tamaño del JSON SIN comprimir viaja en una cabecera propia para que la
            // barra de progreso del navegador pueda mostrar un % de descarga real:
            // Content-Length lo pone ob_gzhandler y mide el gzip (unos cientos de KB),
            // no los megas que el navegador va recibiendo ya descomprimidos.
            $json = (string) json_encode(array_merge(['ok' => true], $resultado));
            header('X-Json-Bytes: ' . strlen($json));
            echo $json;
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            error_log('ReporteInventario Exception: ' . $e->getMessage() . ' on line ' . $e->getLine());
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    private function generarExistencias(int $idEmpresa): array
    {
        $filtros  = $this->getFiltrosExistencias();
        $desglose = $filtros['desglose'];

        // El desglose (por lote / por caducidad) define las filas por sí solo: cuando
        // está activo, "Agrupar por" no pinta nada — la vista lo deshabilita.
        $limite = ReporteInventarioRepository::LIMITE_FILAS_PANTALLA;
        if ($desglose === self::DESGLOSE_CONSIGNACION) {
            // Este desglose sirve datos de consignaciones desde una pestaña que solo exige
            // Inventario: se pide además el permiso del módulo dueño, como hace requirePestana().
            $this->requirePermisoVerModulo(self::RUTA_CONSIGNACIONES);
            $modo = $desglose;
            $rows = $this->repository->getConsignacionesDetalle(
                $idEmpresa,
                self::filtrosConsignacionDesdeExistencias($filtros),
                (string) ($filtros['consignado'] ?? ''),
                $limite
            );
        } elseif ($desglose !== 'GENERAL') {
            $modo = $desglose;
            $rows = $this->repository->getExistenciasPorDesglose($idEmpresa, $filtros, $desglose, $limite);
        } else {
            $modo = $filtros['agrupar_por'];
            $rows = match ($modo) {
                'PRODUCTO'   => $this->repository->getExistenciasAgrupadoProducto($idEmpresa, $filtros),
                'CATEGORIA'  => $this->repository->getExistenciasAgrupadoCategoria($idEmpresa, $filtros),
                'BODEGA'     => $this->repository->getExistenciasAgrupadoBodega($idEmpresa, $filtros),
                default      => $this->repository->getExistenciasDetalle($idEmpresa, $filtros, $limite),
            };
        }
        // El repositorio pide una fila de más para poder distinguir "justo el tope" de
        // "hay más": si llegó, se recorta y la tabla lo dice en su última fila.
        $hayMas = count($rows) > $limite;
        if ($hayMas) {
            $rows = array_slice($rows, 0, $limite);
        }
        // Sin KPIs: la pantalla no muestra ninguno en esta pestaña y calcularlos obligaba
        // a repetir la consulta completa en cada Mostrar (medido: 14,5 s con 10.000 pares producto×bodega). El único
        // indicador que sí se usa, el de Auditoría, se cuenta en PHP sobre las filas ya traídas.
        // Sin rawData por lo mismo: el front-end nunca lo lee y duplicaba la respuesta
        // (5.000 filas: 7 MB con él, 4,7 MB sin él, antes de comprimir).

        // Filtros que RECORTAN el kardex que suma cada fila. El seguimiento de un
        // negativo tiene que aplicarlos igual o su saldo no cuadrará con la fila.
        //
        // Cuáles son depende del nivel, y no es lo mismo en los dos:
        //  - En los desgloses (getExistenciasPorDesglose → buildWhereExistenciasKardex)
        //    lote/NUP/caducidad se aplican SOBRE el kardex, así que sí recortan la suma.
        //  - En "En general" (baseExistencias) esos tres son un EXISTS que solo decide
        //    qué producto×bodega aparece; la suma es de todo el kardex del par. Mandarlos
        //    haría que el seguimiento sumara menos que la fila.
        // La fecha de corte recorta en los dos.
        $filtrosSaldo = ['fecha_corte' => (string) ($filtros['fecha_corte'] ?? '')];
        if (in_array($desglose, self::DESGLOSES_EXISTENCIAS, true)) {
            $filtrosSaldo += [
                'numero_lote'           => (string) ($filtros['numero_lote'] ?? ''),
                'nup'                   => (string) ($filtros['nup'] ?? ''),
                'fecha_caducidad_desde' => (string) ($filtros['fecha_caducidad_desde'] ?? ''),
                'fecha_caducidad_hasta' => (string) ($filtros['fecha_caducidad_hasta'] ?? ''),
            ];
        }
        $colSpan = self::colSpanExistencias($modo);

        return [
            'rows'       => $this->renderRows($rows, fn($r) => $this->filaExistencias($r, $modo), $colSpan)
                            . ($hayMas ? self::filaTopeAlcanzado($limite, $colSpan) : ''),
            'agrupacion' => $modo,
            // Con qué filtros se pintó ESTA tabla, para que el seguimiento de un negativo
            // reproduzca la misma suma (el formulario puede haber cambiado sin pulsar Mostrar).
            'filtros_saldo' => $filtrosSaldo,
            'tope'       => $hayMas ? $limite : null,
        ];
    }

    private function generarMovimientos(int $idEmpresa): array
    {
        $filtros = $this->getFiltrosMovimientos();
        $modo = $filtros['agrupar_por'];
        $limite = ReporteInventarioRepository::LIMITE_FILAS_PANTALLA;

        $rows = match ($modo) {
            'PRODUCTO' => $this->repository->getMovimientosAgrupadoProducto($idEmpresa, $filtros),
            'BODEGA'   => $this->repository->getMovimientosAgrupadoBodega($idEmpresa, $filtros),
            'TIPO'     => $this->repository->getMovimientosAgrupadoTipo($idEmpresa, $filtros),
            'ORIGEN'   => $this->repository->getMovimientosAgrupadoOrigen($idEmpresa, $filtros),
            'FECHA'    => $this->repository->getMovimientosAgrupadoFecha($idEmpresa, $filtros),
            'MES'      => $this->repository->getMovimientosAgrupadoMes($idEmpresa, $filtros),
            default    => $this->repository->getMovimientosDetalle($idEmpresa, $filtros, $limite),
        };
        $hayMas = count($rows) > $limite;
        if ($hayMas) {
            $rows = array_slice($rows, 0, $limite);
        }
        // Sin KPIs: la pantalla no muestra ninguno en esta pestaña y calcularlos obligaba
        // a repetir la consulta completa en cada Mostrar (medido: una segunda pasada completa sobre el kardex). El único
        // indicador que sí se usa, el de Auditoría, se cuenta en PHP sobre las filas ya traídas.

        // +1 columna (Código) en detalle y en el agrupado por producto; el resto de agrupados no la lleva.
        $colSpan = $modo === 'NINGUNO' ? 13 : ($modo === 'PRODUCTO' ? 7 : 6);

        return [
            'rows'       => $this->renderRows($rows, fn($r) => $this->filaMovimientos($r, $modo), $colSpan)
                            . ($hayMas ? self::filaTopeAlcanzado($limite, $colSpan) : ''),
            'agrupacion' => $modo,
            'tope'       => $hayMas ? $limite : null,
        ];
    }

    private function generarValorizacion(int $idEmpresa): array
    {
        $filtros = $this->getFiltrosValorizacion();
        // Se normaliza a PRODUCTO igual que el match: así la fila sabe que debe pintar la
        // columna Código aunque llegue un "agrupar_por" desconocido.
        $modo = self::modoValorizacion($filtros['agrupar_por']);

        $rows = match ($modo) {
            'CATEGORIA' => $this->repository->getValorizacionAgrupadoCategoria($idEmpresa, $filtros),
            'BODEGA'    => $this->repository->getValorizacionAgrupadoBodega($idEmpresa, $filtros),
            'MARCA'     => $this->repository->getValorizacionAgrupadoMarca($idEmpresa, $filtros),
            default     => $this->repository->getValorizacionAgrupadoProducto($idEmpresa, $filtros),
        };
        // Sin KPIs: la pantalla no muestra ninguno en esta pestaña y calcularlos obligaba
        // a repetir la consulta completa en cada Mostrar (medido: otros 15 s, sobre la misma base que ya se acaba de consultar). El único
        // indicador que sí se usa, el de Auditoría, se cuenta en PHP sobre las filas ya traídas.

        return [
            'rows'       => $this->renderRows($rows, fn($r) => $this->filaValorizacion($r, $modo), $modo === 'PRODUCTO' ? 6 : 5),
            'agrupacion' => $modo,
        ];
    }

    private function generarConsignaciones(int $idEmpresa): array
    {
        $filtros = $this->getFiltrosConsignaciones();
        $modo = $filtros['agrupar_por'];

        $rows = match ($modo) {
            'CLIENTE'  => $this->repository->getConsignacionesAgrupadoCliente($idEmpresa, $filtros),
            'PRODUCTO' => $this->repository->getConsignacionesAgrupadoProducto($idEmpresa, $filtros),
            default    => $this->repository->getConsignacionesCabeceras($idEmpresa, $filtros),
        };

        // Sin KPIs ni rawData a propósito: la pestaña no muestra tarjetas de indicadores y el
        // front-end nunca lee `rawData`. Calcularlos obligaba a repetir entera la consulta de
        // saldos (la más cara del módulo) y a serializar dos veces el mismo resultado.
        return [
            'rows'       => $this->renderRows($rows, fn($r) => $this->filaConsignaciones($r, $modo), $modo === 'NINGUNO' ? 10 : ($modo === 'PRODUCTO' ? 4 : 3)),
            'agrupacion' => $modo,
        ];
    }

    private function generarAuditoria(int $idEmpresaSesion): array
    {
        $filtros = $this->getFiltrosAuditoria();
        $limite  = ReporteInventarioRepository::LIMITE_FILAS_PANTALLA;
        $rows    = $this->repository->getAuditoriaStock($idEmpresaSesion, $filtros, $limite);
        $hayMas  = count($rows) > $limite;
        if ($hayMas) {
            $rows = array_slice($rows, 0, $limite);
        }

        return [
            'rows'       => $this->renderRows($rows, fn($r) => $this->filaAuditoria($r), 7)
                            . ($hayMas ? self::filaTopeAlcanzado($limite, 7) : ''),
            // Este sí lo lee la pantalla (el contador de discrepancias), y no cuesta una
            // consulta aparte: sale de las filas ya traídas. Con tope, es "al menos N".
            'kpis'       => ['total_discrepancias' => count($rows)],
            'agrupacion' => 'NINGUNO',
            'tope'       => $hayMas ? $limite : null,
        ];
    }

    /**
     * Última fila de la tabla cuando el resultado llegó al tope de pantalla. No es un
     * error: el dato completo sigue disponible en el Excel y el PDF, que no llevan tope.
     */
    private static function filaTopeAlcanzado(int $limite, int $colSpan): string
    {
        return '<tr class="table-warning"><td colspan="' . $colSpan . '" class="text-center small py-2">'
            . '<i class="bi bi-exclamation-triangle me-1"></i>Se muestran las primeras '
            . number_format($limite) . ' filas. Afina los filtros para ver menos, o descarga el '
            . 'Excel/PDF, que sí traen el listado completo.'
            . '</td></tr>';
    }

    private function renderRows(array $rows, callable $render, int $colSpanVacio): string
    {
        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="' . $colSpanVacio . '" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No se encontraron resultados.</td></tr>';
        } else {
            foreach ($rows as $r) {
                echo $render($r);
            }
        }
        return (string) ob_get_clean();
    }

    // ────────────────────────────────────────────────────────────────
    // RENDER DE FILAS POR PESTAÑA
    // ────────────────────────────────────────────────────────────────
    /** Columnas de la tabla de Existencias según el modo (para el colspan del mensaje vacío).
     *  Incluye la columna Código, que va primera en todo modo cuya fila es un producto
     *  (detalle, desgloses y agrupado por producto); los demás agrupados no la tienen. */
    private static function colSpanExistencias(string $modo): int
    {
        return match ($modo) {
            'NINGUNO'        => 11,
            'LOTE_CADUCIDAD' => 11,
            'LOTE', 'CADUCIDAD' => 9,
            'PRODUCTO'       => 9,
            self::DESGLOSE_CONSIGNACION => 15,
            default          => 8,
        };
    }

    /** Valorización siempre agrupa; cualquier valor fuera de la lista cae en "por Producto". */
    private static function modoValorizacion(?string $agruparPor): string
    {
        return in_array($agruparPor, ['CATEGORIA', 'BODEGA', 'MARCA'], true) ? $agruparPor : 'PRODUCTO';
    }

    /** Primera celda de una fila de reporte: el código del producto. */
    private static function tdCodigo(?string $codigo): string
    {
        $codigo = trim((string) $codigo);
        return '<td class="small text-nowrap fw-semibold">' . htmlspecialchars($codigo !== '' ? $codigo : '-') . '</td>';
    }

    /**
     * Texto de una celda que puede venir vacía: escapado, y con guion si no hay nada.
     * Compara contra '' en vez de usar `?:` porque en PHP la cadena "0" es falsy, y un lote
     * o un NUP llamados literalmente "0" existen y se estaban pintando como "—".
     */
    private static function texto($valor): string
    {
        $valor = (string) ($valor ?? '');
        return htmlspecialchars($valor !== '' ? $valor : '—');
    }

    /**
     * Celda de "Stock" que, cuando el saldo es NEGATIVO, se vuelve un botón para abrir el
     * seguimiento de ese negativo (qué movimientos lo produjeron).
     *
     * El botón va en la celda y no en la fila entera por dos razones: en el nivel "En
     * general" la fila ya tiene su propio clic (editar mínimo/máximo/categoría), y un
     * negativo es la excepción — el resto de filas se quedan exactamente como estaban.
     *
     * `data-seg-cols` dice qué columnas agrupan esa fila y viaja junto al valor de cada una,
     * porque el seguimiento tiene que rehacer EXACTAMENTE el mismo grupo; la cadena vacía
     * significa NULL ("sin lote" es un grupo más del desglose, no una fila sin clave). Los
     * agrupados (por producto / categoría / bodega) no llevan botón: su fila resume varios
     * productos o bodegas, así que no hay una clave de kardex que seguir.
     */
    private static function tdStockConSeguimiento(array $r, string $modo): string
    {
        $stock = (float) ($r['stock_actual'] ?? 0);
        $texto = number_format($stock, 2);

        $cols = match ($modo) {
            'NINGUNO'        => '',
            'LOTE'           => 'lote',
            'CADUCIDAD'      => 'caducidad',
            'LOTE_CADUCIDAD' => 'lote,nup,caducidad',
            default          => null,
        };

        if ($stock >= 0 || $cols === null) {
            return '<td class="text-end fw-bold' . ($stock < 0 ? ' text-danger' : '') . '">' . $texto . '</td>';
        }

        return '<td class="text-end p-0 pe-2">'
            . '<button type="button" class="btn btn-sm btn-link p-0 fw-bold text-danger text-decoration-none"'
            . ' title="Ver por qué está en negativo"'
            . ' onclick="event.stopPropagation(); window.RI_Seguimiento.abrir(this);"'
            . ' data-seg-cols="' . $cols . '"'
            . ' data-seg-producto="' . (int) ($r['id_producto'] ?? 0) . '"'
            . ' data-seg-bodega="' . (int) ($r['id_bodega'] ?? 0) . '"'
            . ' data-seg-lote="' . htmlspecialchars((string) ($r['lote'] ?? ''), ENT_QUOTES) . '"'
            . ' data-seg-nup="' . htmlspecialchars((string) ($r['nup'] ?? ''), ENT_QUOTES) . '"'
            . ' data-seg-caducidad="' . htmlspecialchars((string) ($r['fecha_caducidad'] ?? ''), ENT_QUOTES) . '"'
            . ' data-seg-producto-nombre="' . htmlspecialchars((string) ($r['producto_nombre'] ?? ''), ENT_QUOTES) . '"'
            . ' data-seg-bodega-nombre="' . htmlspecialchars((string) ($r['bodega_nombre'] ?? ''), ENT_QUOTES) . '"'
            . '>' . $texto . ' <i class="bi bi-search"></i></button>'
            . '</td>';
    }


    private function filaExistencias(array $r, string $modo): string
    {
        // "Lote + consignación": la fila es una línea de consignación, no un par
        // producto×bodega, así que sus columnas son las del documento que la entregó.
        if ($modo === self::DESGLOSE_CONSIGNACION) {
            return $this->filaExistenciaConsignacion($r);
        }

        $costo = number_format((float) ($r['costo_unitario'] ?? 0), 4);
        $valor = number_format((float) ($r['valor_total'] ?? 0), 2);

        if ($modo === 'NINGUNO') {
            $idProducto = (int) ($r['id_producto'] ?? 0);
            $idBodega   = (int) ($r['id_bodega'] ?? 0);
            $idCategoriaActual = $r['id_categoria'] ?? '';
            $stockMinimo = (float) ($r['stock_minimo'] ?? 0);
            $stockMaximo = (float) ($r['stock_maximo'] ?? 0);

            return '<tr class="ri-ex-row" style="cursor:pointer;" title="Editar mínimo, máximo y categoría"'
                . ' onclick="window.RI_Existencias.abrirModalEditar(this)"'
                . ' data-id-producto="' . $idProducto . '"'
                . ' data-id-bodega="' . $idBodega . '"'
                . ' data-producto-nombre="' . htmlspecialchars($r['producto_nombre'] ?? '', ENT_QUOTES) . '"'
                . ' data-bodega-nombre="' . htmlspecialchars($r['bodega_nombre'] ?? '', ENT_QUOTES) . '"'
                . ' data-stock-minimo="' . $stockMinimo . '"'
                . ' data-stock-maximo="' . $stockMaximo . '"'
                . ' data-id-categoria="' . $idCategoriaActual . '"'
                . ' data-costo-unitario="' . (float) ($r['costo_unitario'] ?? 0) . '"'
                . '>'
                . self::tdCodigo($r['producto_codigo'] ?? '')
                . '<td><span class="fw-bold">' . htmlspecialchars($r['producto_nombre'] ?? '') . '</span></td>'
                . '<td class="small">' . htmlspecialchars($r['categoria_nombre'] ?? '') . '</td>'
                . '<td class="small">' . htmlspecialchars($r['bodega_nombre'] ?? '') . '</td>'
                . '<td class="text-end small text-info">' . number_format((float) ($r['consignado'] ?? 0), 2) . '</td>'
                . self::tdStockConSeguimiento($r, $modo)
                . '<td class="text-end fw-bold text-primary">' . number_format((float) ($r['stock_total'] ?? 0), 2) . '</td>'
                . '<td class="text-end small text-muted">' . number_format($stockMinimo, 2) . '</td>'
                . '<td class="text-end small text-muted">' . number_format($stockMaximo, 2) . '</td>'
                . '<td class="text-end">' . $costo . '</td>'
                . '<td class="text-end fw-bold text-primary">' . $valor . '</td>'
                . '</tr>';
        }

        // Desgloses por debajo de producto×bodega: cada uno muestra solo las columnas
        // que su agrupación puede afirmar ("Por lotes" no tiene una caducidad única, etc.).
        if (in_array($modo, self::DESGLOSES_EXISTENCIAS, true)) {
            $cad = !empty($r['fecha_caducidad']) ? date('d-m-Y', strtotime($r['fecha_caducidad'])) : '—';
            $celdasClave = '';
            if ($modo === 'LOTE' || $modo === 'LOTE_CADUCIDAD') {
                $celdasClave .= '<td class="small">' . self::texto($r['lote'] ?? null) . '</td>';
            }
            if ($modo === 'LOTE_CADUCIDAD') {
                $celdasClave .= '<td class="small">' . self::texto($r['nup'] ?? null) . '</td>';
            }
            if ($modo === 'CADUCIDAD' || $modo === 'LOTE_CADUCIDAD') {
                $celdasClave .= '<td class="small">' . $cad . '</td>';
            }
            return '<tr>'
                . self::tdCodigo($r['producto_codigo'] ?? '')
                . '<td><span class="fw-bold">' . htmlspecialchars($r['producto_nombre'] ?? '') . '</span></td>'
                . '<td class="small">' . htmlspecialchars($r['bodega_nombre'] ?? '') . '</td>'
                . $celdasClave
                . self::tdStockConSeguimiento($r, $modo)
                . '<td class="text-end small text-info">' . number_format((float) ($r['consignado'] ?? 0), 2) . '</td>'
                . '<td class="text-end fw-bold text-primary">' . number_format((float) ($r['stock_total'] ?? 0), 2) . '</td>'
                . '<td class="text-end">' . $costo . '</td>'
                . '<td class="text-end fw-bold text-primary">' . $valor . '</td>'
                . '</tr>';
        }

        // Agrupado por producto: el código viaja aparte del label y encabeza la fila.
        return '<tr>'
            . ($modo === 'PRODUCTO' ? self::tdCodigo($r['codigo_grupo'] ?? '') : '')
            . '<td class="fw-bold">' . htmlspecialchars((string) ($r['nombre_grupo'] ?? '')) . '</td>'
            . '<td class="text-center">' . (int) ($r['cantidad_productos'] ?? 0) . '</td>'
            . '<td class="text-end small text-info">' . number_format((float) ($r['consignado'] ?? 0), 2) . '</td>'
            . '<td class="text-end fw-bold">' . number_format((float) ($r['stock_actual'] ?? 0), 2) . '</td>'
            . '<td class="text-end fw-bold text-primary">' . number_format((float) ($r['stock_total'] ?? 0), 2) . '</td>'
            . '<td class="text-end small text-muted">' . number_format((float) ($r['stock_minimo'] ?? 0), 2) . '</td>'
            . '<td class="text-end">' . $costo . '</td>'
            . '<td class="text-end fw-bold text-primary">' . $valor . '</td>'
            . '</tr>';
    }

    /** Fila del desglose "Lote + consignación" de Existencias: una línea de consignación
     *  con su documento, su lote/NUP y el desglose de lo que ya salió del saldo. */
    private function filaExistenciaConsignacion(array $r): string
    {
        $saldo = (float) ($r['saldo'] ?? 0);
        $num   = static fn($v) => number_format((float) $v, 2);

        // sinFiltros = true: el modal es el de la pestaña Consignaciones y por defecto reaplica
        // los filtros de ESA pestaña, que aquí no son los que produjeron la fila.
        return '<tr class="ri-cv-row" style="cursor:pointer;" title="Ver detalle de la consignación"'
            . ' onclick="window.RI_Consignaciones.verDetalle(' . (int) ($r['id_consignacion'] ?? 0) . ', true)">'
            . '<td class="small text-nowrap">' . date('d-m-Y', strtotime($r['fecha_emision'] ?? '')) . '</td>'
            . '<td class="small text-nowrap">' . htmlspecialchars($r['secuencial'] ?? '') . '</td>'
            . '<td class="small">' . htmlspecialchars($r['cliente_nombre'] ?? '-') . '</td>'
            . '<td class="small">' . htmlspecialchars($r['vendedor_nombre'] ?? '-') . '</td>'
            . self::tdCodigo($r['producto_codigo'] ?? '')
            . '<td><span class="fw-bold">' . htmlspecialchars($r['producto_nombre'] ?? '') . '</span></td>'
            . '<td class="small">' . htmlspecialchars($r['numero_lote'] ?? '-') . '</td>'
            . '<td class="small">' . htmlspecialchars($r['nup'] ?? '-') . '</td>'
            . '<td class="small">' . htmlspecialchars($r['responsable_traslado_nombre'] ?? '-') . '</td>'
            . '<td class="small">' . htmlspecialchars($r['bodega_nombre'] ?? '-') . '</td>'
            . '<td class="text-end small">' . $num($r['cantidad_consignada'] ?? 0) . '</td>'
            . '<td class="text-end small">' . $num($r['cantidad_retornada'] ?? 0) . '</td>'
            . '<td class="text-end small">' . $num($r['cantidad_facturada'] ?? 0) . '</td>'
            . '<td class="text-end small" title="Entregado al cliente a cambio de otro producto (módulo Cambios de productos)">'
            . $num($r['cantidad_cambiada'] ?? 0) . '</td>'
            . '<td class="text-end fw-bold ' . ($saldo > 0 ? 'text-primary' : 'text-muted') . '">' . $num($saldo) . '</td>'
            . '</tr>';
    }

    private function filaMovimientos(array $r, string $modo): string
    {
        if ($modo === 'NINGUNO') {
            $cant = (float) ($r['cantidad'] ?? 0);
            $entrada = $cant > 0 ? number_format($cant, 2) : '-';
            $salida = $cant < 0 ? number_format(abs($cant), 2) : '-';
            $saldo = (float) ($r['saldo'] ?? 0);
            $cad = !empty($r['fecha_caducidad']) ? date('d-m-Y', strtotime($r['fecha_caducidad'])) : '-';
            return '<tr>'
                . self::tdCodigo($r['producto_codigo'] ?? '')
                . '<td class="small text-nowrap">' . date('d-m-Y H:i', strtotime($r['fecha_movimiento'] ?? '')) . '</td>'
                . '<td><span class="fw-bold">' . htmlspecialchars($r['producto_nombre'] ?? '') . '</span></td>'
                . '<td class="small">' . htmlspecialchars($r['bodega_nombre'] ?? '') . '</td>'
                . '<td class="text-center small text-uppercase">' . htmlspecialchars($r['tipo_movimiento'] ?? '') . '</td>'
                . '<td class="small">' . htmlspecialchars($r['origen_label'] ?? '') . '</td>'
                . '<td class="text-end text-success">' . $entrada . '</td>'
                . '<td class="text-end text-danger">' . $salida . '</td>'
                . '<td class="text-end fw-bold">' . number_format($saldo, 2) . '</td>'
                . '<td class="text-end small">' . number_format((float) ($r['costo_unitario'] ?? 0), 4) . '</td>'
                . '<td class="small">' . htmlspecialchars($r['numero_lote'] ?? '-') . '</td>'
                . '<td class="small">' . $cad . '</td>'
                . '<td class="small" style="max-width:280px;white-space:normal;word-break:break-word;">' . htmlspecialchars($r['observaciones'] ?? '-') . '</td>'
                . '</tr>';
        }

        $label = $r['nombre_grupo'] ?? '';
        if ($modo === 'FECHA' && !empty($label)) {
            $label = date('d/m/Y', strtotime((string) $label));
        }
        return '<tr>'
            . ($modo === 'PRODUCTO' ? self::tdCodigo($r['codigo_grupo'] ?? '') : '')
            . '<td class="fw-bold">' . htmlspecialchars((string) $label) . '</td>'
            . '<td class="text-center">' . (int) ($r['cantidad_movimientos'] ?? 0) . '</td>'
            . '<td class="text-end text-success">' . number_format((float) ($r['total_entradas'] ?? 0), 2) . '</td>'
            . '<td class="text-end text-danger">' . number_format((float) ($r['total_salidas'] ?? 0), 2) . '</td>'
            . '<td class="text-end fw-bold">' . number_format((float) ($r['saldo_neto'] ?? 0), 2) . '</td>'
            . '<td class="text-end small text-muted">' . number_format((float) ($r['costo_total'] ?? 0), 2) . '</td>'
            . '</tr>';
    }

    private function filaValorizacion(array $r, string $modo): string
    {
        return '<tr>'
            . ($modo === 'PRODUCTO' ? self::tdCodigo($r['codigo_grupo'] ?? '') : '')
            . '<td class="fw-bold">' . htmlspecialchars((string) ($r['nombre_grupo'] ?? '')) . '</td>'
            . '<td class="text-center">' . (int) ($r['cantidad_productos'] ?? 0) . '</td>'
            . '<td class="text-end">' . number_format((float) ($r['stock_actual'] ?? 0), 2) . '</td>'
            . '<td class="text-end">' . number_format((float) ($r['costo_promedio'] ?? 0), 4) . '</td>'
            . '<td class="text-end fw-bold text-primary">' . number_format((float) ($r['valor_total'] ?? 0), 2) . '</td>'
            . '</tr>';
    }

    private function filaConsignaciones(array $r, string $modo): string
    {
        if ($modo === 'NINGUNO') {
            $saldo = (float) ($r['saldo'] ?? 0);
            $badgesEstado = [
                'Entregada' => 'bg-success',
                'Emitida'   => 'bg-warning text-dark',
                'Anulada'   => 'bg-danger',
            ];
            $estado = $r['estado'] ?? '';
            $badgeClass = $badgesEstado[$estado] ?? 'bg-secondary';
            // Lote y NUP llegan agregados (string_agg) porque la fila es el documento completo:
            // si la consignación mezcla varios, se listan separados por coma y la celda recorta
            // con ellipsis dejando el valor entero en el title.
            $lotes = trim((string) ($r['lotes'] ?? '')) !== '' ? (string) $r['lotes'] : '-';
            $nups  = trim((string) ($r['nups']  ?? '')) !== '' ? (string) $r['nups']  : '-';
            // Los códigos llegan agregados por el mismo motivo: la fila es el documento completo,
            // que puede llevar varios productos. El detalle producto a producto está en el modal.
            $codigos = trim((string) ($r['codigos'] ?? '')) !== '' ? (string) $r['codigos'] : '-';

            $totalProductos = (float) ($r['total_productos'] ?? 0);
            $tituloTotal = 'Consignado ' . number_format($totalProductos, 2)
                . ' · Retornado ' . number_format((float) ($r['total_retornado'] ?? 0), 2)
                . ' · Facturado ' . number_format((float) ($r['total_facturado'] ?? 0), 2)
                . ' · A cambio ' . number_format((float) ($r['total_cambiado'] ?? 0), 2);

            return '<tr class="ri-cv-row" style="cursor:pointer;" onclick="window.RI_Consignaciones.verDetalle(' . (int) ($r['id_consignacion'] ?? 0) . ')" title="Ver detalle de productos">'
                . '<td class="small text-truncate fw-semibold" style="max-width:150px;" title="' . htmlspecialchars($codigos, ENT_QUOTES) . '">' . htmlspecialchars($codigos) . '</td>'
                . '<td class="small">' . date('d-m-Y', strtotime($r['fecha_emision'] ?? '')) . '<br><small class="text-muted">' . htmlspecialchars($r['secuencial'] ?? '') . '</small></td>'
                . '<td><span class="fw-bold">' . htmlspecialchars($r['cliente_nombre'] ?? '') . '</span><br><small class="text-muted">' . htmlspecialchars($r['cliente_identificacion'] ?? '') . '</small></td>'
                . '<td class="small">' . htmlspecialchars($r['vendedor_nombre'] ?? '-') . '</td>'
                . '<td class="small">' . htmlspecialchars($r['responsable_traslado_nombre'] ?? '-') . '</td>'
                . '<td class="small text-truncate" style="max-width:150px;" title="' . htmlspecialchars($lotes, ENT_QUOTES) . '">' . htmlspecialchars($lotes) . '</td>'
                . '<td class="small text-truncate" style="max-width:150px;" title="' . htmlspecialchars($nups, ENT_QUOTES) . '">' . htmlspecialchars($nups) . '</td>'
                . '<td class="text-end" title="' . htmlspecialchars($tituloTotal, ENT_QUOTES) . '">' . number_format($totalProductos, 2)
                . '<br><small class="text-muted">' . (int) ($r['cantidad_productos'] ?? 0) . ' ítem(s)</small></td>'
                . '<td class="text-end fw-bold">' . number_format($saldo, 2) . '</td>'
                . '<td class="text-center"><span class="badge ' . $badgeClass . '">' . htmlspecialchars($estado) . '</span></td>'
                . '</tr>';
        }

        return '<tr>'
            . ($modo === 'PRODUCTO' ? self::tdCodigo($r['codigo_grupo'] ?? '') : '')
            . '<td class="fw-bold">' . htmlspecialchars((string) ($r['nombre_grupo'] ?? '')) . '</td>'
            . '<td class="text-center">' . (int) ($r['cantidad_consignaciones'] ?? 0) . '</td>'
            . '<td class="text-end fw-bold">' . number_format((float) ($r['saldo'] ?? 0), 2) . '</td>'
            . '</tr>';
    }

    private function filaAuditoria(array $r): string
    {
        $cacheado = (float) ($r['cacheado'] ?? 0);
        $real = (float) ($r['real_kardex'] ?? 0);
        $diferencia = $cacheado - $real;
        $colorDif = $diferencia > 0 ? 'text-danger' : 'text-warning';
        return '<tr>'
            . self::tdCodigo($r['producto_codigo'] ?? '')
            . '<td><span class="fw-bold">' . htmlspecialchars($r['producto_nombre'] ?? '') . '</span></td>'
            . '<td class="small">' . htmlspecialchars($r['bodega_nombre'] ?? '') . '</td>'
            . '<td class="text-end">' . number_format($cacheado, 2) . '</td>'
            . '<td class="text-end fw-bold">' . number_format($real, 2) . '</td>'
            . '<td class="text-end fw-bold ' . $colorDif . '">' . ($diferencia > 0 ? '+' : '') . number_format($diferencia, 2) . '</td>'
            . '<td class="text-center">'
            . '<button type="button" class="btn btn-sm btn-outline-primary" title="Corregir: dejar el stock guardado igual al del kardex"'
            . ' onclick="window.RI_Auditoria.corregir(this)"'
            . ' data-id-producto="' . (int) ($r['id_producto'] ?? 0) . '"'
            . ' data-id-bodega="' . (int) ($r['id_bodega'] ?? 0) . '"'
            . ' data-producto-nombre="' . htmlspecialchars($r['producto_nombre'] ?? '', ENT_QUOTES) . '"'
            . ' data-bodega-nombre="' . htmlspecialchars($r['bodega_nombre'] ?? '', ENT_QUOTES) . '"'
            . ' data-cacheado="' . $cacheado . '" data-real="' . $real . '"'
            . '><i class="bi bi-check2-circle me-1"></i>Corregir</button>'
            . '</td>'
            . '</tr>';
    }

    /** Fila de línea de producto dentro del modal de detalle de una consignación. */
    private function filaConsignacionDetalleLinea(array $r): string
    {
        $idDetalle = (int) ($r['id_detalle'] ?? 0);
        $retornado = (float) ($r['cantidad_retornada'] ?? 0);
        $facturado = (float) ($r['cantidad_facturada'] ?? 0);

        $tdRetornado = $retornado > 0
            ? '<td class="text-end small"><a href="#" class="link-primary text-decoration-underline"'
                . ' onclick="window.RI_Consignaciones.verDocumentosLinea(' . $idDetalle . ', \'retorno\'); return false;"'
                . ' title="Ver retornos que explican esta cantidad">' . number_format($retornado, 2) . '</a></td>'
            : '<td class="text-end small">' . number_format($retornado, 2) . '</td>';

        $tdFacturado = $facturado > 0
            ? '<td class="text-end small"><a href="#" class="link-primary text-decoration-underline"'
                . ' onclick="window.RI_Consignaciones.verDocumentosLinea(' . $idDetalle . ', \'factura\'); return false;"'
                . ' title="Ver facturas que explican esta cantidad">' . number_format($facturado, 2) . '</a></td>'
            : '<td class="text-end small">' . number_format($facturado, 2) . '</td>';

        // Entregado a cambio (Cambios de productos Emitida): sale del saldo igual que lo facturado.
        $cambiado   = (float) ($r['cantidad_cambiada'] ?? 0);
        $tdCambiado = '<td class="text-end small"' . ($cambiado > 0 ? ' title="Entregado al cliente a cambio de otro producto (módulo Cambios de productos)"' : '') . '>'
            . number_format($cambiado, 2) . '</td>';

        $codigo = trim((string) ($r['producto_codigo'] ?? ''));

        return '<tr>'
            . '<td class="small text-nowrap">' . htmlspecialchars($codigo !== '' ? $codigo : '-') . '</td>'
            . '<td class="small">' . htmlspecialchars($r['producto_nombre'] ?? '') . '</td>'
            . '<td class="small">' . htmlspecialchars($r['bodega_nombre'] ?? '') . '</td>'
            . '<td class="small">' . htmlspecialchars($r['numero_lote'] ?? '-') . '</td>'
            . '<td class="small">' . htmlspecialchars($r['nup'] ?? '-') . '</td>'
            . '<td class="text-end small">' . number_format((float) ($r['cantidad_consignada'] ?? 0), 2) . '</td>'
            . $tdRetornado
            . $tdFacturado
            . $tdCambiado
            . '<td class="text-end small fw-bold">' . number_format((float) ($r['saldo'] ?? 0), 2) . '</td>'
            . '</tr>';
    }

    // ────────────────────────────────────────────────────────────────
    // EDICIÓN EN LÍNEA (Existencias): mínimo/máximo por bodega y categoría
    // ────────────────────────────────────────────────────────────────
    public function actualizarMinMaxAjax(): void
    {
        $this->requireActualizar();
        $this->requirePestana('existencias');
        header('Content-Type: application/json');

        try {
            $idEmpresa   = (int) $_SESSION['id_empresa'];
            $idUsuario   = (int) $_SESSION['id_usuario'];
            $idProducto  = (int) ($_REQUEST['id_producto'] ?? 0);
            $idBodega    = (int) ($_REQUEST['id_bodega'] ?? 0);
            $stockMinimo = (float) ($_REQUEST['stock_minimo'] ?? 0);
            $stockMaximo = (float) ($_REQUEST['stock_maximo'] ?? 0);

            if ($idProducto <= 0 || $idBodega <= 0) {
                throw new \InvalidArgumentException('Producto o bodega no válidos.');
            }
            $this->requireBodegaPermitida($idBodega);
            if ($stockMinimo < 0 || $stockMaximo < 0) {
                throw new \InvalidArgumentException('El mínimo y el máximo no pueden ser negativos.');
            }
            if ($stockMaximo > 0 && $stockMaximo < $stockMinimo) {
                throw new \InvalidArgumentException('El máximo no puede ser menor que el mínimo.');
            }

            $inventarioRepo = new InventarioRepository();
            $antes = $inventarioRepo->getProductoBodega($idProducto, $idBodega, $idEmpresa);
            if (!$antes) {
                throw new \InvalidArgumentException('El producto no tiene existencias registradas en esa bodega.');
            }

            $inventarioRepo->actualizarMinMax($idProducto, $idBodega, $idEmpresa, $stockMinimo, $stockMaximo, $idUsuario);

            (new \App\Services\LogSistemaService())->registrar(
                $idUsuario, $idEmpresa, 'actualizar_min_max', 'productos_bodegas', $idProducto,
                ['stock_minimo' => $antes['stock_minimo'], 'stock_maximo' => $antes['stock_maximo']],
                ['stock_minimo' => $stockMinimo, 'stock_maximo' => $stockMaximo]
            );

            echo json_encode(['ok' => true]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function actualizarCategoriaAjax(): void
    {
        $this->requireActualizar();
        $this->requirePestana('existencias');
        header('Content-Type: application/json');

        try {
            $idEmpresa    = (int) $_SESSION['id_empresa'];
            $idUsuario    = (int) $_SESSION['id_usuario'];
            $idProducto   = (int) ($_REQUEST['id_producto'] ?? 0);
            $idCategoria  = !empty($_REQUEST['id_categoria']) ? (int) $_REQUEST['id_categoria'] : null;

            if ($idProducto <= 0) {
                throw new \InvalidArgumentException('Producto no válido.');
            }

            $productoRepo = new ProductoRepository();
            $antes = $productoRepo->getDetalleCompleto($idProducto, $idEmpresa);
            if (!$antes) {
                throw new \InvalidArgumentException('El producto no existe.');
            }

            $productoRepo->actualizarCategoria($idProducto, $idEmpresa, $idCategoria, $idUsuario);

            (new \App\Services\LogSistemaService())->registrar(
                $idUsuario, $idEmpresa, 'actualizar_categoria', 'productos', $idProducto,
                ['id_categoria' => $antes['id_categoria']],
                ['id_categoria' => $idCategoria]
            );

            $categoriaNombre = 'Sin categoría';
            if ($idCategoria !== null) {
                $cat = (new CategoriaRepository())->getDetalleCompleto($idCategoria, $idEmpresa);
                $categoriaNombre = $cat['nombre'] ?? 'Sin categoría';
            }

            echo json_encode(['ok' => true, 'categoria_nombre' => $categoriaNombre]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Ajuste manual de inventario (entrada/salida) desde el modal de edición de Existencias.
     *  Reutiliza InventarioService::ajusteManual() — la misma lógica de negocio (kardex,
     *  costeo, validación de stock, auditoría) que usa el módulo de Inventario. */
    public function ajustarInventarioAjax(): void
    {
        $this->requireActualizar();
        $this->requirePestana('existencias');
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];

            $data = [
                'id_producto'     => (int) ($_REQUEST['id_producto'] ?? 0),
                'id_bodega'       => (int) ($_REQUEST['id_bodega'] ?? 0),
                'tipo_movimiento' => $_REQUEST['tipo_movimiento'] ?? '',
                'cantidad'        => (float) ($_REQUEST['cantidad'] ?? 0),
                'costo_unitario'  => (float) ($_REQUEST['costo_unitario'] ?? 0),
                'observaciones'   => trim($_REQUEST['observaciones'] ?? '') ?: 'Ajuste manual',
                'numero_lote'     => trim($_REQUEST['numero_lote'] ?? '') ?: null,
            ];

            if ($data['id_producto'] <= 0 || $data['id_bodega'] <= 0) {
                throw new \InvalidArgumentException('Producto o bodega no válidos.');
            }
            $this->requireBodegaPermitida($data['id_bodega']);
            if (!in_array($data['tipo_movimiento'], ['entrada', 'salida'], true)) {
                throw new \InvalidArgumentException('Tipo de ajuste no válido.');
            }
            if ($data['cantidad'] <= 0) {
                throw new \InvalidArgumentException('La cantidad debe ser mayor a cero.');
            }

            $service = new InventarioService(new InventarioRepository(), new LogSistemaService());
            $service->ajusteManual($data, $idEmpresa, $idUsuario);

            echo json_encode(['ok' => true]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Auditoría: deja productos_bodegas.stock_actual igual a la suma real del kardex. */
    public function corregirStockAuditoriaAjax(): void
    {
        $this->requireActualizar();
        $this->requirePestana('auditoria');
        header('Content-Type: application/json');

        try {
            $idUsuario = (int) $_SESSION['id_usuario'];
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idProducto = (int) ($_REQUEST['id_producto'] ?? 0);
            $idBodega = (int) ($_REQUEST['id_bodega'] ?? 0);

            if ($idProducto <= 0 || $idBodega <= 0) {
                throw new \InvalidArgumentException('Producto o bodega no válidos.');
            }
            $this->requireBodegaPermitida($idBodega);

            $resultado = $this->repository->corregirStockAuditoria($idProducto, $idBodega, $idEmpresa, $idUsuario);

            (new LogSistemaService())->registrar(
                $idUsuario, $idEmpresa, 'corregir_stock_auditoria', 'productos_bodegas', $idProducto,
                ['stock_actual' => $resultado['antes']],
                ['stock_actual' => $resultado['despues']]
            );

            echo json_encode(['ok' => true, 'stock_actual' => $resultado['despues']]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Corrige TODAS las discrepancias visibles con los filtros actuales, para la empresa activa en sesión. */
    public function corregirTodoAuditoriaAjax(): void
    {
        $this->requireActualizar();
        $this->requirePestana('auditoria');
        header('Content-Type: application/json');

        try {
            $idUsuario = (int) $_SESSION['id_usuario'];
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $filtros = $this->getFiltrosAuditoria();

            $filas = $this->repository->getAuditoriaStock($idEmpresa, $filtros);

            $corregidas = 0;
            foreach ($filas as $f) {
                $resultado = $this->repository->corregirStockAuditoria(
                    (int) $f['id_producto'],
                    (int) $f['id_bodega'],
                    $idEmpresa,
                    $idUsuario
                );

                (new LogSistemaService())->registrar(
                    $idUsuario, $idEmpresa, 'corregir_stock_auditoria', 'productos_bodegas', (int) $f['id_producto'],
                    ['stock_actual' => $resultado['antes']],
                    ['stock_actual' => $resultado['despues']]
                );

                $corregidas++;
            }

            echo json_encode(['ok' => true, 'corregidas' => $corregidas]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ────────────────────────────────────────────────────────────────
    // DETALLE DE UNA CONSIGNACIÓN (modal, click en la fila del listado)
    // ────────────────────────────────────────────────────────────────
    public function verConsignacionDetalleAjax(): void
    {
        $this->requireLeer();
        $this->requirePestana('consignaciones');
        $this->liberarSesion();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idConsignacion = (int) ($_REQUEST['id'] ?? 0);
            if ($idConsignacion <= 0) {
                throw new \InvalidArgumentException('Consignación no válida.');
            }

            // El modal reaplica los mismos filtros de línea del listado para que sus totales
            // cuadren con el "Total productos" y el "Saldo" de la fila. Con `sin_filtros=1`
            // (enlace "Ver todas las líneas" del modal) se muestra el documento completo.
            $filtrosLinea = empty($_REQUEST['sin_filtros'])
                ? array_intersect_key(
                    $this->getFiltrosConsignaciones(),
                    array_flip(ReporteInventarioRepository::FILTROS_LINEA_CONSIGNACION)
                )
                : [];
            $hayFiltros = !empty(array_filter($filtrosLinea, fn($v) => $v !== '' && $v !== null));
            // Aparte de $hayFiltros: no es un filtro de la búsqueda sino lo que este usuario
            // puede ver, así que también se aplica con "Ver todas las líneas".
            $filtrosLinea['bodegas_denegadas'] = $this->bodegasDenegadas();

            $lineas = $this->repository->getConsignacionDetalleLineas($idEmpresa, $idConsignacion, $filtrosLinea);
            if (empty($lineas)) {
                echo json_encode(['ok' => false, 'error' => 'No se encontró la consignación o no pertenece a esta empresa.']);
                exit;
            }
            $cab = $lineas[0];

            $suma = fn(string $campo) => array_sum(array_map(fn($l) => (float) ($l[$campo] ?? 0), $lineas));

            echo json_encode([
                'ok' => true,
                'cabecera' => [
                    'secuencial'    => $cab['secuencial'] ?? '',
                    'fecha_emision' => !empty($cab['fecha_emision']) ? date('d-m-Y', strtotime($cab['fecha_emision'])) : '',
                    'cliente'       => $cab['cliente_nombre'] ?? '',
                    'identificacion'=> $cab['cliente_identificacion'] ?? '',
                    'vendedor'      => $cab['vendedor_nombre'] ?? '-',
                    'responsable'   => $cab['responsable_traslado_nombre'] ?? '-',
                    'estado'        => $cab['estado'] ?? '',
                ],
                'filtrado' => $hayFiltros,
                'totales'  => [
                    'consignado' => number_format($suma('cantidad_consignada'), 2),
                    'retornado'  => number_format($suma('cantidad_retornada'), 2),
                    'facturado'  => number_format($suma('cantidad_facturada'), 2),
                    'cambiado'   => number_format($suma('cantidad_cambiada'), 2),
                    'saldo'      => number_format($suma('saldo'), 2),
                ],
                'rows' => $this->renderRows($lineas, fn($r) => $this->filaConsignacionDetalleLinea($r), 10),
            ]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Fila de documento (retorno o factura) dentro del sub-modal de una línea de consignación.
     *  En "factura" el documento es la FACTURA DE VENTA (establecimiento-punto-secuencial), no el
     *  documento interno de facturación de consignación, y el PDF apunta al de Facturas de Venta. */
    private function filaDocumentoLinea(array $r, string $tipo, bool $puedeVerPdf): string
    {
        $base = rtrim(BASE_URL, '/');

        if ($tipo === 'retorno') {
            $fecha  = $r['fecha_retorno'] ?? '';
            $doc    = trim(($r['serie'] ?? '') . '-' . ($r['secuencial'] ?? ''), '-');
            $sub    = '';
            $urlPdf = $base . '/modulos/retornos-cv/pdf?id=' . (int) ($r['id'] ?? 0);
        } else {
            $fecha = $r['fecha_emision'] ?? '';
            // La factura de venta se numera establecimiento-punto_emision-secuencial. Si ya no
            // existe en ventas_cabecera, se cae al número que guardó la facturación.
            $partes = array_filter([
                trim((string) ($r['establecimiento'] ?? '')),
                trim((string) ($r['punto_emision'] ?? '')),
                trim((string) ($r['secuencial'] ?? '')),
            ], fn($p) => $p !== '');
            $doc = count($partes) === 3 ? implode('-', $partes) : trim((string) ($r['numero_factura'] ?? ''));
            if ($doc === '') $doc = '-';

            // El PDF solo se ofrece si la factura sigue existiendo (id_venta viene del LEFT JOIN
            // contra ventas_cabecera): con solo `id_factura` se enlazaba a un documento borrado y
            // el botón terminaba en un 404.
            $idFactura = (int) ($r['id_venta'] ?? 0);
            $urlPdf = ($idFactura > 0 && empty($r['factura_eliminada']))
                ? $base . '/modulos/factura-venta/exportar-pdf-ajax?id=' . $idFactura
                : '';

            $estadoFac = trim((string) ($r['estado'] ?? ''));
            $sub = ($estadoFac !== '' && strcasecmp($estadoFac, 'facturada') !== 0)
                ? '<br><small class="text-muted">' . htmlspecialchars($estadoFac) . '</small>'
                : '';
        }

        // Sin permiso de lectura sobre el módulo dueño del documento no se emite la celda: el JS
        // oculta también su <th>, así no queda una columna llena de guiones para quien no puede
        // abrir ninguno. El guard real sigue viviendo en el módulo destino (requireLeer), esto
        // es solo no ofrecer un botón que iba a terminar en "no tiene permiso".
        if (!$puedeVerPdf) {
            $tdPdf = '';
        } elseif ($urlPdf !== '') {
            $tdPdf = '<td class="text-center"><a href="' . htmlspecialchars($urlPdf, ENT_QUOTES) . '" target="_blank" rel="noopener"'
                . ' class="btn btn-sm btn-outline-danger py-0 px-1" title="Imprimir PDF del documento">'
                . '<i class="bi bi-file-earmark-pdf"></i></a></td>';
        } else {
            $tdPdf = '<td class="text-center"><span class="text-muted small" title="El documento ya no está disponible">-</span></td>';
        }

        return '<tr>'
            . '<td class="small">' . (!empty($fecha) ? date('d-m-Y', strtotime($fecha)) : '-') . '</td>'
            . '<td class="small fw-bold">' . htmlspecialchars($doc) . $sub . '</td>'
            . '<td class="text-end small">' . number_format((float) ($r['cantidad'] ?? 0), 2) . '</td>'
            . '<td class="text-end small">' . number_format((float) ($r['total'] ?? 0), 2) . '</td>'
            . $tdPdf
            . '</tr>';
    }

    /** Documentos (retornos o facturas) que explican la cantidad Retornado/Facturado de una línea de consignación. */
    public function verDocumentosLineaConsignacionAjax(): void
    {
        $this->requireLeer();
        $this->requirePestana('consignaciones');
        $this->liberarSesion();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idDetalle = (int) ($_REQUEST['id_detalle'] ?? 0);
            $tipo = $_REQUEST['tipo'] ?? '';
            if ($idDetalle <= 0 || !in_array($tipo, ['retorno', 'factura'], true)) {
                throw new \InvalidArgumentException('Parámetros no válidos.');
            }
            // La línea llega por id: si está en una bodega que el usuario no ve, el sub-modal
            // responde igual que con un id inválido (no se filtra el motivo).
            $denegadas = $this->bodegasDenegadas();
            if ($denegadas && !$this->repository->lineaConsignacionVisible($idEmpresa, $idDetalle, $denegadas)) {
                throw new \InvalidArgumentException('Parámetros no válidos.');
            }

            $rows = $tipo === 'retorno'
                ? $this->repository->getRetornosDeLineaConsignacion($idEmpresa, $idDetalle)
                : $this->repository->getFacturasDeLineaConsignacion($idEmpresa, $idDetalle);

            // El PDF se sirve desde el módulo dueño del documento, que exige su propio permiso de
            // lectura: si el usuario no lo tiene, el botón no se muestra en lugar de ofrecerle un
            // enlace que iba a rebotar. Se resuelve una vez por petición (Permisos cachea).
            $rutaDocumento = $tipo === 'retorno' ? 'modulos/retornos-cv' : 'modulos/factura-venta';
            $puedeVerPdf   = !empty($this->permisosModuloPorRuta($rutaDocumento)['ver']);

            echo json_encode([
                'ok'        => true,
                'tipo'      => $tipo,
                'puede_pdf' => $puedeVerPdf,
                'rows'      => $this->renderRows($rows, fn($r) => $this->filaDocumentoLinea($r, $tipo, $puedeVerPdf), $puedeVerPdf ? 5 : 4),
            ]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * PDF del ESTADO completo de una consignación (botón del modal de detalle): el mismo
     * diseño del comprobante de Consignaciones de Ventas, pero con las cantidades
     * retornadas/facturadas/a cambio, el saldo por línea y el resumen del saldo en poder
     * del cliente.
     * Siempre es el documento COMPLETO (no reaplica los filtros de línea del listado):
     * es un estado del documento, no de la búsqueda. Usa el modelo general aunque la
     * empresa tenga una plantilla de diseño activa: la plantilla no conoce la columna de
     * saldo ni el resumen final.
     */
    public function consignacionPdf(): void
    {
        $this->requireLeer();
        $this->requirePestana('consignaciones');
        $this->liberarSesion();

        $idEmpresa      = (int) $_SESSION['id_empresa'];
        $idConsignacion = (int) ($_REQUEST['id'] ?? 0);
        if ($idConsignacion <= 0) {
            http_response_code(400);
            echo 'Consignación no válida.';
            exit;
        }

        try {
            $service = new \App\Services\modulos\ConsignacionVentaService(
                new \App\repositories\modulos\ConsignacionVentaRepository(),
                new \App\Rules\modulos\ConsignacionVentaRules(),
                new LogSistemaService()
            );
            $cons = $service->getDetalleCompleto($idConsignacion, $idEmpresa);
            // Mismo 404 que un documento inexistente: si ninguna de sus líneas está en una
            // bodega visible para este usuario, el documento no existe para él.
            $denegadas = $this->bodegasDenegadas();
            if (!$cons || ($denegadas && !$this->repository->consignacionVisible($idEmpresa, $idConsignacion, $denegadas))) {
                http_response_code(404);
                echo 'No se encontró la consignación o no pertenece a esta empresa.';
                exit;
            }

            // Mismas fuentes que el PDF de Consignaciones de Ventas; el saldo se calcula
            // por línea igual que en la pestaña (consignado − retornado − facturado − a cambio).
            $retornado = $service->getRetornadoPorLinea($idConsignacion, $idEmpresa);
            $facturado = $service->getFacturadoPorLinea($idConsignacion, $idEmpresa);
            $cambiado  = $service->getCambiadoPorLinea($idConsignacion, $idEmpresa);
            $detalles  = $cons['detalles'] ?? [];
            foreach ($detalles as &$d) {
                $idDet = (int) ($d['id'] ?? 0);
                $d['retornado'] = (float) ($retornado[$idDet] ?? 0);
                $d['facturado'] = (float) ($facturado[$idDet] ?? 0);
                $d['cambiado']  = (float) ($cambiado[$idDet] ?? 0);
                $d['saldo']     = (float) ($d['cantidad'] ?? 0) - $d['retornado'] - $d['facturado'] - $d['cambiado'];
            }
            unset($d);

            $empresaModel = new Empresa();
            $empresa      = $empresaModel->getPorId($idEmpresa) ?? [];
            $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
            if (!empty($establecimientos[0]['logo_ruta'])) {
                $empresa['logo_ruta'] = $establecimientos[0]['logo_ruta'];
            }

            (new \App\Services\modulos\ConsignacionVentaPdfService())->generar($cons, $detalles, $empresa, 'D', ['completo' => true]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            http_response_code(500);
            echo 'Error al generar el PDF: ' . $e->getMessage();
        }
        exit;
    }

    // ────────────────────────────────────────────────────────────────
    // SEGUIMIENTO DE UN STOCK NEGATIVO (Existencias)
    // ────────────────────────────────────────────────────────────────
    /** Tope de movimientos del seguimiento. Un negativo se explica con el historial de UNA
     *  clave (producto+bodega+lote), que casi nunca pasa de unas decenas de movimientos; el
     *  tope está para el caso patológico de un producto con años de rotación y sin lote. */
    private const LIMITE_SEGUIMIENTO = 1000;

    /**
     * Explica el stock negativo de una fila de Existencias: los movimientos de kardex que la
     * componen, en orden cronológico y con saldo corrido, más un diagnóstico (en qué
     * movimiento cruzó a negativo) y los demás lotes del mismo producto y bodega.
     *
     * La clave llega en `cols` (qué columnas agrupan esa fila) con su valor al lado, y NO se
     * deduce del desglose que haya en pantalla: la fila ya sabe cuál es su clave, y el
     * selector Detalle puede haber cambiado entre que se pintó la tabla y se pulsó el número.
     */
    public function seguimientoNegativoAjax(): void
    {
        $this->requireLeer();
        $this->requirePestana('existencias');
        $this->liberarSesion();
        header('Content-Type: application/json');

        try {
            $idEmpresa  = (int) $_SESSION['id_empresa'];
            $idProducto = (int) ($_REQUEST['id_producto'] ?? 0);
            $idBodega   = (int) ($_REQUEST['id_bodega'] ?? 0);
            if ($idProducto <= 0 || $idBodega <= 0) {
                throw new \InvalidArgumentException('Parámetros no válidos.');
            }
            // Misma regla que el resto del reporte: una bodega sin acceso responde igual que
            // un id inexistente, sin decir que el motivo es el permiso.
            if (in_array($idBodega, $this->bodegasDenegadas(), true)) {
                throw new \InvalidArgumentException('Parámetros no válidos.');
            }

            // Las columnas que no vengan en `cols` NO forman parte del grupo: el seguimiento
            // suma entonces todos sus valores, igual que hace la fila que se está explicando.
            $clave = [];
            foreach (explode(',', (string) ($_REQUEST['cols'] ?? '')) as $col) {
                $col = trim($col);
                if ($col !== '') {
                    $clave[$col] = (string) ($_REQUEST[$col] ?? '');
                }
            }

            // Los filtros con los que se pintó la tabla, tal como los devolvió el Mostrar que
            // la generó (`filtros_saldo`). Se aceptan solo los que recortan el kardex: el
            // resto no cambia la suma de una fila y no tiene por qué llegar hasta aquí.
            $filtros = ['bodegas_denegadas' => $this->bodegasDenegadas()];
            foreach (['fecha_corte', 'numero_lote', 'nup', 'fecha_caducidad_desde', 'fecha_caducidad_hasta'] as $f) {
                $valor = trim((string) ($_REQUEST[$f] ?? ''));
                if ($valor !== '') {
                    $filtros[$f] = $valor;
                }
            }

            $rows = $this->repository->getSeguimientoClave(
                $idEmpresa, $idProducto, $idBodega, $clave, $filtros, self::LIMITE_SEGUIMIENTO
            );
            $hayMas = count($rows) > self::LIMITE_SEGUIMIENTO;
            if ($hayMas) {
                $rows = array_slice($rows, 0, self::LIMITE_SEGUIMIENTO);
            }

            $resumen = self::resumenSeguimiento($rows, $hayMas);
            // Los otros lotes se listan SIN el filtro de lote/NUP/caducidad, y solo con el
            // corte: esta tabla existe justamente para encontrar el lote gemelo que el filtro
            // de la pantalla estaría escondiendo.
            $grupos  = $this->repository->getGruposDeProductoBodega(
                $idEmpresa, $idProducto, $idBodega, (string) ($filtros['fecha_corte'] ?? '')
            );

            echo json_encode([
                'ok'      => true,
                'resumen' => $resumen,
                'rows'    => $this->renderRows($rows, fn($r) => self::filaSeguimiento($r, $resumen['id_cruce']), 11),
                'grupos'  => $this->renderRows($grupos, fn($r) => self::filaGrupoProductoBodega($r, $clave), 6),
            ]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Diagnóstico del seguimiento, calculado en PHP sobre las filas ya traídas (son pocas y
     * ya están en memoria: repetir la consulta en SQL solo para contarlas no aporta).
     *
     * Lo que de verdad responde "¿por qué está en negativo?" es el primer movimiento en que
     * el saldo corrido cruza por debajo de 0, así que se identifica esa fila (`id_cruce`)
     * para resaltarla. Los dos casos que más se repiten se nombran aparte, porque el usuario
     * no tiene por qué deducirlos de la tabla:
     *  - `sin_entradas`: la clave no tiene NI UNA entrada, solo salidas. Típico de un lote
     *    que se facturó sin haberse ingresado nunca con ese lote.
     *  - `otro_ambiente`: hay movimientos de un tipo_ambiente distinto al de la empresa.
     *    Suman en Existencias pero NO se ven en la pestaña Movimientos, así que el negativo
     *    parece salir de la nada mientras no se sepa que están ahí.
     */
    private static function resumenSeguimiento(array $rows, bool $hayMas): array
    {
        $entradas = 0.0;
        $salidas  = 0.0;
        $otroAmbiente = 0;
        $idCruce = null;
        $cruce = null;

        foreach ($rows as $r) {
            $cantidad = (float) $r['cantidad'];
            if ($cantidad >= 0) {
                $entradas += $cantidad;
            } else {
                $salidas += abs($cantidad);
            }
            if (\App\Helpers\Booleano::es($r['otro_ambiente'] ?? false)) {
                $otroAmbiente++;
            }
            if ($idCruce === null && (float) $r['saldo'] < 0) {
                $idCruce = (int) $r['id'];
                $cruce = [
                    'fecha'         => self::fechaHora($r['fecha_movimiento'] ?? null),
                    'origen'        => (string) ($r['origen_label'] ?? ''),
                    'tipo'          => (string) ($r['tipo_movimiento'] ?? ''),
                    'referencia_id' => $r['referencia_id'] !== null ? (int) $r['referencia_id'] : null,
                    'cantidad'      => $cantidad,
                    'saldo'         => (float) $r['saldo'],
                ];
            }
        }

        $ultima = $rows ? end($rows) : null;

        return [
            'total_movimientos' => count($rows),
            'entradas'      => $entradas,
            'salidas'       => $salidas,
            'saldo_final'   => $ultima ? (float) $ultima['saldo'] : 0.0,
            'id_cruce'      => $idCruce,
            'cruce'         => $cruce,
            'sin_entradas'  => !empty($rows) && $entradas == 0.0,
            'otro_ambiente' => $otroAmbiente,
            'primero'       => $rows ? self::fechaHora($rows[0]['fecha_movimiento'] ?? null) : '',
            'ultimo'        => $ultima ? self::fechaHora($ultima['fecha_movimiento'] ?? null) : '',
            // Con el listado recortado, el saldo final mostrado NO es el de la fila: hay que
            // decirlo, o el usuario creerá que el número del reporte está mal.
            'truncado'      => $hayMas,
        ];
    }

    /** Fecha del sistema: d-m-Y H:i:s (regla de UI). Cadena vacía si no hay fecha. */
    private static function fechaHora(?string $fecha): string
    {
        return !empty($fecha) ? date('d-m-Y H:i:s', strtotime($fecha)) : '';
    }

    /** Fila del seguimiento. Se resalta la del cruce a negativo y se marcan las que están en
     *  otro tipo_ambiente, que son justo las que no se ven en la pestaña Movimientos. */
    private static function filaSeguimiento(array $r, ?int $idCruce): string
    {
        $cantidad = (float) $r['cantidad'];
        $saldo    = (float) $r['saldo'];
        $esCruce  = $idCruce !== null && (int) $r['id'] === $idCruce;
        $otroAmb  = \App\Helpers\Booleano::es($r['otro_ambiente'] ?? false);
        $cad      = !empty($r['fecha_caducidad']) ? date('d-m-Y', strtotime((string) $r['fecha_caducidad'])) : '—';
        $obs      = trim((string) ($r['observaciones'] ?? ''));

        return '<tr' . ($esCruce ? ' class="table-danger"' : '') . '>'
            . '<td class="small text-nowrap">' . self::fechaHora($r['fecha_movimiento'] ?? null)
                . ($esCruce ? ' <i class="bi bi-arrow-down-circle-fill text-danger ms-1" title="Aquí el saldo cruza a negativo"></i>' : '')
                . '</td>'
            . '<td class="small">' . htmlspecialchars((string) ($r['tipo_movimiento'] ?? '')) . '</td>'
            . '<td class="small">' . htmlspecialchars((string) ($r['origen_label'] ?? ''))
                . ($otroAmb ? ' <span class="badge bg-warning text-dark" title="Movimiento de otro ambiente: no aparece en la pestaña Movimientos">otro ambiente</span>' : '')
                . '</td>'
            . '<td class="small text-muted">' . ($r['referencia_id'] !== null ? (int) $r['referencia_id'] : '—') . '</td>'
            . '<td class="small">' . self::texto($r['numero_lote'] ?? null) . '</td>'
            . '<td class="small">' . self::texto($r['nup'] ?? null) . '</td>'
            . '<td class="small">' . $cad . '</td>'
            . '<td class="text-end fw-bold ' . ($cantidad < 0 ? 'text-danger' : 'text-success') . '">' . number_format($cantidad, 2) . '</td>'
            . '<td class="text-end fw-bold ' . ($saldo < 0 ? 'text-danger' : '') . '">' . number_format($saldo, 2) . '</td>'
            . '<td class="small">' . self::texto($r['usuario_nombre'] ?? null) . '</td>'
            . '<td class="small text-muted" style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"'
                . ' title="' . htmlspecialchars($obs, ENT_QUOTES) . '">' . htmlspecialchars($obs !== '' ? $obs : '—') . '</td>'
            . '</tr>';
    }

    /** Fila de "otros grupos del mismo producto y bodega". Marca el grupo que se está
     *  siguiendo y resalta los negativos: son los candidatos a ser el mismo lote escrito
     *  de otra forma. */
    private static function filaGrupoProductoBodega(array $r, array $clave): string
    {
        $saldo  = (float) $r['saldo'];
        $lote   = (string) ($r['numero_lote'] ?? '');
        $nup    = (string) ($r['nup'] ?? '');
        $cadRaw = (string) ($r['fecha_caducidad'] ?? '');

        // "El que se está siguiendo" se decide SOLO con las columnas que forman la clave: en
        // "Por lotes", dos grupos con el mismo lote y distinta caducidad son la misma fila.
        // Con la clave vacía ("En general") no se marca ninguno: la fila los agrega TODOS, así
        // que marcarlos sería teñir la tabla entera y perder justo lo que distingue unos de
        // otros. Ahí esta tabla no muestra "los otros", sino de qué se compone la fila.
        $esActual = $clave !== [];
        foreach (['lote' => $lote, 'nup' => $nup, 'caducidad' => $cadRaw] as $col => $valor) {
            if (array_key_exists($col, $clave) && (string) $clave[$col] !== $valor) {
                $esActual = false;
                break;
            }
        }

        return '<tr' . ($esActual ? ' class="table-primary"' : '') . '>'
            . '<td class="small">' . self::texto($lote)
                . ($esActual ? ' <span class="badge bg-primary">esta fila</span>' : '') . '</td>'
            . '<td class="small">' . self::texto($nup) . '</td>'
            . '<td class="small">' . ($cadRaw !== '' ? date('d-m-Y', strtotime($cadRaw)) : '—') . '</td>'
            . '<td class="text-end fw-bold ' . ($saldo < 0 ? 'text-danger' : '') . '">' . number_format($saldo, 2) . '</td>'
            . '<td class="text-end small text-muted">' . (int) $r['movimientos'] . '</td>'
            . '<td class="small text-nowrap text-muted">' . self::fechaHora($r['ultimo_movimiento'] ?? null) . '</td>'
            . '</tr>';
    }

    // ────────────────────────────────────────────────────────────────
    // AUTOCOMPLETAR
    // ────────────────────────────────────────────────────────────────
    public function getProductosAjax(): void
    {
        $this->requireLeer();
        $this->liberarSesion();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['q'] ?? '');

        $repo   = new ProductoRepository();
        $result = $repo->getListado($idEmpresa, $buscar, 1, 15, 'nombre', 'ASC');

        echo json_encode(['ok' => true, 'data' => $result['rows']]);
        exit;
    }

    public function getClientesAjax(): void
    {
        $this->requireLeer();
        $this->liberarSesion();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['q'] ?? '');

        $repo   = new ClienteRepository();
        $result = $repo->getListado($idEmpresa, $buscar, 1, 15, 'nombre', 'ASC');

        $data = array_map(fn($row) => [
            'id'              => $row['id'],
            'nombre'          => $row['nombre'] ?? '',
            'identificacion'  => $row['identificacion'] ?? '',
        ], $result['rows'] ?? []);

        echo json_encode(['ok' => true, 'data' => $data]);
        exit;
    }

    // ────────────────────────────────────────────────────────────────
    // EXPORTACIÓN
    // ────────────────────────────────────────────────────────────────
    public function exportExcel(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $tab = $this->normalizarPestana($_REQUEST['tab'] ?? 'existencias');
        $this->requirePestana($tab);
        $this->liberarSesion();

        [$headers, $exportData, $titulo] = $this->datosExport($idEmpresa, $tab);

        try {
            $empresa       = (new Empresa())->getPorId($idEmpresa);
            $nombreEmpresa = $empresa['nombre'] ?? '';

            $reportService = new \App\Services\ReportService();
            $reportService->exportToExcel($titulo, $headers, $exportData, $titulo, $nombreEmpresa);
            exit;
        } catch (\Throwable $e) {
            echo 'Error al generar Excel: ' . $e->getMessage();
        }
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $tab = $this->normalizarPestana($_REQUEST['tab'] ?? 'existencias');
        $this->requirePestana($tab);
        $this->liberarSesion();

        [$headers, $exportData, $titulo] = $this->datosExport($idEmpresa, $tab);

        try {
            $empresa       = (new Empresa())->getPorId($idEmpresa) ?? [];
            $nombreEmpresa = $empresa['nombre'] ?? 'REPORTE DE INVENTARIOS';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) require_once $autoload;

            ob_start();
            ?>
            <style>
                table { width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 8.5pt; margin: 0 auto 20px auto; }
                th { background: #f2f2f2; border: 1px solid #ccc; padding: 5px; text-align: center; }
                td { border: 1px solid #ccc; padding: 5px; }
                .text-end { text-align: right; }
                .header { text-align: center; margin-bottom: 20px; }
            </style>
            <div class="header">
                <h2><?= htmlspecialchars($nombreEmpresa) ?></h2>
                <h3><?= htmlspecialchars($titulo) ?></h3>
                <p>Fecha de reporte: <?= date('d-m-Y H:i:s') ?></p>
            </div>
            <table>
                <thead>
                    <tr><?php foreach ($headers as $h): ?><th><?= htmlspecialchars($h) ?></th><?php endforeach; ?></tr>
                </thead>
                <tbody>
                    <?php foreach ($exportData as $row): ?>
                        <tr>
                            <?php foreach ($row as $i => $val): ?>
                                <td class="<?= is_numeric($val) ? 'text-end' : '' ?>"><?= htmlspecialchars((string) $val) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php
            $html = ob_get_clean();
            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $html2pdf->writeHTML($html);
            $html2pdf->output('ReporteInventarios_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
    }

    /**
     * Si la exportación llegó al tope, lo dice en una última fila del propio archivo:
     * un Excel recortado en silencio es peor que uno que avisa de que falta algo.
     */
    private static function recortarExport(array $rows, int $nColumnas): array
    {
        $tope = ReporteInventarioRepository::LIMITE_FILAS_EXPORT;
        if (count($rows) <= $tope) {
            return $rows;
        }
        $rows = array_slice($rows, 0, $tope);
        $rows[] = array_pad(
            ['*** Listado recortado en ' . number_format($tope) . ' filas. Afina los filtros para exportarlo completo. ***'],
            $nColumnas,
            ''
        );
        return $rows;
    }

    /** @return array{0: array, 1: array, 2: string} [headers, filas, título] */
    private function datosExport(int $idEmpresa, string $tab): array
    {
        switch ($tab) {
            case 'movimientos':
                $filtros = $this->getFiltrosMovimientos();
                $modo = $filtros['agrupar_por'];
                $rows = match ($modo) {
                    'PRODUCTO' => $this->repository->getMovimientosAgrupadoProducto($idEmpresa, $filtros),
                    'BODEGA'   => $this->repository->getMovimientosAgrupadoBodega($idEmpresa, $filtros),
                    'TIPO'     => $this->repository->getMovimientosAgrupadoTipo($idEmpresa, $filtros),
                    'ORIGEN'   => $this->repository->getMovimientosAgrupadoOrigen($idEmpresa, $filtros),
                    'FECHA'    => $this->repository->getMovimientosAgrupadoFecha($idEmpresa, $filtros),
                    'MES'      => $this->repository->getMovimientosAgrupadoMes($idEmpresa, $filtros),
                    default    => $this->repository->getMovimientosDetalle($idEmpresa, $filtros, ReporteInventarioRepository::LIMITE_FILAS_EXPORT),
                };
                if ($modo === 'NINGUNO') {
                    $headers = ['Código', 'Fecha', 'Producto', 'Bodega', 'Tipo', 'Origen', 'Entradas', 'Salidas', 'Saldo', 'Costo Unit.', 'Lote', 'Observaciones'];
                    $data = array_map(function ($r) {
                        $cant = (float) $r['cantidad'];
                        return [
                            $r['producto_codigo'] ?? '',
                            date('d-m-Y H:i', strtotime($r['fecha_movimiento'])),
                            $r['producto_nombre'] ?? '', $r['bodega_nombre'] ?? '',
                            strtoupper($r['tipo_movimiento'] ?? ''), $r['origen_label'] ?? '',
                            $cant > 0 ? $cant : 0, $cant < 0 ? abs($cant) : 0, (float) $r['saldo'],
                            (float) $r['costo_unitario'], $r['numero_lote'] ?? '', $r['observaciones'] ?? '',
                        ];
                    }, $rows);
                } elseif ($modo === 'PRODUCTO') {
                    $headers = ['Código', 'Producto', 'Movimientos', 'Entradas', 'Salidas', 'Saldo neto', 'Costo total'];
                    $data = array_map(fn($r) => [
                        (string) ($r['codigo_grupo'] ?? ''), (string) $r['nombre_grupo'], (int) $r['cantidad_movimientos'],
                        (float) $r['total_entradas'], (float) $r['total_salidas'],
                        (float) $r['saldo_neto'], (float) $r['costo_total'],
                    ], $rows);
                } else {
                    $headers = ['Grupo', 'Movimientos', 'Entradas', 'Salidas', 'Saldo neto', 'Costo total'];
                    $data = array_map(fn($r) => [
                        (string) $r['nombre_grupo'], (int) $r['cantidad_movimientos'],
                        (float) $r['total_entradas'], (float) $r['total_salidas'],
                        (float) $r['saldo_neto'], (float) $r['costo_total'],
                    ], $rows);
                }
                return [$headers, self::recortarExport($data, count($headers)), 'Movimientos de Inventario'];

            case 'valorizacion':
                $filtros = $this->getFiltrosValorizacion();
                $modo = self::modoValorizacion($filtros['agrupar_por']);
                $rows = match ($modo) {
                    'CATEGORIA' => $this->repository->getValorizacionAgrupadoCategoria($idEmpresa, $filtros),
                    'BODEGA'    => $this->repository->getValorizacionAgrupadoBodega($idEmpresa, $filtros),
                    'MARCA'     => $this->repository->getValorizacionAgrupadoMarca($idEmpresa, $filtros),
                    default     => $this->repository->getValorizacionAgrupadoProducto($idEmpresa, $filtros),
                };
                if ($modo === 'PRODUCTO') {
                    $headers = ['Código', 'Producto', 'Productos', 'Stock', 'Costo promedio', 'Valor total'];
                    $data = array_map(fn($r) => [
                        (string) ($r['codigo_grupo'] ?? ''), (string) $r['nombre_grupo'], (int) $r['cantidad_productos'],
                        (float) $r['stock_actual'], (float) $r['costo_promedio'], (float) $r['valor_total'],
                    ], $rows);
                } else {
                    $headers = ['Grupo', 'Productos', 'Stock', 'Costo promedio', 'Valor total'];
                    $data = array_map(fn($r) => [
                        (string) $r['nombre_grupo'], (int) $r['cantidad_productos'],
                        (float) $r['stock_actual'], (float) $r['costo_promedio'], (float) $r['valor_total'],
                    ], $rows);
                }
                return [$headers, $data, 'Valorización de Inventario'];

            case 'consignaciones':
                $filtros = $this->getFiltrosConsignaciones();
                $modo = $filtros['agrupar_por'];
                $rows = match ($modo) {
                    'CLIENTE'  => $this->repository->getConsignacionesAgrupadoCliente($idEmpresa, $filtros),
                    'PRODUCTO' => $this->repository->getConsignacionesAgrupadoProducto($idEmpresa, $filtros),
                    default    => $this->repository->getConsignacionesDetalle($idEmpresa, $filtros),
                };
                if ($modo === 'NINGUNO') {
                    $headers = ['Fecha', 'Secuencial', 'Cliente', 'Identificación', 'Asesor', 'Responsable de traslado',
                                'Código', 'Producto', 'Bodega', 'Lote', 'NUP', 'Consignado', 'Retornado', 'Facturado', 'A cambio', 'Saldo', 'Valor a costo'];
                    $data = array_map(fn($r) => [
                        date('d-m-Y', strtotime($r['fecha_emision'])), $r['secuencial'] ?? '',
                        $r['cliente_nombre'] ?? '', $r['cliente_identificacion'] ?? '',
                        $r['vendedor_nombre'] ?? '', $r['responsable_traslado_nombre'] ?? '',
                        $r['producto_codigo'] ?? '',
                        $r['producto_nombre'] ?? '', $r['bodega_nombre'] ?? '',
                        $r['numero_lote'] ?? '-', $r['nup'] ?? '-',
                        (float) $r['cantidad_consignada'], (float) $r['cantidad_retornada'], (float) $r['cantidad_facturada'],
                        (float) ($r['cantidad_cambiada'] ?? 0),
                        (float) $r['saldo'], (float) $r['valor_saldo'],
                    ], $rows);
                } elseif ($modo === 'PRODUCTO') {
                    $headers = ['Código', 'Producto', 'Consignaciones', 'Saldo', 'Valor a costo'];
                    $data = array_map(fn($r) => [
                        (string) ($r['codigo_grupo'] ?? ''), (string) $r['nombre_grupo'], (int) $r['cantidad_consignaciones'],
                        (float) $r['saldo'], (float) $r['valor_saldo'],
                    ], $rows);
                } else {
                    $headers = ['Grupo', 'Consignaciones', 'Saldo', 'Valor a costo'];
                    $data = array_map(fn($r) => [
                        (string) $r['nombre_grupo'], (int) $r['cantidad_consignaciones'],
                        (float) $r['saldo'], (float) $r['valor_saldo'],
                    ], $rows);
                }
                return [$headers, $data, 'Consignaciones en Poder de Clientes'];

            default: // existencias
                $filtros  = $this->getFiltrosExistencias();
                $desglose = $filtros['desglose'];
                $topeExport = ReporteInventarioRepository::LIMITE_FILAS_EXPORT;
                if ($desglose === self::DESGLOSE_CONSIGNACION) {
                    // Mismo guard que en pantalla: la exportación no puede servir lo que
                    // la pestaña no dejaría ver (ver generarExistencias()).
                    $this->requirePermisoVerModulo(self::RUTA_CONSIGNACIONES);
                    $modo = $desglose;
                    $rows = $this->repository->getConsignacionesDetalle(
                        $idEmpresa,
                        self::filtrosConsignacionDesdeExistencias($filtros),
                        (string) ($filtros['consignado'] ?? ''),
                        $topeExport
                    );
                } elseif ($desglose !== 'GENERAL') {
                    $modo = $desglose;
                    $rows = $this->repository->getExistenciasPorDesglose($idEmpresa, $filtros, $desglose, $topeExport);
                } else {
                    $modo = $filtros['agrupar_por'];
                    $rows = match ($modo) {
                        'PRODUCTO'  => $this->repository->getExistenciasAgrupadoProducto($idEmpresa, $filtros),
                        'CATEGORIA' => $this->repository->getExistenciasAgrupadoCategoria($idEmpresa, $filtros),
                        'BODEGA'    => $this->repository->getExistenciasAgrupadoBodega($idEmpresa, $filtros),
                        default     => $this->repository->getExistenciasDetalle($idEmpresa, $filtros, $topeExport),
                    };
                }
                if ($modo === self::DESGLOSE_CONSIGNACION) {
                    $headers = ['Fecha', 'Secuencial', 'Cliente', 'Asesor', 'Código', 'Descripción', 'Lote', 'NUP',
                                'Responsable de traslado', 'Bodega', 'Consignado', 'Retornado', 'Facturado', 'A cambio', 'Saldo'];
                    $data = array_map(fn($r) => [
                        date('d-m-Y', strtotime($r['fecha_emision'])), $r['secuencial'] ?? '',
                        $r['cliente_nombre'] ?? '', $r['vendedor_nombre'] ?? '',
                        $r['producto_codigo'] ?? '',
                        $r['producto_nombre'] ?? '', $r['numero_lote'] ?? '-', $r['nup'] ?? '-',
                        $r['responsable_traslado_nombre'] ?? '', $r['bodega_nombre'] ?? '',
                        (float) $r['cantidad_consignada'], (float) $r['cantidad_retornada'],
                        (float) $r['cantidad_facturada'], (float) ($r['cantidad_cambiada'] ?? 0),
                        (float) $r['saldo'],
                    ], $rows);
                } elseif ($modo === 'NINGUNO') {
                    $headers = ['Código', 'Producto', 'Categoría', 'Bodega', 'Consignación', 'Stock', 'Stock Total', 'Mínimo', 'Máximo', 'Costo Unit.', 'Valor total', 'Estado'];
                    $data = array_map(fn($r) => [
                        $r['producto_codigo'] ?? '', $r['producto_nombre'] ?? '', $r['categoria_nombre'] ?? '', $r['bodega_nombre'] ?? '',
                        (float) $r['consignado'], (float) $r['stock_actual'], (float) $r['stock_total'], (float) $r['stock_minimo'], (float) $r['stock_maximo'],
                        (float) $r['costo_unitario'], (float) $r['valor_total'], $r['estado_stock'] ?? '',
                    ], $rows);
                } elseif (in_array($modo, self::DESGLOSES_EXISTENCIAS, true)) {
                    // Mismas columnas que la pantalla: solo las que el desglose puede afirmar.
                    $conLote = $modo === 'LOTE' || $modo === 'LOTE_CADUCIDAD';
                    $conNup  = $modo === 'LOTE_CADUCIDAD';
                    $conCad  = $modo === 'CADUCIDAD' || $modo === 'LOTE_CADUCIDAD';
                    $headers = array_merge(
                        ['Código', 'Producto', 'Bodega'],
                        $conLote ? ['Lote'] : [],
                        $conNup  ? ['NUP'] : [],
                        $conCad  ? ['Caducidad'] : [],
                        ['Stock', 'Consignación', 'Stock Total', 'Costo Unit.', 'Valor total']
                    );
                    $data = array_map(function ($r) use ($conLote, $conNup, $conCad) {
                        $cad = !empty($r['fecha_caducidad']) ? date('d-m-Y', strtotime($r['fecha_caducidad'])) : '';
                        return array_merge(
                            [$r['producto_codigo'] ?? '', $r['producto_nombre'] ?? '', $r['bodega_nombre'] ?? ''],
                            $conLote ? [$r['lote'] ?? ''] : [],
                            $conNup  ? [$r['nup'] ?? ''] : [],
                            $conCad  ? [$cad] : [],
                            [
                                (float) $r['stock_actual'], (float) $r['consignado'], (float) $r['stock_total'],
                                (float) $r['costo_unitario'], (float) $r['valor_total'],
                            ]
                        );
                    }, $rows);
                } elseif ($modo === 'PRODUCTO') {
                    $headers = ['Código', 'Producto', 'Productos', 'Consignación', 'Stock', 'Stock Total', 'Costo Unit.', 'Valor total'];
                    $data = array_map(fn($r) => [
                        (string) ($r['codigo_grupo'] ?? ''), (string) $r['nombre_grupo'], (int) $r['cantidad_productos'],
                        (float) $r['consignado'], (float) $r['stock_actual'], (float) $r['stock_total'], (float) $r['costo_unitario'], (float) $r['valor_total'],
                    ], $rows);
                } else {
                    $headers = ['Grupo', 'Productos', 'Consignación', 'Stock', 'Stock Total', 'Costo Unit.', 'Valor total'];
                    $data = array_map(fn($r) => [
                        (string) $r['nombre_grupo'], (int) $r['cantidad_productos'],
                        (float) $r['consignado'], (float) $r['stock_actual'], (float) $r['stock_total'], (float) $r['costo_unitario'], (float) $r['valor_total'],
                    ], $rows);
                }
                return [$headers, self::recortarExport($data, count($headers)), 'Existencias de Inventario'];
        }
    }
}
