<html lang="es">

<head>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Nuevo Egreso</title>
</head>

<?php
session_start();
if (isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa']) && isset($_SESSION['ruc_empresa'])) {
	$id_usuario = $_SESSION['id_usuario'];
	$id_empresa = $_SESSION['id_empresa'];
	$ruc_empresa = $_SESSION['ruc_empresa'];
	include("../paginas/menu_de_empresas.php");
	include("../modal/opciones_egreso.php");
	ini_set('date.timezone', 'America/Guayaquil');
	$con = conenta_login();
	$id_tmp = $_SESSION['id_usuario'];
	unset($_SESSION['arrayFormaPagoEgreso']);
	$delete_compras_tmp = mysqli_query($con, "DELETE FROM saldos_compras_tmp WHERE mid(ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "'");
	$delete_ingreso_tmp_cero = mysqli_query($con, "DELETE FROM ingresos_egresos_tmp WHERE id_usuario = '0'");
	$delete_ingreso_tmp = mysqli_query($con, "DELETE FROM ingresos_egresos_tmp WHERE id_usuario = '" . $id_tmp . "'");
	$delete_diario_tmp = mysqli_query($con, "DELETE FROM detalle_diario_tmp WHERE id_usuario='" . $id_tmp . "' and ruc_empresa = '" . $ruc_empresa . "' ");
	//}
?>

	<body>
		<?php
		include("../modal/nuevo_proveedor_retencion.php");
		?>
		<div class="container">
			<div class="panel panel-info">
				<div class="panel-heading">
					<div class="btn-group pull-right">
						<form class="form-group" id="guardar_egreso" name="guardar_egreso" method="POST">
							<span id="mensaje_al_guardar_egreso"></span>
							<button id="guardar_datos_egreso" type="submit" class="btn btn-info btn-md" title="Guardar egreso"><span class='glyphicon glyphicon-floppy-disk'></span> Guardar</button>
					</div>
					<h4><i class='glyphicon glyphicon-pencil'></i> Nuevo egreso</h4>
				</div>

				<!-- desde aqui el encabezado del egreso -->
				<div class="panel-body">
					<div id="resultados_guardar_egreso"></div>
					<input type="hidden" id="buscar_de" name="buscar_de">
					<input type="hidden" id="id_proveedor" name="id_proveedor">

					<div class="well well-sm">
						<div class="form-group row">
							<div class="col-sm-2">
								<div class="input-group">
									<span class="input-group-addon"><b>Fecha</b></span>
									<input type="text" class="form-control input-sm" id="fecha_egreso" name="fecha_egreso" value="<?php echo date("d-m-Y"); ?>">
								</div>
							</div>
							<div class="col-sm-7">
								<div class="input-group">
									<span class="input-group-addon"><b>Beneficiario</b></span>
									<input type="text" class="form-control input-sm" id="nombre_beneficiario" name="nombre_beneficiario" onkeyup='buscar_beneficiarios();' autocomplete="off" placeholder="Buscar beneficiario" required>
									<span class="input-group-btn btn-md"><button class="btn btn-info btn-md" data-toggle="modal" data-target="#nuevoProveedorRetencion" type="button" title="Agregar proveedor"><span class="glyphicon glyphicon-plus"></span></button></span>
								</div>
							</div>
							<div class="col-sm-3">
								<div class="input-group">
									<span class="input-group-addon"><b>Total Egreso</b></span>
									<input type="text" class="form-control input-sm" id="total_egreso" name="total_egreso" readonly style="text-align:right">
								</div>
							</div>
						</div>
						<div class="form-group row">
							<div class="col-sm-9">
								<div class="input-group">
									<span class="input-group-addon"><b>Observaciones</b></span>
									<input type="text" class="form-control input-sm" name="detalle_adicional" id="detalle_adicional">
								</div>
							</div>
							<div class="col-sm-3">

								<div class="btn-group">
									<button type="button" class="btn btn-info btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><span class='glyphicon glyphicon-list-alt'></span> Nómina <span class="caret"></span></button>
									<ul class="dropdown-menu">
										<li><a class="btn btn-defalt btn-sm" id="carga_nomina" title="Buscar Roles de pago" onclick="mostrar_nomina_por_pagar(1);" data-toggle="modal" data-target="#nomina"><span class='glyphicon glyphicon-paperclip'></span> Roles de pago</a></li>
										<li><a class="btn btn-defalt btn-sm" id="carga_quincena" title="Buscar Quincenas" onclick="mostrar_quincena_por_pagar(1);" data-toggle="modal" data-target="#quincena"><span class='glyphicon glyphicon-paperclip'></span> Quincenas</a></li>
										<li><a class="btn btn-defalt btn-sm" id="carga_anticipos" title="Buscar Anticípos" onclick="mostrar_anticipos(1);" data-toggle="modal" data-target="#anticipo_sueldo"><span class='glyphicon glyphicon-paperclip'></span> Anticípos</a></li>
										<li><a class="btn btn-defalt btn-sm" id="carga_prestamos" title="Buscar Préstamos de empresa" onclick="mostrar_prestamos(1);" data-toggle="modal" data-target="#prestamos_empresa"><span class='glyphicon glyphicon-paperclip'></span> Préstamos</a></li>
										<li><a class="btn btn-defalt btn-sm" id="carga_decimocuarto" title="Buscar décimo cuarto" onclick="mostrar_decimocuarto(1);" data-toggle="modal" data-target="#decimocuarto"><span class='glyphicon glyphicon-paperclip'></span> Décimo cuarto</a></li>
									</ul>
								</div>
								<a class="btn btn-info btn-sm" id="carga_prov" onclick="mostrar_cxp(1);" title="Buscar Proveedores" data-toggle="modal" data-target="#proveedores"><span class='glyphicon glyphicon-paperclip'></span> Compras</a>
							</div>
						</div>
						<input type="hidden" id="total_pagos_egreso" name="total_pagos_egreso">
					</div>
					</form>

					<div class="row">
						<div class="col-sm-7">
							<div class="form-group">
								<div class="panel panel-info">
									<div class="table-responsive">
										<table class="table table-bordered">
											<tr class="info">
												<th style="padding: 2px;" colspan="5">Otras opciones de egreso</th>
											</tr>
											<tr class="info">
												<th style="padding: 2px;">Concepto</th>
												<th style="padding: 2px;">Valor</th>
												<th style="padding: 2px;">Detalle</th>
												<th style="padding: 2px;" class="text-center"><span class="glyphicon glyphicon-chevron-down"></span></th>
											</tr>
											<td class='col-sm-3' style="padding: 2px;">
												<select class="form-control" style="height: 30px" title="Seleccione tipo de egreso" name="tipo_egreso" id="tipo_egreso">
													<?php
													$resultado = mysqli_query($con, "SELECT * FROM opciones_ingresos_egresos WHERE tipo_opcion ='2' and status='1' and ruc_empresa='" . $ruc_empresa . "'order by descripcion asc");
													?>
													<option value="0">Seleccione</option>
													<?php
													while ($row = mysqli_fetch_assoc($resultado)) {
													?>
														<option value="<?php echo $row['id'] ?>"><?php echo strtoupper($row['descripcion']) ?> </option>
													<?php
													}
													?>
												</select>
											</td>

											<td class='col-sm-2' style="padding: 2px;">
												<div>
													<input type="number" class="form-control input-sm text-left" title="Ingrese valor" name="valor_egreso" id="valor_egreso" placeholder="Valor">
												</div>
											</td>
											<td class='col-sm-6' style="padding: 2px;">
												<div>
													<input type="text" class="form-control input-sm" title="Detalle de egreso" name="detalle_egreso" id="detalle_egreso" placeholder="Detalle">
												</div>
											</td>
											<td class="col-sm-1" style="text-align:center; padding: 2px;">
												<button type="button" class="btn btn-info btn-md" title="Agregar detalle" onclick="agregar_detalle_egreso()"><span class="glyphicon glyphicon-plus"></span></button>
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
												<th style="padding: 2px;" colspan="4">
													<span class="pull-center"><button class="list-group-item list-group-item-info " style="text-align: center;" onclick="muestra_formas_de_pago_egreso();" title="Agregar formas de pagos" data-toggle="modal" data-target="#formasPagosEgreso"><span class="glyphicon glyphicon-usd"></span> Agregar formas de pago <span class="glyphicon glyphicon-usd"></span></button></span>
												</th>
											</tr>
										</table>
									</div>
								</div>
							</div>
						</div>
					</div>
					<div id="detalle_de_egresos"></div><!-- Carga los datos ajax del detalle del egreso -->
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
		$("#fecha_egreso").mask("99-99-9999");
	});

	$(document).ready(function() {
		load(1);
		muestra_por_pagar_proveedores();
	});


	function mostrar_cxp(page) {
		document.querySelector("#opciones_egreso_CxP_Proveedores").reset();
		load(page);
	}


	function mostrar_nomina_por_pagar(page) {
		document.querySelector("#opciones_egreso_nomina").reset();
		load(page);
	}

	function mostrar_quincena_por_pagar(page) {
		document.querySelector("#opciones_egreso_quincena").reset();
		load(page);
	}

	function mostrar_anticipos(page) {
		document.querySelector("#opciones_egreso_anticipo_sueldo").reset();
		load(page);
	}

	function mostrar_prestamos(page) {
		document.querySelector("#opciones_egreso_prestamos_empresa").reset();
		load(page);
	}

	function mostrar_decimocuarto(page) {
		document.querySelector("#opciones_egreso_decimo_cuarto").reset();
		load(page);
	}


	function buscar_beneficiarios() {
		$("#nombre_beneficiario").autocomplete({
			source: '../ajax/proveedores_autocompletar.php',
			minLength: 2,
			select: function(event, ui) {
				event.preventDefault();
				$('#id_proveedor').val(ui.item.id_proveedor);
				$('#nombre_beneficiario').val(ui.item.razon_social);
			}
		});

		$("#nombre_beneficiario").on("keydown", function(event) {
			if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
				$("#id_proveedor").val("");
				$("#nombre_beneficiario").val("");
			}
			if (event.keyCode == $.ui.keyCode.DELETE) {
				$("#id_proveedor").val("");
				$("#nombre_beneficiario").val("");
			}
		});
	}

	$(function() {
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
	});

	function load(page) {
		var por_buscar = $("#dato_a_buscar").val();
		var por_buscar_nomina = $("#dato_a_buscar_nomina").val();
		var por_buscar_quincena = $("#dato_a_buscar_quincena").val();
		var dato_a_buscar_anticipo_sueldo = $("#dato_a_buscar_anticipo_sueldo").val();
		var dato_a_buscar_prestamo_empresa = $("#dato_a_buscar_prestamo_empresa").val();
		var dato_a_buscar_decimo_cuarto = $("#dato_a_buscar_decimo_cuarto").val();

		$("#loader_cxp_proveedores").fadeIn('slow');
		$.ajax({
			url: '../clases/egresos.php?action=buscar_por_pagar&page=' + page + '&por_buscar=' + por_buscar,
			beforeSend: function(objeto) {
				$('#loader_cxp_proveedores').html('<img src="../image/ajax-loader.gif"> Cargando...');
			},
			success: function(data) {
				$(".outer_div_cxp_proveedores").html(data).fadeIn('slow');
				$('#loader_cxp_proveedores').html('');
				event.preventDefault();
			}
		});


		$("#loader_nomina").fadeIn('slow');
		$('#carga_nomina').attr("disabled", true);
		$.ajax({
			url: '../clases/egresos.php?action=buscar_nomina_por_pagar&page=' + page + '&por_buscar_nomina=' + por_buscar_nomina,
			beforeSend: function(objeto) {
				$('#loader_nomina').html('<img src="../image/ajax-loader.gif"> Buscando...');
			},
			success: function(datos) {
				$(".outer_div_nomina").html(datos).fadeIn('slow');
				$('#loader_nomina').html('');
				$('#carga_nomina').attr("disabled", false);
				event.preventDefault();
			}
		})

		$("#loader_quincena").fadeIn('slow');
		$('#carga_quincena').attr("disabled", true);
		$.ajax({
			url: '../clases/egresos.php?action=buscar_quincena_por_pagar&page=' + page + '&por_buscar_quincena=' + por_buscar_quincena,
			beforeSend: function(objeto) {
				$('#loader_quincena').html('<img src="../image/ajax-loader.gif"> Buscando...');
			},
			success: function(datos) {
				$(".outer_div_quincena").html(datos).fadeIn('slow');
				$('#loader_quincena').html('');
				$('#carga_quincena').attr("disabled", false);
				event.preventDefault();
			}
		})


		$("#loader_anticipo_sueldo").fadeIn('slow');
		$('#carga_anticipos').attr("disabled", true);
		$.ajax({
			url: '../clases/egresos.php?action=buscar_anticipos_sueldos&page=' + page + '&dato_a_buscar_anticipo_sueldo=' + dato_a_buscar_anticipo_sueldo,
			beforeSend: function(objeto) {
				$('#loader_anticipo_sueldo').html('<img src="../image/ajax-loader.gif"> Buscando...');
			},
			success: function(datos) {
				$(".outer_div_anticipo_sueldo").html(datos).fadeIn('slow');
				$('#loader_anticipo_sueldo').html('');
				$('#carga_anticipos').attr("disabled", false);
				event.preventDefault();
			}
		})


		$("#loader_prestamo_empresa").fadeIn('slow');
		$('#carga_prestamos').attr("disabled", true);
		$.ajax({
			url: '../clases/egresos.php?action=buscar_prestamos_empresa&page=' + page + '&dato_a_buscar_prestamo_empresa=' + dato_a_buscar_prestamo_empresa,
			beforeSend: function(objeto) {
				$('#loader_prestamo_empresa').html('<img src="../image/ajax-loader.gif"> Buscando...');
			},
			success: function(datos) {
				$(".outer_div_prestamo_empresa").html(datos).fadeIn('slow');
				$('#loader_prestamo_empresa').html('');
				$('#carga_prestamos').attr("disabled", false);
				event.preventDefault();
			}
		})

		$("#loader_decimo_cuarto").fadeIn('slow');
		$('#carga_decimocuarto').attr("disabled", true);
		$.ajax({
			url: '../clases/egresos.php?action=buscar_decimo_cuarto&page=' + page + '&dato_a_buscar_decimo_cuarto=' + dato_a_buscar_decimo_cuarto,
			beforeSend: function(objeto) {
				$('#loader_decimo_cuarto').html('<img src="../image/ajax-loader.gif"> Buscando...');
			},
			success: function(datos) {
				$(".outer_div_decimo_cuarto").html(datos).fadeIn('slow');
				$('#loader_decimo_cuarto').html('');
				$('#carga_decimocuarto').attr("disabled", false);
				event.preventDefault();
			}
		})

	}

	function muestra_por_pagar_proveedores() {
		document.getElementById("dato_a_buscar").style.visibility = "";
		document.getElementById("boton_buscar").style.visibility = "";

		$('#guardar_datos_egreso').attr("disabled", true);
		$('#carga_prov').attr("disabled", true);

		$.ajax({
			url: '../clases/egresos.php?action=actualiza_compras_por_pagar',
			beforeSend: function(objeto) {
				$('#loader').html('<img src="../image/ajax-loader.gif"> Actualizando, espere por favor...');
			},
			success: function(data) {
				$(".outer_div").html(data).fadeIn('slow');
				$('#loader').html('');
				$('#guardar_datos_egreso').attr("disabled", false);
				$('#carga_prov').attr("disabled", false);
				load(1);
				event.preventDefault();
			}
		});
	}


	function actualiza_egreso_tmp() {
		document.getElementById("dato_a_buscar").style.visibility = "";
		document.getElementById("boton_buscar").style.visibility = "";
		$.ajax({
			url: '../clases/egresos.php?action=actualiza_egreso_tmp',
			beforeSend: function(objeto) {
				$('#loader').html('<img src="../image/ajax-loader.gif"> Actualizando, espere por favor...');
			},
			success: function(data) {
				$(".outer_div").html(data).fadeIn('slow');
				$('#loader').html('');
				load(1);
				event.preventDefault();
			}
		});
	}


	function muestra_formas_de_pago_egreso() {
		document.querySelector("#opciones_formasPagosEgreso").reset();
		$("#loader_formas_pagos_egreso").fadeIn('slow');
		$.ajax({
			url: '../clases/egresos.php?action=mostrar_formas_de_pago_egreso',
			beforeSend: function(objeto) {
				$('#loader_formas_pagos_egreso').html('<img src="../image/ajax-loader.gif"> Cargando...');
			},
			success: function(data) {
				$(".outer_div_formas_pagos_egreso").html(data).fadeIn('slow');
				$('#loader_formas_pagos_egreso').html('');
				mostrar_asiento();
			}
		})
		event.preventDefault();
	}

	function muestra_pagos_varios_egreso() {
		$("#loader").fadeIn('slow');
		$.ajax({
			url: '../clases/egresos.php?action=mostrar_pagos_varios_egreso',
			beforeSend: function(objeto) {
				$('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
			},
			success: function(data) {
				$(".outer_div").html(data).fadeIn('slow');
				$('#loader').html('');
				mostrar_asiento();
			}
		})
		event.preventDefault();
	};


	function agrega_por_pagar_proveedor(id) { //viene del boton de buscar_por_pagar_en_egreso de proveedores
		var total_documento_por_pagar = $("#total_documento_por_pagar" + id).val();
		var a_pagar = $("#a_pagar" + id).val();
		var nombre_proveedor = $("#nombre_proveedor" + id).val();
		var id_proveedor = $("#id_proveedor_seleccionado" + id).val();
		var beneficiario = $("#nombre_beneficiario").val();
		if (beneficiario == "") {
			$("#nombre_beneficiario").val(nombre_proveedor);
			$("#id_proveedor").val(id_proveedor);
		}

		//Inicia validacion
		if (isNaN(a_pagar)) {
			alert('El dato ingresado, no es un número');
			document.getElementById('a_pagar' + id).focus();
			return false;
		}

		if (Number(a_pagar) > Number(total_documento_por_pagar)) {
			alert('El valor ingresado es mayor que el valor pendiente por pagar');
			document.getElementById('a_pagar' + id).focus();
			return false;
		}

		if (Number(a_pagar) <= 0) {
			alert('El valor ingresado debe ser mayor a cero');
			document.getElementById('a_pagar' + id).focus();
			return false;
		}

		//Fin validacion
		$.ajax({
			type: "POST",
			url: "../ajax/agregar_detalle_egreso.php?action=agrega_facturas_compras",
			data: "id=" + id + "&a_pagar=" + a_pagar,
			beforeSend: function(objeto) {
				$("#detalle_de_egresos").html("Cargando...");
			},
			success: function(datos) {
				$("#detalle_de_egresos").html(datos);
				var dato_buscado = $("#dato_a_buscar").val();
				actualiza_egreso_tmp();
				$("#dato_a_buscar").val(dato_buscado);
				mostrar_asiento();
				event.preventDefault();
			}
		});

	}

	function agrega_por_pagar_nomina(id) {
		var total_sueldo_por_pagar = $("#total_sueldo_por_pagar" + id).val();
		var a_pagar = $("#a_pagar_sueldo" + id).val();
		var nombre_empleado = $("#nombre_empleado" + id).val();
		var mes_ano = $("#mes_ano" + id).val();
		var id_empleado = $("#id_empleado" + id).val();
		var beneficiario = $("#nombre_beneficiario").val();
		if (beneficiario == "") {
			$("#nombre_beneficiario").val(nombre_empleado);
			$("#id_proveedor").val(id_empleado);
		}

		//Inicia validacion
		if (isNaN(a_pagar)) {
			alert('El dato ingresado, no es un número');
			document.getElementById('a_pagar_sueldo' + id).focus();
			return false;
		}

		if (Number(a_pagar) > Number(total_sueldo_por_pagar)) {
			alert('El valor ingresado es mayor que el valor pendiente por pagar');
			document.getElementById('a_pagar_sueldo' + id).focus();
			return false;
		}

		if (Number(a_pagar) <= 0) {
			alert('El valor ingresado debe ser mayor a cero');
			document.getElementById('a_pagar_sueldo' + id).focus();
			return false;
		}

		//Fin validacion
		$.ajax({
			type: "POST",
			url: "../ajax/agregar_detalle_egreso.php?action=agrega_sueldos_por_pagar",
			data: "id=" + id + "&a_pagar=" + a_pagar + "&nombre_empleado=" + nombre_empleado + "&mes_ano=" + mes_ano,
			beforeSend: function(objeto) {
				$("#detalle_de_egresos").html("Cargando...");
			},
			success: function(datos) {
				$("#detalle_de_egresos").html(datos);
				var dato_buscado = $("#dato_a_buscar_nomina").val();
				actualiza_egreso_tmp();
				$("#dato_a_buscar_nomina").val(dato_buscado);
				mostrar_asiento();
				event.preventDefault();
			}
		});

	}

	//para agregar la quincena al egreso tmp
	function agrega_por_pagar_quincena(id) {
		var total_quincena_por_pagar = $("#total_quincena_por_pagar" + id).val();
		var a_pagar = $("#a_pagar_quincena" + id).val();
		var nombre_empleado = $("#nombre_empleado" + id).val();
		var mes_ano = $("#mes_ano" + id).val();
		var id_empleado = $("#id_empleado" + id).val();
		var beneficiario = $("#nombre_beneficiario").val();
		if (beneficiario == "") {
			$("#nombre_beneficiario").val(nombre_empleado);
			$("#id_proveedor").val(id_empleado);
		}

		//Inicia validacion
		if (isNaN(a_pagar)) {
			alert('El dato ingresado, no es un número');
			document.getElementById('a_pagar_sueldo' + id).focus();
			return false;
		}

		if (Number(a_pagar) > Number(total_quincena_por_pagar)) {
			alert('El valor ingresado es mayor que el valor pendiente por pagar');
			document.getElementById('a_pagar_quincena' + id).focus();
			return false;
		}

		if (Number(a_pagar) <= 0) {
			alert('El valor ingresado debe ser mayor a cero');
			document.getElementById('a_pagar_quincena' + id).focus();
			return false;
		}

		//Fin validacion
		$.ajax({
			type: "POST",
			url: "../ajax/agregar_detalle_egreso.php?action=agrega_quincena_por_pagar",
			data: "id=" + id + "&a_pagar=" + a_pagar + "&nombre_empleado=" + nombre_empleado + "&mes_ano=" + mes_ano,
			beforeSend: function(objeto) {
				$("#detalle_de_egresos").html("Cargando...");
			},
			success: function(datos) {
				$("#detalle_de_egresos").html(datos);
				var dato_buscado = $("#dato_a_buscar_quincena").val();
				actualiza_egreso_tmp();
				$("#dato_a_buscar_quincena").val(dato_buscado);
				mostrar_asiento();
				event.preventDefault();
			}
		});

	}

	//eliminar iten del egreso
	function eliminar_fila_egreso(id) {
		$.ajax({
			type: "GET",
			url: "../ajax/agregar_detalle_egreso.php",
			data: "action=eliminar_item_egreso&id=" + id,
			beforeSend: function(objeto) {
				$("#detalle_de_egresos").html("Cargando...");
			},
			success: function(datos) {
				$("#detalle_de_egresos").html(datos);
				muestra_por_pagar_proveedores();
				mostrar_asiento();
			}
		});
		event.preventDefault();
	}

	//eliminar iten del egreso de forma de pago
	function eliminar_fila_pago_egreso(id) {
		$.ajax({
			type: "GET",
			url: "../ajax/agregar_detalle_egreso.php",
			data: "action=eliminar_pago_egreso&id_fp_tmp=" + id,
			beforeSend: function(objeto) {
				$("#detalle_de_egresos").html("Cargando...");
			},
			success: function(datos) {
				$("#detalle_de_egresos").html(datos);
				mostrar_asiento();
			}
		});
		event.preventDefault();

	}


	//agregar pagos	
	function agrega_pagos_egreso() {
		var forma_pago_egreso = $("#forma_pago_egreso").val();
		var valor_pago_egreso = $("#valor_pago_egreso").val();
		var numero_cheque_egreso = $("#numero_cheque_egreso").val();
		var fecha_cobro_egreso = $("#fecha_cobro_egreso").val();
		var tipo = $("#tipo").val();

		//Inicia validacion
		if ((forma_pago_egreso == 0)) {
			alert('Seleccione forma de pago');
			document.getElementById('forma_pago_egreso').focus();
			return false;
		}

		//origen es para ver de que tabla me esta trayendo el dato, para segubn eso mostrar deposito o transferencia
		var origen = forma_pago_egreso.substring(0, 1);

		if (origen == 1 && tipo != '0') {
			document.getElementById("tipo").value = "0";
			document.getElementById('valor_pago_egreso').focus();
			return false;
		}

		if (origen == 2 && tipo == '0') {
			alert('Seleccione cheque, débito o transferencia.');
			document.getElementById('tipo').focus();
			return false;
		}

		if (isNaN(valor_pago_egreso)) {
			alert('Ingrese un valor correcto');
			document.getElementById('valor_pago_egreso').focus();
			return false;
		}

		if (Number(valor_pago_egreso) < 0) {
			alert('El valor ingresado debe ser mayor o igual a cero');
			document.getElementById('valor_pago_egreso').focus();
			return false;
		}

		if ((tipo == "C" && numero_cheque_egreso == "")) {
			alert('Ingrese número de cheque.');
			document.getElementById('numero_cheque_egreso').focus();
			return false;
		}

		if ((tipo == "C" && numero_cheque_egreso != "" && fecha_cobro_egreso == "")) {
			alert('Ingrese fecha de cobro del cheque.');
			document.getElementById('fecha_cobro_egreso').focus();
			return false;
		}

		var forma_pago = forma_pago_egreso.substring(1, forma_pago_egreso.length);
		//Fin validacion
		$.ajax({
			type: "POST",
			url: "../ajax/agregar_detalle_egreso.php?action=forma_de_pago_egreso",
			data: "forma_pago_egreso=" + forma_pago + "&valor_pago_egreso=" + valor_pago_egreso + "&numero_cheque_egreso=" + numero_cheque_egreso + "&fecha_cobro_egreso=" + fecha_cobro_egreso + "&origen=" + origen + "&tipo=" + tipo,
			beforeSend: function(objeto) {
				$("#detalle_de_egresos").html("Mensaje: Cargando...");
			},
			success: function(datos) {
				$("#detalle_de_egresos").html(datos);
				mostrar_asiento();
			}
		});
		event.preventDefault();
	}

	//para guardar el egreso
	$(function() {
		$("#guardar_egreso").submit(function(event) {
			$('#guardar_datos_egreso').attr("disabled", true);
			var parametros = $(this).serialize();
			$.ajax({
				type: "POST",
				url: '../ajax/guardar_egreso.php',
				data: parametros,
				beforeSend: function(objeto) {
					$("#mensaje_al_guardar_egreso").html("Guardando...");
				},
				success: function(datos) {
					$("#resultados_guardar_egreso").html(datos);
					$("#mensaje_al_guardar_egreso").html("");
					$('#guardar_datos_egreso').attr("disabled", false);
				}
			});
			event.preventDefault();
		});
	});

	//para guardar un nuevo proveedor
	$("#guardar_proveedor").submit(function(event) {
		$('#guardar_datos').attr("disabled", true);
		var parametros = $(this).serialize();
		$.ajax({
			type: "POST",
			url: '../ajax/guarda_proveedor_retencion.php',
			data: parametros,
			beforeSend: function(objeto) {
				$("#resultados_ajax").html("Mensaje: Guardando...");
			},
			success: function(datos) {
				$("#resultados_ajax").html(datos);
				$('#guardar_datos').attr("disabled", false);
			}
		});
		event.preventDefault();
	});
	//agrega un item
	function agregar_detalle_egreso() {
		var tipo_egreso = $("#tipo_egreso").val();
		var valor_egreso = $("#valor_egreso").val();
		var detalle_egreso = $("#detalle_egreso").val();
		var nombre_beneficiario = $("#nombre_beneficiario").val();

		if (nombre_beneficiario == '') {
			alert('Ingrese beneficiario');
			document.getElementById('nombre_beneficiario').focus();
			return false;
		}
		//Inicia validacion
		if (tipo_egreso == '0') {
			alert('Seleccione un tipo de egreso');
			document.getElementById('tipo_egreso').focus();
			return false;
		}
		if (valor_egreso == '') {
			alert('Ingrese valor');
			document.getElementById('valor_egreso').focus();
			return false;
		}

		if (isNaN(valor_egreso)) {
			alert('El dato ingresado en valor, no es un número');
			document.getElementById('valor_egreso').focus();
			return false;
		}

		if (detalle_egreso == '') {
			alert('Ingrese detalle del egreso');
			document.getElementById('detalle_egreso').focus();
			return false;
		}
		//Fin validacion
		$("#detalle_de_egresos").fadeIn('fast');
		$.ajax({
			url: "../ajax/agregar_detalle_egreso.php?action=agrega_diferentes_egresos&tipo_egreso=" + tipo_egreso + "&valor_egreso=" + valor_egreso + "&detalle_egreso=" + detalle_egreso + "&nombre_beneficiario=" + nombre_beneficiario,
			beforeSend: function(objeto) {
				$("#detalle_de_egresos").html("Cargando detalle...");
			},
			success: function(data) {
				$("#detalle_de_egresos").html(data);
				document.getElementById("tipo_egreso").value = "0";
				document.getElementById("valor_egreso").value = "";
				document.getElementById("detalle_egreso").value = "";
				mostrar_asiento();
			}
		})
		event.preventDefault();
	}



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

		//$("#cuenta_diario").autocomplete("widget").addClass("fixedHeight"); //para que aparezca la barra de desplazamiento en el buscar

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

				// Verificar que el mes y año sean los mismos
				if (mes_entra != mes_hoy) {
					$("#fecha_egreso").notify("El mes ingresado no es igual al mes actual", {
						position: "top center",
						className: "info" // Notificación informativa
					});
				}

				if (anio_entra != anio_hoy) {
					$("#fecha_egreso").notify("El año ingresado no es igual al año actual", {
						position: "top center",
						className: "info" // Notificación informativa
					});
				}

				// Verificar que la fecha no sea mayor a la actual
				if (fecha_input_obj > fecha_hoy_obj) {
					$("#fecha_egreso").val(fecha_hoy); // Asignar la fecha actual
					$("#fecha_egreso").notify("La fecha ingresada es mayor a la fecha actual.", {
						position: "top center",
					});
				}
			}

			document.getElementById('nombre_beneficiario').focus();
		});
	});
</script>