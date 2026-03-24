<?php
include("../validadores/generador_codigo_unico.php");
include("../conexiones/conectalogin.php");
include("../clases/asientos_contables.php");
//include("../helpers/helpers.php");
session_start();
$con = conenta_login();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_empresa = $_SESSION['id_empresa'];
$id_usuario = $_SESSION['id_usuario'];
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
ini_set('date.timezone', 'America/Guayaquil');
$fecha_registro = date("Y-m-d H:i:s");


if ($action == 'balance_inicial') {
	$q = mysqli_real_escape_string($con, (strip_tags($_REQUEST['q'], ENT_QUOTES)));
	$ordenado = mysqli_real_escape_string($con, (strip_tags($_GET['ordenado'], ENT_QUOTES)));
	$por = mysqli_real_escape_string($con, (strip_tags($_GET['por'], ENT_QUOTES)));
	$aColumns = array('fecha_asiento', 'id_diario', 'concepto_general', 'tipo'); //Columnas de busqueda
	$sTable = "encabezado_diario as enc_dia ";
	$sWhere = "WHERE enc_dia.ruc_empresa = '" . $ruc_empresa . "' and enc_dia.tipo ='BALANCE_INICIAL' ";
	$text_buscar = explode(' ', $q);
	$like = "";
	for ($i = 0; $i < count($text_buscar); $i++) {
		$like .= "%" . $text_buscar[$i];
	}
	if ($_GET['q'] != "") {
		$sWhere = "WHERE enc_dia.ruc_empresa = '" . $ruc_empresa . "' and enc_dia.tipo ='BALANCE_INICIAL' AND ";

		for ($i = 0; $i < count($aColumns); $i++) {
			$sWhere .= $aColumns[$i] . " LIKE '" . $like . "%' AND enc_dia.ruc_empresa = '" . $ruc_empresa . "' and enc_dia.tipo ='BALANCE_INICIAL' OR ";
		}
		$sWhere = substr_replace($sWhere, "AND enc_dia.ruc_empresa = '" . $ruc_empresa . "' and enc_dia.tipo ='BALANCE_INICIAL' ", -3);
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
					?>
						<input type="hidden" value="<?php echo $numero_asiento; ?>" id="id_diario<?php echo $id_diario; ?>">
						<input type="hidden" value="<?php echo $concepto_general; ?>" id="mod_concepto_general<?php echo $id_diario; ?>">
						<input type="hidden" value="<?php echo $fecha_asiento; ?>" id="mod_fecha_asiento<?php echo $id_diario; ?>">
						<input type="hidden" value="<?php echo $codigo_unico; ?>" id="mod_codigo_unico<?php echo $id_diario; ?>">
						<input type="hidden" value="<?php echo $id_documento; ?>" id="mod_id_documento<?php echo $id_diario; ?>">
						<input type="hidden" value="<?php echo $tipo; ?>" id="mod_tipo<?php echo $id_diario; ?>">
						<tr>
							<td class='col-md-1'><?php echo $fecha_asiento; ?></td>
							<td><?php echo $numero_asiento; ?></td>
							<td><?php echo $tipo; ?></td>
							<td><?php echo $concepto_general; ?></td>
							<td><span class="label <?php echo $label_class; ?>"><?php echo strtoupper($estado); ?></span></td>
							<td class='col-md-3'><span class="pull-right">
									<a href="../pdf/pdf_diario_contable.php?action=diario_contable&id_diario=<?php echo $id_diario ?>" class='btn btn-default btn-xs' title='Pdf' target="_blank"><img src="../image/pdf.ico" width="18" height="18"></a>
									<a href="../excel/reporte_diario_contable_excel.php?action=diario_contable&id_diario=<?php echo $id_diario ?>" class='btn btn-success btn-xs' title='Excel' target="_blank"><img src="../image/excel.ico" width="18" height="18"></a>
									<a class='btn btn-info btn-xs' title='Detalle asiento' onclick="detalle_asiento('<?php echo $id_diario; ?>');" data-toggle="modal" data-target="#detalleDocumentoContable"><i class="glyphicon glyphicon-list"></i></a>
									<a class='btn btn-info btn-xs' title='Editar asiento' onclick="obtener_datos('<?php echo $id_diario; ?>');" data-toggle="modal" data-target="#NuevoDiarioContable"><i class="glyphicon glyphicon-refresh"></i></a>
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

if ($action == 'generar_balance_inicial') {
	$asiento_contable = new asientos_contables();
	$ano_anterior = mysqli_real_escape_string($con, (strip_tags($_REQUEST['ano_anterior'], ENT_QUOTES)));
	$ano_siguiente = mysqli_real_escape_string($con, (strip_tags($_REQUEST['ano_siguiente'], ENT_QUOTES)));
	generar_asiento_inicial($con, $ruc_empresa, $id_usuario, $ano_anterior, $ano_siguiente);

	$concepto_diario = 'BALANCE INICIAL ' . $ano_siguiente;
	$fecha_diario = $ano_siguiente . '/01/01';
	$sql_diario_temporal = mysqli_query($con, "SELECT * from detalle_diario_tmp where id_usuario = '" . $id_usuario . "' and ruc_empresa = '" . $ruc_empresa . "'");
	$count = mysqli_num_rows($sql_diario_temporal);
	if ($count == 0) {
	?>
		<div class="alert alert-danger" role="alert">
			<button type="button" class="close" data-dismiss="alert">&times;</button>
			<strong>Error!</strong>
			<?php
			echo "No hay detalle de cuentas para generar el asiento";
			?>
		</div>
		<?php
	} else {
		$guarda_asiento = $asiento_contable->guarda_asiento($con, $fecha_diario, $concepto_diario, 'BALANCE_INICIAL', '0', $ruc_empresa, $id_usuario, '0');

		if ($guarda_asiento == '1') {
		?>
			<div class="alert alert-success" role="alert">
				<button type="button" class="close" data-dismiss="alert">&times;</button>
				<strong>Error!</strong>
				<?php
				echo "Asiento guardado con éxito.";
				?>
			</div>
		<?php
		}

		if ($guarda_asiento == '2') {
		?>
			<div class="alert alert-danger" role="alert">
				<button type="button" class="close" data-dismiss="alert">&times;</button>
				<strong>Error!</strong>
				<?php
				echo "El balance inicial ya esta registrado.";
				?>
			</div>
		<?php
		}

		if ($guarda_asiento == '3') {
		?>
			<div class="alert alert-danger" role="alert">
				<button type="button" class="close" data-dismiss="alert">&times;</button>
				<strong>Error!</strong>
				<?php
				echo "Uno o mas detalles del asiento ya estan registrados en otros asientos.";
				?>
			</div>
<?php

		}
	}
}



function generar_asiento_inicial($con, $ruc_empresa, $id_usuario, $ano_anterior, $ano_siguiente)
{
	$detalle = 'BALANCE INICIAL ' . $ano_siguiente;
	$desde = $ano_anterior . '/01/01';
	$hasta = $ano_anterior . '/12/31';
	$sql_delete = mysqli_query($con, "DELETE FROM detalle_diario_tmp WHERE ruc_empresa= '" . $ruc_empresa . "' ");

	$sql_detalle_diario = mysqli_query($con, "INSERT INTO detalle_diario_tmp (id_detalle_cuenta, ruc_empresa, id_cuenta, codigo_cuenta, cuenta, debe, haber, detalle_item, id_usuario) 
			SELECT null, '" . $ruc_empresa . "', plan.id_cuenta, plan.codigo_cuenta, plan.nombre_cuenta, round(sum(det_dia.debe),2), round(sum(det_dia.haber),2), '" . $detalle . "', '" . $id_usuario . "' 
			FROM detalle_diario_contable as det_dia 
			INNER JOIN encabezado_diario as enc_dia 
			ON enc_dia.codigo_unico=det_dia.codigo_unico 
			INNER JOIN plan_cuentas as plan ON plan.id_cuenta=det_dia.id_cuenta 
			WHERE plan.ruc_empresa = '" . $ruc_empresa . "' 
			and enc_dia.ruc_empresa = '" . $ruc_empresa . "' 
			and det_dia.ruc_empresa = '" . $ruc_empresa . "' 
			and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') 
			between '" . date("Y/m/d", strtotime($desde)) . "' 
			and '" . date("Y/m/d", strtotime($hasta)) . "' 
			and mid(plan.codigo_cuenta,1,1) between '1' and '3' 
			and enc_dia.estado !='ANULADO' group by plan.id_cuenta order by plan.codigo_cuenta asc");

	$sql_delete_saldos_cero = mysqli_query($con, "DELETE FROM detalle_diario_tmp WHERE ruc_empresa= '" . $ruc_empresa . "' and (debe - haber) = 0");

	$sql_update_diario = mysqli_query($con, "UPDATE detalle_diario_tmp SET debe = debe-haber, haber ='' WHERE debe>haber ");
	$sql_update_diario = mysqli_query($con, "UPDATE detalle_diario_tmp SET haber = haber-debe, debe ='' WHERE haber>debe ");
}
?>