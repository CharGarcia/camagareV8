<?php
//error_reporting(E_ALL);
//ini_set('display_errors', 1);
require("../ajax/reporte_compras.php");
$ruc_empresa = $_SESSION['ruc_empresa'];
//PARA BUSCAR LAS FACTURAS de ventas	
$action = $_POST['tipo_reporte'];
$id_proveedor = $_POST['id_proveedor'];
$desde = $_POST['desde'];
$hasta = $_POST['hasta'];
//para sacar el nombre de la empresa
$sql_empresa =  mysqli_query($con, "SELECT * FROM empresas where ruc= '" . $ruc_empresa . "'");
$empresa_info = mysqli_fetch_array($sql_empresa);
$tituloEmpresa = $empresa_info['nombre_comercial'] . "-" . substr($empresa_info['ruc'], 10, 3);

if ($action == '1' || $action == '2') {
	if ($action == '1') {
		$resultados = reporte_compras($con, $ruc_empresa, $desde, $hasta, $id_proveedor, '1');
		$nombre_archivo = "Adquisiciones";
	}
	if ($action == '2') {
		$resultados = reporte_compras($con, $ruc_empresa, $desde, $hasta, $id_proveedor, '2');
		$nombre_archivo = "NC_Adquisiciones";
	}

	if (mysqli_num_rows($resultados) > 0) {
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
			->setDescription("Reporte de compras")
			->setKeywords("reporte compras")
			->setCategory("Reporte excel");

		$tituloReporte = "Reporte de adquisiciones en compras y servicios desde " . $desde . " hasta " . $hasta;
		$titulosColumnas = array('Fecha', 'Proveedor', 'Ruc', 'Documento', 'Número', 'Subtotal', 'Descuento', 'IVA', 'Propina', 'Otros', 'Total', 'Tipo Deducible', 'Sustento Tributario', 'Aut. SRI', 'Retenciones', 'Tipo', 'Código', 'Cuenta configurada', 'Cuenta modificada', 'Egreso', 'Proyecto');

		$objPHPExcel->setActiveSheetIndex(0)
			->mergeCells('A1:E1')
			->mergeCells('A2:E2');

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
			->setCellValue('U3',  $titulosColumnas[20]);
		$i = 4;

		$suma_total_retenciones = 0;
		$suma_propina = 0;
		$suma_otros_val = 0;
		$suma_descuento = 0;
		$suma_subtotal = 0;
		$suma_iva = 0;
		$suma_total_compra = 0;

		while ($row = mysqli_fetch_array($resultados)) {
			$fecha_compra = $row['fecha_compra'];
			$documento = $row['documento'];
			$nombre_proveedor = $row['proveedor'];
			$total_compra = $row['total_compra'];
			$ruc_proveedor = $row['ruc_proveedor'];
			$nombre_comprobante = $row['comprobante'];
			$subtotal = $row['subtotal'];
			$descuento = $row['descuento'];
			$iva = $row['iva'];
			$otros_val = $row['otros_val'];
			$propina = $row['propina'];
			$id_sustento = $row['id_sustento'];
			$suma_subtotal += $subtotal;
			$suma_descuento += $descuento;
			$suma_iva += $iva;
			$suma_propina += $propina;
			$suma_otros_val += $otros_val;
			$suma_total_compra += $row['total_compra'];


			//para SABER EL nombre del SUSTENTO TRIBUTARIO
			$sql_sustento = mysqli_query($con, "SELECT * FROM sustento_tributario WHERE id_sustento ='" . $id_sustento . "' ");
			$row_sustento = mysqli_fetch_array($sql_sustento);
			$nombre_sustento = $row_sustento['nombre_sustento'];

			$sql_retenciones = mysqli_query($con, "SELECT sum(cue.valor_retenido) as valor_retenido 
			FROM cuerpo_retencion as cue LEFT JOIN encabezado_retencion as enc 
			ON enc.serie_retencion=cue.serie_retencion and enc.secuencial_retencion=cue.secuencial_retencion 
			and cue.ruc_empresa=enc.ruc_empresa 
			WHERE enc.numero_comprobante='" . $row['documento'] . "' and enc.id_proveedor='" . $row['id_proveedor'] . "' 
			and mid(enc.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' group by enc.numero_comprobante");
			$row_retenciones = mysqli_fetch_array($sql_retenciones);
			$total_retenciones = $row_retenciones['valor_retenido'];
			$suma_total_retenciones += $total_retenciones;

			$sql_cuenta = mysqli_query($con, "SELECT plan.codigo_cuenta as codigo, plan.nombre_cuenta as cuenta 
			FROM asientos_programados as asi LEFT JOIN plan_cuentas as plan ON plan.id_cuenta=asi.id_cuenta 
			WHERE asi.id_pro_cli='" . $row['id_proveedor'] . "' and asi.ruc_empresa = '" . $ruc_empresa . "'");
			$row_cuenta = mysqli_fetch_array($sql_cuenta);
			$codigo_contable = $row_cuenta['codigo'];
			$cuenta_contable = $row_cuenta['cuenta'];

			$sql_cuenta_editada = mysqli_query($con, "
								SELECT DISTINCT(plan.nombre_cuenta) AS cuenta, 
									ROUND(SUM(det.debe), 2) AS valor,
									pro.nombre AS proyecto 
								FROM detalle_diario_contable AS det 
								LEFT JOIN plan_cuentas AS plan ON plan.id_cuenta = det.id_cuenta
								LEFT JOIN encabezado_diario AS enc ON enc.codigo_unico = det.codigo_unico
								LEFT JOIN proyectos AS pro ON pro.id = plan.id_proyecto
								WHERE enc.codigo_unico = 'COM" . $row['id_compra'] . "' 
								AND det.debe > 0 
								GROUP BY det.id_cuenta");
			if (!$sql_cuenta_editada) {
				die("Error en la consulta: " . mysqli_error($con));
			}

			$cuenta_editada = "";
			$proyecto = "";
			$contador = 1;

			// Verificar si hay resultados
			//if (mysqli_num_rows($sql_cuenta_editada) > 0) {
			foreach ($sql_cuenta_editada as $cuentas) {
				$cuenta_editada .= "Cuenta: " . $contador . " " . $cuentas['cuenta'] . " Valor: " . $cuentas['valor'] . "\n";
				$proyecto .= $cuentas['proyecto'] . " " . "\n";
				$contador++;
			}
			//} else {
			//	$cuenta_editada = "Cuenta no asignada.";
			//	$proyecto = "Proyecto no asignado.";
			//}

			$sql_eg = mysqli_query($con, "SELECT det.numero_ing_egr as egreso 
			FROM detalle_ingresos_egresos as det INNER JOIN ingresos_egresos as egr ON egr.codigo_documento=det.codigo_documento 
			WHERE det.codigo_documento_cv='" . $row['codigo_documento'] . "' and det.tipo_documento='EGRESO'");
			$row_eg = mysqli_fetch_array($sql_eg);
			$egreso = $row_eg['egreso'];

			//para SABER EL nombre del TIPO DEDUCIBLE
			switch ($row['deducible_en']) {
				case "01":
					$deducible = 'No asignado';
					break;
				case "02":
					$deducible = 'No asignado';
					break;
				case "03":
					$deducible = 'No asignado';
					break;
				case "04":
					$deducible = 'Deducible para impuestos';
					break;
				case "05":
					$deducible = 'No deducible o gasto personal';
					break;
				case "":
					$deducible = 'No asignado';
					break;
			}


			//$titulosColumnas = array('Tipo', 'Código', 'Cuenta', 'Egreso');
			$objPHPExcel->setActiveSheetIndex(0)
				->setCellValue('A' . $i,  date("d/m/Y", strtotime($row['fecha_compra'])))
				->setCellValue('B' . $i,  strtoupper($row['proveedor']))
				->setCellValue('C' . $i,  "=\"" . $row['ruc_proveedor'] . "\"")
				->setCellValue('D' . $i,  $nombre_comprobante)
				->setCellValue('E' . $i,  $row['documento'])
				->setCellValue('F' . $i,  $subtotal)
				->setCellValue('G' . $i,  $descuento)
				->setCellValue('H' . $i,  $iva)
				->setCellValue('I' . $i,  $propina)
				->setCellValue('J' . $i,  $otros_val)
				->setCellValue('K' . $i,  $row['total_compra'])
				->setCellValue('L' . $i,  $deducible)
				->setCellValue('M' . $i,  $nombre_sustento)
				->setCellValue('N' . $i,  "=\"" . $row['aut_sri'] . "\"")
				->setCellValue('O' . $i,  $total_retenciones)
				->setCellValue('P' . $i,  $row['tipo_comprobante'])
				->setCellValue('Q' . $i,  $codigo_contable)
				->setCellValue('R' . $i,  $cuenta_contable)
				->setCellValue('S' . $i,  rtrim($cuenta_editada, "\n"))
				->setCellValue('T' . $i,  $egreso)
				->setCellValue('U' . $i,  rtrim($proyecto, "\n"));
			$i++;
		}
		$t = $i + 1;

		$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('E' . $t,  'Totales')
			->setCellValue('F' . $t,  number_format($suma_subtotal, 2, '.', ''))
			->setCellValue('G' . $t,  number_format($suma_descuento, 2, '.', ''))
			->setCellValue('H' . $t,  number_format($suma_iva, 2, '.', ''))
			->setCellValue('I' . $t,  number_format($suma_propina, 2, '.', ''))
			->setCellValue('J' . $t,  number_format($suma_otros_val, 2, '.', ''))
			->setCellValue('K' . $t,  number_format($suma_total_compra, 2, '.', ''))
			->setCellValue('O' . $t,  number_format($suma_total_retenciones, 2, '.', ''));

		$objPHPExcel->getActiveSheet()->getStyle('F4:K' . $t)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
		//$objPHPExcel->getActiveSheet()->getStyle('F4:K' . $t)->getNumberFormat()->setFormatCode('#,##0.00');

		for ($i = 'A'; $i <= 'L'; $i++) {
			$objPHPExcel->setActiveSheetIndex(0)
				->getColumnDimension($i)->setAutoSize(TRUE);
		}

		genera_excel($objPHPExcel, $nombre_archivo, $nombre_archivo);
	} else {
		echo ('No hay resultados para mostrar');
	}
} /* else {
	echo ('Vuelva a intentarlo');
} */


if ($action == '3' || $action == '4') {
	if ($action == '3') {
		$resultados = detalle_compras($con, $ruc_empresa, $id_proveedor, $desde, $hasta, '3');
		$nombre_archivo = "Detalle_Adquisiciones";
	}

	if ($action == '4') {
		$resultados = detalle_compras($con, $ruc_empresa, $id_proveedor, $desde, $hasta, '4');
		$nombre_archivo = "Detalle_NC_Adquisiciones";
	}

	if (mysqli_num_rows($resultados) > 0) {
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
			->setDescription("Reporte de compras")
			->setKeywords("reporte compras")
			->setCategory("Reporte excel");

		$tituloReporte = "Reporte detallado. Del: " . $desde . " Al: " . $hasta;
		$titulosColumnas = array('Fecha', 'Proveedor', 'Ruc', 'Documento', 'Número', 'Código', 'Detalle', 'Tarifa', 'Cantidad', 'Valor unitario', 'Descuento', 'Subtotal', 'IVA', 'Total');

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
		$suma_valor_unitario = 0;
		while ($row = mysqli_fetch_array($resultados)) {
			$fecha_compra = $row['fecha_compra'];
			$comprobante = $row['comprobante'];
			$numero_documento = $row['documento'];
			$proveedor = strClean($row['proveedor']);
			$ruc_proveedor = $row['ruc_proveedor'];
			$cantidad = $row['cantidad'];
			$producto = strClean($row['detalle_producto']);
			$codigo = strClean($row['codigo_producto']);
			$precio = $row['precio'];
			$suma_valor_unitario += $precio;
			$descuento = $row['descuento'];
			$subtotal = $row['subtotal'];
			$tarifa_iva = $row['tarifa'];
			$total_compra = number_format(($row['subtotal'] + $row['total_iva']), 2, '.', '');

			$objPHPExcel->setActiveSheetIndex(0)
				->setCellValue('A' . $i,  date("d-m-Y", strtotime($fecha_compra)))
				->setCellValue('B' . $i,  $proveedor)
				->setCellValue('C' . $i,  "=\"" . $ruc_proveedor . "\"")
				->setCellValue('D' . $i,  $comprobante)
				->setCellValue('E' . $i,  $numero_documento)
				->setCellValue('F' . $i,  "=\"" . $codigo . "\"")
				->setCellValue('G' . $i,  "=\"" . $producto . "\"")
				->setCellValue('H' . $i,  $tarifa_iva)
				->setCellValue('I' . $i,  number_format($cantidad, 4, '.', ''))
				->setCellValue('J' . $i,  number_format($precio, 4, '.', ''))
				->setCellValue('K' . $i,  number_format($descuento, 2, '.', ''))
				->setCellValue('L' . $i,  number_format($subtotal, 2, '.', ''))
				->setCellValue('M' . $i,  number_format($row['total_iva'], 2, '.', ''))
				->setCellValue('N' . $i,  number_format($total_compra, 2, '.', ''));
			$i = $i + 1;
		} //fin del while

		//$objPHPExcel->getActiveSheet()->getStyle('I4:N' . $i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
		//$objPHPExcel->getActiveSheet()->getStyle('I4:N' . $i)->getNumberFormat()->setFormatCode('#,##0.00');

		for ($i = 'A'; $i <= 'S'; $i++) {
			$objPHPExcel->setActiveSheetIndex(0)
				->getColumnDimension($i)->setAutoSize(TRUE);
		}

		genera_excel($objPHPExcel, $nombre_archivo, $nombre_archivo);
	} else {
		print_r('No hay resultados para mostrar');
	}
} /* else {
	echo ('Vuelva a intentarlo');
} */

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
