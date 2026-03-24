<?php

	if (empty($_POST['nuevo_codigo_cuenta'])) {
           $errors[] = "Seleccione una cuenta para generar un nuevo código";
		}else if (empty($_POST['nuevo_nombre_cuenta'])) {
           $errors[] = "Ingrese un nombre para la cuenta.";
        }else if ( (!empty($_POST['nuevo_codigo_cuenta'])) && (!empty($_POST['nuevo_nombre_cuenta']))){
					
		/* Connect To Database*/
		include("../conexiones/conectalogin.php");
		include("../validadores/generador_codigo_unico.php");
		$con = conenta_login();
		$codigo_unico=codigo_unico(20);
		session_start();
		$ruc_empresa = $_SESSION['ruc_empresa'];
		$id_usuario = $_SESSION['id_usuario'];
		// escaping, additionally removing everything that could be (html/javascript-) code
		$codigo_cuenta=mysqli_real_escape_string($con,(strip_tags($_POST["nuevo_codigo_cuenta"],ENT_QUOTES)));
		$nombre_cuenta=mysqli_real_escape_string($con,(strip_tags($_POST["nuevo_nombre_cuenta"],ENT_QUOTES)));
		$nivel_cuenta=mysqli_real_escape_string($con,(strip_tags($_POST["nuevo_nivel_cuenta"],ENT_QUOTES)));
		$codigo_sri=mysqli_real_escape_string($con,(strip_tags($_POST["nuevo_codigo_sri"],ENT_QUOTES)));
		$codigo_supercias=mysqli_real_escape_string($con,(strip_tags($_POST["nuevo_codigo_supercias"],ENT_QUOTES)));
		ini_set('date.timezone','America/Guayaquil');
		$fecha_registro=date('Y-m-d H:i:s');

		$buscar_cuenta=mysqli_query($con, "SELECT * FROM plan_cuentas WHERE ruc_empresa='".$ruc_empresa."' and codigo_cuenta='".$codigo_cuenta."'");
		$contar_cuentas_registradas=mysqli_num_rows($buscar_cuenta);
		if ($contar_cuentas_registradas>0){
			$errors []= "El código de cuenta ya está registrado, intente de nuevo.";
		}else{
		$guardar_cuenta=mysqli_query($con, "INSERT INTO plan_cuentas VALUES (null,'".$codigo_cuenta."','".$nivel_cuenta."','".$nombre_cuenta."','".$codigo_sri."','".$codigo_supercias."','".$ruc_empresa."','".$id_usuario."','".$fecha_registro."','".$codigo_unico."')");
			if ($guardar_cuenta){
				echo "<script>
				$.notify('La cuenta ha sido ingresada satisfactoriamente','success');
				//setTimeout(function (){location.href ='../modulos/plan_de_cuentas.php'}, 1000);
				$('.close:visible').click();
				</script>";
			} else{
				$errors []= "Lo siento algo ha salido mal intenta nuevamente.".mysqli_error($con);
			}
		}
		}else {
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