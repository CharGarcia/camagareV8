<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\AsistenciaPuntoRepository;
use App\repositories\modulos\BiometriaRepository;
use App\repositories\modulos\MarcacionRepository;
use App\repositories\modulos\EmpleadoRepository;
use App\repositories\modulos\AsistenciaHorarioRepository;
use App\repositories\modulos\AsistenciaJornadaRepository;
use App\repositories\modulos\AsistenciaConfigRepository;
use App\repositories\modulos\NovedadRepository;
use App\Rules\modulos\AsistenciaPuntoRules;
use App\Rules\modulos\MarcacionRules;
use App\Rules\modulos\AsistenciaHorarioRules;
use App\Rules\modulos\NovedadRules;
use App\Services\LogSistemaService;
use App\Services\modulos\AsistenciaPuntoService;
use App\Services\modulos\MarcacionService;
use App\Services\modulos\BiometriaService;
use App\Services\modulos\AsistenciaHorarioService;
use App\Services\modulos\JornadaService;
use App\Services\modulos\NovedadService;
use App\Services\modulos\GeneracionNovedadesService;
use App\models\CatalogoNovedades;

/**
 * Panel del módulo Control de Asistencia.
 *
 * Landing = Puntos de servicio (donde se genera el QR de ubicación).
 * Acción secundaria = Marcaciones (bitácora operativa, solo lectura por ahora).
 */
class ControlAsistenciaController extends BaseModuloController
{
    private AsistenciaPuntoService $puntoService;
    private MarcacionService $marcacionService;
    private BiometriaService $biometriaService;
    private AsistenciaHorarioService $horarioService;
    private JornadaService $jornadaService;
    private AsistenciaConfigRepository $configRepo;
    private GeneracionNovedadesService $generacionService;
    private const RUTA_MODULO = 'modulos/control-asistencia';

    public function __construct()
    {
        parent::__construct();
        $log = new LogSistemaService();
        $this->puntoService = new AsistenciaPuntoService(new AsistenciaPuntoRepository(), new AsistenciaPuntoRules(), $log);
        $this->marcacionService = new MarcacionService(
            new MarcacionRepository(),
            new BiometriaRepository(),
            new AsistenciaPuntoRepository(),
            new MarcacionRules(),
            $log
        );
        $this->biometriaService = new BiometriaService(new BiometriaRepository(), $log);
        $this->horarioService = new AsistenciaHorarioService(new AsistenciaHorarioRepository(), new AsistenciaHorarioRules(), $log);
        $this->jornadaService = new JornadaService(
            new AsistenciaJornadaRepository(),
            new MarcacionRepository(),
            new AsistenciaHorarioRepository(),
            $log
        );
        $this->configRepo = new AsistenciaConfigRepository();
        $this->generacionService = new GeneracionNovedadesService(
            new AsistenciaJornadaRepository(),
            $this->configRepo,
            new NovedadRepository(),
            new NovedadService(new NovedadRepository(), new NovedadRules(), $log)
        );
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    // ==================================================================
    // PUNTOS DE SERVICIO (listado principal)
    // ==================================================================

    public function index(): void
    {
        $this->requireLeer();

        $perm      = $this->getPermisos();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $buscar   = trim($_GET['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? 'nombre');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? 'ASC'));
        $perPage  = 20;

        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;
        $result     = $this->puntoService->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $totalPages = $perPage > 0 ? (int) ceil($result['total'] / $perPage) : 1;

        $this->viewWithLayout('layouts.main', 'modulos.control_asistencia.index', [
            'titulo'     => 'Control de Asistencia — Puntos de servicio',
            'perm'       => $perm,
            'rutaModulo' => self::RUTA_MODULO,
            'rows'       => $result['rows'],
            'total'      => $result['total'],
            'page'       => $page,
            'totalPages' => $totalPages,
            'perPage'    => $perPage,
            'buscar'     => $buscar,
            'ordenCol'   => $ordenCol,
            'ordenDir'   => $ordenDir,
            'idEmpresa'  => $idEmpresa,
        ]);
    }

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['b'] ?? '');
        $page      = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol  = trim($_GET['sort'] ?? 'nombre');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? 'ASC'));
        $perPage   = 20;

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $result     = $this->puntoService->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $total      = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to   = $total > 0 ? min($page * $perPage, $total) : 0;

        ob_start();
        if (empty($result['rows'])) {
            echo '<tr><td colspan="7" class="text-center py-5 text-muted">No hay puntos de servicio registrados.</td></tr>';
        } else {
            foreach ($result['rows'] as $r) {
                echo $this->renderFilaPunto($r, $perm);
            }
        }
        $rowsHtml = ob_get_clean();

        $prevDisabled = ($page <= 1) ? 'disabled' : '';
        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
        $paginationHtml = '<div class="btn-group btn-group-sm">'
            . '<button type="button" class="btn btn-outline-secondary" ' . $prevDisabled . ' onclick="cambiarPaginaAjax(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>'
            . '<button type="button" class="btn btn-outline-secondary" ' . $nextDisabled . ' onclick="cambiarPaginaAjax(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>'
            . '</div>';

        echo json_encode([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'pagination' => $paginationHtml,
            'info'       => "$from-$to/$total",
            'total'      => $total,
        ]);
        exit;
    }

    private function renderFilaPunto(array $r, array $perm): string
    {
        $h = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
        $dataJson = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');

        $activo = ($r['estado'] ?? 'activo') === 'activo';
        $estadoBadge = $activo
            ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Activo</span>'
            : '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Inactivo</span>';
        $gpsBadge = !empty($r['exige_gps'])
            ? '<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25"><i class="bi bi-geo-alt me-1"></i>Sí</span>'
            : '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">No</span>';

        $btnDel = $perm['eliminar']
            ? '<button class="btn btn-outline-danger btn-xs border-0 px-2" onclick="event.stopPropagation();eliminarPunto(' . (int) $r['id'] . ')" title="Eliminar"><i class="bi bi-trash"></i></button>'
            : '';

        return '<tr class="punto-row" role="button" data-row=\'' . $dataJson . '\' onclick="abrirModalEditarPunto(this)">'
            . '<td class="ps-3 fw-medium" data-col="nombre">' . $h($r['nombre']) . '</td>'
            . '<td data-col="direccion" class="small text-muted">' . $h($r['direccion'] ?? '—') . '</td>'
            . '<td class="text-center" data-col="radio">' . (int) $r['radio_m'] . ' m</td>'
            . '<td class="text-center" data-col="gps">' . $gpsBadge . '</td>'
            . '<td class="text-center" data-col="estado">' . $estadoBadge . '</td>'
            . '<td class="text-center" data-col="qr" onclick="event.stopPropagation()">'
            . '<button class="btn btn-outline-primary btn-xs px-2" onclick="verQrPunto(' . (int) $r['id'] . ')" title="Ver / imprimir QR"><i class="bi bi-qr-code"></i> QR</button>'
            . '</td>'
            . '<td class="text-center pe-3" onclick="event.stopPropagation()">' . $btnDel . '</td>'
            . '</tr>';
    }

    public function store(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        $data = $this->recogerDatosPunto();
        $data['id_empresa'] = (int) $_SESSION['id_empresa'];
        $data['id_usuario'] = (int) $_SESSION['id_usuario'];

        try {
            $id = $this->puntoService->crear($data);
            echo json_encode(['ok' => true, 'msg' => 'Punto de servicio creado.', 'id' => $id]);
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
        $data = $this->recogerDatosPunto();
        $data['id_usuario'] = (int) $_SESSION['id_usuario'];

        try {
            if ($id <= 0) throw new \Exception('ID no válido.');
            $this->puntoService->actualizar($id, $idEmpresa, $data);
            echo json_encode(['ok' => true, 'msg' => 'Punto de servicio actualizado.']);
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
            $this->puntoService->eliminar($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Punto de servicio eliminado.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Devuelve los datos del punto (incluye su qr_token) para armar el QR. */
    public function getDetalleAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $data = $this->puntoService->getDetalle($id, $idEmpresa);
        echo json_encode($data ? ['ok' => true, 'data' => $data] : ['ok' => false, 'error' => 'No encontrado']);
        exit;
    }

    /** Regenera el QR del punto (revoca el anterior). */
    public function regenerarQr(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $id = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            if ($id <= 0) throw new \Exception('ID no válido.');
            $token = $this->puntoService->regenerarQr($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'qr_token' => $token, 'msg' => 'QR regenerado.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ==================================================================
    // MARCACIONES (bitácora — solo lectura por ahora)
    // ==================================================================

    public function marcaciones(): void
    {
        $this->requireLeer();

        $perm      = $this->getPermisos();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $buscar   = trim($_GET['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? 'fecha_hora');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? 'DESC'));
        $perPage  = 20;

        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;
        $result     = $this->marcacionService->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $totalPages = $perPage > 0 ? (int) ceil($result['total'] / $perPage) : 1;

        $this->viewWithLayout('layouts.main', 'modulos.control_asistencia.marcaciones', [
            'titulo'     => 'Control de Asistencia — Marcaciones',
            'perm'       => $perm,
            'rutaModulo' => self::RUTA_MODULO,
            'rows'       => $result['rows'],
            'total'      => $result['total'],
            'page'       => $page,
            'totalPages' => $totalPages,
            'perPage'    => $perPage,
            'buscar'     => $buscar,
            'ordenCol'   => $ordenCol,
            'ordenDir'   => $ordenDir,
            'idEmpresa'  => $idEmpresa,
        ]);
    }

    public function marcacionesSearchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['b'] ?? '');
        $page      = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol  = trim($_GET['sort'] ?? 'fecha_hora');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? 'DESC'));
        $perPage   = 20;

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $result     = $this->marcacionService->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $total      = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to   = $total > 0 ? min($page * $perPage, $total) : 0;

        ob_start();
        if (empty($result['rows'])) {
            echo '<tr><td colspan="7" class="text-center py-5 text-muted">No hay marcaciones registradas.</td></tr>';
        } else {
            foreach ($result['rows'] as $r) {
                echo $this->renderFilaMarcacion($r, $perm);
            }
        }
        $rowsHtml = ob_get_clean();

        $prevDisabled = ($page <= 1) ? 'disabled' : '';
        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
        $paginationHtml = '<div class="btn-group btn-group-sm">'
            . '<button type="button" class="btn btn-outline-secondary" ' . $prevDisabled . ' onclick="cambiarPaginaAjax(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>'
            . '<button type="button" class="btn btn-outline-secondary" ' . $nextDisabled . ' onclick="cambiarPaginaAjax(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>'
            . '</div>';

        echo json_encode([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'pagination' => $paginationHtml,
            'info'       => "$from-$to/$total",
            'total'      => $total,
        ]);
        exit;
    }

    private function renderFilaMarcacion(array $r, array $perm): string
    {
        $h = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');

        $fecha = $r['fecha_hora'] ? date('d-m-Y H:i:s', strtotime((string) $r['fecha_hora'])) : '—';

        $tipoMap = ['entrada' => 'success', 'salida' => 'danger', 'inicio_break' => 'warning', 'fin_break' => 'info'];
        $tipoColor = $tipoMap[$r['tipo']] ?? 'secondary';
        $tipoBadge = '<span class="badge bg-' . $tipoColor . ' bg-opacity-10 text-' . $tipoColor . ' border border-' . $tipoColor . ' border-opacity-25">' . $h(ucfirst(str_replace('_', ' ', (string) $r['tipo']))) . '</span>';

        $estado = $r['estado'] ?? 'valida';
        $estadoMap = ['valida' => 'success', 'sospechosa' => 'warning', 'anulada' => 'secondary'];
        $estadoColor = $estadoMap[$estado] ?? 'secondary';
        $estadoBadge = '<span class="badge bg-' . $estadoColor . ' bg-opacity-10 text-' . $estadoColor . ' border border-' . $estadoColor . ' border-opacity-25">' . $h(ucfirst($estado)) . '</span>';

        $dist = $r['distancia_m'] !== null ? (int) $r['distancia_m'] . ' m' : '—';

        $btnDel = $perm['eliminar']
            ? '<button class="btn btn-outline-danger btn-xs border-0 px-2" onclick="eliminarMarcacion(' . (int) $r['id'] . ')" title="Eliminar"><i class="bi bi-trash"></i></button>'
            : '';

        return '<tr>'
            . '<td class="ps-3 fw-medium" data-col="empleado">' . $h($r['empleado_nombre']) . '</td>'
            . '<td data-col="punto" class="small text-muted">' . $h($r['punto_nombre'] ?? '—') . '</td>'
            . '<td data-col="fecha">' . $h($fecha) . '</td>'
            . '<td class="text-center" data-col="tipo">' . $tipoBadge . '</td>'
            . '<td class="text-center" data-col="distancia">' . $dist . '</td>'
            . '<td class="text-center" data-col="estado">' . $estadoBadge . '</td>'
            . '<td class="text-center pe-3">' . $btnDel . '</td>'
            . '</tr>';
    }

    public function deleteMarcacion(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        $id = (int) ($_POST['id_eliminar'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            if ($id <= 0) throw new \Exception('ID no válido.');
            $this->marcacionService->eliminar($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Marcación eliminada.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ==================================================================
    // CREDENCIALES DE EMPLEADOS (QR personal)
    // ==================================================================

    public function empleados(): void
    {
        $this->requireLeer();

        $perm      = $this->getPermisos();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['b'] ?? '');
        $page      = max(1, (int) ($_GET['page'] ?? 1));
        $perPage   = 20;

        $result  = (new EmpleadoRepository())->getListado($idEmpresa, $buscar, $page, $perPage, 'nombres_apellidos', 'ASC');
        $bioRepo = new BiometriaRepository();
        $tokens  = $bioRepo->getTokensPorEmpresa($idEmpresa);
        $rostros = $bioRepo->getEmpleadosConRostro($idEmpresa);
        $totalPages = $perPage > 0 ? (int) ceil($result['total'] / $perPage) : 1;

        $this->viewWithLayout('layouts.main', 'modulos.control_asistencia.empleados', [
            'titulo'     => 'Control de Asistencia — Credenciales de empleados',
            'perm'       => $perm,
            'rutaModulo' => self::RUTA_MODULO,
            'rows'       => $result['rows'],
            'tokens'     => $tokens,
            'rostros'    => $rostros,
            'total'      => $result['total'],
            'page'       => $page,
            'totalPages' => $totalPages,
            'perPage'    => $perPage,
            'buscar'     => $buscar,
            'idEmpresa'  => $idEmpresa,
        ]);
    }

    /** Enrola (o devuelve) la credencial personal del empleado y su enlace. */
    public function enrolarEmpleadoAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        $idEmpleado = (int) ($_POST['id_empleado'] ?? 0);
        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $idUsuario  = (int) $_SESSION['id_usuario'];

        try {
            if ($idEmpleado <= 0) throw new \Exception('Empleado no válido.');
            $bio = $this->biometriaService->enrolar($idEmpleado, $idEmpresa, $idUsuario);
            $token = $bio['qr_token'];
            echo json_encode([
                'ok'    => true,
                'token' => $token,
                'link'  => rtrim(BASE_URL, '/') . '/asistencia/app?e=' . urlencode($token),
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Regenera el token personal del empleado (revoca el anterior). */
    public function regenerarEmpleadoAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $idEmpleado = (int) ($_POST['id_empleado'] ?? 0);
        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $idUsuario  = (int) $_SESSION['id_usuario'];

        try {
            if ($idEmpleado <= 0) throw new \Exception('Empleado no válido.');
            $token = $this->biometriaService->regenerarToken($idEmpleado, $idEmpresa, $idUsuario);
            echo json_encode([
                'ok'    => true,
                'token' => $token,
                'link'  => rtrim(BASE_URL, '/') . '/asistencia/app?e=' . urlencode($token),
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Enrola el rostro del empleado (recibe el descriptor calculado en el navegador). */
    public function enrolarRostroAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        $idEmpleado = (int) ($_POST['id_empleado'] ?? 0);
        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $idUsuario  = (int) $_SESSION['id_usuario'];
        $descriptor = json_decode($_POST['descriptor'] ?? '[]', true);

        try {
            if ($idEmpleado <= 0) throw new \Exception('Empleado no válido.');
            if (empty($_POST['consentimiento'])) throw new \Exception('Se requiere el consentimiento del empleado para registrar su rostro.');
            if (!is_array($descriptor)) throw new \Exception('Descriptor facial no válido.');
            $descriptor = array_map('floatval', $descriptor);
            $this->biometriaService->enrolarRostro($idEmpleado, $idEmpresa, $descriptor, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Rostro registrado correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ==================================================================
    // HORARIOS / TURNOS y ASIGNACIONES
    // ==================================================================

    public function horarios(): void
    {
        $this->requireLeer();
        $perm      = $this->getPermisos();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $horarios     = $this->horarioService->getListado($idEmpresa, '', 1, 0, 'nombre', 'ASC')['rows'];
        $asignaciones = (new AsistenciaHorarioRepository())->getAsignaciones($idEmpresa);
        $empleados    = (new EmpleadoRepository())->getListado($idEmpresa, '', 1, 0, 'nombres_apellidos', 'ASC')['rows'];
        $puntos       = (new AsistenciaPuntoRepository())->getListado($idEmpresa, '', 1, 0, 'nombre', 'ASC')['rows'];

        $this->viewWithLayout('layouts.main', 'modulos.control_asistencia.horarios', [
            'titulo'       => 'Control de Asistencia — Horarios y turnos',
            'perm'         => $perm,
            'rutaModulo'   => self::RUTA_MODULO,
            'horarios'     => $horarios,
            'asignaciones' => $asignaciones,
            'empleados'    => $empleados,
            'puntos'       => $puntos,
            'idEmpresa'    => $idEmpresa,
        ]);
    }

    public function storeHorario(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');
        $data = $this->recogerDatosHorario();
        $data['id_empresa'] = (int) $_SESSION['id_empresa'];
        $data['id_usuario'] = (int) $_SESSION['id_usuario'];
        try {
            $id = $this->horarioService->crear($data);
            echo json_encode(['ok' => true, 'msg' => 'Horario creado.', 'id' => $id]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function updateHorario(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');
        $id = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $data = $this->recogerDatosHorario();
        $data['id_usuario'] = (int) $_SESSION['id_usuario'];
        try {
            if ($id <= 0) throw new \Exception('ID no válido.');
            $this->horarioService->actualizar($id, $idEmpresa, $data);
            echo json_encode(['ok' => true, 'msg' => 'Horario actualizado.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function deleteHorario(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');
        $id = (int) ($_POST['id_eliminar'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        try {
            if ($id <= 0) throw new \Exception('ID no válido.');
            $this->horarioService->eliminar($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Horario eliminado.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function asignarHorario(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');
        $data = [
            'id_empresa'    => (int) $_SESSION['id_empresa'],
            'id_usuario'    => (int) $_SESSION['id_usuario'],
            'id_empleado'   => (int) ($_POST['id_empleado'] ?? 0),
            'id_horario'    => (int) ($_POST['id_horario'] ?? 0),
            'id_punto'      => ($_POST['id_punto'] ?? '') !== '' ? (int) $_POST['id_punto'] : null,
            'vigente_desde' => trim($_POST['vigente_desde'] ?? ''),
            'vigente_hasta' => trim($_POST['vigente_hasta'] ?? ''),
        ];
        try {
            $id = $this->horarioService->asignar($data);
            echo json_encode(['ok' => true, 'msg' => 'Asignación creada.', 'id' => $id]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function eliminarAsignacion(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');
        $id = (int) ($_POST['id_eliminar'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        try {
            if ($id <= 0) throw new \Exception('ID no válido.');
            $this->horarioService->eliminarAsignacion($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Asignación eliminada.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    private function recogerDatosHorario(): array
    {
        return [
            'nombre'           => trim($_POST['nombre'] ?? ''),
            'hora_entrada'     => trim($_POST['hora_entrada'] ?? ''),
            'hora_salida'      => trim($_POST['hora_salida'] ?? ''),
            'cruza_medianoche' => !empty($_POST['cruza_medianoche']),
            'tolerancia_min'   => (int) ($_POST['tolerancia_min'] ?? 5),
            'horas_jornada'    => (float) ($_POST['horas_jornada'] ?? 8),
            'dias_semana'      => trim($_POST['dias_semana'] ?? '1,2,3,4,5'),
            'estado'           => trim($_POST['estado'] ?? 'activo'),
        ];
    }

    // ==================================================================
    // JORNADAS (resultado del motor)
    // ==================================================================

    public function jornadas(): void
    {
        $this->requireLeer();
        $perm      = $this->getPermisos();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $buscar   = trim($_GET['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? 'fecha');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? 'DESC'));
        $perPage  = 20;

        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;
        $result     = $this->jornadaService->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $totalPages = $perPage > 0 ? (int) ceil($result['total'] / $perPage) : 1;

        $this->viewWithLayout('layouts.main', 'modulos.control_asistencia.jornadas', [
            'titulo'     => 'Control de Asistencia — Jornadas',
            'perm'       => $perm,
            'rutaModulo' => self::RUTA_MODULO,
            'rows'       => $result['rows'],
            'total'      => $result['total'],
            'page'       => $page,
            'totalPages' => $totalPages,
            'perPage'    => $perPage,
            'buscar'     => $buscar,
            'ordenCol'   => $ordenCol,
            'ordenDir'   => $ordenDir,
            'idEmpresa'  => $idEmpresa,
            'meses'      => CatalogoNovedades::MESES,
            'aplicaEnOpts' => CatalogoNovedades::aplicaEn(),
        ]);
    }

    public function jornadasSearchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['b'] ?? '');
        $page      = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol  = trim($_GET['sort'] ?? 'fecha');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? 'DESC'));
        $perPage   = 20;

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $result     = $this->jornadaService->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $total      = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to   = $total > 0 ? min($page * $perPage, $total) : 0;

        ob_start();
        if (empty($result['rows'])) {
            echo '<tr><td colspan="8" class="text-center py-5 text-muted">No hay jornadas calculadas. Usa «Recalcular».</td></tr>';
        } else {
            foreach ($result['rows'] as $r) {
                echo $this->renderFilaJornada($r);
            }
        }
        $rowsHtml = ob_get_clean();

        $prevDisabled = ($page <= 1) ? 'disabled' : '';
        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
        $paginationHtml = '<div class="btn-group btn-group-sm">'
            . '<button type="button" class="btn btn-outline-secondary" ' . $prevDisabled . ' onclick="cambiarPaginaAjax(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>'
            . '<button type="button" class="btn btn-outline-secondary" ' . $nextDisabled . ' onclick="cambiarPaginaAjax(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>'
            . '</div>';

        echo json_encode(['ok' => true, 'rows' => $rowsHtml, 'pagination' => $paginationHtml, 'info' => "$from-$to/$total", 'total' => $total]);
        exit;
    }

    private function renderFilaJornada(array $r): string
    {
        $h = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
        $fecha = $r['fecha'] ? date('d-m-Y', strtotime((string) $r['fecha'])) : '—';
        $ent = $r['primera_entrada'] ? date('H:i', strtotime((string) $r['primera_entrada'])) : '—';
        $sal = $r['ultima_salida'] ? date('H:i', strtotime((string) $r['ultima_salida'])) : '—';

        $estadoMap = ['completa' => 'success', 'incompleta' => 'warning', 'falta' => 'danger', 'permiso' => 'info'];
        $ec = $estadoMap[$r['estado']] ?? 'secondary';
        $estadoBadge = '<span class="badge bg-' . $ec . ' bg-opacity-10 text-' . $ec . ' border border-' . $ec . ' border-opacity-25">' . $h(ucfirst((string) $r['estado'])) . '</span>';

        $atr = (int) $r['atraso_min'];
        $ext = (int) $r['extra_min'];
        $atrCell = $atr > 0 ? '<span class="text-danger fw-medium">' . $atr . ' min</span>' : '—';
        $extCell = $ext > 0 ? '<span class="text-success fw-medium">' . $ext . ' min</span>' : '—';

        return '<tr>'
            . '<td class="ps-3 fw-medium" data-col="empleado">' . $h($r['empleado_nombre']) . '</td>'
            . '<td data-col="fecha">' . $h($fecha) . '</td>'
            . '<td class="text-center" data-col="entrada">' . $h($ent) . '</td>'
            . '<td class="text-center" data-col="salida">' . $h($sal) . '</td>'
            . '<td class="text-center fw-bold" data-col="horas">' . number_format((float) $r['horas_trabajadas'], 2) . '</td>'
            . '<td class="text-center" data-col="atraso">' . $atrCell . '</td>'
            . '<td class="text-center" data-col="extra">' . $extCell . '</td>'
            . '<td class="text-center pe-3" data-col="estado">' . $estadoBadge . '</td>'
            . '</tr>';
    }

    public function recalcularJornadasAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $desde = trim($_POST['desde'] ?? '');
        $hasta = trim($_POST['hasta'] ?? '');
        $idEmpleado = ($_POST['id_empleado'] ?? '') !== '' ? (int) $_POST['id_empleado'] : null;

        try {
            if ($desde === '' || $hasta === '') throw new \Exception('Indica el rango de fechas.');
            if (strtotime($desde) === false || strtotime($hasta) === false) throw new \Exception('Fechas no válidas.');
            $n = $this->jornadaService->recalcularRango($idEmpresa, $desde, $hasta, $idUsuario, $idEmpleado);
            echo json_encode(['ok' => true, 'msg' => "Se procesaron {$n} jornada(s).", 'n' => $n]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ==================================================================
    // CONFIGURACIÓN (tratamiento de atrasos) y GENERACIÓN DE NOVEDADES (paso 4)
    // ==================================================================

    public function configuracion(): void
    {
        $this->requireLeer();
        $perm      = $this->getPermisos();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $config = $this->configRepo->getByEmpresa($idEmpresa);

        $this->viewWithLayout('layouts.main', 'modulos.control_asistencia.configuracion', [
            'titulo'      => 'Control de Asistencia — Configuración',
            'perm'        => $perm,
            'rutaModulo'  => self::RUTA_MODULO,
            'atrasoModo'  => $config['atraso_modo'] ?? 'informativo',
            'aplicaEnOpts' => CatalogoNovedades::aplicaEn(),
            'meses'       => CatalogoNovedades::MESES,
            'idEmpresa'   => $idEmpresa,
        ]);
    }

    public function guardarConfiguracionAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $modo = trim($_POST['atraso_modo'] ?? 'informativo');

        try {
            if (!in_array($modo, ['informativo', 'descuento', 'dias'], true)) {
                throw new \Exception('Opción de atraso no válida.');
            }
            $this->configRepo->upsert($idEmpresa, $modo, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Configuración guardada.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Genera/actualiza las Novedades del período a partir de las jornadas calculadas. */
    public function generarNovedadesAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $mes = (int) ($_POST['periodo_mes'] ?? 0);
        $anio = (int) ($_POST['periodo_anio'] ?? 0);
        $aplicaEn = trim($_POST['aplica_en'] ?? 'rol');
        $idEmpleado = ($_POST['id_empleado'] ?? '') !== '' ? (int) $_POST['id_empleado'] : null;

        try {
            $res = $this->generacionService->generar($idEmpresa, $mes, $anio, $aplicaEn, $idUsuario, $idEmpleado);
            $msg = "Listo: {$res['creadas']} creada(s), {$res['actualizadas']} actualizada(s)"
                . ($res['eliminadas'] > 0 ? ", {$res['eliminadas']} eliminada(s)" : '')
                . ($res['omitidas'] > 0 ? ", {$res['omitidas']} omitida(s) por rol ya pagado" : '')
                . " — {$res['empleados']} empleado(s) con novedades de asistencia.";
            echo json_encode(['ok' => true, 'msg' => $msg] + $res);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ==================================================================
    private function recogerDatosPunto(): array
    {
        return [
            'nombre'      => trim($_POST['nombre'] ?? ''),
            'direccion'   => trim($_POST['direccion'] ?? ''),
            'latitud'     => trim($_POST['latitud'] ?? ''),
            'longitud'    => trim($_POST['longitud'] ?? ''),
            'radio_m'     => (int) ($_POST['radio_m'] ?? 150),
            'exige_gps'   => !empty($_POST['exige_gps']),
            'qr_rotativo' => !empty($_POST['qr_rotativo']),
            'estado'      => trim($_POST['estado'] ?? 'activo'),
        ];
    }
}
