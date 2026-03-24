<?php
/* Connect To Database*/
include("../conexiones/conectalogin.php");
include("../helpers/helpers.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];

//PARA BUSCAR LAS FACTURAS de ventas	
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
$id_cliente = $_POST['id_cliente'];
$id_producto = $_POST['id_producto'];
$desde = $_POST['fecha_desde'];
$hasta = $_POST['fecha_hasta'];
$id_marca = $_POST['id_marca'];
$id_grupo = $_POST['id_grupo'];
$id_vendedor = $_POST['vendedor'];
ini_set('date.timezone', 'America/Guayaquil');

if ($action == '1') { // reporte de ventas
?>
	<div class="panel panel-info">
		<div class="table-responsive">
			<table class="table table-hover">
				<tr class="info">
					<th>#</th>
					<th>Fecha</th>
					<th>Cliente</th>
					<th>Ruc</th>
					<th>Secuencial</th>
					<th>Subtotal</th>
					<th>Descuento</th>
					<th>IVA</th>
					<th>Propina</th>
					<th>Otros</th>
					<th>Total</th>
					<th>Asesor</th>
				</tr>
				<?php
				$suma_factura = 0;
				$suma_subtotal = 0;
				$suma_base_descuento = 0;
				$suma_propina = 0;
				$suma_tasa_turistica = 0;
				$suma_iva = 0;
				$n = 0;

				$resultado = reporte_ventas_facturas($con, $ruc_empresa, $desde, $hasta, $id_cliente, $id_producto, $id_marca, $id_grupo, $id_vendedor);
				while ($row = mysqli_fetch_array($resultado)) {
					$suma_factura += $row['total_factura'];
					$fecha_factura = $row['fecha_factura'];
					$serie_factura = $row['serie_factura'];
					$secuencial_factura = $row['secuencial_factura'];
					$nombre_cliente_factura = $row['cliente'];
					$total_factura = $row['total_factura'];
					$ruc_cliente = $row['ruc'];
					$n = $n + 1;

				?>
					<tr>
						<td><?php echo $n; ?></td>
						<td><?php echo date("d/m/Y", strtotime($fecha_factura)); ?></td>
						<td><?php echo $nombre_cliente_factura; ?></td>
						<td><?php echo $ruc_cliente; ?></td>
						<td><?php echo $serie_factura; ?>-<?php echo str_pad($secuencial_factura, 9, "000000000", STR_PAD_LEFT); ?></td>
						<td class="text-right"><?php echo number_format($row['subtotal'], 2, '.', ''); ?></td>
						<td class="text-right"><?php echo number_format($row['descuento'], 2, '.', ''); ?></td>
						<td class="text-right"><?php echo number_format($row['total_iva'], 2, '.', ''); ?></td>
						<td class="text-right"><?php echo number_format($row['propina'], 2, '.', ''); ?></td>
						<td class="text-right"><?php echo number_format($row['tasa_turistica'], 2, '.', ''); ?></td>
						<td class="text-right"><?php echo number_format($row['total_factura'], 2, '.', ''); ?></td>
						<td><?php echo $row['vendedor']; ?></td>
					</tr>
				<?php
					$suma_subtotal += $row['subtotal'];
					$suma_iva += $row['total_iva'];
					$suma_base_descuento += $row['descuento'];
					$suma_propina += $row['propina'];
					$suma_tasa_turistica += $row['tasa_turistica'];
				}
				?>
				<tr class="info">
					<th colspan="4">Totales</th>
					<td><span id="loader_excel"></span></td>
					<td class="text-right"><?php echo number_format($suma_subtotal, 2, '.', ''); ?></td>
					<td class="text-right"><?php echo number_format($suma_base_descuento, 2, '.', ''); ?></td>
					<td class="text-right"><?php echo number_format($suma_iva, 2, '.', ''); ?></td>
					<td class="text-right"><?php echo number_format($suma_propina, 2, '.', ''); ?></td>
					<td class="text-right"><?php echo number_format($suma_tasa_turistica, 2, '.', ''); ?></td>
					<td class="text-right"><?php echo number_format($suma_factura, 2, '.', ''); ?></td>
					<td></td>
				</tr>
			</table>
		</div>
	</div>
<?php
}

if ($action == '2') { //notas de credito
?>
	<div class="panel panel-info">
		<div class="table-responsive">
			<table class="table table-hover">
				<tr class="info">
					<th>#</th>
					<th>Fecha</th>
					<th>Cliente</th>
					<th>Ruc</th>
					<th>NC</th>
					<th>Factura</th>
					<th>Subtotal</th>
					<th>Descuento</th>
					<th>IVA</th>
					<th>Total</th>
					<th>Asesor</th>
				</tr>
				<?php
				$suma_nc = 0;
				$suma_subtotal = 0;
				$suma_descuento = 0;
				$suma_iva = 0;
				$n = 0;

				$resultado_nc = reporte_nc($con, $ruc_empresa, $desde, $hasta, $id_cliente, $id_producto, $id_marca, $id_grupo, $id_vendedor);
				while ($row = mysqli_fetch_array($resultado_nc)) {
					$suma_nc += $row['total_nc'];
					$id_encabezado_nc = $row['id_encabezado_nc'];
					$fecha_nc = $row['fecha_nc'];
					$serie_nc = $row['serie_nc'];
					$secuencial_nc = $row['secuencial_nc'];
					$nombre_cliente_nc = $row['cliente'];
					$factura_afectada = $row['factura_modificada'];
					$total_nc = $row['total_nc'];
					$ruc_cliente = $row['ruc'];
					$vendedor = $row['vendedor'];
					$n = $n + 1;

				?>
					<tr>
						<td><?php echo $n; ?></td>
						<td><?php echo date("d/m/Y", strtotime($fecha_nc)); ?></td>
						<td><?php echo $nombre_cliente_nc; ?></td>
						<td><?php echo $ruc_cliente; ?></td>
						<td><?php echo $serie_nc; ?>-<?php echo str_pad($secuencial_nc, 9, "000000000", STR_PAD_LEFT); ?></td>
						<td><?php echo $factura_afectada; ?></td>
						<td class="text-right"><?php echo number_format($row['subtotal'], 2, '.', ''); ?></td>
						<td class="text-right"><?php echo number_format($row['descuento'], 2, '.', ''); ?></td>
						<td class="text-right"><?php echo number_format($row['total_iva'], 2, '.', ''); ?></td>
						<td class="text-right"><?php echo number_format($total_nc, 2, '.', ''); ?></td>
						<td><?php echo $vendedor; ?></td>
					</tr>
				<?php
					$suma_subtotal += $row['subtotal'];
					$suma_descuento += $row['descuento'];
					$suma_iva += $row['total_iva'];
				}
				?>
				<tr class="info">
					<th colspan="5">Totales</th>
					<td><span id="loader_nc"></span></td>
					<td class="text-right"><?php echo number_format($suma_subtotal, 2, '.', ''); ?></td>
					<td class="text-right"><?php echo number_format($suma_descuento, 2, '.', ''); ?></td>
					<td class="text-right"><?php echo number_format($suma_iva, 2, '.', ''); ?></td>
					<td class="text-right"><?php echo number_format($suma_nc, 2, '.', ''); ?></td>
					<td></td>
				</tr>
			</table>
		</div>
	</div>
<?php
}
// para buscar las facturas en detalle
if ($action == '3') {
?>
	<div class="panel panel-info">
		<div class="table-responsive">
			<table class="table table-hover">
				<tr class="info">
					<td>#</td>
					<td>Fecha</td>
					<td>Cliente</td>
					<td>Factura</td>
					<td>Código</td>
					<td>Detalle</td>
					<td>Tarifa</td>
					<td>Cantidad</td>
					<td>Valor Uni.</td>
					<td>Descuento</td>
					<td>Subtotal</td>
					<td>IVA</td>
					<td>Total</td>
				</tr>
				<?php
				$n = 0;

				$suma_total_factura = 0;
				$suma_cantidad = 0;
				$suma_valor_unitario = 0;
				$suma_subtotal_factura = 0;
				$suma_descuento = 0;
				$suma_iva = 0;
				$resultado = detalle_facturas($con, $ruc_empresa, $id_cliente, $id_producto, $id_marca, $id_grupo, $desde, $hasta);
				while ($row = mysqli_fetch_array($resultado)) {
					$n = $n + 1;
					$fecha_factura = $row['fecha_factura'];
					$serie_factura = $row['serie_factura'];
					$secuencial_factura = $row['secuencial_factura'];
					$nombre_cliente_factura = $row['nombre_cliente'];
					$ruc_cliente = $row['ruc'];
					$cantidad = $row['cantidad_factura'];
					$suma_cantidad += $row['cantidad_factura'];
					$producto = $row['nombre_producto'];
					$codigo = $row['codigo_producto'];
					$valor_unitario = $row['valor_unitario_factura'];
					$suma_valor_unitario += $row['valor_unitario_factura'];
					$descuento = $row['descuento'];
					$suma_descuento += $row['descuento'];
					$subtotal_factura = $row['subtotal'];
					$suma_subtotal_factura += $row['subtotal'];
					$suma_iva += $row['total_iva'];
					$tarifa_iva = $row['tarifa'];
					$total_factura = number_format(($row['subtotal'] + $row['total_iva']), 2, '.', '');
					$suma_total_factura += $total_factura;
				?>
					<tr>
						<td><?php echo $n; ?></td>
						<td><?php echo date("d/m/Y", strtotime($fecha_factura)); ?></td>
						<td><?php echo $nombre_cliente_factura; ?></td>
						<td><?php echo $serie_factura; ?>-<?php echo str_pad($secuencial_factura, 9, "000000000", STR_PAD_LEFT); ?></td>
						<td><?php echo $codigo ?></td>
						<td><?php echo $producto ?></td>
						<td><?php echo $tarifa_iva ?></td>
						<td><?php echo $cantidad ?></td>
						<td><?php echo number_format($valor_unitario, 4, '.', '') ?></td>
						<td><?php echo number_format($descuento, 2, '.', '') ?></td>
						<td><?php echo  number_format($subtotal_factura, 2, '.', '') ?></td>
						<td><?php echo  number_format($row['total_iva'], 2, '.', '') ?></td>
						<td><?php echo number_format($total_factura, 2, '.', '') ?></td>
					</tr>
				<?php
				}
				?>
				<tr class="info">
					<td colspan="7" class='text-right'>Totales</td>
					<td><?php echo number_format($suma_cantidad, 2, '.', '') ?></td>
					<td><?php echo number_format($suma_valor_unitario, 2, '.', '') ?></td>
					<td><?php echo number_format($suma_descuento, 2, '.', '') ?></td>
					<td><?php echo number_format($suma_subtotal_factura, 2, '.', '') ?></td>
					<td><?php echo number_format($suma_iva, 2, '.', '') ?></td>
					<td><?php echo number_format($suma_total_factura, 2, '.', '') ?></td>
				</tr>
			</table>
		</div>
	</div>
<?php
}

// para buscar las nc en detalle
if ($action == '4') {
?>
	<div class="panel panel-info">
		<div class="table-responsive">
			<table class="table table-hover">
				<tr class="info">
					<td>#</td>
					<td>Fecha</td>
					<td>Cliente</td>
					<td>Nc</td>
					<td>Factura</td>
					<td>Motivo</td>
					<td>Código</td>
					<td>Detalle</td>
					<td>Tipo</td>
					<td>Tarifa</td>
					<td>Cantidad</td>
					<td>Valor Uni.</td>
					<td>Descuento</td>
					<td>Subtotal</td>
					<td>IVA</td>
					<td>Total</td>
				</tr>
				<?php
				$n = 0;

				$suma_total_nc = 0;
				$suma_cantidad = 0;
				$suma_valor_unitario = 0;
				$suma_subtotal_nc = 0;
				$suma_descuento = 0;
				$suma_iva = 0;
				$resultado = detalle_nc($con, $ruc_empresa, $id_cliente, $id_producto, $id_marca, $id_grupo, $desde, $hasta);
				while ($row = mysqli_fetch_array($resultado)) {
					$fecha_nc = $row['fecha_nc'];
					$serie_nc = $row['serie_nc'];
					$secuencial_nc = $row['secuencial_nc'];
					$nombre_cliente_nc = $row['nombre_cliente'];
					$ruc_cliente = $row['ruc'];
					$cantidad = $row['cantidad_nc'];
					$suma_cantidad += $row['cantidad_nc'];
					$producto = $row['nombre_producto'];
					$codigo = $row['codigo_producto'];
					$valor_unitario = $row['valor_unitario_nc'];
					$suma_valor_unitario += $row['valor_unitario_nc'];
					$descuento = $row['descuento'];
					$suma_descuento += $row['descuento'];
					$subtotal_nc = $row['subtotal_nc'] - $descuento;
					$suma_subtotal_nc += $row['subtotal_nc'] - $descuento;
					$tipo_produccion = $row['nombre_produccion'];
					$tarifa_iva = $row['tarifa'];
					$porcentaje_iva = $row['porcentaje_iva'] / 100;
					$suma_iva += number_format(($subtotal_nc * $porcentaje_iva), 2, '.', '');
					$total_nc = ($row['subtotal_nc'] - $row['descuento'] + ($subtotal_nc * $porcentaje_iva));
					$suma_total_nc += $total_nc;

					$factura_modificada = $row['factura_modificada'];
					$motivo = $row['motivo'];
					$n = $n + 1;

				?>
					<tr>
						<td><?php echo $n; ?></td>
						<td><?php echo date("d/m/Y", strtotime($fecha_nc)); ?></td>
						<td><?php echo $nombre_cliente_nc; ?></td>
						<td><?php echo $serie_nc; ?>-<?php echo str_pad($secuencial_nc, 9, "000000000", STR_PAD_LEFT); ?></td>
						<td><?php echo $factura_modificada; ?></td>
						<td><?php echo ($motivo) ?></td>
						<td><?php echo ($codigo) ?></td>
						<td><?php echo ($producto) ?></td>
						<td><?php echo ($tipo_produccion) ?></td>
						<td><?php echo ($tarifa_iva) ?></td>
						<td><?php echo ($cantidad) ?></td>
						<td><?php echo number_format($valor_unitario, 4, '.', '') ?></td>
						<td><?php echo number_format($descuento, 2, '.', '') ?></td>
						<td><?php echo  number_format($subtotal_nc, 2, '.', '') ?></td>
						<td><?php echo  number_format(($subtotal_nc * $porcentaje_iva), 2, '.', '') ?></td>
						<td><?php echo number_format($total_nc, 2, '.', '') ?></td>
					</tr>
				<?php
				}
				?>
				<tr class="info">
					<td colspan="10" class='text-right'>Totales</td>
					<td><?php echo number_format($suma_cantidad, 4, '.', '') ?></td>
					<td><?php echo number_format($suma_valor_unitario, 4, '.', '') ?></td>
					<td><?php echo number_format($suma_descuento, 2, '.', '') ?></td>
					<td><?php echo number_format($suma_subtotal_nc, 2, '.', '') ?></td>
					<td><?php echo number_format($suma_iva, 2, '.', '') ?></td>
					<td><?php echo number_format($suma_total_nc, 2, '.', '') ?></td>
				</tr>
			</table>
		</div>
	</div>
<?php
}

//para reporte de recibos
if ($action == '5') {
?>
	<div class="panel panel-info">
		<div class="table-responsive">
			<table class="table table-hover">
				<tr class="info">
					<th>#</th>
					<th>Fecha</th>
					<th>Ruc</th>
					<th>Cliente</th>
					<th>Secuencial</th>
					<th>Total</th>
					<th>Asesor</th>
				</tr>
				<?php
				$suma_recibos = 0;
				$n = 0;
				$resultado = recibos($con, $ruc_empresa, $id_cliente, $id_producto, $id_marca, $id_grupo, $desde, $hasta);
				while ($row = mysqli_fetch_array($resultado)) {
					$suma_recibos += $row['total_recibo'];
					$fecha_recibo = $row['fecha_recibo'];
					$serie_recibo = $row['serie_recibo'];
					$secuencial_recibo = $row['secuencial_recibo'];
					$nombre_cliente_recibo = $row['nombre'];
					$total_recibo = $row['total_recibo'];
					$ruc_cliente = $row['ruc'];
					$n = $n + 1;
				?>
					<tr>
						<td><?php echo $n; ?></td>
						<td><?php echo date("d/m/Y", strtotime($fecha_recibo)); ?></td>
						<td><?php echo $ruc_cliente; ?></td>
						<td><?php echo $nombre_cliente_recibo; ?></td>
						<td><?php echo $serie_recibo; ?>-<?php echo str_pad($secuencial_recibo, 9, "000000000", STR_PAD_LEFT); ?></td>
						<td><?php echo number_format($row['total_recibo'], 2, '.', ''); ?></td>
						<td><?php echo $row['vendedor']; ?></td>
					</tr>
				<?php
				}
				?>
				<tr class="info">
					<th colspan="4">Totales</th>
					<td><span id="loader_excel"></span></td>
					<td><?php echo number_format($suma_recibos, 2, '.', ''); ?></td>
					<td></td>
				</tr>

			</table>
		</div>
	</div>
<?php
}


//para reporte de recibos en detalle
if ($action == '6') {
?>
	<div class="panel panel-info">
		<div class="table-responsive">
			<table class="table table-hover">
				<tr class="info">
					<td>#</td>
					<td>Fecha</td>
					<td>Cliente</td>
					<td>Recibo</td>
					<td>Código</td>
					<td>Detalle</td>
					<td>Tarifa</td>
					<td>Cantidad</td>
					<td>V/Uni</td>
					<td>Subtotal</td>
					<td>Iva</td>
					<td>Total</td>
				</tr>
				<?php
				$suma_recibos = 0;
				$n = 0;

				$resultado = detalle_recibos($con, $ruc_empresa, $id_cliente, $id_producto, $id_marca, $id_grupo, $desde, $hasta);
				while ($row = mysqli_fetch_array($resultado)) {
					$suma_recibos += $row['subtotal'] + ($row['subtotal'] * $row['porcentaje_iva']);
					$n = $n + 1;
				?>
					<tr>
						<td><?php echo $n; ?></td>
						<td><?php echo date("d/m/Y", strtotime($row['fecha_recibo'])); ?></td>
						<td><?php echo $row['nombre']; ?></td>
						<td><?php echo $row['recibo'] ?></td>
						<td><?php echo $row['codigo'] ?></td>
						<td><?php echo $row['detalle'] ?></td>
						<td><?php echo $row['tarifa'] ?></td>
						<td><?php echo number_format($row['cantidad'], 2, '.', '') ?></td>
						<td><?php echo number_format($row['precio'], 2, '.', '') ?></td>
						<td><?php echo $row['subtotal'] ?></td>
						<td><?php echo number_format($row['subtotal'] * $row['porcentaje_iva'], 2, '.', ''); ?></td>
						<td><?php echo number_format($row['subtotal'] + ($row['subtotal'] * $row['porcentaje_iva']), 2, '.', ''); ?></td>
					</tr>
				<?php
				}
				?>
				<tr class="info">
					<th colspan="11">Totales</th>
					<td><?php echo number_format($suma_recibos, 2, '.', ''); ?></td>
				</tr>
			</table>
		</div>
	</div>
<?php
}


function reporte_ventas_facturas($con, $ruc_empresa, $desde, $hasta, $id_cliente, $id_producto, $id_marca, $id_grupo, $id_vendedor)
{

	if (empty($id_cliente)) {
		$condicion_cliente = "";
	} else {
		$condicion_cliente = " and enc_fac.id_cliente=" . $id_cliente;
	}

	if (empty($id_producto)) {
		$condicion_producto = "";
	} else {
		$condicion_producto = " and cue_fac.id_producto=" . $id_producto;
	}

	if (empty($id_marca)) {
		$condicion_marca = "";
		$condicion_marca_tarifa = "";
	} else {
		$condicion_marca = " and mar_pro.id_marca=" . $id_marca;
		$condicion_marca_tarifa = " and mar.id_marca=" . $id_marca;
	}

	if (empty($id_grupo)) {
		$condicion_grupo = "";
		$condicion_grupo_tarifa = "";
	} else {
		$condicion_grupo = " and grupo_asi.id_grupo=" . $id_grupo;
		$condicion_grupo_tarifa = " and grupo.id_grupo=" . $id_grupo;
	}

	if (empty($id_vendedor)) {
		$condicion_vendedor = "";
	} else {
		$condicion_vendedor = " and ven_ven.id_vendedor=" . $id_vendedor;
	}
	$resultado = mysqli_query($con, "SELECT ven.nombre as vendedor, cli.nombre as cliente, cli.ruc as ruc, cli.id as id_cliente,
	enc_fac.serie_factura as serie_factura, enc_fac.secuencial_factura as secuencial_factura, sum(cue_fac.subtotal_factura) as subtotal,
		sum(cue_fac.descuento) as descuento, tar.porcentaje_iva as porcentaje_iva, sum((cue_fac.subtotal_factura - cue_fac.descuento) * (tar.porcentaje_iva /100)) as total_iva,
		usu.nombre as usuario, enc_fac.propina as propina, enc_fac.tasa_turistica as tasa_turistica, enc_fac.total_factura as total_factura, enc_fac.fecha_factura as fecha_factura
		FROM cuerpo_factura as cue_fac INNER JOIN encabezado_factura as enc_fac ON enc_fac.serie_factura=cue_fac.serie_factura and enc_fac.secuencial_factura=cue_fac.secuencial_factura 
		INNER JOIN clientes as cli ON cli.id=enc_fac.id_cliente
		INNER JOIN tarifa_iva as tar ON tar.codigo=cue_fac.tarifa_iva 
		LEFT JOIN productos_servicios as pro_ser ON pro_ser.id=cue_fac.id_producto  
		LEFT JOIN usuarios as usu ON usu.id=enc_fac.id_usuario
		LEFT JOIN marca_producto as mar_pro ON mar_pro.id_producto=cue_fac.id_producto
		LEFT JOIN grupo_producto_asignado as grupo_asi ON grupo_asi.id_producto=cue_fac.id_producto 
		LEFT JOIN vendedores_ventas as ven_ven ON ven_ven.id_venta= enc_fac.id_encabezado_factura 
		LEFT JOIN vendedores as ven ON ven.id_vendedor=ven_ven.id_vendedor
		WHERE enc_fac.ruc_empresa='" . $ruc_empresa . "' and cue_fac.ruc_empresa='" . $ruc_empresa . "' 
		and DATE_FORMAT(enc_fac.fecha_factura, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
		and '" . date("Y/m/d", strtotime($hasta)) . "' $condicion_cliente $condicion_producto $condicion_marca $condicion_grupo $condicion_vendedor
		group by cue_fac.serie_factura, cue_fac.secuencial_factura");
	return $resultado;
}

function reporte_nc($con, $ruc_empresa, $desde, $hasta, $id_cliente, $id_producto, $id_marca, $id_grupo, $id_vendedor)
{
	if (empty($id_cliente)) {
		$condicion_cliente = "";
	} else {
		$condicion_cliente = " and enc_nc.id_cliente=" . $id_cliente;
	}

	if (empty($id_producto)) {
		$condicion_producto = "";
	} else {
		$condicion_producto = " and cue_nc.id_producto=" . $id_producto;
	}

	if (empty($id_marca)) {
		$condicion_marca = "";
	} else {
		$condicion_marca = " and mar_pro.id_marca=" . $id_marca;
	}

	if (empty($id_grupo)) {
		$condicion_grupo = "";
	} else {
		$condicion_grupo = " and grupo_asi.id_grupo=" . $id_grupo;
	}

	if (empty($id_vendedor)) {
		$condicion_vendedor = "";
	} else {
		$condicion_vendedor = " and ven_ncv.id_vendedor=" . $id_vendedor;
	}

	$resultado = mysqli_query($con, "SELECT cli.nombre as cliente, sum(cue_nc.subtotal_nc) as subtotal, enc_nc.id_encabezado_nc as id_encabezado_nc,
				enc_nc.serie_nc as serie_nc, enc_nc.secuencial_nc as secuencial_nc, enc_nc.factura_modificada as factura_modificada,
				enc_nc.fecha_nc as fecha_nc, enc_nc.total_nc as total_nc, ven.nombre as vendedor, cli.ruc as ruc,
				tar.porcentaje_iva as porcentaje_iva, sum((cue_nc.subtotal_nc - cue_nc.descuento) * (tar.porcentaje_iva /100)) as total_iva,
				sum(cue_nc.descuento) as descuento 
				FROM cuerpo_nc as cue_nc 
				INNER JOIN encabezado_nc as enc_nc ON enc_nc.serie_nc=cue_nc.serie_nc and enc_nc.secuencial_nc=cue_nc.secuencial_nc 
				INNER JOIN clientes as cli ON cli.id=enc_nc.id_cliente 
				INNER JOIN tarifa_iva as tar ON tar.codigo=cue_nc.tarifa_iva
				LEFT JOIN productos_servicios as pro_ser ON pro_ser.id=cue_nc.id_producto 
				LEFT JOIN marca_producto as mar_pro ON mar_pro.id_producto=cue_nc.id_producto 
				LEFT JOIN grupo_producto_asignado as grupo_asi ON grupo_asi.id_producto=cue_nc.id_producto
				LEFT JOIN vendedores_ncv as ven_ncv ON ven_ncv.id_ncv= enc_nc.id_encabezado_nc 
				LEFT JOIN vendedores as ven ON ven.id_vendedor=ven_ncv.id_vendedor
				WHERE enc_nc.ruc_empresa='" . $ruc_empresa . "' and cue_nc.ruc_empresa='" . $ruc_empresa . "' 
				and DATE_FORMAT(enc_nc.fecha_nc, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
				and '" . date("Y/m/d", strtotime($hasta)) . "' $condicion_cliente $condicion_producto $condicion_marca $condicion_grupo $condicion_vendedor 
				group by cue_nc.serie_nc, cue_nc.secuencial_nc");
	return $resultado;
}


 function detalle_facturas($con, $ruc_empresa, $id_cliente, $id_producto, $id_marca, $id_grupo, $desde, $hasta)
{
	if (empty($id_cliente)) {
		$condicion_cliente = "";
	} else {
		$condicion_cliente = " and enc_fac.id_cliente=" . $id_cliente;
	}

	if (empty($id_producto)) {
		$condicion_producto = "";
	} else {
		$condicion_producto = " and cue_fac.id_producto=" . $id_producto;
	}

	if (empty($id_marca)) {
		$condicion_marca = "";
	} else {
		$condicion_marca = " and mar_pro.id_marca=" . $id_marca;
	}

	if (empty($id_grupo)) {
		$condicion_grupo = "";
	} else {
		$condicion_grupo = " and grupo_asi.id_grupo=" . $id_grupo;
	}
	$resultado = mysqli_query($con, "SELECT enc_fac.fecha_factura as fecha_factura, 
				cue_fac.serie_factura as serie_factura, cue_fac.secuencial_factura as secuencial_factura,
				cli.nombre as nombre_cliente, cli.ruc as ruc, cue_fac.cantidad_factura as cantidad_factura,
				cue_fac.nombre_producto as nombre_producto, cue_fac.codigo_producto as codigo_producto,
				cue_fac.valor_unitario_factura as valor_unitario_factura, 
				cue_fac.descuento as descuento, round((cue_fac.subtotal_factura - cue_fac.descuento),2)  as subtotal,
				tar_iva.tarifa as tarifa, tar_iva.porcentaje_iva as porcentaje_iva, 
				round((cue_fac.subtotal_factura * (tar_iva.porcentaje_iva /100)),2) as total_iva, if(pro_ser.tipo_produccion=1,'Producto','Servicio') as nombre_produccion 
				FROM cuerpo_factura as cue_fac 
				INNER JOIN encabezado_factura as enc_fac ON enc_fac.serie_factura=cue_fac.serie_factura and enc_fac.secuencial_factura=cue_fac.secuencial_factura 
				INNER JOIN clientes as cli ON cli.id=enc_fac.id_cliente
				LEFT JOIN productos_servicios as pro_ser ON pro_ser.id=cue_fac.id_producto 
				LEFT JOIN marca_producto as mar_pro ON mar_pro.id_producto=cue_fac.id_producto 
				LEFT JOIN grupo_producto_asignado as grupo_asi ON grupo_asi.id_producto=cue_fac.id_producto
				INNER JOIN tarifa_iva as tar_iva ON tar_iva.codigo=cue_fac.tarifa_iva 
				WHERE enc_fac.ruc_empresa='" . $ruc_empresa . "' and cue_fac.ruc_empresa='" . $ruc_empresa . "' 
				and DATE_FORMAT(enc_fac.fecha_factura, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "'
				 $condicion_cliente $condicion_producto $condicion_marca $condicion_grupo ");
	return $resultado;
} 




function detalle_nc($con, $ruc_empresa, $id_cliente, $id_producto, $id_marca, $id_grupo, $desde, $hasta)
{

	if (empty($id_cliente)) {
		$condicion_cliente = "";
	} else {
		$condicion_cliente = " and enc_nc.id_cliente=" . $id_cliente;
	}

	if (empty($id_producto)) {
		$condicion_producto = "";
	} else {
		$condicion_producto = " and cue_nc.id_producto=" . $id_producto;
	}

	if (empty($id_marca)) {
		$condicion_marca = "";
	} else {
		$condicion_marca = " and mar_pro.id_marca=" . $id_marca;
	}

	if (empty($id_grupo)) {
		$condicion_grupo = "";
	} else {
		$condicion_grupo = " and grupo_asi.id_grupo=" . $id_grupo;
	}
	$resultado = mysqli_query($con, "SELECT enc_nc.fecha_nc as fecha_nc, 
				cue_nc.serie_nc as serie_nc, cue_nc.secuencial_nc as secuencial_nc,
				cli.nombre as nombre_cliente, enc_nc.total_nc as total_nc, 
				cli.ruc as ruc, cue_nc.cantidad_nc as cantidad_nc,
				cue_nc.nombre_producto as nombre_producto, cue_nc.codigo_producto as codigo_producto,
				cue_nc.valor_unitario_nc as valor_unitario_nc, 
				cue_nc.descuento as descuento, cue_nc.subtotal_nc as subtotal_nc,
				tip_pro.nombre as nombre_produccion, tar_iva.tarifa as tarifa, tar_iva.porcentaje_iva as porcentaje_iva,
				enc_nc.factura_modificada as factura_modificada, enc_nc.motivo as motivo
				FROM cuerpo_nc as cue_nc 
				INNER JOIN encabezado_nc as enc_nc ON enc_nc.serie_nc=cue_nc.serie_nc and enc_nc.secuencial_nc=cue_nc.secuencial_nc 
				INNER JOIN clientes as cli ON cli.id=enc_nc.id_cliente 
				LEFT JOIN productos_servicios as pro_ser ON pro_ser.id=cue_nc.id_producto 
				LEFT JOIN marca_producto as mar_pro ON mar_pro.id_producto=cue_nc.id_producto
				LEFT JOIN grupo_producto_asignado as grupo_asi ON grupo_asi.id_producto=cue_nc.id_producto 
				INNER JOIN tipo_produccion as tip_pro ON tip_pro.codigo=cue_nc.tipo_produccion 
				INNER JOIN tarifa_iva as tar_iva ON tar_iva.codigo=cue_nc.tarifa_iva WHERE enc_nc.ruc_empresa='" . $ruc_empresa . "' 
				and cue_nc.ruc_empresa='" . $ruc_empresa . "' and DATE_FORMAT(enc_nc.fecha_nc, '%Y/%m/%d') 
				between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' 
				$condicion_cliente $condicion_producto $condicion_marca $condicion_grupo order by enc_nc.secuencial_nc desc");
	return $resultado;
}

function recibos($con, $ruc_empresa, $id_cliente, $id_producto, $id_marca, $id_grupo, $desde, $hasta)
{
	if (empty($id_cliente)) {
		$condicion_cliente = "";
	} else {
		$condicion_cliente = " and enc_rec.id_cliente=" . $id_cliente;
	}

	if (empty($id_producto)) {
		$condicion_producto = "";
	} else {
		$condicion_producto = " and cue_rec.id_producto=" . $id_producto;
	}

	if (empty($id_marca)) {
		$condicion_marca = "";
		$condicion_marca_tarifa = "";
	} else {
		$condicion_marca = " and mar_pro.id_marca=" . $id_marca;
		$condicion_marca_tarifa = " and mar.id_marca=" . $id_marca;
	}

	if (empty($id_grupo)) {
		$condicion_grupo = "";
		$condicion_grupo_tarifa = "";
	} else {
		$condicion_grupo = " and grupo_asi.id_grupo=" . $id_grupo;
		$condicion_grupo_tarifa = " and grupo.id_grupo=" . $id_grupo;
	}

	if (empty($vendedor)) {
		$condicion_vendedor = "";
	} else {
		$condicion_vendedor = " and ven_rec.id_vendedor=" . $vendedor;
	}
	$resultado = mysqli_query($con, "SELECT ven.nombre as vendedor, cli.nombre as nombre, cli.ruc as ruc, 
					enc_rec.serie_recibo as serie_recibo, enc_rec.secuencial_recibo as secuencial_recibo, sum(enc_rec.total_recibo + enc_rec.propina + enc_rec.tasa_turistica) as total_recibo, 
					usu.nombre as nombre_usuario, enc_rec.fecha_recibo as fecha_recibo
					FROM encabezado_recibo as enc_rec INNER JOIN clientes as cli ON cli.id=enc_rec.id_cliente 
					LEFT JOIN usuarios as usu ON usu.id=enc_rec.id_usuario
					LEFT JOIN vendedores_recibos as ven_rec ON ven_rec.id_recibo= enc_rec.id_encabezado_recibo 
					LEFT JOIN vendedores as ven ON ven.id_vendedor=ven_rec.id_vendedor
					WHERE enc_rec.ruc_empresa='" . $ruc_empresa . "' and DATE_FORMAT(enc_rec.fecha_recibo, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
					and '" . date("Y/m/d", strtotime($hasta)) . "' $condicion_cliente $condicion_vendedor
					and enc_rec.status != '2' group by enc_rec.serie_recibo, enc_rec.secuencial_recibo");
	return $resultado;
	//$condicion_cliente $condicion_vendedor $condicion_marca $condicion_marca_tarifa $condicion_grupo $condicion_grupo_tarifa
}

function detalle_recibos($con, $ruc_empresa, $id_cliente, $id_producto, $id_marca, $id_grupo, $desde, $hasta)
{
	if (empty($id_cliente)) {
		$condicion_cliente = "";
	} else {
		$condicion_cliente = " and enc_rec.id_cliente=" . $id_cliente;
	}

	if (empty($id_producto)) {
		$condicion_producto = "";
	} else {
		$condicion_producto = " and cue_rec.id_producto=" . $id_producto;
	}

	if (empty($id_marca)) {
		$condicion_marca = "";
		//$condicion_marca_tarifa = "";
	} else {
		$condicion_marca = " and mar_pro.id_marca=" . $id_marca;
		//$condicion_marca_tarifa = " and mar.id_marca=" . $id_marca;
	}

	if (empty($id_grupo)) {
		$condicion_grupo = "";
		//$condicion_grupo_tarifa = "";
	} else {
		$condicion_grupo = " and grupo_asi.id_grupo=" . $id_grupo;
		//$condicion_grupo_tarifa = " and grupo.id_grupo=" . $id_grupo;
	}
	$resultado = mysqli_query($con, "SELECT enc_rec.fecha_recibo as fecha_recibo, 
	cli.nombre as nombre, concat(enc_rec.serie_recibo,'-',enc_rec.secuencial_recibo) as recibo, 
	cue_rec.codigo_producto as codigo, cue_rec.nombre_producto as detalle, 
	tar.tarifa as tarifa, cue_rec.cantidad as cantidad, cue_rec.valor_unitario as precio, cue_rec.subtotal as subtotal,
	tar.porcentaje_iva/100 as porcentaje_iva
	FROM cuerpo_recibo as cue_rec INNER JOIN encabezado_recibo as enc_rec
	ON enc_rec.id_encabezado_recibo=cue_rec.id_encabezado_recibo
	 INNER JOIN clientes as cli ON cli.id=enc_rec.id_cliente 
	 INNER JOIN tarifa_iva as tar ON tar.codigo=cue_rec.tarifa_iva
	 LEFT JOIN marca_producto as mar_pro ON mar_pro.id_producto=cue_rec.id_producto
	LEFT JOIN grupo_producto_asignado as grupo_asi ON grupo_asi.id_producto=cue_rec.id_producto 
	WHERE enc_rec.ruc_empresa='" . $ruc_empresa . "' and DATE_FORMAT(enc_rec.fecha_recibo, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
	and '" . date("Y/m/d", strtotime($hasta)) . "' $condicion_cliente $condicion_producto $condicion_marca $condicion_grupo 
	and enc_rec.status != '2'");
	return $resultado;
}
?>