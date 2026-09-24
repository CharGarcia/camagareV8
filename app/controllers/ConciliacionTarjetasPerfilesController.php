<?php
/**
 * Catálogo global de Perfiles de lectura del estado de cuenta de tarjetas (nivel 3).
 * Define cómo leer el archivo de cada procesadora (Payphone, Nuvei, datáfono de cada
 * banco) en Excel/CSV o PDF; todas las empresas eligen uno de estos formatos en
 * modulos/conciliacion-tarjetas.
 * Ruta: /config/conciliacion-tarjetas-perfiles (ConfigController::conciliacionTarjetasPerfiles).
 * Ver database/migrations/20260924_conciliacion_tarjetas_perfiles_global.sql.
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\Helpers\PreferenciasHelper;
use App\models\BancoEcuador;
use App\Services\ConciliacionTarjetasPerfilService;
use App\Services\ErrorLogService;

class ConciliacionTarjetasPerfilesController extends Controller
{
    private const COLUMNAS_ORDEN = ['tipo_procesadora', 'nombre_banco', 'nombre_perfil', 'tipo_archivo', 'nivel', 'activo'];

    private ConciliacionTarjetasPerfilService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new ConciliacionTarjetasPerfilService();
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

        // Orden persistido por CMG_initSort (clave __vista__ del módulo); el orden se aplica en el navegador.
        $vista = PreferenciasHelper::getPreferenciasVista('conciliacion-tarjetas-perfiles');
        $ordenCol = in_array($vista['__ordenCol__'] ?? '', self::COLUMNAS_ORDEN, true) ? $vista['__ordenCol__'] : 'tipo_procesadora';
        $ordenDir = strtoupper((string) ($vista['__ordenDir__'] ?? '')) === 'DESC' ? 'DESC' : 'ASC';

        $this->viewWithLayout('layouts.main', 'conciliacionTarjetasPerfiles.index', [
            'titulo'    => 'Perfiles de Lectura de Tarjetas',
            'perfiles'  => $this->service->listar(),
            'bancos'    => (new BancoEcuador())->getAll(),
            'ordenCol'  => $ordenCol,
            'ordenDir'  => $ordenDir,
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

    public function eliminarAjax(): void
    {
        $data = json_decode(file_get_contents('php://input') ?: '[]', true) ?: $_POST;
        $this->responderJson(__FUNCTION__, function () use ($data) {
            $this->service->eliminar((int) ($data['id'] ?? 0), (int) $_SESSION['id_usuario']);
            return null;
        });
    }

    /** Sube un archivo de muestra y devuelve las primeras filas/líneas crudas y el resultado del mapeo actual. */
    public function previsualizarArchivoAjax(): void
    {
        $this->responderJson(__FUNCTION__, fn () => $this->service->previsualizarArchivo(
            $_FILES['archivo'] ?? [],
            strtoupper(trim((string) ($_POST['tipo_archivo'] ?? 'EXCEL'))),
            (int) ($_POST['fila_inicio'] ?? 0),
            json_decode((string) ($_POST['mapeo_prueba'] ?? ''), true) ?: null,
            trim((string) ($_POST['formato_fecha'] ?? '')),
            (string) ($_POST['separador_decimal'] ?? '.'),
        ));
    }

    private function responderJson(string $accion, callable $fn): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        if (!$this->esSuperAdmin()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Solo el superadministrador puede configurar los perfiles de lectura.']);
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
