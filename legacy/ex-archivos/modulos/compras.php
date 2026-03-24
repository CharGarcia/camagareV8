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
	<html lang="en">

	<head>
		<meta charset="utf-8">
		<title>Adquisiciones</title>
		<?php
		include("../paginas/menu_de_empresas.php");
		include("../modal/detalle_documento.php");
		include("../modal/cobro_pago_directo.php");
		include("../modal/enviar_documentos_mail.php");
		?>
	</head>

	<body>

		<div class="container-fluid">
			<div class="panel panel-info">
				<div class="panel-heading">
					<div class="btn-group pull-right">
						<form method="post" action="../modulos/nuevo_registro_compras.php">
							<?php
							if (getPermisos($con, $id_usuario, $ruc_empresa, 'compras')['w'] == 1) {
							?>
								<button type='submit' class="btn btn-info"><span class="glyphicon glyphicon-plus"></span> Nueva compra</button>
							<?php
							}
							?>
						</form>
					</div>
					<h4><i class='glyphicon glyphicon-search'></i> Compras</h4>
				</div>
				<ul class="nav nav-tabs nav-justified">
					<li class="active"><a data-toggle="tab" onclick='load(1);' href="#compras">Compras</a></li>
					<li><a data-toggle="tab" href="#detalle_compras">Detalle de compras</a></li>
				</ul>
				<div class="tab-content">
					<div id="compras" class="tab-pane fade in active">
						<div class="panel-body">
							<form class="form-horizontal" role="form">
								<div class="form-group row">
									<div class="col-md-2">
										<div class="input-group">
											<span class="input-group-addon"><b>Tipo</b></span>
											<select class="form-control input-sm" id="id_comprobante" name="id_comprobante" onchange='load(1);'>
												<option value="" selected>Todos</option>
												<?php
												$tipo = mysqli_query($con, "SELECT distinct com.id_comprobante as id_comprobante, doc.comprobante as comprobante 
												FROM encabezado_compra as com INNER JOIN comprobantes_autorizados as doc
												ON doc.id_comprobante=com.id_comprobante WHERE com.ruc_empresa ='" . $ruc_empresa . "' order by doc.comprobante asc");
												while ($row = mysqli_fetch_array($tipo)) {
												?>
													<option value="<?php echo $row['id_comprobante'] ?>"><?php echo strtoupper($row['comprobante']) ?></option>
												<?php
												}
												?>
											</select>
										</div>
									</div>
									<div class="col-md-2">
										<div class="input-group">
											<span class="input-group-addon"><b>Año</b></span>
											<?php
											$currentYear = date("Y");
											?>
											<select class="form-control input-sm" id="anio_compra" name="anio_compra" onchange='load(1);'>
												<option value="" selected>Todos</option>
												<?php
												$existsCurrentYear = false;
												$tipo = mysqli_query($con, "SELECT distinct(year(fecha_compra)) as anio FROM encabezado_compra WHERE ruc_empresa ='" . $ruc_empresa . "' order by year(fecha_compra) desc");
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
											<select class="form-control input-sm" id="mes_compra" name="mes_compra" onchange='load(1);'>
												<option value="" selected>Todos</option>
												<?php
												$tipo = mysqli_query($con, "SELECT distinct(DATE_FORMAT(fecha_compra, '%m')) as mes FROM encabezado_compra WHERE ruc_empresa ='" . $ruc_empresa . "' order by month(fecha_compra) asc");
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
											<select class="form-control input-sm" id="dia_compra" name="dia_compra" onchange='load(1);'>
												<option value="" selected>Todos</option>
												<?php
												$tipo = mysqli_query($con, "SELECT distinct(DATE_FORMAT(fecha_compra, '%d')) as dia FROM encabezado_compra WHERE ruc_empresa ='" . $ruc_empresa . "' order by day(fecha_compra) asc");
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
										<input type="hidden" id="ordenado" value="fecha_compra">
										<input type="hidden" id="por" value="desc">
										<div class="input-group">
											<span class="input-group-addon"><b>Buscar:</b></span>
											<input type="text" class="form-control input-sm" id="q" placeholder="Proveedor, serie, factura, fecha, ruc, estado" onkeyup='load(1);'>
											<span class="input-group-btn">
												<button type="button" class="btn btn-default input-sm" onclick='load(1);'><span class="glyphicon glyphicon-search"></span></button>
											</span>
										</div>
									</div>
									<span id="loader"></span>
								</div>
							</form>
							<div id="resultados"></div><!-- Carga los datos ajax -->
							<div class="outer_div"></div><!-- Carga los datos ajax -->
						</div>
					</div>

					<div id="detalle_compras" class="tab-pane fade">
						<div class="panel-body">
							<form class="form-horizontal" role="form">
								<div class="form-group row">
									<label for="d" class="col-md-1 control-label">Buscar:</label>
									<div class="col-md-5">
										<input type="hidden" id="ordenado_det" value="detalle_producto">
										<input type="hidden" id="por_det" value="asc">
										<div class="input-group">
											<input type="text" class="form-control" id="d" placeholder="Productos, servicios, código" onkeyup='load(1);'>
											<span class="input-group-btn">
												<button type="button" class="btn btn-default" onclick='load(1);'><span class="glyphicon glyphicon-search"></span> Buscar</button>
											</span>
										</div>
									</div>
									<span id="loader_detalles"></span>
								</div>
							</form>
							<div id="resultados_detalles_compras"></div>
							<div class="outer_div_detalles"></div>
						</div>
					</div>
				</div>
			</div>
		</div>

	<?php
} else {
	header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/sistema') . '/empresa');
	exit;
}
	?>
	<script type="text/javascript" src="../js/style_bootstrap.js"> </script>
	<link rel="stylesheet" href="../css/jquery-ui.css">
	<script src="../js/jquery-ui.js"></script>
	<script src="../js/notify.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>

	</body>
	<style type="text/css">
		ul.ui-autocomplete {
			z-index: 1100;
		}
	</style>

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

		function load(page) {
			var por = $("#por").val();
			var ordenado = $("#ordenado").val();
			var q = $("#q").val();
			var d = $("#d").val();
			var por_det = $("#por_det").val();
			var ordenado_det = $("#ordenado_det").val();
			var id_comprobante = $("#id_comprobante").val();
			var anio_compra = $("#anio_compra").val();
			var mes_compra = $("#mes_compra").val();
			var dia_compra = $("#dia_compra").val();

			$("#loader").fadeIn('slow');
			$.ajax({
				url: '../ajax/buscar_compras.php?action=compras&page=' + page + '&q=' + q + "&por=" + por +
					"&ordenado=" + ordenado + '&id_comprobante=' + id_comprobante + '&anio_compra=' + anio_compra + '&mes_compra=' + mes_compra + '&dia_compra=' + dia_compra,
				beforeSend: function(objeto) {
					$('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
				},
				success: function(data) {
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');
				}
			});

			$("#loader_detalles").fadeIn('slow');
			$.ajax({
				url: '../ajax/buscar_detalle_compras.php?action=detalle_compras&page=' + page + '&d=' + d + "&por=" + por_det + "&ordenado=" + ordenado_det,
				beforeSend: function(objeto) {
					$('#loader_detalles').html('<img src="../image/ajax-loader.gif"> Cargando...');
				},
				success: function(data) {
					$(".outer_div_detalles").html(data).fadeIn('slow');
					$('#loader_detalles').html('');
				}
			});

		}

		function ordenar(ordenado) {
			$("#ordenado").val(ordenado);
			var por = $("#por").val();
			var ordenado = $("#ordenado").val();
			var q = $("#q").val();
			var p = $("#p").val();
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

		function eliminar_registro(id) {
			var q = $("#q").val();
			var codigo_documento = $("#codigo_documento" + id).val();
			var proveedor_documento = $("#proveedor_documento" + id).val();
			var numero_documento = $("#numero_documento" + id).val();

			if (confirm("Realmente desea eliminar el registro de " + proveedor_documento + " N." + numero_documento + " ?")) {
				$.ajax({
					type: "POST",
					url: "../ajax/buscar_compras.php",
					data: "action=eliminar_compra&codigo_documento=" + codigo_documento,
					"q": q,
					beforeSend: function(objeto) {
						$("#loader").html("Cargando...");
					},
					success: function(datos) {
						$("#loader").html(datos);
						load(1);
					}
				});
			}

		}

		function detalle_factura_compra(codigo) {
			$("#loaderdet").fadeIn('slow');
			$.ajax({
				url: '../ajax/detalle_documento.php?action=compras&codigo=' + codigo,
				beforeSend: function(objeto) {
					$('#loaderdet').html('<img src="../image/ajax-loader.gif"> Cargando detalles...');
				},
				success: function(data) {
					$(".outer_divdet").html(data).fadeIn('slow');
					$('#loaderdet').html('');
				}
			});

		}


		//detalle de retenciones de compra
		function detalle_retencion_compra(id) {
			$("#outer_divdet").fadeIn('slow');
			var id_encabezado_compra = $("#id_documento" + id).val();
			$.ajax({
				url: '../ajax/detalle_documento.php?action=detalle_retenciones_compras&id_encabezado_compra=' + id_encabezado_compra,
				beforeSend: function(objeto) {
					$('#outer_divdet').html('<img src="../image/ajax-loader.gif"> Cargando detalle retenciones...');
				},
				success: function(data) {
					$(".outer_divdet").html(data).fadeIn('slow');
					$('#outer_divdet').html('');
				}
			});
		}


		function carga_modal_registrar_pago(id, valor, proveedor, numero_documento, fecha) {
			document.querySelector("#detalle_pago_compra").reset();
			$(".outer_divPagoCompra").html('').fadeIn('fast');
			$("#id_FacturaCompra").val(id);
			$("#valor_pago_egreso").val(valor);
			$("#porpagar_FacturaCompra").val(valor);
			document.querySelector("#datos_pago_compra").innerHTML = 'Fecha: ' + fecha + ' </br>Proveedor: ' + proveedor + ' </br>Documento: ' + numero_documento + ' </br>Saldo por pagar: ' + valor;
			$.ajax({
				url: "../ajax/buscar_compras.php?action=nuevo_pago_compra",
				beforeSend: function(objeto) {
					$("#loaderCobroFacturaCompra").html("Cargando...");
				},
				success: function(data) {
					$('#loaderCobroFacturaCompra').html('');
				}
			});
		}


		//agregar pagos	
		function agrega_pagos_egreso() {
			var forma_pago_egreso = $("#forma_pago_egreso").val();
			var valor_pago_egreso = $("#valor_pago_egreso").val();
			var numero_cheque_egreso = $("#numero_cheque_egreso").val();
			var fecha_cobro_egreso = $("#fecha_cobro_egreso").val();
			var tipo = $("#tipo_egreso").val();

			//Inicia validacion
			if ((forma_pago_egreso == 0)) {
				alert('Seleccione forma de pago');
				document.getElementById('forma_pago_egreso').focus();
				return false;
			}

			//origen es para ver de que tabla me esta trayendo el dato, para segubn eso mostrar deposito o transferencia
			var origen = forma_pago_egreso.substring(0, 1);

			if (origen == 1 && tipo != '0') {
				document.getElementById("tipo_egreso").value = "0";
				document.getElementById('valor_pago_egreso').focus();
				return false;
			}

			if (origen == 2 && tipo == '0') {
				alert('Seleccione cheque, débito o transferencia.');
				document.getElementById('tipo_egreso').focus();
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
				url: "../ajax/buscar_compras.php?action=forma_de_pago_egreso",
				data: "forma_pago_egreso=" + forma_pago + "&valor_pago_egreso=" + valor_pago_egreso + "&numero_cheque_egreso=" + numero_cheque_egreso + "&fecha_cobro_egreso=" + fecha_cobro_egreso + "&origen=" + origen + "&tipo=" + tipo,
				beforeSend: function(objeto) {
					$("#loaderCobroFacturaCompra").html("Cargando...");
				},
				success: function(datos) {
					$(".outer_divPagoCompra").html(datos).fadeIn('fast');
					$("#loaderCobroFacturaCompra").html('');
					document.getElementById("forma_pago_egreso").value = "0";
					document.getElementById("tipo_egreso").value = "0";
					document.getElementById("valor_pago_egreso").value = "";
				}
			});
			event.preventDefault();
		}


		//eliminar iten del egreso de forma de pago
		function eliminar_fila_pago_egreso(id) {
			$.ajax({
				url: "../ajax/buscar_compras.php?action=eliminar_pago_egreso&id_fp_tmp=" + id,
				beforeSend: function(objeto) {
					$("#loaderCobroFacturaCompra").html("Eliminando...");
				},
				success: function(datos) {
					$(".outer_divPagoCompra").html(datos).fadeIn('fast');
					$('#loaderCobroFacturaCompra').html('');
				}
			});
			event.preventDefault();
		}

		function guarda_pago_compra() {
			$('#btnActionFormPagoCompra').attr("disabled", true);
			var id_compra = $("#id_FacturaCompra").val();
			var fecha_egreso = $("#fecha_egreso").val();
			var saldo = $("#porpagar_FacturaCompra").val();
			var nota = $("#nota_compra").val();
			$.ajax({
				type: "POST",
				url: "../ajax/buscar_compras.php?action=guardar_pago_compra",
				data: "id_compra=" + id_compra + "&fecha_egreso=" + fecha_egreso + "&saldo=" + saldo + "&nota=" + nota,
				beforeSend: function(objeto) {
					$("#loaderCobroFacturaCompra").html("Guardando...");
				},
				success: function(datos) {
					$("#loaderCobroFacturaCompra").html(datos);
					$('#btnActionFormPagoCompra').attr("disabled", false);
				}
			});
			event.preventDefault();
		}



		function buscar_productos() {
			//para usar el lector de barras
			var keycode = event.keyCode;
			var codigo_producto = $("#editar_detalle_compra").val();
			if (keycode == '13') {
				let request = (window.XMLHttpRequest) ?
					new XMLHttpRequest() :
					new ActiveXObject('Microsoft.XMLHTTP');
				let ajaxUrl = '../ajax/buscar_orden_mecanica.php?action=bar_code&codigo_producto=' + codigo_producto;
				request.open("GET", ajaxUrl, true);
				request.send();
				request.onreadystatechange = function() {
					if (request.readyState == 4 && request.status == 200) {
						let objData = JSON.parse(request.responseText);
						if (objData.status) {
							let objProducto = objData;
							document.querySelector("#editar_detalle_compra").value = objProducto.nombre_producto;
							document.querySelector("#editar_cantidad_compra").value = 1;
							document.getElementById('editar_cantidad_compra').focus();
						} else {
							$.notify(objData.msg, "error");
						}
					}
					return false;
				}
			}

			$("#editar_detalle_compra").autocomplete({
				source: '../ajax/productos_autocompletar.php',
				minLength: 2,
				select: function(event, ui) {
					event.preventDefault();
					$('#editar_detalle_compra').val(ui.item.nombre);
					$('#editar_codigo_compra').val(ui.item.codigo);
					$('#editar_val_uni_compra').val(ui.item.precio);
					//hasta aqui me controla si trabaja con inventario
					document.getElementById('editar_cantidad_compra').focus();
				}

			});
			$("#editar_detalle_compra").on("keydown", function(event) {
				if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
					$("#editar_detalle_compra").val("");
					$("#editar_codigo_compra").val("");
					$("#editar_cantidad_compra").val("");
					$("#editar_cantidad_compra").val("");
				}
			});
		}

		function revisar_notificaciones_adquisiciones(codigo) {
			$("#loaderdet").fadeIn('slow');
			$.ajax({
				url: '../ajax/detalle_documento.php?action=revisar_notificaciones_adquisiciones&codigo=' + codigo,
				beforeSend: function(objeto) {
					$('#loaderdet').html('<img src="../image/ajax-loader.gif"> Cargando detalles...');
				},
				success: function(data) {
					$(".outer_divdet").html(data).fadeIn('slow');
					$('#loaderdet').html('');
				}
			});

		}

		function agregar_respuesta(id, codigo_documento) {
			var respuesta = prompt("Ingrese respuesta:");
			if ((respuesta)) {
				$.ajax({
					type: "POST",
					url: "../ajax/detalle_documento.php",
					data: "action=agregar_respuesta_compra&id=" + id + "&respuesta=" + respuesta,
					beforeSend: function(objeto) {
						$("#outer_divdet").html("Agregando...");
					},
					success: function(datos) {
						$(".outer_divdet").html(datos).fadeIn('fast');
						$('#outer_divdet').html('');
						revisar_notificaciones_adquisiciones(codigo_documento);
						load(1);
					}
				})
			}
		}

		function enviar_respuesta_mail(id, correo) {
			$("#id_documento").val(id); //uso el id del mismo formulario para no crear un nuevo modal
			$("#mail_receptor").val(correo);
			$("#tipo_documento").val("respuesta_compra");
		};

		function eliminar_respuesta(id, codigo_documento) {
			if (confirm("Realmente desea eliminar la respuesta?")) {
				$.ajax({
					type: "POST",
					url: "../ajax/detalle_documento.php",
					data: "action=eliminar_respuesta_compra&id=" + id,
					beforeSend: function(objeto) {
						$("#outer_divdet").html("Agregando...");
					},
					success: function(datos) {
						$(".outer_divdet").html(datos).fadeIn('fast');
						$('#outer_divdet').html('');
						revisar_notificaciones_adquisiciones(codigo_documento);
						load(1);
					}
				});
			}
		};


		function generar_pdf_adquisicion(documento, id) {
			var id_documento_sri = id;
			var tipo_documento_sri = documento;
			//var pagina = $("#pagina").val();
			$.ajax({
				type: 'POST',
				url: '../facturacion_electronica/enviarComprobantesSri.php',
				data: 'tipo_documento_sri=' + tipo_documento_sri + '&id_documento_sri=' + id_documento_sri + '&modo_envio=offline',
				beforeSend: function(objeto) {
					$.notify('Generando pdf espere por favor...', 'warning');
				},
				success: function(datos) {
					$("#resultados").html(datos);
					//load(pagina);
					window.location.href = '../ajax/imprime_documento.php?id_documento=' + btoa(id) + '&tipo_documento=' + tipo_documento_sri + '&tipo_archivo=pdf';
				}
			});
			event.preventDefault();
		};
	</script>