<?php
	if (empty($_POST['mod_id'])) {
           $errors[] = "ID vacío";
        }else if (empty($_POST['mod_empresa'])) {
           $errors[] = "Seleccione una empresa";
		}else if (empty($_POST['mod_institucion'])) {
           $errors[] = "Ingrese una institución de cual quiere modificar la clave";
        }  else if (
			!empty($_POST['mod_id']) && !empty($_POST['mod_empresa']) && !empty($_POST['mod_institucion']) )
		{
		/* Connect To Database*/
		include("../conexiones/conectalogin.php");
		$con = conenta_login();
		// escaping, additionally removing everything that could be (html/javascript-) code
		$ruc_empresa=mysqli_real_escape_string($con,(strip_tags($_POST["mod_empresa"],ENT_QUOTES)));
		$institucion=mysqli_real_escape_string($con,(strip_tags($_POST["mod_institucion"],ENT_QUOTES)));
		$usuario=mysqli_real_escape_string($con,(strip_tags($_POST["mod_usuario"],ENT_QUOTES)));
		$clave=mysqli_real_escape_string($con,(strip_tags($_POST["mod_clave"],ENT_QUOTES)));
		$detalle=mysqli_real_escape_string($con,(strip_tags($_POST["mod_detalle"],ENT_QUOTES)));
		$id_misclaves=intval($_POST['mod_id']);
		

		$sql="UPDATE mis_claves SET ruc_empresa='$ruc_empresa', institucion='$institucion', usuario='$usuario', clave='$clave', detalle='$detalle' WHERE id_misclaves=$id_misclaves";
		$query_update = mysqli_query($con,$sql);
			if ($query_update){
				$messages[] = "El registro ha sido actualizado satisfactoriamente.";
			} else{
				$errors []= "Lo siento algo ha salido mal intenta nuevamente.".mysqli_error($con);
			}
		} else {
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