<?php
/* Connect To Database*/
include("../conexiones/conectalogin.php");
//include("../helpers/helpers.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];

//PARA BUSCAR LAS FACTURAS de ventas	
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
$id_cliente = $_POST['id_cliente'];
$id_producto = $_POST['id_producto'];
$desde = $_POST['desde'];
$hasta = $_POST['hasta'];
$id_marca = $_POST['id_marca'];
$id_vendedor = $_POST['id_vendedor'];

ini_set('date.timezone', 'America/Guayaquil');

if ($action == 'reporte_ventas_asesor') {
	$resultado_ventas = reporte_ventas_asesor($con, $id_cliente, $id_producto, $id_marca, $id_vendedor, $ruc_empresa, $desde, $hasta);
	if ($resultado_ventas->num_rows > 0) {
?>
		<div class="panel panel-info">
			<div class="table-responsive">
				<table class="table table-hover">
					<tr class="info">

						<th class="text-left">Asesor</th>
						<th class="text-right">Total ventas brutas</th>
						<th class="text-right">Total NC</th>
						<th class="text-right">Total ventas netas</th>
					</tr>
					<?php
					$suma_ventas = 0;
					$suma_nc = 0;
					$suma_total = 0;
					while ($row_ventas = mysqli_fetch_array($resultado_ventas)) {
						$subtotal_ventas = $row_ventas['subtotal_ventas'];
						$suma_ventas += $subtotal_ventas;
						$asesor = $row_ventas['asesor'];
						$subtotal_nc = $row_ventas['subtotal_nc'];
						$suma_nc += $subtotal_nc;
						$ventas_netas = $subtotal_ventas - $subtotal_nc;
						$suma_total += $ventas_netas;
					?>
						<tr>
							<td class="text-left"><?php echo $asesor; ?></td>
							<td class="text-right"><?php echo number_format($subtotal_ventas, 2, '.', ','); ?></td>
							<td class="text-right"><?php echo number_format($subtotal_nc, 2, '.', ','); ?></td>
							<td class="text-right"><?php echo number_format($ventas_netas, 2, '.', ','); ?></td>
						</tr>
					<?php
					}
					?>
					<tr class="info">
						<td class="text-left">SUMAS</td>
						<td class="text-right"><?php echo number_format($suma_ventas, 2, '.', ','); ?></td>
						<td class="text-right"><?php echo number_format($suma_nc, 2, '.', ','); ?></td>
						<td class="text-right"><?php echo number_format($suma_total, 2, '.', ','); ?></td>
					</tr>
				</table>
			</div>
		</div>
	<?php
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
	}
}


function reporte_ventas_asesor($con, $id_cliente, $id_producto, $id_marca, $id_vendedor, $ruc_empresa, $desde, $hasta)
{
	if (empty($id_cliente)) {
		$condicion_cliente_fac = "";
		$condicion_cliente_nc = "";
	} else {
		$condicion_cliente_fac = " and enc_fac.id_cliente=" . $id_cliente;
		$condicion_cliente_nc = " and enc_nc.id_cliente=" . $id_cliente;
	}

	if (empty($id_producto)) {
		$condicion_producto_fac = "";
		$condicion_producto_nc = "";
	} else {
		$condicion_producto_fac = " and cue_fac.id_producto=" . $id_producto;
		$condicion_producto_nc = " and cue_nc.id_producto=" . $id_producto;
	}

	if (empty($id_marca)) {
		$condicion_marca = "";
		//$condicion_marca_tarifa = "";
	} else {
		$condicion_marca = " and mar_pro.id_marca=" . $id_marca;
		//$condicion_marca_tarifa = " and mar.id_marca=" . $id_marca;
	}

	if (empty($id_vendedor)) {
		$condicion_vendedor = "";
	} else {
		$condicion_vendedor = " and ven_ven.id_vendedor=" . $id_vendedor;
	}

	$resultado_ventas = mysqli_query($con, "SELECT round(sum(cue_fac.subtotal_factura - cue_fac.descuento),2) as subtotal_ventas, 
				ifnull(ven.nombre,'SIN ASESOR ASIGNADO EN FACTURAS') as asesor, (SELECT round(sum(cue_nc.subtotal_nc),2) FROM cuerpo_nc as cue_nc 
				 INNER JOIN encabezado_nc as enc_nc ON enc_nc.serie_nc=cue_nc.serie_nc and enc_nc.secuencial_nc=cue_nc.secuencial_nc and enc_nc.ruc_empresa=cue_nc.ruc_empresa 
				LEFT JOIN vendedores_ncv as ven_ncv ON ven_ncv.id_ncv = enc_nc.id_encabezado_nc
				 WHERE enc_nc.ruc_empresa='" . $ruc_empresa . "' and DATE_FORMAT(enc_nc.fecha_nc, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
			    and '" . date("Y/m/d", strtotime($hasta)) . "' $condicion_cliente_nc $condicion_producto_nc and ven_ncv.id_vendedor= ven.id_vendedor) as subtotal_nc 
				 FROM cuerpo_factura as cue_fac INNER JOIN encabezado_factura as enc_fac ON enc_fac.serie_factura=cue_fac.serie_factura and enc_fac.secuencial_factura=cue_fac.secuencial_factura 
				LEFT JOIN marca_producto as mar_pro ON mar_pro.id_producto=cue_fac.id_producto 
				LEFT JOIN vendedores_ventas as ven_ven ON ven_ven.id_venta = enc_fac.id_encabezado_factura 
				LEFT JOIN vendedores as ven ON ven.id_vendedor=ven_ven.id_vendedor
				WHERE enc_fac.ruc_empresa='" . $ruc_empresa . "' and cue_fac.ruc_empresa='" . $ruc_empresa . "' 
				and DATE_FORMAT(enc_fac.fecha_factura, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
				and '" . date("Y/m/d", strtotime($hasta)) . "' $condicion_cliente_fac $condicion_producto_fac $condicion_marca $condicion_vendedor group by ven.id_vendedor order by ven.nombre asc");

	return $resultado_ventas;
}
?>