<?php
	if (empty($_POST['total_factura_pago'])) {
           $errors[] = "Seleccione una factura.";
		} else if (empty($_POST['serie_factura_pago'])){
			$errors[] = "Seleccione una factura.";
		} else if (empty($_POST['secuencial_factura_pago'])){
			$errors[] = "Seleccione una factura.";
		} else if (empty($_POST['total_pagos_agregados'])){
			$errors[] = "Agregue formas de pago.";
		} else if ($_POST['total_factura_pago'] != $_POST['total_pagos_agregados']){
			$errors[] = "El total de la factura no es igual al total de pagos agregados.";
        } else if (!empty($_POST['total_factura_pago']) && !empty($_POST['serie_factura_pago']) && !empty($_POST['secuencial_factura_pago'])){

		include("../conexiones/conectalogin.php");
		$con = conenta_login();
		session_start();
		$ruc_empresa = $_SESSION['ruc_empresa'];
		
		$serie_factura_pago=mysqli_real_escape_string($con,(strip_tags($_POST["serie_factura_pago"],ENT_QUOTES)));
		$secuencial_factura_pago=mysqli_real_escape_string($con,(strip_tags($_POST["secuencial_factura_pago"],ENT_QUOTES)));
		
		$delete_pago=mysqli_query($con,"DELETE FROM formas_pago_ventas WHERE ruc_empresa= '".$ruc_empresa."' and serie_factura ='".$serie_factura_pago."' and secuencial_factura='".$secuencial_factura_pago."'");

		
		$busca_pagos_tmp = mysqli_query($con, "SELECT * FROM pago_factura_tmp WHERE ruc_empresa = '".$ruc_empresa."' and serie = '".$serie_factura_pago."' and secuencial = '".$secuencial_factura_pago."'");
											
		while ($row_detalle_pagos=mysqli_fetch_array($busca_pagos_tmp)){
		$id_forma_pago=$row_detalle_pagos['codigo_forma_pago'];
		$valor_pago=$row_detalle_pagos['valor'];
		$query_guarda_detalle_pagos_factura = mysqli_query($con, "INSERT INTO formas_pago_ventas VALUES (null, '".$ruc_empresa."','".$serie_factura_pago."','".$secuencial_factura_pago."','".$id_forma_pago."','".$valor_pago."')");
		}
		
			if ($query_guarda_detalle_pagos_factura){
				echo "<script>
				$.notify('Guardado nuevas formas de pago.','success');
				setTimeout(function () {location.reload()}, 60 * 20); 
				</script>";	
			} else{
				$errors []= "Lo siento algo ha salido mal intenta nuevamente.".mysqli_error($con);
			}
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