<?php
include(dirname(dirname(__DIR__)) . "/conexiones/conectalogin.php");
$con = conenta_login();
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
if ($action == 'buscar_empresa_asignada') {
	session_start();
	$id_usuario = isset($_SESSION['id_usuario']) ? $_SESSION['id_usuario'] : 0;
	if ($id_usuario) {
		$q = mysqli_real_escape_string($con, (strip_tags($_REQUEST['q'], ENT_QUOTES)));
		$aColumns = array('emp.nombre', 'emp.nombre_comercial', 'emp.ruc');
		$sTable = "empresa_asignada as emp_asi INNER JOIN empresas as emp ON emp.id=emp_asi.id_empresa INNER JOIN usuarios as usu ON usu.id=emp_asi.id_usuario ";
		$sWhere = " WHERE emp_asi.id_usuario='" . $id_usuario . "' AND emp.estado='1' AND usu.estado='1' ";
		if ($_GET['q'] != "") {
			$sWhere = " WHERE (emp_asi.id_usuario='" . $id_usuario . "' AND emp.estado='1' AND usu.estado='1' AND ";
			for ($i = 0; $i < count($aColumns); $i++) {
				$sWhere .= $aColumns[$i] . " LIKE '%" . $_GET['q'] . "%' AND emp_asi.id_usuario='" . $id_usuario . "' AND emp.estado='1' AND usu.estado='1' OR ";
			}
			$sWhere = substr_replace($sWhere, " AND emp_asi.id_usuario='" . $id_usuario . "' AND emp.estado='1' AND usu.estado='1' ", -3);
			$sWhere .= ')';
		}
		$sWhere .= " order by emp.nombre_comercial asc ";

		include(dirname(__DIR__) . "/ajax/pagination.php");
		//pagination variables
		$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
		$per_page = 10; //how much records you want to show
		$adjacents  = 4; //gap between pages after number of adjacents
		$offset = ($page - 1) * $per_page;
		$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
		$row = mysqli_fetch_array($count_query);
		$numrows = $row['numrows'];
		$total_pages = ceil($numrows / $per_page);
		$reload = '../index.php?menu=true';
		//main query to fetch the data
		$sql = "SELECT * FROM  $sTable $sWhere LIMIT $offset,$per_page";
		$query = mysqli_query($con, $sql);
		//loop through fetched data
		if ($numrows > 0) {
			while ($itemu = mysqli_fetch_array($query)) {
?>
				<form class="form-horizontal" action="/sistema/set_empresa.php" method="POST">
					<input type="hidden" name="id_usuario" value="<?php echo $id_usuario ?>" />
					<input type="hidden" name="id_empresa" value="<?php echo $itemu['id_empresa'] ?>" />
					<input type="hidden" name="ruc_empresa" value="<?php echo $itemu['ruc'] ?>" />
					<Button type="submit" style="border-radius: 0px;" class="list-group-item list-group-item-info"><?php echo ucwords(strtolower($itemu['nombre_comercial'])) ?></button>
				</form>
			<?php
			}
			?>
			<tr>
				<td colspan="2">
					<span class="pull-right">
						<?php
						if ($total_pages > 1) {
							echo paginate($reload, $page, $total_pages, $adjacents);
						}
						?>
					</span>
				</td>
			</tr>
<?php
		}
	}
}
?>