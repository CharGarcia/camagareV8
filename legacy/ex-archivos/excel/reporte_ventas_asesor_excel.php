<?php
include("../ajax/reporte_ventas_asesor.php");
$con = conenta_login();
//session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_cliente = $_POST['id_cliente'];
$id_producto = $_POST['id_producto'];
$desde = $_POST['desde'];
$hasta = $_POST['hasta'];
$id_marca = $_POST['id_marca'];
$id_vendedor = $_POST['id_vendedor'];
$resultado_ventas = reporte_ventas_asesor($con, $id_cliente, $id_producto, $id_marca, $id_vendedor, $ruc_empresa, $desde, $hasta);

if ($resultado_ventas->num_rows > 0) {
	date_default_timezone_set('America/Guayaquil');
	if (PHP_SAPI == 'cli')
		die('Este archivo solo se puede ver desde un navegador web');

	/** Se agrega la libreria PHPExcel */
	require_once 'lib/PHPExcel/PHPExcel.php';

	// Se crea el objeto PHPExcel
	$objPHPExcel = new PHPExcel();

	// Se asignan las propiedades del libro
	$objPHPExcel->getProperties()->setCreator("CaMaGaRe") //Autor
		->setLastModifiedBy("CaMaGaRe") //Ultimo usuario que lo modificó
		->setTitle("Reporte Excel")
		->setSubject("Reporte Excel")
		->setDescription("Reporte ventas asesor")
		->setKeywords("reporte ventas asesor")
		->setCategory("Reporte excel");

	//para sacar el nombre de la empresa
	$sql_empresa = mysqli_query($con, "SELECT * FROM empresas where ruc= '" . $ruc_empresa . "'");
	$empresa_info = mysqli_fetch_array($sql_empresa);
	$tituloEmpresa = $empresa_info['nombre'];
	$tituloReporte = "Reporte de ventas por asesor desde: " . date("d-m-Y", strtotime($desde)) . " hasta: " . date("d-m-Y", strtotime($hasta));
	$titulosColumnas = array('Asesor', 'Total ventas brutas', 'Total notas de crédito', 'Total ventas netas');

	$objPHPExcel->setActiveSheetIndex(0)
		->mergeCells('A1:B1')
		->mergeCells('A2:B2');

	// Se agregan los titulos del reporte
	$objPHPExcel->setActiveSheetIndex(0)
		->setCellValue('A1',  $tituloEmpresa)
		->setCellValue('A2',  $tituloReporte)
		->setCellValue('A3',  $titulosColumnas[0])
		->setCellValue('B3',  $titulosColumnas[1])
		->setCellValue('C3',  $titulosColumnas[2])
		->setCellValue('D3',  $titulosColumnas[3]);
	$i = 4;
	$suma_ventas = 0;
	$suma_nc = 0;
	$suma_total = 0;
	while ($fila = $resultado_ventas->fetch_array()) {
		$subtotal_ventas = $fila['subtotal_ventas'];
		$suma_ventas += $subtotal_ventas;
		$asesor = $fila['asesor'];
		$subtotal_nc = $fila['subtotal_nc'];
		$suma_nc += $subtotal_nc;
		$ventas_netas = $subtotal_ventas - $subtotal_nc;
		$suma_total += $ventas_netas;

		$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('A' . $i,  strtoupper($fila['asesor']))
			->setCellValue('B' . $i,  number_format($subtotal_ventas, 2, '.', ''))
			->setCellValue('C' . $i,  number_format($subtotal_nc, 2, '.', ''))
			->setCellValue('D' . $i,  number_format($ventas_netas, 2, '.', ''));
		$i++;
	}
	$t = $i + 1;
	$objPHPExcel->setActiveSheetIndex(0)
		->setCellValue('A' . $t,  'TOTALES')
		->setCellValue('B' . $t,  number_format($suma_ventas, 2, '.', ''))
		->setCellValue('C' . $t,  number_format($suma_nc, 2, '.', ''))
		->setCellValue('D' . $t,  number_format($suma_total, 2, '.', ''));

	$objPHPExcel->getActiveSheet()->getStyle('B4:D' . $t)->getNumberFormat()->setFormatCode('#,##0.00');

	for ($i = 'A'; $i <= 'D'; $i++) {
		$objPHPExcel->setActiveSheetIndex(0)
			->getColumnDimension($i)->setAutoSize(TRUE);
	}

	// Se asigna el nombre a la hoja
	$objPHPExcel->getActiveSheet()->setTitle('VentasAsesor');

	// Se activa la hoja para que sea la que se muestre cuando el archivo se abre
	$objPHPExcel->setActiveSheetIndex(0);
	// Inmovilizar paneles 
	//$objPHPExcel->getActiveSheet(0)->freezePane('A4');
	$objPHPExcel->getActiveSheet(0)->freezePaneByColumnAndRow(0, 4);

	// Se manda el archivo al navegador web, con el nombre que se indica (Excel2007)
	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	header('Content-Disposition: attachment;filename="Reporte ventas asesor.xlsx"');
	header('Cache-Control: max-age=0');

	$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
	$objWriter->save('php://output');
	exit;
} else {
	echo ('No hay resultados para mostrar');
}
