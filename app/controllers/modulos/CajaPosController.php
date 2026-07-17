<?php
declare(strict_types=1);

namespace App\controllers\modulos;

use App\models\Empresa;
use App\repositories\modulos\CajaSesionRepository;
use App\repositories\modulos\ProductoRepository;
use App\Rules\modulos\CajaSesionRules;
use App\Services\LogSistemaService;
use App\Services\modulos\CajaSesionService;
use App\Services\modulos\PosVentaService;

/**
 * Apertura/cierre de la caja del Punto de Venta. Página STANDALONE (se abre
 * en ventana aparte, mismo patrón que Videos de Ayuda) — no usa el layout
 * principal. Es la puerta de entrada obligatoria antes de vender: sin turno
 * abierto para el punto de emisión, no hay pantalla de venta.
 *
 * Ruta 'modulos/caja-pos' → el router resuelve esta clase como
 * App\controllers\modulos\CajaPosController (CamelCase del segmento de URL).
 */
class CajaPosController extends BaseModuloController
{
    private const RUTA_MODULO = 'modulos/caja-pos';
    private CajaSesionService $service;
    private PosVentaService $ventaService;

    public function __construct()
    {
        parent::__construct();
        $repo = new CajaSesionRepository();
        $rules = new CajaSesionRules();
        $logService = new LogSistemaService();
        $this->service = new CajaSesionService($repo, $rules, $logService);
        $this->ventaService = new PosVentaService($this->service, $logService);
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    public function index(): void
    {
        $this->requireLeer();

        $this->view('modulos.caja_sesion.standalone', [
            'titulo' => 'Caja — Punto de Venta',
            'rutaModulo' => self::RUTA_MODULO,
            'perm' => $this->getPermisos(),
        ]);
    }

    /**
     * Pantalla de venta del POS (Diseño A · Grid Retail). Exige turno de
     * caja abierto para el punto de emisión; si no lo hay, cae al aviso de
     * "vuelve a caja" en vez de mostrar el mostrador.
     */
    public function venta(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idPuntoEmision = (int) ($_GET['id_punto_emision'] ?? 0);
        $sesion = $idPuntoEmision > 0 ? $this->service->getSesionAbierta($idEmpresa, $idPuntoEmision) : null;

        if (!$sesion) {
            $this->view('modulos.caja_sesion.venta_placeholder', [
                'titulo' => 'Punto de Venta',
                'rutaModulo' => self::RUTA_MODULO,
                'idPuntoEmision' => $idPuntoEmision,
                'sesion' => null,
            ]);
            return;
        }

        $this->view('modulos.caja_sesion.venta', [
            'titulo' => 'Punto de Venta',
            'rutaModulo' => self::RUTA_MODULO,
            'idPuntoEmision' => $idPuntoEmision,
            'sesion' => $sesion,
        ]);
    }

    public function getProductosAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar = trim($_GET['q'] ?? '');

        $repo = new ProductoRepository();
        $result = $repo->getListado($idEmpresa, $buscar, 1, 24, 'nombre', 'ASC', null, 'venta', true);

        $this->json(['ok' => true, 'data' => $result['rows']]);
    }

    public function cobrarAjax(): void
    {
        $this->requireCrear();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $idPuntoEmision = (int) ($_POST['id_punto_emision'] ?? 0);
        $formaPago = trim($_POST['forma_pago'] ?? '01');
        $items = json_decode($_POST['items'] ?? '[]', true);

        try {
            if (!is_array($items)) {
                throw new \Exception('El carrito no es válido.');
            }
            $res = $this->ventaService->cobrar([
                'id_empresa' => $idEmpresa,
                'id_usuario' => $idUsuario,
                'id_punto_emision' => $idPuntoEmision,
                'forma_pago' => $formaPago,
                'items' => $items,
            ], $this->getEmpresaConfig($idEmpresa));

            $this->json(['ok' => true, 'msg' => 'Venta registrada correctamente.', 'data' => $res]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    private function getEmpresaConfig(int $idEmpresa): array
    {
        $empresaModel = new Empresa();
        $empresaData = $empresaModel->getPorId($idEmpresa) ?? [];
        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        if (!empty($establecimientos)) {
            try {
                $estRepo = new \App\repositories\modulos\EmpresaRepository();
                $estConfig = $estRepo->getEstablecimientoConfig((int) $establecimientos[0]['id']);
                if ($estConfig) {
                    $empresaData = array_merge($empresaData, $estConfig);
                }
            } catch (\Throwable $e) {
                // Migración de configuración pendiente — se usan valores por defecto.
            }
        }
        return $empresaData;
    }

    public function getEstablecimientosAjax(): void
    {
        $this->requireLeer();
        $empresaModel = new Empresa();
        $data = $empresaModel->getEstablecimientos((int) $_SESSION['id_empresa']);
        $this->json(['ok' => true, 'data' => $data]);
    }

    public function getPuntosEmisionAjax(): void
    {
        $this->requireLeer();
        $idEstablecimiento = (int) ($_GET['id_establecimiento'] ?? 0);
        $empresaModel = new Empresa();
        $data = $idEstablecimiento > 0 ? $empresaModel->getPuntosEmision($idEstablecimiento) : [];
        $this->json(['ok' => true, 'data' => $data]);
    }

    public function estadoActualAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idPuntoEmision = (int) ($_GET['id_punto_emision'] ?? 0);

        if ($idPuntoEmision <= 0) {
            $this->json(['ok' => false, 'error' => 'Punto de emisión no válido.'], 400);
        }

        $sesion = $this->service->getSesionAbierta($idEmpresa, $idPuntoEmision);
        $this->json(['ok' => true, 'sesion' => $sesion]);
    }

    public function abrirAjax(): void
    {
        $this->requireCrear();

        $data = [
            'id_empresa' => (int) $_SESSION['id_empresa'],
            'id_usuario' => (int) $_SESSION['id_usuario'],
            'id_punto_emision' => (int) ($_POST['id_punto_emision'] ?? 0),
            'fondo_inicial' => $_POST['fondo_inicial'] ?? null,
        ];

        try {
            $sesion = $this->service->abrir($data);
            $this->json(['ok' => true, 'msg' => 'Caja abierta correctamente.', 'sesion' => $sesion]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function cerrarAjax(): void
    {
        $this->requireActualizar();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $id = (int) ($_POST['id'] ?? 0);

        $data = [
            'id_usuario' => (int) $_SESSION['id_usuario'],
            'monto_contado' => $_POST['monto_contado'] ?? null,
            'observaciones_cierre' => $_POST['observaciones_cierre'] ?? '',
        ];

        try {
            if ($id <= 0) {
                throw new \Exception('Sesión de caja no válida.');
            }
            $sesion = $this->service->cerrar($id, $idEmpresa, $data);
            $this->json(['ok' => true, 'msg' => 'Caja cerrada correctamente.', 'sesion' => $sesion]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }
}
