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
        $idPuntoEmision = (int) ($_SESSION['pos_id_punto_emision'] ?? 0);
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

        $estConfig = $this->getEmpresaConfig($idEmpresa);
        $toBool = fn($v) => ($v === true || $v === 't' || $v === 'true' || $v === 1 || $v === '1');

        $this->view('modulos.caja_sesion.venta', [
            'titulo' => 'Punto de Venta',
            'rutaModulo' => self::RUTA_MODULO,
            'idPuntoEmision' => $idPuntoEmision,
            'sesion' => $sesion,
            'obligatorioLotes' => $toBool($estConfig['obligatorio_lotes'] ?? false),
            'obligatorioCaducidad' => $toBool($estConfig['obligatorio_caducidad'] ?? false),
            'obligatorioNup' => $toBool($estConfig['obligatorio_nup'] ?? false),
            'soloStockPositivo' => $toBool($estConfig['factura_solo_stock_positivo'] ?? false),
            'limiteConsumidorFinal' => (float) ($estConfig['valor_limite_consumidor_final'] ?? 50),
            'puedeFactura' => \App\Helpers\Permisos::puedeCrear('modulos/factura-venta'),
            'puedeRecibo' => \App\Helpers\Permisos::puedeCrear('modulos/recibo-venta'),
            'bodegas' => (new Empresa())->getBodegas($idEmpresa),
            'empresa' => $estConfig,
            'categorias' => (new \App\repositories\modulos\CategoriaRepository())
                ->getListado($idEmpresa, '', 1, 0, 'nombre', 'ASC')['rows'],
            'marcas' => (new \App\repositories\modulos\MarcaRepository())
                ->getListado($idEmpresa, '', 1, 0, 'nombre', 'ASC')['rows'],
        ]);
    }

    public function getProductosAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar = trim($_GET['q'] ?? '');

        $repo = new ProductoRepository();
        $result = $repo->getListado($idEmpresa, $buscar, 1, 24, 'nombre', 'ASC', null, 'venta', true);
        $rows = $result['rows'];

        // Igual que Factura de Venta/Recibos de Venta: variantes (Color/Talla,
        // con recargo opcional de precio) por producto, para el selector del POS.
        foreach ($rows as &$p) {
            $p['variantes'] = $repo->getVariantes((int) $p['id'], $idEmpresa);
        }
        unset($p);

        $idBodega = $this->resolverBodega($idEmpresa, (int) ($_GET['id_bodega'] ?? 0));
        if ($idBodega) {
            $idsInventariables = array_values(array_filter(array_map(
                fn($p) => !empty($p['inventariable']) && ($p['tipo_produccion'] ?? '') !== '02' ? (int) $p['id'] : null,
                $rows
            )));
            $stockPorProducto = (new \App\repositories\modulos\InventarioRepository())
                ->getStockActualPorProductos($idsInventariables, $idBodega, $idEmpresa);
            foreach ($rows as &$p) {
                if (!empty($p['inventariable']) && ($p['tipo_produccion'] ?? '') !== '02') {
                    $p['stock_pos'] = $stockPorProducto[(int) $p['id']] ?? 0.0;
                }
            }
            unset($p);
        }

        $this->json(['ok' => true, 'data' => $rows]);
    }

    /**
     * Lotes con stock disponible para un producto, en la bodega del POS.
     * Mismo origen de datos que usa el selector de lote de Factura de Venta
     * (InventarioRepository::getLotesDisponibles), sin depender de ese módulo.
     */
    public function getLotesAjax(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idProducto = (int) ($_GET['id_producto'] ?? 0);
        if ($idProducto <= 0) {
            $this->json(['ok' => false, 'error' => 'Producto no válido.'], 400);
        }

        $idBodega = $this->resolverBodega($idEmpresa, (int) ($_GET['id_bodega'] ?? 0));
        if (!$idBodega) {
            $this->json(['ok' => true, 'data' => [], 'stock_total' => 0]);
        }

        $repoInv = new \App\repositories\modulos\InventarioRepository();
        $lotes = $repoInv->getLotesDisponibles($idProducto, $idBodega, $idEmpresa);
        $stockTotal = $repoInv->getStockActual($idProducto, $idBodega, $idEmpresa);

        $this->json(['ok' => true, 'data' => $lotes, 'stock_total' => $stockTotal]);
    }

    /**
     * Búsqueda de clientes para el selector del POS (mismo repositorio que
     * usa Factura de Venta), autocontenido bajo el permiso de este módulo.
     */
    public function getClientesAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar = trim($_GET['q'] ?? '');

        $repo = new \App\repositories\modulos\ClienteRepository();
        $result = $repo->getListado($idEmpresa, $buscar, 1, 10, 'nombre', 'ASC', null, true);

        $this->json(['ok' => true, 'data' => $result['rows']]);
    }

    /**
     * Catálogo de tipos de identificación (para el mini-formulario de
     * "nuevo cliente"), tomado del mismo modelo que usa el módulo Clientes.
     * Se excluye "Consumidor Final": este formulario existe precisamente
     * para capturar un cliente identificado (ver límite en PosVentaService).
     */
    public function getTiposIdClienteAjax(): void
    {
        $this->requireLeer();
        $modelTipos = new \App\models\IdentificadorCompradorVendedor();
        $todos = $modelTipos->getAll('codigo', 'ASC');
        $tipos = array_values(array_filter($todos, function ($r) {
            if ((int) ($r['tipo'] ?? 0) !== 1 || (int) ($r['status'] ?? 1) !== 1) {
                return false;
            }
            $nombre = strtoupper((string) ($r['nombre'] ?? ''));
            $codigo = strtoupper((string) ($r['codigo'] ?? ''));
            return !str_contains($nombre, 'CONSUMIDOR') && !str_contains($codigo, 'CONSUMIDOR');
        }));

        $this->json(['ok' => true, 'data' => $tipos]);
    }

    /**
     * Formas de pago configuradas por la empresa (empresa_formas_pago —
     * bancos, tarjetas, efectivo con nombre propio), no los 3 botones fijos
     * de antes. Se excluye ANTICIPO: aplicar un anticipo existente es un
     * flujo aparte, no "cómo pagó" una venta de mostrador.
     */
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

    /**
     * Traduce el tipo de forma de pago de la empresa al código del catálogo
     * SRI que exige el documento (Factura/Recibo). No hay columna de mapeo
     * en empresa_formas_pago, así que se resuelve por tipo/modalidad.
     */
    private function mapearCodigoSriFormaPago(array $f): string
    {
        $tipo = strtoupper((string) ($f['tipo'] ?? ''));
        if ($tipo === 'BANCO') {
            return '20'; // Otros con utilización del sistema financiero (transferencia)
        }
        if ($tipo === 'TARJETA') {
            return strtoupper((string) ($f['modalidad_tarjeta'] ?? '')) === 'DEBITO' ? '16' : '19';
        }
        if ($tipo === 'PAYPHONE') {
            return '19'; // Payphone no distingue modalidad; se asume tarjeta de crédito
        }
        return '01'; // Efectivo / sin utilización del sistema financiero
    }

    /**
     * Genera un enlace de pago con tarjeta (Payphone, "cajita") por el total
     * actual del carrito y lo envía por WhatsApp — mismo flujo y misma
     * plantilla aprobada ('link_pago_payphone') que ya usa Factura de Venta
     * (FacturaVentaController::enviarLinkPagoPorWhatsapp), pero sin exigir un
     * documento previo: el POS todavía no ha creado la venta en este punto.
     *
     * Importante: a diferencia de Factura de Venta, aquí NO hay un manejador
     * que complete la venta automáticamente cuando el cliente paga (ver
     * PayphoneController::procesarAprobacion, solo actúa para 'factura_venta').
     * El cajero debe confirmar el pago y presionar "Cobrar" igual que siempre.
     */
    public function enviarLinkPagoWhatsappAjax(): void
    {
        $this->requireCrear();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $idPuntoEmision = (int) ($_POST['id_punto_emision'] ?? 0);
        $telefono = preg_replace('/[^0-9]/', '', trim($_POST['telefono'] ?? ''));
        $nombreCliente = trim($_POST['nombre_cliente'] ?? '') ?: 'Cliente';
        $monto = (float) ($_POST['monto'] ?? 0);

        if (empty($telefono)) {
            $this->json(['ok' => false, 'error' => 'Ingresa un número de WhatsApp.']);
        }
        if (str_starts_with($telefono, '593') && strlen($telefono) !== 12) {
            $this->json(['ok' => false, 'error' => 'El número para Ecuador (593) debe tener exactamente 12 dígitos.']);
        }
        if ($monto <= 0) {
            $this->json(['ok' => false, 'error' => 'El carrito está vacío.']);
        }

        try {
            $stmt = $this->db->prepare("SELECT * FROM whatsapp_plantillas WHERE nombre = 'link_pago_payphone' AND id_empresa = ?");
            $stmt->execute([$idEmpresa]);
            $plantillaMeta = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$plantillaMeta || $plantillaMeta['estado_meta'] !== 'APPROVED') {
                throw new \Exception('No tienes la plantilla de WhatsApp "link_pago_payphone" aprobada. Configúrala en Plantillas de WhatsApp (igual que para Factura de Venta).');
            }

            $ppRepo = new \App\repositories\PayphoneRepository();
            $formaCobro = $ppRepo->getFormaCobroPayphone($idEmpresa);
            if (!$formaCobro) {
                throw new \Exception('No hay una forma de cobro de tipo "Payphone" activa y configurada para la empresa.');
            }

            $descripcion = 'Venta POS' . ($idPuntoEmision > 0 ? ' — Punto #' . $idPuntoEmision : '');

            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $urlBaseAbs = $scheme . '://' . $host . rtrim(BASE_URL, '/');

            $pp = new \App\Services\PayphoneService($ppRepo);
            $cajita = $pp->prepararCajita($idEmpresa, [
                'monto' => \App\Services\PayphoneService::dolaresACentavos($monto),
                'descripcion' => $descripcion,
                'modulo' => 'pos',
                'id_referencia' => null,
                'url_retorno' => $urlBaseAbs . '/payphone/retorno',
                'url_cancelacion' => $urlBaseAbs . '/payphone/cancelacion',
                'url_exito' => null,
                'id_usuario' => $idUsuario,
                'id_forma_cobro' => (int) $formaCobro['id'],
            ]);

            if (empty($cajita['ok'])) {
                throw new \Exception($cajita['mensaje'] ?? 'No se pudo generar el enlace de pago.');
            }

            $urlPago = $urlBaseAbs . '/pago/' . $cajita['client_transaction_id'];
            $valoresVariables = [$nombreCliente, '$' . number_format($monto, 2), $descripcion, $urlPago];

            $componentes = json_decode($plantillaMeta['componentes'], true) ?? [];
            $apiComponents = [];
            foreach ($componentes as $comp) {
                if (($comp['type'] ?? '') === 'BODY') {
                    $parameters = array_map(fn($v) => ['type' => 'text', 'text' => (string) $v], $valoresVariables);
                    $apiComponents[] = ['type' => 'body', 'parameters' => $parameters];
                    break;
                }
            }

            $whatsappService = new \App\services\WhatsappService();
            $result = $whatsappService->sendTemplateMessage($idEmpresa, $telefono, $plantillaMeta['nombre'], $plantillaMeta['idioma'], $apiComponents);
            if (!$result['success']) {
                throw new \Exception('Error enviando el enlace de pago: ' . $result['message']);
            }

            try {
                $repoMsj = new \App\repositories\modulos\WhatsappMensajeRepository();
                $idChat = $repoMsj->getOrCreateChat($idEmpresa, $telefono, $nombreCliente, 'Enlace de pago enviado', false);
                $repoMsj->saveMessage(
                    $idEmpresa,
                    $idChat,
                    'OUT',
                    $telefono,
                    'template',
                    ['template' => $plantillaMeta['nombre'], 'variables' => $valoresVariables],
                    $result['data']['messages'][0]['id'] ?? null,
                    'sent'
                );
            } catch (\Throwable $ex) {
                // No detiene el flujo si falla el guardado del historial.
            }

            $this->json(['ok' => true, 'msg' => 'Enlace de pago por $' . number_format($monto, 2) . ' enviado por WhatsApp al ' . $telefono . '.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Aviso de links de pago con tarjeta enviados por el cajero que Payphone
     * todavía no confirma (la respuesta puede tardar hasta ~10 minutos). Se
     * consulta por polling desde venta.php; en cuanto Payphone aprueba/rechaza/
     * cancela el pago (PayphoneController::procesarAprobacion actualiza el
     * estado), la transacción deja de venir en esta lista sola, sin que nadie
     * la tenga que marcar como resuelta.
     */
    public function getPagosTarjetaPendientesAjax(): void
    {
        $this->requireLeer();

        // Este endpoint solo lee (nunca escribe) y se consulta por polling —
        // liberar el lock de sesión cuanto antes, mismo criterio que el
        // aviso unificado del navbar (ContadoresController::navbarAjax).
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            $repo = new \App\repositories\PayphoneRepository();
            $rows = $repo->getPendientesPorUsuario($idEmpresa, 'pos', $idUsuario);
            $data = array_map(fn($r) => [
                'descripcion' => $r['descripcion'],
                'monto' => round(((int) $r['monto']) / 100, 2),
                'creado' => $r['created_at'],
            ], $rows);
            $this->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'data' => []]);
        }
    }

    public function cobrarAjax(): void
    {
        $this->requireCrear();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $idPuntoEmision = (int) ($_POST['id_punto_emision'] ?? 0);
        $formaPago = trim($_POST['forma_pago'] ?? '01');
        $idFormaPagoEmpresa = (int) ($_POST['id_forma_pago'] ?? 0);
        $idCliente = (int) ($_POST['id_cliente'] ?? 0);
        $idBodega = (int) ($_POST['id_bodega'] ?? 0);
        $tipoDocumento = strtoupper(trim($_POST['tipo_documento'] ?? 'RECIBO'));
        $tipoOperacionBancaria = strtoupper(trim($_POST['tipo_operacion_bancaria'] ?? ''));
        $numeroOperacion = trim($_POST['numero_operacion'] ?? '');
        $fechaCobro = trim($_POST['fecha_cobro'] ?? '');
        $items = json_decode($_POST['items'] ?? '[]', true);

        try {
            if (!is_array($items)) {
                throw new \Exception('El carrito no es válido.');
            }
            if ($tipoOperacionBancaria === 'CHEQUE' && $fechaCobro === '') {
                throw new \Exception('Indica la fecha de cobro del cheque.');
            }
            // No basta con ocultar la opción en la pantalla: el permiso se
            // exige también aquí, por si alguien arma la petición a mano.
            $rutaDocumento = $tipoDocumento === 'FACTURA' ? 'modulos/factura-venta' : 'modulos/recibo-venta';
            if (!\App\Helpers\Permisos::puedeCrear($rutaDocumento)) {
                throw new \Exception('No tienes permiso para generar ' . ($tipoDocumento === 'FACTURA' ? 'facturas' : 'recibos de venta') . '.');
            }

            $res = $this->ventaService->cobrar([
                'id_empresa' => $idEmpresa,
                'id_usuario' => $idUsuario,
                'id_punto_emision' => $idPuntoEmision,
                'tipo_operacion_bancaria' => $tipoOperacionBancaria,
                'numero_operacion' => $numeroOperacion,
                'fecha_cobro' => $fechaCobro,
                'forma_pago' => $formaPago,
                'id_forma_pago_empresa' => $idFormaPagoEmpresa,
                'id_cliente' => $idCliente,
                'id_bodega' => $idBodega,
                'tipo_documento' => $tipoDocumento,
                'items' => $items,
            ], $this->getEmpresaConfig($idEmpresa));

            $this->json(['ok' => true, 'msg' => 'Venta registrada correctamente.', 'data' => $res]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Bodega elegida por el cajero (si la mandó y es de esta empresa) o, si
     * no, la primera bodega activa — para no romper cuando solo hay una.
     */
    private function resolverBodega(int $idEmpresa, int $idBodegaElegida): ?int
    {
        if ($idBodegaElegida > 0) {
            $bodegas = (new Empresa())->getBodegas($idEmpresa);
            foreach ($bodegas as $b) {
                if ((int) $b['id'] === $idBodegaElegida) {
                    return $idBodegaElegida;
                }
            }
        }
        return $this->ventaService->getBodegaActiva($idEmpresa);
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

        // Recordar el punto de emisión elegido: la pantalla de venta lo lee de
        // sesión (URL limpia, sin id en la dirección).
        $_SESSION['pos_id_punto_emision'] = $idPuntoEmision;

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
            unset($_SESSION['pos_id_punto_emision']);
            $this->json(['ok' => true, 'msg' => 'Caja cerrada correctamente.', 'sesion' => $sesion]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }
}
