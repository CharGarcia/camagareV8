<?php
session_start();
if (isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa']) && isset($_SESSION['ruc_empresa'])) {
	$id_usuario = $_SESSION['id_usuario'];
	$id_empresa = $_SESSION['id_empresa'];
	$ruc_empresa = $_SESSION['ruc_empresa'];

?>
	<html lang="es">
	<head>
		<title>Gráfico ventas vs compras</title>
		<?php include("../paginas/menu_de_empresas.php"); ?>
	</head>
	<body>
		<div class="row">
			<div class="col-sm-2">
				<div class="panel panel-default">
					<div class="table-responsive">
						<div class="panel-heading">
							<form class="form-horizontal">
								<input type="hidden" id="mes">
								<input type="hidden" id="suma">
								<div class="form-group">
									<label class="col-sm-2 control-label">Año</label>
									<div class="col-sm-9">
										<select class="form-control" name="anio_periodo" id="anio_periodo">
											<option value="<?php echo date("Y") ?>"> <?php echo date("Y") ?></option>
											<?php for ($i = $anio2 = date("Y") - 1; $i > $anio1 = date("Y") - 5; $i += -1) {
											?>
												<option value="<?php echo $i ?>"> <?php echo $i ?></option>
											<?php }  ?>
										</select>
									</div>
								</div>
								<div class="form-group">
									<label class="col-sm-2 control-label">Tipo</label>
									<div class="col-sm-9">
										<select class="form-control" name="tipo" id="tipo">
											<option value="line"> Lineal</option>
											<option value="column" selected> Columnas</option>
											<option value="bar"> Barras</option>
											<option value="area"> Area</option>
											<option value="spline"> Invertido</option>
										</select>
									</div>
								</div>
								<div class="form-group">
									<label class="col-sm-2 control-label"></label>
									<div class="col-sm-10">
										<button type="button" class="btn btn-info" onclick='mostrar_char();'><span class="glyphicon glyphicon-search"></span> Mostrar </button>
									</div>
								</div>
								<div class="form-group">
									<label class="col-sm-4 control-label"></label>
									<div class="col-sm-8">
										<div id="loader"></div>
									</div>
								</div>
							</form>
						</div>
					</div>
				</div>
			</div>
			<div class="col-sm-10">
				<div id="container" style="min-width: 300px; max-width: 1200px; height: 500px; margin: 1 auto"></div>
			</div>
		</div>
	</body>
	</html>
	<script src="../js/bar_chart/highcharts.js"></script>
	<script src="../js/bar_chart/highcharts-3d.js"></script>
	<script src="../js/bar_chart/modules/exporting.js"></script>
	<script src="../js/bar_chart/modules/export-data.js"></script>
	<script src="../js/bar_chart/modules/accessibility.js"></script>
	<script type="text/javascript" src="../js/style_bootstrap.js"> </script>
	<link rel="stylesheet" href="../css/jquery-ui.css">
	<script src="../js/jquery-ui.js"></script>
	<script src="../js/notify.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
<?php
} else {
	header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/sistema') . '/empresa');
	exit;
}
?>
<script>
	//para cuando se seleecione el anio
	function mostrar_char() {
		var anio = $("#anio_periodo").val();
		var tipo_char = $("#tipo").val();
		$.ajax({
			url: '../ajax/analisis_ventasvscompras.php?action=analisis_ventasvscompras&anio=' + anio,
			beforeSend: function(objeto) {
				$('#loader').html('<img src="../image/ajax-loader.gif">');
			},
			success: function(data) {
				$.each(data, function(i, item) {
					grafico(item.meses, anio, tipo_char, item.sumas_compras, item.sumas_ventas);
					$('#loader').html('');
				});
			}
		})
	}

	function grafico(meses, anio, tipo_char, sumas_compras, sumas_ventas) {
		Highcharts.chart('container', {
			chart: {
				type: tipo_char,
				style: {
					fontSize: '14px'
				}
			},
			title: {
				text: 'Ventas vs Compras ' + anio
			},
			subtitle: {
				text: 'Mensual'
			},
			xAxis: {
				categories: meses,
				title: {
					text: 'Meses'
				}
			},
			yAxis: {
				min: 0,
				title: {
					text: 'Moneda (Dólares)',
					align: 'high'
				},
				labels: {
					overflow: 'justify'
				}
			},
			tooltip: {
				valueSuffix: ' Dólares'
			},
			tooltip: {
				headerFormat: '<span style="font-size:12px">{point.key}</span><table>',
				pointFormat: '<tr><td style="color:{series.color};padding:0">{series.name}: </td>' +
					'<td style="padding:0"><b>{point.y:.1f}</b></td></tr>',
				footerFormat: '</table>',
				shared: true,
				useHTML: true
			},
			plotOptions: {
				bar: {
					dataLabels: {
						enabled: true
					}
				},
				column: {
					dataLabels: {
						enabled: true
					}
				},
				line: {
					dataLabels: {
						enabled: true
					}
				},
				area: {
					dataLabels: {
						enabled: true
					}
				},
				spline: {
					dataLabels: {
						enabled: true
					}
				}
			},

			credits: {
				enabled: false
			},
			series: [{
					name: 'Ventas',
					data: sumas_ventas
				},
				{
					name: 'Compras',
					data: sumas_compras
				}
			]
		});
	}
</script>