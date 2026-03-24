<?php
ini_set('display_errors', 0); // Oculta errores al generar el Excel
error_reporting(0);
ob_start();
include("../conexiones/conectalogin.php");
date_default_timezone_set('America/Guayaquil');
//include_once("../clases/saldo_producto_y_conversion.php");
//date_default_timezone_set('America/Guayaquil');
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];

$desde = date('Y/m/d', strtotime($_POST['fecha_desde']));
$hasta = date('Y/m/d', strtotime($_POST['fecha_hasta']));
$ordenado = mysqli_real_escape_string($con, (strip_tags($_POST['ordenado'], ENT_QUOTES)));
$por = mysqli_real_escape_string($con, (strip_tags($_POST['por'], ENT_QUOTES)));
$tipo = mysqli_real_escape_string($con, (strip_tags($_POST['registro_inventario'], ENT_QUOTES)));
$producto = mysqli_real_escape_string($con, (strip_tags($_POST['id_producto'], ENT_QUOTES)));
$nombre_producto = mysqli_real_escape_string($con, (strip_tags($_POST['producto'], ENT_QUOTES)));
$marca = mysqli_real_escape_string($con, (strip_tags($_POST['id_marca'], ENT_QUOTES)));
$lote = mysqli_real_escape_string($con, (strip_tags($_POST['lote'], ENT_QUOTES)));
$caducidad = mysqli_real_escape_string($con, (strip_tags($_POST['caducidad'], ENT_QUOTES)));
$referencia = mysqli_real_escape_string($con, (strip_tags($_POST['referencia'], ENT_QUOTES)));
$bodega = mysqli_real_escape_string($con, (strip_tags($_POST['bodega'], ENT_QUOTES)));

switch ($tipo) {
	case "1": //entradas
		$data = entradas($producto, $ordenado, $por, $ruc_empresa, $con, $desde, $hasta, $marca, $lote, $caducidad, $referencia, $bodega);
		$tituloReporte = "Reporte de entradas de inventarios. Del " . $desde . " al " . $hasta;
		$titulosColumnas = array('Código', 'Producto', 'Cantidad', 'Medida', 'Marca', 'Costo', 'Lote', 'Referencia', 'Bodega', 'Fecha de registro', 'Vencimiento', 'Usuario', '', '');
		break;
	case "2": //salidas
		$data = salidas($producto, $ordenado, $por, $ruc_empresa, $con, $desde, $hasta, $marca, $lote, $caducidad, $referencia, $bodega);
		$tituloReporte = "Reporte de salidas de inventarios. Del " . $desde . " al " . $hasta;
		$titulosColumnas = array('Código', 'Producto', 'Cantidad', 'Medida', 'Marca', 'Precio', 'Lote', 'Referencia', 'Bodega', 'Fecha de registro', 'Vencimiento', 'Usuario', '', '');
		break;

	case "3": //existencia en general
		$data = existencia_general($ordenado, $por, $ruc_empresa, $con, $marca, $hasta);
		$tituloReporte = "Reporte de existencias de inventarios en general. Hasta " . $hasta;
		$titulosColumnas = array('Código', 'Producto', 'Existencia', 'Costo promedio', 'Precio de venta promedio', 'Marca', 'Medida', 'Bodega', '', '', '', '', '', '');
		break;
	case "4": //existencia caducidad
		$data = existencia_caducidad($ruc_empresa, $con, $marca);
		$tituloReporte = "Reporte de existencias de inventarios por fechas de vencimiento. Hasta " . $hasta;
		$titulosColumnas = array('Código', 'Producto', 'Existencia', 'Marca', 'Medida', 'Bodega', 'Vencimiento', '', '', '', '', '', '', '');
		break;
	case "5": //existencia lote
		$data = existencia_lote($ordenado, $por, $ruc_empresa, $con, $marca);
		$tituloReporte = "Reporte de existencias por lote. Hasta " . $hasta;
		$titulosColumnas = array('Código', 'Producto', 'Existencia', 'Marca', 'Medida', 'Bodega', 'Lote', 'Caducidad', '', '', '', '', '', '');
		break;
	case "6": //existencia consignacion
		$data = existencia_consignacion($ordenado, $por, $ruc_empresa, $con, $marca, $desde, $hasta, $bodega);
		$tituloReporte = "Reporte de existencias + consignaciones. Hasta " . $hasta;
		$titulosColumnas = array('Código', 'Producto', 'Existencia', 'Consignación', 'Saldo', 'Marca', 'Medida', 'Bodega', '', '', '', '', '', '');
		break;
	case "7": //kardex
		$data = kardex($nombre_producto, $producto, $ruc_empresa, $con, $desde, $hasta, $marca, $lote, $bodega);
		$tituloReporte = "Kardex del producto: " . $nombre_producto . " Hasta " . $hasta;
		$titulosColumnas = array('Fecha', 'Detalle', 'Entrada-Cantidad', 'Entrada-Precio', 'Entrada-Total', 'Salida-Cantidad', 'Salida-Precio', 'Salida-Total', 'Existencia-Cantidad', 'Existencia-Precio', 'Existencia-Total', '', '', '');
		break;
	case "8": //existencia consignacion lote
		$data = existencia_consignacion_lote($ordenado, $por, $ruc_empresa, $con, $marca, $hasta);
		$tituloReporte = "Reporte de existencias + consignaciones + lotes. Hasta " . $hasta;
		$titulosColumnas = array('Código', 'Producto', 'Existencia', 'Consignación', 'Saldo', 'Lote', 'Marca', 'Medida', 'Bodega', 'Caducidad', '', '', '', '');
		break;
	case "9": //costo y venta promedio
		$data = costo_venta_promedio($producto, $ruc_empresa, $con, $marca, $desde, $hasta, $bodega);
		$tituloReporte = "Costo/venta promedio desde " . $desde . " Hasta " . $hasta;
		$titulosColumnas = array('Código', 'Producto', 'Costo_promedio', 'venta_promedio', 'Marca', 'Medida', 'Bodega', '', '', '', '', '', '', '');
		break;
	case "10": //existencia en general
		$data = minimos($ordenado, $por, $ruc_empresa, $con, $marca, $bodega);
		$tituloReporte = "Reporte de mínimos ";
		$titulosColumnas = array('Código', 'Producto', 'Entrada', 'Salida', 'Existencia', 'Marca', 'Medida', 'Bodega', 'Mínimo', '', '', '', '', '');
		break;
	case "11": //existencia consignacion caducidad
		$data = existencia_consignacion_caducidad($ordenado, $por, $ruc_empresa, $con, $marca, $hasta);
		$tituloReporte = "Reporte de existencias + consignaciones + caducidad. Hasta " . $hasta;
		$titulosColumnas = array('Código', 'Producto', 'Existencia', 'Consignación', 'Saldo', 'caducidad', 'Marca', 'Medida', 'Bodega', '', '', '', '', '');
		break;
}

function costo_venta_promedio($id_producto, $ruc_empresa, $con, $marca, $desde, $hasta, $bodega)
{
	if (empty($bodega)) {
		$condicion_bodega = "";
	} else {
		$condicion_bodega = " and inv.id_bodega=" . $bodega;
	}
	if (empty($marca)) {
		$condicion_marca = "";
	} else {
		$condicion_marca = " and mar_pro.id_marca=" . $marca;
	}
	if (empty($id_producto)) {
		$condicion_producto = "";
	} else {
		$condicion_producto = "and inv.id_producto='" . $id_producto . "'";
	}

	if (empty($lote)) {
		$condicion_lote = "";
	} else {
		$condicion_lote = "and inv.lote LIKE '%" . $lote . "%' ";
	}

	if (empty($caducidad)) {
		$condicion_caducidad = "";
	} else {
		$condicion_caducidad = "and inv.fecha_vencimiento LIKE '%" . $caducidad . "%' ";
	}
	$sTable = "existencias_inventario_tmp as inv INNER JOIN unidad_medida as med ON med.id_medida=inv.id_medida INNER JOIN bodega as bod ON bod.id_bodega=inv.id_bodega LEFT JOIN marca_producto as mar_pro ON mar_pro.id_producto=inv.id_producto LEFT JOIN marca as mar ON mar.id_marca=mar_pro.id_marca";
	$sWhere = "WHERE inv.ruc_empresa ='" . $ruc_empresa . " ' $condicion_bodega $condicion_producto $condicion_marca $condicion_lote $condicion_caducidad";

	$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
	$row = mysqli_fetch_array($count_query);
	$numrows = $row['numrows'];
	//main query to fetch the data
	$sql = "SELECT inv.id_producto as id_producto, mar.nombre_marca as marca, inv.codigo_producto as codigo_producto, inv.nombre_producto as nombre_producto, inv.cantidad_entrada as cantidad_entrada, inv.cantidad_salida as cantidad_salida, 
			   inv.saldo_producto as existencia, med.nombre_medida as medida, bod.nombre_bodega as bodega FROM $sTable $sWhere";
	$query = mysqli_query($con, $sql);
	$data = array('desde' => $desde, 'hasta' => $hasta, 'con' => $con, 'query' => $query, 'numrows' => $numrows);
	return $data;
}


function kardex($nombre_producto, $producto, $ruc_empresa, $con, $desde, $hasta, $marca, $lote, $bodega)
{

	if (empty($bodega)) {
		$condicion_bodega = "";
	} else {
		$condicion_bodega = " and inv.id_bodega=" . $bodega;
	}

	if (empty($marca)) {
		$condicion_marca = "";
	} else {
		$condicion_marca = " and mar_pro.id_marca=" . $marca;
	}

	if (empty($lote)) {
		$condicion_lote = "";
	} else {
		$condicion_lote = " and inv.lote LIKE '%" . $lote . "%' ";
	}

	$sWhere = " WHERE inv.ruc_empresa ='" . $ruc_empresa . " ' and 
				DATE_FORMAT(inv.fecha_registro, '%Y/%m/%d') <= '" . $hasta . "' 
				and inv.id_producto ='" . $producto . "' $condicion_bodega $condicion_marca $condicion_lote    
				order by inv.fecha_registro desc, inv.cantidad_entrada asc";

	$sTable = "inventarios as inv INNER JOIN unidad_medida as med ON med.id_medida=inv.id_medida 
			INNER JOIN bodega as bod ON bod.id_bodega=inv.id_bodega
			LEFT JOIN marca_producto as mar_pro ON mar_pro.id_producto=inv.id_producto 
			LEFT JOIN marca as mar ON mar.id_marca=mar_pro.id_marca";

	//Count the total number of row in your table*/
	$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
	$row = mysqli_fetch_array($count_query);
	$numrows = $row['numrows'];
	//main query to fetch the data

	$sql = "SELECT inv.fecha_registro as fecha_registro, inv.referencia as referencia, round(inv.cantidad_entrada,4) as entrada,
		   round(inv.costo_unitario,4) as costo, round(inv.cantidad_entrada * inv.costo_unitario,2) as total_entrada, 
		   round(inv.cantidad_salida,4) as salida, round(inv.precio,4) as precio, round(inv.cantidad_salida * inv.precio,2) as total_salida,
		   (SELECT round(SUM(CASE WHEN operacion = 'ENTRADA' THEN cantidad_entrada ELSE -cantidad_salida END),4)
				FROM inventarios AS i2 WHERE i2.fecha_registro <= inv.fecha_registro AND i2.id_producto = inv.id_producto ) AS saldo_acumulado,
			(SELECT round(AVG(i3.precio),4)
				FROM inventarios AS i3
				WHERE i3.fecha_registro <= inv.fecha_registro AND i3.id_producto = inv.id_producto and i3.precio>0) AS precio_promedio
			FROM $sTable $sWhere ";
	$query = mysqli_query($con, $sql);
	$data = array('query' => $query, 'numrows' => $numrows, 'id_producto' => $producto);
	return $data;
}

function entradas($producto, $ordenado, $por, $ruc_empresa, $con, $desde, $hasta, $marca, $lote, $caducidad, $referencia, $bodega)
{
	if (empty($bodega)) {
		$condicion_bodega = "";
	} else {
		$condicion_bodega = " and inv.id_bodega =" . $bodega;
	}

	if (empty($producto)) {
		$condicion_producto = "";
	} else {
		$condicion_producto = " and inv.id_producto =" . $producto;
	}

	if (empty($marca)) {
		$condicion_marca = "";
	} else {
		$condicion_marca = " and mar_pro.id_marca=" . $marca;
	}

	if (empty($lote)) {
		$condicion_lote = "";
	} else {
		$condicion_lote = " and inv.lote LIKE '%" . $lote . "%' ";
	}

	if (empty($caducidad)) {
		$condicion_caducidad = "";
	} else {
		$condicion_caducidad = " and inv.fecha_vencimiento LIKE '%" . $caducidad . "%' ";
	}

	if (empty($referencia)) {
		$condicion_referencia = "";
	} else {
		$condicion_referencia = " and inv.referencia LIKE '%" . $referencia . "%' ";
	}

	$sWhere = " WHERE inv.ruc_empresa ='" . $ruc_empresa . " ' and inv.operacion='ENTRADA' 
			and DATE_FORMAT(inv.fecha_registro, '%Y/%m/%d') BETWEEN '" . $desde . "' and '" . $hasta . "' 
			and inv.cantidad_entrada > 0 $condicion_producto $condicion_bodega $condicion_marca $condicion_lote 
			$condicion_caducidad $condicion_referencia 
			order by $ordenado $por";

	$sTable = "inventarios as inv INNER JOIN unidad_medida as med ON med.id_medida=inv.id_medida INNER JOIN bodega as bod ON bod.id_bodega=inv.id_bodega INNER JOIN usuarios as usu ON usu.id=inv.id_usuario LEFT JOIN marca_producto as mar_pro ON mar_pro.id_producto=inv.id_producto LEFT JOIN marca as mar ON mar.id_marca=mar_pro.id_marca";

	$count_query = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
	$row = mysqli_fetch_array($count_query);
	$numrows = $row['numrows'];
	$sql = "SELECT mar.nombre_marca as marca, round(inv.costo_unitario, 4) as costo_unitario, inv.codigo_producto as codigo_producto, inv.nombre_producto as nombre_producto, round(inv.cantidad_entrada,4) as cantidad_entrada, med.nombre_medida as medida, inv.referencia as referencia, bod.nombre_bodega as bodega,
		   inv.fecha_registro as fecha_registro, inv.fecha_vencimiento as fecha_vencimiento, inv.lote as lote, usu.nombre as usuario
		   FROM $sTable $sWhere "; //LIMIT $offset, $per_page
	$query = mysqli_query($con, $sql);
	$data = array('query' => $query, 'numrows' => $numrows);
	return $data;
}

function salidas($producto, $ordenado, $por, $ruc_empresa, $con, $desde, $hasta, $marca, $lote, $caducidad, $referencia, $bodega)
{
	if (empty($bodega)) {
		$condicion_bodega = "";
	} else {
		$condicion_bodega = " and inv.id_bodega =" . $bodega;
	}

	if (empty($producto)) {
		$condicion_producto = "";
	} else {
		$condicion_producto = " and inv.id_producto =" . $producto;
	}

	if (empty($marca)) {
		$condicion_marca = "";
	} else {
		$condicion_marca = " and mar_pro.id_marca=" . $marca;
	}

	if (empty($lote)) {
		$condicion_lote = "";
	} else {
		$condicion_lote = " and inv.lote LIKE '%" . $lote . "%' ";
	}

	if (empty($caducidad)) {
		$condicion_caducidad = "";
	} else {
		$condicion_caducidad = " and inv.fecha_vencimiento LIKE '%" . $caducidad . "%' ";
	}

	if (empty($referencia)) {
		$condicion_referencia = "";
	} else {
		$condicion_referencia = " and inv.referencia LIKE '%" . $referencia . "%' ";
	}

	$sWhere = " WHERE inv.ruc_empresa ='" . $ruc_empresa . " ' and inv.operacion='SALIDA' and DATE_FORMAT(inv.fecha_registro, '%Y/%m/%d') BETWEEN '" . $desde . "' and '" . $hasta . "' and inv.cantidad_salida > 0 $condicion_producto $condicion_bodega $condicion_marca $condicion_lote 
			$condicion_caducidad $condicion_referencia order by $ordenado $por"; //order by $ordenado $por

	$sTable = "inventarios as inv INNER JOIN unidad_medida as med ON med.id_medida=inv.id_medida INNER JOIN bodega as bod ON bod.id_bodega=inv.id_bodega INNER JOIN usuarios as usu ON usu.id=inv.id_usuario LEFT JOIN marca_producto as mar_pro ON mar_pro.id_producto=inv.id_producto LEFT JOIN marca as mar ON mar.id_marca=mar_pro.id_marca";
	$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
	$row = mysqli_fetch_array($count_query);
	$numrows = $row['numrows'];
	$sql = "SELECT mar.nombre_marca as marca, inv.codigo_producto as codigo_producto, inv.nombre_producto as nombre_producto, round(inv.cantidad_salida, 4) as cantidad_salida, med.nombre_medida as medida, round(inv.precio,4) as precio, inv.referencia as referencia, bod.nombre_bodega as bodega,
		   inv.fecha_registro as fecha_registro, inv.fecha_vencimiento as fecha_vencimiento, inv.lote as lote, usu.nombre as usuario
		   FROM  $sTable $sWhere "; //LIMIT $offset,$per_page
	$query = mysqli_query($con, $sql);
	$data = array('query' => $query, 'numrows' => $numrows);
	return $data;
}

function existencia_general($ordenado, $por, $ruc_empresa, $con, $marca, $hasta)
{
	if (empty($marca)) {
		$condicion_marca = "";
	} else {
		$condicion_marca = " and mar_pro.id_marca=" . $marca;
	}

	$sTable = "existencias_inventario_tmp as inv INNER JOIN unidad_medida as med ON med.id_medida=inv.id_medida INNER JOIN bodega as bod ON bod.id_bodega=inv.id_bodega LEFT JOIN marca_producto as mar_pro ON mar_pro.id_producto=inv.id_producto LEFT JOIN marca as mar ON mar.id_marca=mar_pro.id_marca";
	$sWhere = "WHERE inv.ruc_empresa ='" . $ruc_empresa . " ' $condicion_marca order by $ordenado $por";
	$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
	$row = mysqli_fetch_array($count_query);
	$numrows = $row['numrows'];
	$sql = "SELECT inv.id_producto as id_producto, mar.nombre_marca as marca, inv.codigo_producto as codigo_producto, inv.nombre_producto as nombre_producto, inv.cantidad_entrada as cantidad_entrada, inv.cantidad_salida as cantidad_salida, 
			   inv.saldo_producto as existencia, med.nombre_medida as medida, bod.nombre_bodega as bodega FROM $sTable $sWhere "; //LIMIT $offset, $per_page
	$query = mysqli_query($con, $sql);
	$data = array('hasta' => $hasta, 'con' => $con, 'query' => $query, 'numrows' => $numrows);
	return $data;
}

function existencia_caducidad($ruc_empresa, $con, $marca)
{
	if (empty($marca)) {
		$condicion_marca = "";
	} else {
		$condicion_marca = " and mar_pro.id_marca=" . $marca;
	}
	$sTable = "existencias_inventario_tmp as inv INNER JOIN unidad_medida as med ON med.id_medida=inv.id_medida INNER JOIN bodega as bod ON bod.id_bodega=inv.id_bodega LEFT JOIN marca_producto as mar_pro ON mar_pro.id_producto=inv.id_producto LEFT JOIN marca as mar ON mar.id_marca=mar_pro.id_marca";
	$sWhere = "WHERE inv.ruc_empresa ='" . $ruc_empresa . " ' $condicion_marca order by inv.fecha_caducidad desc "; //$ordenado $por
	$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
	$row = mysqli_fetch_array($count_query);
	$numrows = $row['numrows'];

	$sql = "SELECT mar.nombre_marca as marca, inv.codigo_producto as codigo_producto, inv.nombre_producto as nombre_producto, inv.cantidad_entrada as cantidad_entrada, inv.cantidad_salida as cantidad_salida, 
				inv.saldo_producto as existencia, med.nombre_medida as medida, bod.nombre_bodega as bodega, inv.fecha_caducidad as vencimiento FROM $sTable $sWhere";
	$query = mysqli_query($con, $sql);
	$data = array('query' => $query, 'numrows' => $numrows);
	return $data;
}

function existencia_lote($ordenado, $por, $ruc_empresa, $con, $marca)
{
	if (empty($marca)) {
		$condicion_marca = "";
	} else {
		$condicion_marca = " and mar_pro.id_marca=" . $marca;
	}
	$sTable = "existencias_inventario_tmp as inv 
	INNER JOIN unidad_medida as med ON med.id_medida=inv.id_medida 
	INNER JOIN bodega as bod ON bod.id_bodega=inv.id_bodega 
	LEFT JOIN marca_producto as mar_pro ON mar_pro.id_producto=inv.id_producto 
	LEFT JOIN marca as mar ON mar.id_marca=mar_pro.id_marca";
	$sWhere = "WHERE inv.ruc_empresa ='" . $ruc_empresa . " ' $condicion_marca order by $ordenado $por";
	$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
	$row = mysqli_fetch_array($count_query);
	$numrows = $row['numrows'];
	$sql = "SELECT mar.nombre_marca as marca, inv.codigo_producto as codigo_producto, inv.nombre_producto as nombre_producto, 
	inv.saldo_producto as existencia, med.nombre_medida as medida, bod.nombre_bodega as bodega, inv.lote as lote, 
	inv.fecha_caducidad as caducidad FROM $sTable $sWhere";
	$query = mysqli_query($con, $sql);
	$data = array('query' => $query, 'numrows' => $numrows);
	return $data;
}

function existencia_consignacion($ordenado, $por, $ruc_empresa, $con, $marca, $hasta)
{
	if (empty($marca)) {
		$condicion_marca = "";
	} else {
		$condicion_marca = " and mar_pro.id_marca=" . $marca;
	}

	$sTable = "existencias_inventario_tmp as inv INNER JOIN unidad_medida as med ON med.id_medida=inv.id_medida INNER JOIN bodega as bod ON bod.id_bodega=inv.id_bodega LEFT JOIN marca_producto as mar_pro ON mar_pro.id_producto=inv.id_producto LEFT JOIN marca as mar ON mar.id_marca=mar_pro.id_marca";
	$sWhere = "WHERE inv.ruc_empresa ='" . $ruc_empresa . " ' $condicion_marca order by $ordenado $por";
	$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
	$row = mysqli_fetch_array($count_query);
	$numrows = $row['numrows'];
	$sql = "SELECT inv.id_producto as id_producto, mar.nombre_marca as marca, inv.codigo_producto as codigo_producto, 
	inv.nombre_producto as nombre_producto, inv.saldo_producto as existencia, med.nombre_medida as medida, 
	bod.nombre_bodega as bodega, inv.id_bodega as id_bodega FROM $sTable $sWhere ";
	$query = mysqli_query($con, $sql);
	$data = array('ruc_empresa' => $ruc_empresa, 'hasta' => $hasta, 'con' => $con, 'query' => $query, 'numrows' => $numrows);
	return $data;
}

function existencia_consignacion_lote($ordenado, $por, $ruc_empresa, $con, $marca, $hasta)
{
	if (empty($marca)) {
		$condicion_marca = "";
	} else {
		$condicion_marca = " and mar_pro.id_marca=" . $marca;
	}

	$sTable = "existencias_inventario_tmp as inv INNER JOIN unidad_medida as med ON med.id_medida=inv.id_medida INNER JOIN bodega as bod ON bod.id_bodega=inv.id_bodega LEFT JOIN marca_producto as mar_pro ON mar_pro.id_producto=inv.id_producto LEFT JOIN marca as mar ON mar.id_marca=mar_pro.id_marca";
	$sWhere = "WHERE inv.ruc_empresa ='" . $ruc_empresa . " ' $condicion_marca order by $ordenado $por";
	$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
	$row = mysqli_fetch_array($count_query);
	$numrows = $row['numrows'];
	$sql = "SELECT inv.id_producto as id_producto, mar.nombre_marca as marca, inv.codigo_producto as codigo_producto, 
	inv.nombre_producto as nombre_producto, inv.saldo_producto as existencia, med.nombre_medida as medida, 
	bod.nombre_bodega as bodega, inv.lote as lote, inv.id_bodega as id_bodega, inv.fecha_caducidad as caducidad FROM $sTable $sWhere ";
	$query = mysqli_query($con, $sql);
	$data = array('ruc_empresa' => $ruc_empresa, 'hasta' => $hasta, 'con' => $con, 'query' => $query, 'numrows' => $numrows);
	return $data;
}

function existencia_consignacion_caducidad($ordenado, $por, $ruc_empresa, $con, $marca, $hasta)
{
	if (empty($marca)) {
		$condicion_marca = "";
	} else {
		$condicion_marca = " and mar_pro.id_marca=" . $marca;
	}

	$sTable = "existencias_inventario_tmp as inv INNER JOIN unidad_medida as med ON med.id_medida=inv.id_medida INNER JOIN bodega as bod ON bod.id_bodega=inv.id_bodega LEFT JOIN marca_producto as mar_pro ON mar_pro.id_producto=inv.id_producto LEFT JOIN marca as mar ON mar.id_marca=mar_pro.id_marca";
	$sWhere = "WHERE inv.ruc_empresa ='" . $ruc_empresa . " ' $condicion_marca order by $ordenado $por";
	$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
	$row = mysqli_fetch_array($count_query);
	$numrows = $row['numrows'];
	$sql = "SELECT inv.id_producto as id_producto, mar.nombre_marca as marca, inv.codigo_producto as codigo_producto, 
	inv.nombre_producto as nombre_producto, inv.saldo_producto as existencia, med.nombre_medida as medida, 
	bod.nombre_bodega as bodega, inv.fecha_caducidad as caducidad, inv.id_bodega as id_bodega FROM $sTable $sWhere ";
	$query = mysqli_query($con, $sql);
	$data = array('ruc_empresa' => $ruc_empresa, 'hasta' => $hasta, 'con' => $con, 'query' => $query, 'numrows' => $numrows);
	return $data;
}

function minimos($ordenado, $por, $ruc_empresa, $con, $marca, $bodega)
{
	if (empty($bodega)) {
		$condicion_bodega = "";
	} else {
		$condicion_bodega = " and inv.id_bodega =" . $bodega;
	}

	if (empty($marca)) {
		$condicion_marca = "";
	} else {
		$condicion_marca = " and mar_pro.id_marca=" . $marca;
	}

	$sTable = "existencias_inventario_tmp as inv INNER JOIN unidad_medida as med ON med.id_medida=inv.id_medida INNER JOIN bodega as bod ON bod.id_bodega=inv.id_bodega LEFT JOIN marca_producto as mar_pro ON mar_pro.id_producto=inv.id_producto LEFT JOIN marca as mar ON mar.id_marca=mar_pro.id_marca";
	$sWhere = "WHERE inv.ruc_empresa ='" . $ruc_empresa . " ' $condicion_bodega $condicion_marca and inv.saldo_producto != 0 order by $ordenado $por";
	$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
	$row = mysqli_fetch_array($count_query);
	$numrows = $row['numrows'];
	$sql = "SELECT inv.id_producto as id_producto, mar.nombre_marca as marca, inv.codigo_producto as codigo_producto, inv.nombre_producto as nombre_producto, inv.cantidad_entrada as cantidad_entrada, inv.cantidad_salida as cantidad_salida, 
			   inv.saldo_producto as existencia, med.nombre_medida as medida, bod.nombre_bodega as bodega, bod.id_bodega as id_bodega FROM $sTable $sWhere "; //LIMIT $offset, $per_page
	$query = mysqli_query($con, $sql);
	$data = array('con' => $con, 'query' => $query, 'numrows' => $numrows);
	return $data;
}

if ($data['numrows'] > 0) {
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
		->setDescription("Reporte de inventarios")
		->setKeywords("reporte inventarios")
		->setCategory("Reporte excel");

	//para sacar el nombre de la empresa
	$sql_empresa = "SELECT * FROM empresas where ruc= '" . $ruc_empresa . "'";
	$resultado_empresa = mysqli_query($con, $sql_empresa);
	$empresa_info = mysqli_fetch_array($resultado_empresa);
	$tituloEmpresa = $empresa_info['nombre'];

	$objPHPExcel->setActiveSheetIndex(0)
		->mergeCells('A1:B1')
		->mergeCells('A2:B2');

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
		->setCellValue('N3',  $titulosColumnas[13]);
	$i = 4;

	switch ($tipo) {
		case "1": //entradas
			while ($row = mysqli_fetch_array($data['query'])) {
				$objPHPExcel->setActiveSheetIndex(0)
					->setCellValueExplicit('A' . $i, strtoupper($row['codigo_producto']), PHPExcel_Cell_DataType::TYPE_STRING)
					->setCellValue('B' . $i,  strtoupper($row['nombre_producto']))
					->setCellValue('C' . $i,  number_format($row['cantidad_entrada'], 4, '.', ''))
					->setCellValue('D' . $i,  strtoupper($row['medida']))
					->setCellValue('E' . $i,  strtoupper($row['marca']))
					->setCellValue('F' . $i,  number_format($row['costo_unitario'], 4, '.', ''))
					->setCellValue('G' . $i,  "=\"" . strtoupper($row['lote']) . "\"")
					->setCellValue('H' . $i,  strtoupper($row['referencia']))
					->setCellValue('I' . $i,  strtoupper($row['bodega']))
					->setCellValue('J' . $i,  date("d-m-Y", strtotime($row['fecha_registro'])))
					->setCellValue('K' . $i,  date("d-m-Y", strtotime($row['fecha_vencimiento'])))
					->setCellValue('L' . $i,  strtoupper($row['usuario']));
				$i++;
			}
			break;
		case "2": //salidas
			while ($row = mysqli_fetch_array($data['query'])) {
				$objPHPExcel->setActiveSheetIndex(0)
					->setCellValueExplicit('A' . $i, strtoupper($row['codigo_producto']), PHPExcel_Cell_DataType::TYPE_STRING)
					->setCellValue('B' . $i,  strtoupper($row['nombre_producto']))
					->setCellValue('C' . $i,  number_format($row['cantidad_salida'], 4, '.', ''))
					->setCellValue('D' . $i,  strtoupper($row['medida']))
					->setCellValue('E' . $i,  strtoupper($row['marca']))
					->setCellValue('F' . $i,  number_format($row['precio'], 4, '.', ''))
					->setCellValue('G' . $i,  "=\"" . strtoupper($row['lote']) . "\"")
					->setCellValue('H' . $i,  strtoupper($row['referencia']))
					->setCellValue('I' . $i,  strtoupper($row['bodega']))
					->setCellValue('J' . $i,  date("d-m-Y", strtotime($row['fecha_registro'])))
					->setCellValue('K' . $i,  date("d-m-Y", strtotime($row['fecha_vencimiento'])))
					->setCellValue('L' . $i,  strtoupper($row['usuario']));
				$i++;
			}
			break;
		case "3": //existencia en general
			while ($row = mysqli_fetch_array($data['query'])) {
				$id_producto = $row['id_producto'];
				$sql_costo_promedio = mysqli_query($data['con'], "SELECT avg(costo_unitario) as costo_promedio FROM inventarios WHERE id_producto = '" . $id_producto . "' and DATE_FORMAT(fecha_registro, '%Y/%m/%d') <= '" . $data['hasta'] . "' and operacion ='ENTRADA' and costo_unitario > 0 ");
				$row_costo = mysqli_fetch_array($sql_costo_promedio);

				$sql_pv_promedio = mysqli_query($data['con'], "SELECT avg(precio) as precio_promedio FROM inventarios WHERE id_producto = '" . $id_producto . "' and DATE_FORMAT(fecha_registro, '%Y/%m/%d') <= '" . $data['hasta'] . "' and operacion = 'SALIDA' and precio > 0");
				$row_precio = mysqli_fetch_array($sql_pv_promedio);

				$objPHPExcel->setActiveSheetIndex(0)
					->setCellValueExplicit('A' . $i, strtoupper($row['codigo_producto']), PHPExcel_Cell_DataType::TYPE_STRING)
					->setCellValue('B' . $i, strtoupper($row['nombre_producto']))
					->setCellValue('C' . $i, number_format($row['existencia'], 4, '.', ''))
					->setCellValue('D' . $i, number_format($row_costo['costo_promedio'], 4, '.', ''))
					->setCellValue('E' . $i, number_format($row_precio['precio_promedio'], 4, '.', ''))
					->setCellValue('F' . $i, strtoupper($row['marca']))
					->setCellValue('G' . $i, strtoupper($row['medida']))
					->setCellValue('H' . $i, strtoupper($row['bodega']));

				$i++;
			}

			break;
		case "4": //existencia caducidad
			while ($row = mysqli_fetch_array($data['query'])) {
				$objPHPExcel->setActiveSheetIndex(0)
					->setCellValueExplicit('A' . $i, strtoupper($row['codigo_producto']), PHPExcel_Cell_DataType::TYPE_STRING)
					->setCellValue('B' . $i,  strtoupper($row['nombre_producto']))
					->setCellValue('C' . $i,  number_format($row['existencia'], 4, '.', ''))
					->setCellValue('D' . $i,  strtoupper($row['marca']))
					->setCellValue('E' . $i,  strtoupper($row['medida']))
					->setCellValue('F' . $i,  strtoupper($row['bodega']))
					->setCellValue('G' . $i,   date("d-m-Y", strtotime($row['vencimiento'])));
				$i++;
			}

			break;
		case "5": //existencia lote
			while ($row = mysqli_fetch_array($data['query'])) {
				$objPHPExcel->setActiveSheetIndex(0)
					->setCellValueExplicit('A' . $i, strtoupper($row['codigo_producto']), PHPExcel_Cell_DataType::TYPE_STRING)
					->setCellValue('B' . $i,  strtoupper($row['nombre_producto']))
					->setCellValue('C' . $i,  number_format($row['existencia'], 4, '.', ''))
					->setCellValue('D' . $i,  strtoupper($row['marca']))
					->setCellValue('E' . $i,  strtoupper($row['medida']))
					->setCellValue('F' . $i,  strtoupper($row['bodega']))
					->setCellValue('G' . $i,  "=\"" . strtoupper($row['lote']) . "\"")
					->setCellValue('H' . $i, date("d-m-Y", strtotime($row['caducidad'])));
				$i++;
			}

			break;
		case "6": //existencia consignacion
			while ($row = mysqli_fetch_array($data['query'])) {
				$id_producto = $row['id_producto'];
				$id_bodega = $row['id_bodega'];

				$suma_entrada = mysqli_query($data['con'], "SELECT sum(det_con.cant_consignacion) as entradas 
						FROM detalle_consignacion as det_con INNER JOIN encabezado_consignacion as enc_con 
						ON det_con.codigo_unico=enc_con.codigo_unico 
						WHERE enc_con.ruc_empresa='" . $data['ruc_empresa'] . "' 
						and DATE_FORMAT(enc_con.fecha_consignacion, '%Y/%m/%d') <= '" . $data['hasta'] . "' 
						and det_con.id_producto='" . $id_producto . "' and det_con.id_bodega='" . $id_bodega . "' 
						and enc_con.tipo_consignacion='VENTA' and enc_con.operacion='ENTRADA' ");
				$row_entrada = mysqli_fetch_array($suma_entrada);
				$cantidad_entrada = $row_entrada['entradas'];

				$suma_devuelta = mysqli_query($data['con'], "SELECT sum(det_con.cant_consignacion) as devueltas 
						FROM detalle_consignacion as det_con INNER JOIN encabezado_consignacion as enc_con 
						ON det_con.codigo_unico=enc_con.codigo_unico 
						WHERE enc_con.ruc_empresa='" . $data['ruc_empresa'] . "' 
						and DATE_FORMAT(enc_con.fecha_consignacion, '%Y/%m/%d') <= '" . $data['hasta'] . "' 
						and det_con.id_producto='" . $id_producto . "' and det_con.id_bodega='" . $id_bodega . "' 
						and enc_con.tipo_consignacion='VENTA' and enc_con.operacion='DEVOLUCIÓN' ");
				$row_devuelta = mysqli_fetch_array($suma_devuelta);
				$cantidad_devuelta = $row_devuelta['devueltas'];

				$suma_facturada = mysqli_query($data['con'], "SELECT sum(det_con.cant_consignacion) as facturada 
						FROM detalle_consignacion as det_con INNER JOIN encabezado_consignacion as enc_con 
						ON det_con.codigo_unico=enc_con.codigo_unico 
						WHERE enc_con.ruc_empresa='" . $data['ruc_empresa'] . "' 
						and DATE_FORMAT(enc_con.fecha_consignacion, '%Y/%m/%d') <= '" . $data['hasta'] . "' 
						and det_con.id_producto='" . $id_producto . "' and det_con.id_bodega='" . $id_bodega . "' 
						and enc_con.tipo_consignacion='VENTA' and enc_con.operacion='FACTURA' ");
				$row_facturada = mysqli_fetch_array($suma_facturada);
				$cantidad_facturada = $row_facturada['facturada'];

				$saldo_consignado = $cantidad_entrada - $cantidad_devuelta - $cantidad_facturada;
				$saldo_final = $row['existencia'] + $saldo_consignado;

				$objPHPExcel->setActiveSheetIndex(0)
					->setCellValueExplicit('A' . $i, strtoupper($row['codigo_producto']), PHPExcel_Cell_DataType::TYPE_STRING)
					->setCellValue('B' . $i,  strtoupper($row['nombre_producto']))
					->setCellValue('C' . $i,  number_format($row['existencia'], 4, '.', ''))
					->setCellValue('D' . $i,  number_format($saldo_consignado, 4, '.', ''))
					->setCellValue('E' . $i,  number_format($saldo_final, 4, '.', ''))
					->setCellValue('F' . $i,  strtoupper($row['marca']))
					->setCellValue('G' . $i,  strtoupper($row['medida']))
					->setCellValue('H' . $i,  strtoupper($row['bodega']));
				$i++;
			}
			break;
		case "7": //kardex
			while ($row = mysqli_fetch_array($data['query'])) {
				$objPHPExcel->setActiveSheetIndex(0)
					->setCellValue('A' . $i,  date('d-m-Y', strtotime($row['fecha_registro'])))
					->setCellValue('B' . $i,  strtoupper($row['referencia']))
					->setCellValue('C' . $i,  number_format($row['entrada'], 4, '.', ''))
					->setCellValue('D' . $i,  number_format($row['costo'], 4, '.', ''))
					->setCellValue('E' . $i,  number_format($row['total_entrada'], 4, '.', ''))
					->setCellValue('F' . $i,  number_format($row['salida'], 4, '.', ''))
					->setCellValue('G' . $i,  number_format($row['precio'], 4, '.', ''))
					->setCellValue('H' . $i,  number_format($row['total_salida'], 4, '.', ''))
					->setCellValue('I' . $i,  number_format($row['saldo_acumulado'], 4, '.', ''))
					->setCellValue('J' . $i,  number_format($row['precio_promedio'], 4, '.', ''))
					->setCellValue('K' . $i,  number_format($row['saldo_acumulado'] * $row['precio_promedio'], 2, '.', ''));
				$i++;
			}
			break;
		case "8": //existencia consignacion lotes
			while ($row = mysqli_fetch_array($data['query'])) {
				$id_producto = $row['id_producto'];
				$id_bodega = $row['id_bodega'];
				$lote = $row['lote'];
				$suma_entrada = mysqli_query($data['con'], "SELECT sum(det_con.cant_consignacion) as entradas 
						FROM detalle_consignacion as det_con INNER JOIN encabezado_consignacion as enc_con 
						ON det_con.codigo_unico=enc_con.codigo_unico 
						WHERE enc_con.ruc_empresa='" . $data['ruc_empresa'] . "' 
						and DATE_FORMAT(enc_con.fecha_consignacion, '%Y/%m/%d') <= '" . $data['hasta'] . "' 
						and det_con.lote = '" . $lote . "' and det_con.id_producto='" . $id_producto . "' 
						and det_con.id_bodega='" . $id_bodega . "' 
						and enc_con.tipo_consignacion='VENTA' and enc_con.operacion='ENTRADA' ");
				$row_entrada = mysqli_fetch_array($suma_entrada);
				$cantidad_entrada = $row_entrada['entradas'];

				$suma_devuelta = mysqli_query($data['con'], "SELECT sum(det_con.cant_consignacion) as devueltas 
						FROM detalle_consignacion as det_con INNER JOIN encabezado_consignacion as enc_con 
						ON det_con.codigo_unico=enc_con.codigo_unico 
						WHERE enc_con.ruc_empresa='" . $data['ruc_empresa'] . "' 
						and DATE_FORMAT(enc_con.fecha_consignacion, '%Y/%m/%d') <= '" . $data['hasta'] . "' 
						and det_con.lote = '" . $lote . "' and det_con.id_producto='" . $id_producto . "' 
						and det_con.id_bodega='" . $id_bodega . "' and enc_con.tipo_consignacion='VENTA' 
						and enc_con.operacion='DEVOLUCIÓN' ");
				$row_devuelta = mysqli_fetch_array($suma_devuelta);
				$cantidad_devuelta = $row_devuelta['devueltas'];

				$suma_facturada = mysqli_query($data['con'], "SELECT sum(det_con.cant_consignacion) as facturada 
						FROM detalle_consignacion as det_con INNER JOIN encabezado_consignacion as enc_con 
						ON det_con.codigo_unico=enc_con.codigo_unico 
						WHERE enc_con.ruc_empresa='" . $data['ruc_empresa'] . "' 
						and DATE_FORMAT(enc_con.fecha_consignacion, '%Y/%m/%d') <= '" . $data['hasta'] . "' 
						and det_con.lote = '" . $lote . "' and det_con.id_producto='" . $id_producto . "' 
						and det_con.id_bodega='" . $id_bodega . "' and enc_con.tipo_consignacion='VENTA' 
						and enc_con.operacion='FACTURA' ");
				$row_facturada = mysqli_fetch_array($suma_facturada);
				$cantidad_facturada = $row_facturada['facturada'];

				$saldo_consignado = $cantidad_entrada - $cantidad_devuelta - $cantidad_facturada;
				$saldo_final = $row['existencia'] + $saldo_consignado;

				$objPHPExcel->setActiveSheetIndex(0)
					->setCellValueExplicit('A' . $i, strtoupper($row['codigo_producto']), PHPExcel_Cell_DataType::TYPE_STRING)
					->setCellValue('B' . $i,  strtoupper($row['nombre_producto']))
					->setCellValue('C' . $i,  number_format($row['existencia'], 4, '.', ''))
					->setCellValue('D' . $i,  number_format($saldo_consignado, 4, '.', ''))
					->setCellValue('E' . $i,  number_format($saldo_final, 4, '.', ''))
					->setCellValue('F' . $i,  "=\"" . strtoupper($row['lote']) . "\"")
					->setCellValue('G' . $i,  strtoupper($row['marca']))
					->setCellValue('H' . $i,  strtoupper($row['medida']))
					->setCellValue('I' . $i,  strtoupper($row['bodega']))
					->setCellValue('J' . $i,  date("d-m-Y", strtotime($row['caducidad'])));
				$i++;
			}
			break;
		case "9": //costo y venta promedio
			while ($row = mysqli_fetch_array($data['query'])) {
				$id_producto = $row['id_producto'];

				$sql_costo_promedio = mysqli_query($data['con'], "SELECT AVG(costo_unitario) as costo_promedio FROM inventarios WHERE id_producto = '" . $id_producto . "' and DATE_FORMAT(fecha_registro, '%Y/%m/%d') between '" . $data['desde'] . "' and '" . $data['hasta'] . "' and operacion ='ENTRADA' and costo_unitario >0 ");
				$row_costo_promedio = mysqli_fetch_array($sql_costo_promedio);

				$sql_venta_promedio = mysqli_query($data['con'], "SELECT AVG(precio) as precio_promedio FROM inventarios WHERE id_producto = '" . $id_producto . "' and DATE_FORMAT(fecha_registro, '%Y/%m/%d') between '" . $data['desde'] . "' and '" . $data['hasta'] . "' and operacion ='SALIDA' and precio > 0 ");
				$row_venta_promedio = mysqli_fetch_array($sql_venta_promedio);

				$objPHPExcel->setActiveSheetIndex(0)
					->setCellValue('A' . $i,  "=\"" . strtoupper($row['codigo_producto']) . "\"")
					->setCellValue('B' . $i,  strtoupper($row['nombre_producto']))
					->setCellValue('C' . $i,  number_format($row_costo_promedio['costo_promedio'], 4, '.', ''))
					->setCellValue('D' . $i,  number_format($row_venta_promedio['precio_promedio'], 4, '.', ''))
					->setCellValue('E' . $i,  strtoupper($row['marca']))
					->setCellValue('F' . $i,  strtoupper($row['medida']))
					->setCellValue('G' . $i,  strtoupper($row['bodega']));
				$i++;
			}
			break;
		case "10": //minimos
			while ($row = mysqli_fetch_array($data['query'])) {
				$id_producto = $row['id_producto'];
				$id_bodega = $row['id_bodega'];

				//minimos inventarios
				$busca_minimo = mysqli_query($data['con'], "SELECT * FROM minimos_inventarios WHERE id_producto='" . $id_producto . "' and id_bodega='" . $id_bodega . "'");
				$row_minimo = mysqli_fetch_array($busca_minimo);
				$valor_minimo = isset($row_minimo['valor_minimo']) ? $row_minimo['valor_minimo'] : 0;

				$objPHPExcel->setActiveSheetIndex(0)
					->setCellValueExplicit('A' . $i, strtoupper($row['codigo_producto']), PHPExcel_Cell_DataType::TYPE_STRING)
					->setCellValue('B' . $i,  strtoupper($row['nombre_producto']))
					->setCellValue('C' . $i,  number_format($row['cantidad_entrada'], 4, '.', ''))
					->setCellValue('D' . $i,  number_format($row['cantidad_salida'], 4, '.', ''))
					->setCellValue('E' . $i,  number_format($row['existencia'], 4, '.', ''))
					->setCellValue('F' . $i,  strtoupper($row['marca']))
					->setCellValue('G' . $i,  strtoupper($row['medida']))
					->setCellValue('H' . $i,  strtoupper($row['bodega']))
					->setCellValue('I' . $i,  $valor_minimo);
				$i++;
			}
			break;
		case "11": //existencia consignacion CADUCIDAD
			while ($row = mysqli_fetch_array($data['query'])) {
				$id_producto = $row['id_producto'];
				$id_bodega = $row['id_bodega'];
				$caducidad = $row['caducidad'];
				$suma_entrada = mysqli_query($data['con'], "SELECT sum(det_con.cant_consignacion) as entradas 
							FROM detalle_consignacion as det_con INNER JOIN encabezado_consignacion as enc_con 
							ON det_con.codigo_unico=enc_con.codigo_unico 
							WHERE enc_con.ruc_empresa='" . $data['ruc_empresa'] . "' 
							and DATE_FORMAT(enc_con.fecha_consignacion, '%Y/%m/%d') <= '" . $data['hasta'] . "' 
							and det_con.vencimiento = '" . $caducidad . "' and det_con.id_producto='" . $id_producto . "' 
							and det_con.id_bodega='" . $id_bodega . "' 
							and enc_con.tipo_consignacion='VENTA' and enc_con.operacion='ENTRADA' ");
				$row_entrada = mysqli_fetch_array($suma_entrada);
				$cantidad_entrada = $row_entrada['entradas'];

				$suma_devuelta = mysqli_query($data['con'], "SELECT sum(det_con.cant_consignacion) as devueltas 
							FROM detalle_consignacion as det_con INNER JOIN encabezado_consignacion as enc_con 
							ON det_con.codigo_unico=enc_con.codigo_unico 
							WHERE enc_con.ruc_empresa='" . $data['ruc_empresa'] . "' 
							and DATE_FORMAT(enc_con.fecha_consignacion, '%Y/%m/%d') <= '" . $data['hasta'] . "' 
							and det_con.vencimiento = '" . $caducidad . "' and det_con.id_producto='" . $id_producto . "' 
							and det_con.id_bodega='" . $id_bodega . "' and enc_con.tipo_consignacion='VENTA' 
							and enc_con.operacion='DEVOLUCIÓN' ");
				$row_devuelta = mysqli_fetch_array($suma_devuelta);
				$cantidad_devuelta = $row_devuelta['devueltas'];

				$suma_facturada = mysqli_query($data['con'], "SELECT sum(det_con.cant_consignacion) as facturada 
							FROM detalle_consignacion as det_con INNER JOIN encabezado_consignacion as enc_con 
							ON det_con.codigo_unico=enc_con.codigo_unico 
							WHERE enc_con.ruc_empresa='" . $data['ruc_empresa'] . "' 
							and DATE_FORMAT(enc_con.fecha_consignacion, '%Y/%m/%d') <= '" . $data['hasta'] . "' 
							and det_con.vencimiento = '" . $caducidad . "' and det_con.id_producto='" . $id_producto . "' 
							and det_con.id_bodega='" . $id_bodega . "' and enc_con.tipo_consignacion='VENTA' 
							and enc_con.operacion='FACTURA' ");
				$row_facturada = mysqli_fetch_array($suma_facturada);
				$cantidad_facturada = $row_facturada['facturada'];

				$saldo_consignado = $cantidad_entrada - $cantidad_devuelta - $cantidad_facturada;
				$saldo_final = $row['existencia'] + $saldo_consignado;

				$objPHPExcel->setActiveSheetIndex(0)
					->setCellValueExplicit('A' . $i, strtoupper($row['codigo_producto']), PHPExcel_Cell_DataType::TYPE_STRING)
					->setCellValue('B' . $i,  strtoupper($row['nombre_producto']))
					->setCellValue('C' . $i,  number_format($row['existencia'], 4, '.', ''))
					->setCellValue('D' . $i,  number_format($saldo_consignado, 4, '.', ''))
					->setCellValue('E' . $i,  number_format($saldo_final, 4, '.', ''))
					->setCellValue('F' . $i,  date("d-m-Y", strtotime($row['caducidad'])))
					->setCellValue('G' . $i,  strtoupper($row['marca']))
					->setCellValue('H' . $i,  strtoupper($row['medida']))
					->setCellValue('I' . $i,  strtoupper($row['bodega']));
				$i++;
			}
			break;
	}


	/* for ($i = 'A'; $i <= 'B'; $i++) {
		$objPHPExcel->setActiveSheetIndex(0)
			->getColumnDimension($i)->setAutoSize(TRUE);
	} */

	foreach (range('A', 'B') as $col) {
		$objPHPExcel->getActiveSheet()->getColumnDimension($col)->setAutoSize(true);
	}


	// Se asigna el nombre a la hoja
	$objPHPExcel->getActiveSheet()->setTitle('Inventarios');

	// Se activa la hoja para que sea la que se muestre cuando el archivo se abre
	$objPHPExcel->setActiveSheetIndex(0);
	// Inmovilizar paneles 
	//$objPHPExcel->getActiveSheet(0)->freezePane('A4');
	$objPHPExcel->getActiveSheet(0)->freezePaneByColumnAndRow(0, 4);

	// Se manda el archivo al navegador web, con el nombre que se indica (Excel2007)
	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	header('Content-Disposition: attachment;filename="ReporteInventarios.xlsx"');
	header('Cache-Control: max-age=0');
	ob_clean();
	$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
	$objWriter->save('php://output');
	exit;
} else {
	echo ('No hay resultados para mostrar, primero debe generar el reporte dando clic en el boton ver, y luego descargar el archivo excel.');
}
//}
