<?php
declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\AutomatizacionesRepository;
use App\Rules\modulos\AutomatizacionesRules;
use App\Services\LogSistemaService;
use App\Services\modulos\AutomatizacionesService;

class AutomatizacionesController extends BaseModuloController
{
    private AutomatizacionesService $service;
    private const RUTA_MODULO = 'modulos/automatizaciones';

    public function __construct()
    {
        parent::__construct();
        $repository    = new AutomatizacionesRepository();
        $rules         = new AutomatizacionesRules();
        $logService    = new LogSistemaService();
        $this->service = new AutomatizacionesService($repository, $rules, $logService);
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    public function index(): void
    {
        $this->requireLeer();

        $perm       = $this->getPermisos();
        $idEmpresa  = (int)$_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);

        $buscar   = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page     = max(1, (int)($_GET['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'nombre');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'asc'));
        $perPage  = 25;

        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);

        foreach ($result['rows'] as &$r) {
            if (!empty($r['created_at']))        $r['created_at']        = date('d-m-Y H:i:s', strtotime($r['created_at']));
            if (!empty($r['updated_at']))        $r['updated_at']        = date('d-m-Y H:i:s', strtotime($r['updated_at']));
            if (!empty($r['proxima_ejecucion'])) $r['proxima_ejecucion_fmt'] = date('d-m-Y H:i:s', strtotime($r['proxima_ejecucion']));
            if (!empty($r['ultima_ejecucion']))  $r['ultima_ejecucion_fmt']  = date('d-m-Y H:i:s', strtotime($r['ultima_ejecucion']));
        }
        unset($r);

        $totalPages = $perPage > 0 ? (int)ceil($result['total'] / $perPage) : 1;
        $modulos    = $this->service->getModulosDisponibles();

        $this->viewWithLayout('layouts.main', 'modulos.automatizaciones.index', [
            'titulo'      => 'Automatizaciones',
            'perm'        => $perm,
            'rutaModulo'  => self::RUTA_MODULO,
            'rows'        => $result['rows'],
            'total'       => $result['total'],
            'page'        => $page,
            'totalPages'  => $totalPages,
            'perPage'     => $perPage,
            'buscar'      => $buscar,
            'ordenCol'    => $ordenCol,
            'ordenDir'    => $ordenDir,
            'modulos'     => $modulos,
            'vistaConfig' => $prefsVista,
            'fullWidth'   => true,
        ]);
    }

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa  = (int)$_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
        $buscar     = trim($_GET['b'] ?? '');
        $page       = max(1, (int)($_GET['page'] ?? 1));
        $ordenCol   = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'nombre');
        $ordenDir   = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'ASC'));
        $perPage    = 25;

        $perm            = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $result     = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows       = $result['rows'];
        $total      = $result['total'];
        $totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;
        $from       = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to         = $total > 0 ? min($page * $perPage, $total) : 0;

        foreach ($rows as &$r) {
            if (!empty($r['proxima_ejecucion'])) $r['proxima_ejecucion_fmt'] = date('d-m-Y H:i:s', strtotime($r['proxima_ejecucion']));
            if (!empty($r['ultima_ejecucion']))  $r['ultima_ejecucion_fmt']  = date('d-m-Y H:i:s', strtotime($r['ultima_ejecucion']));
        }
        unset($r);

        if (empty($rows)) {
            $rowsHtml = '<tr><td colspan="8" class="text-center py-5 text-muted"><i class="fas fa-robot fa-2x d-block mb-2"></i>No se encontraron automatizaciones.</td></tr>';
        } else {
            $rowsHtml = '';
            foreach ($rows as $r) {
                $rowsHtml .= $this->renderFila($r);
            }
        }

        ob_start();
        $prevDis = ($page <= 1)         ? 'disabled' : '';
        $nextDis = ($page >= $totalPages) ? 'disabled' : '';
        echo '<div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary border-end-0 rounded-end-0" ' . $prevDis . ' onclick="cambiarPaginaAjax(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary rounded-start-0" '            . $nextDis . ' onclick="cambiarPaginaAjax(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>
              </div>';
        $paginationHtml = ob_get_clean();

        echo json_encode([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'pagination' => $paginationHtml,
            'info'       => "{$from}-{$to}/{$total}",
            'total'      => $total,
            'pdf_url'    => BASE_URL . '/' . self::RUTA_MODULO . '/export-pdf?b='    . urlencode($buscar) . "&sort={$ordenCol}&dir={$ordenDir}",
            'excel_url'  => BASE_URL . '/' . self::RUTA_MODULO . '/export-excel?b=' . urlencode($buscar) . "&sort={$ordenCol}&dir={$ordenDir}",
        ]);
        exit;
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $buscar    = trim($_GET['b'] ?? '');
        $ordenCol  = trim($_GET['sort'] ?? 'nombre');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? 'ASC'));

        $perm            = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;
        $data            = $this->service->getListado($idEmpresa, $buscar, 1, 0, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows            = $data['rows'];

        try {
            $empresaModel  = new \App\models\Empresa();
            $empresa       = $empresaModel->getPorId($idEmpresa);
            $nombreEmpresa = $empresa['nombre'] ?? 'AUTOMATIZACIONES';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) require_once $autoload;

            ob_start(); ?>
            <style>
                table { width:100%; border-collapse:collapse; font-family:Arial,sans-serif; font-size:8pt; }
                th { background:#f2f2f2; border:1px solid #ccc; padding:4px; text-align:left; }
                td { border:1px solid #ccc; padding:4px; word-wrap:break-word; }
                .header { text-align:center; margin-bottom:15px; }
                h1 { margin:0; font-size:14pt; color:#333; }
                h2 { margin:3px 0 0; color:#666; font-size:10pt; text-transform:uppercase; }
            </style>
            <page backtop="10mm" backbottom="10mm" backleft="10mm" backright="10mm">
                <div class="header">
                    <h1><?= htmlspecialchars($nombreEmpresa) ?></h1>
                    <h2>Listado de Automatizaciones</h2>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th style="width:25%">Nombre</th>
                            <th style="width:12%">Módulo</th>
                            <th style="width:20%">Acción</th>
                            <th style="width:12%">Frecuencia</th>
                            <th style="width:18%">Próx. ejecución</th>
                            <th style="width:13%">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['nombre'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['modulo'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['accion'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['frecuencia_tipo'] ?? '') ?></td>
                                <td><?= !empty($r['proxima_ejecucion']) ? date('d-m-Y H:i', strtotime($r['proxima_ejecucion'])) : '—' ?></td>
                                <td><?= htmlspecialchars($r['estado'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </page>
            <?php
            $content = ob_get_clean();
            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $html2pdf->writeHTML($content);
            $html2pdf->output('Automatizaciones_' . date('Ymd_His') . '.pdf', 'D');
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
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $buscar    = trim($_GET['b'] ?? '');
        $ordenCol  = trim($_GET['sort'] ?? 'nombre');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? 'ASC'));

        $perm            = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;
        $data            = $this->service->getListado($idEmpresa, $buscar, 1, 0, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows            = $data['rows'];

        try {
            $empresaModel  = new \App\models\Empresa();
            $empresa       = $empresaModel->getPorId($idEmpresa);
            $nombreEmpresa = $empresa['nombre'] ?? '';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) require_once $autoload;

            $headers    = ['Nombre', 'Módulo', 'Acción', 'Frecuencia', 'Valor frecuencia', 'Próx. ejecución', 'Últ. ejecución', 'Último resultado', 'Estado'];
            $exportData = [];
            foreach ($rows as $r) {
                $exportData[] = [
                    $r['nombre']          ?? '',
                    $r['modulo']          ?? '',
                    $r['accion']          ?? '',
                    $r['frecuencia_tipo'] ?? '',
                    $r['frecuencia_valor'] ?? '',
                    !empty($r['proxima_ejecucion']) ? date('d-m-Y H:i:s', strtotime($r['proxima_ejecucion'])) : '',
                    !empty($r['ultima_ejecucion'])  ? date('d-m-Y H:i:s', strtotime($r['ultima_ejecucion']))  : '',
                    $r['ultimo_resultado'] ?? '',
                    $r['estado']           ?? '',
                ];
            }

            $reportService = new \App\Services\ReportService();
            $reportService->exportToExcel('Automatizaciones', $headers, $exportData, 'Listado Automatizaciones', $nombreEmpresa);
            exit;
        } catch (\Throwable $e) {
            header('Content-Type: text/html');
            echo 'Error al generar Excel: ' . $e->getMessage();
            exit;
        }
    }

    public function store(): void
    {
        $this->requireCrear();
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $idUsuario = (int)$_SESSION['id_usuario'];

        try {
            $data = $this->getPostData();
            $id   = $this->service->crear($data, $idEmpresa, $idUsuario);
            $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Automatización creada correctamente.']);
        } catch (\InvalidArgumentException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function update(): void
    {
        $this->requireActualizar();
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $idUsuario = (int)$_SESSION['id_usuario'];
        $id        = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            $this->json(['ok' => false, 'error' => 'ID inválido.'], 400);
            return;
        }

        try {
            $data = $this->getPostData();
            $this->service->actualizar($id, $data, $idEmpresa, $idUsuario);
            $this->json(['ok' => true, 'mensaje' => 'Automatización actualizada correctamente.']);
        } catch (\InvalidArgumentException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function delete(): void
    {
        $this->requireEliminar();
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $idUsuario = (int)$_SESSION['id_usuario'];
        $id        = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            $this->json(['ok' => false, 'error' => 'ID inválido.'], 400);
            return;
        }

        try {
            $this->service->eliminar($id, $idEmpresa, $idUsuario);
            $this->json(['ok' => true, 'mensaje' => 'Automatización eliminada correctamente.']);
        } catch (\RuntimeException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => 'Error al eliminar la automatización.'], 500);
        }
    }

    /** Devuelve los datos de una automatización para edición (AJAX) */
    public function getOne(): void
    {
        $this->requireLeer();
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $id        = (int)($_GET['id'] ?? 0);

        $registro = $this->service->getById($id, $idEmpresa);
        if ($registro === null) {
            $this->json(['ok' => false, 'error' => 'No encontrado.'], 404);
            return;
        }

        if (is_string($registro['parametros'])) {
            $registro['parametros'] = json_decode($registro['parametros'], true) ?? [];
        }

        $this->json(['ok' => true, 'data' => $registro]);
    }

    /** Devuelve acciones disponibles para un módulo (AJAX) */
    public function getAcciones(): void
    {
        $this->requireLeer();
        $modulo  = trim($_GET['modulo'] ?? '');
        $acciones = $this->service->getAccionesPorModulo($modulo);
        $this->json(['ok' => true, 'acciones' => $acciones]);
    }

    /** Devuelve campos de parámetros para módulo+acción (AJAX) */
    public function getParametros(): void
    {
        $this->requireLeer();
        $modulo     = trim($_GET['modulo'] ?? '');
        $accion     = trim($_GET['accion'] ?? '');
        $parametros = $this->service->getParametrosPorAccion($modulo, $accion);
        $this->json(['ok' => true, 'parametros' => $parametros]);
    }

    /** Historial de ejecuciones de una automatización (AJAX) */
    public function log(): void
    {
        $this->requireLeer();
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $id        = (int)($_GET['id'] ?? 0);
        $page      = max(1, (int)($_GET['page'] ?? 1));

        try {
            $result = $this->service->getLog($id, $idEmpresa, $page, 20);
            foreach ($result['rows'] as &$r) {
                if (!empty($r['iniciado_en']))   $r['iniciado_en']   = date('d-m-Y H:i:s', strtotime($r['iniciado_en']));
                if (!empty($r['finalizado_en'])) $r['finalizado_en'] = date('d-m-Y H:i:s', strtotime($r['finalizado_en']));
            }
            unset($r);
            $this->json(['ok' => true, 'rows' => $result['rows'], 'total' => $result['total']]);
        } catch (\RuntimeException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 404);
        }
    }

    /** Ejecuta manualmente una automatización (AJAX) */
    public function ejecutar(): void
    {
        $this->requireActualizar();
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $idUsuario = (int)$_SESSION['id_usuario'];
        $id        = (int)($_POST['id'] ?? 0);

        try {
            $resultado = $this->service->ejecutarManual($id, $idEmpresa, $idUsuario);
            $this->json(['ok' => true, 'resultado' => $resultado]);
        } catch (\RuntimeException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => 'Error al ejecutar la automatización.'], 500);
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function renderFila(array $r): string
    {
        $estadoClases    = ['activo' => 'success', 'inactivo' => 'secondary', 'en_proceso' => 'warning'];
        $resultadoClases = ['exitoso' => 'success', 'error' => 'danger', 'pendiente' => 'secondary'];

        $ce   = $estadoClases[$r['estado'] ?? '']              ?? 'secondary';
        $cr   = $resultadoClases[$r['ultimo_resultado'] ?? ''] ?? 'secondary';
        $data = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');

        $badgeEstado    = '<span class="badge bg-' . $ce . ' bg-opacity-10 text-' . $ce . ' border border-' . $ce . ' border-opacity-25">' . htmlspecialchars($r['estado'] ?? '') . '</span>';
        $badgeResultado = !empty($r['ultimo_resultado'])
            ? '<span class="badge bg-' . $cr . ' bg-opacity-10 text-' . $cr . ' border border-' . $cr . ' border-opacity-25">' . htmlspecialchars($r['ultimo_resultado']) . '</span>'
            : '<span class="text-muted">—</span>';

        $proxima  = !empty($r['proxima_ejecucion_fmt'])
            ? '<i class="fas fa-clock text-info me-1" style="font-size:.75rem;"></i>' . htmlspecialchars($r['proxima_ejecucion_fmt'])
            : '<span class="text-muted">—</span>';
        $ultima   = !empty($r['ultima_ejecucion_fmt']) ? htmlspecialchars($r['ultima_ejecucion_fmt']) : '<span class="text-muted">Sin ejecuciones</span>';
        $estab    = !empty($r['nombre_establecimiento'])
            ? '<br><small class="text-muted">' . htmlspecialchars($r['nombre_establecimiento']) . '</small>'
            : '';

        return '<tr class="auto-row" role="button" tabindex="0" data-row=\'' . $data . '\' onclick="AUTO_abrirModalEditar(this)">
            <td class="ps-3 fw-medium text-truncate" style="max-width:260px;" data-col="nombre">' . htmlspecialchars($r['nombre'] ?? '') . $estab . '</td>
            <td data-col="modulo"><span class="badge bg-light text-dark border">' . htmlspecialchars($r['modulo'] ?? '') . '</span></td>
            <td data-col="accion" class="text-truncate" style="max-width:180px;">' . htmlspecialchars($r['accion'] ?? '') . '</td>
            <td data-col="frecuencia_tipo">' . htmlspecialchars($r['frecuencia_tipo'] ?? '') . '</td>
            <td data-col="proxima_ejecucion" style="font-size:.82rem;">' . $proxima . '</td>
            <td data-col="ultima_ejecucion" style="font-size:.82rem;">' . $ultima . '</td>
            <td data-col="ultimo_resultado" class="text-center">' . $badgeResultado . '</td>
            <td data-col="estado" class="text-center pe-3">' . $badgeEstado . '</td>
        </tr>';
    }

    private function getPostData(): array
    {
        $parametros = [];
        $rawParametros = $_POST['parametros'] ?? '';
        if ($rawParametros !== '') {
            $parametros = json_decode($rawParametros, true) ?? [];
        }

        return [
            'id_establecimiento' => !empty($_POST['id_establecimiento']) ? (int)$_POST['id_establecimiento'] : null,
            'nombre'             => trim($_POST['nombre'] ?? ''),
            'descripcion'        => trim($_POST['descripcion'] ?? ''),
            'modulo'             => trim($_POST['modulo'] ?? ''),
            'accion'             => trim($_POST['accion'] ?? ''),
            'parametros'         => $parametros,
            'frecuencia_tipo'    => trim($_POST['frecuencia_tipo'] ?? ''),
            'frecuencia_valor'   => trim($_POST['frecuencia_valor'] ?? ''),
            'cron_expression'    => trim($_POST['cron_expression'] ?? ''),
            'estado'             => trim($_POST['estado'] ?? 'activo'),
        ];
    }
}
