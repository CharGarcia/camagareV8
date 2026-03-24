<?php
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
include("../conexiones/conectalogin.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
//para buscar el saldo de un producto en base a una orden de consignacion
if ($action == 'saldo_consignacion_venta') {
	$numero_consignacion = $_POST["numero_consignacion"];
	$id_producto = $_POST["id_producto"];
	$sql_entradas = mysqli_query($con, "SELECT sum(det_con.cant_consignacion) as entradas FROM encabezado_consignacion as enc_con INNER JOIN detalle_consignacion as det_con ON enc_con.codigo_unico=det_con.codigo_unico WHERE enc_con.ruc_empresa = '" . $ruc_empresa . "' and enc_con.numero_consignacion = '" . $numero_consignacion . "' and det_con.id_producto='" . $id_producto . "' and enc_con.operacion='ENTRADA' and enc_con.tipo_consignacion='VENTA' ");
	$row_entradas = mysqli_fetch_array($sql_entradas);
	$total_entradas = $row_entradas['entradas'];

	$sql_facturadas = mysqli_query($con, "SELECT sum(det_con.cant_consignacion) as facturadas FROM encabezado_consignacion as enc_con INNER JOIN detalle_consignacion as det_con ON enc_con.codigo_unico=det_con.codigo_unico WHERE enc_con.ruc_empresa = '" . $ruc_empresa . "' and det_con.id_producto='" . $id_producto . "' and enc_con.operacion='FACTURA' and enc_con.tipo_consignacion='VENTA' and det_con.numero_orden_entrada='" . $numero_consignacion . "' ");
	$row_facturadas = mysqli_fetch_array($sql_facturadas);
	$total_facturadas = $row_facturadas['facturadas'];

	$sql_devueltas = mysqli_query($con, "SELECT sum(det_con.cant_consignacion) as devueltas FROM encabezado_consignacion as enc_con INNER JOIN detalle_consignacion as det_con ON enc_con.codigo_unico=det_con.codigo_unico WHERE enc_con.ruc_empresa = '" . $ruc_empresa . "' and det_con.id_producto='" . $id_producto . "' and enc_con.operacion='DEVOLUCIÓN' and enc_con.tipo_consignacion='VENTA' and det_con.numero_orden_entrada='" . $numero_consignacion . "'");
	$row_devueltas = mysqli_fetch_array($sql_devueltas);
	$total_devueltas = $row_devueltas['devueltas'];

	//saldo dela  factura temporal
	$busca_saldo_tmp = mysqli_query($con, "SELECT sum(cantidad_tmp) as suma FROM factura_tmp WHERE id_usuario = '" . $id_usuario . "' and ruc_empresa = '" . $ruc_empresa . "' and id_producto = '" . $id_producto . "' and tarifa_iva='" . $numero_consignacion . "'");
	$saldo_producto_tmp = mysqli_fetch_array($busca_saldo_tmp);
	$cantidad_tmp = $saldo_producto_tmp['suma'];
	echo $total_entradas - $total_facturadas - $total_devueltas - $cantidad_tmp;
}

//lotes
if ($action == 'consignacion_venta_lotes') {
	$id_producto = $_POST["id_producto"];
	$numero_consignacion = $_POST["numero_consignacion"];

	$sql_lotes = mysqli_query($con, "SELECT det_con.lote as lote FROM encabezado_consignacion as enc_con INNER JOIN detalle_consignacion as det_con ON enc_con.codigo_unico=det_con.codigo_unico WHERE enc_con.ruc_empresa = '" . $ruc_empresa . "' and enc_con.numero_consignacion = '" . $numero_consignacion . "' and det_con.id_producto='" . $id_producto . "' and enc_con.operacion='ENTRADA' and enc_con.tipo_consignacion='VENTA' ");
?>
	<option value="0" selected>Seleccione</option>
	<?php
	while ($row_lotes = mysqli_fetch_array($sql_lotes)) {
	?>
		<option value="<?php echo $row_lotes['lote']; ?>"><?php echo $row_lotes['lote']; ?></option>
	<?php
	}
}

//para mostrar los numeros de codigos unicos de productos
if ($action == 'consignacion_venta_cup') {
	$id_producto = $_POST["id_producto"];
	$lote = $_POST["lote"];
	$numero_consignacion = $_POST["numero_consignacion"];
	$sql_nup = mysqli_query($con, "SELECT det_con.nup as nup FROM encabezado_consignacion as enc_con INNER JOIN detalle_consignacion as det_con ON enc_con.codigo_unico=det_con.codigo_unico WHERE enc_con.ruc_empresa = '" . $ruc_empresa . "' and enc_con.numero_consignacion = '" . $numero_consignacion . "' and det_con.id_producto='" . $id_producto . "' and enc_con.operacion='ENTRADA' and enc_con.tipo_consignacion='VENTA' and det_con.lote='" . $lote . "' ");
	?>
	<option value="0" selected>Seleccione</option>
	<?php
	while ($row_nup = mysqli_fetch_array($sql_nup)) {
	?>
		<option value="<?php echo $row_nup['nup']; ?>"><?php echo $row_nup['nup']; ?></option>
	<?php
	}
}

//saldo de los lotes
if ($action == 'saldo_consignacion_venta_lote') {
	$id_producto = $_POST["id_producto"];
	$numero_consignacion = $_POST["numero_consignacion"];
	$lote = $_POST["lote"];

	$sql_entradas = mysqli_query($con, "SELECT sum(det_con.cant_consignacion) as entradas FROM encabezado_consignacion as enc_con INNER JOIN detalle_consignacion as det_con ON enc_con.codigo_unico=det_con.codigo_unico WHERE enc_con.ruc_empresa = '" . $ruc_empresa . "' and enc_con.numero_consignacion = '" . $numero_consignacion . "' and det_con.id_producto='" . $id_producto . "' and enc_con.operacion='ENTRADA' and det_con.lote='" . $lote . "' and enc_con.tipo_consignacion='VENTA' ");
	$row_entradas = mysqli_fetch_array($sql_entradas);
	$total_entradas = $row_entradas['entradas'];

	$sql_facturadas = mysqli_query($con, "SELECT sum(det_con.cant_consignacion) as facturadas FROM encabezado_consignacion as enc_con INNER JOIN detalle_consignacion as det_con ON enc_con.codigo_unico=det_con.codigo_unico WHERE enc_con.ruc_empresa = '" . $ruc_empresa . "' and det_con.id_producto='" . $id_producto . "' and enc_con.operacion='FACTURA' and det_con.lote='" . $lote . "' and enc_con.tipo_consignacion='VENTA' and det_con.numero_orden_entrada='" . $numero_consignacion . "'");
	$row_facturadas = mysqli_fetch_array($sql_facturadas);
	$total_facturadas = $row_facturadas['facturadas'];

	$sql_devueltas = mysqli_query($con, "SELECT sum(det_con.cant_consignacion) as devueltas FROM encabezado_consignacion as enc_con INNER JOIN detalle_consignacion as det_con ON enc_con.codigo_unico=det_con.codigo_unico WHERE enc_con.ruc_empresa = '" . $ruc_empresa . "' and det_con.id_producto='" . $id_producto . "' and enc_con.operacion='DEVOLUCIÓN' and det_con.lote='" . $lote . "' and enc_con.tipo_consignacion='VENTA' and det_con.numero_orden_entrada='" . $numero_consignacion . "'");
	$row_devueltas = mysqli_fetch_array($sql_devueltas);
	$total_devueltas = $row_devueltas['devueltas'];

	//saldo dela  factura temporal
	$busca_saldo_tmp = mysqli_query($con, "SELECT sum(cantidad_tmp) as suma FROM factura_tmp WHERE id_usuario = '" . $id_usuario . "' and ruc_empresa = '" . $ruc_empresa . "' and id_producto = '" . $id_producto . "' and tarifa_iva='" . $numero_consignacion . "' and lote='" . $lote . "'");
	$saldo_producto_tmp = mysqli_fetch_array($busca_saldo_tmp);
	$cantidad_tmp = $saldo_producto_tmp['suma'];

	echo $total_entradas - $total_facturadas - $total_devueltas - $cantidad_tmp;
}

//saldo segun seleccion de cup
if ($action == 'saldo_consignacion_venta_cup') {
	$id_producto = $_POST["id_producto"];
	$numero_consignacion = $_POST["numero_consignacion"];
	$cup = $_POST["cup"];

	$sql_entradas = mysqli_query($con, "SELECT sum(det_con.cant_consignacion) as entradas FROM encabezado_consignacion as enc_con INNER JOIN detalle_consignacion as det_con ON enc_con.codigo_unico=det_con.codigo_unico WHERE enc_con.ruc_empresa = '" . $ruc_empresa . "' and enc_con.numero_consignacion = '" . $numero_consignacion . "' and det_con.id_producto='" . $id_producto . "' and enc_con.operacion='ENTRADA' and det_con.nup='" . $cup . "' and enc_con.tipo_consignacion='VENTA' ");
	$row_entradas = mysqli_fetch_array($sql_entradas);
	$total_entradas = $row_entradas['entradas'];

	$sql_facturadas = mysqli_query($con, "SELECT sum(det_con.cant_consignacion) as facturadas FROM encabezado_consignacion as enc_con INNER JOIN detalle_consignacion as det_con ON enc_con.codigo_unico=det_con.codigo_unico WHERE enc_con.ruc_empresa = '" . $ruc_empresa . "' and det_con.id_producto='" . $id_producto . "' and enc_con.operacion='FACTURA' and det_con.nup='" . $cup . "' and enc_con.tipo_consignacion='VENTA' and det_con.numero_orden_entrada='" . $numero_consignacion . "'");
	$row_facturadas = mysqli_fetch_array($sql_facturadas);
	$total_facturadas = $row_facturadas['facturadas'];

	$sql_devueltas = mysqli_query($con, "SELECT sum(det_con.cant_consignacion) as devueltas FROM encabezado_consignacion as enc_con INNER JOIN detalle_consignacion as det_con ON enc_con.codigo_unico=det_con.codigo_unico WHERE enc_con.ruc_empresa = '" . $ruc_empresa . "' and det_con.id_producto='" . $id_producto . "' and enc_con.operacion='DEVOLUCIÓN' and det_con.nup='" . $cup . "' and enc_con.tipo_consignacion='VENTA' and det_con.numero_orden_entrada='" . $numero_consignacion . "'");
	$row_devueltas = mysqli_fetch_array($sql_devueltas);
	$total_devueltas = $row_devueltas['devueltas'];

	//saldo dela  factura temporal
	$busca_saldo_tmp = mysqli_query($con, "SELECT sum(cantidad_tmp) as suma FROM factura_tmp WHERE id_usuario = '" . $id_usuario . "' and ruc_empresa = '" . $ruc_empresa . "' and id_producto = '" . $id_producto . "' and tarifa_iva='" . $numero_consignacion . "' and lote='" . $lote . "'");
	$saldo_producto_tmp = mysqli_fetch_array($busca_saldo_tmp);
	$cantidad_tmp = $saldo_producto_tmp['suma'];

	echo $total_entradas - $total_facturadas - $total_devueltas - $cantidad_tmp;
}

//saldo de los vencimientos
if ($action == 'saldo_consignacion_venta_vencimiento') {
	$id_producto = $_POST["id_producto"];
	$numero_consignacion = $_POST["numero_consignacion"];
	$vencimiento = $_POST["vencimiento"];

	$sql_entradas = mysqli_query($con, "SELECT sum(det_con.cant_consignacion) as entradas FROM encabezado_consignacion as enc_con INNER JOIN detalle_consignacion as det_con ON enc_con.codigo_unico=det_con.codigo_unico WHERE enc_con.ruc_empresa = '" . $ruc_empresa . "' and enc_con.numero_consignacion = '" . $numero_consignacion . "' and det_con.id_producto='" . $id_producto . "' and enc_con.operacion='ENTRADA' and det_con.vencimiento='" . $vencimiento . "' and enc_con.tipo_consignacion='VENTA' ");
	$row_entradas = mysqli_fetch_array($sql_entradas);
	$total_entradas = $row_entradas['entradas'];

	$sql_facturadas = mysqli_query($con, "SELECT sum(det_con.cant_consignacion) as facturadas FROM encabezado_consignacion as enc_con INNER JOIN detalle_consignacion as det_con ON enc_con.codigo_unico=det_con.codigo_unico WHERE enc_con.ruc_empresa = '" . $ruc_empresa . "' and det_con.id_producto='" . $id_producto . "' and enc_con.operacion='FACTURA' and det_con.vencimiento='" . $vencimiento . "' and enc_con.tipo_consignacion='VENTA' and det_con.numero_orden_entrada='" . $numero_consignacion . "'");
	$row_facturadas = mysqli_fetch_array($sql_facturadas);
	$total_facturadas = $row_facturadas['facturadas'];

	$sql_devueltas = mysqli_query($con, "SELECT sum(det_con.cant_consignacion) as devueltas FROM encabezado_consignacion as enc_con INNER JOIN detalle_consignacion as det_con ON enc_con.codigo_unico=det_con.codigo_unico WHERE enc_con.ruc_empresa = '" . $ruc_empresa . "' and det_con.id_producto='" . $id_producto . "' and enc_con.operacion='DEVOLUCIÓN' and det_con.vencimiento='" . $vencimiento . "' and enc_con.tipo_consignacion='VENTA' and det_con.numero_orden_entrada='" . $numero_consignacion . "'");
	$row_devueltas = mysqli_fetch_array($sql_devueltas);
	$total_devueltas = $row_devueltas['devueltas'];

	//saldo dela  factura temporal
	$busca_saldo_tmp = mysqli_query($con, "SELECT sum(cantidad_tmp) as suma FROM factura_tmp WHERE id_usuario = '" . $id_usuario . "' and ruc_empresa = '" . $ruc_empresa . "' and id_producto = '" . $id_producto . "' and tarifa_iva='" . $numero_consignacion . "' and vencimiento='" . $vencimiento . "'");
	$saldo_producto_tmp = mysqli_fetch_array($busca_saldo_tmp);
	$cantidad_tmp = $saldo_producto_tmp['suma'];

	echo $total_entradas - $total_facturadas - $total_devueltas - $cantidad_tmp;
}

//fechas de vencimiento
if ($action == 'consignacion_venta_caducidades') {
	$id_producto = $_POST["id_producto"];
	$numero_consignacion = $_POST["numero_consignacion"];

	$sql_caducidad = mysqli_query($con, "SELECT det_con.vencimiento as vencimiento FROM encabezado_consignacion as enc_con INNER JOIN detalle_consignacion as det_con ON enc_con.codigo_unico=det_con.codigo_unico WHERE enc_con.ruc_empresa = '" . $ruc_empresa . "' and enc_con.numero_consignacion = '" . $numero_consignacion . "' and det_con.id_producto='" . $id_producto . "' and enc_con.operacion='ENTRADA' and enc_con.tipo_consignacion='VENTA' ");
	?>
	<option value="0" selected>Seleccione</option>
	<?php
	while ($row_vencimiento = mysqli_fetch_array($sql_caducidad)) {
	?>
		<option value="<?php echo $row_vencimiento['vencimiento']; ?>"><?php echo $row_vencimiento['vencimiento']; ?></option>
<?php
	}
}
?>