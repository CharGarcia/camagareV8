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
		<title>Por cobrar</title>
		<?php
		include("../paginas/menu_de_empresas.php");
		date_default_timezone_set('America/Guayaquil');
		?>
	</head>

	<body>
		<?php
		include("../modal/enviar_documentos_mail.php");
		include("../modal/detalle_cobranza.php");
		include("../modal/detalle_documento.php");
		?>
		<div class="container-fluid">
			<div class="panel panel-info">
				<div class="panel-heading">
					<h4><i class='glyphicon glyphicon-list-alt'></i> Reporte de cuentas por cobrar</h4>
				</div>
				<div class="panel-body">
					<form class="form-horizontal" method="POST" action="../pdf/pdf_cuentas_por_cobrar.php" target="_blank" name="ventas">
						<input type="hidden" name="id_cliente" id="id_cliente">
						<div class="form-group">
							<div class="col-sm-4">
								<div class="input-group">
									<span class="input-group-addon"><b>Cliente</b></span>
									<input type="text" class="form-control input-sm" name="nombre_cliente" id="nombre_cliente" onkeyup='buscar_cliente();' placeholder="Todos" autocomplete="off">
								</div>
							</div>
							<div class="col-sm-3">
								<div class="input-group">
									<span class="input-group-addon"><b>Asesor</b></span>
									<select class="form-control input-sm" name="vendedor" id="vendedor">
										<option value="0" selected>Todos</option>
										<?php
										$con = conenta_login();
										$vendedores = mysqli_query($con, "SELECT * FROM vendedores where ruc_empresa ='" . $ruc_empresa . "'order by nombre asc ");
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
								<div class="input-group">
									<span class="input-group-addon"><b>Hasta</b></span>
									<input type="text" class="form-control input-sm text-center" name="fecha_hasta" id="fecha_hasta" value="<?php echo date("d-m-Y"); ?>">
								</div>
							</div>

							<div class="col-sm-2">
								<button type="button" title="Mostrar resultado" class="btn btn-info btn-sm" onclick="mostrar_informe()"><span class="glyphicon glyphicon-search"></span></button>
								<button type="submit" title='Imprimir pdf' class='btn btn-default btn-sm' title='Pdf'>Pdf</button>
								<button type="button" onclick="document.ventas.action = '../excel/cuentas_porcobrar_excel.php?action=generar_informe_excel'; document.ventas.submit()" class='btn btn-success btn-sm' title="Descargar excel" target="_blank"><img src="../image/excel.ico" width="20" height="16"></button>
							</div>
							<span id="loader"></span>
						</div>

					</form>

					<div id="resultados"></div><!-- Carga los datos ajax href="../excel/reporte_ventas_excel.php"-->
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
	<link rel="stylesheet" href="../css/jquery-ui.css"> <!--para que se vea con fondo blanco el autocomplete -->
	<script src="../js/jquery-ui.js"></script>
	<script src="../js/notify.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>

	</html>
	<script>
		jQuery(function($) {
			$("#fecha_hasta").mask("99-99-9999");
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
			var id_cliente = $("#id_cliente").val();
			var hasta = $("#fecha_hasta").val();
			var vendedor = $("#vendedor").val();

			$.ajax({
				type: "POST",
				url: "../ajax/cuentas_por_cobrar.php",
				data: "action=generar_informe&id_cliente=" + id_cliente + "&hasta=" + hasta + "&vendedor=" + vendedor,
				beforeSend: function(objeto) {
					$('#loader').html('<img src="../image/ajax-loader.gif">Generando...');
				},
				success: function(datos) {
					$(".outer_div").html(datos);
					$("#loader").html('');
				}
			});
		}



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

			//$("#nombre_cliente").autocomplete("widget").addClass("fixedHeight"); //para que aparezca la barra de desplazamiento en el buscar
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

		//tomar la info del mail y documento y lo pasa al modal de enviar mail
		function enviar_cxc_mail_individual(email, id) {
			$("#id_documento").val(id);
			$("#mail_receptor").val(email);
			$("#tipo_documento").val("cxc_individual");
		}

		function enviar_cxc_mail_todos(email, id) {
			$("#id_documento").val(id);
			$("#mail_receptor").val(email);
			$("#tipo_documento").val("cxc_todos");
		}

		//para enviar por mail
		$("#documento_mail").submit(function(event) {
			$('#enviar_mail').attr("disabled", true);
			$('#mensaje_mail').attr("hidden", true); // para mostrar el mensaje de dar clik para enviar y mas abajo lo desaparece
			var parametros = $(this).serialize();
			//var pagina = $("#pagina").val();
			$.ajax({
				type: "GET",
				url: "../documentos_mail/envia_mail.php",
				data: parametros,
				beforeSend: function(objeto) {
					$("#resultados_ajax_mail").html(
						'<div class="progress"><div class="progress-bar progress-bar-primary progress-bar-striped active" role="progressbar" style="width:100%;">Enviando estado de cuenta por cobrar por mail espere por favor...</div></div>');
				},
				success: function(datos) {
					$("#resultados_ajax_mail").html(datos);
					$('#enviar_mail').attr("disabled", false);
					$('#mensaje_mail').attr("hidden", false); // lo vuelve a mostrar el mensaje cuando ya hace todo el proceso
					//load(pagina);
				}
			});
			event.preventDefault();
		});
		//para que cuando se cierre el modal de enviar mail se reseteen los datos y se limpie
		$("#cerrar_mail").click(function() {
			$("#resultados_ajax_mail").empty();
		});


		function detalle_cobranza(id) {
			var codigo_unico = id;

			$("#loaderdet").fadeIn('slow');
			$.ajax({
				url: '../ajax/resumen_cartera.php?action=resumen_cartera&id_documento=' + codigo_unico,
				beforeSend: function(objeto) {
					$('#loaderdet').html('<img src="../image/ajax-loader.gif"> Cargando detalle...');
				},
				success: function(data) {
					$(".outer_divdet").html(data).fadeIn('slow');
					$('#loaderdet').html('');
				}
			})
		}

		function detalle_factura(id) {
			$("#loaderdet").fadeIn('slow');
			$.ajax({
				url: '../ajax/detalle_documento.php?action=facturas_ventas&id=' + id,
				beforeSend: function(objeto) {
					$('#loaderdet').html('<img src="../image/ajax-loader.gif"> Cargando detalle de factura...');
				},
				success: function(data) {
					$(".outer_divdet").html(data).fadeIn('slow');
					$('#loaderdet').html('');
				}
			})
		}

		function enviar_cxc_whatsapp(id, numero, mensaje) {
			document.querySelector("#resultados_ajax_whatsapp").innerHTML = "";
			document.querySelector("#documento_whatsapp").reset();
			$("#id_documento_whatsapp").val(id);
			$("#whatsapp_receptor").val(numero);
			$("#mensaje").val(mensaje);
			$("#tipo_documento_whatsapp").val("cxc");
		};
	</script>