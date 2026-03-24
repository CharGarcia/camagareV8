<?php
require('../ajax/resumen_cartera.php');

$con = conenta_login();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

if ($action == "generar_informe_excel") {
	$desde = $_POST['fecha_desde'];
	$hasta = $_POST['fecha_hasta'];
	$id_cliente = $_POST['id_cliente'];
	$vendedor = ""; //$_POST['vendedor'];
	ini_set('date.timezone', 'America/Guayaquil');
	$fecha_hoy = date_create(date("Y-m-d H:i:s"));
	//if(mysqli_num_rows($resultado) > 0 ){			
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
		->setDescription("Reporte cartera")
		->setKeywords("Reporte cartera")
		->setCategory("Reporte cartera");

	//para sacar el nombre de la empresa
	$sql_empresa = mysqli_query($con, "SELECT * FROM empresas where ruc= '" . $ruc_empresa . "'");
	$empresa_info = mysqli_fetch_array($sql_empresa);
	$tituloEmpresa = $empresa_info['nombre_comercial'];
	$tituloReporte = "Reporte de cartera";

	if (empty($id_cliente)) { //para todos los clientes
		$condicion_cliente = "";
		$condicion_cliente_pro = "";
	} else { //para un cliente
		$condicion_cliente = " and id=" . $id_cliente;
		$condicion_cliente_pro = " and id_cli_pro=" . $id_cliente;
	}

	$busca_saldos_general = resumen_por_cobrar($desde, $hasta, $id_cliente, $vendedor);
	$busca_clientes = mysqli_query($con, "SELECT DISTINCT id_cli_pro as id, nombre_cli_pro as nombre 
		FROM saldo_porcobrar_porpagar WHERE ruc_empresa = '" . $ruc_empresa . "' order by nombre_cli_pro asc");
	$busca_saldos_total = mysqli_query($con, "SELECT sum(total_factura - (total_nc + total_ing  + ing_tmp + total_ret)) as saldo_general FROM saldo_porcobrar_porpagar WHERE id_usuario = '" . $id_usuario . "' $condicion_cliente_pro and ruc_empresa='" . $ruc_empresa . "' and DATE_FORMAT(fecha_documento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' ");
	$row_saldo_total = mysqli_fetch_array($busca_saldos_total);
	$suma_general = $row_saldo_total['saldo_general'];
	$corte = "Al " . date("d-m-Y", strtotime($hasta)) . " Total por cobrar: " . number_format($suma_general, 2, '.', '');

	$titulosColumnas = array('Fecha', 'Factura', 'Cliente', 'Subtotal', 'IVA', 'Total', 'Notas de crédito', 'Abonos', 'Retenciones', 'Saldo');

	$objPHPExcel->setActiveSheetIndex(0)
		->mergeCells('A1:J1')
		->mergeCells('A2:J2')
		->mergeCells('A3:J3');

	$i = 4;
	// Se agregan los titulos del reporte
	$objPHPExcel->setActiveSheetIndex(0)
		->setCellValue('A1', $tituloEmpresa)
		->setCellValue('A2',  $tituloReporte)
		->setCellValue('A3',  $corte);

	$objPHPExcel->setActiveSheetIndex(0)
		->setCellValue('A' . $i,  $titulosColumnas[0])
		->setCellValue('B' . $i,  $titulosColumnas[1])
		->setCellValue('C' . $i,  $titulosColumnas[2])
		->setCellValue('D' . $i,  $titulosColumnas[3])
		->setCellValue('E' . $i,  $titulosColumnas[4])
		->setCellValue('F' . $i,  $titulosColumnas[5])
		->setCellValue('G' . $i,  $titulosColumnas[6])
		->setCellValue('H' . $i,  $titulosColumnas[7])
		->setCellValue('I' . $i,  $titulosColumnas[8])
		->setCellValue('J' . $i,  $titulosColumnas[9]);
	$i++;

	while ($row_clientes = mysqli_fetch_array($busca_clientes)) {
		$ide_cliente = $row_clientes['id'];
		$nombre_cliente = $row_clientes['nombre'];
		$plazo = $row_clientes['plazo'];
		$sql_suma_cliente = mysqli_query($con, "SELECT sum(total_factura - (total_nc + total_ing  + ing_tmp + total_ret)) as total_cliente FROM saldo_porcobrar_porpagar WHERE id_cli_pro = '" . $ide_cliente . "' and id_usuario='" . $id_usuario . "' and ruc_empresa='" . $ruc_empresa . "'");
		$row_total_cliente = mysqli_fetch_array($sql_suma_cliente);
		$total_cliente = $row_total_cliente['total_cliente'];

		//if ($total_cliente > 0) {
		$busca_saldos_general = mysqli_query($con, "SELECT * FROM saldo_porcobrar_porpagar WHERE id_usuario = '" . $id_usuario . "' and ruc_empresa='" . $ruc_empresa . "' and DATE_FORMAT(fecha_documento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' and id_cli_pro='" . $ide_cliente . "' ORDER BY nombre_cli_pro asc, fecha_documento asc, numero_documento asc ");
		while ($detalle = mysqli_fetch_array($busca_saldos_general)) {
			$id_encabezado = $detalle['id_documento'];
			$fecha_documento = $detalle['fecha_documento'];
			$nombre_cli_pro = $detalle['nombre_cli_pro'];
			$numero_documento = $detalle['numero_documento'];
			$total_factura = $detalle['total_factura'];
			$total_nc = $detalle['total_nc'];
			$abonos = $detalle['total_ing'] + $detalle['ing_tmp'];
			$retenciones = $detalle['total_ret'];
			$saldo = $detalle['total_factura'] - $detalle['total_nc'] - $detalle['total_ing'] - $detalle['ing_tmp'] - $detalle['total_ret'];

			$datos_subtotal = mysqli_query($con, "SELECT usu.nombre as usuario, sum(cue_fac.subtotal_factura) as subtotal FROM encabezado_factura as enc_fac INNER JOIN cuerpo_factura as cue_fac ON cue_fac.serie_factura=enc_fac.serie_factura and cue_fac.secuencial_factura=enc_fac.secuencial_factura and cue_fac.ruc_empresa=enc_fac.ruc_empresa INNER JOIN usuarios as usu ON usu.id=enc_fac.id_usuario WHERE enc_fac.id_encabezado_factura = '" . $id_encabezado . "' and mid(enc_fac.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' group by cue_fac.secuencial_factura and cue_fac.serie_factura");
			$detalle_subtotal = mysqli_fetch_array($datos_subtotal);
			$subtotal = $detalle_subtotal['subtotal'];
			$usuario = $detalle_subtotal['usuario'];

			$datos_iva = mysqli_query($con, "SELECT sum(round(cue_fac.subtotal_factura * (tarifa.porcentaje_iva/100),2)) as iva 
			FROM encabezado_factura as enc_fac 
			INNER JOIN cuerpo_factura as cue_fac ON cue_fac.serie_factura=enc_fac.serie_factura 
			and cue_fac.secuencial_factura=enc_fac.secuencial_factura 
			and cue_fac.ruc_empresa=enc_fac.ruc_empresa 
			INNER JOIN tarifa_iva as tarifa ON tarifa.codigo=cue_fac.tarifa_iva 
			WHERE enc_fac.id_encabezado_factura = '" . $id_encabezado . "' 
			and mid(enc_fac.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' 
			group by cue_fac.secuencial_factura and cue_fac.serie_factura"); //and tarifa.codigo='2'
			$detalle_iva = mysqli_fetch_array($datos_iva);
			$subtotal_iva = $detalle_iva['iva'];


			$objPHPExcel->setActiveSheetIndex(0)
				->setCellValue('A' . $i,  date("d/m/Y", strtotime($fecha_documento)))
				->setCellValue('B' . $i,  $numero_documento)
				->setCellValue('C' . $i,  $nombre_cliente)
				->setCellValue('D' . $i,  number_format($subtotal, 2, '.', ''))
				->setCellValue('E' . $i,  number_format($subtotal_iva, 2, '.', ''))
				->setCellValue('F' . $i,  number_format($total_factura, 2, '.', ''))
				->setCellValue('G' . $i,  number_format($total_nc, 2, '.', ''))
				->setCellValue('H' . $i,  number_format($abonos, 2, '.', ''))
				->setCellValue('I' . $i,  number_format($retenciones, 2, '.', ''))
				->setCellValue('J' . $i,  number_format($saldo, 2, '.', ''));
			//$objPHPExcel->getActiveSheet()->getStyle('D' . $i . ':J' . $i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
			$i++;
		}
		$objPHPExcel->getActiveSheet()->getStyle('D5:J' . $i)->getNumberFormat()->setFormatCode('#,##0.00');
		//}
	}


	for ($i = 'A'; $i <= 'N'; $i++) {
		$objPHPExcel->setActiveSheetIndex(0)
			->getColumnDimension($i)->setAutoSize(TRUE);
	}


	// Se asigna el nombre a la hoja
	$objPHPExcel->getActiveSheet()->setTitle('ResumenCartera');

	// Se activa la hoja para que sea la que se muestre cuando el archivo se abre
	$objPHPExcel->setActiveSheetIndex(0);
	$objPHPExcel->getActiveSheet(0)->freezePaneByColumnAndRow(0, 5);

	// Se manda el archivo al navegador web, con el nombre que se indica (Excel2007)
	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	header('Content-Disposition: attachment;filename="ResumenCartera.xlsx"');
	header('Cache-Control: max-age=0');

	$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
	$objWriter->save('php://output');
	exit;
} else {
	echo ('No es posible generar el archivo.');
}
