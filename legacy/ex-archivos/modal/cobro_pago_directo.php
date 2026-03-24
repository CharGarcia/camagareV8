<!-- aqui para abajo cobro recibo de venta-->
<div class="modal fade" data-backdrop="static" id="cobroReciboVenta" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
	<div class="modal-dialog modal-md" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="myModalLabel"><i class="glyphicon glyphicon-usd"></i> Cobro de recibo de venta <span id="loaderCobroReciboVenta"></span></h4>
			</div>
			<div class="modal-body">
				<form class="form-horizontal" method="POST" id="detalle_pago_recibo">
					<input type="hidden" id="id_ReciboVenta">
					<input type="hidden" id="porcobrar_ReciboVenta">
					<div style="padding: 2px; margin-bottom: 5px; margin-top: -10px;" id="datos_cobro_recibo" class="alert alert-info" role="alert"></div>
					<div class="form-group">
						<div class="col-md-12">
							<div class="panel panel-info">
								<div class="table-responsive">
									<table class="table table-bordered">
										<tr class="info">
											<th style="padding: 2px;">Forma cobro</th>
											<th style="padding: 2px;">Tipo</th>
											<th style="padding: 2px;">Valor</th>
											<th style="padding: 2px;" class="text-center"><span class="glyphicon glyphicon-chevron-down"></span></th>
										</tr>
										<td class="col-md-5" style="padding: 2px;">
											<select class="form-control" style="height: 30px" title="Seleccione forma de pago" name="forma_pago_recibo" id="forma_pago_recibo">
												<option value="0">Seleccione</option>
												<?php
												$con = conenta_login();
												$query_cobros_pagos = mysqli_query($con, "SELECT * FROM opciones_cobros_pagos WHERE ruc_empresa = '" . $ruc_empresa . "' and tipo_opcion='1' and status='1' order by descripcion asc");
												while ($row_cobros_pagos = mysqli_fetch_array($query_cobros_pagos)) {
													//el 1 junto al id en el value es para saber que los datos son de la lista de opciones de cobro
												?>
													<option value="<?php echo "1" . $row_cobros_pagos['id']; ?>" selected><?php echo ucwords($row_cobros_pagos['descripcion']); ?></option>
												<?php
												}

												$cuentas = mysqli_query($con, "SELECT cue_ban.id_cuenta as id_cuenta, concat(ban_ecu.nombre_banco,' ',cue_ban.numero_cuenta,' ', if(cue_ban.id_tipo_cuenta=1,'Aho','Cte')) as cuenta_bancaria FROM cuentas_bancarias as cue_ban INNER JOIN bancos_ecuador as ban_ecu ON cue_ban.id_banco=ban_ecu.id_bancos WHERE cue_ban.ruc_empresa ='" . $ruc_empresa . "' and cue_ban.status ='1'");
												while ($row = mysqli_fetch_array($cuentas)) {
													//el 2 junto al id en el value es para saber que los datos son desde bancos
												?>
													<option value="<?php echo "2" . $row['id_cuenta']; ?>"><?php echo ucwords($row['cuenta_bancaria']); ?></option>
												<?php
												}
												?>
											</select>
										</td>
										<td class="col-md-3" style="padding: 2px;">
											<select class="form-control" style="height: 30px" title="Seleccione" name="tipo_recibo" id="tipo_recibo">
												<option value="0">N/A</option>
												<option value="D">Depósito</option>
												<option value="T">Transferencia</option>
											</select>
										</td>

										<td class="col-sm-3" style="padding: 2px;">
											<div>
												<input type="number" class="form-control input-sm" style="text-align:right;" title="Ingrese valor" name="valor_pago_recibo" id="valor_pago_recibo" placeholder="Valor">
											</div>
										</td>
										<td class="col-sm-1" style="text-align:center; padding: 2px;">
											<button type="button" class="btn btn-info btn-sm" title="Agregar forma de pago" onclick="agregar_forma_pago()"><span class="glyphicon glyphicon-plus"></span></button>
										</td>
									</table>
								</div>
							</div>
						</div>
						<div class="outer_divCobroReciboVenta"></div><!-- Datos ajax Final -->
					</div>
					<div class="form-group">
						<div class="col-sm-6">
							<div class="input-group">
								<span class="input-group-addon"><b>Recibido</b></span>
								<input type="number" oninput="calculo_cambio_recibo();" class="form-control input-sm text-right" id="valor_recibido_recibo">
							</div>
						</div>
						<div class="col-sm-6">
							<div class="input-group">
								<span class="input-group-addon"><b>Cambio</b></span>
								<input type="text" class="form-control input-sm text-right" id="valor_cambio_recibo" readonly>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-4">
							<div class="input-group">
								<span class="input-group-addon"><b>Fecha</b></span>
								<input type="text" class="form-control input-sm" id="fecha_ingreso_recibo" name="fecha_ingreso_recibo" value="<?php echo date("d-m-Y"); ?>">
							</div>
						</div>
						<div class="col-sm-8">
							<div class="input-group">
								<span class="input-group-addon"><b>Nota</b></span>
								<input type="text" class="form-control input-sm text-left" id="nota_recibo">
							</div>
						</div>
					</div>
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Cerrar</button>
				<button type="button" class="btn btn-primary btn-sm" onclick="guarda_pago_recibo();" id="btnActionFormPagoRecibo"> Guardar</button>
			</div>
		</div>
	</div>
</div>

<!-- aqui para abajo cobro facturas de venta-->
<div class="modal fade" data-backdrop="static" id="cobroFacturaVenta" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
	<div class="modal-dialog modal-md" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="myModalLabel"><i class="glyphicon glyphicon-usd"></i> Cobro de factura de venta <span id="loaderCobroFacturaVenta"></span></h4>
			</div>
			<div class="modal-body">
				<form class="form-horizontal" method="POST" id="detalle_pago_factura">
					<input type="hidden" id="id_FacturaVenta">
					<input type="hidden" id="porcobrar_FacturaVenta">

					<div style="padding: 2px; margin-bottom: 5px; margin-top: -10px;" id="datos_cobro_factura" class="alert alert-info" role="alert"></div>
					<div class="form-group">
						<div class="col-md-12">
							<div class="panel panel-info">
								<div class="table-responsive">
									<table class="table table-bordered">
										<tr class="info">
											<th style="padding: 2px;">Forma cobro</th>
											<th style="padding: 2px;">Tipo</th>
											<th style="padding: 2px;">Valor</th>
											<th style="padding: 2px;" class="text-center"><span class="glyphicon glyphicon-chevron-down"></span></th>
										</tr>
										<td class="col-md-5" style="padding: 2px;">
											<select class="form-control" style="height: 30px" title="Seleccione forma de pago" name="forma_pago" id="forma_pago">
												<option value="0">Seleccione</option>
												<?php
												$con = conenta_login();
												$query_cobros_pagos = mysqli_query($con, "SELECT * FROM opciones_cobros_pagos WHERE ruc_empresa = '" . $ruc_empresa . "' and tipo_opcion='1' and status='1' order by descripcion asc");
												while ($row_cobros_pagos = mysqli_fetch_array($query_cobros_pagos)) {
													//el 1 junto al id en el value es para saber que los datos son de la lista de opciones de cobro
												?>
													<option value="<?php echo "1" . $row_cobros_pagos['id']; ?>" selected><?php echo ucwords($row_cobros_pagos['descripcion']); ?></option>
												<?php
												}

												$cuentas = mysqli_query($con, "SELECT cue_ban.id_cuenta as id_cuenta, concat(ban_ecu.nombre_banco,' ',cue_ban.numero_cuenta,' ', if(cue_ban.id_tipo_cuenta=1,'Aho','Cte')) as cuenta_bancaria FROM cuentas_bancarias as cue_ban INNER JOIN bancos_ecuador as ban_ecu ON cue_ban.id_banco=ban_ecu.id_bancos WHERE cue_ban.ruc_empresa ='" . $ruc_empresa . "' and cue_ban.status ='1'");
												while ($row = mysqli_fetch_array($cuentas)) {
													//el 2 junto al id en el value es para saber que los datos son desde bancos
												?>
													<option value="<?php echo "2" . $row['id_cuenta']; ?>"><?php echo ucwords($row['cuenta_bancaria']); ?></option>
												<?php
												}
												?>
											</select>
										</td>
										<td class="col-md-3" style="padding: 2px;">
											<select class="form-control" style="height: 30px" title="Seleccione" name="tipo" id="tipo">
												<option value="0">N/A</option>
												<option value="D">Depósito</option>
												<option value="T">Transferencia</option>
											</select>
										</td>

										<td class="col-sm-3" style="padding: 2px;">
											<div>
												<input type="number" class="form-control input-sm" style="text-align:right;" title="Ingrese valor" name="valor_pago" id="valor_pago" placeholder="Valor">
											</div>
										</td>
										<td class="col-sm-1" style="text-align:center; padding: 2px;">
											<button type="button" class="btn btn-info btn-sm" title="Agregar forma de pago" onclick="agregar_forma_pago()"><span class="glyphicon glyphicon-plus"></span></button>
										</td>
									</table>
								</div>
							</div>
						</div>
						<div class="outer_divCobroVenta"></div><!-- Datos ajax Final -->
					</div>
					<div class="form-group">
						<div class="col-sm-6">
							<div class="input-group">
								<span class="input-group-addon"><b>Recibido</b></span>
								<input type="number" oninput="calculo_cambio();" class="form-control input-sm text-right" id="valor_recibido">
							</div>
						</div>
						<div class="col-sm-6">
							<div class="input-group">
								<span class="input-group-addon"><b>Cambio</b></span>
								<input type="number" class="form-control input-sm text-right" id="valor_cambio" readonly>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-4">
							<div class="input-group">
								<span class="input-group-addon"><b>Fecha</b></span>
								<input type="text" class="form-control input-sm" id="fecha_ingreso" name="fecha_ingreso" value="<?php echo date("d-m-Y"); ?>">
							</div>
						</div>
						<div class="col-sm-8">
							<div class="input-group">
								<span class="input-group-addon"><b>Nota</b></span>
								<input type="text" class="form-control input-sm text-left" id="nota_venta">
							</div>
						</div>
					</div>
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Cerrar</button>
				<button type="button" class="btn btn-primary btn-sm" onclick="guarda_pago_factura();" id="btnActionFormPagoFactura"> Guardar</button>
			</div>
		</div>
	</div>
</div>

<!-- aqui para abajo pago facturas de compras-->
<div class="modal fade" data-backdrop="static" id="cobroFacturaCompra" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
	<div class="modal-dialog modal-md" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="myModalLabel"><i class="glyphicon glyphicon-usd"></i> Pago de compras y servicios <span id="loaderCobroFacturaCompra"></span></h4>
			</div>
			<div class="modal-body">
				<form class="form-horizontal" method="POST" id="detalle_pago_compra">
					<input type="hidden" id="id_FacturaCompra">
					<input type="hidden" id="porpagar_FacturaCompra">

					<div style="padding: 2px; margin-bottom: 5px; margin-top: -10px;" id="datos_pago_compra" class="alert alert-info" role="alert"></div>
					<div class="form-group">
						<div class="col-md-12">
							<div class="panel panel-info">
								<div class="table-responsive">
									<table class="table table-bordered">
										<tr class="info">
											<td style="padding: 2px;">Forma pago</th>
											<td style="padding: 2px;" class="text-center">Tipo</td>
											<td style="padding: 2px;" class="text-center">Valor</td>
											<td style="padding: 2px;" class="text-center">Cheque</td>
											<td style="padding: 2px;" class="text-center">Fecha cobro</td>
											<td style="padding: 2px;" class="text-center">Agregar</td>
										</tr>

										<tr>
											<td style="padding: 2px;" class="col-sm-4">
												<select style="height: 30px;" class="form-control" title="Seleccione forma de pago." id="forma_pago_egreso">
													<option value="0">Seleccione</option>
													<?php
													$query_cobros_pagos = mysqli_query($con, "SELECT * FROM opciones_cobros_pagos WHERE ruc_empresa = '" . $ruc_empresa . "' and tipo_opcion='2' and status='1' order by descripcion asc");
													while ($row_cobros_pagos = mysqli_fetch_array($query_cobros_pagos)) {
														//el 1 junto al id en el value es para saber que los datos son de la lista de opciones de cobro
													?>
														<option value="<?php echo "1" . $row_cobros_pagos['id']; ?>" selected><?php echo ucwords($row_cobros_pagos['descripcion']); ?></option>
													<?php
													}
													$cuentas = mysqli_query($con, "SELECT cue_ban.id_cuenta as id_cuenta, concat(ban_ecu.nombre_banco,' ',cue_ban.numero_cuenta,' ', if(cue_ban.id_tipo_cuenta=1,'Aho','Cte')) as cuenta_bancaria FROM cuentas_bancarias as cue_ban INNER JOIN bancos_ecuador as ban_ecu ON cue_ban.id_banco=ban_ecu.id_bancos WHERE cue_ban.ruc_empresa ='" . $ruc_empresa . "' and cue_ban.status ='1'");
													while ($row = mysqli_fetch_array($cuentas)) {
														//el 2 junto al id en el value es para saber que los datos son desde bancos
													?>
														<option value="<?php echo "2" . $row['id_cuenta']; ?>"><?php echo ucwords($row['cuenta_bancaria']); ?></option>
													<?php
													}
													?>
												</select>
											</td>
											<td style="padding: 2px;" class="col-sm-2">
												<select class="form-control" style="height: 30px" title="Seleccione" name="tipo_egreso" id="tipo_egreso">
													<option value="0">N/A</option>
													<option value="C">Cheque</option>
													<option value="D">Débito</option>
													<option value="T">Transferencia</option>
												</select>
											</td>
											<td style="padding: 2px;" class="col-sm-2">
												<input type="number" class="form-control input-sm" style="text-align:right;" title="Ingrese valor" id="valor_pago_egreso" placeholder="Valor">
												</select>
											</td>
											<td style="padding: 2px;" class="col-sm-2">
												<input type="number" class="form-control input-sm" pattern="[0-9]{3}-[0-9]{3}-[0-9]{9}" style="text-align:right;" title="Ingrese número de cheque" id="numero_cheque_egreso" placeholder="Ch">
											</td>
											<td style="padding: 2px;" class="col-sm-2">
												<div class="pull-right">
													<input type="text" class="form-control input-sm" id="fecha_cobro_egreso" value="<?php echo date("d-m-Y"); ?>">
												</div>
											</td>
											<td style="padding: 2px;" class="col-sm-1">
												<div class="text-center">
													<button type="button" class="btn btn-info btn-sm" title="Agregar forma de pago" onclick="agrega_pagos_egreso();"><span class="glyphicon glyphicon-plus"></span></button>
												</div>
											</td>
										</tr>
									</table>
								</div>
							</div>
						</div>
						<div class="outer_divPagoCompra"></div><!-- Datos ajax Final -->
					</div>
					<div class="form-group">
						<div class="col-sm-4">
							<div class="input-group">
								<span class="input-group-addon"><b>Fecha</b></span>
								<input type="text" class="form-control input-sm" id="fecha_egreso" value="<?php echo date("d-m-Y"); ?>">
							</div>
						</div>
						<div class="col-sm-8">
							<div class="input-group">
								<span class="input-group-addon"><b>Nota</b></span>
								<input type="text" class="form-control input-sm text-left" id="nota_compra">
							</div>
						</div>
					</div>
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Cerrar</button>
				<button type="button" class="btn btn-primary btn-sm" onclick="guarda_pago_compra();" id="btnActionFormPagoCompra"> Guardar</button>
			</div>
		</div>
	</div>
</div>
<script type="text/javascript" src="../js/style_bootstrap.js"> </script>
<link rel="stylesheet" href="../css/jquery-ui.css">
<script src="../js/jquery-ui.js"></script>
<script src="../js/notify.js"></script>
<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
<script>
	$('#fecha_ingreso').css('z-index', 1500);
	$('#fecha_ingreso_recibo').css('z-index', 1500);
	$('#fecha_egreso').css('z-index', 1500);

	jQuery(function($) {
		$("#fecha_egreso").mask("99-99-9999");
		$("#fecha_ingreso").mask("99-99-9999");
		$("#fecha_ingreso_recibo").mask("99-99-9999");
		$("#fecha_cobro_egreso").mask("99-99-9999");
	});

	//para facturas
	function calculo_cambio() {
		var porcobrar_FacturaVenta = document.getElementById("porcobrar_FacturaVenta").value;
		var valor_recibido = document.getElementById("valor_recibido").value;
		if (isNaN(valor_recibido)) {
			alert('El valor ingresado, no es un número');
			$("#valor_recibido").val('0');
			document.getElementById('valor_recibido').focus();
			return false;
		}
		var valor_cambio = (valor_recibido - porcobrar_FacturaVenta).toFixed(2);
		$("#valor_cambio").val(valor_cambio);

	}

	//para recibos
	function calculo_cambio_recibo() {
		var porcobrar_ReciboVenta = document.getElementById("porcobrar_ReciboVenta").value;
		var valor_recibido_recibo = document.getElementById("valor_recibido_recibo").value;
		if (isNaN(valor_recibido_recibo)) {
			alert('El valor ingresado, no es un número');
			$("#valor_recibido_recibo").val('0');
			document.getElementById('valor_recibido_recibo').focus();
			return false;
		}
		var valor_cambio_recibo = (valor_recibido_recibo - porcobrar_ReciboVenta).toFixed(2);
		$("#valor_cambio_recibo").val(valor_cambio_recibo);

	}

	$(function() {
		$("#fecha_ingreso").datepicker({
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

		$("#fecha_ingreso_recibo").datepicker({
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

		$("#fecha_egreso").datepicker({
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

		$("#fecha_cobro_egreso").datepicker({
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



	$(function() {
		//cuando cambia el forma de pago
		$('#forma_pago_egreso').change(function() {
			var forma_pago = $("#forma_pago_egreso").val();
			var tipo = $("#tipo_egreso").val();
			$("#tipo_egreso").val("0");
			cambio_opciones(forma_pago, tipo);
		});

		//para cuando cambia el tipo
		$('#tipo_egreso').change(function() {
			var forma_pago = $("#forma_pago_egreso").val();
			var tipo = $("#tipo_egreso").val();
			cambio_opciones(forma_pago, tipo);
		});
	});

	//para pasar el valor de detalles de documentos al total de egreso en el modal de formas de pagos
	$(function() {
		//document.getElementById("cuenta_bancaria_egreso").style.visibility = "hidden";
		document.getElementById("numero_cheque_egreso").style.visibility = "hidden";
		document.getElementById("fecha_cobro_egreso").style.visibility = "hidden";
		var total_egreso = $("#suma_egreso").val();
		$("#valor_pago_egreso").val(total_egreso);
	});


	function cambio_opciones(forma_pago, tipo) {

		var origen = forma_pago.substring(0, 1);
		if (origen == "1") {
			$("#tipo_egreso").val("0");
			$("#numero_cheque_egreso").val("");
			document.getElementById("numero_cheque_egreso").style.visibility = "hidden";
			document.getElementById("fecha_cobro_egreso").style.visibility = "hidden";
			document.getElementById('valor_pago_egreso').focus();
		}

		if (origen == "2" && tipo == "C") {
			$("#numero_cheque_egreso").val("");
			document.getElementById("numero_cheque_egreso").style.visibility = "";
			document.getElementById("fecha_cobro_egreso").style.visibility = "";

			var id_cuenta = forma_pago.substring(1, forma_pago.length);
			$.post('../ajax/buscar_tipo_cuenta_bancaria.php', {
				id_cuenta: id_cuenta
			}).done(function(respuesta) {
				$.each(respuesta, function(i, item) {
					var tipo_cuenta_bancaria = item.tipo_cuenta;
					var ultimo_cheque = item.ultimo_cheque;

					if (tipo_cuenta_bancaria == "2") {
						document.getElementById("numero_cheque_egreso").style.visibility = "";
						document.getElementById("fecha_cobro_egreso").style.visibility = "";
						$("#numero_cheque_egreso").val(ultimo_cheque);
						document.getElementById('numero_cheque_egreso').focus();
					}

					if (tipo_cuenta_bancaria == "1") {
						document.getElementById("numero_cheque_egreso").style.visibility = "hidden";
						document.getElementById("fecha_cobro_egreso").style.visibility = "hidden";
					}
				});
			});
		}

		if (origen == "2" && tipo != "C") {
			document.getElementById("numero_cheque_egreso").style.visibility = "hidden";
			document.getElementById("fecha_cobro_egreso").style.visibility = "hidden";
		}

	};

	$(function() {
		$('#fecha_egreso').change(function() {
			var fecha_input = $("#fecha_egreso").val();
			let date = new Date(); // Fecha actual
			let fecha_hoy = String(date.getDate()).padStart(2, '0') + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + date.getFullYear();

			if (fecha_input.length === 10) {
				let dia_entra = fecha_input.substr(0, 2);
				let mes_entra = fecha_input.substr(3, 2);
				let anio_entra = fecha_input.substr(6, 4);

				let mes_hoy = fecha_hoy.substr(3, 2);
				let anio_hoy = fecha_hoy.substr(6, 4);

				// Crear objetos Date para comparación
				let fecha_input_obj = new Date(anio_entra, mes_entra - 1, dia_entra);
				let fecha_hoy_obj = new Date(date.getFullYear(), date.getMonth(), date.getDate());

				// Verificar que la fecha no sea mayor a la actual
				if (fecha_input_obj > fecha_hoy_obj) {
					$("#fecha_egreso").val(fecha_hoy); // Asignar la fecha actual
					$("#fecha_egreso").notify("La fecha ingresada es mayor a la fecha actual.", {
						position: "top center",
					});
				}
			}
		});
	});

	$(function() {
		$('#fecha_ingreso').change(function() {
			var fecha_input = $("#fecha_ingreso").val();
			let date = new Date(); // Fecha actual
			let fecha_hoy = String(date.getDate()).padStart(2, '0') + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + date.getFullYear();

			if (fecha_input.length === 10) {
				let dia_entra = fecha_input.substr(0, 2);
				let mes_entra = fecha_input.substr(3, 2);
				let anio_entra = fecha_input.substr(6, 4);

				let mes_hoy = fecha_hoy.substr(3, 2);
				let anio_hoy = fecha_hoy.substr(6, 4);

				// Crear objetos Date para comparación
				let fecha_input_obj = new Date(anio_entra, mes_entra - 1, dia_entra);
				let fecha_hoy_obj = new Date(date.getFullYear(), date.getMonth(), date.getDate());

				// Verificar que la fecha no sea mayor a la actual
				if (fecha_input_obj > fecha_hoy_obj) {
					$("#fecha_ingreso").val(fecha_hoy); // Asignar la fecha actual
					$("#fecha_ingreso").notify("La fecha ingresada es mayor a la fecha actual.", {
						position: "botton center",
					});
				}
			}
		});
	});

	$(function() {
		$('#fecha_ingreso_recibo').change(function() {
			var fecha_input = $("#fecha_ingreso_recibo").val();
			let date = new Date(); // Fecha actual
			let fecha_hoy = String(date.getDate()).padStart(2, '0') + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + date.getFullYear();

			if (fecha_input.length === 10) {
				let dia_entra = fecha_input.substr(0, 2);
				let mes_entra = fecha_input.substr(3, 2);
				let anio_entra = fecha_input.substr(6, 4);

				let mes_hoy = fecha_hoy.substr(3, 2);
				let anio_hoy = fecha_hoy.substr(6, 4);

				// Crear objetos Date para comparación
				let fecha_input_obj = new Date(anio_entra, mes_entra - 1, dia_entra);
				let fecha_hoy_obj = new Date(date.getFullYear(), date.getMonth(), date.getDate());

				// Verificar que la fecha no sea mayor a la actual
				if (fecha_input_obj > fecha_hoy_obj) {
					$("#fecha_ingreso_recibo").val(fecha_hoy); // Asignar la fecha actual
					$("#fecha_ingreso_recibo").notify("La fecha ingresada es mayor a la fecha actual.", {
						position: "botton center",
					});
				}
			}
		});
	});
</script>