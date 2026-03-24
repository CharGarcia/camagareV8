<div class="modal fade" data-backdrop="static" id="EnviarDocumentosSri" name="EnviarDocumentosSri" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-send'></i> Enviar documento al SRI</h4>
			</div>
			<div class="modal-body">
				<form class="form-horizontal" id="documento_sri" name="documento_sri">
					<div class="form-group">
						<div class="col-sm-12">
							<div id="resultados_ajax_sri">
							</div>
						</div>
						<div class="col-sm-12">
							<div id="mensaje_sri"></div>
							<input type="hidden" id="id_documento_sri" name="id_documento_sri">
							<input type="hidden" id="numero_documento_sri" name="numero_documento_sri">
							<input type="hidden" id="tipo_documento_sri" name="tipo_documento_sri">
							<input type="hidden" id="modo_envio" name="modo_envio" value="online">
						</div>
					</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" id="btnCerrar" title="Cerrar la ventana" data-dismiss="modal">Cerrar</button>
				<button type="submit" class="btn btn-primary" id="enviar_sri" title="Enviar el documento para autorizar">Enviar</button>
			</div>
			</form>
		</div>
	</div>
</div>
<script>
	$("#btnCerrar").click(function() {
			$("#resultados_ajax_sri").empty();
			location.reload();
		});

		/*
		$("#documento_sri").submit(function(event) {
			//document.querySelector("#documento_sri").reset();
			$('#enviar_sri').attr("disabled", true);
			var numero_documento = $("#numero_documento_sri").val();
			var parametros = $(this).serialize();
			var pagina = $("#pagina").val();
			$.ajax({
				type: "POST",
				url: '../facturacion_electronica/enviarComprobantesSri.php',
				url: '',
				data: parametros,
				beforeSend: function(objeto) {
					$('#resultados_ajax_sri').html('<div class="progress"><div class="progress-bar progress-bar-success progress-bar-striped active" role="progressbar" style="width:100%;">Enviando documento '+ numero_documento + ' espere por favor...</div></div>');
				},
				success: function(datos) {
					$("#resultados_ajax_sri").html(datos);
					$('#enviar_sri').attr("disabled", false);
					load(pagina);
				}
			});
			event.preventDefault();
		});
		*/
		

	</script>
