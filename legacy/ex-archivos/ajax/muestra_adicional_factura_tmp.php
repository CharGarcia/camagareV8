<?php
//Para mostrar el detalle de los adicionales de la factura -->
function muestra_adicionales_factura($serie_factura, $secuencial_factura, $id_usuario, $con, $id_cliente)
{
	$busca_empresa_detalle = mysqli_query($con, "SELECT cli.email as email, 
				cli.direccion as direccion, cli.telefono as telefono, ven.nombre as vendedor FROM clientes as cli LEFT JOIN vendedores as ven
				ON ven.id_vendedor=cli.id_vendedor WHERE cli.id = '" . $id_cliente . "' ");
	$datos_detalle = mysqli_fetch_array($busca_empresa_detalle);
	$email = $datos_detalle['email'];
	$direccion = $datos_detalle['direccion'];
	$telefono = $datos_detalle['telefono'];
	$asesor = $datos_detalle['vendedor'];

	$delete_mail_adicional_tmp = mysqli_query($con, "DELETE FROM adicional_tmp WHERE id_usuario = '" . $id_usuario . "' and serie_factura = '" . $serie_factura . "' and secuencial_factura = '" . $secuencial_factura . "' and concepto='Email'");
	$delete_direccion_adicional_tmp = mysqli_query($con, "DELETE FROM adicional_tmp WHERE id_usuario = '" . $id_usuario . "' and serie_factura = '" . $serie_factura . "' and secuencial_factura = '" . $secuencial_factura . "' and concepto='Dirección'");
	$delete_telefono_adicional_tmp = mysqli_query($con, "DELETE FROM adicional_tmp WHERE id_usuario = '" . $id_usuario . "' and serie_factura = '" . $serie_factura . "' and secuencial_factura = '" . $secuencial_factura . "' and concepto='Teléfono'");
	$delete_telefono_adicional_tmp = mysqli_query($con, "DELETE FROM adicional_tmp WHERE id_usuario = '" . $id_usuario . "' and serie_factura = '" . $serie_factura . "' and secuencial_factura = '" . $secuencial_factura . "' and concepto='Asesor'");

	$detalle_adicional_uno = mysqli_query($con, "INSERT INTO adicional_tmp VALUES (null, '" . $id_usuario . "', '" . $serie_factura . "', '" . $secuencial_factura . "', 'Email','" . $email . "')");
	$detalle_adicional_uno = mysqli_query($con, "INSERT INTO adicional_tmp VALUES (null, '" . $id_usuario . "', '" . $serie_factura . "', '" . $secuencial_factura . "', 'Dirección','" . $direccion . "')");
	if (isset($telefono)) {
		$detalle_adicional_uno = mysqli_query($con, "INSERT INTO adicional_tmp VALUES (null, '" . $id_usuario . "', '" . $serie_factura . "', '" . $secuencial_factura . "', 'Teléfono','" . $telefono . "')");
	}
	if (isset($asesor)) {
		$detalle_adicional_uno = mysqli_query($con, "INSERT INTO adicional_tmp VALUES (null, '" . $id_usuario . "', '" . $serie_factura . "', '" . $secuencial_factura . "', 'Asesor','" . $asesor . "')");
	}

?>
	<div class="table-responsive">
		<table class="table table-bordered">

			<tr class="info">
				<td class='col-xs-3'>
					<input type="text" class="form-control input-sm" id="adicional_concepto" name="adicional_concepto" placeholder="Concepto">
				</td>
				<td class="col-xs-7">
					<input type="text" class="form-control input-sm" id="adicional_descripcion" name="adicional_descripcion" placeholder="Descripción del detalle">
				</td>
				<td class="text-center"><a class='btn btn-info btn-sm' title='Agregar' onclick="agregar_info_adicional()"><i class="glyphicon glyphicon-plus"></i></a></td>
			</tr>
			<?php
			$muestra_adicional_tmp = "SELECT * FROM adicional_tmp WHERE id_usuario = '" . $id_usuario . "' and serie_factura = '" . $serie_factura . "' and secuencial_factura = '" . $secuencial_factura . "'";
			$query = mysqli_query($con, $muestra_adicional_tmp);
			while ($detalle_info_adicional = mysqli_fetch_array($query)) {
				$id_info_adicional = $detalle_info_adicional['id_ad_tmp'];
				$concepto = $detalle_info_adicional['concepto'];
				$detalle = $detalle_info_adicional['detalle'];
			?>
				<tr>
					<input type="hidden" id="id_cliente_adicional" value="<?php echo $id_cliente; ?>">
					<input type="hidden" id="serie_adicional" value="<?php echo $serie_factura; ?>">
					<input type="hidden" id="secuencial_adicional" value="<?php echo $secuencial_factura; ?>">
					<td><?php echo $concepto; ?></td>
					<td><?php echo $detalle; ?></td>
					<?php
					if ($concepto == "Email" || $concepto == "Dirección" || $concepto == "Teléfono" || $concepto == "Asesor") {
					?>
						<td class='text-center'></td>
					<?php
					} else {
					?>
						<td class='text-center'><a class='btn btn-danger btn-sm' title='Eliminar' onclick="eliminar_detalle_info_adicional('<?php echo $id_info_adicional; ?>')"><i class="glyphicon glyphicon-remove"></i></a></td>
					<?php
					}
					?>
				</tr>

			<?php

			}
			?>
		</table>
	</div>
<?php
}

?>