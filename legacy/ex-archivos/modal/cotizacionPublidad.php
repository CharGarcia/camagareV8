	<!-- Modal -->
	<div class="modal fade" data-backdrop="static" id="cotizacionPublicidad" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
		<div class="modal-dialog modal-lg" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<!-- <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button> -->
					<h4 class="modal-title" id="titleModalCotizacionPublicidad"></h4>
				</div>
				<div class="modal-body">
					<form class="form-horizontal" method="post" id="guardar_cotizacion_publicidad" name="guardar_cotizacion_publicidad">
						<input type="hidden" id="id_cotizacion_publicidad" name="id_cotizacion_publicidad">
						<input type="hidden" id="id_cliente_cotizacion">
						<div class="modal-body">
							<div class="well well-sm" style="margin-bottom: -10px; margin-top: -20px;">
								<div class="form-group">
									<div class="col-sm-6">
										<div class="input-group">
											<span class="input-group-addon"><b>Proyecto</b></span>
											<input type="text" class="form-control input-sm" title="Nombre del proyecto" id="nombre_proyecto" name="nombre_proyecto" required>
										</div>
									</div>
									<div class="col-sm-3">
										<div class="input-group">
											<span class="input-group-addon"><b>Fecha</b></span>
											<input type="text" class="form-control input-sm" onchange="genera_numero_cotizacion();" title="Fecha de emisión" id="fecha_cotizacion" name="fecha_cotizacion" value="<?php echo date("d-m-Y"); ?>">
										</div>
									</div>
									<div class="col-sm-3">
										<div class="input-group">
											<span class="input-group-addon"><b>No.</b></span>
											<input type="hidden" id="consecutivo_numero_cotizacion" name="consecutivo_numero_cotizacion">
											<input type="text" class="form-control input-sm" title="Número de cotización" id="numero_cotizacion" name="numero_cotizacion" readonly>
										</div>
									</div>
								</div>
								<div class="form-group">
									<div class="col-sm-9">
										<div class="input-group">
											<span class="input-group-addon"><b>Empresa</b></span>
											<input type="text" class="form-control input-sm" title="Nombre del empresa" placeholder="Agregue un cliente por ruc, cedula o nombre" id="nombre_empresa" name="nombre_empresa" onkeyup='buscar_clientes();' autocomplete="off">
										</div>
									</div>
									<div class="col-sm-3">
										<div class="input-group">
											<span class="input-group-addon"><b>Versión</b></span>
											<input type="number" class="form-control input-sm" title="Versión Cotización" id="version_cotizacion" name="version_cotizacion" readonly>
										</div>
									</div>
								</div>
								<div class="form-group">
									<div class="col-sm-4">
										<div class="input-group">
											<span class="input-group-addon"><b>Contacto</b></span>
											<input type="text" class="form-control input-sm" title="Contacto" id="contacto_empresa" name="contacto_empresa" required>
										</div>
									</div>
									<div class="col-sm-4">
										<div class="input-group">
											<span class="input-group-addon"><b>Presupuesto</b></span>
											<input type="text" class="form-control input-sm" title="Presupuesto" id="presupuesto" name="presupuesto">
										</div>
									</div>
									<div class="col-sm-4">
										<div class="input-group">
											<span class="input-group-addon"><b>Ejecutivo</b></span>
											<select class="form-control input-sm" id="id_vendedor" name="id_vendedor">
												<?php
												$sql = mysqli_query($conexion, "SELECT * FROM vendedores WHERE ruc_empresa ='" . $ruc_empresa . "' order by nombre asc");
												?>
												<option value="">Seleccione</option>
												<?php
												while ($p = mysqli_fetch_assoc($sql)) {
												?>
													<option value="<?php echo $p['id_vendedor']; ?>"><?php echo $p['nombre']; ?> </option>
												<?php
												}
												?>
											</select>
										</div>
									</div>
								</div>
								<div class="form-group">
									<div class="col-sm-6">
										<div class="input-group" style="margin-bottom: -15px; margin-top: 0px;">
											<span class="input-group-addon"><b>Observaciones</b></span>
											<input type="text" class="form-control input-sm" title="Observaciones" id="observaciones" name="observaciones">
										</div>
									</div>
									<div class="col-sm-3">
										<div class="input-group" style="margin-bottom: -15px; margin-top: 0px;">
											<span class="input-group-addon"><b>IVA aplicable</b></span>
											<select class="form-control input-sm" id="tipo_iva" name="tipo_iva" onchange="actualiza_iva_comision();">
												<?php
												$sql = mysqli_query($conexion, "SELECT * FROM tarifa_iva WHERE status=1 order by porcentaje_iva asc");
												while ($p = mysqli_fetch_assoc($sql)) {
												?>
													<option value="<?php echo $p['id']; ?>" selected><?php echo $p['tarifa']; ?> </option>
												<?php
												}
												?>
											</select>
										</div>
									</div>
									<div class="col-sm-3">
										<div class="input-group" style="margin-bottom: -15px; margin-top: 0px;">
											<span class="input-group-addon"><b>Comisión %</b></span>
											<input type="number" onchange="actualiza_iva_comision();" class="form-control input-sm" title="Porcentaje de comisión" id="comision" name="comision" value="17" step="0.01">
										</div>
									</div>
								</div>
							</div>
						</div>

						<div class="panel panel-info">
							<div class="table-responsive">
								<table class="table table-bordered">
									<tr class="info">
										<th style="padding: 2px;">Descripción de servicios</th>
										<th style="padding: 2px;">Tipo</th>
										<th style="padding: 2px;">Precio</th>
										<th style="padding: 2px;">Ciudades</th>
										<th style="padding: 2px;">Días</th>
										<th style="padding: 2px;">Cantidad</th>
										<th style="padding: 2px;" class="text-center">↓</th>
									</tr>

									<td class="col-xs-4" style="padding: 1px;">
										<input type="text" class="form-control input-sm" title="Ingrese detalle" id="descripcion_cotizacion" placeholder="Detalle">
									</td>
									<td style="padding: 1px;">
										<select class="form-control input-sm" id="id_tipo" name="id_tipo">
											<?php
											$sql = mysqli_query($conexion, "SELECT * FROM grupo_familiar_producto WHERE ruc_empresa ='" . $ruc_empresa . "' order by nombre_grupo asc");
											?>
											<option value="">Seleccione</option>
											<?php
											while ($p = mysqli_fetch_assoc($sql)) {
											?>
												<option value="<?php echo $p['id_grupo']; ?>"><?php echo $p['nombre_grupo']; ?> </option>
											<?php
											}
											?>
										</select>
									</td>
									<td style="padding: 1px;">
										<input type="number" class="form-control input-sm" title="Ingrese precio" id="precio_cotizacion" placeholder="Precio">
									</td>
									<td style="padding: 1px;">
										<input type="number" class="form-control input-sm" title="Ingrese ciudades" id="ciudades_cotizacion" placeholder="Ciudades" value="1">
									</td>
									<td style="padding: 1px;">
										<input type="number" class="form-control input-sm" title="Ingrese días" id="dias_cotizacion" placeholder="Días" value="1">
									</td>
									<td style="padding: 1px;">
										<input type="number" class="form-control input-sm" title="Ingrese cantidad" id="cantidad_cotizacion" placeholder="Cantidad" value="1">
									</td>
									<td style="text-align:center; padding: 1px;">
										<button type="button" class="btn btn-info btn-sm" title="Agregar" onclick="agregar_item_cotizacion()"><span class="glyphicon glyphicon-plus"></span></button>
									</td>
								</table>
							</div>
						</div>

						<div class="panel panel-info" style="margin-bottom: 4px; margin-top: -15px; height: 200px;overflow-y: auto;">
							<div id="vista_detalle_cotizacion"></div><!-- Carga los datos ajax -->
						</div>
				</div>
				<div class="modal-footer">
					<span id="resultados_ajax_cotizacion_publicidad"></span>
					<button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
					<button type="button" class="btn btn-primary" onclick="guarda_cotizacion(event);" id="btnActionFormCotizacionPublidad"><span id="btnTextCotizacionPublicidad"></span></button>
				</div>
				</form>
			</div>
		</div>
	</div>

	<!-- costos -->
	<div class="modal fade" data-backdrop="static" id="cotizacionCostos" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
		<div class="modal-dialog modal-lg" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					<h4 class="modal-title" id="titleModalCotizacionPublicidad"><i class='glyphicon glyphicon-edit'></i> Detalle de costos</h4>
				</div>
				<div class="modal-body">
					<div id="vista_detalle_costos_cotizacion"></div>
				</div>
				<div class="modal-footer">
					<span id="resultados_ajax_costos_cotizacion_publicidad"></span>
					<button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
				</div>
			</div>
		</div>
	</div>

	<!-- facturar -->
	<div class="modal fade" data-backdrop="static" id="cotizacionFactura" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
		<div class="modal-dialog modal-lg" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					<h4 class="modal-title" id="titleModalFacturarCotizacionPublicidad"></h4>
				</div>
				<div class="modal-body">
					<form class="form-horizontal" method="post" id="guardar_factura_cotizacion_publicidad" name="guardar_factura_cotizacion_publicidad">
						<input type="hidden" id="id_facturar_cotizacion_publicidad">
						<input type="hidden" id="id_cliente_cotizacion_publicidad">
						<input type="hidden" id="id_iva_cotizacion_publicidad">
						<input type="hidden" id="numero_cotizacion_publicidad">
						<div class="form-group">
							<div class="col-sm-3">
								<div class="input-group">
									<span class="input-group-addon"><b>Fecha</b></span>
									<input type="text" class="form-control input-sm" title="Fecha de factura" id="fecha_factura" placeholder="Fecha" value="<?php echo date("d-m-Y"); ?>">
								</div>
							</div>
							<div class="col-sm-3">
								<div class="input-group">
									<span class="input-group-addon"><b>Serie</b></span>
									<select class="form-control input-sm" id="serie_factura">
										<?php
										$con = conenta_login();
										$sql = "SELECT * FROM sucursales where ruc_empresa ='" . $ruc_empresa . "' order by serie desc;";
										$res = mysqli_query($con, $sql);
										while ($serie = mysqli_fetch_assoc($res)) {
										?>
											<option value="<?php echo $serie['serie'] ?>" selected><?php echo $serie['serie'] ?></option>
										<?php
										}
										?>
									</select>
								</div>
							</div>
							<div class="col-sm-3">
								<div class="input-group">
									<span class="input-group-addon"><b>Factura</b></span>
									<input type="number" class="form-control input-sm" title="Número de factura" id="numero_factura" placeholder="Factura">
								</div>
							</div>
							<div class="col-sm-3">
								<div class="input-group">
									<span class="input-group-addon"><b>Estado</b></span>
									<input type="text" class="form-control input-sm" id="estado_factura" readonly>
								</div>
							</div>
						</div>
						<div class="form-group">
							<div class="col-sm-3">
								<div class="input-group">
									<span class="input-group-addon"><b>Código</b></span>
									<input type="hidden" id="id_producto_factura_cotizacion">
									<input type="text" class="form-control input-sm" title="Código" id="codigo_servicio_factura" placeholder="Código" onkeyup='buscar_productos_cotizacion();' autocomplete="off">
								</div>
							</div>
							<div class="col-sm-9">
								<div class="input-group">
									<span class="input-group-addon"><b>Detalle</b></span>
									<input type="text" class="form-control input-sm" title="Descripción del servicio" id="nombre_servicio_factura" placeholder="Descripción">
								</div>
							</div>
						</div>
						<div class="form-group">
							<div class="col-sm-4">
								<div class="input-group">
									<span class="input-group-addon"><b>Cantidad</b></span>
									<input type="number" class="form-control input-sm" title="Cantidad" id="cantidad_factura" placeholder="Cantidad" value="1" readonly>
								</div>
							</div>
							<div class="col-sm-4">
								<div class="input-group">
									<span class="input-group-addon"><b>Precio</b></span>
									<input type="number" class="form-control input-sm" title="Precio" id="precio_factura" placeholder="Precio" step="0.01" readonly>
								</div>
							</div>
							<div class="col-sm-4">
								<div class="input-group">
									<span class="input-group-addon"><b>Subtotal</b></span>
									<input type="number" class="form-control input-sm text-right" title="Subtotal" id="subtotal_factura" placeholder="Subtotal" step="0.01" readonly>
								</div>
							</div>
						</div>
						<div class="form-group">
							<div class="col-sm-4">
							</div>
							<div class="col-sm-4">
							</div>
							<div class="col-sm-4">
								<div class="input-group">
									<span class="input-group-addon"><b>Iva</b></span>
									<input type="number" class="form-control input-sm text-right" title="Iva" id="iva_factura" placeholder="Iva" step="0.01" readonly>
								</div>
							</div>
						</div>
						<div class="form-group">
							<div class="col-sm-4">
							</div>
							<div class="col-sm-4">
							</div>
							<div class="col-sm-4">
								<div class="input-group">
									<span class="input-group-addon"><b>Total</b></span>
									<input type="number" class="form-control input-sm text-right" title="Total" id="total_factura" placeholder="Total" step="0.01" readonly>
								</div>
							</div>
						</div>
				</div>
				<div class="modal-footer">
					<span id="resultados_ajax_factura_cotizacion_publicidad"></span>
					<button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
					<button type="button" class="btn btn-primary" onclick="guarda_factura(event);" id="btnActionFormFacturarCotizacionPublidad"><span id="btnTextFacturarCotizacionPublicidad"></span></button>
				</div>
				</form>
			</div>
		</div>
	</div>

	<link rel="stylesheet" href="../css/jquery-ui.css">
	<script src="../js/jquery-ui.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
	<script src="../js/notify.js"></script>
	<script>
		$('#fecha_cotizacion').css('z-index', 1500);
		$('#fecha_factura').css('z-index', 1500);
		jQuery(function($) {
			$("#fecha_cotizacion").mask("99-99-9999");
			$("#fecha_factura").mask("99-99-9999");
		});

		$(function() {
			$("#fecha_cotizacion").datepicker({
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


		//agregar item al detalle de la cotizacion
		function agregar_item_cotizacion() {
			var descripcion_cotizacion = document.getElementById('descripcion_cotizacion').value;
			var id_tipo = document.getElementById('id_tipo').value;
			var precio_cotizacion = document.getElementById('precio_cotizacion').value;
			var ciudades_cotizacion = document.getElementById('ciudades_cotizacion').value;
			var dias_cotizacion = document.getElementById('dias_cotizacion').value;
			var cantidad_cotizacion = document.getElementById('cantidad_cotizacion').value;
			var tipo_iva = document.getElementById('tipo_iva').value;
			var comision = document.getElementById('comision').value;

			//Inicia validacion
			if (descripcion_cotizacion == "") {
				alert('Agregar descripción');
				document.getElementById('descripcion_cotizacion').focus();
				return false;
			}

			if (id_tipo == "") {
				alert('Seleccione tipo');
				document.getElementById('id_tipo').focus();
				return false;
			}


			if (precio_cotizacion < 0) {
				alert('Ingrese precio correcto');
				document.getElementById('precio_cotizacion').focus();
				return false;
			}

			if (precio_cotizacion == "") {
				alert('Ingrese precio correcto');
				document.getElementById('precio_cotizacion').focus();
				return false;
			}

			if (ciudades_cotizacion < 1) {
				alert('Ingrese cantidad de ciudades');
				document.getElementById('ciudades_cotizacion').focus();
				return false;
			}

			if (dias_cotizacion < 1) {
				alert('Ingrese días');
				document.getElementById('dias_cotizacion').focus();
				return false;
			}

			if (cantidad_cotizacion <= 0) {
				alert('Ingrese cantidad correcta');
				document.getElementById('cantidad_cotizacion').focus();
				return false;
			}
			//Fin validacion
			$.ajax({
				type: "POST",
				url: "../ajax/cotizacion_publicidad.php?action=agregar_item_cotizacion",
				data: "descripcion_cotizacion=" + descripcion_cotizacion + "&id_tipo=" + id_tipo +
					"&precio_cotizacion=" + precio_cotizacion + "&ciudades_cotizacion=" + ciudades_cotizacion +
					"&dias_cotizacion=" + dias_cotizacion + "&cantidad_cotizacion=" + cantidad_cotizacion + "&tipo_iva=" + tipo_iva + "&comision=" + comision,
				beforeSend: function(objeto) {
					$("#vista_detalle_cotizacion").html("Cargando...");
				},
				success: function(datos) {
					$("#vista_detalle_cotizacion").html(datos);
					$("#descripcion_cotizacion").val("");
					$("#precio_cotizacion").val("");
					$("#id_tipo").val("");
					document.getElementById('descripcion_cotizacion').focus();
				}
			});
		}

		//eliminar iten de la cotizacion
		function eliminar_item_cotizacion(id) {
			if (confirm("Realmente desea eliminar?")) {
				$.ajax({
					type: "POST",
					url: "../ajax/cotizacion_publicidad.php?action=eliminar_item_cotizacion",
					data: "id=" + id,
					beforeSend: function(objeto) {
						$("#vista_detalle_cotizacion").html("Cargando...");
					},
					success: function(datos) {
						$("#vista_detalle_cotizacion").html(datos);
						document.getElementById('descripcion_cotizacion').focus();
					}
				});
			}
		}

		//cambiar la descripcion
		function modificar_descripcion_item(id) {
			var descripcion_item = document.getElementById('descripcion_item' + id).value;
			if (descripcion_item == "") {
				alert('Ingrese una descripción');
				document.getElementById('descripcion_item' + id).focus();
				return false;
			};
			$.ajax({
				type: "POST",
				url: "../ajax/cotizacion_publicidad.php?action=modificar_descripcion_item",
				data: "id=" + id + "&descripcion_item=" + descripcion_item,
				beforeSend: function(objeto) {
					$("#vista_detalle_cotizacion").html("Actualizando...");
				},
				success: function(datos) {
					$("#vista_detalle_cotizacion").html(datos);
				}
			});
		}

		function tipo_item(id) {
			var tipo_iten = document.getElementById('tipo_iten' + id).value;
			if (tipo_iten == "") {
				alert('Seleccione un tipo de servicio');
				document.getElementById('tipo_iten' + id).focus();
				return false;
			};
			$.ajax({
				type: "POST",
				url: "../ajax/cotizacion_publicidad.php?action=modificar_tipo_item",
				data: "id=" + id + "&tipo_iten=" + tipo_iten,
				beforeSend: function(objeto) {
					$("#vista_detalle_cotizacion").html("Actualizando...");
				},
				success: function(datos) {
					$("#vista_detalle_cotizacion").html(datos);
				}
			});
		}

		function precio_item(id) {
			var precio_cotizacion = document.getElementById('precio_item' + id).value;
			if (precio_cotizacion < 0) {
				alert('Ingrese precio correcto');
				document.getElementById('precio_item' + id).focus();
				return false;
			}

			if (precio_cotizacion == "") {
				alert('Ingrese precio correcto');
				document.getElementById('precio_item' + id).focus();
				return false;
			}
			$.ajax({
				type: "POST",
				url: "../ajax/cotizacion_publicidad.php?action=modificar_precio_item",
				data: "id=" + id + "&precio_item=" + precio_cotizacion,
				beforeSend: function(objeto) {
					$("#vista_detalle_cotizacion").html("Actualizando...");
				},
				success: function(datos) {
					$("#vista_detalle_cotizacion").html(datos);
				}
			});
		}


		function ciudad_item(id) {
			var ciudad_item = document.getElementById('ciudad_item' + id).value;
			if (ciudad_item < 1) {
				alert('Ingrese dato correcto');
				document.getElementById('ciudad_item' + id).focus();
				return false;
			}
			$.ajax({
				type: "POST",
				url: "../ajax/cotizacion_publicidad.php?action=modificar_ciudad_item",
				data: "id=" + id + "&ciudad_item=" + ciudad_item,
				beforeSend: function(objeto) {
					$("#vista_detalle_cotizacion").html("Actualizando...");
				},
				success: function(datos) {
					$("#vista_detalle_cotizacion").html(datos);
				}
			});
		}

		function dias_item(id) {
			var dias_item = document.getElementById('dias_item' + id).value;
			if (dias_item < 1) {
				alert('Ingrese dato correcto');
				document.getElementById('dias_item' + id).focus();
				return false;
			}
			$.ajax({
				type: "POST",
				url: "../ajax/cotizacion_publicidad.php?action=modificar_dias_item",
				data: "id=" + id + "&dias_item=" + dias_item,
				beforeSend: function(objeto) {
					$("#vista_detalle_cotizacion").html("Actualizando...");
				},
				success: function(datos) {
					$("#vista_detalle_cotizacion").html(datos);
				}
			});
		}

		function cantidad_item(id) {
			var cantidad_item = document.getElementById('cantidad_item' + id).value;
			if (cantidad_item <= 0) {
				alert('Ingrese cantidad correcta');
				document.getElementById('cantidad_item' + id).focus();
				return false;
			}

			if (cantidad_item == "") {
				alert('Ingrese cantidad');
				document.getElementById('cantidad_item' + id).focus();
				return false;
			}
			$.ajax({
				type: "POST",
				url: "../ajax/cotizacion_publicidad.php?action=modificar_cantidad_item",
				data: "id=" + id + "&cantidad_item=" + cantidad_item,
				beforeSend: function(objeto) {
					$("#vista_detalle_cotizacion").html("Actualizando...");
				},
				success: function(datos) {
					$("#vista_detalle_cotizacion").html(datos);
				}
			});
		}


		//guardar o editar
		function guarda_cotizacion(event) {
			event.preventDefault(); // Prevenir el comportamiento por defecto del formulario
			$('#btnActionFormCotizacionPublidad').attr("disabled", true);
			// Crear objeto con los datos
			var datos = {
				id_cotizacion_publicidad: $("#id_cotizacion_publicidad").val(),
				id_cliente_cotizacion: $("#id_cliente_cotizacion").val(),
				nombre_proyecto: $("#nombre_proyecto").val(),
				contacto_empresa: $("#contacto_empresa").val(),
				numero_cotizacion: $("#consecutivo_numero_cotizacion").val(),
				id_vendedor: $("#id_vendedor").val(),
				fecha_cotizacion: $("#fecha_cotizacion").val(),
				version_cotizacion: $("#version_cotizacion").val(),
				observaciones: $("#observaciones").val(),
				presupuesto: $("#presupuesto").val(),
				tipo_iva: $("#tipo_iva").val(),
				comision: $("#comision").val()
			};

			// Enviar con jQuery AJAX (compatible con PHP 5.6)
			$.ajax({
				type: "POST",
				url: "../ajax/cotizacion_publicidad.php?action=guardar_cotizacion_publicidad",
				data: datos, // No concatenamos manualmente
				beforeSend: function() {
					$("#resultados_ajax_cotizacion_publicidad").html("Guardando...");
				},
				success: function(response) {
					$("#resultados_ajax_cotizacion_publicidad").html(response);
					$('#btnActionFormCotizacionPublidad').attr("disabled", false);
				},
				error: function(xhr, status, error) {
					console.error("Error en la solicitud:", error);
					$("#resultados_ajax_cotizacion_publicidad").html("Error al guardar la cotización.");
					document.querySelector('#guardar_cotizacion_publicidad').reset();
					$('#btnActionFormCotizacionPublidad').attr("disabled", false);
				}
			});
		}



		//cambiar el proveedor
		function modificar_proveedor_costo_item(id_item, id_detalle) {
			var id_proveedor_costo_item = document.getElementById('id_proveedor_costo_item' + id_item).value;
			$.ajax({
				type: "POST",
				url: "../ajax/cotizacion_publicidad.php?action=modificar_proveedor_costo_item",
				data: "id_item=" + id_item + "&id_detalle=" + id_detalle + "&id_proveedor_costo_item=" + id_proveedor_costo_item,
				beforeSend: function(objeto) {
					$("#vista_detalle_costos_cotizacion").html("Actualizando...");
				},
				success: function(datos) {
					$("#vista_detalle_costos_cotizacion").html(datos);
				}
			});
		}

		//cambiar la factura
		function modificar_factura_costo_item(id_item, id_detalle) {
			var factura_costo_item = document.getElementById('factura_costo_item' + id_item).value;
			$.ajax({
				type: "POST",
				url: "../ajax/cotizacion_publicidad.php?action=modificar_factura_costo_item",
				data: "id_item=" + id_item + "&id_detalle=" + id_detalle + "&factura_costo_item=" + factura_costo_item,
				beforeSend: function(objeto) {
					$("#vista_detalle_costos_cotizacion").html("Actualizando...");
				},
				success: function(datos) {
					$("#vista_detalle_costos_cotizacion").html(datos);
				}
			});
		}

		function modificar_valor_costo_item(id_item, id_detalle) {
			var valor_costo_item = document.getElementById('valor_costo_item' + id_item).value;
			if (valor_costo_item < 0) {
				alert('Ingrese valor correcto');
				document.getElementById('valor_costo_item' + id_item).focus();
				return false;
			}

			$.ajax({
				type: "POST",
				url: "../ajax/cotizacion_publicidad.php?action=modificar_valor_costo_item",
				data: "id_item=" + id_item + "&id_detalle=" + id_detalle + "&valor_costo_item=" + valor_costo_item,
				beforeSend: function(objeto) {
					$("#vista_detalle_costos_cotizacion").html("Actualizando...");
				},
				success: function(datos) {
					$("#vista_detalle_costos_cotizacion").html(datos);
				}
			});
		}

		function modificar_estado_costo_item(id_item, id_detalle) {
			var estado_costo_item = document.getElementById('estado_costo_item' + id_item).value;
			$.ajax({
				type: "POST",
				url: "../ajax/cotizacion_publicidad.php?action=modificar_estado_costo_item",
				data: "id_item=" + id_item + "&id_detalle=" + id_detalle + "&estado_costo_item=" + estado_costo_item,
				beforeSend: function(objeto) {
					$("#vista_detalle_costos_cotizacion").html("Actualizando...");
				},
				success: function(datos) {
					$("#vista_detalle_costos_cotizacion").html(datos);
				}
			});
		}

		//para actualizar el iva y la comision
		function actualiza_iva_comision() {
			var tipo_iva = document.getElementById('tipo_iva').value;
			var comision = document.getElementById('comision').value;
			$.ajax({
				type: "POST",
				url: "../ajax/cotizacion_publicidad.php?action=actualiza_iva_comision",
				data: "tipo_iva=" + tipo_iva + "&comision=" + comision,
				beforeSend: function(objeto) {
					$("#vista_detalle_cotizacion").html("Actualizando...");
				},
				success: function(datos) {
					$("#vista_detalle_cotizacion").html(datos);
				}
			});
		}


		function buscar_proveedores(id) {
			$("#proveedor_costo_item" + id).autocomplete({
				source: '../ajax/proveedores_autocompletar.php',
				minLength: 2,
				select: function(event, ui) {
					event.preventDefault();
					$('#id_proveedor_costo_item' + id).val(ui.item.id_proveedor);
					$('#proveedor_costo_item' + id).val(ui.item.razon_social);
					document.getElementById('factura_costo_item' + id).focus();
				}
			});

			$("#proveedor_costo_item" + id).on("keydown", function(event) {
				if (event.keyCode == $.ui.keyCode.UP ||
					event.keyCode == $.ui.keyCode.DOWN ||
					event.keyCode == $.ui.keyCode.DELETE ||
					event.keyCode == 8 || // Retroceso (Backspace)
					event.keyCode == 46) { // Suprimir (Delete)

					$("#id_proveedor_costo_item" + id).val("");
					$("#proveedor_costo_item" + id).val("");
				}
			});

		}


		//guardar factura
		function guarda_factura(event) {
			event.preventDefault(); // Prevenir el comportamiento por defecto del formulario
			$('#btnActionFormFacturarCotizacionPublidad').attr("disabled", true);
			// Crear objeto con los datos
			var datos = {
				id_factura_publicidad: $("#id_facturar_cotizacion_publicidad").val(),
				id_cliente_publicidad: $("#id_cliente_cotizacion_publicidad").val(),
				fecha_factura: $("#fecha_factura").val(),
				serie_factura: $("#serie_factura").val(),
				numero_factura: $("#numero_factura").val(),
				codigo_servicio_factura: $("#codigo_servicio_factura").val(),
				nombre_servicio_factura: $("#nombre_servicio_factura").val(),
				cantidad_factura: $("#cantidad_factura").val(),
				precio_factura: $("#precio_factura").val(),
				subtotal_factura: $("#subtotal_factura").val(),
				total_factura: $("#total_factura").val(),
				id_iva: $("#id_iva_cotizacion_publicidad").val(),
				numero_cotizacion: $("#numero_cotizacion_publicidad").val(),
				id_producto_factura_cotizacion: $("#id_producto_factura_cotizacion").val(),
			};

			// Enviar con jQuery AJAX (compatible con PHP 5.6)
			$.ajax({
				type: "POST",
				url: "../ajax/cotizacion_publicidad.php?action=guardar_factura_publicidad",
				data: datos, // No concatenamos manualmente
				beforeSend: function() {
					$("#resultados_ajax_factura_cotizacion_publicidad").html("Guardando...");
				},
				success: function(response) {
					$("#resultados_ajax_factura_cotizacion_publicidad").html(response);
					$('#btnActionFormFacturarCotizacionPublidad').attr("disabled", false);
				},
				error: function(xhr, status, error) {
					console.error("Error en la solicitud:", error);
					$("#resultados_ajax_factura_cotizacion_publicidad").html("Error al guardar la cotización.");
					document.querySelector('#guardar_factura_cotizacion_publicidad').reset();
					$('#btnActionFormFacturarCotizacionPublidad').attr("disabled", false);
				}
			});
		}


		$(function() {
			//para cuando se cambia el select de opciones
			$('#id_tipo').change(function() {
				document.getElementById('precio_cotizacion').focus();
			});
		});


		function buscar_productos_cotizacion() {
			$("#codigo_servicio_factura").autocomplete({
				source: '../ajax/productos_autocompletar.php',
				minLength: 2,
				select: function(event, ui) {
					event.preventDefault();
					$('#id_producto_factura_cotizacion').val(ui.item.id);
					$('#codigo_servicio_factura').val(ui.item.codigo);
					$('#nombre_servicio_factura').val(ui.item.nombre);
					document.getElementById('nombre_servicio_factura').focus();
				}
			});

			$("#nombre_servicio_factura").autocomplete({
				source: '../ajax/productos_autocompletar.php',
				minLength: 2,
				select: function(event, ui) {
					event.preventDefault();
					$('#id_producto_factura_cotizacion').val(ui.item.id);
					$('#codigo_servicio_factura').val(ui.item.codigo);
					$('#nombre_servicio_factura').val(ui.item.nombre);
					document.getElementById('nombre_servicio_factura').focus();
				}
			});

			$("#codigo_servicio_factura").on("keydown", function(event) {
				if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_producto_factura_cotizacion").val("");
					$("#codigo_servicio_factura").val("");
					$("#nombre_servicio_factura").val("");
				}
				if (event.keyCode == $.ui.keyCode.DELETE) {
					$("#codigo_servicio_factura").val("");
					$("#nombre_servicio_factura").val("");
					$("#id_producto_factura_cotizacion").val("");
				}
			});

			$("#nombre_servicio_factura").on("keydown", function(event) {
				if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_producto_factura_cotizacion").val("");
					$("#codigo_servicio_factura").val("");
					$("#nombre_servicio_factura").val("");
				}
				if (event.keyCode == $.ui.keyCode.DELETE) {
					$("#codigo_servicio_factura").val("");
					$("#nombre_servicio_factura").val("");
					$("#id_producto_factura_cotizacion").val("");
				}
			});
		}
	</script>