<?php
$con = conenta_login();
$ruc_empresa = $_SESSION['ruc_empresa'];
?>
<div class="modal fade" data-backdrop="static" id="detalleFacturaMecanica" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close" onclick="load(1);"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-list-alt'></i> Detalles de factura <span id="mensajes_ordenes_mecanica"></span></h4>
			</div>
			
			<div class="modal-body">
				<form method="POST" class="form-horizontal" role="form">
				<div class="well well-sm" style="margin-bottom: -10px; margin-top: -10px; height: 14%;">
						<div class="form-group row" style="width: auto;">
							<div class="col-md-3">
								<div class="input-group">
									<span class="input-group-addon"><b>Cliente</b></span>
									<input type="hidden" name="id_cliente_mecanica" id="id_cliente_mecanica">
									<input type="text" class="form-control input-sm" name="cliente_mecanica" id="cliente_mecanica" onkeyup='buscar_clientes();' autocomplete="off">
								</div>
							</div>
							<div class="col-md-3">
								<div class="input-group">
									<span class="input-group-addon"><b>Fecha</b></span>
									<input type="text" class="form-control input-sm text-center" name="fecha_mecanica" id="fecha_mecanica" value="<?php echo date("d-m-Y"); ?>">
								</div>
							</div>
							<div class="col-md-3">
								<div class="input-group">
									<span class="input-group-addon"><b>Serie</b></span>
									<select class="form-control input-sm" title="Seleccione serie" name="serie_mecanica" id="serie_mecanica">
										<?php
										$sql = mysqli_query($con, "SELECT * FROM sucursales WHERE ruc_empresa ='" . $ruc_empresa . "'");
										while ($o = mysqli_fetch_array($sql)) {
										?>
											<option value="<?php echo $o['serie'] ?>" selected><?php echo strtoupper($o['serie']) ?></option>
										<?php
										}
										?>
									</select>
								</div>
							</div>
							<div class="col-md-3">
								<div class="input-group">
									<span class="input-group-addon"><b>Bodega</b></span>
									<select class="form-control input-sm" name="bodega_mecanica" id="bodega_mecanica">
										<option value="">Seleccione</option>
										<?php
										$sql_bodega = mysqli_query($con, "SELECT * FROM bodega WHERE ruc_empresa ='" . $ruc_empresa . "'");
										while ($row_bodega = mysqli_fetch_array($sql_bodega)) {
										?>
											<option value="<?php echo $row_bodega['id_bodega'] ?>" selected><?php echo strtoupper($row_bodega['nombre_bodega']) ?></option>
										<?php
										}
										?>
									</select>
								</div>
							</div>
							</div>
						</div>

						<div class="panel panel-info"><!--style="margin-bottom: -15px; margin-top: -20px;"-->
							<div class="table-responsive">
								<table class="table table-bordered">
									<tr class="info">
										<th style="padding: 2px;">Producto-servicio</th>
										<th style="padding: 2px;" class="text-center" id="titulo_bodega">Bodega</th>
										<th style="padding: 2px;" class="text-center">Cantidad</th>
										<th style="padding: 2px;" class="text-center">Precio sin IVA</th>
										<th style="padding: 2px;" class="text-center">Precio con IVA</th>
										<th style="padding: 2px;" class="text-center">Existencia</th>
										<th style="padding: 2px;" class="text-center">Agregar</th>
									</tr>
									<input type="hidden" name="codigo_unico_factura" id="codigo_unico_factura">
									<input type="hidden" name="id_producto_mecanica" id="id_producto_mecanica">
									<input type="hidden" name="precio_tmp" id="precio_tmp">
									<input type="hidden" name="tipo_producto_mecanica" id="tipo_producto_mecanica">
									<input type="hidden" id="inventario" name="inventario" value="SI">
									<input type="hidden" id="medida_agregar" name="medida_agregar">
									<input type="hidden" id="porcentaje_iva">
									<td class='col-xs-5'>
										<input type="text" class="form-control input-sm" id="nombre_producto_servicio" name="nombre_producto_servicio" placeholder="Producto o servicio" onkeyup="buscar_productos();">
									</td>
									<td class='col-xs-1'>
										<div class="pull-right">
											<input type="number" class="form-control input-sm" style="text-align:right;" title="Ingrese cantidad" name="cantidad_agregar" id="cantidad_agregar" value="1">
										</div>
									</td>
									<td class='col-xs-2'>
										<input type="number" style="text-align:right;" class="form-control input-sm" id="precio_agregar" name="precio_agregar" oninput="precio_sin_iva();">
									</td>
									<td class='col-xs-2'>
										<input type="number" style="text-align:right;" class="form-control input-sm" id="precio_agregar_iva" name="precio_agregar_iva" oninput="precio_con_iva();">
									</td>
									<td class="col-xs-2">
										<input type="text" style="text-align:right;" class="form-control input-sm" id="existencia_producto" name="existencia_producto" readonly>
									</td>
									<td class="col-sm-1" style="text-align:center;">
										<button type="button" class="btn btn-info btn-sm" title="Agregar productos" onclick="agregar_item_factura_mecanica()"><span class="glyphicon glyphicon-plus"></span></button>
									</td>
								</table>
							</div>
						</div>
					<div class="outer_divdet_mecanica"></div><!-- Datos ajax Final -->
			</div>
			<div class="modal-footer">
				<span id="muestra_detalle_mecanica"></span><!-- Carga gif animado -->
				<button type="button" onclick="generar_factura();" class="btn btn-success" id="guardar_datos_factura">Generar factura</button>
				<button type="button" onclick="generar_recibo();" class="btn btn-info" id="guardar_datos_recibo">Generar recibo</button>
				<button type="button" class="btn btn-default" data-dismiss="modal" onclick="load(1);">Cerrar</button><!--id="cerrar_detalle_factura_mecanica"-->
			</div>
			</form>
		</div>
	</div>
</div>