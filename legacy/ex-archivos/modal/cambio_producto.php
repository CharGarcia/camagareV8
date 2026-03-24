<div class="modal fade" id="cambio_producto" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" style="overflow-y: scroll;">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="titleModalCambioProducto"></h4>
			</div>
			<form method="POST" id="guardar_cambio_productos" name="guardar_cambio_productos">
				<input type="hidden" name="id_cambio_producto" id="id_cambio_producto" value="">
				<div class="modal-body">
					<div class="well well-sm" style="margin-bottom: 5px; margin-top: -10px;">
						<div class="panel-body" style="margin-bottom: -15px; margin-top: -15px;">
							<div class="form-group row">
								<div class="col-md-3">
									<div class="input-group">
										<span class="input-group-addon"><b>Fecha</b></span>
										<input type="text" class="form-control input-sm text-center" name="fecha_cambio_producto" id="fecha_cambio_producto" value="<?php echo date("d-m-Y"); ?>">
									</div>
								</div>
								<div class="col-md-3">
									<div class="input-group">
										<span class="input-group-addon"><b>Desde</b></span>
										<select class="form-control" style="height:30px;" title="Opción de cambio" name="tipo_cambio" id="tipo_cambio">
											<option value="F" selected>Factura</option>
											<option value="R">Re-cambio</option>
										</select>
									</div>
								</div>
								<div class="col-md-6">
									<div class="input-group">
										<span class="input-group-addon"><b>Cliente</b></span>
										<input type="hidden" name="id_cliente_cambio" id="id_cliente_cambio">
										<input type="text" class="form-control input-sm" placeholder="Nombre, ruc" name="cliente_cambio" id="cliente_cambio" autocomplete="off" onkeyup='buscar_clientes();'>
									</div>
								</div>
							</div>
							<div class="form-group row">
								<div class="col-md-8">
									<div class="input-group">
										<span class="input-group-addon"><b>Producto facturado</b></span>
										<input type="hidden" name="id_registro_facturado" id="id_registro_facturado">
										<input type="text" class="form-control input-sm" placeholder="Factura, producto, código, lote" name="producto_facturado" id="producto_facturado" autocomplete="off" onkeyup='buscar_producto_facturado();'>
									</div>
								</div>
								<div class="col-md-3">
									<div class="input-group">
										<span class="input-group-addon"><b>Cantidad</b></span>
										<input type="hidden" name="cantidad_registrada" id="cantidad_registrada">
										<input type="number" class="form-control input-sm" name="cantidad_facturado" id="cantidad_facturado">
									</div>
								</div>
								<div class="col-md-1">
									<div class="input-group">
										<button type="button" class="btn btn-info btn-sm" title="Agregar producto" onclick="agregar_detalle_productos()"><span class="glyphicon glyphicon-plus"></span></button>
									</div>
								</div>
							</div>
							<div class="form-group row">
								<div class="col-md-9">
									<div class="input-group">
										<span class="input-group-addon"><b>Observaciones</b></span>
										<input type="text" class="form-control input-sm" name="observaciones" id="observaciones" autocomplete="off">
									</div>
								</div>
								<div class="col-md-3">
									<div class="input-group">
										<span class="input-group-addon"><b>Serie</b></span>
										<select class="form-control" style="height:30px;" title="Seleccione serie." name="serie_factura" id="serie_factura">
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
						</div>
						<div id="muestra_detalle_cambio_productos"></div><!-- Carga gif animado -->
						<div class="outer_div_cambio_producto"></div><!-- Datos ajax Final -->
					</div>
				</div>
				<div class="modal-footer">
					<span id="mensajes_cambio_productos"></span>
					<span id="loader_cambio_producto"></span>
					<button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
					<button type="submit" class="btn btn-primary" id="btnActionFormCambioProducto"><span id="btnTextCambioProducto"></span></button>
				</div>
			</form>
		</div>
	</div>
</div>

<script>
	//para guardar el cambio de producto
	$("#guardar_cambio_productos").submit(function(event) {
		$('#btnActionFormCambioProducto').attr("disabled", true);
		var parametros = $(this).serialize();
		$.ajax({
			type: "POST",
			url: "../ajax/cambio_producto.php?action=guardar_cambio_producto",
			data: parametros,
			beforeSend: function(objeto) {
				$("#loader_cambio_producto").html("Guardando...");
			},
			success: function(datos) {
				$("#mensajes_cambio_productos").html(datos);
				$("#loader_cambio_producto").html('');
				$('#btnActionFormCambioProducto').attr("disabled", false);
			}
		});
		event.preventDefault();
	})

	/*
	function guarda_cambio_producto() {
		$('#btnTextCambioProducto').attr("disabled", true);
		var id_cambio_producto = $("#id_cambio_producto").val();
		var fecha_cambio_producto = $("#fecha_cambio_producto").val();
		var id_cliente_cambio = $("#id_cliente_cambio").val();
		var observaciones = $("#observaciones").val();
		var serie_factura = $("#serie_factura").val();
		//datos de los arreglos para comprobar si estan llenos los productos en base a las consignaciones
		var registros = []; //los id de los productos agregados a la lista
		var id_cv = []; //los id de los detalles de consignaciones de venta
		var numero_consignacion = []; // los numeros de consignaciones
		var cant_cambio = []; //las cantidades agregadas para los cambios

		const id_cv = document.querySelectorAll('input[name^="id_cv"]');


		 $('input[name="registros[]"]').each(function() {
			registros.push($(this).val());
		});

		$('input[name="id_cv[]"]').each(function() {
			id_cv.push($(this).val());
		});

		$('input[name="numero_consignacion[]"]').each(function() {
			numero_consignacion.push($(this).val());
		});

		$('input[name="cant_cambio[]"]').each(function() {
			cant_cambio.push($(this).val());
		}); 

		$.ajax({
			type: "POST",
			url: "../ajax/cambio_producto.php?action=guardar_cambio_producto",
			data: "id_cambio_producto=" + id_cambio_producto + "&fecha_cambio_producto=" + fecha_cambio_producto +
				"&observaciones=" + encodeURIComponent(observaciones) +
				"&id_cliente_cambio=" + id_cliente_cambio + "&serie_factura=" + serie_factura +
				"&registros=" + registros + "&id_cv=" + id_cv + "&numero_consignacion=" + numero_consignacion +
				"&cant_cambio=" + cant_cambio,
			beforeSend: function(objeto) {
				$("#loader_cambio_producto").html("Guardando...");
			},
			success: function(datos) {
				$("#mensajes_cambio_productos").html(datos);
				$("#loader_cambio_producto").html('');
				$('#btnTextCambioProducto').attr("disabled", false);
			}
		});
		event.preventDefault();
	}
	*/
</script>