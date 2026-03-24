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
		<title>Reporte ventas ejecutivo</title>
		<?php include("../paginas/menu_de_empresas.php"); ?>
	</head>

	<body>

		<div class="container-fluid">
			<div class="panel panel-info">
				<div class="panel-heading">
					<h4><i class='glyphicon glyphicon-list-alt'></i> Reporte de ventas ejecutivo</h4>
				</div>

				<div class="panel-body">
					<form class="form-horizontal" method="POST" target="_blank" action="../excel/reporte_ventas_ejecutivo_excel.php">
						<input type="hidden" name="id_producto" id="id_producto">
						<div class="form-group row">
							<div class="col-md-2">
								<div class="input-group">
									<span class="input-group-addon"><b>Año</b></span>
									<select class="form-control input-sm" name="anio" id="anio">
										<option value="<?php echo date("Y") ?>"> <?php echo date("Y") ?></option>
										<?php for ($i = $anio2 = date("Y") - 1; $i > $anio1 = date("Y") - 5; $i += -1) {
										?>
											<option value="<?php echo $i ?>"> <?php echo $i ?></option>
										<?php }  ?>
									</select>
								</div>
							</div>
							<div class="col-md-2">
								<div class="input-group">
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
							<div class="col-md-4">
								<div class="input-group">
									<span class="input-group-addon"><b>Producto</b></span>
									<input type="text" class="form-control input-sm text-left" name="nombre_producto" id="nombre_producto" value="Todos" onkeyup='buscar_productos();'>
								</div>
							</div>
						</div>
						<div class="form-group row">
							<div class="col-md-2">
								<div class="input-group">
									<span class="input-group-addon"><b>Por</b></span>
									<select class="form-control input-sm" id="tipo_reporte" name="tipo_reporte" required>
										<option value="1" selected> Unidades</option>
										<option value="2"> Valores</option>
									</select>
								</div>
							</div>
							<div class="col-md-6">
								<div class="input-group">
									<span class="input-group-addon"><b>Cliente</b></span>
									<input type="hidden" name="id_cliente" id="id_cliente" class="form-control input-sm">
									<input type="text" name="cliente" id="cliente" class="form-control input-sm" onkeyup='buscar_clientes();'>
								</div>
							</div>
							<div class="col-md-3">
								<button type="button" title="Mostrar resultado" class="btn btn-info btn-sm" onclick="mostrar_reporte()"><span class="glyphicon glyphicon-search"></span> Mostrar</button>
								<button type="submit" title="Descargar excel" class="btn btn-success btn-sm"><img src="../image/excel.ico" width="16" height="16"> Descargar a excel</button>
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
	<!-- <link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script> -->
	<link rel="stylesheet" href="../css/jquery-ui.css">
	<script src="../js/jquery-ui.js"></script>
	<script src="../js/notify.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>

	</html>
	<script>
		//generar informe
		function mostrar_reporte() {
			var tipo_reporte = $("#tipo_reporte").val();
			var anio = $("#anio").val();
			var id_marca = $("#id_marca").val();
			var id_producto = $("#id_producto").val();
			var id_cliente = $("#id_cliente").val();
			var cliente = $("#cliente").val();

			$.ajax({
				type: "POST",
				url: "../ajax/reporte_ventas_ejecutivo.php?action=reporte_ventas_ejecutivo",
				data: "tipo_reporte=" + tipo_reporte + "&anio=" + anio + "&id_marca=" + id_marca + "&id_producto=" + id_producto + "&id_cliente=" + id_cliente + "&cliente=" + cliente,
				beforeSend: function(objeto) {
					$('#loader').html('<img src="../image/ajax-loader.gif">');
				},
				success: function(datos) {
					$(".outer_div").html(datos);
					$("#loader").html('');
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

		//para buscar los clientes
		function buscar_clientes() {
			$("#cliente").autocomplete({
				source: '../ajax/clientes_autocompletar.php',
				minLength: 2,
				select: function(event, ui) {
					event.preventDefault();
					$('#id_cliente').val(ui.item.id);
					$('#cliente').val(ui.item.nombre);
				}
			});

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
	</script>