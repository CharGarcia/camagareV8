<div class="modal fade" data-backdrop="static" id="detalle_ventas_ingreso" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" style="overflow-y: auto;">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-list-alt'></i> Facturas de venta por cobrar</h4>
			</div>
			<form method="POST" id="agregar_items_ingreso" name="agregar_items_ingreso">
				<div class="modal-body" style="height: 400px;">
					<div class="form-group row">
						<label for="fv" class="col-md-1 control-label">Buscar:</label>
						<div class="col-md-5">
							<div class="input-group">
								<input type="text" class="form-control" id="fv" placeholder="Cliente, factura" onkeyup='load(1);'>
								<span class="input-group-btn">
									<button type="button" class="btn btn-default" onclick='load(1);'><span class="glyphicon glyphicon-search"></span> Buscar</button>
								</span>
							</div>
						</div>
						<span id="loader_facturas_por_cobrar"></span>
					</div>
					<div id="resultados_facturas_por_cobrar"></div><!-- Carga los datos ajax -->
					<div class='outer_div_facturas_por_cobrar'></div><!-- Carga los datos ajax -->
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-default" data-dismiss="modal" reset>Cerrar</button>
					<button type="submit" class="btn btn-info" id="agregar_items">Agregar al ingreso</button>
				</div>
			</form>
		</div>
	</div>
</div>

<div class="modal fade" data-backdrop="static" id="detalle_ventas_recibo" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" style="overflow-y: auto;">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-list-alt'></i> Recibos de venta por cobrar</h4>
			</div>
			<form method="POST" id="agregar_items_recibo_ingreso" name="agregar_items_recibo_ingreso">
				<div class="modal-body" style="height: 400px;">
					<div class="form-group row">
						<label for="rv" class="col-md-1 control-label">Buscar:</label>
						<div class="col-md-5">
							<div class="input-group">
								<input type="text" class="form-control" id="rv" placeholder="Cliente, recibo" onkeyup='load(1);'>
								<span class="input-group-btn">
									<button type="button" class="btn btn-default" onclick='load(1);'><span class="glyphicon glyphicon-search"></span> Buscar</button>
								</span>
							</div>
						</div>
						<span id="loader_recibos_por_cobrar"></span>
					</div>
					<div id="resultados_recibos_por_cobrar"></div><!-- Carga los datos ajax -->
					<div class='outer_div_recibos_por_cobrar'></div><!-- Carga los datos ajax -->
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-default" data-dismiss="modal" reset>Cerrar</button>
					<button type="submit" class="btn btn-info" id="agregar_items_recibo">Agregar al ingreso</button>
				</div>
			</form>
		</div>
	</div>
</div>
<script>
	function copyToClipboardFacturas(id) {
		let input = document.getElementById('nombre_cliente_seleccionado' + id);
		input.select();
		document.execCommand("copy");
		$("#fv").val(input.value);
		load(1);
	}

	function copyToClipboardRecibos(id) {
		let input = document.getElementById('nombre_cliente_seleccionado' + id);
		input.select();
		document.execCommand("copy");
		$("#rv").val(input.value);
		load(1);
	}

	//para recibos
	$("#agregar_items_recibo_ingreso").submit(function(event) {
		$('#agregar_items').attr("disabled", true);
		var parametros = $(this).serialize();
		$.ajax({
			type: "POST",
			url: "../ajax/detalle_ingresos.php?action=agregar_detalle_de_recibos",
			data: parametros,
			beforeSend: function(objeto) {
				$("#loader_ingreso").html("Agregando... ");
			},
			success: function(datos) {
				$(".outer_div_detalle_ingreso").html(datos).fadeIn('fast');
				$("#loader_ingreso").html('');
				$('#agregar_items').attr("disabled", false);

				var total_ingreso = $("#suma_ingreso").val();
				$("#valor_pago").val(total_ingreso);
				$("#total_ingreso").val(total_ingreso);
				actualiza_ingreso_tmp();
			}
		});
		event.preventDefault(); //no borrar
	})

	//para facturas
	$("#agregar_items_ingreso").submit(function(event) {
		$('#agregar_items').attr("disabled", true);
		var parametros = $(this).serialize();
		$.ajax({
			type: "POST",
			url: "../ajax/detalle_ingresos.php?action=agregar_detalle_de_facturas",
			data: parametros,
			beforeSend: function(objeto) {
				$("#loader_ingreso").html("Agregando... ");
			},
			success: function(datos) {
				$(".outer_div_detalle_ingreso").html(datos).fadeIn('fast');
				$("#loader_ingreso").html('');
				$('#agregar_items').attr("disabled", false);

				var total_ingreso = $("#suma_ingreso").val();
				$("#valor_pago").val(total_ingreso);
				$("#total_ingreso").val(total_ingreso);
				actualiza_ingreso_tmp();
			}
		});
		event.preventDefault(); //no borrar
	})


	function actualiza_ingreso_tmp() {
		document.getElementById("fv").style.visibility = "";
		$.ajax({
			url: '../ajax/detalle_ingresos.php?action=actualiza_ingreso_tmp',
			beforeSend: function(objeto) {
				$('#loader_facturas_por_cobrar').html('Actualizando, espere por favor...');
			},
			success: function(data) {
				$(".outer_div_facturas_por_cobrar").html(data).fadeIn('slow');
				$('#loader_facturas_por_cobrar').html('');
				load(1);
			}
		});
		event.preventDefault();
	}
</script>