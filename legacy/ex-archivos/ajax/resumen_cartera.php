<?PHP
include("../conexiones/conectalogin.php");
session_start();
$con = conenta_login();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
if ($action == 'ventas_por_cobrar') {
	$desde = $_GET['desde'];
	$hasta = $_GET['hasta'];
	$id_cliente = $_GET['id_cliente'];
	$vendedor = ""; //$_GET['vendedor'];
	resumen_por_cobrar($desde, $hasta, $id_cliente, $vendedor);
}

if ($action == 'resumen_cartera') {
	$id_documento = $_GET['id_documento'];
	resumen_documento($con, $id_documento);
}


//para sacar informes de todos los clientes
function resumen_por_cobrar($desde, $hasta, $id_cliente, $vendedor)
{
	$con = conenta_login();
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$id_usuario = $_SESSION['id_usuario'];

	if (!empty($id_cliente)) {
		$condicion_cliente = " and enc_fac.id_cliente=" . $id_cliente;
	} else {
		$condicion_cliente = "";
	}

	$limpiar_saldos = mysqli_query($con, "DELETE FROM saldo_porcobrar_porpagar WHERE id_usuario='" . $id_usuario . "' and mid(ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and tipo='POR_COBRAR'");
	$query_por_cobrar = mysqli_query($con, "INSERT INTO saldo_porcobrar_porpagar (id_saldo, tipo, fecha_documento, id_cli_pro, nombre_cli_pro, numero_documento, id_usuario, ruc_empresa, total_factura, total_nc, total_ing, ing_tmp, total_ret, id_documento)
	SELECT null, 'POR_COBRAR', enc_fac.fecha_factura, enc_fac.id_cliente, (select nombre from clientes as cli where cli.id=enc_fac.id_cliente), 
	concat(enc_fac.serie_factura,'-', LPAD(enc_fac.secuencial_factura,9,'0')),'" . $id_usuario . "', '" . $ruc_empresa . "', enc_fac.total_factura, 
	(select sum(total_nc) from encabezado_nc as nc where nc.factura_modificada = concat(enc_fac.serie_factura,'-',LPAD(enc_fac.secuencial_factura,9,'0')) and mid(enc_fac.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and mid(nc.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and nc.fecha_nc between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "'),
	0,0,0, enc_fac.id_encabezado_factura FROM encabezado_factura as enc_fac WHERE mid(enc_fac.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and enc_fac.estado_sri = 'AUTORIZADO' and DATE_FORMAT(enc_fac.fecha_factura, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' $condicion_cliente ");

	$update_ingresos = mysqli_query($con, "UPDATE saldo_porcobrar_porpagar as sal_ing, (SELECT detie.codigo_documento_cv as id_documento_venta, sum(detie.valor_ing_egr) as suma_ingresos FROM detalle_ingresos_egresos as detie INNER JOIN ingresos_egresos as ing_egr ON ing_egr.codigo_documento=detie.codigo_documento WHERE detie.estado ='OK' and ing_egr.tipo_ing_egr='INGRESO' and DATE_FORMAT(ing_egr.fecha_ing_egr, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' group by detie.codigo_documento_cv) as total_ingresos SET sal_ing.total_ing = total_ingresos.suma_ingresos WHERE sal_ing.id_documento=total_ingresos.id_documento_venta and mid(sal_ing.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' ");
	$query_actualizar_retencion = mysqli_query($con, "UPDATE saldo_porcobrar_porpagar as sal_ret, (SELECT det_ret.numero_documento as codigo_registro, sum(det_ret.valor_retenido) as suma_retencion FROM cuerpo_retencion_venta as det_ret INNER JOIN encabezado_retencion_venta as enc_ret ON enc_ret.codigo_unico=det_ret.codigo_unico WHERE mid(enc_ret.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and DATE_FORMAT(enc_ret.fecha_emision, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' group by det_ret.numero_documento) as total_retencion SET sal_ret.total_ret = total_retencion.suma_retencion WHERE replace(sal_ret.numero_documento,'-','')=total_retencion.codigo_registro ");
}


if ($action == 'generar_informe') {
	ini_set('date.timezone', 'America/Guayaquil');
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$id_usuario = $_SESSION['id_usuario'];
	$id_cliente = $_POST['id_cliente'];
	$desde = $_POST['desde'];
	$hasta = $_POST['hasta'];
	$vendedor = ""; //$_POST['vendedor'];
	$con = conenta_login();
	$fecha_hoy = date_create(date("Y-m-d H:i:s"));

	//reporte de todos los clientes de facturas
	if (empty($id_cliente)) {
		$busca_saldos_general = resumen_por_cobrar($desde, $hasta, $id_cliente, $vendedor);
		$busca_clientes = mysqli_query($con, "SELECT DISTINCT id_cli_pro as id, nombre_cli_pro as nombre 
		FROM saldo_porcobrar_porpagar WHERE ruc_empresa = '" . $ruc_empresa . "' order by nombre_cli_pro asc");
		$busca_saldos_total = mysqli_query($con, "SELECT sum(total_factura - (total_nc + total_ing  + ing_tmp + total_ret)) as saldo_general FROM saldo_porcobrar_porpagar WHERE id_usuario = '" . $id_usuario . "' and ruc_empresa='" . $ruc_empresa . "' and DATE_FORMAT(fecha_documento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' ");
		$row_saldo_total = mysqli_fetch_array($busca_saldos_total);
		$suma_general = $row_saldo_total['saldo_general'];

		//para contar los clientes que si tienen ventas en ese periodo
		$contar_clientes = mysqli_query($con, "SELECT COUNT(DISTINCT id_cli_pro) as total 
    FROM saldo_porcobrar_porpagar WHERE ruc_empresa = '" . $ruc_empresa . "'");
		$row = mysqli_fetch_assoc($contar_clientes);
		$total_clientes = $row['total'];


		$busca_saldos_total = mysqli_query($con, "SELECT sum(total_factura - (total_nc + total_ing  + ing_tmp + total_ret)) as saldo_general FROM saldo_porcobrar_porpagar WHERE id_usuario = '" . $id_usuario . "' and ruc_empresa='" . $ruc_empresa . "' and DATE_FORMAT(fecha_documento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' ");
		$row_saldo_total = mysqli_fetch_array($busca_saldos_total);
		$suma_general = $row_saldo_total['saldo_general'];

		if ($total_clientes > 0) {

			//para todos los clientes
?>
			<li style="margin-bottom: 10px; margin-top: -10px; text-align:center; height: 50px;" class="list-group-item list-group-item-success">
				<h4><b>Saldo general por facturas de venta: </b><?php echo number_format($suma_general, 2, '.', ''); ?> Desde: <?php echo date("d-m-Y", strtotime($desde)); ?> Hasta: <?php echo date("d-m-Y", strtotime($hasta)); ?></h4>
			</li>
			<div class="panel-group" id="accordiones">
				<?php
				while ($row_clientes = mysqli_fetch_array($busca_clientes)) {
					$ide_cliente = $row_clientes['id'];
					$nombre_cliente = $row_clientes['nombre'];
					$sql_suma_cliente = mysqli_query($con, "SELECT sum(total_factura - (total_nc + total_ing  + ing_tmp + total_ret)) as total_cliente FROM saldo_porcobrar_porpagar WHERE id_cli_pro = '" . $ide_cliente . "' and id_usuario='" . $id_usuario . "' and ruc_empresa='" . $ruc_empresa . "'");
					$row_total_cliente = mysqli_fetch_array($sql_suma_cliente);
					$total_cliente = $row_total_cliente['total_cliente'];
					//if ($total_cliente > 0) {
				?>
					<div class="panel panel-info">
						<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordiones" href="#<?php echo $ide_cliente; ?>"><span class="caret"></span> <?php echo $nombre_cliente; ?> <b>Saldo:</b> <?php echo number_format($total_cliente, 2, '.', ''); ?></a>
						<div id="<?php echo $ide_cliente; ?>" class="panel-collapse collapse">
							<div class="table-responsive">
								<table class="table table-hover">
									<tr class="info">
										<th style="padding: 2px;">Fecha</th>
										<th style="padding: 2px;">Factura</th>
										<th style="padding: 2px;">Total</th>
										<th style="padding: 2px;">NC</th>
										<th style="padding: 2px;">Abonos</th>
										<th style="padding: 2px;">Retenciones</th>
										<th style="padding: 2px;">Saldo</th>
									</tr>
									<?php
									$busca_saldos_general = mysqli_query($con, "SELECT * FROM saldo_porcobrar_porpagar WHERE id_usuario = '" . $id_usuario . "' and ruc_empresa='" . $ruc_empresa . "' and DATE_FORMAT(fecha_documento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' and id_cli_pro='" . $ide_cliente . "' ORDER BY nombre_cli_pro asc, fecha_documento asc, numero_documento asc ");
									while ($detalle = mysqli_fetch_array($busca_saldos_general)) {
										$id_encabezado = $detalle['id_documento'];
										$fecha_documento = $detalle['fecha_documento'];
										$nombre_cli_pro = $detalle['nombre_cli_pro'];
										$numero_documento = $detalle['numero_documento'];
										$total_factura = $detalle['total_factura'];
										$total_nc = $detalle['total_nc'];
										$abonos = $detalle['total_ing'] + $detalle['ing_tmp'];
										$retenciones = $detalle['total_ret'];
										$saldo = $detalle['total_factura'] - $detalle['total_nc'] - $detalle['total_ing'] - $detalle['ing_tmp'] - $detalle['total_ret'];
									?>
										<tr>
											<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha_documento)); ?></td>
											<td style="padding: 2px;"><?php echo $numero_documento; ?></td>
											<td style="padding: 2px;"><?php echo number_format($total_factura, 2, '.', ''); ?></td>
											<td style="padding: 2px;"><?php echo number_format($total_nc, 2, '.', ''); ?></td>
											<td style="padding: 2px;"><?php echo number_format($abonos, 2, '.', ''); ?></td>
											<td style="padding: 2px;"><?php echo number_format($retenciones, 2, '.', ''); ?></td>
											<td style="padding: 2px;"><?php echo number_format($saldo, 2, '.', ''); ?></td>
										</tr>
									<?php
									}
									?>
								</table>
							</div>
						</div>
					</div>
				<?php
					//}
				}
				?>
			</div>
		<?php
		}
	}


	//reporte de un solo cliente de factura
	if (!empty($id_cliente)) { //si esta lleno cliente
		resumen_por_cobrar($desde, $hasta, $id_cliente, $vendedor);
		$sql_total = mysqli_query($con, "
		SELECT COUNT(*) AS total 
		FROM encabezado_factura AS enc
		INNER JOIN clientes AS cli ON enc.id_cliente = cli.id 
		WHERE enc.ruc_empresa = '" . $ruc_empresa . "'
		AND DATE_FORMAT(enc.fecha_factura, '%Y/%m/%d') 
		BETWEEN '" . date("Y/m/d", strtotime($desde)) . "' 
		AND '" . date("Y/m/d", strtotime($hasta)) . "' and enc.id_cliente='" . $id_cliente . "'	");

		$row_total = mysqli_fetch_array($sql_total);
		$total_registros = $row_total['total'];
		if ($total_registros > 0) {
			$busca_saldos_general = mysqli_query($con, "SELECT * FROM saldo_porcobrar_porpagar WHERE id_usuario = '" . $id_usuario . "' and ruc_empresa='" . $ruc_empresa . "' and id_cli_pro='" . $id_cliente . "' and DATE_FORMAT(fecha_documento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' ORDER BY nombre_cli_pro asc, fecha_documento asc, numero_documento asc ");
		?>
			<div class="panel panel-info">
				<div class="table-responsive">
					<table class="table table-hover">
						<tr class="info">
							<th style="padding: 2px;">Fecha</th>
							<th style="padding: 2px;">Factura</th>
							<th style="padding: 2px;" class="text-right">Total</th>
							<th style="padding: 2px;" class="text-right">NC</th>
							<th style="padding: 2px;" class="text-right">Abonos</th>
							<th style="padding: 2px;" class="text-right">Retenciones</th>
							<th style="padding: 2px;" class="text-right">Saldo</th>
							<th style="padding: 2px;" class="text-right">Detalle</th>
						</tr>
						<?php
						$total_saldo = 0;
						$total_total = 0;
						$total_suma_nc = 0;
						$total_abonos = 0;
						$total_retenciones = 0;
						while ($detalle = mysqli_fetch_array($busca_saldos_general)) {
							$id_saldo = $detalle['id_saldo'];
							$id_cli_pro = $detalle['id_cli_pro'];
							$id_encabezado = $detalle['id_documento'];
							$fecha_documento = $detalle['fecha_documento'];
							$nombre_cli_pro = $detalle['nombre_cli_pro'];
							$numero_documento = $detalle['numero_documento'];
							$total_factura = $detalle['total_factura'];
							$total_nc = $detalle['total_nc'];
							$abonos = $detalle['total_ing'] + $detalle['ing_tmp'];
							$retenciones = $detalle['total_ret'];
							$saldo = $detalle['total_factura'] - $detalle['total_nc'] - $detalle['total_ing'] - $detalle['ing_tmp'] - $detalle['total_ret'];

							$total_total += $total_factura;
							$total_suma_nc += $total_nc;
							$total_abonos += $abonos;
							$total_retenciones += $retenciones;
							$total_saldo += $saldo;

						?>
							<tr>
								<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha_documento)); ?></td>
								<td style="padding: 2px;"><a href="../ajax/imprime_documento.php?id_documento=<?php echo base64_encode($id_encabezado) ?>&tipo_documento=factura&tipo_archivo=pdf" class='btn btn-default btn-xs' title='Ver'><?php echo $numero_documento; ?></i> </a></td>
								<td style="padding: 2px;" class="text-right"><?php echo number_format($total_factura, 2, '.', ''); ?></td>
								<td style="padding: 2px;" class="text-right"><?php echo number_format($total_nc, 2, '.', ''); ?></td>
								<td style="padding: 2px;" class="text-right"><?php echo number_format($abonos, 2, '.', ''); ?></td>
								<td style="padding: 2px;" class="text-right"><?php echo number_format($retenciones, 2, '.', ''); ?></td>
								<td style="padding: 2px;" class="text-right"><?php echo number_format($saldo, 2, '.', ''); ?></td>
								<td style="padding: 2px;" class="text-right">
									<a href="../pdf/resumen_cartera_individual.php?action=resumen_cartera_individual&id_factura=<?php echo base64_encode($id_encabezado); ?>" class='btn btn-default btn-xs' title="Detalles en pdf"> Pdf</a>
									<a class='btn btn-info btn-xs' title="Detalles del documento" onclick="detalle_cobranza('<?php echo $id_encabezado; ?>')" data-toggle="modal" data-target="#detalleCobranza"><span class="glyphicon glyphicon-list-alt"></span></a>
								</td>
							</tr>
						<?php
						}
						?>
						<tr class="info">
							<th style="padding: 2px;" colspan="2">Totales</th>
							<th style="padding: 2px;" class="text-right"><?php echo number_format($total_total, 2, '.', ''); ?></th>
							<th style="padding: 2px;" class="text-right"><?php echo number_format($total_suma_nc, 2, '.', ''); ?></th>
							<th style="padding: 2px;" class="text-right"><?php echo number_format($total_abonos, 2, '.', ''); ?></th>
							<th style="padding: 2px;" class="text-right"><?php echo number_format($total_retenciones, 2, '.', ''); ?></th>
							<th style="padding: 2px;" class="text-right"><?php echo number_format($total_saldo, 2, '.', ''); ?></th>
							<th style="padding: 2px;" colspan="2"></th>
						</tr>
					</table>
				</div>
			</div>
		<?php
		}
	}

	//reporte de todos los clientes de recibos
	if (empty($id_cliente)) {
		$busca_clientes = mysqli_query($con, "SELECT DISTINCT cli.id as id, cli.nombre as nombre 
				FROM clientes as cli INNER JOIN encabezado_recibo as enc On enc.id_cliente = cli.id 
				WHERE enc.ruc_empresa = '" . $ruc_empresa . "' AND DATE_FORMAT(enc.fecha_recibo, '%Y/%m/%d') BETWEEN '" . date("Y/m/d", strtotime($desde)) . "' AND '" . date("Y/m/d", strtotime($hasta)) . "' order by cli.nombre asc");

		$busca_saldos_total = mysqli_query($con, "
	SELECT SUM(enc.total_recibo - IFNULL(ing.total_ingresado, 0)) AS saldo_general
	FROM encabezado_recibo AS enc
	LEFT JOIN (
		SELECT codigo_documento_cv, SUM(valor_ing_egr) AS total_ingresado
		FROM detalle_ingresos_egresos
		GROUP BY codigo_documento_cv
	) AS ing ON ing.codigo_documento_cv = CONCAT('RV', enc.id_encabezado_recibo)
	WHERE enc.ruc_empresa = '" . $ruc_empresa . "'
	AND DATE_FORMAT(enc.fecha_recibo, '%Y/%m/%d') BETWEEN '" . date("Y/m/d", strtotime($desde)) . "' AND '" . date("Y/m/d", strtotime($hasta)) . "'	");
		$row_saldo_total = mysqli_fetch_array($busca_saldos_total);
		$suma_general = $row_saldo_total['saldo_general'];
		if (mysqli_num_rows($busca_clientes) > 0) {

			//para todos los clientes
		?>
			<li style="margin-bottom: 10px; margin-top: -10px; text-align:center; height: 50px;" class="list-group-item list-group-item-success">
				<h4><b>Saldo general por recibos de venta: </b><?php echo number_format($suma_general, 2, '.', ''); ?> Desde: <?php echo date("d-m-Y", strtotime($desde)); ?> Hasta: <?php echo date("d-m-Y", strtotime($hasta)); ?></h4>
			</li>
			<div class="panel-group" id="accordionesRecibos">
				<?php
				while ($row_clientes = mysqli_fetch_array($busca_clientes)) {
					$ide_cliente = $row_clientes['id'];
					$nombre_cliente = $row_clientes['nombre'];
					$busca_saldos_cliente = mysqli_query($con, "
					SELECT SUM(enc.total_recibo - IFNULL(ing.total_ingresado, 0)) AS total_cliente
					FROM encabezado_recibo AS enc
					LEFT JOIN (
						SELECT codigo_documento_cv, SUM(valor_ing_egr) AS total_ingresado
						FROM detalle_ingresos_egresos
						GROUP BY codigo_documento_cv
					) AS ing ON ing.codigo_documento_cv = CONCAT('RV', enc.id_encabezado_recibo)
					WHERE enc.ruc_empresa = '" . $ruc_empresa . "' and enc.id_cliente='" . $ide_cliente . "'
					AND DATE_FORMAT(enc.fecha_recibo, '%Y/%m/%d') BETWEEN '" . date("Y/m/d", strtotime($desde)) . "' AND '" . date("Y/m/d", strtotime($hasta)) . "'
					");
					$row_saldo_total_cliente = mysqli_fetch_array($busca_saldos_cliente);
					$total_cliente = $row_saldo_total_cliente['total_cliente'];
					//if ($total_cliente > 0) {
				?>
					<div class="panel panel-info">
						<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordionesRecibos" href="#<?php echo $ide_cliente . "recibo"; ?>"><span class="caret"></span> <?php echo $nombre_cliente; ?> <b>Saldo:</b> <?php echo number_format($total_cliente, 2, '.', ''); ?></a>
						<div id="<?php echo $ide_cliente . "recibo"; ?>" class="panel-collapse collapse">
							<div class="table-responsive">
								<table class="table table-hover">
									<tr class="info">
										<th style="padding: 2px;">Fecha</th>
										<th style="padding: 2px;">Recibo</th>
										<th style="padding: 2px;">Total</th>
										<th style="padding: 2px;">Abonos</th>
										<th style="padding: 2px;">Saldo</th>
									</tr>
									<?php
									$busca_saldos_general = mysqli_query($con, "SELECT * FROM encabezado_recibo as enc INNER JOIN clientes as cli ON cli.id=enc.id_cliente WHERE enc.ruc_empresa='" . $ruc_empresa . "' and DATE_FORMAT(enc.fecha_recibo, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' and enc.id_cliente='" . $ide_cliente . "' ORDER BY cli.nombre asc, enc.fecha_recibo asc, enc.secuencial_recibo asc ");
									while ($detalle = mysqli_fetch_array($busca_saldos_general)) {
										$id_encabezado = $detalle['id_encabezado_recibo'];
										$fecha_documento = $detalle['fecha_recibo'];
										$nombre_cli_pro = $detalle['nombre'];
										$numero_documento = $detalle['serie_recibo'] . "-" . $detalle['secuencial_recibo'];
										$total_recibo = $detalle['total_recibo'];
										$codigo_recibo = "RV" . $id_encabezado;

										$busca_ingresos = mysqli_query($con, "SELECT sum(det.valor_ing_egr) as abonos 
										FROM detalle_ingresos_egresos as det 
										INNER JOIN ingresos_egresos as enc 
										ON enc.codigo_documento=det.codigo_documento
										 WHERE enc.ruc_empresa='" . $ruc_empresa . "' 
										 and DATE_FORMAT(enc.fecha_ing_egr, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
										 and '" . date("Y/m/d", strtotime($hasta)) . "' and det.codigo_documento_cv = '" . $codigo_recibo . "'");
										$row_ingreso = mysqli_fetch_array($busca_ingresos);
										$abonos = $row_ingreso['abonos'];
										$saldo = $total_recibo - $abonos;
									?>
										<tr>
											<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha_documento)); ?></td>
											<td style="padding: 2px;"><?php echo $numero_documento; ?></td>
											<td style="padding: 2px;"><?php echo number_format($total_recibo, 2, '.', ''); ?></td>
											<td style="padding: 2px;"><?php echo number_format($abonos, 2, '.', ''); ?></td>
											<td style="padding: 2px;"><?php echo number_format($saldo, 2, '.', ''); ?></td>
										</tr>
									<?php
									}
									?>
								</table>
							</div>
						</div>
					</div>
				<?php
					//}
				}
				?>
			</div>
		<?php
		}
	}

	//reporte de un solo cliente de recibos
	if (!empty($id_cliente)) {
		$busca_clientes = mysqli_query($con, "SELECT DISTINCT cli.id as id, cli.nombre as nombre 
					FROM clientes as cli INNER JOIN encabezado_recibo as enc On enc.id_cliente = cli.id 
					WHERE enc.ruc_empresa = '" . $ruc_empresa . "' and enc.id_cliente='" . $id_cliente . "' order by cli.nombre asc");

		$busca_saldos_total = mysqli_query($con, "
		SELECT SUM(enc.total_recibo - IFNULL(ing.total_ingresado, 0)) AS saldo_general
		FROM encabezado_recibo AS enc
		LEFT JOIN (
			SELECT codigo_documento_cv, SUM(valor_ing_egr) AS total_ingresado
			FROM detalle_ingresos_egresos
			GROUP BY codigo_documento_cv
		) AS ing ON ing.codigo_documento_cv = CONCAT('RV', enc.id_encabezado_recibo)
		WHERE enc.ruc_empresa = '" . $ruc_empresa . "'
		AND DATE_FORMAT(enc.fecha_recibo, '%Y/%m/%d') BETWEEN '" . date("Y/m/d", strtotime($desde)) . "' AND '" . date("Y/m/d", strtotime($hasta)) . "' and enc.id_cliente='" . $id_cliente . "'	");
		$row_saldo_total = mysqli_fetch_array($busca_saldos_total);
		$suma_general = $row_saldo_total['saldo_general'];

		if (mysqli_num_rows($busca_clientes) > 0) {
		?>
			<!-- 		<li style="margin-bottom: 10px; margin-top: -10px; text-align:center; height: 50px;" class="list-group-item list-group-item-success">
			<h4><b>Saldo individual por recibos de venta: </b><?php echo number_format($suma_general, 2, '.', ''); ?> Al: <?php echo date("d-m-Y", strtotime($hasta)); ?></h4>
		</li> -->
			<div class="panel-group" id="accordiones">
				<?php
				while ($row_clientes = mysqli_fetch_array($busca_clientes)) {
					$ide_cliente = $row_clientes['id'];
					$nombre_cliente = $row_clientes['nombre'];
					$busca_saldos_cliente = mysqli_query($con, "
						SELECT SUM(enc.total_recibo - IFNULL(ing.total_ingresado, 0)) AS total_cliente
						FROM encabezado_recibo AS enc
						LEFT JOIN (
							SELECT codigo_documento_cv, SUM(valor_ing_egr) AS total_ingresado
							FROM detalle_ingresos_egresos
							GROUP BY codigo_documento_cv
						) AS ing ON ing.codigo_documento_cv = CONCAT('RV', enc.id_encabezado_recibo)
						WHERE enc.ruc_empresa = '" . $ruc_empresa . "' and enc.id_cliente='" . $ide_cliente . "'
						AND DATE_FORMAT(enc.fecha_recibo, '%Y/%m/%d') BETWEEN '" . date("Y/m/d", strtotime($desde)) . "' AND '" . date("Y/m/d", strtotime($hasta)) . "'
						");
					$row_saldo_total_cliente = mysqli_fetch_array($busca_saldos_cliente);
					$total_cliente = $row_saldo_total_cliente['total_cliente'];
					//if ($total_cliente > 0) {
				?>
					<div class="panel panel-info">
						<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordiones" href="#<?php echo $ide_cliente; ?>"><span class="caret"></span> <?php echo $nombre_cliente; ?> <b>Saldo:</b> <?php echo number_format($total_cliente, 2, '.', ''); ?></a>
						<div id="<?php echo $ide_cliente; ?>" class="panel-collapse">
							<div class="table-responsive">
								<table class="table table-hover">
									<tr class="info">
										<th style="padding: 2px;">Fecha</th>
										<th style="padding: 2px;">Recibo</th>
										<th style="padding: 2px;">Total</th>
										<th style="padding: 2px;">Abonos</th>
										<th style="padding: 2px;">Saldo</th>
									</tr>
									<?php
									$busca_saldos_general = mysqli_query($con, "SELECT * FROM encabezado_recibo as enc INNER JOIN clientes as cli ON cli.id=enc.id_cliente WHERE enc.ruc_empresa='" . $ruc_empresa . "' and DATE_FORMAT(enc.fecha_recibo, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' and enc.id_cliente='" . $ide_cliente . "' ORDER BY cli.nombre asc, enc.fecha_recibo asc, enc.secuencial_recibo asc ");
									while ($detalle = mysqli_fetch_array($busca_saldos_general)) {
										$id_encabezado = $detalle['id_encabezado_recibo'];
										$fecha_documento = $detalle['fecha_recibo'];
										$nombre_cli_pro = $detalle['nombre'];
										$numero_documento = $detalle['serie_recibo'] . "-" . $detalle['secuencial_recibo'];
										$total_recibo = $detalle['total_recibo'];
										$codigo_recibo = "RV" . $id_encabezado;

										$busca_ingresos = mysqli_query($con, "SELECT sum(det.valor_ing_egr) as abonos 
											FROM detalle_ingresos_egresos as det 
											INNER JOIN ingresos_egresos as enc 
											ON enc.codigo_documento=det.codigo_documento
											 WHERE enc.ruc_empresa='" . $ruc_empresa . "' 
											 and DATE_FORMAT(enc.fecha_ing_egr, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
											 and '" . date("Y/m/d", strtotime($hasta)) . "' and det.codigo_documento_cv = '" . $codigo_recibo . "'");
										$row_ingreso = mysqli_fetch_array($busca_ingresos);
										$abonos = $row_ingreso['abonos'];
										$saldo = $total_recibo - $abonos;
									?>
										<tr>
											<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha_documento)); ?></td>
											<td style="padding: 2px;"><?php echo $numero_documento; ?></td>
											<td style="padding: 2px;"><?php echo number_format($total_recibo, 2, '.', ''); ?></td>
											<td style="padding: 2px;"><?php echo number_format($abonos, 2, '.', ''); ?></td>
											<td style="padding: 2px;"><?php echo number_format($saldo, 2, '.', ''); ?></td>
										</tr>
									<?php
									}
									?>
								</table>
							</div>
						</div>
					</div>
				<?php
					//}
				}
				?>
			</div>
	<?php
		}
	}
}

function busca_cliente($con, $id_cliente)
{
	//para traer el mail del cliente
	$busca_mail_cliente = mysqli_query($con, "SELECT * FROM clientes WHERE id = '" . $id_cliente . "'");
	$row_cliente = mysqli_fetch_array($busca_mail_cliente);
	return $row_cliente;
}

function resumen_documento($con, $id_documento)
{
	//para factura
	$busca_factura = mysqli_query($con, "SELECT * FROM encabezado_factura WHERE id_encabezado_factura='" . $id_documento . "' order by id_encabezado_factura asc");
	?>
	<div class="panel panel-info">
		Detalle de Factura
		<table class="table table-hover">
			<tr class="info">
				<th style="padding: 2px;">Fecha</th>
				<th style="padding: 2px;">Factura</th>
				<th style="padding: 2px;">Total</th>
			</tr>
			<?php
			$total_factura = 0;
			while ($respuesta = mysqli_fetch_array($busca_factura)) {
			?>
				<tr>
					<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($respuesta['fecha_factura'])); ?></td>
					<td style="padding: 2px;"><?php echo  $respuesta['serie_factura'] . "-" . str_pad($respuesta['secuencial_factura'], 9, "000000000", STR_PAD_LEFT); ?></td>
					<td style="padding: 2px;"><?php echo number_format($respuesta['total_factura'], 2, '.', ''); ?></td>
				</tr>
			<?php
				$total_factura += $respuesta['total_factura'];
			}
			?>
		</table>
	</div>
	<?php
	$busca_notas_credito = mysqli_query($con, "SELECT * FROM encabezado_nc as enc 
	INNER JOIN encabezado_factura as fact ON concat(fact.serie_factura,'-',LPAD(fact.secuencial_factura,9,'000000000')) = enc.factura_modificada and fact.ruc_empresa=enc.ruc_empresa 
	WHERE fact.id_encabezado_factura='" . $id_documento . "' order by fact.id_encabezado_factura asc");
	$total_nc = 0;
	if ($busca_notas_credito->num_rows > 0) {
	?>
		<div class="panel panel-info">
			Detalle de notas de crédito
			<table class="table table-hover">
				<tr class="info">
					<th style="padding: 2px;">Fecha</th>
					<th style="padding: 2px;">Nota Crédito</th>
					<th style="padding: 2px;">Total</th>
				</tr>
				<?php

				while ($respuesta = mysqli_fetch_array($busca_notas_credito)) {
				?>
					<tr>
						<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($respuesta['fecha_nc'])); ?></td>
						<td style="padding: 2px;"><?php echo  $respuesta['serie_nc'] . "-" . str_pad($respuesta['secuencial_nc'], 9, "000000000", STR_PAD_LEFT); ?></td>
						<td style="padding: 2px;"><?php echo number_format($respuesta['total_nc'], 2, '.', ''); ?></td>
					</tr>
				<?php
					$total_nc += $respuesta['total_nc'];
				}

				?>
			</table>
		</div>
	<?php
	}
	//para detalle de ingresos
	$busca_detalle_ingresos = mysqli_query($con, "SELECT inen.fecha_ing_egr as fecha_ing_egr, 
	inen.numero_ing_egr as numero_ing_egr, det.valor_ing_egr as valor_ing_egr 
	FROM detalle_ingresos_egresos as det 
	INNER JOIN ingresos_egresos as inen ON det.codigo_documento=inen.codigo_documento 
	WHERE det.tipo_documento='INGRESO' and det.codigo_documento_cv='" . $id_documento . "' "); //group by det.valor_ing_egr
	$total_ingresos	= 0;
	if ($busca_detalle_ingresos->num_rows > 0) {
	?>
		<div class="panel panel-info">
			Detalle de ingresos
			<table class="table table-hover">
				<tr class="info">
					<th style="padding: 2px;">Fecha</th>
					<th style="padding: 2px;">Ingreso</th>
					<th style="padding: 2px;">Total</th>
				</tr>
				<?php

				while ($respuesta = mysqli_fetch_array($busca_detalle_ingresos)) {
				?>
					<tr>
						<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($respuesta['fecha_ing_egr'])); ?></td>
						<td style="padding: 2px;"><?php echo  $respuesta['numero_ing_egr']; ?></td>
						<td style="padding: 2px;"><?php echo number_format($respuesta['valor_ing_egr'], 2, '.', ''); ?></td>
					</tr>
				<?php
					$total_ingresos	+= $respuesta['valor_ing_egr'];
				}
				?>
			</table>
		</div>
	<?php
	}
	//para detalle de retenciones
	$busca_retenciones = mysqli_query($con, "SELECT * FROM encabezado_factura as enc LEFT JOIN cuerpo_retencion_venta as cue ON cue.numero_documento= CONCAT(REPLACE(enc.serie_factura,'-',''), LPAD(enc.secuencial_factura,9,'000000000')) and cue.ruc_empresa=enc.ruc_empresa INNER JOIN encabezado_retencion_venta as enret ON enret.codigo_unico=cue.codigo_unico WHERE enc.id_encabezado_factura='" . $id_documento . "' order by enc.fecha_factura asc");
	$total_retenciones = 0;
	if ($busca_retenciones->num_rows > 0) {
	?>
		<div class="panel panel-info">
			Detalle de retenciones
			<table class="table table-hover">
				<tr class="info">
					<th style="padding: 2px;">Fecha</th>
					<th style="padding: 2px;">Retención</th>
					<th style="padding: 2px;">Porcentaje</th>
					<th style="padding: 2px;">Valor</th>

				</tr>
				<?php

				while ($respuesta = mysqli_fetch_array($busca_retenciones)) {
				?>
					<tr>
						<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($respuesta['fecha_emision'])); ?></td>
						<td style="padding: 2px;"><?php echo  $respuesta['serie_retencion'] . "-" . str_pad($respuesta['secuencial_retencion'], 9, "000000000", STR_PAD_LEFT); ?></td>
						<td style="padding: 2px;"><?php echo $respuesta['porcentaje_retencion']; ?></td>
						<td style="padding: 2px;"><?php echo number_format($respuesta['valor_retenido'], 2, '.', ''); ?></td>
					</tr>
				<?php
					$total_retenciones += $respuesta['valor_retenido'];
				}
				?>
			</table>
		</div>
	<?php
	}
	?>
	<div class="panel panel-info">
		<table class="table table-hover">
			<tr class="info">
				<th style="padding: 2px;">Deuda</th>
				<th style="padding: 2px;"> (-) NC</th>
				<th style="padding: 2px;"> (-) Abonos</th>
				<th style="padding: 2px;"> (-) Retenciones</th>
				<th style="padding: 2px;"> (=) Saldo</th>
			</tr>
			<tr>
				<td style="padding: 2px;"><?php echo number_format($total_factura, 2, '.', ''); ?></td>
				<td style="padding: 2px;"><?php echo number_format($total_nc, 2, '.', ''); ?></td>
				<td style="padding: 2px;"><?php echo number_format($total_ingresos, 2, '.', ''); ?></td>
				<td style="padding: 2px;"><?php echo number_format($total_retenciones, 2, '.', ''); ?></td>
				<td style="padding: 2px;"><?php echo number_format($total_factura - $total_nc - $total_ingresos - $total_retenciones, 2, '.', ''); ?></td>
			</tr>
		</table>
	</div>
<?php
}

?>