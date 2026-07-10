<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\AsistenciaPuntoRepository;
use App\Rules\modulos\AsistenciaPuntoRules;
use App\Services\LogSistemaService;
use App\Services\modulos\AsistenciaPuntoService;

/**
 * Módulo Puntos de servicio.
 *
 * Gestiona los puntos de servicio (QR de ubicación). Horarios/turnos, marcaciones,
 * jornadas y credenciales viven en sus propios módulos/pestañas.
 */
class PuntosServicioController extends BaseModuloController
{
    private AsistenciaPuntoService $puntoService;
    private const RUTA_MODULO = 'modulos/puntos-servicio';

    public function __construct()
    {
        parent::__construct();
        $log = new LogSistemaService();
        $this->puntoService = new AsistenciaPuntoService(new AsistenciaPuntoRepository(), new AsistenciaPuntoRules(), $log);
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

        $this->viewWithLayout('layouts.main', 'modulos.puntos_servicio.index', [
            'titulo'     => 'Puntos de servicio',
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

    // Marcaciones se separó a su propio módulo: App\controllers\modulos\MarcacionesController (modulos/marcaciones).

    // Credenciales (QR personal + rostro) se movieron a una pestaña del modal de
    // Empleados: App\controllers\modulos\EmpleadosController (credencialAjax /
    // generarCredencialAjax / regenerarCredencialAjax / enrolarRostroAjax).

    // Horarios/turnos y asignaciones se separaron a su propio módulo:
    // App\controllers\modulos\HorariosController (modulos/horarios).


    // ==================================================================
    // JORNADAS (resultado del motor)
    // ==================================================================

    // Jornadas se separó a su propio módulo: App\controllers\modulos\JornadasController (modulos/jornadas).
    // El botón "Generar Novedades" vive ahora en ese módulo.

    // La configuración de "tratamiento de atrasos" por empresa se retiró: ahora es
    // por empleado (ficha del empleado, pestaña "Atrasos").

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
