<?php
declare(strict_types=1);

namespace App\controllers\modulos;

use App\Helpers\PreferenciasHelper;
use App\Services\modulos\AprobacionesService;

/**
 * Configuración centralizada del motor de Aprobaciones: por empresa, qué
 * procesos exigen aprobación y quiénes son los aprobadores.
 */
class AprobacionesConfigController extends BaseModuloController
{
    private AprobacionesService $service;
    private const RUTA_MODULO = 'modulos/aprobaciones-config';

    public function __construct()
    {
        parent::__construct();
        $this->service = new AprobacionesService();
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);

        $this->viewWithLayout('layouts.main', 'modulos.aprobaciones_config.index', [
            'titulo'      => 'Aprobaciones',
            'perm'        => $this->getPermisos(),
            'aprobaciones' => $this->service->getListado($idEmpresa),
            'disponibles' => $this->service->getTiposDisponibles($idEmpresa),
            'usuarios'    => $this->service->getUsuariosEmpresa($idEmpresa),
            'vistaConfig' => PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO),
            'rutaModulo'  => self::RUTA_MODULO,
            'fullWidth'   => false,
        ]);
    }

    /**
     * Crea o actualiza una aprobación. Es el mismo endpoint para ambos casos: el
     * UNIQUE(id_empresa,id_tipo) hace que un proceso tenga una sola config por
     * empresa, así que "crear" y "editar" son el mismo upsert.
     */
    public function guardarAjax(): void
    {
        header('Content-Type: application/json');

        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
        $idTipo    = (int) ($_POST['id_tipo'] ?? 0);

        if (!$idTipo) {
            echo json_encode(['ok' => false, 'mensaje' => 'Selecciona el proceso a aprobar.']);
            return;
        }

        // Si el proceso todavía no está configurado en la empresa, esto es un alta
        // (permiso de crear); si ya existe, es una edición (permiso de actualizar).
        $esNueva = !$this->tieneConfigurada($idEmpresa, $idTipo);
        if ($esNueva) {
            $this->requireCrear();
        } else {
            $this->requireActualizar();
        }

        try {
            $this->service->guardarConfig($idEmpresa, $idTipo, [
                'requiere_aprobacion'  => !empty($_POST['requiere_aprobacion']),
                'usuarios_aprobadores' => $_POST['usuarios_aprobadores'] ?? [],
                'umbral_monto'         => $_POST['umbral_monto'] ?? null,
            ], $idUsuario);
            echo json_encode([
                'ok'      => true,
                'mensaje' => $esNueva ? 'Aprobación creada.' : 'Aprobación actualizada.',
            ]);
        } catch (\InvalidArgumentException $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => 'No se pudo guardar la aprobación.']);
        }
    }

    public function eliminarAjax(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
        $idTipo    = (int) ($_POST['id_tipo'] ?? 0);

        if (!$idTipo) {
            echo json_encode(['ok' => false, 'mensaje' => 'Aprobación no encontrada.']);
            return;
        }

        try {
            $this->service->eliminarConfig($idEmpresa, $idTipo, $idUsuario);
            echo json_encode(['ok' => true, 'mensaje' => 'Aprobación eliminada.']);
        } catch (\InvalidArgumentException $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => 'No se pudo eliminar la aprobación.']);
        }
    }

    private function tieneConfigurada(int $idEmpresa, int $idTipo): bool
    {
        foreach ($this->service->getListado($idEmpresa) as $a) {
            if ((int) $a['id_tipo'] === $idTipo) return true;
        }
        return false;
    }
}
