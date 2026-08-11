<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\AlumnoRepository;
use App\Rules\modulos\AlumnoRules;
use App\Services\LogSistemaService;
use App\Services\modulos\AlumnoService;
use Exception;

class AlumnosController extends BaseModuloController
{
    private const RUTA_MODULO = 'modulos/alumnos';
    private AlumnoService $service;

    public function __construct()
    {
        parent::__construct();
        $repo = new AlumnoRepository();
        $rules = new AlumnoRules();
        $logService = new LogSistemaService();
        $this->service = new AlumnoService($repo, $rules, $logService);
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
        $ordenCol = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'apellidos');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'asc'));
        $perPage  = 20;

        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows = $result['rows'];
        $total = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        // Permisos de los catálogos/módulos que se pueden crear al vuelo desde
        // la Barra de Acciones Superior del modal (mismo patrón que Factura de
        // Venta con Clientes/Productos).
        $permCampus   = $this->permisosModuloPorRuta('modulos/alumnos-campus');
        $permNiveles  = $this->permisosModuloPorRuta('modulos/alumnos-niveles');
        $permClientes = $this->permisosModuloPorRuta('modulos/clientes');

        $this->viewWithLayout('layouts.main', 'modulos.alumnos.index', [
            'titulo'      => 'Alumnos',
            'perm'        => $perm,
            'permCampus'  => $permCampus,
            'permNiveles' => $permNiveles,
            'permClientes'=> $permClientes,
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
        $ordenCol  = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'apellidos');
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

        $estadoBadges = [
            'activo'     => 'success',
            'retirado'   => 'secondary',
            'egresado'   => 'info',
            'suspendido' => 'danger',
        ];

        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-mortarboard fs-3 d-block mb-2"></i>No se encontraron alumnos.</td></tr>';
        } else {
            foreach ($rows as $r) {
                $estado = $r['estado_academico'] ?? 'activo';
                $color = $estadoBadges[$estado] ?? 'secondary';
                $badgeEstado = '<span class="badge bg-' . $color . ' bg-opacity-10 text-' . $color . ' border border-' . $color . ' border-opacity-10">' . ucfirst($estado) . '</span>';
                $badgeMatricula = !empty($r['matricula_vigente']) && in_array($r['matricula_vigente'], [true, 't', 1, '1'], true)
                    ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-10">Vigente</span>'
                    : '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-10">Sin matrícula</span>';

                $dataAttr = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                echo '<tr class="alumno-row" role="button" tabindex="0" data-row=\'' . $dataAttr . '\' onclick="abrirModalAlumnoEditar(this)">
                        <td class="ps-3 fw-bold" data-col="nombres">' . htmlspecialchars(trim(($r['apellidos'] ?? '') . ' ' . ($r['nombres'] ?? ''))) . '</td>
                        <td data-col="codigo_alumno" class="text-muted small">' . htmlspecialchars($r['codigo_alumno'] ?? '') . '</td>
                        <td data-col="campus">' . htmlspecialchars($r['campus_actual_nombre'] ?? '—') . '</td>
                        <td data-col="nivel">' . htmlspecialchars($r['nivel_actual_nombre'] ?? '—') . '</td>
                        <td data-col="representante" class="small">' . htmlspecialchars($r['representante_nombre'] ?? '—') . '</td>
                        <td class="text-center" data-col="matricula">' . $badgeMatricula . '</td>
                        <td class="text-center" data-col="estado_academico">' . $badgeEstado . '</td>
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
            'pagination'=> $paginationHtml,
            'info'      => "$from-$to/$total",
            'total'     => $total,
            'pdf_url'   => BASE_URL . '/' . self::RUTA_MODULO . '/export-pdf?b=' . urlencode($buscar) . "&sort=$ordenCol&dir=$ordenDir",
            'excel_url' => BASE_URL . '/' . self::RUTA_MODULO . '/export-excel?b=' . urlencode($buscar) . "&sort=$ordenCol&dir=$ordenDir",
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
            if ($id <= 0) throw new Exception('ID no válido.');
            $data = $this->service->getDetalle($id, $idEmpresa);
            if (!$data) throw new Exception('Alumno no encontrado.');
            $data['documentos'] = $this->formatearDocumentos($data['documentos'] ?? []);
            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
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
            echo json_encode(['ok' => true, 'msg' => 'Alumno creado correctamente.', 'id' => $id]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
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
            if ($id <= 0) throw new Exception('ID de alumno no válido.');
            $this->service->actualizar($id, $idEmpresa, $data);
            echo json_encode(['ok' => true, 'msg' => 'Alumno actualizado correctamente.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
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
            if ($id <= 0) throw new Exception('ID de alumno no válido.');
            $this->service->eliminar($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Alumno eliminado correctamente.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Typeahead de Cliente (representante que factura) — mismo patrón que
     * MayoresController::getClientesAjax.
     */
    public function getClientesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar = trim($_GET['q'] ?? '');

        $repo = new \App\repositories\modulos\ClienteRepository();
        $result = $repo->getListado($idEmpresa, $buscar, 1, 15, 'nombre', 'ASC', null, true);

        $data = array_map(fn($row) => [
            'id' => $row['id'],
            'nombre' => $row['nombre'] ?? '',
            'identificacion' => $row['identificacion'] ?? '',
        ], $result['rows'] ?? []);

        echo json_encode(['ok' => true, 'data' => $data]);
        exit;
    }

    /**
     * Typeahead de Producto/Servicio para la pestaña "Servicios y Productos".
     */
    public function getProductosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar = trim($_GET['q'] ?? '');

        $repo = new \App\repositories\modulos\ProductoRepository();
        $result = $repo->getListado($idEmpresa, $buscar, 1, 15, 'nombre', 'ASC', null);

        $data = array_map(fn($row) => [
            'id' => $row['id'],
            'nombre' => $row['nombre'] ?? '',
            'precio_base' => $row['precio_base'] ?? 0,
        ], $result['rows'] ?? []);

        echo json_encode(['ok' => true, 'data' => $data]);
        exit;
    }

    /**
     * Catálogos auxiliares del modal: tipos de identificación y puntos de emisión.
     */
    public function catalogosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];

        try {
            $modelTipos = new \App\models\IdentificadorCompradorVendedor();
            $todos = $modelTipos->getAll('codigo', 'ASC');
            $tiposId = array_values(array_filter($todos, fn($r) => (int)($r['tipo'] ?? 0) === 1 && (int)($r['status'] ?? 1) === 1));

            $puntosEmision = $this->service->getPuntosEmisionParaSelect($idEmpresa);

            $repoCampus = new \App\repositories\modulos\AlumnoCampusRepository();
            $repoNivel = new \App\repositories\modulos\AlumnoNivelRepository();

            echo json_encode([
                'ok' => true,
                'tipos_id' => $tiposId,
                'puntos_emision' => $puntosEmision,
                'campus' => $repoCampus->getParaSelect($idEmpresa),
                'niveles' => $repoNivel->getParaSelect($idEmpresa),
            ]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Sube la foto del alumno (mismo patrón que ProductosController::uploadImage).
     */
    public function uploadFotoAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        try {
            if (empty($_FILES['foto'])) throw new Exception('No se envió ninguna imagen.');

            $file = $_FILES['foto'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];

            if (!in_array($ext, $allowed, true)) throw new Exception('Formato de imagen no permitido.');
            if ($file['size'] > 2 * 1024 * 1024) throw new Exception('La imagen excede los 2MB.');

            $uploadDir = MVC_ROOT . '/public/uploads/alumnos/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $fileName = uniqid('alu_') . '.' . $ext;
            $fullPath = $uploadDir . $fileName;

            if (move_uploaded_file($file['tmp_name'], $fullPath)) {
                echo json_encode(['ok' => true, 'path' => 'uploads/alumnos/' . $fileName]);
            } else {
                throw new Exception('Error al mover el archivo al servidor.');
            }
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function subirDocumentoAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $idAlumno = (int) ($_POST['id_alumno'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $tipoDocumento = trim($_POST['tipo_documento'] ?? '');

        try {
            if ($idAlumno <= 0) throw new Exception('Primero guarde el alumno antes de adjuntar documentos.');
            if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('No se recibió ningún archivo válido.');
            }
            $file = $_FILES['archivo'];
            if ($file['size'] > 5 * 1024 * 1024) {
                throw new Exception('El archivo excede los 5MB.');
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $permitidas = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
            if (!in_array($ext, $permitidas, true)) {
                throw new Exception('Tipo de archivo no permitido. Use PDF, JPG, PNG o WEBP.');
            }

            $uploadDir = MVC_ROOT . '/public/uploads/alumnos_documentos/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $fileName = uniqid('alu_doc_') . '.' . $ext;
            $fullPath = $uploadDir . $fileName;

            if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
                throw new Exception('Error al mover el archivo al servidor.');
            }

            $documentos = $this->service->agregarDocumento($idAlumno, $idEmpresa, [
                'tipo_documento' => $tipoDocumento,
                'nombre_archivo' => $file['name'],
                'ruta_archivo'   => 'uploads/alumnos_documentos/' . $fileName,
            ], $idUsuario);

            echo json_encode(['ok' => true, 'msg' => 'Documento adjuntado correctamente.', 'documentos' => $this->formatearDocumentos($documentos)]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function eliminarDocumentoAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $idDocumento = (int) ($_POST['id_documento'] ?? 0);
        $idAlumno = (int) ($_POST['id_alumno'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            if ($idDocumento <= 0 || $idAlumno <= 0) throw new Exception('Datos no válidos.');
            $documentos = $this->service->eliminarDocumento($idDocumento, $idAlumno, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Documento eliminado correctamente.', 'documentos' => $this->formatearDocumentos($documentos)]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Formatea fecha_carga a d-m-Y H:i:s (CLAUDE.md §9) antes de enviarla al cliente.
     */
    private function formatearDocumentos(array $documentos): array
    {
        foreach ($documentos as &$d) {
            if (!empty($d['fecha_carga'])) {
                $d['fecha_carga'] = date('d-m-Y H:i:s', strtotime($d['fecha_carga']));
            }
        }
        unset($d);
        return $documentos;
    }

    private function recogerDatosFormulario(): array
    {
        $periodos = [];
        if (!empty($_POST['periodos_json'])) {
            $periodos = json_decode($_POST['periodos_json'], true) ?: [];
        }
        $horarios = [];
        if (!empty($_POST['horarios_json'])) {
            $horarios = json_decode($_POST['horarios_json'], true) ?: [];
        }
        $servicios = [];
        if (!empty($_POST['servicios_json'])) {
            $servicios = json_decode($_POST['servicios_json'], true) ?: [];
        }

        return [
            'codigo_alumno'                => trim($_POST['codigo_alumno'] ?? ''),
            'nombres'                      => trim($_POST['nombres'] ?? ''),
            'apellidos'                    => trim($_POST['apellidos'] ?? ''),
            'tipo_identificacion'          => trim($_POST['tipo_identificacion'] ?? ''),
            'numero_identificacion'        => trim($_POST['numero_identificacion'] ?? ''),
            'fecha_nacimiento'             => trim($_POST['fecha_nacimiento'] ?? ''),
            'sexo'                         => trim($_POST['sexo'] ?? ''),
            'nacionalidad'                 => trim($_POST['nacionalidad'] ?? ''),
            'foto_ruta'                    => trim($_POST['foto_ruta'] ?? ''),
            'estado_academico'             => trim($_POST['estado_academico'] ?? 'activo'),
            'id_cliente'                   => (int) ($_POST['id_cliente'] ?? 0),
            'relacion_representante'       => trim($_POST['relacion_representante'] ?? ''),
            'id_punto_emision'             => (int) ($_POST['id_punto_emision'] ?? 0),
            'tipo_sangre'                  => trim($_POST['tipo_sangre'] ?? ''),
            'alergias_condiciones'         => trim($_POST['alergias_condiciones'] ?? ''),
            'contacto_emergencia_nombre'   => trim($_POST['contacto_emergencia_nombre'] ?? ''),
            'contacto_emergencia_telefono' => trim($_POST['contacto_emergencia_telefono'] ?? ''),
            'observaciones'                => trim($_POST['observaciones'] ?? ''),
            'periodos'                     => $periodos,
            'horarios'                     => $horarios,
            'servicios'                    => $servicios,
        ];
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $ordenCol  = trim($_GET['sort'] ?? $_POST['sort'] ?? 'apellidos');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? 'asc'));

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $data = $this->service->getListado($idEmpresa, $buscar, 1, 0, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows = $data['rows'];

        try {
            $empresaModel = new \App\models\Empresa();
            $empresa = $empresaModel->getPorId($idEmpresa);
            $nombreEmpresa = $empresa['nombre'] ?? 'REPORTE DE ALUMNOS';

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
                    <h2>Listado de Alumnos</h2>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 25%">Alumno</th>
                            <th style="width: 15%">Código</th>
                            <th style="width: 15%">Campus</th>
                            <th style="width: 15%">Nivel</th>
                            <th style="width: 20%">Representante</th>
                            <th style="width: 10%">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars(trim(($r['apellidos'] ?? '') . ' ' . ($r['nombres'] ?? ''))) ?></td>
                                <td><?= htmlspecialchars((string)($r['codigo_alumno'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)($r['campus_actual_nombre'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)($r['nivel_actual_nombre'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)($r['representante_nombre'] ?? '')) ?></td>
                                <td><?= htmlspecialchars(ucfirst((string)($r['estado_academico'] ?? ''))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </page>
<?php
            $content = ob_get_clean();

            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $html2pdf->writeHTML($content);
            $html2pdf->output('Alumnos_' . date('Ymd_His') . '.pdf', 'D');
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
        $ordenCol  = trim($_GET['sort'] ?? $_POST['sort'] ?? 'apellidos');
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

            $headers = ['Alumno', 'Código', 'Identificación', 'Campus', 'Nivel/Curso', 'Representante', 'Matrícula', 'Estado'];
            $exportData = [];
            foreach ($rows as $r) {
                $exportData[] = [
                    trim(($r['apellidos'] ?? '') . ' ' . ($r['nombres'] ?? '')),
                    (string)($r['codigo_alumno'] ?? ''),
                    (string)($r['numero_identificacion'] ?? ''),
                    (string)($r['campus_actual_nombre'] ?? ''),
                    (string)($r['nivel_actual_nombre'] ?? ''),
                    (string)($r['representante_nombre'] ?? ''),
                    !empty($r['matricula_vigente']) && in_array($r['matricula_vigente'], [true, 't', 1, '1'], true) ? 'Vigente' : 'Sin matrícula',
                    ucfirst((string)($r['estado_academico'] ?? '')),
                ];
            }

            $reportService = new \App\Services\ReportService();
            $reportService->exportToExcel('Alumnos', $headers, $exportData, 'Listado de Alumnos', $nombreEmpresa);
            exit;
        } catch (\Throwable $e) {
            header('Content-Type: text/html');
            echo "Error al generar Excel: " . $e->getMessage();
            exit;
        }
    }
}
