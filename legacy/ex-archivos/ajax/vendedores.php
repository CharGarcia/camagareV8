<?php
require_once("../conexiones/conectalogin.php");
require_once("../ajax/pagination.php"); //include pagination file
require_once("../helpers/helpers.php"); //include pagination file
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
$id_empresa = $_SESSION['id_empresa'];
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

//para asiganciones de vendedores
if ($action == 'asignaciones') {
	$id_vendedor = intval($_GET['id_vendedor']);
	$query_usuarios_asignados = mysqli_query($con, "SELECT emp.id_empresa as id_empresa, usu.id as id_usu, 
	usu.nombre as usuario_empresa 
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

					$query_bodega_asignada = mysqli_query($con, "SELECT * FROM vendedor_asignado WHERE id_empresa='" . $id_empresa . "' and id_vendedor='" . $id_vendedor . "' and id_usuario='" . $id_usu . "'");
					$row_asignados = mysqli_fetch_array($query_bodega_asignada);
					$id_restringido = isset($row_asignados['id']) ? $row_asignados['id'] : "";
					$id_usu_res = isset($row_asignados['id_usuario']) ? 1 : 0;
					$id_vendedor_res = isset($row_asignados['id_vendedor']) ? 1 : 0;
					$id_empresa_res = isset($row_asignados['id_empresa']) ? 1 : 0;
				?>
					<tr>
						<td><?php echo $usuario_empresa; ?></td>
						<?php
						//cuando suma 3 quiere decir que existe un registro lo cual da a entender que ese usuario no tiene acceso a esa bodega
						if (($id_usu_res + $id_vendedor_res + $id_empresa_res) == 3) {
						?>
							<td><a class='btn btn-danger btn-sm' title='Asignar vendedor' onclick="asignar_vendedor('<?php echo $id_restringido; ?>');"><i class="glyphicon glyphicon-remove"></i></a></td>
						<?php
						} else {
						?>
							<td><a class='btn btn-success btn-sm' title='Quitar vendedor' onclick="quitar_vendedor('<?php echo $id_usu; ?>');"><i class="glyphicon glyphicon-ok"></i></a></td>
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

//este es para que no este asignado al vendedor al usuario, por default ya estan agregadas
//solo cuando se guarda en la base se entiende que NO  esta asignada
if ($action == 'asignar_vendedor') {
	$id = intval($_GET['id']);
	$query_quitar = mysqli_query($con, "DELETE FROM vendedor_asignado WHERE id='" . $id . "'");
	if ($query_quitar) {
		echo "<script>$.notify('Vendedor asignada.','success')</script>";
	} else {
		echo "<script>$.notify('Lo siento algo ha salido mal intenta nuevamente.','error')</script>";
	}
}

//agregar la resttriccion de acceso a la vendedor
if ($action == 'quitar_vendedor') {
	$id_usu = intval($_GET['id_usu']);
	$id_vendedor = intval($_GET['id_vendedor']);
	$guarda = mysqli_query($con, "INSERT INTO vendedor_asignado (id_empresa, id_usuario, id_vendedor) 
							VALUES ('" . $id_empresa . "', '" . $id_usu . "', '" . $id_vendedor . "')");
	if ($guarda) {
		echo "<script>$.notify('Vendedor desagregado.','success')</script>";
	} else {
		echo "<script>$.notify('Lo siento algo ha salido mal intenta nuevamente.','error')</script>";
	}
}


if ($action == 'datos_editar_vendedor') {
	$id_vendedor = $_GET['id_vendedor'];
	$sql = mysqli_query($con, "SELECT  ven.id_vendedor as id_vendedor, 
	ven.nombre as nombre, ven.numero_id as numero_id, ven.correo as correo,
	ven.telefono as telefono, ven.direccion as direccion, 
	ven.status as status, ven.tipo_id as tipo_id FROM vendedores as ven 
	WHERE ven.id_vendedor='" . $id_vendedor . "'");
	$vendedor = mysqli_fetch_array($sql);
	$data = array(
		'id_vendedor' => $vendedor['id_vendedor'],
		'vendedor' => $vendedor['nombre'],
		'cedula' => $vendedor['numero_id'],
		'correo' => $vendedor['correo'],
		'telefono' => $vendedor['telefono'],
		'direccion' => $vendedor['direccion'],
		'status' => $vendedor['status'],
		'tipo_id' => $vendedor['tipo_id']
	);

	if ($sql) {
		$arrResponse = array("status" => true, "data" => $data);
	} else {
		$arrResponse = array("status" => false, "msg" => 'Datos no encontrados');
	}
	echo json_encode($arrResponse, JSON_UNESCAPED_UNICODE); //, JSON_UNESCAPED_UNICODE
	die();
}

if ($action == 'eliminar_vendedor') {
	$id_vendedor = intval($_POST['id_vendedor']);
	$query_vendedores_ventas = mysqli_query($con, "SELECT * from vendedores_ventas where id_vendedor='" . $id_vendedor . "'");
	$count_vendedores_ventas = mysqli_num_rows($query_vendedores_ventas);
	$query_vendedores_recibos = mysqli_query($con, "SELECT * from vendedores_recibos where id_vendedor='" . $id_vendedor . "'");
	$count_vendedores_recibos = mysqli_num_rows($query_vendedores_recibos);
	if (($count_vendedores_ventas +  $count_vendedores_recibos) > 0) {
		echo "<script>$.notify('No se puede eliminar. Existen resgistros con este vendedor.','error')</script>";
	} else {
		if ($delete = mysqli_query($con, "UPDATE vendedores SET status='0' WHERE id_vendedor='" . $id_vendedor . "'")) {
			echo "<script>$.notify('Vendedor eliminado.','success')</script>";
		} else {
			echo "<script>$.notify('Lo siento algo ha salido mal intenta nuevamente.','error')</script>";
		}
	}
}

if ($action == 'buscar_vendedores') {
	$q = mysqli_real_escape_string($con, (strip_tags($_REQUEST['q'], ENT_QUOTES)));
	$ordenado = mysqli_real_escape_string($con, (strip_tags($_GET['ordenado'], ENT_QUOTES)));
	$por = mysqli_real_escape_string($con, (strip_tags($_GET['por'], ENT_QUOTES)));
	$aColumns = array('ven.nombre', 'ven.numero_id', 'ven.correo', 'ven.numero_id', 'ven.telefono', 'usu.nombre'); //Columnas de busqueda
	$sTable = "vendedores as ven";
	$sWhere = "WHERE ven.ruc_empresa = '" . $ruc_empresa . "' and ven.status !=0";
	$text_buscar = explode(' ', $q);
	$like = "";
	for ($i = 0; $i < count($text_buscar); $i++) {
		$like .= "%" . $text_buscar[$i];
	}

	if ($_GET['q'] != "") {
		$sWhere = "WHERE (ven.ruc_empresa = '" . $ruc_empresa . "' and ven.status !=0 AND ";
		for ($i = 0; $i < count($aColumns); $i++) {
			$sWhere .= $aColumns[$i] . " LIKE '" . $like . "%' AND ven.ruc_empresa = '" . $ruc_empresa . "' and ven.status !=0 OR ";
		}
		$sWhere = substr_replace($sWhere, "AND ven.ruc_empresa = '" . $ruc_empresa . "' and ven.status !=0 ", -3);
		$sWhere .= ')';
	}
	$sWhere .= " order by $ordenado $por";

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
	$reload = '../vendedores.php';
	//main query to fetch the data
	$sql = "SELECT ven.id_vendedor as id_vendedor, ven.nombre as nombre, ven.numero_id as numero_id, ven.correo as correo,
		ven.telefono as telefono, ven.direccion as direccion, ven.status as status FROM  $sTable $sWhere LIMIT $offset,$per_page";
	$query = mysqli_query($con, $sql);
	//loop through fetched data
	if ($numrows > 0) {

	?>
		<div class="panel panel-info">
			<div class="table-responsive">
				<table class="table">
					<tr class="info">
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("ven.nombre");'>Nombre vendedor</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("ven.numero_id");'>Ruc/Cedula</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("ven.telefono");'>Teléfono</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("ven.correo");'>Email</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("ven.direccion");'>Dirección</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("usu.nombre");'>Usuarios</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("ven.status");'>Status</button></th>
						<th class='text-right'>Opciones</th>
					</tr>
					<?php
					while ($row = mysqli_fetch_array($query)) {
						$id_vendedor = $row['id_vendedor'];
						$nombre_vendedor = $row['nombre'];
						$numero_id = $row['numero_id'];
						$telefono = $row['telefono'];
						$correo = $row['correo'];
						$direccion = $row['direccion'];
						$status = $row['status'];
						//$usuario = $row['usuario'];
					?>
						<tr>
							<td><?php echo strtoupper($nombre_vendedor); ?></td>
							<td><?php echo $numero_id; ?></td>
							<td><?php echo $telefono; ?></td>
							<td><?php echo $correo; ?></td>
							<td><?php echo strtoupper($direccion); ?></td>
							<?php
							if (getPermisos($con, $id_usuario, $ruc_empresa, 'vendedores')['u'] == 1) {
							?>
								<td><a class='btn btn-info btn-sm' title='Asignar usuarios' onclick="usuarios_vendedor('<?php echo $id_vendedor; ?>', '<?php echo strtoupper($nombre_vendedor); ?>');" data-toggle="modal" data-target="#usuarios_asignados"><i class="glyphicon glyphicon-list"></i></a></td>
							<?php
							}
							?>
							<td><?php echo $status == 1 ? "<span class='label label-success'>Activo</span>" : "<span class='label label-danger'>Inactivo</span>"; ?></td>
							<td><span class="pull-right">
									<?php
									if (getPermisos($con, $id_usuario, $ruc_empresa, 'vendedores')['u'] == 1) {
									?>
										<a href="#" class='btn btn-info btn-xs' title='Editar vendedor' onclick="editar_vendedor('<?php echo $id_vendedor; ?>');" data-toggle="modal" data-target="#nuevoVendedor"><i class="glyphicon glyphicon-edit"></i></a>
									<?php
									}
									if (getPermisos($con, $id_usuario, $ruc_empresa, 'vendedores')['d'] == 1) {
									?>
										<a href="#" class='btn btn-danger btn-xs' title='Eliminar vendedor' onclick="eliminar_vendedor('<?php echo $id_vendedor; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
									<?php
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

// Crear vendedor rápido (solo nombre) - retorna JSON para uso en modal clientes
if ($action == 'guardar_vendedor_rapido') {
	header('Content-Type: application/json; charset=utf-8');
	$nombre = isset($_POST['nombre']) ? strClean(trim($_POST['nombre'])) : '';
	if (empty($nombre)) {
		echo json_encode(['success' => false, 'msg' => 'Ingrese el nombre del vendedor']);
		exit;
	}
	$numero_id = '9' . str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
	$busca = mysqli_query($con, "SELECT 1 FROM vendedores WHERE numero_id='" . mysqli_real_escape_string($con, $numero_id) . "' AND ruc_empresa='" . mysqli_real_escape_string($con, $ruc_empresa) . "' LIMIT 1");
	while ($busca && mysqli_num_rows($busca) > 0) {
		$numero_id = '9' . str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
		$busca = mysqli_query($con, "SELECT 1 FROM vendedores WHERE numero_id='" . mysqli_real_escape_string($con, $numero_id) . "' AND ruc_empresa='" . mysqli_real_escape_string($con, $ruc_empresa) . "' LIMIT 1");
	}
	$nombre_esc = mysqli_real_escape_string($con, $nombre);
	$ins = mysqli_query($con, "INSERT INTO vendedores (tipo_id, numero_id, nombre, correo, ruc_empresa, id_usuario, telefono, direccion) VALUES ('05', '" . $numero_id . "', '" . $nombre_esc . "', '', '" . mysqli_real_escape_string($con, $ruc_empresa) . "', '" . intval($id_usuario) . "', '', '')");
	if ($ins) {
		$id_nuevo = mysqli_insert_id($con);
		echo json_encode(['success' => true, 'id_vendedor' => $id_nuevo, 'nombre' => $nombre]);
	} else {
		echo json_encode(['success' => false, 'msg' => 'Error al guardar']);
	}
	exit;
}

if ($action == 'guardar_vendedor') {
	$id_vendedor = intval($_POST['id_vendedor']);
	$tipo_id = $_POST['tipo_id'];
	$cedula = strClean($_POST['cedula']);
	$nombre = strClean($_POST['nombre']);
	$correo = strClean($_POST['correo']);
	$direccion = strClean($_POST['direccion']);
	$telefono = strClean($_POST['telefono']);
	//$id_usu_asignado = $_POST['usuario'];
	$status = $_POST['status'];

	if (empty($cedula)) {
		echo "<script>
				$.notify('Ingrese número de indentificación del vendedor','error');
				</script>";
	} else if (empty($nombre)) {
		echo "<script>
				$.notify('Ingrese nombre del vendedor','error');
				</script>";
	} else {
		if (empty($id_vendedor)) {
			$busca_vendedor = mysqli_query($con, "SELECT * FROM vendedores WHERE numero_id = '" . $cedula . "' and ruc_empresa = '" . $ruc_empresa . "' and status !='0'");
			$count = mysqli_num_rows($busca_vendedor);
			if ($count > 0) {
				echo "<script>
					$.notify('El vendedor ya esta registrado','error');
					</script>";
			} else {
				$guarda_vendedor = mysqli_query($con, "INSERT INTO vendedores (tipo_id,
																			numero_id,
																			nombre,
																			correo,
																			ruc_empresa,
																			id_usuario,
																			telefono,
																			direccion)
																				VALUES ('" . $tipo_id . "',
																						'" . $cedula . "',
																						'" . $nombre . "',
																						'" . $correo . "',
																						'" . $ruc_empresa . "',
																						'" . $id_usuario . "',
																						'" . $telefono . "',
																						'" . $direccion . "')");

				if ($guarda_vendedor) {
					echo "<script>
					$.notify('Vendedor registrado','success');
					document.querySelector('#formVendedores').reset();
					load(1);
					</script>";
				} else {
					echo "<script>
					$.notify('Intente de nuevo','error');
					</script>";
				}
			}
		} else {
			//modificar el vendedor
			$busca_vendedor = mysqli_query($con, "SELECT * FROM vendedores WHERE id_vendedor != '" . $id_vendedor . "' and numero_id = '" . $cedula . "' and ruc_empresa = '" . $ruc_empresa . "' and status !='0'");
			$count = mysqli_num_rows($busca_vendedor);
			if ($count > 0) {
				echo "<script>
					$.notify('El vendedor ya esta registrado','error');
					</script>";
			} else {
				$update_vendedor = mysqli_query($con, "UPDATE vendedores SET numero_id='" . $cedula . "',
																			nombre='" . $nombre . "',
																			correo='" . $correo . "',
																			id_usuario='" . $id_usuario . "',
																			telefono='" . $telefono . "',
																			direccion='" . $direccion . "',
																			status='" . $status . "'																			
																			WHERE id_vendedor ='" . $id_vendedor . "'");
				if ($update_vendedor) {
					echo "<script>
						$.notify('Vendedor actualizado','success');
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