	<!-- Modal -->
	<div class="modal fade" data-backdrop="static" id="nuevoProveedorRetencion" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					<h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-edit'></i> Agregar nuevo proveedor</h4>
				</div>
				<div class="modal-body">
					<form class="form-horizontal" method="post" id="formProveedor">
						<div class="form-group row">
							<div class="col-sm-6">
								<div class="input-group">
									<span class="input-group-addon"><b>Tipo id</b></span>
									<?php $conexion = conenta_login(); ?>
									<select class="form-control" id="tipo_id" name="tipo_id" required>
										<?php
										$sql = "SELECT * FROM iden_comprador;";
										$respuesta = mysqli_query($conexion, $sql);
										while ($datos_comprador = mysqli_fetch_assoc($respuesta)) {
										?>
											<option value="<?php echo $datos_comprador['codigo'] ?>"><?php echo $datos_comprador['nombre'] ?></option>
										<?php
										}
										?>
									</select>
								</div>
							</div>
							<div class="col-sm-6">
								<div class="input-group">
									<span class="input-group-addon"><b>Ruc/cedula *</b></span>
									<input type="text" class="form-control" onkeyup="info_contribuyente();" id="ruc_proveedor" name="ruc_proveedor" required>
								</div>
							</div>
						</div>
						<div class="form-group row">
							<div class="col-sm-12">
								<div class="input-group">
									<span class="input-group-addon"><b>Razón social</b></span>
									<input type="text" class="form-control" id="nombre_proveedor" name="nombre_proveedor" required>
								</div>
							</div>
						</div>
						<div class="form-group row">
							<div class="col-sm-12">
								<div class="input-group">
									<span class="input-group-addon"><b>Tipo contribuyente</b></span>
									<select class="form-control" name="tipo_empresa" id="tipo_empresa">
										<?php
										$sql = "SELECT * FROM tipo_empresa ;";
										$res = mysqli_query($conexion, $sql);
										?> <option value="">Seleccione tipo empresa</option>
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
									<span class="input-group-addon"><b>Email</b></span>
									<input type="text" class="form-control" id="email_proveedor" name="email_proveedor" value="info@camagare.com">
									<span class="input-group-addon"><b><a href="#" data-toggle="tooltip" data-placement="top" title="Puede agregar varios correos separados por coma y espacio"><span class="glyphicon glyphicon-question-sign"></span></a></b></span>
								</div>
							</div>
						</div>
				</div>
				<div class="modal-footer">
					<span id="resultados_modal_proveedor"></span>
					<button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
					<button type="button" class="btn btn-primary" onclick="guarda_proveedor();"><span id="btnTextProveedor"></span> Guardar</button>
				</div>
				</form>
			</div>
		</div>
	</div>
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
						$("#resultados_modal_proveedor").html('Cargando...');
					},
					success: function(datos) {
						$.each(datos, function(i, item) {
							$("#nombre_proveedor").val(item.nombre);
							$("#tipo_empresa").val(item.tipo);
						});
						$("#resultados_modal_proveedor").html('');
					}
				});
			}
		}

		function guarda_proveedor() {
			$('#btnTextProveedor').attr("disabled", true);
			var idProveedor = "";
			var tipo_id = $("#tipo_id").val();
			var ruc_proveedor = $("#ruc_proveedor").val();
			var razon_social = $("#nombre_proveedor").val();
			var nombre_comercial = razon_social;
			var tipo_empresa = $("#tipo_empresa").val();
			var direccion_proveedor = "";
			var mail_proveedor = $("#email_proveedor").val();
			var telefono_proveedor = "";
			var plazo = "1";
			var listBanco = "";
			var listTipoCta = "";
			var txtNumeroCuenta = "";

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