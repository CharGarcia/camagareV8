<?php
		include("../conexiones/conectalogin.php");
		include("../validadores/cedula.php");
		require_once("../helpers/helpers.php");
		$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
	/*Inicia validacion del lado del servidor*/
	if (empty($_POST['tipo_id'])) {
           $errors[] = "Seleccione tipo de identidad";
        } else if (empty($_POST['cedula'])){
			$errors[] = "Ingrese número de cédula o pasaporte";
		} else if (empty($_POST['nombres_alumno'])){
			$errors[] = "Ingrese nombres";
		} else if (!date($_POST['fecha_nacimiento_alumno'])){
			$errors[] = "Ingrese fecha de nacimiento";
		} else if (!date($_POST['fecha_ingreso_alumno'])){
			$errors[] = "Ingrese fecha de ingreso al centro";
		} else if (empty($_POST['horario_alumno'])){
			$errors[] = "Ingrese horario";
	    } else if (empty($_POST['sexo_alumno'])){
			$errors[] = "Seleccione sexo";
		} else if (empty($_POST['sucursal_alumno'])){
			$errors[] = "Seleccione un campus";
		} else if (empty($_POST['serie_facturar'])){
			$errors[] = "Seleccione una sucursal con la que desea facturar";
			} else if (empty($_POST['paralelo_alumno'])){
			$errors[] = "Seleccione un paralelo";
		} else if (
			!empty($_POST['tipo_id']) &&
			!empty($_POST['cedula']) &&
			!empty($_POST['nombres_alumno']) &&
			!empty($_POST['fecha_nacimiento_alumno']) &&
			!empty($_POST['fecha_ingreso_alumno']) &&
			!empty($_POST['horario_alumno']) &&
			!empty($_POST['sexo_alumno'])&&
			!empty($_POST['sucursal_alumno']) &&
			!empty($_POST['serie_facturar']) &&
			!empty($_POST['paralelo_alumno'])
		){
		/* Connect To Database*/
		// escaping, additionally removing everything that could be (html/javascript-) code
		$tipo_id=mysqli_real_escape_string($con,(strip_tags($_POST["tipo_id"],ENT_QUOTES)));
		$cedula=mysqli_real_escape_string($con,(strip_tags(strClean($_POST["cedula"]),ENT_QUOTES)));
		$nombres_alumno=mysqli_real_escape_string($con,(strip_tags(strClean($_POST["nombres_alumno"]),ENT_QUOTES)));
		$fecha_nacimiento_alumno= date('Y-m-d H:i:s', strtotime($_POST["fecha_nacimiento_alumno"]));
		$fecha_ingreso_alumno= date('Y-m-d H:i:s', strtotime($_POST["fecha_ingreso_alumno"])); 
		$sexo_alumno=mysqli_real_escape_string($con,(strip_tags($_POST["sexo_alumno"],ENT_QUOTES)));
		$horario_alumno=mysqli_real_escape_string($con,(strip_tags($_POST["horario_alumno"],ENT_QUOTES)));
		$sucursal_alumno=mysqli_real_escape_string($con,(strip_tags($_POST["sucursal_alumno"],ENT_QUOTES)));
		$serie_facturar=mysqli_real_escape_string($con,(strip_tags($_POST["serie_facturar"],ENT_QUOTES)));
		$paralelo_alumno=mysqli_real_escape_string($con,(strip_tags($_POST["paralelo_alumno"],ENT_QUOTES)));
		$fecha_agregado=date("Y-m-d H:i:s");

		// chequear si alumno ya existe ya existe
                $sql = "SELECT * FROM alumnos WHERE ruc_empresa = '$ruc_empresa' and cedula_alumno = '$cedula'";
                $query_check_alumno = mysqli_query($con,$sql);
				$query_check=mysqli_num_rows($query_check_alumno);
                if ($query_check >= 1) {
                    $errors[] = "Lo sentimos , el alumno ya está registrado.";
                } else {
			switch ($tipo_id) {
			case 1:		
			$validacedula = validacedula($cedula);
				if ($validacedula == "cedula correcta") {
				$sql_alumno="INSERT INTO alumnos VALUES (null,'$cedula','$nombres_alumno','$fecha_nacimiento_alumno' ,'$fecha_ingreso_alumno','$horario_alumno','$sexo_alumno','$sucursal_alumno','$paralelo_alumno',$id_usuario,'$fecha_agregado',$ruc_empresa,'$tipo_id','1',0,'".$serie_facturar."')";
				$query_new_alumno = mysqli_query($con,$sql_alumno);
					if ($query_new_alumno){
						$messages[] = "Alumno ha sido ingresado satisfactoriamente.";
						echo "<script>setTimeout(function () {location.reload()}, 60 * 20)</script>";
					} else{
						$errors []= "Lo siento algo ha salido mal intenta nuevamente.".mysqli_error($con);
					}
				}else{
					$errors []= $validacedula . mysqli_error($con);
				}
				break;			
				default:		
				$sql_alumno="INSERT INTO alumnos VALUES (null,'$cedula','$nombres_alumno','$fecha_nacimiento_alumno' ,'$fecha_ingreso_alumno','$horario_alumno','$sexo_alumno','$sucursal_alumno','$paralelo_alumno',$id_usuario,'$fecha_agregado',$ruc_empresa,'$tipo_id','1',0,'".$serie_facturar."')";
				$query_new_alumno = mysqli_query($con,$sql_alumno);
					if ($query_new_alumno){
						$messages[] = "Alumno ha sido ingresado satisfactoriamente.";
						echo "<script>setTimeout(function () {location.reload()}, 60 * 20)</script>";
					} else{
						$errors []= "Lo siento algo ha salido mal intenta nuevamente.".mysqli_error($con);
					}
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