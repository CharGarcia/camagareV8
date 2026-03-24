<?php

/**
 * cron_descargas.php  — PHP 5.6 compatible
 * 
 * Usos:
 *   php sistema/facturacion_electronica/descargas_sri/cron_descargas.php daily [YYYY-MM-DD]
 *   php sistema/facturacion_electronica/descargas_sri/cron_descargas.php process [YYYY-MM-DD]
 */

@date_default_timezone_set('America/Guayaquil');

/* ========= LOGGING ========= */
if (!function_exists('cron_init_logger')) {
    function cron_init_logger()
    {
        static $done = false;
        if ($done) return;
        $candidatos = array('/var/log/cron_descargas.log', '/tmp/cron_descargas.log');
        foreach ($candidatos as $p) {
            if (!file_exists($p)) {
                @touch($p);
            }
            if (@is_writable($p)) {
                @ini_set('log_errors', '1');
                @ini_set('error_log', $p);
                $done = true;
                return;
            }
        }
        @ini_set('log_errors', '1'); // último intento
        $done = true;
    }
}
if (!function_exists('clog')) {
    function clog($level, $msg, $ctx = null)
    {
        cron_init_logger();
        $ts = '[' . date('Y-m-d H:i:s') . '] ';
        $prefix = '[' . $level . '] ';
        if (is_array($ctx) || is_object($ctx)) {
            @error_log($ts . $prefix . $msg . ' | ' . json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else if ($ctx !== null) {
            @error_log($ts . $prefix . $msg . ' | ' . $ctx);
        } else {
            @error_log($ts . $prefix . $msg);
        }
    }
}
if (!function_exists('logInfo')) {
    function logInfo($m, $c = null)
    {
        clog('INFO', $m, $c);
    }
}
if (!function_exists('logWarn')) {
    function logWarn($m, $c = null)
    {
        clog('WARN', $m, $c);
    }
}
if (!function_exists('logErr')) {
    function logErr($m, $c = null)
    {
        clog('ERR ', $m, $c);
    }
}

/* ========= CARGA LIBRERÍAS PROPIAS ========= */
// Evita efectos de UI/controlador si tu lib los tiene
if (!defined('SRI_LIB_ONLY')) define('SRI_LIB_ONLY', true);

// Rutas base
$BASE = dirname(__FILE__); // .../sistema/facturacion_electronica/descargas_sri
$ROOT = dirname(dirname(dirname($BASE))); // .../var/www/html/sistema

// Incluir tu librería principal (trae login_descargas, descargas_manuales_por_fecha, guardar_en_sri_webhook_queue, etc.)
require_once $BASE . '/cargarSriAutomaticos.php';

// Si por alguna razón conenta_login no vino, intenta cargarlo
if (!function_exists('conenta_login')) {
    $loginPath = $ROOT . '/conexiones/conectalogin.php';
    if (file_exists($loginPath)) {
        require_once $loginPath;
    }
}

/* ========= HELPERS LOCALES (con guards) ========= */

/** Datos de depuración de la conexión */
if (!function_exists('db_debug_info')) {
    function db_debug_info($con)
    {
        $db    = '';
        $hinfo = '';
        if ($con) {
            $h = @mysqli_get_host_info($con);
            if (is_string($h)) $hinfo = $h;

            $rs = @mysqli_query($con, "SELECT DATABASE() AS db;");
            if ($rs) {
                $row = @mysqli_fetch_assoc($rs);
                if ($row && isset($row['db'])) $db = (string)$row['db'];
                @mysqli_free_result($rs);
            }
        }
        return array('database' => $db, 'host_info' => $hinfo);
    }
}

/** Normaliza el array results[] del status/webhook en items {clave_acceso,url_xml,url_ride,tipo_evento,costo,ip} */
if (!function_exists('normalizar_items_webhook')) {
    function normalizar_items_webhook($items)
    {
        $out    = array();
        $vistos = array();
        $stack  = array($items);

        while (!empty($stack)) {
            $node = array_pop($stack);
            if (!is_array($node)) continue;

            $tieneClave = (isset($node['clave_acceso']) || isset($node['claveAcceso']) || isset($node['clave']));
            $tieneUrl   = (isset($node['url_xml']) || isset($node['xmlUrl']) || isset($node['urlXML']));

            if ($tieneClave && $tieneUrl) {
                $clave = isset($node['clave_acceso']) ? $node['clave_acceso']
                    : (isset($node['claveAcceso']) ? $node['claveAcceso']
                        : (isset($node['clave'])       ? $node['clave'] : null));
                $url_xml = isset($node['url_xml']) ? $node['url_xml']
                    : (isset($node['xmlUrl'])  ? $node['xmlUrl']
                        : (isset($node['urlXML'])  ? $node['urlXML'] : null));
                $url_ride = isset($node['url_ride']) ? $node['url_ride'] : (isset($node['pdfUrl']) ? $node['pdfUrl'] : null);
                $tipo_evento = isset($node['tipo_evento']) ? $node['tipo_evento'] : (isset($node['evento']) ? $node['evento'] : null);
                $ip   = isset($node['ip']) ? $node['ip'] : null;
                $costo = null;
                if (isset($node['costo']))        $costo = (float)$node['costo'];
                elseif (isset($node['precio']))   $costo = (float)$node['precio'];

                if ($clave && $url_xml) {
                    $clave_norm = preg_replace('/\D+/', '', (string)$clave);
                    if ($clave_norm !== '' && !isset($vistos[$clave_norm])) {
                        $vistos[$clave_norm] = true;
                        $out[] = array(
                            'clave_acceso' => $clave_norm,
                            'url_xml'      => (string)$url_xml,
                            'url_ride'     => $url_ride ? (string)$url_ride : null,
                            'tipo_evento'  => $tipo_evento ? (string)$tipo_evento : null,
                            'costo'        => $costo,
                            'ip'           => $ip ? (string)$ip : null,
                        );
                    }
                }
            } else {
                foreach ($node as $child) {
                    if (is_array($child)) $stack[] = $child;
                }
            }
        }
        return $out;
    }
}

/** Normaliza items leídos de sri_webhook_queue */
if (!function_exists('normalizar_items_queue')) {
    function normalizar_items_queue($rows)
    {
        $out    = array();
        $vistos = array();
        if (!is_array($rows)) return $out;

        foreach ($rows as $r) {
            $clave   = isset($r['clave_acceso']) ? $r['clave_acceso'] : null;
            $url_xml = isset($r['url_xml']) ? $r['url_xml'] : null;
            if (!$clave || !$url_xml) continue;

            $clave_norm = preg_replace('/\D+/', '', (string)$clave);
            if ($clave_norm === '' || isset($vistos[$clave_norm])) continue;

            $vistos[$clave_norm] = true;
            $out[] = array(
                'clave_acceso' => $clave_norm,
                'url_xml'      => (string)$url_xml
            );
        }
        return $out;
    }
}

/** Devuelve [nuevos, ya_registrados] en tablas finales, por tipo y ruc */
if (!function_exists('dividir_por_existencia_en_sistema')) {
    function dividir_por_existencia_en_sistema($con, $documentos, $tipo_comprobante, $ruc_empresa)
    {
        $tabla_por_tipo = array(
            "1" => "encabezado_compra",
            "2" => "encabezado_liquidacion",
            "3" => "encabezado_compra",
            "4" => "encabezado_compra",
            "6" => "encabezado_retencion_venta",
        );
        $nuevos = array();
        $ya     = array();

        $td = (string)$tipo_comprobante;
        if (!isset($tabla_por_tipo[$td])) {
            return array($documentos, $ya);
        }
        $tabla = $tabla_por_tipo[$td];

        $sql  = "SELECT 1 FROM $tabla WHERE ruc_empresa = ? AND aut_sri = ? LIMIT 1";
        $stmt = mysqli_prepare($con, $sql);
        if (!$stmt) {
            logWarn('dividir_por_existencia_en_sistema: prepare fallo', array('tabla' => $tabla, 'err' => mysqli_error($con)));
            return array($documentos, $ya);
        }

        $vistos = array();
        foreach ($documentos as $d) {
            $clave = isset($d['clave_acceso']) ? $d['clave_acceso'] : null;
            $url   = isset($d['url_xml'])      ? $d['url_xml']      : null;
            if (!$clave || !$url) continue;
            if (isset($vistos[$clave])) continue;
            $vistos[$clave] = true;

            mysqli_stmt_bind_param($stmt, "ss", $ruc_empresa, $clave);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);

            if (mysqli_stmt_num_rows($stmt) > 0) $ya[] = array('clave_acceso' => $clave, 'url_xml' => $url);
            else $nuevos[] = array('clave_acceso' => $clave, 'url_xml' => $url);

            mysqli_stmt_free_result($stmt);
        }
        mysqli_stmt_close($stmt);
        return array($nuevos, $ya);
    }
}

/** Lee documentos de sri_webhook_queue por job y (opcional) ruc */
if (!function_exists('obtener_docs_queue_por_job')) {
    function obtener_docs_queue_por_job($con, $jobId, $ruc_empresa)
    {
        $rows = array();
        $sql = "SELECT clave_acceso, url_xml, url_ride, tipo_evento, costo, ip_evento
                  FROM sri_webhook_queue
                 WHERE job_id = ?
                   AND (ruc_empresa = ? OR (ruc_empresa IS NULL OR ruc_empresa = ''))";
        $stmt = mysqli_prepare($con, $sql);
        if (!$stmt) {
            logWarn('obtener_docs_queue_por_job: prepare fallo', mysqli_error($con));
            return $rows;
        }
        mysqli_stmt_bind_param($stmt, "is", $jobId, $ruc_empresa);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $rows[] = $row;
            }
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

/** Valida respuesta de creación (versión local para cron, nombre único para no chocar) */
if (!function_exists('validar_respuesta_descarga_cron')) {
    function validar_respuesta_descarga_cron($resp)
    {
        $out = array('ok' => false, 'jobId' => null, 'http' => isset($resp['http_code']) ? (int)$resp['http_code'] : null, 'mensaje' => 'Respuesta inválida');
        if (!is_array($resp)) {
            $out['mensaje'] = 'Sin respuesta';
            return $out;
        }
        if (!empty($resp['curl_error'])) {
            $out['mensaje'] = 'cURL: ' . $resp['curl_error'];
            return $out;
        }
        if (isset($resp['response']['jobId']) && $resp['response']['jobId'] !== '') {
            $out['ok']    = true;
            $out['jobId'] = $resp['response']['jobId'];
            $out['mensaje'] = 'OK';
            return $out;
        }
        if (isset($resp['response']['message']) && $resp['response']['message'] !== '') {
            $out['mensaje'] = $resp['response']['message'];
        }
        return $out;
    }
}

/* ========= MODO DAILY: crear jobs por empresa/tipo para una fecha ========= */
if (!function_exists('run_daily')) {
    function run_daily($fechaYmd)
    {
        logInfo('🚀 Inicio cron descargas diarias');

        // Conexión
        if (!function_exists('conenta_login')) {
            logErr('Falta conenta_login()');
            echo "❌ Falta conenta_login()\n";
            return;
        }
        $con = conenta_login();
        if (!$con) {
            logErr('Sin conexión BD');
            echo "❌ Sin conexión BD\n";
            return;
        }
        $dbg = db_debug_info($con);
        logInfo('BD', $dbg);

        // Parseo de fecha
        $dt = DateTime::createFromFormat('Y-m-d', $fechaYmd);
        if (!$dt) {
            $ayer = new DateTime();
            $ayer->modify('-1 day');
            $dt = $ayer;
        }
        $anio = (int)$dt->format('Y');
        $mes  = (int)$dt->format('n');
        $dia  = (int)$dt->format('j');

        // Empresas activas
        $sql = "SELECT des.ruc AS ruc, des.`password` AS pass
                  FROM descargasri des
            INNER JOIN empresas emp ON emp.ruc = des.ruc
                 WHERE des.status = 1
                   AND des.`password` IS NOT NULL
                   AND des.`password` <> ''
                   AND emp.estado = 1";
        $qry = mysqli_query($con, $sql);
        if (!$qry) {
            logErr('Empresas SQL error', mysqli_error($con));
            echo "❌ Error consultando empresas.\n";
            @mysqli_close($con);
            return;
        }

        // Token/codemp
        if (!function_exists('login_descargas') || !function_exists('descargas_manuales_por_fecha')) {
            logErr('Faltan funciones login_descargas() o descargas_manuales_por_fecha()');
            echo "❌ Faltan funciones login_descargas/descargas_manuales_por_fecha.\n";
            @mysqli_free_result($qry);
            @mysqli_close($con);
            return;
        }
        $ld = login_descargas();
        if (!is_array($ld) || empty($ld['codemp']) || empty($ld['token'])) {
            logErr('login_descargas sin credenciales válidas', $ld);
            echo "❌ login_descargas sin credenciales.\n";
            @mysqli_free_result($qry);
            @mysqli_close($con);
            return;
        }
        $codemp = $ld['codemp'];
        $token  = $ld['token'];

        $tipos = array(1, 3, 6);
        //$tipos = array(1, 2, 3, 4, 6);

        while ($row = mysqli_fetch_assoc($qry)) {
            $ruc  = $row['ruc'];
            $pass = $row['pass'];

            foreach ($tipos as $tipo) {
                $t0 = microtime(true);
                $resp = descargas_manuales_por_fecha($codemp, $token, $ruc, $pass, $tipo, $dia, $mes, $anio);
                $dt_ms = (int)round((microtime(true) - $t0) * 1000);

                $val = validar_respuesta_descarga_cron($resp);
                $http = isset($resp['http_code']) ? (int)$resp['http_code'] : null;

                if (!$val['ok']) {
                    logWarn('[SOLIC] FAIL', array('RUC' => $ruc, 'Tipo' => $tipo, 'dmy' => "$dia-$mes-$anio", 'ms' => $dt_ms, 'HTTP' => $http, 'msg' => $val['mensaje']));
                    continue;
                }

                $jobId = (string)$val['jobId'];
                logInfo('[SOLIC] OK', array('RUC' => $ruc, 'Tipo' => $tipo, 'dmy' => "$dia-$mes-$anio", 'ms' => $dt_ms, 'HTTP' => $http, 'jobId' => $jobId));

                // Log en sri_jobs_log
                $now = date('Y-m-d H:i:s');
                @mysqli_query(
                    $con,
                    "INSERT IGNORE INTO sri_jobs_log (job_id, token, codemp, ruc_empresa, tipo_comprobante, anio, mes, dia, estado, creado_en)
                     VALUES ('" . mysqli_real_escape_string($con, $jobId) . "',
                             '" . mysqli_real_escape_string($con, $token) . "',
                             '" . mysqli_real_escape_string($con, $codemp) . "',
                             '" . mysqli_real_escape_string($con, $ruc) . "',
                             '" . mysqli_real_escape_string($con, $tipo) . "',
                             " . (int)$anio . ", " . (int)$mes . ", " . (int)$dia . ",
                             'CREADO', '" . $now . "')"
                );

                // Pull rápido al status para intentar poblar queue
                if (function_exists('consulta_status_job')) {
                    $st = consulta_status_job($codemp, $token, $jobId);
                    $items = (is_array($st) && isset($st['results']) && is_array($st['results'])) ? $st['results'] : array();
                    $state = (is_array($st) && isset($st['state'])) ? (string)$st['state'] : null;
                    $items_norm = normalizar_items_webhook($items);
                    if (!empty($items_norm) && function_exists('guardar_en_sri_webhook_queue')) {
                        $raw_payload = isset($st['raw']) ? (is_array($st['raw']) ? json_encode($st['raw'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string)$st['raw']) : '';
                        $sum = guardar_en_sri_webhook_queue($con, $jobId, $state ? $state : 'DESCONOCIDO', $ruc, $items_norm, null, $raw_payload);
                        $ins = isset($sum['insertados']) ? (int)$sum['insertados'] : 0;
                        $upd = isset($sum['actualizados']) ? (int)$sum['actualizados'] : 0;
                        logInfo('[QUEUE] jobId=' . $jobId . ' ins=' . $ins . ' upd=' . $upd);
                    } else {
                        logInfo('[QUEUE] jobId=' . $jobId . ' sin items en status (quizá llegan por webhook).');
                    }
                }
            }
        }

        @mysqli_free_result($qry);
        @mysqli_close($con);
        logInfo('🏁 Cron OK (daily)');
    }
}

/* ========= MODO PROCESS: procesar jobs (mover a tablas finales) ========= */
if (!function_exists('run_process')) {
    /**
     * Ejecuta el proceso de ingesta/descarga para los jobs del día (o de la fecha dada),
     * pobla la cola (upsert), procesa pendientes por job y luego hace un barrido global.
     *
     * @param string|null $fechaYmd  Fecha 'YYYY-mm-dd'. Si null, usa hoy (según timezone de PHP).
     */
    function run_process($fechaYmd = null)
    {
        logInfo('▶ Inicio PROCESS');

        // 0) Conexión
        if (!function_exists('conenta_login')) {
            logErr('Falta conenta_login()');
            echo "❌ Falta conenta_login()\n";
            return;
        }
        $con = conenta_login();
        if (!$con) {
            logErr('Sin conexión BD');
            echo "❌ Sin conexión BD\n";
            return;
        }

        // 1) Fecha como RANGO (evita DATE(col))
        if ($fechaYmd) {
            $dt = DateTime::createFromFormat('Y-m-d', $fechaYmd);
            $fechaFiltro = $dt ? $fechaYmd : date('Y-m-d');
            logInfo('Filtro PROCESS por creado_en (fecha específica)', array('fecha' => $fechaFiltro));
        } else {
            $fechaFiltro = date('Y-m-d');
            logInfo('Filtro PROCESS por creado_en (HOY)', array('fecha' => $fechaFiltro));
        }
        $desde = $fechaFiltro . ' 00:00:00';
        $hasta = date('Y-m-d', strtotime($fechaFiltro . ' +1 day')) . ' 00:00:00';
        logInfo('Selección de jobs', array('desde' => $desde, 'hasta' => $hasta));

        // 2) Cargar jobs del día (usa índice en creado_en)
        $sqlJobs = "SELECT id, job_id, token, codemp, ruc_empresa, tipo_comprobante
                      FROM sri_jobs_log
                     WHERE creado_en >= ? AND creado_en < ?
                  ORDER BY id DESC";
        $st = mysqli_prepare($con, $sqlJobs);
        if (!$st) {
            logErr('Prepare jobs falló', mysqli_error($con));
            @mysqli_close($con);
            return;
        }
        mysqli_stmt_bind_param($st, "ss", $desde, $hasta);
        mysqli_stmt_execute($st);
        $rs = mysqli_stmt_get_result($st);
        if (!$rs) {
            logErr('Query jobs falló', mysqli_error($con));
            @mysqli_close($con);
            return;
        }

        $totalJobs = 0;
        while ($row = mysqli_fetch_assoc($rs)) {
            $totalJobs++;
            $jobId  = (int)$row['job_id']; // BIGINT en cola
            $token  = (string)$row['token'];
            $codemp = (string)$row['codemp'];
            $ruc    = (string)$row['ruc_empresa'];
            $tipo   = (string)$row['tipo_comprobante'];

            logInfo('▶ Procesando job', array('jobId' => $jobId, 'ruc' => $ruc, 'tipo' => $tipo));

            // 3) Intentar STATUS (para poblar la cola)
            $state = null;
            $items_status = array();
            $raw_payload  = '';
            if (function_exists('consulta_status_job')) {
                $resp = consulta_status_job($codemp, $token, (string)$jobId);
                if (!empty($resp['ok'])) {
                    $state        = isset($resp['state'])   ? (string)$resp['state']   : 'DESCONOCIDO';
                    $items_status = isset($resp['results']) ? (array)$resp['results'] : array();
                    $raw_payload  = isset($resp['raw'])
                        ? (is_array($resp['raw']) ? json_encode($resp['raw'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string)$resp['raw'])
                        : '';
                    logInfo('[STATE] jobId=' . $jobId . ' ' . $state . ' | items_status=' . count($items_status));
                } else {
                    logWarn('⚠️ Falló consulta_status_job', $resp);
                }
            }

            // 4) Normalizar y UPSERT en la cola (sri_webhook_queue)
            $items_norm = array();
            if (!empty($items_status)) {
                if (function_exists('normalizar_items_webhook')) {
                    $items_norm = normalizar_items_webhook($items_status);
                } else {
                    foreach ($items_status as $it) {
                        if (!is_array($it)) continue;

                        // PHP 5.6: sin ??
                        if (isset($it['clave_acceso'])) {
                            $clave = $it['clave_acceso'];
                        } elseif (isset($it['claveAcceso'])) {
                            $clave = $it['claveAcceso'];
                        } elseif (isset($it['clave'])) {
                            $clave = $it['clave'];
                        } else {
                            $clave = null;
                        }

                        if (isset($it['url_xml'])) {
                            $url = $it['url_xml'];
                        } elseif (isset($it['xmlUrl'])) {
                            $url = $it['xmlUrl'];
                        } else {
                            $url = null;
                        }

                        if ($clave && $url) {
                            $items_norm[] = array(
                                'clave_acceso' => preg_replace('/\D+/', '', (string)$clave),
                                'url_xml'      => (string)$url
                            );
                        }
                    }
                }

                if (!empty($items_norm)) {
                    _queue_upsert_batch($con, $jobId, $ruc, $state ? $state : 'DESCONOCIDO', $items_norm, $raw_payload);
                }
            }

            // 5) Traer PENDIENTES por job de la cola (processed=0)
            $docs_queue = _queue_fetch_pendientes_por_job($con, $jobId, $ruc); // [{clave_acceso,url_xml},...]
            logInfo('[QUEUE] pendientes por job', array('count' => count($docs_queue)));

            if (empty($docs_queue)) {
                logInfo('ℹ️ Aún no hay documentos para procesar por job', array('jobId' => $jobId, 'ruc' => $ruc, 'tipo' => $tipo));
                continue;
            }

            // 6) Opcional: dividir por existencia en el sistema final
            $items_nuevos = $docs_queue;
            $items_ya     = array();
            if (function_exists('dividir_por_existencia_en_sistema')) {
                $tmp = dividir_por_existencia_en_sistema($con, $docs_queue, $tipo, $ruc);
                if (is_array($tmp) && count($tmp) === 2) {
                    $items_nuevos = $tmp[0];
                    $items_ya     = $tmp[1];
                }
            }

            // 7) Ingesta SOLO de nuevos (usa url_xml ya en la cola)
            $ok_count = 0;
            $fail_count = 0;
            $warn_count = 0;
            $ya_count = 0;
            $resp_fin = array();
            if (!empty($items_nuevos) && function_exists('descarga_xml_guardado')) {
                $resp_fin = descarga_xml_guardado($con, $items_nuevos, $ruc);

                if (is_array($resp_fin)) {
                    foreach ($resp_fin as $m) {
                        $e = '';
                        if (is_array($m) && isset($m['estado'])) {
                            $e = (string)$m['estado'];
                        }

                        if ($e === '1') {
                            $ok_count++;
                        } elseif ($e === '0') {
                            $fail_count++;
                        } elseif ($e === '2') {
                            $warn_count++;
                        } elseif ($e === '3') {
                            $ya_count++;
                        }
                    }
                }
            }

            // 8) Marcar PROCESADAS en la cola (por claves)
            $claves_proc = array();
            foreach ($items_ya as $it) {
                if (isset($it['clave_acceso'])) $claves_proc[] = (string)$it['clave_acceso'];
            }
            if (!empty($resp_fin) && is_array($resp_fin)) {
                foreach ($resp_fin as $m) {
                    $estado = '';
                    if (is_array($m) && isset($m['estado'])) {
                        $estado = (string)$m['estado'];
                    }
                    if ($estado === '1' && isset($m['clave_acceso'])) {
                        $claves_proc[] = (string)$m['clave_acceso'];
                    }
                }
            }
            if (!empty($claves_proc)) {
                _queue_mark_processed($con, $jobId, $ruc, $claves_proc);
            }

            logInfo('✔ Procesado', array(
                'jobId'      => $jobId,
                'ok'         => $ok_count,
                'warn'       => $warn_count,
                'err'        => $fail_count,
                'ya_reg'     => $ya_count,
                'ya_previos' => count($items_ya)
            ));
        }
        if (is_object($rs)) @mysqli_free_result($rs);

        // 9) Barrido GLOBAL de pendientes (catch-up)
        $glob = _queue_fetch_pendientes_global($con, 2000); // límite sano
        logInfo('[CATCHUP] pendientes globales', array('count' => count($glob)));
        if (!empty($glob)) {
            // Agrupar por (ruc, job_id) para reutilizar pipeline
            $porGrupo = array();
            foreach ($glob as $row) {
                $gk = $row['ruc_empresa'] . '|' . (string)$row['job_id'];
                if (!isset($porGrupo[$gk])) $porGrupo[$gk] = array();
                $porGrupo[$gk][] = array('clave_acceso' => $row['clave_acceso'], 'url_xml' => $row['url_xml']);
            }

            foreach ($porGrupo as $gk => $lista) {
                $parts = explode('|', $gk, 2);
                $rucG  = $parts[0];
                $jobG  = isset($parts[1]) ? (int)$parts[1] : 0;

                $ok_count = 0;
                $fail_count = 0;
                $warn_count = 0;
                $ya_count = 0;
                $resp_fin = array();

                if (function_exists('descarga_xml_guardado')) {
                    $resp_fin = descarga_xml_guardado($con, $lista, $rucG);

                    if (is_array($resp_fin)) {
                        foreach ($resp_fin as $m) {
                            $e = '';
                            if (is_array($m) && isset($m['estado'])) {
                                $e = (string)$m['estado'];
                            }

                            if ($e === '1') {
                                $ok_count++;
                            } elseif ($e === '0') {
                                $fail_count++;
                            } elseif ($e === '2') {
                                $warn_count++;
                            } elseif ($e === '3') {
                                $ya_count++;
                            }
                        }
                    }
                }

                $claves_proc = array();
                if (!empty($resp_fin)) {
                    foreach ($resp_fin as $m) {
                        $estado = '';
                        if (is_array($m) && isset($m['estado'])) {
                            $estado = (string)$m['estado'];
                        }
                        if ($estado === '1' && isset($m['clave_acceso'])) {
                            $claves_proc[] = (string)$m['clave_acceso'];
                        }
                    }
                }

                if (!empty($claves_proc)) {
                    _queue_mark_processed($con, $jobG, $rucG, $claves_proc);
                }
                logInfo('✔ CATCHUP grupo', array(
                    'jobId'  => $jobG,
                    'ruc'    => $rucG,
                    'ok'     => $ok_count,
                    'warn'   => $warn_count,
                    'err'    => $fail_count,
                    'ya_reg' => $ya_count
                ));
            }
        }

        @mysqli_close($con);
        logInfo('✔ PROCESS finalizado', array('jobs' => $totalJobs));
    }
}

/* ===================== Helpers internos ===================== */

/**
 * UPSERT en sri_webhook_queue usando la unique (job_id, clave_acceso).
 * Inserta nuevos y actualiza state/url/raw_json si ya existen.
 */
if (!function_exists('_queue_upsert_batch')) {
    function _queue_upsert_batch($con, $jobId, $ruc, $state, $items, $raw_payload = '')
    {
        if (empty($items) || !is_array($items)) {
            logInfo('[QUEUE] upsert batch', array('ins/upd' => 0, 'nota' => 'items vacíos'));
            return;
        }

        // Evita sobreescribir url_xml / raw_json con valores vacíos
        $sql = "
        INSERT INTO sri_webhook_queue (job_id, state, ruc_empresa, clave_acceso, url_xml, raw_json)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            state       = VALUES(state),
            ruc_empresa = VALUES(ruc_empresa),
            url_xml = CASE
                        WHEN VALUES(url_xml) IS NOT NULL AND VALUES(url_xml) <> '' THEN VALUES(url_xml)
                        ELSE url_xml
                      END,
            raw_json = CASE
                        WHEN VALUES(raw_json) IS NOT NULL AND VALUES(raw_json) <> '' THEN VALUES(raw_json)
                        ELSE raw_json
                      END";

        $st = mysqli_prepare($con, $sql);
        if (!$st) {
            logErr('Prepare upsert queue falló', mysqli_error($con));
            return;
        }

        $n = 0;
        foreach ($items as $it) {
            if (!is_array($it)) continue;

            // PHP 5.6: sin ??
            if (isset($it['clave_acceso'])) {
                $clave = (string)$it['clave_acceso'];
            } elseif (isset($it['claveAcceso'])) {
                $clave = (string)$it['claveAcceso'];
            } elseif (isset($it['clave'])) {
                $clave = (string)$it['clave'];
            } else {
                $clave = '';
            }
            if ($clave === '') continue;

            if (isset($it['url_xml'])) {
                $url = (string)$it['url_xml'];
            } elseif (isset($it['xmlUrl'])) {
                $url = (string)$it['xmlUrl'];
            } else {
                $url = '';
            }

            // Forzamos todo como string para evitar overflow en BIGINT con PHP 32-bits
            $jobIdStr = (string)$jobId;
            $stateStr = (string)$state;
            $rucStr   = (string)$ruc;
            $rawStr   = (string)$raw_payload;

            mysqli_stmt_bind_param($st, "ssssss", $jobIdStr, $stateStr, $rucStr, $clave, $url, $rawStr);
            if (!mysqli_stmt_execute($st)) {
                logWarn('Upsert falló para clave', array('clave' => $clave, 'err' => mysqli_stmt_error($st)));
                continue;
            }
            $n++;
        }

        mysqli_stmt_close($st);
        logInfo('[QUEUE] upsert batch', array('ins/upd' => $n));
    }
}

/**
 * Devuelve pendientes (processed=0) por job y ruc.
 */
if (!function_exists('_queue_fetch_pendientes_por_job')) {
    function _queue_fetch_pendientes_por_job($con, $jobId, $ruc)
    {
        $sql = "SELECT clave_acceso, url_xml
                  FROM sri_webhook_queue
                 WHERE job_id = ? AND ruc_empresa = ? AND processed = 0";
        $st = mysqli_prepare($con, $sql);
        if (!$st) {
            logErr('Prepare fetch pendientes por job falló', mysqli_error($con));
            return array();
        }
        mysqli_stmt_bind_param($st, "is", $jobId, $ruc);
        mysqli_stmt_execute($st);
        $rs = mysqli_stmt_get_result($st);
        $out = array();
        if ($rs) {
            while ($row = mysqli_fetch_assoc($rs)) {
                $out[] = array(
                    'clave_acceso' => (string)$row['clave_acceso'],
                    'url_xml'      => $row['url_xml'] ? (string)$row['url_xml'] : null
                );
            }
            mysqli_free_result($rs);
        }
        mysqli_stmt_close($st);
        return $out;
    }
}

/**
 * Barrido global de pendientes (processed=0). Límite configurable.
 */
if (!function_exists('_queue_fetch_pendientes_global')) {
    function _queue_fetch_pendientes_global($con, $limit = 1000)
    {
        $limit = max(1, min($limit, 5000));
        $sql = "SELECT job_id, ruc_empresa, clave_acceso, url_xml
                  FROM sri_webhook_queue
                 WHERE processed = 0
              ORDER BY created_at ASC
                 LIMIT {$limit}";
        $rs = mysqli_query($con, $sql);
        if (!$rs) {
            logErr('Query fetch pendientes global falló', mysqli_error($con));
            return array();
        }
        $out = array();
        while ($row = mysqli_fetch_assoc($rs)) {
            $out[] = array(
                'job_id'       => (int)$row['job_id'],
                'ruc_empresa'  => (string)$row['ruc_empresa'],
                'clave_acceso' => (string)$row['clave_acceso'],
                'url_xml'      => $row['url_xml'] ? (string)$row['url_xml'] : null
            );
        }
        mysqli_free_result($rs);
        return $out;
    }
}

/**
 * Marca como procesadas por lista de claves para un (job_id, ruc).
 */
if (!function_exists('_queue_mark_processed')) {
    function _queue_mark_processed($con, $jobId, $ruc, $claves)
    {
        $claves = array_values(array_unique(array_filter(array_map('strval', $claves))));
        if (empty($claves)) return;

        // Construir placeholders dinámicos
        $placeholders = implode(',', array_fill(0, count($claves), '?'));
        $types = str_repeat('s', count($claves));
        $sql = "UPDATE sri_webhook_queue
                   SET processed = 1, processed_at = NOW(), error_msg = NULL
                 WHERE job_id = ? AND ruc_empresa = ? AND clave_acceso IN ($placeholders)";
        $st = mysqli_prepare($con, $sql);
        if (!$st) {
            logErr('Prepare mark processed falló', mysqli_error($con));
            return;
        }
        // tipos: i (job), s (ruc), s... (claves)
        $typesAll = 'is' . $types;
        $params = array_merge(array($typesAll, $jobId, $ruc), $claves);
        _stmt_bind_params_dynamic($st, $params);
        mysqli_stmt_execute($st);
        mysqli_stmt_close($st);

        logInfo('[QUEUE] marcadas como procesadas', array('jobId' => $jobId, 'ruc' => $ruc, 'n' => count($claves)));
    }
}

/**
 * Helper para bind_param con número variable de args.
 */
if (!function_exists('_stmt_bind_params_dynamic')) {
    function _stmt_bind_params_dynamic($stmt, $params)
    {
        // $params[0] = types. Resto = valores.
        $refs = array();
        foreach ($params as $k => $v) {
            $refs[$k] = &$params[$k];
        }
        call_user_func_array(array($stmt, 'bind_param'), $refs);
    }
}


/* ========= CLI ========= */
$mode = isset($argv[1]) ? trim($argv[1]) : 'daily';
$fechaArg = null;
if (isset($argv[2]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $argv[2])) {
    $fechaArg = $argv[2];
}

if ($mode === 'daily') {
    // Si no pasas fecha, usa ayer
    if (!$fechaArg) {
        $ayer = new DateTime();
        $ayer->modify('-1 day');
        $fechaArg = $ayer->format('Y-m-d');
    }
    run_daily($fechaArg);
} elseif ($mode === 'process') {
    run_process($fechaArg); // si no hay fecha, toma últimas 2h
} else {
    echo "Uso:\n";
    echo "  php .../cron_descargas.php daily [YYYY-MM-DD]\n";
    echo "  php .../cron_descargas.php process [YYYY-MM-DD]\n";
}


// ===================== ENTRYPOINT CLI =====================
if (php_sapi_name() === 'cli') {
    // Ver argumentos
    $accion    = isset($argv[1]) ? $argv[1] : null;
    $fechaYmd  = isset($argv[2]) ? $argv[2] : null;

    // Trazas mínimas a stdout para que veas algo en el log
    echo "[ENTRY] accion=", ($accion ? $accion : 'NULL'), " fecha=", ($fechaYmd ? $fechaYmd : 'NULL'), " @", date('Y-m-d H:i:s'), "\n";

    if ($accion === 'process') {
        // Si quieres forzar logging de PHP adicional (opcional):
        // ini_set('log_errors','1');
        // ini_set('error_log','/var/log/camagare/cron_descargas_php.log');

        run_process($fechaYmd); // llama a tu función
        echo "[EXIT] run_process terminado @", date('Y-m-d H:i:s'), "\n";
        exit(0);
    }

    // Si no coincide ninguna acción, muestra ayuda
    echo "Uso: php cron_descargas.php process [YYYY-mm-dd]\n";
    exit(1);
}
