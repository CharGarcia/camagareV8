<?php
// webhookDescargasSri.php  (PHP 5.6 compatible)
date_default_timezone_set('America/Guayaquil');

require_once __DIR__ . '/../conexiones/conectalogin.php'; // ajusta si es necesario
$con = conenta_login();
if (!$con) {
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array('status' => 'error', 'message' => 'Sin conexión a BD'));
    exit;
}

/* ===================== Helpers ===================== */
function respond_json($code, $arr)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function client_ip()
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($parts[0]);
    }
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
}
function extract_ruc_from_url($url)
{
    // Busca un RUC de 13 dígitos en el path tipo .../1793204600001/SRI/...
    $path = parse_url($url, PHP_URL_PATH);
    if (!$path) return null;
    if (preg_match('/\/(\d{13})\/SRI\//', $path, $m)) {
        return $m[1];
    }
    return null;
}
function norm_clave($s)
{
    $s = trim((string)$s);
    $s = preg_replace('/\D+/', '', $s); // solo dígitos
    return $s ? $s : null;
}

/* ¿Existe la clave en tablas definitivas? (usa índices: (ruc_empresa, aut_sri)) */
function esta_clave_registrada_global($con, $ruc, $clave)
{
    $sql = "SELECT 1 FROM (
                SELECT aut_sri FROM encabezado_compra           WHERE ruc_empresa=? AND aut_sri=? LIMIT 1
            UNION ALL
                SELECT aut_sri FROM encabezado_liquidacion      WHERE ruc_empresa=? AND aut_sri=? LIMIT 1
            UNION ALL
                SELECT aut_sri FROM encabezado_retencion_venta  WHERE ruc_empresa=? AND aut_sri=? LIMIT 1
            ) t LIMIT 1";
    $st = mysqli_prepare($con, $sql);
    if (!$st) {
        error_log('webhook esta_clave_registrada_global PREPARE: ' . mysqli_error($con));
        return false;
    }
    mysqli_stmt_bind_param($st, 'ssssss', $ruc, $clave, $ruc, $clave, $ruc, $clave);
    mysqli_stmt_execute($st);
    mysqli_stmt_store_result($st);
    $existe = (mysqli_stmt_num_rows($st) > 0);
    mysqli_stmt_free_result($st);
    mysqli_stmt_close($st);
    return $existe;
}

/* ===================== Entrada ===================== */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_json(405, array('status' => 'error', 'message' => 'Método no permitido'));
}
$raw = file_get_contents('php://input');
if ($raw === false || $raw === '') {
    respond_json(400, array('status' => 'error', 'message' => 'Cuerpo vacío'));
}
$data = json_decode($raw, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    respond_json(400, array('status' => 'error', 'message' => 'JSON inválido'));
}

$jobId    = isset($data['jobId']) ? (string)$data['jobId'] : '0'; // bind como string por compatibilidad 32-bit
$state    = isset($data['state']) ? (string)$data['state'] : null; // COMPLETADO / EN_PROCESO / ERROR
$results  = (isset($data['results']) && is_array($data['results'])) ? $data['results'] : array();
$sourceIp = client_ip();

/* ================= sri_jobs_log (opcional pero útil) ================= */
if ($jobId !== '0') {
    $nuevo_estado = null;
    if ($state === 'COMPLETADO') {
        $nuevo_estado = 'COMPLETADO';
    } elseif ($state === 'ERROR' || $state === 'FALLIDO') {
        $nuevo_estado = 'FALLIDO';
    }
    if ($nuevo_estado) {
        $qlog = sprintf(
            "UPDATE sri_jobs_log SET estado='%s' WHERE job_id='%s' LIMIT 1",
            mysqli_real_escape_string($con, $nuevo_estado),
            mysqli_real_escape_string($con, $jobId)
        );
        @mysqli_query($con, $qlog);
    }
}

/* ================= Insert/Upsert en sri_webhook_queue =================
   Tabla según tu dump:
   - UNIQUE KEY uq_job_clave (job_id, clave_acceso)
   - Campos: state, processed (tinyint), processed_at, error_msg, raw_json, etc.
   Usamos upsert y conservamos processed=1 si ya estaba marcado:
   processed = GREATEST(processed, VALUES(processed))
   processed_at = NOW() cuando VALUES(processed)=1
======================================================================= */

$sql = "INSERT INTO sri_webhook_queue
 (job_id, state, ruc_empresa, clave_acceso, url_xml, url_ride, tipo_evento, costo, ip_evento, source_ip, raw_json, processed, processed_at, error_msg)
 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
 ON DUPLICATE KEY UPDATE
  state       = VALUES(state),
  ruc_empresa = VALUES(ruc_empresa),
  url_xml     = VALUES(url_xml),
  url_ride    = VALUES(url_ride),
  tipo_evento = VALUES(tipo_evento),
  costo       = VALUES(costo),
  ip_evento   = VALUES(ip_evento),
  source_ip   = VALUES(source_ip),
  raw_json    = VALUES(raw_json),
  processed   = GREATEST(processed, VALUES(processed)),
  processed_at= CASE WHEN VALUES(processed)=1 THEN NOW() ELSE processed_at END,
  error_msg   = VALUES(error_msg)";

$types = 'sssssssssssis s'; // 14 params (espacio solo visual)
$types = str_replace(' ', '', $types); // "sssssssssssiss"
$stmt  = mysqli_prepare($con, $sql);
if (!$stmt) {
    respond_json(500, array('status' => 'error', 'message' => 'Prepare error: ' . mysqli_error($con)));
}

/* ================= Procesar items ================= */
$insertados = 0;
$marcados_procesados = 0;

if (!empty($results)) {
    foreach ($results as $it) {
        // Normalizar llaves del proveedor
        $clave   = isset($it['clave_acceso']) ? $it['clave_acceso'] : (isset($it['claveAcceso']) ? $it['claveAcceso'] : (isset($it['clave'])       ? $it['clave']       : null));
        $url_xml = isset($it['url_xml']) ? $it['url_xml'] : (isset($it['xmlUrl'])  ? $it['xmlUrl']  : null);
        $url_pdf = isset($it['url_ride']) ? $it['url_ride'] : (isset($it['rideUrl']) ? $it['rideUrl'] : null);
        $evento  = isset($it['tipo_evento']) ? $it['tipo_evento'] : null;
        $costo   = isset($it['costo']) ? (string)$it['costo'] : null; // bind como string por null-safety
        $ip_evt  = isset($it['ip']) ? $it['ip'] : null;

        $clave = norm_clave($clave);
        if (!$clave) {
            continue;
        }

        // Detectar RUC (si no viene explícito)
        $ruc   = $url_xml ? extract_ruc_from_url($url_xml) : null;

        // ¿Ya está registrada en definitivas? => processed=1
        $ya_registrada = ($ruc && esta_clave_registrada_global($con, $ruc, $clave));
        $processed     = $ya_registrada ? 1 : 0;
        $processed_at  = $ya_registrada ? date('Y-m-d H:i:s') : null;
        $error_msg     = null;

        // Bind: todos como string salvo $processed (int)
        mysqli_stmt_bind_param(
            $stmt,
            $types, // "sssssssssssiss"
            $jobId,        // s
            $state,        // s
            $ruc,          // s
            $clave,        // s
            $url_xml,      // s
            $url_pdf,      // s
            $evento,       // s
            $costo,        // s (decimal a string, admite NULL)
            $ip_evt,       // s
            $sourceIp,     // s
            $raw,          // s
            $processed,    // i
            $processed_at, // s (NULL o fecha)
            $error_msg     // s
        );

        if (mysqli_stmt_execute($stmt)) {
            $insertados++;
            if ($ya_registrada) {
                $marcados_procesados++;
            }
        } else {
            error_log('webhook insert error: ' . mysqli_stmt_error($stmt) . ' | clave: ' . $clave);
        }

        // Limpieza por vuelta (seguro en 5.6)
        mysqli_stmt_free_result($stmt);
    }
} else {
    // Placeholder para auditoría si no vinieron results[]
    $dummyClave   = 'NO_RESULT_' . time();
    $ruc          = null;
    $url_xml      = null;
    $url_pdf      = null;
    $evento       = null;
    $costo        = null;
    $ip_evt       = null;
    $processed    = 0;
    $processed_at = null;
    $error_msg    = null;

    mysqli_stmt_bind_param(
        $stmt,
        $types,
        $jobId,
        $state,
        $ruc,
        $dummyClave,
        $url_xml,
        $url_pdf,
        $evento,
        $costo,
        $ip_evt,
        $sourceIp,
        $raw,
        $processed,
        $processed_at,
        $error_msg
    );
    @mysqli_stmt_execute($stmt);
}

mysqli_stmt_close($stmt);

/* COMPLETADO → opcional: mantén sri_jobs_log en COMPLETADO aquí.
   Si tu descarga/registración posterior marca 'processed=1' desde otro proceso,
   puedes actualizar sri_jobs_log a 'PROCESADO' allí. */
if ($jobId !== '0' && $state === 'COMPLETADO') {
    @mysqli_query(
        $con,
        "UPDATE sri_jobs_log SET estado='COMPLETADO' WHERE job_id='" . mysqli_real_escape_string($con, $jobId) . "'"
    );
}

mysqli_close($con);

/* ===================== Respuesta ===================== */
respond_json(200, array(
    'status'   => 'ok',
    'message'  => 'Notificación almacenada',
    'jobId'    => $jobId,
    'state'    => $state,
    'insertados' => $insertados,
    'marcados_procesados' => $marcados_procesados
));
