<?php
/* Connect To Database*/
include("../conexiones/conectalogin.php");
include_once("../helpers/helpers.php");

$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
$fecha_registro = date("Y-m-d H:i:s");
ini_set('date.timezone', 'America/Guayaquil');
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

//PARA BUSCAR LAS COMPRAS

if ($action == 'buscar_adquisiciones') {
	$q = mysqli_real_escape_string($con, (strip_tags($_REQUEST['q'], ENT_QUOTES)));
	$aColumns = array('fecha_compra', 'numero_documento', 'razon_social', 'nombre_comercial', 'comprobante'); //Columnas de busqueda
	$sTable = "encabezado_compra as ec 
	INNER JOIN proveedores as pro ON pro.id_proveedor=ec.id_proveedor 
	INNER JOIN comprobantes_autorizados as com_aut ON com_aut.id_comprobante=ec.id_comprobante ";
	$sWhere = "WHERE ec.ruc_empresa = '" . $ruc_empresa . "' and ec.id_comprobante !=4 ";
	$text_buscar = explode(' ', $q);
	$like = "";
	for ($i = 0; $i < count($text_buscar); $i++) {
		$like .= "%" . $text_buscar[$i];
	}
	if ($_GET['q'] != "") {
		$sWhere = "WHERE (ec.ruc_empresa = '" . $ruc_empresa . "' and ec.id_comprobante !=4 AND ";

		for ($i = 0; $i < count($aColumns); $i++) {
			$sWhere .= $aColumns[$i] . " LIKE '%" . $like . "%' AND ec.ruc_empresa = '" . $ruc_empresa . "' and ec.id_comprobante !=4 OR ";
		}

		$sWhere = substr_replace($sWhere, "AND ec.ruc_empresa = '" . $ruc_empresa . "' and ec.id_comprobante !=4 ", -3);
		$sWhere .= ')';
	}
	$sWhere .= " order by ec.fecha_compra desc"; //group by det_egr.codigo_documento_cv
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
	$reload = '';
	//main query to fetch the data
	$sql = "SELECT ec.fecha_compra as fecha_compra, ec.id_encabezado_compra as id_encabezado_compra,
	 ec.id_proveedor as id_proveedor, pro.razon_social as razon_social,
	 ec.numero_documento as numero_documento, ec.codigo_documento as codigo_documento,
	 ec.total_compra as total_compra, com_aut.comprobante as comprobante, 
	 (select sum(valor_ing_egr) from detalle_ingresos_egresos where codigo_documento_cv=ec.codigo_documento group by codigo_documento_cv ) as abonos,
	 (select sum(total_retencion) from encabezado_retencion where numero_comprobante=ec.numero_documento and ruc_empresa = ec.ruc_empresa and id_proveedor=ec.id_proveedor group by numero_comprobante) as retencion  
	 FROM  $sTable $sWhere LIMIT $offset,$per_page";
	$query = mysqli_query($con, $sql);
	//loop through fetched data

	if ($numrows > 0) {
?>
		<div class="panel panel-info">
			<div class="table-responsive">
				<table class="table table-hover">
					<tr class="info">
						<th>Fecha</th>
						<th>Proveedor</th>
						<th>Comprobante</th>
						<th>Número</th>
						<th>Total</th>
						<th>Pagos</th>
						<th>Retención</th>
						<th>Saldo</th>
						<th class='text-center'>Nota</th>
						<?php
						if (getPermisos($con, $id_usuario, $ruc_empresa, 'notificaciones_adquisiciones')['u'] == 1) {
						?>
							<th class='text-right'>Opciones</th>
						<?php
						}
						?>
						<input type="hidden" value="<?php echo $page; ?>" id="pagina">
					</tr>
					<?php

					while ($row = mysqli_fetch_array($query)) {
						$id_encabezado_compra = $row['id_encabezado_compra'];
						$fecha_compra = $row['fecha_compra'];
						$id_proveedor = $row['id_proveedor'];
						$proveedor = $row['razon_social'];
						$numero_documento = $row['numero_documento'];
						$codigo_documento = $row['codigo_documento'];
						$nombre_comprobante = $row['comprobante'];

						$detalle_observaciones = mysqli_query($con, "SELECT count(status) as observaciones FROM notificaciones_adquisiciones WHERE codigo_documento= '" . $codigo_documento . "' and status = '1'  group by codigo_documento");
						$row_observaciones = mysqli_fetch_array($detalle_observaciones);
						$total_observaciones = isset($row_observaciones['observaciones']) ? $row_observaciones['observaciones'] : 0;
					?>
						<tr>
							<td><?php echo date("d/m/Y", strtotime($fecha_compra)); ?></td>
							<td class='col-md-4'><?php echo strtoupper($proveedor); ?></td>
							<td><?php echo $nombre_comprobante; ?></td>
							<td><?php echo $numero_documento; ?></td>
							<td class='text-right'><?php echo number_format($row['total_compra'], 2, '.', ''); ?></td>
							<td class='text-right'><?php echo number_format($row['abonos'], 2, '.', ''); ?></td>
							<td class='text-right'><?php echo number_format($row['retencion'], 2, '.', ''); ?></td>
							<td class='text-right'><?php echo number_format($row['total_compra'] - $row['abonos'] - $row['retencion'], 2, '.', ''); ?></td>
							<td class='text-center'><?php echo $total_observaciones > 0 ? '<span class="label label-warning">En revisión</span>' : '' ?></td>
							<?php
							if (getPermisos($con, $id_usuario, $ruc_empresa, 'notificaciones_adquisiciones')['u'] == 1) {
							?>
								<td class='col-md-2'><span class="pull-right">
										<a href="#" class="btn btn-info btn-xs" onclick="detalle_notificaciones_adquisiciones('<?php echo $codigo_documento; ?>')" title="Detalle documento y observaciones" data-toggle="modal" data-target="#detalleDocumento"><i class="glyphicon glyphicon-list-alt"></i></a>
									</span></td>
							<?php
							}
							?>
						</tr>
					<?php
						//}
					}
					?>
					<tr>
						<td colspan="10"><span class="pull-right">
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