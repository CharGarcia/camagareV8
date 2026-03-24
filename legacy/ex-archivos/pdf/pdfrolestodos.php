<?php 
	$empresa = $data['empresa'];
	$rol = $data['rol'];
	$logo = $data['logo'];
	$usuario = $data['usuario'];
	$mes_ano = $data['mes_ano'];
	$meses = $data['meses'];
 ?>
<!DOCTYPE html>
<html lang="es">
<head> 
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Roles de pago</title>
	<style>
		table{
			width: 100%;
		}
		table td, table th{
			font-size: 14px;
		}
		h4{
			margin-bottom: 0px;
		}
		.text-center{
			text-align: center;
		}
		.text-right{
			text-align: right;
		}
		
		.tbl-cliente{
			border: 1px solid #CCC;
			border-radius: 10px;
			padding: 5px;
		}
		.wd5{
			width: 5%;
		}
		.wd10{
			width: 10%;
		}
		.wd30{
			width: 30%;
		}
		.wd35{
			width: 35%;
		}
		.wd40{
			width: 40%;
		}
		.wd55{
			width: 55%;
		}
		.tbl-detalle{
			border-collapse: collapse;
		}
		.tbl-detalle thead th{
			padding: 5px;
			background-color: #cdcdcd;
			color: #FFF;
		}
		.tbl-detalle tbody td{
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
			<td class="text-center wd10">
					<?php
					if(empty($logo['logo'])){
					?>
					logo
					<?php
						}else{
					?>
					<img src="../logos_empresas/<?php echo $logo['logo'];?>" alt="Logo" style="height:80px;">
					<?php
					}
					?>
				</td>
				<td class="text-center wd55">
					<h4><strong><?= $empresa['nombre'];?></strong></h4>
					<h4><strong>Rol de pagos <?php foreach($meses as $mes){ 
								if($mes['codigo']==substr($mes_ano,0,2)){
									echo $mes['nombre'].substr($mes_ano,2,5);
								}
							}; ?></strong></h4>
                    <p><?= $empresa['direccion'];?><br>
					RUC: <?= $empresa['ruc'];?> <br>
                    Teléfono: <?= $empresa['telefono'];?> <br>
					Email: <?= $empresa['mail'];?></p>
				</td>
			</tr>
		</tbody>
	</table>
	<br>
	<table class="tbl-detalle">
		<thead>
			<tr>
				<th class="wd5 text-center">No.</th>
				<th class="wd10 text-center">Cédula</th>
				<th class="wd30 text-left">Nombres y apellidos</th>
				<th class="wd10 text-right">Ingresos</th>
				<th class="wd10 text-right">Egresos</th>
				<th class="wd10 text-right">A_Recibir</th>
				<th class="wd10 text-right">Firma</th>
			</tr>
		</thead>
		<tbody>
			<?php 
				$subtotal_sueldo = 0;
				$subtotal_ingresos = 0;
				$subtotal_egresos = 0;
				$subtotal_adicionales = 0;
				$subtotal_recibir = 0;
				$num=0;
				foreach ($rol as $detalle) {
					$subtotal_sueldo += $detalle['sueldo'];
					$subtotal_ingresos += $detalle['ingresos_gravados']+$detalle['ingresos_excentos'];
					$subtotal_egresos += $detalle['total_egresos'];
					$subtotal_adicionales += $detalle['tercero'] + $detalle['cuarto'] + $detalle['fondo_reserva'];
					$subtotal_recibir += $detalle['arecibir'];
					$num ++;
			 ?>
			<tr>
				<td class="text-center"><?= $num; ?></td>
				<td class="text-center"><?= $detalle['cedula']; ?></td>
				<td class="text-left"><?= ucwords($detalle['empleado']); ?></td>
				<td class="text-right"><?= formatMoney($detalle['tercero'] + $detalle['cuarto'] + $detalle['fondo_reserva']+$detalle['sueldo']+$detalle['ingresos_gravados']+$detalle['ingresos_excentos'], 2); ?></td>
				<td class="text-right"><?= formatMoney($detalle['total_egresos'], 2); ?></td>
				<td class="text-right"><?= formatMoney($detalle['arecibir'], 2); ?></td>
				<td></td>
			</tr>
			<?php } ?>
			<tr>
			<td></td>
			<td></td>
				<td class="text-right">Totales: </td>
				<td class="text-right"><?= formatMoney($subtotal_adicionales+$subtotal_sueldo+$subtotal_ingresos, 2); ?></td>
				<td class="text-right"><?= formatMoney($subtotal_egresos, 2); ?></td>
				<td class="text-right"><?= formatMoney($subtotal_recibir, 2); ?></td>
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