<div class="modal fade" data-backdrop="static" id="detalleAprobacionesAdquisiciones" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" style="overflow-y: scroll;">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="titleModalAprobacionesAdquisiciones"></h4>
			</div>
			<div class="modal-body">
				<form class="form-horizontal" method="post" id="guardar_aprobaciones_adquisiciones" name="guardar_aprobaciones_adquisiciones">
					<input type="hidden" id="id_aprobacion_adquisicion" name="id_aprobacion_adquisicion">
					<div class="form-group">
						<div class="col-xs-3">
							<div class="input-group">
								<span class="input-group-addon"><b>Mes</b></span>
								<select class="form-control" name="mes" id="mes">
									<?php foreach (Meses() as $key => $value) { ?>
										<option value="<?php echo $value['codigo'] ?>" selected> <?php echo $value['nombre'] ?></option>
									<?php }  ?>
								</select>
							</div>
						</div>
						<div class="col-xs-3">
							<div class="input-group">
								<span class="input-group-addon"><b>Año</b></span>
								<select class="form-control" name="anio" id="anio">
									<option value="<?php echo date("Y") ?>" selected> <?php echo date("Y") ?></option>
									<?php for ($i = $anio2 = date("Y") - 1; $i > $anio1 = date("Y") - 5; $i += -1) {
									?>
										<option value="<?php echo $i ?>"> <?php echo $i ?></option>
									<?php }  ?>
								</select>
							</div>
						</div>
						<div class="col-xs-3">
							<div class="input-group">
								<button type="button" id="boton_buscar" class="btn btn-default" onclick="buscar_compras_aprobaciones()"><span class="glyphicon glyphicon-search"></span> Buscar</button>
								<spam id="loader_aprobaciones_adquisiciones"></spam>
							</div>

						</div>
					</div>
					<div class="outer_div_aprobaciones_adquisiciones"></div>
			</div>
			<div class="modal-footer">
				<span id="resultados_aprobaciones_adquisiciones"></span>
				<button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
				<button type="submit" class="btn btn-primary" id="btnActionFormAprobacionesAdquisiciones"><span id="btnTextAprobacionesAdquisiciones"></span></button>
			</div>
			</form>
		</div>
	</div>
</div>

<div class="modal fade" data-backdrop="static" id="detalleComprasAprobadas" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" style="overflow-y: scroll;">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="titleModalComprasAprobadas"></h4>
			</div>
			<div class="modal-body">
				<div class="loader_compras_aprobadas"></div>
				<div class="outer_div_compras_aprobadas"></div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>
<script>
	$("#guardar_aprobaciones_adquisiciones").submit(function(event) {
		$('#btnActionFormAprobacionesAdquisiciones').attr("disabled", true);
		var parametros = $(this).serialize();
		$.ajax({
			type: "POST",
			url: "../ajax/aprobar_adquisiciones.php?action=guardar_compras_aprobadas",
			data: parametros,
			beforeSend: function(objeto) {
				$("#loader_aprobaciones_adquisiciones").html("Guardando...");
			},
			success: function(datos) {
				$("#loader_aprobaciones_adquisiciones").html(datos);
				$('#btnActionFormAprobacionesAdquisiciones').attr("disabled", false);
			}
		});
		event.preventDefault();
	})
</script>