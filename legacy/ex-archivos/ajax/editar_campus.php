<?php
include("../conexiones/conectalogin.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
	if (empty($_POST['mod_id_campus'])) {
           $errors[] = "Vuelva a seleccionar un nombre de campus";
        }else if (empty($_POST['mod_nombre_campus'])) {
           $errors[] = "Ingrese un nombre del campus";
		} else if (
			!empty($_POST['mod_id_campus']) &&
			!empty($_POST['mod_nombre_campus'])){
		$id_campus=mysqli_real_escape_string($con,(strip_tags($_POST["mod_id_campus"],ENT_QUOTES)));
		$nombre_campus=mysqli_real_escape_string($con,(strip_tags($_POST["mod_nombre_campus"],ENT_QUOTES)));

		$sql="UPDATE campus_alumnos SET nombre_campus='$nombre_campus',id_usuario=$id_usuario WHERE id_campus=$id_campus";
		$query_update = mysqli_query($con,$sql);
			if ($query_update){
				$messages[] = "El nombre del campus ha sido actualizado satisfactoriamente.";
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