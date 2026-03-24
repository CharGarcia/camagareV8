<?php
/* Connect To Database*/
include("../conexiones/conectalogin.php");
require_once("../helpers/helpers.php");
$con = conenta_login();
session_start();
ini_set('date.timezone', 'America/Guayaquil');
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_empresa = $_SESSION['id_empresa'];
$id_usuario = $_SESSION['id_usuario'];
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

//balance supercias eri
if ($action == 'ERI') {
	$desde = mysqli_real_escape_string($con, (strip_tags($_REQUEST['fecha_desde'], ENT_QUOTES)));
	$hasta = mysqli_real_escape_string($con, (strip_tags($_REQUEST['fecha_hasta'], ENT_QUOTES)));
	echo control_errores($con, $ruc_empresa, $desde, $hasta, 'pantalla');
	echo generar_supercias_eri($con, $ruc_empresa, $id_usuario, $desde, $hasta);
	$sql_update_ingresos = mysqli_query($con, "UPDATE balances_tmp SET valor=valor*-1 WHERE ruc_empresa = '" . $ruc_empresa . "' and mid(codigo_cuenta,1,1)='4' ");
	$archivo = generar_txt_eri($con, $ruc_empresa); ?>
	<div class="row">
		<div class="col-md-3">
			Resumen
			<div class="panel panel-success">
				<div class="table-responsive">
					<table class="table table-hover">
						<tr class="success">
							<th style="padding: 2px;">Código ERI</th>
							<th style="padding: 2px; text-align: right">Total</th>
						</tr>
						<?php
						$sql_detalle_balance = mysqli_query($con, "SELECT tmp.id_balance as id, tmp.codigo_cuenta as codigo_esf, round(sum(tmp.valor),2) as valor FROM balances_tmp as tmp WHERE tmp.ruc_empresa = '" . $ruc_empresa . "' and tmp.id_usuario='" . $id_usuario . "'  group by tmp.codigo_cuenta order by tmp.codigo_cuenta asc "); //group by codigo_cuenta  
						while ($row_detalle_balance = mysqli_fetch_array($sql_detalle_balance)) {
							$id = $row_detalle_balance['id'];
							$codigo_esf = $row_detalle_balance['codigo_esf'] == null ? "Sin código" : $row_detalle_balance['codigo_esf'];
							$valor = $row_detalle_balance['valor'];
							if ($valor != 0) {
						?>
								<tr>
									<td style="padding: 2px;"><a class='btn btn-default btn-xs' data-toggle="collapse" data-parent="#acordioneri" href="#<?php echo $id; ?>"><?php echo $codigo_esf; ?></a></td>
									<td style="padding: 2px; text-align: right"><?php echo number_format($valor, 2, '.', ','); ?></td>
								</tr>
						<?php
							}
						}
						?>
						<tr>
							<td colspan="2" style="padding: 2px;" class="text-center">
								<a title="Generar archivo txt" class="btn btn-info btn-sm" href="<?php echo $archivo ?>" download><span class="glyphicon glyphicon-download-alt"></span> Generar txt</a>
						</tr>
					</table>

				</div>
			</div>
		</div>
		<div class="col-md-9">
			Detalle de todas las cuentas
			<div class="panel-group" id="acordioneri">
				<?php
				$sql_detalle_balance_supercias = mysqli_query($con, "SELECT tmp.id_balance as id, tmp.codigo_cuenta as codigo_esf, round(sum(tmp.valor),2) as valor FROM balances_tmp as tmp WHERE tmp.ruc_empresa = '" . $ruc_empresa . "' and tmp.id_usuario='" . $id_usuario . "' group by tmp.codigo_cuenta order by tmp.codigo_cuenta asc ");
				while ($row_balance_supercias = mysqli_fetch_array($sql_detalle_balance_supercias)) {
					$id = $row_balance_supercias['id'];
					$codigo = $row_balance_supercias['codigo_esf'];
					$codigo_esf = $row_balance_supercias['codigo_esf'] == null ? "Sin_código" : $row_balance_supercias['codigo_esf'];
					$valor = $row_balance_supercias['valor'];

					$sql_detalle_cuentas_supercias = mysqli_query($con, "SELECT * FROM supercias_eri WHERE codigo = '" . $codigo . "' ");
					$row_supercias = mysqli_fetch_array($sql_detalle_cuentas_supercias);
					$cuenta_supercias = isset($row_supercias['cuenta']) ? $row_supercias['cuenta'] : "Sin cuenta asignada";
					if ($valor != 0) {
				?>
						<div class="panel panel-info">
							<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#acordioneri" href="#<?php echo $id; ?>"><span class="caret"></span> <b>Código:</b> <?php echo $codigo_esf; ?> <b>Cuenta:</b> <?php echo $cuenta_supercias; ?> <b>Total:</b> <?php echo $valor; ?></a>
							<div id="<?php echo $id; ?>" class="panel-collapse collapse">

								<div class="table-responsive">
									<table class="table table-hover">
										<tr class="info">
											<th style="padding: 2px;">Código ERI</th>
											<th style="padding: 2px;">Código Cuenta</th>
											<th style="padding: 2px;">Nombre Cuenta</th>
											<th style="padding: 2px;">Total</th>
										</tr>
										<?php
										$sql_detalle_balance = mysqli_query($con, "SELECT plan.id_cuenta as id_cuenta, tmp.nombre_cuenta as nombre_cuenta, tmp.codigo_cuenta as codigo_supercias, round(tmp.valor,2) as valor, plan.codigo_cuenta as codigo_cuenta FROM balances_tmp as tmp INNER JOIN plan_cuentas as plan ON plan.id_cuenta=tmp.id_balance WHERE tmp.ruc_empresa = '" . $ruc_empresa . "' and tmp.id_usuario='" . $id_usuario . "' and tmp.codigo_cuenta='" . $codigo . "' ");
										while ($row_detalle_balance = mysqli_fetch_array($sql_detalle_balance)) {
											$id_cuenta = $row_detalle_balance['id_cuenta'];
											$nombre_cuenta = strtoupper($row_detalle_balance['nombre_cuenta']);
											$codigo_cuenta = strtoupper($row_detalle_balance['codigo_cuenta']);
											$codigo_supercias_detalle = $row_detalle_balance['codigo_supercias'] == null ? "Sin código" : $row_detalle_balance['codigo_supercias'];
											$valor_detalle = $row_detalle_balance['valor'];
											if ($valor_detalle != 0) {
										?>
												<tr>
													<td style="padding: 2px;"><?php echo $codigo_supercias_detalle; ?></td>
													<td style="padding: 2px;"><a class='btn btn-info btn-xs' title='Editar cuenta' onclick="obtener_datos_editar_cuenta('<?php echo $id_cuenta; ?>');" data-toggle="modal" data-target="#EditarCuentaContable"><?php echo $codigo_cuenta; ?></a></td>
													<td style="padding: 2px;"><?php echo $nombre_cuenta; ?></td>
													<td style="padding: 2px;"><?php echo number_format($valor_detalle, 2, '.', ','); ?></td>
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
		</div>
	</div><?php
		}

		//balance supercias esf
		if ($action == 'ESF') {
			$desde = mysqli_real_escape_string($con, (strip_tags($_REQUEST['fecha_desde'], ENT_QUOTES)));
			$hasta = mysqli_real_escape_string($con, (strip_tags($_REQUEST['fecha_hasta'], ENT_QUOTES)));
			echo control_errores($con, $ruc_empresa, $desde, $hasta, 'pantalla');
			echo generar_supercias_esf($con, $ruc_empresa, $id_usuario, $desde, $hasta);
			$sql_update_pasivo = mysqli_query($con, "UPDATE balances_tmp SET valor=valor*-1 WHERE ruc_empresa = '" . $ruc_empresa . "' and mid(codigo_cuenta,1,1)='2' ");
			$sql_update_patrimonio = mysqli_query($con, "UPDATE balances_tmp SET valor=valor*-1 WHERE ruc_empresa = '" . $ruc_empresa . "' and mid(codigo_cuenta,1,1)='3' ");
			//$sql_update_ingresos = mysqli_query($con, "UPDATE balances_tmp SET valor=valor*-1 WHERE ruc_empresa = '" . $ruc_empresa . "' and mid(codigo_cuenta,1,1)='4' ");
			//$sql_update = mysqli_query($con, "UPDATE balances_tmp as bal_tmp INNER JOIN plan_cuentas as plan ON bal_tmp.codigo_cuenta=plan.codigo_cuenta SET bal_tmp.id_balance=plan.id_cuenta, bal_tmp.codigo_cuenta = plan.codigo_supercias WHERE plan.ruc_empresa = '" . $ruc_empresa . "' and bal_tmp.ruc_empresa = '" . $ruc_empresa . "'");
			$archivo = generar_txt_esf($con, $ruc_empresa);
			?>
	<div class="row">
		<div class="col-md-3">
			Resumen
			<div class="panel panel-success">
				<div class="table-responsive">
					<table class="table table-hover">
						<tr class="success">
							<th style="padding: 2px;">Código ESF</th>
							<th style="padding: 2px; text-align: right">Total</th>
						</tr>
						<?php
						$sql_detalle_balance = mysqli_query($con, "SELECT tmp.id_balance as id, tmp.codigo_cuenta as codigo_esf, round(sum(tmp.valor),2) as valor FROM balances_tmp as tmp WHERE tmp.ruc_empresa = '" . $ruc_empresa . "' and tmp.id_usuario='" . $id_usuario . "'  group by tmp.codigo_cuenta order by tmp.codigo_cuenta asc "); //group by codigo_cuenta  
						while ($row_detalle_balance = mysqli_fetch_array($sql_detalle_balance)) {
							$id = $row_detalle_balance['id'];
							$codigo_esf = $row_detalle_balance['codigo_esf'] == null ? "Sin código" : $row_detalle_balance['codigo_esf'];
							$valor = $row_detalle_balance['valor'];
							if ($valor != 0) {
						?>
								<tr>
									<td style="padding: 2px;"><a class='btn btn-default btn-xs' data-toggle="collapse" data-parent="#acordionesf" href="#<?php echo $id; ?>"><?php echo $codigo_esf; ?></a></td>
									<td style="padding: 2px; text-align: right"><?php echo number_format($valor, 2, '.', ','); ?></td>
								</tr>
						<?php
							}
						}
						?>
						<tr>
							<td colspan="2" style="padding: 2px;" class="text-center">
								<a title="Generar archivo txt" class="btn btn-info btn-sm" href="<?php echo $archivo ?>" download><span class="glyphicon glyphicon-download-alt"></span> Generar txt</a>
						</tr>
					</table>

				</div>
			</div>
		</div>
		<div class="col-md-9">
			Detalle de todas las cuentas
			<div class="panel-group" id="acordionesf">
				<?php
				$sql_detalle_balance_supercias = mysqli_query($con, "SELECT tmp.id_balance as id, tmp.codigo_cuenta as codigo_esf, round(sum(tmp.valor),2) as valor FROM balances_tmp as tmp WHERE tmp.ruc_empresa = '" . $ruc_empresa . "' and tmp.id_usuario='" . $id_usuario . "' group by tmp.codigo_cuenta order by tmp.codigo_cuenta asc ");
				while ($row_balance_supercias = mysqli_fetch_array($sql_detalle_balance_supercias)) {
					$id = $row_balance_supercias['id'];
					$codigo = $row_balance_supercias['codigo_esf'];
					$codigo_esf = $row_balance_supercias['codigo_esf'] == null ? "Sin_código" : $row_balance_supercias['codigo_esf'];
					$valor = $row_balance_supercias['valor'];

					$sql_detalle_cuentas_supercias = mysqli_query($con, "SELECT * FROM supercias_esf WHERE codigo = '" . $codigo . "' ");
					$row_supercias = mysqli_fetch_array($sql_detalle_cuentas_supercias);
					$cuenta_supercias = isset($row_supercias['cuenta']) ? $row_supercias['cuenta'] : "Sin cuenta asignada";
					if ($valor != 0) {
				?>
						<div class="panel panel-info">
							<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#acordionesf" href="#<?php echo $id; ?>"><span class="caret"></span> <b>Código:</b> <?php echo $codigo_esf; ?> <b>Cuenta:</b> <?php echo $cuenta_supercias; ?> <b>Total:</b> <?php echo $valor; ?></a>
							<div id="<?php echo $id; ?>" class="panel-collapse collapse">

								<div class="table-responsive">
									<table class="table table-hover">
										<tr class="info">
											<th style="padding: 2px;">Código ESF</th>
											<th style="padding: 2px;">Código Cuenta</th>
											<th style="padding: 2px;">Nombre Cuenta</th>
											<th style="padding: 2px;">Total</th>
										</tr>
										<?php
										$sql_detalle_balance = mysqli_query($con, "SELECT plan.id_cuenta as id_cuenta, tmp.nombre_cuenta as nombre_cuenta, tmp.codigo_cuenta as codigo_supercias, round(tmp.valor,2) as valor, plan.codigo_cuenta as codigo_cuenta FROM balances_tmp as tmp INNER JOIN plan_cuentas as plan ON plan.id_cuenta=tmp.id_balance WHERE tmp.ruc_empresa = '" . $ruc_empresa . "' and tmp.id_usuario='" . $id_usuario . "' and tmp.codigo_cuenta='" . $codigo . "' ");
										while ($row_detalle_balance = mysqli_fetch_array($sql_detalle_balance)) {
											$id_cuenta = $row_detalle_balance['id_cuenta'];
											$nombre_cuenta = strtoupper($row_detalle_balance['nombre_cuenta']);
											$codigo_cuenta = strtoupper($row_detalle_balance['codigo_cuenta']);
											$codigo_supercias_detalle = $row_detalle_balance['codigo_supercias'] == null ? "Sin código" : $row_detalle_balance['codigo_supercias'];
											$valor_detalle = $row_detalle_balance['valor'];
											if ($valor_detalle != 0) {
										?>
												<tr>
													<td style="padding: 2px;"><?php echo $codigo_supercias_detalle; ?></td>
													<td style="padding: 2px;"><a class='btn btn-info btn-xs' title='Editar cuenta' onclick="obtener_datos_editar_cuenta('<?php echo $id_cuenta; ?>');" data-toggle="modal" data-target="#EditarCuentaContable"><?php echo $codigo_cuenta; ?></a></td>
													<td style="padding: 2px;"><?php echo $nombre_cuenta; ?></td>
													<td style="padding: 2px;"><?php echo number_format($valor_detalle, 2, '.', ','); ?></td>
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
		</div>
	</div><?php
		}

		//balance de comprobacion
		if ($action == '3') {
			$desde = mysqli_real_escape_string($con, (strip_tags($_REQUEST['fecha_desde'], ENT_QUOTES)));
			$hasta = mysqli_real_escape_string($con, (strip_tags($_REQUEST['fecha_hasta'], ENT_QUOTES)));
			echo control_errores($con, $ruc_empresa, $desde, $hasta, 'pantalla'); ?>
	<div class="table-responsive">
		<div class="panel panel-info">
			<table class="table table-hover">
				<tr class="info">
					<th style="padding: 2px;" rowspan="2">Código</th>
					<th style="padding: 2px; text-align:center;" rowspan="2">Cuenta</th>
					<th style="padding: 2px; text-align:center;" colspan="2">Sumas</th>
					<th style="padding: 2px; text-align:center;" colspan="2">Saldos</th>
				</tr>
				<tr class="info">
					<th style="padding: 2px; text-align:center;">Debe</th>
					<th style="padding: 2px; text-align:center;">Haber</th>
					<th style="padding: 2px; text-align:center;">Deudor</th>
					<th style="padding: 2px; text-align:center;">Acreedor</th>
				</tr>
				<?php
				$sql_detalle_diario = mysqli_query($con, "SELECT plan.codigo_cuenta as codigo_cuenta, plan.nombre_cuenta as nombre_cuenta, sum(det_dia.debe) as debe, sum(det_dia.haber) as haber FROM detalle_diario_contable as det_dia INNER JOIN encabezado_diario as enc_dia ON enc_dia.codigo_unico=det_dia.codigo_unico INNER JOIN plan_cuentas as plan ON plan.id_cuenta=det_dia.id_cuenta WHERE plan.ruc_empresa = '" . $ruc_empresa . "' and enc_dia.ruc_empresa = '" . $ruc_empresa . "' and det_dia.ruc_empresa = '" . $ruc_empresa . "' and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' and mid(plan.codigo_cuenta,1,1) >= '1' and mid(plan.codigo_cuenta,1,1) <= '6' and enc_dia.estado !='ANULADO' and plan.nivel_cuenta='5' group by plan.id_cuenta order by plan.codigo_cuenta asc");
				$suma_debe_cuenta = 0;
				$suma_haber_cuenta = 0;
				$suma_deudor_cuenta = 0;
				$suma_acreedor_cuenta = 0;
				while ($row_detalle_balance = mysqli_fetch_array($sql_detalle_diario)) {
					$codigo_cuenta = $row_detalle_balance['codigo_cuenta'];
					$nombre_cuenta = strtoupper($row_detalle_balance['nombre_cuenta']);
					$debe_cuenta = $row_detalle_balance['debe'];
					$haber_cuenta = $row_detalle_balance['haber'];
					$suma_debe_cuenta += $debe_cuenta;
					$suma_haber_cuenta += $haber_cuenta;
					$deudor_cuenta = $debe_cuenta > $haber_cuenta ? $debe_cuenta - $haber_cuenta : 0;
					$acreedor_cuenta = $haber_cuenta > $debe_cuenta ? $haber_cuenta - $debe_cuenta : 0;
					$suma_deudor_cuenta += $deudor_cuenta;
					$suma_acreedor_cuenta += $acreedor_cuenta;
				?>
					<tr>
						<td style="padding: 2px;"><?php echo $codigo_cuenta; ?></td>
						<td style="padding: 2px;"><?php echo $nombre_cuenta; ?></td>
						<td style="padding: 2px; text-align:right;"><?php echo number_format($debe_cuenta, 2, '.', ','); ?></td>
						<td style="padding: 2px; text-align:right;"><?php echo number_format($haber_cuenta, 2, '.', ','); ?></td>
						<td style="padding: 2px; text-align:right;"><?php echo number_format($deudor_cuenta, 2, '.', ','); ?></td>
						<td style="padding: 2px; text-align:right;"><?php echo number_format($acreedor_cuenta, 2, '.', ','); ?></td>
					</tr>
				<?php
				}

				?>
				<tr class="info">
					<td style="padding: 2px;  text-align:right;" colspan="2">Sumas</td>
					<td style="padding: 2px;  text-align:right;"><?php echo number_format($suma_debe_cuenta, 2, '.', ','); ?></td>
					<td style="padding: 2px;  text-align:right;"><?php echo number_format($suma_haber_cuenta, 2, '.', ','); ?></td>
					<td style="padding: 2px;  text-align:right;"><?php echo number_format($suma_deudor_cuenta, 2, '.', ','); ?></td>
					<td style="padding: 2px;  text-align:right;"><?php echo number_format($suma_acreedor_cuenta, 2, '.', ','); ?></td>
				</tr>
			</table>
		</div>
	</div><?php
		}

		//balance sri
		if ($action == 'sri') {
			$desde = mysqli_real_escape_string($con, (strip_tags($_REQUEST['fecha_desde'], ENT_QUOTES)));
			$hasta = mysqli_real_escape_string($con, (strip_tags($_REQUEST['fecha_hasta'], ENT_QUOTES)));
			$id_proyecto = mysqli_real_escape_string($con, (strip_tags($_REQUEST['id_proyecto'], ENT_QUOTES)));
			echo control_errores($con, $ruc_empresa, $desde, $hasta, 'pantalla');
			echo generar_balance($con, $ruc_empresa, $id_usuario, $desde, $hasta, '1', '6', $id_proyecto);
			$sql_delete = mysqli_query($con, "DELETE FROM balances_tmp WHERE ruc_empresa = '" . $ruc_empresa . "' and id_usuario='" . $id_usuario . "' and nivel_cuenta !='5'");
			$sql_update_pasivo = mysqli_query($con, "UPDATE balances_tmp SET valor=valor*-1 WHERE ruc_empresa = '" . $ruc_empresa . "' and mid(codigo_cuenta,1,1)='2' ");
			$sql_update_patrimonio = mysqli_query($con, "UPDATE balances_tmp SET valor=valor*-1 WHERE ruc_empresa = '" . $ruc_empresa . "' and mid(codigo_cuenta,1,1)='3' ");
			$sql_update_ingresos = mysqli_query($con, "UPDATE balances_tmp SET valor=valor*-1 WHERE ruc_empresa = '" . $ruc_empresa . "' and mid(codigo_cuenta,1,1)='4' ");
			$sql_update = mysqli_query($con, "UPDATE balances_tmp as bal_tmp INNER JOIN plan_cuentas as plan ON bal_tmp.codigo_cuenta=plan.codigo_cuenta SET bal_tmp.id_balance=plan.id_cuenta, bal_tmp.codigo_cuenta = plan.codigo_sri WHERE plan.ruc_empresa = '" . $ruc_empresa . "' and bal_tmp.ruc_empresa = '" . $ruc_empresa . "'");

			?>
	<div class="row">
		<div class="col-md-3">
			Resumen
			<div class="panel panel-success">
				<div class="table-responsive">
					<table class="table table-hover">
						<tr class="success">
							<th style="padding: 2px;">Código SRI</th>
							<th style="padding: 2px; text-align: right;">Total</th>
						</tr>
						<?php
						$sql_detalle_balance = mysqli_query($con, "SELECT tmp.id_balance as id, tmp.codigo_cuenta as codigo_sri, ROUND(SUM(tmp.valor), 2) as valor 
						FROM balances_tmp as tmp 
						WHERE tmp.ruc_empresa = '" . $ruc_empresa . "' 
						AND tmp.id_usuario='" . $id_usuario . "'  
						GROUP BY tmp.codigo_cuenta 
						ORDER BY CAST(tmp.codigo_cuenta AS UNSIGNED) ASC");

						$sql_ruc_contador = mysqli_query($con, "SELECT ruc_contador	FROM empresas WHERE ruc = '" . $ruc_empresa . "'");
						$row_ruc_contador = mysqli_fetch_array($sql_ruc_contador);
						$ruc_contador = $row_ruc_contador['ruc_contador'];
						//para crear el archivo xml
						$fila = "";
						$nombre = $ruc_empresa . "-formulario_101_sri.xml";
						$file = fopen("../xml/" . $nombre, "w") or die("Problemas en la creacion");
						$fila_encabezado = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><detallesDeclaracion>';
						$fila_cierre = '</detallesDeclaracion>';

						fwrite($file, $fila_encabezado);
						//hasta aqui crea el archivo xml

						while ($row_detalle_balance = mysqli_fetch_array($sql_detalle_balance)) {
							$id = $row_detalle_balance['id'];
							$codigo_sri = $row_detalle_balance['codigo_sri'] == null ? "Sin código" : $row_detalle_balance['codigo_sri'];
							$valor = $row_detalle_balance['valor'];
							$valor_xml = $row_detalle_balance['valor'] < 0 ? $row_detalle_balance['valor'] * -1 : $row_detalle_balance['valor'];
							if ($valor != 0) {
								$sql_concepto_sri = mysqli_query($con, "SELECT concepto	FROM plan_cuentas_sri WHERE casillero = '" . $codigo_sri . "'");
								$row_concepto_sri = mysqli_fetch_array($sql_concepto_sri);
								$concepto = $row_concepto_sri['concepto'];
								$fila = '<detalle concepto="' . $concepto . '">' . number_format($valor_xml, 2, '.', '') . '</detalle>' . "\r\n";


								fwrite($file, $fila);
						?>
								<tr>
									<td style="padding: 2px;"><a class='btn btn-default btn-xs' data-toggle="collapse" data-parent="#acordionsri" href="#<?php echo $id; ?>"><?php echo $codigo_sri; ?></a></td>
									<td style="padding: 2px; text-align: right;"><?php echo number_format($valor, 2, '.', ','); ?></td>
								</tr>
						<?php
							}
						}
						$fila_ruc_empresa = '<detalle concepto="80">' . $ruc_contador . '</detalle>';
						fwrite($file, $fila_ruc_empresa);
						fwrite($file, $fila_cierre);
						fclose($file);
						$archivo = '../xml/' . $nombre;
						?>
						<tr>
							<td colspan="2" style="padding: 2px;" class="text-center">
								<a title="Generar archivo xml" class="btn btn-info btn-sm" href="<?php echo $archivo ?>" download><span class="glyphicon glyphicon-download-alt"></span> Generar xml</a>
						</tr>
					</table>
				</div>
			</div>
		</div>
		<div class="col-md-9">
			Detalle de todas las cuentas
			<div class="panel-group" id="acordionsri">
				<?php
				$sql_detalle_balance_sri = mysqli_query($con, "SELECT tmp.id_balance as id, tmp.codigo_cuenta as codigo_sri, round(sum(tmp.valor),2) as valor FROM balances_tmp as tmp WHERE tmp.ruc_empresa = '" . $ruc_empresa . "' and tmp.id_usuario='" . $id_usuario . "'  group by tmp.codigo_cuenta order by tmp.codigo_cuenta asc "); //group by codigo_cuenta  
				while ($row_balance_sri = mysqli_fetch_array($sql_detalle_balance_sri)) {
					$id = $row_balance_sri['id'];
					$codigo = $row_balance_sri['codigo_sri'];
					$codigo_sri = $row_balance_sri['codigo_sri'] == null ? "Sin_código" : $row_balance_sri['codigo_sri'];
					$valor = $row_balance_sri['valor'];
					if ($valor != 0) {
				?>
						<div class="panel panel-info">
							<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#acordionsri" href="#<?php echo $id; ?>"><span class="caret"></span> <b>Código:</b> <?php echo $codigo_sri; ?> <b>Total:</b> <?php echo $valor; ?></a>
							<div id="<?php echo $id; ?>" class="panel-collapse collapse">

								<div class="table-responsive">
									<table class="table table-hover">
										<tr class="info">
											<th style="padding: 2px;">Código SRI</th>
											<th style="padding: 2px;">Código Cuenta</th>
											<th style="padding: 2px;">Nombre Cuenta</th>
											<th style="padding: 2px;">Total</th>
										</tr>
										<?php
										$sql_detalle_balance = mysqli_query($con, "SELECT plan.id_cuenta as id_cuenta, tmp.nombre_cuenta as nombre_cuenta, tmp.codigo_cuenta as codigo_sri, round(tmp.valor,2) as valor, plan.codigo_cuenta as codigo_cuenta FROM balances_tmp as tmp INNER JOIN plan_cuentas as plan ON plan.id_cuenta=tmp.id_balance WHERE tmp.ruc_empresa = '" . $ruc_empresa . "' and tmp.id_usuario='" . $id_usuario . "' and tmp.codigo_cuenta = '" . $codigo . "' "); //group by codigo_cuenta  
										while ($row_detalle_balance = mysqli_fetch_array($sql_detalle_balance)) {
											$id_cuenta = $row_detalle_balance['id_cuenta'];
											$nombre_cuenta = strtoupper($row_detalle_balance['nombre_cuenta']);
											$codigo_cuenta = strtoupper($row_detalle_balance['codigo_cuenta']);
											$codigo_sri_detalle = $row_detalle_balance['codigo_sri'] == null ? "Sin código" : $row_detalle_balance['codigo_sri'];
											$valor_detalle = $row_detalle_balance['valor'];
											if ($valor_detalle != 0) {
										?>
												<tr>
													<td style="padding: 2px;"><?php echo $codigo_sri_detalle; ?></td>
													<td style="padding: 2px;"><a class='btn btn-info btn-xs' title='Editar cuenta' onclick="obtener_datos_editar_cuenta('<?php echo $id_cuenta; ?>');" data-toggle="modal" data-target="#EditarCuentaContable"><?php echo $codigo_cuenta; ?></a></td>
													<td style="padding: 2px;"><?php echo $nombre_cuenta; ?></td>
													<td style="padding: 2px;"><?php echo number_format($valor_detalle, 2, '.', ','); ?></td>
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
		</div>
	</div><?php
		}

		//1 es balance general
		if ($action == '1') {
			$desde = mysqli_real_escape_string($con, (strip_tags($_REQUEST['fecha_desde'], ENT_QUOTES)));
			$hasta = mysqli_real_escape_string($con, (strip_tags($_REQUEST['fecha_hasta'], ENT_QUOTES)));
			$nivel = mysqli_real_escape_string($con, (strip_tags($_REQUEST['nivel'], ENT_QUOTES)));
			$id_proyecto = mysqli_real_escape_string($con, (strip_tags($_REQUEST['id_proyecto'], ENT_QUOTES)));
			echo control_errores($con, $ruc_empresa, $desde, $hasta, 'pantalla');
			if ($nivel == '0') {
				$nivel_cuenta = "";
			} else {
				$nivel_cuenta = " and nivel_cuenta = " . $nivel;
			}
			?>
	<div class="table-responsive">
		<div class="panel panel-info">
			<table class="table table-hover">
				<tr class="info">
					<th style="padding: 2px;">Código</th>
					<th style="padding: 2px;">Cuenta</th>
					<th style="padding: 2px;">Nivel 5</th>
					<th style="padding: 2px;">Nivel 4</th>
					<th style="padding: 2px;">Nivel 3</th>
					<th style="padding: 2px;">Nivel 2</th>
					<th style="padding: 2px;">Nivel 1</th>
				</tr>
				<?php
				$resumen_activo_pasivo_patrimonio = resumen_activo_pasivo_patrimonio($con, $ruc_empresa, $id_usuario, $desde, $hasta, $id_proyecto);
				$sql_detalle_balance = mysqli_query($con, "SELECT nivel_cuenta as nivel, codigo_cuenta as codigo_cuenta, 
				nombre_cuenta as nombre_cuenta, round(sum(valor),2) as valor 
				FROM balances_tmp WHERE ruc_empresa = '" . $ruc_empresa . "' $nivel_cuenta 
				group by codigo_cuenta, nivel_cuenta");
				while ($row_detalle_balance = mysqli_fetch_array($sql_detalle_balance)) {
					$codigo_cuenta = $row_detalle_balance['codigo_cuenta'];
					$nombre_cuenta = strtoupper($row_detalle_balance['nombre_cuenta']);
					$nivel = $row_detalle_balance['nivel'];
					$valor = $row_detalle_balance['valor'];
					if ($valor != 0) {
						if (substr($codigo_cuenta, 0, 1) == 1) {
							$valor = $valor;
						} else {
							$valor = $valor * -1;
						}
				?>
						<tr>
							<td style="padding: 2px;"><?php echo $codigo_cuenta; ?></td>
							<td style="padding: 2px;"><?php echo $nombre_cuenta; ?></td>
							<?php
							if ($nivel == 5) {
							?>
								<td style="padding: 2px;"><?php echo number_format($valor, 2, '.', ','); ?></td>
							<?php
							} else {
							?>
								<td style="padding: 2px;"></td>
							<?php
							}

							if ($nivel == 4) {
							?>
								<td style="padding: 2px;"><?php echo number_format($valor, 2, '.', ','); ?></td>
							<?php
							} else {
							?>
								<td style="padding: 2px;"></td>
							<?php
							}
							if ($nivel == 3) {
							?>
								<td style="padding: 2px;"><?php echo number_format($valor, 2, '.', ','); ?></td>
							<?php
							} else {
							?>
								<td style="padding: 2px;"></td>
							<?php
							}
							if ($nivel == 2) {
							?>
								<td style="padding: 2px;"><?php echo number_format($valor, 2, '.', ','); ?></td>
							<?php
							} else {
							?>
								<td style="padding: 2px;"></td>
							<?php
							}
							if ($nivel == 1) {
							?>
								<td style="padding: 2px;"><?php echo number_format($valor, 2, '.', ','); ?></td>
							<?php
							} else {
							?>
								<td style="padding: 2px;"></td>
							<?php
							}
							?>
						</tr>
				<?php
					}
				}
				$activo = $resumen_activo_pasivo_patrimonio['activo'];
				$pasivo = $resumen_activo_pasivo_patrimonio['pasivo'];
				$patrimonio = $resumen_activo_pasivo_patrimonio['patrimonio'];
				?>
				<tr class="info">
					<td style="padding: 2px;"></td>
					<td style="padding: 2px;">TOTAL ACTIVO </td>
					<td style="padding: 2px;"> <?php echo number_format($activo, 2, '.', ','); ?></td>
					<td style="padding: 2px;" colspan="4"></td>
				</tr>
				<tr class="info">
					<td style="padding: 2px;"></td>
					<td style="padding: 2px;">TOTAL PASIVO + PATRIMONIO</td>
					<td style="padding: 2px;"> <?php echo number_format($pasivo + $patrimonio, 2, '.', ','); ?></td>
					<td style="padding: 2px;" colspan="4"></td>
				</tr>
				<?php
				//para sacar la utilidad
				$resultado_utilidad = utilidad_perdida($con, $ruc_empresa, $id_usuario, $desde, $hasta, $id_proyecto);

				$suma_pasivo_patrimonio = $pasivo + $patrimonio;
				$resultado_diferencia = $activo - $suma_pasivo_patrimonio;

				if ($activo == $suma_pasivo_patrimonio) {
					$diferencias = "";
				} else {
					$diferencias = $resultado_diferencia == 0 ? "" : "Diferencia para ser registrada como pérdida o ganancia: " . number_format(abs($resultado_diferencia), 2, '.', ',');
				}

				$patente = patente($con, $ruc_empresa, $id_usuario, $desde, $hasta, $id_proyecto)['patente'];
				$unocincoxmil = patente($con, $ruc_empresa, $id_usuario, $desde, $hasta, $id_proyecto)['unocincoxmil'];

				?>
				<tr class="info">
					<td style="padding: 2px;"></td>
					<td style="padding: 2px;"><?php echo $resultado_utilidad['resultado']; ?></td>
					<td style="padding: 2px;"> <?php echo number_format($resultado_utilidad['valor'], 2, '.', ','); ?></td>
					<td style="padding: 2px;" colspan="4"></td>
				</tr>
				<tr class="danger">
					<td style="padding: 2px;" colspan="7" align="center"><b><?php echo $diferencias; ?></b></td>
				</tr>
				<tr class="info">
					<th style="padding: 2px;" colspan="2" align="left">*** Valores estimados de patente municipal y 1.5 x 1000 ***</b></th>
					<td style="padding: 2px;" colspan="5" align="center"></b></td>
				</tr>
				<tr class="warning">
					<td style="padding: 2px;">Patente</td>
					<td style="padding: 2px;"><?php echo number_format($patente, 2, '.', ''); ?></td>
					<td style="padding: 2px;" colspan="5">Activos - pasivos en base a la tabla del municipio</td>
				</tr>
				<tr class="warning">
					<td style="padding: 2px;">1.5 X 1000</td>
					<td style="padding: 2px;"><?php echo $unocincoxmil; ?></td>
					<td style="padding: 2px;" colspan="5"> Activos - pasivos corrientes - pasivos contingentes por 1.5 por mil</td>
				</tr>
				<tr class="warning">
					<td style="padding: 2px;">Total</td>
					<td style="padding: 2px;"><?php echo number_format($patente + $unocincoxmil, 2, '.', ''); ?></td>
					<td style="padding: 2px;" colspan="5"></td>
				</tr>
			</table>
		</div>
	</div>
<?php
		}

		//para calcular la patente y 1.5 x 1000
		function patente($con, $ruc_empresa, $id_usuario, $desde, $hasta, $id_proyecto)
		{
			$patente = 0;
			generar_balance($con, $ruc_empresa, $id_usuario, $desde, $hasta, '1', '3', $id_proyecto);

			$sql_activo_patente = mysqli_query($con, "SELECT * FROM balances_tmp 
			WHERE codigo_cuenta = '1' and ruc_empresa = '" . $ruc_empresa . "'");
			$row_activo_patente = mysqli_fetch_array($sql_activo_patente);
			$valor_activo_patente = isset($row_activo_patente['valor']) ? $row_activo_patente['valor'] : 0;

			$sql_pasivo_patente = mysqli_query($con, "SELECT * FROM balances_tmp 
			WHERE codigo_cuenta = '2' and ruc_empresa = '" . $ruc_empresa . "'");
			$row_pasivo_patente = mysqli_fetch_array($sql_pasivo_patente);
			$valor_pasivo_patente = isset($row_pasivo_patente['valor']) ? $row_pasivo_patente['valor'] * -1 : 0;

			$sql_pasivo_corriente = mysqli_query($con, "SELECT * FROM balances_tmp WHERE codigo_cuenta = '2.1' and ruc_empresa = '" . $ruc_empresa . "'");
			$row_pasivo_corriente = mysqli_fetch_array($sql_pasivo_corriente);
			$valor_pasivo_corriente = isset($row_pasivo_corriente['valor']) ? $row_pasivo_corriente['valor'] * -1 : 0;
			$unocincoxmil = number_format((($valor_activo_patente - $valor_pasivo_corriente) * 1.5) / 1000, 2, '.', '');
			$base_imponible_municipio = number_format($valor_activo_patente - $valor_pasivo_patente, 2, '.', '');
			if ($base_imponible_municipio > 50000.01) {
				$patente = (($base_imponible_municipio - 50000.01) * (2 / 100)) + 700;
			}
			if ($base_imponible_municipio <= 50000 && $base_imponible_municipio >= 40000.01) {
				$patente = (($base_imponible_municipio - 40000.01) * (1.80 / 100)) + 520;
			}
			if ($base_imponible_municipio <= 40000 && $base_imponible_municipio >= 30000.01) {
				$patente = (($base_imponible_municipio - 30000.01) * (1.60 / 100)) + 360;
			}
			if ($base_imponible_municipio <= 30000 && $base_imponible_municipio >= 20000.01) {
				$patente = (($base_imponible_municipio - 20000.01) * (1.40 / 100)) + 200;
			}
			if ($base_imponible_municipio <= 20000 && $base_imponible_municipio >= 10000.01) {
				$patente = (($base_imponible_municipio - 10000.01) * (1.20 / 100)) + 100;
			}
			if ($base_imponible_municipio <= 10000 && $base_imponible_municipio >= 0) {
				$patente = (($base_imponible_municipio - 0) * (1 / 100)) + 0;
			}
			//}
			return array('patente' => $patente, 'unocincoxmil' => $unocincoxmil);
		}

		// es estado financiero por periodos
		if ($action == '1P') {
			$desde = mysqli_real_escape_string($con, (strip_tags($_REQUEST['fecha_desde'], ENT_QUOTES)));
			$hasta = mysqli_real_escape_string($con, (strip_tags($_REQUEST['fecha_hasta'], ENT_QUOTES)));
			$id_proyecto = mysqli_real_escape_string($con, (strip_tags($_REQUEST['id_proyecto'], ENT_QUOTES)));
			$nivel = 5;
			echo control_errores($con, $ruc_empresa, $desde, $hasta, 'pantalla');
			$resultados = generar_balance_periodos($con, $ruc_empresa, $desde, $hasta, 1, 3, $id_proyecto);
			$titulosColumnas = array('Código', 'Código SRI', 'Cuenta', 'Nivel 5', 'Nivel 4', 'Nivel 3', 'Nivel 2', 'Nivel 1'); ?>
	<div class="table-responsive">
		<div class="panel panel-info">
			<table class="table table-hover">
				<tr class="info">
					<th style="padding: 2px;">Código</th>
					<th style="padding: 2px;">Cuenta</th>
					<?php
					$fechaInicio = new DateTime(date("Y/m/d", strtotime($desde)));
					$fechaFin = new DateTime(date("Y/m/d", strtotime($hasta)));
					for ($fecha = $fechaInicio; $fecha <= $fechaFin; $fecha->modify('+1 month')) {
						$mes = substr(obtenerNombreMes($fecha->format('m')), 0, 3);
						$ano = $fecha->format('y');
						$mes_ano = $mes . "-" . $ano;
					?>
						<th style="padding: 2px;" class="text-right"><?php echo $mes_ano; ?></th>
					<?php
					}
					?>
					<th style="padding: 2px;" class="text-right">Resultado</th>
				</tr>
				<?php
				// Genera las filas con los datos

				$fechaInicio = new DateTime(date("Y/m/d", strtotime($desde)));
				$fechaFin = new DateTime(date("Y/m/d", strtotime($hasta)));
				for ($fecha = $fechaInicio; $fecha <= $fechaFin; $fecha->modify('+1 month')) {
					$mes = $fecha->format('m');
					$ano = $fecha->format('y');
					$mes_ano = $mes . "-" . $ano;
					$sumaColumna[$mes_ano] = 0;
				}

				foreach ($resultados as $codigo => $datosCuentas) {
					foreach ($datosCuentas as $cuenta => $datosPeriodo) {
				?>
						<tr>
							<td style="padding: 2px;"><?php echo $codigo; ?></td>
							<td style="padding: 2px;"><?php echo strtoupper($cuenta); ?></td>
							<?php
							// Genera las celdas con los datos de cada mes
							$total_fila = 0;
							$fechaInicio = new DateTime(date("Y/m/d", strtotime($desde)));
							$fechaFin = new DateTime(date("Y/m/d", strtotime($hasta)));

							for ($fecha = $fechaInicio; $fecha <= $fechaFin; $fecha->modify('+1 month')) {
								$mes = $fecha->format('m');
								$ano = $fecha->format('y');
								$mes_ano = $mes . "-" . $ano;
								$saldo = isset($datosPeriodo[$ano][$mes]['saldo']) ? $datosPeriodo[$ano][$mes]['saldo'] : 0;

								if (substr($codigo, 0, 1) > 1) {
									$saldo = $saldo * -1;
								} else {
									$saldo = $saldo;
								}

								if (substr($codigo, 0, 1) > 1) {
									if (isset($sumaColumna[$mes_ano])) {
										$sumaColumna[$mes_ano] -= $saldo;
									}
								} else {
									$sumaColumna[$mes_ano] += $saldo;
								}

								$total_fila += $saldo;
								$saldo = $saldo == 0 ? "" : number_format($saldo, 2, '.', ',');
							?>
								<td style="padding: 2px;" class="text-right"><?php echo $saldo; ?></td>
							<?php
							}
							?>
							<td style="padding: 2px;" class="text-right"><?php echo number_format($total_fila, 2, '.', ','); ?></td>
						</tr>
				<?php
					}
				}
				?>
				<tr class="info">
					<td style="padding: 2px;" colspan="2">Activo - Pasivo - Patrimonio</td>
					<?php
					$fechaInicio = new DateTime(date("Y/m/d", strtotime($desde)));
					$fechaFin = new DateTime(date("Y/m/d", strtotime($hasta)));
					for ($fecha = $fechaInicio; $fecha <= $fechaFin; $fecha->modify('+1 month')) {
						$mes = $fecha->format('m');
						$ano = $fecha->format('y');
						$mes_ano = $mes . "-" . $ano;
						$total_columna = isset($sumaColumna[$mes_ano]) ? $sumaColumna[$mes_ano] : 0;
					?>
						<td style="padding: 2px;" class="text-right"><?php echo number_format($total_columna, 2, '.', ','); ?></td>
					<?php
					}
					$resultado_utilidad = utilidad_perdida($con, $ruc_empresa, $id_usuario, $desde, $hasta, $id_proyecto);
					?>
					<td style="padding: 2px;" class="text-right"> <?php echo number_format($resultado_utilidad['valor'], 2, '.', ','); ?></td>
				</tr>
			</table>
		</div>
	</div><?php
		}

		// es estado de resultados por periodos
		if ($action == '2P') {
			$desde = mysqli_real_escape_string($con, (strip_tags($_REQUEST['fecha_desde'], ENT_QUOTES)));
			$hasta = mysqli_real_escape_string($con, (strip_tags($_REQUEST['fecha_hasta'], ENT_QUOTES)));
			$id_proyecto = mysqli_real_escape_string($con, (strip_tags($_REQUEST['id_proyecto'], ENT_QUOTES)));
			$nivel = 5;

			echo control_errores($con, $ruc_empresa, $desde, $hasta, 'pantalla');
			$resultados = generar_balance_periodos($con, $ruc_empresa, $desde, $hasta, 4, 6, $id_proyecto);
			?>
	<div class="table-responsive">
		<div class="panel panel-info">
			<table class="table table-hover">
				<tr class="info">
					<th style="padding: 2px;">Código</th>
					<th style="padding: 2px;">Cuenta</th>
					<?php
					$fechaInicio = new DateTime(date("Y/m/d", strtotime($desde)));
					$fechaFin = new DateTime(date("Y/m/d", strtotime($hasta)));
					for ($fecha = $fechaInicio; $fecha <= $fechaFin; $fecha->modify('+1 month')) {
						$mes = substr(obtenerNombreMes($fecha->format('m')), 0, 3);
						$ano = $fecha->format('y');
						$mes_ano = $mes . "-" . $ano;
					?>
						<th style="padding: 2px;" class="text-right"><?php echo $mes_ano; ?></th>
					<?php
					}
					?>
					<th style="padding: 2px;" class="text-right">Resultado</th>
				</tr>
				<?php
				// Genera las filas con los datos
				$fechaInicio = new DateTime(date("Y/m/d", strtotime($desde)));
				$fechaFin = new DateTime(date("Y/m/d", strtotime($hasta)));
				for ($fecha = $fechaInicio; $fecha <= $fechaFin; $fecha->modify('+1 month')) {
					$mes = $fecha->format('m');
					$ano = $fecha->format('y');
					$mes_ano = $mes . "-" . $ano;
					$sumaColumna[$mes_ano] = 0;
				}

				foreach ($resultados as $codigo => $datosCuentas) {
					foreach ($datosCuentas as $cuenta => $datosPeriodo) {
				?>
						<tr>
							<td style="padding: 2px;"><?php echo $codigo; ?></td>
							<td style="padding: 2px;"><?php echo strtoupper($cuenta); ?></td>
							<?php
							// Genera las celdas con los datos de cada mes
							$total_fila = 0;
							$fechaInicio = new DateTime(date("Y/m/d", strtotime($desde)));
							$fechaFin = new DateTime(date("Y/m/d", strtotime($hasta)));
							for ($fecha = $fechaInicio; $fecha <= $fechaFin; $fecha->modify('+1 month')) {
								$mes = $fecha->format('m');
								$ano = $fecha->format('y');
								$mes_ano = $mes . "-" . $ano;
								$saldo = isset($datosPeriodo[$ano][$mes]['saldo']) ? $datosPeriodo[$ano][$mes]['saldo'] : 0;
								if (substr($codigo, 0, 1) == 4) {
									$saldo = $saldo * -1;
								} else {
									$saldo = $saldo;
								}

								if (substr($codigo, 0, 1) == 4) {
									if (isset($sumaColumna[$mes_ano])) {
										$sumaColumna[$mes_ano] += $saldo;
									}
								} else {
									$sumaColumna[$mes_ano] -= $saldo;
								}

								$total_fila += $saldo;
								$saldo = $saldo == 0 ? "" : number_format($saldo, 2, '.', ',');
							?>
								<td style="padding: 2px;" class="text-right"><?php echo $saldo; ?></td>
							<?php
							}
							?>
							<td style="padding: 2px;" class="text-right"><?php echo number_format($total_fila, 2, '.', ','); ?></td>
						</tr>
				<?php
					}
				}
				?>
				<tr class="info">
					<td style="padding: 2px;" colspan="2">Utilidad</td>
					<?php
					$fechaInicio = new DateTime(date("Y/m/d", strtotime($desde)));
					$fechaFin = new DateTime(date("Y/m/d", strtotime($hasta)));
					for ($fecha = $fechaInicio; $fecha <= $fechaFin; $fecha->modify('+1 month')) {
						$mes = $fecha->format('m');
						$ano = $fecha->format('y');
						$mes_ano = $mes . "-" . $ano;
						$total_columna = isset($sumaColumna[$mes_ano]) ? $sumaColumna[$mes_ano] : 0;
					?>
						<td style="padding: 2px;" class="text-right"><?php echo number_format($total_columna, 2, '.', ','); ?></td>
					<?php
					}
					$resultado_utilidad = utilidad_perdida($con, $ruc_empresa, $id_usuario, $desde, $hasta, $id_proyecto);
					?>
					<td style="padding: 2px;" class="text-right"> <?php echo number_format($resultado_utilidad['valor'], 2, '.', ','); ?></td>
				</tr>
				<?php
				$resultado_utilidad = utilidad_perdida($con, $ruc_empresa, $id_usuario, $desde, $hasta, $id_proyecto);
				?>
			</table>
		</div>
	</div>
<?php
		}

		// es estado de resultados presupuestado
		if ($action == '2PP') {
			$desde = mysqli_real_escape_string($con, (strip_tags($_REQUEST['fecha_desde'], ENT_QUOTES)));
			$hasta = mysqli_real_escape_string($con, (strip_tags($_REQUEST['fecha_hasta'], ENT_QUOTES)));
			$id_proyecto = mysqli_real_escape_string($con, (strip_tags($_REQUEST['id_proyecto'], ENT_QUOTES)));
			$nivel = 5;
			$resultados = generar_resultados_presupuestado($con, $ruc_empresa, $desde, $hasta, $id_proyecto);
?>
	<div class="table-responsive">
		<div class="panel panel-info">
			<table class="table table-hover">
				<tr class="info">
					<th style="padding: 2px;">Código</th>
					<th style="padding: 2px;">Cuenta</th>
					<th style="padding: 2px;">Presupuesto</th>
					<?php
					$fechaInicio = new DateTime(date("Y/m/d", strtotime($desde)));
					$fechaFin = new DateTime(date("Y/m/d", strtotime($hasta)));
					for ($fecha = $fechaInicio; $fecha <= $fechaFin; $fecha->modify('+1 month')) {
						$mes = substr(obtenerNombreMes($fecha->format('m')), 0, 3);
						$ano = $fecha->format('y');
						$mes_ano = $mes . "-" . $ano;
					?>
						<th style="padding: 2px;" class="text-right"><?php echo $mes_ano; ?></th>
					<?php
					}
					?>
					<th style="padding: 2px;" class="text-right">Ejecutado</th>
					<th style="padding: 2px;" class="text-right">Por_ejecutar</th>
				</tr>
				<?php
				// Genera las filas con los datos
				$suma_por_ejecutar = 0;
				$suma_ejecutado = 0;
				$valor_presupuesto = 0;
				$suma_presupuesto = 0;

				$fechaInicio = new DateTime(date("Y/m/d", strtotime($desde)));
				$fechaFin = new DateTime(date("Y/m/d", strtotime($hasta)));
				for ($fecha = $fechaInicio; $fecha <= $fechaFin; $fecha->modify('+1 month')) {
					$mes = $fecha->format('m');
					$ano = $fecha->format('y');
					$mes_ano = $mes . "-" . $ano;
					$sumaColumna[$mes_ano] = 0;
				}
				foreach ($resultados as $codigo => $datosCuentas) {
				?>
					<tr>
						<td style="padding: 2px;"><?php echo $codigo; ?></td>
						<td style="padding: 2px;"><?php echo strtoupper($datosCuentas['cuenta']); ?></td>
						<td style="padding: 2px;"><?php echo number_format($datosCuentas['valor'], 2, '.', ','); ?></td>
						<?php
						// Genera las celdas con los datos de cada mes
						$valor_presupuesto = $datosCuentas['valor'];
						$total_fila = 0;
						$fechaInicio = new DateTime(date("Y/m/d", strtotime($desde)));
						$fechaFin = new DateTime(date("Y/m/d", strtotime($hasta)));
						for ($fecha = $fechaInicio; $fecha <= $fechaFin; $fecha->modify('+1 month')) {
							$mes = $fecha->format('m');
							$ano = $fecha->format('y');
							$mes_ano = $mes . "-" . $ano;
							$sql_saldos = mysqli_query($con, "SELECT round(sum(det.debe-haber),2) as saldo 
								FROM detalle_diario_contable as det INNER JOIN encabezado_diario as enc 
								ON enc.codigo_unico=det.codigo_unico WHERE enc.ruc_empresa='" . $ruc_empresa . "' and 
								DATE_FORMAT(enc.fecha_asiento, '%m') = '" . $mes . "'
								and DATE_FORMAT(enc.fecha_asiento, '%y') = '" . $ano . "' and det.id_cuenta='" . $datosCuentas['id_cuenta'] . "' group by det.id_cuenta");
							$row_saldos = mysqli_fetch_array($sql_saldos);

							$saldo = isset($row_saldos['saldo']) ? $row_saldos['saldo'] : 0;
							if (substr($codigo, 0, 1) == 4) {
								$saldo = $saldo * -1;
							} else {
								$saldo = $saldo;
							}

							if (isset($sumaColumna[$mes_ano])) {
								$sumaColumna[$mes_ano] += $saldo;
							}

							$total_fila += $saldo;
							$saldo = $saldo == 0 ? "" : number_format($saldo, 2, '.', ',');
						?>
							<td style="padding: 2px;" class="text-right"><?php echo $saldo; ?></td>
						<?php
						}
						?>
						<td style="padding: 2px;" class="text-right"><?php echo number_format($total_fila, 2, '.', ','); ?></td>
						<td style="padding: 2px;" class="text-right"><?php echo number_format($valor_presupuesto - $total_fila, 2, '.', ','); ?></td>
					</tr>
				<?php
					$suma_ejecutado += $total_fila;
					$suma_por_ejecutar += $valor_presupuesto - $total_fila;
					$suma_presupuesto += $valor_presupuesto;
				}
				?>
				<tr class="info">
					<td style="padding: 2px;" colspan="2">Totales</td>
					<td style="padding: 2px;"><?php echo number_format($suma_presupuesto, 2, '.', ','); ?></td>
					<?php
					$fechaInicio = new DateTime(date("Y/m/d", strtotime($desde)));
					$fechaFin = new DateTime(date("Y/m/d", strtotime($hasta)));
					for ($fecha = $fechaInicio; $fecha <= $fechaFin; $fecha->modify('+1 month')) {
						$mes = $fecha->format('m');
						$ano = $fecha->format('y');
						$mes_ano = $mes . "-" . $ano;
						$total_columna = isset($sumaColumna[$mes_ano]) ? $sumaColumna[$mes_ano] : 0;
					?>
						<td style="padding: 2px;" class="text-right"><?php echo number_format($total_columna, 2, '.', ','); ?></td>
					<?php
					}
					?>
					<td style="padding: 2px;" class="text-right"> <?php echo number_format($suma_ejecutado, 2, '.', ','); ?></td>
					<td style="padding: 2px;" class="text-right"> <?php echo number_format($suma_por_ejecutar, 2, '.', ','); ?></td>
				</tr>
				<?php
				$resultado_utilidad = utilidad_perdida($con, $ruc_empresa, $id_usuario, $desde, $hasta, $id_proyecto);
				?>
			</table>
		</div>
	</div>
<?php
		}

		//estado de resultados general
		if ($action == '2') {
			$desde = mysqli_real_escape_string($con, (strip_tags($_REQUEST['fecha_desde'], ENT_QUOTES)));
			$hasta = mysqli_real_escape_string($con, (strip_tags($_REQUEST['fecha_hasta'], ENT_QUOTES)));
			$nivel = mysqli_real_escape_string($con, (strip_tags($_REQUEST['nivel'], ENT_QUOTES)));
			$id_proyecto = mysqli_real_escape_string($con, (strip_tags($_REQUEST['id_proyecto'], ENT_QUOTES)));
			echo control_errores($con, $ruc_empresa, $desde, $hasta, 'pantalla');
			if ($nivel == '0') {
				$nivel_cuenta = "";
			} else {
				$nivel_cuenta = " and nivel_cuenta = " . $nivel;
			}
?>
	<div class="table-responsive">
		<div class="panel panel-info">
			<table class="table table-hover">
				<tr class="info">
					<th style="padding: 2px;">Código</th>
					<th style="padding: 2px;">Cuenta</th>
					<th style="padding: 2px;">Nivel 5</th>
					<th style="padding: 2px;">Nivel 4</th>
					<th style="padding: 2px;">Nivel 3</th>
					<th style="padding: 2px;">Nivel 2</th>
					<th style="padding: 2px;">Nivel 1</th>
				</tr>
				<?php
				echo generar_balance($con, $ruc_empresa, $id_usuario, $desde, $hasta, '4', '4', $id_proyecto);
				$sql_detalle_balance = mysqli_query($con, "SELECT nivel_cuenta as nivel, codigo_cuenta as codigo_cuenta, nombre_cuenta as nombre_cuenta, sum(valor) as valor  FROM balances_tmp WHERE ruc_empresa = '" . $ruc_empresa . "' $nivel_cuenta group by codigo_cuenta");
				while ($row_detalle_balance = mysqli_fetch_array($sql_detalle_balance)) {
					$codigo_cuenta = $row_detalle_balance['codigo_cuenta'];
					$nombre_cuenta = strtoupper($row_detalle_balance['nombre_cuenta']);
					$valor = $row_detalle_balance['valor'];
					if (substr($codigo_cuenta, 0, 1) == 4) {
						$valor = $valor * -1;
					}
					$nivel = $row_detalle_balance['nivel'];

				?>
					<tr>
						<td style="padding: 2px;"><?php echo $codigo_cuenta; ?></td>
						<td style="padding: 2px;"><?php echo $nombre_cuenta; ?></td>
						<?php
						if ($nivel == 5) {
						?>
							<td style="padding: 2px;"><?php echo number_format($valor, 2, '.', ','); ?></td>
						<?php
						} else {
						?>
							<td style="padding: 2px;"></td>
						<?php
						}

						if ($nivel == 4) {
						?>
							<td style="padding: 2px;"><?php echo number_format($valor, 2, '.', ','); ?></td>
						<?php
						} else {
						?>
							<td style="padding: 2px;"></td>
						<?php
						}


						if ($nivel == 3) {
						?>
							<td style="padding: 2px;"><?php echo number_format($valor, 2, '.', ','); ?></td>
						<?php
						} else {
						?>
							<td style="padding: 2px;"></td>
						<?php
						}

						if ($nivel == 2) {
						?>
							<td style="padding: 2px;"><?php echo number_format($valor, 2, '.', ','); ?></td>
						<?php
						} else {
						?>
							<td style="padding: 2px;"></td>
						<?php
						}

						if ($nivel == 1) {
						?>
							<td style="padding: 2px;"><?php echo number_format($valor, 2, '.', ','); ?></td>
						<?php
						} else {
						?>
							<td style="padding: 2px;"></td>
						<?php
						}
						?>
					</tr>
				<?php
				}
				//costos y gastos
				echo generar_balance($con, $ruc_empresa, $id_usuario, $desde, $hasta, '5', '6', $id_proyecto);
				$sql_detalle_balance = mysqli_query($con, "SELECT nivel_cuenta as nivel, codigo_cuenta as codigo_cuenta, nombre_cuenta as nombre_cuenta, sum(valor) as valor  FROM balances_tmp WHERE ruc_empresa = '" . $ruc_empresa . "' $nivel_cuenta group by codigo_cuenta");
				while ($row_detalle_balance = mysqli_fetch_array($sql_detalle_balance)) {
					$codigo_cuenta = $row_detalle_balance['codigo_cuenta'];
					$nombre_cuenta = strtoupper($row_detalle_balance['nombre_cuenta']);
					$valor = $row_detalle_balance['valor'];
					if (substr($codigo_cuenta, 0, 1) == 4) {
						$valor = $valor * -1;
					}
					$nivel = $row_detalle_balance['nivel'];

				?>
					<tr>
						<td style="padding: 2px;"><?php echo $codigo_cuenta; ?></td>
						<td style="padding: 2px;"><?php echo $nombre_cuenta; ?></td>
						<?php
						if ($nivel == 5) {
						?>
							<td style="padding: 2px;"><?php echo number_format($valor, 2, '.', ','); ?></td>
						<?php
						} else {
						?>
							<td style="padding: 2px;"></td>
						<?php
						}

						if ($nivel == 4) {
						?>
							<td style="padding: 2px;"><?php echo number_format($valor, 2, '.', ','); ?></td>
						<?php
						} else {
						?>
							<td style="padding: 2px;"></td>
						<?php
						}


						if ($nivel == 3) {
						?>
							<td style="padding: 2px;"><?php echo number_format($valor, 2, '.', ','); ?></td>
						<?php
						} else {
						?>
							<td style="padding: 2px;"></td>
						<?php
						}

						if ($nivel == 2) {
						?>
							<td style="padding: 2px;"><?php echo number_format($valor, 2, '.', ','); ?></td>
						<?php
						} else {
						?>
							<td style="padding: 2px;"></td>
						<?php
						}

						if ($nivel == 1) {
						?>
							<td style="padding: 2px;"><?php echo number_format($valor, 2, '.', ','); ?></td>
						<?php
						} else {
						?>
							<td style="padding: 2px;"></td>
						<?php
						}
						?>
					</tr>
				<?php
				}
				//para sacar la utilidad
				$resultado_utilidad = utilidad_perdida($con, $ruc_empresa, $id_usuario, $desde, $hasta, $id_proyecto);

				?>
				<tr class="info">
					<td style="padding: 2px;" colspan="2"> <?php echo $resultado_utilidad['resultado']; ?></td>
					<td style="padding: 2px;"> <?php echo number_format($resultado_utilidad['valor'], 2, '.', ','); ?></td>
					<td style="padding: 2px;" colspan="4"></td>
				</tr>
			</table>
		</div>
	</div>
	<?php
		}

		//para hacer mayor general
		if ($action == '4') {
			$desde = mysqli_real_escape_string($con, (strip_tags($_REQUEST['fecha_desde'], ENT_QUOTES)));
			$hasta = mysqli_real_escape_string($con, (strip_tags($_REQUEST['fecha_hasta'], ENT_QUOTES)));
			$cuenta = mysqli_real_escape_string($con, (strip_tags($_REQUEST['cuenta'], ENT_QUOTES)));
			$sql_cuentas = mysqli_query($con, "SELECT * FROM plan_cuentas WHERE id_cuenta = '" . $cuenta . "' "); //  
			$row_cuentas = mysqli_fetch_array($sql_cuentas);
			$codigo_cuenta = isset($row_cuentas['codigo_cuenta']) ? $row_cuentas['codigo_cuenta'] : "";
			$nombre_cuenta = strtoupper(isset($row_cuentas['nombre_cuenta']) ? $row_cuentas['nombre_cuenta'] : "");
			//si tiene una cuenta seleccionada
			if (!empty($cuenta)) {
				$saldo_cuenta = saldo_cuenta($con, $ruc_empresa, $desde, $hasta, $cuenta);
	?>

		<div class="table-responsive">
			<div class="panel panel-success">
				<div class="panel-heading" style="padding: 2px;">
					<h5>
						<p align="left"><b>Códido:</b> <?php echo $codigo_cuenta; ?> <b>Cuenta:</b> <?php echo $nombre_cuenta; ?> <b>Saldo:</b> <?php echo $saldo_cuenta; ?></p>
					</h5>
				</div>
				<table class="table table-hover">
					<tr class="info">
						<th style="padding: 2px;">Fecha</th>
						<th style="padding: 2px;">Detalle</th>
						<th style="padding: 2px;">Asiento</th>
						<th style="padding: 2px;">Tipo</th>
						<th style="padding: 2px;">Debe</th>
						<th style="padding: 2px;">Haber</th>
						<th style="padding: 2px;">Saldo</th>
					</tr>
					<?php
					//para cuentas individuales
					$saldo = 0;
					$sql_detalle_diario = mysqli_query($con, "SELECT enc_dia.id_documento as id_documento, enc_dia.codigo_unico as codigo_unico, 
			enc_dia.id_diario as id_diario, enc_dia.concepto_general as concepto_general, enc_dia.tipo as tipo, 
			enc_dia.id_diario as asiento, enc_dia.fecha_asiento as fecha, det_dia.debe as debe, det_dia.haber as haber, 
			det_dia.detalle_item as detalle, enc_dia.codigo_unico_bloque as codigo_unico_bloque
			FROM encabezado_diario as enc_dia 
			INNER JOIN detalle_diario_contable as det_dia ON 
			enc_dia.codigo_unico=det_dia.codigo_unico and enc_dia.codigo_unico_bloque=det_dia.codigo_unico_bloque and enc_dia.codigo_unico_bloque=det_dia.codigo_unico_bloque 
			INNER JOIN plan_cuentas as plan ON 
			plan.id_cuenta=det_dia.id_cuenta WHERE enc_dia.ruc_empresa = '" . $ruc_empresa . "' and 
			DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
			and '" . date("Y/m/d", strtotime($hasta)) . "' and plan.id_cuenta = '" . $cuenta . "' 
			and enc_dia.estado !='ANULADO' order by enc_dia.fecha_asiento asc "); // 

					while ($row_detalle_diario = mysqli_fetch_array($sql_detalle_diario)) {
						$id_diario = $row_detalle_diario['id_diario'];
						$id_documento = $row_detalle_diario['id_documento'];
						$codigo_unico = $row_detalle_diario['codigo_unico'];
						$codigo_unico_bloque = $row_detalle_diario['codigo_unico_bloque'];
						$fecha = date('d-m-Y', strtotime($row_detalle_diario['fecha']));
						$concepto_general = $row_detalle_diario['concepto_general'];
						$detalle = $row_detalle_diario['detalle'];
						$debe = $row_detalle_diario['debe'];
						$haber = $row_detalle_diario['haber'];
						$saldo += $debe - $haber;
						$asiento = $row_detalle_diario['asiento'];
						$tipo = $row_detalle_diario['tipo'];
					?>
						<input type="hidden" value="<?php echo $asiento; ?>" id="numero_asiento<?php echo $id_diario; ?>">
						<input type="hidden" value="<?php echo $concepto_general; ?>" id="mod_concepto_general<?php echo $id_diario; ?>">
						<input type="hidden" value="<?php echo $fecha; ?>" id="mod_fecha_asiento<?php echo $id_diario; ?>">
						<input type="hidden" value="<?php echo $codigo_unico; ?>" id="mod_codigo_unico<?php echo $id_diario; ?>">
						<input type="hidden" value="<?php echo $codigo_unico_bloque; ?>" id="mod_codigo_unico_bloque<?php echo $id_diario; ?>">
						<input type="hidden" value="<?php echo $id_documento; ?>" id="mod_id_documento<?php echo $id_diario; ?>">
						<input type="hidden" value="<?php echo $tipo; ?>" id="mod_tipo<?php echo $id_diario; ?>">
						<tr>
							<td style="padding: 2px;"><?php echo $fecha; ?></td>
							<td style="padding: 2px;"><?php echo $detalle; ?></td>
							<td style="padding: 2px;">
								<a class='btn btn-info btn-xs' title='Editar asiento' onclick="obtener_datos('<?php echo $id_diario; ?>');" data-toggle="modal" data-target="#NuevoDiarioContable"><i class="glyphicon glyphicon-edit"></i> <?php echo $asiento; ?></a>
							</td>
							<td style="padding: 2px;"><?php echo $tipo; ?></td>
							<td style="padding: 2px;"><?php echo number_format($debe, 2, '.', ','); ?></td>
							<td style="padding: 2px;"><?php echo number_format($haber, 2, '.', ','); ?></td>
							<td style="padding: 2px;"><?php echo number_format($saldo, 2, '.', ','); ?></td>
						</tr>
					<?php
					}
					?>
				</table>
			</div>
		</div>
	<?php
			} else {
				//para todas las cuentas
	?>
		<div class="panel-group" id="accordiones">
			<?php
				$sql_detalle_cuentas = mysqli_query($con, "SELECT plan.codigo_cuenta as codigo_cuenta, plan.nombre_cuenta as nombre_cuenta, plan.id_cuenta as ide_cuenta FROM plan_cuentas as plan WHERE plan.ruc_empresa = '" . $ruc_empresa . "' and plan.nivel_cuenta='5' ");
				while ($row_detalle_cuentas = mysqli_fetch_array($sql_detalle_cuentas)) {
					$ide_cuenta = $row_detalle_cuentas['ide_cuenta'];
					$codigo_cuenta = $row_detalle_cuentas['codigo_cuenta'];
					$nombre_cuenta = strtoupper($row_detalle_cuentas['nombre_cuenta']);
					$sql_registros = mysqli_query($con, "SELECT * FROM encabezado_diario as enc_dia INNER JOIN detalle_diario_contable as det_dia ON enc_dia.codigo_unico=det_dia.codigo_unico INNER JOIN plan_cuentas as plan ON plan.id_cuenta=det_dia.id_cuenta WHERE enc_dia.ruc_empresa = '" . $ruc_empresa . "' and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' and det_dia.id_cuenta = '" . $ide_cuenta . "' and enc_dia.estado !='ANULADO'");
					$registros = mysqli_num_rows($sql_registros);
					if ($registros > 0) {
						$saldo_cuenta = saldo_cuenta($con, $ruc_empresa, $desde, $hasta, $ide_cuenta);
			?>
					<div class="panel panel-success">
						<a class="list-group-item list-group-item-success" data-toggle="collapse" data-parent="#accordiones" href="#<?php echo $ide_cuenta; ?>"><span class="caret"></span> <b>Códido:</b> <?php echo $codigo_cuenta; ?> <b>Cuenta:</b> <?php echo $nombre_cuenta; ?> <b>Saldo:</b> <?php echo $saldo_cuenta; ?></a>
						<div id="<?php echo $ide_cuenta; ?>" class="panel-collapse collapse">
							<div class="table-responsive">
								<table class="table table-hover">
									<tr class="info">
										<th style="padding: 2px;">Fecha</th>
										<th style="padding: 2px;">Detalle</th>
										<th style="padding: 2px;">Asiento</th>
										<th style="padding: 2px;">Tipo</th>
										<th style="padding: 2px;">Debe</th>
										<th style="padding: 2px;">Haber</th>
										<th style="padding: 2px;">Saldo</th>
									</tr>
									<?php
									$saldo = 0;
									$sql_detalle_diario = mysqli_query($con, "SELECT enc_dia.tipo as tipo, enc_dia.id_diario as asiento, 
			enc_dia.fecha_asiento as fecha, det_dia.debe as debe, det_dia.haber as haber, 
			det_dia.detalle_item as detalle, enc_dia.codigo_unico as codigo_unico, enc_dia.id_diario as id_diario, 
			enc_dia.concepto_general as concepto_general, enc_dia.id_documento as id_documento, enc_dia.codigo_unico_bloque as codigo_unico_bloque
			FROM encabezado_diario as enc_dia 
			INNER JOIN detalle_diario_contable as det_dia ON enc_dia.codigo_unico=det_dia.codigo_unico and enc_dia.codigo_unico_bloque=det_dia.codigo_unico_bloque
			INNER JOIN plan_cuentas as plan ON plan.id_cuenta=det_dia.id_cuenta 
			WHERE enc_dia.ruc_empresa = '" . $ruc_empresa . "' and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
			and '" . date("Y/m/d", strtotime($hasta)) . "' and plan.id_cuenta = '" . $ide_cuenta . "' 
			and enc_dia.estado !='ANULADO' order by enc_dia.fecha_asiento asc");
									while ($row_detalle_diario = mysqli_fetch_array($sql_detalle_diario)) {
										$detalle = $row_detalle_diario['detalle'];
										$debe = $row_detalle_diario['debe'];
										$haber = $row_detalle_diario['haber'];
										$saldo += $debe - $haber;
										$asiento = $row_detalle_diario['asiento'];
										$tipo = $row_detalle_diario['tipo'];
										$id_diario = $row_detalle_diario['id_diario'];
										$id_documento = $row_detalle_diario['id_documento'];
										$codigo_unico = $row_detalle_diario['codigo_unico'];
										$codigo_unico_bloque = $row_detalle_diario['codigo_unico_bloque'];
										$fecha = date('d-m-Y', strtotime($row_detalle_diario['fecha']));
										$concepto_general = $row_detalle_diario['concepto_general'];
									?>
										<input type="hidden" value="<?php echo $asiento; ?>" id="numero_asiento<?php echo $id_diario; ?>">
										<input type="hidden" value="<?php echo $concepto_general; ?>" id="mod_concepto_general<?php echo $id_diario; ?>">
										<input type="hidden" value="<?php echo $fecha; ?>" id="mod_fecha_asiento<?php echo $id_diario; ?>">
										<input type="hidden" value="<?php echo $codigo_unico; ?>" id="mod_codigo_unico<?php echo $id_diario; ?>">
										<input type="hidden" value="<?php echo $codigo_unico_bloque; ?>" id="mod_codigo_unico_bloque<?php echo $id_diario; ?>">
										<input type="hidden" value="<?php echo $id_documento; ?>" id="mod_id_documento<?php echo $id_diario; ?>">
										<input type="hidden" value="<?php echo $tipo; ?>" id="mod_tipo<?php echo $id_diario; ?>">
										<tr>
											<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha)); ?></td>
											<td style="padding: 2px;"><?php echo $detalle; ?></td>
											<td style="padding: 2px;">
												<a class='btn btn-info btn-xs' title='Editar asiento' onclick="obtener_datos('<?php echo $id_diario; ?>');" data-toggle="modal" data-target="#NuevoDiarioContable"><i class="glyphicon glyphicon-edit"></i> <?php echo $asiento; ?></a>
											</td>
											<td style="padding: 2px;"><?php echo $tipo; ?></td>
											<td style="padding: 2px;"><?php echo number_format($debe, 2, '.', ','); ?></td>
											<td style="padding: 2px;"><?php echo number_format($haber, 2, '.', ','); ?></td>
											<td style="padding: 2px;"><?php echo number_format($saldo, 2, '.', ','); ?></td>
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
	<?php
			}
		}

		//para hacer mayor de clientes
		if ($action == '5') {
			$desde = mysqli_real_escape_string($con, (strip_tags($_REQUEST['fecha_desde'], ENT_QUOTES)));
			$hasta = mysqli_real_escape_string($con, (strip_tags($_REQUEST['fecha_hasta'], ENT_QUOTES)));
			$cuenta = mysqli_real_escape_string($con, (strip_tags($_REQUEST['cuenta'], ENT_QUOTES)));
			$pro_cli = mysqli_real_escape_string($con, (strip_tags($_REQUEST['pro_cli'], ENT_QUOTES)));
			$sql_clientes = mysqli_query($con, "SELECT * FROM clientes WHERE id = '" . $pro_cli . "' "); //  
			$row_clientes = mysqli_fetch_array($sql_clientes);
			$nombre_cliente = isset($row_clientes['nombre']) ? $row_clientes['nombre'] : "";

			$sql_cuentas = mysqli_query($con, "SELECT * FROM plan_cuentas WHERE id_cuenta = '" . $cuenta . "' "); //  
			$row_cuentas = mysqli_fetch_array($sql_cuentas);
			$codigo_cuenta = isset($row_cuentas['codigo_cuenta']) ? $row_cuentas['codigo_cuenta'] : "";
			$nombre_cuenta = isset($row_cuentas['nombre_cuenta']) ? strtoupper($row_cuentas['nombre_cuenta']) : "";
			//para un cliente y una cuenta
			if (!empty($pro_cli) && !empty($cuenta)) {
				//$saldo_cuenta = saldo_cuenta($con, $ruc_empresa, $desde, $hasta, $cuenta);
	?>
		<div class="table-responsive">
			<div class="panel panel-success">
				<div class="panel-heading" style="padding: 2px;">
					<h5>
						<p align="left"><b>Cliente: </b><?php echo $nombre_cliente; ?> <b>Código: </b><?php echo $codigo_cuenta; ?> <b>Cuenta: </b><?php echo $nombre_cuenta; ?></p>
					</h5>
				</div>
				<table class="table table-hover">
					<tr class="info">
						<th style="padding: 2px;">Fecha</th>
						<th style="padding: 2px;">Detalle</th>
						<th style="padding: 2px;">Asiento</th>
						<th style="padding: 2px;">Tipo</th>
						<th style="padding: 2px;">Debe</th>
						<th style="padding: 2px;">Haber</th>
						<th style="padding: 2px;">Saldo</th>
					</tr>
					<?php
					//para todas las cuentas
					$saldo = 0;
					$sql_detalle_diario = mysqli_query($con, "SELECT enc_dia.tipo as tipo, enc_dia.id_diario as asiento, 
			enc_dia.fecha_asiento as fecha, det_dia.debe as debe, det_dia.haber as haber, det_dia.detalle_item as detalle, 
			plan.codigo_cuenta as codigo_cuenta, plan.nombre_cuenta as nombre_cuenta, enc_dia.codigo_unico as codigo_unico, 
			enc_dia.id_diario as id_diario, enc_dia.concepto_general as concepto_general, enc_dia.id_documento as id_documento, enc_dia.codigo_unico_bloque as codigo_unico_bloque
			FROM encabezado_diario as enc_dia 
			INNER JOIN detalle_diario_contable as det_dia ON enc_dia.codigo_unico=det_dia.codigo_unico and enc_dia.codigo_unico_bloque=det_dia.codigo_unico_bloque
			INNER JOIN plan_cuentas as plan ON plan.id_cuenta=det_dia.id_cuenta 
			WHERE enc_dia.ruc_empresa = '" . $ruc_empresa . "' 
			and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
			and '" . date("Y/m/d", strtotime($hasta)) . "' and det_dia.id_cli_pro = '" . $pro_cli . "' 
			and plan.id_cuenta = '" . $cuenta . "' and enc_dia.estado !='ANULADO' order by enc_dia.fecha_asiento asc "); //  
					while ($row_detalle_diario = mysqli_fetch_array($sql_detalle_diario)) {
						$codigo_cuenta = $row_detalle_diario['codigo_cuenta'];
						$nombre_cuenta = strtoupper($row_detalle_diario['nombre_cuenta']);
						$detalle = $row_detalle_diario['detalle'];
						$debe = $row_detalle_diario['debe'];
						$haber = $row_detalle_diario['haber'];
						$saldo += $debe - $haber;
						$asiento = $row_detalle_diario['asiento'];
						$tipo = $row_detalle_diario['tipo'];
						$id_diario = $row_detalle_diario['id_diario'];
						$id_documento = $row_detalle_diario['id_documento'];
						$codigo_unico = $row_detalle_diario['codigo_unico'];
						$codigo_unico_bloque = $row_detalle_diario['codigo_unico_bloque'];
						$fecha = date('d-m-Y', strtotime($row_detalle_diario['fecha']));
						$concepto_general = $row_detalle_diario['concepto_general'];
					?>
						<input type="hidden" value="<?php echo $asiento; ?>" id="numero_asiento<?php echo $id_diario; ?>">
						<input type="hidden" value="<?php echo $concepto_general; ?>" id="mod_concepto_general<?php echo $id_diario; ?>">
						<input type="hidden" value="<?php echo $fecha; ?>" id="mod_fecha_asiento<?php echo $id_diario; ?>">
						<input type="hidden" value="<?php echo $codigo_unico; ?>" id="mod_codigo_unico<?php echo $id_diario; ?>">
						<input type="hidden" value="<?php echo $codigo_unico_bloque; ?>" id="mod_codigo_unico_bloque<?php echo $id_diario; ?>">
						<input type="hidden" value="<?php echo $id_documento; ?>" id="mod_id_documento<?php echo $id_diario; ?>">
						<input type="hidden" value="<?php echo $tipo; ?>" id="mod_tipo<?php echo $id_diario; ?>">
						<tr>
							<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha)); ?></td>
							<td style="padding: 2px;"><?php echo $detalle; ?></td>
							<td style="padding: 2px;">
								<a class='btn btn-info btn-xs' title='Editar asiento' onclick="obtener_datos('<?php echo $id_diario; ?>');" data-toggle="modal" data-target="#NuevoDiarioContable"><i class="glyphicon glyphicon-edit"></i> <?php echo $asiento; ?></a>
							</td>
							<td style="padding: 2px;"><?php echo $tipo; ?></td>
							<td style="padding: 2px;"><?php echo number_format($debe, 2, '.', ','); ?></td>
							<td style="padding: 2px;"><?php echo number_format($haber, 2, '.', ','); ?></td>
							<td style="padding: 2px;"><?php echo number_format($saldo, 2, '.', ','); ?></td>
						</tr>
					<?php
					}
					?>
				</table>
			</div>
		</div>
	<?php
			}

			//para un cliente y todas las cuentas
			if (!empty($pro_cli) && empty($cuenta)) {
	?>
		<div class="panel-group" id="accordiones_cli">
			<?php
				$sql_detalle_cuentas = mysqli_query($con, "SELECT DISTINCT det_dia.id_cli_pro as ide_cliente_cuenta, 
					plan.codigo_cuenta as codigo_cuenta, plan.nombre_cuenta as nombre_cuenta, 
					plan.id_cuenta as ide_cuenta 
					FROM detalle_diario_contable as det_dia 
					INNER JOIN encabezado_diario as enc_dia ON enc_dia.codigo_unico=det_dia.codigo_unico
					INNER JOIN plan_cuentas as plan ON det_dia.id_cuenta=plan.id_cuenta 
					WHERE det_dia.ruc_empresa = '" . $ruc_empresa . "' and plan.nivel_cuenta='5' 
					and det_dia.id_cli_pro ='" . $pro_cli . "' and 
					DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') 
					between '" . date("Y/m/d", strtotime($desde)) . "' 
					and '" . date("Y/m/d", strtotime($hasta)) . "' and enc_dia.estado !='ANULADO'");

				while ($row_detalle_cuentas = mysqli_fetch_array($sql_detalle_cuentas)) {
					$ide_cuenta_contables = $row_detalle_cuentas['ide_cuenta'];
					$ide_cliente_cuenta = $row_detalle_cuentas['ide_cliente_cuenta'];
					$codigo_cuenta = $row_detalle_cuentas['codigo_cuenta'];
					$nombre_cuenta = strtoupper($row_detalle_cuentas['nombre_cuenta']);

			?>
				<div class="panel panel-success">
					<a class="list-group-item list-group-item-success" data-toggle="collapse" data-parent="#accordiones_cli" href="#<?php echo $ide_cuenta_contables . $ide_cliente_cuenta; ?>"><span class="caret"></span> <b>Códido:</b> <?php echo $codigo_cuenta; ?> <b>Cuenta:</b> <?php echo $nombre_cuenta; ?></a>
					<div id="<?php echo $ide_cuenta_contables . $ide_cliente_cuenta; ?>" class="panel-collapse collapse">
						<div class="table-responsive">
							<table class="table table-hover">
								<tr class="info">
									<th style="padding: 2px;">Fecha</th>
									<th style="padding: 2px;">Detalle</th>
									<th style="padding: 2px;">Asiento</th>
									<th style="padding: 2px;">Tipo</th>
									<th style="padding: 2px;">Debe</th>
									<th style="padding: 2px;">Haber</th>
									<th style="padding: 2px;">Saldo</th>
								</tr>
								<?php
								$saldo = 0;
								$sql_detalle_diario = mysqli_query($con, "SELECT enc_dia.tipo as tipo, enc_dia.id_diario as asiento, 
											enc_dia.fecha_asiento as fecha, det_dia.debe as debe, det_dia.haber as haber, 
											det_dia.detalle_item as detalle, enc_dia.codigo_unico as codigo_unico, enc_dia.id_diario as id_diario, 
											enc_dia.concepto_general as concepto_general, enc_dia.id_documento as id_documento, enc_dia.codigo_unico_bloque as codigo_unico_bloque 
											FROM encabezado_diario as enc_dia 
											INNER JOIN detalle_diario_contable as det_dia ON enc_dia.codigo_unico=det_dia.codigo_unico and enc_dia.codigo_unico_bloque=det_dia.codigo_unico_bloque 
											WHERE enc_dia.ruc_empresa = '" . $ruc_empresa . "' 
											and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
											and '" . date("Y/m/d", strtotime($hasta)) . "' and det_dia.id_cuenta = '" . $ide_cuenta_contables . "' 
											and det_dia.id_cli_pro='" . $ide_cliente_cuenta . "' and enc_dia.estado !='ANULADO' order by enc_dia.fecha_asiento asc");

								while ($row_detalle_diario = mysqli_fetch_array($sql_detalle_diario)) {
									$fecha = $row_detalle_diario['fecha'];
									$detalle = $row_detalle_diario['detalle'];
									$debe = $row_detalle_diario['debe'];
									$haber = $row_detalle_diario['haber'];
									$saldo += $debe - $haber;
									$asiento = $row_detalle_diario['asiento'];
									$tipo = $row_detalle_diario['tipo'];
									$id_diario = $row_detalle_diario['id_diario'];
									$id_documento = $row_detalle_diario['id_documento'];
									$codigo_unico = $row_detalle_diario['codigo_unico'];
									$codigo_unico_bloque = $row_detalle_diario['codigo_unico_bloque'];
									$fecha = date('d-m-Y', strtotime($row_detalle_diario['fecha']));
									$concepto_general = $row_detalle_diario['concepto_general'];
								?>
									<input type="hidden" value="<?php echo $asiento; ?>" id="numero_asiento<?php echo $id_diario; ?>">
									<input type="hidden" value="<?php echo $concepto_general; ?>" id="mod_concepto_general<?php echo $id_diario; ?>">
									<input type="hidden" value="<?php echo $fecha; ?>" id="mod_fecha_asiento<?php echo $id_diario; ?>">
									<input type="hidden" value="<?php echo $codigo_unico; ?>" id="mod_codigo_unico<?php echo $id_diario; ?>">
									<input type="hidden" value="<?php echo $codigo_unico_bloque; ?>" id="mod_codigo_unico_bloque<?php echo $id_diario; ?>">
									<input type="hidden" value="<?php echo $id_documento; ?>" id="mod_id_documento<?php echo $id_diario; ?>">
									<input type="hidden" value="<?php echo $tipo; ?>" id="mod_tipo<?php echo $id_diario; ?>">
									<tr>
										<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha)); ?></td>
										<td style="padding: 2px;"><?php echo $detalle; ?></td>
										<td style="padding: 2px;">
											<a class='btn btn-info btn-xs' title='Editar asiento' onclick="obtener_datos('<?php echo $id_diario; ?>');" data-toggle="modal" data-target="#NuevoDiarioContable"><i class="glyphicon glyphicon-edit"></i> <?php echo $asiento; ?></a>
										</td>
										<td style="padding: 2px;"><?php echo $tipo; ?></td>
										<td style="padding: 2px;"><?php echo number_format($debe, 2, '.', ','); ?></td>
										<td style="padding: 2px;"><?php echo number_format($haber, 2, '.', ','); ?></td>
										<td style="padding: 2px;"><?php echo number_format($saldo, 2, '.', ','); ?></td>
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
			?>
		</div>
	<?php
			}

			//para todos los clientes y una cuenta
			if (empty($pro_cli) && !empty($cuenta)) {
	?>
		<div class="panel-group" id="accordiones">
			<?php
				$sql_detalle_clientes = mysqli_query($con, "SELECT DISTINCT det_dia.id_cli_pro as id_cliente, cli.nombre as cliente
		FROM detalle_diario_contable as det_dia INNER JOIN encabezado_diario as enc_dia ON enc_dia.codigo_unico=det_dia.codigo_unico
		INNER JOIN clientes as cli ON cli.id=det_dia.id_cli_pro
		WHERE det_dia.ruc_empresa = '" . $ruc_empresa . "' and cli.ruc_empresa='" . $ruc_empresa . "' and det_dia.id_cuenta='" . $cuenta . "' and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') 
					between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' order by cli.nombre asc ");
				while ($row_detalle_clientes = mysqli_fetch_array($sql_detalle_clientes)) {
					$ide_cliente = $row_detalle_clientes['id_cliente'];
					$nombre_cliente = strtoupper($row_detalle_clientes['cliente']);

			?>
				<div class="panel panel-success">
					<a class="list-group-item list-group-item-success" data-toggle="collapse" data-parent="#accordiones" href="#<?php echo $ide_cliente; ?>"><span class="caret"></span> <b>Cliente: </b> <?php echo $nombre_cliente; ?></a>
					<div id="<?php echo $ide_cliente; ?>" class="panel-collapse collapse">
						<div class="table-responsive">
							<table class="table table-hover">
								<tr class="info">
									<th style="padding: 2px;">Fecha</th>
									<th style="padding: 2px;">Detalle</th>
									<th style="padding: 2px;">Asiento</th>
									<th style="padding: 2px;">Tipo</th>
									<th style="padding: 2px;">Debe</th>
									<th style="padding: 2px;">Haber</th>
									<th style="padding: 2px;">Saldo</th>
								</tr>
								<?php
								$saldo = 0;

								$sql_detalle_diario = mysqli_query($con, "SELECT enc_dia.tipo as tipo, enc_dia.id_diario as asiento, 
										enc_dia.fecha_asiento as fecha, det_dia.debe as debe, det_dia.haber as haber, det_dia.detalle_item as detalle, 
										plan.codigo_cuenta as codigo_cuenta, plan.nombre_cuenta as nombre_cuenta, 
										enc_dia.codigo_unico as codigo_unico, enc_dia.id_diario as id_diario, enc_dia.concepto_general as concepto_general, 
										enc_dia.id_documento as id_documento, enc_dia.codigo_unico_bloque as codigo_unico_bloque 
										FROM encabezado_diario as enc_dia 
										INNER JOIN detalle_diario_contable as det_dia ON det_dia.codigo_unico=enc_dia.codigo_unico
										INNER JOIN plan_cuentas as plan ON plan.id_cuenta=det_dia.id_cuenta  
										WHERE enc_dia.ruc_empresa = '" . $ruc_empresa . "' and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') 
										between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' 
										and det_dia.id_cli_pro = '" . $ide_cliente . "' and det_dia.id_cuenta='" . $cuenta . "' and enc_dia.estado !='ANULADO' order by enc_dia.fecha_asiento asc"); //  

								while ($row_detalle_diario = mysqli_fetch_array($sql_detalle_diario)) {
									$codigo_cuenta = $row_detalle_diario['codigo_cuenta'];
									$nombre_cuenta = strtoupper($row_detalle_diario['nombre_cuenta']);
									$detalle = $row_detalle_diario['detalle'];
									$debe = $row_detalle_diario['debe'];
									$haber = $row_detalle_diario['haber'];
									$saldo += $debe - $haber;
									$asiento = $row_detalle_diario['asiento'];
									$tipo = $row_detalle_diario['tipo'];
									$id_diario = $row_detalle_diario['id_diario'];
									$id_documento = $row_detalle_diario['id_documento'];
									$codigo_unico = $row_detalle_diario['codigo_unico'];
									$codigo_unico_bloque = $row_detalle_diario['codigo_unico_bloque'];
									$fecha = date('d-m-Y', strtotime($row_detalle_diario['fecha']));
									$concepto_general = $row_detalle_diario['concepto_general'];
								?>
									<input type="hidden" value="<?php echo $asiento; ?>" id="numero_asiento<?php echo $id_diario; ?>">
									<input type="hidden" value="<?php echo $concepto_general; ?>" id="mod_concepto_general<?php echo $id_diario; ?>">
									<input type="hidden" value="<?php echo $fecha; ?>" id="mod_fecha_asiento<?php echo $id_diario; ?>">
									<input type="hidden" value="<?php echo $codigo_unico; ?>" id="mod_codigo_unico<?php echo $id_diario; ?>">
									<input type="hidden" value="<?php echo $codigo_unico_bloque; ?>" id="mod_codigo_unico_bloque<?php echo $id_diario; ?>">
									<input type="hidden" value="<?php echo $id_documento; ?>" id="mod_id_documento<?php echo $id_diario; ?>">
									<input type="hidden" value="<?php echo $tipo; ?>" id="mod_tipo<?php echo $id_diario; ?>">
									<tr>
										<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha)); ?></td>
										<td style="padding: 2px;"><?php echo $detalle; ?></td>
										<td style="padding: 2px;">
											<a class='btn btn-info btn-xs' title='Editar asiento' onclick="obtener_datos('<?php echo $id_diario; ?>');" data-toggle="modal" data-target="#NuevoDiarioContable"><i class="glyphicon glyphicon-edit"></i> <?php echo $asiento; ?></a>
										</td>
										<td style="padding: 2px;"><?php echo $tipo; ?></td>
										<td style="padding: 2px;"><?php echo number_format($debe, 2, '.', ','); ?></td>
										<td style="padding: 2px;"><?php echo number_format($haber, 2, '.', ','); ?></td>
										<td style="padding: 2px;"><?php echo number_format($saldo, 2, '.', ','); ?></td>
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

			//para todos los clientes y todas las cuentas
			if (empty($pro_cli) && empty($cuenta)) {
	?>
		<div class="panel-group" id="accordiones_clientes">
			<?php
				$sql_detalle_clientes = mysqli_query($con, "SELECT DISTINCT det_dia.id_cli_pro as id_cliente, cli.nombre as cliente
				FROM detalle_diario_contable as det_dia INNER JOIN encabezado_diario as enc_dia ON enc_dia.codigo_unico=det_dia.codigo_unico
				INNER JOIN clientes as cli ON cli.id=det_dia.id_cli_pro
				WHERE det_dia.ruc_empresa = '" . $ruc_empresa . "' and cli.ruc_empresa='" . $ruc_empresa . "' and det_dia.id_cuenta > 0 and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') 
							between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' order by cli.nombre asc ");
				while ($row_detalle_clientes = mysqli_fetch_array($sql_detalle_clientes)) {
					$ide_cliente = $row_detalle_clientes['id_cliente'];
					$nombre_cliente = $row_detalle_clientes['cliente'];
			?>
				<div class="panel panel-info">
					<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordiones_clientes" href="#<?php echo $ide_cliente; ?>"><span class="caret"></span> <b>Cliente: </b> <?php echo $nombre_cliente; ?></a>
					<div class="panel-collapse collapse" id="<?php echo $ide_cliente; ?>">

						<div class="panel-group" id="accordiones_cuentas">
							<?php
							$sql_detalle_cuentas = mysqli_query($con, "SELECT DISTINCT det_dia.id_cli_pro as ide_cliente_cuenta, 
										plan.codigo_cuenta as codigo_cuenta, plan.nombre_cuenta as nombre_cuenta, 
										plan.id_cuenta as ide_cuenta 
										FROM detalle_diario_contable as det_dia 
										INNER JOIN encabezado_diario as enc_dia ON enc_dia.codigo_unico=det_dia.codigo_unico
										INNER JOIN plan_cuentas as plan ON det_dia.id_cuenta=plan.id_cuenta 
										WHERE det_dia.ruc_empresa = '" . $ruc_empresa . "' and plan.nivel_cuenta='5' 
										and det_dia.id_cli_pro='" . $ide_cliente . "' and 
										DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') 
										between '" . date("Y/m/d", strtotime($desde)) . "' 
										and '" . date("Y/m/d", strtotime($hasta)) . "' and enc_dia.estado !='ANULADO'");

							while ($row_detalle_cuentas = mysqli_fetch_array($sql_detalle_cuentas)) {
								$ide_cuenta_contables = $row_detalle_cuentas['ide_cuenta'];
								$ide_cliente_cuenta = $row_detalle_cuentas['ide_cliente_cuenta'];
								$codigo_cuenta = $row_detalle_cuentas['codigo_cuenta'];
								$nombre_cuenta = strtoupper($row_detalle_cuentas['nombre_cuenta']);

							?>
								<div class="panel panel-success">
									<a class="list-group-item list-group-item-success" data-toggle="collapse" data-parent="#accordiones_cuentas" href="#<?php echo $ide_cuenta_contables . $ide_cliente_cuenta; ?>"><span class="caret"></span> <b>Códido:</b> <?php echo $codigo_cuenta; ?> <b>Cuenta:</b> <?php echo $nombre_cuenta; ?></a>
									<div id="<?php echo $ide_cuenta_contables . $ide_cliente_cuenta; ?>" class="panel-collapse collapse">
										<div class="table-responsive">
											<table class="table table-hover">
												<tr class="info">
													<th style="padding: 2px;">Fecha</th>
													<th style="padding: 2px;">Detalle</th>
													<th style="padding: 2px;">Asiento</th>
													<th style="padding: 2px;">Tipo</th>
													<th style="padding: 2px;">Debe</th>
													<th style="padding: 2px;">Haber</th>
													<th style="padding: 2px;">Saldo</th>
												</tr>
												<?php
												$saldo = 0;
												$sql_detalle_diario = mysqli_query($con, "SELECT enc_dia.tipo as tipo, enc_dia.id_diario as asiento, 
															enc_dia.fecha_asiento as fecha, det_dia.debe as debe, det_dia.haber as haber, 
															det_dia.detalle_item as detalle, enc_dia.codigo_unico as codigo_unico, enc_dia.id_diario as id_diario, 
															enc_dia.concepto_general as concepto_general, enc_dia.id_documento as id_documento, enc_dia.codigo_unico_bloque as codigo_unico_bloque 
															FROM encabezado_diario as enc_dia 
															INNER JOIN detalle_diario_contable as det_dia ON enc_dia.codigo_unico=det_dia.codigo_unico and enc_dia.codigo_unico_bloque=det_dia.codigo_unico_bloque
															WHERE enc_dia.ruc_empresa = '" . $ruc_empresa . "' 
															and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
															and '" . date("Y/m/d", strtotime($hasta)) . "' and det_dia.id_cuenta = '" . $ide_cuenta_contables . "' 
															and det_dia.id_cli_pro='" . $ide_cliente_cuenta . "' and enc_dia.estado !='ANULADO' order by enc_dia.fecha_asiento asc");

												while ($row_detalle_diario = mysqli_fetch_array($sql_detalle_diario)) {
													$fecha = $row_detalle_diario['fecha'];
													$detalle = $row_detalle_diario['detalle'];
													$debe = $row_detalle_diario['debe'];
													$haber = $row_detalle_diario['haber'];
													$saldo += $debe - $haber;
													$asiento = $row_detalle_diario['asiento'];
													$tipo = $row_detalle_diario['tipo'];
													$id_diario = $row_detalle_diario['id_diario'];
													$id_documento = $row_detalle_diario['id_documento'];
													$codigo_unico = $row_detalle_diario['codigo_unico'];
													$codigo_unico_bloque = $row_detalle_diario['codigo_unico_bloque'];
													$fecha = date('d-m-Y', strtotime($row_detalle_diario['fecha']));
													$concepto_general = $row_detalle_diario['concepto_general'];
												?>
													<input type="hidden" value="<?php echo $asiento; ?>" id="numero_asiento<?php echo $id_diario; ?>">
													<input type="hidden" value="<?php echo $concepto_general; ?>" id="mod_concepto_general<?php echo $id_diario; ?>">
													<input type="hidden" value="<?php echo $fecha; ?>" id="mod_fecha_asiento<?php echo $id_diario; ?>">
													<input type="hidden" value="<?php echo $codigo_unico; ?>" id="mod_codigo_unico<?php echo $id_diario; ?>">
													<input type="hidden" value="<?php echo $codigo_unico_bloque; ?>" id="mod_codigo_unico_bloque<?php echo $id_diario; ?>">
													<input type="hidden" value="<?php echo $id_documento; ?>" id="mod_id_documento<?php echo $id_diario; ?>">
													<input type="hidden" value="<?php echo $tipo; ?>" id="mod_tipo<?php echo $id_diario; ?>">
													<tr>
														<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha)); ?></td>
														<td style="padding: 2px;"><?php echo $detalle; ?></td>
														<td style="padding: 2px;">
															<a class='btn btn-info btn-xs' title='Editar asiento' onclick="obtener_datos('<?php echo $id_diario; ?>');" data-toggle="modal" data-target="#NuevoDiarioContable"><i class="glyphicon glyphicon-edit"></i> <?php echo $asiento; ?></a>
														</td>
														<td style="padding: 2px;"><?php echo $tipo; ?></td>
														<td style="padding: 2px;"><?php echo number_format($debe, 2, '.', ','); ?></td>
														<td style="padding: 2px;"><?php echo number_format($haber, 2, '.', ','); ?></td>
														<td style="padding: 2px;"><?php echo number_format($saldo, 2, '.', ','); ?></td>
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
							?>
						</div>
					</div>
				</div>
			<?php

				}
			?>
		</div>
	<?php
			}
		} //cierra el mayor de clientes

		//para hacer mayor de proveedores
		if ($action == '6') {
			//estas variables vienen de el post de mayores de javascript
			$desde = mysqli_real_escape_string($con, (strip_tags($_REQUEST['fecha_desde'], ENT_QUOTES)));
			$hasta = mysqli_real_escape_string($con, (strip_tags($_REQUEST['fecha_hasta'], ENT_QUOTES)));
			$cuenta = mysqli_real_escape_string($con, (strip_tags($_REQUEST['cuenta'], ENT_QUOTES)));
			$pro_cli = mysqli_real_escape_string($con, (strip_tags($_REQUEST['pro_cli'], ENT_QUOTES)));

			//para un proveedor y una cuenta
			$sql_proveedores = mysqli_query($con, "SELECT * FROM proveedores WHERE id_proveedor = '" . $pro_cli . "' "); //  
			$row_proveedores = mysqli_fetch_array($sql_proveedores);
			$nombre_proveedor = $row_proveedores['razon_social'];

			$sql_cuentas = mysqli_query($con, "SELECT * FROM plan_cuentas WHERE id_cuenta = '" . $cuenta . "' "); //  
			$row_cuentas = mysqli_fetch_array($sql_cuentas);
			$codigo_cuenta = $row_cuentas['codigo_cuenta'];
			$nombre_cuenta = strtoupper($row_cuentas['nombre_cuenta']);

			if (!empty($pro_cli) && !empty($cuenta)) {
	?>
		<div class="table-responsive">
			<div class="panel panel-success">
				<div class="panel-heading" style="padding: 2px;">
					<h5>
						<p align="left"><b>Proveedor: </b><?php echo $nombre_proveedor; ?> <b>Código: </b><?php echo $codigo_cuenta; ?> <b>Cuenta: </b><?php echo $nombre_cuenta; ?> </p>
					</h5>
				</div>
				<table class="table table-hover">
					<tr class="info">
						<th style="padding: 2px;">Fecha</th>
						<th style="padding: 2px;">Detalle</th>
						<th style="padding: 2px;">Asiento</th>
						<th style="padding: 2px;">Tipo</th>
						<th style="padding: 2px;">Debe</th>
						<th style="padding: 2px;">Haber</th>
						<th style="padding: 2px;">Saldo</th>
					</tr>
					<?php
					$saldo = 0;
					$sql_detalle_diario = mysqli_query($con, "SELECT enc_dia.tipo as tipo, enc_dia.id_diario as asiento, 
			enc_dia.fecha_asiento as fecha, det_dia.debe as debe, det_dia.haber as haber, 
			det_dia.detalle_item as detalle, plan.codigo_cuenta as codigo_cuenta, plan.nombre_cuenta as nombre_cuenta, 
			enc_dia.codigo_unico as codigo_unico, enc_dia.id_diario as id_diario, enc_dia.concepto_general as concepto_general, 
			enc_dia.id_documento as id_documento, enc_dia.codigo_unico_bloque as codigo_unico_bloque			
			FROM encabezado_diario as enc_dia 
			INNER JOIN detalle_diario_contable as det_dia ON enc_dia.codigo_unico=det_dia.codigo_unico and enc_dia.codigo_unico_bloque=det_dia.codigo_unico_bloque
			INNER JOIN plan_cuentas as plan ON 
			 plan.id_cuenta=det_dia.id_cuenta WHERE enc_dia.ruc_empresa = '" . $ruc_empresa . "' and 
			 DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
			 and '" . date("Y/m/d", strtotime($hasta)) . "' and det_dia.id_cli_pro = '" . $pro_cli . "' 
			 and plan.id_cuenta = '" . $cuenta . "' and enc_dia.estado !='ANULADO' order by enc_dia.fecha_asiento asc, det_dia.debe asc "); //  
					while ($row_detalle_diario = mysqli_fetch_array($sql_detalle_diario)) {
						$codigo_cuenta = $row_detalle_diario['codigo_cuenta'];
						$nombre_cuenta = strtoupper($row_detalle_diario['nombre_cuenta']);
						$detalle = $row_detalle_diario['detalle'];
						$debe = $row_detalle_diario['debe'];
						$haber = $row_detalle_diario['haber'];
						$saldo += $debe - $haber;
						$asiento = $row_detalle_diario['asiento'];
						$tipo = $row_detalle_diario['tipo'];
						$id_diario = $row_detalle_diario['id_diario'];
						$id_documento = $row_detalle_diario['id_documento'];
						$codigo_unico = $row_detalle_diario['codigo_unico'];
						$codigo_unico_bloque = $row_detalle_diario['codigo_unico_bloque'];
						$fecha = date('d-m-Y', strtotime($row_detalle_diario['fecha']));
						$concepto_general = $row_detalle_diario['concepto_general'];
					?>
						<input type="hidden" value="<?php echo $asiento; ?>" id="numero_asiento<?php echo $id_diario; ?>">
						<input type="hidden" value="<?php echo $concepto_general; ?>" id="mod_concepto_general<?php echo $id_diario; ?>">
						<input type="hidden" value="<?php echo $fecha; ?>" id="mod_fecha_asiento<?php echo $id_diario; ?>">
						<input type="hidden" value="<?php echo $codigo_unico; ?>" id="mod_codigo_unico<?php echo $id_diario; ?>">
						<input type="hidden" value="<?php echo $codigo_unico_bloque; ?>" id="mod_codigo_unico_bloque<?php echo $id_diario; ?>">
						<input type="hidden" value="<?php echo $id_documento; ?>" id="mod_id_documento<?php echo $id_diario; ?>">
						<input type="hidden" value="<?php echo $tipo; ?>" id="mod_tipo<?php echo $id_diario; ?>">
						<tr>
							<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha)); ?></td>
							<td style="padding: 2px;"><?php echo $detalle; ?></td>
							<td style="padding: 2px;">
								<a class='btn btn-info btn-xs' title='Editar asiento' onclick="obtener_datos('<?php echo $id_diario; ?>');" data-toggle="modal" data-target="#NuevoDiarioContable"><i class="glyphicon glyphicon-edit"></i> <?php echo $asiento; ?></a>
							</td>
							<td style="padding: 2px;"><?php echo $tipo; ?></td>
							<td style="padding: 2px;"><?php echo number_format($debe, 2, '.', ','); ?></td>
							<td style="padding: 2px;"><?php echo number_format($haber, 2, '.', ','); ?></td>
							<td style="padding: 2px;"><?php echo number_format($saldo, 2, '.', ','); ?></td>
						</tr>
					<?php
					}
					?>
				</table>
			</div>
		</div>
	<?php
			}

			//para todos los proveedores y una cuenta
			if (empty($pro_cli) && !empty($cuenta)) {
	?>
		<div class="panel-group" id="accordiones">
			<?php
				$sql_detalle_proveedores = mysqli_query($con, "SELECT DISTINCT det_dia.id_cli_pro as id_proveedor, pro.razon_social as razon_social
				FROM detalle_diario_contable as det_dia INNER JOIN encabezado_diario as enc_dia ON enc_dia.codigo_unico=det_dia.codigo_unico
				INNER JOIN proveedores as pro ON pro.id_proveedor=det_dia.id_cli_pro
				WHERE det_dia.ruc_empresa = '" . $ruc_empresa . "' and pro.ruc_empresa='" . $ruc_empresa . "' and det_dia.id_cuenta='" . $cuenta . "' and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') 
							between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' order by pro.razon_social asc ");
				while ($row_detalle_proveedores = mysqli_fetch_array($sql_detalle_proveedores)) {
					$ide_proveedor = $row_detalle_proveedores['id_proveedor'];
					$nombre_proveedor = strtoupper($row_detalle_proveedores['razon_social']);

			?>
				<div class="panel panel-success">
					<a class="list-group-item list-group-item-success" data-toggle="collapse" data-parent="#accordiones" href="#<?php echo $ide_proveedor; ?>"><span class="caret"></span> <b>Proveedor: </b> <?php echo $nombre_proveedor; ?></a>
					<div id="<?php echo $ide_proveedor; ?>" class="panel-collapse collapse">
						<div class="table-responsive">
							<table class="table table-hover">
								<tr class="info">
									<th style="padding: 2px;">Fecha</th>
									<th style="padding: 2px;">Detalle</th>
									<th style="padding: 2px;">Asiento</th>
									<th style="padding: 2px;">Tipo</th>
									<th style="padding: 2px;">Debe</th>
									<th style="padding: 2px;">Haber</th>
									<th style="padding: 2px;">Saldo</th>
								</tr>
								<?php
								$saldo = 0;
								$sql_detalle_diario = mysqli_query($con, "SELECT enc_dia.tipo as tipo, enc_dia.id_diario as asiento, 
						enc_dia.fecha_asiento as fecha, det_dia.debe as debe, det_dia.haber as haber, det_dia.detalle_item as detalle, 
						plan.codigo_cuenta as codigo_cuenta, plan.nombre_cuenta as nombre_cuenta, 
						enc_dia.codigo_unico as codigo_unico, enc_dia.id_diario as id_diario, enc_dia.concepto_general as concepto_general, 
						enc_dia.id_documento as id_documento, enc_dia.codigo_unico_bloque as codigo_unico_bloque 
						FROM encabezado_diario as enc_dia 
						INNER JOIN detalle_diario_contable as det_dia ON det_dia.codigo_unico=enc_dia.codigo_unico and enc_dia.codigo_unico_bloque=det_dia.codigo_unico_bloque
						INNER JOIN plan_cuentas as plan ON plan.id_cuenta=det_dia.id_cuenta  
						WHERE enc_dia.ruc_empresa = '" . $ruc_empresa . "' and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') 
						between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' 
						and det_dia.id_cli_pro = '" . $ide_proveedor . "' and det_dia.id_cuenta='" . $cuenta . "' and enc_dia.estado !='ANULADO' order by enc_dia.fecha_asiento asc"); //  
								while ($row_detalle_diario = mysqli_fetch_array($sql_detalle_diario)) {
									$codigo_cuenta = $row_detalle_diario['codigo_cuenta'];
									$nombre_cuenta = strtoupper($row_detalle_diario['nombre_cuenta']);
									$detalle = $row_detalle_diario['detalle'];
									$debe = $row_detalle_diario['debe'];
									$haber = $row_detalle_diario['haber'];
									$saldo += $debe - $haber;
									$asiento = $row_detalle_diario['asiento'];
									$tipo = $row_detalle_diario['tipo'];
									$id_diario = $row_detalle_diario['id_diario'];
									$id_documento = $row_detalle_diario['id_documento'];
									$codigo_unico = $row_detalle_diario['codigo_unico'];
									$codigo_unico_bloque = $row_detalle_diario['codigo_unico_bloque'];
									$fecha = date('d-m-Y', strtotime($row_detalle_diario['fecha']));
									$concepto_general = $row_detalle_diario['concepto_general'];
								?>
									<input type="hidden" value="<?php echo $asiento; ?>" id="numero_asiento<?php echo $id_diario; ?>">
									<input type="hidden" value="<?php echo $concepto_general; ?>" id="mod_concepto_general<?php echo $id_diario; ?>">
									<input type="hidden" value="<?php echo $fecha; ?>" id="mod_fecha_asiento<?php echo $id_diario; ?>">
									<input type="hidden" value="<?php echo $codigo_unico; ?>" id="mod_codigo_unico<?php echo $id_diario; ?>">
									<input type="hidden" value="<?php echo $codigo_unico_bloque; ?>" id="mod_codigo_unico_bloque<?php echo $id_diario; ?>">
									<input type="hidden" value="<?php echo $id_documento; ?>" id="mod_id_documento<?php echo $id_diario; ?>">
									<input type="hidden" value="<?php echo $tipo; ?>" id="mod_tipo<?php echo $id_diario; ?>">
									<tr>
										<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha)); ?></td>
										<td style="padding: 2px;"><?php echo $detalle; ?></td>
										<td style="padding: 2px;">
											<a class='btn btn-info btn-xs' title='Editar asiento' onclick="obtener_datos('<?php echo $id_diario; ?>');" data-toggle="modal" data-target="#NuevoDiarioContable"><i class="glyphicon glyphicon-edit"></i> <?php echo $asiento; ?></a>
										</td>
										<td style="padding: 2px;"><?php echo $tipo; ?></td>
										<td style="padding: 2px;"><?php echo number_format($debe, 2, '.', ','); ?></td>
										<td style="padding: 2px;"><?php echo number_format($haber, 2, '.', ','); ?></td>
										<td style="padding: 2px;"><?php echo number_format($saldo, 2, '.', ','); ?></td>
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
			?>
		</div>
	<?php
			}

			//para todos los proveedores y todas las cuentas
			if (empty($pro_cli) && empty($cuenta)) {
	?>
		<div class="panel-group" id="accordiones_proveedores">
			<?php
				$sql_detalle_proveedores = mysqli_query($con, "SELECT DISTINCT det_dia.id_cli_pro as id_proveedor, pro.razon_social as razon_social
			FROM detalle_diario_contable as det_dia INNER JOIN encabezado_diario as enc_dia ON enc_dia.codigo_unico=det_dia.codigo_unico
			INNER JOIN proveedores as pro ON pro.id_proveedor=det_dia.id_cli_pro
			WHERE det_dia.ruc_empresa = '" . $ruc_empresa . "' and pro.ruc_empresa='" . $ruc_empresa . "' and det_dia.id_cuenta > 0 and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') 
						between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' order by pro.razon_social asc ");
				while ($row_detalle_proveedores = mysqli_fetch_array($sql_detalle_proveedores)) {
					$ide_proveedor = $row_detalle_proveedores['id_proveedor'];
					$nombre_proveedor = strtoupper($row_detalle_proveedores['razon_social']);
			?>
				<div class="panel panel-info">
					<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordiones_proveedores" href="#<?php echo $ide_proveedor; ?>"><span class="caret"></span> <b>Proveedor: </b> <?php echo $nombre_proveedor; ?></a>
					<div class="panel-collapse collapse" id="<?php echo $ide_proveedor; ?>">

						<div class="panel-group" id="accordiones_cuentas">
							<?php
							$sql_detalle_cuentas = mysqli_query($con, "SELECT DISTINCT det_dia.id_cli_pro as ide_proveedor_cuenta, 
										plan.codigo_cuenta as codigo_cuenta, plan.nombre_cuenta as nombre_cuenta, 
										plan.id_cuenta as ide_cuenta 
										FROM detalle_diario_contable as det_dia 
										INNER JOIN encabezado_diario as enc_dia ON enc_dia.codigo_unico=det_dia.codigo_unico
										INNER JOIN plan_cuentas as plan ON det_dia.id_cuenta=plan.id_cuenta 
										WHERE plan.ruc_empresa = '" . $ruc_empresa . "' and plan.nivel_cuenta='5' 
										and det_dia.id_cli_pro ='" . $ide_proveedor . "' and 
										DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') 
										between '" . date("Y/m/d", strtotime($desde)) . "' 
										and '" . date("Y/m/d", strtotime($hasta)) . "' and enc_dia.estado !='ANULADO'");
							while ($row_detalle_cuentas = mysqli_fetch_array($sql_detalle_cuentas)) {
								$ide_cuenta_contables = $row_detalle_cuentas['ide_cuenta'];
								$ide_proveedor_cuenta = $row_detalle_cuentas['ide_proveedor_cuenta'];
								$codigo_cuenta = $row_detalle_cuentas['codigo_cuenta'];
								$nombre_cuenta = strtoupper($row_detalle_cuentas['nombre_cuenta']);

							?>
								<div class="panel panel-success">
									<a class="list-group-item list-group-item-success" data-toggle="collapse" data-parent="#accordiones_cuentas" href="#<?php echo $ide_cuenta_contables . $ide_proveedor_cuenta; ?>"><span class="caret"></span> <b>Códido:</b> <?php echo $codigo_cuenta; ?> <b>Cuenta:</b> <?php echo strtoupper($nombre_cuenta); ?></a>
									<div id="<?php echo $ide_cuenta_contables . $ide_proveedor_cuenta; ?>" class="panel-collapse collapse">
										<div class="table-responsive">
											<table class="table table-hover">
												<tr class="info">
													<th style="padding: 2px;">Fecha</th>
													<th style="padding: 2px;">Detalle</th>
													<th style="padding: 2px;">Asiento</th>
													<th style="padding: 2px;">Tipo</th>
													<th style="padding: 2px;">Debe</th>
													<th style="padding: 2px;">Haber</th>
													<th style="padding: 2px;">Saldo</th>
												</tr>
												<?php
												$saldo = 0;
												$sql_detalle_diario = mysqli_query($con, "SELECT enc_dia.tipo as tipo, enc_dia.id_diario as asiento, 
							enc_dia.fecha_asiento as fecha, det_dia.debe as debe, det_dia.haber as haber, 
							det_dia.detalle_item as detalle, enc_dia.codigo_unico as codigo_unico, enc_dia.id_diario as id_diario, 
							enc_dia.concepto_general as concepto_general, enc_dia.id_documento as id_documento, enc_dia.codigo_unico_bloque as codigo_unico_bloque 
							FROM encabezado_diario as enc_dia 
							INNER JOIN detalle_diario_contable as det_dia ON enc_dia.codigo_unico=det_dia.codigo_unico and enc_dia.codigo_unico_bloque=det_dia.codigo_unico_bloque
							WHERE enc_dia.ruc_empresa = '" . $ruc_empresa . "' 
							and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
							and '" . date("Y/m/d", strtotime($hasta)) . "' and det_dia.id_cuenta = '" . $ide_cuenta_contables . "' 
							and det_dia.id_cli_pro='" . $ide_proveedor_cuenta . "' and enc_dia.estado !='ANULADO' order by enc_dia.fecha_asiento asc");
												while ($row_detalle_diario = mysqli_fetch_array($sql_detalle_diario)) {
													$detalle = $row_detalle_diario['detalle'];
													$debe = $row_detalle_diario['debe'];
													$haber = $row_detalle_diario['haber'];
													$saldo += $debe - $haber;
													$asiento = $row_detalle_diario['asiento'];
													$tipo = $row_detalle_diario['tipo'];
													$id_diario = $row_detalle_diario['id_diario'];
													$id_documento = $row_detalle_diario['id_documento'];
													$codigo_unico = $row_detalle_diario['codigo_unico'];
													$codigo_unico_bloque = $row_detalle_diario['codigo_unico_bloque'];
													$fecha = date('d-m-Y', strtotime($row_detalle_diario['fecha']));
													$concepto_general = $row_detalle_diario['concepto_general'];
												?>
													<input type="hidden" value="<?php echo $asiento; ?>" id="numero_asiento<?php echo $id_diario; ?>">
													<input type="hidden" value="<?php echo $concepto_general; ?>" id="mod_concepto_general<?php echo $id_diario; ?>">
													<input type="hidden" value="<?php echo $fecha; ?>" id="mod_fecha_asiento<?php echo $id_diario; ?>">
													<input type="hidden" value="<?php echo $codigo_unico; ?>" id="mod_codigo_unico<?php echo $id_diario; ?>">
													<input type="hidden" value="<?php echo $codigo_unico_bloque; ?>" id="mod_codigo_unico_bloque<?php echo $id_diario; ?>">
													<input type="hidden" value="<?php echo $id_documento; ?>" id="mod_id_documento<?php echo $id_diario; ?>">
													<input type="hidden" value="<?php echo $tipo; ?>" id="mod_tipo<?php echo $id_diario; ?>">
													<tr>
														<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha)); ?></td>
														<td style="padding: 2px;"><?php echo $detalle; ?></td>
														<td style="padding: 2px;">
															<a class='btn btn-info btn-xs' title='Editar asiento' onclick="obtener_datos('<?php echo $id_diario; ?>');" data-toggle="modal" data-target="#NuevoDiarioContable"><i class="glyphicon glyphicon-edit"></i> <?php echo $asiento; ?></a>
														</td>
														<td style="padding: 2px;"><?php echo $tipo; ?></td>
														<td style="padding: 2px;"><?php echo number_format($debe, 2, '.', ','); ?></td>
														<td style="padding: 2px;"><?php echo number_format($haber, 2, '.', ','); ?></td>
														<td style="padding: 2px;"><?php echo number_format($saldo, 2, '.', ','); ?></td>
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
							?>
						</div>
					</div>
				</div>
			<?php
				}
			?>
		</div>
	<?php
			}

			//para un proveedor y todas las cuentas
			if (!empty($pro_cli) && empty($cuenta)) {
	?>
		<div class="panel-group" id="accordiones_ptc">
			<?php
				$sql_detalle_cuentas = mysqli_query($con, "SELECT DISTINCT det_dia.id_cli_pro as ide_proveedor_cuenta, 
						plan.codigo_cuenta as codigo_cuenta, plan.nombre_cuenta as nombre_cuenta, 
						plan.id_cuenta as ide_cuenta 
						FROM detalle_diario_contable as det_dia 
						INNER JOIN encabezado_diario as enc_dia ON enc_dia.codigo_unico=det_dia.codigo_unico
						INNER JOIN plan_cuentas as plan ON det_dia.id_cuenta=plan.id_cuenta 
						WHERE det_dia.ruc_empresa = '" . $ruc_empresa . "' and plan.nivel_cuenta='5' 
						and det_dia.id_cli_pro='" . $pro_cli . "' and 
						DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') 
						between '" . date("Y/m/d", strtotime($desde)) . "' 
						and '" . date("Y/m/d", strtotime($hasta)) . "' and enc_dia.estado !='ANULADO'");

				while ($row_detalle_cuentas = mysqli_fetch_array($sql_detalle_cuentas)) {
					$ide_cuenta_contables = $row_detalle_cuentas['ide_cuenta'];
					$ide_proveedor_cuenta = $row_detalle_cuentas['ide_proveedor_cuenta'];
					$codigo_cuenta = $row_detalle_cuentas['codigo_cuenta'];
					$nombre_cuenta = strtoupper($row_detalle_cuentas['nombre_cuenta']);

			?>
				<div class="panel panel-success">
					<a class="list-group-item list-group-item-success" data-toggle="collapse" data-parent="#accordiones_ptc" href="#<?php echo $ide_cuenta_contables . $ide_proveedor_cuenta; ?>"><span class="caret"></span> <b>Códido:</b> <?php echo $codigo_cuenta; ?> <b>Cuenta:</b> <?php echo $nombre_cuenta; ?></a>
					<div id="<?php echo $ide_cuenta_contables . $ide_proveedor_cuenta; ?>" class="panel-collapse collapse">
						<div class="table-responsive">
							<table class="table table-hover">
								<tr class="info">
									<th style="padding: 2px;">Fecha</th>
									<th style="padding: 2px;">Detalle</th>
									<th style="padding: 2px;">Asiento</th>
									<th style="padding: 2px;">Tipo</th>
									<th style="padding: 2px;">Debe</th>
									<th style="padding: 2px;">Haber</th>
									<th style="padding: 2px;">Saldo</th>
								</tr>
								<?php
								$saldo = 0;
								$sql_detalle_diario = mysqli_query($con, "SELECT enc_dia.tipo as tipo, enc_dia.id_diario as asiento, 
												enc_dia.fecha_asiento as fecha, det_dia.debe as debe, det_dia.haber as haber, 
												det_dia.detalle_item as detalle, enc_dia.codigo_unico as codigo_unico, enc_dia.id_diario as id_diario, 
												enc_dia.concepto_general as concepto_general, enc_dia.id_documento as id_documento, enc_dia.codigo_unico_bloque as codigo_unico_bloque 
												FROM encabezado_diario as enc_dia 
												INNER JOIN detalle_diario_contable as det_dia ON enc_dia.codigo_unico=det_dia.codigo_unico and enc_dia.codigo_unico_bloque=det_dia.codigo_unico_bloque
												WHERE enc_dia.ruc_empresa = '" . $ruc_empresa . "' 
												and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
												and '" . date("Y/m/d", strtotime($hasta)) . "' and det_dia.id_cuenta = '" . $ide_cuenta_contables . "' 
												and det_dia.id_cli_pro='" . $ide_proveedor_cuenta . "' and enc_dia.estado !='ANULADO' order by enc_dia.fecha_asiento asc");

								while ($row_detalle_diario = mysqli_fetch_array($sql_detalle_diario)) {
									$fecha = $row_detalle_diario['fecha'];
									$detalle = $row_detalle_diario['detalle'];
									$debe = $row_detalle_diario['debe'];
									$haber = $row_detalle_diario['haber'];
									$saldo += $debe - $haber;
									$asiento = $row_detalle_diario['asiento'];
									$tipo = $row_detalle_diario['tipo'];
									$id_diario = $row_detalle_diario['id_diario'];
									$id_documento = $row_detalle_diario['id_documento'];
									$codigo_unico = $row_detalle_diario['codigo_unico'];
									$codigo_unico_bloque = $row_detalle_diario['codigo_unico_bloque'];
									$fecha = date('d-m-Y', strtotime($row_detalle_diario['fecha']));
									$concepto_general = $row_detalle_diario['concepto_general'];
								?>
									<input type="hidden" value="<?php echo $asiento; ?>" id="numero_asiento<?php echo $id_diario; ?>">
									<input type="hidden" value="<?php echo $concepto_general; ?>" id="mod_concepto_general<?php echo $id_diario; ?>">
									<input type="hidden" value="<?php echo $fecha; ?>" id="mod_fecha_asiento<?php echo $id_diario; ?>">
									<input type="hidden" value="<?php echo $codigo_unico; ?>" id="mod_codigo_unico<?php echo $id_diario; ?>">
									<input type="hidden" value="<?php echo $codigo_unico_bloque; ?>" id="mod_codigo_unico_bloque<?php echo $id_diario; ?>">
									<input type="hidden" value="<?php echo $id_documento; ?>" id="mod_id_documento<?php echo $id_diario; ?>">
									<input type="hidden" value="<?php echo $tipo; ?>" id="mod_tipo<?php echo $id_diario; ?>">
									<tr>
										<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha)); ?></td>
										<td style="padding: 2px;"><?php echo $detalle; ?></td>
										<td style="padding: 2px;">
											<a class='btn btn-info btn-xs' title='Editar asiento' onclick="obtener_datos('<?php echo $id_diario; ?>');" data-toggle="modal" data-target="#NuevoDiarioContable"><i class="glyphicon glyphicon-edit"></i> <?php echo $asiento; ?></a>
										</td>
										<td style="padding: 2px;"><?php echo $tipo; ?></td>
										<td style="padding: 2px;"><?php echo number_format($debe, 2, '.', ','); ?></td>
										<td style="padding: 2px;"><?php echo number_format($haber, 2, '.', ','); ?></td>
										<td style="padding: 2px;"><?php echo number_format($saldo, 2, '.', ','); ?></td>
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
			?>
		</div>
	<?php
			}
		}

		//para hacer mayor por detalle de asiento
		if ($action == '7') {
			$desde = mysqli_real_escape_string($con, (strip_tags($_REQUEST['fecha_desde'], ENT_QUOTES)));
			$hasta = mysqli_real_escape_string($con, (strip_tags($_REQUEST['fecha_hasta'], ENT_QUOTES)));
			$det_pro_cli = mysqli_real_escape_string($con, (strip_tags($_REQUEST['det_pro_cli'], ENT_QUOTES)));
			//para un detalle y todas las cuentas
			if (!empty($det_pro_cli)) {
	?>
		<div class="panel-group" id="accordiones">
			<?php
				$sql_detalle_cuentas = mysqli_query($con, "SELECT plan.codigo_cuenta as codigo_cuenta, plan.nombre_cuenta as nombre_cuenta, plan.id_cuenta as ide_cuenta FROM plan_cuentas as plan WHERE plan.ruc_empresa = '" . $ruc_empresa . "' and plan.nivel_cuenta='5' ");
				while ($row_detalle_cuentas = mysqli_fetch_array($sql_detalle_cuentas)) {
					$ide_cuenta = $row_detalle_cuentas['ide_cuenta'];
					$codigo_cuenta = $row_detalle_cuentas['codigo_cuenta'];
					$nombre_cuenta = strtoupper($row_detalle_cuentas['nombre_cuenta']);
					$sql_registros = mysqli_query($con, "SELECT * FROM encabezado_diario as enc_dia 
			INNER JOIN detalle_diario_contable as det_dia ON enc_dia.codigo_unico=det_dia.codigo_unico 
			INNER JOIN plan_cuentas as plan ON plan.id_cuenta=det_dia.id_cuenta 
			WHERE enc_dia.ruc_empresa = '" . $ruc_empresa . "' and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') 
			between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' 
			and det_dia.id_cuenta = '" . $ide_cuenta . "' and enc_dia.estado !='ANULADO' 
			and det_dia.detalle_item LIKE '%" . $det_pro_cli . "%'");
					$registros = mysqli_num_rows($sql_registros);
					if ($registros > 0) {
			?>
					<div class="panel panel-success">
						<a class="list-group-item list-group-item-success" data-toggle="collapse" data-parent="#accordiones" href="#<?php echo $ide_cuenta; ?>"><span class="caret"></span> <b>Códido:</b> <?php echo $codigo_cuenta; ?> <b>Cuenta:</b> <?php echo strtoupper($nombre_cuenta); ?></a>
						<div id="<?php echo $ide_cuenta; ?>" class="panel-collapse collapse">

							<div class="table-responsive">
								<table class="table table-hover">
									<tr class="info">
										<th style="padding: 2px;">Fecha</th>
										<th style="padding: 2px;">Detalle</th>
										<th style="padding: 2px;">Asiento</th>
										<th style="padding: 2px;">Tipo</th>
										<th style="padding: 2px;">Debe</th>
										<th style="padding: 2px;">Haber</th>
										<th style="padding: 2px;">Saldo</th>
									</tr>
									<?php
									$saldo = 0;
									$sql_detalle_diario = mysqli_query($con, "SELECT enc_dia.tipo as tipo, enc_dia.id_diario as asiento, 
			enc_dia.fecha_asiento as fecha, det_dia.debe as debe, det_dia.haber as haber, 
			det_dia.detalle_item as detalle, enc_dia.codigo_unico as codigo_unico, enc_dia.id_diario as id_diario, 
			enc_dia.concepto_general as concepto_general, enc_dia.id_documento as id_documento, enc_dia.codigo_unico_bloque as codigo_unico_bloque 
			FROM encabezado_diario as enc_dia INNER JOIN detalle_diario_contable as det_dia ON enc_dia.codigo_unico=det_dia.codigo_unico and enc_dia.codigo_unico_bloque=det_dia.codigo_unico_bloque
			WHERE enc_dia.ruc_empresa = '" . $ruc_empresa . "' and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
			and '" . date("Y/m/d", strtotime($hasta)) . "' and det_dia.id_cuenta = '" . $ide_cuenta . "' 
			and enc_dia.estado !='ANULADO' and det_dia.detalle_item LIKE '%" . $det_pro_cli . "%' order by enc_dia.fecha_asiento asc");
									while ($row_detalle_diario = mysqli_fetch_array($sql_detalle_diario)) {
										$detalle = $row_detalle_diario['detalle'];
										$debe = $row_detalle_diario['debe'];
										$haber = $row_detalle_diario['haber'];
										$saldo += $debe - $haber;
										$asiento = $row_detalle_diario['asiento'];
										$tipo = $row_detalle_diario['tipo'];
										$id_diario = $row_detalle_diario['id_diario'];
										$id_documento = $row_detalle_diario['id_documento'];
										$codigo_unico = $row_detalle_diario['codigo_unico'];
										$codigo_unico_bloque = $row_detalle_diario['codigo_unico_bloque'];
										$fecha = date('d-m-Y', strtotime($row_detalle_diario['fecha']));
										$concepto_general = $row_detalle_diario['concepto_general'];
									?>
										<input type="hidden" value="<?php echo $asiento; ?>" id="numero_asiento<?php echo $id_diario; ?>">
										<input type="hidden" value="<?php echo $concepto_general; ?>" id="mod_concepto_general<?php echo $id_diario; ?>">
										<input type="hidden" value="<?php echo $fecha; ?>" id="mod_fecha_asiento<?php echo $id_diario; ?>">
										<input type="hidden" value="<?php echo $codigo_unico; ?>" id="mod_codigo_unico<?php echo $id_diario; ?>">
										<input type="hidden" value="<?php echo $codigo_unico_bloque; ?>" id="mod_codigo_unico_bloque<?php echo $id_diario; ?>">
										<input type="hidden" value="<?php echo $id_documento; ?>" id="mod_id_documento<?php echo $id_diario; ?>">
										<input type="hidden" value="<?php echo $tipo; ?>" id="mod_tipo<?php echo $id_diario; ?>">
										<tr>
											<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha)); ?></td>
											<td style="padding: 2px;"><?php echo $detalle; ?></td>
											<td style="padding: 2px;">
												<a class='btn btn-info btn-xs' title='Editar asiento' onclick="obtener_datos('<?php echo $id_diario; ?>');" data-toggle="modal" data-target="#NuevoDiarioContable"><i class="glyphicon glyphicon-edit"></i> <?php echo $asiento; ?></a>
											</td>
											<td style="padding: 2px;"><?php echo $tipo; ?></td>
											<td style="padding: 2px;"><?php echo number_format($debe, 2, '.', ','); ?></td>
											<td style="padding: 2px;"><?php echo number_format($haber, 2, '.', ','); ?></td>
											<td style="padding: 2px;"><?php echo number_format($saldo, 2, '.', ','); ?></td>
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
			}
		}

		//para hacer mayor de empleados
		if ($action == '8') {
			//estas variables vienen de el post de mayores de javascript
			$desde = mysqli_real_escape_string($con, (strip_tags($_REQUEST['fecha_desde'], ENT_QUOTES)));
			$hasta = mysqli_real_escape_string($con, (strip_tags($_REQUEST['fecha_hasta'], ENT_QUOTES)));
			$cuenta = mysqli_real_escape_string($con, (strip_tags($_REQUEST['cuenta'], ENT_QUOTES)));
			$pro_cli = mysqli_real_escape_string($con, (strip_tags($_REQUEST['pro_cli'], ENT_QUOTES)));

			//para un empleado y una cuenta
			$sql_empleados = mysqli_query($con, "SELECT * FROM empleados WHERE id = '" . $pro_cli . "' "); //  
			$row_empleados = mysqli_fetch_array($sql_empleados);
			$nombre_empleado = $row_empleados['nombres_apellidos'];

			$sql_cuentas = mysqli_query($con, "SELECT * FROM plan_cuentas WHERE id_cuenta = '" . $cuenta . "' "); //  
			$row_cuentas = mysqli_fetch_array($sql_cuentas);
			$codigo_cuenta = $row_cuentas['codigo_cuenta'];
			$nombre_cuenta = strtoupper($row_cuentas['nombre_cuenta']);

			if (!empty($pro_cli) && !empty($cuenta)) {
			?>
			<div class="table-responsive">
				<div class="panel panel-success">
					<div class="panel-heading" style="padding: 2px;">
						<h5>
							<p align="left"><b>Empleado: </b><?php echo $nombre_empleado; ?> <b>Código: </b><?php echo $codigo_cuenta; ?> <b>Cuenta: </b><?php echo $nombre_cuenta; ?> </p>
						</h5>
					</div>
					<table class="table table-hover">
						<tr class="info">
							<th style="padding: 2px;">Fecha</th>
							<th style="padding: 2px;">Detalle</th>
							<th style="padding: 2px;">Asiento</th>
							<th style="padding: 2px;">Tipo</th>
							<th style="padding: 2px;">Debe</th>
							<th style="padding: 2px;">Haber</th>
							<th style="padding: 2px;">Saldo</th>
						</tr>
						<?php
						$saldo = 0;
						$sql_detalle_diario = mysqli_query($con, "SELECT enc_dia.tipo as tipo, enc_dia.id_diario as asiento, 
					enc_dia.fecha_asiento as fecha, det_dia.debe as debe, det_dia.haber as haber, 
					det_dia.detalle_item as detalle, plan.codigo_cuenta as codigo_cuenta, plan.nombre_cuenta as nombre_cuenta, 
					enc_dia.codigo_unico as codigo_unico, enc_dia.id_diario as id_diario, enc_dia.concepto_general as concepto_general, 
					enc_dia.id_documento as id_documento, enc_dia.codigo_unico_bloque as codigo_unico_bloque			
					FROM encabezado_diario as enc_dia 
					INNER JOIN detalle_diario_contable as det_dia ON enc_dia.codigo_unico=det_dia.codigo_unico and enc_dia.codigo_unico_bloque=det_dia.codigo_unico_bloque
					INNER JOIN plan_cuentas as plan ON 
					 plan.id_cuenta=det_dia.id_cuenta WHERE enc_dia.ruc_empresa = '" . $ruc_empresa . "' and 
					 DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
					 and '" . date("Y/m/d", strtotime($hasta)) . "' and det_dia.id_cli_pro = '" . $pro_cli . "' 
					 and plan.id_cuenta = '" . $cuenta . "' and enc_dia.estado !='ANULADO' order by enc_dia.fecha_asiento asc, det_dia.debe asc "); //  
						while ($row_detalle_diario = mysqli_fetch_array($sql_detalle_diario)) {
							$codigo_cuenta = $row_detalle_diario['codigo_cuenta'];
							$nombre_cuenta = strtoupper($row_detalle_diario['nombre_cuenta']);
							$detalle = $row_detalle_diario['detalle'];
							$debe = $row_detalle_diario['debe'];
							$haber = $row_detalle_diario['haber'];
							$saldo += $debe - $haber;
							$asiento = $row_detalle_diario['asiento'];
							$tipo = $row_detalle_diario['tipo'];
							$id_diario = $row_detalle_diario['id_diario'];
							$id_documento = $row_detalle_diario['id_documento'];
							$codigo_unico = $row_detalle_diario['codigo_unico'];
							$codigo_unico_bloque = $row_detalle_diario['codigo_unico_bloque'];
							$fecha = date('d-m-Y', strtotime($row_detalle_diario['fecha']));
							$concepto_general = $row_detalle_diario['concepto_general'];
						?>
							<input type="hidden" value="<?php echo $asiento; ?>" id="numero_asiento<?php echo $id_diario; ?>">
							<input type="hidden" value="<?php echo $concepto_general; ?>" id="mod_concepto_general<?php echo $id_diario; ?>">
							<input type="hidden" value="<?php echo $fecha; ?>" id="mod_fecha_asiento<?php echo $id_diario; ?>">
							<input type="hidden" value="<?php echo $codigo_unico; ?>" id="mod_codigo_unico<?php echo $id_diario; ?>">
							<input type="hidden" value="<?php echo $codigo_unico_bloque; ?>" id="mod_codigo_unico_bloque<?php echo $id_diario; ?>">
							<input type="hidden" value="<?php echo $id_documento; ?>" id="mod_id_documento<?php echo $id_diario; ?>">
							<input type="hidden" value="<?php echo $tipo; ?>" id="mod_tipo<?php echo $id_diario; ?>">
							<tr>
								<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha)); ?></td>
								<td style="padding: 2px;"><?php echo $detalle; ?></td>
								<td style="padding: 2px;">
									<a class='btn btn-info btn-xs' title='Editar asiento' onclick="obtener_datos('<?php echo $id_diario; ?>');" data-toggle="modal" data-target="#NuevoDiarioContable"><i class="glyphicon glyphicon-edit"></i> <?php echo $asiento; ?></a>
								</td>
								<td style="padding: 2px;"><?php echo $tipo; ?></td>
								<td style="padding: 2px;"><?php echo number_format($debe, 2, '.', ','); ?></td>
								<td style="padding: 2px;"><?php echo number_format($haber, 2, '.', ','); ?></td>
								<td style="padding: 2px;"><?php echo number_format($saldo, 2, '.', ','); ?></td>
							</tr>
						<?php
						}
						?>
					</table>
				</div>
			</div>
		<?php
			}

			//para todos los empleados y una cuenta
			if (empty($pro_cli) && !empty($cuenta)) {
		?>
			<div class="panel-group" id="accordiones">
				<?php
				$sql_detalle_empleados = mysqli_query($con, "SELECT DISTINCT det_dia.id_cli_pro as id_empleado, emp.nombres_apellidos as nombres_apellidos
						FROM detalle_diario_contable as det_dia INNER JOIN encabezado_diario as enc_dia ON enc_dia.codigo_unico=det_dia.codigo_unico
						INNER JOIN empleados as emp ON emp.id=det_dia.id_cli_pro
						WHERE det_dia.ruc_empresa = '" . $ruc_empresa . "' and det_dia.id_cuenta='" . $cuenta . "' and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') 
									between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' order by emp.nombres_apellidos asc ");
				while ($row_detalle_empleados = mysqli_fetch_array($sql_detalle_empleados)) {
					$ide_empleado = $row_detalle_empleados['id_empleado'];
					$nombre_empleado = strtoupper($row_detalle_empleados['nombres_apellidos']);

				?>
					<div class="panel panel-success">
						<a class="list-group-item list-group-item-success" data-toggle="collapse" data-parent="#accordiones" href="#<?php echo $ide_empleado; ?>"><span class="caret"></span> <b>Empleado: </b> <?php echo $nombre_empleado; ?></a>
						<div id="<?php echo $ide_empleado; ?>" class="panel-collapse collapse">
							<div class="table-responsive">
								<table class="table table-hover">
									<tr class="info">
										<th style="padding: 2px;">Fecha</th>
										<th style="padding: 2px;">Detalle</th>
										<th style="padding: 2px;">Asiento</th>
										<th style="padding: 2px;">Tipo</th>
										<th style="padding: 2px;">Debe</th>
										<th style="padding: 2px;">Haber</th>
										<th style="padding: 2px;">Saldo</th>
									</tr>
									<?php
									$saldo = 0;
									$sql_detalle_diario = mysqli_query($con, "SELECT enc_dia.tipo as tipo, enc_dia.id_diario as asiento, 
								enc_dia.fecha_asiento as fecha, det_dia.debe as debe, det_dia.haber as haber, det_dia.detalle_item as detalle, 
								plan.codigo_cuenta as codigo_cuenta, plan.nombre_cuenta as nombre_cuenta, 
								enc_dia.codigo_unico as codigo_unico, enc_dia.id_diario as id_diario, enc_dia.concepto_general as concepto_general, 
								enc_dia.id_documento as id_documento, enc_dia.codigo_unico_bloque as codigo_unico_bloque 
								FROM encabezado_diario as enc_dia 
								INNER JOIN detalle_diario_contable as det_dia ON det_dia.codigo_unico=enc_dia.codigo_unico and enc_dia.codigo_unico_bloque=det_dia.codigo_unico_bloque
								INNER JOIN plan_cuentas as plan ON plan.id_cuenta=det_dia.id_cuenta  
								WHERE enc_dia.ruc_empresa = '" . $ruc_empresa . "' and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') 
								between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' 
								and det_dia.id_cli_pro = '" . $ide_empleado . "' and det_dia.id_cuenta='" . $cuenta . "' and enc_dia.estado !='ANULADO' order by enc_dia.fecha_asiento asc"); //  
									while ($row_detalle_diario = mysqli_fetch_array($sql_detalle_diario)) {
										$codigo_cuenta = $row_detalle_diario['codigo_cuenta'];
										$nombre_cuenta = strtoupper($row_detalle_diario['nombre_cuenta']);
										$detalle = $row_detalle_diario['detalle'];
										$debe = $row_detalle_diario['debe'];
										$haber = $row_detalle_diario['haber'];
										$saldo += $debe - $haber;
										$asiento = $row_detalle_diario['asiento'];
										$tipo = $row_detalle_diario['tipo'];
										$id_diario = $row_detalle_diario['id_diario'];
										$id_documento = $row_detalle_diario['id_documento'];
										$codigo_unico = $row_detalle_diario['codigo_unico'];
										$codigo_unico_bloque = $row_detalle_diario['codigo_unico_bloque'];
										$fecha = date('d-m-Y', strtotime($row_detalle_diario['fecha']));
										$concepto_general = $row_detalle_diario['concepto_general'];
									?>
										<input type="hidden" value="<?php echo $asiento; ?>" id="numero_asiento<?php echo $id_diario; ?>">
										<input type="hidden" value="<?php echo $concepto_general; ?>" id="mod_concepto_general<?php echo $id_diario; ?>">
										<input type="hidden" value="<?php echo $fecha; ?>" id="mod_fecha_asiento<?php echo $id_diario; ?>">
										<input type="hidden" value="<?php echo $codigo_unico; ?>" id="mod_codigo_unico<?php echo $id_diario; ?>">
										<input type="hidden" value="<?php echo $codigo_unico_bloque; ?>" id="mod_codigo_unico_bloque<?php echo $id_diario; ?>">
										<input type="hidden" value="<?php echo $id_documento; ?>" id="mod_id_documento<?php echo $id_diario; ?>">
										<input type="hidden" value="<?php echo $tipo; ?>" id="mod_tipo<?php echo $id_diario; ?>">
										<tr>
											<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha)); ?></td>
											<td style="padding: 2px;"><?php echo $detalle; ?></td>
											<td style="padding: 2px;">
												<a class='btn btn-info btn-xs' title='Editar asiento' onclick="obtener_datos('<?php echo $id_diario; ?>');" data-toggle="modal" data-target="#NuevoDiarioContable"><i class="glyphicon glyphicon-edit"></i> <?php echo $asiento; ?></a>
											</td>
											<td style="padding: 2px;"><?php echo $tipo; ?></td>
											<td style="padding: 2px;"><?php echo number_format($debe, 2, '.', ','); ?></td>
											<td style="padding: 2px;"><?php echo number_format($haber, 2, '.', ','); ?></td>
											<td style="padding: 2px;"><?php echo number_format($saldo, 2, '.', ','); ?></td>
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
				?>
			</div>
		<?php
			}

			//para todos los empleados y todas las cuentas
			if (empty($pro_cli) && empty($cuenta)) {
		?>
			<div class="panel-group" id="accordiones_empleados">
				<?php
				$sql_detalle_empleados = mysqli_query($con, "SELECT DISTINCT det_dia.id_cli_pro as id_empleado, emp.nombres_apellidos as nombres_apellidos
					FROM detalle_diario_contable as det_dia INNER JOIN encabezado_diario as enc_dia ON enc_dia.codigo_unico=det_dia.codigo_unico
					INNER JOIN empleados as emp ON emp.id=det_dia.id_cli_pro
					WHERE det_dia.ruc_empresa = '" . $ruc_empresa . "' and det_dia.id_cuenta > 0 and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') 
								between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' order by emp.nombres_apellidos asc ");
				while ($row_detalle_empleados = mysqli_fetch_array($sql_detalle_empleados)) {
					$ide_empleado = $row_detalle_empleados['id_empleado'];
					$nombre_empleado = strtoupper($row_detalle_empleados['nombres_apellidos']);
				?>
					<div class="panel panel-info">
						<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordiones_empleados" href="#<?php echo $ide_empleado; ?>"><span class="caret"></span> <b>Empleado: </b> <?php echo $nombre_empleado; ?></a>
						<div class="panel-collapse collapse" id="<?php echo $ide_empleado; ?>">

							<div class="panel-group" id="accordiones_cuentas">
								<?php
								$sql_detalle_cuentas = mysqli_query($con, "SELECT DISTINCT det_dia.id_cli_pro as ide_empleado_cuenta, 
												plan.codigo_cuenta as codigo_cuenta, plan.nombre_cuenta as nombre_cuenta, 
												plan.id_cuenta as ide_cuenta 
												FROM detalle_diario_contable as det_dia 
												INNER JOIN encabezado_diario as enc_dia ON enc_dia.codigo_unico=det_dia.codigo_unico
												INNER JOIN plan_cuentas as plan ON det_dia.id_cuenta=plan.id_cuenta 
												WHERE plan.ruc_empresa = '" . $ruc_empresa . "' and plan.nivel_cuenta='5' 
												and det_dia.id_cli_pro ='" . $ide_empleado . "' and 
												DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') 
												between '" . date("Y/m/d", strtotime($desde)) . "' 
												and '" . date("Y/m/d", strtotime($hasta)) . "' and enc_dia.estado !='ANULADO'");
								while ($row_detalle_cuentas = mysqli_fetch_array($sql_detalle_cuentas)) {
									$ide_cuenta_contables = $row_detalle_cuentas['ide_cuenta'];
									$ide_empleado_cuenta = $row_detalle_cuentas['ide_empleado_cuenta'];
									$codigo_cuenta = $row_detalle_cuentas['codigo_cuenta'];
									$nombre_cuenta = strtoupper($row_detalle_cuentas['nombre_cuenta']);

								?>
									<div class="panel panel-success">
										<a class="list-group-item list-group-item-success" data-toggle="collapse" data-parent="#accordiones_cuentas" href="#<?php echo $ide_cuenta_contables . $ide_empleado_cuenta; ?>"><span class="caret"></span> <b>Códido:</b> <?php echo $codigo_cuenta; ?> <b>Cuenta:</b> <?php echo strtoupper($nombre_cuenta); ?></a>
										<div id="<?php echo $ide_cuenta_contables . $ide_empleado_cuenta; ?>" class="panel-collapse collapse">
											<div class="table-responsive">
												<table class="table table-hover">
													<tr class="info">
														<th style="padding: 2px;">Fecha</th>
														<th style="padding: 2px;">Detalle</th>
														<th style="padding: 2px;">Asiento</th>
														<th style="padding: 2px;">Tipo</th>
														<th style="padding: 2px;">Debe</th>
														<th style="padding: 2px;">Haber</th>
														<th style="padding: 2px;">Saldo</th>
													</tr>
													<?php
													$saldo = 0;
													$sql_detalle_diario = mysqli_query($con, "SELECT enc_dia.tipo as tipo, enc_dia.id_diario as asiento, 
									enc_dia.fecha_asiento as fecha, det_dia.debe as debe, det_dia.haber as haber, 
									det_dia.detalle_item as detalle, enc_dia.codigo_unico as codigo_unico, enc_dia.id_diario as id_diario, 
									enc_dia.concepto_general as concepto_general, enc_dia.id_documento as id_documento, enc_dia.codigo_unico_bloque as codigo_unico_bloque 
									FROM encabezado_diario as enc_dia 
									INNER JOIN detalle_diario_contable as det_dia ON enc_dia.codigo_unico=det_dia.codigo_unico and enc_dia.codigo_unico_bloque=det_dia.codigo_unico_bloque
									WHERE enc_dia.ruc_empresa = '" . $ruc_empresa . "' 
									and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
									and '" . date("Y/m/d", strtotime($hasta)) . "' and det_dia.id_cuenta = '" . $ide_cuenta_contables . "' 
									and det_dia.id_cli_pro='" . $ide_proveedor_cuenta . "' and enc_dia.estado !='ANULADO' order by enc_dia.fecha_asiento asc");
													while ($row_detalle_diario = mysqli_fetch_array($sql_detalle_diario)) {
														$detalle = $row_detalle_diario['detalle'];
														$debe = $row_detalle_diario['debe'];
														$haber = $row_detalle_diario['haber'];
														$saldo += $debe - $haber;
														$asiento = $row_detalle_diario['asiento'];
														$tipo = $row_detalle_diario['tipo'];
														$id_diario = $row_detalle_diario['id_diario'];
														$id_documento = $row_detalle_diario['id_documento'];
														$codigo_unico = $row_detalle_diario['codigo_unico'];
														$codigo_unico_bloque = $row_detalle_diario['codigo_unico_bloque'];
														$fecha = date('d-m-Y', strtotime($row_detalle_diario['fecha']));
														$concepto_general = $row_detalle_diario['concepto_general'];
													?>
														<input type="hidden" value="<?php echo $asiento; ?>" id="numero_asiento<?php echo $id_diario; ?>">
														<input type="hidden" value="<?php echo $concepto_general; ?>" id="mod_concepto_general<?php echo $id_diario; ?>">
														<input type="hidden" value="<?php echo $fecha; ?>" id="mod_fecha_asiento<?php echo $id_diario; ?>">
														<input type="hidden" value="<?php echo $codigo_unico; ?>" id="mod_codigo_unico<?php echo $id_diario; ?>">
														<input type="hidden" value="<?php echo $codigo_unico_bloque; ?>" id="mod_codigo_unico_bloque<?php echo $id_diario; ?>">
														<input type="hidden" value="<?php echo $id_documento; ?>" id="mod_id_documento<?php echo $id_diario; ?>">
														<input type="hidden" value="<?php echo $tipo; ?>" id="mod_tipo<?php echo $id_diario; ?>">
														<tr>
															<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha)); ?></td>
															<td style="padding: 2px;"><?php echo $detalle; ?></td>
															<td style="padding: 2px;">
																<a class='btn btn-info btn-xs' title='Editar asiento' onclick="obtener_datos('<?php echo $id_diario; ?>');" data-toggle="modal" data-target="#NuevoDiarioContable"><i class="glyphicon glyphicon-edit"></i> <?php echo $asiento; ?></a>
															</td>
															<td style="padding: 2px;"><?php echo $tipo; ?></td>
															<td style="padding: 2px;"><?php echo number_format($debe, 2, '.', ','); ?></td>
															<td style="padding: 2px;"><?php echo number_format($haber, 2, '.', ','); ?></td>
															<td style="padding: 2px;"><?php echo number_format($saldo, 2, '.', ','); ?></td>
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
								?>
							</div>
						</div>
					</div>
				<?php
				}
				?>
			</div>
		<?php
			}

			//para un empleado y todas las cuentas
			if (!empty($pro_cli) && empty($cuenta)) {
		?>
			<div class="panel-group" id="accordiones_etc">
				<?php
				$sql_detalle_cuentas = mysqli_query($con, "SELECT DISTINCT det_dia.id_cli_pro as ide_empleado_cuenta, 
								plan.codigo_cuenta as codigo_cuenta, plan.nombre_cuenta as nombre_cuenta, 
								plan.id_cuenta as ide_cuenta 
								FROM detalle_diario_contable as det_dia 
								INNER JOIN encabezado_diario as enc_dia ON enc_dia.codigo_unico=det_dia.codigo_unico
								INNER JOIN plan_cuentas as plan ON det_dia.id_cuenta=plan.id_cuenta 
								WHERE det_dia.ruc_empresa = '" . $ruc_empresa . "' and plan.nivel_cuenta='5' 
								and det_dia.id_cli_pro='" . $pro_cli . "' and 
								DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') 
								between '" . date("Y/m/d", strtotime($desde)) . "' 
								and '" . date("Y/m/d", strtotime($hasta)) . "' and enc_dia.estado !='ANULADO'");

				while ($row_detalle_cuentas = mysqli_fetch_array($sql_detalle_cuentas)) {
					$ide_cuenta_contables = $row_detalle_cuentas['ide_cuenta'];
					$ide_empleado_cuenta = $row_detalle_cuentas['ide_empleado_cuenta'];
					$codigo_cuenta = $row_detalle_cuentas['codigo_cuenta'];
					$nombre_cuenta = strtoupper($row_detalle_cuentas['nombre_cuenta']);

				?>
					<div class="panel panel-success">
						<a class="list-group-item list-group-item-success" data-toggle="collapse" data-parent="#accordiones_ptc" href="#<?php echo $ide_cuenta_contables . $ide_empleado_cuenta; ?>"><span class="caret"></span> <b>Códido:</b> <?php echo $codigo_cuenta; ?> <b>Cuenta:</b> <?php echo $nombre_cuenta; ?></a>
						<div id="<?php echo $ide_cuenta_contables . $ide_empleado_cuenta; ?>" class="panel-collapse collapse">
							<div class="table-responsive">
								<table class="table table-hover">
									<tr class="info">
										<th style="padding: 2px;">Fecha</th>
										<th style="padding: 2px;">Detalle</th>
										<th style="padding: 2px;">Asiento</th>
										<th style="padding: 2px;">Tipo</th>
										<th style="padding: 2px;">Debe</th>
										<th style="padding: 2px;">Haber</th>
										<th style="padding: 2px;">Saldo</th>
									</tr>
									<?php
									$saldo = 0;
									$sql_detalle_diario = mysqli_query($con, "SELECT enc_dia.tipo as tipo, enc_dia.id_diario as asiento, 
														enc_dia.fecha_asiento as fecha, det_dia.debe as debe, det_dia.haber as haber, 
														det_dia.detalle_item as detalle, enc_dia.codigo_unico as codigo_unico, enc_dia.id_diario as id_diario, 
														enc_dia.concepto_general as concepto_general, enc_dia.id_documento as id_documento, enc_dia.codigo_unico_bloque as codigo_unico_bloque 
														FROM encabezado_diario as enc_dia 
														INNER JOIN detalle_diario_contable as det_dia ON enc_dia.codigo_unico=det_dia.codigo_unico and enc_dia.codigo_unico_bloque=det_dia.codigo_unico_bloque
														WHERE enc_dia.ruc_empresa = '" . $ruc_empresa . "' 
														and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
														and '" . date("Y/m/d", strtotime($hasta)) . "' and det_dia.id_cuenta = '" . $ide_cuenta_contables . "' 
														and det_dia.id_cli_pro='" . $ide_empleado_cuenta . "' and enc_dia.estado !='ANULADO' order by enc_dia.fecha_asiento asc");

									while ($row_detalle_diario = mysqli_fetch_array($sql_detalle_diario)) {
										$fecha = $row_detalle_diario['fecha'];
										$detalle = $row_detalle_diario['detalle'];
										$debe = $row_detalle_diario['debe'];
										$haber = $row_detalle_diario['haber'];
										$saldo += $debe - $haber;
										$asiento = $row_detalle_diario['asiento'];
										$tipo = $row_detalle_diario['tipo'];
										$id_diario = $row_detalle_diario['id_diario'];
										$id_documento = $row_detalle_diario['id_documento'];
										$codigo_unico = $row_detalle_diario['codigo_unico'];
										$codigo_unico_bloque = $row_detalle_diario['codigo_unico_bloque'];
										$fecha = date('d-m-Y', strtotime($row_detalle_diario['fecha']));
										$concepto_general = $row_detalle_diario['concepto_general'];
									?>
										<input type="hidden" value="<?php echo $asiento; ?>" id="numero_asiento<?php echo $id_diario; ?>">
										<input type="hidden" value="<?php echo $concepto_general; ?>" id="mod_concepto_general<?php echo $id_diario; ?>">
										<input type="hidden" value="<?php echo $fecha; ?>" id="mod_fecha_asiento<?php echo $id_diario; ?>">
										<input type="hidden" value="<?php echo $codigo_unico; ?>" id="mod_codigo_unico<?php echo $id_diario; ?>">
										<input type="hidden" value="<?php echo $codigo_unico_bloque; ?>" id="mod_codigo_unico_bloque<?php echo $id_diario; ?>">
										<input type="hidden" value="<?php echo $id_documento; ?>" id="mod_id_documento<?php echo $id_diario; ?>">
										<input type="hidden" value="<?php echo $tipo; ?>" id="mod_tipo<?php echo $id_diario; ?>">
										<tr>
											<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha)); ?></td>
											<td style="padding: 2px;"><?php echo $detalle; ?></td>
											<td style="padding: 2px;">
												<a class='btn btn-info btn-xs' title='Editar asiento' onclick="obtener_datos('<?php echo $id_diario; ?>');" data-toggle="modal" data-target="#NuevoDiarioContable"><i class="glyphicon glyphicon-edit"></i> <?php echo $asiento; ?></a>
											</td>
											<td style="padding: 2px;"><?php echo $tipo; ?></td>
											<td style="padding: 2px;"><?php echo number_format($debe, 2, '.', ','); ?></td>
											<td style="padding: 2px;"><?php echo number_format($haber, 2, '.', ','); ?></td>
											<td style="padding: 2px;"><?php echo number_format($saldo, 2, '.', ','); ?></td>
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
				?>
			</div>
	<?php
			}
		}

		//funcion para generar el balance
		function generar_balance($con, $ruc_empresa, $id_usuario, $desde, $hasta, $cuenta_inicial, $cuenta_final, $id_proyecto)
		{
			if ($id_proyecto > 0) {
				$condicion_proyecto = " and plan.id_proyecto=" . $id_proyecto;
			} else {
				$condicion_proyecto = "";
			}

			$sql_delete = mysqli_query($con, "DELETE FROM balances_tmp WHERE ruc_empresa= '" . $ruc_empresa . "' ");

			$sql_detalle_diario = mysqli_query($con, "INSERT INTO balances_tmp (id_balance, codigo_cuenta, nombre_cuenta, nivel_cuenta, valor, ruc_empresa, id_usuario) 
			SELECT null, plan.codigo_cuenta, plan.nombre_cuenta, '5', sum(det_dia.debe-det_dia.haber), '" . $ruc_empresa . "', '" . $id_usuario . "' 
			FROM detalle_diario_contable as det_dia 
			INNER JOIN encabezado_diario as enc_dia 
			ON enc_dia.codigo_unico=det_dia.codigo_unico 
			INNER JOIN plan_cuentas as plan 
			ON plan.id_cuenta=det_dia.id_cuenta 
			WHERE plan.ruc_empresa = '" . $ruc_empresa . "' and enc_dia.ruc_empresa = '" . $ruc_empresa . "' and det_dia.ruc_empresa = '" . $ruc_empresa . "' and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' and mid(plan.codigo_cuenta,1,1) between '" . $cuenta_inicial . "' and '" . $cuenta_final . "' $condicion_proyecto and enc_dia.estado !='ANULADO' group by plan.id_cuenta order by plan.codigo_cuenta asc");

			$sql_nivel_uno = mysqli_query($con, "INSERT INTO balances_tmp (id_balance, codigo_cuenta, nombre_cuenta, nivel_cuenta, valor, ruc_empresa, id_usuario) 
			SELECT null, plan.codigo_cuenta, plan.nombre_cuenta, '1', sum(tmp.valor), '" . $ruc_empresa . "', '" . $id_usuario . "' FROM balances_tmp as tmp INNER JOIN plan_cuentas as plan ON plan.codigo_cuenta=mid(tmp.codigo_cuenta,1,1) WHERE plan.ruc_empresa = '" . $ruc_empresa . "' and tmp.ruc_empresa = '" . $ruc_empresa . "' and plan.nivel_cuenta='1' group by mid(tmp.codigo_cuenta,1,1) ");

			$sql_nivel_dos = mysqli_query($con, "INSERT INTO balances_tmp (id_balance, codigo_cuenta, nombre_cuenta, nivel_cuenta, valor, ruc_empresa, id_usuario) 
			SELECT null, plan.codigo_cuenta, plan.nombre_cuenta, '2', sum(tmp.valor), '" . $ruc_empresa . "', '" . $id_usuario . "' FROM balances_tmp as tmp INNER JOIN plan_cuentas as plan ON plan.codigo_cuenta=mid(tmp.codigo_cuenta,1,3) WHERE plan.ruc_empresa = '" . $ruc_empresa . "' and tmp.ruc_empresa = '" . $ruc_empresa . "' and plan.nivel_cuenta='2' group by mid(tmp.codigo_cuenta,1,3) ");

			$sql_nivel_tres = mysqli_query($con, "INSERT INTO balances_tmp (id_balance, codigo_cuenta, nombre_cuenta, nivel_cuenta, valor, ruc_empresa, id_usuario) 
			SELECT null, plan.codigo_cuenta, plan.nombre_cuenta, '3', sum(tmp.valor), '" . $ruc_empresa . "', '" . $id_usuario . "' FROM balances_tmp as tmp INNER JOIN plan_cuentas as plan ON plan.codigo_cuenta=mid(tmp.codigo_cuenta,1,6) WHERE plan.ruc_empresa = '" . $ruc_empresa . "' and tmp.ruc_empresa = '" . $ruc_empresa . "' and plan.nivel_cuenta='3' group by mid(tmp.codigo_cuenta,1,6) ");

			$sql_nivel_cuatro = mysqli_query($con, "INSERT INTO balances_tmp (id_balance, codigo_cuenta, nombre_cuenta, nivel_cuenta, valor, ruc_empresa, id_usuario) 
			SELECT null, plan.codigo_cuenta, plan.nombre_cuenta, '4', sum(tmp.valor), '" . $ruc_empresa . "', '" . $id_usuario . "' FROM balances_tmp as tmp INNER JOIN plan_cuentas as plan ON plan.codigo_cuenta=mid(tmp.codigo_cuenta,1,9) WHERE plan.ruc_empresa = '" . $ruc_empresa . "' and tmp.ruc_empresa = '" . $ruc_empresa . "' and plan.nivel_cuenta='4' group by mid(tmp.codigo_cuenta,1,9) ");

			$sql_delete = mysqli_query($con, "DELETE FROM balances_tmp WHERE ruc_empresa= '" . $ruc_empresa . "' and valor = 0 ");
		}

		function generar_balance_periodos($con, $ruc_empresa, $desde, $hasta, $cuenta_inicial, $cuenta_final, $id_proyecto)
		{

			if ($id_proyecto > 0) {
				$condicion_proyecto = " and plan.id_proyecto=" . $id_proyecto;
			} else {
				$condicion_proyecto = "";
			}
			$resultados = array();
			$sql_detalle_diario = mysqli_query($con, "SELECT DATE_FORMAT(enc_dia.fecha_asiento, '%m') AS mes, DATE_FORMAT(enc_dia.fecha_asiento, '%y') AS ano, plan.codigo_cuenta as codigo, plan.nombre_cuenta as cuenta, sum(det_dia.debe-det_dia.haber) as saldo 
			FROM detalle_diario_contable as det_dia INNER JOIN encabezado_diario as enc_dia ON enc_dia.codigo_unico=det_dia.codigo_unico 
			INNER JOIN plan_cuentas as plan ON plan.id_cuenta=det_dia.id_cuenta 
			WHERE plan.ruc_empresa = '" . $ruc_empresa . "' and enc_dia.ruc_empresa = '" . $ruc_empresa . "' 
			and det_dia.ruc_empresa = '" . $ruc_empresa . "' and 
			DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
			and '" . date("Y/m/d", strtotime($hasta)) . "' and mid(plan.codigo_cuenta,1,1) between '" . $cuenta_inicial . "' 
			and '" . $cuenta_final . "' and enc_dia.estado !='ANULADO' $condicion_proyecto group by plan.id_cuenta, MONTH(enc_dia.fecha_asiento), YEAR(enc_dia.fecha_asiento) order by plan.codigo_cuenta asc, MONTH(enc_dia.fecha_asiento), YEAR(enc_dia.fecha_asiento) asc");

			while ($row = mysqli_fetch_assoc($sql_detalle_diario)) {
				$codigo = $row['codigo'];
				$cuenta = $row['cuenta'];
				$mes = $row['mes'];
				$ano = $row['ano'];
				$saldo = $row['saldo'];
				$resultados[$codigo][$cuenta][$ano][$mes] = array('saldo' => $saldo, 'ano' => $ano, 'mes' => $mes);
			}
			return $resultados;
		}

		function generar_resultados_presupuestado($con, $ruc_empresa, $desde, $hasta, $id_proyecto)
		{

			if ($id_proyecto > 0) {
				$condicion_proyecto = " and plan.id_proyecto=" . $id_proyecto;
			} else {
				$condicion_proyecto = "";
			}
			$resultados = array();
			$sql_detalle_diario = mysqli_query($con, "SELECT plan.codigo_cuenta as codigo, plan.nombre_cuenta as cuenta, 
		round(sum(det.valor),2) as valor, plan.id_cuenta as id_cuenta
		FROM detalle_presupuesto as det 
		INNER JOIN encabezado_presupuesto as enc ON enc.id=det.id_pre 
		INNER JOIN plan_cuentas as plan ON plan.id_cuenta=det.id_cuenta 
		WHERE plan.ruc_empresa = '" . $ruc_empresa . "' and enc.ruc_empresa = '" . $ruc_empresa . "' 
		and DATE_FORMAT(enc.fecha_inicio, '%Y/%m/%d') >= '" . date("Y/m/d", strtotime($desde)) . "' 
		and DATE_FORMAT(enc.fecha_fin, '%Y/%m/%d') <= '" . date("Y/m/d", strtotime($hasta)) . "' $condicion_proyecto group by det.codigo_cuenta order by plan.codigo_cuenta asc");

			while ($row = mysqli_fetch_assoc($sql_detalle_diario)) {
				$codigo = $row['codigo'];
				$cuenta = $row['cuenta'];
				$valor = $row['valor'];
				$id_cuenta = $row['id_cuenta'];
				$resultados[$codigo] = array('cuenta' => $cuenta, 'valor' => $valor, 'id_cuenta' => $id_cuenta);
			}
			return $resultados;
		}



		function utilidad_perdida($con, $ruc_empresa, $id_usuario, $desde, $hasta, $id_proyecto)
		{

			$ingresos = 0;
			$costos = 0;
			$gastos = 0;
			generar_balance($con, $ruc_empresa, $id_usuario, $desde, $hasta, '4', '6', $id_proyecto);
			$sql_ingresos = mysqli_query($con, "SELECT round(sum(valor*-1),2) as valor  FROM balances_tmp WHERE ruc_empresa = '" . $ruc_empresa . "' and nivel_cuenta='1' and mid(codigo_cuenta,1,1)=4 group by codigo_cuenta");
			$row_ingresos = mysqli_fetch_array($sql_ingresos);
			$ingresos = isset($row_ingresos['valor']) ? $row_ingresos['valor'] : 0;

			$sql_costos = mysqli_query($con, "SELECT round(sum(valor),2) as valor  FROM balances_tmp WHERE ruc_empresa = '" . $ruc_empresa . "' and nivel_cuenta='1' and mid(codigo_cuenta,1,1)=5 group by codigo_cuenta");
			$row_costos = mysqli_fetch_array($sql_costos);
			$costos = isset($row_costos['valor']) ? $row_costos['valor'] : 0;

			$sql_gastos = mysqli_query($con, "SELECT round(sum(valor),2) as valor  FROM balances_tmp WHERE ruc_empresa = '" . $ruc_empresa . "' and nivel_cuenta='1' and mid(codigo_cuenta,1,1)=6 group by codigo_cuenta");
			$row_gastos = mysqli_fetch_array($sql_gastos);
			$gastos = isset($row_gastos['valor']) ? $row_gastos['valor'] : 0;


			$utilidad = ($ingresos - $costos - $gastos);

			if ($ingresos > ($costos + $gastos)) {
				$resultado = "UTILIDAD DEL EJERCICIO";
				$utilidad = $utilidad;
			} else {
				$resultado = "PÉRDIDA DEL EJERCICIO";
				$utilidad = $utilidad;
			}

			$respuesta = array('resultado' => $resultado, 'valor' => $utilidad);
			return $respuesta;
		}

		function resumen_activo_pasivo_patrimonio($con, $ruc_empresa, $id_usuario, $desde, $hasta, $id_proyecto)
		{
			$activo = 0;
			$pasivo = 0;
			$patrimonio = 0;
			generar_balance($con, $ruc_empresa, $id_usuario, $desde, $hasta, '1', '3', $id_proyecto);
			$sql_activo = mysqli_query($con, "SELECT round(sum(valor),2) as valor  FROM balances_tmp WHERE ruc_empresa = '" . $ruc_empresa . "' and nivel_cuenta='1' and mid(codigo_cuenta,1,1)=1 group by codigo_cuenta");
			$row_activos = mysqli_fetch_array($sql_activo);
			$activo = isset($row_activos['valor']) ? $row_activos['valor'] : 0;

			$sql_pasivo = mysqli_query($con, "SELECT round(sum(valor*-1),2) as valor  FROM balances_tmp WHERE ruc_empresa = '" . $ruc_empresa . "' and nivel_cuenta='1' and mid(codigo_cuenta,1,1)=2 group by codigo_cuenta");
			$row_pasivo = mysqli_fetch_array($sql_pasivo);
			$pasivo = isset($row_pasivo['valor']) ? $row_pasivo['valor'] : 0;

			$sql_patrimonio = mysqli_query($con, "SELECT round(sum(valor*-1),2) as valor  FROM balances_tmp WHERE ruc_empresa = '" . $ruc_empresa . "' and nivel_cuenta='1' and mid(codigo_cuenta,1,1)=3 group by codigo_cuenta");
			$row_patrimonio = mysqli_fetch_array($sql_patrimonio);
			$patrimonio = isset($row_patrimonio['valor']) ? $row_patrimonio['valor'] : 0;

			$respuesta = array('activo' => $activo, 'pasivo' => $pasivo, 'patrimonio' => $patrimonio);
			return $respuesta;
		}

		function control_errores($con, $ruc_empresa, $desde, $hasta, $estilo)
		{

			$sql_empresas = mysqli_query($con, " SELECT id FROM empresas WHERE ruc = '" . $ruc_empresa . "'");
			$row_empresas = mysqli_fetch_array($sql_empresas);
			$id_empresa = $row_empresas['id'];

			$mensajes = array();
			$sql_compras = mysqli_query($con, " SELECT COUNT(*) AS total_registros, DATE_FORMAT(fecha_compra, '%m-%Y') AS mes 
				FROM encabezado_compra WHERE ruc_empresa = '" . $ruc_empresa . "' AND DATE_FORMAT(fecha_compra, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' AND id_registro_contable = 0 GROUP BY mes");
			foreach ($sql_compras as $resultado) {
				$mensajes[] = str_pad($resultado['total_registros'], 9, "*", STR_PAD_LEFT) . " Registros de compras, gastos y servicios por contabilizar en el mes: " . $resultado['mes'];
			}

			$sql_ventas = mysqli_query($con, " SELECT COUNT(*) AS total_registros, DATE_FORMAT(fecha_factura, '%m-%Y') AS mes 
				FROM encabezado_factura WHERE ruc_empresa = '" . $ruc_empresa . "' AND DATE_FORMAT(fecha_factura, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' AND id_registro_contable = 0 and estado_sri !='ANULADA' GROUP BY mes");
			foreach ($sql_ventas as $resultado) {
				$mensajes[] = str_pad($resultado['total_registros'], 9, "*", STR_PAD_LEFT) . " Registros de ventas por contabilizar en el mes: " . $resultado['mes'];
			}

			$sql_recibos = mysqli_query($con, " SELECT COUNT(*) AS total_registros, DATE_FORMAT(fecha_recibo, '%m-%Y') AS mes 
				FROM encabezado_recibo WHERE ruc_empresa = '" . $ruc_empresa . "' AND DATE_FORMAT(fecha_recibo, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' AND id_registro_contable = 0 and status !='2' GROUP BY mes");
			foreach ($sql_recibos as $resultado) {
				$mensajes[] = str_pad($resultado['total_registros'], 9, "*", STR_PAD_LEFT) . " Registros de recibos de ventas por contabilizar en el mes: " . $resultado['mes'];
			}

			$sql_retenciones_ventas = mysqli_query($con, " SELECT COUNT(*) AS total_registros, DATE_FORMAT(enc.fecha_emision, '%m-%Y') AS mes 
				FROM encabezado_retencion_venta as enc INNER JOIN cuerpo_retencion_venta as cue ON cue.codigo_unico=enc.codigo_unico WHERE enc.ruc_empresa = '" . $ruc_empresa . "' AND DATE_FORMAT(enc.fecha_emision, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' AND enc.id_registro_contable = 0 and cue.valor_retenido>0 GROUP BY mes");
			foreach ($sql_retenciones_ventas as $resultado) {
				$mensajes[] = str_pad($resultado['total_registros'], 9, "*", STR_PAD_LEFT) . " Registros de retenciones en ventas por contabilizar en el mes: " . $resultado['mes'];
			}

			$sql_retenciones_compras = mysqli_query($con, " SELECT COUNT(*) AS total_registros, DATE_FORMAT(fecha_emision, '%m-%Y') AS mes 
				FROM encabezado_retencion WHERE ruc_empresa = '" . $ruc_empresa . "' AND DATE_FORMAT(fecha_emision, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' AND id_registro_contable = 0 and estado_sri !='ANULADA' and total_retencion > 0 GROUP BY mes");
			foreach ($sql_retenciones_compras as $resultado) {
				$mensajes[] = str_pad($resultado['total_registros'], 9, "*", STR_PAD_LEFT) . " Registros de retenciones en compras por contabilizar en el mes: " . $resultado['mes'];
			}

			$sql_nc = mysqli_query($con, " SELECT COUNT(*) AS total_registros, DATE_FORMAT(fecha_nc, '%m-%Y') AS mes 
				FROM encabezado_nc WHERE ruc_empresa = '" . $ruc_empresa . "' AND DATE_FORMAT(fecha_nc, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' AND id_registro_contable = 0 and estado_sri !='ANULADA' GROUP BY mes");
			foreach ($sql_nc as $resultado) {
				$mensajes[] = str_pad($resultado['total_registros'], 9, "*", STR_PAD_LEFT) . " Registros de notas de crédito por contabilizar en el mes: " . $resultado['mes'];
			}

			$sql_ingresos = mysqli_query($con, " SELECT COUNT(*) AS total_registros, DATE_FORMAT(fecha_ing_egr, '%m-%Y') AS mes 
				FROM ingresos_egresos WHERE ruc_empresa = '" . $ruc_empresa . "' AND DATE_FORMAT(fecha_ing_egr, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' AND codigo_contable = 0 and estado !='ANULADO' and tipo_ing_egr='INGRESO' and valor_ing_egr>0 GROUP BY mes");
			foreach ($sql_ingresos as $resultado) {
				$mensajes[] = str_pad($resultado['total_registros'], 9, "*", STR_PAD_LEFT) . " Registros de ingresos por contabilizar en el mes: " . $resultado['mes'];
			}

			$sql_egresos = mysqli_query($con, " SELECT COUNT(*) AS total_registros, DATE_FORMAT(fecha_ing_egr, '%m-%Y') AS mes 
				FROM ingresos_egresos WHERE ruc_empresa = '" . $ruc_empresa . "' AND DATE_FORMAT(fecha_ing_egr, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' AND codigo_contable = 0 and estado !='ANULADO' and tipo_ing_egr='EGRESO' and valor_ing_egr>0 GROUP BY mes");
			foreach ($sql_egresos as $resultado) {
				$mensajes[] = str_pad($resultado['total_registros'], 9, "*", STR_PAD_LEFT) . " Registros de egresos por contabilizar en el mes: " . $resultado['mes'];
			}

			$sql_roles = mysqli_query($con, " SELECT COUNT(*) AS total_registros, SUBSTRING(rol.mes_ano,4,4) AS anio 
				FROM detalle_rolespago as det INNER JOIN rolespago as rol ON rol.id=det.id_rol WHERE rol.id_empresa = '" . $id_empresa . "' AND SUBSTRING(rol.mes_ano,4,4) = '" . date("Y", strtotime($desde)) . "' and SUBSTRING(rol.mes_ano,4,4) = '" . date("Y", strtotime($hasta)) . "' AND det.id_registro_contable = 0 and rol.status='1' GROUP BY SUBSTRING(rol.mes_ano,4,4)");
			foreach ($sql_roles as $resultado) {
				$mensajes[] = str_pad($resultado['total_registros'], 9, "*", STR_PAD_LEFT) . " Registros de roles de pago por contabilizar en el año: " . $resultado['anio'];
			}

			$sql_asientos = mysqli_query($con, "SELECT enc_dia.id_diario as numero_asiento, 
		round(sum(det_dia.debe-det_dia.haber),2) as diferencia 
		FROM encabezado_diario as enc_dia 
		INNER JOIN detalle_diario_contable as det_dia ON enc_dia.codigo_unico=det_dia.codigo_unico 
		WHERE enc_dia.ruc_empresa = '" . $ruc_empresa . "' and year(enc_dia.fecha_asiento)>2022 and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' and enc_dia.estado !='ANULADO' group by det_dia.codigo_unico");
			while ($row_asientos = mysqli_fetch_array($sql_asientos)) {
				$diferencias = $row_asientos['diferencia'];
				if ($diferencias != 0) {
					$mensajes[] = "Error en asiento No. " . $row_asientos['numero_asiento'] . "<a href='../modulos/libro_diario.php' target='_blank'> Ir al diario >></a>";
				}
			}

			//para ver diferencia en el asiento de ingreso
			$sql_dif_ing = mysqli_query($con, "SELECT enc_dia.id_diario as id_diario, enc_dia.id_diario as numero_asiento, 
		round(ing.valor_ing_egr - (select sum(det.debe) from detalle_diario_contable as det where det.codigo_unico=concat('ING', ing.id_ing_egr) group by det.codigo_unico),2) as diferencia, ing.numero_ing_egr as documento 
		FROM encabezado_diario as enc_dia 
		INNER JOIN ingresos_egresos as ing ON concat('ING', ing.id_ing_egr)=enc_dia.codigo_unico
		WHERE enc_dia.ruc_empresa = '" . $ruc_empresa . "' and year(enc_dia.fecha_asiento)>2022 
		and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
		and '" . date("Y/m/d", strtotime($hasta)) . "' and enc_dia.estado !='ANULADO' ");
			while ($row_dif_ing = mysqli_fetch_array($sql_dif_ing)) {
				$diferencias = $row_dif_ing['diferencia'];
				if ($diferencias != 0) {
					$mensajes[] = "Diferencias $ " . $row_dif_ing['diferencia'] * -1 . ", entre ingreso " . $row_dif_ing['documento'] . " y asiento contable No. " . $row_dif_ing['id_diario'] . "<a href='../modulos/libro_diario.php' target='_blank'> Ir al diario >></a>";
				}
			}

			//para ver diferencia en el asiento de egreso
			$sql_dif_egr = mysqli_query($con, "SELECT enc_dia.id_diario as id_diario, 
		round(ing.valor_ing_egr - (select sum(det.debe) from detalle_diario_contable as det where det.codigo_unico=concat('EGR', ing.id_ing_egr) group by det.codigo_unico),2) as diferencia, ing.numero_ing_egr as documento 
		FROM encabezado_diario as enc_dia 
		INNER JOIN ingresos_egresos as ing ON concat('EGR',ing.id_ing_egr)=enc_dia.codigo_unico
		WHERE enc_dia.ruc_empresa = '" . $ruc_empresa . "' and year(enc_dia.fecha_asiento)>2022
		and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
		and '" . date("Y/m/d", strtotime($hasta)) . "' and enc_dia.estado !='ANULADO' ");
			while ($row_dif_ing = mysqli_fetch_array($sql_dif_egr)) {
				$diferencias = $row_dif_ing['diferencia'];
				if ($diferencias != 0) {
					$mensajes[] = " Diferencias $ " . $row_dif_ing['diferencia'] * -1 . ", entre egreso " . $row_dif_ing['documento'] . " y asiento contable No. " . $row_dif_ing['id_diario'] . "<a href='../modulos/libro_diario.php' target='_blank'> Ir al diario >></a>";
				}
			}

			//para ver diferencia en el asiento de retenciones de ventas
			$sql_dif_rv = mysqli_query($con, "SELECT enc_dia.id_diario as id_diario, 
		round((select sum(valor_retenido) from cuerpo_retencion_venta where codigo_unico = ret.codigo_unico group by codigo_unico) - sum(det.debe) ,2) as diferencia_ret_ven, concat(ret.serie_retencion, '-', lpad(ret.secuencial_retencion,9,'0')) as documento
		FROM encabezado_diario as enc_dia  
		INNER JOIN encabezado_retencion_venta as ret ON concat('RETVEN', ret.id_encabezado_retencion)=enc_dia.codigo_unico
		INNER JOIN detalle_diario_contable as det ON det.codigo_unico=concat('RETVEN', ret.id_encabezado_retencion)
		WHERE enc_dia.ruc_empresa = '" . $ruc_empresa . "' and year(enc_dia.fecha_asiento)>2022 and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') 
		between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' 
		and enc_dia.estado !='ANULADO' group by det.codigo_unico");
			while ($row_dif_ing = mysqli_fetch_array($sql_dif_rv)) {
				$diferencias = $row_dif_ing['diferencia_ret_ven'];
				if ($diferencias != 0) {
					$mensajes[] = " Diferencias $ " . $row_dif_ing['diferencia_ret_ven'] * -1 . ", en total retención por ventas " . $row_dif_ing['documento'] . " y asiento contable No. " . $row_dif_ing['id_diario'] . "<a href='../modulos/libro_diario.php' target='_blank'> Ir al diario >></a>";
				}
			}

			//retenciones de compras
			$sql_dif_rc = mysqli_query($con, "SELECT enc_dia.id_diario as id_diario, 
		round(ret.total_retencion - sum(det.debe),2) as diferencia, concat(ret.serie_retencion, '-',lpad(ret.secuencial_retencion,9,'0')) as documento 
		FROM encabezado_diario as enc_dia  
		INNER JOIN encabezado_retencion as ret ON concat('RETCOM', ret.id_encabezado_retencion)=enc_dia.codigo_unico
		INNER JOIN detalle_diario_contable as det ON det.codigo_unico=concat('RETCOM', ret.id_encabezado_retencion)
		WHERE enc_dia.ruc_empresa = '" . $ruc_empresa . "' and year(enc_dia.fecha_asiento)>2022 and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') 
		between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' 
		and enc_dia.estado !='ANULADO' group by det.codigo_unico");
			while ($row_dif_ing = mysqli_fetch_array($sql_dif_rc)) {
				$diferencias = $row_dif_ing['diferencia'];
				if ($diferencias != 0) {
					$mensajes[] = " Diferencias $ " . $row_dif_ing['diferencia'] * -1 . ", en total retenido por compras " . $row_dif_ing['documento'] . " y asiento contable No. " . $row_dif_ing['id_diario'] . "<a href='../modulos/libro_diario.php' target='_blank'> Ir al diario >></a>";
				}
			}

			if ($estilo == "pantalla") {
				$respuesta = "";
				if (!empty($mensajes)) {
					$respuesta = '<li style="margin-bottom: 10px; margin-top: -10px; padding: 3px;" class="list-group-item list-group-item-danger"><h5><b>';
					foreach ($mensajes as $value) {
						$respuesta .= $value . "<br>";
					}
					$respuesta .= '</b></h5></li>';
				}
				return $respuesta;
			}
			if ($estilo == "excel") {
				$respuesta = "";
				if (!empty($mensajes)) {
					foreach ($mensajes as $value) {
						$respuesta .= $value . " ";
					}
					$respuesta .= $respuesta;
				}
				return $respuesta;
			}
		}


		function saldo_cuenta($con, $ruc_empresa, $desde, $hasta, $cuenta)
		{
			$sql_detalle_diario = mysqli_query($con, "SELECT round(sum(det_dia.debe - det_dia.haber),2) as saldo
			FROM detalle_diario_contable as det_dia INNER JOIN encabezado_diario as enc_dia 
			ON enc_dia.codigo_unico=det_dia.codigo_unico 
			WHERE enc_dia.ruc_empresa = '" . $ruc_empresa . "' and 
			DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
			and '" . date("Y/m/d", strtotime($hasta)) . "' and det_dia.id_cuenta = '" . $cuenta . "' 
			and enc_dia.estado !='ANULADO' GROUP BY det_dia.id_cuenta ");
			$row_asientos = mysqli_fetch_array($sql_detalle_diario);
			$saldo = isset($row_asientos['saldo']) ? $row_asientos['saldo'] : "";
			return $saldo;
		}

		function generar_txt_esf($con, $ruc_empresa)
		{
			$sql_esf = mysqli_query($con, "SELECT * FROM supercias_esf order by id asc");
			$fila = "";
			$nombre = $ruc_empresa . "-ESTADO_SITUACION_FINANCIERA.txt";
			$file = fopen("../xml/" . $nombre, "w") or die("Problemas en la creacion");
			while ($row_esf = mysqli_fetch_array($sql_esf)) {
				$codigo = $row_esf['codigo'];
				$largo = strlen($codigo);
				$sql_tmp = mysqli_query($con, "SELECT round(sum(valor),2) as valor FROM balances_tmp WHERE ruc_empresa = '" . $ruc_empresa . "' and mid(codigo_cuenta,1,'" . $largo . "') = '" . $codigo . "'  group by mid(codigo_cuenta,1,'" . $largo . "')");
				$row_tmp = mysqli_fetch_array($sql_tmp);
				$valor = isset($row_tmp['valor']) ? $row_tmp['valor'] : "0.00";
				$fila = $codigo . " " . $valor . "\r\n";
				fwrite($file, $fila);
			}
			fclose($file);
			$archivo = '../xml/' . $nombre;
			return $archivo;
		}

		function generar_txt_eri($con, $ruc_empresa)
		{
			$sql_esf = mysqli_query($con, "SELECT * FROM supercias_eri order by id asc");
			$fila = "";
			$nombre = $ruc_empresa . "-ESTADO_RESULTADO_INTEGRAL.txt";
			$file = fopen("../xml/" . $nombre, "w") or die("Problemas en la creacion");
			while ($row_esf = mysqli_fetch_array($sql_esf)) {
				$codigo = $row_esf['codigo'];
				$largo = strlen($codigo);
				$sql_tmp = mysqli_query($con, "SELECT round(sum(valor),2) as valor FROM balances_tmp WHERE ruc_empresa = '" . $ruc_empresa . "' and mid(codigo_cuenta,1,'" . $largo . "') = '" . $codigo . "'  group by mid(codigo_cuenta,1,'" . $largo . "')");
				$row_tmp = mysqli_fetch_array($sql_tmp);
				$valor = isset($row_tmp['valor']) ? $row_tmp['valor'] : "0.00";
				$fila = $codigo . " " . $valor . "\r\n";
				fwrite($file, $fila);
			}
			fclose($file);
			$archivo = '../xml/' . $nombre;
			return $archivo;
		}


		//funcion para generar el balance general de supercias
		function generar_supercias_esf($con, $ruc_empresa, $id_usuario, $desde, $hasta)
		{
			$sql_delete = mysqli_query($con, "DELETE FROM balances_tmp WHERE ruc_empresa= '" . $ruc_empresa . "' ");
			$sql_detalle_diario = mysqli_query($con, "INSERT INTO balances_tmp (id_balance, codigo_cuenta, nombre_cuenta, nivel_cuenta, valor, ruc_empresa, id_usuario) 
		SELECT plan.id_cuenta, plan.codigo_supercias, plan.nombre_cuenta, '5', sum(det_dia.debe-det_dia.haber), '" . $ruc_empresa . "', '" . $id_usuario . "' FROM detalle_diario_contable as det_dia INNER JOIN encabezado_diario as enc_dia ON enc_dia.codigo_unico=det_dia.codigo_unico INNER JOIN plan_cuentas as plan ON plan.id_cuenta=det_dia.id_cuenta WHERE plan.ruc_empresa = '" . $ruc_empresa . "' and enc_dia.ruc_empresa = '" . $ruc_empresa . "' and det_dia.ruc_empresa = '" . $ruc_empresa . "' and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' and mid(plan.codigo_cuenta,1,1) between '1' and '3' and enc_dia.estado !='ANULADO' group by plan.id_cuenta order by plan.codigo_cuenta asc");
		}

		function generar_supercias_eri($con, $ruc_empresa, $id_usuario, $desde, $hasta)
		{
			$sql_delete = mysqli_query($con, "DELETE FROM balances_tmp WHERE ruc_empresa= '" . $ruc_empresa . "' ");
			$sql_detalle_diario = mysqli_query($con, "INSERT INTO balances_tmp (id_balance, codigo_cuenta, nombre_cuenta, nivel_cuenta, valor, ruc_empresa, id_usuario) 
		SELECT plan.id_cuenta, plan.codigo_supercias, plan.nombre_cuenta, '5', sum(det_dia.debe-det_dia.haber), '" . $ruc_empresa . "', '" . $id_usuario . "' FROM detalle_diario_contable as det_dia INNER JOIN encabezado_diario as enc_dia ON enc_dia.codigo_unico=det_dia.codigo_unico INNER JOIN plan_cuentas as plan ON plan.id_cuenta=det_dia.id_cuenta WHERE plan.ruc_empresa = '" . $ruc_empresa . "' and enc_dia.ruc_empresa = '" . $ruc_empresa . "' and det_dia.ruc_empresa = '" . $ruc_empresa . "' and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' and mid(plan.codigo_cuenta,1,1) between '4' and '6' and enc_dia.estado !='ANULADO' group by plan.id_cuenta order by plan.codigo_cuenta asc");
		}


	?>