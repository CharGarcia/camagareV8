<?php
	/*Inicia validacion del lado del servidor*/
		include("../conexiones/conectalogin.php");
		$con = conenta_login();
		
		session_start();
		//$id_usuario = $_SESSION['id_usuario'];
		$ruc_empresa = $_SESSION['ruc_empresa'];
		ini_set('date.timezone','America/Guayaquil');
		$fecha_agregado=date("Y-m-d H:i:s");
		
		
//para corregir las nuevas fechas

		$query_registro=mysqli_query($con, "SELECT * FROM inventarios_arreglo WHERE ruc_empresa='".$ruc_empresa."'");
		while ($row_salidas=mysqli_fetch_array($query_registro)){
		$id_producto=$row_salidas["id_producto"];
		$bodega=$row_salidas["id_bodega"];
		$cantidad_salida_registrar=$row_salidas["cantidad_salida"];
		$precio_producto=$row_salidas["precio"];
		$fecha_salida=$row_salidas["fecha_registro"];
		$referencia_registrar=$row_salidas["referencia"];
		$id_usuario_registrar=$row_salidas["id_usuario"];
		$unidad_medida=$row_salidas["id_medida"];
		$fecha_agregado=$row_salidas["fecha_agregado"];
		$tipo_registro=$row_salidas["tipo_registro"];
		$codigo_producto=$row_salidas["codigo_producto"];
		$nombre_producto=$row_salidas["nombre_producto"];
		$saldo_cantidad_salida=$cantidad_salida_registrar;
		
			//traer todas las fechas de caducidad de ese producto de entradas de inventarios
			$query_fechas_entradas=mysqli_query($con, "SELECT * FROM inventarios WHERE id_producto='".$id_producto."' and id_bodega='".$bodega."' and operacion = 'ENTRADA' order by fecha_vencimiento asc");
			while($fechas_caducidad = mysqli_fetch_array($query_fechas_entradas)){
			$fecha_caducidad_mas_antigua = $fechas_caducidad['fecha_vencimiento'];
			//traer el saldo de ese producto y esa fecha_agregado
				if ($saldo_cantidad_salida>0){	
					$query_saldo_producto_entrada=mysqli_query($con, "SELECT * FROM inventarios WHERE id_producto='".$id_producto."' and fecha_vencimiento like '".$fecha_caducidad_mas_antigua."' ");
					$row_saldo_producto_entrada=mysqli_fetch_array($query_saldo_producto_entrada);
					$saldo_producto_entrada=$row_saldo_producto_entrada["cantidad_entrada"];
					
					$query_saldo_producto_salida=mysqli_query($con, "SELECT sum(cantidad_salida)as suma_salida FROM inventarios WHERE id_producto='".$id_producto."' and fecha_vencimiento like '".$fecha_caducidad_mas_antigua."' ");
					$row_saldo_producto_salida=mysqli_fetch_array($query_saldo_producto_salida);
					$saldo_producto_salida=$row_saldo_producto_salida["suma_salida"];
					
					$saldo_producto=$saldo_producto_entrada-$saldo_producto_salida;

					if($saldo_cantidad_salida>$saldo_producto){
						$query_new_insert_corregido= mysqli_query($con, "INSERT INTO inventarios VALUES (NULL, '$ruc_empresa', $id_producto,'$precio_producto',0,$saldo_producto,'$fecha_salida','".$fecha_caducidad_mas_antigua."','$referencia_registrar', $id_usuario_registrar, '$unidad_medida','$fecha_agregado','$tipo_registro',$bodega,'SALIDA','$codigo_producto','$nombre_producto')");
						}else{
						$query_new_insert_corregido= mysqli_query($con, "INSERT INTO inventarios VALUES (NULL, '$ruc_empresa', $id_producto,'$precio_producto',0,$saldo_cantidad_salida,'$fecha_salida','".$fecha_caducidad_mas_antigua."','$referencia_registrar', $id_usuario_registrar, '$unidad_medida','$fecha_agregado','$tipo_registro',$bodega,'SALIDA','$codigo_producto','$nombre_producto')");			
					}
					
				}
				$saldo_cantidad_salida=$saldo_cantidad_salida-$saldo_producto;	
			
			}
		
		}

			if ($query_new_insert_corregido){
				$messages[] = "Registros corregidos.";	
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