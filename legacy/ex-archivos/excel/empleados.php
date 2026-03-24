<?php
include("../conexiones/conectalogin.php");
$conexion = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_empresa = $_SESSION['id_empresa'];

$consulta = "SELECT * FROM empleados WHERE id_empresa='" . $id_empresa . "' ORDER BY nombres_apellidos ASC";
$resultado = $conexion->query($consulta);

if ($resultado->num_rows > 0) {
	date_default_timezone_set('America/Guayaquil');
	if (PHP_SAPI == 'cli') {
		die('Este archivo solo se puede ver desde un navegador web');
	}

	require_once 'lib/PHPExcel/PHPExcel.php';

	// Crear objeto PHPExcel
	$objPHPExcel = new PHPExcel();

	// Propiedades del archivo
	$objPHPExcel->getProperties()->setCreator("CaMaGaRe")
		->setLastModifiedBy("CaMaGaRe")
		->setTitle("Empleados")
		->setSubject("Empleados")
		->setDescription("Listado de Empleados")
		->setKeywords("Empleados")
		->setCategory("Empleados");

	// Nombre de la empresa
	$sql_empresa = "SELECT * FROM empresas WHERE ruc = '" . $ruc_empresa . "'";
	$resultado_empresa = mysqli_query($conexion, $sql_empresa);
	$empresa_info = mysqli_fetch_array($resultado_empresa);
	$tituloEmpresa = $empresa_info['nombre'];
	$tituloReporte = "Listado de Empleados";

	// Encabezados
	$titulosColumnas = [
		'Documento',
		'Nombres y Apellidos',
		'Dirección',
		'Email',
		'Teléfono',
		'Sexo',
		'Fecha Nacimiento',
		'Status',
		'Banco',
		'Tipo Cuenta',
		'Número Cuenta'
	];

	// Títulos principales
	$objPHPExcel->setActiveSheetIndex(0)
		->mergeCells('A1:H1')
		->mergeCells('A2:H2');

	$objPHPExcel->setActiveSheetIndex(0)
		->setCellValue('A1', $tituloEmpresa)
		->setCellValue('A2', $tituloReporte);

	// Columnas
	$col = 'A';
	foreach ($titulosColumnas as $titulo) {
		$objPHPExcel->setActiveSheetIndex(0)->setCellValue($col . '3', $titulo);
		$col++;
	}

	// Cargar bancos
	$bancos = [];
	$sql_bancos = mysqli_query($conexion, "SELECT id_bancos, nombre_banco FROM bancos_ecuador");
	while ($row_banco = mysqli_fetch_assoc($sql_bancos)) {
		$bancos[$row_banco['id_bancos']] = $row_banco['nombre_banco'];
	}

	// Datos
	$i = 4;
	while ($fila = $resultado->fetch_assoc()) {
		// Sexo
		$sexo_texto = $fila['sexo'] == 1 ? 'Masculino' : 'Femenino';

		// Status
		$status_texto = $fila['status'] == 1 ? 'Activo' : 'Inactivo';

		// Banco
		$banco_nombre = isset($bancos[$fila['id_banco']]) ? $bancos[$fila['id_banco']] : 'NO REGISTRADO';

		// Tipo cuenta
		$tipos_cta = [
			1 => 'Ahorros',
			2 => 'Corriente',
			3 => 'Virtual'
		];
		$tipo_cta_texto = isset($tipos_cta[$fila['tipo_cta']]) ? $tipos_cta[$fila['tipo_cta']] : 'NO DEFINIDO';

		$objPHPExcel->setActiveSheetIndex(0)
			->setCellValueExplicit('A' . $i, $fila['documento'], PHPExcel_Cell_DataType::TYPE_STRING)
			->setCellValue('B' . $i, strtoupper($fila['nombres_apellidos']))
			->setCellValue('C' . $i, strtoupper($fila['direccion']))
			->setCellValue('D' . $i, $fila['email'])
			->setCellValueExplicit('E' . $i, $fila['telefono'], PHPExcel_Cell_DataType::TYPE_STRING)
			->setCellValue('F' . $i, $sexo_texto)
			->setCellValue('G' . $i, $fila['fecha_nacimiento'])
			->setCellValue('H' . $i, $status_texto)
			->setCellValue('I' . $i, $banco_nombre)
			->setCellValue('J' . $i, $tipo_cta_texto)
			->setCellValueExplicit('K' . $i, $fila['numero_cta'], PHPExcel_Cell_DataType::TYPE_STRING);
		$i++;
	}

	// Autosize
	foreach (range('A', 'K') as $columnID) {
		$objPHPExcel->getActiveSheet()->getColumnDimension($columnID)->setAutoSize(true);
	}

	// Título hoja
	$objPHPExcel->getActiveSheet()->setTitle('Empleados');

	// Freeze panes
	$objPHPExcel->getActiveSheet(0)->freezePaneByColumnAndRow(0, 4);

	// Salida al navegador
	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	header('Content-Disposition: attachment;filename="Empleados.xlsx"');
	header('Cache-Control: max-age=0');

	$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
	$objWriter->save('php://output');
	exit;
} else {
	echo 'No hay resultados para mostrar';
}
