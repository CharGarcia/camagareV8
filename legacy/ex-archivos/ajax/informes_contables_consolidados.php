<?php
/* Connect To Database*/
include("../conexiones/conectalogin.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

//balance decomprobacion
if ($action == '3') {
	$desde = mysqli_real_escape_string($con, (strip_tags($_REQUEST['fecha_desde'], ENT_QUOTES)));
	$hasta = mysqli_real_escape_string($con, (strip_tags($_REQUEST['fecha_hasta'], ENT_QUOTES)));
	echo control_errores($con, $ruc_empresa, $desde, $hasta, 'pantalla');
?>
	<div class="table-responsive">
		<div class="panel panel-info">
			<table class="table table-hover">
				<tr class="info">
					<th style="padding: 2px;" rowspan="2">Código</th>
					<th style="padding: 2px;" rowspan="2" align="center">Cuenta</th>
					<th style="padding: 2px; text-align:center;" colspan="2" align="center">Sumas</th>
					<th style="padding: 2px; text-align:center;" colspan="2" align="center">Saldos</th>
				</tr>
				<tr class="info">
					<th style="padding: 2px; text-align:center;">Debe</th>
					<th style="padding: 2px; text-align:center;">Haber</th>
					<th style="padding: 2px; text-align:center;">Deudor</th>
					<th style="padding: 2px; text-align:center;">Acreedor</th>
				</tr>

				<?php
				$sql_detalle_diario = mysqli_query($con, "SELECT plan.codigo_cuenta as codigo_cuenta, plan.nombre_cuenta as nombre_cuenta, sum(det_dia.debe) as debe, sum(det_dia.haber) as haber FROM detalle_diario_contable as det_dia INNER JOIN encabezado_diario as enc_dia ON enc_dia.codigo_unico=det_dia.codigo_unico INNER JOIN plan_cuentas as plan ON plan.id_cuenta=det_dia.id_cuenta WHERE mid(plan.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and mid(enc_dia.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and det_dia.ruc_empresa = '" . $ruc_empresa . "' and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' and mid(plan.codigo_cuenta,1,1) >= '1' and mid(plan.codigo_cuenta,1,1) <= '6' and enc_dia.estado !='ANULADO' and plan.nivel_cuenta='5' group by plan.id_cuenta order by plan.codigo_cuenta asc");

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
						<td style="padding: 2px; text-align:right;"><?php echo number_format($debe_cuenta, 2, '.', ''); ?></td>
						<td style="padding: 2px; text-align:right;"><?php echo number_format($haber_cuenta, 2, '.', ''); ?></td>
						<td style="padding: 2px; text-align:right;"><?php echo number_format($deudor_cuenta, 2, '.', ''); ?></td>
						<td style="padding: 2px; text-align:right;"><?php echo number_format($acreedor_cuenta, 2, '.', ''); ?></td>
					</tr>
				<?php
				}

				?>
				<tr class="info">
					<td style="padding: 2px;  text-align:right;" colspan="2">Sumas</td>
					<td style="padding: 2px;  text-align:right;"><?php echo number_format($suma_debe_cuenta, 2, '.', ''); ?></td>
					<td style="padding: 2px;  text-align:right;"><?php echo number_format($suma_haber_cuenta, 2, '.', ''); ?></td>
					<td style="padding: 2px;  text-align:right;"><?php echo number_format($suma_deudor_cuenta, 2, '.', ''); ?></td>
					<td style="padding: 2px;  text-align:right;"><?php echo number_format($suma_acreedor_cuenta, 2, '.', ''); ?></td>
				</tr>
			</table>
		</div>
	</div>
<?php
}
//balance sri
if ($action == 'sri') {
	$desde = mysqli_real_escape_string($con, (strip_tags($_REQUEST['fecha_desde'], ENT_QUOTES)));
	$hasta = mysqli_real_escape_string($con, (strip_tags($_REQUEST['fecha_hasta'], ENT_QUOTES)));
	echo control_errores($con, $ruc_empresa, $desde, $hasta, 'pantalla');

	echo generar_balance($con, $ruc_empresa, $id_usuario, $desde, $hasta, '1', '6');
	$sql_delete = mysqli_query($con, "DELETE FROM balances_tmp WHERE mid(ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and id_usuario='" . $id_usuario . "' and nivel_cuenta !='5'");
	$sql_update_pasivo = mysqli_query($con, "UPDATE balances_tmp SET valor=valor*-1 WHERE mid(ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and mid(codigo_cuenta,1,1)='2' ");
	$sql_update_patrimonio = mysqli_query($con, "UPDATE balances_tmp SET valor=valor*-1 WHERE mid(ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and mid(codigo_cuenta,1,1)='3' ");
	$sql_update_ingresos = mysqli_query($con, "UPDATE balances_tmp SET valor=valor*-1 WHERE mid(ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and mid(codigo_cuenta,1,1)='4' ");
	$sql_update = mysqli_query($con, "UPDATE balances_tmp as bal_tmp INNER JOIN plan_cuentas as plan ON bal_tmp.codigo_cuenta=plan.codigo_cuenta SET bal_tmp.id_balance=plan.id_cuenta, bal_tmp.codigo_cuenta = plan.codigo_sri WHERE mid(plan.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and mid(bal_tmp.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "'");

?>
<div class="row">
<div class="col-md-3">
			Resumen
			<div class="panel panel-success">
			<div class="table-responsive">
			  <table class="table table-hover">
				<tr  class="success">
					<th style ="padding: 2px;">Código SRI</th>
					<th style ="padding: 2px; text-align: right">Total</th>
				</tr>	
			<?php
			$sql_detalle_balance=mysqli_query($con,"SELECT tmp.id_balance as id, tmp.codigo_cuenta as codigo_sri, round(sum(tmp.valor),2) as valor FROM balances_tmp as tmp WHERE mid(tmp.ruc_empresa,1,10) = '". substr($ruc_empresa,0,10) ."' and tmp.id_usuario='".$id_usuario."'  group by tmp.codigo_cuenta order by tmp.codigo_cuenta asc ");//group by codigo_cuenta  
			while ($row_detalle_balance=mysqli_fetch_array($sql_detalle_balance)){
				$id = $row_detalle_balance['id'];	
				$codigo_sri=$row_detalle_balance['codigo_sri']==null?"Sin código":$row_detalle_balance['codigo_sri'];
					$valor =$row_detalle_balance['valor'];
				if ($valor !=0){	
				?>
				<tr>			
				<td style ="padding: 2px;"><a class='btn btn-default btn-xs' data-toggle="collapse" data-parent="#acordionsri" href="#<?php echo $id; ?>"><?php echo $codigo_sri; ?></a></td>
				<td style ="padding: 2px;" align="right"><?php echo number_format($valor,2,'.',''); ?></td>
				</tr>
				<?php				
				}
			}
			?>
			</table>
		</div>
		</div>
		</div>


<div class="col-md-9">
Detalle de todas las cuentas
	<div class="panel-group" id="acordionsri">
		<?php
		$sql_detalle_balance_sri = mysqli_query($con, "SELECT tmp.id_balance as id, tmp.codigo_cuenta as codigo_sri, round(sum(tmp.valor),2) as valor FROM balances_tmp as tmp WHERE mid(tmp.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and tmp.id_usuario='" . $id_usuario . "'  group by tmp.codigo_cuenta order by tmp.codigo_cuenta asc "); //group by codigo_cuenta  
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
									$sql_detalle_balance = mysqli_query($con, "SELECT tmp.nombre_cuenta as nombre_cuenta, tmp.codigo_cuenta as codigo_sri, round(tmp.valor,2) as valor, plan.codigo_cuenta as codigo_cuenta FROM balances_tmp as tmp INNER JOIN plan_cuentas as plan ON plan.id_cuenta=tmp.id_balance WHERE mid(tmp.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and tmp.id_usuario='" . $id_usuario . "' and tmp.codigo_cuenta = '" . $codigo . "' "); //group by codigo_cuenta  
									while ($row_detalle_balance = mysqli_fetch_array($sql_detalle_balance)) {
										$nombre_cuenta = strtoupper($row_detalle_balance['nombre_cuenta']);
										$codigo_cuenta = strtoupper($row_detalle_balance['codigo_cuenta']);
										$codigo_sri_detalle = $row_detalle_balance['codigo_sri'] == null ? "Sin código" : $row_detalle_balance['codigo_sri'];
										$valor_detalle = $row_detalle_balance['valor'];
										if ($valor_detalle != 0) {
										?>
											<tr>
												<td style="padding: 2px;"><?php echo $codigo_sri_detalle; ?></td>
												<td style="padding: 2px;"><?php echo $codigo_cuenta; ?></td>
												<td style="padding: 2px;"><?php echo $nombre_cuenta; ?></td>
												<td style="padding: 2px;"><?php echo number_format($valor_detalle, 2, '.', ''); ?></td>
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
	</div>
<?php
}

//1 es balance general
if ($action == '1') {
	$desde = mysqli_real_escape_string($con, (strip_tags($_REQUEST['fecha_desde'], ENT_QUOTES)));
	$hasta = mysqli_real_escape_string($con, (strip_tags($_REQUEST['fecha_hasta'], ENT_QUOTES)));
	$nivel = mysqli_real_escape_string($con, (strip_tags($_REQUEST['nivel'], ENT_QUOTES)));
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

				generar_balance($con, $ruc_empresa, $id_usuario, $desde, $hasta, '1', '3');
				$sql_detalle_balance = mysqli_query($con, "SELECT nivel_cuenta as nivel, codigo_cuenta as codigo_cuenta, nombre_cuenta as nombre_cuenta, sum(valor) as valor FROM balances_tmp WHERE mid(ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' $nivel_cuenta group by codigo_cuenta, nivel_cuenta");
				$sql_totales_activo_pasivo_patrimonio = mysqli_query($con, "SELECT nombre_cuenta as nombre_cuenta, sum(round(valor,2)) as valor, codigo_cuenta as codigo_cuenta  FROM balances_tmp WHERE mid(ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and nivel_cuenta='1' group by codigo_cuenta");
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
								<td style="padding: 2px;"><?php echo number_format($valor, 2, '.', ''); ?></td>
							<?php
							} else {
							?>
								<td style="padding: 2px;"></td>
							<?php
							}

							if ($nivel == 4) {
							?>
								<td style="padding: 2px;"><?php echo number_format($valor, 2, '.', ''); ?></td>
							<?php
							} else {
							?>
								<td style="padding: 2px;"></td>
							<?php
							}
							if ($nivel == 3) {
							?>
								<td style="padding: 2px;"><?php echo number_format($valor, 2, '.', ''); ?></td>
							<?php
							} else {
							?>
								<td style="padding: 2px;"></td>
							<?php
							}
							if ($nivel == 2) {
							?>
								<td style="padding: 2px;"><?php echo number_format($valor, 2, '.', ''); ?></td>
							<?php
							} else {
							?>
								<td style="padding: 2px;"></td>
							<?php
							}
							if ($nivel == 1) {
							?>
								<td style="padding: 2px;"><?php echo number_format($valor, 2, '.', ''); ?></td>
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

				//para sacar la utilidad
				$resultado_utilidad = utilidad_perdida($con, $ruc_empresa, $id_usuario, $desde, $hasta);

				//para sacar los totales de activo pasivo y patrimonio
				$suma_activo = array();
				$suma_pasivo = array();
				$suma_patrimonio = array();
				while ($row_totales = mysqli_fetch_array($sql_totales_activo_pasivo_patrimonio)) {
					$codigo_cuenta = $row_totales['codigo_cuenta'];
					$nombre_cuenta = strtoupper($row_totales['nombre_cuenta']);
					//para poner los pasivos y patrimoio con signo positivo
					if (substr($codigo_cuenta, 0, 1) == 1) {
						$valor = number_format($row_totales['valor'], 2, '.', '');
					} else {
						$valor = number_format($row_totales['valor'] * -1, 2, '.', '');
					}
					if ($codigo_cuenta == "1") {
						$suma_activo[] = $valor;
					} else {
						$suma_activo[] = 0;
					}

					if ($codigo_cuenta == "2") {
						$suma_pasivo[] = $valor;
					} else {
						$suma_pasivo[] = 0;
					}

					if ($codigo_cuenta == "3") {
						$suma_patrimonio[] = $valor;
					} else {
						$suma_patrimonio[] = 0;
					}

					?>
					<tr class="info">
						<td style="padding: 2px;"></td>
						<td style="padding: 2px;">TOTAL <?php echo $nombre_cuenta; ?> </td>
						<td style="padding: 2px;"> <?php echo $valor; ?></td>
						<td style="padding: 2px;" colspan="4"></td>
					</tr>
				<?php
				}

				$suma_pasivo_patrimonio = array_sum($suma_pasivo) + array_sum($suma_patrimonio);
				$resultado_diferencia = number_format(array_sum($suma_activo), 2, '.', '') - number_format($suma_pasivo_patrimonio, 2, '.', '');

				if (array_sum($suma_activo) == $suma_pasivo_patrimonio) {
					$diferencias = "";
				} else {
					$diferencias = $resultado_diferencia == 0 ? "" : "Diferencia: " . number_format($resultado_diferencia, 2, '.', '');
				}
				?>
				<tr class="info">
					<td style="padding: 2px;"></td>
					<td style="padding: 2px;"><?php echo $resultado_utilidad['resultado']; ?></td>
					<td style="padding: 2px;"> <?php echo number_format($resultado_utilidad['valor'], 2, '.', ''); ?></td>
					<td style="padding: 2px;" colspan="4"></td>
				</tr>
				<tr class="danger">
					<td style="padding: 2px;" colspan="7" align="center"><b><?php echo $diferencias; ?></b></td>
				</tr>

			</table>
		</div>
	</div>
<?php

}


//2 es estado de resultados
if ($action == '2') {
	$desde = mysqli_real_escape_string($con, (strip_tags($_REQUEST['fecha_desde'], ENT_QUOTES)));
	$hasta = mysqli_real_escape_string($con, (strip_tags($_REQUEST['fecha_hasta'], ENT_QUOTES)));
	$nivel = mysqli_real_escape_string($con, (strip_tags($_REQUEST['nivel'], ENT_QUOTES)));
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
				echo generar_balance($con, $ruc_empresa, $id_usuario, $desde, $hasta, '4', '6');
				$sql_detalle_balance = mysqli_query($con, "SELECT nivel_cuenta as nivel, codigo_cuenta as codigo_cuenta, nombre_cuenta as nombre_cuenta, sum(valor) as valor  FROM balances_tmp WHERE mid(ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' $nivel_cuenta group by codigo_cuenta");
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
							<td style="padding: 2px;"><?php echo number_format($valor, 2, '.', ''); ?></td>
						<?php
						} else {
						?>
							<td style="padding: 2px;"></td>
						<?php
						}

						if ($nivel == 4) {
						?>
							<td style="padding: 2px;"><?php echo number_format($valor, 2, '.', ''); ?></td>
						<?php
						} else {
						?>
							<td style="padding: 2px;"></td>
						<?php
						}


						if ($nivel == 3) {
						?>
							<td style="padding: 2px;"><?php echo number_format($valor, 2, '.', ''); ?></td>
						<?php
						} else {
						?>
							<td style="padding: 2px;"></td>
						<?php
						}

						if ($nivel == 2) {
						?>
							<td style="padding: 2px;"><?php echo number_format($valor, 2, '.', ''); ?></td>
						<?php
						} else {
						?>
							<td style="padding: 2px;"></td>
						<?php
						}

						if ($nivel == 1) {
						?>
							<td style="padding: 2px;"><?php echo number_format($valor, 2, '.', ''); ?></td>
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
				$resultado_utilidad = utilidad_perdida($con, $ruc_empresa, $id_usuario, $desde, $hasta);

				?>
				<tr class="info">
					<td style="padding: 2px;"></td>
					<td style="padding: 2px;"> <?php echo $resultado_utilidad['resultado']; ?></td>
					<td style="padding: 2px;"> <?php echo number_format($resultado_utilidad['valor'], 2, '.', ''); ?></td>
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
	$codigo_cuenta = $row_cuentas['codigo_cuenta'];
	$nombre_cuenta = strtoupper($row_cuentas['nombre_cuenta']);

	//si tiene una cuenta seleccionada
	if (!empty($cuenta)) {
	?>

		<div class="table-responsive">
			<div class="panel panel-success">
				<div class="panel-heading" style="padding: 2px;">
					<h5>
						<p align="left"><b>Códido:</b> <?php echo $codigo_cuenta; ?> <b>Cuenta:</b> <?php echo $nombre_cuenta; ?> </p>
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
					//para cuentas indivuduales
					$saldo = 0;
					$sql_detalle_diario = mysqli_query($con, "SELECT enc_dia.id_documento as id_documento, enc_dia.codigo_unico as codigo_unico, 
			enc_dia.id_diario as id_diario, enc_dia.concepto_general as concepto_general, enc_dia.tipo as tipo, 
			enc_dia.numero_asiento as asiento, enc_dia.fecha_asiento as fecha, det_dia.debe as debe, det_dia.haber as haber, 
			det_dia.detalle_item as detalle 
			FROM encabezado_diario as enc_dia INNER JOIN detalle_diario_contable as det_dia ON enc_dia.codigo_unico=det_dia.codigo_unico INNER JOIN plan_cuentas as plan ON plan.id_cuenta=det_dia.id_cuenta WHERE mid(enc_dia.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' and plan.id_cuenta = '" . $cuenta . "' and enc_dia.estado !='ANULADO' order by enc_dia.fecha_asiento asc "); //  
					while ($row_detalle_diario = mysqli_fetch_array($sql_detalle_diario)) {
						$id_diario = $row_detalle_diario['id_diario'];
						$id_documento = $row_detalle_diario['id_documento'];
						$codigo_unico = $row_detalle_diario['codigo_unico'];
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
						<input type="hidden" value="<?php echo $id_documento; ?>" id="mod_id_documento<?php echo $id_diario; ?>">
						<input type="hidden" value="<?php echo $tipo; ?>" id="mod_tipo<?php echo $id_diario; ?>">

						<tr>
							<td style="padding: 2px;"><?php echo $fecha; ?></td>
							<td style="padding: 2px;"><?php echo $detalle; ?></td>
							<td style="padding: 2px;">
								<a href="#" class='btn btn-info btn-xs' title='Editar asiento' onclick="obtener_datos('<?php echo $id_diario; ?>');" data-toggle="modal" data-target="#NuevoDiarioContable"><i class="glyphicon glyphicon-edit"></i> <?php echo $asiento; ?></a>
							</td>
							<td style="padding: 2px;"><?php echo $tipo; ?></td>
							<td style="padding: 2px;"><?php echo number_format($debe, 2, '.', ''); ?></td>
							<td style="padding: 2px;"><?php echo number_format($haber, 2, '.', ''); ?></td>
							<td style="padding: 2px;"><?php echo number_format($saldo, 2, '.', ''); ?></td>
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
			$sql_detalle_cuentas = mysqli_query($con, "SELECT plan.codigo_cuenta as codigo_cuenta, plan.nombre_cuenta as nombre_cuenta, plan.id_cuenta as ide_cuenta FROM plan_cuentas as plan WHERE mid(plan.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and plan.nivel_cuenta='5' ");
			while ($row_detalle_cuentas = mysqli_fetch_array($sql_detalle_cuentas)) {
				$ide_cuenta = $row_detalle_cuentas['ide_cuenta'];
				$codigo_cuenta = $row_detalle_cuentas['codigo_cuenta'];
				$nombre_cuenta = strtoupper($row_detalle_cuentas['nombre_cuenta']);
				$sql_registros = mysqli_query($con, "SELECT * FROM encabezado_diario as enc_dia INNER JOIN detalle_diario_contable as det_dia ON enc_dia.codigo_unico=det_dia.codigo_unico INNER JOIN plan_cuentas as plan ON plan.id_cuenta=det_dia.id_cuenta WHERE mid(enc_dia.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' and det_dia.id_cuenta = '" . $ide_cuenta . "' and enc_dia.estado !='ANULADO'");
				$registros = mysqli_num_rows($sql_registros);
				if ($registros > 0) {
			?>
					<div class="panel panel-success">
						<a class="list-group-item list-group-item-success" data-toggle="collapse" data-parent="#accordiones" href="#<?php echo $ide_cuenta; ?>"><span class="caret"></span> <b>Códido:</b> <?php echo $codigo_cuenta; ?> <b>Cuenta:</b> <?php echo $nombre_cuenta; ?></a>
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
									$sql_detalle_diario = mysqli_query($con, "SELECT enc_dia.tipo as tipo, enc_dia.numero_asiento as asiento, 
			enc_dia.fecha_asiento as fecha, det_dia.debe as debe, det_dia.haber as haber, 
			det_dia.detalle_item as detalle, enc_dia.codigo_unico as codigo_unico, enc_dia.id_diario as id_diario, 
			enc_dia.concepto_general as concepto_general, enc_dia.id_documento as id_documento
			FROM encabezado_diario as enc_dia INNER JOIN detalle_diario_contable as det_dia ON enc_dia.codigo_unico=det_dia.codigo_unico 
			INNER JOIN plan_cuentas as plan ON plan.id_cuenta=det_dia.id_cuenta 
			WHERE mid(enc_dia.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
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
										$fecha = date('d-m-Y', strtotime($row_detalle_diario['fecha']));
										$concepto_general = $row_detalle_diario['concepto_general'];

									?>
										<input type="hidden" value="<?php echo $asiento; ?>" id="numero_asiento<?php echo $id_diario; ?>">
										<input type="hidden" value="<?php echo $concepto_general; ?>" id="mod_concepto_general<?php echo $id_diario; ?>">
										<input type="hidden" value="<?php echo $fecha; ?>" id="mod_fecha_asiento<?php echo $id_diario; ?>">
										<input type="hidden" value="<?php echo $codigo_unico; ?>" id="mod_codigo_unico<?php echo $id_diario; ?>">
										<input type="hidden" value="<?php echo $id_documento; ?>" id="mod_id_documento<?php echo $id_diario; ?>">
										<input type="hidden" value="<?php echo $tipo; ?>" id="mod_tipo<?php echo $id_diario; ?>">

										<tr>
											<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha)); ?></td>
											<td style="padding: 2px;"><?php echo $detalle; ?></td>
											<td style="padding: 2px;">
												<a href="#" class='btn btn-info btn-xs' title='Editar asiento' onclick="obtener_datos('<?php echo $id_diario; ?>');" data-toggle="modal" data-target="#NuevoDiarioContable"><i class="glyphicon glyphicon-edit"></i> <?php echo $asiento; ?></a>
											</td>
											<td style="padding: 2px;"><?php echo $tipo; ?></td>
											<td style="padding: 2px;"><?php echo number_format($debe, 2, '.', ''); ?></td>
											<td style="padding: 2px;"><?php echo number_format($haber, 2, '.', ''); ?></td>
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
	$nombre_cliente = $row_clientes['nombre'];

	$sql_cuentas = mysqli_query($con, "SELECT * FROM plan_cuentas WHERE id_cuenta = '" . $cuenta . "' "); //  
	$row_cuentas = mysqli_fetch_array($sql_cuentas);
	$codigo_cuenta = $row_cuentas['codigo_cuenta'];
	$nombre_cuenta = strtoupper($row_cuentas['nombre_cuenta']);
	//para un cliente y una cuenta
	if (!empty($pro_cli) && !empty($cuenta)) {
	?>
		<div class="table-responsive">
			<div class="panel panel-success">
				<div class="panel-heading" style="padding: 2px;">
					<h5>
						<p align="left"><b>Cliente: </b><?php echo $nombre_cliente; ?> <b>Código: </b><?php echo $codigo_cuenta; ?> <b>Cuenta: </b><?php echo $nombre_cuenta; ?> </p>
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
					$sql_detalle_diario = mysqli_query($con, "SELECT enc_dia.tipo as tipo, enc_dia.numero_asiento as asiento, 
			enc_dia.fecha_asiento as fecha, det_dia.debe as debe, det_dia.haber as haber, det_dia.detalle_item as detalle, 
			plan.codigo_cuenta as codigo_cuenta, plan.nombre_cuenta as nombre_cuenta, enc_dia.codigo_unico as codigo_unico, 
			enc_dia.id_diario as id_diario, enc_dia.concepto_general as concepto_general, enc_dia.id_documento as id_documento
			FROM encabezado_diario as enc_dia INNER JOIN detalle_diario_contable as det_dia ON enc_dia.codigo_unico=det_dia.codigo_unico INNER JOIN clientes as cli ON cli.id=det_dia.id_cli_pro INNER JOIN plan_cuentas as plan ON plan.id_cuenta=det_dia.id_cuenta WHERE mid(enc_dia.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' and det_dia.id_cli_pro = '" . $pro_cli . "' and plan.id_cuenta = '" . $cuenta . "' and enc_dia.estado !='ANULADO' order by enc_dia.fecha_asiento asc "); //  
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
						$fecha = date('d-m-Y', strtotime($row_detalle_diario['fecha']));
						$concepto_general = $row_detalle_diario['concepto_general'];
					?>
						<input type="hidden" value="<?php echo $asiento; ?>" id="numero_asiento<?php echo $id_diario; ?>">
						<input type="hidden" value="<?php echo $concepto_general; ?>" id="mod_concepto_general<?php echo $id_diario; ?>">
						<input type="hidden" value="<?php echo $fecha; ?>" id="mod_fecha_asiento<?php echo $id_diario; ?>">
						<input type="hidden" value="<?php echo $codigo_unico; ?>" id="mod_codigo_unico<?php echo $id_diario; ?>">
						<input type="hidden" value="<?php echo $id_documento; ?>" id="mod_id_documento<?php echo $id_diario; ?>">
						<input type="hidden" value="<?php echo $tipo; ?>" id="mod_tipo<?php echo $id_diario; ?>">
						<tr>
							<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha)); ?></td>
							<td style="padding: 2px;"><?php echo $detalle; ?></td>
							<td style="padding: 2px;">
								<a href="#" class='btn btn-info btn-xs' title='Editar asiento' onclick="obtener_datos('<?php echo $id_diario; ?>');" data-toggle="modal" data-target="#NuevoDiarioContable"><i class="glyphicon glyphicon-edit"></i> <?php echo $asiento; ?></a>
							</td>
							<td style="padding: 2px;"><?php echo $tipo; ?></td>
							<td style="padding: 2px;"><?php echo number_format($debe, 2, '.', ''); ?></td>
							<td style="padding: 2px;"><?php echo number_format($haber, 2, '.', ''); ?></td>
							<td style="padding: 2px;"><?php echo number_format($saldo, 2, '.', ''); ?></td>
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
		<div class="panel-group" id="accordiones">
			<?php
			$sql_detalle_cuentas = mysqli_query($con, "SELECT plan.codigo_cuenta as codigo_cuenta, plan.nombre_cuenta as nombre_cuenta, plan.id_cuenta as ide_cuenta FROM plan_cuentas as plan WHERE mid(plan.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and plan.nivel_cuenta='5' ");
			while ($row_detalle_cuentas = mysqli_fetch_array($sql_detalle_cuentas)) {
				$ide_cuenta = $row_detalle_cuentas['ide_cuenta'];
				$codigo_cuenta = $row_detalle_cuentas['codigo_cuenta'];
				$nombre_cuenta = strtoupper($row_detalle_cuentas['nombre_cuenta']);
				$sql_registros = mysqli_query($con, "SELECT * FROM encabezado_diario as enc_dia INNER JOIN detalle_diario_contable as det_dia ON enc_dia.codigo_unico=det_dia.codigo_unico INNER JOIN plan_cuentas as plan ON plan.id_cuenta=det_dia.id_cuenta WHERE mid(enc_dia.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' and det_dia.id_cuenta = '" . $ide_cuenta . "' and enc_dia.estado !='ANULADO' and det_dia.id_cli_pro='" . $pro_cli . "'");
				$registros = mysqli_num_rows($sql_registros);
				if ($registros > 0) {
			?>
					<div class="panel panel-success">
						<a class="list-group-item list-group-item-success" data-toggle="collapse" data-parent="#accordiones" href="#<?php echo $ide_cuenta; ?>"><span class="caret"></span> <b>Códido:</b> <?php echo $codigo_cuenta; ?> <b>Cuenta:</b> <?php echo $nombre_cuenta; ?></a>
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
									$sql_detalle_diario = mysqli_query($con, "SELECT enc_dia.tipo as tipo, enc_dia.numero_asiento as asiento, 
			enc_dia.fecha_asiento as fecha, det_dia.debe as debe, det_dia.haber as haber, det_dia.detalle_item as detalle, 
			enc_dia.codigo_unico as codigo_unico, enc_dia.id_diario as id_diario, enc_dia.concepto_general as concepto_general, 
			enc_dia.id_documento as id_documento FROM encabezado_diario as enc_dia INNER JOIN detalle_diario_contable as det_dia 
			ON enc_dia.codigo_unico=det_dia.codigo_unico WHERE mid(enc_dia.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and 
			DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and
			 '" . date("Y/m/d", strtotime($hasta)) . "' and det_dia.id_cuenta = '" . $ide_cuenta . "' and
			  enc_dia.estado !='ANULADO' and det_dia.id_cli_pro='" . $pro_cli . "' order by enc_dia.fecha_asiento asc");
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
										$fecha = date('d-m-Y', strtotime($row_detalle_diario['fecha']));
										$concepto_general = $row_detalle_diario['concepto_general'];
									?>
										<input type="hidden" value="<?php echo $asiento; ?>" id="numero_asiento<?php echo $id_diario; ?>">
										<input type="hidden" value="<?php echo $concepto_general; ?>" id="mod_concepto_general<?php echo $id_diario; ?>">
										<input type="hidden" value="<?php echo $fecha; ?>" id="mod_fecha_asiento<?php echo $id_diario; ?>">
										<input type="hidden" value="<?php echo $codigo_unico; ?>" id="mod_codigo_unico<?php echo $id_diario; ?>">
										<input type="hidden" value="<?php echo $id_documento; ?>" id="mod_id_documento<?php echo $id_diario; ?>">
										<input type="hidden" value="<?php echo $tipo; ?>" id="mod_tipo<?php echo $id_diario; ?>">
										<tr>
											<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha)); ?></td>
											<td style="padding: 2px;"><?php echo $detalle; ?></td>
											<td style="padding: 2px;">
												<a href="#" class='btn btn-info btn-xs' title='Editar asiento' onclick="obtener_datos('<?php echo $id_diario; ?>');" data-toggle="modal" data-target="#NuevoDiarioContable"><i class="glyphicon glyphicon-edit"></i> <?php echo $asiento; ?></a>
											</td>
											<td style="padding: 2px;"><?php echo $tipo; ?></td>
											<td style="padding: 2px;"><?php echo number_format($debe, 2, '.', ''); ?></td>
											<td style="padding: 2px;"><?php echo number_format($haber, 2, '.', ''); ?></td>
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
				}
			}
			?>
			</div>
		<?php
		}

		//para todos los cliente y una cuenta
		if (empty($pro_cli) && !empty($cuenta)) {
			?>
			<div class="panel-group" id="accordiones">
				<?php
				$sql_detalle_clientes = mysqli_query($con, "SELECT * FROM clientes WHERE mid(ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' ");
				while ($row_detalle_clientes = mysqli_fetch_array($sql_detalle_clientes)) {
					$ide_cliente = $row_detalle_clientes['id'];
					$nombre_cliente = $row_detalle_clientes['nombre'];
					$sql_registros = mysqli_query($con, "SELECT * FROM encabezado_diario as enc_dia INNER JOIN detalle_diario_contable as det_dia ON enc_dia.codigo_unico=det_dia.codigo_unico INNER JOIN clientes as cli ON cli.id=det_dia.id_cli_pro WHERE mid(enc_dia.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' and det_dia.id_cli_pro = '" . $ide_cliente . "' and enc_dia.estado !='ANULADO'");
					$registros = mysqli_num_rows($sql_registros);
					if ($registros > 0) {
				?>
						<div class="panel panel-success">
							<a class="list-group-item list-group-item-success" data-toggle="collapse" data-parent="#accordiones" href="#<?php echo $ide_cliente; ?>"><span class="caret"></span> <b>Cliente: </b> <?php echo $nombre_cliente; ?></a>
							<div id="<?php echo $ide_cliente; ?>" class="panel-collapse collapse">
								<div class="table-responsive">
									<table class="table table-hover">
										<tr class="info">
											<th style="padding: 2px;">Fecha</th>
											<th style="padding: 2px;">Código-Cuenta</th>
											<th style="padding: 2px;">Detalle</th>
											<th style="padding: 2px;">Asiento</th>
											<th style="padding: 2px;">Tipo</th>
											<th style="padding: 2px;">Debe</th>
											<th style="padding: 2px;">Haber</th>
											<th style="padding: 2px;">Saldo</th>
										</tr>
										<?php
										$saldo = 0;
										$sql_detalle_diario = mysqli_query($con, "SELECT enc_dia.tipo as tipo, enc_dia.numero_asiento as asiento, 
			enc_dia.fecha_asiento as fecha, det_dia.debe as debe, det_dia.haber as haber, 
			det_dia.detalle_item as detalle, plan.codigo_cuenta as codigo_cuenta, plan.nombre_cuenta as nombre_cuenta, 
			enc_dia.codigo_unico as codigo_unico, enc_dia.id_diario as id_diario, enc_dia.concepto_general as concepto_general, 
			enc_dia.id_documento as id_documento FROM encabezado_diario as enc_dia INNER JOIN detalle_diario_contable as det_dia ON enc_dia.codigo_unico=det_dia.codigo_unico INNER JOIN plan_cuentas as plan ON plan.id_cuenta=det_dia.id_cuenta INNER JOIN clientes as cli ON cli.id=det_dia.id_cli_pro WHERE mid(enc_dia.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' and det_dia.id_cuenta='" . $cuenta . "' and enc_dia.estado !='ANULADO' order by enc_dia.fecha_asiento asc"); //  
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
											$fecha = date('d-m-Y', strtotime($row_detalle_diario['fecha']));
											$concepto_general = $row_detalle_diario['concepto_general'];
										?>
											<input type="hidden" value="<?php echo $asiento; ?>" id="numero_asiento<?php echo $id_diario; ?>">
											<input type="hidden" value="<?php echo $concepto_general; ?>" id="mod_concepto_general<?php echo $id_diario; ?>">
											<input type="hidden" value="<?php echo $fecha; ?>" id="mod_fecha_asiento<?php echo $id_diario; ?>">
											<input type="hidden" value="<?php echo $codigo_unico; ?>" id="mod_codigo_unico<?php echo $id_diario; ?>">
											<input type="hidden" value="<?php echo $id_documento; ?>" id="mod_id_documento<?php echo $id_diario; ?>">
											<input type="hidden" value="<?php echo $tipo; ?>" id="mod_tipo<?php echo $id_diario; ?>">
											<tr>
												<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha)); ?></td>
												<td style="padding: 2px;"><?php echo $codigo_cuenta . " " . $nombre_cuenta; ?></td>
												<td style="padding: 2px;"><?php echo $detalle; ?></td>
												<td style="padding: 2px;">
													<a href="#" class='btn btn-info btn-xs' title='Editar asiento' onclick="obtener_datos('<?php echo $id_diario; ?>');" data-toggle="modal" data-target="#NuevoDiarioContable"><i class="glyphicon glyphicon-edit"></i> <?php echo $asiento; ?></a>
												</td>
												<td style="padding: 2px;"><?php echo $tipo; ?></td>
												<td style="padding: 2px;"><?php echo number_format($debe, 2, '.', ''); ?></td>
												<td style="padding: 2px;"><?php echo number_format($haber, 2, '.', ''); ?></td>
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
					}
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
				$sql_detalle_clientes = mysqli_query($con, "SELECT * FROM clientes WHERE mid(ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' ");
				while ($row_detalle_clientes = mysqli_fetch_array($sql_detalle_clientes)) {
					$ide_cliente = $row_detalle_clientes['id'];
					$nombre_cliente = $row_detalle_clientes['nombre'];
					$sql_registros = mysqli_query($con, "SELECT * FROM encabezado_diario as enc_dia INNER JOIN detalle_diario_contable as det_dia ON enc_dia.codigo_unico=det_dia.codigo_unico INNER JOIN clientes as cli ON cli.id=det_dia.id_cli_pro WHERE mid(enc_dia.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' and det_dia.id_cli_pro = '" . $ide_cliente . "' and enc_dia.estado !='ANULADO'");
					$registros = mysqli_num_rows($sql_registros);
					if ($registros > 0) {
				?>
						<div class="panel panel-info">
							<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordiones_clientes" href="#<?php echo $ide_cliente; ?>"><span class="caret"></span> <b>Cliente: </b> <?php echo $nombre_cliente; ?></a>
							<div class="panel-collapse collapse" id="<?php echo $ide_cliente; ?>">

								<div class="panel-group" id="accordiones_cuentas">
									<?php
									$sql_detalle_cuentas = mysqli_query($con, "SELECT DISTINCT det_dia.id_cli_pro as ide_cliente_cuenta, plan.codigo_cuenta as codigo_cuenta, plan.nombre_cuenta as nombre_cuenta, plan.id_cuenta as ide_cuenta FROM detalle_diario_contable as det_dia INNER JOIN plan_cuentas as plan ON det_dia.id_cuenta=plan.id_cuenta WHERE mid(plan.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and plan.nivel_cuenta='5' and det_dia.id_cli_pro='" . $ide_cliente . "'");
									while ($row_detalle_cuentas = mysqli_fetch_array($sql_detalle_cuentas)) {
										$ide_cuenta_contables = $row_detalle_cuentas['ide_cuenta'];
										$ide_cliente_cuenta = $row_detalle_cuentas['ide_cliente_cuenta'];
										$codigo_cuenta = $row_detalle_cuentas['codigo_cuenta'];
										$nombre_cuenta = strtoupper($row_detalle_cuentas['nombre_cuenta']);

										$sql_registros_cuentas = mysqli_query($con, "SELECT * FROM encabezado_diario as enc_dia INNER JOIN detalle_diario_contable as det_dia ON enc_dia.codigo_unico=det_dia.codigo_unico INNER JOIN plan_cuentas as plan ON plan.id_cuenta=det_dia.id_cuenta WHERE mid(enc_dia.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' and det_dia.id_cuenta = '" . $ide_cuenta_contables . "' and enc_dia.estado !='ANULADO' and det_dia.id_cli_pro='" . $ide_cliente_cuenta . "'");
										$registros_cuentas = mysqli_num_rows($sql_registros_cuentas);
										if ($registros_cuentas > 0) {
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
															$sql_detalle_diario = mysqli_query($con, "SELECT enc_dia.tipo as tipo, enc_dia.numero_asiento as asiento, 
							enc_dia.fecha_asiento as fecha, det_dia.debe as debe, det_dia.haber as haber, 
							det_dia.detalle_item as detalle, enc_dia.codigo_unico as codigo_unico, enc_dia.id_diario as id_diario, 
							enc_dia.concepto_general as concepto_general, enc_dia.id_documento as id_documento
							FROM encabezado_diario as enc_dia INNER JOIN detalle_diario_contable as det_dia ON enc_dia.codigo_unico=det_dia.codigo_unico WHERE mid(enc_dia.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' and det_dia.id_cuenta = '" . $ide_cuenta_contables . "' and det_dia.id_cli_pro='" . $ide_cliente_cuenta . "' and enc_dia.estado !='ANULADO' order by enc_dia.fecha_asiento asc");
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
																$fecha = date('d-m-Y', strtotime($row_detalle_diario['fecha']));
																$concepto_general = $row_detalle_diario['concepto_general'];
															?>
																<input type="hidden" value="<?php echo $asiento; ?>" id="numero_asiento<?php echo $id_diario; ?>">
																<input type="hidden" value="<?php echo $concepto_general; ?>" id="mod_concepto_general<?php echo $id_diario; ?>">
																<input type="hidden" value="<?php echo $fecha; ?>" id="mod_fecha_asiento<?php echo $id_diario; ?>">
																<input type="hidden" value="<?php echo $codigo_unico; ?>" id="mod_codigo_unico<?php echo $id_diario; ?>">
																<input type="hidden" value="<?php echo $id_documento; ?>" id="mod_id_documento<?php echo $id_diario; ?>">
																<input type="hidden" value="<?php echo $tipo; ?>" id="mod_tipo<?php echo $id_diario; ?>">
																<tr>
																	<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha)); ?></td>
																	<td style="padding: 2px;"><?php echo $detalle; ?></td>
																	<td style="padding: 2px;">
																		<a href="#" class='btn btn-info btn-xs' title='Editar asiento' onclick="obtener_datos('<?php echo $id_diario; ?>');" data-toggle="modal" data-target="#NuevoDiarioContable"><i class="glyphicon glyphicon-edit"></i> <?php echo $asiento; ?></a>
																	</td>
																	<td style="padding: 2px;"><?php echo $tipo; ?></td>
																	<td style="padding: 2px;"><?php echo number_format($debe, 2, '.', ''); ?></td>
																	<td style="padding: 2px;"><?php echo number_format($haber, 2, '.', ''); ?></td>
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
										}
									}
									?>
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

	//para hacer mayor de proveedores
	if ($action == '6') {
		$desde = mysqli_real_escape_string($con, (strip_tags($_REQUEST['fecha_desde'], ENT_QUOTES)));
		$hasta = mysqli_real_escape_string($con, (strip_tags($_REQUEST['fecha_hasta'], ENT_QUOTES)));
		$cuenta = mysqli_real_escape_string($con, (strip_tags($_REQUEST['cuenta'], ENT_QUOTES)));
		$pro_cli = mysqli_real_escape_string($con, (strip_tags($_REQUEST['pro_cli'], ENT_QUOTES)));
		$sql_proveedores = mysqli_query($con, "SELECT * FROM proveedores WHERE id_proveedor = '" . $pro_cli . "' "); //  
		$row_proveedores = mysqli_fetch_array($sql_proveedores);
		$nombre_proveedor = $row_proveedores['razon_social'];

		$sql_cuentas = mysqli_query($con, "SELECT * FROM plan_cuentas WHERE id_cuenta = '" . $cuenta . "' "); //  
		$row_cuentas = mysqli_fetch_array($sql_cuentas);
		$codigo_cuenta = $row_cuentas['codigo_cuenta'];
		$nombre_cuenta = strtoupper($row_cuentas['nombre_cuenta']);
		//para un proveedor y una cuenta
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
						//para cuentas
						$saldo = 0;
						$sql_detalle_diario = mysqli_query($con, "SELECT enc_dia.tipo as tipo, enc_dia.numero_asiento as asiento, 
			enc_dia.fecha_asiento as fecha, det_dia.debe as debe, det_dia.haber as haber, 
			det_dia.detalle_item as detalle, plan.codigo_cuenta as codigo_cuenta, plan.nombre_cuenta as nombre_cuenta, 
			enc_dia.codigo_unico as codigo_unico, enc_dia.id_diario as id_diario, enc_dia.concepto_general as concepto_general, 
			enc_dia.id_documento as id_documento			
			FROM encabezado_diario as enc_dia INNER JOIN detalle_diario_contable as det_dia ON enc_dia.codigo_unico=det_dia.codigo_unico INNER JOIN proveedores as cli ON cli.id_proveedor=det_dia.id_cli_pro INNER JOIN plan_cuentas as plan ON plan.id_cuenta=det_dia.id_cuenta WHERE mid(enc_dia.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' and det_dia.id_cli_pro = '" . $pro_cli . "' and plan.id_cuenta = '" . $cuenta . "' and enc_dia.estado !='ANULADO' order by enc_dia.fecha_asiento asc "); //  
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
							$fecha = date('d-m-Y', strtotime($row_detalle_diario['fecha']));
							$concepto_general = $row_detalle_diario['concepto_general'];
						?>
							<input type="hidden" value="<?php echo $asiento; ?>" id="numero_asiento<?php echo $id_diario; ?>">
							<input type="hidden" value="<?php echo $concepto_general; ?>" id="mod_concepto_general<?php echo $id_diario; ?>">
							<input type="hidden" value="<?php echo $fecha; ?>" id="mod_fecha_asiento<?php echo $id_diario; ?>">
							<input type="hidden" value="<?php echo $codigo_unico; ?>" id="mod_codigo_unico<?php echo $id_diario; ?>">
							<input type="hidden" value="<?php echo $id_documento; ?>" id="mod_id_documento<?php echo $id_diario; ?>">
							<input type="hidden" value="<?php echo $tipo; ?>" id="mod_tipo<?php echo $id_diario; ?>">
							<tr>
								<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha)); ?></td>
								<td style="padding: 2px;"><?php echo $detalle; ?></td>
								<td style="padding: 2px;">
									<a href="#" class='btn btn-info btn-xs' title='Editar asiento' onclick="obtener_datos('<?php echo $id_diario; ?>');" data-toggle="modal" data-target="#NuevoDiarioContable"><i class="glyphicon glyphicon-edit"></i> <?php echo $asiento; ?></a>
								</td>
								<td style="padding: 2px;"><?php echo $tipo; ?></td>
								<td style="padding: 2px;"><?php echo number_format($debe, 2, '.', ''); ?></td>
								<td style="padding: 2px;"><?php echo number_format($haber, 2, '.', ''); ?></td>
								<td style="padding: 2px;"><?php echo number_format($saldo, 2, '.', ''); ?></td>
							</tr>
						<?php
						}
						?>
					</table>
				</div>
			</div>
		<?php
		}

		//para un proveedor y todas las cuentas
		if (!empty($pro_cli) && empty($cuenta)) {
		?>
			<div class="panel-group" id="accordiones">
				<?php
				$sql_detalle_cuentas = mysqli_query($con, "SELECT plan.codigo_cuenta as codigo_cuenta, plan.nombre_cuenta as nombre_cuenta, plan.id_cuenta as ide_cuenta FROM plan_cuentas as plan WHERE mid(plan.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and plan.nivel_cuenta='5' ");
				while ($row_detalle_cuentas = mysqli_fetch_array($sql_detalle_cuentas)) {
					$ide_cuenta = $row_detalle_cuentas['ide_cuenta'];
					$codigo_cuenta = $row_detalle_cuentas['codigo_cuenta'];
					$nombre_cuenta = strtoupper($row_detalle_cuentas['nombre_cuenta']);
					$sql_registros = mysqli_query($con, "SELECT * FROM encabezado_diario as enc_dia INNER JOIN detalle_diario_contable as det_dia ON enc_dia.codigo_unico=det_dia.codigo_unico INNER JOIN plan_cuentas as plan ON plan.id_cuenta=det_dia.id_cuenta WHERE mid(enc_dia.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' and det_dia.id_cuenta = '" . $ide_cuenta . "' and enc_dia.estado !='ANULADO' and det_dia.id_cli_pro='" . $pro_cli . "'");
					$registros = mysqli_num_rows($sql_registros);
					if ($registros > 0) {
				?>
						<div class="panel panel-success">
							<a class="list-group-item list-group-item-success" data-toggle="collapse" data-parent="#accordiones" href="#<?php echo $ide_cuenta; ?>"><span class="caret"></span> <b>Códido:</b> <?php echo $codigo_cuenta; ?> <b>Cuenta:</b> <?php echo $nombre_cuenta; ?></a>
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
										$sql_detalle_diario = mysqli_query($con, "SELECT enc_dia.tipo as tipo, enc_dia.numero_asiento as asiento, 
			enc_dia.fecha_asiento as fecha, det_dia.debe as debe, det_dia.haber as haber, det_dia.detalle_item as detalle, 
			enc_dia.codigo_unico as codigo_unico, enc_dia.id_diario as id_diario, enc_dia.concepto_general as concepto_general, 
			enc_dia.id_documento as id_documento FROM encabezado_diario as enc_dia INNER JOIN detalle_diario_contable as det_dia ON enc_dia.codigo_unico=det_dia.codigo_unico WHERE mid(enc_dia.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' and det_dia.id_cuenta = '" . $ide_cuenta . "' and enc_dia.estado !='ANULADO' and det_dia.id_cli_pro='" . $pro_cli . "' order by enc_dia.fecha_asiento asc");
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
											$fecha = date('d-m-Y', strtotime($row_detalle_diario['fecha']));
											$concepto_general = $row_detalle_diario['concepto_general'];
										?>
											<input type="hidden" value="<?php echo $asiento; ?>" id="numero_asiento<?php echo $id_diario; ?>">
											<input type="hidden" value="<?php echo $concepto_general; ?>" id="mod_concepto_general<?php echo $id_diario; ?>">
											<input type="hidden" value="<?php echo $fecha; ?>" id="mod_fecha_asiento<?php echo $id_diario; ?>">
											<input type="hidden" value="<?php echo $codigo_unico; ?>" id="mod_codigo_unico<?php echo $id_diario; ?>">
											<input type="hidden" value="<?php echo $id_documento; ?>" id="mod_id_documento<?php echo $id_diario; ?>">
											<input type="hidden" value="<?php echo $tipo; ?>" id="mod_tipo<?php echo $id_diario; ?>">
											<tr>
												<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha)); ?></td>
												<td style="padding: 2px;"><?php echo $detalle; ?></td>
												<td style="padding: 2px;">
													<a href="#" class='btn btn-info btn-xs' title='Editar asiento' onclick="obtener_datos('<?php echo $id_diario; ?>');" data-toggle="modal" data-target="#NuevoDiarioContable"><i class="glyphicon glyphicon-edit"></i> <?php echo $asiento; ?></a>
												</td>
												<td style="padding: 2px;"><?php echo $tipo; ?></td>
												<td style="padding: 2px;"><?php echo number_format($debe, 2, '.', ''); ?></td>
												<td style="padding: 2px;"><?php echo number_format($haber, 2, '.', ''); ?></td>
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
					}
				}
				?>
				</div>
			<?php
			}

			//para todos los proveedores y una cuenta
			if (empty($pro_cli) && !empty($cuenta)) {
				?>
				<div class="panel-group" id="accordiones">
					<?php
					$sql_detalle_proveedores = mysqli_query($con, "SELECT * FROM proveedores WHERE mid(ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' ");
					while ($row_detalle_proveedores = mysqli_fetch_array($sql_detalle_proveedores)) {
						$ide_proveedor = $row_detalle_proveedores['id_proveedor'];
						$nombre_proveedor = $row_detalle_proveedores['razon_social'];
						$sql_registros = mysqli_query($con, "SELECT * FROM encabezado_diario as enc_dia INNER JOIN detalle_diario_contable as det_dia ON enc_dia.codigo_unico=det_dia.codigo_unico INNER JOIN proveedores as cli ON cli.id_proveedor=det_dia.id_cli_pro WHERE mid(enc_dia.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' and det_dia.id_cli_pro = '" . $ide_proveedor . "' and enc_dia.estado !='ANULADO'");
						$registros = mysqli_num_rows($sql_registros);
						if ($registros > 0) {
					?>
							<div class="panel panel-success">
								<a class="list-group-item list-group-item-success" data-toggle="collapse" data-parent="#accordiones" href="#<?php echo $ide_proveedor; ?>"><span class="caret"></span> <b>Proveedor: </b> <?php echo $nombre_proveedor; ?></a>
								<div id="<?php echo $ide_proveedor; ?>" class="panel-collapse collapse">
									<div class="table-responsive">
										<table class="table table-hover">
											<tr class="info">
												<th style="padding: 2px;">Fecha</th>
												<th style="padding: 2px;">Código-Cuenta</th>
												<th style="padding: 2px;">Detalle</th>
												<th style="padding: 2px;">Asiento</th>
												<th style="padding: 2px;">Tipo</th>
												<th style="padding: 2px;">Debe</th>
												<th style="padding: 2px;">Haber</th>
												<th style="padding: 2px;">Saldo</th>
											</tr>
											<?php
											$saldo = 0;
											$sql_detalle_diario = mysqli_query($con, "SELECT enc_dia.tipo as tipo, enc_dia.numero_asiento as asiento, 
			enc_dia.fecha_asiento as fecha, det_dia.debe as debe, det_dia.haber as haber, det_dia.detalle_item as detalle, 
			plan.codigo_cuenta as codigo_cuenta, plan.nombre_cuenta as nombre_cuenta, 
			enc_dia.codigo_unico as codigo_unico, enc_dia.id_diario as id_diario, enc_dia.concepto_general as concepto_general, 
			enc_dia.id_documento as id_documento FROM encabezado_diario as enc_dia INNER JOIN detalle_diario_contable as det_dia ON enc_dia.codigo_unico=det_dia.codigo_unico INNER JOIN plan_cuentas as plan ON plan.id_cuenta=det_dia.id_cuenta INNER JOIN proveedores as cli ON cli.id_proveedor=det_dia.id_cli_pro WHERE mid(enc_dia.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' and det_dia.id_cuenta='" . $cuenta . "' and det_dia.id_cli_pro='" . $ide_proveedor . "' and enc_dia.estado !='ANULADO' order by enc_dia.fecha_asiento asc"); //  
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
												$fecha = date('d-m-Y', strtotime($row_detalle_diario['fecha']));
												$concepto_general = $row_detalle_diario['concepto_general'];
											?>
												<input type="hidden" value="<?php echo $asiento; ?>" id="numero_asiento<?php echo $id_diario; ?>">
												<input type="hidden" value="<?php echo $concepto_general; ?>" id="mod_concepto_general<?php echo $id_diario; ?>">
												<input type="hidden" value="<?php echo $fecha; ?>" id="mod_fecha_asiento<?php echo $id_diario; ?>">
												<input type="hidden" value="<?php echo $codigo_unico; ?>" id="mod_codigo_unico<?php echo $id_diario; ?>">
												<input type="hidden" value="<?php echo $id_documento; ?>" id="mod_id_documento<?php echo $id_diario; ?>">
												<input type="hidden" value="<?php echo $tipo; ?>" id="mod_tipo<?php echo $id_diario; ?>">
												<tr>
													<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha)); ?></td>
													<td style="padding: 2px;"><?php echo $codigo_cuenta . " " . $nombre_cuenta; ?></td>
													<td style="padding: 2px;"><?php echo $detalle; ?></td>
													<td style="padding: 2px;">
														<a href="#" class='btn btn-info btn-xs' title='Editar asiento' onclick="obtener_datos('<?php echo $id_diario; ?>');" data-toggle="modal" data-target="#NuevoDiarioContable"><i class="glyphicon glyphicon-edit"></i> <?php echo $asiento; ?></a>
													</td>
													<td style="padding: 2px;"><?php echo $tipo; ?></td>
													<td style="padding: 2px;"><?php echo number_format($debe, 2, '.', ''); ?></td>
													<td style="padding: 2px;"><?php echo number_format($haber, 2, '.', ''); ?></td>
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
						}
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
					$sql_detalle_proveedores = mysqli_query($con, "SELECT * FROM proveedores WHERE mid(ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' ");
					while ($row_detalle_proveedores = mysqli_fetch_array($sql_detalle_proveedores)) {
						$ide_proveedor = $row_detalle_proveedores['id_proveedor'];
						$nombre_proveedor = $row_detalle_proveedores['razon_social'];
						$sql_registros = mysqli_query($con, "SELECT * FROM encabezado_diario as enc_dia INNER JOIN detalle_diario_contable as det_dia ON enc_dia.codigo_unico=det_dia.codigo_unico INNER JOIN proveedores as cli ON cli.id_proveedor=det_dia.id_cli_pro WHERE mid(enc_dia.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' and det_dia.id_cli_pro = '" . $ide_proveedor . "' and enc_dia.estado !='ANULADO'");
						$registros = mysqli_num_rows($sql_registros);
						if ($registros > 0) {
					?>
							<div class="panel panel-info">
								<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordiones_proveedores" href="#<?php echo $ide_proveedor; ?>"><span class="caret"></span> <b>Proveedor: </b> <?php echo $nombre_proveedor; ?></a>
								<div class="panel-collapse collapse" id="<?php echo $ide_proveedor; ?>">

									<div class="panel-group" id="accordiones_cuentas">
										<?php
										$sql_detalle_cuentas = mysqli_query($con, "SELECT DISTINCT det_dia.id_cli_pro as ide_cliente_cuenta, plan.codigo_cuenta as codigo_cuenta, plan.nombre_cuenta as nombre_cuenta, plan.id_cuenta as ide_cuenta FROM detalle_diario_contable as det_dia INNER JOIN plan_cuentas as plan ON det_dia.id_cuenta=plan.id_cuenta WHERE plan.ruc_empresa = '" . $ruc_empresa . "' and plan.nivel_cuenta='5' and det_dia.id_cli_pro='" . $ide_proveedor . "'");
										while ($row_detalle_cuentas = mysqli_fetch_array($sql_detalle_cuentas)) {
											$ide_cuenta_contables = $row_detalle_cuentas['ide_cuenta'];
											$ide_cliente_cuenta = $row_detalle_cuentas['ide_cliente_cuenta'];
											$codigo_cuenta = $row_detalle_cuentas['codigo_cuenta'];
											$nombre_cuenta = strtoupper($row_detalle_cuentas['nombre_cuenta']);

											$sql_registros_cuentas = mysqli_query($con, "SELECT * FROM encabezado_diario as enc_dia INNER JOIN detalle_diario_contable as det_dia ON enc_dia.codigo_unico=det_dia.codigo_unico INNER JOIN plan_cuentas as plan ON plan.id_cuenta=det_dia.id_cuenta WHERE enc_dia.ruc_empresa = '" . $ruc_empresa . "' and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' and det_dia.id_cuenta = '" . $ide_cuenta_contables . "' and enc_dia.estado !='ANULADO' and det_dia.id_cli_pro='" . $ide_cliente_cuenta . "'");
											$registros_cuentas = mysqli_num_rows($sql_registros_cuentas);
											if ($registros_cuentas > 0) {
										?>
												<div class="panel panel-success">
													<a class="list-group-item list-group-item-success" data-toggle="collapse" data-parent="#accordiones_cuentas" href="#<?php echo $ide_cuenta_contables . $ide_cliente_cuenta; ?>"><span class="caret"></span> <b>Códido:</b> <?php echo $codigo_cuenta; ?> <b>Cuenta:</b> <?php echo strtoupper($nombre_cuenta); ?></a>
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
																$sql_detalle_diario = mysqli_query($con, "SELECT enc_dia.tipo as tipo, enc_dia.numero_asiento as asiento, 
							enc_dia.fecha_asiento as fecha, det_dia.debe as debe, det_dia.haber as haber, 
							det_dia.detalle_item as detalle, enc_dia.codigo_unico as codigo_unico, enc_dia.id_diario as id_diario, 
							enc_dia.concepto_general as concepto_general, enc_dia.id_documento as id_documento FROM encabezado_diario as enc_dia INNER JOIN detalle_diario_contable as det_dia ON enc_dia.codigo_unico=det_dia.codigo_unico WHERE mid(enc_dia.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' and det_dia.id_cuenta = '" . $ide_cuenta_contables . "' and det_dia.id_cli_pro='" . $ide_cliente_cuenta . "' and enc_dia.estado !='ANULADO' order by enc_dia.fecha_asiento asc");
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
																	$fecha = date('d-m-Y', strtotime($row_detalle_diario['fecha']));
																	$concepto_general = $row_detalle_diario['concepto_general'];
																?>
																	<input type="hidden" value="<?php echo $asiento; ?>" id="numero_asiento<?php echo $id_diario; ?>">
																	<input type="hidden" value="<?php echo $concepto_general; ?>" id="mod_concepto_general<?php echo $id_diario; ?>">
																	<input type="hidden" value="<?php echo $fecha; ?>" id="mod_fecha_asiento<?php echo $id_diario; ?>">
																	<input type="hidden" value="<?php echo $codigo_unico; ?>" id="mod_codigo_unico<?php echo $id_diario; ?>">
																	<input type="hidden" value="<?php echo $id_documento; ?>" id="mod_id_documento<?php echo $id_diario; ?>">
																	<input type="hidden" value="<?php echo $tipo; ?>" id="mod_tipo<?php echo $id_diario; ?>">
																	<tr>
																		<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha)); ?></td>
																		<td style="padding: 2px;"><?php echo $detalle; ?></td>
																		<td style="padding: 2px;">
																			<a href="#" class='btn btn-info btn-xs' title='Editar asiento' onclick="obtener_datos('<?php echo $id_diario; ?>');" data-toggle="modal" data-target="#NuevoDiarioContable"><i class="glyphicon glyphicon-edit"></i> <?php echo $asiento; ?></a>
																		</td>
																		<td style="padding: 2px;"><?php echo $tipo; ?></td>
																		<td style="padding: 2px;"><?php echo number_format($debe, 2, '.', ''); ?></td>
																		<td style="padding: 2px;"><?php echo number_format($haber, 2, '.', ''); ?></td>
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
											}
										}
										?>
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


		//para hacer mayor por detalle de asiento
		if ($action == '7') {
			$desde = mysqli_real_escape_string($con, (strip_tags($_REQUEST['fecha_desde'], ENT_QUOTES)));
			$hasta = mysqli_real_escape_string($con, (strip_tags($_REQUEST['fecha_hasta'], ENT_QUOTES)));
			//$cuenta = mysqli_real_escape_string($con,(strip_tags($_REQUEST['cuenta'], ENT_QUOTES)));
			//$pro_cli = mysqli_real_escape_string($con,(strip_tags($_REQUEST['pro_cli'], ENT_QUOTES)));
			$det_pro_cli = mysqli_real_escape_string($con, (strip_tags($_REQUEST['det_pro_cli'], ENT_QUOTES)));
			$sql_proveedores = mysqli_query($con, "SELECT * FROM proveedores WHERE id_proveedor = '" . $pro_cli . "' "); //  
			$row_proveedores = mysqli_fetch_array($sql_proveedores);
			$nombre_proveedor = $row_proveedores['razon_social'];

			//para un detalle y todas las cuentas
			if (!empty($det_pro_cli)) {
			?>
				<div class="panel-group" id="accordiones">
					<?php
					$sql_detalle_cuentas = mysqli_query($con, "SELECT plan.codigo_cuenta as codigo_cuenta, plan.nombre_cuenta as nombre_cuenta, plan.id_cuenta as ide_cuenta FROM plan_cuentas as plan WHERE mid(plan.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and plan.nivel_cuenta='5' ");
					while ($row_detalle_cuentas = mysqli_fetch_array($sql_detalle_cuentas)) {
						$ide_cuenta = $row_detalle_cuentas['ide_cuenta'];
						$codigo_cuenta = $row_detalle_cuentas['codigo_cuenta'];
						$nombre_cuenta = strtoupper($row_detalle_cuentas['nombre_cuenta']);
						$sql_registros = mysqli_query($con, "SELECT * FROM encabezado_diario as enc_dia 
			INNER JOIN detalle_diario_contable as det_dia ON enc_dia.codigo_unico=det_dia.codigo_unico 
			INNER JOIN plan_cuentas as plan ON plan.id_cuenta=det_dia.id_cuenta 
			WHERE mid(enc_dia.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') 
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
											$sql_detalle_diario = mysqli_query($con, "SELECT enc_dia.tipo as tipo, enc_dia.numero_asiento as asiento, 
			enc_dia.fecha_asiento as fecha, det_dia.debe as debe, det_dia.haber as haber, 
			det_dia.detalle_item as detalle, enc_dia.codigo_unico as codigo_unico, enc_dia.id_diario as id_diario, 
			enc_dia.concepto_general as concepto_general, enc_dia.id_documento as id_documento 
			FROM encabezado_diario as enc_dia INNER JOIN detalle_diario_contable as det_dia ON enc_dia.codigo_unico=det_dia.codigo_unico 
			WHERE mid(enc_dia.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
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
												$fecha = date('d-m-Y', strtotime($row_detalle_diario['fecha']));
												$concepto_general = $row_detalle_diario['concepto_general'];
											?>
												<input type="hidden" value="<?php echo $asiento; ?>" id="numero_asiento<?php echo $id_diario; ?>">
												<input type="hidden" value="<?php echo $concepto_general; ?>" id="mod_concepto_general<?php echo $id_diario; ?>">
												<input type="hidden" value="<?php echo $fecha; ?>" id="mod_fecha_asiento<?php echo $id_diario; ?>">
												<input type="hidden" value="<?php echo $codigo_unico; ?>" id="mod_codigo_unico<?php echo $id_diario; ?>">
												<input type="hidden" value="<?php echo $id_documento; ?>" id="mod_id_documento<?php echo $id_diario; ?>">
												<input type="hidden" value="<?php echo $tipo; ?>" id="mod_tipo<?php echo $id_diario; ?>">
												<tr>
													<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha)); ?></td>
													<td style="padding: 2px;"><?php echo $detalle; ?></td>
													<td style="padding: 2px;">
														<a href="#" class='btn btn-info btn-xs' title='Editar asiento' onclick="obtener_datos('<?php echo $id_diario; ?>');" data-toggle="modal" data-target="#NuevoDiarioContable"><i class="glyphicon glyphicon-edit"></i> <?php echo $asiento; ?></a>
													</td>
													<td style="padding: 2px;"><?php echo $tipo; ?></td>
													<td style="padding: 2px;"><?php echo number_format($debe, 2, '.', ''); ?></td>
													<td style="padding: 2px;"><?php echo number_format($haber, 2, '.', ''); ?></td>
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
						}
					}
				}
			}

			//funcion para generar el balance
			function generar_balance($con, $ruc_empresa, $id_usuario, $desde, $hasta, $cuenta_inicial, $cuenta_final)
			{
				$sql_delete = mysqli_query($con, "DELETE FROM balances_tmp WHERE mid(ruc_empresa,1,10)= '" . substr($ruc_empresa,0,10) . "' ");

				$sql_detalle_diario = mysqli_query($con, "INSERT INTO balances_tmp (id_balance, codigo_cuenta, nombre_cuenta, nivel_cuenta, valor, ruc_empresa, id_usuario) 
			SELECT null, plan.codigo_cuenta, plan.nombre_cuenta, '5', sum(det_dia.debe-det_dia.haber), '" . $ruc_empresa . "', '" . $id_usuario . "' FROM detalle_diario_contable as det_dia INNER JOIN encabezado_diario as enc_dia ON enc_dia.codigo_unico=det_dia.codigo_unico INNER JOIN plan_cuentas as plan ON plan.id_cuenta=det_dia.id_cuenta WHERE mid(plan.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and mid(enc_dia.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and mid(det_dia.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' and mid(plan.codigo_cuenta,1,1) between '" . $cuenta_inicial . "' and '" . $cuenta_final . "' and enc_dia.estado !='ANULADO' group by plan.id_cuenta order by plan.codigo_cuenta asc");

				$sql_nivel_uno = mysqli_query($con, "INSERT INTO balances_tmp (id_balance, codigo_cuenta, nombre_cuenta, nivel_cuenta, valor, ruc_empresa, id_usuario) 
			SELECT null, plan.codigo_cuenta, plan.nombre_cuenta, '1', sum(tmp.valor), '" . $ruc_empresa . "', '" . $id_usuario . "' FROM balances_tmp as tmp INNER JOIN plan_cuentas as plan ON plan.codigo_cuenta=mid(tmp.codigo_cuenta,1,1) WHERE mid(plan.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and mid(tmp.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and plan.nivel_cuenta='1' group by mid(tmp.codigo_cuenta,1,1) ");

				$sql_nivel_dos = mysqli_query($con, "INSERT INTO balances_tmp (id_balance, codigo_cuenta, nombre_cuenta, nivel_cuenta, valor, ruc_empresa, id_usuario) 
			SELECT null, plan.codigo_cuenta, plan.nombre_cuenta, '2', sum(tmp.valor), '" . $ruc_empresa . "', '" . $id_usuario . "' FROM balances_tmp as tmp INNER JOIN plan_cuentas as plan ON plan.codigo_cuenta=mid(tmp.codigo_cuenta,1,3) WHERE mid(plan.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and mid(tmp.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and plan.nivel_cuenta='2' group by mid(tmp.codigo_cuenta,1,3) ");

				$sql_nivel_tres = mysqli_query($con, "INSERT INTO balances_tmp (id_balance, codigo_cuenta, nombre_cuenta, nivel_cuenta, valor, ruc_empresa, id_usuario) 
			SELECT null, plan.codigo_cuenta, plan.nombre_cuenta, '3', sum(tmp.valor), '" . $ruc_empresa . "', '" . $id_usuario . "' FROM balances_tmp as tmp INNER JOIN plan_cuentas as plan ON plan.codigo_cuenta=mid(tmp.codigo_cuenta,1,6) WHERE mid(plan.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and mid(tmp.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and plan.nivel_cuenta='3' group by mid(tmp.codigo_cuenta,1,6) ");

				$sql_nivel_cuatro = mysqli_query($con, "INSERT INTO balances_tmp (id_balance, codigo_cuenta, nombre_cuenta, nivel_cuenta, valor, ruc_empresa, id_usuario) 
			SELECT null, plan.codigo_cuenta, plan.nombre_cuenta, '4', sum(tmp.valor), '" . $ruc_empresa . "', '" . $id_usuario . "' FROM balances_tmp as tmp INNER JOIN plan_cuentas as plan ON plan.codigo_cuenta=mid(tmp.codigo_cuenta,1,9) WHERE mid(plan.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and mid(tmp.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and plan.nivel_cuenta='4' group by mid(tmp.codigo_cuenta,1,9) ");
			}


			function utilidad_perdida($con, $ruc_empresa, $id_usuario, $desde, $hasta)
			{

				$ingresos = 0;
				$costos = 0;
				$gastos = 0;
				generar_balance($con, $ruc_empresa, $id_usuario, $desde, $hasta, '4', '6');
				$sql_ingresos = mysqli_query($con, "SELECT codigo_cuenta as codigo_cuenta, nombre_cuenta as nombre_cuenta, sum(round(valor*-1,2)) as valor  FROM balances_tmp WHERE mid(ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and nivel_cuenta='1' and mid(codigo_cuenta,1,1)=4 group by codigo_cuenta");
				$row_ingresos = mysqli_fetch_array($sql_ingresos);
				$ingresos = $row_ingresos['valor'];

				$sql_costos = mysqli_query($con, "SELECT codigo_cuenta as codigo_cuenta, nombre_cuenta as nombre_cuenta, sum(round(valor,2)) as valor  FROM balances_tmp WHERE mid(ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and nivel_cuenta='1' and mid(codigo_cuenta,1,1)=5 group by codigo_cuenta");
				$row_costos = mysqli_fetch_array($sql_costos);
				$costos = $row_costos['valor'];

				$sql_gastos = mysqli_query($con, "SELECT codigo_cuenta as codigo_cuenta, nombre_cuenta as nombre_cuenta, sum(round(valor,2)) as valor  FROM balances_tmp WHERE mid(ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and nivel_cuenta='1' and mid(codigo_cuenta,1,1)=6 group by codigo_cuenta");
				$row_gastos = mysqli_fetch_array($sql_gastos);
				$gastos = $row_gastos['valor'];


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

			function control_errores($con, $ruc_empresa, $desde, $hasta, $estilo)
			{
				$mensajes = array();
				$sql_compras = mysqli_query($con, "SELECT * FROM encabezado_compra as enc INNER JOIN empresas as emp ON emp.ruc=enc.ruc_empresa WHERE mid(enc.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and DATE_FORMAT(enc.fecha_compra, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' and  enc.id_registro_contable =0 and emp.estado='1'");
				$total_compras = mysqli_num_rows($sql_compras);
				$row_compras = mysqli_fetch_array($sql_compras);
				$est_compras = substr($row_compras['ruc_empresa'],10,3);
				if ($total_compras > 0) {
					$mensajes[] = mysqli_num_rows($sql_compras) . " registros de compras, gastos y servicios por contabilizar del establecimiento:".$est_compras.". ";
				}

				$sql_ventas = mysqli_query($con, "SELECT * FROM encabezado_factura as enc INNER JOIN empresas as emp ON emp.ruc=enc.ruc_empresa WHERE mid(enc.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and DATE_FORMAT(enc.fecha_factura, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' and enc.id_registro_contable =0 and enc.estado_sri ='AUTORIZADA' and emp.estado='1'");
				$total_ventas = mysqli_num_rows($sql_ventas);
				$row_ventas = mysqli_fetch_array($sql_ventas);
				$est_ventas = substr($row_ventas['ruc_empresa'],10,3);
				if ($total_ventas > 0) {
					$mensajes[] = mysqli_num_rows($sql_ventas) . " registros de ventas por contabilizar del establecimiento:".$est_ventas.". ";
				}

				$sql_retenciones_ventas = mysqli_query($con, "SELECT * FROM encabezado_retencion_venta as enc INNER JOIN cuerpo_retencion_venta as cue ON cue.codigo_unico=enc.codigo_unico WHERE enc.ruc_empresa = '" . $ruc_empresa . "' and DATE_FORMAT(enc.fecha_emision, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' and  enc.id_registro_contable =0 and cue.valor_retenido>0 ");
				$total_retenciones_ventas = mysqli_num_rows($sql_retenciones_ventas);
				$row_ret_venta = mysqli_fetch_array($sql_retenciones_ventas);
				$est_ret_ventas = substr($row_ret_venta['ruc_empresa'],10,3);
				if ($total_retenciones_ventas > 0) {
					$mensajes[] = mysqli_num_rows($sql_retenciones_ventas) . " registros de retenciones en ventas por contabilizar del establecimiento ". $est_ret_ventas.". ";
				}

				$sql_retenciones_compras = mysqli_query($con, "SELECT * FROM encabezado_retencion as enc INNER JOIN empresas as emp ON emp.ruc=enc.ruc_empresa WHERE mid(enc.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and DATE_FORMAT(enc.fecha_emision, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' and enc.id_registro_contable =0 and enc.estado_sri ='AUTORIZADA' and enc.total_retencion > 0 and emp.estado='1'");
				$total_retenciones_compras = mysqli_num_rows($sql_retenciones_compras);
				$row_ret_compra = mysqli_fetch_array($sql_retenciones_compras);
				$est_ret_compra = substr($row_ret_compra['ruc_empresa'],10,3);
				if ($total_retenciones_compras > 0) {
					$mensajes[] = mysqli_num_rows($sql_retenciones_compras) . " registros de retenciones en compras por contabilizar del establecimiento ".$est_ret_compra.". ";
				}

				$sql_nc = mysqli_query($con, "SELECT * FROM encabezado_nc as enc INNER JOIN empresas as emp ON emp.ruc=enc.ruc_empresa WHERE mid(enc.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and DATE_FORMAT(enc.fecha_nc, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' and  enc.id_registro_contable =0 and enc.estado_sri ='AUTORIZADA' and emp.estado='1'");
				$total_nc = mysqli_num_rows($sql_nc);
				$row_nc = mysqli_fetch_array($sql_nc);
				$est_nc = substr($row_nc['ruc_empresa'],10,3);
				if ($total_nc > 0) {
					$mensajes[] = mysqli_num_rows($sql_nc) . " registros de notas de crédito por contabilizar del establecimiento ".$est_nc.". ";
				}

				$sql_ingresos = mysqli_query($con, "SELECT * FROM ingresos_egresos as enc INNER JOIN empresas as emp ON emp.ruc=enc.ruc_empresa WHERE mid(enc.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and DATE_FORMAT(enc.fecha_ing_egr, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' and enc.codigo_contable =0 and enc.estado !='ANULADO' and enc.tipo_ing_egr='INGRESO' and enc.valor_ing_egr>0 and emp.estado='1'");
				$total_ingresos = mysqli_num_rows($sql_ingresos);
				$row_ingresos = mysqli_fetch_array($sql_ingresos);
				$est_ingresos = substr($row_ingresos['ruc_empresa'],10,3);
				if ($total_ingresos > 0) {
					$mensajes[] = mysqli_num_rows($sql_ingresos) . " registros de ingresos por contabilizar del establecimiento ".$est_ingresos.". ";
				}

				$sql_egresos = mysqli_query($con, "SELECT * FROM ingresos_egresos as enc INNER JOIN empresas as emp ON emp.ruc=enc.ruc_empresa WHERE mid(enc.ruc_empresa,1,10) = '" . substr($ruc_empresa,0,10) . "' and DATE_FORMAT(enc.fecha_ing_egr, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' and enc.codigo_contable =0 and estado !='ANULADO' and enc.tipo_ing_egr='EGRESO' and enc.valor_ing_egr>0 and emp.estado='1'");
				$total_egresos = mysqli_num_rows($sql_egresos);
				$row_gresos = mysqli_fetch_array($sql_egresos);
				$est_egresos = substr($row_gresos['ruc_empresa'],10,3);
				if ($total_egresos > 0) {
					$mensajes[] = mysqli_num_rows($sql_egresos) . " registros de egresos por contabilizar del establecimiento ".$est_egresos.". ";
				}

				$sql_asientos = mysqli_query($con, "SELECT enc_dia.numero_asiento as numero_asiento, sum(det_dia.debe-det_dia.haber) as diferencia FROM encabezado_diario as enc_dia INNER JOIN detalle_diario_contable as det_dia ON enc_dia.codigo_unico=det_dia.codigo_unico WHERE mid(enc_dia.ruc_empresa,1.12) = '" . substr($ruc_empresa,0,12) . "' and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' and enc_dia.estado !='ANULADO' group by det_dia.codigo_unico");
				while ($row_asientos = mysqli_fetch_array($sql_asientos)) {
					$diferencias = $row_asientos['diferencia'];
					if ($diferencias != 0) {
						$mensajes[] = "Error en asiento No. " . $row_asientos['numero_asiento'];
					}
				}

				if ($estilo == "pantalla") {

					if (!empty($mensajes)) {
						$respuesta = '<li style="margin-bottom: 10px; margin-top: -10px; padding: 3px;" class="list-group-item list-group-item-danger"><h5><b>';
						foreach ($mensajes as $value) {
							$respuesta .= $value . " ";
						}
						$respuesta .= '</b></h5></li>';
					}
					return $respuesta;
				}
				if ($estilo == "excel") {

					if (!empty($mensajes)) {
						$respuesta = "";
						foreach ($mensajes as $value) {
							$respuesta .= $value . " ";
						}
						$respuesta .= $respuesta;
					}
					return $respuesta;
				}
			}

			?>