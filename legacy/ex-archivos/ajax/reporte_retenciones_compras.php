<?php
/* Connect To Database*/
include("../conexiones/conectalogin.php");
$con = conenta_login();
session_start();
$id_usuario = $_SESSION['id_usuario'];
$ruc_empresa = $_SESSION['ruc_empresa'];

$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
if($action == 'reporte_retenciones_compras'){
		if (empty($_POST['fecha_desde'])) {
           $errors[] = "Ingrese fecha desde.";
		} else if (empty($_POST['fecha_hasta'])) {
		$errors[] = "Ingrese fecha hasta.";
        } else if (!empty($_POST['fecha_desde']) && !empty($_POST['fecha_hasta'])){
$fecha_desde=mysqli_real_escape_string($con,(strip_tags($_POST["fecha_desde"],ENT_QUOTES)));
$fecha_hasta=mysqli_real_escape_string($con,(strip_tags($_POST["fecha_hasta"],ENT_QUOTES)));
?>
<div class="panel panel-info">
   <div class="table-responsive">
   <table class="table">
  <tr class="info">
	<th>Registros</th>
	<th>Código</th>
	<th>Concepto</th>
	<th>Impuesto</th>
	<th>Base imponible</th>
	<th>Porcentaje</th>
	<th>Valor retenido</th>
</tr>
<?php

// PARA MOSTRAR LOS CONCEPTOS DE RETENCIONES
	$sql_conceptos_retencion=mysqli_query($con, "SELECT cr.impuesto as impuesto, 
	count(cr.secuencial_retencion) as registros, cr.porcentaje_retencion as porcentaje_retencion, 
	cr.codigo_impuesto as codigo_impuesto, rs.concepto_ret as concepto_ret, 
	sum(cr.base_imponible) as base_imponible, sum(cr.valor_retenido) as valor_retenido 
	FROM cuerpo_retencion as cr INNER JOIN encabezado_retencion as enc ON enc.serie_retencion=cr.serie_retencion and 
	enc.secuencial_retencion = cr.secuencial_retencion and enc.ruc_empresa=cr.ruc_empresa
	 LEFT JOIN retenciones_sri as rs ON rs.id_ret=cr.id_retencion WHERE cr.ruc_empresa = '".$ruc_empresa."' 
	 and enc.ruc_empresa = '".$ruc_empresa."' and enc.fecha_emision between '" . date("Y-m-d", strtotime($fecha_desde)) . "' 
	 and '" . date("Y-m-d", strtotime($fecha_hasta)) . "' group by cr.codigo_impuesto order by cr.impuesto asc");
	while ($row=mysqli_fetch_array($sql_conceptos_retencion)){
	$registros=$row["registros"];
	$concepto_retencion=$row["concepto_ret"];
	$tipo_impuesto=$row["impuesto"];
	$codigo_impuesto=$row["codigo_impuesto"];
	$base_imponible=$row["base_imponible"];
	$porcentaje_retencion=$row["porcentaje_retencion"]."%";
	$valor_retenido=$row["valor_retenido"];
		?>
		<tr>
			<td><?php echo $registros;?></td>
			<td><?php echo $codigo_impuesto;?></td>
			<td><?php echo $concepto_retencion;?></td>
			<td class="text-center"><?php echo $tipo_impuesto;?></td>
			<td class="text-right"><?php echo $base_imponible;?></td>
			<td class="text-right"><?php echo $porcentaje_retencion;?></td>
			<td class="text-right"><?php echo $valor_retenido;?></td>
		</tr>		

		<?php
		}

		//para sacar las sumas de las retenciones
		$suma_retenciones=mysqli_query($con, "SELECT sum(cr.valor_retenido) as valor_retenido, cr.impuesto as impuesto FROM
		cuerpo_retencion as cr INNER JOIN encabezado_retencion as enc ON enc.serie_retencion=cr.serie_retencion and 
		enc.secuencial_retencion = cr.secuencial_retencion and enc.ruc_empresa=cr.ruc_empresa WHERE
		cr.ruc_empresa = '".$ruc_empresa."' and enc.ruc_empresa = '".$ruc_empresa."' 
		and enc.fecha_emision between '" . date("Y-m-d", strtotime($fecha_desde)) . "' 
		and '" . date("Y-m-d", strtotime($fecha_hasta)) . "' group by cr.impuesto ");
				
		while ($row = mysqli_fetch_array($suma_retenciones)) {
			$impuesto = strtoupper($row["impuesto"]);
			$total_retenido =  $row['valor_retenido'];
			?>
			<tr class="info">
				<td class="text-right" colspan="6" style="padding: 2px;">Total Retenciones de <?php echo ($impuesto); ?>:</td>
				<td class="text-right" colspan="6" style="padding: 2px;"><?php echo number_format($total_retenido, 2, '.', ''); ?></td>
			</tr>
		<?php
		}	
?>		
</table>	
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
