<!DOCTYPE html>
<html lang="es">

<head>
	<title>Contables</title>
	<?php
	session_start();
	if (isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa']) && isset($_SESSION['ruc_empresa'])) {
		$id_usuario = $_SESSION['id_usuario'];
		$id_empresa = $_SESSION['id_empresa'];
		$ruc_empresa = $_SESSION['ruc_empresa'];

		include("../paginas/menu_de_empresas.php");

	?>
</head>

<body>
	<?php
		include("../modal/detalle_compras_proveedor.php");
	?>
	<div class="container-fluid">
		<div class="panel panel-info">
			<div class="panel-heading">
				<h4><i class='glyphicon glyphicon-pencil'></i> Configuración de cuentas contables.</h4>
			</div>
			<div class="panel-body">
				<form class="form-horizontal" role="form">
					<input type="hidden" name="id_cuenta" id="id_cuenta">
					<div class="form-group">
						<div class="col-md-4">
							<div class="input-group">
								<span class="input-group-addon"><b>Tipo asiento</b></span>
								<select class="form-control input-sm" id="tipo_asiento" name="tipo_asiento" required>
									<option value="ventas" selected> Ventas con facturas</option>
									<option value="recibos"> Ventas con recibos</option>
									<option value="compras_servicios"> Adquisiciones de compras y/o servicios</option>
									<option value="retenciones_ventas"> Retenciones en ventas</option>
									<option value="retenciones_compras"> Retenciones en compras</option>
									<option value="ingresosegresos"> Ingresos y egresos</option>
									<option value="cobrosypagos"> Cobros y pagos</option>
									<option value="rol_pagos"> Roles de pago</option>
								</select>
							</div>
						</div>
						<div class="col-md-2">
							<div class="input-group">
								<span class="input-group-addon"><b>Año</b></span>
								<select class="form-control input-sm" id="anio_transaccion" name="anio_transaccion" onchange='load(1);'>
									<option value="">Todos</option>
									<option value="<?php echo date("Y") ?>" selected><?php echo date("Y") ?></option>
									<?php
									for ($i = $anio2 = date("Y") - 1; $i > $anio1 = date("Y") - 6; $i += -1) {
									?>
										<option value="<?php echo $i ?>"> <?php echo $i ?></option>
									<?php
									}
									?>
								</select>
							</div>
						</div>
						<div class="col-md-2">
							<div class="input-group">
								<span class="input-group-addon"><b>Mes</b></span>
								<select class="form-control input-sm" id="mes_transaccion" name="mes_transaccion" onchange='load(1);'>
									<option value="" selected>Todos</option>
									<option value="01">Enero</option>
									<option value="02">Febrero</option>
									<option value="03">Marzo</option>
									<option value="04">Abril</option>
									<option value="05">Mayo</option>
									<option value="06">Junio</option>
									<option value="07">Julio</option>
									<option value="08">Agosto</option>
									<option value="09">Septiembre</option>
									<option value="10">Octubre</option>
									<option value="11">Noviembre</option>
									<option value="12">Diciembre</option>
								</select>
							</div>
						</div>

						<div class="col-sm-4">
							<button type="button" title="Mostrar " class="btn btn-info btn-sm" onclick="mostrar_tipo()"><span class="glyphicon glyphicon-search"></span> Mostrar</button>
							<span id="loader"></span>
						</div>
					</div>
				</form>
				<div id="resultados"></div><!-- Carga los datos ajax -->
				<div class='outer_div'></div><!-- Carga los datos ajax -->
			</div><!--fin del body de todo -->
		</div><!--fin del panel info que abarca a todo -->
	</div> <!--fin de la caja de 8 espacios -->
	<!--</div> fin del container -->

<?php } else {
		header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/sistema') . '/empresa');
		exit;
	}
?>
<link rel="stylesheet" href="../css/jquery-ui.css">
<script src="../js/jquery-ui.js"></script>
<script src="../js/notify.js"></script>
<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
</body>

</html>
<script>
	function mostrar_tipo() {
		var tipo = $("#tipo_asiento").val();
		var mes = $("#mes_transaccion").val();
		var anio = $("#anio_transaccion").val();
		$("#loader").fadeIn('slow');
		$.ajax({
			url: '../ajax/configuracion_contable.php?action=buscar_asientos_prestablecidos&tipo=' + tipo + '&mes=' + mes + '&anio=' + anio,
			beforeSend: function(objeto) {
				$('#loader').html('<img src="../image/ajax-loader.gif"> Cargando, espere por favor...');
			},
			success: function(data) {
				$(".outer_div").html(data).fadeIn('slow');
				$('#loader').html('');
			}
		})
	}

	//mostrar detalle
	function mostrar_detalle_compras(id) {
		$.ajax({
			url: "../ajax/detalle_documento.php?action=detalle_compras_proveedor&id_proveedor=" + id,
			beforeSend: function(objeto) {
				$("#loaderdet").html("Mostrando detalle...");
			},
			success: function(data) {
				$(".outer_divdet").html(data).fadeIn('fast');
				$('#loaderdet').html('');
			}
		});
	}


	function mostrar_cuentas_empleado() {
		var id_empleado = $("#idEmpleado").val();
		$(".cuentas_empleado").html('');
		if (id_empleado == "") {
			alert('Seleccione un empleado');
			document.getElementById('datalistEmpleados').focus();
			return false;
		}

		$.ajax({
			url: "../ajax/configuracion_contable.php?action=mostrar_cuentas_empleado&id_empleado=" + id_empleado,
			beforeSend: function(objeto) {
				$("#loader_empleado").html("Mostrando cuentas...");
			},
			success: function(data) {
				$(".cuentas_empleado").html(data).fadeIn('fast');
				$('#loader_empleado').html('');
			}
		});
	}

	//cuando es una cuenta
	function guardar_cuenta(extension, codigo_unico, id_documento, tipo, concepto_cuenta, id_registro, id_asiento_tipo) {
		$("#cuenta_contable" + extension + codigo_unico).autocomplete({
			source: '../ajax/cuentas_autocompletar.php',
			minLength: 2,
			select: function(event, ui) {
				event.preventDefault();
				$('#id_cuenta' + extension + codigo_unico).val(ui.item.id_cuenta);
				var id_cuenta = $("#id_cuenta" + extension + codigo_unico).val();
				$('#cuenta_contable' + extension + codigo_unico).val(ui.item.nombre_cuenta);
				$.ajax({
					url: "../ajax/configuracion_contable.php?action=guarda_cuenta&id_documento=" + id_documento + "&id_cuenta=" + id_cuenta + "&tipo=" + tipo + "&concepto_cuenta=" + concepto_cuenta + "&id_registro=" + id_registro + "&id_asiento_tipo=" + id_asiento_tipo,
					beforeSend: function(objeto) {
						$("#loader").html("Guardando...");
					},
					success: function(data) {
						$(".outer_divdet").html(data).fadeIn('fast');
						$('#loader').html('');
					}
				});
			}
		});
		$("#cuenta_contable" + extension + codigo_unico).on("keydown", function(event) {
			if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
				$("#id_cuenta" + extension + codigo_unico).val("");
				$("#cuenta_contable" + extension + codigo_unico).val("");
			}
			if (event.keyCode == $.ui.keyCode.DELETE) {
				$("#cuenta_contable" + extension + codigo_unico).val("");
				$("#id_cuenta" + extension + codigo_unico).val("");
			}
		});

	}

	function eliminar_cuenta(extension, codigo_unico, id_registro) {
		if (confirm("Realmente desea eliminar la cuenta?")) {
			$.ajax({
				url: "../ajax/configuracion_contable.php?action=eliminar_cuenta&id_registro=" + id_registro,
				beforeSend: function(objeto) {
					$("#loader").html("Guardando...");
				},
				success: function(data) {
					$(".outer_divdet").html(data).fadeIn('fast');
					$('#loader').html('');
					$('#cuenta_contable' + extension + codigo_unico).val('');
					$('#codigo_cuenta' + extension + codigo_unico).val('');
					$('#id_cuenta' + extension + codigo_unico).val('');
				}
			});
		}

	}

	function agregar_empleado() {
		$("#datalistEmpleados").autocomplete({
			source: '../ajax/empleado_autocompletar_rol.php',
			minLength: 2,
			select: function(event, ui) {
				event.preventDefault();
				$('#idEmpleado').val(ui.item.id);
				$('#datalistEmpleados').val(ui.item.nombres_apellidos);
			}

		});

		$("#datalistEmpleados").on("keydown", function(event) {
			if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
				$("#idEmpleado").val("");
				$("#datalistEmpleados").val("");
			}
			if (event.keyCode == $.ui.keyCode.DELETE) {
				$("#datalistEmpleados").val("");
				$("#idEmpleado").val("");
			}
		});

	}

	//para facturas de ventas
	function listado_clientes_facturas() {
		var anio = $("#anio_transaccion").val();
		var mes = $("#mes_transaccion").val();
		$.ajax({
			url: '../ajax/configuracion_contable.php?action=listado_clientes_facturas&anio=' + anio + '&mes=' + mes,
			beforeSend: function(objeto) {
				$('#loader').html('Cargando lista de clientes, espere por favor...');
			},
			success: function(data) {
				$(".listado_clientes_facturas").html(data).fadeIn('slow');
				$('#loader').html('');
			}
		})
	}

	function listado_productos_ventas() {
		var anio = $("#anio_transaccion").val();
		var mes = $("#mes_transaccion").val();
		$.ajax({
			url: '../ajax/configuracion_contable.php?action=listado_productos_ventas&anio=' + anio + '&mes=' + mes,
			beforeSend: function(objeto) {
				$('#loader').html('Cargando lista de productos y servicios, espere por favor...');
			},
			success: function(data) {
				$(".listado_productos_ventas").html(data).fadeIn('slow');
				$('#loader').html('');
			}
		})
	}

	function listado_categorias_ventas() {
		$.ajax({
			url: '../ajax/configuracion_contable.php?action=listado_categorias_ventas',
			beforeSend: function(objeto) {
				$('#loader').html('Cargando categorías, espere por favor...');
			},
			success: function(data) {
				$(".listado_categorias_ventas").html(data).fadeIn('slow');
				$('#loader').html('');
			}
		})
	}

	function listado_marcas_ventas() {
		$.ajax({
			url: '../ajax/configuracion_contable.php?action=listado_marcas_ventas',
			beforeSend: function(objeto) {
				$('#loader').html('Cargando marcas, espere por favor...');
			},
			success: function(data) {
				$(".listado_marcas_ventas").html(data).fadeIn('slow');
				$('#loader').html('');
			}
		})
	}


	//para recibos de ventas
	function listado_clientes_recibos() {
		var anio = $("#anio_transaccion").val();
		var mes = $("#mes_transaccion").val();
		$.ajax({
			url: '../ajax/configuracion_contable.php?action=listado_clientes_recibos&anio=' + anio + '&mes=' + mes,
			beforeSend: function(objeto) {
				$('#loader').html('Cargando lista de clientes, espere por favor...');
			},
			success: function(data) {
				$(".listado_clientes_recibos").html(data).fadeIn('slow');
				$('#loader').html('');
			}
		})
	}

	function listado_productos_ventas_recibos() {
		var anio = $("#anio_transaccion").val();
		var mes = $("#mes_transaccion").val();
		$.ajax({
			url: '../ajax/configuracion_contable.php?action=listado_productos_ventas_recibos&anio=' + anio + '&mes=' + mes,
			beforeSend: function(objeto) {
				$('#loader').html('Cargando lista de productos y servicios, espere por favor...');
			},
			success: function(data) {
				$(".listado_productos_ventas_recibos").html(data).fadeIn('slow');
				$('#loader').html('');
			}
		})
	}

	function listado_categorias_ventas_recibos() {
		$.ajax({
			url: '../ajax/configuracion_contable.php?action=listado_categorias_ventas_recibos',
			beforeSend: function(objeto) {
				$('#loader').html('Cargando categorías, espere por favor...');
			},
			success: function(data) {
				$(".listado_categorias_ventas_recibos").html(data).fadeIn('slow');
				$('#loader').html('');
			}
		})
	}

	function listado_marcas_ventas_recibos() {
		$.ajax({
			url: '../ajax/configuracion_contable.php?action=listado_marcas_ventas_recibos',
			beforeSend: function(objeto) {
				$('#loader').html('Cargando marcas, espere por favor...');
			},
			success: function(data) {
				$(".listado_marcas_ventas_recibos").html(data).fadeIn('slow');
				$('#loader').html('');
			}
		})
	}


	//para proveedores
	function listado_proveedores() {
		var anio = $("#anio_transaccion").val();
		var mes = $("#mes_transaccion").val();
		$.ajax({
			url: '../ajax/configuracion_contable.php?action=listado_proveedores&anio=' + anio + '&mes=' + mes,
			beforeSend: function(objeto) {
				$('#loader').html('Cargando lista de proveedores, espere por favor...');
			},
			success: function(data) {
				$(".listado_proveedores").html(data).fadeIn('slow');
				$('#loader').html('');
			}
		})
	}
</script>