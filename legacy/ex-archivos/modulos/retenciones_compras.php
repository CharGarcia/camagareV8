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
		<title>Retenciones compras</title>
		<?php include("../paginas/menu_de_empresas.php");
		include("../modal/enviar_documentos_mail.php");
		//include("../modal/enviar_documentos_sri.php");
		include("../modal/anular_documentos_sri.php");
		include("../modal/detalle_documento.php");
		?>
	</head>

	<body>

		<div class="container-fluid">
			<div class="panel panel-info">
				<div class="panel-heading">
					<div class="btn-group pull-right">
						<form method="post" action="../modulos/nueva_retencion_electronica.php">
							<?php
							if (getPermisos($con, $id_usuario, $ruc_empresa, 'retenciones_compras')['w'] == 1) {
							?>
								<button type='submit' class="btn btn-info"><span class="glyphicon glyphicon-plus"></span> Nueva Retención</button>
							<?php
							}
							?>
						</form>
					</div>
					<h4><i class='glyphicon glyphicon-search'></i> Retenciones en compras</h4>
				</div>
				<div class="panel-body">
					<form class="form-horizontal" role="form" id="datos_cotizacion">
						<div class="form-group row">
							<div class="col-md-2">
								<div class="input-group">
									<span class="input-group-addon"><b>Estado</b></span>
									<select class="form-control input-sm" id="estado" name="estado" onchange='load(1);'>
										<option value="" selected>Todos</option>
										<?php
										$tipo = mysqli_query($con, "SELECT distinct estado_sri 
												FROM encabezado_retencion WHERE ruc_empresa ='" . $ruc_empresa . "' order by estado_sri asc");
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
									<select class="form-control input-sm" id="anio_ret" name="anio_ret" onchange='load(1);'>
										<option value="" selected>Todos</option>
										<?php
										$existsCurrentYear = false;
										$tipo = mysqli_query($con, "SELECT distinct(year(fecha_emision)) as anio FROM encabezado_retencion WHERE ruc_empresa ='" . $ruc_empresa . "' order by year(fecha_emision) desc");
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
									<select class="form-control input-sm" id="mes_ret" name="mes_ret" onchange='load(1);'>
										<option value="" selected>Todos</option>
										<?php
										$tipo = mysqli_query($con, "SELECT distinct(DATE_FORMAT(fecha_emision, '%m')) as mes FROM encabezado_retencion WHERE ruc_empresa ='" . $ruc_empresa . "' order by month(fecha_emision) asc");
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
								<input type="hidden" id="ordenado" value="id_encabezado_retencion">
								<input type="hidden" id="por" value="desc">
								<div class="input-group">
									<span class="input-group-addon"><b>Buscar:</b></span>
									<input type="text" class="form-control input-sm" id="q" placeholder="Fecha, Proveedor, serie, número, código, concepto, factura" onkeyup='load(1);'>
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
			var q = $("#q").val();
			var estado = $("#estado").val();
			var anio_ret = $("#anio_ret").val();
			var mes_ret = $("#mes_ret").val();
			$("#loader").fadeIn('slow');
			$.ajax({
				url: '../ajax/buscar_retenciones_compras.php?action=buscar_retenciones_compras&page=' + page + '&q=' + q +
					'&por=' + por + '&ordenado=' + ordenado + '&estado=' + estado + '&anio_ret=' + anio_ret + '&mes_ret=' + mes_ret,
				beforeSend: function(objeto) {
					$('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
				},
				success: function(data) {
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');
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

		function enviar_retencion_mail(id) {
			var mail_retencion = $("#mail_proveedor" + id).val();
			$("#id_documento").val(id); //uso el id del mismo formulario para no crear un nuevo modal
			$("#mail_receptor").val(mail_retencion);
			$("#tipo_documento").val("retencion");
		};


		//pasa el codigo del id del documento a anularse al modal de anular documentos sri
		function anular_documento_en_sri(id) {
			document.querySelector("#anular_documento_sri").reset();
			document.getElementById('resultados_ajax_anular').innerHTML = '';
			$('#anular_sri').attr("disabled", true);
			$("#resultados_anular").html('<div class="progress"><div class="progress-bar progress-bar-primary progress-bar-striped active" role="progressbar" style="width:100%;">Consultando SRI...</div></div>');
			let documento = '07';
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


		$(function() {
			$("#edita_fecha_r").datepicker({
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
			$("#edita_fecha_r").datepicker("option", "minDate", "-1m:+26d");
			$("#edita_fecha_r").datepicker("option", "maxDate", "+0m +0d");
		});

		function detalle_retencion_compra(id_ret) {
			$("#outer_divdet").fadeIn('slow');
			$.ajax({
				url: '../ajax/detalle_documento.php?action=detalle_retencion_compras&id_ret=' + id_ret,
				beforeSend: function(objeto) {
					$('#outer_divdet').html('<img src="../image/ajax-loader.gif"> Cargando detalle de retención...');
				},
				success: function(data) {
					$(".outer_divdet").html(data).fadeIn('slow');
					$('#outer_divdet').html('');
				}
			})
		}

		function eliminar_retencion_compras(id_retencion) {
			var q = $("#q").val();
			var serie = $("#serie_retencion" + id_retencion).val();
			var secuencial = $("#secuencial_retencion" + id_retencion).val();
			var pagina = $("#pagina").val();
			if (confirm("Realmente desea eliminar la retención " + serie + "-" + secuencial + " ?")) {
				$.ajax({
					type: "POST",
					url: "../ajax/buscar_retenciones_compras.php?action=eliminar_retencion_compras",
					data: "id_retencion=" + id_retencion,
					"q": q,
					beforeSend: function(objeto) {
						$("#resultados").html("Eliminando...");
					},
					success: function(datos) {
						$("#resultados").html(datos);
						load(pagina);
					}
				});
			}
		}

		function enviar_retencion_sri(id) {
			var serie_retencion = $("#serie_retencion" + id).val();
			var secuencial_retencion = $("#secuencial_retencion" + id).val();
			var numero_retencion = String("000000000" + secuencial_retencion).slice(-9);
			var pagina = $("#pagina").val();
			if (confirm("Seguro desea enviar la retención " + serie_retencion + '-' + numero_retencion + " al SRI?")) {
				$.ajax({
					type: "POST",
					url: "../facturacion_electronica/procesarEnvioSri.php",
					data: "id_documento_sri=" + id + "&modo_envio=online&tipo_documento_sri=retencion",
					beforeSend: function(objeto) {
						$("#loader").html("Enviando retención...");
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
					url: "../ajax/buscar_retenciones_compras.php?action=cancelar_envio_sri",
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
			var anio_ret = $("#anio_ret").val();
			var mes_ret = $("#mes_ret").val();
			var page = $("#pagina").val();
			$.ajax({
				url: '../ajax/buscar_retenciones_compras.php?action=buscar_retenciones_compras&page=' + page + '&q=' + q +
					'&por=' + por + '&ordenado=' + ordenado + '&estado=' + estado + '&anio_ret=' + anio_ret + '&mes_ret=' + mes_ret,
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