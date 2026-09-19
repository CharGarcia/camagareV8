<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\VacacionRepository;
use App\repositories\modulos\VacacionSolicitudRepository;
use App\Rules\modulos\VacacionRules;
use App\Rules\modulos\VacacionSolicitudRules;
use App\Services\LogSistemaService;
use App\Services\modulos\VacacionPdfService;
use App\Services\modulos\VacacionService;
use App\Services\modulos\VacacionSolicitudService;
use App\models\CatalogoNovedades;

class VacacionesController extends BaseModuloController
{
    private VacacionService $service;
    private ?VacacionSolicitudService $solicitudService = null;
    private const RUTA_MODULO = 'modulos/vacaciones';

    public function __construct()
    {
        parent::__construct();
        $this->service = new VacacionService(new VacacionRepository(), new VacacionRules(), new LogSistemaService());
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    public function index(): void
    {
        $this->requireLeer();
        $perm       = $this->getPermisos();
        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);

        $buscar   = trim($_GET['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_desde');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage  = 20;

        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;
        $result     = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $totalPages = $perPage > 0 ? (int) ceil($result['total'] / $perPage) : 1;

        $this->viewWithLayout('layouts.main', 'modulos.vacaciones.index', [
            'titulo'     => 'Vacaciones',
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
            'meses'      => CatalogoNovedades::MESES,
            // Selects del modal de filtros: solo los valores que la empresa realmente usa.
            'aniosFiltro'    => $this->service->getAniosUsados($idEmpresa),
            'usuariosFiltro' => $this->service->getUsuariosConVacaciones($idEmpresa),
            'vistaConfig' => $prefsVista,
            'idEmpresa'  => $idEmpresa,
            // Bandeja de solicitudes: badge con las que esperan aprobación (0 si su SQL no está aplicado).
            'solicitudesPendientes' => $this->solicitudes()->contarPendientes($idEmpresa, $idUsuarioFiltro),
        ]);
    }

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
        $buscar     = trim($_GET['b'] ?? '');
        $page       = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol   = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_desde');
        $ordenDir   = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage    = 20;
        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $result     = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $totalPages = $perPage > 0 ? (int) ceil($result['total'] / $perPage) : 1;
        $from = $result['total'] > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to   = $result['total'] > 0 ? min($page * $perPage, $result['total']) : 0;

        ob_start();
        if (empty($result['rows'])) {
            echo '<tr><td colspan="8" class="text-center py-5 text-muted">No hay vacaciones registradas.</td></tr>';
        } else {
            foreach ($result['rows'] as $r) echo $this->renderFila($r);
        }
        $rowsHtml = ob_get_clean();

        $prev = $page <= 1 ? 'disabled' : '';
        $next = $page >= $totalPages ? 'disabled' : '';
        $pag = '<div class="btn-group btn-group-sm">'
            . '<button type="button" class="btn btn-outline-secondary" ' . $prev . ' onclick="cambiarPaginaAjax(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>'
            . '<button type="button" class="btn btn-outline-secondary" ' . $next . ' onclick="cambiarPaginaAjax(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button></div>';

        echo json_encode(['ok' => true, 'rows' => $rowsHtml, 'pagination' => $pag, 'info' => "$from-$to/" . $result['total'], 'total' => $result['total']]);
        exit;
    }

    private function renderFila(array $r): string
    {
        $h = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
        $dataJson = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
        $desde = $r['fecha_desde'] ? date('d-m-Y', strtotime((string) $r['fecha_desde'])) : '—';
        $hasta = $r['fecha_hasta'] ? date('d-m-Y', strtotime((string) $r['fecha_hasta'])) : '—';
        $colores = ['registrado' => 'info', 'pagado' => 'success', 'anulado' => 'danger'];
        $c = $colores[$r['estado']] ?? 'secondary';
        $estado = '<span class="badge bg-' . $c . ' bg-opacity-10 text-' . $c . ' border border-' . $c . ' border-opacity-25">' . $h(ucfirst((string) $r['estado'])) . '</span>';

        return '<tr class="vac-row" role="button" data-row=\'' . $dataJson . '\' onclick="abrirModalEditar(this)">'
            . '<td class="ps-3 fw-medium" data-col="empleado">' . $h($r['empleado_nombre']) . '</td>'
            . '<td data-col="identificacion"><code class="text-secondary">' . $h($r['empleado_identificacion']) . '</code></td>'
            . '<td data-col="desde">' . $h($desde) . '</td>'
            . '<td data-col="hasta">' . $h($hasta) . '</td>'
            . '<td class="text-center" data-col="dias">' . (float) $r['dias_gozados'] . '</td>'
            . '<td class="text-end fw-bold" data-col="valor">$' . number_format((float) $r['valor'], 2) . '</td>'
            . '<td class="text-center" data-col="estado">' . $estado . '</td>'
            . '<td class="text-center pe-3" onclick="event.stopPropagation()">'
            . '<button class="btn btn-outline-danger btn-xs border-0 px-2" onclick="eliminarRegistro(' . (int) $r['id'] . ')" title="Eliminar"><i class="bi bi-trash"></i></button>'
            . '</td></tr>';
    }

    public function buscarEmpleadosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $q = trim($_GET['q'] ?? '');
        $data = strlen($q) >= 2 ? (new VacacionRepository())->buscarEmpleados((int) $_SESSION['id_empresa'], $q) : [];
        echo json_encode(['ok' => true, 'data' => $data]);
        exit;
    }

    public function getInfoEmpleadoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idEmp = (int) ($_GET['id_empleado'] ?? 0);
        $excl  = (int) ($_GET['exclude'] ?? 0) ?: null;
        try {
            $info = $this->service->getInfoEmpleado($idEmp, (int) $_SESSION['id_empresa'], $excl);
            $perm = $this->getPermisos();
            foreach ($info['periodos'] as &$p) {
                if ($p['marca'] !== null) {
                    $p['marca']['puede_desmarcar'] = $this->puedeDesmarcar($p['marca'], $perm);
                }
            }
            unset($p);
            echo json_encode(['ok' => true, 'data' => $info]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Marca como tomados o pagados antes del sistema los períodos elegidos del empleado.
     * POST: id_empleado, estado (tomado|pagado), periodos[número] = días, observacion.
     */
    public function marcarPeriodosAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');
        try {
            $dias = $_POST['periodos'] ?? [];
            $n = $this->service->marcarPeriodos(
                (int) $_SESSION['id_empresa'],
                (int) ($_POST['id_empleado'] ?? 0),
                trim((string) ($_POST['estado'] ?? '')),
                is_array($dias) ? $dias : [],
                trim((string) ($_POST['observacion'] ?? '')),
                (int) $_SESSION['id_usuario']
            );
            echo json_encode(['ok' => true, 'msg' => $n === 1 ? 'Período marcado.' : "{$n} períodos marcados."]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Quita la marca de un período (sus días vuelven al saldo). POST: id_periodo. */
    public function desmarcarPeriodoAjax(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');
        try {
            $id = (int) ($_POST['id_periodo'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $marca = $this->service->getPeriodoMarcado($id, $idEmpresa);
            if (!$marca) throw new \Exception('Ese período ya no está marcado. Actualice la ventana.');
            $this->requireRegistroPropio($marca);
            $this->service->desmarcarPeriodo($id, $idEmpresa, (int) $_SESSION['id_usuario']);
            echo json_encode(['ok' => true, 'msg' => 'Se quitó la marca del período.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** ¿Puede quitar la marca? Permiso de eliminar y, sin acceso total, solo las que él marcó (requireRegistroPropio). */
    private function puedeDesmarcar(array $marca, array $perm): bool
    {
        if (empty($perm['eliminar'])) return false;
        if (!empty($perm['todo']) || (int) ($_SESSION['nivel'] ?? 1) >= 3) return true;
        return (int) ($marca['created_by'] ?? 0) === (int) ($_SESSION['id_usuario'] ?? 0);
    }

    public function store(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');
        $data = $this->recoger();
        $data['id_empresa'] = (int) $_SESSION['id_empresa'];
        $data['id_usuario'] = (int) $_SESSION['id_usuario'];
        $data['estado'] = 'registrado';
        try {
            $id = $this->service->crear($data);
            echo json_encode(['ok' => true, 'msg' => 'Vacación registrada.', 'id' => $id]);
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
        $data = $this->recoger();
        $data['id_usuario'] = (int) $_SESSION['id_usuario'];
        try {
            if ($id <= 0) throw new \Exception('ID no válido.');
            $this->service->actualizar($id, (int) $_SESSION['id_empresa'], $data);
            echo json_encode(['ok' => true, 'msg' => 'Vacación actualizada.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function getDetalleAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $data = $this->service->getDetalle((int) ($_GET['id'] ?? 0), (int) $_SESSION['id_empresa']);
        echo json_encode($data ? ['ok' => true, 'data' => $data] : ['ok' => false, 'error' => 'No encontrado']);
        exit;
    }

    public function cambiarEstado(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');
        try {
            $this->service->cambiarEstado((int) ($_POST['id'] ?? 0), (int) $_SESSION['id_empresa'], trim($_POST['estado'] ?? ''), (int) $_SESSION['id_usuario']);
            echo json_encode(['ok' => true, 'msg' => 'Estado actualizado.']);
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
        try {
            $id = (int) ($_POST['id_eliminar'] ?? 0);
            if ($id <= 0) throw new \Exception('ID no válido.');
            $this->service->eliminar($id, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']);
            echo json_encode(['ok' => true, 'msg' => 'Vacación eliminada.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ─── Solicitudes de vacaciones ───────────────────────────────────────────
    //
    // Permisos: enviar el enlace y APROBAR piden Crear (aprobar registra la
    // vacación); rechazar y anular un enlace piden Actualizar. Sin acceso total,
    // cada usuario solo ve y resuelve las solicitudes que él envió (§6).

    private function solicitudes(): VacacionSolicitudService
    {
        if ($this->solicitudService === null) {
            $this->solicitudService = new VacacionSolicitudService(
                new VacacionSolicitudRepository(),
                new VacacionSolicitudRules(),
                new LogSistemaService()
            );
        }
        return $this->solicitudService;
    }

    /** null si el usuario tiene acceso total; su id si solo ve lo suyo. */
    private function filtroPropio(): ?int
    {
        return empty($this->getPermisos()['todo']) ? (int) $_SESSION['id_usuario'] : null;
    }

    /** Envía al empleado el enlace para que llene su solicitud. POST: id_empleado, correo. */
    public function enviarSolicitudAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');
        try {
            $res = $this->solicitudes()->enviarInvitacion(
                (int) $_SESSION['id_empresa'],
                (int) ($_POST['id_empleado'] ?? 0),
                trim((string) ($_POST['correo'] ?? '')),
                (int) $_SESSION['id_usuario']
            );
            echo json_encode([
                'ok'      => true,
                'msg'     => $res['enviado']
                    ? 'Solicitud enviada a ' . $res['correo'] . '.'
                    : 'No se pudo enviar el correo (revise la configuración de correo de la empresa). Copie el enlace y envíeselo al empleado.',
                'enviado' => $res['enviado'],
                'url'     => $res['url'],
            ]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Solicitudes de un empleado (ficha del empleado). GET: id_empleado. */
    public function solicitudesEmpleadoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        try {
            $svc  = $this->solicitudes();
            $filas = $svc->getPorEmpleado(
                (int) ($_GET['id_empleado'] ?? 0),
                (int) $_SESSION['id_empresa'],
                $this->filtroPropio()
            );
            echo json_encode([
                'ok'         => true,
                'disponible' => $svc->disponible(),
                'data'       => array_map(fn($s) => $this->filaSolicitud($s, $svc), $filas),
            ]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Vacaciones registradas de un empleado (sección "Vacaciones registradas" de su
     * ficha). No filtra por creador: el saldo y los días gozados que ya muestra el
     * cuadro de períodos son del empleado completo, así que su detalle también.
     * GET: id_empleado.
     */
    public function vacacionesEmpleadoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        try {
            $idEmpresa  = (int) $_SESSION['id_empresa'];
            $idEmpleado = (int) ($_GET['id_empleado'] ?? 0);
            $filas = array_map(static fn($v) => [
                'id'           => (int) $v['id'],
                'fecha_desde'  => $v['fecha_desde'],
                'fecha_hasta'  => $v['fecha_hasta'],
                'dias_gozados' => (float) $v['dias_gozados'],
                'dias_derecho' => (float) $v['dias_derecho'],
                'valor'        => (float) $v['valor'],
                'periodo_mes'  => (int) $v['periodo_mes'],
                'periodo_anio' => (int) $v['periodo_anio'],
                'afecta_rol'   => in_array((string) $v['afecta_rol'], ['1', 't', 'true'], true),
                'estado'       => $v['estado'],
                'observacion'  => $v['observacion'],
            ], $this->service->getVacacionesEmpleado($idEmpleado, $idEmpresa));

            echo json_encode(['ok' => true, 'data' => $filas, 'meses' => CatalogoNovedades::MESES]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Bandeja del módulo: solicitudes de toda la empresa. GET: estado (vacío = abiertas). */
    public function solicitudesBandejaAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        try {
            $svc       = $this->solicitudes();
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $filtro    = $this->filtroPropio();
            $filas     = $svc->getBandeja($idEmpresa, trim((string) ($_GET['estado'] ?? '')), $filtro);
            echo json_encode([
                'ok'         => true,
                'disponible' => $svc->disponible(),
                'pendientes' => $svc->contarPendientes($idEmpresa, $filtro),
                'data'       => array_map(fn($s) => $this->filaSolicitud($s, $svc), $filas),
            ]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Lo que necesita la UI de cada solicitud (sin exponer el token completo salvo en su enlace). */
    private function filaSolicitud(array $s, VacacionSolicitudService $svc): array
    {
        return [
            'id'               => (int) $s['id'],
            'id_empleado'      => (int) $s['id_empleado'],
            'empleado_nombre'  => $s['empleado_nombre'] ?? '',
            'empleado_identificacion' => $s['empleado_identificacion'] ?? '',
            'estado'           => $s['estado'],
            'correo_destino'   => $s['correo_destino'],
            'enviado_at'       => $s['enviado_at'],
            'expira_at'        => $s['expira_at'],
            'fecha_desde'      => $s['fecha_desde'],
            'fecha_hasta'      => $s['fecha_hasta'],
            'dias_solicitados' => (float) ($s['dias_solicitados'] ?? 0),
            'motivo'           => $s['motivo'],
            'contacto'         => $s['contacto'],
            'solicitado_at'    => $s['solicitado_at'],
            'resuelto_at'      => $s['resuelto_at'],
            'resuelto_nombre'  => $s['resuelto_nombre'] ?? null,
            'comentario'       => $s['comentario'],
            'id_vacacion'      => $s['id_vacacion'] !== null ? (int) $s['id_vacacion'] : null,
            'url'              => $s['estado'] === 'enviada' ? $svc->urlDe($s) : '',
        ];
    }

    /** Aprueba la solicitud: crea la vacación. POST: id, comentario, notificar. */
    public function aprobarSolicitudAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');
        try {
            $id        = (int) ($_POST['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $this->requireRegistroPropio($this->solicitudes()->getDetalle($id, $idEmpresa));

            $idVacacion = $this->solicitudes()->aprobar($id, $idEmpresa, (int) $_SESSION['id_usuario'], [
                'comentario' => trim((string) ($_POST['comentario'] ?? '')),
                'notificar'  => !empty($_POST['notificar']),
            ]);
            echo json_encode(['ok' => true, 'msg' => 'Solicitud aprobada: la vacación quedó registrada.', 'id_vacacion' => $idVacacion]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Rechaza la solicitud. POST: id, motivo, notificar. */
    public function rechazarSolicitudAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');
        try {
            $id        = (int) ($_POST['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $this->requireRegistroPropio($this->solicitudes()->getDetalle($id, $idEmpresa));

            $this->solicitudes()->rechazar(
                $id,
                $idEmpresa,
                (int) $_SESSION['id_usuario'],
                trim((string) ($_POST['motivo'] ?? '')),
                !empty($_POST['notificar'])
            );
            echo json_encode(['ok' => true, 'msg' => 'Solicitud rechazada.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Anula un enlace que el empleado todavía no usó. POST: id. */
    public function cancelarSolicitudAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');
        try {
            $id        = (int) ($_POST['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $this->requireRegistroPropio($this->solicitudes()->getDetalle($id, $idEmpresa));

            $this->solicitudes()->cancelar($id, $idEmpresa, (int) $_SESSION['id_usuario']);
            echo json_encode(['ok' => true, 'msg' => 'El enlace quedó anulado.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** PDF de una solicitud. GET: id. */
    public function solicitudPdf(): void
    {
        $this->requireLeer();
        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        try {
            $sol = $this->solicitudes()->getDetalle($id, $idEmpresa);
            if (!$sol) { http_response_code(404); echo 'Solicitud no encontrada'; exit; }
            $this->requireRegistroPropio($sol);

            (new VacacionPdfService())->generarSolicitud($sol, $this->cargarEmpresaParaPdf($idEmpresa), 'D');
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            http_response_code(500);
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
        exit;
    }

    /** PDF del detalle de vacaciones de un empleado (períodos, saldo y valores). GET: id_empleado. */
    public function detalleEmpleadoPdf(): void
    {
        $this->requireLeer();
        $idEmpleado = (int) ($_GET['id_empleado'] ?? 0);
        $idEmpresa  = (int) $_SESSION['id_empresa'];
        try {
            $emp = $this->service->getEmpleado($idEmpleado, $idEmpresa);
            if (!$emp) { http_response_code(404); echo 'Empleado no encontrado'; exit; }

            $info       = $this->service->getInfoEmpleado($idEmpleado, $idEmpresa);
            $vacaciones = $this->service->getVacacionesEmpleado($idEmpleado, $idEmpresa);

            (new VacacionPdfService())->generarDetalleEmpleado($emp, $info, $vacaciones, $this->cargarEmpresaParaPdf($idEmpresa), 'D');
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            http_response_code(500);
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
        exit;
    }

    /** Datos de la empresa (con el logo del establecimiento) para los PDF. */
    private function cargarEmpresaParaPdf(int $idEmpresa): array
    {
        $empresaModel = new \App\models\Empresa();
        $empresa      = $empresaModel->getPorId($idEmpresa) ?? [];
        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        if (!empty($establecimientos[0]['logo_ruta'])) {
            $empresa['logo_ruta'] = $establecimientos[0]['logo_ruta'];
        }
        return $empresa;
    }

    private function recoger(): array
    {
        return [
            'id_empleado'  => (int) ($_POST['id_empleado'] ?? 0),
            'fecha_desde'  => trim($_POST['fecha_desde'] ?? ''),
            'fecha_hasta'  => trim($_POST['fecha_hasta'] ?? ''),
            'dias_gozados' => (float) ($_POST['dias_gozados'] ?? 0),
            'periodo_mes'  => (int) ($_POST['periodo_mes'] ?? 0),
            'periodo_anio' => (int) ($_POST['periodo_anio'] ?? 0),
            'afecta_rol'   => !empty($_POST['afecta_rol']) ? 1 : 0,
            'observacion'  => trim($_POST['observacion'] ?? ''),
            // El modal muestra el estado al editar: sin recogerlo, cada edición
            // devolvía la vacación a 'registrado' (el repositorio usa ese valor por
            // defecto). En store() se fuerza 'registrado' igual que antes.
            'estado'       => trim($_POST['estado'] ?? ''),
        ];
    }
}
