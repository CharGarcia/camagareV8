<?php

	if (empty($_POST['fecha_emision_sri'])) {
           $errors[] = "Ingrese fecha de emisión de la autorización SRI.";
		}else if (empty($_POST['fecha_vence_sri'])) {
           $errors[] = "Ingrese fecha de vencimiento de la autorización SRI.";
		}else if (empty($_POST['autorizacion_sri'])) {
           $errors[] = "Ingrese el número de autorización otorgado por el SRI.";
		}else if (empty($_POST['del_sri'])) {
           $errors[] = "Ingrese número inicial del rango autorizado por el SRI.";
		}else if (!is_numeric($_POST['del_sri'])) {
           $errors[] = "Ingrese dato en formato de número.";
		}else if (empty($_POST['al_sri'])) {
           $errors[] = "Ingrese número final del rango autorizado por el SRI.";
		}else if (!is_numeric($_POST['al_sri'])) {
           $errors[] = "Ingrese dato en formato de número.";
		}else if ($_POST['del_sri'] >= $_POST['al_sri']) {
           $errors[] = "El número inicial no puede ser mayor al número final.";
		}else if (empty($_POST['imprenta'])) {
           $errors[] = "Ingrese datos del pie de imprenta.";
        }else if (!empty($_POST['fecha_emision_sri']) && !empty($_POST['fecha_vence_sri'])&& !empty($_POST['imprenta'])
			&& !empty($_POST['del_sri'])&& !empty($_POST['al_sri'])&& !empty($_POST['autorizacion_sri'])
		){
					
		/* Connect To Database*/
		include("../conexiones/conectalogin.php");
		$con = conenta_login();
		// escaping, additionally removing everything that could be (html/javascript-) code
		session_start();
		$documento=mysqli_real_escape_string($con,(strip_tags($_POST["documento_sri"],ENT_QUOTES)));
		$serie=mysqli_real_escape_string($con,(strip_tags($_POST["serie_sri"],ENT_QUOTES)));
		$autorizacion=mysqli_real_escape_string($con,(strip_tags($_POST["autorizacion_sri"],ENT_QUOTES)));
		$fecha_emision=mysqli_real_escape_string($con,(strip_tags($_POST["fecha_emision_sri"],ENT_QUOTES)));
		$fecha_vence=mysqli_real_escape_string($con,(strip_tags($_POST["fecha_vence_sri"],ENT_QUOTES)));
		$del=mysqli_real_escape_string($con,(strip_tags($_POST["del_sri"],ENT_QUOTES)));
		$al=mysqli_real_escape_string($con,(strip_tags($_POST["al_sri"],ENT_QUOTES)));
		$imprenta=mysqli_real_escape_string($con,(strip_tags($_POST["imprenta"],ENT_QUOTES)));
		$ruc_empresa = $_SESSION['ruc_empresa'];
		
		
		
		 $busca_autorizacion = "SELECT * FROM autorizaciones_sri WHERE ruc_empresa = '$ruc_empresa' and codigo_documento = '$documento' and autorizacion_sri = '$autorizacion'";
		 $result = $con->query($busca_autorizacion);
		 $count = mysqli_num_rows($result);
				 if ($count == 1) {
					 $errors []= "La autorización ya está registrada.".mysqli_error($con);
				 }else{
						$sql="INSERT INTO autorizaciones_sri (id_autorizacion, codigo_documento, id_serie, autorizacion_sri, emision_autorizacion, vence_autorizacion, del_autorizacion, al_autorizacion, ruc_empresa, imprenta) 
						VALUES (null,'$documento','$serie','$autorizacion','$fecha_emision','$fecha_vence','$del','$al','$ruc_empresa','$imprenta')";
						$query_new_insert = mysqli_query($con,$sql);
						if ($query_new_insert){
							$messages[] = "La autorización ha sido ingresada satisfactoriamente.";
						} else{
							$errors []= "Lo siento algo ha salido mal intenta nuevamente.".mysqli_error($con);
						}
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