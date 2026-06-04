<?php
declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\core\Database;
use App\Services\LogSistemaService;
use App\Services\modulos\ImportadorExcelService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PDO;

class ImportadorExcelController extends Controller
{
    private ImportadorExcelService $service;

    public function __construct()
    {
        parent::__construct();
        
        $this->requireAuth();
        if (!in_array($_SESSION['nivel'] ?? 0, [2, 3])) {
            $_SESSION['config_msg'] = ['danger', 'No tiene permisos para acceder al importador.'];
            $this->redirect(BASE_URL . '/config');
        }
        
        $db = Database::getConnection();
        $logService = new LogSistemaService($db);
        $this->service = new ImportadorExcelService($db, $logService);
    }

    public function index(): void
    {
        $empresasDestino = [];
        
        if (($_SESSION['nivel'] ?? 0) === 3) {
            $db = Database::getConnection();
            $st = $db->query("SELECT id, nombre AS razon_social, ruc FROM empresas WHERE eliminado = false ORDER BY nombre");
            $empresasDestino = $st->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $empresasDestino = [
                [
                    'id' => $_SESSION['id_empresa'], 
                    'razon_social' => 'Empresa Actual', 
                    'ruc' => ''
                ]
            ];
        }

        $entidades = $this->service->getEntidadesDisponibles();

        $this->viewWithLayout('layouts.main', 'config.importador_excel', [
            'titulo' => 'Importador desde Excel',
            'empresasDestino' => $empresasDestino,
            'entidades' => $entidades,
            'nivel' => $_SESSION['nivel'] ?? 0
        ]);
    }

    public function descargarPlantillaAjax(): void
    {
        $entidad = $_GET['entidad'] ?? '';
        $entidades = $this->service->getEntidadesDisponibles();
        
        if (!isset($entidades[$entidad])) {
            die('Entidad no válida.');
        }

        $columnas = $entidades[$entidad]['columnas'];
        
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        $colIndex = 1;
        foreach ($columnas as $col) {
            $sheet->setCellValueExplicit([$colIndex, 1], $col, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->getColumnDimensionByColumn($colIndex)->setAutoSize(true);
            $sheet->getStyle([$colIndex, 1])->getFont()->setBold(true);
            $colIndex++;
        }
        
        $sheet->setCellValue([1, 2], 'Llenar datos desde esta fila. Reemplazar esta fila con datos reales.');
        $sheet->getStyle([1, 2])->getFont()->setItalic(true)->getColor()->setARGB('FF888888');

        $fileName = "plantilla_{$entidad}.xlsx";

        ob_clean();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');
        header('Cache-Control: max-age=1');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Cache-Control: cache, must-revalidate');
        header('Pragma: public');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public function procesarImportacionAjax(): void
    {
        header('Content-Type: application/json');

        try {
            if (!isset($_FILES['archivo_excel']) || $_FILES['archivo_excel']['error'] !== UPLOAD_ERR_OK) {
                throw new \Exception('Debe subir un archivo Excel válido (.xlsx).');
            }

            $entidad = $_POST['entidad'] ?? '';
            $idEmpresaDestino = !empty($_POST['id_empresa']) ? (int) $_POST['id_empresa'] : (int) ($_SESSION['id_empresa'] ?? 0);
            $tipoAmbiente = !empty($_POST['tipo_ambiente']) ? (string) $_POST['tipo_ambiente'] : '1';
            
            $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
            $nivelUsuario = (int) ($_SESSION['nivel'] ?? 0);

            if ($nivelUsuario < 3 && $idEmpresaDestino !== (int) ($_SESSION['id_empresa'] ?? 0)) {
                throw new \Exception('No tiene permisos para importar datos en otra empresa.');
            }

            $archivoTmp = $_FILES['archivo_excel']['tmp_name'];

            $registrosInsertados = $this->service->procesar($archivoTmp, $entidad, $idEmpresaDestino, $tipoAmbiente, $idUsuario);

            echo json_encode([
                'ok' => true,
                'mensaje' => "Importación completada exitosamente. Se insertaron {$registrosInsertados} registros."
            ]);

        } catch (\Throwable $e) {
            echo json_encode([
                'ok' => false,
                'mensaje' => 'Error de importación: ' . $e->getMessage()
            ]);
        }
        exit;
    }
}
