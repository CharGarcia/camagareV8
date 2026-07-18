<?php
/**
 * Controlador API v1: Pedidos (consulta y creación desde la app móvil).
 * Adaptador HTTP→JSON puro: toda la lógica de negocio vive en PedidoService/
 * PedidoRepository/PedidoRules ya existentes (los mismos que usa el módulo web),
 * sin duplicarla.
 */

declare(strict_types=1);

namespace App\controllers\api\v1;

use App\controllers\api\ApiBaseController;
use App\models\Empresa;
use App\Repositories\Modulos\PedidoRepository;
use App\Repositories\Modulos\ResponsableTrasladoRepository;
use App\Rules\Modulos\PedidoRules;
use App\Services\Modulos\PedidoService;
use App\Services\SecuencialService;
use Exception;

class PedidosController extends ApiBaseController
{
    /** Tal cual lo espera SecuencialService::DOCUMENT_MAP para mapear a pedidos_cabecera. */
    private const TIPO_DOCUMENTO = 'Pedidos';

    private PedidoService $service;
    private PedidoRepository $repository;

    public function __construct()
    {
        parent::__construct();
        $this->service = new PedidoService();
        $this->repository = new PedidoRepository();
    }

    protected function getRutaModulo(): string
    {
        return 'modulos/pedidos';
    }

    /**
     * GET /api/v1/pedidos/listar?buscar=&page=&sort=&dir=
     */
    public function listar(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar = trim($_GET['buscar'] ?? '');
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? 'fecha_pedido');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? 'DESC'));
        $perPage = min(50, max(1, (int) ($_GET['per_page'] ?? 20)));

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
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
     * GET /api/v1/pedidos/obtener?id=123
     */
    public function obtener(): void
    {
        $this->requireLeer();

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->jsonError('ID_REQUERIDO', 'Falta id.', 422);
        }

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $cabecera = $this->repository->obtenerPorId($id, $idEmpresa);
        if (!$cabecera) {
            $this->jsonError('NO_ENCONTRADO', 'Pedido no encontrado.', 404);
        }
        $detalles = $this->repository->obtenerDetalles($id, $idEmpresa);

        $this->jsonOk(['cabecera' => $cabecera, 'detalles' => $detalles]);
    }

    /**
     * POST /api/v1/pedidos/crear
     * body: { cabecera: {...}, detalles: [...] }
     */
    public function crear(): void
    {
        $this->requireCrear();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonError('METODO_NO_PERMITIDO', 'Use POST.', 405);
        }

        $body = $this->getJsonBody();
        $cabecera = $body['cabecera'] ?? [];
        $detalles = $body['detalles'] ?? [];

        if (!is_array($cabecera) || !is_array($detalles)) {
            $this->jsonError('BODY_INVALIDO', 'Formato de cabecera/detalles inválido.', 422);
        }

        try {
            PedidoRules::validar($cabecera, $detalles);
        } catch (Exception $e) {
            $this->jsonError('VALIDACION', $e->getMessage(), 422);
        }

        $idPuntoEmision = (int) ($cabecera['id_punto_emision'] ?? 0);
        $secuencial = (int) ltrim((string) ($cabecera['secuencial'] ?? ''), '0');
        if ($idPuntoEmision <= 0 || $secuencial <= 0) {
            $this->jsonError('SERIE_REQUERIDA', 'Selecciona la serie (establecimiento/punto de emisión) antes de guardar.', 422);
        }

        // El secuencial se calculó al abrir el formulario (SecuencialService es de solo
        // lectura, no lo "reserva"): revalidar aquí que siga disponible justo antes de
        // insertar reduce (sin eliminar del todo) la ventana de colisión entre dos
        // celulares creando pedidos casi al mismo tiempo en el mismo punto de emisión.
        $validacion = (new SecuencialService())->validarSecuencial($idPuntoEmision, self::TIPO_DOCUMENTO, $secuencial);
        if (empty($validacion['disponible'])) {
            $this->jsonError('SECUENCIAL_NO_DISPONIBLE', $validacion['mensaje'] ?? 'El secuencial ya no está disponible, vuelve a intentar.', 409);
        }

        try {
            $idPedido = $this->service->guardarPedido($cabecera, $detalles, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']);
        } catch (Exception $e) {
            $this->jsonError('ERROR_GUARDAR', $e->getMessage(), 500);
        }

        $this->jsonOk(['id' => $idPedido], [], 201);
    }

    /**
     * GET /api/v1/pedidos/series
     * Establecimientos de la empresa con sus puntos de emisión activos anidados,
     * con los códigos de texto ya resueltos (los mismos que usa PedidoService::guardarPedido).
     */
    public function series(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $empresaModel = new Empresa();
        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);

        $resultado = [];
        foreach ($establecimientos as $est) {
            $puntos = $empresaModel->getPuntosEmision((int) $est['id']);
            $resultado[] = [
                'id_establecimiento' => (int) $est['id'],
                'establecimiento' => $est['codigo'],
                'direccion' => $est['direccion'] ?? '',
                'puntos_emision' => array_map(static function (array $p): array {
                    return [
                        'id_punto_emision' => (int) $p['id'],
                        'punto_emision' => $p['codigo_punto'],
                    ];
                }, $puntos),
            ];
        }

        $this->jsonOk($resultado);
    }

    /**
     * GET /api/v1/pedidos/secuencial?id_punto_emision=123
     * Mismo cálculo que PedidosController (web) ::getSecuencialAjax. Es de solo lectura:
     * el número no queda reservado hasta que el pedido se guarda de verdad.
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
     * GET /api/v1/pedidos/buscar-clientes?q=
     * Mismo criterio que PedidosController (web) ::buscarClientesAjax, para el
     * autocompletar del formulario de creación en la app.
     */
    public function buscarClientes(): void
    {
        $this->requireLeer();

        $term = trim($_GET['q'] ?? $_GET['term'] ?? '');
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $db = \App\core\Database::getConnection();
        $stmt = $db->prepare(
            "SELECT id, identificacion, nombre FROM clientes
             WHERE (nombre ILIKE :term OR identificacion ILIKE :term)
               AND id_empresa = :id_empresa AND status = '1'
             LIMIT 10"
        );
        $stmt->execute(['term' => "%{$term}%", 'id_empresa' => $idEmpresa]);

        $this->jsonOk($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * GET /api/v1/pedidos/responsables
     * Catálogo de responsables de traslado activos de la empresa, para el selector
     * de "responsable de entrega" del formulario.
     */
    public function responsables(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $this->jsonOk((new ResponsableTrasladoRepository())->listarPorEmpresa($idEmpresa));
    }

    /**
     * GET /api/v1/pedidos/buscar-productos?q=
     * Mismo criterio que PedidosController (web) ::buscarProductosAjax.
     */
    public function buscarProductos(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar = trim($_GET['q'] ?? $_GET['term'] ?? '');

        $db = \App\core\Database::getConnection();
        $stmt = $db->prepare(
            "SELECT p.id, p.codigo, p.nombre, p.precio_base
             FROM productos p
             WHERE p.id_empresa = :id_empresa
               AND p.eliminado = false AND p.status = 1
               AND (p.codigo ILIKE :q OR p.nombre ILIKE :q)
             ORDER BY p.nombre ASC
             LIMIT 15"
        );
        $stmt->execute([':id_empresa' => $idEmpresa, ':q' => '%' . $buscar . '%']);

        $this->jsonOk($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }
}
