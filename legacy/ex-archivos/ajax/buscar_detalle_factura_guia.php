<?php
/* Connect To Database*/
include("../conexiones/conectalogin.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];

//PARA AGREGAR DETALLE DE LA FACTURA
if (!empty($_POST['factura'])) {
	//elimina todos los datos de la temporal 
	$delete_factura_tmp = mysqli_query($con, "DELETE FROM factura_tmp WHERE id_usuario = '" . $id_usuario . "' and ruc_empresa = '" . $ruc_empresa . "'");

	$factura = mysqli_real_escape_string($con, (strip_tags($_POST['factura'], ENT_QUOTES)));
	$serie_factura = substr($factura, 0, 7);
	$secuencial_factura =  substr($factura, 8, 9);
	$decimal_cant = decimales($ruc_empresa, $serie_factura, $con);

	//comprobar si hay esa factura echa a ese cliente
	$busca_factura_de_cliente = mysqli_query($con, "SELECT * FROM encabezado_factura ef, clientes cl WHERE ef.ruc_empresa = '" . $ruc_empresa . "' and ef.serie_factura = '" . $serie_factura . "' and ef.secuencial_factura ='" . $secuencial_factura . "' and ef.id_cliente=cl.id");
	$resultado_encabezado = mysqli_fetch_array($busca_factura_de_cliente);
	$direccion_cliente = $resultado_encabezado['direccion'];
	$fecha_factura = $resultado_encabezado['fecha_factura'];
	$guia_remision = $resultado_encabezado['guia_remision'];
	$id_cliente = $resultado_encabezado['id'];
	$nombre_cliente = $resultado_encabezado['nombre'];

	//direccion de donde se emitio la factura de la sucursal
	$busca_datos_sucursal = mysqli_query($con, "SELECT * FROM sucursales WHERE ruc_empresa = '" . $ruc_empresa . "' and serie = '" . $serie_factura . "' ");
	$resultado_sucursal = mysqli_fetch_array($busca_datos_sucursal);
	$direccion_sucursal = $resultado_sucursal['direccion_sucursal'];

	$busca_detalle_factura = mysqli_query($con, "SELECT ps.id as id_producto, ps.codigo_producto as codigo, ps.nombre_producto as producto, cf.cantidad_factura as cantidad  FROM cuerpo_factura cf, productos_servicios ps WHERE cf.id_producto = ps.id and cf.ruc_empresa = '" . $ruc_empresa . "' and cf.serie_factura = '" . $serie_factura . "' and cf.secuencial_factura ='" . $secuencial_factura . "' ");
	while ($resultado_detalle_factura = mysqli_fetch_array($busca_detalle_factura)) {
		$id_producto = $resultado_detalle_factura['id_producto'];
		$cantidad = $resultado_detalle_factura['cantidad'];
		$insert_tmp = mysqli_query($con, "INSERT INTO factura_tmp VALUES (null,'" . $id_producto . "', '" . $cantidad . "','0','0','0','0','0','0','" . $id_usuario . "','0','0','0','0', 0 , '" . $ruc_empresa . "')");
	}
?>
	<input type="hidden" name="direccion_sucursal" id="direccion_sucursal" value="<?php echo $direccion_sucursal; ?>">
	<input type="hidden" name="direccion_cliente" id="direccion_cliente" value="<?php echo $direccion_cliente; ?>">
	<input type="hidden" name="id_cliente_factura" id="id_cliente_factura" value="<?php echo $id_cliente; ?>">
	<input type="hidden" name="nombre_cliente_guia" id="nombre_cliente_guia" value="<?php echo $nombre_cliente; ?>">
	<input type="hidden" name="fecha_factura" id="fecha_factura" value="<?php echo date("d-m-Y", strtotime($fecha_factura)); ?>">
	<input type="hidden" name="guia_remision_serie" id="guia_remision_serie" value="<?php echo substr($guia_remision, 0, 7); ?>">
	<input type="hidden" name="guia_remision_secuencial" id="guia_remision_secuencial" value="<?php echo substr($guia_remision, 8, 9); ?>">
	<input type="hidden" name="est_destino" id="est_destino" value="<?php echo substr($serie_factura, 0, 3); ?>">
<?php
}

//para cuando se cambia de cliente
if (isset($_POST['cambia_cliente'])) {
	//no se hace nada solo actualiza los datos del cliente
	$serie_guia = mysqli_real_escape_string($con, (strip_tags($_POST["serie_guia"], ENT_QUOTES)));
	$secuencial_guia = mysqli_real_escape_string($con, (strip_tags($_POST["secuencial_guia"], ENT_QUOTES)));
	$id_cliente = mysqli_real_escape_string($con, (strip_tags($_POST["id_cliente"], ENT_QUOTES)));
	$decimal_cant = decimales($ruc_empresa, $serie_guia, $con);
}

//para eliminar un iten de de la info adicional
if (isset($_POST['eliminar_info_adicional_gr'])) {
	$id_info_tmp = intval($_POST['id_info_gr']);
	$serie_guia = mysqli_real_escape_string($con, (strip_tags($_POST["serie_guia"], ENT_QUOTES)));
	$secuencial_guia = mysqli_real_escape_string($con, (strip_tags($_POST["secuencial_guia"], ENT_QUOTES)));
	$id_cliente = mysqli_real_escape_string($con, (strip_tags($_POST["id_cliente"], ENT_QUOTES)));
	$decimal_cant = decimales($ruc_empresa, $serie_guia, $con);
	$delete_info = mysqli_query($con, "DELETE FROM adicional_tmp WHERE id_ad_tmp='" . $id_info_tmp . "'");
}

//para agregar un info adicional al temporal de las guias
if (isset($_POST['agregar_info_adicional_gr'])) {
	$concepto = $_POST['adicional_concepto'];
	$detalle = $_POST['adicional_descripcion'];
	$serie_guia = mysqli_real_escape_string($con, (strip_tags($_POST["serie_guia"], ENT_QUOTES)));
	$secuencial_guia = mysqli_real_escape_string($con, (strip_tags($_POST["secuencial_guia"], ENT_QUOTES)));
	$id_cliente = mysqli_real_escape_string($con, (strip_tags($_POST["id_cliente"], ENT_QUOTES)));
	$decimal_cant = decimales($ruc_empresa, $serie_guia, $con);
	$detalle_adicional_tmp = mysqli_query($con, "INSERT INTO adicional_tmp VALUES (null, '" . $id_usuario . "', '" . $serie_guia . "', '" . $secuencial_guia . "', '" . $concepto . "','" . $detalle . "')");
}


//para eliminar un item de la factura tmp
if (isset($_GET['id_eliminar'])) {
	$id_tmp_eliminar = intval($_GET['id_eliminar']);
	$serie_guia = mysqli_real_escape_string($con, (strip_tags($_GET["serie_guia"], ENT_QUOTES)));
	$secuencial_guia = mysqli_real_escape_string($con, (strip_tags($_GET["secuencial_guia"], ENT_QUOTES)));
	$id_cliente = mysqli_real_escape_string($con, (strip_tags($_GET["id_cliente"], ENT_QUOTES)));
	$decimal_cant = decimales($ruc_empresa, $serie_guia, $con);
	$delete = mysqli_query($con, "DELETE FROM factura_tmp WHERE id='" . $id_tmp_eliminar . "'");
}


//para agregar un iten de la factura tmp
if (isset($_POST['ingreso_item']) && ($_POST['ingreso_item'] == 'ingreso_item')) {
	$id_producto = $_POST['id_producto'];
	$cant_producto = $_POST['cant_producto'];
	$serie_guia = mysqli_real_escape_string($con, (strip_tags($_POST["serie_guia"], ENT_QUOTES)));
	$secuencial_guia = mysqli_real_escape_string($con, (strip_tags($_POST["secuencial_guia"], ENT_QUOTES)));
	$id_cliente = mysqli_real_escape_string($con, (strip_tags($_POST["id_cliente"], ENT_QUOTES)));
	$decimal_cant = decimales($ruc_empresa, $serie_guia, $con);
	$insert_tmp = mysqli_query($con, "INSERT INTO factura_tmp VALUES (null,'" . $id_producto . "', '" . $cant_producto . "','0','0','0','0','0','0','" . $id_usuario . "','0','0','0','0', 0 , '" . $ruc_empresa . "')");
}


?>
<div class="panel panel-info">
	<div class="table-responsive">
		<table class="table table-hover">
			<tr class="info">
				<th>Cantidad</th>
				<th>Descripción</th>
				<th>Código principal</th>
				<th>Código auxiliar</th>
				<th class='text-right'>Eliminar</th>
			</tr>

			<?php
			//para mostrar el detalle desde el temporal
			$muestra_detalle_factura_tmp = mysqli_query($con, "SELECT ft.id as id_tmp, ps.id as id_producto, 
			ps.codigo_producto as codigo, ps.nombre_producto as producto, ft.cantidad_tmp as cantidad, ft.subtotal as subtotal 
			FROM factura_tmp as ft, productos_servicios as ps 
			WHERE ft.id_producto = ps.id and ft.id_usuario='" . $id_usuario . "' and ft.ruc_empresa='" . $ruc_empresa . "' ");
			while ($row = mysqli_fetch_array($muestra_detalle_factura_tmp)) {
				$id_tmp = $row["id_tmp"];
				$id_producto = $row['id_producto'];
				$codigo_producto_final = $row['codigo'];
				$producto_final = $row['producto'];
				$cantidad_final = $row['cantidad'];
			?>
				<tr>
					<td><?php echo number_format($cantidad_final, $decimal_cant, '.', ''); ?></td>
					<td><?php echo $producto_final; ?></td>
					<td><?php echo $codigo_producto_final; ?></td>
					<td><?php echo $codigo_producto_final; ?></td>
					<td class='text-right'><a href="#" class='btn btn-danger btn-sm' onclick="eliminar_iten_guia('<?php echo $id_tmp; ?>')" title="Eliminar item"><i class="glyphicon glyphicon-trash"></i></a></td>
				</tr>
			<?php
			}
			?>
		</table>
	</div>
</div>
<div class="row">
	<div class="container-fluid">
		<?php
		include("../ajax/muestra_adicional_gr_tmp.php");
		$muestra_adicionales_gr = muestra_adicionales_gr($serie_guia, $secuencial_guia, $id_usuario, $con, $id_cliente);
		echo $muestra_adicionales_gr; ?>
		<div id="detalle_info_adicional"></div>
	</div>
</div>

</div>

<?php
//para ver cuantos decimales
function decimales($ruc_empresa, $serie, $con)
{
	$busca_info_sucursal = "SELECT * FROM sucursales WHERE ruc_empresa = '" . $ruc_empresa . "' and serie = '" . $serie . "' ";
	$result_info_sucursal = $con->query($busca_info_sucursal);
	$info_sucursal = mysqli_fetch_array($result_info_sucursal);
	$decimal_cant = intval($info_sucursal['decimal_cant']);
	if ($decimal_cant == 1) {
		$decimal_cant = 0;
	} else {
		$decimal_cant = $decimal_cant;
	}

	return 	$decimal_cant;
}
?>