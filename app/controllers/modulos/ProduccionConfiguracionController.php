<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\Helpers\Permisos;
use App\models\Empresa;
use App\Services\modulos\ProduccionConfiguracionService;

/**
 * Configuración de Producción (modulos/produccion-configuracion): bodegas por defecto e
 * interruptores del motor; las cuentas van en Configuración Contable → Producción.
 * Guardarla activa Producción para la empresa. Permisos: ver = consultar; actualizar = guardar.
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
            'fullWidth'  => true,
            'perm'       => $this->getPermisos(),
            'rutaModulo' => self::RUTA_MODULO,
            'instalado'  => $instalado,
            'config'     => $cfg,
            'bodegas'    => $instalado ? $this->service->repo()->getBodegas($idEmpresa) : [],
            'metodos'    => ProduccionConfiguracionService::METODOS_REPARTO,
            // Tarjeta «Cuentas contables»: estado de las 6 cuentas (se configuran en Configuración
            // Contable → Producción), si la contabilización está encendida y si el usuario puede ir allá.
            'cuentasContables'   => $cfg ? (new \App\Services\modulos\ProduccionAsientoService())->estadoCuentas($idEmpresa) : [],
            'contabilizaProd'    => $cfg ? \App\Services\modulos\ContabilidadInterruptorService::crear()->contabiliza($idEmpresa, \App\Services\modulos\ProduccionAsientoService::CLAVE) : false,
            'permConfigContable' => Permisos::porRuta('modulos/configuracion-contable'),
            // Crear bodega al vuelo (modal reutilizable de Bodegas): permisos de ESE módulo.
            'permBodegas'      => Permisos::porRuta('modulos/bodegas'),
            'establecimientos' => $instalado ? (new Empresa())->getEstablecimientos($idEmpresa) : [],
            'base'       => BASE_URL,
        ]);
    }

    /** Bodegas para los selectores (se recargan tras crear una bodega desde esta pantalla). */
    public function bodegasAjax(): void
    {
        $this->requireLeer();
        $this->json(['ok' => true, 'data' => $this->service->repo()->getBodegas((int) $_SESSION['id_empresa'])]);
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
}
