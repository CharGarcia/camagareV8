<div class="modal fade" id="detalleNumeroConsignacion" tabindex="-1" role="dialog" data-backdrop="static" aria-labelledby="myModalLabel" style="overflow-y: scroll;">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-list-alt'></i> Detalle de consignación</h4>
			</div>
			<div class="modal-body">
				<form method="POST" id="agregar_items_facturacion_consignacion" name="agregar_items_facturacion_consignacion">
					<div class="outer_div_numero_consignacion"></div><!-- Datos ajax Final -->
			</div>
			<div class="modal-footer">
				<span id="loaderdetnumfac"></span><!-- Carga gif animado -->
				<button type="button" class="btn btn-default" data-dismiss="modal" reset>Cerrar</button>
				<button type="button" class="btn btn-info" onclick="agregar_items_factura()" id="btn_agregar_items_factura">Agregar a factura</button>
			</div>
			</form>
		</div>
	</div>
</div>
