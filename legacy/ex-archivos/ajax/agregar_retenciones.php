<?php
/* Connect To Database*/
include("../conexiones/conectalogin.php");
$con = conenta_login();
session_start();
$id_usuario = $_SESSION['id_usuario'];
$ruc_empresa = $_SESSION['ruc_empresa'];
if (isset($_POST['id_ret'])){$id_retencion = $_POST['id_ret'];}
if (isset($_POST['porcentaje_ret'])){$porcentaje_ret=$_POST['porcentaje_ret'];}
if (isset($_POST['base_imponible_ret'])){$base_imponible_ret=$_POST['base_imponible_ret'];}
if (isset($_POST['fecha_ret']))
if (isset($_POST['fecha_fac'])){
$fecha_ret=$_POST['fecha_ret'];
$mes_fiscal = date("m", strtotime($fecha_ret));
$anio_fiscal = date("Y", strtotime($fecha_ret));
$ejercicio_fiscal = $mes_fiscal."/".$anio_fiscal;
}

	
	if (isset($_POST['id_cliente'])){$codigo_proveedor=$_POST['id_cliente'];}

if (!empty($id_retencion) and !empty($base_imponible_ret) ){
//para buscar datos de la retencion
	$sql_retencion=mysqli_query($con, "select * from retenciones_sri WHERE id_ret = '".$id_retencion."'");
	$row_retenciones=mysqli_fetch_array($sql_retencion);
	$codigo_ret = $row_retenciones['codigo_ret'];
	$concepto_ret = $row_retenciones['concepto_ret'];
	$impuesto_ret = $row_retenciones['impuesto_ret'];
	$valor_ret=number_format(($base_imponible_ret * $porcentaje_ret)/100,2,'.','');

//para controlar la retencion de iva en los proveedores
//comprobar si la seleccion de retencion es de IVA
		
	if($impuesto_ret=="IVA"){
		//traer el tipo de empresa que soy
		if (isset($_POST['id_proveedor'])){
			$codigo_proveedor=$_POST['id_proveedor'];
			$sql_mi_empresa=mysqli_query($con, "select em.tipo as tipo, te.nombre as nombre, em.nombre_comercial as miempresa from empresas em, tipo_empresa te WHERE em.ruc = '".$ruc_empresa."' and em.tipo=te.codigo");
			$row_tipo_mi_empresa=mysqli_fetch_array($sql_mi_empresa);
			$codigo_mi_empresa = intval($row_tipo_mi_empresa['tipo']);
			$nombre_tipo_mi_empresa = $row_tipo_mi_empresa['nombre'];
			$nombre_mi_empresa = $row_tipo_mi_empresa['miempresa'];
		
			
		//traer el tipo de empresa a quien voy a retener
			$sql_empresa_retenida=mysqli_query($con, "select pr.tipo_empresa as tipo_empresa, te.nombre as nombre, pr.razon_social as retenido from proveedores pr, tipo_empresa te WHERE pr.id_proveedor = '".$codigo_proveedor."' and pr.tipo_empresa=te.codigo");
			$row_tipo_empresa_retenida=mysqli_fetch_array($sql_empresa_retenida);
			$codigo_empresa_retenida = intval($row_tipo_empresa_retenida['tipo_empresa']);
			$nombre_tipo_empresa_retenida = $row_tipo_empresa_retenida['nombre'];
			$nombre_empresa_retenida = $row_tipo_empresa_retenida['retenido'];
			$mensaje = $nombre_mi_empresa." contribuyente tipo: " . $nombre_tipo_mi_empresa . ", no debe retener IVA a ".$nombre_empresa_retenida." que es un contribuyente tipo: " . $nombre_tipo_empresa_retenida;
			
						
		// si ambos son contribuyentes especiales si se retienen iva
			if ($codigo_empresa_retenida==4 and $codigo_mi_empresa ==4){ 
			$busca_retencion_repetida=mysqli_query($con, "select * from retencion_tmp WHERE id_ret = '".$id_retencion."' and id_usuario='".$id_usuario."'");
			 $count_registros = mysqli_num_rows($busca_retencion_repetida);
						 if ($count_registros > 0) {
						 echo "<script>alert('El concepto de retención seleccionado, ya está agregado.')</script>";
						echo "<script>window.close();</script>";
						 }else{
			//para guardar en la retencion temporal
			$insert_tmp=mysqli_query($con, "INSERT INTO retencion_tmp VALUES (null, '".$id_retencion."', '".$codigo_ret."','".$concepto_ret."','".$impuesto_ret."','".$porcentaje_ret."','".$base_imponible_ret."','".$valor_ret."','".$id_usuario."','".$ejercicio_fiscal."')");
						 }
			}else{
			
			if($codigo_empresa_retenida>=$codigo_mi_empresa or ($codigo_empresa_retenida==2 and $codigo_mi_empresa==3) ){
					echo "<script>alert('$mensaje')</script>";
					echo "<script>window.close();</script>";
				}else{
					//para buscar las retenciones dentro del tmp que no se repitan
				$busca_retencion_repetida=mysqli_query($con, "select * from retencion_tmp WHERE id_ret = '".$id_retencion." and id_usuario='".$id_usuario."'");
				$count_registros = mysqli_num_rows($busca_retencion_repetida);
						 if ($count_registros > 0) {
						 echo "<script>alert('El concepto de retención seleccionado, ya está agregado.')</script>";
						echo "<script>window.close();</script>";
						 }else{
				//para guardar en la retencion temporal
				$insert_tmp=mysqli_query($con, "INSERT INTO retencion_tmp VALUES (null, '".$id_retencion."', '".$codigo_ret."','".$concepto_ret."','".$impuesto_ret."','".$porcentaje_ret."','".$base_imponible_ret."','".$valor_ret."','".$id_usuario."','".$ejercicio_fiscal."')");
						 }
				}
			}
			}else{
				//para buscar las retenciones dentro del tmp que no se repitan
				$busca_retencion_repetida=mysqli_query($con, "select * from retencion_tmp WHERE id_ret = '".$id_retencion."' and id_usuario='".$id_usuario."'");
				$count_registros = mysqli_num_rows($busca_retencion_repetida);
					if ($count_registros > 0) {
					 echo "<script>alert('El concepto de retención seleccionado, ya está agregado.')</script>";
					echo "<script>window.close();</script>";
					}else{
					//para guardar en la retencion temporal
					$insert_tmp=mysqli_query($con, "INSERT INTO retencion_tmp VALUES (null, '".$id_retencion."', '".$codigo_ret."','".$concepto_ret."','".$impuesto_ret."','".$porcentaje_ret."','".$base_imponible_ret."','".$valor_ret."','".$id_usuario."','".$ejercicio_fiscal."')");
					}
			}
	}else{
						
		//para buscar las retenciones dentro del tmp que no se repitan
		$busca_retencion_repetida=mysqli_query($con, "select * from retencion_tmp WHERE id_ret = '".$id_retencion."' and id_usuario='".$id_usuario."'");
		 $count_registros = mysqli_num_rows($busca_retencion_repetida);
				if ($count_registros > 0) {
				 echo "<script>alert('El concepto de retención seleccionado, ya está agregado.')</script>";
				echo "<script>window.close();</script>";
				}else{
				//para guardar en la retencion temporal
				$insert_tmp=mysqli_query($con, "INSERT INTO retencion_tmp VALUES (null, '".$id_retencion."', '".$codigo_ret."','".$concepto_ret."','".$impuesto_ret."','".$porcentaje_ret."','".$base_imponible_ret."','".$valor_ret."','".$id_usuario."','".$ejercicio_fiscal."')");
				}
		}
}


//para eliminar un iten de la retencion
if (isset($_GET['id'])){
$id_tmp=intval($_GET['id']);	
$delete=mysqli_query($con, "DELETE FROM retencion_tmp WHERE id='".$id_tmp."'");
}
?>
<div class="panel panel-info">
   <div class="table-responsive">
  <table class="table table-bordered">
  <tr class="info">
	<th class='text-right'>Año fiscal</th>
	<th class='text-center'>Base imponible</th>
	<th class='text-center'>Impuesto</th>
	<th class='text-right'>Código</th>
	<th class='text-center'>Concepto</th>
	<th class='text-right'>Porcentaje</th>
	<th class='text-center'>Valor retención</th>
	<th class='text-right'>Eliminar</th>
	</tr>
<?php
// PARA MOSTRAR LOS ITEMS DE LA RETENCION
	$total_retencion=0;
	$total_factura=0;
	$sql=mysqli_query($con, "SELECT * FROM retencion_tmp WHERE id_usuario = $id_usuario ");
	while ($row=mysqli_fetch_array($sql)){
	$id_tmp=$row["id"];
	$base_ret=number_format($row['base_ret'],2,'.','');
	$impuesto_ret=$row['impuesto_ret'];
	$codigo_ret=$row['cod_ret'];
	$concepto_ret=$row['concepto_ret'];
	$porcentaje_ret=$row['porcentaje_ret'];
	$proceso_fiscal=$row['ejercicio_fiscal'];
	$val_ret=number_format($row['val_ret'],2,'.','');
	$total_retencion+=$val_ret;
	$total_factura+=$base_ret;
	
			
		?>
		<tr>		
			<td><?php echo $proceso_fiscal;?></td>
			<td><?php echo $base_ret;?></td>
			<td><?php echo $impuesto_ret;?></td>
			<td><?php echo $codigo_ret;?></td>
			<td><?php echo strtoupper($concepto_ret);?></td>
			<td><?php echo $porcentaje_ret;?> %</td>
			<td><?php echo $val_ret;?></td>
			<td class='text-right'>
			<a href="#" class='btn btn-danger btn-sm' onclick="eliminar_concepto_retencion('<?php echo $id_tmp; ?>')" title ="Eliminar item"><i class="glyphicon glyphicon-trash"></i></a></td>
		</tr>		

		<?php
		}
		?>
<tr class="info">
	<th colspan="6" class='text-right'>Total retención</th>
	<th ><?php echo number_format($total_retencion,2,'.','') ;?></th>
	<td><input type="hidden" id="suma_retencion" value="<?php echo number_format($total_retencion ,2,'.','');?>"></td>
	</tr>
</table>
</div>
</div>