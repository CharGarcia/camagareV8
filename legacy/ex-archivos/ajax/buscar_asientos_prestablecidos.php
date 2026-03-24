<?php
/* Connect To Database*/
include("../conexiones/conectalogin.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_empresa = $_SESSION['id_empresa'];
$id_usuario = $_SESSION['id_usuario'];
$fecha_registro = date("Y-m-d H:i:s");
ini_set('date.timezone', 'America/Guayaquil');
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
//para buscar detalles asientos prestablecidos 
if ($action == 'buscar_asientos_prestablecidos') {
	$tipo = $_GET['tipo'];
	
	if ($tipo == 'ventas' || $tipo == 'recibos' || $tipo == 'compras_servicios' || $tipo == 'rol_pagos' ) {
		$query_asientos_tipo = mysqli_query($con, "SELECT * FROM asientos_tipo WHERE tipo_asiento = '" . $tipo . "' ");
		?>
		<div class="panel-group" id="accordionCuentasGeneral">
			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordionCuentasGeneral" href="#CuentasGeneral"><span class="caret"></span> Opción 1. Configurar asiento general que aplica para todos los registros de <?php echo $tipo; ?></a>
				<div id="CuentasGeneral" class="panel-collapse collapse">
					<form class="form-horizontal" method="POST">
						<div class="panel panel-info">
							<div class="table-responsive">
								<table class="table">
									<tr class="info">
										<td>Descripción</td>
										<td>Detalle</td>
										<td>Tipo</td>
										<td>Código</td>
										<td>Cuenta contable</td>
										<td class="col-xs-1 text-center">Opciones</td>
									</tr>
									<?php

									//cuando no esta asignado una cuenta a cada concepto
									while ($row_detalle_tipo = mysqli_fetch_array($query_asientos_tipo)) {
										$codigo_unico = rand(100, 50000);
										$id_asiento_tipo = $row_detalle_tipo['id_asiento_tipo'];
										$concepto_cuenta = $row_detalle_tipo['concepto_cuenta'];
										$tipo_asiento = $row_detalle_tipo['tipo_asiento'];
										$tipo_saldo = $row_detalle_tipo['tipo_saldo'];
										$detalle = $row_detalle_tipo['detalle'];

										//PARA TRAER LAS CUENTAS ya guardadas
										$sql_cuentas_programadas = mysqli_query($con, "SELECT * FROM asientos_programados as ap 
										INNER JOIN plan_cuentas as pc ON pc.id_cuenta=ap.id_cuenta 
										WHERE ap.ruc_empresa = '" . $ruc_empresa . "' and ap.tipo_asiento = '" . $tipo . "' 
										and ap.id_pro_cli='" . $id_asiento_tipo . "' ");
										$row_cuenta = mysqli_fetch_array($sql_cuentas_programadas);
										$id_cuenta = isset($row_cuenta['id_cuenta']) ? $row_cuenta['id_cuenta'] : 0;
										$codigo_cuenta = isset($row_cuenta['codigo_cuenta']) ? $row_cuenta['codigo_cuenta'] : "";
										$nombre_cuenta = isset($row_cuenta['nombre_cuenta']) ? $row_cuenta['nombre_cuenta'] : "";
										$id_registro = isset($row_cuenta['id_asi_pro']) ? $row_cuenta['id_asi_pro'] : 0;
									?>
										<tr class="active">
											<input type="hidden" id="id_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta; ?>">
											<td class="col-xs-3"><?php echo mb_strtoupper($concepto_cuenta, 'UTF-8') ?></td>
											<td class="col-xs-2"><?php echo ucfirst(($detalle)) ?></td>
											<td class="col-xs-1"><?php echo ucfirst(($tipo_saldo)) ?></td>
											<td class="col-xs-2"><input type="text" id="codigo_cuenta<?php echo $codigo_unico; ?>" class="form-control input-sm" value="<?php echo $codigo_cuenta; ?>" readonly></td>
											<td class="col-xs-3">
												<input type="text" <?php echo empty($codigo_cuenta) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('<?php echo $codigo_unico; ?>','<?php echo $id_asiento_tipo; ?>','<?php echo $tipo; ?>', '<?php echo $concepto_cuenta; ?>');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta; ?>">
											</td>
											<td class="col-xs-1 text-center">
											<a href="#" class="btn btn-danger btn-xs" title="Eliminar cuenta" onclick="eliminar_cuenta('<?php echo $codigo_unico; ?>','<?php echo $id_registro; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
											</td>
										</tr>
									<?php
									}
									?>
								</table>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php
	}
	//para los iva EN VENTAS
	if ($tipo == 'ventas') {
		?>
		<div class="panel-group" id="accordionIvaVentas">
			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordionIvaVentas" href="#IvaVentas"><span class="caret"></span> Cuentas contables para tarifa de IVA diferente de 0 en ventas (Obligatoria)</a>
				<div id="IvaVentas" class="panel-collapse collapse">
					<form class="form-horizontal" method="POST">
						<div class="panel panel-info">
							<div class="table-responsive">
								<table class="table">
									<?php
									$sql_ratifa_iva = mysqli_query($con, "SELECT * FROM tarifa_iva WHERE porcentaje_iva>0 ORDER BY tarifa asc");
									while ($row = mysqli_fetch_array($sql_ratifa_iva)) {
										$codigo_unico = rand(100, 50000);
										$codigo_tarifa = $row['codigo'];
										$nombre_tarifa = "IVA " . $row['porcentaje_iva'] . "%";

										//PARA TRAER LAS CUENTAS ya guardadas
										$sql_cuentas_programadas = mysqli_query($con, "SELECT * FROM asientos_programados as ap INNER JOIN plan_cuentas as pc ON pc.id_cuenta=ap.id_cuenta WHERE ap.ruc_empresa = '" . $ruc_empresa . "' and ap.tipo_asiento = 'iva_ventas' and ap.id_pro_cli='" . $codigo_tarifa . "' ");
										$row_cuenta = mysqli_fetch_array($sql_cuentas_programadas);
										$id_cuenta = isset($row_cuenta['id_cuenta']) ? $row_cuenta['id_cuenta'] : 0;
										$codigo_cuenta = isset($row_cuenta['codigo_cuenta']) ? $row_cuenta['codigo_cuenta'] : "";
										$nombre_cuenta = isset($row_cuenta['nombre_cuenta']) ? $row_cuenta['nombre_cuenta'] : "";
										$id_registro = isset($row_cuenta['id_asi_pro']) ? $row_cuenta['id_asi_pro'] : 0;
									?>
										<tr class="active">
											<input type="hidden" id="id_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta; ?>">
											<td class="col-xs-3"><?php echo mb_strtoupper($nombre_tarifa, 'UTF-8') ?></td>
											<td class="col-xs-2">Aplica a todas las facturas de venta que tengan este porcentaje de IVA</td>
											<td class="col-xs-1">Pasivo</td>
											<td class="col-xs-2"><input type="text" class="form-control input-sm" id="codigo_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $codigo_cuenta; ?>" readonly></td>
											<td class="col-xs-3">
												<input type="text" <?php echo empty($codigo_cuenta) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('<?php echo $codigo_unico; ?>', '<?php echo $codigo_tarifa; ?>','iva_ventas', '<?php echo $nombre_tarifa; ?>');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta; ?>">
											</td>
											<td class="col-xs-1 text-center">
											<a href="#" class="btn btn-danger btn-xs" title="Eliminar cuenta" onclick="eliminar_cuenta('<?php echo $codigo_unico; ?>','<?php echo $id_registro; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
											</td>
										</tr>
									<?php
									}
									?>
								</table>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	//para los subtotales de las tarifa de iva de ventas
	if ($tipo == 'ventas') {
		?>
		<div class="panel-group" id="accordionTarifaIvaVentas">
			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordionTarifaIvaVentas" href="#TarifaIvaVentas"><span class="caret"></span> Contabilización de ventas en base a tarifas de IVA (opcional)</a>
				<div id="TarifaIvaVentas" class="panel-collapse collapse">
					<form class="form-horizontal" method="POST">
						<div class="panel panel-info">
							<div class="table-responsive">
								<table class="table">
									<tr class="info">
										<td>Subtotal Tarifa IVA en ventas</td>
										<td>Tipo</td>
										<td>Código</td>
										<td>Cuenta contable</td>
										<td class="col-xs-1 text-center">Opciones</td>
									</tr>
									<?php
									$sql_ratifa_iva = mysqli_query($con, "SELECT * FROM tarifa_iva ORDER BY tarifa asc");
									while ($row = mysqli_fetch_array($sql_ratifa_iva)) {
										$codigo_unico = rand(100, 50000);
										$codigo_tarifa = $row['codigo'];
										$nombre_tarifa = $row['tarifa'];

										//PARA TRAER LAS CUENTAS ya guardadas
										$sql_cuentas_programadas = mysqli_query($con, "SELECT * FROM asientos_programados as ap INNER JOIN plan_cuentas as pc ON pc.id_cuenta=ap.id_cuenta WHERE ap.ruc_empresa = '" . $ruc_empresa . "' and ap.tipo_asiento = 'tarifa_iva_ventas' and ap.id_pro_cli='" . $codigo_tarifa . "' ");
										$row_cuenta = mysqli_fetch_array($sql_cuentas_programadas);
										$id_cuenta = isset($row_cuenta['id_cuenta']) ? $row_cuenta['id_cuenta'] : 0;
										$codigo_cuenta = isset($row_cuenta['codigo_cuenta']) ? $row_cuenta['codigo_cuenta'] : "";
										$nombre_cuenta = isset($row_cuenta['nombre_cuenta']) ? $row_cuenta['nombre_cuenta'] : "";
										$id_registro = isset($row_cuenta['id_asi_pro']) ? $row_cuenta['id_asi_pro'] : 0;
									?>
										<tr class="active">
											<input type="hidden" id="id_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta; ?>">
											<td class="col-xs-3"><?php echo mb_strtoupper($nombre_tarifa, 'UTF-8') ?></td>
											<td class="col-xs-2">Ingreso</td>
											<td class="col-xs-2"><input type="text" class="form-control input-sm" id="codigo_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $codigo_cuenta; ?>" readonly></td>
											<td class="col-xs-4">
												<input type="text" <?php echo empty($codigo_cuenta) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('<?php echo $codigo_unico; ?>', '<?php echo $codigo_tarifa; ?>','tarifa_iva_ventas', '<?php echo $nombre_tarifa; ?>');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta; ?>">
											</td>
											<td class="col-xs-1 text-center">
											<a href="#" class="btn btn-danger btn-xs" title="Eliminar cuenta" onclick="eliminar_cuenta('<?php echo $codigo_unico; ?>','<?php echo $id_registro; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
											</td>
										</tr>
									<?php
									}
									?>
								</table>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	//para los clientes
	if ($tipo == 'ventas') {
		?>
		<div class="panel-group" id="accordionClientesVentas">
			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordionClientesVentas" href="#ClientesVentas"><span class="caret"></span> Contabilización de ventas con factura en base a clientes (opcional)</a>
				<div id="ClientesVentas" class="panel-collapse collapse">
					<form class="form-horizontal" method="POST">
						<div class="panel panel-info">
							<div class="table-responsive">
								<table class="table">
									<tr class="info">
										<td>Cliente</td>
										<td>Tipo</td>
										<td>Código</td>
										<td>Cuenta contable</td>
										<td class="col-xs-1 text-center">Opciones</td>
									</tr>
									<?php
									$query_clientes = mysqli_query($con, "SELECT DISTINCT cli.ruc as ruc_cliente, enc_fac.id_cliente as id_cliente, cli.nombre as cliente FROM encabezado_factura as enc_fac INNER JOIN clientes as cli ON cli.id=enc_fac.id_cliente WHERE enc_fac.ruc_empresa = '" . $ruc_empresa . "' group by enc_fac.id_cliente order by cli.nombre asc");
									while ($row_clientes = mysqli_fetch_array($query_clientes)) {
										$codigo_unico = rand(100, 50000);
										$id_cliente = $row_clientes['id_cliente'];
										$cliente = $row_clientes['cliente'];
										$ruc_cliente = $row_clientes['ruc_cliente'];

										//para mostrar las cuentas que ya estan guardadas
										$sql_cuentas_programadas = mysqli_query($con, "SELECT * FROM asientos_programados as ap INNER JOIN plan_cuentas as pc ON pc.id_cuenta=ap.id_cuenta WHERE ap.ruc_empresa = '" . $ruc_empresa . "' and ap.tipo_asiento = 'cliente' and ap.id_pro_cli='" . $id_cliente . "' ");
										$row_cuenta = mysqli_fetch_array($sql_cuentas_programadas);
										$id_cuenta = isset($row_cuenta['id_cuenta']) ? $row_cuenta['id_cuenta'] : 0;
										$codigo_cuenta = isset($row_cuenta['codigo_cuenta']) ? $row_cuenta['codigo_cuenta'] : "";
										$nombre_cuenta = isset($row_cuenta['nombre_cuenta']) ? $row_cuenta['nombre_cuenta'] : "";
										$id_registro = isset($row_cuenta['id_asi_pro']) ? $row_cuenta['id_asi_pro'] : 0;
									?>
										<tr class="active">
											<input type="hidden" id="id_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta; ?>">
											<td class="col-xs-4"><?php echo mb_strtoupper($cliente, 'UTF-8'); ?></td>
											<td class="col-xs-1">Ingreso</td>
											<td class="col-xs-2"><input type="text" class="form-control input-sm" id="codigo_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $codigo_cuenta; ?>" readonly></td>
											<td class="col-xs-4">
												<input type="text" <?php echo empty($codigo_cuenta) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('<?php echo $codigo_unico; ?>', '<?php echo $id_cliente; ?>','cliente', '<?php echo $ruc_cliente; ?>');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta; ?>">
											</td>
											<td class="col-xs-1 text-center">
											<a href="#" class="btn btn-danger btn-xs" title="Eliminar cuenta" onclick="eliminar_cuenta('<?php echo $codigo_unico; ?>','<?php echo $id_registro; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
											</td>
										</tr>
									<?php
									}
									?>
								</table>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	//para las ventas por grupos de familias o categorias
	if ($tipo == 'ventas') {
		?>
		<div class="panel-group" id="accordionCategoriasVentasFacturas">
			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordionCategoriasVentasFacturas" href="#CategoriasVentasFacturas"><span class="caret"></span> Contabilización de ventas con factura por grupos de familias o categorías (opcional)</a>
				<div id="CategoriasVentasFacturas" class="panel-collapse collapse">
					<form class="form-horizontal" method="POST">
						<div class="panel panel-info">
							<div class="table-responsive">
								<table class="table">
									<tr class="info">
										<td>Categorías</td>
										<td>Tipo</td>
										<td>Código</td>
										<td>Cuenta contable</td>
										<td class="col-xs-1 text-center">Opciones</td>
									</tr>
									<?php
									$sql_categoria_venta = mysqli_query($con, "SELECT id_grupo, nombre_grupo FROM grupo_familiar_producto WHERE ruc_empresa = '" . $ruc_empresa . "' order by nombre_grupo asc");
									while ($row_categoria_venta = mysqli_fetch_array($sql_categoria_venta)) {
										$codigo_unico = rand(100, 50000);
										$id_grupo = $row_categoria_venta['id_grupo'];
										$nombre_grupo = $row_categoria_venta['nombre_grupo'];

										//para mostrar las cuentas que ya estan guardadas
										$sql_cuentas_programadas = mysqli_query($con, "SELECT * FROM asientos_programados as ap INNER JOIN plan_cuentas as pc ON pc.id_cuenta=ap.id_cuenta WHERE ap.ruc_empresa = '" . $ruc_empresa . "' and ap.tipo_asiento = 'ventascategoriasfacturas' and ap.id_pro_cli='" . $id_grupo . "' ");
										$row_cuenta = mysqli_fetch_array($sql_cuentas_programadas);
										$id_cuenta = isset($row_cuenta['id_cuenta']) ? $row_cuenta['id_cuenta'] : 0;
										$codigo_cuenta = isset($row_cuenta['codigo_cuenta']) ? $row_cuenta['codigo_cuenta'] : "";
										$nombre_cuenta = isset($row_cuenta['nombre_cuenta']) ? $row_cuenta['nombre_cuenta'] : "";
										$id_registro = isset($row_cuenta['id_asi_pro']) ? $row_cuenta['id_asi_pro'] : 0;

									?>
										<tr class="active">
											<input type="hidden" id="id_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta; ?>">
											<td class="col-xs-2"><?php echo mb_strtoupper($nombre_grupo, 'UTF-8') ?></td>
											<td class="col-xs-1">Ingreso</td>
											<td class="col-xs-1"><input type="text" class="form-control input-sm" id="codigo_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $codigo_cuenta; ?>" readonly></td>
											<td class="col-xs-4">
												<input type="text" <?php echo empty($codigo_cuenta) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('<?php echo $codigo_unico; ?>', '<?php echo $id_grupo; ?>','ventascategoriasfacturas', '<?php echo $id_grupo; ?>');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta; ?>">
											</td>
											<td class="col-xs-1 text-center">
											<a href="#" class="btn btn-danger btn-xs" title="Eliminar cuenta" onclick="eliminar_cuenta('<?php echo $codigo_unico; ?>','<?php echo $id_registro; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
											</td>
										</tr>
									<?php
									}
									?>
								</table>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	//para las ventas por marcas
	if ($tipo == 'ventas') {
		?>
		<div class="panel-group" id="accordionMarcasVentasFacturas">
			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordionMarcasVentasFacturas" href="#MarcasVentasFacturas"><span class="caret"></span> Contabilización de ventas con factura por marcas de productos (opcional)</a>
				<div id="MarcasVentasFacturas" class="panel-collapse collapse">
					<form class="form-horizontal" method="POST">
						<div class="panel panel-info">
							<div class="table-responsive">
								<table class="table">
									<tr class="info">
										<td>Marca</td>
										<td>Tipo</td>
										<td>Código</td>
										<td>Cuenta contable</td>
										<td class="col-xs-1 text-center">Opciones</td>
									</tr>
									<?php
									$sql_categoria_venta = mysqli_query($con, "SELECT id_marca, nombre_marca FROM marca WHERE ruc_empresa = '" . $ruc_empresa . "' order by nombre_marca asc");
									while ($row_categoria_venta = mysqli_fetch_array($sql_categoria_venta)) {
										$codigo_unico = rand(100, 50000);
										$id_marca = $row_categoria_venta['id_marca'];
										$nombre_marca = $row_categoria_venta['nombre_marca'];

										//para mostrar las cuentas que ya estan guardadas
										$sql_cuentas_programadas = mysqli_query($con, "SELECT * FROM asientos_programados as ap INNER JOIN plan_cuentas as pc ON pc.id_cuenta=ap.id_cuenta WHERE ap.ruc_empresa = '" . $ruc_empresa . "' and ap.tipo_asiento = 'ventasmarcasfacturas' and ap.id_pro_cli='" . $id_marca . "' ");
										$row_cuenta = mysqli_fetch_array($sql_cuentas_programadas);
										$id_cuenta = isset($row_cuenta['id_cuenta']) ? $row_cuenta['id_cuenta'] : 0;
										$codigo_cuenta = isset($row_cuenta['codigo_cuenta']) ? $row_cuenta['codigo_cuenta'] : "";
										$nombre_cuenta = isset($row_cuenta['nombre_cuenta']) ? $row_cuenta['nombre_cuenta'] : "";
										$id_registro = isset($row_cuenta['id_asi_pro']) ? $row_cuenta['id_asi_pro'] : 0;

									?>
										<tr class="active">
											<input type="hidden" id="id_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta; ?>">
											<td class="col-xs-2"><?php echo mb_strtoupper($nombre_marca, 'UTF-8') ?></td>
											<td class="col-xs-1">Ingreso</td>
											<td class="col-xs-1"><input type="text" class="form-control input-sm" id="codigo_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $codigo_cuenta; ?>" readonly></td>
											<td class="col-xs-4">
												<input type="text" <?php echo empty($codigo_cuenta) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('<?php echo $codigo_unico; ?>', '<?php echo $id_marca; ?>','ventasmarcasfacturas', '<?php echo $id_marca; ?>');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta; ?>">
											</td>
											<td class="col-xs-1 text-center">
											<a href="#" class="btn btn-danger btn-xs" title="Eliminar cuenta" onclick="eliminar_cuenta('<?php echo $codigo_unico; ?>','<?php echo $id_registro; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
											</td>
										</tr>
									<?php
									}
									?>
								</table>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	//para los productos con factura
	if ($tipo == 'ventas') {
		?>
		<div class="panel-group" id="accordionProductosVentasFactura">
			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordionProductosVentasFactura" href="#ProductosVentasFactura"><span class="caret"></span> Contabilización de ventas con factura en base a productos (opcional)</a>
				<div id="ProductosVentasFactura" class="panel-collapse collapse">
					<form class="form-horizontal" method="POST">
						<div class="panel panel-info">
							<div class="table-responsive">
								<table class="table">
									<tr class="info">
										<td>Producto/Servicio</td>
										<td>Tipo</td>
										<td>Código</td>
										<td>Cuenta contable</td>
										<td class="col-xs-1 text-center">Opciones</td>
									</tr>
									<?php
									$query_productos = mysqli_query($con, "SELECT DISTINCT pro.id as id_producto, pro.codigo_producto as codigo_producto, pro.nombre_producto as nombre_producto FROM productos_servicios as pro INNER JOIN cuerpo_factura as cue ON cue.id_producto=pro.id WHERE pro.ruc_empresa = '" . $ruc_empresa . "' order by pro.nombre_producto asc");
									while ($row_productos = mysqli_fetch_array($query_productos)) {
										$codigo_unico = rand(100, 50000);
										$id_producto = $row_productos['id_producto'];
										$nombre_producto = $row_productos['nombre_producto'];
										$codigo_producto = $row_productos['codigo_producto'];

										//para mostrar las cuentas que ya estan guardadas
										$sql_cuentas_programadas = mysqli_query($con, "SELECT * FROM asientos_programados as ap INNER JOIN plan_cuentas as pc ON pc.id_cuenta=ap.id_cuenta WHERE ap.ruc_empresa = '" . $ruc_empresa . "' and ap.tipo_asiento = 'productoventafactura' and ap.id_pro_cli='" . $id_producto . "' ");
										$row_cuenta = mysqli_fetch_array($sql_cuentas_programadas);
										$id_cuenta = isset($row_cuenta['id_cuenta']) ? $row_cuenta['id_cuenta'] : 0;
										$codigo_cuenta = isset($row_cuenta['codigo_cuenta']) ? $row_cuenta['codigo_cuenta'] : "";
										$nombre_cuenta = isset($row_cuenta['nombre_cuenta']) ? $row_cuenta['nombre_cuenta'] : "";
										$id_registro = isset($row_cuenta['id_asi_pro']) ? $row_cuenta['id_asi_pro'] : 0;
									?>
										<tr class="active">
											<input type="hidden" id="id_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta; ?>">
											<td class="col-xs-4"><?php echo mb_strtoupper($nombre_producto, 'UTF-8'); ?></td>
											<td class="col-xs-1">Ingreso</td>
											<td class="col-xs-2"><input type="text" class="form-control input-sm" id="codigo_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $codigo_cuenta; ?>" readonly></td>
											<td class="col-xs-4">
												<input type="text" <?php echo empty($codigo_cuenta) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('<?php echo $codigo_unico; ?>', '<?php echo $id_producto; ?>','productoventafactura', '<?php echo $codigo_producto; ?>');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta; ?>">
											</td>
											<td class="col-xs-1 text-center">
											<a href="#" class="btn btn-danger btn-xs" title="Eliminar cuenta" onclick="eliminar_cuenta('<?php echo $codigo_unico; ?>','<?php echo $id_registro; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
											</td>
										</tr>
									<?php
									}
									?>
								</table>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	//para los iva EN VENTAS con recibos
	if ($tipo == 'recibos') {
		?>
		<div class="panel-group" id="accordionIvaVentasRecibos">
			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordionIvaVentas" href="#IvaVentasRecibos"><span class="caret"></span> Cuentas contables para tarifa de IVA diferente de 0 en ventas con recibos (Obligatorio)</a>
				<div id="IvaVentasRecibos" class="panel-collapse collapse">
					<form class="form-horizontal" method="POST">
						<div class="panel panel-info">
							<div class="table-responsive">
								<table class="table">
									<?php
									$sql_ratifa_iva = mysqli_query($con, "SELECT * FROM tarifa_iva WHERE porcentaje_iva>0 ORDER BY tarifa asc");
									while ($row = mysqli_fetch_array($sql_ratifa_iva)) {
										$codigo_unico = rand(100, 50000);
										$codigo_tarifa = $row['codigo'];
										$nombre_tarifa = "IVA " . $row['porcentaje_iva'] . "%";

										//PARA TRAER LAS CUENTAS ya guardadas
										$sql_cuentas_programadas = mysqli_query($con, "SELECT * FROM asientos_programados as ap INNER JOIN plan_cuentas as pc ON pc.id_cuenta=ap.id_cuenta WHERE ap.ruc_empresa = '" . $ruc_empresa . "' and ap.tipo_asiento = 'iva_ventas_recibos' and ap.id_pro_cli='" . $codigo_tarifa . "' ");
										$row_cuenta = mysqli_fetch_array($sql_cuentas_programadas);
										$id_cuenta = isset($row_cuenta['id_cuenta']) ? $row_cuenta['id_cuenta'] : 0;
										$codigo_cuenta = isset($row_cuenta['codigo_cuenta']) ? $row_cuenta['codigo_cuenta'] : "";
										$nombre_cuenta = isset($row_cuenta['nombre_cuenta']) ? $row_cuenta['nombre_cuenta'] : "";
										$id_registro = isset($row_cuenta['id_asi_pro']) ? $row_cuenta['id_asi_pro'] : 0;
									?>
										<tr class="active">
											<input type="hidden" id="id_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta; ?>">
											<td class="col-xs-3"><?php echo mb_strtoupper($nombre_tarifa, 'UTF-8') ?></td>
											<td class="col-xs-2">Aplica a todas los recibos de venta que tengan este porcentaje de IVA</td>
											<td class="col-xs-1">Pasivo</td>
											<td class="col-xs-2"><input type="text" class="form-control input-sm" id="codigo_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $codigo_cuenta; ?>" readonly></td>
											<td class="col-xs-3">
												<input type="text" <?php echo empty($codigo_cuenta) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('<?php echo $codigo_unico; ?>', '<?php echo $codigo_tarifa; ?>','iva_ventas_recibos', '<?php echo $nombre_tarifa; ?>');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta; ?>">
											</td>
											<td class="col-xs-1 text-center">
											<a href="#" class="btn btn-danger btn-xs" title="Eliminar cuenta" onclick="eliminar_cuenta('<?php echo $codigo_unico; ?>','<?php echo $id_registro; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
											</td>
										</tr>
									<?php
									}
									?>
								</table>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	//para los subtotales de las tarifa de iva de ventas con recibos
	if ($tipo == 'recibos') {
		?>
		<div class="panel-group" id="accordionTarifaIvaVentasRecibos">
			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordionTarifaIvaVentasRecibos" href="#TarifaIvaVentasRecibos"><span class="caret"></span> Contabilización de ventas con recibos en base a tarifas de IVA (opcional)</a>
				<div id="TarifaIvaVentasRecibos" class="panel-collapse collapse">
					<form class="form-horizontal" method="POST">
						<div class="panel panel-info">
							<div class="table-responsive">
								<table class="table">
									<tr class="info">
										<td>Subtotal Tarifa IVA en ventas</td>
										<td>Tipo</td>
										<td>Código</td>
										<td>Cuenta contable</td>
										<td class="col-xs-1 text-center">Opciones</td>
									</tr>
									<?php
									$sql_ratifa_iva = mysqli_query($con, "SELECT * FROM tarifa_iva ORDER BY tarifa asc");
									while ($row = mysqli_fetch_array($sql_ratifa_iva)) {
										$codigo_unico = rand(100, 50000);
										$codigo_tarifa = $row['codigo'];
										$nombre_tarifa = $row['tarifa'];

										//PARA TRAER LAS CUENTAS ya guardadas
										$sql_cuentas_programadas = mysqli_query($con, "SELECT * FROM asientos_programados as ap INNER JOIN plan_cuentas as pc ON pc.id_cuenta=ap.id_cuenta WHERE ap.ruc_empresa = '" . $ruc_empresa . "' and ap.tipo_asiento = 'tarifa_iva_ventas_recibos' and ap.id_pro_cli='" . $codigo_tarifa . "' ");
										$row_cuenta = mysqli_fetch_array($sql_cuentas_programadas);
										$id_cuenta = isset($row_cuenta['id_cuenta']) ? $row_cuenta['id_cuenta'] : 0;
										$codigo_cuenta = isset($row_cuenta['codigo_cuenta']) ? $row_cuenta['codigo_cuenta'] : "";
										$nombre_cuenta = isset($row_cuenta['nombre_cuenta']) ? $row_cuenta['nombre_cuenta'] : "";
										$id_registro = isset($row_cuenta['id_asi_pro']) ? $row_cuenta['id_asi_pro'] : 0;
									?>
										<tr class="active">
											<input type="hidden" id="id_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta; ?>">
											<td class="col-xs-3"><?php echo mb_strtoupper($nombre_tarifa, 'UTF-8') ?></td>
											<td class="col-xs-2">Ingreso</td>
											<td class="col-xs-2"><input type="text" class="form-control input-sm" id="codigo_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $codigo_cuenta; ?>" readonly></td>
											<td class="col-xs-4"><input type="text" <?php echo empty($codigo_cuenta) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('<?php echo $codigo_unico; ?>', '<?php echo $codigo_tarifa; ?>','tarifa_iva_ventas_recibos', '<?php echo $nombre_tarifa; ?>');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta; ?>"></td>
											<td class="col-xs-1 text-center">
											<a href="#" class="btn btn-danger btn-xs" title="Eliminar cuenta" onclick="eliminar_cuenta('<?php echo $codigo_unico; ?>','<?php echo $id_registro; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
											</td>
										</tr>
									<?php
									}
									?>
								</table>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	//para los clientes DE RECIBOS
	if ($tipo == 'recibos') {
		?>
		<div class="panel-group" id="accordionClientesVentasRecibos">
			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordionClientesVentasRecibos" href="#ClientesVentasRecibos"><span class="caret"></span> Contabilización de ventas con recibos en base a clientes (opcional)</a>
				<div id="ClientesVentasRecibos" class="panel-collapse collapse">
					<form class="form-horizontal" method="POST">
						<div class="panel panel-info">
							<div class="table-responsive">
								<table class="table">
									<tr class="info">
										<td>Cliente</td>
										<td>Tipo</td>
										<td>Código</td>
										<td>Cuenta contable</td>
										<td class="col-xs-1 text-center">Opciones</td>
									</tr>
									<?php
									$query_clientes = mysqli_query($con, "SELECT DISTINCT cli.ruc as ruc_cliente, enc_rec.id_cliente as id_cliente, cli.nombre as cliente FROM encabezado_recibo as enc_rec INNER JOIN clientes as cli ON cli.id=enc_rec.id_cliente WHERE enc_rec.ruc_empresa = '" . $ruc_empresa . "' group by enc_rec.id_cliente order by cli.nombre asc");
									while ($row_clientes = mysqli_fetch_array($query_clientes)) {
										$codigo_unico = rand(100, 50000);
										$id_cliente = $row_clientes['id_cliente'];
										$cliente = $row_clientes['cliente'];
										$ruc_cliente = $row_clientes['ruc_cliente'];

										//para mostrar las cuentas que ya estan guardadas
										$sql_cuentas_programadas = mysqli_query($con, "SELECT * FROM asientos_programados as ap INNER JOIN plan_cuentas as pc ON pc.id_cuenta=ap.id_cuenta WHERE ap.ruc_empresa = '" . $ruc_empresa . "' and ap.tipo_asiento = 'clienteRecibo' and ap.id_pro_cli='" . $id_cliente . "' ");
										$row_cuenta = mysqli_fetch_array($sql_cuentas_programadas);
										$id_cuenta = isset($row_cuenta['id_cuenta']) ? $row_cuenta['id_cuenta'] : 0;
										$codigo_cuenta = isset($row_cuenta['codigo_cuenta']) ? $row_cuenta['codigo_cuenta'] : "";
										$nombre_cuenta = isset($row_cuenta['nombre_cuenta']) ? $row_cuenta['nombre_cuenta'] : "";
										$id_registro = isset($row_cuenta['id_asi_pro']) ? $row_cuenta['id_asi_pro'] : 0;
									?>
										<tr class="active">
											<input type="hidden" id="id_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta; ?>">
											<td class="col-xs-4"><?php echo mb_strtoupper($cliente, 'UTF-8'); ?></td>
											<td class="col-xs-1">Ingreso</td>
											<td class="col-xs-2"><input type="text" class="form-control input-sm" id="codigo_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $codigo_cuenta; ?>" readonly></td>
											<td class="col-xs-4">
												<input type="text" <?php echo empty($codigo_cuenta) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('<?php echo $codigo_unico; ?>', '<?php echo $id_cliente; ?>','clienteRecibo', '<?php echo $ruc_cliente; ?>');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta; ?>">
											</td>
											<td class="col-xs-1 text-center">
											<a href="#" class="btn btn-danger btn-xs" title="Eliminar cuenta" onclick="eliminar_cuenta('<?php echo $codigo_unico; ?>','<?php echo $id_registro; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
											</td>
										</tr>
									<?php
									}
									?>
								</table>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	//para las ventas con recibos por grupos de familias o categorias
	if ($tipo == 'recibos') {
		?>
		<div class="panel-group" id="accordionCategoriasVentasRecibos">
			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordionCategoriasVentasRecibos" href="#CategoriasVentasRecibos"><span class="caret"></span> Contabilización de ventas con recibos por grupos de familias o categorías (opcional)</a>
				<div id="CategoriasVentasRecibos" class="panel-collapse collapse">
					<form class="form-horizontal" method="POST">
						<div class="panel panel-info">
							<div class="table-responsive">
								<table class="table">
									<tr class="info">
										<td>Categorías</td>
										<td>Tipo</td>
										<td>Código</td>
										<td>Cuenta contable</td>
										<td class="col-xs-1 text-center">Opciones</td>
									</tr>
									<?php
									$sql_categoria_venta = mysqli_query($con, "SELECT id_grupo, nombre_grupo FROM grupo_familiar_producto WHERE ruc_empresa = '" . $ruc_empresa . "' order by nombre_grupo asc");
									while ($row_categoria_venta = mysqli_fetch_array($sql_categoria_venta)) {
										$codigo_unico = rand(100, 50000);
										$id_grupo = $row_categoria_venta['id_grupo'];
										$nombre_grupo = $row_categoria_venta['nombre_grupo'];

										//para mostrar las cuentas que ya estan guardadas
										$sql_cuentas_programadas = mysqli_query($con, "SELECT * FROM asientos_programados as ap INNER JOIN plan_cuentas as pc ON pc.id_cuenta=ap.id_cuenta WHERE ap.ruc_empresa = '" . $ruc_empresa . "' and ap.tipo_asiento = 'ventascategoriasrecibos' and ap.id_pro_cli='" . $id_grupo . "' ");
										$row_cuenta = mysqli_fetch_array($sql_cuentas_programadas);
										$id_cuenta = isset($row_cuenta['id_cuenta']) ? $row_cuenta['id_cuenta'] : 0;
										$codigo_cuenta = isset($row_cuenta['codigo_cuenta']) ? $row_cuenta['codigo_cuenta'] : "";
										$nombre_cuenta = isset($row_cuenta['nombre_cuenta']) ? $row_cuenta['nombre_cuenta'] : "";
										$id_registro = isset($row_cuenta['id_asi_pro']) ? $row_cuenta['id_asi_pro'] : 0;
									?>
										<tr class="active">
											<input type="hidden" id="id_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta; ?>">
											<td class="col-xs-2"><?php echo mb_strtoupper($nombre_grupo, 'UTF-8') ?></td>
											<td class="col-xs-1">Ingreso</td>
											<td class="col-xs-1"><input type="text" class="form-control input-sm" id="codigo_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $codigo_cuenta; ?>" readonly></td>
											<td class="col-xs-4">
												<input type="text" <?php echo empty($codigo_cuenta) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('<?php echo $codigo_unico; ?>', '<?php echo $id_grupo; ?>','ventascategoriasrecibos', '<?php echo $id_grupo; ?>');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta; ?>">
											</td>
											<td class="col-xs-1 text-center">
											<a href="#" class="btn btn-danger btn-xs" title="Eliminar cuenta" onclick="eliminar_cuenta('<?php echo $codigo_unico; ?>','<?php echo $id_registro; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
											</td>
										</tr>
									<?php
									}
									?>
								</table>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	//para las ventas por marcas con recibos
	if ($tipo == 'recibos') {
		?>
		<div class="panel-group" id="accordionMarcasVentasRecibos">
			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordionMarcasVentasRecibos" href="#MarcasVentasRecibos"><span class="caret"></span> Contabilización de ventas con recibos por marcas de productos (opcional)</a>
				<div id="MarcasVentasRecibos" class="panel-collapse collapse">
					<form class="form-horizontal" method="POST">
						<div class="panel panel-info">
							<div class="table-responsive">
								<table class="table">
									<tr class="info">
										<td>Marca</td>
										<td>Tipo</td>
										<td>Código</td>
										<td>Cuenta contable</td>
										<td class="col-xs-1 text-center">Opciones</td>
									</tr>
									<?php
									$sql_categoria_venta = mysqli_query($con, "SELECT id_marca, nombre_marca FROM marca WHERE ruc_empresa = '" . $ruc_empresa . "' order by nombre_marca asc");
									while ($row_categoria_venta = mysqli_fetch_array($sql_categoria_venta)) {
										$codigo_unico = rand(100, 50000);
										$id_marca = $row_categoria_venta['id_marca'];
										$nombre_marca = $row_categoria_venta['nombre_marca'];

										//para mostrar las cuentas que ya estan guardadas
										$sql_cuentas_programadas = mysqli_query($con, "SELECT * FROM asientos_programados as ap INNER JOIN plan_cuentas as pc ON pc.id_cuenta=ap.id_cuenta WHERE ap.ruc_empresa = '" . $ruc_empresa . "' and ap.tipo_asiento = 'ventasmarcasrecibos' and ap.id_pro_cli='" . $id_marca . "' ");
										$row_cuenta = mysqli_fetch_array($sql_cuentas_programadas);
										$id_cuenta = isset($row_cuenta['id_cuenta']) ? $row_cuenta['id_cuenta'] : 0;
										$codigo_cuenta = isset($row_cuenta['codigo_cuenta']) ? $row_cuenta['codigo_cuenta'] : "";
										$nombre_cuenta = isset($row_cuenta['nombre_cuenta']) ? $row_cuenta['nombre_cuenta'] : "";
										$id_registro = isset($row_cuenta['id_asi_pro']) ? $row_cuenta['id_asi_pro'] : 0;
									?>
										<tr class="active">
											<input type="hidden" id="id_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta; ?>">
											<td class="col-xs-2"><?php echo mb_strtoupper($nombre_marca, 'UTF-8') ?></td>
											<td class="col-xs-1">Ingreso</td>
											<td class="col-xs-1"><input type="text" class="form-control input-sm" id="codigo_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $codigo_cuenta; ?>" readonly></td>
											<td class="col-xs-4">
												<input type="text" <?php echo empty($codigo_cuenta) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('<?php echo $codigo_unico; ?>', '<?php echo $id_marca; ?>','ventasmarcasrecibos', '<?php echo $id_marca; ?>');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta; ?>">
											</td>
											<td class="col-xs-1 text-center">
											<a href="#" class="btn btn-danger btn-xs" title="Eliminar cuenta" onclick="eliminar_cuenta('<?php echo $codigo_unico; ?>','<?php echo $id_registro; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
											</td>
										</tr>
									<?php
									}
									?>
								</table>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	//para los productos con recibos
	if ($tipo == 'recibos') {
		?>
		<div class="panel-group" id="accordionProductosVentasRecibo">
			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordionProductosVentasRecibo" href="#ProductosVentasRecibo"><span class="caret"></span> Contabilización de ventas con recibo en base a productos (opcional)</a>
				<div id="ProductosVentasRecibo" class="panel-collapse collapse">
					<form class="form-horizontal" method="POST">
						<div class="panel panel-info">
							<div class="table-responsive">
								<table class="table">
									<tr class="info">
										<td>Producto/Servicio</td>
										<td>Tipo</td>
										<td>Código</td>
										<td>Cuenta contable</td>
										<td class="col-xs-1 text-center">Opciones</td>
									</tr>
									<?php
									$query_productos_recibos = mysqli_query($con, "SELECT DISTINCT pro.id as id_producto, pro.codigo_producto as codigo_producto, pro.nombre_producto as nombre_producto FROM productos_servicios as pro INNER JOIN cuerpo_recibo as cue ON cue.id_producto=pro.id WHERE pro.ruc_empresa = '" . $ruc_empresa . "' order by pro.nombre_producto asc");
									while ($row_productos = mysqli_fetch_array($query_productos_recibos)) {
										$codigo_unico = rand(100, 50000);
										$id_producto = $row_productos['id_producto'];
										$nombre_producto = $row_productos['nombre_producto'];
										$codigo_producto = $row_productos['codigo_producto'];

										//para mostrar las cuentas que ya estan guardadas
										$sql_cuentas_programadas = mysqli_query($con, "SELECT * FROM asientos_programados as ap INNER JOIN plan_cuentas as pc ON pc.id_cuenta=ap.id_cuenta WHERE ap.ruc_empresa = '" . $ruc_empresa . "' and ap.tipo_asiento = 'productoventarecibo' and ap.id_pro_cli='" . $id_producto . "' ");
										$row_cuenta = mysqli_fetch_array($sql_cuentas_programadas);
										$id_cuenta = isset($row_cuenta['id_cuenta']) ? $row_cuenta['id_cuenta'] : 0;
										$codigo_cuenta = isset($row_cuenta['codigo_cuenta']) ? $row_cuenta['codigo_cuenta'] : "";
										$nombre_cuenta = isset($row_cuenta['nombre_cuenta']) ? $row_cuenta['nombre_cuenta'] : "";
										$id_registro = isset($row_cuenta['id_asi_pro']) ? $row_cuenta['id_asi_pro'] : 0;
									?>
										<tr class="active">
											<input type="hidden" id="id_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta; ?>">
											<td class="col-xs-4"><?php echo mb_strtoupper($nombre_producto, 'UTF-8'); ?></td>
											<td class="col-xs-1">Ingreso</td>
											<td class="col-xs-2"><input type="text" class="form-control input-sm" id="codigo_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $codigo_cuenta; ?>" readonly></td>
											<td class="col-xs-4">
												<input type="text" <?php echo empty($codigo_cuenta) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('<?php echo $codigo_unico; ?>', '<?php echo $id_producto; ?>','productoventarecibo', '<?php echo $codigo_producto; ?>');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta; ?>">
											</td>
											<td class="col-xs-1 text-center">
											<a href="#" class="btn btn-danger btn-xs" title="Eliminar cuenta" onclick="eliminar_cuenta('<?php echo $codigo_unico; ?>','<?php echo $id_registro; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
											</td>
										</tr>
									<?php
									}
									?>
								</table>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	//para los iva EN compras
	if ($tipo == 'compras_servicios') {
		?>
		<div class="panel-group" id="accordionIvaCompras">
			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordionIvaCompras" href="#IvaCompras"><span class="caret"></span> Cuentas contables para tarifa de IVA diferente de 0 en compras (Obligatorio)</a>
				<div id="IvaCompras" class="panel-collapse collapse">
					<form class="form-horizontal" method="POST">
						<div class="panel panel-info">
							<div class="table-responsive">
								<table class="table">
									<?php
									$sql_ratifa_iva = mysqli_query($con, "SELECT * FROM tarifa_iva WHERE porcentaje_iva>0 ORDER BY tarifa asc");
									while ($row = mysqli_fetch_array($sql_ratifa_iva)) {
										$codigo_unico = rand(100, 50000);
										$codigo_tarifa = $row['codigo'];
										$nombre_tarifa = "IVA " . $row['porcentaje_iva'] . "% en compras";

										//PARA TRAER LAS CUENTAS ya guardadas
										$sql_cuentas_programadas = mysqli_query($con, "SELECT * FROM asientos_programados as ap INNER JOIN plan_cuentas as pc ON pc.id_cuenta=ap.id_cuenta WHERE ap.ruc_empresa = '" . $ruc_empresa . "' and ap.tipo_asiento = 'iva_compras' and ap.id_pro_cli='" . $codigo_tarifa . "' ");
										$row_cuenta = mysqli_fetch_array($sql_cuentas_programadas);
										$id_cuenta = isset($row_cuenta['id_cuenta']) ? $row_cuenta['id_cuenta'] : 0;
										$codigo_cuenta = isset($row_cuenta['codigo_cuenta']) ? $row_cuenta['codigo_cuenta'] : "";
										$nombre_cuenta = isset($row_cuenta['nombre_cuenta']) ? $row_cuenta['nombre_cuenta'] : "";
										$id_registro = isset($row_cuenta['id_asi_pro']) ? $row_cuenta['id_asi_pro'] : 0;
									?>
										<tr class="active">
											<input type="hidden" id="id_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta; ?>">
											<td class="col-xs-3"><?php echo mb_strtoupper($nombre_tarifa, 'UTF-8') ?></td>
											<td class="col-xs-2">Cuenta de Iva en general para todas las compras con este porcentaje.</td>
											<td class="col-xs-1">Activo</td>
											<td class="col-xs-2"><input type="text" class="form-control input-sm" id="codigo_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $codigo_cuenta; ?>" readonly></td>
											<td class="col-xs-3">
												<input type="text" <?php echo empty($codigo_cuenta) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('<?php echo $codigo_unico; ?>', '<?php echo $codigo_tarifa; ?>','iva_compras', '<?php echo $nombre_tarifa; ?>');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta; ?>">
											</td>
											<td class="col-xs-1 text-center">
											<a href="#" class="btn btn-danger btn-xs" title="Eliminar cuenta" onclick="eliminar_cuenta('<?php echo $codigo_unico; ?>','<?php echo $id_registro; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
											</td>
										</tr>
									<?php
									}
									?>
								</table>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	//para los subtotales de las tarifa de iva de compras
	if ($tipo == 'compras_servicios') {
		?>
		<div class="panel-group" id="accordionTarifaIvaCompras">
			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordionTarifaIvaCompras" href="#TarifaIvaCompras"><span class="caret"></span> Contabilización de adquisiciones en base a tarifas de IVA (opcional)</a>
				<div id="TarifaIvaCompras" class="panel-collapse collapse">
					<form class="form-horizontal" method="POST">
						<div class="panel panel-info">
							<div class="table-responsive">
								<table class="table">
									<tr class="info">
										<td>Subtotal Tarifa IVA en compras</td>
										<td>Detalle</td>
										<td>Tipo</td>
										<td>Código</td>
										<td>Cuenta contable</td>
										<td class="col-xs-1 text-center">Opciones</td>
									</tr>
									<?php
									$sql_ratifa_iva = mysqli_query($con, "SELECT * FROM tarifa_iva ORDER BY tarifa asc");
									while ($row = mysqli_fetch_array($sql_ratifa_iva)) {
										$codigo_unico = rand(100, 50000);
										$codigo_tarifa = $row['codigo'];
										$nombre_tarifa = $row['tarifa'];

										//PARA TRAER LAS CUENTAS ya guardadas
										$sql_cuentas_programadas = mysqli_query($con, "SELECT * FROM asientos_programados as ap INNER JOIN plan_cuentas as pc ON pc.id_cuenta=ap.id_cuenta WHERE ap.ruc_empresa = '" . $ruc_empresa . "' and ap.tipo_asiento = 'tarifa_iva_compras' and ap.id_pro_cli='" . $codigo_tarifa . "' ");
										$row_cuenta = mysqli_fetch_array($sql_cuentas_programadas);
										$id_cuenta = isset($row_cuenta['id_cuenta']) ? $row_cuenta['id_cuenta'] : 0;
										$codigo_cuenta = isset($row_cuenta['codigo_cuenta']) ? $row_cuenta['codigo_cuenta'] : "";
										$nombre_cuenta = isset($row_cuenta['nombre_cuenta']) ? $row_cuenta['nombre_cuenta'] : "";
										$id_registro = isset($row_cuenta['id_asi_pro']) ? $row_cuenta['id_asi_pro'] : 0;
									?>
										<tr class="active">
											<input type="hidden" id="id_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta; ?>">
											<td class="col-xs-3"><?php echo mb_strtoupper($nombre_tarifa, 'UTF-8') ?></td>
											<td class="col-xs-2">Subtotal de tarifa IVA que aplica a cada factura de compra.</td>
											<td class="col-xs-1">Ingreso</td>
											<td class="col-xs-2"><input type="text" class="form-control input-sm" id="codigo_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $codigo_cuenta; ?>" readonly></td>
											<td class="col-xs-3">
												<input type="text" <?php echo empty($codigo_cuenta) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('<?php echo $codigo_unico; ?>', '<?php echo $codigo_tarifa; ?>','tarifa_iva_compras', '<?php echo $nombre_tarifa; ?>');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta; ?>">
											</td>
											<td class="col-xs-1 text-center">
											<a href="#" class="btn btn-danger btn-xs" title="Eliminar cuenta" onclick="eliminar_cuenta('<?php echo $codigo_unico; ?>','<?php echo $id_registro; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
											</td>
										</tr>
									<?php
									}
									?>
								</table>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	//para los proveedores
	if ($tipo == 'compras_servicios') {
		?>
		<div class="panel-group" id="accordionProveedoresCompras">
			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordionProveedoresCompras" href="#ProveedoresCompras"><span class="caret"></span> Contabilización de adquisiciones en base a proveedores (opcional)</a>
				<div id="ProveedoresCompras" class="panel-collapse collapse">
					<form class="form-horizontal" method="POST">
						<div class="panel panel-info">
							<div class="table-responsive">
								<table class="table">
									<tr class="info">
										<td>Proveedor</td>
										<td>Tipo</td>
										<td>Código</td>
										<td>Cuenta contable</td>
										<td class="col-xs-1 text-center">Opciones</td>
									</tr>
									<?php
									$query_proveedores = mysqli_query($con, "SELECT DISTINCT prov.ruc_proveedor as ruc_proveedor, enc_com.id_proveedor as id_proveedor, prov.razon_social as razon_social FROM encabezado_compra as enc_com INNER JOIN proveedores as prov ON enc_com.id_proveedor=prov.id_proveedor WHERE enc_com.ruc_empresa = '" . $ruc_empresa . "' group by enc_com.id_proveedor order by prov.razon_social asc");
									while ($row_compras = mysqli_fetch_array($query_proveedores)) {
										$codigo_unico = rand(100, 50000);
										$id_proveedor = $row_compras['id_proveedor'];
										$proveedor = $row_compras['razon_social'];
										$ruc_proveedor = $row_compras['ruc_proveedor'];

										//para mostrar las cuentas que ya estan guardadas
										$sql_cuentas_programadas = mysqli_query($con, "SELECT * FROM asientos_programados as ap INNER JOIN plan_cuentas as pc ON pc.id_cuenta=ap.id_cuenta WHERE ap.ruc_empresa = '" . $ruc_empresa . "' and ap.tipo_asiento = 'proveedor' and ap.id_pro_cli='" . $id_proveedor . "' ");
										$row_cuenta = mysqli_fetch_array($sql_cuentas_programadas);
										$id_cuenta = isset($row_cuenta['id_cuenta']) ? $row_cuenta['id_cuenta'] : 0;
										$codigo_cuenta = isset($row_cuenta['codigo_cuenta']) ? $row_cuenta['codigo_cuenta'] : "";
										$nombre_cuenta = isset($row_cuenta['nombre_cuenta']) ? $row_cuenta['nombre_cuenta'] : "";
										$id_registro = isset($row_cuenta['id_asi_pro']) ? $row_cuenta['id_asi_pro'] : 0;
									?>
										<tr class="active">
											<input type="hidden" id="id_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta; ?>">
											<td class="col-xs-4"><a href="#" title='Mostrar detalle de compras' onclick="mostrar_detalle_compras('<?php echo $id_proveedor; ?>')" data-toggle="modal" data-target="#detalleComprasProveedor"><?php echo mb_strtoupper($proveedor, 'UTF-8'); ?> </a></td>
											<td class="col-xs-1">Activo/Costo/Gasto</td>
											<td class="col-xs-2"><input type="text" class="form-control input-sm" id="codigo_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $codigo_cuenta; ?>" readonly></td>
											<td class="col-xs-4">
												<input type="text" <?php echo empty($codigo_cuenta) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('<?php echo $codigo_unico; ?>', '<?php echo $id_proveedor; ?>','proveedor', '<?php echo $ruc_proveedor; ?>');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta; ?>">
											</td>
											<td class="col-xs-1 text-center">
											<a href="#" class="btn btn-danger btn-xs" title="Eliminar cuenta" onclick="eliminar_cuenta('<?php echo $codigo_unico; ?>','<?php echo $id_registro; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
											</td>
										</tr>
									<?php
									}
									?>
								</table>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	//para las retenciones en compras
	if ($tipo == 'retenciones_compras') {
		?>
		<div class="panel-group" id="accordionRetencionesCompras">
			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordionRetencionesCompras" href="#RetencionesCompras"><span class="caret"></span> Contabilización de retenciones en compras (Obligatorio)</a>
				<div id="RetencionesCompras" class="panel-collapse collapse">
					<form class="form-horizontal" method="POST">
						<div class="panel panel-info">
							<div class="table-responsive">
								<table class="table">
									<tr class="info">
										<td>Concepto</td>
										<td>Impuesto</td>
										<td>Cod</td>
										<td>%</td>
										<td>Tipo</td>
										<td>Código</td>
										<td>Cuenta contable</td>
										<td class="col-xs-1 text-center">Opciones</td>
									</tr>
									<?php
									$query_concepto_retencion = mysqli_query($con, "SELECT DISTINCT(cue_ret.codigo_impuesto) as codigo_impuesto, cue_ret.id_cr as id_retencion, cue_ret.impuesto as impuesto, cue_ret.porcentaje_retencion as porcentaje_retencion FROM cuerpo_retencion as cue_ret WHERE cue_ret.ruc_empresa = '" . $ruc_empresa . "' group by cue_ret.codigo_impuesto");
									while ($row_concepto_retencion = mysqli_fetch_array($query_concepto_retencion)) {
										$codigo_unico = rand(100, 50000);
										$id_retencion = $row_concepto_retencion['codigo_impuesto']; //$row_concepto_retencion['id_retencion'];
										$codigo_impuesto = $row_concepto_retencion['codigo_impuesto'];
										$impuesto = $row_concepto_retencion['impuesto'];
										$porcentaje_retencion = $row_concepto_retencion['porcentaje_retencion'];

										switch ($impuesto) {
											case "1":
												$impuesto = 'RENTA';
												break;
											case "2":
												$impuesto = 'IVA';
												break;
											case "3":
												$impuesto = 'ISD';
												break;
										}

										//PARA TRAER LOS NOMBRES DE CONCEPTOS DE RETENCIONES
										$query_retenciones = mysqli_query($con, "SELECT * FROM retenciones_sri WHERE codigo_ret='" . $codigo_impuesto . "'");
										$row_retenciones = mysqli_fetch_array($query_retenciones);
										$concepto_ret = isset($row_retenciones['concepto_ret']) ? $row_retenciones['concepto_ret'] : "";

										//para mostrar las cuentas que ya estan guardadas
										$sql_cuentas_programadas = mysqli_query($con, "SELECT * FROM asientos_programados as ap INNER JOIN plan_cuentas as pc ON pc.id_cuenta=ap.id_cuenta WHERE ap.ruc_empresa = '" . $ruc_empresa . "' and ap.tipo_asiento = 'retenciones_compras' and ap.id_pro_cli='" . $id_retencion . "' ");
										$row_cuenta = mysqli_fetch_array($sql_cuentas_programadas);
										$id_cuenta = isset($row_cuenta['id_cuenta']) ? $row_cuenta['id_cuenta'] : 0;
										$codigo_cuenta = isset($row_cuenta['codigo_cuenta']) ? $row_cuenta['codigo_cuenta'] : "";
										$nombre_cuenta = isset($row_cuenta['nombre_cuenta']) ? $row_cuenta['nombre_cuenta'] : "";
										$id_registro = isset($row_cuenta['id_asi_pro']) ? $row_cuenta['id_asi_pro'] : 0;
									?>
										<tr class="active">
											<input type="hidden" id="id_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta; ?>">
											<td class="col-xs-2"><?php echo mb_strtoupper($concepto_ret, 'UTF-8') ?></td>
											<td class="col-xs-1"><?php echo $impuesto ?></td>
											<td class="col-xs-1"><?php echo $codigo_impuesto ?></td>
											<td class="col-xs-1"><?php echo $porcentaje_retencion . "%" ?></td>
											<td class="col-xs-1">Pasivo</td>
											<td class="col-xs-1"><input type="text" class="form-control input-sm" id="codigo_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $codigo_cuenta; ?>" readonly></td>
											<td class="col-xs-4">
												<input type="text" <?php echo empty($codigo_cuenta) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('<?php echo $codigo_unico; ?>', '<?php echo $id_retencion; ?>','retenciones_compras', '<?php echo $impuesto . $codigo_impuesto; ?>');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta; ?>">
											</td>
											<td class="col-xs-1 text-center">
											<a href="#" class="btn btn-danger btn-xs" title="Eliminar cuenta" onclick="eliminar_cuenta('<?php echo $codigo_unico; ?>','<?php echo $id_registro; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
											</td>
										</tr>
									<?php
									}
									?>
								</table>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	//para las retenciones en ventas
	if ($tipo == 'retenciones_ventas') {
		?>
		<div class="panel-group" id="accordionRetencionesVentas">
			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordionRetencionesVentas" href="#RetencionesVentas"><span class="caret"></span> Contabilización de retenciones en ventas (Obligatorio)</a>
				<div id="RetencionesVentas" class="panel-collapse collapse">
					<form class="form-horizontal" method="POST">
						<div class="panel panel-info">
							<div class="table-responsive">
								<table class="table">
									<tr class="info">
										<td>Concepto</td>
										<td>Impuesto</td>
										<td>Cod</td>
										<td>%</td>
										<td>Tipo</td>
										<td>Código</td>
										<td>Cuenta contable</td>
										<td class="col-xs-1 text-center">Opciones</td>
									</tr>
									<?php
									$query_concepto_retencion = mysqli_query($con, "SELECT DISTINCT(cue_ret.codigo_impuesto) as codigo_impuesto, cue_ret.id_cr as id_retencion, cue_ret.impuesto as impuesto, cue_ret.porcentaje_retencion as porcentaje_retencion FROM cuerpo_retencion_venta as cue_ret WHERE cue_ret.ruc_empresa = '" . $ruc_empresa . "' group by cue_ret.codigo_impuesto");
									while ($row_concepto_retencion = mysqli_fetch_array($query_concepto_retencion)) {
										$codigo_unico = rand(100, 50000);
										$id_retencion = $row_concepto_retencion['codigo_impuesto']; //$row_concepto_retencion['id_retencion'];
										$codigo_impuesto = $row_concepto_retencion['codigo_impuesto'];
										$impuesto = $row_concepto_retencion['impuesto'];
										$porcentaje_retencion = $row_concepto_retencion['porcentaje_retencion'];

										switch ($impuesto) {
											case "1":
												$impuesto = 'RENTA';
												break;
											case "2":
												$impuesto = 'IVA';
												break;
											case "3":
												$impuesto = 'ISD';
												break;
										}

										//PARA TRAER LOS NOMBRES DE CONCEPTOS DE RETENCIONES
										$query_retenciones = mysqli_query($con, "SELECT * FROM retenciones_sri WHERE codigo_ret='" . $codigo_impuesto . "'");
										$row_retenciones = mysqli_fetch_array($query_retenciones);
										$concepto_ret = isset($row_retenciones['concepto_ret']) ? $row_retenciones['concepto_ret'] : "";

										//para mostrar las cuentas que ya estan guardadas
										$sql_cuentas_programadas = mysqli_query($con, "SELECT * FROM asientos_programados as ap INNER JOIN plan_cuentas as pc ON pc.id_cuenta=ap.id_cuenta WHERE ap.ruc_empresa = '" . $ruc_empresa . "' and ap.tipo_asiento = 'retenciones_ventas' and ap.id_pro_cli='" . $codigo_impuesto . "' ");
										$row_cuenta = mysqli_fetch_array($sql_cuentas_programadas);
										$id_cuenta = isset($row_cuenta['id_cuenta']) ? $row_cuenta['id_cuenta'] : 0;
										$codigo_cuenta = isset($row_cuenta['codigo_cuenta']) ? $row_cuenta['codigo_cuenta'] : "";
										$nombre_cuenta = isset($row_cuenta['nombre_cuenta']) ? $row_cuenta['nombre_cuenta'] : "";
										$id_registro = isset($row_cuenta['id_asi_pro']) ? $row_cuenta['id_asi_pro'] : 0;
										if ($concepto_ret == "") {
											$concepto_ret = "Retenciones de " . $impuesto . " código " . $codigo_impuesto;
										} else {
											$concepto_ret = $concepto_ret;
										}

									?>
										<tr class="active">
											<input type="hidden" id="id_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta; ?>">
											<td class="col-xs-2"><?php echo mb_strtoupper($concepto_ret, 'UTF-8') ?></td>
											<td class="col-xs-1"><?php echo $impuesto ?></td>
											<td class="col-xs-1"><?php echo $codigo_impuesto ?></td>
											<td class="col-xs-1"><?php echo $porcentaje_retencion . "%" ?></td>
											<td class="col-xs-1">Activo</td>
											<td class="col-xs-1"><input type="text" class="form-control input-sm" id="codigo_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $codigo_cuenta; ?>" readonly></td>
											<td class="col-xs-4">
												<input type="text" <?php echo empty($codigo_cuenta) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('<?php echo $codigo_unico; ?>', '<?php echo $id_retencion; ?>','retenciones_ventas', '<?php echo $impuesto . $codigo_impuesto; ?>');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta; ?>">
											</td>
											<td class="col-xs-1 text-center">
											<a href="#" class="btn btn-danger btn-xs" title="Eliminar cuenta" onclick="eliminar_cuenta('<?php echo $codigo_unico; ?>','<?php echo $id_registro; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
											</td>
										</tr>
									<?php
									}
									?>
								</table>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	//para los ingresos
	if ($tipo == 'ingresosegresos') {
		?>
		<div class="panel-group" id="accordionCuentasIngresos">
			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordionCuentasIngresos" href="#CuentasIngresos"><span class="caret"></span> Cuentas contables para opciones de ingresos (Obligatorio)</a>
				<div id="CuentasIngresos" class="panel-collapse collapse">
					<form class="form-horizontal" method="POST">
						<div class="panel panel-info">
							<div class="table-responsive">
								<table class="table">
									<tr class="info">
										<td>Descripción</td>
										<td>Tipo</td>
										<td>Código</td>
										<td>Cuenta contable</td>
										<td class="col-xs-1 text-center">Opciones</td>
									</tr>
									<?php
									$query_registros = mysqli_query($con, "SELECT * FROM opciones_ingresos_egresos WHERE ruc_empresa = '" . $ruc_empresa . "' and tipo_opcion='1' and status='1' order by descripcion asc");
									while ($row_registro = mysqli_fetch_array($query_registros)) {
										$codigo_unico = rand(100, 50000);
										$id_ingreso = $row_registro['id'];
										$descripcion = $row_registro['descripcion'];

										//para mostrar las cuentas que ya estan guardadas
										$sql_cuentas_programadas = mysqli_query($con, "SELECT * FROM asientos_programados as ap INNER JOIN plan_cuentas as pc ON pc.id_cuenta=ap.id_cuenta WHERE ap.ruc_empresa = '" . $ruc_empresa . "' and ap.tipo_asiento = 'opcion_ingreso' and ap.id_pro_cli='" . $id_ingreso . "' ");
										$row_cuenta = mysqli_fetch_array($sql_cuentas_programadas);
										$id_cuenta = isset($row_cuenta['id_cuenta']) ? $row_cuenta['id_cuenta'] : 0;
										$codigo_cuenta = isset($row_cuenta['codigo_cuenta']) ? $row_cuenta['codigo_cuenta'] : "";
										$nombre_cuenta = isset($row_cuenta['nombre_cuenta']) ? $row_cuenta['nombre_cuenta'] : "";
										$id_registro = isset($row_cuenta['id_asi_pro']) ? $row_cuenta['id_asi_pro'] : 0;
									?>
										<tr class="active">
											<input type="hidden" id="id_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta; ?>">
											<td class="col-xs-4"><?php echo mb_strtoupper($descripcion, 'UTF-8'); ?></td>
											<td class="col-xs-1">Activo/Pasivo/Costo/Gasto</td>
											<td class="col-xs-2"><input type="text" class="form-control input-sm" id="codigo_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $codigo_cuenta; ?>" readonly></td>
											<td class="col-xs-4">
												<input type="text" <?php echo empty($codigo_cuenta) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('<?php echo $codigo_unico; ?>', '<?php echo $id_ingreso; ?>','opcion_ingreso', '<?php echo $descripcion; ?>');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta; ?>">
											</td>
											<td class="col-xs-1 text-center">
											<a href="#" class="btn btn-danger btn-xs" title="Eliminar cuenta" onclick="eliminar_cuenta('<?php echo $codigo_unico; ?>','<?php echo $id_registro; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
											</td>
										</tr>
									<?php
									}
									?>
								</table>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	//para los egresos
	if ($tipo == 'ingresosegresos') {
		?>
		<div class="panel-group" id="accordionCuentasEgresos">
			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordionCuentasEgresos" href="#CuentasEgresos"><span class="caret"></span> Cuentas contables para opciones de egresos (Obligatorio)</a>
				<div id="CuentasEgresos" class="panel-collapse collapse">
					<form class="form-horizontal" method="POST">
						<div class="panel panel-info">
							<div class="table-responsive">
								<table class="table">
									<tr class="info">
										<td>Descripción</td>
										<td>Tipo</td>
										<td>Código</td>
										<td>Cuenta contable</td>
										<td class="col-xs-1 text-center">Opciones</td>
									</tr>
									<?php
									$query_registros = mysqli_query($con, "SELECT * FROM opciones_ingresos_egresos WHERE ruc_empresa = '" . $ruc_empresa . "' and tipo_opcion='2' and status='1' order by descripcion asc");
									while ($row_registro = mysqli_fetch_array($query_registros)) {
										$codigo_unico = rand(100, 50000);
										$id_ingreso = $row_registro['id'];
										$descripcion = $row_registro['descripcion'];

										//para mostrar las cuentas que ya estan guardadas
										$sql_cuentas_programadas = mysqli_query($con, "SELECT * FROM asientos_programados as ap INNER JOIN plan_cuentas as pc ON pc.id_cuenta=ap.id_cuenta WHERE ap.ruc_empresa = '" . $ruc_empresa . "' and ap.tipo_asiento = 'opcion_egreso' and ap.id_pro_cli='" . $id_ingreso . "' ");
										$row_cuenta = mysqli_fetch_array($sql_cuentas_programadas);
										$id_cuenta = isset($row_cuenta['id_cuenta']) ? $row_cuenta['id_cuenta'] : 0;
										$codigo_cuenta = isset($row_cuenta['codigo_cuenta']) ? $row_cuenta['codigo_cuenta'] : "";
										$nombre_cuenta = isset($row_cuenta['nombre_cuenta']) ? $row_cuenta['nombre_cuenta'] : "";
										$id_registro = isset($row_cuenta['id_asi_pro']) ? $row_cuenta['id_asi_pro'] : 0;
									?>
										<tr class="active">
											<input type="hidden" id="id_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta; ?>">
											<td class="col-xs-4"><?php echo mb_strtoupper($descripcion, 'UTF-8'); ?></td>
											<td class="col-xs-1">Activo/Pasivo/Costo/Gasto</td>
											<td class="col-xs-2"><input type="text" class="form-control input-sm" id="codigo_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $codigo_cuenta; ?>" readonly></td>
											<td class="col-xs-4">
												<input type="text" <?php echo empty($codigo_cuenta) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('<?php echo $codigo_unico; ?>', '<?php echo $id_ingreso; ?>','opcion_egreso', '<?php echo $descripcion; ?>');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta; ?>">
											</td>
											<td class="col-xs-1 text-center">
											<a href="#" class="btn btn-danger btn-xs" title="Eliminar cuenta" onclick="eliminar_cuenta('<?php echo $codigo_unico; ?>','<?php echo $id_registro; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
											</td>
										</tr>
									<?php
									}
									?>
								</table>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	//para los cobros
	if ($tipo == 'cobrosypagos') {
		?>
		<div class="panel-group" id="accordionCuentasCobros">
			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordionCuentasCobros" href="#CuentasCobros"><span class="caret"></span> Cuentas contables para formas de cobros en ingresos (Obligatorio)</a>
				<div id="CuentasCobros" class="panel-collapse collapse">
					<form class="form-horizontal" method="POST">
						<div class="panel panel-info">
							<div class="table-responsive">
								<table class="table">
									<tr class="info">
										<td>Descripción</td>
										<td>Tipo</td>
										<td>Código</td>
										<td>Cuenta contable</td>
										<td class="col-xs-1 text-center">Opciones</td>
									</tr>
									<?php
									$query_registros = mysqli_query($con, "SELECT * FROM opciones_cobros_pagos WHERE ruc_empresa = '" . $ruc_empresa . "' and tipo_opcion='1' and status='1' order by descripcion asc");
									while ($row_registro = mysqli_fetch_array($query_registros)) {
										$codigo_unico = rand(100, 50000);
										$id_ingreso = $row_registro['id'];
										$descripcion = $row_registro['descripcion'];

										//para mostrar las cuentas que ya estan guardadas
										$sql_cuentas_programadas = mysqli_query($con, "SELECT * FROM asientos_programados as ap INNER JOIN plan_cuentas as pc ON pc.id_cuenta=ap.id_cuenta WHERE ap.ruc_empresa = '" . $ruc_empresa . "' and ap.tipo_asiento = 'opcion_cobro' and ap.id_pro_cli='" . $id_ingreso . "' ");
										$row_cuenta = mysqli_fetch_array($sql_cuentas_programadas);
										$id_cuenta = isset($row_cuenta['id_cuenta']) ? $row_cuenta['id_cuenta'] : 0;
										$codigo_cuenta = isset($row_cuenta['codigo_cuenta']) ? $row_cuenta['codigo_cuenta'] : "";
										$nombre_cuenta = isset($row_cuenta['nombre_cuenta']) ? $row_cuenta['nombre_cuenta'] : "";
										$id_registro = isset($row_cuenta['id_asi_pro']) ? $row_cuenta['id_asi_pro'] : 0;
									?>
										<tr class="active">
											<input type="hidden" id="id_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta; ?>">
											<td class="col-xs-4"><?php echo mb_strtoupper($descripcion, 'UTF-8'); ?></td>
											<td class="col-xs-1">Activo</td>
											<td class="col-xs-2"><input type="text" class="form-control input-sm" id="codigo_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $codigo_cuenta; ?>" readonly></td>
											<td class="col-xs-4">
												<input type="text" <?php echo empty($codigo_cuenta) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('<?php echo $codigo_unico; ?>', '<?php echo $id_ingreso; ?>','opcion_cobro', '<?php echo $descripcion; ?>');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta; ?>">
											</td>
											<td class="col-xs-1 text-center">
											<a href="#" class="btn btn-danger btn-xs" title="Eliminar cuenta" onclick="eliminar_cuenta('<?php echo $codigo_unico; ?>','<?php echo $id_registro; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
											</td>
										</tr>
									<?php
									}
									?>
								</table>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	//para los pagos
	if ($tipo == 'cobrosypagos') {
		?>
		<div class="panel-group" id="accordionCuentasPagos">
			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordionCuentasPagos" href="#CuentasPagos"><span class="caret"></span> Cuentas contables para formas de pagos en egresos (Obligatorio)</a>
				<div id="CuentasPagos" class="panel-collapse collapse">
					<form class="form-horizontal" method="POST">
						<div class="panel panel-info">
							<div class="table-responsive">
								<table class="table">
									<tr class="info">
										<td>Descripción</td>
										<td>Tipo</td>
										<td>Código</td>
										<td>Cuenta contable</td>
										<td class="col-xs-1 text-center">Opciones</td>
									</tr>
									<?php
									$query_registros = mysqli_query($con, "SELECT * FROM opciones_cobros_pagos WHERE ruc_empresa = '" . $ruc_empresa . "' and tipo_opcion='2' and status='1' order by descripcion asc");
									while ($row_registro = mysqli_fetch_array($query_registros)) {
										$codigo_unico = rand(100, 50000);
										$id_ingreso = $row_registro['id'];
										$descripcion = $row_registro['descripcion'];

										//para mostrar las cuentas que ya estan guardadas
										$sql_cuentas_programadas = mysqli_query($con, "SELECT * FROM asientos_programados as ap INNER JOIN plan_cuentas as pc ON pc.id_cuenta=ap.id_cuenta WHERE ap.ruc_empresa = '" . $ruc_empresa . "' and ap.tipo_asiento = 'opcion_pago' and ap.id_pro_cli='" . $id_ingreso . "' ");
										$row_cuenta = mysqli_fetch_array($sql_cuentas_programadas);
										$id_cuenta = isset($row_cuenta['id_cuenta']) ? $row_cuenta['id_cuenta'] : 0;
										$codigo_cuenta = isset($row_cuenta['codigo_cuenta']) ? $row_cuenta['codigo_cuenta'] : "";
										$nombre_cuenta = isset($row_cuenta['nombre_cuenta']) ? $row_cuenta['nombre_cuenta'] : "";
										$id_registro = isset($row_cuenta['id_asi_pro']) ? $row_cuenta['id_asi_pro'] : 0;
									?>
										<tr class="active">
											<input type="hidden" id="id_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta; ?>">
											<td class="col-xs-4"><?php echo mb_strtoupper($descripcion, 'UTF-8'); ?></td>
											<td class="col-xs-1">Activo</td>
											<td class="col-xs-2"><input type="text" class="form-control input-sm" id="codigo_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $codigo_cuenta; ?>" readonly></td>
											<td class="col-xs-4">
												<input type="text" <?php echo empty($codigo_cuenta) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('<?php echo $codigo_unico; ?>', '<?php echo $id_ingreso; ?>','opcion_pago', '<?php echo $descripcion; ?>');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta; ?>">
											</td>
											<td class="col-xs-1 text-center">
											<a href="#" class="btn btn-danger btn-xs" title="Eliminar cuenta" onclick="eliminar_cuenta('<?php echo $codigo_unico; ?>','<?php echo $id_registro; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
											</td>
										</tr>
									<?php
									}
									?>
								</table>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	//para las formas de pago en bancos
	if ($tipo == 'cobrosypagos') {
		?>
		<div class="panel-group" id="accordionCuentasBancos">
			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordionCuentasBancos" href="#CuentasBancos"><span class="caret"></span> Cuentas contables para cuentas bancarias (Obligatorio)</a>
				<div id="CuentasBancos" class="panel-collapse collapse">
					<form class="form-horizontal" method="POST">
						<div class="panel panel-info">
							<div class="table-responsive">
								<table class="table">
									<tr class="info">
										<td>Cuenta bancaria</td>
										<td>Tipo</td>
										<td>Código</td>
										<td>Cuenta contable</td>
										<td class="col-xs-1 text-center">Opciones</td>
									</tr>
									<?php
									$cuentas = mysqli_query($con, "SELECT cue_ban.numero_cuenta as numero_cuenta, cue_ban.id_cuenta as id_cuenta, concat(ban_ecu.nombre_banco,' ',cue_ban.numero_cuenta,' ', if(cue_ban.id_tipo_cuenta=1,'Aho','Cte')) as cuenta_bancaria FROM cuentas_bancarias as cue_ban INNER JOIN bancos_ecuador as ban_ecu ON cue_ban.id_banco=ban_ecu.id_bancos WHERE cue_ban.ruc_empresa = '" . $ruc_empresa . "' ");
									while ($row = mysqli_fetch_array($cuentas)) {
										$codigo_unico = rand(100, 50000);
										$id_cuenta_bancaria = $row['id_cuenta'];
										$cuenta_bancaria = $row['cuenta_bancaria'];
										$numero_cuenta = $row['numero_cuenta'];

										//para mostrar las cuentas que ya estan guardadas
										$sql_cuentas_programadas = mysqli_query($con, "SELECT * FROM asientos_programados as ap INNER JOIN plan_cuentas as pc ON pc.id_cuenta=ap.id_cuenta WHERE ap.ruc_empresa = '" . $ruc_empresa . "' and ap.tipo_asiento = 'bancos' and ap.id_pro_cli='" . $id_cuenta_bancaria . "' ");
										$row_cuenta = mysqli_fetch_array($sql_cuentas_programadas);
										$id_cuenta = isset($row_cuenta['id_cuenta']) ? $row_cuenta['id_cuenta'] : 0;
										$codigo_cuenta = isset($row_cuenta['codigo_cuenta']) ? $row_cuenta['codigo_cuenta'] : "";
										$nombre_cuenta = isset($row_cuenta['nombre_cuenta']) ? $row_cuenta['nombre_cuenta'] : "";
										$id_registro = isset($row_cuenta['id_asi_pro']) ? $row_cuenta['id_asi_pro'] : 0;
									?>
										<tr class="active">
											<input type="hidden" id="id_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta; ?>">
											<td class="col-xs-3"><?php echo mb_strtoupper($cuenta_bancaria, 'UTF-8') ?></td>
											<td class="col-xs-2">Activo</td>
											<td class="col-xs-2"><input type="text" class="form-control input-sm" id="codigo_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $codigo_cuenta; ?>" readonly></td>
											<td class="col-xs-4">
												<input type="text" <?php echo empty($codigo_cuenta) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('<?php echo $codigo_unico; ?>', '<?php echo $id_cuenta_bancaria; ?>','bancos', '<?php echo $numero_cuenta; ?>');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta; ?>">
											</td>
											<td class="col-xs-1 text-center">
											<a href="#" class="btn btn-danger btn-xs" title="Eliminar cuenta" onclick="eliminar_cuenta('<?php echo $codigo_unico; ?>','<?php echo $id_registro; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
											</td>
										</tr>
									<?php
									}
									?>
								</table>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php
	}
	//para los empleados
	if ($tipo == 'rol_pagos') {
		?>
		<div class="panel-group" id="accordionEmpleados">
			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordionEmpleados" href="#Empleados"><span class="caret"></span> Configurar asiento individual para cada empleado (opcional)</a>
				<div id="Empleados" class="panel-collapse collapse">
				<div class="panel-body">
				<form class="form-horizontal" role="form" >
				<input type="hidden" id="idEmpleado" value="">
						<div class="form-group">
							<div class="col-md-6">
							<div class="input-group">
								<span class="input-group-addon"><b>Empleado</b></span>
								<input type="text" name="datalistEmpleados" class="form-control input-sm" id="datalistEmpleados" value="" placeholder="Escribir para buscar un empleado" onkeyup='agregar_empleado();' autocomplete="off">
							</div>
							</div>
							<div class="col-sm-4">
								<button type="button" title="Mostrar" class="btn btn-info btn-sm" onclick="mostrar_cuentas_empleado()"><span class="glyphicon glyphicon-search" ></span> Mostrar cuentas</button>				
								<span id="loader_empleado"></span>
							</div>
						</div>					
				</form>
				<div class='cuentas_empleado'></div><!-- Carga los datos ajax -->
				</div>
				</div>
			</div>
		</div>
		<?php
	}
}

if ($action == 'mostrar_cuentas_empleado') {
	$id_empleado = $_GET['id_empleado'];
	?>
	<form class="form-horizontal" method="POST">
	<div class="panel panel-info">
		<div class="table-responsive">
			<table class="table">
				<tr class="info">
					<td>Descripción</td>
					<td>Detalle</td>
					<td>Tipo</td>
					<td>Código</td>
					<td>Cuenta contable</td>
					<td class="col-xs-1 text-center">Opciones</td>
				</tr>
				<?php
				$query_asientos_tipo = mysqli_query($con, "SELECT * FROM asientos_tipo WHERE tipo_asiento = 'rol_pagos' order by concepto_cuenta asc ");
				while ($row_detalle_tipo = mysqli_fetch_array($query_asientos_tipo)) {
					$codigo_unico = rand(100, 50000);
					$id_asiento_tipo = $row_detalle_tipo['id_asiento_tipo'];
					$concepto_cuenta = $row_detalle_tipo['concepto_cuenta'];
					$tipo_asiento = $row_detalle_tipo['tipo_asiento'];
					$tipo_saldo = $row_detalle_tipo['tipo_saldo'];
					$detalle = $row_detalle_tipo['detalle'];

					//para mostrar las cuentas que ya estan guardadas
					$sql_cuentas_programadas = mysqli_query($con, "SELECT * FROM asientos_programados as ap 
					INNER JOIN plan_cuentas as pc ON pc.id_cuenta=ap.id_cuenta 
					WHERE ap.ruc_empresa = '" . $ruc_empresa . "' and ap.tipo_asiento = 'empleado' 
					and ap.id_pro_cli='" . $id_asiento_tipo . "' and concepto_tipo= concat('id_empleado','".$id_empleado."') ");
					$row_cuenta = mysqli_fetch_array($sql_cuentas_programadas);
					$id_cuenta = isset($row_cuenta['id_cuenta']) ? $row_cuenta['id_cuenta'] : 0;
					$codigo_cuenta = isset($row_cuenta['codigo_cuenta']) ? $row_cuenta['codigo_cuenta'] : "";
					$nombre_cuenta = isset($row_cuenta['nombre_cuenta']) ? $row_cuenta['nombre_cuenta'] : "";
					$id_registro = isset($row_cuenta['id_asi_pro']) ? $row_cuenta['id_asi_pro'] : 0;
				?>
					<tr class="active">
						<input type="hidden" id="id_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta; ?>">
						<td class="col-xs-3"><?php echo mb_strtoupper($concepto_cuenta, 'UTF-8') ?></td>
						<td class="col-xs-2"><?php echo ucfirst(($detalle)) ?></td>
						<td class="col-xs-1"><?php echo ucfirst(($tipo_saldo)) ?></td>
						<td class="col-xs-2"><input type="text" id="codigo_cuenta<?php echo $codigo_unico; ?>" class="form-control input-sm" value="<?php echo $codigo_cuenta; ?>" readonly></td>
						<td class="col-xs-3">
							<input type="text" <?php echo empty($codigo_cuenta) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('<?php echo $codigo_unico; ?>','<?php echo $id_asiento_tipo; ?>','empleado', 'id_empleado<?php echo $id_empleado; ?>');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta; ?>">
						</td>
						<td class="col-xs-1 text-center">
							<a href="#" class="btn btn-danger btn-xs" title="Eliminar cuenta" onclick="eliminar_cuenta('<?php echo $codigo_unico; ?>','<?php echo $id_registro; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
						</td>
					</tr>
				<?php
				}
				?>
			</table>
		</div>
	</div>
	</form>
	<?php
}

if($action=="guarda_cuenta"){
	$id_cuenta=$_GET['id_cuenta'];
	$tipo=$_GET['tipo'];
	$id_registro=$_GET['id'];
	$concepto_cuenta=$_GET['concepto_cuenta'];

		eliminar_registro($con, $id_registro);
		guardar_registro($con, $ruc_empresa, $id_cuenta, $tipo, $concepto_cuenta, $id_registro, $id_usuario);
		echo "<script>$.notify('Cuenta guardada.','success')</script>";

}

if($action=="eliminar_cuenta"){
	$id_registro=$_GET['id'];
	eliminar_registro($con, $id_registro);
	echo "<script>$.notify('Registro eliminado.','info');
			</script>";
}

function eliminar_registro($con, $id_registro){
	$eliminar_asiento_tipo=mysqli_query($con,"DELETE FROM asientos_programados WHERE id_asi_pro = '".$id_registro."' ");
	return $eliminar_asiento_tipo;
}

function guardar_registro($con, $ruc_empresa, $id_cuenta, $tipo_asiento, $concepto_cuenta, $id_registro, $id_usuario){
	$fecha_agregado=date("Y-m-d H:i:s");
	ini_set('date.timezone','America/Guayaquil');
	$guardar_asiento_tipo=mysqli_query($con,"INSERT INTO asientos_programados VALUES (NULL, '".$ruc_empresa."', '".$tipo_asiento."','".$id_cuenta."','DEBE-HABER','".$concepto_cuenta."','".$id_registro."','".$id_usuario."','".$fecha_agregado."')");				
	return $guardar_asiento_tipo;
}
?>