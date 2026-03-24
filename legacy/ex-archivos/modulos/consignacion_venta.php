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
		<title>Consignaciones ventas</title>

		<?php
		include("../paginas/menu_de_empresas.php");
		//include("../modal/detalle_consignaciones.php");
		?>
		<style type="text/css">
			ul.ui-autocomplete {
				z-index: 1100;
			}
		</style>

	</head>

	<body>

		<div class="container-fluid">
			<div class="panel panel-info">
				<div class="panel-heading">
					<div class="btn-group pull-right">
						<button type='submit' class="btn btn-info" data-toggle="modal" data-target="#nueva_consignacion_venta" onclick="carga_modal();"><span class="glyphicon glyphicon-plus"></span> Nueva consignación</button>
					</div>
					<h4><i class='glyphicon glyphicon-search'></i> Consignaciones en ventas</h4>
				</div>
				<div class="panel-body">
					<?php
					include("../modal/consignacion_venta.php");
					?>
					<form class="form-horizontal" method="POST">
						<div class="form-group row">
							<div class="col-md-5">
								<input type="hidden" id="ordenado" value="enc_con.numero_consignacion">
								<input type="hidden" id="por" value="desc">
								<div class="input-group">
									<span class="input-group-addon"><b>Buscar:</b></span>
									<input type="text" class="form-control input-sm" id="q" placeholder="Cliente, Número, Observaciones" onkeyup='load(1);'>
									<span class="input-group-btn">
										<button type="button" class="btn btn-default input-sm" onclick='load(1);'><span class="glyphicon glyphicon-search"></span> Buscar</button>
									</span>
								</div>
							</div>
							<span id="loader"></span>
						</div>
					</form>
					<div id="resultados"></div><!-- Carga los datos ajax -->
					<div class="outer_div"></div><!-- Carga los datos ajax -->
				</div>
			</div>

		</div>
	<?php

} else {
	header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/sistema') . '/empresa');
	exit;
}
	?>
	<!--para que se vea con fondo blanco el autocomplete -->
	<!-- 	<link rel="stylesheet" href="../css/jquery-ui.css"> 
	<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script> -->

	<link rel="stylesheet" href="../css/jquery-ui.css">
	<script src="../js/jquery-ui.js"></script>
	<script src="../js/notify.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
	<script src="../js/ordenado.js" type="text/javascript"></script>
	</body>

	</html>
	<script>
		$(document).ready(function() {
			load(1);
		});

		$('#fecha_consignacion_salida').css('z-index', 1500);
		$('#fecha_pedido').css('z-index', 1500);

		jQuery(function($) {
			$("#fecha_consignacion_salida").mask("99-99-9999");
			$("#fecha_pedido").mask("99-99-9999");
			$("#hora_entrega_desde").mask("99:99");
			$("#hora_entrega_hasta").mask("99:99");
		});

		$(function() {
			$("#fecha_consignacion_salida").datepicker({
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

			$("#fecha_pedido").datepicker({
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

		function load(page) {
			var q = $("#q").val();
			var ordenado = $("#ordenado").val();
			var por = $("#por").val();
			$("#loader").fadeIn('slow');
			$.ajax({
				url: '../ajax/consignacion_venta.php?action=buscar_consignacion_venta&page=' + page + '&q=' + q + "&ordenado=" + ordenado + "&por=" + por,
				beforeSend: function(objeto) {
					$('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
				},
				success: function(data) {
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');
				}
			})
		}

		function carga_modal_pedidos() {
			document.querySelector("#detalle_pedido").reset();
			$(".outer_divdetpedido").html('').fadeIn('fast');
			$('#numero_pedido').focus();
		}

		//cada vez que se selecciona la bodega se inicia el cursor en pedido
		$('#bodega_pedido').change(function() {
			mostrar_detalle_pedido();
			$('#numero_pedido').focus();
		});

		//para buscar los clientes
		function buscar_clientes() {
			$("#cliente_consignacion_venta").autocomplete({
				source: '../ajax/clientes_autocompletar.php',
				minLength: 2,
				select: function(event, ui) {
					event.preventDefault();
					$('#id_cliente_consignacion_venta').val(ui.item.id);
					$('#cliente_consignacion_venta').val(ui.item.nombre);
					$('#responsable_traslado').val(ui.item.id_vendedor);
					document.getElementById('observacion_consignacion_venta').focus();
				}
			});

			$("#cliente_consignacion_venta").on("keydown", function(event) {
				if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_cliente_consignacion_venta").val("");
					$("#cliente_consignacion_venta").val("");
				}
				if (event.keyCode == $.ui.keyCode.DELETE) {
					$("#cliente_consignacion_venta").val("");
					$("#id_cliente_consignacion_venta").val("");
				}
			});
		}

		//para cargar el modal
		function carga_modal() {
			resetea_datos();
			document.getElementById("titulo_lote").style.display = "none";
			document.getElementById("titulo_caducidad").style.display = "none";
			document.getElementById("titulo_existencia").style.display = "none";
			document.getElementById("lista_lote").style.display = "none";
			document.getElementById("lista_caducidad").style.display = "none";
			document.getElementById("lista_existencia").style.display = "none";
			//para traer el tipo de configuracion de inventarios, si o no
			var serie_consignacion = $("#serie_consignacion").val();
			$("#codigo_unico").val(''); //para borrar la info del input

			$.post('../ajax/consulta_configuracion_facturacion.php', {
				opcion_mostrar: 'inventario',
				serie_consultada: serie_consignacion
			}).done(function(respuesta_inventario) {
				var resultado_inventario = $.trim(respuesta_inventario);
				$('#inventario').val(resultado_inventario);
			});


			//para traer y ver si trabaja con lote
			$.post('../ajax/consulta_configuracion_facturacion.php', {
				opcion_mostrar: 'lote',
				serie_consultada: serie_consignacion
			}).done(function(respuesta_lote) {
				var resultado_lote = $.trim(respuesta_lote);
				$('#muestra_lote').val(resultado_lote);
			});

			//para traer y ver si trabaja con bodega
			$.post('../ajax/consulta_configuracion_facturacion.php', {
				opcion_mostrar: 'bodega',
				serie_consultada: serie_consignacion
			}).done(function(respuesta_bodega) {
				var resultado_bodega = $.trim(respuesta_bodega);
				$('#muestra_bodega').val(resultado_bodega);
			});

			//para traer y ver si trabaja con vencimiento
			$.post('../ajax/consulta_configuracion_facturacion.php', {
				opcion_mostrar: 'vencimiento',
				serie_consultada: serie_consignacion
			}).done(function(respuesta_vencimiento) {
				var resultado_vencimiento = $.trim(respuesta_vencimiento);
				$('#muestra_vencimiento').val(resultado_vencimiento);
			});

			//document.getElementById('cliente_consignacion_venta').focus();	
		}


		function eliminar_detalle_consignacion(id) {
			$.ajax({
				url: "../ajax/detalle_consignaciones.php?action=eliminar_item&id_registro=" + id,
				beforeSend: function(objeto) {
					$("#muestra_detalle_consignacion").html("Eliminando...");
				},
				success: function(data) {
					$(".outer_divdet_consignacion").html(data).fadeIn('fast');
					$('#muestra_detalle_consignacion').html('');
				}
			});
		}


		function resetea_datos() {
			$("#guardar_consignacion_venta")[0].reset(); //para reseatear formulario y limpiar todos los campos
			$.ajax({
				url: "../ajax/detalle_consignaciones.php?action=limpiar_info_entrada",
				beforeSend: function(objeto) {
					$("#muestra_detalle_consignacion").html("Iniciando...");
				},
				success: function(data) {
					$(".outer_divdet_consignacion").html(data).fadeIn('fast');
					$('#muestra_detalle_consignacion').html('');
				}
			});
		}


		//para buscar productos
		function buscar_productos() {
			$("#nombre_producto").autocomplete({
				source: '../ajax/productos_autocompletar_inventario.php',
				minLength: 2,
				select: function(event, ui) {
					event.preventDefault();
					$('#id_producto').val(ui.item.id);
					$('#nombre_producto').val(ui.item.nombre);
					$('#precio_producto_consignacion').val(ui.item.precio);
					$('#medida_agregar').val(ui.item.medida);

					var configuracion_inventario = document.getElementById('inventario').value;
					var configuracion_lote = document.getElementById('muestra_lote').value;
					var configuracion_bodega = document.getElementById('muestra_bodega').value;
					var configuracion_vencimiento = document.getElementById('muestra_vencimiento').value;
					var producto = $("#id_producto").val();
					var id_serie = $("#serie_consignacion").val();

					if ((configuracion_inventario == 'NO' || configuracion_inventario == '')) {
						document.getElementById("titulo_lote").style.display = "none";
						document.getElementById("titulo_caducidad").style.display = "none";
						document.getElementById("lista_lote").style.display = "none";
						document.getElementById("lista_caducidad").style.display = "none";
						//var producto = $("#id_producto").val();

						//cuando trae se busca el producto me trae que tipo de medida tiene
						$.post('../ajax/select_tipo_medida.php', {
							id_producto: producto
						}).done(function(res_tipos_medidas) {
							$("#medida_agregar").html(res_tipos_medidas);
						});
					}

					//aqui controla cuando se selecciona producto y trabaja con inventario
					if (configuracion_inventario == 'SI') {

						if (configuracion_lote == 'SI') {
							document.getElementById("titulo_lote").style.display = "";
							document.getElementById("lista_lote").style.display = "";
						}

						if (configuracion_vencimiento == 'SI') {
							document.getElementById("titulo_caducidad").style.display = "";
							document.getElementById("lista_caducidad").style.display = "";
						}

						document.getElementById("existencia_producto").disabled = true;

						$("#existencia_producto").val("0");
						var bodega = $("#bodega_agregar").val();
						var producto = $("#id_producto").val();

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

					//para cargar listado de precios
					$.post('../ajax/select_tipo_precio.php', {
						id_producto: producto,
						serie_sucursal: id_serie
					}).done(function(res_tipos_precios) {
						$("#precio_agregar").html(res_tipos_precios);
					});


					//hasta aqui me controla si trabaja con inventario
					$("#cantidad_agregar").val("1");
					document.getElementById('nup').focus();
				}
			});

			//$("#nombre_producto").autocomplete("widget").addClass("fixedHeight"); //para que aparezca la barra de desplazamiento en el buscar

			$("#nombre_producto").on("keydown", function(event) {
				if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_producto").val("");
					$("#nombre_producto").val("");
					$("#existencia_producto").val("");
					$("#medida_agregar").val("");
					$("#stock_tmp").val("");
					$("#precio_producto_consignacion").val("");
				}
			});

		}

		$(function() {

			//para cuando se selecciona un precio
			$('#precio_agregar').change(function() {
				var precio_seleccionado = $("#precio_agregar").val();
				$("#precio_producto_consignacion").val(precio_seleccionado);
				$("#cantidad_agregar").val("1");
				document.getElementById('nup').focus();
			});
			//para cuando se cambia el select de bodega que me cargue el saldo de ese producto
			$('#bodega_agregar').change(function() {
				var bodega = $("#bodega_agregar").val();
				var producto = $("#id_producto").val();
				var id_medida = $("#medida_agregar").val();

				//reinicia la medida
				$.post('../ajax/select_tipo_medida.php', {
					id_producto: producto
				}).done(function(res_id_medidas) {
					$("#medida_agregar").html(res_id_medidas);
				});

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
				var id_producto = $("#id_producto").val();
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
			});

			//para traer el valor de conversion de medidas en el producto cuando se cambia el select de lote
			$('#lote_agregar').change(function() {
				var lote = $("#lote_agregar").val();
				var producto = $("#id_producto").val();
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

				//para reinicie vencimiento
				$.post('../ajax/select_opciones_inventario.php', {
					opcion: 'caducidad',
					id_producto: producto,
					bodega: bodega
				}).done(function(res_opciones_caducidad) {
					$("#caducidad_agregar").html(res_opciones_caducidad);
				});

				$("#cantidad_agregar").val("1");
				document.getElementById('nup').focus();

			});

			//para traer el valor de conversion de medidas en el producto cuando se cambia el select de caducidad
			$('#caducidad_agregar').change(function() {
				var caducidad = $("#caducidad_agregar").val();
				var producto = $("#id_producto").val();

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
			});

		});

		//agrega un item
		function agregar_item() {
			var id_producto = $("#id_producto").val();
			var cantidad_agregar = $("#cantidad_agregar").val();
			var nup_agregar = $("#nup").val();
			var bodega_agregar = $("#bodega_agregar").val();
			var medida_agregar = $("#medida_agregar").val();
			var precio = $("#precio_producto_consignacion").val();
			var lote_agregar = $("#lote_agregar").val();
			var caducidad_agregar = $("#caducidad_agregar").val();
			var existencia_producto = document.getElementById('existencia_producto').value;
			var configuracion_inventario = $("#inventario").val();
			var control_bodega = document.getElementById('muestra_bodega').value;
			var control_lote = document.getElementById('muestra_lote').value;
			var control_caducidad = document.getElementById('muestra_vencimiento').value;

			//Inicia validacion
			if (id_producto == '') {
				alert('Ingrese producto');
				document.getElementById('nombre_producto').focus();
				return false;
			}
			if (cantidad_agregar == '') {
				alert('Ingrese cantidad');
				document.getElementById('cantidad_agregar').focus();
				return false;
			}
			if (nup_agregar == '') {
				alert('Ingrese número único de producto');
				document.getElementById('nup').focus();
				return false;
			}

			if (isNaN(cantidad_agregar)) {
				alert('El dato ingresado en cantidad, no es un número');
				document.getElementById('cantidad_agregar').focus();
				return false;
			}

			if (configuracion_inventario == 'SI' && control_bodega == 'SI' && bodega_agregar == '0') {
				alert('Seleccione una bodega');
				document.getElementById('bodega_agregar').focus();
				return false;
			}

			if (configuracion_inventario == 'SI' && control_lote == 'SI' && lote_agregar == '0') {
				alert('Seleccione un lote');
				document.getElementById('lote_agregar').focus();
				return false;
			}

			if (configuracion_inventario == 'SI' && control_caducidad == 'SI' && caducidad_agregar == '0') {
				alert('Seleccione fecha de vencimiento');
				document.getElementById('caducidad_agregar').focus();
				return false;
			}

			if (parseFloat(cantidad_agregar) > parseFloat(existencia_producto) && configuracion_inventario == 'SI') {
				alert('El saldo en inventarios es menor a la cantidad a consignar ');
				document.getElementById('cantidad_agregar').focus();
				return false;
			}

			//Fin validacion
			$("#muestra_detalle_consignacion").fadeIn('fast');
			$.ajax({
				url: "../ajax/detalle_consignaciones.php?action=agregar_detalle_consignacion_venta&id_producto=" + id_producto + "&cantidad_agregar=" + cantidad_agregar + "&bodega_agregar=" + bodega_agregar + "&medida_agregar=" + medida_agregar + "&lote_agregar=" + lote_agregar + "&caducidad_agregar=" + caducidad_agregar + "&inventario=" + inventario + "&nup=" + nup_agregar + "&precio=" + precio,
				beforeSend: function(objeto) {
					$("#muestra_detalle_consignacion").html("Cargando detalle...");
				},
				success: function(data) {
					$(".outer_divdet_consignacion").html(data).fadeIn('fast');
					$('#muestra_detalle_consignacion').html('');
					document.getElementById("nombre_producto").value = "";
					document.getElementById("cantidad_agregar").value = "1";
					document.getElementById("medida_agregar").value = "";
					document.getElementById("existencia_producto").value = "";
					document.getElementById("id_producto").value = "";
					document.getElementById("nup").value = "";
					document.getElementById("precio_producto_consignacion").value = "";
					document.getElementById('nombre_producto').focus();
				}
			});
		}

		//para guardar la consignacion_ventas
		$("#guardar_consignacion_venta").submit(function(event) {
			event.preventDefault(); // Prevenir comportamiento por defecto
			$("#guardar_datos").hide(); // Ocultar el botón mientras se procesa
			var parametros = $(this).serialize();
			// Deshabilitar el formulario para evitar múltiples envíos
			$("#guardar_consignacion_venta :input").prop("disabled", true);
			$.ajax({
				type: "POST",
				url: "../ajax/guardar_consignacion_ventas.php",
				data: parametros,
				beforeSend: function(objeto) {
					$("#loader_consignacion_venta").html("Guardando...");
				},
				success: function(datos) {
					$("#loader_consignacion_venta").html(datos);
					$("#loader_consignacion_venta").html(''); // Limpiar mensajes
					$("#guardar_datos").show(); // Mostrar botón nuevamente
					$("#guardar_consignacion_venta :input").prop("disabled", false); // Rehabilitar inputs
					load(1); // Recargar la vista
				},
				error: function() {
					$("#loader_consignacion_venta").html("Error en la solicitud."); // Mensaje de error
					$("#guardar_consignacion_venta :input").prop("disabled", false); // Rehabilitar inputs
				}
			});
		});


		//eliminar consignacion_ventas
		function eliminar_consignacion_ventas(codigo) {
			var q = $("#q").val();
			if (confirm("Realmente desea anular la consignación?")) {
				$.ajax({
					type: "GET",
					url: "../ajax/detalle_consignaciones.php",
					data: "action=eliminar_consignacion_ventas&codigo_unico=" + codigo,
					"q": q,
					beforeSend: function(objeto) {
						$("#resultados").html("Mensaje: Cargando...");
					},
					success: function(datos) {
						$("#resultados").html(datos);
						load(1);
					}
				});
			}
		}

		//DETALLE consignacion_ventas
		function mostrar_detalle_consignacion(codigo) {
			$.ajax({
				url: "../ajax/detalle_consignaciones.php?action=detalle_consignacion&codigo_unico=" + codigo,
				beforeSend: function(objeto) {
					$("#loaderdet").html("Iniciando...");
				},
				success: function(data) {
					$(".outer_divdet").html(data).fadeIn('fast');
					$('#loaderdet').html('');
				}
			});
		}


		// bandera para evitar múltiples solicitudes simultáneas
		var _cargandoDetalleConsignacion = false;

		function obtener_datos(id) {
			if (_cargandoDetalleConsignacion) {
				return;
			} // evita doble clic

			carga_modal(); // tu función para abrir/mostrar el modal

			// 1) Lee valores
			var codigo_unico = $("#mod_codigo_unico" + id).val();
			var fecha_consignacion = $("#mod_fecha_consignacion" + id).val();
			var mod_nombre_cliente = $("#mod_nombre_cliente" + id).val();
			var mod_id_cliente = $("#mod_id_cliente" + id).val();
			var mod_punto_partida = $("#mod_punto_partida" + id).val();
			var mod_punto_llegada = $("#mod_punto_llegada" + id).val();
			var mod_responsable = $("#mod_responsable" + id).val();
			var mod_serie = $("#mod_serie" + id).val();
			var mod_observaciones = $("#mod_observaciones" + id).val();
			var mod_fecha_entrega = $("#mod_fecha_entrega" + id).val();
			var mod_hora_desde = $("#mod_hora_entrega_desde" + id).val();
			var mod_hora_hasta = $("#mod_hora_entrega_hasta" + id).val();
			var mod_traslado_por = $("#mod_traslado_por" + id).val();

			// 2) Llena campos inmediatamente (esto no depende del ajax)
			$("#codigo_unico").val(codigo_unico);
			$("#fecha_consignacion_salida").val(fecha_consignacion);
			$("#id_cliente_consignacion_venta").val(mod_id_cliente);
			$("#cliente_consignacion_venta").val(mod_nombre_cliente);
			$("#punto_partida").val(mod_punto_partida);
			$("#punto_llegada").val(mod_punto_llegada);
			$("#responsable_traslado").val(mod_responsable);
			$("#serie_consignacion").val(mod_serie);
			$("#observacion_consignacion_venta").val(mod_observaciones);
			$("#fecha_pedido").val(mod_fecha_entrega);
			$("#hora_entrega_desde").val(mod_hora_desde);
			$("#hora_entrega_hasta").val(mod_hora_hasta);
			$("#traslado").val(mod_traslado_por);

			// 3) Prepara UI de carga (bloquea botones/inputs del modal)
			_cargandoDetalleConsignacion = true;
			var $contenedor = $(".outer_divdet_consignacion");
			var $loaderSlot = $("#muestra_detalle_consignacion");

			// overlay simple para evitar interacción
			var overlayHtml = '<div id="overlayCargaDet" ' +
				'style="position:absolute; inset:0; background:rgba(255,255,255,.6); ' +
				'display:flex; align-items:center; justify-content:center; z-index:9999;">' +
				'<div><img src="../image/ajax-loader.gif" style="margin-right:6px;"> Cargando detalle de productos...</div>' +
				'</div>';

			// el contenedor padre del listado debería ser posicionable
			$contenedor.css('position', 'relative');
			$contenedor.append(overlayHtml);

			// también un mensaje fallback
			$("#muestra_detalle_consignacion").fadeIn('fast');
			$loaderSlot.html('<img src="../image/ajax-loader.gif"> Cargando detalle de productos...');

			// deshabilita inputs del formulario principal dentro del modal para evitar cambios mientras carga
			var $modal = $("#nueva_consignacion_venta, #detallePedido, .modal:visible"); // ajusta al id real del modal si aplica
			$modal.find('input, select, button, textarea').prop('disabled', true);

			// 4) AJAX seguro (GET con params y manejo de errores)
			$.ajax({
				url: "../ajax/detalle_consignaciones.php",
				method: "GET",
				data: {
					action: "editar_detalle_consignacion_venta",
					codigo_unico: codigo_unico // jQuery se encarga de codificar
				},
				dataType: "html",
				timeout: 30000, // 30s por si hay red lenta
				cache: false,
				success: function(html) {
					// pinta primero
					$contenedor.html(html).fadeIn('fast');
					$loaderSlot.html('');

					// enfoca un input relevante tras pintar (espera 1 frame)
					window.requestAnimationFrame(function() {
						var $focus = $('#cliente_consignacion_venta');
						if ($focus.length) {
							$focus.focus();
						}
					});

					// notificación de éxito DESPUÉS de renderizar
					/* if (window.$ && $.notify) {
						$.notify('Ítems cargados desde la consignación', 'success');
					} */
				},
				error: function(xhr, status) {
					$loaderSlot.html('');
					$contenedor.find('#overlayCargaDet').remove();
					var msg = (status === 'timeout') ? 'Tiempo de espera agotado.' : 'Error al cargar el detalle.';
					if (window.$ && $.notify) {
						$.notify(msg, 'error');
					}
				},
				complete: function() {
					// quita overlay y re-habilita la UI SIEMPRE al final
					$contenedor.find('#overlayCargaDet').remove();
					$modal.find('input, select, button, textarea').prop('disabled', false);
					_cargandoDetalleConsignacion = false;
				}
			});
		}



		/* 		function mostrar_detalle_pedido() {
					var pedido = $("#numero_pedido").val();
					var bodega = $("#bodega_pedido").val();
					if (pedido == "") {
						alert('Ingrese número de pedido.');
						document.getElementById('numero_pedido').focus();
						return false;
					}
					$.ajax({
						url: "../ajax/consignacion_venta.php?action=detalle_pedido&pedido=" + pedido + "&bodega=" + bodega,
						beforeSend: function(objeto) {
							$("#loaderdetPedido").html("Cargando...");
						},
						success: function(data) {
							$(".outer_divdetpedido").html(data).fadeIn('fast');
							$('#loaderdetPedido').html('');
						}
					});
				} */

		function mostrar_detalle_pedido() {
			var pedido = $("#numero_pedido").val().trim();
			var bodega = $("#bodega_pedido").val();

			if (!pedido) {
				alert('Ingrese número de pedido.');
				$("#numero_pedido").focus();
				return false;
			}

			$.ajax({
				type: "GET", // o "POST" si prefieres ocultar los params
				url: "../ajax/consignacion_venta.php",
				data: {
					action: "detalle_pedido",
					pedido: pedido,
					bodega: bodega
				},
				dataType: "html", // podrías usar "json" si en algún momento quieres manejar datos estructurados
				beforeSend: function() {
					$("#loaderdetPedido").html("Cargando...");
				},
				success: function(data) {
					$(".outer_divdetpedido").html(data).fadeIn("fast");
				},
				error: function(xhr, status, error) {
					console.error("Error AJAX:", status, error);
					alert("Hubo un problema al cargar el pedido.");
				},
				complete: function() {
					$("#loaderdetPedido").html("");
				}
			});
		}


		//cada vez que se selecciona un lote en pedidos
		function saldo_producto_pedido(id) {
			var lote = $("#lote_pedido" + id).val();
			var producto = $("#id_producto_pedido" + id).val();
			var bodega = $("#bodega_pedido").val();

			if (!lote || !producto || !bodega) {
				console.warn("Faltan datos para consultar saldo.");
				return;
			}

			$.post('../ajax/saldo_producto_inventario.php', {
				opcion_lote: lote,
				id_producto: producto,
				bodega: bodega
			}).done(function(respuesta_lote) {
				$("#existencia_pedido" + id).val(respuesta_lote);
			});
			document.getElementById('nup_pedido' + id).focus();
		}


		function agregar_item_pedido(id) {
			var id_producto = $("#id_producto_pedido" + id).val();
			var cantidad_agregar = $("#cantidad_pedido" + id).val();
			var nup_agregar = $("#nup_pedido" + id).val();
			var bodega_agregar = $("#bodega_pedido").val();
			var medida_agregar = $("#id_medida_pedido" + id).val();
			var lote_agregar = $("#lote_pedido" + id).val();
			var caducidad_agregar = 0;
			var existencia_producto = document.getElementById("existencia_pedido" + id).value;
			var id_cliente = $("#id_cliente_pedido" + id).val();
			var numero_pedido = $("#numero_pedido" + id).val();
			var observaciones_pedido = $("#observaciones_pedido" + id).val();
			var nombre_cliente = $("#nombre_cliente_pedido" + id).val();
			var saldo_entrante = $("#saldo_entrante" + id).val();
			var hora_entrega_desde = $("#hora_entrega_desde" + id).val();
			var hora_entrega_hasta = $("#hora_entrega_hasta" + id).val();
			var fecha_entrega = $("#fecha_entrega" + id).val();
			var responsable = $("#responsable" + id).val();
			var precio_pedido = $("#precio_pedido" + id).val();
			var id_vendedor = $("#id_vendedor" + id).val();

			//Inicia validacion
			if (id_producto == '') {
				alert('Cargue un pedido y luego seleccione un producto');
				return false;
			}
			if (cantidad_agregar == '') {
				alert('Ingrese cantidad');
				document.getElementById('cantidad_pedido' + id).focus();
				return false;
			}
			if (precio_pedido == '') {
				alert('Ingrese precio');
				document.getElementById('precio_pedido' + id).focus();
				return false;
			}
			if (precio_pedido < 0) {
				alert('El precio no puede ser menor a cero');
				document.getElementById('precio_pedido' + id).focus();
				return false;
			}
			if (lote_agregar == 0) {
				alert('Seleccione un lote');
				document.getElementById('lote_pedido' + id).focus();
				return false;
			}
			if (nup_agregar == '') {
				alert('Ingrese número único de producto');
				document.getElementById('nup_pedido' + id).focus();
				return false;
			}

			if (isNaN(cantidad_agregar)) {
				alert('El dato ingresado en cantidad, no es un número');
				document.getElementById('cantidad_pedido' + id).focus();
				return false;
			}
			if (isNaN(precio_pedido)) {
				alert('El dato ingresado en precio, no es un número');
				document.getElementById('precio_pedido' + id).focus();
				return false;
			}

			if (parseFloat(cantidad_agregar) > parseFloat(existencia_producto)) {
				alert('El saldo en inventarios es menor a la cantidad a consignar.');
				document.getElementById('cantidad_pedido' + id).focus();
				return false;
			}

			if (saldo_entrante == 0) {
				alert('No es posible agregar, este producto en este pedido ya fue despachado en su totalidad.');
				return false;
			}

			//Fin validacion
			$("#muestra_detalle_consignacion").fadeIn('fast');
			$.ajax({
				url: "../ajax/detalle_consignaciones.php?action=agregar_detalle_consignacion_venta&id=" + id + "&id_producto=" + id_producto + "&cantidad_agregar=" + cantidad_agregar + "&bodega_agregar=" + bodega_agregar + "&medida_agregar=" + medida_agregar + "&lote_agregar=" + lote_agregar + "&caducidad_agregar=" + caducidad_agregar + "&inventario=" + inventario + "&nup=" + nup_agregar + "&precio=" + precio_pedido,
				beforeSend: function(objeto) {
					$("#muestra_detalle_consignacion").html("Cargando detalle...");
				},
				success: function(data) {
					$(".outer_divdet_consignacion").html(data).fadeIn('fast');
					$('#muestra_detalle_consignacion').html('');
					document.getElementById("cantidad_pedido" + id).value = "1";
					document.getElementById("existencia_pedido" + id).value = 0;
					document.getElementById("lote_pedido" + id).value = 0;
					document.getElementById("nup_pedido" + id).value = "";
					document.getElementById('nup_pedido' + id).focus();
					$("#id_cliente_consignacion_venta").val(id_cliente);
					$("#cliente_consignacion_venta").val(nombre_cliente);
					$("#observacion_consignacion_venta").val('No. pedido: ' + numero_pedido + ' Obs: ' + observaciones_pedido);
					$("#fecha_pedido").val(fecha_entrega);
					$("#hora_entrega_desde").val(hora_entrega_desde);
					$("#hora_entrega_hasta").val(hora_entrega_hasta);
					$("#traslado").val(responsable);
					$("#responsable_traslado").val(id_vendedor);
					mostrar_detalle_pedido();
				}
			});
		}



		function lista_precios_pedido(id) {
			var precio_seleccionado = $("#lista_precios_pedido" + id).val();
			$("#precio_pedido" + id).val(precio_seleccionado);
			document.getElementById('nup_pedido' + id).focus();
		}


		function detalle_entrega_destino(latitud, longitud, observaciones_entrega, fecha_hora_entregado, direccion_entregado, encargado_entrega) {
			$("#latitud").val(latitud);
			$("#longitud").val(longitud);
			$("#observaciones_entrega").val(observaciones_entrega);
			$("#fecha_hora_entregado").val(fecha_hora_entregado);
			$("#direccion_entregado").val(direccion_entregado);
			$("#encargado_entrega").val(encargado_entrega);
		}


		//cada vez que selecciona el select de lotes se carga los lotes
		function cargarLotesEnSelect(idFila) {
			var id_producto = $("#id_producto_pedido" + idFila).val();
			var bodega = $("#bodega_pedido").val();
			var $select = $("#lote_pedido" + idFila);

			if (!id_producto || !bodega) {
				console.warn("Faltan datos: producto o bodega.");
				return;
			}

			// Mostrar cargando
			$select.empty().append('<option value="0">Cargando...</option>');

			$.ajax({
				url: "../ajax/consignacion_venta.php",
				method: "GET",
				dataType: "json",
				data: {
					action: "obtener_lotes",
					id_producto: id_producto,
					bodega: bodega
				},
				success: function(resp) {
					$select.empty().append('<option value="0">Seleccione</option>');
					if (resp.status === "ok" && resp.lotes.length > 0) {
						resp.lotes.forEach(function(l) {
							var fcad = l.fecha_caducidad ? new Date(l.fecha_caducidad) : null;
							var cad = fcad ? ("0" + (fcad.getMonth() + 1)).slice(-2) + "-" + fcad.getFullYear() : "";
							$select.append(
								$("<option>", {
									value: l.lote,
									text: l.lote + " vence:" + cad
								}) //+ " saldo:" + l.saldo
							);
						});
					} else {
						$select.append('<option value="0">Sin lotes</option>');
					}
				},
				error: function() {
					$select.empty().append('<option value="0">Error cargando lotes</option>');
				}
			});
		}

		// disparar al hacer foco o clic en el select
		$(document).on("focus", "select[id^='lote_pedido']", function() {
			var idFila = this.id.replace("lote_pedido", "");
			if (!$(this).data("loaded")) {
				cargarLotesEnSelect(idFila);
				$(this).data("loaded", true); // para no recargar siempre
			}
		});
	</script>