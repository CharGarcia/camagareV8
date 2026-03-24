<?php 
	$empresa = $data['empresa'];
	$logo = $data['logo'];
	$quincena = $data['quincena'];
	$usuario = $data['usuario'];
	$detalles = $data['detalle'];
	$meses = $data['meses'];
	$novedades = $data['novedades'];
	$calculos_salarios = $data['salario'];
	$incremento_hora_nocturna=1+$calculos_salarios['hora_nocturna']/100;
    $incremento_hora_suplementaria=1+$calculos_salarios['hora_suplementaria']/100;
    $incremento_hora_extraordinaria=1+$calculos_salarios['hora_extraordinaria']/100;
	$hora_normal=number_format($quincena['sueldo']/$calculos_salarios['hora_normal'], 2, '.', '');
	$calculo_hora_nocturna=number_format($hora_normal*$incremento_hora_nocturna, 2, '.', '');
	$calculo_hora_suplementaria=number_format($hora_normal*$incremento_hora_suplementaria, 2, '.', '');
	$calculo_hora_extraordinaria=number_format($hora_normal*$incremento_hora_extraordinaria, 2, '.', '');
?>
<!DOCTYPE html>
<html lang="es">
<head> 
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Quincena</title>
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
		.wd10{
			width: 10%;
		}
		.wd15{
			width: 15%;
		}
		.wd33{
			width: 33.33%;
		}
		.wd40{
			width: 40%;
		}
		.wd50{
			width: 50%;
		}
		.wd80{
			width: 80%;
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
				<td class="text-center wd50">
					<h4><strong><?= $empresa['nombre'];?></strong></h4>
                    <p><?= $empresa['direccion'];?><br>
					RUC: <?= $empresa['ruc'];?> <br>
                    Teléfono: <?= $empresa['telefono'];?> <br>
					Email: <?= $empresa['mail'];?></p>
				</td>
			</tr>
		</tbody>
	</table>
	<br>
	<table class="tbl-hader">
		<tbody>
			<tr>
				<td class="text-left wd80">
					<h3><strong>Quincena <?php 
					foreach($meses as $mes){
						if($mes['codigo']==substr($quincena['mes_ano'],0,2)){
						echo $mes['nombre'].substr($quincena['mes_ano'],2,5);
						}
					}
						?></strong></h3>
					<p>Nombres y apellidos: <strong><?= ucwords($quincena['empleado']); ?></strong><br>
					Cédula: <?= $quincena['cedula']; ?>
					Fecha Registro: <?= date("d-m-Y", strtotime($quincena['fecha'])) ?>
				</p>
				</td>
			</tr>
		</tbody>
	</table>
	<br>
	<table class="tbl-detalle">
		<thead>
			<tr>
				<th class="wd50 text-left">Novedad</th>
				<th class="wd40 text-left">Detalle</th>
				<th class="wd10 text-right">Valor</th>
			</tr>
		</thead>
		<tbody>
				<?php
				foreach ($detalles as $detalle){
				?>
				<tr>
					<?php foreach ($novedades as $novedad){
						if($detalle['id_novedad']==$novedad['codigo']){
								if ($detalle['id_novedad']==4 || $detalle['id_novedad']==5 || $detalle['id_novedad']==6 ){	
							?>
							<td class="text-left"><?= $detalle['valor']; ?> <?= ucwords($novedad['nombre']); ?></td>
							<?php
							}else{
								?>
							<td class="text-left"><?= ucwords($novedad['nombre']); ?></td>
							<?php
							}
						}
				}
				?>
				<td class="text-left"><?= ucwords($detalle['detalle']); ?></td>
				<?php
				if ($detalle['id_novedad']==4 ){
				?>
				<td class="text-right"><?= formatMoney($detalle['valor'] * $calculo_hora_nocturna, 2); ?></td>
				<?php
				}else if ($detalle['id_novedad']==5){
				?>
				<td class="text-right"><?= formatMoney($detalle['valor'] * $calculo_hora_suplementaria, 2); ?></td>
				<?php
				}else if ($detalle['id_novedad']==6){
					?>
				<td class="text-right"><?= formatMoney($detalle['valor'] * $calculo_hora_extraordinaria, 2); ?></td>
				<?php
				}else{
				?>
				<td class="text-right"><?= formatMoney($detalle['valor'], 2); ?></td>
				<?php
				}
				?>
				</tr>
				<?php
				}
				?>			
		</tbody>
	</table>
	<br>
	<div class="text-left">
		<p>Quincena (+): <?= formatMoney($quincena['quincena'], 2); ?><br>
		Adicionales (+): <?= formatMoney($quincena['adicional'], 2); ?><br>
		Descuentos (-): <?= formatMoney($quincena['descuento'], 2); ?><br>
		Total a recibir (=): <?= formatMoney($quincena['quincena']+$quincena['adicional']-$quincena['descuento'], 2); ?> <?= numero_letras($quincena['arecibir']); ?><br><br>
		Recibí conforme:_______________________________________<br><br>
		Realizado por: <?= ucwords($usuario['nombre']); ?><br><br>
		Aprobado por:__________________________________________<br>
		</p>
	</div>
</body>
</html>