<html lang="es">
<meta name="viewport" content="width=device-width, initial-scale=1">

<head>
	<title>Retención compras</title>
</head>
<?php
session_start();
if (isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa']) && isset($_SESSION['ruc_empresa'])) {
	$id_usuario = $_SESSION['id_usuario'];
	$id_empresa = $_SESSION['id_empresa'];
	$ruc_empresa = $_SESSION['ruc_empresa'];

	include("../paginas/menu_de_empresas.php");
	ini_set('date.timezone', 'America/Guayaquil');
	//para borrar los datos de la retencion que este en temporal

	$con = conenta_login();
	if (isset($_SESSION['id_usuario'])) {
		$id_tmp = $_SESSION['id_usuario'];
		$delete_retencion_tmp = mysqli_query($con, "DELETE FROM retencion_tmp WHERE id_usuario = '" . $id_tmp . "';");
		$delete_adicional_tmp = mysqli_query($con, "DELETE FROM adicional_tmp WHERE id_usuario = '" . $id_tmp . "';");
	}
?>

	<body>
		<?php
		include("../modal/nuevo_proveedor_retencion.php");
		?>

		<div class="container">
			<div class="panel panel-info">

				<div class="panel-heading">
					<div class="btn-group pull-right">
						<form class="form-group" id="guardar_retencion_e" name="guardar_retencion_e" method="POST">
							<span id="mensaje_guardar_ret_electronica"></span>
							<button id="guardar_datos_retencion_e" type="submit" class="btn btn-info btn-md"><span class='glyphicon glyphicon-floppy-disk'></span> Guardar</button>
					</div>
					<h4><i class='glyphicon glyphicon-edit'></i> Nueva retención por compras</h4>
				</div>

				<div class="panel-body">
					<div id="resultados_ajax"></div>
					<div id="resultados_guardar_retencion_electronica"></div>
					<div id="observacion_retener_iva"></div>
					<div id="observacion_agente_micro"></div>
					<div class="well well-sm">
						<div class="table-responsive">
							<table class="table table-bordered">
								<tr class="info">
									<td style="padding: 2px;">Fecha retención</td>
									<td style="padding: 2px;">Fecha comprobante</td>
									<td style="padding: 2px;">Serie</td>
									<td style="padding: 2px;">Secuencial</td>
									<td style="padding: 2px;">Proveedor</td>
								</tr>
								<td class='col-xs-2'>
									<input type="text" class="form-control input-sm" name="fecha_retencion_e" id="fecha_retencion_e" value="<?php echo date("d-m-Y"); ?>">
								</td>
								<td class='col-xs-2'>
									<input type="text" class="form-control input-sm" name="fecha_comprobante_e" id="fecha_comprobante_e" value="<?php echo date("d-m-Y"); ?>">
								</td>
								<td class="col-md-2">
									<select class="form-control" name="serie_retencion_e" id="serie_retencion_e" required>
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
								<td class="col-md-2"><input type="text" class="form-control input-sm text-right" id="secuencial_retencion_e" name="secuencial_retencion_e" placeholder="000000001" readonly></td>

								<td class="col-xs-6">
									<div class="input-group">
										<input class="form-control input-sm" id="nombre_prov" name="nombre_prov" placeholder="Proveedor" onkeyup='buscar_proveedores();' autocomplete="off">
										<span class="input-group-btn btn-md"><button class="btn btn-info btn-md" data-toggle="modal" data-target="#nuevoProveedorRetencion" type="button" title="Agregar proveedor"><span class="glyphicon glyphicon-plus"></span></button></span>
									</div>
								</td>
								<input type="hidden" id="id_proveedor_e" name="id_proveedor_e">
								<input type="hidden" id="ruc_proveedor" name="ruc_proveedor">
								<input type="hidden" id="id_concepto_ret" name="id_concepto_ret">
								<input type="hidden" id="total_retencion_e" name="total_retencion_e">
								<input type="hidden" id="mail_proveedor" name="mail_proveedor">
								<input type="hidden" id="id_compra" name="id_compra" value="0">
							</table>
							<table class="table table-bordered">
								<tr class="info">
									<td style="padding: 2px;">Tipo comprobante</td>
									<td style="padding: 2px;">Número comprobante</td>
									<td style="padding: 2px;">Concepto Renta/IVA</td>
									<td style="padding: 2px;">Porcentaje</td>
									<td style="padding: 2px;">Base imponible</td>
									<td style="padding: 2px;">Agregar</td>
								</tr>
								<td class="col-xs-3"><select class="form-control input-sm" name="tipo_comprobante" id="tipo_comprobante">
										<option value="0">Seleccione comprobante</option>
										<option value="01" selected>Factura</option>
										<option value="05">Nota de débito</option>
										<option value="03">Liq. compras</option>

									</select></td>
								<td class="col-xs-2">
									<input type="text" class="form-control input-sm" pattern="[0-9]{3}-[0-9]{3}-[0-9]{9}" maxlength="17" id="numero_comprobante" name="numero_comprobante" placeholder="001-001-000000009" title="formato: 001-001-000000009" required>
								</td>
								<td class='col-xs-3'>
									<input type="text" class="form-control input-sm" id="concepto_retencion" name="concepto_retencion" placeholder="Concepto" title="Busque un concepto de retención" onkeyup='buscar_retenciones_compras();' autocomplete="off">
								</td>
								<td class='col-xs-1'>
									<input type="text" class="form-control input-sm text-right" id="porcentaje_retencion" name="porcentaje_retencion" placeholder="%">
								</td>
								<td class='col-xs-2'>
									<input type="text" class="form-control input-sm text-right" id="base_retencion" name="base_retencion" placeholder="Base">
								</td>
								<td class='col-xs-1'>
									<button type="button" class="btn btn-info btn-md" title="Agregar retenciones" onclick="agregar_concepto_retencion()"><span class="glyphicon glyphicon-plus"></span></button>
								</td>
							</table>
						</div>
					</div>
					</form>
					<div id="resultados"></div><!-- Carga los datos ajax del detalle de la factura -->
					<span id="loader"></span>
				</div>
			</div>
		</div>


	<?php
} else {
	header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/sistema') . '/empresa');
}
	?>
	</body>
	<link rel="stylesheet" href="../css/jquery-ui.css">
	<script src="../js/jquery-ui.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
	<script src="../js/notify.js"></script>

</html>
<script>
	jQuery(function($) {
		$("#fecha_retencion_e").mask("99-99-9999");
		$("#fecha_comprobante_e").mask("99-99-9999");
		$("#numero_comprobante").mask("999-999-9?99999999");
	});

	function buscar_proveedores() {
		$("#nombre_prov").autocomplete({
			source: '../ajax/proveedores_autocompletar.php',
			minLength: 2,
			select: function(event, ui) {
				event.preventDefault();
				$('#id_proveedor_e').val(ui.item.id_proveedor);
				$('#nombre_prov').val(ui.item.razon_social);
				$('#mail_proveedor').val(ui.item.mail_proveedor);
				$('#ruc_proveedor').val(ui.item.ruc_proveedor);

				var mail = document.getElementById('mail_proveedor').value;;

				if (mail == '') {
					alert('Este proveedor no tiene mail, favor agregar.');
					$("#id_proveedor_e").val("");
					$("#nombre_prov").val("");
					$("#ruc_proveedor").val("");
					document.getElementById('nombre_prov').focus();
					return false;
				}

				document.getElementById('numero_comprobante').focus();
				retiene_iva();
				info_agente_micro_especial();
			}
		});

		$("#nombre_prov").on("keydown", function(event) {
			if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
				$("#id_proveedor_e").val("");
				$("#nombre_prov").val("");
				$("#ruc_proveedor").val("");

			}
			if (event.keyCode == $.ui.keyCode.DELETE) {
				$("#id_proveedor_e").val("");
				$("#nombre_prov").val("");
				$("#ruc_proveedor").val("");

			}
		});
	}

	//para ver si debere retener el iva
	function retiene_iva() {
		var id_proveedor = $("#id_proveedor_e").val();
		$.ajax({
			url: '../ajax/observacion_retener_iva.php?&id_proveedor=' + id_proveedor,
			beforeSend: function(objeto) {
				$('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
			},
			success: function(data) {
				$("#observacion_retener_iva").html(data).fadeIn('slow');
				$('#loader').html('');
			}
		})
	};

	function info_agente_micro_especial() {
		var ruc_proveedor = $("#ruc_proveedor").val();
		$.ajax({
			type: "POST",
			url: "../clases/info_agente_micro_especial.php?action=info_agente_micro_especial",
			data: "ruc_proveedor=" + ruc_proveedor,
			beforeSend: function(objeto) {
				$("#loader").html('Cargando...');
			},
			success: function(datos) {
				$("#observacion_agente_micro").html(datos).fadeIn('slow');
				$("#loader").html('');
			}
		});
	}

	function buscar_retenciones_compras() {
		$("#concepto_retencion").autocomplete({
			source: '../ajax/concepto_retencion_autocompletar.php',
			minLength: 2,
			select: function(event, ui) {
				event.preventDefault();
				$('#id_concepto_ret').val(ui.item.id_ret);
				$('#concepto_retencion').val(ui.item.concepto_ret);
				$('#porcentaje_retencion').val(ui.item.porcentaje_ret);
				document.getElementById('base_retencion').focus();
				//para ver si debe retener iva
			}
		});

		$("#concepto_retencion").on("keydown", function(event) {
			if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
				$("#id_concepto_ret").val("");
				$("#concepto_retencion").val("");
				$("#porcentaje_retencion").val("");
			}
			if (event.keyCode == $.ui.keyCode.DELETE) {
				$("#id_concepto_ret").val("");
				$("#concepto_retencion").val("");
				$("#porcentaje_retencion").val("");
			}
		});
	}


	$(document).ready(function() {
		var id_serie = $("#serie_retencion_e").val();
		$.post('../ajax/buscar_ultima_retencion.php', {
			serie_re: id_serie
		}).done(function(respuesta) {
			var retencion_final = respuesta;
			$("#secuencial_retencion_e").val(retencion_final);
		});
		document.getElementById('nombre_prov').focus();
	});

	$(function() {
		$("#fecha_retencion_e").datepicker({
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
		$("#fecha_retencion_e").datepicker("option", "minDate", "-1m:+24d");
		$("#fecha_retencion_e").datepicker("option", "maxDate", "+0m +0d");
	});

	$(function() {
		$("#fecha_comprobante_e").datepicker({
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
		$("#fecha_comprobante_e").datepicker("option", "minDate", "-1m:+24d");
		$("#fecha_comprobante_e").datepicker("option", "maxDate", "+0m +0d");
	});

	function eliminar_concepto_retencion(id) {
		$.ajax({
			type: "GET",
			url: "../ajax/agregar_retenciones.php",
			data: "id=" + id,
			beforeSend: function(objeto) {
				$("#resultados").html("Mensaje: Cargando...");
			},
			success: function(datos) {
				$("#resultados").html(datos);
			}
		});

	};

	function agregar_concepto_retencion() { //viene del boton de agregar en el modal de buscar_concepto_retencion.php
		var id_proveedor = $("#id_proveedor_e").val();
		var id_ret = $("#id_concepto_ret").val();
		var porcentaje_ret = $("#porcentaje_retencion").val();
		var base_imponible_ret = $("#base_retencion").val();
		var fecha_ret = $("#fecha_retencion_e").val();
		var codigo_proveedor = $("#id_proveedor_e").val();
		//Inicia validacion
		if (id_proveedor == "") {
			alert('Seleccione un proveedor');
			document.getElementById('nombre_prov').focus();
			return false;
		}
		if (isNaN(porcentaje_ret)) {
			alert('El dato ingresado en porcentaje, no es un número');
			document.getElementById('porcentaje_retencion').focus();
			return false;
		}
		if (isNaN(base_imponible_ret)) {
			alert('El dato ingresado en base imponible, no es un número');
			document.getElementById('base_retencion').focus();
			return false;
		}
		if ((base_imponible_ret) == 0) {
			alert('Ingrese valor en base imponible');
			document.getElementById('base_retencion').focus();
			return false;
		}
		//Fin validacion
		$.ajax({
			type: "POST",
			url: "../ajax/agregar_retenciones.php",
			data: "id_ret=" + id_ret + "&porcentaje_ret=" + porcentaje_ret + "&base_imponible_ret=" + base_imponible_ret + "&fecha_ret=" + fecha_ret + "&codigo_proveedor=" + codigo_proveedor,
			beforeSend: function(objeto) {
				$("#resultados").html("Mensaje: Cargando...");
			},
			success: function(datos) {
				$("#resultados").html(datos);
				$("#concepto_retencion").val("");
				$("#porcentaje_retencion").val("");
				$("#base_retencion").val("");
				document.getElementById('concepto_retencion').focus();
			}
		});

	};


	//para mostrar la retencion que continua segun la serie seleccionada		
	$(function() {
		$('#serie_retencion_e').change(function() {
			var id_serie = $("#serie_retencion_e").val();
			$.post('../ajax/buscar_ultima_retencion.php', {
				serie_re: id_serie
			}).done(function(respuesta) {
				var retencion_final = respuesta;
				$("#secuencial_retencion_e").val(retencion_final);
			});
			document.getElementById('nombre_prov').focus();
		});

	});

	//para guardar la retencion electronica
	$("#guardar_retencion_e").submit(function(event) {
		$('#guardar_datos_retencion_e').attr("disabled", true);
		//para pasar el total de la retencion de un text a otro text
		var total_retencion = $("#suma_retencion").val();
		$("#total_retencion_e").val(total_retencion);
		//de aqui para abajo para guardar la factura
		var parametros = $(this).serialize();
		$.ajax({
			type: "POST",
			url: '../ajax/guardar_retencion_electronica.php',
			data: parametros,
			beforeSend: function(objeto) {
				$("#mensaje_guardar_ret_electronica").html("Guardando...");
			},
			success: function(datos) {
				$("#resultados_guardar_retencion_electronica").html(datos);
				$("#mensaje_guardar_ret_electronica").html("");
				$('#guardar_datos_retencion_e').attr("disabled", false);
			}
		});
		event.preventDefault();
	});


	//para cuando cambia en el secuencial de factura que se aplica la retencion
	$(function() {
		$('#numero_comprobante').change(function() {
			var numero_comprobante = $("#numero_comprobante").val();
			var serie = numero_comprobante.substr(0, 8);
			var secuencial = numero_comprobante.substr(8, 9);
			while (secuencial.length < 9) {
				var secuencial = '0' + secuencial;
				$("#numero_comprobante").val(serie + secuencial);
			}

			document.getElementById('concepto_retencion').focus();
		});
	});
</script>