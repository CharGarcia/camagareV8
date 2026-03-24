<?php

	if (empty($_POST['mod_id'])) {
           $errors[] = "ID vacío";
        }else if (empty($_POST['mod_nombre'])) {
           $errors[] = "Nombre vacío";
        }  else if (
			!empty($_POST['mod_id']) &&
			!empty($_POST['mod_nombre'])
		){
		/* Connect To Database*/
		include("../conexiones/conectalogin.php");
		$con = conenta_login();
		// escaping, additionally removing everything that could be (html/javascript-) code
		$nombre=mysqli_real_escape_string($con,(strip_tags($_POST["mod_nombre"],ENT_QUOTES)));
		$estado=mysqli_real_escape_string($con,(strip_tags($_POST["mod_estado"],ENT_QUOTES)));
		$tipo=mysqli_real_escape_string($con,(strip_tags($_POST["mod_tipo"],ENT_QUOTES)));
		$mail=mysqli_real_escape_string($con,(strip_tags($_POST["mod_mail"],ENT_QUOTES)));
		$cedula=mysqli_real_escape_string($con,(strip_tags($_POST["mod_cedula"],ENT_QUOTES)));
		$id_usuario=($_POST['mod_id']);
		
		// aqui poner si solo hay un super administrador no se puede cambiar el tipo de usuario
		$busca_super = "SELECT * FROM usuarios WHERE id = '".$id_usuario."' ";
	    $result = $con->query($busca_super);
		$administrador = mysqli_fetch_assoc($result);
		$super = $administrador['tipo'];
		
		if ($super == "Super Administrador" && $tipo != "Super Administrador") {
		$cuenta_administrador = "SELECT * FROM usuarios WHERE tipo = 'Super Administrador' ";
	    $resultado = $con->query($cuenta_administrador);
		$count = mysqli_num_rows($resultado);
		if ($count == 1 ){
			$errors []= "Lo siento, debe haber al menos un usuario Super Administrador.".mysqli_error($con);
		}else{		
		$sql="UPDATE usuarios SET nombre='".$nombre."', cedula='".$cedula."', tipo='".$tipo."', estado='".$estado."', mail='".$mail."' WHERE id='".$id_usuario."'";
		$query_update = mysqli_query($con,$sql);
			if ($query_update){
				$messages[] = "Usuario ha sido actualizado satisfactoriamente.";
			} else{
				$errors []= "Lo siento algo ha salido mal intenta nuevamente.".mysqli_error($con);
			}
			
		}
		}else{
					switch ($tipo) {
					case "Usuario":
						$nivel_usuario='1';
						break;
					case "Administrador":
						$nivel_usuario='2';
						break;
					case "Super Administrador":
						$nivel_usuario='3';
						break;
						}
			
			$sql="UPDATE usuarios SET nombre='".$nombre."', cedula='".$cedula."',nivel='".$nivel_usuario."', tipo='".$tipo."', estado='".$estado."', mail='".$mail."' WHERE id='".$id_usuario."'";
		$query_update = mysqli_query($con,$sql);
			if ($query_update){
				$messages[] = "Usuario ha sido actualizado satisfactoriamente.";
			} else{
				$errors []= "Lo siento algo ha salido mal intenta nuevamente.".mysqli_error($con);
			}
			
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