<?php
		/* Connect To Database*/
		include("../conexiones/conectalogin.php");
		include("../validadores/generador_codigo_unico.php");
		include("../validadores/valida_varios_mails.php");
	
		$con = conenta_login();
	if (empty($_POST['fecha_entrada_vehiculo'])) {
           $errors[] = "Ingrese fecha de ingreso del vehículo.";
		}else if (!date($_POST['fecha_entrada_vehiculo'])) {
           $errors[] = "Ingrese fecha correcta";
		}else if (empty($_POST['hora_entrada'])) {
           $errors[] = "Ingrese hora de entrada del vehículo.";
		}else if (empty($_POST['placa'])) {
           $errors[] = "Ingrese placa del vehículo.";
		}else if (empty($_POST['marca'])) {
           $errors[] = "Ingrese marca del vehículo.";
		}else if (empty($_POST['anio'])) {
           $errors[] = "Ingrese año del vehículo.";
		}else if (!is_numeric($_POST['anio'])) {
           $errors[] = "Ingrese año correcto del vehículo.";
		}else if (empty($_POST['propietario'])) {
           $errors[] = "Ingrese nombre del propietario del vehículo.";
		}else if (empty($_POST['chasis'])) {
           $errors[] = "Ingrese número de chasis del vehículo.";
		}else if (empty($_POST['usuario'])) {
				   $errors[] = "Ingrese nombre del usuario quien esta a cargo del vehículo.";
		}else if (empty($_POST['contacto'])) {
				   $errors[] = "Ingrese número de teléfono del contacto del vehículo.";
		}else if (empty($_POST['correo_usuario']) && validar_mails($_POST['email'])=='error') {
				   $errors[] = "Ingrese mail correcto del contacto del vehículo.";
		}else if (empty($_POST['observaciones'])) {
				   $errors[] = "Ingrese detalle de observaciones del vehículo al ingresar al taller.";		   
        } else if (!empty($_POST['fecha_entrada_vehiculo']) 
		&& !empty($_POST['hora_entrada']) 
		&& !empty($_POST['placa']) 
		&& !empty($_POST['marca'])
		&& !empty($_POST['anio'])
		&& !empty($_POST['chasis'])
		&& !empty($_POST['usuario'])
		&& !empty($_POST['propietario'])
		&& !empty($_POST['contacto'])
		&& !empty($_POST['observaciones']))
		{
			ini_set('date.timezone','America/Guayaquil');
			$fecha_entrada_vehiculo=date('Y-m-d H:i:s', strtotime($_POST['fecha_entrada_vehiculo']));
			$hora_entrada=date('H:i:s', strtotime($_POST['hora_entrada']));
			$placa=mysqli_real_escape_string($con,(strip_tags($_POST["placa"],ENT_QUOTES)));
			$marca=mysqli_real_escape_string($con,(strip_tags($_POST["marca"],ENT_QUOTES)));
			$anio=mysqli_real_escape_string($con,(strip_tags($_POST["anio"],ENT_QUOTES)));
			$chasis=mysqli_real_escape_string($con,(strip_tags($_POST["chasis"],ENT_QUOTES)));
			$usuario=mysqli_real_escape_string($con,(strip_tags($_POST["usuario"],ENT_QUOTES)));
			$correo_usuario=mysqli_real_escape_string($con,(strip_tags($_POST["correo_usuario"],ENT_QUOTES)));
			$propietario=mysqli_real_escape_string($con,(strip_tags($_POST["propietario"],ENT_QUOTES)));			
			$contacto=mysqli_real_escape_string($con,(strip_tags($_POST["contacto"],ENT_QUOTES)));
			$estado=mysqli_real_escape_string($con,(strip_tags($_POST["estado"],ENT_QUOTES)));
			$observaciones_entrada=mysqli_real_escape_string($con,(strip_tags($_POST["observaciones"],ENT_QUOTES)));
			session_start();
			$id_usuario = $_SESSION['id_usuario'];
			$ruc_empresa = $_SESSION['ruc_empresa'];
			$codigo_unico=codigo_unico(20);
			$fecha_registro=date("Y-m-d H:i:s");
			$consulta_ultima_orden=mysqli_query($con, "SELECT max(numero_orden) as ultimo FROM encabezado_mecanica WHERE ruc_empresa='".$ruc_empresa."'");
			$row_ultimo=mysqli_fetch_array($consulta_ultima_orden);
			$siguiente_orden=$row_ultimo['ultimo']+1;
		
			$guarda_encabezado_mecanica=mysqli_query($con, "INSERT INTO encabezado_mecanica VALUES (null,'".$ruc_empresa."','".$siguiente_orden."','','".$id_usuario."','".$usuario."','".$contacto."','".$fecha_entrada_vehiculo."','".$hora_entrada."','','','".$fecha_registro."','".$codigo_unico."','".$correo_usuario."', null,'','".$estado."')");
			$guarda_vehiculo=mysqli_query($con, "INSERT INTO vehiculos VALUES (null,'".$ruc_empresa."','".$marca."','".$placa."','".$chasis."','".$anio."','".$propietario."','".$codigo_unico."')");
			$guarda_observaciones=mysqli_query($con, "INSERT INTO observaciones_mecanica VALUES (null,'".$ruc_empresa."','".$codigo_unico."','Observaciones de entrada','".$observaciones_entrada."')");

			//echo mysqli_error($con);
				if ($guarda_encabezado_mecanica && $guarda_vehiculo){
					echo "<script>
					$.notify('nueva orden guardada con éxito','success');
					setTimeout(function (){location.href ='../modulos/orden_mecanica.php'}, 1000);
					</script>";
					} else{
						echo "<script>
					$.notify('Lo siento algo ha salido mal intenta nuevamente','error');
					</script>";	
					}
		}else{
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
