<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\TallerDepartamentoRepository;
use App\Services\LogSistemaService;
use App\Services\modulos\TallerDepartamentoService;
use Exception;

/**
 * Catálogo de departamentos del taller.
 *
 * Cada taller arma su propio flujo (diagnóstico, mecánica, enderezada, pintura,
 * armado, control de calidad…). Cada departamento genera su columna en el
 * tablero y su pantalla de tablet en modulos/taller-estacion.
 *
 * El checklist de recepción es otro módulo: modulos/taller-checklist.
 */
class TallerDepartamentosController extends BaseModuloController
{
    private TallerDepartamentoService $service;
    private const RUTA_MODULO = 'modulos/taller-departamentos';

    public function __construct()
    {
        parent::__construct();
        $this->service = new TallerDepartamentoService(
            new TallerDepartamentoRepository(),
            new LogSistemaService()
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

        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);

        $buscar   = trim($_GET['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'orden');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'asc'));
        $perPage  = 30;

        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $result     = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $totalPages = $perPage > 0 ? (int) ceil($result['total'] / $perPage) : 1;

        $this->viewWithLayout('layouts.main', 'modulos.taller_departamentos.index', [
            'titulo'      => 'Departamentos del taller',
            'perm'        => $perm,
            'rutaModulo'  => self::RUTA_MODULO,
            'rows'        => $result['rows'],
            'total'       => $result['total'],
            'page'        => $page,
            'totalPages'  => $totalPages,
            'perPage'     => $perPage,
            'buscar'      => $buscar,
            'ordenCol'    => $ordenCol,
            'ordenDir'    => $ordenDir,
            'vistaConfig' => $prefsVista,
        ]);
    }

    public function store(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

            $idEmpresa = (int) $_SESSION['id_empresa'];
            $data = [
                'id_empresa'         => $idEmpresa,
                'id_usuario'         => (int) $_SESSION['id_usuario'],
                'nombre'             => trim((string) ($input['nombre'] ?? '')),
                'codigo'             => trim((string) ($input['codigo'] ?? '')) ?: null,
                'descripcion'        => trim((string) ($input['descripcion'] ?? '')) ?: null,
                'color'              => trim((string) ($input['color'] ?? '#0d6efd')),
                'icono'              => trim((string) ($input['icono'] ?? 'bi-tools')) ?: 'bi-tools',
                'orden'              => (int) ($input['orden'] ?? 0),
                'es_diagnostico'     => !empty($input['es_diagnostico']),
                'es_control_calidad' => !empty($input['es_control_calidad']),
                'activo'             => !isset($input['activo']) || !empty($input['activo']),
            ];

            if (!empty($input['id'])) {
                $this->requireActualizar();
                $this->service->actualizar((int) $input['id'], $idEmpresa, $data);
                echo json_encode(['ok' => true, 'msg' => 'Departamento actualizado.']);
            } else {
                $id = $this->service->crear($data);
                echo json_encode(['ok' => true, 'msg' => 'Departamento creado.', 'id' => $id]);
            }
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function eliminar(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        try {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('ID no válido.');

            $this->service->eliminar($id, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']);
            echo json_encode(['ok' => true, 'msg' => 'Departamento eliminado.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function listaAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        echo json_encode(['ok' => true, 'data' => $this->service->getActivos((int) $_SESSION['id_empresa'])]);
        exit;
    }

}
