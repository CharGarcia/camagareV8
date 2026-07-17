<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\DeclaracionIvaRepository;
use App\services\modulos\DeclaracionIvaService;

class DeclaracionIvaController extends BaseModuloController
{
    private $service;
    private $repository;

    protected function getRutaModulo(): string
    {
        return 'modulos/declaracion-iva';
    }

    public function __construct()
    {
        parent::__construct();
        $this->repository = new DeclaracionIvaRepository();
        $this->service    = new DeclaracionIvaService($this->repository);
        $this->verificarMigracionCasilleros();
    }

    private function verificarMigracionCasilleros(): void
    {
        $db = \App\core\Database::getConnection();
        try {
            $st = $db->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'casilleros_declaracion_sri' AND column_name = 'id'");
            if ($st->rowCount() === 0) {
                $db->exec("ALTER TABLE casilleros_declaracion_sri ADD COLUMN id SERIAL PRIMARY KEY");
                $db->exec("ALTER TABLE casilleros_declaracion_sri ADD COLUMN editado_manualmente BOOLEAN DEFAULT FALSE");
                $db->exec("ALTER TABLE casilleros_declaracion_sri ADD COLUMN concepto TEXT DEFAULT NULL");
            } else {
                $st2 = $db->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'casilleros_declaracion_sri' AND column_name = 'editado_manualmente'");
                if ($st2->rowCount() === 0) {
                    $db->exec("ALTER TABLE casilleros_declaracion_sri ADD COLUMN editado_manualmente BOOLEAN DEFAULT FALSE");
                }
                $st3 = $db->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'casilleros_declaracion_sri' AND column_name = 'concepto'");
                if ($st3->rowCount() === 0) {
                    $db->exec("ALTER TABLE casilleros_declaracion_sri ADD COLUMN concepto TEXT DEFAULT NULL");
                }
            }

            // Validar que la tabla sri_casilleros_etiquetas tenga la columna eliminado
            $st4 = $db->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'sri_casilleros_etiquetas' AND column_name = 'eliminado'");
            if ($st4->rowCount() === 0) {
                $db->exec("ALTER TABLE sri_casilleros_etiquetas ADD COLUMN eliminado BOOLEAN DEFAULT FALSE");
            }
            
            // Validar que la tabla sri_casilleros_etiquetas tenga la columna id
            $st_id_etq = $db->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'sri_casilleros_etiquetas' AND column_name = 'id'");
            if ($st_id_etq->rowCount() === 0) {
                $db->exec("ALTER TABLE sri_casilleros_etiquetas ADD COLUMN id SERIAL PRIMARY KEY");
            }

            // Validar tipo_ambiente en casilleros_declaracion_sri
            $st5 = $db->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'casilleros_declaracion_sri' AND column_name = 'tipo_ambiente'");
            if ($st5->rowCount() === 0) {
                $db->exec("ALTER TABLE casilleros_declaracion_sri ADD COLUMN tipo_ambiente VARCHAR(1) DEFAULT '1'");
            }

            // id_establecimiento debe permitir NULL (el insert del módulo no lo envía)
            $stEst = $db->query("SELECT is_nullable FROM information_schema.columns WHERE table_name = 'casilleros_declaracion_sri' AND column_name = 'id_establecimiento'");
            $rowEst = $stEst->fetch();
            if ($rowEst && $rowEst['is_nullable'] === 'NO') {
                $db->exec("ALTER TABLE casilleros_declaracion_sri ALTER COLUMN id_establecimiento DROP NOT NULL");
            }

            // casillero_bruto debe permitir NULL: una fila puede tener solo neto o impuesto
            $stBruto = $db->query("SELECT is_nullable FROM information_schema.columns WHERE table_name = 'sri_casilleros_etiquetas' AND column_name = 'casillero_bruto'");
            $rowBruto = $stBruto->fetch();
            if ($rowBruto && $rowBruto['is_nullable'] === 'NO') {
                $db->exec("ALTER TABLE sri_casilleros_etiquetas ALTER COLUMN casillero_bruto DROP NOT NULL");
            }

            // fuente_valor: indica cómo se llena el casillero (montos sincronizados o conteo de documentos)
            $stFuente = $db->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'sri_casilleros_etiquetas' AND column_name = 'fuente_valor'");
            if ($stFuente->rowCount() === 0) {
                $db->exec("ALTER TABLE sri_casilleros_etiquetas ADD COLUMN fuente_valor VARCHAR(50) DEFAULT 'documentos'");
            }
            
            // Validar casillero_bruto, casillero_neto y casillero_impuesto en sri_casilleros_etiquetas
            $st6 = $db->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'sri_casilleros_etiquetas' AND column_name = 'casillero_bruto'");
            if ($st6->rowCount() === 0) {
                $db->exec("ALTER TABLE sri_casilleros_etiquetas RENAME COLUMN casillero TO casillero_bruto");
                $db->exec("ALTER TABLE sri_casilleros_etiquetas ADD COLUMN formula_bruto TEXT DEFAULT ''");
                $db->exec("ALTER TABLE sri_casilleros_etiquetas ADD COLUMN casillero_neto VARCHAR(255) DEFAULT ''");
                $db->exec("ALTER TABLE sri_casilleros_etiquetas ADD COLUMN formula_neto TEXT DEFAULT ''");
                $db->exec("ALTER TABLE sri_casilleros_etiquetas ADD COLUMN casillero_impuesto VARCHAR(255) DEFAULT ''");
                $db->exec("ALTER TABLE sri_casilleros_etiquetas ADD COLUMN formula_impuesto TEXT DEFAULT ''");
            }

        } catch (\Throwable $e) {
            // Ignorar errores de migración si ocurren (por locks u otra causa)
            error_log("Error migracion casilleros: " . $e->getMessage());
        }
    }

    public function index(): void
    {
        $this->requireLeer();
        
        $idEmpresa = (int) $_SESSION['id_empresa'];
        // Por defecto se declara el mes anterior al actual
        $anio = $_GET['anio'] ?? date('Y', strtotime('first day of last month'));
        $mes  = $_GET['mes']  ?? date('m', strtotime('first day of last month'));

        $estructura = $this->repository->getEstructuraFormulario();
        $anios      = $this->repository->getAniosConVentas($idEmpresa);

        $this->viewWithLayout('layouts.main', 'modulos/declaracion_iva/index', [
            'titulo' => 'Declaración de IVA (form 104 SRI)',
            'fullWidth' => true,
            'perm' => $this->getPermisos(),
            'anio' => (int) $anio,
            'mes' => $mes,
            'anios' => $anios,
            'estructura' => $estructura,
            'base' => BASE_URL,
            'rutaModulo' => $this->getRutaModulo()
        ]);
    }

    public function generarAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $anio = $_GET['anio'] ?? date('Y');
        $periodo = $_GET['periodo'] ?? date('m');
        $tipo = $_GET['tipo_periodo'] ?? 'mensual';

        try {
            if ($tipo === 'semestral') {
                if ($periodo == '1') {
                    $fechaDesde = "{$anio}-01-01";
                    $fechaHasta = "{$anio}-06-30";
                } else {
                    $fechaDesde = "{$anio}-07-01";
                    $fechaHasta = "{$anio}-12-31";
                }
            } else {
                $fechaDesde = "{$anio}-{$periodo}-01";
                $fechaHasta = date("Y-m-t", strtotime($fechaDesde));
            }

            $sincronizar = (int)($_GET['sincronizar'] ?? 0) === 1;
            if ($sincronizar) {
                if ($tipo === 'semestral') {
                    for ($m = ($periodo == '1' ? 1 : 7); $m <= ($periodo == '1' ? 6 : 12); $m++) {
                        $mesStr = str_pad((string)$m, 2, '0', STR_PAD_LEFT);
                        $this->service->sincronizarPeriodo($idEmpresa, (string)$anio, $mesStr, $idUsuario);
                    }
                } else {
                    $this->service->sincronizarPeriodo($idEmpresa, (string)$anio, (string)$periodo, $idUsuario);
                }
            }
            
            $resumenCompleto = $this->service->getResumenCompleto($idEmpresa, $fechaDesde, $fechaHasta);
            $detalleDocumentos = $this->repository->getDetalleDocumentos($idEmpresa, $fechaDesde, $fechaHasta);
            
            echo json_encode([
                'ok' => true, 
                'resumen_completo' => $resumenCompleto,
                'detalle_documentos' => $detalleDocumentos
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function actualizarCasilleroAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $nuevoCasillero = trim((string)($_POST['casillero'] ?? ''));

        if ($id <= 0 || empty($nuevoCasillero)) {
            echo json_encode(['ok' => false, 'mensaje' => 'Datos inválidos']);
            exit;
        }

        try {
            $this->repository->actualizarCasilleroManual($id, $nuevoCasillero);
            echo json_encode(['ok' => true, 'mensaje' => 'Casillero actualizado exitosamente']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function exportarExcel(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $anio = $_GET['anio'] ?? date('Y');
        $periodo = $_GET['periodo'] ?? date('m');
        $tipo = $_GET['tipo_periodo'] ?? 'mensual';

        if ($tipo === 'semestral') {
            if ($periodo == '1') {
                $fechaDesde = "{$anio}-01-01";
                $fechaHasta = "{$anio}-06-30";
                $nombreArchivo = "Declaracion_IVA_{$anio}_Semestre1.xlsx";
            } else {
                $fechaDesde = "{$anio}-07-01";
                $fechaHasta = "{$anio}-12-31";
                $nombreArchivo = "Declaracion_IVA_{$anio}_Semestre2.xlsx";
            }
        } else {
            $fechaDesde = "{$anio}-{$periodo}-01";
            $fechaHasta = date("Y-m-t", strtotime($fechaDesde));
            $nombreArchivo = "Declaracion_IVA_{$anio}_{$periodo}.xlsx";
        }

        try {
            $resumenCompleto = $this->service->getResumenCompleto($idEmpresa, $fechaDesde, $fechaHasta);
            $detalleDocumentos = $this->repository->getDetalleDocumentos($idEmpresa, $fechaDesde, $fechaHasta);

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

            // ==========================================
            // HOJA 1: RESUMEN 104
            // ==========================================
            $sheet1 = $spreadsheet->getActiveSheet();
            $sheet1->setTitle('Resumen 104');

            // Consultar empresa
            $db = \App\core\Database::getConnection();
            $st = $db->prepare("SELECT nombre FROM empresas WHERE id = ?");
            $st->execute([$idEmpresa]);
            $empresaRow = $st->fetch();
            $nombreEmpresa = $empresaRow['nombre'] ?? 'Empresa ' . $idEmpresa;

            // Fila 1: Nombre de la empresa
            $sheet1->setCellValue('A1', mb_strtoupper($nombreEmpresa, 'UTF-8'));
            $sheet1->mergeCells('A1:G1');
            $sheet1->getStyle('A1')->applyFromArray([
                'font' => ['bold' => true, 'size' => 14],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
            ]);

            // Fila 2: Formulario 104 periodo...
            $textoPeriodo = $tipo === 'semestral' ? 'Semestral' : 'Mensual';
            $meses = ['01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio',
                      '07'=>'Julio','08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'];
            $textoFecha = $tipo === 'semestral' 
                ? ($periodo == '1' ? "Primer Semestre - {$anio}" : "Segundo Semestre - {$anio}")
                : ($meses[$periodo] ?? $periodo) . " - {$anio}";
            
            $sheet1->setCellValue('A2', "Formulario 104 periodo {$textoPeriodo}, {$textoFecha}");
            $sheet1->mergeCells('A2:G2');
            $sheet1->getStyle('A2')->applyFromArray([
                'font' => ['bold' => true, 'size' => 12],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
            ]);

            // Encabezados Hoja 1
            $sheet1->setCellValue('A4', 'Concepto');
            $sheet1->setCellValue('B4', 'Cas. Bruto');
            $sheet1->setCellValue('C4', 'Valor Bruto');
            $sheet1->setCellValue('D4', 'Cas. Neto');
            $sheet1->setCellValue('E4', 'Valor Neto');
            $sheet1->setCellValue('F4', 'Cas. Impuesto');
            $sheet1->setCellValue('G4', 'Impuesto Gen.');

            // Estilos de encabezado Hoja 1
            $headerStyle1 = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '0D6EFD']],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
            ];
            $sheet1->getStyle('A4:G4')->applyFromArray($headerStyle1);
            
            $sheet1->getColumnDimension('A')->setWidth(60);
            $sheet1->getColumnDimension('C')->setWidth(15);
            $sheet1->getColumnDimension('E')->setWidth(15);
            $sheet1->getColumnDimension('G')->setWidth(15);

            $rowIdx = 5;
            $currentSeccion = '';
            
            $layout = $resumenCompleto['layout'] ?? [];
            $valores = $resumenCompleto['valores'] ?? [];

            foreach ($layout as $r) {
                if ($r['seccion'] !== $currentSeccion) {
                    $sheet1->setCellValue('A' . $rowIdx, 'SECCIÓN: ' . $r['seccion']);
                    $sheet1->mergeCells("A{$rowIdx}:G{$rowIdx}");
                    $sheet1->getStyle("A{$rowIdx}")->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E9ECEF']]
                    ]);
                    $rowIdx++;
                    $currentSeccion = $r['seccion'];
                }

                $indentStr = str_repeat('    ', (int)($r['indent'] ?? 0));
                $descFormateada = $indentStr . ($r['descripcion'] ?? '');
                
                $sheet1->setCellValue('A' . $rowIdx, $descFormateada);

                if (($r['tipo'] ?? 'valor') === 'titulo') {
                    $sheet1->mergeCells("A{$rowIdx}:G{$rowIdx}");
                } else {
                    $cBruto = $r['casillero_bruto'] ?? '';
                    $cNeto = $r['casillero_neto'] ?? '';
                    $cImp = $r['casillero_impuesto'] ?? '';

                    $vBruto = $cBruto ? (float)($valores[$cBruto] ?? 0) : null;
                    $vNeto = $cNeto ? (float)($valores[$cNeto] ?? 0) : null;
                    $vImp = $cImp ? (float)($valores[$cImp] ?? 0) : null;

                    if ($cBruto) $sheet1->setCellValueExplicit('B' . $rowIdx, $cBruto, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    if ($vBruto !== null) $sheet1->setCellValue('C' . $rowIdx, $vBruto);
                    
                    if ($cNeto) $sheet1->setCellValueExplicit('D' . $rowIdx, $cNeto, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    if ($vNeto !== null) $sheet1->setCellValue('E' . $rowIdx, $vNeto);
                    
                    if ($cImp) $sheet1->setCellValueExplicit('F' . $rowIdx, $cImp, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    if ($vImp !== null) $sheet1->setCellValue('G' . $rowIdx, $vImp);
                    
                    // Formato de moneda/número
                    $formato = (($r['fuente_valor'] ?? '') !== 'documentos') ? '#,##0' : '#,##0.00';
                    $sheet1->getStyle("C{$rowIdx}")->getNumberFormat()->setFormatCode($formato);
                    $sheet1->getStyle("E{$rowIdx}")->getNumberFormat()->setFormatCode($formato);
                    $sheet1->getStyle("G{$rowIdx}")->getNumberFormat()->setFormatCode($formato);
                }

                if (!empty($r['bold'])) {
                    $sheet1->getStyle("A{$rowIdx}:G{$rowIdx}")->getFont()->setBold(true);
                }

                $rowIdx++;
            }

            // ==========================================
            // HOJA 2: DETALLE DOCUMENTOS
            // ==========================================
            $sheet2 = $spreadsheet->createSheet();
            $sheet2->setTitle('Detalle Documentos');

            $sheet2->setCellValue('A1', 'Origen');
            $sheet2->setCellValue('B1', 'Documento');
            $sheet2->setCellValue('C1', 'Fecha');
            $sheet2->setCellValue('D1', 'Entidad');
            $sheet2->setCellValue('E1', 'Concepto');

            $sheet2->getColumnDimension('A')->setWidth(20);
            $sheet2->getColumnDimension('B')->setWidth(20);
            $sheet2->getColumnDimension('C')->setWidth(15);
            $sheet2->getColumnDimension('D')->setWidth(40);
            $sheet2->getColumnDimension('E')->setWidth(30);

            // Agrupar los detalles por documento y concepto
            $grupos = [];
            foreach ($detalleDocumentos as $d) {
                $docNum = !empty($d['establecimiento']) ? "{$d['establecimiento']}-{$d['punto_emision']}-{$d['secuencial']}" : "ID: {$d['id_origen']}";
                $keyDoc = "{$d['origen']}_{$docNum}";
                
                $concepto = $d['concepto'] ?? 'Sin concepto';
                $concepto = preg_replace('/\s*\((Base|IVA)\)$/i', '', $concepto);
                
                $keyGrupo = "{$keyDoc}_{$concepto}";
                
                if (!isset($grupos[$keyGrupo])) {
                    $grupos[$keyGrupo] = [
                        'origen' => $d['origen'],
                        'docNum' => $docNum,
                        'fecha' => $d['fecha'],
                        'entidad' => $d['entidad'],
                        'concepto' => $concepto,
                        'casilleros' => []
                    ];
                }
                
                $grupos[$keyGrupo]['casilleros'][] = [
                    'casillero' => $d['casillero'],
                    'valor' => $d['valor'],
                    'manual' => !empty($d['editado_manualmente'])
                ];
            }

            $rowIdx = 2;
            $maxCasilleros = 0;

            foreach ($grupos as $g) {
                $sheet2->setCellValue('A' . $rowIdx, str_replace('_', ' ', $g['origen'] ?? ''));
                $sheet2->setCellValueExplicit('B' . $rowIdx, $g['docNum'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet2->setCellValue('C' . $rowIdx, $g['fecha'] ?? '');
                $sheet2->setCellValue('D' . $rowIdx, $g['entidad'] ?? '');
                $sheet2->setCellValue('E' . $rowIdx, $g['concepto']);
                
                // Ordenar casilleros
                usort($g['casilleros'], function($a, $b) {
                    return (int)$a['casillero'] <=> (int)$b['casillero'];
                });

                $colIndex = 6; // F

                foreach ($g['casilleros'] as $cas) {
                    $colLetterCasillero = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                    $sheet2->setCellValueExplicit($colLetterCasillero . $rowIdx, $cas['casillero'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    
                    $colIndex++;
                    $colLetterValor = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                    $sheet2->setCellValue($colLetterValor . $rowIdx, (float)($cas['valor'] ?? 0));
                    $sheet2->getStyle($colLetterValor . $rowIdx)->getNumberFormat()->setFormatCode('#,##0.00');
                    
                    $colIndex++;
                }

                $numCasilleros = count($g['casilleros']);
                if ($numCasilleros > $maxCasilleros) {
                    $maxCasilleros = $numCasilleros;
                }

                $rowIdx++;
            }

            // Encabezados dinámicos para casilleros
            $colIndex = 6;
            for ($i = 1; $i <= $maxCasilleros; $i++) {
                $colLetterCasillero = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                $sheet2->setCellValue($colLetterCasillero . '1', 'Casillero ' . $i);
                $sheet2->getColumnDimension($colLetterCasillero)->setWidth(12);
                $colIndex++;
                
                $colLetterValor = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                $sheet2->setCellValue($colLetterValor . '1', 'Valor ' . $i);
                $sheet2->getColumnDimension($colLetterValor)->setWidth(15);
                $colIndex++;
            }

            // Aplicar estilo al encabezado
            if ($colIndex > 1) {
                $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex - 1);
                $sheet2->getStyle("A1:{$lastColLetter}1")->applyFromArray($headerStyle1);
            }

            // Descarga
            $spreadsheet->setActiveSheetIndex(0);
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $nombreArchivo . '"');
            header('Cache-Control: max-age=0');
            $writer->save('php://output');

        } catch (\Throwable $e) {
            echo "Error al generar Excel: " . $e->getMessage();
        }
        exit;
    }
}
