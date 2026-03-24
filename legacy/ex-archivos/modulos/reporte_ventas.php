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
		<title>Reporte ventas + NC</title>
		<?php include("../paginas/menu_de_empresas.php"); ?>
	</head>

	<body>

		<div class="container-fluid">
			<div class="panel panel-info">
				<div class="panel-heading">
					<h4><i class='glyphicon glyphicon-list-alt'></i> Reporte de ventas y Notas de crédito</h4>
				</div>

				<div class="panel-body">
					<form class="form-horizontal" method="POST" target="_blank" action="../excel/reporte_ventas_excel.php">
						<input type="hidden" name="id_cliente" id="id_cliente">
						<input type="hidden" name="id_producto" id="id_producto">
						<div class="form-group">
							<div class="col-sm-12">
								<div class="input-group">
									<span class="input-group-addon"><b>Reporte</b></span>
									<select class="form-control input-sm" id="tipo_reporte" name="tipo_reporte" required>
										<option value="1" selected> Facturas de venta</option>
										<option value="2"> Notas de crédito en ventas</option>
										<option value="5"> Recibos de venta</option>
										<option value="3"> Detalle de facturas de venta</option>
										<option value="4"> Detalle de Notas de crédito en venta</option>
										<option value="6"> Detalle de Recibos de venta</option>
									</select>

									<span class="input-group-addon"><b>Desde</b></span>
									<input type="text" class="form-control input-sm text-center" name="fecha_desde" id="fecha_desde" value="<?php echo date("01-m-Y"); ?>">

									<span class="input-group-addon"><b>Hasta</b></span>
									<input type="text" class="form-control input-sm text-center" name="fecha_hasta" id="fecha_hasta" value="<?php echo date("d-m-Y"); ?>">
									<span class="input-group-addon" id="label_id_marca"><b>Marca</b></span>
									<select class="form-control input-sm" title="Marca" name="id_marca" id="id_marca">
										<?php
										$sql_marca = mysqli_query($conexion, "SELECT * FROM marca where ruc_empresa='" . $ruc_empresa . "'");
										?> <option value="">Todos</option>
										<?php
										while ($tipo = mysqli_fetch_assoc($sql_marca)) {
										?>
											<option value="<?php echo $tipo['id_marca'] ?>"><?php echo strtoupper($tipo['nombre_marca']) ?> </option>
										<?php
										}
										?>
									</select>
									<span class="input-group-addon" id="label_id_grupo"><b>Grupo</b></span>
									<select class="form-control input-sm" title="Marca" name="id_grupo" id="id_grupo">
										<?php
										$sql_grupo = mysqli_query($conexion, "SELECT * FROM grupo_familiar_producto where ruc_empresa='" . $ruc_empresa . "'");
										?> <option value="">Todos</option>
										<?php
										while ($grupo = mysqli_fetch_assoc($sql_grupo)) {
										?>
											<option value="<?php echo $grupo['id_grupo'] ?>"><?php echo strtoupper($grupo['nombre_grupo']) ?> </option>
										<?php
										}
										?>
									</select>
								</div>
							</div>
						</div>

						<div class="form-group">
							<div class="col-sm-10">
								<div class="input-group">
									<span class="input-group-addon"><b>Cliente</b></span>
									<input type="text" class="form-control input-sm text-left" name="nombre_cliente" id="nombre_cliente" value="Todos" onkeyup='buscar_cliente();'>
									<span class="input-group-addon" id="label_producto"><b>Producto</b></span>
									<input type="text" class="form-control input-sm text-left" name="nombre_producto" id="nombre_producto" value="Todos" onkeyup='buscar_productos();'>

									<span class="input-group-addon"><b>Asesor</b></span>
									<select class="form-control input-sm" name="vendedor" id="vendedor">
										<option value="0" selected>Todos</option>
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
							<div class="col-sm-2">
								<button type="button" title="Mostrar resultado" class="btn btn-info btn-sm" onclick="mostrar_reporte()"><span class="glyphicon glyphicon-search"></span></button>
								<button type="submit" title="Descargar excel" class="btn btn-success btn-sm"><img src="../image/excel.ico" width="16" height="16"></button>
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
	<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
	<script src="../js/rango_fechas.js"></script>


	</html>
	<script>
		$(document).ready(function() {
			window.addEventListener("keypress", function(event) {
				if (event.keyCode == 13) {
					event.preventDefault();
				}
			}, false);

			document.getElementById("label_id_marca").style.display = "none";
			document.getElementById("id_marca").style.display = "none";
			document.getElementById("label_id_grupo").style.display = "none";
			document.getElementById("id_grupo").style.display = "none";
			document.getElementById("label_producto").style.display = "none";
			document.getElementById("nombre_producto").style.display = "none";
		});

		$('#tipo_reporte').change(function() {

			var tipo = $("#tipo_reporte").val();
			if (tipo == "3" || tipo == "4" || tipo == "6") {
				document.getElementById("label_id_marca").style.display = "";
				document.getElementById("id_marca").style.display = "";
				document.getElementById("label_id_grupo").style.display = "";
				document.getElementById("id_grupo").style.display = "";
				document.getElementById("label_producto").style.display = "";
				document.getElementById("nombre_producto").style.display = "";
				//document.getElementById('producto').focus();
			}

			if (tipo == "1" || tipo == "2" || tipo == "5") {
				document.getElementById("label_id_marca").style.display = "none";
				document.getElementById("id_marca").style.display = "none";
				document.getElementById("label_id_grupo").style.display = "none";
				document.getElementById("id_grupo").style.display = "none";
				document.getElementById("label_producto").style.display = "none";
				document.getElementById("nombre_producto").style.display = "none";
			}

		});

		jQuery(function($) {
			$("#fecha_desde").mask("99-99-9999");
			$("#fecha_hasta").mask("99-99-9999");
		});
		$(function() {
			$("#fecha_desde").datepicker({
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

		$(function() {
			$("#fecha_hasta").datepicker({
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

		function buscar_cliente() {
			$("#nombre_cliente").autocomplete({
				source: '../ajax/clientes_autocompletar.php',
				minLength: 2,
				select: function(event, ui) {
					event.preventDefault();
					$('#id_cliente').val(ui.item.id);
					$('#nombre_cliente').val(ui.item.nombre);
				}
			});
			$("#nombre_cliente").autocomplete("widget").addClass("fixedHeight"); //para que aparezca la barra de desplazamiento en el buscar

			$("#nombre_cliente").on("keydown", function(event) {
				if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_cliente").val("");
					$("#nombre_cliente").val("");
				}
				if (event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_cliente").val("");
					$("#nombre_cliente").val("");
				}
			});
		}


		function buscar_productos() {
			$("#nombre_producto").autocomplete({
				source: '../ajax/productos_autocompletar.php',
				minLength: 2,
				select: function(event, ui) {
					event.preventDefault();
					$('#id_producto').val(ui.item.id);
					$('#nombre_producto').val(ui.item.nombre);
				}
			});

			$("#nombre_producto").autocomplete("widget").addClass("fixedHeight"); //para que aparezca la barra de desplazamiento en el buscar

			$("#nombre_producto").on("keydown", function(event) {
				if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_producto").val("");
					$("#nombre_producto").val("");
				}
				if (event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_producto").val("");
					$("#nombre_producto").val("");
				}
			});
		}

		//generar informe
		function mostrar_reporte() {
			var tipo_reporte = $("#tipo_reporte").val();
			var id_cliente = $("#id_cliente").val();
			var id_producto = $("#id_producto").val();
			var fecha_desde = $("#fecha_desde").val();
			var fecha_hasta = $("#fecha_hasta").val();
			var id_marca = $("#id_marca").val();
			var id_grupo = $("#id_grupo").val();
			var vendedor = $("#vendedor").val();

			var rango_fechas = rango_fecha(fecha_desde, fecha_hasta, 365);
			if (rango_fechas == true) {
				alert('La diferencia entre las fechas no puede ser mayor a 1 año.');
				return false;
			}

			$.ajax({
				type: "POST",
				url: "../ajax/reporte_ventas.php",
				data: "action=" + tipo_reporte + "&id_cliente=" + id_cliente + "&id_producto=" + id_producto + "&fecha_desde=" + fecha_desde + "&fecha_hasta=" + fecha_hasta + "&id_marca=" + id_marca + "&vendedor=" + vendedor + "&id_grupo=" + id_grupo,
				beforeSend: function(objeto) {
					$('#loader').html('<img src="../image/ajax-loader.gif">');
				},
				success: function(datos) {
					$(".outer_div").html(datos);
					$("#loader").html('');
				}
			});


		}
	</script>