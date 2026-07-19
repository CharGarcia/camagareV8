<?php
/**
 * Controlador API v1: Entregas de Consignaciones en venta (GPS + firma).
 * Adaptador HTTP→JSON: la lógica vive en ConsignacionVentaService (reutilizada,
 * no duplicada) — este controlador solo agrega el guardado del archivo de firma,
 * que es específico de cómo la app manda los datos (base64 en el JSON).
 *
 * Permiso separado de "Consignación venta" (modulos/consignaciones-ventas) a
 * propósito: un repartidor puede tener SOLO 'modulos/entregas-consignaciones'
 * asignado, sin ver/crear consignaciones. Sin "acceso total" (t) ve únicamente las
 * consignaciones cuyo responsable de traslado está vinculado a él (tabla
 * usuarios_responsables_traslado) — no es el filtro de "creado por mí" habitual.
 */

declare(strict_types=1);

namespace App\controllers\api\v1;

use App\controllers\api\ApiBaseController;
use App\repositories\ApiUsuarioResponsableTrasladoRepository;
use App\repositories\modulos\ConsignacionVentaRepository;
use App\Rules\modulos\ConsignacionVentaRules;
use App\Services\LogSistemaService;
use App\Services\modulos\ConsignacionVentaService;
use Exception;

class EntregasController extends ApiBaseController
{
    private ConsignacionVentaService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new ConsignacionVentaService(
            new ConsignacionVentaRepository(),
            new ConsignacionVentaRules(),
            new LogSistemaService()
        );
    }

    protected function getRutaModulo(): string
    {
        return 'modulos/entregas-consignaciones';
    }

    /**
     * GET /api/v1/entregas/pendientes?buscar=&page=
     */
    public function pendientes(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar = trim($_GET['buscar'] ?? '');
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(50, max(1, (int) ($_GET['per_page'] ?? 20)));

        $idsResponsables = $this->resolverFiltroResponsables();

        $result = $this->service->getPendientesEntrega($idEmpresa, $idsResponsables, $buscar, $page, $perPage);
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
     * GET /api/v1/entregas/obtener?id=123
     */
    public function obtener(): void
    {
        $this->requireLeer();

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->jsonError('ID_REQUERIDO', 'Falta id.', 422);
        }

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $detalle = $this->service->getDetalleCompleto($id, $idEmpresa);
        if (!$detalle) {
            $this->jsonError('NO_ENCONTRADO', 'Consignación no encontrada.', 404);
        }

        $this->jsonOk($detalle);
    }

    /**
     * POST /api/v1/entregas/registrar
     * body: { id_consignacion, uuid_cliente, capturado_en, latitud?, longitud?,
     *         precision_m?, firma_base64?, dispositivo_id?, observaciones? }
     */
    public function registrar(): void
    {
        $this->requireActualizar();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonError('METODO_NO_PERMITIDO', 'Use POST.', 405);
        }

        $body = $this->getJsonBody();
        $idConsignacion = (int) ($body['id_consignacion'] ?? 0);
        $uuid = trim((string) ($body['uuid_cliente'] ?? ''));
        $capturadoEn = trim((string) ($body['capturado_en'] ?? ''));

        if ($idConsignacion <= 0 || $uuid === '' || $capturadoEn === '') {
            $this->jsonError('DATOS_INCOMPLETOS', 'Faltan datos obligatorios (id_consignacion, uuid_cliente, capturado_en).', 422);
        }

        $idEmpresa = (int) $_SESSION['id_empresa'];

        $firmaPath = null;
        if (!empty($body['firma_base64'])) {
            try {
                $firmaPath = $this->guardarFirma($idEmpresa, $uuid, (string) $body['firma_base64']);
            } catch (Exception $e) {
                $this->jsonError('FIRMA_INVALIDA', $e->getMessage(), 422);
            }
        }

        try {
            $resultado = $this->service->registrarEntrega([
                'id_consignacion' => $idConsignacion,
                'id_empresa'      => $idEmpresa,
                'id_usuario'      => (int) $_SESSION['id_usuario'],
                'uuid_cliente'    => $uuid,
                'latitud'         => isset($body['latitud']) ? (float) $body['latitud'] : null,
                'longitud'        => isset($body['longitud']) ? (float) $body['longitud'] : null,
                'precision_m'     => isset($body['precision_m']) ? (float) $body['precision_m'] : null,
                'firma_path'      => $firmaPath,
                'capturado_en'    => $capturadoEn,
                'dispositivo_id'  => trim((string) ($body['dispositivo_id'] ?? '')),
                'canal'           => 'movil',
                'observaciones'   => $body['observaciones'] ?? null,
            ]);
        } catch (Exception $e) {
            // La firma ya se guardó en disco antes de este punto: si el registro falla
            // (conflicto de estado), no dejar el archivo huérfano.
            if ($firmaPath !== null) {
                @unlink(MVC_ROOT . '/' . $firmaPath);
            }
            // La consignación ya no admite entrega (anulada/ya entregada por otro medio
            // mientras el celular estaba offline, etc.): el cliente NO debe reintentar solo.
            $this->jsonError('NO_ADMITE_ENTREGA', $e->getMessage(), 409);
        }

        $this->jsonOk($resultado, [], $resultado['ya_entregada'] ? 200 : 201);
    }

    /** null = ver todas (acceso total); array (posiblemente vacío) = solo esos responsables. */
    private function resolverFiltroResponsables(): ?array
    {
        $perm = $this->getPermisos();
        if (!empty($perm['todo'])) {
            return null;
        }
        $repo = new ApiUsuarioResponsableTrasladoRepository();
        return $repo->getIdsResponsablesDeUsuario((int) $_SESSION['id_usuario'], (int) $_SESSION['id_empresa']);
    }

    /** Decodifica la firma (PNG en base64, con o sin prefijo data:) y la guarda en disco. */
    private function guardarFirma(int $idEmpresa, string $uuid, string $base64): string
    {
        $data = $base64;
        $comaPos = strpos($data, ',');
        if ($comaPos !== false && str_starts_with($data, 'data:')) {
            $data = substr($data, $comaPos + 1);
        }

        $binario = base64_decode($data, true);
        if ($binario === false || strlen($binario) === 0) {
            throw new Exception('La firma recibida no es una imagen válida.');
        }
        if (strlen($binario) > 2 * 1024 * 1024) {
            throw new Exception('La firma es demasiado grande (máx. 2MB).');
        }

        $dir = MVC_ROOT . "/storage/entregas/empresa_{$idEmpresa}";
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new Exception('No se pudo preparar el almacenamiento de firmas.');
        }

        $nombreArchivo = preg_replace('/[^a-zA-Z0-9_-]/', '', $uuid) . '.png';
        $rutaCompleta = $dir . '/' . $nombreArchivo;
        if (@file_put_contents($rutaCompleta, $binario) === false) {
            throw new Exception('No se pudo guardar la firma.');
        }

        return "storage/entregas/empresa_{$idEmpresa}/{$nombreArchivo}";
    }
}
