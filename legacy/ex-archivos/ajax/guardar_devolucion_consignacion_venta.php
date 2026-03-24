<?php
//include("../validadores/generador_codigo_unico.php");
/* Connect To Database*/
include("../conexiones/conectalogin.php");
include("../helpers/helpers.php");
ini_set('date.timezone', 'America/Guayaquil');
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
if (empty($_POST['fecha_devolucion_consignacion_venta'])) {
	echo "<script>$.notify('Ingrese fecha.','error');
				</script>";
} else if (!date($_POST['fecha_devolucion_consignacion_venta'])) {
	echo "<script>$.notify('Ingrese una fecha correcta.','error');
				</script>";
} else if (empty($_POST['numero_cv'])) {
	echo "<script>$.notify('Ingrese un número de consignación.','info');
				</script>";
} else if ((!empty($_POST['fecha_devolucion_consignacion_venta'])) && (!empty($_POST['numero_cv']))) {

	$fecha_devolucion_consignacion = date('Y-m-d H:i:s', strtotime(mysqli_real_escape_string($con, (strip_tags($_POST["fecha_devolucion_consignacion_venta"], ENT_QUOTES)))));
	$numero_cv = mysqli_real_escape_string($con, (strip_tags($_POST["numero_cv"], ENT_QUOTES)));
	$opcion_salida = 'DEVOLUCIÓN';
	$observacion_consignacion_venta = mysqli_real_escape_string($con, (strip_tags($_POST["observacion_devolucion_consignacion_venta"], ENT_QUOTES)));
	$serie_sucursal = mysqli_real_escape_string($con, (strip_tags($_POST["serie_devolucion_consignacion"], ENT_QUOTES)));
	$fecha_registro = date("Y-m-d H:i:s");
	$codigo_unico = codigo_aleatorio(19) . "2"; // 2 es para guardar devolucion de consignacion

	$registros = isset($_POST["registros"]) ? $_POST["registros"] : "";

	//buscar cliente
	$busca_cliente = mysqli_query($con, "SELECT * FROM encabezado_consignacion WHERE numero_consignacion = '" . $numero_cv . "' and ruc_empresa='" . $ruc_empresa . "' ");
	$row_cliente = mysqli_fetch_array($busca_cliente);
	$id_cliente = $row_cliente['id_cli_pro'];
	$asesor = $row_cliente['responsable'];
	//buscar si trabaja con inventario
	$trabaja_inventario = mysqli_query($con, "SELECT * FROM configuracion_facturacion WHERE ruc_empresa = '" . $ruc_empresa . "' and serie_sucursal='" . $serie_sucursal . "'");
	$row_inventario = mysqli_fetch_array($trabaja_inventario);
	$inventario = $row_inventario['inventario'];

	$consulta_ultima_orden = mysqli_query($con, "SELECT max(numero_consignacion) as ultimo FROM encabezado_consignacion WHERE ruc_empresa='" . $ruc_empresa . "' and tipo_consignacion='VENTA' and operacion ='DEVOLUCIÓN'");
	$row_ultimo = mysqli_fetch_array($consulta_ultima_orden);
	$siguiente_orden = $row_ultimo['ultimo'] + 1;

	if (!empty($registros)) {
		$id_productos = $_POST["id_producto"];
		$codigo_productos = $_POST["codigo_producto"];
		$nombre_productos = $_POST["nombre_producto"];
		$nups = $_POST["nup"];
		$lotes = $_POST["lote"];
		$medidas = $_POST["medida"];
		$bodegas = $_POST["bodega"];
		$vencimientos = $_POST["vencimiento"];
		$cantidades = $_POST["devolucion"];
		if (array_sum($cantidades) > 0) {
			$encabezado_consignacion = mysqli_query($con, "INSERT INTO encabezado_consignacion VALUES (null,'" . $fecha_devolucion_consignacion . "','" . $ruc_empresa . "','" . $codigo_unico . "','" . $id_cliente . "','VENTA','" . $siguiente_orden . "','" . $observacion_consignacion_venta . "','" . $fecha_registro . "','" . $id_usuario . "','','','" . $asesor . "', '" . $opcion_salida . "','" . $serie_sucursal . "','','0','0','0','0','1')");
			foreach ($registros as $valor) {
				$cantidad = $cantidades[$valor];
				$id_producto = $id_productos[$valor];
				$codigo_producto = $codigo_productos[$valor];
				$nombre_producto = $nombre_productos[$valor];
				$nup = $nups[$valor];
				$lote = $lotes[$valor];
				$medida = $medidas[$valor];
				$bodega = $bodegas[$valor];
				$vencimiento = $vencimientos[$valor];
				if ($cantidad > 0) {
					$detalle_consignacion = mysqli_query($con, "INSERT INTO detalle_consignacion 
					VALUES (null,'" . $id_producto . "', '" . $codigo_producto . "', 
					'" . $nombre_producto . "','" . $lote . "','" . $vencimiento . "',
					'" . $bodega . "','" . $medida . "','" . $ruc_empresa . "',
					'" . $codigo_unico . "','" . $cantidad . "','" . $numero_cv . "',
					'" . $nup . "','0','0')");
				}
			}
			echo "<script>$.notify('Retorno registrado.','success');
			setTimeout(function (){location.reload()}, 1000);
							</script>";
		} else {
			echo "<script>$.notify('No hay cantidades agregadas para guardar.','error');
			mostrar_consignacion_venta();
			</script>";
		}
	} else {
		echo "<script>$.notify('No hay registros para guardar.','error');
				</script>";
	}
} else {
	echo "<script>$.notify('intente de nuevo.','error');
				</script>";
}


function regresar_inventario($con, $ruc_empresa, $codigo_unico, $id_usuario, $siguiente_orden)
{

	$detalle_consignacion = "Retorno consignación ventas N " . $siguiente_orden;
	ini_set('date.timezone', 'America/Guayaquil');
	$fecha_registro = date("Y-m-d H:i:s");
	$sql_regresa_inventario = mysqli_query($con, "SELECT * FROM detalle_consignacion WHERE codigo_unico = '" . $codigo_unico . "' and ruc_empresa='" . $ruc_empresa . "'");

	while ($row_detalle = mysqli_fetch_array($sql_regresa_inventario)) {
		$id_producto = $row_detalle['id_producto'];
		$codigo_producto = $row_detalle['codigo_producto'];
		$nombre_producto = $row_detalle['nombre_producto'];
		$cantidad = $row_detalle['cant_consignacion'];
		$id_bodega = $row_detalle['id_bodega'];
		$id_medida = $row_detalle['id_medida'];
		$lote = $row_detalle['lote'];
		$vencimiento = $row_detalle['vencimiento'];
		$numero_consignacion = $row_detalle['numero_orden_entrada'];

		$sql_producto = mysqli_query($con, "SELECT * FROM productos_servicios WHERE id = '" . $id_producto . "'");
		$row_productos = mysqli_fetch_array($sql_producto);
		$precio = $row_productos['precio_producto'];

		$guarda_entrada_inventario = mysqli_query($con, "INSERT INTO inventarios VALUES (null, '" . $ruc_empresa . "','" . $id_producto . "','" . $precio . "','" . $cantidad . "','0','" . $fecha_registro . "','" . $vencimiento . "','" . $detalle_consignacion . "','" . $id_usuario . "','" . $id_medida . "','" . $fecha_registro . "','A','" . $id_bodega . "','ENTRADA','" . $codigo_producto . "','" . $nombre_producto . "','0','OK','" . $lote . "','" . $codigo_unico . "')");
	}


	if ($guarda_entrada_inventario) {
		echo "<script>
		$.notify('Retorno registrado en inventario.','success');
		</script>";
	} else {
		echo "<script>
		$.notify('Lo siento el retorno no se registro en el inventario.','error');
		</script>";
	}
}
