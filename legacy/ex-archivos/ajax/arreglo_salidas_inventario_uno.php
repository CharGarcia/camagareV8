<?php
	/*Inicia validacion del lado del servidor*/
		include("../conexiones/conectalogin.php");
		$con = conenta_login();
		
		session_start();
		//$id_usuario = $_SESSION['id_usuario'];
		$ruc_empresa = $_SESSION['ruc_empresa'];
		ini_set('date.timezone','America/Guayaquil');
		$fecha_agregado=date("Y-m-d H:i:s");
		
		
//limpiar la nueva tabla
		$delete_query_arreglo=mysqli_query($con,"DELETE FROM inventarios_arreglo");
		
//para copiar de una base a otra
		$query_salidas=mysqli_query($con, "SELECT * FROM inventarios WHERE ruc_empresa='".$ruc_empresa."' and operacion='SALIDA' and cantidad_salida > 0");
		while ($row_salidas=mysqli_fetch_array($query_salidas)){
		$id_inventario=$row_salidas["id_inventario"];
		$id_producto=$row_salidas["id_producto"];
		$precio=$row_salidas["precio"];
		$cantidad_salida=$row_salidas["cantidad_salida"];
		$fecha_registro=$row_salidas["fecha_registro"];
		$referencia=$row_salidas["referencia"];
		$id_usuario=$row_salidas["id_usuario"];
		$id_medida=$row_salidas["id_medida"];
		$fecha_agregado=$row_salidas["fecha_agregado"];
		$tipo_registro=$row_salidas["tipo_registro"];
		$id_bodega=$row_salidas["id_bodega"];
		$codigo_producto=$row_salidas["codigo_producto"];
		$nombre_producto=$row_salidas["nombre_producto"];
		$query_new_insert= mysqli_query($con, "INSERT INTO inventarios_arreglo VALUES ($id_inventario, '$ruc_empresa', $id_producto,'$precio','0',$cantidad_salida,'$fecha_registro','0','$referencia', $id_usuario, $id_medida,'$fecha_agregado','$tipo_registro',$id_bodega,'SALIDA','$codigo_producto','$nombre_producto')");
		}



			if ($delete_query_arreglo && $query_new_insert){
				$messages[] = "Copiado a la nueva tabla.";	
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