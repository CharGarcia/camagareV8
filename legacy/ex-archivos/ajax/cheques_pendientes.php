<?PHP
	include("../conexiones/conectalogin.php");
	session_start();
$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';

if ($action == 'cheques_pendientes'){
	ini_set('date.timezone','America/Guayaquil');
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$cuenta = $_POST['cuenta'];
	$fecha_hasta = $_POST['fecha_hasta'];
	$con = conenta_login();
	?>
		<div class="panel-group" id="accordiones">
			<div class="panel panel-info">
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordiones" href="#detalleCheques"><span class="caret"></span> <b> Detalle de cheques por cobrar</b> </a>
			  <div id="detalleCheques" >
			  <div class="table-responsive">
			  <table class="table table-hover">
			  <tr  class="info">
					<th style ="padding: 2px;">Fecha emisión</th>
					<th style ="padding: 2px;">Fecha en cheque</th>
					<th style ="padding: 2px;">Estado</th>
					<th style ="padding: 2px;">Cheque</th>
					<th style ="padding: 2px;">Nombre</th>
					<th style ="padding: 2px;">Valor</th>
				</tr>
			<?php
		$suma=0;
		 $sql_cheques_cobrados = cheques_pendientes_cobrados($con, $cuenta, $ruc_empresa, $fecha_hasta);
		while ($row_cheques=mysqli_fetch_array($sql_cheques_cobrados)){
			$fecha_emision=$row_cheques['fecha_emision'];
			$fecha_entrega="Pendiente de cobro a la fecha";
			$fecha_pago=$row_cheques['fecha_pago'];
			$nombre_egreso =  $row_cheques['nombre'];
			$numero_cheque=$row_cheques['cheque'];
			$valor=$row_cheques['valor'];
			$suma += $row_cheques['valor'];
			?>
			<tr>	
				<td style ="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha_emision));?></td>
				<td style ="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha_pago));?></td>
				<td style ="padding: 2px;"><?php echo $fecha_entrega;?></td>
				<td style ="padding: 2px;"><?php echo $numero_cheque;?></td>
				<td style ="padding: 2px;"><?php echo $nombre_egreso;?></td>
				<td style ="padding: 2px;"><?php echo number_format($valor,2,'.','');?></td>
			</tr>
			<?php
			}
					
		$sql_cheques_por_cobrar = cheques_pendientes_estado_entregar($con, $cuenta, $ruc_empresa, $fecha_hasta);
		$suma_porcobrar=0;
		while ($row_cheques=mysqli_fetch_array($sql_cheques_por_cobrar)){
			$fecha_emision=$row_cheques['fecha_emision'];
			$fecha_entrega="En oficina por entregar";
			$fecha_pago=$row_cheques['fecha_pago'];
			$nombre_egreso =  $row_cheques['nombre'];
			$numero_cheque=$row_cheques['cheque'];
			$valor=$row_cheques['valor'];
			$suma_porcobrar += $row_cheques['valor'];
			?>
			<tr>	
				<td style ="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha_emision));?></td>
				<td style ="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha_pago));?></td>
				<td style ="padding: 2px;"><?php echo $fecha_entrega;?></td>
				<td style ="padding: 2px;"><?php echo $numero_cheque;?></td>
				<td style ="padding: 2px;"><?php echo $nombre_egreso;?></td>
				<td style ="padding: 2px;"><?php echo number_format($valor,2,'.','');?></td>
			</tr>
			<?php
			}
			
		$sql_cheques_por_cobrar = cheques_pendientes_estado_por_cobrar($con, $cuenta, $ruc_empresa, $fecha_hasta);
		$suma_estadoporcobrar=0;
		while ($row_cheques=mysqli_fetch_array($sql_cheques_por_cobrar)){
			$fecha_emision=$row_cheques['fecha_emision'];
			$fecha_entrega="Por cobrar";
			$fecha_pago=$row_cheques['fecha_pago'];
			$nombre_egreso =  $row_cheques['nombre'];
			$numero_cheque=$row_cheques['cheque'];
			$valor=$row_cheques['valor'];
			$suma_estadoporcobrar += $row_cheques['valor'];
			?>
			<tr>	
				<td style ="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha_emision));?></td>
				<td style ="padding: 2px;"><?php echo date("d-m-Y", strtotime($fecha_pago));?></td>
				<td style ="padding: 2px;"><?php echo $fecha_entrega;?></td>
				<td style ="padding: 2px;"><?php echo $numero_cheque;?></td>
				<td style ="padding: 2px;"><?php echo $nombre_egreso;?></td>
				<td style ="padding: 2px;"><?php echo number_format($valor,2,'.','');?></td>
			</tr>
			<?php
			}
			?>

			<tr class="info">
			<td class="text-right" colspan="5" style ="padding: 2px;">Total cheques posfechados: </td>
			<td style ="padding: 2px;"><?php echo number_format($suma+$suma_porcobrar+$suma_estadoporcobrar,2,'.','');?></td>
			</tr>
			</table>
			</div>
			</div>
			</div>
			
		</div>
	<?php
}

//cheques cobrados a la fecha de sacar el reporte pero pendientes segun la fecha que se ingresa para el reporte
function cheques_pendientes_cobrados($con, $cuenta, $ruc_empresa, $fecha_hasta){
	$sql_cheques_pagados = mysqli_query($con, "SELECT pago.cheque as cheque, pago.fecha_emision as fecha_emision, 
	pago.fecha_pago as fecha_pago, pago.fecha_entrega as fecha_entrega, round(pago.valor_forma_pago,2) as valor, 
	ing.nombre_ing_egr as nombre FROM formas_pagos_ing_egr as pago INNER JOIN ingresos_egresos as ing ON ing.codigo_documento=pago.codigo_documento 
	WHERE pago.id_cuenta='".$cuenta."' and ing.ruc_empresa ='".$ruc_empresa."' 
	and pago.ruc_empresa ='".$ruc_empresa."'
	and pago.tipo_documento='EGRESO' and pago.detalle_pago = 'C' and pago.valor_forma_pago > 0
	and DATE_FORMAT(pago.fecha_entrega, '%Y/%m/%d') > '".date("Y/m/d", strtotime($fecha_hasta))."'
	and DATE_FORMAT(pago.fecha_emision, '%Y/%m/%d') <= '".date("Y/m/d", strtotime($fecha_hasta))."'
	and pago.estado='OK' order by pago.fecha_pago asc");
	return $sql_cheques_pagados;
}

function cheques_pendientes_estado_entregar($con, $cuenta, $ruc_empresa, $fecha_hasta){
	$sql_cheques_pagados = mysqli_query($con, "SELECT pago.estado_pago as estado_pago, pago.cheque as cheque, pago.fecha_emision as fecha_emision, 
	pago.fecha_pago as fecha_pago, pago.fecha_entrega as fecha_entrega, round(pago.valor_forma_pago,2) as valor, 
	ing.nombre_ing_egr as nombre FROM formas_pagos_ing_egr as pago INNER JOIN ingresos_egresos as ing ON ing.codigo_documento=pago.codigo_documento 
	WHERE pago.id_cuenta='".$cuenta."' and ing.ruc_empresa ='".$ruc_empresa."' 
	and pago.ruc_empresa ='".$ruc_empresa."'
	and pago.tipo_documento='EGRESO' and pago.detalle_pago = 'C' and pago.valor_forma_pago > 0
	and pago.estado_pago = 'ENTREGAR'
	and DATE_FORMAT(pago.fecha_emision, '%Y/%m/%d') <= '".date("Y/m/d", strtotime($fecha_hasta))."'
	and pago.estado='OK' order by pago.fecha_pago asc");
	return $sql_cheques_pagados;
}

function cheques_pendientes_estado_por_cobrar($con, $cuenta, $ruc_empresa, $fecha_hasta){
	$sql_cheques_pagados = mysqli_query($con, "SELECT pago.estado_pago as estado_pago, pago.cheque as cheque, pago.fecha_emision as fecha_emision, 
	pago.fecha_pago as fecha_pago, pago.fecha_entrega as fecha_entrega, round(pago.valor_forma_pago,2) as valor, 
	ing.nombre_ing_egr as nombre FROM formas_pagos_ing_egr as pago INNER JOIN ingresos_egresos as ing ON ing.codigo_documento=pago.codigo_documento WHERE pago.id_cuenta='".$cuenta."' and ing.ruc_empresa ='".$ruc_empresa."' 
	and pago.ruc_empresa ='".$ruc_empresa."'
	and pago.tipo_documento='EGRESO' and pago.detalle_pago = 'C' and pago.valor_forma_pago > 0
	and pago.estado_pago = 'POR COBRAR'
	and DATE_FORMAT(pago.fecha_emision, '%Y/%m/%d') <= '".date("Y/m/d", strtotime($fecha_hasta))."'
	and pago.estado='OK' order by pago.fecha_pago asc");
	return $sql_cheques_pagados;
}

?>