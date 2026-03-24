	<!-- Modal -->
	<div class="modal fade" id="nuevoFrase" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
	  <div class="modal-dialog" role="document">
		<div class="modal-content">
		  <div class="modal-header">
			<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			<h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-edit'></i> Agregar nueva frase</h4>
		  </div>
		  <div class="modal-body">
			<form class="form-horizontal" method="post" id="guardar_frase" name="guardar_frase">
						<div id="resultados_ajax"></div>
						  <div class="form-group">
							<label class="col-sm-3 control-label">Pensamiento</label>
							<div class="col-sm-8">
							 <textarea class="form-control" rows="5" id="nombre_frase" name="nombre_frase"></textarea>
							</div>
						  </div>
						  
					  </div>
					  <div class="modal-footer">
						<button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
						<button type="submit" class="btn btn-primary" id="guardar_datos">Guardar</button>
					  </div>
		  </form>
		</div>
	  </div>
	</div>
	