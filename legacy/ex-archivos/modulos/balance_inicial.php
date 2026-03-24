<?php
session_start();
if (isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa']) && isset($_SESSION['ruc_empresa'])) {
	$id_usuario = $_SESSION['id_usuario'];
	$id_empresa = $_SESSION['id_empresa'];
	$ruc_empresa = $_SESSION['ruc_empresa'];

?>
	<!DOCTYPE html>
	<html lang="en">

	<head>
		<meta charset="utf-8">
		<title>Balance inicial</title>
		<?php
		include("../paginas/menu_de_empresas.php");
		include("../modal/nuevo_diario.php");
		include("../modal/detalle_documento_contable.php");
		?>
		<style type="text/css">
			ul.ui-autocomplete {
				z-index: 1100;
			}
		</style>
	</head>

	<body>
		<div class="container-fluid">
			<div class="panel panel-info">
				<div class="panel-heading">
					<div class="btn-group pull-right">
						<button type='submit' class="btn btn-info" onclick="iniciar_formulario();" data-toggle="modal" data-target="#balanceInicial"><span class="glyphicon glyphicon-plus"></span> Nuevo</button>
					</div>
					<h4><i class='glyphicon glyphicon-search'></i> Balance inicial</h4>
				</div>
				<div class="tab-content">
					<div class="tab-pane fade in active">
						<div class="panel-body">
							<form class="form-horizontal" role="form">
								<div class="form-group row">
									<div class="col-md-6">
										<input type="hidden" id="ordenado" value="id_diario">
										<input type="hidden" id="por" value="desc">
										<div class="input-group">
											<span class="input-group-addon"><b>Buscar:</b></span>
											<input type="text" class="form-control" id="q" placeholder="Fecha, detalle" onkeyup='load(1);'>
											<span class="input-group-btn">
												<button type="button" onclick='load(1);' class="btn btn-default"><span class="glyphicon glyphicon-search"></span> Buscar</button>
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
	<script src="../js/ordenado.js" type="text/javascript"></script>
	<script src="../js/siguiente_input.js" type="text/javascript"></script>
	</body>
	<style>
		.fixedHeight {
			padding: 1px;
			max-height: 200px;
			overflow: auto;
		}
	</style>

	</html>
	<script>
		$(document).ready(function() {
			load(1);
		});

		function load(page) {
			var q = $("#q").val();
			var d = $("#d").val();
			var oa = $("#oa").val();
			var ordenado = $("#ordenado").val();
			var por = $("#por").val();

			$("#loader").fadeIn('slow');
			$.ajax({
				url: "../ajax/balance_inicial.php?action=balance_inicial&page=" + page + "&q=" + q + "&ordenado=" + ordenado + "&por=" + por,
				beforeSend: function(objeto) {
					$('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
				},
				success: function(data) {
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');
				}
			});
		}


		//para borrar datos del formulario para nuevo
		function iniciar_formulario() {
			document.querySelector("#form_balance_inicial").reset();
		}



		//detalle de diario
		function detalle_asiento(codigo) {
			$("#loaderdet_contable").fadeIn('slow');
			$.ajax({
				url: '../ajax/detalle_documento_contable.php?action=detalle_asiento&codigo_unico=' + codigo,
				beforeSend: function(objeto) {
					$('#loaderdet_contable').html('<img src="../image/ajax-loader.gif"> Cargando detalle de diario...');
				},
				success: function(data) {
					$(".outer_divdet_contable").html(data).fadeIn('slow');
					$('#loaderdet_contable').html('');
				}
			})
		}
		//eliminar asiento
		function eliminar_asiento(id) {
			var q = $("#q").val();
			if (confirm("Realmente desea anular el asiento contable?")) {
				$.ajax({
					type: "GET",
					url: "../ajax/buscar_libro_diario.php",
					data: "action=eliminar_asiento&id_diario=" + id,
					"q": q,
					beforeSend: function(objeto) {
						$('#loader').html('<img src="../image/ajax-loader.gif">Eliminando...');
					},
					success: function(datos) {
						$("#loader").html(datos);
						load(1);
					}
				});
			}
		}

		//generar balance inicial
		function generar_balance_inicial() {
			var ano_anterior = $("#ano_anterior").val();
			var ano_siguiente = $("#ano_siguiente").val();
			if (ano_anterior > ano_siguiente) {
				alert('El año 1 no puede ser mayor al año 2');
				return false;
			}

			if (ano_anterior == ano_siguiente) {
				alert('El año 1 no puede ser igual al año 2');
				return false;
			}

			if (ano_siguiente < ano_anterior) {
				alert('El año 2 no puede ser menor al año 1');
				return false;
			}


			$.ajax({
				type: "POST",
				url: "../ajax/balance_inicial.php",
				data: "action=generar_balance_inicial&ano_anterior=" + ano_anterior + "&ano_siguiente=" + ano_siguiente,
				beforeSend: function(objeto) {
					$('#mensaje_balance_inicial').html('Generando');
				},
				success: function(datos) {
					$("#mensaje_balance_inicial").html('');
					$("#resultados_ajax_balance_inicial").html(datos);
					load(1);
				}
			});
		}

		function obtener_datos(id) {
			document.querySelector("#form_nuevo_diario").reset();
			var codigo_unico = $("#mod_codigo_unico" + id).val();
			var concepto_general = $("#mod_concepto_general" + id).val();
			var fecha_asiento = $("#mod_fecha_asiento" + id).val();

			$("#codigo_unico").val(codigo_unico);
			$("#concepto_diario").val(concepto_general);
			$("#fecha_diario").val(fecha_asiento);

			$("#muestra_detalle_diario").fadeIn('fast');
			$.ajax({
				url: '../ajax/agregar_item_diario_tmp.php?action=cargar_detalle_diario&codigo_unico=' + codigo_unico,
				beforeSend: function(objeto) {
					$('#muestra_detalle_diario').html('<img src="../image/ajax-loader.gif"> Cargando...');
				},
				success: function(data) {
					$(".outer_divdet").html(data).fadeIn('fast');
					$('#muestra_detalle_diario').html('');
					document.getElementById('cuenta_diario').focus();
				}
			});
		}
	</script>