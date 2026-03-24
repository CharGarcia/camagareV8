<div class="modal fade" id="CuentaBancaria" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
	<div class="modal-dialog modal-md" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h5 class="modal-title" id="titleModalCuentaBancaria"></h5>
			</div>
			<div class="modal-body">
				<form class="form-horizontal" method="post" id="formCuentaBancaria">
					<input type="hidden" id="idCuentaBancaria" value="">
					<div class="form-group row">
						<div class="col-sm-8">
							<div class="input-group">
								<span class="input-group-addon"><b>Banco</b></span>
								<select class="form-control" name="banco" id="banco">
									<?php
									$con = conenta_login();
									$res = mysqli_query($con, "SELECT * FROM bancos_ecuador order by nombre_banco asc");
									?> <option value="">Seleccione</option>
									<?php
									while ($o = mysqli_fetch_assoc($res)) {
									?>
										<option value="<?php echo $o['id_bancos'] ?>"><?php echo $o['nombre_banco'] ?> </option>
									<?php
									}
									?>
								</select>
							</div>
						</div>
						<div class="col-sm-4">
							<div class="input-group">
								<span class="input-group-addon"><b>Tipo</b></span>
								<select class="form-control" name="tipo_cuenta" id="tipo_cuenta">
									<option value="" selected> Seleccione</option>
									<option value="1">Ahorros</option>
									<option value="2">Corriente</option>
									<option value="3">Virtual</option>
									<option value="4">Tarjeta</option>
								</select>
							</div>
						</div>
					</div>
					<div class="form-group row">
						<div class="col-sm-7">
							<div class="input-group">
								<span class="input-group-addon"><b>Número cuenta</b></span>
								<input type="text" class="form-control" name="numero_cuenta" id="numero_cuenta">
							</div>
						</div>
						<div class="col-sm-5">
							<div class="input-group">
								<span class="input-group-addon"><b>Status *</b></span>
								<select class="form-control form-control-sm" id="listStatusCuentaBancaria">
									<option value="1" selected>Activo</option>
									<option value="2">Inactivo</option>
								</select>
							</div>
						</div>
					</div>
					<div class="modal-footer">
						<div id="resultados_cuenta_bancaria"></div>
						<button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
						<button type="button" onclick="guarda_cuenta_bancaria();" id="btnActionFormCuentaBancaria" class="btn btn-primary" title="Guardar cuenta bancaria"><span id="btnTextCuentaBancaria"></span></button>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
<script>
	function guarda_cuenta_bancaria() {
		$('#btnTextCuentaBancaria').attr("disabled", true);
		var id_cuenta = $("#idCuentaBancaria").val();
		var banco = $("#banco").val();
		var tipo = $("#tipo_cuenta").val();
		var cuenta = $("#numero_cuenta").val();
		var status = $("#listStatusCuentaBancaria").val();

		$.ajax({
			type: "POST",
			url: "../ajax/cuentas_bancarias.php?action=guarda_cuenta_bancaria",
			data: "id_cuenta=" + id_cuenta + "&banco=" + banco +
				"&tipo=" + tipo + "&cuenta=" + cuenta + "&status=" + status,
			beforeSend: function(objeto) {
				$("#resultados_cuenta_bancaria").html("Guardando...");
			},
			success: function(datos) {
				$("#resultados_cuenta_bancaria").html(datos);
				$('#btnTextCuentaBancaria').attr("disabled", false);
			}
		});
		event.preventDefault();
	}
</script>