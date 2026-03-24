<?php
include("../conexiones/conectalogin.php");
require_once("../helpers/helpers.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];

$action = $_POST['tipo_reporte'];
$id_cliente_proveedor = $_POST['id_cliente_proveedor'];
$nombre_cliente_proveedor = $_POST['nombre_cliente_proveedor'];
$desde = $_POST['fecha_desde'];
$hasta = $_POST['fecha_hasta'];
$detalle = $_POST['detalle'];
$cantidad = $_POST['cantidad'];
$observaciones = $_POST['observaciones'];

ini_set('date.timezone', 'America/Guayaquil');

if ($action == '1' || $action == '2') { //ingresos y egresos

	if ($action == '1') {
		$tipo_documento = "INGRESO";
		$tipo_ingreso_egreso = $_POST['tipo_ingreso'];
	}

	if ($action == '2') {
		$tipo_documento = "EGRESO";
		$tipo_ingreso_egreso = $_POST['tipo_egreso'];
	}

	if (empty($id_cliente_proveedor)) {
		$condicion_id_cliente = "";
	} else {
		$condicion_id_cliente = " and ing_egr.id_cli_pro='" . $id_cliente_proveedor . "'";
	}

	if (empty($nombre_cliente_proveedor)) {
		$condicion_cliente_proveedor = "";
	} else {
		$condicion_cliente_proveedor = " and ing_egr.nombre_ing_egr LIKE '%" . $nombre_cliente_proveedor . "%'";
	}

	if (empty($observaciones)) {
		$condicion_observaciones = "";
	} else {
		$condicion_observaciones = " and ing_egr.detalle_adicional LIKE '%" . $observaciones . "%'";
	}

	if (empty($tipo_ingreso_egreso)) {
		$condicion_tipo_ingreso_egreso = "";
	} else {
		$condicion_tipo_ingreso_egreso = " and det.tipo_ing_egr='" . $tipo_ingreso_egreso . "'";
	}

	if (empty($detalle)) {
		$condicion_detalle = "";
	} else {
		$condicion_detalle = "  and det.detalle_ing_egr LIKE '%" . $detalle . "%'";
	}

	$resultado_consolidado[] = mysqli_query($con, "SELECT ing_egr.nombre_ing_egr as nombre_ing_egr, 
				ing_egr.codigo_documento as codigo_documento, ing_egr.detalle_adicional as observaciones, det.detalle_ing_egr as detalle, 
				ing_egr.numero_ing_egr as numero_ing_egr, ing_egr.fecha_ing_egr as fecha_ing_egr, det.tipo_ing_egr as tipo, 
				det.valor_ing_egr as valor_forma_pago, usu.nombre as usuario FROM ingresos_egresos as ing_egr INNER JOIN detalle_ingresos_egresos as det ON det.codigo_documento=ing_egr.codigo_documento
				INNER JOIN usuarios as usu ON usu.id=ing_egr.id_usuario
				WHERE ing_egr.ruc_empresa='" . $ruc_empresa . "' $condicion_id_cliente $condicion_tipo_ingreso_egreso $condicion_cliente_proveedor $condicion_detalle $condicion_observaciones 
				and DATE_FORMAT(ing_egr.fecha_ing_egr, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
				and '" . date("Y/m/d", strtotime($hasta)) . "' and ing_egr.tipo_ing_egr= '" . $tipo_documento . "' 
				order by ing_egr.fecha_ing_egr ");

	if (mysqli_num_rows($resultado_consolidado[0]) > 0) {
		genera_excel($resultado_consolidado, $action, $desde, $hasta, $ruc_empresa, $con);
	} else {
		echo ('No hay resultados para mostrar');
	}
}


if ($action == '3' || $action == '4' || $action == '5') { //detalle de ingresos y egresos
	if ($action == '3') {
		$tipo_documento = "INGRESO";
		$formas_cobro_pago = $_POST['formas_cobro'];
		$tipo_cobro_pago = $_POST['tipo_cobro'];
		$tipo_ingreso_egreso = $_POST['tipo_ingreso'];
	}

	if ($action == '4') {
		$tipo_documento = "EGRESO";
		$formas_cobro_pago = $_POST['formas_pago'];
		$tipo_cobro_pago = $_POST['tipo_pago'];
		$tipo_ingreso_egreso = $_POST['tipo_egreso'];
	}

	if ($action == '5') {
		$formas_pago = $_POST['formas_pago'];
		$formas_cobro = $_POST['formas_cobro'];

		if (empty($formas_cobro)) {
			$condicion_cobro = "";
		} else {
			if (substr($formas_cobro, 0, 1) == 1) {
				$condicion_cobro = "  and pago.codigo_forma_pago=" . substr($formas_cobro, 1, strlen($formas_cobro));
			} else {
				$condicion_cobro = "  and pago.id_cuenta=" . substr($formas_cobro, 1, strlen($formas_cobro));
			}
		}

		if (empty($formas_pago)) {
			$condicion_pago = "";
		} else {
			if (substr($formas_pago, 0, 1) == 1) {
				$condicion_pago = "  and pago.codigo_forma_pago=" . substr($formas_pago, 1, strlen($formas_pago));
			} else {
				$condicion_pago = "  and pago.id_cuenta=" . substr($formas_pago, 1, strlen($formas_pago));
			}
		}
	}


	if (empty($tipo_ingreso_egreso)) {
		$condicion_tipo_ingreso_egreso = "";
	} else {
		$condicion_tipo_ingreso_egreso = " and det.tipo_ing_egr='" . $tipo_ingreso_egreso . "'";
	}

	if (empty($id_cliente_proveedor)) {
		$condicion_id_cliente = "";
	} else {
		$condicion_id_cliente = " and ing_egr.id_cli_pro='" . $id_cliente_proveedor . "'";
	}

	if (empty($nombre_cliente_proveedor)) {
		$condicion_cliente_proveedor = "";
	} else {
		$condicion_cliente_proveedor = " and ing_egr.nombre_ing_egr LIKE '%" . $nombre_cliente_proveedor . "%'";
	}

	if (empty($detalle)) {
		$condicion_detalle = "";
	} else {
		$condicion_detalle = "  and det.detalle_ing_egr LIKE '%" . $detalle . "%'";
	}

	if (empty($formas_cobro_pago)) {
		$condicion_forma_pago = "";
	} else {
		if (substr($formas_cobro_pago, 0, 1) == 1) {
			$condicion_forma_pago = "  and pago.codigo_forma_pago=" . substr($formas_cobro_pago, 1, strlen($formas_cobro_pago));
		} else {
			$condicion_forma_pago = "  and pago.id_cuenta=" . substr($formas_cobro_pago, 1, strlen($formas_cobro_pago));
		}
	}

	if (empty($tipo_cobro_pago)) {
		$condicion_tipo_pago = "";
	} else {
		$condicion_tipo_pago = " and pago.detalle_pago='" . $tipo_cobro_pago . "'";
	}

	if ($action == '3' || $action == '4') {
		$resultado_consolidado[] = mysqli_query($con, "SELECT pago.codigo_documento as codigo_documento, pago.codigo_forma_pago as codigo_forma_pago,
				pago.numero_ing_egr as numero_ing_egr, ing_egr.fecha_ing_egr as fecha_ing_egr, ing_egr.nombre_ing_egr as nombre_ing_egr,
				round(pago.valor_forma_pago,2) as valor_forma_pago, pago.detalle_pago as tipo, pago.tipo_documento as tipo_documento, pago.id_cuenta as id_cuenta, pago.cheque as cheque, usu.nombre as usuario 
				FROM formas_pagos_ing_egr as pago INNER JOIN ingresos_egresos as ing_egr ON ing_egr.codigo_documento=pago.codigo_documento
				 INNER JOIN usuarios as usu ON usu.id=ing_egr.id_usuario 
				 WHERE pago.ruc_empresa='" . $ruc_empresa . "' $condicion_id_cliente $condicion_cliente_proveedor $condicion_forma_pago $condicion_tipo_pago and DATE_FORMAT(ing_egr.fecha_ing_egr, '%Y/%m/%d') 
				 between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' 
				 and pago.tipo_documento='" . $tipo_documento . "' order by ing_egr.fecha_ing_egr desc");
		if (mysqli_num_rows($resultado_consolidado[0]) > 0) {
			genera_excel($resultado_consolidado, $action, $desde, $hasta, $ruc_empresa, $con);
		} else {
			echo ('No hay resultados para mostrar');
		}
	}


	if ($action == '5') {
		$resultado_consolidado[] = mysqli_query($con, "SELECT pago.codigo_documento as codigo_documento, pago.codigo_forma_pago as codigo_forma_pago,
			pago.numero_ing_egr as numero_ing_egr, ing_egr.fecha_ing_egr as fecha_ing_egr, ing_egr.nombre_ing_egr as nombre_ing_egr,
			round(pago.valor_forma_pago,2) as valor_forma_cobro, '0' as valor_forma_pago, pago.detalle_pago as tipo, pago.tipo_documento as tipo_documento, pago.id_cuenta as id_cuenta, pago.cheque as cheque, usu.nombre as usuario 
			FROM formas_pagos_ing_egr as pago INNER JOIN ingresos_egresos as ing_egr ON ing_egr.codigo_documento=pago.codigo_documento
				INNER JOIN usuarios as usu ON usu.id=ing_egr.id_usuario WHERE pago.ruc_empresa='" . $ruc_empresa . "' and DATE_FORMAT(ing_egr.fecha_ing_egr, '%Y/%m/%d') 
				between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' 
				and pago.tipo_documento='INGRESO' $condicion_cobro order by ing_egr.fecha_ing_egr desc");

		$resultado_consolidado[] = mysqli_query($con, "SELECT pago.codigo_documento as codigo_documento, pago.codigo_forma_pago as codigo_forma_pago,
			pago.numero_ing_egr as numero_ing_egr, ing_egr.fecha_ing_egr as fecha_ing_egr, ing_egr.nombre_ing_egr as nombre_ing_egr,
			round(pago.valor_forma_pago,2) as valor_forma_pago, '0' as valor_forma_cobro, pago.detalle_pago as tipo, pago.tipo_documento as tipo_documento, pago.id_cuenta as id_cuenta, pago.cheque as cheque, usu.nombre as usuario 
			FROM formas_pagos_ing_egr as pago INNER JOIN ingresos_egresos as ing_egr ON ing_egr.codigo_documento=pago.codigo_documento
				INNER JOIN usuarios as usu ON usu.id=ing_egr.id_usuario WHERE pago.ruc_empresa='" . $ruc_empresa . "' and DATE_FORMAT(ing_egr.fecha_ing_egr, '%Y/%m/%d') 
			between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' 
			and pago.tipo_documento='EGRESO' $condicion_pago order by ing_egr.fecha_ing_egr desc");

		if (is_array($resultado_consolidado)) {
			genera_excel($resultado_consolidado, $action, $desde, $hasta, $ruc_empresa, $con);
		} else {
			echo ('No hay resultados para mostrar');
		}
	}
}


function genera_excel($resultado_consolidado, $action, $desde, $hasta, $ruc_empresa, $con)
{
	if (PHP_SAPI == 'cli')
		die('Este archivo solo se puede ver desde un navegador web');

	/** Se agrega la libreria PHPExcel */
	require_once 'lib/PHPExcel/PHPExcel.php';

	// Se crea el objeto PHPExcel
	$objPHPExcel = new PHPExcel();

	// Se asignan las propiedades del libro
	$objPHPExcel->getProperties()->setCreator("CaMaGaRe") //Autor
		->setLastModifiedBy("CaMaGaRe") //Ultimo usuario que lo modificó
		->setTitle("Reporte Ingresos Egresos")
		->setSubject("Reporte Excel")
		->setDescription("Reporte Ingresos Egresos")
		->setKeywords("Reporte Ingresos Egresos")
		->setCategory("Reporte Ingresos Egresos");

	//para sacar el nombre de la empresa
	$sql_empresa = mysqli_query($con, "SELECT * FROM empresas where ruc= '" . $ruc_empresa . "'");
	$empresa_info = mysqli_fetch_array($sql_empresa);
	$tituloEmpresa = $empresa_info['nombre_comercial'];

	if ($action == '1') {
		$nombre_reporte = "Detalle de Ingresos";
		$tituloReporte = "Reporte de Ingresos desde: " . date("d/m/Y", strtotime($desde)) . " Hasta: " . date("d/m/Y", strtotime($hasta));
		$titulosColumnas = array('Ingreso', 'Fecha', 'Cliente', 'Valor', 'Detalle', 'Observaciones', 'Generado por', 'Asesor', '', '', '');
	}
	if ($action == '2') {
		$nombre_reporte = "Detalle de Egresos";
		$tituloReporte = "Reporte de Egresos desde: " . date("d/m/Y", strtotime($desde)) . " Hasta: " . date("d/m/Y", strtotime($hasta));
		$titulosColumnas = array('Egreso', 'Fecha', 'Proveedor', 'Valor', 'Detalle', 'Observaciones', 'Generado por', 'Asesor', '', '', '');
	}

	if ($action == '3') {
		$nombre_reporte = "Detalle de cobros";
		$tituloReporte = "Reporte de cobros desde: " . date("d/m/Y", strtotime($desde)) . " Hasta: " . date("d/m/Y", strtotime($hasta));
		$titulosColumnas = array('Ingreso', 'Fecha', 'Cliente', 'Valor', 'Tipo', 'Forma Cobro', 'Tipo cobro', 'Detalle', 'Generado por', 'Asesor', '');
	}

	if ($action == '4') {
		$nombre_reporte = "Detalle de pagos";
		$tituloReporte = "Reporte de pagos desde: " . date("d/m/Y", strtotime($desde)) . " Hasta: " . date("d/m/Y", strtotime($hasta));
		$titulosColumnas = array('Egreso', 'Fecha', 'Proveedor', 'Valor', 'Tipo', 'Forma pago', 'Tipo Pago', 'Detalle', 'Generado por', 'Asesor', '');
	}

	if ($action == '5') {
		$nombre_reporte = "Detalle de cobros vs pagos";
		$tituloReporte = "Reporte de cobros vs pagos desde: " . date("d/m/Y", strtotime($desde)) . " Hasta: " . date("d/m/Y", strtotime($hasta));
		$titulosColumnas = array('Número', 'Fecha', 'Cliente/Proveedor', 'Entrada', 'Salida', 'Tipo', 'Forma Cobro/Pago', 'Tipo Cobro/Pago', 'Detalle', 'Generado por', 'Asesor');
	}

	$objPHPExcel->setActiveSheetIndex(0)
		->mergeCells('A1:D1')
		->mergeCells('A2:D2');

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
		->setCellValue('K3',  $titulosColumnas[10]);
	$i = 4;

	$total_general = 0;
	$total_ingresos = 0;
	$total_egresos = 0;
	foreach ($resultado_consolidado as $resultado) {
		while ($row = mysqli_fetch_array($resultado)) {
			$numero_ing_egr = $row['numero_ing_egr'];
			$fecha_ing_egr = $row['fecha_ing_egr'];
			$nombre_ing_egr = $row['nombre_ing_egr'];
			$valor_ing_egr = $row['valor_forma_pago'];
			$valor_forma_cobro = $row['valor_forma_cobro'];
			$valor_forma_pago = $row['valor_forma_pago'];
			$detalle = $row['detalle'];
			$observaciones = $row['observaciones'];
			$total_general += $valor_ing_egr;
			$total_ingresos += $valor_forma_cobro;
			$total_egresos += $valor_forma_pago;
			$usuario = $row['usuario'];
			$codigo_documento = $row['codigo_documento'];
			$tipo_documento = $row['tipo_documento'];
			$codigo_forma_pago = $row['codigo_forma_pago'];
			$id_cuenta = $row['id_cuenta'];
			$cheque = $row['cheque'] > 0 ? " - " . $row['cheque'] : "";

			$sql_asesor = mysqli_query($con, "SELECT ven.nombre as asesor FROM detalle_ingresos_egresos as det LEFT JOIN vendedores_ventas as ven_ven ON ven_ven.id_venta=det.codigo_documento_cv INNER JOIN vendedores as ven ON ven.id_vendedor=ven_ven.id_vendedor WHERE det.codigo_documento= '" . $codigo_documento . "'");
			$info_asesor = mysqli_fetch_array($sql_asesor);
			$asesor = $info_asesor['asesor'];


			if ($action == '1' || $action == '2') {
				$objPHPExcel->setActiveSheetIndex(0)
					->setCellValue('A' . $i,  "=\"" . $numero_ing_egr . "\"")
					->setCellValue('B' . $i,  date("d/m/Y", strtotime($fecha_ing_egr)))
					->setCellValue('C' . $i,  strtoupper($nombre_ing_egr))
					->setCellValue('D' . $i,  number_format($valor_ing_egr, 2, '.', ''))
					->setCellValue('E' . $i,  $detalle)
					->setCellValue('F' . $i,  $observaciones)
					->setCellValue('G' . $i,  $usuario)
					->setCellValue('H' . $i,  $asesor);
				$objPHPExcel->getActiveSheet()->getStyle('D' . $i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
			}

			if ($action == '3' || $action == '4' || $action == '5') {

				$detalle_ing_egr = mysqli_query($con, "SELECT detalle_ing_egr as detalle, tipo_ing_egr as tipo FROM detalle_ingresos_egresos WHERE codigo_documento ='" . $codigo_documento . "'");
				$detalle = "";
				foreach ($detalle_ing_egr as $valor) {
					$detalle .= $valor['detalle'] . " // ";
					$tipo_ing_egr = $valor['tipo'];
					if (!is_numeric($tipo_ing_egr)) {
						$tipo_asiento = mysqli_query($con, "SELECT * FROM asientos_tipo WHERE codigo='" . $tipo_ing_egr . "' ");
						$row_asiento = mysqli_fetch_assoc($tipo_asiento);
						$transaccion = $row_asiento['tipo_asiento'];
					} else {
						$tipo_pago = mysqli_query($con, "SELECT * FROM opciones_ingresos_egresos WHERE id='" . $tipo_ing_egr . "' ");
						$row_tipo_pago = mysqli_fetch_assoc($tipo_pago);
						$transaccion = $row_tipo_pago['descripcion'];
					}
				}

				$id_cuenta = $row['id_cuenta'];
				if ($id_cuenta > 0) {
					$cuentas = mysqli_query($con, "SELECT cue_ban.id_cuenta as id_cuenta, concat(ban_ecu.nombre_banco,' ',cue_ban.numero_cuenta,' ', if(cue_ban.id_tipo_cuenta=1,'Aho','Cte')) as cuenta_bancaria FROM cuentas_bancarias as cue_ban INNER JOIN bancos_ecuador as ban_ecu ON cue_ban.id_banco=ban_ecu.id_bancos WHERE cue_ban.id_cuenta ='" . $id_cuenta . "'");
					$row_cuenta = mysqli_fetch_array($cuentas);
					$forma_cobro_pago = strtoupper($row_cuenta['cuenta_bancaria']);
					if ($tipo_documento == 'INGRESO') {
						$tipo = $row['tipo'];
						switch ($tipo) {
							case "D":
								$tipo = 'Depósito';
								break;
							case "T":
								$tipo = 'Transferencia';
								break;
						}
					}
					if ($tipo_documento == 'EGRESO') {
						$tipo = $row['tipo'];
						switch ($tipo) {
							case "D":
								$tipo = 'Débito';
								break;
							case "T":
								$tipo = 'Transferencia';
								break;
							case "C":
								$tipo = 'Cheque' . $cheque;
								break;
						}
					}
				} else {
					$tipo = "";
				}

				$codigo_forma_pago = $row['codigo_forma_pago'];
				if ($codigo_forma_pago > 0) {
					$opciones_pagos = mysqli_query($con, "SELECT * FROM opciones_cobros_pagos WHERE id ='" . $codigo_forma_pago . "'");
					$row_opciones_pagos = mysqli_fetch_array($opciones_pagos);
					$forma_cobro_pago = $row_opciones_pagos['descripcion'];
				}
			}

			if ($action == '3' || $action == '4') {
				$objPHPExcel->setActiveSheetIndex(0)
					->setCellValue('A' . $i,  "=\"" . $numero_ing_egr . "\"")
					->setCellValue('B' . $i,  date("d/m/Y", strtotime($fecha_ing_egr)))
					->setCellValue('C' . $i,  strtoupper($nombre_ing_egr))
					->setCellValue('D' . $i,  number_format($valor_ing_egr, 2, '.', ''))
					->setCellValue('E' . $i,  $transaccion)
					->setCellValue('F' . $i,  $forma_cobro_pago)
					->setCellValue('G' . $i,  $tipo)
					->setCellValue('H' . $i,  $detalle)
					->setCellValue('I' . $i,  $usuario)
					->setCellValue('J' . $i,  $asesor);
				$objPHPExcel->getActiveSheet()->getStyle('D' . $i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
			}

			if ($action == '5') {
				$objPHPExcel->setActiveSheetIndex(0)
					->setCellValue('A' . $i,  "=\"" . $numero_ing_egr . "\"")
					->setCellValue('B' . $i,  date("d/m/Y", strtotime($fecha_ing_egr)))
					->setCellValue('C' . $i,  strtoupper($nombre_ing_egr))
					->setCellValue('D' . $i,  number_format($valor_forma_cobro, 2, '.', ''))
					->setCellValue('E' . $i,  number_format($valor_forma_pago, 2, '.', ''))
					->setCellValue('F' . $i,  $transaccion)
					->setCellValue('G' . $i,  $forma_cobro_pago)
					->setCellValue('H' . $i,  $tipo)
					->setCellValue('I' . $i,  $detalle)
					->setCellValue('J' . $i,  $usuario)
					->setCellValue('K' . $i,  $asesor);
				$objPHPExcel->getActiveSheet()->getStyle('D' . $i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
				$objPHPExcel->getActiveSheet()->getStyle('E' . $i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
			}

			$i++;
		}
	}

	if ($action == '1' || $action == '2' || $action == '3' || $action == '4') {
		$objPHPExcel->setActiveSheetIndex(0)->setCellValue('C' . $i,  "Totales");
		$objPHPExcel->setActiveSheetIndex(0)->setCellValue('D' . $i,  $total_general);
	}
	if ($action == '5') {
		$objPHPExcel->setActiveSheetIndex(0)->setCellValue('C' . $i,  "Totales");
		$objPHPExcel->setActiveSheetIndex(0)->setCellValue('D' . $i,  $total_ingresos);
		$objPHPExcel->setActiveSheetIndex(0)->setCellValue('E' . $i,  $total_egresos);
	}


	for ($i = 'A'; $i <= 'D'; $i++) {
		$objPHPExcel->setActiveSheetIndex(0)
			->getColumnDimension($i)->setAutoSize(TRUE);
	}


	// Se asigna el nombre a la hoja
	$objPHPExcel->getActiveSheet()->setTitle($nombre_reporte);

	// Se activa la hoja para que sea la que se muestre cuando el archivo se abre
	$objPHPExcel->setActiveSheetIndex(0);
	$objPHPExcel->getActiveSheet(0)->freezePaneByColumnAndRow(0, 4);

	// Se manda el archivo al navegador web, con el nombre que se indica (Excel2007)
	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	header('Content-Disposition: attachment;filename=' . $nombre_reporte . ".xlsx");
	header('Cache-Control: max-age=0');

	$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
	$objWriter->save('php://output');
	exit;
}
