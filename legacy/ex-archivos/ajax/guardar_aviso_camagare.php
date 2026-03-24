<?php
if (empty($_POST['ruc_empresa'])) {
	$errors[] = "Seleccione empresa";
} else if (empty($_POST['detalle_aviso'])) {
	$errors[] = "Ingrese un detalle o aviso";
} else if (!empty($_POST['ruc_empresa']) && !empty($_POST['detalle_aviso'])) {
	/* Connect To Database*/
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
	$empresa = mysqli_real_escape_string($con, (strip_tags($_POST["ruc_empresa"], ENT_QUOTES)));
	$detalle_aviso = mysqli_real_escape_string($con, (strip_tags($_POST["detalle_aviso"], ENT_QUOTES)));
	session_start();
	$id_usuario = $_SESSION['id_usuario'];

	$sql = "INSERT INTO avisos_camagare VALUES (null,'" . $empresa . "','" . $detalle_aviso . "','" . $id_usuario . "')";
	$query_new_insert = mysqli_query($con, $sql);
	if ($query_new_insert) {
		echo "<script>
				$.notify('El nuevo aviso ha sido ingresado satisfactoriamente','success');
				setTimeout(function () {location.reload()}, 40 * 20); 
				</script>";
	} else {
		$errors[] = "Lo siento algo ha salido mal intenta nuevamente." . mysqli_error($con);
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