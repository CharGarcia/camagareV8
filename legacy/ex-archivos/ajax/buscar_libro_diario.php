<?php
include("../conexiones/conectalogin.php");
include("../validadores/generador_codigo_unico.php");
include("../helpers/helpers.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_empresa = $_SESSION['id_empresa'];
$id_usuario = $_SESSION['id_usuario'];
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
ini_set('date.timezone', 'America/Guayaquil');
$fecha_registro = date("Y-m-d H:i:s");


if ($action == 'reporte_libro_diario') {
	$desde = mysqli_real_escape_string($con, (strip_tags($_POST['fecha_desde'], ENT_QUOTES)));
	$hasta = mysqli_real_escape_string($con, (strip_tags($_POST['fecha_hasta'], ENT_QUOTES)));
	$sql_diario   = mysqli_query($con, "SELECT * FROM encabezado_diario WHERE ruc_empresa='" . $ruc_empresa . "' and  DATE_FORMAT(fecha_asiento, '%Y/%m/%d') 
	between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' 
	and (estado ='ok' or estado ='editado')");

	if (mysqli_num_rows($sql_diario) > 0) {

?>
		<div class="panel panel-info">
			<div class="table-responsive">
				<table class="table">
					<thead>
						<tr class="info">
							<th style="padding: 0px;">Fecha</th>
							<th style="padding: 0px;">Asiento</th>
							<th style="padding: 0px;">Tipo</th>
							<th style="padding: 0px;" class="col-md-6">
								<table>
									<tr>
										<th class="col-md-2">Código</th>
										<th class="col-md-10">Detalle</th>
										<th class="col-md-2">Dede</th>
										<th class="col-md-2">Haber</th>
									</tr>
								</table>
							</th>
						</tr>
					</thead>
					<?php
					$suma_debe = 0;
					$suma_haber = 0;
					while ($row = mysqli_fetch_array($sql_diario)) {
						$fecha_asiento = date('d-m-Y', strtotime($row['fecha_asiento']));
						$numero_asiento = $row['id_diario'];
						$concepto_general = $row['concepto_general'];
						$tipo = $row['tipo'];
						$codigo_unico = $row['codigo_unico'];
						$sql_detalle = mysqli_query($con, "SELECT * FROM detalle_diario_contable as det INNER JOIN plan_cuentas as plan
					ON plan.id_cuenta = det.id_cuenta WHERE det.ruc_empresa='" . $ruc_empresa . "' and det.codigo_unico ='" . $codigo_unico . "'");
					?>
						<tr>
							<td class="col-md-2"><?php echo $fecha_asiento; ?></td>
							<td><?php echo $numero_asiento; ?></td>
							<td><?php echo $tipo; ?></td>
							<td class="col-md-6">
								<table>
									<?php
									foreach ($sql_detalle as $row_detalle) {
									?>
										<tr>
											<td class="col-md-2"><?php echo strtoupper($row_detalle['codigo_cuenta']); ?></td>
											<td class="col-md-10"><?php echo strtoupper($row_detalle['nombre_cuenta']); ?></td>
											<td class="col-md-2 text-right"><?php echo $row_detalle['debe']; ?></td>
											<td class="col-md-2 text-right"><?php echo $row_detalle['haber']; ?></td>
										</tr>

									<?php
										$suma_debe += $row_detalle['debe'];
										$suma_haber += $row_detalle['haber'];
									}

									?>
									<tr>
										<td colspan="2"><?php echo "<b>P/R: </b>" . strtoupper($concepto_general); ?></td>
									</tr>
								</table>
							</td>
						</tr>
					<?php
					}
					?>
					<tr class="info">
						<th style="padding: 0px;" colspan="3"></th>
						<th style="padding: 0px;">
							<table>
								<tr>
									<th class="col-md-10">Sumas</th>
									<th class="col-md-2 text-right"><?php echo formatMoney($suma_debe, 2); ?></th>
									<th class="col-md-2 text-right"><?php echo formatMoney($suma_haber, 2); ?></th>
								</tr>
							</table>
						</th>
					</tr>
				</table>
			</div>
		</div>
	<?php
	} else {
		echo "No hay registros para mostrar";
	}
}

//par buscar los asientos contables y listarlos todos
if ($action == 'libro_diario') {
	$q = mysqli_real_escape_string($con, (strip_tags($_REQUEST['q'], ENT_QUOTES)));
	$ordenado = mysqli_real_escape_string($con, (strip_tags($_GET['ordenado'], ENT_QUOTES)));
	$por = mysqli_real_escape_string($con, (strip_tags($_GET['por'], ENT_QUOTES)));
	$tipo_asiento = mysqli_real_escape_string($con, (strip_tags($_GET['tipo_asiento'], ENT_QUOTES)));
	$mes_asiento = mysqli_real_escape_string($con, (strip_tags($_GET['mes_asiento'], ENT_QUOTES)));
	$anio_asiento = mysqli_real_escape_string($con, (strip_tags($_GET['anio_asiento'], ENT_QUOTES)));

	if (empty($tipo_asiento)) {
		$opciones_tipo_asiento = "";
	} else {
		$opciones_tipo_asiento = " and enc_dia.tipo = '" . $tipo_asiento . "' ";
	}

	if (empty($mes_asiento)) {
		$opciones_mes_asiento = "";
	} else {
		$opciones_mes_asiento = " and month(enc_dia.fecha_asiento) = '" . $mes_asiento . "' ";
	}
	if (empty($anio_asiento)) {
		$opciones_anio_asiento = "";
	} else {
		$opciones_anio_asiento = " and year(enc_dia.fecha_asiento) = '" . $anio_asiento . "' ";
	}
	$aColumns = array('fecha_asiento', 'id_diario', 'concepto_general', 'tipo'); //Columnas de busqueda
	$sTable = "encabezado_diario as enc_dia ";
	$sWhere = "WHERE enc_dia.ruc_empresa = '" . $ruc_empresa . "' $opciones_tipo_asiento $opciones_anio_asiento $opciones_mes_asiento and enc_dia.estado !='Anulado'";
	$text_buscar = explode(' ', $q);
	$like = "";
	for ($i = 0; $i < count($text_buscar); $i++) {
		$like .= "%" . $text_buscar[$i];
	}
	if ($_GET['q'] != "") {
		$sWhere = "WHERE enc_dia.ruc_empresa = '" . $ruc_empresa . "' $opciones_tipo_asiento $opciones_anio_asiento $opciones_mes_asiento and enc_dia.estado !='Anulado' AND ";

		for ($i = 0; $i < count($aColumns); $i++) {
			$sWhere .= $aColumns[$i] . " LIKE '" . $like . "%' AND enc_dia.ruc_empresa = '" . $ruc_empresa . "' $opciones_tipo_asiento $opciones_anio_asiento $opciones_mes_asiento and enc_dia.estado !='Anulado' OR ";
		}
		$sWhere = substr_replace($sWhere, "AND enc_dia.ruc_empresa = '" . $ruc_empresa . "' $opciones_tipo_asiento $opciones_anio_asiento $opciones_mes_asiento and enc_dia.estado !='Anulado'", -3);
	}
	$sWhere .= " order by $ordenado $por";

	include("../ajax/pagination.php"); //include pagination file
	//pagination variables
	$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
	$per_page = 20; //how much records you want to show
	$adjacents  = 4; //gap between pages after number of adjacents
	$offset = ($page - 1) * $per_page;
	//Count the total number of row in your table*/
	$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
	$row = mysqli_fetch_array($count_query);
	$numrows = $row['numrows'];
	$total_pages = ceil($numrows / $per_page);
	$reload = '../libro_diario.php';
	//main query to fetch the data
	$sql = "SELECT * FROM  $sTable $sWhere LIMIT $offset, $per_page";
	$query = mysqli_query($con, $sql);
	//loop through fetched data
	if ($numrows > 0) {

	?>
		<div class="panel panel-info">
			<div class="table-responsive">
				<table class="table">
					<tr class="info">
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("fecha_asiento");'>Fecha</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("id_diario");'>Asiento</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("tipo");'>Tipo</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("concepto_general");'>Concepto</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("estado");'>Estado</button></th>
						<th class='text-right'>Opciones</th>

					</tr>
					<?php
					while ($row = mysqli_fetch_array($query)) {
						$id_diario = $row['id_diario'];
						$id_documento = $row['id_documento'];
						$codigo_unico = $row['codigo_unico'];
						$codigo_unico_bloque = $row['codigo_unico_bloque'];
						$fecha_asiento = date('d-m-Y', strtotime($row['fecha_asiento']));
						$numero_asiento = $row['id_diario'];
						$concepto_general = $row['concepto_general'];
						$estado = $row['estado'];
						$tipo = $row['tipo'];
						switch ($estado) {
							case "Editado":
								$label_class = 'label-warning';
								break;
							case "Anulado":
								$label_class = 'label-danger';
								break;
							case "Anulado_en_bloque":
								$label_class = 'label-danger';
								break;
							case "ok":
								$label_class = 'label-success';
								break;
						}

						switch ($tipo) {
							case "RETENCIONES_COMPRAS":
								$link_tipo = "<a href='../modulos/retenciones_compras.php' target='_blank'> RETENCIONES_COMPRAS</a>";
								break;
							case "RETENCIONES_VENTAS":
								$link_tipo = "<a href='../modulos/retenciones_ventas.php' target='_blank'> RETENCIONES_VENTAS</a>";
								break;
							case "VENTAS":
								$link_tipo = "<a href='../modulos/facturas.php' target='_blank'> VENTAS</a>";
								break;
							case "INGRESOS":
								$link_tipo = "<a href='../modulos/ingresos.php' target='_blank'> INGRESOS</a>";
								break;
							case "EGRESOS":
								$link_tipo = "<a href='../modulos/egresos.php' target='_blank'> EGRESOS</a>";
								break;
							case "RECIBOS":
								$link_tipo = "<a href='../modulos/recibo_venta.php' target='_blank'> RECIBOS</a>";
								break;
							case "NC_VENTAS":
								$link_tipo = "<a href='../modulos/notas_de_credito.php' target='_blank'> NC_VENTAS</a>";
								break;
							case "COMPRAS_SERVICIOS":
								$link_tipo = "<a href='../modulos/compras.php' target='_blank'> COMPRAS_SERVICIOS</a>";
								break;
							default;
								$link_tipo = $tipo;
						}
					?>
						<input type="hidden" value="<?php echo $numero_asiento; ?>" id="id_diario<?php echo $id_diario; ?>">
						<input type="hidden" value="<?php echo $concepto_general; ?>" id="mod_concepto_general<?php echo $id_diario; ?>">
						<input type="hidden" value="<?php echo $fecha_asiento; ?>" id="mod_fecha_asiento<?php echo $id_diario; ?>">
						<input type="hidden" value="<?php echo $codigo_unico; ?>" id="mod_codigo_unico<?php echo $id_diario; ?>">
						<input type="hidden" value="<?php echo $codigo_unico_bloque; ?>" id="mod_codigo_unico_bloque<?php echo $id_diario; ?>">
						<input type="hidden" value="<?php echo $id_documento; ?>" id="mod_id_documento<?php echo $id_diario; ?>">
						<input type="hidden" value="<?php echo $tipo; ?>" id="mod_tipo<?php echo $id_diario; ?>">
						<tr>
							<td class='col-md-1'><?php echo $fecha_asiento; ?></td>
							<td><?php echo $numero_asiento; ?></td>
							<td><?php echo $link_tipo; ?></td>
							<td><?php echo $concepto_general; ?></td>
							<td><span class="label <?php echo $label_class; ?>"><?php echo strtoupper($estado); ?></span></td>
							<td class='col-md-3'><span class="pull-right">
									<?php
									if ($estado != 'Anulado') {
									?>
										<a href="../pdf/pdf_diario_contable.php?action=diario_contable&id_diario=<?php echo $id_diario ?>" class='btn btn-default btn-xs' title='Pdf' target="_blank"><img src="../image/pdf.ico" width="18" height="18"></a>
										<a href="../excel/reporte_diario_contable_excel.php?action=diario_contable&id_diario=<?php echo $id_diario ?>" class='btn btn-success btn-xs' title='Excel' target="_blank"><img src="../image/excel.ico" width="18" height="18"></a>
										<a class='btn btn-info btn-xs' title='Detalle asiento' onclick="detalle_asiento('<?php echo $id_diario; ?>');" data-toggle="modal" data-target="#detalleDocumentoContable"><i class="glyphicon glyphicon-list"></i></a>
										<a class='btn btn-info btn-xs' title='Editar asiento' onclick="obtener_datos('<?php echo $id_diario; ?>');" data-toggle="modal" data-target="#NuevoDiarioContable"><i class="glyphicon glyphicon-refresh"></i></a>
										<?php
										if ($tipo == 'DIARIO') {
										?>
											<a class='btn btn-info btn-xs' title='Duplicar asiento' onclick="duplicar_asiento('<?php echo $id_diario; ?>');" data-toggle="modal" data-target="#NuevoDiarioContable"><i class="glyphicon glyphicon-duplicate"></i></a>
									<?php
										}
									}
									?>
									<a class='btn btn-danger btn-xs' title='Eliminar asiento' onclick="eliminar_asiento('<?php echo $id_diario; ?>');"><i class="glyphicon glyphicon-erase"></i></a>

						</tr>
					<?php
					}
					?>
					<tr>
						<td colspan="8"><span class="pull-right">
								<?php
								echo paginate($reload, $page, $total_pages, $adjacents);
								?>
							</span></td>
					</tr>
				</table>
			</div>
		</div>
<?php
	}
}

/* 
if ($action == 'detalle_asientos') {
	$d = mysqli_real_escape_string($con, (strip_tags($_REQUEST['d'], ENT_QUOTES)));
	$aColumns = array('det_dia.detalle_item', 'plan.codigo_cuenta', 'plan.nombre_cuenta'); //Columnas de busqueda
	$sTable = "detalle_diario_contable as det_dia INNER JOIN plan_cuentas as plan ON plan.id_cuenta=det_dia.id_cuenta ";
	$sWhere = "WHERE det_dia.ruc_empresa = '" . $ruc_empresa . "' ";
	$text_buscar = explode(' ', $d);
	$like = "";
	for ($i = 0; $i < count($text_buscar); $i++) {
		$like .= "%" . $text_buscar[$i];
	}
	if ($_GET['d'] != "") {
		$sWhere = "WHERE det_dia.ruc_empresa = '" . $ruc_empresa . "' AND ";

		for ($i = 0; $i < count($aColumns); $i++) {
			$sWhere .= $aColumns[$i] . " LIKE '%" . $like . "%' AND det_dia.ruc_empresa = '" . $ruc_empresa . "' OR ";
		}
		$sWhere = substr_replace($sWhere, "AND det_dia.ruc_empresa = '" . $ruc_empresa . "' ", -3);
	}
	$sWhere .= " order by id_detalle_cuenta desc "; //$ordenado $por

	include("../ajax/pagination.php"); //include pagination file
	//pagination variables
	$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
	$per_page = 20; //how much records you want to show
	$adjacents  = 4; //gap between pages after number of adjacents
	$offset = ($page - 1) * $per_page;
	
	$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
	$row = mysqli_fetch_array($count_query);
	$numrows = $row['numrows'];
	$total_pages = ceil($numrows / $per_page);
	$reload = '../libro_diario.php';
	//main query to fetch the data
	$sql = "SELECT det_dia.detalle_item as detalle_item, det_dia.codigo_unico as codigo_unico, plan.codigo_cuenta as codigo_cuenta, plan.nombre_cuenta as nombre_cuenta, det_dia.debe as debe, det_dia.haber as haber FROM  $sTable $sWhere LIMIT $offset, $per_page";
	$query = mysqli_query($con, $sql);
	//loop through fetched data
	if ($numrows > 0) {
	?>
		<div class="panel panel-info">
			<div class="table-responsive">
				<table class="table">
					<tr class="info">
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("detalle_item");'>Detalle</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("id_diario");'>Asiento</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("codigo_cuenta");'>Código</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("nombre_cuenta");'>Cuenta</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("debe");'>Debe</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("haber");'>Haber</button></th>
					</tr>
					<?php
					while ($row = mysqli_fetch_array($query)) {
						$codigo_unico = $row['codigo_unico'];
						$detalle_item = $row['detalle_item'];
						$codigo_cuenta = $row['codigo_cuenta'];
						$nombre_cuenta = $row['nombre_cuenta'];
						$debe = $row['debe'];
						$haber = $row['haber'];
						$query_numero_asiento = mysqli_query($con, "SELECT * FROM encabezado_diario WHERE codigo_unico='" . $codigo_unico . "' and ruc_empresa='" . $ruc_empresa . "' ");
						$row_asiento = mysqli_fetch_array($query_numero_asiento);
						$numero_asiento = $row_asiento['id_diario'];
					?>
						<tr>
							<td><?php echo $detalle_item; ?></td>
							<td><?php echo $numero_asiento; ?></td>
							<td><?php echo $codigo_cuenta; ?></td>
							<td><?php echo $nombre_cuenta; ?></td>
							<td><?php echo $debe; ?></td>
							<td><?php echo $haber; ?></td>
						</tr>
					<?php
					}
					?>
					<tr>
						<td colspan="8"><span class="pull-right">
								<?php
								echo paginate($reload, $page, $total_pages, $adjacents);
								?>
							</span></td>
					</tr>
				</table>
			</div>
		</div>
<?php
	}
} */

//eliminar numero de asiento de los registros de facturas, retenciones y compras, ingresos y egresos, roles
if ($action == 'eliminar_asiento') {
	$id_diario = mysqli_real_escape_string($con, (strip_tags($_GET['id_diario'], ENT_QUOTES)));
	$sql_registro = mysqli_query($con, "SELECT * FROM encabezado_diario WHERE id_diario='" . $id_diario . "'");
	$row_registro = mysqli_fetch_array($sql_registro);
	$tipo_registro = strtoupper($row_registro['tipo']);
	$numero_asiento = $row_registro['id_diario'];
	$codigo_unico = isset($row_registro['codigo_unico']) ? $row_registro['codigo_unico'] : "";
	$codigo_bloque = $row_registro['codigo_unico_bloque'];
	//ojo al momento de editar o eliminar se dispara una acccion en la base de datos y elimina el detalle y actualiza el codigo contable en cada documento
	$update_encabezado = mysqli_query($con, "UPDATE encabezado_diario SET codigo_unico='' , estado='Anulado', id_usuario='" . $id_usuario . "', fecha_registro='" . $fecha_registro . "' WHERE id_diario='" . $id_diario . "'");

	//para eliminar el detalle del asiento
	if (!empty($codigo_unico)) {
		$delete_detalle_diario = mysqli_query($con, "DELETE FROM detalle_diario_contable 
		WHERE codigo_unico = '" . $codigo_unico . "' and codigo_unico_bloque = '" . $codigo_unico_bloque . "' ");
	} else {
		echo "<script>$.notify('El asiento no fue eliminado.','error')</script>";
	}

	if ($update_encabezado) {
		echo "<script>$.notify('Asiento contable anulado.','success');
					</script>";
	} else {
		echo "<script>$.notify('Lo siento algo ha salido mal intenta nuevamente.','error')</script>";
	}
}
?>