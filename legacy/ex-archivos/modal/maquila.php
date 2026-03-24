	<!-- Modal -->
	<div class="modal fade" data-backdrop="static" id="maquila" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
		<div class="modal-dialog modal-lg" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					<h4 class="modal-title" id="titleModalMaquila"></h4>
				</div>
				<div class="modal-body">
					<form class="form-horizontal" method="post" id="guardar_maquila" name="guardar_maquila">
						<input type="hidden" id="id_maquila" name="id_maquila">
						<div class="form-group">
							<div class="col-sm-3">
								<div class="input-group">
									<span class="input-group-addon"><b>Status</b></span>
									<select class="form-control input-sm" id="status" name="status" required>
										<option value="1">En proceso</option>
										<option value="2">Terminado</option>
										<option value="3">Anulado</option>
									</select>
								</div>
							</div>
							<div class="col-sm-5">
								<div class="input-group">
									<span class="input-group-addon"><b>Referencia</b></span>
									<input type="text" class="form-control input-sm" id="referencia" name="referencia">
								</div>
							</div>
							<div class="col-sm-4">
								<div class="input-group">
									<span class="input-group-addon"><b>Factura</b></span>
									<input type="text" class="form-control input-sm" id="factura" name="factura">
								</div>
							</div>
						</div>
						<div class="form-group">
							<div class="col-sm-6">
								<div class="input-group">
									<span class="input-group-addon"><b>Proveedor</b></span>
									<input type="hidden" id="id_proveedor" name="id_proveedor">
									<input type="text" class="form-control input-sm" id="proveedor" name="proveedor" onkeyup='buscar_proveedor();' autocomplete="off">
								</div>
							</div>
							<div class="col-sm-6">
								<div class="input-group">
									<span class="input-group-addon"><b>Producto</b></span>
									<input type="hidden" id="id_producto" name="id_producto">
									<input type="text" class="form-control input-sm" id="producto" name="producto" onkeyup='buscar_producto();' autocomplete="off">
								</div>
							</div>
							</div>
							<div class="form-group">
							<div class="col-sm-3">
								<div class="input-group">
									<span class="input-group-addon"><b>Cantidad</b></span>
									<input type="number" style="text-align:right;" class="form-control input-sm" id="cantidad" name="cantidad" oninput="calculo_totales();">
								</div>
							</div>
							<div class="col-sm-3">
								<div class="input-group">
									<span class="input-group-addon"><b>Costo unitario</b></span>
									<input type="number" style="text-align:right;" class="form-control input-sm" id="precio_costo" name="precio_costo" oninput="calculo_totales();" required>
								</div>
							</div>
							<div class="col-sm-3">
								<div class="input-group">
									<span class="input-group-addon"><b>Valor Venta</b></span>
									<input type="number" style="text-align:right;" class="form-control input-sm" id="precio_venta" name="precio_venta" oninput="calculo_totales();" required>
								</div>
							</div>
							<div class="col-sm-3">
								<div class="input-group">
									<span class="input-group-addon"><b>Uti. x unidad</b></span>
									<input type="number" style="text-align:right;" class="form-control input-sm" id="utilidad_por_unidad" name="utilidad_por_unidad" readonly>
								</div>
							</div>
						</div>
						<div class="form-group">
							<div class="col-sm-3">
								<div class="input-group">
									<span class="input-group-addon"><b>Inicio</b></span>
									<input type="text" class="form-control input-sm" id="fecha_inicio" name="fecha_inicio" value="<?php echo date("d-m-Y"); ?>">
								</div>
							</div>
							<div class="col-sm-3">
								<div class="input-group">
									<span class="input-group-addon"><b>Entrega</b></span>
									<input type="text" class="form-control input-sm" id="fecha_entrega" name="fecha_entrega">
								</div>
							</div>
							<div class="col-sm-3">
								<div class="input-group">
									<span class="input-group-addon"><b>Bultos</b></span>
									<input type="number" style="text-align:right;" class="form-control input-sm" id="bultos" name="bultos">
								</div>
							</div>
							<div class="col-sm-3">
								<div class="input-group">
									<span class="input-group-addon"><b>Total venta</b></span>
									<input type="number" style="text-align:right;" class="form-control input-sm" id="total_venta" name="total_venta" readonly>
								</div>
							</div>
						</div>
						<div class="form-group">
							<div class="col-sm-3">
								<div class="input-group">
									<span class="input-group-addon"><b>Total costos</b></span>
									<input type="number" style="text-align:right;" class="form-control input-sm" id="total_costos" name="total_costos" readonly>
								</div>
							</div>
							<div class="col-sm-3">
								<div class="input-group">
									<span class="input-group-addon"><b>Total gastos</b></span>
									<input type="number" style="text-align:right;" class="form-control input-sm" id="total_gastos" name="total_gastos" readonly>
								</div>
							</div>
							<div class="col-sm-3">
								<div class="input-group">
									<span class="input-group-addon"><b>Utilidad neta</b></span>
									<input type="number" style="text-align:right;" class="form-control input-sm" id="utilidad_neta" name="utilidad_neta" readonly>
								</div>
							</div>
							<div class="col-sm-3">
								<div class="input-group">
									<span class="input-group-addon"><b>Por pagar</b></span>
									<input type="number" style="text-align:right;" class="form-control input-sm" id="por_pagar" name="por_pagar" readonly>
								</div>
							</div>							
						</div>
						<div class="panel panel-info" style="margin-bottom: -10px; height: 14%">
							<div class="table-responsive">
								<table class="table table-bordered">
									<tr class="info">
										<th style="padding: 2px;" class="text-left">Detalle de Gastos</th>
										<th style="padding: 2px;" class="text-left">Detalle de Pagos o abonos</th>
									</tr>
									<td class="col-xs-6" style="padding: 0px;">
										<div class="table-responsive">
											<table class="table table-bordered" style="padding: 0px; margin-bottom: 0px;">
												<tr class="info">
													<td class="col-xs-4" style="padding: 2px;">
														<input type="text" style="height:25px;" class="form-control input-sm" id="fecha_gasto" value="<?php echo date("d-m-Y"); ?>">
													</td>
													<td class="col-xs-3" style="padding: 2px;">
														<input type="text" style="height:25px;" class="form-control input-sm" id="valor_gasto" placeholder="Valor">
													</td>
													<td class="col-xs-5" style="padding: 2px;">
														<input type="text" style="height:25px;" class="form-control input-sm" id="detalle_gasto" placeholder="Detalle gasto">
													</td>
													<td class="col-xs-1" style="padding: 2px;"><button type="button" style="height:25px;" class="btn btn-info btn-sm" title="Agregar gasto" onclick="agrega_gasto()"><span class="glyphicon glyphicon-plus"></span></button></td>
												</tr>
											</table>
										</div>
									</td>
									<td class="col-xs-6" style="padding: 0px;">
										<div class="table-responsive">
											<table class="table table-bordered" style="padding: 0px; margin-bottom: 0px;">
												<tr class="info">
													<td class="col-xs-4" style="padding: 2px;">
														<input type="text" style="height:25px;" class="form-control input-sm" id="fecha_pago" value="<?php echo date("d-m-Y"); ?>">
													</td>
													<td class="col-xs-3" style="padding: 2px;">
														<input type="text" style="height:25px;" class="form-control input-sm" id="valor_pago" placeholder="Valor">
													</td>
													<td class="col-xs-5" style="padding: 2px;">
														<input type="text" style="height:25px;" class="form-control input-sm" id="detalle_pago" placeholder="Detalle pago">
													</td>
													<td class="col-xs-1" style="padding: 2px;"><button type="button" style="height:25px;" class="btn btn-info btn-sm" title="Agregar pago" onclick="agrega_pago()"><span class="glyphicon glyphicon-plus"></span></button></td>
												</tr>
											</table>
										</div>
									</td>
								</table>
							</div>
						</div>

					<!-- para mostrar los arreglos de pagos y gastos-->
							<div class="panel panel-info" style="margin-bottom: -10px; margin-top: 15px; height: 14%">
							<div class="table-responsive">
								<table class="table table-bordered">
								<td class="col-xs-6" style="padding: 0px;">
										<div class="table-responsive">
										<div id="detalle_gastos"></div>
										</div>
									</td>
									<td class="col-xs-6" style="padding: 0px;">
										<div class="table-responsive">
										<div id="detalle_pagos"></div>
										</div>
									</td>
								</table>
							</div>
						</div>
					<!-- hasta aqui mostramos los arreglos de pagos y gastos-->
				</div>
				<div class="modal-footer">
					<span id="resultados_info_sri"></span>
					<span id="resultados_ajax_guarda_maquila"></span>
					<button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
					<button type="button" class="btn btn-primary" onclick="guarda_maquila();" id="btnActionFormMaquila"><span id="btnTextMaquila"></span></button>
				</div>
				</form>
			</div>
		</div>
	</div>
	<script>
		$('#fecha_inicio').css('z-index', 1500);
		$('#fecha_entrega').css('z-index', 1500);
		$('#fecha_pago').css('z-index', 1500);
		$('#fecha_gasto').css('z-index', 1500);

		jQuery(function($) {
			$("#fecha_inicio").mask("99-99-9999");
			$("#fecha_entrega").mask("99-99-9999");
			$("#fecha_pago").mask("99-99-9999");
			$("#fecha_gasto").mask("99-99-9999");
		});

		$(function() {
			$("#fecha_inicio").datepicker({
				dateFormat: "dd-mm-yy",
				firstDay: 1,
				dayNamesMin: ["Do", "Lu", "Ma", "Mi", "Ju", "Vi", "Sa"],
				dayNamesShort: ["Dom", "Lun", "Mar", "Mie", "Jue", "Vie", "Sab"],
				monthNames: ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio",
					"Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"
				],
				monthNamesShort: ["Ene", "Feb", "Mar", "Abr", "May", "Jun",
					"Jul", "Ago", "Sep", "Oct", "Nov", "Dic"
				]
			});
			$("#fecha_entrega").datepicker({
				dateFormat: "dd-mm-yy",
				firstDay: 1,
				dayNamesMin: ["Do", "Lu", "Ma", "Mi", "Ju", "Vi", "Sa"],
				dayNamesShort: ["Dom", "Lun", "Mar", "Mie", "Jue", "Vie", "Sab"],
				monthNames: ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio",
					"Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"
				],
				monthNamesShort: ["Ene", "Feb", "Mar", "Abr", "May", "Jun",
					"Jul", "Ago", "Sep", "Oct", "Nov", "Dic"
				]
			});
			$("#fecha_pago").datepicker({
				dateFormat: "dd-mm-yy",
				firstDay: 1,
				dayNamesMin: ["Do", "Lu", "Ma", "Mi", "Ju", "Vi", "Sa"],
				dayNamesShort: ["Dom", "Lun", "Mar", "Mie", "Jue", "Vie", "Sab"],
				monthNames: ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio",
					"Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"
				],
				monthNamesShort: ["Ene", "Feb", "Mar", "Abr", "May", "Jun",
					"Jul", "Ago", "Sep", "Oct", "Nov", "Dic"
				]
			});
			$("#fecha_gasto").datepicker({
				dateFormat: "dd-mm-yy",
				firstDay: 1,
				dayNamesMin: ["Do", "Lu", "Ma", "Mi", "Ju", "Vi", "Sa"],
				dayNamesShort: ["Dom", "Lun", "Mar", "Mie", "Jue", "Vie", "Sab"],
				monthNames: ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio",
					"Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"
				],
				monthNamesShort: ["Ene", "Feb", "Mar", "Abr", "May", "Jun",
					"Jul", "Ago", "Sep", "Oct", "Nov", "Dic"
				]
			});
		});

		function guarda_maquila() {
			$('#btnActionFormMaquila').attr("disabled", true);
			var id_maquila = $("#id_maquila").val();
			var status = $("#status").val();
			var referencia = $("#referencia").val();
			var factura = $("#factura").val();
			var id_proveedor = $("#id_proveedor").val();
			var id_producto = $("#id_producto").val();
			var cantidad = $("#cantidad").val();
			var fecha_inicio = $("#fecha_inicio").val();
			var fecha_entrega = $("#fecha_entrega").val();
			var bultos = $("#bultos").val();
			var precio_costo = $("#precio_costo").val();
			var precio_venta = $("#precio_venta").val();

			$.ajax({
				type: "POST",
				url: "../ajax/maquila.php?action=guardar_maquila",
				data: "id_maquila=" + id_maquila + "&status=" + status +
					"&referencia=" + encodeURIComponent(referencia) + "&factura=" + encodeURIComponent(factura) +
					"&id_proveedor=" + id_proveedor + "&id_producto=" +
					id_producto + "&cantidad=" + cantidad + "&fecha_inicio=" +
					fecha_inicio + "&fecha_entrega=" + fecha_entrega + "&bultos=" + bultos 
					+ "&precio_costo=" + precio_costo + "&precio_venta=" + precio_venta,
				beforeSend: function(objeto) {
					$("#resultados_ajax_guarda_maquila").html("Guardando...");
				},
				success: function(datos) {
					$("#resultados_ajax_guarda_maquila").html(datos);
					$('#btnActionFormMaquila').attr("disabled", false);
				}
			});
			event.preventDefault();
		}

		function buscar_proveedor() {
			$("#proveedor").autocomplete({
				source: '../ajax/proveedores_autocompletar.php',
				minLength: 2,
				select: function(event, ui) {
					event.preventDefault();
					$('#id_proveedor').val(ui.item.id_proveedor);
					$('#proveedor').val(ui.item.razon_social);
					document.getElementById('producto').focus();
				}
			});

			$("#proveedor").on("keydown", function(event) {
				if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_proveedor").val("");
					$("#proveedor").val("");
				}
				if (event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_proveedor").val("");
					$("#proveedor").val("");
				}
			});
		}

		function buscar_producto() {
			$("#producto").autocomplete({
				appendTo: "#maquila",
				source: '../ajax/productos_autocompletar.php',
				minLength: 2,
				select: function(event, ui) {
					event.preventDefault();
					$('#id_producto').val(ui.item.id);
					$('#producto').val(ui.item.nombre);
					$('#precio_venta').val(ui.item.precio);
					document.getElementById('cantidad').focus();
				}
			});

			$("#producto").on("keydown", function(event) {
				if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_producto").val("");
					$("#producto").val("");
					$("#precio_venta").val("");
				}
			});

		}

		//agregar pagos
		function agrega_pago() {
			var fecha = $("#fecha_pago").val();
			var valor = $("#valor_pago").val();
			var detalle = $("#detalle_pago").val();
			if (isNaN(valor)) {
				alert('El dato ingresado en valor, no es un número');
				document.getElementById('valor_pago').focus();
				return false;
			}

			$.ajax({
				type: "POST",
				url: "../ajax/maquila.php?action=agrega_pago",
				data: "fecha_pago=" + fecha + "&valor_pago=" + valor + "&detalle_pago="+detalle,
				beforeSend: function(objeto) {
					$("#detalle_pagos").html("Cargando...");
				},
				success: function(datos) {
					$("#detalle_pagos").html(datos);
					$("#valor_pago").val("");
					$("#detalle_pago").val("");
					calculo_totales();
				}
			});
		}

				//agregar gastos
		function agrega_gasto() {
			var fecha = $("#fecha_gasto").val();
			var valor = $("#valor_gasto").val();
			var detalle = $("#detalle_gasto").val();
			if (isNaN(valor)) {
				alert('El dato ingresado en valor, no es un número');
				document.getElementById('valor_gasto').focus();
				return false;
			}

			$.ajax({
				type: "POST",
				url: "../ajax/maquila.php?action=agrega_gasto",
				data: "fecha_gasto=" + fecha + "&valor_gasto=" + valor + "&detalle_gasto="+detalle,
				beforeSend: function(objeto) {
					$("#detalle_gastos").html("Cargando...");
				},
				success: function(datos) {
					$("#detalle_gastos").html(datos);
					$("#valor_gasto").val("");
					$("#detalle_gasto").val("");
					calculo_totales();
				}
			});
		}

		//para una fila de pago
		function eliminar_pago(id) {
			if (confirm("Realmente desea eliminar?")) {
				$.ajax({
					type: "POST",
					url: "../ajax/maquila.php?action=eliminar_pago",
					data: "id=" + id,
					beforeSend: function(objeto) {
						$("#detalle_pagos").html("Eliminando...");
					},
					success: function(datos) {
						$("#detalle_pagos").html(datos);
						calculo_totales();
					}
				});
			}
		}

				//para una fila de gasto
		function eliminar_gasto(id) {
			if (confirm("Realmente desea eliminar?")) {
				$.ajax({
					type: "POST",
					url: "../ajax/maquila.php?action=eliminar_gasto",
					data: "id=" + id,
					beforeSend: function(objeto) {
						$("#detalle_gastos").html("Eliminando...");
					},
					success: function(datos) {
						$("#detalle_gastos").html(datos);
						calculo_totales();
					}
				});
			}
		}

		   //calcular los totales
		   function calculo_totales() {
				var cantidad = document.getElementById('cantidad').value;
				var precio_costo = document.getElementById('precio_costo').value;
				var precio_venta = document.getElementById('precio_venta').value;

				if ((cantidad < 0)) {
					alert('La cantidad, debe ser mayor a cero');
					document.getElementById('cantidad').value='';
					document.getElementById('cantidad').focus();
					return false;
				}
				if ((precio_costo < 0)) {
					alert('El costo, debe ser mayor a cero');
					document.getElementById('precio_costo').value='';
					document.getElementById('precio_costo').focus();
					return false;
				}
				if ((precio_venta < 0)) {
					alert('El precio venta, debe ser mayor a cero');
					document.getElementById('precio_venta').value='';
					document.getElementById('precio_venta').focus();
					return false;
				}

				var total_pagos =0;
				var total_gastos =0;
				let request = (window.XMLHttpRequest) ?
				new XMLHttpRequest() :
				new ActiveXObject('Microsoft.XMLHTTP');
					let ajaxUrl = '../ajax/maquila.php?action=consulta_pagos_gastos_actuales';
					request.open("GET", ajaxUrl, true);
					request.send();
					request.onreadystatechange = function() {
						if (request.readyState == 4 && request.status == 200) {
							let objData = JSON.parse(request.responseText);
							if (objData.status) {
								let objMaquila = objData.data;
								total_pagos = objMaquila.total_pagos;
								total_gastos = objMaquila.total_gastos;

								var utilidad_por_unidad = (parseFloat(precio_venta) - parseFloat(precio_costo));
								$("#utilidad_por_unidad").val(utilidad_por_unidad.toFixed(2));

								var total_venta = (parseFloat(cantidad) * parseFloat(precio_venta));
								$("#total_venta").val(total_venta.toFixed(2));

								var total_costos = (parseFloat(cantidad) * parseFloat(precio_costo));
								$("#total_costos").val(total_costos.toFixed(2));

								$("#total_gastos").val(total_gastos.toFixed(2));

								var utilidad_neta = (parseFloat(total_venta) - parseFloat(total_costos) - parseFloat(total_gastos));
								$("#utilidad_neta").val(utilidad_neta.toFixed(2));
								
								var por_pagar = (parseFloat(total_costos) - parseFloat(total_pagos));
								$("#por_pagar").val(por_pagar.toFixed(2));


							} else {
								$.notify(objData.msg, "error");
							}
						}
						return false;
					}


				

			}
	</script>