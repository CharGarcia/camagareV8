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
		<title>Mayorización</title>
		<?php
		include("../paginas/menu_de_empresas.php");
		date_default_timezone_set('America/Guayaquil');
		include("../modal/nuevo_diario.php");
		$con = conenta_login();
		?>
		<style type="text/css">
			ul.ui-autocomplete {
				z-index: 1100;
			}
		</style>
	</head>

	<body>
		<div class="container">
			<div class="panel panel-info">
				<div class="panel-heading">
					<h4><i class='glyphicon glyphicon-list-alt'></i> Libro mayor</h4>
				</div>
				<div class="panel-body">
					<form class="form-horizontal" method="POST" target="_blank" action="../excel/mayores.php">
						<input type="hidden" name="id_cuenta_contable" id="id_cuenta_contable">
						<input type="hidden" name="id_pro_cli" id="id_pro_cli">
						<div class="form-group">
							<div class="col-sm-3">
								<div class="input-group">
									<span class="input-group-addon"><b>Reporte</b></span>
									<select class="form-control input-sm" id="nombre_informe" name="nombre_informe" required>
										<option value="4"> Mayor General</option>
										<option value="5"> Clientes</option>
										<option value="6"> Proveedores</option>
										<option value="8"> Empleados</option>
										<option value="7"> Por detalles</option>
									</select>
								</div>
							</div>
							<div class="col-sm-5" id="label_pro_cli">
								<div class="input-group">
									<span class="input-group-addon" id="nombre_pro_cli"></span>
									<input type="text" class="form-control input-sm" name="pro_cli" id="pro_cli" onkeyup='agregar_pro_cli();' placeholder="Todos" autocomplete="off">
								</div>
							</div>
							<div class="col-sm-4" id="label_cuenta">
								<div class="input-group">
									<span class="input-group-addon"><b>Cuenta</b></span>
									<input type="text" class="form-control input-sm" name="cuenta" id="cuenta" onkeyup='agregar_cuenta();' placeholder="Todas" autocomplete="off">
								</div>
							</div>
						</div>
						<div class="form-group">
							<div class="col-sm-2">
								<div class="input-group">
									<span class="input-group-addon"><b>Año</b></span>
									<?php
									$currentYear = date("Y");
									?>
									<select class="form-control input-sm" id="anio_mayor" name="anio_mayor">
										<?php
										$existsCurrentYear = false;
										$tipo = mysqli_query($con, "SELECT DISTINCT YEAR(fecha_asiento) AS anio FROM encabezado_diario WHERE ruc_empresa ='" . $ruc_empresa . "' and estado !='anulado' ORDER BY YEAR(fecha_asiento) DESC");
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
							<div class="col-md-2">
								<div class="input-group">
									<span class="input-group-addon"><b>Mes</b></span>
									<?php
									$currentMonth = date("m");
									$mesSeleccionado = isset($_GET['mes_mayor']) ? $_GET['mes_mayor'] : 'todos';
									?>
									<select class="form-control input-sm" id="mes_mayor" name="mes_mayor" onchange='load(1);'>
										<option value="todos" <?= ($mesSeleccionado == 'todos') ? 'selected' : '' ?>>Todos</option>
										<?php
										$tipo = mysqli_query($con, "SELECT distinct(DATE_FORMAT(fecha_asiento, '%m')) as mes FROM encabezado_diario WHERE ruc_empresa ='" . $ruc_empresa . "' and estado !='anulado' order by month(fecha_asiento) asc");
										while ($row = mysqli_fetch_array($tipo)) {
											$mes = str_pad($row['mes'], 2, '0', STR_PAD_LEFT); // asegura que el mes tenga 2 dígitos
											$selected = ($mes == $mesSeleccionado) ? 'selected' : '';
											echo '<option value="' . $mes . '" ' . $selected . '>' . $mes . '</option>';
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
								<button type="button" title="Mostrar resultado" class="btn btn-info btn-sm" onclick="mostrar_informe()"><span class="glyphicon glyphicon-search"></span></button>
								<button type="submit" title="Descargar excel" class="btn btn-success btn-sm" id="boton_excel"><img src="../image/excel.ico" width="20" height="16"></button>
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
	<script src="../js/siguiente_input.js" type="text/javascript"></script>
	<style>
		.fixedHeight {
			padding: 1px;
			max-height: 200px;
			overflow: auto;
		}
	</style>

	</html>
	<script>
		jQuery(function($) {
			$("#fecha_desde").mask("99-99-9999");
			$("#fecha_hasta").mask("99-99-9999");
			$("#fecha_diario").mask("99-99-9999");
		});

		$(document).ready(function() {
			document.getElementById("label_pro_cli").style.display = "none";
			//document.getElementById("boton_excel").style.display = "none";
			document.getElementById("label_cuenta").style.display = "";
			document.getElementById("cuenta").focus();
		});

		function agregar_cuenta() {
			$("#cuenta").autocomplete({
				source: '../ajax/cuentas_autocompletar.php',
				minLength: 2,
				select: function(event, ui) {
					event.preventDefault();
					$('#id_cuenta_contable').val(ui.item.id_cuenta);
					$('#cuenta').val(ui.item.nombre_cuenta);
				}
			});
			//$("#cuenta").autocomplete("widget").addClass("fixedHeight"); //para que aparezca la barra de desplazamiento en el buscar
			$("#cuenta").on("keydown", function(event) {
				if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_cuenta_contable").val("");
					$("#cuenta").val("");
				}
				if (event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_cuenta_contable").val("");
					$("#cuenta").val("");
				}
			});
		}

		function agregar_pro_cli() {
			var nombre_informe = $("#nombre_informe").val();
			if (nombre_informe == '5') {
				$("#pro_cli").autocomplete({
					source: '../ajax/clientes_autocompletar.php',
					minLength: 2,
					select: function(event, ui) {
						event.preventDefault();
						$('#id_pro_cli').val(ui.item.id);
						$('#pro_cli').val(ui.item.nombre);
					}
				});
			}
			if (nombre_informe == '6') {
				$("#pro_cli").autocomplete({
					source: '../ajax/proveedores_autocompletar.php',
					minLength: 2,
					select: function(event, ui) {
						event.preventDefault();
						$('#id_pro_cli').val(ui.item.id_proveedor);
						$('#pro_cli').val(ui.item.razon_social);
					}
				});
			}
			if (nombre_informe == '7') {
				$("#pro_cli").autocomplete("destroy");
			}
			if (nombre_informe == '8') {
				$("#pro_cli").autocomplete({
					source: '../ajax/empleado_autocompletar.php',
					minLength: 2,
					select: function(event, ui) {
						event.preventDefault();
						$('#id_pro_cli').val(ui.item.id);
						$('#pro_cli').val(ui.item.nombres_apellidos);
					}
				});
			}
		}

		$("#pro_cli").on("keydown", function(event) {
			if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
				$("#id_pro_cli").val("");
				$("#pro_cli").val("");
			}
			if (event.keyCode == $.ui.keyCode.DELETE) {
				$("#id_pro_cli").val("");
				$("#pro_cli").val("");
			}
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
			var cuenta = $("#id_cuenta_contable").val();
			var pro_cli = $("#id_pro_cli").val();
			var det_pro_cli = $("#pro_cli").val();
			var desde = $("#fecha_desde").val();
			var hasta = $("#fecha_hasta").val();

			$.ajax({
				type: "POST",
				url: "../ajax/informes_contables.php",
				data: "action=" + nombre + "&cuenta=" + cuenta + "&fecha_desde=" + desde + "&fecha_hasta=" + hasta + "&pro_cli=" + pro_cli + "&det_pro_cli=" + det_pro_cli,
				beforeSend: function(objeto) {
					$('#loader').html('<img src="../image/ajax-loader.gif">');
				},
				success: function(datos) {
					$(".outer_div").html(datos);
					$("#loader").html('');
				}
			});
		}

		//de aqui para abajo es para modificar el asiento

		function obtener_datos(id) {
			var codigo_unico = $("#mod_codigo_unico" + id).val();
			var codigo_unico_bloque = $("#mod_codigo_unico_bloque" + id).val();
			var concepto_general = $("#mod_concepto_general" + id).val();
			var fecha_asiento = $("#mod_fecha_asiento" + id).val();
			$("#codigo_unico").val(codigo_unico);
			$("#concepto_diario").val(concepto_general);
			$("#fecha_diario").val(fecha_asiento);
			$("#muestra_detalle_diario").fadeIn('fast');
			$.ajax({
				url: '../ajax/agregar_item_diario_tmp.php?action=cargar_detalle_diario&codigo_unico=' + codigo_unico + '&codigo_unico_bloque=' + codigo_unico_bloque,
				beforeSend: function(objeto) {
					$('#muestra_detalle_diario').html('<img src="../image/ajax-loader.gif"> Cargando...');
				},
				success: function(data) {
					$(".outer_divdet").html(data).fadeIn('fast');
					$('#muestra_detalle_diario').html('');
					document.getElementById('cuenta_diario').focus();
				}
			});
		}


		//para buscar las cuentas al hacer un nuevo asiento
		$('#nombre_informe').change(function() {
			//$("#id_marca").val("");
			$("#id_pro_cli").val("");
			$("#pro_cli").val("");
			$("#cuenta").val("");
			$("#id_cuenta_contable").val("");

			var tipo = $("#nombre_informe").val();
			if (tipo == "4") {
				document.getElementById("cuenta").focus();
				document.getElementById("label_pro_cli").style.display = "none";
				document.getElementById("label_cuenta").style.display = "";
				document.getElementById("boton_excel").style.display = "";
			} else {
				document.getElementById("label_pro_cli").style.display = "";
			}

			if (tipo == "5") {
				document.querySelector("#nombre_pro_cli").innerHTML = "<b>Cliente</b>";
				document.getElementById("label_cuenta").style.display = "";
				document.getElementById("boton_excel").style.display = "";
				document.getElementById("pro_cli").focus();
			}
			if (tipo == "6") {
				document.querySelector("#nombre_pro_cli").innerHTML = "<b>Proveedor</b>";
				document.getElementById("label_cuenta").style.display = "";
				document.getElementById("boton_excel").style.display = "none";
				document.getElementById("pro_cli").focus();
			}
			if (tipo == "7") {
				document.querySelector("#nombre_pro_cli").innerHTML = "<b>Detalle, número documento, otros</b>";
				document.getElementById("label_cuenta").style.display = "none";
				document.getElementById("boton_excel").style.display = "none";
				document.getElementById("pro_cli").focus();
			}
			if (tipo == "8") {
				document.querySelector("#nombre_pro_cli").innerHTML = "<b>Empleado</b>";
				document.getElementById("label_cuenta").style.display = "";
				document.getElementById("boton_excel").style.display = "none";
				document.getElementById("pro_cli").focus();
			}

		});


		$('#anio_mayor').change(function() {
			var anio = $("#anio_mayor").val();
			$("#mes_mayor").val('todos');
			$("#fecha_desde").val('01-01-' + anio);
			$("#fecha_hasta").val('31-12-' + anio);
		});

		$('#mes_mayor').change(function() {
			var mes = $("#mes_mayor").val();
			var anio = $("#anio_mayor").val();

			if (mes === "todos") {
				// Si se selecciona "Todos", usar todo el año completo
				$("#fecha_desde").val('01-01-' + anio);
				$("#fecha_hasta").val('31-12-' + anio);
			} else {
				// Calcular el último día del mes seleccionado
				var ultimoDia = new Date(anio, mes, 0).getDate(); // mes ya está en formato 01, 02, etc.
				$("#fecha_desde").val('01-' + mes + '-' + anio);
				$("#fecha_hasta").val(ultimoDia + '-' + mes + '-' + anio);
			}
		});
	</script>