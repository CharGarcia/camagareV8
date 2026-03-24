<?php
	/*Inicia validacion del lado del servidor*/
	if (empty($_POST['codigo'])) {
           $errors[] = "Código esta vacío";
        } else if (empty($_POST['nombre'])){
			$errors[] = "Nombre del producto esta vacío";
		} else if (empty($_POST['ruc_empresa'])){
			$errors[] = "Vuelva a seleccionar una empresa";
		} else if (empty($_POST['precio'])){
			$errors[] = "Precio de venta esta vacío";
		} else if (!is_numeric($_POST['precio'])){
			$errors[] = "Precio de venta no es valor";
		} else if ($_POST['tipo']==""){
			$errors[] = "Seleccione el tipo de producción";
		} else if ($_POST['iva']==""){
			$errors[] = "Seleccione una tarifa de IVA";
		} else if (
			!empty($_POST['codigo']) &&
			!empty($_POST['nombre']) &&
			!empty($_POST['ruc_empresa']) &&
			!empty($_POST['precio']) &&
			$_POST['tipo']!="" &&
			$_POST['iva']!=""
		){
		/* Connect To Database*/
		include("../conexiones/conectalogin.php");
		$con = conenta_login();
		// escaping, additionally removing everything that could be (html/javascript-) code
		$ruc_empresa=mysqli_real_escape_string($con,(strip_tags($_POST["ruc_empresa"],ENT_QUOTES)));
		$codigo=mysqli_real_escape_string($con,(strip_tags($_POST["codigo"],ENT_QUOTES)));
		
		// chequear si producto ya existe
                $sql = "SELECT * FROM productos_servicios WHERE ruc_empresa = '" . $ruc_empresa . "' and codigo_producto = '" . $codigo . "';";
                $query_check_producto = mysqli_query($con,$sql);
				$query_check=mysqli_num_rows($query_check_producto);
                if ($query_check == 1) {
                    $errors[] = "Lo sentimos , el producto o servicio ya existe.";
                } else {
		$nombre=mysqli_real_escape_string($con,(strip_tags($_POST["nombre"],ENT_QUOTES)));
		$descripcion=mysqli_real_escape_string($con,(strip_tags($_POST["descripcion"],ENT_QUOTES)));
		$precio_venta=floatval($_POST['precio']);
		$tipo=mysqli_real_escape_string($con,(strip_tags($_POST["tipo"],ENT_QUOTES)));
		$tarifa_iva=mysqli_real_escape_string($con,(strip_tags($_POST["iva"],ENT_QUOTES)));
		$tarifa_ice=mysqli_real_escape_string($con,(strip_tags($_POST["ice"],ENT_QUOTES)));
		$tarifa_botellas=mysqli_real_escape_string($con,(strip_tags($_POST["botellas"],ENT_QUOTES)));
		$fecha_agregado=date("Y-m-d H:i:s");
		$sql="INSERT INTO productos_servicios (id, ruc_empresa, codigo_producto, nombre_producto, descripcion_producto, precio_producto, tipo_produccion, tarifa_iva, tarifa_ice, tarifa_botellas, fecha_agregado) VALUES (NULL, '$ruc_empresa', '$codigo','$nombre','$descripcion','$precio_venta','$tipo','$tarifa_iva', '$tarifa_ice', '$tarifa_botellas','$fecha_agregado')";
		$query_new_insert = mysqli_query($con,$sql);
			if ($query_new_insert){
				$messages[] = "Producto o servicio ha sido ingresado satisfactoriamente.";	
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