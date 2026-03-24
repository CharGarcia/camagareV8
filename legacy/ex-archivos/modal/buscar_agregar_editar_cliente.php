			<!-- Modal -->
			<div class="modal fade bs-example-modal-md" data-backdrop="static" id="buscarAgregarEdiatrCliente" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
				<div class="modal-dialog modal-md" role="document">
					<div class="modal-content">
						<div class="modal-header">
							<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
							<h4 class="modal-title" id="myModalLabel"><span class='glyphicon glyphicon-search'></span> Buscar, agregar, editar clientes</h4>
						</div>
						<div class="modal-body">
							<div id="resultados_busqueda_cliente"></div><!-- Carga los datos ajax -->
							<div class="outer_divcli"></div><!-- Datos ajax Final -->

							<form class="form-horizontal" method="post" id="guardar_cliente" name="guardar_cliente">
								<input type="hidden" name="id_registro" id="id_registro">
								<input type="hidden" name="id_cliente" id="id_cliente">

								<div class="form-group">
									<div class="col-sm-12">
										<div class="input-group">
											<span class="input-group-addon"><span class="glyphicon glyphicon-search"></span></span>
											<input type="text" class="form-control" id="buscar_cliente" name="buscar_cliente" title="Nombre, Ruc, cedula o pasaporte del cliente" placeholder="Buscar cliente existente" onkeyup="buscar_clientes();" autocomplete="off">
										</div>
									</div>
								</div>
								<hr>
								<div class="well well-sm">
									<div class="form-group">
										<div class="col-sm-5">
											<div class="input-group">
												<span class="input-group-addon"><span class="glyphicon glyphicon-registration-mark"></span></span>
												<?php $conexion = conenta_login(); ?>
												<select class="form-control" id="tipo_id_cliente" name="tipo_id_cliente" required>
													<?php
													$sql = "SELECT * FROM iden_comprador ;";
													$respuesta = mysqli_query($conexion, $sql);
													while ($datos_comprador = mysqli_fetch_assoc($respuesta)) {
													?>
														<option value="<?php echo $datos_comprador['codigo']; ?>"><?php echo $datos_comprador['nombre'] ?></option>
													<?php
													}
													?>
												</select>
											</div>
										</div>
										<div class="col-sm-7">
											<div class="input-group">
												<span class="input-group-addon"><span class="glyphicon glyphicon-qrcode"></span></span>
												<input type="text" class="form-control" onkeyup="info_contribuyente();" id="ruc_cliente" name="ruc_cliente" title="Ruc, cedula o pasaporte del cliente" placeholder="Ruc/cédula/pasaporte" required>
											</div>
										</div>
									</div>
									<div class="form-group">
										<div class="col-sm-12">
											<div class="input-group">
												<span class="input-group-addon"><span class="glyphicon glyphicon-user"></span></span>
												<input type="text" class="form-control" id="nombre_cliente" name="nombre_cliente" title="Nombre del cliente" required placeholder="Nombre cliente">
											</div>
										</div>
									</div>
									<div class="form-group">
										<div class="col-sm-5">
											<div class="input-group">
												<span class="input-group-addon"><span class="glyphicon glyphicon-phone"></span></span>
												<input class="form-control" id="telefono_cliente" name="telefono_cliente" placeholder="Teléfono" title="Teléfono">
											</div>
										</div>
										<div class="col-sm-7">
											<div class="input-group">
												<span class="input-group-addon"><span class="glyphicon glyphicon-road"></span></span>
												<input type="text" class="form-control" id="direccion_cliente" name="direccion_cliente" title="Dirección del cliente" placeholder="Dirección/sector" required>
											</div>
										</div>
									</div>
									<div class="form-group">
										<div class="col-sm-5">
											<div class="input-group">
												<span class="input-group-addon"><span class="glyphicon glyphicon-time"></span></span>
												<input class="form-control" id="plazo_cliente" name="plazo_cliente" placeholder="5 Días" title="Días de plazo a crédito" value="1">
											</div>
										</div>
										<div class="col-sm-7">
											<div class="input-group">
												<span class="input-group-addon"><span class="glyphicon glyphicon-envelope"></span></span>
												<input type="email" class="form-control" id="email_cliente" name="email_cliente" title="mail del cliente" placeholder="e-mail" required>
											</div>
										</div>
									</div>
								</div>
								<div id="resultados_info_sri"></div>
								<div class="modal-footer">
									<button type="submit" class="btn btn-primary" id="guardar_datos">Guardar</button>
									<button type="button" class="btn btn-primary" id="borrar_datos">Nuevo</button>
									<button type="button" class="btn btn-default" data-dismiss="modal" id="cancelar_asigna_cliente">Cancelar</button>
								</div>
							</form>
						</div>
					</div>
				</div>
			</div>
			<script>
				function info_contribuyente() {
					var ruc = document.getElementById('ruc_cliente').value;
					var tipo_id = document.getElementById('tipo_id_cliente').value;
					if ((tipo_id == '05' && ruc.length == 10) || (tipo_id == '04' && ruc.length == 13)) {
						$.ajax({
							type: "POST",
							url: "../clases/info_ruc_sri.php?action=info_ruc",
							data: "numero=" + ruc,
							beforeSend: function(objeto) {
								$("#resultados_info_sri").html('Cargando información, espere por favor...');
							},
							success: function(datos) {
								$.each(datos, function(i, item) {
									$("#nombre_cliente").val(item.nombre);
									$("#direccion_cliente").val(item.direccion);
								});
								$("#resultados_info_sri").html('');
							}
						});
					}
				}
			</script>