<?php

function periodo_contable($mes, $anio, $ruc){
include_once("../conexiones/conectalogin.php");
$conexion = conenta_login();
$mes_periodo = $mes;
$anio_periodo = $anio;
$ruc_emp = $ruc;
$sql = "SELECT * FROM periodo_contable WHERE mes_periodo = '$mes_periodo' and anio_periodo = '$anio_periodo' and ruc_empresa = '$ruc_emp'";	
$busca_periodos = mysqli_query($conexion, $sql);
$count = mysqli_num_rows($busca_periodos);
		if ($count == 0) {
			$value="periodo no disponible";
			return $value;
		}else{
			$value="periodo disponible";
			return $value;
		}
}
?>