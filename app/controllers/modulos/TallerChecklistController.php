<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\TallerChecklistRepository;
use App\Services\LogSistemaService;
use App\Services\modulos\TallerChecklistService;
use Exception;

/**
 * Checklist de recepción del taller.
 *
 * Define qué se revisa cuando entra un vehículo: accesorios, carrocería,
 * documentos y niveles. Es la evidencia del estado en que llegó el carro, así
 * que se copia a cada orden y queda congelada ahí.
 *
 * Va como módulo aparte del catálogo de departamentos porque son cosas
 * distintas: uno define el flujo de trabajo y este, la revisión de entrada.
 */
class TallerChecklistController extends BaseModuloController
{
    private TallerChecklistService $service;
    private const RUTA_MODULO = 'modulos/taller-checklist';

    public function __construct()
    {
        parent::__construct();
        $this->service = new TallerChecklistService(
            new TallerChecklistRepository(),
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
        $perPage  = 100; // el checklist es corto: cabe entero en una pantalla

        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $result     = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $totalPages = $perPage > 0 ? (int) ceil($result['total'] / $perPage) : 1;

        $this->viewWithLayout('layouts.main', 'modulos.taller_checklist.index', [
            'titulo'      => 'Checklist de recepción',
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
            'grupos'      => TallerChecklistRepository::GRUPOS,
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
                'id_empresa' => $idEmpresa,
                'id_usuario' => (int) $_SESSION['id_usuario'],
                'grupo'      => trim((string) ($input['grupo'] ?? 'accesorios')),
                'item'       => trim((string) ($input['item'] ?? '')),
                'orden'      => (int) ($input['orden'] ?? 0),
                'activo'     => !isset($input['activo']) || !empty($input['activo']),
            ];

            if (!empty($input['id'])) {
                $this->requireActualizar();
                $this->service->actualizar((int) $input['id'], $idEmpresa, $data);
                echo json_encode(['ok' => true, 'msg' => 'Punto de revisión actualizado.']);
            } else {
                $id = $this->service->crear($data);
                echo json_encode(['ok' => true, 'msg' => 'Agregado al checklist.', 'id' => $id]);
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
            echo json_encode(['ok' => true, 'msg' => 'Punto de revisión eliminado.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Plantilla activa: la consume la orden al recibir un vehículo. */
    public function listaAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        echo json_encode(['ok' => true, 'data' => $this->service->getPlantilla((int) $_SESSION['id_empresa'])]);
        exit;
    }
}
