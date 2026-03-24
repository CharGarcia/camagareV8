<div class="modal fade" data-backdrop="static" id="detalle_ingreso" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-list-alt'></i> Detalle del ingreso</h4>
			</div>
			<div class="modal-body">
				<form class="form-horizontal" method="POST" id="actualizar_ingreso" name="actualizar_ingreso">
					<div class="outer_divdet_ingreso"></div><!-- Datos ajax Final -->
			</div>
			<div class="modal-footer">
				<span id="loaderdet_ingreso"></span><!-- Carga gif animado -->
				<button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
				<button type="submit" class="btn btn-primary" title="Actualizar ingreso">Actualizar</button>
			</div>
			</form>
		</div>
	</div>
</div>

<div class="modal fade" data-backdrop="static" id="detalle_egreso" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-list-alt'></i> Detalle del egreso</h4>
			</div>
			<div class="modal-body">
				<form class="form-horizontal" method="POST" id="actualizar_egreso" name="actualizar_egreso">
					<div class="outer_divdet_egreso"></div><!-- Datos ajax Final -->
			</div>
			<div class="modal-footer">
				<span id="loaderdet_egreso"></span><!-- Carga gif animado -->
				<button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
				<button type='submit' class="btn btn-primary" title="Actualizar egreso" id="actualiza_egreso">Actualizar</button>
			</div>
			</form>
		</div>
	</div>
</div>