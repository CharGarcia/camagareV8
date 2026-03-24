<?php
/*Inicia validacion del lado del servidor*/
if (empty($_POST['id_producto'])) {
	$errors[] = "Busque y seleccione un producto";
} else if (empty($_POST['fecha_entrada'])) {
	$errors[] = "Ingrese una fecha de registro";
} else if (empty($_POST['fecha_caducidad'])) {
	$errors[] = "Ingrese una fecha de caducidad";
} else if (empty($_POST['cantidad'])) {
	$errors[] = "Ingrese cantidad";
} else if (!is_numeric($_POST['cantidad'])) {
	$errors[] = "La cantidad no es valor";
} else if ($_POST['cantidad'] <= 0) {
	$errors[] = "La cantidad debe ser mayor a cero";
} else if (!is_numeric($_POST['costo_producto'])) {
	$errors[] = "El costo unitario no es valor";
} else if ($_POST['costo_producto'] < 0) {
	$errors[] = "El costo del producto no puede ser menor a cero.";
} else if (empty($_POST['bodega'])) {
	$errors[] = "Seleccione una bodega de destino";
} else if (empty($_POST['lote'])) {
	$errors[] = "Ingrese un lote";
} else if (date('Y-m-d H:i:s', strtotime($_POST['fecha_entrada'])) > date("Y-m-d H:i:s")) {
	$errors[] = "La fecha de registro no puede ser mayor a la actual";
} else if (empty($_POST['referencia'])) {
	$errors[] = "Ingrese una referencia de la entrada";
} else if (
	!empty($_POST['id_producto']) && !empty($_POST['fecha_entrada']) && !empty($_POST['fecha_caducidad']) && !empty($_POST['cantidad'])
	&& !empty($_POST['bodega']) && !empty($_POST['referencia'])
) {
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
	$id_producto = mysqli_real_escape_string($con, (strip_tags($_POST["id_producto"], ENT_QUOTES)));
	$codigo_producto = mysqli_real_escape_string($con, (strip_tags($_POST["codigo_producto"], ENT_QUOTES)));
	$nombre_producto = mysqli_real_escape_string($con, (strip_tags($_POST["nombre_producto"], ENT_QUOTES)));
	$fecha_entrada = date('Y-m-d H:i:s', strtotime($_POST['fecha_entrada']));
	$fecha_caducidad = date('Y-m-d H:i:s', strtotime($_POST['fecha_caducidad']));
	$cantidad_entrada = mysqli_real_escape_string($con, (strip_tags($_POST["cantidad"], ENT_QUOTES)));
	$lote = mysqli_real_escape_string($con, (strip_tags($_POST["lote"], ENT_QUOTES)));
	$precio_producto = '0';
	$costo_producto = mysqli_real_escape_string($con, (strip_tags($_POST["costo_producto"], ENT_QUOTES)));
	//buscar la unidad de medida de ese producto
	$sql_unidad_medida = "SELECT * FROM  productos_servicios where id = '" . $id_producto . "' ";
	$queri_unidad_medida = mysqli_query($con, $sql_unidad_medida);
	$fila_unidad_medida = mysqli_fetch_array($queri_unidad_medida);
	$unidad_medida = $fila_unidad_medida['id_unidad_medida'];
	$referencia = mysqli_real_escape_string($con, (strip_tags($_POST["referencia"], ENT_QUOTES)));
	$bodega = mysqli_real_escape_string($con, (strip_tags($_POST["bodega"], ENT_QUOTES)));

	session_start();
	$id_usuario = $_SESSION['id_usuario'];
	$ruc_empresa = $_SESSION['ruc_empresa'];
	ini_set('date.timezone', 'America/Guayaquil');
	$fecha_agregado = date("Y-m-d H:i:s");
	$sql = "INSERT INTO inventarios VALUES (NULL, '" . $ruc_empresa . "', '" . $id_producto . "','" . $precio_producto . "','" . $cantidad_entrada . "',0,'" . $fecha_entrada . "','" . $fecha_caducidad . "','" . $referencia . "', '" . $id_usuario . "', '" . $unidad_medida . "','" . $fecha_agregado . "','M','" . $bodega . "','ENTRADA','" . $codigo_producto . "','" . $nombre_producto . "','" . $costo_producto . "','OK','" . $lote . "',0)";
	$query_new_insert = mysqli_query($con, $sql);
	if ($query_new_insert) {
		$messages[] = "Nueva entrada de inventario registrada.";
		echo "<script>setTimeout(function () {location.reload()}, 60 * 20)</script>";
	} else {
		$errors[] = "Lo siento algo ha salido mal intenta nuevamente." . mysqli_error($con);
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