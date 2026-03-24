<?php

	if (empty($_POST['serie'])) {
           $errors[] = "Ingrese nueva serie, ejemplo 001-001";
		}else if (strlen($_POST['serie'])>7 or strlen($_POST['serie'])<7 ) {
           $errors[] = "La serie debe contener 3 dígitos numerícos del la sucursal, un guión y 3 dígitos siguientes del punto de emisión, ejemplo 001-001";
		}else if (!is_numeric(substr($_POST['serie'],0,-4))) {
           $errors[] = "Los 3 primeros digitos de la serie debe contener números, ejemplo 001";
		}else if (!is_numeric(substr($_POST['serie'],5,3))) {
           $errors[] = "Los 3 últimos digitos de la serie debe contener números, ejemplo 001";
		}else if (empty($_POST['direccion_sucursal'])) {
           $errors[] = "Ingrese una dirección de la sucursal";
		}else if (empty($_POST['nombre_sucursal'])) {
           $errors[] = "Ingrese nombre de la sucursal";
		}else if (empty($_POST['telefono_sucursal'])) {
           $errors[] = "Ingrese teléfono de la sucursal";
        }else if ( (!empty($_POST['serie'])) && (!empty($_POST['direccion_sucursal'])) && (!empty($_POST['nombre_sucursal'])) && (!empty($_POST['telefono_sucursal']))
		){
					
		/* Connect To Database*/
		include("../conexiones/conectalogin.php");
		$con = conenta_login();
		session_start();
		$ruc_empresa = $_SESSION['ruc_empresa'];
		// escaping, additionally removing everything that could be (html/javascript-) code
		$serie=mysqli_real_escape_string($con,(strip_tags($_POST["serie"],ENT_QUOTES)));
		$direccion_sucursal=mysqli_real_escape_string($con,(strip_tags($_POST["direccion_sucursal"],ENT_QUOTES)));
		$nombre_sucursal=mysqli_real_escape_string($con,(strip_tags($_POST["nombre_sucursal"],ENT_QUOTES)));
		$telefono_sucursal=mysqli_real_escape_string($con,(strip_tags($_POST["telefono_sucursal"],ENT_QUOTES)));

		 $busca_sucursal = "SELECT * FROM sucursales WHERE ruc_empresa = '".$ruc_empresa."' and serie = '".$serie."'";
		 $result = $con->query($busca_sucursal);
		 $count = mysqli_num_rows($result);
				 if ($count == 1) {
					 $errors []= "La sucursal ya está registrada.".mysqli_error($con);
				 }else{
						$sql="INSERT INTO sucursales VALUES (null,'".$ruc_empresa."','".$serie."','".$direccion_sucursal."','".$telefono_sucursal."','','','1','1','1','1','1','2','".$nombre_sucursal."','1','1','1','1')";
						$query_new_insert = mysqli_query($con,$sql);
						if ($query_new_insert){
							$messages[] = "La sucursal ha sido ingresada satisfactoriamente.";
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