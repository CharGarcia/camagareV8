<?php
require_once("../ajax/buscar_ultima_factura.php");
require_once("../ajax/pagination.php");
require_once("../helpers/helpers.php");
$con = conenta_login();
//session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
ini_set('date.timezone', 'America/Guayaquil');
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';


if ($action == 'anular_cotizacion') {
	$id = intval($_POST['id']);
	$update = mysqli_query($con, "UPDATE encabezado_cotizacion_publicidad SET status ='0' WHERE id='" . $id . "'");
	if ($update) {
		echo "<script>$.notify('Registro anulado.','success')</script>";
	} else {
		echo "<script>$.notify('Lo siento algo ha salido mal intenta nuevamente.','error')</script>";
	}
}

if ($action == 'informacion_facturar_cotizacion') {
	$id = $_GET['id'];
	$serie_factura = $_GET['serie_factura'];

	//para traer el encabezado
	$sql = mysqli_query($con, "SELECT * FROM encabezado_cotizacion_publicidad WHERE id ='" . $id . "' ");
	$info_encabezado = mysqli_fetch_array($sql);

	$subtotal_cotizacion = 0;
	$suma_subtotal = 0;
	$total_iva = 0;
	$suma_iva = 0;
	$comision = 0;
	$suma_comision = 0;
	$suma_costo = 0;
	$valor_costo = 0;
	$total_final = 0;
	$subtotalYcomision = 0;
	$sumasubtotalYcomision = 0;
	$info_detalle_valores = mysqli_query($con, "SELECT * FROM cuerpo_cotizacion_publicidad as cue INNER JOIN 
	encabezado_cotizacion_publicidad as enc ON enc.id=cue.id_encabezado_cotizacion 
	INNER JOIN tarifa_iva as tar ON tar.id=enc.tipo_iva WHERE cue.id_encabezado_cotizacion = '" . $id . "' ");
	while ($detalle_cotizacion = mysqli_fetch_assoc($info_detalle_valores)) {
		$subtotal_cotizacion =  ($detalle_cotizacion['precio'] * $detalle_cotizacion['cantidad']);
		$comision = $subtotal_cotizacion * ($detalle_cotizacion['comision'] / 100);
		$subtotalYcomision = $subtotal_cotizacion + $comision;
		$sumasubtotalYcomision += $subtotalYcomision;
		$suma_comision += $comision;
		$valor_costo = $detalle_cotizacion['valor_costo'];
		$suma_costo += $valor_costo;
		$porcentaje_iva = $detalle_cotizacion['porcentaje_iva'] / 100;
		$total_iva =  ($subtotalYcomision) * $porcentaje_iva;
		$suma_iva += $total_iva;
		$suma_subtotal += $subtotal_cotizacion;
		$total_final += $subtotalYcomision + $total_iva;
	}


	$numero_factura = siguiente_documento($con, $ruc_empresa, $serie_factura);

	$data = array(
		'id' => $id,
		'id_cliente' => $info_encabezado['id_cliente'],
		'fecha_factura' => date("d-m-Y", strtotime($info_encabezado['fecha_factura'] = '0000-00-00' ? $info_encabezado['fecha'] : $info_encabezado['fecha_factura'])),
		'serie_factura' => $info_encabezado['serie_factura'] == "" ? $serie_factura : $info_encabezado['serie_factura'],
		'numero_factura' => $info_encabezado['numero_factura'] == 0 ? $numero_factura : $info_encabezado['numero_factura'],
		'estado_factura' => $info_encabezado['estado_factura'] == "" ? "Pendiente" : strtoupper($info_encabezado['estado_factura']),
		'codigo_servicio' => $info_encabezado['codigo_servicio'],
		'descripcion_servicio' => $info_encabezado['descripcion_servicio'],
		'cantidad_factura' => $info_encabezado['cantidad_factura'] == 0 ? 1 : $info_encabezado['cantidad_factura'],
		'precio_factura' => $info_encabezado['precio_factura'] == 0 ? number_format($sumasubtotalYcomision, 2, '.', '') : $info_encabezado['precio_factura'],
		'subtotal_factura' => number_format($sumasubtotalYcomision, 2, '.', ''),
		'iva_factura' => number_format($suma_iva, 2, '.', ''),
		'total_factura' => number_format($total_final, 2, '.', ''),
		'id_iva' => $info_encabezado['tipo_iva'],
		'numero_cotizacion_publicidad' => str_pad($info_encabezado['numero'], 3, '0', STR_PAD_LEFT) . '-' . date("Y", strtotime($info_encabezado['fecha']))
	);


	if ($sql) {
		$arrResponse = array("status" => true, "data" => $data);
	} else {
		$arrResponse = array("status" => false, "msg" => 'Datos no encontrados');
	}

	echo json_encode($arrResponse, JSON_UNESCAPED_UNICODE);
	die();
}


if ($action == 'actualiza_iva_comision') {
	$tipo_iva = $_POST['tipo_iva'];
	$comision = $_POST['comision'];
	//para almacenar el iva y la comision
	unset($_SESSION['arrayDatosIvaComision']); // Elimina la configuración anterior
	$_SESSION['arrayDatosIvaComision'] = array(
		'id' => 1,
		'tipo_iva' => $tipo_iva,
		'comision' => $comision
	);
	detalle_cotizacion();
}


if ($action == 'genera_numero_cotizacion') {
	$id_cliente = $_GET['id_cliente'];
	$anio = $_GET['anio'];
	$version = $_GET['version'];
	$sql = mysqli_query($con, "SELECT max(numero) + 1 as numero FROM encabezado_cotizacion_publicidad 
	WHERE id_cliente ='" . $id_cliente . "' and version= '" . $version . "' and year(fecha) = '" . $anio . "' and ruc_empresa ='" . $ruc_empresa . "' and status ='1'");
	$info_encabezado = mysqli_fetch_array($sql);
	$numero = isset($info_encabezado['numero']) ? $info_encabezado['numero'] : 1;
	echo $numero;
}

if ($action == 'genera_numero_version') {
	$id_cliente = $_GET['id_cliente'];
	$numero = $_GET['numero_cotizacion'];
	$anio = $_GET['anio'];
	$sql = mysqli_query($con, "SELECT max(version) + 1 as version FROM encabezado_cotizacion_publicidad 
	WHERE id_cliente ='" . $id_cliente . "' and numero= '" . $numero . "' and year(fecha) = '" . $anio . "' and ruc_empresa ='" . $ruc_empresa . "' and status ='1'");
	$info_encabezado = mysqli_fetch_array($sql);
	$version = isset($info_encabezado['version']) ? $info_encabezado['version'] : 1;
	echo $version;
}


if ($action == 'iniciar_formulario') {
	unset($_SESSION['arrayDetalleCotizacion']);
	unset($_SESSION['arrayDatosIvaComision']);
	detalle_cotizacion();
}

if ($action == 'iniciar_formulario_costos') {
	$id = $_GET['id'];
	detalle_costos_cotizacion($id);
}

function detalle_costos_cotizacion($id)
{
	$con = conenta_login();
	$subtotal_cotizacion = 0;
	$suma_subtotal = 0;
	$total_iva = 0;
	$suma_iva = 0;
	$comision = 0;
	$suma_comision = 0;
	$suma_costo = 0;
	$valor_costo = 0;
	$total_final = 0;
	$subtotalYcomision = 0;
	$sumasubtotalYcomision = 0;
	$info_detalle_valores = mysqli_query($con, "SELECT * FROM cuerpo_cotizacion_publicidad as cue INNER JOIN 
	encabezado_cotizacion_publicidad as enc ON enc.id=cue.id_encabezado_cotizacion 
	INNER JOIN tarifa_iva as tar ON tar.id=enc.tipo_iva WHERE cue.id_encabezado_cotizacion = '" . $id . "' ");
	while ($detalle_cotizacion = mysqli_fetch_assoc($info_detalle_valores)) {
		$subtotal_cotizacion =  ($detalle_cotizacion['precio'] * $detalle_cotizacion['cantidad']);
		$comision = $subtotal_cotizacion * ($detalle_cotizacion['comision'] / 100);
		$subtotalYcomision = $subtotal_cotizacion + $comision;
		$sumasubtotalYcomision += $subtotalYcomision;
		$suma_comision += $comision;
		$valor_costo = $detalle_cotizacion['valor_costo'];
		$suma_costo += $valor_costo;
		$porcentaje_iva = $detalle_cotizacion['porcentaje_iva'] / 100;
		$total_iva =  ($subtotalYcomision) * $porcentaje_iva;
		$suma_iva += $total_iva;
		$suma_subtotal += $subtotal_cotizacion;
		$total_final += $subtotalYcomision + $total_iva;
	}

	$info_detalle = mysqli_query($con, "SELECT * FROM cuerpo_cotizacion_publicidad as cue 
	LEFT JOIN proveedores as pro ON pro.id_proveedor=cue.id_proveedor WHERE cue.id_encabezado_cotizacion = '" . $id . "' ");
?>
	<div class="panel panel-info">

		<div class="table-responsive">
			<table class="table table-bordered" style="padding: 0px; margin-bottom: 0px;">
				<tr class="info">
					<th colspan="12" style="padding: 2px;" class="text-left">Detalle de servicios ofertados y costos generados</th>
				</tr>
				<tr class="info">
					<th style="padding: 2px;" class="text-left col-xs-4">Descripción</th>
					<th style="padding: 2px;" class="text-right col-xs-1">Subtotal</th>
					<th style="padding: 2px;" class="text-left col-xs-3">Proveedor</th>
					<th style="padding: 2px;" class="text-left col-xs-1">Factura</th>
					<th style="padding: 2px;" class="text-right col-xs-1">Valor</th>
					<th style="padding: 2px;" class="text-right col-xs-1">Estado</th>
				</tr>
				<?php
				$valor_costo = 0;
				while ($detalle = mysqli_fetch_assoc($info_detalle)) {
					$id = $detalle['id'];
					$id_detalle = $detalle['id_encabezado_cotizacion'];
					$descripcion_cotizacion = $detalle['descripcion'];
					$precio_cotizacion = $detalle['precio'];
					$cantidad_cotizacion = $detalle['cantidad'];
					$id_proveedor = $detalle['id_proveedor'];
					$nombre_proveedor = $detalle['razon_social'];
					$factura_costo = $detalle['factura'];
					$valor_costo = $detalle['valor_costo'];
					$estado_costo = $detalle['observaciones'];
					$subtotal_cotizacion = $cantidad_cotizacion * $precio_cotizacion;
				?>
					<tr>
						<td style="padding: 2px; height:25px;" class="col-xs-4 text-left"><?php echo $descripcion_cotizacion; ?></td>
						<td style="padding: 2px;" class="col-xs-1 text-right" style="height:25px;"><?php echo number_format($subtotal_cotizacion, 2, '.', ''); ?></td>
						<td style="padding: 2px; height:25px;" class="col-xs-4 text-left">
							<input type="hidden" id="id_proveedor_costo_item<?php echo $id; ?>" value="<?php echo $id_proveedor; ?>">
							<input type="text" style="height:25px; padding: 2px;" class="form-control input-sm" title="Proveedor" id="proveedor_costo_item<?php echo $id; ?>" onchange="modificar_proveedor_costo_item('<?php echo $id; ?>', '<?php echo $id_detalle; ?>');" onkeyup="buscar_proveedores('<?php echo $id; ?>');" autocomplete="off" value="<?php echo $nombre_proveedor; ?>">
						</td>
						<td style="padding: 2px; height:25px;" class="col-xs-1 text-right">
							<input type="text" style="height:25px; padding: 2px;" class="form-control input-sm text-right" title="Factura" id="factura_costo_item<?php echo $id; ?>" onchange="modificar_factura_costo_item('<?php echo $id; ?>', '<?php echo $id_detalle; ?>');" value="<?php echo $factura_costo; ?>">
						</td>
						<td style="padding: 2px; height:25px;" class="col-xs-1 text-right">
							<input type="text" style="height:25px; padding: 2px;" class="form-control input-sm text-right" title="Valor" id="valor_costo_item<?php echo $id; ?>" onchange="modificar_valor_costo_item('<?php echo $id; ?>', '<?php echo $id_detalle; ?>');" value="<?php echo $valor_costo; ?>">
						</td>
						<td style="padding: 2px; height:25px;" class="col-xs-1 text-left">
							<input type="text" style="height:25px; padding: 2px;" class="form-control input-sm" title="Estado" id="estado_costo_item<?php echo $id; ?>" onchange="modificar_estado_costo_item('<?php echo $id; ?>', '<?php echo $id_detalle; ?>');" value="<?php echo $estado_costo; ?>">
						</td>
					</tr>
				<?php
				}
				?>
				<tr class="info">
					<td class="text-right col-xs-4" style="padding: 2px;">Subtotal cotización: </td>
					<td class="text-right col-xs-1" style="padding: 2px;"><?php echo number_format($suma_subtotal, 2, '.', ''); ?></td>
					<td colspan="2" class="text-right col-xs-4" style="padding: 2px;">Subtotal Costo:</td>
					<td class="text-right col-xs-1" style="padding: 2px;"><?php echo number_format($suma_costo, 2, '.', ''); ?></td>
					<td class="text-right col-xs-1" style="padding: 2px;"></td>
				</tr>
				<tr class="info">
					<td class="text-right col-xs-4" style="padding: 2px;">Comisión: </td>
					<td class="text-right col-xs-1" style="padding: 2px;"><?php echo number_format($suma_comision, 2, '.', ''); ?></td>
					<td colspan="2" class="text-right col-xs-4" style="padding: 2px;">--</td>
					<td class="text-right col-xs-1" style="padding: 2px;">--</td>
					<td class="text-right col-xs-1" style="padding: 2px;"></td>
				</tr>
				<tr class="info">
					<td class="text-right col-xs-4" style="padding: 2px;">Iva: </td>
					<td class="text-right col-xs-1" style="padding: 2px;"><?php echo number_format($suma_iva, 2, '.', ''); ?></td>
					<td colspan="2" class="text-right col-xs-4" style="padding: 2px;">--</td>
					<td class="text-right col-xs-1" style="padding: 2px;">--</td>
					<td class="text-right col-xs-1" style="padding: 2px;"></td>
				</tr>
				<tr class="info">
					<td class="text-right col-xs-4" style="padding: 2px;">Total: </td>
					<td class="text-right col-xs-1" style="padding: 2px;"><?php echo number_format($total_final, 2, '.', ''); ?></td>
					<td colspan="2" class="text-right col-xs-4" style="padding: 2px;">Total costo:</td>
					<td class="text-right col-xs-1" style="padding: 2px;"><?php echo number_format($suma_costo, 2, '.', ''); ?></td>
					<td class="text-right col-xs-1" style="padding: 2px;"></td>
				</tr>
				<?php
				$perdida = $suma_costo - $sumasubtotalYcomision;
				$utilidad = $sumasubtotalYcomision - $suma_costo;
				if ($suma_costo > $sumasubtotalYcomision) {
				?>
					<tr class="danger">
						<th colspan="12" style="padding: 2px;" class="text-center">El costo es mayor a la cotización por: <?php echo number_format($perdida, 2, '.', ''); ?></th>
					</tr>
				<?php
				} else {
				?>
					<tr class="success">
						<th colspan="12" style="padding: 2px;" class="text-center">Utilidad: <?php echo number_format($utilidad, 2, '.', ''); ?> </th>
					</tr>
					<tr class="success">
						<th colspan="12" style="padding: 2px;" class="text-center"><?php echo number_format(($utilidad / $sumasubtotalYcomision) * 100, 2, '.', ''); ?>%</th>
					</tr>
				<?php
				}
				?>
			</table>
		</div>
	</div>
<?php
}


function detalle_cotizacion()
{
?>
	<div class="table-responsive">
		<table class="table table-bordered" style="padding: 0px; margin-bottom: 0px;">
			<tr class="info">
				<th style="padding: 2px;" class="text-left col-xs-3">Descripción</th>
				<th style="padding: 2px;" class="text-left col-xs-2">Tipo servicio</th>
				<th style="padding: 2px;" class="text-right">Precio</th>
				<th style="padding: 2px;" class="text-right">Ciudades</th>
				<th style="padding: 2px;" class="text-right">Días</th>
				<th style="padding: 2px;" class="text-right">Cantidad</th>
				<th style="padding: 2px;" class="text-right">Subtotal</th>
				<th style="padding: 2px;" class="text-center">Eliminar</th>
			</tr>
			<?php
			if (isset($_SESSION['arrayDetalleCotizacion'])) {
				foreach ($_SESSION['arrayDetalleCotizacion'] as $detalle) {
					$id = $detalle['id'];
					$descripcion_cotizacion = $detalle['descripcion_cotizacion'];
					$id_tipo = $detalle['id_tipo'];
					$precio_cotizacion = $detalle['precio_cotizacion'];
					$ciudades_cotizacion = $detalle['ciudades_cotizacion'];
					$dias_cotizacion = $detalle['dias_cotizacion'];
					$cantidad_cotizacion = $detalle['cantidad_cotizacion'];
					$subtotal = $cantidad_cotizacion * $precio_cotizacion;
			?>
					<tr>
						<td style="padding: 2px; height:25px;" class="col-xs-3 text-left">
							<input type="text" style="text-align:left; height:25px; padding: 2px;" class="form-control input-sm" title="Detalle" id="descripcion_item<?php echo $id; ?>" onchange="modificar_descripcion_item('<?php echo $id; ?>');" value="<?php echo $descripcion_cotizacion; ?>">
						</td>
						<td style="padding: 2px;" class="col-xs-2 text-left" style="height:25px;">
							<select class="form-control input-sm" style="height:25px;" onchange="tipo_item('<?php echo $id; ?>');" id="tipo_iten<?php echo $id; ?>">
								<?php
								$con = conenta_login();
								$ruc_empresa = $_SESSION['ruc_empresa'];
								$sql = mysqli_query($con, "SELECT * FROM grupo_familiar_producto WHERE ruc_empresa ='" . $ruc_empresa . "' order by nombre_grupo asc");
								while ($p = mysqli_fetch_assoc($sql)) {
									if ($id_tipo == $p['id_grupo']) {
								?>
										<option value="<?php echo $id_tipo; ?>" selected><?php echo $p['nombre_grupo']; ?> </option>
									<?php
									} else {
									?>
										<option value="<?php echo $p['id_grupo']; ?>"><?php echo $p['nombre_grupo']; ?> </option>
								<?php
									}
								}
								?>
							</select>
						</td>
						<td style="padding: 2px;" class="col-xs-1 text-right" style="height:25px;">
							<input type="number" style="text-align:right; height:25px; padding: 2px;" class="form-control input-sm" title="Precio" id="precio_item<?php echo $id; ?>" onchange="precio_item('<?php echo $id; ?>');" value="<?php echo number_format($precio_cotizacion, 2, '.', ''); ?>">
						</td>
						<td style="padding: 2px;" class="col-xs-1 text-right" style="height:25px;">
							<input type="number" style="text-align:right; height:25px; padding: 2px;" class="form-control input-sm" title="Ciudad" id="ciudad_item<?php echo $id; ?>" onchange="ciudad_item('<?php echo $id; ?>');" value="<?php echo number_format($ciudades_cotizacion, 0, '.', ''); ?>">
						</td>
						<td style="padding: 2px;" class="col-xs-1 text-right" style="height:25px;">
							<input type="number" style="text-align:right; height:25px; padding: 2px;" class="form-control input-sm" title="Días" id="dias_item<?php echo $id; ?>" onchange="dias_item('<?php echo $id; ?>');" value="<?php echo number_format($dias_cotizacion, 0, '.', ''); ?>">
						</td>
						<td style="padding: 2px;" class="col-xs-1 text-right" style="height:25px;">
							<input type="number" style="text-align:right; height:25px; padding: 2px;" class="form-control input-sm" title="Cantidad" id="cantidad_item<?php echo $id; ?>" onchange="cantidad_item('<?php echo $id; ?>');" value="<?php echo number_format($cantidad_cotizacion, 0, '.', ''); ?>">
						</td>
						<td style="padding: 2px;" class="col-xs-1 text-right" style="height:25px;"><?php echo number_format($subtotal, 2, '.', ''); ?></td>
						<td style="padding: 2px;" class="col-xs-1 text-center" style="height:25px;"><button type="button" style="height:25px;" class="btn btn-danger btn-xs" title="Eliminar" onclick="eliminar_item_cotizacion('<?php echo $id; ?>')"><span class="glyphicon glyphicon-remove"></span></button></td>
					</tr>
			<?php
				}
			}
			?>
		</table>
	</div>
	<div class="table-responsive">
		<table class="table table-bordered" style="padding: 0px; margin-bottom: 0px;">
			<?php
			$subtotal_cotizacion = 0;
			$suma_subtotal = 0;
			$total_iva = 0;
			$suma_iva = 0;
			$comision = 0;
			$suma_comision = 0;
			$total_final = 0;
			$subtotalYcomision = 0;
			if (isset($_SESSION['arrayDetalleCotizacion']) && isset($_SESSION['arrayDatosIvaComision'])) {
				foreach ($_SESSION['arrayDetalleCotizacion'] as $detalle_cotizacion) {
					$subtotal_cotizacion =  ($detalle_cotizacion['precio_cotizacion'] * $detalle_cotizacion['cantidad_cotizacion']);
					$comision = $subtotal_cotizacion * ($_SESSION['arrayDatosIvaComision']['comision'] / 100);
					$subtotalYcomision = $subtotal_cotizacion + $comision;
					$suma_comision += $comision;
					$sql = mysqli_query($con, "SELECT porcentaje_iva FROM tarifa_iva WHERE id= '" . $_SESSION['arrayDatosIvaComision']['tipo_iva'] . "'");
					$row_iva = mysqli_fetch_assoc($sql);
					$porcentaje_iva = $row_iva['porcentaje_iva'] / 100;
					$total_iva =  ($subtotalYcomision) * $porcentaje_iva;
					$suma_iva += $total_iva;
					$suma_subtotal += $subtotal_cotizacion;
					$total_final += $subtotalYcomision + $total_iva;
				}
			}

			?>
			<tr class="info">
				<td class="text-right col-xs-10" style="padding: 2px;">Subtotal: </td>
				<td class="text-right col-xs-1" style="padding: 2px;"><?php echo number_format($suma_subtotal, 2, '.', ''); ?></td>
				<td class="text-right col-xs-1" style="padding: 2px;"></td>
			</tr>
			<tr class="info">
				<td class="text-right col-xs-10" style="padding: 2px;">Comisión: </td>
				<td class="text-right col-xs-1" style="padding: 2px;"><?php echo number_format($suma_comision, 2, '.', ''); ?></td>
				<td class="text-right col-xs-1" style="padding: 2px;"></td>
			</tr>
			<tr class="info">
				<td class="text-right col-xs-10" style="padding: 2px;">Iva: </td>
				<td class="text-right col-xs-1" style="padding: 2px;"><?php echo number_format($suma_iva, 2, '.', ''); ?></td>
				<td class="text-right col-xs-1" style="padding: 2px;"></td>
			</tr>
			<tr class="info">
				<td class="text-right col-xs-10" style="padding: 2px;">Total: </td>
				<td class="text-right col-xs-1" style="padding: 2px;"><?php echo number_format($total_final, 2, '.', ''); ?></td>
				<td class="text-right col-xs-1" style="padding: 2px;"></td>
			</tr>
		</table>
	</div>
	<?php
}

//cambiar la  el proveedor
if ($action == 'modificar_proveedor_costo_item') {
	$id_item = $_POST['id_item'];
	$id_detalle = $_POST['id_detalle'];
	$id_proveedor_costo_item = strClean($_POST['id_proveedor_costo_item']);
	$update_detalle = mysqli_query($con, "UPDATE cuerpo_cotizacion_publicidad SET id_proveedor= '0', factura='', valor_costo='0.00' WHERE id= '" . $id_item . "'");
	//buscar facturas y valor de este proveedor
	$select_compra = mysqli_query($con, "SELECT enc.numero_documento as numero_documento, 
	round(sum(enc.otros_val + cue.subtotal),2) as subtotal 
	FROM encabezado_compra as enc 
	INNER JOIN cuerpo_compra as cue 
	ON cue.codigo_documento=enc.codigo_documento 
	WHERE enc.id_proveedor= '" . $id_proveedor_costo_item . "' group by cue.codigo_documento");
	$num_rows = mysqli_num_rows($select_compra);
	if ($num_rows == 1) {
		$row = mysqli_fetch_assoc($select_compra);
		$numero_documento = $row['numero_documento'];
		$subtotal = $row['subtotal'];
		$update_detalle = mysqli_query($con, "UPDATE cuerpo_cotizacion_publicidad SET id_proveedor= '" . $id_proveedor_costo_item . "', factura='" . $numero_documento . "', valor_costo='" . $subtotal . "' WHERE id= '" . $id_item . "'");
	} else {
		$update_detalle = mysqli_query($con, "UPDATE cuerpo_cotizacion_publicidad SET id_proveedor= '" . $id_proveedor_costo_item . "' WHERE id= '" . $id_item . "'");
	}

	if ($update_detalle) {
		echo "<script>
            $.notify('Actualizado','success');
            </script>";
	} else {
		echo "<script>
            $.notify('Intente de nuevo','error');
            </script>";
	}

	detalle_costos_cotizacion($id_detalle);
}

if ($action == 'factura_costo__item') {
	$id_item = $_POST['id_item'];
	$id_detalle = $_POST['id_detalle'];
	$factura_costo__item = strClean($_POST['factura_costo__item']);
	$update_detalle = mysqli_query($con, "UPDATE cuerpo_cotizacion_publicidad SET factura= '" . $factura_costo__item . "' WHERE id= '" . $id_item . "'");
	if ($update_detalle) {
		echo "<script>
            $.notify('Actualizado','success');
            </script>";
	} else {
		echo "<script>
            $.notify('Intente de nuevo','error');
            </script>";
	}

	detalle_costos_cotizacion($id_detalle);
}

if ($action == 'modificar_factura_costo_item') {
	$id_item = $_POST['id_item'];
	$id_detalle = $_POST['id_detalle'];
	$factura_costo_item = strClean($_POST['factura_costo_item']);
	$update_detalle = mysqli_query($con, "UPDATE cuerpo_cotizacion_publicidad SET factura= '" . $factura_costo_item . "' WHERE id= '" . $id_item . "'");
	if ($update_detalle) {
		echo "<script>
            $.notify('Actualizado','success');
            </script>";
	} else {
		echo "<script>
            $.notify('Intente de nuevo','error');
            </script>";
	}

	detalle_costos_cotizacion($id_detalle);
}

if ($action == 'modificar_valor_costo_item') {
	$id_item = $_POST['id_item'];
	$id_detalle = $_POST['id_detalle'];
	$valor_costo_item = strClean($_POST['valor_costo_item']);
	$update_detalle = mysqli_query($con, "UPDATE cuerpo_cotizacion_publicidad SET valor_costo= '" . $valor_costo_item . "' WHERE id= '" . $id_item . "'");
	if ($update_detalle) {
		echo "<script>
            $.notify('Actualizado','success');
            </script>";
	} else {
		echo "<script>
            $.notify('Intente de nuevo','error');
            </script>";
	}

	detalle_costos_cotizacion($id_detalle);
}

if ($action == 'modificar_estado_costo_item') {
	$id_item = $_POST['id_item'];
	$id_detalle = $_POST['id_detalle'];
	$estado_costo_item = strClean($_POST['estado_costo_item']);
	$update_detalle = mysqli_query($con, "UPDATE cuerpo_cotizacion_publicidad SET estado= '" . $estado_costo_item . "' WHERE id= '" . $id_item . "'");
	if ($update_detalle) {
		echo "<script>
            $.notify('Actualizado','success');
            </script>";
	} else {
		echo "<script>
            $.notify('Intente de nuevo','error');
            </script>";
	}
	detalle_costos_cotizacion($id_detalle);
}

//para editar la informacion de la cotizacion
if ($action == 'informacion_editar_cotizacion') {
	$id = $_GET['id'];
	//para traer el encabezado
	$sql = mysqli_query($con, "SELECT * FROM encabezado_cotizacion_publicidad as enc 
	INNER JOIN clientes as cli ON cli.id=enc.id_cliente 
	WHERE enc.id ='" . $id . "' ");
	$info_encabezado = mysqli_fetch_array($sql);

	$data = array(
		'id_cliente' => $info_encabezado['id_cliente'],
		'nombre_cliente' => strtoupper($info_encabezado['nombre']),
		'contacto' => strtoupper($info_encabezado['contacto']),
		'ejecutivo' => $info_encabezado['ejecutivo'],
		'fecha' => date("d-m-Y", strtotime($info_encabezado['fecha'])),
		'version' => $info_encabezado['version'],
		'proyecto' => strtoupper($info_encabezado['proyecto']),
		'numero' => $info_encabezado['numero'],
		'observaciones' => strtoupper($info_encabezado['observaciones']),
		'presupuesto' => strtoupper($info_encabezado['presupuesto']),
		'tipo_iva' => $info_encabezado['tipo_iva'],
		'comision' => $info_encabezado['comision']
	);

	if ($sql) {
		$arrResponse = array("status" => true, "data" => $data);
	} else {
		$arrResponse = array("status" => false, "msg" => 'Datos no encontrados');
	}

	echo json_encode($arrResponse, JSON_UNESCAPED_UNICODE);
	die();
}

if ($action == 'iniciar_formulario_editar') {
	$id = $_GET['id'];
	unset($_SESSION['arrayDetalleCotizacion']);
	unset($_SESSION['arrayDatosIvaComision']);
	//traer el detalle
	$info_detalle = mysqli_query($con, "SELECT * FROM cuerpo_cotizacion_publicidad WHERE id_encabezado_cotizacion = '" . $id . "'  ");
	$arrayDetalleCotizacion = array();
	while ($row_info_cuerpo = mysqli_fetch_array($info_detalle)) {
		$arrayDatos = array(
			'id' => rand(5, 5000),
			'descripcion_cotizacion' => $row_info_cuerpo['descripcion'],
			'id_tipo' => $row_info_cuerpo['id_tipo'],
			'precio_cotizacion' => $row_info_cuerpo['precio'],
			'ciudades_cotizacion' => $row_info_cuerpo['ciudades'],
			'dias_cotizacion' => $row_info_cuerpo['dias'],
			'cantidad_cotizacion' => $row_info_cuerpo['cantidad']
		);
		array_push($arrayDetalleCotizacion, $arrayDatos);
	}
	$_SESSION['arrayDetalleCotizacion'] = $arrayDetalleCotizacion;
	//para almacenar el iva y la comision
	unset($_SESSION['arrayDatosIvaComision']); // Elimina la configuración anterior
	$info_IvaComision = mysqli_query($con, "SELECT * FROM encabezado_cotizacion_publicidad WHERE id = '" . $id . "'  ");
	$row_info_encabezado = mysqli_fetch_array($info_IvaComision);
	$_SESSION['arrayDatosIvaComision'] = array(
		'id' => 1,
		'tipo_iva' => $row_info_encabezado['tipo_iva'],
		'comision' => $row_info_encabezado['comision']
	);
	detalle_cotizacion();
}

//cambiar la cantidad del item
if ($action == 'modificar_cantidad_item') {
	$id = $_POST['id'];
	$cantidad_item = $_POST['cantidad_item'];
	foreach ($_SESSION['arrayDetalleCotizacion'] as $key => $item) {
		if ($item['id'] == $id) {
			$_SESSION['arrayDetalleCotizacion'][$key]['cantidad_cotizacion'] = $cantidad_item;
			echo "<script>
            $.notify('Actualizado','success');
            </script>";
			break; // Detener la búsqueda una vez encontrado
		}
	}
	detalle_cotizacion();
}
//cambiar la dias del item
if ($action == 'modificar_dias_item') {
	$id = $_POST['id'];
	$dias_item = $_POST['dias_item'];
	foreach ($_SESSION['arrayDetalleCotizacion'] as $key => $item) {
		if ($item['id'] == $id) {
			$_SESSION['arrayDetalleCotizacion'][$key]['dias_cotizacion'] = $dias_item;
			echo "<script>
            $.notify('Actualizado','success');
            </script>";
			break; // Detener la búsqueda una vez encontrado
		}
	}
	detalle_cotizacion();
}

//cambiar la ciudad del item
if ($action == 'modificar_ciudad_item') {
	$id = $_POST['id'];
	$ciudad_item = $_POST['ciudad_item'];
	foreach ($_SESSION['arrayDetalleCotizacion'] as $key => $item) {
		if ($item['id'] == $id) {
			$_SESSION['arrayDetalleCotizacion'][$key]['ciudades_cotizacion'] = $ciudad_item;
			echo "<script>
            $.notify('Actualizado','success');
            </script>";
			break; // Detener la búsqueda una vez encontrado
		}
	}
	detalle_cotizacion();
}


//cambiar la precio del item
if ($action == 'modificar_precio_item') {
	$id = $_POST['id'];
	$precio_item = $_POST['precio_item'];
	foreach ($_SESSION['arrayDetalleCotizacion'] as $key => $item) {
		if ($item['id'] == $id) {
			$_SESSION['arrayDetalleCotizacion'][$key]['precio_cotizacion'] = $precio_item;
			echo "<script>
            $.notify('Actualizado','success');
            </script>";
			break; // Detener la búsqueda una vez encontrado
		}
	}
	detalle_cotizacion();
}

//cambiar la tipo del item
if ($action == 'modificar_tipo_item') {
	$id = $_POST['id'];
	$tipo_iten = strClean($_POST['tipo_iten']);
	foreach ($_SESSION['arrayDetalleCotizacion'] as $key => $item) {
		if ($item['id'] == $id) {
			$_SESSION['arrayDetalleCotizacion'][$key]['id_tipo'] = $tipo_iten;
			echo "<script>
            $.notify('Actualizado','success');
            </script>";
			break; // Detener la búsqueda una vez encontrado
		}
	}
	detalle_cotizacion();
}

//cambiar la descripcion del item
if ($action == 'modificar_descripcion_item') {
	$id = $_POST['id'];
	$nuevaDescripcion = strClean($_POST['descripcion_item']);
	foreach ($_SESSION['arrayDetalleCotizacion'] as $key => $item) {
		if ($item['id'] == $id) {
			$_SESSION['arrayDetalleCotizacion'][$key]['descripcion_cotizacion'] = $nuevaDescripcion;
			echo "<script>
            $.notify('Actualizado','success');
            </script>";
			break; // Detener la búsqueda una vez encontrado
		}
	}
	detalle_cotizacion();
}


//eliminar iten
if ($action == 'eliminar_item_cotizacion') {
	$intid = $_POST['id'];
	$arrData = $_SESSION['arrayDetalleCotizacion'];
	for ($i = 0; $i < count($arrData); $i++) {
		if ($arrData[$i]['id'] == $intid) {
			unset($arrData[$i]);
			echo "<script>
            $.notify('Eliminado','error');
            </script>";
		}
	}
	sort($arrData); //para reordenar el array
	$_SESSION['arrayDetalleCotizacion'] = $arrData;
	detalle_cotizacion();
}

//agregar nuevo iten a la cotizacion, nueva o editar
if ($action == 'agregar_item_cotizacion') {
	$descripcion_cotizacion = strClean($_POST['descripcion_cotizacion']);
	$id_tipo = $_POST['id_tipo'];
	$precio_cotizacion = $_POST['precio_cotizacion'];
	$ciudades_cotizacion = $_POST['ciudades_cotizacion'];
	$dias_cotizacion = $_POST['dias_cotizacion'];
	$cantidad_cotizacion = $_POST['cantidad_cotizacion'];
	$tipo_iva = $_POST['tipo_iva'];
	$comision = $_POST['comision'];

	if (!empty($descripcion_cotizacion)) {
		$arrayDetalleCotizacion = array();
		$arrayDetalleIvaComision = array();
		$arrayDatosDetalles = array(
			'id' => rand(5, 5000),
			'descripcion_cotizacion' => $descripcion_cotizacion,
			'id_tipo' => $id_tipo,
			'precio_cotizacion' => $precio_cotizacion,
			'ciudades_cotizacion' => $ciudades_cotizacion,
			'dias_cotizacion' => $dias_cotizacion,
			'cantidad_cotizacion' => $cantidad_cotizacion
		);

		if (isset($_SESSION['arrayDetalleCotizacion'])) {
			$arrayDetalleCotizacion = $_SESSION['arrayDetalleCotizacion'];
			array_push($arrayDetalleCotizacion, $arrayDatosDetalles);
			$_SESSION['arrayDetalleCotizacion'] = $arrayDetalleCotizacion;
		} else {
			array_push($arrayDetalleCotizacion, $arrayDatosDetalles);
			$_SESSION['arrayDetalleCotizacion'] = $arrayDetalleCotizacion;
		}
		//para almacenar el iva y la comision
		unset($_SESSION['arrayDatosIvaComision']); // Elimina la configuración anterior
		$_SESSION['arrayDatosIvaComision'] = array(
			'id' => 1,
			'tipo_iva' => $tipo_iva,
			'comision' => $comision
		);
	} else {
		echo "<script>
		$.notify('Ingrese descripción','error');
		</script>";
	}
	detalle_cotizacion();
}

//PARA BUSCAR LAS buscar_cotizaciones
if ($action == 'buscar_cotizaciones') {
	// escaping, additionally removing everything that could be (html/javascript-) code
	$q = mysqli_real_escape_string($con, (strip_tags($_REQUEST['q'], ENT_QUOTES)));
	$ordenado = mysqli_real_escape_string($con, (strip_tags($_GET['ordenado'], ENT_QUOTES)));
	$por = mysqli_real_escape_string($con, (strip_tags($_GET['por'], ENT_QUOTES)));
	$estado = mysqli_real_escape_string($con, (strip_tags($_GET['estado'], ENT_QUOTES)));
	$anio = mysqli_real_escape_string($con, (strip_tags($_GET['anio'], ENT_QUOTES)));
	$mes = mysqli_real_escape_string($con, (strip_tags($_GET['mes'], ENT_QUOTES)));
	$dia = mysqli_real_escape_string($con, (strip_tags($_GET['dia'], ENT_QUOTES)));

	$opciones_status = " and ec.status = '1' ";

	if (empty($anio)) {
		$opciones_anio = "";
	} else {
		$opciones_anio = " and year(ec.fecha) = '" . $anio . "' ";
	}
	if (empty($mes)) {
		$opciones_mes = "";
	} else {
		$opciones_mes = " and month(ec.fecha) = '" . $mes . "' ";
	}

	if (empty($dia)) {
		$opciones_dia = "";
	} else {
		$opciones_dia = " and day(ec.fecha) = '" . $dia . "' ";
	}

	$aColumns = array('fecha', 'contacto', 'ejecutivo', 'cl.nombre', 'proyecto', 'numero', 'ven.nombre'); //Columnas de busqueda
	$sTable = "encabezado_cotizacion_publicidad as ec 
	INNER JOIN clientes as cl ON cl.id=ec.id_cliente
	INNER JOIN vendedores as ven ON ven.id_vendedor=ec.ejecutivo";
	$sWhere = "WHERE ec.ruc_empresa ='" . $ruc_empresa . "' $opciones_status $opciones_anio $opciones_mes $opciones_dia ";
	$text_buscar = explode(' ', $q);
	$like = "";
	for ($i = 0; $i < count($text_buscar); $i++) {
		$like .= "%" . $text_buscar[$i];
	}
	if ($_GET['q'] != "") {
		$sWhere = "WHERE (ec.ruc_empresa ='" . $ruc_empresa . "' $opciones_status $opciones_anio $opciones_mes $opciones_dia AND ";

		for ($i = 0; $i < count($aColumns); $i++) {
			$sWhere .= $aColumns[$i] . " LIKE '" . $like . "%' AND ec.ruc_empresa = '" . $ruc_empresa . "' $opciones_status $opciones_anio $opciones_mes $opciones_dia OR ";
		}
		$sWhere = substr_replace($sWhere, "AND ec.ruc_empresa = '" . $ruc_empresa . "' $opciones_status $opciones_anio $opciones_mes $opciones_dia ", -3);
		$sWhere .= ')';
	}
	$sWhere .= " order by $ordenado $por";

	//pagination variables
	$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
	$per_page = 20; //how much records you want to show
	$adjacents  = 10; //gap between pages after number of adjacents
	$offset = ($page - 1) * $per_page;
	//Count the total number of row in your table*/
	$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable  $sWhere");
	$row = mysqli_fetch_array($count_query);

	$numrows = $row['numrows'];
	$total_pages = ceil($numrows / $per_page);
	$reload = '';
	//main query to fetch the data
	$sql = "SELECT ec.id as id, ec.numero as numero, cl.nombre as cliente,
	ec.version as version, ec.contacto as contacto, ven.nombre as ejecutivo,
	ec.proyecto as proyecto, ec.status as status, ec.fecha as fecha, 
	ec.observaciones as observaciones, ec.presupuesto as presupuesto FROM  $sTable $sWhere LIMIT $offset,$per_page";
	$query = mysqli_query($con, $sql);
	//loop through fetched data
	if ($numrows > 0) {
	?>
		<div class="panel panel-info">
			<div class="table-responsive">
				<table class="table table-hover">
					<tr class="info">
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("fecha");'>Fecha</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("numero");'>Número</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("version");'>Versión</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("cl.nombre");'>Cliente</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("contacto");'>Contacto</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("ven.nombre");'>Ejecutivo</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("proyecto");'>Proyecto</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("observaciones");'>Observaciones</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("presupuesto");'>Presupuesto</button></th>
						<th class='text-right'>Opciones</th>
						<input type="hidden" value="<?php echo $page; ?>" id="pagina">
					</tr>
					<?php

					while ($row = mysqli_fetch_array($query)) {
						$id = $row['id'];
						$fecha = $row['fecha'];
						$numero = str_pad($row['numero'], 3, '0', STR_PAD_LEFT) . "-" . date("Y", strtotime($fecha));
						$nombre_cliente = $row['cliente'];
						$version = "V" . $row['version'];
						$contacto = $row['contacto'];
						$ejecutivo = $row['ejecutivo'];
						$proyecto = $row['proyecto'];
						$observaciones = $row['observaciones'];
						$presupuesto = $row['presupuesto'];
					?>
						<tr>
							<td><?php echo date("d/m/Y", strtotime($fecha)); ?></td>
							<td><?php echo $numero; ?></td>
							<td><?php echo $version; ?></td>
							<td class='col-md-2'><?php echo strtoupper($nombre_cliente); ?></td>
							<td><?php echo strtoupper($contacto); ?></td>
							<td><?php echo strtoupper($ejecutivo); ?></td>
							<td><?php echo strtoupper($proyecto); ?></td>
							<td><?php echo strtoupper($observaciones); ?></td>
							<td><?php echo strtoupper($presupuesto); ?></td>
							<td class='col-md-3'><span class="pull-right">

									<?php
									$con = conenta_login();
									if (getPermisos($con, $id_usuario, $ruc_empresa, 'cotizacion_publicidad')['r'] == 1) {
									?>
										<a href="../pdf/pdf_cotizacion_publicidad.php?action=general&id=<?php echo base64_encode($id); ?>" class='btn btn-default btn-xs' title='Pdf' target="_blank">Pdf</a>
									<?php
									}
									if (getPermisos($con, $id_usuario, $ruc_empresa, 'cotizacion_publicidad')['u'] == 1) {
									?>
										<a class='btn btn-info btn-xs' title='Editar cotización' onclick="editar_cotizacion('<?php echo $id; ?>')" data-toggle="modal" data-target="#cotizacionPublicidad">Editar</a>
										<a class='btn btn-info btn-xs' title='Cambiar de versión' onclick="version_cotizacion('<?php echo $id; ?>')" data-toggle="modal" data-target="#cotizacionPublicidad">Versión</a>
										<a class='btn btn-info btn-xs' title='Agregar costos' onclick="costos_cotizacion('<?php echo $id; ?>')" data-toggle="modal" data-target="#cotizacionCostos">Costos</a>
										<a class='btn btn-info btn-xs' title='Facturar' onclick="facturar_cotizacion('<?php echo $id; ?>')" data-toggle="modal" data-target="#cotizacionFactura">Factura</a>
									<?php
									}
									if (getPermisos($con, $id_usuario, $ruc_empresa, 'cotizacion_publicidad')['d'] == 1) {
									?>
										<a class='btn btn-danger btn-xs' title='Anular cotización' onclick="anular_cotizacion('<?php echo $id; ?>')"><i class="glyphicon glyphicon-erase"></i> </a>
									<?php
									}
									?>
								</span></td>
						</tr>
					<?php
					}
					?>
					<tr>
						<td colspan="10"><span class="pull-right">
								<?php
								echo paginate($reload, $page, $total_pages, $adjacents);
								?></span></td>
					</tr>
				</table>
			</div>
		</div>
<?php
	}
}

//guardar o editar
if ($action == 'guardar_cotizacion_publicidad') {
	$id_cotizacion_publicidad = mysqli_real_escape_string($con, $_POST['id_cotizacion_publicidad']);
	$id_cliente_cotizacion = mysqli_real_escape_string($con, $_POST['id_cliente_cotizacion']);
	$nombre_proyecto = mysqli_real_escape_string($con, strClean($_POST['nombre_proyecto']));
	$contacto_empresa = mysqli_real_escape_string($con, strClean($_POST['contacto_empresa']));
	$numero_cotizacion = mysqli_real_escape_string($con, $_POST['numero_cotizacion']);
	$id_vendedor = mysqli_real_escape_string($con, $_POST['id_vendedor']);
	$fecha_cotizacion = mysqli_real_escape_string($con, $_POST['fecha_cotizacion']);
	$version_cotizacion = mysqli_real_escape_string($con, strClean($_POST['version_cotizacion']));
	$observaciones = mysqli_real_escape_string($con, strClean($_POST['observaciones']));
	$presupuesto = mysqli_real_escape_string($con, strClean($_POST['presupuesto']));
	$tipo_iva = mysqli_real_escape_string($con, $_POST['tipo_iva']);
	$comision = mysqli_real_escape_string($con, $_POST['comision']);

	if (!isset($id_cliente_cotizacion) || trim($id_cliente_cotizacion) === '') {
		echo "<script>
            $.notify('Seleccione un cliente','error');
            </script>";
	} else if (!isset($nombre_proyecto) || trim($nombre_proyecto) === '') {
		echo "<script>
				$.notify('Ingrese un nombre al proyecto','error');
				</script>";
	} else if (!isset($contacto_empresa) || trim($contacto_empresa) === '') {
		echo "<script>
				$.notify('Ingrese un nombre de contacto','error');
				</script>";
	} else if (!isset($numero_cotizacion) || trim($numero_cotizacion) === '') {
		echo "<script>
				$.notify('Ingrese un número de cotización','error');
				</script>";
	} else if (!isset($id_vendedor) || trim($id_vendedor) === '') {
		echo "<script>
				$.notify('Seleccione un ejecutivo','error');
				</script>";
	} else if (!isset($fecha_cotizacion) || trim($fecha_cotizacion) === '') {
		echo "<script>
				$.notify('Ingrese una fecha de cotización','error');
				</script>";
	} else if (!isset($version_cotizacion) || trim($version_cotizacion) === '') {
		echo "<script>
				$.notify('Ingrese una versión de cotización','error');
				</script>";
	} else if (!isset($ruc_empresa) || trim($ruc_empresa) === '') {
		echo "<script>
				$.notify('Se ha perdido la conexión, vuelva a ingresar al sistema','error');
				</script>";
	} else if (!isset($id_usuario) || trim($id_usuario) === '') {
		echo "<script>
				$.notify('Se ha perdido la conexión, vuelva a ingresar al sistema','error');
				</script>";
	} else if (!isset($_SESSION['arrayDetalleCotizacion'])) {
		echo "<script>
                $.notify('No se encontraron detalles para la cotización','error');
                </script>";
	} else if (count($_SESSION['arrayDetalleCotizacion']) == 0) {
		echo "<script>
				$.notify('No se encontraron detalles para la cotización','error');
				</script>";
	} else {

		if (empty($id_cotizacion_publicidad)) {
			$busca_cotizacion = mysqli_query($con, "SELECT * FROM encabezado_cotizacion_publicidad WHERE ruc_empresa = '" . $ruc_empresa . "' and year(fecha) ='" . date("Y", strtotime($fecha_cotizacion)) . "' and version ='" . $version_cotizacion . "' and numero ='" . $numero_cotizacion . "' and id_cliente ='" . $id_cliente_cotizacion . "' and status='1' ");
			$count = mysqli_num_rows($busca_cotizacion);
			if ($count > 0) {
				echo "<script>
                $.notify('Existe una cotización con el mismo número y versión para este cliente que puede editar','error');
                </script>";
			} else {
				$con->begin_transaction();
				try {
					$sql_insert = "INSERT INTO encabezado_cotizacion_publicidad (ruc_empresa, 
																id_cliente, 
																contacto, 
																ejecutivo, 
																fecha, 
																version, 
																proyecto, 
																numero, 
																id_usuario, 
																observaciones,
																presupuesto,
																tipo_iva,
																comision) 
    															VALUES ('" . $ruc_empresa . "', 
																'" . $id_cliente_cotizacion . "', 
																'" . $contacto_empresa . "', 
																'" . $id_vendedor . "', 
																'" . date("Y/m/d", strtotime($fecha_cotizacion)) . "', 
																'" . $version_cotizacion . "', 
																'" . $nombre_proyecto . "', 
																'" . $numero_cotizacion . "', 
																'" . $id_usuario . "', 
																'" . $observaciones . "',
																'" . $presupuesto . "',
																'" . $tipo_iva . "',
																'" . $comision . "')";

					if (!$con->query($sql_insert)) {
						echo "<script>
                $.notify('Error al guardar la cotización','error');
                </script>";
					}
					$id_encabezado = $con->insert_id;
					foreach ($_SESSION['arrayDetalleCotizacion'] as $detalle) {
						$sql_detalle = "INSERT INTO 
					cuerpo_cotizacion_publicidad(id_encabezado_cotizacion,
												descripcion,
												id_tipo,
												precio,
												ciudades,
												dias,
												cantidad)
											VALUES('" . $id_encabezado . "',
											'" . $detalle['descripcion_cotizacion'] . "',
											'" . $detalle['id_tipo'] . "',
											'" . $detalle['precio_cotizacion'] . "',
											'" . $detalle['ciudades_cotizacion'] . "',
											'" . $detalle['dias_cotizacion'] . "',
											'" . $detalle['cantidad_cotizacion'] . "')";
						if (!$con->query($sql_detalle)) {
							echo "<script>
						$.notify('Error al guardar el detalle','error');
						</script>";
						}
					}
					$con->commit();
					unset($_SESSION['arrayDetalleCotizacion']);
					echo "<script>
			$.notify('Cotización guardada correctamente','success');
			$('#vista_detalle_cotizacion').html('');
			document.querySelector('#guardar_cotizacion_publicidad').reset();
			load(1);
			</script>";
				} catch (Exception $e) {
					$con->rollback();
					echo "<script>
                $.notify('No es posible guardar, intente de nuevo','error');
                </script>";
				}
			}
		} else {
			//editar cotizacion
			$busca_cotizacion = mysqli_query($con, "SELECT * FROM encabezado_cotizacion_publicidad WHERE id != '" . $id_cotizacion_publicidad . "' and ruc_empresa = '" . $ruc_empresa . "' and year(fecha) ='" . date("Y", strtotime($fecha_cotizacion)) . "' and version ='" . $version_cotizacion . "' and numero ='" . $numero_cotizacion . "' and id_cliente ='" . $id_cliente_cotizacion . "' and status='1' ");
			$count = mysqli_num_rows($busca_cotizacion);
			if ($count > 0) {
				echo "<script>
                $.notify('Existe una cotización con el mismo número y versión para este cliente que puede editar','error');
                </script>";
			} else {
				$con->begin_transaction();
				try {
					$sql_update = "UPDATE encabezado_cotizacion_publicidad SET 
			id_cliente = '" . $id_cliente_cotizacion . "',
			contacto = '" . $contacto_empresa . "',
			ejecutivo= '" . $id_vendedor . "',
			fecha = '" . date("Y/m/d", strtotime($fecha_cotizacion)) . "',
			version =  '" . $version_cotizacion . "',
			proyecto =  '" . $nombre_proyecto . "',
			numero ='" . $numero_cotizacion . "', 
			id_usuario = '" . $id_usuario . "',
			observaciones = '" . $observaciones . "',
			presupuesto = '" . $presupuesto . "',
			tipo_iva = '" . $tipo_iva . "',
			comision = '" . $comision . "'
			WHERE id = '" . $id_cotizacion_publicidad . "'";

					if (!$con->query($sql_update)) {
						echo "<script>
		$.notify('Error al actualizar el encabezado de la cotización','error');
		</script>";
					}
					//borrar el detalle de la cotizacion anterior
					$sql_delete_detalle = mysqli_query($con, "DELETE FROM cuerpo_cotizacion_publicidad WHERE id_encabezado_cotizacion = '" . $id_cotizacion_publicidad . "'  ");
					foreach ($_SESSION['arrayDetalleCotizacion'] as $detalle) {
						$sql_detalle = "INSERT INTO 
			cuerpo_cotizacion_publicidad(id_encabezado_cotizacion,
										descripcion,
										id_tipo,
										precio,
										ciudades,
										dias,
										cantidad)
									VALUES('" . $id_cotizacion_publicidad . "',
									'" . $detalle['descripcion_cotizacion'] . "',
									'" . $detalle['id_tipo'] . "',
									'" . $detalle['precio_cotizacion'] . "',
									'" . $detalle['ciudades_cotizacion'] . "',
									'" . $detalle['dias_cotizacion'] . "',
									'" . $detalle['cantidad_cotizacion'] . "')";
						if (!$con->query($sql_detalle)) {
							echo "<script>
				$.notify('Error al guardar el detalle','error');
				</script>";
						}
					}
					$con->commit();
					unset($_SESSION['arrayDetalleCotizacion']);
					unset($_SESSION['arrayDatosIvaComision']);
					echo "<script>
	$.notify('Cotización actualizada correctamente','success');
	$('#vista_detalle_cotizacion').html('');
	document.querySelector('#guardar_cotizacion_publicidad').reset();
	load(1);
	</script>";
				} catch (Exception $e) {
					$con->rollback();
					echo "<script>
		$.notify('No es posible guardar, intente de nuevo','error');
		</script>";
				}
			}
		}

		$con->close();
	} //termina if de else
}

//guardar factura
if ($action == 'guardar_factura_publicidad') {

	mysqli_autocommit($con, false); // Desactivar autocommit para manejar transacciones manualmente

	try {
		// Sanitización de datos
		$id_cotizacion_publicidad = mysqli_real_escape_string($con, $_POST['id_factura_publicidad']);
		$id_cliente = mysqli_real_escape_string($con, $_POST['id_cliente_publicidad']);
		$fecha_factura = date("Y-m-d", strtotime($_POST['fecha_factura'])); // Formato de fecha correcto
		$serie_factura = mysqli_real_escape_string($con, $_POST['serie_factura']);
		$numero_factura = mysqli_real_escape_string($con, $_POST['numero_factura']);
		$codigo_servicio_factura = mysqli_real_escape_string($con, strClean($_POST['codigo_servicio_factura']));
		$nombre_servicio_factura = mysqli_real_escape_string($con, strClean($_POST['nombre_servicio_factura']));
		$cantidad_factura = floatval($_POST['cantidad_factura']); // Convertir a número decimal
		$precio_factura = floatval($_POST['precio_factura']);
		$subtotal_factura = floatval($_POST['subtotal_factura']);
		$total_factura = floatval($_POST['total_factura']);
		$id_iva = mysqli_real_escape_string($con, $_POST['id_iva']);
		$numero_cotizacion = mysqli_real_escape_string($con, $_POST['numero_cotizacion']);
		$id_producto_factura_cotizacion = mysqli_real_escape_string($con, $_POST['id_producto_factura_cotizacion']);

		if (empty($id_cotizacion_publicidad)) {
			throw new Exception("Seleccione una cotización para facturar");
		}
		if (empty($fecha_factura)) {
			throw new Exception("Ingrese fecha de emisión de la factura");
		}
		if (empty($numero_factura)) {
			throw new Exception("Ingrese un número de factura");
		}
		if (empty($codigo_servicio_factura)) {
			throw new Exception("Ingrese un código del servicio");
		}
		if (empty($nombre_servicio_factura)) {
			throw new Exception("Ingrese descripción del servicio");
		}

		if (empty($id_producto_factura_cotizacion)) {
			throw new Exception("Si desea guardar una nueva factura debe volver a seleccionar el servicio");
		}

		// Obtener código de IVA
		$sql_iva = mysqli_query($con, "SELECT codigo FROM tarifa_iva WHERE id='$id_iva'");
		if (!$sql_iva) throw new Exception("Error al consultar el IVA: " . mysqli_error($con));
		$detalle_iva = mysqli_fetch_assoc($sql_iva);
		$tarifa_iva = $detalle_iva['codigo'];

		// Obtener datos del cliente
		$sql_cliente = mysqli_query($con, "SELECT email, direccion, telefono FROM clientes WHERE id='$id_cliente'");
		if (!$sql_cliente) throw new Exception("Error al consultar el cliente: " . mysqli_error($con));
		$detalle_cliente = mysqli_fetch_assoc($sql_cliente);
		$email = $detalle_cliente['email'];
		$direccion = $detalle_cliente['direccion'];
		$telefono = $detalle_cliente['telefono'];

		// Actualizar cotización
		$sql_update = "UPDATE encabezado_cotizacion_publicidad SET 
        fecha_factura = '$fecha_factura',
        serie_factura = '$serie_factura',
        numero_factura = '$numero_factura',
        estado_factura = 'Facturado', 
        codigo_servicio = '$codigo_servicio_factura',
        descripcion_servicio = '$nombre_servicio_factura',
        cantidad_factura = '$cantidad_factura',
        precio_factura = '$precio_factura'
        WHERE id = '$id_cotizacion_publicidad'";

		if (!mysqli_query($con, $sql_update)) throw new Exception("Error al actualizar la cotización: " . mysqli_error($con));

		/* 		//verifico que no exista el servicio
		$sql_verificar = "SELECT id FROM productos_servicios 
                  WHERE (codigo_producto = '$codigo_servicio_factura' OR nombre_producto = '$nombre_servicio_factura') 
                  AND ruc_empresa = '$ruc_empresa'";
		$result_verificar = mysqli_query($con, $sql_verificar);

		if (!$result_verificar) {
			throw new Exception("Error al verificar existencia del servicio: " . mysqli_error($con));
		}

		if (mysqli_num_rows($result_verificar) > 0) {
			// Si ya existe el código o el nombre del servicio, mostrar un mensaje y detener la ejecución
			echo "<script>
				$.notify('El código o el nombre del servicio ya existen en la base de datos. No se puede duplicar.', 'error');
			</script>";
			exit(); // Detener la ejecución
		} else {
			// Si no existe, proceder con la inserción del nuevo producto
			$sql_servicio = "INSERT INTO productos_servicios VALUES (null, '$ruc_empresa',
				'$codigo_servicio_factura', '$nombre_servicio_factura', '',
				'$precio_factura', '02', '$tarifa_iva', '0', '0',
				'$fecha_factura', '0', '1', '$id_usuario')";

			if (!mysqli_query($con, $sql_servicio)) {
				throw new Exception("Error al insertar el servicio: " . mysqli_error($con));
			}

			$id_producto = mysqli_insert_id($con); // Obtener el ID del producto insertado
		} */

		// Guardar encabezado de la factura
		$sql_encabezado = "INSERT INTO encabezado_factura VALUES (null, '$ruc_empresa',
        '$fecha_factura', '$serie_factura', '$numero_factura',
        '$id_cliente', '', '', '$fecha_factura', 'POR COBRAR', 'ELECTRÓNICA',
        'PENDIENTE', '$total_factura', '$id_usuario', '0', '0', '', 'PENDIENTE', 0, 0)";

		if (!mysqli_query($con, $sql_encabezado)) throw new Exception("Error al insertar encabezado de factura: " . mysqli_error($con));

		// Guardar detalle de la factura
		$sql_detalle = "INSERT INTO cuerpo_factura VALUES (null, '$ruc_empresa',
        '$serie_factura', '$numero_factura', '$id_producto_factura_cotizacion',
        '$cantidad_factura', '$precio_factura', '$subtotal_factura',
        '02', '$tarifa_iva', '0',
        '', '0', '$codigo_servicio_factura',
        '$nombre_servicio_factura', '0', '0', '0', '0')";

		if (!mysqli_query($con, $sql_detalle)) throw new Exception("Error al insertar detalle de factura: " . mysqli_error($con));

		// Guardar formas de pago
		$sql_pago = "INSERT INTO formas_pago_ventas VALUES (null, '$ruc_empresa',
        '$serie_factura', '$numero_factura', '20', '$total_factura')";

		if (!mysqli_query($con, $sql_pago)) throw new Exception("Error al insertar forma de pago: " . mysqli_error($con));

		// Guardar detalles adicionales de la factura
		$sql_detalles_adicionales = [
			"INSERT INTO detalle_adicional_factura VALUES (null, '$ruc_empresa', '$serie_factura', '$numero_factura', 'Email', '$email')",
			"INSERT INTO detalle_adicional_factura VALUES (null, '$ruc_empresa', '$serie_factura', '$numero_factura', 'Dirección', '$direccion')",
			"INSERT INTO detalle_adicional_factura VALUES (null, '$ruc_empresa', '$serie_factura', '$numero_factura', 'Cotización', '$numero_cotizacion')"
		];

		if (!empty($telefono)) {
			$sql_detalles_adicionales[] = "INSERT INTO detalle_adicional_factura VALUES (null, '$ruc_empresa', '$serie_factura', '$numero_factura', 'Teléfono', '$telefono')";
		}

		foreach ($sql_detalles_adicionales as $query) {
			if (!mysqli_query($con, $query)) throw new Exception("Error al insertar detalle adicional: " . mysqli_error($con));
		}

		// Si todo salió bien, confirmar la transacción
		mysqli_commit($con);

		echo "<script>
        $.notify('Factura generada correctamente', 'success');
        document.querySelector('#guardar_factura_cotizacion_publicidad').reset();
    </script>";
	} catch (Exception $e) {
		mysqli_rollback($con);
		echo "<script>
        $.notify('Error: No es posible guardar. Detalle: " . addslashes($e->getMessage()) . "', 'error');
    </script>";
	}

	// Cerrar conexión
	mysqli_close($con);
}
?>