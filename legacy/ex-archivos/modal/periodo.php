<div class="modal fade" id="nuevoPeriodo" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
	<div class="modal-dialog modal-sm" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="titleModalPeriodo"></h4>
			</div>
			<div class="modal-body">
				<form class="form-horizontal" method="post" id="guardar_periodo" name="guardar_periodo">
					<input type="hidden" name="idPeriodo" id="idPeriodo">
					<div class="form-group">
						<label for="" class="col-sm-4 control-label"> Mes</label>
						<div class="col-sm-6">
							<select class="form-control" name="mes_periodo" id="mes_periodo">
								<?php foreach (Meses() as $mes) {
									if (date("m") == $mes['codigo']) {
								?>
										<option value="<?= $mes['codigo']; ?>" selected><?= ucwords($mes['nombre']); ?></option>
									<?php
									} else {
									?>
										<option value="<?= $mes['codigo']; ?>"><?= ucwords($mes['nombre']); ?></option>
								<?php
									}
								}
								?>
							</select>
						</div>
					</div>
					<div class="form-group">
						<label for="" class="col-sm-4 control-label"> Año</label>
						<div class="col-sm-6">
							<select class="form-control text-left" name="anio_periodo" id="anio_periodo">
								<option value="<?php echo date("Y") + 1 ?>" selected> <?php echo date("Y") + 1 ?></option>
								<option value="<?php echo date("Y") ?>" selected> <?php echo date("Y") ?></option>
								<?php for ($i = $anio2 = date("Y") - 1; $i > $anio1 = date("Y") - 10; $i += -1) {
								?>
									<option value="<?php echo $i ?>"> <?php echo $i ?></option>
								<?php }  ?>
							</select>
						</div>
					</div>

					<div class="form-group">
						<label for="" class="col-sm-4 control-label"> Status</label>
						<div class="col-sm-6">
							<select class="form-control" id="listStatus" name="listStatus">
								<option value="1">Abierto</option>
								<option value="2" selected>Cerrado</option>
							</select>
						</div>
					</div>

			</div>
			<div class="modal-footer">
				<span id="resultados_ajax_guarda_periodo"></span>
				<button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
				<button type="button" class="btn btn-primary" id="btnActionFormPeriodo" onclick="guarda_periodo();"><span id="btnTextPeriodo"></span></button>
			</div>
			</form>
		</div>
	</div>
</div>
<script>
	function guarda_periodo() {
		$('#btnTextPeriodo').attr("disabled", true);
		var idPeriodo = $("#idPeriodo").val();
		var mes_periodo = $("#mes_periodo").val();
		var anio_periodo = $("#anio_periodo").val();
		var listStatus = $("#listStatus").val();
		$.ajax({
			type: "POST",
			url: "../ajax/periodos.php?action=guardar_periodo",
			data: "idPeriodo=" + idPeriodo + "&mes_periodo=" + mes_periodo +
				"&anio_periodo=" + anio_periodo + "&listStatus=" + listStatus,
			beforeSend: function(objeto) {
				$("#resultados_ajax_guarda_periodo").html("Guardando...");
			},
			success: function(datos) {
				$("#resultados_ajax_guarda_periodo").html(datos);
				$('#btnTextPeriodo').attr("disabled", false);
			}
		});
		event.preventDefault();
	}
</script>