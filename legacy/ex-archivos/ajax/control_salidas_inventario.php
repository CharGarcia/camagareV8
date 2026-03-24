<?php
include_once("../clases/saldo_producto_y_conversion.php");
if (!isset($_SESSION['ruc_empresa'])){
		session_start();
		$ruc_empresa = $_SESSION['ruc_empresa'];
		$id_usuario = $_SESSION['id_usuario'];
}

class control_salida_inventario{
	
	private $con;
	public $ruc_empresa;
	
	public function salidas_desde_inventario($id_bodega, $id_producto, $cantidad_salida, $tipo_salida, $fecha_salida, $referencia, $unidad_medida_salida, $precio_producto, $lote){
		$ruc_empresa = $_SESSION['ruc_empresa'];
		$id_usuario = $_SESSION['id_usuario'];
		$fecha_agregado=date("Y-m-d H:i:s");
		$con = conenta_login();		
		$saldo_producto_y_conversion = new saldo_producto_y_conversion();
		//borrar las existencias temporales
		$delete_inventario_tmp = mysqli_query($con, "DELETE FROM existencias_inventario_tmp WHERE ruc_empresa = '".$ruc_empresa."' and id_usuario='".$id_usuario."';");
			
		$nombre_producto = $this->info_producto($id_producto, $con)['nombre_producto'];
		$codigo_producto = $this->info_producto($id_producto, $con)['codigo_producto'];	
		
		if($lote=="0" || empty($lote) || ($lote=="") || ($lote==" ") || ($lote==0) || ($lote==null)){
			$campo_condicion = null;
		}else{
			$campo_condicion = "Lote";
		}
		$vencimiento="";
		$query_entradas_inventario = $this->consulta_entradas_inventario($ruc_empresa, $id_producto, $id_bodega, $campo_condicion, $lote, $vencimiento, $con);//para ver las entradas al inventario de ese producto y esa condicion porejemplo lote 
		$total_registro_entradas = mysqli_num_rows($query_entradas_inventario);
								
			$fechas_de_vencimiento=array();
			$lote=array();
			while ($row_detalle_entradas=mysqli_fetch_array($query_entradas_inventario)){
			$fechas_de_vencimiento[]=$row_detalle_entradas["fecha_vencimiento"];
			$lote[]=$row_detalle_entradas["lote"];
			}

			$query_new_insert=array();
			for ($i=0; $i<$total_registro_entradas; $i++){
			if($campo_condicion=="Lote"){
			$query_guarda_inventario_tmp = mysqli_query($con, "INSERT INTO existencias_inventario_tmp (id_existencia_tmp, id_producto, codigo_producto,nombre_producto,cantidad_entrada,cantidad_salida,id_bodega,id_medida,fecha_caducidad,ruc_empresa, saldo_producto,lote,id_usuario) 
			SELECT null,id_producto, codigo_producto, nombre_producto, sum(cantidad_entrada),sum(cantidad_salida), id_bodega, id_medida, fecha_vencimiento,ruc_empresa, sum(cantidad_entrada)-sum(cantidad_salida),lote,'".$id_usuario."' FROM inventarios WHERE ruc_empresa ='". $ruc_empresa ."' and fecha_vencimiento='".$fechas_de_vencimiento[$i]."' and lote='".$lote[$i]."' and id_producto='".$id_producto."' group by id_bodega, id_producto, id_medida, fecha_vencimiento");
			}else{
			$query_guarda_inventario_tmp = mysqli_query($con, "INSERT INTO existencias_inventario_tmp (id_existencia_tmp, id_producto, codigo_producto,nombre_producto,cantidad_entrada,cantidad_salida,id_bodega,id_medida,fecha_caducidad,ruc_empresa, saldo_producto,lote,id_usuario) 
			SELECT null,id_producto, codigo_producto, nombre_producto, sum(cantidad_entrada),sum(cantidad_salida), id_bodega, id_medida, fecha_vencimiento,ruc_empresa, sum(cantidad_entrada)-sum(cantidad_salida),lote,'".$id_usuario."' FROM inventarios WHERE ruc_empresa ='". $ruc_empresa ."' and fecha_vencimiento='".$fechas_de_vencimiento[$i]."' and id_producto='".$id_producto."' group by id_bodega, id_producto, id_medida, fecha_vencimiento");
			}
			//borrar las filas que tengan saldo cero
			$delete_fila_saldo_cero_tmp = mysqli_query($con, "DELETE FROM existencias_inventario_tmp WHERE ruc_empresa = '".$ruc_empresa."' and saldo_producto=0;");
			
			// while para traer todas las filas
			$total_saldo_producto = array();
			$sql_filas = mysqli_query($con,"SELECT * FROM existencias_inventario_tmp WHERE ruc_empresa = '".$ruc_empresa."' and id_producto = '".$id_producto."' and id_usuario = '".$id_usuario."' ");
				
				while ($row_temporales = mysqli_fetch_array($sql_filas)){
				$id_medida_tmp_entrada=$row_temporales["id_medida"];
				$cantidad_a_transformar = $row_temporales['saldo_producto'];
				//transformar la medida temporal a la medida que se esta vendiendo en la factura
				$total_saldo_producto[]= $saldo_producto_y_conversion->conversion($id_medida_tmp_entrada, $unidad_medida_salida, $id_producto, '0', $cantidad_a_transformar, $con, 'saldo');	
				}
				$suma_total_producto = array_sum($total_saldo_producto);

				$saldo_producto=$suma_total_producto;
			
				//para sacar costo unitario de producto
				$sql_costo = mysqli_query($con,"SELECT * FROM inventarios WHERE ruc_empresa = '".$ruc_empresa."' and id_producto = '".$id_producto."' and operacion = 'ENTRADA' order by id_inventario desc ");
				$row_costo = mysqli_fetch_array($sql_costo);
				$total_costo_unitario=$row_costo['costo_unitario'];

				
				if($cantidad_salida > 0 && $saldo_producto > 0){
					if($cantidad_salida <= $saldo_producto ){
						if ($cantidad_salida>0){
						$query_new_insert[] = mysqli_query($con, "INSERT INTO inventarios VALUES (NULL, '".$ruc_empresa."', '".$id_producto."','".$precio_producto."','0','".$cantidad_salida."','".$fecha_salida."','".$fechas_de_vencimiento[$i]."','".$referencia."', '".$id_usuario."', '".$unidad_medida_salida."','".$fecha_agregado."','".$tipo_salida."','".$id_bodega."','SALIDA','".$codigo_producto."','".$nombre_producto."','".$total_costo_unitario."','OK','".$lote[$i]."',0)");
						}
						$cantidad_salida=$cantidad_salida-$saldo_producto;
					}

						if ($cantidad_salida > $saldo_producto ){
							if ($saldo_producto>0){
						$query_new_insert[]= mysqli_query($con,"INSERT INTO inventarios VALUES (NULL, '".$ruc_empresa."', '".$id_producto."','".$precio_producto."',0,'".$saldo_producto."','".$fecha_salida."','".$fechas_de_vencimiento[$i]."','".$referencia."', '".$id_usuario."', '".$unidad_medida_salida."','".$fecha_agregado."','".$tipo_salida."','".$id_bodega."','SALIDA','".$codigo_producto."','".$nombre_producto."','".$total_costo_unitario."','OK','".$lote[$i]."',0)");			
							}
						$cantidad_salida=$cantidad_salida-$saldo_producto;
					}
				}
			}
		return $query_new_insert;
	}
	
		public function salidas_desde_transferencia($id_bodega, $id_producto, $cantidad_salida, $tipo_salida, $fecha_salida, $referencia, $unidad_medida_salida, $precio_producto, $codigo_unico){
		$ruc_empresa = $_SESSION['ruc_empresa'];
		$id_usuario = $_SESSION['id_usuario'];
		$fecha_agregado=date("Y-m-d H:i:s");
		$con = conenta_login();	
		$saldo_producto_y_conversion = new saldo_producto_y_conversion();
		//borrar las existencias temporales
		$delete_inventario_tmp = mysqli_query($con, "DELETE FROM existencias_inventario_tmp WHERE ruc_empresa = '".$ruc_empresa."' and id_usuario='".$id_usuario."';");
			
		$nombre_producto = $this->info_producto($id_producto, $con)['nombre_producto'];
		$codigo_producto = $this->info_producto($id_producto, $con)['codigo_producto'];	
			
		$campo_condicion = null;
		$vencimiento="";
		$query_entradas_inventario = $this->consulta_entradas_inventario($ruc_empresa, $id_producto, $id_bodega, $campo_condicion, null, $vencimiento, $con);//para ver las entradas al inventario de ese producto y esa condicion porejemplo lote 
		$total_registro_entradas = mysqli_num_rows($query_entradas_inventario);
								
			$fechas_de_vencimiento=array();
			$lote=array();
			while ($row_detalle_entradas=mysqli_fetch_array($query_entradas_inventario)){
			$fechas_de_vencimiento[]=$row_detalle_entradas["fecha_vencimiento"];
			$lote[]=$row_detalle_entradas["lote"];
			}

			$query_new_insert=array();
			for ($i=0; $i<$total_registro_entradas; $i++){
			if($campo_condicion=="Lote"){
			$query_guarda_inventario_tmp = mysqli_query($con, "INSERT INTO existencias_inventario_tmp (id_existencia_tmp, id_producto, codigo_producto,nombre_producto,cantidad_entrada,cantidad_salida,id_bodega,id_medida,fecha_caducidad,ruc_empresa, saldo_producto,lote,id_usuario) 
			SELECT null,id_producto, codigo_producto, nombre_producto, sum(cantidad_entrada),sum(cantidad_salida), id_bodega, id_medida, fecha_vencimiento,ruc_empresa, sum(cantidad_entrada)-sum(cantidad_salida),lote,'".$id_usuario."' FROM inventarios WHERE ruc_empresa ='". $ruc_empresa ."' and fecha_vencimiento='".$fechas_de_vencimiento[$i]."' and lote='".$lote[$i]."' and id_producto='".$id_producto."' group by id_bodega, id_producto, id_medida, fecha_vencimiento");
			}else{
			$query_guarda_inventario_tmp = mysqli_query($con, "INSERT INTO existencias_inventario_tmp (id_existencia_tmp, id_producto, codigo_producto,nombre_producto,cantidad_entrada,cantidad_salida,id_bodega,id_medida,fecha_caducidad,ruc_empresa, saldo_producto,lote,id_usuario) 
			SELECT null,id_producto, codigo_producto, nombre_producto, sum(cantidad_entrada),sum(cantidad_salida), id_bodega, id_medida, fecha_vencimiento,ruc_empresa, sum(cantidad_entrada)-sum(cantidad_salida),lote,'".$id_usuario."' FROM inventarios WHERE ruc_empresa ='". $ruc_empresa ."' and fecha_vencimiento='".$fechas_de_vencimiento[$i]."' and id_producto='".$id_producto."' group by id_bodega, id_producto, id_medida, fecha_vencimiento");
			}
			//borrar las filas que tengan saldo cero
			$delete_fila_saldo_cero_tmp = mysqli_query($con, "DELETE FROM existencias_inventario_tmp WHERE ruc_empresa = '".$ruc_empresa."' and saldo_producto=0;");
			
			// while para traer todas las filas
			$total_saldo_producto = array();
			$sql_filas = mysqli_query($con,"SELECT * FROM existencias_inventario_tmp WHERE ruc_empresa = '".$ruc_empresa."' and id_producto = '".$id_producto."' and id_usuario = '".$id_usuario."' ");
				
				
				while ($row_temporales = mysqli_fetch_array($sql_filas)){
				$id_medida_tmp_entrada=$row_temporales["id_medida"];
				$cantidad_a_transformar = $row_temporales['saldo_producto'];
				//transformar la medida temporal a la medida que se esta vendiendo en la factura
				$total_saldo_producto[]= $saldo_producto_y_conversion->conversion($id_medida_tmp_entrada, $unidad_medida_salida, $id_producto, '0', $cantidad_a_transformar, $con, 'saldo');	
				}
				$suma_total_producto = array_sum($total_saldo_producto);

				$saldo_producto=$suma_total_producto;
				
				//para sacar costo unitario de producto
				$sql_costo = mysqli_query($con,"SELECT * FROM inventarios WHERE ruc_empresa = '".$ruc_empresa."' and id_producto = '".$id_producto."' and operacion = 'ENTRADA' order by id_inventario desc ");
				$row_costo = mysqli_fetch_array($sql_costo);
				$total_costo_unitario=$row_costo['costo_unitario'];

				
				if($cantidad_salida > 0 && $saldo_producto > 0){
					if($cantidad_salida <= $saldo_producto ){
						if ($cantidad_salida>0){
						$query_new_insert[] = mysqli_query($con, "INSERT INTO inventarios VALUES (NULL, '".$ruc_empresa."', '".$id_producto."','".$precio_producto."','0','".$cantidad_salida."','".$fecha_salida."','".$fechas_de_vencimiento[$i]."','".$referencia."', '".$id_usuario."', '".$unidad_medida_salida."','".$fecha_agregado."','".$tipo_salida."','".$id_bodega."','SALIDA','".$codigo_producto."','".$nombre_producto."','".$total_costo_unitario."','OK','".$lote[$i]."','".$codigo_unico."')");
						}
						$cantidad_salida=$cantidad_salida-$saldo_producto;
					}

						if ($cantidad_salida > $saldo_producto ){
							if ($saldo_producto>0){
						$query_new_insert[]= mysqli_query($con,"INSERT INTO inventarios VALUES (NULL, '".$ruc_empresa."', '".$id_producto."','".$precio_producto."',0,'".$saldo_producto."','".$fecha_salida."','".$fechas_de_vencimiento[$i]."','".$referencia."', '".$id_usuario."', '".$unidad_medida_salida."','".$fecha_agregado."','".$tipo_salida."','".$id_bodega."','SALIDA','".$codigo_producto."','".$nombre_producto."','".$total_costo_unitario."','OK','".$lote[$i]."','".$codigo_unico."')");			
							}
						$cantidad_salida=$cantidad_salida-$saldo_producto;
					}
				}
			}
		return $query_new_insert;
	}
	
	
	public function salidas_desde_inventario_mp($id_bodega, $id_producto, $cantidad_salida, $tipo_salida, $fecha_salida, $referencia, $unidad_medida_salida, $precio_producto){
		$ruc_empresa = $_SESSION['ruc_empresa'];
		$id_usuario = $_SESSION['id_usuario'];
		$fecha_agregado=date("Y-m-d H:i:s");
		$con = conenta_login();		
		$saldo_producto_y_conversion = new saldo_producto_y_conversion();
			
		//medida del producto
		$query_medida_producto=mysqli_query($con,"select * from productos_servicios WHERE id='".$id_producto."' ");
		$row_medida_producto=mysqli_fetch_array($query_medida_producto);
		$id_medida = $row_medida_producto['id_unidad_medida'];
		$codigo_producto = $row_medida_producto['codigo_producto'];
		$nombre_producto = $row_medida_producto['nombre_producto'];
		
		$query_fechas_entradas=mysqli_query($con,"select * from inventarios_mp WHERE ruc_empresa='".$ruc_empresa."' and id_producto='".$id_producto."' and id_bodega='".$id_bodega."' and operacion = 'ENTRADA' order by fecha_vencimiento asc ");
		$total_registros_fechas = mysqli_num_rows($query_fechas_entradas);
					
		//borrar las existencias temporales
		$delete_inventario_tmp = mysqli_query($con, "DELETE FROM existencias_inventario_tmp WHERE ruc_empresa = '".$ruc_empresa."';");			
					
			$fechas_de_vencimiento=array();
			$lote=array();
			while ($row_fechas_entradas=mysqli_fetch_array($query_fechas_entradas)){
			$fechas_de_vencimiento[]=$row_fechas_entradas["fecha_vencimiento"];
			$lote[]=$row_fechas_entradas["lote"];
			}

			$query_new_insert=array();
			for ($i=0; $i<$total_registros_fechas; $i++){
			//traer el saldo del producto en todas sus medidas pero de la fecha de vencimiento seleccionada
			//and fecha_vencimiento='".$fechas_de_vencimiento[$i]."'
			$query_guarda_inventario_tmp = mysqli_query($con, "INSERT INTO existencias_inventario_tmp (id_existencia_tmp, id_producto, codigo_producto,nombre_producto,cantidad_entrada,cantidad_salida,id_bodega,id_medida,fecha_caducidad,ruc_empresa, saldo_producto,lote,id_usuario) 
			SELECT null,id_producto, codigo_producto, nombre_producto, sum(cantidad_entrada),sum(cantidad_salida), id_bodega, id_medida, fecha_vencimiento,ruc_empresa, sum(cantidad_entrada)-sum(cantidad_salida),lote,'".$id_usuario."' FROM inventarios_mp WHERE ruc_empresa ='". $ruc_empresa ."' and id_producto='".$id_producto."' and fecha_vencimiento='".$fechas_de_vencimiento[$i]."' group by id_bodega, id_producto, id_medida, fecha_vencimiento");
			//borrar las filas que tengan saldo cero
			$delete_fila_saldo_cero_tmp = mysqli_query($con, "DELETE FROM existencias_inventario_tmp WHERE ruc_empresa = '".$ruc_empresa."' and saldo_producto=0;");
			
			// while para traer todas las filas
			$total_saldo_producto = array();
			$sql_filas = mysqli_query($con,"SELECT * FROM existencias_inventario_tmp WHERE ruc_empresa = '".$ruc_empresa."' and id_producto = '".$id_producto."' and id_usuario = '".$id_usuario."' ");
				while ($row_temporales = mysqli_fetch_array($sql_filas)){
				$id_medida_tmp_entrada=$row_temporales["id_medida"];
				$cantidad_a_transformar = $row_temporales['saldo_producto'];
				//transformar la medida temporal a la medida que se esta vendiendo en la factura
				$total_saldo_producto[]= $saldo_producto_y_conversion->conversion($id_medida_tmp_entrada, $unidad_medida_salida, $id_producto, '0', $cantidad_a_transformar, $con, 'saldo');	
				}
				$suma_total_producto = array_sum($total_saldo_producto);
				$saldo_producto=$suma_total_producto;
				
				//para sacar costo unitario de producto
				$sql_costo = mysqli_query($con,"SELECT * FROM inventarios WHERE ruc_empresa = '".$ruc_empresa."' and id_producto = '".$id_producto."' and operacion = 'ENTRADA' order by id_inventario desc ");
				$row_costo = mysqli_fetch_array($sql_costo);
				$total_costo_unitario=$row_costo['costo_unitario'];
				
				if($cantidad_salida > 0 && $saldo_producto > 0){
					if($cantidad_salida <= $saldo_producto ){
						if ($cantidad_salida>0){
						$query_new_insert[] = mysqli_query($con, "INSERT INTO inventarios_mp VALUES (NULL, '".$ruc_empresa."', '".$id_producto."','".$total_costo_unitario."','0','".$cantidad_salida."','".$fecha_salida."','".$fechas_de_vencimiento[$i]."','".$referencia."', '".$id_usuario."', '".$unidad_medida_salida."','".$fecha_agregado."','".$tipo_salida."','".$id_bodega."','SALIDA','".$codigo_producto."','".$nombre_producto."','".$total_costo_unitario."','OK','".$lote[$i]."',0)");
						}
						$cantidad_salida=$cantidad_salida-$saldo_producto;
					}

						if ($cantidad_salida > $saldo_producto ){
							if ($saldo_producto>0){
						$query_new_insert[]= mysqli_query($con,"INSERT INTO inventarios_mp VALUES (NULL, '".$ruc_empresa."', '".$id_producto."','".$total_costo_unitario."',0,'".$saldo_producto."','".$fecha_salida."','".$fechas_de_vencimiento[$i]."','".$referencia."', '".$id_usuario."', '".$unidad_medida_salida."','".$fecha_agregado."','".$tipo_salida."','".$id_bodega."','SALIDA','".$codigo_producto."','".$nombre_producto."','".$total_costo_unitario."','OK','".$lote[$i]."',0)");			
							}
						$cantidad_salida=$cantidad_salida-$saldo_producto;
					}
				}
			}
		return $query_new_insert;
	}
	
		public function salidas_desde_produccion($id_bodega, $id_producto, $cantidad_salida, $tipo_salida, $fecha_salida, $referencia, $unidad_medida_salida, $orden){
		$ruc_empresa = $_SESSION['ruc_empresa'];
		$id_usuario = $_SESSION['id_usuario'];
		$fecha_agregado=date("Y-m-d H:i:s");
		$precio_producto=0;
		$con = conenta_login();		
		$saldo_producto_y_conversion = new saldo_producto_y_conversion();
			
		//medida del producto
		$query_medida_producto=mysqli_query($con,"select * from productos_servicios WHERE id='".$id_producto."' ");
		$row_medida_producto=mysqli_fetch_array($query_medida_producto);
		$id_medida = $row_medida_producto['id_unidad_medida'];
		$codigo_producto = $row_medida_producto['codigo_producto'];
		$nombre_producto = $row_medida_producto['nombre_producto'];
		
		$query_fechas_entradas=mysqli_query($con,"select * from inventarios_mp WHERE ruc_empresa='".$ruc_empresa."' and id_producto='".$id_producto."' and id_bodega='".$id_bodega."' and operacion = 'ENTRADA' order by fecha_vencimiento asc ");
		$total_registros_fechas = mysqli_num_rows($query_fechas_entradas);
					
		//borrar las existencias temporales
		$delete_inventario_tmp = mysqli_query($con, "DELETE FROM existencias_inventario_tmp WHERE ruc_empresa = '".$ruc_empresa."';");			
					
			$fechas_de_vencimiento=array();
			$lote=array();
			while ($row_fechas_entradas=mysqli_fetch_array($query_fechas_entradas)){
			$fechas_de_vencimiento[]=$row_fechas_entradas["fecha_vencimiento"];
			$lote[]=$row_fechas_entradas["lote"];
			}

			$query_new_insert=array();
			for ($i=0; $i<$total_registros_fechas; $i++){
			//traer el saldo del producto en todas sus medidas pero de la fecha de vencimiento seleccionada
			//and fecha_vencimiento='".$fechas_de_vencimiento[$i]."'
			$query_guarda_inventario_tmp = mysqli_query($con, "INSERT INTO existencias_inventario_tmp (id_existencia_tmp, id_producto, codigo_producto,nombre_producto,cantidad_entrada,cantidad_salida,id_bodega,id_medida,fecha_caducidad,ruc_empresa, saldo_producto,lote,id_usuario) 
			SELECT null,id_producto, codigo_producto, nombre_producto, sum(cantidad_entrada),sum(cantidad_salida), id_bodega, id_medida, fecha_vencimiento,ruc_empresa, sum(cantidad_entrada)-sum(cantidad_salida),lote,'".$id_usuario."' FROM inventarios_mp WHERE ruc_empresa ='". $ruc_empresa ."' and id_producto='".$id_producto."' and fecha_vencimiento='".$fechas_de_vencimiento[$i]."' group by id_bodega, id_producto, id_medida, fecha_vencimiento");
			//borrar las filas que tengan saldo cero
			$delete_fila_saldo_cero_tmp = mysqli_query($con, "DELETE FROM existencias_inventario_tmp WHERE ruc_empresa = '".$ruc_empresa."' and saldo_producto=0;");
			
			// while para traer todas las filas
			$total_saldo_producto = array();
			$sql_filas = mysqli_query($con,"SELECT * FROM existencias_inventario_tmp WHERE ruc_empresa = '".$ruc_empresa."' and id_producto = '".$id_producto."' and id_usuario = '".$id_usuario."' ");
				while ($row_temporales = mysqli_fetch_array($sql_filas)){
				$id_medida_tmp_entrada=$row_temporales["id_medida"];
				$cantidad_a_transformar = $row_temporales['saldo_producto'];
				//transformar la medida temporal a la medida que se esta sacando en la produccion
				$total_saldo_producto[]= $saldo_producto_y_conversion->conversion($id_medida_tmp_entrada, $unidad_medida_salida, $id_producto, '0', $cantidad_a_transformar, $con, 'saldo');	
				}
				$suma_total_producto = array_sum($total_saldo_producto);
				$saldo_producto=$suma_total_producto;
				
				/*
				//para sacar costo unitario de producto en su medida original
				$sql_costo = mysqli_query($con,"SELECT * FROM inventarios_mp WHERE ruc_empresa = '".$ruc_empresa."' and id_producto = '".$id_producto."' and operacion = 'ENTRADA' and lote='".$lote[$i]."' and fecha_vencimiento='".$fechas_de_vencimiento[$i]."'");
				$row_costo = mysqli_fetch_array($sql_costo);
				$costo_unitario=$row_costo['costo_unitario'];
				$total_costo_unitario= $saldo_producto_y_conversion->conversion($id_medida_tmp_entrada, $unidad_medida_salida, $id_producto, $costo_unitario, $cantidad_a_transformar, $con, 'precio');	
				*/
				//para sacar costo unitario de producto
				$sql_costo = mysqli_query($con,"SELECT * FROM inventarios WHERE ruc_empresa = '".$ruc_empresa."' and id_producto = '".$id_producto."' and operacion = 'ENTRADA' order by id_inventario desc ");
				$row_costo = mysqli_fetch_array($sql_costo);
				$total_costo_unitario=$row_costo['costo_unitario'];
				
				
				
				if($cantidad_salida > 0 && $saldo_producto > 0){
					if($cantidad_salida <= $saldo_producto ){
						if ($cantidad_salida>0){
						$query_new_insert[] = mysqli_query($con, "INSERT INTO inventarios_mp VALUES (NULL, '".$ruc_empresa."', '".$id_producto."','".$total_costo_unitario."','0','".$cantidad_salida."','".$fecha_salida."','".$fechas_de_vencimiento[$i]."','".$referencia."', '".$id_usuario."', '".$unidad_medida_salida."','".$fecha_agregado."','".$tipo_salida."','".$id_bodega."','SALIDA','".$codigo_producto."','".$nombre_producto."','".$total_costo_unitario."','OK','".$lote[$i]."','".$orden."')");
						}
						$cantidad_salida=$cantidad_salida-$saldo_producto;
					}

						if ($cantidad_salida > $saldo_producto ){
							if ($saldo_producto>0){
						$query_new_insert[]= mysqli_query($con,"INSERT INTO inventarios_mp VALUES (NULL, '".$ruc_empresa."', '".$id_producto."','".$total_costo_unitario."',0,'".$saldo_producto."','".$fecha_salida."','".$fechas_de_vencimiento[$i]."','".$referencia."', '".$id_usuario."', '".$unidad_medida_salida."','".$fecha_agregado."','".$tipo_salida."','".$id_bodega."','SALIDA','".$codigo_producto."','".$nombre_producto."','".$total_costo_unitario."','OK','".$lote[$i]."','".$orden."')");			
							}
						$cantidad_salida=$cantidad_salida-$saldo_producto;
					}
				}
			}
		return $query_new_insert;
	}
	
	public function salidas_desde_factura($serie_sucursal, $id_bodega, $id_producto, $cantidad_salida, $tipo_salida, $fecha_salida, $referencia, $unidad_medida_requerida, $precio_venta, $lote, $vencimiento){
		$ruc_empresa = $_SESSION['ruc_empresa'];
		$id_usuario = $_SESSION['id_usuario'];
		$fecha_agregado=date("Y-m-d H:i:s");
		$con = conenta_login();		
		$saldo_producto_y_conversion = new saldo_producto_y_conversion();
		//borrar las existencias temporales
		$delete_inventario_tmp = mysqli_query($con, "DELETE FROM existencias_inventario_tmp WHERE ruc_empresa = '".$ruc_empresa."';");
		
		$nombre_producto = $this->info_producto($id_producto, $con)['nombre_producto'];
		$codigo_producto = $this->info_producto($id_producto, $con)['codigo_producto'];	
		$campo_condicion = $this->metodos_salidas_inventario($serie_sucursal, $con);//metodo de salida de inventario
		$query_entradas_inventario = $this->consulta_entradas_inventario($ruc_empresa, $id_producto, $id_bodega, $campo_condicion, $lote, $vencimiento, $con);//para ver las entradas al inventario de ese producto y esa condicion porejemplo lote 
		$total_registro_entradas = mysqli_num_rows($query_entradas_inventario);
		
			$fechas_de_vencimiento=array();
			$lote=array();
			
			while ($row_detalle_entradas=mysqli_fetch_array($query_entradas_inventario)){
			$fechas_de_vencimiento[]=$row_detalle_entradas["fecha_vencimiento"];
			$lote[]=$row_detalle_entradas["lote"];
			}
			
			$query_new_insert=array();
			for ($i=0; $i<$total_registro_entradas; $i++){ //para recorrer cada registro de entrada
			//traer el saldo del producto en todas sus medidas pero de la fecha de vencimiento seleccionada
			
			if($campo_condicion=="Lote"){
			$query_guarda_inventario_tmp = mysqli_query($con, "INSERT INTO existencias_inventario_tmp (id_existencia_tmp, id_producto, codigo_producto,nombre_producto,cantidad_entrada,cantidad_salida,id_bodega,id_medida,fecha_caducidad,ruc_empresa, saldo_producto,lote,id_usuario) 
			SELECT null,id_producto, codigo_producto, nombre_producto, sum(cantidad_entrada),sum(cantidad_salida), id_bodega, id_medida, fecha_vencimiento,ruc_empresa, sum(cantidad_entrada)-sum(cantidad_salida),lote,'".$id_usuario."' FROM inventarios WHERE ruc_empresa ='". $ruc_empresa ."' and fecha_vencimiento='".$fechas_de_vencimiento[$i]."' and lote='".$lote[$i]."' and id_producto='".$id_producto."' group by id_bodega, id_producto, id_medida, fecha_vencimiento");
			}else{
			$query_guarda_inventario_tmp = mysqli_query($con, "INSERT INTO existencias_inventario_tmp (id_existencia_tmp, id_producto, codigo_producto,nombre_producto,cantidad_entrada,cantidad_salida,id_bodega,id_medida,fecha_caducidad,ruc_empresa, saldo_producto,lote,id_usuario) 
			SELECT null,id_producto, codigo_producto, nombre_producto, sum(cantidad_entrada),sum(cantidad_salida), id_bodega, id_medida, fecha_vencimiento,ruc_empresa, sum(cantidad_entrada)-sum(cantidad_salida),lote,'".$id_usuario."' FROM inventarios WHERE ruc_empresa ='". $ruc_empresa ."' and fecha_vencimiento='".$fechas_de_vencimiento[$i]."' and id_producto='".$id_producto."' group by id_bodega, id_producto, id_medida, fecha_vencimiento");
			}

			//borrar las filas que tengan saldo cero
			$delete_fila_saldo_cero_tmp = mysqli_query($con, "DELETE FROM existencias_inventario_tmp WHERE ruc_empresa = '".$ruc_empresa."' and saldo_producto=0;");			
			
			// while para traer todas las filas
			$total_saldo_producto = array();
			$sql_filas = mysqli_query($con,"SELECT * FROM existencias_inventario_tmp WHERE ruc_empresa = '".$ruc_empresa."' and id_producto = '".$id_producto."' and id_usuario = '".$id_usuario."' ");
			while ($row_temporales = mysqli_fetch_array($sql_filas)){
			$id_medida_tmp_entrada=$row_temporales["id_medida"];
			$cantidad_a_transformar = $row_temporales['saldo_producto'];
			//transformar la medida temporal a la medida que se esta vendiendo en la factura
			$total_saldo_producto[]= $saldo_producto_y_conversion->conversion($id_medida_tmp_entrada, $unidad_medida_requerida, $id_producto, '0', $cantidad_a_transformar, $con, 'saldo');	
			}
			$suma_total_producto = array_sum($total_saldo_producto);
			
			//para sacar costo unitario de producto
				$sql_costo = mysqli_query($con,"SELECT * FROM inventarios WHERE ruc_empresa = '".$ruc_empresa."' and id_producto = '".$id_producto."' and operacion = 'ENTRADA' order by id_inventario desc ");
				$row_costo = mysqli_fetch_array($sql_costo);
				$total_costo_unitario=$row_costo['costo_unitario'];
				
				$saldo_producto =  $suma_total_producto;
				if($cantidad_salida > 0 && $saldo_producto > 0){
					if($cantidad_salida <= $saldo_producto ){
						if ($cantidad_salida>0){
						$query_new_insert[] = mysqli_query($con, "INSERT INTO inventarios VALUES (NULL, '".$ruc_empresa."', '".$id_producto."','".$precio_venta."','0','".$cantidad_salida."','".$fecha_salida."','".$fechas_de_vencimiento[$i]."','".$referencia."', '".$id_usuario."', '".$unidad_medida_requerida."','".$fecha_agregado."','".$tipo_salida."','".$id_bodega."','SALIDA','".$codigo_producto."','".$nombre_producto."','".$total_costo_unitario."','OK','".$lote[$i]."',0)");
						}
						$cantidad_salida=$cantidad_salida-$saldo_producto;
					}

						if ($cantidad_salida > $saldo_producto ){
							if ($saldo_producto>0){
						$query_new_insert[]= mysqli_query($con,"INSERT INTO inventarios VALUES (NULL, '".$ruc_empresa."', '".$id_producto."','".$precio_venta."',0,'".$saldo_producto."','".$fecha_salida."','".$fechas_de_vencimiento[$i]."','".$referencia."', '".$id_usuario."', '".$unidad_medida_requerida."','".$fecha_agregado."','".$tipo_salida."','".$id_bodega."','SALIDA','".$codigo_producto."','".$nombre_producto."','".$total_costo_unitario."','OK','".$lote[$i]."',0)");			
							}
						$cantidad_salida=$cantidad_salida-$saldo_producto;
					}
				}
			}
				return $query_new_insert;
	}

	//--------//

	//funciones de control para salidas de inventario
	public function metodos_salidas_inventario($serie_sucursal, $con){
		$ruc_empresa = $_SESSION['ruc_empresa'];
		//consultar que metodo de salida de inventario esta usando esta sucursal
		$query_metodo_salida=mysqli_query($con,"select * from configuracion_facturacion WHERE ruc_empresa='".$ruc_empresa."' and serie_sucursal='".$serie_sucursal."' ");
		$row_metodo_salida=mysqli_fetch_array($query_metodo_salida);	
		$metodo_salida=$row_metodo_salida['calculo_salida'];
		return $metodo_salida;
	}
	
	public function info_producto($id_producto, $con){
		//info del producto
		$query_info_producto=mysqli_query($con,"select * from productos_servicios WHERE id='".$id_producto."' ");
		$row_medida_producto=mysqli_fetch_array($query_info_producto);
		return $row_medida_producto;
	}
	
	public function consulta_entradas_inventario($ruc_empresa, $id_producto, $id_bodega, $campo_condicion, $lote, $vencimiento, $con){
		//info entradas de inventario
		if($campo_condicion=="Lote"){
		$query_entradas_inventario=mysqli_query($con,"select * from inventarios WHERE ruc_empresa='".$ruc_empresa."' and id_producto='".$id_producto."' and id_bodega='".$id_bodega."' and operacion = 'ENTRADA' and lote='".$lote."' order by lote asc ");
		return $query_entradas_inventario;//me entrega solo el array para luego contarlo o sacar la info con mysqly fecht array
		}
		if($campo_condicion=="Caducidad"){
		$query_entradas_inventario=mysqli_query($con,"select * from inventarios WHERE ruc_empresa='".$ruc_empresa."' and id_producto='".$id_producto."' and id_bodega='".$id_bodega."' and operacion = 'ENTRADA' and fecha_vencimiento='".$vencimiento."' order by fecha_vencimiento asc ");
		return $query_entradas_inventario;//me entrega solo el array para luego contarlo o sacar la info con mysqly fecht array
		}
		if($campo_condicion=="Fifo"){
		$query_entradas_inventario=mysqli_query($con,"select * from inventarios WHERE ruc_empresa='".$ruc_empresa."' and id_producto='".$id_producto."' and id_bodega='".$id_bodega."' and operacion = 'ENTRADA' order by fecha_vencimiento asc ");
		return $query_entradas_inventario;//me entrega solo el array para luego contarlo o sacar la info con mysqly fecht array
		}
		if($campo_condicion==""){
		$query_entradas_inventario=mysqli_query($con,"select * from inventarios WHERE ruc_empresa='".$ruc_empresa."' and id_producto='".$id_producto."' and id_bodega='".$id_bodega."' and operacion = 'ENTRADA' order by fecha_vencimiento asc ");
		return $query_entradas_inventario;//me entrega solo el array para luego contarlo o sacar la info con mysqly fecht array
		}
		if($campo_condicion==null){
		$query_entradas_inventario=mysqli_query($con,"select * from inventarios WHERE ruc_empresa='".$ruc_empresa."' and id_producto='".$id_producto."' and id_bodega='".$id_bodega."' and operacion = 'ENTRADA' order by fecha_vencimiento asc ");
		return $query_entradas_inventario;//me entrega solo el array para luego contarlo o sacar la info con mysqly fecht array
		}

	}
	
}
?>


