<?php
	/*Inicia validacion del lado del servidor*/
	include_once("../clases/saldo_producto_y_conversion.php");
	if (empty($_POST['enviar_inventario'])) {
		echo "<script>$.notify('Seleccione el (los) items que desea guardar.','error')</script>";
		} else if (
			!empty($_POST['enviar_inventario'])){
		/* Connect To Database*/
		include("../conexiones/conectalogin.php");
		$con = conenta_login();
		ini_set('date.timezone','America/Guayaquil');
		$registros=$_POST["id_registro"];
		$cheked=$_POST["enviar_inventario"];
		$id_producto=$_POST["id_producto"];
		$cantidad_producto=$_POST["cantidad_producto"];
		$saldo_producto=$_POST["saldo_producto"];
		$unidad_medida=$_POST["unidad_medida"];
		$precio_producto=$_POST["precio_producto"];
		$caducidad=$_POST["caducidad"];
		$lote=$_POST["lote"];
		$producto_compra=$_POST["producto_compra"];
		$proveedor=$_POST["proveedor"];
		$numero_documento=$_POST["numero_documento"];
		$bodega=$_POST["bodega"];
		$tipo_inventario="2";
		
		$codigo_producto=$_POST["codigo_producto"];
		$nombre_producto=$_POST["mi_producto"];
		$codigo_compra=$_POST["codigo_compra"];
		$codigo_registro=$_POST["codigo_registro"];
		$saldo_producto_y_conversion = new saldo_producto_y_conversion();

		session_start();
		$id_usuario = $_SESSION['id_usuario'];
		$ruc_empresa = $_SESSION['ruc_empresa'];
		$fecha_agregado=date("Y-m-d H:i:s");
		
		foreach ($registros as $valor ){
			if (isset($cheked[$valor]) == true){
				if (empty($id_producto[$valor])){
				$contador_producto[]=1;
				}else{
				$contador_producto[]=0;
				}
				
				if (empty($cantidad_producto[$valor])){
				$contador_cantidad[]=1;
				}else{
				$contador_cantidad[]=0;
				}
				
				if (!is_numeric($cantidad_producto[$valor])){
				$contador_cantidad_numerico[]=1;
				}else{
				$contador_cantidad_numerico[]=0;
				}
				
				if ($cantidad_producto[$valor]>$saldo_producto[$valor]){
				$contador_cantidad_mayor[]=0;
				}else{
				$contador_cantidad_mayor[]=0;
				}

				if (empty($unidad_medida[$valor])){
				$contador_unidad_medida[]=1;
				}else{
				$contador_unidad_medida[]=0;
				}
				
				if (empty($caducidad[$valor])){
				$contador_caducidad[]=1;
				}else{
				$contador_caducidad[]=0;
				}
				
				if (!date($caducidad[$valor])){
				$contador_caducidad_fecha[]=1;
				}else{
				$contador_caducidad_fecha[]=0;
				}
				
				if (empty($lote[$valor])){
				$contador_lote[]=1;
				}else{
				$contador_lote[]=0;
				}
			}//fin del if
		}//fin foreach primero
		
		if (array_sum($contador_producto)>0){
			echo "<script>$.notify('Ingrese producto en la fila marcada.','error')</script>";
			}
			
		if (array_sum($contador_cantidad)>0){
			echo "<script>$.notify('Ingrese cantidad en la fila marcada.','error')</script>";	
			}
			
		if (array_sum($contador_cantidad_numerico)>0){
			echo "<script>$.notify('La cantidad ingresada en la fila marcada no es un número.','error')</script>";	
			}
		
		if (array_sum($contador_cantidad_mayor)>0){
			echo "<script>$.notify('La cantidad ingresada es mayor al saldo disponible en la fila marcada.','error')</script>";	
			}
			
			
		if (array_sum($contador_unidad_medida)>0){
			echo "<script>$.notify('Seleccione una anidad de medida en la fila marcada.','error')</script>";	
			}
			
		if (array_sum($contador_caducidad)>0){
			echo "<script>$.notify('Ingrese fecha de caducidad en la fila marcada.','error')</script>";	
			}	
			
		if (array_sum($contador_caducidad_fecha)>0){
			echo "<script>$.notify('Ingrese fecha de caducidad correcta en la fila marcada.','error')</script>";		
			}	
			
		if (array_sum($contador_lote)>0){
			echo "<script>$.notify('Ingrese lote en la fila marcada.','error')</script>";	
			}	
			
		if ((array_sum($contador_lote)+array_sum($contador_caducidad_fecha)+array_sum($contador_unidad_medida)+array_sum($contador_cantidad_mayor)+array_sum($contador_cantidad_numerico)+array_sum($contador_cantidad)+array_sum($contador_producto))==0){	
	
			foreach ($registros as $valor ){
				if (isset($cheked[$valor]) == true){
						//para saber a que sucursal enviar el inventario
						$query_ruc_sucursal=mysqli_query($con,"select * from bodega WHERE id_bodega='".$bodega[$valor]."' ");
						$row_ruc_bodega=mysqli_fetch_array($query_ruc_sucursal);
						$ruc_sucursal = $row_ruc_bodega['ruc_empresa'];
					
					//convertir en la medida del producto
					$query_medida_producto=mysqli_query($con,"select * from productos_servicios WHERE id='".$id_producto[$valor]."' ");
					$row_medida_producto=mysqli_fetch_array($query_medida_producto);
					$id_medida_producto = $row_medida_producto['id_unidad_medida'];
					$cantidad_converida= $saldo_producto_y_conversion->conversion($unidad_medida[$valor], $id_medida_producto, $id_producto[$valor], '0', $cantidad_producto[$valor], $con,'saldo');	
					$query_inventario=mysqli_query($con,"INSERT INTO inventarios VALUES (NULL, '".$ruc_sucursal."', '".$id_producto[$valor]."',0,'".$cantidad_converida."','0','".$fecha_agregado."','".$caducidad[$valor]."','".$proveedor[$valor]." No. ".$numero_documento[$valor]." Producto original: ". $producto_compra[$valor]."','".$id_usuario."','".$id_medida_producto."','".$fecha_agregado."','A','".$bodega[$valor]."','ENTRADA','".$codigo_producto[$valor]."','".$nombre_producto[$valor]."','".$precio_producto[$valor]."','OK','".$lote[$valor]."','".$codigo_registro[$valor]."')");		
					$query_cuerpo_compra=mysqli_query($con,"UPDATE cuerpo_compra SET cantidad_inv=cantidad_inv + '".$cantidad_converida."' WHERE id_cuerpo_compra='".$codigo_registro[$valor]."'");
					if ($query_inventario && $query_cuerpo_compra){
						echo "<script>
						$.notify('El producto $nombre_producto[$valor] ha sido ingresado satisfactoriamente al inventario.','success');
						setTimeout(function () {location.reload()}, 40 * 20);
						</script>";					
					} else{
						echo "<script>$.notify('Lo siento algo ha salido mal intenta nuevamente.','error')</script>";
					}
				}
			}
		}
}
		
		if (isset($errors)){			
			?>
			<div class="alert alert-danger" role="alert">
				<button type="button" class="close" data-dismiss="alert">&times;</button>
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
						<?php
							foreach ($messages as $message) {
									echo $message;
								}
							?>
				</div>
				<?php
			}

?>