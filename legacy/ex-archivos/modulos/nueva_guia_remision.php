<html lang="es">
<meta name="viewport" content="width=device-width, initial-scale=1">

<head>
	<title>Guía de remisión</title>
</head>

<?php
session_start();
if (isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa']) && isset($_SESSION['ruc_empresa'])) {
	$id_usuario = $_SESSION['id_usuario'];
	$id_empresa = $_SESSION['id_empresa'];
	$ruc_empresa = $_SESSION['ruc_empresa'];

	include("../paginas/menu_de_empresas.php");
	include("../ajax/autocompleta/clientes.php");
	ini_set('date.timezone', 'America/Guayaquil');
	//para borrar los datos de la guia que este en temporal
	$con = conenta_login();
	if (isset($_SESSION['id_usuario'])) {
		$id_usuario_tmp = $_SESSION['id_usuario'];
		$delete_factura_tmp = mysqli_query($con, "DELETE FROM factura_tmp WHERE id_usuario = '" . $id_usuario_tmp . "' and ruc_empresa = '" . $ruc_empresa . "'");
		$delete_info_adicional_tmp = mysqli_query($con, "DELETE FROM adicional_tmp WHERE id_usuario = '" . $id_usuario_tmp . "';");
	}
?>

	<body>
		<?php
		include("../modal/buscar_productos.php");
		include("../modal/registro_productos.php");
		include("../modal/clientes.php");
		//include("../modal/registro_clientes.php");				
		?>

		<div class="container">
			<div class="panel panel-info">
				<div class="panel-heading">
					<div class="btn-group pull-right">
						<form class="form-group" id="guardar_guia" name="guardar_guia" method="POST">
							<span id="mensaje_guardar_gr_electronica"></span>
							<button id="guardar_datos_guia" type="submit" class="btn btn-info btn-md"><span class='glyphicon glyphicon-floppy-disk'></span> Guardar</button>
					</div>
					<h4><i class='glyphicon glyphicon-edit'></i> Nueva guía de remisión</h4>
				</div>
				<div class="panel-body">
					<div id="resultados_guardar_guia_remision"></div>
					<div class="well well-sm">
						<div class="panel panel-default">
							<div class="panel-body">
								<div class="form-group row">
									<input type="hidden" id="id_transportista_guia" name="id_transportista_guia">
									<div class="col-sm-3">
										<div class="input-group">
											<span class="input-group-addon"><b>Fecha emisión</b></span>
											<input type="text" class="form-control input-sm" name="fecha_guia" id="fecha_guia" value="<?php echo date("d-m-Y"); ?>">
										</div>
									</div>
									<div class="col-md-2">
										<div class="input-group">
											<span class="input-group-addon"><b>Serie</b></span>
											<?php $conexion = conenta_login(); ?>
											<select class="form-control input-sm" name="serie_guia" id="serie_guia" required>
												<?php
												$sql = "SELECT * FROM sucursales where ruc_empresa ='" . $ruc_empresa . "' order by id_sucursal asc;";
												$res = mysqli_query($conexion, $sql);
												while ($o = mysqli_fetch_assoc($res)) {
												?>
													<option value="<?php echo $o['serie']; ?>" selected><?php echo $o['serie']; ?></option>
												<?php
												}
												?>
											</select>
										</div>
									</div>
									<div class="col-md-3">
										<div class="input-group">
											<span class="input-group-addon"><b>Secuencial</b></span>
											<input type="text" class="form-control input-sm text-right" id="secuencial_guia" name="secuencial_guia" placeholder="000000001" readonly>
										</div>
									</div>
									<div class="col-md-4">
										<div class="input-group">
											<span class="input-group-addon"><b>Factura</b></span>
											<input type="text" class="form-control input-sm" id="factura_guia" name="factura_guia" placeholder="001-001-000000009">
											<span class="input-group-btn">
												<button type="button" onclick="pasa_factura_guia();" class="btn btn-info btn-sm" title="Cargar datos de factura"><span class="glyphicon glyphicon-search">
													</span> Cargar</button></span>
										</div>
									</div>
								</div>
								<div class="form-group row">
									<div class="col-md-6">
										<div class="input-group">
											<span class="input-group-addon"><b>Transportista</b></span>
											<input type="text" class="form-control input-sm" id="transportista_guia" name="transportista_guia" onkeyup="buscar_transportista()" placeholder="Buscar un transportista" autocomplete="off"><span class="input-group-btn btn-md"><button class="btn btn-info btn-md" type="button" title="Nuevo transportista" data-toggle="modal" onclick="crear_cliente()" data-target="#nuevoCliente"><span class="glyphicon glyphicon-pencil"></span></button></span>
										</div>
									</div>
									<div class="col-md-2">
										<div class="input-group">
											<span class="input-group-addon"><b>Placa</b></span>
											<input type="text" class="form-control input-sm" id="placa_guia" name="placa_guia" placeholder="Placa">
										</div>
									</div>
									<div class="col-md-4">
										<div class="input-group">
											<span class="input-group-addon"><b>Motivo</b></span>
											<input type="text" class="form-control input-sm" id="motivo_guia" name="motivo_guia" placeholder="Motivo de traslado">
										</div>
									</div>

								</div>
								<div class="form-group row">
									<input type="hidden" id="id_cliente_guia" name="id_cliente_guia">
									<div class="col-md-6">
										<div class="input-group">
											<span class="input-group-addon"><b>Cliente</b></span>
											<input type="text" class="form-control input-sm" id="cliente_guia" name="cliente_guia" onkeyup="buscar_cliente()" placeholder="Buscar un cliente" autocomplete="off"><span class="input-group-btn btn-md"><button class="btn btn-info btn-md" type="button" title="Nuevo cliente" data-toggle="modal" data-target="#nuevoCliente" onclick="crear_cliente()"><span class="glyphicon glyphicon-pencil"></span></button></span>
										</div>
									</div>
									<div class="col-md-3">
										<div class="input-group">
											<span class="input-group-addon"><b>Fecha salida</b></span>
											<input type="text" class="form-control input-sm" id="fecha_salida_guia" name="fecha_salida_guia" placeholder="Fecha Salida" value="<?php echo date("d-m-Y"); ?>">
										</div>
									</div>
									<div class="col-md-3">
										<div class="input-group">
											<span class="input-group-addon"><b>Fecha llegada</b></span>
											<input type="text" class="form-control input-sm" id="fecha_llegada_guia" name="fecha_llegada_guia" placeholder="Fecha Llegada" value="<?php echo date("d-m-Y"); ?>">
										</div>
									</div>
								</div>
								<div class="form-group row">
									<div class="col-md-6">
										<div class="input-group">
											<span class="input-group-addon"><b>Origen</b></span>
											<input type="text" class="form-control input-sm" id="partida_guia" name="partida_guia" placeholder="Punto de partida">
										</div>
									</div>
									<div class="col-md-6">
										<div class="input-group">
											<span class="input-group-addon"><b>Destino</b></span>
											<input type="text" class="form-control input-sm" id="destino_guia" name="destino_guia" placeholder="Punto de llegada">
										</div>
									</div>


								</div>
								<div class="form-group row">
									<div class="col-md-6">
										<div class="input-group">
											<span class="input-group-addon"><b>Ruta</b></span>
											<input type="text" class="form-control input-sm" id="ruta_guia" name="ruta_guia" placeholder="Ruta al destino" maxlength="300">
										</div>
									</div>
									<div class="col-md-3">
										<div class="input-group">
											<span class="input-group-addon"><b>Doc. aduanero</b></span>
											<input type="text" class="form-control input-sm" id="aduanero_guia" name="aduanero_guia" placeholder="Documento">
										</div>
									</div>
									<div class="col-md-3">
										<div class="input-group">
											<span class="input-group-addon"><b>Cód est. destino</b></span>
											<input type="text" class="form-control input-sm" id="codigo_destino_guia" name="codigo_destino_guia" placeholder="001">
										</div>
									</div>
								</div>
							</div>
						</div>
						</form>

						<!-- agregar itens a guia de remision-->
						<div class="panel panel-info">
							<div class="table-responsive">
								<table class="table table-bordered">
									<tr class="success">
										<th style="padding: 2px;">Producto</th>
										<th style="padding: 2px;" class="text-center">Cantidad</th>
										<th style="padding: 2px;" class="text-center">Agregar</th>
									</tr>
									<input type="hidden" name="id_agregar" id="id_agregar">
									<td class='col-xs-6'>
										<input style="z-index:inherit;" type="text" class="form-control input-sm" title="Ingresar producto." name="producto_agregar" id="producto_agregar" placeholder="Ingrese un producto" onkeyup='buscar_productos();' autocomplete="off">
									</td>
									<td class='col-xs-1'>
										<div class="pull-right">
											<input type="text" class="form-control input-sm" style="text-align:right;" title="Ingrese cantidad" name="cantidad_agregar" id="cantidad_agregar" placeholder="Cantidad">
										</div>
									</td>
									<td class="col-sm-1" style="text-align:center;">
										<button type="button" class="btn btn-info btn-md" title="Agregar productos a la guía de remisión" onclick="agregar_item_gr_manual()"><span class="glyphicon glyphicon-plus"></span></button>
									</td>
								</table>
							</div>
						</div>
						<!-- hasta aqui agregar iten a guia de remision -->
					</div>
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
	<script src="../js/notify.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>

</html>
<script>
	$(document).ready(function() {
		var id_serie = $("#serie_guia").val();
		$.post('../ajax/buscar_ultima_gr.php', {
			serie_gr: id_serie
		}).done(function(respuesta) {
			var guia_final = respuesta;
			$("#secuencial_guia").val(guia_final);
		});
	});

	jQuery(function($) {
		$("#fecha_guia").mask("99-99-9999");
		$("#fecha_salida_guia").mask("99-99-9999");
		$("#fecha_llegada_guia").mask("99-99-9999");
		$("#factura_guia").mask("999-999-9?99999999");
		$("#placa_guia").mask("***-****");
		$("#codigo_destino_guia").mask("999");
	});

	function crear_cliente() {
		document.querySelector("#titleModalCliente").innerHTML = "<i class='glyphicon glyphicon-ok'></i> Nuevo Cliente";
		document.querySelector("#guardar_cliente").reset();
		document.querySelector("#id_cliente").value = "";
		document.querySelector("#btnActionFormCliente").classList.replace("btn-info", "btn-primary");
		document.querySelector("#btnTextCliente").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Guardar";
		document.querySelector('#btnActionFormCliente').title = "Guardar cliente";
	}

	function buscar_cliente() {
		$("#cliente_guia").autocomplete({
			source: '../ajax/clientes_autocompletar.php',
			minLength: 2,
			select: function(event, ui) {
				event.preventDefault();
				$('#id_cliente_guia').val(ui.item.id);
				$('#cliente_guia').val(ui.item.nombre);
				$('#destino_guia').val(ui.item.direccion);
				cambia_cliente();
			}
		});

		$("#cliente_guia").on("keydown", function(event) {
			if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
				$("#id_cliente_guia").val("");
				$("#cliente_guia").val("");
			}
			if (event.keyCode == $.ui.keyCode.DELETE) {
				$("#cliente_guia").val("");
				$("#id_cliente_guia").val("");
			}
		});
	}

	function buscar_transportista() {
		$("#transportista_guia").autocomplete({
			source: '../ajax/clientes_autocompletar.php',
			minLength: 2,
			select: function(event, ui) {
				event.preventDefault();
				$('#id_transportista_guia').val(ui.item.id);
				$('#transportista_guia').val(ui.item.nombre);
				buscar_placa(ui.item.id);
			}
		});

		$("#transportista_guia").on("keydown", function(event) {
			if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
				$("#id_transportista_guia").val("");
				$("#transportista_guia").val("");
			}
			if (event.keyCode == $.ui.keyCode.DELETE) {
				$("#transportista_guia").val("");
				$("#id_transportista_guia").val("");
			}
		});
	}


	//para pasar el numero de la factura y cargar los datos de la factura y del cliente en la guia de remision
	function pasa_factura_guia() {
		var factura = $("#factura_guia").val();
		var serie_guia = $("#serie_guia").val();
		var secuencial_guia = $("#secuencial_guia").val();

		if (serie_guia == '0') {
			alert('Seleccione serie');
			$('#id_cliente_guia').val('');
			$('#cliente_guia').val('');
			$('#destino_guia').val('');
			document.getElementById('serie_guia').focus();
			return false;
		}

		if (serie_guia == '') {
			alert('Seleccione serie');
			$('#id_cliente_guia').val('');
			$('#cliente_guia').val('');
			$('#destino_guia').val('');
			document.getElementById('serie_guia').focus();
			return false;
		}

		if (factura == '') {
			alert('Ingrese número de factura');
			document.getElementById('factura_guia').focus();
			return false;
		}

		$.ajax({
			type: "POST",
			url: "../ajax/buscar_detalle_factura_guia.php",
			data: "factura=" + factura,
			beforeSend: function(objeto) {
				$("#resultados").html("Mensaje: Cargando...");
			},
			success: function(datos) {
				$("#resultados").html(datos);
				//pasa los datos a la guia
				var direccion_sucursal = $("#direccion_sucursal").val();
				var direccion_cliente = $("#direccion_cliente").val();
				var fecha_factura = $("#fecha_factura").val();
				var guia_remision_serie = $("#guia_remision_serie").val();
				var guia_remision_secuencial = $("#guia_remision_secuencial").val();
				var id_cliente = $("#id_cliente_factura").val();
				var nombre_cliente = $("#nombre_cliente_guia").val();
				var est_destino = $("#est_destino").val();

				$("#partida_guia").val(direccion_sucursal);
				$("#destino_guia").val(direccion_cliente);
				$("#motivo_guia").val("Venta");
				$("#fecha_salida_guia").val(fecha_factura);
				$("#fecha_llegada_guia").val(fecha_factura);
				$("#id_cliente_guia").val(id_cliente);
				$("#cliente_guia").val(nombre_cliente);
				$("#codigo_destino_guia").val(est_destino);
				cambia_cliente();
				document.getElementById('transportista_guia').focus();
			}
		});
	}

	//pasa iten
	function agregar_item_gr_manual() {
		var id_agregar = $("#id_agregar").val();
		var producto_agregar = $("#producto_agregar").val();
		var cantidad_agregar = $("#cantidad_agregar").val();
		var serie_guia = $("#serie_guia").val();
		var secuencial_guia = $("#secuencial_guia").val();
		var id_cliente = $("#id_cliente_guia").val();
		//Inicia validacion

		if (serie_guia == '0') {
			alert('Seleccione serie.');
			document.getElementById('serie_guia').focus();
			return false;
		}

		if (serie_guia == '') {
			alert('Seleccione serie.');
			document.getElementById('serie_guia').focus();
			return false;
		}

		if (id_agregar == '') {
			alert('Seleccione un producto.');
			document.getElementById('producto_agregar').focus();
			return false;
		}

		if (cantidad_agregar == '') {
			alert('Ingrese cantidad.');
			document.getElementById('cantidad_agregar').focus();
			return false;
		}

		if (isNaN(cantidad_agregar)) {
			alert('El dato ingresado en cantidad, no es un número');
			document.getElementById('cantidad_agregar').focus();
			return false;
		}

		$.ajax({
			type: "POST",
			url: "../ajax/buscar_detalle_factura_guia.php",
			data: "ingreso_item=ingreso_item&id_producto=" + id_agregar + "&cant_producto=" + cantidad_agregar + "&serie_guia=" + serie_guia + "&secuencial_guia=" + secuencial_guia + "&id_cliente=" + id_cliente,
			beforeSend: function(objeto) {
				$("#resultados").html("Mensaje: Cargando...");
			},
			success: function(datos) {
				$("#resultados").html(datos);
				$("#producto_agregar").val("");
				$("#id_agregar").val("");
				$("#cantidad_agregar").val("");
				document.getElementById('producto_agregar').focus();
				cambia_cliente();
			}
		});

	}



	//para buscar productos
	function buscar_productos() {
		$("#producto_agregar").autocomplete({
			source: '../ajax/productos_autocompletar.php',
			minLength: 2,
			select: function(event, ui) {
				event.preventDefault();
				$('#id_agregar').val(ui.item.id);
				$('#producto_agregar').val(ui.item.nombre);
				document.getElementById('cantidad_agregar').focus();
			}
		});

		$("#producto_agregar").autocomplete("widget").addClass("fixedHeight"); //para que aparezca la barra de desplazamiento en el buscar

		$("#producto_agregar").on("keydown", function(event) {
			if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
				$("#id_agregar").val("");
				$("#producto_agregar").val("");
			}
		});
	}

	//para mostrar la guia que continua segun la serie seleccionada
	$(function() {
		$('#serie_guia').change(function() {
			var id_serie = $("#serie_guia").val();
			$.post('../ajax/buscar_ultima_gr.php', {
				serie_gr: id_serie
			}).done(function(respuesta) {
				var guia_final = respuesta;
				$("#secuencial_guia").val(guia_final);
			});
		});
	});


	$(function() {
		$("#fecha_guia").datepicker({
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
		$("#fecha_salida_guia").datepicker({
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
		$("#fecha_llegada_guia").datepicker({
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
		$("#fecha_llegada_guia").datepicker("option", "minDate", "-1m:+24d");
		$("#fecha_llegada_guia").datepicker("option", "maxDate", "+0m +0d");
	});



	//para guardar la guia electronica
	$(function() {
		$("#guardar_guia").submit(function(event) {
			$('#guardar_datos_guia').attr("disabled", true);
			//de aqui para abajo para guardar la factura
			var parametros = $(this).serialize();
			$.ajax({
				type: "POST",
				url: '../ajax/guardar_guia_remision_electronica.php',
				data: parametros,
				beforeSend: function(objeto) {
					$("#mensaje_guardar_gr_electronica").html("Guardando...");
				},
				success: function(datos) {
					$("#resultados_guardar_guia_remision").html(datos);
					$('#guardar_datos_guia').attr("disabled", false);
					$("#mensaje_guardar_gr_electronica").html("");
					load(1);
				}
			});
			event.preventDefault();
		});
	});

	function eliminar_iten_guia(id) {
		var serie_guia = $("#serie_guia").val();
		var secuencial_guia = $("#secuencial_guia").val();
		var id_cliente = $("#id_cliente_guia").val();

		if (serie_guia == "0") {
			alert('Seleccione serie de guía de remisión.');
			document.getElementById('serie_guia').focus();
			return false;
		}
		if (serie_guia == '') {
			alert('Seleccione serie de guía de remisión.');
			document.getElementById('serie_guia').focus();
			return false;
		}

		$.ajax({
			type: "GET",
			url: "../ajax/buscar_detalle_factura_guia.php",
			data: "id_eliminar=" + id + "&serie_guia=" + serie_guia + "&secuencial_guia=" + secuencial_guia + "&id_cliente=" + id_cliente,
			beforeSend: function(objeto) {
				$("#resultados").html("Mensaje: Cargando...");
			},
			success: function(datos) {
				$("#resultados").html(datos);
			}
		});

	};

	function eliminar_detalle_info_adicional_gr(id) {
		var serie_guia = $("#serie_guia").val();
		var secuencial_guia = $("#secuencial_guia").val();
		var id_cliente = $("#id_cliente_guia").val();

		$.ajax({
			type: "POST",
			url: "../ajax/buscar_detalle_factura_guia.php",
			data: "eliminar_info_adicional_gr=eliminar_info_adicional_gr&id_info_gr=" + id + "&serie_guia=" + serie_guia + "&secuencial_guia=" + secuencial_guia + "&id_cliente=" + id_cliente,
			beforeSend: function(objeto) {
				$("#resultados").html("Mensaje: Cargando...");
			},
			success: function(datos) {
				$("#resultados").html(datos);
			}
		});
	}

	//para agregar info adicional en la guia
	function agregar_info_adicional_gr() {
		var serie_guia = $("#serie_guia").val();
		var secuencial_guia = $("#secuencial_guia").val();
		var id_cliente = $("#id_cliente_guia").val();
		var adicional_concepto = $("#adicional_concepto").val();
		var adicional_descripcion = $("#adicional_descripcion").val();

		if (adicional_concepto == "") {
			alert('Ingrese concepto.');
			document.getElementById('adicional_concepto').focus();
			return false;
		}
		if (adicional_descripcion == "") {
			alert('Ingrese detalle de información adicional.');
			document.getElementById('adicional_descripcion').focus();
			return false;
		}

		$.ajax({
			type: "POST",
			url: "../ajax/buscar_detalle_factura_guia.php",
			data: "agregar_info_adicional_gr=agregar_info_adicional_gr&serie_guia=" + serie_guia + "&secuencial_guia=" + secuencial_guia + "&id_cliente=" + id_cliente + "&adicional_concepto=" + adicional_concepto + "&adicional_descripcion=" + adicional_descripcion,
			beforeSend: function(objeto) {
				$("#resultados").html("Mensaje: Cargando...");
			},
			success: function(datos) {
				$("#resultados").html(datos);
			}
		});
	}

	//para cuando cambia en el secuencial de factura y se aplique los ceros a la izquierda
	$(function() {
		$('#factura_guia').change(function() {
			var numero_comprobante = $("#factura_guia").val();
			var serie = numero_comprobante.substr(0, 8);
			var secuencial = numero_comprobante.substr(8, 9);
			while (secuencial.length < 9) {
				var secuencial = '0' + secuencial;
				$("#factura_guia").val(serie + secuencial);
			}
		});
	});


	//al cambiar de cliente debe actualizarse los datos del mismo en adicionales
	function cambia_cliente() {
		var serie_guia = $("#serie_guia").val();
		var secuencial_guia = $("#secuencial_guia").val();
		var id_cliente = $("#id_cliente_guia").val();

		if (serie_guia == '0') {
			alert('Seleccione serie');
			$('#id_cliente_guia').val('');
			$('#cliente_guia').val('');
			$('#destino_guia').val('');
			document.getElementById('serie_guia').focus();
			return false;
		}

		if (serie_guia == '') {
			alert('Seleccione serie');
			$('#id_cliente_guia').val('');
			$('#cliente_guia').val('');
			$('#destino_guia').val('');
			document.getElementById('serie_guia').focus();
			return false;
		}
		//Fin validacion
		$.ajax({
			type: "POST",
			url: "../ajax/buscar_detalle_factura_guia.php",
			data: "cambia_cliente=cambia_cliente&serie_guia=" + serie_guia + "&secuencial_guia=" + secuencial_guia + "&id_cliente=" + id_cliente,
			beforeSend: function(objeto) {
				$("#resultados").html("Cargando...");
			},
			success: function(datos) {
				$("#resultados").html(datos);
			}
		});
	}

	function buscar_placa(id_transportista) {
		$.ajax({
			type: "POST",
			url: "../ajax/buscar_guias_remision.php?action=buscar_placa",
			data: "id_transportista=" + id_transportista,
			beforeSend: function(objeto) {
				$("#mensaje_guardar_gr_electronica").html("Actualizando...");
			},
			success: function(datos) {
				$.each(datos, function(i, item) {
					$("#placa_guia").val(item.placa);
				});
				$("#mensaje_guardar_gr_electronica").html("");
				document.getElementById('ruta_guia').focus();
			}
		});
	}
</script>