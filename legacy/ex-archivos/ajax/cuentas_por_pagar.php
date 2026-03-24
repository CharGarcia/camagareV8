<?PHP
//include("../conexiones/conectalogin.php");
include("../clases/egresos.php");
$genera_saldos = new egresos();
$con = conenta_login();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
if ($action == 'cuentas_por_pagar') {
	$desde = "2018/01/01";
	$hasta = $_GET['hasta'];
	echo $genera_saldos->saldos_por_pagar($con, $desde, $hasta);
}



//para generar el informe de cuentas por pagar
if ($action == 'generar_informe') {
	ini_set('date.timezone', 'America/Guayaquil');
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$id_usuario = $_SESSION['id_usuario'];
	$id_proveedor = $_POST['id_proveedor'];
	$desde = "2018/01/01";
	$hasta = $_POST['hasta'];
	$con = conenta_login();
	$fecha_hoy = date_create(date("Y-m-d H:i:s"));

	if (empty($id_proveedor)) { //para todos los proveedores
		echo $genera_saldos->saldos_por_pagar($con, $desde, $hasta);

		//$busca_proveedores=mysqli_query($con, "SELECT * FROM proveedores WHERE ruc_empresa = '".$ruc_empresa."' order by razon_social asc");
		$busca_proveedores = mysqli_query($con, "SELECT DISTINCT sal.id_proveedor as id_proveedor, sal.razon_social as razon_social FROM saldos_compras_tmp as sal WHERE sal.ruc_empresa = '" . $ruc_empresa . "' order by sal.razon_social asc");

		$busca_saldos_total = mysqli_query($con, "SELECT sum(total_compra - (total_egresos + total_retencion + total_egresos_tmp)) as saldo_general FROM saldos_compras_tmp WHERE mid(ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and fecha_compra between '" . date("Y-m-d", strtotime($desde)) . "' and '" . date("Y-m-d", strtotime($hasta)) . "' and id_comprobante !=4");
		$row_saldo_total = mysqli_fetch_array($busca_saldos_total);
		$suma_general = $row_saldo_total['saldo_general'];

		$busca_saldos_total_nc = mysqli_query($con, "SELECT sum(total_compra + (total_egresos + total_retencion + total_egresos_tmp)) as saldo_general FROM saldos_compras_tmp WHERE mid(ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and fecha_compra between '" . date("Y-m-d", strtotime($desde)) . "' and '" . date("Y-m-d", strtotime($hasta)) . "' and id_comprobante=4 ");
		$row_saldo_total_nc = mysqli_fetch_array($busca_saldos_total_nc);
		$suma_general_nc = $row_saldo_total_nc['saldo_general'];

		//para todos los proveedores
?>
		<div style="padding: 1px; margin-bottom: 5px;" class="alert alert-success text-center" role="alert">
			Saldo total al <?php echo date("d-m-Y", strtotime($hasta)); ?>: <b><?php echo number_format($suma_general - $suma_general_nc, 2, '.', ''); ?> </b>
		</div>

		<div class="panel-group" id="accordiones">
			<?php
			while ($row_proveedor = mysqli_fetch_array($busca_proveedores)) {
				$ide_proveedor = $row_proveedor['id_proveedor'];
				$nombre_proveedor = $row_proveedor['razon_social'];
				$sql_suma_proveedor = mysqli_query($con, "SELECT sum(total_compra - (total_egresos + total_retencion + total_egresos_tmp)) as total_proveedor FROM saldos_compras_tmp WHERE id_proveedor = '" . $ide_proveedor . "' and mid(ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and id_comprobante !=4 ");
				$row_total_proveedor = mysqli_fetch_array($sql_suma_proveedor);
				$total_proveedor = $row_total_proveedor['total_proveedor'];

				$sql_suma_proveedor_nc = mysqli_query($con, "SELECT sum(total_compra + (total_egresos + total_retencion + total_egresos_tmp)) as total_proveedor FROM saldos_compras_tmp WHERE id_proveedor = '" . $ide_proveedor . "' and mid(ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and id_comprobante = 4 ");
				$row_total_proveedor_nc = mysqli_fetch_array($sql_suma_proveedor_nc);
				$total_proveedor_nc = $row_total_proveedor_nc['total_proveedor'];

				if (($total_proveedor - $total_proveedor_nc) != 0) {
			?>
					<div class="panel panel-info">
						<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordiones" href="#<?php echo $ide_proveedor; ?>"><span class="caret"></span> <b>Proveedor:</b> <?php echo $nombre_proveedor; ?> <b>Saldo:</b> <?php echo number_format($total_proveedor - $total_proveedor_nc, 2, '.', ''); ?></a>
						<div id="<?php echo $ide_proveedor; ?>" class="panel-collapse collapse">
							<div class="table-responsive">
								<table class="table table-hover">
									<tr class="info">
										<th style="padding: 2px;">Fecha</th>
										<th style="padding: 2px;">Documento</th>
										<th style="padding: 2px;">Número</th>
										<th style="padding: 2px;" class="text-right">Total</th>
										<th style="padding: 2px;" class="text-right">Abonos</th>
										<th style="padding: 2px;" class="text-right">Retenciones</th>
										<th style="padding: 2px;" class="text-right">Saldo</th>
										<th style="padding: 2px;" class="text-right">Días</th>
									</tr>
									<?php
									$busca_saldos_general = mysqli_query($con, "SELECT * FROM saldos_compras_tmp as sal_tmp INNER JOIN comprobantes_autorizados as doc_aut ON sal_tmp.id_comprobante=doc_aut.id_comprobante WHERE mid(sal_tmp.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and sal_tmp.fecha_compra between '" . date("Y-m-d", strtotime($desde)) . "' and '" . date("Y-m-d", strtotime($hasta)) . "' and sal_tmp.id_proveedor='" . $ide_proveedor . "' ORDER BY sal_tmp.razon_social asc, sal_tmp.fecha_compra asc ");
									while ($detalle = mysqli_fetch_array($busca_saldos_general)) {
										$fecha_documento = $detalle['fecha_compra'];
										$nombre_documento = $detalle['comprobante'];
										$id_comprobante = $detalle['id_comprobante'];
										$numero_documento = $detalle['numero_documento'];
										$total_compra = $detalle['total_compra'];
										$total_abonos = $detalle['total_egresos'];
										$total_retenciones = $detalle['total_retencion'];

										if ($id_comprobante == 4) {
											$saldo = ($detalle['total_compra'] + ($detalle['total_egresos'] + $detalle['total_retencion'] + $detalle['total_egresos_tmp'])) * -1;
										} else {
											$saldo = $detalle['total_compra'] - ($detalle['total_egresos'] + $detalle['total_retencion'] + $detalle['total_egresos_tmp']);
										}

										$fecha_vencimiento = date_create($fecha_documento);
										$diferencia_dias = date_diff($fecha_hoy, $fecha_vencimiento);
										$total_dias = $diferencia_dias->format('%a');
										if (($saldo) != 0) {
									?>
											<tr>
												<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha_documento)); ?></td>
												<td style="padding: 2px;"><?php echo $nombre_documento; ?></td>
												<td style="padding: 2px;"><?php echo $numero_documento; ?></td>
												<td style="padding: 2px;" class="text-right"><?php echo number_format($total_compra, 2, '.', ''); ?></td>
												<td style="padding: 2px;" class="text-right"><?php echo number_format($total_abonos, 2, '.', ''); ?></td>
												<td style="padding: 2px;" class="text-right"><?php echo number_format($total_retenciones, 2, '.', ''); ?></td>
												<td style="padding: 2px;" class="text-right"><?php echo number_format($saldo, 2, '.', ''); ?></td>
												<td style="padding: 2px;" class="text-right"><?php echo $total_dias; ?></td>
											</tr>
									<?php
										}
									}
									?>
								</table>
							</div>
						</div>
					</div>
			<?php
				}
			}
			?>
		</div>
	<?php

	}

	if (!empty($id_proveedor)) { //si esta lleno proveedor
		echo $genera_saldos->saldos_por_pagar($con, $desde, $hasta);
		$busca_saldos_general = mysqli_query($con, "SELECT * FROM saldos_compras_tmp as sal_tmp INNER JOIN comprobantes_autorizados as doc_aut ON sal_tmp.id_comprobante=doc_aut.id_comprobante WHERE mid(sal_tmp.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and sal_tmp.fecha_compra between '" . date("Y-m-d", strtotime($desde)) . "' and '" . date("Y-m-d", strtotime($hasta)) . "' and sal_tmp.id_proveedor='" . $id_proveedor . "' ORDER BY sal_tmp.razon_social asc, sal_tmp.fecha_compra asc ");
	?>
		<div class="panel panel-info">
			<div class="table-responsive">
				<table class="table table-hover">
					<tr class="info">
						<th style="padding: 2px;">Fecha</th>
						<th style="padding: 2px;">Documento</th>
						<th style="padding: 2px;">Número</th>
						<th style="padding: 2px;" class="text-right">Total</th>
						<th style="padding: 2px;" class="text-right">Abonos</th>
						<th style="padding: 2px;" class="text-right">Retenciones</th>
						<th style="padding: 2px;" class="text-right">Saldo</th>
						<th style="padding: 2px;" class="text-right">Días</th>
					</tr>
					<?php
					$total_saldo = 0;
					while ($detalle = mysqli_fetch_array($busca_saldos_general)) {
						$fecha_documento = $detalle['fecha_compra'];
						$nombre_documento = $detalle['comprobante'];
						$id_comprobante = $detalle['id_comprobante'];
						$numero_documento = $detalle['numero_documento'];
						$total_compra = $detalle['total_compra'];
						$total_abonos = $detalle['total_egresos'];
						$total_retenciones = $detalle['total_retencion'];

						$saldo = $detalle['total_compra'] - ($detalle['total_egresos'] + $detalle['total_retencion'] + $detalle['total_egresos_tmp']);
						if ($id_comprobante == 4) {
							$saldo = ($detalle['total_compra'] + ($detalle['total_egresos'] + $detalle['total_retencion'] + $detalle['total_egresos_tmp'])) * -1;
						} else {
							$saldo = $detalle['total_compra'] - ($detalle['total_egresos'] + $detalle['total_retencion'] + $detalle['total_egresos_tmp']);
						}
						$total_saldo += $saldo;
						$fecha_vencimiento = date_create($fecha_documento);
						$diferencia_dias = date_diff($fecha_hoy, $fecha_vencimiento);
						$total_dias = $diferencia_dias->format('%a');

						if (($saldo) != 0) {
					?>
							<tr>
								<td style="padding: 2px;"><?php echo date("d/m/Y", strtotime($fecha_documento)); ?></td>
								<td style="padding: 2px;"><?php echo $nombre_documento; ?></td>
								<td style="padding: 2px;"><?php echo $numero_documento; ?></td>
								<td style="padding: 2px;" class="text-right"><?php echo number_format($total_compra, 2, '.', ''); ?></td>
								<td style="padding: 2px;" class="text-right"><?php echo number_format($total_abonos, 2, '.', ''); ?></td>
								<td style="padding: 2px;" class="text-right"><?php echo number_format($total_retenciones, 2, '.', ''); ?></td>
								<td style="padding: 2px;" class="text-right"><?php echo number_format($saldo, 2, '.', ''); ?></td>
								<td style="padding: 2px;" class="text-right"><?php echo $total_dias; ?></td>
							</tr>
					<?php
						}
					}
					?>
					<tr class="info">
						<th style="padding: 2px;" colspan="5"></th>
						<th style="padding: 2px;">Total por pagar</th>
						<th style="padding: 2px;" class="text-right"><?php echo number_format($total_saldo, 2, '.', ''); ?></th>
						<th style="padding: 2px;"></th>
					</tr>
				</table>
			</div>
		</div>
<?php
	}
}

?>