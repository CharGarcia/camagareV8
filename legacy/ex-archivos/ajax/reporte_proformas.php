<?php
	/* Connect To Database*/
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
	session_start();
	$ruc_empresa = $_SESSION['ruc_empresa'];

//PARA BUSCAR LAS proformas	
$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
$tipo_reporte=$_POST['action'];
$id_cliente=$_POST['id_cliente'];
$id_producto=$_POST['id_producto'];
$desde=$_POST['desde'];
$hasta=$_POST['hasta'];
$id_marca=$_POST['id_marca'];
ini_set('date.timezone','America/Guayaquil');

if($action == '1'){
if (empty($id_cliente)){
$condicion_cliente="";
}else{
$condicion_cliente=" and enc_pro.id_cliente=".$id_cliente;	
}

if (empty($id_producto)){
$condicion_producto="";
}else{
$condicion_producto=" and cue_pro.id_producto=".$id_producto;	
}

if (empty($id_marca)){
$condicion_marca="";
}else{
$condicion_marca=" and mar_pro.id_marca=".$id_marca;	
}
			?>	
		<div class="panel panel-info">
			<div class="table-responsive">
			  <table class="table table-hover">
				<tr  class="info">
					<th>#</th>
					<th>Fecha</th>
					<th>Cliente</th>
					<th>Ruc</th>
					<th>Proforma</th>
					<th>Base 0</th>
					<th>Base 12</th>
					<th>Base No iva</th>
					<th>Base Exento</th>
					<th>Descuento</th>
					<th>Iva 12</th>
					<th>Total</th>
					<th>Usuario</th>
				</tr>
				<?php
				$suma_proforma=0;
				$suma_base_cero=0;
				$suma_base_doce=0;
				$suma_base_noimp=0;
				$suma_base_exento=0;
				$suma_base_descuento=0;
				$n=0;
				$resultado = mysqli_query($con, "SELECT * FROM cuerpo_proforma as cue_pro INNER JOIN encabezado_proforma as enc_pro ON enc_pro.codigo_unico=cue_pro.codigo_unico LEFT JOIN clientes as cli ON cli.id=enc_pro.id_cliente LEFT JOIN productos_servicios as pro_ser ON pro_ser.id=cue_pro.id_producto LEFT JOIN marca_producto as mar_pro ON mar_pro.id_producto=cue_pro.id_producto WHERE enc_pro.ruc_empresa='".$ruc_empresa."' and cue_pro.ruc_empresa='".$ruc_empresa."' and DATE_FORMAT(enc_pro.fecha_proforma, '%Y/%m/%d') between '".date("Y/m/d", strtotime($desde))."' and '".date("Y/m/d", strtotime($hasta))."' $condicion_cliente $condicion_producto $condicion_marca group by cue_pro.codigo_unico");
			
				while ($row=mysqli_fetch_array($resultado)){
						$suma_proforma+= $row['total_proforma'];
						$id_encabezado_proforma=$row['id_encabezado_proforma'];
						$codigo_unico=$row['codigo_unico'];
						$fecha_proforma=$row['fecha_proforma'];
						$serie_proforma=$row['serie_proforma'];
						$secuencial_proforma=$row['secuencial_proforma'];
						$nombre_cliente_proforma=$row['nombre'];
						$total_proforma=$row['total_proforma'];
						$ruc_cliente=$row['ruc'];
						$n++;
					
					?>
					<tr>
						<td><?php echo $n; ?></td>
						<td><?php echo date("d/m/Y", strtotime($fecha_proforma)); ?></td>
						<td><?php echo $nombre_cliente_proforma; ?></td>
						<td><?php echo $ruc_cliente; ?></td>
						<td><?php echo $serie_proforma; ?>-<?php echo str_pad($secuencial_proforma,9,"000000000",STR_PAD_LEFT); ?></td>
					
					<?php
					//para sacar el detalle de base cero
					$sql_cero = "SELECT sum(subtotal) as subtotal FROM cuerpo_proforma where tarifa_iva = 0 and codigo_unico = '".$codigo_unico."' and ruc_empresa= '".$ruc_empresa."'";      
					$resultado_subtotales = mysqli_query($con,$sql_cero);
					$subtotales=mysqli_fetch_array($resultado_subtotales);
					$base_cero= $subtotales['subtotal'];				
					?>
						<td><?php echo number_format($base_cero,2,'.',''); ?></td>
					<?php
					//para sacar el detalle de base doce
					$sql_doce = "SELECT sum(subtotal) as subtotal FROM cuerpo_proforma where tarifa_iva = 2 and codigo_unico = '".$codigo_unico."' and ruc_empresa= '".$ruc_empresa."'";      
					$resultado_subtotales = mysqli_query($con,$sql_doce);
					$subtotales=mysqli_fetch_array($resultado_subtotales);
					$base_doce= $subtotales['subtotal'];
					?>
						<td><?php echo number_format($base_doce,2,'.',''); ?></td>
					<?php
					//para sacar el detalle de base no obj imp
					$sql_no = "SELECT sum(subtotal) as subtotal FROM cuerpo_proforma where tarifa_iva = 6 and codigo_unico = '".$codigo_unico."' and ruc_empresa= '".$ruc_empresa."'";      
					$resultado_subtotales = mysqli_query($con,$sql_no);
					$subtotales=mysqli_fetch_array($resultado_subtotales);
					$base_noimp= $subtotales['subtotal'];
					
					?>
						<td><?php echo number_format($base_noimp,2,'.',''); ?></td>
					<?php
					//para sacar el detalle de base exento
					$sql_detalle = "SELECT sum(subtotal) as subtotal FROM cuerpo_proforma where tarifa_iva = 7 and codigo_unico = '".$codigo_unico."' and ruc_empresa= '".$ruc_empresa."'";      
					$resultado_subtotales = mysqli_query($con,$sql_detalle);
					$subtotales=mysqli_fetch_array($resultado_subtotales);
					$base_exento= $subtotales['subtotal'];
					
					//para sacar el detalle de descuento
					$sql_descuento = "SELECT sum(descuento) as descuento FROM cuerpo_proforma where codigo_unico = '".$codigo_unico."' and ruc_empresa= '".$ruc_empresa."'";      
					$resultado_subtotales = mysqli_query($con,$sql_descuento);
					$subtotales=mysqli_fetch_array($resultado_subtotales);
					$base_descuento= $subtotales['descuento'];
					?>
						<td><?php echo number_format($base_exento,2,'.',''); ?></td>
						<td><?php echo number_format($base_descuento,2,'.',''); ?></td>
						<td><?php echo number_format($base_doce * 0.12,2,'.',''); ?></td>
						<td><?php echo number_format($total_proforma,2,'.',''); ?></td>
						
					<?php
					$sql_usuario = "SELECT usu.nombre as nombre_usuario FROM usuarios usu, encabezado_proforma enc where usu.id=enc.id_usuario and enc.id_encabezado_proforma='".$id_encabezado_proforma."'";      
					$resultado_usuario = mysqli_query($con,$sql_usuario);
					$usuario_nombres= mysqli_fetch_array($resultado_usuario);
					$nombre_usuario = $usuario_nombres['nombre_usuario'];
					?>
					<td><?php echo substr($nombre_usuario, 0,6);?></td>
					</tr>
					<?php
					$suma_base_cero+= $base_cero;
					$suma_base_doce+= $base_doce;
					$suma_base_noimp+= $base_noimp;
					$suma_base_exento+= $base_exento;
					$suma_base_descuento+= $base_descuento;
					
				}
				?>	
					<tr  class="info">
					<th colspan="4">Totales</th>
					<td><span id="loader_excel"></span></td>
						<td><?php echo number_format($suma_base_cero,2,'.',''); ?></td>
						<td><?php echo number_format($suma_base_doce,2,'.',''); ?></td>
						<td><?php echo number_format($suma_base_noimp,2,'.',''); ?></td>
						<td><?php echo number_format($suma_base_exento,2,'.',''); ?></td>
						<td><?php echo number_format($suma_base_descuento,2,'.',''); ?></td>
						<td><?php echo number_format($suma_base_doce *0.12,2,'.',''); ?></td>
						<td><?php echo number_format($suma_proforma,2,'.',''); ?></td>
						<td></td>
					</tr>
										
				</table>
				</div>
			</div>
		<?php
}

// para buscar las proformas en detalle
if($action == '2'){
	if (empty($id_cliente)){
$condicion_cliente="";
}else{
$condicion_cliente=" and enc_pro.id_cliente=".$id_cliente;	
}

if (empty($id_producto)){
$condicion_producto="";
}else{
$condicion_producto=" and cue_pro.id_producto=".$id_producto;	
}

if (empty($id_marca)){
$condicion_marca="";
}else{
$condicion_marca=" and mar_pro.id_marca=".$id_marca;	
}
			?>	
		<div class="panel panel-info">
			<div class="table-responsive">
			  <table class="table table-hover">
						<tr class="info">
							<td>#</td>
							<td>Fecha</td>
							<td>Cliente</td>
							<td>Proforma</td>
							<td>Código</td>
							<td>Detalle</td>
							<td>Tipo</td>
							<td>Tarifa</td>
							<td>Cantidad</td>
							<td>Valor Uni.</td>
							<td>Subtotal</td>
							<td>Descuento</td>
							<td>IVA 12%</td>
							<td>Total</td>
						</tr>
				<?php
				$n=0;
				
				$resultado = mysqli_query($con, "SELECT enc_pro.fecha_proforma as fecha_proforma, 
				enc_pro.serie_proforma as serie_proforma, cue_pro.secuencial_proforma as secuencial_proforma,
				cli.nombre as nombre_cliente, enc_pro.total_proforma as total_proforma, 
				cli.ruc as ruc, cue_pro.cantidad as cantidad_proforma,
				cue_pro.nombre_producto as nombre_producto, cue_pro.codigo_producto as codigo_producto,
				cue_pro.valor_unitario as valor_unitario_proforma,cue_pro.descuento as descuento, 
				cue_pro.subtotal as subtotal_proforma,
				tip_pro.nombre as nombre_produccion, tar_iva.tarifa as tarifa, tar_iva.porcentaje_iva as porcentaje_iva 
				FROM cuerpo_proforma as cue_pro 
				INNER JOIN encabezado_proforma as enc_pro ON enc_pro.codigo_unico=cue_pro.codigo_unico 
				INNER JOIN clientes as cli ON cli.id=enc_pro.id_cliente 
				LEFT JOIN productos_servicios as pro_ser ON pro_ser.id=cue_pro.id_producto 
				LEFT JOIN marca_producto as mar_pro ON mar_pro.id_producto=cue_pro.id_producto 
				INNER JOIN tipo_produccion as tip_pro ON tip_pro.codigo=cue_pro.tipo_produccion 
				INNER JOIN tarifa_iva as tar_iva ON tar_iva.codigo=cue_pro.tarifa_iva WHERE enc_pro.ruc_empresa='".$ruc_empresa."' and cue_pro.ruc_empresa='".$ruc_empresa."' and DATE_FORMAT(enc_pro.fecha_proforma, '%Y/%m/%d') between '".date("Y/m/d", strtotime($desde))."' and '".date("Y/m/d", strtotime($hasta))."' $condicion_cliente $condicion_producto $condicion_marca ");//group by cue_fac.serie_factura, cue_fac.secuencial_factura
				$suma_total_proforma=0;
				$suma_cantidad =0;
				$suma_valor_unitario =0;
				$suma_subtotal_proforma =0;
				$suma_descuento =0;
				$suma_iva=0;
				while ($row=mysqli_fetch_array($resultado)){
					$n=$n+1;
						$fecha_proforma=$row['fecha_proforma'];
						$serie_proforma=$row['serie_proforma'];
						$secuencial_proforma=$row['secuencial_proforma'];
						$nombre_cliente_proforma=$row['nombre_cliente'];
						$ruc_cliente=$row['ruc'];
						$cantidad= $row['cantidad_proforma'];
						$suma_cantidad += $row['cantidad_proforma'];
						$producto= $row['nombre_producto'];
						$codigo= $row['codigo_producto'];
						$valor_unitario= $row['valor_unitario_proforma'];
						$suma_valor_unitario += $row['valor_unitario_proforma'];
						$subtotal_proforma= $row['subtotal_proforma'];
						$suma_subtotal_proforma += $row['subtotal_proforma'];
						$tipo_produccion= $row['nombre_produccion'];
						$descuento= $row['descuento'];
						$suma_descuento += $row['descuento'];
						$tarifa_iva= $row['tarifa'];
						$porcentaje_iva= $row['porcentaje_iva']/100;
						$suma_iva +=number_format(($subtotal_proforma * $porcentaje_iva),2,'.','');
						$total_proforma= ($row['subtotal_proforma'] + ($subtotal_proforma * $porcentaje_iva));
						$suma_total_proforma +=$total_proforma;
					?>				
					<tr>
					<td><?php echo $n; ?></td>
					<td><?php echo date("d/m/Y", strtotime($fecha_proforma)); ?></td>
					<td><?php echo $nombre_cliente_proforma; ?></td>
					<td><?php echo $serie_proforma; ?>-<?php echo str_pad($secuencial_proforma,9,"000000000",STR_PAD_LEFT); ?></td>
					<td><?php echo $codigo?></td>
					<td><?php echo $producto?></td>
					<td><?php echo $tipo_produccion?></td>
					<td><?php echo $tarifa_iva?></td>
					<td><?php echo $cantidad?></td>
					<td><?php echo number_format($valor_unitario,4,'.','')?></td>
					<td><?php echo  number_format($subtotal_proforma,2,'.','')?></td>
					<td><?php echo number_format($descuento,2,'.','')?></td>
					<td><?php echo  number_format(($subtotal_proforma * $porcentaje_iva),2,'.','')?></td>
					<td><?php echo number_format($total_proforma,2,'.','')?></td>
					</tr>
					<?php
				}
				?>	
					<tr class="info">
						<td colspan="8" class='text-right'>Totales</td>
						<td ><?php echo number_format($suma_cantidad,4,'.','')?></td>
						<td ><?php echo number_format($suma_valor_unitario,4,'.','')?></td>
						<td ><?php echo number_format($suma_subtotal_proforma,2,'.','')?></td>
						<td ><?php echo number_format($suma_descuento,2,'.','')?></td>
						<td ><?php echo number_format($suma_iva,2,'.','')?></td>
						<td ><?php echo number_format($suma_total_proforma,2,'.','')?></td>
					</tr>
				</table>
				</div>
			</div>
			<?php
}
?>

