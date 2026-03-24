<?php
//Para mostrar el detalle de los productos agregados -->
if (isset($_GET['id_cliente'])) {
	$id_registro = "RECIBO" . $_GET['id_cliente'];
	$busca_detalle_facturar = mysqli_query($con, "SELECT dpf.id_detalle_pf as id_detalle_pf, ps.nombre_producto as producto, dpf.cant_producto as cant_producto, dpf.precio_producto as precio_producto, dpf.cuando_facturar as cuando_facturar FROM detalle_por_facturar  as dpf LEFT JOIN productos_servicios as ps ON dpf.id_producto = ps.id WHERE dpf.id_referencia = '" . $id_registro . "' ");
?>
	<div class="form-group">
		<div class="panel panel-info">
			<div class="panel-heading">Detalle de productos y servicios programados</div>
			<!--<div class="panel-body">-->
			<div class="table-responsive">
				<table class="table table-bordered">
					<tr class="info">
						<th>Producto</th>
						<th>Cantidad</th>
						<th>Precio</th>
						<th>Período</th>
						<th>Eliminar</th>
					</tr>
					<?php
					while ($detalle_a_facturar = mysqli_fetch_array($busca_detalle_facturar)) {
						$id_detalle_pf = $detalle_a_facturar['id_detalle_pf'];
						$producto = $detalle_a_facturar['producto'];
						$cant_producto = $detalle_a_facturar['cant_producto'];
						$precio_producto = $detalle_a_facturar['precio_producto'];
						$cuando_facturar = $detalle_a_facturar['cuando_facturar'];

						//buscar datos de cuando facturar
						$busca_cuando_facturar = "SELECT * FROM periodo_a_facturar WHERE codigo_periodo = '" . $cuando_facturar . "' ";
						$result = $con->query($busca_cuando_facturar);
						$cuando_se_facturar = mysqli_fetch_array($result);
						$a_facturar = $cuando_se_facturar['detalle_periodo'];
					?>
						<input type="hidden" value="<?php echo $id_detalle_pf; ?>" id="id_detalle_fp_recibo<?php echo $id_detalle_pf; ?>">
						<input type="hidden" value="<?php echo $_GET['id_cliente']; ?>" id="id_cliente_fp_recibo<?php echo $id_detalle_pf; ?>">
						<input type="hidden" value="<?php echo $precio_producto; ?>" id="precio_actual<?php echo $id_detalle_pf; ?>">
						<tr>
							<td><?php echo $producto; ?></td>
							<td class="text-right"><?php echo $cant_producto; ?></td>
							<td class="text-right col-sm-2">
								<input type="number" style="text-align:right; height:30px;" class="form-control input-sm" title="Precio" id="precio_nuevo<?php echo $id_detalle_pf; ?>" onchange="actualiza_precio_recibo_programado('<?php echo $id_detalle_pf; ?>');" value="<?php echo $precio_producto; ?>">
							</td>
							<td><?php echo $a_facturar; ?></td>
							<td class="text-center"><a href="#" class='btn btn-danger btn-md' title='Eliminar' onclick="eliminar_detalle_recibo_programado('<?php echo $id_detalle_pf; ?>')"><i class="glyphicon glyphicon-trash"></i></a></td>
						</tr>
					<?php
					}
					?>
				</table>
				<!--</div>-->
			</div>
		</div>
	</div>
<?php
}
?>