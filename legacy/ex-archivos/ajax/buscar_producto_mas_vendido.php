<?php
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
include("../conexiones/conectalogin.php");
$con = conenta_login();
$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';

	if($action == 'producto_mas_vendido'){
		 $desde = date("Y/m/d", strtotime($_REQUEST['desde']));//date('Y-m-d H:i:s', strtotime($_REQUEST['desde']));
		 $hasta = date("Y/m/d", strtotime($_REQUEST['hasta']));//date('Y-m-d H:i:s', strtotime($_REQUEST['hasta']));
		 $cantidad = mysqli_real_escape_string($con,(strip_tags($_REQUEST['cantidad'], ENT_QUOTES)));
		 $id_cliente = mysqli_real_escape_string($con,(strip_tags($_REQUEST['id_cliente'], ENT_QUOTES)));
		 $id_producto = mysqli_real_escape_string($con,(strip_tags($_REQUEST['id_producto'], ENT_QUOTES)));

		 //cuando no hay ni cliente ni producto
		 if (empty($id_cliente) && empty($id_producto)){
		 
			$buscar_mas_vendidos = mysqli_query($con, "SELECT med.nombre_medida as nombre_medida,
			cuefac.codigo_producto as codigo_producto, cuefac.nombre_producto as nombre_producto, cuefac.id_medida_salida as medida, 
			sum(cuefac.cantidad_factura) as total_cantidad, sum(cuefac.subtotal_factura) as subtotal 
			FROM cuerpo_factura as cuefac INNER JOIN encabezado_factura as encfac 
			ON encfac.serie_factura = cuefac.serie_factura AND encfac.secuencial_factura = cuefac.secuencial_factura 
			LEFT JOIN unidad_medida as med ON med.id_medida=cuefac.id_medida_salida WHERE encfac.ruc_empresa='".$ruc_empresa."' 
			and cuefac.ruc_empresa='".$ruc_empresa."'and DATE_FORMAT(encfac.fecha_factura, '%Y/%m/%d') BETWEEN '".$desde."' 
			AND '".$hasta."' group by cuefac.codigo_producto order by sum(cuefac.cantidad_factura) desc, sum(cuefac.subtotal_factura) desc LIMIT 0, $cantidad");
		
			?>
			<div class="panel panel-info">
			<div class="table-responsive">
			  <table class="table table-hover">
				<tr  class="info">
					<th>Código</th>
					<th>Producto o Servicio</th>
					<th>Cantidad</th>
					<th>Subtotal</th>
					<th>Medida</th>
					
				</tr>
				<?php
				while ($row=mysqli_fetch_array($buscar_mas_vendidos)){
						$nombre_producto=$row['nombre_producto'];
						$codigo_producto=$row['codigo_producto'];
						$total_cantidad=$row['total_cantidad'];
						$subtotal=$row['subtotal'];
						$nombre_medida=$row['nombre_medida'];
					?>
					<tr>			
						<td><?php echo $codigo_producto; ?></td>
						<td><?php echo $nombre_producto; ?></td>
						<td><?php echo $total_cantidad; ?></td>
						<td><?php echo $subtotal; ?></td>
						<td><?php echo $nombre_medida; ?></td>
					</tr>
					<?php
				}
				?>
			  </table>
			</div>
			</div>
			<?php

		}
		 
		//cuando si hay cliente pero no hay producto
		 
		 if (!empty($id_cliente) && empty($id_producto)){
			$condicion_cliente=" and encfac.id_cliente=".$id_cliente;	
			$buscar_mas_vendidos = mysqli_query($con, "SELECT med.nombre_medida as nombre_medida,
			cuefac.codigo_producto as codigo_producto, cuefac.nombre_producto as nombre_producto, cuefac.id_medida_salida as medida, 
			sum(cuefac.cantidad_factura) as total_cantidad, sum(cuefac.subtotal_factura) as subtotal 
			FROM cuerpo_factura as cuefac INNER JOIN encabezado_factura as encfac 
			ON encfac.serie_factura = cuefac.serie_factura AND encfac.secuencial_factura = cuefac.secuencial_factura 
			LEFT JOIN unidad_medida as med ON med.id_medida=cuefac.id_medida_salida WHERE encfac.ruc_empresa='".$ruc_empresa."' 
			and cuefac.ruc_empresa='".$ruc_empresa."'and DATE_FORMAT(encfac.fecha_factura, '%Y/%m/%d') BETWEEN '".$desde."' 
			AND '".$hasta."' $condicion_cliente group by cuefac.codigo_producto, encfac.id_cliente 
			order by sum(cuefac.cantidad_factura) desc LIMIT 0, $cantidad");
			
			?>
			<div class="panel panel-info">
			<div class="table-responsive">
			  <table class="table table-hover">
				<tr  class="info">
					<th>Código</th>
					<th>Producto o Servicio</th>
					<th>Cantidad</th>
					<th>Subtotal</th>
					<th>Medida</th>
					
				</tr>
				<?php
				while ($row=mysqli_fetch_array($buscar_mas_vendidos)){
						$nombre_producto=$row['nombre_producto'];
						$codigo_producto=$row['codigo_producto'];
						$total_cantidad=$row['total_cantidad'];
						$subtotal=$row['subtotal'];
						$nombre_medida=$row['nombre_medida'];
					?>
					<tr>			
						<td><?php echo $codigo_producto; ?></td>
						<td><?php echo $nombre_producto; ?></td>
						<td><?php echo $total_cantidad; ?></td>
						<td><?php echo $subtotal; ?></td>
						<td><?php echo $nombre_medida; ?></td>
					</tr>
					<?php
				}
				?>
			  </table>
			</div>
			</div>
			<?php
		
		}

			//cuando no hay cliente pero si producto, se debe mostrar los clientes	 
		if (empty($id_cliente) && !empty($id_producto)){
			$condicion_producto=" and cuefac.id_producto=".$id_producto;	
			$buscar_mas_vendidos = mysqli_query($con, "SELECT med.nombre_medida as nombre_medida, cli.nombre as cliente,
			cuefac.codigo_producto as codigo_producto, cuefac.nombre_producto as nombre_producto, cuefac.id_medida_salida as medida, 
			sum(cuefac.cantidad_factura) as total_cantidad, sum(cuefac.subtotal_factura) as subtotal 
			FROM cuerpo_factura as cuefac INNER JOIN encabezado_factura as encfac 
			ON encfac.serie_factura = cuefac.serie_factura AND encfac.secuencial_factura = cuefac.secuencial_factura 
			INNER JOIN clientes as cli ON cli.id=encfac.id_cliente
			LEFT JOIN unidad_medida as med ON med.id_medida=cuefac.id_medida_salida WHERE encfac.ruc_empresa='".$ruc_empresa."' 
			and cuefac.ruc_empresa='".$ruc_empresa."'and DATE_FORMAT(encfac.fecha_factura, '%Y/%m/%d') BETWEEN '".$desde."' 
			AND '".$hasta."' $condicion_producto group by cuefac.codigo_producto, encfac.id_cliente 
			order by sum(cuefac.cantidad_factura) desc, sum(cuefac.subtotal_factura) desc LIMIT 0, $cantidad");
			
			?>
			<div class="panel panel-info">
			<div class="table-responsive">
			  <table class="table table-hover">
				<tr  class="info">
					<th>Cliente</th>
					<th>Cantidad</th>
					<th>Subtotal</th>
					<th>Medida</th>
					
				</tr>
				<?php
				while ($row=mysqli_fetch_array($buscar_mas_vendidos)){
						$cliente=$row['cliente'];
						$total_cantidad=$row['total_cantidad'];
						$subtotal=$row['subtotal'];
						$nombre_medida=$row['nombre_medida'];
					?>
					<tr>		
						<td><?php echo $cliente; ?></td>	
						<td><?php echo $total_cantidad; ?></td>
						<td><?php echo $subtotal; ?></td>
						<td><?php echo $nombre_medida; ?></td>
					</tr>
					<?php
				}
				?>
			  </table>
			</div>
			</div>
			<?php
		
		}

			//cuando el cliente y el producto esta lleno	 
			if (!empty($id_cliente) && !empty($id_producto)){
				$condicion_producto=" and cuefac.id_producto=".$id_producto;
				$condicion_cliente=" and encfac.id_cliente=".$id_cliente;		
				$buscar_mas_vendidos = mysqli_query($con, "SELECT encfac.fecha_factura as fecha_documento, med.nombre_medida as nombre_medida, 
				cuefac.id_medida_salida as medida, sum(cuefac.cantidad_factura) as total_cantidad, sum(cuefac.subtotal_factura) as subtotal 
				FROM cuerpo_factura as cuefac INNER JOIN encabezado_factura as encfac 
				ON encfac.serie_factura = cuefac.serie_factura AND encfac.secuencial_factura = cuefac.secuencial_factura 
				INNER JOIN clientes as cli ON cli.id=encfac.id_cliente
				LEFT JOIN unidad_medida as med ON med.id_medida=cuefac.id_medida_salida WHERE encfac.ruc_empresa='".$ruc_empresa."' 
				and cuefac.ruc_empresa='".$ruc_empresa."'and DATE_FORMAT(encfac.fecha_factura, '%Y/%m/%d') BETWEEN '".$desde."' 
				AND '".$hasta."' $condicion_cliente $condicion_producto group by DATE_FORMAT(encfac.fecha_factura, '%Y/%m/%d'), cuefac.codigo_producto, encfac.id_cliente 
				order by sum(cuefac.cantidad_factura) desc, encfac.fecha_factura desc LIMIT 0, $cantidad");
				
				?>
				<div class="panel panel-info">
				<div class="table-responsive">
				  <table class="table table-hover">
					<tr  class="info">
						<th>Fecha</th>
						<th>Cantidad</th>
						<th>Subtotal</th>
						<th>Medida</th>
						
					</tr>
					<?php
					while ($row=mysqli_fetch_array($buscar_mas_vendidos)){
							$fecha_documento=$row['fecha_documento'];
							$total_cantidad=$row['total_cantidad'];
							$subtotal=$row['subtotal'];
							$nombre_medida=$row['nombre_medida'];
						?>
						<tr>		
							<td><?php echo  date("d-m-Y", strtotime($fecha_documento)); ?></td>	
							<td><?php echo $total_cantidad; ?></td>
							<td><?php echo $subtotal; ?></td>
							<td><?php echo $nombre_medida; ?></td>
						</tr>
						<?php
					}
					?>
				  </table>
				</div>
				</div>
				<?php
			
			}
			
	}
	
?>