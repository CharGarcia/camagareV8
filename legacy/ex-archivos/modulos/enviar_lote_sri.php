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
		<title>Facturas en lote</title>

		<?php include("../paginas/menu_de_empresas.php");
		ini_set('date.timezone', 'America/Guayaquil');
		?>
	</head>

	<body>

		<div class="container">
			<div class="panel panel-info">
				<div class="panel-heading">

					<h4><i class='glyphicon glyphicon-search'></i> Documentos pendientes de autorización</h4>
				</div>
				<div class="panel-body">

					<form class="form" role="form">
						<div class="form-group row">
							<div class="col-md-2">
								<div class="input-group">
									<span class="input-group-addon"><b>Desde</b></span>
									<input type="text" name="desde" id="desde" class="form-control datepicker input-sm" value="<?php echo date("d-m-Y") ?>" autocomplete="off">
								</div>
							</div>
							<div class="col-md-2">
								<div class="input-group">
									<span class="input-group-addon"><b>Hasta</b></span>
									<input type="text" name="hasta" id="hasta" class="form-control datepicker input-sm" value="<?php echo date("d-m-Y") ?>" autocomplete="off">
								</div>
							</div>
							<div class="col-md-4">
								<div class="input-group">
									<span class="input-group-addon"><b>Documento</b></span>
									<select class="form-control input-sm" name="documento" id="documento" required>
										<option value="factura">Facturas de venta</option>
										<option value="retencion">Retenciones en compras</option>
										<option value="nc">Notas de crédito</option>
										<option value="gr">Guías de remisión</option>
										<option value="nd">Notas de débito</option>
										<option value="liquidacion">Liquidación compras/servicios</option>
									</select>
								</div>
							</div>

							<div class="col-md-1">
								<button type="button" class="btn btn-default input-sm" onclick='search();'><span class="glyphicon glyphicon-search"></span> Buscar</button>
							</div>
							<div class="col-md-2">
								<button type="button" class="btn btn-success input-sm" onclick='procesar_comp();'><span class="glyphicon glyphicon-cog"></span> Procesar <span class="badge" id="count"></span></button>
								<input type="hidden" name="id_comp[]" id="id_comp" value="">
							</div>
						</div>
					</form>
					<div class="form-group row">
						<div class="col-md-12">

							<div id="loader"></div>
						</div>
					</div>
					<div id='resultados'></div><!-- Carga los datos ajax -->
				</div>
			</div>
		</div>

	<?php
} else {
	header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/sistema') . '/empresa');
	exit;
}
	?>
	<!-- <link rel="stylesheet" href="//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">
	<script src="//code.jquery.com/ui/1.11.4/jquery-ui.js"></script>
	<script src="../js/notify.js"></script> -->

	<link rel="stylesheet" href="../css/jquery-ui.css">
	<script src="../js/jquery-ui.js"></script>
	<script src="../js/notify.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
	</body>

	</html>

	<script>
		jQuery(function($) {
			$("#desde").mask("99-99-9999");
			$("#hasta").mask("99-99-9999");
		});

		$(function() {
			$("#desde").datepicker({
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
			$("#hasta").datepicker({
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


		function search() {
			$("#loader").fadeIn('slow');
			var desde = $("#desde").val();
			var hasta = $("#hasta").val();
			var documento = $("#documento").val();
			var count = 0;
			$.ajax({
				url: '../ajax/buscar_comprobantes_pendientes.php?action=buscar_comp&desde=' + desde + '&hasta=' + hasta + '&documento=' + documento,
				beforeSend: function(objeto) {
					$('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
				},

				success: function(data) {
					$("#resultados").empty();
					var content = '';
					var id_comp = [];
					content += '<div class="panel panel-info">';
					content += '<div class="table-responsive">';
					content += '<table class="table table-hover">';
					content += '<tbody>';
					content += '<tr class="info">';
					content += '<th>Fecha</th>';
					content += '<th>Cliente/proveedor</th>';
					content += '<th>Número</th>';
					content += '<th>IdDocumento</th>';
					content += '<th>Documento</th>';
					content += '<th>Total</th>';
					content += '<th>Enviar</th>';
					content += '</tr>';

					$.each(data, function(i, item) {
						content += '<tr>';
						content += '<td>' + item.fecha + '</td>';
						content += '<td>' + item.cliente + '</td>';
						content += '<td>' + item.num_doc + '</td>';
						content += '<td>' + item.id + '</td>';
						content += '<td>' + item.documento + '</td>';
						content += '<td>' + item.total + '</td>';
						content += '<td><input type="checkbox" class="form-control" id="enviar_' + item.id + '" name="enviar_' + item.id + '" onclick="contar(' + item.id + ',' + count + ')"></td>';
						content += '</tr>';
					});

					content += '</tbody>';
					content += '</table>';
					content += '</div>';
					content += '</div>';
					$("#id_comp").val(id_comp);
					$("#count").html(count);
					$("#resultados").append(content);
					$('#loader').html('');

				}
			})
		};

		function contar(id, pos) {
			var id_comp_actuales = $('#id_comp').val();
			var id_comp = [];
			id_comp = id_comp_actuales.split(',');
			id_comp = JSON.parse("[" + id_comp + "]");

			var count_actual = parseInt($("#count").text());
			var new_count = 0;
			if ($("#enviar_" + id).is(':checked')) {
				new_count = count_actual + parseInt(1);
				id_comp.push(id);
			} else {
				new_count = count_actual - parseInt(1);
				var pos = id_comp.indexOf(id);
				id_comp.splice(pos, 1);
			}
			$("#id_comp").val(id_comp);
			$("#count").html(new_count);

		}

		//enviar comprobantes al sri
		function procesar_comp() {
			var comp_select = $('#id_comp').val(); // Obtiene los valores almacenados
			var documento = $("#documento").val(); // Tipo de documento

			// Limpiar el contenedor de resultados al hacer clic en procesar comprobantes
			$("#resultados").empty();

			// Validar si `comp_select` tiene valores
			if (!comp_select || comp_select.trim() === '') {
				$.notify('No hay comprobantes seleccionados para procesar.', 'error');
				return;
			}

			// Convertir la cadena separada por comas a un array
			comp_select = comp_select.split(','); // Divide la cadena en un array

			var r = confirm("¿Está seguro que desea procesar los comprobantes seleccionados?");
			if (r === true) {
				// Función recursiva para procesar documentos secuencialmente
				function procesarSecuencial(index) {
					if (index >= comp_select.length) {
						$('#loader').html(''); // Ocultar loader al finalizar
						$("#resultados").empty(); // Limpiar el contenedor al finalizar
						return;
					}

					var comp = comp_select[index]; // Documento actual
					$.ajax({
						type: "POST",
						url: '../facturacion_electronica/procesarEnvioSri.php',
						data: {
							id_documento_sri: comp,
							modo_envio: "online",
							tipo_documento_sri: documento
						},
						beforeSend: function() {
							if (index === 0) {
								$('#loader').html('<div class="progress"><div class="progress-bar progress-bar-success progress-bar-striped active" role="progressbar" style="width:100%;">Enviando documentos, espere por favor...</div></div>');
							}
						},
						success: function(datos) {
							$("#resultados").append('<div>' + datos + '</div>');
							procesarSecuencial(index + 1); // Procesar el siguiente documento
						},
						error: function() {
							$("#resultados").append('<div>Error al procesar el documento: ' + comp + '</div>');
							procesarSecuencial(index + 1); // Continuar con el siguiente documento
						}
					});
				}

				// Inicia el procesamiento secuencial desde el primer documento
				procesarSecuencial(0);
			}
		}



		/* 		function procesar_comp() {
					var comp_select = $('#id_comp').val();
					var documento = $("#documento").val();

					if (comp_select == '') {
						$.notify('No hay comprobantes seleccionados para procesar.', 'error');
					} else {
						var r = confirm("Esta seguro que desea procesar los comprobantes seleccionados?");
						if (r == true) {
							if (comp_select != '') {
								//var progreso = 0;
								//var total_documentos = parseInt(100 / $("#count").text());
								$.each(comp_select, function(index, comp) {
									$.ajax({
										type: "POST",
										url: '../facturacion_electronica/procesarEnvioSri.php',
										data: "id_documento_sri=" + comp + "&modo_envio=online" + "&tipo_documento_sri=" + documento,
										beforeSend: function() {
											if (index === 0) {
												$('#loader').html('<div class="progress"><div class="progress-bar progress-bar-success progress-bar-striped active" role="progressbar" style="width:100%;">Enviando documentos, espere por favor...</div></div>');
											}
										},
										success: function(datos) {
											$("#resultados").append('<div>' + datos + '</div>');
											if (index === comp_select.length - 1) {
												$('#loader').html('');
											}
										}
									});
								});

							} else {
								$.notify('No hay comprobantes seleccionados para procesar', 'error');
							}
						}
					}
				} */
	</script>