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
		<title>Salidas inventario</title>
		<?php include("../paginas/menu_de_empresas.php");
		ini_set('date.timezone', 'America/Guayaquil');
		include("../modal/salidas_inventario.php");
		include("../modal/editar_salidas_inventario.php");

		$con = conenta_login();
		if (isset($_SESSION['id_usuario'])) {
			$delete_factura_tmp = mysqli_query($con, "DELETE FROM factura_tmp WHERE id_usuario = '" . $id_usuario . "' and ruc_empresa = '" . $ruc_empresa . "'");
			$delete_adicional_tmp = mysqli_query($con, "DELETE FROM adicional_tmp WHERE id_usuario = '" . $id_usuario . "'");
			//$delete_propina_tasa_tmp = mysqli_query($con, "DELETE FROM propina_tasa_tmp WHERE id_usuario = '" . $id_usuario . "'");
		}
		?>
		<style type="text/css">
			ul.ui-autocomplete {
				z-index: 1100;
			}
		</style>
	</head>

	<body>

		<div class="container-fluid">
			<div class="panel panel-warning">
				<div class="panel-heading">
					<div class="btn-group pull-right">
						<button type='submit' class="btn btn-warning" data-toggle="modal" data-target="#NuevaSalida"><span class="glyphicon glyphicon-plus"></span> Nueva salida</button>
					</div>
					<h4><i class='glyphicon glyphicon-search'></i> Salidas de inventarios</h4>
				</div>
				<div class="panel-body">
					<form class="form-horizontal" role="form">
						<div class="form-group row">
							<div class="col-md-2">
								<div class="input-group">
									<span class="input-group-addon"><b>Bodega</b></span>
									<select class="form-control input-sm" id="bodega_salida" name="bodega_salida" onchange='load(1);'>
										<option value="" selected>Todos</option>
										<?php
										$tipo = mysqli_query($con, "SELECT distinct inv.id_bodega as id_bodega, bod.nombre_bodega as nombre_bodega 
										FROM inventarios as inv INNER JOIN bodega as bod 
										ON bod.id_bodega=inv.id_bodega WHERE inv.ruc_empresa ='" . $ruc_empresa . "' and inv.operacion='SALIDA' order by bod.nombre_bodega asc");
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
									<select class="form-control input-sm" id="anio_salida" name="anio_salida" onchange='load(1);'>
										<option value="">Todos</option>
										<?php
										$currentYear = date('Y');
										$tipo = mysqli_query($con, "SELECT distinct(year(fecha_registro)) as anio FROM inventarios WHERE ruc_empresa ='" . $ruc_empresa . "' and operacion='SALIDA' order by year(fecha_registro) desc");
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
									<select class="form-control input-sm" id="mes_salida" name="mes_salida" onchange='load(1);'>
										<option value="" selected>Todos</option>
										<?php
										$tipo = mysqli_query($con, "SELECT distinct(DATE_FORMAT(fecha_registro, '%m')) as mes FROM inventarios WHERE ruc_empresa ='" . $ruc_empresa . "' and operacion='SALIDA' order by month(fecha_registro) asc");
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
		$(function() {
			$('[data-toggle="tooltip"]').tooltip()
		})

		$('#fecha_salida').css('z-index', 1500);
		$('#mod_fecha_salida').css('z-index', 1500);
		$('#mod_fecha_caducidad').css('z-index', 1500);


		jQuery(function($) {
			$("#fecha_salida").mask("99-99-9999");
			$("#mod_fecha_salida").mask("99-99-9999");
			$("#mod_fecha_caducidad").mask("99-99-9999");
		});

		$(function() {
			$("#fecha_salida").datepicker({
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


			$("#mod_fecha_salida").datepicker({
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
							document.querySelector("#precio_producto").value = objProducto.precio_producto;

							//cuando se busca un producto y se cambia la medida se hace la conversion de medida
							$.post('../ajax/select_tipo_medida.php', {
								id_producto: objProducto.id_producto
							}).done(function(res_tipos_medidas) {
								$("#unidad_medida").html(res_tipos_medidas);
							});

							var bodega = $("#bodega").val();
							$.post('../ajax/saldo_producto_inventario.php', {
								id_bodega: bodega,
								id_producto: objProducto.id_producto
							}).done(function(respuesta) {
								$("#saldo_producto").val(respuesta);
								$('#stock_salida_tmp').val(respuesta);
							});

							//para traer todos los lotes en base a una bodega al momento de buscar un producto
							$.post('../ajax/select_opciones_inventario.php', {
								opcion: 'lote',
								id_producto: objProducto.id_producto,
								bodega: bodega
							}).done(function(res_opciones_lote) {
								$("#lote_salida").html(res_opciones_lote);
							});

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
					$('#precio_producto').val(ui.item.precio);
					$('#precio_salida_tmp').val(ui.item.precio);

					//cuando se busca un producto y se cambia la medida se hace la conversion de medida
					$.post('../ajax/select_tipo_medida.php', {
						id_producto: ui.item.id
					}).done(function(res_tipos_medidas) {
						$("#unidad_medida").html(res_tipos_medidas);
					});

					//para ver el saldo del producto que se quiere hacer la salida
					var bodega = $("#bodega").val();
					$.post('../ajax/saldo_producto_inventario.php', {
						id_bodega: bodega,
						id_producto: ui.item.id
					}).done(function(respuesta) {
						$("#saldo_producto").val(respuesta);
						$('#stock_salida_tmp').val(respuesta);
					});

					//para traer todos los lotes en base a una bodega al momento de buscar un producto
					$.post('../ajax/select_opciones_inventario.php', {
						opcion: 'lote',
						id_producto: ui.item.id,
						bodega: bodega
					}).done(function(res_opciones_lote) {
						$("#lote_salida").html(res_opciones_lote);
					});

					document.getElementById('cantidad').focus();
				}
			});
		}

		//para cuando se selecciona una bodega que me cargue el saldo de ese producto
		$(function() {
			$('#bodega').change(function() {
				var bodega = $("#bodega").val();
				var producto = $("#id_producto").val();
				$.post('../ajax/saldo_producto_inventario.php', {
					id_bodega: bodega,
					id_producto: producto
				}).done(function(respuesta) {
					var saldo_producto = respuesta;
					$("#saldo_producto").val(saldo_producto);
					$('#stock_salida_tmp').val(saldo_producto);
				});

				//para traer todos los lotes en base a una bodega al momento de buscar un producto
				$.post('../ajax/select_opciones_inventario.php', {
					opcion: 'lote',
					id_producto: producto,
					bodega: bodega
				}).done(function(res_opciones_lote) {
					$("#lote_salida").html(res_opciones_lote);
				});

			})

			//para traer el saldo y de acuerdo a la nueva medida
			$('#unidad_medida').change(function() {
				var id_medida = $("#unidad_medida").val();
				var id_producto = $("#id_producto").val();
				var precio_venta = $("#precio_salida_tmp").val();
				var stock_tmp = $("#stock_salida_tmp").val();

				$.post('../ajax/saldo_producto_inventario.php', {
					id_medida_seleccionada: id_medida,
					id_producto: id_producto,
					precio_venta: precio_venta,
					stock_tmp: stock_tmp,
					dato_obtener: 'saldo'
				}).done(function(respuesta_saldo) {
					$("#saldo_producto").val(respuesta_saldo);
				});

				$.post('../ajax/saldo_producto_inventario.php', {
					id_medida_seleccionada: id_medida,
					id_producto: id_producto,
					precio_venta: precio_venta,
					stock_tmp: stock_tmp,
					dato_obtener: 'precio'
				}).done(function(respuesta_precio) {
					$("#precio_producto").val(respuesta_precio);
				});
			});

			//para traer el valor de conversion de medidas en el producto cuando se cambia el select de lote
			$('#lote_salida').change(function() {
				var lote = $("#lote_salida").val();
				var producto = $("#id_producto").val();
				var bodega = $("#bodega").val();
				if (lote == "0") {
					var stock_tmp = $("#stock_salida_tmp").val();
					$("#saldo_producto").val(stock_tmp);
				} else {
					$.post('../ajax/saldo_producto_inventario.php', {
						opcion_lote: lote,
						id_producto: producto,
						bodega: bodega
					}).done(function(respuesta_lote) {
						$("#saldo_producto").val(respuesta_lote);
					});
				}

				//reinicia la medida
				$.post('../ajax/select_tipo_medida.php', {
					id_producto: producto
				}).done(function(res_id_medidas) {
					$("#unidad_medida").html(res_id_medidas);
				});
			});

		});


		$("#nombre_producto").on("keydown", function(event) {
			if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
				$("#id_producto").val("");
				$("#nombre_producto").val("");
				$("#precio_producto").val("");
				$("#codigo_producto").val("");
			}
			if (event.keyCode == $.ui.keyCode.DELETE) {
				$("#nombre_producto").val("");
				$("#id_producto").val("");
				$("#precio_producto").val("");
				$("#codigo_producto").val("");
			}
		});

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
			var ordenado = $("#ordenado").val();
			var por = $("#por").val();
			var bodega_salida = $("#bodega_salida").val();
			var anio_salida = $("#anio_salida").val();
			var mes_salida = $("#mes_salida").val();
			$("#loader").fadeIn('slow');
			$.ajax({
				url: '../ajax/buscar_salidas_inventarios.php?action=buscar_salidas&page=' + page + '&q=' + q +
					"&ordenado=" + ordenado + "&por=" + por + '&bodega_salida=' + bodega_salida + '&anio_salida=' + anio_salida + '&mes_salida=' + mes_salida,
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

		$("#guardar_salida").submit(function(event) {
			$('#guardar_datos').attr("disabled", true);
			var parametros = $(this).serialize();
			$.ajax({
				type: "POST",
				url: "../ajax/nueva_salida_inventario.php",
				data: parametros,
				beforeSend: function(objeto) {
					$("#resultados_ajax_salidas").html("Mensaje: Guardando...");
				},
				success: function(datos) {
					$("#resultados_ajax_salidas").html(datos);
					$('#guardar_datos').attr("disabled", false);
					load(1);
				}
			});
			event.preventDefault();
		});

		//para eliminar una salida
		function eliminar_salida(id) {
			var q = $("#q").val();
			var tipo_registro = $("#tipo_registro" + id).val();
			//Inicia validacion
			if (tipo_registro == "A") {
				alert('No es posible eliminar la salida, debe eliminar el registro de venta');
				return false;
			}
			if (confirm("Realmente desea eliminar la salida del inventario?")) {
				$.ajax({
					type: "GET",
					url: '../ajax/buscar_salidas_inventarios.php?action=eliminar_salida',
					data: "id_salida=" + id,
					"q": q,
					beforeSend: function(objeto) {
						$("#resultados").html("Mensaje: Eliminando...");
					},
					success: function(datos) {
						$("#resultados").html(datos);
						load(1);
					}
				});
			}

		}

		function obtener_datos(id) {
			var id_inventario = $("#id_inventario" + id).val();
			var id_producto = $("#id_producto" + id).val();
			var codigo_producto = $("#codigo_producto" + id).val();
			var nombre_producto = $("#nombre_producto" + id).val();
			var fecha_salida = $("#fecha_salida" + id).val();
			var fecha_caducidad = $("#fecha_caducidad" + id).val();
			var cantidad = $("#cantidad" + id).val();
			var precio = $("#precio" + id).val();
			var medida = $("#medida" + id).val();
			var lote = $("#lote" + id).val();
			var bodega = $("#bodega" + id).val();
			var referencia = $("#referencia" + id).val();
			$("#mod_id_inventario").val(id_inventario);
			$("#mod_codigo_producto").val(codigo_producto);
			$("#mod_nombre_producto").val(nombre_producto);
			$("#mod_fecha_salida").val(fecha_salida);
			$("#mod_fecha_caducidad").val(fecha_caducidad);
			$("#mod_lote").val(lote);
			$("#mod_cantidad").val(cantidad);
			$("#mod_precio_producto").val(precio);
			$("#mod_unidad_medida").val(medida);
			$("#mod_bodega").val(bodega);
			$("#mod_id_producto").val(id_producto);
			$("#mod_referencia").val(referencia);
		}

		//editar una salida
		$("#editar_salida").submit(function(event) {
			$('#guardar_datos').attr("disabled", true);
			var parametros = $(this).serialize();
			$.ajax({
				type: "POST",
				url: "../ajax/editar_salidas_inventario.php",
				data: parametros,
				beforeSend: function(objeto) {
					$("#resultados_ajax_editar_salidas").html("Mensaje: Guardando...");
				},
				success: function(datos) {
					$("#resultados_ajax_editar_salidas").html(datos);
					$('#guardar_datos').attr("disabled", false);
					load(1);
				}
			});
			event.preventDefault();
		});
	</script>