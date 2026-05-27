<?php
declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\UnidadesMedidaRepository;
use App\Rules\modulos\UnidadesMedidaRules;
use App\Services\LogSistemaService;
use App\Services\modulos\UnidadesMedidaService;

class UnidadesMedidaController extends BaseModuloController
{
    private UnidadesMedidaService $service;
    private const RUTA_MODULO = 'modulos/unidades-medida';
    private const PER_PAGE    = 20;

    public function __construct()
    {
        parent::__construct();
        $repo       = new UnidadesMedidaRepository();
        $rules      = new UnidadesMedidaRules();
        $logService = new LogSistemaService();
        $this->service = new UnidadesMedidaService($repo, $rules, $logService);
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    // ─── INDEX ──────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireLeer();

        $perm      = $this->getPermisos();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);

        $tab = in_array($_GET['tab'] ?? 'tipos', ['tipos', 'unidades'], true)
            ? ($_GET['tab'] ?? 'tipos') : 'tipos';

        // Estado de la pestaña Tipos
        $buscarTipos   = trim($_GET['b_tipos'] ?? '');
        $pageTipos     = max(1, (int) ($_GET['page_tipos'] ?? 1));
        $sortTipos     = trim($_GET['sort_tipos'] ?? $prefsVista['__ordenColTipos__'] ?? 'nombre');
        $dirTipos      = strtoupper(trim($_GET['dir_tipos'] ?? $prefsVista['__ordenDirTipos__'] ?? 'asc'));

        // Estado de la pestaña Unidades
        $buscarUni     = trim($_GET['b_uni'] ?? '');
        $pageUni       = max(1, (int) ($_GET['page_uni'] ?? 1));
        $sortUni       = trim($_GET['sort_uni'] ?? $prefsVista['__ordenColUni__'] ?? 'nombre');
        $dirUni        = strtoupper(trim($_GET['dir_uni'] ?? $prefsVista['__ordenDirUni__'] ?? 'asc'));
        $filtroTipo    = !empty($_GET['f_tipo']) ? (int) $_GET['f_tipo'] : null;

        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        // Cargar tipos
        $resultTipos  = $this->service->getTiposListado($idEmpresa, $buscarTipos, $pageTipos, self::PER_PAGE, $sortTipos, $dirTipos, $idUsuarioFiltro);
        $rowsTipos    = $resultTipos['rows'];
        $totalTipos   = $resultTipos['total'];
        $this->formatFechas($rowsTipos);

        // Cargar unidades
        $resultUni    = $this->service->getUnidadesListado($idEmpresa, $buscarUni, $pageUni, self::PER_PAGE, $sortUni, $dirUni, $filtroTipo, $idUsuarioFiltro);
        $rowsUni      = $resultUni['rows'];
        $totalUni     = $resultUni['total'];
        $this->formatFechas($rowsUni);

        // Tipos activos para selector en modal de unidades y filtro
        $repo = new UnidadesMedidaRepository();
        $tiposSelect = $repo->getTiposActivos($idEmpresa);

        $this->viewWithLayout('layouts.main', 'modulos.unidades_medida.index', [
            'titulo'        => 'Unidades de Medida',
            'perm'          => $perm,
            'rutaModulo'    => self::RUTA_MODULO,
            'vistaConfig'   => $prefsVista,
            'fullWidth'     => true,
            'tab'           => $tab,
            // Tipos
            'rowsTipos'     => $rowsTipos,
            'totalTipos'    => $totalTipos,
            'pageTipos'     => $pageTipos,
            'totalPagesTipos' => self::PER_PAGE > 0 ? (int) ceil($totalTipos / self::PER_PAGE) : 1,
            'buscarTipos'   => $buscarTipos,
            'sortTipos'     => $sortTipos,
            'dirTipos'      => $dirTipos,
            // Unidades
            'rowsUni'       => $rowsUni,
            'totalUni'      => $totalUni,
            'pageUni'       => $pageUni,
            'totalPagesUni' => self::PER_PAGE > 0 ? (int) ceil($totalUni / self::PER_PAGE) : 1,
            'buscarUni'     => $buscarUni,
            'sortUni'       => $sortUni,
            'dirUni'        => $dirUni,
            'filtroTipo'    => $filtroTipo,
            // Compartidos
            'tiposSelect'   => $tiposSelect,
        ]);
    }

    // ─── AJAX SEARCH ────────────────────────────────────────────────────────

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $tab       = in_array($_GET['tab'] ?? 'tipos', ['tipos', 'unidades'], true) ? ($_GET['tab'] ?? 'tipos') : 'tipos';
        $perm      = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $urlBase = BASE_URL . '/' . self::RUTA_MODULO;

        if ($tab === 'tipos') {
            $buscar   = trim($_GET['b'] ?? '');
            $page     = max(1, (int) ($_GET['page'] ?? 1));
            $sort     = trim($_GET['sort'] ?? 'nombre');
            $dir      = strtoupper(trim($_GET['dir'] ?? 'asc'));
            $perPage  = self::PER_PAGE;

            $result   = $this->service->getTiposListado($idEmpresa, $buscar, $page, $perPage, $sort, $dir, $idUsuarioFiltro);
            $rows     = $result['rows'];
            $total    = $result['total'];
            $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
            $from     = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
            $to       = $total > 0 ? min($page * $perPage, $total) : 0;

            ob_start();
            if (empty($rows)) {
                echo '<tr><td colspan="4" class="text-center py-5 text-muted"><i class="bi bi-rulers fs-3 d-block mb-2"></i>No se encontraron tipos de medida.</td></tr>';
            } else {
                foreach ($rows as $r) {
                    $rowData    = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                    $statusBadge = ($r['status'] ?? true)
                        ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Activo</span>'
                        : '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Inactivo</span>';
                    echo '<tr class="tipo-row" role="button" tabindex="0" data-row=\'' . $rowData . '\' onclick="abrirModalTipoEditar(this)">
                            <td class="ps-3" data-col="codigo"><code class="text-secondary">' . htmlspecialchars($r['codigo'] ?? '—') . '</code></td>
                            <td class="fw-medium" data-col="nombre">' . htmlspecialchars($r['nombre'] ?? '') . '</td>
                            <td class="text-center" data-col="total_unidades"><span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">' . (int)($r['total_unidades'] ?? 0) . '</span></td>
                            <td class="text-center pe-3" data-col="status">' . $statusBadge . '</td>
                          </tr>';
                }
            }
            $rowsHtml = ob_get_clean();

            echo json_encode([
                'ok'         => true,
                'rows'       => $rowsHtml,
                'pagination' => $this->buildPagination($page, $totalPages, 'cambiarPaginaTiposAjax'),
                'info'       => "{$from}-{$to}/{$total}",
                'total'      => $total,
                'pdf_url'    => $urlBase . '/export-pdf?tab=tipos&b=' . urlencode($buscar) . "&sort={$sort}&dir={$dir}",
                'excel_url'  => $urlBase . '/export-excel?tab=tipos&b=' . urlencode($buscar) . "&sort={$sort}&dir={$dir}",
            ]);
        } else {
            // tab = unidades
            $buscar    = trim($_GET['b'] ?? '');
            $page      = max(1, (int) ($_GET['page'] ?? 1));
            $sort      = trim($_GET['sort'] ?? 'nombre');
            $dir       = strtoupper(trim($_GET['dir'] ?? 'asc'));
            $filtroTipo = !empty($_GET['f_tipo']) ? (int) $_GET['f_tipo'] : null;
            $perPage   = self::PER_PAGE;

            $result    = $this->service->getUnidadesListado($idEmpresa, $buscar, $page, $perPage, $sort, $dir, $filtroTipo, $idUsuarioFiltro);
            $rows      = $result['rows'];
            $total     = $result['total'];
            $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
            $from      = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
            $to        = $total > 0 ? min($page * $perPage, $total) : 0;

            ob_start();
            if (empty($rows)) {
                echo '<tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-rulers fs-3 d-block mb-2"></i>No se encontraron unidades de medida.</td></tr>';
            } else {
                foreach ($rows as $r) {
                    $rowData    = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                    $statusBadge = ($r['status'] ?? true)
                        ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Activo</span>'
                        : '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Inactivo</span>';
                    $esBaseBadge = ($r['es_base'] === true || $r['es_base'] === 't' || $r['es_base'] === '1' || $r['es_base'] === 1)
                        ? '<span class="badge bg-warning bg-opacity-15 text-warning border border-warning border-opacity-25 ms-1" title="Unidad base del tipo"><i class="bi bi-star-fill" style="font-size:0.6rem;"></i></span>'
                        : '';
                    echo '<tr class="unidad-row" role="button" tabindex="0" data-row=\'' . $rowData . '\' onclick="abrirModalUnidadEditar(this)">
                            <td class="ps-3" data-col="codigo"><code class="text-secondary">' . htmlspecialchars($r['codigo'] ?? '—') . '</code></td>
                            <td class="fw-medium" data-col="nombre">' . htmlspecialchars($r['nombre'] ?? '') . $esBaseBadge . '</td>
                            <td data-col="abreviatura"><span class="badge bg-light text-dark border">' . htmlspecialchars($r['abreviatura'] ?? '') . '</span></td>
                            <td data-col="tipo_nombre" class="text-muted small">' . htmlspecialchars($r['tipo_nombre'] ?? '—') . '</td>
                            <td class="text-end pe-3" data-col="factor_base">' . ($r['es_base'] === true || $r['es_base'] === 't' ? '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">Base</span>' : htmlspecialchars((string)($r['factor_base'] ?? '1'))) . '</td>
                            <td class="text-center pe-3" data-col="status">' . $statusBadge . '</td>
                          </tr>';
                }
            }
            $rowsHtml = ob_get_clean();

            $filtroParam = $filtroTipo ? "&f_tipo={$filtroTipo}" : '';
            echo json_encode([
                'ok'         => true,
                'rows'       => $rowsHtml,
                'pagination' => $this->buildPagination($page, $totalPages, 'cambiarPaginaUnidadesAjax'),
                'info'       => "{$from}-{$to}/{$total}",
                'total'      => $total,
                'pdf_url'    => $urlBase . '/export-pdf?tab=unidades&b=' . urlencode($buscar) . "&sort={$sort}&dir={$dir}{$filtroParam}",
                'excel_url'  => $urlBase . '/export-excel?tab=unidades&b=' . urlencode($buscar) . "&sort={$sort}&dir={$dir}{$filtroParam}",
            ]);
        }
        exit;
    }

    // ─── TIPOS CRUD ─────────────────────────────────────────────────────────

    public function storeTipo(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        $data = $this->recogerDatosTipo();
        $data['id_empresa'] = (int) $_SESSION['id_empresa'];
        $data['id_usuario'] = (int) $_SESSION['id_usuario'];

        try {
            $id = $this->service->crearTipo($data);
            echo json_encode(['ok' => true, 'msg' => 'Tipo de medida creado correctamente.', 'id' => $id]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function updateTipo(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $id        = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $data = $this->recogerDatosTipo();
        $data['id_empresa'] = $idEmpresa;
        $data['id_usuario'] = (int) $_SESSION['id_usuario'];

        try {
            if ($id <= 0) throw new \Exception('ID no válido.');
            $this->service->actualizarTipo($id, $idEmpresa, $data);
            echo json_encode(['ok' => true, 'msg' => 'Tipo de medida actualizado correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function deleteTipo(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        $id        = (int) ($_POST['id_eliminar'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            if ($id <= 0) throw new \Exception('ID no válido.');
            $this->service->eliminarTipo($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Tipo de medida eliminado correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function getDetalleTipoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        try {
            if ($id <= 0) throw new \Exception('ID no válido.');
            $repo   = new UnidadesMedidaRepository();
            $detalle = $repo->getDetalleTipo($id, $idEmpresa);
            if (!$detalle) throw new \Exception('Tipo no encontrado.');

            $fmt = fn($d) => !empty($d) ? date('d-m-Y H:i:s', strtotime($d)) : '—';

            echo json_encode([
                'ok'   => true,
                'data' => [
                    'creado_at'       => $fmt($detalle['created_at'] ?? null),
                    'creado_por'      => $detalle['creado_por_nombre'] ?? 'Sistema',
                    'actualizado_at'  => $fmt($detalle['updated_at'] ?? null),
                    'total_unidades'  => (int) ($detalle['total_unidades'] ?? 0),
                ],
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ─── UNIDADES CRUD ──────────────────────────────────────────────────────

    public function storeUnidad(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        $data = $this->recogerDatosUnidad();
        $data['id_empresa'] = (int) $_SESSION['id_empresa'];
        $data['id_usuario'] = (int) $_SESSION['id_usuario'];

        try {
            $id = $this->service->crearUnidad($data);
            echo json_encode(['ok' => true, 'msg' => 'Unidad de medida creada correctamente.', 'id' => $id]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function updateUnidad(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $id        = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $data = $this->recogerDatosUnidad();
        $data['id_empresa'] = $idEmpresa;
        $data['id_usuario'] = (int) $_SESSION['id_usuario'];

        try {
            if ($id <= 0) throw new \Exception('ID no válido.');
            $this->service->actualizarUnidad($id, $idEmpresa, $data);
            echo json_encode(['ok' => true, 'msg' => 'Unidad de medida actualizada correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function deleteUnidad(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        $id        = (int) ($_POST['id_eliminar'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            if ($id <= 0) throw new \Exception('ID no válido.');
            $this->service->eliminarUnidad($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Unidad de medida eliminada correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function getDetalleUnidadAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        try {
            if ($id <= 0) throw new \Exception('ID no válido.');
            $repo    = new UnidadesMedidaRepository();
            $detalle = $repo->getDetalleUnidad($id, $idEmpresa);
            if (!$detalle) throw new \Exception('Unidad no encontrada.');

            $fmt = fn($d) => !empty($d) ? date('d-m-Y H:i:s', strtotime($d)) : '—';

            echo json_encode([
                'ok'   => true,
                'data' => [
                    'creado_at'      => $fmt($detalle['created_at'] ?? null),
                    'creado_por'     => $detalle['creado_por_nombre'] ?? 'Sistema',
                    'actualizado_at' => $fmt($detalle['updated_at'] ?? null),
                    'tipo_nombre'    => $detalle['tipo_nombre'] ?? '—',
                ],
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ─── EXPORTACIONES ──────────────────────────────────────────────────────

    public function exportPdf(): void
    {
        $this->requireLeer();

        $idEmpresa   = (int) $_SESSION['id_empresa'];
        $tab         = in_array($_GET['tab'] ?? 'tipos', ['tipos', 'unidades'], true) ? ($_GET['tab'] ?? 'tipos') : 'tipos';
        $buscar      = trim($_GET['b'] ?? '');
        $sort        = trim($_GET['sort'] ?? 'nombre');
        $dir         = strtoupper(trim($_GET['dir'] ?? 'asc'));
        $filtroTipo  = !empty($_GET['f_tipo']) ? (int) $_GET['f_tipo'] : null;
        $perm        = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        try {
            $empresaModel  = new \App\models\Empresa();
            $empresa       = $empresaModel->getPorId($idEmpresa);
            $nombreEmpresa = $empresa['nombre'] ?? 'Empresa';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) require_once $autoload;

            ob_start();
            if ($tab === 'tipos') {
                $data  = $this->service->getTiposListado($idEmpresa, $buscar, 1, 0, $sort, $dir, $idUsuarioFiltro);
                $rows  = $data['rows'];
                $titulo = 'Tipos de Medida';
                ?>
<style>
table{width:100%;border-collapse:collapse;font-family:Arial,sans-serif;font-size:8pt;}
th{background:#f2f2f2;border:1px solid #ccc;padding:4px;text-align:left;}
td{border:1px solid #ccc;padding:4px;}
.header{text-align:center;margin-bottom:15px;}h1{margin:0;font-size:14pt;color:#333;}h2{margin:3px 0 0;color:#666;font-size:10pt;text-transform:uppercase;}
</style>
<page backtop="10mm" backbottom="10mm" backleft="10mm" backright="10mm">
<div class="header"><h1><?= htmlspecialchars($nombreEmpresa) ?></h1><h2><?= $titulo ?></h2></div>
<table><thead><tr><th style="width:15%">Código</th><th style="width:55%">Nombre</th><th style="width:15%">Unidades</th><th style="width:15%">Estado</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
<tr><td><?= htmlspecialchars($r['codigo'] ?? '') ?></td><td><?= htmlspecialchars($r['nombre'] ?? '') ?></td><td><?= (int)($r['total_unidades'] ?? 0) ?></td><td><?= ($r['status'] ?? true) ? 'Activo' : 'Inactivo' ?></td></tr>
<?php endforeach; ?></tbody></table></page>
<?php
            } else {
                $data  = $this->service->getUnidadesListado($idEmpresa, $buscar, 1, 0, $sort, $dir, $filtroTipo, $idUsuarioFiltro);
                $rows  = $data['rows'];
                $titulo = 'Unidades de Medida';
                ?>
<style>
table{width:100%;border-collapse:collapse;font-family:Arial,sans-serif;font-size:8pt;}
th{background:#f2f2f2;border:1px solid #ccc;padding:4px;text-align:left;}
td{border:1px solid #ccc;padding:4px;}
.header{text-align:center;margin-bottom:15px;}h1{margin:0;font-size:14pt;color:#333;}h2{margin:3px 0 0;color:#666;font-size:10pt;text-transform:uppercase;}
</style>
<page backtop="10mm" backbottom="10mm" backleft="10mm" backright="10mm">
<div class="header"><h1><?= htmlspecialchars($nombreEmpresa) ?></h1><h2><?= $titulo ?></h2></div>
<table><thead><tr><th style="width:10%">Código</th><th style="width:35%">Nombre</th><th style="width:10%">Abrev.</th><th style="width:25%">Tipo</th><th style="width:10%">Factor</th><th style="width:10%">Estado</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
<tr><td><?= htmlspecialchars($r['codigo'] ?? '') ?></td><td><?= htmlspecialchars($r['nombre'] ?? '') ?></td><td><?= htmlspecialchars($r['abreviatura'] ?? '') ?></td><td><?= htmlspecialchars($r['tipo_nombre'] ?? '') ?></td><td><?= htmlspecialchars((string)($r['factor_base'] ?? '1')) ?></td><td><?= ($r['status'] ?? true) ? 'Activo' : 'Inactivo' ?></td></tr>
<?php endforeach; ?></tbody></table></page>
<?php
            }
            $content = ob_get_clean();

            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es');
            $html2pdf->writeHTML($content);
            $html2pdf->output('UnidadesMedida_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            header('Content-Type: text/html');
            echo 'Error al generar PDF: ' . $e->getMessage();
            exit;
        }
    }

    public function exportExcel(): void
    {
        $this->requireLeer();

        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $tab        = in_array($_GET['tab'] ?? 'tipos', ['tipos', 'unidades'], true) ? ($_GET['tab'] ?? 'tipos') : 'tipos';
        $buscar     = trim($_GET['b'] ?? '');
        $sort       = trim($_GET['sort'] ?? 'nombre');
        $dir        = strtoupper(trim($_GET['dir'] ?? 'asc'));
        $filtroTipo = !empty($_GET['f_tipo']) ? (int) $_GET['f_tipo'] : null;
        $perm       = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        try {
            $empresaModel  = new \App\models\Empresa();
            $empresa       = $empresaModel->getPorId($idEmpresa);
            $nombreEmpresa = $empresa['nombre'] ?? '';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) require_once $autoload;

            $reportService = new \App\Services\ReportService();

            if ($tab === 'tipos') {
                $data   = $this->service->getTiposListado($idEmpresa, $buscar, 1, 0, $sort, $dir, $idUsuarioFiltro);
                $rows   = $data['rows'];
                $headers = ['Código', 'Nombre', 'Unidades', 'Estado'];
                $exportData = [];
                foreach ($rows as $r) {
                    $exportData[] = [
                        (string)($r['codigo'] ?? ''),
                        (string)($r['nombre'] ?? ''),
                        (string)($r['total_unidades'] ?? '0'),
                        ($r['status'] ?? true) ? 'Activo' : 'Inactivo',
                    ];
                }
                $reportService->exportToExcel('TiposMedida', $headers, $exportData, 'Tipos de Medida', $nombreEmpresa);
            } else {
                $data   = $this->service->getUnidadesListado($idEmpresa, $buscar, 1, 0, $sort, $dir, $filtroTipo, $idUsuarioFiltro);
                $rows   = $data['rows'];
                $headers = ['Código', 'Nombre', 'Abreviatura', 'Tipo', 'Factor Base', 'Estado'];
                $exportData = [];
                foreach ($rows as $r) {
                    $exportData[] = [
                        (string)($r['codigo'] ?? ''),
                        (string)($r['nombre'] ?? ''),
                        (string)($r['abreviatura'] ?? ''),
                        (string)($r['tipo_nombre'] ?? ''),
                        (string)($r['factor_base'] ?? '1'),
                        ($r['status'] ?? true) ? 'Activo' : 'Inactivo',
                    ];
                }
                $reportService->exportToExcel('UnidadesMedida', $headers, $exportData, 'Unidades de Medida', $nombreEmpresa);
            }
            exit;
        } catch (\Throwable $e) {
            header('Content-Type: text/html');
            echo 'Error al generar Excel: ' . $e->getMessage();
            exit;
        }
    }

    // ─── HELPERS ────────────────────────────────────────────────────────────

    private function recogerDatosTipo(): array
    {
        return [
            'codigo' => trim($_POST['codigo'] ?? ''),
            'nombre' => trim($_POST['nombre'] ?? ''),
            'status' => isset($_POST['status']) && $_POST['status'] == '1',
        ];
    }

    private function recogerDatosUnidad(): array
    {
        return [
            'id_tipo'     => (int) ($_POST['id_tipo'] ?? 0),
            'codigo'      => trim($_POST['codigo'] ?? ''),
            'nombre'      => trim($_POST['nombre'] ?? ''),
            'abreviatura' => trim($_POST['abreviatura'] ?? ''),
            'factor_base' => is_numeric($_POST['factor_base'] ?? '') ? (float) $_POST['factor_base'] : 1.0,
            'es_base'     => isset($_POST['es_base']) && $_POST['es_base'] == '1',
            'status'      => isset($_POST['status']) && $_POST['status'] == '1',
        ];
    }

    /**
     * Devuelve todas las unidades activas del mismo tipo que la unidad indicada.
     * Usado por el módulo de ventas para el selector de unidades al facturar.
     * GET /modulos/unidades-medida/get-unidades-por-tipo?id_unidad=X
     */
    public function getUnidadesPorTipoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idUnidad  = (int) ($_GET['id_unidad'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        try {
            if ($idUnidad <= 0) throw new \Exception('ID de unidad no válido.');

            $repo      = new UnidadesMedidaRepository();
            $unidades  = $repo->getUnidadesMismoTipo($idUnidad, $idEmpresa);

            if (empty($unidades)) throw new \Exception('No se encontraron unidades para este tipo.');

            echo json_encode(['ok' => true, 'data' => $unidades]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    private function formatFechas(array &$rows): void
    {
        foreach ($rows as &$r) {
            if (!empty($r['created_at'])) $r['created_at'] = date('d-m-Y H:i:s', strtotime($r['created_at']));
            if (!empty($r['updated_at'])) $r['updated_at'] = date('d-m-Y H:i:s', strtotime($r['updated_at']));
        }
        unset($r);
    }

    private function buildPagination(int $page, int $totalPages, string $fn): string
    {
        $prevDisabled = $page <= 1 ? 'disabled' : '';
        $nextDisabled = $page >= $totalPages ? 'disabled' : '';
        return '<div class="btn-group btn-group-sm">
                  <button type="button" class="btn btn-outline-secondary border-end-0 rounded-end-0" ' . $prevDisabled . ' onclick="' . $fn . '(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>
                  <button type="button" class="btn btn-outline-secondary rounded-start-0" ' . $nextDisabled . ' onclick="' . $fn . '(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>
                </div>';
    }
}
