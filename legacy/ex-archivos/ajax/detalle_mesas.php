<?php
include("../conexiones/conectalogin.php");
require_once("../helpers/helpers.php");
session_start();
$con = conenta_login();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

//para mostrar el detalle al mostrar la mesa
if ($action == 'reset_posiciones') {
	$elimina_posicion = mysqli_query($con, "DELETE FROM posiciones_mesas WHERE ruc_empresa='" . $ruc_empresa . "' ");
	echo "<script>
	$.notify('Posiciones reseteadas','info');
	</script>";
}

//para agregar una orden a la mesa
if ($action == 'agregar_orden' && isset($_GET['id_mesa'])) {
	ini_set('date.timezone', 'America/Guayaquil');
	$id_producto = mysqli_real_escape_string($con, (strip_tags($_GET['id_producto'], ENT_QUOTES)));
	$id_mesa = mysqli_real_escape_string($con, (strip_tags($_GET['id_mesa'], ENT_QUOTES)));
	$precio = mysqli_real_escape_string($con, (strip_tags($_GET['precio'], ENT_QUOTES)));
	$cantidad = mysqli_real_escape_string($con, (strip_tags($_GET['cantidad'], ENT_QUOTES)));
	$id_cliente = mysqli_real_escape_string($con, (strip_tags($_GET['id_cliente'], ENT_QUOTES)));
	$id_bodega = mysqli_real_escape_string($con, (strip_tags($_GET['bodega_agregar'], ENT_QUOTES)));
	$id_medida = mysqli_real_escape_string($con, (strip_tags($_GET['medida_agregar'], ENT_QUOTES)));
	$lote = mysqli_real_escape_string($con, (strip_tags($_GET['lote_agregar'], ENT_QUOTES)));
	$tipo_produccion = mysqli_real_escape_string($con, (strip_tags($_GET['tipo_producto_agregar'], ENT_QUOTES)));
	$serie = mysqli_real_escape_string($con, (strip_tags($_GET['serie_sucursal'], ENT_QUOTES)));
	$fecha_mesa = date('Y-m-d H:i:s', strtotime($_GET['fecha_mesa']));
	$fecha_agregado = date("Y-m-d H:i:s");
	$detalle_agregarse_mesa = mysqli_query($con, "INSERT INTO detalle_mesas VALUES (null, '" . $ruc_empresa . "', '" . $id_mesa . "', '" . $id_producto . "', '" . $precio . "', '" . $cantidad . "', '" . $id_cliente . "', '" . $fecha_mesa . "', '" . $precio * $cantidad . "','0', '" . $id_usuario . "','PENDIENTE', '" . $id_bodega . "','" . $id_medida . "','" . $lote . "','0','" . $tipo_produccion . "','" . $serie . "','0','" . $fecha_agregado . "','SI')");
	muestra_detalle_mesa($id_mesa);
}

//para eliminar un item de la mesa
if ($action == 'eliminar_orden_mesa' && isset($_GET['id_mesa'])) {
	$id_mesa = $_GET['id_mesa'];
	$id_detalle = $_GET['id_detalle'];
	$elimina_detalle = mysqli_query($con, "DELETE FROM detalle_mesas WHERE id_detalle_mesa='" . $id_detalle . "' and estado='PENDIENTE' and ruc_empresa ='" . $ruc_empresa . "'");
	muestra_detalle_mesa($id_mesa);
}
//para mostrar el detalle al mostrar la mesa
if ($action == 'muestra_detalle' && isset($_GET['id_mesa'])) {
	$id_mesa = $_GET['id_mesa'];
	muestra_detalle_mesa($id_mesa);
}

//para actualizar el descuento de cada item
if ($action == 'actualiza_descuento_item') {
	$descuento_item = $_POST['descuento_item'];
	$id = $_POST['id'];
	$id_mesa = $_POST['id_mesa'];
	$update = mysqli_query($con, "UPDATE detalle_mesas SET descuento='" . $descuento_item . "' WHERE id_detalle_mesa='" . $id . "'");
	muestra_detalle_mesa($id_mesa);
	echo "<script>
	$.notify('Descuento actualizado','info');
	</script>";
}


function muestra_detalle_mesa($id_mesa)
{
	$con = conenta_login();
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$id_usuario = $_SESSION['id_usuario'];
	$elimina_datos_error = mysqli_query($con, "DELETE FROM detalle_mesas WHERE id_usuario='0' ");
	$busca_detalle_mesa = mysqli_query($con, "SELECT * FROM detalle_mesas WHERE id_mesa = '" . $id_mesa . "' and estado = 'PENDIENTE' and ruc_empresa='" . $ruc_empresa . "'");

	$sql_propina = mysqli_query($con, "SELECT * from propina_restaurante_tmp where id_mesa = '" . $id_mesa . "'");
	$row_propina = mysqli_fetch_array($sql_propina);
	$total_propina = isset($row_propina['propina']) ? $row_propina['propina'] : 0;

?>
	<div class="modal-body">
		<div class="panel panel-info" style="margin-bottom: 5px; margin-top: -20px;">
			<div class="table-responsive">
				<table class="table table-hover">
					<tr class="info">
						<th style="padding: 2px;" class='col-sm-6'>Producto</th>
						<th style="padding: 2px;" class="text-right">Cantidad</th>
						<th style="padding: 2px;" class="text-right">Precio</th>
						<th style="padding: 2px;" class="text-right">Descuento</th>
						<th style="padding: 2px;" class="text-right">Subtotal</th>
						<th style="padding: 2px;" class="text-right">IVA</th>
						<th style="padding: 2px;" class="text-right">Total</th>
						<th style="padding: 2px;" class='text-right'>Opciones</th>
						<th style="padding: 2px;" class='text-right'>Facturar</th>
					</tr>
					<?php
					$sutotal_a_pagar = array();
					$total_a_pagar = array();
					$iva = array();
					while ($detalle_a_facturar = mysqli_fetch_array($busca_detalle_mesa)) {
						$id_detalle = $detalle_a_facturar['id_detalle_mesa'];
						$id_producto = $detalle_a_facturar['id_producto'];
						$cantidad = $detalle_a_facturar['cantidad'];
						$precio = $detalle_a_facturar['precio'];
						$descuento = $detalle_a_facturar['descuento'];
						$marcado = $detalle_a_facturar['marcado'];

						//buscar productos
						$busca_nombre_producto = mysqli_query($con, "SELECT * FROM productos_servicios WHERE id = '" . $id_producto . "' ");
						$row_productos = mysqli_fetch_array($busca_nombre_producto);
						$nombre_producto = $row_productos['nombre_producto'];
						$tarifa_iva = $row_productos['tarifa_iva'];

						//buscar tipos iva
						$busca_tarifa_iva = mysqli_query($con, "SELECT * FROM tarifa_iva WHERE codigo = '" . $tarifa_iva . "' ");
						$row_tarifa = mysqli_fetch_array($busca_tarifa_iva);
						$nombre_tarifa = $row_tarifa['tarifa'];
						$porcentaje_iva = $row_tarifa['porcentaje_iva'];

						$subtotal_item = number_format(abs(($cantidad * $precio - $descuento)), 2, '.', '');
						$iva_item = number_format(abs($subtotal_item * ($porcentaje_iva / 100)), 2, '.', '');

						$total_con_impuesto = number_format(abs($subtotal_item + $iva_item), 2, '.', '');

						if ($marcado == 'SI') {
							$sutotal_a_pagar[] = $total_con_impuesto;
							$iva[] = number_format((($cantidad * $precio) - $descuento) * ($porcentaje_iva / 100), 2, '.', '');
						}

					?>
						<input type="hidden" value="<?php echo $id_mesa; ?>" id="id_mesa_eliminada<?php echo $id_detalle; ?>">
						<input type="hidden" value="<?php echo $id_detalle; ?>" id="id_detalle_mesa<?php echo $id_detalle; ?>">
						<input type="hidden" value="<?php echo $marcado; ?>" id="estado_marcado<?php echo $id_detalle; ?>">
						<input type="hidden" id="descuento_inicial<?php echo $id_detalle; ?>" value="<?php echo $descuento; ?>">
						<tr>
							<td style="padding: 2px;" class='col-sm-6'><?php echo $nombre_producto; ?></td>
							<td align="right" style="padding: 2px;"><?php echo $cantidad; ?></td>
							<td align="right" style="padding: 2px;"><?php echo $precio; ?></td>
							<td align="right" style="padding: 2px;">
								<input type="text" style="text-align:right; height:20px;" class="form-control input-sm" title="Descuento" id="descuento_item<?php echo $id_detalle; ?>" onchange="descuento_item('<?php echo $id_detalle; ?>');" value="<?php echo $descuento; ?>">
							</td>
							<td align="right" style="padding: 2px;"><?php echo number_format($cantidad * $precio - $descuento, 2, '.', ''); ?></td>
							<td align="right" style="padding: 2px;"><?php echo $nombre_tarifa; ?></td>
							<td align="right" style="padding: 2px;"><?php echo $total_con_impuesto; ?></td>
							<?php
							if (getPermisos($con, $id_usuario, $ruc_empresa, 'ordenes')['d'] == 1) {
							?>
								<td align="center" style="padding: 2px;"><a href="#" class='btn btn-danger btn-xs' title='Eliminar' onclick="eliminar_orden('<?php echo $id_detalle; ?>')"><i class="glyphicon glyphicon-remove"></i></a></td>
							<?php
							} else {
							?>
								<td></td>
							<?php
							}
							if ($marcado == 'SI') {
							?>
								<td align="center" style="padding: 2px;"><input type="checkbox" onclick="editar_check_marcado('<?php echo $id_detalle; ?>');" name="por_facturar[<?php echo $id_detalle; ?>]" id="por_facturar[<?php echo $id_detalle; ?>]" value="<?php echo $id_detalle; ?>" title="Por facturar" checked></td>
							<?php
							} else {
							?>
								<td align="center" style="padding: 2px;"><input type="checkbox" onclick="editar_check_marcado('<?php echo $id_detalle; ?>');" name="por_facturar[<?php echo $id_detalle; ?>]" id="por_facturar[<?php echo $id_detalle; ?>]" value="<?php echo $id_detalle; ?>" title="Por facturar"></td>
							<?php
							}
							?>
						</tr>
					<?php
					}

					$total_a_pagar = number_format(array_sum($sutotal_a_pagar), 2, '.', ''); //+ array_sum($iva)
					if (isset($id_detalle)) {
					?>
						<tr class="info">
							<td colspan="9">
								<div class="form-group row" style="margin-bottom: 3px;">
									<div class="col-sm-3">
									</div>
									<div class="col-sm-3">
										<div class="input-group">
											<span class="input-group-addon"><b>Propina</b></span>
											<input class="form-control input-sm text-right" type="text" id="propina" name="propina" value="<?php echo number_format($total_propina, 2, '.', ''); ?>" onchange="aplica_propina('<?php echo $id_detalle ?>');" title="Ingrese el valor del servicio o propina y presione enter">
											<span class="input-group-btn"><button class="btn btn-default btn-sm" onclick="calcular_propina('<?php echo $id_detalle; ?>')" type="button" title="calcular 10%" value="<<"><span class="glyphicon glyphicon-record"></span></button></span>
										</div>
									</div>
									<input type="hidden" id="propina_calculada" name="propina_calculada" value="<?php echo number_format(array_sum($sutotal_a_pagar) * 0.10, 2, '.', ''); ?>">
									<input type="hidden" id="total_factura" name="total_factura" value="<?php echo number_format($total_a_pagar + $total_propina, 2, '.', ''); ?>">
									<div class="col-sm-3">
										<div class="input-group">
											<span class="input-group-addon"><b>Total</b></span>
											<b><input class="form-control input-sm text-right" value="<?php echo number_format($total_a_pagar + $total_propina, 2, '.', ''); ?>" readonly></b>
										</div>
									</div>
								</div>
							</td>
						</tr>
					<?php
					}
					?>
				</table>
			</div>
		</div>
	</div>
<?php
}

//para calcular el total en base a las selecciones de cada item
if ($action == 'editar_check_marcado' && isset($_GET['id_detalle'])) {
	$con = conenta_login();
	$id_detalle = $_GET['id_detalle'];
	$id_mesa = $_GET['id_mesa'];
	$estado_marcado = $_GET['estado_marcado'];

	if ($estado_marcado == 'SI') {
		$estado_marcado = 'NO';
	} else {
		$estado_marcado = 'SI';
	}
	$actualiza_detalle_mesa = mysqli_query($con, "UPDATE detalle_mesas SET marcado = '" . $estado_marcado . "' WHERE id_detalle_mesa = '" . $id_detalle . "'");
	if ($actualiza_detalle_mesa) {
		muestra_detalle_mesa($id_mesa);
	}
}

//para guardar la propina
if ($action == 'guardar_propina' && isset($_GET['id_mesa'])) {
	$con = conenta_login();
	$id_mesa = $_GET['id_mesa'];
	$propina = $_GET['propina'];

	$elimina_propina = mysqli_query($con, "DELETE FROM propina_restaurante_tmp WHERE id_mesa='" . $id_mesa . "' ");
	$guarda_propina = mysqli_query($con, "INSERT INTO propina_restaurante_tmp VALUES (null, '" . $id_mesa . "', '" . $propina . "' )");
	if ($guarda_propina) {
		muestra_detalle_mesa($id_mesa);
	} else {
		echo "<script>
		$.notify('No se ha guardado.','error');
		</script>";
	}
}

//para guardar la posicion
if ($action == 'guardar_posiciones_mesas' && isset($_GET['id_mesa'])) {
	$con = conenta_login();
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$id_mesa = $_GET['id_mesa'];
	$eje_x = $_GET['ejex'];
	$eje_y = $_GET['ejey'];

	if ($eje_x < 0) {
		$eje_x = 0;
	}

	$elimina_posicion = mysqli_query($con, "DELETE FROM posiciones_mesas WHERE id_mesa='" . $id_mesa . "' ");
	$guarda_posicion = mysqli_query($con, "INSERT INTO posiciones_mesas VALUES (null, '" . $ruc_empresa . "', '" . $id_mesa . "', '" . $eje_x . "' , '" . $eje_y . "')");
	echo "<script>
	$.notify('Mesa reubicada.','success');
	</script>";
}
?>