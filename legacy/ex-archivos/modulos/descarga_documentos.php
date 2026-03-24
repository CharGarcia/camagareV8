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
		<title>Descargas</title>

		<?php include("../paginas/menu_de_empresas.php");
		ini_set('date.timezone', 'America/Guayaquil');
		?>
	</head>

	<body>
		<div class="container-fluid">
			<div class="panel panel-info">
				<div class="panel-heading">
					<h4><i class='glyphicon glyphicon-search'></i> Descarga de documentos</h4>
				</div>
				<div class="panel-body">
					<form class="form" role="form">
						<div class="form-group row">
							<div class="col-md-2">
								<div class="input-group">
									<span class="input-group-addon"><b>Desde</b></span>
									<input type="text" name="desde" id="desde" class="form-control datepicker input-sm text-center" value="<?php echo date("01-m-Y") ?>" autocomplete="off">
								</div>
							</div>
							<div class="col-md-2">
								<div class="input-group">
									<span class="input-group-addon"><b>Hasta</b></span>
									<input type="text" name="hasta" id="hasta" class="form-control datepicker input-sm text-center" value="<?php echo date("d-m-Y") ?>" autocomplete="off">
								</div>
							</div>
							<div class="col-md-3">
								<div class="input-group">
									<span class="input-group-addon"><b>Documento</b></span>
									<select class="form-control input-sm" name="documento" id="documento" required>
										<option value="1" selected>Facturas Ventas</option>
										<option value="3">Retenciones Compras</option>
										<option value="5">Notas de crédito ventas</option>
										<!-- <option value="7">Guías de remisión ventas</option> -->
									</select>
								</div>
							</div>
							<div class="col-md-2">
								<div class="input-group">
									<span class="input-group-addon"><b>Tipo</b></span>
									<select class="form-control input-sm" name="tipo_formato" id="tipo_formato" required>
										<option value="1" selected>Pdf</option>
										<option value="2">Xml</option>
										<option value="3">Todos</option>
									</select>
								</div>
							</div>
							<div class="col-md-3">
								<button type="button" class="btn btn-default input-sm" onclick='search();'><span class="glyphicon glyphicon-search"></span> Buscar</button>
								<button type="button" class="btn btn-primary input-sm" onclick='procesar_comp();'><span class="glyphicon glyphicon-cog"></span> Generar <span class="badge" id="count"></span></button>
								<input type="hidden" name="id_comp[]" id="id_comp" value="">
							</div>

						</div>
						<div class="form-group row">
							<div class="col-md-7">
								<div class="input-group">
									<span class="input-group-addon"><b>Cliente/Proveedor</b></span>
									<input type="hidden" name="id_cliente" id="id_cliente" class="form-control input-sm">
									<input type="text" name="cliente" id="cliente" class="form-control input-sm" onkeyup='buscar_clientes_proveedor();'>
								</div>
							</div>


						</div>
						<div class="form-group row">
							<div class="col-md-12 text-center">
								<div id="loader"></div>
							</div>
						</div>
					</form>

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
	<!-- 	<link rel="stylesheet" href="//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">
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
				dateFormat: 'dd-mm-yy'
			});
			$("#hasta").datepicker({
				dateFormat: 'dd-mm-yy'
			});
		});

		function search() {
			$("#loader").fadeIn('slow');
			var desde = $("#desde").val();
			var hasta = $("#hasta").val();
			var documento = $("#documento").val();
			var id_cliente = $("#id_cliente").val();
			var count = 0;
			$.ajax({
				url: '../ajax/buscar_comprobantes_para_descargar.php?action=buscar_comp&desde=' + desde + '&hasta=' + hasta + '&documento=' + documento + '&id_cliente=' + id_cliente,
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
					content += '<th>Documento</th>';
					content += '<th>Total</th>';
					content += '<th>Enviar</th>';
					content += '</tr>';

					$.each(data, function(i, item) {
						content += '<tr>';
						content += '<td>' + item.fecha + '</td>';
						content += '<td>' + item.cliente + '</td>';
						content += '<td>' + item.num_doc + '</td>';
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

		//descargar comprobantes
		function procesar_comp() {
			var comp_select = $('#id_comp').val();
			var documento = $("#documento").val();
			var tipo_formato = $("#tipo_formato").val();

			if (comp_select == '') {
				$.notify('No hay comprobantes seleccionados para procesar.', 'error');
			} else {

				if (comp_select != '') {

					var progreso = 0;
					var total_documentos = parseInt(100 / $("#count").text());

					$.ajax({
						type: "POST",
						dataType: "html",
						url: '../ajax/buscar_comprobantes_para_descargar.php?action=procesar_comp',
						data: {
							comp_select: comp_select,
							documento: documento,
							tipo_formato: tipo_formato
						},

						beforeSend: function() {
							$('#loader').html('<div class="progress"><div class="progress-bar progress-bar-info progress-bar-striped active" role="progressbar" style="width:100%;">Descargando documentos, espere por favor...</div></div>');
						},

						success: function(datos) {
							$("#resultados").html(datos);
							$('#loader').html('');
						}

					})
				} else {
					$.notify('No hay comprobantes seleccionados para procesar', 'error');
				}
			}
		}

		//para buscar los clientes
		function buscar_clientes_proveedor() {
			var documento = $('#documento').val();

			if (documento == "1") {
				$("#cliente").autocomplete({
					source: '../ajax/clientes_autocompletar.php',
					minLength: 2,
					select: function(event, ui) {
						event.preventDefault();
						$('#id_cliente').val(ui.item.id);
						$('#cliente').val(ui.item.nombre);
					}
				});
			}
			if (documento == "3") {
				$("#cliente").autocomplete({
					source: '../ajax/proveedores_autocompletar.php',
					minLength: 2,
					select: function(event, ui) {
						event.preventDefault();
						$('#id_cliente').val(ui.item.id_proveedor);
						$('#cliente').val(ui.item.razon_social);
					}
				});
			}

			if (documento == "5") {
				$("#cliente").autocomplete({
					source: '../ajax/clientes_autocompletar.php',
					minLength: 2,
					select: function(event, ui) {
						event.preventDefault();
						$('#id_cliente').val(ui.item.id);
						$('#cliente').val(ui.item.nombre);
					}
				});
			}

			$("#cliente").on("keydown", function(event) {
				if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_cliente").val("");
					$("#cliente").val("");
				}
				if (event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_cliente").val("");
					$("#cliente").val("");
				}
			});
		}
/* 
		function descarga_comprimidos(documentos, tipo_documento, tipo_formato) {
			$.ajax({
				type: "POST",
				url: "../ajax/imprime_documento.php?action=descargar_varios_documentos&documentos=" + documentos + "&tipo_documento=" + tipo_documento + "&tipo_formato=" + tipo_formato,
				beforeSend: function(objeto) {
					$('#loader').html('<div class="progress"><div class="progress-bar progress-bar-info progress-bar-striped active" role="progressbar" style="width:100%;">Generando documento zip, espere por favor...</div></div>');
				},
				success: function(datos) {
					$("#resultados").html(datos).fadeIn('slow');
					$("#loader").html("");

				}
			});
		} */
	</script>