<?php
require("../ajax/reporte_ventas_ejecutivo.php");
$con = conenta_login();
$ruc_empresa = $_SESSION['ruc_empresa'];
$tipo_reporte = $_POST['tipo_reporte'];
$id_marca = $_POST['id_marca'];
$id_producto = $_POST['id_producto'];
$anio = $_POST['anio'];
$id_cliente = $_POST['id_cliente'];
$cliente = !empty($_POST['cliente']) ? " Cliente: " . $_POST['cliente'] : "";

if (empty($id_producto)) {
	$condicion_producto = "";
} else {
	$condicion_producto = " and cue_fac.id_producto=" . $id_producto;
}
if (empty($id_cliente)) {
	$condicion_cliente = "";
} else {
	$condicion_cliente = " and enc_fac.id_cliente=" . $id_cliente;
}

if (empty($id_marca)) {
	$condicion_marca = "";
} else {
	$condicion_marca = " and mar_pro.id_marca=" . $id_marca;
}

if ($tipo_reporte == '1') {
	$condicion_datos = "sum(cue_fac.cantidad_factura) as cantidad";
	$nombre_reporte = " en unidades";
} else {
	$condicion_datos = "sum(cue_fac.subtotal_factura-descuento)  as cantidad";
	$nombre_reporte = " en valores";
}

$resultado_productos = datos_productos($con, $condicion_datos, $ruc_empresa, $anio, $condicion_producto, $condicion_marca, $condicion_cliente);

if (mysqli_num_rows($resultado_productos) > 0) {
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
		->setDescription("Reporte ejecutivo")
		->setKeywords("Reporte ejecutivo")
		->setCategory("Reporte excel");

	//para sacar el nombre de la empresa
	$sql_empresa = "SELECT * FROM empresas where ruc= '" . $ruc_empresa . "'";
	$resultado_empresa = mysqli_query($con, $sql_empresa);
	$empresa_info = mysqli_fetch_array($resultado_empresa);
	$tituloEmpresa = $empresa_info['nombre_comercial'];
	$tituloReporte = "Reporte ejecutivo de ventas " . $nombre_reporte . " año " . $anio . $cliente;
	$titulosColumnas = array(
		'Código', 'Producto', 'Enero', 'Promedio Enero',
		'Febrero', 'Promedio Febrero', 'Marzo', 'Promedio Marzo', 'Abril', 'Promedio Abril',
		'Mayo', 'Promedio Mayo', 'Junio', 'Promedio Junio', 'Julio', 'Promedio Julio',
		'Agosto', 'Promedio Agosto', 'Septiembre', 'Promedio Septiembre',
		'Octubre', 'Promedio Octubre', 'Noviembre', 'Promedio Noviembre',
		'Diciembre', 'Promedio Diciembre', 'Total meses', 'Promedio General'
	);
	$objPHPExcel->setActiveSheetIndex(0)
		->mergeCells('A1:K1')
		->mergeCells('A2:K2');

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
		->setCellValue('AB3',  $titulosColumnas[27]);
	$i = 4;

	$suma_ene = 0;
	$suma_feb = 0;
	$suma_mar = 0;
	$suma_abr = 0;
	$suma_may = 0;
	$suma_jun = 0;
	$suma_jul = 0;
	$suma_ago = 0;
	$suma_sep = 0;
	$suma_oct = 0;
	$suma_nov = 0;
	$suma_dic = 0;
	$suma_general = 0;
	$suma_cantidad_por_precio = 0;

	if ($tipo_reporte == '1') {
		$decimal = 0;
	} else {
		$decimal = 2;
	}


	while ($row = mysqli_fetch_array($resultado_productos)) {
		$codigo = $row['codigo_producto'];
		$producto = $row['nombre_producto'];
		$id_producto = $row['anio'];

		$resultado = resultado_fila($con, $id_producto);

		$row_result = mysqli_fetch_array($resultado);
		$suma_total = $row_result['cantidad_ene'] +
			$row_result['cantidad_feb'] +
			$row_result['cantidad_mar'] +
			$row_result['cantidad_abr'] +
			$row_result['cantidad_may'] +
			$row_result['cantidad_jun'] +
			$row_result['cantidad_jul'] +
			$row_result['cantidad_ago'] +
			$row_result['cantidad_sep'] +
			$row_result['cantidad_oct'] +
			$row_result['cantidad_nov'] +
			$row_result['cantidad_dic'];
		$suma_general += $suma_total;

		$suma_ene += $row_result['cantidad_ene'];
		$suma_feb += $row_result['cantidad_feb'];
		$suma_mar += $row_result['cantidad_mar'];
		$suma_abr += $row_result['cantidad_abr'];
		$suma_may += $row_result['cantidad_may'];
		$suma_jun += $row_result['cantidad_jun'];
		$suma_jul += $row_result['cantidad_jul'];
		$suma_ago += $row_result['cantidad_ago'];
		$suma_sep += $row_result['cantidad_sep'];
		$suma_oct += $row_result['cantidad_oct'];
		$suma_nov += $row_result['cantidad_nov'];
		$suma_dic += $row_result['cantidad_dic'];

		$array_precio_promedio = array(
			$row_result['precio_ene'],
			$row_result['precio_feb'],
			$row_result['precio_mar'],
			$row_result['precio_abr'],
			$row_result['precio_may'],
			$row_result['precio_jun'],
			$row_result['precio_jul'],
			$row_result['precio_ago'],
			$row_result['precio_sep'],
			$row_result['precio_oct'],
			$row_result['precio_nov'],
			$row_result['precio_dic']
		);

		$suma_precios_promedio = array_sum($array_precio_promedio);

		$contador = 0;
		foreach ($array_precio_promedio as $numero) {
			if ($numero > 0) {
				$contador++;
			}
		}
		if ($contador > 0) {
			$precio_promedio_fila = $suma_precios_promedio / $contador;
		} else {
			$precio_promedio_fila = 0;
		}


		$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('A' . $i,  "=\"" . $codigo . "\"")
			->setCellValue('B' . $i,  strtoupper($producto))
			->setCellValue('C' . $i,  $row_result['cantidad_ene'] > 0 ? number_format($row_result['cantidad_ene'], $decimal, '.', '') : "")
			->setCellValue('D' . $i,  $row_result['precio_ene'] > 0 ? number_format($row_result['precio_ene'], 2, '.', '') : "")
			->setCellValue('E' . $i,  $row_result['cantidad_feb'] > 0 ? number_format($row_result['cantidad_feb'], $decimal, '.', '') : "")
			->setCellValue('F' . $i,  $row_result['precio_feb'] > 0 ? number_format($row_result['precio_feb'], 2, '.', '') : "")
			->setCellValue('G' . $i,  $row_result['cantidad_mar'] > 0 ? number_format($row_result['cantidad_mar'], $decimal, '.', '') : "")
			->setCellValue('H' . $i,  $row_result['precio_mar'] > 0 ? number_format($row_result['precio_mar'], 2, '.', '') : "")
			->setCellValue('I' . $i,  $row_result['cantidad_abr'] > 0 ? number_format($row_result['cantidad_abr'], $decimal, '.', '') : "")
			->setCellValue('J' . $i,  $row_result['precio_abr'] > 0 ? number_format($row_result['precio_abr'], 2, '.', '') : "")
			->setCellValue('K' . $i,  $row_result['cantidad_may'] > 0 ? number_format($row_result['cantidad_may'], $decimal, '.', '') : "")
			->setCellValue('L' . $i,  $row_result['precio_may'] > 0 ? number_format($row_result['precio_may'], 2, '.', '') : "")
			->setCellValue('M' . $i,  $row_result['cantidad_jun'] > 0 ? number_format($row_result['cantidad_jun'], $decimal, '.', '') : "")
			->setCellValue('N' . $i,  $row_result['precio_jun'] > 0 ? number_format($row_result['precio_jun'], 2, '.', '') : "")
			->setCellValue('O' . $i,  $row_result['cantidad_jul'] > 0 ? number_format($row_result['cantidad_jul'], $decimal, '.', '') : "")
			->setCellValue('P' . $i,  $row_result['precio_jul'] > 0 ? number_format($row_result['precio_jul'], 2, '.', '') : "")
			->setCellValue('Q' . $i,  $row_result['cantidad_ago'] > 0 ? number_format($row_result['cantidad_ago'], $decimal, '.', '') : "")
			->setCellValue('R' . $i,  $row_result['precio_ago'] > 0 ? number_format($row_result['precio_ago'], 2, '.', '') : "")
			->setCellValue('S' . $i,  $row_result['cantidad_sep'] > 0 ? number_format($row_result['cantidad_sep'], $decimal, '.', '') : "")
			->setCellValue('T' . $i,  $row_result['precio_sep'] > 0 ? number_format($row_result['precio_sep'], 2, '.', '') : "")
			->setCellValue('U' . $i,  $row_result['cantidad_oct'] > 0 ? number_format($row_result['cantidad_oct'], $decimal, '.', '') : "")
			->setCellValue('V' . $i,  $row_result['precio_oct'] > 0 ? number_format($row_result['precio_oct'], 2, '.', '') : "")
			->setCellValue('W' . $i,  $row_result['cantidad_nov'] > 0 ? number_format($row_result['cantidad_nov'], $decimal, '.', '') : "")
			->setCellValue('X' . $i,  $row_result['precio_nov'] > 0 ? number_format($row_result['precio_nov'], 2, '.', '') : "")
			->setCellValue('Y' . $i,  $row_result['cantidad_dic'] > 0 ? number_format($row_result['cantidad_dic'], $decimal, '.', '') : "")
			->setCellValue('Z' . $i,  $row_result['precio_dic'] > 0 ? number_format($row_result['precio_dic'], 2, '.', '') : "")
			->setCellValue('AA' . $i,  number_format($suma_total, $decimal, '.', ''))
			->setCellValue('AB' . $i,  number_format($precio_promedio_fila, 2, '.', ''));
		$i++;
	}

	for ($i = 'A'; $i <= 'AB'; $i++) {
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
	header('Content-Disposition: attachment;filename="ReportEjecutivo.xlsx"');
	header('Cache-Control: max-age=0');

	$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
	$objWriter->save('php://output');
	exit;
} else {
	echo ('No hay resultados para mostrar');
}
