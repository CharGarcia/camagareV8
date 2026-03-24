<?php
include("../conexiones/conectalogin.php");
$conexion = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];

$consulta = "SELECT plan.codigo_cuenta as codigo_cuenta, 
plan.nombre_cuenta as nombre_cuenta, plan.nivel_cuenta as nivel_cuenta, 
plan.codigo_sri as codigo_sri, plan.codigo_supercias as codigo_supercias, proy.nombre as proyecto, plan.status as status
  FROM plan_cuentas as plan 
  LEFT JOIN proyectos as proy
ON proy.id=plan.id_proyecto WHERE mid(plan.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' order by plan.codigo_cuenta asc";
$resultado = $conexion->query($consulta);


if ($resultado->num_rows > 0) {
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
		->setTitle("Plan de cuentas")
		->setSubject("Plan de cuentas")
		->setDescription("Plan de cuentas contables")
		->setKeywords("Plan de cuentas")
		->setCategory("Reporte excel");

	//para sacar el nombre de la empresa
	$sql_empresa = "SELECT * FROM empresas where ruc= '" . $ruc_empresa . "'";
	$resultado_empresa = mysqli_query($conexion, $sql_empresa);
	$empresa_info = mysqli_fetch_array($resultado_empresa);
	$tituloEmpresa = $empresa_info['nombre'];
	$tituloReporte = "Plan de cuentas";
	$titulosColumnas = array('Código', 'Cuenta', 'Nivel', 'Código SRI', 'Código Supercias', 'Proyecto', 'Status');

	$objPHPExcel->setActiveSheetIndex(0)
		->mergeCells('A1:E1')
		->mergeCells('A2:E2')
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
	;

	//Se agregan los datos de las facturas


	//para sacar subtotales de todas las tarifa iva de cada una de las facturas, si aumentara una tarifa no importaria

	$i = 4;

	while ($fila = $resultado->fetch_array()) {
		$codigo_cuenta = $fila['codigo_cuenta'];
		$nombre_cuenta = $fila['nombre_cuenta'];
		$nivel_cuenta = $fila['nivel_cuenta'];
		$codigo_sri = $fila['codigo_sri'];
		$codigo_supercias = $fila['codigo_supercias'];
		$proyecto = $fila['proyecto'];
		$status = $fila['status'] == 1 ? "Activa" : "Inactiva";


		$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('A' . $i,  $codigo_cuenta)
			->setCellValue('B' . $i,  strtoupper($nombre_cuenta))
			->setCellValue('C' . $i,  $nivel_cuenta)
			->setCellValue('D' . $i,  $codigo_sri)
			->setCellValue('E' . $i,  $codigo_supercias)
			->setCellValue('F' . $i,  $proyecto)
			->setCellValue('G' . $i,  $status)
		;
		$i++;
	}
	$t = $i + 1;

	for ($i = 'A'; $i <= 'G'; $i++) {
		$objPHPExcel->setActiveSheetIndex(0)
			->getColumnDimension($i)->setAutoSize(TRUE);
	}

	// Se asigna el nombre a la hoja
	$objPHPExcel->getActiveSheet()->setTitle('Plan cuentas');

	// Se activa la hoja para que sea la que se muestre cuando el archivo se abre
	$objPHPExcel->setActiveSheetIndex(0);
	$objPHPExcel->getActiveSheet(0)->freezePaneByColumnAndRow(0, 4);

	// Se manda el archivo al navegador web, con el nombre que se indica (Excel2007)
	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	header('Content-Disposition: attachment;filename="PlanCuentas.xlsx"');
	header('Cache-Control: max-age=0');

	$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
	$objWriter->save('php://output');
	exit;
} else {
	echo ('No hay resultados para mostrar');
}
