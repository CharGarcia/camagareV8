<?php
/**
 * Controlador API v1: Compras (solo consulta + alta por número de autorización SRI).
 * Adaptador HTTP→JSON puro: reutiliza ComprasRepository/ComprasService tal cual los usa
 * la web, y para la carga por autorización reutiliza el mismo par de servicios que ya usa
 * DescargasSriController::procesarClavesAccesoAjax() (web) — SriService::obtenerComprobanteXml()
 * + DocumentoAutomatedRegisterService::procesarYRegistrar() — sin reimplementar el parseo del
 * XML ni la inserción de cabecera/detalle/proveedor/impuestos/pagos.
 *
 * Alcance "básico" a propósito: la app NO permite crear una compra a mano (formulario
 * completo), solo listar/ver y cargar por número de autorización (clave de acceso
 * electrónica, 49 dígitos) — la alta manual sigue siendo exclusiva de la web.
 * Mismo permiso 'modulos/compras' que la web (letra 'w' para la carga por autorización,
 * ya que crea un registro).
 */

declare(strict_types=1);

namespace App\controllers\api\v1;

use App\controllers\api\ApiBaseController;
use App\repositories\modulos\ComprasRepository;
use App\Services\modulos\ComprasService;
use App\Services\modulos\DocumentoAutomatedRegisterService;
use App\Services\SriService;
use Throwable;

class ComprasController extends ApiBaseController
{
    private ComprasRepository $repository;
    private ComprasService $service;

    public function __construct()
    {
        parent::__construct();
        $this->repository = new ComprasRepository();
        $this->service = new ComprasService();
    }

    protected function getRutaModulo(): string
    {
        return 'modulos/compras';
    }

    /**
     * GET /api/v1/compras/listar?buscar=&page=&per_page=
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

        $this->jsonOk($result['rows'], [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
        ]);
    }

    /**
     * GET /api/v1/compras/obtener?id=123
     */
    public function obtener(): void
    {
        $this->requireLeer();

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->jsonError('ID_REQUERIDO', 'Falta id.', 422);
        }

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $compra = $this->service->getPorId($id, $idEmpresa);
        if (!$compra) {
            $this->jsonError('NO_ENCONTRADO', 'Compra no encontrada.', 404);
        }

        $this->jsonOk($compra);
    }

    /**
     * POST /api/v1/compras/cargar-por-autorizacion
     * body: { numero_autorizacion }
     * numero_autorizacion = clave de acceso electrónica del SRI (49 dígitos). Consulta el
     * webservice oficial del SRI y, si está autorizado, registra la compra automáticamente
     * (mismo motor que usa Descargas SRI en la web).
     */
    public function cargarPorAutorizacion(): void
    {
        $this->requireCrear();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonError('METODO_NO_PERMITIDO', 'Use POST.', 405);
        }

        $body = $this->getJsonBody();
        $clave = preg_replace('/\D/', '', (string) ($body['numero_autorizacion'] ?? '')) ?? '';

        if (strlen($clave) !== 49) {
            $this->jsonError('CLAVE_INVALIDA', 'El número de autorización debe tener 49 dígitos (clave de acceso electrónica del SRI).', 422);
        }

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        $sriService = new SriService();
        $respuesta = $sriService->obtenerComprobanteXml($clave);

        if (empty($respuesta['ok']) || empty($respuesta['xml'])) {
            $this->jsonError(
                'SRI_NO_DISPONIBLE',
                $respuesta['mensaje'] ?? 'No se pudo obtener el comprobante desde el SRI.',
                422,
                ['estado_sri' => $respuesta['estado'] ?? 'DESCONOCIDO']
            );
        }

        try {
            $registerService = new DocumentoAutomatedRegisterService();
            $resRegistro = $registerService->procesarYRegistrar((string) $respuesta['xml'], $idEmpresa, $idUsuario);
        } catch (Throwable $e) {
            $this->jsonError('ERROR_REGISTRO', $e->getMessage(), 422);
        }

        if (empty($resRegistro['ok'])) {
            $this->jsonError(
                'ERROR_REGISTRO',
                $resRegistro['mensaje'] ?? $resRegistro['error'] ?? 'No se pudo registrar la compra.',
                422,
                ['estado_registro' => $resRegistro['estado_registro'] ?? 'ERROR']
            );
        }

        $this->jsonOk([
            'id' => $resRegistro['id'] ?? null,
            'existe' => (bool) ($resRegistro['existe'] ?? false),
            'estado_registro' => $resRegistro['estado_registro'] ?? 'REGISTRADO',
            'mensaje' => $resRegistro['mensaje'] ?? 'Compra registrada correctamente.',
            'numero_documento' => $resRegistro['numero_documento'] ?? null,
            'tipo_nombre' => $resRegistro['tipo_nombre'] ?? null,
            'emisor' => $resRegistro['emisor'] ?? null,
            'total' => $resRegistro['total'] ?? null,
        ], [], 201);
    }
}
