<div class="modal fade" id="detallePreciosProductos" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
<div class="modal-dialog modal-lg" role="document">
	<div class="modal-content">
				<div class="modal-header">
				  <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				  <h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-list-alt'></i> Detalle de precios de venta del producto.</h4>
				</div>
				<div class="modal-body">
					<div class="form-group">
					<div class="col-sm-8">
					<div class="input-group">
					<span class="input-group-addon"><b>Producto</b></span>
					<textarea rows="1" cols="50" class="form-control input-sm" name="nombre_producto_actual" id="nombre_producto_actual" readonly ></textarea>
					</div>
					</div>
					<div class="col-sm-4">
					<div class="input-group">
					<span class="input-group-addon"><b>Precio actual</b></span>
					<input type="text" class="form-control input-sm text-right" name="precio_producto_actual" id="precio_producto_actual" readonly>
					</div>
					</div>
					</div>
					<input type="hidden" name="id_producto_precio" id="id_producto_precio">
				</div>
		<div class="modal-body">
		<div id="resultados_detalle_precios"></div>
			<div class="form-group">
				<div class="panel panel-info">
					<table class="table table-bordered">
						<tr class="info">
								<th class="text-center" style ="padding: 2px;">Nuevo Precio</th>
								<th class="text-center" style ="padding: 2px;">Desde</th>
								<th class="text-center" style ="padding: 2px;">Hasta</th>
								<th style ="padding: 2px;">Detalle</th>
								<th class="text-center" style ="padding: 2px;">Agregar</th>
						</tr>
						<td class='col-xs-2' style ="padding: 2px;">
						<input type="text" class="form-control input-sm" name="precio_nuevo" id="precio_nuevo" >
						</td>
						<td class='col-xs-2' style ="padding: 2px;">
						<input type="text" class="form-control input-sm" name="aplica_desde" id="aplica_desde" value="<?php echo date("d-m-Y");?>">
						</td>
						<td class='col-xs-2' style ="padding: 2px;">
						<input type="text" class="form-control input-sm" name="aplica_hasta" id="aplica_hasta" >
						</td>
						<td class='col-xs-4' style ="padding: 2px;">												
						<input type="text" class="form-control input-sm" name="detalle_precio" id="detalle_precio" maxlength="20">
						</td>
						<td class="text-center" style ="padding: 2px;"><a class='btn btn-info input-sm' onclick="agregar_nuevo_precio()"><i class="glyphicon glyphicon-plus"></i></a></td>
					</table>
				</div>
					<div id="muestra_detalle_precios"></div><!-- Carga gif animado -->
					<div class="outer_divdet" ></div><!-- Datos ajax Final -->
			</div>
			</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
				</div>

	</div>
</div>
</div>
