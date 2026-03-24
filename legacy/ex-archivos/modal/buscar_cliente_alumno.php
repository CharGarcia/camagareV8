			<!-- Modal -->
			<div class="modal fade bs-example-modal-md" id="agregarClienteAlumno" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
				<div class="modal-dialog modal-md" role="document">
					<div class="modal-content">
						<div class="modal-header">
							<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
							<h4 class="modal-title" id="myModalLabel"><span class='glyphicon glyphicon-search'></span> Buscar clientes y asignar a un alumno</h4>
						</div>
						<div class="modal-body">
							<div id="resultados_busqueda_cliente"></div><!-- Carga los datos ajax -->
							<div class="outer_divcli"></div><!-- Datos ajax Final -->

							<form class="form-horizontal" method="post" id="guardar_cliente_alumno" name="guardar_cliente_alumno">
								<input type="hidden" name="id_alumno" id="id_alumno">
								<input type="hidden" name="id_cliente_alumno" id="id_cliente_alumno">

								<div class="form-group">
									<div class="col-sm-12">
										<div class="input-group">
											<span class="input-group-addon"><span class="glyphicon glyphicon-search"></span></span>
											<input type="text" class="form-control" id="buscar_cliente_alumno" name="buscar_cliente_alumno" title="Nombre, Ruc, cedula o pasaporte del cliente" placeholder="Buscar cliente" onkeyup="buscar_clientes_alumnos();" autocomplete="off">
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
														<option value="<?php echo $datos_comprador['codigo'] ?>"><?php echo $datos_comprador['nombre'] ?></option>
													<?php
													}
													?>
												</select>
											</div>
										</div>
										<div class="col-sm-7">
											<div class="input-group">
												<span class="input-group-addon"><span class="glyphicon glyphicon-qrcode"></span></span>
												<input type="text" class="form-control" onkeyup="info_contribuyente();" id="ruc_cliente_alumno" name="ruc_cliente_alumno" title="Ruc, cedula o pasaporte del cliente" placeholder="Ruc/cédula/pasaporte" required>
											</div>
										</div>
									</div>
									<div class="form-group">
										<div class="col-sm-12">
											<div class="input-group">
												<span class="input-group-addon"><span class="glyphicon glyphicon-user"></span></span>
												<input type="text" class="form-control" id="nombre_cliente_alumno" name="nombre_cliente_alumno" title="Nombre del cliente" required placeholder="Nombre cliente">
											</div>
										</div>
									</div>
									<div class="form-group">
										<div class="col-sm-5">
											<div class="input-group">
												<span class="input-group-addon"><span class="glyphicon glyphicon-phone"></span></span>
												<input class="form-control" id="telefono_cliente_alumno" name="telefono_cliente_alumno" placeholder="Teléfono" title="Teléfono">
											</div>
										</div>
										<div class="col-sm-7">
											<div class="input-group">
												<span class="input-group-addon"><span class="glyphicon glyphicon-road"></span></span>
												<input type="text" class="form-control" id="direccion_cliente_alumno" name="direccion_cliente_alumno" title="Dirección del cliente" placeholder="Dirección/sector" required>
											</div>
										</div>
									</div>
									<div class="form-group">
										<div class="col-sm-5">
											<div class="input-group">
												<span class="input-group-addon"><span class="glyphicon glyphicon-time"></span></span>
												<input class="form-control" id="plazo_cliente_alumno" name="plazo_cliente_alumno" placeholder="5 Días" title="Días de plazo a crédito" value="5">
											</div>
										</div>
										<div class="col-sm-7">
											<div class="input-group">
												<span class="input-group-addon"><span class="glyphicon glyphicon-envelope"></span></span>
												<input type="email" class="form-control" id="email_cliente_alumno" name="email_cliente_alumno" title="mail del cliente" placeholder="e-mail" required>
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
					var ruc = document.getElementById('ruc_cliente_alumno').value;
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
									$("#nombre_cliente_alumno").val(item.nombre);
									$("#direccion_cliente_alumno").val(item.direccion);
								});
								$("#resultados_info_sri").html('');
							}
						});
					}
				}
			</script>