<?php
$empresa = $data['empresa'];
$logo = $data['logo'];
$usuario = $data['usuario'];
$encabezado_retorno = $data['encabezado_retorno'];
$detalle_retorno = $data['detalle_retorno'];

?>
<!DOCTYPE html>
<html lang="es">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Retorno Consignación</title>
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

		.wd33 {
			width: 33.33%;
		}

		.wd40 {
			width: 40%;
		}

		.wd50 {
			width: 50%;
		}

		.wd100 {
			width: 100%;
		}

		.tbl-detalle {
			border-collapse: collapse;
		}

		.tbl-detalle thead th {
			padding: 5px;
			background-color: #cdcdcd;
			color: black;
		}

		.tbl-detalle tbody td {
			border-bottom: 1px solid black;
			padding: 3px;
			height: 15px;
		}
	</style>
</head>

<body>
	<table class="tbl-hader">
		<tbody>
			<tr>
				<td class="text-left wd33">
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
					<p>DETALLE DE RETORNO DE CONSIGNACIÓN<br>
						No. documento: 001-001-<?= $encabezado_retorno['numero_consignacion']; ?> <br>
						Fecha emisión: <?= date('d-m-Y', strtotime($encabezado_retorno['fecha_consignacion'])); ?> <br>
						Cliente/Receptor: <?= $encabezado_retorno['nombre']; ?></p>
				</td>
			</tr>
		</tbody>
	</table>
	<br>
	<table class="tbl-detalle">
		<thead>
			<tr>
				<th class="wd15 text-left">Código</th>
				<th class="wd50 text-left">Descripción</th>
				<th class="wd15 text-center">Lote</th>
				<th class="wd10 text-center">NUP</th>
				<th class="wd10 text-right">Cantidad</th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ($detalle_retorno as $detalle) {
			?>
				<tr>
					<td class="wd15 text-left"><?= strtoupper($detalle['codigo_producto']); ?></td>
					<td class="wd50 text-left"><?= strtoupper($detalle['nombre_producto']); ?></td>
					<td class="wd15 text-center"><?= strtoupper($detalle['lote']); ?></td>
					<td class="wd10 text-center"><?= $detalle['nup']; ?></td>
					<td class="wd10 text-right"><?= formatMoney($detalle['cant_consignacion'], 2); ?></td>
				</tr>
			<?php
			}
			?>
		</tbody>
	</table>
	<br>
	<table class="tbl-detalle">
		<thead>
			<tr>
				<th class="wd100 text-left">Observaciones</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td class="text-left"><?= strtoupper($encabezado_retorno['observaciones']); ?></td>
			</tr>
		</tbody>
	</table>
	<br>
	<br>
	<br>
	<table>
		<thead>
			<tr>
				<th class="wd50 text-center">-------------------------------------------</th>
				<th class="wd50 text-center">-------------------------------------------</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td class="wd50 text-center">REALIZADO POR</td>
				<td class="wd50 text-center">RECIBIDO POR</td>
			</tr>
		</tbody>
	</table>
</body>

</html>