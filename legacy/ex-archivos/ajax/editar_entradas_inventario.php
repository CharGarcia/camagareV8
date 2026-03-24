<?php
if (empty($_POST['mod_id_inventario'])) {
	$errors[] = "Seleccione una entrada";
} else if (empty($_POST['mod_fecha_entrada'])) {
	$errors[] = "Fecha de entrada";
} else if (!date($_POST['mod_fecha_entrada'])) {
	$errors[] = "Ingrese una fecha de entrada correcta";
} else if (date('Y-m-d H:i:s', strtotime($_POST['mod_fecha_entrada'])) > date("Y-m-d H:i:s")) {
	$errors[] = "La fecha de registro no puede ser mayor a la actual";
} else if (empty($_POST['mod_fecha_caducidad'])) {
	$errors[] = "Ingrese fecha de caducidad correcta";
} else if (!date($_POST['mod_fecha_caducidad'])) {
	$errors[] = "Ingrese una fecha de caducidad correcta";
} else if (empty($_POST['mod_cantidad'])) {
	$errors[] = "Ingrese cantidad";
} else if (!is_numeric($_POST['mod_cantidad'])) {
	$errors[] = "La cantidad no es valor";
} else if ($_POST['mod_cantidad'] <= 0) {
	$errors[] = "La cantidad debe ser mayor a cero";
} else if (empty($_POST['mod_costo_producto'])) {
	$errors[] = "Ingrese costo unitario";
} else if (!is_numeric($_POST['mod_costo_producto'])) {
	$errors[] = "El costo unitario no es valor";
} else if ($_POST['mod_costo_producto'] < 0) {
	$errors[] = "El costo del producto no puede ser menor a cero.";
} else if (empty($_POST['mod_bodega'])) {
	$errors[] = "Seleccione una bodega";
} else if (empty($_POST['mod_referencia'])) {
	$errors[] = "Ingrese una referencia";
} else if (
	!empty($_POST['mod_id_inventario']) &&
	!empty($_POST['mod_fecha_entrada']) &&
	!empty($_POST['mod_fecha_caducidad']) &&
	!empty($_POST['mod_cantidad']) &&
	!empty($_POST['mod_costo_producto']) &&
	!empty($_POST['mod_bodega']) &&
	!empty($_POST['mod_referencia'])
) {
	/* Connect To Database*/
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
	$mod_id_inventario = mysqli_real_escape_string($con, (strip_tags($_POST["mod_id_inventario"], ENT_QUOTES)));
	$mod_fecha_entrada = date('Y-m-d H:i:s', strtotime($_POST['mod_fecha_entrada']));
	$mod_fecha_caducidad = date('Y-m-d H:i:s', strtotime($_POST['mod_fecha_caducidad']));
	$mod_cantidad = mysqli_real_escape_string($con, (strip_tags($_POST["mod_cantidad"], ENT_QUOTES)));
	$mod_precio_producto = '0';
	$mod_costo_producto = mysqli_real_escape_string($con, (strip_tags($_POST["mod_costo_producto"], ENT_QUOTES)));
	$mod_bodega = mysqli_real_escape_string($con, (strip_tags($_POST["mod_bodega"], ENT_QUOTES)));
	$mod_lote = mysqli_real_escape_string($con, (strip_tags($_POST["mod_lote"], ENT_QUOTES)));
	$mod_referencia = mysqli_real_escape_string($con, (strip_tags($_POST["mod_referencia"], ENT_QUOTES)));
	session_start();
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$id_usuario = $_SESSION['id_usuario'];
	$fecha_agregado = date("Y-m-d H:i:s");
	//para ver si ya ha sido usado ese producto
	$query_saldo = mysqli_query($con, "SELECT sum(cantidad_entrada-cantidad_salida) as cantidad_total, cantidad_entrada as cantidad_anterior  FROM  inventarios WHERE id_inventario='" . $mod_id_inventario . "'");
	$row_saldo = mysqli_fetch_array($query_saldo);
	$saldo_cantidad = $row_saldo["cantidad_total"];
	$cantidad_anterior = $row_saldo["cantidad_anterior"];

	//para ver si ya ha sido usado ese producto con esa fecha de caducidad
	$query_producto = mysqli_query($con, "SELECT * FROM  inventarios WHERE id_inventario='" . $mod_id_inventario . "'");
	$row_producto = mysqli_fetch_array($query_producto);
	$codigo_producto = $row_producto["codigo_producto"];

	$query_salidas = mysqli_query($con, "SELECT * FROM inventarios WHERE ruc_empresa='" . $ruc_empresa . "' and codigo_producto='" . $codigo_producto . "' and operacion='SALIDA' and fecha_vencimiento='" . $mod_fecha_caducidad . "'");
	$row_salidas = mysqli_num_rows($query_salidas);

	if ($row_salidas > 0) {
		$errors[] = "No se puede editar la entrada, ya que modificaría la existencia. Existen registros de salidas de este producto con esta fecha de vencimiento. Debe hacer una nueva entrada y dar de baja la existencia actual mediante una salida manual." . mysqli_error($con);
	} else {
		if ($saldo_cantidad - $cantidad_anterior + $mod_cantidad >= 0) {
			$sql = "UPDATE inventarios SET precio='" . $mod_precio_producto . "', cantidad_entrada='" . $mod_cantidad . "',fecha_registro='" . $mod_fecha_entrada . "', fecha_vencimiento='" . $mod_fecha_caducidad . "', referencia='" . $mod_referencia . "', id_usuario='" . $id_usuario . "', fecha_agregado='" . $fecha_agregado . "',id_bodega='" . $mod_bodega . "', costo_unitario='" . $mod_costo_producto . "', lote='" . $mod_lote . "' WHERE id_inventario='" . $mod_id_inventario . "'";
			$query_update = mysqli_query($con, $sql);
			if ($query_update) {
				$messages[] = "La entrada de inventario ha sido actualizada satisfactoriamente.";
				echo "<script>setTimeout(function () {location.reload()}, 60 * 20)</script>";
			} else {
				$errors[] = "Lo siento algo ha salido mal intenta nuevamente." . mysqli_error($con);
			}
		} else {
			$errors[] = "No es posible modificar la entrada, el producto ya ha sido utilizado." . mysqli_error($con);
		}
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