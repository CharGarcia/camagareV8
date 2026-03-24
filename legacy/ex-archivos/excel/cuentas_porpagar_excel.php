<?php
require('../clases/egresos.php');
$genera_saldos = new egresos();

$con = conenta_login();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

if ($action == "generar_informe_excel") {

	$desde = "2018/01/01";
	$hasta = $_POST['fecha_hasta'];
	$id_proveedor = $_POST['id_proveedor'];
	ini_set('date.timezone', 'America/Guayaquil');
	//date_default_timezone_set('America/Guayaquil');
	$fecha_hoy = date_create(date("Y-m-d H:i:s"));
	//if(mysqli_num_rows($resultado) > 0 ){			
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
		->setDescription("Reporte cuentas por pagar")
		->setKeywords("Reporte cuentas por pagar")
		->setCategory("Reporte cuentas por pagar");

	//para sacar el nombre de la empresa
	$sql_empresa = mysqli_query($con, "SELECT * FROM empresas where ruc= '" . $ruc_empresa . "'");
	$empresa_info = mysqli_fetch_array($sql_empresa);
	$tituloEmpresa = $empresa_info['nombre_comercial'];
	$tituloReporte = "Reporte Cuentas Por Pagar";

	if (empty($id_proveedor)) { //para todos los proveedores
		$condicion_proveedor = "";
		$condicion_un_proveedor = "";
	} else { //para un proveedor
		$condicion_proveedor = " and id_proveedor=" . $id_proveedor;
		$condicion_un_proveedor = "and sal.id_proveedor=" . $id_proveedor;
	}

	$genera_saldos->saldos_por_pagar($con, $desde, $hasta);

	$busca_proveedores = mysqli_query($con, "SELECT DISTINCT sal.id_proveedor as id_proveedor, sal.razon_social as razon_social FROM saldos_compras_tmp as sal WHERE sal.ruc_empresa = '" . $ruc_empresa . "' $condicion_un_proveedor order by sal.razon_social asc");

	$busca_saldos_total = mysqli_query($con, "SELECT sum(total_compra - (total_egresos + total_retencion + total_egresos_tmp)) as saldo_general FROM saldos_compras_tmp WHERE mid(ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and fecha_compra between '" . date("Y-m-d", strtotime($desde)) . "' and '" . date("Y-m-d", strtotime($hasta)) . "' and id_comprobante !=4 $condicion_proveedor");
	$row_saldo_total = mysqli_fetch_array($busca_saldos_total);
	$suma_general = $row_saldo_total['saldo_general'];

	$busca_saldos_total_nc = mysqli_query($con, "SELECT sum(total_compra + (total_egresos + total_retencion + total_egresos_tmp)) as saldo_general FROM saldos_compras_tmp WHERE mid(ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and fecha_compra between '" . date("Y-m-d", strtotime($desde)) . "' and '" . date("Y-m-d", strtotime($hasta)) . "' and id_comprobante=4 $condicion_proveedor");
	$row_saldo_total_nc = mysqli_fetch_array($busca_saldos_total_nc);
	$suma_general_nc = $row_saldo_total_nc['saldo_general'];

	$corte = "Al " . date("d-m-Y", strtotime($hasta)) . " Total: " . number_format($suma_general - $suma_general_nc, 2, '.', '');

	$titulosColumnas = array('Fecha', 'Proveedor', 'Documento', 'Número', 'Total', 'Abonos', 'Retenciones', 'Saldo', 'Días Crédito', 'Días vencidos');

	$objPHPExcel->setActiveSheetIndex(0)
		->mergeCells('A1:J1')
		->mergeCells('A2:J2')
		->mergeCells('A3:J3')
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
	;
	$i++;

	while ($row_proveedor = mysqli_fetch_array($busca_proveedores)) {
		$ide_proveedor = $row_proveedor['id_proveedor'];
		$nombre_proveedor = $row_proveedor['razon_social'];
		$sql_suma_proveedor = mysqli_query($con, "SELECT sum(total_compra - (total_egresos + total_retencion + total_egresos_tmp)) as total_proveedor FROM saldos_compras_tmp WHERE id_proveedor = '" . $ide_proveedor . "' and mid(ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and id_comprobante !=4 ");
		$row_total_proveedor = mysqli_fetch_array($sql_suma_proveedor);
		$total_proveedor = $row_total_proveedor['total_proveedor'];

		$sql_suma_proveedor_nc = mysqli_query($con, "SELECT sum(total_compra + (total_egresos + total_retencion + total_egresos_tmp)) as total_proveedor FROM saldos_compras_tmp WHERE id_proveedor = '" . $ide_proveedor . "' and mid(ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and id_comprobante =4 ");
		$row_total_proveedor_nc = mysqli_fetch_array($sql_suma_proveedor_nc);
		$total_proveedor_nc = $row_total_proveedor_nc['total_proveedor'];

		if (($total_proveedor - $total_proveedor_nc) != 0) {

			$busca_saldos_general = mysqli_query($con, "SELECT * FROM saldos_compras_tmp as sal_tmp INNER JOIN comprobantes_autorizados as doc_aut ON sal_tmp.id_comprobante=doc_aut.id_comprobante WHERE mid(sal_tmp.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and sal_tmp.fecha_compra between '" . date("Y-m-d", strtotime($desde)) . "' and '" . date("Y-m-d", strtotime($hasta)) . "' and sal_tmp.id_proveedor='" . $ide_proveedor . "' ORDER BY sal_tmp.razon_social asc, sal_tmp.fecha_compra asc ");
			while ($detalle = mysqli_fetch_array($busca_saldos_general)) {
				$fecha_documento = $detalle['fecha_compra'];
				$nombre_documento = $detalle['comprobante'];
				$id_comprobante = $detalle['id_comprobante'];
				$numero_documento = $detalle['numero_documento'];
				$codigo_documento = $detalle['codigo_documento'];
				$total_compra = $detalle['total_compra'];
				$total_abonos = $detalle['total_egresos'];
				$total_retenciones = $detalle['total_retencion'];

				$busca_plazos = mysqli_query($con, "SELECT * FROM formas_pago_compras WHERE codigo_documento = '" . $codigo_documento . "' ");
				$row_plazo = mysqli_fetch_array($busca_plazos);
				$plazo = $row_plazo['plazo_pago'] > 0 ? $row_plazo['plazo_pago'] : 0;

				if ($id_comprobante == 4) {
					$saldo = ($detalle['total_compra'] + ($detalle['total_egresos'] + $detalle['total_retencion'] + $detalle['total_egresos_tmp'])) * -1;
				} else {
					$saldo = $detalle['total_compra'] - ($detalle['total_egresos'] + $detalle['total_retencion'] + $detalle['total_egresos_tmp']);
				}

				$fecha_vencimiento = date_create($fecha_documento);
				$diferencia_dias = date_diff($fecha_hoy, $fecha_vencimiento);
				$total_dias = $diferencia_dias->format('%a');
				if (($saldo) != 0) {
					$objPHPExcel->setActiveSheetIndex(0)
						->setCellValue('A' . $i,  date("d/m/Y", strtotime($fecha_documento)))
						->setCellValue('B' . $i,  $nombre_proveedor)
						->setCellValue('C' . $i,  $nombre_documento)
						->setCellValue('D' . $i,  $numero_documento)
						->setCellValue('E' . $i,  number_format($total_compra, 2, '.', ''))
						->setCellValue('F' . $i,  number_format($total_abonos, 2, '.', ''))
						->setCellValue('G' . $i,  number_format($total_retenciones, 2, '.', ''))
						->setCellValue('H' . $i,  number_format($saldo, 2, '.', ''))
						->setCellValue('I' . $i,  $plazo)
						->setCellValue('J' . $i,  $total_dias - $plazo)
					;
					$i++;
				}
			}
		}
	}

	//$objPHPExcel->getActiveSheet()->getStyle('E4:H' . $i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);



	for ($i = 'A'; $i <= 'J'; $i++) {
		$objPHPExcel->setActiveSheetIndex(0)
			->getColumnDimension($i)->setAutoSize(TRUE);
	}


	// Se asigna el nombre a la hoja
	$objPHPExcel->getActiveSheet()->setTitle('CuentasPorPagar');

	// Se activa la hoja para que sea la que se muestre cuando el archivo se abre
	$objPHPExcel->setActiveSheetIndex(0);
	$objPHPExcel->getActiveSheet(0)->freezePaneByColumnAndRow(0, 5);

	// Se manda el archivo al navegador web, con el nombre que se indica (Excel2007)
	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	header('Content-Disposition: attachment;filename="CuentasPorPagar.xlsx"');
	header('Cache-Control: max-age=0');

	$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
	$objWriter->save('php://output');
	exit;
} else {
	echo ('No es posible generar el archivo.');
}
