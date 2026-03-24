<?php
include("../conexiones/conectalogin.php");
//include("../helpers/helpers.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];

$nombre_informe = mysqli_real_escape_string($con, (strip_tags($_REQUEST['nombre_informe'], ENT_QUOTES)));
$desde = mysqli_real_escape_string($con, (strip_tags($_REQUEST['fecha_desde'], ENT_QUOTES)));
$hasta = mysqli_real_escape_string($con, (strip_tags($_REQUEST['fecha_hasta'], ENT_QUOTES)));
$pro_cli = mysqli_real_escape_string($con, (strip_tags($_REQUEST['id_pro_cli'], ENT_QUOTES)));
$id_cuenta = mysqli_real_escape_string($con, (strip_tags($_REQUEST['id_cuenta_contable'], ENT_QUOTES)));

function clientes($con, $pro_cli)
{
	$sql_clientes = mysqli_query($con, "SELECT * FROM clientes where id= '" . $pro_cli . "'");
	$info_cliente = mysqli_fetch_array($sql_clientes);
	return $info_cliente;
}

function cuentas($con, $id_cuenta)
{
	$sql_cuentas = mysqli_query($con, "SELECT * FROM plan_cuentas where id_cuenta= '" . $id_cuenta . "'");
	$info_cuentas = mysqli_fetch_array($sql_cuentas);
	return $info_cuentas;
}


//buscar en base a una cuenta seleccionada y fechas
if ($nombre_informe == "4" && !empty($id_cuenta)) {
	$sql_cuentas = mysqli_query($con, "SELECT * FROM plan_cuentas WHERE id_cuenta = '" . $id_cuenta . "' "); //  
	$row_cuentas = mysqli_fetch_array($sql_cuentas);
	$codigo_cuenta = $row_cuentas['codigo_cuenta'];
	$nombre_cuenta = $row_cuentas['nombre_cuenta'];
	$consulta = mysqli_query($con, "SELECT enc_dia.tipo as tipo, enc_dia.id_diario as asiento, 
				enc_dia.fecha_asiento as fecha, det_dia.debe as debe, det_dia.haber as haber, det_dia.detalle_item as detalle 
				FROM detalle_diario_contable as det_dia INNER JOIN encabezado_diario as enc_dia ON enc_dia.codigo_unico=det_dia.codigo_unico 
				INNER JOIN plan_cuentas as plan ON plan.id_cuenta=det_dia.id_cuenta WHERE enc_dia.ruc_empresa = '" . $ruc_empresa . "' 
				and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
				and '" . date("Y/m/d", strtotime($hasta)) . "' and plan.id_cuenta = '" . $id_cuenta . "' and enc_dia.estado !='ANULADO' 
				order by enc_dia.fecha_asiento asc, det_dia.debe desc");
}

//buscar en base a todas las cuentas
if ($nombre_informe == "4" && empty($id_cuenta)) {
	$consulta = mysqli_query($con, "SELECT enc_dia.tipo as tipo, enc_dia.id_diario as asiento, 
				enc_dia.fecha_asiento as fecha, det_dia.debe as debe, det_dia.haber as haber, det_dia.detalle_item as detalle 
				FROM detalle_diario_contable as det_dia INNER JOIN encabezado_diario as enc_dia ON enc_dia.codigo_unico=det_dia.codigo_unico 
				INNER JOIN plan_cuentas as plan ON plan.id_cuenta=det_dia.id_cuenta 
				WHERE enc_dia.ruc_empresa = '" . $ruc_empresa . "' 
				and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
				and '" . date("Y/m/d", strtotime($hasta)) . "' and enc_dia.estado !='ANULADO' order by enc_dia.fecha_asiento asc, det_dia.debe desc ");
}

//buscar un cliente con todas sus cuentas y por fechas
if ($nombre_informe == "5" && !empty($pro_cli) && empty($id_cuenta)) {
	$consulta = mysqli_query($con, "SELECT plan.codigo_cuenta as codigo_cuenta, plan.nombre_cuenta as nombre_cuenta,
				enc_dia.tipo as tipo, enc_dia.id_diario as asiento, 
				enc_dia.fecha_asiento as fecha, det_dia.debe as debe, det_dia.haber as haber, det_dia.detalle_item as detalle
				FROM detalle_diario_contable as det_dia 
				INNER JOIN encabezado_diario as enc_dia ON enc_dia.codigo_unico=det_dia.codigo_unico
				INNER JOIN plan_cuentas as plan ON det_dia.id_cuenta=plan.id_cuenta 
				WHERE det_dia.ruc_empresa = '" . $ruc_empresa . "' and plan.nivel_cuenta='5' 
				and det_dia.id_cli_pro ='" . $pro_cli . "' and 
				DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') 
				between '" . date("Y/m/d", strtotime($desde)) . "' 
				and '" . date("Y/m/d", strtotime($hasta)) . "' and enc_dia.estado !='ANULADO' order by enc_dia.fecha_asiento asc, det_dia.debe desc");
}

//buscar un cliente con una cuenta y por fechas
if ($nombre_informe == "5" && !empty($pro_cli) && !empty($id_cuenta)) {
	$consulta = mysqli_query($con, "SELECT plan.codigo_cuenta as codigo_cuenta, plan.nombre_cuenta as nombre_cuenta,
				enc_dia.tipo as tipo, enc_dia.id_diario as asiento, 
				enc_dia.fecha_asiento as fecha, det_dia.debe as debe, det_dia.haber as haber, det_dia.detalle_item as detalle
				FROM detalle_diario_contable as det_dia 
				INNER JOIN encabezado_diario as enc_dia ON enc_dia.codigo_unico=det_dia.codigo_unico
				INNER JOIN plan_cuentas as plan ON det_dia.id_cuenta=plan.id_cuenta 
				WHERE det_dia.ruc_empresa = '" . $ruc_empresa . "' and plan.nivel_cuenta='5' 
				and det_dia.id_cli_pro ='" . $pro_cli . "' and det_dia.id_cuenta ='" . $id_cuenta . "' and 
				DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') 
				between '" . date("Y/m/d", strtotime($desde)) . "' 
				and '" . date("Y/m/d", strtotime($hasta)) . "' and enc_dia.estado !='ANULADO' order by enc_dia.fecha_asiento asc, det_dia.debe desc");
}

//buscar TODOS cliente con una cuenta y por fechas
if ($nombre_informe == "5" && empty($pro_cli) && !empty($id_cuenta)) {
	$consulta = mysqli_query($con, "SELECT plan.codigo_cuenta as codigo_cuenta, plan.nombre_cuenta as nombre_cuenta,
				enc_dia.tipo as tipo, enc_dia.id_diario as asiento, 
				enc_dia.fecha_asiento as fecha, det_dia.debe as debe, det_dia.haber as haber, det_dia.detalle_item as detalle, det_dia.id_cli_pro as id_cliente
				FROM detalle_diario_contable as det_dia 
				INNER JOIN encabezado_diario as enc_dia ON enc_dia.codigo_unico=det_dia.codigo_unico
				INNER JOIN plan_cuentas as plan ON det_dia.id_cuenta=plan.id_cuenta 
				WHERE det_dia.ruc_empresa = '" . $ruc_empresa . "' and plan.nivel_cuenta='5' 
				and det_dia.id_cuenta ='" . $id_cuenta . "' and 
				DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') 
				between '" . date("Y/m/d", strtotime($desde)) . "' 
				and '" . date("Y/m/d", strtotime($hasta)) . "' and enc_dia.estado !='ANULADO' and det_dia.id_cli_pro > 0
				order by enc_dia.fecha_asiento asc, det_dia.debe desc");
}


if (mysqli_num_fields($consulta) > 0) {
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
		->setTitle("Mayor General")
		->setSubject("Mayor General")
		->setDescription("Mayor General")
		->setKeywords("Mayor General")
		->setCategory("Mayor General");

	//para sacar el nombre de la empresa
	$sql_empresa = "SELECT * FROM empresas where ruc= '" . $ruc_empresa . "'";
	$resultado_empresa = mysqli_query($con, $sql_empresa);
	$empresa_info = mysqli_fetch_array($resultado_empresa);
	$tituloEmpresa = $empresa_info['nombre'];

	//encabezados para mayor general de una cuenta
	if ($nombre_informe == '4' && !empty($id_cuenta)) {
		$tituloReporte = "MAYOR GENERAL";
		$fechaReporte = "DESDE " . date("d-m-Y", strtotime($desde)) . " HASTA " . date("d-m-Y", strtotime($hasta));
		$codigo_y_cuenta = "Código: " . $codigo_cuenta . " Cuenta: " . $nombre_cuenta;
		$tituloHoja = "MayorGeneral";
		$titulolibro = "Cuenta " . $codigo_cuenta . ".xlsx";
		$titulosColumnas = array('Fecha', 'Detalle', 'Código', 'Cuenta', 'Asiento', 'Tipo', 'Debe', 'Haber', 'Saldo', 'Tipo Documento', 'Documento');
	}

	//encabezados para mayor general de todas las cuentas
	if ($nombre_informe == '4' && empty($id_cuenta)) {
		$tituloReporte = "MAYOR GENERAL";
		$fechaReporte = "DESDE " . date("d-m-Y", strtotime($desde)) . " HASTA " . date("d-m-Y", strtotime($hasta));
		$codigo_y_cuenta = "TODAS LAS CUENTAS";
		$tituloHoja = "MayorGeneral";
		$titulolibro = "MayorGeneral.xlsx";
		$titulosColumnas = array('Fecha', 'Detalle', 'Código', 'Cuenta', 'Asiento', 'Tipo', 'Debe', 'Haber', 'Saldo', 'Tipo Documento', 'Documento');
	}

	//TITULOS DE TODAS LAS CUENTAS DE UN CLIENTE
	if ($nombre_informe == '5' && !empty($pro_cli) && empty($id_cuenta)) {
		$nombre_cliente = clientes($con, $pro_cli)['nombre'];

		$tituloReporte = "MAYOR GENERAL DE UN CLIENTE";
		$fechaReporte = "DESDE " . date("d-m-Y", strtotime($desde)) . " HASTA " . date("d-m-Y", strtotime($hasta));
		$codigo_y_cuenta = "TODAS LAS CUENTAS - Cliente: " . strtoupper($nombre_cliente);
		$tituloHoja = "MayorGeneral";
		$titulolibro = "MayorGeneralCliente.xlsx";
		$titulosColumnas = array('Fecha', 'Detalle', 'Código', 'Cuenta', 'Asiento', 'Tipo', 'Debe', 'Haber', 'Saldo', 'Tipo Documento', 'Documento');
	}

	//TITULOS DE UNA CUENTA DE UN CLIENTE
	if ($nombre_informe == '5' && !empty($pro_cli) && !empty($id_cuenta)) {
		$nombre_cliente = clientes($con, $pro_cli)['nombre'];
		$nombre_cuenta = cuentas($con, $id_cuenta)['nombre_cuenta'];

		$tituloReporte = "MAYOR GENERAL DE UN CLIENTE Y UNA CUENTA CONTABLE";
		$fechaReporte = "DESDE " . date("d-m-Y", strtotime($desde)) . " HASTA " . date("d-m-Y", strtotime($hasta));
		$codigo_y_cuenta = "Cuenta: " . strtoupper($nombre_cuenta) . " Cliente: " . strtoupper($nombre_cliente);
		$tituloHoja = "MayorGeneral";
		$titulolibro = "MayorGeneralClienteCuenta.xlsx";
		$titulosColumnas = array('Fecha', 'Detalle', 'Código', 'Cuenta', 'Asiento', 'Tipo', 'Debe', 'Haber', 'Saldo', 'Tipo Documento', 'Documento');
	}

	//TITULOS DE UNA CUENTA DE TODOS CLIENTES
	if ($nombre_informe == '5' && empty($pro_cli) && !empty($id_cuenta)) {
		$nombre_cuenta = cuentas($con, $id_cuenta)['nombre_cuenta'];

		$tituloReporte = "MAYOR GENERAL DE TODOS LOS CLIENTES Y UNA CUENTA CONTABLE";
		$fechaReporte = "DESDE " . date("d-m-Y", strtotime($desde)) . " HASTA " . date("d-m-Y", strtotime($hasta));
		$codigo_y_cuenta = "Cuenta: " . strtoupper($nombre_cuenta);
		$tituloHoja = "MayorGeneral";
		$titulolibro = "MayorGeneralClientesCuenta.xlsx";
		$titulosColumnas = array('Fecha', 'Detalle', 'Código', 'Cuenta', 'Asiento', 'Tipo', 'Debe', 'Haber', 'Saldo', 'Cliente', 'Tipo Documento', 'Documento');
	}

	//$titulosColumnas = array('Fecha','Detalle','Código','Cuenta','Asiento','Tipo','Debe','Haber','Saldo');

	$objPHPExcel->setActiveSheetIndex(0)
		->mergeCells('A1:G1')
		->mergeCells('A2:G2')
		->mergeCells('A3:G3')
		->mergeCells('A4:G4');

	// Se agregan los titulos del reporte cuando es mayor general en base a una cuenta
	if ($nombre_informe == '4' && !empty($id_cuenta)) {
		$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('A1',  $tituloEmpresa)
			->setCellValue('A2',  $tituloReporte)
			->setCellValue('A3',  $fechaReporte)
			->setCellValue('A4',  $codigo_y_cuenta)
			->setCellValue('A5',  $titulosColumnas[0])
			->setCellValue('B5',  $titulosColumnas[1])
			->setCellValue('C5',  $titulosColumnas[2])
			->setCellValue('D5',  $titulosColumnas[3])
			->setCellValue('E5',  $titulosColumnas[4])
			->setCellValue('F5',  $titulosColumnas[5])
			->setCellValue('G5',  $titulosColumnas[6])
			->setCellValue('H5',  $titulosColumnas[7])
			->setCellValue('I5',  $titulosColumnas[8])
			->setCellValue('J5',  $titulosColumnas[9])
			->setCellValue('K5',  $titulosColumnas[10]);
		//$i = 6;
	}

	//titulos para cuando son todas las cuentas del mayor general
	if ($nombre_informe == '4' && empty($id_cuenta)) {
		$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('A1',  $tituloEmpresa)
			->setCellValue('A2',  $tituloReporte)
			->setCellValue('A3',  $fechaReporte)
			->setCellValue('A4',  $codigo_y_cuenta);
		//$i = 5;
	}

	//cuando es un mayor de un cliente con todas sus cuentas
	if ($nombre_informe == '5' && !empty($pro_cli) && empty($id_cuenta)) {
		$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('A1',  $tituloEmpresa)
			->setCellValue('A2',  $tituloReporte)
			->setCellValue('A3',  $fechaReporte)
			->setCellValue('A4',  $codigo_y_cuenta)
			->setCellValue('A5',  $titulosColumnas[0])
			->setCellValue('B5',  $titulosColumnas[1])
			->setCellValue('C5',  $titulosColumnas[2])
			->setCellValue('D5',  $titulosColumnas[3])
			->setCellValue('E5',  $titulosColumnas[4])
			->setCellValue('F5',  $titulosColumnas[5])
			->setCellValue('G5',  $titulosColumnas[6])
			->setCellValue('H5',  $titulosColumnas[7])
			->setCellValue('I5',  $titulosColumnas[8])
			->setCellValue('J5',  $titulosColumnas[9])
			->setCellValue('K5',  $titulosColumnas[10]);
		//$i = 6;
	}

	//cuando es un mayor de un cliente con una cuenta
	if ($nombre_informe == '5' && !empty($pro_cli) && !empty($id_cuenta)) {
		$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('A1',  $tituloEmpresa)
			->setCellValue('A2',  $tituloReporte)
			->setCellValue('A3',  $fechaReporte)
			->setCellValue('A4',  $codigo_y_cuenta)
			->setCellValue('A5',  $titulosColumnas[0])
			->setCellValue('B5',  $titulosColumnas[1])
			->setCellValue('C5',  $titulosColumnas[2])
			->setCellValue('D5',  $titulosColumnas[3])
			->setCellValue('E5',  $titulosColumnas[4])
			->setCellValue('F5',  $titulosColumnas[5])
			->setCellValue('G5',  $titulosColumnas[6])
			->setCellValue('H5',  $titulosColumnas[7])
			->setCellValue('I5',  $titulosColumnas[8])
			->setCellValue('J5',  $titulosColumnas[9])
			->setCellValue('K5',  $titulosColumnas[10]);
		//$i = 6;
	}

	//titulo para cuando son todas las cuentas de un cliente
	if ($nombre_informe == '5' && !empty($pro_cli) && empty($id_cuenta)) {
		$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('A1',  $tituloEmpresa)
			->setCellValue('A2',  $tituloReporte)
			->setCellValue('A3',  $fechaReporte)
			->setCellValue('A4',  $codigo_y_cuenta)
			->setCellValue('A5',  $titulosColumnas[0])
			->setCellValue('B5',  $titulosColumnas[1])
			->setCellValue('C5',  $titulosColumnas[2])
			->setCellValue('D5',  $titulosColumnas[3])
			->setCellValue('E5',  $titulosColumnas[4])
			->setCellValue('F5',  $titulosColumnas[5])
			->setCellValue('G5',  $titulosColumnas[6])
			->setCellValue('H5',  $titulosColumnas[7])
			->setCellValue('I5',  $titulosColumnas[8])
			->setCellValue('J5',  $titulosColumnas[9])
			->setCellValue('K5',  $titulosColumnas[10]);
	}

	//titulo para cuando son todos los clientes con una cuenta
	if ($nombre_informe == '5' && empty($pro_cli) && !empty($id_cuenta)) {
		$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('A1',  $tituloEmpresa)
			->setCellValue('A2',  $tituloReporte)
			->setCellValue('A3',  $fechaReporte)
			->setCellValue('A4',  $codigo_y_cuenta)
			->setCellValue('A5',  $titulosColumnas[0])
			->setCellValue('B5',  $titulosColumnas[1])
			->setCellValue('C5',  $titulosColumnas[2])
			->setCellValue('D5',  $titulosColumnas[3])
			->setCellValue('E5',  $titulosColumnas[4])
			->setCellValue('F5',  $titulosColumnas[5])
			->setCellValue('G5',  $titulosColumnas[6])
			->setCellValue('H5',  $titulosColumnas[7])
			->setCellValue('I5',  $titulosColumnas[8])
			->setCellValue('J5',  $titulosColumnas[9])
			->setCellValue('K5',  $titulosColumnas[10])
			->setCellValue('L5',  $titulosColumnas[11]);
	}

	//mayor general con una cuenta
	if ($nombre_informe == '4' && !empty($id_cuenta)) {
		$i = 6;
		$saldo = 0;
		while ($row_detalle_diario = mysqli_fetch_array($consulta)) {
			$fecha = date('d-m-Y', strtotime($row_detalle_diario['fecha']));
			$detalle = $row_detalle_diario['detalle'];
			$debe = $row_detalle_diario['debe'];
			$haber = $row_detalle_diario['haber'];
			$saldo += $debe - $haber;
			$asiento = $row_detalle_diario['asiento'];
			$tipo = $row_detalle_diario['tipo'];
			$objPHPExcel->setActiveSheetIndex(0)
				->setCellValue('A' . $i,  $fecha)
				->setCellValue('B' . $i,  strtoupper($detalle))
				->setCellValue('C' . $i,  $codigo_cuenta)
				->setCellValue('D' . $i,  $nombre_cuenta)
				->setCellValue('E' . $i,  $asiento)
				->setCellValue('F' . $i,  $tipo)
				->setCellValue('G' . $i,  number_format($debe, 2, '.', ''))
				->setCellValue('H' . $i,  number_format($haber, 2, '.', ''))
				->setCellValue('I' . $i,  number_format($saldo, 2, '.', ''))
				->setCellValue('J' . $i,  detalle_documentos($con, $tipo, $asiento, $ruc_empresa)['documento'])
				->setCellValue('K' . $i,  detalle_documentos($con, $tipo, $asiento, $ruc_empresa)['numero_documento']);
			$i++;
		}
	}


	//mayor general de todas las cuentas
	if ($nombre_informe == '4' && empty($id_cuenta)) {
		$i = 6;
		$sql_cuentas = mysqli_query($con, "SELECT * FROM plan_cuentas WHERE ruc_empresa = '" . $ruc_empresa . "' and nivel_cuenta='5'"); //  
		while ($row_cuentas = mysqli_fetch_array($sql_cuentas)) {
			$id_cuenta = $row_cuentas['id_cuenta'];
			$codigo_cuenta = $row_cuentas['codigo_cuenta'];
			$nombre_cuenta = $row_cuentas['nombre_cuenta'];
			$consulta_cuentas = mysqli_query($con, "SELECT enc_dia.tipo as tipo, enc_dia.id_diario as asiento, enc_dia.fecha_asiento as fecha, det_dia.debe as debe, det_dia.haber as haber, det_dia.detalle_item as detalle FROM detalle_diario_contable as det_dia INNER JOIN encabezado_diario as enc_dia ON enc_dia.codigo_unico=det_dia.codigo_unico INNER JOIN plan_cuentas as plan ON plan.id_cuenta=det_dia.id_cuenta WHERE enc_dia.ruc_empresa = '" . $ruc_empresa . "' and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' and enc_dia.estado !='ANULADO' and det_dia.id_cuenta='" . $id_cuenta . "' order by enc_dia.fecha_asiento asc, det_dia.debe desc ");
			$registros = mysqli_num_rows($consulta_cuentas);
			if ($registros > 0) {
				$objPHPExcel->setActiveSheetIndex(0)
					->setCellValue('A' . $i,  strtoupper($codigo_cuenta))
					->setCellValue('B' . $i,  strtoupper($nombre_cuenta));
				$i = $i + 1;

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
					->setCellValue('K' . $i,  $titulosColumnas[10]);
				$i = $i + 1;

				$saldo = 0;
				while ($row_detalle_diario = mysqli_fetch_array($consulta_cuentas)) {
					$fecha = date('d-m-Y', strtotime($row_detalle_diario['fecha']));
					$detalle = $row_detalle_diario['detalle'];
					$debe = $row_detalle_diario['debe'];
					$haber = $row_detalle_diario['haber'];
					$saldo += $debe - $haber;
					$asiento = $row_detalle_diario['asiento'];
					$tipo = $row_detalle_diario['tipo'];

					$objPHPExcel->setActiveSheetIndex(0)
						->setCellValue('A' . $i,  $fecha)
						->setCellValue('B' . $i,  strtoupper($detalle))
						->setCellValue('C' . $i,  $codigo_cuenta)
						->setCellValue('D' . $i,  $nombre_cuenta)
						->setCellValue('E' . $i,  $asiento)
						->setCellValue('F' . $i,  $tipo)
						->setCellValue('G' . $i,  number_format($debe, 2, '.', ''))
						->setCellValue('H' . $i,  number_format($haber, 2, '.', ''))
						->setCellValue('I' . $i,  number_format($saldo, 2, '.', ''))
						->setCellValue('J' . $i,  detalle_documentos($con, $tipo, $asiento, $ruc_empresa)['documento'])
						->setCellValue('K' . $i,  detalle_documentos($con, $tipo, $asiento, $ruc_empresa)['numero_documento']);
					$i++;
				}
				$i++;
			}
		}
	}


	//mayor general de un cliente con todas las cuenta
	if ($nombre_informe == '5' && !empty($pro_cli) && empty($id_cuenta)) {
		$i = 6;
		$saldo = 0;
		while ($row_detalle_diario = mysqli_fetch_array($consulta)) {
			$fecha = date('d-m-Y', strtotime($row_detalle_diario['fecha']));
			$codigo_cuenta = $row_detalle_diario['codigo_cuenta'];
			$nombre_cuenta = $row_detalle_diario['nombre_cuenta'];
			$detalle = $row_detalle_diario['detalle'];
			$debe = $row_detalle_diario['debe'];
			$haber = $row_detalle_diario['haber'];
			$saldo += $debe - $haber;
			$asiento = $row_detalle_diario['asiento'];
			$tipo = $row_detalle_diario['tipo'];

			$objPHPExcel->setActiveSheetIndex(0)
				->setCellValue('A' . $i,  $fecha)
				->setCellValue('B' . $i,  strtoupper($detalle))
				->setCellValue('C' . $i,  $codigo_cuenta)
				->setCellValue('D' . $i,  $nombre_cuenta)
				->setCellValue('E' . $i,  $asiento)
				->setCellValue('F' . $i,  $tipo)
				->setCellValue('G' . $i,  number_format($debe, 2, '.', ''))
				->setCellValue('H' . $i,  number_format($haber, 2, '.', ''))
				->setCellValue('I' . $i,  number_format($saldo, 2, '.', ''))
				->setCellValue('J' . $i,  detalle_documentos($con, $tipo, $asiento, $ruc_empresa)['documento'])
				->setCellValue('K' . $i,  detalle_documentos($con, $tipo, $asiento, $ruc_empresa)['numero_documento']);
			$i++;
		}
	}


	//mayor general de un cliente con una cuenta
	if ($nombre_informe == '5' && !empty($pro_cli) && !empty($id_cuenta)) {
		$i = 6;
		$saldo = 0;
		while ($row_detalle_diario = mysqli_fetch_array($consulta)) {
			$fecha = date('d-m-Y', strtotime($row_detalle_diario['fecha']));
			$codigo_cuenta = $row_detalle_diario['codigo_cuenta'];
			$nombre_cuenta = $row_detalle_diario['nombre_cuenta'];
			$detalle = $row_detalle_diario['detalle'];
			$debe = $row_detalle_diario['debe'];
			$haber = $row_detalle_diario['haber'];
			$saldo += $debe - $haber;
			$asiento = $row_detalle_diario['asiento'];
			$tipo = $row_detalle_diario['tipo'];

			$objPHPExcel->setActiveSheetIndex(0)
				->setCellValue('A' . $i,  $fecha)
				->setCellValue('B' . $i,  strtoupper($detalle))
				->setCellValue('C' . $i,  $codigo_cuenta)
				->setCellValue('D' . $i,  $nombre_cuenta)
				->setCellValue('E' . $i,  $asiento)
				->setCellValue('F' . $i,  $tipo)
				->setCellValue('G' . $i,  number_format($debe, 2, '.', ''))
				->setCellValue('H' . $i,  number_format($haber, 2, '.', ''))
				->setCellValue('I' . $i,  number_format($saldo, 2, '.', ''))
				->setCellValue('J' . $i,  detalle_documentos($con, $tipo, $asiento, $ruc_empresa)['documento'])
				->setCellValue('K' . $i,  detalle_documentos($con, $tipo, $asiento, $ruc_empresa)['numero_documento']);
			$i++;
		}
	}

	//mayor general de todos clientes con una cuenta
	if ($nombre_informe == '5' && empty($pro_cli) && !empty($id_cuenta)) {
		$i = 6;
		$saldo = 0;
		while ($row_detalle_diario = mysqli_fetch_array($consulta)) {
			$fecha = date('d-m-Y', strtotime($row_detalle_diario['fecha']));
			$codigo_cuenta = $row_detalle_diario['codigo_cuenta'];
			$nombre_cuenta = $row_detalle_diario['nombre_cuenta'];
			$detalle = $row_detalle_diario['detalle'];
			$debe = $row_detalle_diario['debe'];
			$haber = $row_detalle_diario['haber'];
			$saldo += $debe - $haber;
			$asiento = $row_detalle_diario['asiento'];
			$tipo = $row_detalle_diario['tipo'];
			$id_cliente = $row_detalle_diario['id_cliente'];
			$cliente = clientes($con, $id_cliente)['nombre'];

			$objPHPExcel->setActiveSheetIndex(0)
				->setCellValue('A' . $i,  $fecha)
				->setCellValue('B' . $i,  strtoupper($detalle))
				->setCellValue('C' . $i,  $codigo_cuenta)
				->setCellValue('D' . $i,  $nombre_cuenta)
				->setCellValue('E' . $i,  $asiento)
				->setCellValue('F' . $i,  $tipo)
				->setCellValue('G' . $i,  number_format($debe, 2, '.', ''))
				->setCellValue('H' . $i,  number_format($haber, 2, '.', ''))
				->setCellValue('I' . $i,  number_format($saldo, 2, '.', ''))
				->setCellValue('J' . $i,  $cliente)
				->setCellValue('K' . $i,  detalle_documentos($con, $tipo, $asiento, $ruc_empresa)['documento'])
				->setCellValue('L' . $i,  detalle_documentos($con, $tipo, $asiento, $ruc_empresa)['numero_documento']);
			$i++;
		}
	}



	$objPHPExcel->getActiveSheet()->getStyle('G6:I' . $i)->getNumberFormat()->setFormatCode('#,##0.00');

	// Se asigna el nombre a la hoja
	$objPHPExcel->getActiveSheet()->setTitle($tituloHoja);

	// Se activa la hoja para que sea la que se muestre cuando el archivo se abre
	$objPHPExcel->setActiveSheetIndex(0);
	$objPHPExcel->getActiveSheet(0)->freezePaneByColumnAndRow(0, 6);

	// Se manda el archivo al navegador web, con el nombre que se indica (Excel2007)
	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	header('Content-Disposition: attachment;filename=' . $titulolibro);
	header('Cache-Control: max-age=0');

	$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
	$objWriter->save('php://output');
	exit;
} else {
	echo ('No hay resultados para mostrar');
}



function detalle_documentos($con, $tipo_asiento, $asiento, $ruc_empresa)
{
	switch ($tipo_asiento) {
		case "COMPRAS_SERVICIOS";
			$detalle_documento = mysqli_query($con, "SELECT doc.comprobante as documento, enc.numero_documento as numero_documento
			 FROM encabezado_compra as enc 
			INNER JOIN comprobantes_autorizados AS doc ON doc.id_comprobante=enc.id_comprobante
			WHERE enc.id_registro_contable = '" . $asiento . "' and enc.ruc_empresa='" . $ruc_empresa . "'");
			$result = mysqli_fetch_array($detalle_documento);
			$datos = array('documento' => $result['documento'], 'numero_documento' => $result['numero_documento']);
			return $datos;
			break;
		case "VENTAS";
			$detalle_documento = mysqli_query($con, "SELECT concat(enc.serie_factura, '-', LPAD(enc.secuencial_factura,9,'0')) as numero_documento
			 FROM encabezado_factura as enc 
			WHERE enc.id_registro_contable = '" . $asiento . "' and enc.ruc_empresa='" . $ruc_empresa . "'");
			$result = mysqli_fetch_array($detalle_documento);
			$datos = array('documento' => 'Factura', 'numero_documento' => $result['numero_documento']);
			return $datos;
			break;
		case "NC_VENTAS";
			$detalle_documento = mysqli_query($con, "SELECT concat(enc.serie_nc, '-', LPAD(enc.secuencial_nc,9,'0')) as numero_documento
			 FROM encabezado_nc as enc 
			WHERE enc.id_registro_contable = '" . $asiento . "' and enc.ruc_empresa='" . $ruc_empresa . "'");
			$result = mysqli_fetch_array($detalle_documento);
			$datos = array('documento' => 'Nota de Crédito en ventas', 'numero_documento' => $result['numero_documento']);
			return $datos;
			break;
		case "EGRESOS";
			$detalle_documento = mysqli_query($con, "SELECT enc.numero_ing_egr as numero_documento
			 FROM ingresos_egresos as enc 
			WHERE enc.codigo_contable = '" . $asiento . "' and enc.ruc_empresa='" . $ruc_empresa . "' and enc.tipo_ing_egr ='EGRESO' ");
			$result = mysqli_fetch_array($detalle_documento);
			$datos = array('documento' => 'Egreso', 'numero_documento' => $result['numero_documento']);
			return $datos;
		case "INGRESOS";
			$detalle_documento = mysqli_query($con, "SELECT enc.numero_ing_egr as numero_documento
			 FROM ingresos_egresos as enc 
			WHERE enc.codigo_contable = '" . $asiento . "' and enc.ruc_empresa='" . $ruc_empresa . "' and enc.tipo_ing_egr ='INGRESO' ");
			$result = mysqli_fetch_array($detalle_documento);
			$datos = array('documento' => 'Ingreso', 'numero_documento' => $result['numero_documento']);
			return $datos;
		case "RETENCIONES_VENTAS";
			$detalle_documento = mysqli_query($con, "SELECT concat(enc.serie_retencion, '-', LPAD(enc.secuencial_retencion,9,'0')) as numero_documento
			 FROM encabezado_retencion_venta as enc 
			WHERE enc.id_registro_contable = '" . $asiento . "' and enc.ruc_empresa='" . $ruc_empresa . "' ");
			$result = mysqli_fetch_array($detalle_documento);
			$datos = array('documento' => 'Retenciones de ventas', 'numero_documento' => $result['numero_documento']);
			return $datos;
			break;
		case "RETENCIONES_COMPRAS";
			$detalle_documento = mysqli_query($con, "SELECT concat(enc.serie_retencion, '-', LPAD(enc.secuencial_retencion,9,'0')) as numero_documento
			 FROM encabezado_retencion as enc 
			WHERE enc.id_registro_contable = '" . $asiento . "' and enc.ruc_empresa='" . $ruc_empresa . "' ");
			$result = mysqli_fetch_array($detalle_documento);
			$datos = array('documento' => 'Retenciones de compras', 'numero_documento' => $result['numero_documento']);
			return $datos;
			break;
		default;
			return "";
	}
}
