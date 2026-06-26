<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\Services\modulos\AtsService;
use App\Services\modulos\AtsExcelService;
use App\Services\Xml\XmlAtsService;
use App\Services\Xml\AtsValidatorService;
use App\repositories\modulos\AtsRepository;
use App\Services\LogSistemaService;
use App\models\Empresa;

/**
 * Anexo Transaccional Simplificado (ATS).
 * Ruta MVC: modulos/anexo-ats (submodulos_menu.ruta = 'modulos/anexo-ats').
 *
 * Genera el ats.xml del período a partir de compras, liquidaciones de compra
 * y sus retenciones (retencion_compra_cabecera/detalle).
 */
class AnexoAtsController extends BaseModuloController
{
    private AtsService $service;
    private AtsExcelService $excel;

    protected function getRutaModulo(): string
    {
        return 'modulos/anexo-ats';
    }

    public function __construct()
    {
        parent::__construct();
        $this->service = new AtsService(
            new AtsRepository(),
            new XmlAtsService(),
            new LogSistemaService(),
            new AtsValidatorService()
        );
        $this->excel = new AtsExcelService($this->service);
    }

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $empresaModel = new Empresa();
        $empresaData  = $empresaModel->getPorId($idEmpresa);

        $this->viewWithLayout('layouts.main', 'modulos/anexo_ats/index', [
            'titulo'     => 'Anexo Transaccional Simplificado (ATS)',
            'perm'       => $this->getPermisos(),
            'empresa'    => $empresaData,
            'anioActual' => (int) date('Y'),
            'base'       => BASE_URL,
            'rutaModulo' => $this->getRutaModulo(),
            'fullWidth'  => true,
        ]);
    }

    /** POST: genera el anexo y devuelve enlaces de descarga (JSON). */
    public function generarAjax(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        $mes       = trim($_POST['mes'] ?? '');
        $anio      = trim($_POST['anio'] ?? '');
        $semestral = !empty($_POST['semestral']) && in_array((string) $_POST['semestral'], ['1', 'true', 'on'], true);

        if ($mes === '' || $anio === '') {
            $this->json(['ok' => false, 'mensaje' => 'Seleccione mes y año.'], 422);
        }

        try {
            $res = $this->service->generar($idEmpresa, $idUsuario, $mes, $anio, $semestral);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'mensaje' => 'Error al generar el anexo: ' . $e->getMessage()], 500);
        }

        if (empty($res['ok'])) {
            $this->json(['ok' => false, 'mensaje' => $res['mensaje'] ?? 'No se pudo generar el anexo.'], 400);
        }

        $ruta    = rtrim(BASE_URL, '/') . '/' . $this->getRutaModulo();
        $urlBase = $ruta . '/descargar?archivo=';
        $urlExcel = $ruta . '/excel?mes=' . urlencode($mes) . '&anio=' . urlencode($anio)
                  . '&semestral=' . ($semestral ? '1' : '0');
        $this->json([
            'ok'           => true,
            'registros'    => $res['registros'],
            'xml'          => $res['nombre_xml'],
            'url_xml'      => $urlBase . urlencode($res['nombre_xml']),
            'zip'          => $res['nombre_zip'],
            'url_zip'      => $res['nombre_zip'] ? $urlBase . urlencode($res['nombre_zip']) : null,
            'url_excel'    => $urlExcel,
            'errores'      => $res['errores'] ?? [],
            'advertencias' => $res['advertencias'] ?? [],
        ]);
    }

    /** GET: genera y descarga el Excel de detalle (?mes=&anio=&semestral=). */
    public function excel(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $mes       = trim($_GET['mes'] ?? '');
        $anio      = trim($_GET['anio'] ?? '');
        $semestral = !empty($_GET['semestral']) && in_array((string) $_GET['semestral'], ['1', 'true', 'on'], true);

        if ($mes === '' || $anio === '') {
            http_response_code(422);
            echo 'Seleccione mes y año.';
            exit;
        }

        try {
            $res = $this->excel->generar($idEmpresa, $mes, $anio, $semestral);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo 'Error al generar el Excel: ' . $e->getMessage();
            exit;
        }

        if (empty($res['ok']) || empty($res['ruta']) || !is_file($res['ruta'])) {
            http_response_code(400);
            echo $res['mensaje'] ?? 'No se pudo generar el Excel.';
            exit;
        }

        if (!headers_sent()) {
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $res['nombre'] . '"');
            header('Content-Length: ' . filesize($res['ruta']));
            header('Cache-Control: max-age=0');
        }
        readfile($res['ruta']);
        exit;
    }

    /** GET: descarga el archivo generado (?archivo=ATmmaaaa.xml|zip). */
    public function descargar(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $nombre    = (string) ($_GET['archivo'] ?? '');

        $ruta = $this->service->rutaArchivo($idEmpresa, $nombre);
        if ($ruta === null) {
            http_response_code(404);
            echo 'Archivo no encontrado.';
            exit;
        }

        $esZip = str_ends_with($nombre, '.zip');
        if (!headers_sent()) {
            header('Content-Type: ' . ($esZip ? 'application/zip' : 'application/xml'));
            header('Content-Disposition: attachment; filename="' . $nombre . '"');
            header('Content-Length: ' . filesize($ruta));
            header('Cache-Control: no-store');
        }
        readfile($ruta);
        exit;
    }
}
