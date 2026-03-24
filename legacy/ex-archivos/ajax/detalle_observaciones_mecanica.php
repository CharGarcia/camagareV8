<?PHP
include("../conexiones/conectalogin.php");
session_start();
$con = conenta_login();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];

if (!isset($ruc_empresa) && !isset($id_usuario)) {
	echo "<script>
				$.notify('la conexión a internet ha fallado, vuelva a ingresar al sistema.','error');
				setTimeout(function (){location.href ='../includes/logout.php'}, 2000);
			</script>";
	exit;
}

$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
// para actualizar fechas
if ($action == 'actualizar_fechas') {
	$codigo_unico = $_POST['codigo_unico'];
	$estado = $_POST['estado'];

	$fecha_entrada = date('Y-m-d H:i:s', strtotime($_POST['fecha_entrada']));
	$hora_entrada = date('H:i:s', strtotime($_POST['hora_entrada']));
	$fecha_salida = date('Y-m-d H:i:s', strtotime($_POST['fecha_salida']));
	$hora_salida = date('H:i:s', strtotime($_POST['hora_salida']));
	if ($estado == "CERRADA") {
		$datos = "fecha_recepcion='" . $fecha_entrada . "', hora_recepcion='" . $hora_entrada . "', fecha_entrega='" . $fecha_salida . "', hora_entrega='" . $hora_salida . "'";
	} else {
		$datos = "fecha_recepcion='" . $fecha_entrada . "', hora_recepcion='" . $hora_entrada . "', fecha_entrega='" . $fecha_salida . "', hora_entrega='" . $hora_salida . "', estado='" . $estado . "'";
	}
	echo actualizar_mecanica($datos, $codigo_unico, $con);
}

// para actualizar vehiculo
if ($action == 'actualizar_vehiculo') {
	$codigo_unico = $_POST['codigo_unico'];
	$mod_placa = $_POST['mod_placa'];
	$mod_marca = $_POST['mod_marca'];
	$mod_anio = $_POST['mod_anio'];
	$mod_propietario = $_POST['mod_propietario'];
	$mod_chasis = $_POST['mod_chasis'];
	$busca_orden = mysqli_query($con, "SELECT * FROM encabezado_mecanica WHERE codigo_unico='" . $codigo_unico . "' ");
	$row_orden = mysqli_fetch_array($busca_orden);
	$estado_orden = $row_orden['estado'];
	if ($estado_orden != 'CERRADA') {
		$sql_update = mysqli_query($con, "UPDATE vehiculos SET marca='" . $mod_marca . "', placa='" . $mod_placa . "', chasis='" . $mod_chasis . "', anio='" . $mod_anio . "',propietario='" . $mod_propietario . "' WHERE codigo_unico='" . $codigo_unico . "'");
		echo "<script>
	$.notify('Registro actualizado.','success');
	</script>";
	} else {
		echo "<script>
		$.notify('No es posible actualizar, la orden está cerrada.','error');
		</script>";
	}
}

// para actualizar usuario
if ($action == 'actualizar_usuario') {
	$codigo_unico = $_POST['codigo_unico'];
	$mod_usuario = $_POST['mod_usuario'];
	$mod_telefono = $_POST['mod_telefono'];
	$mod_correo_usuario = $_POST['mod_correo_usuario'];
	$datos = "nombre_usuario='" . $mod_usuario . "', contacto_usuario='" . $mod_telefono . "', correo_usuario='" . $mod_correo_usuario . "'";
	echo actualizar_mecanica($datos, $codigo_unico, $con);
}

// para actualizar proxima cita
if ($action == 'actualizar_proxima_cita') {
	$codigo_unico = $_POST['codigo_unico'];
	$proxima_cita = date('Y-m-d H:i:s', strtotime($_POST['proxima_cita']));
	$obs_proxima_cita = $_POST['obs_proxima_cita'];
	$datos = "proximo_chequeo='" . $proxima_cita . "', obs_prox_chequeo='" . $obs_proxima_cita . "'";
	echo actualizar_proxima_cita($datos, $codigo_unico, $con);
}


function actualizar_proxima_cita($datos, $codigo_unico, $con)
{
	$sql_update = mysqli_query($con, "UPDATE encabezado_mecanica SET $datos WHERE codigo_unico='" . $codigo_unico . "'");
	echo "<script>
	$.notify('Registro actualizado.','success');
	</script>";
}

function actualizar_mecanica($datos, $codigo_unico, $con)
{
	$busca_orden = mysqli_query($con, "SELECT * FROM encabezado_mecanica WHERE codigo_unico='" . $codigo_unico . "' ");
	$row_orden = mysqli_fetch_array($busca_orden);
	$estado_orden = $row_orden['estado'];
	if ($estado_orden != 'CERRADA') {
		$sql_update = mysqli_query($con, "UPDATE encabezado_mecanica SET $datos WHERE codigo_unico='" . $codigo_unico . "'");
		echo "<script>
	$.notify('Registro actualizado.','success');
	</script>";
	} else {
		echo "<script>
		$.notify('No es posible actualizar, la orden está cerrada.','error');
		</script>";
	}
}



//para agregar un detalle a la orden
if ($action == 'agregar_observaciones') {
	$codigo_unico = $_GET['codigo_unico'];
	$fecha_agregado = date("Y-m-d H:i:s");
	$concepto = mysqli_real_escape_string($con, (strip_tags($_GET["concepto"], ENT_QUOTES)));
	$detalle = mysqli_real_escape_string($con, (strip_tags($_GET["detalle"], ENT_QUOTES)));

	$busca_orden = mysqli_query($con, "SELECT * FROM encabezado_mecanica WHERE codigo_unico='" . $codigo_unico . "' ");
	$row_orden = mysqli_fetch_array($busca_orden);
	$estado_orden = $row_orden['estado'];
	if ($estado_orden != 'CERRADA') {
		$detalle_observaciones = mysqli_query($con, "INSERT INTO observaciones_mecanica VALUES (null, '" . $ruc_empresa . "', '" . $codigo_unico . "', '" . $concepto . "', '" . $detalle . "')");
		muestra_detalle_observaciones_mecanica();
	} else {
		echo "<script>
		$.notify('No es posible agregar, la orden está cerrada.','error');
		</script>";
		muestra_detalle_observaciones_mecanica();
	}
}

if ($action == 'muestra_detalle_factura') {
	muestra_detalle_factura_mecanica();
}

//para agregar un detalle a la factura
if ($action == 'agregar_detalle_factura') {
	$codigo_unico = $_GET['codigo_unico'];
	$fecha_agregado = date("Y-m-d H:i:s");
	if (!include_once("../clases/control_salidas_inventario.php")) {
		include_once("../clases/control_salidas_inventario.php");
	}
	include("../validadores/generador_codigo_unico.php");
	$guarda_salida_inventario = new control_salida_inventario();
	$id_producto_mecanica = mysqli_real_escape_string($con, (strip_tags($_GET["id_producto_mecanica"], ENT_QUOTES)));
	$cantidad_agregar = mysqli_real_escape_string($con, (strip_tags($_GET["cantidad_agregar"], ENT_QUOTES)));
	$precio_agregar = mysqli_real_escape_string($con, (strip_tags($_GET["precio_agregar"], ENT_QUOTES)));
	$fecha_emision = date('Y-m-d', strtotime($_GET['fecha_emision']));
	$bodega_agregar = mysqli_real_escape_string($con, (strip_tags($_GET["bodega_agregar"], ENT_QUOTES)));
	$tipo_producto_mecanica = mysqli_real_escape_string($con, (strip_tags($_GET["tipo_producto_mecanica"], ENT_QUOTES)));
	$serie_mecanica = mysqli_real_escape_string($con, (strip_tags($_GET["serie_mecanica"], ENT_QUOTES)));
	$medida_agregar = mysqli_real_escape_string($con, (strip_tags($_GET["medida_agregar"], ENT_QUOTES)));
	$tipo_produccion = mysqli_real_escape_string($con, (strip_tags($_GET["tipo_producto_agregar"], ENT_QUOTES)));
	$inventario = mysqli_real_escape_string($con, (strip_tags($_GET["inventario"], ENT_QUOTES)));

	$busca_producto = mysqli_query($con, "SELECT * FROM productos_servicios WHERE id='" . $id_producto_mecanica . "' ");
	$row_producto = mysqli_fetch_array($busca_producto);
	$tarifa_iva = $row_producto['tarifa_iva'];

	$busca_orden = mysqli_query($con, "SELECT * FROM encabezado_mecanica WHERE codigo_unico='" . $codigo_unico . "' ");
	$row_orden = mysqli_fetch_array($busca_orden);
	$estado_orden = $row_orden['estado'];
	$id_orden = $row_orden['id_enc_mecanica'];
	//$numero_orden = $row_orden['numero_orden'];

	$id_cliente = $row_orden['id_cliente'];
	if ($estado_orden != 'CERRADA') {
		$codigo_unico_registro = codigo_unico(20);
		$detalle_observaciones = mysqli_query($con, "INSERT INTO detalle_factura_mecanica VALUES (null, 
	'" . $ruc_empresa . "', '" . $id_orden . "', '" . $id_producto_mecanica . "', '" . $precio_agregar . "',
	'" . $cantidad_agregar . "', '" . $id_cliente . "', '" . $fecha_emision . "','" . $precio_agregar * $cantidad_agregar . "',
	'0','" . $id_usuario . "', '" . $codigo_unico . "', '" . $bodega_agregar . "',
	'" . $medida_agregar . "','0','0','" . $tipo_producto_mecanica . "','" . $serie_mecanica . "','0', 
	'" . $fecha_agregado . "','" . $codigo_unico_registro . "', '" . $tarifa_iva . "')");

		/*
	if ($inventario == "SI" && $tipo_produccion == "01"){
		$insertar_en_inventario = $guarda_salida_inventario->salidas_desde_mecanica($serie_mecanica, $bodega_agregar, $id_producto_mecanica, $cantidad_agregar, $codigo_unico_registro, $fecha_emision, $referencia_salida_inventario, $medida_agregar, $precio_agregar, '0', '0');												
	}	
	*/
		muestra_detalle_factura_mecanica();
	} else {
		echo "<script>
		$.notify('No es posible agregar, la orden está cerrada.','error');
		</script>";
		muestra_detalle_factura_mecanica();
	}
}

//para eliminar un detalle
if ($action == 'eliminar_observaciones') {
	$id_registro = $_GET['id_registro'];
	$codigo_unico = $_GET['codigo_unico'];
	$busca_orden = mysqli_query($con, "SELECT * FROM encabezado_mecanica WHERE codigo_unico='" . $codigo_unico . "' ");
	$row_orden = mysqli_fetch_array($busca_orden);
	$estado_orden = $row_orden['estado'];
	if ($estado_orden != 'CERRADA') {
		$elimina_detalle = mysqli_query($con, "DELETE FROM observaciones_mecanica WHERE id_obs='" . $id_registro . "'");
		muestra_detalle_observaciones_mecanica();
	} else {
		echo "<script>
					$.notify('No es posible eliminar, la orden está cerrada.','error');
					</script>";
		muestra_detalle_observaciones_mecanica();
	}
}

//para eliminar toda la orden
if ($action == 'eliminar_orden_total') {
	$id_registro = $_GET['id_registro'];
	$busca_orden = mysqli_query($con, "SELECT * FROM encabezado_mecanica WHERE id_enc_mecanica='" . $id_registro . "' ");
	$row_orden = mysqli_fetch_array($busca_orden);
	$estado_orden = $row_orden['estado'];
	$codigo_unico = $row_orden['codigo_unico'];
	if ($estado_orden != 'CERRADA') {
		$elimina_encabezado = mysqli_query($con, "DELETE FROM encabezado_mecanica WHERE id_enc_mecanica='" . $id_registro . "'");
		$eliminar_observaciones = mysqli_query($con, "DELETE FROM observaciones_mecanica WHERE codigo_unico='" . $codigo_unico . "'");

		$busca_codigos = mysqli_query($con, "SELECT * FROM detalle_factura_mecanica WHERE codigo_unico='" . $codigo_unico . "' ");
		while ($row_codigos = mysqli_fetch_array($busca_codigos)) {
			$codigo_unico_registro = $row_codigos['codigo_unico_registro'];
			$eliminar_registros_inventario = mysqli_query($con, "DELETE FROM inventarios WHERE id_documento_venta = '" . $codigo_unico_registro . "'");
		}
		$elimina_detalle_factura = mysqli_query($con, "DELETE FROM detalle_factura_mecanica WHERE codigo_unico='" . $codigo_unico . "'");
		echo "<script>
		$.notify('Registros eliminado','success');
		setTimeout(function (){location.href ='../modulos/orden_mecanica.php'}, 1000);
		</script>";
	} else {
		echo "<script>
		$.notify('No es posible eliminar, la orden está terminada.','error');
		setTimeout(function (){location.href ='../modulos/orden_mecanica.php'}, 1000);
		</script>";
	}
}
//eliminar detalle de factura
if ($action == 'eliminar_detalle_factura') {
	$id_registro = $_GET['id_registro'];
	$codigo_unico = $_GET['codigo_unico'];
	//para ver el codigo unico y eliminar en el inventario
	$busca_detalle = mysqli_query($con, "SELECT * FROM detalle_factura_mecanica WHERE id_detalle='" . $id_registro . "' ");
	$row_detalle = mysqli_fetch_array($busca_detalle);
	$codigo_unico_registro = $row_detalle['codigo_unico_registro'];
	//para ver si la orden esta cerrada
	$busca_orden = mysqli_query($con, "SELECT * FROM encabezado_mecanica WHERE codigo_unico='" . $codigo_unico . "' ");
	$row_orden = mysqli_fetch_array($busca_orden);
	$estado_orden = $row_orden['estado'];
	if ($estado_orden != 'CERRADA') {
		$elimina_detalle_factura = mysqli_query($con, "DELETE FROM detalle_factura_mecanica WHERE id_detalle='" . $id_registro . "'");
		$elimina_detalle_inventario = mysqli_query($con, "DELETE FROM inventarios WHERE id_documento_venta='" . $codigo_unico_registro . "'");
		muestra_detalle_factura_mecanica();
	} else {
		echo "<script>
			$.notify('No es posible eliminar, la orden está cerrada.','error');
			</script>";
		muestra_detalle_factura_mecanica();
	}
}

//para mostrar los datos 
if ($action == 'detalle_observaciones') {
	muestra_detalle_observaciones_mecanica();
}

//para mostrar los detalles de factura 
if ($action == 'detalle_factura_mecanica') {
	muestra_detalle_factura_mecanica();
}


function muestra_detalle_observaciones_mecanica()
{
	$con = conenta_login();
	if (isset($_GET['codigo_unico'])) {
		$codigo_unico = $_GET['codigo_unico'];
		$busca_detalle = mysqli_query($con, "SELECT * FROM observaciones_mecanica WHERE codigo_unico='" . $codigo_unico . "' order by id_obs asc");

?>
		<div class="panel panel-info">
			<table class="table table-hover">
				<tr class="info">
					<th style="padding: 2px;">Concepto</th>
					<th style="padding: 2px;">Detalle</th>
					<th style="padding: 2px;" class='text-right'>Eliminar</th>
				</tr>
				<?php
				while ($detalle = mysqli_fetch_array($busca_detalle)) {
					$id_obs = $detalle['id_obs'];
					$concepto = $detalle['concepto'];
					$detalle = $detalle['detalle'];

				?>
					<tr>
						<td style="padding: 2px;"><?php echo $concepto; ?></td>
						<td style="padding: 2px;"><?php echo $detalle; ?></td>
						<td style="padding: 2px;" class='text-right'><a href="#" class='btn btn-danger btn-xs' title='Eliminar' onclick="eliminar_detalle_observaciones('<?php echo $id_obs; ?>')"><i class="glyphicon glyphicon-remove"></i></a></td>
					</tr>
				<?php
				}
				?>
			</table>
		</div>
	<?php
	}
}

function muestra_detalle_factura_mecanica()
{
	$con = conenta_login();
	if (isset($_GET['codigo_unico'])) {
		$codigo_unico = $_GET['codigo_unico'];
		$busca_detalle = mysqli_query($con, "SELECT * FROM detalle_factura_mecanica as det_fac 
	INNER JOIN productos_servicios as pro_ser 
	ON det_fac.id_producto=pro_ser.id 
	INNER JOIN tarifa_iva as tar ON tar.codigo=det_fac.tarifa_iva
	WHERE det_fac.codigo_unico='" . $codigo_unico . "' 
	 order by det_fac.id_detalle asc");

		$busca_orden = mysqli_query($con, "SELECT * FROM encabezado_mecanica WHERE codigo_unico='" . $codigo_unico . "' ");
		$row_orden = mysqli_fetch_array($busca_orden);
		$estado_orden = $row_orden['estado'];
		if ($estado_orden == 'CERRADA') {
			$sololee = "readonly";
		} else {
			$sololee = "";
		}
	?>
		<div class="panel panel-info">
			<div class="table-responsive">
				<table class="table table-hover">
					<tr class="info">
						<th style="padding: 2px;">Descripción</th>
						<th style="padding: 2px;">Cantidad</th>
						<th style="padding: 2px;">SinIVA</th>
						<th style="padding: 2px;">ConIVA</th>
						<th style="padding: 2px;">Descuento</th>
						<th style="padding: 2px;">IVA</th>
						<th style="padding: 2px;" class='text-right'>Subtotal</th>
						<th style="padding: 2px;" class='text-right'>Eliminar</th>
					</tr>
					<?php
					//$sutotal_a_pagar = array();
					//$iva = array();
					$total_a_pagar = 0;
					$suma_cantidad = 0;
					$suma_coniva = 0;
					$suma_siniva = 0;
					$suma_descuento = 0;
					$suma_subtotal = 0;
					$suma_iva = 0;
					while ($detalle = mysqli_fetch_array($busca_detalle)) {
						$id_detalle = $detalle['id_detalle'];
						$nombre_producto = $detalle['nombre_producto'];
						$cantidad = $detalle['cantidad'];
						$precio = $detalle['precio'];
						$descuento = $detalle['descuento'];
						$subtotal = number_format($detalle['subtotal'], 2, '.', '');
						$nombre_tarifa = $detalle['tarifa'];
						$porcentaje_iva = number_format(($detalle['porcentaje_iva'] / 100), 2, '.', '');

						//$sutotal_a_pagar[] = $subtotal;
						//$iva[] = number_format((($cantidad * $precio) - $descuento) * ($detalle['porcentaje_iva'] / 100), 2, '.', '');

						$precio_sin_iva = number_format($precio, 4, '.', '');
						$precio_con_iva = number_format($precio + ($precio * $porcentaje_iva), 4, '.', '');

						$suma_cantidad += $cantidad;
						$suma_coniva += $precio_con_iva;

						$suma_siniva += $precio_sin_iva;
						$suma_descuento += $descuento;

					?>
						<tr>
							<input type="hidden" id="subtotal_item<?php echo $id_detalle; ?>" value="<?php echo number_format($subtotal + $descuento, 2, '.', ''); ?>">
							<input type="hidden" value="<?php echo $subtotal; ?>" id="subtotal<?php echo $id_detalle; ?>">
							<input type="hidden" id="descuento_inicial<?php echo $id_detalle; ?>" value="<?php echo $descuento; ?>">
							<input type="hidden" id="porcentaje_item<?php echo $id_detalle; ?>" value="<?php echo $porcentaje_iva; ?>">
							<input type="hidden" id="precio_sin_iva_inicial<?php echo $id_detalle; ?>" value="<?php echo $precio_sin_iva; ?>">
							<input type="hidden" id="precio_con_iva_inicial<?php echo $id_detalle; ?>" value="<?php echo $precio_con_iva; ?>">
							<input type="hidden" id="cantidad_inicial<?php echo $id_detalle; ?>" value="<?php echo $cantidad; ?>">

							<td style="padding: 2px;"><?php echo $nombre_producto; ?></td>
							<td style="padding: 2px;" class="col-sm-1"><input type="text" style="text-align:right; height:20px;" class="form-control input-sm" title="Cantidad del producto" id="cantidad_producto<?php echo $id_detalle; ?>" onchange="actualiza_cantidad('<?php echo $id_detalle; ?>');" value="<?php echo $cantidad; ?>" <?php echo $sololee; ?>></td>
							<td style="padding: 2px;" class="col-sm-1"><input type="text" style="text-align:right; height:20px;" class="form-control input-sm" title="Precio del producto sin IVA" id="precio_item_sin_iva<?php echo $id_detalle; ?>" onchange="precio_item_sin_iva('<?php echo $id_detalle; ?>');" value="<?php echo $precio_sin_iva; ?>" <?php echo $sololee; ?>></td>
							<td style="padding: 2px;" class="col-sm-1"><input type="text" style="text-align:right; height:20px;" class="form-control input-sm" title="Precio del producto con IVA" id="precio_item_con_iva<?php echo $id_detalle; ?>" onchange="precio_item_con_iva('<?php echo $id_detalle; ?>');" value="<?php echo $precio_con_iva; ?>" <?php echo $sololee; ?>></td>
							<td style="padding: 2px;" class="col-sm-1"><input type="text" style="text-align:right; height:20px;" class="form-control input-sm" title="Descuento" id="descuento_item<?php echo $id_detalle; ?>" onchange="descuento_item('<?php echo $id_detalle; ?>');" value="<?php echo $descuento; ?>" <?php echo $sololee; ?>></td>
							<td style="padding: 2px;"><?php echo $nombre_tarifa; ?></td>
							<td style="padding: 2px;" align="right"><?php echo $subtotal; ?></td>
							<td style="padding: 2px;" class='text-right'><a href="#" class='btn btn-danger btn-xs' title='Eliminar' onclick="eliminar_detalle_factura('<?php echo $id_detalle; ?>')"><i class="glyphicon glyphicon-remove"></i></a></td>
						</tr>
					<?php
					}

					$sql_subtotales = mysqli_query($con, "SELECT round(sum(subtotal - descuento),2) as subtotal,
					round(sum(descuento),2) as descuento
					 FROM detalle_factura_mecanica WHERE codigo_unico='" . $codigo_unico . "' order by id_detalle asc");
					$row_subtotales = mysqli_fetch_assoc($sql_subtotales);
					$subtotal_general = isset($row_subtotales['subtotal']) ? $row_subtotales['subtotal'] : 0;
					$total_descuento = isset($row_subtotales['descuento']) ? $row_subtotales['descuento'] : 0;

					?>
					<tr class="info">
						<td style="padding: 2px;" colspan="6" align="right">Subtotal</td>
						<td style="padding: 2px;" align="right"><?php echo $subtotal_general; ?></td>
						<td style="padding: 2px;"></td>
					</tr>
					<tr class="info">
						<td style="padding: 2px;" colspan="6" align="right">Descuento</td>
						<td style="padding: 2px;" align="right"><?php echo $total_descuento; ?></td>
						<td style="padding: 2px;"></td>
					</tr>
					<?php
					//PARA MOSTRAR LOS IVAS
					$total_iva = 0;
					$suma_iva = 0;
					$sql_iva = mysqli_query($con, "SELECT round(sum((ft.cantidad * ft.precio - ft.descuento) * (ti.porcentaje_iva/100)),2) as total_iva  
			FROM detalle_factura_mecanica as ft 
			INNER JOIN tarifa_iva as ti ON ti.codigo = ft.tarifa_iva 
			WHERE ft.codigo_unico='" . $codigo_unico . "' 
			and ti.porcentaje_iva > 0 group by ft.tarifa_iva ");
					while ($row = mysqli_fetch_assoc($sql_iva)) {
						$total_iva =  $row['total_iva'];
						$suma_iva +=  $row['total_iva'];
					?>
						<tr class="info">
							<td style="padding: 2px;" colspan="6" align="right">IVA</td>
							<td style="padding: 2px;" align="right"><?php echo $total_iva; ?></td>
							<td style="padding: 2px;"></td>
						</tr>
					<?php
					}
					$total_a_pagar = number_format($subtotal_general + $suma_iva, 2, '.', '');
					?>
					<tr class="info">
						<td style="padding: 2px;" colspan="6" align="right">Total</td>
						<td style="padding: 2px;" align="right"><input type="hidden" id="total_factura" name="total_factura" value="<?php echo number_format($total_a_pagar, 2, '.', ''); ?>"><b> <?php echo number_format($total_a_pagar, 2, '.', ''); ?> </b></td>
						<td style="padding: 2px;"></td>
					</tr>
				</table>
			</div>
		</div>
<?php
	}
}

if ($action == 'calculo_precio_item') {
	$id_tmp = intval($_POST['id']);
	$precio = $_POST['precio'];
	$update = mysqli_query($con, "UPDATE detalle_factura_mecanica SET precio='" . number_format($precio, 4, '.', '') . "', subtotal= round(precio * cantidad - descuento, 4) WHERE id_detalle='" . $id_tmp . "'");
	echo "<script>
	$.notify('Precio actualizado','info');
	</script>";
}


if ($action == 'actualiza_cantidad') {
	$id_tmp = intval($_POST['id']);
	$cantidad_producto = $_POST['cantidad_producto'];
	$update = mysqli_query($con, "UPDATE detalle_factura_mecanica SET cantidad='" . number_format($cantidad_producto, 4, '.', '') . "', subtotal= round(precio * cantidad - descuento,4) WHERE id_detalle='" . $id_tmp . "'");
	echo "<script>
	$.notify('Cantidad actualizada','info');
	</script>";
}

if ($action == 'actualiza_descuento_item') {
	$id_tmp = intval($_POST['id']);
	$descuento_item = $_POST['descuento_item'];
	$update = mysqli_query($con, "UPDATE detalle_factura_mecanica SET descuento='" . number_format($descuento_item, 4, '.', '') . "', subtotal= round(precio * cantidad - descuento,4) WHERE id_detalle='" . $id_tmp . "'");
	echo "<script>
	$.notify('Descuento actualizado','info');
	</script>";
}
?>