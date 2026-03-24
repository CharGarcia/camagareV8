<html lang="es">

<head>
	<title>Facturas programadas</title>
</head>

<?php
session_start();
if (isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa']) && isset($_SESSION['ruc_empresa'])) {
	$id_usuario = $_SESSION['id_usuario'];
	$id_empresa = $_SESSION['id_empresa'];
	$ruc_empresa = $_SESSION['ruc_empresa'];
	include("../paginas/menu_de_empresas.php");
	include("../modal/detalle_factura_programada.php");
	include("../modal/agregar_cliente_factura_programada.php");
?>

	<body>

		<div class="container">
			<div class="panel panel-info">
				<div class="panel-heading">
					<div class="btn-group pull-right">
						<form class="form-group" action="../modulos/opciones_facturas_programadas.php" method="POST">
							<div class="btn-group">
								<button type="button" class="btn btn-info dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><span class='glyphicon glyphicon-list-alt'></span> Opciones <span class="caret"></span></button>
								<ul class="dropdown-menu">
									<li><button type="submit" style="border:none;" class="btn btn-default btn-md btn-block"><span class='glyphicon glyphicon-film'></span> Generar facturas</button></li>
									<li><a href="#" data-toggle="modal" data-target="#NuevaFacturaProgramada"><span class="glyphicon glyphicon-pencil"></span> Agregar cliente</a></li>
								</ul>
							</div>
						</form>
					</div>

					<h4><i class='glyphicon glyphicon-search'></i> Facturas programadas</h4>
				</div>
				<div class="panel-body">
					<form class="form-horizontal" role="form">
						<div class="form-group row">
							<label for="q" class="col-md-1 control-label">Buscar:</label>
							<div class="col-md-5">
								<div class="input-group">
									<input type="text" class="form-control" id="q" placeholder="Cliente" onkeyup='load(1);'>
									<span class="input-group-btn">
										<button type="button" class="btn btn-default" onclick='load(1);'><span class="glyphicon glyphicon-search"></span> Buscar</button>
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

	</body>
	<style type="text/css">
		ul.ui-autocomplete {
			z-index: 1100;
		}
	</style>
	<link rel="stylesheet" href="../css/jquery-ui.css">
	<script src="../js/jquery-ui.js"></script>
	<script src="../js/notify.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>

</html>
<?php
} else {
	header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/sistema') . '/empresa');
}
?>
<script>
	$(function() {
		$("#nombre_producto_servicio").autocomplete({
			source: '../ajax/productos_autocompletar.php',
			minLength: 2,
			select: function(event, ui) {
				event.preventDefault();
				$('#id_producto').val(ui.item.id);
				$('#nombre_producto_servicio').val(ui.item.nombre);
				$('#precio').val(ui.item.precio);
			}
		});
	});

	$("#nombre_producto_servicio").on("keydown", function(event) {
		if (event.keyCode == $.ui.keyCode.LEFT || event.keyCode == $.ui.keyCode.RIGHT || event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE || event.keyCode == $.ui.keyCode.BACKSPACE) {
			$("#id_producto").val("");
			$("#nombre_producto_servicio").val("");
			$("#precio").val("");
		}
		if (event.keyCode == $.ui.keyCode.DELETE) {
			$("#nombre_producto_servicio").val("");
			$("#id_producto").val("");
			$("#precio").val("");
		}
	});

	$(document).ready(function() {
		load(1);
	});

	function load(page) {
		var q = $("#q").val();
		var cliente = $("#cli").val();
		$("#loaderCliente").fadeIn('slow');

		$("#loader").fadeIn('slow');
		$.ajax({
			url: '../ajax/buscar_facturas_programadas.php?action=ajax&page=' + page + '&q=' + q,
			beforeSend: function(objeto) {
				$('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
			},
			success: function(data) {
				$(".outer_div").html(data).fadeIn('slow');
				$('#loader').html('');

			}
		});
		//para buscar los clientes y agregar en facturas programadas
		$.ajax({
			url: '../ajax/buscar_clientes_factura_programada.php?actiones=ajax&pages=' + page + '&cli=' + cliente,
			beforeSend: function(objeto) {
				$('#loaderCliente').html('<img src="../image/ajax-loader.gif"> Cargando...');
			},
			success: function(data) {
				$(".outer_divcli").html(data).fadeIn('slow');
				$('#loaderCliente').html('');

			}
		})
	};

	function agrega_cliente_factura_programada(id) {
		var q = $("#cli").val();
		$.ajax({
			type: "POST",
			url: "../ajax/agregar_cliente_factura_programada.php",
			data: "id_cliente=" + id,
			"q": q,
			beforeSend: function(objeto) {
				$("#resultados").html("Mensaje: Cargando...");
			},
			success: function(datos) {
				$("#resultados").html(datos);
				load(1);
			}
		});
	}

	function eliminar_factura_programada(id_fp) {
		var q = $("#q").val();
		if (confirm("Realmente desea eliminar el cliente y detalle de la factura?")) {
			$.ajax({
				type: "GET",
				url: "../ajax/buscar_facturas_programadas.php",
				data: "id_fp=" + id_fp,
				"q": q,
				beforeSend: function(objeto) {
					$("#resultados").html("Mensaje: Cliente y detalle de factura eliminados...");
				},
				success: function(datos) {
					$("#resultados").html(datos);
					load(1);
				}
			});
		}
	}

	//pasa el id de la factura a modal/detalle_factura_programada.php y carga detalle a facturar
	function detalle_factura_programada(id_reg) {
		var id_registro = $("#codigo_cliente" + id_reg).val();
		$("#id_cliente_pf").val(id_registro);

		$("#muestra_detalle_factura_programada").fadeIn('fast');
		$.ajax({
			url: '../ajax/detalle_factura_programada.php?muestra_detalle_fp=OK&id_cliente=' + id_registro,
			beforeSend: function(objeto) {
				$('#muestra_detalle_factura_programada').html('<img src="../image/ajax-loader.gif"> Cargando... por favor espere a que se cargue la información.');
			},
			success: function(data) {
				$(".outer_divdet").html(data).fadeIn('fast');
				$('#muestra_detalle_factura_programada').html('');
			}
		})
	};

	//para agregar un producto al DETALLE por facturar
	function agregar_detalle_factura_programada() {
		var id_cliente = $("#id_cliente_pf").val();
		var id_producto = $("#id_producto").val();
		var cantidad_producto = $("#cantidad").val();
		var precio_producto = $("#precio").val();
		var periodo = $("#periodo").val();
		//Inicia validacion
		if (id_producto == '') {
			alert('Seleccione producto');
			document.getElementById('id_producto').focus();
			return false;
		}
		if (cantidad_producto == '') {
			alert('Ingrese cantidad');
			document.getElementById('cantidad').focus();
			return false;
		}
		if (isNaN(cantidad_producto)) {
			alert('El dato ingresado en cantidad, no es un número');
			document.getElementById('cantidad').focus();
			return false;
		}
		if (precio_producto == '') {
			alert('Ingrese precio');
			document.getElementById('precio').focus();
			return false;
		}
		if (isNaN(precio_producto)) {
			alert('El dato ingresado en precio, no es un número');
			document.getElementById('precio').focus();
			return false;
		}
		if (periodo == '') {
			alert('Seleccione período a facturar');
			document.getElementById('periodo').focus();
			return false;
		}
		//Fin validacion
		$("#muestra_detalle_factura_programada").fadeIn('fast');
		$.ajax({
			url: "../ajax/detalle_factura_programada.php?agregar_detalle_fp&id_cliente=" + id_cliente + "&id_producto=" + id_producto + "&cantidad_producto=" + cantidad_producto + "&precio_producto=" + precio_producto + "&periodo=" + periodo,
			beforeSend: function(objeto) {
				$("#muestra_detalle_factura_programada").html("Cargando detalle...");
			},
			success: function(data) {
				$(".outer_divdet").html(data).fadeIn('fast');
				$('#muestra_detalle_factura_programada').html('');
				document.getElementById("id_producto").value = "";
				document.getElementById("precio").value = "";
				document.getElementById("periodo").value = "";
				document.getElementById("nombre_producto_servicio").value = "";
				document.getElementById("cantidad").value = "1";
			}
		});
	};

	//pasa eliminar cada detalle de factura del alumno
	function eliminar_detalle_factura_programada(id_detalle_fp) {
		var id_detalle_fp = $("#id_detalle_fp" + id_detalle_fp).val();
		var id_cliente = $("#id_cliente_fp" + id_detalle_fp).val();
		$("#muestra_detalle_factura_programada").fadeIn('fast');
		$.ajax({
			url: '../ajax/detalle_factura_programada.php?eliminar_detalle_fp&id_detalle_fp=' + id_detalle_fp + '&id_cliente=' + id_cliente,
			beforeSend: function(objeto) {
				$("#muestra_detalle_factura_programada").html("Cargando detalle...");
			},
			success: function(data) {
				$(".outer_divdet").html(data).fadeIn('fast');
				$('#muestra_detalle_factura_programada').html('');
			}
		});
	};


	function actualiza_precio_factura_programada(id) {
		var precio_actual = $("#precio_actual" + id).val();
		var precio_nuevo = $("#precio_nuevo" + id).val();
		var id_cliente = $("#id_cliente_fp" + id).val();

		if (precio_nuevo == "" || precio_nuevo < 0) {
			alert('La cantidad, debe ser mayor o igual a cero');
			$("#precio_nuevo" + id).val(precio_actual);
			document.getElementById('precio_nuevo' + id).focus();
			return false;
		}

		$.ajax({
			url: "../ajax/detalle_factura_programada.php",
			data: "action=actualiza_precio&id=" + id + "&precio_nuevo=" + precio_nuevo + '&id_cliente=' + id_cliente,
			beforeSend: function(objeto) {
				$("#muestra_detalle_factura_programada").html("Actualizando...");
			},
			success: function(data) {
				$(".outer_divdet").html(data).fadeIn('fast');
				$('#muestra_detalle_factura_programada').html('');
			}
		});
	}
</script>