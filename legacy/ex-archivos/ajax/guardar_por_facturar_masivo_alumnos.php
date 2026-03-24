<?php
		/* Connect To Database*/
		include("../conexiones/conectalogin.php");
		$con = conenta_login();
	if (empty($_POST['valor_masivo'])) {
           $errors[] = "Ingrese valor.";
		}else if (empty($_POST['periodo_masivo'])) {
           $errors[] = "Seleccione periodo";
		}else if (empty($_POST['id_producto'])) {
           $errors[] = "Seleccione producto";
		}else if (empty($_POST['columna'])) {
           $errors[] = "Seleccione columna.";
		}else if (empty($_POST['dato'])) {
           $errors[] = "Ingrese dato referente a la columna seleccionada.";
        } else if (!empty($_POST['valor_masivo']) && !empty($_POST['periodo_masivo'])  && !empty($_POST['columna']) 
		&& !empty($_POST['dato']) && !empty($_POST['id_producto']))
		{
			// escaping, additionally removing everything that could be (html/javascript-) code
			$valor_masivo=mysqli_real_escape_string($con,(strip_tags($_POST["valor_masivo"],ENT_QUOTES)));
			$periodo_masivo=mysqli_real_escape_string($con,(strip_tags($_POST["periodo_masivo"],ENT_QUOTES)));
			$columna=mysqli_real_escape_string($con,(strip_tags($_POST["columna"],ENT_QUOTES)));
			$dato=mysqli_real_escape_string($con,(strip_tags($_POST["dato"],ENT_QUOTES)));
			$producto=mysqli_real_escape_string($con,(strip_tags($_POST["id_producto"],ENT_QUOTES)));
			$fecha_registro=date("Y-m-d H:i:s");
			session_start();
			$id_usuario = $_SESSION['id_usuario'];
			$ruc_empresa = $_SESSION['ruc_empresa'];
			
			//seleccionar todos los alumnos donde la columna seleccionada sea igual al dato seleccionado
			$busca_alumnos = mysqli_query($con,"SELECT * FROM alumnos WHERE ruc_empresa = '$ruc_empresa' and $columna = $dato ");
			
			while ($row=mysqli_fetch_array($busca_alumnos)){
					$id_alumno=$row["id_alumno"];
					$nombre_alumno=$row["nombres_alumno"];
					$apellido_alumno=$row["apellidos_alumno"];
			//guarda en detalle por facturar
					$guarda_detalle_por_facturar=mysqli_query($con, "INSERT INTO detalle_por_facturar VALUES (null, '$ruc_empresa',$id_alumno,$producto,'1',$valor_masivo,'$periodo_masivo','$fecha_registro',$id_usuario)");
				}
			
				
					if ($guarda_detalle_por_facturar){
						$messages[] = "Datos guardados satisfactoriamente.";
						} else
							{
								$errors []= "Lo siento algo ha salido mal intenta nuevamente.".mysqli_error($con);
							}

		} else 
			{
			$errors []= "Error desconocido.";
			}
		if (isset($errors))
			{
			?>
			<div class="alert alert-danger" role="alert">
				<button type="button" class="close" data-dismiss="alert">&times;</button>
				<strong>Atención! </strong> 
					<?php
						foreach ($errors as $error) 
						{
							echo $error;
						}
					?>
			</div>
			<?php
			}
			if (isset($messages))
			{
				
			?>
			<div class="alert alert-success" role="alert">
				<button type="button" class="close" data-dismiss="alert">&times;</button>
				<strong>¡Bien hecho! </strong>
					<?php
						foreach ($messages as $message) 
						{
							echo $message;
						}
					?>
			</div>
			<?php
			}
			?>