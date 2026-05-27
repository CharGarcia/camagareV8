<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\VendedorRepository;
use App\Rules\modulos\VendedorRules;
use App\Services\LogSistemaService;
use App\Services\modulos\VendedorService;

class VendedoresController extends BaseModuloController
{
    private VendedorService $service;
    private const RUTA_MODULO = 'modulos/vendedores';

    public function __construct()
    {
        parent::__construct();
        $repository = new VendedorRepository();
        $rules = new VendedorRules();
        $logService = new LogSistemaService();
        $this->service = new VendedorService($repository, $rules, $logService);
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

        // Formatear fechas para el modal
        foreach ($rows as &$r) {
            if (!empty($r['created_at'])) $r['created_at'] = date('d-m-Y H:i:s', strtotime($r['created_at']));
            if (!empty($r['updated_at'])) $r['updated_at'] = date('d-m-Y H:i:s', strtotime($r['updated_at']));
            if (!empty($r['creado_at']))  $r['creado_at']  = date('d-m-Y H:i:s', strtotime($r['creado_at']));
            if (!empty($r['actualizado_at'])) $r['actualizado_at'] = date('d-m-Y H:i:s', strtotime($r['actualizado_at']));
        }
        unset($r);

        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $this->viewWithLayout('layouts.main', 'modulos.vendedores.index', [
            'titulo'     => 'Vendedores',
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
        $perPage   = 20;

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows = $result['rows'];
        $total = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to   = $total > 0 ? min($page * $perPage, $total) : 0;

        // Renderizar Filas
        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-person-badge fs-3 d-block mb-2"></i>No se encontraron vendedores.</td></tr>';
        } else {
            foreach ($rows as $r) {
                // Formatear fechas para el modal
                if (!empty($r['created_at'])) $r['created_at'] = date('d-m-Y H:i:s', strtotime($r['created_at']));
                if (!empty($r['updated_at'])) $r['updated_at'] = date('d-m-Y H:i:s', strtotime($r['updated_at']));
                if (!empty($r['creado_at']))  $r['creado_at']  = date('d-m-Y H:i:s', strtotime($r['creado_at']));
                if (!empty($r['actualizado_at'])) $r['actualizado_at'] = date('d-m-Y H:i:s', strtotime($r['actualizado_at']));

                $statusBadge = ((int)($r['status'] ?? 1) == 1)
                    ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Activo</span>'
                    : '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Inactivo</span>';

                $dataAttr = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                echo '<tr class="vendedor-row" role="button" tabindex="0" data-row=\'' . $dataAttr . '\' onclick="abrirModalVendedorEditar(this)">
                        <td class="ps-3" data-col="identificacion"><code class="text-secondary">' . htmlspecialchars($r['identificacion'] ?? '') . '</code></td>
                        <td class="fw-medium" data-col="nombre">' . htmlspecialchars($r['nombre'] ?? '') . '</td>
                        <td data-col="correo">' . htmlspecialchars($r['correo'] ?? '—') . '</td>
                        <td data-col="telefono">' . htmlspecialchars($r['telefono'] ?? '—') . '</td>
                        <td data-col="direccion" class="text-muted small">' . htmlspecialchars($r['direccion'] ?? '—') . '</td>
                        <td class="text-center pe-3" data-col="status">' . $statusBadge . '</td>
                      </tr>';
            }
        }
        $rowsHtml = ob_get_clean();

        // Renderizar Paginación
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

            $repo = new VendedorRepository(); // El controlador ya tiene acceso, pero por claridad
            $vendedor = $repo->getDetalleCompleto($id, $idEmpresa);

            if (!$vendedor) throw new \Exception('Vendedor no encontrado');

            $clientesCount = $repo->contarClientesAsignados($id, $idEmpresa);

            $fmt = fn($d) => !empty($d) ? date('d-m-Y H:i:s', strtotime($d)) : '—';

            echo json_encode([
                'ok' => true,
                'data' => [
                    'creado_at' => $fmt($vendedor['created_at'] ?? $vendedor['creado_at'] ?? null),
                    'creado_por' => $vendedor['creado_por_nombre'] ?? 'Sistema',
                    'actualizado_at' => $fmt($vendedor['updated_at'] ?? $vendedor['actualizado_at'] ?? null),
                    'actualizado_por' => $vendedor['actualizado_por_nombre'] ?? '—',
                    'clientes_count' => $clientesCount
                ]
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
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
            $nombreEmpresa = $empresa['nombre'] ?? 'REPORTE DE VENDEDORES';

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
                    <h2>Listado de Vendedores</h2>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 20%">Identificación</th>
                            <th style="width: 40%">Nombre</th>
                            <th style="width: 25%">Correo</th>
                            <th style="width: 15%">Teléfono</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars((string)($r['identificacion'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)($r['nombre'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)($r['correo'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($r['telefono'] ?? '-')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </page>
<?php
            $content = ob_get_clean();

            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es');
            $html2pdf->writeHTML($content);
            $html2pdf->output('Vendedores_' . date('Ymd_His') . '.pdf', 'D');
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

            $headers = ['Identificación', 'Nombre', 'Correo', 'Teléfono', 'Dirección', 'Fecha de creación', 'Última modificación', 'Estado'];
            $exportData = [];
            foreach ($rows as $r) {
                $fechaC = !empty($r['created_at']) ? date('d-m-Y H:i:s', strtotime($r['created_at'])) : ($r['creado_at'] ? date('d-m-Y H:i:s', strtotime($r['creado_at'])) : '-');
                $fechaU = !empty($r['updated_at']) ? date('d-m-Y H:i:s', strtotime($r['updated_at'])) : ($r['actualizado_at'] ? date('d-m-Y H:i:s', strtotime($r['actualizado_at'])) : '-');

                $exportData[] = [
                    (string)($r['identificacion'] ?? ''),
                    (string)($r['nombre'] ?? ''),
                    (string)($r['correo'] ?? ''),
                    (string)($r['telefono'] ?? ''),
                    (string)($r['direccion'] ?? ''),
                    $fechaC,
                    $fechaU,
                    ((int)($r['status'] ?? 1) === 1 ? 'Activo' : 'Inactivo')
                ];
            }

            $reportService = new \App\Services\ReportService();
            $reportService->exportToExcel('Vendedores', $headers, $exportData, 'Listado Vendedores', $nombreEmpresa);
            exit;
        } catch (\Throwable $e) {
            header('Content-Type: text/html');
            echo "Error al generar Excel: " . $e->getMessage();
            exit;
        }
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
            echo json_encode(['ok' => true, 'msg' => 'Vendedor creado correctamente.', 'id' => $id]);
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
        $data['id_usuario'] = (int) $_SESSION['id_usuario'];

        try {
            if ($id <= 0) throw new \Exception('ID de vendedor no válido.');
            $this->service->actualizar($id, $idEmpresa, $data);
            echo json_encode(['ok' => true, 'msg' => 'Vendedor actualizado correctamente.']);
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
            if ($id <= 0) throw new \Exception('ID de vendedor no válido.');
            $this->service->eliminar($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Vendedor eliminado correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    private function recogerDatosFormulario(): array
    {
        return [
            'nombre'         => trim($_POST['nombre'] ?? ''),
            'identificacion' => trim($_POST['identificacion'] ?? ''),
            'telefono'       => trim($_POST['telefono'] ?? '') !== '' ? trim($_POST['telefono']) : null,
            'correo'         => trim($_POST['correo'] ?? '') !== '' ? trim($_POST['correo']) : null,
            'direccion'      => trim($_POST['direccion'] ?? '') !== '' ? trim($_POST['direccion']) : null,
            'status'         => (int) ($_POST['status'] ?? 1),
        ];
    }
}
