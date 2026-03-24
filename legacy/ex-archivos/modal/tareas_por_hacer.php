	<!-- Modal -->
	<div class="modal fade" id="modalTareas" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					<h5 class="modal-title" id="titleModalTareas"></h5>
				</div>
				<div class="modal-body">
					<?php
					$con = conenta_login();
					$id_usuario = $_SESSION['id_usuario'];
					?>
					<form class="form-horizontal" method="post" id="guardar_tarea" name="guardar_tarea">
						<input type="hidden" id="idTarea" value="">
						<div class="form-group">
							<div class="col-sm-12">
								<div class="input-group">
									<span class="input-group-addon"><b>Empresa</b></span>
									<select class="form-control" id="id_empresa" name="id_empresa" required>
										<?php
										$sql_empresas = mysqli_query($con, "SELECT emp.id as id, concat(emp.nombre_comercial,'-',emp.nombre) as empresa FROM empresas as emp 
										INNER JOIN empresa_asignada as asi ON asi.id_empresa=emp.id WHERE asi.id_usuario='" . $id_usuario . "' and emp.estado='1' order by emp.nombre_comercial asc");
										while ($datos_empresa = mysqli_fetch_assoc($sql_empresas)) {
										?>
											<option value="<?php echo $datos_empresa['id']; ?>"><?php echo $datos_empresa['empresa'] ?></option>
										<?php
										}
										?>
									</select>
								</div>
							</div>
						</div>
						<div class="form-group">
							<div class="col-sm-12">
								<div class="input-group">
									<span class="input-group-addon"><b>Obligación</b></span>
									<select class="form-control" id="id_obligacion" name="id_obligacion" required>
										<?php
										$sql_obligacion = mysqli_query($con, "SELECT * FROM obligaciones_empresas where status ='1' order by descripcion asc ");
										while ($datos_obligacion = mysqli_fetch_assoc($sql_obligacion)) {
										?>
											<option value="<?php echo $datos_obligacion['id'] ?>"><?php echo $datos_obligacion['descripcion'] ?></option>
										<?php
										}
										?>
									</select>
								</div>
							</div>
						</div>
						<div class="form-group">
							<div class="col-sm-6">
								<div class="input-group">
									<span class="input-group-addon"><b>Fecha a realizar</b></span>
									<input type="date" class="form-control" title="Fecha a realizar" id="fecha_realizar" name="fecha_realizar" value="<?php echo date("Y-m-d"); ?>">
								</div>
							</div>
							<div class=" col-sm-6">
								<div class="input-group">
									<span class="input-group-addon"><b>Repetir</b></span>
									<select class="form-control" id="repetir" name="repetir" required>
										<option value="0" selected>No</option>
										<option value="1">Mensual</option>
										<option value="2">Anual</option>
									</select>
								</div>
							</div>
						</div>

						<div class="form-group">
							<div class="col-sm-7">
								<div class="input-group">
									<span class="input-group-addon"><b>Observaciones</b></span>
									<input type="text" class="form-control" title="Observaciones" id="observacion" name="observacion">
								</div>
							</div>
							<div class="col-sm-5">
								<div class="input-group">
									<span class="input-group-addon"><b>Estado</b></span>
									<select class="form-control" id="status" name="status" disabled required>
										<option value="1" selected>Por realizar</option>
										<option value="2">Realizada</option>
									</select>
								</div>
							</div>

						</div>

				</div>
				<div class="modal-footer">
					<span id="resultados_modal_tareas"></span>
					<button type="button" class="btn btn-default" data-dismiss="modal" title="Cerrar">Cerrar</button>
					<button type="button" onclick="guarda_tarea();" id="btnActionFormTarea" class="btn btn-primary" title=""><span id="btnTextTarea"></span></button>
				</div>
				</form>
			</div>
		</div>
	</div>

	<!--modal usuarios asigandas -->
	<div data-backdrop="static" id="tarea_asignadas" class="modal fade" role="dialog">
		<div class="modal-dialog modal-md">
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal">×</button>
					<h5 class="modal-title"><i class='glyphicon glyphicon-ok'></i> Tarea Asignada a Usuarios</h5>
				</div>
				<div class="modal-body">
					<form id="formEmpresaAsignada">
						<input type="hidden" id="idEmpresaAsignada" name="idEmpresaAsignada" value="">
						<div class="form-group row">
							<div class="col-sm-12">
								<div class="input-group">
									<span class="input-group-addon"><b>Empresa</b></span>
									<input type="text" name="nombre_empresa_asignada" class="form-control" id="nombre_empresa_asignada" value="" readonly>
								</div>
							</div>
						</div>
						<div id="resultados_asignaciones"></div><!-- Carga los datos ajax -->
						<div class="outer_div_asiganciones"></div><!-- Carga los datos ajax -->
				</div>
				<div class="modal-footer">
					<span id="resultados_modal_tarea_asignada"></span>
					<button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
				</div>
				</form>
			</div>
		</div>
	</div>
	<script>
		function guarda_tarea() {
			$('#btnTextTarea').attr("disabled", true);
			var idTarea = $("#idTarea").val();
			var id_empresa = $("#id_empresa").val();
			var id_obligacion = $("#id_obligacion").val();
			var fecha_realizar = $("#fecha_realizar").val();
			var repetir = $("#repetir").val();
			var observacion = $("#observacion").val();
			var status = $("#status").val();

			$.ajax({
				type: "POST",
				url: "../ajax/tareas_por_hacer.php?action=guardar_tarea",
				data: "idTarea=" + idTarea + "&id_empresa=" + id_empresa +
					"&id_obligacion=" + id_obligacion + "&fecha_realizar=" + fecha_realizar +
					"&repetir=" + repetir + "&observacion=" + observacion + "&status=" + status,
				beforeSend: function(objeto) {
					$("#resultados_modal_tareas").html("Guardando...");
				},
				success: function(datos) {
					$("#resultados_modal_tareas").html(datos);
					$('#btnTextTarea').attr("disabled", false);
				}
			});
			event.preventDefault();
		}
	</script>