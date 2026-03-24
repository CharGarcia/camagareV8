<?php
require("../ajax/reporte_ventas.php");
$ruc_empresa = $_SESSION['ruc_empresa'];
$tipo_reporte = $_POST['tipo_reporte'];
$id_cliente = $_POST['id_cliente'];
$id_producto = $_POST['id_producto'];
$desde = $_POST['fecha_desde'];
$hasta = $_POST['fecha_hasta'];
$id_marca = $_POST['id_marca'];
$id_grupo = $_POST['id_grupo'];
$id_vendedor = $_POST['vendedor'];

$action = $_POST['tipo_reporte']; //(isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

//para sacar el nombre de la empresa
$sql_empresa =  mysqli_query($con, "SELECT * FROM empresas where ruc= '" . $ruc_empresa . "'");
$empresa_info = mysqli_fetch_array($sql_empresa);
$tituloEmpresa = $empresa_info['nombre_comercial'] . "-" . substr($empresa_info['ruc'], 10, 3);

//para facturas
if ($action == '1') {
	$resultado = reporte_ventas_facturas($con, $ruc_empresa, $desde, $hasta, $id_cliente, $id_producto, $id_marca, $id_grupo, $id_vendedor);
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

		$tituloReporte = "Reporte de ventas. Del: " . $desde . " Al: " . $hasta;
		$titulosColumnas = array('Fecha', 'Cliente', 'Ruc', 'Secuencial', 'subtotal', 'IVA', 'Descuento', 'Propina', 'Otros', 'Total', 'Pago', 'Usuario', 'Retenciones', 'Vendedor');

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
			->setCellValue('I3',  $titulosColumnas[8])
			->setCellValue('J3',  $titulosColumnas[9])
			->setCellValue('K3',  $titulosColumnas[10])
			->setCellValue('L3',  $titulosColumnas[11])
			->setCellValue('M3',  $titulosColumnas[12])
			->setCellValue('N3',  $titulosColumnas[13]);

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
				->setCellValue('M' . $i,  $total_retenciones)
				->setCellValue('N' . $i, $fila['vendedor']);
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

		for ($i = 'A'; $i <= 'K'; $i++) {
			$objPHPExcel->setActiveSheetIndex(0)
				->getColumnDimension($i)->setAutoSize(TRUE);
		}

		genera_excel($objPHPExcel, "Ventas", "ReporteVentas");
	} else {
		echo ('No hay resultados para mostrar');
	}
}

//para notas de credito
if ($action == '2') {
	$resultado = reporte_nc($con, $ruc_empresa, $desde, $hasta, $id_cliente, $id_producto, $id_marca, $id_grupo, $id_vendedor);
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

		$tituloReporte = "Reporte de notas de crédito. Del: " . $desde . " Al: " . $hasta;
		$titulosColumnas = array('Fecha', 'Cliente', 'Ruc', 'NC', 'Factura Afectada', 'Subtotal', 'IVA', 'Descuento', 'Total', 'Vendedor');

		$objPHPExcel->setActiveSheetIndex(0)
			->mergeCells('A1:O1')
			->mergeCells('A2:O2');

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
			->setCellValue('J3',  $titulosColumnas[9]);

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
				->setCellValue('I' . $i,  $fila['total_nc'])
				->setCellValue('J' . $i,   $fila['vendedor']);
			$objPHPExcel->getActiveSheet()->getStyle('F' . $i . ':I' . $i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
			$i++;
		}
		$t = $i + 1;
		$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('C' . $t,  'Totales');

		for ($i = 'A'; $i <= 'I'; $i++) {
			$objPHPExcel->setActiveSheetIndex(0)
				->getColumnDimension($i)->setAutoSize(TRUE);
		}

		genera_excel($objPHPExcel, "DetalleNC", "ReporteNCVentas");
	} else {
		echo ('No hay resultados para mostrar');
	}
}


//reporte detalle de ventas
if ($action == '3') {
	$suma_total_factura = 0;
	$suma_cantidad = 0;
	$suma_valor_unitario = 0;
	$suma_subtotal_factura = 0;
	$suma_descuento = 0;
	$suma_iva = 0;
	$resultado = detalle_facturas($con, $ruc_empresa, $id_cliente, $id_producto, $id_marca, $id_grupo, $desde, $hasta);
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
			->setKeywords("reporte ventas")
			->setCategory("Reporte excel");

		$tituloReporte = "Reporte de ventas detallado. Del: " . $desde . " Al: " . $hasta;
		$titulosColumnas = array('Fecha', 'Cliente', 'Ruc', 'Factura', 'Código', 'Detalle', 'Tipo', 'Tarifa', 'Cantidad', 'Valor unitario', 'Descuento', 'Subtotal', 'IVA', 'Total', 'Lote', 'Medida', 'vencimiento', 'Bodega', 'Marca', 'Categoría');

		$objPHPExcel->setActiveSheetIndex(0)
			->mergeCells('A1:S1')
			->mergeCells('A2:S2');

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
			->setCellValue('T3',  $titulosColumnas[19]);
		$i = 4;

		while ($fila = mysqli_fetch_array($resultado)) {
			$fecha_factura = $fila['fecha_factura'];
			$serie_factura = $fila['serie_factura'];
			$secuencial_factura = $fila['secuencial_factura'];
			$nombre_cliente_factura = $fila['nombre_cliente'];
			$ruc_cliente = $fila['ruc'];
			$cantidad = $fila['cantidad_factura'];
			$suma_cantidad += $fila['cantidad_factura'];
			$producto = preg_replace('/"/', "", $fila['nombre_producto']);
			$codigo = $fila['codigo_producto'];
			$valor_unitario = $fila['valor_unitario_factura'];
			$suma_valor_unitario += $fila['valor_unitario_factura'];
			$descuento = $fila['descuento'];
			$suma_descuento += $fila['descuento'];
			$subtotal_factura = $fila['subtotal'];
			$suma_subtotal_factura += $fila['subtotal'];
			$tipo_produccion = $fila['nombre_produccion'];
			$tarifa_iva = $fila['tarifa'];
			$porcentaje_iva = $fila['porcentaje_iva'] / 100;
			$suma_iva += number_format(($subtotal_factura * $porcentaje_iva), 2, '.', '');
			$lote = $fila['lote'];
			$medida = $fila['medida'];
			$vencimiento = $fila['vencimiento'];
			$bodega = $fila['bodega'];
			$total_factura = ($fila['subtotal'] + $fila['total_iva']);
			$suma_total_factura += $total_factura;
			$nombre_marca = $fila['nombre_marca'];
			$nombre_grupo = $fila['nombre_grupo'];


			$objPHPExcel->setActiveSheetIndex(0)
				->setCellValue('A' . $i,  date("d/m/Y", strtotime($fila['fecha_factura'])))
				->setCellValue('B' . $i,  $nombre_cliente_factura)
				->setCellValue('C' . $i,  "=\"" . $fila['ruc'] . "\"")
				->setCellValue('D' . $i,  $fila['serie_factura'] . '-' . str_pad($fila['secuencial_factura'], 9, "000000000", STR_PAD_LEFT))
				->setCellValue('E' . $i,  "=\"" . $codigo . "\"")
				->setCellValue('F' . $i,  "=\"" . $producto . "\"")
				->setCellValue('G' . $i,  $tipo_produccion)
				->setCellValue('H' . $i,  $tarifa_iva)
				->setCellValue('I' . $i,  number_format($cantidad, 4, '.', ''))
				->setCellValue('J' . $i,  number_format($valor_unitario, 4, '.', ''))
				->setCellValue('K' . $i,  number_format($descuento, 2, '.', ''))
				->setCellValue('L' . $i,  number_format($subtotal_factura, 2, '.', ''))
				->setCellValue('M' . $i,  number_format(($subtotal_factura * $porcentaje_iva), 2, '.', ''))
				->setCellValue('N' . $i,  number_format($total_factura, 2, '.', ''))
				->setCellValue('O' . $i,  $lote)
				->setCellValue('P' . $i,  $medida)
				->setCellValue('Q' . $i,  date("d-m-Y", strtotime($vencimiento)))
				->setCellValue('R' . $i,  $bodega)
				->setCellValue('S' . $i,  $nombre_marca)
				->setCellValue('T' . $i,  $nombre_grupo);
			$objPHPExcel->getActiveSheet()->getStyle('I' . $i . ':N' . $i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
			$i = $i + 1;
		} //fin del while

		$t = $i + 1;
		$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('H' . $t,  'Totales')
			->setCellValue('I' . $t,  number_format($suma_cantidad, 4, '.', ''))
			->setCellValue('J' . $t,  number_format($suma_valor_unitario, 4, '.', ''))
			->setCellValue('K' . $t,  number_format($suma_descuento, 2, '.', ''))
			->setCellValue('L' . $t,  number_format($suma_subtotal_factura, 2, '.', ''))
			->setCellValue('M' . $t,  number_format($suma_iva, 2, '.', ''))
			->setCellValue('N' . $t,  number_format($suma_total_factura, 2, '.', ''));
		$objPHPExcel->getActiveSheet()->getStyle('I' . $t . ':N' . $t)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);

		for ($i = 'A'; $i <= 'S'; $i++) {
			$objPHPExcel->setActiveSheetIndex(0)
				->getColumnDimension($i)->setAutoSize(TRUE);
		}

		genera_excel($objPHPExcel, "DetalleVentas", "ReporteDetalleVentas");
	} else {
		print_r('No hay resultados para mostrar');
	}
}


//reporte detalle de NC
if ($action == '4') {
	$suma_total_nc = 0;
	$suma_cantidad = 0;
	$suma_valor_unitario = 0;
	$suma_subtotal_nc = 0;
	$suma_descuento = 0;
	$suma_iva = 0;
	$resultado = detalle_nc($con, $ruc_empresa, $id_cliente, $id_producto, $id_marca, $id_grupo, $desde, $hasta);
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
			->setDescription("Reporte de NC")
			->setKeywords("Reporte NC")
			->setCategory("Reporte excel");
		$tituloReporte = "Reporte de NC detallado. Del: " . $desde . " Al: " . $hasta;
		$titulosColumnas = array('Fecha', 'Cliente', 'Ruc', 'NC', 'Factura Modificada', 'Motivo', 'Código', 'Detalle', 'Tipo', 'Tarifa', 'Cantidad', 'Valor unitario', 'Descuento', 'Subtotal', 'IVA', 'Total', 'Marca', 'Grupo');

		$objPHPExcel->setActiveSheetIndex(0)
			->mergeCells('A1:P1')
			->mergeCells('A2:P2');

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
			->setCellValue('R3',  $titulosColumnas[17]);
		$i = 4;

		while ($row = mysqli_fetch_array($resultado)) {
			$fecha_nc = $row['fecha_nc'];
			$serie_nc = $row['serie_nc'];
			$secuencial_nc = $row['secuencial_nc'];
			$nombre_cliente_nc = $row['nombre_cliente'];
			$ruc_cliente = $row['ruc'];
			$cantidad = $row['cantidad_nc'];
			$suma_cantidad += $row['cantidad_nc'];
			$producto = $row['nombre_producto'];
			$codigo = $row['codigo_producto'];
			$valor_unitario = $row['valor_unitario_nc'];
			$suma_valor_unitario += $row['valor_unitario_nc'];
			$descuento = $row['descuento'];
			$suma_descuento += $row['descuento'];
			$subtotal_nc = $row['subtotal_nc'] - $descuento;
			$suma_subtotal_nc += $row['subtotal_nc'] - $descuento;
			$tipo_produccion = $row['nombre_produccion'];
			$tarifa_iva = $row['tarifa'];
			$porcentaje_iva = $row['porcentaje_iva'] / 100;
			$suma_iva += number_format(($subtotal_nc * $porcentaje_iva), 2, '.', '');
			$total_nc = ($row['subtotal_nc'] - $row['descuento'] + ($subtotal_nc * $porcentaje_iva));
			$suma_total_nc += $total_nc;
			$factura_modificada = $row['factura_modificada'];
			$motivo = $row['motivo'];
			$nombre_marca = $row['nombre_marca'];
			$nombre_grupo = $row['nombre_grupo'];

			$objPHPExcel->setActiveSheetIndex(0)
				->setCellValue('A' . $i,  date("d/m/Y", strtotime($fecha_nc)))
				->setCellValue('B' . $i,  $nombre_cliente_nc)
				->setCellValue('C' . $i,  "=\"" . $ruc_cliente . "\"")
				->setCellValue('D' . $i,  $serie_nc . '-' . str_pad($secuencial_nc, 9, "000000000", STR_PAD_LEFT))
				->setCellValue('E' . $i,  $factura_modificada)
				->setCellValue('F' . $i,  $motivo)
				->setCellValue('G' . $i,  "=\"" . $codigo . "\"")
				->setCellValue('H' . $i,  "=\"" . $producto . "\"")
				->setCellValue('I' . $i,  $tipo_produccion)
				->setCellValue('J' . $i,  $tarifa_iva)
				->setCellValue('K' . $i,  number_format($cantidad, 4, '.', ''))
				->setCellValue('L' . $i,  number_format($valor_unitario, 4, '.', ''))
				->setCellValue('M' . $i,  number_format($descuento, 2, '.', ''))
				->setCellValue('N' . $i,  number_format($subtotal_nc, 2, '.', ''))
				->setCellValue('O' . $i,  number_format(($subtotal_nc * $porcentaje_iva), 2, '.', ''))
				->setCellValue('P' . $i,  number_format($total_nc, 2, '.', ''))
				->setCellValue('Q' . $i,  $nombre_marca)
				->setCellValue('R' . $i,  $nombre_grupo);
			$i = $i + 1;
		} //fin del while

		$t = $i + 1;
		$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('J' . $t,  'Totales')
			->setCellValue('K' . $t,  number_format($suma_cantidad, 4, '.', ''))
			->setCellValue('L' . $t,  number_format($suma_valor_unitario, 4, '.', ''))
			->setCellValue('M' . $t,  number_format($suma_descuento, 2, '.', ''))
			->setCellValue('N' . $t,  number_format($suma_subtotal_nc, 2, '.', ''))
			->setCellValue('O' . $t,  number_format($suma_iva, 2, '.', ''))
			->setCellValue('P' . $t,  number_format($suma_total_nc, 2, '.', ''));

		for ($i = 'A'; $i <= 'P'; $i++) {
			$objPHPExcel->setActiveSheetIndex(0)
				->getColumnDimension($i)->setAutoSize(TRUE);
		}

		genera_excel($objPHPExcel, "DetalleNC", "ReporteDetalleNC");
	} else {
		print_r('No hay resultados para mostrar');
	}
}

//para recibos de venta
if ($action == '5') {
	$resultado = recibos($con, $ruc_empresa, $id_cliente, $id_producto, $id_marca, $id_grupo, $desde, $hasta);
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
			->setDescription("Reporte de recibos de venta")
			->setKeywords("Reporte Recibos de ventas")
			->setCategory("Reporte excel");

		$tituloReporte = "Reporte de recibos de venta. Del: " . $desde . " Al: " . $hasta;
		$titulosColumnas = array('Fecha', 'Ruc', 'Cliente', 'Recibo', 'Total', 'Usuario', 'Vendedor');

		$objPHPExcel->setActiveSheetIndex(0)
			->mergeCells('A1:C1')
			->mergeCells('A2:C2');

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
			->setCellValue('G3',  $titulosColumnas[6]);
		$i = 4;
		$suma_recibos = 0;
		while ($row = mysqli_fetch_array($resultado)) {
			$suma_recibos += $row['total_recibo'];
			$fecha_recibo = $row['fecha_recibo'];
			$serie_recibo = $row['serie_recibo'];
			$secuencial_recibo = $row['secuencial_recibo'];
			$nombre_cliente_recibo = $row['nombre'];
			$total_recibo = $row['total_recibo'];
			$ruc_cliente = $row['ruc'];


			$objPHPExcel->setActiveSheetIndex(0)
				->setCellValue('A' . $i,  date("d/m/Y", strtotime($row['fecha_recibo'])))
				->setCellValue('B' . $i,  "=\"" . $row['ruc'] . "\"")
				->setCellValue('C' . $i,  strtoupper($row['nombre']))
				->setCellValue('D' . $i,  $row['serie_recibo'] . '-' . str_pad($row['secuencial_recibo'], 9, "000000000", STR_PAD_LEFT))
				->setCellValue('E' . $i,  number_format($row['total_recibo'], 2, '.', ''))
				->setCellValue('F' . $i,  strtoupper($row['nombre_usuario']))
				->setCellValue('G' . $i, $row['vendedor']);
			$objPHPExcel->getActiveSheet()->getStyle('E' . $i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
			$i++;
		}
		$t = $i + 1;
		$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('D' . $t,  'Totales')
			->setCellValue('E' . $t,  number_format($suma_recibos, 2, '.', ''));
		$objPHPExcel->getActiveSheet()->getStyle('E' . $t)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
		for ($i = 'A'; $i <= 'G'; $i++) {
			$objPHPExcel->setActiveSheetIndex(0)
				->getColumnDimension($i)->setAutoSize(TRUE);
		}

		genera_excel($objPHPExcel, "RecibosVenta", "ReporteRecibosVenta");
	} else {
		echo ('No hay resultados para mostrar');
	}
}

//para recibos de venta en detalle
if ($action == '6') {
	$resultado = detalle_recibos($con, $ruc_empresa, $id_cliente, $id_producto, $id_marca, $id_grupo, $desde, $hasta);
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
			->setDescription("Reporte de recibos de venta")
			->setKeywords("Reporte Recibos de ventas")
			->setCategory("Reporte excel");

		$tituloReporte = "Reporte de recibos de venta. Del: " . $desde . " Al: " . $hasta;
		$titulosColumnas = array('Fecha', 'Cliente', 'Ruc', 'Recibo', 'Código', 'Detalle', 'Tarifa', 'Cantidad', 'Valor unitario', 'Descuento', 'Subtotal', 'IVA', 'Total', 'Lote', 'Medida', 'vencimiento', 'Bodega', 'Marca', 'Categoría');

		$objPHPExcel->setActiveSheetIndex(0)
			->mergeCells('A1:C1')
			->mergeCells('A2:C2');

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
			->setCellValue('S3',  $titulosColumnas[18]);
		$i = 4;
		$suma_subtotal = 0;
		$suma_iva = 0;
		$suma_total = 0;
		while ($row = mysqli_fetch_array($resultado)) {
			$suma_subtotal += $row['subtotal'];
			$suma_iva += ($row['subtotal'] * $row['porcentaje_iva']);
			$suma_total += $row['subtotal'] + ($row['subtotal'] * $row['porcentaje_iva']);


			$objPHPExcel->setActiveSheetIndex(0)
				->setCellValue('A' . $i,  date("d-m-Y", strtotime($row['fecha_recibo'])))
				->setCellValue('B' . $i,  strtoupper($row['nombre']))
				->setCellValue('C' . $i,  "=\"" . $row['ruc'] . "\"")
				->setCellValue('D' . $i,  $row['recibo'])
				->setCellValue('E' . $i,  "=\"" . $row['codigo'] . "\"")
				->setCellValue('F' . $i,  "=\"" . $row['detalle'] . "\"")
				->setCellValue('G' . $i,  $row['tarifa'])
				->setCellValue('H' . $i,  number_format($row['cantidad'], 2, '.', ''))
				->setCellValue('I' . $i,  number_format($row['precio'], 2, '.', ''))
				->setCellValue('J' . $i,  number_format($row['descuento'], 2, '.', ''))
				->setCellValue('K' . $i,  number_format($row['subtotal'], 2, '.', ''))
				->setCellValue('L' . $i,  number_format($row['subtotal'] * $row['porcentaje_iva'], 2, '.', ''))
				->setCellValue('M' . $i,  number_format($row['subtotal'] + ($row['subtotal'] * $row['porcentaje_iva']), 2, '.', ''))
				->setCellValue('N' . $i,  $row['lote'])
				->setCellValue('O' . $i,  $row['medida'])
				->setCellValue('P' . $i,  date("d-m-Y", strtotime($row['vencimiento'])))
				->setCellValue('Q' . $i,  $row['bodega'])
				->setCellValue('R' . $i,  $row['marca'])
				->setCellValue('S' . $i,  $row['categoria']);
			$objPHPExcel->getActiveSheet()->getStyle('H' . $i . ':M' . $i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
			$i++;
		}
		$t = $i + 1;
		$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('G' . $t,  'Totales')
			->setCellValue('K' . $t,  number_format($suma_subtotal, 2, '.', ''))
			->setCellValue('L' . $t,  number_format($suma_iva, 2, '.', ''))
			->setCellValue('M' . $t,  number_format($suma_total, 2, '.', ''));
		$objPHPExcel->getActiveSheet()->getStyle('K' . $t . ':M' . $t)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
		for ($i = 'A'; $i <= 'G'; $i++) {
			$objPHPExcel->setActiveSheetIndex(0)
				->getColumnDimension($i)->setAutoSize(TRUE);
		}

		genera_excel($objPHPExcel, "RecibosVenta", "ReporteDetRecibosVenta");
	} else {
		echo ('No hay resultados para mostrar');
	}
}

function genera_excel($objPHPExcel, $nombre_hoja, $nombre_archivo)
{
	// Se asigna el nombre a la hoja
	$nombre_archivo = $nombre_archivo . ".xlsx";
	$objPHPExcel->getActiveSheet()->setTitle($nombre_hoja);

	// Se activa la hoja para que sea la que se muestre cuando el archivo se abre
	$objPHPExcel->setActiveSheetIndex(0);
	$objPHPExcel->getActiveSheet(0)->freezePaneByColumnAndRow(0, 4);

	// Se manda el archivo al navegador web, con el nombre que se indica (Excel2007)
	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	header('Content-Disposition: attachment;filename="' . $nombre_archivo . '"');
	header('Cache-Control: max-age=0');

	$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
	$objWriter->save('php://output');
	exit;
}
