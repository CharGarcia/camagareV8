<?php
ini_set('display_errors', 1);       // mostrar en pantalla
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include("../ajax/conciliacion_bancaria.php");
date_default_timezone_set('America/Guayaquil');
$con = conenta_login();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

if ($action == "generar_informe_excel") {
	$cuenta = $_POST['cuenta'];
	$fecha_desde = $_POST['fecha_desde'];
	$fecha_hasta = $_POST['fecha_hasta'];

	$sql_cuenta = mysqli_fetch_array(mysqli_query($con, "SELECT cue_ban.id_cuenta as id_cuenta, concat(ban_ecu.nombre_banco,' ',cue_ban.numero_cuenta,' ', if(cue_ban.id_tipo_cuenta=1,'Aho','Cte')) as cuenta_bancaria FROM cuentas_bancarias as cue_ban INNER JOIN bancos_ecuador as ban_ecu ON cue_ban.id_banco=ban_ecu.id_bancos WHERE cue_ban.ruc_empresa ='" . $ruc_empresa . "' and cue_ban.id_cuenta='" . $cuenta . "'"));
	$nombre_cuenta = $sql_cuenta['cuenta_bancaria'];
	$id_cuenta_bancaria = $sql_cuenta['id_cuenta'];

	//para sacar la cuenta contable asignada a la cuenta bancaria 

	$suma_creditos_saldo_inicial = saldo_inicial_creditos($con, $cuenta, $ruc_empresa, $fecha_desde);
	$suma_debitos_saldo_inicial = saldo_inicial_debitos($con, $cuenta, $ruc_empresa, $fecha_desde);
	$cheques_saldo_inicial = cheques_saldo_inicial($con, $cuenta, $ruc_empresa, $fecha_desde);
	$saldo_inicial = $suma_creditos_saldo_inicial - $suma_debitos_saldo_inicial - $cheques_saldo_inicial;

	$total_creditos = creditos_debitos($con, $cuenta, $ruc_empresa, $fecha_desde, $fecha_hasta, 'INGRESO');
	$total_debitos = creditos_debitos($con, $cuenta, $ruc_empresa, $fecha_desde, $fecha_hasta, 'EGRESO');
	$cheques_pagados = cheques_pagados($con, $cuenta, $ruc_empresa, $fecha_desde, $fecha_hasta);

	$saldo_final = $saldo_inicial + $total_creditos - $total_debitos - $cheques_pagados;

	//ini_set('date.timezone', 'America/Guayaquil');
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
		->setTitle("Conciliacion Bancaria")
		->setSubject("Conciliacion Bancaria")
		->setDescription("Conciliacion Bancaria")
		->setKeywords("Conciliacion Bancaria")
		->setCategory("Conciliacion Bancaria");

	//para sacar el nombre de la empresa
	$sql_empresa = mysqli_query($con, "SELECT * FROM empresas where ruc= '" . $ruc_empresa . "'");
	$empresa_info = mysqli_fetch_array($sql_empresa);
	$tituloEmpresa = $empresa_info['nombre_comercial'];
	$tituloReporte = "Conciliación Bancaria " . $nombre_cuenta . " del " . date("d/m/Y", strtotime($fecha_desde)) . " al " . date("d/m/Y", strtotime($fecha_hasta));


	$titulosColumnas = array('Fecha Emisión', 'Nombre', 'Créditos', 'Débitos', 'Tipo', 'N.Cheque', 'Fecha cobro', 'Estado cheque', 'Detalle', 'Documento', 'Número', 'Asiento', 'Debe', 'Haber');

	$objPHPExcel->setActiveSheetIndex(0)
		->mergeCells('A1:I1')
		->mergeCells('A2:I2')
	;

	$i = 7;
	// Se agregan los titulos del reporte
	$objPHPExcel->setActiveSheetIndex(0)
		->setCellValue('A1', $tituloEmpresa)
		->setCellValue('A2',  $tituloReporte)
		->setCellValue('A3',  "Saldo Inicial")
		->setCellValue('B3',  $saldo_inicial)
		->setCellValue('A4',  "Créditos")
		->setCellValue('B4',  $total_creditos)
		->setCellValue('A5',  "Débitos")
		->setCellValue('B5',  number_format($total_debitos + $cheques_pagados, 2, '.', ''))
		->setCellValue('A6',  "Saldo Final")
		->setCellValue('B6',  $saldo_final)
	;

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
		->setCellValue('J' . $i,  $titulosColumnas[9])
		->setCellValue('K' . $i,  $titulosColumnas[10])
		->setCellValue('L' . $i,  $titulosColumnas[11])
		->setCellValue('M' . $i,  $titulosColumnas[12])
		->setCellValue('N' . $i,  $titulosColumnas[13])
	;
	$i++;

	//CREDITOS
	$sql_ingresos = detalle_creditos_debitos($con, $cuenta, $ruc_empresa, $fecha_desde, $fecha_hasta, 'INGRESO');
	while ($row_ingresos = mysqli_fetch_array($sql_ingresos)) {
		$fecha_emision = $row_ingresos['fecha_emision'];
		$codigo_documento = $row_ingresos['codigo_documento'];
		$id_ingreso = "ING" . $row_ingresos['id_ing_egr'];
		$id_diarios = detalle_asiento_contable($con, $id_cuenta_bancaria, $ruc_empresa, $fecha_desde, $fecha_hasta, 'INGRESOS', $id_ingreso)['id_diarios'];
		$total_debe = detalle_asiento_contable($con, $id_cuenta_bancaria, $ruc_empresa, $fecha_desde, $fecha_hasta, 'INGRESOS', $id_ingreso)['total_debe'];
		$total_haber = detalle_asiento_contable($con, $id_cuenta_bancaria, $ruc_empresa, $fecha_desde, $fecha_hasta, 'INGRESOS', $id_ingreso)['total_haber'];

		$nombre_ingreso = $row_ingresos['nombre_ing_egr'];
		$numero_ing_egr = $row_ingresos['numero_ing_egr'];
		$detalle_pago = $row_ingresos['detalle_pago'];
		switch ($detalle_pago) {
			case "D":
				$detalle_pago = 'Depósito';
				break;
			case "T":
				$detalle_pago = 'Transferencia';
				break;
		}
		$valor = $row_ingresos['valor_forma_pago'];
		$sql_detalle_ingresos = detalle_ingresos_egresos($con, $codigo_documento);
		$detalle_unido = "";

		foreach ($sql_detalle_ingresos as $detalle) {
			$detalle_unido .= $detalle['detalle_ing_egr'] . " ";
		}
		$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('A' . $i,  date("d/m/Y", strtotime($fecha_emision)))
			->setCellValue('B' . $i,  $nombre_ingreso)
			->setCellValue('C' . $i,  number_format($valor, 2, '.', ''))
			->setCellValue('D' . $i,  number_format(0, 2, '.', ''))
			->setCellValue('E' . $i,  $detalle_pago)
			->setCellValue('F' . $i,  "")
			->setCellValue('G' . $i,  "")
			->setCellValue('H' . $i,  "")
			->setCellValue('I' . $i,  $detalle_unido)
			->setCellValue('J' . $i,  "Ingreso")
			->setCellValue('K' . $i,  $numero_ing_egr)
			->setCellValue('L' . $i,  $id_diarios)
			->setCellValue('M' . $i,  $total_debe)
			->setCellValue('N' . $i,  $total_haber)
		;
		//$objPHPExcel->getActiveSheet()->getStyle('D' . $i . ':E' . $i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
		$i++;
	}

	//DEBITOS
	$sql_egresos = detalle_creditos_debitos($con, $cuenta, $ruc_empresa, $fecha_desde, $fecha_hasta, 'EGRESO');
	while ($row_egresos = mysqli_fetch_array($sql_egresos)) {
		//$fecha_emision=$row_egresos['cheque']>0?$row_egresos['fecha_entrega']:$row_egresos['fecha_emision'];
		$fecha_emision = $row_egresos['fecha_emision'];
		$codigo_documento = $row_egresos['codigo_documento'];

		$id_egreso = "EGR" . $row_egresos['id_ing_egr'];
		$id_diarios = detalle_asiento_contable($con, $id_cuenta_bancaria, $ruc_empresa, $fecha_desde, $fecha_hasta, 'EGRESOS', $id_egreso)['id_diarios'];
		$total_debe = detalle_asiento_contable($con, $id_cuenta_bancaria, $ruc_empresa, $fecha_desde, $fecha_hasta, 'EGRESOS', $id_egreso)['total_debe'];
		$total_haber = detalle_asiento_contable($con, $id_cuenta_bancaria, $ruc_empresa, $fecha_desde, $fecha_hasta, 'EGRESOS', $id_egreso)['total_haber'];

		$nombre_egreso = $row_egresos['nombre_ing_egr'];
		$numero_ing_egr = $row_egresos['numero_ing_egr'];
		$detalle_pago = $row_egresos['detalle_pago'];
		$fecha_pago = $fecha_emision;
		switch ($detalle_pago) {
			case "D":
				$detalle_pago = 'Débito';
				$fecha_pago = $row_egresos['fecha_pago'];
				break;
			case "T":
				$detalle_pago = 'Transferencia';
				$fecha_pago = $row_egresos['fecha_pago'];
				break;
			case "C":
				$detalle_pago = 'Cheque';
				$fecha_pago = $row_egresos['fecha_entrega'];
				break;
		}

		$valor = $row_egresos['valor_forma_pago'];
		$numero_cheque = $row_egresos['cheque'] > 0 ? $row_egresos['cheque'] : "";

		$estado_pago = $numero_cheque > 0 ? $row_egresos['estado_pago'] : "";
		$sql_detalle_egresos = detalle_ingresos_egresos($con, $codigo_documento);
		$detalle_unido = "";

		foreach ($sql_detalle_egresos as $detalle) {
			$detalle_unido .= $detalle['detalle_ing_egr'] . " ";
		}
		$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('A' . $i,  date("d/m/Y", strtotime($fecha_emision)))
			->setCellValue('B' . $i,  $nombre_egreso)
			->setCellValue('C' . $i,  number_format(0, 2, '.', ''))
			->setCellValue('D' . $i,  number_format($valor, 2, '.', ''))
			->setCellValue('E' . $i,  $detalle_pago)
			->setCellValue('F' . $i,  $numero_cheque)
			->setCellValue('G' . $i,  date("d/m/Y", strtotime($fecha_pago)))
			->setCellValue('H' . $i,  $estado_pago)
			->setCellValue('I' . $i,  $detalle_unido)
			->setCellValue('J' . $i,  "Egreso")
			->setCellValue('K' . $i,  $numero_ing_egr)
			->setCellValue('L' . $i,  $id_diarios)
			->setCellValue('M' . $i,  $total_debe)
			->setCellValue('N' . $i,  $total_haber)
		;
		//$objPHPExcel->getActiveSheet()->getStyle('D' . $i . ':E' . $i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
		$i++;
	}

	/* for ($i = 'A'; $i <= 'I'; $i++) {
		$objPHPExcel->setActiveSheetIndex(0)
			->getColumnDimension($i)->setAutoSize(TRUE);
	} */


	// Se asigna el nombre a la hoja
	$objPHPExcel->getActiveSheet()->setTitle('ConciliacionBancaria');

	// Se activa la hoja para que sea la que se muestre cuando el archivo se abre
	$objPHPExcel->setActiveSheetIndex(0);
	$objPHPExcel->getActiveSheet(0)->freezePaneByColumnAndRow(0, 8);

	// Se manda el archivo al navegador web, con el nombre que se indica (Excel2007)
	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	header('Content-Disposition: attachment;filename="ConciliacionBancaria.xlsx"');
	header('Cache-Control: max-age=0');

	$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
	$objWriter->save('php://output');
	exit;
} else {
	echo ('No es posible generar el archivo.');
}
