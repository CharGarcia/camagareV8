<?php
/* Connect To Database*/
include("../conexiones/conectalogin.php");
include("../validadores/periodo_contable.php");
require_once("../helpers/helpers.php");
$con = conenta_login();
session_start();
date_default_timezone_set('America/Guayaquil');
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
$fecha_registro = date("Y-m-d H:i:s");
//PARA ELIMINAR NC
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';


if ($action == 'cancelar_envio_sri') {
	$id = $_POST["id"];
	$page = $_POST["page"];
	$busca_estado = mysqli_query($con, "SELECT estado_sri FROM encabezado_nc WHERE id_encabezado_nc='" . $id . "' ");
	$row_estado = mysqli_fetch_assoc($busca_estado);
	$estado_sri = $row_estado['estado_sri'];

	if ($estado_sri === "ENVIANDO") {
		$update_estado = mysqli_query($con, "UPDATE encabezado_nc SET estado_sri='PENDIENTE' WHERE id_encabezado_nc='" . $id . "' ");
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

if ($action == 'emilinar_nota_credito') {
	$id_nc = intval($_POST['id_nc']);
	$busca_datos_nc = "SELECT enc.id_registro_contable as id_registro_contable, enc.serie_nc as serie_nc, enc.secuencial_nc as secuencial_nc, enc.fecha_nc as fecha_nc, cl.ruc as ruc_cliente, enc.ruc_empresa as ruc_empresa FROM encabezado_nc enc, clientes cl WHERE enc.id_encabezado_nc = $id_nc and enc.id_cliente=cl.id ";
	$result = $con->query($busca_datos_nc);
	$datos_nc = mysqli_fetch_array($result);
	$serie_nc = $datos_nc['serie_nc'];
	$secuencial = $datos_nc['secuencial_nc'];

	include("../clases/anular_registros.php");
	$anular_asiento_contable = new anular_registros();

	$id_registro_contable = $datos_nc['id_registro_contable'];
	if ($id_registro_contable > 0) {
		$resultado_anular_documento = $anular_asiento_contable->anular_asiento_contable($con, $id_registro_contable);
	}
	//eliminar la nc y los datos de la nc 
	$delete_encabezado = mysqli_query($con, "DELETE FROM encabezado_nc WHERE id_encabezado_nc = '" . $id_nc . "'");
	$delete_detalle = mysqli_query($con, "DELETE FROM cuerpo_nc WHERE ruc_empresa = '" . $ruc_empresa . "' and serie_nc = '" . $serie_nc . "' and secuencial_nc = '" . $secuencial . "'");
	$delete_adicional = mysqli_query($con, "DELETE FROM detalle_adicional_nc WHERE ruc_empresa = '" . $ruc_empresa . "' and serie_nc = '" . $serie_nc . "' and secuencial_nc = '" . $secuencial . "'");
	$delete_vendedor = mysqli_query($con, "DELETE FROM vendedores_ncv WHERE id_ncv = '" . $id_nc . "'");
	if ($delete_encabezado) {
		echo "<script>
				$.notify('Nota de crédito eliminada.','success')
				</script>";
	} else {
		echo "<script>
				$.notify('Lo siento algo ha salido mal intenta nuevamente','error')
				</script>";
	}
}

//PARA BUSCAR LAS NC

if ($action == 'buscar_nota_credito') {
	// escaping, additionally removing everything that could be (html/javascript-) code
	$ordenado = mysqli_real_escape_string($con, (strip_tags($_GET['ordenado'], ENT_QUOTES)));
	$por = mysqli_real_escape_string($con, (strip_tags($_GET['por'], ENT_QUOTES)));
	$q = mysqli_real_escape_string($con, (strip_tags($_REQUEST['q'], ENT_QUOTES)));
	$estado = mysqli_real_escape_string($con, (strip_tags($_GET['estado'], ENT_QUOTES)));
	$anio_nc = mysqli_real_escape_string($con, (strip_tags($_GET['anio_nc'], ENT_QUOTES)));
	$mes_nc = mysqli_real_escape_string($con, (strip_tags($_GET['mes_nc'], ENT_QUOTES)));
	if (empty($estado)) {
		$opciones_status = "";
	} else {
		$opciones_status = " and enc.estado_sri = '" . $estado . "' ";
	}
	if (empty($anio_nc)) {
		$opciones_anio_nc = "";
	} else {
		$opciones_anio_nc = " and year(enc.fecha_nc) = '" . $anio_nc . "' ";
	}
	if (empty($mes_nc)) {
		$opciones_mes_nc = "";
	} else {
		$opciones_mes_nc = " and month(enc.fecha_nc) = '" . $mes_nc . "' ";
	}
	$aColumns = array('enc.fecha_nc', 'enc.secuencial_nc', 'enc.serie_nc', 'enc.factura_modificada', 'cl.nombre'); //Columnas de busqueda
	$sTable = "encabezado_nc as enc INNER JOIN clientes as cl ON cl.id=enc.id_cliente";
	$sWhere = "WHERE enc.ruc_empresa ='" .  $ruc_empresa . " '  $opciones_status $opciones_anio_nc $opciones_mes_nc ";
	$text_buscar = explode(' ', $q);
	$like = "";
	for ($i = 0; $i < count($text_buscar); $i++) {
		$like .= "%" . $text_buscar[$i];
	}
	if ($_GET['q'] != "") {
		$sWhere = "WHERE (enc.ruc_empresa ='" .  $ruc_empresa . " ' $opciones_status $opciones_anio_nc $opciones_mes_nc AND ";

		for ($i = 0; $i < count($aColumns); $i++) {
			$sWhere .= $aColumns[$i] . " LIKE '%" . $like . "%' AND enc.ruc_empresa = '" .  $ruc_empresa . "' $opciones_status $opciones_anio_nc $opciones_mes_nc OR ";
		}

		$sWhere = substr_replace($sWhere, "AND enc.ruc_empresa = '" .  $ruc_empresa . "' $opciones_status $opciones_anio_nc $opciones_mes_nc ", -3);
		$sWhere .= ')';
	}
	$sWhere .= " order by $ordenado $por";
	include("../ajax/pagination.php"); //include pagination file
	//pagination variables
	$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
	$per_page = 20; //how much records you want to show
	$adjacents  = 4; //gap between pages after number of adjacents
	$offset = ($page - 1) * $per_page;
	//Count the total number of row in your table*/
	$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable  $sWhere");
	$row = mysqli_fetch_array($count_query);
	$numrows = $row['numrows'];
	$total_pages = ceil($numrows / $per_page);
	$reload = '../facturas.php';
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
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("fecha_nc");'>Fecha</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("id_cliente");'>Cliente</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("secuencial_nc");'>Número</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("factura_modificada");'>Documento modificado</button></th>
						<th class='text-right'>Total</th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("estado_sri");'>Estado SRI</button></th>
						<th class='text-right'>Opciones</th>
						<input type="hidden" value="<?php echo $page; ?>" id="pagina">
					</tr>
					<?php

					while ($row = mysqli_fetch_array($query)) {
						$id_encabezado_nc = $row['id_encabezado_nc'];
						$fecha_nc = $row['fecha_nc'];
						$serie_nc = $row['serie_nc'];
						$secuencial_nc = $row['secuencial_nc'];
						$factura_modificada = $row['factura_modificada'];
						$nombre_cliente_nc = $row['nombre'];
						$ruc_cliente = $row['ruc'];
						$tipo_nc = $row['tipo_nc'];
						$estado_sri = $row['estado_sri'];
						$total_nc = $row['total_nc'];
						$id_cliente = $row['id'];
						$ambiente = $row['ambiente'];
						$mail = $row['email'];
						$estado_mail = $row['estado_mail'];
						$aut_sri = $row['aut_sri'];
						$numero_nc = $serie_nc . "-" . str_pad($secuencial_nc, 9, "000000000", STR_PAD_LEFT);

						$respuesta_sri = mysqli_query($con, "SELECT * FROM respuestas_sri WHERE id_documento = '" . $id_encabezado_nc . "' and documento='nc' and status='1' ");
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
								$label_class_sri = 'label-danger';;
								break;
							case "NO APLICA":
								$label_class_sri = 'label-info';;
								break;
							case "AUTORIZADO":
								$label_class_sri = 'label-success';
								break;
						}

						//tipo factura
						switch ($tipo_nc) {
							case "ELECTRÓNICA":
								$label_class_tipo = 'label-primary';
								break;
							case "FÍSICA":
								$label_class_tipo = 'label-info';;
								break;
						}

						//ambiente
						switch ($ambiente) {
							case "0":
								$label_class_ambiente = 'label-warning';
								$tipo_ambiente = 'EMITIDA';
								break;
							case "1":
								$label_class_ambiente = 'label-info';
								$tipo_ambiente = 'PRUEBAS';
								break;
							case "2":
								$label_class_ambiente = 'label-success';
								$tipo_ambiente = 'PRODUCCIÓN';
								break;
						}

						//estado mail
						switch ($estado_mail) {
							case "PENDIENTE":
								$estado_mail_final = 'btn btn-default btn-xs';
								break;
							case "ENVIADO":
								$estado_mail_final = 'btn btn-info btn-xs';;
								break;
						}
					?>
						<input type="hidden" value="<?php echo $ruc_cliente; ?>" id="ruc_cliente<?php echo $id_encabezado_nc; ?>">
						<input type="hidden" value="<?php echo $aut_sri; ?>" id="aut_sri<?php echo $id_encabezado_nc; ?>">
						<input type="hidden" value="<?php echo $mail; ?>" id="mail_cliente<?php echo $id_encabezado_nc; ?>">
						<input type="hidden" value="<?php echo $id_encabezado_nc; ?>" id="id_encabezado_nc<?php echo $id_encabezado_nc; ?>">
						<input type="hidden" value="<?php echo $serie_nc; ?>" id="serie_nc<?php echo $id_encabezado_nc; ?>">
						<input type="hidden" value="<?php echo $secuencial_nc; ?>" id="secuencial_nc<?php echo $id_encabezado_nc; ?>">
						<input type="hidden" value="<?php echo date("d-m-Y", strtotime($fecha_nc)); ?>" id="fecha_nc<?php echo $id_encabezado_nc; ?>">
						<tr>
							<td><?php echo date("d/m/Y", strtotime($fecha_nc)); ?></td>
							<td><?php echo $nombre_cliente_nc; ?></td>
							<td><?php echo $serie_nc; ?>-<?php echo str_pad($secuencial_nc, 9, "000000000", STR_PAD_LEFT); ?></td>
							<td><?php echo $factura_modificada; ?></td>
							<td class='text-right'><?php echo $total_nc; ?></td>
							<td><span class="label <?php echo $label_class_sri; ?>"><?php echo $estado_sri; ?><?php echo $mensaje_sri; ?></span></td>
							<td class='col-md-2'><span class="pull-right">

									<?php
									//PARA ENVIAR AL SRI
									if ($tipo_nc == "ELECTRÓNICA") {
										switch ($estado_sri) {
											case "PENDIENTE";
											case "DEVUELTA";
												if (getPermisos($con, $id_usuario, $ruc_empresa, 'notas_de_credito')['w'] == 1) {
									?>
													<a class='btn btn-success btn-xs' onclick="enviar_nc_sri('<?php echo $id_encabezado_nc; ?>','<?php echo $ruc_cliente; ?>');" title='Enviar al SRI'><i class="glyphicon glyphicon-send"></i></a>
												<?php
												}
												break;
										}
									}

									switch ($estado_sri) {
										case "ENVIANDO";
											if (getPermisos($con, $id_usuario, $ruc_empresa, 'notas_de_credito')['r'] == 1) {
												?>
												<b>Enviando al SRI... </b><a class='btn btn-default btn-xs' title='Cancelar envio' onclick="cancelar_envio_sri('<?php echo $id_encabezado_nc; ?>')">Cancelar </a>
											<?php
											}
											break;
									}


									switch ($estado_sri) {
										case "AUTORIZADO";
										case "ANULADA";
											if (getPermisos($con, $id_usuario, $ruc_empresa, 'notas_de_credito')['r'] == 1) {
											?>
												<a href="../ajax/imprime_documento.php?id_documento=<?php echo base64_encode($id_encabezado_nc) ?>&tipo_documento=nc&tipo_archivo=pdf" class='btn btn-default btn-xs' title='Ver' download target="_blank">Pdf</i> </a>
												<a href="../ajax/imprime_documento.php?id_documento=<?php echo base64_encode($id_encabezado_nc) ?>&tipo_documento=nc&tipo_archivo=xml" class='btn btn-default btn-xs' title='Ver' download target="_blank">Xml</i> </a>
											<?php
											}
											break;
									}

									//para eliminar
									if ($estado_sri == "PENDIENTE") {
										if (getPermisos($con, $id_usuario, $ruc_empresa, 'notas_de_credito')['d'] == 1) {
											?>
											<a class='btn btn-danger btn-xs' title='Eliminar nota de crédito' onclick="eliminar_nc('<?php echo $id_encabezado_nc; ?>')"><i class="glyphicon glyphicon-erase"></i> </a>
											<?php
										}
									}

									switch ($estado_sri) {
										case "PENDIENTE";
										case "DEVUELTA";
										case "AUTORIZADO";
											if (getPermisos($con, $id_usuario, $ruc_empresa, 'notas_de_credito')['r'] == 1) {
											?>
												<a class='btn btn-info btn-xs' title='Detalle factura' onclick="detalle_nc('<?php echo $id_encabezado_nc; ?>')" data-toggle="modal" data-target="#detalleDocumento"><i class="glyphicon glyphicon-list"></i></a>
											<?php
											}
											break;
									}
									if ($estado_sri == "AUTORIZADO") {
										if (getPermisos($con, $id_usuario, $ruc_empresa, 'notas_de_credito')['d'] == 1) {
											?>
											<a class="<?php echo $estado_mail_final; ?>" onclick="enviar_nc_mail('<?php echo $id_encabezado_nc; ?>');" title='Enviar por mail al cliente' data-toggle="modal" data-target="#EnviarDocumentosMail"><i class="glyphicon glyphicon-envelope"></i> </a>
											<?php
											if (mostrarBotonAnular($fecha_nc)) {
											?>
												<a class='btn btn-warning btn-xs' title='Anular nc' data-toggle="modal" data-target="#AnularDocumentosSri" onclick="anular_documento_en_sri('<?php echo $id_encabezado_nc; ?>')"><i class="glyphicon glyphicon-remove"></i> </a>
									<?php
											}
										}
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
?>