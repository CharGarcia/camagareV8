<?php
/**
 * Controlador API v1: Proveedores (listar/crear/actualizar + catálogos + consulta SRI).
 * Adaptador HTTP→JSON puro: reutiliza ProveedorService/ProveedorRules/ProveedorRepository
 * tal cual los usa la web, sin duplicar lógica. Mismo permiso 'modulos/proveedores' que
 * la web.
 *
 * Alcance "básico" a propósito (decisión explícita, no un descuido): el formulario móvil
 * crea/edita razón social, tipo de identificación, identificación, nombre comercial,
 * email, teléfono, dirección, provincia y ciudad. Datos bancarios, retenciones/sustento
 * tributario predeterminado, configuración de auto-pago y cuentas contables se siguen
 * administrando solo desde la web — ProveedorService los trata como opcionales, así que
 * omitirlos en el body no rompe nada.
 */

declare(strict_types=1);

namespace App\controllers\api\v1;

use App\controllers\api\ApiBaseController;
use App\models\Ciudad;
use App\models\IdentificadorCompradorVendedor;
use App\models\Provincia;
use App\repositories\modulos\ProveedorRepository;
use App\Rules\modulos\ProveedorRules;
use App\Services\LogSistemaService;
use App\Services\modulos\ProveedorService;
use App\Services\SriIdentificationService;
use Throwable;

class ProveedoresController extends ApiBaseController
{
    private ProveedorService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new ProveedorService(new ProveedorRepository(), new ProveedorRules(), new LogSistemaService());
    }

    protected function getRutaModulo(): string
    {
        return 'modulos/proveedores';
    }

    /**
     * GET /api/v1/proveedores/listar?buscar=&page=
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

        $repository = new ProveedorRepository();
        $result = $repository->getListado($idEmpresa, $buscar, $page, $perPage, 'razon_social', 'ASC', $idUsuarioFiltro);
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
     * GET /api/v1/proveedores/obtener?id=123
     */
    public function obtener(): void
    {
        $this->requireLeer();

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->jsonError('ID_REQUERIDO', 'Falta id.', 422);
        }

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $repository = new ProveedorRepository();
        $proveedor = $repository->findById($id, $idEmpresa);
        if (!$proveedor) {
            $this->jsonError('NO_ENCONTRADO', 'Proveedor no encontrado.', 404);
        }

        $this->jsonOk($proveedor);
    }

    /**
     * POST /api/v1/proveedores/crear
     * body: { razon_social, tipo_id_proveedor, identificacion, nombre_comercial?, email?, telefono?, direccion?, provincia?, ciudad? }
     */
    public function crear(): void
    {
        $this->requireCrear();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonError('METODO_NO_PERMITIDO', 'Use POST.', 405);
        }

        $data = $this->datosDesdeBody($this->getJsonBody());
        $data['id_empresa'] = (int) $_SESSION['id_empresa'];
        $data['id_usuario'] = (int) $_SESSION['id_usuario'];

        try {
            $id = $this->service->crear($data);
        } catch (Throwable $e) {
            $this->jsonError('ERROR_GUARDAR', $e->getMessage(), 422);
        }

        $this->jsonOk(['id' => $id], [], 201);
    }

    /**
     * POST /api/v1/proveedores/actualizar
     * body: { id, razon_social, tipo_id_proveedor, identificacion, nombre_comercial?, email?, telefono?, direccion?, provincia?, ciudad? }
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
        $data = $this->datosDesdeBody($body);
        $data['id_usuario'] = (int) $_SESSION['id_usuario'];

        try {
            $this->service->actualizar($id, $idEmpresa, $data);
        } catch (Throwable $e) {
            $this->jsonError('ERROR_GUARDAR', $e->getMessage(), 422);
        }

        $this->jsonOk(['id' => $id]);
    }

    /**
     * GET /api/v1/proveedores/catalogos
     * Bootstrap del formulario: tipos de identificación (mismo catálogo que Clientes,
     * tipo=1 sirve tanto a comprador como a vendedor) y provincias.
     */
    public function catalogos(): void
    {
        $this->requireLeer();

        $tipos = (new IdentificadorCompradorVendedor())->getAll('codigo', 'ASC');
        $tiposId = array_values(array_filter($tipos, fn($r) => (int) ($r['tipo'] ?? 0) === 1 && (int) ($r['status'] ?? 1) === 1));

        $this->jsonOk([
            'tipos_id' => $tiposId,
            'provincias' => (new Provincia())->getTodas(),
        ]);
    }

    /**
     * GET /api/v1/proveedores/ciudades?cod_prov=17
     */
    public function ciudades(): void
    {
        $this->requireLeer();

        $codProv = trim($_GET['cod_prov'] ?? '');
        if ($codProv === '') {
            $this->jsonOk([]);
        }

        $this->jsonOk((new Ciudad())->getPorProvincia($codProv));
    }

    /**
     * GET /api/v1/proveedores/consultar-sri?identificacion=...
     * Proxy al mismo servicio que usa la web: la app nunca llama al SRI directamente.
     */
    public function consultarSri(): void
    {
        $this->requireLeer();

        $identificacion = trim($_GET['identificacion'] ?? '');
        if ($identificacion === '') {
            $this->jsonError('IDENTIFICACION_REQUERIDA', 'Falta identificacion.', 422);
        }

        $resultado = (new SriIdentificationService())->consultar($identificacion, (int) $_SESSION['id_empresa']);
        $this->jsonOk($resultado);
    }

    /** @return array<string,mixed> */
    private function datosDesdeBody(array $body): array
    {
        return [
            'razon_social' => trim((string) ($body['razon_social'] ?? '')),
            'tipo_id_proveedor' => trim((string) ($body['tipo_id_proveedor'] ?? '')),
            'identificacion' => trim((string) ($body['identificacion'] ?? '')),
            'nombre_comercial' => trim((string) ($body['nombre_comercial'] ?? '')) !== '' ? trim((string) $body['nombre_comercial']) : null,
            'email' => trim((string) ($body['email'] ?? '')),
            'telefono' => trim((string) ($body['telefono'] ?? '')) !== '' ? trim((string) $body['telefono']) : null,
            'direccion' => trim((string) ($body['direccion'] ?? '')) !== '' ? trim((string) $body['direccion']) : null,
            'provincia' => trim((string) ($body['provincia'] ?? '')) !== '' ? trim((string) $body['provincia']) : null,
            'ciudad' => trim((string) ($body['ciudad'] ?? '')) !== '' ? trim((string) $body['ciudad']) : null,
            'status' => 1,
        ];
    }
}
