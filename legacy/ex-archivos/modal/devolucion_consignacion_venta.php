<?php
//para traer la serie de la sucursal primera
$con = conenta_login();
$ruc_empresa = $_SESSION['ruc_empresa'];
ini_set('date.timezone', 'America/Guayaquil');
?>
<div class="modal fade" data-backdrop="static" id="devolucion_consignacion_venta" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" style="overflow-y: scroll;">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-list-alt'></i> Retorno de consignación en ventas</h4>
			</div>
			<div class="modal-body">
				<form method="POST" id="guardar_devolucion_consignacion_venta" name="guardar_devolucion_consignacion_venta">
					<div id="mensajes_devolucion_consignacion_venta"></div>
					<div class="well well-sm" style="margin-bottom: 5px; margin-top: -10px;">
						<div class="panel-body" style="margin-bottom: -30px; margin-top: -15px;">
							<div class="col-sm-3">
								<div class="form-group">
									<div class="input-group">
										<span class="input-group-addon"><b>Serie</b></span>
										<select class="form-control" style="height:30px;" title="Seleccione serie." name="serie_devolucion_consignacion" id="serie_devolucion_consignacion">
											<?php
											$conexion = conenta_login();
											$sql = "SELECT * FROM sucursales WHERE ruc_empresa ='" . $ruc_empresa . "'";
											$res = mysqli_query($conexion, $sql);
											while ($o = mysqli_fetch_array($res)) {
											?>
												<option value="<?php echo $o['serie'] ?>" selected><?php echo strtoupper($o['serie']) ?></option>
											<?php
											}
											?>
										</select>
									</div>
								</div>
							</div>
							<div class="col-sm-3">
								<div class="form-group">
									<div class="input-group">
										<span class="input-group-addon"><b>Fecha</b></span>
										<input type="text" class="form-control input-sm text-center" name="fecha_devolucion_consignacion_venta" id="fecha_devolucion_consignacion_venta" value="<?php echo date("d-m-Y"); ?>">
									</div>
								</div>
							</div>

							<div class="col-sm-3">
								<div class="form-group">
									<div class="input-group">
										<span class="input-group-addon"><b>No. CV</b></span>
										<input type="number" class="form-control input-sm" class="text-left" title="Ingrese No. CV" name="numero_cv" id="numero_cv" placeholder="No. CV" onkeyup="limpiar_producto();">
									</div>
								</div>
							</div>
							<div class="col-sm-3">
								<div class="form-group">
									<button type="button" class="btn btn-info btn-sm" title="Cargar consignación" onclick="mostrar_consignacion_venta()"><span class="glyphicon glyphicon-search"></span> Cargar CV</button>
									<span id="muestra_detalle_devolucion_consignacion_venta"></span><!-- Carga gif animado -->
								</div>
							</div>
							<div class="col-sm-12">
								<div class="form-group">
									<div class="input-group">
										<span class="input-group-addon"><b>Observaciones</b></span>
										<input type="text" class="form-control input-sm" name="observacion_devolucion_consignacion_venta" id="observacion_devolucion_consignacion_venta">
									</div>
								</div>
							</div>
						</div>
					</div>
					<div class="outer_div_detalle_consignacion_venta"></div><!-- Datos ajax Final -->
			</div>
			<div class="modal-footer">
				<span id="loader_devolucion"></span>
				<button type="button" class="btn btn-default" data-dismiss="modal" id="cerrar_devolucion_consignacion_venta">Cerrar</button>
				<button type="submit" class="btn btn-info" id="guardar_devoluciones">Guardar</button>
			</div>
			</form>
		</div>
	</div>
</div>

<script>
	$("#guardar_devolucion_consignacion_venta").submit(function(event) {
		$('#guardar_devoluciones').attr("disabled", true);
		var parametros = $(this).serialize();
		$.ajax({
			type: "POST",
			url: "../ajax/guardar_devolucion_consignacion_venta.php",
			data: parametros,
			beforeSend: function(objeto) {
				$("#loader_devolucion").html("Agregando... ");
			},
			success: function(datos) {
				$(".outer_div_detalle_consignacion_venta").html(datos).fadeIn('fast');
				$("#loader_devolucion").html('');
				$('#guardar_devoluciones').attr("disabled", false);
			}
		});
		event.preventDefault(); //no borrar
	})
</script>