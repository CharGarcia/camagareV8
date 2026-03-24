<?php
	/* Connect To Database*/
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
	session_start();
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$id_usuario = $_SESSION['id_usuario'];
	$fecha_registro=date("Y-m-d H:i:s");
	ini_set('date.timezone','America/Guayaquil');
	$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
	
	//para buscar detalles de compras para pasar a inventario, solo busca
		if($action == 'pasar_inventario'){
		 $p = mysqli_real_escape_string($con,(strip_tags($_REQUEST['p'], ENT_QUOTES)));
		 $aColumns = array('detalle_producto', 'codigo_producto','numero_documento','razon_social');//Columnas de busqueda
		 //$aColumns = array('codigo_producto','detalle_producto','numero_documento', 'razon_social','ruc_proveedor');//Columnas de busqueda
		 $sTable = "cuerpo_compra as cuecom INNER JOIN encabezado_compra as enccom ON mid(enccom.ruc_empresa,1,10) = '". substr($ruc_empresa,0,10) ."' and cuecom.codigo_documento = enccom.codigo_documento and cuecom.cantidad-cuecom.cantidad_inv > 0 LEFT JOIN proveedores as pro ON pro.id_proveedor=enccom.id_proveedor";
		 $sWhere = " " ;
		if ( $_GET['p'] != "" )
		{
			$sWhere = " WHERE (";
			
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= $aColumns[$i]." LIKE '%".$p."%' OR ";
			}
			
			$sWhere = substr_replace( $sWhere, " ", -3 );
			$sWhere .= ')';
		}
		$sWhere.=" order by cuecom.id_cuerpo_compra desc";
		
		include ("../ajax/pagination.php"); //include pagination file
		//pagination variables
		$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page']))?$_REQUEST['page']:1;
		$per_page = 10; //how much records you want to show
		$adjacents  = 10; //gap between pages after number of adjacents
		$offset = ($page - 1) * $per_page;
		//Count the total number of row in your table*/
		$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
		$row= mysqli_fetch_array($count_query);
		$numrows = $row['numrows'];
		$total_pages = ceil($numrows/$per_page);
		$reload = '../compras.php';
		//main query to fetch the data
		$sql="SELECT * FROM  $sTable $sWhere LIMIT $offset,$per_page";
		$query = mysqli_query($con, $sql);
		//loop through fetched data
		if ($numrows>0){
			?>
			<form class="form-horizontal" method="POST" id="guarda_a_inventario" name="guarda_a_inventario">
			<div class="panel panel-info">
			<div id="resultados_enviar_inventario"></div>		
			<div class="table-responsive">
			  <table class="table">
				<?php
				while ($row_detalle_compra = mysqli_fetch_array($query)){
					$id_registro_compra=$row_detalle_compra['id_cuerpo_compra'];
					$codigo_producto=$row_detalle_compra['codigo_producto'];
					$nombre_producto=$row_detalle_compra['detalle_producto'];
					$precio_producto=number_format($row_detalle_compra['subtotal']/$row_detalle_compra['cantidad'], 4, '.', '');
					$cantidad_producto=$row_detalle_compra['cantidad'];
					$saldo_producto=$row_detalle_compra['cantidad']-$row_detalle_compra['cantidad_inv'];
					$codigo_documento=$row_detalle_compra['codigo_documento'];
					//datos del producto
					$busca_datos_producto = mysqli_query($con,"SELECT * FROM productos_servicios as proser, unidad_medida as unimed WHERE proser.codigo_producto='".$codigo_producto."' and proser.nombre_producto='".$nombre_producto."' and proser.ruc_empresa='".$ruc_empresa."' and unimed.id_medida=proser.id_unidad_medida");
					$row_datos_producto = mysqli_fetch_array($busca_datos_producto);
					$codigo_producto_interno=isset($row_datos_producto['codigo_producto'])?$row_datos_producto['codigo_producto']:"";
					$nombre_producto_interno=isset($row_datos_producto['nombre_producto'])?$row_datos_producto['nombre_producto']:"";
					$medida_producto_interno=isset($row_datos_producto['id_unidad_medida'])?$row_datos_producto['id_unidad_medida']:"";
					$nombre_medida_producto_interno=isset($row_datos_producto['nombre_medida'])?$row_datos_producto['nombre_medida']:"";
					$id_producto_interno=isset($row_datos_producto['id'])?$row_datos_producto['id']:"";
					
					if ($codigo_producto==$codigo_producto_interno && $nombre_producto==$nombre_producto_interno){
						$nombre_producto_mostrado=$nombre_producto_interno;
						$codigo_producto_mostrado=$codigo_producto_interno;
						$id_producto_mostrado=$id_producto_interno;
					}else{
						$nombre_producto_mostrado="";
						$codigo_producto_mostrado="";
						$id_producto_mostrado="";					
					}
					
					//datos del proveedor y factura
					$busca_datos_proveedor = mysqli_query($con,"SELECT * FROM encabezado_compra ec, proveedores pro WHERE ec.codigo_documento='".$codigo_documento."' and ec.id_proveedor=pro.id_proveedor");
					$row_datos_proveedor = mysqli_fetch_array($busca_datos_proveedor);
					$nombre_proveedor=$row_datos_proveedor['razon_social'];
					$numero_documento_compra=$row_datos_proveedor['numero_documento'];
					//if ($saldo_producto>0){
						?>
						<tr class="info">
						<td colspan="9"><b>Producto en documento:</b><?php echo $nombre_producto; ?> <b>Proveedor:</b><?php echo $nombre_proveedor; ?> <b>No:</b><?php echo $numero_documento_compra; ?> </td>
						</tr>
						
						<tr class="active">
							<input type="hidden" name="id_producto[<?php echo $id_registro_compra;?>]" id="id_producto<?php echo $id_registro_compra;?>"  value="<?php echo $id_producto_mostrado;?>">
							<input type="hidden" name="codigo_producto[<?php echo $id_registro_compra;?>]" id="codigo_producto<?php echo $id_registro_compra;?>" value="<?php echo $codigo_producto_mostrado; ?>">
							<input type="hidden" name="saldo_producto[<?php echo $id_registro_compra;?>]" value="<?php echo $saldo_producto;?>">
							<input type="hidden" name="id_registro[]" value="<?php echo $id_registro_compra;?>">
							<input type="hidden" name="producto_compra[<?php echo $id_registro_compra;?>]" value="<?php echo $nombre_producto;?>">
							<input type="hidden" name="proveedor[<?php echo $id_registro_compra;?>]" value="<?php echo $nombre_proveedor;?>">
							<input type="hidden" name="numero_documento[<?php echo $id_registro_compra;?>]" value="<?php echo $numero_documento_compra;?>">
							<input type="hidden" name="codigo_compra" value="<?php echo $codigo_documento;?>">
							<input type="hidden" name="codigo_registro[<?php echo $id_registro_compra;?>]" value="<?php echo $id_registro_compra;?>">
							
							<td class="col-xs-4" colspan="1">
							Producto para inventario
							<input type="text" class="form-control input-sm" id="mi_producto<?php echo $id_registro_compra;?>" name="mi_producto[<?php echo $id_registro_compra;?>]"  onkeyup="buscar_productos('<?php echo $id_registro_compra;?>');" autocomplete="off" placeholder="Ingrese producto" value="<?php echo $nombre_producto_mostrado; ?>">
							</td>
							<td class="col-xs-1">
							Cantidad
							<input type="text" class="form-control input-sm text-right" name="cantidad_producto[<?php echo $id_registro_compra;?>]"  placeholder="Ingrese cantidad" value="<?php echo $saldo_producto; ?>" >
							</td>

							<td class="col-xs-2">
							Medida
							<select class="form-control" name="unidad_medida[<?php echo $id_registro_compra;?>]" id="unidad_medida<?php echo $id_registro_compra;?>">
							<option value="">Seleccione</option>
							<?php
							if ($codigo_producto==$codigo_producto_interno && $nombre_producto==$nombre_producto_interno){
								?>
							<option value="<?php echo $medida_producto_interno;?>"selected><?php echo $nombre_medida_producto_interno;?></option>
							<?php
							}
							?>
							</select>
							</td>
							<td class="col-xs-2">
							Bodega
									<select class="form-control" name="bodega[<?php echo $id_registro_compra;?>]" id="bodega<?php echo $id_registro_compra;?>">
								<?php
									$sql_bod = mysqli_query($con,"SELECT * FROM bodega as bod INNER JOIN empresas as emp ON emp.ruc=bod.ruc_empresa WHERE emp.estado='1' and mid(bod.ruc_empresa,1,10)='".substr($ruc_empresa,0,10)."' order by bod.nombre_bodega asc");
									//$res = mysqli_query($con,$sql);
								?> <option value="">Seleccione</option>
								 <?php
									while($o = mysqli_fetch_assoc($sql_bod)){
								?>
									<option value="<?php echo $o['id_bodega'] ?>"selected><?php echo strtoupper ($o['nombre_bodega']) ?> </option>
									<?php
									}
								?>
									</select>
							</td>
							<input type="hidden" class="form-control input-sm text-right" name="precio_producto[<?php echo $id_registro_compra;?>]" value="<?php echo $precio_producto;?>" readonly>
							
							<td class="col-xs-1">
							Lote
							<input type="text" class="form-control input-sm" name="lote[<?php echo $id_registro_compra;?>]"  placeholder="Lote" value="<?php echo date('Ymd');?>">
							</td>
							<td class="col-xs-1">
							Caducidad
							<input type="date" class="form-control input-sm" name="caducidad[<?php echo $id_registro_compra;?>]" value="<?php echo date('Y-m-d');?>">
							</td>
							
							<td>
							Enviar
							<input class="form-control" type="checkbox" name="enviar_inventario[<?php echo $id_registro_compra;?>]"></td>
						</tr>
						<?php
					//}
				}
				?>
					<tr class="info">
						<td colspan="9"><span class="pull-right">
						<button type='submit' class="btn btn-warning" id="enviar_datos" ><span class="glyphicon glyphicon-log-out"></span> Enviar al inventario</button>
						</span></td>
					</tr>
					<tr>
					<td colspan="11" ><span class="pull-right">
					<?php
					 echo paginate($reload, $page, $total_pages, $adjacents);
					?></span></td>
				</tr>
			  </table>
			</div>
			</form>
			</div>
			<script>
			
	
			//para guardar en el inventario de entradas
				$( "#guarda_a_inventario" ).submit(function( event ) {
				  $('#enviar_datos').attr("disabled", true);
				 var parametros = $(this).serialize();
					 $.ajax({
							type: "POST",
							url: "../ajax/guardar_compras_inventario.php",
							data: parametros,
							 beforeSend: function(objeto){
								$("#resultados_enviar_inventario").html("Mensaje: Guardando...");
							  },
							success: function(datos){
							$("#resultados_enviar_inventario").html(datos);
							$('#enviar_datos').attr("disabled", false);
						  }
					});
				  event.preventDefault();
				})
				
			</script>
			<?php
			
		}
		
		}
?>