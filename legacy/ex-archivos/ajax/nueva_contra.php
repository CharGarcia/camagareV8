<?php
session_start();
$id_usuario = $_SESSION['id_usuario'];
	if (empty($_POST['empresa'])) {
           $errors[] = "Seleccione una empresa";
		} else if (empty($_POST['institucion'])){
			$errors[] = "Ingrese el nombre de la institución de cual requiere guardar la clave";     
        } else if (!empty($_POST['empresa']) && !empty($_POST['institucion'])){
		/* Connect To Database*/
		include("../conexiones/conectalogin.php");
		$con = conenta_login();
		// escaping, additionally removing everything that could be (html/javascript-) code
		$ruc_empresa=mysqli_real_escape_string($con,(strip_tags($_POST["empresa"],ENT_QUOTES)));
		$institucion=mysqli_real_escape_string($con,(strip_tags($_POST["institucion"],ENT_QUOTES)));
		$usuario=mysqli_real_escape_string($con,(strip_tags($_POST["usuario"],ENT_QUOTES)));
		$clave=mysqli_real_escape_string($con,(strip_tags($_POST["clave"],ENT_QUOTES)));
		$detalle=mysqli_real_escape_string($con,(strip_tags($_POST["detalle"],ENT_QUOTES)));

	$busca_claves = "SELECT * FROM mis_claves WHERE id_usuario = $id_usuario and ruc_empresa = '$ruc_empresa' and institucion ='$institucion' and usuario = '$usuario' and clave = '$clave'";
	 $result = $con->query($busca_claves);
	 $count = mysqli_num_rows($result);
	 if ($count == 1) {
		 $errors []= "La clave que desea registrar, ya está registrada.".mysqli_error($con);
	 }else{
		
	
			$sql="INSERT INTO mis_claves VALUES (null,'$id_usuario','$ruc_empresa','$institucion','$usuario','$clave','$detalle')";
		$query_new_insert = mysqli_query($con,$sql);
			if ($query_new_insert){
				$messages[] = "La nueva contraseña ha sido guardada satisfactoriamente.";
			} else{
				$errors []= "Lo siento algo ha salido mal intenta nuevamente.".mysqli_error($con);
			}
		}
		}
		else {
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