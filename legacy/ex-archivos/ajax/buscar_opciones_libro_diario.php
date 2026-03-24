<?php
include("../conexiones/conectalogin.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
ini_set('date.timezone', 'America/Guayaquil');
$fecha_registro = date("Y-m-d H:i:s");


if ($action == 'eliminar_asientos_bloque') {
	$codigo_bloque = mysqli_real_escape_string($con, (strip_tags($_GET['codigo_bloque'], ENT_QUOTES)));
	//$sql_registro = mysqli_query($con, "SELECT * FROM encabezado_diario WHERE codigo_unico_bloque='" . $codigo_bloque . "'");
	$update_encabezado = mysqli_query($con, "UPDATE encabezado_diario SET codigo_unico='', estado='Anulado_en_bloque', 
	id_usuario='" . $id_usuario . "', fecha_registro='" . $fecha_registro . "' WHERE codigo_unico_bloque='" . $codigo_bloque . "'");
	//$eliminar_asiento=eliminar_registro_asiento($con, $codigo_bloque);

	/* 	while ($row_registro = mysqli_fetch_array($sql_registro)) {
		$tipo_registro = $row_registro['tipo'];
		$numero_asiento = $row_registro['id_diario'];

		//$eliminar_detalle_diario=mysqli_query($con,"DELETE FROM detalle_diario_contable WHERE codigo_unico='".$row_registro['codigo_unico']."'");

		switch ($tipo_registro) {
			case "VENTAS":
				$update_encabezado_ventas = mysqli_query($con, "UPDATE encabezado_factura SET id_registro_contable='0' WHERE ruc_empresa='" . $ruc_empresa . "' and id_registro_contable='" . $numero_asiento . "'");
				break;
			case "RECIBOS":
				$update_encabezado_recibos_ventas = mysqli_query($con, "UPDATE encabezado_recibo SET id_registro_contable='0' WHERE ruc_empresa='" . $ruc_empresa . "' and id_registro_contable='" . $numero_asiento . "'");
				break;
			case "NC_VENTAS":
				$update_encabezado_nc_ventas = mysqli_query($con, "UPDATE encabezado_nc SET id_registro_contable='0' WHERE ruc_empresa='" . $ruc_empresa . "' and id_registro_contable='" . $numero_asiento . "'");
				break;
			case "RETENCIONES_VENTAS":
				$update_encabezado_ret_ventas = mysqli_query($con, "UPDATE encabezado_retencion_venta SET id_registro_contable='0' WHERE ruc_empresa='" . $ruc_empresa . "' and id_registro_contable='" . $numero_asiento . "'");
				break;
			case "RETENCIONES_COMPRAS":
				$update_encabezado_ventas = mysqli_query($con, "UPDATE encabezado_retencion SET id_registro_contable='0' WHERE ruc_empresa='" . $ruc_empresa . "' and id_registro_contable='" . $numero_asiento . "'");
				break;
			case "COMPRAS_SERVICIOS":
				$update_encabezado_ventas = mysqli_query($con, "UPDATE encabezado_compra SET id_registro_contable='0' WHERE ruc_empresa='" . $ruc_empresa . "' and id_registro_contable='" . $numero_asiento . "'");
				break;
			case "INGRESOS":
				$update_encabezado_ingresos = mysqli_query($con, "UPDATE ingresos_egresos SET codigo_contable='0' WHERE ruc_empresa='" . $ruc_empresa . "' and codigo_contable='" . $numero_asiento . "' and tipo_ing_egr='INGRESO'");
				break;
			case "EGRESOS":
				$update_encabezado_egresos = mysqli_query($con, "UPDATE ingresos_egresos SET codigo_contable='0' WHERE ruc_empresa='" . $ruc_empresa . "' and codigo_contable='" . $numero_asiento . "' and tipo_ing_egr='EGRESO'");
				break;
			case "ROL_PAGOS":
				$update_encabezado_roles = mysqli_query($con, "UPDATE rolespago SET id_registro_contable='0' WHERE id_empresa='" . $id_empresa . "' and id_registro_contable='" . $numero_asiento . "' ");
				break;
		}
	} */

	if ($update_encabezado) {
		echo "<script>$.notify('Asientos contables eliminados.','success');
				setTimeout(function (){location.reload()}, 1000);
					</script>";
	} else {
		echo "<script>$.notify('Lo siento algo ha salido mal intenta nuevamente.','error')</script>";
	}
}


if ($action == 'buscar_asientos_bloque') {
	// escaping, additionally removing everything that could be (html/javascript-) code
	$q = mysqli_real_escape_string($con, (strip_tags($_REQUEST['oa'], ENT_QUOTES)));
	//$ordenado = mysqli_real_escape_string($con,(strip_tags($_GET['ordenado'], ENT_QUOTES)));
	// $por = mysqli_real_escape_string($con,(strip_tags($_GET['por'], ENT_QUOTES)));
	$aColumns = array('fecha_registro', 'tipo', 'estado', 'fecha_asiento', 'concepto_general', 'codigo_unico', 'codigo_unico_bloque', 'id_diario'); //Columnas de busqueda
	$sTable = "encabezado_diario";
	$sWhere = "WHERE ruc_empresa = '" . $ruc_empresa . "' ";
	if ($_GET['oa'] != "") {
		$sWhere = "WHERE ( ruc_empresa = '" . $ruc_empresa . "' AND ";
		for ($i = 0; $i < count($aColumns); $i++) {
			$sWhere .= $aColumns[$i] . " LIKE '%" . $q . "%' AND ruc_empresa = '" . $ruc_empresa . "' OR ";
		}
		$sWhere = substr_replace($sWhere, "AND ruc_empresa = '" . $ruc_empresa . "' ", -3);
		$sWhere .= ')';
	}
	$sWhere .= " order by fecha_registro desc"; //  group by codigo_unico_bloque
	//group by enc_dia.numero_diario

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
	//DISTINCT codigo_unico_bloque, fecha_registro, codigo_unico, tipo, estado
	$sql = "SELECT * FROM  $sTable $sWhere LIMIT $offset, $per_page";
	$query = mysqli_query($con, $sql);
	//loop through fetched data
	if ($numrows > 0) {

?>
		<div class="panel panel-info">
			<div class="table-responsive">
				<table class="table">
					<tr class="info">
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("fecha_registro");'>Fecha registro</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("fecha_asiento");'>Fecha asiento</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("tipo");'>Tipo</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("id_diario");'>Asiento</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("codigo_unico_bloque");'>Código lote</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Estado</button></th>
						<th class='text-right'>Opciones</th>
					</tr>
					<?php

					while ($row = mysqli_fetch_array($query)) {
						$fecha_registro = date('d-m-Y', strtotime($row['fecha_registro']));
						$codigo_unico = $row['codigo_unico'];
						$codigo_unico_bloque = $row['codigo_unico_bloque'];
						$concepto_general = $row['tipo'];
						$numero_asiento = $row['id_diario'];
						$estado = $row['estado'];
						$fecha_asiento = date('d-m-Y', strtotime($row['fecha_asiento']));

					?>
						<tr>
							<td><?php echo $fecha_registro; ?></td>
							<td><?php echo $fecha_asiento; ?></td>
							<td><?php echo $concepto_general; ?></td>
							<td><?php echo $numero_asiento; ?></td>
							<td><?php echo $codigo_unico_bloque; ?></td>
							<td><?php echo $estado; ?></td>
							<td><span class="pull-right">
									<a href="#" class='btn btn-danger btn-xs' title='Anular diario' onclick="eliminar_asientos_bloque('<?php echo $codigo_unico_bloque; ?>');"><i class="glyphicon glyphicon-erase"></i></a>
						</tr>
					<?php
					}
					?>
					<tr>
						<td colspan="7"><span class="pull-right">
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
	function eliminar_registro_asiento($con, $codigo_bloque){
		//$eliminar_encabezado_diario=mysqli_query($con,"DELETE FROM encabezado_diario WHERE codigo_unico='".$codigo_unico."'");
		$eliminar_detalle_diario=mysqli_query($con,"DELETE FROM detalle_diario_contable WHERE codigo_unico_bloque='".$codigo_bloque."'");
	return $eliminar_detalle_diario;
	}
	*/
?>