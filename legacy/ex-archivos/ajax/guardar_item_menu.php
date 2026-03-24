<?php
	if (empty($_POST['nombre_item'])) {
           $errors[] = "Ingrese Nombre del item";
		}else if (empty($_POST['ruta_item']) ) {
           $errors[] = "Ingrese ruta del archivo";
		}else if (empty($_POST['nivel']) ) {
           $errors[] = "Seleccione nivel";
		}else if (empty($_POST['estado']) ) {
           $errors[] = "Seleccione estado"; 
        } else if (!empty($_POST['nombre_item'])&&!empty($_POST['ruta_item'])&&!empty($_POST['nivel'])&&!empty($_POST['estado'])){
		/* Connect To Database*/
		include("../conexiones/conectalogin.php");
		$con = conenta_login();
		// escaping, additionally removing everything that could be (html/javascript-) code
		$nombre_item=mysqli_real_escape_string($con,(strip_tags($_POST["nombre_item"],ENT_QUOTES)));
		$ruta_item=mysqli_real_escape_string($con,(strip_tags($_POST["ruta_item"],ENT_QUOTES)));
		$nivel=mysqli_real_escape_string($con,(strip_tags($_POST["nivel"],ENT_QUOTES)));
		$estado=mysqli_real_escape_string($con,(strip_tags($_POST["estado"],ENT_QUOTES)));
		
	$busca_menu = "SELECT * FROM menu WHERE etiqueta = '$nombre_item' ";
	 $result = $con->query($busca_menu);
	 $count = mysqli_num_rows($result);
	 if ($count == 1) {
		 $errors []= "El nombre ya está registrado.".mysqli_error($con);
	 }else{
		$icono="";
		$query_new_insert=mysqli_query($con,"INSERT INTO menu VALUES (null,'".$nombre_item."','".$ruta_item."','".$nivel."','".$estado."','".$icono."')");
			if ($query_new_insert){
				$messages[] = "El nuevo item ha sido ingresado satisfactoriamente.";
			} else{
				$errors []= "Lo siento algo ha salido mal intenta nuevamente.".mysqli_error($con);
			}
		}
		} else {
			$errors []= "Error desconocido, vuelva a intentarlo.";
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