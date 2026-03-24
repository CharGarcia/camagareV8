<?php
include("../conexiones/conectalogin.php");
include("../helpers/helpers.php");
$con = conenta_login();
session_start();
$id_empresa = $_SESSION['id_empresa'];

$tipo_reporte = mysqli_real_escape_string($con, (strip_tags($_POST['tipo_reporte'], ENT_QUOTES)));
$id_empleado = mysqli_real_escape_string($con, (strip_tags($_POST['id_empleado'], ENT_QUOTES)));
$periodo = mysqli_real_escape_string($con, (strip_tags($_POST['periodo'], ENT_QUOTES)));
$desde = $_POST['desde'];
$hasta = $_POST['hasta'];
$id_novedad = $tipo_reporte;

if ($tipo_reporte == 'Q') {
	//reporte_quincena($con, $id_empresa, $periodo, $id_empleado);
}

if (($tipo_reporte > 0 && $tipo_reporte < 15) || ($tipo_reporte == 'T')) {
	$resultado = datos_novedades($con, $id_empresa, $desde, $hasta, $id_novedad, $id_empleado);

	if (mysqli_num_rows($resultado) > 0) {
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
			->setTitle("Novedades roles")
			->setSubject("Novedades roles")
			->setDescription("Novedades roles")
			->setKeywords("Novedades roles")
			->setCategory("Novedades roles");

		//para sacar el nombre de la empresa
		$sql_empresa = mysqli_query($con, "SELECT * FROM empresas where id= '" . $id_empresa . "'");
		$empresa_info = mysqli_fetch_array($sql_empresa);
		$tituloEmpresa = $empresa_info['nombre'];

		$tituloReporte = "Novedades de nómina desde: " . $desde . " hasta: " . $hasta;
		$titulosColumnas = array('Período', 'Nombres y apellidos', 'Documento', 'Novedad', 'Detalle', 'Aplica en', 'Aporta al IESS', 'Valor');

		$objPHPExcel->setActiveSheetIndex(0)
			->mergeCells('A1:H1')
			->mergeCells('A2:H2');

		// Se agregan los titulos del reporte
		$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('A1',  $tituloEmpresa)
			->setCellValue('A2',  $tituloReporte)
			->setCellValue('A3',  $titulosColumnas[0])
			->setCellValue('B3',  $titulosColumnas[1])
			->setCellValue('C3',  $titulosColumnas[2])
			->setCellValue('D3',  $titulosColumnas[3])
			->setCellValue('E3',  $titulosColumnas[4])
			->setCellValue('F3',  $titulosColumnas[5])
			->setCellValue('G3',  $titulosColumnas[6])
			->setCellValue('H3',  $titulosColumnas[7]);
		$i = 4;

		while ($fila = mysqli_fetch_array($resultado)) {
			foreach (novedades_sueldos() as $novedades) {
				if (intval($novedades['codigo']) === intval($fila['id_novedad'])) {
					$novedad = $novedades['nombre'];
				}
			}
			$objPHPExcel->setActiveSheetIndex(0)
				->setCellValue('A' . $i,  $fila['mes_ano'])
				->setCellValue('B' . $i,  strtoupper($fila['nombres_apellidos']))
				->setCellValue('C' . $i,  "=\"" . $fila['documento'] . "\"")
				->setCellValue('D' . $i,  strtoupper($novedad))
				->setCellValue('E' . $i,  strtoupper($fila['detalle']))
				->setCellValue('F' . $i,  $fila['aplica_en'] == 'R' ? 'ROL DE PAGOS' : 'QUINCENA')
				->setCellValue('G' . $i,  $fila['iess'] == '0' ? 'NO' : 'SI')
				->setCellValue('H' . $i, number_format($fila['valor'], 2, '.', ''));
			$i++;
		}
		$objPHPExcel->getActiveSheet()->getStyle('H4:H' . $i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);


		for ($i = 'A'; $i <= 'H'; $i++) {
			$objPHPExcel->setActiveSheetIndex(0)->getColumnDimension($i)->setAutoSize(TRUE);
		}

		$objPHPExcel->setActiveSheetIndex(0);
		$objPHPExcel->getActiveSheet(0)->freezePaneByColumnAndRow(0, 4);
		$objPHPExcel->getActiveSheet(0)->setTitle('Novedades');
		genera_excel($objPHPExcel, "Novedades_" . $desde . " - " . $hasta);
	} else {
		echo ('No hay resultados para mostrar');
	}
}

if ($tipo_reporte == 'R') {
	$resultado = datos_roles($con, $id_empresa, $periodo, $id_empleado);
	if (mysqli_num_rows($resultado) > 0) {
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
			->setTitle("Roles de pago")
			->setSubject("Roles de pago")
			->setDescription("Roles de pago")
			->setKeywords("Roles de pago")
			->setCategory("Roles de pago");

		//para sacar el nombre de la empresa
		$sql_empresa = mysqli_query($con, "SELECT * FROM empresas where id= '" . $id_empresa . "'");
		$empresa_info = mysqli_fetch_array($sql_empresa);
		$tituloEmpresa = $empresa_info['nombre'];

		$tituloReporte = "Roles de pago detallado " . $periodo;
		$titulosColumnas = array(
			'Documento', 'Nombres y apellidos', 'Cargo', 'Departamento', 'Días laborados', 'Días no laborados', 'sueldo', 'Horas nocturas', 'Valor horas nocturnas', 'Aporta Iess?', 'Horas suplementarias', 'Valor horas suplementarias', 'Aporta Iess?', 'Horas extraordinarias', 'Valor horas extraordinarias', 'Aporta Iess?', 'Detalle de horas laboradas', 'Ingresos fijos mensuales', 'Aporta Iess?', 'Detalle de ingresos fijos mensuales', 'Otros ingresos', 'Aporta Iess?', 'Detalle de otros ingresos', 'Descuentos', 'Detalle de descuentos',
			'Anticípos', 'Detalle de anticípos', 'Préstamos empresa', 'Detalle de préstamos de empresa', 'Préstamos hipotecarios', 'Detalle de préstamos hipotecarios',
			'Préstamos quirografarios', 'Detalle de préstamos quirografarios', 'Descuentos fijos mensuales', 'Detalle de descuentos fijos mensuales', 'Aporte personal', 'Aporte patronal',
			'Décimo tercero', 'Décimo cuarto', 'Fondos de reserva', 'Líquido a recibir'
		);

		$objPHPExcel->setActiveSheetIndex(0)
			->mergeCells('A1:H1')
			->mergeCells('A2:H2');

		// Se agregan los titulos del reporte
		$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('A1',  $tituloEmpresa)
			->setCellValue('A2',  $tituloReporte)
			->setCellValue('A3',  $titulosColumnas[0])
			->setCellValue('B3',  $titulosColumnas[1])
			->setCellValue('C3',  $titulosColumnas[2])
			->setCellValue('D3',  $titulosColumnas[3])
			->setCellValue('E3',  $titulosColumnas[4])
			->setCellValue('F3',  $titulosColumnas[5])
			->setCellValue('G3',  $titulosColumnas[6])
			->setCellValue('H3',  $titulosColumnas[7])
			->setCellValue('I3',  $titulosColumnas[8])
			->setCellValue('J3',  $titulosColumnas[9])
			->setCellValue('K3',  $titulosColumnas[10])
			->setCellValue('L3',  $titulosColumnas[11])
			->setCellValue('M3',  $titulosColumnas[12])
			->setCellValue('N3',  $titulosColumnas[13])
			->setCellValue('O3',  $titulosColumnas[14])
			->setCellValue('P3',  $titulosColumnas[15])
			->setCellValue('Q3',  $titulosColumnas[16])
			->setCellValue('R3',  $titulosColumnas[17])
			->setCellValue('S3',  $titulosColumnas[18])
			->setCellValue('T3',  $titulosColumnas[19])
			->setCellValue('U3',  $titulosColumnas[20])
			->setCellValue('V3',  $titulosColumnas[21])
			->setCellValue('W3',  $titulosColumnas[22])
			->setCellValue('X3',  $titulosColumnas[23])
			->setCellValue('Y3',  $titulosColumnas[24])
			->setCellValue('Z3',  $titulosColumnas[25])
			->setCellValue('AA3',  $titulosColumnas[26])
			->setCellValue('AB3',  $titulosColumnas[27])
			->setCellValue('AC3',  $titulosColumnas[28])
			->setCellValue('AD3',  $titulosColumnas[29])
			->setCellValue('AE3',  $titulosColumnas[30])
			->setCellValue('AF3',  $titulosColumnas[31])
			->setCellValue('AG3',  $titulosColumnas[32])
			->setCellValue('AH3',  $titulosColumnas[33])
			->setCellValue('AI3',  $titulosColumnas[34])
			->setCellValue('AJ3',  $titulosColumnas[35])
			->setCellValue('AK3',  $titulosColumnas[36])
			->setCellValue('AL3',  $titulosColumnas[37])
			->setCellValue('AM3',  $titulosColumnas[38])
			->setCellValue('AN3',  $titulosColumnas[39])
			->setCellValue('AO3',  $titulosColumnas[40]);

		$i = 4;

		while ($fila = mysqli_fetch_array($resultado)) {
			$valor_hora_normal = number_format(($fila['sueldo'] / 240), 2, '.', '');
			$dias_no_laborados = novedades($con, $id_empresa, $fila['id_empleado'], $periodo, '10', 'R')['valor'];
			$horas_nocturnas = novedades($con, $id_empresa, $fila['id_empleado'], $periodo, '4', 'R')['valor'];
			$horas_suplementarias = novedades($con, $id_empresa, $fila['id_empleado'], $periodo, '5', 'R')['valor'];
			$horas_extraordinarias = novedades($con, $id_empresa, $fila['id_empleado'], $periodo, '6', 'R')['valor'];
			$total_hora_nocturna = number_format((($valor_hora_normal) * (1 + salarios($con, $periodo)['hora_nocturna'] / 100)) * $horas_nocturnas, 2, '.', '');
			$total_hora_suplementaria = number_format((($valor_hora_normal) * (1 + salarios($con, $periodo)['hora_suplementaria'] / 100)) * $horas_suplementarias, 2, '.', '');
			$total_hora_extraordinaria = number_format((($valor_hora_normal) * (1 + salarios($con, $periodo)['hora_extraordinaria'] / 100)) * $horas_extraordinarias, 2, '.', '');
			$iess_horas_nocturnas = novedades($con, $id_empresa, $fila['id_empleado'], $periodo, '4', 'R')['iess'];
			$iess_horas_suplementarias = novedades($con, $id_empresa, $fila['id_empleado'], $periodo, '5', 'R')['iess'];
			$iess_horas_extraordinarias = novedades($con, $id_empresa, $fila['id_empleado'], $periodo, '6', 'R')['iess'];
			$detalle_horas_laboradas = novedades($con, $id_empresa, $fila['id_empleado'], $periodo, '4', 'R')['detalle'] . " " . novedades($con, $id_empresa, $fila['id_empleado'], $periodo, '5', 'R')['detalle'] . " " . novedades($con, $id_empresa, $fila['id_empleado'], $periodo, '6', 'R')['detalle'];
			$ingresos_fijos = ing_des_fijos($con, $id_empresa, $fila['id_empleado'], '1')['valor'];
			$ingresos_fijos_detalle = ing_des_fijos($con, $id_empresa, $fila['id_empleado'], '1')['detalle'];
			$ingresos_fijos_iess = ing_des_fijos($con, $id_empresa, $fila['id_empleado'], '1')['iess'];
			$descuentos_fijos = ing_des_fijos($con, $id_empresa, $fila['id_empleado'], '2')['valor'];
			$descuentos_fijos_detalle = ing_des_fijos($con, $id_empresa, $fila['id_empleado'], '2')['detalle'];
			$valor_otros_ingresos = novedades($con, $id_empresa, $fila['id_empleado'], $periodo, '1', 'R')['valor'];
			$iess_otros_ingresos = novedades($con, $id_empresa, $fila['id_empleado'], $periodo, '1', 'R')['iess'];
			$detalle_otros_ingresos = novedades($con, $id_empresa, $fila['id_empleado'], $periodo, '1', 'R')['detalle'];
			$valor_descuento = novedades($con, $id_empresa, $fila['id_empleado'], $periodo, '2', 'R')['valor'];
			$detalle_descuentos = novedades($con, $id_empresa, $fila['id_empleado'], $periodo, '2', 'R')['detalle'];
			$valor_anticipos = novedades($con, $id_empresa, $fila['id_empleado'], $periodo, '3', 'R')['valor'];
			$detalle_anticipos = novedades($con, $id_empresa, $fila['id_empleado'], $periodo, '3', 'R')['detalle'];
			$valor_prestamos_empresa = novedades($con, $id_empresa, $fila['id_empleado'], $periodo, '9', 'R')['valor'];
			$detalle_prestamos_empresa = novedades($con, $id_empresa, $fila['id_empleado'], $periodo, '9', 'R')['detalle'];
			$valor_prestamos_hipotecarios = novedades($con, $id_empresa, $fila['id_empleado'], $periodo, '8', 'R')['valor'];
			$detalle_prestamos_hipotecarios = novedades($con, $id_empresa, $fila['id_empleado'], $periodo, '8', 'R')['detalle'];
			$valor_prestamos_quirografarios = novedades($con, $id_empresa, $fila['id_empleado'], $periodo, '7', 'R')['valor'];
			$detalle_prestamos_quirografarios = novedades($con, $id_empresa, $fila['id_empleado'], $periodo, '7', 'R')['detalle'];

			$objPHPExcel->setActiveSheetIndex(0)
				->setCellValue('A' . $i,  "=\"" . $fila['documento'] . "\"")
				->setCellValue('B' . $i,  $fila['empleado'])
				->setCellValue('C' . $i,  $fila['cargo'])
				->setCellValue('D' . $i,  $fila['departamento'])
				->setCellValue('E' . $i,  $fila['dias_laborados'])
				->setCellValue('F' . $i,  $dias_no_laborados)
				->setCellValue('G' . $i,  number_format($fila['sueldo_mes'], 2, '.', ''))
				->setCellValue('H' . $i,  $horas_nocturnas)
				->setCellValue('I' . $i,  $total_hora_nocturna)
				->setCellValue('J' . $i,  $iess_horas_nocturnas)
				->setCellValue('K' . $i,  $horas_suplementarias)
				->setCellValue('L' . $i,  $total_hora_suplementaria)
				->setCellValue('M' . $i,  $iess_horas_suplementarias)
				->setCellValue('N' . $i,  $horas_extraordinarias)
				->setCellValue('O' . $i,  $total_hora_extraordinaria)
				->setCellValue('P' . $i,  $iess_horas_extraordinarias)
				->setCellValue('Q' . $i,  $detalle_horas_laboradas)
				->setCellValue('R' . $i,  $ingresos_fijos)
				->setCellValue('S' . $i,  $ingresos_fijos_iess)
				->setCellValue('T' . $i,  $ingresos_fijos_detalle)
				->setCellValue('U' . $i,  $valor_otros_ingresos)
				->setCellValue('V' . $i,  $iess_otros_ingresos)
				->setCellValue('W' . $i,  $detalle_otros_ingresos)
				->setCellValue('X' . $i,  $valor_descuento)
				->setCellValue('Y' . $i,  $detalle_descuentos)
				->setCellValue('Z' . $i,  $valor_anticipos)
				->setCellValue('AA' . $i, $detalle_anticipos)
				->setCellValue('AB' . $i,  $valor_prestamos_empresa)
				->setCellValue('AC' . $i,  $detalle_prestamos_empresa)
				->setCellValue('AD' . $i,  $valor_prestamos_hipotecarios)
				->setCellValue('AE' . $i,  $detalle_prestamos_hipotecarios)
				->setCellValue('AF' . $i,  $valor_prestamos_quirografarios)
				->setCellValue('AG' . $i,  $detalle_prestamos_quirografarios)
				->setCellValue('AH' . $i,  $descuentos_fijos)
				->setCellValue('AI' . $i,  $descuentos_fijos_detalle)
				->setCellValue('AJ' . $i,  number_format($fila['aporte_personal'], 2, '.', ''))
				->setCellValue('AK' . $i,  number_format($fila['aporte_patronal'], 2, '.', ''))
				->setCellValue('AL' . $i,  number_format($fila['tercero'], 2, '.', ''))
				->setCellValue('AM' . $i,  number_format($fila['cuarto'], 2, '.', ''))
				->setCellValue('AN' . $i,  number_format($fila['fondo_reserva'], 2, '.', ''))
				->setCellValue('AO' . $i,  number_format($fila['a_recibir'], 2, '.', ''));
			$i++;
		}
		//$objPHPExcel->getActiveSheet()->getStyle('G4:AM'.$i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_TEXT);
		$objPHPExcel->getActiveSheet()->getStyle('G4:AO' . $i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);


		for ($i = 'A'; $i <= 'AO'; $i++) {
			$objPHPExcel->setActiveSheetIndex(0)->getColumnDimension($i)->setAutoSize(TRUE);
		}

		$objPHPExcel->setActiveSheetIndex(0);
		$objPHPExcel->getActiveSheet(0)->freezePaneByColumnAndRow(0, 4);
		$objPHPExcel->getActiveSheet(0)->setTitle('Detallado');
		//HASTA AQUI EL ROL DETALLADO DE LA HOJA 1


		//DESDE AQUI EL ROL FORMATO UNO
		$objPHPExcel->createSheet();
		$objPHPExcel->setActiveSheetIndex(1)->setTitle('Formato_uno');
		$titulosColumnas = array(
			'EMPLEADO', 'CEDULA', 'CARGO', 'SBU', 'FONDOS DE RESERVA 8.33%', 'BONO POR CUMPLIMIENTO', 'COMISIONES', 'INCENTIVOS',
			'TOTAL', 'IESS 9.45%', 'ACUMULA FONDOS DE RESERVA', 'QUINCENA Y ANTICIPOS', 'PRÉSTAMOS QUIROGRAFARIOS', 'PRÉSTAMOS HIPOTECARIOS',
			'COMISIONES-BONO ANTICIPADO', 'SEGURO VEHICULAR', 'SEGURO MÉDICO', 'RETENCIÓN JUDICIAL', 'ASOCIACIÓN TRABAJADORES', 'PRÉSTAMOS EMPRESA', 'OTROS', 'TOTAL', 'VALOR A RECIBIR'
		);

		$objPHPExcel->setActiveSheetIndex(1)
			->mergeCells('A1:H1')
			->mergeCells('A2:C2')
			->mergeCells('D2:I2')
			->mergeCells('J2:V2');

		$objPHPExcel->setActiveSheetIndex(1)
			->setCellValue('A1',  $tituloEmpresa)
			->setCellValue('A2',  "Roles de pago " . $periodo)
			->setCellValue('D2',  "INGRESOS")
			->setCellValue('J2',  "DESCUENTOS")
			->setCellValue('A3',  $titulosColumnas[0])
			->setCellValue('B3',  $titulosColumnas[1])
			->setCellValue('C3',  $titulosColumnas[2])
			->setCellValue('D3',  $titulosColumnas[3])
			->setCellValue('E3',  $titulosColumnas[4])
			->setCellValue('F3',  $titulosColumnas[5])
			->setCellValue('G3',  $titulosColumnas[6])
			->setCellValue('H3',  $titulosColumnas[7])
			->setCellValue('I3',  $titulosColumnas[8])
			->setCellValue('J3',  $titulosColumnas[9])
			->setCellValue('K3',  $titulosColumnas[10])
			->setCellValue('L3',  $titulosColumnas[11])
			->setCellValue('M3',  $titulosColumnas[12])
			->setCellValue('N3',  $titulosColumnas[13])
			->setCellValue('O3',  $titulosColumnas[14])
			->setCellValue('P3',  $titulosColumnas[15])
			->setCellValue('Q3',  $titulosColumnas[16])
			->setCellValue('R3',  $titulosColumnas[17])
			->setCellValue('S3',  $titulosColumnas[18])
			->setCellValue('T3',  $titulosColumnas[19])
			->setCellValue('U3',  $titulosColumnas[20])
			->setCellValue('V3',  $titulosColumnas[21])
			->setCellValue('W3',  $titulosColumnas[22]);
		$p = 4;
		$resultado_dos = datos_roles($con, $id_empresa, $periodo, $condicion_empleado);

		while ($fila = mysqli_fetch_array($resultado_dos)) {
			$valor_hora_normal = number_format(($fila['sueldo'] / 240), 2, '.', '');
			$dias_no_laborados = novedades($con, $id_empresa, $fila['id_empleado'], $periodo, '10', 'R')['valor'];
			$horas_nocturnas = novedades($con, $id_empresa, $fila['id_empleado'], $periodo, '4', 'R')['valor'];
			$horas_suplementarias = novedades($con, $id_empresa, $fila['id_empleado'], $periodo, '5', 'R')['valor'];
			$horas_extraordinarias = novedades($con, $id_empresa, $fila['id_empleado'], $periodo, '6', 'R')['valor'];
			$total_hora_nocturna = number_format((($valor_hora_normal) * (1 + salarios($con, $periodo)['hora_nocturna'] / 100)) * $horas_nocturnas, 2, '.', '');
			$total_hora_suplementaria = number_format((($valor_hora_normal) * (1 + salarios($con, $periodo)['hora_suplementaria'] / 100)) * $horas_suplementarias, 2, '.', '');
			$total_hora_extraordinaria = number_format((($valor_hora_normal) * (1 + salarios($con, $periodo)['hora_extraordinaria'] / 100)) * $horas_extraordinarias, 2, '.', '');
			$iess_horas_nocturnas = novedades($con, $id_empresa, $fila['id_empleado'], $periodo, '4', 'R')['iess'];
			$iess_horas_suplementarias = novedades($con, $id_empresa, $fila['id_empleado'], $periodo, '5', 'R')['iess'];
			$iess_horas_extraordinarias = novedades($con, $id_empresa, $fila['id_empleado'], $periodo, '6', 'R')['iess'];
			$detalle_horas_laboradas = novedades($con, $id_empresa, $fila['id_empleado'], $periodo, '4', 'R')['detalle'] . " " . novedades($con, $id_empresa, $fila['id_empleado'], $periodo, '5', 'R')['detalle'] . " " . novedades($con, $id_empresa, $fila['id_empleado'], $periodo, '6', 'R')['detalle'];
			$ingresos_fijos = ing_des_fijos($con, $id_empresa, $fila['id_empleado'], '1')['valor'];
			$ingresos_fijos_detalle = ing_des_fijos($con, $id_empresa, $fila['id_empleado'], '1')['detalle'];
			$ingresos_fijos_iess = ing_des_fijos($con, $id_empresa, $fila['id_empleado'], '1')['iess'];
			$descuentos_fijos = ing_des_fijos($con, $id_empresa, $fila['id_empleado'], '2')['valor'];
			$descuentos_fijos_detalle = ing_des_fijos($con, $id_empresa, $fila['id_empleado'], '2')['detalle'];
			$valor_otros_ingresos = novedades($con, $id_empresa, $fila['id_empleado'], $periodo, '1', 'R')['valor'];
			$iess_otros_ingresos = novedades($con, $id_empresa, $fila['id_empleado'], $periodo, '1', 'R')['iess'];
			$detalle_otros_ingresos = novedades($con, $id_empresa, $fila['id_empleado'], $periodo, '1', 'R')['detalle'];
			$valor_descuento = novedades($con, $id_empresa, $fila['id_empleado'], $periodo, '2', 'R')['valor'];
			$detalle_descuentos = novedades($con, $id_empresa, $fila['id_empleado'], $periodo, '2', 'R')['detalle'];
			$valor_anticipos = novedades($con, $id_empresa, $fila['id_empleado'], $periodo, '3', 'R')['valor'];
			$detalle_anticipos = novedades($con, $id_empresa, $fila['id_empleado'], $periodo, '3', 'R')['detalle'];
			$valor_prestamos_empresa = novedades($con, $id_empresa, $fila['id_empleado'], $periodo, '9', 'R')['valor'];
			$detalle_prestamos_empresa = novedades($con, $id_empresa, $fila['id_empleado'], $periodo, '9', 'R')['detalle'];
			$valor_prestamos_hipotecarios = novedades($con, $id_empresa, $fila['id_empleado'], $periodo, '8', 'R')['valor'];
			$detalle_prestamos_hipotecarios = novedades($con, $id_empresa, $fila['id_empleado'], $periodo, '8', 'R')['detalle'];
			$valor_prestamos_quirografarios = novedades($con, $id_empresa, $fila['id_empleado'], $periodo, '7', 'R')['valor'];
			$detalle_prestamos_quirografarios = novedades($con, $id_empresa, $fila['id_empleado'], $periodo, '7', 'R')['detalle'];

			$objPHPExcel->setActiveSheetIndex(1)
				->setCellValue('A' . $p,  $fila['empleado'])
				->setCellValue('B' . $p,  "=\"" . $fila['documento'] . "\"")
				->setCellValue('C' . $p,  $fila['cargo'])
				->setCellValue('D' . $p,  number_format($fila['sueldo_mes'], 2, '.', ''))
				->setCellValue('E' . $p, 	number_format($fila['fondo_reserva'], 2, '.', ''))
				->setCellValue('F' . $p,  $valor_otros_ingresos)
				->setCellValue('G' . $p,  $detalle_otros_ingresos)
				->setCellValue('H' . $p,  '')
				->setCellValue('I' . $p,  $fila['sueldo_mes'] + $fila['fondo_reserva'] + $valor_otros_ingresos)
				->setCellValue('J' . $p,  number_format($fila['aporte_personal'], 2, '.', ''))
				->setCellValue('K' . $p,  $fila['estado_fondo_reserva'] == '2' ? 'SI' : 'NO')
				->setCellValue('L' . $p,  number_format($fila['quincena'], 2, '.', ''))
				->setCellValue('M' . $p,  $valor_prestamos_quirografarios)
				->setCellValue('N' . $p,  $valor_prestamos_hipotecarios)
				->setCellValue('O' . $p,  $valor_anticipos)
				->setCellValue('P' . $p,  '')
				->setCellValue('Q' . $p,  '')
				->setCellValue('R' . $p,  '')
				->setCellValue('S' . $p,  '')
				->setCellValue('T' . $p,  $valor_prestamos_empresa)
				->setCellValue('U' . $p,  $valor_descuento + $descuentos_fijos)
				->setCellValue('V' . $p,  $fila['aporte_personal'] + $fila['quincena'] + $valor_prestamos_quirografarios + $valor_prestamos_hipotecarios + $valor_anticipos + $valor_prestamos_empresa + $valor_descuento + $descuentos_fijos)
				->setCellValue('W' . $p,  number_format($fila['a_recibir'], 2, '.', ''));
			$p++;
		}

		//$objPHPExcel->getActiveSheet(1)->getStyle('B4:B'.$p)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_TEXT);
		$objPHPExcel->getActiveSheet(1)->getStyle('D4:W' . $p)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);


		for ($p = 'A'; $p <= 'W'; $p++) {
			$objPHPExcel->setActiveSheetIndex(1)->getColumnDimension($p)->setAutoSize(TRUE);
		}


		$objPHPExcel->getActiveSheet(1)->freezePaneByColumnAndRow(0, 4);
		$objPHPExcel->setActiveSheetIndex(0);

		//hasta aqui la segunda hoja
		genera_excel($objPHPExcel, "RolesPago_" . $periodo);
	} else {
		echo ('No hay resultados para mostrar');
	}
}

function genera_excel($objPHPExcel, $nombre_archivo)
{
	// Se asigna el nombre a la hoja
	$nombre_archivo = $nombre_archivo . ".xlsx";

	// Se manda el archivo al navegador web, con el nombre que se indica (Excel2007)
	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	header('Content-Disposition: attachment;filename="' . $nombre_archivo . '"');
	header('Cache-Control: max-age=0');

	$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
	$objWriter->save('php://output');
	exit;
}

function ing_des_fijos($con, $id_empresa, $id_empleado, $tipo)
{
	$detalle_fijos = mysqli_query($con, "SELECT round(sum(det.valor),2) as valor, 
			GROUP_CONCAT(det.detalle SEPARATOR ', ') AS detalle, 
			GROUP_CONCAT(if(det.aporta_al_iess=false,'NO','SI') SEPARATOR ', ') as iess  
			FROM detalle_sueldos as det INNER JOIN sueldos as sue ON sue.id=det.id_sueldo
			WHERE sue.status=1 and sue.id_empleado = '" . $id_empleado . "' and 
			sue.id_empresa= '" . $id_empresa . "' and det.tipo='" . $tipo . "' group by det.tipo");
	$fijos = mysqli_fetch_array($detalle_fijos);
	return $fijos;
}

function novedades($con, $id_empresa, $id_empleado, $periodo, $id_novedad, $aplica)
{
	$detalle_novedades = mysqli_query($con, "SELECT round(sum(valor),2) as valor, 
			GROUP_CONCAT(detalle SEPARATOR ', ') AS detalle,
			GROUP_CONCAT(if(iess=0,'NO','SI') SEPARATOR ', ') as iess
			 FROM novedades WHERE id_empleado = '" . $id_empleado . "' and 
			id_empresa= '" . $id_empresa . "' and mes_ano='" . $periodo . "' and aplica_en='" . $aplica . "' and
			id_novedad='" . $id_novedad . "' and status !=0 group by id_novedad, id_empleado, mes_ano, id_empresa");
	$novedades = mysqli_fetch_array($detalle_novedades);
	return $novedades;
}

function salarios($con, $periodo)
{
	$detalle_salarios = mysqli_query($con, "SELECT * FROM salarios 
			WHERE ano = '" . substr($periodo, -4) . "'");
	$salarios = mysqli_fetch_array($detalle_salarios);
	return $salarios;
}


function datos_roles($con, $id_empresa, $periodo, $id_empleado)
{
	if (empty($id_empleado)) {
		$condicion_empleado = "";
	} else {
		$condicion_empleado = " and det.id_empleado=" . $id_empleado;
	}

	$resultado = mysqli_query($con, "SELECT emp.id as id_empleado, rol.mes_ano as mes_ano, emp.nombres_apellidos as empleado,
			emp.documento as documento, det.dias_laborados as dias_laborados, det.sueldo as sueldo_mes, sue.sueldo as sueldo, 
			det.ingresos_gravados as ingresos_gravados, det.ingresos_excentos as ingresos_excentos,
			det.aporte_patronal as aporte_patronal, det.quincena as quincena, det.aporte_personal as aporte_personal,  det.total_egresos as total_egresos, det.tercero as tercero, 
			det.cuarto as cuarto, det.fondo_reserva as fondo_reserva, det.a_recibir as a_recibir, sue.cargo_empresa as cargo, 
			dep.nombre as departamento, sue.fondo_reserva as estado_fondo_reserva 
			FROM detalle_rolespago as det 
			INNER JOIN rolespago as rol ON rol.id=det.id_rol
			INNER JOIN empleados as emp ON emp.id=det.id_empleado 
			LEFT JOIN sueldos as sue ON sue.id_empleado=det.id_empleado
			LEFT JOIN departamentos as dep ON dep.id=sue.departamento
			  WHERE rol.id_empresa = '" . $id_empresa . "' and rol.mes_ano = '" . $periodo . "' $condicion_empleado and rol.status=1 order by emp.nombres_apellidos asc");
	return $resultado;
}

function datos_novedades($con, $id_empresa, $desde, $hasta, $id_novedad, $id_empleado)
{

	if (empty($id_empleado)) {
		$condicion_empleado = "";
	} else {
		$condicion_empleado = " and nov.id_empleado=" . $id_empleado;
	}

	if ($id_novedad == 'T') {
		$condicion_novedad = "";
	} else {
		$condicion_novedad = " and nov.id_novedad = " . $id_novedad;
	}

	$resultado = mysqli_query($con, "SELECT * FROM novedades as nov 
	INNER JOIN empleados as emp 
	ON emp.id=nov.id_empleado 
	WHERE nov.id_empresa = '" . $id_empresa . "' 
	and DATE_FORMAT(nov.fecha_novedad, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
	and '" . date("Y/m/d", strtotime($hasta)) . "' $condicion_empleado $condicion_novedad and nov.status=1 order by emp.nombres_apellidos asc");
	return $resultado;
}
