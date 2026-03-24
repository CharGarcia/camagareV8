<?php
	if (empty($_POST['mod_id_item'])) {
           $errors[] = "ID vacío";
        }else if (empty($_POST['mod_nombre_item'])) {
           $errors[] = "Ingrese nombre del item";
		}else if (empty($_POST['mod_ruta_item'])) {
           $errors[] = "Ingrese ruta";
		}else if (empty($_POST['mod_nivel'])) {
           $errors[] = "Seleccione nivel";
		}else if (empty($_POST['mod_estado'])) {
           $errors[] = "Seleccione estado";
        }  else if (!empty($_POST['mod_id_item']) && !empty($_POST['mod_nombre_item']) && !empty($_POST['mod_ruta_item']) && !empty($_POST['mod_nivel']) && !empty($_POST['mod_estado']))
		{
		/* Connect To Database*/
		include("../conexiones/conectalogin.php");
		$con = conenta_login();
		// escaping, additionally removing everything that could be (html/javascript-) code
		$mod_id_item=intval($_POST['mod_id_item']);
		$mod_nombre_item=mysqli_real_escape_string($con,(strip_tags($_POST["mod_nombre_item"],ENT_QUOTES)));
		$mod_ruta_item=mysqli_real_escape_string($con,(strip_tags($_POST["mod_ruta_item"],ENT_QUOTES)));
		$mod_nivel=mysqli_real_escape_string($con,(strip_tags($_POST["mod_nivel"],ENT_QUOTES)));
		$mod_estado=mysqli_real_escape_string($con,(strip_tags($_POST["mod_estado"],ENT_QUOTES)));
		
		$sql_update="UPDATE menu SET etiqueta='$mod_nombre_item', ruta='$mod_ruta_item', nivel='$mod_nivel', estado=$mod_estado WHERE id=$mod_id_item";
		$query_update = mysqli_query($con,$sql_update);
			if ($query_update){
				$messages[] = "El item del menú ha sido actualizado satisfactoriamente.";
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