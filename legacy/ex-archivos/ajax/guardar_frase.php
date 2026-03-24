<?php
	if (empty($_POST['nombre_frase'])) {
           $errors[] = "Ingrese frase";
        } else if (!empty($_POST['nombre_frase'])){
		/* Connect To Database*/
		include("../conexiones/conectalogin.php");
		$con = conenta_login();
		$nombre_frase=mysqli_real_escape_string($con,(strip_tags($_POST["nombre_frase"],ENT_QUOTES)));
		
		$sql="INSERT INTO pensamientos VALUES (null,'$nombre_frase')";
		$query_new_insert = mysqli_query($con,$sql);
			if ($query_new_insert){
				echo "<script>
				$.notify('La nueva frase ha sido ingresada satisfactoriamente','success');
				setTimeout(function () {location.reload()}, 60 * 20); 
				</script>";	
			} else{
				$errors []= "Lo siento algo ha salido mal intenta nuevamente.".mysqli_error($con);
			}
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