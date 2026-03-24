<?php
include("../conexiones/conectalogin.php");
include("../helpers/helpers.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];

$sucursal = $_POST['sucursal'];
$mes = $_POST['mes'];
$anio = $_POST['anio_periodo'];
$ejercicio_fiscal = $mes . "/" . $anio;
date_default_timezone_set('America/Guayaquil');
if (PHP_SAPI == 'cli')
	die('Este archivo solo se puede ver desde un navegador web');

/** Se agrega la libreria PHPExcel */
require_once 'lib/PHPExcel/PHPExcel.php';
// Se crea el objeto PHPExcel
$objPHPExcel = new PHPExcel();
$objPHPExcel->getProperties()->setCreator("CaMaGaRe") //Autor
	->setLastModifiedBy("CaMaGaRe") //Ultimo usuario que lo modificó
	->setTitle("Reporte Excel")
	->setSubject("Reporte Excel")
	->setDescription("Reporte General")
	->setKeywords("Reporte General")
	->setCategory("Reporte General");

$tituloPeriodo = "Mes: " . $mes . " Año: " . $anio;
//para sacar el nombre de la empresa
$sql_empresa = mysqli_query($con, "SELECT * FROM empresas where ruc = '" . $ruc_empresa . "' ");
$empresa_info = mysqli_fetch_array($sql_empresa);
$tituloEmpresa = $empresa_info['nombre_comercial'];

//para todas las sucursales
if (empty($sucursal)) { //AQUI SACA EL REPORTE DE TODAS LAS SUCURSALES
	$ruc_encabezado_factura = " mid(enc_fac.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "'";
	$ruc_comparativo_factura = " mid(enc_fac.ruc_empresa,1,10) = mid(cue_fac.ruc_empresa,1,10)";
	$ruc_agrupado_factura = " mid(cue_fac.ruc_empresa,1,10)";
	$ruc_cuerpo_factura = " and mid(cue_fac.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "'";

	$ruc_encabezado_nc = " mid(enc_nc.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "'";
	$ruc_comparativo_nc = " mid(enc_nc.ruc_empresa,1,10) = mid(cue_nc.ruc_empresa,1,10)";
	$ruc_agrupado_nc = " mid(cue_nc.ruc_empresa,1,10)";
	$ruc_cuerpo_nc = " and mid(cue_nc.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "'";
	$ruc_encabezado_compra = " mid(enc_com.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "'";
	$ruc_cuerpo_retencion_compra = " mid(cue_ret.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "'";
	$ruc_comparativo_retencion_compra = " mid(enc_ret.ruc_empresa,1,10) = mid(cue_ret.ruc_empresa,1,10)";
} else { //SACA EL REPORTE SOLO DE UNA EMPRESA SELECCIONADA
	$ruc_encabezado_factura = " enc_fac.ruc_empresa=" . $sucursal;
	$ruc_comparativo_factura = " enc_fac.ruc_empresa = cue_fac.ruc_empresa";
	$ruc_agrupado_factura = " cue_fac.ruc_empresa";
	$ruc_cuerpo_factura = " and cue_fac.ruc_empresa=" . $sucursal;
	$ruc_encabezado_nc = " enc_nc.ruc_empresa=" . $sucursal;
	$ruc_comparativo_nc = " enc_nc.ruc_empresa = cue_nc.ruc_empresa";
	$ruc_agrupado_nc = " cue_nc.ruc_empresa";
	$ruc_cuerpo_nc = " and cue_nc.ruc_empresa=" . $sucursal;
	$ruc_encabezado_compra = " enc_com.ruc_empresa=" . $sucursal;
	$ruc_cuerpo_retencion_compra = " cue_ret.ruc_empresa=" . $sucursal;
	$ruc_comparativo_retencion_compra = " enc_ret.ruc_empresa = cue_ret.ruc_empresa";
}

//consultas
$resultado_facturas_ventas = mysqli_query($con, "SELECT enc_fac.fecha_factura as fecha_factura,
	enc_fac.serie_factura as serie_factura, enc_fac.secuencial_factura as secuencial_factura, cli.nombre as nombre_cliente,
	cli.ruc as ruc_cliente, 
	ROUND(SUM(CASE WHEN cue_fac.tarifa_iva = 0 THEN cue_fac.subtotal_factura - cue_fac.descuento ELSE 0 END), 2) AS base_cero,
    ROUND(SUM(CASE WHEN cue_fac.tarifa_iva = 2 THEN cue_fac.subtotal_factura - cue_fac.descuento ELSE 0 END), 2) AS base_doce,
    ROUND(SUM(CASE WHEN cue_fac.tarifa_iva = 6 THEN cue_fac.subtotal_factura - cue_fac.descuento ELSE 0 END), 2) AS base_noimp,
    ROUND(SUM(CASE WHEN cue_fac.tarifa_iva = 7 THEN cue_fac.subtotal_factura - cue_fac.descuento ELSE 0 END), 2) AS base_exento,
    ROUND(SUM(CASE WHEN cue_fac.tarifa_iva = 3 THEN cue_fac.subtotal_factura - cue_fac.descuento ELSE 0 END), 2) AS base_catorce,
    ROUND(SUM(CASE WHEN cue_fac.tarifa_iva = 4 THEN cue_fac.subtotal_factura - cue_fac.descuento ELSE 0 END), 2) AS base_quince,
    ROUND(SUM(CASE WHEN cue_fac.tarifa_iva = 5 THEN cue_fac.subtotal_factura - cue_fac.descuento ELSE 0 END), 2) AS base_cinco,
    ROUND(SUM(CASE WHEN cue_fac.tarifa_iva = 10 THEN cue_fac.subtotal_factura - cue_fac.descuento ELSE 0 END), 2) AS base_trece,
    ROUND(SUM(CASE WHEN cue_fac.tarifa_iva = 8 THEN cue_fac.subtotal_factura - cue_fac.descuento ELSE 0 END), 2) AS base_diferenciado,
    ROUND(SUM(cue_fac.descuento), 2) AS descuento,	enc_fac.propina as propina, enc_fac.tasa_turistica as tasa_turistica,
	enc_fac.total_factura as total_factura, usu.nombre as nombre_usuario, emp.nombre_comercial as nombre_sucursal 
	FROM encabezado_factura as enc_fac INNER JOIN cuerpo_factura as cue_fac   
	ON cue_fac.serie_factura=enc_fac.serie_factura and cue_fac.secuencial_factura=enc_fac.secuencial_factura and $ruc_comparativo_factura 
	LEFT JOIN clientes as cli ON cli.id=enc_fac.id_cliente LEFT JOIN usuarios as usu ON usu.id=enc_fac.id_usuario 
	INNER JOIN empresas as emp ON emp.ruc = enc_fac.ruc_empresa WHERE $ruc_encabezado_factura $ruc_cuerpo_factura 
	and month(enc_fac.fecha_factura) ='" . $mes . "' and year(enc_fac.fecha_factura) = '" . $anio . "' group by cue_fac.serie_factura, cue_fac.secuencial_factura, $ruc_agrupado_factura order by enc_fac.fecha_factura asc");

$resultado_nc_facturas_ventas = mysqli_query($con, "SELECT emp.nombre_comercial as nombre_sucursal, enc_nc.fecha_nc as fecha_nc, cli.nombre as nombre_cliente, cli.ruc as ruc_cliente,
	enc_nc.factura_modificada as factura_modificada, enc_nc.total_nc as total_nc, enc_nc.serie_nc as serie_nc, enc_nc.secuencial_nc as secuencial_nc,
	ROUND(SUM(CASE WHEN cue_nc.tarifa_iva = 0 THEN cue_nc.subtotal_nc - cue_nc.descuento ELSE 0 END), 2) AS base_cero,
    ROUND(SUM(CASE WHEN cue_nc.tarifa_iva = 2 THEN cue_nc.subtotal_nc - cue_nc.descuento ELSE 0 END), 2) AS base_doce,
    ROUND(SUM(CASE WHEN cue_nc.tarifa_iva = 6 THEN cue_nc.subtotal_nc - cue_nc.descuento ELSE 0 END), 2) AS base_noimp,
    ROUND(SUM(CASE WHEN cue_nc.tarifa_iva = 7 THEN cue_nc.subtotal_nc - cue_nc.descuento ELSE 0 END), 2) AS base_exento,
    ROUND(SUM(CASE WHEN cue_nc.tarifa_iva = 3 THEN cue_nc.subtotal_nc - cue_nc.descuento ELSE 0 END), 2) AS base_catorce,
    ROUND(SUM(CASE WHEN cue_nc.tarifa_iva = 4 THEN cue_nc.subtotal_nc - cue_nc.descuento ELSE 0 END), 2) AS base_quince,
    ROUND(SUM(CASE WHEN cue_nc.tarifa_iva = 5 THEN cue_nc.subtotal_nc - cue_nc.descuento ELSE 0 END), 2) AS base_cinco,
    ROUND(SUM(CASE WHEN cue_nc.tarifa_iva = 10 THEN cue_nc.subtotal_nc - cue_nc.descuento ELSE 0 END), 2) AS base_trece,
    ROUND(SUM(CASE WHEN cue_nc.tarifa_iva = 8 THEN cue_nc.subtotal_nc - cue_nc.descuento ELSE 0 END), 2) AS base_diferenciado,
    ROUND(SUM(cue_nc.descuento), 2) AS descuento
	FROM encabezado_nc as enc_nc INNER JOIN cuerpo_nc as cue_nc ON cue_nc.serie_nc=enc_nc.serie_nc and cue_nc.secuencial_nc=enc_nc.secuencial_nc and $ruc_comparativo_nc
	INNER JOIN clientes as cli ON cli.id=enc_nc.id_cliente LEFT JOIN usuarios as usu ON usu.id=enc_nc.id_usuario 
	INNER JOIN empresas as emp ON emp.ruc = enc_nc.ruc_empresa WHERE $ruc_encabezado_nc $ruc_cuerpo_nc  
	and cue_nc.ruc_empresa='" . $ruc_empresa . "' and month(enc_nc.fecha_nc) ='" . $mes . "' and year(enc_nc.fecha_nc) = '" . $anio . "' group by cue_nc.serie_nc, cue_nc.secuencial_nc, $ruc_agrupado_nc order by enc_nc.fecha_nc asc");


//para las compras
$resultado_facturas_compras = mysqli_query($con, "SELECT 
    enc_com.fecha_compra AS fecha_compra,
    pro.razon_social AS nombre_proveedor,
    pro.ruc_proveedor AS ruc_proveedor,
    enc_com.tipo_comprobante AS tipo_comprobante,
    enc_com.total_compra AS total_compra,
    enc_com.numero_documento AS numero_documento,
    enc_com.propina AS propina,
    enc_com.otros_val AS otros_val,
    emp.nombre_comercial AS nombre_sucursal,
    com_aut.comprobante AS comprobante,
    ROUND(cue_com.descuento,2) AS descuento,
	ROUND(SUM(CASE WHEN cue_com.impuesto = 2 AND cue_com.det_impuesto = 0 THEN cue_com.subtotal ELSE 0 END),2) AS base_cero,
	ROUND(SUM(CASE WHEN cue_com.impuesto = 2 AND cue_com.det_impuesto = 2 THEN cue_com.subtotal ELSE 0 END),2) AS base_doce,
    ROUND(SUM(CASE WHEN cue_com.impuesto = 2 AND cue_com.det_impuesto = 6 THEN cue_com.subtotal ELSE 0 END),2) AS base_noimp,
    ROUND(SUM(CASE WHEN cue_com.impuesto = 2 AND cue_com.det_impuesto = 7 THEN cue_com.subtotal ELSE 0 END),2) AS base_exento,
    ROUND(SUM(CASE WHEN cue_com.impuesto = 2 AND cue_com.det_impuesto = 3 THEN cue_com.subtotal ELSE 0 END),2) AS base_catorce,
    ROUND(SUM(CASE WHEN cue_com.impuesto = 2 AND cue_com.det_impuesto = 4 THEN cue_com.subtotal ELSE 0 END),2) AS base_quince,
    ROUND(SUM(CASE WHEN cue_com.impuesto = 2 AND cue_com.det_impuesto = 5 THEN cue_com.subtotal ELSE 0 END),2) AS base_cinco,
    ROUND(SUM(CASE WHEN cue_com.impuesto = 2 AND cue_com.det_impuesto = 10 THEN cue_com.subtotal ELSE 0 END),2) AS base_trece,
    ROUND(SUM(CASE WHEN cue_com.impuesto = 2 AND cue_com.det_impuesto = 8 THEN cue_com.subtotal ELSE 0 END),2) AS base_diferenciado
FROM 
    encabezado_compra AS enc_com
INNER JOIN 
    cuerpo_compra AS cue_com ON cue_com.codigo_documento = enc_com.codigo_documento
INNER JOIN 
    proveedores AS pro ON pro.id_proveedor = enc_com.id_proveedor
INNER JOIN 
    empresas AS emp ON emp.ruc = enc_com.ruc_empresa
INNER JOIN 
    comprobantes_autorizados AS com_aut ON com_aut.id_comprobante = enc_com.id_comprobante
WHERE 
    $ruc_encabezado_compra
    AND enc_com.id_comprobante != 4
    AND MONTH(enc_com.fecha_compra) = '" . $mes . "'
    AND YEAR(enc_com.fecha_compra) = '" . $anio . "'
GROUP BY 
    cue_com.codigo_documento
ORDER BY 
    enc_com.fecha_compra ASC ");

//para las nc en compras
$resultado_nc_compras = mysqli_query($con, "SELECT enc_com.fecha_compra as fecha_compra, pro.razon_social as nombre_proveedor, pro.ruc_proveedor as ruc_proveedor, enc_com.tipo_comprobante as tipo_comprobante,
enc_com.total_compra as total_compra, enc_com.numero_documento as numero_documento, enc_com.propina as propina, enc_com.otros_val as otros_val, emp.nombre_comercial as nombre_sucursal, com_aut.comprobante as comprobante, enc_com.factura_aplica_nc_nd as documento_afectado,
 ROUND(SUM(CASE WHEN cue_com.impuesto = 2 AND cue_com.det_impuesto = 0 THEN cue_com.subtotal ELSE 0 END), 2) AS base_cero,
    ROUND(SUM(CASE WHEN cue_com.impuesto = 2 AND cue_com.det_impuesto = 2 THEN cue_com.subtotal ELSE 0 END), 2) AS base_doce,
    ROUND(SUM(CASE WHEN cue_com.impuesto = 2 AND cue_com.det_impuesto = 6 THEN cue_com.subtotal ELSE 0 END), 2) AS base_noimp,
    ROUND(SUM(CASE WHEN cue_com.impuesto = 2 AND cue_com.det_impuesto = 7 THEN cue_com.subtotal ELSE 0 END), 2) AS base_exento,
    ROUND(SUM(CASE WHEN cue_com.impuesto = 2 AND cue_com.det_impuesto = 3 THEN cue_com.subtotal ELSE 0 END), 2) AS base_catorce,
    ROUND(SUM(CASE WHEN cue_com.impuesto = 2 AND cue_com.det_impuesto = 4 THEN cue_com.subtotal ELSE 0 END), 2) AS base_quince,
    ROUND(SUM(CASE WHEN cue_com.impuesto = 2 AND cue_com.det_impuesto = 5 THEN cue_com.subtotal ELSE 0 END), 2) AS base_cinco,
    ROUND(SUM(CASE WHEN cue_com.impuesto = 2 AND cue_com.det_impuesto = 10 THEN cue_com.subtotal ELSE 0 END), 2) AS base_trece,
    ROUND(SUM(CASE WHEN cue_com.impuesto = 2 AND cue_com.det_impuesto = 8 THEN cue_com.subtotal ELSE 0 END), 2) AS base_diferenciado,
    ROUND(SUM(cue_com.descuento), 2) AS descuento
FROM encabezado_compra as enc_com INNER JOIN cuerpo_compra as cue_com ON cue_com.codigo_documento=enc_com.codigo_documento INNER JOIN proveedores as pro ON pro.id_proveedor=enc_com.id_proveedor 
INNER JOIN empresas as emp ON emp.ruc = enc_com.ruc_empresa INNER JOIN comprobantes_autorizados as com_aut ON com_aut.id_comprobante=enc_com.id_comprobante WHERE $ruc_encabezado_compra and enc_com.id_comprobante =4 and month(enc_com.fecha_compra) ='" . $mes . "' and year(enc_com.fecha_compra) = '" . $anio . "' group by cue_com.codigo_documento order by enc_com.fecha_compra asc");

//para las retenciones en compras
$resultado_retenciones_compra =  mysqli_query($con, "SELECT emp.nombre_comercial as nombre_sucursal, enc_ret.fecha_emision as fecha_retencion,
pro.razon_social as nombre_proveedor, pro.ruc_proveedor as ruc_proveedor, enc_ret.serie_retencion as serie_retencion, enc_ret.secuencial_retencion as secuencial_retencion, 
com_aut.comprobante as documento_retenido, enc_ret.numero_comprobante as numero_documento_retenido, cue_ret.impuesto as impuesto, cue_ret.base_imponible as base_imponible, cue_ret.codigo_impuesto as codigo_impuesto, cue_ret.porcentaje_retencion as porcentaje, cue_ret.valor_retenido as valor_retenido, cue_ret.nombre_retencion as concepto 
FROM cuerpo_retencion as cue_ret INNER JOIN encabezado_retencion as enc_ret ON enc_ret.serie_retencion=cue_ret.serie_retencion and enc_ret.secuencial_retencion=cue_ret.secuencial_retencion and $ruc_comparativo_retencion_compra INNER JOIN proveedores as pro ON pro.id_proveedor=enc_ret.id_proveedor INNER JOIN empresas as emp ON emp.ruc = enc_ret.ruc_empresa INNER JOIN comprobantes_autorizados as com_aut ON com_aut.codigo_comprobante=enc_ret.tipo_comprobante WHERE $ruc_cuerpo_retencion_compra and cue_ret.ejercicio_fiscal = '" . $ejercicio_fiscal . "' ");

$resultado_retenciones_venta =  mysqli_query($con, "SELECT emp.nombre_comercial as nombre_sucursal, enc_ret.fecha_emision as fecha_retencion,
cli.nombre as nombre_cliente, cli.ruc as ruc_cliente, enc_ret.serie_retencion as serie_retencion, enc_ret.secuencial_retencion as secuencial_retencion, 
com_aut.comprobante as documento_retenido, enc_ret.numero_documento as numero_documento_retenido, if(cue_ret.impuesto=1,'RENTA','IVA') as impuesto, cue_ret.base_imponible as base_imponible, cue_ret.codigo_impuesto as codigo_impuesto, cue_ret.porcentaje_retencion as porcentaje, cue_ret.valor_retenido as valor_retenido, ret_sri.concepto_ret as concepto 
FROM cuerpo_retencion_venta as cue_ret INNER JOIN encabezado_retencion_venta as enc_ret ON enc_ret.codigo_unico=cue_ret.codigo_unico INNER JOIN clientes as cli ON cli.id=enc_ret.id_cliente INNER JOIN empresas as emp ON emp.ruc = enc_ret.ruc_empresa INNER JOIN comprobantes_autorizados as com_aut ON com_aut.codigo_comprobante=cue_ret.tipo_documento 
LEFT JOIN retenciones_sri as ret_sri ON ret_sri.codigo_ret=cue_ret.codigo_impuesto WHERE $ruc_cuerpo_retencion_compra and cue_ret.ejercicio_fiscal = '" . $ejercicio_fiscal . "' ");

//hast aqui consultas

//para facturas de ventas
$suma_resultados = 0; //me permite ir sumando si hay resultados en cada sql
$tituloReporte = "Reporte General de Facturas de Ventas";
$titulosColumnas = array(
	'Sucursal',
	'Fecha', 'Cliente', 'Ruc', 'Factura',
	'Base 15', 'Base 14', 'Base 13', 'Base 12', 'Base 5', 'Base Diferenciado',
	'Base 0', 'Base No iva', 'Base Exento',
	'Iva 15', 'Iva 14', 'Iva 13', 'Iva 12', 'Iva 5', 'Iva diferenciado',
	'Base ice', 'Descuento', 'Propina', 'Otros', 'Total', 'Usuario'
);
$objPHPExcel->setActiveSheetIndex(0)
	->mergeCells('A1:C1')
	->mergeCells('A2:C2');

// Se agregan los titulos del reporte
$objPHPExcel->setActiveSheetIndex(0)
	->setCellValue('A1',  $tituloEmpresa)
	->setCellValue('A2',  $tituloReporte)
	->setCellValue('A3',  $tituloPeriodo);
$objPHPExcel->getActiveSheet(0)->setTitle('Ventas');
$objPHPExcel->getActiveSheet(0)->freezePaneByColumnAndRow(0, 5);

$i = 4;
$objPHPExcel->setActiveSheetIndex(0)
	->setCellValue('A' . $i,  $titulosColumnas[0])
	->setCellValue('B' . $i,  $titulosColumnas[1])
	->setCellValue('C' . $i,  $titulosColumnas[2])
	->setCellValue('D' . $i,  $titulosColumnas[3])
	->setCellValue('E' . $i,  $titulosColumnas[4])
	->setCellValue('F' . $i,  $titulosColumnas[5])
	->setCellValue('G' . $i,  $titulosColumnas[6])
	->setCellValue('H' . $i,  $titulosColumnas[7])
	->setCellValue('I' . $i,  $titulosColumnas[8])
	->setCellValue('J' . $i,  $titulosColumnas[9])
	->setCellValue('K' . $i,  $titulosColumnas[10])
	->setCellValue('L' . $i,  $titulosColumnas[11])
	->setCellValue('M' . $i,  $titulosColumnas[12])
	->setCellValue('N' . $i,  $titulosColumnas[13])
	->setCellValue('O' . $i,  $titulosColumnas[14])
	->setCellValue('P' . $i,  $titulosColumnas[15])
	->setCellValue('Q' . $i,  $titulosColumnas[16])
	->setCellValue('R' . $i,  $titulosColumnas[17])
	->setCellValue('S' . $i,  $titulosColumnas[18])
	->setCellValue('T' . $i,  $titulosColumnas[19])
	->setCellValue('U' . $i,  $titulosColumnas[20])
	->setCellValue('V' . $i,  $titulosColumnas[21])
	->setCellValue('W' . $i,  $titulosColumnas[22])
	->setCellValue('X' . $i,  $titulosColumnas[23])
	->setCellValue('Y' . $i,  $titulosColumnas[24])
	->setCellValue('Z' . $i,  $titulosColumnas[25]);

//para nc de ventas
//para agregar una nueva hoja
$objPHPExcel->createSheet();
$tituloReporte = "Reporte General de Notas de Crédito en Ventas";
$titulosColumnas = array(
	'Sucursal', 'Fecha', 'Cliente',
	'Ruc', 'Número NC', 'Documento modificado',
	'Base 15', 'Base 14', 'Base 13',
	'Base 12', 'Base 5', 'Base diferenciado',
	'Base 0', 'Base No iva',	'Base Exento',
	'Iva 15', 'Iva 14', 'Iva 13',
	'Iva 12', 'Iva 5', 'Iva diferenciado',
	'Base ice', 'Descuento', 'Total', 'Usuario'
);

$objPHPExcel->setActiveSheetIndex(1)
	->mergeCells('A1:C1')
	->mergeCells('A2:C2');

// Se agregan los titulos del reporte
$objPHPExcel->setActiveSheetIndex(1)
	->setCellValue('A1',  $tituloEmpresa)
	->setCellValue('A2',  $tituloReporte)
	->setCellValue('A3',  $tituloPeriodo);
$objPHPExcel->getActiveSheet(1)->setTitle('NcVentas');
$objPHPExcel->getActiveSheet(1)->freezePaneByColumnAndRow(0, 5);

$i = 4;
$objPHPExcel->setActiveSheetIndex(1)
	->setCellValue('A' . $i,  $titulosColumnas[0])
	->setCellValue('B' . $i,  $titulosColumnas[1])
	->setCellValue('C' . $i,  $titulosColumnas[2])
	->setCellValue('D' . $i,  $titulosColumnas[3])
	->setCellValue('E' . $i,  $titulosColumnas[4])
	->setCellValue('F' . $i,  $titulosColumnas[5])
	->setCellValue('G' . $i,  $titulosColumnas[6])
	->setCellValue('H' . $i,  $titulosColumnas[7])
	->setCellValue('I' . $i,  $titulosColumnas[8])
	->setCellValue('J' . $i,  $titulosColumnas[9])
	->setCellValue('K' . $i,  $titulosColumnas[10])
	->setCellValue('L' . $i,  $titulosColumnas[11])
	->setCellValue('M' . $i,  $titulosColumnas[12])
	->setCellValue('N' . $i,  $titulosColumnas[13])
	->setCellValue('O' . $i,  $titulosColumnas[14])
	->setCellValue('P' . $i,  $titulosColumnas[15])
	->setCellValue('Q' . $i,  $titulosColumnas[16])
	->setCellValue('R' . $i,  $titulosColumnas[17])
	->setCellValue('S' . $i,  $titulosColumnas[18])
	->setCellValue('T' . $i,  $titulosColumnas[19])
	->setCellValue('U' . $i,  $titulosColumnas[20])
	->setCellValue('V' . $i,  $titulosColumnas[21])
	->setCellValue('W' . $i,  $titulosColumnas[22])
	->setCellValue('X' . $i,  $titulosColumnas[23])
	->setCellValue('Y' . $i,  $titulosColumnas[24]);


//para las compras
//para agregar una nueva hoja
$objPHPExcel->createSheet();
$tituloReporte = "Reporte General de Adquisiciones";
$titulosColumnas = array(
	'Sucursal', 'Fecha', 'Proveedor',
	'Ruc/Cedula', 'Documento', 'Número Documento',
	'Base 15', 'Base 14', 'Base 13',
	'Base 12', 'Base 5', 'Base diferenciado',
	'Base cero', 'Base No iva', 'Base Exento',
	'Iva 15', 'Iva 14', 'Iva 13',
	'Iva 12', 'Iva 5', 'Iva Diferenciado',
	'Descuento', 'Propina', 'Otros Valores',
	'Total', 'Tipo'
);

$objPHPExcel->setActiveSheetIndex(2)
	->mergeCells('A1:C1')
	->mergeCells('A2:C2');

// Se agregan los titulos del reporte
$objPHPExcel->setActiveSheetIndex(2)
	->setCellValue('A1',  $tituloEmpresa)
	->setCellValue('A2',  $tituloReporte)
	->setCellValue('A3',  $tituloPeriodo);

$objPHPExcel->getActiveSheet(2)->setTitle('Adquisiciones');
$objPHPExcel->getActiveSheet(2)->freezePaneByColumnAndRow(0, 5);

$objPHPExcel->setActiveSheetIndex(2)
	->setCellValue('A' . $i,  $titulosColumnas[0])
	->setCellValue('B' . $i,  $titulosColumnas[1])
	->setCellValue('C' . $i,  $titulosColumnas[2])
	->setCellValue('D' . $i,  $titulosColumnas[3])
	->setCellValue('E' . $i,  $titulosColumnas[4])
	->setCellValue('F' . $i,  $titulosColumnas[5])
	->setCellValue('G' . $i,  $titulosColumnas[6])
	->setCellValue('H' . $i,  $titulosColumnas[7])
	->setCellValue('I' . $i,  $titulosColumnas[8])
	->setCellValue('J' . $i,  $titulosColumnas[9])
	->setCellValue('K' . $i,  $titulosColumnas[10])
	->setCellValue('L' . $i,  $titulosColumnas[11])
	->setCellValue('M' . $i,  $titulosColumnas[12])
	->setCellValue('N' . $i,  $titulosColumnas[13])
	->setCellValue('O' . $i,  $titulosColumnas[14])
	->setCellValue('P' . $i,  $titulosColumnas[15])
	->setCellValue('Q' . $i,  $titulosColumnas[16])
	->setCellValue('R' . $i,  $titulosColumnas[17])
	->setCellValue('S' . $i,  $titulosColumnas[18])
	->setCellValue('T' . $i,  $titulosColumnas[19])
	->setCellValue('U' . $i,  $titulosColumnas[20])
	->setCellValue('V' . $i,  $titulosColumnas[21])
	->setCellValue('W' . $i,  $titulosColumnas[22])
	->setCellValue('X' . $i,  $titulosColumnas[23])
	->setCellValue('Y' . $i,  $titulosColumnas[24])
	->setCellValue('Z' . $i,  $titulosColumnas[25]);


$objPHPExcel->createSheet();

$tituloReporte = "Reporte General de Notas de Crédito en Adquisiciones";
$titulosColumnas = array(
	'Sucursal', 'Fecha', 'Proveedor',
	'Ruc/Cedula', 'Documento', 'Número Documento',
	'Documento Afectado',
	'Base 15', 'Base 14', 'Base 13',
	'Base 12', 'Base 5', 'Base diferenciado',
	'Base 0', 'Base No iva', 'Base Exento',
	'Iva 15', 'Iva 14', 'Iva 13',
	'Iva 12', 'Iva 5', 'Iva diferenciado',
	'Descuento', 'Propina', 'Otros Valores',
	'Total', 'Tipo'
);

$objPHPExcel->setActiveSheetIndex(3)
	->mergeCells('A1:C1')
	->mergeCells('A2:C2');

// Se agregan los titulos del reporte
$objPHPExcel->setActiveSheetIndex(3)
	->setCellValue('A1',  $tituloEmpresa)
	->setCellValue('A2',  $tituloReporte)
	->setCellValue('A3',  $tituloPeriodo);

$objPHPExcel->getActiveSheet(3)->setTitle('NcAdquisiciones');
$objPHPExcel->getActiveSheet(3)->freezePaneByColumnAndRow(0, 5);

$i = 4;
$objPHPExcel->setActiveSheetIndex(3)
	->setCellValue('A' . $i,  $titulosColumnas[0])
	->setCellValue('B' . $i,  $titulosColumnas[1])
	->setCellValue('C' . $i,  $titulosColumnas[2])
	->setCellValue('D' . $i,  $titulosColumnas[3])
	->setCellValue('E' . $i,  $titulosColumnas[4])
	->setCellValue('F' . $i,  $titulosColumnas[5])
	->setCellValue('G' . $i,  $titulosColumnas[6])
	->setCellValue('H' . $i,  $titulosColumnas[7])
	->setCellValue('I' . $i,  $titulosColumnas[8])
	->setCellValue('J' . $i,  $titulosColumnas[9])
	->setCellValue('K' . $i,  $titulosColumnas[10])
	->setCellValue('L' . $i,  $titulosColumnas[11])
	->setCellValue('M' . $i,  $titulosColumnas[12])
	->setCellValue('N' . $i,  $titulosColumnas[13])
	->setCellValue('O' . $i,  $titulosColumnas[14])
	->setCellValue('P' . $i,  $titulosColumnas[15])
	->setCellValue('Q' . $i,  $titulosColumnas[16])
	->setCellValue('R' . $i,  $titulosColumnas[17])
	->setCellValue('S' . $i,  $titulosColumnas[18])
	->setCellValue('T' . $i,  $titulosColumnas[19])
	->setCellValue('U' . $i,  $titulosColumnas[20])
	->setCellValue('V' . $i,  $titulosColumnas[21])
	->setCellValue('W' . $i,  $titulosColumnas[22])
	->setCellValue('X' . $i,  $titulosColumnas[23])
	->setCellValue('Y' . $i,  $titulosColumnas[24])
	->setCellValue('Z' . $i,  $titulosColumnas[25])
	->setCellValue('AA' . $i,  $titulosColumnas[26]);


$objPHPExcel->createSheet();
$tituloReporte = "Reporte General de Retenciones en Compras";
$titulosColumnas = array('Sucursal', 'Fecha', 'Proveedor', 'Ruc/Cedula', 'Número Retención', 'Documento Retenido', 'Número Doc Retenido', 'Impuesto', 'Base Imponible', 'Código Retención', 'Porcentaje', 'Valor Retenido', 'Concepto');

$objPHPExcel->setActiveSheetIndex(4)
	->mergeCells('A1:C1')
	->mergeCells('A2:C2');

// Se agregan los titulos del reporte
$objPHPExcel->setActiveSheetIndex(4)
	->setCellValue('A1',  $tituloEmpresa)
	->setCellValue('A2',  $tituloReporte)
	->setCellValue('A3',  $tituloPeriodo);

$objPHPExcel->getActiveSheet(4)->setTitle('RetCompras');
$objPHPExcel->getActiveSheet(4)->freezePaneByColumnAndRow(0, 5);
$i = 4;
$objPHPExcel->setActiveSheetIndex(4)
	->setCellValue('A' . $i,  $titulosColumnas[0])
	->setCellValue('B' . $i,  $titulosColumnas[1])
	->setCellValue('C' . $i,  $titulosColumnas[2])
	->setCellValue('D' . $i,  $titulosColumnas[3])
	->setCellValue('E' . $i,  $titulosColumnas[4])
	->setCellValue('F' . $i,  $titulosColumnas[5])
	->setCellValue('G' . $i,  $titulosColumnas[6])
	->setCellValue('H' . $i,  $titulosColumnas[7])
	->setCellValue('I' . $i,  $titulosColumnas[8])
	->setCellValue('J' . $i,  $titulosColumnas[9])
	->setCellValue('K' . $i,  $titulosColumnas[10])
	->setCellValue('L' . $i,  $titulosColumnas[11])
	->setCellValue('M' . $i,  $titulosColumnas[12]);

$objPHPExcel->createSheet();
$tituloReporte = "Reporte General de Retenciones en Ventas";
$titulosColumnas = array('Sucursal', 'Fecha', 'Proveedor', 'Ruc/Cedula', 'Número Retención', 'Documento Retenido', 'Número Doc Retenido', 'Impuesto', 'Base Imponible', 'Código Retención', 'Porcentaje', 'Valor Retenido', 'Concepto');

$objPHPExcel->setActiveSheetIndex(5)
	->mergeCells('A1:C1')
	->mergeCells('A2:C2');

// Se agregan los titulos del reporte
$objPHPExcel->setActiveSheetIndex(5)
	->setCellValue('A1',  $tituloEmpresa)
	->setCellValue('A2',  $tituloReporte)
	->setCellValue('A3',  $tituloPeriodo);

$objPHPExcel->getActiveSheet(5)->setTitle('RetVentas');
$objPHPExcel->getActiveSheet(5)->freezePaneByColumnAndRow(0, 5);

$objPHPExcel->setActiveSheetIndex(5)
	->setCellValue('A' . $i,  $titulosColumnas[0])
	->setCellValue('B' . $i,  $titulosColumnas[1])
	->setCellValue('C' . $i,  $titulosColumnas[2])
	->setCellValue('D' . $i,  $titulosColumnas[3])
	->setCellValue('E' . $i,  $titulosColumnas[4])
	->setCellValue('F' . $i,  $titulosColumnas[5])
	->setCellValue('G' . $i,  $titulosColumnas[6])
	->setCellValue('H' . $i,  $titulosColumnas[7])
	->setCellValue('I' . $i,  $titulosColumnas[8])
	->setCellValue('J' . $i,  $titulosColumnas[9])
	->setCellValue('K' . $i,  $titulosColumnas[10])
	->setCellValue('L' . $i,  $titulosColumnas[11])
	->setCellValue('M' . $i,  $titulosColumnas[12]);


//desde aqui para llenar los datos en cada hoja
//para las ventas
if (mysqli_num_rows($resultado_facturas_ventas) > 0) {
	$i = 4;
	$suma_base_cero = 0;
	$suma_base_doce = 0;
	$suma_base_trece = 0;
	$suma_base_catorce = 0;
	$suma_base_quince = 0;
	$suma_base_cinco = 0;
	$suma_base_diferenciado = 0;
	$suma_base_noimp = 0;
	$suma_base_exento = 0;
	$suma_base_descuento = 0;
	$suma_propina = 0;
	$suma_tasa_turistica = 0;
	$suma_total_factura = 0;
	$suma_iva_base_doce = 0;
	$suma_iva_base_trece = 0;
	$suma_iva_base_catorce = 0;
	$suma_iva_base_quince = 0;
	$suma_iva_base_cinco = 0;
	$suma_iva_base_diferenciado = 0;
	$i++;
	while ($fila = mysqli_fetch_array($resultado_facturas_ventas)) {
		$numero_factura = $fila['serie_factura'] . "-" . str_pad($fila['secuencial_factura'], 9, "000000000", STR_PAD_LEFT);
		$suma_base_cero += $fila['base_cero'];
		$suma_base_doce += number_format($fila['base_doce'], 2, '.', '');
		$suma_base_trece += number_format($fila['base_trece'], 2, '.', '');
		$suma_base_catorce += number_format($fila['base_catorce'], 2, '.', '');
		$suma_base_quince += number_format($fila['base_quince'], 2, '.', '');
		$suma_base_cinco += number_format($fila['base_cinco'], 2, '.', '');
		$suma_base_diferenciado += number_format($fila['base_diferenciado'], 2, '.', '');
		$suma_iva_base_doce += number_format($fila['base_doce'] * 0.12, 2, '.', '');
		$suma_iva_base_trece += number_format($fila['base_trece'] * 0.13, 2, '.', '');
		$suma_iva_base_catorce += number_format($fila['base_catorce'] * 0.14, 2, '.', '');
		$suma_iva_base_quince += number_format($fila['base_quince'] * 0.15, 2, '.', '');
		$suma_iva_base_cinco += number_format($fila['base_cinco'] * 0.05, 2, '.', '');
		$suma_iva_base_diferenciado += number_format($fila['base_diferenciado'] * 0.08, 2, '.', '');
		$suma_base_noimp += $fila['base_noimp'];
		$suma_base_exento += $fila['base_exento'];
		$suma_base_descuento += $fila['descuento'];
		$suma_propina += $fila['propina'];
		$suma_tasa_turistica += $fila['tasa_turistica'];
		$suma_total_factura += $fila['total_factura'];

		$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('A' . $i,  $fila['nombre_sucursal'])
			->setCellValue('B' . $i,  date("d/m/Y", strtotime($fila['fecha_factura'])))
			->setCellValue('C' . $i,  strtoupper(clear_cadena($fila['nombre_cliente'])))
			->setCellValue('D' . $i,  "=\"" . $fila['ruc_cliente'] . "\"")
			->setCellValue('E' . $i,  $numero_factura)
			->setCellValue('F' . $i,  number_format($fila['base_quince'], 2, '.', ''))
			->setCellValue('G' . $i,  number_format($fila['base_catorce'], 2, '.', ''))
			->setCellValue('H' . $i,  number_format($fila['base_trece'], 2, '.', ''))
			->setCellValue('I' . $i,  number_format($fila['base_doce'], 2, '.', ''))
			->setCellValue('J' . $i,  number_format($fila['base_cinco'], 2, '.', ''))
			->setCellValue('K' . $i,  number_format($fila['base_diferenciado'], 2, '.', ''))
			->setCellValue('L' . $i,  number_format($fila['base_cero'], 2, '.', ''))
			->setCellValue('M' . $i,  number_format($fila['base_noimp'], 2, '.', ''))
			->setCellValue('N' . $i,  number_format($fila['base_exento'], 2, '.', ''))
			->setCellValue('O' . $i,  number_format($fila['base_quince'] * 0.15, 2, '.', ''))
			->setCellValue('P' . $i,  number_format($fila['base_catorce'] * 0.14, 2, '.', ''))
			->setCellValue('Q' . $i,  number_format($fila['base_trece'] * 0.13, 2, '.', ''))
			->setCellValue('R' . $i,  number_format($fila['base_doce'] * 0.12, 2, '.', ''))
			->setCellValue('S' . $i,  number_format($fila['base_cinco'] * 0.05, 2, '.', ''))
			->setCellValue('T' . $i,  number_format($fila['base_diferenciado'] * 0.08, 2, '.', ''))
			->setCellValue('U' . $i,  number_format(0, 2, '.', ''))
			->setCellValue('V' . $i,  number_format($fila['descuento'], 2, '.', ''))
			->setCellValue('W' . $i,  number_format($fila['propina'], 2, '.', ''))
			->setCellValue('X' . $i,  number_format($fila['tasa_turistica'], 2, '.', ''))
			->setCellValue('Y' . $i,  number_format($fila['total_factura'], 2, '.', ''))
			->setCellValue('Z' . $i,  strtoupper($fila['nombre_usuario']));
		$i++;
	}

	$t = $i + 1;
	$objPHPExcel->setActiveSheetIndex(0)
		->setCellValue('E' . $t,  'Totales')
		->setCellValue('F' . $t,  number_format($suma_base_quince, 2, '.', ''))
		->setCellValue('G' . $t,  number_format($suma_base_catorce, 2, '.', ''))
		->setCellValue('H' . $t,  number_format($suma_base_trece, 2, '.', ''))
		->setCellValue('I' . $t,  number_format($suma_base_doce, 2, '.', ''))
		->setCellValue('J' . $t,  number_format($suma_base_cinco, 2, '.', ''))
		->setCellValue('K' . $t,  number_format($suma_base_diferenciado, 2, '.', ''))
		->setCellValue('L' . $t,  number_format($suma_base_cero, 2, '.', ''))
		->setCellValue('M' . $t,  number_format($suma_base_noimp, 2, '.', ''))
		->setCellValue('N' . $t,  number_format($suma_base_exento, 2, '.', ''))
		->setCellValue('O' . $t,  number_format($suma_iva_base_quince, 2, '.', ''))
		->setCellValue('P' . $t,  number_format($suma_iva_base_catorce, 2, '.', ''))
		->setCellValue('Q' . $t,  number_format($suma_iva_base_trece, 2, '.', ''))
		->setCellValue('R' . $t,  number_format($suma_iva_base_doce, 2, '.', ''))
		->setCellValue('S' . $t,  number_format($suma_iva_base_cinco, 2, '.', ''))
		->setCellValue('T' . $t,  number_format($suma_iva_base_diferenciado, 2, '.', ''))
		->setCellValue('U' . $t,  number_format(0, 2, '.', ''))
		->setCellValue('V' . $t,  number_format($suma_base_descuento, 2, '.', ''))
		->setCellValue('W' . $t,  number_format($suma_propina, 2, '.', ''))
		->setCellValue('X' . $t,  number_format($suma_tasa_turistica, 2, '.', ''))
		->setCellValue('Y' . $t,  number_format($suma_total_factura, 2, '.', ''));

	$objPHPExcel->getActiveSheet()->getStyle('F4:Y' . $t)->getNumberFormat()->setFormatCode('#,##0.00');


	for ($i = 'A'; $i <= 'O'; $i++) {
		$objPHPExcel->setActiveSheetIndex(0)
			->getColumnDimension($i)->setAutoSize(TRUE);
	}
	$suma_resultados = 1;
} else {
	$suma_resultados = 0;
} //aqui termina la factura de ventas 

//para las nc en ventas
if (mysqli_num_rows($resultado_nc_facturas_ventas) > 0) {
	$i = 4;
	$suma_base_cero = 0;
	$suma_base_doce = 0;
	$suma_base_trece = 0;
	$suma_base_catorce = 0;
	$suma_base_quince = 0;
	$suma_base_cinco = 0;
	$suma_base_diferenciado = 0;
	$suma_base_noimp = 0;
	$suma_base_exento = 0;
	$suma_base_descuento = 0;
	$suma_total_nc = 0;
	$suma_iva_base_doce = 0;
	$suma_iva_base_trece = 0;
	$suma_iva_base_catorce = 0;
	$suma_iva_base_quince = 0;
	$suma_iva_base_cinco = 0;
	$suma_iva_base_diferenciado = 0;
	$i++;
	while ($fila = mysqli_fetch_array($resultado_nc_facturas_ventas)) {
		$numero_nc = $fila['serie_nc'] . "-" . str_pad($fila['secuencial_nc'], 9, "000000000", STR_PAD_LEFT);
		$suma_base_cero += $fila['base_cero'];
		$suma_base_doce += number_format($fila['base_doce'], 2, '.', '');
		$suma_base_trece += number_format($fila['base_trece'], 2, '.', '');
		$suma_base_catorce += number_format($fila['base_catorce'], 2, '.', '');
		$suma_base_quince += number_format($fila['base_quince'], 2, '.', '');
		$suma_base_cinco += number_format($fila['base_cinco'], 2, '.', '');
		$suma_base_diferenciado += number_format($fila['base_diferenciado'], 2, '.', '');
		$suma_iva_base_doce += number_format($fila['base_doce'] * 0.12, 2, '.', '');
		$suma_iva_base_trece += number_format($fila['base_trece'] * 0.13, 2, '.', '');
		$suma_iva_base_catorce += number_format($fila['base_catorce'] * 0.14, 2, '.', '');
		$suma_iva_base_quince += number_format($fila['base_quince'] * 0.15, 2, '.', '');
		$suma_iva_base_cinco += number_format($fila['base_cinco'] * 0.05, 2, '.', '');
		$suma_iva_base_diferenciado += number_format($fila['base_diferenciado'] * 0.08, 2, '.', '');
		$suma_base_noimp += $fila['base_noimp'];
		$suma_base_exento += $fila['base_exento'];
		$suma_base_descuento += $fila['descuento'];
		$suma_total_nc += $fila['total_nc'];

		$objPHPExcel->setActiveSheetIndex(1)
			->setCellValue('A' . $i,  $fila['nombre_sucursal'])
			->setCellValue('B' . $i,  date("d/m/Y", strtotime($fila['fecha_nc'])))
			->setCellValue('C' . $i,  "=\"" . strtoupper($fila['nombre_cliente']) . "\"")
			->setCellValue('D' . $i,  "=\"" . $fila['ruc_cliente'] . "\"")
			->setCellValue('E' . $i,  $numero_nc)
			->setCellValue('F' . $i,  $fila['factura_modificada'])
			->setCellValue('G' . $i,  number_format($fila['base_quince'], 2, '.', ''))
			->setCellValue('H' . $i,  number_format($fila['base_catorce'], 2, '.', ''))
			->setCellValue('I' . $i,  number_format($fila['base_trece'], 2, '.', ''))
			->setCellValue('J' . $i,  number_format($fila['base_doce'], 2, '.', ''))
			->setCellValue('K' . $i,  number_format($fila['base_cinco'], 2, '.', ''))
			->setCellValue('L' . $i,  number_format($fila['base_diferenciado'], 2, '.', ''))
			->setCellValue('M' . $i,  number_format($fila['base_cero'], 2, '.', ''))
			->setCellValue('N' . $i,  number_format($fila['base_noimp'], 2, '.', ''))
			->setCellValue('O' . $i,  number_format($fila['base_exento'], 2, '.', ''))
			->setCellValue('P' . $i,  number_format($fila['base_quince'] * 0.15, 2, '.', ''))
			->setCellValue('Q' . $i,  number_format($fila['base_catorce'] * 0.14, 2, '.', ''))
			->setCellValue('R' . $i,  number_format($fila['base_trece'] * 0.13, 2, '.', ''))
			->setCellValue('S' . $i,  number_format($fila['base_doce'] * 0.12, 2, '.', ''))
			->setCellValue('T' . $i,  number_format($fila['base_cinco'] * 0.05, 2, '.', ''))
			->setCellValue('U' . $i,  number_format($fila['base_diferenciado'] * 0.08, 2, '.', ''))
			->setCellValue('V' . $i,  number_format(0, 2, '.', ''))
			->setCellValue('W' . $i,  number_format($fila['descuento'], 2, '.', ''))
			->setCellValue('X' . $i,  number_format($fila['total_nc'], 2, '.', ''))
			->setCellValue('Y' . $i,  strtoupper($fila['nombre_usuario']));
		$i++;
	}
	$t = $i + 1;
	$objPHPExcel->setActiveSheetIndex(1)
		->setCellValue('F' . $t,  'Totales')
		->setCellValue('G' . $t,  number_format($suma_base_quince, 2, '.', ''))
		->setCellValue('H' . $t,  number_format($suma_base_catorce, 2, '.', ''))
		->setCellValue('I' . $t,  number_format($suma_base_trece, 2, '.', ''))
		->setCellValue('J' . $t,  number_format($suma_base_doce, 2, '.', ''))
		->setCellValue('K' . $t,  number_format($suma_base_cinco, 2, '.', ''))
		->setCellValue('L' . $t,  number_format($suma_base_diferenciado, 2, '.', ''))
		->setCellValue('M' . $t,  number_format($suma_base_cero, 2, '.', ''))
		->setCellValue('N' . $t,  number_format($suma_base_noimp, 2, '.', ''))
		->setCellValue('O' . $t,  number_format($suma_base_exento, 2, '.', ''))
		->setCellValue('P' . $t,  number_format($suma_iva_base_quince, 2, '.', ''))
		->setCellValue('Q' . $t,  number_format($suma_iva_base_catorce, 2, '.', ''))
		->setCellValue('R' . $t,  number_format($suma_iva_base_trece, 2, '.', ''))
		->setCellValue('S' . $t,  number_format($suma_iva_base_doce, 2, '.', ''))
		->setCellValue('T' . $t,  number_format($suma_iva_base_cinco, 2, '.', ''))
		->setCellValue('U' . $t,  number_format($suma_iva_base_diferenciado, 2, '.', ''))
		->setCellValue('V' . $t,  number_format(0, 2, '.', ''))
		->setCellValue('W' . $t,  number_format($suma_base_descuento, 2, '.', ''))
		->setCellValue('X' . $t,  number_format($suma_total_nc, 2, '.', ''));

	$objPHPExcel->getActiveSheet()->getStyle('G4:X' . $t)->getNumberFormat()->setFormatCode('#,##0.00');

	for ($i = 'A'; $i <= 'O'; $i++) {
		$objPHPExcel->setActiveSheetIndex(1)
			->getColumnDimension($i)->setAutoSize(TRUE);
	}
	$suma_resultados = 1;
} else {
	$suma_resultados = 0;
} //aqui termina las nc ventas 

//para las compras
if (mysqli_num_rows($resultado_facturas_compras) > 0) {
	$i = 4;
	$suma_base_cero = 0;
	$suma_base_quince = 0;
	$suma_base_catorce = 0;
	$suma_base_trece = 0;
	$suma_base_doce = 0;
	$suma_base_cinco = 0;
	$suma_base_diferenciado = 0;
	$suma_base_noimp = 0;
	$suma_base_exento = 0;
	$suma_base_descuento = 0;
	$suma_total_compra = 0;
	$suma_iva_base_quince = 0;
	$suma_iva_base_catorce = 0;
	$suma_iva_base_trece = 0;
	$suma_iva_base_doce = 0;
	$suma_iva_base_cinco = 0;
	$suma_iva_base_diferenciado = 0;
	$suma_propina = 0;
	$suma_otros_val = 0;
	$i++;
	while ($fila = mysqli_fetch_array($resultado_facturas_compras)) {
		$suma_base_cero += number_format($fila['base_cero'], 2, '.', '');
		$suma_base_quince += number_format($fila['base_quince'], 2, '.', '');
		$suma_base_catorce += number_format($fila['base_catorce'], 2, '.', '');
		$suma_base_trece += number_format($fila['base_trece'], 2, '.', '');
		$suma_base_doce += number_format($fila['base_doce'], 2, '.', '');
		$suma_base_cinco += number_format($fila['base_cinco'], 2, '.', '');
		$suma_base_diferenciado += number_format($fila['base_diferenciado'], 2, '.', '');
		$suma_iva_base_quince += number_format($fila['base_quince'] * 0.15, 2, '.', '');
		$suma_iva_base_catorce += number_format($fila['base_catorce'] * 0.14, 2, '.', '');
		$suma_iva_base_trece += number_format($fila['base_trece'] * 0.13, 2, '.', '');
		$suma_iva_base_doce += number_format($fila['base_doce'] * 0.12, 2, '.', '');
		$suma_iva_base_cinco += number_format($fila['base_cinco'] * 0.05, 2, '.', '');
		$suma_iva_base_diferenciado += number_format($fila['base_diferenciado'] * 0.08, 2, '.', '');
		$suma_base_noimp += $fila['base_noimp'];
		$suma_base_exento += $fila['base_exento'];
		$suma_base_descuento += $fila['descuento'];
		$suma_total_compra += $fila['total_compra'];
		$suma_propina += $fila['propina'];
		$suma_otros_val += $fila['otros_val'];

		$objPHPExcel->setActiveSheetIndex(2)
			->setCellValue('A' . $i,  $fila['nombre_sucursal'])
			->setCellValue('B' . $i,  date("d/m/Y", strtotime($fila['fecha_compra'])))
			->setCellValue('C' . $i,  "=\"" . strtoupper($fila['nombre_proveedor']) . "\"")
			->setCellValue('D' . $i,  "=\"" . $fila['ruc_proveedor'] . "\"")
			->setCellValue('E' . $i,  "=\"" . $fila['comprobante'] . "\"")
			->setCellValue('F' . $i,  $fila['numero_documento'])
			->setCellValue('G' . $i,  number_format($fila['base_quince'], 2, '.', ''))
			->setCellValue('H' . $i,  number_format($fila['base_catorce'], 2, '.', ''))
			->setCellValue('I' . $i,  number_format($fila['base_trece'], 2, '.', ''))
			->setCellValue('J' . $i,  number_format($fila['base_doce'], 2, '.', ''))
			->setCellValue('K' . $i,  number_format($fila['base_cinco'], 2, '.', ''))
			->setCellValue('L' . $i,  number_format($fila['base_diferenciado'], 2, '.', ''))
			->setCellValue('M' . $i,  number_format($fila['base_cero'], 2, '.', ''))
			->setCellValue('N' . $i,  number_format($fila['base_noimp'], 2, '.', ''))
			->setCellValue('O' . $i,  number_format($fila['base_exento'], 2, '.', ''))
			->setCellValue('P' . $i,  number_format($fila['base_quince'] * 0.15, 2, '.', ''))
			->setCellValue('Q' . $i,  number_format($fila['base_catorce'] * 0.14, 2, '.', ''))
			->setCellValue('R' . $i,  number_format($fila['base_trece'] * 0.13, 2, '.', ''))
			->setCellValue('S' . $i,  number_format($fila['base_doce'] * 0.12, 2, '.', ''))
			->setCellValue('T' . $i,  number_format($fila['base_cinco'] * 0.05, 2, '.', ''))
			->setCellValue('U' . $i,  number_format($fila['base_diferenciado'] * 0.08, 2, '.', ''))
			->setCellValue('V' . $i,  number_format($fila['descuento'], 2, '.', ''))
			->setCellValue('W' . $i,  number_format($fila['propina'], 2, '.', ''))
			->setCellValue('X' . $i,  number_format($fila['otros_val'], 2, '.', ''))
			->setCellValue('Y' . $i,  number_format($fila['total_compra'], 2, '.', ''))
			->setCellValue('Z' . $i,  strtoupper($fila['tipo_comprobante']));
		$i++;
	}

	$t = $i + 1;
	$objPHPExcel->setActiveSheetIndex(2)
		->setCellValue('F' . $t,  'Totales')
		->setCellValue('G' . $t,  number_format($suma_base_quince, 2, '.', ''))
		->setCellValue('H' . $t,  number_format($suma_base_catorce, 2, '.', ''))
		->setCellValue('I' . $t,  number_format($suma_base_trece, 2, '.', ''))
		->setCellValue('J' . $t,  number_format($suma_base_doce, 2, '.', ''))
		->setCellValue('K' . $t,  number_format($suma_base_cinco, 2, '.', ''))
		->setCellValue('L' . $t,  number_format($suma_base_diferenciado, 2, '.', ''))
		->setCellValue('M' . $t,  number_format($suma_base_cero, 2, '.', ''))
		->setCellValue('N' . $t,  number_format($suma_base_noimp, 2, '.', ''))
		->setCellValue('O' . $t,  number_format($suma_base_exento, 2, '.', ''))
		->setCellValue('P' . $t,  number_format($suma_iva_base_quince, 2, '.', ''))
		->setCellValue('Q' . $t,  number_format($suma_iva_base_catorce, 2, '.', ''))
		->setCellValue('R' . $t,  number_format($suma_iva_base_trece, 2, '.', ''))
		->setCellValue('S' . $t,  number_format($suma_iva_base_doce, 2, '.', ''))
		->setCellValue('T' . $t,  number_format($suma_iva_base_cinco, 2, '.', ''))
		->setCellValue('U' . $t,  number_format($suma_iva_base_diferenciado, 2, '.', ''))
		->setCellValue('V' . $t,  number_format($suma_base_descuento, 2, '.', ''))
		->setCellValue('W' . $t,  number_format($suma_propina, 2, '.', ''))
		->setCellValue('X' . $t,  number_format($suma_otros_val, 2, '.', ''))
		->setCellValue('Y' . $t,  number_format($suma_total_compra, 2, '.', ''));
	$objPHPExcel->getActiveSheet()->getStyle('G4:Y' . $t)->getNumberFormat()->setFormatCode('#,##0.00');
	for ($i = 'A'; $i <= 'C'; $i++) {
		$objPHPExcel->setActiveSheetIndex(2)
			->getColumnDimension($i)->setAutoSize(TRUE);
	}
	$suma_resultados = 1;
} else {
	$suma_resultados = 0;
} //aqui termina las compras 

//PARA LAS NC COMPRAS
if (mysqli_num_rows($resultado_nc_compras) > 0) {
	$i = 4;
	$suma_base_cero = 0;
	$suma_base_quince = 0;
	$suma_base_catorce = 0;
	$suma_base_trece = 0;
	$suma_base_doce = 0;
	$suma_base_cinco = 0;
	$suma_base_diferenciado = 0;
	$suma_base_noimp = 0;
	$suma_base_exento = 0;
	$suma_base_descuento = 0;
	$suma_total_compra = 0;
	$suma_iva_base_quince = 0;
	$suma_iva_base_catorce = 0;
	$suma_iva_base_trece = 0;
	$suma_iva_base_doce = 0;
	$suma_iva_base_cinco = 0;
	$suma_iva_base_diferenciado = 0;
	$suma_propina = 0;
	$suma_otros_val = 0;
	$i++;
	while ($fila = mysqli_fetch_array($resultado_nc_compras)) {
		$suma_base_cero += $fila['base_cero'];
		$suma_base_quince += number_format($fila['base_quince'], 2, '.', '');
		$suma_base_catorce += number_format($fila['base_catorce'], 2, '.', '');
		$suma_base_trece += number_format($fila['base_trece'], 2, '.', '');
		$suma_base_doce += number_format($fila['base_doce'], 2, '.', '');
		$suma_base_cinco += number_format($fila['base_cinco'], 2, '.', '');
		$suma_base_diferenciado += number_format($fila['base_diferenciado'], 2, '.', '');
		$suma_iva_base_quince += number_format($fila['base_quince'] * 0.15, 2, '.', '');
		$suma_iva_base_catorce += number_format($fila['base_catorce'] * 0.14, 2, '.', '');
		$suma_iva_base_trece += number_format($fila['base_trece'] * 0.13, 2, '.', '');
		$suma_iva_base_doce += number_format($fila['base_doce'] * 0.12, 2, '.', '');
		$suma_iva_base_cinco += number_format($fila['base_cinco'] * 0.05, 2, '.', '');
		$suma_iva_base_diferenciado += number_format($fila['base_diferenciado'] * 0.08, 2, '.', '');
		$suma_base_noimp += $fila['base_noimp'];
		$suma_base_exento += $fila['base_exento'];
		$suma_base_descuento += $fila['descuento'];
		$suma_total_compra += $fila['total_compra'];
		$suma_propina += $fila['propina'];
		$suma_otros_val += $fila['otros_val'];

		$objPHPExcel->setActiveSheetIndex(3)
			->setCellValue('A' . $i,  $fila['nombre_sucursal'])
			->setCellValue('B' . $i,  date("d/m/Y", strtotime($fila['fecha_compra'])))
			->setCellValue('C' . $i,  strtoupper(clear_cadena($fila['nombre_proveedor'])))
			->setCellValue('D' . $i,  "=\"" . $fila['ruc_proveedor'] . "\"")
			->setCellValue('E' . $i,  $fila['comprobante'])
			->setCellValue('F' . $i,  $fila['numero_documento'])
			->setCellValue('G' . $i,  $fila['documento_afectado'])
			->setCellValue('H' . $i,  number_format($fila['base_quince'], 2, '.', ''))
			->setCellValue('I' . $i,  number_format($fila['base_catorce'], 2, '.', ''))
			->setCellValue('J' . $i,  number_format($fila['base_trece'], 2, '.', ''))
			->setCellValue('K' . $i,  number_format($fila['base_doce'], 2, '.', ''))
			->setCellValue('L' . $i,  number_format($fila['base_cinco'], 2, '.', ''))
			->setCellValue('M' . $i,  number_format($fila['base_diferenciado'], 2, '.', ''))
			->setCellValue('N' . $i,  number_format($fila['base_cero'], 2, '.', ''))
			->setCellValue('O' . $i,  number_format($fila['base_noimp'], 2, '.', ''))
			->setCellValue('P' . $i,  number_format($fila['base_exento'], 2, '.', ''))
			->setCellValue('Q' . $i,  number_format($fila['base_quince'] * 0.15, 2, '.', ''))
			->setCellValue('R' . $i,  number_format($fila['base_catorce'] * 0.14, 2, '.', ''))
			->setCellValue('S' . $i,  number_format($fila['base_trece'] * 0.13, 2, '.', ''))
			->setCellValue('T' . $i,  number_format($fila['base_doce'] * 0.12, 2, '.', ''))
			->setCellValue('U' . $i,  number_format($fila['base_cinco'] * 0.05, 2, '.', ''))
			->setCellValue('V' . $i,  number_format($fila['base_diferenciado'] * 0.08, 2, '.', ''))
			->setCellValue('W' . $i,  number_format($fila['descuento'], 2, '.', ''))
			->setCellValue('X' . $i,  number_format($fila['propina'], 2, '.', ''))
			->setCellValue('Y' . $i,  number_format($fila['otros_val'], 2, '.', ''))
			->setCellValue('Z' . $i,  number_format($fila['total_compra'], 2, '.', ''))
			->setCellValue('AA' . $i,  strtoupper($fila['tipo_comprobante']));
		$i++;
	}
	$t = $i + 1;
	$objPHPExcel->setActiveSheetIndex(3)
		->setCellValue('G' . $t,  'Totales')
		->setCellValue('H' . $t,  number_format($suma_base_quince, 2, '.', ''))
		->setCellValue('I' . $t,  number_format($suma_base_catorce, 2, '.', ''))
		->setCellValue('J' . $t,  number_format($suma_base_trece, 2, '.', ''))
		->setCellValue('K' . $t,  number_format($suma_base_doce, 2, '.', ''))
		->setCellValue('L' . $t,  number_format($suma_base_cinco, 2, '.', ''))
		->setCellValue('M' . $t,  number_format($suma_base_diferenciado, 2, '.', ''))
		->setCellValue('N' . $t,  number_format($suma_base_cero, 2, '.', ''))
		->setCellValue('O' . $t,  number_format($suma_base_noimp, 2, '.', ''))
		->setCellValue('P' . $t,  number_format($suma_base_exento, 2, '.', ''))
		->setCellValue('Q' . $t,  number_format($suma_iva_base_quince, 2, '.', ''))
		->setCellValue('R' . $t,  number_format($suma_iva_base_catorce, 2, '.', ''))
		->setCellValue('S' . $t,  number_format($suma_iva_base_trece, 2, '.', ''))
		->setCellValue('T' . $t,  number_format($suma_iva_base_doce, 2, '.', ''))
		->setCellValue('U' . $t,  number_format($suma_iva_base_cinco, 2, '.', ''))
		->setCellValue('V' . $t,  number_format($suma_iva_base_diferenciado, 2, '.', ''))
		->setCellValue('W' . $t,  number_format($suma_base_descuento, 2, '.', ''))
		->setCellValue('X' . $t,  number_format($suma_propina, 2, '.', ''))
		->setCellValue('Y' . $t,  number_format($suma_otros_val, 2, '.', ''))
		->setCellValue('Z' . $t,  number_format($suma_total_compra, 2, '.', ''));
	$objPHPExcel->getActiveSheet()->getStyle('H4:Z' . $t)->getNumberFormat()->setFormatCode('#,##0.00');
	for ($i = 'A'; $i <= 'C'; $i++) {
		$objPHPExcel->setActiveSheetIndex(3)
			->getColumnDimension($i)->setAutoSize(TRUE);
	}
	$suma_resultados = 1;
} else {
	$suma_resultados = 0;
} //hasta aqui las nc compras

//para las retenciones en compras 
if (mysqli_num_rows($resultado_retenciones_compra) > 0) {
	$i = 4;
	$suma_total = 0;
	$suma_base_imponible = 0;
	$i++;
	while ($fila = mysqli_fetch_array($resultado_retenciones_compra)) {
		$numero_retencion = $fila['serie_retencion'] . "-" . str_pad($fila['secuencial_retencion'], 9, "000000000", STR_PAD_LEFT);
		$suma_total += $fila['valor_retenido'];
		$suma_base_imponible += $fila['base_imponible'];

		$objPHPExcel->setActiveSheetIndex(4)
			->setCellValue('A' . $i,  $fila['nombre_sucursal'])
			->setCellValue('B' . $i,  date("d/m/Y", strtotime($fila['fecha_retencion'])))
			->setCellValue('C' . $i,  strtoupper(clear_cadena($fila['nombre_proveedor'])))
			->setCellValue('D' . $i,  "=\"" . $fila['ruc_proveedor'] . "\"")
			->setCellValue('E' . $i,  $numero_retencion)
			->setCellValue('F' . $i,  $fila['documento_retenido'])
			->setCellValue('G' . $i,  $fila['numero_documento_retenido'])
			->setCellValue('H' . $i,  $fila['impuesto'])
			->setCellValue('I' . $i,  $fila['base_imponible'])
			->setCellValue('J' . $i,  $fila['codigo_impuesto'])
			->setCellValue('K' . $i,  $fila['porcentaje'] . "%")
			->setCellValue('L' . $i,  number_format($fila['valor_retenido'], 2, '.', ''))
			->setCellValue('M' . $i,  $fila['concepto']);
		$i++;
	}
	$t = $i + 1;
	$objPHPExcel->setActiveSheetIndex(4)
		->setCellValue('H' . $t,  'Totales')
		->setCellValue('I' . $t,  number_format($suma_base_imponible, 2, '.', ''))
		->setCellValue('L' . $t,  number_format($suma_total, 2, '.', ''));
	$objPHPExcel->getActiveSheet()->getStyle('I4:I' . $t)->getNumberFormat()->setFormatCode('#,##0.00');
	$objPHPExcel->getActiveSheet()->getStyle('L4:L' . $t)->getNumberFormat()->setFormatCode('#,##0.00');

	for ($i = 'A'; $i <= 'M'; $i++) {
		$objPHPExcel->setActiveSheetIndex(4)
			->getColumnDimension($i)->setAutoSize(TRUE);
	}
	$suma_resultados = 1;
} else {
	$suma_resultados = 0;
} //aqui termina las retenciones en compras

//para las retenciones en ventas
if (mysqli_num_rows($resultado_retenciones_venta) > 0) {
	$i = 4;
	$suma_total = 0;
	$suma_base_imponible = 0;
	$i++;
	while ($fila = mysqli_fetch_array($resultado_retenciones_venta)) {
		$numero_retencion = $fila['serie_retencion'] . "-" . str_pad($fila['secuencial_retencion'], 9, "000000000", STR_PAD_LEFT);
		$suma_total += $fila['valor_retenido'];
		$suma_base_imponible += $fila['base_imponible'];

		$objPHPExcel->setActiveSheetIndex(5)
			->setCellValue('A' . $i,  $fila['nombre_sucursal'])
			->setCellValue('B' . $i,  date("d/m/Y", strtotime($fila['fecha_retencion'])))
			->setCellValue('C' . $i,  strtoupper(clear_cadena($fila['nombre_cliente'])))
			->setCellValue('D' . $i,  "=\"" . $fila['ruc_cliente'] . "\"")
			->setCellValue('E' . $i,  $numero_retencion)
			->setCellValue('F' . $i,  $fila['documento_retenido'])
			->setCellValue('G' . $i,  substr($fila['numero_documento_retenido'], 0, 3) . "-" . substr($fila['numero_documento_retenido'], 3, 3) . "-" . substr($fila['numero_documento_retenido'], 6, 9))
			->setCellValue('H' . $i,  $fila['impuesto'])
			->setCellValue('I' . $i,  $fila['base_imponible'])
			->setCellValue('J' . $i,  $fila['codigo_impuesto'])
			->setCellValue('K' . $i,  $fila['porcentaje'] . "%")
			->setCellValue('L' . $i,  number_format($fila['valor_retenido'], 2, '.', ''))
			->setCellValue('M' . $i,  $fila['concepto']);
		$i++;
	}
	$t = $i + 1;
	$objPHPExcel->setActiveSheetIndex(5)
		->setCellValue('H' . $t,  'Totales')
		->setCellValue('I' . $t,  number_format($suma_base_imponible, 2, '.', ''))
		->setCellValue('L' . $t,  number_format($suma_total, 2, '.', ''));
	$objPHPExcel->getActiveSheet()->getStyle('I4:I' . $t)->getNumberFormat()->setFormatCode('#,##0.00');
	$objPHPExcel->getActiveSheet()->getStyle('L4:L' . $t)->getNumberFormat()->setFormatCode('#,##0.00');
	for ($i = 'A'; $i <= 'M'; $i++) {
		$objPHPExcel->setActiveSheetIndex(5)
			->getColumnDimension($i)->setAutoSize(TRUE);
	}
	$suma_resultados = 1;
} else {
	$suma_resultados = 0;
} //aqui termina las retenciones en ventas 


//hasta aqui se llenan los datos en las hojas

//para formar el libro y descargarlo
$objPHPExcel->setActiveSheetIndex(0);
$nombre_archivo ="ReporteGeneralConsolidado-".$mes."-".$anio.".xlsx";
//$objPHPExcel->getActiveSheet(0)->freezePaneByColumnAndRow(0,4);
// Se manda el archivo al navegador web, con el nombre que se indica (Excel2007)
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="'.$nombre_archivo.'"');
header('Cache-Control: max-age=0');

$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
$objWriter->save('php://output');
exit;
