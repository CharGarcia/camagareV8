<?php
include("../ajax/informes_contables.php");
//include("../helpers/helpers.php");
$con = conenta_login();
//	session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];

$desde = $_POST['fecha_desde'];
$hasta = $_POST['fecha_hasta'];
$id_proyecto = $_POST['id_proyecto'];

$sql_proyecto = mysqli_query($con, "SELECT * FROM proyectos where id= '" . $id_proyecto . "'");
$row_proyecto = mysqli_fetch_array($sql_proyecto);
$proyecto = isset($row_proyecto['nombre']) ? " PROYECTO / CENTRO DE COSTOS : " . strtoupper($row_proyecto['nombre']) : "";

$informe = $_POST['nombre_informe'];
$nivel = $_POST['nivel'];
if ($nivel == '0') {
	$nivel_cuenta = "";
} else {
	$nivel_cuenta = " and nivel_cuenta = " . $nivel;
}

if ($informe == 'sri') {
	generar_balance($con, $ruc_empresa, $id_usuario, $desde, $hasta, '1', '6', $id_proyecto);
	$sql_delete = mysqli_query($con, "DELETE FROM balances_tmp WHERE ruc_empresa = '" . $ruc_empresa . "' and id_usuario='" . $id_usuario . "' and nivel_cuenta !='5'");
	$sql_update_pasivo = mysqli_query($con, "UPDATE balances_tmp SET valor=valor*-1 WHERE ruc_empresa = '" . $ruc_empresa . "' and mid(codigo_cuenta,1,1)='2' ");
	$sql_update_patrimonio = mysqli_query($con, "UPDATE balances_tmp SET valor=valor*-1 WHERE ruc_empresa = '" . $ruc_empresa . "' and mid(codigo_cuenta,1,1)='3' ");
	$sql_update_ingresos = mysqli_query($con, "UPDATE balances_tmp SET valor=valor*-1 WHERE ruc_empresa = '" . $ruc_empresa . "' and mid(codigo_cuenta,1,1)='4' ");
	$sql_update = mysqli_query($con, "UPDATE balances_tmp as bal_tmp INNER JOIN plan_cuentas as plan ON bal_tmp.codigo_cuenta=plan.codigo_cuenta SET bal_tmp.codigo_cuenta = plan.codigo_sri WHERE plan.ruc_empresa = '" . $ruc_empresa . "' and bal_tmp.ruc_empresa = '" . $ruc_empresa . "' ");
	$consulta = mysqli_query($con, "SELECT codigo_cuenta as codigo_cuenta, sum(valor) as valor FROM balances_tmp WHERE ruc_empresa = '" . $ruc_empresa . "' and id_usuario='" . $id_usuario . "' group by codigo_cuenta");
	$errores = control_errores($con, $ruc_empresa, $desde, $hasta, 'excel');
}


if ($informe == '1') {
	$resumen_activo_pasivo_patrimonio = resumen_activo_pasivo_patrimonio($con, $ruc_empresa, $id_usuario, $desde, $hasta, $id_proyecto);
	$consulta = mysqli_query($con, "SELECT nivel_cuenta as nivel, codigo_cuenta as codigo_cuenta, nombre_cuenta as nombre_cuenta, sum(valor) as valor  FROM balances_tmp WHERE ruc_empresa = '" . $ruc_empresa . "' $nivel_cuenta group by codigo_cuenta, nivel_cuenta");
	$activo = $resumen_activo_pasivo_patrimonio['activo'];
	$pasivo = $resumen_activo_pasivo_patrimonio['pasivo'];
	$patrimonio = $resumen_activo_pasivo_patrimonio['patrimonio'];
	$resultado_utilidad = utilidad_perdida($con, $ruc_empresa, $id_usuario, $desde, $hasta, $id_proyecto);
	$resultado_ejercicio = $resultado_utilidad['resultado'];
	$utilidad_ejercicio = $resultado_utilidad['valor'];
	$errores = control_errores($con, $ruc_empresa, $desde, $hasta, 'excel');
}

if ($informe == '1P') {
	$consulta = generar_balance_periodos($con, $ruc_empresa, $desde, $hasta, 1, 3, $id_proyecto);
	$errores = control_errores($con, $ruc_empresa, $desde, $hasta, 'excel');
}

if ($informe == '2') {
	generar_balance($con, $ruc_empresa, $id_usuario, $desde, $hasta, '4', '6', $id_proyecto);
	$consulta = mysqli_query($con, "SELECT nivel_cuenta as nivel, codigo_cuenta as codigo_cuenta, nombre_cuenta as nombre_cuenta, sum(valor) as valor  FROM balances_tmp WHERE ruc_empresa = '" . $ruc_empresa . "' $nivel_cuenta group by codigo_cuenta");
	$resultado_utilidad = utilidad_perdida($con, $ruc_empresa, $id_usuario, $desde, $hasta);
	$resultado_ejercicio = $resultado_utilidad['resultado'];
	$utilidad_ejercicio = $resultado_utilidad['valor'];
	$errores = control_errores($con, $ruc_empresa, $desde, $hasta, 'excel');
}

if ($informe == '2P') {
	$consulta = generar_balance_periodos($con, $ruc_empresa, $desde, $hasta, 4, 6, $id_proyecto);
	$errores = control_errores($con, $ruc_empresa, $desde, $hasta, 'excel');
}

if ($informe == '2PP') {
	$consulta = generar_resultados_presupuestado($con, $ruc_empresa, $desde, $hasta, $id_proyecto);
	$errores = control_errores($con, $ruc_empresa, $desde, $hasta, 'excel');
}

if ($informe == '3') {
	$consulta = mysqli_query($con, "SELECT plan.codigo_cuenta as codigo_cuenta, plan.nombre_cuenta as nombre_cuenta, sum(det_dia.debe) as debe, sum(det_dia.haber) as haber FROM detalle_diario_contable as det_dia INNER JOIN encabezado_diario as enc_dia ON enc_dia.codigo_unico=det_dia.codigo_unico INNER JOIN plan_cuentas as plan ON plan.id_cuenta=det_dia.id_cuenta WHERE plan.ruc_empresa = '" . $ruc_empresa . "' and enc_dia.ruc_empresa = '" . $ruc_empresa . "' and det_dia.ruc_empresa = '" . $ruc_empresa . "' and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' and mid(plan.codigo_cuenta,1,1) >= '1' and mid(plan.codigo_cuenta,1,1) <= '6' and enc_dia.estado !='ANULADO' and plan.nivel_cuenta='5' group by plan.id_cuenta order by plan.codigo_cuenta asc");
	$errores = control_errores($con, $ruc_empresa, $desde, $hasta, 'excel');
}


//if(mysqli_num_fields($consulta) > 0 ){
if ($consulta) {
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
		->setTitle("Estados Financieros")
		->setSubject("Estados Financieros")
		->setDescription("Estados Financieros")
		->setKeywords("Estados Financieros")
		->setCategory("Estados Financieros");

	//para sacar el nombre de la empresa
	$sql_empresa = mysqli_query($con, "SELECT * FROM empresas where ruc= '" . $ruc_empresa . "'");
	$empresa_info = mysqli_fetch_array($sql_empresa);
	$tituloEmpresa = $empresa_info['nombre'];
	$rep_legal = $empresa_info['nom_rep_legal'];
	$nombre_contador = $empresa_info['nombre_contador'];
	$cedula_rep_legal = $empresa_info['ced_rep_legal'];
	$ruc_contador = $empresa_info['ruc_contador'];

	if ($informe == 'sri') {
		$tituloReporte = "BALANCES SRI";
		$fechaReporte = "DEL " . date("d-m-Y", strtotime($desde)) . " AL " . date("d-m-Y", strtotime($hasta));
		$tituloHoja = "Balances_sri";
		$titulolibro = "Balances_sri.xlsx";
		$titulosColumnas = array('', 'Código SRI', 'Valor', '', '', '', '', '');
	}
	if ($informe == '1') {
		$tituloReporte = "ESTADO DE SITUACIÓN FINANCIERA";
		$fechaReporte = "AL " . date("d-m-Y", strtotime($hasta));
		$tituloHoja = "Estado_situacion_financiera";
		$titulolibro = "Estado_situacion_financiera.xlsx";
		$titulosColumnas = array('Código', 'Código SRI', 'Cuenta', 'Nivel 5', 'Nivel 4', 'Nivel 3', 'Nivel 2', 'Nivel 1');
	}
	if ($informe == '1P') {
		$tituloReporte = "ESTADO DE SITUACIÓN FINANCIERA POR PERÍODOS";
		$fechaReporte = "AL " . date("d-m-Y", strtotime($hasta));
		$tituloHoja = "Estado_situacion_financiera";
		$titulolibro = "ESF_periodos.xlsx";
		$periodos = "";
		$titulosColumnas = array('Código', 'Cuenta');
		$fechaInicio = new DateTime(date("Y/m/d", strtotime($desde)));
		$fechaFin = new DateTime(date("Y/m/d", strtotime($hasta)));
		for ($fecha = $fechaInicio; $fecha <= $fechaFin; $fecha->modify('+1 month')) {
			$mes = substr(obtenerNombreMes($fecha->format('m')), 0, 3);
			$ano = $fecha->format('y');
			$periodos = $mes . "-" . $ano;
			array_push($titulosColumnas, $periodos);
		}
		array_push($titulosColumnas, 'Total períodos');
		$titulosColumnas = $titulosColumnas;
	}

	if ($informe == '2') {
		$tituloReporte = "ESTADO DE RESULTADOS";
		$fechaReporte = "DEL " . date("d-m-Y", strtotime($desde)) . " AL " . date("d-m-Y", strtotime($hasta));
		$tituloHoja = "Estado_resultados";
		$titulolibro = "Estado_resultados.xlsx";
		$titulosColumnas = array('Código', 'Código SRI', 'Cuenta', 'Nivel 5', 'Nivel 4', 'Nivel 3', 'Nivel 2', 'Nivel 1');
	}
	if ($informe == '2P') {
		$tituloReporte = "ESTADO DE RESULTADOS POR PERÍODOS";
		$fechaReporte = "DEL " . date("d-m-Y", strtotime($desde)) . " AL " . date("d-m-Y", strtotime($hasta));
		$tituloHoja = "Estado_resultados";
		$titulolibro = "ER_periodos.xlsx";
		$periodos = "";
		$titulosColumnas = array('Código', 'Cuenta');
		$fechaInicio = new DateTime(date("Y/m/d", strtotime($desde)));
		$fechaFin = new DateTime(date("Y/m/d", strtotime($hasta)));
		for ($fecha = $fechaInicio; $fecha <= $fechaFin; $fecha->modify('+1 month')) {
			$mes = substr(obtenerNombreMes($fecha->format('m')), 0, 3);
			$ano = $fecha->format('y');
			$periodos = $mes . "-" . $ano;
			array_push($titulosColumnas, $periodos);
		}
		array_push($titulosColumnas, 'Total períodos');
		$titulosColumnas = $titulosColumnas;
	}
	if ($informe == '2PP') {
		$tituloReporte = "ESTADO DE RESULTADOS PRESUPUESTADO";
		$fechaReporte = "DEL " . date("d-m-Y", strtotime($desde)) . " AL " . date("d-m-Y", strtotime($hasta));
		$tituloHoja = "Estado_presupuestado";
		$titulolibro = "ER_presupuestado.xlsx";
		$periodos = "";
		$titulosColumnas = array('Código', 'Cuenta', 'Presupuesto');
		$fechaInicio = new DateTime(date("Y/m/d", strtotime($desde)));
		$fechaFin = new DateTime(date("Y/m/d", strtotime($hasta)));
		for ($fecha = $fechaInicio; $fecha <= $fechaFin; $fecha->modify('+1 month')) {
			$mes = substr(obtenerNombreMes($fecha->format('m')), 0, 3);
			$ano = $fecha->format('y');
			$periodos = $mes . "-" . $ano;
			array_push($titulosColumnas, $periodos);
		}
		array_push($titulosColumnas, 'Ejecutado');
		array_push($titulosColumnas, 'Por ejecutar');
		$titulosColumnas = $titulosColumnas;
	}

	if ($informe == '3') {
		$tituloReporte = "BALANCE DE COMPROBACIÓN";
		$fechaReporte = "DEL " . date("d-m-Y", strtotime($desde)) . " AL " . date("d-m-Y", strtotime($hasta));
		$tituloHoja = "Balance_comprobacion";
		$titulolibro = "Balance_comprobacion.xlsx";
		$titulosColumnas = array('Código', 'Cuenta', 'Debe', 'Haber', 'Saldo deudor', 'Saldo acreedor');
	}

	// Se agregan los titulos del reporte
	$objPHPExcel->setActiveSheetIndex(0)
		->setCellValue('A1',  $tituloEmpresa)
		->setCellValue('A2',  $tituloReporte)
		->setCellValue('A3',  $fechaReporte)
	;

	// Se agregan los titulos de las columnas para mostrar los datos de todos los reportes
	foreach (letras_columnas_excel($titulosColumnas) as $letra => $tituloColumna) {
		$objPHPExcel->setActiveSheetIndex(0)->setCellValue($letra . '4', $tituloColumna);
	}


	$i = 5;

	if ($informe == '1P') {
		$i = 5;
		$fechaInicio = new DateTime(date("Y/m/d", strtotime($desde)));
		$fechaFin = new DateTime(date("Y/m/d", strtotime($hasta)));
		for ($fecha = $fechaInicio; $fecha <= $fechaFin; $fecha->modify('+1 month')) {
			$mes = $fecha->format('m');
			$ano = $fecha->format('y');
			$mes_ano = $mes . "-" . $ano;
			$sumaColumna[$mes_ano] = 0;
		}
		foreach ($consulta as $codigo => $datosCuentas) {
			foreach ($datosCuentas as $cuenta => $datosPeriodo) {
				$objPHPExcel->setActiveSheetIndex(0)
					->setCellValue('A' . $i,  $codigo)
					->setCellValue('B' . $i,  $cuenta)
				;
				$total_fila = 0;
				$fechaInicio = new DateTime(date("Y/m/d", strtotime($desde)));
				$fechaFin = new DateTime(date("Y/m/d", strtotime($hasta)));
				$indice_letra = 2; //el indice 2 corresponde a la letra C de la columna excel
				for ($fecha = $fechaInicio; $fecha <= $fechaFin; $fecha->modify('+1 month')) {
					$mes = $fecha->format('m');
					$ano = $fecha->format('y');
					$mes_ano = $mes . "-" . $ano;
					$saldo = isset($datosPeriodo[$ano][$mes]['saldo']) ? $datosPeriodo[$ano][$mes]['saldo'] : 0;

					if (substr($codigo, 0, 1) > 1) {
						$saldo = $saldo * -1;
					} else {
						$saldo = $saldo;
					}

					if (substr($codigo, 0, 1) > 1) {
						if (isset($sumaColumna[$indice_letra])) {
							$sumaColumna[$indice_letra] -= $saldo;
						}
					} else {
						$sumaColumna[$indice_letra] += $saldo;
					}

					$total_fila += $saldo;
					$saldo = $saldo == 0 ? "" : number_format($saldo, 2, '.', '');

					$objPHPExcel->setActiveSheetIndex(0)->setCellValue(indice_letra($titulosColumnas)[$indice_letra] . $i,  $saldo);
					$indice_letra++;
				}
				$objPHPExcel->setActiveSheetIndex(0)->setCellValue(indice_letra($titulosColumnas)[$indice_letra] . $i,  $total_fila);
				$i++;
			}
		}

		$t = $i + 1;
		$objPHPExcel->setActiveSheetIndex(0)->setCellValue('B' . $t,  'Activo - Pasivo - Patrimonio');

		//el indice 2 corresponde a la letra C de la columna excel
		$total_columna = 0;
		$total_tatales = 0;
		for ($letra = 2; $letra <= count($titulosColumnas) - 2; $letra++) {
			$total_columna = isset($sumaColumna[$letra]) ? $sumaColumna[$letra] : 0;
			$total_tatales += $total_columna;
			$objPHPExcel->setActiveSheetIndex(0)->setCellValue(indice_letra($titulosColumnas)[$letra] . $t,  $total_columna);
		}
		$indice_para_total = $letra;
		$objPHPExcel->setActiveSheetIndex(0)->setCellValue(indice_letra($titulosColumnas)[$indice_para_total] . $t,  $total_tatales);
		$t++;
		$objPHPExcel->getActiveSheet()->getStyle('C5:AC' . $t)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
	}

	if ($informe == '2P') {
		$i = 5;
		$fechaInicio = new DateTime(date("Y/m/d", strtotime($desde)));
		$fechaFin = new DateTime(date("Y/m/d", strtotime($hasta)));
		for ($fecha = $fechaInicio; $fecha <= $fechaFin; $fecha->modify('+1 month')) {
			$mes = $fecha->format('m');
			$ano = $fecha->format('y');
			$mes_ano = $mes . "-" . $ano;
			$sumaColumna[$mes_ano] = 0;
		}
		foreach ($consulta as $codigo => $datosCuentas) {
			foreach ($datosCuentas as $cuenta => $datosPeriodo) {
				$objPHPExcel->setActiveSheetIndex(0)
					->setCellValue('A' . $i,  $codigo)
					->setCellValue('B' . $i,  $cuenta)
				;
				$total_fila = 0;
				$fechaInicio = new DateTime(date("Y/m/d", strtotime($desde)));
				$fechaFin = new DateTime(date("Y/m/d", strtotime($hasta)));
				$indice_letra = 2; //el indice 2 corresponde a la letra C de la columna excel
				for ($fecha = $fechaInicio; $fecha <= $fechaFin; $fecha->modify('+1 month')) {
					$mes = $fecha->format('m');
					$ano = $fecha->format('y');
					$mes_ano = $mes . "-" . $ano;
					$saldo = isset($datosPeriodo[$ano][$mes]['saldo']) ? $datosPeriodo[$ano][$mes]['saldo'] : 0;

					if (substr($codigo, 0, 1) == 4) {
						$saldo = $saldo * -1;
					} else {
						$saldo = $saldo;
					}

					if (substr($codigo, 0, 1) == 4) {
						if (isset($sumaColumna[$indice_letra])) {
							$sumaColumna[$indice_letra] += $saldo;
						}
					} else {
						$sumaColumna[$indice_letra] -= $saldo;
					}

					$total_fila += $saldo;
					$saldo = $saldo == 0 ? "" : number_format($saldo, 2, '.', '');

					$objPHPExcel->setActiveSheetIndex(0)->setCellValue(indice_letra($titulosColumnas)[$indice_letra] . $i,  $saldo);
					$indice_letra++;
				}
				$objPHPExcel->setActiveSheetIndex(0)->setCellValue(indice_letra($titulosColumnas)[$indice_letra] . $i,  $total_fila);
				$i++;
			}
		}

		$t = $i + 1;
		$objPHPExcel->setActiveSheetIndex(0)->setCellValue('B' . $t,  'Utilidad');

		//el indice 2 corresponde a la letra C de la columna excel
		$total_columna = 0;
		$total_tatales = 0;
		for ($letra = 2; $letra <= count($titulosColumnas) - 2; $letra++) {
			$total_columna = isset($sumaColumna[$letra]) ? $sumaColumna[$letra] : 0;
			$total_tatales += $total_columna;
			$objPHPExcel->setActiveSheetIndex(0)->setCellValue(indice_letra($titulosColumnas)[$letra] . $t,  $total_columna);
		}
		$indice_para_total = $letra;
		$objPHPExcel->setActiveSheetIndex(0)->setCellValue(indice_letra($titulosColumnas)[$indice_para_total] . $t,  $total_tatales);
		$t++;
		$objPHPExcel->getActiveSheet()->getStyle('C5:AC' . $t)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
	}

	if ($informe == '2PP') {
		$i = 5;
		$suma_por_ejecutar = 0;
		$suma_ejecutado = 0;
		$valor_presupuesto = 0;
		$suma_presupuesto = 0;


		$fechaInicio = new DateTime(date("Y/m/d", strtotime($desde)));
		$fechaFin = new DateTime(date("Y/m/d", strtotime($hasta)));
		for ($fecha = $fechaInicio; $fecha <= $fechaFin; $fecha->modify('+1 month')) {
			$mes = $fecha->format('m');
			$ano = $fecha->format('y');
			$mes_ano = $mes . "-" . $ano;
			$sumaColumna[$mes_ano] = 0;
		}

		foreach ($consulta as $codigo => $datosCuentas) {
			$objPHPExcel->setActiveSheetIndex(0)
				->setCellValue('A' . $i,  $codigo)
				->setCellValue('B' . $i,  strtoupper($datosCuentas['cuenta']))
				->setCellValue('C' . $i,  number_format($datosCuentas['valor'], 2, '.', ''))
			;
			$valor_presupuesto = $datosCuentas['valor'];

			$fechaInicio = new DateTime(date("Y/m/d", strtotime($desde)));
			$fechaFin = new DateTime(date("Y/m/d", strtotime($hasta)));
			$indice_letra = 3; //el indice 3 corresponde a la letra D de la columna excel
			$total_fila = 0;
			for ($fecha = $fechaInicio; $fecha <= $fechaFin; $fecha->modify('+1 month')) {
				$mes = $fecha->format('m');
				$ano = $fecha->format('y');
				$mes_ano = $mes . "-" . $ano;
				$sql_saldos = mysqli_query($con, "SELECT round(sum(det.debe-haber),2) as saldo 
									FROM detalle_diario_contable as det INNER JOIN encabezado_diario as enc 
									ON enc.codigo_unico=det.codigo_unico WHERE enc.ruc_empresa='" . $ruc_empresa . "' and 
									DATE_FORMAT(enc.fecha_asiento, '%m') = '" . $mes . "'
									and DATE_FORMAT(enc.fecha_asiento, '%y') = '" . $ano . "' and det.id_cuenta='" . $datosCuentas['id_cuenta'] . "' group by det.id_cuenta");
				$row_saldos = mysqli_fetch_array($sql_saldos);
				$saldo = isset($row_saldos['saldo']) ? $row_saldos['saldo'] : 0;
				if (substr($codigo, 0, 1) == 4) {
					$saldo = $saldo * -1;
				} else {
					$saldo = $saldo;
				}

				if (isset($sumaColumna[$mes_ano])) {
					$sumaColumna[$mes_ano] += $saldo;
				}

				$total_fila += $saldo;
				$saldo = $saldo == 0 ? "" : number_format($saldo, 2, '.', '');
				$objPHPExcel->setActiveSheetIndex(0)->setCellValue(indice_letra($titulosColumnas)[$indice_letra] . $i,  $saldo);
				$indice_letra++;
			}

			$objPHPExcel->setActiveSheetIndex(0)->setCellValue(indice_letra($titulosColumnas)[$indice_letra] . $i, $total_fila);
			$objPHPExcel->setActiveSheetIndex(0)->setCellValue(indice_letra($titulosColumnas)[$indice_letra + 1] . $i,  $valor_presupuesto - $total_fila);
			$suma_ejecutado += $total_fila;
			$suma_por_ejecutar += $valor_presupuesto - $total_fila;
			$suma_presupuesto += $valor_presupuesto;

			$i++;
		}

		$t = $i + 1;
		$objPHPExcel->setActiveSheetIndex(0)->setCellValue('B' . $t,  'Totales');
		$objPHPExcel->setActiveSheetIndex(0)->setCellValue('C' . $t,  $suma_presupuesto);

		$fechaInicio = new DateTime(date("Y/m/d", strtotime($desde)));
		$fechaFin = new DateTime(date("Y/m/d", strtotime($hasta)));
		$indice_letra = 3; //el indice 3 corresponde a la letra D de la columna excel
		$total_columna = 0;
		for ($fecha = $fechaInicio; $fecha <= $fechaFin; $fecha->modify('+1 month')) {
			$mes = $fecha->format('m');
			$ano = $fecha->format('y');
			$mes_ano = $mes . "-" . $ano;
			$total_columna = isset($sumaColumna[$mes_ano]) ? $sumaColumna[$mes_ano] : 0;
			$objPHPExcel->setActiveSheetIndex(0)->setCellValue(indice_letra($titulosColumnas)[$indice_letra] . $t,  number_format($total_columna, 2, '.', ''));
			$objPHPExcel->setActiveSheetIndex(0)->setCellValue(indice_letra($titulosColumnas)[$indice_letra + 1] . $t,  $suma_ejecutado);
			$objPHPExcel->setActiveSheetIndex(0)->setCellValue(indice_letra($titulosColumnas)[$indice_letra + 2] . $t,  $suma_por_ejecutar);
			$indice_letra++;
		}

		$t++;
		$objPHPExcel->getActiveSheet()->getStyle('C5:BZ' . $t)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
	}



	if ($informe == '1') {
		while ($row_detalle_balance = mysqli_fetch_array($consulta)) {
			$codigo_cuenta = $row_detalle_balance['codigo_cuenta'];
			$codigos_sri = mysqli_query($con, "SELECT * FROM plan_cuentas WHERE ruc_empresa = '" . $ruc_empresa . "' and codigo_cuenta='" . $codigo_cuenta . "'");
			$row_codigos_sri = mysqli_fetch_array($codigos_sri);
			$codigo_sri = $row_codigos_sri['codigo_sri'];
			$valor = $row_detalle_balance['valor'];
			if ($valor != 0) {
				$nivel = $row_detalle_balance['nivel'];
				if (substr($codigo_cuenta, 0, 1) == 1) {
					$valor = $valor;
				} else {
					$valor = $valor * -1;
				}

				if ($nivel == 5) {
					$nivel_cinco = $valor;
				} else {
					$nivel_cinco = "";
				}

				if ($nivel == 4) {
					$nivel_cuatro = $valor;
				} else {
					$nivel_cuatro = "";
				}

				if ($nivel == 3) {
					$nivel_tres = $valor;
				} else {
					$nivel_tres = "";
				}

				if ($nivel == 2) {
					$nivel_dos = $valor;
				} else {
					$nivel_dos = "";
				}

				if ($nivel == 1) {
					$nivel_uno = $valor;
				} else {
					$nivel_uno = "";
				}


				$objPHPExcel->setActiveSheetIndex(0)
					->setCellValue('A' . $i,  $row_detalle_balance['codigo_cuenta'])
					->setCellValue('B' . $i,  $codigo_sri)
					->setCellValue('C' . $i,  strtoupper($row_detalle_balance['nombre_cuenta']))
					->setCellValue('D' . $i,  $nivel_cinco)
					->setCellValue('E' . $i,  $nivel_cuatro)
					->setCellValue('F' . $i,  $nivel_tres)
					->setCellValue('G' . $i,  $nivel_dos)
					->setCellValue('H' . $i,  $nivel_uno)
				;
				$objPHPExcel->getActiveSheet()->getStyle('D' . $i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
				$objPHPExcel->getActiveSheet()->getStyle('E' . $i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
				$objPHPExcel->getActiveSheet()->getStyle('F' . $i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
				$objPHPExcel->getActiveSheet()->getStyle('G' . $i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
				$objPHPExcel->getActiveSheet()->getStyle('H' . $i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
				$objPHPExcel->getActiveSheet()->getStyle('A' . $i)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
				$objPHPExcel->getActiveSheet()->getStyle('B' . $i)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
				$i++;
			}
		}

		$t = $i + 1;

		$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('C' . $t,  'TOTAL ACTIVO')
			->setCellValue('D' . $t,  number_format($activo, 2, '.', ''))
		;
		$t = $t + 1;

		$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('C' . $t,  'TOTAL PASIVO + PATRIMONIO')
			->setCellValue('D' . $t,  number_format($pasivo + $patrimonio, 2, '.', ''))
		;
		$t = $t + 1;

		$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('C' . $t,  $resultado_ejercicio)
			->setCellValue('D' . $t,  number_format($utilidad_ejercicio, 2, '.', ''))
		;
	}

	if ($informe == '2') {
		while ($row_detalle_balance = mysqli_fetch_array($consulta)) {
			$codigo_cuenta = $row_detalle_balance['codigo_cuenta'];
			$codigos_sri = mysqli_query($con, "SELECT * FROM plan_cuentas WHERE ruc_empresa = '" . $ruc_empresa . "' and codigo_cuenta='" . $codigo_cuenta . "'");
			$row_codigos_sri = mysqli_fetch_array($codigos_sri);
			$codigo_sri = $row_codigos_sri['codigo_sri'];
			$valor = $row_detalle_balance['valor'];
			if ($valor != 0) {
				if (substr($codigo_cuenta, 0, 1) > 3 && substr($codigo_cuenta, 0, 1) < 5) {
					$valor = $valor * -1;
				} else {
					$valor = $valor;
				}
				$nivel = $row_detalle_balance['nivel'];
				if ($nivel == 5) {
					$nivel_cinco = $valor;
				} else {
					$nivel_cinco = "";
				}

				if ($nivel == 4) {
					$nivel_cuatro = $valor;
				} else {
					$nivel_cuatro = "";
				}

				if ($nivel == 3) {
					$nivel_tres = $valor;
				} else {
					$nivel_tres = "";
				}

				if ($nivel == 2) {
					$nivel_dos = $valor;
				} else {
					$nivel_dos = "";
				}

				if ($nivel == 1) {
					$nivel_uno = $valor;
				} else {
					$nivel_uno = "";
				}

				$objPHPExcel->setActiveSheetIndex(0)
					->setCellValue('A' . $i,  $row_detalle_balance['codigo_cuenta'])
					->setCellValue('B' . $i,  $codigo_sri)
					->setCellValue('C' . $i,  strtoupper($row_detalle_balance['nombre_cuenta']))
					->setCellValue('D' . $i,  $nivel_cinco)
					->setCellValue('E' . $i,  $nivel_cuatro)
					->setCellValue('F' . $i,  $nivel_tres)
					->setCellValue('G' . $i,  $nivel_dos)
					->setCellValue('H' . $i,  $nivel_uno)
				;
				$objPHPExcel->getActiveSheet()->getStyle('D' . $i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
				$objPHPExcel->getActiveSheet()->getStyle('E' . $i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
				$objPHPExcel->getActiveSheet()->getStyle('F' . $i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
				$objPHPExcel->getActiveSheet()->getStyle('G' . $i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
				$objPHPExcel->getActiveSheet()->getStyle('H' . $i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
				$objPHPExcel->getActiveSheet()->getStyle('A' . $i)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
				$objPHPExcel->getActiveSheet()->getStyle('B' . $i)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);

				$i++;
			}
		}
		$t = $i + 1;
		$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('C' . $t,  $resultado_ejercicio)
			->setCellValue('D' . $t,  number_format($utilidad_ejercicio, 2, '.', ''))
		;
	}

	if ($informe == '3') {
		$suma_debe_cuenta = 0;
		$suma_haber_cuenta = 0;
		$suma_deudor_cuenta = 0;
		$suma_acreedor_cuenta = 0;

		while ($row_detalle_balance = mysqli_fetch_array($consulta)) {
			$codigo_cuenta = $row_detalle_balance['codigo_cuenta'];
			$nombre_cuenta = $row_detalle_balance['nombre_cuenta'];
			$debe_cuenta = $row_detalle_balance['debe'];
			$haber_cuenta = $row_detalle_balance['haber'];
			$suma_debe_cuenta += $debe_cuenta;
			$suma_haber_cuenta += $haber_cuenta;
			$deudor_cuenta = $debe_cuenta > $haber_cuenta ? $debe_cuenta - $haber_cuenta : 0;
			$acreedor_cuenta = $haber_cuenta > $debe_cuenta ? $haber_cuenta - $debe_cuenta : 0;
			$suma_deudor_cuenta += $deudor_cuenta;
			$suma_acreedor_cuenta += $acreedor_cuenta;
			$objPHPExcel->setActiveSheetIndex(0)
				->setCellValue('A' . $i,  $codigo_cuenta)
				->setCellValue('B' . $i,  $nombre_cuenta)
				->setCellValue('C' . $i,  $debe_cuenta)
				->setCellValue('D' . $i,  $haber_cuenta)
				->setCellValue('E' . $i,  $deudor_cuenta)
				->setCellValue('F' . $i,  $acreedor_cuenta)
			;
			$objPHPExcel->getActiveSheet()->getStyle('C' . $i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
			$objPHPExcel->getActiveSheet()->getStyle('D' . $i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
			$objPHPExcel->getActiveSheet()->getStyle('E' . $i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
			$objPHPExcel->getActiveSheet()->getStyle('F' . $i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
			$i++;
		}

		$t = $i + 1;
		$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('B' . $t,  'Sumas')
			->setCellValue('C' . $t,  number_format($suma_debe_cuenta, 2, '.', ''))
			->setCellValue('D' . $t,  number_format($suma_haber_cuenta, 2, '.', ''))
			->setCellValue('E' . $t,  number_format($suma_deudor_cuenta, 2, '.', ''))
			->setCellValue('F' . $t,  number_format($suma_acreedor_cuenta, 2, '.', ''))
		;
		$t++;
		$objPHPExcel->getActiveSheet()->getStyle('C' . $t . ':F' . $t)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
		$objPHPExcel->getActiveSheet()->getStyle('C' . $t . ':F' . $t)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
	}

	if ($informe == 'sri') {
		while ($row_detalle_balance = mysqli_fetch_array($consulta)) {
			$codigo_sri = $row_detalle_balance['codigo_cuenta'];
			$valor = $row_detalle_balance['valor'];
			if ($valor != 0) {
				$objPHPExcel->setActiveSheetIndex(0)
					->setCellValue('A' . $i,  '')
					->setCellValue('B' . $i,  $codigo_sri)
					->setCellValue('C' . $i,  $valor)
					->setCellValue('D' . $i,  '')
					->setCellValue('E' . $i,  '')
					->setCellValue('F' . $i,  '')
					->setCellValue('G' . $i,  '')
					->setCellValue('H' . $i,  '')
				;
				$objPHPExcel->getActiveSheet()->getStyle('C' . $i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
				$i++;
			}
		}
		$t = $i;
	}

	$t = $t + 2;
	$objPHPExcel->setActiveSheetIndex(0)
		->setCellValue('C' . $t,  'Gerente General')
		->setCellValue('D' . $t,  'Contador')
	;

	$t = $t + 1;
	$objPHPExcel->setActiveSheetIndex(0)
		->setCellValue('C' . $t,  $rep_legal)
		->setCellValue('D' . $t,  $nombre_contador)
	;

	$t = $t + 1;
	$objPHPExcel->setActiveSheetIndex(0)
		->setCellValue('C' . $t,  "Ced/pas:" . $cedula_rep_legal)
		->setCellValue('D' . $t,  "RUC:" . $ruc_contador)
	;

	$t = $t + 3;
	$objPHPExcel->setActiveSheetIndex(0)
		->setCellValue('A' . $t,  'Revisar: ' . $errores)
	;

	$t = $t;
	$h = $t + 5;
	$objPHPExcel->getActiveSheet()->mergeCells('A' . $t . ':H' . $h);
	$objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':H' . $h)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_JUSTIFY);
	$objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':H' . $h)->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_TOP);



	for ($i = 'A'; $i <= 'B'; $i++) {
		$objPHPExcel->setActiveSheetIndex(0)
			->getColumnDimension($i)->setAutoSize(TRUE);
	}


	// Se asigna el nombre a la hoja
	$objPHPExcel->getActiveSheet()->setTitle($tituloHoja);

	// Se activa la hoja para que sea la que se muestre cuando el archivo se abre
	$objPHPExcel->setActiveSheetIndex(0);
	$objPHPExcel->getActiveSheet(0)->freezePaneByColumnAndRow(0, 5);

	// Se manda el archivo al navegador web, con el nombre que se indica (Excel2007)
	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	header('Content-Disposition: attachment;filename=' . $titulolibro);
	header('Cache-Control: max-age=0');

	$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
	$objWriter->save('php://output');
	exit;
} else {
	echo ('No hay resultados para mostrar');
	exit;
}
