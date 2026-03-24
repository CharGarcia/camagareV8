<?php
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];


	if (empty($_POST['serie_sucursal'])) {
           $errors[] = "Seleccione serie";
		}else if (empty($_POST['controla_inventario'])) {
           $errors[] = "Seleccione si quiere que dependa del inventario o no";		   
        } else if (!empty($_POST['serie_sucursal'])&& !empty($_POST['controla_inventario'])){
		/* Connect To Database*/
		include("../conexiones/conectalogin.php");
		$con = conenta_login();
		// escaping, additionally removing everything that could be (html/javascript-) code
		$serie_sucursal=mysqli_real_escape_string($con,(strip_tags($_POST["serie_sucursal"],ENT_QUOTES)));
		$controla_inventario=mysqli_real_escape_string($con,(strip_tags($_POST["controla_inventario"],ENT_QUOTES)));
			
	$elimina_configuracion = mysqli_query($con, "DELETE FROM configuracion_inventarios WHERE ruc_empresa='".$ruc_empresa."' and serie_sucursal='".$serie_sucursal."'");
	$guarda_configuracion = mysqli_query($con, "INSERT INTO configuracion_inventarios VALUES (null, '".$ruc_empresa."', '".$serie_sucursal."', '".$controla_inventario."')");

	if ($elimina_configuracion && $guarda_configuracion){
				$messages[] = "Configurado satisfactoriamente.";
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