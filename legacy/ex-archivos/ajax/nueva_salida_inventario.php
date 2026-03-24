<?php
include("../conexiones/conectalogin.php");
session_start();
$id_usuario = $_SESSION['id_usuario'];
$ruc_empresa = $_SESSION['ruc_empresa'];

$con = conenta_login();

/*Inicia validacion del lado del servidor*/
if (empty($_POST['id_producto'])) {
	$errors[] = "Busque y seleccione un producto";
} else if (empty($_POST['fecha_salida'])) {
	$errors[] = "Ingrese una fecha de registro";
} else if (date('Y-m-d H:i:s', strtotime($_POST['fecha_salida'])) > date("Y-m-d H:i:s")) {
	$errors[] = "La fecha de registro no puede ser mayor a la actual";
} else if (empty($_POST['cantidad'])) {
	$errors[] = "Ingrese cantidad";
} else if (!is_numeric($_POST['cantidad'])) {
	$errors[] = "La cantidad no es valor";
} else if ($_POST['cantidad']<=0) {
	$errors[] = "La cantidad debe ser mayor a cero";
} else if (empty($_POST['precio_producto'])) {
	$errors[] = "Precio del producto";
} else if (!is_numeric($_POST['precio_producto'])) {
	$errors[] = "El precio no es valor";
} else if ($_POST['cantidad'] > $_POST['saldo_producto']) {
	$errors[] = "La cantidad de salida no puede ser mayor al saldo.";
} else if (empty($_POST['unidad_medida'])) {
	$errors[] = "Seleccione la unidad de medida";
} else if (empty($_POST['bodega'])) {
	$errors[] = "Seleccione una bodega de destino";
} else if (empty($_POST['referencia'])) {
	$errors[] = "Ingrese una referencia de la salida";
} else if (
	!empty($_POST['id_producto']) && !empty($_POST['fecha_salida']) && !empty($_POST['cantidad'])
	&& !empty($_POST['precio_producto']) && !empty($_POST['unidad_medida']) && !empty($_POST['bodega']) && !empty($_POST['referencia'])
) {

	// escaping, additionally removing everything that could be (html/javascript-) code
	$id_producto = mysqli_real_escape_string($con, (strip_tags($_POST["id_producto"], ENT_QUOTES)));
	$codigo_producto = mysqli_real_escape_string($con, (strip_tags($_POST["codigo_producto"], ENT_QUOTES)));
	$nombre_producto = mysqli_real_escape_string($con, (strip_tags($_POST["nombre_producto"], ENT_QUOTES)));
	$fecha_salida = date('Y-m-d H:i:s', strtotime($_POST['fecha_salida']));
	$cantidad_salida = mysqli_real_escape_string($con, (strip_tags($_POST["cantidad"], ENT_QUOTES)));
	$precio_producto = mysqli_real_escape_string($con, (strip_tags($_POST["precio_producto"], ENT_QUOTES)));
	$unidad_medida = mysqli_real_escape_string($con, (strip_tags($_POST["unidad_medida"], ENT_QUOTES)));
	$referencia = mysqli_real_escape_string($con, (strip_tags($_POST["referencia"], ENT_QUOTES)));
	$bodega = mysqli_real_escape_string($con, (strip_tags($_POST["bodega"], ENT_QUOTES)));
	$lote = mysqli_real_escape_string($con, (strip_tags($_POST["lote_salida"], ENT_QUOTES)));
	$serie = mysqli_real_escape_string($con, (strip_tags($_POST["serie_salida_inventario"], ENT_QUOTES)));
	$tipo_salida = "M";

	$consulta_configuracion = mysqli_query($con, "SELECT * FROM configuracion_facturacion WHERE ruc_empresa ='" . $ruc_empresa . "' and serie_sucursal ='" . $serie . "';");
	$row_configuracion = mysqli_fetch_array($consulta_configuracion);
	$requiere_lote = $row_configuracion['lote'];

	ini_set('date.timezone', 'America/Guayaquil');
	$fecha_agregado = date("Y-m-d H:i:s");
	include("../clases/control_salidas_inventario.php");
	$guarda_salida_inventario = new control_salida_inventario();


	if ($requiere_lote == 'SI' && empty($lote)) {
		$errors[] = "Seleccione un lote.";
	} else {

		$query_new_insert = $guarda_salida_inventario->salidas_desde_inventario($bodega, $id_producto, $cantidad_salida, $tipo_salida, $fecha_salida, $referencia, $unidad_medida, $precio_producto, $lote);

		if ($query_new_insert) {
			echo "<script>
					$.notify('Salida de inventario registrada.','success');
					setTimeout(function () {location.reload()}, 1000);
					</script>";
		} else {
			$errors[] = "Lo siento algo ha salido mal intenta nuevamente." . mysqli_error($con);
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