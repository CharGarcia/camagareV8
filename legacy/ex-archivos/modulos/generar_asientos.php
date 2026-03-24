<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="es" lang="es">

<head>
	<title>Generar asientos</title>
	<?php
	session_start();
	if (isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa']) && isset($_SESSION['ruc_empresa'])) {
		$id_usuario = $_SESSION['id_usuario'];
		$id_empresa = $_SESSION['id_empresa'];
		$ruc_empresa = $_SESSION['ruc_empresa'];
		include("../paginas/menu_de_empresas.php");
		$con = conenta_login();
	?>
</head>

<body>
	<div class="container-fluid">
		<div class="panel panel-info">
			<div class="panel-heading">
				<h4><i class='glyphicon glyphicon-pencil'></i> Generar asientos contables</h4>
			</div>
			<div class="panel-body">
				<form class="form-horizontal" role="form">
					<input type="hidden" name="id_cuenta" id="id_cuenta">
					<div class="form-group">
						<div class="col-md-4">
							<div class="input-group">
								<span class="input-group-addon"><b>Asientos de:</b></span>
								<select class="form-control input-sm" id="tipo_asiento" name="tipo_asiento" required>
									<option value="ventas" selected> Ventas Facturas</option>
									<option value="recibos"> Ventas Recibos</option>
									<option value="nc_ventas"> Notas de crédito Ventas</option>
									<option value="retenciones_ventas"> Retenciones en ventas</option>
									<option value="retenciones_compras"> Retenciones en compras</option>
									<option value="compras_servicios"> Adquisiciones compras y/o servicios</option>
									<option value="ingresos"> Ingresos</option>
									<option value="egresos"> Egresos</option>
									<option value="rol_pagos"> Roles de pago</option>
								</select>
							</div>
						</div>
						<div class="col-sm-2">
							<div class="input-group">
								<span class="input-group-addon"><b>Año</b></span>
								<select class="form-control input-sm" name="anio_asiento" id="anio_asiento">
									<option value="<?php echo date("Y") ?>"> <?php echo date("Y") ?></option>
									<?php for ($i = $anio2 = date("Y") - 1; $i > $anio1 = date("Y") - 7; $i += -1) {
									?>
										<option value="<?php echo $i ?>"> <?php echo $i ?></option>
									<?php }  ?>
								</select>
							</div>
						</div>
						<div class="col-sm-2">
							<div class="input-group">
								<span class="input-group-addon"><b>Mes</b></span>
								<select class="form-control input-sm" name="mes_asiento" id="mes_asiento">
									<option value="todos">Todos</option>
									<option value="S1">Primer semestre</option>
									<option value="S2">Segundo semestre</option>
									<option value="01"> 01</option>
									<option value="02"> 02</option>
									<option value="03"> 03</option>
									<option value="04"> 04</option>
									<option value="05"> 05</option>
									<option value="06"> 06</option>
									<option value="07"> 07</option>
									<option value="08"> 08</option>
									<option value="09"> 09</option>
									<option value="10"> 10</option>
									<option value="11"> 11</option>
									<option value="12"> 12</option>
								</select>
							</div>
						</div>
						<div class="col-sm-2">
							<div class="input-group">
								<span class="input-group-addon"><b>Desde</b></span>
								<input type="text" class="form-control input-sm text-center" name="fecha_desde" id="fecha_desde" value="<?php echo date("01-01-Y"); ?>">
							</div>
						</div>
						<div class="col-sm-2">
							<div class="input-group">
								<span class="input-group-addon"><b>Hasta</b></span>
								<input type="text" class="form-control input-sm text-center" name="fecha_hasta" id="fecha_hasta" value="<?php echo date("d-m-Y"); ?>">
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-3">
							<button type="button" id="generar_asientos" title="Mostrar " class="btn btn-info btn-sm" onclick="mostrar_documentos()"><span class="glyphicon glyphicon-search"></span> Generar Asientos</button>
							<button type="button" id="guardar_asiento" title="Guardar " class="btn btn-success btn-sm" onclick="guardar_asientos(event)"><span class="glyphicon glyphicon-floppy-disk"></span> Guardar Asientos</button>
						</div>
					</div>
					<div class="form-group">
						<div class="col-md-12">
							<span id="loader"></span>
						</div>
					</div>

				</form>
				<div id="resultados"></div><!-- Carga los datos ajax -->
				<div class='outer_div'></div><!-- Carga los datos ajax -->
			</div>
			<!--fin del body de todo -->
		</div>
		<!--fin del panel info que abarca a todo -->
	</div>
	<!--fin del container -->
<?php } else {
		header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/sistema') . '/empresa');
		exit;
	}
?>
<link rel="stylesheet" href="../css/jquery-ui.css">
<script src="../js/jquery-ui.js"></script>
<script src="../js/notify.js"></script>
<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
<script src="../js/rango_fechas.js"></script>
</body>

</html>
<script>
	jQuery(function($) {
		$("#fecha_desde").mask("99-99-9999");
		$("#fecha_hasta").mask("99-99-9999");
	});

	$(function() {
		$("#fecha_desde").datepicker({
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
		$("#fecha_hasta").datepicker({
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

	function mostrar_documentos() {
		var transaccion = $("#tipo_asiento").val();
		var cliente_proveedor = ""; //$("#id_cli_pro").val();
		var desde = $("#fecha_desde").val();
		var hasta = $("#fecha_hasta").val();
		var rango_fechas = rango_fecha(desde, hasta, 185);

		if (rango_fechas == true) {
			alert('La diferencia entre las fechas no puede ser mayor a 6 meses.');
			return false;
		}

		$("#generar_asientos").hide();
		$("#guardar_asiento").hide();
		$.ajax({
			url: '../ajax/buscar_documentos_por_contabilizar.php?action=' + transaccion + '&cliente_proveedor=' + cliente_proveedor + '&desde=' + desde + '&hasta=' + hasta,
			beforeSend: function(objeto) {
				$('#loader').html('<img src="../image/ajax-loader.gif"> Generando asientos. Este proceso pueder tardar más de 1 minuto, espere por favor...');
			},
			success: function(data) {
				$(".outer_div").html(data).fadeIn('slow');
				$('#loader').html('');
				$("#generar_asientos").show();
				$("#guardar_asiento").show();
			}
		})
	}


	function buscar_cli_pro() {
		var tipo_asiento = $("#tipo_asiento").val();
		if (tipo_asiento == 'ventas' || tipo_asiento == 'recibos' || tipo_asiento == 'nc_ventas' || tipo_asiento == 'retenciones_ventas' || tipo_asiento == 'ingresos') {
			$("#cliente_proveedor").autocomplete({
				source: '../ajax/clientes_autocompletar.php',
				minLength: 2,
				select: function(event, ui) {
					event.preventDefault();
					$('#id_cli_pro').val(ui.item.id);
					$('#cliente_proveedor').val(ui.item.nombre);
				}
			});
		}

		if (tipo_asiento == 'compras_servicios' || tipo_asiento == 'retenciones_compras' || tipo_asiento == 'egresos') {
			$("#cliente_proveedor").autocomplete({
				source: '../ajax/proveedores_autocompletar.php',
				minLength: 2,
				select: function(event, ui) {
					event.preventDefault();
					$('#id_cli_pro').val(ui.item.id_proveedor);
					$('#cliente_proveedor').val(ui.item.razon_social);
				}
			});
		}

		$("#cliente_proveedor").on("keydown", function(event) {
			if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
				$("#id_cli_pro").val("");
				$("#cliente_proveedor").val("");
			}
			if (event.keyCode == $.ui.keyCode.DELETE) {
				$("#cliente_proveedor").val("");
				$("#id_cli_pro").val("");
			}
		});
	}

	function eliminar_registro(id_registro, transaccion) {
		if (confirm("Realmente desea eliminar el item?")) {
			$.ajax({
				type: "GET",
				url: "../ajax/buscar_documentos_por_contabilizar.php?action=eliminar_registro",
				data: "id_registro=" + id_registro + "&transaccion=" + transaccion,
				beforeSend: function(objeto) {
					$("#resultados").html("Eliminando registros, espere por favor...");
				},
				success: function(datos) {
					$(".outer_div").html(datos).fadeIn('slow');
					$("#resultados").html('');
				}
			});
			event.preventDefault();
		}
	}

	//guardar asientos automaticos
	/* 	function guardar_asientos() {
			var tipo_asiento = $("#tipo_asiento").val();
			var fecha_desde = $("#fecha_desde").val();
			var fecha_hasta = $("#fecha_hasta").val();
			$('#guardar_asiento').attr("disabled", true);
			$.ajax({
				type: "POST",
				url: '../ajax/buscar_documentos_por_contabilizar.php',
				data: "action=guardar_asientos&tipo_asiento=" + tipo_asiento + "&fecha_desde=" + fecha_desde + "&fecha_hasta=" + fecha_hasta,
				beforeSend: function(objeto) {
					$('#loader').html('Guardando...');
				},
				success: function(datos) {
					$("#resultados").html(datos).fadeIn('slow');
					$('#loader').html('');
					$('#guardar_asiento').attr("disabled", false);
				}
			});
			event.preventDefault();
		} */

	function guardar_asientos(e) {
		e.preventDefault(); // evita que se recargue la página

		var tipo_asiento = $("#tipo_asiento").val();
		var fecha_desde = $("#fecha_desde").val();
		var fecha_hasta = $("#fecha_hasta").val();

		$('#guardar_asiento').attr("disabled", true);

		$.ajax({
			type: "POST",
			url: '../ajax/buscar_documentos_por_contabilizar.php',
			data: "action=guardar_asientos&tipo_asiento=" + tipo_asiento + "&fecha_desde=" + fecha_desde + "&fecha_hasta=" + fecha_hasta,
			beforeSend: function(objeto) {
				$('#loader').html('Guardando...');
			},
			success: function(datos) {
				$("#resultados").html(datos).fadeIn('slow');
				$('#loader').html('');
				$('#guardar_asiento').attr("disabled", false);
			}
		});
	}



	function modificar_debe(id, tipo_asiento) {
		var modificar_debe = $("#modificar_debe" + id).val();
		var debe_actual = $("#debe_actual" + id).val();
		var haber = $("#modificar_haber" + id).val();

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
			url: "../ajax/buscar_documentos_por_contabilizar.php",
			data: "action=actualizar_debe&id_item=" + id + "&debe=" + modificar_debe + "&haber=" + haber + "&tipo_asiento=" + tipo_asiento,
			beforeSend: function(objeto) {
				$("#loader").html("Actualizando...");
			},
			success: function(datos) {
				$(".outer_div").html(datos).fadeIn('slow');
				$('#loader').html('');
			}
		});
	}

	function modificar_haber(id, tipo_asiento) {
		var modificar_haber = $("#modificar_haber" + id).val();
		var haber_actual = $("#haber_actual" + id).val();
		var debe = $("#modificar_debe" + id).val();

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
			url: "../ajax/buscar_documentos_por_contabilizar.php",
			data: "action=actualizar_haber&id_item=" + id + "&haber=" + modificar_haber + "&debe=" + debe + "&tipo_asiento=" + tipo_asiento,
			beforeSend: function(objeto) {
				$("#loader").html("Actualizando...");
			},
			success: function(datos) {
				$(".outer_div").html(datos).fadeIn('slow');
				$('#loader').html('');
			}
		});
	}

	$('#anio_asiento').change(function() {
		var anio = $("#anio_asiento").val();
		$("#mes_asiento").val('todos');
		$("#fecha_desde").val('01-01-' + anio);
		$("#fecha_hasta").val('31-12-' + anio);
	});

	$('#mes_asiento').change(function() {
		var mes = $("#mes_asiento").val();
		var anio = $("#anio_asiento").val();

		if (mes === "todos") {
			// Si se selecciona "Todos", usar todo el año completo
			$("#fecha_desde").val('01-01-' + anio);
			$("#fecha_hasta").val('31-12-' + anio);
		} else if (mes === "S1") {
			$("#fecha_desde").val('01-01-' + anio);
			$("#fecha_hasta").val('30-06-' + anio);
		} else if (mes === "S2") {
			$("#fecha_desde").val('01-07-' + anio);
			$("#fecha_hasta").val('31-12-' + anio);
		} else {
			// Calcular el último día del mes seleccionado
			var ultimoDia = new Date(anio, mes, 0).getDate(); // mes ya está en formato 01, 02, etc.
			$("#fecha_desde").val('01-' + mes + '-' + anio);
			$("#fecha_hasta").val(ultimoDia + '-' + mes + '-' + anio);
		}
	});
</script>