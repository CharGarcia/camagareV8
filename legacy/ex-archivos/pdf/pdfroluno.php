<?php
$empresa = $data['empresa'];
$rol = $data['rol'];
$logo = $data['logo'];
$usuario = $data['usuario'];
$meses = $data['meses'];
$detalles = $data['detalle'];
$novedades = $data['novedades'];
$calculos_salarios = $data['salario'];
$ing_des_fijos = $data['ing_des_fijos'];
$incremento_hora_nocturna = 1 + ($calculos_salarios['hora_nocturna'] / 100);
$incremento_hora_suplementaria = 1 + ($calculos_salarios['hora_suplementaria'] / 100);
$incremento_hora_extraordinaria = 1 + ($calculos_salarios['hora_extraordinaria'] / 100);
$hora_normal = number_format($rol['sueldo'] / $calculos_salarios['hora_normal'], 2, '.', '');
$calculo_hora_nocturna = number_format($hora_normal * $incremento_hora_nocturna, 2, '.', '');
$calculo_hora_suplementaria = number_format($hora_normal * $incremento_hora_suplementaria, 2, '.', '');
$calculo_hora_extraordinaria = number_format($hora_normal * $incremento_hora_extraordinaria, 2, '.', '');
?>
<!DOCTYPE html>
<html lang="es">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Rol de pagos</title>
	<style>
		table {
			width: 100%;
		}

		table td,
		table th {
			font-size: 14px;
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

		.wd10 {
			width: 10%;
		}

		.wd15 {
			width: 15%;
		}

		.wd20 {
			width: 20%;
		}

		.wd33 {
			width: 33.33%;
		}

		.wd40 {
			width: 40%;
		}

		.wd50 {
			width: 50%;
		}

		.wd80 {
			width: 80%;
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
			border-bottom: 0.5px solid #CCC;
			padding: 5px;
			height: 10px;
		}
	</style>
</head>

<body>
	<table class="tbl-hader">
		<tbody>
			<tr>
				<td class="text-center wd33">
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
				<td class="text-center wd50">
					<h4><strong><?= $empresa['nombre']; ?></strong></h4>
					<p><?= $empresa['direccion']; ?><br>
						RUC: <?= $empresa['ruc']; ?> <br>
						Teléfono: <?= $empresa['telefono']; ?> <br>
						Email: <?= $empresa['mail']; ?></p>
				</td>
			</tr>
		</tbody>
	</table>

	<table class="tbl-hader">
		<tbody>
			<tr>
				<td class="text-left wd80">
					<h3><strong>Rol de pagos <?php
												foreach ($meses as $mes) {
													if ($mes['codigo'] == substr($rol['mes_ano'], 0, 2)) {
														echo $mes['nombre'] . substr($rol['mes_ano'], 2, 5);
													}
												}
												?></strong></h3>
					<p>Nombres y apellidos: <strong><?= ucwords($rol['empleado']); ?></strong><br>
						Cédula: <strong><?= $rol['cedula']; ?></strong><br>
						Cargo: <strong><?= $rol['cargo']; ?></strong><br>
					</p>
				</td>
			</tr>
		</tbody>
	</table>
	<br>
	<table class="tbl-detalle">
		<thead>
			<tr>
				<th class="wd20 text-left">Detalle</th>
				<th class="wd10 text-right">Valor</th>
				<th class="wd20 text-left">Detalle Ingresos</th>
				<th class="wd10 text-right">Valor</th>
				<th class="wd20 text-left">Detalle Egresos</th>
				<th class="wd10 text-right">Valor</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td class="text-left">Días laborados</td>
				<td class="text-right"><?= $rol['dias_laborados']; ?></td>
				<td class="text-left">Sueldo</td>
				<td class="text-right"><?= formatMoney($rol['sueldo'], 2); ?></td>
				<td class="text-left">Quincena</td>
				<td class="text-right"><?= formatMoney($rol['quincena'], 2); ?></td>
			</tr>
			<tr>
				<td class="text-left">Aporte Patronal</td>
				<td class="text-right"><?= formatMoney($rol['aporte_patronal'], 2); ?></td>
				<td class="text-left">Otros Ingresos</td>
				<td class="text-right"><?= formatMoney($rol['ingresos_gravados'] + $rol['ingresos_excentos'], 2); ?></td>
				<td class="text-left">Otros Egresos</td>
				<td class="text-right"><?= formatMoney($rol['total_egresos'] - $rol['aporte_personal'] - $rol['quincena'], 2); ?></td>
			</tr>
			<tr>
				<td class="text-left"></td>
				<td class="text-right"></td>
				<td class="text-left">Décimo tercero</td>
				<td class="text-right"><?= formatMoney($rol['tercero'], 2); ?></td>
				<td class="text-left">Aporte Personal</td>
				<td class="text-right"><?= formatMoney($rol['aporte_personal'], 2); ?></td>
			</tr>
			<tr>
				<td class="text-left"></td>
				<td class="text-right"></td>
				<td class="text-left">Décimo cuarto</td>
				<td class="text-right"><?= formatMoney($rol['cuarto'], 2); ?></td>
				<td class="text-left"></td>
				<td class="text-right"></td>
				<td class="text-left"></td>
			</tr>
			<tr>
				<td class="text-left"></td>
				<td class="text-right"></td>
				<td class="text-left">Fondos de reserva</td>
				<td class="text-right"><?= formatMoney($rol['fondo_reserva'], 2); ?></td>
				<td class="text-left"></td>
				<td class="text-right"></td>
			</tr>
			<tr>
				<td class="text-left"></td>
				<td class="text-right"></td>
				<td class="text-left"><b>Total Ingresos (A)</b></td>
				<td class="text-right"><b><?= formatMoney($rol['sueldo'] + $rol['ingresos_gravados'] + $rol['ingresos_excentos'] + $rol['tercero'] + $rol['cuarto'] + $rol['fondo_reserva'], 2); ?></b></td>
				<td class="text-left"><b>Total Egresos (B)</b></td>
				<td class="text-right"><b><?= formatMoney($rol['total_egresos'], 2); ?></b></td>
			</tr>
		</tbody>
	</table>
	<div class="text-left">
		<p>
			<b>LÍQUIDO A RECIBIR (A-B): <?= formatMoney($rol['a_recibir'], 2); ?> <?= "(" . strtolower(numero_letras($rol['a_recibir'])) . " dólares americanos)"; ?></b><br><br>
		</p>
	</div>
	<?php
	if ($detalles) {
	?>
		Detalle de novedades reportadas durante el mes.
		<table class="tbl-detalle">
			<thead>
				<tr>
					<th class="wd40 text-left">Novedad</th>
					<th class="wd40 text-left">Detalle</th>
					<th class="wd10 text-right">Valor</th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ($detalles as $detalle) {
				?>
					<tr>
						<?php foreach ($novedades as $novedad) {
							if ($detalle['id_novedad'] == $novedad['codigo']) {
								if ($detalle['id_novedad'] == 4 || $detalle['id_novedad'] == 5 || $detalle['id_novedad'] == 6) {
						?>
									<td class="wd40 text-left"><?= $detalle['valor']; ?> <?= ucwords($novedad['nombre']); ?></td>
								<?php
								} else {
								?>
									<td class="wd40 text-left"><?= ucwords($novedad['nombre']); ?></td>
						<?php
								}
							}
						}
						?>
						<td class="wd40 text-left"><?= ucwords($detalle['detalle']); ?></td>
						<?php
						if ($detalle['id_novedad'] == 4) {
						?>
							<td class="wd10 text-right"><?= formatMoney($detalle['valor'] * $calculo_hora_nocturna, 2); ?></td>
						<?php
						} else if ($detalle['id_novedad'] == 5) {
						?>
							<td class="wd10 text-right"><?= formatMoney($detalle['valor'] * $calculo_hora_suplementaria, 2); ?></td>
						<?php
						} else if ($detalle['id_novedad'] == 6) {
						?>
							<td class="wd10 text-right"><?= formatMoney($detalle['valor'] * $calculo_hora_extraordinaria, 2); ?></td>
						<?php
						} else {
						?>
							<td class="wd10 text-right"><?= formatMoney($detalle['valor'], 2); ?></td>
						<?php
						}
						?>
					</tr>
				<?php
				}
				?>
			</tbody>
		</table>
	<?php
	}
	?>
	<br>
	<div class="text-left">
		<p>
			Recibí conforme:_______________________________________<br><br>
			Realizado por: <?= ucwords($usuario['nombre']); ?><br><br>
			Aprobado por:__________________________________________<br>
		</p>
	</div>
</body>

</html>