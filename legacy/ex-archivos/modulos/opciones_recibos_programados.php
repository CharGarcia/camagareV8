<html lang="es">
<meta name="viewport" content="width=device-width, initial-scale=1">

<head>
	<title>Generar recibos</title>
</head>

<?php
session_start();
if (isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa']) && isset($_SESSION['ruc_empresa'])) {
	$id_usuario = $_SESSION['id_usuario'];
	$id_empresa = $_SESSION['id_empresa'];
	$ruc_empresa = $_SESSION['ruc_empresa'];
	include("../paginas/menu_de_empresas.php");

	$con = conenta_login();
?>

	<body>

		<div class="container">
			<div class="panel panel-info">
				<div class="panel-heading">
					<h4><i class='glyphicon glyphicon-edit'></i> Opciones de facturación programada</h4>
				</div>

				<ul class="nav nav-tabs">
					<li class="active"><a data-toggle="tab" href="#generar_facturas">Generar recibos</a></li>
				</ul>

				<div class="tab-content">
					<div id="generar_facturas" class="tab-pane fade in active">
						<div class="panel-body">
							<div class="well well-sm">
								<div class="table-responsive">
									<table class="table table-bordered">
										<tr class="warning">
											<th class="text-center">Sucursal</th>
											<th class="text-center">Mes</th>
											<th class="text-center">Año</th>
											<th class="text-center">Período</th>
											<th class="text-center">Mostrar</th>
										</tr>

										<td class='col-sm-2'>
											<select class="form-control" name="sucursal_facturar" id="sucursal_facturar">
												<option value="0">Seleccione serie</option>
												<?php
												$conexion = conenta_login();
												$sql = "SELECT * FROM sucursales where ruc_empresa ='" . $ruc_empresa . "' order by id_sucursal asc;";
												$res = mysqli_query($conexion, $sql);
												while ($o = mysqli_fetch_assoc($res)) {
												?>
													<option value="<?php echo $o['serie'] ?> " selected><?php echo $o['serie'] ?> </option>
												<?php
												}
												?>
											</select>
										</td>

										<td class='col-sm-2'>
											<select class="form-control" name="mes_facturar" id="mes_facturar">
												<option value="<?php echo date("m") ?>" selected> <?php echo date("m") ?></option>
												<option value="01">01</option>
												<option value="02">02</option>
												<option value="03">03</option>
												<option value="04">04</option>
												<option value="05">05</option>
												<option value="06">06</option>
												<option value="07">07</option>
												<option value="08">08</option>
												<option value="09">09</option>
												<option value="10">10</option>
												<option value="11">11</option>
												<option value="12">12</option>

											</select>
										</td>

										<td class='col-sm-2'>
											<select class="form-control" name="anio_facturar" id="anio_facturar">
												<option value="<?php echo date("Y") + 1 ?>" selected> <?php echo date("Y") + 1 ?></option>
												<option value="<?php echo date("Y") ?>" selected> <?php echo date("Y") ?></option>
												<?php for ($i = $anio2 = date("Y") - 1; $i > $anio1 = date("Y") - 10; $i += -1) {
												?>
													<option value="<?php echo $i ?>"> <?php echo $i ?></option>
												<?php }  ?>
											</select>
										</td>

										<td class='col-sm-2'>
											<select class="form-control" id="periodo_facturar" name="periodo_facturar" required>
												<option value="" Selected>Seleccione período</option>
												<?php
												$sql = "SELECT * FROM periodo_a_facturar";
												$respuesta = mysqli_query($con, $sql);
												while ($datos_periodo = mysqli_fetch_assoc($respuesta)) {
												?>
													<option value="<?php echo $datos_periodo['codigo_periodo'] ?>"><?php echo $datos_periodo['detalle_periodo'] ?></option>
												<?php
												}
												?>
											</select>
										</td>

										<td class='col-sm-1 text-center'>
											<a class="btn btn-info" href="#" onclick="mostrar_recibos_programados('')"><i class='glyphicon glyphicon-search'></i></a>
										</td>

									</table>
								</div>
								<div id="recibos_por_facturar"></div><!-- Carga los datos ajax del detalle de la factura -->
								<div class='outer_div_recibos'></div><!-- Carga los datos ajax -->
							</div> <!-- HASATA AQUI EL body -->
						</div>
					</div>



				</div> <!-- HASATA AQUI EL DIV tab contents -->
			</div> <!-- HASATA AQUI EL DIV DEL BODY -->
		</div>

	<?php
} else {
	header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/sistema') . '/empresa');
}
	?>

	</body>

</html>
<script>
	function mostrar_recibos_programados() {
		var sucursal_facturar = $("#sucursal_facturar").val();
		var mes_facturar = $("#mes_facturar").val();
		var anio_facturar = $("#anio_facturar").val();
		var periodo_facturar = $("#periodo_facturar").val();

		$("#recibos_por_facturar").fadeIn('slow');
		$.ajax({
			type: "POST",
			url: '../ajax/mostrar_recibos_por_facturar.php',
			data: 'action=facturar&sucursal_facturar=' + sucursal_facturar + '&mes_facturar=' + mes_facturar + "&anio_facturar=" + anio_facturar + "&periodo_facturar=" + periodo_facturar,
			beforeSend: function(objeto) {
				$("#recibos_por_facturar").html("Mensaje: Cargando...");
			},
			success: function(datos) {
				$("#recibos_por_facturar").html(datos);
			}
		});
	}
</script>