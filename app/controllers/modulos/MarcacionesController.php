<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\MarcacionRepository;
use App\repositories\modulos\BiometriaRepository;
use App\repositories\modulos\AsistenciaPuntoRepository;
use App\Rules\modulos\MarcacionRules;
use App\Services\modulos\MarcacionService;
use App\Services\LogSistemaService;
use App\Helpers\PreferenciasHelper;

/**
 * Módulo Marcaciones (bitácora de marcaciones de asistencia).
 * Separado de Control de Asistencia: aquí solo se consulta/depura el registro
 * de marcaciones. La marcación en sí la crea el empleado desde su celular (PWA).
 */
class MarcacionesController extends BaseModuloController
{
    private MarcacionService $service;
    private const RUTA_MODULO = 'modulos/marcaciones';

    public function __construct()
    {
        parent::__construct();
        $log = new LogSistemaService();
        $this->service = new MarcacionService(
            new MarcacionRepository(),
            new BiometriaRepository(),
            new AsistenciaPuntoRepository(),
            new MarcacionRules(),
            $log
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
        $ordenCol = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_hora');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage  = 20;

        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;
        $result     = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $totalPages = $perPage > 0 ? (int) ceil($result['total'] / $perPage) : 1;

        $this->viewWithLayout('layouts.main', 'modulos.marcaciones.index', [
            'titulo'     => 'Marcaciones',
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
        $ordenCol  = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_hora');
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
            echo '<tr><td colspan="7" class="text-center py-5 text-muted">No hay marcaciones registradas.</td></tr>';
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

        echo json_encode([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'pagination' => $paginationHtml,
            'info'       => "$from-$to/$total",
            'total'      => $total,
        ]);
        exit;
    }

    private function renderFila(array $r, array $perm): string
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
            echo json_encode(['ok' => true, 'msg' => 'Marcación eliminada.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}
