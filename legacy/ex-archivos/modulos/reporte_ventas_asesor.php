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
		<title>Reporte ventas | Asesor</title>
		<?php include("../paginas/menu_de_empresas.php"); ?>
	</head>

	<body>
		<div class="container-fluid">
			<div class="panel panel-info">
				<div class="panel-heading">
					<h4><i class='glyphicon glyphicon-list-alt'></i> Reporte de ventas por asesor</h4>
				</div>
				<div class="panel-body">
					<form class="form-horizontal" method="POST" target="_blank" action="../excel/reporte_ventas_asesor_excel.php">
						<input type="hidden" name="id_cliente" id="id_cliente">
						<input type="hidden" name="id_producto" id="id_producto">
						<div class="form-group">
							<div class="col-sm-10">
								<div class="input-group">
									<span class="input-group-addon"><b>Asesor</b></span>
									<select class="form-control input-sm" name="id_vendedor" id="id_vendedor">
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
									<span class="input-group-addon"><b>Desde</b></span>
									<input type="text" class="form-control input-sm text-center" name="desde" id="desde" value="<?php echo date("01-m-Y"); ?>">

									<span class="input-group-addon"><b>Hasta</b></span>
									<input type="text" class="form-control input-sm text-center" name="hasta" id="hasta" value="<?php echo date("d-m-Y"); ?>">

									<span class="input-group-addon"><b>Cliente</b></span>
									<input type="text" class="form-control input-sm text-left" name="nombre_cliente" id="nombre_cliente" value="Todos" onkeyup='buscar_cliente();'>

									<span class="input-group-addon"><b>Producto</b></span>
									<input type="text" class="form-control input-sm text-left" name="nombre_producto" id="nombre_producto" value="Todos" onkeyup='buscar_productos();'>

									<span class="input-group-addon"><b>Marca</b></span>
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
		});

		$(function() {
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
			var id_cliente = $("#id_cliente").val();
			var id_producto = $("#id_producto").val();
			var desde = $("#desde").val();
			var hasta = $("#hasta").val();
			var id_marca = $("#id_marca").val();
			var id_vendedor = $("#id_vendedor").val();
			$.ajax({
				type: "POST",
				url: "../ajax/reporte_ventas_asesor.php",
				data: "action=reporte_ventas_asesor&id_cliente=" + id_cliente + "&id_producto=" + id_producto + "&desde=" + desde + "&hasta=" + hasta + "&id_marca=" + id_marca + "&id_vendedor=" + id_vendedor,
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