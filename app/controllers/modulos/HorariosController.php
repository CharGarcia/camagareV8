<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\AsistenciaHorarioRepository;
use App\Rules\modulos\AsistenciaHorarioRules;
use App\Services\LogSistemaService;
use App\Services\modulos\AsistenciaHorarioService;
use App\Helpers\PreferenciasHelper;

/**
 * Módulo Horarios y turnos: catálogo de turnos (hora entrada/salida, tolerancia,
 * horas de jornada, días). La ASIGNACIÓN de un turno a un empleado se hace en la
 * ficha del empleado (pestaña "Horario"), no aquí.
 */
class HorariosController extends BaseModuloController
{
    private AsistenciaHorarioService $service;
    private const RUTA_MODULO = 'modulos/horarios';

    public function __construct()
    {
        parent::__construct();
        $log = new LogSistemaService();
        $this->service = new AsistenciaHorarioService(new AsistenciaHorarioRepository(), new AsistenciaHorarioRules(), $log);
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
        $ordenCol = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'nombre');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'ASC'));
        $perPage  = 20;

        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;
        $result     = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $totalPages = $perPage > 0 ? (int) ceil($result['total'] / $perPage) : 1;

        $this->viewWithLayout('layouts.main', 'modulos.horarios.index', [
            'titulo'     => 'Horarios y turnos',
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
        $ordenCol  = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'nombre');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'ASC'));
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
            echo '<tr><td colspan="7" class="text-center py-5 text-muted">No hay turnos registrados.</td></tr>';
        } else {
            foreach ($result['rows'] as $r) {
                echo $this->renderFila($r, $perm);
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

    private const DIAS_LBL = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];

    private function renderFila(array $r, array $perm): string
    {
        $h = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
        $dataJson = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');

        $horario = $h(substr((string) $r['hora_entrada'], 0, 5)) . ' – ' . $h(substr((string) $r['hora_salida'], 0, 5))
            . (!empty($r['cruza_medianoche']) ? ' <span class="badge bg-dark bg-opacity-10 text-dark">+1d</span>' : '');

        $dias = [];
        foreach (array_filter(array_map('trim', explode(',', (string) $r['dias_semana']))) as $d) {
            $dias[] = self::DIAS_LBL[(int) $d] ?? $d;
        }
        $diasTxt = implode(' ', $dias);

        $activo = ($r['estado'] ?? 'activo') === 'activo';
        $estadoBadge = '<span class="badge bg-' . ($activo ? 'success' : 'secondary') . ' bg-opacity-10 text-' . ($activo ? 'success' : 'secondary') . ' border border-' . ($activo ? 'success' : 'secondary') . ' border-opacity-25">' . ($activo ? 'Activo' : 'Inactivo') . '</span>';

        $btnDel = $perm['eliminar']
            ? '<button class="btn btn-outline-danger btn-xs border-0 px-2" onclick="event.stopPropagation();eliminarHorario(' . (int) $r['id'] . ')" title="Eliminar"><i class="bi bi-trash"></i></button>'
            : '';

        return '<tr class="horario-row" role="button" data-row=\'' . $dataJson . '\' onclick="abrirModalHorario(this)">'
            . '<td class="ps-3 fw-medium" data-col="nombre">' . $h($r['nombre']) . '</td>'
            . '<td class="text-center" data-col="horario">' . $horario . '</td>'
            . '<td class="text-center" data-col="tolerancia">' . (int) $r['tolerancia_min'] . ' min</td>'
            . '<td class="text-center" data-col="horas">' . $h(number_format((float) $r['horas_jornada'], 1)) . '</td>'
            . '<td class="small text-muted" data-col="dias">' . $h($diasTxt) . '</td>'
            . '<td class="text-center" data-col="estado">' . $estadoBadge . '</td>'
            . '<td class="text-center pe-3" onclick="event.stopPropagation()">' . $btnDel . '</td>'
            . '</tr>';
    }

    public function store(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');
        $data = $this->recogerDatos();
        $data['id_empresa'] = (int) $_SESSION['id_empresa'];
        $data['id_usuario'] = (int) $_SESSION['id_usuario'];
        try {
            $id = $this->service->crear($data);
            echo json_encode(['ok' => true, 'msg' => 'Turno creado.', 'id' => $id]);
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
        $data = $this->recogerDatos();
        $data['id_usuario'] = (int) $_SESSION['id_usuario'];
        try {
            if ($id <= 0) throw new \Exception('ID no válido.');
            $this->service->actualizar($id, $idEmpresa, $data);
            echo json_encode(['ok' => true, 'msg' => 'Turno actualizado.']);
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
            echo json_encode(['ok' => true, 'msg' => 'Turno eliminado.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    private function recogerDatos(): array
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
}
