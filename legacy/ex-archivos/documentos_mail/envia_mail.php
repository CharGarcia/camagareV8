<?php
//include("../validadores/valida_varios_mails.php");
include("../helpers/helpers.php");
include("../documentos_mail/envia_documentos_sri_por_mail.php");

if ((isset($_GET['id_documento'])) && (isset($_GET['tipo_documento'])) && (isset($_GET['mail_receptor']))) {
	if (empty($_GET['id_documento'])) {
		$errors[] = "Seleccione un documento electrónico para enviar mail";
	} else if (empty($_GET['tipo_documento'])) {
		$errors[] = "Seleccione un documento electrónico para enviar mail";
	} else if (empty($_GET['mail_receptor'])) {
		$errors[] = "Ingrese mail";
	} elseif (!empty($_GET['mail_receptor']) && !validarCorreo($_GET['mail_receptor'])) {
		$errors[] = "Error en mail, Puede ingresar varios correos separados por coma y espacio.";
	} else if (!empty($_GET['id_documento']) && (!empty($_GET['tipo_documento'])) && (!empty($_GET['mail_receptor']))) {

		$id_documento = $_GET['id_documento'];
		$tipo_documento = $_GET['tipo_documento'];
		$mail_receptor = $_GET['mail_receptor'];
		$envia_documento = new enviar_documentos_sri();
		echo $envia_documento->envia_mail($id_documento, $tipo_documento, $mail_receptor);
	} else {
?>
		<div class="alert alert-danger" role="alert">
			<button type="button" class="close" data-dismiss="alert">&times;</button>
			<strong>Error!</strong> Error desconocido.
		</div>
	<?php
	}
}

if (isset($errors)) {
	?>
	<div class="alert alert-danger" role="alert">
		<button type="button" class="close" data-dismiss="alert">&times;</button>
		<strong><?php echo ("Revisar!") ?></strong>
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
		<strong>Bien hecho! </strong>
		<?php
		foreach ($messages as $message) {
			echo $message;
		}
		?>
	</div>
<?php
}
?>