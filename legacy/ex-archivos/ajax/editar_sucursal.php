<?php
session_start();
	if (empty($_POST['mod_id_sucursal'])) {
           $errors[] = "Seleccione una sucursal";
        }else if (empty($_POST['mod_direccion_sucursal'])) {
           $errors[] = "Ingrese dirección de la sucursal";
		}else if (empty($_POST['mod_nombre_sucursal'])) {
           $errors[] = "Ingrese nombre comercial de la sucursal";
        }  else if (
			!empty($_POST['mod_id_sucursal']) && !empty($_POST['mod_direccion_sucursal']) && !empty($_POST['mod_nombre_sucursal']) )
		{
		/* Connect To Database*/
		include("../conexiones/conectalogin.php");
		$con = conenta_login();
		// escaping, additionally removing everything that could be (html/javascript-) code
		$id_sucursal=mysqli_real_escape_string($con,(strip_tags($_POST["mod_id_sucursal"],ENT_QUOTES)));
		$serie_sucursal=mysqli_real_escape_string($con,(strip_tags($_POST["mod_serie"],ENT_QUOTES)));
		$nombre_sucursal=mysqli_real_escape_string($con,(strip_tags($_POST["mod_nombre_sucursal"],ENT_QUOTES)));
		$direccion_sucursal=mysqli_real_escape_string($con,(strip_tags($_POST["mod_direccion_sucursal"],ENT_QUOTES)));
		$telefono_sucursal=mysqli_real_escape_string($con,(strip_tags($_POST["mod_telefono_sucursal"],ENT_QUOTES)));
		

			$sql="UPDATE sucursales SET serie= '".$serie_sucursal."', direccion_sucursal='".$direccion_sucursal."', telefono_sucursal='".$telefono_sucursal."', nombre_sucursal='".$nombre_sucursal."' WHERE id_sucursal='".$id_sucursal."'";
		$query_update = mysqli_query($con,$sql);
			if ($query_update){
				$messages[] = "La sucursal ha sido actualizada satisfactoriamente.";
				echo "<script>setTimeout(function () {location.reload()}, 60 * 20)</script>";
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