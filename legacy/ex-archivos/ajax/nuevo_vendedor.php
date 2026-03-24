<?php
include("../validadores/cedula.php");
include("../validadores/valida_varios_mails.php");
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
	if (empty($_POST['nombre'])) {
           $errors[] = "Ingrese Nombre del cliente";
		}else if (empty($_POST['cedula'])) {
           $errors[] = "Ingrese cedula o pasaporte";
		}else if (empty($_POST['direccion'])) {
           $errors[] = "Ingrese dirección del vendedor";
		} elseif (!empty($_POST['correo']) && validar_mails($_POST['correo'])=='error') {
			$errors[] = "Error en mail, Puede ingresar varios correos separados por coma y espacio.";
        } else if (!empty($_POST['nombre']) && !empty($_POST['direccion']) && !empty($_POST['cedula']) && !empty($_POST['correo'])){
		/* Connect To Database*/
		include("../conexiones/conectalogin.php");
		include("../clases/control_caracteres_especiales.php");
		$sanitize= new sanitize();
		$con = conenta_login();
		// escaping, additionally removing everything that could be (html/javascript-) code
		$nombre=$sanitize->string_sanitize($_POST["nombre"],$force_lowercase = false, $anal = false);
		$tipo_id=mysqli_real_escape_string($con,(strip_tags($_POST["tipo_id"],ENT_QUOTES)));
		$cedula=mysqli_real_escape_string($con,(strip_tags($_POST["cedula"],ENT_QUOTES)));
		$telefono=$sanitize->string_sanitize($_POST["telefono"],$force_lowercase = false, $anal = false);
		$correo=mysqli_real_escape_string($con,(strip_tags($_POST["correo"],ENT_QUOTES)));
		$direccion=$sanitize->string_sanitize($_POST["direccion"],$force_lowercase = false, $anal = false);
		$fecha_agregado=date("Y-m-d H:i:s");

	$busca_vendedor = mysqli_query($con, "SELECT * FROM vendedores WHERE numero_id = '".$cedula."' and ruc_empresa = '".$ruc_empresa."'");
	 $count = mysqli_num_rows($busca_vendedor);
	 if ($count > 0) {
		 $errors []= "El vendedor con esta identificación ya está registrado.".mysqli_error($con);
	 }else{
			
			switch ($tipo_id) {
		case 05:
			$validacedula = validacedula($cedula);
			if ($validacedula == "cedula correcta") { 
				echo guarda_vendedor($con, $ruc_empresa, $nombre, $tipo_id, $cedula, $telefono, $correo, $direccion, $fecha_agregado, $id_usuario);
			}else{
				$errors []= $validacedula . mysqli_error($con);
			}
			break;
			default:
				echo guarda_vendedor($con, $ruc_empresa, $nombre, $tipo_id, $cedula, $telefono, $correo, $direccion, $fecha_agregado, $id_usuario);
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
			
			
			function guarda_vendedor($con, $ruc_empresa, $nombre, $tipo_id, $cedula, $telefono, $correo, $direccion, $fecha_agregado, $id_usuario){
				$query_new_insert =mysqli_query($con,"INSERT INTO vendedores VALUES (null, '".$tipo_id."', '".$cedula."','".$nombre."','".$correo."','".$ruc_empresa."','".$fecha_agregado."','".$id_usuario."','".$telefono."','".$direccion."')");
				if ($query_new_insert){
					echo "<script>$.notify('Nuevo vendedor guardado.','success');
					setTimeout(function (){location.reload()}, 1000);
					</script>";
				} else{
					echo "<script>$.notify('Lo siento algo ha salido mal intenta nuevamente.','error')</script>";
				}				
			}
?>