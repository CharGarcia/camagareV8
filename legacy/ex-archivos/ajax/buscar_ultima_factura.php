<?php
include("../conexiones/conectalogin.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
if (isset($_POST['serie_fe'])) {
	if (empty($_POST['serie_fe'])) {
		echo 0;
	} else {
		$serie = mysqli_real_escape_string($con, (strip_tags($_POST["serie_fe"], ENT_QUOTES)));
		echo siguiente_documento($con, $ruc_empresa, $serie);
	}
}


function siguiente_documento($con, $ruc_empresa, $serie)
{
	$cuenta_facturas = mysqli_query($con, "SELECT * FROM encabezado_factura WHERE ruc_empresa = '" . $ruc_empresa . "' and serie_factura = '" . $serie . "' and tipo_factura = 'ELECTRÓNICA'");
	$count = mysqli_num_rows($cuenta_facturas);

	//la factura inicial segun se configura en el sistema camagare
	$sql_factura_inicial = mysqli_query($con, "SELECT inicial_factura FROM sucursales WHERE ruc_empresa = '" . $ruc_empresa . "' and serie = '" . $serie . "'");
	$row_inicial_factura = mysqli_fetch_array($sql_factura_inicial);
	$inicial = $row_inicial_factura['inicial_factura'];

	if ($count == 0) {
		return $inicial;
	} else {
		$sql_factura = mysqli_query($con, "SELECT MIN(secuencial_factura) as minimo, MAX(secuencial_factura) as maximo FROM encabezado_factura WHERE ruc_empresa = '" . $ruc_empresa . "' and serie_factura = '" . $serie . "' and tipo_factura = 'ELECTRÓNICA'");
		$row_factura = mysqli_fetch_assoc($sql_factura);
		$inicial = intval($inicial);
		$final = intval($row_factura['maximo']) + 1;

		if ($inicial > $final) {
			$final = $inicial + 1;
		}

		$serie_inicio_fin = array();
		foreach (range($inicial, $final) as $toda_la_serie) {
			$serie_inicio_fin[] = intval($toda_la_serie);
		}

		$facturas_registradas = mysqli_query($con, "SELECT secuencial_factura as facturas FROM encabezado_factura WHERE ruc_empresa = '" . $ruc_empresa . "' and serie_factura = '" . $serie . "' and tipo_factura = 'ELECTRÓNICA' and secuencial_factura >= '" . $inicial . "' ");

		$solo_registradas = array();
		while ($todas_las_encontradas = mysqli_fetch_array($facturas_registradas)) {
			$solo_registradas[] = intval($todas_las_encontradas['facturas']);
		}

		$facturas_faltantes = array_diff($serie_inicio_fin, $solo_registradas);
		if ($facturas_faltantes == false) {
			return $final;
		} else {
			return min($facturas_faltantes);
		}
	}
}
