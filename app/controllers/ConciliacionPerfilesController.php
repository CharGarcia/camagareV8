<?php
/**
 * Catálogo global de Perfiles de Mapeo de cobros bancarios (nivel 3).
 * Define cómo leer el estado de cuenta de cada banco (Excel/CSV o PDF); todas
 * las empresas eligen uno de estos formatos en modulos/conciliacion-cobros.
 * Ruta: /config/conciliacion-perfiles (ConfigController::conciliacionPerfiles).
 * Ver database/migrations/20260923_conciliacion_perfiles_global.sql.
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\models\BancoEcuador;
use App\Services\ConciliacionPerfilService;
use App\Services\ErrorLogService;

class ConciliacionPerfilesController extends Controller
{
    private ConciliacionPerfilService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new ConciliacionPerfilService();
    }

    private function esSuperAdmin(): bool
    {
        return (int) ($_SESSION['nivel'] ?? 0) >= 3;
    }

    public function index(): void
    {
        $this->requireAuth();
        if (!$this->esSuperAdmin()) {
            $_SESSION['config_msg'] = ['danger', 'No tiene permisos para acceder a esta sección.'];
            $this->redirect(BASE_URL . '/config');
        }

        $this->viewWithLayout('layouts.main', 'conciliacionPerfiles.index', [
            'titulo'    => 'Perfiles de Mapeo de Cobros Bancarios',
            'fullWidth' => true,
            'perfiles'  => $this->service->listar(),
            'bancos'    => (new BancoEcuador())->getAll(),
        ]);
    }

    public function listarAjax(): void
    {
        $this->responderJson(__FUNCTION__, fn () => $this->service->listar(trim((string) ($_GET['b'] ?? ''))));
    }

    public function guardarAjax(): void
    {
        $data = json_decode(file_get_contents('php://input') ?: '[]', true) ?: $_POST;
        $this->responderJson(__FUNCTION__, fn () => $this->service->guardar($data, (int) $_SESSION['id_usuario']));
    }

    public function cambiarEstadoAjax(): void
    {
        $data = json_decode(file_get_contents('php://input') ?: '[]', true) ?: $_POST;
        $this->responderJson(__FUNCTION__, function () use ($data) {
            $this->service->cambiarActivo((int) ($data['id'] ?? 0), !empty($data['activo']), (int) $_SESSION['id_usuario']);
            return null;
        });
    }

    public function eliminarAjax(): void
    {
        $data = json_decode(file_get_contents('php://input') ?: '[]', true) ?: $_POST;
        $this->responderJson(__FUNCTION__, function () use ($data) {
            $this->service->eliminar((int) ($data['id'] ?? 0), (int) $_SESSION['id_usuario']);
            return null;
        });
    }

    /** Sube un archivo de muestra y devuelve las primeras filas/líneas crudas (y, en PDF, el resultado del patrón). */
    public function previsualizarArchivoAjax(): void
    {
        $this->responderJson(__FUNCTION__, fn () => $this->service->previsualizarArchivo(
            $_FILES['archivo'] ?? [],
            strtoupper(trim((string) ($_POST['tipo_archivo'] ?? ''))),
            (int) ($_POST['fila_inicio'] ?? 0),
            trim((string) ($_POST['regex_prueba'] ?? '')) ?: null,
            trim((string) ($_POST['tipo_credito_prueba'] ?? '')) ?: null,
        ));
    }

    /** Analiza el PDF de muestra y propone un patrón (regex) de línea de datos para el perfil. */
    public function sugerirRegexPdfAjax(): void
    {
        $this->responderJson(__FUNCTION__, fn () => $this->service->sugerirRegexPdf($_FILES['archivo'] ?? []));
    }

    private function responderJson(string $accion, callable $fn): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        if (!$this->esSuperAdmin()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Solo el superadministrador puede configurar los perfiles de mapeo.']);
            exit;
        }

        try {
            echo json_encode(['ok' => true, 'data' => $fn()], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => $accion]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
}
