<div class="modal fade" data-backdrop="static" id="pedidos" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" style="overflow-y: scroll;">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="titleModal"></h4>
			</div>
			<div class="modal-body">
				<form class="form-horizontal" method="POST" id="guardar_pedido" name="guardar_pedido">
					<div id="mensaje_pedido"></div>
					<input type="hidden" name="idPedido" id="idPedido">
					<!--<div class="well well-sm" style="margin-bottom: 20px; margin-top: -5px;"> style="margin-bottom: 5px; margin-top: -5px;"-->
					<div class="well well-sm" style="margin-bottom: -15px; margin-top: -5px;">
						<div class="panel-body">
							<div class="col-sm-3">
								<div class="form-group">
									<div class="input-group">
										<span class="input-group-addon"><b>Status</b></span>
										<select class="form-control input-sm" id="listStatus" name="listStatus">
											<option value="1" selected>Pendiente</option>
											<option value="2">Procesado</option>
											<option value="3">Anulado</option>
										</select>
									</div>
								</div>
							</div>
							<div class="col-sm-9">
								<div class="form-group">
									<div class="input-group">
										<span class="input-group-addon"><b>Cliente *</b></span>
										<input type="hidden" name="id_cliente_pedido" id="id_cliente_pedido">
										<input type="text" class="form-control input-sm" name="cliente_pedido" id="cliente_pedido" onkeyup='buscar_clientes();' autocomplete="off" title="Buscar y seleccionar un cliente">
									</div>
								</div>
							</div>

							<div class="col-sm-12">
								<div class="form-group">
									<div class="input-group">
										<span class="input-group-addon"><b>Observaciones Cliente</b></span>
										<input type="text" class="form-control input-sm" name="observacion_pedido_cliente" id="observacion_pedido_cliente" title="Observaciones para cliente">
									</div>
								</div>
							</div>
							<div class="col-sm-8">
								<div class="form-group">
									<div class="input-group">
										<span class="input-group-addon"><b>Observaciones Internas</b></span>
										<input type="text" class="form-control input-sm" name="observacion_pedido_interna" id="observacion_pedido_interna" title="Observaciones internas">
									</div>
								</div>
							</div>
							<div class="col-sm-4">
								<div class="form-group">
									<div class="input-group">
										<span class="input-group-addon"><b>Responsable traslado*</b></span>
										<select class="form-control input-sm" id="responsable_traslado" name="responsable_traslado">
											<?php
											$con = conenta_login();
											$ruc_empresa = $_SESSION['ruc_empresa'];
											$defaultId = 2; //para que salga seleccionado la opcion 2 por default
											$sql_traslado = mysqli_query($con, "SELECT * FROM responsable_traslado WHERE ruc_empresa ='" . $ruc_empresa . "' and status ='1' order by nombre asc");
											foreach ($sql_traslado as $responsable) {
											?>
												<option value="<?php echo $responsable['id'] ?>" <?= $responsable['id'] == $defaultId ? 'selected' : ''; ?>><?php echo strtoupper($responsable['nombre']) ?></option>
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
										<span class="input-group-addon"><b>Fecha entrega*</b></span>
										<input type="text" class="form-control input-sm text-center" name="fecha_pedido" id="fecha_pedido" value="<?php echo date("d-m-Y"); ?>" title="fecha de pedido dd-mm-aaaa">
									</div>
								</div>
							</div>
							<div class="col-sm-4">
								<div class="form-group">
									<div class="input-group">
										<span class="input-group-addon"><b>Hora entrega desde*</b></span>
										<input type="text" class="form-control input-sm text-center" name="hora_entrega_desde" id="hora_entrega_desde" title="Hora de entrega desde HH:MM">
									</div>
								</div>
							</div>
							<div class="col-sm-4">
								<div class="form-group">
									<div class="input-group">
										<span class="input-group-addon"><b>Hora entrega hasta*</b></span>
										<input type="text" class="form-control input-sm text-center" name="hora_entrega_hasta" id="hora_entrega_hasta" title="Hora de entrega hasta HH:MM">
									</div>
								</div>
							</div>
						</div>
					</div>

					<div class="panel-body">
						<div class="form-group">
							<div class="panel panel-info" style="margin-bottom: -15px; margin-top: -20px;">
								<div class="table-responsive">
									<table class="table table-bordered">
										<tr class="info">
											<th style="padding: 2px;">Producto</th>
											<th style="padding: 2px;" class="text-center">Cantidad</th>
											<th style="padding: 2px;" class="text-center">Agregar</th>
										</tr>

										<input type="hidden" name="id_producto" id="id_producto">
										<input type="hidden" name="codigo_producto" id="codigo_producto">
										<td class="col-xs-9">
											<input type="text" class="form-control input-sm" id="nombre_producto" name="nombre_producto" placeholder="Producto" onkeyup="buscar_productos();">
										</td>
										<td class="col-xs-2">
											<div class="pull-right">
												<input type="number" class="form-control input-sm" style="text-align:right;" title="Ingrese cantidad" name="cantidad_agregar" id="cantidad_agregar" placeholder="Cantidad" value="1">
											</div>
										</td>
										<td class="col-sm-1" style="text-align:center;">
											<button type="button" class="btn btn-info btn-sm" title="Agregar productos" onclick="agregar_item();"><span class="glyphicon glyphicon-plus"></span></button>
										</td>
									</table>
								</div>
							</div>
						</div>
					</div>

					<div id="muestra_detalle_pedido"></div><!-- Carga gif animado -->
					<div class="outer_divdet_pedido"></div><!-- Datos ajax Final -->
			</div>
			<div class="modal-footer">
				<span id="loader_pedido"></span>
				<button type="button" class="btn btn-default" data-dismiss="modal" id="cerrar_pedido" title="Cancelar">Cerrar</button>
				<button type="submit" class="btn btn-info" id="btnActionForm" class="btn btn-primary" title=""><span id="btnText"></span></button>
			</div>
			</form>
		</div>
	</div>
</div>


<!-- Modal -->
<div class="modal fade" id="modalViewPedido" data-backdrop="static" data-keyboard="false" aria-labelledby="exampleModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h5 class="modal-title"><i class="glyphicon glyphicon-list"></i> Detalle del pedido</h5>
			</div>
			<div class="modal-body">
				<div id="detalle_pedido"></div><!-- Carga gif animado -->
				<div class="outer_detalle_pedido"></div><!-- Datos ajax Final -->
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal" title="Cerrar modal pedido"> Cerrar</button>
			</div>
		</div>
	</div>
</div>