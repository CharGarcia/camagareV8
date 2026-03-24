<div id="NuevaCuenta" data-backdrop="static" class="modal fade" role="dialog">
	<div class="modal-dialog modal-lg">
		<!-- Modal content-->
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal">×</button>
				<h4 class="modal-title"><i class='glyphicon glyphicon-pencil'></i> Nueva cuenta contable</h4>
			</div>

			<div class="modal-body">
				<form class="form-horizontal" method="post" name="guardar_nueva_cuenta" id="guardar_nueva_cuenta">
					<div id="resultados_ajax_guardar_cuentas"></div>
					<input type="hidden" class="form-control text-left" name="nuevo_nivel_cuenta" id="nuevo_nivel_cuenta">
					<div class="form-group">
						<div class="col-sm-12">
							<textarea class="form-control text-left" id="mostrar_codigo_cuenta" readonly rows="4"></textarea>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-4">
							<div class="input-group">
								<span class="input-group-addon"><b>Código Contable</b></span>
								<input type="text" class="form-control text-left" name="nuevo_codigo_cuenta" id="nuevo_codigo_cuenta">
							</div>
						</div>
						<div class="col-sm-8">
							<div class="input-group">
								<span class="input-group-addon"><b>Cuenta Contable</b></span>
								<input type="text" class="form-control text-left" name="nuevo_nombre_cuenta" id="nuevo_nombre_cuenta">
							</div>
						</div>
					</div>

					<div class="form-group">
						<div class="col-sm-4">
							<div class="input-group">
								<span class="input-group-addon"><b>Código SRI</b></span>
								<input autocomplete="off" type="text" class="form-control text-left" name="nuevo_codigo_sri" id="nuevo_codigo_sri">
							</div>
						</div>
						<div class="col-sm-8">
							<div class="input-group">
								<span class="input-group-addon"><b>Cuenta SRI</b></span>
								<textarea class="form-control text-left" id="nueva_cuenta_sri" readonly></textarea>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-4">
							<div class="input-group">
								<span class="input-group-addon"><b>Código Supercias</b></span>
								<input onkeyup='buscar_cuentas_supercias();' autocomplete="off" type="text" class="form-control text-left" name="nuevo_codigo_supercias" id="nuevo_codigo_supercias">
							</div>
						</div>
						<div class="col-sm-8">
							<div class="input-group">
								<span class="input-group-addon"><b>Cuenta Supercias</b></span>
								<textarea class="form-control text-left" id="nueva_cuenta_supercias" readonly></textarea>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-4">
						</div>
						<div class="col-sm-8">
							<div class="input-group">
								<span class="input-group-addon"><b>Proyecto / Centro de costos</b></span>
								<select class="form-control input-sm" id="proyecto" name="proyecto">
									<option value="0" selected>Ninguno</option>
									<?php
									$con = conenta_login();
									$ruc_empresa = $_SESSION['ruc_empresa'];
									$sql_proyecto = mysqli_query($con, "SELECT * FROM proyectos WHERE ruc_empresa ='" . $ruc_empresa . "' and status ='1' order by nombre asc");
									foreach ($sql_proyecto as $proyecto) {
									?>
										<option value="<?php echo $proyecto['id'] ?>"><?php echo strtoupper($proyecto['nombre']) ?></option>
									<?php
									}
									?>
								</select>
							</div>
						</div>
					</div>
			</div>
			<div class="modal-footer">
				<span id="loader_guardar_cuenta"></span>
				<button type="button" class="btn btn-default" data-dismiss="modal" reset>Cerrar</button>
				<button type="submit" class="btn btn-primary" id="guardar_datos">Guardar</button>
			</div>
			</form>
		</div>

	</div>
</div>

<div id="EditarCuentaContable" data-backdrop="static" class="modal fade" role="dialog">
	<div class="modal-dialog modal-lg">
		<!-- Modal content-->
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal">×</button>
				<h4 class="modal-title"><i class='glyphicon glyphicon-edit'></i> Editar cuenta contable</h4>
			</div>
			<div class="modal-body">
				<form class="form-horizontal" method="post" name="editar_cuenta" id="editar_cuenta">
					<div id="resultados_ajax_editar_cuentas"></div>
					<input type="hidden" name="mod_id_cuenta" id="mod_id_cuenta">
					<div class="form-group">
						<div class="col-sm-2">
							<div class="input-group">
								<span class="input-group-addon"><b>Nivel</b></span>
								<input type="text" class="form-control text-center" name="mod_nivel_cuenta" id="mod_nivel_cuenta" readonly>
							</div>
						</div>
						<div class="col-sm-3">
							<div class="input-group">
								<span class="input-group-addon"><b>Status</b></span>
								<select class="form-control form-control-sm" id="listStatus" name="listStatus">
									<option value="1" selected>Activa</option>
									<option value="0">Inactiva</option>
								</select>
							</div>
						</div>
						<div class="col-sm-7">
							<div class="input-group">
								<span class="input-group-addon"><b>Proyecto / Centro de costos</b></span>
								<select class="form-control input-sm" id="mod_proyecto" name="mod_proyecto">
									<option value="0" selected>Ninguno</option>
									<?php
									$sql_proyecto = mysqli_query($con, "SELECT * FROM proyectos WHERE ruc_empresa ='" . $ruc_empresa . "' and status ='1' order by nombre asc");
									foreach ($sql_proyecto as $proyecto) {
									?>
										<option value="<?php echo $proyecto['id'] ?>"><?php echo strtoupper($proyecto['nombre']) ?></option>
									<?php
									}
									?>
								</select>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-4">
							<div class="input-group">
								<span class="input-group-addon"><b>Código Contable</b></span>
								<input type="text" class="form-control text-left" name="mod_codigo_cuenta" id="mod_codigo_cuenta" readonly>
							</div>
						</div>
						<div class="col-sm-8">
							<div class="input-group">
								<span class="input-group-addon"><b>Cuenta contable</b></span>
								<input type="text" class="form-control text-left" name="mod_nombre_cuenta" id="mod_nombre_cuenta">
							</div>
						</div>
					</div>

					<div class="form-group">
						<div class="col-sm-4">
							<div class="input-group">
								<span class="input-group-addon"><b>Código SRI</b></span>
								<input autocomplete="off" type="text" class="form-control text-left" name="mod_codigo_sri" id="mod_codigo_sri">
							</div>
						</div>
						<div class="col-sm-8">
							<div class="input-group">
								<span class="input-group-addon"><b>Cuenta SRI</b></span>
								<textarea class="form-control text-left" id="mod_cuenta_sri" readonly></textarea>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-4">
							<div class="input-group">
								<span class="input-group-addon"><b>Código Supercias</b></span>
								<input onkeyup='buscar_cuentas_supercias_editar();' autocomplete="off" type="text" class="form-control text-left" name="mod_codigo_supercias" id="mod_codigo_supercias">
							</div>
						</div>
						<div class="col-sm-8">
							<div class="input-group">
								<span class="input-group-addon"><b>Cuenta Supercias</b></span>
								<textarea class="form-control text-left" id="mod_cuenta_supercias" readonly></textarea>
							</div>
						</div>
					</div>

			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
				<button type="submit" class="btn btn-primary" id="actualizar_datos">Actualizar</button>
			</div>
			</form>
		</div>
	</div>
</div>
<script>
	$("#guardar_nueva_cuenta").submit(function(event) {
		var page = $("#pagina").val();
		$('#guardar_datos').attr("disabled", true);
		var parametros = $(this).serialize();
		$.ajax({
			type: "POST",
			url: "../ajax/plan_de_cuentas.php?action=guardar_cuenta_contable",
			data: parametros,
			beforeSend: function(objeto) {
				$("#loader_guardar_cuenta").html("Guardando...");
			},
			success: function(datos) {
				$("#resultados_ajax_guardar_cuentas").html(datos);
				$("#loader_guardar_cuenta").html("");
				$('#guardar_datos').attr("disabled", false);
				load(page);
			}
		});
		event.preventDefault();
	})

	$("#editar_cuenta").submit(function(event) {
		$('#actualizar_datos').attr("disabled", true);
		var parametros = $(this).serialize();
		$.ajax({
			type: "POST",
			url: "../ajax/plan_de_cuentas.php?action=editar_cuenta_contable",
			data: parametros,
			beforeSend: function(objeto) {
				$("#resultados_ajax_editar_cuentas").html("Actualizando...");
			},
			success: function(datos) {
				$("#resultados_ajax_editar_cuentas").html('');
				$("#resultados_ajax_editar_cuentas").html(datos);
				$('#actualizar_datos').attr("disabled", false);
				load(1);
			}
		});
		event.preventDefault();
	})

	function buscar_cuentas_supercias() {
		$("#nuevo_codigo_supercias").autocomplete({
			source: '../ajax/plan_de_cuentas.php?action=cuentas_supercias',
			minLength: 1,
			select: function(event, ui) {
				event.preventDefault();
				$('#nuevo_codigo_supercias').val(ui.item.codigo);
				$('#nueva_cuenta_supercias').val(ui.item.cuenta);
			}
		});

		//$("#nuevo_codigo_supercias").autocomplete("widget").addClass("fixedHeight"); //para que aparezca la barra de desplazamiento en el buscar
		$("#nuevo_codigo_supercias").on("keydown", function(event) {
			if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
				$("#nuevo_codigo_supercias").val("");
				$("#nueva_cuenta_supercias").val("");
			}
			if (event.keyCode == $.ui.keyCode.DELETE) {
				$("#nuevo_codigo_supercias").val("");
				$("#nueva_cuenta_supercias").val("");
			}
		});
	}

	function buscar_cuentas_supercias_editar() {
		$("#mod_codigo_supercias").autocomplete({
			source: '../ajax/plan_de_cuentas.php?action=cuentas_supercias',
			minLength: 1,
			select: function(event, ui) {
				event.preventDefault();
				$('#mod_codigo_supercias').val(ui.item.codigo);
				$('#mod_cuenta_supercias').val(ui.item.cuenta);
			}
		});

		//$("#mod_codigo_supercias").autocomplete("widget").addClass("fixedHeight"); //para que aparezca la barra de desplazamiento en el buscar
		$("#mod_codigo_supercias").on("keydown", function(event) {
			if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
				$("#mod_codigo_supercias").val("");
				$("#mod_cuenta_supercias").val("");
			}
			if (event.keyCode == $.ui.keyCode.DELETE) {
				$("#mod_codigo_supercias").val("");
				$("#mod_cuenta_supercias").val("");
			}
		});
	}
</script>