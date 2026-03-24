<?php
	/* Connect To Database*/
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];

	$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
	if($action == 'ajax'){
        //$id_detalle = mysqli_real_escape_string($con,(strip_tags($_REQUEST['id_detalle'], ENT_QUOTES)));
		$id_detalle =$_GET['id_detalle'];
		$sql="SELECT ps.nombre_producto as producto, df.cantidad_producto as cantidad_producto, df.precio_producto as precio_producto FROM detalle_facturas_en_bloque df, productos_servicios ps WHERE df.id_facturas_en_bloque = $id_detalle and df.codigo_producto = ps.codigo_producto";
		$query = mysqli_query($con, $sql);
	
			?>
			<div class="table-responsive">
			  <table class="table">
				<tr  class="info">
					<th>Producto</th>
					<th>Cantidad</th>
					<th>Precio</th>
				</tr>
				<?php
				while ($row=mysqli_fetch_array($query)){
					$nombre_producto=$row['producto'];
					$cantidad_producto=$row['cantidad_producto'];
					$precio_producto=number_format($row["precio_producto"],2,'.','');
					?>
					<tr>
						<td><?php echo $nombre_producto; ?></td>
						<td><?php echo $cantidad_producto; ?></td>
						<td><?php echo $precio_producto; ?></td>
					<?php
				}
				?>
			  </table>
			</div>
			<?php
		}
	
?>