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
		<title>Existencias</title>
		<?php
		include("../paginas/menu_de_empresas.php");
		include("../modal/consignacion_venta.php");
		date_default_timezone_set('America/Guayaquil');
		//actualizar existencias




		?>
	</head>

	<body>
		<div class="container-fluid">
			<div class="panel panel-info">
				<div class="panel-heading">
					<h4><i class='glyphicon glyphicon-list-alt'></i> Existencias en consignación por ventas</h4>
				</div>
				<div class="panel-body">
					<form class="form-horizontal" role="form" method="POST" target="_blank" action="../excel/reporte_existencias_cv.php?action=existencia_consignacion_ventas_excel">
						<div class="form-group row">
							<input type="hidden" id="ordenado" name="ordenado" value="exi.cantidad">
							<input type="hidden" id="por" name="por" value="desc">
							<div class="col-sm-3">
								<div class="input-group">
									<span class="input-group-addon"><b>Buscar por:</b></span>
									<select class="form-control input-sm" id="tipo_existencia" name="tipo_existencia" required>
										<option value="1"> Clientes</option>
										<option value="2"> No. CV</option>
										<option value="3"> Producto</option>
										<option value="4"> NUP</option>
										<option value="5"> Lote</option>
										<option value="6" selected> Asesor</option>
									</select>
								</div>
							</div>
							<div class="col-sm-3">
								<div class="input-group">
									<span class="input-group-addon"><b>Asesor</b></span>
									<select class="form-control input-sm" name="asesor" id="asesor">
										<option value="" selected>Todos</option>
										<?php
										$con = conenta_login();
										$vendedores = mysqli_query($conexion, "SELECT * FROM vendedores where ruc_empresa ='" . $ruc_empresa . "'order by nombre asc ");
										while ($row_vendedores = mysqli_fetch_assoc($vendedores)) {
										?>
											<option value="<?php echo $row_vendedores['id_vendedor'] ?>"><?php echo $row_vendedores['nombre'] ?></option>
										<?php
										}
										?>
									</select>
								</div>
							</div>
							<div class="col-sm-4">
								<div class="input-group">
									<span class="input-group-addon" id="criterio"></span>
									<input type="hidden" name="id_nombre_buscar" id="id_nombre_buscar">
									<input type="hidden" id="id_cliente_tmp">
									<input type="hidden" id="id_asesor_tmp">
									<input type="text" class="form-control input-sm text-left" name="nombre_buscar" id="nombre_buscar" onkeyup='buscar_cliente();'>
								</div>
							</div>
							<div class="col-sm-2">
								<button type="button" title="Mostrar resultado" class="btn btn-info btn-sm" onclick="buscar_existencia()" id="mostrar_existencias"><span class="glyphicon glyphicon-search"></span></button>
								<button type="submit" title="Descargar excel" class="btn btn-success btn-sm" id="mostrar_existencias_excel"><img src="../image/excel.ico" width="16" height="16"></button>
								<span id="loader"></span>
							</div>

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
	</body>
	<link rel="stylesheet" href="../css/jquery-ui.css">
	<script src="../js/jquery-ui.js"></script>
	<script src="../js/notify.js"></script>
	<script src="../js/ordenado.js"></script>

	</html>
	<script>
		$(document).ready(function() {
			actualizar_existencia();
			document.querySelector("#criterio").innerHTML = "<b>Cliente</b>";
			document.getElementById("nombre_buscar").focus();
		});

		function actualizar_existencia() {
			$("#mostrar_existencias").hide();
			$("#mostrar_existencias_excel").hide();
			$("#loader").fadeIn('slow');
			$.ajax({
				url: '../ajax/buscar_existencias_consignacion.php?action=actualizar_existencia',
				beforeSend: function(objeto) {
					$('#loader').html('Actualizando existencias...');
				},
				success: function(data) {
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');
					$("#mostrar_existencias").show();
					$("#mostrar_existencias_excel").show();
				}
			})
		}

		function ordenar(ordenado) {
			$("#ordenado").val(ordenado);
			var por = $("#por").val();
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

		function buscar_existencia() {
			var tipo_existencia = $("#tipo_existencia").val();
			var id_nombre_buscar = $("#id_nombre_buscar").val();
			var nombre_buscar = $("#nombre_buscar").val();
			var asesor = $("#asesor").val();
			var ordenado = $("#ordenado").val();
			var por = $("#por").val();
			$("#loader").fadeIn('slow');
			$.ajax({
				url: '../ajax/buscar_existencias_consignacion.php?action=existencia_consignacion_ventas&tipo_existencia=' +
					tipo_existencia + '&id_nombre_buscar=' + id_nombre_buscar + "&nombre_buscar=" + nombre_buscar +
					'&asesor=' + asesor + '&ordenado=' + ordenado + '&por=' + por,
				beforeSend: function(objeto) {
					$('#loader').html('<img src="../image/ajax-loader.gif">');
				},
				success: function(data) {
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');
				}
			})
		}

		function load(page) {
			var id_cliente = $("#id_cliente_tmp").val();
			var id_asesor = $("#id_asesor_tmp").val();
			detalle_consignaciones(id_cliente, id_asesor, page);
		}

		function detalle_consignaciones(id_cliente, id_asesor, page) {
			$('#id_cliente_tmp').val(id_cliente);
			$('#id_asesor_tmp').val(id_asesor);
			var ordenado = $("#ordenado").val();
			var por = $("#por").val();
			$("#loader_cliente" + id_cliente).fadeIn('slow');
			$.ajax({
				url: '../ajax/buscar_existencias_consignacion.php?action=detalle_consignacion_cliente&id_cliente=' +
					id_cliente + '&id_asesor=' + id_asesor + '&page=' + page + '&ordenado=' + ordenado + '&por=' + por,
				beforeSend: function(objeto) {
					$('#loader_cliente' + id_cliente).html('Cargando información...');
				},
				success: function(data) {
					$(".listado_consignaciones").html(data).fadeIn('slow');
					$('#loader_cliente' + id_cliente).html('');
				}
			})
		}

		function buscar_cliente() {
			var nombre_informe = $("#tipo_existencia").val();
			//buscar por cliente
			if (nombre_informe == '1' || nombre_informe == '6') {
				$("#nombre_buscar").autocomplete({
					source: '../ajax/clientes_autocompletar.php',
					minLength: 2,
					select: function(event, ui) {
						event.preventDefault();
						$('#id_nombre_buscar').val(ui.item.id);
						$('#nombre_buscar').val(ui.item.nombre);
					}
				});
			}
			//buscar producto
			if (nombre_informe == '3') {
				$("#nombre_buscar").autocomplete({
					source: '../ajax/productos_autocompletar.php',
					minLength: 2,
					select: function(event, ui) {
						event.preventDefault();
						$('#id_nombre_buscar').val(ui.item.id);
						$('#nombre_buscar').val(ui.item.nombre);
					}
				});
			}

			//$("#nombre_buscar").autocomplete("widget").addClass("fixedHeight"); //para que aparezca la barra de desplazamiento en el buscar

			$("#nombre_buscar").on("keydown", function(event) {
				if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_nombre_buscar").val("");
					$("#nombre_buscar").val("");
				}
				if (event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_nombre_buscar").val("");
					$("#nombre_buscar").val("");
				}
			});
		}

		$('#tipo_existencia').change(function() {
			var tipo = $("#tipo_existencia").val();
			$("#id_nombre_buscar").val("");
			$("#nombre_buscar").val("");

			if (tipo == "1") {
				document.querySelector("#criterio").innerHTML = "<b>Cliente</b>";
			}
			if (tipo == "2") {
				document.querySelector("#criterio").innerHTML = "<b>No. consignación</b>";
			}
			if (tipo == "3") {
				document.querySelector("#criterio").innerHTML = "<b>Producto</b>";
			}
			if (tipo == "4") {
				document.querySelector("#criterio").innerHTML = "<b>NUP</b>";
			}
			if (tipo == "5") {
				document.querySelector("#criterio").innerHTML = "<b>Lote</b>";
			}
			if (tipo == "6") {
				document.querySelector("#criterio").innerHTML = "<b>Cliente</b>";
			}

			$(".outer_div").html('').fadeIn('slow');

			document.getElementById('nombre_buscar').focus();
		});


		//DETALLE consignacion_ventas
		function detalle_consignacion(numero_consignacion) {
			$.ajax({
				url: "../ajax/buscar_existencias_consignacion.php?action=detalle_consignacion_por_numero&numero_consignacion=" + numero_consignacion,
				beforeSend: function(objeto) {
					$("#loaderdet").html("Iniciando...");
				},
				success: function(data) {
					$(".outer_divdet").html(data).fadeIn('fast');
					$('#loaderdet').html('');
				}
			});
		}

		//para mostrar el numero de factura o devolucion en detalle de existencia
		function detalle_axistencia(documento, numero) {
			alert(documento + ': ' + numero);
		}
	</script>