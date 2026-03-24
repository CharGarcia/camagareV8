<?php
include("../conexiones/conectalogin.php");
include_once("../clases/saldo_producto_y_conversion.php");
$saldo_producto = new saldo_producto_y_conversion();
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];

//para buscar lotes en nueva factura electronica
if (isset($_POST['id_producto']) && $_POST['opcion'] == 'lote') {
	$id_producto = mysqli_real_escape_string($con, (strip_tags($_POST["id_producto"], ENT_QUOTES)));
	$bodega = mysqli_real_escape_string($con, (strip_tags($_POST["bodega"], ENT_QUOTES)));
	//borrar las existencias temporales
	$delete_inventario_tmp = mysqli_query($con, "DELETE FROM existencias_inventario_tmp WHERE ruc_empresa = '" . $ruc_empresa . "';");
	$query_guarda_inventario_tmp = mysqli_query($con, "INSERT INTO existencias_inventario_tmp (id_existencia_tmp, id_producto, codigo_producto,nombre_producto,cantidad_entrada,cantidad_salida,id_bodega,id_medida,fecha_caducidad, ruc_empresa, saldo_producto,lote,id_usuario) 
SELECT null,id_producto, codigo_producto, nombre_producto, sum(cantidad_entrada), sum(cantidad_salida), id_bodega, id_medida, fecha_vencimiento,ruc_empresa, sum(cantidad_entrada)-sum(cantidad_salida),lote, '" . $id_usuario . "'  FROM inventarios WHERE ruc_empresa ='" . $ruc_empresa . "' and id_producto='" . $id_producto . "' and id_bodega='" . $bodega . "' group by lote");
	//selet para buscar linea por linea y ver si la medida es igual al producto o sino modificar esa linea
	$resultado = array();
	$sql_filas = mysqli_query($con, "SELECT * FROM existencias_inventario_tmp WHERE ruc_empresa = '" . $ruc_empresa . "' and id_usuario = '" . $id_usuario . "' and cantidad_salida>0 ");
	while ($row_temporales = mysqli_fetch_array($sql_filas)) {
		$id_producto = $row_temporales["id_producto"];
		$codigo_producto = $row_temporales["codigo_producto"];
		$nombre_producto = $row_temporales["nombre_producto"];
		//obtener medida del producto
		$sql_medida_producto = mysqli_query($con, "SELECT * FROM productos_servicios WHERE id = '" . $id_producto . "'");
		$row_producto = mysqli_fetch_array($sql_medida_producto);
		$id_medida_salida = $row_producto['id_unidad_medida'];
		$id_medida_entrada = $row_temporales["id_medida"];
		$cantidad_entrada_tmp = $row_temporales['cantidad_entrada'];
		$id_bodega = $row_temporales['id_bodega'];
		$caducidad = $row_temporales['fecha_caducidad'];
		$lote = $row_temporales['lote'];

		if ($id_medida_entrada != $id_medida_salida) {
			$id_tmp = $row_temporales["id_existencia_tmp"];
			$cantidad_a_transformar = $row_temporales['cantidad_salida'];
			$total_saldo_producto = $saldo_producto->conversion($id_medida_entrada, $id_medida_salida, $id_producto, '0', $cantidad_a_transformar, $con, 'saldo');
			$resultado[] = array('id_tmp' => $id_tmp, 'id_producto' => $id_producto, 'codigo_producto' => $codigo_producto, 'nombre_producto' => $nombre_producto, 'entrada' => $cantidad_entrada_tmp, 'salida' => $cantidad_a_transformar, 'id_bodega' => $id_bodega, 'id_medida' => $id_medida_salida, 'caducidad' => $caducidad, 'saldo_convertido' => $total_saldo_producto, 'lote' => $lote);
		}
	}
	foreach ($resultado as $valor) {
		$delete_tmp = mysqli_query($con, "DELETE FROM existencias_inventario_tmp WHERE id_existencia_tmp='" . $valor['id_tmp'] . "';");
		$sql_actualizar = mysqli_query($con, "INSERT INTO existencias_inventario_tmp VALUES (null,'" . $valor['id_producto'] . "','" . $valor['codigo_producto'] . "','" . $valor['nombre_producto'] . "','" . $valor['entrada'] . "','" . $valor['saldo_convertido'] . "','" . $valor['id_bodega'] . "','" . $valor['id_medida'] . "','" . $valor['caducidad'] . "', '" . $ruc_empresa . "', '" . $valor['saldo_convertido'] . "','" . $valor['lote'] . "','" . $id_usuario . "' )");
	}
	//todos los id temporales traidos para luego borrarlos
	$ides = array();
	$sql_filas_borrar = mysqli_query($con, "SELECT * FROM existencias_inventario_tmp WHERE ruc_empresa = '" . $ruc_empresa . "' and id_usuario = '" . $id_usuario . "'");
	while ($row_ides_temporales = mysqli_fetch_array($sql_filas_borrar)) {
		$id_temp_iniciales = $row_ides_temporales["id_existencia_tmp"];
		$ides[] = array('id_tmp_iniciales' => $id_temp_iniciales);
	}
	$query_actualiza_inventario_tmp = mysqli_query($con, "INSERT INTO existencias_inventario_tmp (id_existencia_tmp, id_producto, codigo_producto,nombre_producto,cantidad_entrada,cantidad_salida,id_bodega,id_medida,fecha_caducidad, ruc_empresa, saldo_producto,lote,id_usuario) 
SELECT null,id_producto, codigo_producto, nombre_producto, sum(cantidad_entrada), sum(cantidad_salida), id_bodega, id_medida, fecha_caducidad, ruc_empresa, sum(cantidad_entrada)-sum(cantidad_salida),lote, '" . $id_usuario . "'  FROM existencias_inventario_tmp WHERE ruc_empresa ='" . $ruc_empresa . "' group by lote");
	//eliminar los ides tmp iniciales
	foreach ($ides as $id_tm) {
		$delete_ides_tmp_iniciales = mysqli_query($con, "DELETE FROM existencias_inventario_tmp WHERE id_existencia_tmp='" . $id_tm['id_tmp_iniciales'] . "';");
	}

	$sql_filas = mysqli_query($con, "SELECT * FROM existencias_inventario_tmp WHERE ruc_empresa = '" . $ruc_empresa . "' and id_usuario = '" . $id_usuario . "' and saldo_producto>0 order by year(fecha_caducidad) asc");
?>
	<option value="0" selected>Seleccione</option>
	<?php
	while ($row_lotes = mysqli_fetch_array($sql_filas)) {
	?>
		<option value="<?php echo $row_lotes['lote']; ?>"><?php echo $row_lotes['lote'] . " vence:" . date('m-Y', strtotime($row_lotes['fecha_caducidad'])); ?></option>
	<?php
	}
}

//para buscar caducidades en nueva factura electronica
if (isset($_POST['id_producto']) && $_POST['opcion'] == 'caducidad') {
	$id_producto = mysqli_real_escape_string($con, (strip_tags($_POST["id_producto"], ENT_QUOTES)));
	$bodega = mysqli_real_escape_string($con, (strip_tags($_POST["bodega"], ENT_QUOTES)));
	//borrar las existencias temporales
	$delete_inventario_tmp = mysqli_query($con, "DELETE FROM existencias_inventario_tmp WHERE ruc_empresa = '" . $ruc_empresa . "';");
	$query_guarda_inventario_tmp = mysqli_query($con, "INSERT INTO existencias_inventario_tmp (id_existencia_tmp, id_producto, codigo_producto,nombre_producto,cantidad_entrada,cantidad_salida,id_bodega,id_medida,fecha_caducidad, ruc_empresa, saldo_producto,lote,id_usuario) 
SELECT null,id_producto, codigo_producto, nombre_producto, sum(cantidad_entrada), sum(cantidad_salida), id_bodega, id_medida, fecha_vencimiento,ruc_empresa, sum(cantidad_entrada)-sum(cantidad_salida),lote, '" . $id_usuario . "'  FROM inventarios WHERE ruc_empresa ='" . $ruc_empresa . "' and id_producto='" . $id_producto . "' and id_bodega='" . $bodega . "' group by fecha_vencimiento");
	//selet para buscar linea por linea y ver si la medida es igual al producto o sino modificar esa linea
	$resultado = array();
	$sql_filas = mysqli_query($con, "SELECT * FROM existencias_inventario_tmp WHERE ruc_empresa = '" . $ruc_empresa . "' and id_usuario = '" . $id_usuario . "' and cantidad_salida>0 ");
	while ($row_temporales = mysqli_fetch_array($sql_filas)) {
		$id_producto = $row_temporales["id_producto"];
		$codigo_producto = $row_temporales["codigo_producto"];
		$nombre_producto = $row_temporales["nombre_producto"];
		//obtener medida del producto
		$sql_medida_producto = mysqli_query($con, "SELECT * FROM productos_servicios WHERE id = '" . $id_producto . "'");
		$row_producto = mysqli_fetch_array($sql_medida_producto);
		$id_medida_salida = $row_producto['id_unidad_medida'];
		$id_medida_entrada = $row_temporales["id_medida"];
		$cantidad_entrada_tmp = $row_temporales['cantidad_entrada'];
		$id_bodega = $row_temporales['id_bodega'];
		$caducidad = $row_temporales['fecha_caducidad'];
		$lote = $row_temporales['lote'];

		if ($id_medida_entrada != $id_medida_salida) {
			$id_tmp = $row_temporales["id_existencia_tmp"];
			$cantidad_a_transformar = $row_temporales['cantidad_salida'];
			$total_saldo_producto = $saldo_producto->conversion($id_medida_entrada, $id_medida_salida, $id_producto, '0', $cantidad_a_transformar, $con, 'saldo');
			$resultado[] = array('id_tmp' => $id_tmp, 'id_producto' => $id_producto, 'codigo_producto' => $codigo_producto, 'nombre_producto' => $nombre_producto, 'entrada' => $cantidad_entrada_tmp, 'salida' => $cantidad_a_transformar, 'id_bodega' => $id_bodega, 'id_medida' => $id_medida_salida, 'caducidad' => $caducidad, 'saldo_convertido' => $total_saldo_producto, 'lote' => $lote);
		}
	}
	foreach ($resultado as $valor) {
		$delete_tmp = mysqli_query($con, "DELETE FROM existencias_inventario_tmp WHERE id_existencia_tmp='" . $valor['id_tmp'] . "';");
		$sql_actualizar = mysqli_query($con, "INSERT INTO existencias_inventario_tmp VALUES (null,'" . $valor['id_producto'] . "','" . $valor['codigo_producto'] . "','" . $valor['nombre_producto'] . "','" . $valor['entrada'] . "','" . $valor['saldo_convertido'] . "','" . $valor['id_bodega'] . "','" . $valor['id_medida'] . "','" . $valor['caducidad'] . "', '" . $ruc_empresa . "', '" . $valor['saldo_convertido'] . "','" . $valor['lote'] . "','" . $id_usuario . "' )");
	}
	//todos los id temporales traidos para luego borrarlos
	$ides = array();
	$sql_filas_borrar = mysqli_query($con, "SELECT * FROM existencias_inventario_tmp WHERE ruc_empresa = '" . $ruc_empresa . "' and id_usuario = '" . $id_usuario . "'");
	while ($row_ides_temporales = mysqli_fetch_array($sql_filas_borrar)) {
		$id_temp_iniciales = $row_ides_temporales["id_existencia_tmp"];
		$ides[] = array('id_tmp_iniciales' => $id_temp_iniciales);
	}
	$query_actualiza_inventario_tmp = mysqli_query($con, "INSERT INTO existencias_inventario_tmp (id_existencia_tmp, id_producto, codigo_producto,nombre_producto,cantidad_entrada,cantidad_salida,id_bodega,id_medida,fecha_caducidad, ruc_empresa, saldo_producto,lote,id_usuario) 
SELECT null,id_producto, codigo_producto, nombre_producto, sum(cantidad_entrada), sum(cantidad_salida), id_bodega, id_medida, fecha_caducidad, ruc_empresa, sum(cantidad_entrada)-sum(cantidad_salida),lote, '" . $id_usuario . "'  FROM existencias_inventario_tmp WHERE ruc_empresa ='" . $ruc_empresa . "' group by fecha_caducidad");
	//eliminar los ides tmp iniciales
	foreach ($ides as $id_tm) {
		$delete_ides_tmp_iniciales = mysqli_query($con, "DELETE FROM existencias_inventario_tmp WHERE id_existencia_tmp='" . $id_tm['id_tmp_iniciales'] . "';");
	}

	$sql_filas = mysqli_query($con, "SELECT * FROM existencias_inventario_tmp WHERE ruc_empresa = '" . $ruc_empresa . "' and id_usuario = '" . $id_usuario . "' and saldo_producto>0");
	?>
	<option value="0" selected>Seleccione</option>
	<?php
	while ($row_caducidad = mysqli_fetch_array($sql_filas)) {
	?>
		<option value="<?php echo $row_caducidad['fecha_caducidad']; ?>"><?php echo date('d-m-Y', strtotime($row_caducidad['fecha_caducidad'])); ?></option>
	<?php
	}
}

//para buscar precios
if (isset($_POST['id_producto']) && $_POST['opcion'] == 'precios') {
	$id_producto = mysqli_real_escape_string($con, (strip_tags($_POST["id_producto"], ENT_QUOTES)));
	$fecha_hoy = date('Y-m-d');
	$sql_filas = mysqli_query($con, "SELECT * FROM precios_productos WHERE id_producto='" . $id_producto . "' order by id_precio desc");

	$sql_precio_normal = mysqli_query($con, "SELECT * FROM productos_servicios WHERE id='" . $id_producto . "'");
	$row_precio_normal = mysqli_fetch_array($sql_precio_normal);
	$precio_normal = $row_precio_normal['precio_producto'];

	?>
	<option value="<?php echo $precio_normal; ?>"><?php echo $precio_normal . " Normal"; ?></option>
	<?php

	while ($row_precios = mysqli_fetch_array($sql_filas)) {
		$fecha_desde = date('Y-m-d', strtotime($row_precios['fecha_desde']));
		$fecha_hasta = date('Y-m-d', strtotime($row_precios['fecha_hasta']));
		if ($fecha_hoy >= $fecha_desde && $fecha_hoy <= $fecha_hasta) {
	?>
			<option value="<?php echo $row_precios['precio']; ?>"><?php echo number_format($row_precios['precio'], 4, '.', '') . " " . $row_precios['detalle_precio']; ?></option>
<?php
		}
	}
}

?>