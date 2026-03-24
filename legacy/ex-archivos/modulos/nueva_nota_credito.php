<html lang="es">
<meta name="viewport" content="width=device-width, initial-scale=1">

<head>
	<title>Nota de crédito</title>
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
		$delete_nc_tmp = mysqli_query($con, "DELETE FROM factura_tmp WHERE id_usuario = '" . $id_tmp . "' and ruc_empresa = '" . $ruc_empresa . "'");
	}
?>

	<body>
		<?php
		include("../modal/agregar_items_nc.php");
		?>
		<div class="container">
			<div class="panel panel-info">
				<div class="panel-heading">
					<div class="btn-group pull-right">
						<form class="form-group" id="guardar_nc_e" name="guardar_nc_e" method="POST">
							<span id="mensaje_guardar_nc_electronica"></span>
							<button id="guardar_datos_nc_e" type="submit" class="btn btn-info btn-md"><span class='glyphicon glyphicon-floppy-disk'></span> Guardar</button>
					</div>
					<h4><i class='glyphicon glyphicon-edit'></i> Nueva nota de crédito</h4>
				</div>


				<div class="panel-body">
					<div id="resultados_ajax"></div>
					<div id="resultados_guardar_nc_electronica"></div>

					<div class="well well-sm">
						<div class="table-responsive">
							<table class="table table-bordered">
								<tr class="info">
									<td style="padding: 2px;" class="text-center">Fecha nota crédito</td>
									<td style="padding: 2px;" class="text-center">Serie</td>
									<td style="padding: 2px;" class="text-center">Secuencial</td>
									<td style="padding: 2px;" class="text-center">N°. Factura aplica NC</td>
									<td style="padding: 2px;" class="text-center">Cargar desde factura</td>
								</tr>
								<td class="col-xs-2">
									<input type="text" class="form-control input-sm text-center" name="fecha_nc_e" id="fecha_nc_e" value="<?php echo date("d-m-Y"); ?>">
								</td>
								<td class="col-xs-2">
									<select class="form-control input-sm" name="serie_nc_e" id="serie_nc_e" required>
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
								<td class="col-xs-2"><input type="text" class="form-control input-sm text-right" id="secuencial_nc_e" name="secuencial_nc_e" placeholder="000000001" readonly></td>
								<td class="col-xs-2"><input type="text" class="form-control input-sm text-center" id="numero_factura" name="numero_factura" placeholder="001-001-000000009" title="formato: 001-001-000000009" required></td>
								<td class='col-xs-2'><button type="button" onclick="pasa_factura_nc();" class="btn btn-info btn-sm" data-toggle="modal" data-target="#agregarItemsnc" title="Agregar items a nota de crédito">
										<span class="glyphicon glyphicon-download-alt"></span> Cargar items de factura</button></td>
								<input type="hidden" id="total_nc_e" name="total_nc_e">
							</table>
						</div>
						<div class="form-group row">
							<div class="col-sm-3">
								<div class="input-group">
									<span class="input-group-addon"><b>Fecha factura</b></span>
									<input type="text" class="form-control input-sm text-center" name="fecha_factura" id="fecha_factura" value="<?php echo date("d-m-Y"); ?>">
								</div>
							</div>
							<div class="col-sm-9">
								<div class="input-group">
									<span class="input-group-addon"><b>Razón social / Nombre</b></span>
									<input type="text" class="form-control input-sm" name="nombre_cliente" id="nombre_cliente" placeholder="Razón social" onkeyup="buscar_clientes()">
									<input type="hidden" id="id_cliente" name="id_cliente">
								</div>
							</div>
						</div>
						<div class="form-group row">
							<div class="col-sm-12">
								<div class="input-group">
									<span class="input-group-addon"><b>Razón o motivo de modificación</b></span>
									<input type="text" class="form-control input-sm" name="motivo" id="motivo" placeholder="Motivo">
								</div>
							</div>
						</div>
						</form>
					</div>

					<div class="panel panel-info">
						<div class="table-responsive">
							<table class="table table-bordered">
								<tr class="success">
									<th style="padding: 2px;">Producto/Servicio</th>
									<th style="padding: 2px;" class="text-center">Cantidad</th>
									<th style="padding: 2px;" class="text-center">Precio/U.</th>
									<th style="padding: 2px;" class="text-center">Descuento</th>
									<th style="padding: 2px;" class="text-center">Agregar</th>
								</tr>
								<input type="hidden" name="id_agregar" id="id_agregar">
								<td class='col-xs-6'>
									<input style="z-index:inherit;" type="text" class="form-control input-sm" title="Ingresar producto o servicio." name="producto_agregar" id="producto_agregar" placeholder="Ingrese un producto" onkeyup='buscar_productos();' autocomplete="off">
								</td>
								<td class='col-xs-1'>
									<div class="pull-right">
										<input type="text" class="form-control input-sm" style="text-align:right;" title="Ingrese cantidad" name="cantidad_agregar" id="cantidad_agregar" placeholder="Cantidad">
									</div>
								</td>
								<td class='col-xs-1'>
									<input type="text" class="form-control input-sm" style="text-align:right;" title="Ingrese precio" name="precio_agregar" id="precio_agregar" placeholder="Precio">
								</td>
								<td class='col-xs-1'>
									<input type="text" style="text-align:right;" class="form-control input-sm" id="descuento_agregar" name="descuento_agregar" title="Ingrese descuento" placeholder="Descuento">
								</td>
								<td class="col-sm-1" style="text-align:center;">
									<button type="button" class="btn btn-info btn-md" title="Agregar productos a la nota de crédito" onclick="agregar_item_nc_manual()"><span class="glyphicon glyphicon-plus"></span></button>
								</td>
							</table>
						</div>
					</div>

					<div id="resultados_nc"></div><!-- Carga los datos ajax del detalle de la factura -->

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
		$("#fecha_nc_e").mask("99-99-9999");
		$("#fecha_factura").mask("99-99-9999");
		$("#numero_factura").mask("999-999-9?99999999");
	});
	$(function() {
		$("#fecha_nc_e").datepicker({
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

		$("#fecha_factura").datepicker({
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

	//para que cargue automaticamente el numero que continua de nota credito	
	$(document).ready(function() {
		//load(1);
		var id_serie = $("#serie_nc_e").val();
		$.post('../ajax/buscar_ultima_nc.php', {
			serie_nc: id_serie
		}).done(function(respuesta) {
			var nc_final = respuesta;
			$("#secuencial_nc_e").val(nc_final);
		});
		document.getElementById('numero_factura').focus();
	});
	//para mostrar la nota de credito que continua segun la serie seleccionada		
	$(function() {
		$('#serie_nc_e').change(function() {
			var id_serie = $("#serie_nc_e").val();
			$.post('../ajax/buscar_ultima_nc.php', {
				serie_nc: id_serie
			}).done(function(respuesta) {
				var nc_final = respuesta;
				$("#secuencial_nc_e").val(nc_final);
			});
		});

	});
	//para pasar el id del cliente y la factura a buscar el detalle y cargarlo en el modal
	function pasa_factura_nc() {
		var factura = $("#numero_factura").val();
		var serie = $("#serie_nc_e").val();
		$.ajax({
			type: "POST",
			url: "../ajax/buscar_detalle_factura_nc.php?action=detalle_factura",
			data: "factura=" + factura + "&serie=" + serie,
			beforeSend: function(objeto) {
				$("#loader_detalle_factura").html("Mensaje: Cargando...");
			},
			success: function(datos) {
				$("#loader_detalle_factura").html(datos);
				var fecha_factura = $("#fecha_factura_consultada").val();
				var id_cliente = $("#id_cliente_factura").val();
				var nombre_cliente = $("#nombre_cliente_factura").val();
				$("#fecha_factura").val(fecha_factura);
				$("#id_cliente").val(id_cliente);
				$("#nombre_cliente").val(nombre_cliente);

			}
		});
	};


	//agregar un iten desde los datos cargados de la factura
	function agregar_item_nc(id) { //viene del boton de agregar en el modal de agregar items nc
		var serie_factura_nc = $("#serie_nc_e").val();
		var numero_factura = $("#numero_factura").val();
		var subtotal = parseFloat(document.getElementById('subtotal_item' + id).value);
		var precio = parseFloat(document.getElementById('precio_' + id).value);
		var cantidad = parseFloat(document.getElementById('cantidad_' + id).value);
		var descuento = parseFloat(document.getElementById('descuento_' + id).value);
		var id_producto = document.getElementById('id_producto' + id).value;
		var total = (subtotal - ((cantidad * precio) - descuento));

		//Inicia validacion
		if (isNaN(cantidad)) {
			alert('El dato ingresado en cantidad, no es un número');
			document.getElementById('cantidad_' + id).focus();
			return false;
		}
		if (isNaN(precio)) {
			alert('El dato ingresado en precio, no es un número');
			document.getElementById('precio_' + id).focus();
			return false;
		}
		if (isNaN(descuento)) {
			alert('El dato ingresado en descuento, no es un número');
			document.getElementById('descuento_' + id).focus();
			return false;
		}

		//Fin validacion
		$.ajax({
			type: "POST",
			url: "../ajax/agregar_items_nc.php",
			data: "id=" + id + "&precio=" + precio + "&cantidad=" + cantidad + "&serie_factura_nc=" + serie_factura_nc + "&numero_factura=" + numero_factura + "&descuento=" + descuento + "&id_producto=" + id_producto,
			beforeSend: function(objeto) {
				$("#resultados_nc").html("Mensaje: Cargando...");
			},
			success: function(datos) {
				$("#resultados_nc").html(datos);
			}
		});

	};

	//agregar itens desde forma manual
	function agregar_item_nc_manual() {
		var serie_factura_nc = $("#serie_nc_e").val();
		var id_agregar = $("#id_agregar").val();
		var producto_agregar = $("#producto_agregar").val();
		var cantidad_agregar = $("#cantidad_agregar").val();
		var precio_agregar = $("#precio_agregar").val();
		var descuento_agregar = $("#descuento_agregar").val();
		var numero_factura = $("#numero_factura").val();

		//Inicia validacion

		if (producto_agregar == '') {
			alert('Ingrese producto.');
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

		if (precio_agregar == '') {
			alert('Ingrese precio.');
			document.getElementById('precio_agregar').focus();
			return false;
		}

		if (isNaN(precio_agregar)) {
			alert('El dato ingresado en precio, no es un número');
			document.getElementById('precio_agregar').focus();
			return false;
		}

		//Fin validacion
		$.ajax({
			type: "POST",
			url: "../ajax/agregar_items_nc.php",
			data: "agregar_item_manual=agregar_item_manual&id_agregar=" + id_agregar + "&producto_agregar=" + producto_agregar + "&cantidad_agregar=" + cantidad_agregar + "&precio_agregar=" + precio_agregar + "&descuento_agregar=" + descuento_agregar + "&serie_factura_nc=" + serie_factura_nc + "&numero_factura=" + numero_factura,
			beforeSend: function(objeto) {
				$("#resultados_nc").html("Mensaje: Cargando...");
			},
			success: function(datos) {
				$("#resultados_nc").html(datos);
				$("#producto_agregar").val("");
				$("#id_agregar").val("");
				$("#precio_agregar").val("");
				$("#cantidad_agregar").val("");
				$("#descuento_agregar").val("0");
				document.getElementById('producto_agregar').focus();
			}
		});

	};

	function eliminar_item_nc(id) {
		var numero_factura = $("#numero_factura").val();
		$.ajax({
			type: "POST",
			url: "../ajax/agregar_items_nc.php",
			data: "id_eliminar=" + id + "&numero_factura=" + numero_factura,
			beforeSend: function(objeto) {
				$("#resultados_nc").html("Mensaje: Cargando...");
			},
			success: function(datos) {
				$("#resultados_nc").html(datos);
			}
		});

	};

	//para guardar la nc electronica
	$("#guardar_nc_e").submit(function(event) {
		$('#guardar_datos_nc_e').attr("disabled", true);
		//para pasar el total de la retencion de un text a otro text
		var total_nc = $("#suma_nc").val();
		$("#total_nc_e").val(total_nc);
		//de aqui para abajo para guardar la factura
		var parametros = $(this).serialize();
		$.ajax({
			type: "POST",
			url: '../ajax/guardar_nc_electronica.php',
			data: parametros,
			beforeSend: function(objeto) {
				$("#mensaje_guardar_nc_electronica").html("Guardando...");
			},
			success: function(datos) {
				$("#resultados_guardar_nc_electronica").html(datos);
				$("#mensaje_guardar_nc_electronica").html("");
				$('#guardar_datos_nc_e').attr("disabled", false);
			}
		});
		event.preventDefault();
	});

	//para buscar productos
	function buscar_productos() {
		$("#producto_agregar").autocomplete({
			source: '../ajax/productos_autocompletar.php',
			minLength: 2,
			select: function(event, ui) {
				event.preventDefault();
				$('#id_agregar').val(ui.item.id);
				$('#producto_agregar').val(ui.item.nombre);
				$('#precio_agregar').val(ui.item.precio);
				document.getElementById('cantidad_agregar').focus();
			}
		});

		$("#producto_agregar").autocomplete("widget").addClass("fixedHeight"); //para que aparezca la barra de desplazamiento en el buscar

		$("#producto_agregar").on("keydown", function(event) {
			if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
				$("#id_agregar").val("");
				$("#producto_agregar").val("");
				$("#precio_agregar").val("");
			}
		});

	}

	function buscar_clientes() {
		$("#nombre_cliente").autocomplete({
			source: '../ajax/clientes_autocompletar.php',
			minLength: 2,
			select: function(event, ui) {
				event.preventDefault();
				$('#id_cliente').val(ui.item.id);
				$('#nombre_cliente').val(ui.item.nombre);
				document.getElementById('producto_agregar').focus();
			}
		});

		$("#nombre_cliente").on("keydown", function(event) {
			if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
				$("#id_cliente").val("");
				$("#nombre_cliente").val("");
			}
			if (event.keyCode == $.ui.keyCode.DELETE) {
				$("#nombre_cliente").val("");
				$("#id_cliente").val("");
			}
		});
	}

	//para cuando cambia en el secuencial de factura y se aplique los ceros a la izquierda
	$(function() {
		$('#numero_factura').change(function() {
			var numero_comprobante = $("#numero_factura").val();
			var serie = numero_comprobante.substr(0, 8);
			var secuencial = numero_comprobante.substr(8, 9);
			while (secuencial.length < 9) {
				var secuencial = '0' + secuencial;
				$("#numero_factura").val(serie + secuencial);
			}
		});
	});
</script>