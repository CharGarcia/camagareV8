<?php
/* Connect To Database*/
include("../conexiones/conectalogin.php");

$con = conenta_login();
session_start();
$id_usuario = $_SESSION['id_usuario'];
$ruc_empresa = $_SESSION['ruc_empresa'];

if (isset($_POST['serie_liquidacion'])) {
	$serie_liquidacion = $_POST['serie_liquidacion'];
}
if (isset($_POST['codigo_item'])) {
	$codigo_item = $_POST['codigo_item'];
}
if (isset($_POST['detalle_item'])) {
	$detalle_item = $_POST['detalle_item'];
}
if (isset($_POST['cantidad_item'])) {
	$cantidad_item = $_POST['cantidad_item'];
}
if (isset($_POST['valuni_item'])) {
	$valuni_item = $_POST['valuni_item'];
}
if (isset($_POST['descuento_item'])) {
	$descuento_item = empty($_POST['descuento_item']) ? "0.00" : $_POST['descuento_item'];
}
if (isset($_POST['tipo_iva'])) {
	$tipo_iva = $_POST['tipo_iva'];
}
if (isset($_POST['secuencial_liquidacion'])) {
	$secuencial_liquidacion = $_POST['secuencial_liquidacion'];
}
if (isset($_POST['id_proveedor_lc'])) {
	$id_proveedor_lc = $_POST['id_proveedor_lc'];
}


//para saber los decimales que trabaja esta empresa
/* if (isset($_POST['serie_liquidacion'])) {
	$serie = $_POST['serie_liquidacion'];
	$busca_info_sucursal = "SELECT * FROM sucursales WHERE ruc_empresa = '" . $ruc_empresa . "' and serie = '" . $serie . "' ";
	$result_info_sucursal = $con->query($busca_info_sucursal);
	$info_sucursal = mysqli_fetch_array($result_info_sucursal);
	$decimal_precio = intval($info_sucursal['decimal_doc']);
	$decimal_cant = intval($info_sucursal['decimal_cant']);
	if ($decimal_cant == 1) {
		$decimal_cant = 0;
	} else {
		$decimal_cant = $decimal_cant;
	}
}

if (!isset($_POST['serie_liquidacion']) or empty($_POST['serie_liquidacion'])) {
	$decimal_precio = 2;
	$decimal_cant = 0;
} */

$decimal_precio = 2;
$decimal_cant = 2;

//para guardar en la liquidacion temporal
if (isset($codigo_item) && isset($detalle_item) && isset($cantidad_item) && isset($valuni_item) && isset($tipo_iva)) {
	$subtotal = number_format($cantidad_item * $valuni_item, 2, '.', '');
	$insert_tmp = mysqli_query($con, "INSERT INTO factura_tmp VALUES (null,'" . $codigo_item . "', '" . number_format($cantidad_item, 2, '.', '') . "', '" . number_format($valuni_item, 2, '.', '') . "', '" . number_format($descuento_item, 2, '.', '') . "' , '0', '" . $tipo_iva . "', '0',  '0', '" . $id_usuario . "', '0', '0',  '" . $detalle_item . "', '0', '" . number_format($subtotal, 2, '.', '') . "',  '" . $ruc_empresa . "')");
}

//para agregar un adicional al temporal de liquidaciones adicional
if (isset($_POST['agregar_adicional'])) {
	$concepto = $_POST['adicional_concepto'];
	$detalle = $_POST['adicional_descripcion'];
	$detalle_adicional_tmp = mysqli_query($con, "INSERT INTO adicional_tmp VALUES (null, '" . $id_usuario . "', '" . $serie_liquidacion . "', '" . $secuencial_liquidacion . "', '" . $concepto . "','" . $detalle . "')");
}

//para eliminar una fila de info adicional de la liquidacion
if (isset($_POST['id_adicional'])) {
	$id_info_adicional = intval($_POST['id_adicional']);
	$elimina_detalle_liquidacion = mysqli_query($con, "DELETE FROM adicional_tmp WHERE id_ad_tmp='" . $id_info_adicional . "'");
}


//para eliminar un iten de la liquidacion tmp
if (isset($_POST['id'])) {
	$id_tmp = intval($_POST['id']);
	$delete = mysqli_query($con, "DELETE FROM factura_tmp WHERE id='" . $id_tmp . "'");
}

?>
<div class="panel panel-info">
	<div class="table-responsive">
		<table class="table table-bordered">
			<tr class="info">
				<th class='text-center'>CÓDIGO</th>
				<th class='text-center'>CANT.</th>
				<th>DESCRIPCIÓN</th>
				<th class='text-right'>PRECIO UNIT.</th>
				<th class='text-center'>DESCUENTO</th>
				<th class='text-right'>TIPO IVA</th>
				<th class='text-right'>SUBTOTAL</th>
				<th class='text-right'>OPCIONES</th>
			</tr>
			<?php

			// PARA MOSTRAR LOS ITEMS DE LA liquidacion
			$subtotal_general = 0;
			$total_descuento = 0;
			$sql = mysqli_query($con, "SELECT ft.tarifa_iva as tarifa, ft.id as id_tmp, 
			ft.id_producto as codigo_producto, ft.cantidad_tmp as cantidad_tmp, 
			ft.lote as nombre_producto, ft.precio_tmp as precio_tmp, ft.descuento as descuento, ft.subtotal as subtotal 
			FROM factura_tmp as ft 
			WHERE ft.id_usuario = '" . $id_usuario . "' and ft.ruc_empresa = '" . $ruc_empresa . "' ");
			while ($row = mysqli_fetch_array($sql)) {
				$id_tmp = $row["id_tmp"];
				$codigo_producto = $row['codigo_producto'];
				$nombre_producto = $row['nombre_producto'];
				$cantidad = number_format($row['cantidad_tmp'], 2, '.', '');
				$precio_venta = number_format($row['precio_tmp'], 2, '.', '');
				$descuento = number_format($row['descuento'], 2, '.', '');
				$subtotal = number_format($row['subtotal'] - $descuento, 2, '.', '');
				$subtotal_general += $subtotal; //Sumador subtotal general
				$total_descuento += number_format($descuento, 2, '.', ''); //Sumador total descuento
				$tarifa = $row['tarifa'];
				//PARA MOStrar el nombre de la tarifa de iva
				$nombre_tarifa_iva = mysqli_query($con, "SELECT * from tarifa_iva WHERE codigo = '" . $tarifa . "'");
				$row_tarifa = mysqli_fetch_array($nombre_tarifa_iva);
				$nombre_tarifa = $row_tarifa['tarifa'];
			?>
				<tr>
					<td class='text-center'><?php echo strtoupper($codigo_producto); ?></td>
					<td class='text-center'><?php echo $cantidad; ?></td>
					<td><?php echo $nombre_producto; ?></td>
					<td class='text-right'><?php echo $precio_venta; ?></td>
					<td class='text-right'><?php echo $descuento; ?></td>
					<td class='text-right'><?php echo $nombre_tarifa; ?></td>
					<td class='text-right'><?php echo $subtotal; ?></td>
					<td class='text-right'>
						<a href="#" class='btn btn-danger btn-sm' onclick="eliminar_fila('<?php echo $id_tmp; ?>')" title="Eliminar item"><i class="glyphicon glyphicon-trash"></i></a>
					</td>
				</tr>
			<?php
			}
			?>
		</table>
	</div>
</div>

<!-- para mostrar los adicionales de la liquidacion -->
<div class="row">
	<div class="col-md-6">
		<div class="panel panel-info">
			<div class="panel-heading">Detalle de información adicional</div>
			<td><?php
				include("../ajax/muestra_adicional_liquidacion_tmp.php");
				$muestra_adicionales_liquidacion = muestra_adicionales_liquidacion($serie_liquidacion, $secuencial_liquidacion, $id_usuario, $con, $id_proveedor_lc);
				echo $muestra_adicionales_liquidacion; ?>
			</td>
		</div>
	</div>
	<!-- para mostrar los subtotales -->
	<div class="col-md-6">
		<div class="panel panel-info">
			<div class="table-responsive">
				<table class="table">
					<tr class="info">
						<td class='text-right'>SUBTOTAL GENERAL: </td>
						<td class='text-center'><?php echo number_format($subtotal_general, 2, '.', ''); ?></td>
						<td></td>
						<td></td>
					</tr>
					<?php

					//PARA MOSTRAR LOS NOMBRES DE CADA TARIFA DE IVA Y LOS VALORES DE CADA SUBTOTAL
					$sql_tarifas_iva = mysqli_query($con, "SELECT round(sum(ft.subtotal - ft.descuento),2) as suma_tarifa_iva, 
			ti.tarifa as tarifa FROM factura_tmp as ft INNER JOIN tarifa_iva as ti ON ti.codigo = ft.tarifa_iva 
			WHERE ft.id_usuario = '" . $id_usuario . "' and ft.ruc_empresa = '" . $ruc_empresa . "' group by ft.tarifa_iva ");
					while ($row = mysqli_fetch_array($sql_tarifas_iva)) {
						$nombre_tarifa_iva = strtoupper($row["tarifa"]);
						$subtotal_tarifa_iva = number_format($row['suma_tarifa_iva'], 2, '.', '');
					?>
						<tr class="info">
							<td class='text-right'>SUBTOTAL <?php echo ($nombre_tarifa_iva); ?>:</td>
							<td class='text-center'><?php echo number_format($subtotal_tarifa_iva, 2, '.', ''); ?></td>
							<td></td>
							<td></td>
						</tr>

					<?php
					}
					?>
					<tr class="info">
						<td class='text-right'>TOTAL DESCUENTO: </td>
						<td class='text-center'><?php echo number_format($total_descuento, 2, '.', ''); ?></td>
						<td></td>
						<td></td>
					</tr>
					<?php
					//PARA MOSTRAR LOS IVAS
					$total_iva = 0;
					$suma_iva = 0;
					$sql_iva = mysqli_query($con, "SELECT round(sum(ft.subtotal - ft.descuento),2) as suma_tarifa_iva, 
			ti.tarifa as tarifa, (ti.porcentaje_iva/100) as porcentaje FROM factura_tmp as ft 
			INNER JOIN tarifa_iva as ti ON ti.codigo = ft.tarifa_iva WHERE ft.id_usuario = '" . $id_usuario . "' and ft.ruc_empresa = '" . $ruc_empresa . "' 
			and ti.porcentaje_iva > 0 group by ft.tarifa_iva ");
					while ($row = mysqli_fetch_array($sql_iva)) {
						$nombre_porcentaje_iva = strtoupper($row['tarifa']);
						$total_iva =  $row['suma_tarifa_iva'] * $row['porcentaje'];
						$suma_iva += $total_iva;
					?>
						<tr class="info">
							<td class='text-right'>IVA <?php echo ($nombre_porcentaje_iva); ?>:</td>
							<td class='text-center'><?php echo number_format($total_iva, 2, '.', ''); ?></td>
							<td></td>
							<td></td>
						</tr>
					<?php
					}
					?>

					<tr class="info">
						<td class='text-right'>TOTAL: </td>
						<td class='text-center'><?php echo number_format($subtotal_general + $suma_iva, 2, '.', ''); ?></td>
						<td></td>
						<td><input type="hidden" id="suma_lc" value="<?php echo number_format($subtotal_general + $suma_iva, 2, '.', ''); ?>"></td>
					</tr>
				</table>
			</div>
		</div>
	</div>
</div>