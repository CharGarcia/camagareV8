<?php
/* Connect To Database*/
		include("../conexiones/conectalogin.php");
		$con = conenta_login();

		
	if (empty($_POST['usuario'])) {
           $errors[] = "Ingrese nombre de usuario";
		} elseif (!filter_var($_POST['mail'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Su dirección de correo electrónico no está en un formato de correo electrónico válida";
		}else if (empty($_POST['telefono'])) {
           $errors[] = "Ingrese teléfono de contacto";
        }else if (!empty($_POST['usuario'])&& !empty($_POST['mail'])&& !empty($_POST['telefono'])
		){

		session_start();
		$id_usuario = $_SESSION['id_usuario'];
		// escaping, additionally removing everything that could be (html/javascript-) code
		$usuario=mysqli_real_escape_string($con,(strip_tags($_POST["usuario"],ENT_QUOTES)));
		$telefono=mysqli_real_escape_string($con,(strip_tags($_POST["telefono"],ENT_QUOTES)));
		$mail=mysqli_real_escape_string($con,(strip_tags($_POST["mail"],ENT_QUOTES)));

		$sql="UPDATE usuarios SET nombre='$usuario', mail='$mail', telefono='$telefono' WHERE id=$id_usuario";
			$query_update = mysqli_query($con,$sql);
			if ($query_update){
				$messages[] = "Los datos se actualizaron correctamente.";
			} else{
				$errors []= "Lo siento algo ha salido mal intenta nuevamente.".mysqli_error($con);
			}
		}else {
			$errors []= "Error desconocido.";
		}
		
		if (isset($errors)){
			
			?>
			<div class="alert alert-danger" role="alert">
				<button type="button" class="close" data-dismiss="alert">&times;</button>
					<strong>Error! </strong> 
					<?php
						foreach ($errors as $error) {
								echo $error;
							}
						?>
			</div>
			<?php
			}
			if (isset($messages)){
				
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