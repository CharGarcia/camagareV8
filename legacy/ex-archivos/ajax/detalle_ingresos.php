<?PHP
include("../ajax/detalle_documento.php");
//include("../conexiones/conectalogin.php");
//session_start();
$con = conenta_login();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];

$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

if ($action == 'saldos_cuentas_por_cobrar') {
	generar_cuentas_por_cobrar();
	generar_cuentas_por_cobrar_recibos();
}

if ($action == 'actualiza_ingreso_tmp') {
	actualiza_ingreso_tmp();
}

//ACTUALIZA EL REGISTRO que se agrego al egreso actual que se esta haciendo

function actualiza_ingreso_tmp()
{
	$con = conenta_login();
	$ruc_empresa = $_SESSION['ruc_empresa'];
	//para borrar las que tienen saldo cero
	$update_ingresos_tmp = mysqli_query($con, "UPDATE saldo_porcobrar_porpagar as sal_tmp, (SELECT iet.id_documento as registro, sum(iet.valor) as suma_ingreso_tmp FROM ingresos_egresos_tmp as iet WHERE iet.tipo_documento='INGRESO' group by iet.id_documento) as total_ingreso_tmp SET sal_tmp.ing_tmp = total_ingreso_tmp.suma_ingreso_tmp WHERE total_ingreso_tmp.registro=sal_tmp.id_documento");
}


function generar_cuentas_por_cobrar()
{
	$con = conenta_login();
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$id_usuario = $_SESSION['id_usuario'];
	$ruc_base = substr($ruc_empresa, 0, 10); // ✅ Prefijo solo una vez

	// 1️⃣ ELIMINAR registros anteriores del usuario actual (más rápido si hay índice en ruc_empresa y id_usuario)
	mysqli_query($con, "
        DELETE FROM saldo_porcobrar_porpagar 
        WHERE id_usuario = '$id_usuario' 
        AND mid(ruc_empresa,1,10) = '$ruc_base'
        AND tipo = 'POR_COBRAR'
    ");

	// 2️⃣ INSERTAR todas las facturas autorizadas en una sola consulta
	mysqli_query($con, "
        INSERT INTO saldo_porcobrar_porpagar (
            tipo, fecha_documento, id_cli_pro, nombre_cli_pro, numero_documento, 
            id_usuario, ruc_empresa, total_factura, total_nc, total_ing, ing_tmp, total_ret, id_documento
        )
        SELECT 
            'POR_COBRAR', enc_fac.fecha_factura, enc_fac.id_cliente, cli.nombre,
            CONCAT(enc_fac.serie_factura,'-', LPAD(enc_fac.secuencial_factura,9,'0')),
            '$id_usuario', '$ruc_empresa',
            enc_fac.total_factura, 0, 0, 0, 0, enc_fac.id_encabezado_factura
        FROM encabezado_factura enc_fac
        INNER JOIN clientes cli ON cli.id = enc_fac.id_cliente
        WHERE enc_fac.estado_sri = 'AUTORIZADO' 
        AND enc_fac.ruc_empresa = '$ruc_empresa'
    ");

	// 3️⃣ ACTUALIZAR notas de crédito en bloque
	mysqli_query($con, "
        UPDATE saldo_porcobrar_porpagar sal
        INNER JOIN (
            SELECT nc.factura_modificada AS codigo_registro, ROUND(SUM(nc.total_nc), 2) AS suma_nc
            FROM encabezado_nc nc
            WHERE nc.ruc_empresa = '$ruc_empresa'
            GROUP BY nc.factura_modificada
        ) AS nc_tot ON sal.numero_documento = nc_tot.codigo_registro
        SET sal.total_nc = nc_tot.suma_nc
    ");

	// 4️⃣ ACTUALIZAR ingresos en bloque (indexar detie.codigo_documento_cv ⚡)
	mysqli_query($con, "
        UPDATE saldo_porcobrar_porpagar sal
        INNER JOIN (
            SELECT detie.codigo_documento_cv AS codigo_registro, ROUND(SUM(detie.valor_ing_egr),2) AS suma_ingresos
            FROM detalle_ingresos_egresos detie
            INNER JOIN ingresos_egresos ing_egr ON ing_egr.codigo_documento = detie.codigo_documento
            WHERE detie.estado = 'OK'
              AND detie.tipo_ing_egr = 'CCXCC'
              AND detie.tipo_documento = 'INGRESO'
              AND MID(detie.ruc_empresa,1,10) = '$ruc_base'
            GROUP BY detie.codigo_documento_cv
        ) AS ing ON sal.id_documento = ing.codigo_registro
        SET sal.total_ing = ing.suma_ingresos
    ");

	// 5️⃣ ACTUALIZAR retenciones en bloque (usar índice en numero_documento)
	mysqli_query($con, "
        UPDATE saldo_porcobrar_porpagar sal
        INNER JOIN (
            SELECT ret_ven.numero_documento AS registro, ROUND(SUM(ret_ven.valor_retenido),2) AS suma_retenciones
            FROM cuerpo_retencion_venta ret_ven
            WHERE MID(ret_ven.ruc_empresa,1,10) = '$ruc_base'
            GROUP BY ret_ven.numero_documento
        ) AS ret ON REPLACE(sal.numero_documento,'-','') = ret.registro
        SET sal.total_ret = ret.suma_retenciones
    ");

	// 6️⃣ ELIMINAR facturas ya canceladas (saldo 0)
	mysqli_query($con, "
        DELETE FROM saldo_porcobrar_porpagar 
        WHERE mid(ruc_empresa,1,10) = '$ruc_base'
        AND total_factura <= (total_nc + total_ing + ing_tmp + total_ret)
    ");
}

function generar_cuentas_por_cobrar_recibos()
{
	$con = conenta_login();
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$id_usuario = $_SESSION['id_usuario'];
	$ruc_base = substr($ruc_empresa, 0, 10); // ✅ Prefijo solo una vez

	// 1️⃣ ELIMINAR registros anteriores del usuario actual (más rápido si hay índice en ruc_empresa y id_usuario)
	mysqli_query($con, "
        DELETE FROM saldo_porcobrar_porpagar 
        WHERE id_usuario = '$id_usuario' 
        AND mid(ruc_empresa,1,10) = '$ruc_base'
        AND tipo = 'POR_COBRAR_RECIBOS'
    ");

	// 2️⃣ INSERTAR todas las recibos autorizadas en una sola consulta
	mysqli_query($con, "
        INSERT INTO saldo_porcobrar_porpagar (
            tipo, fecha_documento, id_cli_pro, nombre_cli_pro, numero_documento, 
            id_usuario, ruc_empresa, total_factura, total_nc, total_ing, ing_tmp, total_ret, id_documento
        )
        SELECT 
            'POR_COBRAR_RECIBOS', enc_rec.fecha_recibo, enc_rec.id_cliente, cli.nombre,
            CONCAT(enc_rec.serie_recibo,'-', LPAD(enc_rec.secuencial_recibo,9,'0')),
            '$id_usuario', '$ruc_empresa',
            enc_rec.total_recibo, 0, 0, 0, 0, concat('RV', enc_rec.id_encabezado_recibo)
        FROM encabezado_recibo enc_rec
        INNER JOIN clientes cli ON cli.id = enc_rec.id_cliente
        WHERE enc_rec.status != '2' 
        AND enc_rec.ruc_empresa = '$ruc_empresa'
    ");

	// 3️⃣ ACTUALIZAR notas de crédito en bloque

	// 4️⃣ ACTUALIZAR ingresos en bloque (indexar detie.codigo_documento_cv ⚡)
	mysqli_query($con, "
        UPDATE saldo_porcobrar_porpagar sal
        INNER JOIN (
            SELECT detie.codigo_documento_cv AS codigo_registro, ROUND(SUM(detie.valor_ing_egr),2) AS suma_ingresos
            FROM detalle_ingresos_egresos detie
            INNER JOIN ingresos_egresos ing_egr ON ing_egr.codigo_documento = detie.codigo_documento
            WHERE detie.estado = 'OK'
              AND detie.tipo_ing_egr = 'CCXRC'
              AND detie.tipo_documento = 'INGRESO'
              AND MID(detie.ruc_empresa,1,10) = '$ruc_base'
            GROUP BY detie.codigo_documento_cv
        ) AS ing ON sal.id_documento = ing.codigo_registro
        SET sal.total_ing = ing.suma_ingresos
    ");

	// 5️⃣ ACTUALIZAR retenciones en bloque (usar índice en numero_documento)

	// 6️⃣ ELIMINAR facturas ya canceladas (saldo 0)
	mysqli_query($con, "
        DELETE FROM saldo_porcobrar_porpagar 
        WHERE mid(ruc_empresa,1,10) = '$ruc_base'
        AND total_factura <= (total_nc + total_ing + ing_tmp + total_ret)
    ");
}


//buscar facturas por cobrar
if ($action == 'facturas_por_cobrar') {

	// 🔹 Limpieza de entrada (compatible PHP 5.6)
	$q = trim(mysqli_real_escape_string($con, strip_tags(isset($_REQUEST['fv']) ? $_REQUEST['fv'] : '', ENT_QUOTES)));

	// 🔹 Columnas a buscar
	$aColumns = array('fecha_documento', 'numero_documento', 'nombre_cli_pro');

	// 🔹 Tabla
	$sTable = "saldo_porcobrar_porpagar";

	// 🔹 Condición base
	$baseWhere = "mid(ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "'  
                  AND id_usuario = '" . $id_usuario . "' 
                  AND tipo='POR_COBRAR' 
                  AND (total_factura - total_nc - total_ing - ing_tmp - total_ret) > 0";

	$sWhere = "WHERE $baseWhere";

	// ✅ Si hay búsqueda, procesar palabras
	if (!empty($q)) {
		$palabras = explode(" ", $q); // divide la búsqueda por espacios
		$condicionesPalabras = array();

		foreach ($palabras as $palabra) {
			$palabra = trim($palabra);
			if ($palabra !== "") {
				$subCondiciones = array();
				foreach ($aColumns as $col) {
					$subCondiciones[] = "$col LIKE '%$palabra%'";
				}
				// Cada palabra debe coincidir al menos en una columna
				$condicionesPalabras[] = "(" . implode(" OR ", $subCondiciones) . ")";
			}
		}

		// ✅ Todas las palabras deben cumplirse (con AND)
		if (!empty($condicionesPalabras)) {
			$sWhere = "WHERE ($baseWhere) AND " . implode(" AND ", $condicionesPalabras);
		}
	}

	// 🔹 Orden
	$orderBy = " ORDER BY fecha_documento ASC";

	include("../ajax/pagination.php");

	// 🔹 Paginación
	$page       = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
	$per_page   = 20;
	$adjacents  = 10;
	$offset     = ($page - 1) * $per_page;

	// 🔹 Contar total de registros
	$count_query = mysqli_query($con, "SELECT COUNT(*) AS numrows FROM $sTable $sWhere");
	$row = mysqli_fetch_assoc($count_query);
	$numrows = isset($row['numrows']) ? $row['numrows'] : 0;
	$total_pages = ceil($numrows / $per_page);
	$reload = '../facturas.php';

	// 🔹 Consulta principal
	$sql = "SELECT * FROM $sTable $sWhere $orderBy LIMIT $offset, $per_page";
	$query = mysqli_query($con, $sql);

	// 🔹 Mostrar resultados
	if ($numrows > 0) {
?>
		<div class="panel panel-info" style="height: 300px; overflow-y: auto;">
			<div class="table-responsive">
				<table class="table table-hover">
					<tr class="info">
						<th style="padding: 2px;">Fecha</th>
						<th style="padding: 2px;">Cliente</th>
						<th style="padding: 2px;">Número</th>
						<th style="padding: 2px;">Saldo</th>
						<th style="padding: 2px;"><span class="glyphicon glyphicon-copy"></span></th>
						<th style="padding: 2px;">Cobro</th>
						<input type="hidden" value="<?php echo $page; ?>" id="pagina">
					</tr>
					<?php while ($row = mysqli_fetch_assoc($query)) :
						$id_saldo = $row['id_saldo'];
						$id_documento = $row['id_documento'];
						$fecha_documento = $row['fecha_documento'];
						$id_cli_pro = $row['id_cli_pro'];
						$nombre_cli_pro = strtoupper($row['nombre_cli_pro']);
						$numero_documento = $row['numero_documento'];
						$saldo = $row['total_factura'] - $row['total_nc'] - $row['total_ing'] - $row['ing_tmp'] - $row['total_ret'];
						$detalle = $nombre_cli_pro . " " . $numero_documento;
					?>
						<tr>
							<input type="hidden" value="<?php echo htmlspecialchars($nombre_cli_pro); ?>" id="nombre_cliente_seleccionado<?php echo $id_saldo; ?>">
							<input type="hidden" value="<?php echo $id_cli_pro; ?>" id="id_cliente_seleccionado<?php echo $id_saldo; ?>">
							<input type="hidden" id="saldo<?php echo $id_saldo; ?>" value="<?php echo number_format($saldo, 2, '.', ''); ?>">
							<input type="hidden" name="registros[]" value="<?php echo $id_saldo; ?>">
							<input type="hidden" name="detalle[<?php echo $id_saldo; ?>]" value="<?php echo htmlspecialchars($detalle); ?>">
							<input type="hidden" name="id_documento[<?php echo $id_saldo; ?>]" value="<?php echo $id_documento; ?>">
							<input type="hidden" name="nombre_cliente[<?php echo $id_saldo; ?>]" value="<?php echo htmlspecialchars($nombre_cli_pro); ?>">

							<td style="padding: 2px;"><?php echo date("d/m/Y", strtotime($fecha_documento)); ?></td>
							<td style="padding: 2px;" class='col-md-4'><?php echo $nombre_cli_pro; ?><button class="btn btn-default btn-xs" type="button" title="Mostrar todos del mismo cliente" onclick="copyToClipboardFacturas('<?php echo $id_saldo; ?>')"><span class="glyphicon glyphicon-copy"></span></button></td>
							<td style="padding: 2px;"><?php echo $numero_documento; ?></td>
							<td style="padding: 2px;"><?php echo number_format($saldo, 2, '.', ''); ?></td>
							<td style="padding: 2px;">
								<button class="btn btn-default btn-sm" type="button" title="Pasar valor"
									onclick="copiar_valor('<?php echo $id_saldo; ?>')" id="linea_copia<?php echo $id_saldo; ?>">
									<span class="glyphicon glyphicon-arrow-right"></span>
								</button>
							</td>
							<td style="padding: 2px;" class='col-sm-2'>
								<input type="text" style="text-align:right;" class="form-control input-sm" title="Cobro"
									name="valor_cobro[<?php echo $id_saldo; ?>]" id="valor_cobro<?php echo $id_saldo; ?>"
									onchange="control_cobro('<?php echo $id_saldo; ?>');">
							</td>
						</tr>
					<?php endwhile; ?>
					<tr>
						<td colspan="7">
							<span class="pull-right">
								<?php echo paginate($reload, $page, $total_pages, $adjacents); ?>
							</span>
						</td>
					</tr>
				</table>
			</div>
		</div>
	<?php
	} // cierre de if $numrows > 0
} // cierre de if $action



//buscar recibos por cobrar
if ($action == 'recibos_por_cobrar') {

	// 🔹 Sanitizar el input (PHP 5.6 compatible)
	$q = trim(mysqli_real_escape_string($con, strip_tags(isset($_REQUEST['rv']) ? $_REQUEST['rv'] : '', ENT_QUOTES)));

	// 🔹 Columnas sobre las que buscar
	$aColumns = array('fecha_documento', 'numero_documento', 'nombre_cli_pro');

	// 🔹 Tabla
	$sTable = "saldo_porcobrar_porpagar";

	// 🔹 Condición base (solo recibos por cobrar)
	$baseWhere = "mid(ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "'  
                  AND id_usuario = '" . $id_usuario . "' 
                  AND tipo='POR_COBRAR_RECIBOS' 
                  AND (total_factura - total_nc - total_ing - ing_tmp - total_ret) > 0";

	$sWhere = "WHERE $baseWhere";

	// ✅ Si hay búsqueda, procesamos las palabras
	if (!empty($q)) {
		$palabras = explode(" ", $q); // dividir por espacio
		$condicionesPalabras = array();

		foreach ($palabras as $palabra) {
			$palabra = trim($palabra);
			if ($palabra !== "") {
				$subCondiciones = array();
				foreach ($aColumns as $col) {
					$subCondiciones[] = "$col LIKE '%$palabra%'";
				}
				// Cada palabra debe aparecer en al menos una columna
				$condicionesPalabras[] = "(" . implode(" OR ", $subCondiciones) . ")";
			}
		}

		if (!empty($condicionesPalabras)) {
			$sWhere = "WHERE ($baseWhere) AND " . implode(" AND ", $condicionesPalabras);
		}
	}

	// 🔹 Ordenar por fecha (separado de $sWhere para evitar conflictos)
	$orderBy = " ORDER BY fecha_documento ASC";

	include("../ajax/pagination.php");

	// 🔹 Paginación
	$page       = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
	$per_page   = 20;
	$adjacents  = 10;
	$offset     = ($page - 1) * $per_page;

	// 🔹 Contar total de registros
	$count_query = mysqli_query($con, "SELECT COUNT(*) AS numrows FROM $sTable $sWhere");
	$row = mysqli_fetch_assoc($count_query);
	$numrows = isset($row['numrows']) ? $row['numrows'] : 0;
	$total_pages = ceil($numrows / $per_page);
	$reload = '../facturas.php';

	// 🔹 Consulta principal
	$sql = "SELECT * FROM $sTable $sWhere $orderBy LIMIT $offset, $per_page";
	$query = mysqli_query($con, $sql);

	// 🔹 Mostrar resultados si hay datos
	if ($numrows > 0) {
	?>
		<div class="panel panel-info" style="height: 300px; overflow-y: auto;">
			<div class="table-responsive">
				<table class="table table-hover">
					<tr class="info">
						<th style="padding: 2px;">Fecha</th>
						<th style="padding: 2px;">Cliente</th>
						<th style="padding: 2px;">Número</th>
						<th style="padding: 2px;">Saldo</th>
						<th style="padding: 2px;"><span class="glyphicon glyphicon-copy"></span></th>
						<th style="padding: 2px;">Cobro</th>
						<input type="hidden" value="<?php echo $page; ?>" id="pagina">
					</tr>
					<?php while ($row = mysqli_fetch_assoc($query)) :
						$id_saldo = $row['id_saldo'];
						$id_documento = $row['id_documento'];
						$fecha_documento = $row['fecha_documento'];
						$id_cli_pro = $row['id_cli_pro'];
						$nombre_cli_pro = strtoupper($row['nombre_cli_pro']);
						$numero_documento = $row['numero_documento'];
						$saldo = $row['total_factura'] - $row['total_nc'] - $row['total_ing'] - $row['ing_tmp'] - $row['total_ret'];
						$detalle = $nombre_cli_pro . " " . $numero_documento;
					?>
						<tr>
							<input type="hidden" value="<?php echo htmlspecialchars($nombre_cli_pro); ?>" id="nombre_cliente_seleccionado<?php echo $id_saldo; ?>">
							<input type="hidden" value="<?php echo $id_cli_pro; ?>" id="id_cliente_seleccionado<?php echo $id_saldo; ?>">
							<input type="hidden" id="saldo<?php echo $id_saldo; ?>" value="<?php echo number_format($saldo, 2, '.', ''); ?>">
							<input type="hidden" name="registros[]" value="<?php echo $id_saldo; ?>">
							<input type="hidden" name="detalle[<?php echo $id_saldo; ?>]" value="<?php echo htmlspecialchars($detalle); ?>">
							<input type="hidden" name="id_documento[<?php echo $id_saldo; ?>]" value="<?php echo $id_documento; ?>">
							<input type="hidden" name="nombre_cliente[<?php echo $id_saldo; ?>]" value="<?php echo htmlspecialchars($nombre_cli_pro); ?>">

							<td style="padding: 2px;"><?php echo date("d/m/Y", strtotime($fecha_documento)); ?></td>
							<td style="padding: 2px;" class='col-md-4'><?php echo $nombre_cli_pro; ?> <button class="btn btn-default btn-xs" type="button" title="Mostrar todos del mismo cliente" onclick="copyToClipboardRecibos('<?php echo $id_saldo; ?>')"><span class="glyphicon glyphicon-copy"></span></button></td>
							<td style="padding: 2px;"><?php echo $numero_documento; ?></td>
							<td style="padding: 2px;"><?php echo number_format($saldo, 2, '.', ''); ?></td>
							<td style="padding: 2px;">
								<button class="btn btn-default btn-sm" type="button" title="Pasar valor"
									onclick="copiar_valor('<?php echo $id_saldo; ?>')" id="linea_copia<?php echo $id_saldo; ?>">
									<span class="glyphicon glyphicon-arrow-right"></span>
								</button>
							</td>
							<td style="padding: 2px;" class='col-sm-2'>
								<input type="text" style="text-align:right;" class="form-control input-sm" title="Cobro"
									name="valor_cobro[<?php echo $id_saldo; ?>]" id="valor_cobro<?php echo $id_saldo; ?>"
									onchange="control_cobro('<?php echo $id_saldo; ?>');">
							</td>
						</tr>
					<?php endwhile; ?>
					<tr>
						<td colspan="7">
							<span class="pull-right">
								<?php echo paginate($reload, $page, $total_pages, $adjacents); ?>
							</span>
						</td>
					</tr>
				</table>
			</div>
		</div>
	<?php
	}
}


//para agregar nuevo iten al ingreso
if ($action == 'agregar_detalle_ingreso') {
	$tipo_ingreso = $_GET["tipo_ingreso"];
	$valor_ingreso = $_GET["valor_ingreso"];
	$detalle_ingreso = $_GET["detalle_ingreso"];
	$beneficiario_ingreso = $_GET["nombre_beneficiario"];
	$agregar_ingreso = mysqli_query($con, "INSERT INTO ingresos_egresos_tmp VALUES (null, 'INGRESO', '" . $beneficiario_ingreso . "', '" . $detalle_ingreso . "', '" . $valor_ingreso . "', '" . $tipo_ingreso . "', '" . $id_usuario . "','0')");
	detalle_nuevo_ingreso();
}

//para agregar forma de pago al ingreso
if ($action == 'agregar_forma_pago_ingreso') {
	$forma_pago = $_GET["forma_pago"];
	$valor_pago = $_GET["valor_pago"];
	$tipo = $_GET["tipo"];
	$origen = $_GET["origen"];

	$arrayFormaPago = array();
	$arrayDatos = array('id' => rand(5, 500), 'id_forma' => $forma_pago, 'tipo' => $tipo, 'valor' => $valor_pago, 'origen' => $origen);
	if (isset($_SESSION['arrayFormaPagoIngreso'])) {
		$on = true;
		$arrayFormaPago = $_SESSION['arrayFormaPagoIngreso'];
		for ($pr = 0; $pr < count($arrayFormaPago); $pr++) {
			if ($arrayFormaPago[$pr]['id_forma'] == $forma_pago && $origen == $arrayFormaPago[$pr]['origen']) {
				$arrayFormaPago[$pr]['valor'] += $valor_pago;
				$on = false;
			}
		}
		if ($on) {
			array_push($arrayFormaPago, $arrayDatos);
		}
		$_SESSION['arrayFormaPagoIngreso'] = $arrayFormaPago;
	} else {
		array_push($arrayFormaPago, $arrayDatos);
		$_SESSION['arrayFormaPagoIngreso'] = $arrayFormaPago;
	}
	detalle_nuevo_ingreso();
}

//para agregar detalle de facturas de ventas por cobrar al ingreso
if ($action == 'agregar_detalle_de_facturas') {
	$valor_cobro = $_POST["valor_cobro"];
	$detalle = $_POST["detalle"];
	$id_documento = $_POST["id_documento"];
	$registros = $_POST["registros"];
	$nombre_cliente = $_POST["nombre_cliente"];
	$fecha_agregado = date("Y-m-d H:i:s");
	$cantidad_cobros = array_sum($valor_cobro);
	if ($cantidad_cobros == 0) {
		echo "<script>$.notify('Agregar valores cobrados.','error');
				</script>";
	} else {
		foreach ($registros as $valor) {
			$cobrado = $valor_cobro[$valor];
			$detalle_cobro = $detalle[$valor];
			$documento = $id_documento[$valor];
			$beneficiario_cliente = $nombre_cliente[$valor];
			if ($cobrado > 0) {
				$agregar_detalle_ingreso = mysqli_query($con, "INSERT INTO ingresos_egresos_tmp VALUES (null, 'INGRESO','" . $beneficiario_cliente . "', '" . $detalle_cobro . "', '" . $cobrado . "', 'CCXCC','" . $id_usuario . "', '" . $documento . "')");
			}
		}
		echo "<script>$.notify('Agregado.','success');
		</script>";
	}
	detalle_nuevo_ingreso();
}

//para recibos
if ($action == 'agregar_detalle_de_recibos') {
	$valor_cobro = $_POST["valor_cobro"];
	$detalle = $_POST["detalle"];
	$id_documento = $_POST["id_documento"];
	$registros = $_POST["registros"];
	$nombre_cliente = $_POST["nombre_cliente"];
	$fecha_agregado = date("Y-m-d H:i:s");
	$cantidad_cobros = array_sum($valor_cobro);
	if ($cantidad_cobros == 0) {
		echo "<script>$.notify('Agregar valores cobrados.','error');
				</script>";
	} else {
		foreach ($registros as $valor) {
			$cobrado = $valor_cobro[$valor];
			$detalle_cobro = $detalle[$valor];
			$documento = $id_documento[$valor];
			$beneficiario_cliente = $nombre_cliente[$valor];
			if ($cobrado > 0) {
				$agregar_detalle_ingreso = mysqli_query($con, "INSERT INTO ingresos_egresos_tmp VALUES (null, 'INGRESO','" . $beneficiario_cliente . "', '" . $detalle_cobro . "', '" . $cobrado . "', 'CCXRC','" . $id_usuario . "', '" . $documento . "')");
			}
		}
		echo "<script>$.notify('Agregado.','success');
		</script>";
	}
	detalle_nuevo_ingreso();
}



//eliminar detalle del ingreso
if ($action == 'eliminar_item_ingreso') {
	$id_documento = $_GET['id_documento'];
	$update_ingresos_tmp = mysqli_query($con, "UPDATE saldo_porcobrar_porpagar SET ing_tmp = '0' WHERE id_documento ='" . $id_documento . "'");
	$elimina_detalle_ingreso_tmp = mysqli_query($con, "DELETE FROM ingresos_egresos_tmp WHERE id_documento='" . $id_documento . "' and tipo_documento='INGRESO'");
	detalle_nuevo_ingreso();
}

//eliminar detalle pago del ingreso
if ($action == 'eliminar_item_pago') {
	$intid = $_GET['id_registro'];
	$arrData = $_SESSION['arrayFormaPagoIngreso'];
	for ($i = 0; $i < count($arrData); $i++) {
		if ($arrData[$i]['id'] == $intid) {
			unset($arrData[$i]);
			echo "<script>
            $.notify('Eliminado','error');
            </script>";
		}
	}
	sort($arrData); //para reordenar el array
	$_SESSION['arrayFormaPagoIngreso'] = $arrData;
	detalle_nuevo_ingreso();
}


function detalle_nuevo_ingreso()
{
	$con = conenta_login();
	$id_usuario = $_SESSION['id_usuario'];
	$ruc_empresa = $_SESSION['ruc_empresa'];

	$busca_ingreso = mysqli_query($con, "SELECT * FROM ingresos_egresos_tmp WHERE id_usuario = '" . $id_usuario . "' and tipo_documento='INGRESO'");
	?>
	<div class="row">
		<div class="panel-group" id="accordion" style="margin-bottom: -10px; margin-top: -15px;">
			<div class="col-md-7">
				<div class="panel panel-info">
					<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordion" href="#collapse1"><span class="caret"></span> Detalle de documentos agregados al ingreso</a>
					<div id="collapse1" class="panel-collapse">
						<div class="panel panel-info">
							<table class="table table-hover">
								<tr class="info">
									<td style="padding: 2px;">Nombre</td>
									<td style="padding: 2px;">Detalle</td>
									<td style="padding: 2px;" class='text-center'>Valor</td>
									<td style="padding: 2px;">Tipo</td>
									<td style="padding: 2px;" class='text-center'>Eliminar</td>
								</tr>
								<?php
								$valor_total = 0;
								while ($detalle = mysqli_fetch_array($busca_ingreso)) {
									$id_ingreso = $detalle['id_tmp'];
									$id_documento = $detalle['id_documento'];
									$detalle_ingreso = $detalle['detalle'];
									$beneficiario_cliente = $detalle['beneficiario_cliente'];
									$valor = $detalle['valor'];
									$valor_total += $valor;
									$tipo_transaccion = $detalle['tipo_transaccion'];

									if (!is_numeric($tipo_transaccion)) {
										$tipo_asiento = mysqli_query($con, "SELECT * FROM asientos_tipo WHERE codigo='" . $tipo_transaccion . "' ");
										$row_asiento = mysqli_fetch_assoc($tipo_asiento);
										$transaccion = $row_asiento['tipo_asiento'];
									} else {
										$tipo_pago = mysqli_query($con, "SELECT * FROM opciones_ingresos_egresos WHERE id='" . $tipo_transaccion . "' ");
										$row_tipo_pago = mysqli_fetch_assoc($tipo_pago);
										$transaccion = $row_tipo_pago['descripcion'];
									}
								?>
									<tr>
										<td style="padding: 2px;"><?php echo $beneficiario_cliente; ?></td>
										<td style="padding: 2px;"><?php echo $detalle_ingreso; ?></td>
										<td style="padding: 2px;"><?php echo number_format($valor, 2, '.', ''); ?></td>
										<td style="padding: 2px;"><?php echo $transaccion; ?></td>
										<td style="padding: 2px;" class='text-right'><a class='btn btn-danger btn-xs' title='Eliminar' onclick="eliminar_item_ingreso('<?php echo $id_documento; ?>')"><i class="glyphicon glyphicon-remove"></i></a></td>
									</tr>
								<?php
								}
								?>
								<input type="hidden" id="suma_ingreso" value="<?php echo number_format($valor_total, 2, '.', ''); ?>">
								<tr class="info">
									<th style="padding: 2px;"></th>
									<th style="padding: 2px;">Total</th>
									<th style="padding: 2px;"><?php echo number_format($valor_total, 2, '.', ''); ?></th>
									<th style="padding: 2px;" colspan="6"></th>
								</tr>
							</table>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="panel-group" id="accordion_pago" style="margin-bottom: -10px; margin-top: -15px;">
			<div class="col-md-5">
				<div class="panel panel-info">
					<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordion_pago" href="#collapse2"><span class="caret"></span> Detalle de formas de pagos</a>
					<div id="collapse2" class="panel-collapse">
						<div class="panel panel-info">
							<table class="table table-hover">
								<tr class="info">
									<td style="padding: 2px;">Forma</td>
									<td style="padding: 2px;">Valor</td>
									<td style="padding: 2px;">Tipo</td>
									<td style="padding: 2px;" class='text-right'>Eliminar</td>
								</tr>
								<?php
								$valor_total_pago = 0;
								if (isset($_SESSION['arrayFormaPagoIngreso'])) {
									foreach ($_SESSION['arrayFormaPagoIngreso'] as $detalle) {
										$id = $detalle['id'];
										$id_forma = $detalle['id_forma'];
										$tipo = $detalle['tipo'];
										switch ($tipo) {
											case "0":
												$tipo = 'N/A';
												break;
											case "D":
												$tipo = 'Depósito';
												break;
											case "T":
												$tipo = 'Transferencia';
												break;
										}
										$origen = $detalle['origen'];
										$valor_pago = number_format($detalle['valor'], 2, '.', '');
										$valor_total_pago += $valor_pago;

										if ($origen == 1) {
											$query_cobros_pagos = mysqli_query($con, "SELECT * FROM opciones_cobros_pagos WHERE id='" . $id_forma . "' and ruc_empresa='" . $ruc_empresa . "' ");
											$row_cobros_pagos = mysqli_fetch_array($query_cobros_pagos);
											$forma_pago = strtoupper($row_cobros_pagos['descripcion']);
										} else {

											$cuentas_bancarias = mysqli_query($con, "SELECT concat(ban_ecu.nombre_banco,' ',cue_ban.numero_cuenta,' ', if(cue_ban.id_tipo_cuenta=1,'Aho','Cte')) as cuenta_bancaria FROM cuentas_bancarias as cue_ban INNER JOIN bancos_ecuador as ban_ecu ON cue_ban.id_banco=ban_ecu.id_bancos WHERE cue_ban.id_cuenta ='" . $id_forma . "'");
											$row_cuentas_bancarias = mysqli_fetch_array($cuentas_bancarias);
											$forma_pago = strtoupper($row_cuentas_bancarias['cuenta_bancaria']);
										}
								?>
										<tr>
											<td style="padding: 2px;"><?php echo $forma_pago; ?></td>
											<td style="padding: 2px;"><?php echo number_format($valor_pago, 2, '.', ''); ?></td>
											<td style="padding: 2px;"><?php echo $tipo; ?></td>
											<td style="padding: 2px;" class='text-right'><a href="#" class='btn btn-danger btn-xs' title='Eliminar' onclick="eliminar_item_pago('<?php echo $id; ?>')"><i class="glyphicon glyphicon-remove"></i></a></td>
										</tr>
								<?php
									}
								}
								?>
								<tr class="info">
									<th style="padding: 2px;">Total</th>
									<th style="padding: 2px;"><?php echo number_format($valor_total_pago, 2, '.', ''); ?></th>
									<th style="padding: 2px;" colspan="6"></th>
								</tr>
							</table>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<!-- desde aqui asiento contable-->
	<div class="row" style="margin-bottom: 5px; margin-top: 15px;">
		<div class="col-md-12">
			<div class="panel-group" id="accordion_contable">
				<div class="panel panel-info">
					<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordion_contable" href="#collapse_contable"><span class="caret"></span> Asiento contable (Opcional)</a>
					<div id="collapse_contable" class="panel-collapse collapse">

						<div class="table-responsive">
							<input type="hidden" name="codigo_unico" id="codigo_unico">
							<input type="hidden" name="id_cuenta" id="id_cuenta">
							<input type="hidden" name="cod_cuenta" id="cod_cuenta">
							<div class="panel panel-info" style="margin-bottom: 5px; margin-top: -0px;">
								<table class="table table-bordered">
									<td class='col-xs-4'>
										<input type="text" class="form-control input-sm focusNext" name="cuenta_diario" id="cuenta_diario" onkeyup='buscar_cuentas();' autocomplete="off" tabindex="4" placeholder="Buscar cuenta contable">
									</td>
									<td class='col-xs-2'><input type="number" class="form-control input-sm focusNext text-left" name="debe_diario" id="debe_diario" tabindex="5" placeholder="Debe"></td>
									<td class='col-xs-2'><input type="number" class="form-control input-sm focusNext text-left" name="haber_cuenta" id="haber_cuenta" tabindex="6" placeholder="Haber"></td>
									<td class='col-xs-4'><input type="text" class="form-control input-sm focusNext" name="det_cuenta" id="det_cuenta" tabindex="7" placeholder="Referencia"></td>
									<td class='col-xs-1 text-center'><button type="button" class="btn btn-info btn-md focusNext" title="Agregar detalle de diario" tabindex="8" onclick="agregar_item_diario()"><span class="glyphicon glyphicon-plus"></span></button> </td>
								</table>
							</div>
							<div id="muestra_detalle_diario"></div><!-- Carga gif animado -->
							<div class="outer_divdet"></div><!-- Datos ajax Final -->
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
<?php
}

//para mostrar en el modal el detalle del ingreso
if ($action == 'detalle_ingreso') {
	$con = conenta_login();
	$codigo_unico = $_GET['codigo_unico'];
	$busca_encabezado_ingreso = mysqli_query($con, "SELECT * FROM ingresos_egresos WHERE codigo_documento = '" . $codigo_unico . "' ");
	$encabezado_ingresos = mysqli_fetch_array($busca_encabezado_ingreso);
	$id_registro_contable = $encabezado_ingresos['codigo_contable'];
	$id_cliente = $encabezado_ingresos['id_cli_pro'];
	$busca_detalle = mysqli_query($con, "SELECT * FROM detalle_ingresos_egresos WHERE codigo_documento = '" . $codigo_unico . "' ");
	$busca_pagos = mysqli_query($con, "SELECT * FROM formas_pagos_ing_egr WHERE codigo_documento = '" . $codigo_unico . "' ");
?>
	<div class="well well-sm" style="margin-bottom: -20px; margin-top: -10px; height: 14%;">
		<div class="modal-body">
			<div class="form-group row">
				<div class="col-sm-3">
					<div class="input-group">
						<span class="input-group-addon"><b>Ingreso</b></span>
						<input type="text" class="form-control input-sm text-center" value="<?php echo $encabezado_ingresos['numero_ing_egr']; ?>" readonly>
					</div>
				</div>
				<div class="col-sm-9">
					<div class="input-group">
						<span class="input-group-addon"><b>Recibido de</b></span>
						<input type="hidden" id="id_cliente_editar_ingreso" name="id_cliente_editar_ingreso" value="<?php echo $id_cliente; ?>">
						<input type="hidden" id="codigo_unico_ingreso" name="codigo_unico_ingreso" value="<?php echo $codigo_unico; ?>">
						<input type="text" id="cliente_editar_ingreso" name="cliente_editar_ingreso" class="form-control input-sm" onkeyup="buscar_cliente_editar_ingreso();" value="<?php echo $encabezado_ingresos['nombre_ing_egr']; ?>" placeholder="Cliente">
					</div>
				</div>
			</div>
			<div class="form-group row">
				<div class="col-sm-3">
					<div class="input-group">
						<span class="input-group-addon"><b>Fecha</b></span>
						<input type="text" class="form-control input-sm text-center" id="fecha_editar_ingreso" name="fecha_editar_ingreso" value="<?php echo date("d-m-Y", strtotime($encabezado_ingresos['fecha_ing_egr'])); ?>">
					</div>
				</div>
				<div class="col-sm-9">
					<div class="input-group">
						<span class="input-group-addon"><b>Observaciones</b></span>
						<input type="text" class="form-control input-sm" id="observaciones_editar_ingreso" name="observaciones_editar_ingreso" value="<?php echo $encabezado_ingresos['detalle_adicional']; ?>" placeholder="Observaciones">
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="panel panel-info">
		<div class="table-responsive">
			<table class="table table-hover">
				<tr class="info">
					<th style="padding: 2px;">Tipo</th>
					<th style="padding: 2px;">Detalle</th>
					<th style="padding: 2px;" class="text-center">Valor</th>
				</tr>
				<?php
				while ($detalle = mysqli_fetch_array($busca_detalle)) {
					$id_detalle = $detalle['id_detalle_ing_egr'];
				?>
					<tr>
						<?php
						$tipo_ing_egr = $detalle['tipo_ing_egr'];
						if (!is_numeric($tipo_ing_egr)) { //cuando NO es un codigo id de las opciones de egresos
							$tipo_asiento = mysqli_query($con, "SELECT * FROM asientos_tipo WHERE codigo='" . $tipo_ing_egr . "' ");
							$row_asiento = mysqli_fetch_assoc($tipo_asiento);
						?>
							<input type="hidden" name="valor_detalle_ingresos_no_editables[<?php echo $id_detalle; ?>]" value="<?php echo number_format($detalle['valor_ing_egr'], 2, '.', ''); ?>">
							<td style="padding: 2px;"><?php echo $row_asiento['tipo_asiento']; ?></td>
							<td style="padding: 2px;"><?php echo $detalle['detalle_ing_egr']; ?></td>
							<td style="padding: 2px; text-align: center;"><?php echo number_format($detalle['valor_ing_egr'], 2, '.', ''); ?></td>
						<?php
						} else {
						?>
							<input type="hidden" name="id_detalle_ingreso[]" value="<?php echo $id_detalle; ?>">
							<td class='col-sm-2' style="padding: 2px;">
								<select class="form-control" style="height: 30px" title="Seleccione tipo de ingreso" id="tipo_ingreso<?php echo $id_detalle; ?>" name="tipo_ingreso[<?php echo $id_detalle; ?>]">
									<?php
									$resultado = mysqli_query($con, "SELECT * FROM opciones_ingresos_egresos WHERE tipo_opcion ='1' and status='1' and ruc_empresa='" . $ruc_empresa . "'order by descripcion asc");
									while ($row = mysqli_fetch_assoc($resultado)) {
										$selected = ($row['id'] == $tipo_ing_egr) ? 'selected' : '';
									?>
										<option value="<?php echo $row['id']; ?>" <?php echo $selected; ?>><?php echo strtoupper($row['descripcion']); ?> </option>
									<?php
									}
									?>
								</select>
							</td>
							<td style="padding: 2px;">
								<input type="text" class="form-control input-sm" id="detalle_editar_ingreso[<?php echo $id_detalle; ?>]" name="detalle_editar_ingreso[<?php echo $id_detalle; ?>]" value="<?php echo $detalle['detalle_ing_egr']; ?>" placeholder="Detalle">
							</td>
							<td class="col-sm-2" style="padding: 2px;">
								<input type="text" class="form-control input-sm text-center" id="valor_detalle_editar_ingreso[<?php echo $id_detalle; ?>]" name="valor_detalle_editar_ingreso[<?php echo $id_detalle; ?>]" value="<?php echo number_format($detalle['valor_ing_egr'], 2, '.', '') ?>" placeholder="Valor">
							</td>
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
	<div class="panel panel-info">
		<div class="table-responsive">
			<table class="table table-hover">
				<tr class="info">
					<th style="padding: 2px;">Forma cobro</th>
					<th style="padding: 2px;">Tipo</th>
					<th style="padding: 2px;" class="text-center">Valor</th>
				</tr>
				<?php
				while ($detalle_pagos = mysqli_fetch_array($busca_pagos)) {
					$codigo_forma_pago = $detalle_pagos['codigo_forma_pago'];
					$id_cuenta = $detalle_pagos['id_cuenta'];
					$id_fp = $detalle_pagos['id_fp'];
				?>
					<input type="hidden" name="id_forma_pago_ingreso[]" value="<?php echo $id_fp; ?>">
				<?php
					echo formas_de_pago_ingreso($id_fp, $con, $ruc_empresa, $detalle_pagos['codigo_forma_pago'], $detalle_pagos['id_cuenta'], $detalle_pagos['detalle_pago'], $detalle_pagos['valor_forma_pago']);
				}
				?>
			</table>
		</div>
	</div>

	<script>
		$('#fecha_editar_ingreso').css('z-index', 1500);
		jQuery(function($) {
			$("#fecha_editar_ingreso").mask("99-99-9999");
		});

		$(function() {
			$("#fecha_editar_ingreso").datepicker({
				dateFormat: "dd-mm-yy",
				firstDay: 1,
				dayNamesMin: ["Do", "Lu", "Ma", "Mi", "Ju", "Vi", "Sa"],
				dayNamesShort: ["Dom", "Lun", "Mar", "Mie", "Jue", "Vie", "Sab"],
				monthNames: ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio",
					"Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"
				],
				monthNamesShort: ["Ene", "Feb", "Mar", "Abr", "May", "Jun",
					"Jul", "Ago", "Sep", "Oct", "Nov", "Dic"
				]
			});
		});

		//para cuando se cambia la forma de pago o tipo de cuenta bancaria
		function cambioFormaPagoEditarIngreso(id) {
			var selectTipo = document.getElementById('tipo_editar_forma_pago' + id);
			var forma_pago = $("#forma_pago_editar_ingreso" + id).val();
			$("#tipo_editar_forma_pago" + id).val("0");
			var origen = forma_pago.substring(0, 1);
			if (origen == "1") {
				selectTipo.disabled = false; // Deshabilitar el input
				selectTipo.value = '0'; // Limpiar el valor si se deshabilita
				selectTipo.style.display = 'none'; //para ocultar
				document.getElementById('valor_cobro_editar_ingreso' + id).focus();
			} else {
				selectTipo.style.display = 'block'; //para mostrar
				selectTipo.disabled = false; // Habilitar el select
			}
		}


		//para buscar clientes para los ingresos
		function buscar_cliente_editar_ingreso() {
			$("#cliente_editar_ingreso").autocomplete({
				source: '../ajax/clientes_autocompletar_ingresos.php',
				minLength: 2,
				select: function(event, ui) {
					event.preventDefault();
					$('#id_cliente_editar_ingreso').val(ui.item.id);
					$('#cliente_editar_ingreso').val(ui.item.nombre);
				}
			});

			$("#cliente_editar_ingreso").on("keydown", function(event) {
				if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_cliente_editar_ingreso").val("");
					$("#cliente_editar_ingreso").val("");
				}
				if (event.keyCode == $.ui.keyCode.DELETE) {
					$("#id_cliente_editar_ingreso").val("");
					$("#cliente_editar_ingreso").val("");
				}
			});
		}
	</script>
<?php
	echo detalle_asiento_contable($con, $ruc_empresa, $id_registro_contable, 'INGRESOS');
}

function formas_de_pago_ingreso($id_fp, $con, $ruc_empresa, $codigo_forma_pago, $id_cuenta, $tipo, $valor)
{
?>
	<tr>
		<td style="padding: 2px;" class="col-sm-6">
			<select onchange="cambioFormaPagoEditarIngreso('<?php echo $id_fp ?>')" style="height: 30px;" class="form-control" title="Seleccione forma de pago." id="forma_pago_editar_ingreso<?php echo $id_fp; ?>" name="forma_pago_editar_ingreso[<?php echo $id_fp; ?>]">
				<?php
				$query_cobros_pagos = mysqli_query($con, "SELECT * FROM opciones_cobros_pagos WHERE ruc_empresa = '" . $ruc_empresa . "' and tipo_opcion='1' and status='1' order by descripcion asc");
				while ($row_cobros_pagos = mysqli_fetch_array($query_cobros_pagos)) {
					$selected = ($row_cobros_pagos['id'] == $codigo_forma_pago) ? 'selected' : '';
					//el 1 junto al id en el value es para saber que los datos son de la lista de opciones de cobro
				?>
					<option value="<?php echo "1" . $row_cobros_pagos['id']; ?>" <?php echo $selected; ?>><?php echo strtoupper($row_cobros_pagos['descripcion']); ?></option>
				<?php
				}
				$cuentas = mysqli_query($con, "SELECT cue_ban.id_cuenta as id_cuenta, concat(ban_ecu.nombre_banco,' ',cue_ban.numero_cuenta,' ', if(cue_ban.id_tipo_cuenta=1,'Aho','Cte')) as cuenta_bancaria FROM cuentas_bancarias as cue_ban INNER JOIN bancos_ecuador as ban_ecu ON cue_ban.id_banco=ban_ecu.id_bancos WHERE cue_ban.ruc_empresa ='" . $ruc_empresa . "' and cue_ban.status ='1'");
				while ($row_cuenta = mysqli_fetch_array($cuentas)) {
					$selected = ($row_cuenta['id_cuenta'] == $id_cuenta) ? 'selected' : '';
					//el 2 junto al id en el value es para saber que los datos son desde bancos
				?>
					<option value="<?php echo "2" . $row_cuenta['id_cuenta']; ?>" <?php echo $selected; ?>><?php echo strtoupper($row_cuenta['cuenta_bancaria']); ?></option>
				<?php
				}
				?>
			</select>
		</td>
		<td style="padding: 2px;" class="col-sm-2">
			<select class="form-control" style="height: 30px; <?php echo ($id_cuenta > 0) ? 'display: block;' : 'display: none;'; ?>" title="Seleccione" id="tipo_editar_forma_pago<?php echo $id_fp; ?>" name="tipo_editar_forma_pago[<?php echo $id_fp; ?>]">
				<option value="0" <?php if ($tipo == '0') echo 'selected'; ?>>Seleccione</option>
				<option value="D" <?php if ($tipo == 'D') echo 'selected'; ?>>Depósito</option>
				<option value="T" <?php if ($tipo == 'T') echo 'selected'; ?>>Transferencia</option>
			</select>
		</td>
		<td style="padding: 2px;" class="col-sm-2">
			<input type="text" class="form-control input-sm text-center" id="valor_cobro_editar_ingreso<?php echo $id_fp; ?>" name="valor_cobro_editar_ingreso[<?php echo $id_fp; ?>]" value="<?php echo $valor; ?>">
		</td>
	</tr>
<?php
}
