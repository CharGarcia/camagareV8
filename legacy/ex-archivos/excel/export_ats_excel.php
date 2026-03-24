<?php
// ==== NO ESCRIBAS NADA (ni espacios) ANTES DE ESTE <?php ====
include("../conexiones/conectalogin.php");
$con = conenta_login();

// Para que nada contamine los headers de descarga:
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Por si hay algún echo accidental de librerías:
if (function_exists('ob_get_level') && ob_get_level() === 0) {
    ob_start();
}

/* ===================== RUTAS ===================== */
$BASE    = dirname(__DIR__);            // /var/www/html/sistema
$DIR_XML = $BASE . '/xml';              // carpeta de XML ATS

// PHPExcel (ajusta si tu lib está en otro lado)
require_once 'lib/PHPExcel/PHPExcel.php';

/* ===================== HELPERS ===================== */
function xml_mas_reciente($dir)
{
    if (!is_dir($dir)) return null;
    $files = glob(rtrim($dir, '/\\') . '/*.xml');
    if (!$files) return null;
    usort($files, function ($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    return $files[0];
}

// Normalizador robusto de números (maneja 1.234,56 y 123,45)
function toFloat($v)
{
    $v = trim((string)$v);
    if ($v === '') return 0.0;
    // Si hay coma y no hay punto -> "123,45" => "123.45"
    if (strpos($v, ',') !== false && strpos($v, '.') === false) {
        $v = str_replace(',', '.', $v);
    } else {
        // "1,234.56" o "1.234,56" -> quita separador de miles ','
        $v = str_replace(',', '', $v);
    }
    return (float)$v;
}

// Primer valor de un nombre (namespace-agnostic, case-sensitive por nombre)
function val_any($xml, $names)
{
    foreach ($names as $n) {
        $nodes = $xml->xpath('//*[local-name()="' . $n . '"]');
        if ($nodes && isset($nodes[0])) {
            $t = trim((string)$nodes[0]);
            if ($t !== '') return $t;
        }
    }
    return '';
}

// Niños por nombre dentro de un nodo detalle
function child_val($node, $name)
{
    $n = $node->xpath('./*[local-name()="' . $name . '"]');
    return ($n && isset($n[0])) ? trim((string)$n[0]) : '';
}

// Lista de nodos detalle (namespace-agnóstico)
function detalles($xml, $grupoLocal, $detalleLocal)
{
    $nodes = $xml->xpath('//*[local-name()="' . $grupoLocal . '"]//*[local-name()="' . $detalleLocal . '"]');
    return $nodes ?: array();
}

// Padding seguro (solo si longitud < objetivo; NO trunca si es mayor)
function pad_left_digits($value, $length)
{
    $v = preg_replace('/\D+/', '', (string)$value); // deja solo dígitos
    if ($v === '') return str_pad('', $length, '0', STR_PAD_LEFT);
    if (strlen($v) >= $length) return $v; // no truncamos
    return str_pad($v, $length, '0', STR_PAD_LEFT);
}

/* ===================== CARGA XML ===================== */
$xmlPath = xml_mas_reciente($DIR_XML);
if (!$xmlPath || !file_exists($xmlPath)) {
    http_response_code(500);
    exit; // No hay XML: revisa carpeta sistema/xml
}
libxml_use_internal_errors(true);
$xml = simplexml_load_file($xmlPath);
if ($xml === false) {
    http_response_code(500);
    exit; // XML inválido
}

// (opcional) RUC desde sesión
@session_start();
$ruc_empresa = isset($_SESSION['ruc_empresa']) ? $_SESSION['ruc_empresa'] : '';

/* ============ OBTENER CAMPOS CABECERA (robusto) ============ */
$tipoID   = val_any($xml, array('TipoIDInformante', 'tipoIDInformante', 'tipoIdInformante'));
$idInf    = val_any($xml, array('IdInformante', 'idInformante'));
$razon    = val_any($xml, array('razonSocial', 'RazonSocial'));
$anio     = val_any($xml, array('Anio', 'anio', 'AnioFiscal', 'anioFiscal'));
$mes      = val_any($xml, array('Mes', 'mes'));

// Detalles por grupo
$comprasNodes     = detalles($xml, 'compras',     'detalleCompras');
$ventasNodes      = detalles($xml, 'ventas',      'detalleVentas');
// Retenciones AIR (cada <detalleAir> dentro de <air>)
$retencionesNodes = detalles($xml, 'air', 'detalleAir');
$anuladosNodes    = detalles($xml, 'anulados',    'detalleAnulados');

/* ===================== CONSTRUIR EXCEL ===================== */
$obj = new PHPExcel();
$obj->getProperties()
    ->setCreator('ATS Export')
    ->setTitle('ATS ' . $anio . $mes)
    ->setSubject('Resumen ATS');

// Estilos
function header_style($sh, $range)
{
    $st = $sh->getStyle($range);
    $st->getFont()->setBold(true);
    $st->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
    $st->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
    $st->getBorders()->getAllBorders()->setBorderStyle(PHPExcel_Style_Border::BORDER_THIN);
}
function thins($sh, $range)
{
    $sh->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(PHPExcel_Style_Border::BORDER_THIN);
}
function money_fmt($sh, $range)
{
    $sh->getStyle($range)->getNumberFormat()->setFormatCode('#,##0.00');
}
function set_row($sh, $row, $arr)
{
    foreach ($arr as $col => $val) {
        $sh->setCellValue($col . $row, $val);
    }
}

/* -------- Hoja 1: Resumen (2 columnas hacia abajo) -------- */
$sh = $obj->setActiveSheetIndex(0);
$sh->setTitle('Resumen');

// Encabezado
$sh->setCellValue('A1', 'Campo');
$sh->setCellValue('B1', 'Valor');
header_style($sh, 'A1:B1');
$sh->freezePaneByColumnAndRow(0, 2);

// Columna B como TEXTO (por seguridad)
$sh->getStyle('B:B')->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_TEXT);

// Filas
$r = 2;
$rows = array(
    array('Archivo XML', basename($xmlPath)),
    array('RUC empresa', $ruc_empresa),
    array('Razón Social', $razon),
    array('Año', $anio),
    array('Mes', $mes),
    array('N° Compras', count($comprasNodes)),
    array('N° Ventas', count($ventasNodes)),
    array('N° Retenciones', count($retencionesNodes)),
    array('N° Anulados', count($anuladosNodes)),
);
foreach ($rows as $pair) {
    $sh->setCellValue('A' . $r, $pair[0]);
    if ($pair[0] === 'RUC empresa') {
        $sh->setCellValueExplicit('B' . $r, (string)$pair[1], PHPExcel_Cell_DataType::TYPE_STRING);
        $sh->getStyle('B' . $r)->getNumberFormat()->setFormatCode('@');
    } else {
        $sh->setCellValue('B' . $r, $pair[1]);
    }
    $r++;
}
thins($sh, 'A1:B' . ($r - 1));
$sh->getColumnDimension('A')->setWidth(28);
$sh->getColumnDimension('B')->setWidth(50);

/* -------- Hoja 2: Compras -------- */
$compras = $obj->createSheet(1);
$compras->setTitle('Compras');
$colsC = array(
    'A' => 'codSustento',
    'B' => 'tpIdProv',
    'C' => 'idProv',
    'D' => 'Proveedor',
    'E' => 'tipoComprobante',
    'F' => 'parteRel',
    'G' => 'fechaRegistro',
    'H' => 'establecimiento',
    'I' => 'puntoEmision',
    'J' => 'secuencial',
    'K' => 'fechaEmision',
    'L' => 'autorizacion',
    'M' => 'baseNoGraIva',
    'N' => 'baseImponible',
    'O' => 'baseImpGrav',
    'P' => 'baseImpExe',
    'Q' => 'montoIce',
    'R' => 'montoIva',
    'S' => 'valRetBien10',
    'T' => 'valRetServ20',
    'U' => 'valorRetBienes',
    'V' => 'valRetServ50',
    'W' => 'valorRetServicios',
    'X' => 'valRetServ100',
    'Y' => 'valorRetencionNc',
    'Z' => 'totbasesImpReemb'
);
set_row($compras, 1, $colsC);
header_style($compras, 'A1:Z1');
$compras->freezePaneByColumnAndRow(0, 2);
$compras->setAutoFilter('A1:Z1');

// Formato TEXTO en columnas clave
$compras->getStyle('A:A')->getNumberFormat()->setFormatCode('@');
$compras->getStyle('B:B')->getNumberFormat()->setFormatCode('@');
$compras->getStyle('C:C')->getNumberFormat()->setFormatCode('@'); // idProv
$compras->getStyle('D:D')->getNumberFormat()->setFormatCode('@');
$compras->getStyle('E:E')->getNumberFormat()->setFormatCode('@');
$compras->getStyle('F:F')->getNumberFormat()->setFormatCode('@');
$compras->getStyle('H:H')->getNumberFormat()->setFormatCode('@'); // estab
$compras->getStyle('I:I')->getNumberFormat()->setFormatCode('@'); // ptoEmi
$compras->getStyle('J:J')->getNumberFormat()->setFormatCode('@'); // secuencial
$compras->getStyle('L:L')->getNumberFormat()->setFormatCode('@'); // autorizacion

$r = 2;
// Inicializa totales para M..Z
$sumCols = range('M', 'Z');
$totC    = array_fill_keys($sumCols, 0.0);

foreach ($comprasNodes as $c) {
    // Valores originales y descripciones desde BD
    $codSustento = child_val($c, 'codSustento');
    $busca_sustento = mysqli_query($con, "SELECT nombre_sustento FROM sustento_tributario WHERE codigo_sustento = '" . $codSustento . "' ");
    $row_sustento = mysqli_fetch_array($busca_sustento);

    $tpIdProv = child_val($c, 'tpIdProv');
    $busca_tipo_prov = mysqli_query($con, "SELECT nombre FROM iden_vendedor WHERE codigo = '" . $tpIdProv . "' ");
    $row_tipo_prov = mysqli_fetch_array($busca_tipo_prov);

    $idProv = child_val($c, 'idProv');
    $busca_proveedor = mysqli_query($con, "SELECT razon_social FROM proveedores WHERE ruc_proveedor = '" . $idProv . "' and ruc_empresa= '" . $ruc_empresa . "'");
    $row_proveedor = mysqli_fetch_array($busca_proveedor);

    $tipoComprobante = child_val($c, 'tipoComprobante');
    $busca_comprobante = mysqli_query($con, "SELECT comprobante FROM comprobantes_autorizados WHERE codigo_comprobante = '" . $tipoComprobante . "' ");
    $row_comprobante = mysqli_fetch_array($busca_comprobante);

    $sustento_tributario = isset($row_sustento['nombre_sustento']) ? $row_sustento['nombre_sustento'] : '';
    $tipo_id_prov        = isset($row_tipo_prov['nombre']) ? $row_tipo_prov['nombre'] : '';
    $proveedor           = isset($row_proveedor['razon_social']) ? $row_proveedor['razon_social'] : '';
    $tipo_comprobante    = isset($row_comprobante['comprobante']) ? $row_comprobante['comprobante'] : '';

    $parteRel       = child_val($c, 'parteRel');
    $fechaRegistro  = child_val($c, 'fechaRegistro');
    $establecimiento = child_val($c, 'establecimiento');
    $puntoEmision   = child_val($c, 'puntoEmision');
    $secuencial     = child_val($c, 'secuencial');
    $fechaEmision   = child_val($c, 'fechaEmision');
    $autorizacion   = child_val($c, 'autorizacion');

    // Montos (M..Z)
    $baseNoGraIva   = child_val($c, 'baseNoGraIva');   // M
    $baseImponible  = child_val($c, 'baseImponible');  // N
    $baseImpGrav    = child_val($c, 'baseImpGrav');    // O
    $baseImpExe     = child_val($c, 'baseImpExe');     // P
    $montoIce       = child_val($c, 'montoIce');       // Q
    $montoIva       = child_val($c, 'montoIva');       // R
    $valRetBien10   = child_val($c, 'valRetBien10');   // S
    $valRetServ20   = child_val($c, 'valRetServ20');   // T
    $valorRetBienes = child_val($c, 'valorRetBienes'); // U
    $valRetServ50   = child_val($c, 'valRetServ50');   // V
    $valorRetServicios = child_val($c, 'valorRetServicios'); // W
    $valRetServ100  = child_val($c, 'valRetServ100');  // X
    $valorRetencionNc = child_val($c, 'valorRetencionNc'); // Y
    $totbasesImpReemb = child_val($c, 'totbasesImpReemb'); // Z

    // PAD y tipo TEXTO según requerimiento
    $idProv_txt = pad_left_digits($idProv, 13);  // si lo necesitas 9, cambia 13 -> 9
    $estab_txt  = pad_left_digits($establecimiento, 3);
    $ptoEmi_txt = pad_left_digits($puntoEmision, 3);
    $sec_txt    = pad_left_digits($secuencial, 9);

    // Escribir fila con tipos correctos
    $compras->setCellValueExplicit('A' . $r, $codSustento . '-' . $sustento_tributario, PHPExcel_Cell_DataType::TYPE_STRING);
    $compras->setCellValueExplicit('B' . $r, $tpIdProv . '-' . $tipo_id_prov,          PHPExcel_Cell_DataType::TYPE_STRING);
    $compras->setCellValueExplicit('C' . $r, $idProv_txt,                          PHPExcel_Cell_DataType::TYPE_STRING);
    $compras->setCellValueExplicit('D' . $r, $proveedor,                           PHPExcel_Cell_DataType::TYPE_STRING);
    $compras->setCellValueExplicit('E' . $r, $tipoComprobante . '-' . $tipo_comprobante, PHPExcel_Cell_DataType::TYPE_STRING);
    $compras->setCellValue('F' . $r, $parteRel);
    $compras->setCellValue('G' . $r, $fechaRegistro);
    $compras->setCellValueExplicit('H' . $r, $estab_txt, PHPExcel_Cell_DataType::TYPE_STRING);
    $compras->setCellValueExplicit('I' . $r, $ptoEmi_txt, PHPExcel_Cell_DataType::TYPE_STRING);
    $compras->setCellValueExplicit('J' . $r, $sec_txt,   PHPExcel_Cell_DataType::TYPE_STRING);
    $compras->setCellValue('K' . $r, $fechaEmision);
    $compras->setCellValueExplicit('L' . $r, $autorizacion, PHPExcel_Cell_DataType::TYPE_STRING);

    // Montos
    $compras->setCellValue('M' . $r, $baseNoGraIva);
    $compras->setCellValue('N' . $r, $baseImponible);
    $compras->setCellValue('O' . $r, $baseImpGrav);
    $compras->setCellValue('P' . $r, $baseImpExe);
    $compras->setCellValue('Q' . $r, $montoIce);
    $compras->setCellValue('R' . $r, $montoIva);
    $compras->setCellValue('S' . $r, $valRetBien10);
    $compras->setCellValue('T' . $r, $valRetServ20);
    $compras->setCellValue('U' . $r, $valorRetBienes);
    $compras->setCellValue('V' . $r, $valRetServ50);
    $compras->setCellValue('W' . $r, $valorRetServicios);
    $compras->setCellValue('X' . $r, $valRetServ100);
    $compras->setCellValue('Y' . $r, $valorRetencionNc);
    $compras->setCellValue('Z' . $r, $totbasesImpReemb);

    // Acumular totales M..Z con mapa explícito
    $sumMap = array(
        'M' => $baseNoGraIva,
        'N' => $baseImponible,
        'O' => $baseImpGrav,
        'P' => $baseImpExe,
        'Q' => $montoIce,
        'R' => $montoIva,
        'S' => $valRetBien10,
        'T' => $valRetServ20,
        'U' => $valorRetBienes,
        'V' => $valRetServ50,
        'W' => $valorRetServicios,
        'X' => $valRetServ100,
        'Y' => $valorRetencionNc,
        'Z' => $totbasesImpReemb
    );
    foreach ($sumMap as $col => $val) {
        $totC[$col] += toFloat($val);
    }

    $r++;
}
if ($r > 2) {
    // Etiqueta y totales
    $compras->setCellValue("L{$r}", 'Totales:');
    $compras->getStyle("L{$r}:Z{$r}")->getFont()->setBold(true);

    foreach ($sumCols as $col) {
        $compras->setCellValue($col . $r, $totC[$col]);
    }

    // Formato numérico y bordes hasta Z
    money_fmt($compras, "M2:Z{$r}");
    thins($compras, "A1:Z{$r}");
}
foreach (range('A', 'Z') as $cc) $compras->getColumnDimension($cc)->setAutoSize(true);

/* -------- Hoja 3: Ventas -------- */
$ventas = $obj->createSheet(2);
$ventas->setTitle('Ventas');
$colsV = array(
    'A' => 'idCliente',
    'B' => 'tipoIdCliente',
    'C' => 'parteRelVtas',
    'D' => 'tipoComprobante',
    'E' => 'numeroComprobantes',
    'F' => 'baseNoGraIva',
    'G' => 'baseImponible',
    'H' => 'baseImpGrav',
    'I' => 'montoIva',
    'J' => 'valorRetIva',
    'K' => 'valorRetRenta',
    'L' => 'estab',
    'M' => 'ptoEmi',
    'N' => 'secuencial',
    'O' => 'fechaEmision'
);
set_row($ventas, 1, $colsV);
header_style($ventas, 'A1:O1');
$ventas->freezePaneByColumnAndRow(0, 2);
$ventas->setAutoFilter('A1:O1');

$r = 2;
$totV = array('F' => 0, 'G' => 0, 'H' => 0, 'I' => 0, 'J' => 0, 'K' => 0);
foreach ($ventasNodes as $v) {
    $row = array(
        'A' => child_val($v, 'idCliente'),
        'B' => child_val($v, 'tipoIdCliente'),
        'C' => child_val($v, 'parteRelVtas'),
        'D' => child_val($v, 'tipoComprobante'),
        'E' => child_val($v, 'numeroComprobantes'),
        'F' => child_val($v, 'baseNoGraIva'),
        'G' => child_val($v, 'baseImponible'),
        'H' => child_val($v, 'baseImpGrav'),
        'I' => child_val($v, 'montoIva'),
        'J' => child_val($v, 'valorRetIva'),
        'K' => child_val($v, 'valorRetRenta'),
        'L' => child_val($v, 'estab'),
        'M' => child_val($v, 'ptoEmi'),
        'N' => child_val($v, 'secuencial'),
        'O' => child_val($v, 'fechaEmision'),
    );
    set_row($ventas, $r, $row);
    foreach ($totV as $k => $_) $totV[$k] += toFloat($row[$k]);
    $r++;
}
if ($r > 2) {
    set_row($ventas, $r, array('A' => 'Totales:'));
    $ventas->mergeCells("A{$r}:E{$r}");
    $ventas->getStyle("A{$r}:O{$r}")->getFont()->setBold(true);
    foreach (array('F', 'G', 'H', 'I', 'J', 'K') as $k) $ventas->setCellValue($k . $r, $totV[$k]);
    money_fmt($ventas, "F2:K{$r}");
    thins($ventas, "A1:O{$r}");
}
foreach (range('A', 'O') as $cc) $ventas->getColumnDimension($cc)->setAutoSize(true);

/* -------- Hoja 4: AIR (retenciones desde <air><detalleAir> + campos *Retencion1) -------- */
$ret = $obj->createSheet(3);
$ret->setTitle('Retenciones');
$colsR = array(
    'A' => 'codRetAir',
    'B' => 'baseImpAir',
    'C' => 'porcentajeAir',
    'D' => 'valRetAir',
    'E' => 'estabRetencion1',     // texto 3 dígitos (pad)
    'F' => 'ptoEmiRetencion1',    // texto 3 dígitos (pad)
    'G' => 'secRetencion1',       // texto 9 dígitos (pad)
    'H' => 'autRetencion1',       // texto
    'I' => 'fechaEmiRet1'
);
set_row($ret, 1, $colsR);
header_style($ret, 'A1:I1');
$ret->freezePaneByColumnAndRow(0, 2);
$ret->setAutoFilter('A1:I1');

// Fuerza TEXTO en columnas E, F, G, H (como pediste)
$ret->getStyle('E:E')->getNumberFormat()->setFormatCode('@');
$ret->getStyle('F:F')->getNumberFormat()->setFormatCode('@');
$ret->getStyle('G:G')->getNumberFormat()->setFormatCode('@');
$ret->getStyle('H:H')->getNumberFormat()->setFormatCode('@');

$r = 2;
// Sumamos baseImpAir y valRetAir
$totR = array('B' => 0.0, 'D' => 0.0);

foreach ($retencionesNodes as $rt) {
    // Campos propios del detalleAir
    $codRetAir      = child_val($rt, 'codRetAir');
    $baseImpAir     = child_val($rt, 'baseImpAir');
    $porcentajeAir  = child_val($rt, 'porcentajeAir');
    $valRetAir      = child_val($rt, 'valRetAir');

    // Subimos al detalle de compras que contiene este <air> para leer los *Retencion1
    $compraNodo = $rt->xpath('ancestor::*[local-name()="detalleCompras"][1]');
    $estabRet1 = $ptoEmiRet1 = $secRet1 = $autRet1 = $fechaEmiRet1 = '';

    if ($compraNodo && isset($compraNodo[0])) {
        $c = $compraNodo[0];
        $estabRet1    = child_val($c, 'estabRetencion1');
        $ptoEmiRet1   = child_val($c, 'ptoEmiRetencion1');
        $secRet1      = child_val($c, 'secRetencion1');
        $autRet1      = child_val($c, 'autRetencion1');
        $fechaEmiRet1 = child_val($c, 'fechaEmiRet1');
    }

    // PAD y tipo texto según reglas (3/3/9) y autRetención como texto completo
    $estabRet1_txt = pad_left_digits($estabRet1, 3);
    $ptoEmiRet1_txt = pad_left_digits($ptoEmiRet1, 3);
    $secRet1_txt   = pad_left_digits($secRet1, 9);

    // Escribir fila
    $ret->setCellValue('A' . $r, $codRetAir);
    $ret->setCellValue('B' . $r, $baseImpAir);
    $ret->setCellValue('C' . $r, $porcentajeAir);
    $ret->setCellValue('D' . $r, $valRetAir);

    $ret->setCellValueExplicit('E' . $r, $estabRet1_txt, PHPExcel_Cell_DataType::TYPE_STRING);
    $ret->setCellValueExplicit('F' . $r, $ptoEmiRet1_txt, PHPExcel_Cell_DataType::TYPE_STRING);
    $ret->setCellValueExplicit('G' . $r, $secRet1_txt,   PHPExcel_Cell_DataType::TYPE_STRING);
    $ret->setCellValueExplicit('H' . $r, (string)$autRet1, PHPExcel_Cell_DataType::TYPE_STRING);
    $ret->setCellValue('I' . $r, $fechaEmiRet1);

    // Acumular totales (maneja "1.234,56" y "123,45")
    $totR['B'] += (float)str_replace(',', '.', $baseImpAir);
    $totR['D'] += (float)str_replace(',', '.', $valRetAir);

    $r++;
}

if ($r > 2) {
    // Fila totales
    $ret->setCellValue("A{$r}", 'Totales:');
    $ret->getStyle("A{$r}:I{$r}")->getFont()->setBold(true);
    $ret->setCellValue("B{$r}", $totR['B']);
    $ret->setCellValue("D{$r}", $totR['D']);

    // Formatos y bordes
    money_fmt($ret, "B2:B{$r}");
    money_fmt($ret, "D2:D{$r}");
    thins($ret, "A1:I{$r}");
}

// Auto-size columnas
foreach (range('A', 'I') as $cc) {
    $ret->getColumnDimension($cc)->setAutoSize(true);
}


/* -------- Hoja 5: Anulados (si hay) -------- */
if (count($anuladosNodes) > 0) {
    $anu = $obj->createSheet(4);
    $anu->setTitle('Anulados');
    $colsA = array(
        'A' => 'tipoComprobante',
        'B' => 'estab',
        'C' => 'ptoEmi',
        'D' => 'secuencialInicio',
        'E' => 'secuencialFin',
        'F' => 'autorizacion',
        'G' => 'fechaAnulacion'
    );
    set_row($anu, 1, $colsA);
    header_style($anu, 'A1:G1');
    $anu->freezePaneByColumnAndRow(0, 2);
    $anu->setAutoFilter('A1:G1');

    $anu->getStyle('B:B')->getNumberFormat()->setFormatCode('@');
    $anu->getStyle('C:C')->getNumberFormat()->setFormatCode('@');

    $r = 2;
    foreach ($anuladosNodes as $a) {
        $estab  = pad_left_digits(child_val($a, 'estab'), 3);
        $ptoEmi = pad_left_digits(child_val($a, 'ptoEmi'), 3);
        $secIni = pad_left_digits(child_val($a, 'secuencialInicio'), 9);
        $secFin = pad_left_digits(child_val($a, 'secuencialFin'), 9);

        $anu->setCellValue('A' . $r, child_val($a, 'tipoComprobante'));
        $anu->setCellValueExplicit('B' . $r, $estab,  PHPExcel_Cell_DataType::TYPE_STRING);
        $anu->setCellValueExplicit('C' . $r, $ptoEmi, PHPExcel_Cell_DataType::TYPE_STRING);
        $anu->setCellValueExplicit('D' . $r, $secIni, PHPExcel_Cell_DataType::TYPE_STRING);
        $anu->setCellValueExplicit('E' . $r, $secFin, PHPExcel_Cell_DataType::TYPE_STRING);
        $anu->setCellValue('F' . $r, child_val($a, 'autorizacion'));
        $anu->setCellValue('G' . $r, child_val($a, 'fechaAnulacion'));
        $r++;
    }
    thins($anu, "A1:G" . ($r - 1));
    foreach (range('A', 'G') as $cc) $anu->getColumnDimension($cc)->setAutoSize(true);
}

/* ===================== DESCARGA ===================== */
if (function_exists('ob_get_length') && ob_get_length()) {
    ob_end_clean();
}

$anioOut = ($anio !== '') ? $anio : date('Y');
$mesOut  = ($mes  !== '') ? $mes  : date('m');
$filename = "ATS_{$anioOut}-{$mesOut}_{$ruc_empresa}.xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Transfer-Encoding: binary');
header('Cache-Control: max-age=0');
header('Pragma: public');
header('Expires: 0');

PHPExcel_IOFactory::createWriter($obj, 'Excel2007')->save('php://output');
exit;
