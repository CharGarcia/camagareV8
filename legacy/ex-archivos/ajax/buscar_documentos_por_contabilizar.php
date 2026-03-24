<?php
include("../conexiones/conectalogin.php");
include("../clases/contabilizacion.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_empresa = $_SESSION['id_empresa'];
$id_usuario = $_SESSION['id_usuario'];
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
$contabilizacion = new contabilizacion();

//para eliminar los asientos que se van a contabilizar
if ($action == 'eliminar_registro') {
	$transaccion = $_GET['transaccion'];
	$id_registro = $_GET['id_registro'];
	$eliminar_registro = $contabilizacion->eliminaRegistroPorContabilizar($con, $ruc_empresa, $id_registro);

	if ($eliminar_registro) {
		$registros_vacios = $contabilizacion->contarRegistrosSinCuentaContable($con, $ruc_empresa, $transaccion);
		echo view_asientos_contables_para_guardar($con, $ruc_empresa, $registros_vacios, $transaccion);
		echo "<script>$.notify('Registro eliminado.','success')</script>";
	} else {
		echo "<script>$.notify('Lo siento algo ha salido mal intenta nuevamente.','error')</script>";
	}
}

//para traer facturas de ventas
if ($action == 'ventas') {
	$desde = mysqli_real_escape_string($con, (strip_tags($_REQUEST['desde'], ENT_QUOTES)));
	$hasta = mysqli_real_escape_string($con, (strip_tags($_REQUEST['hasta'], ENT_QUOTES)));
	//para pasar los documentos para ser contabilizados
	$documentos_a_contabilizar = $contabilizacion->documentosVentasFacturas($con, $ruc_empresa, $desde, $hasta);
	if ($documentos_a_contabilizar == 'noData') {
?>
		<div style="padding: 2px; margin-bottom: 5px; margin-top: -10px;" class="alert alert-danger" role="alert">
			No hay registros para contabilizar en las fechas ingresadas. <br>
		</div>
	<?php
		exit;
	}
	if ($documentos_a_contabilizar == 'configurarCuentas') {
	?>
		<div style="padding: 2px; margin-bottom: 5px; margin-top: -10px;" class="alert alert-danger" role="alert">
			En el menú contabilidad/configuración Cuentas Contables/ tipo asiento: ventas con facturas, <br>
			puede configurar las cuentas para asientos de ventas de forma general, (primera opción) o de forma personalizada. <br>
			* Solo se debe configurar una de las opciones personalizadas, y las otras deben estar en blanco. <br>
			* La primera opción general de contabilización aplica cuando no hay cuentas configuradas en las opciones personalizadas. <br>
			* El orden que sigue el sistema para contabilizar un asiento es: por marcas, por categorías, por productos, por clientes, por tarifa de iva y en general. <br>
			<br>
		</div>
	<?php
	} else {
		$registros_vacios = $contabilizacion->contarRegistrosSinCuentaContable($con, $ruc_empresa, 'ventas');
		echo view_asientos_contables_para_guardar($con, $ruc_empresa, $registros_vacios, 'ventas');
	}
}

//para traer recibos de ventas
if ($action == 'recibos') {
	$desde = mysqli_real_escape_string($con, (strip_tags($_REQUEST['desde'], ENT_QUOTES)));
	$hasta = mysqli_real_escape_string($con, (strip_tags($_REQUEST['hasta'], ENT_QUOTES)));

	$documentos_a_contabilizar = $contabilizacion->documentosVentasRecibos($con, $ruc_empresa, $desde, $hasta);
	if ($documentos_a_contabilizar == 'noData') {
	?>
		<div style="padding: 2px; margin-bottom: 5px; margin-top: -10px;" class="alert alert-danger" role="alert">
			No hay registros para contabilizar en las fechas ingresadas. <br>
		</div>
	<?php
		exit;
	}

	if ($documentos_a_contabilizar == 'configurarCuentas') {
	?>
		<div style="padding: 2px; margin-bottom: 5px; margin-top: -10px;" class="alert alert-danger" role="alert">
			En el menú contabilidad/configuración Cuentas Contables/ tipo asiento: ventas con recibos, <br>
			puede configurar las cuentas para asientos de ventas de forma general, (primera opción) o de forma personalizada. <br>
			* Solo se debe configurar una de las opciones personalizadas, y las otras deben estar en blanco. <br>
			* La primera opción general de contabilización aplica cuando no hay cuentas configuradas en las opciones personalizadas. <br>
			* El orden que sigue el sistema para contabilizar un asiento es: por marcas, por categorías, por productos, por clientes, por tarifa de iva y en general. <br>
		</div>
	<?php
	} else {
		$registros_vacios = $contabilizacion->contarRegistrosSinCuentaContable($con, $ruc_empresa, 'recibos');
		echo view_asientos_contables_para_guardar($con, $ruc_empresa, $registros_vacios, 'recibos');
	}
}

//para traer documentos de notas de credito de ventas
if ($action == 'nc_ventas') {
	$desde = mysqli_real_escape_string($con, (strip_tags($_REQUEST['desde'], ENT_QUOTES)));
	$hasta = mysqli_real_escape_string($con, (strip_tags($_REQUEST['hasta'], ENT_QUOTES)));

	//buscar los documentos a contabilizar
	$documentos_a_contabilizar = $contabilizacion->documentosNcVentas($con, $ruc_empresa, $desde, $hasta);
	if ($documentos_a_contabilizar == 'noData') {
	?>
		<div style="padding: 2px; margin-bottom: 5px; margin-top: -10px;" class="alert alert-danger" role="alert">
			No hay registros para contabilizar en las fechas ingresadas. <br>
		</div>
	<?php
		exit;
	}
	if ($documentos_a_contabilizar == 'configurarCuentas') {
	?>
		<div style="padding: 2px; margin-bottom: 5px; margin-top: -10px;" class="alert alert-danger" role="alert">
			En el menú contabilidad/configuración Cuentas Contables/ tipo asiento: ventas con facturas, <br>
			puede configurar las cuentas para asientos de ventas de forma general, (primera opción) o de forma personalizada. <br>
			* Solo se debe configurar una de las opciones personalizadas, y las otras deben estar en blanco. <br>
			* La primera opción general de contabilización aplica cuando no hay cuentas configuradas en las opciones personalizadas. <br>
			* El orden que sigue el sistema para contabilizar un asiento es: por marcas, por categorías, por productos, por clientes, por tarifa de iva y en general. <br>
		</div>
	<?php
	} else {
		$registros_vacios = $contabilizacion->contarRegistrosSinCuentaContable($con, $ruc_empresa, 'nc_ventas');
		echo view_asientos_contables_para_guardar($con, $ruc_empresa, $registros_vacios, 'nc_ventas');
	}
}

//para traer documentos de retenciones de ventas
if ($action == 'retenciones_ventas') {
	$desde = mysqli_real_escape_string($con, (strip_tags($_REQUEST['desde'], ENT_QUOTES)));
	$hasta = mysqli_real_escape_string($con, (strip_tags($_REQUEST['hasta'], ENT_QUOTES)));

	//buscar los documentos a contabilizar
	$documentos_a_contabilizar = $contabilizacion->documentosRetencionesVentas($con, $ruc_empresa, $desde, $hasta);
	if ($documentos_a_contabilizar == 'noData') {
	?>
		<div style="padding: 2px; margin-bottom: 5px; margin-top: -10px;" class="alert alert-danger" role="alert">
			No hay registros para contabilizar en las fechas ingresadas. <br>
		</div>
	<?php
		exit;
	}
	$registros_vacios = $contabilizacion->contarRegistrosSinCuentaContable($con, $ruc_empresa, 'retenciones_ventas');
	echo view_asientos_contables_para_guardar($con, $ruc_empresa, $registros_vacios, 'retenciones_ventas');
}

//para traer documentos de retenciones de compras
if ($action == 'retenciones_compras') {
	$desde = mysqli_real_escape_string($con, (strip_tags($_REQUEST['desde'], ENT_QUOTES)));
	$hasta = mysqli_real_escape_string($con, (strip_tags($_REQUEST['hasta'], ENT_QUOTES)));
	//buscar los documentos a contabilizar
	$documentos_a_contabilizar = $contabilizacion->documentosRetencionesCompras($con, $ruc_empresa, $desde, $hasta);
	if ($documentos_a_contabilizar == 'noData') {
	?>
		<div style="padding: 2px; margin-bottom: 5px; margin-top: -10px;" class="alert alert-danger" role="alert">
			No hay registros para contabilizar en las fechas ingresadas. <br>
		</div>
	<?php
		exit;
	}
	$registros_vacios = $contabilizacion->contarRegistrosSinCuentaContable($con, $ruc_empresa, 'retenciones_compras');
	echo view_asientos_contables_para_guardar($con, $ruc_empresa, $registros_vacios, 'retenciones_compras');
}

//para traer documentos de compras 
if ($action == 'compras_servicios') {
	$desde = mysqli_real_escape_string($con, (strip_tags($_REQUEST['desde'], ENT_QUOTES)));
	$hasta = mysqli_real_escape_string($con, (strip_tags($_REQUEST['hasta'], ENT_QUOTES)));

	$documentos_a_contabilizar = $contabilizacion->documentosAdquisiciones($con, $ruc_empresa, $desde, $hasta);
	if ($documentos_a_contabilizar == 'noData') {
	?>
		<div style="padding: 2px; margin-bottom: 5px; margin-top: -10px;" class="alert alert-danger" role="alert">
			No hay registros para contabilizar en las fechas ingresadas. <br>
		</div>
	<?php
		exit;
	}
	if ($documentos_a_contabilizar == 'configurarCuentas') {
	?>
		<div style="padding: 2px; margin-bottom: 5px; margin-top: -10px;" class="alert alert-danger" role="alert">
			En el menú contabilidad/configuración Cuentas Contables/ tipo asiento: Adquisiciones de compras y/o servicios, <br>
			puede configurar las cuentas para asientos de adquisiciones de forma general, (primera opción) o de forma personalizada. <br>
			* Solo se debe configurar una de las opciones personalizadas, y las otras deben estar en blanco. <br>
			* La primera opción general de contabilización aplica cuando no hay cuentas configuradas en las opciones personalizadas. <br>
			* El orden que sigue el sistema para contabilizar un asiento es: por proveedores, por tarifa de iva y en general. <br>
		</div>
	<?php
	} else {
		$registros_vacios = $contabilizacion->contarRegistrosSinCuentaContable($con, $ruc_empresa, 'compras_servicios');
		echo view_asientos_contables_para_guardar($con, $ruc_empresa, $registros_vacios, 'compras_servicios');
	}
}

//para traer los documentos de ingreso
if ($action == 'ingresos') {
	$desde = mysqli_real_escape_string($con, (strip_tags($_REQUEST['desde'], ENT_QUOTES)));
	$hasta = mysqli_real_escape_string($con, (strip_tags($_REQUEST['hasta'], ENT_QUOTES)));

	$documentos_a_contabilizar = $contabilizacion->documentosIngresos($con, $ruc_empresa, $desde, $hasta);
	if ($documentos_a_contabilizar == 'noData') {
	?>
		<div style="padding: 2px; margin-bottom: 5px; margin-top: -10px;" class="alert alert-danger" role="alert">
			No hay registros para contabilizar en las fechas ingresadas. <br>
		</div>
	<?php
		exit;
	}

	$registros_vacios = $contabilizacion->contarRegistrosSinCuentaContable($con, $ruc_empresa, 'ingresos');
	echo view_asientos_contables_para_guardar($con, $ruc_empresa, $registros_vacios, 'ingresos');
}

//para traer los documentos de egreso
if ($action == 'egresos') {
	$desde = mysqli_real_escape_string($con, (strip_tags($_REQUEST['desde'], ENT_QUOTES)));
	$hasta = mysqli_real_escape_string($con, (strip_tags($_REQUEST['hasta'], ENT_QUOTES)));
	$documentos_a_contabilizar = $contabilizacion->documentosEgresos($con, $ruc_empresa, $desde, $hasta);
	if ($documentos_a_contabilizar == 'noData') {
	?>
		<div style="padding: 2px; margin-bottom: 5px; margin-top: -10px;" class="alert alert-danger" role="alert">
			No hay registros para contabilizar en las fechas ingresadas. <br>
		</div>
	<?php
		exit;
	}
	$registros_vacios = $contabilizacion->contarRegistrosSinCuentaContable($con, $ruc_empresa, 'egresos');
	echo view_asientos_contables_para_guardar($con, $ruc_empresa, $registros_vacios, 'egresos');
}

//para traer los documentos de roles de pagos
if ($action == 'rol_pagos') {
	$desde = mysqli_real_escape_string($con, (strip_tags($_REQUEST['desde'], ENT_QUOTES)));
	$hasta = mysqli_real_escape_string($con, (strip_tags($_REQUEST['hasta'], ENT_QUOTES)));
	$documentos_a_contabilizar = $contabilizacion->documentosRolPagos($con, $id_empresa, $desde, $hasta, $ruc_empresa);
	if ($documentos_a_contabilizar == 'noData') {
	?>
		<div style="padding: 2px; margin-bottom: 5px; margin-top: -10px;" class="alert alert-danger" role="alert">
			En el menú contabilidad/configuración Cuentas Contables/ tipo asiento: Roles de pago, <br>
			puede configurar las cuentas para asientos de roles de pago de forma general, (primera opción) o de forma personalizada. <br>
			* Solo se debe configurar una de las opciones personalizadas, y las otras deben estar en blanco. <br>
			* La primera opción general de contabilización aplica cuando no hay cuentas configuradas en las opciones personalizadas. <br>
			* El orden que sigue el sistema para contabilizar un asiento es: por empleado de forma individual y luego en general. <br>
		</div>
	<?php
		exit;
	}
	$registros_vacios = $contabilizacion->contarRegistrosSinCuentaContable($con, $ruc_empresa, 'rol_pagos');
	echo view_asientos_contables_para_guardar($con, $ruc_empresa, $registros_vacios, 'rol_pagos');
}

function view_asientos_contables_para_guardar($con, $ruc_empresa, $registros_vacios, $tipo_asiento)
{
	$query_registros = mysqli_query($con, "SELECT distinct(id_registro) as id_registro 
	FROM asientos_automaticos_tmp WHERE ruc_empresa = '" . $ruc_empresa . "' and tipo_asiento = '" . $tipo_asiento . "'");
	if ($query_registros->num_rows > 0) {
	?>
		<div class="panel panel-success">
			<a class="list-group-item list-group-item-success" data-toggle="collapse" data-parent="#accordion" href="#asientosContabilizados"><span class="caret"></span> Asientos contables <?php echo  $registros_vacios > 0 ? "<B><FONT COLOR='red'> ¡Atención! Existen " . $registros_vacios . " registros sin cuentas contables configuradas.</FONT></B>" : "" ?> </a>
			<div id="asientosContabilizados" class="panel-collapse">
				<?php
				while ($row_registros = mysqli_fetch_array($query_registros)) {
					$id_registro = $row_registros['id_registro'];
					$query_asientos = mysqli_query($con, "SELECT id_registro as id_registro, 
				id as id, fecha as fecha, codigo_cuenta as codigo_cuenta, nombre_cuenta as nombre_cuenta, 
				detalle as detalle, round(sum(debe),2) as debe, round(sum(haber),2) as haber, tipo_asiento as tipo_asiento 
				FROM asientos_automaticos_tmp 
				WHERE ruc_empresa = '" . $ruc_empresa . "' and tipo_asiento = '" . $tipo_asiento . "' and id_registro='" . $id_registro . "' 
				group by id order by id asc");
				?>
					<table class="table">
						<tr class="info">
							<th>Fecha</th>
							<th>Código</th>
							<th>Cuenta</th>
							<th>Debe</th>
							<th>Haber</th>
							<th>Detalle</th>
							<th class='text-right'>Opciones</th>
						</tr>
						<?php
						$suma_debe = 0;
						$suma_haber = 0;
						while ($row_asiento = mysqli_fetch_array($query_asientos)) {
							$id_registro = $row_asiento['id_registro'];
						?>
							<tr>
								<td><?php echo date("d/m/Y", strtotime($row_asiento['fecha']));; ?></td>
								<td><?php echo $row_asiento['codigo_cuenta']; ?></td>
								<td><?php echo empty($row_asiento['codigo_cuenta']) ? "<B><FONT COLOR='red'>" . $row_asiento['nombre_cuenta'] . "</FONT></B>" : strtoupper($row_asiento['nombre_cuenta']); ?></td>
								<td><input style="height:25px;" type="number" class="form-control text-right" id="modificar_debe<?php echo $row_asiento['id']; ?>" onchange="modificar_debe('<?php echo $row_asiento['id']; ?>', '<?php echo $row_asiento['tipo_asiento']; ?>');" value="<?php echo number_format($row_asiento['debe'], 2, '.', ''); ?>"></td>
								<td><input style="height:25px;" type="number" class="form-control text-right" id="modificar_haber<?php echo $row_asiento['id']; ?>" onchange="modificar_haber('<?php echo $row_asiento['id']; ?>', '<?php echo $row_asiento['tipo_asiento']; ?>');" value="<?php echo number_format($row_asiento['haber'], 2, '.', ''); ?>"></td>
								<td><?php echo $row_asiento['detalle']; ?></td>
								<td><span class="pull-right">
										<a href="#" class='btn btn-danger btn-sm' title='Eliminar item' onclick="eliminar_registro('<?php echo $id_registro; ?>','ventas');"><i class="glyphicon glyphicon-trash"></i></a>
							</tr>
						<?php
							$suma_debe += $row_asiento['debe'];
							$suma_haber += $row_asiento['haber'];
						}
						$diferencia = number_format($suma_debe - $suma_haber, 2, '.', '');
						if ($diferencia != 0) {
							$diferencia = "<B><FONT COLOR='red'><span class='glyphicon glyphicon-remove'></span> Diferencia </FONT>" . $diferencia;
						} else {
							$diferencia = "<B><FONT COLOR='green'><span class='glyphicon glyphicon-ok'></span></FONT>";
						}
						?>
						<tr class="warning">
							<td colspan="2"></td>
							<td>Sumas</td>
							<td class="text-right"><?php echo number_format($suma_debe, 2, '.', ''); ?></td>
							<td class="text-right"><?php echo number_format($suma_haber, 2, '.', ''); ?></td>
							<td><?php echo $diferencia; ?></td>
							<td></td>
						</tr>
					</table>
				<?php
				}
				?>
			</div>
		</div>
	<?php
	} else {
	?>
		<div style="padding: 2px; margin-bottom: 5px; margin-top: -10px;" class="alert alert-danger" role="alert">
			No hay registros para contabilizar en las fechas ingresadas. <br>
		</div>
<?php
	}
}

if ($action == 'actualizar_debe') {
	$id_tmp = $_POST["id_item"];
	$tipo_asiento = $_POST["tipo_asiento"];
	$debe_diario = $_POST["debe"];
	$haber_diario = 0;
	$actualiza_detalle = mysqli_query($con, "UPDATE asientos_automaticos_tmp SET debe='" . $debe_diario . "', haber='0.00' WHERE id='" . $id_tmp . "'");
	$registros_vacios = $contabilizacion->contarRegistrosSinCuentaContable($con, $ruc_empresa, $tipo_asiento);
	view_asientos_contables_para_guardar($con, $ruc_empresa, $registros_vacios, $tipo_asiento);
}

//actualiza haber
if ($action == 'actualizar_haber') {
	$id_tmp = $_POST["id_item"];
	$tipo_asiento = $_POST["tipo_asiento"];
	$debe_diario = 0;
	$haber_diario = $_POST["haber"];
	$actualiza_detalle = mysqli_query($con, "UPDATE asientos_automaticos_tmp SET debe='0.00', haber='" . $haber_diario . "' WHERE id='" . $id_tmp . "'");
	$registros_vacios = $contabilizacion->contarRegistrosSinCuentaContable($con, $ruc_empresa, $tipo_asiento);
	view_asientos_contables_para_guardar($con, $ruc_empresa, $registros_vacios, $tipo_asiento);
}

//para guardar los asientos generados
if ($action == 'guardar_asientos') {
	$tipo_asiento = $_POST['tipo_asiento'];
	$fecha_desde = date('Y-m-d', strtotime($_POST['fecha_desde'], ENT_QUOTES));
	$fecha_hasta = date('Y-m-d', strtotime($_POST['fecha_hasta'], ENT_QUOTES));
	$sql_contar_documentos = mysqli_query($con, "SELECT * FROM asientos_automaticos_tmp WHERE ruc_empresa = '" . $ruc_empresa . "' ");
	$sql_contar_cuentas = mysqli_query($con, "SELECT * FROM asientos_automaticos_tmp WHERE ruc_empresa = '" . $ruc_empresa . "' and id_cuenta ='0'");
	$guardar_asientos = $contabilizacion->guardar_asientos_contables_generados($con, $ruc_empresa, $id_usuario, $tipo_asiento);

	if ($sql_contar_documentos->num_rows == 0) {
		echo "<script>
				$.notify('No hay documentos para contabilizar.','error');
				</script>";
	} else if ($sql_contar_cuentas->num_rows > 0) {
		echo "<script>
			$.notify('Configurar cuentas en el módulo contabilidad / configurar cuentas contables','error');
			</script>";
	} else if ($guardar_asientos == 'partidaDoble') {
		echo "<script>
			$.notify('Existen asientos que no cumplen con partida doble.','error');
			</script>";
	} else {
		echo "<script>
			$.notify('Registros contables guardados con éxito','success');
			setTimeout(function (){location.href ='../modulos/generar_asientos.php'}, 1000);
			</script>";
	}
}
//						
?>