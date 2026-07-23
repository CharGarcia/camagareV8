<?php
declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\ComandaRepository;
use App\repositories\modulos\MesaRepository;
use App\Rules\modulos\ComandaRules;
use App\Services\LogSistemaService;
use App\Services\modulos\ComandaService;
use Exception;

/**
 * Comandas (POS Restaurantes). Una comanda es de una mesa; el mesero le va
 * agregando ítems mientras está 'abierta'. No mueve inventario ni genera
 * documento propio (eso llega en el cobro, fase posterior — reutiliza
 * PosVentaService igual que Servicio Car-Wash reutiliza Factura/ReciboVentaService).
 *
 * Ruta 'modulos/comandas' → ComandasController (clase debe llamarse así:
 * el router arma App\controllers\modulos\{segmento}Controller).
 */
class ComandasController extends BaseModuloController
{
    private const RUTA_MODULO = 'modulos/comandas';
    private ComandaService $service;

    public function __construct()
    {
        parent::__construct();
        $repo = new ComandaRepository();
        $rules = new ComandaRules();
        $mesaRepo = new MesaRepository();
        $logService = new LogSistemaService();
        $this->service = new ComandaService($repo, $rules, $mesaRepo, $logService);
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    /** No hay listado propio todavía (llega con el cobro/historial); el punto de entrada es el tablero de mesas. */
    public function index(): void
    {
        $this->requireLeer();
        $this->redirect(rtrim(BASE_URL ?? '', '/') . '/modulos/mesas/tablero');
    }

    // ─── Pantalla de la comanda (mesero) ───────────────────────────────────────

    public function ver(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        // URL limpia (sin id): la comanda "actual" viaja en sesión, mismo
        // patrón que pos_id_punto_emision en caja-pos — así nadie cambia el
        // id a mano en la barra de direcciones para ver otra mesa.
        $id = (int) ($_SESSION['pos_id_comanda'] ?? 0);

        $comanda = $id > 0 ? $this->service->getDetalle($id, $idEmpresa) : null;
        if (!$comanda) {
            $this->redirect(rtrim(BASE_URL ?? '', '/') . '/modulos/mesas/tablero');
        }

        $this->view('modulos.comandas.ver', [
            'titulo'     => 'Comanda ' . ($comanda['numero_comanda'] ?? ''),
            'rutaModulo' => self::RUTA_MODULO,
            'perm'       => $this->getPermisos(),
            'comanda'    => $comanda,
        ]);
    }

    public function verAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $id = (int) ($_SESSION['pos_id_comanda'] ?? 0);

        try {
            $comanda = $id > 0 ? $this->service->getDetalle($id, $idEmpresa) : null;
            if (!$comanda) throw new Exception('Comanda no encontrada.');
            $this->json(['ok' => true, 'data' => $comanda]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    // ─── Abrir / entrar / anular comanda ───────────────────────────────────────

    public function abrirAjax(): void
    {
        $this->requireCrear();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            $idComanda = $this->service->abrir([
                'id_empresa'     => $idEmpresa,
                'id_usuario'     => $idUsuario,
                'id_mesa'        => (int) ($_POST['id_mesa'] ?? 0),
                'id_caja_sesion' => (int) ($_POST['id_caja_sesion'] ?? 0),
                'comensales'     => (int) ($_POST['comensales'] ?? 0),
                'observaciones'  => trim($_POST['observaciones'] ?? ''),
            ]);
            // URL limpia: la pantalla de la comanda (modulos/comandas/ver) lee este id de sesión, no de la URL.
            $_SESSION['pos_id_comanda'] = $idComanda;
            $this->json(['ok' => true, 'msg' => 'Comanda abierta.', 'id' => $idComanda]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /** Entrar a una comanda ya abierta (mesa ocupada) desde el tablero — fija el id en sesión antes de navegar a la URL limpia. */
    public function entrarAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        try {
            $id = (int) ($_POST['id'] ?? 0);
            $comanda = $id > 0 ? $this->service->getDetalle($id, $idEmpresa) : null;
            if (!$comanda) throw new Exception('Comanda no encontrada.');
            $_SESSION['pos_id_comanda'] = $id;
            $this->json(['ok' => true]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function anularAjax(): void
    {
        $this->requireEliminar();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('Comanda no válida.');
            $this->service->anular($id, $idEmpresa, $idUsuario, trim($_POST['motivo'] ?? ''));
            $this->json(['ok' => true, 'msg' => 'Comanda anulada; la mesa quedó disponible.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function actualizarCabeceraAjax(): void
    {
        $this->requireActualizar();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('Comanda no válida.');
            $this->service->actualizarCabecera($id, $idEmpresa, $idUsuario, [
                'id_cliente'    => (int) ($_POST['id_cliente'] ?? 0),
                'comensales'    => (int) ($_POST['comensales'] ?? 0),
                'observaciones' => trim($_POST['observaciones'] ?? ''),
            ]);
            $this->json(['ok' => true, 'msg' => 'Comanda actualizada.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    // ─── Líneas ────────────────────────────────────────────────────────────────

    public function agregarLineaAjax(): void
    {
        $this->requireCrear();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            $idComanda = (int) ($_POST['id_comanda'] ?? 0);
            if ($idComanda <= 0) throw new Exception('Comanda no válida.');

            $idLinea = $this->service->agregarLinea($idComanda, $idEmpresa, $idUsuario, [
                'id_producto'      => (int) ($_POST['id_producto'] ?? 0),
                'descripcion'      => trim($_POST['descripcion'] ?? ''),
                'cantidad'         => (float) ($_POST['cantidad'] ?? 0),
                'precio_unitario'  => (float) ($_POST['precio_unitario'] ?? 0),
                'descuento'        => (float) ($_POST['descuento'] ?? 0),
                'observacion_item' => trim($_POST['observacion_item'] ?? ''),
            ]);
            $this->json(['ok' => true, 'msg' => 'Ítem agregado.', 'id' => $idLinea]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function anularLineaAjax(): void
    {
        $this->requireActualizar();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            $idLinea   = (int) ($_POST['id_linea'] ?? 0);
            $idComanda = (int) ($_POST['id_comanda'] ?? 0);
            if ($idLinea <= 0 || $idComanda <= 0) throw new Exception('Línea no válida.');
            $this->service->anularLinea($idLinea, $idComanda, $idEmpresa, $idUsuario);
            $this->json(['ok' => true, 'msg' => 'Ítem anulado.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    // ─── Cocina / barra ────────────────────────────────────────────────────────

    /** Envía a cocina/barra las líneas 'pendiente' (todas, o solo las marcadas por el mesero). */
    public function enviarCocinaAjax(): void
    {
        $this->requireCrear();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            $idComanda = (int) ($_POST['id_comanda'] ?? 0);
            if ($idComanda <= 0) throw new Exception('Comanda no válida.');

            $idsLineas = [];
            if (!empty($_POST['ids_lineas'])) {
                $idsLineas = array_map('intval', json_decode($_POST['ids_lineas'], true) ?: []);
            }

            $n = $this->service->enviarACocina($idComanda, $idEmpresa, $idUsuario, $idsLineas);
            $this->json(['ok' => true, 'msg' => $n > 0 ? "Se enviaron {$n} ítem(s) a cocina/barra." : 'No había ítems pendientes por enviar.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /** El mesero marca un ítem 'listo' como entregado al cliente. */
    public function marcarEntregadoAjax(): void
    {
        $this->requireActualizar();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            $idLinea = (int) ($_POST['id_linea'] ?? 0);
            if ($idLinea <= 0) throw new Exception('Ítem no válido.');
            $this->service->cambiarEstadoLinea($idLinea, $idEmpresa, $idUsuario, 'entregado');
            $this->json(['ok' => true, 'msg' => 'Ítem entregado.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    // ─── Autocompletes del selector de ítems (mismo patrón que Car-Wash/POS) ──

    public function getProductosAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar = trim($_GET['q'] ?? '');

        try {
            $repo = new \App\repositories\modulos\ProductoRepository();
            $result = $repo->getListado($idEmpresa, $buscar, 1, 24, 'nombre', 'ASC', null, 'venta', true);
            $rows = array_map(function ($p) use ($repo, $idEmpresa) {
                $p['variantes'] = $repo->getVariantes((int) $p['id'], $idEmpresa);
                return $p;
            }, $result['rows']);
            $this->json(['ok' => true, 'data' => $rows]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'data' => [], 'error' => $e->getMessage()]);
        }
    }

    public function getClientesAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar = trim($_GET['q'] ?? '');

        $repo = new \App\repositories\modulos\ClienteRepository();
        $result = $repo->getListado($idEmpresa, $buscar, 1, 10, 'nombre', 'ASC', null, true);
        $this->json(['ok' => true, 'data' => $result['rows']]);
    }
}
