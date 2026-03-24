<?php
include("../conexiones/conectalogin.php");
require('../pdf/funciones_pdf.php');
require('../helpers/helpers.php');
ini_set('date.timezone', 'America/Guayaquil');
$con = conenta_login();
session_start();

$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
$id_documento = base64_decode($_GET['id_documento']);
$encabezado_factura = mysqli_query($con, "SELECT * FROM encabezado_factura as enc INNER JOIN clientes as cli ON cli.id=enc.id_cliente WHERE enc.id_encabezado_factura = '" . $id_documento . "' ");
$row_encabezado = mysqli_fetch_array($encabezado_factura);
$cliente = $row_encabezado['nombre'];
$fecha_factura = date('d-m-Y', strtotime($row_encabezado['fecha_factura']));
$ruc_cliente = $row_encabezado['ruc'];
$dir_cliente = $row_encabezado['direccion'];
$tel_cliente = $row_encabezado['telefono'];
$email_cliente = $row_encabezado['email'];
$total_factura = $row_encabezado['total_factura'];
$serie_factura = $row_encabezado['serie_factura'];
$secuencial_factura = $row_encabezado['secuencial_factura'];
$numero_factura = $serie_factura . "-" . str_pad($secuencial_factura, 9, "000000000", STR_PAD_LEFT);

$sql_vendedor = mysqli_query($con, "SELECT * FROM vendedores_ventas as ven_fac INNER JOIN vendedores as ven ON ven.id_vendedor=ven_fac.id_vendedor WHERE ven_fac.id_venta = '" . $id_documento . "' ");
$row_vendedor = mysqli_fetch_array($sql_vendedor);
$vendedor = $row_vendedor['nombre'];

$busca_empresa = mysqli_query($con, "SELECT * FROM empresas WHERE ruc = '" . $ruc_empresa . "'");
$datos_empresa = mysqli_fetch_assoc($busca_empresa);
$nombre_empresa = $datos_empresa['nombre_comercial'];
$direccion_empresa = $datos_empresa['direccion'];

$html_encabezado = '<p align="center">' . utf8_decode($nombre_empresa) . '</p>
				  <p align="center">' . utf8_decode('RUC ' . substr($ruc_empresa, 0, 10) . "001") . '</p>
				  <p align="center">' . utf8_decode($direccion_empresa) . '</p>
				  <p align="center">' . 'Factura ' . $numero_factura . '</p><br>';

if ($action == "ticket_factura_venta_a2") {
	$pdf = new funciones_pdf('P', 'mm', array(80, 150)); //P-L
	$pdf->AddPage(); //es importante agregar esta linea para saber la pagina inicial
	$pdf->SetFont('Arial', 'B', 8); //esta tambien es importante
	$pdf->SetX(10);
	$pdf->detalle_html($html_encabezado);
	$pdf->SetX(5);
	$pdf->Cell(70, 6, 'Fecha: ' . utf8_decode($fecha_factura), 0, 1, 'L');
	$pdf->SetX(5);
	$pdf->Cell(70, 6, 'Cliente: ' . utf8_decode($cliente), 0, 1, 'L');
	$pdf->SetX(5);
	$pdf->Cell(70, 6, 'Ced/ruc: ' . utf8_decode($ruc_cliente), 0, 1, 'L');
	$pdf->SetX(5);
	$pdf->Cell(70, 6, 'Mail: ' . utf8_decode($email_cliente), 0, 1, 'L');

	$pdf->SetX(5);
	$pdf->SetWidths(array(15, 40, 15));
	$pdf->Row_tabla(array(utf8_decode('Cant'), 'Detalle', 'Subtotal'));
	$sutotal_a_pagar = array();
	$iva = array();
	$detalle_factura = mysqli_query($con, "SELECT * FROM cuerpo_factura WHERE serie_factura = '" . $serie_factura . "' and secuencial_factura='" . $secuencial_factura . "' and ruc_empresa='" . $ruc_empresa . "' ");
	while ($row_detalle = mysqli_fetch_assoc($detalle_factura)) {

		$tarifa_iva = $row_detalle['tarifa_iva'];
		//buscar tipos iva
		$busca_tarifa_iva = mysqli_query($con, "SELECT * FROM tarifa_iva WHERE codigo = '" . $tarifa_iva . "' ");
		$row_tarifa = mysqli_fetch_array($busca_tarifa_iva);
		$nombre_tarifa = $row_tarifa['tarifa'];
		$porcentaje_iva = $row_tarifa['porcentaje_iva'];

		$sutotal_a_pagar[] = $row_detalle['subtotal_factura'] - $row_detalle['descuento'];
		$iva[] = ($row_detalle['subtotal_factura'] - $row_detalle['descuento']) * ($porcentaje_iva / 100);

		$pdf->SetX(5);
		$pdf->Row_tabla(array($row_detalle['cantidad_factura'], utf8_decode(clear_cadena($row_detalle['nombre_producto'])), number_format($row_detalle['subtotal_factura'] - $row_detalle['descuento'], 2, '.', '')));
	}
	$pdf->SetX(40);
	$pdf->Cell(35, 6, 'Subtotal: ' . number_format(array_sum($sutotal_a_pagar), 2, '.', ''), 1, 1, 'R');
	$pdf->SetX(40);
	$pdf->Cell(35, 6, 'IVA' . $nombre_tarifa . ': ' . number_format(array_sum($iva), 2, '.', ''), 1, 1, 'R');
	$pdf->SetX(40);
	$pdf->Cell(35, 6, 'Total a pagar: ' . number_format($total_factura, 2, '.', ''), 1, 1, 'R');
	$pdf->Output("Ticket " . $numero_factura . ".pdf", "D");
}

/*
if($action == "factura_venta_a4"){
	$pdf = new funciones_pdf('P', 'mm');//P-L
	$pdf->AddPage();//es importante agregar esta linea para saber la pagina inicial
	$pdf->SetFont('Arial','B',8);//esta tambien es importante
	$pdf->SetX(20);
	$pdf->detalle_html($html_encabezado);
	$pdf->SetX(10);
	$pdf->Cell(190, 6, 'Fecha: '.utf8_decode($fecha_factura),0,1,'L');
	$pdf->SetX(10);
	$pdf->Cell(190, 6, 'Cliente: '.utf8_decode($cliente),0,1,'L');
	$pdf->SetX(10);
	$pdf->Cell(190, 6, 'Ced/ruc: '.utf8_decode($ruc_cliente),0,1,'L');
	$pdf->SetX(10);
	$pdf->Cell(190, 6, utf8_decode('Dirección: ').utf8_decode($dir_cliente),0,1,'L');
	$pdf->SetX(10);
	$pdf->Cell(190, 6, utf8_decode('Teléfono: ').utf8_decode($tel_cliente),0,1,'L');
	$pdf->SetX(10);
	$pdf->Cell(190, 6, 'Mail: '.utf8_decode($email_cliente),0,1,'L');
	$pdf->SetX(10);
	$pdf->Cell(190, 6, 'Vendedor: '.utf8_decode($vendedor),0,1,'L');
	
	$pdf->SetX(10);
	$pdf->SetWidths(array(40,120,30));
	$pdf->Row_tabla(array(utf8_decode('Cant'),'Detalle','Valor'));
	$sutotal_a_pagar=array();
	//$iva=array();
	$detalle_factura = mysqli_query($con, "SELECT * FROM cuerpo_factura WHERE id_encabezado_factura = '".$id_documento."' ");
		while ($row_detalle=mysqli_fetch_assoc($detalle_factura)){
		$sutotal_a_pagar[] = (($row_detalle['cantidad']*$row_detalle['valor_unitario'])-$row_detalle['descuento']);
		$pdf->SetX(10);
		$pdf->Row_tabla(array($row_detalle['cantidad'],utf8_decode($row_detalle['nombre_producto']),utf8_decode($row_detalle['valor_unitario'])));
		}
		
	$pdf->SetX(170);
	$pdf->Cell(30, 6, 'Total: '.number_format($total_factura,2,'.',''),1,1,'R');
	$pdf->Output("RV-".$numero_factura.".pdf","D");
	}
*/
