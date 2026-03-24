<?php
//include("../conexiones/conectalogin.php");
require('../pdf/funciones_pdf.php');
include("../ajax/buscar_existencias_consignacion.php");
//require_once('../helpers/helpers.php');


//$db = new db();
$con = conenta_login();
//session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario_actual = $_SESSION['id_usuario'];
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
if (isset($_GET['action']) && isset($_GET['codigo_unico'])) {
	$codigo_unico = $_GET['codigo_unico'];
	$busca_encabezado = mysqli_query($con, "SELECT enc_con.fecha_consignacion as fecha_consignacion, 
enc_con.fecha_registro as fecha_registro, enc_con.numero_consignacion as numero_consignacion,
cli.nombre as nombre, usu.nombre as usuario, enc_con.observaciones as observaciones, cli.direccion as direccion, 
cli.telefono as telefono, enc_con.punto_partida as punto_partida, enc_con.punto_llegada as punto_llegada, 
enc_con.fecha_entrega as fecha_entrega, enc_con.hora_entrega_desde as hora_entrega_desde, 
enc_con.hora_entrega_hasta as hora_entrega_hasta, enc_con.traslado_por as traslado_por, ven.nombre as vendedor 
FROM encabezado_consignacion as enc_con 
INNER JOIN clientes as cli ON enc_con.id_cli_pro=cli.id INNER JOIN usuarios as usu ON usu.id=enc_con.id_usuario 
LEFT JOIN vendedores as ven ON ven.id_vendedor= enc_con.responsable
WHERE enc_con.codigo_unico = '" . $codigo_unico . "' ");
	$row_encabezados = mysqli_fetch_array($busca_encabezado);
	$fecha_emision = date("d-m-Y", strtotime($row_encabezados['fecha_consignacion']));
	$fecha_registro = date("H:i:s", strtotime($row_encabezados['fecha_registro']));
	$numero_consignacion = $row_encabezados['numero_consignacion'];
	$cliente = $row_encabezados['nombre'];
	$direccion_cliente = $row_encabezados['direccion'];
	$telefono_cliente = $row_encabezados['telefono'];
	$observaciones = $row_encabezados['observaciones'];
	$punto_partida = $row_encabezados['punto_partida'];
	$punto_llegada = $row_encabezados['punto_llegada'];
	$vendedor = $row_encabezados['vendedor'];
	$usuario = $row_encabezados['usuario'];
	$fecha_entrega = $row_encabezados['fecha_entrega'] == 0 ? "___/___/_____" : date("d-m-Y", strtotime($row_encabezados['fecha_entrega']));
	$hora_entrega = " desde: " . date("H:i", strtotime($row_encabezados['hora_entrega_desde'])) . " hasta: " . date("H:i", strtotime($row_encabezados['hora_entrega_hasta']));
	$traslado_por = $row_encabezados['traslado_por'] == 0 ? "1" : $row_encabezados['traslado_por'];

	$sql_traslado = mysqli_query($con, "SELECT * FROM responsable_traslado WHERE ruc_empresa ='" . $ruc_empresa . "' order by nombre asc");

	foreach ($sql_traslado as $resp) {
		if ($traslado_por == $resp['id']) {
			$traslado_por_final = utf8_decode($resp['nombre']);
		}
	}

	//totl cantidades
	$busca_totales = mysqli_query($con, "SELECT sum(cant_consignacion) as cantidad FROM detalle_consignacion WHERE codigo_unico = '" . $codigo_unico . "' ");
	$row_totales = mysqli_fetch_array($busca_totales);
	$total_consignadas = $row_totales['cantidad'];

	//para buscar la imagen
	$busca_imagen = mysqli_query($con, "SELECT * FROM sucursales WHERE ruc_empresa = '" . $ruc_empresa . "' ");
	$datos_imagen = mysqli_fetch_assoc($busca_imagen);
	$imagen = "../logos_empresas/" . $datos_imagen['logo_sucursal'];

	$busca_empresa = mysqli_query($con, "SELECT * FROM empresas WHERE ruc = '" . $ruc_empresa . "' ");
	$datos_empresa = mysqli_fetch_assoc($busca_empresa);
	$nombre_empresa = $datos_empresa['nombre_comercial'];
	$html_encabezado = '<p align="center">' . $nombre_empresa . '</p>
				  <p align="center">' . utf8_decode('PRODUCTOS EN CONSIGNACIÓN / DOCUMENTO DE TRASLADO') . '</p><br>';

	$detalle_consignacion = mysqli_query($con, "SELECT det_con.id_producto as id_producto, det_con.codigo_producto as codigo_producto, 
det_con.nombre_producto as nombre_producto, bod.nombre_bodega as bodega, det_con.lote as lote, det_con.nup as nup, FORMAT(det_con.cant_consignacion,0) as cant_consignacion, 
round(det_con.precio,2) as precio
FROM detalle_consignacion as det_con INNER JOIN bodega as bod ON bod.id_bodega=det_con.id_bodega WHERE det_con.codigo_unico = '" . $codigo_unico . "' ");

	$pdf = new funciones_pdf('P', 'mm', 'A4'); //P
	$pdf->AliasNbPages();
	$imagen_optimizada = $pdf->imagen_optimizada($imagen, $width = 200, $height = 200);
	imagejpeg($imagen_optimizada, '../docs_temp/' . $ruc_empresa . '.jpg');
	$pdf->AddPage(); //es importante agregar esta linea para saber la pagina inicial
	$pdf->SetFont('Arial', 'B', 10); //esta tambien es importante
	$prop = array('HeaderColor' => array(213, 219, 219), 'color1' => array(253, 254, 254), 'color2' => array(253, 254, 254), 'padding' => 2);

	$pdf->detalle_html($html_encabezado);
	$pdf->Cell(50);
	$pdf->Cell(140, 5, 'DIS-PR-01-R02', 1, 1, 'L');
	$pdf->Cell(50);
	$pdf->Cell(140, 5, 'No. documento: 001-001-' . $numero_consignacion, 1, 1, 'L');
	$pdf->Cell(50);
	$pdf->Cell(140, 5, utf8_decode('Fecha emisión: ') . $fecha_emision . " Hora:" . $fecha_registro, 1, 1, 'L');
	$pdf->Cell(50);
	$pdf->Cell(140, 5, 'Punto de partida: ' . utf8_decode($punto_partida), 1, 1, 'L');
	$pdf->Cell(50);
	$pdf->Cell(140, 5, 'Punto de llegada: ' . utf8_decode($punto_llegada), 1, 1, 'L');
	$pdf->Cell(50);
	$pdf->Cell(140, 5, 'Asesor: ' . utf8_decode($vendedor), 1, 1, 'L');
	$pdf->Cell(50);
	$pdf->MultiCell(140, 5, 'Cliente/Receptor: ' . utf8_decode($cliente), 1, 1);
	$pdf->Cell(50);
	$pdf->MultiCell(140, 5, utf8_decode('Dirección cliente: ') . utf8_decode($direccion_cliente), 1, 1);
	$pdf->Cell(50);
	$pdf->MultiCell(140, 5, utf8_decode('Teléfono cliente: ') . utf8_decode($telefono_cliente), 1, 1);
	$pdf->Cell(50);
	$pdf->Cell(140, 5, 'Fecha entrega: ' . $fecha_entrega . " Hora" . $hora_entrega, 1, 1, 'L');
	//$pdf->Cell(140,5,'Fecha entrega: '.$fecha_entrega." Hora entrega: ".$hora_entrega. " Responsable traslado: ".$traslado_por_final,1,1,'L');

	$pdf->Image('../docs_temp/' . $ruc_empresa . '.jpg', 20, 20, 30, 30, 'jpg', '');

	$pdf->Ln();
	$pdf->SetFont('Arial', 'B', 7); //esta tambien es importante

	if ($action == "conciliado") {
		$total_facturado_suma = 0;
		$total_devuelto_suma = 0;
		$suma_saldo_general = 0;
		$pdf->SetWidths(array(30, 70, 10, 20, 20, 10, 10, 10, 10));
		$pdf->Row_tabla(array(utf8_decode('Código'), utf8_decode('Descripción'), 'Bod', 'Lote', 'Nup', 'Cant', 'Ret', 'Fac', 'Saldo'));
		while ($row_detalle = mysqli_fetch_assoc($detalle_consignacion)) {

			$detalle_devolucion = detalle_devolucion($con, $ruc_empresa, $numero_consignacion, $row_detalle['id_producto'], $row_detalle['lote'], $row_detalle['nup']);
			$total_devuelto = $detalle_devolucion['devuelto'];
			$total_devuelto_suma += $detalle_devolucion['devuelto'];


			$detalle_facturado = detalle_facturado($con, $ruc_empresa, $numero_consignacion, $row_detalle['id_producto'], $row_detalle['lote'], $row_detalle['nup']);
			$total_facturado = $detalle_facturado['facturado'];
			$total_facturado_suma += $detalle_facturado['facturado'];
			$suma_saldo_general += number_format($row_detalle['cant_consignacion'] - $total_devuelto - $total_facturado, 0, '.', '');

			$pdf->Row_tabla(array(
				$row_detalle['codigo_producto'],
				utf8_decode($row_detalle['nombre_producto']),
				substr($row_detalle['bodega'], 0, 1),
				utf8_decode($row_detalle['lote']),
				$row_detalle['nup'],
				$row_detalle['cant_consignacion'],
				$total_devuelto,
				$total_facturado,
				number_format($row_detalle['cant_consignacion'] - $total_devuelto - $total_facturado, 0, '.', '')
			));
		}
		$pdf->SetWidths(array(30, 120, 10, 10, 10, 10));
		$pdf->Row_tabla(array(utf8_decode('TOTALES:'), '', number_format($total_consignadas, 0, '.', ''), number_format($total_devuelto_suma, 0, '.', ''), number_format($total_facturado_suma, 0, '.', ''), number_format($suma_saldo_general, 0, '.', '')));
	}


	if ($action == "general") {
		$pdf->SetWidths(array(30, 70, 10, 20, 20, 10, 10, 10, 10));
		$pdf->Row_tabla(array(utf8_decode('Código'), utf8_decode('Descripción'), 'Bod', 'Lote', 'Nup', 'Cant', 'Ret', 'Fac', 'Acon'));
		while ($row_detalle = mysqli_fetch_assoc($detalle_consignacion)) {
			$pdf->Row_tabla(array(
				$row_detalle['codigo_producto'],
				utf8_decode($row_detalle['nombre_producto']),
				substr($row_detalle['bodega'], 0, 1),
				utf8_decode($row_detalle['lote']),
				$row_detalle['nup'],
				$row_detalle['cant_consignacion'],
				'',
				'',
				''
			));
		}
		$pdf->SetWidths(array(30, 120, 10, 30));
		$pdf->Row_tabla(array(utf8_decode('TOTALES:'), '', number_format($total_consignadas, 0, '.', ''), ''));
	}

	if ($action == "con_precios") {
		$suma_total = 0;
		$pdf->SetWidths(array(30, 65, 20, 20, 10, 10, 10, 10, 15));
		$pdf->Row_tabla(array(utf8_decode('Código'), utf8_decode('Descripción'), 'Lote', 'Nup', 'Cant', 'Ret', 'Fac', 'Acon', 'PVP'));
		while ($row_detalle = mysqli_fetch_assoc($detalle_consignacion)) {
			$detalle_iva = mysqli_query($con, "SELECT sum(1 + (tar.porcentaje_iva / 100)) as porcentaje FROM productos_servicios as pro INNER JOIN tarifa_iva as tar ON tar.codigo=pro.tarifa_iva WHERE pro.id = '" . $row_detalle['id_producto'] . "' ");
			$row_iva = mysqli_fetch_assoc($detalle_iva);
			$total = number_format($row_detalle['precio'] * $row_detalle['cant_consignacion'] * $row_iva['porcentaje'], 2, '.', '');
			$suma_total += $total;
			$pdf->Row_tabla(array(
				$row_detalle['codigo_producto'],
				utf8_decode($row_detalle['nombre_producto']),
				utf8_decode($row_detalle['lote']),
				$row_detalle['nup'],
				$row_detalle['cant_consignacion'],
				'',
				'',
				'',
				$total
			));
		}
		$pdf->SetWidths(array(30, 105, 10, 20, 10, 15));
		$pdf->Row_tabla(array(utf8_decode('TOTALES'), '', number_format($total_consignadas, 0, '.', ''), '', 'Total', number_format($suma_total, 2, '.', '')));
	}



	$pdf->MultiCell(190, 5, 'Observaciones:' . utf8_decode($observaciones), 1, 1);
	$pdf->Ln();

	//$pdf->Cell(5);
	//$pdf->Cell(140,5,"EMITIDO POR: ".utf8_decode(strtoupper($usuario)),0,0,'L');
	$pdf->detalle_html('<p align="center"></p><hr>');
	$pdf->detalle_html('<p align="center"> EMITIDO POR: ' . utf8_decode(strtoupper($usuario)) . '     LOGISTICA: ' . strtoupper($traslado_por_final) . '                          RECIBIDO POR</p><br>');

	$pdf->detalle_html(utf8_decode('<p align="center"> VERIFICACIÓN DE ACONDICIONAMIENTO POR:                                                                                         '));
	//$pdf->detalle_html('<br><br>');

	//$pdf->SetY(5);
	//$pdf->Cell(0, 5, utf8_decode('Pág:') . $pdf->PageNo(), 0, 0, 'R');


	$pdf->Output("CV N. " . $numero_consignacion . ".pdf", "D");
	unlink('../docs_temp/' . $ruc_empresa . '.jpg');
}
