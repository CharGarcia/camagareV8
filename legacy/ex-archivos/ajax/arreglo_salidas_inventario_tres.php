<?php
	/*Inicia validacion del lado del servidor*/
		include("../conexiones/conectalogin.php");
		$con = conenta_login();
		
		session_start();
		//$id_usuario = $_SESSION['id_usuario'];
		$ruc_empresa = $_SESSION['ruc_empresa'];
		ini_set('date.timezone','America/Guayaquil');
		$fecha_agregado=date("Y-m-d H:i:s");
		
		
//para borrar los registros anteriores
		$query_salidas=mysqli_query($con, "SELECT * FROM inventarios_arreglo WHERE ruc_empresa='".$ruc_empresa."'");
		while ($row_salidas=mysqli_fetch_array($query_salidas)){
		$id_inventario=$row_salidas["id_inventario"];
		$delete_query_inventario=mysqli_query($con,"DELETE FROM inventarios WHERE EXISTS (SELECT id_inventario FROM inventarios_arreglo WHERE inventarios.id_inventario = inventarios_arreglo.id_inventario)");
		}
			

			if ($delete_query_inventario){
				$messages[] = "Registros anteriores eliminados.";	
			} else{
				$errors []= "Lo siento algo ha salido mal intenta nuevamente.".mysqli_error($con);
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