<?php
/* Connect To Database*/
include("../conexiones/conectalogin.php");
include("../helpers/helpers.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];

//PARA BUSCAR LAS FACTURAS de ventas	
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
//$tipo_reporte = $_POST['action'];
$id_proveedor = $_POST['id_proveedor'];
$desde = $_POST['desde'];
$hasta = $_POST['hasta'];
ini_set('date.timezone', 'America/Guayaquil');

if ($action == '1' || $action == '2') {

	if ($action == '1') {
		$resultado = reporte_compras($con, $ruc_empresa, $desde, $hasta, $id_proveedor, '1');
	}
	if ($action == '2') {
		$resultado = reporte_compras($con, $ruc_empresa, $desde, $hasta, $id_proveedor, '2');
	}

?>
	<div class="panel panel-info">
		<div class="table-responsive">
			<table class="table table-hover">
				<tr class="info">
					<th>Fecha</th>
					<th>Proveedor</th>
					<th>Ruc</th>
					<th>Documento</th>
					<th>Número</th>
					<th>Subtotal</th>
					<th>Descuento</th>
					<th>IVA</th>
					<th>Propina</th>
					<th>Otros</th>
					<th>Total</th>
				</tr>
				<?php
				$total_compra = 0;
				$suma_total = 0;
				$suma_subtotal = 0;
				$suma_descuento = 0;
				$suma_propina = 0;
				$suma_otros_val = 0;
				$suma_iva = 0;

				while ($row = mysqli_fetch_array($resultado)) {
					$fecha_compra = $row['fecha_compra'];
					$numero_documento = $row['documento'];
					$nombre_proveedor = $row['proveedor'];
					$total_compra = $row['total_compra'];
					$ruc_proveedor = $row['ruc_proveedor'];
					$tipo_documento = $row['comprobante'];
					$subtotal = $row['subtotal'];
					$descuento = $row['descuento'];
					$iva = $row['iva'];
					$otros_val = $row['otros_val'];
					$propina = $row['propina'];
				?>
					<tr>
						<td><?php echo date("d-m-Y", strtotime($fecha_compra)); ?></td>
						<td><?php echo $nombre_proveedor; ?></td>
						<td><?php echo $ruc_proveedor; ?></td>
						<td><?php echo $tipo_documento; ?></td>
						<td><?php echo $numero_documento; ?></td>

						<td class="text-right"><?php echo number_format($subtotal, 2, '.', ''); ?></td>
						<td class="text-right"><?php echo number_format($descuento, 2, '.', ''); ?></td>
						<td class="text-right"><?php echo number_format($iva, 2, '.', ''); ?></td>
						<td class="text-right"><?php echo number_format($propina, 2, '.', ''); ?></td>
						<td class="text-right"><?php echo number_format($otros_val, 2, '.', ''); ?></td>
						<td class="text-right"><?php echo number_format($total_compra, 2, '.', ''); ?></td>
					</tr>
				<?php
					$suma_subtotal += $subtotal;
					$suma_iva += $iva;
					$suma_descuento += $descuento;
					$suma_propina += $propina;
					$suma_otros_val += $otros_val;
					$suma_total += $total_compra;
				}
				?>
				<tr class="info">
					<th colspan="4">Totales</th>
					<td><span id="loader_excel"></span></td>
					<td class="text-right"><?php echo number_format($suma_subtotal, 2, '.', ''); ?></td>
					<td class="text-right"><?php echo number_format($suma_descuento, 2, '.', ''); ?></td>
					<td class="text-right"><?php echo number_format($suma_iva, 2, '.', ''); ?></td>
					<td class="text-right"><?php echo number_format($suma_propina, 2, '.', ''); ?></td>
					<td class="text-right"><?php echo number_format($suma_otros_val, 2, '.', ''); ?></td>
					<td class="text-right"><?php echo number_format($suma_total, 2, '.', ''); ?></td>
				</tr>

			</table>
		</div>
	</div>
<?php
}


// para buscar las compras en detalle
if ($action == '3' || $action == '4') {
	if ($action == '3') {
		$resultado = detalle_compras($con, $ruc_empresa, $id_proveedor, $desde, $hasta, '3');
	}
	if ($action == '4') {
		$resultado = detalle_compras($con, $ruc_empresa, $id_proveedor, $desde, $hasta, '4');
	}
?>
	<div class="panel panel-info">
		<div class="table-responsive">
			<table class="table table-hover">
				<tr class="info">
					<td>Fecha</td>
					<td>Proveedor</td>
					<td>Documento</td>
					<td>Número</td>
					<td>Código</td>
					<td>Detalle</td>
					<td>Tarifa</td>
					<td>Cantidad</td>
					<td>Valor Uni.</td>
					<td>Descuento</td>
					<td>Subtotal</td>
					<td>IVA</td>
					<td>Total</td>
				</tr>
				<?php
				$suma_total_compra = 0;
				$suma_cantidad = 0;
				$suma_valor_unitario = 0;
				$suma_subtotal = 0;
				$suma_descuento = 0;
				$suma_iva = 0;

				while ($row = mysqli_fetch_array($resultado)) {
					$fecha_compra = $row['fecha_compra'];
					$comprobante = $row['comprobante'];
					$numero_documento = $row['documento'];
					$proveedor = $row['proveedor'];
					$ruc_proveedor = $row['ruc_proveedor'];
					$cantidad = $row['cantidad'];
					$suma_cantidad += $cantidad;
					$producto = $row['detalle_producto'];
					$codigo = $row['codigo_producto'];
					$precio = $row['precio'];
					$suma_valor_unitario += $precio;
					$descuento = $row['descuento'];
					$suma_descuento += $row['descuento'];
					$subtotal = $row['subtotal'];
					$suma_subtotal += $row['subtotal'];
					$suma_iva += $row['total_iva'];
					$tarifa_iva = $row['tarifa'];
					$total_compra = number_format(($row['subtotal'] + $row['total_iva']), 2, '.', '');
					$suma_total_compra += $total_compra;
				?>
					<tr>
						<td><?php echo date("d/m/Y", strtotime($fecha_compra)); ?></td>
						<td><?php echo $proveedor; ?></td>
						<td><?php echo $comprobante; ?></td>
						<td><?php echo $numero_documento; ?></td>
						<td><?php echo $codigo ?></td>
						<td><?php echo $producto ?></td>
						<td><?php echo $tarifa_iva ?></td>
						<td><?php echo $cantidad ?></td>
						<td><?php echo number_format($precio, 4, '.', '') ?></td>
						<td><?php echo number_format($descuento, 2, '.', '') ?></td>
						<td><?php echo  number_format($subtotal, 2, '.', '') ?></td>
						<td><?php echo  number_format($row['total_iva'], 2, '.', '') ?></td>
						<td><?php echo number_format($total_compra, 2, '.', '') ?></td>
					</tr>
				<?php
				}
				?>
				<tr class="info">
					<td colspan="7" class='text-right'>Totales</td>
					<td><?php echo number_format($suma_cantidad, 2, '.', '') ?></td>
					<td><?php echo number_format($suma_valor_unitario, 2, '.', '') ?></td>
					<td><?php echo number_format($suma_descuento, 2, '.', '') ?></td>
					<td><?php echo number_format($suma_subtotal, 2, '.', '') ?></td>
					<td><?php echo number_format($suma_iva, 2, '.', '') ?></td>
					<td><?php echo number_format($suma_total_compra, 2, '.', '') ?></td>
				</tr>
			</table>
		</div>
	</div>
<?php
}


function reporte_compras($con, $ruc_empresa, $desde, $hasta, $id_proveedor, $documentos)
{
	if (empty($id_proveedor)) {
		$condicion_proveedor = "";
	} else {
		$condicion_proveedor = " and enc_com.id_proveedor=" . $id_proveedor;
	}

	if (($documentos == "1")) {
		$condicion_documento = " and enc_com.id_comprobante !=4"; //para todos las facturas y demas excepto nc
	}
	if (($documentos == "2")) {
		$condicion_documento = " and enc_com.id_comprobante = 4"; //solo para notas de credito
	}


	$resultado = mysqli_query($con, "SELECT enc_com.fecha_compra as fecha_compra, pro.razon_social as proveedor, 
	pro.ruc_proveedor as ruc_proveedor, com_aut.comprobante as comprobante, enc_com.numero_documento as documento,
	round(sum(cue_com.subtotal),2) as subtotal, sum(cue_com.descuento) as descuento, round(sum(cue_com.subtotal * (tar.porcentaje_iva /100)),2) as iva,
	enc_com.propina as propina, enc_com.otros_val as otros_val, enc_com.total_compra as total_compra, enc_com.id_sustento as id_sustento,
	enc_com.id_proveedor as id_proveedor, enc_com.codigo_documento as codigo_documento, enc_com.deducible_en as deducible_en,
	enc_com.aut_sri as aut_sri, enc_com.tipo_comprobante as tipo_comprobante, enc_com.id_encabezado_compra as id_compra
	FROM cuerpo_compra as cue_com 
	INNER JOIN encabezado_compra as enc_com ON enc_com.codigo_documento=cue_com.codigo_documento 
	INNER JOIN tarifa_iva as tar ON tar.codigo=cue_com.det_impuesto 
	LEFT JOIN proveedores as pro ON pro.id_proveedor=enc_com.id_proveedor 
	LEFT JOIN comprobantes_autorizados as com_aut ON com_aut.id_comprobante=enc_com.id_comprobante 
	WHERE enc_com.ruc_empresa = '" . $ruc_empresa . "' and cue_com.ruc_empresa = '" . $ruc_empresa . "' 
	and DATE_FORMAT(enc_com.fecha_compra, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
	and '" . date("Y/m/d", strtotime($hasta)) . "' $condicion_proveedor $condicion_documento 
	group by enc_com.codigo_documento order by enc_com.fecha_compra asc");
	return $resultado;
}


function detalle_compras($con, $ruc_empresa, $id_proveedor, $desde, $hasta, $documentos)
{
	if (empty($id_proveedor)) {
		$condicion_proveedor = "";
	} else {
		$condicion_proveedor = " and enc_com.id_proveedor=" . $id_proveedor;
	}

	if (($documentos == "3")) {
		$condicion_documento = " and enc_com.id_comprobante !=4"; //para todos las facturas y demas excepto nc
	}
	if (($documentos == "4")) {
		$condicion_documento = " and enc_com.id_comprobante = 4"; //solo para notas de credito
	}

	$resultado = mysqli_query($con, "SELECT enc_com.fecha_compra as fecha_compra, pro.razon_social as proveedor, 
	pro.ruc_proveedor as ruc_proveedor, com_aut.comprobante as comprobante, enc_com.numero_documento as documento,
	cue_com.subtotal as subtotal, cue_com.descuento as descuento, round(cue_com.subtotal * (tar.porcentaje_iva /100),2) as total_iva,
	enc_com.propina as propina, enc_com.otros_val as otros_val, enc_com.total_compra as total_compra, cue_com.codigo_producto as codigo_producto,
	cue_com.detalle_producto as detalle_producto, cue_com.cantidad as cantidad, cue_com.precio as precio, tar.tarifa as tarifa
				FROM cuerpo_compra as cue_com 
				INNER JOIN encabezado_compra as enc_com ON enc_com.codigo_documento=cue_com.codigo_documento 
				LEFT JOIN proveedores as pro ON pro.id_proveedor=enc_com.id_proveedor 
				INNER JOIN tarifa_iva as tar ON tar.codigo=cue_com.det_impuesto
				LEFT JOIN comprobantes_autorizados as com_aut ON com_aut.id_comprobante=enc_com.id_comprobante
				WHERE enc_com.ruc_empresa='" . $ruc_empresa . "' and cue_com.ruc_empresa='" . $ruc_empresa . "' 
				and DATE_FORMAT(enc_com.fecha_compra, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "'
				 $condicion_proveedor $condicion_documento order by enc_com.fecha_compra asc");
	return $resultado;
}
?>