<?php
require("../ajax/resumen_cartera.php");
require('../pdf/funciones_pdf.php');
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario_actual = $_SESSION['id_usuario'];
$con = conenta_login();

$id_cliente = $_POST['id_cliente'];
$desde = $_POST['fecha_desde'];
$hasta = $_POST['fecha_hasta'];

//para buscar la imagen
$busca_imagen = mysqli_query($con, "SELECT * FROM sucursales WHERE ruc_empresa = '" . $ruc_empresa . "' ");
$datos_imagen = mysqli_fetch_assoc($busca_imagen);
$imagen = "../logos_empresas/" . $datos_imagen['logo_sucursal'];

$busca_empresa = mysqli_query($con, "SELECT * FROM empresas WHERE ruc = '" . $ruc_empresa . "' ");
$datos_empresa = mysqli_fetch_assoc($busca_empresa);
$nombre_empresa = $datos_empresa['nombre'];
$html_encabezado = '<p align="center">' . $nombre_empresa . '</p><br>
				  <p align="center">REPORTE DE CARTERA</p><br>
				  <p align="center">Del: ' . $desde . '  Al: ' . $hasta . '</p><br>';

//para las facturas
resumen_por_cobrar($desde, $hasta, $id_cliente, '');
$contar_clientes = mysqli_query($con, "SELECT COUNT(DISTINCT id_cli_pro) as total 
    FROM saldo_porcobrar_porpagar WHERE ruc_empresa = '" . $ruc_empresa . "'");
$row = mysqli_fetch_assoc($contar_clientes);
$total_clientes = $row['total'];

$pdf = new funciones_pdf('L', 'mm', 'A4'); //P
$pdf->AliasNbPages();
$pdf->SetFont('Times', '', 12);
$imagen_optimizada = $pdf->imagen_optimizada($imagen, $width = 200, $height = 200);
imagejpeg($imagen_optimizada, '../docs_temp/' . $ruc_empresa . '.jpg');
$nombre_imagen = '../docs_temp/' . $ruc_empresa . '.jpg';

$pdf->AddPage(); //es importante agregar esta linea para saber la pagina inicial
$pdf->SetFont('Arial', '', 9); //esta tambien es importante
$pdf->detalle_html(utf8_decode($html_encabezado));
//Image($file, $x=null, $y=null, $w=0, $h=0, $type='', $link='')
$pdf->Image($nombre_imagen, 20, 10, 30, 30, 'jpg', '');


//para facturas
if ($total_clientes > 0) {
	$pdf->SetWidths(array(20, 120, 35, 20, 20, 20, 20, 20));
	$pdf->Row_tabla(array(utf8_decode('Fecha'), 'Cliente', 'Factura', 'Total', 'NC', 'Abonos', 'Retenciones', 'Saldo'));
	$suma_total = 0;
	$suma_nc = 0;
	$suma_abonos = 0;
	$suma_retenciones = 0;
	$suma_saldo = 0;
	$resultados = mysqli_query($con, "SELECT * FROM saldo_porcobrar_porpagar 
WHERE id_usuario = '" . $id_usuario . "' and ruc_empresa='" . $ruc_empresa . "' 
and DATE_FORMAT(fecha_documento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
and '" . date("Y/m/d", strtotime($hasta)) . "' ORDER BY nombre_cli_pro asc, fecha_documento asc, numero_documento asc ");

	while ($row_datos = mysqli_fetch_assoc($resultados)) {
		$abonos = $row_datos['total_ing'] + $row_datos['ing_tmp'];
		$saldo = $row_datos['total_factura'] - $row_datos['total_nc'] - $row_datos['total_ing'] - $row_datos['ing_tmp'] - $row_datos['total_ret'];

		$pdf->Row_tabla(array(
			date('d/m/Y', strtotime($row_datos['fecha_documento'])),
			utf8_decode(strtoupper($row_datos['nombre_cli_pro'])),
			$row_datos['numero_documento'],
			number_format($row_datos['total_factura'], 2, '.', ''),
			number_format($row_datos['total_nc'], 2, '.', ''),
			number_format($abonos, 2, '.', ''),
			number_format($row_datos['total_ret'], 2, '.', ''),
			number_format($saldo, 2, '.', '')
		));
		$suma_total += $row_datos['total_factura'];
		$suma_nc += $row_datos['total_nc'];
		$suma_abonos += $abonos;
		$suma_retenciones += $row_datos['total_ret'];
		$suma_saldo += $saldo;
	}

	$pdf->Cell(175, 6, 'Totales:', 2, 0, 'R');
	$pdf->Cell(20, 6, number_format($suma_total, 2, '.', ''), 1, 0, 'R');
	$pdf->Cell(20, 6, number_format($suma_nc, 2, '.', ''), 1, 0, 'R');
	$pdf->Cell(20, 6, number_format($suma_abonos, 2, '.', ''), 1, 0, 'R');
	$pdf->Cell(20, 6, number_format($suma_retenciones, 2, '.', ''), 1, 0, 'R');
	$pdf->Cell(20, 6, number_format($suma_saldo, 2, '.', ''), 1, 1, 'R');
}


//para todos los clientes de recibos
if (empty($id_cliente)) {
	$condicion_cliente = "";
} else {
	$condicion_cliente = " and enc.id_cliente = " . $id_cliente;
}

$busca_clientes = mysqli_query($con, "SELECT DISTINCT cli.id as id, cli.nombre as nombre 
FROM clientes as cli INNER JOIN encabezado_recibo as enc On enc.id_cliente = cli.id 
WHERE enc.ruc_empresa = '" . $ruc_empresa . "' $condicion_cliente order by cli.nombre asc");

if (mysqli_num_rows($busca_clientes) > 0) {
	$pdf->SetWidths(array(20, 120, 35, 20, 20, 20, 20, 20));
	$pdf->Row_tabla(array(utf8_decode('Fecha'), 'Cliente', 'Recibo', 'Total', 'Abonos', 'Saldo'));
	$suma_total = 0;
	$suma_abonos = 0;
	$suma_saldo = 0;
	while ($row_clientes = mysqli_fetch_array($busca_clientes)) {
		$ide_cliente = $row_clientes['id'];
		$nombre_cliente = $row_clientes['nombre'];

		$busca_saldos_general = mysqli_query($con, "SELECT * FROM encabezado_recibo as enc 
	INNER JOIN clientes as cli ON cli.id=enc.id_cliente 
	WHERE enc.ruc_empresa='" . $ruc_empresa . "' and 
	DATE_FORMAT(enc.fecha_recibo, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
	and '" . date("Y/m/d", strtotime($hasta)) . "' and enc.id_cliente='" . $ide_cliente . "' ORDER BY cli.nombre asc, enc.fecha_recibo asc, enc.secuencial_recibo asc ");
		while ($detalle = mysqli_fetch_array($busca_saldos_general)) {
			$id_encabezado = $detalle['id_encabezado_recibo'];
			$numero_documento = $detalle['serie_recibo'] . "-" . $detalle['secuencial_recibo'];
			$total_recibo = $detalle['total_recibo'];
			$codigo_recibo = "RV" . $id_encabezado;

			$busca_ingresos = mysqli_query($con, "SELECT sum(det.valor_ing_egr) as abonos 
		FROM detalle_ingresos_egresos as det 
		INNER JOIN ingresos_egresos as enc 
		ON enc.codigo_documento=det.codigo_documento
		WHERE enc.ruc_empresa='" . $ruc_empresa . "' 
		and DATE_FORMAT(enc.fecha_ing_egr, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
		and '" . date("Y/m/d", strtotime($hasta)) . "' and det.codigo_documento_cv = '" . $codigo_recibo . "'");
			$row_ingreso = mysqli_fetch_array($busca_ingresos);
			$abonos = $row_ingreso['abonos'];
			$saldo = $total_recibo - $abonos;


			$pdf->Row_tabla(array(
				date('d/m/Y', strtotime($detalle['fecha_recibo'])),
				utf8_decode(strtoupper($detalle['nombre'])),
				$numero_documento,
				number_format($total_recibo, 2, '.', ''),
				number_format($abonos, 2, '.', ''),
				number_format($saldo, 2, '.', '')
			));
			$suma_total += $total_recibo;
			$suma_abonos += $abonos;
			$suma_saldo += $saldo;
		}
	}
	$pdf->Cell(175, 6, 'Totales:', 2, 0, 'R');
	$pdf->Cell(20, 6, number_format($suma_total, 2, '.', ''), 1, 0, 'R');
	$pdf->Cell(20, 6, number_format($suma_abonos, 2, '.', ''), 1, 0, 'R');
	$pdf->Cell(20, 6, number_format($suma_saldo, 2, '.', ''), 1, 1, 'R');
}



$pdf->Cell(80, 30, '----------------------------------', 2, 0, 'C');
$pdf->Cell(100, 30, '----------------------------------', 2, 1, 'C');
$pdf->Cell(80, -25, 'REVISADO POR', 2, 0, 'C');
$pdf->Cell(100, -25, 'APROBADO POR', 2, 0, 'C');

$pdf->Output("Reporte_De_Cartera " . $desde . " al " . $hasta . ".pdf", "D");
unlink('../docs_temp/' . $ruc_empresa . '.jpg');
