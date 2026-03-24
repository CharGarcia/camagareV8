<?php
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
	if (empty($_POST['id_cliente'])) {
           $errors[] = "Seleccione un cliente";
        } else if (!empty($_POST['id_cliente']))
		{
		/* Connect To Database*/
		include("../conexiones/conectalogin.php");
		$con = conenta_login();
		// escaping, additionally removing everything that could be (html/javascript-) code
		$codigo_cliente=mysqli_real_escape_string($con,(strip_tags($_POST["id_cliente"],ENT_QUOTES)));
		ini_set('date.timezone','America/Guayaquil'); 
		$fecha_agregado=date("Y-m-d H:i:s");
				
		$sql_cliente="INSERT INTO clientes_facturas_programadas VALUES (null,'".$ruc_empresa."','".$codigo_cliente."','".$fecha_agregado."', '".$id_usuario."')";
		$query_new_insert = mysqli_query($con,$sql_cliente);
		if ($query_new_insert){
							$messages[] = "El nuevo cliente ha sido agregado satisfactoriamente, proceda a ingresar los detalles de la factura.";
						} else{
							$errors []= "Lo siento algo ha salido mal intenta nuevamente.".mysqli_error($con);
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