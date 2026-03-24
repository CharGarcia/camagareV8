<?php
		include("../conexiones/conectalogin.php");
		$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
	/*Inicia validacion del lado del servidor*/
	if (empty($_POST['nombre_horario'])){
			$errors[] = "Ingrese detalle del horario";
		} else if (
			!empty($_POST['nombre_horario']) 
		){
		/* Connect To Database*/
		// escaping, additionally removing everything that could be (html/javascript-) code
		$nombre_horario=mysqli_real_escape_string($con,(strip_tags($_POST["nombre_horario"],ENT_QUOTES)));

		// chequear si alumno ya existe ya existe
                $sql = "SELECT * FROM horarios_alumnos WHERE ruc_empresa = '$ruc_empresa' and nombre_horario = '$nombre_horario'";
                $query_check_nivel = mysqli_query($con,$sql);
				$query_check=mysqli_num_rows($query_check_nivel);
                if ($query_check >= 1) {
                    $errors[] = "Lo sentimos , el horario ya está registrado.";
                } else {
				
				$sql_alumno="INSERT INTO horarios_alumnos VALUES (null,'$nombre_horario','$ruc_empresa',$id_usuario)";
				$query_new_alumno = mysqli_query($con,$sql_alumno);
					if ($query_new_alumno){
						$messages[] = "Nuevo horario ha sido ingresado satisfactoriamente.";
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