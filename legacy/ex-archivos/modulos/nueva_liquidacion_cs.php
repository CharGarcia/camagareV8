<html lang="es-ec">
<meta name="viewport" content="width=device-width, initial-scale=1">

<head>
	<title>Liquidación CS</title>
</head>
<?php
session_start();
if (isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa']) && isset($_SESSION['ruc_empresa'])) {
	$id_usuario = $_SESSION['id_usuario'];
	$id_empresa = $_SESSION['id_empresa'];
	$ruc_empresa = $_SESSION['ruc_empresa'];

	include("../paginas/menu_de_empresas.php");
	ini_set('date.timezone', 'America/Guayaquil');
	//para borrar los datos de la factura que este en temporal
	$con = conenta_login();
	if (isset($_SESSION['id_usuario'])) {
		$delete_factura_tmp = mysqli_query($con, "DELETE FROM factura_tmp WHERE id_usuario = '" . $id_usuario . "' and ruc_empresa = '" . $ruc_empresa . "'");
		$delete_adicional_tmp = mysqli_query($con, "DELETE FROM adicional_tmp WHERE id_usuario = '" . $id_usuario . "'");
	}

?>

	<body>
		<?php
		include_once("../modal/nuevo_proveedor_retencion.php");
		?>
		<div class="container" id="content">
			<div class="panel panel-info">
				<div class="panel-heading">
					<div class="btn-group pull-right">
						<form class="form-group" id="guardar_liquidacion" name="guardar_liquidacion" method="POST">
							<span id="mensaje_guardar_lc"></span>
							<button id="guardar_datos_liquidacion" title="Guarda liquidación" type="submit" class="btn btn-info btn-md"><span class='glyphicon glyphicon-floppy-disk'></span> Guardar</button>
					</div>
					<h4><i class='glyphicon glyphicon-edit'></i> Nueva Liquidación de compras de bienes o prestación de servicios</h4>
				</div>
				<div class="panel-body">
					<div id="resultados_guardar_liquidacion"></div>
					<div class="well well-sm" style="margin-bottom: 5px; margin-top: -10px; height: 14%">
						<div class="table-responsive">
							<table class="table table-bordered">
								<tr class="default">
									<th class="text-center col-sm-1" style="padding: 1px;">Fecha</th>
									<th class="text-center col-sm-2" style="padding: 1px;">Serie</th>
									<th class="text-center col-sm-1" style="padding: 1px;">Secuencial</th>
									<th class="text-left col-sm-4" style="padding: 1px;"> Proveedor</th>
									<th class="text-left col-sm-4" style="padding: 1px;"> Pago</th>
								</tr>
								<tr class="default">
									<td style="padding: 2px;">
										<input type="text" class="text-center form-control input-sm" name="fecha_liquidacion" id="fecha_liquidacion" value="<?php echo date("d-m-Y"); ?>">
									</td>
									<td style="padding: 2px;">
										<select style="height:30px;" class="form-control" name="serie_liquidacion" id="serie_liquidacion">
											<option value="0">Seleccione</option>
											<?php
											$sql = "SELECT * FROM sucursales WHERE ruc_empresa ='" . $ruc_empresa . "' order by id_sucursal asc;";
											$res = mysqli_query($con, $sql);
											while ($serie = mysqli_fetch_assoc($res)) {
											?>
												<option value="<?php echo $serie['serie'] ?>" selected><?php echo $serie['serie'] ?></option>
											<?php
											}
											?>
										</select>
									</td>
									<td style="padding: 2px;">
										<input type="text" class="form-control input-sm text-right" id="secuencial_liquidacion" name="secuencial_liquidacion" readonly>
									</td>
									<td style="padding: 2px;">
										<div class="input-group">
											<input style="z-index:inherit;" type="text" class="form-control input-sm" id="nombre_proveedor_lc" name="nombre_proveedor_lc" placeholder="Agregue un proveedor" title="Buscar un proveedor." onkeyup='buscar_proveedor();' autocomplete="off"><span class="input-group-btn btn-md"><button class="btn btn-info btn-md" type="button" title="Nuevo proveedor" data-toggle="modal" data-target="#nuevoProveedorRetencion"><span class="glyphicon glyphicon-plus"></span></button></span>
										</div>
									</td>
									<td style="padding: 2px;">
										<select style="height:30px;" class="form-control" name="forma_pago_lc" id="forma_pago_lc">
											<option value="0">Seleccione forma de pago</option>
											<option value="20" selected>OTROS CON UTILIZACION DEL SISTEMA FINANCIERO</option>
											<?php
											$sql = "SELECT * FROM formas_de_pago WHERE aplica_a ='VENTAS' order by nombre_pago asc";
											$res = mysqli_query($con, $sql);
											while ($o = mysqli_fetch_assoc($res)) {
											?>
												<option value="<?php echo $o['codigo_pago'] ?>"><?php echo $o['nombre_pago'] ?></option>
											<?php
											}
											?>
										</select>
									</td>
								</tr>
							</table>
						</div>
						<input type="hidden" id="mail_proveedor_lc" name="mail_proveedor_lc">
						<input type="hidden" id="ruc_proveedor_lc" name="ruc_proveedor_lc">
						<input type="hidden" id="id_proveedor_lc" name="id_proveedor_lc">
						<input type="hidden" id="total_lc" name="total_lc">
					</div>
					<!-- para agregar los productos a la factura -->

					<div class="panel panel-info">
						<div class="table-responsive">
							<table class="table table-bordered">
								<tr class="success">
									<th style="padding: 2px;">Código</th>
									<th style="padding: 2px;">Producto/servicio</th>
									<th class="text-center" style="padding: 2px;">Cantidad</th>
									<th class="text-center" style="padding: 2px;">Val/Uni</th>
									<th class="text-center" style="padding: 2px;">Descuento</th>
									<th class="text-center" style="padding: 2px;">Tipo IVA</th>
									<th class="text-center" style="padding: 2px;">Agregar</th>
								</tr>

								<td class="col-sm-2">
									<input type="text" class="form-control input-sm" id="codigo_item">
								</td>
								<td class="col-sm-4">
									<input type="text" class="form-control input-sm" id="detalle_item">
								</td>
								<td class="col-sm-1">
									<input type="number" class="form-control input-sm text-right" id="cantidad_item">
								</td>
								<td class="col-sm-1">
									<input type="number" class="form-control input-sm text-right" id="valuni_item">
								</td>
								<td class="col-sm-1">
									<input type="number" class="form-control input-sm text-right" id="descuento_item">
								</td>
								<td class='col-md-2'>
									<select class="form-control input-sm" name="tipo_iva" id="tipo_iva">
										<?php
										$sql = "SELECT * FROM tarifa_iva WHERE status = '1' order by tarifa asc ";
										$res = mysqli_query($con, $sql);
										while ($o = mysqli_fetch_assoc($res)) {
										?>
											<option value="<?php echo $o['codigo']; ?>" selected><?php echo $o['tarifa'] ?></option>
										<?php
										}
										?>
									</select>
								</td>
								<td class="text-center col-sm-1">
									<button type="button" class="btn btn-info btn-md" title="Agregar productos o servicios" onclick="agregar_item()"><span class="glyphicon glyphicon-plus"></span></button>
								</td>

							</table>
						</div>
					</div>
					</form>
					<div id="resultados"></div><!-- Carga los datos ajax del detalle de la factura -->
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
		$("#fecha_liquidacion").mask("99-99-9999");
	});


	$(function() {
		$("#fecha_liquidacion").datepicker({
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

	//para buscar los proveedores
	function buscar_proveedor() {
		$("#nombre_proveedor_lc").autocomplete({
			source: '../ajax/proveedores_autocompletar.php',
			minLength: 2,
			select: function(event, ui) {
				event.preventDefault();
				$('#id_proveedor_lc').val(ui.item.id_proveedor);
				$('#nombre_proveedor_lc').val(ui.item.razon_social);
				$('#mail_proveedor_lc').val(ui.item.mail_proveedor);
				$('#ruc_proveedor_lc').val(ui.item.ruc_proveedor);

				var mail = document.getElementById('mail_proveedor_lc').value;
				var ruc = document.getElementById('ruc_proveedor_lc').value;

				if (ruc.length == 13) {
					alert('No es permitido proveedores con RUC.');
					$("#id_proveedor_lc").val("");
					$("#nombre_proveedor_lc").val("");
					$("#mail_proveedor_lc").val("");
					$("#ruc_proveedor_lc").val("");
					document.getElementById('nombre_proveedor_lc').focus();
					return false;
				}

				if (mail == '') {
					alert('Este proveedor no tiene mail, favor agregar.');
					$("#id_proveedor_lc").val("");
					$("#nombre_proveedor_lc").val("");
					$("#mail_proveedor_lc").val("");
					$("#ruc_proveedor_lc").val("");
					document.getElementById('nombre_proveedor_lc').focus();
					return false;
				}
				document.getElementById('codigo_item').focus();
			}
		});

		$("#nombre_proveedor_lc").on("keydown", function(event) {
			if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
				$("#id_proveedor_lc").val("");
				$("#nombre_proveedor_lc").val("");
				$("#mail_proveedor_lc").val("");
				$("#ruc_proveedor_lc").val("");
			}
			if (event.keyCode == $.ui.keyCode.DELETE) {
				$("#id_proveedor_lc").val("");
				$("#nombre_proveedor_lc").val("");
				$("#mail_proveedor_lc").val("");
				$("#ruc_proveedor_lc").val("");
			}
		});
	}


	//para cargar automaticamente el numero de liq que sigue al momento de cargar la nueva liq
	$(document).ready(function() {
		var id_serie = $("#serie_liquidacion").val();
		$.post('../ajax/buscar_ultima_liquidacion.php', {
			serie_liq: id_serie
		}).done(function(respuesta) {
			var liquidacion_final = respuesta;
			$("#secuencial_liquidacion").val(liquidacion_final);
		});
		document.getElementById('nombre_proveedor').focus();
	});


	//para mostrar la liquidacion que continua segun la serie seleccionada
	$(function() {
		$('#serie_liquidacion').change(function() {
			var id_serie = $("#serie_liquidacion").val();
			$.post('../ajax/buscar_ultima_liquidacion.php', {
				serie_liq: id_serie
			}).done(function(respuesta) {
				var liquidacion_final = respuesta;
				$("#secuencial_liquidacion").val(liquidacion_final);
			});
		});
		document.getElementById('nombre_proveedor_lc').focus();
	});

	//eliminar iten de la lc
	function eliminar_fila(id) {
		var id_proveedor_lc = $("#id_proveedor_lc").val();
		var serie_liquidacion = $("#serie_liquidacion").val();
		var secuencial_liquidacion = $("#secuencial_liquidacion").val();
		$.ajax({
			type: "POST",
			url: "../ajax/agregar_item_lc.php",
			data: "id=" + id + "&id_proveedor_lc=" + id_proveedor_lc + "&serie_liquidacion=" + serie_liquidacion + "&secuencial_liquidacion=" + secuencial_liquidacion,
			beforeSend: function(objeto) {
				$("#resultados").html("Mensaje: Cargando...");
			},
			success: function(datos) {
				$("#resultados").html(datos);
			}
		});
	};

	//para guardar la lc electronica
	$(function() {
		$("#guardar_liquidacion").submit(function(event) {
			$('#guardar_datos_liquidacion').attr("disabled", true);
			//para pasar el total de la factura de un text a otro text
			var total_lc = $("#suma_lc").val();
			$("#total_lc").val(total_lc);
			//de aqui para abajo para guardar la factura
			var parametros = $(this).serialize();
			$.ajax({
				type: "POST",
				url: '../ajax/guardar_liquidacion_electronica.php',
				data: parametros,
				beforeSend: function(objeto) {
					$("#mensaje_guardar_lc").html("Guardando...");
					$("#resultados_guardar_liquidacion").html("Mensaje: Guardando...");
				},
				success: function(datos) {
					$("#resultados_guardar_liquidacion").html(datos);
					$("#mensaje_guardar_lc").html("");
					$('#guardar_datos_liquidacion').attr("disabled", false);
				}
			});
			event.preventDefault();
		});
	});

	//agrega un item al cuerpo de la lc

	function agregar_item() {
		var id_proveedor_lc = $("#id_proveedor_lc").val();
		var serie_liquidacion = $("#serie_liquidacion").val();
		var secuencial_liquidacion = $("#secuencial_liquidacion").val();
		var codigo_item = document.getElementById('codigo_item').value;
		var detalle_item = document.getElementById('detalle_item').value;
		var cantidad_item = document.getElementById('cantidad_item').value;
		var valuni_item = document.getElementById('valuni_item').value;
		var descuento_item = document.getElementById('descuento_item').value;
		var tipo_iva = document.getElementById('tipo_iva').value;

		//Inicia validacion
		if (serie_liquidacion == "0") {
			$.notify("Seleccione una sucursal.");
			document.getElementById('serie_liquidacion').focus();
			return false;
		}
		if (secuencial_liquidacion == "0") {
			$.notify("Seleccione una sucursal.");
			document.getElementById('secuencial_liquidacion').focus();
			return false;
		}
		if (id_proveedor_lc == "") {
			$.notify("Seleccione un proveedor.");
			document.getElementById('nombre_proveedor_lc').focus();
			return false;
		}
		if (codigo_item == "") {
			$.notify("Ingrese código de producto o servicio.");
			document.getElementById('codigo_item').focus();
			return false;
		}
		if (detalle_item == "") {
			$.notify("Ingrese detalle de producto o servicio.");
			document.getElementById('detalle_item').focus();
			return false;
		}
		if (cantidad_item == "") {
			$.notify("Ingrese cantidad del producto o servicio.");
			document.getElementById('cantidad_item').focus();
			return false;
		}
		if (valuni_item == "") {
			$.notify("Ingrese valor unitario del producto o servicio.");
			document.getElementById('valuni_item').focus();
			return false;
		}
		if (isNaN(cantidad_item)) {
			$.notify("El dato ingresado en cantidad, no es un número.");
			document.getElementById('cantidad_item').focus();
			return false;
		}
		if (isNaN(valuni_item)) {
			$.notify("El dato ingresado en valor unitario, no es un número.");
			document.getElementById('valuni_item').focus();
			return false;
		}
		if (isNaN(descuento_item)) {
			$.notify("El dato ingresado en descuento, no es un número.");
			document.getElementById('descuento_item').focus();
			return false;
		}

		//Fin validacion
		$.ajax({
			type: "POST",
			url: "../ajax/agregar_item_lc.php",
			data: "serie_liquidacion=" + serie_liquidacion + "&codigo_item=" + codigo_item + "&detalle_item=" + detalle_item + "&cantidad_item=" + cantidad_item + "&valuni_item=" + valuni_item + "&descuento_item=" + descuento_item + "&tipo_iva=" + tipo_iva + "&secuencial_liquidacion=" + secuencial_liquidacion + "&id_proveedor_lc=" + id_proveedor_lc,
			beforeSend: function(objeto) {
				$("#resultados").html("Cargando...");
			},
			success: function(datos) {
				$("#resultados").html(datos);
				$("#codigo_item").val("");
				$("#detalle_item").val("");
				$("#cantidad_item").val("");
				$("#valuni_item").val("");
				$("#descuento_item").val("0");
				document.getElementById('codigo_item').focus();
			}
		});
		event.preventDefault();

	};


	//para agregar informacion adicional a la lc

	function agregar_info_adicional() {
		var id_proveedor_lc = $("#id_proveedor_lc").val();
		var serie_liquidacion = $("#serie_liquidacion").val();
		var secuencial_liquidacion = $("#secuencial_liquidacion").val();
		var adicional_concepto = $("#adicional_concepto").val();
		var adicional_descripcion = $("#adicional_descripcion").val();

		//Inicia validacion
		if (adicional_concepto == '') {
			$.notify("Ingrese concepto.");
			document.getElementById('adicional_concepto').focus();
			return false;
		}
		if (adicional_descripcion == '') {
			$.notify("Ingrese detalle.");
			document.getElementById('adicional_descripcion').focus();
			return false;
		}

		//Fin validacion
		$.ajax({
			type: "POST",
			url: "../ajax/agregar_item_lc.php",
			data: "agregar_adicional=agregar_adicional&serie_liquidacion=" + serie_liquidacion + "&secuencial_liquidacion=" + secuencial_liquidacion + "&adicional_concepto=" + adicional_concepto + "&adicional_descripcion=" + adicional_descripcion + "&id_proveedor_lc=" + id_proveedor_lc,
			beforeSend: function(objeto) {
				$("#resultados").html("Cargando...");
			},
			success: function(datos) {
				$("#resultados").html(datos);
				document.getElementById("adicional_concepto").value = "";
				document.getElementById("adicional_descripcion").value = "";
			}
		});
	};

	//pasa eliminar cada detalle adicional de la lc a guardarse
	function eliminar_detalle_info_adicional(id_info_adicional) {
		var id_proveedor_lc = $("#id_proveedor_lc").val();
		var serie_liquidacion = $("#serie_liquidacion").val();
		var secuencial_liquidacion = $("#secuencial_liquidacion").val();

		$.ajax({
			type: "POST",
			url: "../ajax/agregar_item_lc.php",
			data: "id_adicional=" + id_info_adicional + "&serie_liquidacion=" + serie_liquidacion + "&secuencial_liquidacion=" + secuencial_liquidacion + "&id_proveedor_lc=" + id_proveedor_lc,
			beforeSend: function(objeto) {
				$("#resultados").html("Cargando detalle...");
			},
			success: function(datos) {
				$("#resultados").html(datos);
				$.notify("Detalle adicional eliminado.", "error");
			}
		});
	};
</script>