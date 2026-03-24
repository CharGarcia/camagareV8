<div class="modal fade" id="otrosIngresos" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
<div class="modal-dialog modal-lg" role="document">
			<div class="modal-content">
				<div class="modal-header">
				  <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				  <h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-list-alt'></i> Otros ingresos</h4>
				</div>
			<div class="modal-body">
			<input type="hidden" name="numero_ingreso" id="numero_ingreso">
					<div class="form-group">					 
							<table class="table table-bordered">
								<tr  class="warning">
										<th>Nombre</th>
										<th>Valor</th>
										<th>Detalle</th>
										<th class="text-center">Agregar</th>
								</tr>
								<td class='col-xs-6'><input type="text" class="form-control input-sm" name="nombre_otros" id="nombre_otros" ></td>
								<td class='col-xs-2'><input type="text" class="form-control input-sm" name="valor_otros" id="valor_otros" ></td>
								<td class='col-xs-3'><input type="text" class="form-control input-sm" name="detalle_otros" id="detalle_otros"></td>
								<td class="text-center"><a class='btn btn-info' onclick="agregar_otros_ingresos()"><i class="glyphicon glyphicon-plus"></i></a></td>
							</table>
					</div>
			</div>

				<div class="modal-footer">
				   <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
				</div>
		</div>
</div>
</div>
