<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\Services\modulos\ProduccionConfiguracionService;

/**
 * Configuración de Producción (modulos/produccion-configuracion): bodegas por defecto,
 * interruptores del motor y cuentas contables del módulo. Guardarla activa Producción
 * para la empresa. Permisos: ver = consultar; actualizar = guardar.
 * Sin lógica de negocio: delega en ProduccionConfiguracionService.
 */
class ProduccionConfiguracionController extends BaseModuloController
{
    private const RUTA_MODULO = 'modulos/produccion-configuracion';

    private ProduccionConfiguracionService $service;

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    public function __construct()
    {
        parent::__construct();
        $this->service = ProduccionConfiguracionService::crear();
    }

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $instalado = $this->service->instalado();
        $cfg       = $instalado ? $this->service->get($idEmpresa) : null;

        $this->viewWithLayout('layouts.main', 'modulos/produccion_configuracion/index', [
            'titulo'     => 'Configuración de Producción',
            'perm'       => $this->getPermisos(),
            'rutaModulo' => self::RUTA_MODULO,
            'instalado'  => $instalado,
            'config'     => $cfg,
            'cuentas'    => $this->service->cuentasParaVista($cfg, $idEmpresa),
            'bodegas'    => $instalado ? $this->service->repo()->getBodegas($idEmpresa) : [],
            'metodos'    => ProduccionConfiguracionService::METODOS_REPARTO,
            'etiquetas'  => ProduccionConfiguracionService::ETIQUETAS_CUENTA,
            'base'       => BASE_URL,
        ]);
    }

    public function guardarAjax(): void
    {
        $this->requireActualizar();
        try {
            $cfg = $this->service->guardar($_POST, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']);
            $this->json([
                'ok'      => true,
                'config'  => $cfg,
                'mensaje' => 'Configuración guardada. Producción está activo para esta empresa.',
            ]);
        } catch (\DomainException $e) {
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $this->json(['ok' => false, 'mensaje' => 'Ocurrió un error inesperado al guardar la configuración.']);
        }
    }

    public function buscarCuentasAjax(): void
    {
        $this->requireLeer();
        $q = trim((string) ($_GET['q'] ?? ''));
        $this->json([
            'ok'   => true,
            'data' => $this->service->instalado()
                ? $this->service->repo()->buscarCuentas((int) $_SESSION['id_empresa'], $q)
                : [],
        ]);
    }
}
