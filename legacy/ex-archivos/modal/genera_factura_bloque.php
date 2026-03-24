
<div class="modal fade" id="generaFacturaBloque" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
<div class="modal-dialog" role="document">
<div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          <h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-film'></i> Generar facturas en bloque</h4>
        </div>
        <div class="modal-body">
	<form class="form-horizontal" method="post" id="guardar_sucursal" name="guardar_sucursal">
	<div id="resultados_ajax"></div>
				<div class="form-group">
					<label for="" class="col-sm-4 control-label"> Período a facturar</label>
					  <div class="col-sm-4">
						  <select class="form-control" id="periodo" name="periodo" required>
										<option value="" Selected>Seleccione período</option>
										<option value="01">Semanal</option>
										<option value="02">Mensual</option>
										<option value="03">Trimestral</option>
										<option value="04">Semestral</option>
										<option value="05">Anual</option>
						  </select>
					  </div>
				 </div>
				 <div class="form-group">
				<label for="" class="col-sm-4 control-label"> Detalle adicional</label>
					  <div class="col-md-7">
							<input type="text" class="form-control input-sm" id="detalle" name="detalle">
					</div>
				</div>
				</div>
				<div class="modal-footer">
				   <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
				   <button type="submit" class="btn btn-primary" id="guardar_datos_sucursal" >Generar</button>
				</div>
            </form>
	</div>
	</div>
 </div>
