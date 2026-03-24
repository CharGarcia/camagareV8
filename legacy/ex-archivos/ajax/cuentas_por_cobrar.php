<?PHP
include("../conexiones/conectalogin.php");
session_start();
$con = conenta_login();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
if ($action == 'ventas_por_cobrar') {
	$desde = "2018/01/01";
	$hasta = $_GET['hasta'];
	$id_cliente = $_GET['id_cliente'];
	$vendedor = $_GET['vendedor'];
	resumen_por_cobrar($desde, $hasta, $id_cliente, $vendedor);
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

	$limpiar_saldos_facturas = mysqli_query($con, "DELETE FROM saldo_porcobrar_porpagar WHERE id_usuario='" . $id_usuario . "' and ruc_empresa = '" . $ruc_empresa . "' and tipo='POR_COBRAR'");
	$limpiar_saldos_recibos = mysqli_query($con, "DELETE FROM saldo_porcobrar_porpagar WHERE id_usuario='" . $id_usuario . "' and ruc_empresa = '" . $ruc_empresa . "' and tipo='POR_COBRAR_RECIBOS'");
	$query_por_cobrar = mysqli_query($con, "INSERT INTO saldo_porcobrar_porpagar (id_saldo, tipo, fecha_documento, id_cli_pro, nombre_cli_pro, numero_documento, id_usuario, ruc_empresa, total_factura, total_nc, total_ing, ing_tmp, total_ret, id_documento)
	SELECT null, 'POR_COBRAR', enc_fac.fecha_factura, enc_fac.id_cliente, (select nombre from clientes as cli where cli.id=enc_fac.id_cliente), 
	concat(enc_fac.serie_factura,'-', LPAD(enc_fac.secuencial_factura,9,'0')),'" . $id_usuario . "', '" . $ruc_empresa . "', enc_fac.total_factura, 
	(select sum(total_nc) from encabezado_nc as nc where nc.factura_modificada = concat(enc_fac.serie_factura,'-',LPAD(enc_fac.secuencial_factura,9,'0')) and enc_fac.ruc_empresa = '" . $ruc_empresa . "' and nc.ruc_empresa = '" . $ruc_empresa . "' and DATE_FORMAT(nc.fecha_nc, '%Y/%m/%d') <= '" . date("Y/m/d", strtotime($hasta)) . "'),
	0,0,0, enc_fac.id_encabezado_factura FROM encabezado_factura as enc_fac WHERE enc_fac.ruc_empresa = '" . $ruc_empresa . "' and enc_fac.estado_sri = 'AUTORIZADO' and DATE_FORMAT(enc_fac.fecha_factura, '%Y/%m/%d') <= '" . date("Y/m/d", strtotime($hasta)) . "' $condicion_cliente ");
	$update_ingresos = mysqli_query($con, "UPDATE saldo_porcobrar_porpagar as sal_ing, (SELECT detie.codigo_documento_cv as id_documento_venta, sum(detie.valor_ing_egr) as suma_ingresos FROM detalle_ingresos_egresos as detie INNER JOIN ingresos_egresos as ing_egr ON ing_egr.codigo_documento=detie.codigo_documento WHERE detie.estado ='OK' and detie.tipo_documento='INGRESO' and detie.tipo_ing_egr='CCXCC' and DATE_FORMAT(ing_egr.fecha_ing_egr, '%Y/%m/%d') <= '" . date("Y/m/d", strtotime($hasta)) . "' group by detie.codigo_documento_cv) as total_ingresos SET sal_ing.total_ing = total_ingresos.suma_ingresos WHERE sal_ing.id_documento=total_ingresos.id_documento_venta and sal_ing.ruc_empresa = '" . $ruc_empresa . "' ");
	$query_actualizar_retencion = mysqli_query($con, "UPDATE saldo_porcobrar_porpagar as sal_ret, (SELECT det_ret.numero_documento as codigo_registro, sum(det_ret.valor_retenido) as suma_retencion FROM cuerpo_retencion_venta as det_ret INNER JOIN encabezado_retencion_venta as enc_ret ON enc_ret.codigo_unico=det_ret.codigo_unico WHERE mid(enc_ret.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and DATE_FORMAT(enc_ret.fecha_emision, '%Y/%m/%d') <= '" . date("Y/m/d", strtotime($hasta)) . "' group by det_ret.numero_documento) as total_retencion SET sal_ret.total_ret = total_retencion.suma_retencion WHERE replace(sal_ret.numero_documento,'-','')=total_retencion.codigo_registro ");
	//para borrar las que tienen saldo cero
	$eliminar_saldos_cero = mysqli_query($con, "DELETE FROM saldo_porcobrar_porpagar WHERE ruc_empresa = '" . $ruc_empresa . "' and total_factura <= (total_nc + total_ing  + ing_tmp + total_ret)");

	//para borrar las que no tienen asociado un vendedor
	if ($vendedor != 0) {
		$eliminar_ventas_vendedores = mysqli_query($con, "DELETE FROM saldo_porcobrar_porpagar WHERE id_documento NOT IN (SELECT id_venta FROM vendedores_ventas WHERE id_vendedor='" . $vendedor . "') ");
	}
}

if ($action == 'generar_informe') {
	ini_set('date.timezone', 'America/Guayaquil');
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$id_usuario = $_SESSION['id_usuario'];
	$id_cliente = $_POST['id_cliente'];
	$desde = "2018/01/01";
	$hasta = $_POST['hasta'];
	$vendedor = $_POST['vendedor'];
	$con = conenta_login();
	$fecha_hoy = date_create(date("Y-m-d H:i:s"));

	if (empty($id_cliente)) { // para todos los clientes

		$busca_saldos_general = resumen_por_cobrar($desde, $hasta, $id_cliente, $vendedor);
		$busca_clientes = mysqli_query($con, "SELECT DISTINCT sal.id_cli_pro as id, sal.nombre_cli_pro as nombre, cli.telefono as telefono FROM saldo_porcobrar_porpagar as sal 
		INNER JOIN clientes as cli ON cli.id=sal.id_cli_pro WHERE sal.ruc_empresa = '" . $ruc_empresa . "' order by sal.nombre_cli_pro asc");

		$sql_empresa = mysqli_query($con, "SELECT nombre_comercial FROM empresas WHERE ruc = '" . $ruc_empresa . "' ");
		$row_empresa = mysqli_fetch_array($sql_empresa);
		$nombre_comercial_empresa = $row_empresa['nombre_comercial'];


		$busca_saldos_total = mysqli_query($con, "SELECT sum(total_factura - (total_nc + total_ing  + ing_tmp + total_ret)) as saldo_general FROM saldo_porcobrar_porpagar WHERE id_usuario = '" . $id_usuario . "' and ruc_empresa='" . $ruc_empresa . "' and DATE_FORMAT(fecha_documento, '%Y/%m/%d') <= '" . date("Y/m/d", strtotime($hasta)) . "' ");
		$row_saldo_total = mysqli_fetch_array($busca_saldos_total);
		$suma_general = $row_saldo_total['saldo_general'];
		//para todos los clientes
?>
		<div style="padding: 1px; margin-bottom: 5px;" class="alert alert-success text-center" role="alert">
			Saldo total cuentas por cobrar por facturas de venta al <?php echo date("d-m-Y", strtotime($hasta)); ?>: <b><?php echo number_format($suma_general, 2, '.', ''); ?> </b>
		</div>
		<div class="panel-group" id="accordiones">
			<?php
			while ($row_clientes = mysqli_fetch_array($busca_clientes)) {
				$ide_cliente = $row_clientes['id'];
				$nombre_cliente = $row_clientes['nombre'];
				$telefono = $row_clientes['telefono'];
				$sql_suma_cliente = mysqli_query($con, "SELECT sum(round(total_factura - (total_nc + total_ing  + ing_tmp + total_ret),2)) as total_cliente FROM saldo_porcobrar_porpagar WHERE id_cli_pro = '" . $ide_cliente . "' and id_usuario='" . $id_usuario . "' and ruc_empresa='" . $ruc_empresa . "'");
				$row_total_cliente = mysqli_fetch_array($sql_suma_cliente);
				$total_cliente = number_format($row_total_cliente['total_cliente'], 2, '.', '');

				$sql_suma_ventas = mysqli_query($con, "SELECT sum(round(total_factura,2)) as total_ventas FROM encabezado_factura WHERE id_cliente = '" . $ide_cliente . "' and DATE_FORMAT(fecha_factura, '%Y/%m/%d') <= '" . date("Y/m/d", strtotime($hasta)) . "' and mid(ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' group by id_cliente");
				$row_total_ventas = mysqli_fetch_array($sql_suma_ventas);
				$total_ventas = isset($row_total_ventas['total_ventas']) ? $row_total_ventas['total_ventas'] : 0;

				$sql_suma_nc = mysqli_query($con, "SELECT sum(round(total_nc,2)) as total_nc FROM encabezado_nc WHERE id_cliente = '" . $ide_cliente . "' and DATE_FORMAT(fecha_nc, '%Y/%m/%d') <= '" . date("Y/m/d", strtotime($hasta)) . "' and mid(ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' group by id_cliente");
				$row_total_nc = mysqli_fetch_array($sql_suma_nc);
				$total_nc = isset($row_total_nc['total_nc']) ? $row_total_nc['total_nc'] : 0;

				$sql_suma_retenciones = mysqli_query($con, "SELECT sum(round(det.valor_retenido,2)) as total_retenciones FROM cuerpo_retencion_venta as det INNER JOIN encabezado_retencion_venta  as enc ON enc.codigo_unico=det.codigo_unico WHERE enc.id_cliente = '" . $ide_cliente . "' and DATE_FORMAT(enc.fecha_emision, '%Y/%m/%d') <= '" . date("Y/m/d", strtotime($hasta)) . "' and mid(enc.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' group by det.codigo_unico");
				$row_total_retenciones = mysqli_fetch_array($sql_suma_retenciones);
				$total_retenciones = isset($row_total_retenciones['total_retenciones']) ? $row_total_retenciones['total_retenciones'] : 0;

				$total_ventas_netas = number_format($total_ventas - $total_nc - $total_retenciones, 2, '.', '');

				$sql_suma_ingresos_cliente = mysqli_query($con, "SELECT sum(round(valor_ing_egr,2)) as ingresos FROM ingresos_egresos WHERE id_cli_pro = '" . $ide_cliente . "' and tipo_ing_egr ='INGRESO' and DATE_FORMAT(fecha_ing_egr, '%Y/%m/%d') <= '" . date("Y/m/d", strtotime($hasta)) . "' and mid(ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "'");
				$row_total_ingresos_cliente = mysqli_fetch_array($sql_suma_ingresos_cliente);
				$total_ingresos_cliente = $row_total_ingresos_cliente['ingresos'];

				$query_puerto_whatsapp = mysqli_query($con, "SELECT count(*) FROM puerto_whatsapp WHERE ruc_empresa = '" . $ruc_empresa . "' and  status= '1'");
				$row_whatsapp = mysqli_fetch_row($query_puerto_whatsapp);
				$count_whatsapp = $row_whatsapp[0];

				$mensaje = "Estimad@ " . $nombre_cliente . ", actualmente mantenemos un saldo pendiente por " . number_format($total_cliente, 2, '.', '') . " Dólares, a la empresa " . $nombre_comercial_empresa;

				if ($total_cliente > 0) {
			?>
					<div class="panel panel-info">
						<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordiones" href="#<?php echo $ide_cliente; ?>"><span class="caret"></span> <b>Cliente:</b> <?php echo $nombre_cliente; ?> <b>Saldo:</b> <?php echo number_format($total_cliente, 2, '.', ''); ?> </a>
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
										<th style="padding: 2px;">Días</th>
										<th style="padding: 2px;">Asesor</th>
									</tr>

									<?php
									$saldo_porcobrar_porpagar = mysqli_query($con, "SELECT * FROM saldo_porcobrar_porpagar WHERE id_usuario = '" . $id_usuario . "' and ruc_empresa='" . $ruc_empresa . "' and DATE_FORMAT(fecha_documento, '%Y/%m/%d') <= '" . date("Y/m/d", strtotime($hasta)) . "' and id_cli_pro='" . $ide_cliente . "' ORDER BY nombre_cli_pro asc, fecha_documento asc, numero_documento asc ");
									while ($detalle = mysqli_fetch_array($saldo_porcobrar_porpagar)) {
										$id_encabezado = $detalle['id_documento'];
										$fecha_documento = $detalle['fecha_documento'];
										$nombre_cli_pro = $detalle['nombre_cli_pro'];
										$numero_documento = $detalle['numero_documento'];
										$total_factura = $detalle['total_factura'];
										$total_nc = $detalle['total_nc'];
										$abonos = $detalle['total_ing'] + $detalle['ing_tmp'];
										$retenciones = $detalle['total_ret'];
										$saldo = $detalle['total_factura'] - $detalle['total_nc'] - $detalle['total_ing'] - $detalle['ing_tmp'] - $detalle['total_ret'];
										$fecha_vencimiento = date_create($fecha_documento);
										$diferencia_dias = date_diff($fecha_hoy, $fecha_vencimiento);
										$total_dias = $diferencia_dias->format('%a');

										$datos_vendedor = mysqli_query($con, "SELECT * FROM vendedores as ven INNER JOIN vendedores_ventas as ven_ven ON ven_ven.id_vendedor=ven.id_vendedor WHERE  ven_ven.id_venta= '" . $id_encabezado . "' ");
										$detalle_vendedor = mysqli_fetch_array($datos_vendedor);
										$nombre_vendedor = isset($detalle_vendedor['nombre']) ? $detalle_vendedor['nombre'] : "";
									?>
										<tr>
											<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha_documento)); ?></td>
											<td style="padding: 2px;"><?php echo $numero_documento; ?></td>
											<td style="padding: 2px;"><?php echo number_format($total_factura, 2, '.', ''); ?></td>
											<td style="padding: 2px;"><?php echo number_format($total_nc, 2, '.', ''); ?></td>
											<td style="padding: 2px;"><?php echo number_format($abonos, 2, '.', ''); ?></td>
											<td style="padding: 2px;"><?php echo number_format($retenciones, 2, '.', ''); ?></td>
											<td style="padding: 2px;"><?php echo number_format($saldo, 2, '.', ''); ?></td>
											<td style="padding: 2px;"><?php echo $total_dias; ?></td>
											<td style="padding: 2px;"><?php echo $nombre_vendedor; ?></td>
										</tr>
									<?php
									}
									if ($count_whatsapp >= 1) {
									?>
										<tr class="info">
											<th class="text-center" style="padding: 2px;" colspan="9"> Enviar por whatsapp >> <a class='btn btn-success btn-xs' title='Enviar cuenta total por whatsapp' onclick="enviar_cxc_whatsapp('<?php echo $ruc_empresa; ?>', '<?php echo $telefono; ?>', '<?php echo $mensaje; ?>')" data-toggle="modal" data-target="#EnviarDocumentosWhatsapp"><img src="../image/whatsapp.png" alt="Logo" width="15px"> </a></th>
										</tr>
									<?php
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

		<!--hasta aqui de facturas -->
		<?php
		$saldo_total = saldo_total_recibos($con, $hasta, $ruc_empresa);

		if ($saldo_total > 0) {
		?>
			<div style="padding: 1px; margin-bottom: 5px;" class="alert alert-success text-center" role="alert">
				Saldo total cuentas por cobrar por recibos de venta al <?php echo date("d-m-Y", strtotime($hasta)); ?>: <b><?php echo number_format($saldo_total, 2, '.', ''); ?> </b>
			</div>
			<div class="panel-group" id="accordionesRecibos">
				<?php
				$id_cliente = "";
				$busca_clientes_recibos = clientes_recibos($con, $hasta, $ruc_empresa, $id_cliente, $vendedor);
				while ($row_clientes = mysqli_fetch_array($busca_clientes_recibos)) {
					$id_cliente_recibo = $row_clientes['id'];
					$nombre_cliente = $row_clientes['nombre'];

					$total_cliente_generales = saldo_recibo_por_cliente($con, $ruc_empresa, $hasta, $id_cliente_recibo);

					if ($total_cliente_generales > 0) {
						$id_cliente_id = "RV" . $id_cliente_recibo;
				?>
						<div class="panel panel-info">
							<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordionesRecibos" href="#<?php echo $id_cliente_id; ?>"><span class="caret"></span> <b>Cliente:</b> <?php echo $nombre_cliente; ?> <b>Saldo:</b> <?php echo number_format($total_cliente_generales, 2, '.', ''); ?></a>
							<div id="<?php echo $id_cliente_id; ?>" class="panel-collapse collapse">
								<div class="table-responsive">
									<table class="table table-hover">
										<tr class="info">
											<th style="padding: 2px;">Fecha</th>
											<th style="padding: 2px;">Recibo</th>
											<th style="padding: 2px;">Total</th>
											<th style="padding: 2px;">Abonos</th>
											<th style="padding: 2px;">Saldo</th>
											<th style="padding: 2px;">Días</th>
											<th style="padding: 2px;">Asesor</th>
										</tr>
										<?php
										$recibos_individuales = recibos_del_cliente($con, $ruc_empresa, $hasta, $id_cliente_recibo);
										while ($detalle = mysqli_fetch_array($recibos_individuales)) {
											$id_encabezado = $detalle['id_encabezado_recibo'];
											$id_documento = "RV" . $id_encabezado;
											$fecha_documento = $detalle['fecha_recibo'];
											$numero_documento = $detalle['serie_recibo'] . "-" . str_pad($detalle['secuencial_recibo'], 9, "000000000", STR_PAD_LEFT);
											$total_recibo = $detalle['total_recibo'];
											$fecha_vencimiento = date_create($fecha_documento);
											$diferencia_dias = date_diff($fecha_hoy, $fecha_vencimiento);
											$total_dias = $diferencia_dias->format('%a');

											$nombre_vendedor = vendedores_recibos($con, $id_encabezado);

											$suma_abonos_recibo = abonos_cliente_recibo($con, $ruc_empresa, $hasta, $id_documento);
											$saldo = $total_recibo - $suma_abonos_recibo;
											if ($saldo > 0) {
										?>
												<tr>
													<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha_documento)); ?></td>
													<td style="padding: 2px;"><?php echo $numero_documento; ?></td>
													<td style="padding: 2px;"><?php echo number_format($total_recibo, 2, '.', ''); ?></td>
													<td style="padding: 2px;"><?php echo number_format($suma_abonos_recibo, 2, '.', ''); ?></td>
													<td style="padding: 2px;"><?php echo number_format($saldo, 2, '.', ''); ?></td>
													<td style="padding: 2px;"><?php echo $total_dias; ?></td>
													<td style="padding: 2px;"><?php echo $nombre_vendedor; ?></td>
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
	}

	if (!empty($id_cliente)) { //si esta lleno cliente
		resumen_por_cobrar($desde, $hasta, $id_cliente, $vendedor);

		$sql_empresa = mysqli_query($con, "SELECT nombre_comercial FROM empresas WHERE ruc = '" . $ruc_empresa . "' ");
		$row_empresa = mysqli_fetch_array($sql_empresa);
		$nombre_comercial_empresa = $row_empresa['nombre_comercial'];

		$busca_saldos_general = mysqli_query($con, "SELECT * FROM saldo_porcobrar_porpagar as sal INNER JOIN clientes as cli ON 
		cli.id=sal.id_cli_pro WHERE sal.id_usuario = '" . $id_usuario . "' and sal.ruc_empresa='" . $ruc_empresa . "' and sal.id_cli_pro='" . $id_cliente . "' and DATE_FORMAT(sal.fecha_documento, '%Y/%m/%d') <= '" . date("Y/m/d", strtotime($hasta)) . "' ORDER BY sal.nombre_cli_pro asc, fecha_documento asc, sal.numero_documento asc ");
		$registros_facturas = mysqli_num_rows($busca_saldos_general);
		if ($registros_facturas > 0) {
		?>
			<div class="panel panel-info">
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
							<th style="padding: 2px;">Días</th>
							<th style="padding: 2px;">Asesor</th>
							<th style="padding: 2px;" class="text-right">Opciones</th>
						</tr>
						<?php
						$total_saldo = 0;
						$email = "";
						while ($detalle = mysqli_fetch_array($busca_saldos_general)) {
							$id_saldo = $detalle['id_saldo'];
							$id_encabezado = $detalle['id_documento'];
							$fecha_documento = $detalle['fecha_documento'];
							$nombre_cli_pro = $detalle['nombre_cli_pro'];
							$numero_documento = $detalle['numero_documento'];
							$total_factura = $detalle['total_factura'];
							$total_nc = $detalle['total_nc'];
							$abonos = $detalle['total_ing'] + $detalle['ing_tmp'];
							$retenciones = $detalle['total_ret'];
							$saldo = $detalle['total_factura'] - $detalle['total_nc'] - $detalle['total_ing'] - $detalle['ing_tmp'] - $detalle['total_ret'];
							$total_saldo += $saldo;
							$fecha_vencimiento = date_create($fecha_documento);
							$diferencia_dias = date_diff($fecha_hoy, $fecha_vencimiento);
							$total_dias = $diferencia_dias->format('%a');
							$email = $detalle['email'];
							$telefono = $detalle['telefono'];
							$cliente = $detalle['nombre'];
							$datos_vendedor = mysqli_query($con, "SELECT * FROM vendedores as ven INNER JOIN vendedores_ventas as ven_ven ON ven_ven.id_vendedor=ven.id_vendedor WHERE  ven_ven.id_venta= '" . $id_encabezado . "' ");
							$detalle_vendedor = mysqli_fetch_array($datos_vendedor);
							$nombre_vendedor = isset($detalle_vendedor['nombre']) ? $detalle_vendedor['nombre'] : "";

							$mensaje = "Estimad@ " . $cliente . ", actualmente mantenemos un saldo pendiente por " . number_format($total_saldo, 2, '.', '') . " Dólares, a la empresa " . $nombre_comercial_empresa;

							$query_puerto_whatsapp = mysqli_query($con, "SELECT count(*) FROM puerto_whatsapp WHERE ruc_empresa = '" . $ruc_empresa . "' and  status= '1'");
							$row_whatsapp = mysqli_fetch_row($query_puerto_whatsapp);
							$count_whatsapp = $row_whatsapp[0];

						?>
							<tr>
								<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha_documento)); ?></td>
								<td style="padding: 2px;"><a href="../ajax/imprime_documento.php?id_documento=<?php echo base64_encode($id_encabezado) ?>&tipo_documento=factura&tipo_archivo=pdf" class='btn btn-default btn-xs' title='Ver' target="_blank"><?php echo $numero_documento; ?></i> </a></td>
								<td style="padding: 2px;"><?php echo number_format($total_factura, 2, '.', ''); ?></td>
								<td style="padding: 2px;"><?php echo number_format($total_nc, 2, '.', ''); ?></td>
								<td style="padding: 2px;"><?php echo number_format($abonos, 2, '.', ''); ?></td>
								<td style="padding: 2px;"><?php echo number_format($retenciones, 2, '.', ''); ?></td>
								<td style="padding: 2px;"><?php echo number_format($saldo, 2, '.', ''); ?></td>
								<td style="padding: 2px;"><?php echo $total_dias; ?></td>
								<td style="padding: 2px;"><?php echo $nombre_vendedor; ?></td>
								<td style="padding: 2px;" class="text-right">
									<a class='btn btn-info btn-xs' title='Detalle factura' onclick="detalle_factura('<?php echo $id_encabezado; ?>')" data-toggle="modal" data-target="#detalleDocumento"><i class="glyphicon glyphicon-list"></i> </a>
									<a class='btn btn-info btn-xs' title="Detalles de cobros" onclick="detalle_cobranza('<?php echo $id_encabezado; ?>')" data-toggle="modal" data-target="#detalleCobranza"><span class="glyphicon glyphicon-log-in"></span></a>
									<a class='btn btn-info btn-xs' title='Enviar cuenta individual por mail' onclick="enviar_cxc_mail_individual('<?php echo $email; ?>','<?php echo $id_saldo; ?>')" data-toggle="modal" data-target="#EnviarDocumentosMail"><i class="glyphicon glyphicon-envelope"></i> </a>
								</td>
							</tr>
						<?php
						}
						?>
						<tr class="info">
							<th style="padding: 2px;" colspan="7">Total por cobrar:</th>
							<th style="padding: 2px;"><?php echo number_format($total_saldo, 2, '.', ''); ?></th>
							<th style="padding: 2px;" colspan="2" class="text-right">Enviar todo
								<a class='btn btn-info btn-xs' title='Enviar cuenta total por mail' onclick="enviar_cxc_mail_todos('<?php echo $email; ?>','<?php echo $id_cliente; ?>')" data-toggle="modal" data-target="#EnviarDocumentosMail"><i class="glyphicon glyphicon-envelope"></i> </a>
								<?php
								if ($count_whatsapp >= 1) {
								?>
									<a class='btn btn-success btn-xs' title='Enviar cuenta total por whatsapp' onclick="enviar_cxc_whatsapp('<?php echo $ruc_empresa; ?>', '<?php echo $telefono; ?>', '<?php echo $mensaje; ?>')" data-toggle="modal" data-target="#EnviarDocumentosWhatsapp"><img src="../image/whatsapp.png" alt="Logo" width="15px"> </a>
								<?php
								}
								?>
							</th>
						</tr>
					</table>
				</div>
			</div>
		<?php
		}
		?>
		<!-- para recibos indidual por cliente-->

		<?php
		$total_cliente_recibo = saldo_recibo_por_cliente($con, $ruc_empresa, $hasta, $id_cliente);
		if ($total_cliente_recibo > 0) {
			$id_cliente_id = "RV" . $id_cliente;
		?>
			<div class="panel panel-info">
				<div class="table-responsive">
					<table class="table table-hover">
						<tr class="info">
							<th style="padding: 2px;">Fecha</th>
							<th style="padding: 2px;">Recibo</th>
							<th style="padding: 2px;">Total</th>
							<th style="padding: 2px;">Abonos</th>
							<th style="padding: 2px;">Saldo</th>
							<th style="padding: 2px;">Días</th>
							<th style="padding: 2px;">Asesor</th>
						</tr>
						<?php
						$recibos_individuales = recibos_del_cliente($con, $ruc_empresa, $hasta, $id_cliente);
						$total_saldo_recibos = 0;
						while ($detalle = mysqli_fetch_array($recibos_individuales)) {
							$id_encabezado = $detalle['id_encabezado_recibo'];
							$id_documento = "RV" . $id_encabezado;
							$fecha_documento = $detalle['fecha_recibo'];
							$numero_documento = $detalle['serie_recibo'] . "-" . str_pad($detalle['secuencial_recibo'], 9, "000000000", STR_PAD_LEFT);
							$total_recibo = $detalle['total_recibo'];
							$fecha_vencimiento = date_create($fecha_documento);
							$diferencia_dias = date_diff($fecha_hoy, $fecha_vencimiento);
							$total_dias = $diferencia_dias->format('%a');

							$vendedor = vendedores_recibos($con, $id_encabezado);

							$suma_abonos_recibo = abonos_cliente_recibo($con, $ruc_empresa, $hasta, $id_documento);
							$saldo = $total_recibo - $suma_abonos_recibo;
							$total_saldo_recibos += $saldo;
							if ($saldo > 0) {
						?>
								<tr>
									<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha_documento)); ?></td>
									<td style="padding: 2px;"><a href="../pdf/pdf_recibo_venta.php?id_documento=<?php echo base64_encode($id_encabezado) ?>&action=recibo_venta_a4" class='btn btn-default btn-xs' title='Ver' target="_blank"><?php echo $numero_documento; ?></i> </a></td>
									<td style="padding: 2px;"><?php echo number_format($total_recibo, 2, '.', ''); ?></td>
									<td style="padding: 2px;"><?php echo number_format($suma_abonos_recibo, 2, '.', ''); ?></td>
									<td style="padding: 2px;"><?php echo number_format($saldo, 2, '.', ''); ?></td>
									<td style="padding: 2px;"><?php echo $total_dias; ?></td>
									<td style="padding: 2px;"><?php echo $vendedor; ?></td>
								</tr>
						<?php
							}
						}
						?>
						<tr class="info">
							<th style="padding: 2px;" colspan="4">Total por cobrar:</th>
							<th style="padding: 2px;"><?php echo number_format($total_saldo_recibos, 2, '.', ''); ?></th>
							<th style="padding: 2px;" colspan="2"></th>
						</tr>
					</table>
				</div>
			</div>
<?php
		} //fin de recibo por cliente
	} // fin de condicion de cliente
} //fin de action geenerar informe

function saldo_total_recibos($con, $hasta, $ruc_empresa)
{

	$todos_los_clientes = clientes_recibos($con, $hasta, $ruc_empresa, $id_cliente = '', $vendedor = '');
	$saldo = 0;
	foreach ($todos_los_clientes as $valor) {
		$saldo += saldo_recibo_por_cliente($con, $ruc_empresa, $hasta, $valor['id']);
	}

	return $saldo;
}

function clientes_recibos($con, $hasta, $ruc_empresa, $id_cliente, $vendedor)
{
	if ($id_cliente > 0) {
		$condicion_cliente = " and id=" . $id_cliente;
	} else {
		$condicion_cliente = " ";
	}

	if ($vendedor > 0) {
		$condicion_vendedor = " and id_encabezado_recibo IN (SELECT id_recibo FROM vendedores_recibos WHERE id_vendedor=" . $vendedor . ")";
	} else {
		$condicion_vendedor = " ";
	}

	$busca_clientes_recibos = mysqli_query($con, "SELECT * FROM clientes 
	WHERE id IN (SELECT id_cliente FROM encabezado_recibo 
	WHERE ruc_empresa='" . $ruc_empresa . "' and DATE_FORMAT(fecha_recibo, '%Y/%m/%d') <= '" . date("Y/m/d", strtotime($hasta)) . "' and status !='2' $condicion_vendedor) 
	and ruc_empresa = '" . $ruc_empresa . "' $condicion_cliente order by nombre asc");
	return $busca_clientes_recibos;
}


function saldo_recibo_por_cliente($con, $ruc_empresa, $hasta, $id_cliente_recibo)
{

	$sql_cliente_ingresos = mysqli_query($con, "SELECT DISTINCT(id_encabezado_recibo) AS id_encabezado_recibo FROM encabezado_recibo WHERE ruc_empresa = '" . $ruc_empresa . "' 
	and id_cliente ='" . $id_cliente_recibo . "' and status !='2' and DATE_FORMAT(fecha_recibo, '%Y/%m/%d') <= '" . date("Y/m/d", strtotime($hasta)) . "'");
	$suma_ingresos_cliente = 0;
	foreach ($sql_cliente_ingresos as $id_registro) {
		$id_encabezado_recibo = "RV" . $id_registro['id_encabezado_recibo'];
		$sql_suma_ingresos_cliente = mysqli_query($con, "SELECT sum(round(detie.valor_ing_egr,2)) as suma_ingresos_cliente 
	FROM detalle_ingresos_egresos as detie INNER JOIN ingresos_egresos as ing_egr 
	ON ing_egr.codigo_documento=detie.codigo_documento WHERE ing_egr.ruc_empresa = '" . $ruc_empresa . "' 
	and detie.estado ='OK' and detie.tipo_documento='INGRESO' and detie.tipo_ing_egr='CCXRC' 
	and DATE_FORMAT(ing_egr.fecha_ing_egr, '%Y/%m/%d') <= '" . date("Y/m/d", strtotime($hasta)) . "' and detie.codigo_documento_cv ='" . $id_encabezado_recibo . "' 
	group by detie.codigo_documento_cv");

		$row_total_ingresos_cliente = mysqli_fetch_array($sql_suma_ingresos_cliente);
		$suma_ingresos_cliente += isset($row_total_ingresos_cliente['suma_ingresos_cliente']) ? $row_total_ingresos_cliente['suma_ingresos_cliente'] : 0;
	}

	$sql_suma_cliente = mysqli_query($con, "SELECT sum(round(total_recibo,2)) as saldo 
	FROM encabezado_recibo WHERE ruc_empresa = '" . $ruc_empresa . "'
	and DATE_FORMAT(fecha_recibo, '%Y/%m/%d') <= '" . date("Y/m/d", strtotime($hasta)) . "' and id_cliente='" . $id_cliente_recibo . "' 
	and status !='2' group by id_cliente");

	$row_total_cliente = mysqli_fetch_array($sql_suma_cliente);
	$total_saldo_cliente = isset($row_total_cliente['saldo']) ? $row_total_cliente['saldo'] : 0;
	$total_cliente = number_format($total_saldo_cliente - $suma_ingresos_cliente, 2, '.', '');
	return $total_cliente;
}


function recibos_del_cliente($con, $ruc_empresa, $hasta, $id_cliente_recibo)
{
	$recibos_individuales = mysqli_query($con, "SELECT * FROM encabezado_recibo as enc INNER JOIN clientes as cli ON 
	cli.id=enc.id_cliente WHERE enc.ruc_empresa = '" . $ruc_empresa . "'
	and DATE_FORMAT(enc.fecha_recibo, '%Y/%m/%d') <= '" . date("Y/m/d", strtotime($hasta)) . "' and enc.id_cliente='" . $id_cliente_recibo . "' 
	and enc.status !='2' order by enc.fecha_recibo asc ");
	return $recibos_individuales;
}

function vendedores_recibos($con, $id_encabezado)
{
	$datos_vendedor = mysqli_query($con, "SELECT * FROM vendedores as ven INNER JOIN vendedores_recibos as ven_ven ON ven_ven.id_vendedor=ven.id_vendedor WHERE ven_ven.id_recibo= '" . $id_encabezado . "' ");
	$detalle_vendedor = mysqli_fetch_array($datos_vendedor);
	$vendedor = isset($detalle_vendedor['nombre']) ? $detalle_vendedor['nombre'] : "";
	return $vendedor;
}


function abonos_cliente_recibo($con, $ruc_empresa, $hasta, $id_documento)
{
	$sql_suma_ingresos_recibo = mysqli_query($con, "SELECT sum(detie.valor_ing_egr) as abonos 
FROM detalle_ingresos_egresos as detie INNER JOIN ingresos_egresos as ing_egr 
ON ing_egr.codigo_documento = detie.codigo_documento WHERE ing_egr.ruc_empresa = '" . $ruc_empresa . "' 
and detie.estado ='OK' and detie.tipo_documento='INGRESO' and detie.tipo_ing_egr='CCXRC' 
and DATE_FORMAT(ing_egr.fecha_ing_egr, '%Y/%m/%d') <= '" . date("Y/m/d", strtotime($hasta)) . "' and detie.codigo_documento_cv='" . $id_documento . "' 
group by detie.codigo_documento_cv ");
	$row_total_ingresos_recibo = mysqli_fetch_array($sql_suma_ingresos_recibo);
	$suma_abonos_recibo = isset($row_total_ingresos_recibo['abonos']) ? $row_total_ingresos_recibo['abonos'] : 0;
	return $suma_abonos_recibo;
}


function usuario_recibo($con, $id_usuario)
{
	$datos_usuario = mysqli_query($con, "SELECT * FROM usuarios WHERE id= '" . $id_usuario . "' ");
	$row_usuario = mysqli_fetch_array($datos_usuario);
	$usuario = $row_usuario['nombre'];
	return $usuario;
}
?>