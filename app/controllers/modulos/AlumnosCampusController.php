<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\AlumnoCampusRepository;
use App\Rules\modulos\AlumnoCampusRules;
use App\Services\LogSistemaService;
use App\Services\modulos\AlumnoCampusService;

class AlumnosCampusController extends BaseModuloController
{
    private const RUTA_MODULO = 'modulos/alumnos-campus';
    private AlumnoCampusService $service;

    public function __construct()
    {
        parent::__construct();
        $repo = new AlumnoCampusRepository();
        $rules = new AlumnoCampusRules();
        $logService = new LogSistemaService();
        $this->service = new AlumnoCampusService($repo, $rules, $logService);
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
        $ordenCol = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'nombre');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'asc'));
        $perPage  = 20;

        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows = $result['rows'];
        $total = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $this->viewWithLayout('layouts.main', 'modulos.alumnos_campus.index', [
            'titulo'     => 'Campus',
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
        $ordenCol  = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'nombre');
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

        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="3" class="text-center py-5 text-muted"><i class="bi bi-geo-alt fs-3 d-block mb-2"></i>No se encontraron campus.</td></tr>';
        } else {
            foreach ($rows as $r) {
                $badge = ($r['estado'] === 'activo')
                    ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-10">Activo</span>'
                    : '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-10">Inactivo</span>';
                $dataAttr = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                echo '<tr class="campus-row" role="button" tabindex="0" data-row=\'' . $dataAttr . '\' onclick="abrirModalCampusEditar(this)">
                        <td class="ps-3 fw-bold" data-col="nombre">' . htmlspecialchars($r['nombre'] ?? '') . '</td>
                        <td data-col="direccion" class="text-muted small">' . htmlspecialchars($r['direccion'] ?? '') . '</td>
                        <td class="text-center" data-col="estado">' . $badge . '</td>
                      </tr>';
            }
        }
        $rowsHtml = ob_get_clean();

        ob_start();
        $prevDisabled = ($page <= 1) ? 'disabled' : '';
        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
        echo '<div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary border-end-0 rounded-end-0" ' . $prevDisabled . ' onclick="cambiarPaginaCampusAjax(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary rounded-start-0" ' . $nextDisabled . ' onclick="cambiarPaginaCampusAjax(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>
              </div>';
        $paginationHtml = ob_get_clean();

        echo json_encode([
            'ok' => true,
            'rows' => $rowsHtml,
            'pagination' => $paginationHtml,
            'info' => "$from-$to/$total",
            'total' => $total,
        ]);
        exit;
    }

    /**
     * Listado corto para el select del modal de Alumno (id, nombre).
     */
    public function paraSelectAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        echo json_encode(['ok' => true, 'data' => $this->service->getParaSelect($idEmpresa)]);
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
            echo json_encode(['ok' => true, 'msg' => 'Campus creado correctamente.', 'id' => $id, 'nombre' => mb_strtoupper(trim($data['nombre']), 'UTF-8')]);
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
            if ($id <= 0) throw new \Exception('ID de campus no válido.');
            $this->service->actualizar($id, $idEmpresa, $data);
            echo json_encode(['ok' => true, 'msg' => 'Campus actualizado correctamente.']);
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
            if ($id <= 0) throw new \Exception('ID de campus no válido.');
            $this->service->eliminar($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Campus eliminado correctamente.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    private function recogerDatosFormulario(): array
    {
        return [
            'nombre'    => trim($_POST['nombre'] ?? ''),
            'direccion' => trim($_POST['direccion'] ?? ''),
            'estado'    => trim($_POST['estado'] ?? 'activo'),
        ];
    }
}
