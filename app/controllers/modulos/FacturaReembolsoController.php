<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\Services\modulos\FacturaReembolsoService;
use App\repositories\modulos\FacturaReembolsoRepository;
use App\models\Empresa;

class FacturaReembolsoController extends BaseModuloController
{
    private $service;
    private $repository;

    protected function getRutaModulo(): string
    {
        return 'modulos/factura-reembolso';
    }

    public function __construct()
    {
        parent::__construct();
        $this->repository = new FacturaReembolsoRepository();
        $rules            = new \App\Rules\modulos\FacturaReembolsoRules();
        $logService       = new \App\Services\LogSistemaService();
        $this->service    = new FacturaReembolsoService($this->repository, $rules, $logService);
    }

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());
        $buscar   = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_emision');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage  = 20;

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $result = $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $totalPages = (int) ceil($result['total'] / $perPage);

        $empresaModel = new Empresa();
        $empresaData  = $empresaModel->getPorId($idEmpresa);
        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);

        // Fusionar config del establecimiento principal (incluye id_forma_pago_sri_def,
        // usado para preseleccionar la forma de pago por defecto en la pestaña de pagos).
        if (!empty($establecimientos)) {
            try {
                $estRepo   = new \App\repositories\modulos\EmpresaRepository();
                $estConfig = $estRepo->getEstablecimientoConfig((int) $establecimientos[0]['id']);
                if ($estConfig) {
                    $empresaData = array_merge($empresaData ?? [], $estConfig);
                }
            } catch (\Throwable $e) {}
        }

        // Solo se ofrecen como Serie los puntos de emisión que ya tienen
        // configurado el secuencial inicial para "Facturas de reembolso"
        // (Empresa → Secuenciales); sin eso no se puede emitir válidamente.
        $secRepo = new \App\repositories\SecuencialRepository();
        $puntos = [];
        foreach ($establecimientos as $est) {
            $pts = $empresaModel->getPuntosEmision((int) $est['id']);
            foreach ($pts as $p) {
                $config = $secRepo->getConfigSecuencial((int) $p['id'], 'Facturas de reembolso');
                if (empty($config['id'])) {
                    continue;
                }
                $p['cod_establecimiento'] = $est['codigo'];
                $puntos[] = $p;
            }
        }

        // Series con documentos REALES (para el filtro "Serie" del buscador); $puntos
        // arriba es solo "qué serie puedo usar en un documento NUEVO" (ver comentario
        // en el bucle anterior).
        $seriesFiltro = $this->repository->getSeriesDistintas($idEmpresa);

        $total = $result['total'];
        $this->viewWithLayout('layouts.main', 'modulos/factura_reembolso/index', [
            'titulo'            => 'Facturas de Reembolso',
            'perm'              => $perm,
            'rows'              => $result['rows'],
            'total'             => $total,
            'page'              => $page,
            'totalPages'        => $totalPages,
            'perPage'           => $perPage,
            'from'              => $total > 0 ? (($page - 1) * $perPage) + 1 : 0,
            'to'                => $total > 0 ? min($page * $perPage, $total) : 0,
            'buscar'            => $buscar,
            'ordenCol'          => $ordenCol,
            'ordenDir'          => $ordenDir,
            'vistaConfig'       => $prefsVista,
            'base'              => BASE_URL,
            'rutaModulo'        => $this->getRutaModulo(),
            'empresa'           => $empresaData,
            'establecimientos'  => $establecimientos,
            'puntos'            => $puntos,
            'seriesFiltro'      => $seriesFiltro,
            'tarifasIva'        => $this->repository->getTarifasIva(),
            'formasPago'        => $this->repository->getFormasPago(),
            'tiposIdentificacion' => $this->repository->getTiposIdentificacionProveedor(),
            'fullWidth'         => true,
        ]);
    }

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());
        $buscar     = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page       = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol   = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_emision');
        $ordenDir   = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage    = 20;

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $result     = $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows       = $result['rows'];
        $total      = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
        $from       = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to         = $total > 0 ? min($page * $perPage, $total) : 0;

        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="9" class="text-center py-5 text-muted"><i class="bi bi-receipt fs-3 d-block mb-2"></i>No se encontraron facturas de reembolso.</td></tr>';
        } else {
            foreach ($rows as $r) {
                $rowData     = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                $numero      = htmlspecialchars(($r['establecimiento'] ?? '') . '-' . ($r['punto_emision'] ?? '') . '-' . ($r['secuencial'] ?? ''));
                $fecha       = !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '—';
                $estado      = $r['estado'] ?? 'borrador';
                $estadoClass = match ($estado) {
                    'autorizado' => 'bg-success bg-opacity-10 text-success border-success',
                    'anulado'    => 'bg-danger bg-opacity-10 text-danger border-danger',
                    'borrador'   => 'bg-secondary bg-opacity-10 text-secondary border-secondary',
                    default      => 'bg-primary bg-opacity-10 text-primary border-primary',
                };
                $estadoBadge = '<span class="badge ' . $estadoClass . ' border border-opacity-25">' . ucfirst($estado) . '</span>';

                $totalReembolsado = (float) ($r['total_base_imponible_reembolso'] ?? 0) + (float) ($r['total_impuesto_reembolso'] ?? 0);

                echo '<tr class="fr-row" role="button" tabindex="0" data-row=\'' . $rowData . '\' onclick="window.FR_abrirModalFR(this)">
                        <td class="ps-3" data-col="numero"><code>' . $numero . '</code></td>
                        <td data-col="fecha_emision">' . $fecha . '</td>
                        <td class="fw-medium text-truncate" data-col="cliente_nombre" style="max-width:200px">' . htmlspecialchars($r['cliente_nombre'] ?? '—') . '</td>
                        <td data-col="cliente_ruc"><small class="text-muted">' . htmlspecialchars($r['cliente_ruc'] ?? '—') . '</small></td>
                        <td class="text-center" data-col="cantidad_terceros"><span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">' . (int) ($r['cantidad_terceros'] ?? 0) . '</span></td>
                        <td class="text-end" data-col="total_reembolsado">$' . number_format($totalReembolsado, 2) . '</td>
                        <td class="text-end fw-bold" data-col="importe_total">$' . number_format((float) ($r['importe_total'] ?? 0), 2) . '</td>
                        <td data-col="usuario_nombre">' . htmlspecialchars($r['usuario_nombre'] ?? '—') . '</td>
                        <td class="text-center pe-3" data-col="estado">' . $estadoBadge . '</td>
                      </tr>';
            }
        }
        $rowsHtml = ob_get_clean();

        ob_start();
        $prevDisabled = ($page <= 1)           ? 'disabled' : '';
        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
        echo '<button type="button" class="btn btn-outline-secondary" ' . $prevDisabled . ' onclick="window.FR_cambiarPaginaAjax(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>
              <button type="button" class="btn btn-outline-secondary" ' . $nextDisabled . ' onclick="window.FR_cambiarPaginaAjax(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>';
        $paginationHtml = ob_get_clean();

        $urlBase = BASE_URL . '/' . $this->getRutaModulo();
        echo json_encode([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'pagination' => $paginationHtml,
            'info'       => "$from-$to/$total",
            'total'      => $total,
            'pdf_url'    => $urlBase . '/export-pdf?b=' . urlencode($buscar) . "&sort=$ordenCol&dir=$ordenDir",
            'excel_url'  => $urlBase . '/export-excel?b=' . urlencode($buscar) . "&sort=$ordenCol&dir=$ordenDir",
        ]);
        exit;
    }

    private function getListadoParaExport(): array
    {
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $ordenCol  = trim($_GET['sort'] ?? $_POST['sort'] ?? 'fecha_emision');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? 'DESC'));

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        return $this->repository->getListado($idEmpresa, $buscar, 1, 0, $ordenCol, $ordenDir, $idUsuarioFiltro);
    }

    public function exportExcel(): void
    {
        $this->requireLeer();
        $rows = $this->getListadoParaExport()['rows'];

        $idEmpresa     = (int) $_SESSION['id_empresa'];
        $empresaModel  = new Empresa();
        $empresa       = $empresaModel->getPorId($idEmpresa);
        $nombreEmpresa = $empresa['nombre'] ?? '';

        $headers    = ['Número', 'Fecha Emisión', 'Cliente', 'Identificación', 'Terceros', 'Base Reembolso', 'IVA Reembolso', 'Total', 'Estado'];
        $exportData = [];
        foreach ($rows as $r) {
            $numero = ($r['establecimiento'] ?? '') . '-' . ($r['punto_emision'] ?? '') . '-' . ($r['secuencial'] ?? '');
            $exportData[] = [
                (string) $numero,
                !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '',
                (string) ($r['cliente_nombre'] ?? ''),
                (string) ($r['cliente_ruc'] ?? ''),
                (int) ($r['cantidad_terceros'] ?? 0),
                (float) ($r['total_base_imponible_reembolso'] ?? 0),
                (float) ($r['total_impuesto_reembolso'] ?? 0),
                (float) ($r['importe_total'] ?? 0),
                ucfirst((string) ($r['estado'] ?? '')),
            ];
        }

        try {
            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }
            $reportService = new \App\Services\ReportService();
            $reportService->exportToExcel('Facturas de Reembolso', $headers, $exportData, 'Listado Facturas de Reembolso', $nombreEmpresa);
        } catch (\Throwable $e) {
            header('Content-Type: text/html');
            echo 'Error al generar Excel: ' . $e->getMessage();
            exit;
        }
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        $rows = $this->getListadoParaExport()['rows'];

        $idEmpresa     = (int) $_SESSION['id_empresa'];
        $empresaModel  = new Empresa();
        $empresa       = $empresaModel->getPorId($idEmpresa);
        $nombreEmpresa = $empresa['nombre'] ?? 'REPORTE DE FACTURAS DE REEMBOLSO';

        try {
            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            ob_start();
?>
            <style>
                table { width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 7pt; table-layout: fixed; }
                th { background: #f2f2f2; border: 1px solid #ccc; padding: 3px; text-align: left; }
                td { border: 1px solid #ccc; padding: 3px; overflow: hidden; word-wrap: break-word; }
                .text-end { text-align: right; }
                .header { text-align: center; margin-bottom: 15px; width: 100%; }
                h1 { margin: 0; font-size: 14pt; color: #333; }
                h2 { margin: 3px 0 0 0; color: #666; font-size: 10pt; text-transform: uppercase; }
            </style>
            <page backtop="10mm" backbottom="10mm" backleft="10mm" backright="10mm">
                <div class="header">
                    <h1><?= htmlspecialchars($nombreEmpresa) ?></h1>
                    <h2>Listado de Facturas de Reembolso</h2>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 12%">Número</th>
                            <th style="width: 10%">Fecha</th>
                            <th style="width: 24%">Cliente</th>
                            <th style="width: 12%">Identificación</th>
                            <th style="width: 8%">Terceros</th>
                            <th style="width: 14%" class="text-end">Total</th>
                            <th style="width: 12%">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <?php $numero = ($r['establecimiento'] ?? '') . '-' . ($r['punto_emision'] ?? '') . '-' . ($r['secuencial'] ?? ''); ?>
                            <tr>
                                <td><?= htmlspecialchars((string) $numero) ?></td>
                                <td><?= !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '-' ?></td>
                                <td><?= htmlspecialchars((string) ($r['cliente_nombre'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string) ($r['cliente_ruc'] ?? '')) ?></td>
                                <td><?= (int) ($r['cantidad_terceros'] ?? 0) ?></td>
                                <td class="text-end"><?= number_format((float) ($r['importe_total'] ?? 0), 2) ?></td>
                                <td><?= ucfirst((string) ($r['estado'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </page>
<?php
            $content = ob_get_clean();

            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $html2pdf->writeHTML($content);
            $html2pdf->output('FacturasReembolso_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            header('Content-Type: text/html');
            echo 'Error al generar PDF: ' . $e->getMessage();
            exit;
        }
    }

    public function getFacturaReembolsoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$id) {
            echo json_encode(['ok' => false, 'mensaje' => 'ID requerido']);
            exit;
        }

        $cabecera = $this->service->getPorId($id, $idEmpresa);
        if (!$cabecera) {
            echo json_encode(['ok' => false, 'mensaje' => 'Factura de reembolso no encontrada']);
            exit;
        }

        echo json_encode(['ok' => true] + $cabecera);
        exit;
    }

    public function guardarAjax(): void
    {
        header('Content-Type: application/json');

        try {
            $data = $_POST;
            if (isset($_POST['data'])) {
                $data = json_decode($_POST['data'], true);
            }

            $data['id_empresa'] = (int) $_SESSION['id_empresa'];
            $data['id_usuario'] = (int) $_SESSION['id_usuario'];

            $empresaModel = new Empresa();
            $empresaData  = $empresaModel->getPorId($data['id_empresa']) ?? [];
            try {
                $establecimientos = $empresaModel->getEstablecimientos($data['id_empresa']);
                if (!empty($establecimientos)) {
                    $estRepo   = new \App\repositories\modulos\EmpresaRepository();
                    $estConfig = $estRepo->getEstablecimientoConfig((int) $establecimientos[0]['id']);
                    if ($estConfig) {
                        $empresaData = array_merge($empresaData, $estConfig);
                    }
                }
            } catch (\Throwable $e) {}
            $data['empresa_config'] = $empresaData;

            if (!empty($data['id_punto_emision'])) {
                $db = \App\core\Database::getConnection();
                $st = $db->prepare("
                    SELECT p.id_establecimiento, p.codigo_punto, e.codigo AS cod_establecimiento
                    FROM empresa_punto_emision p
                    JOIN empresa_establecimiento e ON e.id = p.id_establecimiento
                    WHERE p.id = ?
                    LIMIT 1
                ");
                $st->execute([$data['id_punto_emision']]);
                $puntoRow = $st->fetch(\PDO::FETCH_ASSOC);

                if ($puntoRow) {
                    if (empty($data['id_establecimiento'])) $data['id_establecimiento'] = $puntoRow['id_establecimiento'];
                    if (empty($data['establecimiento']))    $data['establecimiento']    = $puntoRow['cod_establecimiento'];
                    if (empty($data['punto_emision']))      $data['punto_emision']      = $puntoRow['codigo_punto'];
                }
            }

            $idExistente = !empty($data['id']) ? (int) $data['id'] : 0;

            if ($idExistente > 0) {
                $this->requireActualizar();
                $id = $this->service->actualizar($idExistente, $data);
                $mensaje = 'Factura de reembolso actualizada exitosamente.';
            } else {
                $this->requireCrear();
                $id = $this->service->crear($data);
                $mensaje = 'Factura de reembolso guardada exitosamente.';
            }

            echo json_encode(['ok' => true, 'mensaje' => $mensaje, 'id' => $id]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function eliminarAjax(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        $id        = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            $this->service->eliminar($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'mensaje' => 'Factura de reembolso eliminada correctamente.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function anularAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $id        = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            $this->service->anular($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'mensaje' => 'Factura de reembolso anulada correctamente.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function getTarifasIvaAjax(): void
    {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'data' => $this->repository->getTarifasIva()]);
        exit;
    }

    public function getFormasPagoAjax(): void
    {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'data' => $this->repository->getFormasPago()]);
        exit;
    }

    public function getTiposIdentificacionAjax(): void
    {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'data' => $this->repository->getTiposIdentificacionProveedor()]);
        exit;
    }

    public function getSecuencialAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idPt = (int) ($_GET['id_punto'] ?? $_GET['id_punto_emision'] ?? 0);
        if ($idPt <= 0) {
            echo json_encode(['ok' => false, 'mensaje' => 'Punto de emisión requerido.']);
            exit;
        }

        $secuencialService = new \App\Services\SecuencialService();
        $res = $secuencialService->obtenerSiguienteSecuencial($idPt, 'Facturas de reembolso');

        echo json_encode(array_merge(['ok' => true], $res));
        exit;
    }

    /**
     * Consulta RUC/Cédula al servicio de identificación SRI (reutilizable, mismo
     * patrón que Proveedores/Clientes), para autocompletar la razón social del
     * tercero reembolsado al darlo de alta manualmente.
     */
    public function consultarSriAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json; charset=utf-8');
        $identificacion = trim($_POST['identificacion'] ?? $_GET['identificacion'] ?? '');
        if ($identificacion === '') {
            echo json_encode(['ok' => false, 'error' => 'Identificación vacía.']);
            exit;
        }
        try {
            $svc    = new \App\Services\SriIdentificationService();
            $result = $svc->consultar($identificacion, (int) $_SESSION['id_empresa']);
            echo json_encode($result);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Typeahead de compras ya registradas, para autocompletar un tercero
     * reembolsado: trae el documento del proveedor + el detalle de impuestos
     * agregado, listo para guardarse como reembolsoDetalle.
     */
    public function buscarComprasAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['q'] ?? '');

        $compras = $this->repository->buscarComprasParaTercero($idEmpresa, $buscar);
        $rows = array_map(function ($c) {
            $c['impuestos'] = $this->repository->getImpuestosAgregadosCompra((int) $c['id']);
            return $c;
        }, $compras);

        echo json_encode(['ok' => true, 'data' => $rows]);
        exit;
    }

    /**
     * Vista previa del asiento contable "cuenta puente" de una factura de
     * reembolso (pestaña del modal).
     */
    public function getAsientoSugeridoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $id        = (int) ($_GET['id'] ?? $_GET['id_factura_reembolso'] ?? 0);

        try {
            if ($id <= 0) {
                echo json_encode(['ok' => true, 'detalles' => [], 'es_guardado' => false]);
                exit;
            }

            $cab = $this->repository->getPorId($id);
            if (!$cab || (int) ($cab['id_empresa'] ?? 0) !== $idEmpresa) {
                echo json_encode(['ok' => true, 'detalles' => []]);
                exit;
            }

            $idAsiento = (int) ($cab['id_asiento_contable'] ?? 0);
            if ($idAsiento > 0) {
                $asientoRepo    = new \App\repositories\modulos\AsientoContableRepository();
                $asientoRules   = new \App\Rules\modulos\AsientoContableRules();
                $asientoService = new \App\Services\modulos\AsientoContableService($asientoRepo, $asientoRules, new \App\Services\LogSistemaService());
                $cabA = $asientoService->getDetalleAsiento($idAsiento, $idEmpresa);

                $detalles = [];
                foreach (($cabA['detalles'] ?? []) as $det) {
                    $detalles[] = [
                        'id_cuenta_contable'   => (int) $det['id_cuenta_contable'],
                        'cuenta_codigo'        => $det['codigo_cuenta'] ?? $det['cuenta_codigo'] ?? '',
                        'cuenta_nombre'        => $det['nombre_cuenta'] ?? $det['cuenta_nombre'] ?? '',
                        'debe'                 => (float) $det['debe'],
                        'haber'                => (float) $det['haber'],
                        'referencia_detalle'   => $det['referencia_detalle'] ?? '',
                        'documento_referencia' => $det['documento_referencia'] ?? '',
                    ];
                }
                echo json_encode(['ok' => true, 'detalles' => $detalles, 'es_guardado' => true]);
                exit;
            }

            // Vista previa del asiento (documento aún no autorizado, sin guardar todavía):
            // si no cuadra (p. ej. los terceros no coinciden con las líneas de "Gasto" del
            // detalle), no se muestra como error — el asiento real se genera recién al
            // autorizar en el SRI (con reintentos silenciosos, ver SriEnvioService) y
            // puede corregirse/generarse manualmente después desde Contabilidad una vez
            // revisado el balance.
            try {
                $builder = new \App\Services\modulos\AsientoBuilderService();
                $detalles = $builder->generarAsientoFacturaReembolso($idEmpresa, $id);
                echo json_encode(['ok' => true, 'detalles' => $detalles, 'es_guardado' => false]);
            } catch (\Throwable $ePreview) {
                echo json_encode([
                    'ok' => true, 'detalles' => [], 'es_guardado' => false, 'pendiente' => true,
                    'mensaje' => 'El asiento se generará al autorizar el documento en el SRI.',
                ]);
            }
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function autorizarSRIAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $id        = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            $envioService = new \App\Services\Sri\SriEnvioService();
            $resultado    = $envioService->enviarFacturaReembolso($id, $idEmpresa, $idUsuario);

            echo json_encode([
                'ok'                  => $resultado['ok'],
                'estado'              => $resultado['estado'],
                'mensaje'             => $resultado['mensaje'],
                'numero_autorizacion' => $resultado['numero_autorizacion'] ?? '',
                'fecha_autorizacion'  => $resultado['fecha_autorizacion']  ?? '',
                'errores'             => $resultado['errores'] ?? [],
            ]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function getHistorialSriAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $logs = (new \App\models\SriEnvioLog())->getPorComprobante('factura_reembolso', $id, $idEmpresa);
        echo json_encode(['ok' => true, 'data' => $logs]);
        exit;
    }

    /**
     * Arma el arreglo de empresa enriquecido con la configuración del
     * establecimiento (igual patrón que Nota de Débito) y devuelve también
     * la dirección del establecimiento para el XML/PDF.
     *
     * @return array{0: array, 1: ?string} [empresa, dirEstablecimiento]
     */
    private function construirEmpresaComprobante(int $idEmpresa, array $fr): array
    {
        $empresaModel = new Empresa();
        $empresa = $empresaModel->getPorId($idEmpresa) ?? [];
        $dirEstablecimiento = null;

        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);

        $est = null;
        if (!empty($fr['id_establecimiento'])) {
            foreach ($establecimientos as $e) {
                if ((int) $e['id'] === (int) $fr['id_establecimiento']) { $est = $e; break; }
            }
        }
        if (!$est && !empty($establecimientos)) $est = $establecimientos[0];

        if ($est) {
            $dirEstablecimiento = $est['direccion'] ?? null;
            if (!empty($est['logo_ruta']))           $empresa['logo_ruta'] = $est['logo_ruta'];
            if (!empty($est['direccion']))           $empresa['direccion_establecimiento'] = $est['direccion'];
            if (!empty($est['leyenda_pdf_titulo']))  $empresa['leyenda_pdf_titulo'] = $est['leyenda_pdf_titulo'];
            if (!empty($est['leyenda_pdf_mensaje'])) $empresa['leyenda_pdf_mensaje'] = $est['leyenda_pdf_mensaje'];

            try {
                $estRepo   = new \App\repositories\modulos\EmpresaRepository();
                $estConfig = $estRepo->getEstablecimientoConfig((int) $est['id']);
                if ($estConfig) {
                    $estConfig['direccion_matriz']          = $empresa['direccion'] ?? '';
                    $estConfig['direccion_establecimiento'] = $est['direccion'] ?? '';
                    if (!empty($est['logo_ruta']))           $estConfig['logo_ruta'] = $est['logo_ruta'];
                    if (!empty($est['leyenda_pdf_titulo']))  $estConfig['leyenda_pdf_titulo'] = $est['leyenda_pdf_titulo'];
                    if (!empty($est['leyenda_pdf_mensaje'])) $estConfig['leyenda_pdf_mensaje'] = $est['leyenda_pdf_mensaje'];
                    $empresa = array_merge($empresa, $estConfig);
                }
            } catch (\Throwable $e) {}
        }

        return [$empresa, $dirEstablecimiento];
    }

    /** Detalles+impuestos, terceros+impuestos, pagos e info adicional de una factura de reembolso. */
    private function cargarDatosCompletos(int $id): array
    {
        $detalles = $this->repository->getDetalles($id);
        // Impuestos EN LOTE: una sola consulta para todas las líneas, en vez
        // de una por línea. Con la base en un servidor remoto, un documento
        // largo pagaba un viaje de red por cada ítem.
        $impuestosPorDetalle = $this->repository->getImpuestosPorDetalles(array_column($detalles, 'id'));
        foreach ($detalles as &$d) {
            $d['impuestos'] = $impuestosPorDetalle[(int) $d['id']] ?? [];
        }
        unset($d);

        $terceros = $this->repository->getTerceros($id);
        foreach ($terceros as &$t) {
            $t['impuestos'] = $this->repository->getImpuestosTercero((int) $t['id']);
        }
        unset($t);

        return [
            $detalles,
            $terceros,
            $this->repository->getPagos($id),
            $this->repository->getInfoAdicional($id),
        ];
    }

    public function exportPdfDoc(): void
    {
        $this->requireLeer();
        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        try {
            $fr = $this->repository->getPorId($id);
            if (!$fr || (int) $fr['id_empresa'] !== $idEmpresa) {
                die('Factura de reembolso no encontrada');
            }

            [$detalles, $terceros, $pagos, $infoAdicional] = $this->cargarDatosCompletos($id);
            [$empresa] = $this->construirEmpresaComprobante($idEmpresa, $fr);

            $pdfService = new \App\Services\modulos\FacturaReembolsoPdfService();
            $pdfService->generar($fr, $detalles, $terceros, $pagos, $infoAdicional, $empresa, 'D');
        } catch (\Throwable $e) {
            die('Error al generar PDF: ' . $e->getMessage());
        }
        exit;
    }

    public function exportExcelDoc(): void
    {
        $this->requireLeer();
        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$id) { http_response_code(400); echo 'ID requerido'; exit; }

        try {
            $fr = $this->repository->getPorId($id);
            if (!$fr || (int) ($fr['id_empresa'] ?? 0) !== $idEmpresa) {
                http_response_code(404); echo 'Factura de reembolso no encontrada'; exit;
            }

            [$detalles, $terceros, $pagos] = $this->cargarDatosCompletos($id);
            [$empresa] = $this->construirEmpresaComprobante($idEmpresa, $fr);

            $numero = ($fr['establecimiento'] ?? '001') . '-' . ($fr['punto_emision'] ?? '001') . '-'
                    . str_pad((string) ($fr['secuencial'] ?? ''), 9, '0', STR_PAD_LEFT);

            require_once MVC_ROOT . '/vendor/autoload.php';

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Factura Reembolso');

            $sheet->setCellValue('A1', strtoupper((string) ($empresa['nombre'] ?? '')));
            $sheet->mergeCells('A1:F1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);

            $sheet->setCellValue('A2', 'FACTURA DE REEMBOLSO N.° ' . $numero);
            $sheet->mergeCells('A2:F2');
            $sheet->getStyle('A2')->getFont()->setBold(true);

            $fecha = !empty($fr['fecha_emision']) ? date('d-m-Y', strtotime((string) $fr['fecha_emision'])) : '';
            $sheet->setCellValue('A3', 'Fecha: ' . $fecha);
            $sheet->setCellValue('C3', 'Cliente: ' . (string) ($fr['cliente_nombre'] ?? ''));
            $sheet->setCellValue('A4', 'Identificación: ' . (string) ($fr['cliente_ruc'] ?? ''));
            $sheet->setCellValue('C4', 'Estado: ' . ucfirst((string) ($fr['estado'] ?? '')));

            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '3C465A']],
            ];

            // ── Detalle ──────────────────────────────────────────────────────
            $headerRow = 6;
            $headers = ['Descripción', 'Cantidad', 'P. Unitario', 'Descuento', 'Subtotal', 'Reembolso'];
            $col = 'A';
            foreach ($headers as $h) {
                $sheet->setCellValue($col . $headerRow, $h);
                $col++;
            }
            $sheet->getStyle('A' . $headerRow . ':F' . $headerRow)->applyFromArray($headerStyle);

            $row = $headerRow + 1;
            $subtotMap = []; $ivaMap = []; $tarifaMap = [];
            foreach ($detalles as $d) {
                $sheet->setCellValueExplicit('A' . $row, (string) ($d['descripcion'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValue('B' . $row, (float) ($d['cantidad'] ?? 0));
                $sheet->setCellValue('C' . $row, (float) ($d['precio_unitario'] ?? 0));
                $sheet->setCellValue('D' . $row, (float) ($d['descuento'] ?? 0));
                $sheet->setCellValue('E' . $row, (float) ($d['precio_total_sin_impuesto'] ?? 0));
                $sheet->setCellValueExplicit('F' . $row, !empty($d['es_reembolso']) ? 'Sí' : 'No', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $row++;

                foreach ($d['impuestos'] ?? [] as $imp) {
                    if ((string) ($imp['codigo_impuesto'] ?? '') !== '2') continue;
                    $codPct = (string) ($imp['codigo_porcentaje'] ?? '0');
                    $subtotMap[$codPct] = ($subtotMap[$codPct] ?? 0.0) + (float) ($imp['base_imponible'] ?? 0);
                    $ivaMap[$codPct]    = ($ivaMap[$codPct] ?? 0.0) + (float) ($imp['valor'] ?? 0);
                    $tarifaMap[$codPct] = (float) ($imp['tarifa'] ?? 0);
                }
            }
            if ($row > $headerRow + 1) {
                $sheet->getStyle('B' . ($headerRow + 1) . ':E' . ($row - 1))->getNumberFormat()->setFormatCode('#,##0.00');
            }

            // ── Totales ──────────────────────────────────────────────────────
            $subtotal = (float) ($fr['total_sin_impuestos'] ?? 0);
            $descuentoTotal = (float) ($fr['total_descuento'] ?? 0);
            $propina = (float) ($fr['propina'] ?? 0);
            $total = (float) ($fr['importe_total'] ?? 0);
            $totalIva = array_sum($ivaMap);

            $row += 1;
            $totales = [
                'Subtotal sin impuestos' => $subtotal,
                'Descuento' => $descuentoTotal,
            ];
            ksort($ivaMap);
            foreach ($ivaMap as $codPct => $ivaVal) {
                $tarPct = $tarifaMap[$codPct] ?? 0.0;
                $tarLabel = $tarPct == (int) $tarPct ? (string) (int) $tarPct : number_format($tarPct, 2);
                $totales["IVA {$tarLabel}%"] = $ivaVal;
            }
            if ($propina > 0) $totales['Propina'] = $propina;
            $totales['TOTAL'] = $total;

            foreach ($totales as $label => $valor) {
                $sheet->setCellValue('D' . $row, $label);
                $sheet->getStyle('D' . $row)->getFont()->setBold(true);
                $sheet->setCellValue('E' . $row, $valor);
                $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                $row++;
            }

            // ── Terceros reembolsados ────────────────────────────────────────
            if (!empty($terceros)) {
                $row += 1;
                $sheet->setCellValue('A' . $row, 'Terceros reembolsados');
                $sheet->getStyle('A' . $row)->getFont()->setBold(true);
                $row++;

                $tHeaderRow = $row;
                $tHeaders = ['Fecha', 'Identificación', 'Razón social', 'Documento', 'Base imponible', 'Impuesto'];
                $col = 'A';
                foreach ($tHeaders as $h) {
                    $sheet->setCellValue($col . $tHeaderRow, $h);
                    $col++;
                }
                $sheet->getStyle('A' . $tHeaderRow . ':F' . $tHeaderRow)->applyFromArray($headerStyle);
                $row++;

                $totalReembBase = 0.0; $totalReembIva = 0.0;
                foreach ($terceros as $t) {
                    $fechaDoc = '';
                    if (!empty($t['fecha_emision_doc_reembolso'])) {
                        $ts = strtotime((string) $t['fecha_emision_doc_reembolso']);
                        $fechaDoc = $ts ? date('d-m-Y', $ts) : (string) $t['fecha_emision_doc_reembolso'];
                    }
                    $doc = ($t['estab_doc_reembolso'] ?? '') . '-' . ($t['pto_emi_doc_reembolso'] ?? '') . '-' . ($t['secuencial_doc_reembolso'] ?? '');
                    $base = (float) ($t['base_imponible_total'] ?? 0);
                    $imp  = (float) ($t['impuesto_total'] ?? 0);
                    $totalReembBase += $base;
                    $totalReembIva  += $imp;

                    $sheet->setCellValueExplicit('A' . $row, $fechaDoc, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit('B' . $row, (string) ($t['identificacion_proveedor_reembolso'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit('C' . $row, (string) ($t['razon_social_proveedor_reembolso'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit('D' . $row, (string) $doc, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('E' . $row, $base);
                    $sheet->setCellValue('F' . $row, $imp);
                    $sheet->getStyle('E' . $row . ':F' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                    $row++;
                }

                $row += 1;
                $sheet->setCellValue('D' . $row, 'BASE REEMBOLSADA');
                $sheet->getStyle('D' . $row)->getFont()->setBold(true);
                $sheet->setCellValue('E' . $row, $totalReembBase);
                $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                $row++;
                $sheet->setCellValue('D' . $row, 'IVA REEMBOLSADO');
                $sheet->getStyle('D' . $row)->getFont()->setBold(true);
                $sheet->setCellValue('E' . $row, $totalReembIva);
                $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                $row++;
            }

            // ── Forma de pago ────────────────────────────────────────────────
            if (!empty($pagos)) {
                $row += 1;
                $sheet->setCellValue('A' . $row, 'Forma de pago');
                $sheet->getStyle('A' . $row)->getFont()->setBold(true);
                $row++;
                foreach ($pagos as $p) {
                    $sheet->setCellValue('A' . $row, (string) ($p['nombre_forma_pago'] ?? $p['forma_pago'] ?? ''));
                    $sheet->setCellValue('C' . $row, (float) ($p['total'] ?? 0));
                    $sheet->getStyle('C' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                    $row++;
                }
            }

            foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $c) {
                $sheet->getColumnDimension($c)->setAutoSize(true);
            }

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $nombre = 'FacturaReembolso_' . $numero . '.xlsx';

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $nombre . '"');
            header('Cache-Control: max-age=0');
            $writer->save('php://output');
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            http_response_code(500); echo 'Error al generar Excel: ' . $e->getMessage();
        }
        exit;
    }

    public function exportXmlDoc(): void
    {
        $this->requireLeer();
        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        try {
            $fr = $this->repository->getPorId($id);
            if (!$fr || (int) $fr['id_empresa'] !== $idEmpresa) {
                die('Factura de reembolso no encontrada');
            }

            $numero = ($fr['establecimiento'] ?? '001') . '-' . ($fr['punto_emision'] ?? '001') . '-' . str_pad((string) $fr['secuencial'], 9, '0', STR_PAD_LEFT);

            if (!empty($fr['detalle_xml'])) {
                header('Content-Type: application/xml; charset=UTF-8');
                header('Content-Disposition: attachment; filename="factura_reembolso_' . $numero . '.xml"');
                echo $fr['detalle_xml'];
                exit;
            }

            [$detalles, $terceros, $pagos, $infoAdicional] = $this->cargarDatosCompletos($id);
            [$empresa, $dirEstablecimiento] = $this->construirEmpresaComprobante($idEmpresa, $fr);

            $xmlService = new \App\Services\Xml\XmlFacturaReembolsoService();
            $xmlString  = $xmlService->generar($fr, $detalles, $terceros, $pagos, $infoAdicional, $empresa, $dirEstablecimiento);

            try { $this->repository->updateDetalleXml($id, $xmlString); } catch (\Throwable $e) {}

            header('Content-Type: application/xml; charset=UTF-8');
            header('Content-Disposition: attachment; filename="factura_reembolso_' . $numero . '.xml"');
            echo $xmlString;
        } catch (\Throwable $e) {
            die('Error al generar XML: ' . $e->getMessage());
        }
        exit;
    }

    public function enviarCorreoAjax(): void
    {
        ob_start();
        $this->requireLeer();
        header('Content-Type: application/json');

        $id        = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$id) {
            ob_end_clean();
            echo json_encode(['ok' => false, 'mensaje' => 'ID requerido.']);
            exit;
        }

        try {
            $fr = $this->repository->getPorId($id);
            if (!$fr || (int) ($fr['id_empresa'] ?? 0) !== $idEmpresa) {
                ob_end_clean();
                echo json_encode(['ok' => false, 'mensaje' => 'Factura de reembolso no encontrada.']);
                exit;
            }
            if (($fr['estado'] ?? '') !== 'autorizado') {
                ob_end_clean();
                echo json_encode(['ok' => false, 'mensaje' => 'La factura de reembolso debe estar autorizada para enviar el correo.']);
                exit;
            }

            [$detalles, $terceros, $pagos, $infoAdicional] = $this->cargarDatosCompletos($id);
            [$empresa, $dirEstablecimiento] = $this->construirEmpresaComprobante($idEmpresa, $fr);

            $pdfService = new \App\Services\modulos\FacturaReembolsoPdfService();
            $pdfString  = $pdfService->generarBytes($fr, $detalles, $terceros, $pagos, $infoAdicional, $empresa);

            $xmlString = $fr['detalle_xml'] ?? '';
            if (empty($xmlString)) {
                $xmlService = new \App\Services\Xml\XmlFacturaReembolsoService();
                $xmlString  = $xmlService->generar($fr, $detalles, $terceros, $pagos, $infoAdicional, $empresa, $dirEstablecimiento);
                try { $this->repository->updateDetalleXml($id, $xmlString); } catch (\Throwable $e) {}
            }

            $numAut         = $fr['numero_autorizacion'] ?? $fr['clave_acceso'] ?? '';
            $correosDestino = trim($_POST['correos'] ?? '');

            $emailSvc = new \App\Services\EnvioDocumentosSRIService();
            $enviado  = $emailSvc->enviarSiAplica($idEmpresa, 'factura_reembolso', $fr, $xmlString, $pdfString, $numAut, true, $correosDestino);

            ob_end_clean();
            if ($enviado) {
                $db = \App\core\Database::getConnection();
                $db->prepare("UPDATE factura_reembolso_cabecera SET estado_correo = 'enviado' WHERE id = ?")->execute([$id]);
                echo json_encode(['ok' => true, 'mensaje' => 'Correo enviado correctamente.']);
            } else {
                echo json_encode(['ok' => false, 'mensaje' => 'No se pudo enviar el correo. Verifica la configuración o el correo del destinatario.']);
            }
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            ob_end_clean();
            echo json_encode(['ok' => false, 'mensaje' => 'Error al enviar correo: ' . $e->getMessage()]);
        }
        exit;
    }

    public function descargarXmlOriginalAjax(): void
    {
        $this->requireLeer();

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$id) {
            http_response_code(400);
            echo 'ID requerido';
            exit;
        }

        $fr = $this->repository->getPorId($id);
        if (!$fr || (int) ($fr['id_empresa'] ?? 0) !== $idEmpresa) {
            http_response_code(404);
            echo 'Factura de reembolso no encontrada';
            exit;
        }

        $numero   = ($fr['establecimiento'] ?? '001') . '-'
                  . ($fr['punto_emision']   ?? '001') . '-'
                  . str_pad((string) ($fr['secuencial'] ?? ''), 9, '0', STR_PAD_LEFT);
        $filename = 'factura_reembolso_' . $numero . '.xml';

        if (!empty($fr['detalle_xml'])) {
            header('Content-Type: application/xml; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            echo $fr['detalle_xml'];
        } else {
            http_response_code(404);
            echo 'Esta factura de reembolso todavía no tiene un XML generado.';
        }
        exit;
    }

    public function countBorradoresAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $result = $this->repository->getListado($idEmpresa, 'estado:borrador', 1, 1, 'fecha_emision', 'DESC', $idUsuarioFiltro);
        echo json_encode(['ok' => true, 'count' => $result['total']]);
        exit;
    }
}
