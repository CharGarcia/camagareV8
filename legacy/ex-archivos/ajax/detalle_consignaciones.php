<?PHP
include("../conexiones/conectalogin.php");
require_once("../helpers/helpers.php");
session_start();
$con = conenta_login();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];

$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';


if ($action == 'actualizar_asesor') {
	$id_asesor = $_POST['id_asesor'];
	$codigo_unico = $_POST['codigo_unico'];
	$update = mysqli_query($con, "UPDATE encabezado_consignacion SET responsable= '" . $id_asesor . "' WHERE codigo_unico= '" . $codigo_unico . "' ");
	if ($update) {
		echo "<script>$.notify('Actualizado','success');
	</script>";
	} else {
		echo "<script>$.notify('Intente de nuevo','error');
	</script>";
	}
}



//para actualizar el descuento de cada item
/* if ($action == 'actualiza_descuento_item') {
	$descuento_item = $_POST['descuento_item'];
	$id = $_POST['id'];
	$serie_factura = $_POST['serie_factura'];
	$update = mysqli_query($con, "UPDATE factura_tmp SET descuento='" . $descuento_item . "', subtotal= round(precio_tmp * cantidad_tmp,2) WHERE id='" . $id . "'");
	detalle_nueva_factura_consignacion_ventas();
} */

if ($action == 'actualiza_descuento_item') {
	// Entradas
	$descuento_item = isset($_POST['descuento_item']) ? trim($_POST['descuento_item']) : '';
	$id             = isset($_POST['id']) ? $_POST['id'] : '';
	$serie_factura  = isset($_POST['serie_factura']) ? $_POST['serie_factura'] : '';

	// Validaciones básicas
	if ($id === '' || !is_numeric($id)) {
		echo "<div class='alert alert-danger'>ID de ítem inválido.</div>";
		exit;
	}
	// Normaliza número (permite coma o punto)
	$descuento_item = str_replace(',', '.', $descuento_item);
	if ($descuento_item === '' || !is_numeric($descuento_item)) {
		echo "<div class='alert alert-danger'>Descuento inválido.</div>";
		exit;
	}
	$descuento_item = (float)$descuento_item;
	if ($descuento_item < 0) {
		$descuento_item = 0.0;
	}

	// Actualiza con prepared statement
	$sql = "
        UPDATE factura_tmp
           SET descuento = ?, 
               subtotal  = ROUND(precio_tmp * cantidad_tmp, 2)
         WHERE id = ?
         /* Si tienes multiempresa en esta tabla, agrega:
            AND ruc_empresa = ? */
    ";

	if ($stmt = mysqli_prepare($con, $sql)) {
		// Bind: double (descuento), int (id)
		mysqli_stmt_bind_param($stmt, 'di', $descuento_item, $id);
		// Si agregas ruc_empresa en el WHERE, usa: mysqli_stmt_bind_param($stmt, 'dis', $descuento_item, $id, $ruc_empresa);

		if (!mysqli_stmt_execute($stmt)) {
			echo "<div class='alert alert-danger'>No se pudo actualizar el descuento.</div>";
			mysqli_stmt_close($stmt);
			exit;
		}
		mysqli_stmt_close($stmt);
	} else {
		echo "<div class='alert alert-danger'>Error preparando la consulta.</div>";
		exit;
	}

	// Mantén tu flujo actual
	if (function_exists('detalle_nueva_factura_consignacion_ventas')) {
		// Si tu función acepta la serie, pásala; si no, quedará igual que antes
		try {
			$ref = new ReflectionFunction('detalle_nueva_factura_consignacion_ventas');
			if ($ref->getNumberOfParameters() >= 1) {
				detalle_nueva_factura_consignacion_ventas($serie_factura);
			} else {
				detalle_nueva_factura_consignacion_ventas();
			}
		} catch (Exception $e) {
			// Si no existe Reflection (o falla), llama sin parámetro como tenías
			detalle_nueva_factura_consignacion_ventas();
		}
	} else {
		echo "<div class='alert alert-success'>Descuento actualizado.</div>";
	}
}


//para actualizar el descuento de todos los item
/* if ($action == 'aplicar_descuento_todos') {
	$porcentaje_descuento = $_POST['porcentaje_descuento'];
	$serie_factura = $_POST['serie_factura'];
	$update = mysqli_query($con, "UPDATE factura_tmp SET descuento= subtotal * '" . $porcentaje_descuento . "' /100, subtotal= round(precio_tmp * cantidad_tmp,2) WHERE id_usuario='" . $id_usuario . "' and ruc_empresa='" . $ruc_empresa . "'");
	detalle_nueva_factura_consignacion_ventas();
} */

if ($action == 'aplicar_descuento_todos') {
	// Entradas
	$porcentaje_descuento = isset($_POST['porcentaje_descuento']) ? trim($_POST['porcentaje_descuento']) : '';
	$serie_factura        = isset($_POST['serie_factura']) ? $_POST['serie_factura'] : '';

	// Validaciones y normalización
	if ($porcentaje_descuento === '') {
		echo "<div class='alert alert-danger'>Ingresa un porcentaje.</div>";
		exit;
	}
	// Permitir coma o punto
	$porcentaje_descuento = str_replace(',', '.', $porcentaje_descuento);
	if (!is_numeric($porcentaje_descuento)) {
		echo "<div class='alert alert-danger'>Porcentaje inválido.</div>";
		exit;
	}
	$porcentaje_descuento = (float)$porcentaje_descuento;
	if ($porcentaje_descuento < 0)   $porcentaje_descuento = 0;
	if ($porcentaje_descuento > 100) $porcentaje_descuento = 100;

	// Convertir a fracción
	$pct = $porcentaje_descuento / 100.0;

	// Update masivo seguro:
	// - descuento = ROUND(ROUND(precio*cantidad,2) * pct, 2)
	// - subtotal  = ROUND(precio*cantidad, 2)
	$sql = "
        UPDATE factura_tmp
           SET descuento = ROUND(ROUND(precio_tmp * cantidad_tmp, 2) * ?, 2),
               subtotal  = ROUND(precio_tmp * cantidad_tmp, 2)
         WHERE id_usuario = ?
           AND ruc_empresa = ?
    ";

	if ($stmt = mysqli_prepare($con, $sql)) {
		mysqli_stmt_bind_param($stmt, 'dss', $pct, $id_usuario, $ruc_empresa);
		if (!mysqli_stmt_execute($stmt)) {
			echo "<div class='alert alert-danger'>No se pudo aplicar el descuento a todos.</div>";
			mysqli_stmt_close($stmt);
			exit;
		}
		mysqli_stmt_close($stmt);
	} else {
		echo "<div class='alert alert-danger'>Error preparando la consulta.</div>";
		exit;
	}

	// Mantén tu flujo actual de render
	if (function_exists('detalle_nueva_factura_consignacion_ventas')) {
		try {
			$ref = new ReflectionFunction('detalle_nueva_factura_consignacion_ventas');
			if ($ref->getNumberOfParameters() >= 1) {
				detalle_nueva_factura_consignacion_ventas($serie_factura);
			} else {
				detalle_nueva_factura_consignacion_ventas();
			}
		} catch (Exception $e) {
			detalle_nueva_factura_consignacion_ventas();
		}
	} else {
		echo "<div class='alert alert-success'>Descuentos aplicados.</div>";
	}
}



//para agregar nuevo iten del detalle de consignacion cargada a la nueva factura a generar
 if ($action == 'agregar_detalle_facturacion_consignacion_venta') {
	$id_detalle_consignacion = intval($_POST['id']);
	$cantidad = $_POST["cantidad"];
	$precio = $_POST["precio"];
	$descuento = $_POST["descuento"];
	$numero_consignacion = $_POST["numero_consignacion"];
	$serie_factura = $_POST["serie_factura"];

	$detalle_item = mysqli_query($con, "SELECT * FROM detalle_consignacion as det INNER JOIN productos_servicios as pro ON pro.id=det.id_producto WHERE det.id_det_consignacion = '" . $id_detalle_consignacion . "' ");
	$row_detalle = mysqli_fetch_array($detalle_item);
	$id_producto = $row_detalle['id_producto'];
	$lote = $row_detalle['lote'];

	$busca_vencimiento = mysqli_query($con, "SELECT * FROM inventarios WHERE id_producto = '" . $id_producto . "' and lote= '" . $lote . "' and operacion='ENTRADA'");
	$row_vencimiento = mysqli_fetch_array($busca_vencimiento);
	$vencimiento = date('y-m-d', strtotime($row_vencimiento['fecha_vencimiento']));

	$arrayItemFacturar = array();
	$arrayDatos = array(
		'id' => $id_detalle_consignacion,
		'numero_consignacion' => $numero_consignacion,
		'serie_factura' => $serie_factura,
		'cantidad' => $cantidad,
		'precio' => $precio,
		'descuento' => $descuento,
		'id_producto' => $row_detalle['id_producto'],
		'codigo_producto' => $row_detalle['codigo_producto'],
		'nombre_producto' => $row_detalle['nombre_producto'],
		'bodega' => $row_detalle['id_bodega'],
		'medida' => $row_detalle['id_medida'],
		'lote' => $row_detalle['lote'],
		'nup' => $row_detalle['nup'],
		'tarifa_iva' => $row_detalle['tarifa_iva'],
		'vencimiento' => $vencimiento
	);
	if (isset($_SESSION['arrayItemFacturar'])) {
		$on = true;
		$arrayItemFacturar = $_SESSION['arrayItemFacturar'];
		for ($pr = 0; $pr < count($arrayItemFacturar); $pr++) {
			if ($arrayItemFacturar[$pr]['id'] == $id_detalle_consignacion) {
				$arrayItemFacturar[$pr]['cantidad'] = $cantidad;
				$arrayItemFacturar[$pr]['precio'] = $precio;
				$arrayItemFacturar[$pr]['descuento'] = $descuento;
				$on = false;
			}
		}
		if ($on) {
			array_push($arrayItemFacturar, $arrayDatos);
		}
		$_SESSION['arrayItemFacturar'] = $arrayItemFacturar;
	} else {
		array_push($arrayItemFacturar, $arrayDatos);
		$_SESSION['arrayItemFacturar'] = $arrayItemFacturar;
	}
} 

/* if ($action == 'agregar_detalle_facturacion_consignacion_venta') {
	// --- 1) Entradas y validación básica ---
	$id_detalle_consignacion = isset($_POST['id']) ? intval($_POST['id']) : 0;
	$cantidad            = isset($_POST['cantidad']) ? str_replace(',', '.', trim($_POST['cantidad'])) : '0';
	$precio              = isset($_POST['precio']) ? str_replace(',', '.', trim($_POST['precio'])) : '0';
	$descuento           = isset($_POST['descuento']) ? str_replace(',', '.', trim($_POST['descuento'])) : '0';
	$numero_consignacion = isset($_POST['numero_consignacion']) ? trim($_POST['numero_consignacion']) : '';
	$serie_factura       = isset($_POST['serie_factura']) ? trim($_POST['serie_factura']) : '';

	if ($id_detalle_consignacion <= 0) {
		exit;
	} // ID inválido, no hacemos nada silenciosamente
	if (!is_numeric($cantidad)) {
		$cantidad = '0';
	}
	if (!is_numeric($precio)) {
		$precio = '0';
	}
	if (!is_numeric($descuento)) {
		$descuento = '0';
	}

	$cantidad  = max(0.0, (float)$cantidad);
	$precio    = max(0.0, (float)$precio);
	$descuento = max(0.0, (float)$descuento);

	// --- 2) Consultas seguras (solo columnas necesarias) ---
	// detalle_consignacion + productos_servicios
	$sqlDet = "SELECT det.id_producto, det.id_bodega, det.id_medida, det.lote, det.nup, det.tarifa_iva,
                      pro.codigo_producto, pro.nombre_producto
                 FROM detalle_consignacion AS det
                 INNER JOIN productos_servicios AS pro ON pro.id = det.id_producto
                WHERE det.id_det_consignacion = ?
                LIMIT 1";
	$stmtDet = mysqli_prepare($con, $sqlDet);
	if (!$stmtDet) {
		exit;
	}
	mysqli_stmt_bind_param($stmtDet, 'i', $id_detalle_consignacion);
	mysqli_stmt_execute($stmtDet);
	$resDet = mysqli_stmt_get_result($stmtDet);
	$row_detalle = $resDet ? mysqli_fetch_assoc($resDet) : null;
	mysqli_stmt_close($stmtDet);

	if (!$row_detalle) {
		exit;
	} // no existe el detalle

	$id_producto = (int)$row_detalle['id_producto'];
	$lote        = $row_detalle['lote'];

	// inventarios (vencimiento) — filtra por ENTRADA y limita 1
	// (si manejas multiempresa en inventarios, agrega AND ruc_empresa = ?)
	$sqlVto = "SELECT fecha_vencimiento
                 FROM inventarios
                WHERE id_producto = ?
                  AND lote = ?
                  AND operacion = 'ENTRADA'
                ORDER BY fecha_vencimiento DESC
                LIMIT 1";
	$stmtVto = mysqli_prepare($con, $sqlVto);
	if ($stmtVto) {
		mysqli_stmt_bind_param($stmtVto, 'is', $id_producto, $lote);
		mysqli_stmt_execute($stmtVto);
		$resVto = mysqli_stmt_get_result($stmtVto);
		$row_venc = $resVto ? mysqli_fetch_assoc($resVto) : null;
		mysqli_stmt_close($stmtVto);
	} else {
		$row_venc = null;
	}

	$vencimiento = '';
	if (!empty($row_venc['fecha_vencimiento'])) {
		// Usa año con 4 dígitos
		$vencimiento = date('Y-m-d', strtotime($row_venc['fecha_vencimiento']));
	}

	// --- 3) Armar payload limpio para la sesión ---
	$arrayDatos = array(
		'id'                 => $id_detalle_consignacion,
		'numero_consignacion' => $numero_consignacion,
		'serie_factura'      => $serie_factura,
		'cantidad'           => $cantidad,
		'precio'             => $precio,
		'descuento'          => $descuento,
		'id_producto'        => $id_producto,
		'codigo_producto'    => $row_detalle['codigo_producto'],
		'nombre_producto'    => $row_detalle['nombre_producto'],
		'bodega'             => (int)$row_detalle['id_bodega'],
		'medida'             => (int)$row_detalle['id_medida'],
		'lote'               => $row_detalle['lote'],
		'nup'                => $row_detalle['nup'],
		'tarifa_iva'         => $row_detalle['tarifa_iva'],
		'vencimiento'        => $vencimiento
	);

	// --- 4) Escribir/actualizar en la sesión (sin duplicados por ID) ---
	if (!isset($_SESSION['arrayItemFacturar']) || !is_array($_SESSION['arrayItemFacturar'])) {
		$_SESSION['arrayItemFacturar'] = array();
	}

	$arrayItemFacturar = $_SESSION['arrayItemFacturar'];
	$found = false;

	// Busca por 'id' (id_detalle_consignacion)
	for ($i = 0, $n = count($arrayItemFacturar); $i < $n; $i++) {
		if ((int)$arrayItemFacturar[$i]['id'] === $id_detalle_consignacion) {
			// Actualiza campos editables
			$arrayItemFacturar[$i]['cantidad']  = $cantidad;
			$arrayItemFacturar[$i]['precio']    = $precio;
			$arrayItemFacturar[$i]['descuento'] = $descuento;

			// Mantén consistentes datos sensibles (por si cambió en BD)
			$arrayItemFacturar[$i]['codigo_producto'] = $arrayDatos['codigo_producto'];
			$arrayItemFacturar[$i]['nombre_producto'] = $arrayDatos['nombre_producto'];
			$arrayItemFacturar[$i]['bodega']          = $arrayDatos['bodega'];
			$arrayItemFacturar[$i]['medida']          = $arrayDatos['medida'];
			$arrayItemFacturar[$i]['lote']            = $arrayDatos['lote'];
			$arrayItemFacturar[$i]['nup']             = $arrayDatos['nup'];
			$arrayItemFacturar[$i]['tarifa_iva']      = $arrayDatos['tarifa_iva'];
			$arrayItemFacturar[$i]['vencimiento']     = $arrayDatos['vencimiento'];
			$arrayItemFacturar[$i]['numero_consignacion'] = $numero_consignacion;
			$arrayItemFacturar[$i]['serie_factura']       = $serie_factura;

			$found = true;
			break;
		}
	}

	if (!$found) {
		$arrayItemFacturar[] = $arrayDatos;
	}

	$_SESSION['arrayItemFacturar'] = $arrayItemFacturar;
} */


 if ($action == 'items_a_facturar') {
	$numero_cv = $_POST['numero_consignacion'];
	$serie_cv = $_POST['serie_factura'];

	$busca_info_sucursal = mysqli_query($con, "SELECT * FROM sucursales WHERE ruc_empresa = '" . $ruc_empresa . "' and serie = '" . $serie_cv . "' ");
	$info_sucursal = mysqli_fetch_array($busca_info_sucursal);
	$decimal_precio = intval($info_sucursal['decimal_doc']);
	$decimal_cant = intval($info_sucursal['decimal_cant']);

	if (isset($_SESSION['arrayItemFacturar'])) {
		foreach ($_SESSION['arrayItemFacturar'] as $detalle) {
			if ($detalle['cantidad'] > 0 && $detalle['precio'] >= 0) {
				$subtotal = number_format($detalle['cantidad'] * $detalle['precio'], 2, '.', '');
				$agregar_consignacion = mysqli_query($con, "INSERT INTO factura_tmp VALUES (null, '" . $detalle['id_producto'] . "', '" . number_format($detalle['cantidad'], $decimal_cant, '.', '') . "', '" . number_format($detalle['precio'], $decimal_precio, '.', '') . "', '" . $detalle['descuento'] . "','1', '" . $detalle['tarifa_iva'] . "', '" . $detalle['nup'] . "','" . $detalle['numero_consignacion'] . "','" . $id_usuario . "', '" . $detalle['bodega'] . "','" . $detalle['medida'] . "','" . $detalle['lote'] . "','" . $detalle['vencimiento'] . "','" . $subtotal . "','" . $ruc_empresa . "')");
			}
		}
		unset($_SESSION['arrayItemFacturar']);
		echo "<script>$.notify('Agregado a factura','success');
		</script>";
	} else {
		echo "<script>$.notify('No hay items con valores para agregar.','error');
		</script>";
	}
	detalle_nueva_factura_consignacion_ventas();
} 

/* if ($action == 'items_a_facturar') {
	// ---- Entradas ----
	$numero_cv  = isset($_POST['numero_consignacion']) ? trim($_POST['numero_consignacion']) : '';
	$serie_cv   = isset($_POST['serie_factura']) ? trim($_POST['serie_factura']) : '';

	// ---- Lee decimales de la sucursal (prepared) ----
	$decimal_precio = 2;
	$decimal_cant   = 2;

	if ($stmtSuc = mysqli_prepare($con, "SELECT decimal_doc, decimal_cant FROM sucursales WHERE ruc_empresa = ? AND serie = ? LIMIT 1")) {
		mysqli_stmt_bind_param($stmtSuc, 'ss', $ruc_empresa, $serie_cv);
		mysqli_stmt_execute($stmtSuc);
		$resSuc = mysqli_stmt_get_result($stmtSuc);
		if ($resSuc && ($info_sucursal = mysqli_fetch_assoc($resSuc))) {
			$decimal_precio = intval($info_sucursal['decimal_doc']);
			$decimal_cant   = intval($info_sucursal['decimal_cant']);
			if ($decimal_precio < 0 || $decimal_precio > 6) $decimal_precio = 2;
			if ($decimal_cant   < 0 || $decimal_cant   > 6) $decimal_cant   = 2;
		}
		mysqli_stmt_close($stmtSuc);
	}

	if (!isset($_SESSION['arrayItemFacturar']) || !is_array($_SESSION['arrayItemFacturar']) || empty($_SESSION['arrayItemFacturar'])) {
		echo "<script>$.notify('No hay items con valores para agregar.','error');</script>";
		detalle_nueva_factura_consignacion_ventas();
		exit;
	}

	// ---- Transacción ----
	mysqli_begin_transaction($con);
	try {
		// IMPORTANTE: especifica columnas reales de factura_tmp
		$sqlIns = "
            INSERT INTO factura_tmp
                (id_producto, cantidad_tmp, precio_tmp, descuento, tipo_produccion, tarifa_iva, tarifa_ice, tarifa_botellas, id_usuario, id_bodega, id_medida, lote, vencimiento, subtotal, ruc_empresa)
            VALUES
                (?, ?, ?, ?, '1', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
		$stmtIns = mysqli_prepare($con, $sqlIns);
		if (!$stmtIns) {
			throw new Exception('Error preparando INSERT: ' . mysqli_error($con));
		}

		// Bind variables (tipos: i = int, d = double, s = string)
		// id_producto (i), cantidad (d), precio (d), descuento (d), tarifa_iva (s), nup (s), numero_consignacion (s),
		// id_usuario (s|i según diseño), id_bodega (i), id_medida (i), lote (s), fecha_vencimiento (s), subtotal (d), ruc_empresa (s)
		mysqli_stmt_bind_param(
			$stmtIns,
			'idddssssiiissds',
			$id_producto,
			$cantidad_fmt,
			$precio_fmt,
			$descuento_cap,
			$tarifa_iva,
			$nup,
			$numero_consignacion_bind,
			$id_usuario_bind,
			$id_bodega,
			$id_medida,
			$lote,
			$vencimiento,
			$subtotal_calc,
			$ruc_empresa_bind
		);

		// Set constantes de bind que no cambian por ítem
		$numero_consignacion_bind = $numero_cv;
		// según tu esquema, id_usuario suele ser INT. Si lo tienes string, deja 's' arriba; si es int, cambia a 'i' en la máscara
		$id_usuario_bind          = $id_usuario;
		$ruc_empresa_bind         = $ruc_empresa;

		$inserto_algo = false;

		foreach ($_SESSION['arrayItemFacturar'] as $detalle) {
			// ---- Validar cantidad y precio ----
			if (!isset($detalle['cantidad'], $detalle['precio'])) continue;

			// Normaliza numerales (coma/punto) y forzamos no negativos
			$cantidad  = (float) str_replace(',', '.', $detalle['cantidad']);
			$precio    = (float) str_replace(',', '.', $detalle['precio']);
			$descuento = isset($detalle['descuento']) ? (float) str_replace(',', '.', $detalle['descuento']) : 0.0;

			if ($cantidad <= 0 || $precio < 0) continue;

			// Redondeos según configuración de sucursal (para guardar consistentes)
			$cantidad_fmt = (float) number_format($cantidad, $decimal_cant, '.', '');
			$precio_fmt   = (float) number_format($precio,   $decimal_precio, '.', '');

			// Subtotal bruto (2 decimales para cálculos contables)
			$subtotal_calc = (float) number_format($cantidad_fmt * $precio_fmt, 2, '.', '');

			// Capa de seguridad: descuento no debe exceder el subtotal bruto ni ser negativo
			if ($descuento < 0) $descuento = 0.0;
			$descuento_cap = min($descuento, $subtotal_calc);
			// (opcional) Si quieres truncar en lugar de redondear:
			// $descuento_cap = floor($descuento_cap * 100) / 100;

			// Asignar otros campos
			$id_producto   = (int) $detalle['id_producto'];
			$id_bodega     = (int) $detalle['bodega'];
			$id_medida     = (int) $detalle['medida'];
			$lote          = isset($detalle['lote']) ? (string) $detalle['lote'] : '';
			$vencimiento   = !empty($detalle['vencimiento']) ? date('Y-m-d', strtotime($detalle['vencimiento'])) : null; // o '' si tu columna no admite NULL
			$tarifa_iva    = isset($detalle['tarifa_iva']) ? (string) $detalle['tarifa_iva'] : '0';
			$nup           = isset($detalle['nup']) ? (string) $detalle['nup'] : '';

			if (!mysqli_stmt_execute($stmtIns)) {
				throw new Exception('Error insertando ítem: ' . mysqli_stmt_error($stmtIns));
			}
			$inserto_algo = true;
		}

		mysqli_stmt_close($stmtIns);

		if (!$inserto_algo) {
			// Nada válido para insertar
			mysqli_rollback($con);
			echo "<script>$.notify('No hay items válidos para agregar.','error');</script>";
			detalle_nueva_factura_consignacion_ventas();
			exit;
		}

		mysqli_commit($con);

		// Limpia la sesión de staging
		unset($_SESSION['arrayItemFacturar']);

		echo "<script>$.notify('Agregado a factura','success');</script>";
	} catch (Exception $e) {
		mysqli_rollback($con);
		echo "<script>$.notify('No se pudo agregar: " . htmlspecialchars($e->getMessage(), ENT_QUOTES) . "','error');</script>";
	}

	// Mantén tu flujo
	detalle_nueva_factura_consignacion_ventas();
} */



//para cuando es nueva devolucion
if ($action == 'nueva_devolucion') {
	unset($_SESSION['arrayItemDevolver']);
}

/* if ($action == 'agregar_item_a_devolver') {
	$id_detalle_consignacion = intval($_POST['id']);
	$cantidad = $_POST["cantidad"];
	$numero_consignacion = $_POST["numero_consignacion"];
	$serie_factura = $_POST["serie_factura"];

	$detalle_item = mysqli_query($con, "SELECT * FROM detalle_consignacion as det INNER JOIN productos_servicios as pro on pro.id=det.id_producto WHERE det.id_det_consignacion = '" . $id_detalle_consignacion . "' ");
	$row_detalle = mysqli_fetch_array($detalle_item);
	$id_producto = $row_detalle['id_producto'];
	$lote = $row_detalle['lote'];

	$busca_vencimiento = mysqli_query($con, "SELECT * FROM inventarios WHERE id_producto = '" . $id_producto . "' and lote= '" . $lote . "' and operacion='ENTRADA'");
	$row_vencimiento = mysqli_fetch_array($busca_vencimiento);
	$vencimiento = date('Y-m-d', strtotime($row_vencimiento['fecha_vencimiento']));

	$arrayItemDevolver = array();
	$arrayDatos = array(
		'id' => $id_detalle_consignacion,
		'numero_consignacion' => $numero_consignacion,
		'serie_factura' => $serie_factura,
		'cantidad' => $cantidad,
		'id_producto' => $row_detalle['id_producto'],
		'codigo_producto' => $row_detalle['codigo_producto'],
		'nombre_producto' => $row_detalle['nombre_producto'],
		'bodega' => $row_detalle['id_bodega'],
		'medida' => $row_detalle['id_medida'],
		'lote' => $row_detalle['lote'],
		'nup' => $row_detalle['nup'],
		'vencimiento' => $vencimiento
	);

	if ($cantidad > 0) {
		if (isset($_SESSION['arrayItemDevolver'])) {
			$on = true;
			$arrayItemDevolver = $_SESSION['arrayItemDevolver'];
			for ($pr = 0; $pr < count($arrayItemDevolver); $pr++) {
				if ($arrayItemDevolver[$pr]['id'] == $id_detalle_consignacion) {
					unset($arrayItemDevolver[$pr]);
					$on = true;
				}
			}
			if ($on) {
				array_push($arrayItemDevolver, $arrayDatos);
			}
			$_SESSION['arrayItemDevolver'] = $arrayItemDevolver;
		} else {
			array_push($arrayItemDevolver, $arrayDatos);
			$_SESSION['arrayItemDevolver'] = $arrayItemDevolver;
		}
	}
} */

if ($action == 'agregar_item_a_devolver') {
	// 1) Entradas y validación básica
	$id_detalle_consignacion = isset($_POST['id']) ? intval($_POST['id']) : 0;
	$cantidad                = isset($_POST['cantidad']) ? str_replace(',', '.', trim($_POST['cantidad'])) : '0';
	$numero_consignacion     = isset($_POST['numero_consignacion']) ? trim($_POST['numero_consignacion']) : '';
	$serie_factura           = isset($_POST['serie_factura']) ? trim($_POST['serie_factura']) : '';

	if ($id_detalle_consignacion <= 0) {
		exit;
	}
	if (!is_numeric($cantidad)) {
		$cantidad = '0';
	}
	$cantidad = (float)$cantidad;
	if ($cantidad <= 0) {
		exit;
	} // no se agrega nada si no hay cantidad válida

	// 2) Detalle consignación + producto (solo columnas necesarias)
	$sqlDet = "SELECT det.id_producto, det.id_bodega, det.id_medida, det.lote, det.nup,
                      pro.codigo_producto, pro.nombre_producto
                 FROM detalle_consignacion AS det
                 INNER JOIN productos_servicios AS pro ON pro.id = det.id_producto
                WHERE det.id_det_consignacion = ?
                LIMIT 1";
	if (!($stmtDet = mysqli_prepare($con, $sqlDet))) {
		exit;
	}
	mysqli_stmt_bind_param($stmtDet, 'i', $id_detalle_consignacion);
	mysqli_stmt_execute($stmtDet);
	$resDet = mysqli_stmt_get_result($stmtDet);
	$row_detalle = $resDet ? mysqli_fetch_assoc($resDet) : null;
	mysqli_stmt_close($stmtDet);

	if (!$row_detalle) {
		exit;
	} // no existe el detalle

	$id_producto = (int)$row_detalle['id_producto'];
	$lote        = (string)$row_detalle['lote'];

	// 3) Vencimiento desde inventarios (operación ENTRADA)
	$vencimiento = '';
	$sqlVto = "SELECT fecha_vencimiento
                 FROM inventarios
                WHERE id_producto = ? AND lote = ? AND operacion = 'ENTRADA'
                ORDER BY fecha_vencimiento DESC
                LIMIT 1";
	if ($stmtVto = mysqli_prepare($con, $sqlVto)) {
		mysqli_stmt_bind_param($stmtVto, 'is', $id_producto, $lote);
		mysqli_stmt_execute($stmtVto);
		$resVto = mysqli_stmt_get_result($stmtVto);
		if ($resVto && ($row_vto = mysqli_fetch_assoc($resVto)) && !empty($row_vto['fecha_vencimiento'])) {
			$vencimiento = date('Y-m-d', strtotime($row_vto['fecha_vencimiento']));
		}
		mysqli_stmt_close($stmtVto);
	}

	// 4) Payload limpio para la sesión
	$arrayDatos = array(
		'id'                 => $id_detalle_consignacion,
		'numero_consignacion' => $numero_consignacion,
		'serie_factura'      => $serie_factura,
		'cantidad'           => $cantidad,
		'id_producto'        => $id_producto,
		'codigo_producto'    => $row_detalle['codigo_producto'],
		'nombre_producto'    => $row_detalle['nombre_producto'],
		'bodega'             => (int)$row_detalle['id_bodega'],
		'medida'             => (int)$row_detalle['id_medida'],
		'lote'               => $lote,
		'nup'                => $row_detalle['nup'],
		'vencimiento'        => $vencimiento
	);

	// 5) Escribir/actualizar en la sesión (sin duplicados ni “huecos”)
	if (!isset($_SESSION['arrayItemDevolver']) || !is_array($_SESSION['arrayItemDevolver'])) {
		$_SESSION['arrayItemDevolver'] = array();
	}

	$arrayItemDevolver = $_SESSION['arrayItemDevolver'];
	$foundIndex = -1;

	for ($i = 0, $n = count($arrayItemDevolver); $i < $n; $i++) {
		if ((int)$arrayItemDevolver[$i]['id'] === $id_detalle_consignacion) {
			$foundIndex = $i;
			break;
		}
	}

	if ($foundIndex >= 0) {
		// Reemplaza el ítem existente (evita duplicados)
		$arrayItemDevolver[$foundIndex] = $arrayDatos;
	} else {
		// Agrega nuevo
		$arrayItemDevolver[] = $arrayDatos;
	}

	// Reindexa por si acaso (buena práctica si hubo unset en otros flujos)
	$_SESSION['arrayItemDevolver'] = array_values($arrayItemDevolver);
}


//para agregar nuevo detalle a la consignacion de ventas
/* if ($action == 'agregar_detalle_consignacion_venta') {
	$fecha_agregado = date("Y-m-d H:i:s");
	$id_producto = mysqli_real_escape_string($con, (strip_tags($_GET["id_producto"], ENT_QUOTES)));
	$cantidad_agregar = mysqli_real_escape_string($con, (strip_tags($_GET["cantidad_agregar"], ENT_QUOTES)));
	$nup_agregar = mysqli_real_escape_string($con, (strip_tags($_GET["nup"], ENT_QUOTES)));
	$lote_agregar = mysqli_real_escape_string($con, (strip_tags($_GET["lote_agregar"], ENT_QUOTES)));
	$bodega_agregar = mysqli_real_escape_string($con, (strip_tags($_GET["bodega_agregar"], ENT_QUOTES)));
	$medida_agregar = mysqli_real_escape_string($con, (strip_tags($_GET["medida_agregar"], ENT_QUOTES)));
	$caducidad_agregar = mysqli_real_escape_string($con, (strip_tags($_GET["caducidad_agregar"], ENT_QUOTES)));
	$inventario = mysqli_real_escape_string($con, (strip_tags($_GET["inventario"], ENT_QUOTES)));
	$precio = mysqli_real_escape_string($con, (strip_tags($_GET["precio"], ENT_QUOTES)));
	$id_detalle_pedido = isset($_GET["id"]) ? $_GET["id"] : 0;

	$buscar_item_repetido = mysqli_query($con, "SELECT * FROM factura_tmp WHERE id_producto='" . $id_producto . "' and lote='" . $lote_agregar . "' and tarifa_ice='" . $nup_agregar . "' and id_usuario='" . $id_usuario . "' and ruc_empresa='" . $ruc_empresa . "'");
	$items_repetidos = mysqli_num_rows($buscar_item_repetido);
	if ($items_repetidos > 0) {
		echo "<script>
		$.notify('Producto ya agregado con este lote y NUP','error');
		</script>";
	} else {
		$subtotal = number_format($cantidad_agregar * $precio, 2, '.', '');
		$agregar_consignacion = mysqli_query($con, "INSERT INTO factura_tmp VALUES (null, '" . $id_producto . "', '" . $cantidad_agregar . "', '" . $precio . "', '0','1', '0', '" . $nup_agregar . "','0','" . $id_usuario . "', '" . $bodega_agregar . "','" . $medida_agregar . "','" . $lote_agregar . "','" . $caducidad_agregar . "','" . $subtotal . "','" . $ruc_empresa . "')");
		$lastid = mysqli_insert_id($con);
		if ($id_detalle_pedido > 0) {
			add_detalle_pedido_tmp($lastid, $id_detalle_pedido, $cantidad_agregar);
		}
	}
	detalle_nueva_consignacion_venta();
} */

if ($action == 'agregar_detalle_consignacion_venta') {
	$fecha_agregado = date("Y-m-d H:i:s");

	// 1) Entradas (se están enviando por GET; ideal migrar a POST)
	$id_producto       = isset($_GET["id_producto"])       ? (int)$_GET["id_producto"]       : 0;
	$cantidad_agregar  = isset($_GET["cantidad_agregar"])  ? str_replace(',', '.', trim($_GET["cantidad_agregar"])) : '0';
	$nup_agregar       = isset($_GET["nup"])               ? trim($_GET["nup"])               : '';
	$lote_agregar      = isset($_GET["lote_agregar"])      ? trim($_GET["lote_agregar"])      : '';
	$bodega_agregar    = isset($_GET["bodega_agregar"])    ? (int)$_GET["bodega_agregar"]     : 0;
	$medida_agregar    = isset($_GET["medida_agregar"])    ? (int)$_GET["medida_agregar"]     : 0;
	$caducidad_agregar = isset($_GET["caducidad_agregar"]) ? trim($_GET["caducidad_agregar"]) : '';
	$inventario        = isset($_GET["inventario"])        ? trim($_GET["inventario"])        : ''; // por si lo usas luego
	$precio            = isset($_GET["precio"])            ? str_replace(',', '.', trim($_GET["precio"])) : '0';
	$id_detalle_pedido = isset($_GET["id"])                ? (int)$_GET["id"]                 : 0;

	// 2) Validación / normalización
	if ($id_producto <= 0) {
		echo "<script>$.notify('Producto inválido','error');</script>";
		detalle_nueva_consignacion_venta();
		exit;
	}
	if (!is_numeric($cantidad_agregar)) {
		$cantidad_agregar = '0';
	}
	if (!is_numeric($precio)) {
		$precio = '0';
	}

	$cantidad_agregar = (float)$cantidad_agregar;
	$precio           = (float)$precio;

	if ($cantidad_agregar <= 0) {
		echo "<script>$.notify('Cantidad debe ser mayor a 0','error');</script>";
		detalle_nueva_consignacion_venta();
		exit;
	}
	if ($precio < 0) {
		$precio = 0.0;
	}

	// Fecha caducidad a YYYY-mm-dd (acepta dd-mm-YYYY o YYYY-mm-dd)
	$caducidad_sql = null;
	if ($caducidad_agregar !== '') {
		if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $caducidad_agregar)) {
			list($d, $m, $Y) = explode('-', $caducidad_agregar);
			$caducidad_sql = $Y . '-' . $m . '-' . $d;
		} elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $caducidad_agregar)) {
			$caducidad_sql = $caducidad_agregar;
		}
	}

	// 3) Evitar duplicados: mismo producto + lote + NUP + usuario + empresa
	$dup = false;
	if ($stmt = mysqli_prepare($con, "SELECT 1 FROM factura_tmp WHERE id_producto = ? AND lote = ? AND tarifa_ice = ? AND id_usuario = ? AND ruc_empresa = ? LIMIT 1")) {
		mysqli_stmt_bind_param($stmt, 'issis', $id_producto, $lote_agregar, $nup_agregar, $id_usuario, $ruc_empresa);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_store_result($stmt);
		$dup = (mysqli_stmt_num_rows($stmt) > 0);
		mysqli_stmt_close($stmt);
	} else {
		echo "<script>$.notify('Error preparando verificación','error');</script>";
		detalle_nueva_consignacion_venta();
		exit;
	}

	if ($dup) {
		echo "<script>$.notify('Producto ya agregado con este lote y NUP','error');</script>";
		detalle_nueva_consignacion_venta();
		exit;
	}

	// 4) Calcular subtotal (2 decimales contables)
	$subtotal = (float) number_format($cantidad_agregar * $precio, 2, '.', '');

	// 5) Insert seguro (especifica columnas REALES de factura_tmp)
	// Asumo columnas: id_producto, cantidad_tmp, precio_tmp, descuento, tipo, tarifa_iva, nup, numero_consignacion, id_usuario, id_bodega, id_medida, lote, fecha_vencimiento, subtotal, ruc_empresa
	$sqlIns = "INSERT INTO factura_tmp
               (id_producto, cantidad_tmp, precio_tmp, descuento, tipo_produccion, tarifa_iva, tarifa_ice, tarifa_botellas, id_usuario, id_bodega, id_medida, lote, vencimiento, subtotal, ruc_empresa)
               VALUES (?, ?, ?, 0, '01', '0', ?, '0', ?, ?, ?, ?, ?, ?, ?)";

	if ($stmt = mysqli_prepare($con, $sqlIns)) {
		// fecha_vencimiento puede ser NULL, según esquema; si no admite NULL, pasa '' en su lugar
		$fecha_vto_bind = $caducidad_sql ? $caducidad_sql : 0;
		mysqli_stmt_bind_param(
			$stmt,
			'iddsiiissds',
			$id_producto,
			$cantidad_agregar,
			$precio,
			$nup_agregar,
			$id_usuario,
			$bodega_agregar,
			$medida_agregar,
			$lote_agregar,
			$fecha_vto_bind,
			$subtotal,
			$ruc_empresa
		);
		// Nota sobre tipos:
		// 'i' int, 'd' double, 's' string. Ajusta la máscara si id_usuario es int ('i') o si fecha no admite NULL (entonces pásala como 's' con '').

		if (!mysqli_stmt_execute($stmt)) {
			echo "<script>$.notify('No se pudo agregar el ítem','error');</script>";
			mysqli_stmt_close($stmt);
			detalle_nueva_consignacion_venta();
			exit;
		}
		$lastid = mysqli_insert_id($con);
		mysqli_stmt_close($stmt);
	} else {
		echo "<script>$.notify('Error preparando inserción','error');</script>";
		detalle_nueva_consignacion_venta();
		exit;
	}

	// 6) Relación opcional con detalle de pedido
	if ($id_detalle_pedido > 0 && function_exists('add_detalle_pedido_tmp')) {
		add_detalle_pedido_tmp($lastid, $id_detalle_pedido, $cantidad_agregar);
	}

	// 7) Feedback y refresco
	echo "<script>$.notify('Ítem agregado','success');</script>";
	detalle_nueva_consignacion_venta();
}


//para almacenar los id del pedido y luego guardar las cantidades usadas en la consignacion y conciliar en el pedido
/* function add_detalle_pedido_tmp($id_item, $id_detalle_pedido, $cantidad)
{
	$conciliacion_pedido = array();
	$arrayDatosItems = array();
	$arrayDatosItems = array('id' => $id_item, 'id_detalle' => $id_detalle_pedido, 'cantidad' => $cantidad);
	if (isset($_SESSION['conciliacion_pedido'])) {
		$conciliacion_pedido = $_SESSION['conciliacion_pedido'];
		array_push($conciliacion_pedido, $arrayDatosItems);
		$_SESSION['conciliacion_pedido'] = $conciliacion_pedido;
	} else {
		array_push($conciliacion_pedido, $arrayDatosItems);
		$_SESSION['conciliacion_pedido'] = $conciliacion_pedido;
	}
} */

function add_detalle_pedido_tmp($id_item, $id_detalle_pedido, $cantidad)
{
	// Normalización y validación
	$id_item           = (int)$id_item;
	$id_detalle_pedido = (int)$id_detalle_pedido;
	// permitir coma o punto en $cantidad
	$cantidad = str_replace(',', '.', (string)$cantidad);
	$cantidad = is_numeric($cantidad) ? (float)$cantidad : 0.0;

	if ($id_item <= 0 || $id_detalle_pedido <= 0 || $cantidad <= 0) {
		return false; // datos inválidos → no registramos
	}

	if (!isset($_SESSION['conciliacion_pedido']) || !is_array($_SESSION['conciliacion_pedido'])) {
		$_SESSION['conciliacion_pedido'] = array();
	}

	$list = $_SESSION['conciliacion_pedido'];
	$found = false;

	// Buscar si ya existe la combinación (id_item, id_detalle)
	for ($i = 0, $n = count($list); $i < $n; $i++) {
		$row = $list[$i];
		if ((int)$row['id'] === $id_item && (int)$row['id_detalle'] === $id_detalle_pedido) {
			// Si quieres reemplazar la cantidad en lugar de sumar, usa:
			// $list[$i]['cantidad'] = $cantidad;
			$list[$i]['cantidad'] = (isset($row['cantidad']) && is_numeric($row['cantidad']))
				? ((float)$row['cantidad'] + $cantidad)  // SUMA cantidades
				: $cantidad;
			$found = true;
			break;
		}
	}

	if (!$found) {
		$list[] = array(
			'id'         => $id_item,
			'id_detalle' => $id_detalle_pedido,
			'cantidad'   => $cantidad
		);
	}

	// Reindexa por si acaso
	$_SESSION['conciliacion_pedido'] = array_values($list);
	return true;
}



//para editar detalle a la consignacion de ventas
/* if ($action == 'editar_detalle_consignacion_venta') {
	$codigo_unico = mysqli_real_escape_string($con, (strip_tags($_GET["codigo_unico"], ENT_QUOTES)));
	$agregar_consignacion = mysqli_query($con, "INSERT INTO factura_tmp (id, id_producto, cantidad_tmp, precio_tmp, descuento, tipo_produccion, tarifa_iva, tarifa_ice, tarifa_botellas, id_usuario, id_bodega, id_medida, lote, vencimiento, subtotal, ruc_empresa)
	SELECT null, id_producto, cant_consignacion, precio, descuento,'1', (select tarifa_iva from productos_servicios where id=id_producto ) as tarifa_iva, nup,0,'" . $id_usuario . "', id_bodega, id_medida, lote, vencimiento, round(precio * cant_consignacion,2), ruc_empresa FROM detalle_consignacion WHERE codigo_unico='" . $codigo_unico . "'");
	detalle_nueva_consignacion_venta();
} */

if ($action == 'editar_detalle_consignacion_venta') {
	// 1) Entradas (ideal migrar a POST)
	$codigo_unico = isset($_GET['codigo_unico']) ? trim($_GET['codigo_unico']) : '';
	if ($codigo_unico === '') {
		echo "<script>$.notify('No existe el registro seleccionado','error');</script>";
		detalle_nueva_consignacion_venta();
		exit;
	}

	// 2) Transacción
	mysqli_begin_transaction($con);
	$copiados = false;

	try {
		$sql = "
            INSERT INTO factura_tmp
                (id, id_producto, cantidad_tmp, precio_tmp, descuento, tipo_produccion, tarifa_iva, tarifa_ice, tarifa_botellas, id_usuario, id_bodega, id_medida, lote, vencimiento, subtotal, ruc_empresa)
            SELECT
                NULL,
                det.id_producto,
                det.cant_consignacion,
                det.precio,
                det.descuento,
                '1',
                pro.tarifa_iva,
                det.nup,
                0,
                ?,                  -- id_usuario
                det.id_bodega,
                det.id_medida,
                det.lote,
                det.vencimiento,
                ROUND(det.precio * det.cant_consignacion, 2) AS subtotal,
                det.ruc_empresa
            FROM detalle_consignacion AS det
            INNER JOIN productos_servicios AS pro
                ON pro.id = det.id_producto
            WHERE det.codigo_unico = ?
              AND det.ruc_empresa  = ?
        ";

		if (!($stmt = mysqli_prepare($con, $sql))) {
			throw new Exception('Error preparando INSERT: ' . mysqli_error($con));
		}

		mysqli_stmt_bind_param($stmt, 'iss', $id_usuario, $codigo_unico, $ruc_empresa);

		if (!mysqli_stmt_execute($stmt)) {
			$err = mysqli_stmt_error($stmt);
			mysqli_stmt_close($stmt);
			throw new Exception('No se pudo copiar los ítems de la consignación. ' . $err);
		}
		$filas = mysqli_stmt_affected_rows($stmt);
		mysqli_stmt_close($stmt);

		if ($filas <= 0) {
			mysqli_rollback($con);
			// Renderiza primero y luego muestra el error
			ob_start();
			detalle_nueva_consignacion_venta();
			$html = ob_get_clean();
			echo $html .
				"<script>$.notify('No hay detalles para agregar','error');</script>";
			exit;
		}

		mysqli_commit($con);
		$copiados = true;
	} catch (Exception $e) {
		mysqli_rollback($con);
		// Renderiza primero y luego muestra el error
		ob_start();
		detalle_nueva_consignacion_venta();
		$html = ob_get_clean();
		$msg = addslashes($e->getMessage());
		echo $html . "<script>$.notify('{$msg}','error');</script>";
		exit;
	}

	// 3) Render primero, toast después (¡clave para tu UX!)
	ob_start();
	detalle_nueva_consignacion_venta();
	$html = ob_get_clean();

	/* if ($copiados) {
		echo $html . "<script>$.notify('Ítems cargados desde la consignación','success');</script>";
	} else {
		echo $html;
	} */

	echo $html;
}




//resetea los datos de la tabla temp de factura tmp
/* if ($action == 'limpiar_info_entrada') {
	unset($_SESSION['conciliacion_pedido']); //limpia la sesion que tiene los datos del detalle del pedido
	unset($_SESSION['arrayItemFacturar']);
	$limpiar_tabla = mysqli_query($con, "DELETE FROM factura_tmp WHERE id_usuario='" . $id_usuario . "' and ruc_empresa='" . $ruc_empresa . "'");
} */


if ($action == 'limpiar_info_entrada') {
	// 1) Limpia estructuras de sesión (si existen)
	if (isset($_SESSION['conciliacion_pedido'])) {
		unset($_SESSION['conciliacion_pedido']); // detalle del pedido
	}
	if (isset($_SESSION['arrayItemFacturar'])) {
		unset($_SESSION['arrayItemFacturar']);   // staging de items a facturar
	}

	// 2) Borra staging de BD de forma segura
	$sql = "DELETE FROM factura_tmp WHERE id_usuario = ? AND ruc_empresa = ?";
	if ($stmt = mysqli_prepare($con, $sql)) {
		// Ajusta el tipo de id_usuario a 'i' si es entero en tu esquema
		mysqli_stmt_bind_param($stmt, 'ss', $id_usuario, $ruc_empresa);
		if (!mysqli_stmt_execute($stmt)) {
			// Error al ejecutar
			echo "<script>$.notify('No se pudo limpiar la información de entrada','error');</script>";
			mysqli_stmt_close($stmt);
			exit;
		}
		$deleted = mysqli_stmt_affected_rows($stmt);
		mysqli_stmt_close($stmt);

		// Feedback (opcional)
		//echo "<script>$.notify('Información limpiada (" . (int)$deleted . " registros).','success');</script>";
	} else {
		echo "<script>$.notify('Error preparando limpieza','error');</script>";
		exit;
	}
}




//para eliminar la consignacion
/* if ($action == 'eliminar_consignacion_ventas') {
	$codigo_unico = $_GET['codigo_unico'];
	$consultar_encabezado = mysqli_query($con, "SELECT * FROM encabezado_consignacion WHERE codigo_unico='" . $codigo_unico . "'");
	$row_encabezado = mysqli_fetch_array($consultar_encabezado);
	$numero_consignacion = $row_encabezado['numero_consignacion'];

	$consultar_utilizada = mysqli_query($con, "SELECT * FROM detalle_consignacion WHERE numero_orden_entrada='" . $numero_consignacion . "' and ruc_empresa='" . $ruc_empresa . "'");
	$entradas = mysqli_num_rows($consultar_utilizada);
	if ($entradas == 0) {
		$actualiza_encabezado = mysqli_query($con, "UPDATE encabezado_consignacion SET observaciones='ANULADA', status='0' WHERE codigo_unico='" . $codigo_unico . "' ");
		$elimina_detalle_consignacion = mysqli_query($con, "DELETE FROM detalle_consignacion WHERE codigo_unico='" . $codigo_unico . "'");
		//$eliminar_registros_inventario = mysqli_query($con, "DELETE FROM inventarios WHERE id_documento_venta = '" . $codigo_unico . "'");
		echo "<script>
		$.notify('Consignación anulada','success');
		setTimeout(function (){location.href ='../modulos/consignacion_venta.php'}, 1000);
		</script>";
	} else {
		echo "<script>
		$.notify('No es posible eliminar, exiten registros de retornos y/o facturas.','error');
		setTimeout(function (){location.href ='../modulos/consignacion_venta.php'}, 1000);
		</script>";
	}
} */

if ($action == 'eliminar_consignacion_ventas') {
	// Idealmente este endpoint debería ser POST, pero respetamos tu flujo actual.
	$codigo_unico = isset($_GET['codigo_unico']) ? trim($_GET['codigo_unico']) : '';

	if ($codigo_unico === '') {
		echo "<script>$.notify('Código inválido','error');</script>";
		exit;
	}

	// 1) Traer encabezado de forma segura
	$sqlEnc = "SELECT numero_consignacion, status, observaciones
                 FROM encabezado_consignacion
                WHERE codigo_unico = ? AND ruc_empresa = ?
                LIMIT 1";
	if (!($stEnc = mysqli_prepare($con, $sqlEnc))) {
		echo "<script>$.notify('Error preparando consulta','error');</script>";
		exit;
	}
	mysqli_stmt_bind_param($stEnc, 'ss', $codigo_unico, $ruc_empresa);
	mysqli_stmt_execute($stEnc);
	$resEnc = mysqli_stmt_get_result($stEnc);
	$enc    = $resEnc ? mysqli_fetch_assoc($resEnc) : null;
	mysqli_stmt_close($stEnc);

	if (!$enc) {
		echo "<script>$.notify('Consignación no encontrada','error');
        setTimeout(function (){location.href ='../modulos/consignacion_venta.php'}, 1000);</script>";
		exit;
	}

	// Idempotencia: si ya está anulada, avisar y salir limpio
	if ((string)$enc['status'] === '0' || strtoupper((string)$enc['observaciones']) === 'ANULADA') {
		echo "<script>$.notify('La consignación ya estaba anulada','success');
        setTimeout(function (){location.href ='../modulos/consignacion_venta.php'}, 1000);</script>";
		exit;
	}

	$numero_consignacion = $enc['numero_consignacion'];

	// 2) Verificar si fue utilizada (retornos/facturas)
	// Nota: mantenemos tu criterio: entradas en detalle_consignacion donde numero_orden_entrada = numero_consignacion
	$sqlUsed = "SELECT 1
                  FROM detalle_consignacion
                 WHERE numero_orden_entrada = ?
                   AND ruc_empresa = ?
                 LIMIT 1";
	if (!($stUsed = mysqli_prepare($con, $sqlUsed))) {
		echo "<script>$.notify('Error preparando verificación','error');</script>";
		exit;
	}
	mysqli_stmt_bind_param($stUsed, 'ss', $numero_consignacion, $ruc_empresa);
	mysqli_stmt_execute($stUsed);
	mysqli_stmt_store_result($stUsed);
	$tiene_entradas = (mysqli_stmt_num_rows($stUsed) > 0);
	mysqli_stmt_close($stUsed);

	if ($tiene_entradas) {
		echo "<script>
        $.notify('No es posible eliminar: existen retornos y/o facturas vinculadas.','error');
        setTimeout(function (){location.href ='../modulos/consignacion_venta.php'}, 1000);
        </script>";
		exit;
	}

	// 3) Transacción: anular encabezado y borrar detalle
	mysqli_begin_transaction($con);
	try {
		// 3.1) Anular encabezado
		$sqlUp = "UPDATE encabezado_consignacion
                     SET observaciones = 'ANULADA',
                         status = '0'
                   WHERE codigo_unico = ?
                     AND ruc_empresa  = ?";
		if (!($stUp = mysqli_prepare($con, $sqlUp))) {
			throw new Exception('Error preparando anulación');
		}
		mysqli_stmt_bind_param($stUp, 'ss', $codigo_unico, $ruc_empresa);
		if (!mysqli_stmt_execute($stUp)) {
			throw new Exception('No se pudo anular la consignación');
		}
		mysqli_stmt_close($stUp);

		// 3.2) Eliminar detalle de la consignación
		$sqlDelDet = "DELETE FROM detalle_consignacion
                       WHERE codigo_unico = ?
                         AND ruc_empresa  = ?";
		if (!($stDel = mysqli_prepare($con, $sqlDelDet))) {
			throw new Exception('Error preparando borrado de detalle');
		}
		mysqli_stmt_bind_param($stDel, 'ss', $codigo_unico, $ruc_empresa);
		if (!mysqli_stmt_execute($stDel)) {
			throw new Exception('No se pudo eliminar el detalle de la consignación');
		}
		mysqli_stmt_close($stDel);

		// 3.3) (Opcional) Si necesitas eliminar movimientos de inventario relacionados, activa y ajusta:
		// $sqlDelInv = "DELETE FROM inventarios WHERE id_documento_venta = ? AND ruc_empresa = ?";
		// ...

		mysqli_commit($con);

		echo "<script>
        $.notify('Consignación anulada','success');
        setTimeout(function (){location.href ='../modulos/consignacion_venta.php'}, 1000);
        </script>";
	} catch (Exception $e) {
		mysqli_rollback($con);
		echo "<script>
        $.notify('" . $e->getMessage() . "','error');
        setTimeout(function (){location.href ='../modulos/consignacion_venta.php'}, 1000);
        </script>";
	}
}

//para eliminar devolucion de la consignacion ventas
/* if ($action == 'eliminar_devolucion_consignacion_ventas') {
	$codigo_unico = $_GET['codigo_unico'];
	$actualiza_encabezado = mysqli_query($con, "UPDATE encabezado_consignacion SET observaciones='ANULADA', status='0' WHERE codigo_unico='" . $codigo_unico . "'");
	$elimina_detalle_consignacion = mysqli_query($con, "DELETE FROM detalle_consignacion WHERE codigo_unico='" . $codigo_unico . "'");
	echo "<script>
		$.notify('Registro anulado','success');
		setTimeout(function (){location.href ='../modulos/devolucion_consignacion_venta.php'}, 1000);
		</script>";
}
 */

if ($action == 'eliminar_devolucion_consignacion_ventas') {
	// Ideal: usar POST, pero respetamos tu flujo actual
	$codigo_unico = isset($_GET['codigo_unico']) ? trim($_GET['codigo_unico']) : '';

	if ($codigo_unico === '') {
		echo "<script>$.notify('Código inválido','error');</script>";
		exit;
	}

	// 1) Consultar encabezado de forma segura (limitando por ruc)
	$sqlEnc = "SELECT status, observaciones
                 FROM encabezado_consignacion
                WHERE codigo_unico = ? AND ruc_empresa = ?
                LIMIT 1";
	if (!($stEnc = mysqli_prepare($con, $sqlEnc))) {
		echo "<script>$.notify('Error preparando consulta','error');</script>";
		exit;
	}
	mysqli_stmt_bind_param($stEnc, 'ss', $codigo_unico, $ruc_empresa);
	mysqli_stmt_execute($stEnc);
	$resEnc = mysqli_stmt_get_result($stEnc);
	$enc    = $resEnc ? mysqli_fetch_assoc($resEnc) : null;
	mysqli_stmt_close($stEnc);

	if (!$enc) {
		echo "<script>$.notify('Registro no encontrado','error');
        setTimeout(function (){location.href ='../modulos/devolucion_consignacion_venta.php'}, 1000);</script>";
		exit;
	}

	// Idempotencia: si ya está anulada, avisar y salir limpio
	if ((string)$enc['status'] === '0' || strtoupper((string)$enc['observaciones']) === 'ANULADA') {
		echo "<script>$.notify('El registro ya estaba anulado','success');
        setTimeout(function (){location.href ='../modulos/devolucion_consignacion_venta.php'}, 1000);</script>";
		exit;
	}

	// 2) Transacción: anular encabezado y borrar detalle
	mysqli_begin_transaction($con);
	try {
		// 2.1) Anular encabezado
		$sqlUp = "UPDATE encabezado_consignacion
                     SET observaciones = 'ANULADA',
                         status = '0'
                   WHERE codigo_unico = ? AND ruc_empresa = ?";
		if (!($stUp = mysqli_prepare($con, $sqlUp))) {
			throw new Exception('Error preparando anulación');
		}
		mysqli_stmt_bind_param($stUp, 'ss', $codigo_unico, $ruc_empresa);
		if (!mysqli_stmt_execute($stUp)) {
			throw new Exception('No se pudo anular el encabezado');
		}
		mysqli_stmt_close($stUp);

		// 2.2) Eliminar detalle
		$sqlDel = "DELETE FROM detalle_consignacion
                    WHERE codigo_unico = ? AND ruc_empresa = ?";
		if (!($stDel = mysqli_prepare($con, $sqlDel))) {
			throw new Exception('Error preparando borrado de detalle');
		}
		mysqli_stmt_bind_param($stDel, 'ss', $codigo_unico, $ruc_empresa);
		if (!mysqli_stmt_execute($stDel)) {
			throw new Exception('No se pudo eliminar el detalle');
		}
		mysqli_stmt_close($stDel);

		// 2.3) (Opcional) Eliminar movimientos de inventario relacionados
		// $sqlInv = "DELETE FROM inventarios WHERE id_documento_venta = ? AND ruc_empresa = ?";
		// ...

		mysqli_commit($con);

		echo "<script>
            $.notify('Registro anulado','success');
            setTimeout(function (){location.href ='../modulos/devolucion_consignacion_venta.php'}, 1000);
        </script>";
	} catch (Exception $e) {
		mysqli_rollback($con);
		echo "<script>
            $.notify('" . $e->getMessage() . "','error');
            setTimeout(function (){location.href ='../modulos/devolucion_consignacion_venta.php'}, 1000);
        </script>";
	}
}



//para eliminar factura de la consignacion ventas
/* if ($action == 'eliminar_factura_consignacion_venta') {
	$codigo_unico = $_GET['codigo_unico'];
	$sql_encabezado = mysqli_query($con, "SELECT * FROM encabezado_consignacion WHERE codigo_unico='" . $codigo_unico . "' ");
	$row_encabezado = mysqli_fetch_array($sql_encabezado);
	$tipo_consignacion = $row_encabezado['tipo_consignacion'];
	$operacion = $row_encabezado['operacion'];
	$serie = $row_encabezado['serie_sucursal'];
	$factura = $row_encabezado['factura_venta'];
	$empresa_ruc = $row_encabezado['ruc_empresa'];
	$factura_venta = $row_encabezado['factura_venta'];
	$observaciones = $row_encabezado['observaciones'];
	if ($factura_venta == "") {
		echo "<script>
		$.notify('$observaciones','error');
		</script>";
		exit;
	}

	if ($tipo_consignacion == "VENTA" && $operacion == "FACTURA") {
		$sql_factura = mysqli_query($con, "SELECT * FROM encabezado_factura WHERE serie_factura='" . $serie . "' and secuencial_factura='" . $factura . "' and ruc_empresa='" . $empresa_ruc . "'");
		$row_factura = mysqli_fetch_array($sql_factura);
		$estado_sri = isset($row_factura['estado_sri']) ? $row_factura['estado_sri'] : "";
		$id_registro_contable = isset($row_factura['id_registro_contable']) ? $row_factura['id_registro_contable'] : 0;
		if ($estado_sri == "AUTORIZADO") {
			echo "<script>
		$.notify('Primero debe anular la factura en el SRI y luego en el sistema.','error');
		</script>";
			exit;
		}
		if ($estado_sri == "PENDIENTE") {
			//para anular el registro contable
			if ($id_registro_contable > 0) {
				include_once("../clases/anular_registros.php");
				$anular_asiento_contable = new anular_registros();
				$resultado_anular_documento = $anular_asiento_contable->anular_asiento_contable($con, $id_registro_contable);
			}

			$eliminar_encabezado_factura = mysqli_query($con, "DELETE FROM encabezado_factura WHERE ruc_empresa = '" . $empresa_ruc . "' and serie_factura='" . $serie . "' and secuencial_factura='" . $factura . "'");
			$delete_detalle_factura = mysqli_query($con, "DELETE FROM cuerpo_factura WHERE ruc_empresa = '" . $empresa_ruc . "' and serie_factura='" . $serie . "' and secuencial_factura='" . $factura . "'");
			$delete_pago_factura = mysqli_query($con, "DELETE FROM formas_pago_ventas WHERE ruc_empresa = '" . $empresa_ruc . "' and serie_factura='" . $serie . "' and secuencial_factura='" . $factura . "'");
			$delete_adicional_factura = mysqli_query($con, "DELETE FROM detalle_adicional_factura WHERE ruc_empresa = '" . $empresa_ruc . "' and serie_factura='" . $serie . "' and secuencial_factura='" . $factura . "'");
			echo "<script>
			$.notify('Factura eliminada.','success')
			</script>";
		}
	}

	$actualiza_encabezado = mysqli_query($con, "UPDATE encabezado_consignacion SET observaciones='ANULADA', status='0' WHERE codigo_unico='" . $codigo_unico . "' ");
	$elimina_detalle_consignacion = mysqli_query($con, "DELETE FROM detalle_consignacion WHERE codigo_unico='" . $codigo_unico . "'");
	echo "<script>
		$.notify('Registro anulado','success');
		setTimeout(function (){location.href ='../modulos/facturacion_consignacion_venta.php'}, 1000);
		</script>";
} */

if ($action == 'eliminar_factura_consignacion_venta') {
	// Ideal: usar POST, pero respetamos tu flujo actual
	$codigo_unico = isset($_GET['codigo_unico']) ? trim($_GET['codigo_unico']) : '';
	if ($codigo_unico === '') {
		echo "<script>$.notify('Código inválido','error');</script>";
		exit;
	}

	// 1) Traer encabezado de forma segura (y de la misma empresa)
	$sqlEnc = "SELECT tipo_consignacion, operacion, serie_sucursal, factura_venta, ruc_empresa, observaciones, status
                 FROM encabezado_consignacion
                WHERE codigo_unico = ? AND ruc_empresa = ?
                LIMIT 1";
	if (!($stEnc = mysqli_prepare($con, $sqlEnc))) {
		echo "<script>$.notify('Error preparando consulta','error');</script>";
		exit;
	}
	mysqli_stmt_bind_param($stEnc, 'ss', $codigo_unico, $ruc_empresa);
	mysqli_stmt_execute($stEnc);
	$resEnc = mysqli_stmt_get_result($stEnc);
	$enc    = $resEnc ? mysqli_fetch_assoc($resEnc) : null;
	mysqli_stmt_close($stEnc);

	if (!$enc) {
		echo "<script>$.notify('Consignación no encontrada','error');
        setTimeout(function (){location.href ='../modulos/facturacion_consignacion_venta.php'}, 1000);</script>";
		exit;
	}

	// Idempotencia: si ya está anulada, informamos y salimos
	if ((string)$enc['status'] === '0' || strtoupper((string)$enc['observaciones']) === 'ANULADA') {
		echo "<script>$.notify('El registro ya estaba anulado','success');
        setTimeout(function (){location.href ='../modulos/facturacion_consignacion_venta.php'}, 1000);</script>";
		exit;
	}

	$tipo_consignacion = $enc['tipo_consignacion'];
	$operacion         = $enc['operacion'];
	$serie             = $enc['serie_sucursal'];
	$factura           = $enc['factura_venta'];
	$empresa_ruc       = $enc['ruc_empresa'];
	$observaciones     = $enc['observaciones'];

	// Si no tiene número de factura, mostramos observaciones y salimos
	if ($factura === '' || $factura === null) {
		$obs = $observaciones !== '' ? $observaciones : 'No hay factura vinculada.';
		echo "<script>$.notify('" . htmlspecialchars($obs, ENT_QUOTES) . "','error');</script>";
		exit;
	}

	// 2) Si es consignación de VENTA en operación FACTURA, validar estado SRI y eliminar factura si procede
	$debe_borrar_factura = false;
	$id_registro_contable = 0;
	if ($tipo_consignacion === "VENTA" && $operacion === "FACTURA") {
		$sqlFac = "SELECT estado_sri, id_registro_contable
                     FROM encabezado_factura
                    WHERE ruc_empresa = ? AND serie_factura = ? AND secuencial_factura = ?
                    LIMIT 1";
		if (!($stFac = mysqli_prepare($con, $sqlFac))) {
			echo "<script>$.notify('Error preparando verificación de factura','error');</script>";
			exit;
		}
		mysqli_stmt_bind_param($stFac, 'sss', $empresa_ruc, $serie, $factura);
		mysqli_stmt_execute($stFac);
		$resFac = mysqli_stmt_get_result($stFac);
		$rowFac = $resFac ? mysqli_fetch_assoc($resFac) : null;
		mysqli_stmt_close($stFac);

		$estado_sri = $rowFac ? (string)$rowFac['estado_sri'] : '';
		$id_registro_contable = $rowFac && isset($rowFac['id_registro_contable']) ? (int)$rowFac['id_registro_contable'] : 0;

		if ($rowFac) {
			if ($estado_sri === "AUTORIZADO") {
				echo "<script>$.notify('Primero debe anular la factura en el SRI y luego en el sistema.','error');</script>";
				exit;
			}
			if ($estado_sri === "PENDIENTE" || $estado_sri === "" || $estado_sri === "RECHAZADO") {
				$debe_borrar_factura = true;
			}
		}
	}

	// 3) Transacción: opcionalmente elimina factura (cuerpo/pagos/adicional/encabezado),
	//    y SIEMPRE anula la consignación y elimina su detalle
	mysqli_begin_transaction($con);
	try {
		// 3.1) Si hay asiento contable y corresponde eliminar factura: anula asiento
		if ($debe_borrar_factura && $id_registro_contable > 0) {
			if (file_exists("../clases/anular_registros.php")) {
				include_once("../clases/anular_registros.php");
				if (class_exists('anular_registros')) {
					$anular_asiento_contable = new anular_registros();
					// Si tu método hace su propia transacción, asegúrate de que no choque; de lo contrario, déjalo así.
					$resultado = $anular_asiento_contable->anular_asiento_contable($con, $id_registro_contable);
					// Puedes validar $resultado si tu clase lo devuelve.
				}
			}
		}

		// 3.2) Borrar factura (si aplica). Orden: detalles/pagos/adicional → encabezado
		if ($debe_borrar_factura) {
			// cuerpo_factura
			if ($st = mysqli_prepare($con, "DELETE FROM cuerpo_factura WHERE ruc_empresa = ? AND serie_factura = ? AND secuencial_factura = ?")) {
				mysqli_stmt_bind_param($st, 'sss', $empresa_ruc, $serie, $factura);
				if (!mysqli_stmt_execute($st)) {
					throw new Exception('No se pudo eliminar el detalle de la factura');
				}
				mysqli_stmt_close($st);
			} else {
				throw new Exception('Error preparando borrado de detalle de factura');
			}

			// formas_pago_ventas
			if ($st = mysqli_prepare($con, "DELETE FROM formas_pago_ventas WHERE ruc_empresa = ? AND serie_factura = ? AND secuencial_factura = ?")) {
				mysqli_stmt_bind_param($st, 'sss', $empresa_ruc, $serie, $factura);
				if (!mysqli_stmt_execute($st)) {
					throw new Exception('No se pudo eliminar los pagos de la factura');
				}
				mysqli_stmt_close($st);
			} else {
				throw new Exception('Error preparando borrado de pagos');
			}

			// detalle_adicional_factura
			if ($st = mysqli_prepare($con, "DELETE FROM detalle_adicional_factura WHERE ruc_empresa = ? AND serie_factura = ? AND secuencial_factura = ?")) {
				mysqli_stmt_bind_param($st, 'sss', $empresa_ruc, $serie, $factura);
				if (!mysqli_stmt_execute($st)) {
					throw new Exception('No se pudo eliminar los adicionales de la factura');
				}
				mysqli_stmt_close($st);
			} else {
				throw new Exception('Error preparando borrado de adicionales');
			}

			// encabezado_factura
			if ($st = mysqli_prepare($con, "DELETE FROM encabezado_factura WHERE ruc_empresa = ? AND serie_factura = ? AND secuencial_factura = ?")) {
				mysqli_stmt_bind_param($st, 'sss', $empresa_ruc, $serie, $factura);
				if (!mysqli_stmt_execute($st)) {
					throw new Exception('No se pudo eliminar el encabezado de la factura');
				}
				mysqli_stmt_close($st);
			} else {
				throw new Exception('Error preparando borrado del encabezado de factura');
			}

			echo "<script>$.notify('Factura eliminada.','success');</script>";
		}

		// 3.3) Anular consignación (encabezado) y borrar su detalle
		if ($st = mysqli_prepare($con, "UPDATE encabezado_consignacion SET observaciones='ANULADA', status='0' WHERE codigo_unico = ? AND ruc_empresa = ?")) {
			mysqli_stmt_bind_param($st, 'ss', $codigo_unico, $ruc_empresa);
			if (!mysqli_stmt_execute($st)) {
				throw new Exception('No se pudo anular el encabezado de la consignación');
			}
			mysqli_stmt_close($st);
		} else {
			throw new Exception('Error preparando anulación de consignación');
		}

		if ($st = mysqli_prepare($con, "DELETE FROM detalle_consignacion WHERE codigo_unico = ? AND ruc_empresa = ?")) {
			mysqli_stmt_bind_param($st, 'ss', $codigo_unico, $ruc_empresa);
			if (!mysqli_stmt_execute($st)) {
				throw new Exception('No se pudo eliminar el detalle de la consignación');
			}
			mysqli_stmt_close($st);
		} else {
			throw new Exception('Error preparando borrado del detalle de consignación');
		}

		// 3.4) (Opcional) Eliminar/ajustar inventarios relacionados si corresponde tu modelo
		// if ($st = mysqli_prepare($con, "DELETE FROM inventarios WHERE id_documento_venta = ? AND ruc_empresa = ?")) { ... }

		mysqli_commit($con);

		echo "<script>
            $.notify('Registro anulado','success');
            setTimeout(function (){location.href ='../modulos/facturacion_consignacion_venta.php'}, 1000);
        </script>";
	} catch (Exception $e) {
		mysqli_rollback($con);
		echo "<script>
            $.notify('" . htmlspecialchars($e->getMessage(), ENT_QUOTES) . "','error');
            setTimeout(function (){location.href ='../modulos/facturacion_consignacion_venta.php'}, 1000);
        </script>";
	}
}



//eliminar detalle de la consignacion nueva que se esta generando
/* if ($action == 'eliminar_item') {
	$id_registro = $_GET['id_registro'];
	$elimina_detalle_factura_tmp = mysqli_query($con, "DELETE FROM factura_tmp WHERE id='" . $id_registro . "'");
	if (isset($_SESSION['conciliacion_pedido'])) {
		eliminar_detalle_pedido_tmp($id_registro);
	}
	detalle_nueva_consignacion_venta();
} */

if ($action == 'eliminar_item') {
	// 1) Entrada y validación
	$id_registro = isset($_GET['id_registro']) ? (int)$_GET['id_registro'] : 0;

	if ($id_registro <= 0) {
		echo "<script>$.notify('ID inválido','error');</script>";
		detalle_nueva_consignacion_venta();
		exit;
	}

	// 2) Delete seguro con prepared statement
	$sql = "DELETE FROM factura_tmp WHERE id = ? AND id_usuario = ? AND ruc_empresa = ?";
	if ($stmt = mysqli_prepare($con, $sql)) {
		// Ajusta el tipo de id_usuario según tu esquema (int o string)
		mysqli_stmt_bind_param($stmt, 'iss', $id_registro, $id_usuario, $ruc_empresa);
		if (!mysqli_stmt_execute($stmt)) {
			echo "<script>$.notify('No se pudo eliminar el ítem','error');</script>";
			mysqli_stmt_close($stmt);
			detalle_nueva_consignacion_venta();
			exit;
		}
		mysqli_stmt_close($stmt);
	} else {
		echo "<script>$.notify('Error preparando eliminación','error');</script>";
		detalle_nueva_consignacion_venta();
		exit;
	}

	// 3) Limpieza en conciliación si corresponde
	if (isset($_SESSION['conciliacion_pedido']) && function_exists('eliminar_detalle_pedido_tmp')) {
		eliminar_detalle_pedido_tmp($id_registro);
	}

	// 4) Refresca el detalle
	detalle_nueva_consignacion_venta();
}


//para eliminar los items del detalle del pedido que se almacenan en una sesion y luego se concilian en la tabla pedidos
/* function eliminar_detalle_pedido_tmp($id)
{
	$intid = $id;
	$arrData = $_SESSION['conciliacion_pedido'];
	for ($i = 0; $i < count($arrData); $i++) {
		if ($arrData[$i]['id'] == $intid) {
			unset($arrData[$i]);
		}
	}
	sort($arrData); //para reordenar el array
	$_SESSION['conciliacion_pedido'] = $arrData;
} */

function eliminar_detalle_pedido_tmp($id)
{
	// Normalizar y validar
	$intid = (int)$id;
	if ($intid <= 0) {
		return false; // id inválido → no hacemos nada
	}

	if (!isset($_SESSION['conciliacion_pedido']) || !is_array($_SESSION['conciliacion_pedido'])) {
		return false; // no existe la sesión
	}

	$arrData = $_SESSION['conciliacion_pedido'];
	$found   = false;

	foreach ($arrData as $idx => $item) {
		if (isset($item['id']) && (int)$item['id'] === $intid) {
			unset($arrData[$idx]);
			$found = true;
		}
	}

	if ($found) {
		// Reindexar el array sin alterar el orden de los elementos
		$_SESSION['conciliacion_pedido'] = array_values($arrData);
		return true;
	}

	return false; // no se encontró el id
}


/* if ($action == 'eliminar_item_factura_consignacion') {
	$id_registro = $_GET['id_registro'];
	$elimina_detalle_factura_tmp = mysqli_query($con, "DELETE FROM factura_tmp WHERE id='" . $id_registro . "'");
	detalle_nueva_factura_consignacion_ventas();
} */

if ($action == 'eliminar_item_factura_consignacion') {
	// 1) Entrada y validación
	$id_registro = isset($_GET['id_registro']) ? (int)$_GET['id_registro'] : 0;
	if ($id_registro <= 0) {
		echo "<script>$.notify('ID inválido','error');</script>";
		detalle_nueva_factura_consignacion_ventas();
		exit;
	}

	// 2) Eliminación segura con filtros de seguridad
	$sql = "DELETE FROM factura_tmp 
             WHERE id = ? 
               AND id_usuario = ? 
               AND ruc_empresa = ?";

	if ($stmt = mysqli_prepare($con, $sql)) {
		// Ajusta el tipo de id_usuario según tu esquema: 'i' si es INT, 's' si es VARCHAR
		mysqli_stmt_bind_param($stmt, 'iss', $id_registro, $id_usuario, $ruc_empresa);
		if (!mysqli_stmt_execute($stmt)) {
			echo "<script>$.notify('No se pudo eliminar el ítem','error');</script>";
			mysqli_stmt_close($stmt);
			detalle_nueva_factura_consignacion_ventas();
			exit;
		}
		$affected = mysqli_stmt_affected_rows($stmt);
		mysqli_stmt_close($stmt);

		if ($affected === 0) {
			// No coincidió (otro usuario/empresa o ya no existe)
			echo "<script>$.notify('Ítem no encontrado o sin permisos para eliminar','warn');</script>";
		} else {
			// Opcional: feedback de éxito
			// echo "<script>$.notify('Ítem eliminado','success');</script>";
		}
	} else {
		echo "<script>$.notify('Error preparando eliminación','error');</script>";
	}

	// 3) Refrescar detalle como en tu flujo original
	detalle_nueva_factura_consignacion_ventas();
}


/* if ($action == 'detalle_consignacion') {
	$codigo_unico = $_GET['codigo_unico'];
	detalle_consignacion($codigo_unico);
}

if ($action == 'detalle_factura') {
	$codigo_unico = $_GET['codigo_unico'];
	detalle_factura($codigo_unico);
}

if ($action == 'mostrar_detalle_devolucion_consignacion') {
	$codigo_unico = $_GET['codigo_unico'];
	detalle_devolucion_consignacion($codigo_unico);
}

if ($action == 'muestra_detalle_consignacion_para_devolucion') {
	$numero_cv = $_GET['numero_cv'];
	detalle_consignacion_para_devolucion($numero_cv);
}

if ($action == 'muestra_detalle_consignacion_para_facturacion') {
	$numero_cv = $_GET['numero_consignacion'];
	$serie_cv = $_GET['serie_factura'];
	detalle_consignacion_para_facturacion($numero_cv, $serie_cv);
} */

// Helpers seguros para PHP 5.6
function _get_str($key, $maxLen = 64, $pattern = '/^[A-Za-z0-9_\-\.]+$/')
{
	if (!isset($_GET[$key])) return null;
	$val = trim($_GET[$key]);
	if ($val === '') return null;
	if (strlen($val) > $maxLen) $val = substr($val, 0, $maxLen);
	// Permite letras, números, _, -, . (ajusta el patrón si necesitas otros)
	if (!preg_match($pattern, $val)) return null;
	return $val;
}
function _get_num($key)
{
	if (!isset($_GET[$key])) return null;
	$val = str_replace(',', '.', trim($_GET[$key]));
	if ($val === '' || !is_numeric($val)) return null;
	return $val + 0; // float/int según sea
}

// (Opcional) una funcióncita para responder error sin romper el flujo
function _bad_param($msg = 'Parámetro inválido.')
{
	echo "<div class='alert alert-warning' style='margin:5px 0'>{$msg}</div>";
}

switch ($action) {
	case 'detalle_consignacion': {
			$codigo_unico = _get_str('codigo_unico', 64);
			if ($codigo_unico === null) {
				_bad_param('Código único inválido.');
				break;
			}
			if (function_exists('detalle_consignacion')) {
				detalle_consignacion($codigo_unico);
			} else {
				_bad_param('Función detalle_consignacion no disponible.');
			}
			break;
		}

	case 'detalle_factura': {
			$codigo_unico = _get_str('codigo_unico', 64);
			if ($codigo_unico === null) {
				_bad_param('Código único inválido.');
				break;
			}
			if (function_exists('detalle_factura')) {
				detalle_factura($codigo_unico);
			} else {
				_bad_param('Función detalle_factura no disponible.');
			}
			break;
		}

	case 'mostrar_detalle_devolucion_consignacion': {
			$codigo_unico = _get_str('codigo_unico', 64);
			if ($codigo_unico === null) {
				_bad_param('Código único inválido.');
				break;
			}
			if (function_exists('detalle_devolucion_consignacion')) {
				detalle_devolucion_consignacion($codigo_unico);
			} else {
				_bad_param('Función detalle_devolucion_consignacion no disponible.');
			}
			break;
		}

	case 'muestra_detalle_consignacion_para_devolucion': {
			// si tu número de consignación es numérico, usa _get_num; si es alfanumérico, cambia por _get_str
			$numero_cv = _get_str('numero_cv', 32); // o _get_num('numero_cv');
			if ($numero_cv === null) {
				_bad_param('Número de consignación inválido.');
				break;
			}
			if (function_exists('detalle_consignacion_para_devolucion')) {
				detalle_consignacion_para_devolucion($numero_cv);
			} else {
				_bad_param('Función detalle_consignacion_para_devolucion no disponible.');
			}
			break;
		}

	case 'muestra_detalle_consignacion_para_facturacion': {
			$numero_cv = _get_str('numero_consignacion', 32); // o _get_num('numero_consignacion');
			$serie_cv  = _get_str('serie_factura', 10, '/^[A-Za-z0-9_\-]+$/');
			if ($numero_cv === null || $serie_cv === null) {
				_bad_param('Datos de facturación inválidos.');
				break;
			}
			if (function_exists('detalle_consignacion_para_facturacion')) {
				detalle_consignacion_para_facturacion($numero_cv, $serie_cv);
			} else {
				_bad_param('Función detalle_consignacion_para_facturacion no disponible.');
			}
			break;
		}

		// (Opcional) default para acciones no reconocidas
	default:
		// _bad_param('Acción no reconocida.');
		break;
}



//muestra el detalle de la consignacion que se ingresa y queremos facturar
/* function detalle_consignacion_para_facturacion($numero_cv, $serie_cv)
{
	$con = conenta_login();
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$id_usuario = $_SESSION['id_usuario'];
	$busca_codigo_unico = mysqli_query($con, "SELECT * FROM encabezado_consignacion as enc_con INNER JOIN clientes as cli ON enc_con.id_cli_pro=cli.id WHERE enc_con.numero_consignacion = '" . $numero_cv . "' and enc_con.ruc_empresa='" . $ruc_empresa . "' ");
	$encabezado_consignacion = mysqli_fetch_array($busca_codigo_unico);
	$codigo_unico = isset($encabezado_consignacion['codigo_unico']) ? $encabezado_consignacion['codigo_unico'] : 0;
	$busca_consignacion = mysqli_query($con, "SELECT * FROM detalle_consignacion WHERE codigo_unico = '" . $codigo_unico . "' ");
?>
	<div style="padding: 1px; margin-bottom: 2px; margin-top: -10px;" class="alert alert-info" role="alert">
		<b>No. CV:</b> <?php echo $numero_cv; ?> <b>Consignado a: </b><?php echo isset($encabezado_consignacion['nombre']) ? $encabezado_consignacion['nombre'] : ""; ?>
	</div>
	<div class="panel panel-info" style="height: 280px; overflow-y: auto; margin-bottom: 5px;">
		<div class="table-responsive">
			<table class="table table-hover">
				<tr class="info">
					<th style="padding: 2px;">N</th>
					<th style="padding: 2px;">Código</th>
					<th style="padding: 2px;">Producto</th>
					<th style="padding: 2px;">Saldo</th>
					<th style="padding: 2px;">Lote</th>
					<th style="padding: 2px;">NUP</th>
					<th style="padding: 2px;">Precios</th>
					<th style="padding: 2px;">Precio</th>
					<th style="padding: 2px;">Cantidad</th>
					<th style="padding: 2px;">Descuento</th>
					<th style="padding: 2px;">Subtotal</th>
				</tr>
				<?php
				$contador = 0;
				while ($detalle = mysqli_fetch_array($busca_consignacion)) {
					$id_det_consignacion = $detalle['id_det_consignacion'];
					$codigo_producto = $detalle['codigo_producto'];
					$id_producto = $detalle['id_producto'];
					$nombre_producto = $detalle['nombre_producto'];
					$nup = $detalle['nup'];
					$lote = $detalle['lote'];
					$precio = $detalle['precio'];

					//busca saldo temp
					$busca_saldo_tmp = mysqli_query($con, "SELECT sum(cantidad_tmp) as suma FROM factura_tmp WHERE id_usuario = '" . $id_usuario . "' and ruc_empresa = '" . $ruc_empresa . "' and id_producto = '" . $id_producto . "' and tarifa_ice='" . $nup . "' and tarifa_botellas='" . $numero_cv . "'");
					$saldo_producto_tmp = mysqli_fetch_array($busca_saldo_tmp);
					$cantidad_tmp = $saldo_producto_tmp['suma'];
					//buscar entradas
					$busca_entradas = mysqli_query($con, "SELECT sum(det.cant_consignacion) as entradas FROM encabezado_consignacion as enc INNER JOIN detalle_consignacion as det ON enc.codigo_unico=det.codigo_unico WHERE det.id_producto = '" . $id_producto . "' and enc.numero_consignacion='" . $numero_cv . "' and det.nup='" . $nup . "' and enc.ruc_empresa='" . $ruc_empresa . "' and enc.tipo_consignacion='VENTA' and enc.operacion = 'ENTRADA' and det.lote='" . $lote . "'");
					$row_entradas = mysqli_fetch_array($busca_entradas);
					$entradas = $row_entradas['entradas'];
					//buscar salidas
					$busca_salidas = mysqli_query($con, "SELECT sum(det.cant_consignacion) as salidas FROM encabezado_consignacion as enc INNER JOIN detalle_consignacion as det ON enc.codigo_unico=det.codigo_unico WHERE det.id_producto = '" . $id_producto . "' and det.numero_orden_entrada='" . $numero_cv . "' and det.nup='" . $nup . "' and enc.ruc_empresa='" . $ruc_empresa . "' and enc.tipo_consignacion='VENTA' and enc.operacion != 'ENTRADA' and det.lote='" . $lote . "'");
					$row_salidas = mysqli_fetch_array($busca_salidas);
					$saldo = number_format($entradas - $row_salidas['salidas'] - $cantidad_tmp, 4, '.', '');
					$contador = $contador + 1;
					$fecha_actual = date("Y-m-d H:i:s");
				?>
					<tr>
						<input type="hidden" name="serie_cv" value="<?php echo $serie_cv; ?>">
						<input type="hidden" name="numero_cv" value="<?php echo $numero_cv; ?>">
						<input type="hidden" id="saldo<?php echo $id_det_consignacion; ?>" value="<?php echo $saldo; ?>">
						<td style="padding: 2px;"><?php echo $contador; ?></td>
						<td style="padding: 2px;"><?php echo $codigo_producto; ?></td>
						<td style="padding: 2px;"><?php echo $nombre_producto; ?></td>
						<td style="padding: 2px;"><?php echo $saldo; ?></td>
						<td style="padding: 2px;"><?php echo $lote; ?></td>
						<td style="padding: 2px;"><?php echo $nup; ?></td>
						<td style="padding: 2px;">
							<select class="form-control input-sm" id="lista_precios<?php echo $id_det_consignacion; ?>" onChange="precios(<?php echo $id_det_consignacion; ?>)">
								<?php
								$sql_precios = mysqli_query($con, "SELECT * FROM precios_productos WHERE id_producto='" . $id_producto . "' and DATE_FORMAT('" . $fecha_actual . "', '%Y/%m/%d') between DATE_FORMAT(fecha_desde, '%Y/%m/%d') and DATE_FORMAT(fecha_hasta, '%Y/%m/%d') order by detalle_precio asc");
								$busca_precio_normal = mysqli_query($con, "SELECT * FROM productos_servicios WHERE id='" . $id_producto . "' ");
								$row_precio_normal = mysqli_fetch_array($busca_precio_normal);
								?>
								<option value="0" selected>Precios</option>
								<option value="<?php echo $row_precio_normal['precio_producto']; ?>">Normal <?php echo number_format($row_precio_normal['precio_producto'], 2, '.', ''); ?></option>
								<?php
								while ($row_precios = mysqli_fetch_array($sql_precios)) {
								?>
									<option value="<?php echo $row_precios['precio']; ?>"><?php echo $row_precios['detalle_precio'] . " " . number_format($row_precios['precio'], 2, '.', ''); ?></option>
								<?php
								}
								?>
							</select>
						</td>
						<td style="padding: 2px;" class="col-sm-1"><input type="text" class="form-control input-sm text-right" name="precio[<?php echo $id_det_consignacion; ?>]" id="precio<?php echo $id_det_consignacion; ?>" onchange="precio_facturacion('<?php echo $id_det_consignacion; ?>');" value="<?php echo $precio; ?>"></td>
						<td style="padding: 2px;" class="col-sm-1"><input type="text" class="form-control input-sm text-right" name="cantidad[<?php echo $id_det_consignacion; ?>]" id="cantidad<?php echo $id_det_consignacion; ?>" onchange="cantidad_facturacion('<?php echo $id_det_consignacion; ?>');"></td>
						<td style="padding: 2px;" class="col-sm-1"><input type="text" class="form-control input-sm text-right" name="descuento[<?php echo $id_det_consignacion; ?>]" id="descuento<?php echo $id_det_consignacion; ?>" onchange="descuento_facturacion('<?php echo $id_det_consignacion; ?>');"></td>
						<td style="padding: 2px;" class="col-sm-1"><input type="text" class="form-control input-sm text-right" name="subtotal[<?php echo $id_det_consignacion; ?>]" id="subtotal<?php echo $id_det_consignacion; ?>" readonly></td>
					</tr>
				<?php
				}
				?>
			</table>
		</div>
	</div>
	<?php
} */

function detalle_consignacion_para_facturacion($numero_cv, $serie_cv)
{
	$con         = conenta_login();
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$id_usuario  = $_SESSION['id_usuario'];

	// ---------- 1) Encabezado seguro ----------
	$sqlEnc = "SELECT enc.codigo_unico, cli.nombre
                 FROM encabezado_consignacion enc
                 JOIN clientes cli ON cli.id = enc.id_cli_pro
                WHERE enc.numero_consignacion = ? AND enc.ruc_empresa = ?
                LIMIT 1";
	if (!($stEnc = mysqli_prepare($con, $sqlEnc))) {
		echo "<div class='alert alert-danger'>Error cargando consignación.</div>";
		return;
	}
	mysqli_stmt_bind_param($stEnc, 'ss', $numero_cv, $ruc_empresa);
	mysqli_stmt_execute($stEnc);
	$resEnc = mysqli_stmt_get_result($stEnc);
	$head   = $resEnc ? mysqli_fetch_assoc($resEnc) : null;
	mysqli_stmt_close($stEnc);

	if (!$head) {
		echo "<div class='alert alert-warning'>No se encontró la consignación {$numero_cv}.</div>";
		return;
	}

	$codigo_unico = $head['codigo_unico'];
	$cliente_nom  = $head['nombre'];

	// ---------- 2) Detalle base (una sola consulta) ----------
	$sqlDet = "SELECT det.id_det_consignacion, det.codigo_producto, det.id_producto, det.nombre_producto,
                      det.nup, det.lote, det.precio
                 FROM detalle_consignacion det
                WHERE det.codigo_unico = ? AND det.ruc_empresa = ?";
	if (!($stDet = mysqli_prepare($con, $sqlDet))) {
		echo "<div class='alert alert-danger'>Error cargando el detalle.</div>";
		return;
	}
	mysqli_stmt_bind_param($stDet, 'ss', $codigo_unico, $ruc_empresa);
	mysqli_stmt_execute($stDet);
	$resDet = mysqli_stmt_get_result($stDet);
	$detRows = [];
	while ($r = mysqli_fetch_assoc($resDet)) {
		$detRows[] = $r;
	}
	mysqli_stmt_close($stDet);
	if (empty($detRows)) {
		echo "<div class='alert alert-info'>La consignación no tiene ítems.</div>";
		return;
	}

	// ---------- 3) Pre-aggregados para SALDO (menos N+1) ----------
	// 3.1 Entradas (consignado originalmente): por producto+lote+nup en la CV actual
	$entradas = []; // key: id_prod|lote|nup => sum
	$sqlEnt = "SELECT det.id_producto, det.lote, det.nup, SUM(det.cant_consignacion) AS entradas
                 FROM encabezado_consignacion enc
                 JOIN detalle_consignacion det ON det.codigo_unico = enc.codigo_unico AND det.ruc_empresa = enc.ruc_empresa
                WHERE enc.ruc_empresa = ? AND enc.tipo_consignacion = 'VENTA' AND enc.operacion = 'ENTRADA'
                  AND enc.numero_consignacion = ?
                GROUP BY det.id_producto, det.lote, det.nup";
	if ($stEnt = mysqli_prepare($con, $sqlEnt)) {
		mysqli_stmt_bind_param($stEnt, 'ss', $ruc_empresa, $numero_cv);
		mysqli_stmt_execute($stEnt);
		$resEnt = mysqli_stmt_get_result($stEnt);
		while ($e = mysqli_fetch_assoc($resEnt)) {
			$k = $e['id_producto'] . '|' . $e['lote'] . '|' . $e['nup'];
			$entradas[$k] = (float)$e['entradas'];
		}
		mysqli_stmt_close($stEnt);
	}

	// 3.2 Salidas (devoluciones/facturas consumidas de esa CV): por producto+lote+nup
	$salidas = []; // key: id_prod|lote|nup => sum
	$sqlSal = "SELECT det.id_producto, det.lote, det.nup, SUM(det.cant_consignacion) AS salidas
                 FROM encabezado_consignacion enc
                 JOIN detalle_consignacion det ON det.codigo_unico = enc.codigo_unico AND det.ruc_empresa = enc.ruc_empresa
                WHERE enc.ruc_empresa = ? AND enc.tipo_consignacion = 'VENTA' AND enc.operacion <> 'ENTRADA'
                  AND det.numero_orden_entrada = ?
                GROUP BY det.id_producto, det.lote, det.nup";
	if ($stSal = mysqli_prepare($con, $sqlSal)) {
		mysqli_stmt_bind_param($stSal, 'ss', $ruc_empresa, $numero_cv);
		mysqli_stmt_execute($stSal);
		$resSal = mysqli_stmt_get_result($stSal);
		while ($s = mysqli_fetch_assoc($resSal)) {
			$k = $s['id_producto'] . '|' . $s['lote'] . '|' . $s['nup'];
			$salidas[$k] = (float)$s['salidas'];
		}
		mysqli_stmt_close($stSal);
	}

	// 3.3 Tomado ya en factura_tmp por el usuario (staging actual): por producto+nup y CV (usa tus mismas columnas)
	$tomado = []; // key: id_prod|nup => sum
	$sqlTmp = "SELECT id_producto, tarifa_ice AS nup, SUM(cantidad_tmp) AS suma
                 FROM factura_tmp
                WHERE id_usuario = ? AND ruc_empresa = ? AND tarifa_botellas = ?
                GROUP BY id_producto, tarifa_ice";
	if ($stTmp = mysqli_prepare($con, $sqlTmp)) {
		mysqli_stmt_bind_param($stTmp, 'sss', $id_usuario, $ruc_empresa, $numero_cv);
		mysqli_stmt_execute($stTmp);
		$resTmp = mysqli_stmt_get_result($stTmp);
		while ($t = mysqli_fetch_assoc($resTmp)) {
			$k = $t['id_producto'] . '|' . $t['nup'];
			$tomado[$k] = (float)$t['suma'];
		}
		mysqli_stmt_close($stTmp);
	}

	// ---------- 4) Render ----------
?>
	<div class="alert alert-info" style="padding:1px;margin-bottom:2px;margin-top:-10px;">
		<b>No. CV:</b> <?php echo htmlspecialchars($numero_cv, ENT_QUOTES); ?>
		<b>Consignado a: </b><?php echo htmlspecialchars($cliente_nom, ENT_QUOTES); ?>
	</div>
	<div class="panel panel-info" style="height:280px;overflow-y:auto;margin-bottom:5px;">
		<div class="table-responsive">
			<table class="table table-hover">
				<tr class="info">
					<th style="padding:2px;">N</th>
					<th style="padding:2px;">Código</th>
					<th style="padding:2px;">Producto</th>
					<th style="padding:2px;">Saldo</th>
					<th style="padding:2px;">Lote</th>
					<th style="padding:2px;">NUP</th>
					<th style="padding:2px;">Precios</th>
					<th style="padding:2px;">Precio</th>
					<th style="padding:2px;">Cantidad</th>
					<th style="padding:2px;">Descuento</th>
					<th style="padding:2px;">Subtotal</th>
				</tr>
				<?php
				$contador = 0;
				$fecha_actual = date('Y-m-d H:i:s');

				// Prepara consultas para precios (para reusar statement)
				$sqlPrecioBase = "SELECT precio_producto FROM productos_servicios WHERE id = ? LIMIT 1";
				$stPB = mysqli_prepare($con, $sqlPrecioBase);

				$sqlListaPrecios = "SELECT detalle_precio, precio
                                      FROM precios_productos
                                     WHERE id_producto = ?
                                       AND DATE_FORMAT(?, '%Y/%m/%d') BETWEEN DATE_FORMAT(fecha_desde, '%Y/%m/%d') AND DATE_FORMAT(fecha_hasta, '%Y/%m/%d')
                                     ORDER BY detalle_precio ASC";
				$stLP = mysqli_prepare($con, $sqlListaPrecios);

				foreach ($detRows as $detalle) {
					$contador++;
					$id_det = (int)$detalle['id_det_consignacion'];
					$cod    = $detalle['codigo_producto'];
					$idp    = (int)$detalle['id_producto'];
					$nom    = $detalle['nombre_producto'];
					$nup    = $detalle['nup'];
					$lote   = $detalle['lote'];
					$precio = (float)$detalle['precio'];

					// Saldo = Entradas - Salidas - TomadoTmp
					$kES = $idp . '|' . $lote . '|' . $nup;
					$kT  = $idp . '|' . $nup;

					$ent = isset($entradas[$kES]) ? (float)$entradas[$kES] : 0.0;
					$sal = isset($salidas[$kES])  ? (float)$salidas[$kES]  : 0.0;
					$tmp = isset($tomado[$kT])    ? (float)$tomado[$kT]    : 0.0;

					$saldo = number_format($ent - $sal - $tmp, 4, '.', '');

					// Precio base normal
					$precio_base = $precio; // fallback
					if ($stPB) {
						mysqli_stmt_bind_param($stPB, 'i', $idp);
						mysqli_stmt_execute($stPB);
						$rPB = mysqli_stmt_get_result($stPB);
						if ($rPB && ($rowPB = mysqli_fetch_assoc($rPB)) && isset($rowPB['precio_producto'])) {
							$precio_base = (float)$rowPB['precio_producto'];
						}
						mysqli_free_result($rPB);
					}

					// Lista de precios vigentes
					$precios_vig = [];
					if ($stLP) {
						mysqli_stmt_bind_param($stLP, 'is', $idp, $fecha_actual);
						mysqli_stmt_execute($stLP);
						$rLP = mysqli_stmt_get_result($stLP);
						while ($rowLP = mysqli_fetch_assoc($rLP)) {
							$precios_vig[] = $rowLP;
						}
						mysqli_free_result($rLP);
					}
				?>
					<tr>
						<input type="hidden" name="serie_cv" value="<?php echo htmlspecialchars($serie_cv, ENT_QUOTES); ?>">
						<input type="hidden" name="numero_cv" value="<?php echo htmlspecialchars($numero_cv, ENT_QUOTES); ?>">
						<input type="hidden" id="saldo<?php echo $id_det; ?>" value="<?php echo $saldo; ?>">

						<td style="padding:2px;"><?php echo $contador; ?></td>
						<td style="padding:2px;"><?php echo htmlspecialchars($cod, ENT_QUOTES); ?></td>
						<td style="padding:2px;"><?php echo htmlspecialchars($nom, ENT_QUOTES); ?></td>
						<td style="padding:2px;"><?php echo $saldo; ?></td>
						<td style="padding:2px;"><?php echo htmlspecialchars($lote, ENT_QUOTES); ?></td>
						<td style="padding:2px;"><?php echo htmlspecialchars($nup, ENT_QUOTES); ?></td>

						<td style="padding:2px;">
							<select class="form-control input-sm" id="lista_precios<?php echo $id_det; ?>" onChange="precios(<?php echo $id_det; ?>)">
								<option value="0" selected>Precios</option>
								<option value="<?php echo number_format($precio_base, 2, '.', ''); ?>">
									Normal <?php echo number_format($precio_base, 2, '.', ''); ?>
								</option>
								<?php foreach ($precios_vig as $pv) { ?>
									<option value="<?php echo number_format((float)$pv['precio'], 2, '.', ''); ?>">
										<?php echo htmlspecialchars($pv['detalle_precio'], ENT_QUOTES) . " " . number_format((float)$pv['precio'], 2, '.', ''); ?>
									</option>
								<?php } ?>
							</select>
						</td>

						<td style="padding:2px;" class="col-sm-1">
							<input type="text" class="form-control input-sm text-right"
								name="precio[<?php echo $id_det; ?>]"
								id="precio<?php echo $id_det; ?>"
								onchange="precio_facturacion('<?php echo $id_det; ?>');"
								value="<?php echo number_format($precio, 2, '.', ''); ?>">
						</td>
						<td style="padding:2px;" class="col-sm-1">
							<input type="text" class="form-control input-sm text-right"
								name="cantidad[<?php echo $id_det; ?>]"
								id="cantidad<?php echo $id_det; ?>"
								onchange="cantidad_facturacion('<?php echo $id_det; ?>');">
						</td>
						<td style="padding:2px;" class="col-sm-1">
							<input type="text" class="form-control input-sm text-right"
								name="descuento[<?php echo $id_det; ?>]"
								id="descuento<?php echo $id_det; ?>"
								onchange="descuento_facturacion('<?php echo $id_det; ?>');">
						</td>
						<td style="padding:2px;" class="col-sm-1">
							<input type="text" class="form-control input-sm text-right"
								name="subtotal[<?php echo $id_det; ?>]"
								id="subtotal<?php echo $id_det; ?>" readonly>
						</td>
					</tr>
				<?php
				} // foreach
				if ($stPB) mysqli_stmt_close($stPB);
				if ($stLP) mysqli_stmt_close($stLP);
				?>
			</table>
		</div>
	</div>
<?php
}



/* function detalle_consignacion_para_devolucion($numero_cv)
{
	$con = conenta_login();
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$busca_codigo_unico = mysqli_query($con, "SELECT * FROM encabezado_consignacion enc_con INNER JOIN clientes as cli ON enc_con.id_cli_pro=cli.id 
	WHERE enc_con.numero_consignacion = '" . $numero_cv . "' and enc_con.ruc_empresa='" . $ruc_empresa . "' ");
	$encabezado_consignacion = mysqli_fetch_array($busca_codigo_unico);
	$codigo_unico = isset($encabezado_consignacion['codigo_unico']) ? $encabezado_consignacion['codigo_unico'] : "";
	$busca_consignacion = mysqli_query($con, "SELECT * FROM detalle_consignacion WHERE codigo_unico = '" . $codigo_unico . "' ");
	if (!empty($codigo_unico)) {
	?>
		<div style="padding: 1px; margin-bottom: 2px; margin-top: -10px;" class="alert alert-info" role="alert">
			<b>Cliente: </b><?php echo $encabezado_consignacion['nombre']; ?>
		</div>
		<div class="panel panel-info" style="height: 300px; overflow-y: auto; margin-bottom: 5px;">
			<div class="table-responsive">
				<table class="table table-hover">
					<tr class="info">
						<th style="padding: 2px;">Código</th>
						<th style="padding: 2px;">Producto</th>
						<th style="padding: 2px;">Saldo</th>
						<th style="padding: 2px;">Lote</th>
						<th style="padding: 2px;">NUP</th>
						<th style="padding: 2px;" class="text-center">Cantidad</th>

					</tr>
					<?php
					while ($detalle = mysqli_fetch_array($busca_consignacion)) {
						$id_det_consignacion = $detalle['id_det_consignacion'];
						$codigo_producto = $detalle['codigo_producto'];
						$id_producto = $detalle['id_producto'];
						$nombre_producto = $detalle['nombre_producto'];
						$nup = $detalle['nup'];
						$lote = $detalle['lote'];
						$medida = $detalle['id_medida'];
						$bodega = $detalle['id_bodega'];
						$vencimiento = $detalle['vencimiento'];
						//buscar entradas
						$busca_entradas = mysqli_query($con, "SELECT sum(det.cant_consignacion) as entradas FROM encabezado_consignacion as enc INNER JOIN detalle_consignacion as det ON enc.codigo_unico=det.codigo_unico WHERE det.id_producto = '" . $id_producto . "' and enc.numero_consignacion='" . $numero_cv . "' and det.nup='" . $nup . "' and enc.ruc_empresa='" . $ruc_empresa . "' and enc.tipo_consignacion='VENTA' and enc.operacion = 'ENTRADA' and det.lote='" . $lote . "'");
						$row_entradas = mysqli_fetch_array($busca_entradas);
						$entradas = $row_entradas['entradas'];
						//buscar salidas
						$busca_salidas = mysqli_query($con, "SELECT sum(det.cant_consignacion) as salidas FROM encabezado_consignacion as enc INNER JOIN detalle_consignacion as det ON enc.codigo_unico=det.codigo_unico WHERE det.id_producto = '" . $id_producto . "' and det.numero_orden_entrada='" . $numero_cv . "' and det.nup='" . $nup . "' and enc.ruc_empresa='" . $ruc_empresa . "' and enc.tipo_consignacion='VENTA' and enc.operacion != 'ENTRADA' and det.lote='" . $lote . "'");
						$row_salidas = mysqli_fetch_array($busca_salidas);
						$saldo = number_format($entradas - $row_salidas['salidas'], 4, '.', '');
					?>
						<tr>
							<input type="hidden" name="id_producto[<?php echo $id_det_consignacion; ?>]" value="<?php echo $id_producto; ?>">
							<input type="hidden" name="codigo_producto[<?php echo $id_det_consignacion; ?>]" value="<?php echo $codigo_producto; ?>">
							<input type="hidden" name="nombre_producto[<?php echo $id_det_consignacion; ?>]" value="<?php echo $nombre_producto; ?>">
							<input type="hidden" name="nup[<?php echo $id_det_consignacion; ?>]" value="<?php echo $nup; ?>">
							<input type="hidden" name="lote[<?php echo $id_det_consignacion; ?>]" value="<?php echo $lote; ?>">
							<input type="hidden" name="medida[<?php echo $id_det_consignacion; ?>]" value="<?php echo $medida; ?>">
							<input type="hidden" name="bodega[<?php echo $id_det_consignacion; ?>]" value="<?php echo $bodega; ?>">
							<input type="hidden" name="vencimiento[<?php echo $id_det_consignacion; ?>]" value="<?php echo $vencimiento; ?>">
							<input type="hidden" name="registros[]" value="<?php echo $id_det_consignacion; ?>">
							<input type="hidden" id="saldo<?php echo $id_det_consignacion; ?>" value="<?php echo $saldo; ?>">
							<td style="padding: 2px;"><?php echo $codigo_producto; ?></td>
							<td style="padding: 2px;"><?php echo $nombre_producto; ?></td>
							<td style="padding: 2px;"><?php echo $saldo; ?></td>
							<td style="padding: 2px;"><?php echo $lote; ?></td>
							<td style="padding: 2px;"><?php echo $nup; ?></td>
							<td style="padding: 2px;" class="col-sm-2"><input type="number" class="form-control input-sm" name="devolucion[<?php echo $id_det_consignacion; ?>]" id="devolucion<?php echo $id_det_consignacion; ?>" onkeyup="cantidad_devolucion('<?php echo $id_det_consignacion; ?>');"></td>
						</tr>
					<?php

					}
					?>
				</table>
			</div>
		</div>
	<?php
	} else {
	?>
		<div class="alert alert-danger" role="alert">
			No hay registros para la consignación que busca.
		</div>
	<?php
	}
} */

function detalle_consignacion_para_devolucion($numero_cv)
{
	$con         = conenta_login();
	$ruc_empresa = $_SESSION['ruc_empresa'];

	// 1) Encabezado: obtener codigo_unico y cliente (seguro)
	$sqlEnc = "SELECT enc_con.codigo_unico, cli.nombre
                 FROM encabezado_consignacion enc_con
                 JOIN clientes cli ON enc_con.id_cli_pro = cli.id
                WHERE enc_con.numero_consignacion = ?
                  AND enc_con.ruc_empresa = ?
                LIMIT 1";
	if (!($stEnc = mysqli_prepare($con, $sqlEnc))) {
		echo "<div class='alert alert-danger'>Error cargando consignación.</div>";
		return;
	}
	mysqli_stmt_bind_param($stEnc, 'ss', $numero_cv, $ruc_empresa);
	mysqli_stmt_execute($stEnc);
	$resEnc = mysqli_stmt_get_result($stEnc);
	$encabezado = $resEnc ? mysqli_fetch_assoc($resEnc) : null;
	mysqli_stmt_close($stEnc);

	if (!$encabezado || empty($encabezado['codigo_unico'])) {
		echo "<div class='alert alert-danger' role='alert'>No hay registros para la consignación que busca.</div>";
		return;
	}
	$codigo_unico = $encabezado['codigo_unico'];
	$nom_cliente  = $encabezado['nombre'];

	// 2) Traer detalle base de la consignación (una sola consulta)
	$detRows = [];
	$sqlDet = "SELECT id_det_consignacion, codigo_producto, id_producto, nombre_producto,
                      nup, lote, id_medida, id_bodega, vencimiento
                 FROM detalle_consignacion
                WHERE codigo_unico = ? AND ruc_empresa = ?";
	if ($stDet = mysqli_prepare($con, $sqlDet)) {
		mysqli_stmt_bind_param($stDet, 'ss', $codigo_unico, $ruc_empresa);
		mysqli_stmt_execute($stDet);
		$resDet = mysqli_stmt_get_result($stDet);
		while ($r = mysqli_fetch_assoc($resDet)) $detRows[] = $r;
		mysqli_stmt_close($stDet);
	}
	if (empty($detRows)) {
		echo "<div class='alert alert-info'>La consignación no tiene ítems.</div>";
		return;
	}

	// 3) Pre-agregar ENTRADAS (consignado) para esta CV: por producto+lote+nup
	$entradas = []; // key: idp|lote|nup => sum
	$sqlEnt = "SELECT det.id_producto, det.lote, det.nup, SUM(det.cant_consignacion) AS entradas
                 FROM encabezado_consignacion enc
                 JOIN detalle_consignacion det
                   ON det.codigo_unico = enc.codigo_unico
                  AND det.ruc_empresa  = enc.ruc_empresa
                WHERE enc.ruc_empresa       = ?
                  AND enc.tipo_consignacion = 'VENTA'
                  AND enc.operacion         = 'ENTRADA'
                  AND enc.numero_consignacion = ?
                GROUP BY det.id_producto, det.lote, det.nup";
	if ($stEnt = mysqli_prepare($con, $sqlEnt)) {
		mysqli_stmt_bind_param($stEnt, 'ss', $ruc_empresa, $numero_cv);
		mysqli_stmt_execute($stEnt);
		$resEnt = mysqli_stmt_get_result($stEnt);
		while ($e = mysqli_fetch_assoc($resEnt)) {
			$k = $e['id_producto'] . '|' . $e['lote'] . '|' . $e['nup'];
			$entradas[$k] = (float)$e['entradas'];
		}
		mysqli_stmt_close($stEnt);
	}

	// 4) Pre-agregar SALIDAS (devoluciones/facturas) vinculadas a esta CV: por producto+lote+nup
	$salidas = []; // key: idp|lote|nup => sum
	$sqlSal = "SELECT det.id_producto, det.lote, det.nup, SUM(det.cant_consignacion) AS salidas
                 FROM encabezado_consignacion enc
                 JOIN detalle_consignacion det
                   ON det.codigo_unico = enc.codigo_unico
                  AND det.ruc_empresa  = enc.ruc_empresa
                WHERE enc.ruc_empresa       = ?
                  AND enc.tipo_consignacion = 'VENTA'
                  AND enc.operacion        <> 'ENTRADA'
                  AND det.numero_orden_entrada = ?
                GROUP BY det.id_producto, det.lote, det.nup";
	if ($stSal = mysqli_prepare($con, $sqlSal)) {
		mysqli_stmt_bind_param($stSal, 'ss', $ruc_empresa, $numero_cv);
		mysqli_stmt_execute($stSal);
		$resSal = mysqli_stmt_get_result($stSal);
		while ($s = mysqli_fetch_assoc($resSal)) {
			$k = $s['id_producto'] . '|' . $s['lote'] . '|' . $s['nup'];
			$salidas[$k] = (float)$s['salidas'];
		}
		mysqli_stmt_close($stSal);
	}

	// 5) Render HTML
?>
	<div class="alert alert-info" style="padding:1px;margin-bottom:2px;margin-top:-10px;">
		<b>Cliente: </b><?php echo htmlspecialchars($nom_cliente, ENT_QUOTES); ?>
	</div>
	<div class="panel panel-info" style="height:300px;overflow-y:auto;margin-bottom:5px;">
		<div class="table-responsive">
			<table class="table table-hover">
				<tr class="info">
					<th style="padding:2px;">Código</th>
					<th style="padding:2px;">Producto</th>
					<th style="padding:2px;">Saldo</th>
					<th style="padding:2px;">Lote</th>
					<th style="padding:2px;">NUP</th>
					<th style="padding:2px;" class="text-center">Cantidad</th>
				</tr>
				<?php foreach ($detRows as $detalle):
					$id_det = (int)$detalle['id_det_consignacion'];
					$idp    = (int)$detalle['id_producto'];
					$cod    = $detalle['codigo_producto'];
					$nom    = $detalle['nombre_producto'];
					$nup    = $detalle['nup'];
					$lote   = $detalle['lote'];
					$medida = (int)$detalle['id_medida'];
					$bodega = (int)$detalle['id_bodega'];
					$vto    = $detalle['vencimiento'];

					$k = $idp . '|' . $lote . '|' . $nup;
					$ent = isset($entradas[$k]) ? (float)$entradas[$k] : 0.0;
					$sal = isset($salidas[$k])  ? (float)$salidas[$k]  : 0.0;
					$saldo = number_format($ent - $sal, 4, '.', '');
				?>
					<tr>
						<input type="hidden" name="id_producto[<?php echo $id_det; ?>]" value="<?php echo (int)$idp; ?>">
						<input type="hidden" name="codigo_producto[<?php echo $id_det; ?>]" value="<?php echo htmlspecialchars($cod, ENT_QUOTES); ?>">
						<input type="hidden" name="nombre_producto[<?php echo $id_det; ?>]" value="<?php echo htmlspecialchars($nom, ENT_QUOTES); ?>">
						<input type="hidden" name="nup[<?php echo $id_det; ?>]" value="<?php echo htmlspecialchars($nup, ENT_QUOTES); ?>">
						<input type="hidden" name="lote[<?php echo $id_det; ?>]" value="<?php echo htmlspecialchars($lote, ENT_QUOTES); ?>">
						<input type="hidden" name="medida[<?php echo $id_det; ?>]" value="<?php echo $medida; ?>">
						<input type="hidden" name="bodega[<?php echo $id_det; ?>]" value="<?php echo $bodega; ?>">
						<input type="hidden" name="vencimiento[<?php echo $id_det; ?>]" value="<?php echo htmlspecialchars($vto, ENT_QUOTES); ?>">
						<input type="hidden" name="registros[]" value="<?php echo $id_det; ?>">
						<input type="hidden" id="saldo<?php echo $id_det; ?>" value="<?php echo $saldo; ?>">

						<td style="padding:2px;"><?php echo htmlspecialchars($cod, ENT_QUOTES); ?></td>
						<td style="padding:2px;"><?php echo htmlspecialchars($nom, ENT_QUOTES); ?></td>
						<td style="padding:2px;"><?php echo $saldo; ?></td>
						<td style="padding:2px;"><?php echo htmlspecialchars($lote, ENT_QUOTES); ?></td>
						<td style="padding:2px;"><?php echo htmlspecialchars($nup, ENT_QUOTES); ?></td>
						<td style="padding:2px;" class="col-sm-2">
							<input type="number" class="form-control input-sm"
								name="devolucion[<?php echo $id_det; ?>]"
								id="devolucion<?php echo $id_det; ?>"
								onkeyup="cantidad_devolucion('<?php echo $id_det; ?>');">
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
		</div>
	</div>
<?php
}



/* function detalle_nueva_factura_consignacion_ventas()
{
	$con = conenta_login();
	$id_usuario = $_SESSION['id_usuario'];
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$busca_detalle = mysqli_query($con, "SELECT fat_tmp.descuento as descuento, 
	fat_tmp.id_producto as id_producto, fat_tmp.precio_tmp as precio_tmp, 
	fat_tmp.id as id_tmp, fat_tmp.tarifa_ice as nup, pro_ser.codigo_producto as codigo_producto, 
	fat_tmp.cantidad_tmp as cantidad, pro_ser.nombre_producto as nombre_producto, uni_med.abre_medida as medida, 
	fat_tmp.tarifa_botellas as num_con, fat_tmp.lote as lote, bod.nombre_bodega as bodega, fat_tmp.subtotal as subtotal,
	fat_tmp.tarifa_iva as tarifa_iva  
	FROM factura_tmp as fat_tmp INNER JOIN productos_servicios as pro_ser ON fat_tmp.id_producto = pro_ser.id INNER JOIN 
	unidad_medida as uni_med ON fat_tmp.id_medida=uni_med.id_medida 
	INNER JOIN bodega as bod ON fat_tmp.id_bodega=bod.id_bodega WHERE fat_tmp.id_usuario = '" . $id_usuario . "' and fat_tmp.ruc_empresa = '" . $ruc_empresa . "' ");
	?>
	<div class="panel panel-info" style="height: 280px; overflow-y: auto; margin-bottom: 5px;">
		<div class="table-responsive">
			<table class="table table-hover">
				<tr class="info">
					<th style="padding: 2px;">No.CV</th>
					<th style="padding: 2px;">Código</th>
					<th style="padding: 2px;">Producto</th>
					<th style="padding: 2px;">Cant</th>
					<th style="padding: 2px;">Bodega</th>
					<th style="padding: 2px;">Lote</th>
					<th style="padding: 2px;">Nup</th>
					<th style="padding: 2px;">Precio</th>
					<th style="padding: 2px;">Descuento</th>
					<th style="padding: 2px;">Subtotal</th>
					<th style="padding: 2px;">Vencimiento</th>
					<th style="padding: 2px;" class='text-right'>Opciones</th>
				</tr>
				<?php
				$subtotal_general = 0;
				$total_descuento = 0;
				while ($detalle = mysqli_fetch_array($busca_detalle)) {
					$id_detalle = $detalle['id_tmp'];
					$id_producto = $detalle['id_producto'];
					$codigo_producto = $detalle['codigo_producto'];
					$nombre_producto = $detalle['nombre_producto'];
					$cantidad = $detalle['cantidad'];
					$numero_consignacion = $detalle['num_con'];
					$bodega = $detalle['bodega'];
					$lote = $detalle['lote'];
					$nup = $detalle['nup'];
					$precio = $detalle['precio_tmp'];
					$descuento = $detalle['descuento'];
					$total_descuento += $descuento;
					//buscar salidas
					$busca_vencimiento = mysqli_query($con, "SELECT * FROM inventarios WHERE id_producto = '" . $id_producto . "' and lote='" . $lote . "' and operacion='ENTRADA'");
					$row_vencimiento = mysqli_fetch_array($busca_vencimiento);
					$vencimiento = date('d-m-Y', strtotime($row_vencimiento['fecha_vencimiento']));
					$subtotal_general += $detalle['subtotal'];
				?>
					<input type="hidden" id="subtotal_item<?php echo $id_detalle; ?>" value="<?php echo number_format($detalle['subtotal'], 2, '.', ''); ?>">
					<input type="hidden" id="descuento_inicial<?php echo $id_detalle; ?>" value="<?php echo $detalle['descuento']; ?>">
					<input type="hidden" id="tarifa_item<?php echo $id_detalle; ?>" value="<?php echo $detalle['tarifa_iva']; ?>">
					<tr>
						<td style="padding: 2px;"><?php echo $numero_consignacion; ?></td>
						<td style="padding: 2px;"><?php echo $codigo_producto; ?></td>
						<td style="padding: 2px;"><?php echo $nombre_producto; ?></td>
						<td style="padding: 2px;"><?php echo number_format($cantidad, 2, '.', ''); ?></td>
						<td style="padding: 2px;"><?php echo $bodega; ?></td>
						<td style="padding: 2px;"><?php echo $lote; ?></td>
						<td style="padding: 2px;"><?php echo $nup; ?></td>
						<td style="padding: 2px;"><?php echo number_format($precio, 4, '.', ''); ?></td>
						<td style="padding: 2px;"><?php echo number_format($descuento, 4, '.', ''); ?></td>
						<td style="padding: 2px;"><?php echo number_format($detalle['subtotal'] - $descuento, 2, '.', ''); ?></td>
						<td style="padding: 2px;"><?php echo $vencimiento; ?></td>
						<td style="padding: 2px;" class='text-right'>
							<button type="button" class="btn btn-info btn-xs" title="Opciones de descuentos" onclick="opciones_descuentos('<?php echo $id_detalle; ?>')" data-toggle="modal" data-target="#aplicarDescuento">D</button>
							<a class='btn btn-danger btn-xs' title='Eliminar' onclick="eliminar_item_factura_consignacion('<?php echo $id_detalle; ?>')"><i class="glyphicon glyphicon-remove"></i></a>
						</td>
					</tr>
				<?php
				}
				?>
			</table>
		</div>
	</div>
	<div class="row" style="margin-bottom: -20px; margin-top: -4px; height: 10%">
		<div class="col-xs-6">
		</div>
		<div class="col-xs-6">
			<div class="panel panel-info">
				<div class="table-responsive">
					<table class="table">
						<tr class="info">
							<td style="padding: 2px;" class='text-right'>SUBTOTAL GENERAL: </td>
							<td style="padding: 2px;" class='text-center'><?php echo number_format($subtotal_general, 2, '.', ''); ?></td>
							<td style="padding: 2px;"></td>
							<td style="padding: 2px;"></td>
						</tr>
						<?php
						//PARA MOSTRAR LOS NOMBRES DE CADA TARIFA DE IVA Y LOS VALORES DE CADA SUBTOTAL
						$subtotal_tarifa_iva = 0;
						$sql = mysqli_query($con, "SELECT ti.tarifa as tarifa, 
						round(sum(ft.subtotal - ft.descuento),2) as suma_tarifa_iva 
						FROM factura_tmp as ft INNER JOIN tarifa_iva as ti ON ti.codigo = ft.tarifa_iva 
						WHERE ft.id_usuario= '" . $id_usuario . "' and ft.ruc_empresa= '" . $ruc_empresa . "' 
						group by ft.tarifa_iva ");
						while ($row = mysqli_fetch_array($sql)) {
							$nombre_tarifa_iva = strtoupper($row["tarifa"]);
							$subtotal_tarifa_iva = $row['suma_tarifa_iva'];
						?>
							<tr class="info">
								<td style="padding: 2px;" class='text-right'>SUBTOTAL <?php echo ($nombre_tarifa_iva); ?>:</td>
								<td style="padding: 2px;" class='text-center'><?php echo number_format($subtotal_tarifa_iva, 2, '.', ''); ?></td>
								<td style="padding: 2px;"></td>
								<td style="padding: 2px;"></td>
							</tr>

						<?php
						}
						?>
						<tr class="info">
							<td style="padding: 2px;" class='text-right'>TOTAL DESCUENTO: </td>
							<td style="padding: 2px;" class='text-center'><?php echo number_format($total_descuento, 2, '.', ''); ?></td>
							<td style="padding: 2px;"></td>
							<td style="padding: 2px;"></td>
						</tr>
						<?php
						//PARA MOSTRAR LOS IVAS
						$total_iva = 0;
						$suma_iva = 0;
						$sql = mysqli_query($con, "SELECT ti.tarifa as tarifa, 
						round(sum((ft.cantidad_tmp * ft.precio_tmp - ft.descuento) * (ti.porcentaje_iva/100)),2) as total_iva 
						FROM factura_tmp as ft 
						INNER JOIN tarifa_iva as ti ON ti.codigo = ft.tarifa_iva 
						WHERE ft.id_usuario= '" . $id_usuario . "' and ft.ruc_empresa= '" . $ruc_empresa . "' 
						and ti.porcentaje_iva > 0 group by ft.tarifa_iva ");
						while ($row = mysqli_fetch_array($sql)) {
							$nombre_porcentaje_iva = strtoupper($row["tarifa"]);
							$total_iva = $row['total_iva'];
							$suma_iva += $row['total_iva'];
						?>
							<tr class="info">
								<td style="padding: 2px;" class='text-right'>IVA <?php echo ($nombre_porcentaje_iva); ?>:</td>
								<td style="padding: 2px;" class='text-center'><?php echo $total_iva; ?></td>
								<td style="padding: 2px;"></td>
								<td style="padding: 2px;"></td>
							</tr>
						<?php
						}
						?>
						<tr class="info">
							<td style="padding: 2px;" class='text-right'>TOTAL: </td>
							<td style="padding: 2px;" class='text-center'><?php echo number_format($subtotal_general + $suma_iva - $total_descuento, 2, '.', ''); ?></td>
							<td style="padding: 2px;"></td>
							<td style="padding: 2px;"></td>
						</tr>
					</table>
				</div>
			</div>
		</div>
	<?php
}
 */

function detalle_nueva_factura_consignacion_ventas()
{
	$con         = conenta_login();
	$id_usuario  = $_SESSION['id_usuario'];
	$ruc_empresa = $_SESSION['ruc_empresa'];

	// ---------- Detalle principal (con vencimiento pre-resuelto) ----------
	// Subquery inv: para cada (id_producto,lote) tomar la última fecha_vencimiento con operacion='ENTRADA'
	$sqlDetalle = "
        SELECT 
            ft.id                          AS id_tmp,
            ft.id_producto                 AS id_producto,
            ps.codigo_producto             AS codigo_producto,
            ps.nombre_producto             AS nombre_producto,
            um.abre_medida                 AS medida,
            b.nombre_bodega                AS bodega,
            ft.cantidad_tmp                AS cantidad,
            ft.precio_tmp                  AS precio_tmp,
            ft.descuento                   AS descuento,
            ft.subtotal                    AS subtotal,
            ft.tarifa_iva                  AS tarifa_iva,
            ft.tarifa_ice                  AS nup,
            ft.tarifa_botellas             AS num_con,
            ft.lote                        AS lote,
            DATE_FORMAT(inv.fecha_vencimiento, '%d-%m-%Y') AS vencimiento
        FROM factura_tmp ft
        INNER JOIN productos_servicios ps ON ps.id = ft.id_producto
        INNER JOIN unidad_medida um       ON um.id_medida = ft.id_medida
        INNER JOIN bodega b               ON b.id_bodega  = ft.id_bodega
        LEFT JOIN (
            SELECT i.id_producto, i.lote, MAX(i.fecha_vencimiento) AS fecha_vencimiento
              FROM inventarios i
             WHERE i.operacion = 'ENTRADA'
             GROUP BY i.id_producto, i.lote
        ) inv ON inv.id_producto = ft.id_producto AND inv.lote = ft.lote
        WHERE ft.id_usuario = ? AND ft.ruc_empresa = ?
        ORDER BY ft.id ASC
    ";

	$items = [];
	if ($st = mysqli_prepare($con, $sqlDetalle)) {
		// Ajusta el tipo de id_usuario a 'i' si es INT en tu esquema
		mysqli_stmt_bind_param($st, 'ss', $id_usuario, $ruc_empresa);
		mysqli_stmt_execute($st);
		$rs = mysqli_stmt_get_result($st);
		while ($row = mysqli_fetch_assoc($rs)) {
			$items[] = $row;
		}
		mysqli_stmt_close($st);
	} else {
		echo "<div class='alert alert-danger'>No se pudo cargar el detalle.</div>";
		return;
	}

	// Totales base (PHP) para tener ya sumas en mano
	$subtotal_general = 0.0;
	$total_descuento  = 0.0;
	foreach ($items as $it) {
		$subtotal_general += (float)$it['subtotal'];
		$total_descuento  += (float)$it['descuento'];
	}

	// ---------- Subtotales por tarifa IVA ----------
	$subporTarifa = [];
	$sqlSubTarifa = "
        SELECT ti.tarifa AS nombre, ROUND(SUM(ft.subtotal - ft.descuento), 2) AS suma_tarifa_iva
          FROM factura_tmp ft
          INNER JOIN tarifa_iva ti ON ti.codigo = ft.tarifa_iva
         WHERE ft.id_usuario = ? AND ft.ruc_empresa = ?
         GROUP BY ft.tarifa_iva, ti.tarifa
         ORDER BY ti.tarifa
    ";
	if ($st = mysqli_prepare($con, $sqlSubTarifa)) {
		mysqli_stmt_bind_param($st, 'ss', $id_usuario, $ruc_empresa);
		mysqli_stmt_execute($st);
		$rs = mysqli_stmt_get_result($st);
		while ($row = mysqli_fetch_assoc($rs)) {
			$subporTarifa[] = $row;
		}
		mysqli_stmt_close($st);
	}

	// ---------- IVA por tarifa (solo > 0) ----------
	$ivas = [];
	$suma_iva = 0.0;
	$sqlIva = "
        SELECT ti.tarifa AS nombre, 
               ROUND(SUM( (ft.cantidad_tmp * ft.precio_tmp - ft.descuento) * (ti.porcentaje_iva/100) ), 2) AS total_iva
          FROM factura_tmp ft
          INNER JOIN tarifa_iva ti ON ti.codigo = ft.tarifa_iva
         WHERE ft.id_usuario = ? AND ft.ruc_empresa = ? AND ti.porcentaje_iva > 0
         GROUP BY ft.tarifa_iva, ti.tarifa
         ORDER BY ti.tarifa
    ";
	if ($st = mysqli_prepare($con, $sqlIva)) {
		mysqli_stmt_bind_param($st, 'ss', $id_usuario, $ruc_empresa);
		mysqli_stmt_execute($st);
		$rs = mysqli_stmt_get_result($st);
		while ($row = mysqli_fetch_assoc($rs)) {
			$row['total_iva'] = (float)$row['total_iva'];
			$ivas[] = $row;
			$suma_iva += $row['total_iva'];
		}
		mysqli_stmt_close($st);
	}

	$total_general = (float)number_format($subtotal_general + $suma_iva - $total_descuento, 2, '.', '');

	// ---------- Render ----------
?>
	<div class="panel panel-info" style="height:280px; overflow-y:auto; margin-bottom:5px;">
		<div class="table-responsive">
			<table class="table table-hover">
				<tr class="info">
					<th style="padding:2px;">No.CV</th>
					<th style="padding:2px;">Código</th>
					<th style="padding:2px;">Producto</th>
					<th style="padding:2px;">Cant</th>
					<th style="padding:2px;">Bodega</th>
					<th style="padding:2px;">Lote</th>
					<th style="padding:2px;">Nup</th>
					<th style="padding:2px;">Precio</th>
					<th style="padding:2px;">Descuento</th>
					<th style="padding:2px;">Subtotal</th>
					<th style="padding:2px;">Vencimiento</th>
					<th style="padding:2px;" class="text-right">Opciones</th>
				</tr>
				<?php foreach ($items as $d):
					$id_detalle       = (int)$d['id_tmp'];
					$codigo_producto  = $d['codigo_producto'];
					$nombre_producto  = $d['nombre_producto'];
					$cantidad         = (float)$d['cantidad'];
					$numero_con       = $d['num_con'];
					$bodega           = $d['bodega'];
					$lote             = $d['lote'];
					$nup              = $d['nup'];
					$precio           = (float)$d['precio_tmp'];
					$descuento        = (float)$d['descuento'];
					$subtotal_bruto   = (float)$d['subtotal'];
					$venc             = $d['vencimiento']; // ya viene dd-mm-YYYY o NULL
					$tarifa_iva       = $d['tarifa_iva'];

					$subtotal_neto    = (float)number_format($subtotal_bruto - $descuento, 2, '.', '');
				?>
					<input type="hidden" id="subtotal_item<?php echo $id_detalle; ?>" value="<?php echo number_format($subtotal_bruto, 2, '.', ''); ?>">
					<input type="hidden" id="descuento_inicial<?php echo $id_detalle; ?>" value="<?php echo number_format($descuento, 2, '.', ''); ?>">
					<input type="hidden" id="tarifa_item<?php echo $id_detalle; ?>" value="<?php echo htmlspecialchars($tarifa_iva, ENT_QUOTES); ?>">

					<tr>
						<td style="padding:2px;"><?php echo htmlspecialchars($numero_con, ENT_QUOTES); ?></td>
						<td style="padding:2px;"><?php echo htmlspecialchars($codigo_producto, ENT_QUOTES); ?></td>
						<td style="padding:2px;"><?php echo htmlspecialchars($nombre_producto, ENT_QUOTES); ?></td>
						<td style="padding:2px;"><?php echo number_format($cantidad, 2, '.', ''); ?></td>
						<td style="padding:2px;"><?php echo htmlspecialchars($bodega, ENT_QUOTES); ?></td>
						<td style="padding:2px;"><?php echo htmlspecialchars($lote, ENT_QUOTES); ?></td>
						<td style="padding:2px;"><?php echo htmlspecialchars($nup, ENT_QUOTES); ?></td>
						<td style="padding:2px;"><?php echo number_format($precio, 4, '.', ''); ?></td>
						<td style="padding:2px;"><?php echo number_format($descuento, 2, '.', ''); ?></td>
						<td style="padding:2px;"><?php echo number_format($subtotal_neto, 2, '.', ''); ?></td>
						<td style="padding:2px;"><?php echo $venc ? htmlspecialchars($venc, ENT_QUOTES) : ''; ?></td>
						<td style="padding:2px;" class="text-right">
							<button type="button" class="btn btn-info btn-xs" title="Opciones de descuentos"
								onclick="opciones_descuentos('<?php echo $id_detalle; ?>')" data-toggle="modal" data-target="#aplicarDescuento">D</button>
							<a class="btn btn-danger btn-xs" title="Eliminar"
								onclick="eliminar_item_factura_consignacion('<?php echo $id_detalle; ?>')"><i class="glyphicon glyphicon-remove"></i></a>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
		</div>
	</div>

	<div class="row" style="margin-bottom:-20px; margin-top:-4px; height:10%">
		<div class="col-xs-6"></div>
		<div class="col-xs-6">
			<div class="panel panel-info">
				<div class="table-responsive">
					<table class="table">
						<tr class="info">
							<td style="padding:2px;" class="text-right">SUBTOTAL GENERAL: </td>
							<td style="padding:2px;" class="text-center"><?php echo number_format($subtotal_general, 2, '.', ''); ?></td>
							<td style="padding:2px;"></td>
							<td style="padding:2px;"></td>
						</tr>

						<?php foreach ($subporTarifa as $row): ?>
							<tr class="info">
								<td style="padding:2px;" class="text-right">
									SUBTOTAL <?php echo htmlspecialchars(strtoupper($row['nombre']), ENT_QUOTES); ?>:
								</td>
								<td style="padding:2px;" class="text-center">
									<?php echo number_format((float)$row['suma_tarifa_iva'], 2, '.', ''); ?>
								</td>
								<td style="padding:2px;"></td>
								<td style="padding:2px;"></td>
							</tr>
						<?php endforeach; ?>

						<tr class="info">
							<td style="padding:2px;" class="text-right">TOTAL DESCUENTO: </td>
							<td style="padding:2px;" class="text-center"><?php echo number_format($total_descuento, 2, '.', ''); ?></td>
							<td style="padding:2px;"></td>
							<td style="padding:2px;"></td>
						</tr>

						<?php foreach ($ivas as $row): ?>
							<tr class="info">
								<td style="padding:2px;" class="text-right">
									IVA <?php echo htmlspecialchars(strtoupper($row['nombre']), ENT_QUOTES); ?>:
								</td>
								<td style="padding:2px;" class="text-center">
									<?php echo number_format((float)$row['total_iva'], 2, '.', ''); ?>
								</td>
								<td style="padding:2px;"></td>
								<td style="padding:2px;"></td>
							</tr>
						<?php endforeach; ?>

						<tr class="info">
							<td style="padding:2px;" class="text-right">TOTAL: </td>
							<td style="padding:2px;" class="text-center"><?php echo number_format($total_general, 2, '.', ''); ?></td>
							<td style="padding:2px;"></td>
							<td style="padding:2px;"></td>
						</tr>
					</table>
				</div>
			</div>
		</div>
	</div>
<?php
}



/* function detalle_nueva_consignacion_venta()
{
	$con = conenta_login();
	$id_usuario = $_SESSION['id_usuario'];
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$busca_detalle = mysqli_query($con, "SELECT fat_tmp.tarifa_ice as nup, fat_tmp.id as id_tmp, 
	pro_ser.codigo_producto as codigo_producto, fat_tmp.cantidad_tmp as cantidad, 
	pro_ser.nombre_producto as nombre_producto, uni_med.abre_medida as medida, 
	bod.nombre_bodega as bodega, fat_tmp.vencimiento as vencimiento, fat_tmp.lote as lote, fat_tmp.subtotal as subtotal  
	FROM factura_tmp as fat_tmp 
	INNER JOIN productos_servicios as pro_ser 
	ON fat_tmp.id_producto = pro_ser.id 
	INNER JOIN bodega as bod ON fat_tmp.id_bodega=bod.id_bodega 
	INNER JOIN unidad_medida as uni_med 
	ON fat_tmp.id_medida=uni_med.id_medida 
	WHERE fat_tmp.id_usuario = '" . $id_usuario . "' and fat_tmp.ruc_empresa = '" . $ruc_empresa . "' ");
?>
	<div class="panel panel-info" style="height: 300px; overflow-y: auto; margin-bottom: 5px;">
		<div class="table-responsive">
			<table class="table table-hover">
				<tr class="info">
					<th style="padding: 2px;">Código</th>
					<th style="padding: 2px;">Producto</th>
					<th style="padding: 2px;">Cant</th>
					<th style="padding: 2px;">Bodega</th>
					<th style="padding: 2px;">Lote</th>
					<th style="padding: 2px;">Nup</th>
					<th style="padding: 2px;" class='text-right'>Eliminar</th>
				</tr>
				<?php
				while ($detalle = mysqli_fetch_array($busca_detalle)) {
					$id_detalle = $detalle['id_tmp'];
					$codigo_producto = $detalle['codigo_producto'];
					$nombre_producto = $detalle['nombre_producto'];
					$cantidad = $detalle['cantidad'];
					$bodega = $detalle['bodega'];
					$lote = $detalle['lote'];
					$nup = $detalle['nup'];
					$vencimiento = date('d-m-Y', strtotime($detalle['vencimiento']));
				?>
					<tr>
						<td style="padding: 2px;"><?php echo $codigo_producto; ?></td>
						<td style="padding: 2px;"><?php echo $nombre_producto; ?></td>
						<td style="padding: 2px;"><?php echo $cantidad; ?></td>
						<td style="padding: 2px;"><?php echo $bodega; ?></td>
						<td style="padding: 2px;"><?php echo $lote; ?></td>
						<td style="padding: 2px;"><?php echo $nup; ?></td>
						<td style="padding: 2px;" class='text-right'><a href="#" class='btn btn-danger btn-xs' title='Eliminar' onclick="eliminar_detalle_consignacion('<?php echo $id_detalle; ?>')"><i class="glyphicon glyphicon-remove"></i></a></td>
					</tr>
				<?php
				}
				?>
			</table>
		</div>
	</div>
<?php
} */


function detalle_nueva_consignacion_venta()
{
	$con         = conenta_login();
	$id_usuario  = $_SESSION['id_usuario'];
	$ruc_empresa = $_SESSION['ruc_empresa'];

	// Consulta segura (sin concatenar)
	$sql = "
        SELECT 
            ft.id                    AS id_tmp,
            ps.codigo_producto       AS codigo_producto,
            ps.nombre_producto       AS nombre_producto,
            ft.cantidad_tmp          AS cantidad,
            b.nombre_bodega          AS bodega,
            ft.lote                  AS lote,
            ft.tarifa_ice            AS nup,
            ft.vencimiento           AS vencimiento
        FROM factura_tmp ft
        INNER JOIN productos_servicios ps ON ps.id = ft.id_producto
        INNER JOIN bodega b               ON b.id_bodega = ft.id_bodega
        INNER JOIN unidad_medida um       ON um.id_medida = ft.id_medida
        WHERE ft.id_usuario = ? AND ft.ruc_empresa = ?
        ORDER BY ft.id ASC
    ";

	$items = [];
	if ($stmt = mysqli_prepare($con, $sql)) {
		// Ajusta el tipo de id_usuario a 'i' si es INT en tu esquema
		mysqli_stmt_bind_param($stmt, 'ss', $id_usuario, $ruc_empresa);
		mysqli_stmt_execute($stmt);
		$res = mysqli_stmt_get_result($stmt);
		while ($row = mysqli_fetch_assoc($res)) {
			$items[] = $row;
		}
		mysqli_stmt_close($stmt);
	} else {
		echo "<div class='alert alert-danger'>No se pudo cargar el detalle.</div>";
		return;
	}
?>
	<div class="panel panel-info" style="height:300px; overflow-y:auto; margin-bottom:5px;">
		<div class="table-responsive">
			<table class="table table-hover">
				<tr class="info">
					<th style="padding:2px;">Código</th>
					<th style="padding:2px;">Producto</th>
					<th style="padding:2px;">Cant</th>
					<th style="padding:2px;">Bodega</th>
					<th style="padding:2px;">Lote</th>
					<th style="padding:2px;">Nup</th>
					<th style="padding:2px;" class="text-right">Eliminar</th>
				</tr>
				<?php if (empty($items)): ?>
					<tr>
						<td colspan="7" class="text-center" style="padding:6px;">Sin ítems por mostrar.</td>
					</tr>
				<?php else: ?>
					<?php foreach ($items as $d):
						$id_detalle = (int)$d['id_tmp'];
						$codigo     = $d['codigo_producto'];
						$nombre     = $d['nombre_producto'];
						$cantidad   = (float)$d['cantidad'];
						$bodega     = $d['bodega'];
						$lote       = $d['lote'];
						$nup        = $d['nup'];
						// Vencimiento puede venir NULL o vacío
						$venc_raw   = $d['vencimiento'];
						$venc       = $venc_raw ? date('d-m-Y', strtotime($venc_raw)) : '';
					?>
						<tr>
							<td style="padding:2px;"><?php echo htmlspecialchars($codigo, ENT_QUOTES); ?></td>
							<td style="padding:2px;"><?php echo htmlspecialchars($nombre, ENT_QUOTES); ?></td>
							<td style="padding:2px;"><?php echo number_format($cantidad, 2, '.', ''); ?></td>
							<td style="padding:2px;"><?php echo htmlspecialchars($bodega, ENT_QUOTES); ?></td>
							<td style="padding:2px;"><?php echo htmlspecialchars($lote, ENT_QUOTES); ?></td>
							<td style="padding:2px;"><?php echo htmlspecialchars($nup, ENT_QUOTES); ?></td>
							<td style="padding:2px;" class="text-right">
								<a href="#" class="btn btn-danger btn-xs" title="Eliminar"
									onclick="eliminar_detalle_consignacion('<?php echo $id_detalle; ?>')">
									<i class="glyphicon glyphicon-remove"></i>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</table>
		</div>
	</div>
<?php
}



/* function detalle_consignacion($codigo_unico)
{
	$con = conenta_login();
	$busca_encabezado = mysqli_query($con, "SELECT ven.nombre as asesor, cli.nombre as cliente, 
	enc_con.numero_consignacion as numero_consignacion, enc_con.fecha_consignacion as fecha_consignacion,
	enc_con.punto_partida as punto_partida, enc_con.punto_llegada as punto_llegada, enc_con.observaciones as observaciones, enc_con.fecha_registro as fecha_registro
	 FROM encabezado_consignacion as enc_con 
	INNER JOIN clientes as cli ON enc_con.id_cli_pro=cli.id
	INNER JOIN vendedores as ven ON ven.id_vendedor=enc_con.responsable 
	WHERE enc_con.codigo_unico = '" . $codigo_unico . "' ");
	$encabezado_consignacion = mysqli_fetch_array($busca_encabezado);
	$busca_detalle = mysqli_query($con, "SELECT * FROM detalle_consignacion as det_con INNER JOIN bodega as bod ON det_con.id_bodega=bod.id_bodega INNER JOIN unidad_medida as uni_med ON det_con.id_medida=uni_med.id_medida WHERE det_con.codigo_unico = '" . $codigo_unico . "' ");
?>
	<div style="padding: 1px; margin-bottom: 2px; margin-top: -10px;" class="alert alert-info" role="alert">
		<b>No:</b> <?php echo $encabezado_consignacion['numero_consignacion']; ?> <b>Fecha:</b> <?php echo date("d/m/Y", strtotime($encabezado_consignacion['fecha_consignacion'])); ?> <b>Hora:</b> <?php echo date("H:i", strtotime($encabezado_consignacion['fecha_registro'])); ?> <b>Cliente: </b><?php echo $encabezado_consignacion['cliente']; ?>
		<b>Punto salida: </b><?php echo $encabezado_consignacion['punto_partida']; ?> <b>Punto llegada: </b><?php echo $encabezado_consignacion['punto_llegada']; ?> <b>Responsable: </b><?php echo $encabezado_consignacion['asesor']; ?>
		<b>Observaciones: </b><?php echo $encabezado_consignacion['observaciones']; ?>
	</div>

	<div class="panel panel-info" style="height: 400px; overflow-y: auto; margin-bottom: 5px;">
		<div class="table-responsive">
			<table class="table table-hover">
				<tr class="info">
					<th style="padding: 2px;">Código</th>
					<th style="padding: 2px;">Producto</th>
					<th style="padding: 2px;">Cant</th>
					<th style="padding: 2px;">Bodega</th>
					<th style="padding: 2px;">Lote</th>
					<th style="padding: 2px;">NUP</th>
					<th style="padding: 2px;">Caducidad</th>
				</tr>
				<?php
				$total_consignaciones = 0;
				while ($detalle = mysqli_fetch_array($busca_detalle)) {
					$codigo_producto = $detalle['codigo_producto'];
					$id_producto = $detalle['id_producto'];
					$nombre_producto = $detalle['nombre_producto'];
					$cantidad = $detalle['cant_consignacion'];
					$total_consignaciones += $cantidad;
					$bodega = $detalle['nombre_bodega'];
					$lote = $detalle['lote'];
					$nup = $detalle['nup'];
					$ncv = $detalle['numero_orden_entrada'];
					$busca_vencimiento = mysqli_query($con, "SELECT * FROM inventarios WHERE id_producto = '" . $id_producto . "' and lote= '" . $lote . "' and operacion='ENTRADA'");
					$row_vencimiento = mysqli_fetch_array($busca_vencimiento);
					$vencimiento = date('d-m-Y', strtotime($row_vencimiento['fecha_vencimiento']));
				?>
					<tr>
						<td style="padding: 2px;"><?php echo $codigo_producto; ?></td>
						<td style="padding: 2px;"><?php echo $nombre_producto; ?></td>
						<td style="padding: 2px;"><?php echo number_format($cantidad, 0, '.', '') ?></td>
						<td style="padding: 2px;"><?php echo $bodega; ?></td>
						<td style="padding: 2px;"><?php echo $lote; ?></td>
						<td style="padding: 2px;"><?php echo $nup; ?></td>
						<td style="padding: 2px;"><?php echo $vencimiento; ?></td>
					</tr>
				<?php
				}
				?>
				<tr>
					<td style="padding: 2px;" colspan="2" class="text-right">Total productos consignados:</td>
					<td style="padding: 2px;"><?php echo $total_consignaciones; ?></td>
					<td style="padding: 2px;" colspan="4"></td>
				</tr>
			</table>
		</div>
	</div>

<?php
} */


function detalle_consignacion($codigo_unico)
{
	$con         = conenta_login();
	$ruc_empresa = $_SESSION['ruc_empresa'];

	// 1) Encabezado (seguro)
	$sqlHead = "
        SELECT 
            ven.nombre              AS asesor,
            cli.nombre              AS cliente,
            enc.numero_consignacion AS numero_consignacion,
            enc.fecha_consignacion  AS fecha_consignacion,
            enc.punto_partida       AS punto_partida,
            enc.punto_llegada       AS punto_llegada,
            enc.observaciones       AS observaciones,
            enc.fecha_registro      AS fecha_registro
        FROM encabezado_consignacion enc
        INNER JOIN clientes   cli ON enc.id_cli_pro = cli.id
        INNER JOIN vendedores ven ON ven.id_vendedor = enc.responsable
        WHERE enc.codigo_unico = ? AND enc.ruc_empresa = ?
        LIMIT 1
    ";
	if (!($stH = mysqli_prepare($con, $sqlHead))) {
		echo "<div class='alert alert-danger'>No se pudo cargar la consignación.</div>";
		return;
	}
	mysqli_stmt_bind_param($stH, 'ss', $codigo_unico, $ruc_empresa);
	mysqli_stmt_execute($stH);
	$rsH  = mysqli_stmt_get_result($stH);
	$head = $rsH ? mysqli_fetch_assoc($rsH) : null;
	mysqli_stmt_close($stH);

	if (!$head) {
		echo "<div class='alert alert-warning'>No se encontró la consignación solicitada.</div>";
		return;
	}

	// 2) Detalle (sin N+1): vencimiento por última ENTRADA de (id_producto, lote)
	$sqlDet = "
        SELECT
            det.codigo_producto,
            det.id_producto,
            det.nombre_producto,
            det.cant_consignacion,
            bod.nombre_bodega     AS nombre_bodega,
            det.lote,
            det.nup,
            vto.fecha_vencimiento AS fecha_vencimiento
        FROM detalle_consignacion det
        INNER JOIN bodega bod      ON bod.id_bodega = det.id_bodega
        INNER JOIN unidad_medida um ON um.id_medida = det.id_medida
        LEFT JOIN (
            SELECT i.id_producto, i.lote, MAX(i.fecha_vencimiento) AS fecha_vencimiento
              FROM inventarios i
             WHERE i.operacion = 'ENTRADA'
             GROUP BY i.id_producto, i.lote
        ) vto ON vto.id_producto = det.id_producto AND vto.lote = det.lote
        WHERE det.codigo_unico = ? AND det.ruc_empresa = ?
        ORDER BY det.id_det_consignacion ASC
    ";
	$items = [];
	if ($stD = mysqli_prepare($con, $sqlDet)) {
		mysqli_stmt_bind_param($stD, 'ss', $codigo_unico, $ruc_empresa);
		mysqli_stmt_execute($stD);
		$rsD = mysqli_stmt_get_result($stD);
		while ($r = mysqli_fetch_assoc($rsD)) {
			$items[] = $r;
		}
		mysqli_stmt_close($stD);
	} else {
		echo "<div class='alert alert-danger'>No se pudo cargar el detalle.</div>";
		return;
	}

	// 3) Render encabezado
?>
	<div class="alert alert-info" style="padding:1px; margin-bottom:2px; margin-top:-10px;" role="alert">
		<b>No:</b> <?php echo htmlspecialchars($head['numero_consignacion'], ENT_QUOTES); ?>
		<b>Fecha:</b> <?php echo $head['fecha_consignacion'] ? date("d/m/Y", strtotime($head['fecha_consignacion'])) : ''; ?>
		<b>Hora:</b> <?php echo $head['fecha_registro'] ? date("H:i", strtotime($head['fecha_registro'])) : ''; ?>
		<b>Cliente:</b> <?php echo htmlspecialchars($head['cliente'], ENT_QUOTES); ?>
		<b>Punto salida:</b> <?php echo htmlspecialchars($head['punto_partida'], ENT_QUOTES); ?>
		<b>Punto llegada:</b> <?php echo htmlspecialchars($head['punto_llegada'], ENT_QUOTES); ?>
		<b>Responsable:</b> <?php echo htmlspecialchars($head['asesor'], ENT_QUOTES); ?>
		<b>Observaciones:</b> <?php echo htmlspecialchars($head['observaciones'], ENT_QUOTES); ?>
	</div>

	<div class="panel panel-info" style="height:400px; overflow-y:auto; margin-bottom:5px;">
		<div class="table-responsive">
			<table class="table table-hover">
				<tr class="info">
					<th style="padding:2px;">Código</th>
					<th style="padding:2px;">Producto</th>
					<th style="padding:2px;">Cant</th>
					<th style="padding:2px;">Bodega</th>
					<th style="padding:2px;">Lote</th>
					<th style="padding:2px;">NUP</th>
					<th style="padding:2px;">Caducidad</th>
				</tr>
				<?php
				$total_consignaciones = 0.0;
				if (empty($items)) {
					echo "<tr><td colspan='7' class='text-center' style='padding:6px;'>Sin ítems.</td></tr>";
				} else {
					foreach ($items as $det) {
						$cantidad      = (float)$det['cant_consignacion'];
						$total_consignaciones += $cantidad;

						$vto_raw = $det['fecha_vencimiento'];
						$vto     = $vto_raw ? date('d-m-Y', strtotime($vto_raw)) : '';
				?>
						<tr>
							<td style="padding:2px;"><?php echo htmlspecialchars($det['codigo_producto'], ENT_QUOTES); ?></td>
							<td style="padding:2px;"><?php echo htmlspecialchars($det['nombre_producto'], ENT_QUOTES); ?></td>
							<td style="padding:2px;"><?php echo number_format($cantidad, 0, '.', ''); ?></td>
							<td style="padding:2px;"><?php echo htmlspecialchars($det['nombre_bodega'], ENT_QUOTES); ?></td>
							<td style="padding:2px;"><?php echo htmlspecialchars($det['lote'], ENT_QUOTES); ?></td>
							<td style="padding:2px;"><?php echo htmlspecialchars($det['nup'], ENT_QUOTES); ?></td>
							<td style="padding:2px;"><?php echo htmlspecialchars($vto, ENT_QUOTES); ?></td>
						</tr>
				<?php
					}
				}
				?>
				<tr>
					<td style="padding:2px;" colspan="2" class="text-right">Total productos consignados:</td>
					<td style="padding:2px;"><?php echo number_format($total_consignaciones, 0, '.', ''); ?></td>
					<td style="padding:2px;" colspan="4"></td>
				</tr>
			</table>
		</div>
	</div>
<?php
}


/* function detalle_factura($codigo_unico)
{
	$con = conenta_login();
	$busca_encabezado = mysqli_query($con, "SELECT * FROM encabezado_consignacion as enc_con 
	INNER JOIN clientes as cli ON enc_con.id_cli_pro=cli.id
	LEFT JOIN vendedores as ve ON ve.id_vendedor=enc_con.responsable 
	WHERE enc_con.codigo_unico = '" . $codigo_unico . "' ");
	$encabezado_consignacion = mysqli_fetch_array($busca_encabezado);
	$busca_detalle = mysqli_query($con, "SELECT * FROM detalle_consignacion as det_con INNER JOIN bodega as bod ON det_con.id_bodega=bod.id_bodega INNER JOIN unidad_medida as uni_med ON det_con.id_medida=uni_med.id_medida WHERE det_con.codigo_unico = '" . $codigo_unico . "' ");
?>
	<div style="padding: 1px; margin-bottom: 2px; margin-top: -10px;" class="alert alert-info" role="alert">
		<b>No:</b> <?php echo $encabezado_consignacion['numero_consignacion']; ?> <b>Fecha:</b> <?php echo date("d/m/Y", strtotime($encabezado_consignacion['fecha_consignacion'])); ?> <b>Cliente: </b><?php echo $encabezado_consignacion['nombre']; ?>
		<b>Punto salida: </b><?php echo $encabezado_consignacion['punto_partida']; ?> <b>Punto llegada: </b><?php echo $encabezado_consignacion['punto_llegada']; ?> <b>Asesor: </b><?php echo $encabezado_consignacion['nombre']; ?>
		<b>Observaciones: </b><?php echo $encabezado_consignacion['observaciones']; ?>
	</div>
	<div class="panel panel-info" style="height: 400px; overflow-y: auto; margin-bottom: 5px;">
		<div class="table-responsive">
			<table class="table table-hover">
				<tr class="info">
					<th style="padding: 2px;">Código</th>
					<th style="padding: 2px;">Producto</th>
					<th style="padding: 2px;">Cant</th>
					<th style="padding: 2px;">Bodega</th>
					<th style="padding: 2px;">Lote</th>
					<th style="padding: 2px;">NUP</th>
					<th style="padding: 2px;">Caducidad</th>
					<th style="padding: 2px;">CV</th>
				</tr>
				<?php
				while ($detalle = mysqli_fetch_array($busca_detalle)) {
					$codigo_producto = $detalle['codigo_producto'];
					$id_producto = $detalle['id_producto'];
					$nombre_producto = $detalle['nombre_producto'];
					$cantidad = $detalle['cant_consignacion'];
					$bodega = $detalle['nombre_bodega'];
					$lote = $detalle['lote'];
					$nup = $detalle['nup'];
					$ncv = $detalle['numero_orden_entrada'];
					$busca_vencimiento = mysqli_query($con, "SELECT * FROM inventarios WHERE id_producto = '" . $id_producto . "' and lote= '" . $lote . "' and operacion='ENTRADA'");
					$row_vencimiento = mysqli_fetch_array($busca_vencimiento);
					$vencimiento = date('d-m-Y', strtotime($row_vencimiento['fecha_vencimiento']));
				?>
					<tr>
						<td style="padding: 2px;"><?php echo $codigo_producto; ?></td>
						<td style="padding: 2px;"><?php echo $nombre_producto; ?></td>
						<td style="padding: 2px;"><?php echo number_format($cantidad, 4, '.', '') ?></td>
						<td style="padding: 2px;"><?php echo $bodega; ?></td>
						<td style="padding: 2px;"><?php echo $lote; ?></td>
						<td style="padding: 2px;"><?php echo $nup; ?></td>
						<td style="padding: 2px;"><?php echo $vencimiento; ?></td>
						<td style="padding: 2px;"><?php echo $ncv; ?></td>
					</tr>
				<?php
				}
				?>
			</table>
		</div>
	</div>
<?php
} */

function detalle_factura($codigo_unico)
{
	$con         = conenta_login();
	$ruc_empresa = $_SESSION['ruc_empresa'];

	// 1) Encabezado seguro (con alias claros)
	$sqlHead = "
        SELECT 
            enc.numero_consignacion   AS numero_consignacion,
            enc.fecha_consignacion    AS fecha_consignacion,
            enc.punto_partida         AS punto_partida,
            enc.punto_llegada         AS punto_llegada,
            enc.observaciones         AS observaciones,
            cli.nombre                AS cliente_nombre,
            ve.nombre                 AS asesor_nombre
        FROM encabezado_consignacion enc
        INNER JOIN clientes   cli ON cli.id = enc.id_cli_pro
        LEFT  JOIN vendedores ve  ON ve.id_vendedor = enc.responsable
        WHERE enc.codigo_unico = ? AND enc.ruc_empresa = ?
        LIMIT 1";
	if (!($stH = mysqli_prepare($con, $sqlHead))) {
		echo "<div class='alert alert-danger'>No se pudo cargar la consignación.</div>";
		return;
	}
	mysqli_stmt_bind_param($stH, 'ss', $codigo_unico, $ruc_empresa);
	mysqli_stmt_execute($stH);
	$rsH  = mysqli_stmt_get_result($stH);
	$head = $rsH ? mysqli_fetch_assoc($rsH) : null;
	mysqli_stmt_close($stH);

	if (!$head) {
		echo "<div class='alert alert-warning'>No se encontró la consignación solicitada.</div>";
		return;
	}

	// 2) Detalle con vencimiento (LEFT JOIN a subquery de inventarios ENTRADA)
	$sqlDet = "
        SELECT
            det.codigo_producto,
            det.id_producto,
            det.nombre_producto,
            det.cant_consignacion,
            bod.nombre_bodega       AS nombre_bodega,
            det.lote,
            det.nup,
            det.numero_orden_entrada,
            vto.fecha_vencimiento   AS fecha_vencimiento
        FROM detalle_consignacion det
        INNER JOIN bodega bod ON bod.id_bodega = det.id_bodega
        INNER JOIN unidad_medida um ON um.id_medida = det.id_medida
        LEFT JOIN (
            SELECT id_producto, lote, MAX(fecha_vencimiento) AS fecha_vencimiento
            FROM inventarios
            WHERE operacion = 'ENTRADA'
            GROUP BY id_producto, lote
        ) vto ON vto.id_producto = det.id_producto AND vto.lote = det.lote
        WHERE det.codigo_unico = ? AND det.ruc_empresa = ?
        ORDER BY det.id_det_consignacion ASC";
	$items = [];
	if ($stD = mysqli_prepare($con, $sqlDet)) {
		mysqli_stmt_bind_param($stD, 'ss', $codigo_unico, $ruc_empresa);
		mysqli_stmt_execute($stD);
		$rsD = mysqli_stmt_get_result($stD);
		while ($r = mysqli_fetch_assoc($rsD)) {
			$items[] = $r;
		}
		mysqli_stmt_close($stD);
	} else {
		echo "<div class='alert alert-danger'>No se pudo cargar el detalle.</div>";
		return;
	}

	// 3) Render encabezado
?>
	<div class="alert alert-info" style="padding:1px; margin-bottom:2px; margin-top:-10px;" role="alert">
		<b>No:</b> <?php echo htmlspecialchars($head['numero_consignacion'], ENT_QUOTES); ?>
		<b>Fecha:</b> <?php echo $head['fecha_consignacion'] ? date("d/m/Y", strtotime($head['fecha_consignacion'])) : ''; ?>
		<b>Cliente:</b> <?php echo htmlspecialchars($head['cliente_nombre'], ENT_QUOTES); ?>
		<b>Punto salida:</b> <?php echo htmlspecialchars($head['punto_partida'], ENT_QUOTES); ?>
		<b>Punto llegada:</b> <?php echo htmlspecialchars($head['punto_llegada'], ENT_QUOTES); ?>
		<b>Asesor:</b> <?php echo htmlspecialchars($head['asesor_nombre'], ENT_QUOTES); ?>
		<b>Observaciones:</b> <?php echo htmlspecialchars($head['observaciones'], ENT_QUOTES); ?>
	</div>

	<div class="panel panel-info" style="height:400px; overflow-y:auto; margin-bottom:5px;">
		<div class="table-responsive">
			<table class="table table-hover">
				<tr class="info">
					<th style="padding:2px;">Código</th>
					<th style="padding:2px;">Producto</th>
					<th style="padding:2px;">Cant</th>
					<th style="padding:2px;">Bodega</th>
					<th style="padding:2px;">Lote</th>
					<th style="padding:2px;">NUP</th>
					<th style="padding:2px;">Caducidad</th>
					<th style="padding:2px;">CV</th>
				</tr>
				<?php
				if (empty($items)) {
					echo "<tr><td colspan='8' class='text-center' style='padding:6px;'>Sin ítems.</td></tr>";
				} else {
					foreach ($items as $det) {
						$cant = (float)$det['cant_consignacion'];
						$vto  = $det['fecha_vencimiento'] ? date('d-m-Y', strtotime($det['fecha_vencimiento'])) : '';
				?>
						<tr>
							<td style="padding:2px;"><?php echo htmlspecialchars($det['codigo_producto'], ENT_QUOTES); ?></td>
							<td style="padding:2px;"><?php echo htmlspecialchars($det['nombre_producto'], ENT_QUOTES); ?></td>
							<td style="padding:2px;"><?php echo number_format($cant, 4, '.', ''); ?></td>
							<td style="padding:2px;"><?php echo htmlspecialchars($det['nombre_bodega'], ENT_QUOTES); ?></td>
							<td style="padding:2px;"><?php echo htmlspecialchars($det['lote'], ENT_QUOTES); ?></td>
							<td style="padding:2px;"><?php echo htmlspecialchars($det['nup'], ENT_QUOTES); ?></td>
							<td style="padding:2px;"><?php echo htmlspecialchars($vto, ENT_QUOTES); ?></td>
							<td style="padding:2px;"><?php echo htmlspecialchars($det['numero_orden_entrada'], ENT_QUOTES); ?></td>
						</tr>
				<?php
					}
				}
				?>
			</table>
		</div>
	</div>
<?php
}

/* 
function detalle_devolucion_consignacion($codigo_unico)
{
	$con = conenta_login();
	$busca_encabezado = mysqli_query($con, "SELECT * FROM encabezado_consignacion as enc_con 
	INNER JOIN clientes as cli ON enc_con.id_cli_pro=cli.id WHERE enc_con.codigo_unico = '" . $codigo_unico . "' ");
	$encabezado_consignacion = mysqli_fetch_array($busca_encabezado);

	$busca_detalle = mysqli_query($con, "SELECT * FROM detalle_consignacion as det_con 
	INNER JOIN bodega as bod ON det_con.id_bodega=bod.id_bodega 
	WHERE det_con.codigo_unico = '" . $codigo_unico . "' ");
?>
	<div style="padding: 1px; margin-bottom: 2px; margin-top: -10px;" class="alert alert-info" role="alert">
		<b>No:</b> <?php echo $encabezado_consignacion['numero_consignacion']; ?> <b>Fecha:</b> <?php echo date("d/m/Y", strtotime($encabezado_consignacion['fecha_consignacion'])); ?> <b>Cliente: </b><?php echo $encabezado_consignacion['nombre']; ?>
		<b>Tipo: </b><?php echo 'Retorno' ?> <b>Observaciones: </b><?php echo $encabezado_consignacion['observaciones']; ?>
	</div>

	<div class="panel panel-info" style="height: 400px; overflow-y: auto; margin-bottom: 5px;">
		<div class="table-responsive">
			<table class="table table-hover">
				<tr class="info">
					<th style="padding: 2px;">No. CV</th>
					<th style="padding: 2px;">Código</th>
					<th style="padding: 2px;">Producto</th>
					<th style="padding: 2px;">Cant</th>
					<th style="padding: 2px;">Bodega</th>
					<th style="padding: 2px;">Lote</th>
					<th style="padding: 2px;">nup</th>
					<th style="padding: 2px;">Caducidad</th>
				</tr>
				<?php
				while ($detalle = mysqli_fetch_array($busca_detalle)) {
					$codigo_producto = $detalle['codigo_producto'];
					$id_producto = $detalle['id_producto'];
					$nombre_producto = $detalle['nombre_producto'];
					$cantidad = $detalle['cant_consignacion'];
					$bodega = $detalle['nombre_bodega'];
					$lote = $detalle['lote'];
					$nup = $detalle['nup'];
					$busca_vencimiento = mysqli_query($con, "SELECT * FROM inventarios WHERE id_producto = '" . $id_producto . "' and lote= '" . $lote . "' and operacion='ENTRADA'");
					$row_vencimiento = mysqli_fetch_array($busca_vencimiento);
					$vencimiento = date('d-m-Y', strtotime($row_vencimiento['fecha_vencimiento']));
					$numero_orden_entrada = $detalle['numero_orden_entrada'];
				?>
					<tr>
						<td style="padding: 2px;"><?php echo $numero_orden_entrada; ?></td>
						<td style="padding: 2px;"><?php echo $codigo_producto; ?></td>
						<td style="padding: 2px;"><?php echo strtoupper($nombre_producto); ?></td>
						<td style="padding: 2px;"><?php echo number_format($cantidad, 4, '.', '') ?></td>
						<td style="padding: 2px;"><?php echo strtoupper($bodega); ?></td>
						<td style="padding: 2px;"><?php echo $lote; ?></td>
						<td style="padding: 2px;"><?php echo $nup; ?></td>
						<td style="padding: 2px;"><?php echo $vencimiento; ?></td>

					</tr>
				<?php
				}
				?>
			</table>
		</div>
	</div>
<?php
} */


function detalle_devolucion_consignacion($codigo_unico)
{
	$con         = conenta_login();
	$ruc_empresa = $_SESSION['ruc_empresa'];

	// 1) Encabezado (seguro)
	$sqlHead = "
        SELECT 
            enc.numero_consignacion   AS numero_consignacion,
            enc.fecha_consignacion    AS fecha_consignacion,
            enc.observaciones         AS observaciones,
            cli.nombre                AS cliente
        FROM encabezado_consignacion enc
        INNER JOIN clientes cli ON cli.id = enc.id_cli_pro
        WHERE enc.codigo_unico = ? AND enc.ruc_empresa = ?
        LIMIT 1
    ";
	if (!($stH = mysqli_prepare($con, $sqlHead))) {
		echo "<div class='alert alert-danger'>No se pudo cargar la devolución.</div>";
		return;
	}
	mysqli_stmt_bind_param($stH, 'ss', $codigo_unico, $ruc_empresa);
	mysqli_stmt_execute($stH);
	$rsH  = mysqli_stmt_get_result($stH);
	$head = $rsH ? mysqli_fetch_assoc($rsH) : null;
	mysqli_stmt_close($stH);

	if (!$head) {
		echo "<div class='alert alert-warning' role='alert'>No se encontró la consignación solicitada.</div>";
		return;
	}

	// 2) Detalle (sin N+1) con vencimiento por última ENTRADA
	$sqlDet = "
        SELECT
            det.codigo_producto,
            det.id_producto,
            det.nombre_producto,
            det.cant_consignacion,
            bod.nombre_bodega            AS nombre_bodega,
            det.lote,
            det.nup,
            det.numero_orden_entrada,
            vto.fecha_vencimiento        AS fecha_vencimiento
        FROM detalle_consignacion det
        INNER JOIN bodega bod ON bod.id_bodega = det.id_bodega
        LEFT JOIN (
            SELECT i.id_producto, i.lote, MAX(i.fecha_vencimiento) AS fecha_vencimiento
              FROM inventarios i
             WHERE i.operacion = 'ENTRADA'
             GROUP BY i.id_producto, i.lote
        ) vto ON vto.id_producto = det.id_producto AND vto.lote = det.lote
        WHERE det.codigo_unico = ? AND det.ruc_empresa = ?
        ORDER BY det.id_det_consignacion ASC
    ";
	$items = [];
	if ($stD = mysqli_prepare($con, $sqlDet)) {
		mysqli_stmt_bind_param($stD, 'ss', $codigo_unico, $ruc_empresa);
		mysqli_stmt_execute($stD);
		$rsD = mysqli_stmt_get_result($stD);
		while ($r = mysqli_fetch_assoc($rsD)) {
			$items[] = $r;
		}
		mysqli_stmt_close($stD);
	} else {
		echo "<div class='alert alert-danger'>No se pudo cargar el detalle.</div>";
		return;
	}

	// 3) Render
?>
	<div class="alert alert-info" style="padding:1px; margin-bottom:2px; margin-top:-10px;" role="alert">
		<b>No:</b> <?php echo htmlspecialchars($head['numero_consignacion'], ENT_QUOTES); ?>
		<b>Fecha:</b> <?php echo $head['fecha_consignacion'] ? date("d/m/Y", strtotime($head['fecha_consignacion'])) : ''; ?>
		<b>Cliente: </b><?php echo htmlspecialchars($head['cliente'], ENT_QUOTES); ?>
		<b>Tipo: </b><?php echo 'Retorno'; ?>
		<b>Observaciones: </b><?php echo htmlspecialchars($head['observaciones'], ENT_QUOTES); ?>
	</div>

	<div class="panel panel-info" style="height:400px; overflow-y:auto; margin-bottom:5px;">
		<div class="table-responsive">
			<table class="table table-hover">
				<tr class="info">
					<th style="padding:2px;">No. CV</th>
					<th style="padding:2px;">Código</th>
					<th style="padding:2px;">Producto</th>
					<th style="padding:2px;">Cant</th>
					<th style="padding:2px;">Bodega</th>
					<th style="padding:2px;">Lote</th>
					<th style="padding:2px;">NUP</th>
					<th style="padding:2px;">Caducidad</th>
				</tr>
				<?php
				if (empty($items)) {
					echo "<tr><td colspan='8' class='text-center' style='padding:6px;'>Sin ítems.</td></tr>";
				} else {
					foreach ($items as $det) {
						$cant = (float)$det['cant_consignacion'];
						$vto  = $det['fecha_vencimiento'] ? date('d-m-Y', strtotime($det['fecha_vencimiento'])) : '';
				?>
						<tr>
							<td style="padding:2px;"><?php echo htmlspecialchars($det['numero_orden_entrada'], ENT_QUOTES); ?></td>
							<td style="padding:2px;"><?php echo htmlspecialchars($det['codigo_producto'], ENT_QUOTES); ?></td>
							<td style="padding:2px;"><?php echo htmlspecialchars(strtoupper($det['nombre_producto']), ENT_QUOTES); ?></td>
							<td style="padding:2px;"><?php echo number_format($cant, 4, '.', ''); ?></td>
							<td style="padding:2px;"><?php echo htmlspecialchars(strtoupper($det['nombre_bodega']), ENT_QUOTES); ?></td>
							<td style="padding:2px;"><?php echo htmlspecialchars($det['lote'], ENT_QUOTES); ?></td>
							<td style="padding:2px;"><?php echo htmlspecialchars($det['nup'], ENT_QUOTES); ?></td>
							<td style="padding:2px;"><?php echo htmlspecialchars($vto, ENT_QUOTES); ?></td>
						</tr>
				<?php
					}
				}
				?>
			</table>
		</div>
	</div>
<?php
}


?>