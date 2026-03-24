<?php
if (empty($_POST['mod_id_inventario'])) {
	$errors[] = "Seleccione una salida";
} else if (empty($_POST['mod_fecha_salida'])) {
	$errors[] = "Fecha de salida";
} else if (empty($_POST['mod_fecha_caducidad'])) {
	$errors[] = "Fecha de caducidad";
} else if (!date($_POST['mod_fecha_salida'])) {
	$errors[] = "Ingrese una fecha de salida correcta";
} else if (date('Y-m-d H:i:s', strtotime($_POST['mod_fecha_salida'])) > date("Y-m-d H:i:s")) {
	$errors[] = "La fecha de registro no puede ser mayor a la actual";
} else if (!date($_POST['mod_fecha_caducidad'])) {
	$errors[] = "Ingrese una fecha de caducidad correcta";
} else if (empty($_POST['mod_cantidad'])) {
	$errors[] = "Ingrese cantidad";
} else if (!is_numeric($_POST['mod_cantidad'])) {
	$errors[] = "La cantidad no es valor";
} else if ($_POST['mod_cantidad'] <= 0) {
	$errors[] = "La cantidad debe ser mayor a cero";
} else if (empty($_POST['mod_precio_producto'])) {
	$errors[] = "Ingrese precio";
} else if (!is_numeric($_POST['mod_precio_producto'])) {
	$errors[] = "El precio no es valor";
} else if (empty($_POST['mod_bodega'])) {
	$errors[] = "Seleccione una bodega";
} else if (empty($_POST['mod_unidad_medida'])) {
	$errors[] = "Seleccione una medida";
} else if (empty($_POST['mod_referencia'])) {
	$errors[] = "Ingrese una referencia";
} else if (
	!empty($_POST['mod_id_inventario']) &&
	!empty($_POST['mod_fecha_salida']) &&
	!empty($_POST['mod_fecha_caducidad']) &&
	!empty($_POST['mod_cantidad']) &&
	!empty($_POST['mod_precio_producto']) &&
	!empty($_POST['mod_bodega']) &&
	!empty($_POST['mod_unidad_medida']) &&
	!empty($_POST['mod_referencia'])
) {
	/* Connect To Database*/
	include("../conexiones/conectalogin.php");
	include("../ajax/saldo_producto_inventario.php");
	$con = conenta_login();
	$mod_codigo_producto = mysqli_real_escape_string($con, (strip_tags($_POST["mod_codigo_producto"], ENT_QUOTES)));
	$mod_id_inventario = mysqli_real_escape_string($con, (strip_tags($_POST["mod_id_inventario"], ENT_QUOTES)));
	$mod_id_producto = mysqli_real_escape_string($con, (strip_tags($_POST["mod_id_producto"], ENT_QUOTES)));
	$mod_fecha_salida = date('Y-m-d H:i:s', strtotime($_POST['mod_fecha_salida']));
	$mod_fecha_caducidad = date('Y-m-d H:i:s', strtotime($_POST['mod_fecha_caducidad']));
	$mod_cantidad = mysqli_real_escape_string($con, (strip_tags($_POST["mod_cantidad"], ENT_QUOTES)));
	$mod_precio_producto = mysqli_real_escape_string($con, (strip_tags($_POST["mod_precio_producto"], ENT_QUOTES)));
	$mod_bodega = mysqli_real_escape_string($con, (strip_tags($_POST["mod_bodega"], ENT_QUOTES)));
	$mod_unidad_medida = mysqli_real_escape_string($con, (strip_tags($_POST["mod_unidad_medida"], ENT_QUOTES)));
	$mod_referencia = mysqli_real_escape_string($con, (strip_tags($_POST["mod_referencia"], ENT_QUOTES)));
	session_start();
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$id_usuario = $_SESSION['id_usuario'];
	$fecha_agregado = date("Y-m-d H:i:s");
	//para ver si ya ha sido usado
	$query_cantidad_editada = mysqli_query($con, "SELECT cantidad_salida as cantidad_anterior FROM inventarios WHERE id_inventario='" . $mod_id_inventario . "'");
	$row_cantidad_editada = mysqli_fetch_array($query_cantidad_editada);
	$cantidad_anterior = $row_cantidad_editada["cantidad_anterior"];

	$saldo_producto_actual = new saldo_producto_y_conversion();
	$saldo_cantidad = $saldo_producto_actual->existencias_productos($mod_bodega, $mod_id_producto, $con);

	if (($saldo_cantidad + $cantidad_anterior) >= $mod_cantidad) {
		$sql = "UPDATE inventarios SET precio='" . $mod_precio_producto . "', cantidad_salida='" . $mod_cantidad . "',fecha_registro='" . $mod_fecha_salida . "', fecha_vencimiento='" . $mod_fecha_caducidad . "', referencia='" . $mod_referencia . "', id_usuario='" . $id_usuario . "' ,fecha_agregado='" . $fecha_agregado . "',id_bodega='" . $mod_bodega . "' WHERE id_inventario='" . $mod_id_inventario . "'";
		$query_update = mysqli_query($con, $sql);
		if ($query_update) {
			$messages[] = "La salida de inventario ha sido actualizada satisfactoriamente.";
			echo "<script>setTimeout(function () {location.reload()}, 40 * 20)</script>";
		} else {
			$errors[] = "Lo siento algo ha salido mal intenta nuevamente." . mysqli_error($con);
		}
	} else {
		$errors[] = "No es posible modificar la salida, el saldo actual es menor al ingresado." . mysqli_error($con);
	}
} else {
	$errors[] = "Error desconocido.";
}

if (isset($errors)) {

?>
	<div class="alert alert-danger" role="alert">
		<button type="button" class="close" data-dismiss="alert">&times;</button>
		<strong>Error!</strong>
		<?php
		foreach ($errors as $error) {
			echo $error;
		}
		?>
	</div>
<?php
}
if (isset($messages)) {

?>
	<div class="alert alert-success" role="alert">
		<button type="button" class="close" data-dismiss="alert">&times;</button>
		<strong>¡Bien hecho!</strong>
		<?php
		foreach ($messages as $message) {
			echo $message;
		}
		?>
	</div>
<?php
}

?>