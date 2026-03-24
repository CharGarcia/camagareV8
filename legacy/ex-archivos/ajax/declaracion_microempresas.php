<?php
/* Connect To Database*/
include("../conexiones/conectalogin.php");
$con = conenta_login();
session_start();
$id_usuario = $_SESSION['id_usuario'];
$ruc_empresa = $_SESSION['ruc_empresa'];

$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
if($action == 'declaracion_microempresas'){
		$semestre=mysqli_real_escape_string($con,(strip_tags($_POST["semestre"],ENT_QUOTES)));
		$anio=mysqli_real_escape_string($con,(strip_tags($_POST["anio_periodo"],ENT_QUOTES)));
		
		if (empty($_POST['semestre'])) {
           $errors[] = "Seleccione un semestre.";
		}else if (empty($_POST['anio_periodo'])) {
           $errors[] = "Seleccione año.";
        } else if (!empty($_POST['semestre']) && !empty($_POST['anio_periodo'])){
			$condicion_ruc_empresa_encabezado="mid(enc_fac.ruc_empresa,1,10) = '". substr($ruc_empresa,0,10) ."' and mid(enc_fac.ruc_empresa,1,10) = '". substr($ruc_empresa,0,10) ."'";
			$condicion_ruc_empresa_cuerpo=" and mid(cue_fac.ruc_empresa,1,10) = '". substr($ruc_empresa,0,10) ."' and mid(cue_fac.ruc_empresa,1,10) = '". substr($ruc_empresa,0,10) ."'";
			$condicion_ruc_empresa_encabezado_nc="mid(enc_nc.ruc_empresa,1,10) = '". substr($ruc_empresa,0,10) ."' and mid(enc_nc.ruc_empresa,1,10) = '". substr($ruc_empresa,0,10) ."'";
			$condicion_ruc_empresa_cuerpo_nc=" and mid(cue_nc.ruc_empresa,1,10) = '". substr($ruc_empresa,0,10) ."' and mid(cue_nc.ruc_empresa,1,10) = '". substr($ruc_empresa,0,10) ."'";
	
			$condicion_ruc_empresa_encabezado_retencion=" mid(enc_ret.ruc_empresa,1,10) = '". substr($ruc_empresa,0,10) ."' and mid(enc_ret.ruc_empresa,1,10) = '". substr($ruc_empresa,0,10) ."'";

if ($semestre=="1"){
			$desde="'".date("Y/m/d",strtotime($anio."/01/01"))."'";
			$hasta="'".date("Y/m/d",strtotime($anio."/06/30"))."'";

		}else{
			$desde="'".date("Y/m/d",strtotime($anio."/07/01"))."'";
			$hasta="'".date("Y/m/d",strtotime($anio."/12/31"))."'";
		}
		//ventas
		$resultado_ventas = mysqli_query($con, "SELECT round(sum(cue_fac.subtotal_factura - cue_fac.descuento),2) as total_ventas FROM encabezado_factura as enc_fac INNER JOIN cuerpo_factura as cue_fac ON cue_fac.serie_factura= enc_fac.serie_factura and cue_fac.secuencial_factura= enc_fac.secuencial_factura WHERE $condicion_ruc_empresa_encabezado $condicion_ruc_empresa_cuerpo 
		and DATE_FORMAT(enc_fac.fecha_factura, '%Y/%m/%d') between $desde and $hasta group by mid(enc_fac.ruc_empresa,1,10)");
		$row_ventas=mysqli_fetch_array($resultado_ventas);
		$total_ventas=empty($row_ventas['total_ventas'])?0:$row_ventas['total_ventas'];

		$resultado_nc_ventas = mysqli_query($con, "SELECT round(sum(cue_nc.subtotal_nc - cue_nc.descuento),2) as total_ventas FROM encabezado_nc as enc_nc INNER JOIN cuerpo_nc as cue_nc ON cue_nc.serie_nc= enc_nc.serie_nc and cue_nc.secuencial_nc= enc_nc.secuencial_nc WHERE $condicion_ruc_empresa_encabezado_nc $condicion_ruc_empresa_cuerpo_nc 
		and DATE_FORMAT(enc_nc.fecha_nc, '%Y/%m/%d') between $desde and $hasta group by mid(enc_nc.ruc_empresa,1,10)");
		$row_nc_ventas=mysqli_fetch_array($resultado_nc_ventas);
		$total_nc_ventas=empty($row_nc_ventas['total_ventas'])?0:$row_nc_ventas['total_ventas'];

		//retenciones 1.75
		$resultado_retenciones = mysqli_query($con, "SELECT round(sum(cue_ret.valor_retenido),2) as total_retenciones FROM encabezado_retencion_venta as enc_ret INNER JOIN cuerpo_retencion_venta as cue_ret ON cue_ret.codigo_unico= enc_ret.codigo_unico WHERE $condicion_ruc_empresa_encabezado_retencion 
		and DATE_FORMAT(enc_ret.fecha_emision, '%Y/%m/%d') between $desde and $hasta and cue_ret.impuesto='1' and cue_ret.porcentaje_retencion = '1.75' group by mid(enc_ret.ruc_empresa,1,10)");
		$row_retenciones=mysqli_fetch_array($resultado_retenciones);
		$total_retenciones=empty($row_retenciones['total_retenciones'])?0:$row_retenciones['total_retenciones']; 

		//retenciones diferentes de 1.75
		$resultado_base_imponible = mysqli_query($con, "SELECT round(sum(cue_ret.base_imponible),2) as subtotal FROM encabezado_retencion_venta as enc_ret INNER JOIN cuerpo_retencion_venta as cue_ret ON cue_ret.codigo_unico= enc_ret.codigo_unico WHERE $condicion_ruc_empresa_encabezado_retencion 
		and DATE_FORMAT(enc_ret.fecha_emision, '%Y/%m/%d') between $desde and $hasta and cue_ret.impuesto='1' and cue_ret.porcentaje_retencion != '1.75' group by mid(enc_ret.ruc_empresa,1,10)");
		$row_base_imponible=mysqli_fetch_array($resultado_base_imponible);
		$total_base_imponible=empty($row_base_imponible['subtotal'])?0:$row_base_imponible['subtotal']; 
		
		$subtotal_general=($total_ventas-$total_base_imponible)<0?0:($total_ventas-$total_base_imponible);
		?>


		<div class="panel panel-info">
		<div class="panel-body">
				<table class="table" >
					<tr>
					<td class='col-md-12' style="background: #2E64FE; color: rgb(247, 248, 250); padding: 1px;" colspan="12"><FONT SIZE=2>IMPUESTO A LA RENTA SEMESTRAL DEL RÉGIMEN IMPOSITIVO PARA MICROEMPRESAS</FONT></td>
				  </tr>
					<tr>
						<td style="padding: 1px;" colspan="10">Ingresos Brutos de la Actividad Empresarial Sujetos al Régimen Impositivo para Microempresas</td>
						<td class="col-md-1 text-center" style="background: silver; color: rgb(0, 0, 0); padding: 1px;">301</td>
						<td class="text-right" style="color: rgb(0, 0, 0); padding: 1px;"><?php echo number_format($subtotal_general,2,'.','');?></td>
					</tr>
					<tr>
						<td colspan="10" style="padding: 1px;">(-) Valor de devoluciones o descuentos comerciales que correspondan a los ingresos brutos de la actividad</td>
						<td class="text-center" style="background: silver; color: rgb(0, 0, 0); padding: 1px;">302*</td>
						<td class="text-right" style="color: rgb(0, 0, 0); padding: 1px;"><?php echo number_format($total_nc_ventas,2,'.','');?></td>
					</tr>
					<tr>
						<td colspan="10" style="padding: 1px;">(-) Ingresos exentos del Impuesto a la Renta que correspondan a la actividad empresarial</td>
						<td class="text-center" style="background: silver; color: rgb(0, 0, 0); padding: 1px;">303</td>
						<td></td>
					</tr>
					<tr>
						<td colspan="10" style="padding: 1px;">(-) Ajustes en ingresos de la actividad empresarial por efecto de aplicación de impuestos diferidos (Generación)</td>
						<td class="text-center" style="background: silver; color: rgb(0, 0, 0); padding: 1px;">304</td>
						<td></td>
						</tr>
					<tr>
						<td colspan="10" style="padding: 1px;">(+) Ajustes en ingresos de la actividad empresarial por efecto de aplicación de impuestos diferidos (Reversión)</td>
						<td class="text-center" style="background: silver; color: rgb(0, 0, 0); padding: 1px;">305</td>
						<td></td>
					</tr>
					<tr>
						<td colspan="10" style="padding: 1px;">(=) BASE IMPONIBLE PARA EL IMPUESTO A LA RENTA DEL RÉGIMEN IMPOSITIVO PARA MICROEMPRESAS</td>
						<td class="text-center" style="background: silver; color: rgb(0, 0, 0); padding: 1px;">399</td>
						<td class="text-right" style="color: rgb(0, 0, 0); padding: 1px;"><?php echo number_format($subtotal_general-$total_nc_ventas,2,'.','');?></td>
					</tr>
					<tr>
						<td colspan="10" style="padding: 1px;">Impuesto a la Renta causado del Régimen Impositivo para Microempresas</td>
						<td class="text-center" style="background: silver; color: rgb(0, 0, 0); padding: 1px;">401</td>
						<td class="text-right" style="padding: 1px;">2.00</td>
					</tr>
					<tr>
						<td colspan="10" style="padding: 1px;">(-) Retenciones en la Fuente que le realizaron respecto de los ingresos de la actividad empresarial sujetos al Régimen Impositivo para Microempresas</td>
						<td class="text-center" style="background: silver; color: rgb(0, 0, 0); padding: 1px;">402</td>
						<td class="text-right" style="color: rgb(0, 0, 0); padding: 1px; padding: 1px;"><?php echo number_format($total_retenciones,2,'.','');?></td>
					</tr>
					
					<tr>
						<td colspan="10" style="background: #FAAC58; color: rgb(0, 0, 0); padding: 1px;">(=) IMPUESTO A PAGAR</td>
						<td class="text-center" style="background: #FAAC58; color: rgb(0, 0, 0); padding: 1px;">499</td>
						<td class="text-right" style="background: #FAAC58; color: rgb(0, 0, 0); padding: 1px;"><?php echo number_format((($subtotal_general-$total_nc_ventas)*0.02)-$total_retenciones,2,'.','');?></td>
					</tr>
					<tr>
					<td class='col-md-12' style="background: #2E64FE; color: rgb(247, 248, 250); padding: 1px;" colspan="12"><FONT SIZE=2>VALORES A PAGAR Y FORMAS DE PAGO (LUEGO DE IMPUTACIÓN AL PAGO EN DECLARACIONES SUSTITUTIVAS)</FONT></td>
				  </tr>
				  <tr>
						<td colspan="10" style="background: #FAAC58; color: rgb(0, 0, 0); padding: 1px;">TOTAL IMPUESTO A PAGAR</td>
						<td class="text-center" style="background: #FAAC58; color: rgb(0, 0, 0); padding: 1px;">902</td>
						<td class="text-right" style="background: #FAAC58; color: rgb(0, 0, 0); padding: 1px;"><?php echo number_format((($subtotal_general-$total_nc_ventas)*0.02)-$total_retenciones,2,'.','');?></td>
					</tr>
						<tr>
						<td colspan="12" style="background: silver; color: red; padding: 1px; "><b>* El campo 302 se debe verificar si las Notas de Crédito corresponden a la actividad microempresarial.</b></td>
						</tr>
				</table>
</div>
</div>
			
	
<?php
	}else{
			$errors[]= "Error desconocido";
		}
	}
	
if (isset($errors)){			
			?>
			<div class="alert alert-danger" role="alert">
				<button type="button" class="close" data-dismiss="alert">&times;</button>
					<strong>Error!</strong> 
					<?php
						foreach ($errors as $error) {
								echo $error;
							}
						?>
			</div>
			<?php
			}
			if (isset($messages)){
				
				?>
				<div class="alert alert-success" role="alert">
						<button type="button" class="close" data-dismiss="alert">&times;</button>
						<strong>¡Bien hecho!</strong>
						<?php
							foreach ($messages as $message) {
									echo $message;
								}
							?>
				</div>
				<?php
			}
?>
