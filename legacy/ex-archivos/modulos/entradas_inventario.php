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
		<title>Entradas inventarios</title>
		<?php include("../paginas/menu_de_empresas.php");
		ini_set('date.timezone', 'America/Guayaquil');
		include("../modal/entradas_inventario.php");
		include("../modal/editar_entradas_inventario.php");
		?>
		<style type="text/css">
			ul.ui-autocomplete {
				z-index: 1100;
			}
		</style>
	</head>

	<body>

		<div class="container-fluid">
			<div class="panel panel-success">
				<div class="panel-heading">
					<div class="btn-group pull-right">
						<button type='submit' class="btn btn-success" data-toggle="modal" data-target="#NuevaEntrada"><span class="glyphicon glyphicon-plus"></span> Nueva entrada</button>
					</div>
					<h4><i class='glyphicon glyphicon-search'></i> Entradas de inventarios</h4>
				</div>
				<div class="panel-body">
					<form class="form-horizontal" role="form" method="POST" action="">
						<div class="form-group row">
							<div class="col-md-2">
								<div class="input-group">
									<span class="input-group-addon"><b>Bodega</b></span>
									<select class="form-control input-sm" id="bodega_entrada" name="bodega_entrada" onchange='load(1);'>
										<option value="" selected>Todos</option>
										<?php
										$tipo = mysqli_query($con, "SELECT distinct inv.id_bodega as id_bodega, bod.nombre_bodega as nombre_bodega 
										FROM inventarios as inv INNER JOIN bodega as bod 
										ON bod.id_bodega=inv.id_bodega WHERE inv.ruc_empresa ='" . $ruc_empresa . "' and inv.operacion='ENTRADA' order by bod.nombre_bodega asc");
										while ($row = mysqli_fetch_array($tipo)) {
										?>
											<option value="<?php echo $row['id_bodega'] ?>"><?php echo strtoupper($row['nombre_bodega']) ?></option>
										<?php
										}
										?>
									</select>
								</div>
							</div>
							<div class="col-md-2">
								<div class="input-group">
									<span class="input-group-addon"><b>Año</b></span>
									<select class="form-control input-sm" id="anio_entrada" name="anio_entrada" onchange='load(1);'>
										<option value="">Todos</option>
										<?php
										$currentYear = date('Y');
										$tipo = mysqli_query($con, "SELECT distinct(year(fecha_registro)) as anio FROM inventarios WHERE ruc_empresa ='" . $ruc_empresa . "' and operacion='ENTRADA' order by year(fecha_registro) desc");
										while ($row = mysqli_fetch_array($tipo)) {
											$selected = ($row['anio'] == $currentYear) ? 'selected' : '';
										?>
											<option value="<?php echo $row['anio']; ?>" <?php echo $selected; ?>><?php echo strtoupper($row['anio']) ?></option>
										<?php
										}
										?>
									</select>
								</div>
							</div>
							<div class="col-md-2">
								<div class="input-group">
									<span class="input-group-addon"><b>Mes</b></span>
									<select class="form-control input-sm" id="mes_entrada" name="mes_entrada" onchange='load(1);'>
										<option value="" selected>Todos</option>
										<?php
										$tipo = mysqli_query($con, "SELECT distinct(DATE_FORMAT(fecha_registro, '%m')) as mes FROM inventarios WHERE ruc_empresa ='" . $ruc_empresa . "' and operacion='ENTRADA' order by month(fecha_registro) asc");
										while ($row = mysqli_fetch_array($tipo)) {
										?>
											<option value="<?php echo $row['mes']; ?>"><?php echo strtoupper($row['mes']) ?></option>
										<?php
										}
										?>
									</select>
								</div>
							</div>
							<div class="col-md-5">
								<input type="hidden" id="ordenado" value="inv.id_inventario">
								<input type="hidden" id="por" value="desc">
								<div class="input-group">
									<span class="input-group-addon"><b>Buscar:</b></span>
									<input type="text" class="form-control input-sm" id="q" placeholder="Nombre, referencia" onkeyup='load(1);'>
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
		$('#fecha_entrada').css('z-index', 1500);
		$('#fecha_caducidad').css('z-index', 1500);
		$('#mod_fecha_entrada').css('z-index', 1500);
		$('#mod_fecha_caducidad').css('z-index', 1500);
		$('#entrada_desde').css('z-index', 1500);
		$('#entrada_hasta').css('z-index', 1500);

		jQuery(function($) {
			$("#fecha_entrada").mask("99-99-9999");
			$("#fecha_caducidad").mask("99-99-9999");
			$("#mod_fecha_entrada").mask("99-99-9999");
			$("#mod_fecha_caducidad").mask("99-99-9999");
			$("#entrada_desde").mask("99-99-9999");
			$("#entrada_hasta").mask("99-99-9999");
		});

		$(function() {
			$("#entrada_desde").datepicker({
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
			$("#entrada_desde").datepicker("setDate", "-1m");

			$("#entrada_hasta").datepicker({
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


			$("#fecha_entrada").datepicker({
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


			$("#fecha_caducidad").datepicker({
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

			$("#mod_fecha_entrada").datepicker({
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

			$("#mod_fecha_caducidad").datepicker({
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


		function agregar_productos() {
			var keycode = event.keyCode;
			var codigo_producto = $("#nombre_producto").val();
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
							document.querySelector("#id_producto").value = objProducto.id_producto;
							document.querySelector("#nombre_producto").value = objProducto.nombre_producto;
							document.querySelector("#codigo_producto").value = objProducto.codigo_producto;
							document.querySelector("#unidad_medida").value = objProducto.nombre_medida;
							document.getElementById('cantidad').focus();
						} else {
							$.notify(objData.msg, "error");
						}
					}
					return false;
				}
			}


			$("#nombre_producto").autocomplete({
				source: '../ajax/productos_autocompletar_inventario.php',
				minLength: 2,
				select: function(event, ui) {
					event.preventDefault();
					$('#id_producto').val(ui.item.id);
					$('#codigo_producto').val(ui.item.codigo);
					$('#nombre_producto').val(ui.item.nombre);
					$('#unidad_medida').val(ui.item.unidad_medida);
					var producto = $("#id_producto").val();
					var consultar_costo = "consultar_costo";

					//para ver el costo dell producto del ultimo registro
					$.post('../ajax/buscar_entradas_inventarios.php', {
						action: consultar_costo,
						id_producto: producto
					}).done(function(respuesta_costo) {
						var costo_producto = respuesta_costo;
						$("#costo_producto").val(costo_producto);
					});
					document.getElementById('cantidad').focus();
				}

			});

			$("#nombre_producto").on("keydown", function(event) {
				if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_producto").val("");
					$("#nombre_producto").val("");
					$("#codigo_producto").val("");
					$("#unidad_medida").val("");
					$("#costo_producto").val("");
				}
				if (event.keyCode == $.ui.keyCode.DELETE) {
					$("#nombre_producto").val("");
					$("#id_producto").val("");
					$("#codigo_producto").val("");
					$("#unidad_medida").val("");
					$("#costo_producto").val("");
				}
			});
		}


		$(document).ready(function() {
			window.addEventListener("keypress", function(event) {
				if (event.keyCode == 13) {
					event.preventDefault();
				}
			}, false);
			load(1);
		});


		function load(page) {
			var q = $("#q").val();
			var por = $("#por").val();
			var ordenado = $("#ordenado").val();
			var bodega_entrada = $("#bodega_entrada").val();
			var anio_entrada = $("#anio_entrada").val();
			var mes_entrada = $("#mes_entrada").val();
			$("#loader").fadeIn('slow');
			$.ajax({
				url: '../ajax/buscar_entradas_inventarios.php?action=buscar_entradas&page=' + page + '&q=' + q +
					'&ordenado=' + ordenado + '&por=' + por + '&bodega_entrada=' + bodega_entrada + '&anio_entrada=' + anio_entrada + '&mes_entrada=' + mes_entrada,
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

		$("#guardar_entrada").submit(function(event) {
			$('#guardar_datos').attr("disabled", true);
			var parametros = $(this).serialize();
			$.ajax({
				type: "POST",
				url: "../ajax/nueva_entrada_inventario.php",
				data: parametros,
				beforeSend: function(objeto) {
					$("#resultados_ajax_entradas").html("Mensaje: Guardando...");
				},
				success: function(datos) {
					$("#resultados_ajax_entradas").html(datos);
					$('#guardar_datos').attr("disabled", false);
					load(1);
				}
			});
			event.preventDefault();
		});

		//para eliminar una entrada
		function eliminar_entrada(id) {
			var q = $("#q").val();
			var tipo_registro = $("#tipo_registro" + id).val();
			//Inicia validacion

			if (confirm("Realmente desea eliminar la entrada de inventario?")) {
				$.ajax({
					type: "GET",
					url: '../ajax/buscar_entradas_inventarios.php?action=eliminar_entrada',
					data: "id_entrada=" + id,
					"q": q,
					beforeSend: function(objeto) {
						//$("#resultados").html("Mensaje: Eliminando...");
						$('#loader').html('<img src="../image/ajax-loader.gif"> Eliminando...');
					},
					success: function(datos) {
						$("#resultados").html(datos);
						$("#loader").html('');
						load(1);
					}
				});
			}

		}

		function obtener_datos(id) {
			var id_inventario = $("#id_inventario" + id).val();
			var nombre_producto = $("#nombre_producto" + id).val();
			var fecha_registro = $("#fecha_registro" + id).val();
			var fecha_vencimiento = $("#fecha_vencimiento" + id).val();
			var cantidad = $("#cantidad" + id).val();
			var costo_unitario = $("#costo_unitario" + id).val();
			var tipo_medida = $("#tipo_medida" + id).val();
			var medida = $("#medida" + id).val();
			var lote = $("#lote" + id).val();
			var bodega = $("#bodega" + id).val();
			var referencia = $("#referencia" + id).val();
			$("#mod_id_inventario").val(id_inventario);
			$("#mod_nombre_producto").val(nombre_producto);
			$("#mod_fecha_entrada").val(fecha_registro);
			$("#mod_fecha_caducidad").val(fecha_vencimiento);
			$("#mod_cantidad").val(cantidad);
			$("#mod_costo_producto").val(costo_unitario);
			$("#mod_tipo_medida").val(tipo_medida);
			$("#mod_unidad_medida").val(medida);
			$("#mod_bodega").val(bodega);
			$("#mod_lote").val(lote);
			$("#mod_referencia").val(referencia);

			$.post('../ajax/select_tipo_medida.php', {
				tipo_med: tipo_medida
			}).done(function(respuesta) {
				$("#mod_unidad_medida").html(respuesta);
			});


		}

		//editar una entrada
		$("#editar_entrada").submit(function(event) {
			$('#guardar_datos').attr("disabled", true);
			var parametros = $(this).serialize();
			$.ajax({
				type: "POST",
				url: "../ajax/editar_entradas_inventario.php",
				data: parametros,
				beforeSend: function(objeto) {
					$("#resultados_ajax_editar_entradas").html("Mensaje: Guardando...");
				},
				success: function(datos) {
					$("#resultados_ajax_editar_entradas").html(datos);
					$('#guardar_datos').attr("disabled", false);
					load(1);
				}
			});
			event.preventDefault();
		});
	</script>