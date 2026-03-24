<?php
//include("../conexiones/conectalogin.php");
require_once('../ajax/resumen_diario.php');
require('../pdf/funciones_pdf.php');
require_once('../helpers/helpers.php');

$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];


$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
$fecha = $_POST['fecha'];
$usuario = $_POST['usuario'];
if (empty($usuario)) {
	$id_usuario = $_SESSION['id_usuario'];
} else {
	$id_usuario = $usuario;
}


//para buscar la imagen
$busca_imagen = mysqli_query($con, "SELECT * FROM sucursales WHERE ruc_empresa = '" . $ruc_empresa . "' ");
$datos_imagen = mysqli_fetch_assoc($busca_imagen);
$imagen = "../logos_empresas/" . $datos_imagen['logo_sucursal'];

//para buscar el usuario
$sql_usuario = mysqli_query($con, "SELECT * FROM usuarios WHERE id = '" . $id_usuario . "' ");
$row_usuario = mysqli_fetch_assoc($sql_usuario);
$nombre_usuario = $row_usuario['nombre'];

$busca_empresa = mysqli_query($con, "SELECT * FROM empresas WHERE ruc = '" . $ruc_empresa . "' ");
$datos_empresa = mysqli_fetch_assoc($busca_empresa);
$nombre_empresa = $datos_empresa['nombre_comercial'];
$html_encabezado = '<p align="center">' . $nombre_empresa . '</p>
				  <p align="center">' . utf8_decode('RESUMEN DIARIO') . '</p><br>
				  <p align="center">Fecha: ' . utf8_decode($fecha) . '</p><br>';

$pdf = new funciones_pdf('P', 'mm', 'A4'); //P
$pdf->AliasNbPages();
$imagen_optimizada = $pdf->imagen_optimizada($imagen, $width = 200, $height = 200);
imagejpeg($imagen_optimizada, '../docs_temp/' . $ruc_empresa . '.jpg');
$pdf->AddPage(); //es importante agregar esta linea para saber la pagina inicial
$pdf->SetFont('Arial', 'B', 10); //esta tambien es importante

$pdf->detalle_html($html_encabezado);
$pdf->Image('../docs_temp/' . $ruc_empresa . '.jpg', 20, 5, 30, 30, 'jpg', '');
$pdf->Ln();

$pdf->SetFont('Arial', '', 7); //esta tambien es importante
//para ventas
$detalle_ventas = detalle_ventas($con, $fecha, $ruc_empresa, $usuario);
if ($detalle_ventas->num_rows > 0) {
	$pdf->SetWidths(array(190));
	$pdf->Row_tabla(array(utf8_decode('Detalle de facturas de venta')));
	$pdf->SetWidths(array(100, 40, 25, 25));
	$pdf->Row_tabla(array(utf8_decode('Cliente'), utf8_decode('Número'), 'Total', 'Saldo'));
	$suma_ventas = 0;
	$suma_saldo = 0;
	while ($row = mysqli_fetch_array($detalle_ventas)) {
		$suma_ventas += $row['total_factura'];
		$suma_saldo += saldo_factura($con, $ruc_empresa, $row['id_encabezado_factura']);
		$pdf->Row_tabla(array(utf8_decode($row['nombre']), $row['serie_factura'] . "-" . $row['secuencial_factura'], $row['total_factura'], saldo_factura($con, $ruc_empresa, $row['id_encabezado_factura'])));
	}
	$pdf->SetWidths(array(140, 25, 25));
	$pdf->Row_tabla(array(utf8_decode('Totales'), number_format($suma_ventas, 2, '.', ''), number_format($suma_saldo, 2, '.', '')));
}

//notas de credito
$detalle_nc = detalle_nc($con, $fecha, $ruc_empresa);
if ($detalle_nc->num_rows > 0) {
	$pdf->Ln();
	$pdf->SetWidths(array(190));
	$pdf->Row_tabla(array(utf8_decode('Detalle de notas de crédito')));
	$pdf->SetWidths(array(125, 40, 25));
	$pdf->Row_tabla(array(utf8_decode('Cliente'), utf8_decode('Número'), 'Total'));
	$suma_ventas = 0;
	while ($row = mysqli_fetch_array($detalle_nc)) {
		$suma_ventas += $row['total_nc'];
		$pdf->Row_tabla(array(utf8_decode($row['nombre']), $row['serie_nc'] . "-" . $row['secuencial_nc'], $row['total_nc']));
	}
	$pdf->SetWidths(array(125, 40, 25));
	$pdf->Row_tabla(array('', utf8_decode('Totales'), number_format($suma_ventas, 2, '.', '')));
}

//para recibos
$detalle_recibos = detalle_recibos($con, $fecha, $ruc_empresa, $usuario);
if ($detalle_recibos->num_rows > 0) {
	$pdf->Ln();
	$pdf->SetWidths(array(190));
	$pdf->Row_tabla(array(utf8_decode('Detalle de recibos de venta')));
	$pdf->SetWidths(array(100, 40, 25, 25));
	$pdf->Row_tabla(array(utf8_decode('Cliente'), utf8_decode('Número'), 'Total', 'Saldo'));
	$suma_recibos = 0;
	$suma_saldo = 0;
	while ($row = mysqli_fetch_array($detalle_recibos)) {
		$suma_recibos += $row['total_recibo'];
		$suma_saldo += saldo_recibo($con, $row['id_encabezado_recibo']);
		$pdf->Row_tabla(array(utf8_decode($row['nombre']), $row['serie_recibo'] . "-" . $row['secuencial_recibo'], $row['total_recibo'], saldo_recibo($con, $ruc_empresa, $row['id_encabezado_recibo'])));
	}
	$pdf->SetWidths(array(140, 25, 25));
	$pdf->Row_tabla(array(utf8_decode('Totales'), number_format($suma_recibos, 2, '.', ''), number_format($suma_saldo, 2, '.', '')));
}

//detalle de ingresos
$detalle_ingresos = ingresos_egresos($con, $ruc_empresa, $fecha, 'INGRESO', $usuario);
if ($detalle_ingresos->num_rows > 0) {
	$pdf->Ln();
	$pdf->SetWidths(array(190));
	$pdf->Row_tabla(array(utf8_decode('Detalle de ingresos')));
	$pdf->SetWidths(array(60, 20, 60, 30, 20));
	$pdf->Row_tabla(array(utf8_decode('Recibido de'), utf8_decode('Número'), 'Detalle', 'Forma cobro', 'Valor'));
	$suma_ingresos = 0;
	while ($row = mysqli_fetch_array($detalle_ingresos)) {
		$numero = 'Ingreso ' . $row['numero_ing_egr'];
		$cliente = $row['nombre_ing_egr'];
		$total_pago = $row['valor_forma_pago'];
		$codigo_forma_pago = $row['codigo_forma_pago'];
		$id_cuenta = $row['id_cuenta'];
		$cheque = $row['cheque'] > 0 ? $row['cheque'] . " - " : "";
		$tipo_ing_egr = $row['tipo'];
		$suma_ingresos += $row['valor_forma_pago'];

		$detalle_ing_egr = mysqli_query($con, "SELECT detalle_ing_egr as detalle FROM detalle_ingresos_egresos WHERE codigo_documento ='" . $row['codigo_documento'] . "'");
		$detalle = "";
		foreach ($detalle_ing_egr as $valor) {
			$detalle .= $valor['detalle'] . " ";
		}

		$pdf->Row_tabla(array(utf8_decode($cliente), $numero, utf8_decode($detalle), forma_pago($id_cuenta, $cheque, $codigo_forma_pago, $con, 'INGRESO', $row), $total_pago));
	}
	$pdf->SetWidths(array(170, 20));
	$pdf->Row_tabla(array(utf8_decode('Totales'), number_format($suma_ingresos, 2, '.', '')));
}

//detalle de egresos
$detalle_egresos = ingresos_egresos($con, $ruc_empresa, $fecha, 'EGRESO', $usuario);
if ($detalle_egresos->num_rows > 0) {
	$pdf->Ln();
	$pdf->SetWidths(array(190));
	$pdf->Row_tabla(array(utf8_decode('Detalle de egresos')));
	$pdf->SetWidths(array(60, 20, 60, 30, 20));
	$pdf->Row_tabla(array(utf8_decode('Pagado a'), utf8_decode('Número'), 'Detalle', 'Forma pago', 'Valor'));
	$suma_egresos = 0;
	while ($row = mysqli_fetch_array($detalle_egresos)) {
		$numero = 'Egreso ' . $row['numero_ing_egr'];
		$cliente = $row['nombre_ing_egr'];
		$total_pago = $row['valor_forma_pago'];
		$codigo_forma_pago = $row['codigo_forma_pago'];
		$id_cuenta = $row['id_cuenta'];
		$cheque = $row['cheque'] > 0 ? $row['cheque'] . " - " : "";
		$tipo_ing_egr = $row['tipo'];
		$suma_egresos += $row['valor_forma_pago'];
		$detalle_ing_egr = mysqli_query($con, "SELECT detalle_ing_egr as detalle FROM detalle_ingresos_egresos WHERE codigo_documento ='" . $row['codigo_documento'] . "'");
		$detalle = "";
		foreach ($detalle_ing_egr as $valor) {
			$detalle .= $valor['detalle'] . " ";
		}

		$pdf->Row_tabla(array(utf8_decode($cliente), $numero,  utf8_decode($detalle), forma_pago($id_cuenta, $cheque, $codigo_forma_pago, $con, 'EGRESO', $row), $total_pago));
	}
	$pdf->SetWidths(array(170, 20));
	$pdf->Row_tabla(array(utf8_decode('Totales'), number_format($suma_egresos, 2, '.', '')));
}

//resumen de cobros
$resumen_cobros = resumen_cobros($con, $ruc_empresa, $fecha, $usuario);
if ($resumen_cobros->num_rows > 0) {
	$pdf->Ln();
	$pdf->SetWidths(array(120));
	$pdf->Row_tabla(array(utf8_decode('Resumen de cobros')));
	$pdf->SetWidths(array(70, 30, 20));
	$pdf->Row_tabla(array(utf8_decode('Forma de cobro'), 'Total', 'Saldo'));
	while ($row = mysqli_fetch_array($resumen_cobros)) {
		$valor = $row['valor_forma_pago'];
		$codigo_forma_pago = $row['codigo_forma_pago'];
		$id_cuenta = $row['id_cuenta'];
		$cheque = $row['cheque'] > 0 ? $row['cheque'] . " - " : "";
		$saldo_resumen_diario = formula_calculo_saldo($con, $codigo_forma_pago);

		$valor_saldo_forma_pago = 0;
		foreach ($saldo_resumen_diario as $row_resumen_saldo) {
			$valor_saldo_forma_pago += saldo_resumen_forma_pago($con, $ruc_empresa, $fecha, $row_resumen_saldo, 'EGRESO');
		}

		$pdf->Row_tabla(array(utf8_decode(forma_pago($id_cuenta, $cheque, $codigo_forma_pago, $con, 'INGRESO', $row)), $valor, number_format($valor - $valor_saldo_forma_pago, 2, '.', '')));
	}
}

//resumen de pagos
$resumen_pagos = resumen_pagos($con, $ruc_empresa, $fecha, $usuario);
if ($resumen_pagos->num_rows > 0) {
	$pdf->Ln();
	$pdf->SetWidths(array(120));
	$pdf->Row_tabla(array(utf8_decode('Resumen de pagos')));
	$pdf->SetWidths(array(90, 30));
	$pdf->Row_tabla(array(utf8_decode('Forma de pago'), 'Total'));
	while ($row = mysqli_fetch_array($resumen_pagos)) {
		$valor = $row['valor_forma_pago'];
		$codigo_forma_pago = $row['codigo_forma_pago'];
		$id_cuenta = $row['id_cuenta'];
		$cheque = $row['cheque'] > 0 ? $row['cheque'] . " - " : "";
		$pdf->Row_tabla(array(utf8_decode(forma_pago($id_cuenta, $cheque, $codigo_forma_pago, $con, 'EGRESO', $row)), $valor));
	}
}

$pdf->Ln();
$pdf->Ln();
$pdf->SetWidths(array(80, 60, 50));
$pdf->Row_tabla(array('Realizado por ' . $nombre_usuario, 'Aprobado por', 'Fecha ' . date('d-m-Y')));


$pdf->Output("Resumen diario " . $fecha . ".pdf", "D");
unlink('../docs_temp/' . $ruc_empresa . '.jpg');
