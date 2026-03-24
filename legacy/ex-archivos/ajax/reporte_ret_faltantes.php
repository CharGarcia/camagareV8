<?php
	/* Connect To Database*/
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
	session_start();
	$ruc_empresa = $_SESSION['ruc_empresa'];

//PARA BUSCAR LAS FACTURAS de ventas	
$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
$desde=$_POST['desde'];
$hasta=$_POST['hasta'];

ini_set('date.timezone','America/Guayaquil');

			?>	
			
		<div class="panel panel-info">
			<div class="table-responsive">
			  <table class="table table-hover">
				<tr  class="info">
					<th>#</th>
					<th>Fecha</th>
					<th>Cliente</th>
					<th>Ruc</th>
					<th>Secuencial</th>
					<th>Detalle</th>
					<th>Retención</th>
					<th>Solicitar</th>
				</tr>
				<?php
				$n=0;
				
				$resultado = mysqli_query($con, "SELECT enc_fac.id_encabezado_factura as id_documento, cli.email as email , enc_fac.fecha_factura as fecha_factura, cli.nombre as nombre, cli.ruc as ruc, enc_fac.serie_factura as serie_factura, enc_fac.secuencial_factura as secuencial_factura
				FROM encabezado_factura as enc_fac INNER JOIN clientes as cli ON cli.id=enc_fac.id_cliente WHERE enc_fac.ruc_empresa='".$ruc_empresa."' and DATE_FORMAT(enc_fac.fecha_factura, '%Y/%m/%d') between '".date("Y/m/d", strtotime($desde))."' and '".date("Y/m/d", strtotime($hasta))."' and enc_fac.estado_sri !='ANULADA' order by enc_fac.fecha_factura asc ");

				while ($row=mysqli_fetch_array($resultado)){
						$id_documento=$row['id_documento'];
						$fecha_factura=$row['fecha_factura'];
						$serie_factura=$row['serie_factura'];
						$secuencial_factura=$row['secuencial_factura'];
						$nombre_cliente_factura=$row['nombre'];
						$mail_cliente=$row['email'];
						$ruc_cliente=$row['ruc'];
						$n=$n+1;
						$numero_documento=str_replace("-", "",$row['serie_factura']).str_pad($row['secuencial_factura'],9,"000000000",STR_PAD_LEFT);

						$retencion = mysqli_query($con, "SELECT sum(cue.valor_retenido) as valor_retenido, enc.id_encabezado_retencion as id_encabezado_retencion FROM cuerpo_retencion_venta as cue INNER JOIN encabezado_retencion_venta as enc ON enc.codigo_unico=cue.codigo_unico WHERE cue.ruc_empresa='".$ruc_empresa."' and cue.numero_documento='".$numero_documento."' group by cue.numero_documento");
						$row_ret=mysqli_fetch_array($retencion);
						//$suma_retenido=isset($row_ret['valor_retenido'])?number_format($row_ret['valor_retenido'],2,'.',''):"NO51616";
						if($row_ret){
							$valor_retenido=$row_ret['valor_retenido'];
							$id_encabezado_retencion=$row_ret['id_encabezado_retencion'];
							$con_retenido="";
						}else{
							$valor_retenido='<span class="label label-danger">NO</span>';
							$con_retenido="NO";
							$id_encabezado_retencion="";
						}
					?>
					<tr>
					<input type="hidden" value="<?php echo $mail_cliente; ?>" id="mail_cliente<?php echo $id_documento; ?>">
						<td><?php echo $n; ?></td>
						<td><?php echo date("d/m/Y", strtotime($fecha_factura)); ?></td>
						<td><?php echo $nombre_cliente_factura; ?></td>
						<td><?php echo $ruc_cliente; ?></td>
						<td><?php echo $serie_factura; ?>-<?php echo str_pad($secuencial_factura,9,"000000000",STR_PAD_LEFT); ?></td>
						<?php if ($con_retenido !="NO"){?>
						<td><a href="#" class='btn btn-info btn-xs' onclick="detalle_retencion_venta('<?php echo $id_encabezado_retencion; ?>')" title="Detalle documento" data-toggle="modal" data-target="#detalleDocumento"><i class="glyphicon glyphicon-list-alt"></i></a></td>
						<?php
						}else{
							?>
							<td></td>
							<?php
						}
						?>
						<td><?php echo $valor_retenido; ?></td>					
						<?php if ($con_retenido =="NO"){?>
							<td><a href="#" class="btn btn-info btn-xs" title="Solicitar retención" onclick="solicitar_retencion('<?php echo $id_documento; ?>')" data-toggle="modal" data-target="#EnviarDocumentosMail"><i class="glyphicon glyphicon-envelope"></i></a></td>
						<?php }else{?>
							<td></td>
							<?php
								}
							?>
					</tr>
					<?php					
					}
					?>	
									
				</table>
				</div>
			</div>