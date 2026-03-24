<?php
include("../conexiones/conectalogin.php");
include('../validadores/numero_letras.php');
require('../pdf/funciones_pdf.php');
ini_set('date.timezone', 'America/Guayaquil');
$con = conenta_login();
session_start();

$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
$id_recibo = base64_decode($_GET['id_documento']);
$encabezado_recibo = mysqli_query($con, "SELECT * FROM encabezado_recibo as enc INNER JOIN clientes as cli ON cli.id=enc.id_cliente WHERE enc.id_encabezado_recibo = '" . $id_recibo . "' ");
$row_encabezado = mysqli_fetch_array($encabezado_recibo);
$cliente = $row_encabezado['nombre'];
$fecha_recibo = date('d-m-Y', strtotime($row_encabezado['fecha_recibo']));
$ruc_cliente = $row_encabezado['ruc'];
$dir_cliente = $row_encabezado['direccion'];
$tel_cliente = $row_encabezado['telefono'];
$email_cliente = $row_encabezado['email'];
$total_recibo = $row_encabezado['total_recibo'];
$serie_recibo = $row_encabezado['serie_recibo'];
$secuencial_recibo = $row_encabezado['secuencial_recibo'];
$numero_recibo = $serie_recibo . "-" . str_pad($secuencial_recibo, 9, "000000000", STR_PAD_LEFT);

$sql_decimales = mysqli_query($con, "SELECT * FROM sucursales WHERE ruc_empresa = '" . $ruc_empresa . "' and serie = '" . $serie_recibo . "' ");
$row_decimales = mysqli_fetch_array($sql_decimales);
$decimal_precio = intval($row_decimales['decimal_doc'] = "" ? "2" : $row_decimales['decimal_doc']);
$decimal_cant = intval($row_decimales['decimal_cant'] = "" ? "2" : $row_decimales['decimal_cant']);
$impuestos_recibo = $row_decimales['impuestos_recibo'];
$imagen = "../logos_empresas/" . $row_decimales['logo_sucursal'];

$sql_vendedor = mysqli_query($con, "SELECT * FROM vendedores_recibos as ven_rec INNER JOIN vendedores as ven ON ven.id_vendedor=ven_rec.id_vendedor WHERE ven_rec.id_recibo = '" . $id_recibo . "' ");
$row_vendedor = mysqli_fetch_array($sql_vendedor);
$vendedor = isset($row_vendedor['nombre']) ? $row_vendedor['nombre'] : "";

$busca_empresa = mysqli_query($con, "SELECT * FROM empresas WHERE ruc = '" . $ruc_empresa . "'");
$datos_empresa = mysqli_fetch_assoc($busca_empresa);
$nombre_empresa = $datos_empresa['nombre_comercial'];
$direccion_empresa = $datos_empresa['direccion'];
$telefono_empresa = $datos_empresa['telefono'];

$html_encabezado = '<p align="center">' . utf8_decode($nombre_empresa) . '</p>
				  <p align="center">' . utf8_decode($ruc_empresa) . '</p>
				  <p align="center">' . utf8_decode($direccion_empresa) . '</p>
				  <p align="center">' . 'Recibo de venta ' . $numero_recibo . '</p>
				  <p align="center">' . 'Documento sin validez tributario' . '</p><br>';


if ($action == "recibo_sin_titulo") {
	$pdf = new funciones_pdf('P', 'mm'); //P-L
	$pdf->AliasNbPages();
	$pdf->AddPage(); //es importante agregar esta linea para saber la pagina inicial
	$pdf->SetFont('Arial', 'B', 8); //esta tambien es importante
	$pdf->SetX(20);
	$html_encabezado = '<p align="center">' . 'Recibo de venta ' . $numero_recibo . '</p><br>';
	$pdf->detalle_html($html_encabezado);
	$pdf->SetX(10);
	$pdf->Cell(190, 6, 'Fecha: ' . utf8_decode($fecha_recibo), 0, 1, 'L');
	$pdf->SetX(10);
	$pdf->Cell(190, 6, 'Cliente: ' . utf8_decode($cliente), 0, 1, 'L');
	$pdf->SetX(10);
	$pdf->Cell(190, 6, 'Ced/ruc: ' . utf8_decode($ruc_cliente), 0, 1, 'L');
	$pdf->SetX(10);
	$pdf->Cell(190, 6, utf8_decode('Dirección: ') . utf8_decode($dir_cliente), 0, 1, 'L');
	$pdf->SetX(10);
	$pdf->Cell(190, 6, utf8_decode('Teléfono: ') . utf8_decode($tel_cliente), 0, 1, 'L');
	$pdf->SetX(10);
	$pdf->Cell(190, 6, 'Mail: ' . utf8_decode($email_cliente), 0, 1, 'L');
	$pdf->SetX(10);
	$pdf->Cell(190, 6, 'Vendedor: ' . utf8_decode($vendedor), 0, 1, 'L');

	$pdf->SetX(10);
	$pdf->SetWidths(array(20, 100, 40, 30));
	$pdf->Row_tabla(array(utf8_decode('Cant'), utf8_decode('Descripción'), utf8_decode('Detalle'), 'Valor'));
	$sutotal_a_pagar = array();
	$iva = array();
	$total_iva = 0;
	$detalle_recibo = mysqli_query($con, "SELECT * FROM cuerpo_recibo WHERE id_encabezado_recibo = '" . $id_recibo . "' ");
	while ($row_detalle = mysqli_fetch_assoc($detalle_recibo)) {
		$tarifa_iva = $row_detalle['tarifa_iva'];
		if ($impuestos_recibo == '2') {
			$busca_tarifa_iva = mysqli_query($con, "SELECT * FROM tarifa_iva WHERE codigo = '" . $tarifa_iva . "' ");
			$row_tarifa = mysqli_fetch_array($busca_tarifa_iva);
			$nombre_tarifa = $row_tarifa['tarifa'];
			$porcentaje_iva = $row_tarifa['porcentaje_iva'];
			$iva[] = (($row_detalle['cantidad'] * $row_detalle['valor_unitario']) - $row_detalle['descuento']) * ($porcentaje_iva / 100);
		}
		$total_iva = array_sum($iva);
		$sutotal_a_pagar[] = (($row_detalle['cantidad'] * $row_detalle['valor_unitario']) - $row_detalle['descuento']);
		$pdf->SetX(10);
		$pdf->Row_tabla(array(number_format($row_detalle['cantidad'], 2, '.', ''), utf8_decode($row_detalle['nombre_producto']), utf8_decode($row_detalle['adicional']), number_format(abs($row_detalle['valor_unitario'] * $row_detalle['cantidad'] - $row_detalle['descuento']), 2, '.', '')));
	}

	if ($impuestos_recibo == '2') {
		$pdf->SetX(170);
		$pdf->Cell(30, 6, 'IVA: ' . number_format($total_iva, 2, '.', ''), 1, 1, 'R');
	}

	$pdf->SetX(170);
	$pdf->Cell(30, 6, 'Total: ' . number_format($total_recibo, 2, '.', ''), 1, 1, 'R');

	$pdf->Ln();
	$pdf->SetX(10);
	$pdf->SetWidths(array(130));
	$pdf->Row_tabla(array(utf8_decode('Información adicional')));
	$pdf->SetWidths(array(30, 100));
	$pdf->Row_tabla(array(utf8_decode('Concepto'), utf8_decode('Descripción')));
	$detalle_adicional_recibo = mysqli_query($con, "SELECT * FROM detalle_adicional_recibo WHERE id_encabezado_recibo = '" . $id_recibo . "' ");
	while ($row_detalle = mysqli_fetch_assoc($detalle_adicional_recibo)) {
		if (!in_array($row_detalle['adicional_concepto'], ['Email', 'Dirección', 'Teléfono'])) {
			$pdf->Row_tabla(array(
				utf8_decode($row_detalle['adicional_concepto']),
				" " . utf8_decode($row_detalle['adicional_descripcion'])
			));
		}
	}

	$pdf->Output("RV-" . $numero_recibo . ".pdf", "D");
}

if ($action == "recibo_venta_a2") {
	$pdf = new funciones_pdf('P', 'mm', array(80, 250)); //P-L
	$pdf->AliasNbPages();
	$pdf->AddPage(); //es importante agregar esta linea para saber la pagina inicial
	$pdf->SetFont('Arial', 'B', 8); //esta tambien es importante
	$pdf->SetX(10);
	$pdf->detalle_html($html_encabezado);
	$pdf->SetX(5);
	$pdf->Cell(70, 6, 'Fecha: ' . utf8_decode($fecha_recibo), 0, 1, 'L');
	$pdf->SetX(5);
	$pdf->Cell(70, 6, 'Cliente: ' . utf8_decode($cliente), 0, 1, 'L');
	$pdf->SetX(5);
	$pdf->Cell(70, 6, 'Ced/ruc: ' . utf8_decode($ruc_cliente), 0, 1, 'L');
	$pdf->SetX(5);
	$pdf->Cell(70, 6, 'Mail: ' . utf8_decode($email_cliente), 0, 1, 'L');

	$pdf->SetX(5);
	$pdf->SetWidths(array(15, 40, 15));
	$pdf->Row_tabla(array(utf8_decode('Cant'), 'Detalle', 'Valor'));
	$sutotal_a_pagar = array();
	$iva = array();
	$total_iva = 0;
	$detalle_recibo = mysqli_query($con, "SELECT * FROM cuerpo_recibo WHERE id_encabezado_recibo = '" . $id_recibo . "' ");
	while ($row_detalle = mysqli_fetch_assoc($detalle_recibo)) {

		$tarifa_iva = $row_detalle['tarifa_iva'];
		if ($impuestos_recibo == '2') {
			$busca_tarifa_iva = mysqli_query($con, "SELECT * FROM tarifa_iva WHERE codigo = '" . $tarifa_iva . "' ");
			$row_tarifa = mysqli_fetch_array($busca_tarifa_iva);
			$nombre_tarifa = $row_tarifa['tarifa'];
			$porcentaje_iva = $row_tarifa['porcentaje_iva'];
			$iva[] = (($row_detalle['cantidad'] * $row_detalle['valor_unitario']) - $row_detalle['descuento']) * ($porcentaje_iva / 100);
		}
		$total_iva = array_sum($iva);
		$sutotal_a_pagar[] = (($row_detalle['cantidad'] * $row_detalle['valor_unitario']) - $row_detalle['descuento']);
		$pdf->SetX(5);
		$pdf->Row_tabla(array(number_format($row_detalle['cantidad'], 2, '.', ''), utf8_decode($row_detalle['nombre_producto']), number_format(abs($row_detalle['valor_unitario'] * $row_detalle['cantidad'] - $row_detalle['descuento']), 2, '.', '')));
	}

	if ($impuestos_recibo == '2') {
		$pdf->SetX(50);
		$pdf->Cell(25, 6, 'IVA: ' . number_format($total_iva, 2, '.', ''), 1, 1, 'R');
	}

	$pdf->SetX(50);
	$pdf->Cell(25, 6, 'Total: ' . number_format($total_recibo, 2, '.', ''), 1, 1, 'R');
	$pdf->CoverPageNumber();
	$pdf->Output("RV-" . $numero_recibo . ".pdf", "D");
}


if ($action == "recibo_venta_a4") {
	$pdf = new funciones_pdf('P', 'mm'); //P-L
	$pdf->AliasNbPages();
	$pdf->AddPage(); //es importante agregar esta linea para saber la pagina inicial
	$pdf->SetFont('Arial', 'B', 8); //esta tambien es importante
	$pdf->SetX(20);
	$pdf->detalle_html($html_encabezado);
	$pdf->SetX(10);
	$pdf->Cell(190, 6, 'Fecha: ' . utf8_decode($fecha_recibo), 0, 1, 'L');
	$pdf->SetX(10);
	$pdf->Cell(190, 6, 'Cliente: ' . utf8_decode($cliente), 0, 1, 'L');
	$pdf->SetX(10);
	$pdf->Cell(190, 6, 'Ced/ruc: ' . utf8_decode($ruc_cliente), 0, 1, 'L');
	$pdf->SetX(10);
	$pdf->Cell(190, 6, utf8_decode('Dirección: ') . utf8_decode($dir_cliente), 0, 1, 'L');
	$pdf->SetX(10);
	$pdf->Cell(190, 6, utf8_decode('Teléfono: ') . utf8_decode($tel_cliente), 0, 1, 'L');
	$pdf->SetX(10);
	$pdf->Cell(190, 6, 'Mail: ' . utf8_decode($email_cliente), 0, 1, 'L');
	$pdf->SetX(10);
	$pdf->Cell(190, 6, 'Vendedor: ' . utf8_decode($vendedor), 0, 1, 'L');

	$pdf->SetX(10);
	$pdf->SetWidths(array(40, 120, 30));
	$pdf->Row_tabla(array(utf8_decode('Cant'), 'Detalle', 'Valor'));
	$sutotal_a_pagar = array();
	$iva = array();
	$total_iva = 0;
	$detalle_recibo = mysqli_query($con, "SELECT * FROM cuerpo_recibo WHERE id_encabezado_recibo = '" . $id_recibo . "' ");
	while ($row_detalle = mysqli_fetch_assoc($detalle_recibo)) {
		$tarifa_iva = $row_detalle['tarifa_iva'];
		if ($impuestos_recibo == '2') {
			$busca_tarifa_iva = mysqli_query($con, "SELECT * FROM tarifa_iva WHERE codigo = '" . $tarifa_iva . "' ");
			$row_tarifa = mysqli_fetch_array($busca_tarifa_iva);
			$nombre_tarifa = $row_tarifa['tarifa'];
			$porcentaje_iva = $row_tarifa['porcentaje_iva'];
			$iva[] = (($row_detalle['cantidad'] * $row_detalle['valor_unitario']) - $row_detalle['descuento']) * ($porcentaje_iva / 100);
		}
		$total_iva = array_sum($iva);
		$sutotal_a_pagar[] = (($row_detalle['cantidad'] * $row_detalle['valor_unitario']) - $row_detalle['descuento']);
		$pdf->SetX(10);
		$pdf->Row_tabla(array(number_format($row_detalle['cantidad'], 2, '.', ''), utf8_decode($row_detalle['nombre_producto']), number_format(abs($row_detalle['valor_unitario'] * $row_detalle['cantidad'] - $row_detalle['descuento']), 2, '.', '')));
	}

	if ($impuestos_recibo == '2') {
		$pdf->SetX(170);
		$pdf->Cell(30, 6, 'IVA: ' . number_format($total_iva, 2, '.', ''), 1, 1, 'R');
	}

	$pdf->SetX(170);
	$pdf->Cell(30, 6, 'Total: ' . number_format($total_recibo, 2, '.', ''), 1, 1, 'R');
	$pdf->CoverPageNumber();
	$pdf->Output("RV-" . $numero_recibo . ".pdf", "D");
}

if ($action == "recibo_venta_a5") {
	$pdf = new funciones_pdf('P', 'mm', 'A5'); //P-L
	$pdf->AliasNbPages();
	$imagen_optimizada = $pdf->imagen_optimizada($imagen, $width = 500, $height = 500);
	imagejpeg($imagen_optimizada, '../docs_temp/' . $ruc_empresa . '.jpg');
	$pdf->AddPage(); //es importante agregar esta linea para saber la pagina inicial
	$pdf->SetFont('Arial', 'B', 8); //esta tambien es importante
	$pdf->SetX(5);
	$pdf->Image('../docs_temp/' . $ruc_empresa . '.jpg', 10, 10, 40, 25, 'jpg', '');

	$pdf->SetX(20);
	$pdf->detalle_html($html_encabezado);
	$pdf->SetX(10);
	$pdf->Cell(190, 6, 'Fecha: ' . utf8_decode($fecha_recibo), 0, 1, 'L');
	$pdf->SetX(10);
	$pdf->Cell(190, 6, 'Cliente: ' . utf8_decode($cliente), 0, 1, 'L');
	$pdf->SetX(10);
	$pdf->Cell(190, 6, 'Ced/ruc: ' . utf8_decode($ruc_cliente), 0, 1, 'L');
	$pdf->SetX(10);
	$pdf->Cell(190, 6, utf8_decode('Dirección: ') . utf8_decode($dir_cliente), 0, 1, 'L');
	$pdf->SetX(10);
	$pdf->Cell(190, 6, utf8_decode('Teléfono: ') . utf8_decode($tel_cliente), 0, 1, 'L');
	$pdf->SetX(10);
	$pdf->Cell(190, 6, 'Mail: ' . utf8_decode($email_cliente), 0, 1, 'L');
	//	$pdf->SetX(10);
	//	$pdf->Cell(190, 6, 'Vendedor: ' . utf8_decode($vendedor), 0, 1, 'L');

	$pdf->SetX(10);
	$pdf->SetWidths(array(20, 90, 20));
	$pdf->Row_tabla(array(utf8_decode('Cant'), 'Detalle', 'Valor'));
	$sutotal_a_pagar = array();
	$iva = array();
	$total_iva = 0;
	$detalle_recibo = mysqli_query($con, "SELECT * FROM cuerpo_recibo WHERE id_encabezado_recibo = '" . $id_recibo . "' ");
	while ($row_detalle = mysqli_fetch_assoc($detalle_recibo)) {
		$tarifa_iva = $row_detalle['tarifa_iva'];
		if ($impuestos_recibo == '2') {
			$busca_tarifa_iva = mysqli_query($con, "SELECT * FROM tarifa_iva WHERE codigo = '" . $tarifa_iva . "' ");
			$row_tarifa = mysqli_fetch_array($busca_tarifa_iva);
			$nombre_tarifa = $row_tarifa['tarifa'];
			$porcentaje_iva = $row_tarifa['porcentaje_iva'];
			$iva[] = (($row_detalle['cantidad'] * $row_detalle['valor_unitario']) - $row_detalle['descuento']) * ($porcentaje_iva / 100);
		}
		$total_iva = array_sum($iva);
		$sutotal_a_pagar[] = (($row_detalle['cantidad'] * $row_detalle['valor_unitario']) - $row_detalle['descuento']);
		$pdf->SetX(10);
		$pdf->Row_tabla(array(number_format($row_detalle['cantidad'], 2, '.', ''), utf8_decode($row_detalle['nombre_producto']), number_format(abs($row_detalle['valor_unitario'] * $row_detalle['cantidad'] - $row_detalle['descuento']), 2, '.', '')));
	}

	//para el detalle de adicionales
	$pdf->Ln();
	$pdf->SetX(10);
	$pdf->SetWidths(array(110));
	$pdf->Row_tabla(array(utf8_decode('Información adicional')));
	$pdf->SetWidths(array(30, 80));
	$pdf->Row_tabla(array(utf8_decode('Concepto'), utf8_decode('Descripción')));
	$detalle_adicional_recibo = mysqli_query($con, "SELECT * FROM detalle_adicional_recibo WHERE id_encabezado_recibo = '" . $id_recibo . "' ");
	while ($row_detalle = mysqli_fetch_assoc($detalle_adicional_recibo)) {
		if (!in_array($row_detalle['adicional_concepto'], ['Email', 'Dirección', 'Teléfono'])) {
			$pdf->Row_tabla(array(
				utf8_decode($row_detalle['adicional_concepto']),
				" " . utf8_decode($row_detalle['adicional_descripcion'])
			));
		}
	}

	if ($impuestos_recibo == '2') {
		$pdf->SetX(120);
		$pdf->Cell(20, 6, 'IVA: ' . number_format($total_iva, 2, '.', ''), 1, 1, 'R');
	}

	$pdf->SetX(120);
	$pdf->Cell(20, 6, 'Total: ' . number_format($total_recibo, 2, '.', ''), 1, 1, 'R');


	$pdf->SetX(10);
	$pdf->Cell(30, 6, 'Recibi conforme ', 1, 1, 'C');
	$pdf->CoverPageNumber();
	$pdf->Output("RV-" . $numero_recibo . ".pdf", "D");
}

if ($action == "recibo_venta_condominio") {
	$pdf = new funciones_pdf('P', 'mm', array(148, 210)); //P-L
	$pdf->AliasNbPages();
	$imagen_optimizada = $pdf->imagen_optimizada($imagen, $width = 200, $height = 200);
	imagejpeg($imagen_optimizada, '../docs_temp/' . $ruc_empresa . '.jpg');
	$pdf->AddPage(); //es importante agregar esta linea para saber la pagina inicial
	$pdf->SetFont('Arial', 'B', 8); //esta tambien es importante
	$pdf->SetX(5);
	$pdf->Image('../docs_temp/' . $ruc_empresa . '.jpg', 10, 10, 15, 15, 'jpg', '');

	$pdf->SetX(15);
	$pdf->Cell(80);
	$x = $pdf->GetX();
	$pdf->Cell(40, 5, $ruc_empresa, 1, 1, 'C');
	$pdf->SetX($x);
	$pdf->Cell(40, 3, 'RECIBO DE CAJA', 1, 1, 'C');
	$pdf->SetX($x);
	$pdf->Cell(40, 5, 'Serie: ' . $numero_recibo, 1, 1, 'C');

	$pdf->Ln();
	$pdf->SetX(10);
	$pdf->Cell(50, 5, utf8_decode('Teléfono: ') . $telefono_empresa, 0, 1, 'L');
	$pdf->SetX(10);
	$pdf->Cell(50, 5, utf8_decode('Dirección: ') . utf8_decode($direccion_empresa), 0, 1, 'L');
	$pdf->Ln();

	$pdf->SetX(10);
	$x = $pdf->GetX();
	$pdf->Cell(130, 4, 'Fecha: ' . utf8_decode($fecha_recibo), 1, 1, 'L');
	$pdf->SetX($x);
	$pdf->Cell(130, 4, utf8_decode('Señor (es): ') . utf8_decode($cliente), 1, 1, 'L');
	$pdf->SetX($x);
	$pdf->Cell(130, 4, utf8_decode('Dirección: ') . utf8_decode($dir_cliente), 1, 1, 'L');
	$pdf->SetX($x);
	$pdf->Cell(130, 4, utf8_decode('Ruc / Cédula: ') . utf8_decode($ruc_cliente), 1, 1, 'L');
	$pdf->SetX($x);
	$pdf->Cell(130, 4, utf8_decode('Teléfono: ') . utf8_decode($tel_cliente), 1, 1, 'L');
	$pdf->Ln();

	$pdf->SetWidths(array(10, 60, 30, 15, 15));
	$pdf->Row_tabla(array(utf8_decode('Cant'), utf8_decode('Descripción'), 'Detalle', 'V/U', 'V. Total'));
	$sutotal_a_pagar = array();
	$iva = array();
	$total_iva = 0;
	$detalle_recibo = mysqli_query($con, "SELECT * FROM cuerpo_recibo WHERE id_encabezado_recibo = '" . $id_recibo . "' ");
	while ($row_detalle = mysqli_fetch_assoc($detalle_recibo)) {
		$tarifa_iva = $row_detalle['tarifa_iva'];
		if ($impuestos_recibo == '2') {
			$busca_tarifa_iva = mysqli_query($con, "SELECT * FROM tarifa_iva WHERE codigo = '" . $tarifa_iva . "' ");
			$row_tarifa = mysqli_fetch_array($busca_tarifa_iva);
			$nombre_tarifa = $row_tarifa['tarifa'];
			$porcentaje_iva = $row_tarifa['porcentaje_iva'];
			$iva[] = (($row_detalle['cantidad'] * $row_detalle['valor_unitario']) - $row_detalle['descuento']) * ($porcentaje_iva / 100);
		}
		$total_iva = array_sum($iva);
		$sutotal_a_pagar[] = (($row_detalle['cantidad'] * $row_detalle['valor_unitario']) - $row_detalle['descuento']);
		$pdf->SetX(10);
		$pdf->Row_tabla(array(number_format($row_detalle['cantidad'], 2, '.', ''), utf8_decode($row_detalle['nombre_producto']), utf8_decode($row_detalle['adicional']), number_format($row_detalle['valor_unitario'], 2, '.', ''), number_format(($row_detalle['valor_unitario'] * $row_detalle['cantidad'] - $row_detalle['descuento']), 2, '.', '')));
	}


	$pdf->Ln();
	$pdf->SetX(10);
	$pdf->SetWidths(array(130));
	$pdf->Row_tabla(array(utf8_decode('Información adicional')));
	$pdf->SetWidths(array(30, 100));
	$pdf->Row_tabla(array(utf8_decode('Concepto'), utf8_decode('Descripción')));
	$detalle_adicional_recibo = mysqli_query($con, "SELECT * FROM detalle_adicional_recibo WHERE id_encabezado_recibo = '" . $id_recibo . "' ");
	while ($row_detalle = mysqli_fetch_assoc($detalle_adicional_recibo)) {
		if (!in_array($row_detalle['adicional_concepto'], ['Email', 'Dirección', 'Teléfono'])) {
			$pdf->Row_tabla(array(
				utf8_decode($row_detalle['adicional_concepto']),
				" " . utf8_decode($row_detalle['adicional_descripcion'])
			));
		}
	}
	$pdf->Ln();
	$pdf->Cell(90);
	$pdf->Cell(25, 6, 'Subtotal: ', 1, 0, 'L');
	$pdf->Cell(15, 6, number_format($total_recibo, 2, '.', ''), 1, 1, 'R');
	$pdf->Cell(90);
	$pdf->Cell(25, 6, 'Intereses mora: ', 1, 0, 'L');
	$pdf->Cell(15, 6, '', 1, 1, 'R');
	$pdf->Cell(90);
	$pdf->Cell(25, 6, 'Multa: ', 1, 0, 'L');
	$pdf->Cell(15, 6, '', 1, 1, 'R');

	$pdf->Cell(45, 6, 'FIRMA AUTORIZADA', 1, 0, 'C');
	$pdf->Cell(45, 6, 'FIRMA CLIENTE', 1, 0, 'C');
	$pdf->Cell(25, 6, 'Total: ', 1, 0, 'L');
	$pdf->Cell(15, 6, number_format($total_recibo, 2, '.', ''), 1, 1, 'R');

	$pdf->CoverPageNumber();
	$pdf->Output("RV-" . $numero_recibo . ".pdf", "D");
}

if ($action == "nota_entrega") {
	$detalle_adicional = mysqli_query($con, "SELECT * FROM detalle_adicional_recibo WHERE id_encabezado_recibo = '" . $id_recibo . "' order by id desc ");
	$pdf = new funciones_pdf('P', 'mm'); //P-L
	$pdf->AliasNbPages();
	$pdf->AddPage(); //es importante agregar esta linea para saber la pagina inicial
	$pdf->SetFont('Arial', 'B', 10); //esta tambien es importante
	$pdf->SetX(20);
	$pdf->detalle_html('<p align="center">NOTA DE ENTREGA No ' . $numero_recibo . '</p>');
	$pdf->Ln();
	$pdf->SetFont('Arial', '', 8); //esta tambien es importante
	$pdf->SetX(10);
	$pdf->Cell(190, 6, 'Fecha: ' . utf8_decode($fecha_recibo), 0, 1, 'L');
	$pdf->SetX(10);
	$pdf->Cell(190, 6, 'Cliente: ' . utf8_decode($cliente), 0, 1, 'L');
	$pdf->SetX(10);
	$pdf->Cell(190, 6, 'Ced/ruc: ' . utf8_decode($ruc_cliente), 0, 1, 'L');
	$pdf->SetX(10);
	$pdf->Cell(190, 6, 'Vendedor: ' . utf8_decode($vendedor), 0, 1, 'L');

	$pdf->SetX(10);
	$pdf->SetWidths(array(190));
	while ($row_detalle = mysqli_fetch_assoc($detalle_adicional)) {
		$pdf->Row_tabla(array(utf8_decode($row_detalle['adicional_concepto']) . ": " . utf8_decode($row_detalle['adicional_descripcion'])));
	}

	$pdf->Ln();
	$pdf->SetWidths(array(30, 70, 18, 18, 17, 17, 20));
	$pdf->Row_tabla(array(utf8_decode('Código'), 'Detalle', 'Cantidad', 'Precio', 'Descuento', '% Desc', 'Subtotal'));
	$sutotal_a_pagar = array();
	$iva = array();
	$total_iva = 0;
	$suma_cantidad = 0;
	$detalle_recibo = mysqli_query($con, "SELECT * FROM cuerpo_recibo AS cue_rec INNER JOIN productos_servicios AS pro 
		On pro.id=cue_rec.id_producto WHERE cue_rec.id_encabezado_recibo = '" . $id_recibo . "' ");

	while ($row_detalle = mysqli_fetch_assoc($detalle_recibo)) {
		$suma_cantidad += $row_detalle['cantidad'];
		$tarifa_iva = $row_detalle['tarifa_iva'];
		if ($impuestos_recibo == '2') {
			$busca_tarifa_iva = mysqli_query($con, "SELECT * FROM tarifa_iva WHERE codigo = '" . $tarifa_iva . "' ");
			$row_tarifa = mysqli_fetch_array($busca_tarifa_iva);
			$nombre_tarifa = $row_tarifa['tarifa'];
			$porcentaje_iva = $row_tarifa['porcentaje_iva'];
			$iva[] = (($row_detalle['cantidad'] * $row_detalle['valor_unitario']) - $row_detalle['descuento']) * ($porcentaje_iva / 100);
		}
		$total_iva = array_sum($iva);
		$sutotal_a_pagar[] = (($row_detalle['cantidad'] * $row_detalle['valor_unitario']) - $row_detalle['descuento']);
		$pdf->SetX(10);
		$descuento = number_format($row_detalle['descuento'], 2, '.', '');
		$subtotal = number_format($row_detalle['valor_unitario'] * $row_detalle['cantidad'], 2, '.', '');
		$porcentaje_descuento = number_format(($descuento / $subtotal) * 100, 2, '.', '');
		$pdf->Row_tabla(array($row_detalle['codigo_auxiliar'], utf8_decode($row_detalle['nombre_producto']), number_format($row_detalle['cantidad'], 2, '.', ''), number_format($row_detalle['valor_unitario'], 2, '.', ''), number_format($row_detalle['descuento'], 2, '.', ''), $porcentaje_descuento, number_format(abs($row_detalle['valor_unitario'] * $row_detalle['cantidad'] - $row_detalle['descuento']), 2, '.', '')));
	}

	$pdf->SetX(110);
	$pdf->Cell(20, 6, 'TOTAL: ' . number_format($suma_cantidad, 2, '.', ''), 0, 1, 'R');

	$pdf->SetY(-45);
	$pdf->Line(10, 250, 200, 250);
	$pdf->Line(30, 270, 80, 270);
	$pdf->Line(90, 270, 140, 270);
	$cantidad_letras = num_letras($total_recibo);
	$pdf->SetX(10);
	$pdf->MultiCell(200, 6, 'TOTAL: ' . strtoupper(utf8_decode($cantidad_letras)), 0, 1);
	$pdf->SetX(170);
	$pdf->Cell(25, 6, 'Subtotal: ' . number_format(array_sum($sutotal_a_pagar), 2, '.', ''), 1, 1, 'R');
	if ($impuestos_recibo == '2') {
		$pdf->SetX(170);
		$pdf->Cell(25, 6, 'IVA: ' . number_format($total_iva, 2, '.', ''), 1, 1, 'R');
	}

	$pdf->SetX(170);
	$pdf->Cell(25, 6, 'Total: ' . number_format($total_recibo, 2, '.', ''), 1, 1, 'R');

	$pdf->SetY(-28);
	$pdf->SetX(50);
	$pdf->Cell(100, 6, 'Recibi conforme                                Entregue Conforme', 0, 0, 'L');

	$pdf->Output("NotaEntrega-" . $numero_recibo . ".pdf", "D");
}
