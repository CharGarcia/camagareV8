<?php
/* ====================== Requisitos / Setup ====================== */
include("../conexiones/conectalogin.php");
require("../excel/lib/PHPExcel/PHPExcel/IOFactory.php"); // Debe existir
require_once("../helpers/helpers.php"); // para strClean(), mensaje_error(), etc.

session_start();
$ruc_empresa = isset($_SESSION['ruc_empresa']) ? $_SESSION['ruc_empresa'] : null;
$id_usuario  = isset($_SESSION['id_usuario']) ? $_SESSION['id_usuario'] : null;

$con = conenta_login();
if (!$con) {
	echo "<script>$.notify('No hay conexión a base de datos.','error');</script>";
	exit;
}

$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

/* ====================== Helpers locales ====================== */
// Normaliza decimal para base de datos (punto, precisión fija)
function dec_db($val, $dec = 2)
{
	if ($val === '' || $val === null) return sprintf('%.' . $dec . 'F', 0);
	$v = floatval($val);
	return sprintf('%.' . $dec . 'F', $v);
}

// Carga condicional de librerías de fecha y conversión robusta Excel->Y-m-d
// Intentamos cargar Date.php si existe en alguna ruta típica
if (!function_exists('xls_to_ymd')) {
	$__date_paths = array(
		__DIR__ . "/../excel/lib/PHPExcel/PHPExcel/Shared/Date.php",
		__DIR__ . "/../Classes/PHPExcel/Shared/Date.php",
		__DIR__ . "/../PHPExcel/Shared/Date.php",
		__DIR__ . "/../vendor/phpoffice/phpexcel/Classes/PHPExcel/Shared/Date.php",
		__DIR__ . "/../vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Shared/Date.php",
		__DIR__ . "/../excel/Date.php",
		__DIR__ . "/../Date.php",
	);
	foreach ($__date_paths as $__p) {
		if (file_exists($__p)) {
			require_once($__p);
			break;
		}
	}

	// Convierte valores de Excel a 'Y-m-d' o null (sin depender obligatoriamente de PHPExcel_Shared_Date)
	function xls_to_ymd($cellVal)
	{
		if ($cellVal === '' || $cellVal === null) return null;

		if (is_numeric($cellVal)) {
			// 1) PHPExcel
			if (class_exists('PHPExcel_Shared_Date')) {
				$ts = PHPExcel_Shared_Date::ExcelToPHP($cellVal);
				return gmdate('Y-m-d', (int)$ts);
			}
			// 2) PhpSpreadsheet
			if (class_exists('\PhpOffice\PhpSpreadsheet\Shared\Date')) {
				if (method_exists('\PhpOffice\PhpSpreadsheet\Shared\Date', 'excelToTimestamp')) {
					$ts = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp($cellVal);
					return gmdate('Y-m-d', (int)$ts);
				}
				if (method_exists('\PhpOffice\PhpSpreadsheet\Shared\Date', 'excelToDateTimeObject')) {
					$dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($cellVal);
					return $dt ? $dt->format('Y-m-d') : null;
				}
			}
			// 3) Fallback manual (excel serial -> unix time)
			$unix = (int)round(($cellVal - 25569) * 86400); // 25569 = días entre 1900-01-01 y 1970-01-01
			return gmdate('Y-m-d', $unix);
		}

		// Si viene como string, intentamos con strtotime
		$t = strtotime($cellVal);
		if ($t && $t > 0) return gmdate('Y-m-d', $t);

		return null;
	}
}

// Limpia string de forma segura
function clean_str_local($s)
{
	if ($s === null) return '';
	return strClean(trim($s)); // usa tu helper existente
}

// Notificación JS
function js_notify($msg, $type = 'info')
{
	echo "<script>$.notify(" . json_encode($msg) . ", " . json_encode($type) . ");</script>";
}

/* ====================== Acción principal ====================== */
if ($action === 'cargar_detalle' && !empty($ruc_empresa)) {

	$mensajes = array();

	// Validación de archivo
	if (empty($_FILES['archivo']['name']) || empty($_FILES['archivo']['tmp_name'])) {
		js_notify('Cargue un archivo Excel (.xlsx).', 'error');
		exit;
	}

	$nombre_archivo   = $_FILES['archivo']['name'];
	$archivo_temporal = $_FILES['archivo']['tmp_name'];
	$ext              = strtolower(pathinfo($nombre_archivo, PATHINFO_EXTENSION));

	if ($ext !== 'xlsx') {
		js_notify('El archivo debe ser .xlsx', 'error');
		exit;
	}

	// Intentar leer Excel desde tmp (sin mover)
	try {
		$objPHPExcel = PHPExcel_IOFactory::load($archivo_temporal);
	} catch (Exception $e) {
		js_notify('No se pudo leer el archivo Excel: ' . $e->getMessage(), 'error');
		exit;
	}

	// Hoja y cabeceras
	$sheet    = $objPHPExcel->setActiveSheetIndex(0);
	$numRows  = $sheet->getHighestRow();

	$serie        = clean_str_local($sheet->getCell('B1')->getCalculatedValue());
	$secuencial   = clean_str_local($sheet->getCell('B2')->getCalculatedValue());
	$total_fact   = floatval($sheet->getCell('B3')->getCalculatedValue());

	if ($serie === '' || $secuencial === '') {
		js_notify('La serie (B1) y el secuencial (B2) son obligatorios.', 'error');
		exit;
	}

	// Iniciar transacción: todo o nada
	mysqli_autocommit($con, false);
	$todo_ok = true;

	// 1) Verificar factura PENDIENTE y bloquear fila
	$q = sprintf(
		"SELECT id_encabezado_factura FROM encabezado_factura 
         WHERE ruc_empresa='%s' AND serie_factura='%s' AND secuencial_factura='%s' AND estado_sri='PENDIENTE' 
         LIMIT 1 FOR UPDATE",
		mysqli_real_escape_string($con, $ruc_empresa),
		mysqli_real_escape_string($con, $serie),
		mysqli_real_escape_string($con, $secuencial)
	);
	$rs = mysqli_query($con, $q);
	if (!$rs) {
		$todo_ok = false;
		$mensajes[] = "Error consultando factura: " . mysqli_error($con);
	}

	$id_enc = null;
	if ($todo_ok) {
		$row = mysqli_fetch_assoc($rs);
		if (!$row) {
			$todo_ok = false;
			$mensajes[] = "No hay una factura con estado PENDIENTE para ese número. Verifique serie y secuencial.";
		} else {
			$id_enc = $row['id_encabezado_factura'];
		}
		mysqli_free_result($rs);
	}

	// 2) Actualizar totales de factura y formas de pago
	if ($todo_ok) {
		$q1 = sprintf(
			"UPDATE encabezado_factura 
             SET total_factura=%s 
             WHERE id_encabezado_factura=%d",
			dec_db($total_fact, 2),
			intval($id_enc)
		);
		if (!mysqli_query($con, $q1)) {
			$todo_ok = false;
			$mensajes[] = "No se guardó el total de la factura: " . mysqli_error($con);
		}

		// Asumimos un solo registro de forma de pago asociado a la factura; si tienes múltiples, aquí podrías prorratear.
		$q2 = sprintf(
			"UPDATE formas_pago_ventas 
             SET valor_pago=%s 
             WHERE ruc_empresa='%s' AND serie_factura='%s' AND secuencial_factura='%s'",
			dec_db($total_fact, 2),
			mysqli_real_escape_string($con, $ruc_empresa),
			mysqli_real_escape_string($con, $serie),
			mysqli_real_escape_string($con, $secuencial)
		);
		if (!mysqli_query($con, $q2)) {
			$todo_ok = false;
			$mensajes[] = "No se guardó el total de pagos de la factura: " . mysqli_error($con);
		}
	}

	// 3) Reemplazar detalle: borrar anterior y reinsertar todo
	if ($todo_ok) {
		$qd = sprintf(
			"DELETE FROM cuerpo_factura 
             WHERE ruc_empresa='%s' AND serie_factura='%s' AND secuencial_factura='%s'",
			mysqli_real_escape_string($con, $ruc_empresa),
			mysqli_real_escape_string($con, $serie),
			mysqli_real_escape_string($con, $secuencial)
		);
		if (!mysqli_query($con, $qd)) {
			$todo_ok = false;
			$mensajes[] = "No se pudo limpiar el detalle previo: " . mysqli_error($con);
		}
	}

	if ($todo_ok) {
		for ($p = 5; $p <= $numRows; $p++) {
			// Lectura de celdas
			$id_producto    = $sheet->getCell('A' . $p)->getCalculatedValue();
			$cantidad_raw   = $sheet->getCell('B' . $p)->getCalculatedValue();
			$punit_raw      = $sheet->getCell('C' . $p)->getCalculatedValue();
			$desc_raw       = $sheet->getCell('D' . $p)->getCalculatedValue();
			$tarifa_iva     = $sheet->getCell('F' . $p)->getCalculatedValue();
			$detalle_extra  = $sheet->getCell('G' . $p)->getCalculatedValue();
			$lote           = $sheet->getCell('H' . $p)->getCalculatedValue();
			$venc_raw       = $sheet->getCell('I' . $p)->getCalculatedValue();
			$id_bodega      = $sheet->getCell('J' . $p)->getCalculatedValue();

			// Fila completamente vacía: continuar
			if (($id_producto === null || $id_producto === '') &&
				($cantidad_raw === null || $cantidad_raw === '') &&
				($punit_raw === null || $punit_raw === '')
			) {
				continue;
			}

			// Normalizaciones
			$id_producto = intval($id_producto);
			if ($id_producto <= 0) {
				$todo_ok = false;
				$mensajes[] = "ID de producto inválido (fila $p).";
				break;
			}

			$cantidad = floatval($cantidad_raw);
			$punit    = floatval($punit_raw);
			$desc     = floatval($desc_raw);
			$tarifa_iva = clean_str_local($tarifa_iva);
			$detalle_adicional = clean_str_local($detalle_extra);
			$lote = clean_str_local($lote);
			$id_bodega = ($id_bodega === '' || $id_bodega === null) ? null : intval($id_bodega);
			$fecha_venc = xls_to_ymd($venc_raw); // null si está vacío o inválido

			// Validaciones de referencias
			// Producto
			$qp = sprintf("SELECT id, codigo_producto, nombre_producto, tipo_produccion, id_unidad_medida 
                           FROM productos_servicios WHERE id=%d LIMIT 1", $id_producto);
			$rp = mysqli_query($con, $qp);
			if (!$rp) {
				$todo_ok = false;
				$mensajes[] = "Error validando producto (fila $p): " . mysqli_error($con);
				break;
			}
			$rowp = mysqli_fetch_assoc($rp);
			mysqli_free_result($rp);
			if (!$rowp) {
				$todo_ok = false;
				$mensajes[] = "Producto/servicio no encontrado (fila $p).";
				break;
			}

			$codigo_producto = $rowp['codigo_producto'];
			$nombre_producto = $rowp['nombre_producto'];
			$tipo_prod       = $rowp['tipo_produccion']; // '01' bien / '02' servicio (según tu lógica)
			$id_medida       = $rowp['id_unidad_medida'];

			// IVA
			$qi = sprintf(
				"SELECT codigo FROM tarifa_iva WHERE codigo='%s' LIMIT 1",
				mysqli_real_escape_string($con, $tarifa_iva)
			);
			$ri = mysqli_query($con, $qi);
			if (!$ri) {
				$todo_ok = false;
				$mensajes[] = "Error validando tarifa IVA (fila $p): " . mysqli_error($con);
				break;
			}
			$rowi = mysqli_fetch_assoc($ri);
			mysqli_free_result($ri);
			if (!$rowi) {
				$todo_ok = false;
				$mensajes[] = "Tarifa IVA no encontrada (fila $p).";
				break;
			}

			// Bodega si es inventariable
			if ($tipo_prod === '01') {
				if (empty($id_bodega)) {
					$todo_ok = false;
					$mensajes[] = "Debe indicar id_bodega para producto de inventario (fila $p).";
					break;
				}
				$qb = sprintf("SELECT id_bodega FROM bodega WHERE id_bodega=%d LIMIT 1", intval($id_bodega));
				$rb = mysqli_query($con, $qb);
				if (!$rb) {
					$todo_ok = false;
					$mensajes[] = "Error validando bodega (fila $p): " . mysqli_error($con);
					break;
				}
				$rowb = mysqli_fetch_assoc($rb);
				mysqli_free_result($rb);
				if (!$rowb) {
					$todo_ok = false;
					$mensajes[] = "Id de bodega no encontrada (fila $p).";
					break;
				}
			} else {
				// Servicios: limpiar datos de inventario
				$id_bodega = null;
				$lote = '';
				$fecha_venc = null;
			}

			// Cálculos por línea
			$subtotal_linea = $cantidad * $punit;
			if ($desc > $subtotal_linea + 0.00001) {
				$todo_ok = false;
				$mensajes[] = "Descuento mayor al subtotal de la línea (fila $p).";
				break;
			}

			// Insert detalle
			$ins = sprintf(
				"INSERT INTO cuerpo_factura 
                 (id_cuerpo_factura, ruc_empresa, serie_factura, secuencial_factura, id_producto, cantidad, precio, subtotal, tipo, tarifa_iva, ice, detalle_adicional, descuento, codigo_producto, nombre_producto, id_unidad_medida, lote, vencimiento, id_bodega)
                 VALUES (NULL, '%s', '%s', '%s', %d, %s, %s, %s, '%s', '%s', '0', '%s', %s, '%s', '%s', %d, %s, %s, %s)",
				mysqli_real_escape_string($con, $ruc_empresa),
				mysqli_real_escape_string($con, $serie),
				mysqli_real_escape_string($con, $secuencial),
				intval($id_producto),
				dec_db($cantidad, 2),
				dec_db($punit, 4),
				dec_db($subtotal_linea, 2),
				mysqli_real_escape_string($con, $tipo_prod),
				mysqli_real_escape_string($con, $tarifa_iva),
				mysqli_real_escape_string($con, $detalle_adicional),
				dec_db($desc, 2),
				mysqli_real_escape_string($con, $codigo_producto),
				mysqli_real_escape_string($con, $nombre_producto),
				intval($id_medida),
				($lote === '' ? "NULL" : "'" . mysqli_real_escape_string($con, $lote) . "'"),
				($fecha_venc ? "'" . mysqli_real_escape_string($con, $fecha_venc) . "'" : "NULL"),
				($id_bodega ? intval($id_bodega) : "NULL")
			);
			if (!mysqli_query($con, $ins)) {
				$todo_ok = false;
				$mensajes[] = "Error insertando detalle (fila $p): " . mysqli_error($con);
				break;
			}
		}
	}

	// 4) Verificación: suma de líneas coincide con total (tolerancia 2 centavos)
	if ($todo_ok) {
		$qsum = sprintf(
			"SELECT IFNULL(SUM(subtotal - descuento),0) AS neto
             FROM cuerpo_factura 
             WHERE ruc_empresa='%s' AND serie_factura='%s' AND secuencial_factura='%s'",
			mysqli_real_escape_string($con, $ruc_empresa),
			mysqli_real_escape_string($con, $serie),
			mysqli_real_escape_string($con, $secuencial)
		);
		$rsum = mysqli_query($con, $qsum);
		if ($rsum) {
			$rowsum = mysqli_fetch_assoc($rsum);
			mysqli_free_result($rsum);
			$neto = floatval($rowsum['neto']);
			if (abs($neto - $total_fact) > 0.02) {
				$todo_ok = false;
				$mensajes[] = "La suma de líneas (" . number_format($neto, 2, '.', '') . ") no coincide con el total de factura (" . number_format($total_fact, 2, '.', '') . ").";
			}
		} else {
			$todo_ok = false;
			$mensajes[] = "Error verificando totales: " . mysqli_error($con);
		}
	}

	// Commit / Rollback
	if ($todo_ok) {
		mysqli_commit($con);
		mysqli_autocommit($con, true);
		js_notify('El detalle de ventas ha sido guardado.', 'success');
	} else {
		mysqli_rollback($con);
		mysqli_autocommit($con, true);
		js_notify('Revisar los errores y volver a cargar.', 'error');
		if (!empty($mensajes)) {
			echo mensaje_error($mensajes); // helper que imprime la lista de errores
		}
	}
} else if ($action === 'cargar_detalle') {
	js_notify('Sesión inválida o sin RUC de empresa.', 'error');
}
