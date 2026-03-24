<?php
/* Connect To Database*/
include("../conexiones/conectalogin.php");

$con = conenta_login();
session_start();
$id_usuario = $_SESSION['id_usuario'];
$ruc_empresa = $_SESSION['ruc_empresa'];
if (isset($_POST['codigo_compra'])){$codigo_compra=$_POST['codigo_compra'];}
if (isset($_POST['detalle_compra'])){$detalle_compra = $_POST['detalle_compra'];}
if (isset($_POST['cantidad_compra'])){$cantidad_compra=$_POST['cantidad_compra'];}
if (isset($_POST['val_uni_compra'])){$val_uni_compra=$_POST['val_uni_compra'];}
if (isset($_POST['descuento_compra'])){$descuento_compra=$_POST['descuento_compra'];}
if (isset($_POST['tipo_impuesto'])){$tipo_impuesto=$_POST['tipo_impuesto'];}
if (isset($_POST['codigo_impuesto'])){$codigo_impuesto=$_POST['codigo_impuesto'];}

//para guardar en la compra temporal
if (isset($_POST['detalle_compra'])){
$insert_tmp=mysqli_query($con, "INSERT INTO compra_tmp VALUES (null,'".$id_usuario."', '".$codigo_compra."', '".$detalle_compra."', '".$cantidad_compra."', '".$val_uni_compra."', '".$tipo_impuesto."', '".$codigo_impuesto."','".$descuento_compra."')");
}

//para eliminar un iten de la compra tmp
if (isset($_GET['id'])){
$id_tmp=intval($_GET['id']);	
$delete=mysqli_query($con, "DELETE FROM compra_tmp WHERE id_compra='".$id_tmp."'");
}
?>
<div class="panel panel-info" style="overflow-y: scroll; height: 200px;">
   <div class="table-responsive">
  <table class="table table-bordered">
  <tr class="info">
	<th class='text-center' style="padding: 2px;">Código</th>
	<th style="padding: 2px;">Descripción</th>
	<th class='text-center' style="padding: 2px;">Cant.</th>
	<th class='text-right' style="padding: 2px;">Precio Uni.</th>
	<th class='text-center' style="padding: 2px;">Descuento</th>
	<th class='text-right' style="padding: 2px;">Impuesto</th>
	<th class='text-right' style="padding: 2px;">Tipo. IMP.</th>
	<th class='text-right' style="padding: 2px;">Subtotal</th>
	<th class='text-right' style="padding: 2px;">Eliminar</th>
</tr>
<?php
// PARA MOSTRAR LOS ITEMS DE LA compra
	$subtotal_general=0;
	$total_descuento=0;
	$sql=mysqli_query($con, "select * FROM compra_tmp WHERE id_usuario = '". $id_usuario ."' ");
	while ($row=mysqli_fetch_array($sql)){
			$id_tmp=$row["id_compra"];
			$codigo_compra=$row['codigo'];
			$detalle=$row['detalle'];
			$cantidad=$row['cantidad'];
			$impuesto=$row['impuesto'];
			$codigo_impuesto=$row['codigo_impuesto'];
			$unitario= number_format($row['unitario'],4,'.','');
			$descuento=number_format($row['descuento'],2,'.','');
			$subtotal=number_format($cantidad*$unitario - $descuento,2,'.','');
		    $subtotal_general+=$cantidad * $unitario - $descuento;//Sumador subtotal general
			$total_descuento+=number_format($descuento,2,'.','');//Sumador total descuento

			//PARA MOStrar el nombre de la tarifa de iva
			$nombre_tarifa_iva=mysqli_query($con, "select * from tarifa_iva where codigo = '". $codigo_impuesto ."'");
			$row_tarifa=mysqli_fetch_array($nombre_tarifa_iva);
			$nombre_tarifa=$row_tarifa['tarifa'];
				//para mostrar el impuesto
			$nombre_impuestos=mysqli_query($con, "select * from impuestos_ventas where codigo_impuesto = '". $impuesto ."'");
			$row_impuesto=mysqli_fetch_array($nombre_impuestos);
			$nombre_impuesto=$row_impuesto['nombre_impuesto'];
				?>
				
				<tr >
					<td class='text-center' style="padding: 2px;"><?php echo strtoupper($codigo_compra);?></td>
					<td class='text-left' style="padding: 2px;"><?php echo strtoupper($detalle);?></td>
					<td style="padding: 2px;"><?php echo $cantidad;?></td>
					<td class='text-right' style="padding: 2px;"><?php echo $unitario;?></td>
					<td class='text-center' style="padding: 2px;"><?php echo $descuento;?></td>
					<td class='text-right' style="padding: 2px;"><?php echo $nombre_impuesto;?></td>
					<td class='text-right'style="padding: 2px;" ><?php echo $nombre_tarifa;?></td>
					<td class='text-right' style="padding: 2px;"><?php echo $subtotal;?></td>
					<td class='text-right' style="padding: 2px;">
					<a href="#" class='btn btn-danger btn-sm' onclick="eliminar_item_compra('<?php echo $id_tmp; ?>')" title ="Eliminar item"><i class="glyphicon glyphicon-remove"></i></a>
					</td>
				</tr>
								
				<?php
			}
			?>
</table>
</div>
</div>

<!-- para mostrar los adicionales de la factura y los totales -->
<div class="row">
<div class="col-md-7">
</div>
	<div class="col-md-5">
		<div class="panel panel-info">
		   <div class="table-responsive">
				<table class="table">
					<tr class="info">
						<td class='text-right' style="padding: 2px;">SUBTOTAL GENERAL: </td>
						<td class='text-center' style="padding: 2px;"><?php echo number_format($subtotal_general,2,'.','');?></td>
						<td></td>
					</tr>
			<?php
			//PARA MOSTRAR LOS NOMBRES DE CADA TARIFA DE IVA Y LOS VALORES DE CADA SUBTOTAL
				$subtotal_tarifa_iva=0;
				$sql=mysqli_query($con, "select ti.porcentaje_iva as porcentaje_iva, ti.tarifa as tarifa, sum(ct.cantidad * round(ct.unitario,2) - descuento) as suma_tarifa_iva from compra_tmp ct, tarifa_iva ti where ti.codigo = ct.codigo_impuesto and ct.id_usuario = '". $id_usuario ."' group by ct.codigo_impuesto " );
				while ($row=mysqli_fetch_array($sql)){
				$nombre_tarifa_iva=strtoupper($row["tarifa"]);
				$porcentaje_iva=1+($row["porcentaje_iva"]/100);
				$subtotal_tarifa_iva=number_format($row['suma_tarifa_iva'],2,'.','');
			?>
					<tr class="info">
						<td class='text-right' style="padding: 2px;">SUBTOTAL <?php echo ($nombre_tarifa_iva);?>:</td>
						<td class='text-center' style="padding: 2px;"><?php echo number_format($subtotal_tarifa_iva,2,'.','');?></td>
						<td></td>
					</tr>

			<?php
				}
			?>
					<tr class="info">
						<td class='text-right' style="padding: 2px;">TOTAL DESCUENTO: </td>
						<td class='text-center' style="padding: 2px;"><?php echo number_format($total_descuento,2,'.','');?></td>
						<td></td>
					</tr>
			<?php
				//PARA MOSTRAR LOS IVAS
			$total_iva = 0;
			$subtotal_porcentaje_iva=0;
			$sql=mysqli_query($con, "select ti.tarifa as tarifa, (sum(ct.cantidad * ct.unitario - descuento) * ti.tarifa /100) as porcentaje from compra_tmp ct, tarifa_iva ti where ti.codigo = ct.codigo_impuesto and ct.id_usuario = '". $id_usuario ."' and ti.tarifa > 0 group by ct.codigo_impuesto " );
			while ($row=mysqli_fetch_array($sql)){
			$nombre_porcentaje_iva=strtoupper($row["tarifa"]);
			$porcentaje_iva=$row['porcentaje'];
			$subtotal_porcentaje_iva= $porcentaje_iva ;
			$total_iva+=$subtotal_porcentaje_iva;
			?>
					<tr class="info">
						<td class='text-right' style="padding: 2px;">IVA <?php echo ($nombre_porcentaje_iva);?>:</td>
						<td class='text-center' style="padding: 2px;"><?php echo number_format($subtotal_porcentaje_iva,2,'.','');?></td>
						<td></td>
					</tr>
			<?php
			}	
			?>
					<tr class="info">
						<td class='text-right' style="padding: 2px;">TOTAL: </td>
						<td class='text-center' style="padding: 2px;"><?php echo number_format($subtotal_general + $total_iva ,2,'.','');?></td>
						<td><input type="hidden" id="suma_factura_compra" value="<?php echo number_format($subtotal_general + $total_iva ,2,'.','');?>"></td>
					</tr>	 
				</table>
			</div>
		</div>
	</div>
</div>
