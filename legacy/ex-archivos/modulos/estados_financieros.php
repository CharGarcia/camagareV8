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
		<title>Estados financieros</title>
		<?php
		include("../paginas/menu_de_empresas.php");
		include("../modal/plan_de_cuentas.php");
		date_default_timezone_set('America/Guayaquil');
		?>
	</head>

	<body>
		<div class="container-fluid">
			<div class="panel panel-info">
				<div class="panel-heading">
					<h4><i class='glyphicon glyphicon-list-alt'></i> Estados financieros</h4>
				</div>
				<div class="panel-body">
					<form class="form-horizontal" method="POST" action="../pdf/pdf_estados_financieros.php" name="balances" target="_blank">
						<input type="hidden" name="id_cuenta" id="id_cuenta">
						<div class="form-group">
							<div class="col-sm-4">
								<div class="input-group">
									<span class="input-group-addon"><b>Tipo</b></span>
									<select class="form-control input-sm" id="nombre_informe" name="nombre_informe" required>
										<option value="1" selected> Estado de Situación Financiera General</option>
										<option value="1P"> Estado de Situación Financiera por períodos</option>
										<option value="2"> Estado de Resultados General</option>
										<option value="2P"> Estado de Resultados por períodos</option>
										<option value="2PP"> Estado de Resultados presupuestado</option>
										<option value="3"> Balance de Comprobación</option>
										<option value="sri"> Imp Renta SRI form 101</option>
										<option value="ESF"> Supercias ESF</option>
										<option value="ERI"> Supercias ERI</option>
									</select>
								</div>
							</div>
							<div class="col-sm-4">
								<div class="input-group">
									<span class="input-group-addon"><b>Proyecto / Centro de costos</b></span>
									<select class="form-control input-sm" id="id_proyecto" name="id_proyecto">
										<option value="0" selected>Ninguno</option>
										<?php
										$sql_proyecto = mysqli_query($con, "SELECT * FROM proyectos WHERE ruc_empresa ='" . $ruc_empresa . "' and status ='1' order by nombre asc");
										foreach ($sql_proyecto as $proyecto) {
										?>
											<option value="<?php echo $proyecto['id'] ?>"><?php echo strtoupper($proyecto['nombre']) ?></option>
										<?php
										}
										?>
									</select>
								</div>
							</div>
							<div class="col-sm-2">
								<div class="input-group">
									<span class="input-group-addon"><b>Nivel</b></span>
									<select class="form-control input-sm" id="nivel" name="nivel" required>
										<option value="0" selected> Todos</option>
										<option value="5"> 5</option>
										<option value="4"> 4</option>
										<option value="3"> 3</option>
										<option value="2"> 2</option>
										<option value="1"> 1</option>
									</select>
								</div>
							</div>
						</div>
						<div class="form-group">
							<div class="col-sm-2">
								<div class="input-group">
									<span class="input-group-addon"><b>Año</b></span>
									<select class="form-control input-sm" name="anio" id="anio">

										<?php
										$existsCurrentYear = false;
										$tipo = mysqli_query($con, "SELECT DISTINCT YEAR(fecha_asiento) AS anio FROM encabezado_diario WHERE ruc_empresa ='" . $ruc_empresa . "' and estado !='Anulado' ORDER BY YEAR(fecha_asiento) DESC");
										// Recorre los resultados de la consulta para verificar si el año actual ya está presente
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
							<div class="col-sm-2">
								<div class="input-group">
									<span class="input-group-addon"><b>Desde</b></span>
									<input type="text" class="form-control input-sm text-center" name="fecha_desde" id="fecha_desde" value="<?php echo date("01-01-Y"); ?>">
								</div>
							</div>
							<div class="col-sm-2">
								<div class="input-group">
									<span class="input-group-addon"><b>Hasta</b></span>
									<input type="text" class="form-control input-sm text-center" name="fecha_hasta" id="fecha_hasta" value="<?php echo date("d-m-Y"); ?>">
								</div>
							</div>
							<div class="col-sm-2">
								<button type="button" title="Mostrar resultado" class="btn btn-info btn-sm" onclick="mostrar_informe()"><span class="glyphicon glyphicon-search"></span> Mostrar</button>
								<button type="submit" title="Imprimir pdf" class="btn btn-default btn-sm" title="Pdf">Pdf</button>
								<button type="button" onclick="document.balances.action = '../excel/estados_financieros.php'; document.balances.submit()" class="btn btn-success btn-sm" title="Descargar excel" target="_blank"><img src="../image/excel.ico" width="20" height="16"></button>
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
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>

	</html>
	<script>
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

		//generar informe
		function mostrar_informe() {
			var nombre = $("#nombre_informe").val();
			var cuenta = $("#id_cuenta").val();
			var fecha_desde = $("#fecha_desde").val();
			var fecha_hasta = $("#fecha_hasta").val();
			var nivel = $("#nivel").val();
			var id_proyecto = $("#id_proyecto").val();

			$.ajax({
				type: "POST",
				url: "../ajax/informes_contables.php",
				data: "action=" + nombre + "&cuenta=" + cuenta + "&fecha_desde=" + fecha_desde + "&fecha_hasta=" + fecha_hasta + "&nivel=" + nivel + "&id_proyecto=" + id_proyecto,
				beforeSend: function(objeto) {
					$('#loader').html('<img src="../image/ajax-loader.gif">');
				},
				success: function(datos) {
					$(".outer_div").html(datos);
					$("#loader").html('');
				}
			});
		}


		function generar_txt_esf() {
			$.ajax({
				type: "POST",
				url: '../ajax/informes_contables.php',
				data: 'action=generar_txt_esf',
				beforeSend: function(objeto) {
					$('#loader').html('<img src="../image/ajax-loader.gif">');
				},
				success: function(datos) {
					$(".outer_div").html(datos);
					$("#loader").html('');
				}
			});

		}


		$('#anio').change(function() {
			var anio = $("#anio").val();
			$("#fecha_desde").val('01-01-' + anio);
			$("#fecha_hasta").val('31-12-' + anio);
		});



		function obtener_datos_editar_cuenta(id) {
			document.querySelector("#editar_cuenta").reset();
			document.querySelector("#mod_id_cuenta").value = id;

			let request = (window.XMLHttpRequest) ?
				new XMLHttpRequest() :
				new ActiveXObject('Microsoft.XMLHTTP');
			let ajaxUrl = '../ajax/plan_de_cuentas.php?action=datos_editar_cuenta&id_cuenta=' + id;
			request.open("GET", ajaxUrl, true);
			request.send();
			request.onreadystatechange = function() {
				if (request.readyState == 4 && request.status == 200) {
					let objData = JSON.parse(request.responseText);
					if (objData.status) {
						let datos = objData.data;
						document.querySelector("#mod_nombre_cuenta").value = datos.nombre_cuenta;
						document.querySelector("#mod_cuenta_sri").innerHTML = datos.nombre_cuenta_sri;
						document.querySelector("#mod_cuenta_supercias").innerHTML = datos.nombre_cuenta_supercias;
						document.querySelector("#mod_codigo_sri").value = datos.codigo_sri;
						document.querySelector("#mod_codigo_supercias").value = datos.codigo_supercias;
						document.querySelector("#mod_nivel_cuenta").value = datos.nivel_cuenta;
						document.querySelector("#mod_codigo_cuenta").value = datos.codigo_cuenta;
						document.querySelector("#listStatus").value = datos.status;

					} else {
						$.notify(objData.msg, "error");
					}
				}
				return false;
			}
		}
	</script>