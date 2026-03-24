	<!-- Modal -->
	<div class="modal fade" id="nuevoVendedor" data-backdrop="static" aria-labelledby="exampleModalLabel" aria-hidden="true">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					<h5 class="modal-title" id="titleModalVendedor"></h5>
				</div>
				<div class="modal-body">
					<form id="formVendedores">
						<input type="hidden" id="idVendedor" value="">
						<div class="form-group row">
							<div class="col-sm-6">
								<div class="input-group">
									<span class="input-group-addon"><b>Tipo id</b></span>
									<select class="form-control" id="tipo_id" required>
										<option value="05" selected>Cédula</option>
										<option value="06">Pasasporte</option>
									</select>
								</div>
							</div>
							<div class="col-sm-6">
								<div class="input-group">
									<span class="input-group-addon"><b>Cedula/pas</b></span>
									<input type="text" class="form-control" onkeyup="info_contribuyente();" id="cedula" required>
								</div>
							</div>
						</div>
						<div class="form-group row">
							<div class="col-sm-12">
								<div class="input-group">
									<span class="input-group-addon"><b>Nombre</b></span>
									<input type="text" class="form-control" id="nombre" required>
								</div>
							</div>
						</div>
						<div class="form-group row">
							<div class="col-sm-8">
								<div class="input-group">
									<span class="input-group-addon"><b>Email</b></span>
									<input type="text" class="form-control" id="correo">
									<span class="input-group-addon"><a href="#" data-toggle="tooltip" data-placement="top" title="Puede agregar varios correos separados por coma y espacio"><span class="glyphicon glyphicon-question-sign"></span></a></span>
								</div>
							</div>
							<div class="col-sm-4">
								<div class="input-group">
									<span class="input-group-addon"><b>Teléfono</b></span>
									<input type="text" class="form-control" id="telefono">
								</div>
							</div>
						</div>
						<div class="form-group row">
							<div class="col-sm-8">
								<div class="input-group">
									<span class="input-group-addon"><b>Dirección</b></span>
									<input class="form-control" id="direccion" maxlength="255" required>
								</div>
							</div>
							<div class="col-sm-4">
								<div class="input-group">
									<span class="input-group-addon"><b>Status</b></span>
									<select class="form-control form-control-sm" id="status">
										<option value="1" selected>Activo</option>
										<option value="2">Inactivo</option>
									</select>
								</div>
							</div>
						</div>
				</div>
				<div class="modal-footer">
					<span id="resultados_modal_vendedor"></span>
					<button type="button" class="btn btn-default" data-dismiss="modal" title="Cerrar">Cerrar</button>
					<button type="button" onclick="guarda_vendedor();" id="btnActionFormVendedor" class="btn btn-primary" title="Guardar"><span id="btnTextVendedor"></span></button>
				</div>
				</form>
			</div>
		</div>
	</div>


	<!--modal usuarios asigandas -->
	<div data-backdrop="static" id="usuarios_asignados" class="modal fade" role="dialog">
		<div class="modal-dialog modal-md">
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal">×</button>
					<h5 class="modal-title"><i class='glyphicon glyphicon-ok'></i> Vendedor asignados a usuarios</h5>
				</div>
				<div class="modal-body">
					<form id="formVendedorAsignado">
						<input type="hidden" id="idVendedorAsignado" name="idVendedorAsignado" value="">
						<div class="form-group row">
							<div class="col-sm-12">
								<div class="input-group">
									<span class="input-group-addon"><b>Vendedor</b></span>
									<input type="text" name="nombre_vendedor_asignado" class="form-control" id="nombre_vendedor_asignado" value="" readonly>
								</div>
							</div>
						</div>
						<div id="resultados_asignaciones"></div><!-- Carga los datos ajax -->
						<div class="outer_div_asiganciones"></div><!-- Carga los datos ajax -->
				</div>
				<div class="modal-footer">
					<span id="resultados_modal_vendedor_asignado"></span>
					<button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
				</div>
				</form>
			</div>
		</div>
	</div>
	<script>
		function guarda_vendedor() {
			$('#btnTextVendedor').attr("disabled", true);
			var id_vendedor = $("#idVendedor").val();
			var tipo_id = $("#tipo_id").val();
			var cedula = $("#cedula").val();
			var nombre = $("#nombre").val();
			var correo = $("#correo").val();
			var direccion = $("#direccion").val();
			var telefono = $("#telefono").val();
			var usuario = $("#usuario").val();
			var status = $("#status").val();

			$.ajax({
				type: "POST",
				url: "../ajax/vendedores.php?action=guardar_vendedor",
				data: "id_vendedor=" + id_vendedor + "&tipo_id=" + tipo_id +
					"&cedula=" + cedula + "&nombre=" + nombre + "&correo=" + correo + "&direccion=" + direccion + "&telefono=" + telefono +
					"&usuario=" + usuario + "&status=" + status,
				beforeSend: function(objeto) {
					$("#resultados_modal_vendedor").html("Guardando...");
				},
				success: function(datos) {
					$("#resultados_modal_vendedor").html(datos);
					$('#btnTextVendedor').attr("disabled", false);
				}
			});
			event.preventDefault();
		}

		function info_contribuyente() {
			var cedula = document.getElementById('cedula').value;
			var tipo_id = document.getElementById('tipo_id').value;
			if ((tipo_id == '05' && ruc.length == 10) || (tipo_id == '04' && ruc.length == 13)) {
				$.ajax({
					type: "POST",
					url: "../clases/info_ruc_sri.php?action=info_ruc",
					data: "numero=" + cedula,
					beforeSend: function(objeto) {
						$("#resultados_modal_vendedor").html('Cargando...');
					},
					success: function(datos) {
						$.each(datos, function(i, item) {
							$("#nombre").val(item.nombre);
							$("#direccion").val(item.direccion);
						});
						$("#resultados_modal_vendedor").html('');
					}
				});
			}
		}
	</script>