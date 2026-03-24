<?php
/* Connect To Database*/
include("../conexiones/conectalogin.php");
require_once("../helpers/helpers.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];

$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
$tipo_reporte = $_POST['action'];
$id_cliente_proveedor = $_POST['id_cliente_proveedor'];
$nombre_cliente_proveedor = $_POST['nombre_cliente_proveedor'];
$desde = $_POST['desde'];
$hasta = $_POST['hasta'];
$detalle = $_POST['detalle'];
$cantidad = $_POST['cantidad'];
$observaciones = $_POST['observaciones'];
$tipo_ingreso = $_POST['tipo_ingreso'];
$tipo_egreso = $_POST['tipo_egreso'];


ini_set('date.timezone', 'America/Guayaquil');

if ($action == '1' || $action == '2') { //ingresos y egresos

	if ($action == '1') {
		$encabezados = "<th>Ingreso</th><th>Fecha</th><th>Cliente</th><th>Valor</th><th>Tipo</th><th>Detalle</th><th>Observaciones</th>";
		$tipo_documento = "INGRESO";
		$tipo_ingreso_egreso = $_POST['tipo_ingreso'];
	}

	if ($action == '2') {
		$encabezados = "<th>Egreso</th><th>Fecha</th><th>Proveedor</th><th>Valor</th><th>Tipo</th><th>Detalle</th><th>Observaciones</th>";
		$tipo_documento = "EGRESO";
		$tipo_ingreso_egreso = $_POST['tipo_egreso'];
	}

	if (empty($id_cliente_proveedor)) {
		$condicion_id_cliente = "";
	} else {
		$condicion_id_cliente = " and ing_egr.id_cli_pro='" . $id_cliente_proveedor . "'";
	}

	if (empty($nombre_cliente_proveedor)) {
		$condicion_cliente_proveedor = "";
	} else {
		$condicion_cliente_proveedor = " and ing_egr.nombre_ing_egr LIKE '%" . $nombre_cliente_proveedor . "%'";
	}

	if (empty($observaciones)) {
		$condicion_observaciones = "";
	} else {
		$condicion_observaciones = " and ing_egr.detalle_adicional LIKE '%" . $observaciones . "%'";
	}

	if (empty($tipo_ingreso_egreso)) {
		$condicion_tipo_ingreso_egreso = "";
	} else {
		$condicion_tipo_ingreso_egreso = " and det.tipo_ing_egr='" . $tipo_ingreso_egreso . "'";
	}

	if (empty($detalle)) {
		$condicion_detalle = "";
	} else {
		$condicion_detalle = "  and det.detalle_ing_egr LIKE '%" . $detalle . "%'";
	}
?>
	<div class="panel panel-info">
		<div class="table-responsive">
			<table class="table table-hover">
				<tr class="info">
					<?php echo $encabezados; ?>
				</tr>
				<?php
				$resultado = mysqli_query($con, "SELECT ing_egr.nombre_ing_egr as nombre_ing_egr, 
				ing_egr.codigo_documento as codigo_documento, ing_egr.detalle_adicional as observaciones, det.detalle_ing_egr as detalle, 
				ing_egr.numero_ing_egr as numero_ing_egr,  ing_egr.fecha_ing_egr as fecha_ing_egr, det.tipo_ing_egr as tipo, 
				det.valor_ing_egr as valor_ing_egr FROM ingresos_egresos as ing_egr INNER JOIN detalle_ingresos_egresos as det ON det.codigo_documento=ing_egr.codigo_documento
				WHERE ing_egr.ruc_empresa='" . $ruc_empresa . "' $condicion_id_cliente $condicion_tipo_ingreso_egreso $condicion_cliente_proveedor $condicion_detalle $condicion_observaciones 
				and DATE_FORMAT(ing_egr.fecha_ing_egr, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
				and '" . date("Y/m/d", strtotime($hasta)) . "' and ing_egr.tipo_ing_egr= '" . $tipo_documento . "' 
				order by ing_egr.fecha_ing_egr desc LIMIT $cantidad");

				while ($row = mysqli_fetch_array($resultado)) {
					$codigo_documento = $row['codigo_documento'];
					$numero_ing_egr = $row['numero_ing_egr'];
					$fecha_ing_egr = $row['fecha_ing_egr'];
					$cliente_proveedor = $row['nombre_ing_egr'];
					$valor_ing_egr = $row['valor_ing_egr'];
					$observaciones = $row['observaciones'];
					$tipo_ing_egr = $row['tipo'];
					$detalle = $row['detalle'];

					if (!is_numeric($tipo_ing_egr)) {
						$tipo_asiento = mysqli_query($con, "SELECT * FROM asientos_tipo WHERE codigo='" . $tipo_ing_egr . "' ");
						$row_asiento = mysqli_fetch_assoc($tipo_asiento);
						$transaccion = $row_asiento['tipo_asiento'];
					} else {
						$tipo_pago = mysqli_query($con, "SELECT * FROM opciones_ingresos_egresos WHERE id='" . $tipo_ing_egr . "' ");
						$row_tipo_pago = mysqli_fetch_assoc($tipo_pago);
						$transaccion = $row_tipo_pago['descripcion'];
					}
				?>
					<tr>
						<td>
							<a href="#" class='btn btn-info btn-xs' title='Detalle' onclick="mostrar_detalle('<?php echo $codigo_documento; ?>')" data-toggle="modal" data-target="#detalle_ingreso_egreso"><?php echo $numero_ing_egr; ?></a>
						</td>
						<td><?php echo date("d/m/Y", strtotime($fecha_ing_egr)); ?></td>
						<td><?php echo $cliente_proveedor; ?></td>
						<td><?php echo $valor_ing_egr; ?></td>
						<td><?php echo $transaccion; ?></td>
						<td><?php echo $detalle; ?></td>
						<td><?php echo $observaciones; ?></td>
					</tr>
				<?php
				}
				?>
			</table>
		</div>
	</div>
<?php
}

if ($action == '3' || $action == '4' || $action == '5') { //detalle de ingresos y egresos
	if ($action == '3') {
		$encabezados = "<th>Ingreso</th><th>Fecha</th><th>Cliente</th><th>Valor</th><th>Forma cobro</th><th>Detalle</th>";
		$tipo_documento = "INGRESO";
		$formas_cobro_pago = $_POST['formas_cobro'];
		$tipo_cobro_pago = $_POST['tipo_cobro'];
	}

	if ($action == '4') {
		$encabezados = "<th>Egreso</th><th>Fecha</th><th>Proveedor</th><th>Valor</th><th>Forma pago</th><th>Detalle</th>";
		$tipo_documento = "EGRESO";
		$formas_cobro_pago = $_POST['formas_pago'];
		$tipo_cobro_pago = $_POST['tipo_pago'];
	}

	if ($action == '5') {
		$encabezados = "<th>Número</th><th>Fecha</th><th>Cliente/Proveedor</th><th>Entrada</th><th>Salida</th><th>Forma cobro/pago</th><th>Detalle</th>";
		$formas_pago = $_POST['formas_pago'];
		$formas_cobro = $_POST['formas_cobro'];

		if (empty($formas_cobro)) {
			$condicion_cobro = "";
		} else {
			if (substr($formas_cobro, 0, 1) == 1) {
				$condicion_cobro = "  and pago.codigo_forma_pago=" . substr($formas_cobro, 1, strlen($formas_cobro));
			} else {
				$condicion_cobro = "  and pago.id_cuenta=" . substr($formas_cobro, 1, strlen($formas_cobro));
			}
		}

		if (empty($formas_pago)) {
			$condicion_pago = "";
		} else {
			if (substr($formas_pago, 0, 1) == 1) {
				$condicion_pago = "  and pago.codigo_forma_pago=" . substr($formas_pago, 1, strlen($formas_pago));
			} else {
				$condicion_pago = "  and pago.id_cuenta=" . substr($formas_pago, 1, strlen($formas_pago));
			}
		}
	}


	if (empty($id_cliente_proveedor)) {
		$condicion_id_cliente = "";
	} else {
		$condicion_id_cliente = " and ing_egr.id_cli_pro='" . $id_cliente_proveedor . "'";
	}

	if (empty($nombre_cliente_proveedor)) {
		$condicion_cliente_proveedor = "";
	} else {
		$condicion_cliente_proveedor = " and ing_egr.nombre_ing_egr LIKE '%" . $nombre_cliente_proveedor . "%'";
	}

	if (empty($formas_cobro_pago)) {
		$condicion_forma_pago = "";
	} else {
		if (substr($formas_cobro_pago, 0, 1) == 1) {
			$condicion_forma_pago = "  and pago.codigo_forma_pago=" . substr($formas_cobro_pago, 1, strlen($formas_cobro_pago));
		} else {
			$condicion_forma_pago = "  and pago.id_cuenta=" . substr($formas_cobro_pago, 1, strlen($formas_cobro_pago));
		}
	}

	if (empty($tipo_cobro_pago)) {
		$condicion_tipo_pago = "";
	} else {
		$condicion_tipo_pago = " and pago.detalle_pago='" . $tipo_cobro_pago . "'";
	}

?>
	<div class="panel panel-info">
		<div class="table-responsive">
			<table class="table table-hover">
				<tr class="info">
					<?php echo $encabezados; ?>
				</tr>
				<?php

				if ($action == '5') {
					$resultado_consolidado[] = mysqli_query($con, "SELECT pago.codigo_documento as codigo_documento, pago.codigo_forma_pago as codigo_forma_pago,
				pago.numero_ing_egr as numero_ing_egr, ing_egr.fecha_ing_egr as fecha_ing_egr, ing_egr.nombre_ing_egr as nombre_ing_egr,
				round(pago.valor_forma_pago,2) as valor_forma_pago, pago.detalle_pago as tipo, pago.tipo_documento as tipo_documento, pago.id_cuenta as id_cuenta, pago.cheque as cheque
				 FROM formas_pagos_ing_egr as pago INNER JOIN ingresos_egresos as ing_egr ON ing_egr.codigo_documento=pago.codigo_documento
				 WHERE pago.ruc_empresa='" . $ruc_empresa . "' and DATE_FORMAT(ing_egr.fecha_ing_egr, '%Y/%m/%d') 
				 between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' 
				 and pago.tipo_documento='INGRESO' $condicion_cobro order by ing_egr.fecha_ing_egr desc");

					$resultado_consolidado[] = mysqli_query($con, "SELECT pago.codigo_documento as codigo_documento, pago.codigo_forma_pago as codigo_forma_pago,
				pago.numero_ing_egr as numero_ing_egr, ing_egr.fecha_ing_egr as fecha_ing_egr, ing_egr.nombre_ing_egr as nombre_ing_egr,
				round(pago.valor_forma_pago,2) as valor_forma_pago, pago.detalle_pago as tipo, pago.tipo_documento as tipo_documento, pago.id_cuenta as id_cuenta, pago.cheque as cheque
				 FROM formas_pagos_ing_egr as pago INNER JOIN ingresos_egresos as ing_egr ON ing_egr.codigo_documento=pago.codigo_documento
				 WHERE pago.ruc_empresa='" . $ruc_empresa . "' and DATE_FORMAT(ing_egr.fecha_ing_egr, '%Y/%m/%d') 
				between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' 
				and pago.tipo_documento='EGRESO' $condicion_pago order by ing_egr.fecha_ing_egr desc");
				}

				//para el resto, action = 4...
				$resultado_consolidado[] = mysqli_query($con, "SELECT pago.codigo_documento as codigo_documento, pago.codigo_forma_pago as codigo_forma_pago,
				pago.numero_ing_egr as numero_ing_egr, ing_egr.fecha_ing_egr as fecha_ing_egr, ing_egr.nombre_ing_egr as nombre_ing_egr,
				round(pago.valor_forma_pago,2) as valor_forma_pago, pago.detalle_pago as tipo, pago.tipo_documento as tipo_documento, pago.id_cuenta as id_cuenta, pago.cheque as cheque
				 FROM formas_pagos_ing_egr as pago INNER JOIN ingresos_egresos as ing_egr ON ing_egr.codigo_documento=pago.codigo_documento
				 WHERE pago.ruc_empresa='" . $ruc_empresa . "' $condicion_id_cliente 
				 $condicion_cliente_proveedor $condicion_forma_pago $condicion_tipo_pago and DATE_FORMAT(ing_egr.fecha_ing_egr, '%Y/%m/%d') 
				 between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' 
				 and pago.tipo_documento='" . $tipo_documento . "' order by ing_egr.fecha_ing_egr desc LIMIT $cantidad");

				$total_general = 0;
				$total_ingresos = 0;
				$total_egresos = 0;
				foreach ($resultado_consolidado as $resultado) {
					while ($row = mysqli_fetch_array($resultado)) {
						$numero_ing_egr = $row['numero_ing_egr'];
						$fecha_ing_egr = $row['fecha_ing_egr'];
						$nombre_ing_egr = $row['nombre_ing_egr'];
						$valor_ing_egr = $row['valor_forma_pago'];
						$total_general += $valor_ing_egr;
						$codigo_documento = $row['codigo_documento'];
						$documento = $row['tipo_documento'];
						$codigo_forma_pago = $row['codigo_forma_pago'];
						$id_cuenta = $row['id_cuenta'];
						$cheque = $row['cheque'] > 0 ? $row['cheque'] . " - " : "";
						$detalle_ing_egr = mysqli_query($con, "SELECT detalle_ing_egr as detalle FROM detalle_ingresos_egresos WHERE codigo_documento ='" . $codigo_documento . "'");
						$detalle = "";
						foreach ($detalle_ing_egr as $valor) {
							$detalle .= $valor['detalle'] . "</br>";
						}

						if ($id_cuenta > 0) {
							$cuentas_bancarias = mysqli_query($con, "SELECT concat(ban_ecu.nombre_banco,' ',cue_ban.numero_cuenta,' ', if(cue_ban.id_tipo_cuenta=1,'Aho','Cte')) as cuenta_bancaria FROM cuentas_bancarias as cue_ban INNER JOIN bancos_ecuador as ban_ecu ON cue_ban.id_banco=ban_ecu.id_bancos WHERE cue_ban.id_cuenta ='" . $id_cuenta . "'");
							$row_cuentas_bancarias = mysqli_fetch_array($cuentas_bancarias);
							$cuenta_banco = " - " . $cheque . strtoupper($row_cuentas_bancarias['cuenta_bancaria']);
						} else {
							$cuenta_banco = "";
						}

						if ($codigo_forma_pago > 0) {
							$opciones_pagos = mysqli_query($con, "SELECT * FROM opciones_cobros_pagos WHERE id ='" . $codigo_forma_pago . "'");
							$row_opciones_pagos = mysqli_fetch_array($opciones_pagos);
							$tipo = $row_opciones_pagos['descripcion'];
						} else {

							if ($documento == 'INGRESO') {
								$tipo = $row['tipo'];
								switch ($tipo) {
									case "D":
										$tipo = 'Depósito';
										break;
									case "T":
										$tipo = 'Transferencia';
										break;
								}
							} else {
								$tipo = $row['tipo'];
								switch ($tipo) {
									case "D":
										$tipo = 'Débito';
										break;
									case "T":
										$tipo = 'Transferencia';
										break;
									case "C":
										$tipo = 'Cheque';
										break;
								}
							}
						}
				?>
						<tr>
							<td>
								<a href="#" class='btn btn-info btn-xs' title='Detalle' onclick="mostrar_detalle('<?php echo $codigo_documento; ?>')" data-toggle="modal" data-target="#detalle_ingreso_egreso"><?php echo $numero_ing_egr; ?></a>
							</td>
							<td><?php echo date("d/m/Y", strtotime($fecha_ing_egr)); ?></td>
							<td><?php echo $nombre_ing_egr; ?></td>
							<?php
							if ($action != '5') {
							?>
								<td><?php echo $valor_ing_egr; ?></td>
								<?php
							} else {
								if ($documento == 'INGRESO') {
									$total_ingresos += $valor_ing_egr;
								?>
									<td><?php echo $valor_ing_egr; ?></td>
									<td>-</td>
								<?php
								} else {
									$total_egresos += $valor_ing_egr;
								?>
									<td>-</td>
									<td><?php echo $valor_ing_egr; ?></td>
							<?php
								}
							}
							?>
							<td><?php echo strtoupper($tipo . $cuenta_banco); ?></td>
							<td><?php echo $detalle; ?></td>
						</tr>
				<?php
					}
				}
				?>
				<tr class="info">
					<td colspan="3">Total</td>
					<?php
					if ($action != '5') {
					?>
						<td colspan="3"><?php echo $total_general; ?></td>
					<?php
					} else {
					?>
						<td><?php echo $total_ingresos; ?></td>
						<td colspan="3"><?php echo $total_egresos; ?></td>
					<?php
					}
					?>
				</tr>
			</table>
		</div>
	</div>
<?php
}

?>