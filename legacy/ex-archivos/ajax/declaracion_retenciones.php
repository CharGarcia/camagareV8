<?php
/* Connect To Database*/
include("../conexiones/conectalogin.php");
$con = conenta_login();
session_start();
$id_usuario = $_SESSION['id_usuario'];
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_empresa = $_SESSION['id_empresa'];

$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
if($action == 'declaracion_retenciones'){
		if (empty($_POST['mes'])) {
           $errors[] = "Seleccione mes.";
        } else if (!empty($_POST['mes'])){
$mes=mysqli_real_escape_string($con,(strip_tags($_POST["mes"],ENT_QUOTES)));
$anio=mysqli_real_escape_string($con,(strip_tags($_POST["anio_periodo"],ENT_QUOTES)));
$parametro=$mes."/".$anio;
$mes_ano=$mes."-".$anio;
?>
<div class="panel-group" id="accordion">
	
	<div class="panel panel-info">
      <!--<div class="panel-heading">
		   <h4 class="panel-title">-->
			<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordion" href="#collapse1"><span class="caret"></span> POR PAGOS EFECTUADOS A RESIDENTES Y ESTABLECIMIENTOS PERMANENTES</a>
		  <!--</h4>
	  </div>-->
	
	<div id="collapse1" class="panel-collapse collapse">
	<div class="panel-body">
		<div class="form-group">
			<div class="col-sm-12">	
			 <div class="panel panel-info">
				<table class="table" >
				  <tr class="info">
					<th>Concepto</th>
					<th>Código</th>
					<th>Base imponible</th>
					<th>Valor retenido</th>
				</tr>
				<?php
				$sql_sueldos=mysqli_query($con, "SELECT round(sum(det.sueldo),2) as sueldo FROM detalle_rolespago as det INNER JOIN rolespago as rol 
				ON rol.id=det.id_rol WHERE rol.id_empresa ='".$id_empresa."' and rol.mes_ano='".$mes_ano."' and rol.status='1' group by rol.mes_ano ");
				$row_sueldo=mysqli_fetch_array($sql_sueldos);
				$suma_sueldos=empty($row_sueldo['sueldo'])?"0.00":$row_sueldo['sueldo'];
				?>
				<tr>
					<td>En relación de dependencia que supera o no la base desgravada</td>
					<td>302</td>
					<td><?php echo $suma_sueldos;?></td>
					<td class='text-center'>Revisar imp renta</td>
				</tr>	
				<?php

				// PARA MOSTRAR LOS CONCEPTOS DE RETENCIONES
				$suma_base=0;
					$sql_conceptos_retencion=mysqli_query($con, "SELECT  cr.codigo_impuesto as codigo_impuesto, rs.concepto_ret as concepto_ret, sum(cr.base_imponible) as base_imponible, sum(cr.valor_retenido) as valor_retenido FROM cuerpo_retencion cr, retenciones_sri rs WHERE cr.id_retencion=rs.id_ret and cr.ruc_empresa='".$ruc_empresa."' and cr.ejercicio_fiscal = '".$parametro."' and cr.impuesto='RENTA' group by cr.codigo_impuesto");
					while ($row=mysqli_fetch_array($sql_conceptos_retencion)){
					$concepto_retencion=$row["concepto_ret"];
					$codigo_impuesto=$row["codigo_impuesto"];
					$base_imponible=$row["base_imponible"];
					$valor_retenido=$row["valor_retenido"];
					$suma_base += $base_imponible;
						?>
						<tr>
							<td><?php echo $concepto_retencion;?></td>
							<td><?php echo $codigo_impuesto;?></td>
							<td><?php echo $base_imponible;?></td>
							<td class='text-center'><?php echo $valor_retenido;?></td>
						</tr>		
						<?php
						}
						//sumar base imponible para luego restar de las compras
						$sql_compras=mysqli_query($con, "SELECT  sum(cue_com.subtotal) as total_compras FROM encabezado_compra as enc_com INNER JOIN cuerpo_compra as cue_com ON enc_com.codigo_documento=cue_com.codigo_documento WHERE enc_com.ruc_empresa='".$ruc_empresa."' and cue_com.ruc_empresa='".$ruc_empresa."' and month(enc_com.fecha_compra)='".$mes."' and year(enc_com.fecha_compra)='".$anio."' and enc_com.id_comprobante != 4 ");
						$row_compras=mysqli_fetch_array($sql_compras);
						$total_compras=$row_compras['total_compras'];
						?>
				<tr>
					<td >Pagos de bienes y servicios no sujetos a retención </td>
					<td >332</td>
					<td ><?php echo number_format(($total_compras-$suma_base)>0?($total_compras-$suma_base):0,2,'.','');?></td>
					<td class='text-center'>0.00</td>
				</tr>	
						
				</table>
				
			  </div>
			</div>
		 </div>
	</div>						 
   </div>
   </div>

   	<div class="panel panel-info">
      <!--<div class="panel-heading">
		   <h4 class="panel-title">-->
			<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordion" href="#collapse2"><span class="caret"></span> TOTALES</a>
		  <!--</h4>
	  </div>-->
	
	<div id="collapse2" class="panel-collapse collapse in">
	<div class="panel-body">
		<div class="form-group">
			<div class="col-sm-12">	
			 <div class="panel panel-info">

				<table class="table" >
				<?php
				//para sacar las sumas de las retenciones de renta
					$suma_retenciones_renta=mysqli_query($con, "SELECT sum(valor_retenido) as retenciones_renta FROM cuerpo_retencion WHERE ruc_empresa='$ruc_empresa' and ejercicio_fiscal = '".$parametro."' and impuesto='RENTA'");
					$renta=mysqli_fetch_array($suma_retenciones_renta);
					$suma_renta = $renta['retenciones_renta'];

					?>
					
				<tr class="info">
					<td class='text-right' colspan="3" >Total retenciones de RENTA: </td>
					<td class='text-center'><?php echo number_format($suma_renta,2,'.','');?></td>
				</tr>
			   </table>
				  </div>
				</div>
			 </div>
				</div>						 
			   </div>
			   </div>
   
   
   
</div>

<?php
}else {
			$errors []= "Error desconocido.";
		}
}

//fin de retenciones compras
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
