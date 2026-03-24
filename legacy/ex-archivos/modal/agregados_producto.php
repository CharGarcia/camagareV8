<div class="modal fade" data-backdrop="static" id="agregados" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" style="overflow-y: scroll;">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="titleModalAgregados_producto"></h4>
			</div>
			<div class="modal-body">
				<form class="form-horizontal" method="POST" id="guardar_agregados_producto" name="guardar_agregados_producto">
					<div id="mensaje_agregados_producto"></div>
					<input type="hidden" name="idAgregados_producto" id="idAgregados_producto">
					<!--<div class="well well-sm" style="margin-bottom: 20px; margin-top: -5px;"> style="margin-bottom: 5px; margin-top: -5px;"-->
					<div class="well well-sm" style="margin-bottom: -15px; margin-top: -5px;">
						<div class="panel-body">
							<div class="col-sm-9">
								<div class="form-group">
									<div class="input-group">
										<span class="input-group-addon"><b>Producto</b></span>
										<input type="hidden" name="id_producto_principal" id="id_producto_principal">
										<input type="text" class="form-control input-sm" name="nombre_producto_principal" id="nombre_producto_principal" placeholder="Producto principal" onkeyup="buscar_producto_principal();">
									</div>
								</div>
							</div>
							<div class="col-sm-3">
								<div class="form-group">
									<div class="input-group">
										<span class="input-group-addon"><b>Status</b></span>
										<select class="form-control input-sm" id="listStatus" name="listStatus">
											<option value="1" selected>Activo</option>
											<option value="2">Pasivo</option>
										</select>
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
											<th style="padding: 2px;" class="text-center">Medida</th>
											<th style="padding: 2px;" class="text-center">Agregar</th>
										</tr>
										<input type="hidden" name="id_producto" id="id_producto">
										<input type="hidden" name="codigo_producto" id="codigo_producto">
										<td class="col-xs-7">
											<input type="text" class="form-control input-sm" id="nombre_producto" name="nombre_producto" placeholder="Producto" onkeyup="buscar_productos();">
										</td>
										<td class="col-xs-2">
											<div class="pull-right">
												<input type="text" class="form-control input-sm" style="text-align:right;" title="Ingrese cantidad" name="cantidad_agregar" id="cantidad_agregar" placeholder="Cantidad">
											</div>
										</td>
										<td class="col-xs-2">
											<select class="form-control input-sm" style="text-align:left;" title="Seleccione medida" id="medida_producto">
											</select>
										</td>
										<td class="col-sm-1" style="text-align:center;">
											<button type="button" class="btn btn-info btn-sm" title="Agregar productos" onclick="agregar_item();"><span class="glyphicon glyphicon-plus"></span></button>
										</td>
									</table>
								</div>
							</div>
						</div>
					</div>
					<div id="muestra_detalle_agregados_producto"></div><!-- Carga gif animado -->
					<div class="outer_divdet_agregados_producto"></div><!-- Datos ajax Final -->
			</div>
			<div class="modal-footer">
				<span id="loader_agregados"></span>
				<button type="button" class="btn btn-default" data-dismiss="modal" id="cerrar_agregados" title="Cancelar">Cerrar</button>
				<button type="submit" class="btn btn-info" id="btnActionFormAgregados_producto" class="btn btn-primary" title=""><span id="btnTextAgregados_producto"></span></button>
			</div>
			</form>
		</div>
	</div>
</div>


<!-- Modal -->
<div class="modal fade" id="modalViewAgregados_producto" data-backdrop="static" data-keyboard="false" aria-labelledby="exampleModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h5 class="modal-title"><i class="glyphicon glyphicon-list"></i> Detalle de agregados</h5>
			</div>
			<div class="modal-body">
				<div id="detalle_agregados_producto"></div><!-- Carga gif animado -->
				<div class="outer_detalle_agregados_producto"></div><!-- Datos ajax Final -->
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal" title="Cerrar"> Cerrar</button>
			</div>
		</div>
	</div>
</div>