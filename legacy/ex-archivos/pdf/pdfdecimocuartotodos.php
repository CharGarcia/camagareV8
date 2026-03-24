<?php
$empresa = $data['empresa'];
$decimocuarto = $data['decimocuarto'];
$usuario = $data['usuario'];
$anio = $data['anio'];
$region = $data['region'] = '1' ? "Sierra-Amazonica" : "Costa";
$logo = $data['logo'];
?>
<!DOCTYPE html>
<html lang="es">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Décimo Cuarto</title>
	<style>
		table {
			width: 100%;
		}

		table td,
		table th {
			font-size: 12px;
		}

		h4 {
			margin-bottom: 0px;
		}

		.text-center {
			text-align: center;
		}

		.text-right {
			text-align: right;
		}

		.tbl-cliente {
			border: 1px solid #CCC;
			border-radius: 10px;
			padding: 5px;
		}

		.wd5 {
			width: 5%;
		}

		.wd10 {
			width: 10%;
		}

		.wd30 {
			width: 30%;
		}

		.wd35 {
			width: 35%;
		}

		.wd40 {
			width: 40%;
		}

		.wd55 {
			width: 55%;
		}

		.tbl-detalle {
			border-collapse: collapse;
		}

		.tbl-detalle thead th {
			padding: 5px;
			background-color: #cdcdcd;
			color: #FFF;
		}

		.tbl-detalle tbody td {
			border-bottom: 1px solid #CCC;
			padding: 5px;
			height: 20px;
		}
	</style>
</head>

<body>
	<table class="tbl-hader">
		<tbody>
			<tr>
				<td class="text-left wd10">
					<?php
					if (empty($logo['logo'])) {
					?>
						logo
					<?php
					} else {
					?>
						<img src="../logos_empresas/<?php echo $logo['logo']; ?>" alt="Logo" style="height:80px;">
					<?php
					}
					?>
				</td>
				<td class="text-center wd30">
					<h4><strong><?= $empresa['nombre']; ?></strong></h4>
					<h4><strong>Décimo Cuarto Sueldo <?php echo $anio; ?></strong></h4>
					<h5><strong>Región <?php echo $region; ?></strong></h5>
					<p><?= $empresa['direccion']; ?><br>
						RUC: <?= $empresa['ruc']; ?> <br>
						Teléfono: <?= $empresa['telefono']; ?> <br>
						Email: <?= $empresa['mail']; ?></p>
				</td>
			</tr>
		</tbody>
	</table>
	<br>
	<table class="tbl-detalle">
		<thead>
			<tr>
				<th class="wd5">No.</th>
				<th class="wd15">Cédula</th>
				<th class="wd30">Nombres y apellidos</th>
				<th class="wd5 text-right">Días</th>
				<th class="wd10 text-right">Décimo</th>
				<th class="wd10 text-right">Anticípos</th>
				<th class="wd10 text-right">Abonos</th>
				<th class="wd10 text-right">A pagar</th>
				<th class="wd10 text-right">Firma</th>
			</tr>
		</thead>
		<tbody>
			<?php

			$subtotal_decimo = 0;
			$subtotal_mensual = 0;
			$subtotal_abonos = 0;
			$subtotal_arecibir = 0;
			$num = 0;
			foreach ($decimocuarto as $detalle) {
				$subtotal_decimo += $detalle['decimo'];
				$subtotal_mensual += $detalle['anticipos'];
				$subtotal_abonos += $detalle['abonos'];
				$subtotal_arecibir += $detalle['arecibir'];
				$num++;
			?>
				<tr>
					<td><?= $num; ?></td>
					<td><?= $detalle['cedula']; ?></td>
					<td><?= ucwords($detalle['empleado']); ?></td>
					<td class="text-right"><?= formatMoney($detalle['dias'], 0); ?></td>
					<td class="text-right"><?= formatMoney($detalle['decimo'], 2); ?></td>
					<td class="text-right"><?= formatMoney($detalle['anticipos'], 2); ?></td>
					<td class="text-right"><?= formatMoney($detalle['abonos'], 2); ?></td>
					<td class="text-right"><?= formatMoney($detalle['arecibir'], 2); ?></td>
					<td></td>
				</tr>
			<?php
			}
			?>
			<tr>
				<td></td>
				<td></td>
				<td></td>
				<td class="text-right">Totales: </td>
				<td class="text-right"><?= formatMoney($subtotal_decimo, 2); ?></td>
				<td class="text-right"><?= formatMoney($subtotal_mensual, 2); ?></td>
				<td class="text-right"><?= formatMoney($subtotal_abonos, 2); ?></td>
				<td class="text-right"><?= formatMoney($subtotal_arecibir, 2); ?></td>
			</tr>
		</tbody>
	</table>
	<br>
	<table>
		<tbody>
			<tr>
				<td>Realizado por: </td>
				<td><?= ucwords($usuario['nombre']); ?></td>
			</tr>
			<tr>
				<td>Aprobado por:</td>
				<td></td>
			</tr>
		</tbody>
	</table>
</body>

</html>