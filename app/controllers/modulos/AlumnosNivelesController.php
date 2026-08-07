<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\AlumnoNivelRepository;
use App\Rules\modulos\AlumnoNivelRules;
use App\Services\LogSistemaService;
use App\Services\modulos\AlumnoNivelService;

class AlumnosNivelesController extends BaseModuloController
{
    private const RUTA_MODULO = 'modulos/alumnos-niveles';
    private AlumnoNivelService $service;

    public function __construct()
    {
        parent::__construct();
        $repo = new AlumnoNivelRepository();
        $rules = new AlumnoNivelRules();
        $logService = new LogSistemaService();
        $this->service = new AlumnoNivelService($repo, $rules, $logService);
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
        $ordenCol = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'orden');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'asc'));
        $perPage  = 20;

        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows = $result['rows'];
        $total = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $this->viewWithLayout('layouts.main', 'modulos.alumnos_niveles.index', [
            'titulo'     => 'Niveles / Cursos',
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
        $ordenCol  = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'orden');
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
            echo '<tr><td colspan="3" class="text-center py-5 text-muted"><i class="bi bi-mortarboard fs-3 d-block mb-2"></i>No se encontraron niveles/cursos.</td></tr>';
        } else {
            foreach ($rows as $r) {
                $badge = ($r['estado'] === 'activo')
                    ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-10">Activo</span>'
                    : '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-10">Inactivo</span>';
                $dataAttr = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                echo '<tr class="nivel-row" role="button" tabindex="0" data-row=\'' . $dataAttr . '\' onclick="abrirModalNivelEditar(this)">
                        <td class="ps-3 fw-bold" data-col="nombre">' . htmlspecialchars($r['nombre'] ?? '') . '</td>
                        <td class="text-center" data-col="orden">' . (int)($r['orden'] ?? 0) . '</td>
                        <td class="text-center" data-col="estado">' . $badge . '</td>
                      </tr>';
            }
        }
        $rowsHtml = ob_get_clean();

        ob_start();
        $prevDisabled = ($page <= 1) ? 'disabled' : '';
        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
        echo '<div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary border-end-0 rounded-end-0" ' . $prevDisabled . ' onclick="cambiarPaginaNivelAjax(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary rounded-start-0" ' . $nextDisabled . ' onclick="cambiarPaginaNivelAjax(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>
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
            echo json_encode(['ok' => true, 'msg' => 'Nivel/curso creado correctamente.', 'id' => $id, 'nombre' => mb_strtoupper(trim($data['nombre']), 'UTF-8')]);
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
            if ($id <= 0) throw new \Exception('ID de nivel/curso no válido.');
            $this->service->actualizar($id, $idEmpresa, $data);
            echo json_encode(['ok' => true, 'msg' => 'Nivel/curso actualizado correctamente.']);
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
            if ($id <= 0) throw new \Exception('ID de nivel/curso no válido.');
            $this->service->eliminar($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Nivel/curso eliminado correctamente.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    private function recogerDatosFormulario(): array
    {
        return [
            'nombre' => trim($_POST['nombre'] ?? ''),
            'orden'  => (int) ($_POST['orden'] ?? 0),
            'estado' => trim($_POST['estado'] ?? 'activo'),
        ];
    }
}
