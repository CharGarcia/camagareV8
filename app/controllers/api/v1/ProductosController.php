<?php
/**
 * Controlador API v1: Productos (consulta + alta/edición básica desde la app móvil).
 * Adaptador HTTP→JSON puro: reutiliza ProductoService/ProductoRules/ProductoRepository
 * tal cual los usa la web, sin duplicar lógica. Mismo permiso 'modulos/productos' que
 * la web.
 *
 * Alcance "básico" a propósito (decisión explícita, no un descuido): el formulario
 * móvil crea/edita código, código auxiliar, nombre, tipo (bien/servicio), tarifa IVA,
 * precio base, categoría, unidad e imagen. Variantes, componentes (BOM) y listas de
 * precio múltiples se siguen administrando solo desde la web. Todo producto creado
 * desde la app queda marcado "solo venta" (opciones: {compra:false, venta:true}), sin
 * excepción — no es configurable desde este formulario.
 */

declare(strict_types=1);

namespace App\controllers\api\v1;

use App\controllers\api\ApiBaseController;
use App\repositories\modulos\InventarioRepository;
use App\repositories\modulos\ProductoRepository;
use App\repositories\modulos\UnidadesMedidaRepository;
use App\Rules\modulos\ProductoRules;
use App\Services\LogSistemaService;
use App\Services\modulos\InventarioService;
use App\Services\modulos\ProductoService;
use Throwable;

class ProductosController extends ApiBaseController
{
    private ProductoService $service;
    private ProductoRepository $repository;

    public function __construct()
    {
        parent::__construct();
        $this->repository = new ProductoRepository();
        $logService = new LogSistemaService();
        $invService = new InventarioService(new InventarioRepository(), $logService);
        $this->service = new ProductoService($this->repository, new ProductoRules(), $logService, $invService);
    }

    protected function getRutaModulo(): string
    {
        return 'modulos/productos';
    }

    /**
     * GET /api/v1/productos/listar?buscar=&page=
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

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, 'nombre', 'ASC', $idUsuarioFiltro, null, true);
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
     * GET /api/v1/productos/obtener?id=123
     */
    public function obtener(): void
    {
        $this->requireLeer();

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->jsonError('ID_REQUERIDO', 'Falta id.', 422);
        }

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $producto = $this->repository->getDetalleCompleto($id, $idEmpresa);
        if (!$producto) {
            $this->jsonError('NO_ENCONTRADO', 'Producto no encontrado.', 404);
        }

        $this->jsonOk($producto);
    }

    /**
     * POST /api/v1/productos/crear
     * body: { codigo?, nombre, precio_base?, id_categoria?, id_medida?, imagen? }
     * codigo vacío = se autogenera con el mismo correlativo que usa la web.
     */
    public function crear(): void
    {
        $this->requireCrear();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonError('METODO_NO_PERMITIDO', 'Use POST.', 405);
        }

        $body = $this->getJsonBody();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $tipoProduccion = $this->tipoProduccionDesdeBody($body);

        $codigo = trim((string) ($body['codigo'] ?? ''));
        if ($codigo === '') {
            $codigo = $this->service->getSiguienteCodigo($idEmpresa, $tipoProduccion);
        }

        $data = $this->datosBasicos($body, $codigo, $tipoProduccion);
        $data['id_empresa'] = $idEmpresa;
        $data['id_usuario'] = (int) $_SESSION['id_usuario'];

        try {
            $id = $this->service->crear($data);
        } catch (Throwable $e) {
            $this->jsonError('ERROR_GUARDAR', $e->getMessage(), 422);
        }

        $this->jsonOk(['id' => $id], [], 201);
    }

    /**
     * POST /api/v1/productos/actualizar
     * body: { id, codigo, nombre, precio_base?, id_categoria?, id_medida?, imagen? }
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
        $codigo = trim((string) ($body['codigo'] ?? ''));
        $data = $this->datosBasicos($body, $codigo, $this->tipoProduccionDesdeBody($body));
        $data['id_empresa'] = $idEmpresa;
        $data['id_usuario'] = (int) $_SESSION['id_usuario'];

        try {
            $this->service->actualizar($id, $idEmpresa, $data);
        } catch (Throwable $e) {
            $this->jsonError('ERROR_GUARDAR', $e->getMessage(), 422);
        }

        $this->jsonOk(['id' => $id]);
    }

    /**
     * GET /api/v1/productos/catalogos
     */
    public function catalogos(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $db = \App\core\Database::getConnection();

        $st = $db->prepare("SELECT id, nombre FROM categorias WHERE id_empresa = ? AND eliminado = false ORDER BY nombre ASC");
        $st->execute([$idEmpresa]);
        $categorias = $st->fetchAll(\PDO::FETCH_ASSOC);

        $st = $db->prepare("SELECT id, nombre FROM marcas WHERE id_empresa = ? AND eliminado = false ORDER BY nombre ASC");
        $st->execute([$idEmpresa]);
        $marcas = $st->fetchAll(\PDO::FETCH_ASSOC);

        $unidadesRepo = new UnidadesMedidaRepository();
        $medidasService = new \App\Services\modulos\MedidasService();

        try {
            $medidasService->asegurarMedidasBase($idEmpresa, (int) $_SESSION['id_usuario']);
        } catch (Throwable $e) {
            // No bloquea el formulario si falla el auto-seed; el usuario puede dejar la unidad en blanco.
        }

        $st = $db->query("SELECT id, tarifa AS nombre, porcentaje_iva AS porcentaje FROM tarifa_iva WHERE status = 1 ORDER BY tarifa ASC");
        $tarifasIva = $st->fetchAll(\PDO::FETCH_ASSOC);

        $this->jsonOk([
            'categorias' => $categorias,
            'marcas' => $marcas,
            'unidades' => $unidadesRepo->getActive($idEmpresa),
            'tarifas_iva' => $tarifasIva,
            // Preselección del formulario móvil: la unidad "Unidad" de esta empresa
            // (misma que usa ProductoService al no enviarse id_medida al crear).
            'medida_default' => $medidasService->getMedidaDefaultUnidad($idEmpresa),
        ]);
    }

    /**
     * GET /api/v1/productos/siguiente-codigo?tipo=01|02
     */
    public function siguienteCodigo(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $tipo = ($_GET['tipo'] ?? '01') === '02' ? '02' : '01';
        $this->jsonOk(['codigo' => $this->service->getSiguienteCodigo($idEmpresa, $tipo)]);
    }

    /**
     * POST /api/v1/productos/subir-imagen
     * body: { imagen_base64: "data:image/jpeg;base64,..." (o sin prefijo) }
     * Guarda en el MISMO directorio que usa la web (storage compartido).
     */
    public function subirImagen(): void
    {
        $this->requireCrear();

        $body = $this->getJsonBody();
        $base64 = (string) ($body['imagen_base64'] ?? '');
        if ($base64 === '') {
            $this->jsonError('IMAGEN_REQUERIDA', 'Falta imagen_base64.', 422);
        }

        $ext = 'jpg';
        $data = $base64;
        if (preg_match('/^data:image\/(\w+);base64,/', $base64, $m)) {
            $ext = strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]);
            $data = substr($base64, strpos($base64, ',') + 1);
        }
        if (!in_array($ext, ['jpg', 'png', 'gif', 'webp'], true)) {
            $this->jsonError('FORMATO_INVALIDO', 'Formato de imagen no permitido.', 422);
        }

        $binario = base64_decode($data, true);
        if ($binario === false || strlen($binario) === 0) {
            $this->jsonError('IMAGEN_INVALIDA', 'La imagen recibida no es válida.', 422);
        }
        if (strlen($binario) > 2 * 1024 * 1024) {
            $this->jsonError('IMAGEN_MUY_GRANDE', 'La imagen excede los 2MB.', 422);
        }

        $dir = MVC_ROOT . '/public/uploads/productos';
        if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
            $this->jsonError('ERROR_ALMACENAMIENTO', 'No se pudo preparar el almacenamiento de imágenes.', 500);
        }

        $nombreArchivo = uniqid('prod_') . '.' . $ext;
        if (@file_put_contents($dir . '/' . $nombreArchivo, $binario) === false) {
            $this->jsonError('ERROR_GUARDAR', 'No se pudo guardar la imagen.', 500);
        }

        $this->jsonOk(['path' => 'uploads/productos/' . $nombreArchivo]);
    }

    private function tipoProduccionDesdeBody(array $body): string
    {
        return ($body['tipo_produccion'] ?? '01') === '02' ? '02' : '01';
    }

    /** @return array<string,mixed> */
    private function datosBasicos(array $body, string $codigo, string $tipoProduccion): array
    {
        return [
            'codigo' => $codigo,
            'codigo_auxiliar' => trim((string) ($body['codigo_auxiliar'] ?? '')),
            'nombre' => trim((string) ($body['nombre'] ?? '')),
            'precio_base' => isset($body['precio_base']) ? (float) $body['precio_base'] : 0,
            'tipo_produccion' => $tipoProduccion,
            // Un servicio ('02') nunca es inventariable: ProductoService lo fuerza a false
            // igual, pero lo dejamos explícito aquí para que quede claro que es a propósito.
            'inventariable' => $tipoProduccion === '01',
            'tarifa_iva' => !empty($body['tarifa_iva']) ? (int) $body['tarifa_iva'] : 2,
            'status' => 1,
            'id_categoria' => !empty($body['id_categoria']) ? (int) $body['id_categoria'] : null,
            'id_marca' => !empty($body['id_marca']) ? (int) $body['id_marca'] : null,
            'id_medida' => !empty($body['id_medida']) ? (int) $body['id_medida'] : null,
            'imagen' => trim((string) ($body['imagen'] ?? '')),
            // Todo producto creado desde la app queda marcado "solo venta", sin excepción
            // (decisión del negocio, no configurable desde este formulario).
            'opciones' => '{"compra":false,"venta":true}',
        ];
    }
}
