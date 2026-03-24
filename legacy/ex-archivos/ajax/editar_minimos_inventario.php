<?php
include("../conexiones/conectalogin.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_minimo = mysqli_real_escape_string($con, (strip_tags($_POST["id_minimo"], ENT_QUOTES)));
$id_producto = mysqli_real_escape_string($con, (strip_tags($_POST["id_producto"], ENT_QUOTES)));
$id_bodega = mysqli_real_escape_string($con, (strip_tags($_POST["id_bodega"], ENT_QUOTES)));
$valor_minimo = mysqli_real_escape_string($con, (strip_tags($_POST["valor_minimo"], ENT_QUOTES)));
//para guardar un nuevo registro de minimos
if (empty($id_minimo)) {

	if ($valor_minimo < 0) {
		$errors[] = "Ingrese valor mínimo mayor o igual a cero";
	} else if (!is_numeric($valor_minimo)) {
		$errors[] = "No es valor";
	} else if ($valor_minimo >= 0) {
		$query_new_insert = mysqli_query($con, "INSERT INTO minimos_inventarios VALUES (NULL, '" . $ruc_empresa . "', '" . $id_producto . "', '" . $id_bodega . "', '" . $valor_minimo . "')");
		if ($query_new_insert) {
			$messages[] = "Registrado.";
			//echo "<script>setTimeout(function () {location.reload()}, 60 * 20)</script>";
		} else {
			$errors[] = "Lo siento algo ha salido mal intenta nuevamente." . mysqli_error($con);
		}
	} else {
		$errors[] = "Error desconocido.";
	}
}
//para modificar un minimo
if (!empty($id_minimo)) {

	if ($valor_minimo < 0) {
		$errors[] = "Ingrese valor mínimo mayor o igual a cero";
	} else if (!is_numeric($valor_minimo)) {
		$errors[] = "No es valor";
	} else if ($valor_minimo >= 0) {
		$query_update = mysqli_query($con, "UPDATE minimos_inventarios SET valor_minimo='" . $valor_minimo . "' WHERE id_minimo='" . $id_minimo . "'");
		if ($query_update) {
			$messages[] = "Actualizado.";
			//echo "<script>setTimeout(function () {location.reload()}, 60 * 20)</script>";
		} else {
			$errors[] = "Lo siento algo ha salido mal intenta nuevamente." . mysqli_error($con);
		}
	} else {
		$errors[] = "Error desconocido.";
	}
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