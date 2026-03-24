<?php 
	$empresa = $data['empresa'];
	$quincena = $data['quincena'];
	$usuario = $data['usuario'];
	$mes_ano = $data['mes_ano'];
	$meses = $data['meses'];
	$logo = $data['logo'];
 ?>
<!DOCTYPE html>
<html lang="es">
<head> 
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Quincenas</title>
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
			<td class="text-left wd33">
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
					<h4><strong>Quincena <?php foreach($meses as $mes){ 
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
				<th class="wd5">No.</th>
				<th class="wd15">Cédula</th>
				<th class="wd35">Nombres y apellidos</th>
				<th class="wd10 text-right">Quincena</th>
				<th class="wd10 text-right">Adicional</th>
				<th class="wd10 text-right">Descuento</th>
				<th class="wd10 text-right">A_Recibir</th>
				<th class="wd10 text-right">Recibí conforme</th>
			</tr>
		</thead>
		<tbody>
			<?php 
			
				$subtotal_quincena = 0;
				$subtotal_adicional = 0;
				$subtotal_descuento = 0;
				$subtotal_arecibir = 0;
				$num=0;
				foreach ($quincena as $detalle) {
					$subtotal_quincena += $detalle['quincena'];
					$subtotal_adicional += $detalle['adicional'];
					$subtotal_descuento += $detalle['descuento'];
					$subtotal_arecibir += $detalle['arecibir'];
					$num ++;
			 ?>
			<tr>
				<td><?= $num; ?></td>
				<td><?= $detalle['cedula']; ?></td>
				<td><?= ucwords($detalle['empleado']); ?></td>
				<td class="text-right"><?= formatMoney($detalle['quincena'], 2); ?></td>
				<td class="text-right"><?= formatMoney($detalle['adicional'], 2); ?></td>
				<td class="text-right"><?= formatMoney($detalle['descuento'], 2); ?></td>
				<td class="text-right"><?= formatMoney($detalle['arecibir'], 2); ?></td>
				<td></td>
			</tr>
			<?php 
			}
			?>
			<tr>
				<td></td>
				<td></td>
				<td class="text-right">Totales: </td>
				<td class="text-right"><?= formatMoney($subtotal_quincena, 2); ?></td>
				<td class="text-right"><?= formatMoney($subtotal_adicional, 2); ?></td>
				<td class="text-right"><?= formatMoney($subtotal_descuento, 2); ?></td>
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