<?php
/* Connect To Database*/
include("../conexiones/conectalogin.php");
require_once("../helpers/helpers.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
$fecha_desde = $_POST['fecha_desde'];
$fecha_hasta = $_POST['fecha_hasta'];

// Convertir fechas si vienen en formato dd/mm/yyyy
if (!empty($fecha_desde)) {
    $fecha_desde = date("Y-m-d", strtotime(str_replace('/', '-', $fecha_desde)));
}

if (!empty($fecha_hasta)) {
    $fecha_hasta = date("Y-m-d", strtotime(str_replace('/', '-', $fecha_hasta)));
}

$id_cliente = $_POST['id_cliente'];
ini_set('date.timezone', 'America/Guayaquil');

if ($action == 'saldo_cliente') {

	$suma_ingreso = 0;
	$suma_ventas = 0;
	$suma_recibos = 0;
	$suma_egreso = 0;
	$suma_ingreso = 0;

	$detalle_ingresos = ingresos_egresos($con, $ruc_empresa, $fecha_desde, $fecha_hasta, 'INGRESO', $id_cliente);
	$detalle_egresos = ingresos_egresos($con, $ruc_empresa, $fecha_desde, $fecha_hasta, 'EGRESO', $id_cliente);

	$detalle_ventas = detalle_ventas($con, $fecha_desde, $fecha_hasta, $ruc_empresa, $id_cliente);
	$detalle_recibos = detalle_recibos($con, $fecha_desde, $fecha_hasta, $ruc_empresa, $id_cliente);


	//if ($detalle_ventas->num_rows + $detalle_nc->num_rows + $detalle_ingresos->num_rows + $detalle_egresos->num_rows == 0) {
	if ($detalle_ingresos->num_rows == 0) {
		echo "No hay datos para mostrar.";
	}


	if ($detalle_ingresos->num_rows > 0) {
?>
		<div class="panel panel-info">
			<div class="table-responsive">
				<table class="table table-hover">
					Detalle de ingresos
					<tr class="info">
						<td>Fecha</td>
						<td>Número</td>
						<td>Detalle</td>
						<td>Forma cobro</td>
						<td>Valor</td>
					</tr>
					<?php
					$suma_ingreso = 0;
					while ($row = mysqli_fetch_array($detalle_ingresos)) {
						$numero = $row['numero_ing_egr'];
						$fecha = $row['fecha_ing_egr'];
						$total_pago = $row['valor_forma_pago'];
						$suma_ingreso += $total_pago;
						$codigo_forma_pago = $row['codigo_forma_pago'];
						$id_cuenta = $row['id_cuenta'];
						$cheque = $row['cheque'] > 0 ? $row['cheque'] . " - " : "";
						$detalle_ing_egr = mysqli_query($con, "SELECT detalle_ing_egr as detalle FROM detalle_ingresos_egresos WHERE codigo_documento ='" . $row['codigo_documento'] . "'");
						$detalle = "";
						foreach ($detalle_ing_egr as $valor) {
							$detalle .= $valor['detalle'] . "</br>";
						}
					?>
						<tr>
							<td><?php echo date("d-m-Y", strtotime($fecha)); ?></td>
							<td><?php echo "Ingreso " . $numero; ?></td>
							<td><?php echo $detalle; ?></td>
							<td><?php echo forma_pago($id_cuenta, $cheque, $codigo_forma_pago, $con, 'INGRESO', $row); ?></td>
							<td><?php echo number_format($total_pago, 2, '.', ''); ?></td>
						</tr>
					<?php
					}
					?>
					<tr class="info">
						<td colspan="4" class="text-right">Totales</td>
						<td><?php echo number_format($suma_ingreso, 2, '.', ''); ?></td>
					</tr>
				</table>
			</div>
		</div>
	<?php
	}


	if ($detalle_ventas->num_rows > 0) {
	?>
		<div class="panel panel-info">
			<div class="table-responsive">
				<table class="table table-hover">
					Detalle de facturas de venta
					<tr class="info">
						<td>Fecha</td>
						<td>Número</td>
						<td>Total</td>
						<td>Saldo</td>
					</tr>
					<?php

					$suma_ventas = 0;
					$suma_saldo = 0;
					while ($row = mysqli_fetch_array($detalle_ventas)) {
						$numero_factura = $row['serie_factura'] . "-" . $row['secuencial_factura'];
						$fecha = $row['fecha_factura'];
						$total_factura = $row['total_factura'];
						$saldo_factura = saldo_factura($con, $ruc_empresa, $row['id_encabezado_factura']);
						$suma_ventas += $total_factura;
						$suma_saldo += $saldo_factura;
					?>
						<tr>
							<td><?php echo date("d-m-Y", strtotime($fecha)); ?></td>
							<td><?php echo $numero_factura; ?></td>
							<td><?php echo number_format($total_factura, 2, '.', ''); ?></td>
							<td><?php echo number_format($saldo_factura, 2, '.', ''); ?></td>
						</tr>
					<?php
					}
					?>
					<tr class="info">
						<td colspan="2" class="text-right">Totales</td>
						<td><?php echo number_format($suma_ventas, 2, '.', ''); ?></td>
						<td><?php echo number_format($suma_saldo, 2, '.', ''); ?></td>
					</tr>
				</table>
			</div>
		</div>
	<?php
	}

	if ($detalle_recibos->num_rows > 0) {
	?>
		<div class="panel panel-info">
			<div class="table-responsive">
				<table class="table table-hover">
					Detalle de recibos de venta
					<tr class="info">
						<td>Fecha</td>
						<td>Número</td>
						<td>Total</td>
						<td>Saldo</td>
					</tr>
					<?php

					$suma_recibos = 0;
					$suma_saldo = 0;
					while ($row = mysqli_fetch_array($detalle_recibos)) {
						$numero_recibo = $row['serie_recibo'] . "-" . $row['secuencial_recibo'];
						$fecha = $row['fecha_recibo'];
						$total_recibo = $row['total_recibo'];
						$saldo_recibo = saldo_recibo($con, $row['id_encabezado_recibo']);
						$suma_recibos += $total_recibo;
						$suma_saldo += $saldo_recibo;
					?>
						<tr>
							<td><?php echo date("d-m-Y", strtotime($fecha)); ?></td>
							<td><?php echo $numero_recibo; ?></td>
							<td><?php echo number_format($total_recibo, 2, '.', ''); ?></td>
							<td><?php echo number_format($saldo_recibo, 2, '.', ''); ?></td>
						</tr>
					<?php
					}
					?>
					<tr class="info">
						<td colspan="2" class="text-right">Totales</td>
						<td><?php echo number_format($suma_recibos, 2, '.', ''); ?></td>
						<td><?php echo number_format($suma_saldo, 2, '.', ''); ?></td>
					</tr>
				</table>
			</div>
		</div>
	<?php
	}

	if ($detalle_egresos->num_rows > 0) {
	?>
		<div class="panel panel-info">
			<div class="table-responsive">
				<table class="table table-hover">
					Detalle de egresos
					<tr class="info">
						<td>Pagado a</td>
						<td>Número</td>
						<td>Detalle</td>
						<td>Forma Pago</td>
						<td>Valor</td>
					</tr>
					<?php
					$suma_egreso = 0;
					while ($row = mysqli_fetch_array($detalle_egresos)) {
						$numero = $row['numero_ing_egr'];
						$fecha = $row['fecha_ing_egr'];
						$total_pago = $row['valor_forma_pago'];
						$suma_egreso += $total_pago;
						$codigo_forma_pago = $row['codigo_forma_pago'];
						$id_cuenta = $row['id_cuenta'];
						$cheque = $row['cheque'] > 0 ? $row['cheque'] . " - " : "";
						$detalle_ing_egr = mysqli_query($con, "SELECT detalle_ing_egr as detalle FROM detalle_ingresos_egresos WHERE codigo_documento ='" . $row['codigo_documento'] . "'");
						$detalle = "";
						foreach ($detalle_ing_egr as $valor) {
							$detalle .= $valor['detalle'] . "</br>";
						}
					?>
						<tr>
							<td><?php echo date("d-m-Y", strtotime($fecha)); ?></td>
							<td><?php echo "Egreso " . $numero; ?></td>
							<td><?php echo $detalle; ?></td>
							<td><?php echo forma_pago($id_cuenta, $cheque, $codigo_forma_pago, $con, 'EGRESO', $row); ?></td>
							<td><?php echo number_format($total_pago, 2, '.', ''); ?></td>
						</tr>
					<?php
					}
					?>
					<tr class="info">
						<td colspan="4" class="text-right">Totales</td>
						<td><?php echo number_format($suma_egreso, 2, '.', ''); ?></td>
					</tr>
				</table>
			</div>
		</div>
	<?php
	}

	?>
	<div class="panel panel-info">
		<div class="table-responsive">
			<table class="table table-hover">
				<tr class="info">
					<td>Ingresos</td>
					<td>(-) Facturas</td>
					<td>(-) Recibos</td>
					<td>(-) Egresos</td>
					<td>(=) Saldo final</td>
					<td>Observación</td> <!-- Nueva cabecera -->
				</tr>
				<tr>
					<td><?php echo number_format($suma_ingreso, 2); ?></td>
					<td><?php echo number_format($suma_ventas, 2); ?></td>
					<td><?php echo number_format($suma_recibos, 2); ?></td>
					<td><?php echo number_format($suma_egreso, 2); ?></td>
					<?php
					$saldo = $suma_ingreso - $suma_ventas - $suma_recibos - $suma_egreso;
					?>
					<td><?php echo number_format($saldo, 2); ?></td>
					<td>
						<?php
						if ($saldo < 0) {
							echo "Por cobrar al cliente";
						} elseif ($saldo > 0) {
							echo "Saldo a favor del cliente";
						}
						// Si es cero, no mostramos nada
						?>
					</td>
				</tr>
			</table>
		</div>
	</div>

<?php
}

function detalle_ventas($con, $fecha_desde, $fecha_hasta, $ruc_empresa, $id_cliente)
{
	$result = mysqli_query($con, "SELECT * FROM encabezado_factura as fac 
	INNER JOIN clientes as cli ON cli.id=fac.id_cliente 
	WHERE fac.ruc_empresa='" . $ruc_empresa . "' 
	and fac.fecha_factura BETWEEN '" . $fecha_desde . "' AND '" . $fecha_hasta . "' 
	and fac.id_cliente='" . $id_cliente . "' order by fac.secuencial_factura asc");
	return $result;
}

function detalle_recibos($con, $fecha_desde, $fecha_hasta, $ruc_empresa, $id_cliente)
{
	$result = mysqli_query($con, "SELECT * FROM encabezado_recibo as rec 
	INNER JOIN clientes as cli ON cli.id=rec.id_cliente WHERE rec.ruc_empresa='" . $ruc_empresa . "' 
	and rec.fecha_recibo BETWEEN '" . $fecha_desde . "' AND '" . $fecha_hasta . "' 
	and rec.id_cliente='" . $id_cliente . "' order by rec.secuencial_recibo asc");
	return $result;
}


function ingresos_egresos($con, $ruc_empresa, $fecha_desde, $fecha_hasta, $tipo, $id_cliente)
{
	$resultado = mysqli_query($con, "
    SELECT 
        pago.codigo_documento as codigo_documento, 
        pago.codigo_forma_pago as codigo_forma_pago,
        pago.numero_ing_egr as numero_ing_egr, 
        ing_egr.fecha_ing_egr as fecha_ing_egr, 
        ing_egr.nombre_ing_egr as nombre_ing_egr,
        round(pago.valor_forma_pago,2) as valor_forma_pago, 
        pago.detalle_pago as tipo, 
        pago.tipo_documento as tipo_documento, 
        pago.id_cuenta as id_cuenta, 
        pago.cheque as cheque
    FROM 
        formas_pagos_ing_egr as pago
    INNER JOIN 
        ingresos_egresos as ing_egr 
        ON ing_egr.codigo_documento = pago.codigo_documento
    WHERE 
        pago.ruc_empresa = '" . $ruc_empresa . "'
        AND ing_egr.fecha_ing_egr BETWEEN '" . $fecha_desde . "' AND '" . $fecha_hasta . "'
        AND pago.tipo_documento = '" . $tipo . "'
		AND ing_egr.id_cli_pro = '" . $id_cliente . "'
    ORDER BY 
        ing_egr.fecha_ing_egr asc
");

	return $resultado;
}




function saldo_factura($con, $ruc_empresa, $id_encabezado_factura)
{

	$detalle_documento = mysqli_query($con, "SELECT * FROM encabezado_factura WHERE id_encabezado_factura = '" . $id_encabezado_factura . "'");
	$row_documento = mysqli_fetch_array($detalle_documento);
	$numero_documento = $row_documento['serie_factura'] . "-" . str_pad($row_documento['secuencial_factura'], 9, "000000000", STR_PAD_LEFT);
	$total_documento = $row_documento['total_factura'];

	$detalle_pagos = mysqli_query($con, "SELECT round(sum(det.valor_ing_egr),2) as ingresos FROM detalle_ingresos_egresos as det WHERE det.codigo_documento_cv = '" . $id_encabezado_factura . "' and det.tipo_documento='INGRESO' and det.estado='OK' group by det.codigo_documento_cv");
	$row_ingresos = mysqli_fetch_array($detalle_pagos);
	$total_ingresos = isset($row_ingresos['ingresos']) ? $row_ingresos['ingresos'] : 0;

	//det.ruc_empresa= '" . $ruc_empresa . "' and

	$detalle_nc = mysqli_query($con, "SELECT round(sum(total_nc),2) as total_nc FROM encabezado_nc WHERE factura_modificada = '" . $numero_documento . "' and mid(ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' group by factura_modificada");
	$row_nc = mysqli_fetch_array($detalle_nc);
	$total_nc = isset($row_nc['total_nc']) ? $row_nc['total_nc'] : 0;

	$detalle_retenciones = mysqli_query($con, "SELECT round(sum(valor_retenido),2) as valor_retenido FROM cuerpo_retencion_venta as cue_ret INNER JOIN encabezado_retencion_venta as enc_ret ON enc_ret.codigo_unico=cue_ret.codigo_unico WHERE enc_ret.numero_documento = '" . str_replace("-", "", $numero_documento) . "' and mid(enc_ret.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' group by enc_ret.numero_documento");
	$row_retenciones = mysqli_fetch_array($detalle_retenciones);
	$total_retencion = isset($row_retenciones['valor_retenido']) ? $row_retenciones['valor_retenido'] : 0;

	$saldo = number_format($total_documento - $total_ingresos - $total_nc - $total_retencion, 2, '.', '');
	return $saldo;
}

function saldo_recibo($con, $id_encabezado_recibo)
{
	$detalle_documento = mysqli_query($con, "SELECT * FROM encabezado_recibo WHERE id_encabezado_recibo = '" . $id_encabezado_recibo . "'");
	$row_documento = mysqli_fetch_array($detalle_documento);
	$total_documento = $row_documento['total_recibo'];

	$id_encabezado_recibo = "RV" . $id_encabezado_recibo;
	$detalle_pagos = mysqli_query($con, "SELECT round(sum(det.valor_ing_egr),2) as ingresos FROM detalle_ingresos_egresos as det WHERE det.codigo_documento_cv = '" . $id_encabezado_recibo . "' and det.tipo_documento='INGRESO' and det.estado='OK' group by det.codigo_documento_cv");
	$row_ingresos = mysqli_fetch_array($detalle_pagos);
	$total_ingresos = isset($row_ingresos['ingresos']) ? $row_ingresos['ingresos'] : 0;

	$saldo = number_format($total_documento - $total_ingresos, 2, '.', '');
	return $saldo;
}

function forma_pago($id_cuenta, $cheque, $codigo_forma_pago, $con, $documento, $row)
{

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
	return $tipo . $cuenta_banco;
}

?>