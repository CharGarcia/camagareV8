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
use App\Services\modulos\PosVentaService;
use App\models\Empresa;
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
        $cajaService = new CajaSesionService(new CajaSesionRepository(), new CajaSesionRules(), $logService);
        $ventaService = new PosVentaService($cajaService, $logService);
        $this->service = new ComandaService($repo, $rules, $mesaRepo, $logService, $ventaService, new MenuRepository());
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
            'titulo'        => 'Comanda ' . ($comanda['numero_comanda'] ?? ''),
            'rutaModulo'    => self::RUTA_MODULO,
            'perm'          => $this->getPermisos(),
            'comanda'       => $comanda,
            'bodegas'       => (new Empresa())->getBodegas($idEmpresa),
            'empresaConfig' => $this->getEmpresaConfig($idEmpresa),
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

    /** El mesero marca como atendida una solicitud de "llamar al mesero" hecha desde el QR. */
    public function atenderAsistenciaAjax(): void
    {
        $this->requireActualizar();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('Comanda no válida.');
            $this->service->atenderAsistencia($id, $idEmpresa, $idUsuario);
            $this->json(['ok' => true, 'msg' => 'Marcado como atendido.']);
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
                'id_menu_item'     => (int) ($_POST['id_menu_item'] ?? 0),
                'descripcion'      => trim($_POST['descripcion'] ?? ''),
                'cantidad'         => (float) ($_POST['cantidad'] ?? 0),
                'precio_unitario'  => (float) ($_POST['precio_unitario'] ?? 0),
                'descuento'        => (float) ($_POST['descuento'] ?? 0),
                'observacion_item' => trim($_POST['observacion_item'] ?? ''),
                'lote'             => trim($_POST['lote'] ?? ''),
                'caducidad'        => trim($_POST['caducidad'] ?? ''),
                'nup'              => trim($_POST['nup'] ?? ''),
            ], $this->getEmpresaConfig($idEmpresa));
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

    /** Deshace un "Eliminar ítem" (vuelve a 'pendiente'). */
    public function restaurarLineaAjax(): void
    {
        $this->requireActualizar();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            $idLinea   = (int) ($_POST['id_linea'] ?? 0);
            $idComanda = (int) ($_POST['id_comanda'] ?? 0);
            if ($idLinea <= 0 || $idComanda <= 0) throw new Exception('Línea no válida.');
            $this->service->restaurarLinea($idLinea, $idComanda, $idEmpresa, $idUsuario);
            $this->json(['ok' => true, 'msg' => 'Ítem restaurado.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /** Descuento por línea (% o $, ya resuelto a $ por el cliente) — mismo patrón que el POS mostrador. */
    public function actualizarDescuentoLineaAjax(): void
    {
        $this->requireActualizar();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            $idLinea   = (int) ($_POST['id_linea'] ?? 0);
            $idComanda = (int) ($_POST['id_comanda'] ?? 0);
            $descuento = (float) ($_POST['descuento'] ?? 0);
            if ($idLinea <= 0 || $idComanda <= 0) throw new Exception('Línea no válida.');
            $this->service->actualizarDescuentoLinea($idLinea, $idComanda, $idEmpresa, $idUsuario, $descuento);
            $this->json(['ok' => true, 'msg' => 'Descuento aplicado.']);
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
            $this->json(['ok' => true, 'msg' => $n > 0 ? "Se enviaron {$n} ítem(s) a preparación." : 'No había ítems pendientes por enviar.']);
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

    /**
     * Selector de ítems del mesero: combina la carta del Menú (con foto, la
     * carátula pensada para elegir rápido) con el catálogo completo de
     * Productos. Un ítem del menú que ya está vinculado a un producto
     * reemplaza a ese producto en la lista (no se repite la tarjeta).
     */
    public function getProductosAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar = trim($_GET['q'] ?? '');

        try {
            $menuRepo = new MenuRepository();
            $menuItems = $menuRepo->getDisponibles($idEmpresa, $buscar);
            $idsProductoEnMenu = array_filter(array_map(fn($m) => (int) ($m['id_producto'] ?? 0), $menuItems));

            $repo = new \App\repositories\modulos\ProductoRepository();
            $result = $repo->getListado($idEmpresa, $buscar, 1, 24, 'nombre', 'ASC', null, 'venta', true);

            $rows = [];
            foreach ($menuItems as $m) {
                $rows[] = [
                    'origen'         => 'menu',
                    'id_menu_item'   => (int) $m['id'],
                    'id_producto'    => !empty($m['id_producto']) ? (int) $m['id_producto'] : null,
                    'nombre'         => $m['nombre'],
                    'precio_base'    => $m['precio'],
                    'porcentaje_iva' => $m['porcentaje_iva'] ?? 0,
                    'imagen'         => $m['imagen'] ?: null,
                    'codigo'         => $m['producto_codigo'] ?? '',
                    'codigo_barras'  => $m['codigo_barras'] ?? '',
                    'codigo_auxiliar'=> $m['codigo_auxiliar'] ?? '',
                    'inventariable'  => $m['inventariable'] ?? false,
                    'tipo_produccion'=> $m['tipo_produccion'] ?? '',
                ];
            }
            foreach ($result['rows'] as $p) {
                if (in_array((int) $p['id'], $idsProductoEnMenu, true)) continue;
                $rows[] = [
                    'origen'         => 'producto',
                    'id_menu_item'   => null,
                    'id_producto'    => (int) $p['id'],
                    'nombre'         => $p['nombre'],
                    'precio_base'    => $p['precio_base'],
                    'porcentaje_iva' => $p['porcentaje_iva_final'] ?? 0,
                    'imagen'         => $p['imagen'] ?: null,
                    'codigo'         => $p['codigo'] ?? '',
                    'codigo_barras'  => $p['codigo_barras'] ?? '',
                    'codigo_auxiliar'=> $p['codigo_auxiliar'] ?? '',
                    'inventariable'  => $p['inventariable'] ?? false,
                    'tipo_produccion'=> $p['tipo_produccion'] ?? '',
                ];
            }

            $this->json(['ok' => true, 'data' => $rows]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'data' => [], 'error' => $e->getMessage()]);
        }
    }

    /**
     * Lotes con stock disponible para un producto, en la bodega elegida en
     * comandas/ver — mismo origen de datos que CajaPosController::getLotesAjax.
     */
    public function getLotesAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idProducto = (int) ($_GET['id_producto'] ?? 0);
        $idBodega = (int) ($_GET['id_bodega'] ?? 0);

        if ($idProducto <= 0) {
            $this->json(['ok' => false, 'error' => 'Producto no válido.']);
        }
        if ($idBodega <= 0) {
            $this->json(['ok' => true, 'data' => [], 'stock_total' => 0]);
        }

        $repoInv = new \App\repositories\modulos\InventarioRepository();
        $lotes = $repoInv->getLotesDisponibles($idProducto, $idBodega, $idEmpresa);
        $stockTotal = $repoInv->getStockActual($idProducto, $idBodega, $idEmpresa);

        $this->json(['ok' => true, 'data' => $lotes, 'stock_total' => $stockTotal]);
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

    /** Formas de pago de la empresa — mismo endpoint/criterio que CajaPosController::getFormasPagoAjax. */
    public function getFormasPagoAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $repo = new \App\repositories\modulos\FormaPagoRepository();
        $formas = $repo->getFormasFiltradas($idEmpresa, 'INGRESO');
        $formas = array_values(array_filter($formas, fn($f) => strtoupper((string) ($f['tipo'] ?? '')) !== 'ANTICIPO'));
        foreach ($formas as &$f) {
            $f['codigo_sri'] = $this->mapearCodigoSriFormaPago($f);
        }
        unset($f);
        $this->json(['ok' => true, 'data' => $formas]);
    }

    /** Mismo criterio que CajaPosController::mapearCodigoSriFormaPago — traduce el tipo de forma de pago al código SRI. */
    private function mapearCodigoSriFormaPago(array $f): string
    {
        $tipo = strtoupper((string) ($f['tipo'] ?? ''));
        if ($tipo === 'BANCO') return '20';
        if ($tipo === 'TARJETA') return strtoupper((string) ($f['modalidad_tarjeta'] ?? '')) === 'DEBITO' ? '16' : '19';
        if ($tipo === 'PAYPHONE') return '19';
        return '01';
    }

    // ─── Cobro / división de cuenta ────────────────────────────────────────────

    public function crearGrupoCobroAjax(): void
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
            $etiqueta = trim($_POST['etiqueta'] ?? '');

            $idGrupo = $this->service->crearGrupoCobro($idComanda, $idEmpresa, $idUsuario, $idsLineas, $etiqueta);
            $this->json(['ok' => true, 'msg' => 'Grupo de cobro creado.', 'id' => $idGrupo]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function eliminarGrupoCobroAjax(): void
    {
        $this->requireActualizar();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            $idGrupo   = (int) ($_POST['id_grupo'] ?? 0);
            $idComanda = (int) ($_POST['id_comanda'] ?? 0);
            if ($idGrupo <= 0 || $idComanda <= 0) throw new Exception('Grupo no válido.');
            $this->service->eliminarGrupoCobro($idGrupo, $idComanda, $idEmpresa, $idUsuario);
            $this->json(['ok' => true, 'msg' => 'Grupo deshecho; sus ítems volvieron a quedar disponibles.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function cobrarGrupoAjax(): void
    {
        $this->requireCrear();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            $idGrupo = (int) ($_POST['id_grupo'] ?? 0);
            if ($idGrupo <= 0) throw new Exception('Grupo no válido.');

            $tipoDocumento = strtoupper(trim($_POST['tipo_documento'] ?? 'RECIBO'));
            $rutaDocumento = $tipoDocumento === 'FACTURA' ? 'modulos/factura-venta' : 'modulos/recibo-venta';
            if (!\App\Helpers\Permisos::puedeCrear($rutaDocumento)) {
                throw new Exception('No tienes permiso para generar ' . ($tipoDocumento === 'FACTURA' ? 'facturas' : 'recibos de venta') . '.');
            }

            $tipoOperacionBancaria = strtoupper(trim($_POST['tipo_operacion_bancaria'] ?? ''));
            $fechaCobro = trim($_POST['fecha_cobro'] ?? '');
            if ($tipoOperacionBancaria === 'CHEQUE' && $fechaCobro === '') {
                throw new Exception('Indica la fecha de cobro del cheque.');
            }

            $res = $this->service->cobrarGrupo($idGrupo, $idEmpresa, $idUsuario, [
                'id_punto_emision'        => (int) ($_POST['id_punto_emision'] ?? 0),
                'id_cliente'              => (int) ($_POST['id_cliente'] ?? 0),
                'tipo_documento'          => $tipoDocumento,
                'forma_pago'              => trim($_POST['forma_pago'] ?? '01'),
                'id_forma_pago_empresa'   => (int) ($_POST['id_forma_pago'] ?? 0),
                'tipo_operacion_bancaria' => $tipoOperacionBancaria,
                'numero_operacion'        => trim($_POST['numero_operacion'] ?? ''),
                'fecha_cobro'             => $fechaCobro,
                'id_bodega'               => (int) ($_POST['id_bodega'] ?? 0),
            ], $this->getEmpresaConfig($idEmpresa));

            $this->json(['ok' => true, 'msg' => 'Cobro registrado: ' . ($res['numero_documento'] ?? ''), 'data' => $res]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /** Mismo criterio que CajaPosController::getEmpresaConfig — datos de empresa + config del primer establecimiento. */
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
}
