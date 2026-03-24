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

//para facturas de ventas
if ($action == 'listado_clientes_facturas') {
	$anio = $_GET['anio'];
	$mes = $_GET['mes'];
	echo listado_clientes_facturas($con, $ruc_empresa, $anio, $mes);
}

if ($action == 'listado_productos_ventas') {
	$anio = $_GET['anio'];
	$mes = $_GET['mes'];
	echo listado_productos_ventas($con, $ruc_empresa, $anio, $mes);
}

if ($action == 'listado_categorias_ventas') {
	echo listado_categorias_ventas($con, $ruc_empresa);
}

if ($action == 'listado_marcas_ventas') {
	echo listado_marcas_ventas($con, $ruc_empresa);
}

//para recibos de ventas
if ($action == 'listado_clientes_recibos') {
	$anio = $_GET['anio'];
	$mes = $_GET['mes'];
	echo listado_clientes_recibos($con, $ruc_empresa, $anio, $mes);
}

if ($action == 'listado_productos_ventas_recibos') {
	$anio = $_GET['anio'];
	$mes = $_GET['mes'];
	echo listado_productos_ventas_recibos($con, $ruc_empresa, $anio, $mes);
}

if ($action == 'listado_categorias_ventas_recibos') {
	echo listado_categorias_ventas_recibos($con, $ruc_empresa);
}

if ($action == 'listado_marcas_ventas_recibos') {
	echo listado_marcas_ventas_recibos($con, $ruc_empresa);
}

//para proveedores
if ($action == 'listado_proveedores') {
	$anio = $_GET['anio'];
	$mes = $_GET['mes'];
	echo listado_proveedores($con, $ruc_empresa, $anio, $mes);
}


//para buscar detalles asientos prestablecidos 
if ($action == 'buscar_asientos_prestablecidos') {
	$tipo = $_GET['tipo'];
	$mes = $_GET['mes'];
	$anio = $_GET['anio'];
	//para facturas de venta
	if ($tipo == 'ventas') {
		$query_asientos_tipo = mysqli_query($con, "SELECT * FROM asientos_tipo WHERE tipo_asiento = '" . $tipo . "' order by concepto_cuenta asc ");

		$sql_marcas = mysqli_query($con, "
		SELECT COUNT(*) AS total
		FROM asientos_programados 
		WHERE tipo_asiento IN ('ventasmarcasfacturasCxC','ventasmarcasfacturas','ventasmarcasfacturasIva')
		AND ruc_empresa = '" . mysqli_real_escape_string($con, $ruc_empresa) . "'");
		$row = mysqli_fetch_assoc($sql_marcas);
		$contar_cuentas_por_marcas = $row['total'] > 0 ? $row['total'] . " cuentas asignadas." : "";

		$sql_categorias = mysqli_query($con, "
		SELECT COUNT(*) AS total
		FROM asientos_programados 
		WHERE tipo_asiento IN ('ventascategoriasfacturasCxC','ventascategoriasfacturas','ventascategoriasfacturasIva')
		AND ruc_empresa = '" . mysqli_real_escape_string($con, $ruc_empresa) . "'");
		$row = mysqli_fetch_assoc($sql_categorias);
		$contar_cuentas_por_categorias = $row['total'] > 0 ? $row['total'] . " cuentas asignadas." : "";

		$sql_productos = mysqli_query($con, "
		SELECT COUNT(*) AS total
		FROM asientos_programados 
		WHERE tipo_asiento IN ('productoventafacturaIva','productoventafacturaCxC','productoventafactura')
		AND ruc_empresa = '" . mysqli_real_escape_string($con, $ruc_empresa) . "'");
		$row = mysqli_fetch_assoc($sql_productos);
		$contar_cuentas_por_productos = $row['total'] > 0 ? $row['total'] . " cuentas asignadas." : "";

		$sql_clientes = mysqli_query($con, "
		SELECT COUNT(*) AS total
		FROM asientos_programados 
		WHERE tipo_asiento IN ('cliente','ventas_cliente_iva','ventas_cliente_cxc')
		AND ruc_empresa = '" . mysqli_real_escape_string($con, $ruc_empresa) . "'");
		$row = mysqli_fetch_assoc($sql_clientes);
		$contar_cuentas_por_clientes = $row['total'] > 0 ? $row['total'] . " cuentas asignadas." : "";

		$sql_tarifa_iva = mysqli_query($con, "
		SELECT COUNT(*) AS total
		FROM asientos_programados 
		WHERE tipo_asiento IN ('tarifa_iva_ventas','tarifa_iva_ventas_iva','tarifa_iva_ventas_cxc')
		AND ruc_empresa = '" . mysqli_real_escape_string($con, $ruc_empresa) . "'");
		$row = mysqli_fetch_assoc($sql_tarifa_iva);
		$contar_cuentas_por_tarifa_iva = $row['total'] > 0 ? $row['total'] . " cuentas asignadas." : "";

?>
		<div class="panel-group" id="accordionVentasFacturas">
			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordionVentasFacturas" href="#VentasFacturas"><span class="caret"></span> Configuración de cuentas para asiento general de ventas con facturas (Esta configuración aplica cuando no está configurado los asientos de forma personalizada)</a>
				<div id="VentasFacturas" class="panel-collapse collapse">
					<form class="form-horizontal" method="POST">
						<div class="panel panel-info">
							<div class="table-responsive">
								<table class="table">
									<tr class="info">
										<td>Descripción</td>
										<td>Detalle</td>
										<td>Tipo</td>
										<td>Cuenta contable</td>
										<td class="col-xs-1 text-center">Eliminar</td>
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
										$cuentas_programadas_ingreso = obtenerCuentasProgramadas($id_asiento_tipo, $con, $ruc_empresa, $id_asiento_tipo, $tipo_asiento);
										$id_cuenta = $cuentas_programadas_ingreso['id_cuenta'];
										$codigo_cuenta = $cuentas_programadas_ingreso['codigo_cuenta'];
										$nombre_cuenta = $cuentas_programadas_ingreso['nombre_cuenta'];
										$id_registro = $cuentas_programadas_ingreso['id_asi_pro'];
									?>
										<tr class="active">
											<input type="hidden" id="id_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta; ?>">
											<td class="col-xs-3"><?php echo mb_strtoupper($concepto_cuenta, 'UTF-8') ?></td>
											<td class="col-xs-3"><?php echo ucfirst(($detalle)) ?></td>
											<td class="col-xs-1"><?php echo ucfirst(($tipo_saldo)) ?></td>
											<td class="col-xs-4">
												<input type="text" <?php echo empty($codigo_cuenta) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('','<?php echo $codigo_unico; ?>','<?php echo $id_asiento_tipo; ?>','<?php echo $tipo; ?>', '<?php echo $concepto_cuenta; ?>', '<?php echo $id_registro; ?>', '<?php echo $id_asiento_tipo; ?>');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta; ?>">
											</td>
											<td class="col-xs-1 text-center">
												<a class="btn btn-danger btn-xs" title="Eliminar cuenta" onclick="eliminar_cuenta('','<?php echo $codigo_unico; ?>','<?php echo $id_registro; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
											</td>
										</tr>
									<?php
									}

									//para iva en ventas con factura
									$sql_ratifa_iva = mysqli_query($con, "SELECT * FROM tarifa_iva WHERE porcentaje_iva>0 ORDER BY tarifa asc");
									while ($row = mysqli_fetch_array($sql_ratifa_iva)) {
										$codigo_unico = rand(100, 50000);
										$codigo_tarifa = $row['codigo'];
										$nombre_tarifa = "IVA " . $row['porcentaje_iva'] . "% en ventas con facturas";

										//PARA TRAER LAS CUENTAS ya guardadas
										$cuentas_programadas_ingreso = obtenerCuentasProgramadas('0', $con, $ruc_empresa, $codigo_tarifa, 'iva_ventas');
										$id_cuenta = $cuentas_programadas_ingreso['id_cuenta'];
										$codigo_cuenta = $cuentas_programadas_ingreso['codigo_cuenta'];
										$nombre_cuenta = $cuentas_programadas_ingreso['nombre_cuenta'];
										$id_registro = $cuentas_programadas_ingreso['id_asi_pro'];
									?>
										<tr class="active">
											<input type="hidden" id="id_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta; ?>">
											<td class="col-xs-3"><?php echo mb_strtoupper($nombre_tarifa, 'UTF-8') ?></td>
											<td class="col-xs-3">Aplica a todas las facturas de venta que tengan este porcentaje de IVA</td>
											<td class="col-xs-1">Pasivo</td>
											<td class="col-xs-4">
												<input type="text" <?php echo empty($codigo_cuenta) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('','<?php echo $codigo_unico; ?>', '<?php echo $codigo_tarifa; ?>','iva_ventas', '<?php echo $nombre_tarifa; ?>', '<?php echo $id_registro; ?>', '0');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta; ?>">
											</td>
											<td class="col-xs-1 text-center">
												<a class="btn btn-danger btn-xs" title="Eliminar cuenta" onclick="eliminar_cuenta('','<?php echo $codigo_unico; ?>','<?php echo $id_registro; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
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

			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordionVentasFacturas" href="#TarifaIvaVentas"><span class="caret"></span> Contabilización de ventas con facturas en base a tarifas de IVA (opcional) <?php echo $contar_cuentas_por_tarifa_iva; ?> </a>
				<div id="TarifaIvaVentas" class="panel-collapse collapse">
					<form class="form-horizontal" method="POST">
						<div class="panel panel-info">
							<div class="table-responsive">
								<table class="table">
									<tr class="info">
										<td>Nombre Tarifa IVA en ventas</td>
										<td>Cuenta de activo CXC</td>
										<td>Cuenta de ingreso</td>
										<td>Cuenta de IVA ventas</td>
									</tr>
									<?php
									$sql_ratifa_iva = mysqli_query($con, "SELECT * FROM tarifa_iva ORDER BY tarifa asc");
									while ($row = mysqli_fetch_array($sql_ratifa_iva)) {
										$codigo_unico = rand(100, 50000);
										$codigo_tarifa = $row['codigo'];
										$nombre_tarifa = $row['tarifa'];

										//PARA TRAER LAS CUENTAS ya guardada de cuentas de ingreso por ventas
										$cuentas_programadas_cxc = obtenerCuentasProgramadas('0', $con, $ruc_empresa, $codigo_tarifa, 'tarifa_iva_ventas_cxc');
										$id_cuenta__cxc = $cuentas_programadas_cxc['id_cuenta'];
										$codigo_cuenta_cxc = $cuentas_programadas_cxc['codigo_cuenta'];
										$nombre_cuenta_cxc = $cuentas_programadas_cxc['nombre_cuenta'];
										$id_registro_cxc = $cuentas_programadas_cxc['id_asi_pro'];

										$cuentas_programadas_ingreso = obtenerCuentasProgramadas('0', $con, $ruc_empresa, $codigo_tarifa, 'tarifa_iva_ventas');
										$id_cuenta_ingreso = $cuentas_programadas_ingreso['id_cuenta'];
										$codigo_cuenta_ingreso = $cuentas_programadas_ingreso['codigo_cuenta'];
										$nombre_cuenta_ingreso = $cuentas_programadas_ingreso['nombre_cuenta'];
										$id_registro_ingreso = $cuentas_programadas_ingreso['id_asi_pro'];

										$cuentas_programadas_iva_ventas = obtenerCuentasProgramadas('0', $con, $ruc_empresa, $codigo_tarifa, 'tarifa_iva_ventas_iva');
										$id_cuenta_iva_ventas = $cuentas_programadas_iva_ventas['id_cuenta'];
										$codigo_cuenta_iva_ventas = $cuentas_programadas_iva_ventas['codigo_cuenta'];
										$nombre_cuenta_iva_ventas = $cuentas_programadas_iva_ventas['nombre_cuenta'];
										$id_registro_iva_ventas = $cuentas_programadas_iva_ventas['id_asi_pro'];

									?>
										<tr class="active">
											<input type="hidden" id="id_cuenta_uno<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta_ingreso; ?>">
											<input type="hidden" id="id_cuenta_dos<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta_iva_ventas; ?>">
											<input type="hidden" id="id_cuenta_tres<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta__cxc; ?>">
											<td class="col-xs-3">Tarifa <?php echo mb_strtoupper($nombre_tarifa, 'UTF-8') ?></td>
											<td class="col-xs-3">
												<div class="input-group">
													<input type="text" <?php echo empty($codigo_cuenta_cxc) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable_uno<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('_uno','<?php echo $codigo_unico; ?>', '<?php echo $codigo_tarifa; ?>','tarifa_iva_ventas_cxc', '<?php echo $nombre_tarifa; ?>', '<?php echo $id_registro_cxc; ?>','0');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta_cxc; ?>">
													<span class="input-group-btn btn-md"><a class="btn btn-danger btn-sm" title="Eliminar cuenta" onclick="eliminar_cuenta('_uno','<?php echo $codigo_unico; ?>','<?php echo $id_registro_cxc; ?>');"><i class="glyphicon glyphicon-trash"></i></a></span>
												</div>
											</td>
											<td class="col-xs-3">
												<div class="input-group">
													<input type="text" <?php echo empty($codigo_cuenta_ingreso) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable_dos<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('_dos','<?php echo $codigo_unico; ?>', '<?php echo $codigo_tarifa; ?>','tarifa_iva_ventas', '<?php echo $nombre_tarifa; ?>', '<?php echo $id_registro_ingreso; ?>','0');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta_ingreso; ?>">
													<span class="input-group-btn btn-md"><a class="btn btn-danger btn-sm" title="Eliminar cuenta" onclick="eliminar_cuenta('_dos','<?php echo $codigo_unico; ?>','<?php echo $id_registro_ingreso; ?>');"><i class="glyphicon glyphicon-trash"></i></a></span>
												</div>
											</td>
											<td class="col-xs-3">
												<div class="input-group">
													<input type="text" <?php echo empty($codigo_cuenta_iva_ventas) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable_tres<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('_tres','<?php echo $codigo_unico; ?>', '<?php echo $codigo_tarifa; ?>','tarifa_iva_ventas_iva', '<?php echo $nombre_tarifa; ?>', '<?php echo $id_registro_iva_ventas; ?>','0');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta_iva_ventas; ?>">
													<span class="input-group-btn btn-md"><a class="btn btn-danger btn-sm" title="Eliminar cuenta" onclick="eliminar_cuenta('_tres','<?php echo $codigo_unico; ?>','<?php echo $id_registro_iva_ventas; ?>');"><i class="glyphicon glyphicon-trash"></i></a></span>
												</div>
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

			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" onclick="listado_clientes_facturas()" data-parent="#accordionVentasFacturas" href="#ClientesVentas"><span class="caret"></span> Contabilización de ventas con factura en base a clientes (opcional) <?php echo $contar_cuentas_por_clientes; ?></a>
				<div id="ClientesVentas" class="panel-collapse collapse">
					<div class="listado_clientes_facturas"></div><!-- Carga los datos ajax -->
				</div>
			</div>

			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" onclick="listado_productos_ventas()" data-parent="#accordionVentasFacturas" href="#ProductosVentasFactura"><span class="caret"></span> Contabilización de ventas con factura en base a productos (opcional) <?php echo $contar_cuentas_por_productos; ?></a>
				<div id="ProductosVentasFactura" class="panel-collapse collapse">
					<div class="listado_productos_ventas"></div><!-- Carga los datos ajax -->
				</div>
			</div>

			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" onclick="listado_categorias_ventas()" data-parent="#accordionVentasFacturas" href="#CategoriasVentasFacturas"><span class="caret"></span> Contabilización de ventas con factura en base a categorías (opcional) <?php echo $contar_cuentas_por_categorias; ?></a>
				<div id="CategoriasVentasFacturas" class="panel-collapse collapse">
					<div class="listado_categorias_ventas"></div><!-- Carga los datos ajax -->
				</div>
			</div>

			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" onclick="listado_marcas_ventas()" data-parent="#accordionVentasFacturas" href="#MarcasVentasFacturas"><span class="caret"></span> Contabilización de ventas con factura por marcas de productos (opcional) <?php echo $contar_cuentas_por_marcas; ?></a>
				<div id="MarcasVentasFacturas" class="panel-collapse collapse">
					<div class="listado_marcas_ventas"></div><!-- Carga los datos ajax -->
				</div>
			</div>
		</div>
	<?php
	}
	//HASTA AQUI FACTURAS

	//para recibos de venta
	if ($tipo == 'recibos') {
		$query_asientos_tipo = mysqli_query($con, "SELECT * FROM asientos_tipo WHERE tipo_asiento = '" . $tipo . "' order by concepto_cuenta asc ");
	?>
		<div class="panel-group" id="accordionRecibosVenta">
			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordionRecibosVenta" href="#ReciboVenta"><span class="caret"></span> Configuración de cuentas para asiento general de ventas con recibos (Esta configuración aplica cuando no está configurado los asientos de forma personalizada)</a>
				<div id="ReciboVenta" class="panel-collapse collapse">
					<form class="form-horizontal" method="POST">
						<div class="panel panel-info">
							<div class="table-responsive">
								<table class="table">
									<tr class="info">
										<td>Descripción</td>
										<td>Detalle</td>
										<td>Tipo</td>
										<td>Cuenta contable</td>
										<td class="col-xs-1 text-center">Eliminar</td>
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
										$cuentas_programadas_ingreso = obtenerCuentasProgramadas($id_asiento_tipo, $con, $ruc_empresa, $id_asiento_tipo, $tipo_asiento);
										$id_cuenta = $cuentas_programadas_ingreso['id_cuenta'];
										$codigo_cuenta = $cuentas_programadas_ingreso['codigo_cuenta'];
										$nombre_cuenta = $cuentas_programadas_ingreso['nombre_cuenta'];
										$id_registro = $cuentas_programadas_ingreso['id_asi_pro'];
									?>
										<tr class="active">
											<input type="hidden" id="id_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta; ?>">
											<td class="col-xs-3"><?php echo mb_strtoupper($concepto_cuenta, 'UTF-8') ?></td>
											<td class="col-xs-3"><?php echo ucfirst(($detalle)) ?></td>
											<td class="col-xs-1"><?php echo ucfirst(($tipo_saldo)) ?></td>
											<td class="col-xs-4">
												<input type="text" <?php echo empty($codigo_cuenta) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('','<?php echo $codigo_unico; ?>','<?php echo $id_asiento_tipo; ?>','<?php echo $tipo; ?>', '<?php echo $concepto_cuenta; ?>', '<?php echo $id_registro; ?>', '<?php echo $id_asiento_tipo; ?>');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta; ?>">
											</td>
											<td class="col-xs-1 text-center">
												<a class="btn btn-danger btn-xs" title="Eliminar cuenta" onclick="eliminar_cuenta('','<?php echo $codigo_unico; ?>','<?php echo $id_registro; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
											</td>
										</tr>
									<?php
									}

									//para el iva de los recibos
									$sql_ratifa_iva = mysqli_query($con, "SELECT * FROM tarifa_iva WHERE porcentaje_iva>0 ORDER BY tarifa asc");
									while ($row = mysqli_fetch_array($sql_ratifa_iva)) {
										$codigo_unico = rand(100, 50000);
										$codigo_tarifa = $row['codigo'];
										$nombre_tarifa = "IVA " . $row['porcentaje_iva'] . "% en ventas con recibos";

										//PARA TRAER LAS CUENTAS ya guardadas
										$cuentas_programadas_ingreso = obtenerCuentasProgramadas('0', $con, $ruc_empresa, $codigo_tarifa, 'iva_ventas_recibos');
										$id_cuenta = $cuentas_programadas_ingreso['id_cuenta'];
										$codigo_cuenta = $cuentas_programadas_ingreso['codigo_cuenta'];
										$nombre_cuenta = $cuentas_programadas_ingreso['nombre_cuenta'];
										$id_registro = $cuentas_programadas_ingreso['id_asi_pro'];
									?>
										<tr class="active">
											<input type="hidden" id="id_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta; ?>">
											<td class="col-xs-3"><?php echo mb_strtoupper($nombre_tarifa, 'UTF-8') ?></td>
											<td class="col-xs-3">Aplica a todas los recibos de venta que tengan este porcentaje de IVA</td>
											<td class="col-xs-1">Pasivo</td>
											<td class="col-xs-4">
												<input type="text" <?php echo empty($codigo_cuenta) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('','<?php echo $codigo_unico; ?>', '<?php echo $codigo_tarifa; ?>','iva_ventas_recibos', '<?php echo $nombre_tarifa; ?>', '<?php echo $id_registro; ?>','0');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta; ?>">
											</td>
											<td class="col-xs-1 text-center">
												<a class="btn btn-danger btn-xs" title="Eliminar cuenta" onclick="eliminar_cuenta('','<?php echo $codigo_unico; ?>','<?php echo $id_registro; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
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

			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordionRecibosVenta" href="#TarifaIvaVentasRecibos"><span class="caret"></span> Contabilización de ventas con recibos en base a tarifas de IVA (opcional)</a>
				<div id="TarifaIvaVentasRecibos" class="panel-collapse collapse">
					<form class="form-horizontal" method="POST">
						<div class="panel panel-info">
							<div class="table-responsive">
								<table class="table">
									<tr class="info">
										<td>Subtotal Tarifa IVA en ventas</td>
										<td>Tipo</td>
										<td>Cuenta contable</td>
										<td class="col-xs-1 text-center">Eliminar</td>
									</tr>
									<?php
									$sql_ratifa_iva = mysqli_query($con, "SELECT * FROM tarifa_iva ORDER BY tarifa asc");
									while ($row = mysqli_fetch_array($sql_ratifa_iva)) {
										$codigo_unico = rand(100, 50000);
										$codigo_tarifa = $row['codigo'];
										$nombre_tarifa = $row['tarifa'];

										//PARA TRAER LAS CUENTAS ya guardada de cuentas de ingreso por ventas
										$cuentas_programadas_ingreso = obtenerCuentasProgramadas('0', $con, $ruc_empresa, $codigo_tarifa, 'tarifa_iva_ventas_recibos');
										$id_cuenta_ingreso = $cuentas_programadas_ingreso['id_cuenta'];
										$codigo_cuenta_ingreso = $cuentas_programadas_ingreso['codigo_cuenta'];
										$nombre_cuenta_ingreso = $cuentas_programadas_ingreso['nombre_cuenta'];
										$id_registro_ingreso = $cuentas_programadas_ingreso['id_asi_pro'];

										$cuentas_programadas_iva_ventas = obtenerCuentasProgramadas('0', $con, $ruc_empresa, $codigo_tarifa, 'tarifa_iva_ventas_recibos_iva');
										$id_cuenta_iva_ventas = $cuentas_programadas_iva_ventas['id_cuenta'];
										$codigo_cuenta_iva_ventas = $cuentas_programadas_iva_ventas['codigo_cuenta'];
										$nombre_cuenta_iva_ventas = $cuentas_programadas_iva_ventas['nombre_cuenta'];
										$id_registro_iva_ventas = $cuentas_programadas_iva_ventas['id_asi_pro'];

										$cuentas_programadas_cxc = obtenerCuentasProgramadas('0', $con, $ruc_empresa, $codigo_tarifa, 'tarifa_iva_ventas_recibos_cxc');
										$id_cuenta__cxc = $cuentas_programadas_cxc['id_cuenta'];
										$codigo_cuenta_cxc = $cuentas_programadas_cxc['codigo_cuenta'];
										$nombre_cuenta_cxc = $cuentas_programadas_cxc['nombre_cuenta'];
										$id_registro_cxc = $cuentas_programadas_cxc['id_asi_pro'];
									?>
										<tr class="active">
											<input type="hidden" id="id_cuenta_uno<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta_ingreso; ?>">
											<input type="hidden" id="id_cuenta_dos<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta_iva_ventas; ?>">
											<input type="hidden" id="id_cuenta_tres<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta__cxc; ?>">
											<td class="col-xs-3">Tarifa <?php echo mb_strtoupper($nombre_tarifa, 'UTF-8') ?></td>
											<td class="col-xs-3">
												<div class="input-group">
													<input type="text" <?php echo empty($codigo_cuenta_cxc) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable_uno<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('_uno','<?php echo $codigo_unico; ?>', '<?php echo $codigo_tarifa; ?>','tarifa_iva_ventas_recibos_cxc', '<?php echo $nombre_tarifa; ?>', '<?php echo $id_registro_cxc; ?>','0');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta_cxc; ?>">
													<span class="input-group-btn btn-md"><a class="btn btn-danger btn-sm" title="Eliminar cuenta" onclick="eliminar_cuenta('_uno','<?php echo $codigo_unico; ?>','<?php echo $id_registro_cxc; ?>');"><i class="glyphicon glyphicon-trash"></i></a></span>
												</div>
											</td>
											<td class="col-xs-3">
												<div class="input-group">
													<input type="text" <?php echo empty($codigo_cuenta_ingreso) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable_dos<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('_dos','<?php echo $codigo_unico; ?>', '<?php echo $codigo_tarifa; ?>','tarifa_iva_ventas_recibos', '<?php echo $nombre_tarifa; ?>', '<?php echo $id_registro_ingreso; ?>','0');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta_ingreso; ?>">
													<span class="input-group-btn btn-md"><a class="btn btn-danger btn-sm" title="Eliminar cuenta" onclick="eliminar_cuenta('_dos','<?php echo $codigo_unico; ?>','<?php echo $id_registro_ingreso; ?>');"><i class="glyphicon glyphicon-trash"></i></a></span>
												</div>
											</td>
											<td class="col-xs-3">
												<div class="input-group">
													<input type="text" <?php echo empty($codigo_cuenta_iva_ventas) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable_tres<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('_tres','<?php echo $codigo_unico; ?>', '<?php echo $codigo_tarifa; ?>','tarifa_iva_ventas_recibos_iva', '<?php echo $nombre_tarifa; ?>', '<?php echo $id_registro_iva_ventas; ?>','0');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta_iva_ventas; ?>">
													<span class="input-group-btn btn-md"><a class="btn btn-danger btn-sm" title="Eliminar cuenta" onclick="eliminar_cuenta('_tres','<?php echo $codigo_unico; ?>','<?php echo $id_registro_iva_ventas; ?>');"><i class="glyphicon glyphicon-trash"></i></a></span>
												</div>
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

			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" onclick="listado_clientes_recibos()" data-parent="#accordionRecibosVenta" href="#ClientesVentasRecibos"><span class="caret"></span> Contabilización de ventas con recibos en base a clientes (opcional)</a>
				<div id="ClientesVentasRecibos" class="panel-collapse collapse">
					<div class="listado_clientes_recibos"></div><!-- Carga los datos ajax -->
				</div>
			</div>

			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" onclick="listado_productos_ventas_recibos()" data-parent="#accordionRecibosVenta" href="#ProductosVentasRecibos"><span class="caret"></span> Contabilización de ventas con recibos en base a productos (opcional)</a>
				<div id="ProductosVentasRecibos" class="panel-collapse collapse">
					<div class="listado_productos_ventas_recibos"></div><!-- Carga los datos ajax -->
				</div>
			</div>

			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" onclick="listado_categorias_ventas_recibos()" data-parent="#accordionRecibosVenta" href="#CategoriasVentasRecibos"><span class="caret"></span> Contabilización de ventas con recibos en base a categorías (opcional)</a>
				<div id="CategoriasVentasRecibos" class="panel-collapse collapse">
					<div class="listado_categorias_ventas_recibos"></div><!-- Carga los datos ajax -->
				</div>
			</div>

			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" onclick="listado_marcas_ventas_recibos()" data-parent="#accordionRecibosVenta" href="#MarcasVentasRecibos"><span class="caret"></span> Contabilización de ventas con recibos por marcas de productos (opcional)</a>
				<div id="MarcasVentasRecibos" class="panel-collapse collapse">
					<div class="listado_marcas_ventas_recibos"></div><!-- Carga los datos ajax -->
				</div>
			</div>
		</div>
	<?php
	}

	//para compras en general
	if ($tipo == 'compras_servicios') {
		$query_asientos_tipo = mysqli_query($con, "SELECT * FROM asientos_tipo WHERE tipo_asiento = '" . $tipo . "' order by concepto_cuenta asc ");
	?>
		<div class="panel-group" id="accordionCompras">
			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordionCompras" href="#CuentasCompras"><span class="caret"></span> Configuración de cuentas para asiento general de adquisiciones (Esta configuración aplica cuando no está configurado los asientos de forma personalizada)</a>
				<div id="CuentasCompras" class="panel-collapse collapse">
					<form class="form-horizontal" method="POST">
						<div class="panel panel-info">
							<div class="table-responsive">
								<table class="table">
									<tr class="info">
										<td>Descripción</td>
										<td>Detalle</td>
										<td>Tipo</td>
										<td>Cuenta contable</td>
										<td class="col-xs-1 text-center">Eliminar</td>
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
										$cuentas_programadas_ingreso = obtenerCuentasProgramadas($id_asiento_tipo, $con, $ruc_empresa, $id_asiento_tipo, $tipo_asiento);
										$id_cuenta = $cuentas_programadas_ingreso['id_cuenta'];
										$codigo_cuenta = $cuentas_programadas_ingreso['codigo_cuenta'];
										$nombre_cuenta = $cuentas_programadas_ingreso['nombre_cuenta'];
										$id_registro = $cuentas_programadas_ingreso['id_asi_pro'];
									?>
										<tr class="active">
											<input type="hidden" id="id_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta; ?>">
											<td class="col-xs-3"><?php echo mb_strtoupper($concepto_cuenta, 'UTF-8') ?></td>
											<td class="col-xs-3"><?php echo ucfirst(($detalle)) ?></td>
											<td class="col-xs-1"><?php echo ucfirst(($tipo_saldo)) ?></td>
											<td class="col-xs-4">
												<input type="text" <?php echo empty($codigo_cuenta) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('','<?php echo $codigo_unico; ?>','<?php echo $id_asiento_tipo; ?>','<?php echo $tipo; ?>', '<?php echo $concepto_cuenta; ?>', '<?php echo $id_registro; ?>', '<?php echo $id_asiento_tipo; ?>');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta; ?>">
											</td>
											<td class="col-xs-1 text-center">
												<a class="btn btn-danger btn-xs" title="Eliminar cuenta" onclick="eliminar_cuenta('','<?php echo $codigo_unico; ?>','<?php echo $id_registro; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
											</td>
										</tr>
									<?php
									}

									//para el iva en compras
									$sql_ratifa_iva = mysqli_query($con, "SELECT * FROM tarifa_iva WHERE porcentaje_iva>0 ORDER BY tarifa asc");
									while ($row = mysqli_fetch_array($sql_ratifa_iva)) {
										$codigo_unico = rand(100, 50000);
										$codigo_tarifa = $row['codigo'];
										$nombre_tarifa = "IVA " . $row['porcentaje_iva'] . "% en compras";

										//PARA TRAER LAS CUENTAS ya guardadas
										$cuentas_programadas_ingreso = obtenerCuentasProgramadas('0', $con, $ruc_empresa, $codigo_tarifa, 'iva_compras');
										$id_cuenta = $cuentas_programadas_ingreso['id_cuenta'];
										$codigo_cuenta = $cuentas_programadas_ingreso['codigo_cuenta'];
										$nombre_cuenta = $cuentas_programadas_ingreso['nombre_cuenta'];
										$id_registro = $cuentas_programadas_ingreso['id_asi_pro'];
									?>
										<tr class="active">
											<input type="hidden" id="id_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta; ?>">
											<td class="col-xs-3"><?php echo mb_strtoupper($nombre_tarifa, 'UTF-8') ?></td>
											<td class="col-xs-3">Cuenta de Iva en general para todas las compras con este porcentaje.</td>
											<td class="col-xs-1">Activo</td>
											<td class="col-xs-4">
												<input type="text" <?php echo empty($codigo_cuenta) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('','<?php echo $codigo_unico; ?>', '<?php echo $codigo_tarifa; ?>','iva_compras', '<?php echo $nombre_tarifa; ?>', '<?php echo $id_registro; ?>','0');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta; ?>">
											</td>
											<td class="col-xs-1 text-center">
												<a class="btn btn-danger btn-xs" title="Eliminar cuenta" onclick="eliminar_cuenta('','<?php echo $codigo_unico; ?>','<?php echo $id_registro; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
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

			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordionCompras" href="#TarifaIvaCompras"><span class="caret"></span> Contabilización de adquisiciones en base a tarifas de IVA (opcional)</a>
				<div id="TarifaIvaCompras" class="panel-collapse collapse">
					<form class="form-horizontal" method="POST">
						<div class="panel panel-info">
							<div class="table-responsive">
								<table class="table">
									<tr class="info">
										<td>Nombre Tarifa IVA en compras</td>
										<td>Cuenta de costo o gasto</td>
										<td>Cuenta de IVA compras</td>
										<td>Cuenta de pasivo CXP</td>
									</tr>
									<?php
									$sql_ratifa_iva = mysqli_query($con, "SELECT * FROM tarifa_iva ORDER BY tarifa asc");
									while ($row = mysqli_fetch_array($sql_ratifa_iva)) {
										$codigo_unico = rand(100, 50000);
										$codigo_tarifa = $row['codigo'];
										$nombre_tarifa = $row['tarifa'];

										//PARA TRAER LAS CUENTAS ya guardada segun la tarifa de iva en compras
										$cuentas_programadas_gasto = obtenerCuentasProgramadas('0', $con, $ruc_empresa, $codigo_tarifa, 'tarifa_iva_compras');
										$id_cuenta_gasto = $cuentas_programadas_gasto['id_cuenta'];
										$codigo_cuenta_gasto = $cuentas_programadas_gasto['codigo_cuenta'];
										$nombre_cuenta_gasto = $cuentas_programadas_gasto['nombre_cuenta'];
										$id_registro_gasto = $cuentas_programadas_gasto['id_asi_pro'];

										$cuentas_programadas_iva_compras = obtenerCuentasProgramadas('0', $con, $ruc_empresa, $codigo_tarifa, 'tarifa_iva_compras_iva');
										$id_cuenta_iva_compras = $cuentas_programadas_iva_compras['id_cuenta'];
										$codigo_cuenta_iva_compras = $cuentas_programadas_iva_compras['codigo_cuenta'];
										$nombre_cuenta_iva_compras = $cuentas_programadas_iva_compras['nombre_cuenta'];
										$id_registro_iva_compras = $cuentas_programadas_iva_compras['id_asi_pro'];

										$cuentas_programadas_cxp = obtenerCuentasProgramadas('0', $con, $ruc_empresa, $codigo_tarifa, 'tarifa_iva_compras_cxp');
										$id_cuenta_cxp = $cuentas_programadas_cxp['id_cuenta'];
										$codigo_cuenta_cxp = $cuentas_programadas_cxp['codigo_cuenta'];
										$nombre_cuenta_cxp = $cuentas_programadas_cxp['nombre_cuenta'];
										$id_registro_cxp = $cuentas_programadas_cxp['id_asi_pro'];
									?>
										<tr class="active">
											<input type="hidden" id="id_cuenta_uno<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta_gasto; ?>">
											<input type="hidden" id="id_cuenta_dos<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta_iva_compras; ?>">
											<input type="hidden" id="id_cuenta_tres<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta_cxp; ?>">
											<td class="col-xs-3">Tarifa <?php echo mb_strtoupper($nombre_tarifa, 'UTF-8') ?></td>
											<td class="col-xs-3">
												<div class="input-group">
													<input type="text" <?php echo empty($codigo_cuenta_gasto) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable_uno<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('_uno','<?php echo $codigo_unico; ?>', '<?php echo $codigo_tarifa; ?>','tarifa_iva_compras', '<?php echo $nombre_tarifa; ?>', '<?php echo $id_registro_gasto; ?>','0');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta_gasto; ?>">
													<span class="input-group-btn btn-md"><a class="btn btn-danger btn-sm" title="Eliminar cuenta" onclick="eliminar_cuenta('_uno','<?php echo $codigo_unico; ?>','<?php echo $id_registro_gasto; ?>');"><i class="glyphicon glyphicon-trash"></i></a></span>
												</div>
											</td>
											<td class="col-xs-3">
												<div class="input-group">
													<input type="text" <?php echo empty($codigo_cuenta_iva_compras) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable_dos<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('_dos','<?php echo $codigo_unico; ?>', '<?php echo $codigo_tarifa; ?>','tarifa_iva_compras_iva', '<?php echo $nombre_tarifa; ?>', '<?php echo $id_registro_iva_compras; ?>','0');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta_iva_compras; ?>">
													<span class="input-group-btn btn-md"><a class="btn btn-danger btn-sm" title="Eliminar cuenta" onclick="eliminar_cuenta('_dos','<?php echo $codigo_unico; ?>','<?php echo $id_registro_iva_compras; ?>');"><i class="glyphicon glyphicon-trash"></i></a></span>
												</div>
											</td>
											<td class="col-xs-3">
												<div class="input-group">
													<input type="text" <?php echo empty($codigo_cuenta_cxp) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable_tres<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('_tres','<?php echo $codigo_unico; ?>', '<?php echo $codigo_tarifa; ?>','tarifa_iva_compras_cxp', '<?php echo $nombre_tarifa; ?>', '<?php echo $id_registro_cxp; ?>','0');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta_cxp; ?>">
													<span class="input-group-btn btn-md"><a class="btn btn-danger btn-sm" title="Eliminar cuenta" onclick="eliminar_cuenta('_tres','<?php echo $codigo_unico; ?>','<?php echo $id_registro_cxp; ?>');"><i class="glyphicon glyphicon-trash"></i></a></span>
												</div>
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

			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" onclick="listado_proveedores()" data-parent="#accordionCompras" href="#ProveedoresCompras"><span class="caret"></span> Contabilización de adquisiciones en base a proveedores (opcional)</a>
				<div id="ProveedoresCompras" class="panel-collapse collapse">
					<div class="listado_proveedores"></div><!-- Carga los datos ajax -->
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
										<td>Cuenta contable</td>
										<td class="col-xs-1 text-center">Eliminar</td>
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
										$cuentas_programadas = obtenerCuentasProgramadas('0', $con, $ruc_empresa, $id_retencion, 'retenciones_compras');
										$id_cuenta = $cuentas_programadas['id_cuenta'];
										$codigo_cuenta = $cuentas_programadas['codigo_cuenta'];
										$nombre_cuenta = $cuentas_programadas['nombre_cuenta'];
										$id_registro = $cuentas_programadas['id_asi_pro'];
									?>
										<tr class="active">
											<input type="hidden" id="id_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta; ?>">
											<td class="col-xs-2"><?php echo mb_strtoupper($concepto_ret, 'UTF-8') ?></td>
											<td class="col-xs-1"><?php echo $impuesto ?></td>
											<td class="col-xs-1"><?php echo $codigo_impuesto ?></td>
											<td class="col-xs-1"><?php echo $porcentaje_retencion . "%" ?></td>
											<td class="col-xs-1">Pasivo</td>
											<td class="col-xs-4">
												<input type="text" <?php echo empty($codigo_cuenta) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('','<?php echo $codigo_unico; ?>', '<?php echo $id_retencion; ?>','retenciones_compras', '<?php echo $impuesto . $codigo_impuesto; ?>', '<?php echo $id_registro; ?>','0');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta; ?>">
											</td>
											<td class="col-xs-1 text-center">
												<a class="btn btn-danger btn-xs" title="Eliminar cuenta" onclick="eliminar_cuenta('','<?php echo $codigo_unico; ?>','<?php echo $id_registro; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
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
										<td>Cuenta contable</td>
										<td class="col-xs-1 text-center">Eliminar</td>
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
										$cuentas_programadas = obtenerCuentasProgramadas('0', $con, $ruc_empresa, $codigo_impuesto, 'retenciones_ventas');
										$id_cuenta = $cuentas_programadas['id_cuenta'];
										$codigo_cuenta = $cuentas_programadas['codigo_cuenta'];
										$nombre_cuenta = $cuentas_programadas['nombre_cuenta'];
										$id_registro = $cuentas_programadas['id_asi_pro'];

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
											<td class="col-xs-4">
												<input type="text" <?php echo empty($codigo_cuenta) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('','<?php echo $codigo_unico; ?>', '<?php echo $id_retencion; ?>','retenciones_ventas', '<?php echo $impuesto . $codigo_impuesto; ?>', '<?php echo $id_registro; ?>','0');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta; ?>">
											</td>
											<td class="col-xs-1 text-center">
												<a class="btn btn-danger btn-xs" title="Eliminar cuenta" onclick="eliminar_cuenta('','<?php echo $codigo_unico; ?>','<?php echo $id_registro; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
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
		<div class="panel-group" id="accordionCuentasIngresosEgresos">
			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordionCuentasIngresosEgresos" href="#CuentasIngresos"><span class="caret"></span> Cuentas contables para opciones de ingresos (Obligatorio)</a>
				<div id="CuentasIngresos" class="panel-collapse collapse">
					<form class="form-horizontal" method="POST">
						<div class="panel panel-info">
							<div class="table-responsive">
								<table class="table">
									<tr class="info">
										<td>Descripción</td>
										<td>Tipo</td>
										<td>Cuenta contable</td>
										<td class="col-xs-1 text-center">Eliminar</td>
									</tr>
									<?php
									$query_registros = mysqli_query($con, "SELECT * FROM opciones_ingresos_egresos WHERE ruc_empresa = '" . $ruc_empresa . "' and tipo_opcion='1' and status='1' order by descripcion asc");
									while ($row_registro = mysqli_fetch_array($query_registros)) {
										$codigo_unico = rand(100, 50000);
										$id_ingreso = $row_registro['id'];
										$descripcion = $row_registro['descripcion'];

										//para mostrar las cuentas que ya estan guardadas
										$cuentas_programadas = obtenerCuentasProgramadas('0', $con, $ruc_empresa, $id_ingreso, 'opcion_ingreso');
										$id_cuenta = $cuentas_programadas['id_cuenta'];
										$codigo_cuenta = $cuentas_programadas['codigo_cuenta'];
										$nombre_cuenta = $cuentas_programadas['nombre_cuenta'];
										$id_registro = $cuentas_programadas['id_asi_pro'];
									?>
										<tr class="active">
											<input type="hidden" id="id_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta; ?>">
											<td class="col-xs-4"><?php echo mb_strtoupper($descripcion, 'UTF-8'); ?></td>
											<td class="col-xs-1">Activo/Pasivo/Costo/Gasto</td>
											<td class="col-xs-4">
												<input type="text" <?php echo empty($codigo_cuenta) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('','<?php echo $codigo_unico; ?>', '<?php echo $id_ingreso; ?>','opcion_ingreso', '<?php echo $descripcion; ?>', '<?php echo $id_registro; ?>','0');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta; ?>">
											</td>
											<td class="col-xs-1 text-center">
												<a class="btn btn-danger btn-xs" title="Eliminar cuenta" onclick="eliminar_cuenta('','<?php echo $codigo_unico; ?>','<?php echo $id_registro; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
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

			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordionCuentasIngresosEgresos" href="#CuentasEgresos"><span class="caret"></span> Cuentas contables para opciones de egresos (Obligatorio)</a>
				<div id="CuentasEgresos" class="panel-collapse collapse">
					<form class="form-horizontal" method="POST">
						<div class="panel panel-info">
							<div class="table-responsive">
								<table class="table">
									<tr class="info">
										<td>Descripción</td>
										<td>Tipo</td>
										<td>Cuenta contable</td>
										<td class="col-xs-1 text-center">Eliminar</td>
									</tr>
									<?php
									$query_registros = mysqli_query($con, "SELECT * FROM opciones_ingresos_egresos WHERE ruc_empresa = '" . $ruc_empresa . "' and tipo_opcion='2' and status='1' order by descripcion asc");
									while ($row_registro = mysqli_fetch_array($query_registros)) {
										$codigo_unico = rand(100, 50000);
										$id_ingreso = $row_registro['id'];
										$descripcion = $row_registro['descripcion'];

										//para mostrar las cuentas que ya estan guardadas
										$cuentas_programadas = obtenerCuentasProgramadas('0', $con, $ruc_empresa, $id_ingreso, 'opcion_egreso');
										$id_cuenta = $cuentas_programadas['id_cuenta'];
										$codigo_cuenta = $cuentas_programadas['codigo_cuenta'];
										$nombre_cuenta = $cuentas_programadas['nombre_cuenta'];
										$id_registro = $cuentas_programadas['id_asi_pro'];
									?>
										<tr class="active">
											<input type="hidden" id="id_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta; ?>">
											<td class="col-xs-4"><?php echo mb_strtoupper($descripcion, 'UTF-8'); ?></td>
											<td class="col-xs-1">Activo/Pasivo/Costo/Gasto</td>
											<td class="col-xs-4">
												<input type="text" <?php echo empty($codigo_cuenta) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('','<?php echo $codigo_unico; ?>', '<?php echo $id_ingreso; ?>','opcion_egreso', '<?php echo $descripcion; ?>', '<?php echo $id_registro; ?>','0');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta; ?>">
											</td>
											<td class="col-xs-1 text-center">
												<a class="btn btn-danger btn-xs" title="Eliminar cuenta" onclick="eliminar_cuenta('','<?php echo $codigo_unico; ?>','<?php echo $id_registro; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
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
		<div class="panel-group" id="accordionCuentasCobrosPagos">
			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordionCuentasCobrosPagos" href="#CuentasCobros"><span class="caret"></span> Cuentas contables para formas de cobros en ingresos (Obligatorio)</a>
				<div id="CuentasCobros" class="panel-collapse collapse">
					<form class="form-horizontal" method="POST">
						<div class="panel panel-info">
							<div class="table-responsive">
								<table class="table">
									<tr class="info">
										<td>Descripción</td>
										<td>Tipo</td>
										<td>Cuenta contable</td>
										<td class="col-xs-1 text-center">Eliminar</td>
									</tr>
									<?php
									$query_registros = mysqli_query($con, "SELECT * FROM opciones_cobros_pagos WHERE ruc_empresa = '" . $ruc_empresa . "' and tipo_opcion='1' and status='1' order by descripcion asc");
									while ($row_registro = mysqli_fetch_array($query_registros)) {
										$codigo_unico = rand(100, 50000);
										$id_ingreso = $row_registro['id'];
										$descripcion = $row_registro['descripcion'];

										//para mostrar las cuentas que ya estan guardadas
										$cuentas_programadas = obtenerCuentasProgramadas('0', $con, $ruc_empresa, $id_ingreso, 'opcion_cobro');
										$id_cuenta = $cuentas_programadas['id_cuenta'];
										$codigo_cuenta = $cuentas_programadas['codigo_cuenta'];
										$nombre_cuenta = $cuentas_programadas['nombre_cuenta'];
										$id_registro = $cuentas_programadas['id_asi_pro'];
									?>
										<tr class="active">
											<input type="hidden" id="id_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta; ?>">
											<td class="col-xs-4"><?php echo mb_strtoupper($descripcion, 'UTF-8'); ?></td>
											<td class="col-xs-1">Activo</td>
											<td class="col-xs-4">
												<input type="text" <?php echo empty($codigo_cuenta) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('','<?php echo $codigo_unico; ?>', '<?php echo $id_ingreso; ?>','opcion_cobro', '<?php echo $descripcion; ?>', '<?php echo $id_registro; ?>','0');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta; ?>">
											</td>
											<td class="col-xs-1 text-center">
												<a class="btn btn-danger btn-xs" title="Eliminar cuenta" onclick="eliminar_cuenta('','<?php echo $codigo_unico; ?>','<?php echo $id_registro; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
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

			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordionCuentasCobrosPagos" href="#CuentasPagos"><span class="caret"></span> Cuentas contables para formas de pagos en egresos (Obligatorio)</a>
				<div id="CuentasPagos" class="panel-collapse collapse">
					<form class="form-horizontal" method="POST">
						<div class="panel panel-info">
							<div class="table-responsive">
								<table class="table">
									<tr class="info">
										<td>Descripción</td>
										<td>Tipo</td>
										<td>Cuenta contable</td>
										<td class="col-xs-1 text-center">Eliminar</td>
									</tr>
									<?php
									$query_registros = mysqli_query($con, "SELECT * FROM opciones_cobros_pagos WHERE ruc_empresa = '" . $ruc_empresa . "' and tipo_opcion='2' and status='1' order by descripcion asc");
									while ($row_registro = mysqli_fetch_array($query_registros)) {
										$codigo_unico = rand(100, 50000);
										$id_ingreso = $row_registro['id'];
										$descripcion = $row_registro['descripcion'];

										//para mostrar las cuentas que ya estan guardadas
										$cuentas_programadas = obtenerCuentasProgramadas('0', $con, $ruc_empresa, $id_ingreso, 'opcion_pago');
										$id_cuenta = $cuentas_programadas['id_cuenta'];
										$codigo_cuenta = $cuentas_programadas['codigo_cuenta'];
										$nombre_cuenta = $cuentas_programadas['nombre_cuenta'];
										$id_registro = $cuentas_programadas['id_asi_pro'];
									?>
										<tr class="active">
											<input type="hidden" id="id_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta; ?>">
											<td class="col-xs-4"><?php echo mb_strtoupper($descripcion, 'UTF-8'); ?></td>
											<td class="col-xs-1">Activo</td>
											<td class="col-xs-4">
												<input type="text" <?php echo empty($codigo_cuenta) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('','<?php echo $codigo_unico; ?>', '<?php echo $id_ingreso; ?>','opcion_pago', '<?php echo $descripcion; ?>', '<?php echo $id_registro; ?>','0');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta; ?>">
											</td>
											<td class="col-xs-1 text-center">
												<a class="btn btn-danger btn-xs" title="Eliminar cuenta" onclick="eliminar_cuenta('','<?php echo $codigo_unico; ?>','<?php echo $id_registro; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
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

			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordionCuentasCobrosPagos" href="#CuentasBancos"><span class="caret"></span> Cuentas contables para cuentas bancarias (Obligatorio)</a>
				<div id="CuentasBancos" class="panel-collapse collapse">
					<form class="form-horizontal" method="POST">
						<div class="panel panel-info">
							<div class="table-responsive">
								<table class="table">
									<tr class="info">
										<td>Cuenta bancaria</td>
										<td>Tipo</td>
										<td>Cuenta contable</td>
										<td class="col-xs-1 text-center">Eliminar</td>
									</tr>
									<?php
									$cuentas = mysqli_query($con, "SELECT cue_ban.numero_cuenta as numero_cuenta, cue_ban.id_cuenta as id_cuenta, concat(ban_ecu.nombre_banco,' ',cue_ban.numero_cuenta,' ', if(cue_ban.id_tipo_cuenta=1,'Aho','Cte')) as cuenta_bancaria FROM cuentas_bancarias as cue_ban INNER JOIN bancos_ecuador as ban_ecu ON cue_ban.id_banco=ban_ecu.id_bancos WHERE cue_ban.ruc_empresa = '" . $ruc_empresa . "' ");
									while ($row = mysqli_fetch_array($cuentas)) {
										$codigo_unico = rand(100, 50000);
										$id_cuenta_bancaria = $row['id_cuenta'];
										$cuenta_bancaria = $row['cuenta_bancaria'];
										$numero_cuenta = $row['numero_cuenta'];

										//para mostrar las cuentas que ya estan guardadas
										$cuentas_programadas = obtenerCuentasProgramadas('0', $con, $ruc_empresa, $id_cuenta_bancaria, 'bancos');
										$id_cuenta = $cuentas_programadas['id_cuenta'];
										$codigo_cuenta = $cuentas_programadas['codigo_cuenta'];
										$nombre_cuenta = $cuentas_programadas['nombre_cuenta'];
										$id_registro = $cuentas_programadas['id_asi_pro'];
									?>
										<tr class="active">
											<input type="hidden" id="id_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta; ?>">
											<td class="col-xs-3"><?php echo mb_strtoupper($cuenta_bancaria, 'UTF-8') ?></td>
											<td class="col-xs-2">Activo</td>
											<td class="col-xs-4">
												<input type="text" <?php echo empty($codigo_cuenta) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('','<?php echo $codigo_unico; ?>', '<?php echo $id_cuenta_bancaria; ?>','bancos', '<?php echo $numero_cuenta; ?>', '<?php echo $id_registro; ?>','0');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta; ?>">
											</td>
											<td class="col-xs-1 text-center">
												<a class="btn btn-danger btn-xs" title="Eliminar cuenta" onclick="eliminar_cuenta('','<?php echo $codigo_unico; ?>','<?php echo $id_registro; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
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


	//para las cuentas en general del rol de pagos
	if ($tipo == 'rol_pagos') {
		$query_asientos_tipo = mysqli_query($con, "SELECT * FROM asientos_tipo WHERE tipo_asiento = '" . $tipo . "' order by concepto_cuenta asc ");
	?>
		<div class="panel-group" id="accordionRolPagos">
			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordionRolPagos" href="#CuentasRolPagos"><span class="caret"></span> Configuración de cuentas para asiento general de rol de pagos (Esta configuración aplica cuando no está configurado los asientos de forma personalizada)</a>
				<div id="CuentasRolPagos" class="panel-collapse collapse">
					<form class="form-horizontal" method="POST">
						<div class="panel panel-info">
							<div class="table-responsive">
								<table class="table">
									<tr class="info">
										<td>Descripción</td>
										<td>Detalle</td>
										<td>Tipo</td>
										<td>Cuenta contable</td>
										<td class="col-xs-1 text-center">Eliminar</td>
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
										$cuentas_programadas = obtenerCuentasProgramadas($id_asiento_tipo, $con, $ruc_empresa, $id_asiento_tipo, 'rol_pagos');
										$id_cuenta = $cuentas_programadas['id_cuenta'];
										$codigo_cuenta = $cuentas_programadas['codigo_cuenta'];
										$nombre_cuenta = $cuentas_programadas['nombre_cuenta'];
										$id_registro = $cuentas_programadas['id_asi_pro'];
									?>
										<tr class="active">
											<input type="hidden" id="id_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta; ?>">
											<td class="col-xs-3"><?php echo mb_strtoupper($concepto_cuenta, 'UTF-8') ?></td>
											<td class="col-xs-3"><?php echo ucfirst(($detalle)) ?></td>
											<td class="col-xs-1"><?php echo ucfirst(($tipo_saldo)) ?></td>
											<td class="col-xs-4">
												<input type="text" <?php echo empty($codigo_cuenta) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('','<?php echo $codigo_unico; ?>','<?php echo $id_asiento_tipo; ?>','<?php echo $tipo; ?>', '<?php echo $concepto_cuenta; ?>', '<?php echo $id_registro; ?>', '<?php echo $id_asiento_tipo; ?>');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta; ?>">
											</td>
											<td class="col-xs-1 text-center">
												<a class="btn btn-danger btn-xs" title="Eliminar cuenta" onclick="eliminar_cuenta('','<?php echo $codigo_unico; ?>','<?php echo $id_registro; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
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

			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordionRolPagos" href="#Empleados"><span class="caret"></span> Configurar asiento individual para cada empleado (opcional)</a>
				<div id="Empleados" class="panel-collapse collapse">
					<div class="panel-body">
						<form class="form-horizontal" role="form">
							<input type="hidden" id="idEmpleado" value="">
							<div class="form-group">
								<div class="col-md-6">
									<div class="input-group">
										<span class="input-group-addon"><b>Empleado</b></span>
										<input type="text" name="datalistEmpleados" class="form-control input-sm" id="datalistEmpleados" value="" placeholder="Escribir para buscar un empleado" onkeyup='agregar_empleado();' autocomplete="off">
									</div>
								</div>
								<div class="col-sm-4">
									<button type="button" title="Mostrar" class="btn btn-info btn-sm" onclick="mostrar_cuentas_empleado()"><span class="glyphicon glyphicon-search"></span> Mostrar cuentas</button>
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
						<td>Cuenta contable</td>
						<td class="col-xs-1 text-center">Eliminar</td>
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
						$cuentas_programadas = obtenerCuentasProgramadas($id_asiento_tipo, $con, $ruc_empresa, $id_empleado, 'empleado');
						$id_cuenta = $cuentas_programadas['id_cuenta'];
						$codigo_cuenta = $cuentas_programadas['codigo_cuenta'];
						$nombre_cuenta = $cuentas_programadas['nombre_cuenta'];
						$id_registro = $cuentas_programadas['id_asi_pro'];
					?>
						<tr class="active">
							<input type="hidden" id="id_cuenta<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta; ?>">
							<td class="col-xs-3"><?php echo mb_strtoupper($concepto_cuenta, 'UTF-8') ?></td>
							<td class="col-xs-2"><?php echo ucfirst(($detalle)) ?></td>
							<td class="col-xs-1"><?php echo ucfirst(($tipo_saldo)) ?></td>
							<td class="col-xs-3">
								<input type="text" <?php echo empty($codigo_cuenta) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('','<?php echo $codigo_unico; ?>','<?php echo $id_empleado; ?>','empleado', '<?php echo $concepto_cuenta; ?>', '<?php echo $id_registro; ?>', '<?php echo $id_asiento_tipo; ?>');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta; ?>">
							</td>
							<td class="col-xs-1 text-center">
								<a class="btn btn-danger btn-xs" title="Eliminar cuenta" onclick="eliminar_cuenta('','<?php echo $codigo_unico; ?>','<?php echo $id_registro; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
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

if ($action == "guarda_cuenta") {
	$id_cuenta = $_GET['id_cuenta'];
	$tipo = $_GET['tipo'];
	$id_documento = $_GET['id_documento'];
	$id_registro = $_GET['id_registro'];
	$concepto_cuenta = $_GET['concepto_cuenta'];
	$id_asiento_tipo = $_GET['id_asiento_tipo'];

	eliminar_registro_cuenta_programada($con, $id_registro);
	guardar_registro_asiento_programado($con, $ruc_empresa, $id_cuenta, $tipo, $concepto_cuenta, $id_documento, $id_usuario, $id_asiento_tipo, $id_registro);
	echo "<script>$.notify('Cuenta agregada.','success')</script>";
}

if ($action == "eliminar_cuenta") {
	$id_registro = $_GET['id_registro'];
	eliminar_registro_cuenta_programada($con, $id_registro);
	echo "<script>$.notify('Registro eliminado.','info');
				</script>";
}

function eliminar_registro_cuenta_programada($con, $id_registro)
{
	$eliminar_asiento_tipo = mysqli_query($con, "DELETE FROM asientos_programados WHERE id_asi_pro = '" . $id_registro . "' ");
	return $eliminar_asiento_tipo;
}


function guardar_registro_asiento_programado($con, $ruc_empresa, $id_cuenta, $tipo_asiento, $concepto_cuenta, $id_documento, $id_usuario, $id_asiento_tipo)
{
	$fecha_agregado = date("Y-m-d H:i:s");
	ini_set('date.timezone', 'America/Guayaquil');
	$guardar_asiento_tipo = mysqli_query($con, "INSERT INTO asientos_programados VALUES (NULL, '" . $ruc_empresa . "', '" . $tipo_asiento . "','" . $id_cuenta . "', '" . $id_asiento_tipo . "', '" . $concepto_cuenta . "','" . $id_documento . "','" . $id_usuario . "','" . $fecha_agregado . "')");
	return $guardar_asiento_tipo;
}


//para conseguir los datos de las cuentas programadas
function obtenerCuentasProgramadas($id_asiento_tipo, $con, $ruc_empresa, $id_pro_cli, $tipo_asiento)
{
	// Preparar la consulta
	$buscar_cuentas = mysqli_query($con, "SELECT pc.id_cuenta, pc.codigo_cuenta, pc.nombre_cuenta, ap.id_asi_pro
                           FROM asientos_programados AS ap
                           INNER JOIN plan_cuentas AS pc ON pc.id_cuenta = ap.id_cuenta
                           WHERE ap.ruc_empresa = '" . $ruc_empresa . "' 
						   AND ap.tipo_asiento = '" . $tipo_asiento . "' 
						   AND ap.id_asiento_tipo = '" . $id_asiento_tipo . "'
						   AND ap.id_pro_cli = '" . $id_pro_cli . "'");
	$row_programadas = mysqli_fetch_array($buscar_cuentas);
	$id_cuenta = isset($row_programadas['id_cuenta']) ? $row_programadas['id_cuenta'] : 0;
	$codigo_cuenta = isset($row_programadas['codigo_cuenta']) ? $row_programadas['codigo_cuenta'] : "";
	$nombre_cuenta = isset($row_programadas['nombre_cuenta']) ? $row_programadas['nombre_cuenta'] : "";
	$id_registro = isset($row_programadas['id_asi_pro']) ? $row_programadas['id_asi_pro'] : 0;

	// Retornar los datos como un array
	return array(
		'id_cuenta' => $id_cuenta,
		'codigo_cuenta' => $codigo_cuenta,
		'nombre_cuenta' => $nombre_cuenta,
		'id_asi_pro' => $id_registro
	);
}



function listado_clientes_facturas($con, $ruc_empresa, $anio, $mes)
{
?>
	<div class="panel panel-info">
		<div class="table-responsive">
			<table class="table">
				<tr class="info">
					<td>Cliente</td>
					<td>Cuenta de activo CXC</td>
					<td>Cuenta de ingreso</td>
					<td>Cuenta de IVA ventas</td>
				</tr>
				<?php
				if (empty($anio)) {
					$opciones_anio = "";
				} else {
					$opciones_anio = " and year(enc_fac.fecha_factura) = '" . $anio . "' ";
				}
				if (empty($mes)) {
					$opciones_mes = "";
				} else {
					$opciones_mes = " and month(enc_fac.fecha_factura) = '" . $mes . "' ";
				}
				$query_clientes = mysqli_query($con, "SELECT DISTINCT cli.ruc as ruc_cliente, 
				enc_fac.id_cliente as id_cliente, cli.nombre as cliente 
				FROM encabezado_factura as enc_fac 
				INNER JOIN clientes as cli ON cli.id=enc_fac.id_cliente 
				WHERE enc_fac.ruc_empresa = '" . $ruc_empresa . "' $opciones_anio $opciones_mes group by enc_fac.id_cliente order by cli.nombre asc");
				while ($row_clientes = mysqli_fetch_array($query_clientes)) {
					$codigo_unico = rand(100, 50000);
					$id_cliente = $row_clientes['id_cliente'];
					$cliente = $row_clientes['cliente'];
					$ruc_cliente = $row_clientes['ruc_cliente'];

					$cuentas_programadas_cxc = obtenerCuentasProgramadas('0', $con, $ruc_empresa, $id_cliente, 'ventas_cliente_cxc');
					$id_cuenta__cxc = $cuentas_programadas_cxc['id_cuenta'];
					$codigo_cuenta_cxc = $cuentas_programadas_cxc['codigo_cuenta'];
					$nombre_cuenta_cxc = $cuentas_programadas_cxc['nombre_cuenta'];
					$id_registro_cxc = $cuentas_programadas_cxc['id_asi_pro'];

					$cuentas_programadas_ingreso = obtenerCuentasProgramadas('0', $con, $ruc_empresa, $id_cliente, 'cliente');
					$id_cuenta_ingreso = $cuentas_programadas_ingreso['id_cuenta'];
					$codigo_cuenta_ingreso = $cuentas_programadas_ingreso['codigo_cuenta'];
					$nombre_cuenta_ingreso = $cuentas_programadas_ingreso['nombre_cuenta'];
					$id_registro_ingreso = $cuentas_programadas_ingreso['id_asi_pro'];

					$cuentas_programadas_iva_ventas = obtenerCuentasProgramadas('0', $con, $ruc_empresa, $id_cliente, 'ventas_cliente_iva');
					$id_cuenta_iva_ventas = $cuentas_programadas_iva_ventas['id_cuenta'];
					$codigo_cuenta_iva_ventas = $cuentas_programadas_iva_ventas['codigo_cuenta'];
					$nombre_cuenta_iva_ventas = $cuentas_programadas_iva_ventas['nombre_cuenta'];
					$id_registro_iva_ventas = $cuentas_programadas_iva_ventas['id_asi_pro'];

				?>
					<tr class="active">
						<input type="hidden" id="id_cuenta_uno<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta_ingreso; ?>">
						<input type="hidden" id="id_cuenta_dos<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta_iva_ventas; ?>">
						<input type="hidden" id="id_cuenta_tres<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta__cxc; ?>">
						<td class="col-xs-3"><?php echo mb_strtoupper($cliente, 'UTF-8') ?></td>
						<td class="col-xs-3">
							<div class="input-group">
								<input type="text" <?php echo empty($codigo_cuenta_cxc) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable_uno<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('_uno','<?php echo $codigo_unico; ?>', '<?php echo $id_cliente; ?>','ventas_cliente_cxc', 'ventas cliente', '<?php echo $id_registro_cxc; ?>','0');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta_cxc; ?>">
								<span class="input-group-btn btn-md"><a class="btn btn-danger btn-sm" title="Eliminar cuenta" onclick="eliminar_cuenta('_uno','<?php echo $codigo_unico; ?>','<?php echo $id_registro_cxc; ?>');"><i class="glyphicon glyphicon-trash"></i></a></span>
							</div>
						</td>
						<td class="col-xs-3">
							<div class="input-group">
								<input type="text" <?php echo empty($codigo_cuenta_ingreso) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable_dos<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('_dos','<?php echo $codigo_unico; ?>', '<?php echo $id_cliente; ?>','cliente', 'ventas cliente', '<?php echo $id_registro_ingreso; ?>','0');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta_ingreso; ?>">
								<span class="input-group-btn btn-md"><a class="btn btn-danger btn-sm" title="Eliminar cuenta" onclick="eliminar_cuenta('_dos','<?php echo $codigo_unico; ?>','<?php echo $id_registro_ingreso; ?>');"><i class="glyphicon glyphicon-trash"></i></a></span>
							</div>
						</td>
						<td class="col-xs-3">
							<div class="input-group">
								<input type="text" <?php echo empty($codigo_cuenta_iva_ventas) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable_tres<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('_tres','<?php echo $codigo_unico; ?>', '<?php echo $id_cliente; ?>','ventas_cliente_iva', 'ventas cliente', '<?php echo $id_registro_iva_ventas; ?>','0');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta_iva_ventas; ?>">
								<span class="input-group-btn btn-md"><a class="btn btn-danger btn-sm" title="Eliminar cuenta" onclick="eliminar_cuenta('_tres','<?php echo $codigo_unico; ?>','<?php echo $id_registro_iva_ventas; ?>');"><i class="glyphicon glyphicon-trash"></i></a></span>
							</div>
						</td>
					</tr>
				<?php
				}
				?>
			</table>
		</div>
	</div>
<?php
}

function listado_productos_ventas($con, $ruc_empresa, $anio, $mes)
{
?>
	<div class="panel panel-info">
		<div class="table-responsive">
			<table class="table">
				<tr class="info">
					<td>Producto/Servicio</td>
					<td>Cuenta de activo CXC</td>
					<td>Cuenta de ingreso</td>
					<td>Cuenta de IVA ventas</td>
				</tr>
				<?php
				if (empty($anio)) {
					$opciones_anio = "";
				} else {
					$opciones_anio = " and year(enc.fecha_factura) = '" . $anio . "' ";
				}
				if (empty($mes)) {
					$opciones_mes = "";
				} else {
					$opciones_mes = " and month(enc.fecha_factura) = '" . $mes . "' ";
				}
				$query_productos = mysqli_query($con, "SELECT DISTINCT pro.id as id_producto, pro.codigo_producto as codigo_producto, pro.nombre_producto as nombre_producto 
									FROM productos_servicios as pro 
									LEFT JOIN cuerpo_factura as cue ON cue.id_producto=pro.id
									LEFT JOIN encabezado_factura as enc ON enc.serie_factura=cue.serie_factura and enc.secuencial_factura=cue.secuencial_factura and enc.ruc_empresa=cue.ruc_empresa 
									WHERE pro.ruc_empresa = '" . $ruc_empresa . "' $opciones_anio $opciones_mes order by pro.nombre_producto asc");
				while ($row_productos = mysqli_fetch_array($query_productos)) {
					$codigo_unico = rand(100, 50000);
					$id_producto = $row_productos['id_producto'];
					$nombre_producto = $row_productos['nombre_producto'];
					$codigo_producto = $row_productos['codigo_producto'];


					$cuentas_programadas_cxc = obtenerCuentasProgramadas('0', $con, $ruc_empresa, $id_producto, 'productoventafacturaCxC');
					$id_cuenta__cxc = $cuentas_programadas_cxc['id_cuenta'];
					$codigo_cuenta_cxc = $cuentas_programadas_cxc['codigo_cuenta'];
					$nombre_cuenta_cxc = $cuentas_programadas_cxc['nombre_cuenta'];
					$id_registro_cxc = $cuentas_programadas_cxc['id_asi_pro'];

					$cuentas_programadas_ingreso = obtenerCuentasProgramadas('0', $con, $ruc_empresa, $id_producto, 'productoventafactura');
					$id_cuenta_ingreso = $cuentas_programadas_ingreso['id_cuenta'];
					$codigo_cuenta_ingreso = $cuentas_programadas_ingreso['codigo_cuenta'];
					$nombre_cuenta_ingreso = $cuentas_programadas_ingreso['nombre_cuenta'];
					$id_registro_ingreso = $cuentas_programadas_ingreso['id_asi_pro'];

					$cuentas_programadas_iva_ventas = obtenerCuentasProgramadas('0', $con, $ruc_empresa, $id_producto, 'productoventafacturaIva');
					$id_cuenta_iva_ventas = $cuentas_programadas_iva_ventas['id_cuenta'];
					$codigo_cuenta_iva_ventas = $cuentas_programadas_iva_ventas['codigo_cuenta'];
					$nombre_cuenta_iva_ventas = $cuentas_programadas_iva_ventas['nombre_cuenta'];
					$id_registro_iva_ventas = $cuentas_programadas_iva_ventas['id_asi_pro'];

				?>
					<tr class="active">
						<input type="hidden" id="id_cuenta_uno<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta_ingreso; ?>">
						<input type="hidden" id="id_cuenta_dos<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta_iva_ventas; ?>">
						<input type="hidden" id="id_cuenta_tres<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta__cxc; ?>">
						<td class="col-xs-3"><?php echo mb_strtoupper($nombre_producto, 'UTF-8') ?></td>
						<td class="col-xs-3">
							<div class="input-group">
								<input type="text" <?php echo empty($codigo_cuenta_cxc) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable_uno<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('_uno','<?php echo $codigo_unico; ?>', '<?php echo $id_producto; ?>','productoventafacturaCxC', 'ventas producto', '<?php echo $id_registro_cxc; ?>','0');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta_cxc; ?>">
								<span class="input-group-btn btn-md"><a class="btn btn-danger btn-sm" title="Eliminar cuenta" onclick="eliminar_cuenta('_uno','<?php echo $codigo_unico; ?>','<?php echo $id_registro_cxc; ?>');"><i class="glyphicon glyphicon-trash"></i></a></span>
							</div>
						</td>
						<td class="col-xs-3">
							<div class="input-group">
								<input type="text" <?php echo empty($codigo_cuenta_ingreso) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable_dos<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('_dos','<?php echo $codigo_unico; ?>', '<?php echo $id_producto; ?>','productoventafactura', 'ventas producto', '<?php echo $id_registro_ingreso; ?>','0');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta_ingreso; ?>">
								<span class="input-group-btn btn-md"><a class="btn btn-danger btn-sm" title="Eliminar cuenta" onclick="eliminar_cuenta('_dos','<?php echo $codigo_unico; ?>','<?php echo $id_registro_ingreso; ?>');"><i class="glyphicon glyphicon-trash"></i></a></span>
							</div>
						</td>
						<td class="col-xs-3">
							<div class="input-group">
								<input type="text" <?php echo empty($codigo_cuenta_iva_ventas) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable_tres<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('_tres','<?php echo $codigo_unico; ?>', '<?php echo $id_producto; ?>','productoventafacturaIva', 'ventas producto', '<?php echo $id_registro_iva_ventas; ?>','0');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta_iva_ventas; ?>">
								<span class="input-group-btn btn-md"><a class="btn btn-danger btn-sm" title="Eliminar cuenta" onclick="eliminar_cuenta('_tres','<?php echo $codigo_unico; ?>','<?php echo $id_registro_iva_ventas; ?>');"><i class="glyphicon glyphicon-trash"></i></a></span>
							</div>
						</td>
					</tr>
				<?php
				}
				?>
			</table>
		</div>
	</div>
<?php
}

function listado_categorias_ventas($con, $ruc_empresa)
{
?>
	<div class="panel panel-info">
		<div class="table-responsive">
			<table class="table">
				<tr class="info">
					<td>Categorías</td>
					<td>Cuenta de activo CXC</td>
					<td>Cuenta de ingreso</td>
					<td>Cuenta de IVA ventas</td>
				</tr>
				<?php
				$sql_categoria_venta = mysqli_query($con, "SELECT id_grupo, nombre_grupo FROM grupo_familiar_producto WHERE ruc_empresa = '" . $ruc_empresa . "' order by nombre_grupo asc");
				while ($row_categoria_venta = mysqli_fetch_array($sql_categoria_venta)) {
					$codigo_unico = rand(100, 50000);
					$id_grupo = $row_categoria_venta['id_grupo'];
					$nombre_grupo = $row_categoria_venta['nombre_grupo'];

					$cuentas_programadas_cxc = obtenerCuentasProgramadas('0', $con, $ruc_empresa, $id_grupo, 'ventascategoriasfacturasCxC');
					$id_cuenta__cxc = $cuentas_programadas_cxc['id_cuenta'];
					$codigo_cuenta_cxc = $cuentas_programadas_cxc['codigo_cuenta'];
					$nombre_cuenta_cxc = $cuentas_programadas_cxc['nombre_cuenta'];
					$id_registro_cxc = $cuentas_programadas_cxc['id_asi_pro'];

					$cuentas_programadas_ingreso = obtenerCuentasProgramadas('0', $con, $ruc_empresa, $id_grupo, 'ventascategoriasfacturas');
					$id_cuenta_ingreso = $cuentas_programadas_ingreso['id_cuenta'];
					$codigo_cuenta_ingreso = $cuentas_programadas_ingreso['codigo_cuenta'];
					$nombre_cuenta_ingreso = $cuentas_programadas_ingreso['nombre_cuenta'];
					$id_registro_ingreso = $cuentas_programadas_ingreso['id_asi_pro'];

					$cuentas_programadas_iva_ventas = obtenerCuentasProgramadas('0', $con, $ruc_empresa, $id_grupo, 'ventascategoriasfacturasIva');
					$id_cuenta_iva_ventas = $cuentas_programadas_iva_ventas['id_cuenta'];
					$codigo_cuenta_iva_ventas = $cuentas_programadas_iva_ventas['codigo_cuenta'];
					$nombre_cuenta_iva_ventas = $cuentas_programadas_iva_ventas['nombre_cuenta'];
					$id_registro_iva_ventas = $cuentas_programadas_iva_ventas['id_asi_pro'];

				?>
					<tr class="active">
						<input type="hidden" id="id_cuenta_uno<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta_ingreso; ?>">
						<input type="hidden" id="id_cuenta_dos<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta_iva_ventas; ?>">
						<input type="hidden" id="id_cuenta_tres<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta__cxc; ?>">
						<td class="col-xs-3"><?php echo mb_strtoupper($nombre_grupo, 'UTF-8') ?></td>
						<td class="col-xs-3">
							<div class="input-group">
								<input type="text" <?php echo empty($codigo_cuenta_cxc) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable_uno<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('_uno','<?php echo $codigo_unico; ?>', '<?php echo $id_grupo; ?>','ventascategoriasfacturasCxC', 'ventas categoría', '<?php echo $id_registro_cxc; ?>','0');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta_cxc; ?>">
								<span class="input-group-btn btn-md"><a class="btn btn-danger btn-sm" title="Eliminar cuenta" onclick="eliminar_cuenta('_uno','<?php echo $codigo_unico; ?>','<?php echo $id_registro_cxc; ?>');"><i class="glyphicon glyphicon-trash"></i></a></span>
							</div>
						</td>
						<td class="col-xs-3">
							<div class="input-group">
								<input type="text" <?php echo empty($codigo_cuenta_ingreso) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable_dos<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('_dos','<?php echo $codigo_unico; ?>', '<?php echo $id_grupo; ?>','ventascategoriasfacturas', 'ventas categoría', '<?php echo $id_registro_ingreso; ?>','0');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta_ingreso; ?>">
								<span class="input-group-btn btn-md"><a class="btn btn-danger btn-sm" title="Eliminar cuenta" onclick="eliminar_cuenta('_dos','<?php echo $codigo_unico; ?>','<?php echo $id_registro_ingreso; ?>');"><i class="glyphicon glyphicon-trash"></i></a></span>
							</div>
						</td>
						<td class="col-xs-3">
							<div class="input-group">
								<input type="text" <?php echo empty($codigo_cuenta_iva_ventas) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable_tres<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('_tres','<?php echo $codigo_unico; ?>', '<?php echo $id_grupo; ?>','ventascategoriasfacturasIva', 'ventas categoría', '<?php echo $id_registro_iva_ventas; ?>','0');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta_iva_ventas; ?>">
								<span class="input-group-btn btn-md"><a class="btn btn-danger btn-sm" title="Eliminar cuenta" onclick="eliminar_cuenta('_tres','<?php echo $codigo_unico; ?>','<?php echo $id_registro_iva_ventas; ?>');"><i class="glyphicon glyphicon-trash"></i></a></span>
							</div>
						</td>
					</tr>
				<?php
				}
				?>
			</table>
		</div>
	</div>
<?php
}

function listado_marcas_ventas($con, $ruc_empresa)
{
?>
	<div class="panel panel-info">
		<div class="table-responsive">
			<table class="table">
				<tr class="info">
					<td>Marca</td>
					<td>Cuenta de activo CXC</td>
					<td>Cuenta de ingreso</td>
					<td>Cuenta de IVA ventas</td>
				</tr>
				<?php
				$sql_categoria_venta = mysqli_query($con, "SELECT id_marca, nombre_marca FROM marca WHERE ruc_empresa = '" . $ruc_empresa . "' order by nombre_marca asc");
				while ($row_categoria_venta = mysqli_fetch_array($sql_categoria_venta)) {
					$codigo_unico = rand(100, 50000);
					$id_marca = $row_categoria_venta['id_marca'];
					$nombre_marca = $row_categoria_venta['nombre_marca'];

					$cuentas_programadas_cxc = obtenerCuentasProgramadas('0', $con, $ruc_empresa, $id_marca, 'ventasmarcasfacturasCxC');
					$id_cuenta__cxc = $cuentas_programadas_cxc['id_cuenta'];
					$codigo_cuenta_cxc = $cuentas_programadas_cxc['codigo_cuenta'];
					$nombre_cuenta_cxc = $cuentas_programadas_cxc['nombre_cuenta'];
					$id_registro_cxc = $cuentas_programadas_cxc['id_asi_pro'];

					$cuentas_programadas_ingreso = obtenerCuentasProgramadas('0', $con, $ruc_empresa, $id_marca, 'ventasmarcasfacturas');
					$id_cuenta_ingreso = $cuentas_programadas_ingreso['id_cuenta'];
					$codigo_cuenta_ingreso = $cuentas_programadas_ingreso['codigo_cuenta'];
					$nombre_cuenta_ingreso = $cuentas_programadas_ingreso['nombre_cuenta'];
					$id_registro_ingreso = $cuentas_programadas_ingreso['id_asi_pro'];

					$cuentas_programadas_iva_ventas = obtenerCuentasProgramadas('0', $con, $ruc_empresa, $id_marca, 'ventasmarcasfacturasIva');
					$id_cuenta_iva_ventas = $cuentas_programadas_iva_ventas['id_cuenta'];
					$codigo_cuenta_iva_ventas = $cuentas_programadas_iva_ventas['codigo_cuenta'];
					$nombre_cuenta_iva_ventas = $cuentas_programadas_iva_ventas['nombre_cuenta'];
					$id_registro_iva_ventas = $cuentas_programadas_iva_ventas['id_asi_pro'];

				?>
					<tr class="active">
						<input type="hidden" id="id_cuenta_uno<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta_ingreso; ?>">
						<input type="hidden" id="id_cuenta_dos<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta_iva_ventas; ?>">
						<input type="hidden" id="id_cuenta_tres<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta__cxc; ?>">
						<td class="col-xs-3"><?php echo mb_strtoupper($nombre_marca, 'UTF-8') ?></td>
						<td class="col-xs-3">
							<div class="input-group">
								<input type="text" <?php echo empty($codigo_cuenta_cxc) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable_uno<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('_uno','<?php echo $codigo_unico; ?>', '<?php echo $id_marca; ?>','ventasmarcasfacturasCxC', 'ventas marca', '<?php echo $id_registro_cxc; ?>','0');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta_cxc; ?>">
								<span class="input-group-btn btn-md"><a class="btn btn-danger btn-sm" title="Eliminar cuenta" onclick="eliminar_cuenta('_uno','<?php echo $codigo_unico; ?>','<?php echo $id_registro_cxc; ?>');"><i class="glyphicon glyphicon-trash"></i></a></span>
							</div>
						</td>
						<td class="col-xs-3">
							<div class="input-group">
								<input type="text" <?php echo empty($codigo_cuenta_ingreso) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable_dos<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('_dos','<?php echo $codigo_unico; ?>', '<?php echo $id_marca; ?>','ventasmarcasfacturas', 'ventas marca', '<?php echo $id_registro_ingreso; ?>','0');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta_ingreso; ?>">
								<span class="input-group-btn btn-md"><a class="btn btn-danger btn-sm" title="Eliminar cuenta" onclick="eliminar_cuenta('_dos','<?php echo $codigo_unico; ?>','<?php echo $id_registro_ingreso; ?>');"><i class="glyphicon glyphicon-trash"></i></a></span>
							</div>
						</td>
						<td class="col-xs-3">
							<div class="input-group">
								<input type="text" <?php echo empty($codigo_cuenta_iva_ventas) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable_tres<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('_tres','<?php echo $codigo_unico; ?>', '<?php echo $id_marca; ?>','ventasmarcasfacturasIva', 'ventas marca', '<?php echo $id_registro_iva_ventas; ?>','0');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta_iva_ventas; ?>">
								<span class="input-group-btn btn-md"><a class="btn btn-danger btn-sm" title="Eliminar cuenta" onclick="eliminar_cuenta('_tres','<?php echo $codigo_unico; ?>','<?php echo $id_registro_iva_ventas; ?>');"><i class="glyphicon glyphicon-trash"></i></a></span>
							</div>
						</td>
					</tr>
				<?php
				}
				?>
			</table>
		</div>
	</div>
<?php
}

//para recibos de ventas
function listado_clientes_recibos($con, $ruc_empresa, $anio, $mes)
{
?>
	<div class="panel panel-info">
		<div class="table-responsive">
			<table class="table">
				<tr class="info">
					<td>Cliente</td>
					<td>Cuenta de activo CXC</td>
					<td>Cuenta de ingreso</td>
					<td>Cuenta de IVA ventas</td>
				</tr>
				<?php
				if (empty($anio)) {
					$opciones_anio = "";
				} else {
					$opciones_anio = " and year(enc_rec.fecha_recibo) = '" . $anio . "' ";
				}
				if (empty($mes)) {
					$opciones_mes = "";
				} else {
					$opciones_mes = " and month(enc_rec.fecha_recibo) = '" . $mes . "' ";
				}
				$query_clientes = mysqli_query($con, "SELECT DISTINCT cli.ruc as ruc_cliente, 
					enc_rec.id_cliente as id_cliente, cli.nombre as cliente 
					FROM encabezado_recibo as enc_rec INNER JOIN clientes as cli ON cli.id=enc_rec.id_cliente 
					WHERE enc_rec.ruc_empresa = '" . $ruc_empresa . "' $opciones_anio $opciones_mes group by enc_rec.id_cliente order by cli.nombre asc");
				while ($row_clientes = mysqli_fetch_array($query_clientes)) {
					$codigo_unico = rand(100, 50000);
					$id_cliente = $row_clientes['id_cliente'];
					$cliente = $row_clientes['cliente'];
					$ruc_cliente = $row_clientes['ruc_cliente'];

					$cuentas_programadas_ingreso = obtenerCuentasProgramadas('0', $con, $ruc_empresa, $id_cliente, 'clienteRecibo');
					$id_cuenta_ingreso = $cuentas_programadas_ingreso['id_cuenta'];
					$codigo_cuenta_ingreso = $cuentas_programadas_ingreso['codigo_cuenta'];
					$nombre_cuenta_ingreso = $cuentas_programadas_ingreso['nombre_cuenta'];
					$id_registro_ingreso = $cuentas_programadas_ingreso['id_asi_pro'];

					$cuentas_programadas_iva_ventas = obtenerCuentasProgramadas('0', $con, $ruc_empresa, $id_cliente, 'clienteRecibo_iva');
					$id_cuenta_iva_ventas = $cuentas_programadas_iva_ventas['id_cuenta'];
					$codigo_cuenta_iva_ventas = $cuentas_programadas_iva_ventas['codigo_cuenta'];
					$nombre_cuenta_iva_ventas = $cuentas_programadas_iva_ventas['nombre_cuenta'];
					$id_registro_iva_ventas = $cuentas_programadas_iva_ventas['id_asi_pro'];

					$cuentas_programadas_cxc = obtenerCuentasProgramadas('0', $con, $ruc_empresa, $id_cliente, 'clienteRecibo_cxc');
					$id_cuenta__cxc = $cuentas_programadas_cxc['id_cuenta'];
					$codigo_cuenta_cxc = $cuentas_programadas_cxc['codigo_cuenta'];
					$nombre_cuenta_cxc = $cuentas_programadas_cxc['nombre_cuenta'];
					$id_registro_cxc = $cuentas_programadas_cxc['id_asi_pro'];
				?>
					<tr class="active">
						<input type="hidden" id="id_cuenta_uno<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta_ingreso; ?>">
						<input type="hidden" id="id_cuenta_dos<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta_iva_ventas; ?>">
						<input type="hidden" id="id_cuenta_tres<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta__cxc; ?>">
						<td class="col-xs-3"><?php echo mb_strtoupper($cliente, 'UTF-8') ?></td>
						<td class="col-xs-3">
							<div class="input-group">
								<input type="text" <?php echo empty($codigo_cuenta_cxc) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable_uno<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('_uno','<?php echo $codigo_unico; ?>', '<?php echo $id_cliente; ?>','clienteRecibo_cxc', 'ventas cliente recibo', '<?php echo $id_registro_cxc; ?>','0');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta_cxc; ?>">
								<span class="input-group-btn btn-md"><a class="btn btn-danger btn-sm" title="Eliminar cuenta" onclick="eliminar_cuenta('_uno','<?php echo $codigo_unico; ?>','<?php echo $id_registro_cxc; ?>');"><i class="glyphicon glyphicon-trash"></i></a></span>
							</div>
						</td>
						<td class="col-xs-3">
							<div class="input-group">
								<input type="text" <?php echo empty($codigo_cuenta_ingreso) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable_dos<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('_dos','<?php echo $codigo_unico; ?>', '<?php echo $id_cliente; ?>','clienteRecibo', 'ventas cliente recibo', '<?php echo $id_registro_ingreso; ?>','0');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta_ingreso; ?>">
								<span class="input-group-btn btn-md"><a class="btn btn-danger btn-sm" title="Eliminar cuenta" onclick="eliminar_cuenta('_dos','<?php echo $codigo_unico; ?>','<?php echo $id_registro_ingreso; ?>');"><i class="glyphicon glyphicon-trash"></i></a></span>
							</div>
						</td>
						<td class="col-xs-3">
							<div class="input-group">
								<input type="text" <?php echo empty($codigo_cuenta_iva_ventas) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable_tres<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('_tres','<?php echo $codigo_unico; ?>', '<?php echo $id_cliente; ?>','clienteRecibo_iva', 'ventas cliente recibo', '<?php echo $id_registro_iva_ventas; ?>','0');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta_iva_ventas; ?>">
								<span class="input-group-btn btn-md"><a class="btn btn-danger btn-sm" title="Eliminar cuenta" onclick="eliminar_cuenta('_tres','<?php echo $codigo_unico; ?>','<?php echo $id_registro_iva_ventas; ?>');"><i class="glyphicon glyphicon-trash"></i></a></span>
							</div>
						</td>
					</tr>
				<?php
				}
				?>
			</table>
		</div>
	</div>
<?php
}

function listado_productos_ventas_recibos($con, $ruc_empresa, $anio, $mes)
{
?>
	<div class="panel panel-info">
		<div class="table-responsive">
			<table class="table">
				<tr class="info">
					<td>Producto/Servicio</td>
					<td>Cuenta de activo CXC</td>
					<td>Cuenta de ingreso</td>
					<td>Cuenta de IVA ventas</td>
				</tr>
				<?php
				if (empty($anio)) {
					$opciones_anio = "";
				} else {
					$opciones_anio = " and year(enc.fecha_recibo) = '" . $anio . "' ";
				}
				if (empty($mes)) {
					$opciones_mes = "";
				} else {
					$opciones_mes = " and month(enc.fecha_recibo) = '" . $mes . "' ";
				}

				$query_productos_recibos = mysqli_query($con, "SELECT DISTINCT pro.id as id_producto, 
				pro.codigo_producto as codigo_producto, pro.nombre_producto as nombre_producto 
				FROM productos_servicios as pro 
				INNER JOIN cuerpo_recibo as cue ON cue.id_producto=pro.id
				INNER JOIN encabezado_recibo as enc ON enc.id_encabezado_recibo=cue.id_encabezado_recibo 
				WHERE pro.ruc_empresa = '" . $ruc_empresa . "' $opciones_anio $opciones_mes order by pro.nombre_producto asc");
				while ($row_productos = mysqli_fetch_array($query_productos_recibos)) {
					$codigo_unico = rand(100, 50000);
					$id_producto = $row_productos['id_producto'];
					$nombre_producto = $row_productos['nombre_producto'];
					$codigo_producto = $row_productos['codigo_producto'];

					$cuentas_programadas_cxc = obtenerCuentasProgramadas('0', $con, $ruc_empresa, $id_producto, 'productoventareciboCxC');
					$id_cuenta__cxc = $cuentas_programadas_cxc['id_cuenta'];
					$codigo_cuenta_cxc = $cuentas_programadas_cxc['codigo_cuenta'];
					$nombre_cuenta_cxc = $cuentas_programadas_cxc['nombre_cuenta'];
					$id_registro_cxc = $cuentas_programadas_cxc['id_asi_pro'];

					$cuentas_programadas_ingreso = obtenerCuentasProgramadas('0', $con, $ruc_empresa, $id_producto, 'productoventarecibo');
					$id_cuenta_ingreso = $cuentas_programadas_ingreso['id_cuenta'];
					$codigo_cuenta_ingreso = $cuentas_programadas_ingreso['codigo_cuenta'];
					$nombre_cuenta_ingreso = $cuentas_programadas_ingreso['nombre_cuenta'];
					$id_registro_ingreso = $cuentas_programadas_ingreso['id_asi_pro'];

					$cuentas_programadas_iva_ventas = obtenerCuentasProgramadas('0', $con, $ruc_empresa, $id_producto, 'productoventareciboIva');
					$id_cuenta_iva_ventas = $cuentas_programadas_iva_ventas['id_cuenta'];
					$codigo_cuenta_iva_ventas = $cuentas_programadas_iva_ventas['codigo_cuenta'];
					$nombre_cuenta_iva_ventas = $cuentas_programadas_iva_ventas['nombre_cuenta'];
					$id_registro_iva_ventas = $cuentas_programadas_iva_ventas['id_asi_pro'];

				?>
					<tr class="active">
						<input type="hidden" id="id_cuenta_uno<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta_ingreso; ?>">
						<input type="hidden" id="id_cuenta_dos<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta_iva_ventas; ?>">
						<input type="hidden" id="id_cuenta_tres<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta__cxc; ?>">
						<td class="col-xs-3"><?php echo mb_strtoupper($nombre_producto, 'UTF-8') ?></td>
						<td class="col-xs-3">
							<div class="input-group">
								<input type="text" <?php echo empty($codigo_cuenta_cxc) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable_uno<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('_uno','<?php echo $codigo_unico; ?>', '<?php echo $id_producto; ?>','productoventareciboCxC', 'ventas producto recibo', '<?php echo $id_registro_cxc; ?>','0');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta_cxc; ?>">
								<span class="input-group-btn btn-md"><a class="btn btn-danger btn-sm" title="Eliminar cuenta" onclick="eliminar_cuenta('_uno','<?php echo $codigo_unico; ?>','<?php echo $id_registro_cxc; ?>');"><i class="glyphicon glyphicon-trash"></i></a></span>
							</div>
						</td>
						<td class="col-xs-3">
							<div class="input-group">
								<input type="text" <?php echo empty($codigo_cuenta_ingreso) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable_dos<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('_dos','<?php echo $codigo_unico; ?>', '<?php echo $id_producto; ?>','productoventarecibo', 'ventas producto recibo', '<?php echo $id_registro_ingreso; ?>','0');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta_ingreso; ?>">
								<span class="input-group-btn btn-md"><a class="btn btn-danger btn-sm" title="Eliminar cuenta" onclick="eliminar_cuenta('_dos','<?php echo $codigo_unico; ?>','<?php echo $id_registro_ingreso; ?>');"><i class="glyphicon glyphicon-trash"></i></a></span>
							</div>
						</td>
						<td class="col-xs-3">
							<div class="input-group">
								<input type="text" <?php echo empty($codigo_cuenta_iva_ventas) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable_tres<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('_tres','<?php echo $codigo_unico; ?>', '<?php echo $id_producto; ?>','productoventareciboIva', 'ventas producto recibo', '<?php echo $id_registro_iva_ventas; ?>','0');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta_iva_ventas; ?>">
								<span class="input-group-btn btn-md"><a class="btn btn-danger btn-sm" title="Eliminar cuenta" onclick="eliminar_cuenta('_tres','<?php echo $codigo_unico; ?>','<?php echo $id_registro_iva_ventas; ?>');"><i class="glyphicon glyphicon-trash"></i></a></span>
							</div>
						</td>
					</tr>
				<?php
				}
				?>
			</table>
		</div>
	</div>
<?php
}

function listado_categorias_ventas_recibos($con, $ruc_empresa)
{
?>
	<div class="panel panel-info">
		<div class="table-responsive">
			<table class="table">
				<tr class="info">
					<td>Categorías</td>
					<td>Cuenta de activo CXC</td>
					<td>Cuenta de ingreso</td>
					<td>Cuenta de IVA ventas</td>
				</tr>
				<?php

				$sql_categoria_venta = mysqli_query($con, "SELECT id_grupo, nombre_grupo FROM grupo_familiar_producto WHERE ruc_empresa = '" . $ruc_empresa . "' order by nombre_grupo asc");
				while ($row_categoria_venta = mysqli_fetch_array($sql_categoria_venta)) {
					$codigo_unico = rand(100, 50000);
					$id_grupo = $row_categoria_venta['id_grupo'];
					$nombre_grupo = $row_categoria_venta['nombre_grupo'];

					$cuentas_programadas_cxc = obtenerCuentasProgramadas('0', $con, $ruc_empresa, $id_grupo, 'ventascategoriasrecibosCxC');
					$id_cuenta__cxc = $cuentas_programadas_cxc['id_cuenta'];
					$codigo_cuenta_cxc = $cuentas_programadas_cxc['codigo_cuenta'];
					$nombre_cuenta_cxc = $cuentas_programadas_cxc['nombre_cuenta'];
					$id_registro_cxc = $cuentas_programadas_cxc['id_asi_pro'];

					$cuentas_programadas_ingreso = obtenerCuentasProgramadas('0', $con, $ruc_empresa, $id_grupo, 'ventascategoriasrecibos');
					$id_cuenta_ingreso = $cuentas_programadas_ingreso['id_cuenta'];
					$codigo_cuenta_ingreso = $cuentas_programadas_ingreso['codigo_cuenta'];
					$nombre_cuenta_ingreso = $cuentas_programadas_ingreso['nombre_cuenta'];
					$id_registro_ingreso = $cuentas_programadas_ingreso['id_asi_pro'];

					$cuentas_programadas_iva_ventas = obtenerCuentasProgramadas('0', $con, $ruc_empresa, $id_grupo, 'ventascategoriasrecibosIva');
					$id_cuenta_iva_ventas = $cuentas_programadas_iva_ventas['id_cuenta'];
					$codigo_cuenta_iva_ventas = $cuentas_programadas_iva_ventas['codigo_cuenta'];
					$nombre_cuenta_iva_ventas = $cuentas_programadas_iva_ventas['nombre_cuenta'];
					$id_registro_iva_ventas = $cuentas_programadas_iva_ventas['id_asi_pro'];

				?>
					<tr class="active">
						<input type="hidden" id="id_cuenta_uno<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta_ingreso; ?>">
						<input type="hidden" id="id_cuenta_dos<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta_iva_ventas; ?>">
						<input type="hidden" id="id_cuenta_tres<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta__cxc; ?>">
						<td class="col-xs-3"><?php echo mb_strtoupper($nombre_grupo, 'UTF-8') ?></td>
						<td class="col-xs-3">
							<div class="input-group">
								<input type="text" <?php echo empty($codigo_cuenta_cxc) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable_uno<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('_uno','<?php echo $codigo_unico; ?>', '<?php echo $id_grupo; ?>','ventascategoriasrecibosCxC', 'ventas categoría recibo', '<?php echo $id_registro_cxc; ?>','0');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta_cxc; ?>">
								<span class="input-group-btn btn-md"><a class="btn btn-danger btn-sm" title="Eliminar cuenta" onclick="eliminar_cuenta('_uno','<?php echo $codigo_unico; ?>','<?php echo $id_registro_cxc; ?>');"><i class="glyphicon glyphicon-trash"></i></a></span>
							</div>
						</td>
						<td class="col-xs-3">
							<div class="input-group">
								<input type="text" <?php echo empty($codigo_cuenta_ingreso) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable_dos<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('_dos','<?php echo $codigo_unico; ?>', '<?php echo $id_grupo; ?>','ventascategoriasrecibos', 'ventas categoría recibo', '<?php echo $id_registro_ingreso; ?>','0');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta_ingreso; ?>">
								<span class="input-group-btn btn-md"><a class="btn btn-danger btn-sm" title="Eliminar cuenta" onclick="eliminar_cuenta('_dos','<?php echo $codigo_unico; ?>','<?php echo $id_registro_ingreso; ?>');"><i class="glyphicon glyphicon-trash"></i></a></span>
							</div>
						</td>
						<td class="col-xs-3">
							<div class="input-group">
								<input type="text" <?php echo empty($codigo_cuenta_iva_ventas) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable_tres<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('_tres','<?php echo $codigo_unico; ?>', '<?php echo $id_grupo; ?>','ventascategoriasrecibosIva', 'ventas categoría recibo', '<?php echo $id_registro_iva_ventas; ?>','0');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta_iva_ventas; ?>">
								<span class="input-group-btn btn-md"><a class="btn btn-danger btn-sm" title="Eliminar cuenta" onclick="eliminar_cuenta('_tres','<?php echo $codigo_unico; ?>','<?php echo $id_registro_iva_ventas; ?>');"><i class="glyphicon glyphicon-trash"></i></a></span>
							</div>
						</td>
					</tr>
				<?php
				}
				?>
			</table>
		</div>
	</div>
<?php
}

function listado_marcas_ventas_recibos($con, $ruc_empresa)
{
?>
	<div class="panel panel-info">
		<div class="table-responsive">
			<table class="table">
				<tr class="info">
					<td>Marca</td>
					<td>Cuenta de activo CXC</td>
					<td>Cuenta de ingreso</td>
					<td>Cuenta de IVA ventas</td>
				</tr>
				<?php
				$sql_categoria_venta = mysqli_query($con, "SELECT id_marca, nombre_marca FROM marca WHERE ruc_empresa = '" . $ruc_empresa . "' order by nombre_marca asc");
				while ($row_categoria_venta = mysqli_fetch_array($sql_categoria_venta)) {
					$codigo_unico = rand(100, 50000);
					$id_marca = $row_categoria_venta['id_marca'];
					$nombre_marca = $row_categoria_venta['nombre_marca'];

					$cuentas_programadas_cxc = obtenerCuentasProgramadas('0', $con, $ruc_empresa, $id_marca, 'ventasmarcasrecibosCxC');
					$id_cuenta__cxc = $cuentas_programadas_cxc['id_cuenta'];
					$codigo_cuenta_cxc = $cuentas_programadas_cxc['codigo_cuenta'];
					$nombre_cuenta_cxc = $cuentas_programadas_cxc['nombre_cuenta'];
					$id_registro_cxc = $cuentas_programadas_cxc['id_asi_pro'];

					$cuentas_programadas_ingreso = obtenerCuentasProgramadas('0', $con, $ruc_empresa, $id_marca, 'ventasmarcasrecibos');
					$id_cuenta_ingreso = $cuentas_programadas_ingreso['id_cuenta'];
					$codigo_cuenta_ingreso = $cuentas_programadas_ingreso['codigo_cuenta'];
					$nombre_cuenta_ingreso = $cuentas_programadas_ingreso['nombre_cuenta'];
					$id_registro_ingreso = $cuentas_programadas_ingreso['id_asi_pro'];

					$cuentas_programadas_iva_ventas = obtenerCuentasProgramadas('0', $con, $ruc_empresa, $id_marca, 'ventasmarcasrecibosIva');
					$id_cuenta_iva_ventas = $cuentas_programadas_iva_ventas['id_cuenta'];
					$codigo_cuenta_iva_ventas = $cuentas_programadas_iva_ventas['codigo_cuenta'];
					$nombre_cuenta_iva_ventas = $cuentas_programadas_iva_ventas['nombre_cuenta'];
					$id_registro_iva_ventas = $cuentas_programadas_iva_ventas['id_asi_pro'];

				?>
					<tr class="active">
						<input type="hidden" id="id_cuenta_uno<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta_ingreso; ?>">
						<input type="hidden" id="id_cuenta_dos<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta_iva_ventas; ?>">
						<input type="hidden" id="id_cuenta_tres<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta__cxc; ?>">
						<td class="col-xs-3"><?php echo mb_strtoupper($nombre_marca, 'UTF-8') ?></td>
						<td class="col-xs-3">
							<div class="input-group">
								<input type="text" <?php echo empty($codigo_cuenta_cxc) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable_uno<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('_uno','<?php echo $codigo_unico; ?>', '<?php echo $id_marca; ?>','ventasmarcasrecibosCxC', 'ventas marca recibo', '<?php echo $id_registro_cxc; ?>','0');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta_cxc; ?>">
								<span class="input-group-btn btn-md"><a class="btn btn-danger btn-sm" title="Eliminar cuenta" onclick="eliminar_cuenta('_uno','<?php echo $codigo_unico; ?>','<?php echo $id_registro_cxc; ?>');"><i class="glyphicon glyphicon-trash"></i></a></span>
							</div>
						</td>
						<td class="col-xs-3">
							<div class="input-group">
								<input type="text" <?php echo empty($codigo_cuenta_ingreso) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable_dos<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('_dos','<?php echo $codigo_unico; ?>', '<?php echo $id_marca; ?>','ventasmarcasrecibos', 'ventas marca recibo', '<?php echo $id_registro_ingreso; ?>','0');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta_ingreso; ?>">
								<span class="input-group-btn btn-md"><a class="btn btn-danger btn-sm" title="Eliminar cuenta" onclick="eliminar_cuenta('_dos','<?php echo $codigo_unico; ?>','<?php echo $id_registro_ingreso; ?>');"><i class="glyphicon glyphicon-trash"></i></a></span>
							</div>
						</td>
						<td class="col-xs-3">
							<div class="input-group">
								<input type="text" <?php echo empty($codigo_cuenta_iva_ventas) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable_tres<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('_tres','<?php echo $codigo_unico; ?>', '<?php echo $id_marca; ?>','ventasmarcasrecibosIva', 'ventas marca recibo', '<?php echo $id_registro_iva_ventas; ?>','0');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta_iva_ventas; ?>">
								<span class="input-group-btn btn-md"><a class="btn btn-danger btn-sm" title="Eliminar cuenta" onclick="eliminar_cuenta('_tres','<?php echo $codigo_unico; ?>','<?php echo $id_registro_iva_ventas; ?>');"><i class="glyphicon glyphicon-trash"></i></a></span>
							</div>
						</td>
					</tr>
				<?php
				}
				?>
			</table>
		</div>
	</div>
<?php
}

function listado_proveedores($con, $ruc_empresa, $anio, $mes)
{
?>
	<div class="panel panel-info">
		<div class="table-responsive">
			<table class="table">
				<tr class="info">
					<td>Proveedor</td>
					<td>Cuenta de costo o gasto</td>
					<td>Cuenta de IVA en compras</td>
					<td>Cuenta pasivo CXP</td>
				</tr>
				<?php
				if (empty($anio)) {
					$opciones_anio = "";
				} else {
					$opciones_anio = " and year(enc_com.fecha_compra) = '" . $anio . "' ";
				}
				if (empty($mes)) {
					$opciones_mes = "";
				} else {
					$opciones_mes = " and month(enc_com.fecha_compra) = '" . $mes . "' ";
				}
				$query_proveedores = mysqli_query($con, "SELECT DISTINCT prov.ruc_proveedor as ruc_proveedor, enc_com.id_proveedor as id_proveedor, prov.razon_social as razon_social 
				FROM encabezado_compra as enc_com INNER JOIN proveedores as prov ON enc_com.id_proveedor=prov.id_proveedor 
				WHERE enc_com.ruc_empresa = '" . $ruc_empresa . "' $opciones_anio $opciones_mes group by enc_com.id_proveedor order by prov.razon_social asc");
				while ($row_compras = mysqli_fetch_array($query_proveedores)) {
					$codigo_unico = rand(100, 50000);
					$id_proveedor = $row_compras['id_proveedor'];
					$proveedor = $row_compras['razon_social'];

					$cuentas_programadas_gasto = obtenerCuentasProgramadas('0', $con, $ruc_empresa, $id_proveedor, 'proveedor');
					$id_cuenta_gasto = $cuentas_programadas_gasto['id_cuenta'];
					$codigo_cuenta_gasto = $cuentas_programadas_gasto['codigo_cuenta'];
					$nombre_cuenta_gasto = $cuentas_programadas_gasto['nombre_cuenta'];
					$id_registro_gasto = $cuentas_programadas_gasto['id_asi_pro'];

					$cuentas_programadas_iva_compras = obtenerCuentasProgramadas('0', $con, $ruc_empresa, $id_proveedor, 'proveedor_iva');
					$id_cuenta_iva_compras = $cuentas_programadas_iva_compras['id_cuenta'];
					$codigo_cuenta_iva_compras = $cuentas_programadas_iva_compras['codigo_cuenta'];
					$nombre_cuenta_iva_compras = $cuentas_programadas_iva_compras['nombre_cuenta'];
					$id_registro_iva_compras = $cuentas_programadas_iva_compras['id_asi_pro'];

					$cuentas_programadas_cxp = obtenerCuentasProgramadas('0', $con, $ruc_empresa, $id_proveedor, 'proveedor_cxp');
					$id_cuenta_cxp = $cuentas_programadas_cxp['id_cuenta'];
					$codigo_cuenta_cxp = $cuentas_programadas_cxp['codigo_cuenta'];
					$nombre_cuenta_cxp = $cuentas_programadas_cxp['nombre_cuenta'];
					$id_registro_cxp = $cuentas_programadas_cxp['id_asi_pro'];
				?>
					<tr class="active">
						<input type="hidden" id="id_cuenta_uno<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta_gasto; ?>">
						<input type="hidden" id="id_cuenta_dos<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta_iva_compras; ?>">
						<input type="hidden" id="id_cuenta_tres<?php echo $codigo_unico; ?>" value="<?php echo $id_cuenta_cxp; ?>">
						<td class="col-xs-3"><a title='Mostrar detalle de compras' onclick="mostrar_detalle_compras('<?php echo $id_proveedor; ?>')" data-toggle="modal" data-target="#detalleComprasProveedor"><?php echo mb_strtoupper($proveedor, 'UTF-8'); ?> </a></td>
						<td class="col-xs-3">
							<div class="input-group">
								<input type="text" <?php echo empty($codigo_cuenta_gasto) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable_uno<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('_uno','<?php echo $codigo_unico; ?>', '<?php echo $id_proveedor; ?>','proveedor', 'compras proveedor', '<?php echo $id_registro_gasto; ?>','0');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta_gasto; ?>">
								<span class="input-group-btn btn-md"><a class="btn btn-danger btn-sm" title="Eliminar cuenta" onclick="eliminar_cuenta('_uno','<?php echo $codigo_unico; ?>','<?php echo $id_registro_gasto; ?>');"><i class="glyphicon glyphicon-trash"></i></a></span>
							</div>
						</td>
						<td class="col-xs-3">
							<div class="input-group">
								<input type="text" <?php echo empty($codigo_cuenta_iva_compras) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable_dos<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('_dos','<?php echo $codigo_unico; ?>', '<?php echo $id_proveedor; ?>','proveedor_iva', 'compras proveedor', '<?php echo $id_registro_iva_compras; ?>','0');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta_iva_compras; ?>">
								<span class="input-group-btn btn-md"><a class="btn btn-danger btn-sm" title="Eliminar cuenta" onclick="eliminar_cuenta('_dos','<?php echo $codigo_unico; ?>','<?php echo $id_registro_iva_compras; ?>');"><i class="glyphicon glyphicon-trash"></i></a></span>
							</div>
						</td>
						<td class="col-xs-3">
							<div class="input-group">
								<input type="text" <?php echo empty($codigo_cuenta_cxp) ? "style='border: 1px solid #f00;'" : ""; ?> class="form-control input-sm" id="cuenta_contable_tres<?php echo $codigo_unico; ?>" onkeyup="guardar_cuenta('_tres','<?php echo $codigo_unico; ?>', '<?php echo $id_proveedor; ?>','proveedor_cxp', 'compras proveedor', '<?php echo $id_registro_cxp; ?>','0');" autocomplete="off" placeholder="Ingrese cuenta" value="<?php echo $nombre_cuenta_cxp; ?>">
								<span class="input-group-btn btn-md"><a class="btn btn-danger btn-sm" title="Eliminar cuenta" onclick="eliminar_cuenta('_tres','<?php echo $codigo_unico; ?>','<?php echo $id_registro_cxp; ?>');"><i class="glyphicon glyphicon-trash"></i></a></span>
							</div>
						</td>
					</tr>
				<?php
				}
				?>
			</table>
		</div>
	</div>
<?php
}
