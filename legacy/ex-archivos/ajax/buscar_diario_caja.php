<?php
include("../conexiones/conectalogin.php");
$con = conenta_login();
$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
	if($action == 'diario_caja'){
		$fecha_caja = $_REQUEST['fecha_caja'];
		echo busca_detalle_caja($fecha_caja, $con);	
	}
	
	function busca_detalle_caja($fecha_caja, $con){
	include("../core/db.php");
	$db = new db();
	
	session_start();
		$ruc_empresa = $_SESSION['ruc_empresa'];
		$id_usuario = $_SESSION['id_usuario'];		
			
	//eliminar registros que tienen cero en entradas y cero en salidas
	$query_elimina_registros = mysqli_query($con, "DELETE FROM detalle_diario_caja WHERE ruc_empresa='".$ruc_empresa."' and entradas= '0.00' and salidas='0.00'"); 
	//total ventas de ese dia
	$verifica_ventas= mysqli_query($con, "SELECT * FROM detalle_diario_caja WHERE ruc_empresa='".$ruc_empresa."' and fecha_diario_caja='".date('Y-m-d',strtotime($fecha_caja))."' and tipo_registro='VENTAS'");
	$total_registros=mysqli_num_rows($verifica_ventas);
	$row_registros=mysqli_fetch_array($verifica_ventas);
	$id_registro_venta=$row_registros['id_diario_caja'];
	if($total_registros==0){	
	$query_guarda_entrada = mysqli_query($con, "INSERT INTO detalle_diario_caja (id_diario_caja, ruc_empresa, fecha_diario_caja, fecha_registro, entradas, salidas, id_usuario, tipo_registro, detalle, codigo_forma_pago) 
	SELECT null,'".$ruc_empresa."', '".date('Y-m-d',strtotime($fecha_caja))."', '".date('Y-m-d')."', sum(fpv.valor_pago), '0', '".$id_usuario."', 'VENTAS','Ventas diarias','0' FROM formas_pago_ventas fpv INNER JOIN encabezado_factura enc ON enc.serie_factura=fpv.serie_factura and enc.secuencial_factura=fpv.secuencial_factura WHERE fpv.ruc_empresa='".$ruc_empresa."' and enc.ruc_empresa='".$ruc_empresa."' and enc.fecha_factura='".date('Y-m-d',strtotime($fecha_caja))."' ");
	}else{
	$sql_ventas_dia= mysqli_query($con, "SELECT sum(fpv.valor_pago) as total_ventas_dia FROM formas_pago_ventas fpv INNER JOIN encabezado_factura enc ON enc.serie_factura=fpv.serie_factura and enc.secuencial_factura=fpv.secuencial_factura WHERE fpv.ruc_empresa='".$ruc_empresa."' and enc.ruc_empresa='".$ruc_empresa."' and enc.fecha_factura='".date('Y-m-d',strtotime($fecha_caja))."' ");
	$row_ventas_dia=mysqli_fetch_array($sql_ventas_dia);
	$ventas_dia=$row_ventas_dia['total_ventas_dia'];	
	$sql_update = mysqli_query($con,"UPDATE detalle_diario_caja SET entradas='".$ventas_dia."' WHERE id_diario_caja='".$id_registro_venta."'");
	}
	
	$sql_entradas_salidas_dia= mysqli_query($con, "SELECT sum(entradas) as entradas, sum(salidas) as salidas FROM detalle_diario_caja WHERE ruc_empresa='".$ruc_empresa."' and fecha_diario_caja='".date('Y-m-d',strtotime($fecha_caja))."' ");
	$row_entradas_salidas_dia=mysqli_fetch_array($sql_entradas_salidas_dia);
	$entradas_dia=$row_entradas_salidas_dia['entradas'];
	$salidas_dia=$row_entradas_salidas_dia['salidas'];
	
	$sql_fecha_mas_antigua= mysqli_query($con, "SELECT min(fecha_diario_caja) as fecha_inicial FROM detalle_diario_caja WHERE ruc_empresa='".$ruc_empresa."' ");
	$row_fecha_mas_antigua=mysqli_fetch_array($sql_fecha_mas_antigua);
	
	$fecha_inicial=$db->var2str(date('Y-m-d H:i:s', strtotime($row_fecha_mas_antigua['fecha_inicial'])));
	$fecha_final=$db->var2str(date('Y-m-d H:i:s', strtotime($fecha_caja."- 1 days")));

	$sql_saldo_inicial= mysqli_query($con, "SELECT sum(entradas-salidas) as saldo_inicial FROM detalle_diario_caja WHERE fecha_diario_caja BETWEEN ".$fecha_inicial." and ".$fecha_final." and ruc_empresa='".$ruc_empresa."'");
	$row_saldo_inicial=mysqli_fetch_array($sql_saldo_inicial);
	$saldo_inicial=$row_saldo_inicial['saldo_inicial'];
	
	//para ver solo las ventas en efectivo
	$ventas_efectivo_manual= mysqli_query($con, "SELECT sum(entradas-salidas) as efectivo_manual FROM detalle_diario_caja WHERE ruc_empresa='".$ruc_empresa."' and fecha_diario_caja='".date('Y-m-d',strtotime($fecha_caja))."' and tipo_registro='MANUAL' and codigo_forma_pago = '01'");
	$row_ventas_efectivo_manual=mysqli_fetch_array($ventas_efectivo_manual);
	$total_ventas_efectivo_manual=$row_ventas_efectivo_manual['efectivo_manual'];
	
	
	$sql_ventas_efectivo= mysqli_query($con, "SELECT sum(fpv.valor_pago) as total_pago FROM formas_pago_ventas fpv INNER JOIN encabezado_factura enc ON enc.serie_factura=fpv.serie_factura and enc.secuencial_factura=fpv.secuencial_factura INNER JOIN formas_de_pago fp ON fp.codigo_pago=fpv.id_forma_pago and fp.aplica_a='VENTAS' WHERE fp.codigo_pago='01' and fpv.ruc_empresa='".$ruc_empresa."' and enc.ruc_empresa='".$ruc_empresa."' and enc.fecha_factura='".date('Y-m-d',strtotime($fecha_caja))."' ");//group by fpv.id_forma_pago
	$row_ventas_efectivo = mysqli_fetch_array($sql_ventas_efectivo);
	$total_ventas_efectivo= $row_ventas_efectivo['total_pago'] + $total_ventas_efectivo_manual;
	
	
	?>
	<input type="hidden" name="total_ventas_efectivo" id="total_ventas_efectivo" value="<?php echo $total_ventas_efectivo; ?>">
			<div class="panel panel-info">
				<div class="table-responsive">
					<table class="table table-bordered" >
						<tr  class="success">
							<th style="padding: 2px;" class="text-center">Saldo inicial</th>
							<th style="padding: 2px;" class="text-center">Entradas</th>
							<th style="padding: 2px;" class="text-center">Salidas</th>
							<th style="padding: 2px;" class="text-center">Saldo final</th>
						</tr>
							<input type="hidden" name="id_agregar" id="id_agregar" >
							<td class='col-xs-2 text-right'><?php echo number_format($saldo_inicial,2,'.','') ?></td>
							<td class='col-xs-2 text-right'><?php echo number_format($entradas_dia,2,'.','') ?></td>							
							<td class='col-xs-2 text-right'><?php echo number_format($salidas_dia,2,'.','') ?></td>
							<td class='col-xs-2 text-right'><?php echo number_format($saldo_inicial+$entradas_dia-$salidas_dia,2,'.','')?></td>							
					</table>
				</div>	
			</div>
			<?php

	//detalle de ventas
		$sql_ventas= mysqli_query($con, "SELECT fpv.id_forma_pago as id_pago, fp.nombre_pago as pago, sum(fpv.valor_pago) as total_pago FROM formas_pago_ventas fpv INNER JOIN encabezado_factura enc ON enc.serie_factura=fpv.serie_factura and enc.secuencial_factura=fpv.secuencial_factura INNER JOIN formas_de_pago fp ON fp.codigo_pago=fpv.id_forma_pago and fp.aplica_a='VENTAS' WHERE fpv.ruc_empresa='".$ruc_empresa."' and enc.ruc_empresa='".$ruc_empresa."' and enc.fecha_factura='".date('Y-m-d',strtotime($fecha_caja))."' group by fpv.id_forma_pago");
		?>		
		<div class="panel panel-default" style="height: 42%; margin-top: -10px;">
			 <div class="panel-heading">Detalle de Ventas</div>
			 
				<div class="table-responsive">
					<table class="table table-bordered" >
						<tr  class="success">
							<th style="padding: 2px;">Descripción</th>
							<th style="padding: 2px;" class='text-center'>Valor</th>
						</tr>
						<?php
						while ($row_ventas=mysqli_fetch_array($sql_ventas)){
							$total_ventas=$row_ventas['total_pago'];
							$forma_pago=$row_ventas['pago'];
							?>
							<tr>
							<td class='col-xs-10'><?php echo $forma_pago ?> </td>	
							<td class='col-xs-2 text-right'><?php echo number_format($total_ventas,2,'.','') ?> </td>
							</tr>
							<?php
						}
						?>													
					</table>
				</div>	
		</div>
		
		<div class="panel panel-default" style="height: 42%; margin-top: -10px;">
			<div class="panel-heading">
			 <div class="btn-group pull-right">
				<button type='button' onclick="tipo_registro('ENTRADA')" class="btn btn-sm btn-info" data-toggle="modal" data-target="#entradas_salidas_caja"><span class="glyphicon glyphicon-plus"></span> Agregar</button>
			</div>
			<h5>Otras entradas</h5>
			</div>
			 
				<div class="table-responsive">
					<table class="table table-bordered">
						<tr  class="success">
							<th style="padding: 2px;">Descripción</th>
							<th style="padding: 2px;">Forma de pago</th>
							<th style="padding: 2px;"  class='text-center'>Valor</th>
							<th style="padding: 2px;"  class='text-center'>Eliminar</th>
						</tr>
							<?php
								//para mostrar las entradas
							$mostrar_otras_entradas= mysqli_query($con, "SELECT * FROM detalle_diario_caja WHERE ruc_empresa='".$ruc_empresa."' and fecha_diario_caja='".date('Y-m-d',strtotime($fecha_caja))."' and tipo_registro='MANUAL' and entradas > 0");
							while ($row_registros_entradas = mysqli_fetch_array($mostrar_otras_entradas)){
								$id_entradas=$row_registros_entradas['id_diario_caja'];
								$valor_entradas=$row_registros_entradas['entradas'];
								$detalle=$row_registros_entradas['detalle'];
								$forma_pago_caja=$row_registros_entradas['codigo_forma_pago'];
								$mostrar_formas_de_pago= mysqli_query($con, "SELECT * FROM formas_de_pago WHERE codigo_pago='".$forma_pago_caja."' and aplica_a='VENTAS'");
								$row_formas_pagos = mysqli_fetch_array($mostrar_formas_de_pago);
								$nombre_pago_otros=$row_formas_pagos['nombre_pago'];
								?>
								<tr>
								<td class='col-xs-4'><?php echo $detalle ?></td>
								<td class='col-xs-4'><?php echo $nombre_pago_otros ?></td>
								<td class='col-xs-2'><?php echo $valor_entradas ?></td>							
								<td class='col-xs-2 text-right'><a href="#" class='btn btn-danger btn-xs' title='Eliminar' onclick="eliminar_registro('<?php echo $id_entradas; ?>')"><i class="glyphicon glyphicon-erase"></i> </a></td>
								</tr>
								<?php
								}
							?>						
					</table>
				</div>	
		</div>
		<div class="panel panel-default" style="height: 42%; margin-top: -10px;">
			 <div class="panel-heading">
			 <div class="btn-group pull-right">
				<button type='button' onclick="tipo_registro('SALIDA')" class="btn btn-sm btn-info" data-toggle="modal" data-target="#entradas_salidas_caja"><span class="glyphicon glyphicon-plus"></span> Agregar</button>
			</div>
			<h5>Detalle de salidas</h5>
			</div>
				<div class="table-responsive">
					<table class="table table-bordered" >
						<tr  class="success">
							<th style="padding: 2px;">Descripción</th>
							<th style="padding: 2px;">Forma de pago</th>
							<th style="padding: 2px;"  class='text-center'>Valor</th>
							<th style="padding: 2px;"  class='text-center'>Eliminar</th>
						</tr>
							<?php
								//para mostrar las salidas
							$mostrar_otras_entradas= mysqli_query($con, "SELECT * FROM detalle_diario_caja WHERE ruc_empresa='".$ruc_empresa."' and fecha_diario_caja='".date('Y-m-d',strtotime($fecha_caja))."' and tipo_registro='MANUAL' and salidas > 0");
							while ($row_registros_entradas = mysqli_fetch_array($mostrar_otras_entradas)){
								$id_entradas=$row_registros_entradas['id_diario_caja'];
								$valor_salidas=$row_registros_entradas['salidas'];
								$detalle=$row_registros_entradas['detalle'];
								$forma_pago_caja=$row_registros_entradas['codigo_forma_pago'];
								$mostrar_formas_de_pago= mysqli_query($con, "SELECT * FROM formas_de_pago WHERE codigo_pago='".$forma_pago_caja."' and aplica_a='VENTAS'");
								$row_formas_pagos = mysqli_fetch_array($mostrar_formas_de_pago);
								$nombre_pago_otros=$row_formas_pagos['nombre_pago'];
								?>
								<tr>
								<td class='col-xs-4'><?php echo $detalle ?></td>
								<td class='col-xs-4'><?php echo $nombre_pago_otros ?></td>
								<td class='col-xs-2'><?php echo $valor_salidas ?></td>							
								<td class='col-xs-2 text-right'><a href="#" class='btn btn-danger btn-xs' title='Eliminar' onclick="eliminar_registro('<?php echo $id_entradas; ?>')"><i class="glyphicon glyphicon-erase"></i> </a></td>
								</tr>
								<?php
								}
							?>						
					</table>
				</div>	
		</div>
				
		<div class="panel panel-default" style="height: 42%; margin-top: -10px;">
			 <div class="panel-heading">Detalle de efectivo</div>
			 <div class="row">
				<div class="col-md-6">
					<div class="table-responsive">
					<div id="resultados_ajax_efectivo"></div>
						<table class="table table-bordered" >
								<tr  class="success">
									<th style="padding: 2px;">Denominación</th>
									<th style="padding: 2px;"  class='text-center'>Cantidad</th>
									<th style="padding: 2px;"  class='text-center'>Valor</th>
								</tr>
								<tr>
								<td class='col-xs-2'>Billetes de $ 100</td>					
								<td class='col-xs-2'><input onkeyup="calcula_efectivo('billete','100');" type="text" class="form-control input-sm text-right" name="billeteCien" id="billeteCien" value="<?php echo detalle_efectivo('billete', '100', $fecha_caja); ?>"></td>
								<td class='col-xs-2'><input type="text" class="form-control input-sm text-right" name="resbilleteCien" id="resbilleteCien" value="<?php echo number_format(detalle_efectivo('billete', '100', $fecha_caja)*100,2,'.',''); ?>" readonly></td>
								</tr>
								<tr>
								<td class='col-xs-2'>Billetes de $ 50</td>					
								<td class='col-xs-2'><input onkeyup="calcula_efectivo('billete','50');" type="text" class="form-control input-sm text-right" name="billeteCincuenta" id="billeteCincuenta" value="<?php echo detalle_efectivo('billete', '50', $fecha_caja); ?>"></td>
								<td class='col-xs-2'><input type="text" class="form-control input-sm text-right" name="resbilleteCincuenta" id="resbilleteCincuenta" value="<?php echo number_format(detalle_efectivo('billete', '50', $fecha_caja)*50,2,'.',''); ?>" readonly></td>
								</tr>
								<tr>
								<td class='col-xs-2'>Billetes de $ 20</td>					
								<td class='col-xs-2'><input onkeyup="calcula_efectivo('billete','20');" type="text" class="form-control input-sm text-right" name="billeteVeinte" id="billeteVeinte" value="<?php echo detalle_efectivo('billete', '20', $fecha_caja); ?>"></td>
								<td class='col-xs-2'><input type="text" class="form-control input-sm text-right" name="resbilleteVeinte" id="resbilleteVeinte" value="<?php echo number_format(detalle_efectivo('billete', '20', $fecha_caja)*20,2,'.',''); ?>" readonly></td>
								</tr>
								<tr>
								<td class='col-xs-2'>Billetes de $ 10</td>					
								<td class='col-xs-2'><input onkeyup="calcula_efectivo('billete','10');" type="text" class="form-control input-sm text-right" name="billeteDiez" id="billeteDiez" value="<?php echo detalle_efectivo('billete', '10', $fecha_caja); ?>"></td>
								<td class='col-xs-2'><input type="text" class="form-control input-sm text-right" name="resbilleteDiez" id="resbilleteDiez" value="<?php echo number_format(detalle_efectivo('billete', '10', $fecha_caja)*10,2,'.',''); ?>" readonly></td>
								</tr>
								<tr>
								<td class='col-xs-2'>Billetes de $ 5</td>					
								<td class='col-xs-2'><input onkeyup="calcula_efectivo('billete','5');" type="text" class="form-control input-sm text-right" name="billeteCinco" id="billeteCinco" value="<?php echo detalle_efectivo('billete', '5', $fecha_caja); ?>"></td>
								<td class='col-xs-2'><input type="text" class="form-control input-sm text-right" name="resbilleteCinco" id="resbilleteCinco" value="<?php echo number_format(detalle_efectivo('billete', '5', $fecha_caja)*5,2,'.',''); ?>" readonly></td>
								</tr>
								<tr>
								<td class='col-xs-2'>Billetes de $ 2</td>					
								<td class='col-xs-2'><input onkeyup="calcula_efectivo('billete','2');" type="text" class="form-control input-sm text-right" name="billeteDos" id="billeteDos" value="<?php echo detalle_efectivo('billete', '2', $fecha_caja); ?>"></td>
								<td class='col-xs-2'><input type="text" class="form-control input-sm text-right" name="resbilleteDos" id="resbilleteDos" value="<?php echo number_format(detalle_efectivo('billete', '2', $fecha_caja)*2,2,'.',''); ?>" readonly></td>
								</tr>
								<tr>
								<td class='col-xs-2'>Billetes de $ 1</td>					
								<td class='col-xs-2'><input onkeyup="calcula_efectivo('billete','1');" type="text" class="form-control input-sm text-right" name="billeteUno" id="billeteUno" value="<?php echo detalle_efectivo('billete', '1', $fecha_caja); ?>"></td>
								<td class='col-xs-2'><input type="text" class="form-control input-sm text-right" name="resbilleteUno" id="resbilleteUno" value="<?php echo number_format(detalle_efectivo('billete', '1', $fecha_caja)*1,2,'.',''); ?>" readonly></td>
								</tr>
								
						</table>
					</div>
				</div>
				<div class="col-md-6">
					<div class="table-responsive">
						<table class="table table-bordered" >
								<tr  class="success">
									<th style="padding: 2px;">Denominación</th>
									<th style="padding: 2px;"  class='text-center'>Cantidad</th>
									<th style="padding: 2px;"  class='text-center'>Valor</th>
								</tr>
								<tr>
								<td class='col-xs-2'>Monedas de $ 1</td>					
								<td class='col-xs-2'><input onkeyup="calcula_efectivo('moneda','100');" type="text" class="form-control input-sm text-right" name="monedaCien" id="monedaCien" value="<?php echo detalle_efectivo('moneda', '100', $fecha_caja); ?>"></td>
								<td class='col-xs-2'><input type="text" class="form-control input-sm text-right" name="resmonedaCien" id="resmonedaCien" value="<?php echo number_format(detalle_efectivo('moneda', '100', $fecha_caja)*1,2,'.',''); ?>" readonly></td>
								</tr>
								<tr>
								<td class='col-xs-2'>Monedas de $ 50 ctvs</td>					
								<td class='col-xs-2'><input onkeyup="calcula_efectivo('moneda','50');" type="text" class="form-control input-sm text-right" name="monedaCincuenta" id="monedaCincuenta" value="<?php echo detalle_efectivo('moneda', '50', $fecha_caja); ?>"></td>
								<td class='col-xs-2'><input type="text" class="form-control input-sm text-right" name="resmonedaCincuenta" id="resmonedaCincuenta" value="<?php echo number_format(detalle_efectivo('moneda', '50', $fecha_caja)*0.50,2,'.',''); ?>" readonly></td>
								</tr>
								<tr>
								<td class='col-xs-2'>Monedas de $ 25 ctvs</td>					
								<td class='col-xs-2'><input onkeyup="calcula_efectivo('moneda','25');" type="text" class="form-control input-sm text-right" name="monedaVeinticinco" id="monedaVeinticinco" value="<?php echo detalle_efectivo('moneda', '25', $fecha_caja); ?>"></td>
								<td class='col-xs-2'><input type="text" class="form-control input-sm text-right" name="resmonedaVeinticinco" id="resmonedaVeinticinco" value="<?php echo number_format(detalle_efectivo('moneda', '25', $fecha_caja)*0.25,2,'.',''); ?>" readonly></td>
								</tr>
								<tr>
								<td class='col-xs-2'>Monedas de $ 10 ctvs</td>					
								<td class='col-xs-2'><input onkeyup="calcula_efectivo('moneda','10');" type="text" class="form-control input-sm text-right" name="monedaDiez" id="monedaDiez" value="<?php echo detalle_efectivo('moneda', '10', $fecha_caja); ?>"></td>
								<td class='col-xs-2'><input type="text" class="form-control input-sm text-right" name="resmonedaDiez" id="resmonedaDiez" value="<?php echo number_format(detalle_efectivo('moneda', '10', $fecha_caja)*0.10,2,'.',''); ?>" readonly></td>
								</tr>
								<tr>
								<td class='col-xs-2'>Monedas de $ 5 ctvs</td>					
								<td class='col-xs-2'><input onkeyup="calcula_efectivo('moneda','5');" type="text" class="form-control input-sm text-right" name="monedaCinco" id="monedaCinco" value="<?php echo detalle_efectivo('moneda', '5', $fecha_caja); ?>"></td>
								<td class='col-xs-2'><input type="text" class="form-control input-sm text-right" name="resmonedaCinco" id="resmonedaCinco" value="<?php echo number_format(detalle_efectivo('moneda', '5', $fecha_caja)*0.05,2,'.',''); ?>" readonly></td>
								</tr>
								<tr>
								<td class='col-xs-2'>Monedas de $ 1 ctv</td>					
								<td class='col-xs-2'><input onkeyup="calcula_efectivo('moneda','1');" type="text" class="form-control input-sm text-right" name="monedaUno" id="monedaUno" value="<?php echo detalle_efectivo('moneda', '1', $fecha_caja); ?>"></td>
								<td class='col-xs-2'><input type="text" class="form-control input-sm text-right" name="resmonedaUno" id="resmonedaUno" value="<?php echo number_format(detalle_efectivo('moneda', '1', $fecha_caja)*0.01,2,'.',''); ?>" readonly></td>
								</tr>
								<tr>
								<td class='col-xs-2 text-center'><button type="submit" class="btn btn-success" id="guardar" ><span class="glyphicon glyphicon-floppy-disk" ></span> Guardar</button></td>
								<td class='col-xs-2 text-right'><b>Total efectivo</b></td>					
								<td class='col-xs-2'><input type="text" class="form-control input-sm text-right" name="total_efectivo" id="total_efectivo" readonly></td>
								</tr>
								
						</table>
					</div>
				</div>
			</div>			
		</div>
	<?php
	$db->close();
	mysqli_close($con);
	}
		
	
	function detalle_efectivo($denominacion, $valor_denominacion, $fecha_detalle){
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$con = conenta_login();
	$sql_detalle_efectivo= mysqli_query($con, "SELECT * FROM detalle_efectivo WHERE fecha_detalle='".date('Y-m-d',strtotime($fecha_detalle))."' and ruc_empresa='".$ruc_empresa."' and denominacion='".$denominacion."' and valor_denominacion='".$valor_denominacion."'");
	$row_detalle_efectivo=mysqli_fetch_array($sql_detalle_efectivo);
	$cantidad =$row_detalle_efectivo['cantidad'];
	return $cantidad;
	mysqli_close($con);
	}
?>
