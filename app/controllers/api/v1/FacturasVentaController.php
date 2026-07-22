<?php
/**
 * Controlador API v1: Facturas de Venta.
 * Adaptador HTTP→JSON: reutiliza FacturaVentaService/FacturaVentaRules/
 * FacturaVentaRepository tal cual los usa la web, sin duplicar lógica.
 *
 * Alcance deliberado (decidido con el usuario, no un descuido):
 * - Cada línea viene SIEMPRE de un producto del catálogo (nunca "ítem libre"),
 *   sin lotes/caducidad/NUP/ICE — mismo alcance "básico" ya decidido para el
 *   módulo Productos en la app. Si el establecimiento exige esos datos
 *   (obligatorio_lotes/caducidad/nup), la creación/edición falla con el mismo
 *   mensaje de validación que usa la web (FacturaVentaRules no se relaja).
 * - Un solo pago por factura, con la forma de pago SRI que el usuario elige
 *   (la app precarga el default de "Configuración de Facturación" del
 *   establecimiento — id_forma_pago_sri_def —, igual que hace el JS de la web,
 *   ya que ese default tampoco lo aplica el backend allá).
 * - Solo se puede EDITAR una factura mientras está en 'borrador' (mismo candado
 *   que impone FacturaVentaService::actualizar() para la web) y la edición NO
 *   permite cambiar establecimiento/punto de emisión/secuencial — eso se fija
 *   al crear y viaja tal cual desde la cabecera ya guardada.
 * - El envío al SRI reutiliza SriEnvioService::enviarFacturaVenta() tal cual
 *   (firma XAdES-BES + polling de autorización, que puede bloquear la petición
 *   HTTP hasta ~18s con sleep() en el peor caso). El cliente móvil debe usar un
 *   timeout generoso para esta llamada específica y avisar al usuario que no
 *   cierre la app ni pierda señal durante la espera.
 * - "Cobrar" (registrar un ingreso contra la factura) reutiliza el mismo
 *   IngresoService::crear() que usa el "cobro rápido" de la web (mismo payload,
 *   ver FacturaVentaController::registrarCobroRapidoAjax en el módulo web) —
 *   pero a propósito es MÁS estricto que la web: solo se habilita si la factura
 *   ya está 'autorizado' por el SRI (la web permite cobrar en efectivo/
 *   transferencia incluso en borrador; para móvil eso es un riesgo — no cobrar
 *   contra un documento que el SRI podría todavía rechazar). El punto de
 *   emisión de Ingresos se resuelve automáticamente (el primero activo del
 *   mismo establecimiento de la factura) — no se le pide al usuario elegirlo,
 *   es un detalle técnico irrelevante para un cobro rápido desde el celular.
 */

declare(strict_types=1);

namespace App\controllers\api\v1;

use App\controllers\api\ApiBaseController;
use App\core\Database;
use App\models\Empresa;
use App\models\FormaPagoSri;
use App\models\SriEnvioLog;
use App\models\Vendedor;
use App\repositories\modulos\BodegaRepository;
use App\repositories\modulos\EmpresaRepository;
use App\repositories\modulos\FacturaVentaRepository;
use App\repositories\modulos\IngresoRepository;
use App\Rules\modulos\FacturaVentaRules;
use App\Rules\modulos\IngresoRules;
use App\Services\LogSistemaService;
use App\Services\modulos\FacturaVentaService;
use App\Services\modulos\IngresoService;
use App\Services\SecuencialService;
use App\Services\Sri\SriEnvioService;
use PDO;
use Throwable;

class FacturasVentaController extends ApiBaseController
{
    private const TIPO_DOCUMENTO = 'Facturas de venta';
    private const CODIGO_IMPUESTO_IVA = '2';

    private FacturaVentaService $service;
    private FacturaVentaRepository $repository;

    public function __construct()
    {
        parent::__construct();
        $this->repository = new FacturaVentaRepository();
        $this->service = new FacturaVentaService($this->repository, new FacturaVentaRules(), new LogSistemaService());
    }

    protected function getRutaModulo(): string
    {
        return 'modulos/factura-venta';
    }

    /**
     * GET /api/v1/facturas-venta/listar?buscar=&page=
     */
    public function listar(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar = trim($_GET['buscar'] ?? '');
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(50, max(1, (int) ($_GET['per_page'] ?? 20)));

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $result = $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, 'fecha_emision', 'DESC', $idUsuarioFiltro);
        $total = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        // getListado() ya trae total_cobrado/total_nc/total_retencion por fila
        // (las usa para el badge de estado de pago) — solo falta el saldo en sí.
        $rows = $result['rows'];
        foreach ($rows as &$r) {
            $saldo = (float) $r['importe_total']
                - (float) ($r['total_cobrado'] ?? 0)
                - (float) ($r['total_nc'] ?? 0)
                - (float) ($r['total_retencion'] ?? 0);
            $r['saldo_pendiente'] = max(0.0, round($saldo, 2));
        }
        unset($r);

        $this->jsonOk($rows, [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
        ]);
    }

    /**
     * GET /api/v1/facturas-venta/obtener?id=123
     */
    public function obtener(): void
    {
        $this->requireLeer();

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->jsonError('ID_REQUERIDO', 'Falta id.', 422);
        }

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $factura = $this->repository->getPorId($id);
        if (!$factura || (int) ($factura['id_empresa'] ?? 0) !== $idEmpresa) {
            $this->jsonError('NO_ENCONTRADO', 'Factura no encontrada.', 404);
        }

        $detalles = $this->repository->getDetalles($id);
        foreach ($detalles as &$d) {
            $d['impuestos'] = $this->repository->getImpuestosDetalle((int) $d['id']);
        }
        unset($d);

        $this->jsonOk([
            'cabecera' => $factura,
            'detalles' => $detalles,
            'pagos' => $this->repository->getPagos($id),
            // Desglose de subtotales/IVA por tarifa (mismo agrupado que la web muestra
            // en "Subtotal {tarifa}" / "(+) IVA {tarifa}%"), listo para pintar tal cual.
            'totales_iva' => $this->agruparTotalesPorTarifa($detalles),
            // Para decidir si mostrar "Cobrar" y precargar el monto. Igual que el
            // saldo que ya muestra la pestaña "Pagos" de la web (importe - cobros -
            // retenciones - notas de crédito), no solo importe - cobros.
            'saldo_pendiente' => $this->calcularSaldoPendiente($id, $idEmpresa, $factura)['saldo_pendiente'],
        ]);
    }

    /**
     * Agrupa por codigo_porcentaje (el código SRI de la tarifa: '0'=0%, '2'=12%,
     * '3'=14%, '4'=15%, '5'=Exento, '6'=No objeto...) en vez de por id_tarifa_iva:
     * ventas_detalle no tiene esa columna en este esquema, así que codigo_porcentaje
     * (persistido siempre en ventas_detalle_impuestos) es la única referencia
     * confiable para reconstruir el desglose de una factura ya guardada.
     *
     * @return array<int,array{codigo_porcentaje:string,nombre_tarifa_iva:string,porcentaje:float,base:float,iva:float}>
     */
    private function agruparTotalesPorTarifa(array $detalles): array
    {
        $codigos = [];
        foreach ($detalles as $d) {
            foreach (($d['impuestos'] ?? []) as $imp) {
                $codigos[(string) $imp['codigo_porcentaje']] = true;
            }
        }

        $nombres = [];
        if (!empty($codigos)) {
            $db = Database::getConnection();
            $placeholders = implode(',', array_fill(0, count($codigos), '?'));
            $st = $db->prepare("SELECT codigo, tarifa, status FROM tarifa_iva WHERE codigo IN ({$placeholders})");
            $st->execute(array_keys($codigos));
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                // Si hay varias tarifas con el mismo código (histórico), preferir la activa.
                if (!isset($nombres[$row['codigo']]) || (int) $row['status'] === 1) {
                    $nombres[$row['codigo']] = $row['tarifa'];
                }
            }
        }

        $grupos = [];
        foreach ($detalles as $d) {
            foreach (($d['impuestos'] ?? []) as $imp) {
                $codigo = (string) $imp['codigo_porcentaje'];
                $pct = (float) $imp['tarifa'];

                if (!isset($grupos[$codigo])) {
                    $grupos[$codigo] = [
                        'codigo_porcentaje' => $codigo,
                        'nombre_tarifa_iva' => $nombres[$codigo] ?? "{$pct}%",
                        'porcentaje' => $pct,
                        'base' => 0.0,
                        'iva' => 0.0,
                    ];
                }

                $grupos[$codigo]['base'] += (float) ($imp['base_imponible'] ?? 0);
                $grupos[$codigo]['iva'] += (float) ($imp['valor'] ?? 0);
            }
        }

        foreach ($grupos as &$g) {
            $g['base'] = round($g['base'], 2);
            $g['iva'] = round($g['iva'], 2);
        }
        unset($g);

        return array_values($grupos);
    }

    /**
     * GET /api/v1/facturas-venta/series
     * Igual que Pedidos: establecimientos con sus puntos de emisión, más los datos
     * de "Configuración de Facturación" que necesita el formulario móvil (forma de
     * pago por defecto, límite de consumidor final), más la serie favorita del
     * usuario (la misma estrellita de la web — PreferenciasHelper::renderEstrellaFavorito
     * guarda el 'id_punto_emision' favorito bajo el módulo 'modulos/factura-venta';
     * no existe un favorito de establecimiento separado, se deriva del punto).
     */
    public function series(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $empresaModel = new Empresa();
        $estRepo = new EmpresaRepository();
        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);

        $resultado = [];
        foreach ($establecimientos as $est) {
            $idEst = (int) $est['id'];
            $puntos = $empresaModel->getPuntosEmision($idEst);
            $config = $estRepo->getEstablecimientoConfig($idEst) ?? [];

            $resultado[] = [
                'id_establecimiento' => $idEst,
                'establecimiento' => $est['codigo'],
                'direccion' => $est['direccion'] ?? '',
                'id_forma_pago_sri_def' => isset($config['id_forma_pago_sri_def']) ? (int) $config['id_forma_pago_sri_def'] : null,
                'valor_limite_consumidor_final' => isset($config['valor_limite_consumidor_final']) ? (float) $config['valor_limite_consumidor_final'] : 50.0,
                'puntos_emision' => array_map(static function (array $p): array {
                    return [
                        'id_punto_emision' => (int) $p['id'],
                        'punto_emision' => $p['codigo_punto'],
                    ];
                }, $puntos),
            ];
        }

        $idPuntoFavorito = null;
        try {
            $prefService = new \App\Services\UsuarioPreferenciaService(new \App\repositories\UsuarioPreferenciaRepository());
            $prefs = $prefService->obtenerPreferencias($idUsuario, $idEmpresa, $this->getRutaModulo());
            $idPuntoFavorito = isset($prefs['id_punto_emision']) ? (int) $prefs['id_punto_emision'] : null;
        } catch (Throwable $e) {
            // Sin favorito configurado o error leyéndolo: el cliente cae al primero disponible.
        }

        $this->jsonOk([
            'establecimientos' => $resultado,
            'id_punto_emision_favorito' => $idPuntoFavorito,
        ]);
    }

    /**
     * GET /api/v1/facturas-venta/secuencial?id_punto_emision=123
     */
    public function secuencial(): void
    {
        $this->requireLeer();

        $idPuntoEmision = (int) ($_GET['id_punto_emision'] ?? 0);
        if ($idPuntoEmision <= 0) {
            $this->jsonError('ID_PUNTO_EMISION_REQUERIDO', 'Falta id_punto_emision.', 422);
        }

        $res = (new SecuencialService())->obtenerSiguienteSecuencial($idPuntoEmision, self::TIPO_DOCUMENTO);
        $this->jsonOk($res);
    }

    /**
     * GET /api/v1/facturas-venta/catalogos
     */
    public function catalogos(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $bodegaRepo = new BodegaRepository();
        $bodegas = $bodegaRepo->getBodegasPermitidas((int) $_SESSION['id_usuario'], $idEmpresa, (int) $_SESSION['nivel']);

        $formas = (new FormaPagoSri())->getAll();
        $formas = array_values(array_filter($formas, fn($f) => (int) ($f['status'] ?? 1) === 1));

        $this->jsonOk([
            'bodegas' => $bodegas,
            'vendedores' => (new Vendedor())->getActivosPorEmpresa($idEmpresa),
            'formas_pago_sri' => $formas,
        ]);
    }

    /**
     * POST /api/v1/facturas-venta/crear
     * body: {
     *   fecha_emision?, id_cliente, id_establecimiento, id_punto_emision,
     *   establecimiento, punto_emision, secuencial, dias_credito?, observaciones?,
     *   id_vendedor?, id_bodega?, forma_pago (código SRI),
     *   detalles: [{ id_producto, cantidad }]
     * }
     */
    public function crear(): void
    {
        $this->requireCrear();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonError('METODO_NO_PERMITIDO', 'Use POST.', 405);
        }

        $body = $this->getJsonBody();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        $idEstablecimiento = (int) ($body['id_establecimiento'] ?? 0);
        $idPuntoEmision = (int) ($body['id_punto_emision'] ?? 0);
        $establecimiento = trim((string) ($body['establecimiento'] ?? ''));
        $puntoEmision = trim((string) ($body['punto_emision'] ?? ''));
        $secuencial = trim((string) ($body['secuencial'] ?? ''));

        if ($idEstablecimiento <= 0 || $idPuntoEmision <= 0 || $establecimiento === '' || $puntoEmision === '' || $secuencial === '') {
            $this->jsonError('DATOS_INCOMPLETOS', 'Faltan datos de la serie (establecimiento/punto/secuencial).', 422);
        }

        // Revalidar que el secuencial siga disponible justo antes de insertar
        // (igual que en Pedidos): reduce, sin eliminar del todo, la ventana de
        // colisión entre dos celulares facturando casi al mismo tiempo.
        $secuencialInt = (int) ltrim($secuencial, '0');
        $validacion = (new SecuencialService())->validarSecuencial($idPuntoEmision, self::TIPO_DOCUMENTO, $secuencialInt);
        if (empty($validacion['disponible'])) {
            $this->jsonError('SECUENCIAL_NO_DISPONIBLE', $validacion['mensaje'] ?? 'El secuencial ya no está disponible, vuelve a intentar.', 409);
        }

        $data = $this->construirDatosFactura($body, $idEmpresa, $idUsuario, [
            'id_establecimiento' => $idEstablecimiento,
            'id_punto_emision' => $idPuntoEmision,
            'establecimiento' => $establecimiento,
            'punto_emision' => $puntoEmision,
            'secuencial' => $secuencial,
        ]);

        try {
            $id = $this->service->crear($data);
        } catch (Throwable $e) {
            $this->jsonError('ERROR_GUARDAR', $e->getMessage(), 422);
        }

        $this->jsonOk(['id' => $id], [], 201);
    }

    /**
     * POST /api/v1/facturas-venta/actualizar
     * body: igual que crear, más { id }. NO acepta cambiar la serie/secuencial
     * (se conserva la de la factura ya guardada) y solo funciona si sigue en
     * estado 'borrador' — mismo candado que la web.
     */
    public function actualizar(): void
    {
        $this->requireActualizar();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonError('METODO_NO_PERMITIDO', 'Use POST.', 405);
        }

        $body = $this->getJsonBody();
        $id = (int) ($body['id'] ?? 0);
        if ($id <= 0) {
            $this->jsonError('ID_REQUERIDO', 'Falta id.', 422);
        }

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        $existente = $this->repository->getPorId($id);
        if (!$existente || (int) ($existente['id_empresa'] ?? 0) !== $idEmpresa) {
            $this->jsonError('NO_ENCONTRADO', 'Factura no encontrada.', 404);
        }
        if (($existente['estado'] ?? '') !== 'borrador') {
            $this->jsonError('NO_EDITABLE', 'Solo se pueden editar facturas en estado borrador.', 409);
        }

        $data = $this->construirDatosFactura($body, $idEmpresa, $idUsuario, [
            'id_establecimiento' => (int) $existente['id_establecimiento'],
            'id_punto_emision' => (int) $existente['id_punto_emision'],
            'establecimiento' => (string) $existente['establecimiento'],
            'punto_emision' => (string) $existente['punto_emision'],
            'secuencial' => (string) $existente['secuencial'],
        ]);

        try {
            $this->service->actualizar($id, $data);
        } catch (Throwable $e) {
            $this->jsonError('ERROR_GUARDAR', $e->getMessage(), 422);
        }

        $this->jsonOk(['id' => $id]);
    }

    /**
     * POST /api/v1/facturas-venta/enviar-sri
     * body: { id }
     * Solo funciona sobre una factura YA GUARDADA (borrador u otro estado no
     * terminal). Reutiliza SriEnvioService::enviarFacturaVenta() tal cual —
     * puede tardar varios segundos (firma + envío + polling de autorización).
     */
    public function enviarSri(): void
    {
        $this->requireActualizar();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonError('METODO_NO_PERMITIDO', 'Use POST.', 405);
        }

        $body = $this->getJsonBody();
        $id = (int) ($body['id'] ?? 0);
        if ($id <= 0) {
            $this->jsonError('ID_REQUERIDO', 'Falta id.', 422);
        }

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        $existente = $this->repository->getPorId($id);
        if (!$existente || (int) ($existente['id_empresa'] ?? 0) !== $idEmpresa) {
            $this->jsonError('NO_ENCONTRADO', 'Factura no encontrada.', 404);
        }

        try {
            $resultado = (new SriEnvioService())->enviarFacturaVenta($id, $idEmpresa, $idUsuario);
        } catch (Throwable $e) {
            try {
                (new SriEnvioLog())->registrar([
                    'id_empresa' => $idEmpresa,
                    'tipo_comprobante' => 'factura_venta',
                    'id_comprobante' => $id,
                    'clave_acceso' => $existente['clave_acceso'] ?? null,
                    'tipo_ambiente' => $existente['tipo_ambiente'] ?? '1',
                    'accion' => 'error',
                    'estado_sri' => 'ERROR',
                    'mensaje' => $e->getMessage(),
                    'created_by' => $idUsuario,
                ]);
            } catch (Throwable $e2) {
                // No bloquear la respuesta al usuario por un fallo al auditar el error.
            }
            $this->jsonError('ERROR_SRI', $e->getMessage(), 500);
        }

        $this->jsonOk([
            'enviado_ok' => (bool) ($resultado['ok'] ?? false),
            'estado' => $resultado['estado'] ?? null,
            'mensaje' => $resultado['mensaje'] ?? '',
            'numero_autorizacion' => $resultado['numero_autorizacion'] ?? '',
            'fecha_autorizacion' => $resultado['fecha_autorizacion'] ?? '',
            'errores' => $resultado['errores'] ?? [],
        ]);
    }

    /**
     * GET /api/v1/facturas-venta/pdf?id=123
     * Responde el PDF binario tal cual (misma plantilla/servicio que usa la web),
     * no el sobre {ok,data} habitual. Funciona igual para facturas en borrador
     * (exportarPdfAjax de la web solo valida id_empresa, no el estado).
     */
    public function pdf(): void
    {
        $this->requireLeer();

        $id = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        if ($id <= 0) {
            $this->jsonError('ID_REQUERIDO', 'Falta id.', 422);
        }

        $factura = $this->repository->getPorId($id);
        if (!$factura || (int) ($factura['id_empresa'] ?? 0) !== $idEmpresa) {
            $this->jsonError('NO_ENCONTRADO', 'Factura no encontrada.', 404);
        }

        $detalles = $this->repository->getDetalles($id);
        foreach ($detalles as &$d) {
            $d['impuestos'] = $this->repository->getImpuestosDetalle((int) $d['id']);
        }
        unset($d);
        $pagos = $this->repository->getPagos($id);
        $infoAdicional = $this->repository->getInfoAdicional($id);

        $empresaModel = new Empresa();
        $empresa = $empresaModel->getPorId($idEmpresa) ?? [];
        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        if (!empty($establecimientos)) {
            $idEst = (int) ($factura['id_establecimiento'] ?? $establecimientos[0]['id']);
            $est = null;
            foreach ($establecimientos as $e) {
                if ((int) $e['id'] === $idEst) {
                    $est = $e;
                    break;
                }
            }
            $est = $est ?? $establecimientos[0];

            if (!empty($est['logo_ruta'])) $empresa['logo_ruta'] = $est['logo_ruta'];
            if (!empty($est['direccion'])) $empresa['direccion_establecimiento'] = $est['direccion'];
            if (!empty($est['leyenda_pdf_titulo'])) $empresa['leyenda_pdf_titulo'] = $est['leyenda_pdf_titulo'];
            if (!empty($est['leyenda_pdf_mensaje'])) $empresa['leyenda_pdf_mensaje'] = $est['leyenda_pdf_mensaje'];

            try {
                $estConfig = (new EmpresaRepository())->getEstablecimientoConfig((int) $est['id']);
                if ($estConfig) {
                    $estConfig['direccion_matriz'] = $empresa['direccion'] ?? '';
                    $estConfig['direccion_establecimiento'] = $est['direccion'] ?? '';
                    if (!empty($est['logo_ruta'])) $estConfig['logo_ruta'] = $est['logo_ruta'];
                    if (!empty($est['leyenda_pdf_titulo'])) $estConfig['leyenda_pdf_titulo'] = $est['leyenda_pdf_titulo'];
                    if (!empty($est['leyenda_pdf_mensaje'])) $estConfig['leyenda_pdf_mensaje'] = $est['leyenda_pdf_mensaje'];
                    $empresa = array_merge($empresa, $estConfig);
                }
            } catch (Throwable $e) {
            }
        }

        try {
            $renderer = new \App\Services\PlantillasPdfRendererService();
            $plantilla = $renderer->getPlantillaActiva($idEmpresa, 'factura_venta');
            if ($plantilla) {
                $renderer->generar($plantilla, $factura, $detalles, $pagos, $infoAdicional, $empresa);
            } else {
                (new \App\Services\modulos\FacturaVentaPdfService())->generar($factura, $detalles, $pagos, $infoAdicional, $empresa);
            }
        } catch (Throwable $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => ['code' => 'ERROR_PDF', 'message' => $e->getMessage()]]);
        }
        exit;
    }

    /**
     * Arma el $data común a crear()/actualizar(): valida cliente/detalles/forma de
     * pago, calcula totales desde el catálogo de productos (nunca confía en
     * precios/impuestos que mande el cliente) y mezcla la config del
     * establecimiento. $identidad trae establecimiento/punto/secuencial ya
     * resueltos por el caller (nuevos en crear(), fijos desde la cabecera en
     * actualizar()).
     *
     * @param array{id_establecimiento:int,id_punto_emision:int,establecimiento:string,punto_emision:string,secuencial:string} $identidad
     * @return array<string,mixed>
     */
    private function construirDatosFactura(array $body, int $idEmpresa, int $idUsuario, array $identidad): array
    {
        $idCliente = (int) ($body['id_cliente'] ?? 0);
        $lineasBody = is_array($body['detalles'] ?? null) ? $body['detalles'] : [];
        $formaPago = trim((string) ($body['forma_pago'] ?? ''));

        if ($idCliente <= 0) {
            $this->jsonError('DATOS_INCOMPLETOS', 'Selecciona un cliente.', 422);
        }
        if (empty($lineasBody)) {
            $this->jsonError('SIN_DETALLES', 'Agrega al menos un producto.', 422);
        }
        if ($formaPago === '') {
            $this->jsonError('FORMA_PAGO_REQUERIDA', 'Selecciona una forma de pago.', 422);
        }

        $idBodega = !empty($body['id_bodega']) ? (int) $body['id_bodega'] : null;
        $detalles = [];
        $totalSinImpuestos = 0.0;
        $totalIva = 0.0;

        foreach ($lineasBody as $linea) {
            $idProducto = (int) ($linea['id_producto'] ?? 0);
            $cantidad = (float) ($linea['cantidad'] ?? 0);
            if ($idProducto <= 0 || $cantidad <= 0) {
                continue;
            }

            $producto = $this->obtenerProductoParaLinea($idProducto, $idEmpresa);
            if (!$producto) {
                $this->jsonError('PRODUCTO_NO_ENCONTRADO', "El producto #{$idProducto} no existe.", 422);
            }

            $precioUnitario = round((float) $producto['precio_base'], 4);
            $subtotalLinea = round($precioUnitario * $cantidad, 2);
            $tarifaPct = (float) ($producto['porcentaje_iva'] ?? 0);
            $codigoPorcentaje = (string) ($producto['codigo_iva'] ?? '0');
            $valorIva = round($subtotalLinea * $tarifaPct / 100, 2);

            $totalSinImpuestos += $subtotalLinea;
            $totalIva += $valorIva;

            $detalles[] = [
                'id_producto' => $idProducto,
                'id_bodega' => $idBodega,
                'id_unidad_medida' => $producto['id_medida'] ? (int) $producto['id_medida'] : null,
                'codigo_principal' => $producto['codigo'],
                'codigo_auxiliar' => $producto['codigo_auxiliar'],
                'descripcion' => $producto['nombre'],
                'nombre' => $producto['nombre'],
                'cantidad' => $cantidad,
                'precio_unitario' => $precioUnitario,
                'descuento' => 0,
                'precio_total_sin_impuesto' => $subtotalLinea,
                'id_tarifa_iva' => $producto['tarifa_iva'] ? (int) $producto['tarifa_iva'] : null,
                'impuestos' => [[
                    'codigo_impuesto' => self::CODIGO_IMPUESTO_IVA,
                    'codigo_porcentaje' => $codigoPorcentaje,
                    'tarifa' => $tarifaPct,
                    'base_imponible' => $subtotalLinea,
                    'valor' => $valorIva,
                ]],
            ];
        }

        if (empty($detalles)) {
            $this->jsonError('SIN_DETALLES', 'Agrega al menos un producto con cantidad válida.', 422);
        }

        $totalSinImpuestos = round($totalSinImpuestos, 2);
        $totalIva = round($totalIva, 2);
        $importeTotal = round($totalSinImpuestos + $totalIva, 2);

        $empresaModel = new Empresa();
        $empresaData = $empresaModel->getPorId($idEmpresa) ?? [];
        try {
            $estConfig = (new EmpresaRepository())->getEstablecimientoConfig($identidad['id_establecimiento']);
            if ($estConfig) {
                $empresaData = array_merge($empresaData, $estConfig);
            }
        } catch (Throwable $e) {
            // Si falla, el Service usa sus propios defaults (tipo_ambiente '1', etc.)
        }

        return array_merge($identidad, [
            'id_empresa' => $idEmpresa,
            'id_usuario' => $idUsuario,
            'id_cliente' => $idCliente,
            'fecha_emision' => trim((string) ($body['fecha_emision'] ?? '')) ?: date('Y-m-d'),
            'dias_credito' => (int) ($body['dias_credito'] ?? 0),
            'observaciones' => trim((string) ($body['observaciones'] ?? '')) ?: null,
            'id_vendedor' => !empty($body['id_vendedor']) ? (int) $body['id_vendedor'] : null,
            'id_bodega' => $idBodega,
            'total_sin_impuestos' => $totalSinImpuestos,
            'total_descuento' => 0,
            'total_ice' => 0,
            'propina' => 0,
            'importe_total' => $importeTotal,
            'detalles' => $detalles,
            'pagos' => [[
                'forma_pago' => $formaPago,
                'total' => $importeTotal,
                'plazo' => null,
                'unidad_tiempo' => null,
            ]],
            'empresa_config' => $empresaData,
        ]);
    }

    private function obtenerProductoParaLinea(int $idProducto, int $idEmpresa): ?array
    {
        $db = Database::getConnection();
        $st = $db->prepare(
            "SELECT p.id, p.codigo, p.codigo_auxiliar, p.nombre, p.precio_base, p.id_medida, p.tarifa_iva,
                    ti.codigo AS codigo_iva, ti.porcentaje_iva AS porcentaje_iva
             FROM productos p
             LEFT JOIN tarifa_iva ti ON ti.id = p.tarifa_iva
             WHERE p.id = :id AND p.id_empresa = :e AND p.eliminado = false AND p.status = 1"
        );
        $st->execute([':id' => $idProducto, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * GET /api/v1/facturas-venta/formas-cobro
     * Catálogo para el formulario de "Cobrar" (mismo filtro que usa la web en
     * getIngresosCatalogosAjax: activas y aplicables a Ingresos).
     */
    public function formasCobro(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $db = Database::getConnection();
        $st = $db->prepare(
            "SELECT id, nombre, tipo
             FROM empresa_formas_pago
             WHERE id_empresa = ? AND eliminado = false AND activo = true
               AND (aplica_en IN ('AMBAS','INGRESO') OR aplica_en IS NULL)
             ORDER BY nombre ASC"
        );
        $st->execute([$idEmpresa]);
        $this->jsonOk($st->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * POST /api/v1/facturas-venta/cobrar
     * body: {
     *   id_factura, monto, id_forma_cobro, observaciones?,
     *   // Solo si la forma de cobro es tipo BANCO:
     *   tipo_operacion_bancaria? ('TRANSFERENCIA'|'DEPOSITO'|'DEBITO'|'CHEQUE'),
     *   numero_referencia?, fecha_cobro? (obligatoria si tipo_operacion_bancaria='CHEQUE')
     * }
     * Solo si la factura está 'autorizado' y tiene saldo pendiente > 0. Crea un
     * ingreso real (mismo IngresoService::crear() que el "cobro rápido" web),
     * con el punto de emisión de Ingresos resuelto automáticamente.
     */
    public function cobrar(): void
    {
        $this->requireActualizar();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonError('METODO_NO_PERMITIDO', 'Use POST.', 405);
        }

        $body = $this->getJsonBody();
        $idFactura = (int) ($body['id_factura'] ?? 0);
        $idFormaCobro = (int) ($body['id_forma_cobro'] ?? 0);
        $monto = round((float) ($body['monto'] ?? 0), 2);

        if ($idFactura <= 0 || $idFormaCobro <= 0 || $monto <= 0) {
            $this->jsonError('DATOS_INCOMPLETOS', 'Faltan datos (factura/forma de cobro/monto).', 422);
        }

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        $factura = $this->repository->getPorId($idFactura);
        if (!$factura || (int) ($factura['id_empresa'] ?? 0) !== $idEmpresa) {
            $this->jsonError('NO_ENCONTRADO', 'Factura no encontrada.', 404);
        }
        if (($factura['estado'] ?? '') !== 'autorizado') {
            $this->jsonError('NO_AUTORIZADA', 'Solo se pueden cobrar facturas ya autorizadas por el SRI.', 409);
        }

        $saldo = $this->calcularSaldoPendiente($idFactura, $idEmpresa, $factura);
        if ($saldo['saldo_pendiente'] <= 0.01) {
            $this->jsonError('YA_PAGADA', 'Esta factura ya no tiene saldo pendiente.', 409);
        }
        if ($monto > $saldo['saldo_pendiente'] + 0.01) {
            $this->jsonError(
                'MONTO_EXCEDE_SALDO',
                sprintf('El monto ($%s) no puede superar el saldo pendiente ($%s).', number_format($monto, 2), number_format($saldo['saldo_pendiente'], 2)),
                422
            );
        }

        $db = Database::getConnection();
        $stForma = $db->prepare('SELECT id, tipo FROM empresa_formas_pago WHERE id = ? AND id_empresa = ? AND eliminado = false AND activo = true');
        $stForma->execute([$idFormaCobro, $idEmpresa]);
        $forma = $stForma->fetch(PDO::FETCH_ASSOC);
        if (!$forma) {
            $this->jsonError('FORMA_COBRO_INVALIDA', 'La forma de cobro no es válida.', 422);
        }

        // Datos bancarios: solo tienen sentido si la forma de cobro es tipo BANCO
        // (mismo condicionamiento que el div "fvPagoDivBanco" de la web — Op.
        // Bancaria: Transferencia/Depósito/Débito/Cheque + Nº referencia/cheque).
        $tipoOperacionBancaria = null;
        $numeroReferencia = null;
        $fechaCobro = null;
        if (strtoupper((string) ($forma['tipo'] ?? '')) === 'BANCO') {
            $tipoOperacionBancaria = strtoupper(trim((string) ($body['tipo_operacion_bancaria'] ?? '')));
            if (!in_array($tipoOperacionBancaria, ['TRANSFERENCIA', 'DEPOSITO', 'DEBITO', 'CHEQUE'], true)) {
                $this->jsonError('TIPO_OPERACION_REQUERIDO', 'Selecciona el tipo de operación bancaria (transferencia, depósito, débito o cheque).', 422);
            }
            $numeroReferencia = trim((string) ($body['numero_referencia'] ?? '')) ?: null;
            if ($tipoOperacionBancaria === 'CHEQUE') {
                $fechaCobro = trim((string) ($body['fecha_cobro'] ?? ''));
                if ($fechaCobro === '') {
                    $this->jsonError('FECHA_COBRO_REQUERIDA', 'Indica la fecha en que se podrá cobrar el cheque.', 422);
                }
            }
        }

        // Punto de emisión de Ingresos: se resuelve solo (el primero activo del
        // mismo establecimiento de la factura) — es un detalle técnico, no algo
        // que el usuario deba elegir para un cobro rápido desde el celular.
        $idEstablecimiento = (int) $factura['id_establecimiento'];
        $stPunto = $db->prepare(
            "SELECT p.id, e.codigo AS establecimiento, p.codigo_punto AS punto_emision
             FROM empresa_punto_emision p
             JOIN empresa_establecimiento e ON e.id = p.id_establecimiento
             WHERE p.id_establecimiento = ? AND p.id_empresa = ? AND p.eliminado = false AND LOWER(p.estado) = 'activo'
             ORDER BY p.codigo_punto ASC
             LIMIT 1"
        );
        $stPunto->execute([$idEstablecimiento, $idEmpresa]);
        $punto = $stPunto->fetch(PDO::FETCH_ASSOC);
        if (!$punto) {
            $this->jsonError('SIN_PUNTO_EMISION', 'No hay un punto de emisión activo para registrar el cobro.', 422);
        }

        $secRes = (new SecuencialService())->obtenerSiguienteSecuencial((int) $punto['id'], 'Ingresos');
        $numDoc = $factura['establecimiento'] . '-' . $factura['punto_emision'] . '-' . $factura['secuencial'];

        // saldo_anterior aquí es el que espera IngresoRules (solo resta cobros ya
        // registrados, igual que registrarCobroRapidoAjax en la web) — no el
        // saldo_pendiente "completo" (que además resta retenciones/NC) que se usó
        // arriba para decidir si dejar cobrar. El completo es siempre <= este, así
        // que un monto válido contra el completo también lo es contra este.
        $payload = [
            'id_empresa' => $idEmpresa,
            'id_establecimiento' => $idEstablecimiento,
            'id_punto_emision' => (int) $punto['id'],
            'id_cliente' => (int) $factura['id_cliente'],
            'id_usuario' => $idUsuario,
            'fecha_emision' => date('Y-m-d'),
            'establecimiento' => $punto['establecimiento'],
            'punto_emision' => $punto['punto_emision'],
            'secuencial' => $secRes['formateado'],
            'numero_ingreso' => $punto['establecimiento'] . '-' . $punto['punto_emision'] . '-' . $secRes['formateado'],
            'tipo_ingreso' => 'FACTURA_VENTA',
            'id_ingreso_concepto' => null,
            'monto_total' => $monto,
            'observaciones' => trim((string) ($body['observaciones'] ?? '')) ?: ('Cobro de factura ' . $numDoc),
            'recibo_de' => $factura['cliente_nombre'] ?? '',
            'id_recibo_cliente' => (int) $factura['id_cliente'],
            'detalles' => [[
                'tipo_documento' => 'FACTURA',
                'id_referencia_documento' => $idFactura,
                'numero_documento' => $numDoc,
                'descripcion' => 'Cobro de factura ' . $numDoc,
                'monto_documento' => (float) $factura['importe_total'],
                'saldo_anterior' => $saldo['saldo_anterior_simple'],
                'monto_cobrado' => $monto,
                'saldo_actual' => max(0.0, $saldo['saldo_anterior_simple'] - $monto),
            ]],
            'pagos' => [[
                'id_forma_cobro' => $idFormaCobro,
                'monto' => $monto,
                // La web manda el mismo Nº ingresado tanto en 'referencia' como en
                // 'numero_cheque' (un solo input para ambos) — se replica igual.
                'referencia' => $numeroReferencia,
                'tipo_operacion_bancaria' => $tipoOperacionBancaria,
                'numero_cheque' => $numeroReferencia,
                'fecha_cobro' => $fechaCobro ?: null,
            ]],
        ];

        try {
            $ingresoService = new IngresoService(new IngresoRepository(), new IngresoRules(), new LogSistemaService());
            $idIngreso = $ingresoService->crear($payload);
        } catch (Throwable $e) {
            $this->jsonError('ERROR_GUARDAR', $e->getMessage(), 422);
        }

        $this->jsonOk(['id_ingreso' => $idIngreso], [], 201);
    }

    /**
     * Saldo pendiente de una factura: importe - cobros ya registrados -
     * retenciones - notas de crédito (igual al cálculo que hace el JS de la
     * pestaña "Pagos" de la web — ver factura_venta/index.php ~6260).
     * También devuelve 'saldo_anterior_simple' (solo importe - cobros, sin
     * retenciones/NC), que es lo que espera IngresoRules como saldo_anterior.
     *
     * @return array{importe_total:float,total_cobrado:float,total_retenciones:float,total_nc:float,saldo_anterior_simple:float,saldo_pendiente:float}
     */
    private function calcularSaldoPendiente(int $idFactura, int $idEmpresa, array $factura): array
    {
        $db = Database::getConnection();
        $importeTotal = (float) $factura['importe_total'];
        $numeroFactura = $factura['establecimiento'] . '-' . $factura['punto_emision'] . '-' . $factura['secuencial'];

        $stCobrado = $db->prepare(
            "SELECT COALESCE(SUM(id2.monto_cobrado), 0)
             FROM ingresos_detalle id2
             INNER JOIN ingresos_cabecera ic2 ON id2.id_ingreso = ic2.id
             WHERE id2.tipo_documento = 'FACTURA' AND id2.id_referencia_documento = ?
               AND ic2.estado != 'anulado' AND ic2.eliminado = false"
        );
        $stCobrado->execute([$idFactura]);
        $totalCobrado = (float) $stCobrado->fetchColumn();

        $stRet = $db->prepare(
            "SELECT COALESCE(SUM(r.total_renta + r.total_iva + r.total_isd), 0)
             FROM retencion_venta_cabecera r
             WHERE r.id_empresa = ? AND r.eliminado = false
               AND (r.id_venta = ?
                    OR EXISTS (SELECT 1 FROM retencion_venta_detalle rd
                               WHERE rd.id_retencion = r.id AND rd.num_doc_sustento = ?))"
        );
        $stRet->execute([$idEmpresa, $idFactura, $numeroFactura]);
        $totalRetenciones = (float) $stRet->fetchColumn();

        $stNc = $db->prepare(
            "SELECT COALESCE(SUM(nc.importe_total), 0)
             FROM notas_credito_cabecera nc
             WHERE nc.num_doc_modificado = ? AND nc.id_empresa = ? AND nc.eliminado = false AND nc.estado != 'anulado'"
        );
        $stNc->execute([$numeroFactura, $idEmpresa]);
        $totalNc = (float) $stNc->fetchColumn();

        $saldoAnteriorSimple = round($importeTotal - $totalCobrado, 2);
        $saldoPendiente = round($importeTotal - $totalCobrado - $totalRetenciones - $totalNc, 2);

        return [
            'importe_total' => round($importeTotal, 2),
            'total_cobrado' => round($totalCobrado, 2),
            'total_retenciones' => round($totalRetenciones, 2),
            'total_nc' => round($totalNc, 2),
            'saldo_anterior_simple' => max(0.0, $saldoAnteriorSimple),
            'saldo_pendiente' => max(0.0, $saldoPendiente),
        ];
    }
}
