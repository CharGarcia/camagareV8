<?php
session_start();
if (isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa']) && isset($_SESSION['ruc_empresa'])) {
	$id_usuario = $_SESSION['id_usuario'];
	$id_empresa = $_SESSION['id_empresa'];
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$con = conenta_login();
	require_once dirname(__DIR__) . '/paginas/verificar_permiso_modulo.php';
?>
	<!DOCTYPE html>
	<html lang="es">

	<head>
		<meta charset="utf-8">
		<title>Cotización</title>
		<?php
		include("../paginas/menu_de_empresas.php");
		include("../modal/cotizacionPublidad.php");
		//unset($_SESSION['arrayFormaPagoIngresoFactura']);
		?>
	</head>

	<body>
		<div class="container-fluid">
			<div class="panel panel-info">
				<div class="panel-heading">
					<div class="btn-group pull-right">
						<?php
						if (getPermisos($con, $id_usuario, $ruc_empresa, 'cotizacion_publicidad')['w'] == 1) {
						?>
							<button type="submit" class="btn btn-info" data-toggle="modal" data-target="#cotizacionPublicidad" onclick="crear_cotizacion();" title="Nueva cotización"><span class="glyphicon glyphicon-plus"></span> Nueva</button>
						<?php
						}
						?>
					</div>
					<h4><i class="glyphicon glyphicon-search"></i> Cotización de servicios</h4>
				</div>

				<div class="panel-body">
					<form class="form-horizontal" role="form">
						<div class="form-group row">
							<div class="col-md-2">
								<div class="input-group">
									<span class="input-group-addon"><b>Año</b></span>
									<?php
									$currentYear = date("Y");
									?>
									<select class="form-control input-sm" id="anio" name="anio" onchange='load(1);'>
										<option value="">Todos</option>
										<?php
										$existsCurrentYear = false;
										$tipo = mysqli_query($con, "SELECT DISTINCT YEAR(fecha) AS anio FROM encabezado_cotizacion_publicidad WHERE ruc_empresa ='" . $ruc_empresa . "' ORDER BY YEAR(fecha) DESC");
										// Recorre los resultados de la consulta para verificar si el año actual ya está presente
										while ($row = mysqli_fetch_array($tipo)) {
											$selected = ($row['anio'] == $currentYear) ? 'selected' : '';
											if ($row['anio'] == $currentYear) {
												$existsCurrentYear = true;
											}
											echo '<option value="' . $row['anio'] . '" ' . $selected . '>' . strtoupper($row['anio']) . '</option>';
										}
										?>
									</select>

								</div>
							</div>
							<div class="col-md-2">
								<div class="input-group">
									<span class="input-group-addon"><b>Mes</b></span>
									<select class="form-control input-sm" id="mes" name="mes" onchange='load(1);'>
										<option value="" selected>Todos</option>
										<?php
										$tipo = mysqli_query($con, "SELECT distinct(DATE_FORMAT(fecha, '%m')) as mes FROM encabezado_cotizacion_publicidad WHERE ruc_empresa ='" . $ruc_empresa . "' order by month(fecha) asc");
										while ($row = mysqli_fetch_array($tipo)) {
										?>
											<option value="<?php echo $row['mes'] ?>"><?php echo strtoupper($row['mes']) ?></option>
										<?php
										}
										?>
									</select>
								</div>
							</div>
							<div class="col-md-2">
								<div class="input-group">
									<span class="input-group-addon"><b>Día</b></span>
									<select class="form-control input-sm" id="dia" name="dia" onchange='load(1);'>
										<option value="" selected>Todos</option>
										<?php
										$tipo = mysqli_query($con, "SELECT distinct(DATE_FORMAT(fecha, '%d')) as dia FROM encabezado_cotizacion_publicidad WHERE ruc_empresa ='" . $ruc_empresa . "' order by day(fecha) asc");
										while ($row = mysqli_fetch_array($tipo)) {
										?>
											<option value="<?php echo $row['dia'] ?>"><?php echo strtoupper($row['dia']) ?></option>
										<?php
										}
										?>
									</select>
								</div>
							</div>
							<div class="col-md-3">
								<input type="hidden" id="ordenado" value="ec.id">
								<input type="hidden" id="por" value="desc">
								<div class="input-group">
									<span class="input-group-addon"><b>Buscar:</b></span>
									<input type="text" class="form-control input-sm" id="q" placeholder="Cliente, serie, fecha, ruc, estado" onkeyup='load(1);'>
									<span class="input-group-btn">
										<button type="button" class="btn btn-default input-sm" onclick='load(1);'><span class="glyphicon glyphicon-search"></span></button>
									</span>
								</div>
							</div>
							<span id="loader"></span>
						</div>
					</form>
					<div id="resultados"></div><!-- Carga los datos ajax -->
					<div class='outer_div'></div><!-- Carga los datos ajax -->
				</div>
			</div>
		</div>

	<?php
} else {
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
		$(document).ready(function() {
			window.addEventListener("keypress", function(event) {
				if (event.keyCode == 13) {
					event.preventDefault();
				}
			}, false);
			load(1);
		});

		function crear_cotizacion() {
			document.querySelector("#titleModalCotizacionPublicidad").innerHTML = "<i class='glyphicon glyphicon-ok'></i> Nueva Cotización";
			document.querySelector("#guardar_cotizacion_publicidad").reset();
			document.querySelector("#id_cotizacion_publicidad").value = "";
			document.querySelector("#version_cotizacion").value = "1";
			document.querySelector("#btnActionFormCotizacionPublidad").classList.replace("btn-info", "btn-primary");
			document.querySelector("#btnTextCotizacionPublicidad").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Guardar";
			document.querySelector('#btnActionFormCotizacionPublidad').title = "Guardar";
			//genera_numero_version();
			//cuando es nuva cotizacion se rsetean las variables de sesion
			$.ajax({
				url: "../ajax/cotizacion_publicidad.php?action=iniciar_formulario",
				beforeSend: function(objeto) {
					$("#vista_detalle_cotizacion").html("Iniciando...");
				},
				success: function(data) {
					$('#vista_detalle_cotizacion').html(data);
				}
			});

		}


		function load(page) {
			var por = $("#por").val();
			var ordenado = $("#ordenado").val();
			var q = $("#q").val();
			var estado = $("#estado").val();
			var anio = $("#anio").val();
			var mes = $("#mes").val();
			var dia = $("#dia").val();
			$("#loader").fadeIn('slow');
			$.ajax({
				url: '../ajax/cotizacion_publicidad.php?action=buscar_cotizaciones&page=' + page + '&q=' + q + "&por=" + por +
					"&ordenado=" + ordenado + '&estado=' + estado + '&anio=' + anio + '&mes=' + mes + '&dia=' + dia,
				beforeSend: function(objeto) {
					$('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
				},
				success: function(data) {
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');
				}
			});

		}

		function ordenar(ordenado) {
			$("#ordenado").val(ordenado);
			var por = $("#por").val();
			var q = $("#q").val();
			var ordenado = $("#ordenado").val();
			$("#loader").fadeIn('slow');
			var value_por = document.getElementById('por').value;
			if (value_por == "asc") {
				$("#por").val("desc");
			}
			if (value_por == "desc") {
				$("#por").val("asc");
			}
			load(1);
		}

		function eliminar(id) {
			var q = $("#q").val();
			var serie = $("#serie" + id).val();
			var secuencial = $("#secuencial" + id).val();

			if (confirm("Realmente desea eliminar la factura " + serie + "-" + secuencial + " ?")) {
				$.ajax({
					type: "POST",
					url: "../ajax/facturas.php?action=eliminar",
					data: "id=" + id,
					"q": q,
					beforeSend: function(objeto) {
						$("#loader").html("Actualizando...");
					},
					success: function(datos) {
						$("#resultados").html(datos);
						$("#loader").html("");
						load(1);
					}
				})
			}
		}


		function editar_cotizacion(id) {
			document.querySelector('#titleModalCotizacionPublicidad').innerHTML = "<i class='glyphicon glyphicon-edit'></i> Actualizar cotización";
			document.querySelector("#guardar_cotizacion_publicidad").reset();
			document.querySelector("#id_cotizacion_publicidad").value = id;
			document.querySelector('#btnActionFormCotizacionPublidad').classList.replace("btn-primary", "btn-info");
			document.querySelector("#btnTextCotizacionPublicidad").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Actualizar";
			//para traer el encabezado
			let request = (window.XMLHttpRequest) ?
				new XMLHttpRequest() :
				new ActiveXObject('Microsoft.XMLHTTP');
			let ajaxUrl = '../ajax/cotizacion_publicidad.php?action=informacion_editar_cotizacion&id=' + id;
			request.open("GET", ajaxUrl, true);
			request.send();
			request.onreadystatechange = function() {
				if (request.readyState == 4 && request.status == 200) {
					let objData = JSON.parse(request.responseText);
					if (objData.status) {
						let objEncabezado = objData.data;
						document.querySelector("#id_cliente_cotizacion").value = objEncabezado.id_cliente;
						document.querySelector("#nombre_empresa").value = objEncabezado.nombre_cliente;
						document.querySelector("#nombre_proyecto").value = objEncabezado.proyecto;
						document.querySelector("#contacto_empresa").value = objEncabezado.contacto;
						document.querySelector("#consecutivo_numero_cotizacion").value = objEncabezado.numero;
						document.querySelector("#id_vendedor").value = objEncabezado.ejecutivo;
						document.querySelector("#fecha_cotizacion").value = objEncabezado.fecha;
						document.querySelector("#version_cotizacion").value = objEncabezado.version;
						document.querySelector("#presupuesto").value = objEncabezado.presupuesto;
						document.querySelector("#observaciones").value = objEncabezado.observaciones;
						document.querySelector("#tipo_iva").value = objEncabezado.tipo_iva;
						document.querySelector("#comision").value = objEncabezado.comision;
						var numeroFormateado = String(objEncabezado.numero).padStart(3, "0");
						var formatoCotizacion = numeroFormateado + '-' + objEncabezado.fecha.split("-")[2];;
						$("#numero_cotizacion").val(formatoCotizacion);
					} else {
						$.notify(objData.msg, "error");
					}
				}
				return false;
			}

			$.ajax({
				url: "../ajax/cotizacion_publicidad.php?action=iniciar_formulario_editar&id=" + id,
				beforeSend: function(objeto) {
					$("#vista_detalle_cotizacion").html("Iniciando...");
				},
				success: function(data) {
					$('#vista_detalle_cotizacion').html(data);
				}
			});
		}


		function version_cotizacion(id) {
			document.querySelector('#titleModalCotizacionPublicidad').innerHTML = "<i class='glyphicon glyphicon-edit'></i> Nueva versión de cotización";
			document.querySelector("#guardar_cotizacion_publicidad").reset();
			document.querySelector("#id_cotizacion_publicidad").value = "";
			document.querySelector('#btnActionFormCotizacionPublidad').classList.replace("btn-primary", "btn-info");
			document.querySelector("#btnTextCotizacionPublicidad").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Guardar";
			//para traer el encabezado
			let request = (window.XMLHttpRequest) ?
				new XMLHttpRequest() :
				new ActiveXObject('Microsoft.XMLHTTP');
			let ajaxUrl = '../ajax/cotizacion_publicidad.php?action=informacion_editar_cotizacion&id=' + id;
			request.open("GET", ajaxUrl, true);
			request.send();
			request.onreadystatechange = function() {
				if (request.readyState == 4 && request.status == 200) {
					let objData = JSON.parse(request.responseText);
					if (objData.status) {
						let objEncabezado = objData.data;
						document.querySelector("#id_cliente_cotizacion").value = objEncabezado.id_cliente;
						document.querySelector("#nombre_empresa").value = objEncabezado.nombre_cliente;
						document.querySelector("#nombre_proyecto").value = objEncabezado.proyecto;
						document.querySelector("#contacto_empresa").value = objEncabezado.contacto;
						document.querySelector("#consecutivo_numero_cotizacion").value = objEncabezado.numero;
						document.querySelector("#id_vendedor").value = objEncabezado.ejecutivo;
						document.querySelector("#fecha_cotizacion").value = objEncabezado.fecha;
						document.querySelector("#presupuesto").value = objEncabezado.presupuesto;
						document.querySelector("#observaciones").value = objEncabezado.observaciones;
						document.querySelector("#tipo_iva").value = objEncabezado.tipo_iva;
						document.querySelector("#comision").value = objEncabezado.comision;
						var numeroFormateado = String(objEncabezado.numero).padStart(3, "0");
						var formatoCotizacion = numeroFormateado + '-' + objEncabezado.fecha.split("-")[2];;
						$("#numero_cotizacion").val(formatoCotizacion);
						genera_numero_version();
					} else {
						$.notify(objData.msg, "error");
					}
				}
				return false;
			}

			$.ajax({
				url: "../ajax/cotizacion_publicidad.php?action=iniciar_formulario_editar&id=" + id,
				beforeSend: function(objeto) {
					$("#vista_detalle_cotizacion").html("Iniciando...");
				},
				success: function(data) {
					$('#vista_detalle_cotizacion').html(data);
				}
			});
		}

		function buscar_clientes() {
			$("#nombre_empresa").autocomplete({
				appendTo: "#cotizacionPublicidad",
				source: '../ajax/clientes_autocompletar.php',
				minLength: 2,
				select: function(event, ui) {
					event.preventDefault();
					$('#id_cliente_cotizacion').val(ui.item.id);
					$('#nombre_empresa').val(ui.item.nombre);
					genera_numero_cotizacion()
					document.getElementById('contacto_empresa').focus();
				}
			});

			$("#nombre_empresa").on("keydown", function(event) {
				if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_cliente_cotizacion").val("");
					$("#nombre_empresa").val("");
				}
				if (event.keyCode == $.ui.keyCode.DELETE) {
					$("#nombre_empresa").val("");
					$("#id_cliente_cotizacion").val("");
				}
			});
		}


		function costos_cotizacion(id) {
			$.ajax({
				url: "../ajax/cotizacion_publicidad.php?action=iniciar_formulario_costos&id=" + id,
				beforeSend: function(objeto) {
					$("#vista_detalle_costos_cotizacion").html("Iniciando...");
				},
				success: function(data) {
					$('#vista_detalle_costos_cotizacion').html(data);
				}
			});

		}


		//cuando cambia la fecha o el cliente
		function genera_numero_cotizacion() {
			var fecha = $("#fecha_cotizacion").val();
			var anio = fecha.split("-")[2];
			var id_cliente = $("#id_cliente_cotizacion").val();
			var version = $("#version_cotizacion").val();
			$.ajax({
				type: "GET",
				url: "../ajax/cotizacion_publicidad.php?action=genera_numero_cotizacion",
				data: "anio=" + anio + "&id_cliente=" + id_cliente + "&version=" + version,
				beforeSend: function(objeto) {
					$("#resultados_ajax_cotizacion_publicidad").html("Generando número...");
				},
				success: function(datos) {
					$("#resultados_ajax_cotizacion_publicidad").html('');
					$("#consecutivo_numero_cotizacion").val(datos);
					var numero = $("#consecutivo_numero_cotizacion").val();
					var numeroFormateado = String(numero).padStart(3, "0");
					var formatoCotizacion = numeroFormateado + '-' + anio;
					$("#numero_cotizacion").val(formatoCotizacion);
					genera_numero_version();
				}
			});
		}

		function genera_numero_version() {
			var fecha = $("#fecha_cotizacion").val();
			var anio = fecha.split("-")[2];
			var id_cliente = $("#id_cliente_cotizacion").val();
			var numero_cotizacion = $("#consecutivo_numero_cotizacion").val();
			$.ajax({
				type: "GET",
				url: "../ajax/cotizacion_publicidad.php?action=genera_numero_version",
				data: "anio=" + anio + "&id_cliente=" + id_cliente + "&numero_cotizacion=" + numero_cotizacion,
				beforeSend: function(objeto) {
					$("#resultados_ajax_cotizacion_publicidad").html("Generando número...");
				},
				success: function(datos) {
					$("#resultados_ajax_cotizacion_publicidad").html('');
					$("#version_cotizacion").val(datos);
				}
			});
		}


		function facturar_cotizacion(id) {
			var serie_factura = $("#serie_factura").val();
			document.querySelector('#titleModalFacturarCotizacionPublicidad').innerHTML = "<i class='glyphicon glyphicon-edit'></i> Facturar cotización";
			document.querySelector("#guardar_factura_cotizacion_publicidad").reset();
			document.querySelector("#id_facturar_cotizacion_publicidad").value = id;
			document.querySelector('#btnActionFormFacturarCotizacionPublidad').classList.replace("btn-primary", "btn-info");
			document.querySelector("#btnTextFacturarCotizacionPublicidad").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Facturar";
			$('#btnActionFormFacturarCotizacionPublidad').attr("disabled", true);
			//para traer el encabezado
			let request = (window.XMLHttpRequest) ?
				new XMLHttpRequest() :
				new ActiveXObject('Microsoft.XMLHTTP');
			let ajaxUrl = '../ajax/cotizacion_publicidad.php?action=informacion_facturar_cotizacion&id=' + id + "&serie_factura=" + serie_factura;
			request.open("GET", ajaxUrl, true);
			request.send();
			request.onreadystatechange = function() {
				if (request.readyState == 4 && request.status == 200) {
					let objData = JSON.parse(request.responseText);
					if (objData.status) {
						let objEncabezado = objData.data;
						document.querySelector("#id_facturar_cotizacion_publicidad").value = objEncabezado.id;
						document.querySelector("#id_cliente_cotizacion_publicidad").value = objEncabezado.id_cliente;
						document.querySelector("#fecha_factura").value = objEncabezado.fecha_factura;
						document.querySelector("#serie_factura").value = objEncabezado.serie_factura;
						document.querySelector("#numero_factura").value = objEncabezado.numero_factura;
						document.querySelector("#estado_factura").value = objEncabezado.estado_factura;
						document.querySelector("#codigo_servicio_factura").value = objEncabezado.codigo_servicio;
						document.querySelector("#nombre_servicio_factura").value = objEncabezado.descripcion_servicio;
						document.querySelector("#cantidad_factura").value = objEncabezado.cantidad_factura;
						document.querySelector("#precio_factura").value = objEncabezado.precio_factura;
						document.querySelector("#subtotal_factura").value = objEncabezado.subtotal_factura;
						document.querySelector("#iva_factura").value = objEncabezado.iva_factura;
						document.querySelector("#total_factura").value = objEncabezado.total_factura;
						document.querySelector("#id_iva_cotizacion_publicidad").value = objEncabezado.id_iva;
						document.querySelector("#numero_cotizacion_publicidad").value = objEncabezado.numero_cotizacion_publicidad
						$('#btnActionFormFacturarCotizacionPublidad').attr("disabled", false);
					} else {
						$.notify(objData.msg, "error");
					}
				}
				return false;
			}

		}

		function anular_cotizacion(id) {
			if (confirm("Realmente desea eliminar el registro?")) {
				$.ajax({
					type: "POST",
					url: "../ajax/cotizacion_publicidad.php?action=anular_cotizacion",
					data: "id=" + id,
					beforeSend: function(objeto) {
						$("#resultados").html("Anulando...");
					},
					success: function(datos) {
						$("#resultados").html(datos);
						load(1);
					}
				});
			}
		}
	</script>