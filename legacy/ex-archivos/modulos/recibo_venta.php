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
		<title>Recibo de venta</title>
		<?php include("../paginas/menu_de_empresas.php");
		include("../modal/detalle_documento.php");
		include("../modal/cobro_pago_directo.php");
		include("../modal/recibo_venta.php");
		include("../modal/enviar_documentos_mail.php");
		unset($_SESSION['arrayFormaPagoIngresorecibo']);
		?>

	</head>

	<body>

		<div class="container-fluid">
			<div class="panel panel-info">
				<div class="panel-heading">
					<div class="btn-group pull-right">
						<?php
						if (getPermisos($con, $id_usuario, $ruc_empresa, 'recibo_venta')['w'] == 1) {
						?>
							<button type="submit" class="btn btn-info" data-toggle="modal" data-target="#recibo" onclick="crear_recibo();" title="Nueva recibo de venta"><span class="glyphicon glyphicon-plus"></span> Nuevo recibo</button>
						<?php
						}
						?>

					</div>
					<h4><i class="glyphicon glyphicon-search"></i> Recibos de venta</h4>
				</div>

				<ul class="nav nav-tabs nav-justified">
					<li class="active"><a data-toggle="tab" href="#recibos">Recibos</a></li>
					<li><a data-toggle="tab" href="#detalle_recibos">Productos y servicios</a></li>
					<li><a data-toggle="tab" href="#detalle_adicionales">Adicionales</a></li>
				</ul>
				<div class="tab-content">
					<div id="recibos" class="tab-pane fade in active">
						<div class="panel-body">
							<form class="form-horizontal" role="form">
								<div class="form-group row">
									<div class="col-md-2">
										<div class="input-group">
											<span class="input-group-addon"><b>Estado</b></span>
											<select class="form-control input-sm" id="estado" name="estado" onchange='load(1);'>
												<option value="" selected>Todos</option>
												<?php
												$tipo = mysqli_query($con, "SELECT distinct status 
												FROM encabezado_recibo WHERE ruc_empresa ='" . $ruc_empresa . "' order by status asc");
												while ($row = mysqli_fetch_array($tipo)) {
												?>
													<option value="<?php echo $row['status']; ?>">
														<?php if ($row['status'] == 1) {
															echo 'Abierto';
														} else if ($row['status'] == 2) {
															echo 'Anulado';
														} else if ($row['status'] == 3) {
															echo 'Cerrado';
														} ?></option>
												<?php
												}
												?>
											</select>
										</div>
									</div>
									<div class="col-md-2">
										<div class="input-group">
											<span class="input-group-addon"><b>Año</b></span>
											<select class="form-control input-sm" id="anio_recibo" name="anio_recibo" onchange='load(1);'>
												<option value="" selected>Todos</option>
												<?php
												$tipo = mysqli_query($con, "SELECT distinct(year(fecha_recibo)) as anio FROM encabezado_recibo WHERE ruc_empresa ='" . $ruc_empresa . "' order by year(fecha_recibo) desc");
												while ($row = mysqli_fetch_array($tipo)) {
												?>
													<option value="<?php echo $row['anio'] ?>"><?php echo strtoupper($row['anio']) ?></option>
												<?php
												}
												?>
											</select>
										</div>
									</div>
									<div class="col-md-2">
										<div class="input-group">
											<span class="input-group-addon"><b>Mes</b></span>
											<select class="form-control input-sm" id="mes_recibo" name="mes_recibo" onchange='load(1);'>
												<option value="" selected>Todos</option>
												<?php
												$tipo = mysqli_query($con, "SELECT distinct(DATE_FORMAT(fecha_recibo, '%m')) as mes FROM encabezado_recibo WHERE ruc_empresa ='" . $ruc_empresa . "' order by month(fecha_recibo) asc");
												while ($row = mysqli_fetch_array($tipo)) {
												?>
													<option value="<?php echo $row['mes'] ?>"><?php echo strtoupper($row['mes']) ?></option>
												<?php
												}
												?>
											</select>
										</div>
									</div>
									<div class="col-md-5">
										<input type="hidden" id="ordenado" value="id_encabezado_recibo">
										<input type="hidden" id="por" value="desc">
										<div class="input-group">
											<span class="input-group-addon"><b>Buscar:</b></span>
											<input type="text" class="form-control input-sm" id="q" placeholder="Cliente, serie, recibo, fecha, ruc, estado" onkeyup='load(1);'>
											<span class="input-group-btn">
												<button type="button" class="btn btn-default input-sm" onclick='load(1);'><span class="glyphicon glyphicon-search"></span> Buscar</button>
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
					<div id="detalle_recibos" class="tab-pane fade">
						<div class="panel-body">
							<form class="form-horizontal" role="form">
								<div class="form-group row">
									<label for="d" class="col-md-1 control-label">Buscar:</label>
									<div class="col-md-5">
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
							<div id="resultados_detalles_recibos"></div><!-- Carga los datos ajax -->
							<div class='outer_div_detalles'></div><!-- Carga los datos ajax -->
						</div>
					</div>
					<div id="detalle_adicionales" class="tab-pane fade">
						<div class="panel-body">
							<form class="form-horizontal" role="form">
								<div class="form-group row">
									<label for="d" class="col-md-1 control-label">Buscar:</label>
									<div class="col-md-5">
										<div class="input-group">
											<input type="text" class="form-control" id="a" placeholder="Detalle adicionales" onkeyup='load(1);'>
											<span class="input-group-btn">
												<button type="button" class="btn btn-default" onclick='load(1);'><span class="glyphicon glyphicon-search"></span> Buscar</button>
											</span>
										</div>
									</div>
									<span id="loader_adicionales"></span>
								</div>
							</form>
							<div id="resultados_detalles_adicionales"></div><!-- Carga los datos ajax -->
							<div class="outer_div_adicionales"></div><!-- Carga los datos ajax -->
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

		function crear_recibo() {
			document.querySelector("#titleModalRecibo").innerHTML = "<i class='glyphicon glyphicon-ok'></i> Nuevo Recibo de Venta";
			document.querySelector("#guardar_recibo").reset();
			document.querySelector("#id_recibo").value = "";
			document.querySelector("#btnActionFormRecibo").classList.replace("btn-info", "btn-primary");
			document.querySelector("#btnTextRecibo").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Guardar";
			document.querySelector('#btnActionFormRecibo').title = "Guardar Recibo";
			document.getElementById("label_bodega_producto_recibo").style.display = "none";
			document.getElementById("label_existencia_producto_recibo").style.display = "none";
			document.getElementById("label_lote_producto_recibo").style.display = "none";
			document.getElementById("label_medida_producto_recibo").style.display = "none";
			document.getElementById("label_caducidad_producto_recibo").style.display = "none";
			document.getElementById("lista_lote_producto_recibo").style.display = "none";
			document.getElementById("lista_caducidad_producto_recibo").style.display = "none";
			document.getElementById("lista_medida_producto_recibo").style.display = "none";

			//para buscar el numero de recibo que continua cada vez que se hace clic en nueva recibo
			var id_serie = $("#serie_recibo").val();

			$.post('../ajax/buscar_ultimo_recibo.php', {
				serie_recibo: id_serie
			}).done(function(respuesta) {
				var recibo = respuesta;
				$("#secuencial_recibo").val(recibo);
			});

			//para traer el tipo de configuracion de inventarios, si o no
			$.post('../ajax/consulta_configuracion_facturacion.php', {
				opcion_mostrar: 'inventario',
				serie_consultada: id_serie
			}).done(function(respuesta_inventario) {
				var resultado_inventario = $.trim(respuesta_inventario);
				$('#inventario_producto_recibo').val(resultado_inventario);

				if (resultado_inventario == "SI") {
					document.getElementById("label_bodega_producto_recibo").style.display = "";
				}
			});

			//para traer y ver si trabaja con medida
			$.post('../ajax/consulta_configuracion_facturacion.php', {
				opcion_mostrar: 'medida',
				serie_consultada: id_serie
			}).done(function(respuesta_medida) {
				var resultado_medida = $.trim(respuesta_medida);
				$('#muestra_medida_producto_recibo').val(resultado_medida);
			});

			//para traer y ver si trabaja con lote
			$.post('../ajax/consulta_configuracion_facturacion.php', {
				opcion_mostrar: 'lote',
				serie_consultada: id_serie
			}).done(function(respuesta_lote) {
				var resultado_lote = $.trim(respuesta_lote);
				$('#muestra_lote_producto_recibo').val(resultado_lote);
			});

			//para traer y ver si trabaja con bodega
			$.post('../ajax/consulta_configuracion_facturacion.php', {
				opcion_mostrar: 'bodega',
				serie_consultada: id_serie
			}).done(function(respuesta_bodega) {
				var resultado_bodega = $.trim(respuesta_bodega);
				$('#muestra_bodega_producto_recibo').val(resultado_bodega);
			});

			//para traer y ver si trabaja con vencimiento
			$.post('../ajax/consulta_configuracion_facturacion.php', {
				opcion_mostrar: 'vencimiento',
				serie_consultada: id_serie
			}).done(function(respuesta_vencimiento) {
				var resultado_vencimiento = $.trim(respuesta_vencimiento);
				$('#muestra_vencimiento_producto_recibo').val(resultado_vencimiento);
			});

			//para cuando es nueva recibo
			$.ajax({
				url: "../ajax/recibo_venta.php?action=nuevo_recibo",
				beforeSend: function(objeto) {
					$("#detalle_recibo").html("Cargando...");
				},
				success: function(data) {
					$('#detalle_recibo').html('');
					$('#detalle_informacion_adicional').html('');
					$('#detalle_subtotales_recibo').html('');
					//$('#detalle_formas_pago').html('');
				}
			});
		}


		function load(page) {
			var por = $("#por").val();
			var ordenado = $("#ordenado").val();
			var q = $("#q").val();
			var d = $("#d").val();
			var a = $("#a").val();
			var estado = $("#estado").val();
			var anio_recibo = $("#anio_recibo").val();
			var mes_recibo = $("#mes_recibo").val();
			$("#loader").fadeIn('slow');
			$.ajax({
				url: '../ajax/recibo_venta.php?action=buscar_recibos&page=' + page + '&q=' + q + "&por=" + por +
					"&ordenado=" + ordenado + '&estado=' + estado + '&anio_recibo=' + anio_recibo + '&mes_recibo=' + mes_recibo,
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
				url: '../ajax/recibo_venta.php?action=buscar_detalle_recibo&page=' + page + '&d=' + d,
				beforeSend: function(objeto) {
					$('#loader_detalles').html('<img src="../image/ajax-loader.gif"> Cargando...');
				},
				success: function(data) {
					$(".outer_div_detalles").html(data).fadeIn('slow');
					$('#loader_detalles').html('');
				}
			});

			$("#loader_adicionales").fadeIn('slow');
			$.ajax({
				url: '../ajax/recibo_venta.php?action=buscar_detalle_adicionales_recibo&page=' + page + '&a=' + a,
				beforeSend: function(objeto) {
					$('#loader_adicionales').html('<img src="../image/ajax-loader.gif"> Cargando...');
				},
				success: function(data) {
					$(".outer_div_adicionales").html(data).fadeIn('slow');
					$('#loader_adicionales').html('');
				}
			})
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

		function anular_recibo(id) {
			var q = $("#q").val();
			var serie = $("#serie_recibo" + id).val();
			var secuencial = $("#secuencial_recibo" + id).val();

			if (confirm("Realmente desea anular el recibo de venta " + serie + "-" + secuencial + " ?")) {
				$.ajax({
					type: "POST",
					url: "../ajax/recibo_venta.php?action=anular_recibo",
					data: "id_recibo=" + id,
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


		function detalle_recibo(id) {
			$("#loaderdet").fadeIn('slow');
			$.ajax({
				url: '../ajax/detalle_documento.php?action=recibo_venta&id=' + id,
				beforeSend: function(objeto) {
					$('#loaderdet').html('<img src="../image/ajax-loader.gif"> Cargando detalle del recibo de venta...');
				},
				success: function(data) {
					$(".outer_divdet").html(data).fadeIn('slow');
					$('#loaderdet').html('');
				}
			})
		}


		function editar_recibo(id) {
			document.querySelector('#titleModalRecibo').innerHTML = "<i class='glyphicon glyphicon-edit'></i> Actualizar Recibo de venta";
			document.querySelector("#guardar_recibo").reset();
			document.querySelector("#id_recibo").value = id;
			document.querySelector('#btnActionFormRecibo').classList.replace("btn-primary", "btn-info");
			document.querySelector("#btnTextRecibo").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Actualizar";
			document.getElementById("label_bodega_producto_recibo").style.display = "none";
			document.getElementById("label_existencia_producto_recibo").style.display = "none";
			document.getElementById("label_lote_producto_recibo").style.display = "none";
			document.getElementById("label_medida_producto_recibo").style.display = "none";
			document.getElementById("label_caducidad_producto_recibo").style.display = "none";
			document.getElementById("lista_lote_producto_recibo").style.display = "none";
			document.getElementById("lista_caducidad_producto_recibo").style.display = "none";
			document.getElementById("lista_medida_producto_recibo").style.display = "none";
			$('#btnActionFormRecibo').attr("disabled", true);
			//para traer el tipo de configuracion de inventarios, si o no
			var id_cliente = $("#id_cliente" + id).val();
			var nombre_cliente = $("#nombre_cliente" + id).val();
			var fecha_recibo = $("#fecha_recibo" + id).val();
			var id_serie = $("#serie_recibo" + id).val();
			var secuencial_recibo = $("#secuencial_recibo" + id).val();
			var total_recibo = $("#total_recibo" + id).val();

			$("#id_recibo").val(id);
			$("#id_cliente_recibo").val(id_cliente);
			$("#nombre_cliente_recibo").val(nombre_cliente);
			$("#fecha_recibo").val(fecha_recibo);
			$("#serie_recibo").val(id_serie);
			$("#secuencial_recibo").val(secuencial_recibo);
			$("#suma_recibo").val(total_recibo);

			$.post('../ajax/consulta_configuracion_facturacion.php', {
				opcion_mostrar: 'inventario',
				serie_consultada: id_serie
			}).done(function(respuesta_inventario) {
				var resultado_inventario = $.trim(respuesta_inventario);
				$('#inventario_producto_recibo').val(resultado_inventario);

				if (resultado_inventario == "SI") {
					document.getElementById("label_bodega_producto_recibo").style.display = "";
				}
			});

			//para traer y ver si trabaja con medida
			$.post('../ajax/consulta_configuracion_facturacion.php', {
				opcion_mostrar: 'medida',
				serie_consultada: id_serie
			}).done(function(respuesta_medida) {
				var resultado_medida = $.trim(respuesta_medida);
				$('#muestra_medida_producto_recibo').val(resultado_medida);
			});

			//para traer y ver si trabaja con lote
			$.post('../ajax/consulta_configuracion_facturacion.php', {
				opcion_mostrar: 'lote',
				serie_consultada: id_serie
			}).done(function(respuesta_lote) {
				var resultado_lote = $.trim(respuesta_lote);
				$('#muestra_lote_producto_recibo').val(resultado_lote);
			});

			//para traer y ver si trabaja con bodega
			$.post('../ajax/consulta_configuracion_facturacion.php', {
				opcion_mostrar: 'bodega',
				serie_consultada: id_serie
			}).done(function(respuesta_bodega) {
				var resultado_bodega = $.trim(respuesta_bodega);
				$('#muestra_bodega_producto_recibo').val(resultado_bodega);
			});

			//para traer y ver si trabaja con vencimiento
			$.post('../ajax/consulta_configuracion_facturacion.php', {
				opcion_mostrar: 'vencimiento',
				serie_consultada: id_serie
			}).done(function(respuesta_vencimiento) {
				var resultado_vencimiento = $.trim(respuesta_vencimiento);
				$('#muestra_vencimiento_producto_recibo').val(resultado_vencimiento);
			});

			//para cuando editar recibo
			$.ajax({
				type: "POST",
				url: "../ajax/recibo_venta.php?action=editar_recibo",
				data: "id_recibo=" + id + "&serie_recibo=" + id_serie + "&secuencial_recibo=" + secuencial_recibo,
				beforeSend: function(objeto) {
					$("#detalle_recibo").html("Cargando...");
				},
				success: function(data) {
					$('#detalle_recibo').html('Cargando...');
					$('#detalle_informacion_adicional').html('Cargando...');
					$('#detalle_subtotales_recibo').html('Cargando...');
				}
			});

			//esperar 2 segundos para cargar
			setTimeout(function() {
				//para el encabezado de la recibo y la info adicional
				$.ajax({
					url: "../ajax/recibo_venta.php?action=muestra_cliente_adicionales_editar_recibo",
					beforeSend: function(objeto) {
						$("#detalle_informacion_adicional").html("Cargando...");
					},
					success: function(dataAdicional) {
						$('#detalle_informacion_adicional').html(dataAdicional);
					}
				});

				//para mostrar el cuerpo de la recibo
				$.ajax({
					type: "POST",
					url: "../ajax/recibo_venta.php?action=muestra_cuerpo_editar_recibo",
					data: "serie_recibo=" + id_serie,
					beforeSend: function(objeto) {
						$("#detalle_recibo").html("Cargando...");
					},
					success: function(dataCuerpo) {
						$('#detalle_recibo').html(dataCuerpo);
					}
				});

				//para mostrar subtotales de la recibo
				$.ajax({
					type: "POST",
					url: "../ajax/recibo_venta.php?action=muestra_subtotales_editar_recibo",
					data: "serie_recibo=" + id_serie,
					beforeSend: function(objeto) {
						$("#detalle_subtotales_recibo").html("Cargando...");

					},
					success: function(datosSubtotal) {
						$("#detalle_subtotales_recibo").html(datosSubtotal);
						$('#btnActionFormRecibo').attr("disabled", false);
					}
				})

			}, 2000);


		}


		function carga_modal_registrar_pago(id, valor, cliente, numero_recibo, fecha) {
			document.querySelector("#detalle_pago_recibo").reset();
			$(".outer_divCobroReciboVenta").html('').fadeIn('fast');
			$("#id_ReciboVenta").val(id);
			$("#valor_pago_recibo").val(valor);
			$("#porcobrar_ReciboVenta").val(valor);
			document.querySelector("#datos_cobro_recibo").innerHTML = 'Fecha: ' + fecha + ' </br>Cliente: ' + cliente + ' </br>Documento: ' + numero_recibo + ' </br>Saldo por cobrar: ' + valor;
			$.ajax({
				url: "../ajax/recibo_venta.php?action=nuevo_pago_recibo",
				beforeSend: function(objeto) {
					$("#detalle_recibo").html("Cargando...");
				},
				success: function(data) {
					$('#detalle_recibo').html('');
				}
			});
		}

		//agrega una forma de pago
		function agregar_forma_pago() {
			var forma_pago = $("#forma_pago_recibo").val();
			var valor_pago = $("#valor_pago_recibo").val();
			var tipo = $("#tipo_recibo").val();

			//Inicia validacion
			if (forma_pago == '0') {
				alert('Seleccione una forma de pago');
				document.getElementById('forma_pago_recibo').focus();
				return false;
			}

			//origen es para ver de que tabla me esta trayendo el dato, para segubn eso mostrar deposito o transferencia
			var origen = forma_pago.substring(0, 1);

			if (origen == 1 && tipo != '0') {
				document.getElementById("tipo_recibo").value = "0";
				document.getElementById('valor_pago_recibo').focus();
				return false;
			}

			if (origen == 2 && tipo == '0') {
				alert('Seleccione depósito o transferencia.');
				document.getElementById('tipo_recibo').focus();
				return false;
			}

			if (valor_pago == '') {
				alert('Ingrese valor');
				document.getElementById('valor_pago_recibo').focus();
				return false;
			}

			if (isNaN(valor_pago)) {
				alert('El dato ingresado en valor, no es un número');
				document.getElementById('valor_pago_recibo').focus();
				return false;
			}

			var forma_pago = forma_pago.substring(1, forma_pago.length);
			//Fin validacion
			$("#loaderCobroReciboVenta").fadeIn('fast');
			$.ajax({
				url: "../ajax/recibo_venta.php?action=agregar_forma_pago_ingreso_recibo&forma_pago=" + forma_pago + "&valor_pago=" + valor_pago + "&tipo=" + tipo + "&origen=" + origen,
				beforeSend: function(objeto) {
					$("#loaderCobroReciboVenta").html("Cargando...");
				},
				success: function(data) {
					$(".outer_divCobroReciboVenta").html(data).fadeIn('fast');
					$('#loaderCobroReciboVenta').html('');
					document.getElementById("forma_pago_recibo").value = "0";
					document.getElementById("tipo_recibo").value = "0";
					document.getElementById("valor_pago_recibo").value = "";
				}
			});
			event.preventDefault();
		}

		function eliminar_item_pago(id) {
			$.ajax({
				url: "../ajax/recibo_venta.php?action=eliminar_item_pago&id_registro=" + id,
				beforeSend: function(objeto) {
					$("#loaderCobroReciboVenta").html("Eliminando...");
				},
				success: function(data) {
					$(".outer_divCobroReciboVenta").html(data).fadeIn('fast');
					$('#loaderCobroReciboVenta').html('');
				}
			});
			event.preventDefault();
		}

		 function guarda_pago_recibo() {
			$('#btnActionFormPagoRecibo').attr("disabled", true);
			var id_recibo = $("#id_ReciboVenta").val();
			var fecha_ingreso = $("#fecha_ingreso_recibo").val();
			var nota = $("#nota_recibo").val();
			
			$.ajax({
				type: "POST",
				url: "../ajax/recibo_venta.php?action=guardar_pago_recibo",
				data: "id_recibo=" + id_recibo + "&fecha_ingreso=" + fecha_ingreso + "&nota=" + nota,
				beforeSend: function(objeto) {
					$("#loaderCobroReciboVenta").html("Guardando...");
				},
				success: function(datos) {
					$(".outer_divCobroReciboVenta").html(datos);
					$('#loaderCobroReciboVenta').html('');
					$('#btnActionFormPagoRecibo').attr("disabled", false);
				}
			});
			event.preventDefault();
		}


		function generar_factura(id) {
			if (confirm("Seguro desea crear una factura y eliminar el recibo de venta?")) {
				$('#generarFactura').attr("disabled", true);
				$.ajax({
					type: "GET",
					url: "../ajax/recibo_venta.php?action=generar_factura&id_recibo=" + id,
					beforeSend: function(objeto) {
						$("#loaderdet").html("Creando factura...");
					},
					success: function(data) {
						$(".outer_divdet").html(data).fadeIn('fast');
						$('#loaderdet').html('');
						$('#generarFactura').attr("disabled", false);
					}
				});
				event.preventDefault();
			}
		}

		function enviar_recibo_mail(id, mail) {
			$("#id_documento").val(id);
			$("#mail_receptor").val(mail);
			$("#tipo_documento").val("recibo_venta");
		};
	</script>