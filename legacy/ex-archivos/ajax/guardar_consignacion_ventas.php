<?php
include("../validadores/generador_codigo_unico.php");
include("../conexiones/conectalogin.php");
$con = conenta_login();
session_start();
ini_set('date.timezone', 'America/Guayaquil');
$fecha_actual = date("Y/m/d");
$hora_actual = date('H:i');
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
$fecha_consignacion_salida = date('Y/m/d', strtotime($_POST["fecha_consignacion_salida"]));
$id_cliente_consignacion_venta = $_POST["id_cliente_consignacion_venta"];
$observacion_consignacion_venta = mysqli_real_escape_string($con, (strip_tags($_POST["observacion_consignacion_venta"], ENT_QUOTES)));
$inventario = mysqli_real_escape_string($con, (strip_tags($_POST["inventario"], ENT_QUOTES)));
$serie_sucursal = mysqli_real_escape_string($con, (strip_tags($_POST["serie_consignacion"], ENT_QUOTES)));
$punto_partida = mysqli_real_escape_string($con, (strip_tags($_POST["punto_partida"], ENT_QUOTES)));
$punto_llegada = mysqli_real_escape_string($con, (strip_tags($_POST["punto_llegada"], ENT_QUOTES)));
$responsable_traslado = mysqli_real_escape_string($con, (strip_tags($_POST["responsable_traslado"], ENT_QUOTES)));
$codigo_unico_modificar = mysqli_real_escape_string($con, (strip_tags($_POST["codigo_unico"], ENT_QUOTES)));
$fecha_entrega = mysqli_real_escape_string($con, (strip_tags(date('Y/m/d', strtotime($_POST['fecha_pedido'])), ENT_QUOTES)));
$hora_entrega_desde = mysqli_real_escape_string($con, (strip_tags(date('H:i', strtotime($_POST['hora_entrega_desde'])), ENT_QUOTES)));
$hora_entrega_hasta = mysqli_real_escape_string($con, (strip_tags(date('H:i', strtotime($_POST['hora_entrega_hasta'])), ENT_QUOTES)));
$traslado_por = mysqli_real_escape_string($con, (strip_tags($_POST["traslado"], ENT_QUOTES)));

$fecha_registro = date("Y-m-d H:i:s");
$codigo_unico = codigo_unico(19) . "1"; //el numero uno es para identificar que es una nuevaonsignacion

$sql_factura_temporal = mysqli_query($con, "SELECT fat_tmp.tarifa_ice as nup, 
fat_tmp.precio_tmp as precio, fat_tmp.id_producto as id_producto, pro_ser.codigo_producto as codigo_producto, 
fat_tmp.cantidad_tmp as cantidad, pro_ser.nombre_producto as nombre_producto, fat_tmp.id_medida as medida, 
fat_tmp.id_bodega as bodega, fat_tmp.vencimiento as vencimiento, fat_tmp.lote as lote, fat_tmp.subtotal as subtotal 
FROM factura_tmp as fat_tmp INNER JOIN productos_servicios as pro_ser ON fat_tmp.id_producto = pro_ser.id 
INNER JOIN bodega as bod ON fat_tmp.id_bodega=bod.id_bodega 
INNER JOIN unidad_medida as uni_med ON fat_tmp.id_medida=uni_med.id_medida 
WHERE fat_tmp.id_usuario = '" . $id_usuario . "' and fat_tmp.ruc_empresa = '" . $ruc_empresa . "' ");
$count = mysqli_num_rows($sql_factura_temporal);

if (empty($_POST["fecha_consignacion_salida"])) {
	echo "<script>
	$.notify('Ingrese fecha de emisión.','error');
	</script>";
} else if (!date($_POST["fecha_consignacion_salida"])) {
	echo "<script>$.notify('Ingrese fecha de emisión correcta.','error');
				</script>";
} else if ($fecha_consignacion_salida < $fecha_actual) {
	echo "<script>
	$.notify('La fecha de emisión no puede ser menor a la actual','error');
	</script>";
} else if (empty($_POST["responsable_traslado"])) {
	echo "<script>$.notify('Seleccione un asesor.','error');
				</script>";
} else if (empty($_POST["id_cliente_consignacion_venta"])) {
	echo "<script>$.notify('Agregue un cliente.','error');
							</script>";
} else if ($fecha_entrega < $fecha_actual) {
	echo "<script>
	$.notify('La fecha de entrega no puede ser menor a la actual.','error');
	</script>";
} else if (empty($_POST['hora_entrega_desde'])) {
	echo "<script>
	$.notify('Ingrese hora de entrega desde','error');
	</script>";
} else if (empty($_POST['hora_entrega_hasta'])) {
	echo "<script>
	$.notify('Ingrese hora de entrega hasta','error');
	</script>";
} else if (substr($_POST['hora_entrega_desde'], 0, 2) < 0) {
	echo "<script>
	$.notify('Hora de entrega incorrecta, ingrese entre 1 y 24','error');
	</script>";
} else if (substr($_POST['hora_entrega_hasta'], 0, 2) < 0) {
	echo "<script>
	$.notify('Hora de entrega hasta incorrecta, ingrese entre 1 y 24','error');
	</script>";
} else if (substr($_POST['hora_entrega_desde'], 0, 2) > 24) {
	echo "<script>
	$.notify('Hora de entrega desde incorrecta, ingrese entre 1 y 24','error');
	</script>";
} else if (substr($_POST['hora_entrega_hasta'], 0, 2) > 24) {
	echo "<script>
	$.notify('Hora de entrega hasta incorrecta, ingrese entre 1 y 24','error');
	</script>";
} else if (substr($_POST['hora_entrega_desde'], 3, 2) < 0) {
	echo "<script>
	$.notify('Minutos de hora de entrega desde incorrectos, ingrese entre 1 y 60','error');
	</script>";
} else if (substr($_POST['hora_entrega_hasta'], 3, 2) < 0) {
	echo "<script>
	$.notify('Minutos de hora de entrega hasta incorrectos, ingrese entre 1 y 60','error');
	</script>";
} else if (substr($_POST['hora_entrega_desde'], 3, 2) > 60) {
	echo "<script>
	$.notify('Minutos de hora de entrega desde incorrectos, ingrese entre 1 y 60','error');
	</script>";
} else if (substr($_POST['hora_entrega_hasta'], 3, 2) > 60) {
	echo "<script>
	$.notify('Minutos de hora de entrega hasta incorrectos, ingrese entre 1 y 60','error');
	</script>";
} else if ($hora_entrega_hasta < $hora_entrega_desde) {
	echo "<script>
	$.notify('La hora hasta no puede ser menor a la hora desde.','error');
	</script>";
} else if ($count == 0) {
	echo "<script>
	$.notify('No hay detalle de productos.','error');
	</script>";
} else if (empty($ruc_empresa)) {
	echo "<script>
	$.notify('La sesión ha expirado, reingrese al sistema.','error');
	</script>";
} else if (empty($id_usuario)) {
	echo "<script>
	$.notify('La sesión ha expirado, reingrese al sistema.','error');
	</script>";
} else {
	$consulta_ultima_orden = mysqli_query($con, "SELECT max(numero_consignacion) as ultimo FROM encabezado_consignacion WHERE ruc_empresa='" . $ruc_empresa . "' and tipo_consignacion='VENTA' and operacion='ENTRADA'");
	$row_ultimo = mysqli_fetch_array($consulta_ultima_orden);
	$siguiente_orden = $row_ultimo['ultimo'] + 1;

	$select_encabezado_consignacion = mysqli_query($con, "SELECT * FROM encabezado_consignacion WHERE codigo_unico='" . $codigo_unico_modificar . "'");
	$row_encabezado = mysqli_fetch_array($select_encabezado_consignacion);
	$numero_consignacion = $row_encabezado['numero_consignacion'];

	$select_detalle_consignacion = mysqli_query($con, "SELECT * FROM detalle_consignacion WHERE numero_orden_entrada='" . $numero_consignacion . "' and ruc_empresa='" . $ruc_empresa . "'");
	$registros_relacionados = mysqli_num_rows($select_detalle_consignacion);

	if ($registros_relacionados > 0 && !empty($codigo_unico_modificar)) {
		echo "<script>$.notify('No es posible actualizar, existen registros de facturas o devoluciones.','error');
				</script>";
		exit;
	}

	if (!empty($codigo_unico_modificar)) {
		//para actualizar consignacion
		$actualiza_encabezado_consignacion = mysqli_query($con, "UPDATE encabezado_consignacion SET fecha_consignacion='" . $fecha_consignacion_salida . "', 
			id_cli_pro= '" . $id_cliente_consignacion_venta . "', observaciones='" . $observacion_consignacion_venta . "', 
			fecha_registro='" . $fecha_registro . "', id_usuario='" . $id_usuario . "', punto_partida='" . $punto_partida . "', 
			punto_llegada='" . $punto_llegada . "', responsable='" . $responsable_traslado . "', 
			serie_sucursal='" . $serie_sucursal . "', fecha_entrega='" . $fecha_entrega . "', 
			hora_entrega_desde= '" . $hora_entrega_desde . "', 
			hora_entrega_hasta= '" . $hora_entrega_hasta . "', traslado_por = '" . $traslado_por . "'
			 WHERE codigo_unico='" . $codigo_unico_modificar . "' ");
		//eliminar detalle y en el inventario
		$elimina_detalle_consignacion = mysqli_query($con, "DELETE FROM detalle_consignacion WHERE codigo_unico='" . $codigo_unico_modificar . "'");
		$eliminar_registros_inventario = mysqli_query($con, "DELETE FROM inventarios WHERE id_documento_venta = '" . $codigo_unico_modificar . "'");
		guarda_detalle_inventario($con, $sql_factura_temporal, $ruc_empresa, $inventario, $codigo_unico_modificar, $fecha_consignacion_salida, $numero_consignacion, $serie_sucursal);
		if (isset($_SESSION['conciliacion_pedido'])) {
			actualiza_pedido($_SESSION['conciliacion_pedido'], $con, $numero_consignacion, $id_usuario);
		}
		echo "<script>$.notify('Consignación actualizada.','success');
				setTimeout(function (){location.reload()}, 1000);
				</script>";
	} else {
		//para guardar nueva consignacion
		$select_consignacion = mysqli_query($con, "SELECT * FROM encabezado_consignacion WHERE numero_consignacion='" . $siguiente_orden . "' and ruc_empresa='" . $ruc_empresa . "' and operacion='ENTRADA'");
		$row_num_consignacion = mysqli_fetch_array($select_consignacion);
		$numero_existente = $row_num_consignacion['numero_consignacion'];

		if ($numero_existente == $siguiente_orden) {
			$siguiente_orden = $siguiente_orden + 1;
		} else {
			$siguiente_orden = $siguiente_orden;
		}

		$encabezado_consignacion = mysqli_query($con, "INSERT INTO encabezado_consignacion VALUES (null,'" . $fecha_consignacion_salida . "',
			'" . $ruc_empresa . "','" . $codigo_unico . "','" . $id_cliente_consignacion_venta . "',
			'VENTA','" . $siguiente_orden . "','" . $observacion_consignacion_venta . "',
			'" . $fecha_registro . "','" . $id_usuario . "','" . $punto_partida . "',
			'" . $punto_llegada . "','" . $responsable_traslado . "', 'ENTRADA','" . $serie_sucursal . "',
			'','" . $fecha_entrega . "','" . $hora_entrega_desde . "', '" . $hora_entrega_hasta . "','" . $traslado_por . "','1')");
		guarda_detalle_inventario($con, $sql_factura_temporal, $ruc_empresa, $inventario, $codigo_unico, $fecha_consignacion_salida, $siguiente_orden, $serie_sucursal);
		if (isset($_SESSION['conciliacion_pedido'])) {
			actualiza_pedido($_SESSION['conciliacion_pedido'], $con, $siguiente_orden, $id_usuario);
		}

		echo "<script>$.notify('Nueva consignación guardada.','success');
						setTimeout(function (){location.reload()}, 1000);
						</script>";
	}
}

//actualizar detalle pedidos
function actualiza_pedido($data, $con, $numero_consignacion, $id_usuario)
{
	$id_pedido = array();
	$ids_pedidos = array();
	foreach ($data as $detalle_pedido) {
		$select_detalle_pedido = mysqli_query($con, "SELECT * FROM detalle_pedido WHERE id='" . $detalle_pedido['id_detalle'] . "'");
		$row_detalle_pedido = mysqli_fetch_array($select_detalle_pedido);
		$despachado = $row_detalle_pedido['despachado'] + $detalle_pedido['cantidad'];
		$id_pedido[] = $row_detalle_pedido['id_pedido'];
		$observaciones_existentes = $row_detalle_pedido['observaciones'];
		$observaciones = ' CV:' . $numero_consignacion;
		if ($observaciones_existentes == $observaciones) {
			$observaciones = "";
		} else {
			$observaciones = $row_detalle_pedido['observaciones'] . ' CV:' . $numero_consignacion;
		}
		$actualiza_detalle_pedidos = mysqli_query($con, "UPDATE detalle_pedido SET despachado = '" . $despachado . "', observaciones = '" . $observaciones . "' WHERE id= '" . $detalle_pedido['id_detalle'] . "' ");
	}

	$ids_pedidos = array_unique($id_pedido);
	for ($i = 0; $i < count($ids_pedidos); $i++) {
		$actualiza_encabezado_pedidos = mysqli_query($con, "UPDATE encabezado_pedido SET status = '2', id_usuario_mod='" . $id_usuario . "', dateedited='" . date("Y-m-d H:i:s") . "' WHERE id= '" . $ids_pedidos[$i] . "' ");
	}
	unset($data);
}


if (isset($errors)) {

?>
	<div class="alert alert-danger" role="alert">
		<button type="button" class="close" data-dismiss="alert">&times;</button>
		<strong>Error!</strong>
		<?php
		foreach ($errors as $error) {
			echo $error;
		}
		?>
	</div>
<?php
}
if (isset($messages)) {

?>
	<div class="alert alert-success" role="alert">
		<button type="button" class="close" data-dismiss="alert">&times;</button>
		<strong>¡Bien hecho!</strong>
		<?php
		foreach ($messages as $message) {
			echo $message;
		}
		?>
	</div>
<?php
}

function guarda_detalle_inventario($con, $sql_factura_temporal, $ruc_empresa, $inventario, $codigo_unico, $fecha_consignacion_salida, $numero_consignacion, $serie_sucursal)
{

	while ($row_detalle = mysqli_fetch_array($sql_factura_temporal)) {
		$id_producto = $row_detalle['id_producto'];
		$codigo_producto = $row_detalle['codigo_producto'];
		$nombre_producto = $row_detalle['nombre_producto'];
		$cantidad = $row_detalle['cantidad'];
		$bodega = $row_detalle['bodega'];
		$medida = $row_detalle['medida'];
		$lote = $row_detalle['lote'];
		$nup = $row_detalle['nup'];
		$precio = $row_detalle['precio'];

		$busca_vencimiento = mysqli_query($con, "SELECT * FROM inventarios WHERE id_producto = '" . $id_producto . "' and lote= '" . $lote . "' and operacion='ENTRADA'");
		$row_vencimiento = mysqli_fetch_array($busca_vencimiento);
		$vencimiento = date('Y-m-d', strtotime($row_vencimiento['fecha_vencimiento']));

		$detalle_consignacion = mysqli_query($con, "INSERT INTO detalle_consignacion VALUES (null,'" . $id_producto . "','" . $codigo_producto . "','" . $nombre_producto . "','" . $lote . "','" . $vencimiento . "','" . $bodega . "','" . $medida . "','" . $ruc_empresa . "','" . $codigo_unico . "','" . $cantidad . "','0','" . $nup . "','" . $precio . "','0')");
	}
}
?>