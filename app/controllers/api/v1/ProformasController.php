<?php
/**
 * Controlador API v1: Proformas (app móvil).
 * Adaptador HTTP→JSON: la lógica vive en ProformaService (la misma que usa el módulo
 * web), y el PDF/correo en ProformaDocumentoService (compartido con la web).
 *
 * Igual que la web: el secuencial lo asigna el servidor al guardar, los totales y el
 * IVA los recalcula ProformaService::normalizarImportes() con la configuración de la
 * empresa, y precio/descuento son libres (las proformas no usan los interruptores de
 * Empresa → Facturación).
 */

declare(strict_types=1);

namespace App\controllers\api\v1;

use App\controllers\api\ApiBaseController;
use App\models\Empresa;
use App\models\Vendedor;
use App\repositories\modulos\ClienteRepository;
use App\repositories\modulos\ProductoRepository;
use App\repositories\modulos\ProformaRepository;
use App\repositories\SecuencialRepository;
use App\Rules\modulos\ProformaRules;
use App\Services\LogSistemaService;
use App\Services\modulos\ProformaDocumentoService;
use App\Services\modulos\ProformaService;
use Throwable;

class ProformasController extends ApiBaseController
{
    /** Tal cual lo espera SecuencialService para mapear a proformas_cabecera. */
    private const TIPO_DOCUMENTO = 'Proformas';
    private const CODIGO_IMPUESTO_IVA = '2';

    private ProformaRepository $repository;
    private ProformaService $service;
    private ProformaDocumentoService $documentos;

    public function __construct()
    {
        parent::__construct();
        $this->repository = new ProformaRepository();
        $this->service    = new ProformaService($this->repository, new ProformaRules(), new LogSistemaService());
        $this->documentos = new ProformaDocumentoService($this->repository, $this->service);
    }

    protected function getRutaModulo(): string
    {
        return 'modulos/proformas';
    }

    /**
     * GET /api/v1/proformas/listar?buscar=&page=&per_page=
     * Sin el permiso "acceso total" solo ve las suyas (igual que la web).
     */
    public function listar(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim((string) ($_GET['buscar'] ?? ''));
        $page      = max(1, (int) ($_GET['page'] ?? 1));
        $perPage   = min(50, max(1, (int) ($_GET['per_page'] ?? 20)));

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $result = $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, 'fecha_emision', 'DESC', $idUsuarioFiltro);
        $total  = (int) ($result['total'] ?? 0);

        $this->jsonOk($result['rows'] ?? [], [
            'page'        => $page,
            'per_page'    => $perPage,
            'total'       => $total,
            'total_pages' => $perPage > 0 ? max(1, (int) ceil($total / $perPage)) : 1,
        ]);
    }

    /**
     * GET /api/v1/proformas/obtener?id=123
     */
    public function obtener(): void
    {
        $this->requireLeer();

        $id = (int) ($_GET['id'] ?? 0);
        $cabecera = $this->cabeceraDeMiEmpresa($id);

        $this->jsonOk([
            'cabecera'       => $cabecera,
            'detalles'       => $this->documentos->getDetallesConImpuestos($id),
            'info_adicional' => $this->repository->getInfoAdicional($id),
        ]);
    }

    /**
     * GET /api/v1/proformas/series
     * Establecimientos con sus puntos de emisión que tienen secuencial de Proformas.
     */
    public function series(): void
    {
        $this->requireLeer();

        $idEmpresa    = (int) $_SESSION['id_empresa'];
        $empresaModel = new Empresa();
        $secRepo      = new SecuencialRepository();

        $resultado = [];
        foreach ($empresaModel->getEstablecimientos($idEmpresa) as $est) {
            $puntos = [];
            foreach ($empresaModel->getPuntosEmision((int) $est['id']) as $p) {
                $config = $secRepo->getConfigSecuencial((int) $p['id'], self::TIPO_DOCUMENTO);
                if (empty($config['id'])) {
                    continue;
                }
                $puntos[] = ['id_punto_emision' => (int) $p['id'], 'punto_emision' => $p['codigo_punto']];
            }
            if (empty($puntos)) {
                continue;
            }
            $resultado[] = [
                'id_establecimiento' => (int) $est['id'],
                'establecimiento'    => $est['codigo'],
                'puntos_emision'     => $puntos,
            ];
        }

        $this->jsonOk(['establecimientos' => $resultado]);
    }

    /**
     * GET /api/v1/proformas/secuencial?id_punto_emision=123&fecha=YYYY-MM-DD
     * Vista previa: el número definitivo lo asigna el servidor al guardar.
     */
    public function secuencial(): void
    {
        $this->requireLeer();

        $idPunto = (int) ($_GET['id_punto_emision'] ?? 0);
        if ($idPunto <= 0) {
            $this->jsonError('ID_REQUERIDO', 'Falta id_punto_emision.', 422);
        }
        $fecha = trim((string) ($_GET['fecha'] ?? '')) ?: null;

        try {
            $this->jsonOk($this->service->getSiguienteSecuencial($idPunto, $fecha));
        } catch (Throwable $e) {
            $this->jsonError('ERROR_SECUENCIAL', $e->getMessage(), 422);
        }
    }

    /**
     * GET /api/v1/proformas/catalogos
     */
    public function catalogos(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $config    = $this->documentos->empresaConfig($idEmpresa);

        $this->jsonOk([
            'vendedores'        => (new Vendedor())->getActivosPorEmpresa($idEmpresa),
            'dias_vigencia'     => 15,
            'decimales_precio'  => (int) ($config['decimales_precio'] ?? 2),
            'decimales_cantidad'=> (int) ($config['decimales_cantidad'] ?? 2),
        ]);
    }

    /**
     * GET /api/v1/proformas/buscar-clientes?q=
     * Propio del módulo (como la web): quien solo tiene Proformas puede elegir cliente
     * sin necesitar permiso sobre el módulo Clientes.
     */
    public function buscarClientes(): void
    {
        $this->requireLeer();

        $q      = trim((string) ($_GET['q'] ?? ''));
        $result = (new ClienteRepository())->getListado((int) $_SESSION['id_empresa'], $q, 1, 15, 'nombre', 'ASC', null, true);
        $this->jsonOk($result['rows'] ?? []);
    }

    /**
     * GET /api/v1/proformas/buscar-productos?q=
     */
    public function buscarProductos(): void
    {
        $this->requireLeer();

        $q      = trim((string) ($_GET['q'] ?? ''));
        $result = (new ProductoRepository())->getListado((int) $_SESSION['id_empresa'], $q, 1, 15, 'nombre', 'ASC', null, 'venta', true);
        $this->jsonOk($result['rows'] ?? []);
    }

    /**
     * POST /api/v1/proformas/crear
     * body: { id_establecimiento, id_punto_emision, fecha_emision?, id_cliente,
     *         id_vendedor?, dias_vigencia?, observaciones?,
     *         detalles: [{ id_producto, cantidad, precio_unitario, descuento? }] }
     */
    public function crear(): void
    {
        $this->requireCrear();
        $this->requirePost();

        $body      = $this->getJsonBody();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idEstab   = (int) ($body['id_establecimiento'] ?? 0);
        $idPunto   = (int) ($body['id_punto_emision'] ?? 0);

        $serie = $this->resolverSerie($idEmpresa, $idEstab, $idPunto);
        $data  = $this->construirDatos($body, $idEmpresa, null, []);
        $data  = array_merge($data, $serie, [
            // Obligatorio para ProformaRules; el valor real lo asigna el Service.
            'secuencial'       => 'auto',
            'condiciones_html' => null,
            'info_adicional'   => [],
        ]);

        try {
            $id = $this->service->crear($data, $this->documentos->empresaConfig($idEmpresa));
        } catch (Throwable $e) {
            $this->jsonError('ERROR_GUARDAR', $e->getMessage(), 422);
        }

        $this->jsonOk(['id' => $id, 'numero' => ProformaDocumentoService::numero($this->repository->getPorId($id) ?? [])], [], 201);
    }

    /**
     * POST /api/v1/proformas/actualizar
     * body: igual que crear, más { id }. Solo en borrador (lo valida el Service). La
     * serie y el secuencial no cambian. Lo que la app no edita (condiciones, info
     * adicional, líneas de texto libre, info adicional por línea) se conserva.
     */
    public function actualizar(): void
    {
        $this->requireActualizar();
        $this->requirePost();

        $body      = $this->getJsonBody();
        $id        = (int) ($body['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $existente = $this->cabeceraDeMiEmpresa($id);

        if (($existente['estado'] ?? '') !== 'borrador') {
            $this->jsonError('NO_EDITABLE', 'Solo se pueden editar proformas en estado borrador.', 409);
        }

        $lineasGuardadas = [];
        foreach ($this->documentos->getDetallesConImpuestos($id) as $d) {
            $lineasGuardadas[(int) $d['id']] = $d;
        }

        $data = $this->construirDatos($body, $idEmpresa, $existente, $lineasGuardadas);
        $data = array_merge($data, [
            'id_establecimiento' => (int) $existente['id_establecimiento'],
            'id_punto_emision'   => (int) $existente['id_punto_emision'],
            'establecimiento'    => $existente['establecimiento'],
            'punto_emision'      => $existente['punto_emision'],
            'secuencial'         => $existente['secuencial'],
            'condiciones_html'   => $existente['condiciones_html'] ?? null,
            'info_adicional'     => $this->repository->getInfoAdicional($id),
        ]);

        try {
            $this->service->actualizar($id, $data, $this->documentos->empresaConfig($idEmpresa));
        } catch (Throwable $e) {
            $this->jsonError('ERROR_GUARDAR', $e->getMessage(), 422);
        }

        $this->jsonOk(['id' => $id]);
    }

    /**
     * POST /api/v1/proformas/cambiar-estado
     * body: { id, estado: aprobada|anulada|rechazada|borrador }
     * Las transiciones permitidas (y reabrir solo nivel ≥ 2) las valida el Service.
     */
    public function cambiarEstado(): void
    {
        $this->requireActualizar();
        $this->requirePost();

        $body   = $this->getJsonBody();
        $id     = (int) ($body['id'] ?? 0);
        $estado = trim((string) ($body['estado'] ?? ''));
        $this->cabeceraDeMiEmpresa($id);
        if ($estado === '') {
            $this->jsonError('ESTADO_REQUERIDO', 'Falta el estado.', 422);
        }

        try {
            $this->service->cambiarEstado($id, $estado, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario'], (int) ($_SESSION['nivel'] ?? 1));
        } catch (Throwable $e) {
            $this->jsonError('ESTADO_NO_PERMITIDO', $e->getMessage(), 409);
        }

        $this->jsonOk(['id' => $id, 'estado' => $estado]);
    }

    /**
     * POST /api/v1/proformas/duplicar
     * body: { id } → nueva proforma en borrador con número nuevo y fecha de hoy.
     */
    public function duplicar(): void
    {
        $this->requireCrear();
        $this->requirePost();

        $body      = $this->getJsonBody();
        $id        = (int) ($body['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $this->cabeceraDeMiEmpresa($id);

        try {
            $empresa = (new Empresa())->getPorId($idEmpresa) ?? [];
            $idNueva = $this->service->duplicar($id, $idEmpresa, (int) $_SESSION['id_usuario'], (string) ($empresa['tipo_ambiente'] ?? '1'));
        } catch (Throwable $e) {
            $this->jsonError('ERROR_DUPLICAR', $e->getMessage(), 422);
        }

        $this->jsonOk(['id' => $idNueva, 'numero' => ProformaDocumentoService::numero($this->repository->getPorId($idNueva) ?? [])], [], 201);
    }

    /**
     * GET /api/v1/proformas/pdf?id=123
     * Responde el PDF binario (mismo documento que la web), no el sobre {ok,data}.
     */
    public function pdf(): void
    {
        $this->requireLeer();

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $cabecera  = $this->cabeceraDeMiEmpresa($id);

        $pdf = $this->documentos->generarPdfString($id, $idEmpresa, $cabecera);
        if ($pdf === null || $pdf === '') {
            $this->jsonError('ERROR_PDF', 'No se pudo generar el PDF de la proforma.', 500);
        }

        $nombre = 'Proforma_' . ProformaDocumentoService::numero($cabecera) . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $nombre . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    /**
     * POST /api/v1/proformas/enviar-correo
     * body: { id, correos: "a@x.com, b@y.com", adjuntar_ficha?: bool }
     */
    public function enviarCorreo(): void
    {
        $this->requireLeer();
        $this->requirePost();

        $body = $this->getJsonBody();
        $id   = (int) ($body['id'] ?? 0);
        $this->cabeceraDeMiEmpresa($id);

        try {
            $res = $this->documentos->enviarCorreo($id, (int) $_SESSION['id_empresa'], (string) ($body['correos'] ?? ''), !empty($body['adjuntar_ficha']));
        } catch (Throwable $e) {
            $this->jsonError('ERROR_CORREO', $e->getMessage(), 500);
        }

        if (!$res['ok']) {
            $this->jsonError('ERROR_CORREO', $res['mensaje'], 422);
        }
        $this->jsonOk(['mensaje' => $res['mensaje']]);
    }

    /**
     * POST /api/v1/proformas/convertir-factura
     * body: { id, forzar?: bool }
     * Crea la factura en borrador (ProformaService::convertirAFactura, igual que la web).
     * Respuestas que no son error, para que la app pregunte o informe:
     *   requiere_confirmacion (ya tiene factura) y stock_insuficiente (con faltantes).
     */
    public function convertirFactura(): void
    {
        $this->requireCrear();
        $this->requirePost();

        $body = $this->getJsonBody();
        $id   = (int) ($body['id'] ?? 0);
        $this->cabeceraDeMiEmpresa($id);

        try {
            $res = $this->service->convertirAFactura($id, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario'], !empty($body['forzar']));
        } catch (Throwable $e) {
            $this->jsonError('ERROR_CONVERTIR', $e->getMessage(), 422);
        }

        $this->jsonOk([
            'id_factura'            => (int) ($res['id_factura'] ?? 0),
            'requiere_confirmacion' => !empty($res['requiere_confirmacion']),
            'mensaje'               => (string) ($res['mensaje'] ?? ''),
            'stock_insuficiente'    => !empty($res['stock_insuficiente']),
            'faltantes'             => $res['faltantes'] ?? [],
        ]);
    }

    /**
     * POST /api/v1/proformas/convertir-pedido
     * body: { id, forzar?: bool }
     * Respuestas que no son error: requiere_confirmacion (ya tiene pedido) e
     * items_sin_producto (líneas de texto libre: el pedido solo admite productos).
     */
    public function convertirPedido(): void
    {
        $this->requireCrear();
        $this->requirePost();

        $body = $this->getJsonBody();
        $id   = (int) ($body['id'] ?? 0);
        $this->cabeceraDeMiEmpresa($id);

        try {
            $res = $this->service->convertirAPedido($id, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario'], !empty($body['forzar']));
        } catch (Throwable $e) {
            $this->jsonError('ERROR_CONVERTIR', $e->getMessage(), 422);
        }

        $this->jsonOk([
            'id_pedido'             => (int) ($res['id_pedido'] ?? 0),
            'numero'                => (string) ($res['numero'] ?? ''),
            'requiere_confirmacion' => !empty($res['requiere_confirmacion']),
            'mensaje'               => (string) ($res['mensaje'] ?? ''),
            'items_sin_producto'    => $res['items_sin_producto'] ?? [],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────

    private function requirePost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonError('METODO_NO_PERMITIDO', 'Use POST.', 405);
        }
    }

    /**
     * Cabecera de la proforma si es de la empresa activa (y, sin "acceso total", del
     * propio usuario, igual que el listado). Si no, el mismo 404 que si no existiera.
     */
    private function cabeceraDeMiEmpresa(int $id): array
    {
        if ($id <= 0) {
            $this->jsonError('ID_REQUERIDO', 'Falta id.', 422);
        }
        $cabecera = $this->repository->getPorId($id);
        if (!$cabecera || (int) ($cabecera['id_empresa'] ?? 0) !== (int) $_SESSION['id_empresa'] || !empty($cabecera['eliminado'])) {
            $this->jsonError('NO_ENCONTRADO', 'Proforma no encontrada.', 404);
        }
        $perm = $this->getPermisos();
        if (empty($perm['todo']) && (int) ($cabecera['id_usuario'] ?? 0) !== (int) $_SESSION['id_usuario']) {
            $this->jsonError('NO_ENCONTRADO', 'Proforma no encontrada.', 404);
        }
        return $cabecera;
    }

    /** Valida que el punto de emisión sea de la empresa y tenga secuencial de Proformas. */
    private function resolverSerie(int $idEmpresa, int $idEstab, int $idPunto): array
    {
        if ($idEstab <= 0 || $idPunto <= 0) {
            $this->jsonError('SERIE_REQUERIDA', 'Selecciona la serie (establecimiento-punto de emisión).', 422);
        }
        $empresaModel = new Empresa();
        foreach ($empresaModel->getEstablecimientos($idEmpresa) as $est) {
            if ((int) $est['id'] !== $idEstab) {
                continue;
            }
            foreach ($empresaModel->getPuntosEmision($idEstab) as $p) {
                if ((int) $p['id'] !== $idPunto) {
                    continue;
                }
                $config = (new SecuencialRepository())->getConfigSecuencial($idPunto, self::TIPO_DOCUMENTO);
                if (empty($config['id'])) {
                    break 2;
                }
                return [
                    'id_establecimiento' => $idEstab,
                    'id_punto_emision'   => $idPunto,
                    'establecimiento'    => $est['codigo'],
                    'punto_emision'      => $p['codigo_punto'],
                ];
            }
        }
        $this->jsonError('SERIE_INVALIDA', 'La serie elegida no tiene secuencial de Proformas configurado.', 422);
    }

    /**
     * Arma el $data común a crear()/actualizar(). Cada línea de producto toma
     * descripción, códigos, unidad e IVA del catálogo (nunca del celular); cantidad,
     * precio y descuento sí vienen de la app. Una línea con `id_detalle` de la propia
     * proforma (solo en edición) conserva lo que la app no muestra: texto libre, info
     * adicional de la línea, unidad e IVA guardados. Los totales los recalcula el Service.
     *
     * @param array<int,array> $lineasGuardadas detalles actuales de la proforma, por id
     */
    private function construirDatos(array $body, int $idEmpresa, ?array $existente, array $lineasGuardadas): array
    {
        $lineasBody = is_array($body['detalles'] ?? null) ? $body['detalles'] : [];
        if (empty($lineasBody)) {
            $this->jsonError('SIN_DETALLES', 'Agrega al menos un producto.', 422);
        }

        $productoRepo = new ProductoRepository();
        $detalles = [];
        foreach ($lineasBody as $i => $linea) {
            $fila      = $i + 1;
            $cantidad  = (float) ($linea['cantidad'] ?? 0);
            $precio    = (float) ($linea['precio_unitario'] ?? 0);
            $descuento = (float) ($linea['descuento'] ?? 0);
            if ($cantidad <= 0) {
                $this->jsonError('CANTIDAD_INVALIDA', "Fila {$fila}: la cantidad debe ser mayor a cero.", 422);
            }
            if ($precio < 0) {
                $this->jsonError('PRECIO_INVALIDO', "Fila {$fila}: el precio no puede ser negativo.", 422);
            }
            $bruto = round($cantidad * $precio, 2);
            if ($descuento < 0 || round($descuento, 2) > $bruto) {
                $this->jsonError('DESCUENTO_INVALIDO', "Fila {$fila}: el descuento debe estar entre 0 y $" . number_format($bruto, 2, '.', '') . '.', 422);
            }

            $idDetalle  = (int) ($linea['id_detalle'] ?? 0);
            $guardada   = $idDetalle > 0 ? ($lineasGuardadas[$idDetalle] ?? null) : null;
            $idProducto = (int) ($linea['id_producto'] ?? 0);

            if ($guardada !== null && (int) ($guardada['id_producto'] ?? 0) === $idProducto) {
                // Línea ya guardada (producto o texto libre): se conservan sus datos.
                $iva = null;
                foreach ($guardada['impuestos'] ?? [] as $imp) {
                    if ((string) ($imp['codigo_impuesto'] ?? '') === self::CODIGO_IMPUESTO_IVA) { $iva = $imp; break; }
                }
                $detalles[] = [
                    'id_producto'      => $idProducto > 0 ? $idProducto : null,
                    'id_unidad_medida' => $guardada['id_unidad_medida'] ?? null,
                    'codigo_principal' => $guardada['codigo_principal'] ?? '',
                    'codigo_auxiliar'  => $guardada['codigo_auxiliar'] ?? null,
                    'descripcion'      => $guardada['descripcion'] ?? '',
                    'adicional'        => $guardada['info_adicional'] ?? null,
                    'cantidad'         => $cantidad,
                    'precio_unitario'  => $precio,
                    'descuento'        => $descuento,
                    'id_tarifa_iva'    => $guardada['id_tarifa_iva'] ?? null,
                    'impuestos'        => [[
                        'codigo_impuesto'   => self::CODIGO_IMPUESTO_IVA,
                        'codigo_porcentaje' => (string) ($iva['codigo_porcentaje'] ?? '0'),
                        'tarifa'            => (float) ($iva['tarifa'] ?? 0),
                    ]],
                ];
                continue;
            }

            if ($idProducto <= 0) {
                $this->jsonError('PRODUCTO_REQUERIDO', "Fila {$fila}: selecciona un producto.", 422);
            }
            $producto = $productoRepo->getPorId($idProducto, $idEmpresa);
            if (!$producto) {
                $this->jsonError('PRODUCTO_NO_ENCONTRADO', "Fila {$fila}: el producto no existe o no está disponible para la venta.", 422);
            }
            $detalles[] = [
                'id_producto'      => $idProducto,
                'id_unidad_medida' => !empty($producto['id_medida']) ? (int) $producto['id_medida'] : null,
                'codigo_principal' => $producto['codigo'] ?? '',
                'codigo_auxiliar'  => $producto['codigo_auxiliar'] ?? null,
                'descripcion'      => $producto['nombre'] ?? '',
                'adicional'        => null,
                'cantidad'         => $cantidad,
                'precio_unitario'  => $precio,
                'descuento'        => $descuento,
                'id_tarifa_iva'    => !empty($producto['tarifa_iva']) ? (int) $producto['tarifa_iva'] : null,
                'impuestos'        => [[
                    'codigo_impuesto'   => self::CODIGO_IMPUESTO_IVA,
                    'codigo_porcentaje' => (string) ($producto['codigo_iva_final'] ?? '0'),
                    'tarifa'            => (float) ($producto['porcentaje_iva_final'] ?? 0),
                ]],
            ];
        }

        $empresa = (new Empresa())->getPorId($idEmpresa) ?? [];
        $observaciones = trim((string) ($body['observaciones'] ?? ''));

        return [
            'id_empresa'    => $idEmpresa,
            'id_usuario'    => (int) $_SESSION['id_usuario'],
            'tipo_ambiente' => (string) ($empresa['tipo_ambiente'] ?? '1'),
            'fecha_emision' => trim((string) ($body['fecha_emision'] ?? '')) ?: date('Y-m-d'),
            'id_cliente'    => (int) ($body['id_cliente'] ?? 0),
            'id_vendedor'   => !empty($body['id_vendedor']) ? (int) $body['id_vendedor'] : null,
            'dias_vigencia' => (int) ($body['dias_vigencia'] ?? ($existente['dias_vigencia'] ?? 15)),
            'observaciones' => $observaciones !== '' ? $observaciones : null,
            'estado'        => $existente['estado'] ?? 'borrador',
            'moneda'        => 'DOLAR',
            'total_ice'     => 0,
            'detalles'      => $detalles,
        ];
    }
}
