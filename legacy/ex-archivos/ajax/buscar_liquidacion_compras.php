<?php
include("../conexiones/conectalogin.php");
require_once("../helpers/helpers.php");
$con = conenta_login();
session_start();
date_default_timezone_set('America/Guayaquil');
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
$fecha_registro = date("Y-m-d H:i:s");
if (isset($_SESSION['id_usuario'])) {
	$delete_factura_tmp = mysqli_query($con, "DELETE FROM factura_tmp WHERE id_usuario = '" . $id_usuario . "' and ruc_empresa = '" . $ruc_empresa . "'");
	$delete_adicional_tmp = mysqli_query($con, "DELETE FROM adicional_tmp WHERE id_usuario = '" . $id_usuario . "'");
}
//PARA ELIMINAR lc ECHAS
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

if ($action == 'cancelar_envio_sri') {
	$id = $_POST["id"];
	$page = $_POST["page"];
	$busca_estado = mysqli_query($con, "SELECT estado_sri FROM encabezado_liquidacion WHERE id_encabezado_liq='" . $id . "' ");
	$row_estado = mysqli_fetch_assoc($busca_estado);
	$estado_sri = $row_estado['estado_sri'];

	if ($estado_sri === "ENVIANDO") {
		$update_estado = mysqli_query($con, "UPDATE encabezado_liquidacion SET estado_sri='PENDIENTE' WHERE id_encabezado_liq='" . $id . "' ");
		if ($update_estado) {
			echo "<script>
			$.notify('Estado actualizado','success');
			load(" . $page . ");
			</script>";
		} else {
			echo "<script>
				$.notify('Intente de nuevo','error');
				</script>";
		}
	} else {
		echo "<script>
			$.notify('No fue posible actualizar','error');
			</script>";
	}
}


if ($action == 'eliminar_liquidacion_compras') {
	$id_lc = intval($_POST['id_lc']);
	$busca_datos_lc = mysqli_query($con, "SELECT * FROM encabezado_liquidacion WHERE id_encabezado_liq = '" . $id_lc . "' ");
	$datos_lc = mysqli_fetch_array($busca_datos_lc);
	$codigo_unico = $datos_lc['codigo_unico'];

	//eliminar la liq y los datos de la liq
	$delete_encabezado = mysqli_query($con, "DELETE FROM encabezado_liquidacion WHERE id_encabezado_liq = '" . $id_lc . "' ");
	$delete_detalle = mysqli_query($con, "DELETE FROM cuerpo_liquidacion WHERE codigo_unico = '" . $codigo_unico . "' ");
	$delete_pago = mysqli_query($con, "DELETE FROM formas_pago_liquidacion WHERE codigo_unico = '" . $codigo_unico . "'");
	$delete_adicional = mysqli_query($con, "DELETE FROM detalle_adicional_liquidacion WHERE codigo_unico = '" . $codigo_unico . "'");
	if ($delete_encabezado && $delete_detalle && $delete_pago && $delete_adicional) {
		echo "<script>
				$.notify('Liquidación eliminada exitosamente','success')
				</script>";
	} else {
		echo "<script>
				$.notify('Lo siento algo ha salido mal intenta nuevamente','error')
				</script>";
	}
}


//PARA BUSCAR LAS liquidaciones

if ($action == 'buscar_liquidacion_compras') {
	$q = mysqli_real_escape_string($con, (strip_tags($_REQUEST['q'], ENT_QUOTES)));
	$ordenado = mysqli_real_escape_string($con, (strip_tags($_GET['ordenado'], ENT_QUOTES)));
	$por = mysqli_real_escape_string($con, (strip_tags($_GET['por'], ENT_QUOTES)));

	$estado_liq = mysqli_real_escape_string($con, (strip_tags($_REQUEST['estado_liq'], ENT_QUOTES)));
	$anio_liq = mysqli_real_escape_string($con, (strip_tags($_REQUEST['anio_liq'], ENT_QUOTES)));
	$mes_liq = mysqli_real_escape_string($con, (strip_tags($_REQUEST['mes_liq'], ENT_QUOTES)));

	if (empty($estado_liq)) {
		$opciones_estado_liq = "";
	} else {
		$opciones_estado_liq = " and el.estado_sri = '" . $estado_liq . "' ";
	}
	if (empty($anio_liq)) {
		$opciones_anio_liq = "";
	} else {
		$opciones_anio_liq = " and year(el.fecha_liquidacion) = '" . $anio_liq . "' ";
	}
	if (empty($mes_liq)) {
		$opciones_mes_liq = "";
	} else {
		$opciones_mes_liq = " and month(el.fecha_liquidacion) = '" . $mes_liq . "' ";
	}

	$aColumns = array('fecha_liquidacion', 'secuencial_liquidacion', 'serie_liquidacion', 'razon_social', 'nombre_comercial', 'ruc_proveedor', 'estado_sri'); //Columnas de busqueda
	$sTable = "encabezado_liquidacion as el, proveedores as pro";
	$sWhere = "WHERE el.ruc_empresa ='" .  $ruc_empresa . " '  AND el.id_proveedor = pro.id_proveedor $opciones_estado_liq $opciones_anio_liq $opciones_mes_liq";
	$text_buscar = explode(' ', $q);
	$like = "";
	for ($i = 0; $i < count($text_buscar); $i++) {
		$like .= "%" . $text_buscar[$i];
	}
	if ($_GET['q'] != "") {
		$sWhere = "WHERE (el.ruc_empresa ='" .  $ruc_empresa . " ' AND el.id_proveedor = pro.id_proveedor $opciones_estado_liq $opciones_anio_liq $opciones_mes_liq AND ";

		for ($i = 0; $i < count($aColumns); $i++) {
			$sWhere .= $aColumns[$i] . " LIKE '" . $like . "%' AND el.ruc_empresa ='" .  $ruc_empresa . " '  AND el.id_proveedor = pro.id_proveedor $opciones_estado_liq $opciones_anio_liq $opciones_mes_liq OR ";
		}

		$sWhere = substr_replace($sWhere, " AND el.ruc_empresa ='" .  $ruc_empresa . " '  AND el.id_proveedor = pro.id_proveedor $opciones_estado_liq $opciones_anio_liq $opciones_mes_liq", -3);
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
	$count_query = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable  $sWhere ");
	$row = mysqli_fetch_array($count_query);
	$numrows = $row['numrows'];
	$total_pages = ceil($numrows / $per_page);
	$reload = '../liquidacion_compra_servicio.php';
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
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("fecha_liquidacion");'>Fecha</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("razon_social");'>Proveedor</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("secuencial_liquidacion");'>Número</button></th>
						<th class='text-right'>Total</th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("estado_sri");'>Estado SRI</button></th>
						<th class='text-right'>Opciones</th>
						<input type="hidden" value="<?php echo $page; ?>" id="pagina">
					</tr>
					<?php

					while ($row = mysqli_fetch_array($query)) {
						$id_encabezado_liquidacion = $row['id_encabezado_liq'];
						$fecha_liquidacion = $row['fecha_liquidacion'];
						$serie_liquidacion = $row['serie_liquidacion'];
						$secuencial_liquidacion = $row['secuencial_liquidacion'];
						$nombre_proveedor_liquidacion = $row['razon_social'];
						$ruc_proveedor = $row['ruc_proveedor'];
						$estado_sri = $row['estado_sri'];
						$total_liquidacion = $row['total_liquidacion'];
						$id_proveedor = $row['id_proveedor'];
						$mail = $row['mail_proveedor'];
						$estado_mail = $row['estado_mail'];
						$aut_sri = $row['aut_sri'];
						$numero_liq = $serie_liquidacion . "-" . str_pad($secuencial_liquidacion, 9, "000000000", STR_PAD_LEFT);

						$respuesta_sri = mysqli_query($con, "SELECT * FROM respuestas_sri WHERE id_documento = '" . $id_encabezado_liquidacion . "' and documento='liquidacion' and status='1' ");
						$row_sri = mysqli_fetch_array($respuesta_sri);
						$mensaje_sri = !empty($row_sri['mensajes']) ? '<span class="badge" title="' . $row_sri['mensajes'] . '">i</span>' : "";

						//estado sri
						switch ($estado_sri) {
							case "ENVIANDO":
								$label_class_sri = 'label-info';
								break;
							case "PENDIENTE":
								$label_class_sri = 'label-warning';
								break;
							case "ANULADA":
								$label_class_sri = 'label-danger';
								break;
							case "NO APLICA":
								$label_class_sri = 'label-info';
								break;
							case "AUTORIZADO":
								$label_class_sri = 'label-success';
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
						<input type="hidden" value="<?php echo $mail; ?>" id="mail_proveedor<?php echo $id_encabezado_liquidacion; ?>">
						<input type="hidden" value="<?php echo $serie_liquidacion; ?>" id="serie_liquidacion<?php echo $id_encabezado_liquidacion; ?>">
						<input type="hidden" value="<?php echo $secuencial_liquidacion; ?>" id="secuencial_liquidacion<?php echo $id_encabezado_liquidacion; ?>">
						<tr>
							<td><?php echo date("d/m/Y", strtotime($fecha_liquidacion)); ?></td>
							<td class='col-md-3'><?php echo strtoupper($nombre_proveedor_liquidacion); ?></td>
							<td><?php echo $serie_liquidacion; ?>-<?php echo str_pad($secuencial_liquidacion, 9, "000000000", STR_PAD_LEFT); ?></td>
							<td class='text-right'><?php echo number_format($total_liquidacion, 2, '.', ''); ?></td>
							<td><span class="label <?php echo $label_class_sri; ?>"><?php echo $estado_sri; ?><?php echo $mensaje_sri; ?></span></td>

							<td class='col-md-3'><span class="pull-right">

									<?php
									//PARA ENVIAR AL SRI
									switch ($estado_sri) {
										case "PENDIENTE";
										case "DEVUELTA";
											if (getPermisos($con, $id_usuario, $ruc_empresa, 'liquidacion_compra_servicio')['w'] == 1) {
									?>
												<a class='btn btn-success btn-xs' onclick="enviar_liquidacion_sri('<?php echo $id_encabezado_liquidacion; ?>');" title='Enviar al SRI'><i class="glyphicon glyphicon-send"></i></a>
											<?php
											}
											break;
									}

									switch ($estado_sri) {
										case "AUTORIZADO";
										case "ANULADA";
											if (getPermisos($con, $id_usuario, $ruc_empresa, 'liquidacion_compra_servicio')['r'] == 1) {
											?>
												<a href="../ajax/imprime_documento.php?id_documento=<?php echo base64_encode($id_encabezado_liquidacion) ?>&tipo_documento=liquidacion&tipo_archivo=pdf" class='btn btn-default btn-xs' title='Ver' download target="_blank">Pdf</i> </a>
												<a href="../ajax/imprime_documento.php?id_documento=<?php echo base64_encode($id_encabezado_liquidacion) ?>&tipo_documento=liquidacion&tipo_archivo=xml" class='btn btn-default btn-xs' title='Ver' download target="_blank">Xml</i> </a>
											<?php
											}
											break;
									}

									switch ($estado_sri) {
										case "ENVIANDO";
											if (getPermisos($con, $id_usuario, $ruc_empresa, 'liquidacion_compra_servicio')['r'] == 1) {
											?>
												<b>Enviando al SRI... </b><a class='btn btn-default btn-xs' title='Cancelar envio' onclick="cancelar_envio_sri('<?php echo $id_encabezado_liquidacion; ?>')">Cancelar </a>
											<?php
											}
											break;
									}


									//PARA mostrar detalle de la liquidación
									switch ($estado_sri) {
										case "PENDIENTE";
										case "DEVUELTA";
										case "AUTORIZADO";
											if (getPermisos($con, $id_usuario, $ruc_empresa, 'liquidacion_compra_servicio')['r'] == 1) {
											?>
												<a class='btn btn-info btn-xs' title='Detalle liquidación' onclick="detalle_liquidacion('<?php echo $id_encabezado_liquidacion; ?>')" data-toggle="modal" data-target="#detalleDocumento"><i class="glyphicon glyphicon-list"></i> </a>
									<?php
											}
											break;
									}
									?>
									<?php
									if ($estado_sri == "PENDIENTE") {
										if (getPermisos($con, $id_usuario, $ruc_empresa, 'liquidacion_compra_servicio')['d'] == 1) {
									?>
											<a class='btn btn-danger btn-xs' title='Eliminar liquidación' onclick="eliminar_liquidacion('<?php echo $id_encabezado_liquidacion; ?>')"><i class="glyphicon glyphicon-erase"></i> </a>
										<?php
										}
									}
									if ($estado_sri == "AUTORIZADO") {
										if (getPermisos($con, $id_usuario, $ruc_empresa, 'liquidacion_compra_servicio')['r'] == 1) {
										?>
											<a class="<?php echo $estado_mail_final; ?>" onclick="enviar_liquidacion_mail('<?php echo $id_encabezado_liquidacion; ?>');" title='Enviar por mail al proveedor' data-toggle="modal" data-target="#EnviarDocumentosMail"><i class="glyphicon glyphicon-envelope"></i> </a>
											<?php
										}
									}

									//para anular una liquidación autorizada por el sri
									switch ($estado_sri) {
										case "AUTORIZADO":
											if (getPermisos($con, $id_usuario, $ruc_empresa, 'liquidacion_compra_servicio')['d'] == 1) {
												if (mostrarBotonAnular($fecha_liquidacion)) {
											?>
													<a class='btn btn-warning btn-xs' title='Anular liquidación' data-toggle="modal" data-target="#AnularDocumentosSri" onclick="anular_documento_en_sri('<?php echo $id_encabezado_liquidacion; ?>')"><i class="glyphicon glyphicon-remove"></i> </a>
									<?php
												}
											}
											break;
									}
									?>
								</span></td>
						</tr>
					<?php
					}
					?>
					<tr>
						<td colspan="9"><span class="pull-right">
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

//detalle de liquidaciones
if ($action == 'detalle_liquidaciones') {
	$q = mysqli_real_escape_string($con, (strip_tags($_REQUEST['d'], ENT_QUOTES)));
	$ordenado = mysqli_real_escape_string($con, (strip_tags($_GET['ordenado_det'], ENT_QUOTES)));
	$por = mysqli_real_escape_string($con, (strip_tags($_GET['por_det'], ENT_QUOTES)));
	$aColumns = array('nombre_producto', 'codigo_producto'); //Columnas de busqueda
	$sTable = "cuerpo_liquidacion as cue INNER JOIN encabezado_liquidacion as enc ON enc.codigo_unico=cue.codigo_unico and enc.ruc_empresa=cue.ruc_empresa";
	$sWhere = "WHERE cue.ruc_empresa ='" .  $ruc_empresa . " '  ";
	$text_buscar = explode(' ', $q);
	$like = "";
	for ($i = 0; $i < count($text_buscar); $i++) {
		$like .= "%" . $text_buscar[$i];
	}
	if ($_GET['d'] != "") {
		$sWhere = "WHERE (cue.ruc_empresa ='" .  $ruc_empresa . " ' AND ";

		for ($i = 0; $i < count($aColumns); $i++) {
			$sWhere .= $aColumns[$i] . " LIKE '%" . $like . "%' AND cue.ruc_empresa ='" .  $ruc_empresa . " '  OR ";
		}

		$sWhere = substr_replace($sWhere, " AND cue.ruc_empresa ='" .  $ruc_empresa . " ' ", -3);
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
	$count_query = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable  $sWhere ");
	$row = mysqli_fetch_array($count_query);
	$numrows = $row['numrows'];
	$total_pages = ceil($numrows / $per_page);
	$reload = '';
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
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("codigo_producto");'>Código</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("nombre_producto");'>Detalle</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("secuencial_liquidacion");'>Número</button></th>
						<th class='text-right'>Opciones</th>
						<input type="hidden" value="<?php echo $page; ?>" id="pagina">
					</tr>
					<?php

					while ($row = mysqli_fetch_array($query)) {
						$id_encabezado_liquidacion = $row['id_encabezado_liq'];
						$nombre_producto = $row['nombre_producto'];
						$codigo_producto = $row['codigo_producto'];
						$secuencial_liquidacion = $row['secuencial_liquidacion'];
						$serie_liquidacion = $row['serie_liquidacion'];
						$numero_liq = $serie_liquidacion . "-" . str_pad($secuencial_liquidacion, 9, "000000000", STR_PAD_LEFT);
					?>
						<tr>
							<td class='col-md-1'><?php echo strtoupper($codigo_producto); ?></td>
							<td class='col-md-3'><?php echo strtoupper($nombre_producto); ?></td>
							<td><?php echo $numero_liq; ?></td>
							<td class='col-md-3'><span class="pull-right">
									<a href="#" class='btn btn-info btn-xs' title='Detalle liquidación' onclick="detalle_liquidacion('<?php echo $id_encabezado_liquidacion; ?>')" data-toggle="modal" data-target="#detalleDocumento"><i class="glyphicon glyphicon-list"></i> </a>
								</span></td>
						</tr>
					<?php
					}
					?>
					<tr>
						<td colspan="5"><span class="pull-right">
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

//detalle de adicionales
if ($action == 'detalle_adicionales') {
	$a = mysqli_real_escape_string($con, (strip_tags($_REQUEST['a'], ENT_QUOTES)));
	$ordenado = mysqli_real_escape_string($con, (strip_tags($_GET['ordenado_adi'], ENT_QUOTES)));
	$por = mysqli_real_escape_string($con, (strip_tags($_GET['por_adi'], ENT_QUOTES)));
	$aColumns = array('adicional_concepto', 'adicional_descripcion'); //Columnas de busqueda
	$sTable = "detalle_adicional_liquidacion as adi INNER JOIN encabezado_liquidacion as enc ON enc.codigo_unico=adi.codigo_unico and enc.ruc_empresa=adi.ruc_empresa";
	$sWhere = "WHERE adi.ruc_empresa ='" .  $ruc_empresa . " '  ";
	$text_buscar = explode(' ', $a);
	$like = "";
	for ($i = 0; $i < count($text_buscar); $i++) {
		$like .= "%" . $text_buscar[$i];
	}
	if ($_GET['a'] != "") {
		$sWhere = "WHERE (adi.ruc_empresa ='" .  $ruc_empresa . " ' AND ";

		for ($i = 0; $i < count($aColumns); $i++) {
			$sWhere .= $aColumns[$i] . " LIKE '%" . $like . "%' AND adi.ruc_empresa ='" .  $ruc_empresa . " '  OR ";
		}

		$sWhere = substr_replace($sWhere, " AND adi.ruc_empresa ='" .  $ruc_empresa . " ' ", -3);
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
	$count_query = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable  $sWhere ");
	$row = mysqli_fetch_array($count_query);
	$numrows = $row['numrows'];
	$total_pages = ceil($numrows / $per_page);
	$reload = '';
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
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("adicional_concepto");'>Adicionales</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("adicional_descripcion");'>Conceptos</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("secuencial_liquidacion");'>Número</button></th>
						<th class='text-right'>Opciones</th>
						<input type="hidden" value="<?php echo $page; ?>" id="pagina">
					</tr>
					<?php

					while ($row = mysqli_fetch_array($query)) {
						$id_encabezado_liquidacion = $row['id_encabezado_liq'];
						$adicional_concepto = $row['adicional_concepto'];
						$adicional_descripcion = $row['adicional_descripcion'];
						$secuencial_liquidacion = $row['secuencial_liquidacion'];
						$serie_liquidacion = $row['serie_liquidacion'];
						$numero_liq = $serie_liquidacion . "-" . str_pad($secuencial_liquidacion, 9, "000000000", STR_PAD_LEFT);
					?>
						<tr>
							<td class='col-md-1'><?php echo strtoupper($adicional_concepto); ?></td>
							<td class='col-md-3'><?php echo strtoupper($adicional_descripcion); ?></td>
							<td><?php echo $numero_liq; ?></td>
							<td class='col-md-3'><span class="pull-right">
									<a href="#" class='btn btn-info btn-xs' title='Detalle liquidación' onclick="detalle_liquidacion('<?php echo $id_encabezado_liquidacion; ?>')" data-toggle="modal" data-target="#detalleDocumento"><i class="glyphicon glyphicon-list"></i> </a>
								</span></td>
						</tr>
					<?php
					}
					?>
					<tr>
						<td colspan="5"><span class="pull-right">
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