<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\TransportistaRepository;
use App\Rules\modulos\TransportistaRules;
use App\Services\modulos\TransportistaService;
use App\Services\LogSistemaService;

class TransportistasController extends BaseModuloController
{
    private TransportistaService $service;
    private TransportistaRepository $repo;

    protected function getRutaModulo(): string
    {
        return 'modulos/transportistas';
    }

    public function __construct()
    {
        parent::__construct();
        $this->repo    = new TransportistaRepository();
        $this->service = new TransportistaService($this->repo, new TransportistaRules(), new LogSistemaService());
    }

    public function index(): void
    {
        $this->requireLeer();

        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $perm       = $this->getPermisos();
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());

        $buscar   = trim($_GET['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol = $prefsVista['__ordenCol__'] ?? 'nombre';
        $ordenDir = strtoupper($prefsVista['__ordenDir__'] ?? 'ASC');
        $perPage  = 20;

        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;
        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);

        $this->viewWithLayout('layouts.main', 'modulos/transportistas/index', [
            'titulo'      => 'Transportistas',
            'perm'        => $perm,
            'rows'        => $result['rows'],
            'total'       => $result['total'],
            'page'        => $page,
            'totalPages'  => (int) ceil($result['total'] / $perPage),
            'perPage'     => $perPage,
            'from'        => $result['total'] > 0 ? (($page - 1) * $perPage) + 1 : 0,
            'to'          => $result['total'] > 0 ? min($page * $perPage, $result['total']) : 0,
            'buscar'      => $buscar,
            'ordenCol'    => $ordenCol,
            'ordenDir'    => $ordenDir,
            'vistaConfig' => $prefsVista,
            'base'        => BASE_URL,
            'rutaModulo'  => $this->getRutaModulo(),
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
        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;
        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows   = $result['rows'];
        $total  = $result['total'];
        $totalPages = (int) ceil($total / $perPage);
        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to   = $total > 0 ? min($page * $perPage, $total) : 0;

        $tiposId = ['04' => 'RUC', '05' => 'Cédula', '06' => 'Pasaporte'];

        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="8" class="text-center py-5 text-muted"><i class="bi bi-truck fs-3 d-block mb-2"></i>No se encontraron transportistas.</td></tr>';
        } else {
            foreach ($rows as $r) {
                $rowData     = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                $estadoClass = $r['estado'] === 'activo'
                    ? 'bg-success bg-opacity-10 text-success border-success'
                    : 'bg-secondary bg-opacity-10 text-secondary border-secondary';
                $tipoIdLabel = $tiposId[$r['tipo_id']] ?? $r['tipo_id'];

                echo "<tr class='transp-row' role='button' tabindex='0' data-row='{$rowData}' onclick='abrirModalTransportistaEditar(this)'>
                    <td class='ps-3' data-col='nombre'>" . htmlspecialchars($r['nombre']) . "</td>
                    <td data-col='tipo_id'><small class='text-muted'>{$tipoIdLabel}</small></td>
                    <td data-col='identificacion'><small class='text-muted'>" . htmlspecialchars($r['identificacion']) . "</small></td>
                    <td data-col='placa'>" . htmlspecialchars($r['placa'] ?? '—') . "</td>
                    <td data-col='telefono'>" . htmlspecialchars($r['telefono'] ?? '—') . "</td>
                    <td data-col='email'>" . htmlspecialchars($r['email'] ?? '—') . "</td>
                    <td class='text-center pe-3' data-col='estado'><span class='badge {$estadoClass} border border-opacity-25'>" . ucfirst($r['estado']) . "</span></td>
                </tr>";
            }
        }
        $rowsHtml = ob_get_clean();

        ob_start();
        $prevDis = ($page <= 1)           ? 'disabled' : '';
        $nextDis = ($page >= $totalPages) ? 'disabled' : '';
        echo "<button type='button' class='btn btn-outline-secondary' {$prevDis} onclick='TR_cambiarPagina(" . ($page - 1) . ")'><i class='bi bi-chevron-left'></i></button>
              <button type='button' class='btn btn-outline-secondary' {$nextDis} onclick='TR_cambiarPagina(" . ($page + 1) . ")'><i class='bi bi-chevron-right'></i></button>";
        $paginationHtml = ob_get_clean();

        echo json_encode([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'pagination' => $paginationHtml,
            'info'       => "{$from}-{$to}/{$total}",
            'total'      => $total,
        ]);
        exit;
    }

    public function guardarAjax(): void
    {
        header('Content-Type: application/json');
        try {
            $data              = $_POST;
            $data['id_empresa'] = (int) $_SESSION['id_empresa'];
            $data['id_usuario'] = (int) $_SESSION['id_usuario'];

            $id = !empty($data['id']) ? (int) $data['id'] : 0;

            if ($id > 0) {
                $this->requireActualizar();
                $data['id'] = $id;
                $this->service->actualizar($id, $data);
                echo json_encode(['ok' => true, 'mensaje' => 'Transportista actualizado correctamente.']);
            } else {
                $this->requireCrear();
                $newId = $this->service->crear($data);
                echo json_encode(['ok' => true, 'mensaje' => 'Transportista creado correctamente.', 'id' => $newId]);
            }
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function eliminarAjax(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');
        try {
            $id        = (int) ($_POST['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];

            if (!$id) {
                echo json_encode(['ok' => false, 'mensaje' => 'ID requerido.']);
                exit;
            }
            $this->service->eliminar($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'mensaje' => 'Transportista eliminado correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function buscarAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $q         = trim($_GET['q'] ?? '');

        $rows = $this->repo->buscarParaSelect($idEmpresa, $q);
        echo json_encode(['ok' => true, 'data' => $rows]);
        exit;
    }
}
