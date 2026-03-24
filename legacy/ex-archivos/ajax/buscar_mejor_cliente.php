<?php
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
include("../conexiones/conectalogin.php");
$con = conenta_login();
$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';

	if($action == 'mejor_cliente'){
		 $desde = $_REQUEST['desde'];
		 $hasta = $_REQUEST['hasta'];
		 $cantidad = mysqli_real_escape_string($con,(strip_tags($_REQUEST['cantidad'], ENT_QUOTES)));
		 $vendedor = mysqli_real_escape_string($con,(strip_tags($_REQUEST['vendedor'], ENT_QUOTES)));
		 $mejor_cliente = mejor_cliente($con, $ruc_empresa, $desde, $hasta, $vendedor, $cantidad);
		
	if ($mejor_cliente->num_rows>0){
		
			?>
			<div class="panel panel-info">
			<div class="table-responsive">
			  <table class="table table-hover">
				<tr  class="info">
					<th>Cliente</th>
					<th>Asesor</th>
					<th class="text-right">Subtotal</th>
					<th class="text-right">NC</th>
					<th class="text-right">Total</th>					
				</tr>
				<?php
				while ($row=mysqli_fetch_array($mejor_cliente)){
						$total_factura=$row['total_factura'];
						$total_nc=$row['total_nc'];
						$cliente=$row['cliente'];
						$vendedor=$row['vendedor'];
						$total_neto = $total_factura - $total_nc;
					?>
					<tr>
						<td><?php echo $cliente; ?></td>
						<td><?php echo $vendedor; ?></td>					
						<td class="text-right"><?php echo number_format($total_factura, 2, '.', ''); ?></td>
						<td class="text-right"><?php echo number_format($total_nc, 2, '.', ''); ?></td>
						<td class="text-right"><?php echo number_format($total_neto, 2, '.', ''); ?></td>
					</tr>
					<?php
				}
				?>
			  </table>
			</div>
			</div>
			<?php
	}else{
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
	}
	

function mejor_cliente($con, $ruc_empresa, $desde, $hasta, $vendedor, $cantidad){
	if($vendedor =='0'){
		$condicion_vendedor="";
	 }else{
		$condicion_vendedor=" and ven_ven.id_vendedor=".$vendedor;
	 }
	$buscar_mas_vendidos = mysqli_query($con, "SELECT ven.nombre as vendedor, cli.nombre as cliente, 
	round(sum(encfac.total_factura),2) as total_factura, 
	(select round(sum(nc.total_nc),2) from encabezado_nc as nc 
	where nc.factura_modificada = concat(encfac.serie_factura, '-', lpad(encfac.secuencial_factura,9,'0')) 
	and nc.ruc_empresa='".$ruc_empresa."' and DATE_FORMAT(nc.fecha_nc, '%Y/%m/%d') 
	between '" . date("Y/m/d", strtotime($desde)) . "' 
	and '" . date("Y/m/d", strtotime($hasta)) . "' group by nc.factura_modificada ) as total_nc  
	FROM encabezado_factura as encfac 
	INNER JOIN clientes as cli ON cli.id = encfac.id_cliente
	LEFT JOIN vendedores_ventas AS ven_ven ON ven_ven.id_venta=encfac.id_encabezado_factura
	LEFT JOIN vendedores as ven ON ven.id_vendedor=ven_ven.id_vendedor
	WHERE encfac.ruc_empresa='".$ruc_empresa."' and DATE_FORMAT(encfac.fecha_factura, '%Y/%m/%d') 
	between '" . date("Y/m/d", strtotime($desde)) . "' 
	and '" . date("Y/m/d", strtotime($hasta)) . "' $condicion_vendedor
	group by encfac.id_cliente order by sum(encfac.total_factura) desc LIMIT 0, $cantidad");
	return $buscar_mas_vendidos;
}

?>