	<!-- Modal -->
	<div class="modal fade" id="nuevoAviso" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					<h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-edit'></i> Agregar nuevo aviso</h4>
				</div>
				<div class="modal-body">
					<form class="form-horizontal" method="post" id="guardar_aviso" name="guardar_aviso">
						<div id="resultados_ajax_avisos"></div>
						<div class="form-group">
							<label class="col-sm-3 control-label">Empresa</label>
							<div class="col-sm-8">
								<input type="hidden" id="ruc_empresa" name="ruc_empresa">
								<input type="text" class="form-control input-sm" id="nombre_empresa" name="nombre_empresa" placeholder="Nombre, ruc, nombre comercial" title="Buscar empresa" onkeyup='buscar_empresa();' autocomplete="off">
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-3 control-label">Detalle</label>
							<div class="col-sm-8">
								<textarea class="form-control" rows="5" id="detalle_aviso" name="detalle_aviso"></textarea>
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