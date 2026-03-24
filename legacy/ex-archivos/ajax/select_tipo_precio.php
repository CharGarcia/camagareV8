<?php
include("../conexiones/conectalogin.php");
		$con = conenta_login();
		session_start();
		$ruc_empresa = $_SESSION['ruc_empresa'];

//para buscar los precios de cada producto
if (isset($_POST['id_producto'])){
	ini_set('date.timezone','America/Guayaquil');
			$serie=mysqli_real_escape_string($con,(strip_tags($_POST["serie_sucursal"],ENT_QUOTES)));
			$busca_info_sucursal = mysqli_query($con,"SELECT * FROM sucursales WHERE ruc_empresa = '".$ruc_empresa."' and serie = '".$serie."' ");
			$info_sucursal = mysqli_fetch_array($busca_info_sucursal);
			//$decimal_precio = $info_sucursal['decimal_doc'];
			$decimal_precio = $info_sucursal['decimal_doc']==0?2:$info_sucursal['decimal_doc'];

		$id_producto=mysqli_real_escape_string($con,(strip_tags($_POST["id_producto"],ENT_QUOTES)));
		//date("d-m-Y", strtotime($fecha_factura))
		$fecha_actual=date("Y-m-d H:i:s");
		$busca_precios = mysqli_query($con,"SELECT * FROM precios_productos WHERE id_producto='".$id_producto."' and DATE_FORMAT('".$fecha_actual."', '%Y/%m/%d') between DATE_FORMAT(fecha_desde, '%Y/%m/%d') and DATE_FORMAT(fecha_hasta, '%Y/%m/%d') order by detalle_precio asc");
		$busca_precio_normal = mysqli_query($con,"SELECT * FROM productos_servicios WHERE id='".$id_producto."' ");
		$row_precio_normal = mysqli_fetch_array($busca_precio_normal);
		//$fecha_desde= $row_precios['fecha_desde'];
		//$fecha_hasta= $row_precios['fecha_hasta'];
		?>
		<option value="0"selected>Precios</option>
		<option value="<?php echo $row_precio_normal['precio_producto'];?>">Normal <?php echo number_format($row_precio_normal['precio_producto'],$decimal_precio,'.','');?></option>
		<?php
		while ($row_precios = mysqli_fetch_array($busca_precios)){
			?>
			<option value="<?php echo $row_precios['precio'];?>"><?php echo $row_precios['detalle_precio']." ". number_format($row_precios['precio'],$decimal_precio,'.','');?></option>
			<?php
		}
}
?>