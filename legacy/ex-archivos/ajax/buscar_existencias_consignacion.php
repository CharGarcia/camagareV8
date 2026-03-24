<?php
/* Connect To Database*/
//require_once("../ajax/consignacion_venta.php");
include("../conexiones/conectalogin.php");
include("../helpers/helpers.php");
require_once("../ajax/pagination.php");

$con = conenta_login();
session_start();
date_default_timezone_set('America/Guayaquil');
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];

$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

//para actualizar las existencias
if ($action == 'actualizar_existencia') {
	$delete = mysqli_query($con, "DELETE FROM existencias_consignacion_venta WHERE ruc_empresa='" . $ruc_empresa . "'");
	$insert = mysqli_query($con, "INSERT INTO existencias_consignacion_venta (
    numero_consignacion, 
    id_cliente, 
    id_asesor, 
    ruc_empresa, 
    cantidad
)
SELECT 
    ec.numero_consignacion,
    ec.id_cli_pro,
    ec.responsable,
    ec.ruc_empresa,
    SUM(dc.cant_consignacion)
FROM 
    encabezado_consignacion ec
JOIN 
    detalle_consignacion dc ON ec.codigo_unico = dc.codigo_unico
WHERE 
    ec.operacion = 'entrada' and ec.status='1' and ec.ruc_empresa='" . $ruc_empresa . "'
GROUP BY 
    ec.numero_consignacion, ec.id_cli_pro, ec.responsable, ec.ruc_empresa
ON DUPLICATE KEY UPDATE 
    cantidad = VALUES(cantidad)");

	$update_factura = mysqli_query($con, "UPDATE existencias_consignacion_venta ecv
SET ecv.cantidad = ecv.cantidad - (
    SELECT IFNULL(SUM(dc.cant_consignacion), 0)
    FROM detalle_consignacion dc
    JOIN encabezado_consignacion ec ON dc.codigo_unico = ec.codigo_unico
    WHERE ec.operacion = 'factura' AND dc.numero_orden_entrada = ecv.numero_consignacion
      AND ec.ruc_empresa = ecv.ruc_empresa
)
WHERE ecv.numero_consignacion IN (
    SELECT DISTINCT dc.numero_orden_entrada
    FROM detalle_consignacion dc
    JOIN encabezado_consignacion ec ON dc.codigo_unico = ec.codigo_unico
    WHERE ec.operacion = 'factura'
      AND dc.numero_orden_entrada = ecv.numero_consignacion
      AND ec.ruc_empresa = ecv.ruc_empresa
)");

	$update_devolucion = mysqli_query($con, "UPDATE existencias_consignacion_venta ecv
SET ecv.cantidad = ecv.cantidad - (
    SELECT IFNULL(SUM(dc.cant_consignacion), 0)
    FROM detalle_consignacion dc
    JOIN encabezado_consignacion ec ON dc.codigo_unico = ec.codigo_unico
    WHERE ec.operacion = 'devolución'
      AND dc.numero_orden_entrada = ecv.numero_consignacion
      AND ec.ruc_empresa = ecv.ruc_empresa
)
WHERE ecv.numero_consignacion IN (
    SELECT DISTINCT dc.numero_orden_entrada
    FROM detalle_consignacion dc
    JOIN encabezado_consignacion ec ON dc.codigo_unico = ec.codigo_unico
    WHERE ec.operacion = 'devolución'
      AND dc.numero_orden_entrada = ecv.numero_consignacion
      AND ec.ruc_empresa = ecv.ruc_empresa
)");
}


if ($action == 'detalle_consignacion_por_numero') {
	$numero_consignacion = $_GET['numero_consignacion'];
	echo detalle_consignacion_por_numero($con, $numero_consignacion, $ruc_empresa);;
}

if ($action == 'detalle_consignacion_cliente') {
	$id_cliente = $_GET['id_cliente'];
	$id_asesor = $_GET['id_asesor'];
	$ordenado = $_GET['ordenado'];
	$por = $_GET['por'];
	$page = $_GET['page'];
	echo consignaciones_por_cliente($con, $ruc_empresa, $id_cliente, $id_asesor, $page, $ordenado, $por);
}


if ($action == 'existencia_consignacion_ventas') {
	$tipo_existencia = mysqli_real_escape_string($con, (strip_tags($_GET['tipo_existencia'], ENT_QUOTES)));
	$id_nombre_buscar = mysqli_real_escape_string($con, (strip_tags($_GET['id_nombre_buscar'], ENT_QUOTES)));
	$nombre_buscar = mysqli_real_escape_string($con, (strip_tags($_GET['nombre_buscar'], ENT_QUOTES)));
	$asesor = mysqli_real_escape_string($con, (strip_tags($_GET['asesor'], ENT_QUOTES)));
	$ordenado = mysqli_real_escape_string($con, (strip_tags($_GET['ordenado'], ENT_QUOTES)));
	$por = mysqli_real_escape_string($con, (strip_tags($_GET['por'], ENT_QUOTES)));

	//buscar por cliente
	if ($tipo_existencia == '1') {
		if (empty($id_nombre_buscar)) {
?>
			<div class="alert alert-danger" role="alert">
				<button type="button" class="close" data-dismiss="alert">&times;</button>
				<strong>Mensaje! </strong>
				<?php
				echo "Seleccione un cliente.";
				?>
			</div>
		<?php
			exit;
		}

		$por_cliente = por_cliente($con, $ruc_empresa, $id_nombre_buscar, $asesor, $page = 1, $ordenado, $por)['query'];
		if ($por_cliente->num_rows == 0) {
		?>
			<div class="alert alert-danger" role="alert">
				<button type="button" class="close" data-dismiss="alert">&times;</button>
				<strong>Mensaje! </strong>
				<?php
				echo "No hay datos para mostrar.";
				?>
			</div>
		<?php
			exit;
		}
		?>
		<div class="panel-group" id="accordiones">
			<?php
			$row_detalle = mysqli_fetch_array($por_cliente);
			$cliente = $row_detalle['cliente'];
			$id_cliente = $row_detalle['id_cliente'];
			$total_saldo_cliente = total_saldo_cliente($con, $ruc_empresa, $id_cliente, $asesor);
			?>
			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" onclick="detalle_consignaciones('<?php echo $id_cliente; ?>','<?php echo $asesor; ?>','1');" data-parent="#accordiones" href="#<?php echo $id_cliente; ?>"><span class="caret"></span> <?php echo strtoupper($cliente); ?> <h5>Productos en consignación: <?php echo $total_saldo_cliente; ?> </h5>
					<div class="text-right" id="loader_cliente<?php echo $id_cliente; ?>"></div>
				</a>
				<div id="<?php echo $id_cliente; ?>" class="panel-collapse collapse">
					<div class="listado_consignaciones"></div><!-- Carga los datos ajax -->
				</div>
			</div>
			<?php
			//}
			?>
		</div>
		<?php
	}

	//busqueda por numero cv	
	if ($tipo_existencia == '2') {
		$consignacion_por_numero = consignacion_por_numero($con, $nombre_buscar, $ruc_empresa);
		if (empty($nombre_buscar)) {
		?>
			<div class="alert alert-danger" role="alert">
				<button type="button" class="close" data-dismiss="alert">&times;</button>
				<strong>Mensaje! </strong>
				<?php
				echo "Ingrese un número de consignación para buscar.";
				?>
			</div>
		<?php
			exit;
		}

		if ($consignacion_por_numero->num_rows == 0) {
		?>
			<div class="alert alert-danger" role="alert">
				<button type="button" class="close" data-dismiss="alert">&times;</button>
				<strong>Mensaje! </strong>
				<?php
				echo "No hay datos para mostrar.";
				?>
			</div>
		<?php
			exit;
		}
		echo detalle_consignacion_por_numero($con, $nombre_buscar, $ruc_empresa);
	}

	if ($tipo_existencia == '3') { //buscar consignaciones por productos
		$consignacion_por_producto = consignacion_por_producto($con, $id_nombre_buscar, $ruc_empresa, $asesor);
		if (empty($id_nombre_buscar)) {
		?>
			<div class="alert alert-danger" role="alert">
				<button type="button" class="close" data-dismiss="alert">&times;</button>
				<strong>Mensaje! </strong>
				<?php
				echo "Ingrese un producto para buscar.";
				?>
			</div>
		<?php
			exit;
		}

		if ($consignacion_por_producto->num_rows == 0) {
		?>
			<div class="alert alert-danger" role="alert">
				<button type="button" class="close" data-dismiss="alert">&times;</button>
				<strong>Mensaje! </strong>
				<?php
				echo "No hay datos para mostrar.";
				?>
			</div>
		<?php
			exit;
		}
		$cantidad_suma = 0;
		$total_facturado_suma = 0;
		$total_devuelto_suma = 0;
		?>
		<div class="panel-group" id="accordiones">
			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordiones" href="#n"><span class="caret"></span> Detalle de consignaciones del producto seleccionado</a>
				<div id="n" class="panel-collapse collapse">
					<div class="table-responsive">
						<div class="panel panel-info">
							<table class="table table-hover">
								<tr class="info">
									<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">No.CV</button></th>
									<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Asesor</button></th>
									<th class='text-right' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Fecha</button></th>
									<th class='col-xs-2' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Cliente</button></th>
									<th class='col-xs-2' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Lote</button></th>
									<th class='col-xs-2' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Nup</button></th>
									<th class='text-right' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("cant_consignacion");'>Consignado</button></th>
									<th class='text-right' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("cant_consignacion");'>Facturado</button></th>
									<th class='text-right' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("cant_consignacion");'>Devuelto</button></th>
									<th class='text-right' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("cant_consignacion");'>Saldo</button></th>
								</tr>
								<?php
								$detalle_consignacion = consignacion_por_producto($con, $id_nombre_buscar, $ruc_empresa, $asesor);
								while ($row_detalle = mysqli_fetch_array($detalle_consignacion)) {
									$codigo_producto = $row_detalle['codigo_producto'];
									$id_producto = $row_detalle['id_producto'];
									$numero_consignacion = $row_detalle['numero_consignacion'];
									$nombre_producto = $row_detalle['nombre_producto'];
									$lote = $row_detalle['lote'];
									$asesor = $row_detalle['asesor'];
									$nup = $row_detalle['nup'];
									$cliente = $row_detalle['cliente'];
									$cantidad = $row_detalle['cant_consignacion'];
									$cantidad_suma += $row_detalle['cant_consignacion'];
									$fecha_consignacion = $row_detalle['fecha_consignacion'];

									$detalle_facturado = detalle_facturado($con, $ruc_empresa, $numero_consignacion, $id_producto, $lote, $nup);
									$total_facturado = $detalle_facturado['facturado'];
									$total_facturado_suma += $detalle_facturado['facturado'];
									$todas_facturas = isset($detalle_facturado['factura']) ? $detalle_facturado['factura'] : 0;

									$detalle_devolucion = detalle_devolucion($con, $ruc_empresa, $numero_consignacion, $id_producto, $lote, $nup);
									$total_devuelto = $detalle_devolucion['devuelto'];
									$total_devuelto_suma += $detalle_devolucion['devuelto'];

								?>
									<tr>
										<td><a class='btn btn-info btn-sm' title='Detalle' onclick="detalle_consignacion('<?php echo $numero_consignacion; ?>');" data-toggle="modal" data-target="#detalleConsignacion"><?php echo $numero_consignacion; ?></a></td>
										<td class='col-xs-2'><?php echo $asesor; ?></td>
										<td class='col-xs-2'><?php echo date("d-m-Y", strtotime($fecha_consignacion)); ?></td>
										<td class='col-xs-2'><?php echo $cliente; ?></td>
										<td><?php echo strtoupper($lote); ?></td>
										<td><?php echo strtoupper($nup); ?></td>
										<td align="center"><?php echo number_format($cantidad, 0, '.', ''); ?></td>
										<td align="center">
											<?php
											if ($total_facturado > 0) {
											?>
												<a class='btn btn-info btn-xs' title="Ver documento" onclick="detalle_axistencia('Factura','<?php echo $todas_facturas; ?>');"><?php echo number_format($total_facturado, 0, '.', ''); ?> <span class="caret"></span></a>
											<?php
											}
											?>
										</td>
										<td align="center">
											<?php
											if ($total_devuelto > 0) {
											?>
												<a class='btn btn-info btn-xs' title="Ver documento" onclick="detalle_axistencia('Devolución','<?php echo $detalle_devolucion['numero']; ?>');"><?php echo number_format($total_devuelto, 0, '.', ''); ?> <span class="caret"></span></a>
											<?php
											}
											?>
										</td>
										<td align="center"><?php echo number_format($cantidad - $total_facturado - $total_devuelto, 0, '.', ''); ?></td>
									</tr>
								<?php
								}
								?>
								<tr class="info">
									<td colspan="6" class="text-left">Totales</td>
									<td class="text-right"><?php echo number_format($cantidad_suma, 0, '.', ''); ?></td>
									<td class="text-right"><?php echo number_format($total_facturado_suma, 0, '.', ''); ?></td>
									<td class="text-right"><?php echo number_format($total_devuelto_suma, 0, '.', ''); ?></td>
									<td class="text-right"><?php echo number_format($cantidad_suma - $total_facturado_suma - $total_devuelto_suma, 0, '.', ''); ?></td>
								</tr>
							</table>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
		$saldo_final = $cantidad_suma - $total_facturado_suma - $total_devuelto_suma;
		?>
		<li style="margin-bottom: 10px; margin-top: -10px;" class="list-group-item list-group-item-info" align="right">
			<h5><b><?php echo number_format($saldo_final, 0, '.', ''); ?> productos en consignación</b></h5>
		</li>
		<?php
	}

	//busqueda por nup	
	if ($tipo_existencia == '4') { //buscar consignaciones por nup
		$consignacion_por_nup = consignacion_por_nup($con, $nombre_buscar, $ruc_empresa, $asesor);
		if (empty($nombre_buscar)) {
		?>
			<div class="alert alert-danger" role="alert">
				<button type="button" class="close" data-dismiss="alert">&times;</button>
				<strong>Mensaje! </strong>
				<?php
				echo "Ingrese número de nup para buscar.";
				?>
			</div>
		<?php
			exit;
		}

		if ($consignacion_por_nup->num_rows == 0) {
		?>
			<div class="alert alert-danger" role="alert">
				<button type="button" class="close" data-dismiss="alert">&times;</button>
				<strong>Mensaje! </strong>
				<?php
				echo "No hay datos para mostrar.";
				?>
			</div>
		<?php
			exit;
		}

		$cantidad_suma = 0;
		$total_facturado_suma = 0;
		$total_devuelto_suma = 0;
		?>
		<div class="panel-group" id="accordiones">
			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordiones" href="#n"><span class="caret"></span> Detalle de consignaciones del nup seleccionado</a>
				<div id="n" class="panel-collapse collapse">
					<div class="table-responsive">
						<div class="panel panel-info">
							<table class="table table-hover">
								<tr class="info">
									<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">No.CV</button></th>
									<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Asesor</button></th>
									<th class='text-right' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Fecha</button></th>
									<th class='col-xs-2' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Cliente</button></th>
									<th class='col-xs-2' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Lote</button></th>
									<th class='text-right' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("cant_consignacion");'>Consignado</button></th>
									<th class='text-right' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("cant_consignacion");'>Facturado</button></th>
									<th class='text-right' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("cant_consignacion");'>Devuelto</button></th>
									<th class='text-right' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("cant_consignacion");'>Saldo</button></th>
								</tr>
								<?php
								$detalle_consignacion = consignacion_por_nup($con, $nombre_buscar, $ruc_empresa, $asesor);
								while ($row_detalle = mysqli_fetch_array($detalle_consignacion)) {
									$codigo_producto = $row_detalle['codigo_producto'];
									$id_producto = $row_detalle['id_producto'];
									$nombre_producto = $row_detalle['nombre_producto'];
									$numero_consignacion = $row_detalle['numero_consignacion'];
									$lote = $row_detalle['lote'];
									$asesor = $row_detalle['asesor'];
									$cliente = $row_detalle['cliente'];
									$nup = $row_detalle['nup'];
									$cantidad = $row_detalle['cant_consignacion'];
									$cantidad_suma += $row_detalle['cant_consignacion'];
									$fecha_consignacion = $row_detalle['fecha_consignacion'];

									$detalle_facturado = detalle_facturado($con, $ruc_empresa, $numero_consignacion, $id_producto, $lote, $nup);
									$total_facturado = $detalle_facturado['facturado'];
									$total_facturado_suma += $detalle_facturado['facturado'];
									$todas_facturas = isset($detalle_facturado['factura']) ? $detalle_facturado['factura'] : 0;

									$detalle_devolucion = detalle_devolucion($con, $ruc_empresa, $numero_consignacion, $id_producto, $lote, $nup);
									$total_devuelto = $detalle_devolucion['devuelto'];
									$total_devuelto_suma += $detalle_devolucion['devuelto'];

								?>
									<tr>
										<td><a class='btn btn-info btn-sm' title='Detalle' onclick="detalle_consignacion('<?php echo $numero_consignacion; ?>');" data-toggle="modal" data-target="#detalleConsignacion"><?php echo $numero_consignacion; ?></a></td>
										<td class='col-xs-2'><?php echo $asesor; ?></td>
										<td class='col-xs-2'><?php echo date("d-m-Y", strtotime($fecha_consignacion)); ?></td>
										<td class='col-xs-2'><?php echo $cliente; ?></td>
										<td><?php echo strtoupper($lote); ?></td>
										<td align="center"><?php echo number_format($cantidad, 0, '.', ''); ?></td>
										<td align="center">
											<?php
											if ($total_facturado > 0) {
											?>
												<a class='btn btn-info btn-xs' title="Ver documento" onclick="detalle_axistencia('Factura','<?php echo $todas_facturas; ?>');"><?php echo number_format($total_facturado, 0, '.', ''); ?> <span class="caret"></span></a>
											<?php
											}
											?>
										</td>
										<td align="center">
											<?php
											if ($total_devuelto > 0) {
											?>
												<a class='btn btn-info btn-xs' title="Ver documento" onclick="detalle_axistencia('Devolución','<?php echo $detalle_devolucion['numero']; ?>');"><?php echo number_format($total_devuelto, 0, '.', ''); ?> <span class="caret"></span></a>
											<?php
											}
											?>
										</td>
										<td align="center"><?php echo number_format($cantidad - $total_facturado - $total_devuelto, 0, '.', ''); ?></td>
									</tr>
								<?php

								}
								?>
							</table>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
		$saldo_final = $cantidad_suma - $total_facturado_suma - $total_devuelto_suma;
		?>
		<li style="margin-bottom: 10px; margin-top: -10px;" class="list-group-item list-group-item-info" align="right">
			<h5><b><?php echo number_format($saldo_final, 0, '.', ''); ?> productos en consignación</b></h5>
		</li>
		<?php
	}

	//busqueda por lote
	if ($tipo_existencia == '5') {
		$consignacion_por_lote = consignacion_por_lote($con, $nombre_buscar, $ruc_empresa, $asesor);
		if (empty($nombre_buscar)) {
		?>
			<div class="alert alert-danger" role="alert">
				<button type="button" class="close" data-dismiss="alert">&times;</button>
				<strong>Mensaje! </strong>
				<?php
				echo "Ingrese lote para buscar.";
				?>
			</div>
		<?php
			exit;
		}

		if ($consignacion_por_lote->num_rows == 0) {
		?>
			<div class="alert alert-danger" role="alert">
				<button type="button" class="close" data-dismiss="alert">&times;</button>
				<strong>Mensaje! </strong>
				<?php
				echo "No hay datos para mostrar.";
				?>
			</div>
		<?php
			exit;
		}

		$cantidad_suma = 0;
		$total_facturado_suma = 0;
		$total_devuelto_suma = 0;
		?>
		<div class="panel-group" id="accordiones">
			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordiones" href="#lote"><span class="caret"></span> Detalle de consignaciones del lote seleccionado</a>
				<div id="lote" class="panel-collapse collapse">
					<div class="table-responsive">
						<div class="panel panel-info">
							<table class="table table-hover">
								<tr class="info">
									<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">No.CV</button></th>
									<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Asesor</button></th>
									<th class='text-right' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Fecha</button></th>
									<th class='col-xs-2' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Cliente</button></th>
									<th class='col-xs-2' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">NUP</button></th>
									<th class='text-right' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("cant_consignacion");'>Consignado</button></th>
									<th class='text-right' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("cant_consignacion");'>Facturado</button></th>
									<th class='text-right' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("cant_consignacion");'>Devuelto</button></th>
									<th class='text-right' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("cant_consignacion");'>Saldo</button></th>
								</tr>
								<?php
								$detalle_consignacion = consignacion_por_lote($con, $nombre_buscar, $ruc_empresa, $asesor);
								while ($row_detalle = mysqli_fetch_array($detalle_consignacion)) {
									$codigo_producto = $row_detalle['codigo_producto'];
									$id_producto = $row_detalle['id_producto'];
									$nombre_producto = $row_detalle['nombre_producto'];
									$nup = $row_detalle['nup'];
									$lote = $row_detalle['lote'];
									$cliente = $row_detalle['cliente'];
									$asesor = $row_detalle['asesor'];
									$cantidad = $row_detalle['cant_consignacion'];
									$cantidad_suma += $row_detalle['cant_consignacion'];
									$fecha_consignacion = $row_detalle['fecha_consignacion'];
									$numero_consignacion = $row_detalle['numero_consignacion'];

									$detalle_facturado = detalle_facturado($con, $ruc_empresa, $numero_consignacion, $id_producto, $lote, $nup);
									$total_facturado = $detalle_facturado['facturado'];
									$total_facturado_suma += $detalle_facturado['facturado'];
									$todas_facturas = isset($detalle_facturado['factura']) ? $detalle_facturado['factura'] : 0;

									$detalle_devolucion = detalle_devolucion($con, $ruc_empresa, $numero_consignacion, $id_producto, $lote, $nup);
									$total_devuelto = $detalle_devolucion['devuelto'];
									$total_devuelto_suma += $detalle_devolucion['devuelto'];

								?>
									<tr>
										<td><a class='btn btn-info btn-sm' title='Detalle' onclick="detalle_consignacion('<?php echo $numero_consignacion; ?>');" data-toggle="modal" data-target="#detalleConsignacion"><?php echo $numero_consignacion; ?></a></td>
										<td class='col-xs-2'><?php echo $asesor; ?></td>
										<td class='col-xs-2'><?php echo date("d-m-Y", strtotime($fecha_consignacion)); ?></td>
										<td class='col-xs-2'><?php echo $cliente; ?></td>
										<td><?php echo strtoupper($nup); ?></td>
										<td align="center"><?php echo number_format($cantidad, 0, '.', ''); ?></td>
										<td align="center">
											<?php
											if ($total_facturado > 0) {
											?>
												<a class='btn btn-info btn-xs' title="Ver documento" onclick="detalle_axistencia('Factura','<?php echo $todas_facturas; ?>');"><?php echo number_format($total_facturado, 0, '.', ''); ?> <span class="caret"></span></a>
											<?php
											}
											?>
										</td>
										<td align="center">
											<?php
											if ($total_devuelto > 0) {
											?>
												<a class='btn btn-info btn-xs' title="Ver documento" onclick="detalle_axistencia('Devolución','<?php echo $detalle_devolucion['numero']; ?>');"><?php echo number_format($total_devuelto, 0, '.', ''); ?> <span class="caret"></span></a>
											<?php
											}
											?>
										</td>
										<td align="center"><?php echo number_format($cantidad - $total_facturado - $total_devuelto, 0, '.', ''); ?></td>
									</tr>
								<?php
								}
								?>
							</table>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
		$saldo_final = $cantidad_suma - $total_facturado_suma - $total_devuelto_suma;
		?>
		<li style="margin-bottom: 10px; margin-top: -10px;" class="list-group-item list-group-item-info" align="right">
			<h5><b><?php echo number_format($saldo_final, 0, '.', ''); ?> productos en consignación</b></h5>
		</li>
		<?php
	}

	//buscar por asesor
	if ($tipo_existencia == '6') {
		if (empty($asesor)) {
		?>
			<div class="alert alert-danger" role="alert">
				<button type="button" class="close" data-dismiss="alert">&times;</button>
				<strong>Mensaje! </strong>
				<?php
				echo "Seleccione un asesor.";
				?>
			</div>
		<?php
			exit;
		}

		$clientes_consignacion_asesor = clientes_consignacion_asesor($con, $ruc_empresa, $asesor, $id_nombre_buscar);
		if ($clientes_consignacion_asesor->num_rows == 0) {
		?>
			<div class="alert alert-danger" role="alert">
				<button type="button" class="close" data-dismiss="alert">&times;</button>
				<strong>Mensaje! </strong>
				<?php
				echo "No hay datos para mostrar.";
				?>
			</div>
		<?php
			exit;
		}
		?>
		<div class="panel-group" id="accordiones">
			<?php
			while ($row_detalle = mysqli_fetch_array($clientes_consignacion_asesor)) {
				$cliente = $row_detalle['cliente'];
				$id_cliente = $row_detalle['id_cliente'];
				$total_saldo_cliente = $row_detalle['saldo'];;
			?>
				<div class="panel panel-info">
					<a class="list-group-item list-group-item-info" data-toggle="collapse" onclick="detalle_consignaciones('<?php echo $id_cliente; ?>','<?php echo $asesor; ?>','1');" data-parent="#accordiones" href="#<?php echo $id_cliente; ?>"><span class="caret"></span> <?php echo strtoupper($cliente); ?> <h5>Productos en consignación: <?php echo $total_saldo_cliente; ?> </h5>
						<div class="text-right" id="loader_cliente<?php echo $id_cliente; ?>"></div>
					</a>
					<div id="<?php echo $id_cliente; ?>" class="panel-collapse collapse">
						<div class="listado_consignaciones"></div><!-- Carga los datos ajax -->
					</div>
				</div>
			<?php
			}
			?>
		</div>
	<?php
	}
}

//me saca el saldo de todas las consignaciones menos las devoluciones y facturados en base a un cliente
function total_saldo_cliente($con, $ruc_empresa, $id_cliente, $id_asesor)
{
	if (empty($id_asesor)) {
		$condicion_asesor = "";
	} else {
		$condicion_asesor = " and id_asesor=" . $id_asesor;
	}
	//mostrar las consignaciones del cliente
	$sql_consignaciones = mysqli_query($con, "SELECT round(sum(cantidad),0) as cantidad FROM existencias_consignacion_venta 
	WHERE ruc_empresa = '" . $ruc_empresa . "' AND id_cliente = '" . $id_cliente . "' $condicion_asesor group by id_cliente ");
	$row_consignado = mysqli_fetch_array($sql_consignaciones);
	$cantidad = isset($row_consignado['cantidad']) ? $row_consignado['cantidad'] : 0;
	return $cantidad;
}

//me saca el saldo de una consignacion en base a un numero de consignacion
function total_saldo_consignacion($con, $ruc_empresa, $consignacion, $id_asesor)
{
	if (empty($id_asesor)) {
		$condicion_asesor = "";
	} else {
		$condicion_asesor = " and id_asesor=" . $id_asesor;
	}

	$sql_consignaciones = mysqli_query($con, "SELECT round(sum(cantidad),0) as cantidad FROM existencias_consignacion_venta 
	WHERE ruc_empresa = '" . $ruc_empresa . "' and numero_consignacion = '" . $consignacion . "' $condicion_asesor group by numero_consignacion");
	$row_consignado = mysqli_fetch_array($sql_consignaciones);
	$cantidad = isset($row_consignado['cantidad']) ? $row_consignado['cantidad'] : 0;
	return $cantidad;
}


//para sacar el detalle factura y numero de factura
function detalle_facturado($con, $ruc_empresa, $numero_consignacion, $id_producto, $lote, $nup)
{
	$facturado = mysqli_query($con, "SELECT round(sum(det_con.cant_consignacion),0) as facturado, 
	concat(enc_con.serie_sucursal,'-', enc_con.factura_venta) as factura 
	FROM encabezado_consignacion as enc_con INNER JOIN detalle_consignacion as det_con 
	ON enc_con.codigo_unico=det_con.codigo_unico WHERE enc_con.ruc_empresa='" . $ruc_empresa . "' 
	and det_con.ruc_empresa='" . $ruc_empresa . "' and enc_con.tipo_consignacion='VENTA' 
	and enc_con.operacion='FACTURA' and det_con.numero_orden_entrada='" . $numero_consignacion . "' 
	and det_con.id_producto='" . $id_producto . "' and det_con.lote='" . $lote . "' 
	and det_con.nup='" . $nup . "' ");
	$row_facturado = mysqli_fetch_array($facturado);
	$detalle = array('facturado' => $row_facturado['facturado'], 'factura' => $row_facturado['factura']);
	return $detalle;
}


//para sacar el total de devoluciones y el numero de devoluciones
function detalle_devolucion($con, $ruc_empresa, $numero_consignacion, $id_producto, $lote, $nup)
{
	$devuelto = mysqli_query($con, "SELECT round(sum(det_con.cant_consignacion),0) as devuelto, enc_con.numero_consignacion as numero   
										FROM encabezado_consignacion as enc_con INNER JOIN detalle_consignacion as det_con 
										ON enc_con.codigo_unico=det_con.codigo_unico WHERE enc_con.ruc_empresa='" . $ruc_empresa . "' 
										and det_con.ruc_empresa='" . $ruc_empresa . "' and enc_con.tipo_consignacion='VENTA' 
										and enc_con.operacion='DEVOLUCIÓN' and det_con.numero_orden_entrada='" . $numero_consignacion . "' 
										and det_con.id_producto='" . $id_producto . "' and det_con.lote='" . $lote . "' 
										and det_con.nup='" . $nup . "'");
	$row_devuelto = mysqli_fetch_array($devuelto);
	$detalle = array('devuelto' => $row_devuelto['devuelto'], 'numero' => $row_devuelto['numero']);
	return $detalle;
}

function consignacion_por_numero($con, $numero, $ruc_empresa)
{
	$query = mysqli_query($con, "SELECT enc.fecha_consignacion as fecha_consignacion,
	enc.observaciones as observaciones, cli.nombre as cliente, ven.nombre as asesor, det.id_producto as id_producto,
	 det.codigo_producto as codigo_producto, det.nombre_producto as nombre_producto, det.lote as lote, det.nup as nup,
	 det.cant_consignacion as cant_consignacion, bod.nombre_bodega as bodega, enc.codigo_unico as codigo_unico
	 FROM encabezado_consignacion as enc 
			INNER JOIN detalle_consignacion as det
			ON det.codigo_unico=enc.codigo_unico 
			INNER JOIN clientes as cli 
			ON cli.id=enc.id_cli_pro
			LEFT JOIN vendedores as ven
			ON ven.id_vendedor=enc.responsable
			INNER JOIN bodega as bod
			ON bod.id_bodega=det.id_bodega
			WHERE enc.ruc_empresa='" . $ruc_empresa . "' 
		and enc.numero_consignacion='" . $numero . "' and enc.tipo_consignacion='VENTA' 
		and enc.operacion='ENTRADA'");
	return $query;
}

function consignacion_por_producto($con, $id_producto, $ruc_empresa, $asesor)
{
	if (empty($asesor)) {
		$condicion_asesor = "";
	} else {
		$condicion_asesor = " and enc.responsable=" . $asesor;
	}

	$query = mysqli_query($con, "SELECT enc.fecha_consignacion as fecha_consignacion, enc.numero_consignacion as numero_consignacion,
			enc.observaciones as observaciones, cli.nombre as cliente, ven.nombre as asesor, det.id_producto as id_producto,
			 det.codigo_producto as codigo_producto, det.nombre_producto as nombre_producto, det.lote as lote, det.nup as nup,
			 det.cant_consignacion as cant_consignacion
			 FROM encabezado_consignacion as enc 
					INNER JOIN detalle_consignacion as det
					ON det.codigo_unico=enc.codigo_unico 
					INNER JOIN clientes as cli 
					ON cli.id=enc.id_cli_pro
					INNER JOIN vendedores as ven
					ON ven.id_vendedor=enc.responsable
					WHERE enc.ruc_empresa='" . $ruc_empresa . "' 
				and det.id_producto='" . $id_producto . "' $condicion_asesor and enc.operacion='ENTRADA' 
				order by enc.numero_consignacion desc");
	return $query;
}

function consignacion_por_nup($con, $nup, $ruc_empresa, $asesor)
{
	if (empty($asesor)) {
		$condicion_asesor = "";
	} else {
		$condicion_asesor = " and enc.responsable=" . $asesor;
	}

	$query = mysqli_query($con, "SELECT enc.fecha_consignacion as fecha_consignacion, enc.numero_consignacion as numero_consignacion,
			enc.observaciones as observaciones, cli.nombre as cliente, ven.nombre as asesor, det.id_producto as id_producto,
			 det.codigo_producto as codigo_producto, det.nombre_producto as nombre_producto, det.lote as lote, det.nup as nup,
			 det.cant_consignacion as cant_consignacion
			 FROM encabezado_consignacion as enc 
					INNER JOIN detalle_consignacion as det
					ON det.codigo_unico=enc.codigo_unico 
					INNER JOIN clientes as cli 
					ON cli.id=enc.id_cli_pro
					INNER JOIN vendedores as ven
					ON ven.id_vendedor=enc.responsable
					WHERE enc.ruc_empresa='" . $ruc_empresa . "' 
				and det.nup='" . $nup . "' $condicion_asesor and enc.operacion='ENTRADA' 
				order by enc.numero_consignacion desc");
	return $query;
}

function consignacion_por_lote($con, $lote, $ruc_empresa, $asesor)
{
	if (empty($asesor)) {
		$condicion_asesor = "";
	} else {
		$condicion_asesor = " and enc.responsable=" . $asesor;
	}

	$query = mysqli_query($con, "SELECT enc.fecha_consignacion as fecha_consignacion, enc.numero_consignacion as numero_consignacion,
			enc.observaciones as observaciones, cli.nombre as cliente, ven.nombre as asesor, det.id_producto as id_producto,
			 det.codigo_producto as codigo_producto, det.nombre_producto as nombre_producto, det.lote as lote, det.nup as nup,
			 det.cant_consignacion as cant_consignacion
			 FROM encabezado_consignacion as enc 
					INNER JOIN detalle_consignacion as det
					ON det.codigo_unico=enc.codigo_unico 
					INNER JOIN clientes as cli 
					ON cli.id=enc.id_cli_pro
					INNER JOIN vendedores as ven
					ON ven.id_vendedor=enc.responsable
					WHERE enc.ruc_empresa='" . $ruc_empresa . "' 
				and det.lote='" . $lote . "' $condicion_asesor and enc.operacion='ENTRADA' 
				order by enc.numero_consignacion desc");
	return $query;
}

function clientes_consignacion_asesor($con, $ruc_empresa, $asesor, $id_cliente)
{
	if (empty($id_cliente)) {
		$condicion_cliente = "";
	} else {
		$condicion_cliente = " and exi.id_cliente=" . $id_cliente;
	}
	$query = mysqli_query($con, "
    SELECT cli.nombre AS cliente, 
           cli.id AS id_cliente, 
           ROUND(SUM(exi.cantidad), 0) AS saldo
    FROM existencias_consignacion_venta AS exi
    INNER JOIN clientes AS cli 
        ON cli.id = exi.id_cliente
    WHERE exi.ruc_empresa = '" . $ruc_empresa . "' 
      AND exi.id_asesor = '" . $asesor . "'
      $condicion_cliente
    GROUP BY exi.id_cliente
    ORDER BY saldo DESC
");
	return $query;
}

function por_cliente($con, $ruc_empresa, $id_cliente, $asesor, $pagina, $ordenado, $por)
{
	if (empty($asesor)) {
		$condicion_asesor = " ";
	} else {
		$condicion_asesor = " and exi.id_asesor=" . $asesor;
	}

	if (empty($id_cliente)) {
		$condicion_cliente = " ";
	} else {
		$condicion_cliente = " and exi.id_cliente=" . $id_cliente;
	}

	$sTable = " existencias_consignacion_venta as exi
        INNER JOIN encabezado_consignacion as enc ON enc.numero_consignacion = exi.numero_consignacion 
        LEFT JOIN clientes as cli ON cli.id = exi.id_cliente
        LEFT JOIN vendedores as ven ON ven.id_vendedor = exi.id_asesor
    ";

	$sWhere = " WHERE exi.ruc_empresa = '" . $ruc_empresa . "' and enc.operacion='entrada'
        $condicion_cliente 
        $condicion_asesor 
        ORDER BY $ordenado $por
    ";

	$page = $pagina > 0 ? $pagina : 1;
	$per_page = 10; //how much records you want to show
	$adjacents  = 4; //gap between pages after number of adjacents
	$offset = ($page - 1) * $per_page;

	// Count the total number of rows in your table
	$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
	$row = mysqli_fetch_array($count_query);
	$numrows = $row['numrows'];
	$total_pages = ceil($numrows / $per_page);
	$reload = '';

	// Main query to fetch the data
	$query = mysqli_query($con, "
        SELECT enc.fecha_consignacion AS fecha_consignacion, 
               enc.numero_consignacion AS numero_consignacion, 
               cli.nombre AS cliente, 
               cli.id AS id_cliente, 
               ven.nombre AS asesor,
               enc.codigo_unico AS codigo_unico, 
               enc.observaciones AS observaciones, 
               ROUND(exi.cantidad, 0) AS saldo, DATEDIFF(CURDATE(), enc.fecha_consignacion) AS dias
        FROM $sTable 
        $sWhere 
        LIMIT $offset, $per_page
    ");

	$resultado = array(
		'query' => $query,
		'page' => $page,
		'reload' => $reload,
		'total_pages' => $total_pages,
		'adjacents' => $adjacents
	);

	return $resultado;
}


function por_cliente_excel($con, $ruc_empresa, $id_cliente, $asesor)
{

	if (empty($asesor)) {
		$condicion_asesor = " ";
	} else {
		$condicion_asesor = " and exi.id_asesor=" . $asesor;
	}

	if (empty($id_cliente)) {
		$condicion_cliente = " ";
	} else {
		$condicion_cliente = " and exi.id_cliente=" . $id_cliente;
	}

	$sTable = " existencias_consignacion_venta as exi
        INNER JOIN encabezado_consignacion as enc ON enc.numero_consignacion = exi.numero_consignacion 
        LEFT JOIN clientes as cli ON cli.id = exi.id_cliente
        LEFT JOIN vendedores as ven ON ven.id_vendedor = exi.id_asesor
    ";

	$sWhere = " WHERE exi.ruc_empresa = '" . $ruc_empresa . "' and enc.operacion='entrada'
        $condicion_cliente 
        $condicion_asesor 
        order by enc.fecha_consignacion desc
    ";

	$query = mysqli_query($con, "SELECT enc.fecha_consignacion as fecha_consignacion, 
	enc.numero_consignacion as numero_consignacion, cli.nombre as cliente, cli.id as id_cliente, ven.nombre as asesor,
	enc.observaciones as observaciones, exi.cantidad as saldo FROM $sTable $sWhere ");
	return $query;
}


function consignaciones_por_cliente($con, $ruc_empresa, $id_cliente, $asesor, $page, $ordenado, $por)
{
	$por_cliente = por_cliente($con, $ruc_empresa, $id_cliente, $asesor, $page, $ordenado, $por); //este es el resultado de la busqueda por 10 registros
	?>
	<div class="panel panel-info">
		<div class="table-responsive">
			<table class="table table-hover">
				<tr class="info">
					<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("exi.numero_consignacion");'>No.CV</button></th>
					<th class='col-xs-2' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("enc.fecha_consignacion");'>Fecha</button></th>
					<th class='col-xs-2' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("enc.fecha_consignacion");'>Días</button></th>
					<th class='col-xs-2' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("ven.nombre");'>Asesor</button></th>
					<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("enc.observaciones");'>Observaciones</button></th>
					<th class='text-right' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("exi.cantidad");'>Saldo_consignado</button></th>
				</tr>
				<?php
				$fecha_hoy = date_create(date("Y-m-d H:i:s"));
				while ($row_detalle = mysqli_fetch_array($por_cliente['query'])) {
					$numero_consignacion = $row_detalle['numero_consignacion'];
					$fecha_consignacion = $row_detalle['fecha_consignacion'];
					$nombre_asesor = $row_detalle['asesor'];
					$observaciones = $row_detalle['observaciones'];
					$total_saldo_consignacion = $row_detalle['saldo'];
					$dias_transcurridos = $row_detalle['dias'];

					if (($total_saldo_consignacion) > 0) {
						$dias_transcurridos = $dias_transcurridos . " Días";
					} else {
						$dias_transcurridos = "---";
					}
				?>
					<tr>
						<td><a class='btn btn-info btn-sm' title='Detalle' onclick="detalle_consignacion('<?php echo $numero_consignacion; ?>');" data-toggle="modal" data-target="#detalleConsignacion"><?php echo $numero_consignacion; ?></a></td>
						<td class='col-xs-2'><?php echo date("d-m-Y", strtotime($fecha_consignacion)); ?></td>
						<td class='col-xs-1'><?php echo $dias_transcurridos; ?> </td>
						<td class='col-xs-2'><?php echo strtoupper($nombre_asesor); ?></td>
						<td><?php echo strtoupper($observaciones); ?></td>
						<td class='col-xs-2 text-center'><?php echo $total_saldo_consignacion; ?></td>
					</tr>
				<?php
				}
				?>
				<tr>
					<td colspan="6"><span class="pull-right">
							<?php
							echo paginate($por_cliente['reload'], $por_cliente['page'], $por_cliente['total_pages'], $por_cliente['adjacents']);
							?></span></td>
				</tr>
			</table>
		</div>
	</div>
	<?php
}

function detalle_consignacion_por_numero($con, $numero_consignacion, $ruc_empresa)
{
	$consignacion_por_numero = consignacion_por_numero($con, $numero_consignacion, $ruc_empresa);
	$row = mysqli_fetch_array($consignacion_por_numero);
	if ($row) {
		$fecha_consignacion = $row['fecha_consignacion'];
		$observaciones = strtoupper($row['observaciones']);
		$nombre_cliente = $row['cliente'];
		$codigo_unico = $row['codigo_unico'];
	} else {
	?>
		<div class="alert alert-danger" role="alert">
			<button type="button" class="close" data-dismiss="alert">&times;</button>
			<strong>Mensaje! </strong>
			<?php
			echo "No hay datos para mostrar.";
			?>
		</div>
	<?php
		exit;
	}
	?>
	<div style="margin-bottom: 10px; margin-top: -10px;" class="list-group-item list-group-item-info">
		<h5><b>No.</b> <?php echo $numero_consignacion; ?> <b>Fecha:</b> <?php echo date("d-m-Y", strtotime($fecha_consignacion)); ?> <b>Cliente: </b><?php echo strtoupper($nombre_cliente); ?> <b>Observaciones: </b><?php echo strtoupper($observaciones); ?> <a onmouseover="this.style.color='green';" onmouseout="this.style.color='black';" href="../pdf/pdf_consignacion_ventas.php?codigo_unico=<?php echo $codigo_unico ?>&action=conciliado" target="_blank" class='btn btn-default btn-sm' title='Descargar pdf'><span class='glyphicon glyphicon-list-alt'></span> Descargar Pdf</i> </a></h5>
	</div>
	<div class="table-responsive">
		<div class="panel panel-info" style="height: 280px; overflow-y: auto; margin-bottom: 5px;">
			<table class="table table-hover">
				<tr class="info">
					<th class='col-xs-2' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("codigo_producto");'>Código</button></th>
					<th class='col-xs-2' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("nombre_producto");'>Producto</button></th>
					<th class='col-xs-2' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("lote");'>Lote</button></th>
					<th class='col-xs-2' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("nup");'>Nup</button></th>
					<th class='col-xs-2' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("bodega");'>Bodega</button></th>
					<th class='text-right' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("cant_consignacion");'>Consignado</button></th>
					<th class='text-right' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("cant_consignacion");'>Retorno</button></th>
					<th class='text-right' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("cant_consignacion");'>Facturado</button></th>
					<th class='text-right' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("cant_consignacion");'>Saldo</button></th>
				</tr>
				<?php
				$cantidad_suma = 0;
				$total_facturado_suma = 0;
				$total_devuelto_suma = 0;
				$detalle_consignacion = consignacion_por_numero($con, $numero_consignacion, $ruc_empresa);
				while ($row_detalle = mysqli_fetch_array($detalle_consignacion)) {
					$id_producto = $row_detalle['id_producto'];
					$codigo_producto = $row_detalle['codigo_producto'];
					$nombre_producto = $row_detalle['nombre_producto'];
					$lote = $row_detalle['lote'];
					$nup = $row_detalle['nup'];
					$bodega = $row_detalle['bodega'];
					$cantidad = $row_detalle['cant_consignacion'];
					$cantidad_suma += $row_detalle['cant_consignacion'];

					$detalle_facturado = detalle_facturado($con, $ruc_empresa, $numero_consignacion, $id_producto, $lote, $nup);
					$total_facturado = $detalle_facturado['facturado'];
					$total_facturado_suma += $detalle_facturado['facturado'];
					$todas_facturas = isset($detalle_facturado['factura']) ? $detalle_facturado['factura'] : 0;

					$detalle_devolucion = detalle_devolucion($con, $ruc_empresa, $numero_consignacion, $id_producto, $lote, $nup);
					$total_devuelto = $detalle_devolucion['devuelto'];
					$total_devuelto_suma += $detalle_devolucion['devuelto'];
				?>
					<tr>
						<td class='col-xs-2'><?php echo strtoupper($codigo_producto); ?></td>
						<td class='col-xs-2'><?php echo strtoupper($nombre_producto); ?></td>
						<td><?php echo strtoupper($lote); ?></td>
						<td><?php echo strtoupper($nup); ?></td>
						<td><?php echo strtoupper($bodega); ?></td>
						<td align="center"><?php echo number_format($cantidad, 0, '.', ''); ?></td>
						<td align="center">
							<?php
							if ($total_devuelto > 0) {
							?>
								<a class='btn btn-info btn-xs' title="Ver documento" onclick="detalle_axistencia('Devolución','<?php echo $detalle_devolucion['numero']; ?>');"><?php echo number_format($total_devuelto, 0, '.', ''); ?> <span class="caret"></span></a>
							<?php
							}
							?>
						</td>
						<td align="center">
							<?php
							if ($total_facturado > 0) {
							?>
								<a class='btn btn-info btn-xs' title="Ver documento" onclick="detalle_axistencia('Factura','<?php echo $todas_facturas; ?>');"><?php echo number_format($total_facturado, 0, '.', ''); ?> <span class="caret"></span></a>
							<?php
							}
							?>
						</td>

						<td align="center"><?php echo number_format($cantidad - $total_facturado - $total_devuelto, 0, '.', ''); ?></td>
					</tr>
				<?php
				}
				$saldo_final = $cantidad_suma - $total_facturado_suma - $total_devuelto_suma;
				?>
			</table>
		</div>
	</div>
	<div class="list-group-item list-group-item-info" align="right">
		<h5><b><?php echo number_format($saldo_final, 0, '.', ''); ?> productos en consignación</b></h5>
	</div>
<?php
}

function detalle_productos_por_cliente($con, $ruc_empresa, $id_cliente, $asesor)
{
	if (empty($asesor)) {
		$condicion_asesor = " ";
	} else {
		$condicion_asesor = " and enc.responsable=" . $asesor;
	}

	if (empty($id_cliente)) {
		$condicion_cliente = " ";
	} else {
		$condicion_cliente = " and enc.id_cli_pro=" . $id_cliente;
	}

	$sTable = "detalle_consignacion as det 
	INNER JOIN encabezado_consignacion as enc 
	ON enc.codigo_unico=det.codigo_unico
	INNER JOIN clientes as cli ON cli.id=enc.id_cli_pro 
	INNER JOIN vendedores as ven ON ven.id_vendedor=enc.responsable";
	$sWhere = "WHERE enc.ruc_empresa='" . $ruc_empresa . "' 
	$condicion_cliente $condicion_asesor 
	and enc.tipo_consignacion='VENTA' and enc.operacion='ENTRADA' 
	order by enc.fecha_consignacion desc ";

	$query = mysqli_query($con, "SELECT enc.fecha_consignacion as fecha_consignacion, enc.numero_consignacion as numero_consignacion, det.id_producto as id_producto, det.codigo_producto as codigo_producto,
	det.nombre_producto as nombre_producto, det.lote as lote, det.nup as nup, ven.nombre as asesor,
	det.cant_consignacion as cant_consignacion FROM $sTable $sWhere ");
	return $query;
}

function detalle_consignado($con, $ruc_empresa, $numero_consignacion)
{
	$consignado = mysqli_query($con, "SELECT round(sum(det_con.cant_consignacion),0) as consignado 
	FROM encabezado_consignacion as enc_con INNER JOIN detalle_consignacion as det_con 
	ON enc_con.codigo_unico=det_con.codigo_unico WHERE enc_con.ruc_empresa='" . $ruc_empresa . "' 
	and det_con.ruc_empresa='" . $ruc_empresa . "' and enc_con.tipo_consignacion='VENTA' 
	and enc_con.operacion='ENTRADA' and enc_con.numero_consignacion='" . $numero_consignacion . "' ");
	$row_consignado = mysqli_fetch_array($consignado);
	return $row_consignado['consignado'];
}
