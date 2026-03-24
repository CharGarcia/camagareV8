<?php
include("../conexiones/conectalogin.php");
require('../pdf/funciones_pdf.php');
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];

$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

if ($action == 'resumen_cartera_individual') {
	$id_documento = base64_decode($_GET['id_factura']);
	//para buscar la imagen
	$busca_imagen = mysqli_query($con, "SELECT * FROM sucursales WHERE ruc_empresa = '" . $ruc_empresa . "' ");
	$datos_imagen = mysqli_fetch_assoc($busca_imagen);
	$imagen = "../logos_empresas/" . $datos_imagen['logo_sucursal'];

	$busca_empresa = mysqli_query($con, "SELECT * FROM empresas WHERE ruc = '" . $ruc_empresa . "' ");
	$datos_empresa = mysqli_fetch_assoc($busca_empresa);
	$nombre_empresa = $datos_empresa['nombre'];
	$html_encabezado = '<p align="center">' . $nombre_empresa . '</p><br>
				  <p align="center">SALDO DE FACTURA DE VENTA</p>';

	$pdf = new funciones_pdf('P', 'mm', 'A4'); //P
	$pdf->AliasNbPages();
	$pdf->SetFont('Times', '', 12);
	$imagen_optimizada = $pdf->imagen_optimizada($imagen, $width = 200, $height = 200);
	imagejpeg($imagen_optimizada, '../docs_temp/' . $ruc_empresa . '.jpg');
	$nombre_imagen = '../docs_temp/' . $ruc_empresa . '.jpg';

	$pdf->AddPage(); //es importante agregar esta linea para saber la pagina inicial
	$pdf->SetFont('Arial', '', 9); //esta tambien es importante
	$pdf->detalle_html(utf8_decode($html_encabezado));
	//Image($file, $x=null, $y=null, $w=0, $h=0, $type='', $link='')
	$pdf->Image($nombre_imagen, 20, 10, 15, 15, 'jpg', '');

	//para las facturas
	$pdf->detalle_html("Detalle de la factura");
	$pdf->ln();
	$pdf->SetWidths(array(20, 110, 35, 20));
	$pdf->Row_tabla(array(utf8_decode('Fecha'), 'Cliente', 'Factura', 'Total'));

	$busca_factura = mysqli_query($con, "SELECT * FROM encabezado_factura as fac 
	INNER JOIN clientes as cli ON cli.id=fac.id_cliente 
	WHERE fac.id_encabezado_factura='" . $id_documento . "'");
	$row_datos = mysqli_fetch_assoc($busca_factura);
	$pdf->Row_tabla(array(
		date('d/m/Y', strtotime($row_datos['fecha_factura'])),
		utf8_decode(strtoupper($row_datos['nombre'])),
		$row_datos['serie_factura'] . "-" . str_pad($row_datos['secuencial_factura'], 9, "000000000", STR_PAD_LEFT),
		number_format($row_datos['total_factura'], 2, '.', '')
	));
	$total_factura = $row_datos['total_factura'];
	$numero_documento = $row_datos['serie_factura'] . "-" . str_pad($row_datos['secuencial_factura'], 9, "000000000", STR_PAD_LEFT);

	//para las nc
	$pdf->ln();
	$pdf->detalle_html(utf8_decode("Detalle de notas de crédito"));
	$pdf->ln();
	$pdf->SetWidths(array(20, 110, 35, 20));
	$pdf->Row_tabla(array(utf8_decode('Fecha'), 'Cliente', 'NC', 'Total'));
	$busca_notas_credito = mysqli_query($con, "SELECT * FROM encabezado_nc as enc 
	INNER JOIN encabezado_factura as fact 
	ON concat(fact.serie_factura,'-',LPAD(fact.secuencial_factura,9,'000000000')) = enc.factura_modificada 
	and fact.ruc_empresa=enc.ruc_empresa 
	INNER JOIN clientes as cli 
	ON cli.id=fact.id_cliente
	WHERE fact.id_encabezado_factura='" . $id_documento . "' ");
	$total_nc = 0;
	while ($row_datos = mysqli_fetch_assoc($busca_notas_credito)) {
		$pdf->Row_tabla(array(
			date('d/m/Y', strtotime($row_datos['fecha_nc'])),
			utf8_decode(strtoupper($row_datos['nombre'])),
			$row_datos['serie_nc'] . "-" . str_pad($row_datos['secuencial_nc'], 9, "000000000", STR_PAD_LEFT),
			number_format($row_datos['total_nc'], 2, '.', '')
		));
		$total_nc += $row_datos['total_nc'];
	}

	//para las ingresos
	$pdf->ln();
	$pdf->detalle_html(utf8_decode("Detalle de ingresos"));
	$pdf->ln();
	$pdf->SetWidths(array(20, 110, 35, 20));
	$pdf->Row_tabla(array(utf8_decode('Fecha'), 'Cliente', 'Ingreso', 'Total'));
	$busca_detalle_ingresos = mysqli_query($con, "SELECT inen.fecha_ing_egr as fecha_ing_egr, 
	inen.numero_ing_egr as numero_ing_egr, det.valor_ing_egr as valor_ing_egr, inen.nombre_ing_egr as nombre_ing_egr 
	FROM detalle_ingresos_egresos as det 
	INNER JOIN ingresos_egresos as inen 
	ON det.codigo_documento=inen.codigo_documento 
	WHERE det.tipo_documento='INGRESO' and det.codigo_documento_cv='" . $id_documento . "' "); //group by det.valor_ing_egr
	$total_ingresos = 0;
	while ($row_datos = mysqli_fetch_assoc($busca_detalle_ingresos)) {
		$pdf->Row_tabla(array(
			date('d/m/Y', strtotime($row_datos['fecha_ing_egr'])),
			utf8_decode(strtoupper($row_datos['nombre_ing_egr'])),
			$row_datos['numero_ing_egr'],
			number_format($row_datos['valor_ing_egr'], 2, '.', '')
		));
		$total_ingresos += $row_datos['valor_ing_egr'];
	}

	//para las retenciones
	$pdf->ln();
	$pdf->detalle_html(utf8_decode("Detalle de retenciones"));
	$pdf->ln();
	$pdf->SetWidths(array(20, 100, 10, 35, 20));
	$pdf->Row_tabla(array(utf8_decode('Fecha'), 'Cliente', '%', utf8_decode('Retención'), 'Total'));
	$busca_retenciones = mysqli_query($con, "SELECT * FROM encabezado_factura as enc 
	LEFT JOIN cuerpo_retencion_venta as cue 
	ON cue.numero_documento= CONCAT(REPLACE(enc.serie_factura,'-',''), LPAD(enc.secuencial_factura,9,'000000000')) 
	and cue.ruc_empresa=enc.ruc_empresa 
	INNER JOIN encabezado_retencion_venta as enret 
	ON enret.codigo_unico=cue.codigo_unico
	INNER JOIN clientes as cli
	ON cli.id=enret.id_cliente
	WHERE enc.id_encabezado_factura='" . $id_documento . "' ");
	$total_retenciones = 0;
	while ($row_datos = mysqli_fetch_assoc($busca_retenciones)) {
		$pdf->Row_tabla(array(
			date('d/m/Y', strtotime($row_datos['fecha_emision'])),
			utf8_decode(strtoupper($row_datos['nombre'])),
			number_format($row_datos['porcentaje_retencion'], 2, '.', ''),
			$row_datos['serie_retencion'] . "-" . str_pad($row_datos['secuencial_retencion'], 9, "000000000", STR_PAD_LEFT),
			number_format($row_datos['valor_retenido'], 2, '.', '')
		));
		$total_retenciones += $row_datos['valor_retenido'];
	}

	//para el resumen
	$pdf->ln();
	$pdf->Cell(20, 6, '', 2, 0, 'R');
	$pdf->detalle_html(utf8_decode("Resumen"));
	$pdf->ln();
	$pdf->Cell(20, 6, '', 2, 0, 'R');
	$pdf->SetWidths(array(30, 30, 30, 30, 30));
	$pdf->Row_tabla(array('Deuda', '(-) NC', '(-) Abonos', '(-) Retenciones', '(=) Saldo'));

	$pdf->Cell(20, 6, '', 2, 0, 'R');
	$pdf->Row_tabla(array(
		number_format($total_factura, 2, '.', ''),
		number_format($total_nc, 2, '.', ''),
		number_format($total_ingresos, 2, '.', ''),
		number_format($total_retenciones, 2, '.', ''),
		number_format($total_factura - $total_nc - $total_ingresos - $total_retenciones, 2, '.', '')
	));

	$pdf->Cell(80, 30, '----------------------------------', 2, 0, 'C');
	$pdf->Cell(100, 30, '----------------------------------', 2, 1, 'C');
	$pdf->Cell(80, -25, 'REVISADO POR', 2, 0, 'C');
	$pdf->Cell(100, -25, 'APROBADO POR', 2, 0, 'C');

	$pdf->Output("Reporte_Individual_De_Cartera " . $numero_documento . ".pdf", "D");
	unlink('../docs_temp/' . $ruc_empresa . '.jpg');
}
