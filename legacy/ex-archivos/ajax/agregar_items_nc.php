<?php
/* Connect To Database*/
//include("../conexiones/conectalogin.php");
include("../ajax/facturas.php");
$con = conenta_login();
//session_start();
$id_usuario = $_SESSION['id_usuario'];
$ruc_empresa = $_SESSION['ruc_empresa'];
if (isset($_POST['id'])) {
	$id_cuerpo_factura = $_POST['id'];
}
if (isset($_POST['precio'])) {
	$precio_venta = $_POST['precio'];
}
if (isset($_POST['cantidad'])) {
	$cantidad = $_POST['cantidad'];
}
if (isset($_POST['descuento'])) {
	$descuento_nc = $_POST['descuento'];
}
if (isset($_POST['numero_factura'])) {

	$numero_factura = $_POST['numero_factura'];
	$serie_factura = substr($numero_factura, 0, 7);
	$secuencial_factura =  substr($numero_factura, 8, 9);
	$sql_encabezado_factura = mysqli_query($con, "SELECT * from encabezado_factura WHERE ruc_empresa='" . $ruc_empresa . "' and serie_factura ='" . $serie_factura . "' and secuencial_factura='" . $secuencial_factura . "'");
	$row_encabezado_factura = mysqli_fetch_array($sql_encabezado_factura);
	$id_encabezado_factura = isset($row_encabezado_factura['id_encabezado_factura']) ? $row_encabezado_factura['id_encabezado_factura'] : 0;
}

if (isset($_POST['id_producto'])) {
	$id_producto = $_POST['id_producto'];
}

//para saber los decimales_precio que trabaja esta empresa
if (isset($_POST['serie_factura_nc'])) {
	$serie_sucursal = $_POST['serie_factura_nc'];
	$busca_info_sucursal = "SELECT * FROM sucursales WHERE ruc_empresa = '" . $ruc_empresa . "' and serie = '" . $serie_sucursal . "' ";
	$result_info_sucursal = $con->query($busca_info_sucursal);
	$info_sucursal = mysqli_fetch_array($result_info_sucursal);
	$decimales_precio = intval($info_sucursal['decimal_doc']);

	$decimal_cant = intval($info_sucursal['decimal_cant']);
	if ($decimal_cant == 1) {
		$decimal_cant = 0;
	} else {
		$decimal_cant = $decimal_cant;
	}
}

if (!isset($_POST['serie_factura_nc']) or empty($_POST['serie_factura_nc'])) {
	$decimales_precio = 2;
	$decimal_cant = 0;
}

if (!empty($id_cuerpo_factura)) {
	//traer INFO de cuerpo factura
	$sql_cuerpo_factura = mysqli_query($con, "SELECT * from cuerpo_factura WHERE id_cuerpo_factura='" . $id_cuerpo_factura . "'");
	$row_cuerpo_factura = mysqli_fetch_array($sql_cuerpo_factura);
	$lote = $row_cuerpo_factura['lote'];
	$vencimiento = $row_cuerpo_factura['vencimiento'];
	$id_bodega = $row_cuerpo_factura['id_bodega'];
	$id_medida = $row_cuerpo_factura['id_medida_salida'];
	$tarifa_iva = $row_cuerpo_factura['tarifa_iva'];


	//busca detalles del producto
	$sql_detalle_producto = mysqli_query($con, "SELECT * from productos_servicios WHERE id = '" . $id_producto . "' ");
	$row_detalle_producto = mysqli_fetch_array($sql_detalle_producto);
	$tipo_produccion = $row_detalle_producto['tipo_produccion'];
	$tarifa_ice = $row_detalle_producto['tarifa_ice'];
	$tarifa_botellas = $row_detalle_producto['tarifa_botellas'];


	//busca en encabezado de nc, que nc tiene esta factura que le estamos aplicando la nc
	$sql_factura_modificada = mysqli_query($con, "SELECT * FROM encabezado_nc WHERE factura_modificada = '" . $numero_factura . "' and ruc_empresa = '" . $ruc_empresa . "' ");
	$count = mysqli_num_rows($sql_factura_modificada);

	if ($count > 0) {
		$row_factura_modificada = mysqli_fetch_array($sql_factura_modificada);
		$serie_nc_modifica = $row_factura_modificada['serie_nc'];
		$secuencial_nc_modifica = $row_factura_modificada['secuencial_nc'];

		$sql_subtotal = mysqli_query($con, "SELECT sum(subtotal_nc) as subtotal_nc FROM cuerpo_nc WHERE id_producto = '" . $id_cuerpo_factura . "' and ruc_empresa = '" . $ruc_empresa . "' and serie_nc ='" . $serie_nc_modifica . "' and secuencial_nc= '" . $secuencial_nc_modifica . "'");
		$row_subtotal = mysqli_fetch_array($sql_subtotal);
		$subtotal_factura_nc = $row_subtotal['subtotal_nc'];
	} else {
		$subtotal_factura_nc = 0;
	}
	//para guardar en la nc temporal
	$subtotal = number_format($cantidad * $precio_venta, 2, '.', '');
	$insert_tmp = mysqli_query($con, "INSERT INTO factura_tmp VALUES ('" . $id_cuerpo_factura . "', '" . $id_producto . "', '" . number_format($cantidad, $decimal_cant, '.', '') . "','" . number_format($precio_venta, $decimales_precio, '.', '') . "','" . $descuento_nc . "','" . $tipo_produccion . "','" . $tarifa_iva . "','" . $tarifa_ice . "','" . $tarifa_botellas . "','" . $id_usuario . "','" . $id_bodega . "','" . $id_medida . "','" . $lote . "','" . $vencimiento . "','" . $subtotal . "','" . $ruc_empresa . "')");
}

//aqui para agregar manualmente productos a la nc
if (isset($_POST['agregar_item_manual']) && ($_POST['agregar_item_manual'] == 'agregar_item_manual')) {
	$id_producto = $_POST['id_agregar'];
	$cantidad_agregar = $_POST['cantidad_agregar'];
	$precio_agregar = $_POST['precio_agregar'];
	$descuento_agregar = $_POST['descuento_agregar'];

	//busca detalles del producto
	$sql_detalle_producto = mysqli_query($con, "SELECT * FROM productos_servicios WHERE id = '" . $id_producto . "' ");
	$row_detalle_producto = mysqli_fetch_array($sql_detalle_producto);
	$tipo_produccion = $row_detalle_producto['tipo_produccion'];
	$tarifa_iva = $row_detalle_producto['tarifa_iva'];
	$tarifa_ice = $row_detalle_producto['tarifa_ice'];
	$tarifa_botellas = $row_detalle_producto['tarifa_botellas'];
	$subtotal = number_format($cantidad_agregar * $precio_agregar, 2, '.', '');
	$insert_manual_tmp = mysqli_query($con, "INSERT INTO factura_tmp VALUES (null, '" . $id_producto . "', '" . $cantidad_agregar . "','" . $precio_agregar . "','" . $descuento_agregar . "','" . $tipo_produccion . "','" . $tarifa_iva . "','" . $tarifa_ice . "','" . $tarifa_botellas . "','" . $id_usuario . "','0','0','0','0','" . $subtotal . "', '" . $ruc_empresa . "' )");
}

//para eliminar un iten de la nota de credito
if (isset($_POST['id_eliminar'])) {
	$id_tmp = intval($_POST['id_eliminar']);
	$delete = mysqli_query($con, "DELETE FROM factura_tmp WHERE id='" . $id_tmp . "'");
}
?>
<div class="panel panel-info">
	<div class="table-responsive">
		<table class="table">
			<tr class="info">
				<th class='text-center'>CÓDIGO</th>
				<th class='text-center'>CANT.</th>
				<th>DESCRIPCIÓN</th>
				<th class='text-right'>PRECIO</th>
				<th class='text-center'>DESCUENTO</th>
				<th class='text-right'>SUBTOTAL</th>
				<th class='text-right'>OPCIONES</th>
			</tr>
			<?php
			// PARA MOSTRAR LOS ITEMS DE LA FACTURA cuando se trae los datos desde una factura 

			$subtotal_general = 0;
			$total_descuento = 0;
			$sql_detalle_nc = mysqli_query($con, "SELECT ft.id_producto as id_producto_tmp, 
			ft.id as id_tmp, ps.codigo_producto as codigo_producto, ft.cantidad_tmp as cantidad_tmp, 
			ps.nombre_producto as nombre_producto, ft.precio_tmp as precio_tmp, ft.descuento as descuento, ft.subtotal as subtotal 
			FROM factura_tmp as ft, productos_servicios as ps 
			WHERE ft.id_usuario = '" . $id_usuario . "' and ft.ruc_empresa = '" . $ruc_empresa . "' 
			and ft.id_producto = ps.id and ps.ruc_empresa='" . $ruc_empresa . "'");
			while ($row = mysqli_fetch_array($sql_detalle_nc)) {
				$id_tmp = $row["id_tmp"];
				$id_producto_tmp = $row["id_producto_tmp"];
				$codigo_producto = $row['codigo_producto'];
				$nombre_producto = $row['nombre_producto'];
				$cantidad = number_format($row['cantidad_tmp'], $decimal_cant, '.', '');

				$precio_venta = number_format($row['precio_tmp'], $decimales_precio, '.', '');
				$descuento = number_format($row['descuento'], 2, '.', '');
				$subtotal = number_format($row['subtotal'] - $descuento, 2, '.', '');
			?>
				<tr>
					<td class='text-center'><?php echo strtoupper($codigo_producto); ?></td>
					<td class='text-center'><?php echo $cantidad; ?></td>
					<td><?php echo $nombre_producto; ?></td>
					<td class='text-right'><?php echo $precio_venta; ?></td>
					<td class='text-center'><?php echo $descuento; ?></td>
					<td class='text-right'><?php echo $subtotal; ?></td>
					<td class='text-right'>
						<a href="#" class='btn btn-danger btn-sm' onclick="eliminar_item_nc('<?php echo $id_tmp; ?>')" title="Eliminar item"><i class="glyphicon glyphicon-trash"></i></a>
					</td>
				</tr>
			<?php
			}


			$sql_subtotales = mysqli_query($con, "SELECT round(sum(subtotal - descuento),2) as subtotal, 
			round(sum(descuento),2) as descuento FROM factura_tmp WHERE id_usuario = '" . $id_usuario . "' 
			and ruc_empresa = '" . $ruc_empresa . "' group by id_usuario ");
			$row_subtotales = mysqli_fetch_array($sql_subtotales);
			$subtotal_general = isset($row_subtotales['subtotal']) ? $row_subtotales['subtotal'] : 0;
			$total_descuento = isset($row_subtotales['descuento']) ? $row_subtotales['descuento'] : 0;

			?>
			<tr class="info">
				<td class='text-right' colspan="5">SUBTOTAL GENERAL: </td>
				<td class='text-right'><?php echo number_format($subtotal_general, 2, '.', ''); ?></td>
				<td></td>
			</tr>
			<?php
			//PARA MOSTRAR LOS NOMBRES DE CADA TARIFA DE IVA Y LOS VALORES DE CADA SUBTOTAL
			$subtotal_tarifa_iva = 0;
			$sql_tarifas_iva = mysqli_query($con, "SELECT round(sum(ft.subtotal - ft.descuento),2) as suma_tarifa_iva, 
			ti.tarifa as tarifa FROM factura_tmp as ft INNER JOIN tarifa_iva as ti ON ti.codigo = ft.tarifa_iva 
			WHERE ft.id_usuario = '" . $id_usuario . "' and ft.ruc_empresa = '" . $ruc_empresa . "' group by ft.tarifa_iva ");

			while ($row = mysqli_fetch_array($sql_tarifas_iva)) {
				$nombre_tarifa_iva = strtoupper($row["tarifa"]);
				$subtotal_tarifa_iva = $row['suma_tarifa_iva'];
			?>
				<tr class="info">
					<td class='text-right' colspan="5">SUBTOTAL <?php echo ($nombre_tarifa_iva); ?>:</td>
					<td class='text-right'><?php echo number_format($subtotal_tarifa_iva, 2, '.', ''); ?></td>
					<td></td>
				</tr>

			<?php
			}
			?>
			<tr class="info">
				<td class='text-right' colspan="5">TOTAL DESCUENTO: </td>
				<td class='text-right'><?php echo number_format($total_descuento, 2, '.', ''); ?></td>
				<td></td>
			</tr>
			<?php
			//PARA MOSTRAR LOS IVAS
			$total_iva = 0;
			$suma_iva = 0;
			$sql_iva = mysqli_query($con, "SELECT round(sum(ft.subtotal - ft.descuento),2) as suma_tarifa_iva, 
			ti.tarifa as tarifa, (ti.porcentaje_iva/100) as porcentaje FROM factura_tmp as ft 
			INNER JOIN tarifa_iva as ti ON ti.codigo = ft.tarifa_iva 
			WHERE ft.id_usuario = '" . $id_usuario . "' and ft.ruc_empresa = '" . $ruc_empresa . "' 
			and ti.porcentaje_iva > 0 group by ft.tarifa_iva ");

			while ($row = mysqli_fetch_array($sql_iva)) {
				$nombre_porcentaje_iva = strtoupper($row['tarifa']);
				$total_iva =  $row['suma_tarifa_iva'] * $row['porcentaje'];
				$suma_iva += $total_iva;
			?>
				<tr class="info">
					<td class='text-right' colspan="5">IVA <?php echo ($nombre_porcentaje_iva); ?>:</td>
					<td class='text-right'><?php echo number_format($total_iva, 2, '.', ''); ?></td>
					<td></td>
				</tr>

			<?php
			}
			?>
			<tr class="info">
				<td class='text-right' colspan="5">TOTAL: </td>
				<td class='text-right'><?php echo number_format($subtotal_general + $suma_iva, 2, '.', ''); ?></td>
				<td><input type="hidden" id="suma_nc" value="<?php echo number_format($subtotal_general + $suma_iva, 2, '.', ''); ?>"></td>
			</tr>
			<tr class="danger">
				<td class='text-right' colspan="5"><b>SALDO FACTURA (-) NOTA DE CRÉDITO: </b></td>
				<td class='text-right'><b><?php echo number_format(saldo_factura($con, $ruc_empresa, $id_encabezado_factura) - ($subtotal_general + $total_iva), 2, '.', ''); ?></b></td>
				<td>(Informativo)</td>
			</tr>
		</table>
	</div>
</div>