<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\ActivoFijoCategoriaRepository;
use App\repositories\modulos\ActivoFijoDepreciacionRepository;
use App\repositories\modulos\ActivoFijoLoteRepository;
use App\repositories\modulos\ActivoFijoRepository;
use App\repositories\modulos\ComprasRepository;
use App\Rules\modulos\ActivoFijoDepreciacionRules;
use App\Rules\modulos\ActivoFijoRules;
use App\Services\LogSistemaService;
use App\Services\modulos\ActivoFijoDepreciacionService;
use App\Services\modulos\ActivoFijoService;

class ActivosFijosController extends BaseModuloController
{
    private ActivoFijoService $service;
    private ActivoFijoRepository $repository;
    private ActivoFijoDepreciacionService $depreciacionService;
    private ComprasRepository $comprasRepository;

    protected function getRutaModulo(): string
    {
        return 'modulos/activos-fijos';
    }

    public function __construct()
    {
        parent::__construct();
        $this->repository = new ActivoFijoRepository();
        $categoriaRepository = new ActivoFijoCategoriaRepository();
        $loteRepository = new ActivoFijoLoteRepository();
        $depreciacionRepository = new ActivoFijoDepreciacionRepository();
        $this->comprasRepository = new ComprasRepository();
        $logService = new LogSistemaService();

        $this->service = new ActivoFijoService(
            $this->repository,
            $categoriaRepository,
            $loteRepository,
            $this->comprasRepository,
            new ActivoFijoRules($this->repository, $categoriaRepository),
            $logService
        );

        $this->depreciacionService = new ActivoFijoDepreciacionService(
            $this->repository,
            $loteRepository,
            $depreciacionRepository,
            new ActivoFijoDepreciacionRules(),
            $logService
        );
    }

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());
        $buscar   = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_adquisicion');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage  = 20;

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $totalPages = (int) ceil($result['total'] / $perPage);

        $this->viewWithLayout('layouts.main', 'modulos/activos_fijos/index', [
            'titulo'      => 'Activos Fijos',
            'perm'        => $perm,
            'rows'        => $result['rows'],
            'total'       => $result['total'],
            'page'        => $page,
            'totalPages'  => $totalPages,
            'perPage'     => $perPage,
            'from'        => $result['total'] > 0 ? (($page - 1) * $perPage) + 1 : 0,
            'to'          => $result['total'] > 0 ? min($page * $perPage, $result['total']) : 0,
            'buscar'      => $buscar,
            'ordenCol'    => $ordenCol,
            'ordenDir'    => $ordenDir,
            'vistaConfig' => $prefsVista,
            'rutaModulo'  => $this->getRutaModulo(),
        ]);
    }

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());
        $buscar     = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page       = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol   = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_adquisicion');
        $ordenDir   = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage    = 20;

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $result     = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows       = $result['rows'];
        $total      = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
        $from       = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to         = $total > 0 ? min($page * $perPage, $total) : 0;

        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="8" class="text-center py-5 text-muted"><i class="bi bi-building fs-3 d-block mb-2"></i>No se encontraron activos fijos.</td></tr>';
        } else {
            foreach ($rows as $r) {
                $fecha = !empty($r['fecha_adquisicion']) ? date('d-m-Y', strtotime($r['fecha_adquisicion'])) : '—';
                $estado = $r['estado'] ?? 'activo';
                $estCls = $estado === 'depreciado_total' ? 'bg-secondary bg-opacity-10 text-secondary border-secondary' : 'bg-success bg-opacity-10 text-success border-success';
                $estTxt = $estado === 'depreciado_total' ? 'Depreciado total' : 'Activo';
                $origenBadge = $r['origen'] === 'compra'
                    ? '<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25"><i class="bi bi-receipt"></i> Compra</span>'
                    : '<span class="badge bg-light text-dark border"><i class="bi bi-pencil"></i> Manual</span>';

                echo '<tr role="button" onclick="AF_abrirModal(' . (int) $r['id'] . ')">
                        <td class="ps-3" data-col="codigo"><code>' . htmlspecialchars((string) ($r['codigo'] ?? '')) . '</code></td>
                        <td data-col="nombre">' . htmlspecialchars($r['nombre']) . '</td>
                        <td data-col="categoria_nombre">' . htmlspecialchars($r['categoria_nombre'] ?? '') . '</td>
                        <td data-col="origen" class="text-center">' . $origenBadge . '</td>
                        <td data-col="fecha_adquisicion">' . $fecha . '</td>
                        <td class="text-end" data-col="valor_adquisicion">$' . number_format((float) $r['valor_adquisicion'], 2) . '</td>
                        <td class="text-end" data-col="valor_en_libros">$' . number_format((float) $r['valor_en_libros'], 2) . '</td>
                        <td class="text-center pe-3" data-col="estado"><span class="badge ' . $estCls . ' border border-opacity-25">' . $estTxt . '</span></td>
                      </tr>';
            }
        }
        $rowsHtml = ob_get_clean();

        $urlBase = rtrim(BASE_URL, '/') . '/' . $this->getRutaModulo();

        echo json_encode([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'total'      => $total,
            'totalPages' => $totalPages,
            'page'       => $page,
            'info'       => "$from-$to/$total",
            'pdf_url'    => $urlBase . '/export-pdf?b=' . urlencode($buscar) . "&sort=$ordenCol&dir=$ordenDir",
            'excel_url'  => $urlBase . '/export-excel?b=' . urlencode($buscar) . "&sort=$ordenCol&dir=$ordenDir",
        ]);
        exit;
    }

    /** Filas del listado con el mismo filtro/orden de la vista, sin paginar (para exportar). */
    private function filasParaExport(): array
    {
        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());
        $buscar     = trim($_GET['b'] ?? '');
        $ordenCol   = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_adquisicion');
        $ordenDir   = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        // perPage = 0 => sin LIMIT (todas las filas del filtro actual).
        $data = $this->service->getListado($idEmpresa, $buscar, 1, 0, $ordenCol, $ordenDir, $idUsuarioFiltro);
        return $data['rows'] ?? [];
    }

    /** Exporta el listado (con el filtro/orden actual) a PDF. */
    public function exportPdf(): void
    {
        $this->requireLeer();
        $rows = $this->filasParaExport();

        try {
            $empresa       = (new \App\models\Empresa())->getPorId((int) $_SESSION['id_empresa']);
            $nombreEmpresa = $empresa['nombre'] ?? '';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            ob_start();
            ?>
            <style>
                table { width:100%; border-collapse:collapse; font-family:Arial,sans-serif; font-size:8pt; }
                th { background:#f2f2f2; border:1px solid #ccc; padding:3px; text-align:left; }
                td { border:1px solid #ccc; padding:3px; }
                .r { text-align:right; }
                h2 { font-family:Arial,sans-serif; font-size:12pt; margin:0 0 2px 0; }
                .sub { font-family:Arial,sans-serif; font-size:8pt; color:#555; margin-bottom:6px; }
            </style>
            <page backtop="8mm" backbottom="8mm" backleft="6mm" backright="6mm">
                <h2><?= htmlspecialchars($nombreEmpresa) ?></h2>
                <div class="sub">Listado de Activos Fijos &mdash; <?= date('d-m-Y H:i:s') ?></div>
                <table>
                    <thead>
                        <tr>
                            <th style="width:10%">Código</th>
                            <th style="width:24%">Nombre</th>
                            <th style="width:16%">Categoría</th>
                            <th style="width:8%">Origen</th>
                            <th style="width:10%">Fecha Adquisición</th>
                            <th style="width:11%" class="r">Valor Adquisición</th>
                            <th style="width:11%" class="r">Valor en Libros</th>
                            <th style="width:10%">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars((string) ($r['codigo'] ?? '')) ?></td>
                            <td><?= htmlspecialchars($r['nombre']) ?></td>
                            <td><?= htmlspecialchars($r['categoria_nombre'] ?? '') ?></td>
                            <td><?= $r['origen'] === 'compra' ? 'Compra' : 'Manual' ?></td>
                            <td><?= !empty($r['fecha_adquisicion']) ? date('d-m-Y', strtotime($r['fecha_adquisicion'])) : '-' ?></td>
                            <td class="r"><?= number_format((float) $r['valor_adquisicion'], 2) ?></td>
                            <td class="r"><?= number_format((float) $r['valor_en_libros'], 2) ?></td>
                            <td><?= ($r['estado'] ?? 'activo') === 'depreciado_total' ? 'Depreciado total' : 'Activo' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </page>
            <?php
            $content = ob_get_clean();

            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $html2pdf->writeHTML($content);
            $html2pdf->output('Activos_fijos_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            header('Content-Type: text/html');
            echo 'Error al generar PDF: ' . $e->getMessage();
            exit;
        }
    }

    /** Exporta el listado (con el filtro/orden actual) a Excel. */
    public function exportExcel(): void
    {
        $this->requireLeer();
        $rows = $this->filasParaExport();

        try {
            $empresa       = (new \App\models\Empresa())->getPorId((int) $_SESSION['id_empresa']);
            $nombreEmpresa = $empresa['nombre'] ?? '';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            $headers = ['Código', 'Nombre', 'Categoría', 'Origen', 'Fecha Adquisición',
                        'Valor Adquisición', 'Valor Residual', 'Depreciación Acumulada',
                        'Valor en Libros', 'Estado'];

            $exportData = [];
            foreach ($rows as $r) {
                $exportData[] = [
                    (string) ($r['codigo'] ?? ''),
                    (string) $r['nombre'],
                    (string) ($r['categoria_nombre'] ?? ''),
                    $r['origen'] === 'compra' ? 'Compra' : 'Manual',
                    !empty($r['fecha_adquisicion']) ? date('d-m-Y', strtotime($r['fecha_adquisicion'])) : '-',
                    number_format((float) $r['valor_adquisicion'], 2, '.', ''),
                    number_format((float) $r['valor_residual'], 2, '.', ''),
                    number_format((float) $r['depreciacion_acumulada'], 2, '.', ''),
                    number_format((float) $r['valor_en_libros'], 2, '.', ''),
                    ($r['estado'] ?? 'activo') === 'depreciado_total' ? 'Depreciado total' : 'Activo',
                ];
            }

            $reportService = new \App\Services\ReportService();
            $reportService->exportToExcel('Activos_Fijos', $headers, $exportData, 'Activos Fijos', $nombreEmpresa);
            exit;
        } catch (\Throwable $e) {
            header('Content-Type: text/html');
            echo 'Error al generar Excel: ' . $e->getMessage();
            exit;
        }
    }

    public function getActivoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $activo = $this->service->getPorId($id, $idEmpresa);
        if (!$activo) {
            echo json_encode(['ok' => false, 'mensaje' => 'Activo fijo no encontrado.']);
            exit;
        }
        $activo['historial'] = $this->service->getHistorialDepreciaciones($id);

        echo json_encode(['ok' => true, 'data' => $activo]);
        exit;
    }

    public function guardarAjax(): void
    {
        if (!empty($_POST['id'])) {
            $this->requireActualizar();
        } else {
            $this->requireCrear();
        }
        header('Content-Type: application/json');

        try {
            $data = $_POST;
            $data['id_empresa'] = (int) $_SESSION['id_empresa'];
            $data['id_usuario'] = (int) $_SESSION['id_usuario'];

            $idExistente = !empty($data['id']) ? (int) $data['id'] : 0;
            $id = $idExistente > 0 ? $this->service->actualizar($idExistente, $data) : $this->service->crear($data);

            echo json_encode(['ok' => true, 'mensaje' => 'Activo fijo guardado correctamente.', 'id' => $id]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function eliminarAjax(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        try {
            $id = (int) ($_POST['id'] ?? 0);
            $this->service->eliminar($id, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']);
            echo json_encode(['ok' => true, 'mensaje' => 'Activo fijo eliminado correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    // ─── Selector "Desde factura de compra" ────────────────────────────────

    public function buscarComprasAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar = trim($_GET['b'] ?? '');
        $result = $this->comprasRepository->getListado($idEmpresa, $buscar, 1, 15, 'fecha_emision', 'DESC');

        $data = array_map(static function (array $c): array {
            return [
                'id'              => (int) $c['id'],
                'numero'          => trim(($c['establecimiento_prov'] ?? '') . '-' . ($c['punto_emision_prov'] ?? '') . '-' . ($c['secuencial_prov'] ?? ''), '-'),
                'proveedor_nombre' => $c['proveedor_nombre'] ?? '',
                'fecha_emision'   => $c['fecha_emision'] ?? '',
                'importe_total'   => (float) ($c['importe_total'] ?? 0),
            ];
        }, $result['rows']);

        echo json_encode(['ok' => true, 'data' => $data]);
        exit;
    }

    public function getDetalleCompraAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idCompra = (int) ($_GET['id_compra'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $compra = $this->comprasRepository->getPorId($idCompra, $idEmpresa);
        if (!$compra) {
            echo json_encode(['ok' => false, 'mensaje' => 'Factura de compra no encontrada.']);
            exit;
        }

        $detalles = $this->comprasRepository->getDetalles($idCompra);
        $lineas = [];
        foreach ($detalles as $d) {
            if ($this->repository->compraDetalleVinculado((int) $d['id'])) {
                continue; // ya convertida en otro activo fijo
            }
            $lineas[] = [
                'id_compra_detalle' => (int) $d['id'],
                'descripcion'       => $d['descripcion'],
                'cantidad'          => (float) $d['cantidad'],
                'precio_unitario'   => (float) $d['precio_unitario'],
                'precio_total'      => (float) $d['precio_total_sin_impuesto'],
            ];
        }

        echo json_encode([
            'ok'   => true,
            'data' => [
                'id_compra'        => (int) $compra['id'],
                'proveedor_nombre' => $compra['proveedor_nombre'] ?? '',
                'fecha_emision'    => $compra['fecha_emision'] ?? '',
                'lineas'           => $lineas,
            ],
        ]);
        exit;
    }

    // ─── Depreciación mensual en lote ───────────────────────────────────────

    public function generarDepreciacionAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $anio = (int) ($_POST['periodo_anio'] ?? 0);
            $mes  = (int) ($_POST['periodo_mes'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];

            $res = $this->depreciacionService->generarLote($idEmpresa, $anio, $mes, $idUsuario);
            echo json_encode(['ok' => true, 'mensaje' => 'Depreciación generada correctamente.', 'data' => $res]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Períodos con depreciación pendiente de generar (solo lectura, sin efectos).
     * Lo consume el aviso informativo de Estados Financieros.
     */
    public function periodosPendientesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $anio = (int) ($_GET['anio'] ?? date('Y'));
            $mes = !empty($_GET['mes']) ? (int) $_GET['mes'] : null;

            $data = $this->depreciacionService->getPeriodosPendientes($idEmpresa, $anio, $mes);
            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function lotesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        echo json_encode(['ok' => true, 'data' => $this->depreciacionService->getListadoLotes($idEmpresa)]);
        exit;
    }

    public function getLoteAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $lote = $this->depreciacionService->getLote($id, $idEmpresa);

        echo json_encode($lote ? ['ok' => true, 'data' => $lote] : ['ok' => false, 'mensaje' => 'Lote no encontrado.']);
        exit;
    }
}
