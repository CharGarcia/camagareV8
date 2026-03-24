<?php
include("../conexiones/conectalogin.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
//$condicion_ruc_empresa =	"ruc_empresa = '" . $ruc_empresa . "'";
$c = mysqli_real_escape_string($con, (strip_tags($_GET['term'], ENT_QUOTES)));
$text_buscar = explode(' ', $c);
$like = "";
for ($i = 0; $i < count($text_buscar); $i++) {
	$like .= "%" . $text_buscar[$i];
}

$aColumns = array('cli.nombre', 'cli.ruc'); //Columnas de busqueda
$sTable = "clientes as cli INNER JOIN encabezado_factura as enc ON cli.id=enc.id_cliente";
$sWhere = "WHERE enc.ruc_empresa = '" . $ruc_empresa . "' ";
if ($_GET['term'] != "") {
	$sWhere = "WHERE (enc.ruc_empresa = '" . $ruc_empresa . "' AND ";

	for ($i = 0; $i < count($aColumns); $i++) {
		$sWhere .= $aColumns[$i] . " LIKE '%" . $like . "%' AND enc.ruc_empresa = '" . $ruc_empresa . "' OR ";
	}

	$sWhere = substr_replace($sWhere, "AND enc.ruc_empresa = '" . $ruc_empresa . "' ", -3);
	$sWhere .= ')';
}
$sWhere .= " order by cli.nombre asc";

//pagination variables
$page = 1;
$per_page = 10; //how much records you want to show
//$adjacents  = 10; //gap between pages after number of adjacents
$offset = ($page - 1) * $per_page;
$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable  $sWhere");
$row = mysqli_fetch_array($count_query);
$numrows = $row['numrows'];
//main query to fetch the data
$sql = "SELECT DISTINCT cli.id as id, cli.nombre as nombre, cli.ruc as ruc FROM $sTable $sWhere LIMIT $offset,$per_page";
$query = mysqli_query($con, $sql);
//loop through fetched data
if ($numrows > 0) {
	$arreglo_clientes = array();
	if (mysqli_num_rows($query) == 0) {
		array_push($arreglo_clientes, "No hay datos");
	} else {
		while ($palabras = mysqli_fetch_array($query)) {
			$row_array['id'] =  $palabras['id'];
			$row_array['value'] = $palabras['nombre'] . " - " . $palabras['ruc'];
			$row_array['nombre'] = $palabras['nombre'];
			array_push($arreglo_clientes, $row_array);
		}
	}
	echo json_encode($arreglo_clientes);
	mysqli_close($con);
}
