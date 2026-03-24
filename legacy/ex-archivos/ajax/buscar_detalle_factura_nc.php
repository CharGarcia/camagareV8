<?php
/* Connect To Database*/
include("../conexiones/conectalogin.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];

//PARA BUSCAR DETALLES DE LAS FACTURAS
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
if ($action == 'detalle_factura') {
	if (empty($_POST['factura'])) {
		$errors[] = "Ingrese un número de factura a la cual le desea aplicar la nota de crédito.";
	} else {

		//para saber los decimales que trabaja esta empresa
		if (isset($_POST['serie'])) {
			$serie_sucursal = substr($_POST['serie'], 0, 7);
			$busca_info_sucursal = "SELECT * FROM sucursales WHERE ruc_empresa = '" . $ruc_empresa . "' and serie = '" . $serie_sucursal . "' ";
			$result_info_sucursal = $con->query($busca_info_sucursal);
			$info_sucursal = mysqli_fetch_array($result_info_sucursal);
			$decimales = $info_sucursal['decimal_doc'];
		}

		if (!isset($_POST['factura']) or empty($_POST['factura'])) {
			$decimales = 2;
		}

		$factura = mysqli_real_escape_string($con, (strip_tags($_POST['factura'], ENT_QUOTES)));
		$serie_factura = substr($factura, 0, 7);
		$secuencial_factura =  substr($factura, 8, 9);

		//comprobar si hay esa factura echa a ese cliente
		$busca_factura_de_cliente = mysqli_query($con, "SELECT * FROM encabezado_factura as enc
		 INNER JOIN clientes as cli ON cli.id=enc.id_cliente
		  WHERE enc.ruc_empresa = '" . $ruc_empresa . "' and enc.serie_factura = '" . $serie_factura . "' 
		  and enc.secuencial_factura ='" . $secuencial_factura . "' ");
		$row_detalle_cliente = mysqli_fetch_array($busca_factura_de_cliente);
		$id_cliente = isset($row_detalle_cliente['id_cliente']) ? $row_detalle_cliente['id_cliente'] : "";
		$fecha_factura = isset($row_detalle_cliente['fecha_factura']) ? $row_detalle_cliente['fecha_factura'] : "";
		$nombre_cliente = isset($row_detalle_cliente['nombre']) ? $row_detalle_cliente['nombre'] : "";

		$count = mysqli_num_rows($busca_factura_de_cliente);
		if ($count == 1) {
?>
			<input type="hidden" name="fecha_factura_consultada" id="fecha_factura_consultada" value="<?php echo $fecha_nc = date('d-m-Y', strtotime($fecha_factura)); ?>">
			<input type="hidden" name="id_cliente_factura" id="id_cliente_factura" value="<?php echo $id_cliente; ?>">
			<input type="hidden" name="nombre_cliente_factura" id="nombre_cliente_factura" value="<?php echo $nombre_cliente; ?>">
			<div class="form-group">
				<label class="col-sm-4 control-label"> Factura a aplicar nota de crédito</label>
				<div class="col-sm-3">
					<input type="text" class="form-control" value="<?php echo $factura; ?>" readonly>
				</div>
			</div>
			<div class="panel panel-info">
				<div class="table-responsive">
					<table class="table table-hover">
						<tr class="info">
							<th>Código</th>
							<th>Producto</th>
							<th>Subtotal</th>
							<th class='text-center'>Cantidad</th>
							<th class='text-center'>Precio</th>
							<th class='text-center'>Descuento</th>
							<th class='text-right'>Agregar</th>
						</tr>
						<?php

						$busca_detalle_factura = mysqli_query($con, "SELECT * FROM cuerpo_factura WHERE ruc_empresa = '" . $ruc_empresa . "' and serie_factura = '" . $serie_factura . "' and secuencial_factura ='" . $secuencial_factura . "' ");

						while ($row = mysqli_fetch_array($busca_detalle_factura)) {
							$id_cuerpo_factura = $row['id_cuerpo_factura'];
							$id_producto = $row['id_producto'];
							$codigo_producto = $row['codigo_producto'];
							$producto = $row['nombre_producto'];
							$cantidad = $row['cantidad_factura'];
							$precio = number_format($row['valor_unitario_factura'], $decimales, '.', '');
							$subtotal = number_format($row['subtotal_factura'], 2, '.', '');
							$descuento = number_format($row['descuento'], 2, '.', '');

						?>
							<tr>
								<input type="hidden" name="serie_factura" id="serie_factura" value="<?php echo $serie_factura; ?>">
								<input type="hidden" name="subtotal_item" id="subtotal_item<?php echo $id_cuerpo_factura; ?>" value="<?php echo $subtotal; ?>">
								<input type="hidden" name="id_producto" id="id_producto<?php echo $id_cuerpo_factura; ?>" value="<?php echo $id_producto; ?>">
								<td><?php echo $codigo_producto; ?></td>
								<td><?php echo $producto; ?></td>
								<td><?php echo $subtotal; ?></td>
								<td class='col-xs-2'>
									<div class="pull-right">
										<input type="text" class="form-control" style="text-align:right" id="cantidad_<?php echo $id_cuerpo_factura; ?>" value="<?php echo $cantidad; ?>">
									</div>
								</td>
								<td class='col-xs-2'>
									<div class="pull-right">
										<input type="text" class="form-control" style="text-align:right" id="precio_<?php echo $id_cuerpo_factura; ?>" value="<?php echo $precio; ?>">
									</div>
								</td>
								<td class='col-xs-2'>
									<div class="pull-right">
										<input type="text" class="form-control" style="text-align:right" id="descuento_<?php echo $id_cuerpo_factura; ?>" value="<?php echo $descuento; ?>">
									</div>
								</td>
								<td class='text-center'><a class='btn btn-info' href="#" onclick="agregar_item_nc('<?php echo $id_cuerpo_factura; ?>')"><i class="glyphicon glyphicon-plus"></i></a></td>
							</tr>
						<?php
						}
						?>
					</table>
				</div>
			</div>
	<?php
		} else {
			$errors[] = "La factura que desea aplicar la nota de crédito no está registrada en el sistema.";
		}
	}
}

if (isset($errors)) {
	?>
	<div class="alert alert-danger" role="alert">
		<button type="button" class="close" data-dismiss="alert">&times;</button>
		<strong>Atención! </strong>
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
		<strong>¡Bien hecho! </strong>
		<?php
		foreach ($messages as $message) {
			echo $message;
		}
		?>
	</div>
<?php
}
?>