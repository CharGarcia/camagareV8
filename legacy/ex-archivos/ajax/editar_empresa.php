<?php
	if (empty($_POST['mod_id_empresa'])) {
           $errors[] = "ID vacío";
        }else if (empty($_POST['mod_estado_empresa'])) {
           $errors[] = "Seleccione estado";
        }  else if (
			!empty($_POST['mod_id_empresa']) &&
			!empty($_POST['mod_estado_empresa'])
		){
		/* Connect To Database*/
		include("../conexiones/conectalogin.php");
		$con = conenta_login();
		// escaping, additionally removing everything that could be (html/javascript-) code
		$id_empresa=mysqli_real_escape_string($con,(strip_tags($_POST["mod_id_empresa"],ENT_QUOTES)));
		$estado_empresa=mysqli_real_escape_string($con,(strip_tags($_POST["mod_estado_empresa"],ENT_QUOTES)));

		$sql="UPDATE empresas SET estado='".$estado_empresa."' WHERE id='".$id_empresa."'";
		$query_update = mysqli_query($con,$sql);
			if ($query_update){
				$messages[] = "El estado ha sido actualizado satisfactoriamente.";
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