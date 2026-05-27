<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\VehiculoRepository;
use App\Rules\modulos\VehiculoRules;
use App\Services\LogSistemaService;
use App\Services\modulos\VehiculoService;

class VehiculosController extends BaseModuloController
{
    private const RUTA_MODULO = 'modulos/vehiculos';
    private VehiculoService $service;

    public function __construct()
    {
        parent::__construct();
        $repo = new VehiculoRepository();
        $rules = new VehiculoRules();
        $logService = new LogSistemaService();
        $this->service = new VehiculoService($repo, $rules, $logService);
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
        $ordenCol = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'id');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'desc'));
        $perPage  = 20;

        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows = $result['rows'];
        $total = $result['total'];

        foreach ($rows as &$r) {
            if (!empty($r['created_at'])) $r['created_at'] = date('d-m-Y H:i:s', strtotime($r['created_at']));
        }
        unset($r);

        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $this->viewWithLayout('layouts.main', 'modulos.vehiculos.index', [
            'titulo'     => 'Vehículos',
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
        $ordenCol  = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'id');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'desc'));
        $perPage   = 20;

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
            echo '<tr><td colspan="9" class="text-center py-5 text-muted"><i class="bi bi-car-front fs-3 d-block mb-2"></i>No se encontraron vehículos.</td></tr>';
        } else {
            foreach ($rows as $r) {
                if (!empty($r['created_at'])) $r['created_at'] = date('d-m-Y H:i:s', strtotime($r['created_at']));

                $estadoBadge = ($r['estado'] === 'activo')
                    ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Activo</span>'
                    : '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Inactivo</span>';

                $dataAttr = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                echo '<tr class="vehiculo-row" role="button" tabindex="0" data-row=\'' . $dataAttr . '\' onclick="abrirModalVehiculoEditar(this)">
                        <td class="ps-3 fw-medium" data-col="marca">' . htmlspecialchars($r['marca'] ?? '') . '</td>
                        <td data-col="placa">' . htmlspecialchars($r['placa'] ?? '') . '</td>
                        <td data-col="chasis">' . htmlspecialchars($r['chasis'] ?? '') . '</td>
                        <td data-col="anio">' . htmlspecialchars((string)($r['anio'] ?? '')) . '</td>
                        <td data-col="propietario">' . htmlspecialchars($r['propietario'] ?? '') . '</td>
                        <td data-col="correo">' . htmlspecialchars($r['correo'] ?? '—') . '</td>
                        <td data-col="telefono">' . htmlspecialchars($r['telefono'] ?? '—') . '</td>
                        <td class="text-center" data-col="estado">' . $estadoBadge . '</td>
                        <td class="text-center" data-col="created_at">' . htmlspecialchars($r['created_at'] ?? '') . '</td>
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

            $vehiculo = $this->service->findById($id, $idEmpresa);

            if (!$vehiculo) throw new \Exception('Vehículo no encontrado');

            $fmt = fn($d) => !empty($d) ? date('d-m-Y H:i:s', strtotime($d)) : '—';

            echo json_encode([
                'ok' => true,
                'data' => [
                    'creado_at' => $fmt($vehiculo['created_at'] ?? null),
                    'creado_por' => $vehiculo['creado_por_nombre'] ?? 'Sistema',
                    'actualizado_at' => $fmt($vehiculo['updated_at'] ?? null),
                    'actualizado_por' => $vehiculo['actualizado_por_nombre'] ?? '—'
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
            echo json_encode(['ok' => true, 'msg' => 'Vehículo creado correctamente.', 'id' => $id]);
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
        $data['id_empresa'] = $idEmpresa;

        try {
            if ($id <= 0) throw new \Exception('ID del vehículo no es válido.');
            $this->service->actualizar($id, $idEmpresa, $data);
            echo json_encode(['ok' => true, 'msg' => 'Vehículo actualizado correctamente.']);
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
            if ($id <= 0) throw new \Exception('ID del vehículo no es válido.');
            $this->service->eliminar($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Vehículo eliminado correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    private function recogerDatosFormulario(): array
    {
        return [
            'marca'       => trim($_POST['marca'] ?? ''),
            'placa'       => trim($_POST['placa'] ?? ''),
            'chasis'      => trim($_POST['chasis'] ?? ''),
            'anio'        => (int) ($_POST['anio'] ?? 0),
            'propietario' => trim($_POST['propietario'] ?? ''),
            'estado'      => trim($_POST['estado'] ?? 'activo'),
            'correo'      => trim($_POST['correo'] ?? ''),
            'telefono'    => trim($_POST['telefono'] ?? ''),
        ];
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $ordenCol  = trim($_GET['sort'] ?? $_POST['sort'] ?? 'id');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? 'desc'));

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $data = $this->service->getListado($idEmpresa, $buscar, 1, 0, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows = $data['rows'];

        try {
            $empresaModel = new \App\models\Empresa();
            $empresa = $empresaModel->getPorId($idEmpresa);
            $nombreEmpresa = $empresa['nombre'] ?? 'REPORTE DE VEHÍCULOS';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            ob_start();
?>
            <style>
                table { width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 8pt; table-layout: fixed; }
                th { background: #f2f2f2; border: 1px solid #ccc; padding: 4px; text-align: left; }
                td { border: 1px solid #ccc; padding: 4px; overflow: hidden; word-wrap: break-word; }
                .header { text-align: center; margin-bottom: 15px; width: 100%; }
                h1 { margin: 0; font-size: 14pt; color: #333; }
                h2 { margin: 3px 0 0 0; color: #666; font-size: 10pt; text-transform: uppercase; }
            </style>
            <page backtop="10mm" backbottom="10mm" backleft="10mm" backright="10mm">
                <div class="header">
                    <h1><?= htmlspecialchars($nombreEmpresa) ?></h1>
                    <h2>Listado de Vehículos</h2>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 20%">Marca</th>
                            <th style="width: 15%">Placa</th>
                            <th style="width: 20%">Chasis</th>
                            <th style="width: 10%">Año</th>
                            <th style="width: 25%">Propietario</th>
                            <th style="width: 10%">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars((string)($r['marca'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)($r['placa'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)($r['chasis'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)($r['anio'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)($r['propietario'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)($r['estado'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </page>
<?php
            $content = ob_get_clean();

            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $html2pdf->writeHTML($content);
            $html2pdf->output('Vehiculos_' . date('Ymd_His') . '.pdf', 'D');
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
        $ordenCol  = trim($_GET['sort'] ?? $_POST['sort'] ?? 'id');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? 'desc'));

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

            $headers = ['Marca', 'Placa', 'Chasis', 'Año', 'Propietario', 'Estado', 'Fecha Registro'];
            $exportData = [];
            foreach ($rows as $r) {
                $exportData[] = [
                    (string)($r['marca'] ?? ''),
                    (string)($r['placa'] ?? ''),
                    (string)($r['chasis'] ?? ''),
                    (string)($r['anio'] ?? ''),
                    (string)($r['propietario'] ?? ''),
                    (string)($r['estado'] ?? ''),
                    (string)($r['created_at'] ?? '')
                ];
            }

            $reportService = new \App\Services\ReportService();
            $reportService->exportToExcel('Vehículos', $headers, $exportData, 'Listado Vehículos', $nombreEmpresa);
            exit;
        } catch (\Throwable $e) {
            header('Content-Type: text/html');
            echo "Error al generar Excel: " . $e->getMessage();
            exit;
        }
    }
}
