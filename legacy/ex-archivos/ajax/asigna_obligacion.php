<?php
echo $_POST['id_obligacion'];

	if (empty($_POST['id_obligacion'])) {
           $errors[] = "ID vacío";
		} else if (
			!empty($_POST['id_obligacion'])
		){
		/* Connect To Database*/
		include("../conexiones/conectalogin.php");
		$con = conenta_login();
		// escaping, additionally removing everything that could be (html/javascript-) code
		$codigo=mysqli_real_escape_string($con,(strip_tags($_POST["mod_codigo"],ENT_QUOTES)));
		$nombre=mysqli_real_escape_string($con,(strip_tags($_POST["mod_nombre"],ENT_QUOTES)));
		$estado=intval($_POST['mod_estado']);
		$precio_venta=floatval($_POST['mod_precio']);
		$id_producto=$_POST['mod_id'];
		$sql="UPDATE productos_servicios SET codigo_producto='".$codigo."', nombre_producto='".$nombre."', precio_producto='".$precio_venta."' WHERE id='".$id_producto."'";
		$query_update = mysqli_query($con,$sql);
			if ($query_update){
				$messages[] = "Producto/servicio ha sido actualizado satisfactoriamente.";
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