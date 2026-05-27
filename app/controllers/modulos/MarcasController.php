<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\MarcaRepository;
use App\Rules\modulos\MarcaRules;
use App\Services\LogSistemaService;
use App\Services\modulos\MarcaService;

class MarcasController extends BaseModuloController
{
    private const RUTA_MODULO = 'modulos/marcas';
    private MarcaService $service;

    public function __construct()
    {
        parent::__construct();
        $repo = new MarcaRepository();
        $rules = new MarcaRules();
        $logService = new LogSistemaService();
        $this->service = new MarcaService($repo, $rules, $logService);
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    public function index(): void
    {
        $this->requireLeer();

        $perm = $this->getPermisos();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);

        $buscar   = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'nombre');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'asc'));
        $perPage  = 20;

        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows = $result['rows'];
        $total = $result['total'];

        foreach ($rows as &$r) {
            if (!empty($r['created_at'])) $r['created_at'] = date('d-m-Y H:i:s', strtotime($r['created_at']));
            if (!empty($r['updated_at'])) $r['updated_at'] = date('d-m-Y H:i:s', strtotime($r['updated_at']));
            if (!empty($r['creado_at']))  $r['creado_at']  = date('d-m-Y H:i:s', strtotime($r['creado_at']));
            if (!empty($r['actualizado_at'])) $r['actualizado_at'] = date('d-m-Y H:i:s', strtotime($r['actualizado_at']));
        }
        unset($r);

        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $this->viewWithLayout('layouts.main', 'modulos.marcas.index', [
            'titulo'     => 'Marcas',
            'perm'       => $perm,
            'rutaModulo' => self::RUTA_MODULO,
            'rows'       => $rows,
            'total'      => $total,
            'page'       => $page,
            'totalPages' => $totalPages,
            'perPage'    => $perPage,
            'buscar'     => $buscar,
            'ordenCol'   => $ordenCol,
            'ordenDir'   => $ordenDir,
            'vistaConfig'=> $prefsVista,
            'fullWidth'  => true,
        ]);
    }

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
        $buscar    = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page      = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol  = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'nombre');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'asc'));
        $perPage   = max(1, (int) ($_GET['perPage'] ?? $_POST['perPage'] ?? 20));

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows = $result['rows'];
        $total = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to   = $total > 0 ? min($page * $perPage, $total) : 0;

        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="2" class="text-center py-5 text-muted"><i class="bi bi-tags fs-3 d-block mb-2"></i>No se encontraron marcas.</td></tr>';
        } else {
            foreach ($rows as $r) {
                if (!empty($r['created_at'])) $r['created_at'] = date('d-m-Y H:i:s', strtotime($r['created_at']));
                if (!empty($r['updated_at'])) $r['updated_at'] = date('d-m-Y H:i:s', strtotime($r['updated_at']));

                $statusBadge = ((int)($r['status'] ?? 1) == 1)
                    ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Activo</span>'
                    : '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Inactivo</span>';

                $dataAttr = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                echo '<tr class="marca-row" role="button" tabindex="0" data-row=\'' . $dataAttr . '\' onclick="abrirModalMarcaEditar(this)">
                        <td class="ps-3 fw-medium" data-col="nombre">' . htmlspecialchars($r['nombre'] ?? '') . '</td>
                        <td class="text-center pe-3" data-col="status">' . $statusBadge . '</td>
                      </tr>';
            }
        }
        $rowsHtml = ob_get_clean();

        ob_start();
        $prevDisabled = ($page <= 1) ? 'disabled' : '';
        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
        echo '<div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary border-end-0 rounded-end-0" ' . $prevDisabled . ' onclick="cambiarPaginaAjax(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary rounded-start-0" ' . $nextDisabled . ' onclick="cambiarPaginaAjax(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>
              </div>';
        $paginationHtml = ob_get_clean();

        echo json_encode([
            'ok'        => true,
            'rows'      => $rowsHtml,
            'pagination' => $paginationHtml,
            'info'      => "$from-$to/$total",
            'total'     => $total,
            'data_raw'  => $rows,
            'pdf_url'   => BASE_URL . '/' . self::RUTA_MODULO . '/export-pdf?b=' . urlencode($buscar) . "&sort=$ordenCol&dir=$ordenDir",
            'excel_url' => BASE_URL . '/' . self::RUTA_MODULO . '/export-excel?b=' . urlencode($buscar) . "&sort=$ordenCol&dir=$ordenDir"
        ]);
        exit;
    }

    public function getDetalleAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        try {
            if ($id <= 0) throw new \Exception('ID no válido');

            $repo = new MarcaRepository();
            $marca = $repo->getDetalleCompleto($id, $idEmpresa);

            if (!$marca) throw new \Exception('Marca no encontrada');

            $productosCount = $repo->contarProductosAsignados($id, $idEmpresa);

            $fmt = fn($d) => !empty($d) ? date('d-m-Y H:i:s', strtotime($d)) : '—';

            echo json_encode([
                'ok' => true,
                'data' => [
                    'creado_at' => $fmt($marca['created_at'] ?? $marca['creado_at'] ?? null),
                    'creado_por' => $marca['creado_por_nombre'] ?? 'Sistema',
                    'actualizado_at' => $fmt($marca['updated_at'] ?? $marca['actualizado_at'] ?? null),
                    'actualizado_por' => $marca['actualizado_por_nombre'] ?? '—',
                    'productos_count' => $productosCount
                ]
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function store(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        $data = $this->recogerDatosFormulario();
        $data['id_empresa'] = (int) $_SESSION['id_empresa'];
        $data['id_usuario'] = (int) $_SESSION['id_usuario'];

        try {
            $id = $this->service->crear($data);
            echo json_encode(['ok' => true, 'msg' => 'Marca creada correctamente.', 'id' => $id]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function update(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $id = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $data = $this->recogerDatosFormulario();
        $data['id_empresa'] = $idEmpresa;
        $data['id_usuario'] = (int) $_SESSION['id_usuario'];

        try {
            if ($id <= 0) throw new \Exception('ID de la marca no es válido.');
            $this->service->actualizar($id, $idEmpresa, $data);
            echo json_encode(['ok' => true, 'msg' => 'Marca actualizada correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function delete(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $id = (int) ($_POST['id_eliminar'] ?? 0);

        try {
            if ($id <= 0) throw new \Exception('ID de la marca no es válido.');
            $this->service->eliminar($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Marca eliminada correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    private function recogerDatosFormulario(): array
    {
        return [
            'nombre' => trim($_POST['nombre'] ?? ''),
            'status' => (int) ($_POST['status'] ?? 1),
        ];
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $ordenCol  = trim($_GET['sort'] ?? $_POST['sort'] ?? 'nombre');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? 'asc'));

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $data = $this->service->getListado($idEmpresa, $buscar, 1, 0, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows = $data['rows'];

        try {
            $empresaModel = new \App\models\Empresa();
            $empresa = $empresaModel->getPorId($idEmpresa);
            $nombreEmpresa = $empresa['nombre'] ?? 'REPORTE DE MARCAS';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            ob_start();
?>
            <style>
                table {
                    width: 100%;
                    border-collapse: collapse;
                    font-family: Arial, sans-serif;
                    font-size: 8pt;
                    table-layout: fixed;
                }

                th {
                    background: #f2f2f2;
                    border: 1px solid #ccc;
                    padding: 4px;
                    text-align: left;
                }

                td {
                    border: 1px solid #ccc;
                    padding: 4px;
                    overflow: hidden;
                    word-wrap: break-word;
                }

                .header {
                    text-align: center;
                    margin-bottom: 15px;
                    width: 100%;
                }

                h1 {
                    margin: 0;
                    font-size: 14pt;
                    color: #333;
                }

                h2 {
                    margin: 3px 0 0 0;
                    color: #666;
                    font-size: 10pt;
                    text-transform: uppercase;
                }
            </style>
            <page backtop="10mm" backbottom="10mm" backleft="10mm" backright="10mm">
                <div class="header">
                    <h1><?= htmlspecialchars($nombreEmpresa) ?></h1>
                    <h2>Listado de Marcas</h2>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 80%">Nombre</th>
                            <th style="width: 20%">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars((string)($r['nombre'] ?? '')) ?></td>
                                <td><?= ((int)($r['status'] ?? 1) === 1 ? 'Activo' : 'Inactivo') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </page>
<?php
            $content = ob_get_clean();

            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es');
            $html2pdf->writeHTML($content);
            $html2pdf->output('Marcas_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            header('Content-Type: text/html');
            echo "Error al generar PDF: " . $e->getMessage();
            exit;
        }
    }

    public function exportExcel(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $ordenCol  = trim($_GET['sort'] ?? $_POST['sort'] ?? 'nombre');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? 'asc'));

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $data = $this->service->getListado($idEmpresa, $buscar, 1, 0, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows = $data['rows'];

        try {
            $empresaModel = new \App\models\Empresa();
            $empresa = $empresaModel->getPorId($idEmpresa);
            $nombreEmpresa = $empresa['nombre'] ?? '';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            $headers = ['Nombre', 'Fecha de creación', 'Última modificación', 'Estado'];
            $exportData = [];
            foreach ($rows as $r) {
                $fechaC = !empty($r['created_at']) ? date('d-m-Y H:i:s', strtotime($r['created_at'])) : ($r['creado_at'] ? date('d-m-Y H:i:s', strtotime($r['creado_at'])) : '-');
                $fechaU = !empty($r['updated_at']) ? date('d-m-Y H:i:s', strtotime($r['updated_at'])) : ($r['actualizado_at'] ? date('d-m-Y H:i:s', strtotime($r['actualizado_at'])) : '-');

                $exportData[] = [
                    (string)($r['nombre'] ?? ''),
                    $fechaC,
                    $fechaU,
                    ((int)($r['status'] ?? 1) === 1 ? 'Activo' : 'Inactivo')
                ];
            }

            $reportService = new \App\Services\ReportService();
            $reportService->exportToExcel('Marcas', $headers, $exportData, 'Listado Marcas', $nombreEmpresa);
            exit;
        } catch (\Throwable $e) {
            header('Content-Type: text/html');
            echo "Error al generar Excel: " . $e->getMessage();
            exit;
        }
    }
}
