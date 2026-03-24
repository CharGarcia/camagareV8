<?php
include("../conexiones/conectalogin.php");
require('../pdf/funciones_pdf.php');
require_once('../helpers/helpers.php');

$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];

$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
if (isset($_GET['action']) && isset($_GET['id']) && $action == "pdf_pedido") {
	$id_pedido = $_GET['id'];
	$datos_encabezados = mysqli_query($con, "SELECT cli.id as id_cliente, enc.id as id, enc.fecha_entrega as fecha_entrega,
enc.datecreated as datecreated, enc.hora_entrega_desde as hora_entrega_desde, enc.hora_entrega_hasta as hora_entrega_hasta, cli.nombre as cliente,
enc.responsable as responsable, enc.numero_pedido as numero_pedido, enc.observaciones_cliente as observaciones_cliente, enc.observaciones_interna as observaciones_interna,
enc.status as status, cli.direccion as direccion_cliente, usu.nombre as asesor FROM encabezado_pedido as enc INNER JOIN clientes as cli ON enc.id_cliente=cli.id 
INNER JOIN usuarios as usu ON usu.id=enc.id_usuario WHERE enc.id = '" . $id_pedido . "' ");
	$row = mysqli_fetch_assoc($datos_encabezados);
	$fecha_entrega = date('d-m-Y', strtotime($row['fecha_entrega']));
	$fecha_registro = date('d-m-Y', strtotime($row['datecreated']));
	$hora_entrega_desde = date('H:i', strtotime($row['hora_entrega_desde']));
	$hora_entrega_hasta = date('H:i', strtotime($row['hora_entrega_hasta']));
	$cliente = strtoupper($row['cliente']);
	$asesor = $row['asesor'];
	$direccion_cliente = $row['direccion_cliente'];
	$responsable = $row['responsable'];
	$numero = $row['numero_pedido'];
	$observaciones_cliente = strtoupper($row['observaciones_cliente']);
	$observaciones_interna = strtoupper($row['observaciones_interna']);

	$sql_traslado = mysqli_query($con, "SELECT * FROM responsable_traslado WHERE ruc_empresa ='" . $ruc_empresa . "' order by nombre asc");
	foreach ($sql_traslado as $resp) {
		if ($responsable == $resp['id']) {
			$responsable_final = $resp['nombre'];
		}
	}
	$detalle_pedido = mysqli_query($con, "SELECT * FROM detalle_pedido WHERE id_pedido='" . $id_pedido . "' ");

	//para buscar la imagen
	$busca_imagen = mysqli_query($con, "SELECT * FROM sucursales WHERE ruc_empresa = '" . $ruc_empresa . "' ");
	$datos_imagen = mysqli_fetch_assoc($busca_imagen);
	$imagen = "../logos_empresas/" . $datos_imagen['logo_sucursal'];

	$busca_empresa = mysqli_query($con, "SELECT * FROM empresas WHERE ruc = '" . $ruc_empresa . "' ");
	$datos_empresa = mysqli_fetch_assoc($busca_empresa);
	$nombre_empresa = $datos_empresa['nombre_comercial'];
	$html_encabezado = '<p align="center">' . $nombre_empresa . '</p>
				  <p align="center">' . utf8_decode('ORDEN DE PEDIDO') . '</p><br>';

	$pdf = new funciones_pdf('P', 'mm', 'A4'); //P
	$pdf->AliasNbPages();
	$imagen_optimizada = $pdf->imagen_optimizada($imagen, $width = 200, $height = 200);
	imagejpeg($imagen_optimizada, '../docs_temp/' . $ruc_empresa . '.jpg');
	$pdf->AddPage(); //es importante agregar esta linea para saber la pagina inicial
	$pdf->SetFont('Arial', 'B', 10); //esta tambien es importante
	$prop = array('HeaderColor' => array(213, 219, 219), 'color1' => array(253, 254, 254), 'color2' => array(253, 254, 254), 'padding' => 2);

	$pdf->detalle_html($html_encabezado);
	$pdf->Cell(50);
	$pdf->Cell(140, 5, 'DIS-PR-01-R01', 1, 1, 'L');
	$pdf->Cell(50);
	$pdf->Cell(140, 5, 'No. documento: 			001-001-' . $numero, 1, 1, 'L');
	$pdf->Cell(50);
	$pdf->Cell(140, 5, utf8_decode('Fecha emisión: 			') . $fecha_registro . " Fecha Entrega: " . $fecha_entrega . " De :" . $hora_entrega_desde . " a " . $hora_entrega_hasta, 1, 1, 'L');
	$pdf->Cell(50);
	$pdf->Cell(140, 5, 'Responsable traslado:     ' . utf8_decode($responsable_final), 1, 1, 'L');
	$pdf->Cell(50);
	$pdf->Cell(140, 5, 'Solicitado por:     ' . utf8_decode($asesor), 1, 1, 'L');
	$pdf->Cell(50);
	$pdf->MultiCell(140, 5, 'Cliente/Receptor:			' . utf8_decode($cliente), 1, 1);
	$pdf->Cell(50);
	$pdf->MultiCell(140, 5, utf8_decode('Dirección cliente:			') . utf8_decode($direccion_cliente), 1, 1);

	$pdf->Image('../docs_temp/' . $ruc_empresa . '.jpg', 20, 20, 30, 30, 'jpg', '');
	$pdf->Ln();

	$pdf->SetFont('Arial', 'B', 7); //esta tambien es importante
	$pdf->SetWidths(array(40, 95, 15, 40));
	$pdf->Row_tabla(array(utf8_decode('Código'), 'Producto', 'Cantidad', 'Observaciones'));
	while ($row_detalle = mysqli_fetch_assoc($detalle_pedido)) {
		if (is_numeric($row_detalle['codigo_producto'])) {
			$codigo = '*' . $row_detalle['codigo_producto'];
		} else {
			$codigo = $row_detalle['codigo_producto'];
		}
		$pdf->Row_tabla(array($codigo, utf8_decode($row_detalle['producto']), utf8_decode($row_detalle['cantidad']), $row_detalle['observaciones']));
	}

	$pdf->Ln();
	$pdf->SetWidths(array(190));
	$pdf->Row_tabla(array(utf8_decode('Observaciones del cliente')));
	$pdf->Row_tabla(array(utf8_decode($observaciones_cliente)));
	$pdf->SetWidths(array(190));
	$pdf->Row_tabla(array(utf8_decode('Observaciones internas')));
	$pdf->Row_tabla(array(utf8_decode($observaciones_interna)));

	$pdf->Output("PEDIDO. " . $numero . ".pdf", "D");
	unlink('../docs_temp/' . $ruc_empresa . '.jpg');
}
