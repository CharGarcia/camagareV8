<?php
/* Connect To Database*/
include("../conexiones/conectalogin.php");
$con = conenta_login();
session_start();
$id_usuario = $_SESSION['id_usuario'];
$ruc_empresa = $_SESSION['ruc_empresa'];
if (isset($_POST['id'])){$id = $_POST['id'];}
if (isset($_POST['precio_venta'])){$precio_venta=$_POST['precio_venta'];}
if (isset($_POST['cantidad'])){$cantidad=$_POST['cantidad'];}
if (isset($_POST['nombre_product'])){$nombre_producto=$_POST['nombre_product'];}


if (!empty($id) and !empty($cantidad) and !empty($precio_venta) ){
//para buscar datos del producto o servicio 
	$sql_producto=mysqli_query($con, "SELECT * FROM productos_servicios WHERE id = '". $id ."'");
	$row_producto=mysqli_fetch_array($sql_producto);
	$tipo_produccion = $row_producto['tipo_produccion'];
	$tarifa_iva = $row_producto['tarifa_iva'];
	$tarifa_ice = $row_producto['tarifa_ice'];
	$tarifa_botellas = $row_producto['tarifa_botellas'];

//para guardar en la factura temporal
$subtotal = number_format($cantidad * $precio_venta, 2, '.', '');
$insert_tmp=mysqli_query($con, "INSERT INTO factura_tmp (id_producto, nombre_producto, cantidad_tmp, precio_tmp , descuento ,tipo_produccion,tarifa_iva,tarifa_ice,tarifa_botellas, id_usuario, id_bodega, id_medida, lote, vencimiento, subtotal, ruc_empresa) VALUES ('".$id."','".$nombre_producto."', '".$cantidad."','".$precio_venta."','0','".$tipo_produccion."','".$tarifa_iva."','".$tarifa_ice."','".$tarifa_botellas."','".$id_usuario."',0,0,0,0,'".$subtotal."','".$ruc_empresa."')");
}

//para eliminar un iten de la factura
if (isset($_GET['id'])){
$id_tmp=intval($_GET['id']);	
$delete=mysqli_query($con, "DELETE FROM factura_tmp WHERE id='".$id_tmp."'");
}
?>


<table class="table">
<tr class="info">
	<th class='text-center'>Cliente</th>
	<th class='text-center'>Factura</th>
	<th class='text-right'>Valor</th>
	<th class='text-right'>Eliminar</th>
	<td></td>
</tr>
<?php
// PARA MOSTRAR LOS ITEMS DE LA FACTURA
	$subtotal_general=0;
	$total_descuento=0;
	$sql=mysqli_query($con, "SELECT * FROM factura_tmp WHERE id_usuario = '". $id_usuario ."' and ruc_empresa = '". $ruc_empresa ."'");
	while ($row=mysqli_fetch_array($sql)){
	$id_tmp=$row["id"];
	$codigo_producto=$row['id_producto'];
	$cantidad=$row['cantidad_tmp'];
	$nombre_producto=$row['nombre_producto'];
	$precio_venta=number_format($row['precio_tmp'],4,'.','');
	number_format($cantidad*$precio_venta,2,'.','');
	$descuento=number_format($row['descuento'],2,'.','');
	$subtotal=number_format($row['subtotal'] - $descuento ,2,'.','');
    $subtotal_general+=number_format($row['subtotal'] - $descuento,2,'.','');//Sumador subtotal general
	$total_descuento+=number_format($descuento,2,'.','');//Sumador total descuento
		?>
		<tr>
			<td class='text-center'><?php echo $codigo_producto;?></td>
			<td class='text-center'><?php echo $cantidad;?></td>
			<td><?php echo $nombre_producto;?></td>
			<td class='text-right'><?php echo $precio_venta;?></td>
			<td class='text-center'><?php echo $descuento;?></td>
			<td class='text-right'><?php echo $subtotal;?></td>
			<td>
			<a href="#" class='btn btn-danger btn-xs' onclick="eliminar('<?php echo $id_tmp; ?>')" title ="Eliminar item"><i class="glyphicon glyphicon-trash"></i></a></td>
		</tr>		

		<?php
}
?>

<tr class="info">
	<td class='text-right' colspan=3 >TOTAL: </td>
	<td class='text-right'><?php echo number_format($subtotal_general ,2,'.','');?></td>
	<td><input type="hidden" id="suma_factura" value="<?php echo number_format($subtotal_general ,2,'.','');?>"></td>
</tr>

</table>


