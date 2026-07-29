<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\AsistenciaJornadaRepository;
use App\repositories\modulos\MarcacionRepository;
use App\repositories\modulos\AsistenciaHorarioRepository;
use App\repositories\modulos\AsistenciaConfigRepository;
use App\repositories\modulos\NovedadRepository;
use App\Rules\modulos\NovedadRules;
use App\Services\LogSistemaService;
use App\Services\modulos\JornadaService;
use App\Services\modulos\NovedadService;
use App\Services\modulos\GeneracionNovedadesService;
use App\Helpers\PreferenciasHelper;
use App\models\CatalogoNovedades;

/**
 * Módulo Jornadas: consolidado diario de asistencia (horas, atrasos, extras, faltas)
 * calculado desde las marcaciones, y puente al rol vía Novedades. Separado de
 * Control de Asistencia (que conserva Puntos de servicio + Horarios + Configuración).
 */
class JornadasController extends BaseModuloController
{
    private JornadaService $service;
    private GeneracionNovedadesService $generacionService;
    private const RUTA_MODULO = 'modulos/jornadas';

    public function __construct()
    {
        parent::__construct();
        $log = new LogSistemaService();
        $this->service = new JornadaService(
            new AsistenciaJornadaRepository(),
            new MarcacionRepository(),
            new AsistenciaHorarioRepository(),
            $log
        );
        $this->generacionService = new GeneracionNovedadesService(
            new AsistenciaJornadaRepository(),
            new AsistenciaConfigRepository(),
            new NovedadRepository(),
            new NovedadService(new NovedadRepository(), new NovedadRules(), $log)
        );
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

        $prefsVista = PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
        $buscar   = trim($_GET['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage  = 20;

        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;
        $result     = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $totalPages = $perPage > 0 ? (int) ceil($result['total'] / $perPage) : 1;

        $this->viewWithLayout('layouts.main', 'modulos.jornadas.index', [
            'titulo'     => 'Jornadas',
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
            'vistaConfig' => $prefsVista,
            'meses'      => CatalogoNovedades::MESES,
            'aplicaEnOpts' => CatalogoNovedades::aplicaEn(),
        ]);
    }

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $prefsVista = PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
        $buscar    = trim($_GET['b'] ?? '');
        $page      = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol  = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage   = 20;

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $result     = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $total      = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to   = $total > 0 ? min($page * $perPage, $total) : 0;

        ob_start();
        if (empty($result['rows'])) {
            $colspan = !empty($perm['actualizar']) ? 9 : 8;
            echo '<tr><td colspan="' . $colspan . '" class="text-center py-5 text-muted">No hay jornadas calculadas. Usa «Recalcular».</td></tr>';
        } else {
            foreach ($result['rows'] as $r) {
                echo $this->renderFila($r);
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

    private function renderFila(array $r): string
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

        $esIncompleta = ($r['estado'] ?? '') === 'incompleta';
        if ($esIncompleta && !empty($r['observacion'])) {
            $estadoBadge = '<span class="badge bg-' . $ec . ' bg-opacity-10 text-' . $ec
                . ' border border-' . $ec . ' border-opacity-25" title="' . $h($r['observacion']) . '">'
                . $h(ucfirst((string) $r['estado'])) . '</span>';
        }

        // Celda de acción: solo con permiso de actualizar y jornada incompleta.
        $accionCell = '';
        if (!empty($this->getPermisos()['actualizar'])) {
            $btn = '';
            if ($esIncompleta) {
                $nombreJs = htmlspecialchars((string) ($r['empleado_nombre'] ?? ''), ENT_QUOTES);
                $fechaJs  = htmlspecialchars((string) $r['fecha'], ENT_QUOTES);
                $btn = '<button class="btn btn-outline-warning btn-xs border-0 px-2" title="Corregir: registrar la marcación que falta" '
                    . 'onclick="abrirCorregirJornada(' . (int) $r['id_empleado'] . ", '" . $fechaJs . "', '" . $nombreJs . "')\">"
                    . '<i class="bi bi-pencil-square"></i></button>';
            }
            $accionCell = '<td class="text-center pe-3">' . $btn . '</td>';
        }

        return '<tr>'
            . '<td class="ps-3 fw-medium" data-col="empleado">' . $h($r['empleado_nombre']) . '</td>'
            . '<td data-col="fecha">' . $h($fecha) . '</td>'
            . '<td class="text-center" data-col="entrada">' . $h($ent) . '</td>'
            . '<td class="text-center" data-col="salida">' . $h($sal) . '</td>'
            . '<td class="text-center fw-bold" data-col="horas">' . number_format((float) $r['horas_trabajadas'], 2) . '</td>'
            . '<td class="text-center" data-col="atraso">' . $atrCell . '</td>'
            . '<td class="text-center" data-col="extra">' . $extCell . '</td>'
            . '<td class="text-center" data-col="estado">' . $estadoBadge . '</td>'
            . $accionCell
            . '</tr>';
    }

    public function recalcularAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $desde = trim($_POST['desde'] ?? '');
        $hasta = trim($_POST['hasta'] ?? '');
        $idEmpleado = ($_POST['id_empleado'] ?? '') !== '' ? (int) $_POST['id_empleado'] : null;

        $soloIncompletas = (int) ($_POST['solo_incompletas'] ?? 0) === 1;

        try {
            if ($desde === '' || $hasta === '') throw new \Exception('Indica el rango de fechas.');
            if (strtotime($desde) === false || strtotime($hasta) === false) throw new \Exception('Fechas no válidas.');

            if ($soloIncompletas) {
                $r = $this->service->recalcularIncompletas($idEmpresa, $desde, $hasta, $idUsuario);
                $msg = "Se recalcularon {$r['procesadas']} incompleta(s): {$r['resueltas']} resuelta(s), {$r['pendientes']} siguen requiriendo revisión.";
                echo json_encode(['ok' => true, 'msg' => $msg] + $r);
            } else {
                $n = $this->service->recalcularRango($idEmpresa, $desde, $hasta, $idUsuario, $idEmpleado);
                echo json_encode(['ok' => true, 'msg' => "Se procesaron {$n} jornada(s).", 'n' => $n]);
            }
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Registra la marcación que falta (por defecto la salida) de una jornada
     * incompleta, directamente desde el listado de Jornadas. Reutiliza el motor
     * de marcaciones y recalcula la jornada del día.
     */
    public function corregirMarcacionAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            $idEmpleado = (int) ($_POST['id_empleado'] ?? 0);
            $tipo  = trim($_POST['tipo'] ?? 'salida');
            $fecha = trim($_POST['fecha'] ?? '');
            $hora  = trim($_POST['hora'] ?? '');
            $obs   = trim($_POST['observacion'] ?? '');

            if ($idEmpleado <= 0) throw new \Exception('Empleado no válido.');
            if ($fecha === '' || $hora === '') throw new \Exception('Indique la fecha y la hora.');

            $ts = strtotime($fecha . ' ' . $hora);
            if ($ts === false) throw new \Exception('Fecha u hora no válidas.');
            if ($ts > time() + 60) throw new \Exception('La marcación no puede ser futura.');

            $marcacionSvc = new \App\Services\modulos\MarcacionService(
                new \App\repositories\modulos\MarcacionRepository(),
                new \App\repositories\modulos\BiometriaRepository(),
                new \App\repositories\modulos\AsistenciaPuntoRepository(),
                new \App\Rules\modulos\MarcacionRules(),
                new \App\Services\LogSistemaService()
            );
            $marcacionSvc->marcarManual([
                'id_empleado' => $idEmpleado,
                'tipo'        => $tipo,
                'fecha_hora'  => date('Y-m-d H:i:s', $ts),
                'observacion' => $obs !== '' ? $obs : 'Corrección desde Jornadas.',
            ], $idEmpresa, $idUsuario);

            // Estado de la jornada tras recalcular, para reflejarlo en el listado.
            $jorn = (new \App\repositories\modulos\AsistenciaJornadaRepository())
                ->getByDia($idEmpleado, $idEmpresa, $fecha);

            echo json_encode([
                'ok' => true,
                'msg' => 'Marcación registrada. Jornada recalculada.',
                'estado' => $jorn['estado'] ?? null,
            ]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Cuenta jornadas incompletas de un período (para avisar antes de generar el rol). */
    public function contarIncompletasAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $mes  = (int) ($_GET['mes'] ?? 0);
            $anio = (int) ($_GET['anio'] ?? 0);
            if ($mes < 1 || $mes > 12 || $anio < 2000) throw new \Exception('Período no válido.');

            $desde = sprintf('%04d-%02d-01', $anio, $mes);
            $hasta = date('Y-m-t', strtotime($desde));
            $n = $this->service->contarIncompletas($idEmpresa, $desde, $hasta);

            echo json_encode(['ok' => true, 'incompletas' => $n]);
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
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}
