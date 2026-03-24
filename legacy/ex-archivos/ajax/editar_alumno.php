<?php
		include("../conexiones/conectalogin.php");
		include("../validadores/cedula.php");
		require_once("../helpers/helpers.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
	if (empty($_POST['mod_id_alumno'])) {
           $errors[] = "Vuelva a seleccionar un alumno";
        }else if (empty($_POST['mod_tipo_id'])) {
           $errors[] = "Seleccione tipo id";
		}else if (empty($_POST['mod_cedula_alumno'])) {
           $errors[] = "Ingrese número de cedula o pasaporte";
		}else if (empty($_POST['mod_nombres_alumno'])) {
           $errors[] = "Ingrese nombres";
		}else if (empty($_POST['mod_fecha_nacimiento_alumno'])) {
           $errors[] = "Ingrese fecha de nacimiento";
		}else if (!date($_POST['mod_fecha_nacimiento_alumno'])) {
           $errors[] = "Ingrese fecha de nacimiento correcta";
		}else if (empty($_POST['mod_fecha_ingreso_alumno'])) {
           $errors[] = "Ingrese fecha de ingreso al centro";
		}else if (!date($_POST['mod_fecha_ingreso_alumno'])) {
           $errors[] = "Ingrese fecha de ingreso correcta";
		}else if (empty($_POST['mod_sexo_alumno'])) {
           $errors[] = "Seleccione sexo";
		}else if (empty($_POST['mod_horario_alumno'])) {
           $errors[] = "Seleccione horario";
		}else if (empty($_POST['mod_sucursal_alumno'])) {
           $errors[] = "Seleccione centro infantil";
		}else if (empty($_POST['mod_nivel_alumno'])) {
           $errors[] = "Seleccione nivel o paralelo";
		}else if (empty($_POST['mod_estado_alumno'])) {
           $errors[] = "Seleccione estado";
		}else if (empty($_POST['mod_serie_facturar'])) {
           $errors[] = "Seleccione sucursal con la que desea facturar";
		} else if (
			!empty($_POST['mod_id_alumno']) && !empty($_POST['mod_tipo_id']) && !empty($_POST['mod_cedula_alumno']) && !empty($_POST['mod_nombres_alumno']) &&
			!empty($_POST['mod_fecha_nacimiento_alumno']) && !empty($_POST['mod_fecha_ingreso_alumno']) && !empty($_POST['mod_sexo_alumno'])
			&& !empty($_POST['mod_horario_alumno']) && !empty($_POST['mod_sucursal_alumno']) && !empty($_POST['mod_nivel_alumno']) && !empty($_POST['mod_estado_alumno']) && !empty($_POST['mod_serie_facturar'])
			){
		$id_alumno=mysqli_real_escape_string($con,(strip_tags($_POST["mod_id_alumno"],ENT_QUOTES)));
		$tipo_id=mysqli_real_escape_string($con,(strip_tags($_POST["mod_tipo_id"],ENT_QUOTES)));
		$cedula_alumno=mysqli_real_escape_string($con,(strip_tags(strClean($_POST["mod_cedula_alumno"]),ENT_QUOTES)));
		$nombre_alumno=mysqli_real_escape_string($con,(strip_tags(strClean($_POST["mod_nombres_alumno"]),ENT_QUOTES)));
		$fecha_nacimiento= date('Y-m-d H:i:s', strtotime($_POST["mod_fecha_nacimiento_alumno"]));
		$fecha_ingreso= date('Y-m-d H:i:s', strtotime($_POST["mod_fecha_ingreso_alumno"])); 
		$sexo_alumno=mysqli_real_escape_string($con,(strip_tags($_POST["mod_sexo_alumno"],ENT_QUOTES)));
		$horario_alumno=mysqli_real_escape_string($con,(strip_tags($_POST["mod_horario_alumno"],ENT_QUOTES)));
		$sucursal_alumno=mysqli_real_escape_string($con,(strip_tags($_POST["mod_sucursal_alumno"],ENT_QUOTES)));
		$paralelo_alumno=mysqli_real_escape_string($con,(strip_tags($_POST["mod_nivel_alumno"],ENT_QUOTES)));
		$estado_alumno=mysqli_real_escape_string($con,(strip_tags($_POST["mod_estado_alumno"],ENT_QUOTES)));
		$serie_facturar=mysqli_real_escape_string($con,(strip_tags($_POST["mod_serie_facturar"],ENT_QUOTES)));
		$fecha_agregado=date("Y-m-d H:i:s");
		
		switch ($tipo_id) {
			case 1:
				$validacedula = validacedula($cedula_alumno);
				if ($validacedula == "cedula correcta") {
					$sql="UPDATE alumnos SET cedula_alumno='$cedula_alumno',nombres_apellidos='$nombre_alumno', fecha_nacimiento_alumno = '$fecha_nacimiento',fecha_ingreso_alumno = '$fecha_ingreso', horario_alumno = '$horario_alumno',
					sexo_alumno='$sexo_alumno', sucursal_alumno='$sucursal_alumno', paralelo_alumno='$paralelo_alumno',id_usuario=$id_usuario, fecha_agregado='$fecha_agregado',
					tipo_id='$tipo_id',estado_alumno='$estado_alumno', serie_facturar='".$serie_facturar."' WHERE id_alumno='".$id_alumno."'";
					$query_update = mysqli_query($con,$sql);
						if ($query_update){
							$messages[] = "Los datos han sido actualizado satisfactoriamente.";
							echo "<script>setTimeout(function () {location.reload()}, 60 * 20)</script>";
						} else{
							$errors []= "Lo siento algo ha salido mal intenta nuevamente.".mysqli_error($con);
						}
				}else{
					$errors []= $validacedula . mysqli_error($con);
				}
				break;			
				default:	
					 $sql="UPDATE alumnos SET cedula_alumno='$cedula_alumno',nombres_apellidos='$nombre_alumno', fecha_nacimiento_alumno = '$fecha_nacimiento',fecha_ingreso_alumno = '$fecha_ingreso', horario_alumno = '$horario_alumno',
					sexo_alumno='$sexo_alumno', sucursal_alumno='$sucursal_alumno', paralelo_alumno='$paralelo_alumno',id_usuario=$id_usuario, fecha_agregado='$fecha_agregado',
					tipo_id='$tipo_id',estado_alumno='$estado_alumno', serie_facturar='".$serie_facturar."' WHERE id_alumno='".$id_alumno."'";
					$query_update = mysqli_query($con,$sql);
						if ($query_update){
							$messages[] = "Los datos han sido actualizado satisfactoriamente.";
							echo "<script>setTimeout(function () {location.reload()}, 60 * 20)</script>";
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