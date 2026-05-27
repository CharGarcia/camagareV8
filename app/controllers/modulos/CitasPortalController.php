<?php
declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\CitaPortalRepository;
use App\Services\modulos\CitaPortalService;
use App\Services\LogSistemaService;

class CitasPortalController extends BaseModuloController
{
    private CitaPortalService $service;
    private const RUTA_MODULO = 'modulos/citas-portal';

    public function __construct()
    {
        parent::__construct();
        $this->service = new CitaPortalService(
            new CitaPortalRepository(),
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

        $idEmpresa   = (int) $_SESSION['id_empresa'];
        $perm        = $this->getPermisos();

        // Reutilizamos el repositorio de configuración para obtener el portal config
        $cfgRepo  = new \App\repositories\modulos\CitaConfiguracionRepository();
        $portal   = $cfgRepo->getPortalConfig($idEmpresa);

        $stats    = $this->service->getPortalStats($idEmpresa);
        $ultimas  = $this->service->getUltimasReservasPortal($idEmpresa, 15);

        $urlPortal = '';
        if (!empty($portal['slug'])) {
            $urlPortal = rtrim(BASE_URL, '/') . '/reservas/' . $portal['slug'];
        }

        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);

        $this->viewWithLayout('layouts.main', 'modulos/citas_portal/index', [
            'titulo'      => 'Portal de Reservas',
            'perm'        => $perm,
            'rutaModulo'  => self::RUTA_MODULO,
            'portal'      => $portal,
            'stats'       => $stats,
            'ultimas'     => $ultimas,
            'urlPortal'   => $urlPortal,
            'vistaConfig' => $prefsVista,
            'fullWidth'   => true,
        ]);
    }
}
