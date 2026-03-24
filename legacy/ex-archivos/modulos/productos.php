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
		<title>Productos/servicios</title>
		<?php include("../paginas/menu_de_empresas.php"); ?>
	</head>

	<body>

		<div class="container-fluid">
			<div class="panel panel-info">
				<div class="panel-heading">
					<div class="btn-group pull-right">
						<?php
						if (getPermisos($con, $id_usuario, $ruc_empresa, 'productos')['w'] == 1) {
						?>
							<button type="submit" class="btn btn-info" data-toggle="modal" data-target="#productos" onclick="crear_producto();"><span class="glyphicon glyphicon-plus"></span> Nuevo Producto o Servicio</button>
						<?php
						}
						?>
					</div>
					<h4><i class="glyphicon glyphicon-search"></i> Productos y Servicios</h4>
				</div>
				<div class="panel-body">
					<?php
					include("../modal/productos.php");
					include("../modal/detalle_precios_producto.php");
					include("../modal/detalle_documento.php");
					?>
					<form class="form-horizontal">
						<div class="form-group row">
							<label for="q" class="col-md-1 control-label">Buscar:</label>
							<div class="col-md-5">
								<input type="hidden" id="ordenado" value="id">
								<input type="hidden" id="por" value="desc">
								<div class="input-group">
									<input type="text" class="form-control" id="q" placeholder="Nombre" onkeyup='load(1);'>
									<span class="input-group-btn">
										<button type="button" class="btn btn-default" onclick='load(1);'><span class="glyphicon glyphicon-search"></span> Buscar</button>
									</span>
								</div>
							</div>
							<div class="col-md-1">
								<a href="../excel/productos.php" class="btn btn-success" title='Descargar en Excel' target="_blank"><img src="../image/excel.ico" width="25" height="20"></a>
							</div>
							<div id="loader"></div>
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
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
	<script src="../js/notify.js"></script>
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

		jQuery(function($) {
			$("#aplica_desde").mask("99-99-9999");
			$("#aplica_hasta").mask("99-99-9999");
		});


		function crear_producto() {
			document.querySelector("#titleModalProducto").innerHTML = "<i class='glyphicon glyphicon-ok'></i> Nuevo producto o servicio";
			document.querySelector("#guardar_producto").reset();
			document.querySelector("#id_producto").value = "";
			document.querySelector("#btnActionFormProducto").classList.replace("btn-info", "btn-primary");
			document.querySelector("#btnTextProducto").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Guardar";
			document.querySelector('#btnActionFormProducto').title = "Guardar Producto";

			document.getElementById("label_marca_producto").style.display = "none";
			document.getElementById("label_medida_producto").style.display = "none";
			document.getElementById("label_unidad_producto").style.display = "none";
			document.getElementById("label_iva_producto").style.display = "none";
			nuevo_codigo();
		}

		$(function() {
			$("#aplica_desde").datepicker({
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

			$("#aplica_hasta").datepicker({
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

		function load(page) {
			var ordenado = $("#ordenado").val();
			var por = $("#por").val();
			var q = $("#q").val();
			$("#loader").fadeIn('slow');
			document.getElementById('q').focus();

			$.ajax({
				url: '../ajax/productos.php?action=buscar_productos&page=' + page + '&q=' + q + "&ordenado=" + ordenado + "&por=" + por,
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


		function eliminar_producto(id) {
			if (confirm("Realmente desea eliminar el producto o el servicio?")) {
				$.ajax({
					type: "POST",
					url: '../ajax/productos.php?action=eliminar_producto',
					data: "id=" + id,
					beforeSend: function(objeto) {
						$("#loader").html("Eliminando...");
					},
					success: function(datos) {
						$("#loader").html(datos);
						load(1);
					}
				});
			}
		}

		function editar_producto(id) {
			document.querySelector('#titleModalProducto').innerHTML = "<i class='glyphicon glyphicon-edit'></i> Actualizar Producto o Servicio";
			document.querySelector("#guardar_producto").reset();
			document.querySelector("#id_producto").value = id;
			document.querySelector('#btnActionFormProducto').classList.replace("btn-primary", "btn-info");
			document.querySelector("#btnTextProducto").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Actualizar";

			var codigo_producto = $("#codigo_producto_mod" + id).val();
			var nombre_producto = $("#nombre_producto_mod" + id).val();
			var precio_producto = $("#precio_producto_mod" + id).val();
			var tipo = $("#tipo_produccion_mod" + id).val();
			var unidad_medida = $("#id_unidad_medida_mod" + id).val();
			var tipo_medida = $("#tipo_medida_mod" + id).val();
			var tarifa_iva = $("#tarifa_iva_mod" + id).val();
			var marca = $("#marca_mod" + id).val();
			var grupo = $("#grupo_mod" + id).val();
			var status = $("#status_mod" + id).val();
			var codigo_auxiliar = $("#codigo_auxiliar_mod" + id).val();
			$("#id_producto").val(id);
			$("#codigo_producto").val(codigo_producto);
			$("#nombre_producto").val(nombre_producto);
			$("#precio_producto").val(precio_producto);
			$("#tipo_producto").val(tipo);
			$("#iva_producto").val(tarifa_iva);
			$("#marca_producto").val(marca);
			$("#grupo_producto").val(grupo);
			$("#tipo_medida_producto").val(tipo_medida);
			$("#unidad_medida_producto").val(unidad_medida);
			$("#status_producto").val(status);
			$("#codigo_auxiliar").val(codigo_auxiliar);

			var tarifa_iva = $("#iva_producto").val();
			if (tarifa_iva > 0) {
				document.getElementById("label_iva_producto").style.display = "";
				precio_sin_impuesto();
			} else {
				document.getElementById("label_iva_producto").style.display = "none";
			}

			if (tipo == '01') {
				document.getElementById("label_marca_producto").style.display = "";
				document.getElementById("label_medida_producto").style.display = "";
				document.getElementById("label_unidad_producto").style.display = "";
			}
			if (tipo == '02') {
				document.getElementById("label_marca_producto").style.display = "none";
				document.getElementById("label_medida_producto").style.display = "none";
				document.getElementById("label_unidad_producto").style.display = "none";
			}

			$.post('../ajax/productos.php?action=tipo_medida', {
				tipo_medida: tipo_medida,
				id_unidad_medida: unidad_medida
			}).done(function(respuesta) {
				$("#unidad_medida_producto").html(respuesta);
			});

		}


		//pasa el id del producto a modal/detalle_precios_producto y carga detalle de precios
		function detalle_precios(id_producto) {
			var nombre_producto = $("#nombre_producto_mod" + id_producto).val();
			var precio_producto = $("#precio_producto_mod" + id_producto).val();
			$("#nombre_producto_actual").val(nombre_producto);
			$("#precio_producto_actual").val(precio_producto);
			$("#id_producto_precio").val(id_producto);

			$("#muestra_detalle_precios").fadeIn('fast');
			$.ajax({
				url: '../ajax/detalle_documento.php?action=detalle_precios&id_producto=' + id_producto,
				beforeSend: function(objeto) {
					$('#muestra_detalle_precios').html('<img src="../image/ajax-loader.gif"> Cargando... por favor espere.');
				},
				success: function(data) {
					$(".outer_divdet").html(data).fadeIn('fast');
					$('#muestra_detalle_precios').html('');
				}
			})
		};

		//para agregar un producto al alumno por facturar
		function agregar_nuevo_precio() {
			var id_producto = $("#id_producto_precio").val();
			var precio_nuevo = $("#precio_nuevo").val();
			var aplica_desde = $("#aplica_desde").val();
			var aplica_hasta = $("#aplica_hasta").val();
			var detalle_precio = $("#detalle_precio").val();
			//Inicia validacion
			if (id_producto == '') {
				alert('Seleccione un producto.');
				return false;
			}
			if (precio_nuevo == '') {
				alert('Ingrese nuevo precio.');
				document.getElementById('precio_nuevo').focus();
				return false;
			}
			if (isNaN(precio_nuevo)) {
				alert('El dato ingresado en precio, no es un número');
				document.getElementById('precio_nuevo').focus();
				return false;
			}
			if (aplica_desde == '') {
				alert('Ingrese fecha inicial.');
				document.getElementById('aplica_desde').focus();
				return false;
			}
			if (aplica_hasta == '') {
				alert('Ingrese fecha final.');
				document.getElementById('aplica_hasta').focus();
				return false;
			}

			//Fin validacion
			$("#muestra_detalle_precios").fadeIn('fast');
			$.ajax({
				url: "../ajax/detalle_documento.php?action=agregar_nuevo_precio&id_producto=" + id_producto + "&precio_nuevo=" + precio_nuevo + "&aplica_desde=" + aplica_desde + "&aplica_hasta=" + aplica_hasta + "&detalle_precio=" + detalle_precio,
				beforeSend: function(objeto) {
					$("#muestra_detalle_precios").html("Cargando detalles...");
				},
				success: function(data) {
					$(".outer_divdet").html(data).fadeIn('fast');
					$('#muestra_detalle_precios').html('');
					document.getElementById("precio_nuevo").value = "";
					document.getElementById("aplica_desde").value = "";
					document.getElementById("aplica_hasta").value = "";
					document.getElementById("detalle_precio").value = "";
				}
			});
		};

		//pasa eliminar cada detalle precio
		function eliminar_precio(id_precio) {
			var id_producto = $("#id_producto" + id_precio).val();
			$("#muestra_detalle_precios").fadeIn('fast');
			$.ajax({
				url: "../ajax/detalle_documento.php?action=eliminar_precio&id_precio=" + id_precio + "&id_producto=" + id_producto,
				beforeSend: function(objeto) {
					$("#muestra_detalle_precios").html("Cargando detalles...");
				},
				success: function(data) {
					$(".outer_divdet").html(data).fadeIn('fast');
					$('#muestra_detalle_precios').html('');
				}
			});
		}

		function nuevo_codigo() {
			$.ajax({
				type: "POST",
				url: "../ajax/productos.php?action=nuevo_codigo",
				beforeSend: function() {
					$("#resultados_ajax_guarda_producto").html("Creando nuevo código...");
				},
				success: function(datos) {
					$("#codigo_producto").val(datos); // ✅ Muestra el código en el input
					$("#resultados_ajax_guarda_producto").html('');
				},
				error: function(xhr, status, error) {
					console.log("❌ Error AJAX:", error);
				}
			});
		}
	</script>