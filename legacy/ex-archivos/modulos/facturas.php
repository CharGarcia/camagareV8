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
		<title>Ventas</title>
		<?php include("../paginas/menu_de_empresas.php");
		//include("../modal/enviar_documentos_sri.php");
		include("../modal/detalle_documento.php");
		include("../modal/anular_documentos_sri.php");
		include("../modal/cobro_pago_directo.php");
		include("../modal/enviar_documentos_mail.php");
		include("../modal/factura.php");
		unset($_SESSION['arrayFormaPagoIngresoFactura']);
		?>
	</head>

	<body>
		<div class="container-fluid">
			<div class="panel panel-info">
				<div class="panel-heading">
					<div class="btn-group pull-right">
						<?php
						if (getPermisos($con, $id_usuario, $ruc_empresa, 'facturas')['w'] == 1) {
						?>
							<button type="submit" class="btn btn-info" data-toggle="modal" data-target="#factura" onclick="crear_factura();" title="Nueva factura electrónica"><span class="glyphicon glyphicon-plus"></span> Nueva factura</button>
						<?php
						}
						?>
					</div>
					<h4><i class="glyphicon glyphicon-search"></i> Facturas de venta</h4>
				</div>
				<ul class="nav nav-tabs nav-justified">
					<li class="active"><a data-toggle="tab" href="#facturas">Facturas</a></li>
					<li><a data-toggle="tab" href="#detalle_facturas">Productos y servicios</a></li>
					<li><a data-toggle="tab" href="#detalle_adicionales">Adicionales</a></li>
				</ul>
				<div class="tab-content">
					<div id="facturas" class="tab-pane fade in active">
						<div class="panel-body">
							<form class="form-horizontal" role="form">
								<div class="form-group row">
									<div class="col-md-2">
										<div class="input-group">
											<span class="input-group-addon"><b>Estado</b></span>
											<select class="form-control input-sm" id="estado" name="estado" onchange='load(1);'>
												<option value="" selected>Todos</option>
												<?php
												$tipo = mysqli_query($con, "SELECT distinct estado_sri 
												FROM encabezado_factura WHERE ruc_empresa ='" . $ruc_empresa . "' order by estado_sri asc");
												while ($row = mysqli_fetch_array($tipo)) {
												?>
													<option value="<?php echo $row['estado_sri'] ?>"><?php echo strtoupper($row['estado_sri']) ?></option>
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
											<select class="form-control input-sm" id="anio_factura" name="anio_factura" onchange='load(1);'>
												<option value="">Todos</option>
												<?php
												$existsCurrentYear = false;
												$tipo = mysqli_query($con, "SELECT DISTINCT YEAR(fecha_factura) AS anio FROM encabezado_factura WHERE ruc_empresa ='" . $ruc_empresa . "' ORDER BY YEAR(fecha_factura) DESC");
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
											<select class="form-control input-sm" id="mes_factura" name="mes_factura" onchange='load(1);'>
												<option value="" selected>Todos</option>
												<?php
												$tipo = mysqli_query($con, "SELECT distinct(DATE_FORMAT(fecha_factura, '%m')) as mes FROM encabezado_factura WHERE ruc_empresa ='" . $ruc_empresa . "' order by month(fecha_factura) asc");
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
											<select class="form-control input-sm" id="dia_factura" name="dia_factura" onchange='load(1);'>
												<option value="" selected>Todos</option>
												<?php
												$tipo = mysqli_query($con, "SELECT distinct(DATE_FORMAT(fecha_factura, '%d')) as dia FROM encabezado_factura WHERE ruc_empresa ='" . $ruc_empresa . "' order by day(fecha_factura) asc");
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
										<input type="hidden" id="ordenado" value="id_encabezado_factura">
										<input type="hidden" id="por" value="desc">
										<div class="input-group">
											<span class="input-group-addon"><b>Buscar:</b></span>
											<input type="text" class="form-control input-sm" id="q" placeholder="Cliente, serie, factura, fecha, ruc, estado" onkeyup='load(1);'>
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

					<div id="detalle_facturas" class="tab-pane fade">
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
							<div id="resultados_detalles_facturas"></div><!-- Carga los datos ajax -->
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

		function crear_factura() {
			document.querySelector("#titleModalFactura").innerHTML = "<i class='glyphicon glyphicon-ok'></i> Nueva factura";
			document.querySelector("#guardar_factura").reset();
			document.querySelector("#id_factura").value = "";
			document.querySelector("#btnActionFormFactura").classList.replace("btn-info", "btn-primary");
			document.querySelector("#btnTextFactura").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Guardar";
			document.querySelector('#btnActionFormFactura').title = "Guardar Factura";
			document.getElementById("label_bodega_producto_factura").style.display = "none";
			document.getElementById("label_existencia_producto_factura").style.display = "none";
			document.getElementById("label_lote_producto_factura").style.display = "none";
			document.getElementById("label_medida_producto_factura").style.display = "none";
			document.getElementById("label_caducidad_producto_factura").style.display = "none";
			document.getElementById("lista_lote_producto_factura").style.display = "none";
			document.getElementById("lista_caducidad_producto_factura").style.display = "none";
			document.getElementById("lista_medida_producto_factura").style.display = "none";

			//para buscar el numero de factura que continua cada vez que se hace clic en nueva factura
			var id_serie = $("#serie_factura").val();

			$.post('../ajax/buscar_ultima_factura.php', {
				serie_fe: id_serie
			}).done(function(respuesta) {
				var factura_final = respuesta;
				$("#secuencial_factura").val(factura_final);
			});

			//para traer el tipo de configuracion de inventarios, si o no
			$.post('../ajax/consulta_configuracion_facturacion.php', {
				opcion_mostrar: 'inventario',
				serie_consultada: id_serie
			}).done(function(respuesta_inventario) {
				var resultado_inventario = $.trim(respuesta_inventario);
				$('#inventario_producto_factura').val(resultado_inventario);

				if (resultado_inventario == "SI") {
					document.getElementById("label_bodega_producto_factura").style.display = "";
				}
			});

			//para traer y ver si trabaja con medida
			$.post('../ajax/consulta_configuracion_facturacion.php', {
				opcion_mostrar: 'medida',
				serie_consultada: id_serie
			}).done(function(respuesta_medida) {
				var resultado_medida = $.trim(respuesta_medida);
				$('#muestra_medida_producto_factura').val(resultado_medida);
			});

			//para traer y ver si trabaja con lote
			$.post('../ajax/consulta_configuracion_facturacion.php', {
				opcion_mostrar: 'lote',
				serie_consultada: id_serie
			}).done(function(respuesta_lote) {
				var resultado_lote = $.trim(respuesta_lote);
				$('#muestra_lote_producto_factura').val(resultado_lote);
			});

			//para traer y ver si trabaja con bodega
			$.post('../ajax/consulta_configuracion_facturacion.php', {
				opcion_mostrar: 'bodega',
				serie_consultada: id_serie
			}).done(function(respuesta_bodega) {
				var resultado_bodega = $.trim(respuesta_bodega);
				$('#muestra_bodega_producto_factura').val(resultado_bodega);
			});

			//para traer y ver si trabaja con vencimiento
			$.post('../ajax/consulta_configuracion_facturacion.php', {
				opcion_mostrar: 'vencimiento',
				serie_consultada: id_serie
			}).done(function(respuesta_vencimiento) {
				var resultado_vencimiento = $.trim(respuesta_vencimiento);
				$('#muestra_vencimiento_producto_factura').val(resultado_vencimiento);
			});

			//para cuando es nueva factura
			$.ajax({
				url: "../ajax/facturas.php?action=nueva_factura",
				beforeSend: function(objeto) {
					$("#detalle_factura").html("Cargando...");
				},
				success: function(data) {
					$('#detalle_factura').html('');
					$('#detalle_informacion_adicional').html('');
					$('#detalle_subtotales_factura').html('');
					$('#detalle_formas_pago').html('');
				}
			});
			//document.getElementById("nombre_cliente_factura").focus();
		}


		function load(page) {
			var por = $("#por").val();
			var ordenado = $("#ordenado").val();
			var q = $("#q").val();
			var d = $("#d").val();
			var a = $("#a").val();
			var estado = $("#estado").val();
			var anio_factura = $("#anio_factura").val();
			var mes_factura = $("#mes_factura").val();
			var dia_factura = $("#dia_factura").val();
			$("#loader").fadeIn('slow');
			$.ajax({
				url: '../ajax/facturas.php?action=buscar_facturas&page=' + page + '&q=' + q + "&por=" + por +
					"&ordenado=" + ordenado + '&estado=' + estado + '&anio_factura=' + anio_factura + '&mes_factura=' + mes_factura + '&dia_factura=' + dia_factura,
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
				url: '../ajax/facturas.php?action=buscar_detalle_facturas&page=' + page + '&d=' + d,
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
				url: '../ajax/facturas.php?action=buscar_detalle_adicionales_facturas&page=' + page + '&a=' + a,
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

		function eliminar_factura(id) {
			var q = $("#q").val();
			var serie = $("#serie_factura" + id).val();
			var secuencial = $("#secuencial_factura" + id).val();

			if (confirm("Realmente desea eliminar la factura " + serie + "-" + secuencial + " ?")) {
				$.ajax({
					type: "POST",
					url: "../ajax/facturas.php?action=eliminar_factura",
					data: "id_factura=" + id,
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

		function anular_documento_en_sri(id) {
			document.querySelector("#anular_documento_sri").reset();
			document.getElementById('resultados_ajax_anular').innerHTML = '';
			$('#anular_sri').attr("disabled", true);
			$("#resultados_anular").html('<div class="progress"><div class="progress-bar progress-bar-primary progress-bar-striped active" role="progressbar" style="width:100%;">Consultando SRI...</div></div>');
			let documento = '01';
			document.querySelector("#id_documento_modificar").value = id;
			document.querySelector("#codigo_documento_modificar").value = documento;
			let request = (window.XMLHttpRequest) ?
				new XMLHttpRequest() :
				new ActiveXObject('Microsoft.XMLHTTP');
			let ajaxUrl = '../ajax/detalle_documento.php?action=info_anular_sri&documento=' + documento + '&id=' + id;
			request.open("GET", ajaxUrl, true);
			request.send();
			request.onreadystatechange = function() {
				if (request.readyState == 4 && request.status == 200) {
					let objData = JSON.parse(request.responseText);
					if (objData.status) {
						let objInfoSRI = objData.data;
						document.querySelector("#cliente_proveedor").value = objInfoSRI.cliente_proveedor;
						document.querySelector("#tipo_comprobante").value = objInfoSRI.tipo_comprobante;
						document.querySelector("#fecha_autorizacion").value = objInfoSRI.fecha_autorizacion;
						document.querySelector("#fecha_documento").value = objInfoSRI.fecha_documento;
						document.querySelector("#clave_acceso").value = objInfoSRI.clave_acceso;
						document.querySelector("#numero_autorizacion").value = objInfoSRI.clave_acceso;
						document.querySelector("#ruc_receptor").value = objInfoSRI.identificacion_receptor;
						document.querySelector("#correo_receptor").value = objInfoSRI.correo_electronico_receptor;
						$('#anular_sri').attr("disabled", false);
						$("#resultados_anular").html('');
					} else {
						$.notify(objData.msg, "error");
					}
				}
				return false;
			}
		}

		function enviar_factura_mail(id) {
			var mail_receptor = $("#mail_cliente" + id).val();
			$("#id_documento").val(id);
			$("#mail_receptor").val(mail_receptor);
			$("#tipo_documento").val("factura");
		};

		function enviar_factura_whatsapp(id, numero, mensaje) {
			document.querySelector("#resultados_ajax_whatsapp").innerHTML = "";
			document.querySelector("#documento_whatsapp").reset();
			$("#id_documento_whatsapp").val(id);
			$("#mensaje").val(mensaje);
			$("#whatsapp_receptor").val(numero);
			$("#tipo_documento_whatsapp").val("factura");
		};

		function detalle_factura(id) {
			$("#loaderdet").fadeIn('slow');
			$.ajax({
				url: '../ajax/detalle_documento.php?action=facturas_ventas&id=' + id,
				beforeSend: function(objeto) {
					$('#loaderdet').html('<img src="../image/ajax-loader.gif"> Cargando detalle de factura...');
				},
				success: function(data) {
					$(".outer_divdet").html(data).fadeIn('slow');
					$('#loaderdet').html('');
				}
			})
		}

		function editar_factura(id) {
			document.querySelector('#titleModalFactura').innerHTML = "<i class='glyphicon glyphicon-edit'></i> Actualizar Factura";
			document.querySelector("#guardar_factura").reset();
			document.querySelector("#id_factura").value = id;
			document.querySelector('#btnActionFormFactura').classList.replace("btn-primary", "btn-info");
			document.querySelector("#btnTextFactura").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Actualizar";
			document.getElementById("label_bodega_producto_factura").style.display = "none";
			document.getElementById("label_existencia_producto_factura").style.display = "none";
			document.getElementById("label_lote_producto_factura").style.display = "none";
			document.getElementById("label_medida_producto_factura").style.display = "none";
			document.getElementById("label_caducidad_producto_factura").style.display = "none";
			document.getElementById("lista_lote_producto_factura").style.display = "none";
			document.getElementById("lista_caducidad_producto_factura").style.display = "none";
			document.getElementById("lista_medida_producto_factura").style.display = "none";
			$('#btnActionFormFactura').attr("disabled", true);
			//para traer el tipo de configuracion de inventarios, si o no
			var id_cliente = $("#id_cliente" + id).val();
			var nombre_cliente = $("#nombre_cliente" + id).val();
			var fecha_factura = $("#fecha_factura" + id).val();
			var id_serie = $("#serie_factura" + id).val();
			var secuencial_factura = $("#secuencial_factura" + id).val();
			var total_factura = $("#total_factura" + id).val();

			$("#id_factura").val(id);
			$("#id_cliente_factura").val(id_cliente);
			$("#nombre_cliente_factura").val(nombre_cliente);
			$("#fecha_factura").val(fecha_factura);
			$("#serie_factura").val(id_serie);
			$("#secuencial_factura").val(secuencial_factura);
			$("#suma_factura").val(total_factura);

			$.post('../ajax/consulta_configuracion_facturacion.php', {
				opcion_mostrar: 'inventario',
				serie_consultada: id_serie
			}).done(function(respuesta_inventario) {
				var resultado_inventario = $.trim(respuesta_inventario);
				$('#inventario_producto_factura').val(resultado_inventario);

				if (resultado_inventario == "SI") {
					document.getElementById("label_bodega_producto_factura").style.display = "";
				}
			});

			//para traer y ver si trabaja con medida
			$.post('../ajax/consulta_configuracion_facturacion.php', {
				opcion_mostrar: 'medida',
				serie_consultada: id_serie
			}).done(function(respuesta_medida) {
				var resultado_medida = $.trim(respuesta_medida);
				$('#muestra_medida_producto_factura').val(resultado_medida);
			});

			//para traer y ver si trabaja con lote
			$.post('../ajax/consulta_configuracion_facturacion.php', {
				opcion_mostrar: 'lote',
				serie_consultada: id_serie
			}).done(function(respuesta_lote) {
				var resultado_lote = $.trim(respuesta_lote);
				$('#muestra_lote_producto_factura').val(resultado_lote);
			});

			//para traer y ver si trabaja con bodega
			$.post('../ajax/consulta_configuracion_facturacion.php', {
				opcion_mostrar: 'bodega',
				serie_consultada: id_serie
			}).done(function(respuesta_bodega) {
				var resultado_bodega = $.trim(respuesta_bodega);
				$('#muestra_bodega_producto_factura').val(resultado_bodega);
			});

			//para traer y ver si trabaja con vencimiento
			$.post('../ajax/consulta_configuracion_facturacion.php', {
				opcion_mostrar: 'vencimiento',
				serie_consultada: id_serie
			}).done(function(respuesta_vencimiento) {
				var resultado_vencimiento = $.trim(respuesta_vencimiento);
				$('#muestra_vencimiento_producto_factura').val(resultado_vencimiento);
			});

			//para cuando editar factura
			$.ajax({
				type: "POST",
				url: "../ajax/facturas.php?action=editar_factura",
				data: "id_factura=" + id + "&serie_factura=" + id_serie + "&secuencial_factura=" + secuencial_factura,
				beforeSend: function(objeto) {
					$("#detalle_factura").html("Cargando...");
				},
				success: function(data) {
					$('#detalle_factura').html('Cargando...');
					$('#detalle_informacion_adicional').html('Cargando...');
					$('#detalle_subtotales_factura').html('Cargando...');
					$('#detalle_formas_pago').html('Cargando...');

				}
			});

			//esperar 2 segundos para cargar
			setTimeout(function() {
				//para el encabezado de la factura y la info adicional
				$.ajax({
					url: "../ajax/facturas.php?action=muestra_cliente_adicionales_editar_factura",
					beforeSend: function(objeto) {
						$("#detalle_informacion_adicional").html("Cargando...");
					},
					success: function(dataAdicional) {
						$('#detalle_informacion_adicional').html(dataAdicional);
					}
				});

				//para mostrar el cuerpo de la factura
				$.ajax({
					type: "POST",
					url: "../ajax/facturas.php?action=muestra_cuerpo_editar_factura",
					data: "serie_factura=" + id_serie,
					beforeSend: function(objeto) {
						$("#detalle_factura").html("Cargando...");
					},
					success: function(dataCuerpo) {
						$('#detalle_factura').html(dataCuerpo);
					}
				});

				//para mostrar las formas de pago 
				$.ajax({
					type: "POST",
					url: "../ajax/facturas.php?action=muestra_formas_pago_editar_factura",
					data: "total_factura=" + total_factura,
					beforeSend: function(objeto) {
						$("#detalle_formas_pago").html("Cargando...");
					},
					success: function(dataFormasPago) {
						$('#detalle_formas_pago').html(dataFormasPago);
					}
				});

				//para mostrar subtotales de la factura
				$.ajax({
					type: "POST",
					url: "../ajax/facturas.php?action=muestra_subtotales_editar_factura",
					data: "serie_factura=" + id_serie,
					beforeSend: function(objeto) {
						$("#detalle_subtotales_factura").html("Cargando...");
					},
					success: function(datosSubtotal) {
						$("#detalle_subtotales_factura").html(datosSubtotal);
						$('#btnActionFormFactura').attr("disabled", false);
					}
				})

			}, 2000);

		}


		function carga_modal_registrar_pago(id, valor, cliente, numero_factura, fecha) {
			document.querySelector("#detalle_pago_factura").reset();
			$(".outer_divCobroVenta").html('').fadeIn('fast');
			$("#id_FacturaVenta").val(id);
			$("#valor_pago").val(valor);
			$("#porcobrar_FacturaVenta").val(valor);
			document.querySelector("#datos_cobro_factura").innerHTML = 'Fecha: ' + fecha + ' </br>Cliente: ' + cliente + ' </br>Documento: ' + numero_factura + ' </br>Saldo por cobrar: ' + valor;
			$.ajax({
				url: "../ajax/facturas.php?action=nuevo_pago_factura",
				beforeSend: function(objeto) {
					$("#detalle_factura").html("Cargando...");
				},
				success: function(data) {
					$('#detalle_factura').html('');
				}
			});
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
			$("#loaderCobroFacturaVenta").fadeIn('fast');
			$.ajax({
				url: "../ajax/facturas.php?action=agregar_forma_pago_ingreso_factura&forma_pago=" + forma_pago + "&valor_pago=" + valor_pago + "&tipo=" + tipo + "&origen=" + origen,
				beforeSend: function(objeto) {
					$("#loaderCobroFacturaVenta").html("Cargando...");
				},
				success: function(data) {
					$(".outer_divCobroVenta").html(data).fadeIn('fast');
					$('#loaderCobroFacturaVenta').html('');
					document.getElementById("forma_pago").value = "0";
					document.getElementById("tipo").value = "0";
					document.getElementById("valor_pago").value = "";
				}
			});
			event.preventDefault();
		}

		function eliminar_item_pago(id) {
			$.ajax({
				url: "../ajax/facturas.php?action=eliminar_item_pago&id_registro=" + id,
				beforeSend: function(objeto) {
					$("#loaderCobroFacturaVenta").html("Eliminando...");
				},
				success: function(data) {
					$(".outer_divCobroVenta").html(data).fadeIn('fast');
					$('#loaderCobroFacturaVenta').html('');
				}
			});
			event.preventDefault();
		}

		function guarda_pago_factura() {
			$('#btnActionFormPagoFactura').attr("disabled", true);
			var id_factura = $("#id_FacturaVenta").val();
			var fecha_ingreso = $("#fecha_ingreso").val();
			var nota = $("#nota_venta").val();
			$.ajax({
				type: "POST",
				url: "../ajax/facturas.php?action=guardar_pago_factura",
				data: "id_factura=" + id_factura + "&fecha_ingreso=" + fecha_ingreso + "&nota=" + nota,
				beforeSend: function(objeto) {
					$("#loaderCobroFacturaVenta").html("Guardando...");
				},
				success: function(datos) {
					$(".outer_divCobroVenta").html(datos);
					$("#loaderCobroFacturaVenta").html('');
					$('#btnActionFormPagoFactura').attr("disabled", false);
				}
			});
			event.preventDefault();
		}


		function duplicar_factura(id) {
			if (confirm("Seguro desea duplicar la factura?")) {
				$('#duplicarFactura').attr("disabled", true);
				$.ajax({
					type: "GET",
					url: "../ajax/facturas.php?action=duplicar_factura&id_factura=" + id,
					beforeSend: function(objeto) {
						$("#loaderdet").html("Duplicando factura...");
					},
					success: function(data) {
						$(".outer_divdet").html(data).fadeIn('fast');
						$('#loaderdet').html('');
						$('#duplicarFactura').attr("disabled", false);
					}
				});
				event.preventDefault();
			}
		}


		function generar_recibo_venta(id) {
			if (confirm("Seguro desea crear recibo de venta y eliminar la factura?")) {
				$('#reciboVenta').attr("disabled", true);
				$.ajax({
					type: "GET",
					url: "../ajax/facturas.php?action=recibo_venta&id_factura=" + id,
					beforeSend: function(objeto) {
						$("#loaderdet").html("Creando recibo de venta...");
					},
					success: function(data) {
						$(".outer_divdet").html(data).fadeIn('fast');
						$('#loaderdet').html('');
						$('#reciboVenta').attr("disabled", false);
					}
				});
				event.preventDefault();
			}
		}


		function imprimir_ticket(opcion, id_factura) {
			window.open('../impresiones/imprimir.php?action=' + opcion + '&id_factura=' + id_factura, '_blank');
		}


		function generar_pdf(id) {
			var id_documento_sri = id;
			var tipo_documento_sri = 'factura';
			$.ajax({
				type: 'POST',
				url: '../facturacion_electronica/enviarComprobantesSri.php',
				data: 'tipo_documento_sri=' + tipo_documento_sri + '&id_documento_sri=' + id_documento_sri + '&modo_envio=offline',
				beforeSend: function(objeto) {
					$.notify('Generando PDF espere por favor...', 'warning');
				},
				success: function(datos) {
					$.notify('PDF generado...', 'success');
					window.location.href = '../ajax/imprime_documento.php?id_documento=' + btoa(id) + '&tipo_documento=factura&tipo_archivo=pdf';
				}
			});
			event.preventDefault();
		}

		function enviar_factura_sri(id, fecha_factura, ruc, total) {
			var serie_factura = $("#serie_factura" + id).val();
			var secuencial_factura = $("#secuencial_factura" + id).val();
			var numero_factura = String("000000000" + secuencial_factura).slice(-9);
			var pagina = $("#pagina").val();

			var hoy = new Date();
			var fecha_hoy = hoy.getFullYear() + '-' +
				('0' + (hoy.getMonth() + 1)).slice(-2) + '-' +
				('0' + hoy.getDate()).slice(-2);

			
			if (ruc =='9999999999999' && total > 50) {
				alert("❌ El valor total para Consumidor Final no puede ser mayor a 50 dólares.");
				return false;
			}

				if (fecha_factura !== fecha_hoy) {
				alert("❌ La factura solo puede enviarse al SRI si la fecha es de hoy.");
				return false;
			}

			// Confirmación
			if (confirm("¿Seguro desea enviar la factura " + serie_factura + '-' + numero_factura + " al SRI?")) {
				$.ajax({
					type: "POST",
					url: "../facturacion_electronica/procesarEnvioSri.php",
					data: {
						id_documento_sri: id,
						modo_envio: 'online',
						tipo_documento_sri: 'factura'
					},
					beforeSend: function() {
						$("#loader").html("Enviando factura...");
					},
					success: function(datos) {
						$("#resultados").html(datos);
						$("#loader").html("");
						load(pagina);
					}
				});
			}
		}


		function cancelar_envio_sri(id) {
			var pagina = $("#pagina").val();
			if (confirm("Seguro desea cancelar el envio del documento al SRI?")) {
				$.ajax({
					type: "POST",
					url: "../ajax/facturas.php?action=cancelar_envio_sri",
					data: "id=" + id + "&page=" + pagina,
					beforeSend: function(objeto) {
						$("#loader").html("Actualizando...");
					},
					success: function(datos) {
						$("#resultados").html(datos);
						$("#loader").html("");
					}
				})
			}
		}


		function actualizar_estado_sri() {
			var por = $("#por").val();
			var ordenado = $("#ordenado").val();
			var q = $("#q").val();
			var estado = $("#estado").val();
			var anio_factura = $("#anio_factura").val();
			var mes_factura = $("#mes_factura").val();
			var dia_factura = $("#dia_factura").val();
			var page = $("#pagina").val();
			$.ajax({
				url: '../ajax/facturas.php?action=buscar_facturas&page=' + page + '&q=' + q + "&por=" + por +
					"&ordenado=" + ordenado + '&estado=' + estado + '&anio_factura=' + anio_factura + '&mes_factura=' + mes_factura + '&dia_factura=' + dia_factura,
				beforeSend: function(objeto) {
					$('#loader').html('');
				},
				success: function(data) {
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');
				}
			});
		}

		setInterval(actualizar_estado_sri, 5000); //para actualizar los estados del sri cada 5 segundos
	</script>