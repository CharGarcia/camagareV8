<?php
include("../validadores/cedula.php");
include("../validadores/valida_varios_mails.php");
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
	if (empty($_POST['mod_id_vendedor'])) {
           $errors[] = "Seleccione un vendedor para actualizar";
        }else if (empty($_POST['mod_nombre'])) {
           $errors[] = "Ingrese nombre del vendedor";
		}else if (empty($_POST['mod_tipo_id'])) {
           $errors[] = "Seleccione un tipo de identificación para el vendedor";
		}else if (empty($_POST['mod_numero_id'])) {
           $errors[] = "Ingrese número de cedula o pasaporte";
		}else if (empty($_POST['mod_direccion'])) {
           $errors[] = "Ingrese la dirección del vendedor";
		} elseif (empty($_POST['mod_correo'])) {
		   $errors[] = "Ingrese correo electrónico";
		} elseif (!empty($_POST['mod_email']) && validar_mails($_POST['mod_correo'])=='error') {
			$errors[] = "Error en mail, Puede ingresar varios correos separados por coma y espacio.";
        }  else if (!empty($_POST['mod_id_vendedor']) && !empty($_POST['mod_nombre']) && !empty($_POST['mod_tipo_id']) && !empty($_POST['mod_numero_id']) && !empty($_POST['mod_direccion'])
		){
		/* Connect To Database*/
		include("../conexiones/conectalogin.php");
		include("../clases/control_caracteres_especiales.php");
		$sanitize= new sanitize();
		$con = conenta_login();
		// escaping, additionally removing everything that could be (html/javascript-) code
		$nombre=$sanitize->string_sanitize($_POST["mod_nombre"],$force_lowercase = false, $anal = false);
		$tipo_id=mysqli_real_escape_string($con,(strip_tags($_POST["mod_tipo_id"],ENT_QUOTES)));
		$numero_id=mysqli_real_escape_string($con,(strip_tags($_POST["mod_numero_id"],ENT_QUOTES)));
		$telefono=$sanitize->string_sanitize($_POST["mod_telefono"],$force_lowercase = false, $anal = false);
		$email=mysqli_real_escape_string($con,(strip_tags($_POST["mod_correo"],ENT_QUOTES)));
		$direccion=$sanitize->string_sanitize($_POST["mod_direccion"],$force_lowercase = false, $anal = false);
		$id_vendedor=intval($_POST['mod_id_vendedor']);
		
	switch ($tipo_id) {
    case 05:
        $validacedula = validacedula($numero_id);
		if ($validacedula == "cedula correcta") { 
			echo editar_vendedor($con, $nombre, $telefono, $email, $direccion, $id_usuario, $id_vendedor);
		}else{
			$errors []= $validacedula . mysqli_error($con);
		}
        break;
		default:
		echo editar_vendedor($con, $nombre, $telefono, $email, $direccion, $id_usuario, $id_vendedor);
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
			
			function editar_vendedor($con, $nombre, $telefono, $email, $direccion, $id_usuario, $id_vendedor){
				$query_update=mysqli_query($con,"UPDATE vendedores SET nombre='".$nombre."', telefono='".$telefono."', correo='".$email."', direccion='".$direccion."', id_usuario = '".$id_usuario."'  WHERE id_vendedor='".$id_vendedor."'");			
				if ($query_update){
					echo "<script>$.notify('Vendedor actualizado.','success');
					setTimeout(function (){location.reload()}, 1000);
					</script>";
				} else{
					echo "<script>$.notify('Lo siento algo ha salido mal intenta nuevamente.','error')</script>";
				}				
			}
?>