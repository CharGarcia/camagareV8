<?php
session_start();
if (isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa']) && isset($_SESSION['ruc_empresa'])) {
	$id_usuario = $_SESSION['id_usuario'];
	$id_empresa = $_SESSION['id_empresa'];
	$ruc_empresa = $_SESSION['ruc_empresa'];
?>
	<!DOCTYPE html>
	<html lang="es">

	<head>
		<title>Agregados</title>
		<?php
		include("../paginas/menu_de_empresas.php");
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
						<button type='submit' class="btn btn-info" data-toggle="modal" data-target="#agregados" onclick="carga_modal_agregados();"><span class="glyphicon glyphicon-plus"></span> Nuevo</button>
					</div>
					<h4><i class='glyphicon glyphicon-search'></i> Agregados en productos</h4>
				</div>
				<div class="panel-body">
					<?php
					include("../modal/agregados_producto.php");
					?>
					<form class="form-horizontal" method="POST">
						<div class="form-group row">
							<label for="q" class="col-md-1 control-label">Buscar:</label>
							<div class="col-md-5">
								<input type="hidden" id="ordenado" value="enc.id">
								<input type="hidden" id="por" value="desc">
								<div class="input-group">
									<input type="text" class="form-control" id="q" placeholder="Producto" onkeyup='load(1);'>
									<span class="input-group-btn">
										<button type="button" class="btn btn-default" onclick='load(1);'><span class="glyphicon glyphicon-search"></span> Buscar</button>
									</span>
								</div>
							</div>
							<span id="loader"></span>
						</div>
					</form>
					<div id="resultados"></div><!-- Carga los datos ajax -->
					<div class="outer_div"></div><!-- Carga los datos ajax -->
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
	<!--para que se vea con fondo blanco el autocomplete -->
	<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
	<script src="../js/ordenado.js" type="text/javascript"></script>
	<script src="../js/notify.js"></script>
	</body>

	</html>
	<script>
		$(document).ready(function() {
			load(1);
		});

		function load(page) {
			var q = $("#q").val();
			var ordenado = $("#ordenado").val();
			var por = $("#por").val();
			$("#loader").fadeIn('slow');
			$.ajax({
				url: '../ajax/agregados_producto.php?action=buscar_agregados_producto&page=' + page + '&q=' + q + "&ordenado=" + ordenado + "&por=" + por,
				beforeSend: function(objeto) {
					$('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
				},
				success: function(data) {
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');
				}
			})
		}


		function carga_modal_agregados() {
			document.querySelector("#titleModalAgregados_producto").innerHTML = "<i class='glyphicon glyphicon-ok'></i> Nuevo agregados en producto";
			document.querySelector("#guardar_agregados_producto").reset();
			document.querySelector("#idAgregados_producto").value = "";
			document.querySelector("#btnActionFormAgregados_producto").classList.replace("btn-info", "btn-primary");
			document.querySelector("#btnTextAgregados_producto").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Guardar";
			document.querySelector('#btnActionFormAgregados_producto').title = "Guardar";

			$("#muestra_detalle_agregados_producto").fadeIn('fast');
			$.ajax({
				url: "../ajax/agregados_producto.php?action=nuevo_agregados_producto",
				beforeSend: function(objeto) {
					$("#muestra_detalle_agregados_producto").html("Cargando detalle...");
				},
				success: function(data) {
					$(".outer_divdet_agregados_producto").html(data).fadeIn('fast');
					$('#muestra_detalle_agregados_producto').html('');
					document.getElementById('nombre_producto_principal').focus();
				}
			});
			
		}


		function buscar_producto_principal() {
			$("#nombre_producto_principal").autocomplete({
				source: '../ajax/productos_autocompletar_inventario.php',
				minLength: 2,
				select: function(event, ui) {
					event.preventDefault();
					$('#id_producto_principal').val(ui.item.id);
					$('#nombre_producto_principal').val(ui.item.nombre);
				}
			});

			$("#nombre_producto_principal").on("keydown", function(event) {
				if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_producto_principal").val("");
					$("#nombre_producto_principal").val("");
				}
			});
		}

		//para buscar productos
		function buscar_productos() {
			$("#nombre_producto").autocomplete({
				source: '../ajax/productos_autocompletar_inventario.php',
				minLength: 2,
				select: function(event, ui) {
					event.preventDefault();
					$('#id_producto').val(ui.item.id);
					$('#nombre_producto').val(ui.item.nombre);
					$('#codigo_producto').val(ui.item.codigo);
					var id_medida = ui.item.medida;

					$.post('../ajax/facturas.php?action=tipo_medida_producto', {
                        id_medida: id_medida
                    }).done(function(respuesta) {
                        $("#medida_producto").html(respuesta);
                    });

					document.getElementById('cantidad_agregar').focus();
				}
			});
			$("#nombre_producto").on("keydown", function(event) {
				if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_producto").val("");
					$("#nombre_producto").val("");
					$("#codigo_producto").val("");
				}
			});
		}

		//agrega un item
		function agregar_item() {
			var id_producto = $("#id_producto").val();
			var id_producto_principal = $("#id_producto_principal").val();
			var cantidad_agregar = $("#cantidad_agregar").val();
			var nombre_producto = $("#nombre_producto").val();
			var codigo_producto = $("#codigo_producto").val();
			var medida_producto = $("#medida_producto").val();
			//Inicia validacion
			if (id_producto == id_producto_principal) {
				alert('El producto que va agregar no puede ser igual al principal');
				document.getElementById('nombre_producto').focus();
				return false;
			}
			if (id_producto == '') {
				alert('Ingrese producto.');
				document.getElementById('nombre_producto').focus();
				return false;
			}
			if (cantidad_agregar == '') {
				alert('Ingrese cantidad.');
				document.getElementById('cantidad_agregar').focus();
				return false;
			}

			if (isNaN(cantidad_agregar)) {
				alert('El dato ingresado en cantidad, no es un número');
				document.getElementById('cantidad_agregar').focus();
				return false;
			}

			//Fin validacion
			$("#muestra_detalle_agregados_producto").fadeIn('fast');
			$.ajax({
				url: "../ajax/agregados_producto.php?action=agregar_producto&id_producto=" + id_producto + "&cantidad_agregar=" + cantidad_agregar + "&nombre_producto=" + nombre_producto + "&codigo_producto=" + codigo_producto+ "&id_medida="+medida_producto,
				beforeSend: function(objeto) {
					$("#muestra_detalle_agregados_producto").html("Cargando detalle...");
				},
				success: function(data) {
					$(".outer_divdet_agregados_producto").html(data).fadeIn('fast');
					$('#muestra_detalle_agregados_producto').html('');
					document.getElementById("nombre_producto").value = "";
					document.getElementById("cantidad_agregar").value = "";
					document.getElementById("id_producto").value = "";
					document.getElementById("codigo_producto").value = "";
					document.getElementById("medida_producto").value = "";
					document.getElementById('nombre_producto').focus();
				}
			});
		}

		//eliminar un item
		function eliminar_item(id) {
			//Inicia validacion
			if (id == '') {
				alert('Seleccione un producto para eliminar.');
				return false;
			}
			//Fin validacion
			$("#muestra_detalle_agregados_producto").fadeIn('fast');
			$.ajax({
				url: "../ajax/agregados_producto.php?action=eliminar_detalle_agregados&id=" + id,
				beforeSend: function(objeto) {
					$("#muestra_detalle_agregados_producto").html("Cargando detalle...");
				},
				success: function(data) {
					$(".outer_divdet_agregados_producto").html(data).fadeIn('fast');
					$('#muestra_detalle_agregados_producto').html('');
					document.getElementById("nombre_producto").value = "";
					document.getElementById("cantidad_agregar").value = "";
					document.getElementById("id_producto").value = "";
					document.getElementById("codigo_producto").value = "";
					document.getElementById('nombre_producto').focus();
				}
			});
		}

		//para guardar
		$("#guardar_agregados_producto").submit(function(event) {
			$('#btnActionFormAgregados_producto').attr("disabled", true);
			var parametros = $(this).serialize();
			$.ajax({
				type: "POST",
				url: "../ajax/agregados_producto.php?action=guardar_agregados_producto",
				data: parametros,
				beforeSend: function(objeto) {
					$("#loader_agregados").html("Guardando...");
				},
				success: function(datos) {
					$("#loader_agregados").html(datos);
					$("#loader_agregados").html('');
					$('#btnActionFormAgregados_producto').attr("disabled", false);
					load(1);
				}
			});
			event.preventDefault();
		});

		//eliminar 
		function eliminar_agregado(id) {
			if (confirm("Realmente desea anular el registro?")) {
				$.ajax({
					type: "GET",
					url: "../ajax/agregados_producto.php?action=eliminar_agregados_producto&id="+id,
					beforeSend: function(objeto) {
						$("#resultados").html("Mensaje: Cargando...");
					},
					success: function(datos) {
						$("#resultados").html(datos);
						load(1);
					}
				});
			}
		}

		function editar_agregados_producto(id) {
			document.querySelector('#titleModalAgregados_producto').innerHTML ="<i class='glyphicon glyphicon-edit'></i> Actualizar agregados en producto";
			document.querySelector("#guardar_agregados_producto").reset();
			document.querySelector("#idAgregados_producto").value = id;
			document.querySelector('#btnActionFormAgregados_producto').classList.replace("btn-primary", "btn-info");
			document.querySelector("#btnTextAgregados_producto").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Actualizar";

			var id_producto_principal = $("#id_producto_mod" + id).val();
			var producto = $("#producto_mod" + id).val();
			var status = $("#status_mod" + id).val();

			$("#id_producto_principal").val(id_producto_principal);
			$("#nombre_producto_principal").val(producto);
			$("#listStatus").val(status);

			$("#muestra_detalle_agregados_producto").fadeIn('fast');
			$.ajax({
				url: "../ajax/agregados_producto.php?action=editar_agregados_producto&id="+id,
				beforeSend: function(objeto) {
					$("#muestra_detalle_agregados_producto").html("Cargando detalle...");
				},
				success: function(data) {
					$(".outer_divdet_agregados_producto").html(data).fadeIn('fast');
					$('#muestra_detalle_agregados_producto').html('');
				}
			});

		}

		function detalle_agregados_producto(id) {
			$("#detalle_agregados_producto").fadeIn('fast');
			$.ajax({
				url: "../ajax/agregados_producto.php?action=detalle_agregados_producto&id="+id,
				beforeSend: function(objeto) {
					$("#detalle_agregados_producto").html("Cargando detalle...");
				},
				success: function(data) {
					$(".outer_detalle_agregados_producto").html(data).fadeIn('fast');
					$('#detalle_agregados_producto').html('');
				}
			});

		}
	</script>