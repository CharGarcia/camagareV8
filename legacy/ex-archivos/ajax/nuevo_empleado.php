<?php
include("../validadores/cedula.php");
	/*Inicia validacion del lado del servidor*/
	if (empty($_POST['nombres'])) {
           $errors[] = "Nombres del empleado esta vacío";
        } else if (empty($_POST['apellidos'])){
			$errors[] = "Apellidos del empleado esta vacío";
		} else if (empty($_POST['cedula'])){
			$errors[] = "Ingrese cedula";
		} else if ($_POST['sexo']==""){
			$errors[] = "Seleccione sexo del empleado";
		} else if (empty($_POST['direccion'])){
			$errors[] = "Ingrese la direción del empleado";
		} else if (empty($_POST['nacimiento'])){
			$errors[] = "Ingrese fecha de nacimiento del empleado";
		} else if (!strtotime($_POST['nacimiento'])){
			$errors[] = "Ingrese fecha de nacimiento correcta";
		} else if ($_POST['rama']==""){
			$errors[] = "Seleccione una rama laboral del empleado";
		} else if ($_POST['cargo_empleado']==""){
			$errors[] = "Seleccione un cargo del empleado";
		} else if ($_POST['rel_lab']==""){
			$errors[] = "Seleccione una relación laboral del empleado";
		} else if (
			!empty($_POST['nombres']) &&
			!empty($_POST['apellidos']) &&
			!empty($_POST['cedula']) &&
			$_POST['sexo']!="" &&
			$_POST['nacimiento']!="" &&
			$_POST['rama']!="" &&
			$_POST['cargo_empleado']!="" &&
			$_POST['nacimiento']!="" &&
			!empty($_POST['rel_lab'])
		){
		/* Connect To Database*/
		include("../conexiones/conectalogin.php");
		$con = conenta_login();
		// escaping, additionally removing everything that could be (html/javascript-) code
		$cedula=mysqli_real_escape_string($con,(strip_tags($_POST["cedula"],ENT_QUOTES)));
		session_start();
	    $ruc_empresa = $_SESSION['ruc_empresa'];
		// chequear si empleado ya existe
                $sql = "SELECT * FROM empleados WHERE cedula_empleado = '" . $cedula . "' and ruc_empresa = '" . $ruc_empresa . "' ;";
                $query_check_empleado = mysqli_query($con,$sql);
				$query_check=mysqli_num_rows($query_check_empleado);
                if ($query_check == 1) {
                    $errors[] = "Lo sentimos , el empleado ya está registrado.";
                } else {
		
		$nombres=mysqli_real_escape_string($con,(strip_tags($_POST["nombres"],ENT_QUOTES)));
		$apellidos=mysqli_real_escape_string($con,(strip_tags($_POST["apellidos"],ENT_QUOTES)));
		$cedula=mysqli_real_escape_string($con,(strip_tags($_POST["cedula"],ENT_QUOTES)));
		$sexo=mysqli_real_escape_string($con,(strip_tags($_POST["sexo"],ENT_QUOTES)));
		$direccion=mysqli_real_escape_string($con,(strip_tags($_POST["direccion"],ENT_QUOTES)));
		$telefono=mysqli_real_escape_string($con,(strip_tags($_POST["telefono"],ENT_QUOTES)));
		$nacimiento = date('Y-m-d H:i:s', strtotime($_POST['nacimiento']));
		//$nacimiento = date('Y-m-d H:i:s', strtotime(str_replace('/', '/', $_POST['nacimiento'])));
		$rama=mysqli_real_escape_string($con,(strip_tags($_POST["rama"],ENT_QUOTES)));
		$cargo=mysqli_real_escape_string($con,(strip_tags($_POST["cargo_empleado"],ENT_QUOTES)));
		$rellab=mysqli_real_escape_string($con,(strip_tags($_POST["rel_lab"],ENT_QUOTES)));
		$mail=mysqli_real_escape_string($con,(strip_tags($_POST["mail"],ENT_QUOTES)));
		$fecha_agregado=date("Y-m-d H:i:s");
		$estado_empleado ="ACTIVO";
		$usuario = $_SESSION['id_usuario'];
		$validacedula = validaCedula();
			
			if ($validacedula == "cedula correcta"){
		
		
		$sql="INSERT INTO empleados (id_empleado, ruc_empresa, nombres_empleado, apellidos_empleado, cedula_empleado, sexo_empleado, dir_empleado, telf_empleado, nace_empleado, rama_empleado, cargo_empleado, rel_iess, mail_empleado, fecha_agregado, estado_empleado, usuario) VALUES (NULL,'$ruc_empresa', '$nombres', '$apellidos','$cedula','$sexo','$direccion', '$telefono','$nacimiento','$rama','$cargo','$rellab','$mail','$fecha_agregado','$estado_empleado', '$usuario')";
		$query_new_insert = mysqli_query($con,$sql);
			if ($query_new_insert){
				$messages[] = "El empleado ha sido registrado satisfactoriamente.";
			} else{
				$errors []= "Lo siento algo ha salido mal intenta nuevamente.". mysqli_error($con);
			}
			}else{
				$errors []= $validacedula . mysqli_error($con);
			}
		}
		} else {
			$errors []= "Error desconocido.";
		}
		

		
		if (isset($errors)){			
			?>
			<div class="alert alert-danger" role="alert">
				<button type="button" class="close" data-dismiss="alert">&times;</button>
					<strong>Corregir! </strong> 
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