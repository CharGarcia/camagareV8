<?php
include("../clases/contabilizacion.php");
//include("../helpers/helpers.php");
include("../validadores/generador_codigo_unico.php");
if (empty($_POST['fecha_factura_consignacion_venta'])) {
	echo "<script>
		$.notify('Ingrese fecha para la factura.','error');
		</script>";
} else if (!date($_POST['fecha_factura_consignacion_venta'])) {
	echo "<script>
		$.notify('Ingrese una fecha correcta.','error');
		</script>";
} else if (empty($_POST['id_cliente_factura_consignacion_venta'])) {
	echo "<script>
		$.notify('Seleccione un cliente.','error');
		</script>";
} else if ($_POST['vendedor'] == "0") {
	echo "<script>
		$.notify('Seleccione un asesor.','error');
		</script>";
} else if ((!empty($_POST['fecha_factura_consignacion_venta'])) && (!empty($_POST['id_cliente_factura_consignacion_venta'])) && (!empty($_POST['vendedor']))) {

	/* Connect To Database*/
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
	session_start();
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$id_usuario = $_SESSION['id_usuario'];

	$fecha_consignacion_salida = date('Y-m-d H:i:s', strtotime(mysqli_real_escape_string($con, (strip_tags($_POST["fecha_factura_consignacion_venta"], ENT_QUOTES)))));
	$id_cliente_consignacion_venta = mysqli_real_escape_string($con, (strip_tags($_POST["id_cliente_factura_consignacion_venta"], ENT_QUOTES)));
	$observacion_consignacion_venta = mysqli_real_escape_string($con, (strip_tags($_POST["observacion_factura_consignacion_venta"], ENT_QUOTES)));
	$adi_concepto = mysqli_real_escape_string($con, (strip_tags($_POST["adi_concepto"], ENT_QUOTES)));
	$adi_detalle = mysqli_real_escape_string($con, (strip_tags($_POST["adi_detalle"], ENT_QUOTES)));
	$id_vendedor = mysqli_real_escape_string($con, (strip_tags($_POST["vendedor"], ENT_QUOTES)));
	$serie_sucursal = mysqli_real_escape_string($con, (strip_tags($_POST["serie_factura_consignacion"], ENT_QUOTES)));
	ini_set('date.timezone', 'America/Guayaquil');
	$fecha_registro = date("Y-m-d H:i:s");
	$codigo_unico = codigo_unico(19) . "3"; //para cuando genera una factura

	$sql_factura_temporal = mysqli_query($con, "SELECT fat_tmp.tarifa_botellas as numero_consignacion, 
		fat_tmp.tarifa_ice as nup, fat_tmp.id_producto as id_producto, pro_ser.codigo_producto as codigo_producto, 
		fat_tmp.cantidad_tmp as cantidad, pro_ser.nombre_producto as nombre_producto, fat_tmp.id_bodega as bodega, 
		fat_tmp.vencimiento as vencimiento, fat_tmp.lote as lote, fat_tmp.id_medida as medida, 
		fat_tmp.precio_tmp as precio, fat_tmp.descuento as descuento, fat_tmp.subtotal as subtotal 
		FROM factura_tmp as fat_tmp INNER JOIN productos_servicios as pro_ser 
		ON fat_tmp.id_producto = pro_ser.id WHERE fat_tmp.id_usuario = '" . $id_usuario . "' 
		and fat_tmp.ruc_empresa = '" . $ruc_empresa . "' ");
	$count = mysqli_num_rows($sql_factura_temporal);
	if ($count == 0) {
		echo "<script>
		$.notify('No hay detalle de productos para facturar.','error');
		</script>";
	} else {
		$consulta_ultima_orden = mysqli_query($con, "SELECT max(numero_consignacion) as ultimo FROM encabezado_consignacion WHERE ruc_empresa='" . $ruc_empresa . "' and tipo_consignacion='VENTA' and operacion ='FACTURA'");
		$row_ultimo = mysqli_fetch_array($consulta_ultima_orden);
		$siguiente_orden = $row_ultimo['ultimo'] + 1;

		$numero_factura = siguiente_factura($con, $ruc_empresa, $serie_sucursal);

		$encabezado_consignacion = mysqli_query($con, "INSERT INTO encabezado_consignacion VALUES (null,'" . $fecha_consignacion_salida . "','" . $ruc_empresa . "','" . $codigo_unico . "','" . $id_cliente_consignacion_venta . "','VENTA','" . $siguiente_orden . "','" . $observacion_consignacion_venta . "','" . $fecha_registro . "','" . $id_usuario . "','','','" . $id_vendedor . "', 'FACTURA','" . $serie_sucursal . "','" . $numero_factura . "','','','','','1')");

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
			$descuento = $row_detalle['descuento'];
			$numero_consignacion = $row_detalle['numero_consignacion'];

			$busca_vencimiento = mysqli_query($con, "SELECT * FROM inventarios WHERE id_producto = '" . $id_producto . "' and lote= '" . $lote . "' and operacion='ENTRADA'");
			$row_vencimiento = mysqli_fetch_array($busca_vencimiento);
			$vencimiento = date('Y-m-d', strtotime($row_vencimiento['fecha_vencimiento']));

			$detalle_consignacion = mysqli_query($con, "INSERT INTO detalle_consignacion VALUES (null,'" . $id_producto . "','" . $codigo_producto . "','" . $nombre_producto . "','" . $lote . "','" . $vencimiento . "','" . $bodega . "','" . $medida . "','" . $ruc_empresa . "','" . $codigo_unico . "','" . $cantidad . "','" . $numero_consignacion . "','" . $nup . "','" . $precio . "', '" . $descuento . "')");
		}

		echo generar_factura($fecha_consignacion_salida, $con, $ruc_empresa, $id_usuario, $serie_sucursal, $codigo_unico, $id_cliente_consignacion_venta, $observacion_consignacion_venta, $numero_factura, $adi_concepto, $adi_detalle, $id_vendedor);
	}
} else {
	$errors[] = "Error desconocido.";
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

function siguiente_factura($con, $ruc_empresa, $serie)
{
	// hay que contar cuantos registros existen
	$cuenta_facturas = mysqli_query($con, "SELECT * FROM encabezado_factura WHERE ruc_empresa = '" . $ruc_empresa . "' and serie_factura = '" . $serie . "' and tipo_factura = 'ELECTRÓNICA'");
	$count = mysqli_num_rows($cuenta_facturas);

	//la factura inicial segun se configura en el sistema camagare
	$busca_factura_inicial = "SELECT inicial_factura FROM sucursales WHERE ruc_empresa = '" . $ruc_empresa . "' and serie = '" . $serie . "'";
	$result = $con->query($busca_factura_inicial);
	$inicial_factura = mysqli_fetch_row($result);
	$inicial = $inicial_factura['0'];

	if ($count == 0) {
		echo ($inicial);
	} else {
		$busca_factura = "SELECT MIN(secuencial_factura) as minimo, MAX(secuencial_factura) as maximo FROM encabezado_factura WHERE ruc_empresa = '" . $ruc_empresa . "' and serie_factura = '" . $serie . "' and tipo_factura = 'ELECTRÓNICA'";
		$result = $con->query($busca_factura);
		$res_sql = mysqli_fetch_assoc($result);
		$factura_inicial = intval($inicial); //$res_sql['minimo'];
		$factura_final = intval($res_sql['maximo']) + 1;

		$serie_inicio_fin = array();
		foreach (range($inicial, $factura_final) as $toda_la_serie) {
			$serie_inicio_fin[] = intval($toda_la_serie);
		}
		$facturas_registradas = "SELECT secuencial_factura as facturas FROM encabezado_factura WHERE ruc_empresa = '" . $ruc_empresa . "' and serie_factura = '" . $serie . "' and tipo_factura = 'ELECTRÓNICA' and secuencial_factura >= '" . $inicial . "' ";
		$result_todas = $con->query($facturas_registradas);

		$solo_registradas = array();
		while ($todas_las_encontradas = mysqli_fetch_array($result_todas)) {
			$solo_registradas[] = intval($todas_las_encontradas['facturas']);
		}

		$facturas_faltantes = array_diff($serie_inicio_fin, $solo_registradas);
		if ($facturas_faltantes == false) {
			return ($factura_final);
		} else {
			return min($facturas_faltantes);
		}
	}
}

function generar_factura($fecha_consignacion_salida, $con, $ruc_empresa, $id_usuario, $serie_facturar, $codigo_unico, $id_cliente, $observaciones, $numero_factura, $adi_concepto, $adi_detalle, $id_vendedor)
{

	ini_set('date.timezone', 'America/Guayaquil');
	$fecha_registro = date("Y-m-d H:i:s");
	$sql_detalle_numero_consignacion = mysqli_query($con, "SELECT * FROM detalle_consignacion WHERE codigo_unico = '" . $codigo_unico . "' ");
	$sql_detalle_por_facturar = mysqli_query($con, "SELECT id_producto, codigo_producto, nombre_producto, sum(cant_consignacion) as cant_consignacion, sum(descuento) as descuento, id_bodega, id_medida, lote, vencimiento, precio FROM detalle_consignacion WHERE codigo_unico = '" . $codigo_unico . "' group by id_producto, lote, precio ");
	$iva = array();
	$subtotal = array();
	$total_descuento = array();
	$numero_consignacion = array();
	while ($row_detalle_numero = mysqli_fetch_array($sql_detalle_numero_consignacion)) {
		$numero_consignacion[] = $row_detalle_numero['numero_orden_entrada'];
	}
	//para guardar encabezado de factura
	$guarda_encabezado_factura = mysqli_query($con, "INSERT INTO encabezado_factura VALUES (null, '" . $ruc_empresa . "','" . $fecha_consignacion_salida . "','" . $serie_facturar . "','" . $numero_factura . "','" . $id_cliente . "','','','" . $fecha_registro . "','POR COBRAR','ELECTRÓNICA','PENDIENTE', '0.00', '" . $id_usuario . "','0','0','','PENDIENTE',0,0)");
	//para guardar el vendedor
	$busca_ultimo_registro = mysqli_query($con, "SELECT * FROM encabezado_factura WHERE id_encabezado_factura= LAST_INSERT_ID()");
	$row_ultimo_registro = mysqli_fetch_array($busca_ultimo_registro);
	$id_encabezado_factura = $row_ultimo_registro['id_encabezado_factura'];

	while ($row_detalle = mysqli_fetch_array($sql_detalle_por_facturar)) {
		$id_producto = $row_detalle['id_producto'];
		$codigo_producto = $row_detalle['codigo_producto'];
		$lote = $row_detalle['lote'];
		$nombre_producto = $row_detalle['nombre_producto'] . " Lt " . $lote;
		$cantidad = $row_detalle['cant_consignacion'];
		$id_bodega = $row_detalle['id_bodega'];
		$id_medida = $row_detalle['id_medida'];
		$vencimiento = $row_detalle['vencimiento'];
		//para traer tipo de tarifas
		$sql_tarifas = mysqli_query($con, "SELECT * FROM productos_servicios as pro 
						INNER JOIN tarifa_iva as tar ON tar.codigo=pro.tarifa_iva WHERE pro.id= '" . $id_producto . "' ");
		$row_tarifas = mysqli_fetch_array($sql_tarifas);
		$tipo_produccion = $row_tarifas['tipo_produccion'];
		$tarifa_iva = $row_tarifas['tarifa_iva'];
		$tarifa_ice = $row_tarifas['tarifa_ice'];
		$tarifa_bp = $row_tarifas['tarifa_botellas'];
		$precio = $row_detalle['precio'];
		$descuento = $row_detalle['descuento'];
		$total_descuento[] = $descuento;
		$subtotal_item = $cantidad * $precio;
		$subtotal[] = $cantidad * $precio;
		if ($tarifa_iva > 0) {
			$iva[] = ($subtotal_item - $descuento) * ($row_tarifas['porcentaje_iva'] / 100);
		}
		$guarda_detalle_factura = mysqli_query($con, "INSERT INTO cuerpo_factura VALUES (null, 
																								'" . $ruc_empresa . "',
																								'" . $serie_facturar . "',
																								'" . $numero_factura . "',
																								'" . $id_producto . "',
																								'" . $cantidad . "',
																								'" . $precio . "',
																								'" . $subtotal_item . "',
																								'" . $tipo_produccion . "',
																								'" . $tarifa_iva . "',
																								'" . $tarifa_ice . "',
																								'" . $tarifa_bp . "',
																								'" . $descuento . "',
																								'" . $codigo_producto . "',
																								'" . $nombre_producto . "',
																								'" . $id_medida . "',
																								'" . $lote . "',
																								'" . $vencimiento . "',
																								'" . $id_bodega . "')");
	}

	//dep(mysqli_error($con));

	$total_factura = number_format(array_sum($subtotal) - array_sum($total_descuento) + array_sum($iva), 2, '.', '');
	$quitar_repetidos = array_unique($numero_consignacion);
	$ordenes_consignacion = implode("-", $quitar_repetidos);

	//para guardar el vendedor
	$guardar_asesor = mysqli_query($con, "INSERT INTO vendedores_ventas VALUES (null,'" . $id_vendedor . "','" . $id_encabezado_factura . "', '" . $fecha_registro . "' ,'" . $id_usuario . "')");

	//para guardar la forma de pago de la factura
	$query_forma_pago_factura = mysqli_query($con, "INSERT INTO formas_pago_ventas VALUES (null, '" . $ruc_empresa . "','" . $serie_facturar . "','" . $numero_factura . "','20', '" . $total_factura . "')");

	$sql_cliente = mysqli_query($con, "SELECT * FROM clientes WHERE id= '" . $id_cliente . "' ");
	$row_clientes = mysqli_fetch_array($sql_cliente);

	$sql_vendedor = mysqli_query($con, "SELECT * FROM vendedores WHERE id_vendedor= '" . $id_vendedor . "' ");
	$row_vendedor = mysqli_fetch_array($sql_vendedor);

	// para guardar detalle adicional factura
	$query_guarda_detalle_adicional_factura = mysqli_query($con, "INSERT INTO detalle_adicional_factura VALUES (null, '" . $ruc_empresa . "','" . $serie_facturar . "','" . $numero_factura . "','Email','" . $row_clientes['email'] . "')");
	$query_guarda_detalle_adicional_factura = mysqli_query($con, "INSERT INTO detalle_adicional_factura VALUES (null, '" . $ruc_empresa . "','" . $serie_facturar . "','" . $numero_factura . "','Dirección','" . $row_clientes['direccion'] . "')");
	$query_guarda_detalle_adicional_factura = mysqli_query($con, "INSERT INTO detalle_adicional_factura VALUES (null, '" . $ruc_empresa . "','" . $serie_facturar . "','" . $numero_factura . "','Teléfono','" . $row_clientes['telefono'] . "')");
	$query_guarda_detalle_adicional_factura = mysqli_query($con, "INSERT INTO detalle_adicional_factura VALUES (null, '" . $ruc_empresa . "','" . $serie_facturar . "','" . $numero_factura . "','No. CV','" . $ordenes_consignacion . "')");
	if (!empty($adi_concepto) && !empty($adi_detalle)) {
		$query_guarda_detalle_adicional_factura = mysqli_query($con, "INSERT INTO detalle_adicional_factura VALUES (null, '" . $ruc_empresa . "','" . $serie_facturar . "','" . $numero_factura . "','" . $adi_concepto . "','" . $adi_detalle . "')");
	}
	if (!empty($observaciones)) {
		$query_guarda_detalle_adicional_factura = mysqli_query($con, "INSERT INTO detalle_adicional_factura VALUES (null, '" . $ruc_empresa . "','" . $serie_facturar . "','" . $numero_factura . "','Observaciones','" . $observaciones . "')");
	}
	if (($id_vendedor != 0)) {
		$query_guarda_detalle_adicional_factura = mysqli_query($con, "INSERT INTO detalle_adicional_factura VALUES (null, '" . $ruc_empresa . "','" . $serie_facturar . "','" . $numero_factura . "','Asesor','" . $row_vendedor['nombre'] . "')");
	}

	//actualizar el total de la factura
	$update_totales_factura = mysqli_query($con, "UPDATE encabezado_factura SET total_factura= '" . $total_factura . "' WHERE id_encabezado_factura= '" . $id_encabezado_factura . "' ");

	if ($guarda_encabezado_factura && $guarda_detalle_factura && $query_forma_pago_factura && $query_guarda_detalle_adicional_factura && $update_totales_factura) {
		//guardar el asiento contable
		$contabilizacion = new contabilizacion();
		$contabilizacion->documentosVentasFacturas($con, $ruc_empresa, $fecha_consignacion_salida, $fecha_consignacion_salida);
		$guardar_asientos_contables_generados = $contabilizacion->guardar_asientos_contables_generados($con, $ruc_empresa, $id_usuario, 'ventas');

		echo "<script>
		$.notify('Factura guardada.','success');
		setTimeout(function (){location.reload()}, 1000);
		</script>";
	} else {
		echo "<script>
		$.notify('Lo siento la factura no se guardó.','error');
		</script>";
	}
}

?>