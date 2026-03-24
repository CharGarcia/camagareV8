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
		<title>Liquidaciones</title>
		<?php include("../paginas/menu_de_empresas.php");
		include("../modal/enviar_documentos_mail.php");
		include("../modal/detalle_documento.php");
		include("../modal/anular_documentos_sri.php");
		?>
	</head>

	<body>

		<div class="container-fluid">
			<div class="panel panel-info">
				<div class="panel-heading">
					<div class="btn-group pull-right">
						<form method="POST" action="../modulos/nueva_liquidacion_cs.php">
							<?php
							if (getPermisos($con, $id_usuario, $ruc_empresa, 'liquidacion_compra_servicio')['w'] == 1) {
							?>
								<button type='submit' class="btn btn-info"><span class="glyphicon glyphicon-plus"></span> Nueva liquidación</button>
							<?php
							}
							?>
						</form>
					</div>
					<h4><i class='glyphicon glyphicon-search'></i> Liquidaciones de compras de bienes o prestación de servicios </h4>
				</div>
				<ul class="nav nav-tabs nav-justified">
					<li class="active"><a data-toggle="tab" href="#liquidaciones">Liquidaciones</a></li>
					<li><a data-toggle="tab" href="#detalle_liquidaciones">Detalle de liquidaciones</a></li>
					<li><a data-toggle="tab" href="#detalle_adicionales">Detalles adicionales</a></li>
				</ul>
				<div class="tab-content">
					<div id="liquidaciones" class="tab-pane fade in active">
						<div class="panel-body">
							<form class="form-horizontal" role="form">
								<div class="form-group row">
									<div class="col-md-2">
										<div class="input-group">
											<span class="input-group-addon"><b>Estado</b></span>
											<select class="form-control input-sm" id="estado_liq" name="estado_liq" onchange='load(1);'>
												<option value="" selected>Todos</option>
												<?php
												$tipo = mysqli_query($con, "SELECT distinct estado_sri FROM encabezado_liquidacion WHERE ruc_empresa ='" . $ruc_empresa . "' order by estado_sri asc");
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
											<select class="form-control input-sm" id="anio_liq" name="anio_liq" onchange='load(1);'>
												<option value="" selected>Todos</option>
												<?php
												$existsCurrentYear = false;
												$tipo = mysqli_query($con, "SELECT distinct(year(fecha_liquidacion)) as anio FROM encabezado_liquidacion WHERE ruc_empresa ='" . $ruc_empresa . "' order by year(fecha_liquidacion) desc");
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
											<select class="form-control input-sm" id="mes_liq" name="mes_liq" onchange='load(1);'>
												<option value="" selected>Todos</option>
												<?php
												$tipo = mysqli_query($con, "SELECT distinct(DATE_FORMAT(fecha_liquidacion, '%m')) as mes FROM encabezado_liquidacion WHERE ruc_empresa ='" . $ruc_empresa . "' order by month(fecha_liquidacion) asc");
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
										<input type="hidden" id="ordenado" value="id_encabezado_liq">
										<input type="hidden" id="por" value="desc">
										<div class="input-group">
											<span class="input-group-addon"><b>Buscar:</b></span>
											<input type="text" class="form-control input-sm" id="q" placeholder="Cliente, serie, factura, fecha, ruc, estado" onkeyup='load(1);'>
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

					<div id="detalle_liquidaciones" class="tab-pane fade">
						<div class="panel-body">
							<form class="form-horizontal" role="form">
								<div class="form-group row">
									<label for="d" class="col-md-1 control-label">Buscar:</label>
									<div class="col-md-5">
										<input type="hidden" id="ordenado_det" value="cue.id_cuerpo_liquidacion">
										<input type="hidden" id="por_det" value="desc">
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
							<div id="resultados_detalles"></div><!-- Carga los datos ajax -->
							<div class='outer_div_detalles'></div><!-- Carga los datos ajax -->
						</div>
					</div>
					<div id="detalle_adicionales" class="tab-pane fade">
						<div class="panel-body">
							<form class="form-horizontal" role="form">
								<div class="form-group row">
									<label for="d" class="col-md-1 control-label">Buscar:</label>
									<div class="col-md-5">
										<input type="hidden" id="ordenado_adi" value="adi.id_detalle">
										<input type="hidden" id="por_adi" value="desc">
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
							<div id="resultados_adicionales"></div><!-- Carga los datos ajax -->
							<div class='outer_div_adicionales'></div><!-- Carga los datos ajax -->
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

		function load(page) {
			var por = $("#por").val();
			var ordenado = $("#ordenado").val();
			var por_det = $("#por_det").val();
			var ordenado_det = $("#ordenado_det").val();
			var por_adi = $("#por_adi").val();
			var ordenado_adi = $("#ordenado_adi").val();
			var q = $("#q").val();
			var d = $("#d").val();
			var a = $("#a").val();
			var estado_liq = $("#estado_liq").val();
			var anio_liq = $("#anio_liq").val();
			var mes_liq = $("#mes_liq").val();
			$("#loader").fadeIn('slow');
			$.ajax({
				url: '../ajax/buscar_liquidacion_compras.php?action=buscar_liquidacion_compras&page=' + page + '&q=' + q +
					"&por=" + por + "&ordenado=" + ordenado + '&estado_liq=' + estado_liq + '&anio_liq=' + anio_liq + '&mes_liq=' + mes_liq,
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
				url: '../ajax/buscar_liquidacion_compras.php?action=detalle_liquidaciones&page=' + page + '&d=' + d + "&por_det=" + por_det + "&ordenado_det=" + ordenado_det,
				beforeSend: function(objeto) {
					$('#loader_detalles').html('<img src="../image/ajax-loader.gif"> Cargando...');
				},
				success: function(data) {
					$(".outer_div_detalles").html(data).fadeIn('slow');
					$('#loader_detalles').html('');
				}
			})

			$("#loader_adicionales").fadeIn('slow');
			$.ajax({
				url: '../ajax/buscar_liquidacion_compras.php?action=detalle_adicionales&page=' + page + '&a=' + a + "&por_adi=" + por_adi + "&ordenado_adi=" + ordenado_adi,
				beforeSend: function(objeto) {
					$('#loader_adicionales').html('<img src="../image/ajax-loader.gif"> Cargando...');
				},
				success: function(data) {
					$(".outer_div_adicionales").html(data).fadeIn('slow');
					$('#loader_adicionales').html('');
				}
			})


		};

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

		function eliminar_liquidacion(id_lc) {
			var q = $("#q").val();
			var serie = $("#serie_liquidacion" + id_lc).val();
			var secuencial = $("#secuencial_liquidacion" + id_lc).val();
			var pagina = $("#pagina").val();
			if (confirm("Realmente desea eliminar la liquidación " + serie + "-" + secuencial + " ?")) {
				$.ajax({
					type: "POST",
					url: "../ajax/buscar_liquidacion_compras.php?action=eliminar_liquidacion_compras",
					data: "id_lc=" + id_lc,
					"q": q,
					beforeSend: function(objeto) {
						$("#resultados").html("Mensaje: Cargando...");
					},
					success: function(datos) {
						$("#resultados").html(datos);
						load(pagina);
					}
				});
			};
		};
		//pasa el codigo del id del documento a anularse al modal de anular documentos sri
		function anular_documento_en_sri(id) {
			document.querySelector("#anular_documento_sri").reset();
			document.getElementById('resultados_ajax_anular').innerHTML = '';
			$('#anular_sri').attr("disabled", true);
			$("#resultados_anular").html('<div class="progress"><div class="progress-bar progress-bar-primary progress-bar-striped active" role="progressbar" style="width:100%;">Consultando SRI...</div></div>');
			let documento = '03';
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

		function enviar_liquidacion_mail(id) {
			var mail_receptor = $("#mail_proveedor" + id).val();
			$("#id_documento").val(id);
			$("#mail_receptor").val(mail_receptor);
			$("#tipo_documento").val("liquidacion");
		};



		function detalle_liquidacion(id_lc) {
			$("#loaderdet").fadeIn('slow');
			$.ajax({
				url: '../ajax/detalle_documento.php?action=liquidacion_compras&id=' + id_lc,
				beforeSend: function(objeto) {
					$('#loaderdet').html('<img src="../image/ajax-loader.gif"> Cargando detalle de la liquidación...');
				},
				success: function(data) {
					$(".outer_divdet").html(data).fadeIn('slow');
					$('#loaderdet').html('');
				}
			})
		}

		function enviar_liquidacion_sri(id) {
			var serie_liquidacion = $("#serie_liquidacion" + id).val();
			var secuencial_liquidacion = $("#secuencial_liquidacion" + id).val();
			var numero_liquidacion = String("000000000" + secuencial_liquidacion).slice(-9);
			var pagina = $("#pagina").val();
			if (confirm("Seguro desea enviar la liquidación " + serie_liquidacion + '-' + numero_liquidacion + " al SRI?")) {
				$.ajax({
					type: "POST",
					url: "../facturacion_electronica/procesarEnvioSri.php",
					data: "id_documento_sri=" + id + "&modo_envio=online&tipo_documento_sri=liquidacion",
					beforeSend: function(objeto) {
						$("#loader").html("Enviando liquidación...");
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
					url: "../ajax/buscar_liquidacion_compras.php?action=cancelar_envio_sri",
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
			var estado_liq = $("#estado_liq").val();
			var anio_liq = $("#anio_liq").val();
			var mes_liq = $("#mes_liq").val();
			var page = $("#pagina").val();
			$.ajax({
				url: '../ajax/buscar_liquidacion_compras.php?action=buscar_liquidacion_compras&page=' + page + '&q=' + q +
					"&por=" + por + "&ordenado=" + ordenado + '&estado_liq=' + estado_liq + '&anio_liq=' + anio_liq + '&mes_liq=' + mes_liq,
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