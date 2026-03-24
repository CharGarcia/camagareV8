<?php
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
	if (empty($_POST['cliente'])) {
           $errors[] = "Seleccione un cliente";
		} else if (empty($_POST['periodo'])){
		   $errors[] = "Seleccione período de emisión";
		} else if (empty($_POST['productouno'])){
		   $errors[] = "Seleccione un producto";
		} else if (empty($_POST['cantidaduno'])){
		   $errors[] = "Ingrese cantidad";
		} else if (empty($_POST['preciouno'])){
		   $errors[] = "Ingrese precio";
		} else if (!is_numeric($_POST['cantidaduno'])){
		   $errors[] = "Solo se aceptan valores númericos en cantidad uno";
		} else if (($_POST['cantidaduno'])<=0){
		   $errors[] = "La cantidad debe ser mayor que cero";
		} else if (!is_numeric($_POST['preciouno'])){
		   $errors[] = "Solo se aceptan valores númericos en precio uno";
		} else if (($_POST['preciouno'])<=0){
		   $errors[] = "El precio debe ser mayor a cero";
        } else if (!empty($_POST['cliente']) && !empty($_POST['periodo']) && !empty($_POST['productouno']) && !empty($_POST['cantidaduno']) && !empty($_POST['preciouno']) )
		{
		/* Connect To Database*/
		include("../conexiones/conectalogin.php");
		$con = conenta_login();
		// escaping, additionally removing everything that could be (html/javascript-) code
		$codigo_cliente=mysqli_real_escape_string($con,(strip_tags($_POST["cliente"],ENT_QUOTES)));
		$periodo_fb=mysqli_real_escape_string($con,(strip_tags($_POST["periodo"],ENT_QUOTES)));
		$fecha_agregado=date("Y-m-d H:i:s");
		$detalle_fb=mysqli_real_escape_string($con,(strip_tags($_POST["detalle"],ENT_QUOTES)));
		$codigo_producto_uno=mysqli_real_escape_string($con,(strip_tags($_POST["productouno"],ENT_QUOTES)));
		$codigo_producto_dos=mysqli_real_escape_string($con,(strip_tags($_POST["productodos"],ENT_QUOTES)));
		$codigo_producto_tres=mysqli_real_escape_string($con,(strip_tags($_POST["productotres"],ENT_QUOTES)));
		$cantidad_producto_uno=mysqli_real_escape_string($con,(strip_tags($_POST["cantidaduno"],ENT_QUOTES)));
		$cantidad_producto_dos=mysqli_real_escape_string($con,(strip_tags($_POST["cantidaddos"],ENT_QUOTES)));
		$cantidad_producto_tres=mysqli_real_escape_string($con,(strip_tags($_POST["cantidadtres"],ENT_QUOTES)));
		$precio_producto_uno=mysqli_real_escape_string($con,(strip_tags($_POST["preciouno"],ENT_QUOTES)));
		$precio_producto_dos=mysqli_real_escape_string($con,(strip_tags($_POST["preciodos"],ENT_QUOTES)));
		$precio_producto_tres=mysqli_real_escape_string($con,(strip_tags($_POST["preciotres"],ENT_QUOTES)));
		//number_format($precio_total,2)
			
		$sql_cliente="INSERT INTO facturas_en_bloque VALUES (null,'$ruc_empresa',$codigo_cliente,'$periodo_fb','$fecha_agregado','$detalle_fb', $id_usuario)";
		$query_new_insert = mysqli_query($con,$sql_cliente);
		//consultar el ultimo id de la facturas en bloque con el usuario
		$sql_id_fb = "select max(id_fb) as id_fb from facturas_en_bloque where id_usuario = $id_usuario and ruc_empresa = $ruc_empresa";
		$resultado_de_la_busqueda = $con->query($sql_id_fb);
		$ultimo_id_encontrado = mysqli_fetch_assoc($resultado_de_la_busqueda);	
		$id_facturas_en_bloque = $ultimo_id_encontrado['id_fb'];
		$guarda_detalle_fb_uno=mysqli_query($con, "INSERT INTO detalle_facturas_en_bloque VALUES (null, $id_facturas_en_bloque,'$codigo_cliente','$periodo_fb','$codigo_producto_uno','$cantidad_producto_uno','$precio_producto_uno')");
		$guarda_detalle_fb_dos=mysqli_query($con, "INSERT INTO detalle_facturas_en_bloque VALUES (null, $id_facturas_en_bloque,'$codigo_cliente','$periodo_fb','$codigo_producto_dos','$cantidad_producto_dos','$precio_producto_dos')");
		$guarda_detalle_fb_tres=mysqli_query($con, "INSERT INTO detalle_facturas_en_bloque VALUES (null, $id_facturas_en_bloque,'$codigo_cliente','$periodo_fb','$codigo_producto_tres','$cantidad_producto_tres','$precio_producto_tres')");

		if ($query_new_insert && $guarda_detalle_fb_uno && $guarda_detalle_fb_dos && $guarda_detalle_fb_tres){
			//elimina los registros de detalle que no tengas datos de productos
			$elimina_vacios=mysqli_query($con, "DELETE FROM detalle_facturas_en_bloque WHERE codigo_producto = '' and cantidad_producto = 0 and precio_producto = 0 ");
			//
							$messages[] = "El nuevo cliente y detalle de factura ha sido ingresado satisfactoriamente.";
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