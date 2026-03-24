<?PHP
include("../conexiones/conectalogin.php");
session_start();
$con = conenta_login();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];

$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

//para agregar nuevo iten al facturar
if ($action == 'agregar_detalle_facturacion_consignacion_venta') {
	$fecha_agregado = date("Y-m-d H:i:s");
	$numero_consignacion = mysqli_real_escape_string($con, (strip_tags($_GET["numero_consignacion"], ENT_QUOTES)));
	$id_producto = mysqli_real_escape_string($con, (strip_tags($_GET["id_producto"], ENT_QUOTES)));
	$cantidad_agregar = mysqli_real_escape_string($con, (strip_tags($_GET["cantidad_agregar"], ENT_QUOTES)));
	$lote_agregar = mysqli_real_escape_string($con, (strip_tags($_GET["lote_agregar"], ENT_QUOTES)));
	$cup_agregar = mysqli_real_escape_string($con, (strip_tags($_GET["cup_agregar"], ENT_QUOTES)));
	$medida_agregar = mysqli_real_escape_string($con, (strip_tags($_GET["medida_agregar"], ENT_QUOTES)));
	$caducidad_agregar = mysqli_real_escape_string($con, (strip_tags($_GET["caducidad_agregar"], ENT_QUOTES)));
	$inventario = mysqli_real_escape_string($con, (strip_tags($_GET["inventario"], ENT_QUOTES)));

	$busca_bodega = mysqli_query($con, "SELECT det_con.id_bodega as bodega FROM detalle_consignacion as det_con INNER JOIN encabezado_consignacion as enc_con ON det_con.codigo_unico=enc_con.codigo_unico WHERE enc_con.ruc_empresa='" . $ruc_empresa . "' and enc_con.numero_consignacion='" . $numero_consignacion . "' and det_con.id_producto='" . $id_producto . "'");
	$row_bodega = mysqli_fetch_array($busca_bodega);
	$bodega_agregar = $row_bodega['bodega'];
	$subtotal = 0;
	$agregar_consignacion = mysqli_query($con, "INSERT INTO factura_tmp VALUES (null, '" . $id_producto . "', '" . $cantidad_agregar . "', '0', '0','1', '" . $numero_consignacion . "', '" . $cup_agregar . "','0','" . $id_usuario . "', '" . $bodega_agregar . "','" . $medida_agregar . "','" . $lote_agregar . "','" . $caducidad_agregar . "','" . $subtotal . "','" . $ruc_empresa . "')");
	detalle_nueva_factura_consignacion_ventas();
}

//para agregar nuevo detalle a la consignacion de ventas
if ($action == 'agregar_detalle_consignacion_venta') {
	$fecha_agregado = date("Y-m-d H:i:s");
	$id_producto = mysqli_real_escape_string($con, (strip_tags($_GET["id_producto"], ENT_QUOTES)));
	$cantidad_agregar = mysqli_real_escape_string($con, (strip_tags($_GET["cantidad_agregar"], ENT_QUOTES)));
	$nup_agregar = mysqli_real_escape_string($con, (strip_tags($_GET["nup"], ENT_QUOTES)));
	$lote_agregar = mysqli_real_escape_string($con, (strip_tags($_GET["lote_agregar"], ENT_QUOTES)));
	$bodega_agregar = mysqli_real_escape_string($con, (strip_tags($_GET["bodega_agregar"], ENT_QUOTES)));
	$medida_agregar = mysqli_real_escape_string($con, (strip_tags($_GET["medida_agregar"], ENT_QUOTES)));
	$caducidad_agregar = mysqli_real_escape_string($con, (strip_tags($_GET["caducidad_agregar"], ENT_QUOTES)));
	$inventario = mysqli_real_escape_string($con, (strip_tags($_GET["inventario"], ENT_QUOTES)));

	$agregar_consignacion = mysqli_query($con, "INSERT INTO factura_tmp VALUES (null, '" . $id_producto . "', '" . $cantidad_agregar . "', '0', '0','1', '0', '" . $nup_agregar . "','0','" . $id_usuario . "', '" . $bodega_agregar . "','" . $medida_agregar . "','" . $lote_agregar . "','" . $caducidad_agregar . "',0,'" . $ruc_empresa . "')");
	detalle_nueva_consignacion_venta();
}

//resetea los datos de la tabla temp de factura tmp
if ($action == 'limpiar_info_entrada') {
	$limpiar_tabla = mysqli_query($con, "DELETE FROM factura_tmp WHERE id_usuario='" . $id_usuario . "' and ruc_empresa='" . $ruc_empresa . "'");
}

//para eliminar la consignacion
if ($action == 'eliminar_consignacion_ventas') {
	$codigo_unico = $_GET['codigo_unico'];
	$consultar_encabezado = mysqli_query($con, "SELECT * FROM encabezado_consignacion  WHERE codigo_unico='" . $codigo_unico . "'");
	$row_encabezado = mysqli_fetch_array($consultar_encabezado);
	$numero_consignacion = $row_encabezado['numero_consignacion'];

	$consultar_utilizada = mysqli_query($con, "SELECT * FROM detalle_consignacion  WHERE numero_orden_entrada='" . $numero_consignacion . "' and ruc_empresa='" . $ruc_empresa . "'");
	$entradas = mysqli_num_rows($consultar_utilizada);
	if ($entradas == 0) {
		$actualiza_encabezado = mysqli_query($con, "UPDATE encabezado_consignacion SET observaciones='ANULADA', status='0'  WHERE codigo_unico='" . $codigo_unico . "'");
		$elimina_detalle_consignacion = mysqli_query($con, "DELETE FROM detalle_consignacion WHERE codigo_unico='" . $codigo_unico . "'");
		$eliminar_registros_inventario = mysqli_query($con, "DELETE FROM inventarios WHERE id_documento_venta = '" . $codigo_unico . "'");
		echo "<script>
		$.notify('Consignación anulada','success');
		setTimeout(function (){location.href ='../modulos/consignaciones_ventas.php'}, 1000);
		</script>";
	} else {
		echo "<script>
		$.notify('No es posible eliminar, exiten registros de devoluciones y facturas.','error');
		setTimeout(function (){location.href ='../modulos/consignaciones_ventas.php'}, 1000);
		</script>";
	}
}

//para eliminar devolucion de la consignacion ventas
if ($action == 'eliminar_devolucion_consignacion_ventas') {
	$codigo_unico = $_GET['codigo_unico'];
	$sql_encabezado = mysqli_query($con, "SELECT * FROM encabezado_consignacion WHERE codigo_unico='" . $codigo_unico . "'");
	$row_encabezado = mysqli_fetch_array($sql_encabezado);
	$tipo_consignacion = $row_encabezado['tipo_consignacion'];
	$operacion = $row_encabezado['operacion'];
	$serie = $row_encabezado['serie_sucursal'];
	$factura = $row_encabezado['factura_venta'];
	$empresa_ruc = $row_encabezado['ruc_empresa'];

	if ($tipo_consignacion == "VENTA" && $operacion == "DEVOLUCIÓN") {
		$eliminar_registros_inventario = mysqli_query($con, "DELETE FROM inventarios WHERE id_documento_venta = '" . $codigo_unico . "'");
	}

	$actualiza_encabezado = mysqli_query($con, "UPDATE encabezado_consignacion SET observaciones='ANULADA', status='0'  WHERE codigo_unico='" . $codigo_unico . "'");
	$elimina_detalle_consignacion = mysqli_query($con, "DELETE FROM detalle_consignacion WHERE codigo_unico='" . $codigo_unico . "'");
	echo "<script>
		$.notify('Registro anulado','success');
		setTimeout(function (){location.href ='../modulos/devolucion_consignacion_venta.php'}, 1000);
		</script>";
}



//para eliminar factura de la consignacion ventas
if ($action == 'eliminar_factura_consignacion_venta') {
	$codigo_unico = $_GET['codigo_unico'];
	$sql_encabezado = mysqli_query($con, "SELECT * FROM encabezado_consignacion WHERE codigo_unico='" . $codigo_unico . "'");
	$row_encabezado = mysqli_fetch_array($sql_encabezado);
	$tipo_consignacion = $row_encabezado['tipo_consignacion'];
	$operacion = $row_encabezado['operacion'];
	$serie = $row_encabezado['serie_sucursal'];
	$factura = $row_encabezado['factura_venta'];
	$empresa_ruc = $row_encabezado['ruc_empresa'];

	if ($tipo_consignacion == "VENTA" && $operacion == "FACTURA") {
		$sql_factura = mysqli_query($con, "SELECT * FROM encabezado_factura WHERE serie_factura='" . $serie . "' and secuencial_factura='" . $factura . "' and ruc_empresa='" . $empresa_ruc . "'");
		$row_factura = mysqli_fetch_array($sql_factura);
		$estado_sri = $row_factura['estado_sri'];
		if ($estado_sri == "AUTORIZADO") {
			echo "<script>
		$.notify('Primero debe anular la factura en el SRI y luego en el sistema.','error');
		</script>";
			exit;
		}
		if ($estado_sri == "PENDIENTE") {
			$eliminar_encabezado_factura = mysqli_query($con, "DELETE FROM encabezado_factura WHERE ruc_empresa = '" . $empresa_ruc . "' and serie_factura='" . $serie . "' and secuencial_factura='" . $factura . "'");
			$delete_detalle_factura = mysqli_query($con, "DELETE FROM cuerpo_factura WHERE ruc_empresa = '" . $empresa_ruc . "' and serie_factura='" . $serie . "' and secuencial_factura='" . $factura . "'");
			$delete_pago_factura = mysqli_query($con, "DELETE FROM formas_pago_ventas WHERE ruc_empresa = '" . $empresa_ruc . "' and serie_factura='" . $serie . "' and secuencial_factura='" . $factura . "'");
			$delete_adicional_factura = mysqli_query($con, "DELETE FROM detalle_adicional_factura WHERE ruc_empresa = '" . $empresa_ruc . "' and serie_factura='" . $serie . "' and secuencial_factura='" . $factura . "'");
			echo "<script>
			$.notify('Factura eliminada.','success')
			</script>";
		}
	}

	$actualiza_encabezado = mysqli_query($con, "UPDATE encabezado_consignacion SET observaciones='ANULADA', status='0'  WHERE codigo_unico='" . $codigo_unico . "'");
	$elimina_detalle_consignacion = mysqli_query($con, "DELETE FROM detalle_consignacion WHERE codigo_unico='" . $codigo_unico . "'");
	echo "<script>
		$.notify('Registro anulado','success');
		setTimeout(function (){location.href ='../modulos/facturacion_consignacion_venta.php'}, 1000);
		</script>";
}


//eliminar detalle de la consignacion nueva que se esta generando
if ($action == 'eliminar_item') {
	$id_registro = $_GET['id_registro'];
	$elimina_detalle_factura_tmp = mysqli_query($con, "DELETE FROM factura_tmp WHERE id='" . $id_registro . "'");
	detalle_nueva_consignacion_venta();
}

if ($action == 'eliminar_item_factura_consignacion') {
	$id_registro = $_GET['id_registro'];
	$elimina_detalle_factura_tmp = mysqli_query($con, "DELETE FROM factura_tmp WHERE id='" . $id_registro . "'");
	detalle_nueva_factura_consignacion_ventas();
}

if ($action == 'detalle_consignacion') {
	$codigo_unico = $_GET['codigo_unico'];
	detalle_consignacion($codigo_unico);
}

if ($action == 'mostrar_detalle_devolucion_consignacion') {
	$codigo_unico = $_GET['codigo_unico'];
	detalle_devolucion_consignacion($codigo_unico);
}

if ($action == 'muestra_detalle_consignacion_para_devolucion') {
	$numero_cv = $_GET['numero_cv'];
	detalle_consignacion_para_devolucion($numero_cv);
}


function detalle_consignacion_para_devolucion($numero_cv)
{
	$con = conenta_login();
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$busca_codigo_unico = mysqli_query($con, "SELECT * FROM encabezado_consignacion enc_con INNER JOIN clientes as cli ON enc_con.id_cli_pro=cli.id WHERE enc_con.numero_consignacion = '" . $numero_cv . "' and enc_con.ruc_empresa='" . $ruc_empresa . "' ");
	$encabezado_consignacion = mysqli_fetch_array($busca_codigo_unico);
	$codigo_unico = $encabezado_consignacion['codigo_unico'];
	$busca_consignacion = mysqli_query($con, "SELECT * FROM detalle_consignacion WHERE codigo_unico = '" . $codigo_unico . "' ");
?>
	<h5 style="margin-bottom: 5px; margin-top: -10px; height: 14%"><span class="input-group-addon"><b>Cliente: </b><?php echo $encabezado_consignacion['nombre']; ?></span></h5>
	<div class="panel panel-info">
		<table class="table table-hover">
			<tr class="info">
				<th style="padding: 2px;">Código</th>
				<th style="padding: 2px;">Producto</th>
				<th style="padding: 2px;">Saldo</th>
				<th style="padding: 2px;">Lote</th>
				<th style="padding: 2px;">NUP</th>
				<th style="padding: 2px;">Dev.</th>

			</tr>
			<?php
			while ($detalle = mysqli_fetch_array($busca_consignacion)) {
				$id_det_consignacion = $detalle['id_det_consignacion'];
				$codigo_producto = $detalle['codigo_producto'];
				$id_producto = $detalle['id_producto'];
				$nombre_producto = $detalle['nombre_producto'];
				$nup = $detalle['nup'];
				$lote = $detalle['lote'];
				//buscar salidas
				$busca_entradas = mysqli_query($con, "SELECT sum(det.cant_consignacion) as entradas FROM encabezado_consignacion as enc INNER JOIN detalle_consignacion as det ON enc.codigo_unico=det.codigo_unico WHERE det.id_producto = '" . $id_producto . "' and enc.numero_consignacion='" . $numero_cv . "' and det.nup='" . $nup . "' and enc.ruc_empresa='" . $ruc_empresa . "' and enc.tipo_consignacion='VENTA' and enc.operacion = 'ENTRADA' and det.lote='" . $lote . "'");
				$row_entradas = mysqli_fetch_array($busca_entradas);
				$entradas = $row_entradas['entradas'];
				//buscar salidas
				$busca_salidas = mysqli_query($con, "SELECT sum(det.cant_consignacion) as salidas FROM encabezado_consignacion as enc INNER JOIN detalle_consignacion as det ON enc.codigo_unico=det.codigo_unico WHERE det.id_producto = '" . $id_producto . "' and det.numero_orden_entrada='" . $numero_cv . "' and det.nup='" . $nup . "' and enc.ruc_empresa='" . $ruc_empresa . "' and enc.tipo_consignacion='VENTA' and enc.operacion != 'ENTRADA' and det.lote='" . $lote . "'");
				$row_salidas = mysqli_fetch_array($busca_salidas);
				$saldo = number_format($entradas - $row_salidas['salidas'], 4, '.', '');
			?>
				<tr>
					<input type="hidden" id="saldo<?php echo $id_det_consignacion; ?>" value="<?php echo $saldo; ?>">
					<input type="hidden" name="registros[]" value="<?php echo $id_det_consignacion; ?>">
					<td style="padding: 2px;"><?php echo $codigo_producto; ?></td>
					<td style="padding: 2px;"><?php echo $nombre_producto; ?></td>
					<td style="padding: 2px;"><?php echo $saldo; ?></td>
					<td style="padding: 2px;"><?php echo $lote; ?></td>
					<td style="padding: 2px;"><?php echo $nup; ?></td>
					<td style="padding: 2px;" class="col-sm-2"><input type="text" class="form-control input-sm" name="devolucion[<?php echo $id_det_consignacion; ?>]" id="devolucion<?php echo $id_det_consignacion; ?>" onchange="cantidad_devolucion('<?php echo $id_det_consignacion; ?>');"></td>
				</tr>
			<?php
			}
			?>
		</table>
	</div>
<?php
}

function detalle_nueva_factura_consignacion_ventas()
{
	$con = conenta_login();
	$id_usuario = $_SESSION['id_usuario'];
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$busca_detalle = mysqli_query($con, "SELECT fat_tmp.id as id_tmp, fat_tmp.tarifa_ice as nup, 
	pro_ser.codigo_producto as codigo_producto, fat_tmp.cantidad_tmp as cantidad, 
	pro_ser.nombre_producto as nombre_producto, uni_med.abre_medida as medida, 
	fat_tmp.tarifa_iva as num_con, fat_tmp.vencimiento as vencimiento, fat_tmp.lote as lote, 
	bod.nombre_bodega as bodega, fat_tmp.subtotal as subtotal 
	FROM factura_tmp as fat_tmp INNER JOIN productos_servicios as pro_ser 
	ON fat_tmp.id_producto = pro_ser.id INNER JOIN unidad_medida as uni_med ON fat_tmp.id_medida=uni_med.id_medida 
	INNER JOIN bodega as bod ON fat_tmp.id_bodega=bod.id_bodega 
	WHERE fat_tmp.id_usuario = '" . $id_usuario . "' and fat_tmp.ruc_empresa = '" . $ruc_empresa . "' ");
?>
	<div class="panel panel-info">
		<table class="table table-hover">
			<tr class="info">
				<th style="padding: 2px;">No.CV</th>
				<th style="padding: 2px;">Código</th>
				<th style="padding: 2px;">Producto</th>
				<th style="padding: 2px;">Cant</th>
				<th style="padding: 2px;">Bodega</th>
				<th style="padding: 2px;">Lote</th>
				<th style="padding: 2px;">Nup</th>
				<th style="padding: 2px;">Caducidad</th>
				<th style="padding: 2px;" class='text-right'>Eliminar</th>
			</tr>
			<?php
			while ($detalle = mysqli_fetch_array($busca_detalle)) {
				$id_detalle = $detalle['id_tmp'];
				$codigo_producto = $detalle['codigo_producto'];
				$nombre_producto = $detalle['nombre_producto'];
				$cantidad = $detalle['cantidad'];
				$numero_consignacion = $detalle['num_con'];
				$bodega = $detalle['bodega'];
				$lote = $detalle['lote'];
				$nup = $detalle['nup'];
				$vencimiento = date('d-m-Y', strtotime($detalle['vencimiento']));
			?>
				<tr>
					<td style="padding: 2px;"><?php echo $numero_consignacion; ?></td>
					<td style="padding: 2px;"><?php echo $codigo_producto; ?></td>
					<td style="padding: 2px;"><?php echo $nombre_producto; ?></td>
					<td style="padding: 2px;"><?php echo $cantidad; ?></td>
					<td style="padding: 2px;"><?php echo $bodega; ?></td>
					<td style="padding: 2px;"><?php echo $lote; ?></td>
					<td style="padding: 2px;"><?php echo $nup; ?></td>
					<td style="padding: 2px;"><?php echo $vencimiento; ?></td>
					<td style="padding: 2px;" class='text-right'><a href="#" class='btn btn-danger btn-xs' title='Eliminar' onclick="eliminar_opcion_detalle_consignacion('<?php echo $id_detalle; ?>')"><i class="glyphicon glyphicon-remove"></i></a></td>
				</tr>
			<?php
			}
			?>
		</table>
	</div>
<?php
}

function detalle_nueva_consignacion_venta()
{
	$con = conenta_login();
	$id_usuario = $_SESSION['id_usuario'];
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$busca_detalle = mysqli_query($con, "SELECT fat_tmp.tarifa_ice as nup, 
	fat_tmp.id as id_tmp, pro_ser.codigo_producto as codigo_producto, fat_tmp.cantidad_tmp as cantidad, 
	pro_ser.nombre_producto as nombre_producto, uni_med.abre_medida as medida, 
	bod.nombre_bodega as bodega, fat_tmp.vencimiento as vencimiento, fat_tmp.lote as lote 
	FROM factura_tmp as fat_tmp INNER JOIN productos_servicios as pro_ser 
	ON fat_tmp.id_producto = pro_ser.id INNER JOIN bodega as bod ON fat_tmp.id_bodega=bod.id_bodega 
	INNER JOIN unidad_medida as uni_med ON fat_tmp.id_medida=uni_med.id_medida 
	WHERE fat_tmp.id_usuario = '" . $id_usuario . "' and fat_tmp.ruc_empresa = '" . $ruc_empresa . "' ");
?>
	<div class="panel panel-info">
		<table class="table table-hover">
			<tr class="info">
				<th style="padding: 2px;">Código</th>
				<th style="padding: 2px;">Producto</th>
				<th style="padding: 2px;">Cant</th>
				<th style="padding: 2px;">Bodega</th>
				<th style="padding: 2px;">Lote</th>
				<th style="padding: 2px;">Nup</th>
				<th style="padding: 2px;">Caducidad</th>
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
					<td style="padding: 2px;"><?php echo $vencimiento; ?></td>
					<td style="padding: 2px;" class='text-right'><a href="#" class='btn btn-danger btn-xs' title='Eliminar' onclick="eliminar_detalle_consignacion('<?php echo $id_detalle; ?>')"><i class="glyphicon glyphicon-remove"></i></a></td>
				</tr>
			<?php
			}
			?>
		</table>
	</div>
<?php
}

function detalle_consignacion($codigo_unico)
{
	$con = conenta_login();
	$busca_encabezado = mysqli_query($con, "SELECT * FROM encabezado_consignacion as enc_con INNER JOIN clientes as cli ON enc_con.id_cli_pro=cli.id WHERE enc_con.codigo_unico = '" . $codigo_unico . "' ");
	$encabezado_consignacion = mysqli_fetch_array($busca_encabezado);
	$busca_detalle = mysqli_query($con, "SELECT * FROM detalle_consignacion as det_con INNER JOIN bodega as bod ON det_con.id_bodega=bod.id_bodega INNER JOIN unidad_medida as uni_med ON det_con.id_medida=uni_med.id_medida WHERE det_con.codigo_unico = '" . $codigo_unico . "' ");
?>
	<h5 style="margin-bottom: 5px; margin-top: -10px; height: 14%"><span class="input-group-addon"><b>No:</b> <?php echo $encabezado_consignacion['numero_consignacion']; ?> <b>Fecha:</b> <?php echo date("d/m/Y", strtotime($encabezado_consignacion['fecha_consignacion'])); ?> <b>Cliente: </b><?php echo $encabezado_consignacion['nombre']; ?></span></h5>
	<h5 style="margin-bottom: 5px; margin-top: -10px; height: 14%"><span class="input-group-addon"><b>Punto salida: </b><?php echo $encabezado_consignacion['punto_partida']; ?> <b>Punto llegada: </b><?php echo $encabezado_consignacion['punto_llegada']; ?> <b>Responsable: </b><?php echo $encabezado_consignacion['responsable']; ?></span></h5>
	<h5 style="margin-bottom: 5px; margin-top: -10px; height: 14%"><span class="input-group-addon"><b>Observaciones: </b><?php echo $encabezado_consignacion['observaciones']; ?></span></h5>
	<div class="panel panel-info">
		<table class="table table-hover">
			<tr class="info">
				<th style="padding: 2px;">Código</th>
				<th style="padding: 2px;">Producto</th>
				<th style="padding: 2px;">Cant</th>
				<th style="padding: 2px;">Bodega</th>
				<th style="padding: 2px;">Lote</th>
				<th style="padding: 2px;">NUP</th>
				<th style="padding: 2px;">Caducidad</th>
				<th style="padding: 2px;">No.CV</th>
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
<?php
}

function detalle_devolucion_consignacion($codigo_unico)
{
	$con = conenta_login();
	$busca_encabezado = mysqli_query($con, "SELECT * FROM encabezado_consignacion as enc_con INNER JOIN clientes as cli ON enc_con.id_cli_pro=cli.id WHERE enc_con.codigo_unico = '" . $codigo_unico . "' ");
	$encabezado_consignacion = mysqli_fetch_array($busca_encabezado);
	$busca_detalle = mysqli_query($con, "SELECT * FROM detalle_consignacion as det_con INNER JOIN bodega as bod ON det_con.id_bodega=bod.id_bodega INNER JOIN unidad_medida as uni_med ON det_con.id_medida=uni_med.id_medida WHERE det_con.codigo_unico = '" . $codigo_unico . "' ");
	if ($encabezado_consignacion['operacion'] == "FACTURA") {
		$numero_factura = " No." . $encabezado_consignacion['serie_sucursal'] . "-" . str_pad($encabezado_consignacion['factura_venta'], 9, "000000000", STR_PAD_LEFT);
	} else {
		$numero_factura = "";
	}
?>
	<h5 style="margin-bottom: 5px; margin-top: -10px; height: 14%"><span class="input-group-addon"><b>No:</b> <?php echo $encabezado_consignacion['numero_consignacion']; ?> <b>Fecha:</b> <?php echo date("d/m/Y", strtotime($encabezado_consignacion['fecha_consignacion'])); ?> <b>Cliente: </b><?php echo $encabezado_consignacion['nombre']; ?></span></h5>
	<h5 style="margin-bottom: 5px; margin-top: -10px; height: 14%"><span class="input-group-addon"><b>Tipo: </b><?php echo $encabezado_consignacion['operacion'] . $numero_factura; ?> <b>Observaciones: </b><?php echo $encabezado_consignacion['observaciones']; ?></span></h5>
	<div class="panel panel-info">
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
				<th style="padding: 2px;">No. CV</th>
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
				$numero_orden_entrada = $detalle['numero_orden_entrada'];
			?>
				<tr>
					<td style="padding: 2px;"><?php echo $numero_orden_entrada; ?></td>
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
<?php
}
?>