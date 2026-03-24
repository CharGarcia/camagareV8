<?php
session_start();
if (isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa']) && isset($_SESSION['ruc_empresa'])) {
	$id_usuario = $_SESSION['id_usuario'];
	$id_empresa = $_SESSION['id_empresa'];
	$ruc_empresa = $_SESSION['ruc_empresa'];
?>
	<html lang="es">
	<head>
		<title>Gráficos compras</title>
		<?php include("../paginas/menu_de_empresas.php"); ?>
		<style type="text/css">
			.highcharts-axis-labels {
				font-weight: bold;
				font-size: 14px;
			}
			.highcharts-axis-title {
				font-size: 14px;
			}
		</style>
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
											<option value="column"> Columnas</option>
											<option value="bar" selected> Barras</option>
											<option value="area"> Area</option>
											<option value="spline"> Invertido</option>
											<option value="pie"> Pastel</option>
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
			url: '../ajax/analisis_compras.php?action=analisis_compras&anio=' + anio,
			beforeSend: function(objeto) {
				$('#loader').html('<img src="../image/ajax-loader.gif">');
			},
			success: function(data) {
				$.each(data, function(i, item) {
					grafico(item.meses, anio, tipo_char, item.sumas);
					$('#loader').html('');
				});
			}
		})
	}

	function grafico(meses, anio, tipo_char, sumas) {
		var total = 0;

		var infodata = [];
		for (let p = 0; p < meses.length; p++) {
			let data = {
				name: meses[p],
				y: sumas[p]
			};
			infodata.push(data);
		};

		for (var i = 0; i < sumas.length; i++) {
			numero = sumas[i];
			total += numero;
		};

		//etiqueta = 'Compras ';
		etiqueta = anio + ' Total: ' + Number.parseFloat(total).toFixed(2);

		const chart = new Highcharts.Chart('container', {
			chart: {
				renderTo: 'container',
				type: tipo_char,
				options3d: {
					enabled: true,
					alpha: 0,
					beta: 0,
					depth: 35,
					viewDistance: 25
				},
				style: {
					fontSize: '14px'
				}
			},
			title: {
				text: 'Detalle de compras'
			},
			subtitle: {
				text: 'Mensual',
				style: {
					fontSize: '14px'
				}
			},
			xAxis: {
				categories: meses,
				title: {
					text: 'Meses',
					style: {
						fontSize: '14px'
					}
				}
			},
			yAxis: {
				min: 0,
				title: {
					text: 'Moneda (Dólares)',
					align: 'high',
					style: {
						fontSize: '14px'
					}
				},
				labels: {
					overflow: 'justify'
				}
			},
			tooltip: {
				valueSuffix: ' Dólares',
				style: {
					fontSize: '11px'
				}
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
				},
				pie: {
					allowPointSelect: true,
					cursor: 'pointer',
					dataLabels: {
						enabled: true,
						format: '<b>{point.name}</b>: {point.percentage:.1f} %'
					}
				}
			},
			legend: {
				layout: 'vertical',
				align: 'right',
				verticalAlign: 'top',
				x: -80,
				y: 10,
				floating: true,
				borderWidth: 2,
				backgroundColor: '#FFFFFF',
				shadow: true
			},
			credits: {
				enabled: false
			},
			series: [{
				name: [etiqueta],
				colorByPoint: true,
				data: infodata
			}]
		});
	}
</script>