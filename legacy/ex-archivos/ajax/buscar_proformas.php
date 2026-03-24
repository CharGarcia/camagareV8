<?php
/* Connect To Database*/
include("../conexiones/conectalogin.php");
require_once("../helpers/helpers.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
ini_set('date.timezone', 'America/Guayaquil');
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
$fecha_registro = date("Y-m-d H:i:s");
if (isset($_SESSION['id_usuario'])) {
	$delete_factura_tmp = mysqli_query($con, "DELETE FROM factura_tmp WHERE id_usuario = '" . $id_usuario . "' and ruc_empresa = '" . $ruc_empresa . "'");
	$delete_adicional_tmp = mysqli_query($con, "DELETE FROM adicional_tmp WHERE id_usuario = '" . $id_usuario . "'");
}


//para generar un pedidio desde proformas
if ($action == 'generar_pedido') {
	$codigo_unico = mysqli_real_escape_string($con, (strip_tags($_GET['codigo_unico'], ENT_QUOTES)));
	$secuencial_proforma = mysqli_real_escape_string($con, (strip_tags($_GET['secuencial'], ENT_QUOTES)));
	$serie_factura = mysqli_real_escape_string($con, (strip_tags($_GET['serie'], ENT_QUOTES)));
	$fecha_registro = date("Y-m-d H:i:s");
	$numero_proforma = "Proforma: " . $serie_factura . "-" . str_pad($secuencial_proforma, 9, "000000000", STR_PAD_LEFT);

	//numero de pedido
	$consulta_pedido = mysqli_query($con, "SELECT max(numero_pedido) as ultimo 
	FROM encabezado_pedido WHERE ruc_empresa='" . $ruc_empresa . "' ");
	$row_ultimo = mysqli_fetch_array($consulta_pedido);
	$numero_pedido = $row_ultimo['ultimo'] + 1;

	//encabezado de la proforma
	$sql_encabezado_proforma = mysqli_query($con, "SELECT * FROM encabezado_proforma 
	WHERE codigo_unico ='" . $codigo_unico . "'");
	$row_encabezado_proforma = mysqli_fetch_array($sql_encabezado_proforma);
	$id_cliente_pedido = $row_encabezado_proforma['id_cliente'];

	//detalle de la proforma
	$sql_detalle_proforma = mysqli_query($con, "SELECT * FROM cuerpo_proforma 
	WHERE codigo_unico ='" . $codigo_unico . "'");

	$sql_traslado = mysqli_query($con, "SELECT * FROM responsable_traslado 
	WHERE ruc_empresa ='" . $ruc_empresa . "' and status ='1' order by nombre asc");
	$row_traslado = mysqli_fetch_array($sql_traslado);
	$responsable_traslado = 2; //$row_traslado['id'];

	$guarda_encabezado_pedido = mysqli_query($con, "INSERT INTO encabezado_pedido (ruc_empresa,
																					fecha_entrega,
																					datecreated,
																					responsable,
																					hora_entrega_desde,
																					hora_entrega_hasta,
																					id_cliente,
																					observaciones_interna,
																					id_usuario,
																					numero_pedido)
																				VALUES ('" . $ruc_empresa . "',
																					'" . date('Y-m-d H:i:s', strtotime($fecha_registro)) . "',
																					'" . date("Y-m-d H:i:s") . "',
																					'" . $responsable_traslado . "',
																					'" . date("H:i:s") . "',
																					'" . date("H:i:s", strtotime('+5 hours')) . "',
																					'" . $id_cliente_pedido . "',
																					'" . $numero_proforma . "',
																					'" . $id_usuario . "',
																					'" . $numero_pedido . "')");
	$id_pedido = mysqli_insert_id($con);

	while ($detalle = mysqli_fetch_array($sql_detalle_proforma)) {
		$guarda_detalle = mysqli_query($con, "INSERT INTO detalle_pedido (id_pedido,
																id_producto,
																codigo_producto,
																producto,
																cantidad)
															VALUES ('" . $id_pedido . "',
																'" . $detalle['id_producto'] . "',
																'" . $detalle['codigo_producto'] . "',
																'" . $detalle['nombre_producto'] . "',
																'" . $detalle['cantidad'] . "')");
	}


	if ($guarda_encabezado_pedido) {
		echo "<script>$.notify('Pedido Generado.','success');
				setTimeout(function (){location.href ='../modulos/proformas.php'}, 1000);
					</script>";
	} else {
		echo "<script>$.notify('Lo siento algo ha salido mal intenta nuevamente.','error')</script>";
	}
}

if ($action == 'facturar_proforma') {
	$codigo_unico = mysqli_real_escape_string($con, (strip_tags($_GET['codigo_unico'], ENT_QUOTES)));
	$numero_factura = mysqli_real_escape_string($con, (strip_tags($_GET['factura_final'], ENT_QUOTES)));
	$secuencial_proforma = mysqli_real_escape_string($con, (strip_tags($_GET['secuencial'], ENT_QUOTES)));
	$serie_factura = mysqli_real_escape_string($con, (strip_tags($_GET['serie'], ENT_QUOTES)));
	$fecha_registro = date("Y-m-d H:i:s");
	$factura_venta = $serie_factura . "-" . str_pad($numero_factura, 9, "000000000", STR_PAD_LEFT);
	$referencia_factura_venta = "Factura: " . $serie_factura . "-" . str_pad($numero_factura, 9, "000000000", STR_PAD_LEFT);
	$numero_proforma = $serie_factura . "-" . str_pad($secuencial_proforma, 9, "000000000", STR_PAD_LEFT);

	$query_guarda_encabezado = mysqli_query($con, "INSERT INTO encabezado_factura (id_encabezado_factura, ruc_empresa, fecha_factura, serie_factura, secuencial_factura, id_cliente, observaciones_factura, guia_remision, fecha_registro, estado_pago, tipo_factura, estado_sri, total_factura, id_usuario, ambiente, id_registro_contable, aut_sri, estado_mail, propina, tasa_turistica) 
	SELECT null, '" . $ruc_empresa . "', fecha_proforma, '" . $serie_factura . "', '" . $numero_factura . "', id_cliente, 'Desde proforma', '', '" . $fecha_registro . "', 'NINGUNO', 'ELECTRÓNICA', 'PENDIENTE', total_proforma, '" . $id_usuario . "','0','0','', 'PENDIENTE', '0', '0' FROM encabezado_proforma WHERE ruc_empresa='" . $ruc_empresa . "' and codigo_unico='" . $codigo_unico . "'");

	$query_guarda_detalle = mysqli_query($con, "INSERT INTO cuerpo_factura (id_cuerpo_factura, ruc_empresa, serie_factura, secuencial_factura, id_producto, cantidad_factura, valor_unitario_factura, subtotal_factura, tipo_produccion, tarifa_iva, tarifa_ice, tarifa_bp, descuento, codigo_producto, nombre_producto, id_medida_salida, lote, vencimiento, id_bodega) 
	SELECT null, '" . $ruc_empresa . "', '" . $serie_factura . "', '" . $numero_factura . "', id_producto, cantidad, valor_unitario, subtotal, tipo_produccion, tarifa_iva, tarifa_ice, tarifa_bp, descuento, codigo_producto, nombre_producto, id_medida_salida, lote, vencimiento, id_bodega FROM cuerpo_proforma WHERE ruc_empresa ='" . $ruc_empresa . "' and codigo_unico='" . $codigo_unico . "'");

	$query_guarda_pago = mysqli_query($con, "INSERT INTO formas_pago_ventas (id_fp, ruc_empresa, serie_factura, secuencial_factura, id_forma_pago, valor_pago) 
	SELECT null, '" . $ruc_empresa . "', '" . $serie_factura . "', '" . $numero_factura . "', '20', total_proforma FROM encabezado_proforma WHERE ruc_empresa='" . $ruc_empresa . "' and codigo_unico='" . $codigo_unico . "'");

	$query_guarda_adicionales = mysqli_query($con, "INSERT INTO detalle_adicional_factura (id_detalle, ruc_empresa, serie_factura, secuencial_factura, adicional_concepto, adicional_descripcion) 
	SELECT null, '" . $ruc_empresa . "', '" . $serie_factura . "', '" . $numero_factura . "', adicional_concepto, adicional_descripcion FROM detalle_adicional_proforma WHERE ruc_empresa ='" . $ruc_empresa . "' and codigo_unico='" . $codigo_unico . "'");

	$query_guarda_adicionales_proforma = mysqli_query($con, "INSERT INTO detalle_adicional_factura VALUES (null, '" . $ruc_empresa . "', '" . $serie_factura . "', '" . $numero_factura . "', 'Proforma', '" . $numero_proforma . "')");

	//actualiza el inventario con el numero de factura para cuando anulen o eliminen la factura
	$actualizar_inventario = mysqli_query($con, "UPDATE inventarios SET id_documento_venta='" . $factura_venta . "', referencia='" . $referencia_factura_venta . "' WHERE ruc_empresa = '" . $ruc_empresa . "' and id_documento_venta='" . $codigo_unico . "' ");

	//actualiza estado de proforma a facturada
	$actualizar_proforma = mysqli_query($con, "UPDATE encabezado_proforma SET estado_proforma='FACTURADA', factura_venta='" . $factura_venta . "' WHERE ruc_empresa = '" . $ruc_empresa . "' and codigo_unico='" . $codigo_unico . "' ");

	if ($query_guarda_encabezado && $query_guarda_detalle && $query_guarda_pago && $query_guarda_adicionales && $actualizar_inventario && $actualizar_proforma && $query_guarda_adicionales_proforma) {
		echo "<script>$.notify('Factura realizada.','success');
				setTimeout(function (){location.href ='../modulos/facturas.php'}, 1000);
					</script>";
	} else {
		echo "<script>$.notify('Lo siento algo ha salido mal intenta nuevamente.','error')</script>";
	}
}


if ($action == 'actualizar_mail_cliente') {
	$id_documento = $_POST['id_documento'];
	$mail = $_POST['mail'];
	$buscar_codigo_unico = mysqli_query($con, "SELECT * FROM encabezado_proforma WHERE id_encabezado_proforma = '" . $id_documento . "'");
	$row_codigo_unico = mysqli_fetch_array($buscar_codigo_unico);
	$codigo_unico = $row_codigo_unico['codigo_unico'];
	$actualizar_mail_proforma = mysqli_query($con, "UPDATE detalle_adicional_proforma SET adicional_descripcion='" . $mail . "' WHERE codigo_unico = '" . $codigo_unico . "' and adicional_concepto='Email'");
	//echo "Actualizado".$codigo_unico;
	/*
if($actualizar_mail_proforma){
	echo "Actualizado".$codigo_unico;
}else{
	echo "Error";
}
*/
}
//PARA ANULAR FACTURAS ECHAS
if ($action == 'anular_proforma') {
	if (isset($_POST['id_proforma'])) {
		$id_proforma = intval($_POST['id_proforma']);
		$busca_datos_factura = "SELECT enc_pro.codigo_unico as codigo_unico, enc_pro.serie_proforma as serie_proforma, enc_pro.secuencial_proforma as secuencial_proforma, enc_pro.fecha_proforma as fecha_proforma, cl.ruc as ruc_cliente, enc_pro.ruc_empresa as ruc_empresa FROM encabezado_proforma enc_pro, clientes cl WHERE enc_pro.id_encabezado_proforma = '" . $id_proforma . "' and enc_pro.id_cliente=cl.id ";
		$result = $con->query($busca_datos_factura);
		$datos_factura = mysqli_fetch_array($result);
		$serie_proforma = $datos_factura['serie_proforma'];
		$secuencial = $datos_factura['secuencial_proforma'];
		$mes_periodo = date("m", strtotime($datos_factura['fecha_proforma']));
		$anio_periodo = date("Y", strtotime($datos_factura['fecha_proforma']));
		$ruc_empresa_periodo = $datos_factura['ruc_empresa'];
		$ruc_del_cliente = $datos_factura['ruc_cliente'];
		$codigo_unico = $datos_factura['codigo_unico'];
		$referencia_modificada = "Proforma eliminada: " . $serie_proforma . "-" . str_pad($secuencial, 9, "000000000", STR_PAD_LEFT);

		//eliminar la factura y los datos de la factura
		if ($delete = mysqli_query($con, "UPDATE encabezado_proforma SET estado_proforma='ANULADA' WHERE id_encabezado_proforma = '" . $id_proforma . "'")
			&& $delete_detalle = mysqli_query($con, "DELETE FROM cuerpo_proforma WHERE ruc_empresa = '" . $ruc_empresa . "' and codigo_unico = '" . $codigo_unico . "' ")
			&& $update_inventario = mysqli_query($con, "DELETE FROM inventarios WHERE ruc_empresa = '" . $ruc_empresa . "' and id_documento_venta='" . $codigo_unico . "' ")
			&& $delete_adicional = mysqli_query($con, "DELETE FROM detalle_adicional_proforma WHERE ruc_empresa = '" . $ruc_empresa . "' and codigo_unico = '" . $codigo_unico . "' ")
		) {
			echo "<script>
				$.notify('Proforma anulada exitosamente','success')
				</script>";
		} else {
			echo "<script>
				$.notify('Lo siento algo ha salido mal intenta nuevamente','error')
				</script>";
		}
	}
}

//PARA BUSCAR LAS proformas
if ($action == 'proformas') {
	// escaping, additionally removing everything that could be (html/javascript-) code
	$q = mysqli_real_escape_string($con, (strip_tags($_REQUEST['q'], ENT_QUOTES)));
	$ordenado = mysqli_real_escape_string($con, (strip_tags($_GET['ordenado'], ENT_QUOTES)));
	$por = mysqli_real_escape_string($con, (strip_tags($_GET['por'], ENT_QUOTES)));
	$aColumns = array('fecha_proforma', 'secuencial_proforma', 'serie_proforma', 'nombre', 'ruc'); //Columnas de busqueda
	$sTable = "encabezado_proforma as enc_pro LEFT JOIN clientes as cl ON cl.id=enc_pro.id_cliente";
	$sWhere = "WHERE enc_pro.ruc_empresa ='" .  $ruc_empresa . " '  ";
	if ($_GET['q'] != "") {
		$sWhere = "WHERE (enc_pro.ruc_empresa ='" . $ruc_empresa . "' AND ";

		for ($i = 0; $i < count($aColumns); $i++) {
			$sWhere .= $aColumns[$i] . " LIKE '%" . $q . "%' AND enc_pro.ruc_empresa = '" . $ruc_empresa . "' OR ";
		}
		$sWhere = substr_replace($sWhere, "AND enc_pro.ruc_empresa = '" . $ruc_empresa . "' ", -3);
		$sWhere .= ')';
	}
	$sWhere .= " order by $ordenado $por";
	include("../ajax/pagination.php"); //include pagination file
	//pagination variables
	$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
	$per_page = 20; //how much records you want to show
	$adjacents  = 10; //gap between pages after number of adjacents
	$offset = ($page - 1) * $per_page;
	//Count the total number of row in your table*/
	$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable  $sWhere");
	$row = mysqli_fetch_array($count_query);
	$numrows = $row['numrows'];
	$total_pages = ceil($numrows / $per_page);
	$reload = '../proformas.php';
	//main query to fetch the data
	$sql = "SELECT * FROM  $sTable $sWhere LIMIT $offset,$per_page";
	$query = mysqli_query($con, $sql);
	//loop through fetched data
	if ($numrows > 0) {
?>
		<div class="panel panel-info">
			<div class="table-responsive">
				<table class="table table-hover">
					<tr class="info">
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("fecha_proforma");'>Fecha</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("nombre");'>Cliente</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("secuencial_proforma");'>Número</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Total</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("estado_proforma");'>Estado</button></th>
						<th class='text-right'>Opciones</th>
						<input type="hidden" value="<?php echo $page; ?>" id="pagina">
					</tr>
					<?php

					while ($row = mysqli_fetch_array($query)) {
						$id_encabezado_proforma = $row['id_encabezado_proforma'];
						$fecha_proforma = $row['fecha_proforma'];
						$serie_proforma = $row['serie_proforma'];
						$secuencial_proforma = $row['secuencial_proforma'];
						$nombre_cliente_proforma = $row['nombre'];
						$ruc_cliente = $row['ruc'];
						$estado_mail = $row['estado_mail'];
						$estado_proforma = $row['estado_proforma'];
						$total_proforma = $row['total_proforma'];
						$mail = $row['email'];
						$codigo_unico = $row['codigo_unico'];
						$numero_proforma = $serie_proforma . "-" . str_pad($secuencial_proforma, 9, "000000000", STR_PAD_LEFT);

						//estado mail
						switch ($estado_proforma) {
							case "PENDIENTE":
								$label_class_proforma = 'label-warning';
								break;
							case "CREADA":
								$label_class_proforma = 'label-info';
								break;
							case "ANULADA":
								$label_class_proforma = 'label-danger';
								break;
							case "ENVIADA":
								$label_class_proforma = 'label-info';
								break;
							case "FACTURADA":
								$label_class_proforma = 'label-success';
								break;
						}

						//estado mail
						switch ($estado_mail) {
							case "PENDIENTE":
								$estado_mail_final = 'btn btn-default btn-xs';
								break;
							case "ENVIADO":
								$estado_mail_final = 'btn btn-info btn-xs';
								break;
						}

					?>
						<input type="hidden" value="<?php echo $ruc_cliente; ?>" id="ruc_cliente<?php echo $id_encabezado_proforma; ?>">
						<input type="hidden" value="<?php echo $mail; ?>" id="mail_cliente<?php echo $id_encabezado_proforma; ?>">
						<input type="hidden" value="<?php echo $id_encabezado_proforma; ?>" id="id_encabezado_proforma<?php echo $id_encabezado_proforma; ?>">
						<input type="hidden" value="<?php echo $serie_proforma; ?>" id="serie_proforma<?php echo $id_encabezado_proforma; ?>">
						<input type="hidden" value="<?php echo $secuencial_proforma; ?>" id="secuencial_proforma<?php echo $id_encabezado_proforma; ?>">
						<input type="hidden" value="<?php echo date("d-m-Y", strtotime($fecha_proforma)); ?>" id="fecha_proforma<?php echo $id_encabezado_proforma; ?>">
						<input type="hidden" value="<?php echo $total_proforma ?>" id="total_proforma<?php echo $id_encabezado_proforma; ?>">
						<tr>
							<td><?php echo date("d/m/Y", strtotime($fecha_proforma)); ?></td>
							<td class='col-md-4'><?php echo strtoupper($nombre_cliente_proforma); ?></td>
							<td><?php echo $serie_proforma; ?>-<?php echo str_pad($secuencial_proforma, 9, "000000000", STR_PAD_LEFT); ?></td>
							<td><?php echo number_format($total_proforma, 2, '.', ''); ?></td>
							<td><span class="label <?php echo $label_class_proforma; ?>"><?php echo $estado_proforma; ?></span></td>

							<td class='col-md-3'><span class="pull-right">
									<?php
									if (getPermisos($con, $id_usuario, $ruc_empresa, 'facturas')['w'] == 1) { //si tiene acceso a facturas y puede crear
									?>
										<a href="#" class='btn btn-success btn-xs' title='Facturar proforma' onclick="facturar_proforma('<?php echo $id_encabezado_proforma; ?>','<?php echo $codigo_unico; ?>', '<?php echo $serie_proforma ?>', '<?php echo str_pad($secuencial_proforma, 9, "000000000", STR_PAD_LEFT); ?>')" <?php if ($estado_proforma == 'ANULADA' || $estado_proforma == 'FACTURADA') {
																																																																																		echo 'style="display:none"';
																																																																																	} ?>><i class="glyphicon glyphicon-modal-window"></i> </a>
									<?php
									}
									if (getPermisos($con, $id_usuario, $ruc_empresa, 'pedidos')['w'] == 1) { //si tiene acceso a pedidos y puede crear
									?>
										<a class='btn btn-info btn-xs' title='Generar pedido' onclick="generar_pedido('<?php echo $id_encabezado_proforma; ?>','<?php echo $codigo_unico; ?>', '<?php echo $serie_proforma ?>', '<?php echo str_pad($secuencial_proforma, 9, "000000000", STR_PAD_LEFT); ?>')" <?php if ($estado_proforma == 'ANULADA') {
																																																																													echo 'style="display:none"';
																																																																												} ?>><i class="glyphicon glyphicon-rub"></i> </a>
									<?php
									}
									if (getPermisos($con, $id_usuario, $ruc_empresa, 'proformas')['r'] == 1) {
									?>
										<a href="../ajax/imprime_documento.php?id_documento=<?php echo base64_encode($id_encabezado_proforma) ?>&tipo_documento=proforma&tipo_archivo=pdf" class='btn btn-default btn-xs' title='Ver' <?php if ($estado_proforma == 'PENDIENTE') {
																																																											echo 'style="display:none"';
																																																										} ?> download>Pdf</i> </a>
										<a class='btn btn-info btn-xs' title='Detalle de proforma' onclick="detalle_proforma('<?php echo $codigo_unico; ?>')" data-toggle="modal" data-target="#detalleDocumento" <?php if ($estado_proforma == 'ANULADA') {
																																																						echo 'style="display:none"';
																																																					} ?>><i class="glyphicon glyphicon-list"></i> </a>
										<a class="<?php echo $estado_mail_final; ?>" onclick="enviar_mail_proforma('<?php echo $id_encabezado_proforma; ?>');" title='Enviar por mail al cliente' data-toggle="modal" data-target="#EnviarProforma" <?php if ($estado_proforma == 'ANULADA' || $estado_proforma == 'APROBADA') {
																																																														echo 'style="display:none"';
																																																													} ?>><i class="glyphicon glyphicon-envelope"></i> </a>

									<?php
									}
									if (getPermisos($con, $id_usuario, $ruc_empresa, 'proformas')['u'] == 1) {
									?>
										<a href="../modulos/editar_proforma.php?id_proforma=<?php echo base64_encode($id_encabezado_proforma); ?>" class='btn btn-info btn-xs' title='Editar proforma' <?php if ($estado_proforma == 'FACTURADA' || $estado_proforma == 'ANULADA') {
																																																			echo 'style="display:none"';
																																																		} ?>><i class="glyphicon glyphicon-edit"></i> </a>
									<?php
									}
									if (getPermisos($con, $id_usuario, $ruc_empresa, 'proformas')['d'] == 1) {
									?>
										<a href="#" class='btn btn-warning btn-xs' title='Anular proforma' onclick="anular_proforma('<?php echo $id_encabezado_proforma; ?>')" <?php if ($estado_proforma == 'FACTURADA') {
																																													echo 'style="display:none"';
																																												} ?>><i class="glyphicon glyphicon-erase"></i> </a>
									<?php
									}
									?>
								</span></td>

						</tr>
					<?php
					}
					?>
					<tr>
						<td colspan="7"><span class="pull-right">
								<?php
								echo paginate($reload, $page, $total_pages, $adjacents);
								?></span></td>
					</tr>
				</table>
			</div>
		</div>
<?php
	}
}
?>