<div class="modal fade" data-backdrop="static" id="EnviarDocumentosWhatsapp" name="EnviarDocumentosWhatsapp" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="myModalLabel"><img src="../image/whatsapp.png" alt="Logo" width="15px"> Enviar whatsapp</h4>
			</div>
			<div class="modal-body">
				<form class="form-horizontal" id="documento_whatsapp" name="documento_whatsapp">
					<div class="form-group">
						<label for="" class="col-sm-4 control-label"> Número de Whatsapp</label>
						<input type="hidden" id="id_documento_whatsapp" name="id_documento_whatsapp">
						<input type="hidden" id="tipo_documento_whatsapp" name="tipo_documento_whatsapp">
						<div class="col-sm-6">
							<input type="text" class="form-control" id="whatsapp_receptor" name="whatsapp_receptor" placeholder="09..." required>
						</div>
					</div>
					<div class="form-group">
						<label for="" class="col-sm-4 control-label"> Mensaje</label>
						<div class="col-sm-6">
							<textarea type="textarea" rows="5" class="form-control" id="mensaje" name="mensaje" placeholder="Mensaje"></textarea>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-12">
							<div id="resultados_ajax_whatsapp"></div>
						</div>
					</div>
			</div>
			<div class="modal-footer">
				<div id="mensaje_whatsapp"></div>
				<button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
				<button type="submit" class="btn btn-primary" id="enviar_whatsapp">Enviar</button>
			</div>
			</form>
		</div>
	</div>
</div>

<div class="modal fade" data-backdrop="static" id="EnviarDocumentosMail" name="EnviarDocumentosMail" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-envelope'></i> Enviar documento por mail</h4>
			</div>
			<div class="modal-body">
				<form class="form-horizontal" id="documento_mail" name="documento_mail">
					<div id="resultados_ajax_mail"></div>
					<div class="form-group">
						<label for="" class="col-sm-1 control-label"> Mail</label>
						<div class="col-sm-10">
							<div id="mensaje_mail"></div>
							<input type="hidden" id="id_documento" name="id_documento">
							<input type="hidden" id="tipo_documento" name="tipo_documento">
							<input type="text" class="form-control" id="mail_receptor" name="mail_receptor" placeholder="e-mail" required>
						</div>
						<a data-toggle="tooltip" data-placement="top" title="Puede agregar varios correos separados por coma y espacio"><span class="glyphicon glyphicon-question-sign"></span></a>
					</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" id="cerrar_mail" data-dismiss="modal">Cerrar</button>
				<button type="submit" class="btn btn-primary" id="enviar_mail">Enviar</button>
			</div>
			</form>
		</div>
	</div>
</div>


<div class="modal fade" data-backdrop="static" id="EnviarProforma" name="EnviarProforma" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-envelope'></i> Enviar proforma</h4>
			</div>
			<div class="modal-body">
				<form class="form-horizontal" id="documento_proforma" name="documento_proforma">
					<div id="resultados_ajax_proforma"></div>
					<div class="form-group">
						<label for="" class="col-sm-1 control-label"> Mail</label>
						<div class="col-sm-10">
							<div id="mensaje_mail"></div>
							<input type="hidden" id="id_documento_proforma" name="id_documento_proforma">
							<input type="hidden" id="tipo_documento_proforma" name="tipo_documento_proforma">
							<input type="text" class="form-control" id="mail_receptor_proforma" name="mail_receptor_proforma" placeholder="e-mail" required>
						</div>
						<a data-toggle="tooltip" data-placement="top" title="Puede agregar varios correos separados por coma y espacio"><span class="glyphicon glyphicon-question-sign"></span></a>
					</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" id="cerrar_proforma" data-dismiss="modal">Cerrar</button>
				<button type="submit" class="btn btn-primary" id="enviar_proforma">Enviar</button>
			</div>
			</form>
		</div>
	</div>
</div>
<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
<link rel="stylesheet" href="../css/jquery-ui.css">
<script src="../js/jquery-ui.js"></script>
<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
<script>
	jQuery(function($) {
		$("#whatsapp_receptor").mask("9999999999");
	});

	$("#cerrar_proforma").click(function() {
		$("#resultados_ajax_proforma").empty();
	});

	$("#cerrar_mail").click(function() {
		$("#resultados_ajax_mail").empty();
	});

	//para enviar por mail el documento
	$("#documento_mail").submit(function(event) {
		$('#enviar_mail').attr("disabled", true);
		$('#mensaje_mail').attr("hidden", true); // para mostrar el mensaje de dar clik para enviar y mas abajo lo desaparece
		var parametros = $(this).serialize();
		//var pagina = $("#pagina").val();

		$.ajax({
			type: "GET",
			url: "../documentos_mail/envia_mail.php",
			data: parametros,
			beforeSend: function(objeto) {
				$("#resultados_ajax_mail").html(
					'<div class="progress"><div class="progress-bar progress-bar-primary progress-bar-striped active" role="progressbar" style="width:100%;">Enviando documento por mail espere por favor...</div></div>');
			},
			success: function(datos) {
				$("#resultados_ajax_mail").html(datos);
				$('#enviar_mail').attr("disabled", false);
				$('#mensaje_mail').attr("hidden", false); // lo vuelve a mostrar el mensaje cuando ya hace todo el proceso
				//load(pagina);
			}
		});
		event.preventDefault();
	});

	$("#documento_whatsapp").submit(function(event) {
		$('#enviar_whatsapp').attr("disabled", true);
		$('#mensaje_whatsapp').attr("hidden", true); // para mostrar el mensaje de dar clik para enviar y mas abajo lo desaparece
		var parametros = $(this).serialize();
		//que se envie cada 5 segundos
		//setTimeout(function() {
		$.ajax({
			type: "GET",
			url: "../clases/whatsapp.php?action=enviar_whatsapp",
			data: parametros,
			beforeSend: function(objeto) {
				$("#resultados_ajax_whatsapp").html(
					'<div class="progress"><div class="progress-bar progress-bar-primary progress-bar-striped active" role="progressbar" style="width:100%;">Enviando whatsapp espere por favor...</div></div>');
			},
			success: function(datos) {
				$("#resultados_ajax_whatsapp").html(datos);
				$('#enviar_whatsapp').attr("disabled", false);
				$('#mensaje_whatsapp').attr("hidden", false); // lo vuelve a mostrar el mensaje cuando ya hace todo el proceso
			}
		});
		event.preventDefault();
		//}, 5000);
	});
</script>