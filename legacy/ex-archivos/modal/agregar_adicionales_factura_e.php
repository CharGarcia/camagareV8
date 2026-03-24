<div class="modal fade" id="agregaAdicionales" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
<div class="modal-dialog modal-md" role="document">
<div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          <h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-edit'></i> Agregar información adicional</h4>
		</div>
        <div class="modal-body">
			<div class="form-group">
				<table class="table table-bordered">
							<tr  class="info">
									<th>Concepto</th>
									<th>Detalle</th>
									<th class="text-center">Agregar</th>
							</tr>
								<td class='col-xs-3'>
								  <input type="text" class="form-control input-sm" id="adicional_concepto" name="adicional_concepto" placeholder="Concepto">
								  </td>
								<td class="col-xs-6">
								<input type="text" class="form-control input-sm" id="adicional_descripcion" name="adicional_descripcion" placeholder="Descripción del detalle">
								</td>
								<td class="text-center"><a class='btn btn-info' onclick="agregar_info_adicional()"><i class="glyphicon glyphicon-plus"></i></a></td>
				 </table>
			 </div>
			<div id="resultados_agregar_adicionales"></div>
			<div class="outer_divadi" ></div><!-- Datos ajax Final -->
		</div>
				<div class="modal-footer">
				   <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
				</div>
	</div>
	</div>
 </div>
