<?php
include("../conexiones/conectalogin.php");
include_once("../helpers/helpers.php");
//include("../validadores/valida_varios_mails.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

if ($action == 'datos_editar_proveedor') {
	$id_proveedor = $_GET['id_proveedor'];
	$sql = mysqli_query($con, "SELECT * FROM proveedores WHERE ruc_empresa='" . $ruc_empresa . "' and id_proveedor='" . $id_proveedor . "'");
	$proveedor = mysqli_fetch_array($sql);
	$data = array(
		'id_proveedor' => $proveedor['id_proveedor'],
		'razon_social' => $proveedor['razon_social'],
		'nombre_comercial' => $proveedor['nombre_comercial'],
		'tipo_id_proveedor' => $proveedor['tipo_id_proveedor'],
		'ruc_proveedor' => $proveedor['ruc_proveedor'],
		'mail_proveedor' => $proveedor['mail_proveedor'],
		'dir_proveedor' => $proveedor['dir_proveedor'],
		'telf_proveedor' => $proveedor['telf_proveedor'],
		'tipo_empresa' => $proveedor['tipo_empresa'],
		'plazo' => $proveedor['plazo'],
		'id_banco' => $proveedor['id_banco'],
		'tipo_cta' => $proveedor['tipo_cta'],
		'numero_cta' => $proveedor['numero_cta']
	);
	if ($sql) {
		$arrResponse = array("status" => true, "data" => $data);
	} else {
		$arrResponse = array("status" => false, "msg" => 'Datos no encontrados');
	}
	echo json_encode($arrResponse, JSON_UNESCAPED_UNICODE); //, JSON_UNESCAPED_UNICODE
	die();
}


if ($action == 'eliminar_proveedor') {
	$id_proveedor = intval($_GET['id_proveedor']);

	$buscar_proveedor_compras = mysqli_query($con, "SELECT * FROM encabezado_compra WHERE id_proveedor='" . $id_proveedor . "'");
	$total_proveedores_compras = mysqli_num_rows($buscar_proveedor_compras);

	$buscar_proveedor_liquidacion = mysqli_query($con, "SELECT * FROM encabezado_liquidacion WHERE id_proveedor='" . $id_proveedor . "'");
	$total_proveedores_liquidacion = mysqli_num_rows($buscar_proveedor_liquidacion);

	$buscar_proveedor_retenciones = mysqli_query($con, "SELECT * FROM encabezado_retencion WHERE id_proveedor='" . $id_proveedor . "'");
	$total_proveedores_retenciones = mysqli_num_rows($buscar_proveedor_retenciones);

	if (($total_proveedores_retenciones + $total_proveedores_compras + $total_proveedores_liquidacion) == 0) {
		if ($delete_proveedor = mysqli_query($con, "DELETE FROM proveedores WHERE id_proveedor='" . $id_proveedor . "'")) {
			echo "<script>$.notify('Proveedor eliminado.','success')</script>";
		} else {
			echo "<script>$.notify('Lo siento algo ha salido mal intenta nuevamente.','error')</script>";
		}
	} else {
		echo "<script>$.notify('No es posible eliminar, esta asignado a una transacción.','error')</script>";
	}
}


if ($action == 'buscar_proveedores') {
	$q = mysqli_real_escape_string($con, (strip_tags($_REQUEST['q'], ENT_QUOTES)));
	$aColumns = array('razon_social', 'nombre_comercial', 'dir_proveedor', 'ruc_proveedor', 'mail_proveedor', 'telf_proveedor'); //Columnas de busqueda ,'nombre_comercial','ruc_proveedor','dir_proveedor'
	$sTable = "proveedores";
	$sWhere = "WHERE ruc_empresa = '" . $ruc_empresa . "' ";

	$text_buscar = explode(' ', $q);
	$like = "";
	for ($i = 0; $i < count($text_buscar); $i++) {
		$like .= "%" . $text_buscar[$i];
	}

	if ($_GET['q'] != "") {
		$sWhere = "WHERE ruc_empresa = '" . $ruc_empresa . "' and ";
		for ($i = 0; $i < count($aColumns); $i++) {
			$sWhere .= $aColumns[$i] . " LIKE '%" . $like . "%' and ruc_empresa = '" . $ruc_empresa . "' OR ";
		}
		$sWhere = substr_replace($sWhere, " and ruc_empresa = '" . $ruc_empresa . "'", -3);
		$sWhere .= '';
	}
	$sWhere .= " order by razon_social asc";

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
	$reload = '../proveedores.php';
	//main query to fetch the data
	$sql = "SELECT * FROM  $sTable $sWhere LIMIT $offset,$per_page";
	$query = mysqli_query($con, $sql);
	//loop through fetched data
	if ($numrows > 0) {

?>
		<div class="panel panel-info">
			<div class="table-responsive">
				<table class="table">
					<tr class="info">
						<th>Razón social</th>
						<th>Nombre comercial</th>
						<th>Ruc/Cedula</th>
						<th>Teléfono</th>
						<th>Email</th>
						<th>Dirección</th>
						<th>Tipo</th>
						<th>Plazo</th>
						<th class="text-right">Acciones</th>

					</tr>
					<?php
					while ($row = mysqli_fetch_array($query)) {
						$id_proveedor = $row['id_proveedor'];
						$razon_social = $row['razon_social'];
						$nombre_comercial = $row['nombre_comercial'];
						$ruc_proveedor = $row['ruc_proveedor'];
						$telf_proveedor = $row['telf_proveedor'];
						$dir_proveedor = $row['dir_proveedor'];
						$mail_proveedor = $row['mail_proveedor'];
						$tipo = $row['tipo_empresa'];
						$tipo_id = $row['tipo_id_proveedor'];
						$plazo = $row['plazo'];
						$unidad_tiempo = $row['unidad_tiempo'];
						$relacionado = $row['relacionado'];

						//buscar TIPO DE EMPRESA
						$busca_tipo_empresa = "SELECT * FROM tipo_empresa WHERE codigo= '$tipo'";
						$result = $con->query($busca_tipo_empresa);
						$datos_tipo = mysqli_fetch_array($result);
						$tipo_empresa = $datos_tipo['nombre'];
						//buscar PARTE RELACIONADA
						if ($relacionado == 1) {
							$relacionado = "NO";
						} else {
							$relacionado = "SI";
						}
					?>
						<tr>
							<td><?php echo strtoupper($razon_social); ?></td>
							<td><?php echo strtoupper($nombre_comercial); ?></td>
							<td><?php echo $ruc_proveedor; ?></td>
							<td><?php echo $telf_proveedor; ?></td>
							<td><?php echo strtolower($mail_proveedor); ?></td>
							<td><?php echo strtoupper($dir_proveedor); ?></td>
							<td><?php echo $tipo_empresa; ?></td>
							<td><?php echo $plazo . " " . $unidad_tiempo; ?></td>
							<td><span class="pull-right">
									<?php
									if (getPermisos($con, $id_usuario, $ruc_empresa, 'proveedores')['u'] == 1) {
									?>
										<a href="#" class='btn btn-info btn-xs' title='Editar proveedor' onclick="editar_proveedor('<?php echo $id_proveedor; ?>');" data-toggle="modal" data-target="#modalProveedor"><i class="glyphicon glyphicon-edit"></i></a>
									<?php
									}
									if (getPermisos($con, $id_usuario, $ruc_empresa, 'proveedores')['d'] == 1) {
									?>
										<a href="#" class='btn btn-danger btn-xs' title='Eliminar proveedor' onclick="eliminar_proveedor('<?php echo $id_proveedor; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
									<?php
									}
									?>
						</tr>
					<?php
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

//guardar proveedor
if ($action == 'guardar_proveedor') {
	$id_proveedor = intval($_POST['id_proveedor']);
	$tipo_id = $_POST['tipo_id'];
	$ruc_proveedor = strClean($_POST['ruc_proveedor']);
	$razon_social = strClean($_POST['razon_social']);
	$nombre_comercial = strClean($_POST['nombre_comercial']);
	$tipo_empresa = $_POST['tipo_empresa'];
	$direccion_proveedor = strClean($_POST['direccion_proveedor']);
	$mail_proveedor = $_POST['mail_proveedor'];
	$telefono_proveedor = strClean($_POST['telefono_proveedor']);
	$plazo = $_POST['plazo'];
	$id_banco = intval($_POST['id_banco']);
	$tipo_cta = intval($_POST['tipo_cta']);
	$numero_cta = strClean($_POST['numero_cta']);

	if (empty($ruc_proveedor)) {
		echo "<script>
				$.notify('Ingrese número de documento','error');
				</script>";
	} else if ($tipo_id == '05' && !validador_cedula($ruc_proveedor)) {
		echo "<script>
			$.notify('Corregir cédula','error');
			</script>";
	} else if ($tipo_id == '04' && !validador_ruc($ruc_proveedor)) {
		echo "<script>
			$.notify('Corregir RUC','error');
			</script>";
	} else if (empty($razon_social)) {
		echo "<script>
			$.notify('Ingrese razón social','error');
			</script>";
	} else if (empty($tipo_empresa)) {
		echo "<script>
			$.notify('Seleccione un tipo de contribuyente','error');
			</script>";
	} else if (!empty($mail_proveedor) && !validarCorreo($mail_proveedor)) {
		echo "<script>
			$.notify('Error en mail, Puede ingresar varios correos separados por coma y espacio','error');
			</script>";
	} else {
		if (empty($id_proveedor)) {
			$busca_proveedor = mysqli_query($con, "SELECT * FROM proveedores WHERE ruc_proveedor = '" . $ruc_proveedor . "' and ruc_empresa = '" . $ruc_empresa . "' ");
			$count = mysqli_num_rows($busca_proveedor);
			if ($count > 0) {
				echo "<script>
					$.notify('El proveedor ya esta registrado','error');
					</script>";
			} else {
				$guarda_proveedor = mysqli_query($con, "INSERT INTO proveedores (razon_social,
																			nombre_comercial,
																			ruc_empresa,
																			tipo_id_proveedor,
																			ruc_proveedor,
																			mail_proveedor,
																			dir_proveedor,
																			telf_proveedor,
																			tipo_empresa,
																			plazo,
																			id_banco,
																			tipo_cta,
																			numero_cta)
																				VALUES ('" . $razon_social . "',
																						'" . $nombre_comercial . "',
																						'" . $ruc_empresa . "',
																						'" . $tipo_id . "',
																						'" . $ruc_proveedor . "',
																						'" . $mail_proveedor . "',
																						'" . $direccion_proveedor . "',
																						'" . $telefono_proveedor . "',
																						'" . $tipo_empresa . "',
																						'" . $plazo . "',
																						'" . $id_banco . "',
																						'" . $tipo_cta . "',
																						'" . $numero_cta . "')");

				if ($guarda_proveedor) {
					echo "<script>
					$.notify('Proveedor registrado','success');
					document.querySelector('#formProveedor').reset();
					load(1);
					</script>";
				} else {
					echo "<script>
					$.notify('No es posible registrar, intente de nuevo','error');
					</script>";
				}
			}
		} else {
			//modificar el proveedor
			$busca_proveedor = mysqli_query($con, "SELECT * FROM proveedores WHERE ruc_empresa = '" . $ruc_empresa . "' and ruc_proveedor = '" . $ruc_proveedor . "' and id_proveedor != '" . $id_proveedor . "' ");
			$count = mysqli_num_rows($busca_proveedor);
			if ($count > 0) {
				echo "<script>
					$.notify('El proveedor ya esta registrado','error');
					</script>";
			} else {
				$update_proveedor = mysqli_query($con, "UPDATE proveedores SET razon_social='" . $razon_social . "',
																				nombre_comercial='" . $nombre_comercial . "',
																				tipo_id_proveedor='" . $tipo_id . "',
																				ruc_proveedor='" . $ruc_proveedor . "',
																				mail_proveedor='" . $mail_proveedor . "',
																				dir_proveedor='" . $direccion_proveedor . "',
																				telf_proveedor='" . $telefono_proveedor . "',
																				tipo_empresa='" . $tipo_empresa . "',
																				plazo='" . $plazo . "',
																				unidad_tiempo='Días',
																				id_banco='" . $id_banco . "',
																				tipo_cta='" . $tipo_cta . "',
																				numero_cta='" . $numero_cta . "' 
																				WHERE id_proveedor='" . $id_proveedor . "'");
				if ($update_proveedor) {
					echo "<script>
						$.notify('Proveedor actualizado','success');
						setTimeout(function () {location.reload()}, 1000);
							</script>";
				} else {
					echo "<script>
							$.notify('No es posible actualizar, intente de nuevo','error');
							</script>";
				}
			}
		}
	}
}

/* if ($action == 'guardar_proveedor') {

	// Sesión
	$ruc_empresa = isset($_SESSION['ruc_empresa']) ? $_SESSION['ruc_empresa'] : '';
	$id_usuario  = isset($_SESSION['id_usuario']) ? intval($_SESSION['id_usuario']) : 0; // por si lo necesitas luego

	// Post
	$id_proveedor = isset($_POST['id_proveedor']) ? intval($_POST['id_proveedor']) : 0;
	$tipo_id      = isset($_POST['tipo_id']) ? $_POST['tipo_id'] : '';

	$ruc_proveedor       = strClean(isset($_POST['ruc_proveedor']) ? $_POST['ruc_proveedor'] : '');
	$razon_social        = strClean(isset($_POST['razon_social']) ? $_POST['razon_social'] : '');
	$nombre_comercial    = strClean(isset($_POST['nombre_comercial']) ? $_POST['nombre_comercial'] : '');
	$tipo_empresa        = isset($_POST['tipo_empresa']) ? strClean($_POST['tipo_empresa']) : '';
	$direccion_proveedor = strClean(isset($_POST['direccion_proveedor']) ? $_POST['direccion_proveedor'] : '');
	$mail_proveedor      = strClean(isset($_POST['mail_proveedor']) ? $_POST['mail_proveedor'] : '');
	$telefono_proveedor  = strClean(isset($_POST['telefono_proveedor']) ? $_POST['telefono_proveedor'] : '');
	$plazo               = isset($_POST['plazo']) ? intval($_POST['plazo']) : 0;

	$id_banco   = isset($_POST['id_banco']) ? intval($_POST['id_banco']) : 0;
	$tipo_cta   = isset($_POST['tipo_cta']) ? intval($_POST['tipo_cta']) : 0;
	$numero_cta = strClean(isset($_POST['numero_cta']) ? $_POST['numero_cta'] : '');

	// Validaciones
	if (empty($ruc_proveedor)) {
		notify('Ingrese número de documento', 'error');
		exit;
	}

	if ($tipo_id == '05' && !validador_cedula($ruc_proveedor)) {
		notify('Corregir cédula', 'error');
		exit;
	}

	if ($tipo_id == '04' && !validador_ruc($ruc_proveedor)) {
		notify('Corregir RUC', 'error');
		exit;
	}

	if (empty($razon_social)) {
		notify('Ingrese razón social', 'error');
		exit;
	}

	if (empty($tipo_empresa)) {
		notify('Seleccione un tipo de contribuyente', 'error');
		exit;
	}

	// Mail opcional, pero si viene, debe cumplir regla estricta
	if (!empty($mail_proveedor) && !validarCorreo($mail_proveedor)) {
		notify('Error en mail, puede ingresar varios correos separados por coma y espacio', 'error');
		exit;
	}

	if (empty($ruc_empresa)) {
		notify('La sesión ha expirado, reingrese al sistema.', 'error');
		exit;
	}

	// ==========================
	// INSERT
	// ==========================
	if ($id_proveedor == 0) {

		// Existe?
		$stmt = mysqli_prepare($con, "SELECT 1 FROM proveedores WHERE ruc_proveedor = ? AND ruc_empresa = ? LIMIT 1");
		if (!$stmt) {
			notify('Error interno (prepare select)', 'error');
			exit;
		}

		mysqli_stmt_bind_param($stmt, "ss", $ruc_proveedor, $ruc_empresa);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_store_result($stmt);

		if (mysqli_stmt_num_rows($stmt) > 0) {
			mysqli_stmt_close($stmt);
			notify('El proveedor ya está registrado', 'error');
			exit;
		}
		mysqli_stmt_close($stmt);

		// Insert
		$sql = "INSERT INTO proveedores
                (razon_social, nombre_comercial, ruc_empresa, tipo_id_proveedor, ruc_proveedor, mail_proveedor, dir_proveedor, telf_proveedor, tipo_empresa, plazo, id_banco, tipo_cta, numero_cta)
                VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

		$stmt = mysqli_prepare($con, $sql);
		if (!$stmt) {
			notify('Error interno (prepare insert)', 'error');
			exit;
		}

		// tipos: 9 strings + 4 ints? ojo:
		// razon(s), nombre_comercial(s), ruc_empresa(s), tipo_id(s), ruc_proveedor(s), mail(s), dir(s), telf(s), tipo_empresa(s),
		// plazo(i), id_banco(i), tipo_cta(i), numero_cta(s)
		mysqli_stmt_bind_param(
			$stmt,
			"sssssssssiiis",
			$razon_social,
			$nombre_comercial,
			$ruc_empresa,
			$tipo_id,
			$ruc_proveedor,
			$mail_proveedor,
			$direccion_proveedor,
			$telefono_proveedor,
			$tipo_empresa,
			$plazo,
			$id_banco,
			$tipo_cta,
			$numero_cta
		);

		if (mysqli_stmt_execute($stmt)) {
			mysqli_stmt_close($stmt);
			echo "<script>
                $.notify('Proveedor registrado','success');
                if (document.querySelector('#formProveedor')) { document.querySelector('#formProveedor').reset(); }
                if (typeof load === 'function') { load(1); }
            </script>";
			exit;
		} else {
			mysqli_stmt_close($stmt);
			notify('No es posible registrar, intente de nuevo', 'error');
			exit;
		}
	}

	// ==========================
	// UPDATE
	// ==========================

	// Existe otro con el mismo documento?
	$stmt = mysqli_prepare($con, "SELECT 1 FROM proveedores WHERE ruc_empresa = ? AND ruc_proveedor = ? AND id_proveedor != ? LIMIT 1");
	if (!$stmt) {
		notify('Error interno (prepare select update)', 'error');
		exit;
	}

	mysqli_stmt_bind_param($stmt, "ssi", $ruc_empresa, $ruc_proveedor, $id_proveedor);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_store_result($stmt);

	if (mysqli_stmt_num_rows($stmt) > 0) {
		mysqli_stmt_close($stmt);
		notify('El proveedor ya está registrado', 'error');
		exit;
	}
	mysqli_stmt_close($stmt);

	// Update (mantengo unidad_tiempo='Días' como lo tenías)
	$sql = "UPDATE proveedores SET
                razon_social = ?,
                nombre_comercial = ?,
                tipo_id_proveedor = ?,
                ruc_proveedor = ?,
                mail_proveedor = ?,
                dir_proveedor = ?,
                telf_proveedor = ?,
                tipo_empresa = ?,
                plazo = ?,
                unidad_tiempo = 'Días',
                id_banco = ?,
                tipo_cta = ?,
                numero_cta = ?
            WHERE id_proveedor = ? AND ruc_empresa = ?";

	$stmt = mysqli_prepare($con, $sql);
	if (!$stmt) {
		notify('Error interno (prepare update)', 'error');
		exit;
	}

	// tipos:
	// razon(s) nombre_comercial(s) tipo_id(s) ruc(s) mail(s) dir(s) telf(s) tipo_empresa(s)
	// plazo(i) id_banco(i) tipo_cta(i) numero_cta(s) id_proveedor(i) ruc_empresa(s)
	mysqli_stmt_bind_param(
		$stmt,
		"ssssssssiiisis",
		$razon_social,
		$nombre_comercial,
		$tipo_id,
		$ruc_proveedor,
		$mail_proveedor,
		$direccion_proveedor,
		$telefono_proveedor,
		$tipo_empresa,
		$plazo,
		$id_banco,
		$tipo_cta,
		$numero_cta,
		$id_proveedor,
		$ruc_empresa
	);

	if (mysqli_stmt_execute($stmt)) {
		mysqli_stmt_close($stmt);
		echo "<script>
            $.notify('Proveedor actualizado','success');
            // Si estás en modal, lo puedes cerrar aquí si aplica:
            // if ($('#modalProveedor').length) { $('#modalProveedor').modal('hide'); }
            if (typeof load === 'function') { load(1); }
        </script>";
		exit;
	} else {
		mysqli_stmt_close($stmt);
		notify('No es posible actualizar, intente de nuevo', 'error');
		exit;
	}
} */


?>