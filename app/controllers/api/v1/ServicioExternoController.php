<?php
/**
 * Controlador API v1: Servicio Externo (órdenes de mantenimiento en sitio del
 * cliente). Adaptador HTTP→JSON puro: reutiliza ServicioExternoService/Rules/
 * Repository tal cual la web (App\controllers\modulos\ServicioExternoController),
 * incluida la generación de Factura/Recibo desde la orden.
 *
 * Alcance "básico" a propósito (decisión explícita, no un descuido): la app solo
 * lista/ve órdenes y crea una nueva + genera su documento. Editar una orden ya
 * creada, cambiar estado manualmente, eliminar, exportar PDF/correo/WhatsApp
 * siguen siendo exclusivos de la web. Mismo permiso 'modulos/servicio-externo'
 * que la web.
 */

declare(strict_types=1);

namespace App\controllers\api\v1;

use App\controllers\api\ApiBaseController;
use App\models\Empresa;
use App\repositories\modulos\BodegaRepository;
use App\repositories\modulos\EmpresaRepository;
use App\repositories\modulos\ServicioExternoRepository;
use App\Rules\modulos\ServicioExternoRules;
use App\Services\LogSistemaService;
use App\Services\modulos\ServicioExternoService;
use App\Services\SecuencialService;
use PDO;
use Throwable;

class ServicioExternoController extends ApiBaseController
{
    private ServicioExternoService $service;
    private ServicioExternoRepository $repository;

    private const TIPO_SECUENCIAL = 'Ordenes servicio externo';

    public function __construct()
    {
        parent::__construct();
        $this->repository = new ServicioExternoRepository();
        $this->service = new ServicioExternoService($this->repository, new ServicioExternoRules(), new LogSistemaService());
    }

    protected function getRutaModulo(): string
    {
        return 'modulos/servicio-externo';
    }

    /**
     * GET /api/v1/servicio-externo/listar?buscar=&page=
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

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, 'fecha_servicio', 'DESC', $idUsuarioFiltro);
        $total = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $this->jsonOk($result['rows'], [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
        ]);
    }

    /**
     * GET /api/v1/servicio-externo/obtener?id=123
     */
    public function obtener(): void
    {
        $this->requireLeer();

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->jsonError('ID_REQUERIDO', 'Falta id.', 422);
        }

        $orden = $this->service->getDetalleCompleto($id, (int) $_SESSION['id_empresa']);
        if (!$orden) {
            $this->jsonError('NO_ENCONTRADO', 'Orden no encontrada.', 404);
        }

        $this->jsonOk($orden);
    }

    /**
     * GET /api/v1/servicio-externo/catalogos
     * Bootstrap del formulario: puntos de emisión, formas de pago, bodegas
     * permitidas, tarifas de IVA y unidades de medida.
     */
    public function catalogos(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $nivel = (int) ($_SESSION['nivel'] ?? 1);

        $secRepo = new \App\repositories\SecuencialRepository();
        $puntos = [];
        foreach ((new EmpresaRepository())->getPuntosEmision($idEmpresa) as $p) {
            $config = $secRepo->getConfigSecuencial((int) $p['id'], 'Ordenes servicio externo');
            if (empty($config['id'])) {
                continue;
            }
            $puntos[] = $p;
        }
        $bodegas = (new BodegaRepository())->getBodegasPermitidas($idUsuario, $idEmpresa, $nivel);

        $this->jsonOk([
            'puntos' => $puntos,
            'formas_pago' => $this->repository->getFormasPago(),
            'bodegas' => $bodegas,
            'tarifas_iva' => $this->repository->getTarifasIva(),
            'unidades' => $this->repository->getUnidadesMedida($idEmpresa),
        ]);
    }

    /**
     * GET /api/v1/servicio-externo/siguiente-secuencial?id_punto_emision=
     */
    public function siguienteSecuencial(): void
    {
        $this->requireLeer();

        $idPunto = (int) ($_GET['id_punto_emision'] ?? 0);
        if ($idPunto <= 0) {
            $this->jsonError('PUNTO_REQUERIDO', 'Punto de emisión no válido.', 422);
        }

        $res = (new SecuencialService())->obtenerSiguienteSecuencial($idPunto, self::TIPO_SECUENCIAL);
        $this->jsonOk($res);
    }

    /**
     * POST /api/v1/servicio-externo/crear
     * body: { id_punto_emision, secuencial, id_cliente, equipo_descripcion,
     *   equipo_marca?, equipo_modelo?, equipo_serie?, direccion_servicio?,
     *   fecha_servicio, descripcion_trabajo?, observaciones?, id_bodega?, detalles[] }
     * detalles[]: { tipo_linea: 'producto'|'servicio', id_producto?, descripcion,
     *   cantidad, precio_unitario, descuento?, porcentaje_iva?, id_tarifa_iva?, id_bodega? }
     */
    public function crear(): void
    {
        $this->requireCrear();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonError('METODO_NO_PERMITIDO', 'Use POST.', 405);
        }

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $body = $this->getJsonBody();

        $idPunto = (int) ($body['id_punto_emision'] ?? 0);
        $secuencial = str_pad((string) ($body['secuencial'] ?? ''), 9, '0', STR_PAD_LEFT);
        if ($idPunto <= 0 || trim((string) ($body['secuencial'] ?? '')) === '') {
            $this->jsonError('SECUENCIAL_REQUERIDO', 'Falta el punto de emisión o el secuencial.', 422);
        }

        // Revalida disponibilidad justo antes de guardar (entre pedir el secuencial y
        // enviar el formulario puede haber pasado otra orden desde otro celular) — mismo
        // patrón que ya usa FacturasVentaController::crear() en esta misma API.
        $secuencialInt = (int) ltrim($secuencial, '0');
        $validacion = (new SecuencialService())->validarSecuencial($idPunto, self::TIPO_SECUENCIAL, $secuencialInt);
        if (empty($validacion['disponible'])) {
            $this->jsonError('SECUENCIAL_NO_DISPONIBLE', $validacion['mensaje'] ?? 'El secuencial ya no está disponible, vuelve a intentar.', 409);
        }

        $db = \App\core\Database::getConnection();
        $stPto = $db->prepare(
            "SELECT pe.id_establecimiento, pe.codigo_punto, es.codigo AS cod_establecimiento
             FROM empresa_punto_emision pe
             JOIN empresa_establecimiento es ON es.id = pe.id_establecimiento
             WHERE pe.id = ? AND es.id_empresa = ? AND pe.eliminado = FALSE"
        );
        $stPto->execute([$idPunto, $idEmpresa]);
        $pto = $stPto->fetch(PDO::FETCH_ASSOC);
        if (!$pto) {
            $this->jsonError('PUNTO_INVALIDO', 'El punto de emisión no existe o está inactivo.', 422);
        }

        $detalles = [];
        foreach ((array) ($body['detalles'] ?? []) as $d) {
            $cantidad = (float) ($d['cantidad'] ?? 0);
            $descripcion = trim((string) ($d['descripcion'] ?? ''));
            if ($cantidad <= 0 || $descripcion === '') {
                continue;
            }
            $detalles[] = [
                'tipo_linea' => ($d['tipo_linea'] ?? 'servicio') === 'producto' ? 'producto' : 'servicio',
                'es_libre' => empty($d['id_producto']),
                'id_producto' => !empty($d['id_producto']) ? (int) $d['id_producto'] : null,
                'descripcion' => $descripcion,
                'id_bodega' => !empty($d['id_bodega']) ? (int) $d['id_bodega'] : null,
                'cantidad' => $cantidad,
                'precio_unitario' => (float) ($d['precio_unitario'] ?? 0),
                'descuento' => (float) ($d['descuento'] ?? 0),
                'porcentaje_iva' => (float) ($d['porcentaje_iva'] ?? 0),
                'id_tarifa_iva' => !empty($d['id_tarifa_iva']) ? (int) $d['id_tarifa_iva'] : null,
            ];
        }

        $data = [
            'id_empresa' => $idEmpresa,
            'id_usuario' => $idUsuario,
            'empresa_config' => $this->obtenerEmpresaConfig($idEmpresa, (int) $pto['id_establecimiento']),
            'id_establecimiento' => (int) $pto['id_establecimiento'],
            'id_punto_emision' => $idPunto,
            'establecimiento' => (string) $pto['cod_establecimiento'],
            'punto_emision' => (string) $pto['codigo_punto'],
            'secuencial' => $secuencial,
            'id_cliente' => (int) ($body['id_cliente'] ?? 0),
            'id_bodega' => !empty($body['id_bodega']) ? (int) $body['id_bodega'] : null,
            'equipo_descripcion' => trim((string) ($body['equipo_descripcion'] ?? '')),
            'equipo_marca' => trim((string) ($body['equipo_marca'] ?? '')) ?: null,
            'equipo_modelo' => trim((string) ($body['equipo_modelo'] ?? '')) ?: null,
            'equipo_serie' => trim((string) ($body['equipo_serie'] ?? '')) ?: null,
            'direccion_servicio' => trim((string) ($body['direccion_servicio'] ?? '')) ?: null,
            'fecha_servicio' => trim((string) ($body['fecha_servicio'] ?? '')) ?: date('Y-m-d'),
            'descripcion_trabajo' => trim((string) ($body['descripcion_trabajo'] ?? '')) ?: null,
            'observaciones' => trim((string) ($body['observaciones'] ?? '')) ?: null,
            'info_adicional' => [],
            'detalles' => $detalles,
        ];

        try {
            $id = $this->service->crear($data);
        } catch (Throwable $e) {
            $this->jsonError('ERROR_GUARDAR', $e->getMessage(), 422);
        }

        $this->jsonOk(['id' => $id], [], 201);
    }

    /**
     * POST /api/v1/servicio-externo/generar-documento
     * body: { id_orden, tipo: 'FACTURA'|'RECIBO', forma_pago?, id_bodega? }
     */
    public function generarDocumento(): void
    {
        $this->requireCrear();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonError('METODO_NO_PERMITIDO', 'Use POST.', 405);
        }

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $body = $this->getJsonBody();

        $idOrden = (int) ($body['id_orden'] ?? 0);
        $tipo = strtoupper(trim((string) ($body['tipo'] ?? '')));
        if ($idOrden <= 0) {
            $this->jsonError('ID_REQUERIDO', 'Falta id_orden.', 422);
        }
        if (!in_array($tipo, ['FACTURA', 'RECIBO'], true)) {
            $this->jsonError('TIPO_INVALIDO', "El tipo debe ser 'FACTURA' o 'RECIBO'.", 422);
        }

        $extra = [
            'forma_pago' => trim((string) ($body['forma_pago'] ?? '01')),
            'id_bodega' => (int) ($body['id_bodega'] ?? 0),
        ];

        try {
            $res = $this->service->generarDocumento($idOrden, $idEmpresa, $idUsuario, $tipo, $extra, $this->obtenerEmpresaConfig($idEmpresa, null));
        } catch (Throwable $e) {
            $this->jsonError('ERROR_GENERAR_DOCUMENTO', $e->getMessage(), 422);
        }

        $this->jsonOk($res);
    }

    /**
     * GET /api/v1/servicio-externo/buscar-clientes?q=
     * Autocomplete propio (no reutiliza /clientes/* ni /pedidos/*): esos endpoints
     * exigen el permiso de SU módulo, y un usuario con acceso a Servicio Externo no
     * necesariamente tiene permiso sobre Clientes o Pedidos — mismo criterio que ya
     * usa la web (ServicioExternoController::buscarClientesAjax).
     */
    public function buscarClientes(): void
    {
        $this->requireLeer();

        $q = trim($_GET['q'] ?? '');
        $db = \App\core\Database::getConnection();
        $st = $db->prepare(
            "SELECT id, identificacion, nombre, direccion, email AS correo, telefono
             FROM clientes
             WHERE (nombre ILIKE :q OR identificacion ILIKE :q)
               AND id_empresa = :e AND status = '1' AND eliminado = false
             ORDER BY nombre ASC
             LIMIT 10"
        );
        $st->execute([':q' => "%{$q}%", ':e' => (int) $_SESSION['id_empresa']]);
        $this->jsonOk($st->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * GET /api/v1/servicio-externo/buscar-productos?q=&id_bodega=
     * Autocomplete propio de repuestos, con stock si se indica bodega (mismo motivo
     * que buscarClientes: permiso propio del módulo, no el de Productos).
     */
    public function buscarProductos(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $q = trim($_GET['q'] ?? '');
        $idBodega = (int) ($_GET['id_bodega'] ?? 0);

        $db = \App\core\Database::getConnection();
        $st = $db->prepare(
            "SELECT id, codigo, nombre, precio_base, tarifa_iva, inventariable, tipo_produccion
             FROM productos
             WHERE (nombre ILIKE :q OR codigo ILIKE :q)
               AND id_empresa = :e AND status = 1 AND eliminado = false
             ORDER BY nombre ASC
             LIMIT 15"
        );
        $st->execute([':q' => "%{$q}%", ':e' => $idEmpresa]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        if ($idBodega > 0) {
            $invRepo = new \App\repositories\modulos\InventarioRepository();
            foreach ($rows as &$p) {
                $esInv = filter_var($p['inventariable'], FILTER_VALIDATE_BOOLEAN) && (($p['tipo_produccion'] ?? '01') !== '02');
                $p['stock_actual'] = $esInv ? $invRepo->getStockActual((int) $p['id'], $idBodega, $idEmpresa) : 0.0;
                $p['controla_stock'] = $esInv;
            }
            unset($p);
        }

        $this->jsonOk($rows);
    }

    /** Igual que ApiBaseController de Facturas de Venta: datos de empresas + config del establecimiento. */
    private function obtenerEmpresaConfig(int $idEmpresa, ?int $idEstablecimiento): array
    {
        $empresaData = (new Empresa())->getPorId($idEmpresa) ?? [];

        if ($idEstablecimiento === null) {
            $establecimientos = (new Empresa())->getEstablecimientos($idEmpresa);
            $idEstablecimiento = (int) ($establecimientos[0]['id'] ?? 0);
        }

        if ($idEstablecimiento > 0) {
            try {
                $estConfig = (new EmpresaRepository())->getEstablecimientoConfig($idEstablecimiento);
                if ($estConfig) {
                    $empresaData = array_merge($empresaData, $estConfig);
                }
            } catch (Throwable $e) {
                // Si falla, el Service usa sus propios defaults (tipo_ambiente '1', etc.)
            }
        }

        return $empresaData;
    }
}
