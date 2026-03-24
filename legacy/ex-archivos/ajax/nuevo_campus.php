<?php
		include("../conexiones/conectalogin.php");
		$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
	/*Inicia validacion del lado del servidor*/
	if (empty($_POST['nombre_campus'])){
			$errors[] = "Ingrese nombre del campus";
		} else if (
			!empty($_POST['nombre_campus']) 
		){
		/* Connect To Database*/
		// escaping, additionally removing everything that could be (html/javascript-) code
		$nombre_campus=mysqli_real_escape_string($con,(strip_tags($_POST["nombre_campus"],ENT_QUOTES)));

		// chequear si alumno ya existe ya existe
                $sql = "SELECT * FROM campus_alumnos WHERE ruc_empresa = '$ruc_empresa' and nombre_campus = '$nombre_campus'";
                $query_check_nivel = mysqli_query($con,$sql);
				$query_check=mysqli_num_rows($query_check_nivel);
                if ($query_check >= 1) {
                    $errors[] = "Lo sentimos , el campus ya está registrado.";
                } else {
				
				$sql_alumno="INSERT INTO campus_alumnos VALUES (null,'$nombre_campus',$id_usuario,'$ruc_empresa')";
				$query_new_alumno = mysqli_query($con,$sql_alumno);
					if ($query_new_alumno){
						$messages[] = "Nuevo campus ha sido ingresado satisfactoriamente.";
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