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
		<title>Retenciones ventas</title>
		<?php
		include("../paginas/menu_de_empresas.php");
		include("../modal/detalle_documento.php");
		?>
	</head>

	<body>
		<div class="container-fluid">
			<div class="panel panel-info">
				<div class="panel-heading">
					<div class="btn-group pull-right">
						<form method="post" action="../modulos/nueva_retencion_ventas.php">
							<button type='submit' class="btn btn-info"><span class="glyphicon glyphicon-plus"></span> Nueva retención por ventas</button>
						</form>
					</div>
					<h4><i class='glyphicon glyphicon-search'></i> Retenciones por ventas</h4>
				</div>
				<div class="tab-content">
					<div id="retenciones_ventas" class="tab-pane fade in active">
						<div class="panel-body">
							<form class="form-horizontal" role="form">
								<div class="form-group row">
									<div class="col-md-2">
										<div class="input-group">
											<span class="input-group-addon"><b>Año</b></span>
											<?php
											$currentYear = date("Y");
											?>
											<select class="form-control input-sm" id="anio_ret_venta" name="anio_ret_venta" onchange='load(1);'>
												<option value="" selected>Todos</option>
												<?php
												$existsCurrentYear = false;
												$tipo = mysqli_query($conexion, "SELECT distinct(year(fecha_emision)) as anio FROM encabezado_retencion_venta WHERE ruc_empresa ='" . $ruc_empresa . "' order by year(fecha_emision) desc");
												while ($row = mysqli_fetch_array($tipo)) {
													$selected = ($row['anio'] == $currentYear) ? 'selected' : '';
													if ($row['anio'] == $currentYear) {
														$existsCurrentYear = true;
													}
													echo '<option value="' . $row['anio'] . '" ' . $selected . '>' . strtoupper($row['anio']) . '</option>';
												}
												?>
											</select>
										</div>
									</div>
									<div class="col-md-2">
										<div class="input-group">
											<span class="input-group-addon"><b>Mes</b></span>
											<select class="form-control input-sm" id="mes_ret_venta" name="mes_ret_venta" onchange='load(1);'>
												<option value="" selected>Todos</option>
												<?php
												$tipo = mysqli_query($conexion, "SELECT distinct(DATE_FORMAT(fecha_emision, '%m')) as mes FROM encabezado_retencion_venta WHERE ruc_empresa ='" . $ruc_empresa . "' order by month(fecha_emision) asc");
												while ($row = mysqli_fetch_array($tipo)) {
												?>
													<option value="<?php echo $row['mes'] ?>"><?php echo strtoupper($row['mes']) ?></option>
												<?php
												}
												?>
											</select>
										</div>
									</div>
									<div class="col-md-5">
										<input type="hidden" id="ordenado" value="erv.fecha_emision">
										<input type="hidden" id="por" value="desc">
										<div class="input-group">
											<span class="input-group-addon"><b>Buscar</b></span>
											<input type="text" class="form-control input-sm" id="q" placeholder="Cliente, serie, factura, fecha, ruc" onkeyup='load(1);'>
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
			</div>
		</div>
	<?php
} else {
	header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/sistema') . '/empresa');
	exit;
}
	?>
	<script type="text/javascript" src="../js/style_bootstrap.js"> </script>
	<link rel="stylesheet" href="../css/jquery-ui.css">
	<script src="../js/jquery-ui.js"></script>
	<script src="../js/notify.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
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

		function load(page) {
			var por = $("#por").val();
			var ordenado = $("#ordenado").val();
			var q = $("#q").val();
			var anio_ret_venta = $("#anio_ret_venta").val();
			var mes_ret_venta = $("#mes_ret_venta").val();
			$("#loader").fadeIn('slow');
			$.ajax({
				url: '../ajax/buscar_retenciones_ventas.php?action=buscar&page=' + page + '&q=' + q + '&anio_ret_venta=' + anio_ret_venta + '&mes_ret_venta=' + mes_ret_venta + '&ordenado=' + ordenado + '&por=' + por,
				beforeSend: function(objeto) {
					$('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
				},
				success: function(data) {
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');
				}
			});

			/* $("#resultados_cargar_electronicos").fadeIn('slow');
			$.ajax({
				url: '../ajax/cargar_electronicos.php?action=cargar_electronicos&page=' + page,
				beforeSend: function(objeto) {
					$('#resultados_cargar_electronicos').html('<img src="../image/ajax-loader.gif"> Cargando...');
				},
				success: function(data) {
					$(".outer_div_cargar_electronicos").html(data).fadeIn('slow');
					$('#resultados_cargar_electronicos').html('');
				}
			}); */
		}

		function eliminar_retencion_ventas(id_retencion) {
			var q = $("#q").val();
			var serie = $("#serie_retencion" + id_retencion).val();
			var secuencial = $("#secuencial_retencion" + id_retencion).val();
			if (confirm("Realmente desea eliminar la retención " + serie + "-" + secuencial + " ?")) {
				$.ajax({
					type: "POST",
					url: "../ajax/buscar_retenciones_ventas.php",
					data: "id_retencion=" + id_retencion,
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
		};

		function detalle_retencion_venta(id_ret) {
			$("#outer_divdet").fadeIn('slow');
			$.ajax({
				url: '../ajax/detalle_documento.php?action=detalle_retencion_ventas&id_ret=' + id_ret,
				beforeSend: function(objeto) {
					$('#outer_divdet').html('<img src="../image/ajax-loader.gif"> Cargando detalle de retención...');
				},
				success: function(data) {
					$(".outer_divdet").html(data).fadeIn('slow');
					$('#outer_divdet').html('');
				}
			})
		}
	</script>