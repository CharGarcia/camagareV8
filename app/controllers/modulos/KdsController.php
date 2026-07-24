<?php
declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\CajaSesionRepository;
use App\repositories\modulos\ComandaRepository;
use App\repositories\modulos\MenuRepository;
use App\repositories\modulos\MesaRepository;
use App\Rules\modulos\CajaSesionRules;
use App\Rules\modulos\ComandaRules;
use App\Services\LogSistemaService;
use App\Services\modulos\CajaSesionService;
use App\Services\modulos\ComandaService;
use App\Services\modulos\KdsService;
use App\Services\modulos\PosVentaService;
use Exception;

/**
 * Pantalla de cocina/barra (KDS) — Fase 2 de POS Restaurantes. Solo lectura +
 * avance de estado de línea (preparando/listo); no toca inventario ni
 * documentos. Independiente del POS mostrador (modulos/caja-pos).
 *
 * Ruta 'modulos/kds' → KdsController (submodulo ya registrado en
 * submodulos_menu como "Configuración kds", id=219, dentro del menú
 * "Restaurante").
 */
class KdsController extends BaseModuloController
{
    private const RUTA_MODULO = 'modulos/kds';
    private KdsService $kdsService;
    private ComandaService $comandaService;

    public function __construct()
    {
        parent::__construct();
        $comandaRepo = new ComandaRepository();
        $logService = new LogSistemaService();
        $this->kdsService = new KdsService($comandaRepo, new MenuRepository());
        $cajaService = new CajaSesionService(new CajaSesionRepository(), new CajaSesionRules(), $logService);
        $ventaService = new PosVentaService($cajaService, $logService);
        $this->comandaService = new ComandaService($comandaRepo, new ComandaRules(), new MesaRepository(), $logService, $ventaService);
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    /**
     * Pantalla standalone (tablet/monitor fijo por estación: cocina, barra, o
     * las que el restaurante haya creado). A propósito NO usa el patrón de
     * "URL limpia" (sesión) de comandas/ver: aquí puede haber varias pantallas
     * físicas abiertas a la vez (una en cocina, otra en barra, etc.), cada una
     * con su propio ?id_estacion= en la URL/marcador — si viviera en sesión,
     * todas las pantallas abiertas con el mismo usuario cambiarían de estación
     * juntas cada vez que una de ellas cambiara de pestaña.
     */
    public function index(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $estaciones = $this->kdsService->getEstaciones($idEmpresa);
        $idEstacion = $this->resolverEstacion($estaciones, (int) ($_GET['id_estacion'] ?? 0));

        $this->view('modulos.kds.index', [
            'titulo'     => 'Pantalla de preparación',
            'rutaModulo' => self::RUTA_MODULO,
            'perm'       => $this->getPermisos(),
            'estaciones' => $estaciones,
            'idEstacion' => $idEstacion,
            'comandas'   => $idEstacion ? $this->kdsService->getComandas($idEmpresa, $idEstacion) : [],
        ]);
    }

    public function pollAjax(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idEstacion = (int) ($_GET['id_estacion'] ?? 0);

        // Solo lectura y de alta frecuencia (polling): liberar el lock de
        // sesión cuanto antes, mismo criterio que ContadoresController::navbarAjax.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $this->json(['ok' => true, 'data' => $idEstacion ? $this->kdsService->getComandas($idEmpresa, $idEstacion) : []]);
    }

    /** Estación pedida por query string si es válida para la empresa, si no la primera configurada. */
    private function resolverEstacion(array $estaciones, int $idPedida): int
    {
        if ($idPedida > 0 && array_filter($estaciones, fn($e) => (int) $e['id'] === $idPedida)) {
            return $idPedida;
        }
        return (int) ($estaciones[0]['id'] ?? 0);
    }

    public function marcarEstadoAjax(): void
    {
        $this->requireActualizar();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            $idLinea = (int) ($_POST['id_linea'] ?? 0);
            $estado  = trim($_POST['estado'] ?? '');
            if ($idLinea <= 0) throw new Exception('Ítem no válido.');
            if (!in_array($estado, ['preparando', 'listo'], true)) throw new Exception('Estado no válido.');

            $this->comandaService->cambiarEstadoLinea($idLinea, $idEmpresa, $idUsuario, $estado);
            $this->json(['ok' => true, 'msg' => 'Estado actualizado.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }
}
