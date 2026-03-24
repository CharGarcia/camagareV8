<?php
include("../conexiones/conectalogin.php");
include("../helpers/helpers.php");
$conexion = conenta_login();
mysqli_set_charset($conexion, "utf8");
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$consulta = mysqli_query($conexion, "SELECT pro_ser.id as id_producto, pro_ser.codigo_auxiliar as codigo_auxiliar, pro_ser.id as id, 
	pro_ser.codigo_producto, pro_ser.nombre_producto, pro_ser.precio_producto as precio_producto, 
	if(pro_ser.tipo_produccion = '01','PRODUCTO','SERVICIO') as tipo_produccion, tar_iva.tarifa as tarifa, 
	uni_med.nombre_medida as nombre_medida, mar.nombre_marca as nombre_marca, grupo.nombre_grupo as nombre_grupo
	FROM productos_servicios as pro_ser LEFT JOIN tarifa_iva as tar_iva ON tar_iva.codigo=pro_ser.tarifa_iva 
	LEFT JOIN unidad_medida as uni_med ON uni_med.id_medida=pro_ser.id_unidad_medida 
	LEFT JOIN marca_producto as mar_pro ON mar_pro.id_producto=pro_ser.id 
	LEFT JOIN marca as mar ON mar.id_marca=mar_pro.id_marca and pro_ser.id=mar_pro.id_producto
	LEFT JOIN grupo_producto_asignado as grupo_asi ON grupo_asi.id_producto=pro_ser.id
	LEFT JOIN grupo_familiar_producto as grupo ON grupo.id_grupo=grupo_asi.id_grupo and pro_ser.id=grupo_asi.id_producto 
	WHERE pro_ser.ruc_empresa='" . $ruc_empresa . "' order by pro_ser.nombre_producto asc");

if (mysqli_num_rows($consulta) > 0) {
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
		->setTitle("Productos Servicios")
		->setSubject("Productos Servicios")
		->setDescription("Productos Servicios")
		->setKeywords("Productos Servicios")
		->setCategory("Productos Servicios");

	//para sacar el nombre de la empresa
	$sql_empresa = "SELECT * FROM empresas where ruc= '" . $ruc_empresa . "'";
	$resultado_empresa = mysqli_query($conexion, $sql_empresa);
	$empresa_info = mysqli_fetch_array($resultado_empresa);
	$tituloEmpresa = $empresa_info['nombre'];
	$tituloReporte = "Listado de Productos y Servicios";
	$titulosColumnas = array('id_producto', 'Código', 'Auxiliar', 'Descripción', 'Precio', 'Tipo', 'Tarifa IVA', 'Medida', 'Marca', 'Grupo');

	$objPHPExcel->setActiveSheetIndex(0)
		->mergeCells('A1:H1')
		->mergeCells('A2:H2')
	;

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
	;
	$i = 4;

	while ($fila = mysqli_fetch_array($consulta)) {
	$objPHPExcel->setActiveSheetIndex(0)
    ->setCellValue('A' . $i,  $fila['id'])
    ->setCellValue('D' . $i,  strClean($fila['nombre_producto']))
    ->setCellValue('E' . $i,  number_format($fila['precio_producto'], 4, '.', ''))
    ->setCellValue('F' . $i,  $fila['tipo_produccion'])
    ->setCellValue('G' . $i,  $fila['tarifa'])
    ->setCellValue('H' . $i,  $fila['nombre_medida'])
    ->setCellValue('I' . $i,  $fila['nombre_marca'])
    ->setCellValue('J' . $i,  $fila['nombre_grupo']);

$objPHPExcel->getActiveSheet()->setCellValueExplicit(
    'B' . $i,
    strClean($fila['codigo_producto']),
    PHPExcel_Cell_DataType::TYPE_STRING
);

$objPHPExcel->getActiveSheet()->setCellValueExplicit(
    'C' . $i,
    strClean($fila['codigo_auxiliar']),
    PHPExcel_Cell_DataType::TYPE_STRING
);

		$i++;
	}
	$objPHPExcel->getActiveSheet()->getStyle('E4:E' . $i)->getNumberFormat()->setFormatCode('#,##0.0000');

	//$objPHPExcel->getActiveSheet()->getStyle('E4:H' . $i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
	//REFERENCIA DEL ARCHIVO DONDE ESTAN LOS FORMATOS C:\xampp\htdocs\sistema\excel\lib\PHPExcel\PHPExcel\Style\NumberFormat.php
	//for ($i = 'A'; $i <= 'C'; $i++) {
	//	$objPHPExcel->setActiveSheetIndex(0)->getColumnDimension($i)->setAutoSize(TRUE);
	//}

	$objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(10);
$objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(18);
$objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(18);
$objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(55);



	// Se asigna el nombre a la hoja
	$objPHPExcel->getActiveSheet()->setTitle('Productos Servicios');


	// Se activa la hoja para que sea la que se muestre cuando el archivo se abre
	$objPHPExcel->setActiveSheetIndex(0);
	$objPHPExcel->getActiveSheet(0)->freezePaneByColumnAndRow(0, 4);

	// Se manda el archivo al navegador web, con el nombre que se indica (Excel2007)
	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	header('Content-Disposition: attachment;filename="ProductosServicios.xlsx"');
	header('Cache-Control: max-age=0');

	$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
	$objWriter->save('php://output');
	exit;
} else {
	echo ('No hay resultados para mostrar');
}
