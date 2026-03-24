<?php
//para traer la serie de la sucursal primera
$con = conenta_login();
$ruc_empresa = $_SESSION['ruc_empresa'];
ini_set('date.timezone', 'America/Guayaquil');
?>
<div class="modal fade" id="facturacion_consignacion_venta" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" style="overflow-y: scroll;">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-list-alt'></i> Facturación de consignación en ventas</h4>
			</div>
			<div class="modal-body">
				<form method="POST" id="guardar_facturacion_consignacion_venta" name="guardar_facturacion_consignacion_venta">
					<div id="mensajes_facturacion_consignacion_venta"></div>
					<div class="well well-sm" style="margin-bottom: 5px; margin-top: -10px;">
						<div class="panel-body" style="margin-bottom: -25px; margin-top: -15px;">
							<div class="col-sm-3">
								<div class="form-group">
									<div class="input-group">
										<span class="input-group-addon"><b>Fecha</b></span>
										<input type="text" class="form-control input-sm text-center" name="fecha_factura_consignacion_venta" id="fecha_factura_consignacion_venta" value="<?php echo date("d-m-Y"); ?>">
									</div>
								</div>
							</div>
							<div class="col-sm-3">
								<div class="form-group">
									<div class="input-group">
										<span class="input-group-addon"><b>Serie</b></span>
										<select class="form-control" style="height:30px;" title="Seleccione serie." name="serie_factura_consignacion" id="serie_factura_consignacion">
											<?php
											$conexion = conenta_login();
											$sql = "SELECT * FROM sucursales WHERE ruc_empresa ='" . $ruc_empresa . "'";
											$res = mysqli_query($conexion, $sql);
											while ($o = mysqli_fetch_array($res)) {
											?>
												<option value="<?php echo $o['serie'] ?>" selected><?php echo strtoupper($o['serie']) ?></option>
											<?php
											}
											?>
										</select>
									</div>
								</div>
							</div>
							<div class="col-sm-6">
								<div class="form-group">
									<div class="input-group">
										<span class="input-group-addon"><b>Cliente</b></span>
										<input type="hidden" name="id_cliente_factura_consignacion_venta" id="id_cliente_factura_consignacion_venta">
										<input type="text" class="form-control input-sm" placeholder="Buscar cliente" name="cliente_factura_consignacion_venta" id="cliente_factura_consignacion_venta" onkeyup='buscar_clientes();' autocomplete="off">
									</div>
								</div>
							</div>

							<div class="col-sm-6">
								<div class="form-group">
									<div class="input-group">
										<span class="input-group-addon"><b>Info Adicional</b></span>
										<input type="text" class="form-control input-sm" name="adi_concepto" id="adi_concepto" placeholder="Concepto">
										<input type="text" class="form-control input-sm" name="adi_detalle" id="adi_detalle" placeholder="Detalle">
									</div>
								</div>
							</div>

							<div class="col-sm-6">
								<div class="form-group">
									<div class="input-group">
										<span class="input-group-addon"><b>Asesor</b></span>
										<select class="form-control input-sm" name="vendedor" id="vendedor">
											<option value="0" selected>Seleccionar</option>
											<?php
											$vendedores = mysqli_query($con, "SELECT * FROM vendedores WHERE ruc_empresa ='" . $ruc_empresa . "' and status ='1' order by nombre asc ");
											while ($row_vendedores = mysqli_fetch_assoc($vendedores)) {
											?>
												<option value="<?php echo $row_vendedores['id_vendedor'] ?>"><?php echo $row_vendedores['nombre'] ?></option>
											<?php
											}
											?>
										</select>
									</div>
									<div class="input-group">
										<span class="input-group-addon"><b>No. CV</b></span>
										<input style="z-index:inherit;" type="number" class="form-control input-sm" title="Ingrese No. CV" name="numero_consignacion" id="numero_consignacion" placeholder="No. consignación venta"><!--onkeyup="limpiar_producto();"-->
										<span class="input-group-btn btn-md">
											<button class="btn btn-info btn-sm" type="button" title="Mostrar detalle de items en la consignación" onclick="mostrar_detalle_numero_consignacion()" data-toggle="modal" data-target="#detalleNumeroConsignacion"><span class="glyphicon glyphicon-search"></span></button>
										</span>
									</div>
								</div>
							</div>

							<div class="col-sm-12">
								<div class="form-group">
									<div class="input-group">
										<span class="input-group-addon"><b>Observaciones</b></span>
										<input type="text" class="form-control input-sm" name="observacion_factura_consignacion_venta" id="observacion_factura_consignacion_venta">
									</div>
								</div>
							</div>
						</div>
					</div>
					<div id="muestra_detalle_facturacion_consignacion"></div><!-- Carga gif animado -->
					<div class="outer_div_facturacion_consignacion"></div><!-- Datos ajax Final -->
			</div>
			<div class="modal-footer">
				<span id="loader_facturacion"></span>
				<button type="button" class="btn btn-default" onclick="setTimeout(function (){location.href ='../modulos/facturacion_consignacion_venta.php'}, 100);" data-dismiss="modal" id="cerrar_detalle_consignacion">Cerrar</button>
				<button type="submit" class="btn btn-info" id="guardar_datos">Guardar</button>
			</div>
			</form>
		</div>
	</div>
</div>
<?php
include("../modal/aplicar_descuento.php");
?>
<script>
	$('#fecha_factura_consignacion_venta').css('z-index', 1500);

	jQuery(function($) {
		$("#fecha_factura_consignacion_venta").mask("99-99-9999");
	});

	$(function() {
		$("#fecha_factura_consignacion_venta").datepicker({
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

	//para pasar el id de descuento al modal de aplicar descuento
	function opciones_descuentos(id) {
		var subtotal_item = document.getElementById('subtotal_item' + id).value;
		var descuento_inicial = document.getElementById('descuento_inicial' + id).value;
		var tarifa_item = document.getElementById('tarifa_item' + id).value;
		var id_serie = document.getElementById('serie_factura_consignacion').value;
		$("#id_tmp_descuento").val(id);
		$("#subtotal_inicial").val(subtotal_item);
		$("#valor_descuento").val(descuento_inicial);
		$("#tarifa").val(tarifa_item);
		$("#serie_factura_descuento").val(id_serie);
		$("#porcentaje_descuento").val(Number.parseFloat(descuento_inicial / subtotal_item * 100).toFixed(2));
	}

	//descuento en un solo item individual desde el modal del descuento
	function aplicar_descuento_item() {
		var descuento_item = document.getElementById('valor_descuento').value;
		var id_serie = document.getElementById('serie_factura_descuento').value;
		var id = document.getElementById('id_tmp_descuento').value;

		$.ajax({
			type: "POST",
			url: "../ajax/detalle_consignaciones.php?action=actualiza_descuento_item",
			data: "id=" + id + "&descuento_item=" + descuento_item + "&serie_factura=" + id_serie,
			beforeSend: function(objeto) {
				$("#loader_facturacion").html("Cargando...");
			},
			success: function(datos) {
				$(".outer_div_facturacion_consignacion").html(datos).fadeIn('fast');
				$("#loader_facturacion").html('');
			}
		});
	}

	//aplicar descuento a todos los items
	function aplicar_descuento_todos() {
		var porcentaje_descuento = document.getElementById('porcentaje_descuento').value;
		var id_serie = document.getElementById('serie_factura_descuento').value;
		$.ajax({
			type: "POST",
			url: "../ajax/detalle_consignaciones.php?action=aplicar_descuento_todos",
			data: "porcentaje_descuento=" + porcentaje_descuento + "&serie_factura=" + id_serie,
			beforeSend: function(objeto) {
				$("#loader_facturacion").html("Cargando...");
			},
			success: function(datos) {
				$(".outer_div_facturacion_consignacion").html(datos).fadeIn('fast');
				$("#loader_facturacion").html('');
			}
		});
	}
</script>