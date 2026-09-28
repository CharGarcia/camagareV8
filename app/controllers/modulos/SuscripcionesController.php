<?php
declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\SuscripcionesRepository;
use App\Rules\modulos\SuscripcionesRules;
use App\Services\LogSistemaService;
use App\Services\modulos\SuscripcionesService;
use App\Services\modulos\KushkiService;

class SuscripcionesController extends BaseModuloController
{
    private SuscripcionesService $service;
    private const RUTA_MODULO = 'modulos/suscripciones';

    /**
     * Módulos cuyo permiso de lectura habilita la pestaña "Facturas" del modal: cada tipo
     * de documento se incluye solo si el usuario puede ver su módulo y, sin "acceso total"
     * en él, solo con los documentos que registró (igual que su listado). Única fuente:
     * modal_suscripcion.php la usa también para decidir si pinta la pestaña.
     */
    public const RUTAS_FACTURAS = [
        'FACTURA' => 'modulos/factura-venta',
        'RECIBO'  => 'modulos/recibo-venta',
    ];

    public function __construct()
    {
        parent::__construct();
        $repository    = new SuscripcionesRepository();
        $rules         = new SuscripcionesRules();
        $logService    = new LogSistemaService();
        $this->service = new SuscripcionesService($repository, $rules, $logService);
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    public function index(): void
    {
        $this->requireLeer();

        $perm       = $this->getPermisos();
        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);

        $buscar   = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'proximo_cobro');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'asc'));
        $perPage  = 20;

        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);

        foreach ($result['rows'] as &$r) {
            if (!empty($r['created_at']))   $r['created_at']   = date('d-m-Y H:i:s', strtotime($r['created_at']));
            if (!empty($r['updated_at']))   $r['updated_at']   = date('d-m-Y H:i:s', strtotime($r['updated_at']));
            if (!empty($r['proximo_cobro'])) $r['proximo_cobro_fmt'] = date('d-m-Y', strtotime($r['proximo_cobro']));
            if (!empty($r['fecha_inicio']))  $r['fecha_inicio_fmt']  = date('d-m-Y', strtotime($r['fecha_inicio']));
        }
        unset($r);

        $totalPages     = $perPage > 0 ? (int) ceil($result['total'] / $perPage) : 1;
        $periodicidades = $this->service->getPeriodicidades();

        $db = \App\core\Database::getConnection();
        $stmt = $db->query("SELECT id, codigo, tarifa, porcentaje_iva FROM tarifa_iva WHERE status = 1 ORDER BY porcentaje_iva ASC");
        $tarifasIva = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $empresaModel = new \App\models\Empresa();
        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        $puntos = [];
        if (!empty($establecimientos)) {
            $puntos = $empresaModel->getPuntosEmision((int) $establecimientos[0]['id']);
        }

        // Los decimales se configuran a nivel de establecimiento (no en la tabla empresas),
        // igual que en la factura de venta.
        $decimalesPrecio   = 2;
        $decimalesCantidad = 2;
        $calculoIva        = 'linea_linea';
        if (!empty($establecimientos)) {
            try {
                $estRepo   = new \App\repositories\modulos\EmpresaRepository();
                $estConfig = $estRepo->getEstablecimientoConfig((int) $establecimientos[0]['id']);
                if ($estConfig) {
                    $decimalesPrecio   = (int) ($estConfig['decimales_precio']   ?? 2);
                    $decimalesCantidad = (int) ($estConfig['decimales_cantidad'] ?? 2);
                    $calculoIva        = ($estConfig['calculo_iva_facturacion'] ?? 'linea_linea') === 'subtotal'
                        ? 'subtotal' : 'linea_linea';
                }
            } catch (\Throwable $e) {
                // Migración pendiente — se usan valores por defecto.
            }
        }

        $this->viewWithLayout('layouts.main', 'modulos.suscripciones.index', [
            'titulo'         => 'Suscripciones',
            'perm'           => $perm,
            'rutaModulo'     => self::RUTA_MODULO,
            'rows'           => $result['rows'],
            'total'          => $result['total'],
            'page'           => $page,
            'totalPages'     => $totalPages,
            'perPage'        => $perPage,
            'buscar'         => $buscar,
            'ordenCol'       => $ordenCol,
            'ordenDir'       => $ordenDir,
            'vistaConfig'    => $prefsVista,
            'periodicidades' => $periodicidades,
            // Selects del modal de filtros (solo valores usados por la empresa).
            'opcionesFiltro' => $this->service->getOpcionesFiltro($idEmpresa),
            'tarifasIva'     => $tarifasIva,
            'puntos'         => $puntos,
            'decimalesPrecio'   => $decimalesPrecio,
            'decimalesCantidad' => $decimalesCantidad,
            'calculoIva'        => $calculoIva,
            'fullWidth'      => true,
        ]);
    }

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa       = (int) $_SESSION['id_empresa'];
        $perm            = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;
        $prefsVista      = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);

        $buscar   = trim($_GET['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'proximo_cobro');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'asc'));
        $perPage  = 20;

        $result     = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $total      = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
        $from       = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to         = $total > 0 ? min($page * $perPage, $total) : 0;

        $estadoClases = [
            'activo'     => 'success',
            'pausado'    => 'warning',
            'suspendido' => 'danger',
            'cancelado'  => 'secondary',
        ];

        ob_start();
        foreach ($result['rows'] as $r) {
            $cls        = $estadoClases[$r['estado'] ?? 'activo'] ?? 'secondary';
            $lbl        = ucfirst($r['estado'] ?? 'activo');
            $proxCobro  = !empty($r['proximo_cobro']) ? date('d-m-Y', strtotime($r['proximo_cobro'])) : '—';
            $fechaIni   = !empty($r['fecha_inicio'])  ? date('d-m-Y', strtotime($r['fecha_inicio']))  : '—';
            $iconoCobro = ($r['forma_cobro'] ?? '') === 'tarjeta'
                ? '<i class="bi bi-credit-card text-primary" title="Tarjeta"></i>'
                : '<i class="bi bi-file-text text-muted" title="Crédito"></i>';
            $totalItems = (int) ($r['total_items'] ?? 0);

            echo '<tr class="susc-row" role="button" data-susc=\'' . htmlspecialchars(json_encode($r), ENT_QUOTES) . '\' onclick="abrirModalSuscEditar(this)">';
            echo '<td class="ps-3 fw-medium" data-col="nombre_cliente">' . htmlspecialchars($r['nombre_cliente'] ?? '') . '</td>';
            echo '<td data-col="identificacion_cliente"><small class="text-muted">' . htmlspecialchars($r['identificacion_cliente'] ?? '') . '</small></td>';
            echo '<td data-col="nombre_periodicidad">' . htmlspecialchars($r['nombre_periodicidad'] ?? '—') . '</td>';
            echo '<td class="text-center" data-col="tipo_comprobante"><small class="text-muted">' . htmlspecialchars(ucwords(str_replace('_', ' ', $r['tipo_comprobante'] ?? 'Factura'))) . '</small></td>';
            echo '<td class="text-center" data-col="forma_cobro">' . $iconoCobro . ' ' . ucfirst($r['forma_cobro'] ?? '') . '</td>';
            echo '<td class="text-center fw-medium" data-col="proximo_cobro">' . $proxCobro . '</td>';
            echo '<td class="text-center" data-col="fecha_inicio">' . $fechaIni . '</td>';
            echo '<td class="text-center" data-col="total_items"><span class="badge bg-secondary bg-opacity-10 text-secondary border">' . $totalItems . ' ítem' . ($totalItems !== 1 ? 's' : '') . '</span></td>';
            $fin        = !empty($r['fecha_fin'])     ? date('d-m-Y', strtotime($r['fecha_fin']))     : '—';
            echo '<td class="text-center" data-col="fecha_fin">' . $fin . '</td>';
            echo '<td class="text-center pe-3" data-col="estado">';
            echo "<span class=\"badge bg-{$cls} bg-opacity-10 text-{$cls} border border-{$cls} border-opacity-25\">{$lbl}</span>";
            echo '</td>';
            echo '</tr>';
        }
        $rowsHtml = ob_get_clean();

        ob_start();
        echo '<button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaAjax(' . ($page - 1) . ')" ' . ($page <= 1 ? 'disabled' : '') . '><i class="bi bi-chevron-left"></i></button>';
        echo '<button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaAjax(' . ($page + 1) . ')" ' . ($page >= $totalPages ? 'disabled' : '') . '><i class="bi bi-chevron-right"></i></button>';
        $paginHtml = ob_get_clean();

        // URLs de exportación con el buscador/orden actuales, para que el JS
        // mantenga los botones PDF/Excel sincronizados con el filtro aplicado.
        $qs      = 'b=' . urlencode($buscar) . '&sort=' . urlencode($ordenCol) . '&dir=' . urlencode($ordenDir);
        $urlBase = BASE_URL . '/' . self::RUTA_MODULO;

        echo json_encode([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'pagination' => $paginHtml,
            'info'       => "$from-$to/$total",
            'pdf_url'    => $urlBase . '/export-pdf?' . $qs,
            'excel_url'  => $urlBase . '/export-excel?' . $qs,
        ]);
    }

    public function store(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        try {
            $data               = $_POST;
            $data['id_empresa'] = (int) $_SESSION['id_empresa'];
            $data['id_usuario'] = (int) $_SESSION['id_usuario'];

            $id = $this->service->crear($data);

            // Si la suscripción se crea con cobro por tarjeta vía Nuvei y aún no
            // tiene una tarjeta vinculada, enviar automáticamente el enlace de
            // registro al cliente para que active el cobro recurrente.
            $mensajeExtra = '';
            if (($data['forma_cobro'] ?? '') === 'tarjeta' && ($data['pasarela_tarjeta'] ?? '') === 'nuvei' && empty($data['id_nuvei_tarjeta'])) {
                try {
                    $envio = $this->enviarRegistroTarjetaNuvei($id, (int) $data['id_empresa'], (int) $data['id_usuario']);
                    if ($envio['ok']) {
                        $mensajeExtra = ' Se envió un enlace al cliente para registrar su tarjeta.';
                    }
                } catch (\Throwable $e) {
                    error_log('[Suscripciones] Error enviando registro de tarjeta automático: ' . $e->getMessage());
                }
            }

            echo json_encode(['ok' => true, 'id' => $id, 'mensaje' => 'Suscripción creada correctamente.' . $mensajeExtra]);
        } catch (\InvalidArgumentException $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $parts = explode('|', $e->getMessage());
            $mensaje = $parts[0];
            $focus = $parts[1] ?? '';
            echo json_encode(['ok' => false, 'mensaje' => $mensaje, 'focus' => $focus]);
        } catch (\Exception $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => 'Error al crear la suscripción: ' . $e->getMessage()]);
        }
    }

    public function update(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $id                 = (int) ($_POST['id'] ?? 0);
            $idEmpresa          = (int) $_SESSION['id_empresa'];
            $data               = $_POST;
            $data['id_empresa'] = $idEmpresa;
            $data['id_usuario'] = (int) $_SESSION['id_usuario'];

            $this->service->actualizar($id, $idEmpresa, $data);
            echo json_encode(['ok' => true, 'mensaje' => 'Suscripción actualizada correctamente.']);
        } catch (\InvalidArgumentException $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $parts = explode('|', $e->getMessage());
            $mensaje = $parts[0];
            $focus = $parts[1] ?? '';
            echo json_encode(['ok' => false, 'mensaje' => $mensaje, 'focus' => $focus]);
        } catch (\Exception $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => 'Error al actualizar: ' . $e->getMessage()]);
        }
    }

    public function cambiarEstado(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $id        = (int) ($_POST['id'] ?? 0);
            $estado    = trim($_POST['estado'] ?? '');
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];

            $this->service->cambiarEstado($id, $idEmpresa, $estado, $idUsuario);
            echo json_encode(['ok' => true, 'mensaje' => 'Estado actualizado correctamente.']);
        } catch (\Exception $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    public function delete(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        try {
            $id        = (int) ($_POST['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];

            $this->service->eliminar($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'mensaje' => 'Suscripción eliminada correctamente.']);
        } catch (\Exception $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    /**
     * Pestaña "Detalles" del buscador (FiltrosModal): búsqueda libre dentro de las
     * suscripciones (ítems, cobros e información adicional). Aplica registros propios (§6).
     */
    public function buscarDetallesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $q         = trim($_GET['q'] ?? '');
        if (mb_strlen($q) < 2) {
            echo json_encode(['rows' => []]);
            return;
        }

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $origenes = ['ITEM' => 'Producto / servicio', 'COBRO' => 'Cobro', 'INFO' => 'Información adicional'];
        $rows = [];
        foreach ($this->service->buscarEnDetalles($idEmpresa, $q, $idUsuarioFiltro, 50) as $r) {
            $rows[] = [
                'origen'         => $origenes[$r['origen']] ?? $r['origen'],
                'referencia'     => $r['referencia'] ?? '',
                'descripcion'    => $r['descripcion'] ?? '',
                'fecha'          => !empty($r['fecha']) ? date('d-m-Y', strtotime((string) $r['fecha'])) : '',
                'monto'          => $r['monto'] !== null ? number_format((float) $r['monto'], 2) : '',
                'id_suscripcion' => (int) $r['id_suscripcion'],
                'cliente'        => $r['cliente'] ?? '',
                'identificacion' => $r['identificacion'] ?? '',
                'proximo_cobro'  => !empty($r['proximo_cobro']) ? date('d-m-Y', strtotime((string) $r['proximo_cobro'])) : '',
                'estado'         => ucfirst((string) ($r['estado'] ?? '')),
            ];
        }
        echo json_encode(['rows' => $rows], JSON_UNESCAPED_UNICODE);
    }

    public function getDetalleAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idSusc    = (int) ($_GET['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $detalle   = $this->service->getDetalle($idSusc, $idEmpresa);
            echo json_encode(['ok' => true, 'detalle' => $detalle]);
        } catch (\Exception $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    public function getPagosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idSusc    = (int) ($_GET['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $pagos     = $this->service->getPagosPorSuscripcion($idSusc, $idEmpresa);
            echo json_encode(['ok' => true, 'pagos' => $pagos]);
        } catch (\Exception $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    /**
     * Pestaña "Facturas" del modal: facturas y recibos de venta emitidos al cliente elegido
     * en el formulario (alcance=cliente) o solo los que generó la suscripción
     * (alcance=suscripcion), con su estado de cobro y el detalle de productos/servicios.
     * Solo lectura; paginado en el servidor.
     */
    public function facturasClienteAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $fuentes = $this->fuentesFacturas();
        if (empty($fuentes)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'mensaje' => 'No tiene permiso para ver facturas ni recibos de venta.']);
            return;
        }

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idSusc    = (int) ($_GET['id'] ?? 0);

        // Registros propios (§6): sin acceso total en Suscripciones, solo las que registró.
        if ($idSusc > 0) {
            $this->requireRegistroPropio($this->service->getSuscripcion($idSusc, $idEmpresa));
        }

        try {
            $page    = max(1, (int) ($_GET['page'] ?? 1));
            $perPage = 20;

            $res = $this->service->getFacturasCliente(
                $idEmpresa,
                (int) ($_GET['id_cliente'] ?? 0),
                $idSusc,
                ($_GET['alcance'] ?? '') === 'suscripcion',
                $fuentes,
                mb_substr(trim((string) ($_GET['b'] ?? '')), 0, 200),
                $page,
                $perPage,
                trim((string) ($_GET['sort'] ?? '')),
                trim((string) ($_GET['dir'] ?? ''))
            );

            echo json_encode([
                'ok'          => true,
                'rows'        => $res['rows'],
                'total'       => $res['total'],
                'resumen'     => $res['resumen'],
                'page'        => $page,
                'per_page'    => $perPage,
                'total_pages' => max(1, (int) ceil($res['total'] / $perPage)),
                // Qué documentos entran y de cuáles solo ve los propios (nota al pie de la pestaña).
                'fuentes'     => array_keys($fuentes),
                'propios'     => array_keys(array_filter($fuentes, static fn ($u) => $u !== null)),
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()], JSON_INVALID_UTF8_SUBSTITUTE);
        }
    }

    /**
     * Tipos de documento de la pestaña "Facturas" que este usuario puede ver, cada uno con
     * su filtro de registros propios (null = ve todos los del módulo).
     */
    private function fuentesFacturas(): array
    {
        $idUsuario = (int) $_SESSION['id_usuario'];
        $fuentes   = [];
        foreach (self::RUTAS_FACTURAS as $origen => $ruta) {
            $p = $this->permisosModuloPorRuta($ruta);
            if (!empty($p['ver'])) {
                $fuentes[$origen] = empty($p['todo']) ? $idUsuario : null;
            }
        }
        return $fuentes;
    }

    public function tokenizarTarjetaAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $id           = (int) ($_POST['id'] ?? 0);
            $idEmpresa    = (int) $_SESSION['id_empresa'];
            $idUsuario    = (int) $_SESSION['id_usuario'];
            $onetimeToken = trim($_POST['kushki_token'] ?? '');

            if (!$onetimeToken) {
                throw new \InvalidArgumentException('Token de Kushki no recibido.');
            }

            $kushki    = new KushkiService($idEmpresa);
            $tokenData = $kushki->crearTokenSuscripcion($onetimeToken);

            $this->service->guardarTokenKushki($id, $idEmpresa, $tokenData, $idUsuario);

            echo json_encode([
                'ok'      => true,
                'last4'   => $tokenData['last4'],
                'brand'   => $tokenData['brand'],
                'mensaje' => 'Tarjeta guardada correctamente.',
            ]);
        } catch (\Exception $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    /**
     * Reenvía manualmente el enlace de registro de tarjeta Nuvei para una
     * suscripción existente (botón "Enviar enlace" en la pestaña Cobro).
     */
    public function enviarRegistroTarjetaNuveiAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $id        = (int) ($_POST['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];

            if ($id <= 0) {
                throw new \InvalidArgumentException('Suscripción inválida.');
            }

            $correoDestino = trim($_POST['correo_destino'] ?? '');

            $resultado = $this->enviarRegistroTarjetaNuvei($id, $idEmpresa, $idUsuario, $correoDestino ?: null);
            echo json_encode($resultado);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    /**
     * Lista las tarjetas Nuvei ya guardadas del cliente de una suscripción,
     * para permitir reutilizar una tarjeta existente sin pedir un registro nuevo.
     */
    public function getTarjetasClienteNuveiAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idSusc    = (int) ($_GET['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];

            $repo = new SuscripcionesRepository();
            $susc = $repo->findById($idSusc, $idEmpresa);
            if (!$susc) {
                throw new \Exception('Suscripción no encontrada.');
            }

            $nuvei    = new \App\Services\NuveiService(new \App\repositories\NuveiRepository());
            $tarjetas = $nuvei->getTarjetasCliente($idEmpresa, (int) $susc['id_cliente']);

            echo json_encode(['ok' => true, 'tarjetas' => $tarjetas]);
        } catch (\Exception $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    /**
     * Vincula directamente una tarjeta Nuvei ya guardada del cliente a la
     * suscripción, sin necesidad de un nuevo enlace de registro.
     */
    public function vincularTarjetaNuveiAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $idSusc         = (int) ($_POST['id'] ?? 0);
            $idNuveiTarjeta = (int) ($_POST['id_nuvei_tarjeta'] ?? 0);
            $idEmpresa      = (int) $_SESSION['id_empresa'];

            $repo = new SuscripcionesRepository();
            $susc = $repo->findById($idSusc, $idEmpresa);
            if (!$susc) {
                throw new \Exception('Suscripción no encontrada.');
            }

            $nuvei   = new \App\Services\NuveiService(new \App\repositories\NuveiRepository());
            $tarjeta = $nuvei->getTarjetasCliente($idEmpresa, (int) $susc['id_cliente']);
            $existe  = array_filter($tarjeta, fn($t) => (int) $t['id'] === $idNuveiTarjeta);
            if (empty($existe)) {
                throw new \Exception('La tarjeta seleccionada no pertenece a este cliente.');
            }

            $repo->updateNuveiTarjeta($idSusc, $idNuveiTarjeta);
            echo json_encode(['ok' => true, 'mensaje' => 'Tarjeta vinculada correctamente.']);
        } catch (\Exception $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    /**
     * Genera la solicitud de registro de tarjeta Nuvei y envía el enlace por
     * correo al cliente de la suscripción. Reutilizado por store() (envío
     * automático al crear) y por enviarRegistroTarjetaNuveiAjax() (reenvío manual).
     */
    private function enviarRegistroTarjetaNuvei(int $idSuscripcion, int $idEmpresa, int $idUsuario, ?string $correoOverride = null): array
    {
        $db = \App\core\Database::getConnection();
        $st = $db->prepare(
            "SELECT s.id, s.id_cliente, c.nombre AS cliente_nombre, c.email AS cliente_email
             FROM suscripciones s
             LEFT JOIN clientes c ON c.id = s.id_cliente
             WHERE s.id = ? AND s.id_empresa = ? AND s.eliminado = false"
        );
        $st->execute([$idSuscripcion, $idEmpresa]);
        $susc = $st->fetch(\PDO::FETCH_ASSOC);

        if (!$susc) {
            return ['ok' => false, 'mensaje' => 'Suscripción no encontrada.'];
        }

        // Permite corregir el correo desde el modal antes de enviar (mismo patrón
        // que "Enviar cobro con Nuvei/Payphone" en Facturas de Venta), sin tocar
        // el correo registrado del cliente si solo se usa para este envío puntual.
        $correoCliente = filter_var($correoOverride, FILTER_VALIDATE_EMAIL)
            ? $correoOverride
            : trim((string) ($susc['cliente_email'] ?? ''));
        if (!filter_var($correoCliente, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'mensaje' => 'No hay un correo válido para enviar el enlace. Ingresa uno.'];
        }

        $nuvei = new \App\Services\NuveiService(new \App\repositories\NuveiRepository());
        $sol   = $nuvei->prepararSolicitudTarjeta($idEmpresa, [
            'id_cliente'    => (int) $susc['id_cliente'],
            'modulo'        => 'suscripcion',
            'id_referencia' => $idSuscripcion,
            'email'         => $correoCliente,
            'id_usuario'    => $idUsuario,
        ]);

        if (!$sol['ok']) {
            return $sol;
        }

        $host       = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scheme     = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $urlBaseAbs = $scheme . '://' . $host . rtrim(BASE_URL, '/');
        $urlRegistro = $urlBaseAbs . '/nuvei/tarjeta/' . $sol['token'];

        $empresaModel  = new \App\models\Empresa();
        $empresaData   = $empresaModel->getPorId($idEmpresa) ?? [];
        $empresaNombre = $empresaData['nombre_comercial'] ?? $empresaData['razon_social'] ?? '';

        $emailSvc = new \App\Services\EnvioDocumentosSRIService();
        $enviado  = $emailSvc->enviarRegistroTarjeta(
            $idEmpresa,
            $correoCliente,
            $susc['cliente_nombre'] ?? 'Cliente',
            $empresaNombre,
            $urlRegistro
        );

        if (!$enviado) {
            return ['ok' => false, 'mensaje' => 'No se pudo enviar el correo. Verifica la configuración de correo de la empresa.'];
        }

        return ['ok' => true, 'mensaje' => 'Enlace de registro enviado a ' . $correoCliente, 'correo' => $correoCliente];
    }

    public function getPeriodicidadesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $periodicidades = $this->service->getPeriodicidades();
        echo json_encode(['ok' => true, 'periodicidades' => $periodicidades]);
    }

    public function getClientesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar = trim($_GET['q'] ?? '');

        $repo = new \App\repositories\modulos\ClienteRepository();
        // soloActivos = true: excluir clientes inactivos en la selección.
        $result = $repo->getListado($idEmpresa, $buscar, 1, 12, 'nombre', 'ASC', null, true);

        echo json_encode(['ok' => true, 'rows' => $result['rows']]);
        exit;
    }

    public function getProductosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar = trim($_GET['q'] ?? '');

        $repo = new \App\repositories\modulos\ProductoRepository();
        $result = $repo->getListado($idEmpresa, $buscar, 1, 12, 'nombre', 'ASC', null, 'venta', true);

        echo json_encode(['ok' => true, 'rows' => $result['rows']]);
        exit;
    }

    public function generarFacturasManualAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido.']);
            return;
        }

        $idEmpresa          = (int) $_SESSION['id_empresa'];
        $idPuntoEmision     = (int) ($_POST['id_punto_emision'] ?? 0);
        $idPeriodicidad     = (int) ($_POST['id_periodicidad'] ?? 0);
        $textoItem          = trim($_POST['texto_item'] ?? '');
        $infoConcepto       = trim($_POST['info_concepto'] ?? '');
        $infoDetalle        = trim($_POST['info_detalle'] ?? '');

        if ($idPuntoEmision <= 0) {
            echo json_encode(['ok' => false, 'mensaje' => 'Debe seleccionar una serie válida.']);
            return;
        }

        if ($idPeriodicidad <= 0) {
            echo json_encode(['ok' => false, 'mensaje' => 'Debe seleccionar una periodicidad.']);
            return;
        }

        try {
            $db = \App\core\Database::getConnection();
            
            // 1. Obtener datos del Establecimiento y Punto de Emisión seleccionado
            $stab = $db->prepare(
                "SELECT ep.*, pe.id AS id_punto_emision, pe.codigo_punto AS punto_emision_codigo
                 FROM empresa_establecimiento ep
                 JOIN empresa_punto_emision pe ON pe.id_establecimiento = ep.id
                 WHERE pe.id = :id_punto AND ep.id_empresa = :id_empresa AND ep.estado = 'activo' AND pe.eliminado = false AND ep.eliminado = false
                 LIMIT 1"
            );
            $stab->execute([':id_punto' => $idPuntoEmision, ':id_empresa' => $idEmpresa]);
            $estabConfig = $stab->fetch(\PDO::FETCH_ASSOC);

            if (!$estabConfig) {
                echo json_encode(['ok' => false, 'mensaje' => 'La serie seleccionada no es válida o está inactiva.']);
                return;
            }

            // 2. Obtener configuración de la empresa
            $empresaModel = new \App\models\Empresa();
            $empresaConfig = $empresaModel->getPorId($idEmpresa);
            if (empty($empresaConfig)) {
                echo json_encode(['ok' => false, 'mensaje' => 'No hay configuración de empresa.']);
                return;
            }

            // 3. Obtener suscripciones vencidas para la periodicidad seleccionada
            $suscRepo = new \App\repositories\modulos\SuscripcionesRepository();
            $suscripciones = $suscRepo->getParaGeneracionManual($idEmpresa, $idPeriodicidad);
            if (empty($suscripciones)) {
                echo json_encode(['ok' => false, 'mensaje' => 'No hay suscripciones activas y vencidas para la periodicidad seleccionada.']);
                return;
            }

            // Generación unificada: el service decide Factura o Recibo de Venta
            // según el tipo_comprobante de cada suscripción. Es el mismo punto de
            // generación que usa la automatización/cron (SuscripcionesHandler).
            $facturacion = new \App\Services\modulos\SuscripcionFacturacionService(
                new \App\Services\modulos\FacturaVentaService(
                    new \App\repositories\modulos\FacturaVentaRepository(),
                    new \App\Rules\modulos\FacturaVentaRules(),
                    new \App\Services\LogSistemaService()
                ),
                new \App\Services\SecuencialService(),
                new \App\Services\modulos\ReciboVentaService(
                    new \App\repositories\modulos\ReciboVentaRepository(),
                    new \App\Rules\modulos\ReciboVentaRules(),
                    new \App\Services\LogSistemaService()
                )
            );

            $idUsuario = (int) $_SESSION['id_usuario'];
            $extras = [
                'texto_item'    => $textoItem,
                'info_concepto' => $infoConcepto,
                'info_detalle'  => $infoDetalle,
            ];

            $generadas = 0;
            $errores   = 0;
            $errorMsgs = [];

            foreach ($suscripciones as $susc) {
                $idSusc = (int) $susc['id'];
                $meses  = (int) ($susc['periodicidad_meses'] ?? 1);
                $codigo = (string) ($susc['periodicidad_codigo'] ?? '');

                try {
                    $detalle = $suscRepo->getDetalle($idSusc);
                    if (empty($detalle)) continue;

                    // El período generado alimenta los placeholders ({mes}, {anio}, ...)
                    $periodo = (string) $susc['proximo_cobro'];

                    $res = $facturacion->generarUnPeriodo(
                        $idEmpresa, $idUsuario, $susc, $detalle, $estabConfig, $empresaConfig, $extras, $periodo
                    );

                    // Avanzar el próximo cobro SOLO si el documento se creó. La generación
                    // está DESACOPLADA del cobro: si la suscripción usa tarjeta vía Nuvei, el
                    // cargo real lo hace la automatización "Cobrar suscripciones (Nuvei)" —
                    // aquí solo se deja el pago en 'pendiente'. Crédito y Kushki no se tocan.
                    $suscRepo->updateProximoCobro(
                        $idSusc,
                        $this->service->calcularProximoCobro($periodo, $meses, $codigo)
                    );

                    $esNuveiTarjeta = ($susc['forma_cobro'] ?? '') === 'tarjeta'
                        && ($susc['pasarela_tarjeta'] ?? '') === 'nuvei'
                        && !empty($susc['id_nuvei_tarjeta']);

                    $suscRepo->insertPago([
                        'id_suscripcion' => $idSusc,
                        'id_empresa'     => $idEmpresa,
                        'id_factura'     => $res['id_factura'],
                        'id_recibo'      => $res['id_recibo'],
                        'fecha_cobro'    => date('Y-m-d'),
                        'monto'          => $res['importe'],
                        'estado'         => $esNuveiTarjeta ? 'pendiente' : 'exitoso',
                        'id_usuario'     => $idUsuario,
                    ]);

                    $generadas++;
                } catch (\Throwable $e) {
                    $errorMsgs[] = "Suscripcion {$idSusc}: " . $e->getMessage();
                    $errores++;
                    \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__ . '#suscripcion_' . $idSusc]);
                }
            }

            if ($generadas === 0) {
                echo json_encode(['ok' => false, 'mensaje' => 'No se pudieron generar documentos. Detalles: ' . implode(' | ', $errorMsgs)], JSON_INVALID_UTF8_SUBSTITUTE);
                return;
            }

            $mensaje = "Se generaron $generadas documento(s) correctamente.";
            if ($errores > 0) {
                $mensaje .= " Hubo $errores con error: " . implode(' | ', $errorMsgs);
            }

            echo json_encode([
                'ok'      => true,
                'mensaje' => $mensaje,
            ], JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()], JSON_INVALID_UTF8_SUBSTITUTE);
        }
    }

    // ── Exportar listado ───────────────────────────────────────────────────────

    /** Filas del listado según el buscador/orden actual (sin paginar). */
    private function filasParaExportar(): array
    {
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $ordenCol  = trim($_GET['sort'] ?? $_POST['sort'] ?? 'proximo_cobro');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? 'asc'));

        $perm            = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $data = $this->service->getListado($idEmpresa, $buscar, 1, 0, $ordenCol, $ordenDir, $idUsuarioFiltro);
        return $data['rows'] ?? [];
    }

    private const CABECERAS_EXPORT = [
        'Cliente', 'Identificación', 'Periodicidad', 'Comprobante', 'Forma de cobro',
        'Próximo cobro', 'Fecha inicio', 'Fecha fin', 'Ítems', 'Estado',
    ];

    /** Una fila del listado a array de celdas, en el orden de CABECERAS_EXPORT. */
    private function filaExport(array $r): array
    {
        $fecha = static fn($v) => !empty($v) ? date('d-m-Y', strtotime((string) $v)) : '-';
        return [
            (string) ($r['nombre_cliente'] ?? ''),
            (string) ($r['identificacion_cliente'] ?? ''),
            (string) ($r['nombre_periodicidad'] ?? '-'),
            ucfirst((string) ($r['tipo_comprobante'] ?? 'factura')),
            ucfirst((string) ($r['forma_cobro'] ?? '')),
            $fecha($r['proximo_cobro'] ?? null),
            $fecha($r['fecha_inicio'] ?? null),
            $fecha($r['fecha_fin'] ?? null),
            (string) ((int) ($r['total_items'] ?? 0)),
            ucfirst((string) ($r['estado'] ?? 'activo')),
        ];
    }

    public function exportPdf(): void
    {
        $this->requireLeer();

        try {
            $rows = $this->filasParaExportar();

            $empresa       = (new \App\models\Empresa())->getPorId((int) $_SESSION['id_empresa']);
            $nombreEmpresa = $empresa['nombre'] ?? 'REPORTE DE SUSCRIPCIONES';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            $anchos = ['22%', '13%', '10%', '9%', '9%', '9%', '9%', '9%', '4%', '6%'];

            // Resumen de valores (mismas suscripciones del listado = filtro de búsqueda).
            $filtro      = trim($_GET['b'] ?? $_POST['b'] ?? '');
            $textoFiltro = $filtro !== '' ? $filtro : 'Ninguno (todas las suscripciones)';
            $resumen     = $this->service->getResumenValores((int) $_SESSION['id_empresa'], $rows);
            // Todas las secciones del resumen tienen 8 columnas: una sola tabla con estos anchos.
            $anchosRes   = ['16%', '24%', '10%', '10%', '10%', '10%', '10%', '10%'];
            $celdaRes    = static function ($v, bool $moneda): string {
                if (is_string($v)) {
                    return htmlspecialchars($v);
                }
                if ($moneda) {
                    return number_format((float) $v, 2, '.', ',');
                }
                return (float) $v == (int) $v ? (string) (int) $v : number_format((float) $v, 2, '.', ',');
            };

            // Detalle por cliente (ítems que se facturan, con IVA): una sola tabla de 11 columnas.
            $detalle    = $this->service->getDetalleClientes((int) $_SESSION['id_empresa'], $rows);
            $anchosDet  = ['8%', '6%', '7%', '26%', '5%', '7%', '7%', '9%', '7%', '8%', '10%'];
            $sumaAnchos = static fn(int $desde, int $n) => array_sum(array_map('floatval', array_slice($anchosDet, $desde, $n))) . '%';
            $n2         = static fn($v) => number_format((float) $v, 2, '.', ',');
            // Cantidad/precio: hasta 6 decimales, sin ceros sobrantes (mínimo 2 en el precio).
            $numLibre   = static function ($v, int $minDec = 0): string {
                $s = rtrim(rtrim(number_format((float) $v, 6, '.', ','), '0'), '.');
                $dec = strpos($s, '.') === false ? 0 : strlen($s) - strpos($s, '.') - 1;
                return $dec < $minDec ? number_format((float) $v, $minDec, '.', ',') : $s;
            };

            ob_start();
            ?>
            <style>
                table { width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 7.5pt; table-layout: fixed; }
                th { background: #f2f2f2; border: 1px solid #ccc; padding: 4px; text-align: left; }
                td { border: 1px solid #ccc; padding: 4px; overflow: hidden; word-wrap: break-word; }
                .header { text-align: center; margin-bottom: 12px; }
                h1 { margin: 0; font-size: 14pt; color: #333; }
                h2 { margin: 3px 0 0 0; color: #666; font-size: 10pt; text-transform: uppercase; }
                table.res td { padding: 3px 4px; }
                table.res td.num { text-align: right; }
                table.res tr.ancho td { border: none; padding: 0; font-size: 1pt; }
                table.res tr.sec td { border: none; font-size: 10pt; font-weight: bold; color: #1F4E79; padding: 8px 0 3px 0; }
                table.res tr.cab td { background: #4472C4; color: #fff; font-weight: bold; text-align: center; }
                table.res tr.grp td { font-weight: bold; color: #1F4E79; background: #f7f9fc; }
                table.res tr.tot td { font-weight: bold; background: #D9E1F2; }
                table.det th { background: #4472C4; color: #fff; text-align: center; padding: 3px; }
                table.det td { padding: 3px 4px; }
                table.det td.num { text-align: right; }
                table.det tr.cli td { font-weight: bold; color: #1F4E79; background: #eef2f9; font-size: 8pt; }
                table.det tr.inf td { color: #555; font-style: italic; }
                table.det tr.tot td { font-weight: bold; background: #D9E1F2; }
                table.det tr.gen td { font-weight: bold; background: #B4C6E7; }
                table.det tr.sep td { border: none; font-size: 4pt; padding: 0; }
            </style>
            <page backtop="10mm" backbottom="10mm" backleft="8mm" backright="8mm">
                <div class="header">
                    <h1><?= htmlspecialchars($nombreEmpresa) ?></h1>
                    <h2>Listado de Suscripciones</h2>
                </div>
                <?php // Tabla de una celda: un <div> centrado se desplaza en Html2Pdf. ?>
                <table style="width:100%; margin-bottom:8px;"><tr><td style="width:100%; border:none; text-align:center; font-size:8pt; color:#555;">Filtro de búsqueda: <?= htmlspecialchars($textoFiltro) ?> - Suscripciones: <?= count($rows) ?></td></tr></table>
                <table>
                    <thead>
                        <tr>
                            <?php foreach (self::CABECERAS_EXPORT as $i => $h): ?>
                                <th style="width: <?= $anchos[$i] ?? 'auto' ?>"><?= htmlspecialchars($h) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <?php foreach ($this->filaExport($r) as $celda): ?>
                                    <td><?= htmlspecialchars($celda) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </page>
            <page backtop="10mm" backbottom="10mm" backleft="8mm" backright="8mm">
                <div class="header">
                    <h1><?= htmlspecialchars($nombreEmpresa) ?></h1>
                    <h2>Resumen de valores</h2>
                </div>
                <table style="width:100%;"><tr><td style="width:100%; border:none; text-align:center; font-size:8pt; color:#555;">Filtro de búsqueda: <?= htmlspecialchars($textoFiltro) ?> - Suscripciones: <?= count($rows) ?><br>Subtotal, IVA y Total son el valor de un cobro; la proyección multiplica por los cobros que genera la periodicidad en un año.</td></tr></table>
                <?php // Una sola tabla para todo el resumen (Html2Pdf parte filas si se abren varias tablas cerca del pie). ?>
                <table class="res">
                    <tr class="ancho">
                        <?php foreach ($anchosRes as $w): ?><td style="width: <?= $w ?>">&nbsp;</td><?php endforeach; ?>
                    </tr>
                    <?php foreach ($resumen as $sec): ?>
                        <tr class="sec"><td colspan="8"><?= htmlspecialchars($sec['titulo']) ?></td></tr>
                        <tr class="cab">
                            <?php foreach ($sec['cabeceras'] as $i => $c): ?>
                                <td style="width: <?= $anchosRes[$i] ?>"><?= htmlspecialchars($c) ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <?php foreach ($sec['filas'] as $f): ?>
                            <?php if ($f['tipo'] === 'grupo'): ?>
                                <tr class="grp"><td colspan="8"><?= htmlspecialchars($f['valores'][0]) ?></td></tr>
                            <?php else: ?>
                                <tr class="<?= $f['tipo'] === 'total' ? 'tot' : '' ?>">
                                    <?php foreach ($f['valores'] as $i => $v): ?>
                                        <td style="width: <?= $anchosRes[$i] ?>" class="<?= is_string($v) ? '' : 'num' ?>"><?= $celdaRes($v, in_array($i, $sec['monedas'], true)) ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </table>
            </page>
            <page backtop="10mm" backbottom="10mm" backleft="8mm" backright="8mm">
                <div class="header">
                    <h1><?= htmlspecialchars($nombreEmpresa) ?></h1>
                    <h2>Detalle por cliente</h2>
                </div>
                <table style="width:100%; margin-bottom:6px;"><tr><td style="width:100%; border:none; text-align:center; font-size:8pt; color:#555;">Filtro de búsqueda: <?= htmlspecialchars($textoFiltro) ?> - Suscripciones: <?= count($rows) ?><br>Lo que se factura en cada cobro de cada suscripción, con su IVA; la proyección anual multiplica por los cobros del año.</td></tr></table>
                <?php // Una sola tabla: el thead (con los anchos) se repite en cada página; clientes, info y totales van como filas con colspan. ?>
                <table class="det">
                    <thead>
                        <tr>
                            <?php foreach (['Periodicidad', 'Estado', 'Próx. cobro', 'Concepto', 'Cant.', 'P. unitario', 'Subtotal', 'Tarifa IVA', 'IVA', 'Total cobro', 'Proy. anual'] as $i => $c): ?>
                                <th style="width: <?= $anchosDet[$i] ?>"><?= $c ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($detalle['clientes'] as $cli): ?>
                        <tr class="cli"><td colspan="11" style="width:100%"><?= htmlspecialchars($cli['nombre']) ?><?= $cli['identificacion'] !== '' ? ' - ' . htmlspecialchars($cli['identificacion']) : '' ?><?= $cli['email'] !== '' ? ' - ' . htmlspecialchars($cli['email']) : '' ?></td></tr>
                        <?php foreach ($cli['suscripciones'] as $s): ?>
                            <?php if (!$s['items']): ?>
                                <tr>
                                    <td style="width: <?= $anchosDet[0] ?>"><?= htmlspecialchars($s['periodicidad']) ?></td>
                                    <td style="width: <?= $anchosDet[1] ?>"><?= htmlspecialchars($s['estado']) ?></td>
                                    <td style="width: <?= $anchosDet[2] ?>"><?= htmlspecialchars($s['proximo_cobro']) ?></td>
                                    <td colspan="8" style="width: <?= $sumaAnchos(3, 8) ?>">Sin ítems registrados</td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($s['items'] as $k => $it): ?>
                                <tr>
                                    <?php // Datos de la suscripción solo en su primera línea. ?>
                                    <td style="width: <?= $anchosDet[0] ?>"><?= $k === 0 ? htmlspecialchars($s['periodicidad']) : '' ?></td>
                                    <td style="width: <?= $anchosDet[1] ?>"><?= $k === 0 ? htmlspecialchars($s['estado']) : '' ?></td>
                                    <td style="width: <?= $anchosDet[2] ?>"><?= $k === 0 ? htmlspecialchars($s['proximo_cobro']) : '' ?></td>
                                    <td style="width: <?= $anchosDet[3] ?>"><?= htmlspecialchars(($it['codigo'] !== '' ? $it['codigo'] . ' - ' : '') . $it['concepto']) ?><?= $it['descripcion'] !== '' ? '<br><i>' . htmlspecialchars($it['descripcion']) . '</i>' : '' ?></td>
                                    <td style="width: <?= $anchosDet[4] ?>" class="num"><?= $numLibre($it['cantidad']) ?></td>
                                    <td style="width: <?= $anchosDet[5] ?>" class="num"><?= $numLibre($it['precio'], 2) ?></td>
                                    <td style="width: <?= $anchosDet[6] ?>" class="num"><?= $n2($it['base']) ?></td>
                                    <td style="width: <?= $anchosDet[7] ?>"><?= htmlspecialchars($it['tarifa']) ?></td>
                                    <td style="width: <?= $anchosDet[8] ?>" class="num"><?= $n2($it['iva']) ?></td>
                                    <td style="width: <?= $anchosDet[9] ?>" class="num"><?= $n2($it['total']) ?></td>
                                    <td style="width: <?= $anchosDet[10] ?>" class="num"><?= $n2($it['anual']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($s['info']): ?>
                                <tr class="inf"><td colspan="11" style="width:100%">Info adicional: <?= htmlspecialchars(implode(' · ', array_map(
                                    static fn($x) => $x['concepto'] !== '' && $x['detalle'] !== '' ? $x['concepto'] . ': ' . $x['detalle'] : $x['concepto'] . $x['detalle'],
                                    $s['info']
                                ))) ?></td></tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php $t = $cli['totales']; ?>
                        <tr class="tot">
                            <td colspan="6" style="width: <?= $sumaAnchos(0, 6) ?>">Total <?= htmlspecialchars($cli['nombre']) ?> (<?= $t['susc'] ?> <?= $t['susc'] === 1 ? 'suscripción' : 'suscripciones' ?>)</td>
                            <td style="width: <?= $anchosDet[6] ?>" class="num"><?= $n2($t['base']) ?></td>
                            <td style="width: <?= $anchosDet[7] ?>"></td>
                            <td style="width: <?= $anchosDet[8] ?>" class="num"><?= $n2($t['iva']) ?></td>
                            <td style="width: <?= $anchosDet[9] ?>" class="num"><?= $n2($t['base'] + $t['iva']) ?></td>
                            <td style="width: <?= $anchosDet[10] ?>" class="num"><?= $n2($t['anual']) ?></td>
                        </tr>
                        <tr class="sep"><td colspan="11" style="width:100%">&nbsp;</td></tr>
                    <?php endforeach; ?>
                    <?php $g = $detalle['general']; ?>
                        <tr class="gen">
                            <td colspan="6" style="width: <?= $sumaAnchos(0, 6) ?>">TOTAL GENERAL (<?= count($detalle['clientes']) ?> clientes, <?= $g['susc'] ?> suscripciones)</td>
                            <td style="width: <?= $anchosDet[6] ?>" class="num"><?= $n2($g['base']) ?></td>
                            <td style="width: <?= $anchosDet[7] ?>"></td>
                            <td style="width: <?= $anchosDet[8] ?>" class="num"><?= $n2($g['iva']) ?></td>
                            <td style="width: <?= $anchosDet[9] ?>" class="num"><?= $n2($g['base'] + $g['iva']) ?></td>
                            <td style="width: <?= $anchosDet[10] ?>" class="num"><?= $n2($g['anual']) ?></td>
                        </tr>
                    </tbody>
                </table>
            </page>
            <?php
            $content = ob_get_clean();

            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $html2pdf->writeHTML($content);
            $html2pdf->output('Suscripciones_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $_SESSION['suscripciones_msg'] = ['danger', 'Error al generar PDF: ' . $e->getMessage()];
            $this->redirect(BASE_URL . '/' . self::RUTA_MODULO);
        }
    }

    public function exportExcel(): void
    {
        $this->requireLeer();

        try {
            $rows = $this->filasParaExportar();

            $empresa       = (new \App\models\Empresa())->getPorId((int) $_SESSION['id_empresa']);
            $nombreEmpresa = $empresa['nombre'] ?? '';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            $exportData = array_map(fn($r) => $this->filaExport($r), $rows);

            // Las tres hojas salen de las mismas $rows: el filtro de búsqueda del listado.
            $filtro = trim($_GET['b'] ?? $_POST['b'] ?? '');
            $textoFiltro = $filtro !== '' ? $filtro : 'Ninguno (todas las suscripciones)';

            $reportService = new \App\Services\ReportService();
            $libro = $reportService->construirSpreadsheet(
                self::CABECERAS_EXPORT,
                $exportData,
                'Listado de Suscripciones',
                $nombreEmpresa,
                ['Filtro de búsqueda' => $textoFiltro, 'Suscripciones' => (string) count($rows)]
            );
            // Segunda hoja: resumen de valores por periodicidad, concepto e IVA.
            $this->service->agregarHojaResumenExcel($libro, (int) $_SESSION['id_empresa'], $rows, $textoFiltro);
            // Tercera hoja: cada cliente con lo que se le factura, línea por línea, con IVA.
            $this->service->agregarHojaDetalleClientesExcel($libro, (int) $_SESSION['id_empresa'], $rows, $textoFiltro);
            $reportService->descargarSpreadsheet($libro, 'Suscripciones');
            exit;
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            if (!headers_sent()) {
                $_SESSION['suscripciones_msg'] = ['danger', 'Error al generar Excel: ' . $e->getMessage()];
                $this->redirect(BASE_URL . '/' . self::RUTA_MODULO);
            }
            exit;
        }
    }

}
