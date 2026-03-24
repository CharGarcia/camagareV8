<div class="modal fade" data-backdrop="static" id="AnularDocumentosSri" name="AnularDocumentosSri" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
	<div class="modal-dialog modal-md" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-trash'></i> Anulación de comprobantes SRI</h4>
			</div>
			<div class="modal-body">
				<form class="form-horizontal" id="anular_documento_sri" name="anular_documento_sri" method="POST">
					<input type="hidden" id="id_documento_modificar" name="id_documento_modificar">
					<input type="hidden" id="codigo_documento_modificar" name="codigo_documento_modificar">
					<div class="form-group">
						<div class="col-sm-12 text-center">
							<h5 style="color: red;"><b>¡Atención! La anulación se debe hacer primero en el SRI. </b></h5>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-12">
							<div class="input-group">
								<span class="input-group-addon">Cliente / proveedor</span>
								<input class="form-control input-sm" id="cliente_proveedor" readonly>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-12">
							<div class="input-group">
								<span class="input-group-addon">*Tipo de comprobante</span>
								<input class="form-control input-sm" id="tipo_comprobante" readonly>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-6">
							<div class="input-group">
								<span class="input-group-addon">*Fecha de autorización</span>
								<input class="form-control input-sm" id="fecha_autorizacion" name="fecha_autorizacion">
							</div>
						</div>
						<div class="col-sm-6">
							<div class="input-group">
								<span class="input-group-addon">Fecha de emisión</span>
								<input class="form-control input-sm" id="fecha_documento" readonly>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-12">
							<div class="input-group">
								<span class="input-group-addon">*Clave acceso</span>
								<input class="form-control input-sm" id="clave_acceso" name="clave_acceso" readonly>
								<span class="input-group-btn btn-md"><button class="btn btn-info btn-sm" type="button" title="Copiar" onclick="copyToClipboard('clave_acceso')"><span class="glyphicon glyphicon-copy"></span></button></span>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-12">
							<div class="input-group">
								<span class="input-group-addon">*Autorización</span>
								<input class="form-control input-sm" id="numero_autorizacion" name="numero_autorizacion" readonly>
								<span class="input-group-btn btn-md"><button class="btn btn-info btn-sm" type="button" title="Copiar" onclick="copyToClipboard('numero_autorizacion')"><span class="glyphicon glyphicon-copy"></span></button></span>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-12">
							<div class="input-group">
								<span class="input-group-addon">*Identificación receptor</span>
								<input class="form-control input-sm" id="ruc_receptor" name="ruc_receptor" readonly>
								<span class="input-group-btn btn-md"><button class="btn btn-info btn-sm" type="button" title="Copiar" onclick="copyToClipboard('ruc_receptor')"><span class="glyphicon glyphicon-copy"></span></button></span>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-12">
							<div class="input-group">
								<span class="input-group-addon">*Correo electrónico receptor</span>
								<input class="form-control input-sm" id="correo_receptor" name="correo_receptor">
								<span class="input-group-btn btn-md"><button class="btn btn-info btn-sm" type="button" title="Copiar" onclick="copyToClipboard('correo_receptor')"><span class="glyphicon glyphicon-copy"></span></button></span>
							</div>
						</div>
					</div>
		<!-- 			<div class="form-group">
						<div class="col-sm-12">
							<div class="input-group">
								<span class="input-group-addon">*Clave SRI</span>
								<input type="password" class="form-control input-sm" id="clave_sri" name="clave_sri" placeholder="Clave SRI">
							</div>
						</div>
					</div> -->
					<div class="form-group">
						<div class="col-sm-12">
							<div class="input-group">
								<span class="input-group-addon"><b>Seleccionar una opción</b></span>
								<select class="form-control input-sm" id="opcion_anular" name="opcion_anular" required>
									<option value="1">Documento anulado previamente en el SRI</option>
									<!-- <option value="2" selected>Documento pendiente de anular</option> -->
								</select>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-12">
							<div id="resultados_ajax_anular"></div>
						</div>
					</div>
			</div>
			<div class="modal-footer">
				<span id="resultados_anular"></span>
				<button type="button" class="btn btn-default" id="btnCerrar" data-dismiss="modal" reset>Cerrar</button>
				<button type="submit" class="btn btn-primary" id="anular_sri">Anular</button>
			</div>
			</form>
		</div>
	</div>
</div>
<script>
	jQuery(function($) {
		$("#fecha_autorizacion").mask("99-99-9999");
	});

	//onclick="load(1);"
	//para anular documento autorizada por el sri
	$("#anular_documento_sri").submit(function(event) {
		if (confirm("Al anular el documento toda la información se elimina. Desea continuar?")) {
			var documento = $("#codigo_documento_modificar").val();
			$('#anular_sri').attr("disabled", true);
			var parametros = $(this).serialize();
			$.ajax({
				type: "POST",
				url: "../ajax/anular_documentos_sri.php",
				data: parametros + "&action=" + documento,
				beforeSend: function(objeto) {
					$("#resultados_ajax_anular").html(
						'<div class="progress"><div class="progress-bar progress-bar-primary progress-bar-striped active" role="progressbar" style="width:100%;">Enviando solicitud, espere por favor...</div></div>');
				},
				success: function(datos) {
					$("#resultados_ajax_anular").html(datos);
					$('#anular_sri').attr("disabled", false);
					load(1);
				}
			});
			event.preventDefault();
		}
	});

	//PARA COPIAR LO QUE HAY EN LOS INPUT
	function copyToClipboard(tipo) {
		let input = document.getElementById(tipo);
		input.select();
		document.execCommand("copy");
		$.notify('Copiado', "success");
	}
</script>