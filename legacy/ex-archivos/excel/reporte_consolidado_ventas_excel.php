<?php
include("../conexiones/conectalogin.php");
require_once("../helpers/helpers.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];

$tipo_reporte = $_POST['tipo_reporte'];
//$id_cliente=$_POST['id_cliente'];
//$id_producto=$_POST['id_producto'];
$desde = $_POST['fecha_desde'];
$hasta = $_POST['fecha_hasta'];
//$id_marca=$_POST['id_marca'];

//para facturas
if ($tipo_reporte == '1') {
	$resultado = mysqli_query($con, "SELECT  cli.nombre as cliente, cli.ruc as ruc, cli.id as id_cliente,
		enc_fac.serie_factura as serie_factura, enc_fac.secuencial_factura as secuencial_factura, sum(cue_fac.subtotal_factura) as subtotal,
			sum(cue_fac.descuento) as descuento, tar.porcentaje_iva as porcentaje_iva, sum((cue_fac.subtotal_factura - cue_fac.descuento) * (tar.porcentaje_iva /100)) as total_iva,
			usu.nombre as usuario, enc_fac.propina as propina, enc_fac.tasa_turistica as tasa_turistica, enc_fac.total_factura as total_factura, enc_fac.fecha_factura as fecha_factura
			FROM cuerpo_factura as cue_fac INNER JOIN encabezado_factura as enc_fac ON enc_fac.serie_factura=cue_fac.serie_factura and enc_fac.secuencial_factura=cue_fac.secuencial_factura 
			INNER JOIN clientes as cli ON cli.id=enc_fac.id_cliente
			INNER JOIN tarifa_iva as tar ON tar.codigo=cue_fac.tarifa_iva 
			LEFT JOIN productos_servicios as pro_ser ON pro_ser.id=cue_fac.id_producto  
			LEFT JOIN usuarios as usu ON usu.id=enc_fac.id_usuario
			WHERE mid(enc_fac.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and 
		mid(cue_fac.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' 
			and DATE_FORMAT(enc_fac.fecha_factura, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
			and '" . date("Y/m/d", strtotime($hasta)) . "' group by cue_fac.serie_factura, cue_fac.secuencial_factura");

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
			->setTitle("Reporte Excel")
			->setSubject("Reporte Excel")
			->setDescription("Reporte de ventas")
			->setKeywords("Reporte ventas")
			->setCategory("Reporte excel");

		//para sacar el nombre de la empresa
		$sql_empresa = "SELECT * FROM empresas where ruc= '" . $ruc_empresa . "'";
		$resultado_empresa = mysqli_query($con, $sql_empresa);
		$empresa_info = mysqli_fetch_array($resultado_empresa);
		$tituloEmpresa = $empresa_info['nombre_comercial'];
		$tituloReporte = "Reporte de ventas consolidado de todas las sucursales";
		$titulosColumnas = array('Fecha', 'Cliente', 'Ruc', 'Secuencial', 'subtotal', 'IVA', 'Descuento', 'Propina', 'Otros', 'Total', 'Pago', 'Usuario', 'Retenciones');

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
			->setCellValue('K3',  $titulosColumnas[10])
			->setCellValue('L3',  $titulosColumnas[11])
			->setCellValue('M3',  $titulosColumnas[12]);
		$i = 4;
		$suma_subtotal = 0;
		$suma_iva = 0;
		$suma_descuento = 0;
		$suma_propina = 0;
		$suma_tasa_turistica = 0;
		$suma_total_factura = 0;
		$suma_total_retenciones = 0;
			while ($fila = mysqli_fetch_array($resultado)) {
				$sql_forma_pago = mysqli_query($con, "SELECT * FROM formas_pago_ventas fpv, formas_de_pago fp where fpv.serie_factura = '" . $fila['serie_factura'] . "' and fpv.secuencial_factura = '" . $fila['secuencial_factura'] . "' and fpv.ruc_empresa= '" . $ruc_empresa . "' and fpv.id_forma_pago = fp.codigo_pago ");
				$forma_de_pago = mysqli_fetch_array($sql_forma_pago);
				$forma_pago = $forma_de_pago['nombre_pago'];
	
				$id_cliente = $fila['id_cliente'];
				$numero_factura = str_replace("-", "", $fila['serie_factura']) . str_pad($fila['secuencial_factura'], 9, "000000000", STR_PAD_LEFT);
				$sql_retenciones = mysqli_query($con, "SELECT round(sum(cue.valor_retenido),2) as valor_retenido 
							FROM cuerpo_retencion_venta as cue INNER JOIN encabezado_retencion_venta as enc 
							ON cue.codigo_unico=enc.codigo_unico 
							WHERE enc.numero_documento='" . $numero_factura . "' and enc.id_cliente='" . $id_cliente . "' group by cue.codigo_unico");
				$row_retenciones = mysqli_fetch_array($sql_retenciones);
				$total_retenciones = isset($row_retenciones['valor_retenido']) ? $row_retenciones['valor_retenido'] : 0;
	
				$suma_subtotal += $fila['subtotal'];
				$suma_iva += $fila['total_iva'];
				$suma_descuento += $fila['descuento'];
				$suma_propina += $fila['propina'];
				$suma_tasa_turistica += $fila['tasa_turistica'];
				$suma_total_factura += $fila['total_factura'];
				$suma_total_retenciones += $total_retenciones;

				$objPHPExcel->setActiveSheetIndex(0)
				->setCellValue('A' . $i,  date("d/m/Y", strtotime($fila['fecha_factura'])))
				->setCellValue('B' . $i,  strtoupper($fila['cliente']))
				->setCellValue('C' . $i,  "=\"" . $fila['ruc'] . "\"")
				->setCellValue('D' . $i,  $fila['serie_factura'] . '-' . str_pad($fila['secuencial_factura'], 9, "000000000", STR_PAD_LEFT))
				->setCellValue('E' . $i,  number_format($fila['subtotal'], 2, '.', ''))
				->setCellValue('F' . $i,  number_format($fila['total_iva'], 2, '.', ''))
				->setCellValue('G' . $i,  number_format($fila['descuento'], 2, '.', ''))
				->setCellValue('H' . $i,  number_format($fila['propina'], 2, '.', ''))
				->setCellValue('I' . $i,  number_format($fila['tasa_turistica'], 2, '.', ''))
				->setCellValue('J' . $i,  number_format($fila['total_factura'], 2, '.', ''))
				->setCellValue('K' . $i,  strtoupper($forma_pago))
				->setCellValue('L' . $i,  strtoupper($fila['usuario']))
				->setCellValue('M' . $i,  $total_retenciones);
			$objPHPExcel->getActiveSheet()->getStyle('E' . $i . ':J' . $i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
			$objPHPExcel->getActiveSheet()->getStyle('M' . $i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
			$i++;
			}
		
			$t = $i + 1;
			$objPHPExcel->setActiveSheetIndex(0)
				->setCellValue('D' . $t,  'Totales')
				->setCellValue('E' . $t,  number_format($suma_subtotal, 2, '.', ''))
				->setCellValue('F' . $t,  number_format($suma_iva, 2, '.', ''))
				->setCellValue('G' . $t,  number_format($suma_descuento, 2, '.', ''))
				->setCellValue('H' . $t,  number_format($suma_propina, 2, '.', ''))
				->setCellValue('I' . $t,  number_format($suma_tasa_turistica, 2, '.', ''))
				->setCellValue('J' . $t,  number_format($suma_total_factura, 2, '.', ''))
				->setCellValue('M' . $t,  number_format($suma_total_retenciones, 2, '.', ''));
			$objPHPExcel->getActiveSheet()->getStyle('E' . $t . ':M' . $t)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
	
		for ($i = 'A'; $i <= 'C'; $i++) {
			$objPHPExcel->setActiveSheetIndex(0)
				->getColumnDimension($i)->setAutoSize(TRUE);
		}

		// Se asigna el nombre a la hoja
		$objPHPExcel->getActiveSheet()->setTitle('Ventas');

		// Se activa la hoja para que sea la que se muestre cuando el archivo se abre
		$objPHPExcel->setActiveSheetIndex(0);
		$objPHPExcel->getActiveSheet(0)->freezePaneByColumnAndRow(0, 4);

		// Se manda el archivo al navegador web, con el nombre que se indica (Excel2007)
		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment;filename="Reportedeventas.xlsx"');
		header('Cache-Control: max-age=0');

		$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
		$objWriter->save('php://output');
		exit;
	} else {
		echo ('No hay resultados para mostrar');
	}
}

//para notas de credito
if ($tipo_reporte == '2') {
	
	$resultado = mysqli_query($con, "SELECT cli.nombre as cliente, sum(cue_nc.subtotal_nc) as subtotal, enc_nc.id_encabezado_nc as id_encabezado_nc,
	enc_nc.serie_nc as serie_nc, enc_nc.secuencial_nc as secuencial_nc, enc_nc.factura_modificada as factura_modificada,
	enc_nc.fecha_nc as fecha_nc, enc_nc.total_nc as total_nc, cli.ruc as ruc,
	tar.porcentaje_iva as porcentaje_iva, sum((cue_nc.subtotal_nc - cue_nc.descuento) * (tar.porcentaje_iva /100)) as total_iva,
	sum(cue_nc.descuento) as descuento 
	FROM cuerpo_nc as cue_nc 
	INNER JOIN encabezado_nc as enc_nc ON enc_nc.serie_nc=cue_nc.serie_nc and enc_nc.secuencial_nc=cue_nc.secuencial_nc 
	INNER JOIN clientes as cli ON cli.id=enc_nc.id_cliente 
	INNER JOIN tarifa_iva as tar ON tar.codigo=cue_nc.tarifa_iva
	LEFT JOIN productos_servicios as pro_ser ON pro_ser.id=cue_nc.id_producto 
	LEFT JOIN marca_producto as mar_pro ON mar_pro.id_producto=cue_nc.id_producto 
	LEFT JOIN grupo_producto_asignado as grupo_asi ON grupo_asi.id_producto=cue_nc.id_producto
	LEFT JOIN vendedores_ncv as ven_ncv ON ven_ncv.id_ncv= enc_nc.id_encabezado_nc 
	LEFT JOIN vendedores as ven ON ven.id_vendedor=ven_ncv.id_vendedor
	WHERE mid(enc_nc.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and mid(cue_nc.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and DATE_FORMAT(enc_nc.fecha_nc, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
	and '" . date("Y/m/d", strtotime($hasta)) . "' group by cue_nc.serie_nc, cue_nc.secuencial_nc");

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
			->setTitle("Reporte Excel")
			->setSubject("Reporte Excel")
			->setDescription("Reporte de notas de crédito")
			->setKeywords("reporte de notas de crédito")
			->setCategory("Reporte excel");

		//para sacar el nombre de la empresa
		$sql_empresa = "SELECT * FROM empresas where ruc= '" . $ruc_empresa . "'";
		$resultado_empresa = mysqli_query($con, $sql_empresa);
		$empresa_info = mysqli_fetch_array($resultado_empresa);
		$tituloEmpresa = $empresa_info['nombre_comercial'];
		$tituloReporte = "Reporte de notas de crédito";
		$titulosColumnas = array('Fecha', 'Cliente', 'Ruc', 'NC', 'Factura Afectada', 'Subtotal', 'IVA', 'Descuento', 'Total');
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
		->setCellValue('D3',  $titulosColumnas[3])
		->setCellValue('E3',  $titulosColumnas[4])
		->setCellValue('F3',  $titulosColumnas[5])
		->setCellValue('G3',  $titulosColumnas[6])
		->setCellValue('H3',  $titulosColumnas[7])
		->setCellValue('I3',  $titulosColumnas[8]);
	$i = 4;

		while ($fila = mysqli_fetch_array($resultado)) {
			$serie = $fila['serie_nc'];
			$secuencial = $fila['secuencial_nc'];
			$factura_afectada = $fila['factura_modificada'];
			$id_encabezado_nc = $fila['id_encabezado_nc '];

			$objPHPExcel->setActiveSheetIndex(0)
				->setCellValue('A' . $i,  date("d/m/Y", strtotime($fila['fecha_nc'])))
				->setCellValue('B' . $i,  strtoupper($fila['cliente']))
				->setCellValue('C' . $i,  "=\"" . $fila['ruc'] . "\"")
				->setCellValue('D' . $i,  $fila['serie_nc'] . '-' . $fila['secuencial_nc'])
				->setCellValue('E' . $i,  $fila['factura_modificada'])
				->setCellValue('F' . $i,  $fila['subtotal'])
				->setCellValue('G' . $i,  $fila['total_iva'])
				->setCellValue('H' . $i,  $fila['descuento'])
				->setCellValue('I' . $i,  $fila['total_nc']);
			$objPHPExcel->getActiveSheet()->getStyle('F' . $i . ':I' . $i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
			$i++;
		}
		$t = $i + 1;
		$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('C' . $t,  'Totales');

		for ($i = 'A'; $i <= 'D'; $i++) {
			$objPHPExcel->setActiveSheetIndex(0)
				->getColumnDimension($i)->setAutoSize(TRUE);
		}

		// Se asigna el nombre a la hoja
		$objPHPExcel->getActiveSheet()->setTitle('nc');

		// Se activa la hoja para que sea la que se muestre cuando el archivo se abre
		$objPHPExcel->setActiveSheetIndex(0);
		$objPHPExcel->getActiveSheet(0)->freezePaneByColumnAndRow(0, 4);

		// Se manda el archivo al navegador web, con el nombre que se indica (Excel2007)
		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment;filename="ReportedeNC.xlsx"');
		header('Cache-Control: max-age=0');

		$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
		$objWriter->save('php://output');
		exit;
	} else {
		echo ('No hay resultados para mostrar');
	}
}
?>
