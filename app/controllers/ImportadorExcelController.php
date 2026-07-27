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
            $st = $db->query(
                "SELECT id,
                        establecimiento,
                        COALESCE(NULLIF(nombre_comercial,''), nombre) AS razon_social,
                        ruc
                   FROM empresas
                  WHERE eliminado = false
                  ORDER BY ruc, establecimiento"
            );
            $empresasDestino = $st->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $db = Database::getConnection();
            $st = $db->prepare(
                "SELECT id,
                        establecimiento,
                        COALESCE(NULLIF(nombre_comercial,''), nombre) AS razon_social,
                        ruc
                   FROM empresas
                  WHERE id = ? AND eliminado = false
                  LIMIT 1"
            );
            $st->execute([$_SESSION['id_empresa']]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            $empresasDestino = $row ? [$row] : [[
                'id'             => $_SESSION['id_empresa'],
                'establecimiento'=> '001',
                'razon_social'   => 'Empresa Actual',
                'ruc'            => ''
            ]];
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
        $entidad      = $_GET['entidad'] ?? '';
        $nivelUsuario = (int)($_SESSION['nivel'] ?? 0);
        $entidades    = $this->service->getEntidadesDisponibles();

        if (!isset($entidades[$entidad])) {
            die('Entidad no válida.');
        }

        // Resolver id_empresa de forma segura según nivel
        if ($nivelUsuario >= 3) {
            // Superadmin: usa la empresa seleccionada en el formulario, o la de sesión como fallback
            $idEmpresaPlantilla = !empty($_GET['id_empresa']) ? (int)$_GET['id_empresa'] : (int)($_SESSION['id_empresa'] ?? 0);
        } else {
            // Niveles 1 y 2: siempre su propia empresa, sin importar lo que venga en GET
            $idEmpresaPlantilla = (int)($_SESSION['id_empresa'] ?? 0);
        }

        // Entidades con plantilla propia de varias hojas
        if ($entidad === 'unidades_medida') {
            $this->enviarXlsx(
                $this->generarPlantillaUnidadesMedida($idEmpresaPlantilla),
                "plantilla_{$entidad}.xlsx"
            );
            return;
        }

        $columnas     = $entidades[$entidad]['columnas'];
        $colNumericas = $entidades[$entidad]['col_numericas'] ?? []; // índices base 0

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Datos');

        $colIndex = 1;
        foreach ($columnas as $idx => $col) {
            $esNumerica = in_array($idx, $colNumericas, true);

            // Cabecera
            $sheet->setCellValueExplicit([$colIndex, 1], $col, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->getColumnDimensionByColumn($colIndex)->setWidth(22);
            $sheet->getStyle([$colIndex, 1])->getFont()->setBold(true);
            $sheet->getStyle([$colIndex, 1])->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FF4472C4');
            $sheet->getStyle([$colIndex, 1])->getFont()->getColor()->setARGB('FFFFFFFF');

            // Formato de celda para filas de datos (fila 3 en adelante, 1000 filas)
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $rangoData = "{$colLetter}3:{$colLetter}1002";

            if ($esNumerica) {
                $sheet->getStyle($rangoData)->getNumberFormat()
                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);
            } else {
                // Texto: evita que Excel altere valores como "04", "9999999999999", etc.
                $sheet->getStyle($rangoData)->getNumberFormat()
                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
            }

            $colIndex++;
        }

        $sheet->setCellValueExplicit(
            [1, 2],
            'Llenar datos desde esta fila. Reemplazar esta fila con datos reales.',
            \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
        );
        $sheet->getStyle([1, 2])->getFont()->setItalic(true)->getColor()->setARGB('FF888888');

        // Hojas de referencia para proveedores
        if ($entidad === 'proveedores') {
            $db = \App\core\Database::getConnection();

            // Helper local para crear hojas de referencia con encabezado coloreado
            $crearHojaRef = function(string $titulo, array $headers, array $filas, string $colorArgb) use ($spreadsheet): void {
                $sh = $spreadsheet->createSheet();
                $sh->setTitle($titulo);
                foreach ($headers as $ci => $h) {
                    $col = $ci + 1;
                    $sh->setCellValueExplicit([$col, 1], $h, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sh->getColumnDimensionByColumn($col)->setAutoSize(true);
                    $sh->getStyle([$col, 1])->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
                    $sh->getStyle([$col, 1])->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setARGB($colorArgb);
                }
                $sh->getStyle([1, 1])->getBorders()->getAllBorders()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);
                $row = 2;
                foreach ($filas as $fila) {
                    foreach (array_values($fila) as $ci => $val) {
                        $sh->setCellValueExplicit([$ci + 1, $row], (string)$val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    }
                    $sh->getStyle([1, $row])->getFont()->setBold(true);
                    $row++;
                }
            };

            // Hoja: Tipos_ID (04,05,06,08 — no aplica 07 para proveedores)
            $stTipos = $db->query("SELECT codigo, nombre FROM identificador_comprador_vendedor WHERE status = 1 AND codigo IN ('04','05','06','08') ORDER BY codigo ASC");
            $crearHojaRef('Tipos_ID', ['CODIGO (usar este valor)', 'NOMBRE'], $stTipos->fetchAll(PDO::FETCH_ASSOC), 'FF7030A0');

            // Hoja: Tipos_Empresa
            $stTE = $db->query("SELECT id, nombre FROM tipo_empresa WHERE status = 1 ORDER BY nombre ASC");
            $crearHojaRef('Tipos_Empresa', ['NOMBRE (usar este valor)', 'ID'], array_map(fn($r) => ['nombre' => $r['nombre'], 'id' => $r['id']], $stTE->fetchAll(PDO::FETCH_ASSOC)), 'FF0070C0');

            // Hoja: Bancos
            $stBancos = $db->query("SELECT nombre_banco FROM bancos_ecuador WHERE status = 1 ORDER BY nombre_banco ASC");
            $crearHojaRef('Bancos', ['NOMBRE_BANCO (usar este valor)'], array_map(fn($r) => ['nombre_banco' => $r['nombre_banco']], $stBancos->fetchAll(PDO::FETCH_ASSOC)), 'FF00B050');

            // Hoja: Tipo_Cuenta (valores fijos)
            $crearHojaRef('Tipo_Cuenta', ['VALOR (usar este)', 'DESCRIPCION'], [
                ['Ahorros',   'Cuenta de ahorros'],
                ['Corriente', 'Cuenta corriente'],
                ['Virtual',   'Cuenta virtual'],
                ['Otro',      'Otro tipo de cuenta'],
            ], 'FFFF6600');

            // Hoja: Sustento_Tributario
            $stST = $db->query("SELECT codigo, nombre FROM sustento_tributario WHERE status = 1 ORDER BY codigo ASC");
            $crearHojaRef('Sustento_Tributario', ['CODIGO (usar este valor)', 'NOMBRE'], $stST->fetchAll(PDO::FETCH_ASSOC), 'FFC00000');
        }

        // Hoja extra con tipos de identificación para clientes
        if ($entidad === 'clientes') {
            $db = \App\core\Database::getConnection();
            $stTipos = $db->query(
                "SELECT codigo, nombre FROM identificador_comprador_vendedor
                  WHERE status = 1 AND codigo IN ('04','05','06','07','08')
                  ORDER BY codigo ASC"
            );
            $tiposId = $stTipos ? $stTipos->fetchAll(PDO::FETCH_ASSOC) : [];

            $sheetTipos = $spreadsheet->createSheet();
            $sheetTipos->setTitle('Tipos_ID');

            $headersTipos = ['CODIGO (usar este valor)', 'NOMBRE'];
            foreach ($headersTipos as $ci => $titulo) {
                $col = $ci + 1;
                $sheetTipos->setCellValueExplicit([$col, 1], $titulo, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheetTipos->getColumnDimensionByColumn($col)->setAutoSize(true);
                $sheetTipos->getStyle([$col, 1])->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
                $sheetTipos->getStyle([$col, 1])->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FF7030A0');
            }
            $sheetTipos->getStyle([1, 1])->getBorders()->getAllBorders()
                ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);

            $rowT = 2;
            foreach ($tiposId as $t) {
                $sheetTipos->setCellValueExplicit([1, $rowT], (string)$t['codigo'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheetTipos->setCellValueExplicit([2, $rowT], (string)$t['nombre'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheetTipos->getStyle([1, $rowT])->getFont()->setBold(true);
                $rowT++;
            }
        }

        // Hoja extra con tarifas IVA para la entidad productos
        if ($entidad === 'productos') {
            $db = \App\core\Database::getConnection();
            $stIva = $db->query("SELECT codigo, tarifa, porcentaje_iva FROM tarifa_iva WHERE status = 1 ORDER BY porcentaje_iva ASC");
            $tarifas = $stIva ? $stIva->fetchAll(PDO::FETCH_ASSOC) : [];

            $sheetIva = $spreadsheet->createSheet();
            $sheetIva->setTitle('Tarifas_IVA');

            $headersIva = [
                'CODIGO_IVA (usar este valor en la plantilla)',
                'NOMBRE_TARIFA',
                'PORCENTAJE %',
            ];
            foreach ($headersIva as $ci => $titulo) {
                $col = $ci + 1;
                $sheetIva->setCellValueExplicit([$col, 1], $titulo, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheetIva->getColumnDimensionByColumn($col)->setAutoSize(true);
                $sheetIva->getStyle([$col, 1])->getFont()->setBold(true);
                $sheetIva->getStyle([$col, 1])->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FF70AD47');
                $sheetIva->getStyle([$col, 1])->getFont()->getColor()->setARGB('FFFFFFFF');
            }
            // Resaltar la columna CODIGO_IVA con borde más grueso para indicar que es el campo a usar
            $sheetIva->getStyle([1, 1])->getBorders()->getAllBorders()
                ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);

            $rowIva = 2;
            foreach ($tarifas as $t) {
                $sheetIva->setCellValueExplicit([1, $rowIva], (string)$t['codigo'],       \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheetIva->setCellValueExplicit([2, $rowIva], (string)$t['tarifa'],        \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheetIva->setCellValueExplicit([3, $rowIva], (string)$t['porcentaje_iva'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                // Resaltar la celda de código para que sea más visible
                $sheetIva->getStyle([1, $rowIva])->getFont()->setBold(true);
                $rowIva++;
            }
        }

        // Hoja extra con unidades de medida (solo para productos)
        if ($entidad === 'productos') {
            // Obtener nombre del establecimiento para la nota informativa
            $stEmp = $db->prepare(
                "SELECT establecimiento, COALESCE(NULLIF(nombre_comercial,''), nombre) AS nombre_emp, ruc
                   FROM empresas WHERE id = ? AND eliminado = false LIMIT 1"
            );
            $stEmp->execute([$idEmpresaPlantilla]);
            $empRow = $stEmp->fetch(PDO::FETCH_ASSOC);
            $labelEstablecimiento = $empRow
                ? 'Est. ' . ($empRow['establecimiento'] ?? '001') . ' - ' . $empRow['nombre_emp'] . ' (RUC: ' . $empRow['ruc'] . ')'
                : 'ID Empresa: ' . $idEmpresaPlantilla;

            if ($idEmpresaPlantilla > 0) {
                $stUm = $db->prepare(
                    "SELECT um.codigo, um.nombre, um.abreviatura,
                            tm.nombre AS tipo_medida
                       FROM unidades_medida um
                       LEFT JOIN tipo_medida tm ON tm.id = um.id_tipo
                      WHERE um.id_empresa = ?
                        AND um.eliminado = false
                        AND um.status = true
                      ORDER BY tm.nombre ASC, um.codigo ASC"
                );
                $stUm->execute([$idEmpresaPlantilla]);
                $unidades = $stUm->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $unidades = [];
            }

            $sheetUm = $spreadsheet->createSheet();
            $sheetUm->setTitle('Unidades_Medida');

            // Fila 1: nota del establecimiento al que pertenece esta plantilla
            $notaEstablecimiento = '⚠ Esta información corresponde al establecimiento: ' . $labelEstablecimiento
                . '. Si carga este archivo en otro establecimiento, la importación será rechazada.';
            $sheetUm->setCellValueExplicit([1, 1], $notaEstablecimiento, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheetUm->mergeCells('A1:D1');
            $sheetUm->getStyle('A1:D1')->applyFromArray([
                'font'      => ['bold' => true, 'color' => ['argb' => 'FF7B0000']],
                'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFF2CC']],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT, 'wrapText' => true],
                'borders'   => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['argb' => 'FFCC8800']]],
            ]);
            $sheetUm->getRowDimension(1)->setRowHeight(30);

            // Fila 2: cabeceras de columnas
            $headersUm = [
                'CODIGO_MEDIDA (usar este valor en la plantilla)',
                'NOMBRE',
                'ABREVIATURA',
                'TIPO DE MEDIDA',
            ];
            foreach ($headersUm as $ci => $titulo) {
                $col = $ci + 1;
                $sheetUm->setCellValueExplicit([$col, 2], $titulo, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheetUm->getColumnDimensionByColumn($col)->setAutoSize(true);
                $sheetUm->getStyle([$col, 2])->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
                $sheetUm->getStyle([$col, 2])->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFED7D31');
            }
            $sheetUm->getStyle([1, 2])->getBorders()->getAllBorders()
                ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);

            // Fila 3+: datos
            if (empty($unidades)) {
                $sheetUm->setCellValueExplicit(
                    [1, 3],
                    'No hay unidades de medida registradas para esta empresa. Créelas primero en Configuración → Unidades de Medida.',
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                );
                $sheetUm->getStyle([1, 3])->getFont()->setItalic(true)->getColor()->setARGB('FFCC0000');
                $sheetUm->mergeCells('A3:D3');
            } else {
                $rowUm = 3;
                foreach ($unidades as $u) {
                    $sheetUm->setCellValueExplicit([1, $rowUm], (string)$u['codigo'],      \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheetUm->setCellValueExplicit([2, $rowUm], (string)$u['nombre'],      \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheetUm->setCellValueExplicit([3, $rowUm], (string)($u['abreviatura'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheetUm->setCellValueExplicit([4, $rowUm], (string)($u['tipo_medida'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheetUm->getStyle([1, $rowUm])->getFont()->setBold(true);
                    $rowUm++;
                }
            }

            // Hoja oculta _Config: guarda el id_empresa para validar al importar
            $sheetConfig = $spreadsheet->createSheet();
            $sheetConfig->setTitle('_Config');
            $sheetConfig->setCellValueExplicit([1, 1], 'id_empresa',         \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheetConfig->setCellValueExplicit([2, 1], (string)$idEmpresaPlantilla, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheetConfig->setCellValueExplicit([1, 2], 'establecimiento',    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheetConfig->setCellValueExplicit([2, 2], $labelEstablecimiento, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheetConfig->getSheetState(); // existe solo para referencia
            // Ocultar la hoja _Config del usuario
            $sheetConfig->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_VERYHIDDEN);
        }

        $spreadsheet->setActiveSheetIndex(0);
        $this->enviarXlsx($spreadsheet, "plantilla_{$entidad}.xlsx");
    }

    /**
     * Descarga el libro como .xlsx y termina la ejecución.
     */
    private function enviarXlsx(Spreadsheet $spreadsheet, string $fileName): void
    {
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

    /**
     * Plantilla de "Unidades y tipos de medida": un solo archivo con las dos
     * tablas del catálogo, sus instrucciones, el catálogo sugerido listo para
     * copiar y lo que la empresa ya tiene registrado.
     *
     * Hojas: Instrucciones · Tipos_Medida · Unidades · Catalogo_Sugerido ·
     *        Ya_Registrado · _Config (oculta)
     */
    private function generarPlantillaUnidadesMedida(int $idEmpresaPlantilla): Spreadsheet
    {
        $db = Database::getConnection();

        $stEmp = $db->prepare(
            "SELECT establecimiento, COALESCE(NULLIF(nombre_comercial,''), nombre) AS nombre_emp, ruc
               FROM empresas WHERE id = ? AND eliminado = false LIMIT 1"
        );
        $stEmp->execute([$idEmpresaPlantilla]);
        $empRow = $stEmp->fetch(PDO::FETCH_ASSOC);
        $labelEstablecimiento = $empRow
            ? 'Est. ' . ($empRow['establecimiento'] ?? '001') . ' - ' . $empRow['nombre_emp'] . ' (RUC: ' . $empRow['ruc'] . ')'
            : 'ID Empresa: ' . $idEmpresaPlantilla;

        $spreadsheet = new Spreadsheet();

        // ── Hoja 1: Instrucciones ────────────────────────────────────────
        $hojaInstr = $spreadsheet->getActiveSheet();
        $hojaInstr->setTitle('Instrucciones');
        $hojaInstr->getColumnDimensionByColumn(1)->setWidth(110);

        $lineas = [
            ['Cómo cargar unidades y tipos de medida', 'titulo'],
            ['Establecimiento de destino: ' . $labelEstablecimiento, 'aviso'],
            ['', 'normal'],
            ['El catálogo tiene dos niveles: el TIPO de medida (la magnitud: peso, volumen, longitud) y la UNIDAD concreta dentro de ese tipo (kilogramo, litro, metro). Por eso este archivo trae dos hojas.', 'normal'],
            ['', 'normal'],
            ['1) Hoja "Tipos_Medida": una fila por magnitud.', 'seccion'],
            ['      CODIGO_TIPO: código corto y sin espacios (PESO, VOL, LONG). Es el que se escribe luego en la hoja Unidades.', 'normal'],
            ['      NOMBRE_TIPO: nombre visible (PESO, VOLUMEN, LONGITUD).', 'normal'],
            ['', 'normal'],
            ['2) Hoja "Unidades": una fila por unidad.', 'seccion'],
            ['      CODIGO_TIPO: el código del tipo al que pertenece. Puede ser un tipo creado en este mismo archivo o uno que ya exista.', 'normal'],
            ['      CODIGO_UNIDAD: código corto (KG, LB, LT). No puede repetirse en toda la empresa, ni siquiera en tipos distintos: al importar productos la unidad se busca solo por este código.', 'normal'],
            ['      NOMBRE_UNIDAD: nombre visible (KILOGRAMO).', 'normal'],
            ['      ABREVIATURA: lo que se muestra junto a la cantidad (kg). Obligatoria.', 'normal'],
            ['      FACTOR_BASE: cuántas unidades base equivale 1 de esta unidad. Si la base del tipo es el kilogramo, la libra lleva 0.453592 porque 1 lb = 0.453592 kg. Si se deja vacío se asume 1.', 'normal'],
            ['      ES_BASE: escriba SI en la unidad de referencia del tipo (kg para peso, litro para volumen, metro para longitud). Solo una por tipo, y su factor siempre es 1. En las demás deje NO o vacío.', 'normal'],
            ['', 'normal'],
            ['Reglas importantes', 'seccion'],
            ['      Puede llenar solo una hoja o las dos. Los tipos se procesan primero, así que una unidad puede apuntar a un tipo creado en este mismo archivo.', 'normal'],
            ['      Si el código ya existe, el registro se ACTUALIZA con los datos del archivo; no se duplica.', 'normal'],
            ['      Si una fila tiene un error, se cancela toda la importación y no se guarda nada. El mensaje indica la hoja y la fila.', 'normal'],
            ['      No cambie los nombres de las hojas ni los títulos de las columnas.', 'normal'],
            ['      Este archivo solo puede importarse en el establecimiento indicado arriba.', 'normal'],
            ['', 'normal'],
            ['Atajos', 'seccion'],
            ['      Hoja "Catalogo_Sugerido": catálogo completo listo para copiar y pegar en las hojas Tipos_Medida y Unidades.', 'normal'],
            ['      Hoja "Ya_Registrado": lo que esta empresa ya tiene, para saber qué códigos están ocupados.', 'normal'],
        ];

        $filaInstr = 1;
        foreach ($lineas as [$texto, $estilo]) {
            $hojaInstr->setCellValueExplicit([1, $filaInstr], $texto, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $celda = $hojaInstr->getStyle([1, $filaInstr]);

            if ($estilo === 'titulo') {
                $celda->getFont()->setBold(true)->setSize(14);
                $celda->getFont()->getColor()->setARGB('FF1F4E79');
            } elseif ($estilo === 'aviso') {
                $celda->getFont()->setBold(true)->getColor()->setARGB('FF7B0000');
                $celda->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFFFF2CC');
            } elseif ($estilo === 'seccion') {
                $celda->getFont()->setBold(true)->getColor()->setARGB('FF1F4E79');
            }

            $celda->getAlignment()->setWrapText(true)->setVertical(
                \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP
            );
            $filaInstr++;
        }

        // ── Hojas de datos ───────────────────────────────────────────────
        $crearHojaDatos = function (string $titulo, array $headers, string $colorArgb) use ($spreadsheet) {
            $hoja = $spreadsheet->createSheet();
            $hoja->setTitle($titulo);

            foreach ($headers as $i => $header) {
                $col = $i + 1;
                $hoja->setCellValueExplicit([$col, 1], $header, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $hoja->getColumnDimensionByColumn($col)->setWidth(24);
                $hoja->getStyle([$col, 1])->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
                $hoja->getStyle([$col, 1])->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB($colorArgb);

                // Texto en las filas de datos: evita que Excel altere códigos como "M3"
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                $hoja->getStyle("{$colLetter}2:{$colLetter}1001")->getNumberFormat()
                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
            }

            return $hoja;
        };

        $crearHojaDatos('Tipos_Medida', ['CODIGO_TIPO', 'NOMBRE_TIPO'], 'FF7030A0');
        $crearHojaDatos(
            'Unidades',
            ['CODIGO_TIPO', 'CODIGO_UNIDAD', 'NOMBRE_UNIDAD', 'ABREVIATURA', 'FACTOR_BASE', 'ES_BASE (SI / NO)'],
            'FFED7D31'
        );

        // ── Hoja: Catalogo_Sugerido ──────────────────────────────────────
        $hojaCat = $spreadsheet->createSheet();
        $hojaCat->setTitle('Catalogo_Sugerido');
        foreach ([28, 28, 26, 16, 16, 18] as $i => $ancho) {
            $hojaCat->getColumnDimensionByColumn($i + 1)->setWidth($ancho);
        }
        $hojaCat->getStyle('A1:F400')->getNumberFormat()
            ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);

        $catalogo = \App\Helpers\CatalogoMedidas::getCatalogo();
        $fila = 1;

        $escribirTitulo = function (string $texto) use ($hojaCat, &$fila): void {
            $hojaCat->setCellValueExplicit([1, $fila], $texto, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $hojaCat->mergeCells('A' . $fila . ':F' . $fila);
            $hojaCat->getStyle('A' . $fila)->getFont()->setBold(true)->getColor()->setARGB('FF1F4E79');
            $fila++;
        };

        $escribirCabecera = function (array $headers) use ($hojaCat, &$fila): void {
            foreach ($headers as $i => $header) {
                $hojaCat->setCellValueExplicit([$i + 1, $fila], $header, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $hojaCat->getStyle([$i + 1, $fila])->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
                $hojaCat->getStyle([$i + 1, $fila])->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FF548235');
            }
            $fila++;
        };

        $escribirFila = function (array $valores) use ($hojaCat, &$fila): void {
            foreach ($valores as $i => $valor) {
                $hojaCat->setCellValueExplicit([$i + 1, $fila], (string) $valor, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            $fila++;
        };

        $escribirTitulo('TIPOS DE MEDIDA — copie estas filas en la hoja "Tipos_Medida"');
        $escribirCabecera(['CODIGO_TIPO', 'NOMBRE_TIPO']);
        foreach ($catalogo as $tipo) {
            $escribirFila([$tipo['codigo'], $tipo['nombre']]);
        }

        $fila++;
        $escribirTitulo('UNIDADES — copie estas filas en la hoja "Unidades"');
        $escribirCabecera(['CODIGO_TIPO', 'CODIGO_UNIDAD', 'NOMBRE_UNIDAD', 'ABREVIATURA', 'FACTOR_BASE', 'ES_BASE (SI / NO)']);
        foreach ($catalogo as $tipo) {
            foreach ($tipo['unidades'] as $unidad) {
                $escribirFila([
                    $tipo['codigo'],
                    $unidad['codigo'],
                    $unidad['nombre'],
                    $unidad['abreviatura'],
                    rtrim(rtrim(number_format((float) $unidad['factor_base'], 6, '.', ''), '0'), '.'),
                    $unidad['es_base'] ? 'SI' : 'NO',
                ]);
            }
        }

        $fila++;
        $hojaCat->setCellValueExplicit(
            [1, $fila],
            'Las unidades de EMPAQUE son presentaciones comerciales y van con factor 1: una caja no equivale a un saco, '
            . 'no se convierten entre sí. Sirven para indicar cómo se vende el producto.',
            \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
        );
        $hojaCat->mergeCells('A' . $fila . ':F' . $fila);
        $hojaCat->getStyle('A' . $fila)->getFont()->setItalic(true)->getColor()->setARGB('FF7B0000');
        $hojaCat->getStyle('A' . $fila)->getAlignment()->setWrapText(true);

        // ── Hoja: Ya_Registrado ──────────────────────────────────────────
        $hojaActual = $spreadsheet->createSheet();
        $hojaActual->setTitle('Ya_Registrado');
        foreach ([28, 28, 26, 16, 16, 18] as $i => $ancho) {
            $hojaActual->getColumnDimensionByColumn($i + 1)->setWidth($ancho);
        }

        $stTipos = $db->prepare(
            "SELECT codigo, nombre FROM tipo_medida
              WHERE id_empresa = ? AND eliminado = false
              ORDER BY nombre ASC"
        );
        $stTipos->execute([$idEmpresaPlantilla]);
        $tiposActuales = $stTipos->fetchAll(PDO::FETCH_ASSOC);

        $stUnidades = $db->prepare(
            "SELECT tm.codigo AS codigo_tipo, um.codigo, um.nombre, um.abreviatura, um.factor_base, um.es_base
               FROM unidades_medida um
               LEFT JOIN tipo_medida tm ON tm.id = um.id_tipo
              WHERE um.id_empresa = ? AND um.eliminado = false
              ORDER BY tm.nombre ASC, um.codigo ASC"
        );
        $stUnidades->execute([$idEmpresaPlantilla]);
        $unidadesActuales = $stUnidades->fetchAll(PDO::FETCH_ASSOC);

        $filaAct = 1;
        $escribirEnActual = function (array $valores, bool $negrita = false, ?string $fondo = null) use ($hojaActual, &$filaAct): void {
            foreach ($valores as $i => $valor) {
                $hojaActual->setCellValueExplicit([$i + 1, $filaAct], (string) $valor, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                if ($negrita) {
                    $hojaActual->getStyle([$i + 1, $filaAct])->getFont()->setBold(true);
                }
                if ($fondo !== null) {
                    $hojaActual->getStyle([$i + 1, $filaAct])->getFont()->getColor()->setARGB('FFFFFFFF');
                    $hojaActual->getStyle([$i + 1, $filaAct])->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setARGB($fondo);
                }
            }
            $filaAct++;
        };

        $escribirEnActual(['TIPOS DE MEDIDA REGISTRADOS'], true);
        $escribirEnActual(['CODIGO_TIPO', 'NOMBRE_TIPO'], true, 'FF7030A0');
        if (empty($tiposActuales)) {
            $escribirEnActual(['(ninguno)', '']);
        } else {
            foreach ($tiposActuales as $t) {
                $escribirEnActual([$t['codigo'], $t['nombre']]);
            }
        }

        $filaAct++;
        $escribirEnActual(['UNIDADES REGISTRADAS'], true);
        $escribirEnActual(['CODIGO_TIPO', 'CODIGO_UNIDAD', 'NOMBRE_UNIDAD', 'ABREVIATURA', 'FACTOR_BASE', 'ES_BASE'], true, 'FFED7D31');
        if (empty($unidadesActuales)) {
            $escribirEnActual(['(ninguna)', '', '', '', '', '']);
        } else {
            foreach ($unidadesActuales as $u) {
                $escribirEnActual([
                    $u['codigo_tipo'] ?? '',
                    $u['codigo'],
                    $u['nombre'],
                    $u['abreviatura'] ?? '',
                    rtrim(rtrim((string) $u['factor_base'], '0'), '.'),
                    \App\Helpers\Booleano::es($u['es_base']) ? 'SI' : 'NO',
                ]);
            }
        }

        // ── Hoja oculta _Config: valida el establecimiento al importar ───
        $sheetConfig = $spreadsheet->createSheet();
        $sheetConfig->setTitle('_Config');
        $sheetConfig->setCellValueExplicit([1, 1], 'id_empresa', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheetConfig->setCellValueExplicit([2, 1], (string) $idEmpresaPlantilla, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheetConfig->setCellValueExplicit([1, 2], 'establecimiento', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheetConfig->setCellValueExplicit([2, 2], $labelEstablecimiento, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheetConfig->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_VERYHIDDEN);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
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
