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
		<title>Notas de crédito</title>
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
						<form method="post" action="../modulos/nueva_nota_credito.php">
							<?php
							$con = conenta_login();
							if (getPermisos($con, $id_usuario, $ruc_empresa, 'notas_de_credito')['w'] == 1) {
							?>
								<button type='submit' class="btn btn-info"><span class="glyphicon glyphicon-plus"></span> Nueva nota de crédito</button>
							<?php
							}
							?>
						</form>
					</div>
					<h4><i class='glyphicon glyphicon-search'></i> Notas de crédito</h4>
				</div>
				<div class="panel-body">
					<form class="form-horizontal">
						<div class="form-group row">
							<div class="col-md-2">
								<div class="input-group">
									<span class="input-group-addon"><b>Estado</b></span>
									<select class="form-control input-sm" id="estado" name="estado" onchange='load(1);'>
										<option value="" selected>Todos</option>
										<?php
										$tipo = mysqli_query($con, "SELECT distinct estado_sri 
												FROM encabezado_nc WHERE ruc_empresa ='" . $ruc_empresa . "' order by estado_sri asc");
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
									<select class="form-control input-sm" id="anio_nc" name="anio_nc" onchange='load(1);'>
										<option value="" selected>Todos</option>
										<?php
										$tipo = mysqli_query($con, "SELECT distinct(year(fecha_nc)) as anio FROM encabezado_nc WHERE ruc_empresa ='" . $ruc_empresa . "' order by year(fecha_nc) desc");
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
									<select class="form-control input-sm" id="mes_nc" name="mes_nc" onchange='load(1);'>
										<option value="" selected>Todos</option>
										<?php
										$tipo = mysqli_query($con, "SELECT distinct(DATE_FORMAT(fecha_nc, '%m')) as mes FROM encabezado_nc WHERE ruc_empresa ='" . $ruc_empresa . "' order by month(fecha_nc) asc");
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
								<input type="hidden" id="ordenado" value="id_encabezado_nc">
								<input type="hidden" id="por" value="desc">
								<div class="input-group">
									<span class="input-group-addon"><b>Buscar:</b></span>
									<input type="text" class="form-control input-sm" id="nc" placeholder="Fecha, Nombre, número" onkeyup='load(1);'>
									<span class="input-group-btn">
										<button type="button" class="btn btn-default input-sm" onclick='load(1);'><span class="glyphicon glyphicon-search"></span> Buscar</button>
									</span>
								</div>
							</div>
						</div>

					</form>
					<div id="resultados_nc"></div><!-- Carga los datos ajax -->
					<div class='outer_div_nc'></div><!-- Carga los datos ajax -->
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
			var q = $("#nc").val();
			var estado = $("#estado").val();
			var anio_nc = $("#anio_nc").val();
			var mes_nc = $("#mes_nc").val();
			$("#loader_nc").fadeIn('slow');
			$.ajax({
				url: '../ajax/buscar_nc.php?action=buscar_nota_credito&page=' + page + '&q=' + q + "&por=" + por + "&ordenado=" + ordenado + '&estado=' + estado + '&anio_nc=' + anio_nc + '&mes_nc=' + mes_nc,
				beforeSend: function(objeto) {
					$('#loader_nc').html('<img src="../image/ajax-loader.gif"> Cargando...');
				},
				success: function(data) {
					$(".outer_div_nc").html(data).fadeIn('slow');
					$('#loader_nc').html('');
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

		function eliminar_nc(id_nc) {
			var q = $("#q").val();
			var serie = $("#serie_nc" + id_nc).val();
			var secuencial = $("#secuencial_nc" + id_nc).val();
			if (confirm("Realmente desea eliminar la nota de crédito " + serie + "-" + secuencial + " ?")) {
				$.ajax({
					type: "POST",
					url: "../ajax/buscar_nc.php?action=emilinar_nota_credito",
					data: "id_nc=" + id_nc,
					"q": q,
					beforeSend: function(objeto) {
						$("#resultados_nc").html("Mensaje: Cargando...");
					},
					success: function(datos) {
						$("#resultados_nc").html(datos);
						load(1);
					}
				});
			}
		};

		function anular_documento_en_sri(id) {
			document.querySelector("#anular_documento_sri").reset();
			document.getElementById('resultados_ajax_anular').innerHTML = '';
			$('#anular_sri').attr("disabled", true);
			$("#resultados_anular").html('<div class="progress"><div class="progress-bar progress-bar-primary progress-bar-striped active" role="progressbar" style="width:100%;">Consultando SRI...</div></div>');
			let documento = '04';
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

		function enviar_nc_mail(id) {
			var mail_nc = $("#mail_cliente" + id).val();
			$("#id_documento").val(id); //uso el id del mismo formulario para no crear un nuevo modal
			$("#mail_receptor").val(mail_nc);
			$("#tipo_documento").val("nc");
		};

		$(function() {
			$("#edita_fecha_f").datepicker({
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
			$("#edita_fecha_f").datepicker("option", "minDate", "-1m:+26d");
			$("#edita_fecha_f").datepicker("option", "maxDate", "+0m +0d");
		});


		function detalle_nc(id_nc) {
			var serie_nc = $("#serie_nc" + id_nc).val();
			var secuencial_nc = $("#secuencial_nc" + id_nc).val();
			$("#loaderdet").fadeIn('slow');
			$.ajax({
				url: '../ajax/detalle_documento.php?action=notas_credito&serie_nc=' + serie_nc + '&secuencial_nc=' + secuencial_nc,
				beforeSend: function(objeto) {
					$('#loaderdet').html('<img src="../image/ajax-loader.gif"> Cargando detalle de nc...');
				},
				success: function(data) {
					$(".outer_divdet").html(data).fadeIn('slow');
					$('#loaderdet').html('');
				}
			})
		}

		function enviar_nc_sri(id, ruc_cliente) {
			var serie_nc = $("#serie_nc" + id).val();
			var secuencial_nc = $("#secuencial_nc" + id).val();
			var numero_nc = String("000000000" + secuencial_nc).slice(-9);
			var id_encabezado_nc = $("#id_encabezado_nc" + id).val();
			var pagina = $("#pagina").val();

		if (ruc_cliente =='9999999999999') {
				alert("❌ No es permitido emitir una nota de crédito a Consumidor Final.");
				return false;
			}

			if (confirm("Seguro desea enviar la nota de crédito " + serie_nc + '-' + secuencial_nc + " al SRI?")) {
				$.ajax({
					type: "POST",
					url: "../facturacion_electronica/procesarEnvioSri.php",
					data: "id_documento_sri=" + id + "&modo_envio=online&tipo_documento_sri=nc",
					beforeSend: function(objeto) {
						$("#loader").html("Enviando nota de crédito...");
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
					url: "../ajax/buscar_nc.php?action=cancelar_envio_sri",
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
			var q = $("#nc").val();
			var estado = $("#estado").val();
			var anio_nc = $("#anio_nc").val();
			var mes_nc = $("#mes_nc").val();
			var page = $("#pagina").val();
			$.ajax({
				url: '../ajax/buscar_nc.php?action=buscar_nota_credito&page=' + page + '&q=' + q + "&por=" + por + "&ordenado=" + ordenado + '&estado=' + estado + '&anio_nc=' + anio_nc + '&mes_nc=' + mes_nc,
				beforeSend: function(objeto) {
					$('#loader_nc').html('');
				},
				success: function(data) {
					$(".outer_div_nc").html(data).fadeIn('slow');
					$('#loader_nc').html('');
				}
			});
		}

		setInterval(actualizar_estado_sri, 5000); //para actualizar los estados del sri cada 5 segundos
	</script>