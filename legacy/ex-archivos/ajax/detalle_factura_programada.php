<?PHP
include("../conexiones/conectalogin.php");
session_start();
$con = conenta_login();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

//para mostrar el detalle de productos al momento de dar clic en detalle de factura programada
if (isset($_GET['muestra_detalle_fp'])) {
	echo detalle_por_facturar($con, $_GET["id_cliente"]);
	//include("../ajax/muestra_detalle_factura_programada.php");
}

//para agregar el detalle de productos a la factura programada
if (isset($_GET['agregar_detalle_fp'])) {
	$id_cliente = "CLIENTE" . mysqli_real_escape_string($con, (strip_tags($_GET["id_cliente"], ENT_QUOTES)));
	$id_producto = mysqli_real_escape_string($con, (strip_tags($_GET["id_producto"], ENT_QUOTES)));
	$cantidad = mysqli_real_escape_string($con, (strip_tags($_GET["cantidad_producto"], ENT_QUOTES)));
	$precio_producto = mysqli_real_escape_string($con, (strip_tags($_GET["precio_producto"], ENT_QUOTES)));
	$periodo = mysqli_real_escape_string($con, (strip_tags($_GET["periodo"], ENT_QUOTES)));
	$fecha_registro = date("Y-m-d H:i:s");
	$guarda_detalle_por_facturar = mysqli_query($con, "INSERT INTO detalle_por_facturar VALUES (null, '" . $ruc_empresa . "','" . $id_cliente . "','" . $id_producto . "','" . $cantidad . "','" . $precio_producto . "','" . $periodo . "','" . $fecha_registro . "','" . $id_usuario . "',0)");
	echo detalle_por_facturar($con, $_GET["id_cliente"]);
	//include("../ajax/muestra_detalle_factura_programada.php");
}

//para eliminar un producto por facturarse
if (isset($_GET['eliminar_detalle_fp'])) {
	$id_registro = $_GET['id_detalle_fp'];
	$elimina_detalle_por_facturarse = mysqli_query($con, "DELETE FROM detalle_por_facturar WHERE id_detalle_pf='" . $id_registro . "'");
	echo detalle_por_facturar($con, $_GET["id_cliente"]);
	//include("../ajax/muestra_detalle_factura_programada.php");
}


//para actualizar precio
if ($action == 'actualiza_precio') {
	$id = $_GET['id'];
	$precio_nuevo = $_GET['precio_nuevo'];
	$id_cliente = $_GET['id_cliente'];

	$update = mysqli_query($con, "UPDATE detalle_por_facturar SET precio_producto='" . number_format($precio_nuevo, 2, '.', '') . "' WHERE id_detalle_pf ='" . $id . "'");
	echo "<script>
	$.notify('Precio actualizado','info');
	</script>";
	echo detalle_por_facturar($con, $id_cliente);
}


function detalle_por_facturar($con, $id_cliente)
{
	//Para mostrar el detalle de los productos agregados -->
	if (isset($id_cliente)) {
		$id_registro = "CLIENTE" . $id_cliente;
		$busca_detalle_facturar = mysqli_query($con, "SELECT dpf.id_detalle_pf as id_detalle_pf, ps.nombre_producto as producto, dpf.cant_producto as cant_producto, dpf.precio_producto as precio_producto, dpf.cuando_facturar as cuando_facturar FROM detalle_por_facturar  as dpf LEFT JOIN productos_servicios as ps ON dpf.id_producto = ps.id WHERE dpf.id_referencia = '" . $id_registro . "' ");
?>
		<div class="form-group">
			<div class="panel panel-info">
				<div class="panel-heading">Detalle de productos y servicios programados</div>
				<!--<div class="panel-body">-->
				<div class="table-responsive">
					<table class="table table-bordered">
						<tr class="info">
							<th>Producto</th>
							<th>Cantidad</th>
							<th>Precio</th>
							<th>Período</th>
							<th>Eliminar</th>
						</tr>
						<?php
						while ($detalle_a_facturar = mysqli_fetch_array($busca_detalle_facturar)) {
							$id_detalle_pf = $detalle_a_facturar['id_detalle_pf'];
							$producto = $detalle_a_facturar['producto'];
							$cant_producto = $detalle_a_facturar['cant_producto'];
							$precio_producto = $detalle_a_facturar['precio_producto'];
							$cuando_facturar = $detalle_a_facturar['cuando_facturar'];

							//buscar datos de cuando facturar
							$busca_cuando_facturar = "SELECT * FROM periodo_a_facturar WHERE codigo_periodo = '" . $cuando_facturar . "' ";
							$result = $con->query($busca_cuando_facturar);
							$cuando_se_facturar = mysqli_fetch_array($result);
							$a_facturar = $cuando_se_facturar['detalle_periodo'];
						?>
							<input type="hidden" value="<?php echo $id_detalle_pf; ?>" id="id_detalle_fp<?php echo $id_detalle_pf; ?>">
							<input type="hidden" value="<?php echo $id_cliente; ?>" id="id_cliente_fp<?php echo $id_detalle_pf; ?>">
							<input type="hidden" value="<?php echo $precio_producto; ?>" id="precio_actual<?php echo $id_detalle_pf; ?>">
							<tr>
								<td><?php echo $producto; ?></td>
								<td class="text-right"><?php echo $cant_producto; ?></td>
								<td class="text-right col-sm-2">
									<input type="number" style="text-align:right; height:30px;" class="form-control input-sm" title="Precio" id="precio_nuevo<?php echo $id_detalle_pf; ?>" onchange="actualiza_precio_factura_programada('<?php echo $id_detalle_pf; ?>');" value="<?php echo $precio_producto; ?>">
								</td>
								<td><?php echo $a_facturar; ?></td>
								<td class="text-center"><a href="#" class='btn btn-danger btn-md' title='Eliminar' onclick="eliminar_detalle_factura_programada('<?php echo $id_detalle_pf; ?>')"><i class="glyphicon glyphicon-trash"></i></a></td>
							</tr>
						<?php
						}
						?>
					</table>
					<!--</div>-->
				</div>
			</div>
		</div>
<?php
	} else {
		echo "No hay datos";
	}
}
?>