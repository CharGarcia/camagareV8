<?php
/* Connect To Database*/
include("../conexiones/conectalogin.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];

//PARA BUSCAR LAS FACTURAS de ventas	
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
$tipo_reporte = $_POST['action'];
//$id_cliente=$_POST['id_cliente'];
//$id_producto=$_POST['id_producto'];
$desde = $_POST['desde'];
$hasta = $_POST['hasta'];
//$id_marca=$_POST['id_marca'];
ini_set('date.timezone', 'America/Guayaquil');

if ($action == '1') {
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
					<th>Usuario</th>
				</tr>
				<?php
				$suma_factura = 0;
				$suma_subtotal = 0;
				$suma_base_descuento = 0;
				$suma_propina = 0;
				$suma_tasa_turistica = 0;
				$suma_iva = 0;
				$n = 0;

				$resultado = mysqli_query($con, "SELECT  cli.nombre as cliente, cli.ruc as ruc, cli.id as id_cliente,
				enc_fac.serie_factura as serie_factura, enc_fac.secuencial_factura as secuencial_factura, sum(cue_fac.subtotal_factura) as subtotal,
					sum(cue_fac.descuento) as descuento, tar.porcentaje_iva as porcentaje_iva, sum((cue_fac.subtotal_factura - cue_fac.descuento) * (tar.porcentaje_iva /100)) as total_iva,
					usu.nombre as usuario, enc_fac.propina as propina, enc_fac.tasa_turistica as tasa_turistica, enc_fac.total_factura as total_factura, enc_fac.fecha_factura as fecha_factura
					FROM cuerpo_factura as cue_fac INNER JOIN encabezado_factura as enc_fac ON enc_fac.serie_factura=cue_fac.serie_factura and enc_fac.secuencial_factura=cue_fac.secuencial_factura 
					INNER JOIN clientes as cli ON cli.id=enc_fac.id_cliente
					INNER JOIN tarifa_iva as tar ON tar.codigo=cue_fac.tarifa_iva 
					LEFT JOIN productos_servicios as pro_ser ON pro_ser.id=cue_fac.id_producto  
					LEFT JOIN usuarios as usu ON usu.id=enc_fac.id_usuario
					WHERE mid(enc_fac.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and 
				mid(cue_fac.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' 
					and DATE_FORMAT(enc_fac.fecha_factura, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
					and '" . date("Y/m/d", strtotime($hasta)) . "' group by cue_fac.serie_factura, cue_fac.secuencial_factura");

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
						<td><?php echo $row['usuario']; ?></td>
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

// para buscar las nc 
if ($action == '2') {
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
				</tr>
				<?php
				$suma_nc = 0;
				$suma_subtotal = 0;
				$suma_descuento = 0;
				$suma_iva = 0;
				$n = 0;

				$resultado = mysqli_query($con, "SELECT cli.nombre as cliente, sum(cue_nc.subtotal_nc) as subtotal, enc_nc.id_encabezado_nc as id_encabezado_nc,
				enc_nc.serie_nc as serie_nc, enc_nc.secuencial_nc as secuencial_nc, enc_nc.factura_modificada as factura_modificada,
				enc_nc.fecha_nc as fecha_nc, enc_nc.total_nc as total_nc, cli.ruc as ruc,
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
				WHERE mid(enc_nc.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and mid(cue_nc.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' 
				and DATE_FORMAT(enc_nc.fecha_nc, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
				and '" . date("Y/m/d", strtotime($hasta)) . "' group by cue_nc.serie_nc, cue_nc.secuencial_nc");

				while ($row = mysqli_fetch_array($resultado)) {
					$suma_nc += $row['total_nc'];
					$id_encabezado_nc = $row['id_encabezado_nc'];
					$fecha_nc = $row['fecha_nc'];
					$serie_nc = $row['serie_nc'];
					$secuencial_nc = $row['secuencial_nc'];
					$nombre_cliente_nc = $row['cliente'];
					$factura_afectada = $row['factura_modificada'];
					$total_nc = $row['total_nc'];
					$ruc_cliente = $row['ruc'];
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
				</tr>
			</table>
		</div>
	</div>
<?php
}
?>