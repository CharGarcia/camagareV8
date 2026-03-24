<?php
require('../ajax/informes_contables.php');
require('../pdf/funciones_pdf.php');

$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];

//$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
$action = $_POST['nombre_informe'];


//para buscar la imagen
$busca_imagen = mysqli_query($con, "SELECT * FROM sucursales WHERE ruc_empresa = '" . $ruc_empresa . "' ");
$datos_imagen = mysqli_fetch_assoc($busca_imagen);
$imagen = "../logos_empresas/" . $datos_imagen['logo_sucursal'];

$busca_empresa = mysqli_query($con, "SELECT * FROM empresas WHERE ruc = '" . $ruc_empresa . "' ");
$datos_empresa = mysqli_fetch_assoc($busca_empresa);
$nombre_empresa = $datos_empresa['nombre'];
$rep_legal = $datos_empresa['nom_rep_legal'];
$nombre_contador = $datos_empresa['nombre_contador'];
$cedula_rep_legal = $datos_empresa['ced_rep_legal'];
$ruc_contador = $datos_empresa['ruc_contador'];

$desde = $_POST['fecha_desde'];
$hasta = $_POST['fecha_hasta'];
$nivel = $_POST['nivel'];
$id_proyecto = $_POST['id_proyecto'];

$sql_proyecto = mysqli_query($con, "SELECT * FROM proyectos where id= '" . $id_proyecto . "'");
$row_proyecto = mysqli_fetch_array($sql_proyecto);
$proyecto = isset($row_proyecto['nombre']) ? " PROYECTO / CENTRO DE COSTOS : " . strtoupper($row_proyecto['nombre']) : "";


$control_errores = control_errores($con, $ruc_empresa, $desde, $hasta, 'excel');
if ($nivel == '0') {
	$nivel_cuenta = "";
} else {
	$nivel_cuenta = " and nivel_cuenta = " . $nivel;
}

$pdf = new funciones_pdf('P', 'mm', 'A4'); //P
$pdf->AliasNbPages();
$imagen_optimizada = $pdf->imagen_optimizada($imagen, $width = 200, $height = 200);
imagejpeg($imagen_optimizada, '../docs_temp/' . $ruc_empresa . '.jpg');
$pdf->AddPage(); //es importante agregar esta linea para saber la pagina inicial
$pdf->SetFont('Arial', '', 9); //esta tambien es importante
$pdf->Image('../docs_temp/' . $ruc_empresa . '.jpg', 10, 5, 30, 30, 'jpg', 'www.camagare.com');

//hasta aqui los encabezados de la funcion fpdf

//1 es balance general
if ($action == '1') {
	//$generar_balance = generar_balance($con, $ruc_empresa, $id_usuario, $desde, $hasta, '1', '3');
	$resumen_activo_pasivo_patrimonio = resumen_activo_pasivo_patrimonio($con, $ruc_empresa, $id_usuario, $desde, $hasta, $id_proyecto);
	$sql_detalle_balance = mysqli_query($con, "SELECT nivel_cuenta as nivel, codigo_cuenta as codigo_cuenta, nombre_cuenta as nombre_cuenta, sum(valor) as valor FROM balances_tmp WHERE ruc_empresa = '" . $ruc_empresa . "' $nivel_cuenta group by codigo_cuenta, nivel_cuenta");
	//$sql_totales_activo_pasivo_patrimonio = mysqli_query($con, "SELECT nombre_cuenta as nombre_cuenta, sum(round(valor,2)) as valor, codigo_cuenta as codigo_cuenta  FROM balances_tmp WHERE ruc_empresa = '" . $ruc_empresa . "' and nivel_cuenta='1' group by codigo_cuenta");

	$nombre_reporte = "ESTADO DE SITUACIÓN FINANCIERA";
	$html_encabezado = '<p align="center">' . utf8_decode($nombre_empresa) . '</p><br>
				  <p align="center">' . utf8_decode($nombre_reporte) . '</p><br>
				  <p align="center">' . utf8_decode($proyecto) . '</p><br>
				  <p align="center">Del: ' . date("d-m-Y", strtotime($desde)) . ' al ' . date("d-m-Y", strtotime($hasta)) . '</p><br>';

	$pdf->detalle_html($html_encabezado);
	//$pdf->Image('../docs_temp/'.$ruc_empresa.'.jpg', 10, 10, 30, 30, 'jpg', '');
	$pdf->SetWidths(array(30, 60, 20, 20, 20, 20, 20));
	$pdf->Row_tabla(array(utf8_decode('Código'), 'Cuenta', 'Nivel 5', 'Nivel 4', 'Nivel 3', 'Nivel 2', 'Nivel 1'));
	while ($row_detalle_balance = mysqli_fetch_assoc($sql_detalle_balance)) {
		$codigo_cuenta = $row_detalle_balance['codigo_cuenta'];
		$nombre_cuenta = strtoupper($row_detalle_balance['nombre_cuenta']);
		$nivel = $row_detalle_balance['nivel'];
		$valor = $row_detalle_balance['valor'];
		if ($valor != 0) {
			if (substr($codigo_cuenta, 0, 1) == 1) {
				$valor = $valor;
			} else {
				$valor = $valor * -1;
			}

			$pdf->Row_tabla(array(
				' ' . $codigo_cuenta,
				utf8_decode($nombre_cuenta),
				$nivel == '5' ? number_format($valor, 2, '.', '') : "",
				$nivel == '4' ? number_format($valor, 2, '.', '') : "",
				$nivel == '3' ? number_format($valor, 2, '.', '') : "",
				$nivel == '2' ? number_format($valor, 2, '.', '') : "",
				$nivel == '1' ? number_format($valor, 2, '.', '') : ""
			));
		}
	}
	$pdf->Ln();

	$activo = $resumen_activo_pasivo_patrimonio['activo'];
	$pasivo = $resumen_activo_pasivo_patrimonio['pasivo'];
	$patrimonio = $resumen_activo_pasivo_patrimonio['patrimonio'];
	$pdf->SetWidths(array(90, 30));
	$pdf->Row_tabla(array(utf8_decode('TOTAL ACTIVO'), number_format($activo, 2, '.', '')));
	$pdf->Row_tabla(array(utf8_decode('TOTAL PASIVO + PATRIMONIO'), number_format($pasivo + $patrimonio, 2, '.', '')));

	$suma_pasivo_patrimonio = $pasivo + $patrimonio;
	$resultado_diferencia = $activo - $suma_pasivo_patrimonio;

	if ($activo == $suma_pasivo_patrimonio) {
		$diferencias = "";
	} else {
		$diferencias = $resultado_diferencia == 0 ? "" : "Diferencia: " . number_format(abs($resultado_diferencia), 2, '.', ',');
	}

	//para sacar la utilidad
	$resultado_utilidad = utilidad_perdida($con, $ruc_empresa, $id_usuario, $desde, $hasta, $id_proyecto);

	$pdf->Row_tabla(array(utf8_decode($resultado_utilidad['resultado']), number_format($resultado_utilidad['valor'], 2, '.', '')));
	$pdf->SetWidths(array(120));
	$pdf->Row_tabla(array($diferencias));

	if (!empty($control_errores)) {
		$pdf->Ln();
		$pdf->MultiCell(190, 5, utf8_decode($control_errores), 1, 1);
	}

	$pdf->Ln();
	$pdf->Ln();
	$pdf->Ln();
	$pdf->Ln();
	$pdf->SetWidths(array(95, 95));
	$pdf->Row_tabla(array('Gerente: ' . strtoupper($rep_legal) . ' ' . $cedula_rep_legal, 'Contador: ' . strtoupper($nombre_contador) . ' ' . $ruc_contador));
	$pdf->Output("Estado_situacion_financiera_del_" . date("d-m-Y", strtotime($desde)) . "_al_" . date("d-m-Y", strtotime($hasta)) . ".pdf", "D");

	unlink('../docs_temp/' . $ruc_empresa . '.jpg');
}

//2 es estado de resultados
if ($action == '2') {
	$generar_balance = generar_balance($con, $ruc_empresa, $id_usuario, $desde, $hasta, '4', '4', $id_proyecto);
	$sql_detalle_balance_er = mysqli_query($con, "SELECT nivel_cuenta as nivel, codigo_cuenta as codigo_cuenta, nombre_cuenta as nombre_cuenta, sum(valor) as valor  FROM balances_tmp WHERE ruc_empresa = '" . $ruc_empresa . "' $nivel_cuenta group by codigo_cuenta");

	$nombre_reporte = "ESTADO DE RESULTADOS";
	$html_encabezado = '<p align="center">' . utf8_decode($nombre_empresa) . '</p><br>
				  <p align="center">' . utf8_decode($nombre_reporte) . '</p><br>
				  <p align="center">' . utf8_decode($proyecto) . '</p><br>
				  <p align="center">Al ' . date("d-m-Y", strtotime($hasta)) . '</p><br>';


	$pdf->detalle_html($html_encabezado);
	//$pdf->Image('../docs_temp/'.$ruc_empresa.'.jpg', 10, 10, 30, 30, 'jpg', '');
	$pdf->SetWidths(array(25, 45, 20, 20, 20, 20, 20, 20));
	$pdf->Row_tabla(array(utf8_decode('Código'), 'Cuenta', 'Auxiliar', 'Nivel 5', 'Nivel 4', 'Nivel 3', 'Nivel 2', 'Nivel 1'));
	while ($row_detalle_balance = mysqli_fetch_assoc($sql_detalle_balance_er)) {
		$codigo_cuenta = $row_detalle_balance['codigo_cuenta'];
		$nombre_cuenta = strtoupper($row_detalle_balance['nombre_cuenta']);
		$nivel = $row_detalle_balance['nivel'];
		$valor = $row_detalle_balance['valor'];
		if ($valor != 0) {
			if (substr($codigo_cuenta, 0, 1) == 4) {
				$valor = $valor * -1;
			}

			$pdf->Row_tabla(array(
				' ' . $codigo_cuenta,
				utf8_decode($nombre_cuenta),
				'',
				$nivel == '5' ? number_format($valor, 2, '.', '') : "",
				$nivel == '4' ? number_format($valor, 2, '.', '') : "",
				$nivel == '3' ? number_format($valor, 2, '.', '') : "",
				$nivel == '2' ? number_format($valor, 2, '.', '') : "",
				$nivel == '1' ? number_format($valor, 2, '.', '') : ""
			));
		}
	}

	$generar_balance = generar_balance($con, $ruc_empresa, $id_usuario, $desde, $hasta, '5', '6', $id_proyecto);
	$sql_detalle_balance_er = mysqli_query($con, "SELECT nivel_cuenta as nivel, codigo_cuenta as codigo_cuenta, nombre_cuenta as nombre_cuenta, sum(valor) as valor  FROM balances_tmp WHERE ruc_empresa = '" . $ruc_empresa . "' $nivel_cuenta group by codigo_cuenta");

	while ($row_detalle_balance = mysqli_fetch_assoc($sql_detalle_balance_er)) {
		$codigo_cuenta = $row_detalle_balance['codigo_cuenta'];
		$nombre_cuenta = strtoupper($row_detalle_balance['nombre_cuenta']);
		$nivel = $row_detalle_balance['nivel'];
		$valor = $row_detalle_balance['valor'];
		if ($valor != 0) {
			if (substr($codigo_cuenta, 0, 1) == 4) {
				$valor = $valor * -1;
			}

			$pdf->Row_tabla(array(
				' ' . $codigo_cuenta,
				utf8_decode($nombre_cuenta),
				'',
				$nivel == '5' ? number_format($valor, 2, '.', '') : "",
				$nivel == '4' ? number_format($valor, 2, '.', '') : "",
				$nivel == '3' ? number_format($valor, 2, '.', '') : "",
				$nivel == '2' ? number_format($valor, 2, '.', '') : "",
				$nivel == '1' ? number_format($valor, 2, '.', '') : ""
			));
		}
	}


	$pdf->Ln();
	//para sacar la utilidad
	$resultado_utilidad = utilidad_perdida($con, $ruc_empresa, $id_usuario, $desde, $hasta, $id_proyecto);
	$pdf->SetWidths(array(90, 20));
	$pdf->Row_tabla(array(utf8_decode($resultado_utilidad['resultado']), number_format($resultado_utilidad['valor'], 2, '.', '')));
	$pdf->SetWidths(array(120));

	if (!empty($control_errores)) {
		$pdf->Ln();
		$pdf->MultiCell(190, 5, utf8_decode($control_errores), 1, 1);
	}

	$pdf->Ln();
	$pdf->Ln();
	$pdf->Ln();
	$pdf->Ln();
	$pdf->SetWidths(array(95, 95));

	$pdf->Row_tabla(array('Gerente: ' . strtoupper($rep_legal) . ' ' . $cedula_rep_legal, 'Contador: ' . strtoupper($nombre_contador) . ' ' . $ruc_contador));

	$pdf->Output("Estado_resultados_al_" . date("d-m-Y", strtotime($hasta)) . ".pdf", "D");
	unlink('../docs_temp/' . $ruc_empresa . '.jpg');
}

if ($action == '1P' || $action == '2P' || $action == '2PP' || $action == '3' || $action == 'sri' || $action == 'ESF' || $action == 'ERI') {
	echo "Informe no disponible.";
}
