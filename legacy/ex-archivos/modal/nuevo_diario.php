<div id="NuevoDiarioContable" class="modal fade" data-backdrop="static" role="dialog">
	<div class="modal-dialog modal-lg">
		<!-- Modal content-->
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" reset>×</button>
				<h4 class="modal-title"><i class='glyphicon glyphicon-edit'></i> Asiento contable</h4>
			</div>

			<div class="modal-body">
				<form class="form-horizontal" id="form_nuevo_diario">
					<div id="resultados_ajax_cuentas"></div>
					<div class="form-group">
						<div class="col-sm-3">
							<div class="input-group">
								<span class="input-group-addon"><b>Fecha</b></span>
								<input type="text" class="form-control" id="fecha_diario" name="fecha_diario" tabindex="2" value="<?php echo date("d-m-Y"); ?>">
							</div>
						</div>
						<div class="col-sm-9">
							<div class="input-group">
								<span class="input-group-addon"><b>Concepto</b></span>
								<!--<input type="text" class="form-control focusNext" id="concepto_diario" name="concepto_diario" tabindex="3" onkeyup="pasa_concepto();" autofocus>-->
								<textarea style="height:33px;" type="textarea" id="concepto_diario" name="concepto_diario" tabindex="3" onkeyup="pasa_concepto();" class="form-control input-sm focusNext" autofocus></textarea>
							</div>
						</div>
					</div>
					<div class="modal-body">
						<div class="form-group">
							<input type="hidden" name="codigo_unico" id="codigo_unico">
							<input type="hidden" name="id_cuenta" id="id_cuenta">
							<input type="hidden" name="cod_cuenta" id="cod_cuenta">
							<div class="panel panel-info" style="margin-bottom: 5px; margin-top: -15px;">
								<table class="table table-bordered">
									<tr class="info">
										<th style="padding: 2px;">Cuenta</th>
										<th class="text-center" style="padding: 2px;">Debe</th>
										<th class="text-center" style="padding: 2px;">Haber</th>
										<th style="padding: 2px;">Detalle</th>
										<th class="text-center" style="padding: 2px;">Agregar</th>
									</tr>
									<td class='col-xs-4'>
										<input type="text" class="form-control input-sm focusNext" name="cuenta_diario" id="cuenta_diario" onkeyup='buscar_cuentas();' autocomplete="off" tabindex="4">
									</td>
									<td class='col-xs-2'><input type="text" class="form-control input-sm focusNext" name="debe_diario" id="debe_diario" tabindex="5"></td>
									<td class='col-xs-2'><input type="text" class="form-control input-sm focusNext" name="haber_cuenta" id="haber_cuenta" tabindex="6"></td>
									<td class='col-xs-4'><input type="text" class="form-control input-sm focusNext" name="det_cuenta" id="det_cuenta" tabindex="7"></td>
									<td class='col-xs-1 text-center'><button type="button" class="btn btn-info btn-sm focusNext" title="Agregar detalle de diario" tabindex="8" onclick="agregar_item_diario()"><span class="glyphicon glyphicon-plus"></span></button> </td>
								</table>
							</div>
							<div id="muestra_detalle_diario"></div><!-- Carga gif animado -->
							<div class="outer_divdet"></div><!-- Datos ajax Final -->
						</div>
					</div>
			</div>
			<div class="modal-footer">
				<span id="mensaje_nuevo_asiento"></span>
				<button type="button" class="btn btn-default" data-dismiss="modal" reset>Cerrar</button>
				<input type="button" class="btn btn-primary" id="guardar_datos" onclick="guardar_asiento();" value="Guardar">
			</div>
			</form>
		</div>

	</div>
</div>



<div id="balanceInicial" class="modal fade" data-backdrop="static" role="dialog">
	<div class="modal-dialog modal-md">
		<!-- Modal content-->
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" reset>×</button>
				<h4 class="modal-title"><i class='glyphicon glyphicon-edit'></i> Balance Inicial</h4>
			</div>

			<div class="modal-body">
				<form class="form-horizontal" id="form_balance_inicial">
					<div id="resultados_ajax_balance_inicial"></div>
					<div class="form-group">
						<div class="col-sm-10">
							<div class="input-group">
								<span class="input-group-addon"><b>Año del balance a tomar los datos</b></span>
								<input type="number" class="form-control text-right" id="ano_anterior" name="ano_anterior" value="<?php echo date("Y") - 1; ?>">
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-10">
							<div class="input-group">
								<span class="input-group-addon"><b>Año del nuevo balance inicial</b></span>
								<input type="number" class="form-control text-right" id="ano_siguiente" name="ano_siguiente" value="<?php echo date("Y"); ?>">
							</div>
						</div>
					</div>

			</div>
			<div class="modal-footer">
				<span id="mensaje_balance_inicial"></span>
				<button type="button" class="btn btn-default" data-dismiss="modal" reset>Cerrar</button>
				<input type="button" class="btn btn-primary" onclick="generar_balance_inicial();" value="Generar">
			</div>
			</form>
		</div>

	</div>
</div>
<script>
	$('#fecha_diario').css('z-index', 1500);
	$(function() {
		$("#fecha_diario").datepicker({
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
			data: "action=agregar_item&id_cuenta=" + id_cuenta + "&cod_cuenta=" + cod_cuenta + "&cuenta_diario=" + cuenta_diario + "&debe_diario=" + debe_diario + "&haber_cuenta=" + haber_cuenta + "&det_cuenta=" + det_cuenta,
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
			}
		});
	}

	function pasa_concepto() {
		var concepto_diario = $("#concepto_diario").val();
		$("#det_cuenta").val(concepto_diario);
	}

	//para guardar el asiento
	function guardar_asiento() {
		$('#guardar_datos').attr("disabled", true);
		var fecha_diario = $("#fecha_diario").val();
		var concepto_diario = $("#concepto_diario").val();
		var subtotal_debe = $("#subtotal_debe").val();
		var subtotal_haber = $("#subtotal_haber").val();
		var tipo = $("#tipo").val();
		var codigo_unico = $("#codigo_unico").val();
		$.ajax({
			type: "POST",
			url: "../ajax/guardar_libro_diario.php",
			data: "fecha_diario=" + fecha_diario + "&concepto_diario=" + concepto_diario + "&subtotal_debe=" + subtotal_debe + "&subtotal_haber=" + subtotal_haber + "&tipo=" + tipo + "&codigo_unico=" + codigo_unico,
			beforeSend: function(objeto) {
				$("#mensaje_nuevo_asiento").html("Guardando...");
			},
			success: function(datos) {
				$("#resultados_ajax_cuentas").html(datos);
				$("#mensaje_nuevo_asiento").html("");
				$('#guardar_datos').attr("disabled", false);
			}
		});
		//event.preventDefault();
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
		var debe = $("#modificar_debe" + id).val();
		var haber = $("#modificar_haber" + id).val();
		var detalle = $("#detalle_asiento" + id).val();

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
			data: "action=actualizar_cuentas_asiento&id_item=" + id + "&id_cuenta=" +
				id_cuenta_modificar + "&codigo_cuenta=" + modificar_codigo_cuenta +
				"&nombre_cuenta=" + modificar_cuenta + "&debe=" + debe + "&haber=" + haber + "&detalle=" + detalle,
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

		var id_cuenta_actual = $("#id_cuenta_modificar" + id).val();
		var debe = $("#modificar_debe" + id).val();
		var haber = $("#modificar_haber" + id).val();
		var detalle = $("#detalle_asiento" + id).val();

		if (detalle_asiento == "") {
			alert('Ingrese detalle del item, no puede quedar vacio');
			document.getElementById('detalle_asiento' + id).focus();
			$("#detalle_asiento" + id).val(detalle_original);
			return false;
		}

		$.ajax({
			type: "POST",
			url: "../ajax/agregar_item_diario_tmp.php",
			data: "action=actualizar_item_asiento&id_item=" + id + "&detalle_item=" + detalle_asiento + "&debe=" + debe + "&haber=" +
				haber + "&detalle=" + detalle + "&id_cuenta=" + id_cuenta_actual,
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
		var haber = $("#modificar_haber" + id).val();
		var detalle = $("#detalle_asiento" + id).val();
		var id_cuenta_actual = $("#id_cuenta_modificar" + id).val();

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
			data: "action=actualizar_debe&id_item=" + id + "&debe=" + modificar_debe + "&haber=" + haber + "&detalle=" + detalle + "&id_cuenta=" + id_cuenta_actual,
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

		var debe = $("#modificar_debe" + id).val();
		var detalle = $("#detalle_asiento" + id).val();
		var id_cuenta_actual = $("#id_cuenta_modificar" + id).val();

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
			data: "action=actualizar_haber&id_item=" + id + "&haber=" + modificar_haber + "&debe=" + debe + "&detalle=" + detalle + "&id_cuenta=" + id_cuenta_actual,
			beforeSend: function(objeto) {
				$("#mensaje_nuevo_asiento").html("Actualizando...");
			},
			success: function(datos) {
				$(".outer_divdet").html(datos).fadeIn('fast');
				$('#mensaje_nuevo_asiento').html('');
			}
		});
	}


	$("concepto_diario").keyup(function() {
		var height = $(this).prop("scrollHeight") + 2 + "px";
		$(this).css({
			"height": height
		});
	})
</script>