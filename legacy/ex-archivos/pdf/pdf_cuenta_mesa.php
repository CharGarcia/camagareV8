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
$id_mesa = $_POST['id_mesa'];
$detalle_mesa = mysqli_query($con, "SELECT * FROM mesas WHERE id_mesa = '" . $id_mesa . "' ");
$row_mesas = mysqli_fetch_array($detalle_mesa);
$nombre_mesa = $row_mesas['nombre_mesa'];

$busca_empresa = mysqli_query($con, "SELECT * FROM empresas WHERE ruc = '" . substr($ruc_empresa, 0, 10) . "001" . "'");
$datos_mesas_integra = mysqli_query($con, "SELECT mesa.id_producto as id_producto, mesa.descuento as descuento, mesa.cantidad as cantidad, ser.nombre_producto as detalle, mesa.precio as valor FROM detalle_mesas as mesa INNER JOIN productos_servicios as ser ON ser.id=mesa.id_producto WHERE mesa.id_mesa='" . $id_mesa . "' and mesa.estado='PENDIENTE' ");
$datos_mesas_precuenta = mysqli_query($con, "SELECT mesa.id_producto as id_producto, mesa.descuento as descuento, mesa.cantidad as cantidad, ser.nombre_producto as detalle, mesa.precio as valor FROM detalle_mesas as mesa INNER JOIN productos_servicios as ser ON ser.id=mesa.id_producto WHERE mesa.id_mesa='" . $id_mesa . "' and mesa.estado='PENDIENTE' ");
$datos_mesas_cocina = mysqli_query($con, "SELECT mesa.id_producto as id_producto, mesa.descuento as descuento, mesa.cantidad as cantidad, ser.nombre_producto as detalle, mesa.precio as valor FROM detalle_mesas as mesa INNER JOIN productos_servicios as ser ON ser.id=mesa.id_producto LEFT JOIN marca_producto as mar ON mar.id_producto=ser.id LEFT JOIN opciones_envio_impresion as opc ON opc.id_categoria=mar.id_marca WHERE mesa.id_mesa='" . $id_mesa . "' and mesa.estado='PENDIENTE' and opc.id_opcion='1' and opc.ruc_empresa='" . $ruc_empresa . "' ");
$datos_mesas_barra = mysqli_query($con, "SELECT mesa.id_producto as id_producto, mesa.descuento as descuento, mesa.cantidad as cantidad, ser.nombre_producto as detalle, mesa.precio as valor FROM detalle_mesas as mesa INNER JOIN productos_servicios as ser ON ser.id=mesa.id_producto LEFT JOIN marca_producto as mar ON mar.id_producto=ser.id LEFT JOIN opciones_envio_impresion as opc ON opc.id_categoria=mar.id_marca WHERE mesa.id_mesa='" . $id_mesa . "' and mesa.estado='PENDIENTE' and opc.id_opcion='2' and opc.ruc_empresa='" . $ruc_empresa . "'");

$datos_empresa = mysqli_fetch_assoc($busca_empresa);
$nombre_empresa = $datos_empresa['nombre_comercial'];
$html_encabezado = '<p align="center">' . utf8_decode($nombre_empresa) . '</p><br>';
$pdf = new funciones_pdf('P', 'mm', array(80, 250)); //P-L
$pdf->AddPage(); //es importante agregar esta linea para saber la pagina inicial
$pdf->SetFont('Arial', 'B', 8); //esta tambien es importante

if ($action == "generar_cuenta_mesa") {
	$busca_imagen = mysqli_query($con, "SELECT * FROM sucursales WHERE ruc_empresa = '" . $ruc_empresa . "' ");
	$datos_imagen = mysqli_fetch_assoc($busca_imagen);
	$imagen = "../logos_empresas/" . $datos_imagen['logo_sucursal'];

	$imagen_optimizada = $pdf->imagen_optimizada($imagen, $width = 200, $height = 200);
	imagejpeg($imagen_optimizada, '../docs_temp/' . $ruc_empresa . '.jpg');
	//$prop = array('HeaderColor'=>array(213, 219, 219),'color1'=>array(253, 254, 254),'color2'=>array(253, 254, 254),'padding'=>2);

	$pdf->Image('../docs_temp/' . $ruc_empresa . '.jpg', 5, 5, 15, 15, 'jpg', '');
	$pdf->SetX(30);
	$pdf->detalle_html($html_encabezado);
	$pdf->Cell(50, 6, 'Detalle de cuenta', 0, 1, 'R');
	$pdf->SetX(5);
	$pdf->Cell(50, 6, 'Mesa: ' . $nombre_mesa, 0, 1, 'L');

	$pdf->SetX(5);
	$pdf->Cell(70, 6, 'Nombre: ', 0, 1, 'L');
	$pdf->SetX(5);
	$pdf->Cell(70, 6, 'Ced/ruc: ', 0, 1, 'L');
	$pdf->SetX(5);
	$pdf->Cell(70, 6, 'Telf: ', 0, 1, 'L');
	$pdf->SetX(5);
	$pdf->Cell(70, 6, 'Mail: ', 0, 1, 'L');
	$pdf->Ln();

	$pdf->SetX(5);
	$pdf->SetWidths(array(10, 32, 13, 15));
	$pdf->Row_tabla(array(utf8_decode('Cant'), 'Detalle', 'Precio', 'Subtotal'));
	$sutotal_a_pagar = array();
	$iva = array();
	while ($row_mesas = mysqli_fetch_assoc($datos_mesas_precuenta)) {
		//buscar productos
		$busca_nombre_producto = mysqli_query($con, "SELECT * FROM productos_servicios WHERE id = '" . $row_mesas['id_producto'] . "' ");
		$row_productos = mysqli_fetch_array($busca_nombre_producto);
		//$nombre_producto =$row_productos['nombre_producto'];
		$tarifa_iva = $row_productos['tarifa_iva'];

		//buscar tipos iva
		$busca_tarifa_iva = mysqli_query($con, "SELECT * FROM tarifa_iva WHERE codigo = '" . $tarifa_iva . "' ");
		$row_tarifa = mysqli_fetch_array($busca_tarifa_iva);
		$nombre_tarifa = $row_tarifa['tarifa'];
		$porcentaje_iva = $row_tarifa['porcentaje_iva'];

		$sutotal_a_pagar[] = number_format((($row_mesas['cantidad'] * $row_mesas['valor']) - $row_mesas['descuento']), 2, '.', '');
		$iva[] = number_format((($row_mesas['cantidad'] * $row_mesas['valor']) - $row_mesas['descuento']) * ($porcentaje_iva / 100), 2, '.', '');

		$pdf->SetX(5);
		$pdf->Row_tabla(array(number_format($row_mesas['cantidad'], 2, '.', ''), utf8_decode($row_mesas['detalle']), number_format($row_mesas['valor'], 2, '.', ''), number_format($row_mesas['cantidad'] * $row_mesas['valor'], 2, '.', '')));
	}

	$total_a_pagar = array_sum($sutotal_a_pagar) + array_sum($iva);

	$busca_propina = mysqli_query($con, "SELECT * FROM propina_restaurante_tmp WHERE id_mesa = '" . $id_mesa . "' ");
	$row_propina = mysqli_fetch_array($busca_propina);
	$propina = $row_propina['propina'];

	$pdf->SetX(47);
	$pdf->Cell(28, 6, 'Subtotal: ' . number_format(array_sum($sutotal_a_pagar), 2, '.', ''), 1, 1, 'R');
	$pdf->SetX(47);
	$pdf->Cell(28, 6, 'IVA: ' . number_format(array_sum($iva), 2, '.', ''), 1, 1, 'R');
	$pdf->SetX(47);
	$pdf->Cell(28, 6, 'Servicio: ' . number_format($propina, 2, '.', ''), 1, 1, 'R');
	$pdf->SetX(47);
	$pdf->Cell(28, 6, 'Total: ' . number_format($total_a_pagar + $propina, 2, '.', ''), 1, 1, 'R');
	$pdf->Output("precuenta-" . $nombre_mesa . ".pdf", "D");
}

//para generar pdf de la orden integra
if ($action == "generar_orden_integra") {
	$pdf->SetX(5);
	$pdf->detalle_html($html_encabezado);
	$pdf->Cell(40, 6, 'Detalle de pedido', 0, 1, 'R');
	$pdf->SetX(5);
	$pdf->Cell(50, 6, 'Mesa: ' . $nombre_mesa, 0, 1, 'L');

	$pdf->SetX(5);
	$pdf->SetWidths(array(15, 55));
	$pdf->Row_tabla(array(utf8_decode('Cant'), 'Detalle'));
	$iva = array();
	while ($row_mesas = mysqli_fetch_assoc($datos_mesas_integra)) {
		$pdf->SetX(5);
		$pdf->Row_tabla(array($row_mesas['cantidad'], utf8_decode($row_mesas['detalle'])));
	}

	$pdf->Output("comanda-" . $nombre_mesa . ".pdf", "D");
}

//para generar pdf de la orden de cocina
if ($action == "generar_orden_cocina") {
	$pdf->SetX(5);
	$pdf->detalle_html($html_encabezado);
	$pdf->Cell(40, 6, 'Pedido a cocina', 0, 1, 'R');
	$pdf->SetX(5);
	$pdf->Cell(50, 6, 'Mesa: ' . $nombre_mesa, 0, 1, 'L');

	$pdf->SetX(5);
	$pdf->SetWidths(array(15, 55));
	$pdf->Row_tabla(array(utf8_decode('Cant'), 'Detalle'));
	$iva = array();
	while ($row_mesas = mysqli_fetch_assoc($datos_mesas_cocina)) {
		$pdf->SetX(5);
		$pdf->Row_tabla(array($row_mesas['cantidad'], utf8_decode($row_mesas['detalle'])));
	}

	$pdf->Output("cocina-" . $nombre_mesa . ".pdf", "D");
}

//para generar pdf de la orden de la barra
if ($action == "generar_orden_barra") {
	$pdf->SetX(5);
	$pdf->detalle_html($html_encabezado);
	$pdf->Cell(40, 6, 'Pedido a barra', 0, 1, 'R');
	$pdf->SetX(5);
	$pdf->Cell(50, 6, 'Mesa: ' . $nombre_mesa, 0, 1, 'L');

	$pdf->SetX(5);
	$pdf->SetWidths(array(15, 55));
	$pdf->Row_tabla(array(utf8_decode('Cant'), 'Detalle'));
	$iva = array();
	while ($row_mesas = mysqli_fetch_assoc($datos_mesas_barra)) {
		//buscar productos
		$pdf->SetX(5);
		$pdf->Row_tabla(array($row_mesas['cantidad'], utf8_decode($row_mesas['detalle'])));
	}

	$pdf->Output("barra-" . $nombre_mesa . ".pdf", "D");
}
