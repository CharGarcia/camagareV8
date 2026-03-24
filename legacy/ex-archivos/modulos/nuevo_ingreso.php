<html lang="es">

<head>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Nuevo ingreso</title>
</head>
<?php
session_start();
if (isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa']) && isset($_SESSION['ruc_empresa'])) {
	$id_usuario = $_SESSION['id_usuario'];
	$id_empresa = $_SESSION['id_empresa'];
	$ruc_empresa = $_SESSION['ruc_empresa'];
	include("../paginas/menu_de_empresas.php");
	include("../modal/clientes.php");
	include("../modal/detalle_ventas_ingreso.php");
	ini_set('date.timezone', 'America/Guayaquil');
	$con = conenta_login();

	unset($_SESSION['arrayFormaPagoIngreso']);
	$limpiar_ingresos = mysqli_query($con, "DELETE FROM ingresos_egresos_tmp WHERE id_usuario='" . $id_usuario . "'");
	$limpiar_ingresos = mysqli_query($con, "DELETE FROM ingresos_egresos_tmp WHERE id_usuario=0");
	$limpiar_pagos = mysqli_query($con, "DELETE FROM formas_pagos_tmp WHERE id_usuario='" . $id_usuario . "' and tipo_documento='INGRESO'");
	$update_ingresos_tmp = mysqli_query($con, "UPDATE saldo_porcobrar_porpagar SET ing_tmp = '0' WHERE id_usuario='" . $id_usuario . "'");
?>

	<body>
		<div class="container">
			<div class="panel panel-info">
				<div class="panel-heading">
					<div class="btn-group pull-right">
						<form class="form-group" id="guardar_ingreso" name="guardar_ingreso" method="POST">
							<span id="mensaje_guardar_ingreso"></span>
							<button type="submit" class="btn btn-info btn-md" id="guarda_datos_btn" title="Guardar ingreso"><span class='glyphicon glyphicon-floppy-disk'></span> Guardar</button>
					</div>
					<h4><i class='glyphicon glyphicon-pencil'></i> Nuevo ingreso</h4>
				</div>

				<!-- desde aqui el encabezado del ingreso -->
				<div class="panel-body">
					<div id="resultados_guardar_ingreso"></div>
					<input type="hidden" id="buscar_de" name="buscar_de">
					<input type="hidden" id="id_proveedor" name="id_proveedor">

					<div class="well well-sm">
						<div class="form-group row">
							<div class="col-sm-2">
								<div class="input-group">
									<span class="input-group-addon"><b>Fecha</b></span>
									<input type="text" class="form-control input-sm" id="fecha_ingreso" name="fecha_ingreso" value="<?php echo date("d-m-Y"); ?>">
								</div>
							</div>
							<div class="col-sm-7">
								<div class="input-group">
									<span class="input-group-addon"><b>Recibo de</b></span>
									<input type="hidden" name="id_cliente_ingreso" id="id_cliente_ingreso">
									<input type="text" class="form-control input-sm" name="cliente_ingreso" id="cliente_ingreso" onkeyup='buscar_clientes();' placeholder="Buscar" autocomplete="off">
									<span class="input-group-btn btn-md"><button class="btn btn-info btn-md" type="button" title="Nuevo cliente" data-toggle="modal" onclick="carga_modal();" data-target="#nuevoCliente"><span class="glyphicon glyphicon-plus"></span></button></span>
								</div>
							</div>
							<div class="col-sm-3">
								<div class="input-group">
									<span class="input-group-addon"><b>Total Ingreso</b></span>
									<input type="text" class="form-control input-sm" id="total_ingreso" name="total_ingreso" readonly style="text-align:right">
								</div>
							</div>
						</div>
						<div class="form-group row">
							<div class="col-sm-8">
								<div class="input-group">
									<span class="input-group-addon"><b>Observaciones</b></span>
									<input type="text" class="form-control input-sm" name="observacion_ingreso" id="observacion_ingreso">
								</div>
							</div>
							<div class="col-sm-4">
								<a class="btn btn-info btn-sm" onclick="load(1);" id="boton_buscar_ventas" title="Mostrar ventas con facturas" data-toggle="modal" data-target="#detalle_ventas_ingreso"><span class='glyphicon glyphicon-paperclip'></span> Facturas</a>
								<a class="btn btn-info btn-sm" onclick="load(1);" id="boton_buscar_ventas_recibo" title="Mostrar ventas con recibo" data-toggle="modal" data-target="#detalle_ventas_recibo"><span class='glyphicon glyphicon-paperclip'></span> Recibos</a>
							</div>
						</div>
						<input type="hidden" id="total_pagos_ingreso" name="total_pagos_ingreso">
					</div>
					<!-- hasta aqui el encabezado del ingreso -->
					</form>

					<div class="row">
						<div class="col-sm-7">
							<div class="form-group">
								<div class="panel panel-info">
									<div class="table-responsive">
										<table class="table table-bordered">
											<tr class="info">
												<th style="padding: 2px;" colspan="5">Otras opciones de ingreso</th>
											</tr>
											<tr class="info">
												<th style="padding: 2px;">Concepto</th>
												<th style="padding: 2px;">Valor</th>
												<th style="padding: 2px;">Detalle</th>
												<th style="padding: 2px;" class="text-center"><span class="glyphicon glyphicon-chevron-down"></span></th>
											</tr>
											<td class='col-sm-3' style="padding: 2px;">
												<select class="form-control" style="height: 30px" title="Seleccione tipo de ingreso" name="tipo_ingreso" id="tipo_ingreso">
													<?php
													$resultado = mysqli_query($con, "SELECT * FROM opciones_ingresos_egresos WHERE tipo_opcion ='1' and status='1' and ruc_empresa='" . $ruc_empresa . "'order by descripcion asc");
													?>
													<option value="">Seleccione</option>
													<?php
													while ($row = mysqli_fetch_assoc($resultado)) {
													?>
														<option value="<?php echo $row['id'] ?>"><?php echo strtoupper($row['descripcion']); ?> </option>
													<?php
													}
													?>
												</select>
											</td>

											<td class='col-sm-2' style="padding: 2px;">
												<div>
													<input type="number" class="form-control input-sm text-left" title="Ingrese valor" name="valor_ingreso" id="valor_ingreso" placeholder="Valor">
												</div>
											</td>
											<td class='col-sm-6' style="padding: 2px;">
												<div>
													<input type="text" class="form-control input-sm" title="Detalle de egreso" name="detalle_ingreso" id="detalle_ingreso" placeholder="Detalle">
												</div>
											</td>
											<td class="col-sm-1" style="text-align:center; padding: 2px;">
												<button type="button" class="btn btn-info btn-md" title="Agregar detalle" onclick="agregar_detalle_ingreso()"><span class="glyphicon glyphicon-plus"></span></button>
											</td>
										</table>
									</div>
								</div>
							</div>
						</div>
						<div class="col-md-5">
							<div class="form-group">
								<div class="panel panel-info">
									<div class="table-responsive">
										<table class="table table-bordered">
											<tr class="info">
												<th style="padding: 2px;" colspan="4">Formas de cobro</th>
											</tr>
											<tr class="info">
												<th style="padding: 2px;">Forma</th>
												<th style="padding: 2px;">Tipo</th>
												<th style="padding: 2px;">Valor</th>
												<th style="padding: 2px;" class="text-center"><span class="glyphicon glyphicon-chevron-down"></span></th>
											</tr>
											<td class="col-md-5" style="padding: 2px;">
												<select class="form-control" style="height: 30px" title="Seleccione forma de pago" name="forma_pago" id="forma_pago">
													<option value="0" selected>Seleccione</option>
													<?php
													$query_cobros_pagos = mysqli_query($con, "SELECT * FROM opciones_cobros_pagos WHERE ruc_empresa = '" . $ruc_empresa . "' and tipo_opcion='1' and status='1' order by descripcion asc");
													while ($row_cobros_pagos = mysqli_fetch_array($query_cobros_pagos)) {
														//el 1 junto al id en el value es para saber que los datos son de la lista de opciones de cobro
													?>
														<option value="<?php echo "1" . $row_cobros_pagos['id']; ?>"><?php echo strtoupper($row_cobros_pagos['descripcion']); ?></option>
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
													<input type="number" class="form-control input-sm text-left" title="Ingrese valor" name="valor_pago" id="valor_pago" placeholder="Valor">
												</div>
											</td>
											<td class="col-sm-1" style="text-align:center; padding: 2px;">
												<button type="button" class="btn btn-info btn-md" title="Agregar forma de pago" onclick="agregar_forma_pago()"><span class="glyphicon glyphicon-plus"></span></button>
											</td>
										</table>
									</div>
								</div>
							</div>
						</div>
					</div>
					<div id="muestra_detalle_ingreso"></div><!-- Carga los datos ajax del detalle del egreso -->
					<div class="outer_div_detalle_ingreso"></div><!-- Datos ajax Final -->
				</div>
			</div>
		</div>
	<?php
} else {
	header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/sistema') . '/empresa');
}
	?>
	<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
	<script src="../js/notify.js"></script>
	</body>

</html>
<script>
	jQuery(function($) {
		$("#fecha_ingreso").mask("99-99-9999");
	});

	$(document).ready(function() {
		load(1);
	});


	function carga_modal() {
		document.querySelector("#titleModalCliente").innerHTML = "<i class='glyphicon glyphicon-ok'></i> Nuevo Cliente";
		document.querySelector("#guardar_cliente").reset();
		document.querySelector("#id_cliente").value = "";
		document.querySelector("#btnActionFormCliente").classList.replace("btn-info", "btn-primary");
		document.querySelector("#btnTextCliente").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Guardar";
		document.querySelector('#btnActionFormCliente').title = "Guardar cliente";
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
	});

	function load(page) {
		var fv = $("#fv").val();
		var rv = $("#rv").val();
		$("#loader_facturas_por_cobrar").fadeIn('slow');
		$.ajax({
			url: '../ajax/detalle_ingresos.php?action=facturas_por_cobrar&page=' + page + '&fv=' + fv,
			beforeSend: function(objeto) {
				$('#loader_facturas_por_cobrar').html('<img src="../image/ajax-loader.gif">');
			},
			success: function(data) {
				$(".outer_div_facturas_por_cobrar").html(data).fadeIn('slow');
				$('#loader_facturas_por_cobrar').html('');
			}
		});

		$.ajax({
			url: '../ajax/detalle_ingresos.php?action=recibos_por_cobrar&page=' + page + '&rv=' + rv,
			beforeSend: function(objeto) {
				$('#loader_recibos_por_cobrar').html('<img src="../image/ajax-loader.gif">');
			},
			success: function(data) {
				$(".outer_div_recibos_por_cobrar").html(data).fadeIn('slow');
				$('#loader_recibos_por_cobrar').html('');
			}
		});
		event.preventDefault();
	}



	//para buscar los clientes
	function buscar_clientes() {
		$("#cliente_ingreso").autocomplete({
			source: '../ajax/clientes_autocompletar_ingresos.php',
			minLength: 2,
			select: function(event, ui) {
				event.preventDefault();
				$('#id_cliente_ingreso').val(ui.item.id);
				$('#cliente_ingreso').val(ui.item.nombre);
				document.getElementById('observacion_ingreso').focus();
			}
		});

		$("#cliente_ingreso").on("keydown", function(event) {
			if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
				$("#id_cliente_ingreso").val("");
				$("#cliente_ingreso").val("");
			}
			if (event.keyCode == $.ui.keyCode.DELETE) {
				$("#cliente_ingreso").val("");
				$("#id_cliente_ingreso").val("");
			}
		});
	}


	//agrega un item
	function agregar_detalle_ingreso() {
		var tipo_ingreso = $("#tipo_ingreso").val();
		var valor_ingreso = $("#valor_ingreso").val();
		var detalle_ingreso = $("#detalle_ingreso").val();
		var nombre_beneficiario = $("#cliente_ingreso").val();

		//Inicia validacion
		if (nombre_beneficiario == "") {
			alert('Ingrese nombre del cliente o de quien recibe el ingreso');
			document.getElementById('cliente_ingreso').focus();
			return false;
		}

		if (tipo_ingreso == '') {
			alert('Seleccione un tipo de ingreso');
			document.getElementById('tipo_ingreso').focus();
			return false;
		}

		if (valor_ingreso == '') {
			alert('Ingrese valor');
			document.getElementById('valor_ingreso').focus();
			return false;
		}

		if (isNaN(valor_ingreso)) {
			alert('El dato ingresado en valor, no es un número');
			document.getElementById('valor_ingreso').focus();
			return false;
		}

		if (detalle_ingreso == '') {
			alert('Ingrese detalle del ingreso');
			document.getElementById('detalle_ingreso').focus();
			return false;
		}

		//Fin validacion
		$("#loader_ingreso").fadeIn('fast');
		$.ajax({
			url: "../ajax/detalle_ingresos.php?action=agregar_detalle_ingreso&tipo_ingreso=" + tipo_ingreso + "&valor_ingreso=" + valor_ingreso + "&detalle_ingreso=" + detalle_ingreso + "&nombre_beneficiario=" + nombre_beneficiario,
			beforeSend: function(objeto) {
				$("#loader_ingreso").html("Agregando...");
			},
			success: function(data) {
				$(".outer_div_detalle_ingreso").html(data).fadeIn('fast');
				$('#loader_ingreso').html('');
				document.getElementById("tipo_ingreso").value = "0";
				document.getElementById("valor_ingreso").value = "";
				document.getElementById("detalle_ingreso").value = "";

				var total_ingreso = $("#suma_ingreso").val();
				$("#valor_pago").val(total_ingreso);
				$("#total_ingreso").val(total_ingreso);
				mostrar_asiento();
			}
		});
		event.preventDefault();
	}

	//agrega una forma de pago
	function agregar_forma_pago() {
		var forma_pago = $("#forma_pago").val();
		var valor_pago = $("#valor_pago").val();
		var tipo = $("#tipo").val();

		//Inicia validacion
		if (forma_pago == '0') {
			alert('Seleccione una forma de pago');
			document.getElementById('forma_pago').focus();
			return false;
		}

		//origen es para ver de que tabla me esta trayendo el dato, para segubn eso mostrar deposito o transferencia
		var origen = forma_pago.substring(0, 1);

		if (origen == 1 && tipo != '0') {
			document.getElementById("tipo").value = "0";
			document.getElementById('valor_pago').focus();
			return false;
		}

		if (origen == 2 && tipo == '0') {
			alert('Seleccione depósito o transferencia.');
			document.getElementById('tipo').focus();
			return false;
		}

		if (valor_pago == '') {
			alert('Ingrese valor');
			document.getElementById('valor_pago').focus();
			return false;
		}

		if (isNaN(valor_pago)) {
			alert('El dato ingresado en valor, no es un número');
			document.getElementById('valor_pago').focus();
			return false;
		}

		var forma_pago = forma_pago.substring(1, forma_pago.length);
		//Fin validacion
		$("#loader_ingreso").fadeIn('fast');
		$.ajax({
			url: "../ajax/detalle_ingresos.php?action=agregar_forma_pago_ingreso&forma_pago=" + forma_pago + "&valor_pago=" + valor_pago + "&tipo=" + tipo + "&origen=" + origen,
			beforeSend: function(objeto) {
				$("#loader_ingreso").html("Cargando detalle...");
			},
			success: function(data) {
				$(".outer_div_detalle_ingreso").html(data).fadeIn('fast');
				$('#loader_ingreso').html('');
				document.getElementById("forma_pago").value = "0";
				document.getElementById("tipo").value = "0";
				document.getElementById("valor_pago").value = "";
				mostrar_asiento();
			}
		});
		event.preventDefault();
	}


	function eliminar_item_ingreso(id) {
		$.ajax({
			url: "../ajax/detalle_ingresos.php?action=eliminar_item_ingreso&id_documento=" + id,
			beforeSend: function(objeto) {
				$("#loader_ingreso").html("Eliminando...");
			},
			success: function(data) {
				$(".outer_div_detalle_ingreso").html(data).fadeIn('fast');
				$('#loader_ingreso').html('');
				var total_ingreso = $("#suma_ingreso").val();
				$("#valor_pago").val(total_ingreso);
				$("#total_ingreso").val(total_ingreso);
				//generar_cuentas_por_cobrar();
				mostrar_asiento();
			}
		});
		event.preventDefault();
	}

	function eliminar_item_pago(id) {
		$.ajax({
			url: "../ajax/detalle_ingresos.php?action=eliminar_item_pago&id_registro=" + id,
			beforeSend: function(objeto) {
				$("#loader_ingreso").html("Eliminando item...");
			},
			success: function(data) {
				$(".outer_div_detalle_ingreso").html(data).fadeIn('fast');
				$('#loader_ingreso').html('');
				mostrar_asiento();
			}
		});
		event.preventDefault();
	}

	function copiar_valor(id) {
		var saldo = $("#saldo" + id).val();
		$("#valor_cobro" + id).val(saldo);
		var nombre_cliente = $("#nombre_cliente_seleccionado" + id).val();
		var id_cliente = $("#id_cliente_seleccionado" + id).val();
		var recibo_de = $("#cliente_ingreso").val();
		var total_ingreso = $("#total_ingreso").val();
		if (recibo_de == "" || total_ingreso == 0) {
			$("#cliente_ingreso").val(nombre_cliente);
			$("#id_cliente_ingreso").val(id_cliente);
		}
	}



	//para controlar el valor ingresado a cobrar no sea mayor al saldo
	function control_cobro(id) {
		var saldo = $("#saldo" + id).val();
		var valor_cobro = $("#valor_cobro" + id).val();

		if ((valor_cobro < 0)) {
			alert('Debe ser valor positivo');
			$("#valor_cobro" + id).val('');
			document.getElementById('valor_cobro' + id).focus();
			return false;
		}

		if (isNaN(valor_cobro)) {
			alert('El dato ingresado, no es un número');
			$("#valor_cobro" + id).val('');
			document.getElementById('valor_cobro' + id).focus();
			return false;
		}

		if (valor_cobro > parseFloat(saldo)) {
			alert('El valor es mayor al saldo existente.');
			$("#valor_cobro" + id).val('');
			document.getElementById('valor_cobro' + id).focus();
			return false;
		}
		$("#valor_cobro" + id).val(Number.parseFloat(valor_cobro).toFixed(2));

		var id_cliente = $("#id_cliente_seleccionado" + id).val();

		var nombre_cliente = $("#id_cliente_ingreso").val();
		if (nombre_cliente = '') {
			var id_cliente = $("#id_cliente_seleccionado" + id).val();
			$("#cliente_ingreso").val(nombre_cliente);
			$("#id_cliente_ingreso").val(id_cliente);
		}
	}


	//para guardar el ingreso
	$("#guardar_ingreso").submit(function(event) {
		$('guarda_datos').attr("disabled", true);
		var parametros = $(this).serialize();
		$.ajax({
			type: "POST",
			url: "../ajax/guardar_ingreso.php",
			data: parametros,
			beforeSend: function(objeto) {
				$("#mensaje_guardar_ingreso").html("Guardando...");
			},
			success: function(datos) {
				$("#resultados_guardar_ingreso").html(datos);
				$("#mensaje_guardar_ingreso").html('');
				$('guarda_datos').attr("disabled", false);
				load(1);
			}
		});
		event.preventDefault();
	});

	//para buscar las cuentas al hacer un asiento
	function buscar_cuentas() {
		$("#cuenta_diario").autocomplete({
			source: '../ajax/cuentas_autocompletar.php',
			minLength: 2,
			select: function(event, ui) {
				event.preventDefault();
				$('#id_cuenta').val(ui.item.id_cuenta);
				$('#cuenta_diario').val(ui.item.nombre_cuenta);
				$('#cod_cuenta').val(ui.item.codigo_cuenta);
				document.getElementById('debe_diario').focus();
			}
		});

		$("#cuenta_diario").autocomplete("widget").addClass("fixedHeight"); //para que aparezca la barra de desplazamiento en el buscar

		$("#cuenta_diario").on("keydown", function(event) {
			if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
				$("#id_cuenta").val("");
				$("#cuenta_diario").val("");
				$("#cod_cuenta").val("");
			}
			if (event.keyCode == $.ui.keyCode.DELETE) {
				$("#id_cuenta").val("");
				$("#cuenta_diario").val("");
				$("#cod_cuenta").val("");
			}
		});
	}


	//para agregar un iten de diario
	function agregar_item_diario() {
		var id_cuenta = $("#id_cuenta").val();
		var cod_cuenta = $("#cod_cuenta").val();
		var cuenta_diario = $("#cuenta_diario").val();
		var debe_diario = $("#debe_diario").val();
		var haber_cuenta = $("#haber_cuenta").val();
		var det_cuenta = $("#det_cuenta").val();
		//Inicia validacion

		if (id_cuenta == "") {
			alert('Agregue una cuenta contable.');
			document.getElementById('cuenta_diario').focus();
			return false;
		}
		if (isNaN(debe_diario)) {
			alert('El dato ingresado en el debe, no es un número');
			document.getElementById('debe_diario').focus();
			return false;
		}

		if (isNaN(haber_cuenta)) {
			alert('El dato ingresado en el haber, no es un número');
			document.getElementById('haber_cuenta').focus();
			return false;
		}

		if (debe_diario == "0" && haber_cuenta == "0") {
			alert('Ingrese valores en el debe o haber');
			document.getElementById('debe_diario').focus();
			return false;
		}

		if (debe_diario == "" && haber_cuenta == "") {
			alert('Ingrese valores en el debe o haber');
			document.getElementById('debe_diario').focus();
			return false;
		}

		if (debe_diario == "0" && haber_cuenta == "") {
			alert('Ingrese valores en el debe o haber');
			document.getElementById('debe_diario').focus();
			return false;
		}

		if (debe_diario == "" && haber_cuenta == "0") {
			alert('Ingrese valores en el debe o haber');
			document.getElementById('debe_diario').focus();
			return false;
		}


		if ((debe_diario) > 0 && (haber_cuenta) > 0) {
			alert('Corregir valores, no pueden tener valores el debe y el haber.');
			document.getElementById('haber_cuenta').focus();
			return false;
		}

		if (det_cuenta == "") {
			alert('Agregue un detalle.');
			document.getElementById('det_cuenta').focus();
			return false;
		}

		//Fin validacion
		$.ajax({
			type: "POST",
			url: "../ajax/agregar_item_diario_tmp.php",
			data: "action=agregar_item&id_cuenta=" + id_cuenta + "&cod_cuenta=" + cod_cuenta + "&cuenta_diario=" + cuenta_diario + "&debe_diario=" + debe_diario + "&haber_cuenta=" + haber_cuenta + "&det_cuenta=" + det_cuenta + "&detalle_diario=detalle_diario",
			beforeSend: function(objeto) {
				$("#mensaje_nuevo_asiento").html("Agregando...");
			},
			success: function(datos) {
				$(".outer_divdet").html(datos).fadeIn('fast');
				$('#muestra_detalle_diario').html('');
				$('#mensaje_nuevo_asiento').html('');
				$("#id_cuenta").val("");
				$("#cod_cuenta").val("");
				$("#cuenta_diario").val("");
				$("#debe_diario").val("");
				$("#haber_cuenta").val("");
				$("#det_cuenta").val("");
				pasa_concepto();
				document.getElementById('cuenta_diario').focus();
			}
		});

	}

	//muestra el asiento cada vez que agrego un pago o un nuevo documento al egreso
	function mostrar_asiento() {
		$.ajax({
			type: "GET",
			url: "../ajax/agregar_item_diario_tmp.php",
			data: "action=mostrar_asiento",
			beforeSend: function(objeto) {
				$("#mensaje_nuevo_asiento").html("Mostrando...");
			},
			success: function(datos) {
				$(".outer_divdet").html(datos).fadeIn('fast');
				$('#muestra_detalle_diario').html('');
				$('#mensaje_nuevo_asiento').html('');
			}
		});
	}

	function eliminar_item_diario(id) {
		$.ajax({
			type: "GET",
			url: "../ajax/agregar_item_diario_tmp.php",
			data: "action=eliminar_item&id_diario=" + id,
			beforeSend: function(objeto) {
				$("#mensaje_nuevo_asiento").html("Eliminando...");
			},
			success: function(datos) {
				$(".outer_divdet").html(datos).fadeIn('fast');
				$('#muestra_detalle_diario').html('');
				$('#mensaje_nuevo_asiento').html('');
				document.getElementById('cuenta_diario').focus();
				mostrar_asiento();
			}
		});
	}

	//para modificar el codigo de la cuenta
	function buscar_cuenta_modificar(id) {
		$("#modificar_codigo_cuenta" + id).autocomplete({
			source: '../ajax/cuentas_autocompletar.php',
			minLength: 2,
			select: function(event, ui) {
				event.preventDefault();
				$('#id_cuenta_modificar' + id).val(ui.item.id_cuenta);
				$('#modificar_cuenta' + id).val(ui.item.nombre_cuenta);
				$('#modificar_codigo_cuenta' + id).val(ui.item.codigo_cuenta);
				document.getElementById('modificar_debe' + id).focus();
			}
		});

		$("#modificar_codigo_cuenta" + id).autocomplete("widget").addClass("fixedHeight"); //para que aparezca la barra de desplazamiento en el buscar

		$("#modificar_codigo_cuenta" + id).on("keydown", function(event) {
			if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
				$("#id_cuenta_modificar" + id).val("");
				$("#modificar_cuenta" + id).val("");
				$("#modificar_codigo_cuenta" + id).val("");
			}
			if (event.keyCode == $.ui.keyCode.DELETE || event.keyCode == $.ui.keyCode.BACKSPACE) {
				$("#id_cuenta_modificar" + id).val("");
				$("#modificar_cuenta" + id).val("");
			}
		});
	}

	//cambiar cuenta 
	function actualizar_cuenta_modificar(id) {
		var codigo_actual = $("#codigo_actual" + id).val();
		var cuenta_actual = $("#cuenta_actual" + id).val();
		var id_cuenta_actual = $("#id_cuenta_modificar" + id).val();

		var id_cuenta_modificar = $("#id_cuenta_modificar" + id).val();
		var modificar_codigo_cuenta = $("#modificar_codigo_cuenta" + id).val();
		var modificar_cuenta = $("#modificar_cuenta" + id).val();

		if (modificar_codigo_cuenta == "") {
			alert('Ingrese cuenta contable');
			document.getElementById('modificar_codigo_cuenta' + id).focus();
			$("#id_cuenta_modificar" + id).val(id_cuenta_actual);
			$("#modificar_cuenta" + id).val(cuenta_actual);
			$("#modificar_codigo_cuenta" + id).val(codigo_actual);
			return false;
		}

		$.ajax({
			type: "POST",
			url: "../ajax/agregar_item_diario_tmp.php",
			data: "action=actualizar_cuentas_asiento&id_item=" + id + "&id_cuenta=" + id_cuenta_modificar + "&codigo_cuenta=" + modificar_codigo_cuenta + "&nombre_cuenta=" + modificar_cuenta,
			beforeSend: function(objeto) {
				$("#mensaje_nuevo_asiento").html("Actualizando...");
			},
			success: function(datos) {
				$(".outer_divdet").html(datos).fadeIn('fast');
				$('#mensaje_nuevo_asiento').html('');
			}
		});
	}

	//para modificar el detalle del asiento de cada item
	function modificar_detalle_directo(id) {
		var detalle_original = $("#detalle_original" + id).val();
		var detalle_asiento = $("#detalle_asiento" + id).val();

		if (detalle_asiento == "") {
			alert('Ingrese detalle del item, no puede quedar vacio');
			document.getElementById('detalle_asiento' + id).focus();
			$("#detalle_asiento" + id).val(detalle_original);
			return false;
		}

		$.ajax({
			type: "POST",
			url: "../ajax/agregar_item_diario_tmp.php",
			data: "action=actualizar_item_asiento&id_item=" + id + "&detalle_item=" + detalle_asiento,
			beforeSend: function(objeto) {
				$("#mensaje_nuevo_asiento").html("Actualizando...");
			},
			success: function(datos) {
				$(".outer_divdet").html(datos).fadeIn('fast');
				$('#mensaje_nuevo_asiento').html('');
			}
		});
	}

	function modificar_debe(id) {
		var modificar_debe = $("#modificar_debe" + id).val();
		var debe_actual = $("#debe_actual" + id).val();

		if (isNaN(modificar_debe)) {
			alert('El dato ingresado, no es un número');
			$("#modificar_debe" + id).val(debe_actual);
			document.getElementById('modificar_debe' + id).focus();
			return false;
		}

		if (modificar_debe < 0) {
			alert('Ingrese valor mayor a cero');
			$("#modificar_debe" + id).val(debe_actual);
			document.getElementById('modificar_debe' + id).focus();
			return false;
		}

		$.ajax({
			type: "POST",
			url: "../ajax/agregar_item_diario_tmp.php",
			data: "action=actualizar_debe&id_item=" + id + "&debe=" + modificar_debe,
			beforeSend: function(objeto) {
				$("#mensaje_nuevo_asiento").html("Actualizando...");
			},
			success: function(datos) {
				$(".outer_divdet").html(datos).fadeIn('fast');
				$('#mensaje_nuevo_asiento').html('');
			}
		});
	}

	function modificar_haber(id) {
		var modificar_haber = $("#modificar_haber" + id).val();
		var haber_actual = $("#haber_actual" + id).val();

		if (isNaN(modificar_haber)) {
			alert('El dato ingresado, no es un número');
			$("#modificar_haber" + id).val(haber_actual);
			document.getElementById('modificar_haber' + id).focus();
			return false;
		}

		if (modificar_haber < 0) {
			alert('Ingrese valor mayor a cero');
			$("#modificar_haber" + id).val(haber_actual);
			document.getElementById('modificar_haber' + id).focus();
			return false;
		}


		$.ajax({
			type: "POST",
			url: "../ajax/agregar_item_diario_tmp.php",
			data: "action=actualizar_haber&id_item=" + id + "&haber=" + modificar_haber,
			beforeSend: function(objeto) {
				$("#mensaje_nuevo_asiento").html("Actualizando...");
			},
			success: function(datos) {
				$(".outer_divdet").html(datos).fadeIn('fast');
				$('#mensaje_nuevo_asiento').html('');
			}
		});
	}

	//para verificar mes y año que sean del periodo actual y muestre una advertencia
	$(function() {
		$('#fecha_ingreso').change(function() {
			var fecha_input = $("#fecha_ingreso").val();
			let date = new Date();
			let fecha_hoy = String(date.getDate()).padStart(2, '0') + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + date.getFullYear();

			if (fecha_input.length = 10) {
				let dia_entra = fecha_input.substr(0, 2);
				let mes_entra = fecha_input.substr(3, 2);
				let anio_entra = fecha_input.substr(6, 4);

				let mes_hoy = fecha_hoy.substr(3, 2);
				let anio_hoy = fecha_hoy.substr(7, 4);

				// Crear objetos Date para comparación
				let fecha_input_obj = new Date(anio_entra, mes_entra - 1, dia_entra);
				let fecha_hoy_obj = new Date(date.getFullYear(), date.getMonth(), date.getDate());

				if (mes_entra != mes_hoy) {
					$("#fecha_ingreso").notify("El mes ingresado no es igual al mes actual", {
						position: "top center",
						className: "info" // Notificación informativa
					});
				}

				if (anio_entra != anio_hoy) {
					$("#fecha_ingreso").notify("El año ingresado no es igual al año actual", {
						position: "top center",
						className: "info" // Notificación informativa
					});
				}

				// Verificar que la fecha no sea mayor a la actual
				if (fecha_input_obj > fecha_hoy_obj) {
					$("#fecha_ingreso").val(fecha_hoy); // Asignar la fecha actual
					$("#fecha_ingreso").notify("La fecha ingresada es mayor a la fecha actual.", {
						position: "top center",
					});
				}
			}
			document.getElementById('cliente_ingreso').focus();
		});
	});
</script>