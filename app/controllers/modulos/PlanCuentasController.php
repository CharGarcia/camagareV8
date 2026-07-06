<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\PlanCuentaRepository;
use App\Rules\modulos\PlanCuentaRules;
use App\Services\LogSistemaService;
use App\Services\modulos\PlanCuentaService;
use App\models\CentroCosto;
use App\models\Proyecto;

class PlanCuentasController extends BaseModuloController
{
    private PlanCuentaService $service;
    private const RUTA_MODULO = 'modulos/plan-cuentas';

    public function __construct()
    {
        parent::__construct();
        $repository = new PlanCuentaRepository();
        $rules = new PlanCuentaRules();
        $logService = new LogSistemaService();
        $this->service = new PlanCuentaService($repository, $rules, $logService);
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    public function index(): void
    {
        $this->requireLeer();

        $perm      = $this->getPermisos();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        
        $buscar   = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $_POST['sort'] ?? 'codigo');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? 'asc'));
        $perPage  = max(1, (int) ($_GET['perPage'] ?? $_POST['perPage'] ?? 20));

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir);
        $rows   = $result['rows'];
        $total  = $result['total'];
        
        $repo = new PlanCuentaRepository();
        $conteoTotalEmpresa = $repo->contarPorEmpresa($idEmpresa);

        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        // Assets for modal
        $ccModel = new CentroCosto();
        $centros = $ccModel->getActivosPorEmpresa($idEmpresa);
        
        $pjModel = new Proyecto();
        $proyectos = $pjModel->getActivosPorEmpresa($idEmpresa);

        $this->viewWithLayout('layouts.main', 'modulos.plan_cuentas.index', [
            'titulo'     => 'Plan de Cuentas',
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
            'centros'    => $centros,
            'proyectos'  => $proyectos,
            'conteoTotal' => $conteoTotalEmpresa,
            'fullWidth'  => true,
        ]);
    }

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page      = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol  = trim($_GET['sort'] ?? $_POST['sort'] ?? 'codigo');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? 'asc'));
        $perPage   = max(1, (int) ($_GET['perPage'] ?? $_POST['perPage'] ?? 20));

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir);
        $rows   = $result['rows'];
        $total  = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to   = $total > 0 ? min($page * $perPage, $total) : 0;

        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-list-columns fs-3 d-block mb-2"></i>No se encontraron cuentas.</td></tr>';
        } else {
            foreach ($rows as $r) {
                $dataJson = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                $statusBadge = ($r['status'] === 1)
                    ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Activo</span>'
                    : '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Inactivo</span>';

                echo '<tr role="button" data-row=\'' . $dataJson . '\' onclick="abrirModalEditar(this)">
                        <td class="ps-3"><code class="text-secondary">' . htmlspecialchars($r['codigo'] ?? '—') . '</code></td>
                        <td class="' . ((int)$r['nivel'] < 5 ? 'fw-medium' : 'fw-normal') . '">' . str_repeat('&nbsp;&nbsp;', (int)($r['nivel'] ?? 1) - 1) . '<span class="' . ((int)$r['nivel'] < 5 ? 'text-uppercase' : '') . '">' . htmlspecialchars($r['nombre'] ?? '') . '</span></td>
                        <td class="text-center" onclick="event.stopPropagation()">
                             <button class="btn btn-outline-primary btn-xs py-0 px-1 border-0" onclick="abrirModalCrearHijo(\'' . $r['codigo'] . '\')" title="Agregar Subcuenta"><i class="bi bi-plus-circle"></i></button>
                        </td>
                        <td class="text-center" onclick="event.stopPropagation()">
                             ' . ((int)$r['nivel'] > 1 ? '<button class="btn btn-outline-danger btn-xs py-0 px-1 border-0" onclick="eliminarAccionDetalle(' . $r['id'] . ')" title="Eliminar"><i class="bi bi-dash-circle"></i></button>' : '') . '
                        </td>
                        <td class="text-center">' . htmlspecialchars($r['nivel'] ?? '') . '</td>
                        <td class="text-center">' . $statusBadge . '</td>
                      </tr>';
            }
        }
        $rowsHtml = ob_get_clean();

        ob_start();
        $prevDisabled = ($page <= 1) ? 'disabled' : '';
        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
        echo '<div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary rounded-end-0" ' . $prevDisabled . ' onclick="cambiarPaginaAjax(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary rounded-start-0" ' . $nextDisabled . ' onclick="cambiarPaginaAjax(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>
              </div>';
        $paginationHtml = ob_get_clean();

        echo json_encode([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'data_raw'   => $rows,
            'pagination' => $paginationHtml,
            'info'       => "$from-$to/$total",
            'total'      => $total,
            'pdf_url'    => BASE_URL . '/' . self::RUTA_MODULO . '/export-pdf?b=' . urlencode($buscar) . "&sort=$ordenCol&dir=$ordenDir",
            'excel_url'  => BASE_URL . '/' . self::RUTA_MODULO . '/export-excel?b=' . urlencode($buscar) . "&sort=$ordenCol&dir=$ordenDir"
        ]);
        exit;
    }

    public function searchAjaxCuentas(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $q = trim($_GET['q'] ?? '');
        $tipo = trim($_GET['tipo'] ?? ''); // 'activo' o 'costo_gasto'

        try {
            $repo = new PlanCuentaRepository();
            $data = $repo->searchCuentas($idEmpresa, $q, $tipo, 15);
            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
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

            $repo = new PlanCuentaRepository();
            $p = $repo->getDetalleCompleto($id, $idEmpresa);

            if (!$p) throw new \Exception('Cuenta no encontrada');

            $fmt = fn($d) => !empty($d) ? date('d-m-Y H:i:s', strtotime($d)) : '—';

            echo json_encode([
                'ok' => true,
                'data' => [
                    'creado_at' => $fmt($p['created_at'] ?? null),
                    'creado_por' => $p['creado_por_nombre'] ?? 'Sistema',
                    'actualizado_at' => $fmt($p['updated_at'] ?? null),
                    'actualizado_por' => $p['actualizado_por_nombre'] ?? '—',
                ]
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function getFaltantesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        
        $repo = new PlanCuentaRepository();
        $faltantes = $repo->getFaltantesNivelUno($idEmpresa);
        
        echo json_encode(['ok' => true, 'faltantes' => $faltantes]);
        exit;
    }

    public function getNextCodigoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idEmpresa   = (int) $_SESSION['id_empresa'];
        $codigoPadre = trim($_GET['padre'] ?? '');

        if (empty($codigoPadre)) {
            echo json_encode(['ok' => false, 'error' => 'Código padre requerido']);
            exit;
        }

        $repo = new PlanCuentaRepository();
        $ultimoHijo = $repo->getUltimoCodigoHijo($idEmpresa, $codigoPadre);
        
        $nivelPadre = count(explode('.', $codigoPadre));
        $nuevoNivel = $nivelPadre + 1;
        $siguienteSecuencia = 1;

        if ($ultimoHijo) {
            $partes = explode('.', $ultimoHijo);
            $siguienteSecuencia = (int)end($partes) + 1;
        }

        // Formato: N2:1.1, N3:1.1.01, N4:1.1.01.01, N5:1.1.01.01.001
        $formato = '%01d'; // Nivel 2: 1.1
        if ($nuevoNivel === 3 || $nuevoNivel === 4) $formato = '%02d'; // Nivel 3 y 4: 01
        if ($nuevoNivel === 5) $formato = '%03d'; // Nivel 5: 001

        $nuevoCodigo = $codigoPadre . '.' . sprintf($formato, $siguienteSecuencia);

        echo json_encode(['ok' => true, 'codigo' => $nuevoCodigo, 'nivel' => $nuevoNivel]);
        exit;
    }

    public function initPlanAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        
        try {
            $repo = new PlanCuentaRepository();
            $repo->beginTransaction();
            $repo->crearRaicesIniciales($idEmpresa, $idUsuario);
            
            $log = new LogSistemaService();
            $log->registrar($idUsuario, $idEmpresa, 'INICIALIZAR', 'plan_cuentas', null, null, ['msg' => 'Creadas 6 cuentas raíz']);
            
            $repo->commit();
            echo json_encode(['ok' => true, 'msg' => 'Plan de cuentas inicial creado correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function cargarModeloAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $configurar = filter_var($_POST['configurar'] ?? 'false', FILTER_VALIDATE_BOOLEAN);

        try {
            // El plan modelo solo puede cargarse como estructura inicial:
            // si la empresa ya tiene cuentas, se rechaza.
            $repo = new PlanCuentaRepository();
            if ($repo->contarPorEmpresa($idEmpresa) > 0) {
                throw new \Exception('El plan de cuentas ya tiene cuentas cargadas; el plan modelo solo puede usarse como estructura inicial.');
            }

            $resp = $this->service->cargarModelo($idEmpresa, $idUsuario, $configurar);
            echo json_encode(['ok' => true, 'msg' => $resp['message']]);
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
            echo json_encode(['ok' => true, 'msg' => 'Cuenta creada correctamente.', 'id' => $id]);
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
            if ($id <= 0) throw new \Exception('ID no válido.');
            $this->service->actualizar($id, $idEmpresa, $data);
            echo json_encode(['ok' => true, 'msg' => 'Cuenta actualizada correctamente.']);
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
            if ($id <= 0) throw new \Exception('ID no válido.');

            $repo = new PlanCuentaRepository();
            $p = $repo->findById($id, $idEmpresa);
            if (!$p) throw new \Exception('Cuenta no encontrada.');

            // Regla 1: No eliminar si es nivel 1
            if ((int)$p['nivel'] === 1) {
                throw new \Exception('No se pueden eliminar las cuentas raíz del sistema.');
            }

            // Regla 2: No eliminar si tiene hijos
            if ($repo->tieneHijos($idEmpresa, $p['codigo'])) {
                throw new \Exception('No se puede eliminar una cuenta que tiene subcuentas asociadas.');
            }

            $this->service->eliminar($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Cuenta eliminada correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function eliminarPlanAjax(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            $afectadas = $this->service->eliminarPlanCompleto($idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => "Se eliminó el plan de cuentas correctamente ({$afectadas} cuentas)."]);
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
        $ordenCol  = trim($_GET['sort'] ?? $_POST['sort'] ?? 'codigo');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? 'asc'));

        $data = $this->service->getListado($idEmpresa, $buscar, 1, 0, $ordenCol, $ordenDir);
        $rows = $data['rows'];

        try {
            $empresaModel = new \App\models\Empresa();
            $empresa = $empresaModel->getPorId($idEmpresa);
            $nombreEmpresa = $empresa['nombre'] ?? '';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) require_once $autoload;

            ob_start();
?>
            <style>
                table { width: 100%; border-collapse: collapse; font-family: Arial; font-size: 8pt; }
                th { background: #f2f2f2; border: 1px solid #ccc; padding: 6px; text-align: left; }
                td { border: 1px solid #ccc; padding: 6px; }
                page_header { text-align: center; margin-bottom: 20px; }
                h1 { margin: 0; font-size: 12pt; }
                h2 { margin: 5px 0; font-size: 10pt; color: #666; text-transform: uppercase; }
            </style>
            <page backtop="10mm" backbottom="10mm" backleft="10mm" backright="10mm">
                <page_header>
                    <h1><?= htmlspecialchars($nombreEmpresa) ?></h1>
                    <h2>Plan de Cuentas</h2>
                </page_header>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 12%">Código</th>
                            <th style="width: 38%">Nombre</th>
                            <th style="width: 8%">Nivel</th>
                            <th style="width: 8%">SRI</th>
                            <th style="width: 16%">SuperCías (ESF/ERI/ECP)</th>
                            <th style="width: 10%">Map Asiento</th>
                            <th style="width: 8%">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['codigo'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['nombre'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['nivel'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['codigo_sri'] ?? '') ?></td>
                                <td><?= htmlspecialchars(($r['supercias_esf'] ?? '') . ' / ' . ($r['supercias_eri'] ?? '') . ' / ' . ($r['supercias_ecp_codigo'] ?? '')) ?></td>
                                <td><?= htmlspecialchars($r['map_asiento'] ?? '') ?></td>
                                <td><?= ($r['status'] === 1 ? 'Activo' : 'Inactivo') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </page>
<?php
            $content = ob_get_clean();
            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es');
            $html2pdf->writeHTML($content);
            $html2pdf->output('Plan_Cuentas_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            echo "Error al generar PDF: " . $e->getMessage();
            exit;
        }
    }

    public function downloadExample(): void
    {
        $this->requireLeer();
        try {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Cabeceras
            $headers = ['Código', 'Nombre', 'Código SRI', 'Supercias ESF', 'Supercias ERI', 'Supercias ECP Cod.', 'Supercias ECP Sub.', 'Map Asiento'];
            foreach ($headers as $col => $text) {
                $sheet->setCellValueByColumnAndRow($col + 1, 1, $text);
            }
            
            $modelo = \App\Services\modulos\PlanCuentaService::getCuentasModeloArray();
            $data = [];
            foreach ($modelo as $m) {
                $data[] = [
                    (string)($m['codigo'] ?? ''),
                    (string)($m['nombre'] ?? ''),
                    (string)($m['codigo_sri'] ?? ''),
                    (string)($m['supercias_esf'] ?? ''),
                    (string)($m['supercias_eri'] ?? ''),
                    (string)($m['supercias_ecp_codigo'] ?? ''),
                    (string)($m['supercias_ecp_subcodigo'] ?? ''),
                    (string)($m['map_asiento'] ?? '')
                ];
            }
            
            foreach ($data as $rowIdx => $rowData) {
                foreach ($rowData as $colIdx => $val) {
                    $sheet->setCellValueByColumnAndRow($colIdx + 1, $rowIdx + 2, $val);
                }
            }
            
            // Formato cabecera
            $sheet->getStyle('A1:E1')->getFont()->setBold(true);
            $sheet->getColumnDimension('A')->setAutoSize(true);
            $sheet->getColumnDimension('B')->setAutoSize(true);
            
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="Ejemplo_Plan_Cuentas.xlsx"');
            header('Cache-Control: max-age=0');
            
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
            exit;
        }
    }

    public function importExcel(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');
        
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        
        if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['ok' => false, 'error' => 'Archivo no recibido o error en subida.']);
            exit;
        }
        
        try {
            $inputFileName = $_FILES['excel_file']['tmp_name'];
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($inputFileName);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            
            if (count($rows) <= 1) {
                throw new \Exception('El archivo está vacío o no contiene datos.');
            }
            
            $repo = new PlanCuentaRepository();
            $repo->beginTransaction();
            
            $importados = 0;
            // Omitir cabecera (fila 0)
            for ($i = 1; $i < count($rows); $i++) {
                $r = $rows[$i];
                // Normalizar el código al formato del sistema (N4 => 2 dígitos, N5 => 3 dígitos)
                $codigo = $this->normalizarCodigo((string)($r[0] ?? ''));
                $nombre = trim((string)($r[1] ?? ''));

                if ($codigo === '' || $nombre === '') continue;

                // Calcular nivel
                $nivel = count(explode('.', $codigo));
                
                // Uppercase para niveles 1-4
                if ($nivel >= 1 && $nivel <= 4) {
                    $nombre = mb_strtoupper($nombre);
                }
                
                // Validar si ya existe
                $existe = $repo->findByCodigo($codigo, $idEmpresa);
                if ($existe) {
                    // Opcional: Actualizar o Skip. Aquí haremos Skip para evitar errores por duplicado en importación inicial.
                    continue;
                }
                
                $data = [
                    'id_empresa'    => $idEmpresa,
                    'id_usuario'    => $idUsuario,
                    'codigo'        => $codigo,
                    'nombre'        => $nombre,
                    'nivel'         => $nivel,
                    'codigo_sri'    => trim((string)($r[2] ?? '')),
                    'supercias_esf' => trim((string)($r[3] ?? '')),
                    'supercias_eri' => trim((string)($r[4] ?? '')),
                    'supercias_ecp_codigo'    => trim((string)($r[5] ?? '')),
                    'supercias_ecp_subcodigo' => trim((string)($r[6] ?? '')),
                    'map_asiento'   => trim((string)($r[7] ?? '')),
                    'id_centro_costos' => null,
                    'id_proyecto'   => null,
                    'status'        => 1,
                    'created_by'    => $idUsuario
                ];
                
                $repo->create($data);
                $importados++;
            }
            
            $log = new LogSistemaService();
            $log->registrar($idUsuario, $idEmpresa, 'IMPORTAR', 'plan_cuentas', null, null, ['msj' => "Importadas $importados cuentas"]);
            
            $repo->commit();
            echo json_encode(['ok' => true, 'msg' => "Se han importado $importados cuentas correctamente."]);
        } catch (\Throwable $e) {
            if (isset($repo)) $repo->rollBack();
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function exportExcel(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['b'] ?? $_POST['b'] ?? '');

        $data = $this->service->getListado($idEmpresa, $buscar, 1, 0, 'codigo', 'asc');
        $rows = $data['rows'];

        try {
            $empresaModel = new \App\models\Empresa();
            $empresa = $empresaModel->getPorId($idEmpresa);
            $nombreEmpresa = $empresa['nombre'] ?? '';

            $headers = ['Código', 'Nombre', 'Nivel', 'Cod. SRI', 'SuperCías ESF', 'SuperCías ERI', 'SuperCías ECP Cod.', 'SuperCías ECP Sub.', 'Map Asiento', 'Centro Costo', 'Proyecto', 'Estado'];
            $exportData = [];
            foreach ($rows as $r) {
                $exportData[] = [
                    (string)($r['codigo'] ?? ''),
                    (string)($r['nombre'] ?? ''),
                    (string)($r['nivel'] ?? ''),
                    (string)($r['codigo_sri'] ?? ''),
                    (string)($r['supercias_esf'] ?? ''),
                    (string)($r['supercias_eri'] ?? ''),
                    (string)($r['supercias_ecp_codigo'] ?? ''),
                    (string)($r['supercias_ecp_subcodigo'] ?? ''),
                    (string)($r['map_asiento'] ?? ''),
                    (string)($r['centro_costo_nombre'] ?? '—'),
                    (string)($r['proyecto_nombre'] ?? '—'),
                    (string)($r['status'] === 1 ? 'Activo' : 'Inactivo')
                ];
            }

            $reportService = new \App\Services\ReportService();
            $reportService->exportToExcel('Plan_Cuentas', $headers, $exportData, 'Plan de Cuentas', $nombreEmpresa);
            exit;
        } catch (\Throwable $e) {
            echo "Error al generar Excel: " . $e->getMessage();
            exit;
        }
    }

    /**
     * Normaliza un código de cuenta al formato del sistema (mismo del plan modelo):
     *   N1=1, N2=1.1, N3=1.1.1, N4=1.1.1.01, N5=1.1.1.01.001
     * Es decir: segmentos de nivel 1-3 sin relleno; nivel 4 con 2 dígitos; nivel 5 con 3 dígitos.
     * Rellena con ceros a la izquierda cada segmento numérico según su posición y limpia
     * espacios / formato numérico que Excel pueda introducir. Los segmentos no numéricos
     * (casos atípicos) se conservan tal cual para no romper el código.
     */
    private function normalizarCodigo(string $codigo): string
    {
        $codigo = trim($codigo);
        if ($codigo === '') return '';

        // Anchos por posición (1-indexado). Posiciones no listadas: sin relleno.
        $anchos = [1 => 1, 2 => 1, 3 => 1, 4 => 2, 5 => 3];

        $partes = explode('.', $codigo);
        $out = [];
        foreach ($partes as $i => $parte) {
            $parte = trim($parte);
            $pos = $i + 1;

            if ($parte === '' || !ctype_digit($parte)) {
                // Segmento vacío o no numérico: se conserva sin tocar.
                $out[] = $parte;
                continue;
            }

            $num = (string)(int)$parte; // quita ceros o decimales espurios de Excel
            $ancho = $anchos[$pos] ?? strlen($num);
            $out[] = str_pad($num, $ancho, '0', STR_PAD_LEFT);
        }

        return implode('.', $out);
    }

    private function recogerDatosFormulario(): array
    {
        return [
            'codigo'            => trim($_POST['codigo'] ?? ''),
            'nombre'            => trim($_POST['nombre'] ?? ''),
            'nivel'             => trim($_POST['nivel'] ?? ''),
            'id_centro_costos'  => (int)($_POST['id_centro_costos'] ?? 0),
            'id_proyecto'       => (int)($_POST['id_proyecto'] ?? 0),
            'codigo_sri'        => trim($_POST['codigo_sri'] ?? ''),
            'supercias_esf'     => trim($_POST['supercias_esf'] ?? ''),
            'supercias_eri'     => trim($_POST['supercias_eri'] ?? ''),
            'supercias_ecp_codigo' => trim($_POST['supercias_ecp_codigo'] ?? ''),
            'supercias_ecp_subcodigo' => trim($_POST['supercias_ecp_subcodigo'] ?? ''),
            'status'            => isset($_POST['status']) && $_POST['status'] == '1' ? 1 : 2,
        ];
    }
}
