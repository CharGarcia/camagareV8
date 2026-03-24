<?php
include("../conexiones/conectalogin.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$condicion_ruc_empresa =	"ruc_empresa = '" . $ruc_empresa . "'";
$c = mysqli_real_escape_string($con, (strip_tags($_GET['term'], ENT_QUOTES)));
$text_buscar = explode(' ', $c);
$like = "";
for ($i = 0; $i < count($text_buscar); $i++) {
	$like .= "%" . $text_buscar[$i];
}

$aColumns = array('nombre', 'ruc'); //Columnas de busqueda
$sTable = "clientes";
$sWhere = "WHERE $condicion_ruc_empresa and status = '1'";
if ($_GET['term'] != "") {
	$sWhere = "WHERE ($condicion_ruc_empresa and status = '1' AND ";

	for ($i = 0; $i < count($aColumns); $i++) {
		$sWhere .= $aColumns[$i] . " LIKE '%" . $like . "%' AND $condicion_ruc_empresa and status = '1' OR ";
	}

	$sWhere = substr_replace($sWhere, "AND $condicion_ruc_empresa and status = '1'", -3);
	$sWhere .= ')';
}
$sWhere .= " order by nombre desc";

//pagination variables
$page = 1;
$per_page = 10; //how much records you want to show
//$adjacents  = 10; //gap between pages after number of adjacents
$offset = ($page - 1) * $per_page;
$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable  $sWhere");
$row = mysqli_fetch_array($count_query);
$numrows = $row['numrows'];
//main query to fetch the data
$sql = "SELECT * FROM  $sTable $sWhere LIMIT $offset,$per_page";
$query = mysqli_query($con, $sql);
//loop through fetched data
if ($numrows > 0) {
	$arreglo_clientes = array();
	if (mysqli_num_rows($query) == 0) {
		array_push($arreglo_clientes, "No hay datos");
	} else {
		while ($palabras = mysqli_fetch_array($query)) {
			$row_array['id'] = $palabras['id'];
			$row_array['value'] = $palabras['nombre'] . " - " . $palabras['ruc'];
			$row_array['nombre'] = $palabras['nombre'];
			$row_array['tipo_id'] = $palabras['tipo_id'];
			$row_array['ruc'] = $palabras['ruc'];
			$row_array['telefono'] = $palabras['telefono'];
			$row_array['direccion'] = $palabras['direccion'];
			$row_array['plazo'] = $palabras['plazo'];
			$row_array['email'] = $palabras['email'];
			$row_array['status'] = $palabras['status'];
			$row_array['id_vendedor'] = isset($palabras['id_vendedor']) ? $palabras['id_vendedor'] : 0;
			array_push($arreglo_clientes, $row_array);
		}
	}
	echo json_encode($arreglo_clientes);
	mysqli_close($con);
}
