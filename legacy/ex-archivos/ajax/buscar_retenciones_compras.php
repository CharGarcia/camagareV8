<?php
/* Connect To Database*/
include("../conexiones/conectalogin.php");
include("../clases/anular_registros.php");
require_once("../helpers/helpers.php");
$anular_asiento_contable = new anular_registros();
$con = conenta_login();
session_start();
date_default_timezone_set('America/Guayaquil');
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
$fecha_registro = date("Y-m-d H:i:s");
//PARA ELIMINAR RETENCIONES HECHAS

$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

if ($action == 'cancelar_envio_sri') {
	$id = $_POST["id"];
	$page = $_POST["page"];
	$busca_estado = mysqli_query($con, "SELECT estado_sri FROM encabezado_retencion WHERE id_encabezado_retencion='" . $id . "' ");
	$row_estado = mysqli_fetch_assoc($busca_estado);
	$estado_sri = $row_estado['estado_sri'];

	if ($estado_sri === "ENVIANDO") {
		$update_estado = mysqli_query($con, "UPDATE encabezado_retencion SET estado_sri='PENDIENTE' WHERE id_encabezado_retencion='" . $id . "' ");
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


if ($action == 'eliminar_retencion_compras') {

	// ---- Validaciones iniciales ----
	if (!isset($_POST['id_retencion']) || !is_numeric($_POST['id_retencion'])) {
		echo "<script>$.notify('ID de retención inválido.','error');</script>";
		exit;
	}
	$id_retencion = intval($_POST['id_retencion']);

	// Trae datos y verifica que pertenezca a la empresa activa
	$sqlBusca = "
        SELECT er.id_encabezado_retencion, er.serie_retencion, er.secuencial_retencion, er.id_registro_contable
        FROM encabezado_retencion er
        WHERE er.id_encabezado_retencion = ? AND er.ruc_empresa = ?
        LIMIT 1
    ";
	if (!($stmt = mysqli_prepare($con, $sqlBusca))) {
		error_log('Prepare falla: ' . mysqli_error($con));
		echo "<script>$.notify('No se pudo preparar la consulta.','error');</script>";
		exit;
	}
	mysqli_stmt_bind_param($stmt, "is", $id_retencion, $ruc_empresa);
	mysqli_stmt_execute($stmt);
	$res = mysqli_stmt_get_result($stmt);
	$datos_retencion = mysqli_fetch_assoc($res);
	mysqli_stmt_close($stmt);

	if (!$datos_retencion) {
		echo "<script>$.notify('Registro no encontrado o no pertenece a la empresa.','warn');</script>";
		exit;
	}

	$serie_retencion      = $datos_retencion['serie_retencion'];
	$secuencial_retencion = $datos_retencion['secuencial_retencion'];
	$id_registro_contable = (int)$datos_retencion['id_registro_contable'];

	// ---- Transacción: todo o nada ----
	mysqli_autocommit($con, false); // Desactiva autocommit
	$todo_ok = true;
	$mensaje_error = '';

	// 1) Si existe asiento, anularlo primero (debe usar la MISMA conexión para quedar dentro de la transacción)
	if ($id_registro_contable > 0) {
		// Se asume que el método devuelve true/false o un array con "ok"
		$resultado_anular = $anular_asiento_contable->anular_asiento_contable($con, $id_registro_contable);
		if ((is_array($resultado_anular) && empty($resultado_anular['ok'])) || ($resultado_anular === false)) {
			$todo_ok = false;
			$mensaje_error = 'No se pudo anular el asiento contable.';
		}
	}

	// 2) Borrar detalles adicionales (hijos)
	if ($todo_ok) {
		$sqlDelAdic = "
            DELETE FROM detalle_adicional_retencion
            WHERE ruc_empresa = ? AND serie_retencion = ? AND secuencial_retencion = ?
        ";
		if (!($stmt = mysqli_prepare($con, $sqlDelAdic))) {
			$todo_ok = false;
			$mensaje_error = 'No se pudo preparar borrado de detalles adicionales.';
		} else {
			mysqli_stmt_bind_param($stmt, "sss", $ruc_empresa, $serie_retencion, $secuencial_retencion);
			if (!mysqli_stmt_execute($stmt)) {
				$todo_ok = false;
				$mensaje_error = 'Error al borrar detalles adicionales.';
				error_log('MySQL detalle_adicional_retencion: ' . mysqli_error($con));
			}
			mysqli_stmt_close($stmt);
		}
	}

	// 3) Borrar cuerpo de retención (hijos)
	if ($todo_ok) {
		$sqlDelCuerpo = "
            DELETE FROM cuerpo_retencion
            WHERE ruc_empresa = ? AND serie_retencion = ? AND secuencial_retencion = ?
        ";
		if (!($stmt = mysqli_prepare($con, $sqlDelCuerpo))) {
			$todo_ok = false;
			$mensaje_error = 'No se pudo preparar borrado del cuerpo de la retención.';
		} else {
			mysqli_stmt_bind_param($stmt, "sss", $ruc_empresa, $serie_retencion, $secuencial_retencion);
			if (!mysqli_stmt_execute($stmt)) {
				$todo_ok = false;
				$mensaje_error = 'Error al borrar el cuerpo de la retención.';
				error_log('MySQL cuerpo_retencion: ' . mysqli_error($con));
			}
			mysqli_stmt_close($stmt);
		}
	}

	// 4) Borrar encabezado (padre)
	if ($todo_ok) {
		$sqlDelEnc = "DELETE FROM encabezado_retencion WHERE id_encabezado_retencion = ? AND ruc_empresa = ?";
		if (!($stmt = mysqli_prepare($con, $sqlDelEnc))) {
			$todo_ok = false;
			$mensaje_error = 'No se pudo preparar borrado del encabezado.';
		} else {
			mysqli_stmt_bind_param($stmt, "is", $id_retencion, $ruc_empresa);
			if (!mysqli_stmt_execute($stmt)) {
				$todo_ok = false;
				$mensaje_error = 'Error al borrar el encabezado de la retención.';
				error_log('MySQL encabezado_retencion: ' . mysqli_error($con));
			}
			mysqli_stmt_close($stmt);
		}
	}

	// ---- Commit o Rollback ----
	if ($todo_ok) {
		if (!mysqli_commit($con)) {
			mysqli_rollback($con);
			echo "<script>$.notify('No se pudo confirmar la transacción. Intente nuevamente.','error');</script>";
		} else {
			echo "<script>$.notify('Retención eliminada correctamente.','success');</script>";
		}
	} else {
		mysqli_rollback($con);
		$msg = $mensaje_error !== '' ? $mensaje_error : 'Lo siento, algo salió mal. Intenta nuevamente.';
		echo "<script>$.notify('" . $msg . "','error');</script>";
	}

	// Restaurar autocommit
	mysqli_autocommit($con, true);
}


//PARA BUSCAR LAS RETENCIONES
if ($action == 'buscar_retenciones_compras') {

	// ==== Entradas (compatibles con PHP 5.6, sin ??) ====
	$q           = isset($_REQUEST['q'])        ? trim($_REQUEST['q'])        : '';
	$ordenado    = isset($_GET['ordenado'])     ? trim($_GET['ordenado'])     : '';
	$por         = isset($_GET['por'])          ? trim($_GET['por'])          : '';
	$estado      = isset($_GET['estado'])       ? trim($_GET['estado'])       : '';
	$anio_ret    = isset($_GET['anio_ret'])     ? trim($_GET['anio_ret'])     : '';
	$mes_ret     = isset($_GET['mes_ret'])      ? trim($_GET['mes_ret'])      : '';

	// ==== Columnas permitidas para ordenar (whitelist) ====
	$sortable = array(
		'fecha_emision'          => 'er.fecha_emision',
		'secuencial_retencion'   => 'er.secuencial_retencion',
		'serie_retencion'        => 'er.serie_retencion',
		'numero_comprobante'     => 'er.numero_comprobante',
		'tipo_comprobante'       => 'er.tipo_comprobante',
		'razon_social'           => 'pr.razon_social',
		'nombre_comercial'       => 'pr.nombre_comercial'
	);

	// Normaliza ORDER BY
	$orden_col = isset($sortable[$ordenado]) ? $sortable[$ordenado] : 'er.fecha_emision';
	$orden_dir = strtoupper($por) === 'ASC' ? 'ASC' : 'DESC';

	// ==== Tablas y joins ====
	$sTable = "
  encabezado_retencion AS er
  LEFT JOIN comprobantes_autorizados AS ca ON er.tipo_comprobante = ca.codigo_comprobante
  LEFT JOIN proveedores AS pr ON er.id_proveedor = pr.id_proveedor
";

	// ==== Filtros base ====
	$filters = array();
	$filters[] = "er.ruc_empresa = '" . mysqli_real_escape_string($con, $ruc_empresa) . "'";

	if ($estado !== '') {
		$filters[] = "er.estado_sri = '" . mysqli_real_escape_string($con, $estado) . "'";
	}
	if ($anio_ret !== '') {
		$filters[] = "YEAR(er.fecha_emision) = '" . mysqli_real_escape_string($con, $anio_ret) . "'";
	}
	if ($mes_ret !== '') {
		$filters[] = "MONTH(er.fecha_emision) = '" . mysqli_real_escape_string($con, $mes_ret) . "'";
	}

	// ==== Búsqueda por términos (cada palabra debe aparecer en alguna columna) ====
	$aColumns = array(
		'er.fecha_emision',
		'er.secuencial_retencion',
		'er.serie_retencion',
		'er.numero_comprobante',
		'er.tipo_comprobante',
		'pr.razon_social',
		'pr.nombre_comercial'
	);

	if ($q !== '') {
		// Divide por espacios y arma ( (col LIKE %t1% OR col2 LIKE %t1% ... ) AND ( ... t2 ... ) )
		$tokens = preg_split('/\s+/', $q);
		$tokenClauses = array();

		foreach ($tokens as $t) {
			if ($t === '') continue;
			$term = mysqli_real_escape_string($con, $t);
			$ors = array();
			foreach ($aColumns as $col) {
				$ors[] = $col . " LIKE '%" . $term . "%'";
			}
			if (!empty($ors)) {
				$tokenClauses[] = '(' . implode(' OR ', $ors) . ')';
			}
		}

		if (!empty($tokenClauses)) {
			$filters[] = implode(' AND ', $tokenClauses);
		}
	}

	// WHERE final
	$sWhere = '';
	if (!empty($filters)) {
		$sWhere = 'WHERE ' . implode(' AND ', $filters);
	}

	// ==== Paginación ====
	include("../ajax/pagination.php");

	$page       = (isset($_REQUEST['page']) && intval($_REQUEST['page']) > 0) ? intval($_REQUEST['page']) : 1;
	$per_page   = 20;
	$adjacents  = 4;
	$offset     = ($page - 1) * $per_page;
	$reload     = '../retenciones_compras.php';

	// ==== Conteo ====
	$sql_count = "SELECT COUNT(*) AS numrows FROM $sTable $sWhere";
	$count_query = mysqli_query($con, $sql_count);
	if (!$count_query) {
		// Manejo de error simple y seguro para producción
		error_log('MySQL error en COUNT: ' . mysqli_error($con) . ' | SQL: ' . $sql_count);
		$numrows = 0;
	} else {
		$row = mysqli_fetch_assoc($count_query);
		$numrows = isset($row['numrows']) ? intval($row['numrows']) : 0;
	}
	$total_pages = ($per_page > 0) ? ceil($numrows / $per_page) : 0;

	// ==== Consulta principal ====
	$select = "
  er.id_encabezado_retencion,
  er.ruc_empresa,
  er.fecha_emision,
  er.fecha_documento,
  er.secuencial_retencion,
  er.serie_retencion,
  er.numero_comprobante,
  er.tipo_comprobante,
  er.estado_sri,
  er.aut_sri,
  er.total_retencion,
  er.ambiente,
  er.estado_mail,
  ca.comprobante,
  pr.id_proveedor,
  pr.ruc_proveedor,
  pr.razon_social,
  pr.mail_proveedor,
  pr.nombre_comercial
";

	$sql = "
  SELECT $select
  FROM $sTable
  $sWhere
  ORDER BY $orden_col $orden_dir
  LIMIT $offset, $per_page
";

	$query = mysqli_query($con, $sql);
	if (!$query) {
		error_log('MySQL error en SELECT: ' . mysqli_error($con) . ' | SQL: ' . $sql);
		// Puedes retornar un JSON de error o mostrar un mensaje controlado.
	}

	if ($numrows > 0) {
?>
		<div class="panel panel-info">
			<div class="table-responsive">
				<table class="table table-hover">
					<tr class="info">
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("fecha_emision");'>Fecha retención</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("fecha_documento");'>Fecha documento</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("razon_social");'> Proveedor</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("secuencial_retencion");'>Número retención</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("total_retencion");'>Total</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("tipo_comprobante");'>Documento</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("numero_comprobante");'>#Documento</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("estado_sri");'>Estado SRI</button></th>
						<th class='text-right'>Opciones</th>
						<input type="hidden" value="<?php echo $page; ?>" id="pagina">
					</tr>
					<?php

					while ($row = mysqli_fetch_array($query)) {
						$id_encabezado_retencion = $row['id_encabezado_retencion'];
						$fecha_retencion = $row['fecha_emision'];
						$fecha_documento = $row['fecha_documento'];
						$serie_retencion = $row['serie_retencion'];
						$secuencial_retencion = $row['secuencial_retencion'];
						$nombre_proveedor = $row['razon_social'];
						$ruc_proveedor = $row['ruc_proveedor'];
						$tipo_documento = $row['comprobante'];
						$numero_comprobante = $row['numero_comprobante'];
						$estado_sri = $row['estado_sri'];
						$total_retencion = $row['total_retencion'];
						$ambiente = $row['ambiente'];
						$mail_proveedor = $row['mail_proveedor'];
						$estado_mail = $row['estado_mail'];
						$aut_sri = $row['aut_sri'];

						$respuesta_sri = mysqli_query($con, "SELECT * FROM respuestas_sri WHERE id_documento = '" . $id_encabezado_retencion . "' and documento='retencion' and status='1' ");
						$row_sri = mysqli_fetch_array($respuesta_sri);
						$mensaje_sri = !empty($row_sri['mensajes']) ? '<span class="badge" title="' . $row_sri['mensajes'] . '">i</span>' : "";

						$numero_ret = $serie_retencion . "-" . str_pad($secuencial_retencion, 9, "000000000", STR_PAD_LEFT);

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
						<input type="hidden" value="<?php echo $mail_proveedor; ?>" id="mail_proveedor<?php echo $id_encabezado_retencion; ?>">
						<input type="hidden" value="<?php echo $serie_retencion; ?>" id="serie_retencion<?php echo $id_encabezado_retencion; ?>">
						<input type="hidden" value="<?php echo $secuencial_retencion; ?>" id="secuencial_retencion<?php echo $id_encabezado_retencion; ?>">
						<tr>
							<td><?php echo date("d/m/Y", strtotime($fecha_retencion)); ?></td>
							<td><?php echo date("d/m/Y", strtotime($fecha_documento)); ?></td>
							<td class="col-xs-2"><?php echo strtoupper($nombre_proveedor); ?></td>
							<td><?php echo $serie_retencion; ?>-<?php echo str_pad($secuencial_retencion, 9, "000000000", STR_PAD_LEFT); ?></td>
							<td><?php echo $total_retencion; ?></td>
							<td class="col-xs-1"><?php echo strtoupper($tipo_documento); ?></td>
							<td class="col-xs-1"><?php echo $numero_comprobante; ?></td>
							<td><span class="label <?php echo $label_class_sri; ?>"><?php echo $estado_sri; ?><?php echo $mensaje_sri; ?></span></td>
							<td><span class="pull-right">

									<?php
									//PARA ENVIAR AL SRI
									$tipo_retencion = "ELECTRÓNICA";
									if ($tipo_retencion == "ELECTRÓNICA") {
										switch ($estado_sri) {
											case "PENDIENTE";
											case "DEVUELTA";
												if (getPermisos($con, $id_usuario, $ruc_empresa, 'retenciones_compras')['w'] == 1) {
									?>
													<a class='btn btn-success btn-xs' onclick="enviar_retencion_sri('<?php echo $id_encabezado_retencion; ?>');" title='Enviar al SRI'><i class="glyphicon glyphicon-send"></i></a>
												<?php
												}
												break;
										}
									}

									switch ($estado_sri) {
										case "AUTORIZADO";
										case "ANULADA";
											if (getPermisos($con, $id_usuario, $ruc_empresa, 'retenciones_compras')['r'] == 1) {
												?>
												<a href="../ajax/imprime_documento.php?id_documento=<?php echo base64_encode($id_encabezado_retencion) ?>&tipo_documento=retencion&tipo_archivo=pdf" class='btn btn-default btn-xs' title='Ver' download target="_blank">Pdf</i> </a>
												<a href="../ajax/imprime_documento.php?id_documento=<?php echo base64_encode($id_encabezado_retencion) ?>&tipo_documento=retencion&tipo_archivo=xml" class='btn btn-default btn-xs' title='Ver' download target="_blank">Xml</i> </a>
											<?php
											}
											break;
									}

									switch ($estado_sri) {
										case "ENVIANDO";
											if (getPermisos($con, $id_usuario, $ruc_empresa, 'retenciones_compras')['r'] == 1) {
											?>
												<b>Enviando al SRI... </b><a class='btn btn-default btn-xs' title='Cancelar envio' onclick="cancelar_envio_sri('<?php echo $id_encabezado_retencion; ?>')">Cancelar </a>
											<?php
											}
											break;
									}

									//para cuando esta anulada y ambiente de produccion
									if ($estado_sri == "PENDIENTE") {
										if (getPermisos($con, $id_usuario, $ruc_empresa, 'retenciones_compras')['d'] == 1) {
											?>
											<a class='btn btn-danger btn-xs' title='Eliminar retencion' onclick="eliminar_retencion_compras('<?php echo $id_encabezado_retencion; ?>')"><i class="glyphicon glyphicon-erase"></i> </a>
											<?php
										}
									}

									switch ($estado_sri) {
										case "PENDIENTE";
										case "DEVUELTA";
										case "AUTORIZADO";
											if (getPermisos($con, $id_usuario, $ruc_empresa, 'retenciones_compras')['r'] == 1) {
											?>
												<a class='btn btn-info btn-xs' onclick="detalle_retencion_compra('<?php echo $id_encabezado_retencion; ?>')" title="Detalle documento" data-toggle="modal" data-target="#detalleDocumento"><i class="glyphicon glyphicon-list-alt"></i></a>
											<?php
											}
											break;
									}
									if (getPermisos($con, $id_usuario, $ruc_empresa, 'retenciones_compras')['r'] == 1) {
										if ($estado_sri == "AUTORIZADO") {
											?>
											<a class="<?php echo $estado_mail_final; ?>" onclick="enviar_retencion_mail('<?php echo $id_encabezado_retencion; ?>');" title='Enviar por mail al proveedor' data-toggle="modal" data-target="#EnviarDocumentosMail"><i class="glyphicon glyphicon-envelope"></i> </a>

											<?php
										}

										if ($estado_sri == "AUTORIZADO") {
											if (getPermisos($con, $id_usuario, $ruc_empresa, 'retenciones_compras')['d'] == 1) {
												if (mostrarBotonAnular($fecha_retencion)) {
											?>
													<a class='btn btn-warning btn-xs' title='Anular retención' data-toggle="modal" data-target="#AnularDocumentosSri" onclick="anular_documento_en_sri('<?php echo $id_encabezado_retencion; ?>')"><i class="glyphicon glyphicon-remove"></i> </a>
									<?php
												}
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