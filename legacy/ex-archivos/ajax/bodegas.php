<?php
/* Connect To Database*/
include("../conexiones/conectalogin.php");
require_once("../ajax/pagination.php");
require_once("../helpers/helpers.php");
$con = conenta_login();
session_start();
$id_usuario = $_SESSION['id_usuario'];
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_empresa = $_SESSION['id_empresa'];
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

if ($action == 'asignaciones') {
	$id_bodega = intval($_GET['id_bodega']);
	$query_usuarios_asignados = mysqli_query($con, "SELECT emp.id_empresa as id_empresa, usu.id as id_usu, usu.nombre as usuario_empresa 
		FROM empresa_asignada as emp INNER JOIN usuarios as usu ON usu.id=emp.id_usuario
		WHERE emp.id_empresa='" . $id_empresa . "' and usu.estado='1' order by usu.nombre asc");
?>
	<div class="table-responsive">
		<div class="panel panel-info" style="max-height:300px;overflow-y: auto;">
			<table class="table table-hover">
				<tr class="info">
					<th>Usuarios</th>
					<th>Acceso</th>
				</tr>
				<?php
				while ($row_usuarios = mysqli_fetch_array($query_usuarios_asignados)) {
					$usuario_empresa = strtoupper($row_usuarios['usuario_empresa']);
					$id_usu = $row_usuarios['id_usu'];
					$id_empresa = $row_usuarios['id_empresa'];

					$query_bodega_asignada = mysqli_query($con, "SELECT * FROM bodega_restringida WHERE id_empresa='" . $id_empresa . "' and id_bodega='" . $id_bodega . "' and id_usuario='" . $id_usu . "'");
					$row_asignados = mysqli_fetch_array($query_bodega_asignada);
					$id_restringido = isset($row_asignados['id']) ? $row_asignados['id'] : "";
					$id_usu_res = isset($row_asignados['id_usuario']) ? 1 : 0;
					$id_bodega_res = isset($row_asignados['id_bodega']) ? 1 : 0;
					$id_empresa_res = isset($row_asignados['id_empresa']) ? 1 : 0;
				?>
					<tr>
						<td><?php echo $usuario_empresa; ?></td>
						<?php
						//cuando suma 3 quiere decir que existe un registro lo cual da a entender que ese usuario no tiene acceso a esa bodega
						if (($id_usu_res + $id_bodega_res + $id_empresa_res) == 3) {
						?>
							<td><a href="#" class='btn btn-danger btn-sm' title='Asignar bodega' onclick="asignar_bodega('<?php echo $id_restringido; ?>');"><i class="glyphicon glyphicon-remove"></i></a></td>
						<?php
						} else {
						?>
							<td><a href="#" class='btn btn-success btn-sm' title='Restringir bodega' onclick="quitar_bodega('<?php echo $id_usu; ?>');"><i class="glyphicon glyphicon-ok"></i></a></td>
						<?php
						}
						?>
					</tr>
				<?php
				}
				?>
			</table>
		</div>
	</div>
	<?php
}

//este es para que no este asignado la bodega al usuario, por default ya estan agregadas
//solo cuando se guarda en la base se entiende que NO  esta asignada
if ($action == 'asignar_bodega') {
	$id = intval($_GET['id']);
	$query_quitar = mysqli_query($con, "DELETE FROM bodega_restringida WHERE id='" . $id . "'");
	if ($query_quitar) {
		echo "<script>$.notify('Bodega asignada.','success')</script>";
	} else {
		echo "<script>$.notify('Lo siento algo ha salido mal intenta nuevamente.','error')</script>";
	}
}

//agregar la resttriccion de acceso a la bodega
if ($action == 'quitar_bodega') {
	$id_usu = intval($_GET['id_usu']);
	$id_bodega = intval($_GET['id_bodega']);
	$guarda = mysqli_query($con, "INSERT INTO bodega_restringida (id_empresa, id_usuario, id_bodega) 
							VALUES ('" . $id_empresa . "', '" . $id_usu . "', '" . $id_bodega . "')");
	if ($guarda) {
		echo "<script>$.notify('Bodega restringida.','success')</script>";
	} else {
		echo "<script>$.notify('Lo siento algo ha salido mal intenta nuevamente.','error')</script>";
	}
}


if ($action == 'eliminar_bodega') {
	$id_bodega = intval($_GET['id_bodega']);

	$query_bodegas = mysqli_query($con, "SELECT * from inventarios WHERE id_bodega='" . $id_bodega . "' and ruc_empresa = '" . $ruc_empresa . "'");
	$count = mysqli_num_rows($query_bodegas);

	if ($count > 0) {
		echo "<script>$.notify('No se puede eliminar. Esta bodega tiene movimientos en inventarios.','error')</script>";
	} else {
		if ($deleteuno = mysqli_query($con, "UPDATE bodega SET status='0' WHERE id_bodega='" . $id_bodega . "'")) {
			echo "<script>$.notify('Bodega eliminada.','success')</script>";
		} else {
			echo "<script>$.notify('Lo siento algo ha salido mal intenta nuevamente.','error')</script>";
		}
	}
}

if ($action == 'buscar_bodegas') {
	$q = mysqli_real_escape_string($con, (strip_tags($_REQUEST['q'], ENT_QUOTES)));
	$aColumns = array('id_bodega', 'nombre_bodega'); //Columnas de busqueda
	$sTable = "bodega";
	$sWhere = "WHERE ruc_empresa ='" .  $ruc_empresa . " ' and status != '0' ";
	if ($_GET['q'] != "") {
		$sWhere = "WHERE (ruc_empresa ='" .  $ruc_empresa . " ' and status != '0' AND ";

		for ($i = 0; $i < count($aColumns); $i++) {
			$sWhere .= $aColumns[$i] . " LIKE '%" . $q . "%' AND ruc_empresa = '" .  $ruc_empresa . "' and status != '0' OR ";
		}

		$sWhere = substr_replace($sWhere, "AND ruc_empresa = '" .  $ruc_empresa . "' and status != '0' ", -3);
		$sWhere .= ')';
	}
	$sWhere .= " order by nombre_bodega asc ";
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
	$reload = '../bodegas.php';
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
						<th>id</th>
						<th>Nombre</th>
						<th>Usuarios</th>
						<th>Status</th>
						<th class='text-right'>Opciones</th>
					</tr>
					<?php
					while ($row = mysqli_fetch_array($query)) {
						$id_bodega = $row['id_bodega'];
						$status = $row['status'];
						$nombre_bodega = strtoupper($row['nombre_bodega']);
					?>
						<input type="hidden" value="<?php echo $nombre_bodega; ?>" id="nombre_bodega_mod<?php echo $id_bodega; ?>">
						<input type="hidden" value="<?php echo $status; ?>" id="status_bodega_mod<?php echo $id_bodega; ?>">
						<tr>
							<td><?php echo $id_bodega; ?></td>
							<td class='col-xs-4'><?php echo $nombre_bodega; ?></td>
							<?php
							if (getPermisos($con, $id_usuario, $ruc_empresa, 'bodegas')['u'] == 1) {
							?>
								<td><a class='btn btn-info btn-sm' title='Asignar bodega' onclick="usuarios_bodega('<?php echo $id_bodega; ?>', '<?php echo $nombre_bodega; ?>');" data-toggle="modal" data-target="#bodegas_asignadas"><i class="glyphicon glyphicon-list"></i></a></td>
							<?php
							}
							?>
							<td><?php echo $status == 1 ? "<span class='label label-success'>Activo</span>" : "<span class='label label-danger'>Inactivo</span>"; ?></td>

							<td class='text-right'>
								<?php
								if (getPermisos($con, $id_usuario, $ruc_empresa, 'bodegas')['u'] == 1) {
								?>
									<a class='btn btn-info btn-sm' title='Editar bodega' onclick="editar_bodega('<?php echo $id_bodega; ?>');" data-toggle="modal" data-target="#bodegas"><i class="glyphicon glyphicon-edit"></i></a>
								<?php
								}
								if (getPermisos($con, $id_usuario, $ruc_empresa, 'bodegas')['d'] == 1) {
								?>
									<a class='btn btn-danger btn-sm' title='Eliminar bodega' onclick="eliminar_bodega('<?php echo $id_bodega; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
								<?php
								}
								?>
							</td>
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

//inicio guardar y editar 
if ($action == 'guardar_bodega') {
	$id_bodega = intval($_POST['id_bodega']);
	$nombre_bodega = strClean($_POST['nombre_bodega']);
	$status = $_POST['status'];

	if (empty($nombre_bodega)) {
		echo "<script>
				$.notify('Ingrese de bodega','error');
				</script>";
	} else {
		if (empty($id_bodega)) {
			$busca_empleado = mysqli_query($con, "SELECT * FROM bodega WHERE nombre_bodega = '" . $nombre_bodega . "' and ruc_empresa = '" . $ruc_empresa . "' and status !=0 ");
			$count = mysqli_num_rows($busca_empleado);
			if ($count > 0) {
				echo "<script>
					$.notify('La bodega ya esta registrada','error');
					</script>";
			} else {
				$guarda_bodega = mysqli_query($con, "INSERT INTO bodega (id_bodega,
																			ruc_empresa,
																			nombre_bodega)
																				VALUES ('" . $id_bodega . "',
																						'" . $ruc_empresa . "',
																						'" . $nombre_bodega . "')");

				if ($guarda_bodega) {
					echo "<script>
					$.notify('Bodega registrada','success');
					document.querySelector('#formBodega').reset();
					load(1);
					</script>";
				} else {
					echo "<script>
					$.notify('Intente de nuevo','error');
					</script>";
				}
			}
		} else {
			//modificar la bodega
			$busca_bodega = mysqli_query($con, "SELECT * FROM bodega WHERE (nombre_bodega = '" . $nombre_bodega . "' and id_bodega != '" . $id_bodega . "' and ruc_empresa = '" . $ruc_empresa . "' and status !=0) ");
			$count = mysqli_num_rows($busca_bodega);
			if ($count > 0) {
				echo "<script>
					$.notify('La bodega ya esta registrada','error');
					</script>";
			} else {
				$update_bodega = mysqli_query($con, "UPDATE bodega SET nombre_bodega='" . $nombre_bodega . "',	status='" . $status . "' 	WHERE id_bodega = '" . $id_bodega . "'");
				if ($update_bodega) {
					echo "<script>
						$.notify('Bodega actualizada','success');
						setTimeout(function () {location.reload()}, 1000);
							</script>";
				} else {
					echo "<script>
							$.notify('Intente de nuevo','error');
							</script>";
				}
			}
		}
	}
}

?>