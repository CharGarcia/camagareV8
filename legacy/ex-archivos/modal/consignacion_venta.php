<?php
//para traer la serie de la sucursal primera
$con = conenta_login();
$ruc_empresa = $_SESSION['ruc_empresa'];
$sql_empresa = mysqli_query($con, "SELECT * FROM empresas WHERE ruc = '" . $ruc_empresa . "' ");
$row_empresa = mysqli_fetch_array($sql_empresa);
$nombre_comercial = $row_empresa['nombre_comercial'];
ini_set('date.timezone', 'America/Guayaquil');

?>
<div class="modal fade" data-backdrop="static" id="nueva_consignacion_venta" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" style="overflow-y: scroll;" data-keyboard="false">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<!-- <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button> -->
				<h4 class="modal-title" id="myModalLabel"><i class="glyphicon glyphicon-list-alt"></i> Nueva consignación en ventas</h4>
			</div>
			<div class="modal-body">
				<form class="form-horizontal" method="POST" id="guardar_consignacion_venta" name="guardar_consignacion_venta">
					<div id="mensajes_consignacion_venta"></div>
					<input type="hidden" name="codigo_unico" id="codigo_unico">
					<div class="well well-sm" style="margin-bottom: -25px; margin-top: -5px;">
						<div class="panel-body">
							<div class="col-sm-2">
								<div class="form-group">
									<div class="input-group">
										<button type="button" class="btn btn-info btn-sm" title="Agregar pedidos" data-toggle="modal" data-target="#detallePedido" onclick="carga_modal_pedidos();"><span class="glyphicon glyphicon-plus"></span> Pedidos</button>
									</div>
								</div>
							</div>
							<div class="col-sm-3">
								<div class="form-group">
									<div class="input-group">
										<span class="input-group-addon"><b>Fecha</b></span>
										<input type="text" class="form-control input-sm text-center" name="fecha_consignacion_salida" id="fecha_consignacion_salida" value="<?php echo date("d-m-Y"); ?>">
									</div>
								</div>
							</div>
							<div class="col-sm-3">
								<div class="form-group">
									<div class="input-group">
										<span class="input-group-addon"><b>Serie</b></span>
										<select class="form-control" style="height:30px;" title="Seleccione serie." name="serie_consignacion" id="serie_consignacion">
											<?php
											$sql_sucursal = mysqli_query($con, "SELECT * FROM sucursales WHERE ruc_empresa ='" . $ruc_empresa . "'");
											while ($row = mysqli_fetch_array($sql_sucursal)) {
											?>
												<option value="<?php echo $row['serie'] ?>" selected><?php echo strtoupper($row['serie']) ?></option>
											<?php
											}
											?>
										</select>
									</div>
								</div>
							</div>
							<div class="col-sm-4">
								<div class="form-group">
									<div class="input-group">
										<span class="input-group-addon"><b>Asesor</b></span>
										<select class="form-control input-sm" id="responsable_traslado" name="responsable_traslado" required>
											<option value="0" Selected>SELECCIONAR</option>
											<?php
											$sql_asesor = mysqli_query($con, "SELECT * FROM vendedores WHERE ruc_empresa ='" . $ruc_empresa . "' order by nombre asc");
											while ($row = mysqli_fetch_array($sql_asesor)) {
											?>
												<option value="<?php echo $row['id_vendedor'] ?>"><?php echo strtoupper($row['nombre']) ?></option>
											<?php
											}
											?>
										</select>
									</div>
								</div>
							</div>
							<div class="col-sm-12">
								<div class="form-group">
									<div class="input-group">
										<span class="input-group-addon"><b>Cliente</b></span>
										<input type="hidden" name="id_cliente_consignacion_venta" id="id_cliente_consignacion_venta">
										<input type="text" class="form-control input-sm" name="cliente_consignacion_venta" id="cliente_consignacion_venta" onkeyup='buscar_clientes();' autocomplete="off">
									</div>
								</div>
							</div>
							<div class="col-sm-6">
								<div class="form-group">
									<div class="input-group">
										<span class="input-group-addon"><b>Pto. Partida</b></span>
										<input type="text" class="form-control input-sm" name="punto_partida" id="punto_partida" value="<?php echo $nombre_comercial; ?>">
									</div>
								</div>
							</div>
							<div class="col-sm-6">
								<div class="form-group">
									<div class="input-group">
										<span class="input-group-addon"><b>Pto. Llegada</b></span>
										<input type="text" class="form-control input-sm" name="punto_llegada" id="punto_llegada">
									</div>
								</div>
							</div>
							<div class="col-sm-9">
								<div class="form-group">
									<div class="input-group">
										<span class="input-group-addon"><b>Observaciones</b></span>
										<input type="text" class="form-control input-sm" name="observacion_consignacion_venta" id="observacion_consignacion_venta">
									</div>
								</div>
							</div>
							<div class="col-sm-3">
								<div class="form-group">
									<div class="input-group">
										<span class="input-group-addon"><b>Bodega</b></span>
										<select class="form-control" style="text-align:right; width: auto; height:30px;" title="Seleccione bodega" name="bodega_agregar" id="bodega_agregar">
											<?php
											//$sql_bodega = mysqli_query($con, "SELECT * FROM bodega WHERE ruc_empresa ='" . $ruc_empresa . "' order by nombre_bodega asc");
											$sql_bodega = mysqli_query($con, "SELECT bod.id_bodega as id_bodega, bod.nombre_bodega as nombre_bodega FROM bodega as bod LEFT JOIN bodega_restringida as res ON res.id_bodega = bod.id_bodega and res.id_usuario='" . $id_usuario . "' WHERE res.id_usuario is null and bod.ruc_empresa ='" . $ruc_empresa . "' and bod.status ='1' order by bod.nombre_bodega asc");
											while ($row = mysqli_fetch_array($sql_bodega)) {
											?>
												<option value="<?php echo $row['id_bodega'] ?>"><?php echo strtoupper($row['nombre_bodega']) ?></option>
											<?php
											}
											?>
										</select>
									</div>
								</div>
							</div>
							<div class="col-sm-4">
								<div class="form-group">
									<div class="input-group">
										<span class="input-group-addon"><b>Fecha entrega</b></span>
										<input type="text" class="form-control input-sm text-center" name="fecha_pedido" id="fecha_pedido" value="<?php echo date("d-m-Y"); ?>" title="fecha de entrega del pedido dd-mm-aaaa">
									</div>
								</div>
							</div>
							<div class="col-sm-2">
								<div class="form-group">
									<div class="input-group">
										<span class="input-group-addon"><b>H. desde</b></span>
										<input type="text" class="form-control input-sm" name="hora_entrega_desde" id="hora_entrega_desde" title="Hora de entrega del pedido desde HH:MM">
									</div>
								</div>
							</div>
							<div class="col-sm-2">
								<div class="form-group">
									<div class="input-group">
										<span class="input-group-addon"><b>H. hasta</b></span>
										<input type="text" class="form-control input-sm" name="hora_entrega_hasta" id="hora_entrega_hasta" title="Hora de entrega del pedido hasta HH:MM">
									</div>
								</div>
							</div>
							<div class="col-sm-4">
								<div class="form-group">
									<div class="input-group">
										<span class="input-group-addon"><b>Traslado por</b></span>
										<select class="form-control input-sm" id="traslado" name="traslado">
											<?php
											$sql_traslado = mysqli_query($con, "SELECT * FROM responsable_traslado WHERE ruc_empresa ='" . $ruc_empresa . "' and status ='1' order by nombre asc");
											while ($row = mysqli_fetch_array($sql_traslado)) {
											?>
												<option value="<?php echo $row['id'] ?>" selected><?php echo strtoupper($row['nombre']) ?></option>
											<?php
											}
											?>
										</select>
									</div>
								</div>
							</div>
						</div>
					</div>

					<div class="panel-body">
						<div class="form-group">
							<div class="panel panel-info" style="margin-bottom: -25px; margin-top: -5px;">
								<div class="table-responsive">
									<table class="table table-bordered">
										<tr class="info">
											<th style="padding: 2px;">Producto</th>
											<th style="padding: 2px;" class="text-center" id="titulo_lote">Lote</th>
											<th style="padding: 2px;" class="text-center">Precios</th>
											<th style="padding: 2px;" class="text-center">Precio</th>
											<th style="padding: 2px;" class="text-center" id="titulo_caducidad">Caducidad</th>
											<th style="padding: 2px;" class="text-center">Cantidad</th>
											<th style="padding: 2px;" class="text-center">NUP</th>
											<th style="padding: 2px;" class="text-center" id="titulo_existencia">Existencia</th>
											<th style="padding: 2px;" class="text-center">Agregar</th>
										</tr>

										<input type="hidden" name="id_producto" id="id_producto">
										<input type="hidden" id="inventario" name="inventario">
										<input type="hidden" id="muestra_lote">
										<input type="hidden" id="medida_agregar">
										<input type="hidden" id="muestra_bodega">
										<input type="hidden" id="muestra_vencimiento">
										<input type="hidden" name="stock_tmp" id="stock_tmp">
										<td class="col-xs-5" style="padding: 1px;">
											<input type="text" class="form-control input-sm" id="nombre_producto" name="nombre_producto" placeholder="Producto" onkeyup="buscar_productos();">
										</td>

										<td class="col-xs-1" style="padding: 1px;" id="lista_lote">
											<select class="form-control" style="text-align:right; width: auto; height:30px;" title="Seleccione lote" name="lote_agregar" id="lote_agregar">
											</select>
										</td>
										<td class="col-xs-2" style="padding: 1px;" id="lista_caducidad">
											<select class="form-control" style="text-align:right; width: auto; height:30px;" title="Seleccione caducidad" name="caducidad_agregar" id="caducidad_agregar">
											</select>
										</td>
										<td class="col-xs-2" style="padding: 1px;">
											<select class="form-control input-sm" style="text-align:right;" title="Seleccione precios" name="precio_agregar" id="precio_agregar">
											</select>
										</td>
										<td class="col-xs-2" style="padding: 1px;">
											<div class="pull-right">
												<input type="number" class="form-control input-sm" style="text-align:right;" title="Ingrese precio" name="precio_producto_consignacion" id="precio_producto_consignacion" placeholder="Precio">
											</div>
										</td>
										<td class="col-xs-1" style="padding: 1px;">
											<div class="pull-right">
												<input type="number" class="form-control input-sm" style="text-align:right;" title="Ingrese cantidad" name="cantidad_agregar" id="cantidad_agregar" placeholder="Cantidad">
											</div>
										</td>
										<td class="col-xs-1" style="padding: 1px;">
											<div class="pull-right">
												<input type="text" class="form-control input-sm" style="text-align:right;" title="Ingrese número único de producto" name="nup" id="nup" placeholder="nup">
											</div>
										</td>
										<td class="col-xs-2" id="lista_existencia" style="padding: 1px;">
											<input type="text" style="text-align:right;" class="form-control input-sm" id="existencia_producto" name="existencia_producto" placeholder="0">
										</td>
										<td class="col-xs-1" style="text-align:center; padding: 1px;">
											<button type="button" class="btn btn-info btn-sm" title="Agregar productos" onclick="agregar_item();"><span class="glyphicon glyphicon-plus"></span></button>
										</td>
									</table>
								</div>
							</div>
						</div>
					</div>
					<div id="muestra_detalle_consignacion"></div><!-- Carga gif animado -->
					<div class="outer_divdet_consignacion"></div><!-- Datos ajax Final -->
			</div>
			<div class="modal-footer">
				<span id="loader_consignacion_venta"></span>
				<button type="button" class="btn btn-default" data-dismiss="modal" aria-label="Close" id="cerrar_detalle_consignacion">Cerrar</button>
				<button type="submit" class="btn btn-info" id="guardar_datos">Guardar</button>
			</div>
			</form>
		</div>
	</div>
</div>

<div class="modal fade" data-backdrop="static" id="detalleConsignacion" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="myModalLabel"><i class="glyphicon glyphicon-list-alt"></i> Detalle de consignación</h4>
			</div>
			<div class="modal-body">
				<div class="outer_divdet"></div><!-- Datos ajax Final -->
			</div>
			<div class="modal-footer">
				<span id="loaderdet"></span><!-- Carga gif animado -->
				<button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" data-backdrop="static" id="detallePedido" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="myModalLabel"><i class="glyphicon glyphicon-list-alt"></i> Detalle del pedido</h4>
			</div>
			<div class="modal-body">
				<div class="container-fluid">
					<form class="form-horizontal" method="POST" id="detalle_pedido">
						<div class="form-group row">
							<div class="col-sm-3">
								<div class="input-group">
									<span class="input-group-addon"><b> Bodega</b></span>
									<select class="form-control input-sm" title="Seleccione bodega" name="bodega_pedido" id="bodega_pedido">
										<?php
										$sql_bodega = mysqli_query($con, "SELECT bod.id_bodega as id_bodega, bod.nombre_bodega as nombre_bodega FROM bodega as bod LEFT JOIN bodega_restringida as res ON res.id_bodega = bod.id_bodega and res.id_usuario='" . $id_usuario . "' WHERE res.id_usuario is null and bod.ruc_empresa ='" . $ruc_empresa . "' and bod.status ='1' order by bod.nombre_bodega asc");
										while ($o = mysqli_fetch_array($sql_bodega)) {
										?>
											<option value="<?php echo $o['id_bodega'] ?>"><?php echo strtoupper($o['nombre_bodega']) ?></option>
										<?php
										}
										?>
									</select>
								</div>
							</div>
							<div class="col-sm-3">
								<div class="input-group">
									<span class="input-group-addon"><b> No. pedido</b></span>
									<input type="text" class="form-control input-sm text-right" name="numero_pedido" id="numero_pedido">
								</div>
							</div>
							<div class="col-sm-2">
								<div class="input-group">
									<button type="button" class="btn btn-info btn-sm" title="Agregar pedidos" onclick="mostrar_detalle_pedido()"><span class="glyphicon glyphicon-search"></span> Mostrar pedido</button>
								</div>
							</div>
							<div class="col-sm-2">
								<div class="input-group">
									<span id="loaderdetPedido"></span>
								</div>
							</div>

						</div>
					</form>
				</div>
				<div id="muestra_detalle_pedido"></div><!-- Carga gif animado -->
				<div class="outer_divdetpedido"></div><!-- Datos ajax Final -->
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" data-backdrop="static" id="detalleEntrega" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
	<div class="modal-dialog modal-md" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="myModalLabel"><i class="glyphicon glyphicon-list-alt"></i> Detalle de entrega</h4>
			</div>
			<div class="modal-body">
				<div class="container-fluid">
					<form class="form-horizontal" method="POST" id="detalle_entregado">
						<div class="form-group row">
							<div class="col-sm-6">
								<div class="input-group">
									<span class="input-group-addon"><b> Latitud</b></span>
									<input type="text" class="form-control input-sm text-right" name="latitud" id="latitud" readonly>
								</div>
							</div>
							<div class="col-sm-6">
								<div class="input-group">
									<span class="input-group-addon"><b> Longitud</b></span>
									<input type="text" class="form-control input-sm text-right" name="longitud" id="longitud" readonly>
								</div>
							</div>

						</div>
						<div class="form-group row">
							<div class="col-sm-5">
								<div class="input-group">
									<span class="input-group-addon"><b> Fecha</b></span>
									<input type="text" class="form-control input-sm text-right" name="fecha_hora_entregado" id="fecha_hora_entregado" readonly>
								</div>
							</div>
							<div class="col-sm-7">
								<div class="input-group">
									<span class="input-group-addon"><b> Nombre</b></span>
									<input type="text" class="form-control input-sm text-left" name="encargado_entrega" id="encargado_entrega" readonly>
								</div>
							</div>
						</div>
						<div class="form-group row">
							<div class="col-sm-12">
								<div class="input-group">
									<span class="input-group-addon"><b> Dirección</b></span>
									<input type="text" class="form-control input-sm text-left" name="direccion_entregado" id="direccion_entregado" readonly>
								</div>
							</div>
						</div>
						<div class="form-group row">
							<div class="col-sm-12">
								<div class="input-group">
									<span class="input-group-addon"><b> Observaciones</b></span>
									<input type="text" class="form-control input-sm text-left" name="observaciones_entrega" id="observaciones_entrega" readonly>
								</div>
							</div>
						</div>
					</form>
				</div>

				<div class="outer_divdetEntrega"></div><!-- Datos ajax Final -->
			</div>
			<div class="modal-footer">
				<span id="loaderdetEntrega"></span><!-- Carga gif animado -->
				<button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>
<script>
	$('#responsable_traslado').change(function() {
		var id_asesor = $("#responsable_traslado").val();
		var codigo_unico = $("#codigo_unico").val();
		if (codigo_unico !== "") {
			if (confirm("Seguro que desea cambiar de asesor?")) {
				$.ajax({
					type: "POST",
					url: "../ajax/detalle_consignaciones.php",
					data: "action=actualizar_asesor&id_asesor=" + id_asesor + "&codigo_unico=" + codigo_unico,
					beforeSend: function(objeto) {
						$("#muestra_detalle_consignacion").html("Actualizando...");
					},
					success: function(datos) {
						$("#muestra_detalle_consignacion").html(datos);
					}
				});
			}
		}
	})
</script>