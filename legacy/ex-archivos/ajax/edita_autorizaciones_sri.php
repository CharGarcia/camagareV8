<?php

	if (!isset($_POST['id_autorizacion_mod'])) {
           $errors[] = "Vuelva a seleccionar una autorización";
		}else if (empty($_POST['fecha_emision_sri_mod'])) {
           $errors[] = "Ingrese fecha de emisión de la autorización SRI.";
		}else if (empty($_POST['fecha_vence_sri_mod'])) {
           $errors[] = "Ingrese fecha de vencimiento de la autorización SRI.";
		}else if (empty($_POST['autorizacion_sri_mod'])) {
           $errors[] = "Ingrese el número de autorización otorgado por el SRI.";
		}else if (empty($_POST['del_sri_mod'])) {
           $errors[] = "Ingrese número inicial del rango autorizado por el SRI.";
		}else if (!is_numeric($_POST['del_sri_mod'])) {
           $errors[] = "Ingrese dato en formato de número.";
		}else if (empty($_POST['al_sri_mod'])) {
           $errors[] = "Ingrese número final del rango autorizado por el SRI.";
		}else if (!is_numeric($_POST['al_sri_mod'])) {
           $errors[] = "Ingrese dato en formato de número.";
		}else if (empty($_POST['imprenta_mod'])) {
           $errors[] = "Ingrese datos del pie de imprenta.";
		}else if ($_POST['del_sri_mod'] >= $_POST['al_sri_mod']) {
           $errors[] = "El número inicial no puede ser mayor al número final.";
        }else if (!empty($_POST['fecha_emision_sri_mod']) && !empty($_POST['fecha_vence_sri_mod']) && !empty($_POST['imprenta_mod'])
			&& !empty($_POST['del_sri_mod'])&& !empty($_POST['al_sri_mod'])&& !empty($_POST['autorizacion_sri_mod'])
		){
					
		/* Connect To Database*/
		include("../conexiones/conectalogin.php");
		$con = conenta_login();
		// escaping, additionally removing everything that could be (html/javascript-) code
		session_start();
		$id_autorizacion=mysqli_real_escape_string($con,(strip_tags($_POST["id_autorizacion_mod"],ENT_QUOTES)));
		$documento=mysqli_real_escape_string($con,(strip_tags($_POST["documento_sri_mod"],ENT_QUOTES)));
		$serie=mysqli_real_escape_string($con,(strip_tags($_POST["serie_sri_mod"],ENT_QUOTES)));
		$autorizacion=mysqli_real_escape_string($con,(strip_tags($_POST["autorizacion_sri_mod"],ENT_QUOTES)));
		$fecha_emision=mysqli_real_escape_string($con,(strip_tags($_POST["fecha_emision_sri_mod"],ENT_QUOTES)));
		$fecha_vence=mysqli_real_escape_string($con,(strip_tags($_POST["fecha_vence_sri_mod"],ENT_QUOTES)));
		$del=mysqli_real_escape_string($con,(strip_tags($_POST["del_sri_mod"],ENT_QUOTES)));
		$al=mysqli_real_escape_string($con,(strip_tags($_POST["al_sri_mod"],ENT_QUOTES)));
		$imprenta=mysqli_real_escape_string($con,(strip_tags($_POST["imprenta_mod"],ENT_QUOTES)));
		$ruc_empresa = $_SESSION['ruc_empresa'];
		

			$sql="UPDATE autorizaciones_sri SET codigo_documento='".$documento."', id_serie='".$serie."',autorizacion_sri='".$autorizacion."', emision_autorizacion='".$fecha_emision."', vence_autorizacion='".$fecha_vence."', del_autorizacion='".$del."', al_autorizacion='".$al."', imprenta='".$imprenta."' WHERE id_autorizacion='".$id_autorizacion."'";
		$query_update = mysqli_query($con,$sql);
			if ($query_update){
				$messages[] = "La autorización SRI ha sido actualizada satisfactoriamente.";
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
					<strong>Error!</strong> 
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