<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\VacacionRepository;
use App\Rules\modulos\VacacionRules;
use App\Services\LogSistemaService;
use App\Services\modulos\VacacionService;
use App\models\CatalogoNovedades;

class VacacionesController extends BaseModuloController
{
    private VacacionService $service;
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
            'vistaConfig' => $prefsVista,
            'idEmpresa'  => $idEmpresa,
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
            echo json_encode(['ok' => true, 'data' => $info]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
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
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
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
        ];
    }
}
