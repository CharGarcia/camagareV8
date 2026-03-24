<?php
//para traer la serie de la sucursal primera
$con = conenta_login();
$ruc_empresa = $_SESSION['ruc_empresa'];
$busca_sucursales = mysqli_query($con, "SELECT * FROM sucursales WHERE ruc_empresa = '" . $ruc_empresa . "' ");
$row_serie = mysqli_fetch_array($busca_sucursales);
ini_set('date.timezone', 'America/Guayaquil');
?>
<div class="modal fade" data-backdrop="static" id="detalleOrdenesMesa" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-list-alt'></i> Detalles de pedido</h4>
			</div>

			<form method="POST" id="generar_factura" name="generar_factura">
				<div class="modal-body">
					<div id="mensajes_ordenes_mesa"></div>
					<div class="well well-sm" style="margin-bottom: 0px; margin-top: -10px; ">
						<div class="form-group row">
							<input type="hidden" id="id_mesa" name="id_mesa">
							<input type="hidden" id="id_cliente_mesa" name="id_cliente_mesa">
							<input type="hidden" id="tipo_id_cliente" name="tipo_id_cliente">
							<input type="hidden" id="ruc_cliente" name="ruc_cliente">
							<input type="hidden" id="telefono_cliente" name="telefono_cliente">
							<input type="hidden" id="direccion_cliente" name="direccion_cliente">
							<input type="hidden" id="plazo_credito" name="plazo_credito">
							<input type="hidden" id="email_cliente" name="email_cliente">
							<input type="hidden" id="serie_factura_e" name="serie_factura_e" value="<?php echo $row_serie['serie']; ?>">
							<div class="col-sm-6">
								<div class="input-group">
									<span class="input-group-addon"><b>Cliente</b></span>
									<input type="text" class="form-control input-sm" id="cliente_ordenes" name="cliente_ordenes" placeholder="Agregue un cliente." title="Buscar un cliente." onkeyup='buscar_clientes();' autocomplete="off">
									<span class="input-group-btn"><button class="btn btn-info btn-sm" type="button" title="Nuevo cliente" onclick="carga_modal()" data-toggle="modal" data-target="#nuevoCliente"><span class="glyphicon glyphicon-pencil"></span></button></span>
								</div>
							</div>
							<div class="col-sm-3">
								<div class="input-group">
									<span class="input-group-addon"><b>Fecha</b></span>
									<input type="text" class="form-control input-sm" name="fecha_mesa" id="fecha_mesa" value="<?php echo date("d-m-Y"); ?>">
								</div>
							</div>
							<div class="col-sm-3">
								<div class="input-group">
									<span class="input-group-addon"><b>Mesa</b></span>
									<b><input class="form-control input-sm" id="label_nombre_mesa" readonly></b>
								</div>
							</div>
						</div>

						<div class="form-group">
							<div class="panel panel-info">
								<div class="table-responsive">
									<table class="table table-bordered">
										<tr class="info">
											<th style="padding: 2px;">Producto</th>
											<th style="padding: 2px;" class="text-center" id="titulo_bodega">Bodega</th>
											<th style="padding: 2px;" class="text-center" id="titulo_lote">Lote</th>
											<!--<th style ="padding: 2px;" class="text-center" id="titulo_caducidad">Caducidad</th>-->
											<th style="padding: 2px;" class="text-center">Cantidad</th>
											<th style="padding: 2px;" class="text-center" id="titulo_medida">Medida</th>
											<th style="padding: 2px;" class="text-center">Precio</th>
											<th style="padding: 2px;" class="text-center" id="titulo_existencia">Existencia</th>
											<th style="padding: 2px;" class="text-center">Agregar</th>
										</tr>
										<td>
											<input type="hidden" name="id_producto_mesa" id="id_producto_mesa">
											<input type="hidden" name="precio_tmp" id="precio_tmp">
											<input type="hidden" name="tipo_producto_agregar" id="tipo_producto_agregar">
											<input type="hidden" id="inventario" name="inventario">
											<input type="hidden" id="muestra_medida" name="muestra_medida">
											<input type="hidden" id="muestra_lote" name="muestra_lote">
											<input type="hidden" id="muestra_bodega" name="muestra_bodega">
											<!--<input type="hidden" id="muestra_vencimiento" name="muestra_vencimiento">-->
											<input type="text" class="form-control input-sm" id="nombre_producto_servicio" name="nombre_producto_servicio" placeholder="Producto" onkeyup="buscar_productos();">
										</td>
										<td class='col-xs-1' id="lista_bodega">
											<select class="form-control" style="text-align:right; width: auto;" title="Seleccione bodega." name="bodega_agregar" id="bodega_agregar">
												<option value="0">Seleccione</option>
												<?php
												$conexion = conenta_login();
												$sql = "SELECT * FROM bodega WHERE ruc_empresa ='" . $ruc_empresa . "'";
												$res = mysqli_query($conexion, $sql);
												while ($o = mysqli_fetch_array($res)) {
												?>
													<option value="<?php echo $o['id_bodega'] ?>" selected><?php echo strtoupper($o['nombre_bodega']) ?></option>
												<?php
												}
												?>
											</select>
										</td>
										<td class='col-xs-1' id="lista_lote">
											<select class="form-control" style="text-align:right; width: auto;" title="Seleccione lote." name="lote_agregar" id="lote_agregar">
											</select>
										</td>
										<td class='col-xs-1'>
											<div class="pull-right">
												<input type="text" class="form-control input-sm" style="text-align:right;" title="Ingrese cantidad" name="cantidad_agregar" id="cantidad_agregar" placeholder="Cantidad">
											</div>
										</td>
										<td class='col-xs-1' id="lista_medida">
											<select class="form-control input-sm" style="text-align:right; width: auto;" title="Seleccione medida." name="medida_agregar" id="medida_agregar">
											</select>
										</td>
										<td class='col-xs-2'>
											<div class="input-group">
												<input type="text" style="text-align:right; width:60%;" class="form-control input-sm" id="precio_agregar" name="precio_agregar">
												<select class="form-control" style="width:20%; height:30px;" title="Seleccione precio" name="select_precio" id="select_precio">
												</select>
											</div>
										</td>
										<td class="col-xs-1" id="lista_existencia">
											<input type="text" style="text-align:right;" class="form-control input-sm" id="existencia_producto" name="existencia_producto" placeholder="0">
										</td>
										<td class="col-sm-1" style="text-align:center;">
											<button type="button" class="btn btn-info btn-sm" title="Agregar productos" onclick="agregar_detalle_orden()"><span class="glyphicon glyphicon-plus"></span></button>
										</td>
									</table>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="outer_divdet_mesa"></div><!-- Datos ajax Final -->
				<div class="modal-footer">
					<span id="muestra_detalle_mesas"></span>
					<div class="btn-group">
						<button type="button" class="btn btn-warning btn-md dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title='Opciones de impresión'><i class='glyphicon glyphicon-print'></i> Impresiones <span class="caret"></span></button>
						<ul class="dropdown-menu" style="padding: 1px; border-radius: 2px; margin-top: 2px; text-align:center; ">
							<li><a onmouseover="this.style.color='green';" onmouseout="this.style.color='black';" onclick="imprimir_comandas('barra')" class='btn btn-default btn-xs' title='Imprimir pedidos de barra'> Barra </a></li>
							<li><a onmouseover="this.style.color='green';" onmouseout="this.style.color='black';" onclick="imprimir_comandas('cocina')" class='btn btn-default btn-xs' title='Imprimir pedidos de cocina'> Cocina </a></li>
							<li><a onmouseover="this.style.color='green';" onmouseout="this.style.color='black';" onclick="imprimir_comandas('comanda_integra')" class='btn btn-default btn-xs' title='Imprimir comanda integra'> Comanda integra </a></li>
							<li><a onmouseover="this.style.color='green';" onmouseout="this.style.color='black';" onclick="imprimir_comandas('precuenta')" class='btn btn-default btn-xs' title='Imprimir pre-cuenta del cliente'> Precuenta cliente </a></li>
						</ul>
					</div>
					<div class="btn-group">
						<button type="button" class="btn btn-default btn-md dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title='Opciones de impresión'><i class='glyphicon glyphicon-cloud-download'></i> Descargar Pdf <span class="caret"></span></button>
						<ul class="dropdown-menu" style="padding: 1px; border-radius: 2px; margin-top: 2px; text-align:center; ">
							<li><a onmouseover="this.style.color='green';" onmouseout="this.style.color='black';" href="#" onclick="document.generar_factura.action = '../pdf/pdf_cuenta_mesa.php?action=generar_orden_barra'; document.generar_factura.submit()" class='btn btn-default btn-xs' title='Imprimir pedidos de barra'> Barra </a></li>
							<li><a onmouseover="this.style.color='green';" onmouseout="this.style.color='black';" href="#" onclick="document.generar_factura.action = '../pdf/pdf_cuenta_mesa.php?action=generar_orden_cocina'; document.generar_factura.submit()" class='btn btn-default btn-xs' title='Imprimir pedidos de cocina'> Cocina </a></li>
							<li><a onmouseover="this.style.color='green';" onmouseout="this.style.color='black';" href="#" onclick="document.generar_factura.action = '../pdf/pdf_cuenta_mesa.php?action=generar_orden_integra'; document.generar_factura.submit()" class='btn btn-default btn-xs' title='Imprimir comanda integra'> Comanda integra </a></li>
							<li><a onmouseover="this.style.color='green';" onmouseout="this.style.color='black';" href="#" onclick="document.generar_factura.action = '../pdf/pdf_cuenta_mesa.php?action=generar_cuenta_mesa'; document.generar_factura.submit()" class='btn btn-default btn-xs' title='Imprimir pre-cuenta del cliente'> Precuenta cliente </a></li>
						</ul>
					</div>
					<button type="submit" class="btn btn-info" id="guardar_datos" title="Generar factura">Generar factura</button>
					<button type="button" class="btn btn-default" data-dismiss="modal" id="cerrar_detalle_mesa" title="Cerrar ventana">Cerrar</button>
				</div>
			</form>
		</div>
	</div>
</div>