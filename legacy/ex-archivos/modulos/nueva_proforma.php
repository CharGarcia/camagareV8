<html lang="es">
<meta name="viewport" content="width=device-width, initial-scale=1">

<head>
	<title>Proforma</title>
</head>

<?php

session_start();
if (isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa']) && isset($_SESSION['ruc_empresa'])) {
	$id_usuario = $_SESSION['id_usuario'];
	$id_empresa = $_SESSION['id_empresa'];
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : ''; //para que al momento de guardar la factura regrese al listado de las facturas 

	include("../paginas/menu_de_empresas.php");
	include("../modal/clientes.php");
	//include("../modal/nuevo_cliente_factura.php");
	ini_set('date.timezone', 'America/Guayaquil');
	//para borrar los datos de la factura que este en temporal
	$con = conenta_login();
	if (isset($_SESSION['id_usuario'])) {
		$delete_factura_tmp = mysqli_query($con, "DELETE FROM factura_tmp WHERE id_usuario = '" . $id_usuario . "' and ruc_empresa = '" . $ruc_empresa . "'");
		$delete_adicional_tmp = mysqli_query($con, "DELETE FROM adicional_tmp WHERE id_usuario = '" . $id_usuario . "'");
		//$delete_propina_tasa_tmp = mysqli_query($con, "DELETE FROM propina_tasa_tmp WHERE id_usuario = '".$id_usuario."'");
	}
?>

	<body>
		<?php
		include("../modal/aplicar_descuento.php");
		include("../modal/registro_productos.php");
		?>
		<div class="container">
			<div class="panel panel-info">
				<div class="panel-heading">
					<div class="btn-group pull-right">
						<form class="form-group" id="guardar_proforma" name="guardar_proforma" method="POST">
							<span id="mensaje_guardar_proforma"></span>
							<button id="guardar_datos_proforma" type="submit" class="btn btn-info btn-md"><span class='glyphicon glyphicon-floppy-disk'></span> Guardar</button>
					</div>
					<h4><i class='glyphicon glyphicon-edit'></i> Nueva proforma</h4>
				</div>

				<div class="panel-body">
					<div id="resultados_ajax"></div>
					<div id="resultados_guardar_proforma"></div>
					<div class="well well-sm"><!--style="height:50px;"-->
						<div class="form-group row">
							<div class="col-sm-2">
								<div class="input-group">
									<span class="input-group-addon"><b>Fecha</b></span>
									<input type="text" class="form-control input-sm" name="fecha_proforma" id="fecha_proforma" value="<?php echo date("d-m-Y"); ?>">
								</div>
							</div>
							<div class="col-sm-2">
								<div class="input-group">
									<span class="input-group-addon"><b>Serie</b></span>
									<select class="form-control input-sm" name="serie_proforma" id="serie_proforma">
										<option value="0">Seleccione</option>
										<?php
										$conexion = conenta_login();
										$sql = "SELECT * FROM sucursales where ruc_empresa ='" . $ruc_empresa . "' order by serie desc;";
										$res = mysqli_query($conexion, $sql);
										while ($serie = mysqli_fetch_assoc($res)) {
										?>
											<option value="<?php echo $serie['serie'] ?>" selected><?php echo $serie['serie'] ?></option>
										<?php
										}
										?>
									</select>
								</div>
							</div>
							<div class="col-sm-2">
								<div class="input-group">
									<span class="input-group-addon"><b>No.</b></span>
									<input type="text" class="form-control input-sm text-right" id="secuencial_proforma" name="secuencial_proforma" placeholder="000000001" readonly>
								</div>
							</div>
							<div class="col-sm-6">
								<div class="input-group">
									<span class="input-group-addon"><b>Cliente</b></span>
									<input style="z-index:inherit;" type="text" class="form-control input-sm" id="nombre_cliente_proforma" name="nombre_cliente_proforma" placeholder="Agregue un cliente por ruc o nombre" title="Buscar un cliente." onkeyup='buscar_clientes();' autocomplete="off">
									<span class="input-group-btn btn-md"><button class="btn btn-info btn-md" type="button" title="Nuevo cliente" onclick="carga_modal();" data-toggle="modal" data-target="#nuevoCliente"><span class="glyphicon glyphicon-pencil"></span></button></span>
								</div>
							</div>

							<input type="hidden" id="id_cliente_proforma" name="id_cliente_proforma">
							<input type="hidden" id="tipo_id_cliente" name="tipo_id_cliente">
							<input type="hidden" id="ruc_cliente" name="ruc_cliente">
							<input type="hidden" id="telefono_cliente" name="telefono_cliente">
							<input type="hidden" id="direccion_cliente" name="direccion_cliente">
							<input type="hidden" id="plazo_credito" name="plazo_credito">
							<input type="hidden" id="email_cliente" name="email_cliente">
							<input type="hidden" id="total_proforma" name="total_proforma">
							<input type="hidden" id="muestra_medida" name="muestra_medida">
							<input type="hidden" id="muestra_lote" name="muestra_lote">
							<input type="hidden" id="muestra_bodega" name="muestra_bodega">
							<input type="hidden" id="muestra_vencimiento" name="muestra_vencimiento">
							<input type="hidden" id="propina_final" name="propina_final">
							<input type="hidden" id="tasa_turistica_final" name="tasa_turistica_final">
							<input type="hidden" id="inventario" name="inventario">
							<input type="hidden" id="action" name="action" value="guardar">
							<input type="hidden" id="codigo_unico" name="codigo_unico">
						</div>
					</div>
					<!-- para agregar los productos a la factura -->

					<div class="panel panel-info">
						<div class="table-responsive">
							<table class="table table-bordered">
								<tr class="success">
									<th style="padding: 2px;">Producto/Servicio</th>
									<th style="padding: 2px;" class="text-center" id="titulo_bodega">Bodega</th>
									<th style="padding: 2px;" class="text-center" id="titulo_lote">Lote</th>
									<th style="padding: 2px;" class="text-center" id="titulo_caducidad">Caducidad</th>
									<th style="padding: 2px;" class="text-center">Cantidad</th>
									<th style="padding: 2px;" class="text-center" id="titulo_medida">Medida</th>
									<th style="padding: 2px;" class="text-center">Precio/U.</th>
									<th style="padding: 2px;" class="text-center" id="titulo_existencia">Existencia</th>
									<th style="padding: 2px;" class="text-center">Agregar</th>
								</tr>

								<td class='col-xs-5'>
									<input type="hidden" name="id_producto_agregar" id="id_producto_agregar">
									<input type="hidden" name="tipo_producto_agregar" id="tipo_producto_agregar">
									<input type="hidden" name="precio_tmp" id="precio_tmp">
									<input type="hidden" name="stock_tmp" id="stock_tmp">
									<div class="input-group">
										<input style="z-index:inherit;" type="text" class="form-control input-sm" title="Buscar producto o servicio." name="producto_agregar" id="producto_agregar" placeholder="Ingrese un producto" onkeyup='buscar_productos();' autocomplete="off">
										<span class="input-group-btn btn-md">
											<button class="btn btn-default btn-sm" type="button" title="Boton desabilitado temporalmente">-</button> <!--data-toggle="modal" data-target="#nuevoProducto" <span class="glyphicon glyphicon-pencil"></span>  -->
										</span>
									</div>
								</td>
								<td class='col-xs-1' id="lista_bodega">
									<select class="form-control" style="text-align:right; width: auto; height:30px;" title="Seleccione bodega." name="bodega_agregar" id="bodega_agregar">
										<option value="0">Seleccione</option>
										<?php
										$con = conenta_login();
										$sql_bodega = mysqli_query($con, "SELECT bod.id_bodega as id_bodega, bod.nombre_bodega as nombre_bodega FROM bodega as bod LEFT JOIN bodega_restringida as res ON res.id_bodega = bod.id_bodega and res.id_usuario='" . $id_usuario . "' WHERE res.id_usuario is null and bod.ruc_empresa ='" . $ruc_empresa . "' and bod.status ='1' order by bod.nombre_bodega asc");
										while ($o = mysqli_fetch_array($sql_bodega)) {
										?>
											<option value="<?php echo $o['id_bodega'] ?>" selected><?php echo strtoupper($o['nombre_bodega']) ?></option>
										<?php
										}
										?>
									</select>
								</td>
								<td class='col-xs-1' id="lista_lote">
									<select class="form-control" style="text-align:right; width: auto; height:30px;" title="Seleccione lote." name="lote_agregar" id="lote_agregar">
									</select>
								</td>
								<td class='col-xs-1' id="lista_caducidad">
									<select class="form-control" style="text-align:right; width: auto;" title="Seleccione caducidad." name="caducidad_agregar" id="caducidad_agregar">
									</select>
								</td>
								<td class='col-xs-1'>
									<div class="pull-right">
										<input type="text" class="form-control input-sm" style="text-align:right;" title="Ingrese cantidad" name="cantidad_agregar" id="cantidad_agregar" placeholder="Cantidad">
									</div>
								</td>
								<td class='col-xs-1' id="lista_medida">
									<select class="form-control" style="text-align:right; height:30px;" title="Seleccione medida." name="medida_agregar" id="medida_agregar">
									</select>
								</td>
								<td class='col-xs-1'>
									<input type="text" style="text-align:right;" class="form-control input-sm" id="precio_agregar" name="precio_agregar">
								</td>
								<td class="col-xs-1" id="lista_existencia">
									<input type="text" style="text-align:right;" class="form-control input-sm" id="existencia_producto" name="existencia_producto" placeholder="0">
								</td>
								<td class="col-sm-1" style="text-align:center;">
									<button type="button" class="btn btn-info btn-md" title="Agregar productos a la factura" onclick="agregar_un_item()"><span class="glyphicon glyphicon-plus"></span></button>
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
	<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
	<script src="../js/notify.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>

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
		$("#fecha_proforma").mask("99-99-9999");
	});


	$(function() {
		$("#fecha_proforma").datepicker({
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

	//para buscar los clientes
	function buscar_clientes() {
		$("#nombre_cliente_proforma").autocomplete({
			source: '../ajax/clientes_autocompletar.php',
			minLength: 2,
			select: function(event, ui) {
				event.preventDefault();
				$('#id_cliente_proforma').val(ui.item.id);
				$('#nombre_cliente_proforma').val(ui.item.nombre);
				$('#tipo_id_cliente').val(ui.item.tipo_id);
				$('#ruc_cliente').val(ui.item.ruc);
				$('#telefono_cliente').val(ui.item.telefono);
				$('#direccion_cliente').val(ui.item.direccion);
				$('#plazo_credito').val(ui.item.plazo);
				$('#email_cliente').val(ui.item.email);
				document.getElementById('producto_agregar').focus();
				cambia_cliente();
			}
		});

		$("#nombre_cliente_proforma").on("keydown", function(event) {
			if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
				$("#id_cliente_proforma").val("");
				$("#nombre_cliente_proforma").val("");
				$("#tipo_id_cliente").val("");
				$("#ruc_cliente").val("");
				$("#telefono_cliente").val("");
				$("#direccion_cliente").val("");
				$("#plazo_credito").val("");
				$("#email_cliente").val("");
			}
			if (event.keyCode == $.ui.keyCode.DELETE) {
				$("#nombre_cliente_proforma").val("");
				$("#id_cliente_proforma").val("");
				$("#tipo_id_cliente").val("");
				$("#ruc_cliente").val("");
				$("#telefono_cliente").val("");
				$("#direccion_cliente").val("");
				$("#plazo_credito").val("");
				$("#email_cliente").val("");
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
				$('#id_producto_agregar').val(ui.item.id);
				$('#producto_agregar').val(ui.item.nombre);
				$('#precio_agregar').val(ui.item.precio);
				$('#precio_tmp').val(ui.item.precio);
				$('#tipo_producto_agregar').val(ui.item.tipo);

				var tipo_producto = $("#tipo_producto_agregar").val();
				var configuracion_inventario = document.getElementById('inventario').value;
				var configuracion_medida = document.getElementById('muestra_medida').value;
				var configuracion_lote = document.getElementById('muestra_lote').value;
				var configuracion_bodega = document.getElementById('muestra_bodega').value;
				var configuracion_vencimiento = document.getElementById('muestra_vencimiento').value;
				var producto = $("#id_producto_agregar").val();

				if (tipo_producto == "02") {
					document.getElementById("titulo_bodega").style.display = "none";
					document.getElementById("titulo_lote").style.display = "none";
					document.getElementById("titulo_caducidad").style.display = "none";
					document.getElementById("titulo_medida").style.display = "none";
					document.getElementById("titulo_existencia").style.display = "none";
					document.getElementById("lista_bodega").style.display = "none";
					document.getElementById("lista_lote").style.display = "none";
					document.getElementById("lista_caducidad").style.display = "none";
					document.getElementById("lista_medida").style.display = "none";
					document.getElementById("lista_existencia").style.display = "none";
				}
				if (tipo_producto == "01" && (configuracion_inventario == 'NO' || configuracion_inventario == '')) {
					document.getElementById("titulo_bodega").style.display = "none";
					document.getElementById("titulo_lote").style.display = "none";
					document.getElementById("titulo_caducidad").style.display = "none";
					document.getElementById("titulo_medida").style.display = "";
					document.getElementById("lista_bodega").style.display = "none";
					document.getElementById("lista_lote").style.display = "none";
					document.getElementById("lista_caducidad").style.display = "none";
					document.getElementById("lista_medida").style.display = "";
					//document.getElementById("precio_agregar").disabled = false;

					var producto = $("#id_producto_agregar").val();

					//cuando trae se busca el producto me trae que tipo de medida tiene
					$.post('../ajax/select_tipo_medida.php', {
						id_producto: producto
					}).done(function(res_tipos_medidas) {
						$("#medida_agregar").html(res_tipos_medidas);
					});
				}

				//aqui controla cuando se selecciona producto y trabaja con inventario
				if (tipo_producto == "01" && configuracion_inventario == 'SI') {

					if (configuracion_medida == "SI") {
						document.getElementById("titulo_medida").style.display = "";
						document.getElementById("lista_medida").style.display = "";
					}
					if (configuracion_lote == 'SI') {
						document.getElementById("titulo_lote").style.display = "";
						document.getElementById("lista_lote").style.display = "";
					}
					if (configuracion_bodega == 'SI') {
						document.getElementById("titulo_bodega").style.display = "";
						document.getElementById("lista_bodega").style.display = "";
					}
					if (configuracion_vencimiento == 'SI') {
						document.getElementById("titulo_caducidad").style.display = "";
						document.getElementById("lista_caducidad").style.display = "";
					}

					document.getElementById("precio_agregar").disabled = false;
					document.getElementById("existencia_producto").disabled = true;

					$("#existencia_producto").val("0");
					var bodega = $("#bodega_agregar").val();
					var producto = $("#id_producto_agregar").val();

					//cuando trae se busca el producto me trae que tipo de medida tiene
					$.post('../ajax/select_tipo_medida.php', {
						id_producto: producto
					}).done(function(res_id_medidas) {
						$("#medida_agregar").html(res_id_medidas);
					});
					//

					//para que se cargue el stock del producto al momento de buscar el producto dependiendo de la bodega que esta seleeccionada por default
					$.post('../ajax/saldo_producto_inventario.php', {
						id_bodega: bodega,
						id_producto: producto
					}).done(function(respuesta) {
						var saldo_producto = respuesta;
						$("#existencia_producto").val(saldo_producto);
						$('#stock_tmp').val(saldo_producto);
					});

					//para traer todos los lotes en base a una bodega al momento de buscar un producto
					$.post('../ajax/select_opciones_inventario.php', {
						opcion: 'lote',
						id_producto: producto,
						bodega: bodega
					}).done(function(res_opciones_lote) {
						$("#lote_agregar").html(res_opciones_lote);
					});

					//para traer todos las caducidades en base a una bodega al momento de buscar un producto
					$.post('../ajax/select_opciones_inventario.php', {
						opcion: 'caducidad',
						id_producto: producto,
						bodega: bodega
					}).done(function(res_opciones_caducidad) {
						$("#caducidad_agregar").html(res_opciones_caducidad);
					});

					document.getElementById("titulo_existencia").style.display = "";
					document.getElementById("lista_existencia").style.display = "";

				}
				//hasta aqui me controla si trabaja con inventario
				document.getElementById('cantidad_agregar').focus();
			}
		});

		$("#producto_agregar").autocomplete("widget").addClass("fixedHeight"); //para que aparezca la barra de desplazamiento en el buscar

		$("#producto_agregar").on("keydown", function(event) {
			if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
				$("#id_producto_agregar").val("");
				$("#producto_agregar").val("");
				$("#precio_agregar").val("");
				$("#tipo_producto_agregar").val("");
				$("#existencia_producto").val("");
				$("#medida_agregar").val("");
				$("#stock_tmp").val("");
			}
		});
	}


	$(function() {
		//para cuando se cambia el select de bodega que me cargue el saldo de ese producto
		$('#bodega_agregar').change(function() {
			var bodega = $("#bodega_agregar").val();
			var producto = $("#id_producto_agregar").val();
			var id_medida = $("#medida_agregar").val();

			//reinicia la medida
			$.post('../ajax/select_tipo_medida.php', {
				id_producto: producto
			}).done(function(res_id_medidas) {
				$("#medida_agregar").html(res_id_medidas);
			});
			//reinicie el precio
			var precio_venta = $("#precio_tmp").val();
			$("#precio_agregar").val(precio_venta);

			//trae la existencia en base a la bodega
			$.post('../ajax/saldo_producto_inventario.php', {
				id_bodega: bodega,
				id_producto: producto
			}).done(function(respuesta) {
				var saldo_producto = respuesta;
				$("#existencia_producto").val(saldo_producto);
				$('#stock_tmp').val(saldo_producto);
			});

			//reinicio el lote
			$.post('../ajax/select_opciones_inventario.php', {
				opcion: 'lote',
				id_producto: producto,
				bodega: bodega
			}).done(function(res_opciones_lote) {
				$("#lote_agregar").html(res_opciones_lote);
			});

			//para reinicie vencimiento
			$.post('../ajax/select_opciones_inventario.php', {
				opcion: 'caducidad',
				id_producto: producto,
				bodega: bodega
			}).done(function(res_opciones_caducidad) {
				$("#caducidad_agregar").html(res_opciones_caducidad);
			});
		});

		//para traer el valor de conversion de medidas en el producto cuando se cambia el select de medida
		$('#medida_agregar').change(function() {
			var id_medida = $("#medida_agregar").val();
			var id_producto = $("#id_producto_agregar").val();
			var precio_venta = $("#precio_tmp").val(); //este precio es para que no se me cambie del precio que calculo cada vez que cambio el selec de medida
			var stock_tmp = $("#stock_tmp").val();

			$.post('../ajax/saldo_producto_inventario.php', {
				id_medida_seleccionada: id_medida,
				id_producto: id_producto,
				precio_venta: precio_venta,
				stock_tmp: stock_tmp,
				dato_obtener: 'saldo'
			}).done(function(respuesta_saldo) {
				$("#existencia_producto").val(respuesta_saldo);
			});

			$.post('../ajax/saldo_producto_inventario.php', {
				id_medida_seleccionada: id_medida,
				id_producto: id_producto,
				precio_venta: precio_venta,
				stock_tmp: stock_tmp,
				dato_obtener: 'precio'
			}).done(function(respuesta_precio) {
				$("#precio_agregar").val(respuesta_precio);
			});

		});

		//para traer el valor de conversion de medidas en el producto cuando se cambia el select de lote
		$('#lote_agregar').change(function() {
			var lote = $("#lote_agregar").val();
			var producto = $("#id_producto_agregar").val();
			var bodega = $("#bodega_agregar").val();
			$.post('../ajax/saldo_producto_inventario.php', {
				opcion_lote: lote,
				id_producto: producto,
				bodega: bodega
			}).done(function(respuesta_lote) {
				$("#existencia_producto").val(respuesta_lote);
			});

			//reinicia la medida
			$.post('../ajax/select_tipo_medida.php', {
				id_producto: producto
			}).done(function(res_id_medidas) {
				$("#medida_agregar").html(res_id_medidas);
			});
			//reinicie el precio
			var precio_venta = $("#precio_tmp").val();
			$("#precio_agregar").val(precio_venta);

			//para reinicie vencimiento
			$.post('../ajax/select_opciones_inventario.php', {
				opcion: 'caducidad',
				id_producto: producto,
				bodega: bodega
			}).done(function(res_opciones_caducidad) {
				$("#caducidad_agregar").html(res_opciones_caducidad);
			});

		});

		//para traer el valor de conversion de medidas en el producto cuando se cambia el select de caducidad
		$('#caducidad_agregar').change(function() {
			var caducidad = $("#caducidad_agregar").val();
			var producto = $("#id_producto_agregar").val();

			$.post('../ajax/saldo_producto_inventario.php', {
				opcion_caducidad: caducidad,
				id_producto: producto
			}).done(function(respuesta_caducidad) {
				$("#existencia_producto").val(respuesta_caducidad);
			});

			//reinicia la medida
			$.post('../ajax/select_tipo_medida.php', {
				id_producto: producto
			}).done(function(res_id_medidas) {
				$("#medida_agregar").html(res_id_medidas);
			});
			//reinicie el precio
			var precio_venta = $("#precio_tmp").val();
			$("#precio_agregar").val(precio_venta);

		});

	});

	//para mostrar cambios cuando se selecciona una serie didferente
	$(function() {
		$('#serie_proforma').change(function() {
			var id_serie = $("#serie_proforma").val();
			$.post('../ajax/buscar_ultima_proforma.php', {
				siguiente_proforma: 'siguiente_proforma',
				serie: id_serie
			}).done(function(respuesta) {
				$("#secuencial_proforma").val(respuesta);
			});

			//limpia todos los campos
			$("#inventario").val("");
			$("#muestra_medida").val("");
			$("#muestra_lote").val("");
			$("#muestra_bodega").val("");
			$("#muestra_vencimiento").val("");

			//para traer el tipo de configuracion de inventarios, si o no
			$.post('../ajax/consulta_configuracion_proformas.php', {
				opcion_mostrar: 'inventario',
				serie_consultada: id_serie
			}).done(function(respuesta_inventario) {
				var resultado_inventario = $.trim(respuesta_inventario);
				$('#inventario').val(resultado_inventario);
			});

			//para traer y ver si trabaja con medida
			$.post('../ajax/consulta_configuracion_proformas.php', {
				opcion_mostrar: 'medida',
				serie_consultada: id_serie
			}).done(function(respuesta_medida) {
				var resultado_medida = $.trim(respuesta_medida);
				$('#muestra_medida').val(resultado_medida);
			});

			//para traer y ver si trabaja con lote
			$.post('../ajax/consulta_configuracion_proformas.php', {
				opcion_mostrar: 'lote',
				serie_consultada: id_serie
			}).done(function(respuesta_lote) {
				var resultado_lote = $.trim(respuesta_lote);
				$('#muestra_lote').val(resultado_lote);
			});

			//para traer y ver si trabaja con bodega
			$.post('../ajax/consulta_configuracion_proformas.php', {
				opcion_mostrar: 'bodega',
				serie_consultada: id_serie
			}).done(function(respuesta_bodega) {
				var resultado_bodega = $.trim(respuesta_bodega);
				$('#muestra_bodega').val(resultado_bodega);
			});

			//para traer y ver si trabaja con vencimiento
			$.post('../ajax/consulta_configuracion_proformas.php', {
				opcion_mostrar: 'vencimiento',
				serie_consultada: id_serie
			}).done(function(respuesta_vencimiento) {
				var resultado_vencimiento = $.trim(respuesta_vencimiento);
				$('#muestra_vencimiento').val(resultado_vencimiento);
			});


		});
	});

	//para cargar automaticamente el numero de factura que sigue al momento de cargar la nueva factura
	$(document).ready(function() {
		document.getElementById("titulo_bodega").style.display = "none";
		document.getElementById("titulo_lote").style.display = "none";
		document.getElementById("titulo_caducidad").style.display = "none";
		document.getElementById("titulo_medida").style.display = "none";
		document.getElementById("titulo_existencia").style.display = "none";
		document.getElementById("lista_bodega").style.display = "none";
		document.getElementById("lista_lote").style.display = "none";
		document.getElementById("lista_caducidad").style.display = "none";
		document.getElementById("lista_medida").style.display = "none";
		document.getElementById("lista_existencia").style.display = "none";
		var serie_proforma = $("#serie_proforma").val();

		$.post('../ajax/buscar_ultima_proforma.php', {
			siguiente_proforma: 'siguiente_proforma',
			serie: serie_proforma
		}).done(function(respuesta) {
			$("#secuencial_proforma").val(respuesta);
		});

		var id_serie = $("#serie_proforma").val();
		//para traer el tipo de configuracion de inventarios, si o no
		$.post('../ajax/consulta_configuracion_proformas.php', {
			opcion_mostrar: 'inventario',
			serie_consultada: id_serie
		}).done(function(respuesta_inventario) {
			var resultado_inventario = $.trim(respuesta_inventario);
			$('#inventario').val(resultado_inventario);
		});

		//para traer y ver si trabaja con medida
		$.post('../ajax/consulta_configuracion_proformas.php', {
			opcion_mostrar: 'medida',
			serie_consultada: id_serie
		}).done(function(respuesta_medida) {
			var resultado_medida = $.trim(respuesta_medida);
			$('#muestra_medida').val(resultado_medida);
		});

		//para traer y ver si trabaja con lote
		$.post('../ajax/consulta_configuracion_proformas.php', {
			opcion_mostrar: 'lote',
			serie_consultada: id_serie
		}).done(function(respuesta_lote) {
			var resultado_lote = $.trim(respuesta_lote);
			$('#muestra_lote').val(resultado_lote);
		});

		//para traer y ver si trabaja con bodega
		$.post('../ajax/consulta_configuracion_proformas.php', {
			opcion_mostrar: 'bodega',
			serie_consultada: id_serie
		}).done(function(respuesta_bodega) {
			var resultado_bodega = $.trim(respuesta_bodega);
			$('#muestra_bodega').val(resultado_bodega);
		});

		//para traer y ver si trabaja con vencimiento
		$.post('../ajax/consulta_configuracion_proformas.php', {
			opcion_mostrar: 'vencimiento',
			serie_consultada: id_serie
		}).done(function(respuesta_vencimiento) {
			var resultado_vencimiento = $.trim(respuesta_vencimiento);
			$('#muestra_vencimiento').val(resultado_vencimiento);
		});

		document.getElementById('nombre_cliente_proforma').focus();

	});


	//eliminar iten de la factura
	function eliminar_fila(id) {
		var id_cliente = $("#id_cliente_proforma").val();
		var serie_proforma = $("#serie_proforma").val();
		var secuencial_proforma = $("#secuencial_proforma").val();
		$.ajax({
			type: "POST",
			url: "../ajax/agregar_facturacion.php",
			data: "id=" + id + "&id_cliente=" + id_cliente + "&serie_factura_e=" + serie_proforma + "&secuencial_factura=" + secuencial_proforma,
			beforeSend: function(objeto) {
				$("#resultados").html("Mensaje: Cargando...");
			},
			success: function(datos) {
				$("#resultados").html(datos);
			}
		});
	};

	//para guardar la proforma
	$(function() {
		$("#guardar_proforma").submit(function(event) {
			$('#guardar_datos_proforma').attr("disabled", true);
			//para pasar el total de la factura de un text a otro text
			var total_proforma = $("#suma_factura").val();
			$("#total_proforma").val(total_proforma);
			//de aqui para abajo para guardar la factura
			var parametros = $(this).serialize();
			$.ajax({
				type: "POST",
				url: '../ajax/guardar_proforma.php',
				data: parametros,
				beforeSend: function(objeto) {
					$("#mensaje_guardar_proforma").html("Guardando...");
				},
				success: function(datos) {
					$("#resultados_guardar_proforma").html(datos);
					$("#mensaje_guardar_proforma").html("");
					$('#guardar_datos_proforma').attr("disabled", false);
				}
			});
			event.preventDefault();
		});
	});

	function aplicar_descuento_directo(id) {
		var id_cliente = $("#id_cliente_proforma").val();
		var serie_proforma = $("#serie_proforma").val();
		var secuencial_proforma = $("#secuencial_proforma").val();
		var subtotal_item = $("#subtotal_item" + id).val();
		var valor_descuento = $("#descuento" + id).val();
		var descuento_item = $("#descuento_item" + id).val();


		if (isNaN(valor_descuento)) {
			alert('El dato ingresado en descuento, no es un número');
			document.getElementById('descuento' + id).focus();
			$("#descuento" + id).val(descuento_item);
			return false;
		}

		if ((valor_descuento < 0)) {
			alert('El descuento, debe ser mayor a cero');
			$("#descuento" + id).val(descuento_item);
			document.getElementById('descuento' + id).focus();
			return false;
		}

		if ((parseFloat(valor_descuento) > parseFloat(subtotal_item))) {
			alert('El descuento es mayor al subtotal del item');
			$("#descuento" + id).val(descuento_item);
			document.getElementById('descuento' + id).focus();
			return false;
		}

		$.ajax({
			type: "POST",
			url: "../ajax/agregar_facturacion.php",
			data: "id_tmp_descuento=" + id + "&valor_descuento=" + valor_descuento + "&id_cliente=" + id_cliente + "&serie_factura_e=" + serie_proforma + "&secuencial_factura=" + secuencial_proforma + "&subtotal_sin_impuesto=" + subtotal_item,
			beforeSend: function(objeto) {
				$("#resultados").html("Cargando...");
			},
			success: function(datos) {
				$("#resultados").html(datos);
			}
		});
	}

	function aplicar_descuento_item() {
		var descuento_item = document.getElementById('valor_descuento').value;
		var id = document.getElementById('id_tmp_descuento').value;
		var id_cliente = $("#id_cliente_proforma").val();
		var serie_proforma = $("#serie_proforma").val();
		var secuencial_proforma = $("#secuencial_proforma").val();
		var subtotal_sin_impuesto = $("#subtotal_inicial").val();

		$.ajax({
			type: "POST",
			url: "../ajax/agregar_facturacion.php",
			data: "id_tmp_descuento=" + id + "&valor_descuento=" + descuento_item + "&id_cliente=" + id_cliente + "&serie_factura_e=" + serie_proforma + "&secuencial_factura=" + secuencial_proforma + "&subtotal_sin_impuesto=" + subtotal_sin_impuesto,
			beforeSend: function(objeto) {
				$("#resultados").html("Cargando...");
			},
			success: function(datos) {
				$("#resultados").html(datos);
			}
		});

	}


	function aplicar_descuento_todos() {
		var porcentaje_descuento = $("#porcentaje_descuento").val();
		var id_cliente = $("#id_cliente_proforma").val();
		var serie_proforma = $("#serie_proforma").val();
		var secuencial_proforma = $("#secuencial_proforma").val();

		if (isNaN(porcentaje_descuento)) {
			alert('El dato ingresado en porcentaje, no es un número');
			document.getElementById('porcentaje_descuento').focus();
			return false;
		}

		if ((porcentaje_descuento < 0)) {
			alert('El porcentaje, debe ser mayor a cero');
			document.getElementById('porcentaje_descuento').focus();
			return false;
		}

		$.ajax({
			type: "POST",
			url: "../ajax/agregar_facturacion.php",
			data: "aplicar_descuento_todos=aplicar_descuento_todos&id_cliente=" + id_cliente + "&serie_factura_e=" + serie_proforma + "&secuencial_factura=" + secuencial_proforma + "&porcentaje_descuento=" + porcentaje_descuento,
			beforeSend: function(objeto) {
				$("#resultados").html("Cargando...");
			},
			success: function(datos) {
				$("#resultados").html(datos);
			}
		});
	}

	//para pasar el id de descuento al modal de aplicar descuento
	function pasa_descuento(id_tmp, subtotal_inicial, serie_proforma, secuencial_proforma, id_cliente, descuento, con_impuesto, sin_impuesto, tarifa) {
		$("#id_tmp_descuento").val(id_tmp);
		$("#serie_proforma_descuento").val(serie_proforma);
		$("#secuencial_proforma_descuento").val(secuencial_proforma);
		$("#id_cliente_descuento").val(id_cliente);
		$("#valor_descuento").val(descuento);
		$("#subtotal_inicial").val(subtotal_inicial);
		$("#subtotal_sin_impuesto").val(sin_impuesto);
		$("#total_pagar").val(con_impuesto);
		$("#subtotal_con_impuesto").val(con_impuesto);
		$("#tarifa").val(tarifa);
		$("#porcentaje_descuento").val(Number.parseFloat(descuento / subtotal_inicial * 100).toFixed(2));

	}

	//REGISTRA EL DESCUENTO
	$(function() {
		$("#guardar_descuento").submit(function(event) { //viene del boton aplicar descuento
			$('#aplicar_descuento').attr("disabled", true);
			var parametros = $(this).serialize();

			var valor_descuento = $("#valor_descuento").val();
			var valor_subtotal = $("#valor_subtotal").val();
			//Inicia validacion
			if (isNaN(valor_descuento)) {
				alert('El dato ingresado, no es un número');
				document.getElementById('valor_descuento').focus();
				$('#aplicar_descuento').attr("disabled", false);
				return false;
			}
			if ((Number(valor_descuento)) > (Number(valor_subtotal))) {
				alert('El descuento ingresado, es mayor que el subtotal');
				document.getElementById('valor_descuento').focus();
				$('#aplicar_descuento').attr("disabled", false);
				return false;
			}
			if ((Number(valor_descuento)) < 0) {
				alert('El descuento ingresado, debe ser mayor o igual a cero');
				document.getElementById('valor_descuento').focus();
				$('#aplicar_descuento').attr("disabled", false);
				return false;
			}
			//Fin validacion
			$.ajax({
				type: "POST",
				url: "../ajax/agregar_facturacion.php",
				data: parametros,
				beforeSend: function(objeto) {
					$("#resultados").html("Cargando...");
				},
				success: function(datos) {
					$("#resultados").html(datos);
					$('#aplicar_descuento').attr("disabled", false);
				}
			});
			event.preventDefault();
		});
	});

	//agrega un item al cuerpo de la factura
	function agregar_un_item() {
		var id_cliente_proforma = $("#id_cliente_proforma").val();
		var serie_proforma = $("#serie_proforma").val();
		var secuencial_proforma = $("#secuencial_proforma").val();
		var id_producto_agregar = $("#id_producto_agregar").val();
		var tipo_producto_agregar = $("#tipo_producto_agregar").val();
		var cantidad = document.getElementById('cantidad_agregar').value;
		var medida_agregar = document.getElementById('medida_agregar').value;
		var lote_agregar = document.getElementById('lote_agregar').value;
		var caducidad_agregar = document.getElementById('caducidad_agregar').value;
		var precio_venta = document.getElementById('precio_agregar').value;
		var bodega_agregar = document.getElementById('bodega_agregar').value;
		var existencia_producto = document.getElementById('existencia_producto').value;
		var configuracion_inventario = document.getElementById('inventario').value;

		var control_bodega = document.getElementById('muestra_bodega').value;
		var control_lote = document.getElementById('muestra_lote').value;
		var control_caducidad = document.getElementById('muestra_vencimiento').value;

		//Inicia validacion
		if (serie_proforma == "0") {
			alert('Seleccione una sucursal');
			document.getElementById('serie_proforma').focus();
			return false;
		}
		if (id_cliente_proforma == "") {
			alert('Seleccione un cliente');
			document.getElementById('id_cliente_proforma').focus();
			return false;
		}
		if (id_producto_agregar == "") {
			alert('Seleccione un producto o servicio');
			document.getElementById('id_producto_agregar').focus();
			return false;
		}
		if (cantidad == "") {
			alert('Ingrese cantidad');
			document.getElementById('cantidad_agregar').focus();
			return false;
		}
		if (isNaN(cantidad)) {
			alert('El dato ingresado en cantidad, no es un número');
			document.getElementById('cantidad_agregar').focus();
			return false;
		}
		if (isNaN(precio_venta)) {
			alert('El dato ingresado en precio, no es un número');
			document.getElementById('precio_agregar').focus();
			return false;
		}
		if (configuracion_inventario == 'SI' && tipo_producto_agregar == '01' && control_bodega == 'SI' && bodega_agregar == '0') {
			alert('Seleccione una bodega');
			document.getElementById('bodega_agregar').focus();
			return false;
		}

		if (configuracion_inventario == 'SI' && tipo_producto_agregar == '01' && control_lote == 'SI' && lote_agregar == '0') {
			alert('Seleccione un lote');
			document.getElementById('lote_agregar').focus();
			return false;
		}

		if (configuracion_inventario == 'SI' && tipo_producto_agregar == '01' && control_caducidad == 'SI' && caducidad_agregar == '0') {
			alert('Seleccione fecha de vencimiento');
			document.getElementById('caducidad_agregar').focus();
			return false;
		}

		//Fin validacion
		$.ajax({
			type: "POST",
			url: "../ajax/agregar_facturacion.php",
			data: "id_producto=" + id_producto_agregar + "&precio_venta=" + precio_venta + "&cantidad=" + cantidad + "&serie_factura_e=" + serie_proforma + "&bodega_agregar=" + bodega_agregar + "&id_cliente=" + id_cliente_proforma + "&secuencial_factura=" + secuencial_proforma + "&medida_agregar=" + medida_agregar + "&lote_agregar=" + lote_agregar + "&caducidad_agregar=" + caducidad_agregar,
			beforeSend: function(objeto) {
				$("#resultados").html("Cargando...");
			},
			success: function(datos) {
				$("#resultados").html(datos);
				$("#producto_agregar").val("");
				$("#id_producto_agregar").val("");
				$("#precio_agregar").val("");
				$("#tipo_producto_agregar").val("");
				$("#existencia_producto").val("0");
				$("#cantidad_agregar").val("1");
				document.getElementById('producto_agregar').focus();
			}
		});

	};


	//para agregar informacion adicional a la factura
	function agregar_info_adicional() {
		//var $elem = $('#content');
		var id_cliente = $("#id_cliente_proforma").val();
		var serie_proforma = $("#serie_proforma").val();
		var secuencial_proforma = $("#secuencial_proforma").val();
		var adicional_concepto = $("#adicional_concepto").val();
		var adicional_descripcion = $("#adicional_descripcion").val();

		//Inicia validacion
		if (adicional_concepto == '') {
			alert('Ingrese un concepto');
			document.getElementById('adicional_concepto').focus();
			return false;
		}
		if (adicional_descripcion == '') {
			alert('Ingrese detalle');
			document.getElementById('adicional_descripcion').focus();
			return false;
		}

		//Fin validacion
		$.ajax({
			type: "POST",
			url: "../ajax/agregar_facturacion.php",
			data: "agregar_adicional=agregar_adicional&serie_factura_e=" + serie_proforma + "&secuencial_factura=" + secuencial_proforma + "&adicional_concepto=" + adicional_concepto + "&adicional_descripcion=" + adicional_descripcion + "&id_cliente=" + id_cliente,
			beforeSend: function(objeto) {
				$("#resultados").html("Cargando...");
			},
			success: function(datos) {
				$("#resultados").html(datos);
				document.getElementById("adicional_concepto").value = "";
				document.getElementById("adicional_descripcion").value = "";
			}
		});
		//$('body').animate({scrollTop: $elem.height()}, 500);
	};

	//pasa eliminar cada detalle adicional de la factura a guardarse
	function eliminar_detalle_info_adicional(id_info_adicional) {
		//var $elem = $('#content');
		var id_cliente = $("#id_cliente_adicional").val();
		var serie_proforma = $("#serie_adicional").val();
		var secuencial_proforma = $("#secuencial_adicional").val();

		$.ajax({
			type: "POST",
			url: "../ajax/agregar_facturacion.php",
			data: "id_adicional=" + id_info_adicional + "&serie_factura_e=" + serie_proforma + "&secuencial_factura=" + secuencial_proforma + "&id_cliente=" + id_cliente,
			beforeSend: function(objeto) {
				$("#resultados").html("Cargando detalle...");
			},
			success: function(datos) {
				$("#resultados").html(datos);
			}
		});
	}


	//para guardar el producto directamente desde la factura
	$("#guardar_producto").submit(function(event) {
		$('#guardar_datos').attr("disabled", true);
		var parametros = $(this).serialize();
		$.ajax({
			type: "POST",
			url: "../ajax/nuevo_producto.php",
			data: parametros,
			beforeSend: function(objeto) {
				$("#resultados_ajax_productos").html("Mensaje: Cargando...");
			},
			success: function(datos) {
				$("#resultados_ajax_productos").html(datos);
				$('#guardar_datos').attr("disabled", false);
			}
		});
		event.preventDefault();
	})

	//para que cuando se cierre el modal de nuevo producto
	$("#cerrar_nuevo_producto").click(function() {
		$("#resultados_ajax_productos").empty();
		document.getElementById("guardar_producto").reset();
	});



	//para pasar los datos del cliente y editar directamente
	/*
	function pasar_datos_cliente(){
			var id_cliente_proforma = $("#id_cliente_proforma").val();
			var nombre_cliente_proforma = $("#nombre_cliente_proforma").val();
			var tipo_id_cliente = $("#tipo_id_cliente").val();
			var ruc_cliente = $("#ruc_cliente").val();
			var telefono_cliente = $("#telefono_cliente").val();
			var direccion_cliente = $("#direccion_cliente").val();
			var plazo_credito = $("#plazo_credito").val();
			var email_cliente = $("#email_cliente").val();
			$("#id_cliente_factura").val(id_cliente_proforma);
			$("#tipo_id_cliente_modi").val(tipo_id_cliente);
			$("#ruc_cliente_directo").val(ruc_cliente);
			$("#nombre_cliente_directo").val(nombre_cliente_proforma);
			$("#telefono_cliente_directo").val(telefono_cliente);
			$("#direccion_cliente_directo").val(direccion_cliente);
			$("#plazo_cliente_directo").val(plazo_credito);
			$("#email_cliente_directo").val(email_cliente);
			document.getElementById("ruc_cliente_directo").readOnly = true;
		}
		*/
	function carga_modal() {
		document.querySelector("#titleModalCliente").innerHTML = "<i class='glyphicon glyphicon-ok'></i> Nuevo Cliente";
		document.querySelector("#guardar_cliente").reset();
		document.querySelector("#id_cliente").value = "";
		document.querySelector("#btnActionFormCliente").classList.replace("btn-info", "btn-primary");
		document.querySelector("#btnTextCliente").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Guardar";
		document.querySelector('#btnActionFormCliente').title = "Guardar pedido";
	}

	$("#guardar_cliente").submit(function(event) {
		$('#guardar_datos').attr("disabled", true);
		var parametros = $(this).serialize();
		$.ajax({
			type: "POST",
			url: "../ajax/clientes.php?action=guardar_cliente",
			data: parametros,
			beforeSend: function(objeto) {
				$("#resultados_ajax").html("Guardando...");
			},
			success: function(datos) {
				$("#resultados_ajax").html(datos);
				$('#guardar_datos').attr("disabled", false);
			}
		});
		event.preventDefault();
	})

	//borrar datos del formulario de nuevo cliente
	$("#borrar_datos").click(function() {
		$("#id_cliente_factura").val("");
		$("#ruc_cliente_directo").val("");
		$("#nombre_cliente_directo").val("");
		$("#telefono_cliente_directo").val("");
		$("#direccion_cliente_directo").val("");
		$("#plazo_cliente_directo").val("5");
		$("#email_cliente_directo").val("");
		$("#id_cliente_proforma").val("");
		$("#nombre_cliente_proforma").val("");
		document.getElementById("ruc_cliente_directo").readOnly = false;
	});


	//al cambiar de cliente debe actualizarse los datos del mismo en adicionales
	function cambia_cliente() {
		//var $elem = $('#content');
		var id_cliente = $("#id_cliente_proforma").val();
		var serie_proforma = $("#serie_proforma").val();
		var secuencial_proforma = $("#secuencial_proforma").val();

		//Fin validacion
		$.ajax({
			type: "POST",
			url: "../ajax/agregar_facturacion.php",
			data: "cambia_cliente=cambia_cliente&serie_factura_e=" + serie_proforma + "&secuencial_factura=" + secuencial_proforma + "&id_cliente=" + id_cliente,
			beforeSend: function(objeto) {
				$("#resultados").html("Cargando...");
			},
			success: function(datos) {
				$("#resultados").html(datos);
				document.getElementById("adicional_concepto").value = "";
				document.getElementById("adicional_descripcion").value = "";
			}
		});
		//$('body').animate({scrollTop: $elem.height()}, 500);
	};
</script>