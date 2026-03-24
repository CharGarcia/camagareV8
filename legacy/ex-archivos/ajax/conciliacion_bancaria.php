<?PHP
include("../conexiones/conectalogin.php");
session_start();
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

if ($action == 'conciliacion_bancaria') {
	ini_set('date.timezone', 'America/Guayaquil');
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$cuenta = $_POST['cuenta'];
	$fecha_desde = $_POST['fecha_desde'];
	$fecha_hasta = $_POST['fecha_hasta'];
	$con = conenta_login();
	$suma_creditos_saldo_inicial = saldo_inicial_creditos($con, $cuenta, $ruc_empresa, $fecha_desde);
	$suma_debitos_saldo_inicial = saldo_inicial_debitos($con, $cuenta, $ruc_empresa, $fecha_desde);
	$cheques_saldo_inicial = cheques_saldo_inicial($con, $cuenta, $ruc_empresa, $fecha_desde);
	$saldo_inicial = $suma_creditos_saldo_inicial - $suma_debitos_saldo_inicial - $cheques_saldo_inicial;

	$total_creditos = creditos_debitos($con, $cuenta, $ruc_empresa, $fecha_desde, $fecha_hasta, 'INGRESO');
	$total_debitos = creditos_debitos($con, $cuenta, $ruc_empresa, $fecha_desde, $fecha_hasta, 'EGRESO');
	$cheques_pagados = cheques_pagados($con, $cuenta, $ruc_empresa, $fecha_desde, $fecha_hasta);

	$saldo_final = $saldo_inicial + $total_creditos - $total_debitos - $cheques_pagados;
?>
	<div class="panel-group" id="accordiones">

		<div class="panel panel-success">
			<a class="list-group-item list-group-item-success" data-toggle="collapse" data-parent="#accordiones" href="#resumen"><span class="caret"></span> <b> Resumen de la conciliación bancaria</b> </a>
			<div id="resumen" class="panel-collapse collapse in">
				<div class="table-responsive">
					<table class="table table-hover">
						<tr class="success">
							<th style="padding: 2px;" class="text-center">Saldo Inicial</th>
							<th style="padding: 2px;" class="text-center">Créditos</th>
							<th style="padding: 2px;" class="text-center">Débitos</th>
							<th style="padding: 2px;" class="text-center">Saldo Final</th>
						</tr>
						<tr>
							<td style="padding: 2px;" class="text-center"><?php echo number_format($saldo_inicial, 2, '.', ''); ?></td>
							<td style="padding: 2px;" class="text-center"><?php echo number_format($total_creditos, 2, '.', ''); ?></td>
							<td style="padding: 2px;" class="text-center"><?php echo number_format($total_debitos + $cheques_pagados, 2, '.', ''); ?></td>
							<td style="padding: 2px;" class="text-center"><?php echo number_format($saldo_final, 2, '.', ''); ?></td>
						</tr>
					</table>
				</div>
			</div>
		</div>

		<div class="panel panel-info">
			<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordiones" href="#ingresos"><span class="caret"></span> <b> Detalle de créditos</b> </a>
			<div id="ingresos" class="panel-collapse collapse">
				<div class="table-responsive">
					<table class="table table-hover">
						<tr class="info">
							<th style="padding: 2px;">Fecha</th>
							<th style="padding: 2px;">Ingreso</th>
							<th style="padding: 2px;">Recibido de</th>
							<th style="padding: 2px;">Detalle</th>
							<th style="padding: 2px;" class="text-center">Tipo</th>
							<th style="padding: 2px;">Valor</th>
						</tr>
						<?php
						$sql_ingresos = detalle_creditos_debitos($con, $cuenta, $ruc_empresa, $fecha_desde, $fecha_hasta, 'INGRESO');
						$total_ingresos = 0;
						while ($row_ingresos = mysqli_fetch_array($sql_ingresos)) {
							$fecha_pago = $row_ingresos['fecha_pago'];
							$codigo_documento = $row_ingresos['codigo_documento'];
							$nombre_ingreso = $row_ingresos['nombre_ing_egr'];
							$numero_ing_egr = $row_ingresos['numero_ing_egr'];
							$valor = $row_ingresos['valor_forma_pago'];
							$total_ingresos += $valor;
							$detalle_pago = $row_ingresos['detalle_pago'];

							switch ($row_ingresos['detalle_pago']) {
								case "D":
									$tipo = 'Dep';
									break;
								case "T":
									$tipo = 'Transf';
									break;
							}
							$sql_detalle_ingresos = detalle_ingresos_egresos($con, $codigo_documento);
						?>
							<tr>
								<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha_pago)); ?></td>
								<td style="padding: 2px;"><?php echo $numero_ing_egr; ?></td>
								<td style="padding: 2px;"><?php echo $nombre_ingreso; ?></td>
								<td style="padding: 2px;"><?php foreach ($sql_detalle_ingresos as $detalle) {
																echo $detalle['detalle_ing_egr'] . "<br>";
															} ?></td>
								<td style="padding: 2px;" class="text-center"><?php echo $tipo; ?></td>
								<td style="padding: 2px;"><?php echo number_format($valor, 2, '.', ''); ?></td>
							</tr>
						<?php
						}
						?>
						<tr class="info">
							<th style="padding: 2px;" colspan="5" class="text-right">Total ingresos: </th>
							<th style="padding: 2px;"><?php echo number_format($total_ingresos, 2, '.', ''); ?></th>
						</tr>
					</table>
				</div>
			</div>
		</div>

		<div class="panel panel-info">
			<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordiones" href="#egresos"><span class="caret"></span> <b> Detalle de débitos</b> </a>
			<div id="egresos" class="panel-collapse collapse">
				<div class="table-responsive">
					<table class="table table-hover">
						<tr class="info">
							<th style="padding: 2px;">Fecha</th>
							<th style="padding: 2px;">Egreso</th>
							<th style="padding: 2px;">Pagado a</th>
							<th style="padding: 2px;">Detalle</th>
							<th style="padding: 2px;">Tipo</th>
							<th style="padding: 2px;">Valor</th>
						</tr>
						<?php
						$sql_egresos = detalle_creditos_debitos($con, $cuenta, $ruc_empresa, $fecha_desde, $fecha_hasta, 'EGRESO');
						$total_egresos = 0;
						while ($row_egresos = mysqli_fetch_array($sql_egresos)) {
							$fecha_pago = $row_egresos['cheque'] > 0 ? $row_egresos['fecha_entrega'] : $row_egresos['fecha_pago'];
							$codigo_documento = $row_egresos['codigo_documento'];
							$nombre_egreso = $row_egresos['nombre_ing_egr'];
							$numero_ing_egr = $row_egresos['numero_ing_egr'];
							$valor = $row_egresos['valor_forma_pago'];
							$total_egresos += $valor;
							switch ($row_egresos['detalle_pago']) {
								case "D":
									$tipo = 'Déb';
									break;
								case "T":
									$tipo = 'Transf';
									break;
								case "C":
									$tipo = 'Ch';
									break;
							}

							$cheque = $row_egresos['cheque'] == 0 ? "" : " " . $row_egresos['cheque'];
							$detalle_pago = $tipo . $cheque;
							$sql_detalle_egresos = detalle_ingresos_egresos($con, $codigo_documento);
						?>
							<tr>
								<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha_pago)); ?></td>
								<td style="padding: 2px;"><?php echo $numero_ing_egr; ?></td>
								<td style="padding: 2px;"><?php echo $nombre_egreso; ?></td>
								<td style="padding: 2px;"><?php foreach ($sql_detalle_egresos as $detalle) {
																echo $detalle['detalle_ing_egr'] . "<br>";
															} ?></td>
								<td style="padding: 2px;"><?php echo $detalle_pago; ?></td>
								<td style="padding: 2px;"><?php echo number_format($valor, 2, '.', ''); ?></td>
							</tr>
						<?php
						}
						?>
						<tr class="info">
							<th style="padding: 2px;" colspan="5" class="text-right">Total débitos: </th>
							<th style="padding: 2px;"><?php echo number_format($total_egresos, 2, '.', ''); ?></th>
						</tr>
					</table>
				</div>
			</div>
		</div>

		<div class="panel panel-warning">
			<a class="list-group-item list-group-item-warning" data-toggle="collapse" data-parent="#accordiones" href="#chequesemitidos"><span class="caret"></span> <b> Detalle de cheques emitidos en el período</b> </a>
			<div id="chequesemitidos" class="panel-collapse collapse">
				<div class="table-responsive">
					<table class="table table-hover">
						<tr class="warning">
							<th style="padding: 2px;">Fecha emisión</th>
							<th style="padding: 2px;">Fecha en Cheque</th>
							<th style="padding: 2px;">Fecha cobro</th>
							<th style="padding: 2px;">Egreso</th>
							<th style="padding: 2px;">No. cheque</th>
							<th style="padding: 2px;">Beneficiario</th>
							<th style="padding: 2px;">Detalle</th>
							<th style="padding: 2px;">Valor</th>
						</tr>
						<?php
						$sql_cheques = cheques_emitidos($con, $cuenta, $ruc_empresa, $fecha_desde, $fecha_hasta);
						$total_cheques = 0;
						while ($row_cheques = mysqli_fetch_array($sql_cheques)) {
							$fecha_emision = $row_cheques['fecha_emision'];
							$fecha_entrega = $row_cheques['fecha_entrega'];
							$fecha_pago = $row_cheques['fecha_pago'];
							$estado_pago = $row_cheques['estado_pago'];
							$codigo_documento = $row_cheques['codigo_documento'];
							$nombre_egreso =  $row_cheques['nombre_ing_egr'];
							$numero_ing_egr = $row_cheques['numero_ing_egr'];
							$numero_cheque = $row_cheques['cheque'];
							$valor = $row_cheques['valor_forma_pago'];
							$total_cheques += $valor;
							$sql_detalle_cheques = detalle_ingresos_egresos($con, $codigo_documento);
						?>
							<tr>
								<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha_emision)); ?></td>
								<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha_pago)); ?></td>
								<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha_entrega)); ?></td>
								<td style="padding: 2px;"><?php echo $numero_ing_egr; ?></td>
								<td style="padding: 2px;"><?php echo $numero_cheque; ?></td>
								<td style="padding: 2px;"><?php echo $nombre_egreso; ?></td>
								<td style="padding: 2px;"><?php foreach ($sql_detalle_cheques as $detalle) {
																echo $detalle['detalle_ing_egr'] . "<br>";
															} ?></td>
								<td style="padding: 2px;"><?php echo number_format($valor, 2, '.', ''); ?></td>
							</tr>
						<?php
						}
						?>
						<tr class="warning">
							<th style="padding: 2px;" colspan="7" class="text-right">Total: </th>
							<th style="padding: 2px;"><?php echo number_format($total_cheques, 2, '.', ''); ?></th>
						</tr>
					</table>
				</div>
			</div>
		</div>

		<div class="panel panel-warning">
			<a class="list-group-item list-group-item-warning" data-toggle="collapse" data-parent="#accordiones" href="#chequespagados"><span class="caret"></span> <b> Detalle de cheques pagados en el período</b> </a>
			<div id="chequespagados" class="panel-collapse collapse">
				<div class="table-responsive">
					<table class="table table-hover">
						<tr class="warning">
							<th style="padding: 2px;">Fecha emisión</th>
							<th style="padding: 2px;">Fecha en cheque</th>
							<th style="padding: 2px;">Fecha Cobro</th>
							<th style="padding: 2px;">Egreso</th>
							<th style="padding: 2px;">No. cheque</th>
							<th style="padding: 2px;">Beneficiario</th>
							<th style="padding: 2px;">Detalle</th>
							<th style="padding: 2px;">Valor</th>
						</tr>
						<?php
						$sql_cheques_pagados = detalle_cheques_pagados($con, $cuenta, $ruc_empresa, $fecha_desde, $fecha_hasta);
						$total_cheques = 0;
						while ($row_cheques = mysqli_fetch_array($sql_cheques_pagados)) {
							$fecha_emision = $row_cheques['fecha_emision'];
							$fecha_entrega = $row_cheques['fecha_entrega'];
							$fecha_pago = $row_cheques['fecha_pago'];
							$codigo_documento = $row_cheques['codigo_documento'];
							$nombre_egreso = $row_cheques['nombre_ing_egr'];
							$numero_ing_egr = $row_cheques['numero_ing_egr'];
							$numero_cheque = $row_cheques['cheque'];
							$valor = $row_cheques['valor_forma_pago'];
							$total_cheques += $valor;
							$sql_detalle_cheques = detalle_ingresos_egresos($con, $codigo_documento);
						?>
							<tr>
								<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha_emision)); ?></td>
								<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha_pago)); ?></td>
								<td style="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha_entrega)); ?></td>
								<td style="padding: 2px;"><?php echo $numero_ing_egr; ?></td>
								<td style="padding: 2px;"><?php echo $numero_cheque; ?></td>
								<td style="padding: 2px;"><?php echo $nombre_egreso; ?></td>
								<td style="padding: 2px;"><?php foreach ($sql_detalle_cheques as $detalle) {
																echo $detalle['detalle_ing_egr'] . "<br>";
															} ?></td>
								<td style="padding: 2px;"><?php echo number_format($valor, 2, '.', ''); ?></td>
							</tr>
						<?php
						}
						?>
						<tr class="warning">
							<th style="padding: 2px;" colspan="7" class="text-right">Total: </th>
							<th style="padding: 2px;"><?php echo number_format($total_cheques, 2, '.', ''); ?></th>
						</tr>
					</table>
				</div>
			</div>
		</div>
	</div>
<?php
}


function creditos_debitos($con, $cuenta, $ruc_empresa, $fecha_desde, $fecha_hasta, $tipo)
{
	// Normaliza fechas para no romper índices
	$desde = date("Y-m-d", strtotime($fecha_desde)) . " 00:00:00";
	$hasta = date("Y-m-d", strtotime($fecha_hasta)) . " 23:59:59";

	// Escapa/normaliza parámetros
	$cuenta_sql      = mysqli_real_escape_string($con, $cuenta);
	$ruc_sql         = mysqli_real_escape_string($con, $ruc_empresa);
	$tipo_sql        = mysqli_real_escape_string($con, $tipo);
	$desde_sql       = mysqli_real_escape_string($con, $desde);
	$hasta_sql       = mysqli_real_escape_string($con, $hasta);

	$sql = "
        SELECT COALESCE(ROUND(SUM(valor_forma_pago), 2), 0) AS total
        FROM formas_pagos_ing_egr
        WHERE id_cuenta      = '{$cuenta_sql}'
          AND ruc_empresa    = '{$ruc_sql}'
          AND tipo_documento = '{$tipo_sql}'
          AND estado         = 'OK'
          AND detalle_pago  <> 'C'
          AND fecha_pago BETWEEN '{$desde_sql}' AND '{$hasta_sql}'
        LIMIT 1
    ";

	$res = mysqli_query($con, $sql);
	if (!$res) {
		// Si quieres ver el error SQL en desarrollo, descomenta:
		// die('SQL error: ' . mysqli_error($con));
		return 0.00;
	}

	$row = mysqli_fetch_assoc($res);
	return $row ? (float)$row['total'] : 0.00;
}


function detalle_creditos_debitos($con, $cuenta, $ruc_empresa, $fecha_desde, $fecha_hasta, $tipo)
{
	// Rango completo del día para no perder registros
	$desde = date("Y-m-d", strtotime($fecha_desde)) . " 00:00:00";
	$hasta = date("Y-m-d", strtotime($fecha_hasta)) . " 23:59:59";

	// Escapar parámetros
	$cuenta_sql   = mysqli_real_escape_string($con, $cuenta);
	$ruc_sql      = mysqli_real_escape_string($con, $ruc_empresa);
	$tipo_sql     = mysqli_real_escape_string($con, $tipo);
	$desde_sql    = mysqli_real_escape_string($con, $desde);
	$hasta_sql    = mysqli_real_escape_string($con, $hasta);

	// Nota: si en tu esquema ing_egr tiene ruc_empresa, conviene añadir
	// "AND ing_egr.ruc_empresa = pagos.ruc_empresa" al JOIN para mayor seguridad.
	$sql = "
        SELECT pagos.*, ing_egr.*
        FROM formas_pagos_ing_egr AS pagos
        INNER JOIN ingresos_egresos AS ing_egr
            ON ing_egr.codigo_documento = pagos.codigo_documento
        WHERE pagos.id_cuenta      = '{$cuenta_sql}'
          AND pagos.ruc_empresa    = '{$ruc_sql}'
          AND pagos.tipo_documento = '{$tipo_sql}'
          AND pagos.estado         = 'OK'
          AND pagos.estado_pago    = 'PAGADO'
          AND (
                (pagos.cheque > 0 AND pagos.fecha_entrega BETWEEN '{$desde_sql}' AND '{$hasta_sql}')
             OR ( (pagos.cheque IS NULL OR pagos.cheque <= 0) AND pagos.fecha_pago BETWEEN '{$desde_sql}' AND '{$hasta_sql}')
          )
        ORDER BY
          CASE WHEN pagos.cheque > 0 THEN pagos.fecha_entrega ELSE pagos.fecha_pago END ASC
    ";

	return mysqli_query($con, $sql);
}


function cheques_emitidos($con, $cuenta, $ruc_empresa, $fecha_desde, $fecha_hasta)
{
	// Rango completo del día
	$desde = date("Y-m-d", strtotime($fecha_desde)) . " 00:00:00";
	$hasta = date("Y-m-d", strtotime($fecha_hasta)) . " 23:59:59";

	// Escapar parámetros
	$cuenta_sql = mysqli_real_escape_string($con, $cuenta);
	$ruc_sql    = mysqli_real_escape_string($con, $ruc_empresa);
	$desde_sql  = mysqli_real_escape_string($con, $desde);
	$hasta_sql  = mysqli_real_escape_string($con, $hasta);

	// Nota: si ingresos_egresos también tiene ruc_empresa, añade:
	// AND ing_egr.ruc_empresa = pagos.ruc_empresa
	$sql = "
        SELECT pagos.*, ing_egr.*
        FROM formas_pagos_ing_egr AS pagos
        INNER JOIN ingresos_egresos AS ing_egr
            ON ing_egr.codigo_documento = pagos.codigo_documento
        WHERE pagos.id_cuenta   = '{$cuenta_sql}'
          AND pagos.ruc_empresa = '{$ruc_sql}'
          AND pagos.tipo_documento = 'EGRESO'
          AND pagos.cheque > 0
          AND pagos.estado = 'OK'
          AND pagos.fecha_emision BETWEEN '{$desde_sql}' AND '{$hasta_sql}'
        ORDER BY pagos.fecha_emision ASC
    ";

	return mysqli_query($con, $sql);
}


function detalle_ingresos_egresos($con, $codigo_documento)
{
	$sql_detalle = mysqli_query($con, "SELECT * FROM detalle_ingresos_egresos WHERE codigo_documento='" . $codigo_documento . "' ");
	return $sql_detalle;
}

function saldo_inicial_creditos($con, $cuenta, $ruc_empresa, $fecha_desde)
{
	// Fecha límite (todo lo anterior a esta fecha)
	$fecha_limite = date("Y-m-d", strtotime($fecha_desde)) . " 00:00:00";

	// Escapar parámetros
	$cuenta_sql = mysqli_real_escape_string($con, $cuenta);
	$ruc_sql    = mysqli_real_escape_string($con, $ruc_empresa);
	$fecha_sql  = mysqli_real_escape_string($con, $fecha_limite);

	// Consulta optimizada sin DATE_FORMAT
	$sql = "
        SELECT COALESCE(ROUND(SUM(valor_forma_pago), 2), 0) AS total
        FROM formas_pagos_ing_egr
        WHERE id_cuenta      = '{$cuenta_sql}'
          AND ruc_empresa    = '{$ruc_sql}'
          AND tipo_documento = 'INGRESO'
          AND fecha_pago < '{$fecha_sql}'
          AND estado_pago    = 'PAGADO'
          AND estado         = 'OK'
          AND id_cuenta > 0
        LIMIT 1
    ";

	$res = mysqli_query($con, $sql);
	if (!$res) {
		// Para debug en desarrollo:
		// die('Error SQL: ' . mysqli_error($con));
		return 0.00;
	}

	$row = mysqli_fetch_assoc($res);
	return $row ? (float)$row['total'] : 0.00;
}


function saldo_inicial_debitos($con, $cuenta, $ruc_empresa, $fecha_desde)
{
	// Todo lo anterior a la fecha_desde (inicio del día)
	$fecha_limite = date("Y-m-d", strtotime($fecha_desde)) . " 00:00:00";

	// Escapar parámetros (PHP 5.6)
	$cuenta_sql = mysqli_real_escape_string($con, $cuenta);
	$ruc_sql    = mysqli_real_escape_string($con, $ruc_empresa);
	$fecha_sql  = mysqli_real_escape_string($con, $fecha_limite);

	$sql = "
        SELECT COALESCE(ROUND(SUM(valor_forma_pago), 2), 0) AS total
        FROM formas_pagos_ing_egr
        WHERE id_cuenta      = '{$cuenta_sql}'
          AND ruc_empresa    = '{$ruc_sql}'
          AND tipo_documento = 'EGRESO'
          AND fecha_pago     < '{$fecha_sql}'
          AND estado_pago    = 'PAGADO'
          AND estado         = 'OK'
          AND detalle_pago  <> 'C'
        LIMIT 1
    ";

	$res = mysqli_query($con, $sql);
	if (!$res) {
		// Para depurar en desarrollo:
		// die('SQL error: ' . mysqli_error($con));
		return 0.00;
	}

	$row = mysqli_fetch_assoc($res);
	return $row ? (float)$row['total'] : 0.00;
}


function cheques_saldo_inicial($con, $cuenta, $ruc_empresa, $fecha_desde)
{
	// Todo lo anterior al inicio de fecha_desde
	$fecha_limite = date("Y-m-d", strtotime($fecha_desde)) . " 00:00:00";

	// Escapar parámetros (PHP 5.6)
	$cuenta_sql = mysqli_real_escape_string($con, $cuenta);
	$ruc_sql    = mysqli_real_escape_string($con, $ruc_empresa);
	$fecha_sql  = mysqli_real_escape_string($con, $fecha_limite);

	$sql = "
        SELECT COALESCE(ROUND(SUM(valor_forma_pago), 2), 0) AS total
        FROM formas_pagos_ing_egr
        WHERE id_cuenta      = '{$cuenta_sql}'
          AND ruc_empresa    = '{$ruc_sql}'
          AND tipo_documento = 'EGRESO'
          AND detalle_pago   = 'C'          -- cheques
          AND estado_pago    = 'PAGADO'
          AND estado         = 'OK'
          AND fecha_entrega  < '{$fecha_sql}'
        LIMIT 1
    ";

	$res = mysqli_query($con, $sql);
	if (!$res) {
		// Para depurar si algo falla:
		// die('SQL error: ' . mysqli_error($con));
		return 0.00;
	}

	$row = mysqli_fetch_assoc($res);
	return $row ? (float)$row['total'] : 0.00;
}


function cheques_pagados($con, $cuenta, $ruc_empresa, $fecha_desde, $fecha_hasta)
{
	// Rango completo
	$desde = date("Y-m-d", strtotime($fecha_desde)) . " 00:00:00";
	$hasta = date("Y-m-d", strtotime($fecha_hasta)) . " 23:59:59";

	// Escapar parámetros (PHP 5.6)
	$cuenta_sql = mysqli_real_escape_string($con, $cuenta);
	$ruc_sql    = mysqli_real_escape_string($con, $ruc_empresa);
	$desde_sql  = mysqli_real_escape_string($con, $desde);
	$hasta_sql  = mysqli_real_escape_string($con, $hasta);

	// Agregado: no hace falta ORDER BY
	$sql = "
        SELECT COALESCE(ROUND(SUM(valor_forma_pago), 2), 0) AS total
        FROM formas_pagos_ing_egr
        WHERE id_cuenta      = '{$cuenta_sql}'
          AND ruc_empresa    = '{$ruc_sql}'
          AND tipo_documento = 'EGRESO'
          AND detalle_pago   = 'C'            -- cheques
          AND estado         = 'OK'
          AND estado_pago    = 'PAGADO'
          AND fecha_entrega BETWEEN '{$desde_sql}' AND '{$hasta_sql}'
        LIMIT 1
    ";

	$res = mysqli_query($con, $sql);
	if (!$res) {
		// En dev, habilita para ver el error:
		// die('SQL error: ' . mysqli_error($con));
		return 0.00;
	}

	$row = mysqli_fetch_assoc($res);
	return $row ? (float)$row['total'] : 0.00;
}


function detalle_cheques_pagados($con, $cuenta, $ruc_empresa, $fecha_desde, $fecha_hasta)
{
	// Rango completo de fechas
	$desde = date("Y-m-d", strtotime($fecha_desde)) . " 00:00:00";
	$hasta = date("Y-m-d", strtotime($fecha_hasta)) . " 23:59:59";

	// Escapar parámetros (PHP 5.6)
	$cuenta_sql = mysqli_real_escape_string($con, $cuenta);
	$ruc_sql    = mysqli_real_escape_string($con, $ruc_empresa);
	$desde_sql  = mysqli_real_escape_string($con, $desde);
	$hasta_sql  = mysqli_real_escape_string($con, $hasta);

	// Nota: si ingresos_egresos tiene ruc_empresa, conviene asegurar:
	// AND detalle.ruc_empresa = pagos.ruc_empresa
	$sql = "
        SELECT pagos.*, detalle.*
        FROM formas_pagos_ing_egr AS pagos
        INNER JOIN ingresos_egresos AS detalle
            ON detalle.codigo_documento = pagos.codigo_documento
        WHERE pagos.id_cuenta      = '{$cuenta_sql}'
          AND pagos.ruc_empresa    = '{$ruc_sql}'
          AND pagos.tipo_documento = 'EGRESO'
          AND pagos.cheque         > 0
          AND pagos.estado_pago    = 'PAGADO'
          AND pagos.estado         = 'OK'
          AND pagos.fecha_entrega BETWEEN '{$desde_sql}' AND '{$hasta_sql}'
        ORDER BY pagos.fecha_entrega ASC
    ";

	return mysqli_query($con, $sql);
}



function detalle_asiento_contable($con, $id_cuenta_bancaria, $ruc_empresa, $fecha_desde, $fecha_hasta, $tipo, $id_documento)
{
	$ruc_empresa_sql        = mysqli_real_escape_string($con, $ruc_empresa);
	$id_cuenta_bancaria_sql = mysqli_real_escape_string($con, $id_cuenta_bancaria);

	$sql_cta_contable = "
        SELECT id_cuenta AS id_cuenta_contable
        FROM asientos_programados
        WHERE tipo_asiento = 'bancos'
          AND ruc_empresa  = '{$ruc_empresa_sql}'
          AND id_pro_cli   = '{$id_cuenta_bancaria_sql}'
        LIMIT 1
    ";
	$res_cta = mysqli_query($con, $sql_cta_contable);
	$row_cta = $res_cta ? mysqli_fetch_assoc($res_cta) : null;
	$id_cuenta_contable = ($row_cta && isset($row_cta['id_cuenta_contable'])) ? $row_cta['id_cuenta_contable'] : null;

	if (!$id_cuenta_contable) {
		return array('id_diarios' => '', 'total_debe' => 0, 'total_haber' => 0);
	}

	$desde = date("Y-m-d", strtotime($fecha_desde)) . " 00:00:00";
	$hasta = date("Y-m-d", strtotime($fecha_hasta)) . " 23:59:59";

	$tipo_sql               = mysqli_real_escape_string($con, $tipo);
	$id_documento_sql       = mysqli_real_escape_string($con, $id_documento);
	$id_cuenta_contable_sql = mysqli_real_escape_string($con, $id_cuenta_contable);

	$sql = "
        SELECT 
            GROUP_CONCAT(DISTINCT enc.id_diario ORDER BY enc.id_diario ASC) AS id_diarios,
            COALESCE(SUM(det.debe),  0) AS total_debe,
            COALESCE(SUM(det.haber), 0) AS total_haber
        FROM detalle_diario_contable AS det
        INNER JOIN encabezado_diario AS enc 
            ON enc.codigo_unico = det.codigo_unico
        WHERE enc.ruc_empresa   = '{$ruc_empresa_sql}'
          AND enc.tipo          = '{$tipo_sql}'
          AND enc.codigo_unico  = '{$id_documento_sql}'
          AND det.id_cuenta     = '{$id_cuenta_contable_sql}'
          AND enc.estado       != 'Anulado'
          AND enc.fecha_asiento BETWEEN '{$desde}' AND '{$hasta}'
    ";
	$res = mysqli_query($con, $sql);
	$row = $res ? mysqli_fetch_assoc($res) : null;

	if (!$row) {
		return array('id_diarios' => '', 'total_debe' => 0, 'total_haber' => 0);
	}
	// Asegura claves
	return array(
		'id_diarios'  => isset($row['id_diarios']) ? $row['id_diarios'] : '',
		'total_debe'  => isset($row['total_debe']) ? (float)$row['total_debe'] : 0,
		'total_haber' => isset($row['total_haber']) ? (float)$row['total_haber'] : 0,
	);
}

?>