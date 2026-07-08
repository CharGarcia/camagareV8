<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\EmpleadoRepository;
use App\Rules\modulos\EmpleadoRules;
use App\Services\LogSistemaService;
use App\Services\modulos\EmpleadoService;
use App\Services\modulos\EmpleadoImportService;
use App\Services\SriIdentificationService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Services\modulos\EmpleadoPdfService;
use App\models\BancoEcuador;
use App\models\Empresa;
use App\models\Usuario;

class EmpleadosController extends BaseModuloController
{
    private EmpleadoService $service;
    private const RUTA_MODULO = 'modulos/empleados';

    public function __construct()
    {
        parent::__construct();
        $repository = new EmpleadoRepository();
        $rules = new EmpleadoRules();
        $logService = new LogSistemaService();
        $this->service = new EmpleadoService($repository, $rules, $logService);
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

        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);

        $buscar   = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'nombres_apellidos');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'ASC'));
        $perPage  = 20;

        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows   = $result['rows'];
        $total  = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        // Cargar bancos para el modal
        $bancoModel = new BancoEcuador();
        // Usamos el id de la tabla bancos_ecuador
        $bancos = $bancoModel->getAll('nombre_banco', 'ASC');

        $this->viewWithLayout('layouts.main', 'modulos.empleados.index', [
            'titulo'     => 'Empleados',
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
            'bancos'     => $bancos,
            'vistaConfig'=> $prefsVista,
            'fullWidth'  => true,
            'idEmpresa'  => $idEmpresa
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
        $ordenCol  = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'nombres_apellidos');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'ASC'));
        $perPage   = 20;

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows   = $result['rows'];
        $total  = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to   = $total > 0 ? min($page * $perPage, $total) : 0;

        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="7" class="text-center py-5 text-muted">No se encontraron empleados.</td></tr>';
        } else {
            foreach ($rows as $r) {
                $dataJson = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                $statusBadge = ($r['estado'] === 'activo')
                    ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Activo</span>'
                    : '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Inactivo</span>';

                echo '<tr class="empleado-row" role="button" data-row=\'' . $dataJson . '\' onclick="abrirModalEditar(this)">
                        <td class="ps-3" data-col="identificacion"><code class="text-secondary">' . htmlspecialchars($r['identificacion']) . '</code></td>
                        <td class="fw-medium" data-col="nombres_apellidos">' . htmlspecialchars($r['nombres_apellidos']) . '</td>
                        <td data-col="email">' . htmlspecialchars($r['email'] ?? '—') . '</td>
                        <td data-col="telefono">' . htmlspecialchars($r['telefono'] ?? '—') . '</td>
                        <td class="text-center" data-col="sexo">' . htmlspecialchars($r['sexo'] ?? '—') . '</td>
                        <td data-col="fecha_nacimiento">' . htmlspecialchars($r['fecha_nacimiento'] ?? '—') . '</td>
                        <td data-col="direccion" class="small text-muted">' . htmlspecialchars($r['direccion'] ?? '—') . '</td>
                        <td data-col="cargo">' . htmlspecialchars($r['cargo'] ?? '—') . '</td>
                        <td data-col="departamento">' . htmlspecialchars($r['departamento'] ?? '—') . '</td>
                        <td data-col="sueldo_base" class="text-end fw-bold">$' . number_format((float)($r['sueldo_base'] ?? 0), 2) . '</td>
                        <td data-col="valor_semanal" class="text-end">$' . number_format((float)($r['valor_semanal'] ?? 0), 2) . '</td>
                        <td data-col="valor_quincena" class="text-end">$' . number_format((float)($r['valor_quincena'] ?? 0), 2) . '</td>
                        <td data-col="region" class="text-capitalize">' . htmlspecialchars($r['region'] ?? '—') . '</td>
                        <td data-col="nombre_banco">' . htmlspecialchars($r['nombre_banco'] ?? '—') . '</td>
                        <td data-col="tipo_cuenta" class="text-capitalize">' . htmlspecialchars($r['tipo_cuenta'] ?? '—') . '</td>
                        <td data-col="numero_cuenta">' . htmlspecialchars($r['numero_cuenta'] ?? '—') . '</td>
                        <td class="text-center" data-col="tipo_id"><span class="small text-muted">' . htmlspecialchars($r['tipo_id']) . '</span></td>
                        <td class="text-center" data-col="estado">' . $statusBadge . '</td>
                        <td class="text-center pe-3" onclick="event.stopPropagation()">
                             <button class="btn btn-outline-danger btn-xs border-0 px-2" onclick="eliminarRegistro(' . $r['id'] . ')" title="Eliminar"><i class="bi bi-trash"></i></button>
                        </td>
                      </tr>';
            }
        }
        $rowsHtml = ob_get_clean();

        ob_start();
        $prevDisabled = ($page <= 1) ? 'disabled' : '';
        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
        echo '<div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" ' . $prevDisabled . ' onclick="cambiarPaginaAjax(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" ' . $nextDisabled . ' onclick="cambiarPaginaAjax(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>
              </div>';
        $paginationHtml = ob_get_clean();

        echo json_encode([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'pagination' => $paginationHtml,
            'info'       => "$from-$to/$total",
            'total'      => $total,
        ]);
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
            echo json_encode(['ok' => true, 'msg' => 'Empleado creado correctamente.', 'id' => $id]);
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
            if ($id <= 0) throw new \Exception('ID no válido.');
            $this->service->actualizar($id, $idEmpresa, $data);
            echo json_encode(['ok' => true, 'msg' => 'Empleado actualizado correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function delete(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        $id = (int) ($_POST['id_eliminar'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            if ($id <= 0) throw new \Exception('ID no válido.');
            $this->service->eliminar($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Empleado eliminado correctamente.']);
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
        $ordenCol  = trim($_GET['sort'] ?? $_POST['sort'] ?? 'nombres_apellidos');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? 'ASC'));

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $data = $this->service->getListado($idEmpresa, $buscar, 1, 0, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows = $data['rows'];

        try {
            // Lógica simple de PDF (puedes expandirla con Html2Pdf como en PlanCuentas)
            echo "Exportando PDF para Empleados... (Se requiere librería Html2Pdf)";
            exit;
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
            exit;
        }
    }

    public function exportExcel(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['b'] ?? $_POST['b'] ?? '');

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $data = $this->service->getListado($idEmpresa, $buscar, 1, 0, 'nombres_apellidos', 'ASC', $idUsuarioFiltro);
        $rows = $data['rows'];

        try {
            $headers = ['ID', 'Nombres y Apellidos', 'Email', 'Teléfono', 'Tipo ID', 'Estado', 'Banco', 'Tipo Cuenta', 'Número Cuenta'];
            $exportData = [];
            foreach ($rows as $r) {
                $exportData[] = [
                    $r['identificacion'],
                    $r['nombres_apellidos'],
                    $r['email'],
                    $r['telefono'],
                    $r['tipo_id'],
                    $r['estado'],
                    $r['id_banco_ecuador'],
                    $r['tipo_cuenta'],
                    $r['numero_cuenta']
                ];
            }

            $reportService = new \App\Services\ReportService();
            $reportService->exportToExcel('Empleados', $headers, $exportData, 'Listado de Empleados', 'Empresa ID ' . $idEmpresa);
            exit;
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
            exit;
        }
    }

    public function getDetalleAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id = (int)($_GET['id'] ?? 0);
        $idEmpresa = (int)$_SESSION['id_empresa'];

        $data = $this->service->getDetalle($id, $idEmpresa);
        if (!$data) {
            echo json_encode(['ok' => false, 'error' => 'No encontrado']);
        } else {
            echo json_encode(['ok' => true, 'data' => $data]);
        }
        exit;
    }

    public function getIessDefaults(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $defaults = $this->service->getIessDefaults();
        echo json_encode(['ok' => true, 'data' => $defaults]);
        exit;
    }

    /**
     * Consulta los datos de una cédula/identificación al SRI para autocompletar
     * el nombre del empleado. Reutiliza el servicio global SriIdentificationService.
     */
    public function consultarSri(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json; charset=utf-8');

        $identificacion = trim($_POST['identificacion'] ?? $_GET['identificacion'] ?? '');
        if ($identificacion === '') {
            echo json_encode(['ok' => false, 'error' => 'Identificación vacía.']);
            exit;
        }

        try {
            $svc    = new SriIdentificationService();
            $result = $svc->consultar($identificacion);
            echo json_encode($result);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Imprime la ficha del empleado en PDF (A4).
     */
    public function imprimirPdf(): void
    {
        $this->requireLeer();

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        if ($id <= 0) { http_response_code(400); echo 'ID requerido'; exit; }

        try {
            $emp = $this->service->getDetalle($id, $idEmpresa);
            if (!$emp) { http_response_code(404); echo 'Empleado no encontrado'; exit; }

            // Resolver nombre del banco (findById no hace join).
            if (!empty($emp['id_banco_ecuador'])) {
                $bancos = (new BancoEcuador())->getAll('nombre_banco', 'ASC');
                foreach ($bancos as $b) {
                    if ((int)$b['id'] === (int)$emp['id_banco_ecuador']) {
                        $emp['nombre_banco'] = $b['nombre_banco'];
                        break;
                    }
                }
            }

            $empresa = $this->cargarEmpresaParaPdf($idEmpresa);
            (new EmpleadoPdfService())->generar($emp, $empresa, 'D');
        } catch (\Throwable $e) {
            http_response_code(500);
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
        exit;
    }

    /** Datos de la empresa (con logo del establecimiento) para el PDF. */
    private function cargarEmpresaParaPdf(int $idEmpresa): array
    {
        $empresaModel = new Empresa();
        $empresa      = $empresaModel->getPorId($idEmpresa) ?? [];
        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        if (!empty($establecimientos[0]['logo_ruta'])) {
            $empresa['logo_ruta'] = $establecimientos[0]['logo_ruta'];
        }
        return $empresa;
    }

    /** Descarga la plantilla Excel para importar empleados. */
    public function plantillaExcel(): void
    {
        $this->requireCrear();
        $ss = new Spreadsheet();
        $hoja = $ss->getActiveSheet();
        $hoja->setTitle('Empleados');
        $headers = [
            'TIPO_ID', 'IDENTIFICACION', 'NOMBRES_APELLIDOS', 'EMAIL', 'TELEFONO', 'DIRECCION',
            'FECHA_NACIMIENTO', 'SEXO', 'CARGO', 'DEPARTAMENTO', 'SUELDO_BASE', 'VALOR_SEMANAL',
            'VALOR_QUINCENA', 'REGION', 'APORTA_IESS', 'FONDOS_RESERVA', 'DECIMO_TERCERO',
            'DECIMO_CUARTO', 'BANCO', 'TIPO_CUENTA', 'NUMERO_CUENTA', 'FECHA_INGRESO',
        ];
        $hoja->fromArray($headers, null, 'A1');
        $hoja->getStyle('A1:V1')->getFont()->setBold(true);
        $hoja->fromArray([[
            'cedula', '1717136574', 'JUAN PEREZ', 'juan@correo.com', '0999999999', 'Av. Siempre Viva',
            '1990-05-20', 'M', 'VENDEDOR', 'VENTAS', 460, 0, 0, 'costa', 'si', 'no_se_paga',
            'acumula', 'acumula', 'PICHINCHA', 'ahorros', '2200123456', '2020-03-01',
        ]], null, 'A2');
        foreach (range('A', 'V') as $col) $hoja->getColumnDimension($col)->setAutoSize(true);

        // Hoja de referencia con valores válidos
        $ref = $ss->createSheet();
        $ref->setTitle('Referencia');
        $ref->fromArray([
            ['CAMPO', 'VALORES VÁLIDOS'],
            ['TIPO_ID', 'cedula, pasaporte'],
            ['SEXO', 'M, F, O'],
            ['REGION', 'costa, sierra, oriente, insular'],
            ['APORTA_IESS', 'si, no'],
            ['FONDOS_RESERVA', 'rol, planilla, no_se_paga'],
            ['DECIMO_TERCERO', 'mensualiza, acumula'],
            ['DECIMO_CUARTO', 'mensualiza, acumula'],
            ['TIPO_CUENTA', 'ahorros, corriente, virtual'],
            ['BANCO', 'Nombre exacto del banco (ver módulo Bancos)'],
            ['FECHA_NACIMIENTO', 'Formato AAAA-MM-DD'],
            ['FECHA_INGRESO', 'Formato AAAA-MM-DD (crea el periodo laboral)'],
        ], null, 'A1');
        $ref->getStyle('A1:B1')->getFont()->setBold(true);
        $ref->getColumnDimension('A')->setAutoSize(true);
        $ref->getColumnDimension('B')->setAutoSize(true);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="plantilla_empleados.xlsx"');
        header('Cache-Control: max-age=0');
        (new Xlsx($ss))->save('php://output');
        exit;
    }

    /** Procesa la carga masiva de empleados desde Excel. */
    public function importarExcel(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');
        try {
            if (empty($_FILES['archivo']['tmp_name']) || !is_uploaded_file($_FILES['archivo']['tmp_name'])) {
                throw new \Exception('No se recibió ningún archivo.');
            }
            $ext = strtolower(pathinfo($_FILES['archivo']['name'] ?? '', PATHINFO_EXTENSION));
            if (!in_array($ext, ['xlsx', 'xls'], true)) {
                throw new \Exception('El archivo debe ser Excel (.xlsx o .xls).');
            }
            $import = new EmpleadoImportService($this->service, \App\core\Database::getConnection());
            $res = $import->procesar($_FILES['archivo']['tmp_name'], (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']);
            echo json_encode(['ok' => true] + $res);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    private function recogerDatosFormulario(): array
    {
        // Campos principales
        $data = [
            'tipo_id'               => trim($_POST['tipo_id'] ?? ''),
            'identificacion'        => trim($_POST['identificacion'] ?? ''),
            'nombres_apellidos'     => trim($_POST['nombres_apellidos'] ?? ''),
            'direccion'             => trim($_POST['direccion'] ?? ''),
            'email'                 => trim($_POST['email'] ?? ''),
            'telefono'              => trim($_POST['telefono'] ?? ''),
            'contacto_emergencia'    => trim($_POST['contacto_emergencia'] ?? ''),
            'fecha_nacimiento'      => trim($_POST['fecha_nacimiento'] ?? ''),
            'sexo'                  => trim($_POST['sexo'] ?? 'M'),
            'estado'                => trim($_POST['estado'] ?? 'activo'),
            'id_banco_ecuador'      => (int)($_POST['id_banco_ecuador'] ?? 0),
            'tipo_cuenta'           => trim($_POST['tipo_cuenta'] ?? ''),
            'numero_cuenta'         => trim($_POST['numero_cuenta'] ?? ''),
            'fondos_reserva'        => trim($_POST['fondos_reserva'] ?? 'no_se_paga'),
            'aporta_iess'           => trim($_POST['aporta_iess'] ?? 'si'),
            'decimo_tercero'        => trim($_POST['decimo_tercero'] ?? 'acumula'),
            'decimo_cuarto'         => trim($_POST['decimo_cuarto'] ?? 'acumula'),
            'aporte_personal'       => (float)($_POST['aporte_personal'] ?? 0),
            'aporte_patronal'       => (float)($_POST['aporte_patronal'] ?? 0),
            'sueldo_base'           => (float)($_POST['sueldo_base'] ?? 0),
            'valor_semanal'         => (float)($_POST['valor_semanal'] ?? 0),
            'valor_quincena'        => (float)($_POST['valor_quincena'] ?? 0),
            'region'                => trim($_POST['region'] ?? 'costa'),
            'cargo'                 => trim($_POST['cargo'] ?? ''),
            'lugar_trabajo'         => trim($_POST['lugar_trabajo'] ?? ''),
            'horario_trabajo'       => trim($_POST['horario_trabajo'] ?? ''),
            'departamento'          => trim($_POST['departamento'] ?? ''),
            'codigo_sectorial_iess'  => trim($_POST['codigo_sectorial_iess'] ?? ''),
        ];

        // Manejo de arrays dinámicos (vienen como JSON desde el frontend para JS a servidor)
        if (!empty($_POST['periodos_json'])) {
            $data['periodos'] = json_decode($_POST['periodos_json'], true);
        }
        if (!empty($_POST['rubros_json'])) {
            $data['rubros_fijos'] = json_decode($_POST['rubros_json'], true);
        }

        return $data;
    }
}
