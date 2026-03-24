<?php
include("../conexiones/conectalogin.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
	if (empty($_POST['mod_id_horario'])) {
           $errors[] = "Vuelva a seleccionar un horario";
        }else if (empty($_POST['mod_nombre_horario'])) {
           $errors[] = "Ingrese un detalle de horario";
		} else if (
			!empty($_POST['mod_id_horario']) &&
			!empty($_POST['mod_nombre_horario'])){
		$id_horario=mysqli_real_escape_string($con,(strip_tags($_POST["mod_id_horario"],ENT_QUOTES)));
		$nombre_horario=mysqli_real_escape_string($con,(strip_tags($_POST["mod_nombre_horario"],ENT_QUOTES)));

		$sql="UPDATE horarios_alumnos SET nombre_horario='$nombre_horario',id_usuario=$id_usuario WHERE id_horario=$id_horario";
		$query_update = mysqli_query($con,$sql);
			if ($query_update){
				$messages[] = "El detalle de horario ha sido actualizado satisfactoriamente.";
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