<?php
require('../ajax/cuentas_por_cobrar.php');
require_once('lib/PHPExcel/PHPExcel.php');
//require('../helpers/helpers.php');

$con = conenta_login();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
date_default_timezone_set('America/Guayaquil');
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

if ($action == "generar_informe_excel") {
	$objPHPExcel = new PHPExcel();
	$objPHPExcel->getProperties()->setCreator("CaMaGaRe") //Autor
		->setLastModifiedBy("CaMaGaRe") //Ultimo usuario que lo modificó
		->setTitle("Reporte Excel")
		->setSubject("Reporte Excel")
		->setDescription("Reporte cuentas por cobrar")
		->setKeywords("Reporte cuentas por cobrar")
		->setCategory("Reporte cuentas por cobrar");
	//para sacar el nombre de la empresa
	$sql_empresa = mysqli_query($con, "SELECT * FROM empresas where ruc= '" . $ruc_empresa . "'");
	$empresa_info = mysqli_fetch_array($sql_empresa);
	$tituloEmpresa = $empresa_info['nombre_comercial'];
	$tituloReporte = "Reporte de cuentas por cobrar por facturas de venta";
	$tituloReporteRecibo = "Reporte de cuentas por cobrar por recibos de venta";

	$desde = "2018/01/01";
	$hasta = $_POST['fecha_hasta'];
	$id_cliente = $_POST['id_cliente'];
	$vendedor = $_POST['vendedor'];
	ini_set('date.timezone', 'America/Guayaquil');
	$fecha_hoy = date_create(date("Y-m-d H:i:s"));

	if (PHP_SAPI == 'cli')
		die('Este archivo solo se puede ver desde un navegador web');
	// Se asignan las propiedades del libro

	$busca_clientes = mysqli_query($con, "SELECT DISTINCT id_cli_pro as id, nombre_cli_pro as nombre, cli.plazo as plazo 
			FROM saldo_porcobrar_porpagar as sal INNER JOIN clientes as cli ON cli.id=sal.id_cli_pro 
			WHERE sal.ruc_empresa = '" . $ruc_empresa . "' order by sal.nombre_cli_pro asc");
	$corte = "Al " . date("d-m-Y", strtotime($hasta));

	$titulosColumnas = array('Fecha', 'Factura', 'Cliente', 'Subtotal', 'IVA', 'Total', 'Notas de crédito', 'Abonos', 'Retenciones', 'Saldo individual', 'Saldo acumulado', 'Días Crédito', 'Días vencidos', 'Vendedor', 'Usuario');

	$objPHPExcel->setActiveSheetIndex(0)
		->mergeCells('A1:N1')
		->mergeCells('A2:N2')
		->mergeCells('A3:N3')
	;

	$i = 4;
	// Se agregan los titulos del reporte
	$objPHPExcel->setActiveSheetIndex(0)
		->setCellValue('A1', $tituloEmpresa)
		->setCellValue('A2',  $tituloReporte)
		->setCellValue('A3',  $corte)
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
		->setCellValue('O' . $i,  $titulosColumnas[14])
	;
	$i++;

	while ($row_clientes = mysqli_fetch_array($busca_clientes)) {
		$ide_cliente = $row_clientes['id'];
		$nombre_cliente = $row_clientes['nombre'];
		$plazo = $row_clientes['plazo'];
		$sql_suma_cliente = mysqli_query($con, "SELECT sum(total_factura - (total_nc + total_ing  + ing_tmp + total_ret)) as total_cliente FROM saldo_porcobrar_porpagar WHERE id_cli_pro = '" . $ide_cliente . "' and id_usuario='" . $id_usuario . "' and ruc_empresa='" . $ruc_empresa . "'");
		$row_total_cliente = mysqli_fetch_array($sql_suma_cliente);
		$total_cliente = $row_total_cliente['total_cliente'];

		if ($total_cliente > 0) {
			$saldo_acumulado = 0;
			$busca_saldos_general = mysqli_query($con, "SELECT * FROM saldo_porcobrar_porpagar WHERE id_usuario = '" . $id_usuario . "' and ruc_empresa='" . $ruc_empresa . "' and id_cli_pro='" . $ide_cliente . "' ORDER BY nombre_cli_pro asc, fecha_documento asc, numero_documento asc ");
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
				$saldo_acumulado += $saldo;
				$fecha_vencimiento = date_create($fecha_documento);
				$diferencia_dias = date_diff($fecha_hoy, $fecha_vencimiento);
				$total_dias = $diferencia_dias->format('%a');

				$datos_subtotal = mysqli_query($con, "SELECT usu.nombre as usuario, sum(cue_fac.subtotal_factura) as subtotal FROM encabezado_factura as enc_fac INNER JOIN cuerpo_factura as cue_fac ON cue_fac.serie_factura=enc_fac.serie_factura and cue_fac.secuencial_factura=enc_fac.secuencial_factura and cue_fac.ruc_empresa=enc_fac.ruc_empresa INNER JOIN usuarios as usu ON usu.id=enc_fac.id_usuario WHERE enc_fac.id_encabezado_factura = '" . $id_encabezado . "' and mid(enc_fac.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' group by cue_fac.secuencial_factura and cue_fac.serie_factura");
				$detalle_subtotal = mysqli_fetch_array($datos_subtotal);
				$subtotal = $detalle_subtotal['subtotal'];
				$usuario = $detalle_subtotal['usuario'];

				$datos_iva = mysqli_query($con, "SELECT round(sum(cue_fac.subtotal_factura * (tarifa.porcentaje_iva/100)),2) as iva 
				FROM encabezado_factura as enc_fac 
				INNER JOIN cuerpo_factura as cue_fac 
				ON cue_fac.serie_factura=enc_fac.serie_factura 
				and cue_fac.secuencial_factura=enc_fac.secuencial_factura 
				and cue_fac.ruc_empresa=enc_fac.ruc_empresa 
				INNER JOIN tarifa_iva as tarifa 
				ON tarifa.codigo=cue_fac.tarifa_iva 
				WHERE enc_fac.id_encabezado_factura = '" . $id_encabezado . "' and mid(enc_fac.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' group by cue_fac.secuencial_factura and cue_fac.serie_factura and cue_fac.tarifa_iva ");
				$detalle_iva = mysqli_fetch_array($datos_iva);
				$subtotal_iva = $detalle_iva['iva'];

				$datos_vendedor = mysqli_query($con, "SELECT * FROM vendedores as ven INNER JOIN vendedores_ventas as ven_ven ON ven_ven.id_vendedor=ven.id_vendedor WHERE  ven_ven.id_venta= '" . $id_encabezado . "' ");
				$detalle_vendedor = mysqli_fetch_array($datos_vendedor);
				$nombre_vendedor = $detalle_vendedor['nombre'];
				$dias_vencidos = $total_dias - $plazo;

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
					->setCellValue('J' . $i,  number_format($saldo, 2, '.', ''))
					->setCellValue('K' . $i,  number_format($saldo_acumulado, 2, '.', ''))
					->setCellValue('L' . $i,  $plazo)
					->setCellValue('M' . $i, ($dias_vencidos) > 0 ? $dias_vencidos : 0)
					->setCellValue('N' . $i,  $nombre_vendedor)
					->setCellValue('O' . $i,  $usuario)
				;
				$i++;
			}
		}
	}
	$objPHPExcel->getActiveSheet()->getStyle('D5:K' . $i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
	//$objPHPExcel->getActiveSheet()->getStyle('E4:E' . $i)->getNumberFormat()->setFormatCode('#,##0.00');

	for ($i = 'A'; $i <= 'N'; $i++) {
		$objPHPExcel->setActiveSheetIndex(0)
			->getColumnDimension($i)->setAutoSize(TRUE);
	}



	$objPHPExcel->setActiveSheetIndex(0);
	$objPHPExcel->getActiveSheet(0)->freezePaneByColumnAndRow(0, 5);

	$objPHPExcel->getActiveSheet(0)->setTitle('Facturas');
	//hasta aqui facturas

	//desde aqui recibos
	$objPHPExcel->createSheet();
	$objPHPExcel->setActiveSheetIndex(1)->setTitle('Recibos');

	$titulosColumnas = array('Fecha', 'Recibo', 'Cliente', 'Total', 'Abonos', 'Saldo', 'Días vencidos', 'Vendedor', 'Usuario');

	$objPHPExcel->setActiveSheetIndex(1)
		->mergeCells('A1:I1')
		->mergeCells('A2:I2')
		->mergeCells('A3:I3')
	;

	$i = 4;
	// Se agregan los titulos del reporte
	$objPHPExcel->setActiveSheetIndex(1)
		->setCellValue('A1', $tituloEmpresa)
		->setCellValue('A2',  $tituloReporteRecibo)
		->setCellValue('A3',  $corte)
	;

	$objPHPExcel->setActiveSheetIndex(1)
		->setCellValue('A' . $i,  $titulosColumnas[0])
		->setCellValue('B' . $i,  $titulosColumnas[1])
		->setCellValue('C' . $i,  $titulosColumnas[2])
		->setCellValue('D' . $i,  $titulosColumnas[3])
		->setCellValue('E' . $i,  $titulosColumnas[4])
		->setCellValue('F' . $i,  $titulosColumnas[5])
		->setCellValue('G' . $i,  $titulosColumnas[6])
		->setCellValue('H' . $i,  $titulosColumnas[7])
		->setCellValue('I' . $i,  $titulosColumnas[8])
	;
	$i++;
	//$id_cliente_recibo = "";
	$busca_clientes_recibos = clientes_recibos($con, $hasta, $ruc_empresa, $id_cliente, $vendedor);

	while ($row_clientes_recibo = mysqli_fetch_array($busca_clientes_recibos)) {
		$ide_cliente_recibo = $row_clientes_recibo['id'];
		$nombre_cliente = $row_clientes_recibo['nombre'];
		$plazo = $row_clientes_recibo['plazo'];

		$total_cliente_recibo = saldo_recibo_por_cliente($con, $ruc_empresa, $hasta, $ide_cliente_recibo);

		if ($total_cliente_recibo > 0) {
			$recibos_individuales = recibos_del_cliente($con, $ruc_empresa, $hasta, $ide_cliente_recibo);
			while ($detalle_recibo = mysqli_fetch_array($recibos_individuales)) {
				$id_encabezado = $detalle_recibo['id_encabezado_recibo'];
				$id_documento = "RV" . $id_encabezado;
				$fecha_documento = $detalle_recibo['fecha_recibo'];
				$nombre_cliente = $detalle_recibo['nombre'];
				$numero_documento = $detalle_recibo['serie_recibo'] . "-" . str_pad($detalle_recibo['secuencial_recibo'], 9, "000000000", STR_PAD_LEFT);
				$total_recibo = $detalle_recibo['total_recibo'];
				$fecha_vencimiento = date_create($fecha_documento);
				$diferencia_dias = date_diff($fecha_hoy, $fecha_vencimiento);
				$total_dias = $diferencia_dias->format('%a');
				$dias_vencidos = $total_dias - $plazo;

				$nombre_vendedor = vendedores_recibos($con, $id_encabezado);

				$suma_abonos_recibo = abonos_cliente_recibo($con, $ruc_empresa, $hasta, $id_documento);
				$saldo = $total_recibo - $suma_abonos_recibo;

				$usuario = usuario_recibo($con, $detalle_recibo['id_usuario']);

				if ($saldo > 0) {
					$objPHPExcel->setActiveSheetIndex(1)
						->setCellValue('A' . $i,  date("d/m/Y", strtotime($fecha_documento)))
						->setCellValue('B' . $i,  $numero_documento)
						->setCellValue('C' . $i,  $nombre_cliente)
						->setCellValue('D' . $i,  number_format($total_recibo, 2, '.', ''))
						->setCellValue('E' . $i,  number_format($suma_abonos_recibo, 2, '.', ''))
						->setCellValue('F' . $i,  number_format($saldo, 2, '.', ''))
						->setCellValue('G' . $i, ($dias_vencidos) > 0 ? $dias_vencidos : 0)
						->setCellValue('H' . $i,  $nombre_vendedor)
						->setCellValue('I' . $i,  $usuario)
					;
					$objPHPExcel->getActiveSheet(1)->getStyle('D' . $i . ':F' . $i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
					$i++;
				}
			}
		}
	}

	for ($i = 'A'; $i <= 'I'; $i++) {
		$objPHPExcel->setActiveSheetIndex(1)
			->getColumnDimension($i)->setAutoSize(TRUE);
	}


	$objPHPExcel->setActiveSheetIndex(1);
	$objPHPExcel->getActiveSheet(1)->freezePaneByColumnAndRow(0, 5);

	//hasta aqui recibos

	$objPHPExcel->setActiveSheetIndex(0);
	genera_excel($objPHPExcel, "CuentasPorCobrar");
} else {
	echo ('No tiene acceso a este servicio.');
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
