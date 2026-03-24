	<!-- Modal -->
	<div class="modal fade" data-backdrop="static" id="modalProveedor" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					<h5 class="modal-title" id="titleModalProveedor"></h5>
				</div>
				<div class="modal-body">
					<form id="formProveedor">
						<input type="hidden" id="idProveedor" name="idProveedor" value="">
						<div class="form-group row">
							<div class="col-sm-5">
								<div class="input-group">
									<span class="input-group-addon"><b>Tipo id</b></span>
									<?php
									$con = conenta_login(); ?>
									<select class="form-control" id="tipo_id" name="tipo_id" required>
										<?php
										$respuesta = mysqli_query($con, "SELECT * FROM iden_comprador");
										while ($datos_comprador = mysqli_fetch_assoc($respuesta)) {
										?>
											<option value="<?php echo $datos_comprador['codigo'] ?>"><?php echo $datos_comprador['nombre'] ?></option>
										<?php
										}
										?>
									</select>
								</div>
							</div>
							<div class="col-sm-7">
								<div class="input-group">
									<span class="input-group-addon"><b>Documento *</b></span>
									<input type="text" class="form-control" onkeyup="info_contribuyente();" id="ruc_proveedor" name="ruc_proveedor" placeholder="Ruc/cédula/otro" required>
								</div>
							</div>
						</div>
						<div class="form-group row">
							<div class="col-sm-12">
								<div class="input-group">
									<span class="input-group-addon"><b>Razón social *</b></span>
									<input type="text" class="form-control" id="razon_social" name="razon_social" placeholder="Razón social" required>
								</div>
							</div>
						</div>
						<div class="form-group row">
							<div class="col-sm-12">
								<div class="input-group">
									<span class="input-group-addon"><b>Nombre comercial</b></span>
									<input type="text" class="form-control" id="nombre_comercial" name="nombre_comercial" placeholder="Nombre comercial">
								</div>
							</div>
						</div>
						<div class="form-group row">
							<div class="col-sm-12">
								<div class="input-group">
									<span class="input-group-addon"><b>Tipo contribuyente</b></span>
									<select class="form-control" name="tipo_empresa" id="tipo_empresa">
										<?php
										$res = mysqli_query($con, "SELECT * FROM tipo_empresa");
										?> <option value="">Seleccione tipo contribuyente</option>
										<?php
										while ($o = mysqli_fetch_assoc($res)) {
										?>
											<option value="<?php echo $o['codigo'] ?>"><?php echo $o['nombre'] ?> </option>
										<?php
										}
										?>
									</select>
								</div>
							</div>
						</div>
						<div class="form-group row">
							<div class="col-sm-12">
								<div class="input-group">
									<span class="input-group-addon"><b>Dirección</b></span>
									<input class="form-control" id="direccion_proveedor" name="direccion_proveedor" placeholder="Dirección" maxlength="255" required>
								</div>
							</div>
						</div>
						<div class="form-group row">
							<div class="col-sm-12">
								<div class="input-group">
									<span class="input-group-addon"><b>Email</b></span>
									<input type="text" class="form-control" id="mail_proveedor" name="mail_proveedor" placeholder="email">
									<span class="input-group-addon"><a href="#" data-toggle="tooltip" data-placement="top" title="Puede agregar varios correos separados por coma y espacio"><span class="glyphicon glyphicon-question-sign"></span></a></span>
								</div>
							</div>
						</div>
						<div class="form-group row">
							<div class="col-sm-6">
								<div class="input-group">
									<span class="input-group-addon"><b>Teléfono</b></span>
									<input type="text" class="form-control" id="telefono_proveedor" name="telefono_proveedor" placeholder="Teléfono">
								</div>
							</div>

							<div class="col-sm-6">
								<div class="input-group">
									<span class="input-group-addon"><b>Días de plazo para pago</b></span>
									<input class="form-control text-right" id="plazo" name="plazo" value="1">
								</div>
							</div>
						</div>
						<div class="form-group row">
							<div class="col-sm-12">
								<div class="input-group">
									<span class="input-group-addon"><b>Banco</b></span>
									<select class="form-control form-control-sm" id="listBanco" name="listBanco">
										<option value="0" selected>Ninguno</option>
										<?php
										$sql = mysqli_query($con, "SELECT id_bancos, nombre_banco FROM bancos_ecuador order by nombre_banco asc");
										while ($row_banco = mysqli_fetch_assoc($sql)) {
										?>
											<option value="<?php echo $row_banco['id_bancos']; ?>"><?php echo $row_banco['nombre_banco']; ?></option>
										<?php
										}
										?>
									</select>
								</div>
							</div>
						</div>
						<div class="form-group row">
							<div class="col-sm-6">
								<div class="input-group">
									<span class="input-group-addon"><b>Tipo cta</b></span>
									<select class="form-control form-control-sm" id="listTipoCta" name="listTipoCta">
										<option value="0" selected>Ninguna</option>
										<option value="1">Ahorro</option>
										<option value="2">Corriente</option>
										<option value="3">Virtual</option>
									</select>
								</div>
							</div>
							<div class="col-sm-6">
								<div class="input-group">
									<span class="input-group-addon"><b>Número cta</b></span>
									<input type="text" class="form-control focusNext" id="txtNumeroCuenta" tabindex="8" name="txtNumeroCuenta" aria-label="Sizing example input" aria-describedby="inputGroup-sizing-sm">
								</div>
							</div>
						</div>
				</div>
				<div class="modal-footer">
					<span id="resultados_modal_proveedor"></span>
					<button type="button" class="btn btn-default" data-dismiss="modal" title="Cerrar modal proveedor">Cerrar</button>
					<button type="button" onclick="guarda_proveedor();" id="btnActionFormProveedor" class="btn btn-primary"><span id="btnTextProveedor"></span></button>
				</div>
			</div>
			</form>
		</div>
	</div>
	<link rel="stylesheet" href="../css/jquery-ui.css">
	<script src="../js/jquery-ui.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
	<script src="../js/notify.js"></script>
	<script>
		function info_contribuyente() {
			var ruc = document.getElementById('ruc_proveedor').value;
			var tipo_id = document.getElementById('tipo_id').value;
			if ((tipo_id == '05' && ruc.length == 10) || (tipo_id == '04' && ruc.length == 13)) {
				$.ajax({
					type: "POST",
					url: "../clases/info_ruc_sri.php?action=info_ruc",
					data: "numero=" + ruc,
					beforeSend: function(objeto) {
						$("#resultados_modal_proveedor").html('Cargando información...');
					},
					success: function(datos) {
						$.each(datos, function(i, item) {
							$("#razon_social").val(item.nombre);
							$("#direccion_proveedor").val(item.direccion);
							$("#nombre_comercial").val(item.nombre_comercial);
							$("#tipo_empresa").val(item.tipo);
						});
						$("#resultados_modal_proveedor").html('');
					}
				});
			}
		}


		function guarda_proveedor() {
			$('#btnTextProveedor').attr("disabled", true);
			var idProveedor = $("#idProveedor").val();
			var tipo_id = $("#tipo_id").val();
			var ruc_proveedor = $("#ruc_proveedor").val();
			var razon_social = $("#razon_social").val();
			var nombre_comercial = $("#nombre_comercial").val();
			var tipo_empresa = $("#tipo_empresa").val();
			var direccion_proveedor = $("#direccion_proveedor").val();
			var mail_proveedor = $("#mail_proveedor").val();
			var telefono_proveedor = $("#telefono_proveedor").val();
			var plazo = $("#plazo").val();
			var listBanco = $("#listBanco").val();
			var listTipoCta = $("#listTipoCta").val();
			var txtNumeroCuenta = $("#txtNumeroCuenta").val();

			$.ajax({
				type: "POST",
				url: "../ajax/proveedores.php?action=guardar_proveedor",
				data: "id_proveedor=" + idProveedor + "&tipo_id=" + tipo_id +
					"&ruc_proveedor=" + ruc_proveedor + "&razon_social=" + razon_social + "&nombre_comercial=" + nombre_comercial +
					"&tipo_empresa=" + tipo_empresa + "&direccion_proveedor=" + direccion_proveedor + "&mail_proveedor=" + mail_proveedor +
					"&telefono_proveedor=" + telefono_proveedor + "&plazo=" + plazo + "&id_banco=" + listBanco + "&tipo_cta=" + listTipoCta + "&numero_cta=" + txtNumeroCuenta,
				beforeSend: function(objeto) {
					$("#resultados_modal_proveedor").html("Guardando...");
				},
				success: function(datos) {
					$("#resultados_modal_proveedor").html(datos);
					$('#btnTextProveedor').attr("disabled", false);
				}
			});
			event.preventDefault();
		}
	</script>