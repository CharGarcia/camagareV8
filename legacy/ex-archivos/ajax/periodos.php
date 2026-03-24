<?php
require_once("../conexiones/conectalogin.php");
require_once("../helpers/helpers.php");
include("../ajax/pagination.php"); //include pagination file
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
ini_set('date.timezone', 'America/Guayaquil');
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

if ($action == 'buscar_periodos') {
	// escaping, additionally removing everything that could be (html/javascript-) code
	$q = mysqli_real_escape_string($con, (strip_tags($_REQUEST['q'], ENT_QUOTES)));
	$aColumns = array('mes_periodo', 'anio_periodo'); //Columnas de busqueda
	$sTable = "periodo_contable";
	$sWhere = "WHERE ruc_empresa ='" .  $ruc_empresa . " '";
	if ($_GET['q'] != "") {
		$sWhere = "WHERE (ruc_empresa ='" .  $ruc_empresa . " ' AND ";
		for ($i = 0; $i < count($aColumns); $i++) {
			$sWhere .= $aColumns[$i] . " LIKE '%" . $q . "%' OR ";
		}
		$sWhere = substr_replace($sWhere, "AND ruc_empresa = '" .  $ruc_empresa . " '", -3);
		$sWhere .= ')';
	}
	$sWhere .= " order by anio_periodo desc, mes_periodo desc";

	//pagination variables
	$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
	$per_page = 12; //how much records you want to show
	$adjacents  = 4; //gap between pages after number of adjacents
	$offset = ($page - 1) * $per_page;
	//Count the total number of row in your table*/
	$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable  $sWhere");
	$row = mysqli_fetch_array($count_query);
	$numrows = $row['numrows'];
	$total_pages = ceil($numrows / $per_page);
	$reload = '../periodos.php';
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
						<td>Mes</td>
						<td>Año</td>
						<td>Status</td>
						<td class='text-right'>Opciones</td>

					</tr>
					<?php
					while ($p = mysqli_fetch_assoc($query)) {
					?>
						<tr>
							<td> <?php foreach (Meses() as $mes) {
										if ($p['mes_periodo'] == $mes['codigo']) {
											echo ucwords($mes['nombre']);
										}
									}
									?>
							</td>
							<td> <?php echo $p['anio_periodo']; ?> </td>
							<td><?php echo $p['status'] == 1 ? "<span class='label label-success'>Abierto</span>" : "<span class='label label-danger'>Cerrado</span>"; ?></td>
							<td class='text-right'>
								<?php
								if (getPermisos($con, $id_usuario, $ruc_empresa, 'periodos')['u'] == 1) {
								?>
									<a class='btn btn-info' title='Editar periodo' data-toggle="modal" data-target="#nuevoPeriodo" onclick="editar_periodo('<?php echo $p['id_periodo']; ?>','<?php echo $p['mes_periodo']; ?>','<?php echo $p['anio_periodo']; ?>','<?php echo $p['status']; ?>')"><i class="glyphicon glyphicon-edit"></i> </a></span>
								<?php
								}
								?>
							</td>
						</tr>
					<?php
					}
					?>
					<tr>
						<td colspan="4"><span class="pull-right">
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

if ($action == 'guardar_periodo') {
	$idPeriodo = intval($_POST['idPeriodo']);
	$mes_periodo = mysqli_real_escape_string($con, (strip_tags($_POST["mes_periodo"], ENT_QUOTES)));
	$anio_periodo = mysqli_real_escape_string($con, (strip_tags($_POST["anio_periodo"], ENT_QUOTES)));
	$listStatus = mysqli_real_escape_string($con, (strip_tags($_POST["listStatus"], ENT_QUOTES)));

	$busca_periodo = mysqli_query($con, "SELECT * FROM periodo_contable WHERE ruc_empresa = '" . $ruc_empresa . "' 
	and mes_periodo='" . $mes_periodo . "' and anio_periodo='" . $anio_periodo . "' ");
	$count = mysqli_num_rows($busca_periodo);

	if (empty($idPeriodo) && $count == 0) {
		$guarda_periodo = mysqli_query($con, "INSERT INTO periodo_contable (mes_periodo, anio_periodo, ruc_empresa) 
			VALUES ('" . $mes_periodo . "','" . $anio_periodo . "','" . $ruc_empresa . "')");
		if ($guarda_periodo) {
			echo "<script>
				$.notify('Período registrado','success');
	 			document.querySelector('#guardar_periodo').reset();
				load(1);
				 </script>";
		} else {
			echo "<script>
				$.notify('No se ha guardado, intente de nuevo','error');
				</script>";
		}
	} else {
		$update_periodo = mysqli_query($con, "UPDATE periodo_contable SET status='" . $listStatus . "' WHERE id_periodo ='" . $idPeriodo . "' ");
		if ($update_periodo) {
			echo "<script>
				$.notify('Período actualizado','success');
	 			document.querySelector('#guardar_periodo').reset();
				 load(1);
				 </script>";
		} else {
			echo "<script>
				$.notify('No se ha guardado, intente de nuevo','error');
				</script>";
		}
	}
}
?>