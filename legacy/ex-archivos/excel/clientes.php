<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$ruc_empresa = isset($_SESSION['ruc_empresa']) ? trim($_SESSION['ruc_empresa']) : '';
if (empty($ruc_empresa)) {
    header('Content-Type: text/html; charset=utf-8');
    die('Sesión expirada. Reingrese al sistema.');
}
$conexion = getConnection();

$map_tipo = ['04'=>'RUC','05'=>'Cédula','06'=>'Pasaporte','07'=>'Consumidor final','08'=>'Identificación del exterior'];
$chk_prov = @mysqli_query($conexion, "SHOW TABLES LIKE 'provincia'");
$chk_ciud = @mysqli_query($conexion, "SHOW TABLES LIKE 'ciudad'");
$chk_ven = @mysqli_query($conexion, "SHOW TABLES LIKE 'vendedores'");
$join_prov = ($chk_prov && mysqli_num_rows($chk_prov) > 0) ? "LEFT JOIN provincia pro ON pro.codigo=cli.provincia" : "";
$join_ciud = ($chk_ciud && mysqli_num_rows($chk_ciud) > 0) ? "LEFT JOIN ciudad ciu ON ciu.codigo=cli.ciudad" : "";
$join_ven = ($chk_ven && mysqli_num_rows($chk_ven) > 0) ? "LEFT JOIN vendedores ven ON ven.id_vendedor=cli.id_vendedor" : "";
$consulta = "SELECT cli.status as status, cli.tipo_id as tipo_id, cli.ruc as ruc, cli.nombre as nombre,
	cli.telefono as telefono, cli.email as email, cli.direccion as direccion, cli.plazo as plazo,
	" . ($chk_prov && mysqli_num_rows($chk_prov) ? "pro.nombre as provincia" : "'' as provincia") . ",
	" . ($chk_ciud && mysqli_num_rows($chk_ciud) ? "ciu.nombre as ciudad" : "'' as ciudad") . ",
	" . ($chk_ven && mysqli_num_rows($chk_ven) ? "ven.nombre as vendedor" : "'' as vendedor") . "
	FROM clientes as cli $join_prov $join_ciud $join_ven
	WHERE cli.ruc_empresa='" . mysqli_real_escape_string($conexion, $ruc_empresa) . "' ORDER BY cli.nombre ASC";
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
		->setTitle("Clientes")
		->setSubject("Clientes")
		->setDescription("Clientes")
		->setKeywords("Clientes")
		->setCategory("Clientes");

	//para sacar el nombre de la empresa
	$sql_empresa = "SELECT * FROM empresas where ruc= '" . $ruc_empresa . "'";
	$resultado_empresa = mysqli_query($conexion, $sql_empresa);
	$empresa_info = mysqli_fetch_array($resultado_empresa);
	$tituloEmpresa = $empresa_info['nombre'];
	$tituloReporte = "Listado de Clientes";
	$titulosColumnas = array('Tipo', 'Identificación', 'Nombre', 'Dirección', 'Teléfono', 'Mail', 'Plazo', 'Provincia', 'Ciudad', 'Status', 'Vendedor');

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
		->setCellValue('K3',  $titulosColumnas[10]);

	//para sacar subtotales de todas las tarifa iva de cada una de las facturas, si aumentara una tarifa no importaria

	$i = 4;

	while ($fila = $resultado->fetch_array()) {
		$tipo = $map_tipo[$fila['tipo_id'] ?? ''] ?? ($fila['tipo_id'] ?? '');
		$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('A' . $i,  $tipo)
			->setCellValue('B' . $i,  "=\"" . $fila['ruc'] . "\"")
			->setCellValue('C' . $i,  strtoupper($fila['nombre']))
			->setCellValue('D' . $i,  strtoupper($fila['direccion']))
			->setCellValue('E' . $i,  "=\"" . $fila['telefono'] . "\"")
			->setCellValue('F' . $i,  $fila['email'])
			->setCellValue('G' . $i,  $fila['plazo'] . " Días")
			->setCellValue('H' . $i,  $fila['provincia'])
			->setCellValue('I' . $i,  $fila['ciudad'])
			->setCellValue('J' . $i,  $fila['status'] == 1 ? "Activo" : "Inactivo")
			->setCellValue('K' . $i,  $fila['vendedor']);
		$i++;
	}

	for ($i = 'A'; $i <= 'B'; $i++) {
		$objPHPExcel->setActiveSheetIndex(0)
			->getColumnDimension($i)->setAutoSize(TRUE);
	}

	// Se asigna el nombre a la hoja
	$objPHPExcel->getActiveSheet()->setTitle('Clientes');

	// Se activa la hoja para que sea la que se muestre cuando el archivo se abre
	$objPHPExcel->setActiveSheetIndex(0);
	$objPHPExcel->getActiveSheet(0)->freezePaneByColumnAndRow(0, 4);

	// Se manda el archivo al navegador web, con el nombre que se indica (Excel2007)
	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	header('Content-Disposition: attachment;filename="Clientes.xlsx"');
	header('Cache-Control: max-age=0');

	$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
	$objWriter->save('php://output');
	exit;
} else {
	echo ('No hay resultados para mostrar');
}
