<div class="modal fade" data-backdrop="static" id="detalleDocumento" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" style="overflow-y: scroll;">
<div class="modal-dialog modal-lg" role="document">
<div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          <h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-list-alt'></i> Detalle del documento <span id="loaderdet" ></span></h4>
        </div>
        <div class="modal-body">
			<div class="outer_divdet" ></div><!-- Datos ajax Final -->
		</div>
		<div class="modal-footer">
		   <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
		</div>
</div>
</div>
</div>
<script>
 		function buscar_retenciones_compras() {
			$("#concepto_retencion_compra").autocomplete({
				source: '../ajax/concepto_retencion_autocompletar.php',
				minLength: 2,
				select: function(event, ui) {
					event.preventDefault();
					$('#id_concepto_ret_compra').val(ui.item.id_ret);
					$('#concepto_retencion_compra').val(ui.item.concepto_ret);
					$('#porcentaje_retencion_compra').val(ui.item.porcentaje_ret);
					document.getElementById('base_retencion_compra').focus();
				}
			});

			$("#concepto_retencion_compra").on("keydown", function(event) {
				if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_concepto_ret_compra").val("");
					$("#concepto_retencion_compra").val("");
					$("#porcentaje_retencion_compra").val("");
				}
				if (event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_concepto_ret_compra").val("");
					$("#concepto_retencion_compra").val("");
					$("#porcentaje_retencion_compra").val("");
				}
			});
		}
</script>