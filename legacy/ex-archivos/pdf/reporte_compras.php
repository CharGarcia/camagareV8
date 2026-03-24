<?php
require("../ajax/reporte_compras.php");
require('../pdf/funciones_pdf.php');

$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario_actual = $_SESSION['id_usuario'];

$action = $_POST['tipo_reporte'];
$id_proveedor = $_POST['id_proveedor'];
$desde = $_POST['desde'];
$hasta = $_POST['hasta'];

if ($action == '3' || $action == '4') {
echo "Reporte no disponible";
}
if ($action == '1' || $action == '2') {
	if ($action == '1') {
		$resultados = reporte_compras($con, $ruc_empresa, $desde, $hasta, $id_proveedor, '1');
		$nombre_archivo = "Adquisiciones";
	}
	if ($action == '2') {
		$resultados = reporte_compras($con, $ruc_empresa, $desde, $hasta, $id_proveedor, '2');
		$nombre_archivo = "NC_Adquisiciones";
	}
}

//para buscar la imagen
$busca_imagen = mysqli_query($con, "SELECT * FROM sucursales WHERE ruc_empresa = '" . $ruc_empresa . "' ");
$datos_imagen = mysqli_fetch_assoc($busca_imagen);
$imagen = "../logos_empresas/" . $datos_imagen['logo_sucursal'];

$busca_empresa = mysqli_query($con, "SELECT * FROM empresas WHERE ruc = '" . $ruc_empresa . "' ");
$datos_empresa = mysqli_fetch_assoc($busca_empresa);
$nombre_empresa = $datos_empresa['nombre'];
$html_encabezado = '<p align="center">' . $nombre_empresa . '</p><br>
				  <p align="center">REPORTE DE ADQUISICIONES</p><br>
				  <p align="center">Del: ' . $desde . '  Al: ' . $hasta . '</p><br>';

$pdf = new funciones_pdf('L', 'mm', 'A4'); //P
$pdf->AliasNbPages();
//$content_added = false;
//$pdf->AddPage();
$pdf->SetFont('Times', '', 12);

$imagen_optimizada = $pdf->imagen_optimizada($imagen, $width = 200, $height = 200);
imagejpeg($imagen_optimizada, '../docs_temp/' . $ruc_empresa . '.jpg');
$nombre_imagen = '../docs_temp/' . $ruc_empresa . '.jpg';

$pdf->AddPage(); //es importante agregar esta linea para saber la pagina inicial
$pdf->SetFont('Arial', '', 9); //esta tambien es importante
$pdf->detalle_html(utf8_decode($html_encabezado));
//Image($file, $x=null, $y=null, $w=0, $h=0, $type='', $link='')
$pdf->Image($nombre_imagen, 20, 10, 30, 30, 'jpg', '');
$pdf->SetWidths(array(20, 80, 30, 35, 20, 20, 15, 20, 15, 20));
$pdf->Row_tabla(array(utf8_decode('Fecha'), 'Proveedor', 'Documento', utf8_decode('Número'), 'Subtotal', 'Descuento', 'IVA', 'Propina', 'Otros', 'Total'));
$suma_subtotal = 0;
$suma_descuento = 0;
$suma_iva = 0;
$suma_propina = 0;
$suma_otros = 0;
$suma_total = 0;
while ($row_datos = mysqli_fetch_assoc($resultados)) {
	$pdf->Row_tabla(array(
		date('d/m/Y', strtotime($row_datos['fecha_compra'])),
		utf8_decode(strtoupper($row_datos['proveedor'])),
		utf8_decode(strtoupper($row_datos['comprobante'])),
		$row_datos['documento'],
		$row_datos['subtotal'],
		$row_datos['descuento'],
		$row_datos['iva'],
		$row_datos['propina'],
		$row_datos['otros_val'],
		$row_datos['total_compra']
	));
	$suma_subtotal += $row_datos['subtotal'];
	$suma_descuento += $row_datos['descuento'];
	$suma_iva += $row_datos['iva'];
	$suma_propina += $row_datos['propina'];
	$suma_otros += $row_datos['otros_val'];
	$suma_total += $row_datos['total_compra'];
}

$pdf->Cell(165, 6, 'Totales:', 2, 0, 'R');
$pdf->Cell(20, 6, number_format($suma_subtotal, 2, '.', ''), 1, 0, 'R');
$pdf->Cell(20, 6, number_format($suma_descuento, 2, '.', ''), 1, 0, 'R');
$pdf->Cell(15, 6, number_format($suma_iva, 2, '.', ''), 1, 0, 'R');
$pdf->Cell(20, 6, number_format($suma_propina, 2, '.', ''), 1, 0, 'R');
$pdf->Cell(15, 6, number_format($suma_otros, 2, '.', ''), 1, 0, 'R');
$pdf->Cell(20, 6, number_format($suma_total, 2, '.', ''), 1, 1, 'R');

$pdf->Cell(80, 30, '----------------------------------', 2, 0, 'C');
$pdf->Cell(100, 30, '----------------------------------', 2, 1, 'C');
$pdf->Cell(80, -25, 'REVISADO POR', 2, 0, 'C');
$pdf->Cell(100, -25, 'APROBADO POR', 2, 0, 'C');


//$content_added = true;
//$pdf->detalle_html('<br><br>');
//$pdf->SetY(5);
//$pdf->Cell(0, 5, utf8_decode('Pág:') . $pdf->PageNo(), 0, 0, 'R');

$pdf->Output($nombre_archivo . " " . $desde . " al " . $hasta . ".pdf", "D");
unlink('../docs_temp/' . $ruc_empresa . '.jpg');
