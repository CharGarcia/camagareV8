<html lang="es">
<meta name="viewport" content="width=device-width, initial-scale=1">

<head>
	<title>Registro compras</title>
</head>
<?php
session_start();
if (isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa']) && isset($_SESSION['ruc_empresa'])) {
	$id_usuario = $_SESSION['id_usuario'];
	$id_empresa = $_SESSION['id_empresa'];
	$ruc_empresa = $_SESSION['ruc_empresa'];

	include("../paginas/menu_de_empresas.php");
	include("../clases/empresas.php");
	ini_set('date.timezone', 'America/Guayaquil');

	//para borrar los datos de la compra que este en temporal
	$con = conenta_login();
	if (isset($_SESSION['id_usuario'])) {
		$id_usuario = $_SESSION['id_usuario'];
		$delete_compra_tmp = mysqli_query($con, "DELETE FROM compra_tmp WHERE id_usuario = '" . $id_usuario . "';");
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
						<form class="form-group" id="guardar_registro_compra" name="guardar_registro_compra" method="POST">
							<span id="loader_guardar_compra"></span>
							<button id="guardar" type="submit" class="btn btn-info btn-md"><span class='glyphicon glyphicon-floppy-disk'></span> Guardar</button>
					</div>
					<h4><i class='glyphicon glyphicon-edit'></i> Registro de adquisiciones</h4>
				</div>

				<div class="panel-body ">
					<div id="resultados_ajax"></div>
					<div id="resultados_guardar_compra"></div>
					<div class="well well-sm">
						<div class="table-responsive">
							<table class="table table-bordered" style="margin-bottom: 5px; margin-top: 3px;">
								<td class='col-xs-2'>
									<label>Fecha emisión</label>
									<input type="text" class="form-control input-sm" name="fecha_compra" id="fecha_compra" value="<?php echo date("d-m-Y"); ?>">
								</td>

								<td class="col-xs-5">
									<label>Proveedor</label>
									<input type="hidden" id="id_proveedor_compra" name="id_proveedor_compra">
									<input type="hidden" id="total_compra" name="total_compra">
									<div id="datos_autorizacion"></div>
									<div class="input-group">
										<input class="form-control input-sm" id="nombre_proveedor_compra" name="nombre_proveedor_compra" placeholder="Proveedor" onkeyup='buscar_proveedores();' autocomplete="off">
										<span class="input-group-btn btn-md"><button class="btn btn-info btn-md" data-toggle="modal" data-target="#nuevoProveedorRetencion" type="button" title="Agregar proveedor"><span class="glyphicon glyphicon-plus"></span></button></span>
									</div>
								</td>

								<td class="col-xs-3">
									<label>Tipo comprobante</label>
									<select class="form-control" name="tipo_comprobante_compra" id="tipo_comprobante_compra" style="height:30px;">
										<?php
										$sql = mysqli_query($con, "SELECT * FROM comprobantes_autorizados order by comprobante asc ");
										while ($row = mysqli_fetch_assoc($sql)) {
											if ($row['codigo_comprobante'] == "1") {

										?>
												<option value="<?php echo $row['id_comprobante'] ?>" selected><?php echo $row['comprobante']; ?> </option>
											<?php

											} else {
											?>
												<option value="<?php echo $row['id_comprobante'] ?>"><?php echo $row['comprobante']; ?> </option>
										<?php
											}
										}
										?>
									</select>
								</td>
								<td class='col-xs-2'>
									<label># documento</label>
									<input type="text" class="form-control input-sm" onkeyup="buscar_autorizacion_sri();" id="numero_comprobante_compra" name="numero_comprobante_compra" placeholder="001-001-000000009" title="formato: 001-001-000000009">
								</td>
							</table>
							<?php
							$info_empresa = new empresas();
							$tipo_empresa = $info_empresa->datos_empresas($ruc_empresa)['tipo'];
							?>
							<input type="hidden" id="tipo_empresa" name="tipo_empresa" value="<?php echo $tipo_empresa; ?>">

							<?php
							if (intval($tipo_empresa) != 1) {
							?>
								<table class="table table-bordered" style="margin-bottom: 5px; margin-top: 3px;">
								<?php
							} else {
								?>
									<table class="table table-bordered" style="display: none; margin-bottom: 5px; margin-top: 3px;">
									<?php
								}
									?>
									<td class="col-xs-3">
										<label>Sustento tributario</label>
										<select class="form-control" name="sustento_tributario" id="sustento_tributario" style="height:30px;" tabindex="5">
											<!--<option value="01" selected>Crédito Tributario para declaración de IVA (servicios y bienes distintos de inventarios y activos fijos)</option> -->
										</select>
									</td>
									<td class='col-xs-3'>
										<label>Autorización. SRI</label>
										<input type="number" class="form-control input-sm" id="aut_sri_compra" name="aut_sri_compra" placeholder="1102520305" title="formato: 1102520305">
									</td>
									<td class='col-xs-1'>
										<label>Desde</label>
										<input type="number" class="form-control input-sm" id="numero_desde" name="numero_desde" placeholder="1111" title="formato: 1111">
									</td>
									<td class='col-xs-1'>
										<label>Hasta</label>
										<input type="number" class="form-control input-sm" id="numero_hasta" name="numero_hasta" placeholder="1111" title="formato: 1111">
									</td>
									<td class='col-xs-2'>
										<label>Caducidad</label>
										<input type="text" class="form-control input-sm" name="fecha_caducidad_compra" id="fecha_caducidad_compra" value="<?php echo date("d-m-Y"); ?>">
									</td>
									<td class='col-xs-2'>
										<p id="label_nc">
											<label>Factura aplica NC/ND</label>
											<input type="text" class="form-control input-sm" id="factura_aplica_nc" name="factura_aplica_nc" placeholder="001-001-123456789" title="Factura que afecta la NC, formato: 001-001-123456789">
										</p>
									</td>
									</table>


									<table class="table table-bordered" style="margin-bottom: 5px; margin-top: 3px;">
										<td class="col-xs-2">
											<label>Código</label>
											<input type="hidden" id="id_producto_compra" name="id_producto_compra">
											<input type="text" class="form-control input-sm" id="codigo_compra" name="codigo_compra" placeholder="Código" onkeyup='buscar_productos();'>
										</td>
										<td class="col-xs-4">
											<label>Detalle</label>
											<input type="text" class="form-control input-sm" id="detalle_compra" name="detalle_compra" placeholder="Detalle" title="Descripción" onkeyup='buscar_productos();'>
										</td>
										<td class='col-xs-1'>
											<label>Cantidad</label>
											<input type="number" class="form-control input-sm" id="cantidad_compra" name="cantidad_compra" title="Cantidad">
										</td>
										<td class='col-xs-1'>
											<label>Valor</label>
											<input type="number" class="form-control input-sm" id="val_uni_compra" name="val_uni_compra">
										</td>
										<td class='col-xs-1'>
											<label>Descuento</label>
											<input type="number" class="form-control input-sm" id="descuento_compra" name="descuento_compra">
										</td>
										<td class='col-xs-1'>
											<label>Impuesto</label>
											<select class="form-control" name="tipo_impuesto" id="tipo_impuesto" style="height:30px;">
												<?php
												$conexion = conenta_login();
												$sql = "SELECT * FROM impuestos_ventas WHERE status = '1' order by id_impuesto desc ";
												$res = mysqli_query($conexion, $sql);
												while ($o = mysqli_fetch_assoc($res)) {
												?>
													<option value="<?php echo $o['codigo_impuesto']; ?>" selected><?php echo $o['nombre_impuesto'] ?></option>
												<?php
												}
												?>
											</select>
										</td>
										<td class='col-xs-2'>
											<label>Tarífas</label>
											<select class="form-control" name="codigo_impuesto" id="codigo_impuesto" style="height:30px;">
												<option value="0" selected>Seleccione</option>
											</select>
										</td>
										<td class='col-xs-1'>
											<label>Agregar</label>
											<button type="button" class="btn btn-info btn-md" title="Agregar item" onclick="agregar_item_compra()"><span class="glyphicon glyphicon-plus"></span></button>
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
	<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
	<script src="../js/notify.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
	<script src="../js/siguiente_input.js" type="text/javascript"></script>
	<script>
		//al cargar la pagina se carga los sutentos en base a la factura que el documento default cargado
		$(document).ready(function() {
			var tipo_impuesto = $("#tipo_impuesto").val();
			//para cargar el tipo de impuesto
			$.post('../ajax/select_detalle_impuestos.php', {
				impuesto: tipo_impuesto
			}).done(function(respuesta) {
				$("#codigo_impuesto").html(respuesta);
			});

			document.getElementById("factura_aplica_nc").style.display = "none";
			document.getElementById("label_nc").style.display = "none";
			var codigo_comprobante = $("#tipo_comprobante_compra").val();

			//para cargar el sustento tributario
			$.post('../ajax/select_sustento_tributario.php', {
				id_documento: codigo_comprobante
			}).done(function(respuesta) {
				$("#sustento_tributario").html(respuesta);
			});

			$('#tipo_comprobante_compra').change(function() {
				codigo_comprobante = $("#tipo_comprobante_compra").val();
				if (codigo_comprobante == 4 || codigo_comprobante == 5 || codigo_comprobante == 23 || codigo_comprobante == 24 || codigo_comprobante == 47) {
					document.getElementById("factura_aplica_nc").style.display = "block";
					document.getElementById("label_nc").style.display = "block";
				} else {
					document.getElementById("factura_aplica_nc").style.display = "none";
					document.getElementById("label_nc").style.display = "none";
				}
			});
			document.getElementById('nombre_proveedor_compra').focus();
		});

		//para buscar_autorizacion_sri de la bd y cargar en el form de nueva compra
		function buscar_autorizacion_sri() {
			var id_proveedor = $("#id_proveedor_compra").val();
			var id_comprobante = $("#tipo_comprobante_compra").val();
			var numero_documento = $("#numero_comprobante_compra").val();

			$.post('../ajax/buscar_aut_sri.php', {
				action: 'autorizacion_sri',
				proveedor: id_proveedor,
				comprobante: id_comprobante,
				documento: numero_documento
			}).done(function(respuesta) {
				$("#datos_autorizacion").html(respuesta);
				var aut_sri = $("#aut_sri_encontrada").val();
				var desde = $("#desde_encontrada").val();
				var hasta = $("#hasta_encontrada").val();
				var fecha = $("#fecha_encontrada").val();
				var sustento_tributario = $("#sustento_tri").val();
				$("#aut_sri_compra").val(aut_sri);
				$("#numero_desde").val(desde);
				$("#numero_hasta").val(hasta);
				$("#fecha_caducidad_compra").val(fecha);
				$("#sustento_tributario").val(sustento_tributario);
			});
		}


		//para cargar los sustentos tributarios dependiendo el documento que se seleccione
		$(function() {
			$('#tipo_comprobante_compra').change(function() {
				var id_comprobante = $("#tipo_comprobante_compra").val();
				$.post('../ajax/select_sustento_tributario.php', {
					id_documento: id_comprobante
				}).done(function(respuesta) {
					$("#sustento_tributario").html(respuesta);
				});
			});

			//para cargar los tipos de iva, ice y botellas dependiendo de la seleccion anterior
			$('#tipo_impuesto').change(function() {
				var tipo_impuesto = $("#tipo_impuesto").val();
				$.post('../ajax/select_detalle_impuestos.php', {
					impuesto: tipo_impuesto
				}).done(function(respuesta) {
					$("#codigo_impuesto").html(respuesta);
				});
			});
		});


		jQuery(function($) {
			$("#fecha_compra").mask("99-99-9999");
			$("#fecha_caducidad_compra").mask("99-99-9999");
			$("#factura_aplica_nc").mask("999-999-9?99999999");
			$("#numero_comprobante_compra").mask("999-999-9?99999999");

		});

		//buscar proveedores
		function buscar_proveedores() {
			$("#nombre_proveedor_compra").autocomplete({
				source: '../ajax/proveedores_autocompletar.php',
				minLength: 2,
				select: function(event, ui) {
					event.preventDefault();
					$('#id_proveedor_compra').val(ui.item.id_proveedor);
					$('#nombre_proveedor_compra').val(ui.item.razon_social);
					ultimo_tipo_comprobante(ui.item.id_proveedor);
					document.getElementById('numero_comprobante_compra').focus();
				}
			});




			$("#nombre_proveedor_compra").on("keydown", function(event) {
				if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_proveedor_compra").val("");
					$("#nombre_proveedor_compra").val("");
				}
				if (event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_proveedor_compra").val("");
					$("#nombre_proveedor_compra").val("");
				}
			});
		}

		//para buscar el ultimo tipo de comprante que fue ingresado en compras
		function ultimo_tipo_comprobante(id_proveedor) {
			let request = (window.XMLHttpRequest) ?
				new XMLHttpRequest() :
				new ActiveXObject('Microsoft.XMLHTTP');
			let ajaxUrl = '../ajax/buscar_aut_sri.php?action=ultimo_comprobante&id_proveedor=' + id_proveedor;
			request.open("GET", ajaxUrl, true);
			request.send();
			request.onreadystatechange = function() {
				if (request.readyState == 4 && request.status == 200) {
					let objData = JSON.parse(request.responseText);
					if (objData.status) {
						let objDocumento = objData;
						document.querySelector("#tipo_comprobante_compra").value = objDocumento.id_comprobante;
						$.post('../ajax/select_sustento_tributario.php', {
							id_documento: objDocumento.id_comprobante
						}).done(function(respuesta) {
							$("#sustento_tributario").html(respuesta);
							document.querySelector("#sustento_tributario").value = objDocumento.id_sustento;
						});
						//document.getElementById('numero_comprobante_compra').focus();
					} else {
						$.notify(objData.msg, "error");
					}
				}
				return false;
			}
		}

		$(function() {
			$("#fecha_compra").datepicker({
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
			$("#fecha_caducidad_compra").datepicker({
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

		function eliminar_item_compra(id) {
			$.ajax({
				type: "GET",
				url: "../ajax/agregar_item_compra_tmp.php",
				data: "id=" + id,
				beforeSend: function(objeto) {
					$("#resultados").html("Mensaje: Cargando...");
				},
				success: function(datos) {
					$("#resultados").html(datos);
				}
			});

		};
		//para agregar un iten de compra
		function agregar_item_compra() {
			var codigo_compra = $("#codigo_compra").val();
			var detalle_compra = $("#detalle_compra").val();
			var cantidad_compra = $("#cantidad_compra").val();
			var val_uni_compra = $("#val_uni_compra").val();
			var descuento_compra = $("#descuento_compra").val();
			var tipo_impuesto = $("#tipo_impuesto").val();
			var codigo_impuesto = $("#codigo_impuesto").val();
			//Inicia validacion

			if (detalle_compra == "") {
				alert('Agregue un detalle de la compra');
				document.getElementById('detalle_compra').focus();
				return false;
			}
			if (isNaN(cantidad_compra)) {
				alert('El dato ingresado en cantidad, no es un número');
				document.getElementById('cantidad_compra').focus();
				return false;
			}
			if ((cantidad_compra) == 0) {
				alert('Ingrese valor en cantidad');
				document.getElementById('cantidad_compra').focus();
				return false;
			}
			if ((val_uni_compra) == 0) {
				alert('Ingrese valor en valor unitario');
				document.getElementById('val_uni_compra').focus();
				return false;
			}
			if (isNaN(val_uni_compra)) {
				alert('El dato ingresado en valor unitario, no es un número');
				document.getElementById('val_uni_compra').focus();
				return false;
			}

			//Fin validacion
			$.ajax({
				type: "POST",
				url: "../ajax/agregar_item_compra_tmp.php",
				data: "codigo_compra=" + codigo_compra + "&detalle_compra=" + detalle_compra + "&cantidad_compra=" + cantidad_compra + "&val_uni_compra=" + val_uni_compra + "&tipo_impuesto=" + tipo_impuesto + "&codigo_impuesto=" + codigo_impuesto + "&descuento_compra=" + descuento_compra,
				beforeSend: function(objeto) {
					$("#resultados").html("Mensaje: Cargando...");
				},
				success: function(datos) {
					$("#resultados").html(datos);
					$("#codigo_compra").val("");
					$("#detalle_compra").val("");
					$("#cantidad_compra").val("1");
					$("#val_uni_compra").val("");
					$("#descuento_compra").val("");
					document.getElementById('detalle_compra').focus();
				}
			});

		};

		//para guardar la compra
		$("#guardar_registro_compra").submit(function(event) {
			$('#guardar').attr("disabled", true);
			//para pasar el total de compra de un text a otro text para que tome en el serialize
			var suma_factura_compra = $("#suma_factura_compra").val();
			$("#total_compra").val(suma_factura_compra);
			//de aqui para abajo para guardar la factura
			var parametros = $(this).serialize();
			$.ajax({
				type: "POST",
				url: '../ajax/guardar_compra.php',
				data: parametros,
				beforeSend: function(objeto) {
					$("#loader_guardar_compra").html("Guardando...");
				},
				success: function(datos) {
					$("#resultados_guardar_compra").html(datos);
					$("#loader_guardar_compra").html("");
					$('#guardar').attr("disabled", false);
				}
			});
			event.preventDefault();
		});


		//para guardar un nuevo proveedor
		$("#guardar_proveedor").submit(function(event) {
			$('#guardar_datos').attr("disabled", true);
			var parametros = $(this).serialize();
			$.ajax({
				type: "POST",
				url: '../ajax/guarda_proveedor_retencion.php',
				data: parametros,
				beforeSend: function(objeto) {
					$("#resultados_ajax").html("Mensaje: Guardando...");
				},
				success: function(datos) {
					$("#resultados_ajax").html(datos);
					$('#guardar_datos').attr("disabled", false);
				}
			});
			event.preventDefault();
		});

		//para buscar productos en ingresar productos de la factura
		function buscar_productos() {
			var id_proveedor = $("#id_proveedor_compra").val();
			var tipo_comprobante_compra = $("#tipo_comprobante_compra").val();
			var numero_documento = $("#numero_comprobante_compra").val();
			$.post('../ajax/buscar_aut_sri.php', {
				action: 'documento_existente',
				proveedor: id_proveedor,
				comprobante: tipo_comprobante_compra,
				documento: numero_documento
			}).done(function(repetido) {
				if (repetido != "") {
					$.notify('El documento ya esta registrado.', 'error');
				}
			});


			$("#detalle_compra").autocomplete({
				source: '../ajax/productos_autocompletar.php',
				minLength: 2,
				select: function(event, ui) {
					event.preventDefault();
					$('#id_producto_compra').val(ui.item.id);
					$('#detalle_compra').val(ui.item.nombre);
					$('#codigo_compra').val(ui.item.codigo);
					document.getElementById('cantidad_compra').focus();
				}
			});

			$("#detalle_compra").autocomplete("widget").addClass("fixedHeight");

			$("#detalle_compra").on("keydown", function(event) {
				if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_producto_compra").val("");
					$("#detalle_compra").val("");
					$("#codigo_compra").val("");
				}

				if (event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_producto_compra").val("");
					$("#detalle_compra").val("");
					$("#codigo_compra").val("");
				}

				if (event.keyCode == $.ui.keyCode.BACKSPACE) {
					$("#id_producto_compra").val("");
					$("#codigo_compra").val("");
				}
			});


			$("#codigo_compra").autocomplete({
				source: '../ajax/productos_autocompletar.php',
				minLength: 2,
				select: function(event, ui) {
					event.preventDefault();
					$('#id_producto_compra').val(ui.item.id);
					$('#detalle_compra').val(ui.item.nombre);
					$('#codigo_compra').val(ui.item.codigo);
					document.getElementById('cantidad_compra').focus();
				}
			});

			$("#codigo_compra").autocomplete("widget").addClass("fixedHeight");
			$("#codigo_compra").on("keydown", function(event) {
				if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_producto_compra").val("");
					$("#detalle_compra").val("");
					$("#codigo_compra").val("");
				}

				if (event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_producto_compra").val("");
					$("#detalle_compra").val("");
					$("#codigo_compra").val("");
				}

				if (event.keyCode == $.ui.keyCode.BACKSPACE) {
					$("#id_producto_compra").val("");
					$("#codigo_compra").val("");
					$("#detalle_compra").val("");
				}
			});
		}
		//para cuando cambia en el secuencial de factura y se aplique los ceros a la izquierda
		$(function() {
			$('#numero_comprobante_compra').change(function() {
				var numero_comprobante = $("#numero_comprobante_compra").val();
				var serie = numero_comprobante.substr(0, 8);
				var secuencial = numero_comprobante.substr(8, 9);
				while (secuencial.length < 9) {
					var secuencial = '0' + secuencial;
					$("#numero_comprobante_compra").val(serie + secuencial);
				}
			});
		});

		//para verificar mes y año que sean del periodo actual y muestre una advertencia
		$(function() {
			$('#fecha_compra').change(function() {
				var fecha_input = $("#fecha_compra").val();
				let date = new Date();
				if (fecha_input.length = 10) {
					let fecha_hoy = String(date.getDate()).padStart(2, '0') + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + date.getFullYear();
					let mes_entra = fecha_input.substr(3, 2);
					let mes_hoy = fecha_hoy.substr(3, 2);

					let anio_entra = fecha_input.substr(7, 4);
					let anio_hoy = fecha_hoy.substr(7, 4);
					if (mes_entra != mes_hoy) {
						$("#fecha_compra").notify("El mes ingresado no es igual al mes actual", {
							position: "top"
						});
					}

					if (anio_entra != anio_hoy) {
						$("#fecha_compra").notify("El año ingresado no es igual al año actual", {
							position: "top"
						});
					}
				}
				document.getElementById('nombre_proveedor_compra').focus();
			});
		});
	</script>

</html>