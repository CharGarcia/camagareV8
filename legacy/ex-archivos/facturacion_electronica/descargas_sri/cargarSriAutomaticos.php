<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('America/Guayaquil');
include('/var/www/html/sistema/conexiones/conectalogin.php');
include('/var/www/html/sistema/clases/lee_xml.php');


if (!defined('SRI_LIB_ONLY')) {

    /* =========================================================
     * Helpers de normalización (webhook y queue) y dedupe
     * ========================================================= */

    if (!function_exists('normalizar_items_webhook')) {
        /**
         * Aplana y normaliza cualquier estructura de results del proveedor.
         * Devuelve arreglo de items con:
         *  - clave_acceso (solo dígitos)
         *  - url_xml
         *  - url_ride (si viene)
         *  - tipo_evento (si viene)
         *  - costo (float si viene)
         *  - ip (si viene; será ip_evento al guardar)
         */
        function normalizar_items_webhook($items)
        {
            $out    = array();
            $vistos = array();
            $stack  = array($items);

            while (!empty($stack)) {
                $node = array_pop($stack);
                if (!is_array($node)) continue;

                $tieneClave = (isset($node['clave_acceso']) || isset($node['claveAcceso']) || isset($node['clave']));
                $tieneUrl   = (isset($node['url_xml']) || isset($node['xmlUrl']));

                if ($tieneClave && $tieneUrl) {
                    $clave = isset($node['clave_acceso']) ? $node['clave_acceso']
                        : (isset($node['claveAcceso']) ? $node['claveAcceso']
                            : (isset($node['clave']) ? $node['clave'] : null));
                    $url_xml = isset($node['url_xml']) ? $node['url_xml']
                        : (isset($node['xmlUrl']) ? $node['xmlUrl'] : null);

                    if ($clave && $url_xml) {
                        $clave_norm = preg_replace('/\D+/', '', (string)$clave);
                        if ($clave_norm !== '' && !isset($vistos[$clave_norm])) {
                            $vistos[$clave_norm] = true;

                            $out[] = array(
                                'clave_acceso' => $clave_norm,
                                'url_xml'      => (string)$url_xml,
                                'url_ride'     => isset($node['url_ride']) ? (string)$node['url_ride'] : (isset($node['pdfUrl']) ? (string)$node['pdfUrl'] : null),
                                'tipo_evento'  => isset($node['tipo_evento']) ? (string)$node['tipo_evento'] : (isset($node['evento']) ? (string)$node['evento'] : null),
                                'costo'        => isset($node['costo']) ? (float)$node['costo'] : null,
                                'ip'           => isset($node['ip']) ? (string)$node['ip'] : null,
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

    if (!function_exists('normalizar_items_queue')) {
        /**
         * Normaliza filas de sri_webhook_queue a pares mínimos {clave_acceso, url_xml}.
         */
        function normalizar_items_queue($rows)
        {
            $out = array();
            if (!is_array($rows)) return $out;
            $vistos = array();

            foreach ($rows as $r) {
                $clave = isset($r['clave_acceso']) ? $r['clave_acceso'] : null;
                $url   = isset($r['url_xml']) ? $r['url_xml'] : null;
                if (!$clave || !$url) continue;

                $clave_norm = preg_replace('/\D+/', '', (string)$clave);
                if ($clave_norm === '') continue;
                if (isset($vistos[$clave_norm])) continue;
                $vistos[$clave_norm] = true;

                $out[] = array('clave_acceso' => $clave_norm, 'url_xml' => (string)$url);
            }
            return $out;
        }
    }

    if (!function_exists('obtener_docs_queue_por_job')) {
        /**
         * Lee documentos de sri_webhook_queue por job_id y ruc_empresa.
         * Devuelve arreglo de filas crudas, luego se normaliza con normalizar_items_queue().
         */
        function obtener_docs_queue_por_job($con, $job_id, $ruc_empresa)
        {
            $out = array();
            $sql = "SELECT clave_acceso, url_xml, url_ride, tipo_evento, costo, ip_evento
                      FROM sri_webhook_queue
                     WHERE job_id = ?
                       AND (ruc_empresa = ? OR ruc_empresa IS NULL)";
            $st = mysqli_prepare($con, $sql);
            if (!$st) return $out;

            mysqli_stmt_bind_param($st, "is", $job_id, $ruc_empresa);
            mysqli_stmt_execute($st);
            mysqli_stmt_store_result($st);

            $clave = $url_xml = $url_ride = $tipo_evento = $ip_evt = null;
            $costo = null;
            mysqli_stmt_bind_result($st, $clave, $url_xml, $url_ride, $tipo_evento, $costo, $ip_evt);

            while (mysqli_stmt_fetch($st)) {
                $out[] = array(
                    'clave_acceso' => $clave,
                    'url_xml'      => $url_xml,
                    'url_ride'     => $url_ride,
                    'tipo_evento'  => $tipo_evento,
                    'costo'        => $costo,
                    'ip_evento'    => $ip_evt,
                );
            }

            mysqli_stmt_free_result($st);
            mysqli_stmt_close($st);
            return $out;
        }
    }

    if (!function_exists('dividir_por_existencia_en_sistema')) {
        /**
         * Divide documentos en [nuevos, ya_registrados] según existencia en tablas finales.
         * Usa ruc_empresa + aut_sri (clave_acceso) para chequear.
         */
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

            $excluir = array();
            if (function_exists('obtener_claves_no_descargar')) {
                $excluir = (array)obtener_claves_no_descargar($con, $ruc_empresa);
            }

            $sql = "SELECT 1 FROM $tabla WHERE ruc_empresa = ? AND aut_sri = ? LIMIT 1";
            $st  = mysqli_prepare($con, $sql);
            if (!$st) return array($documentos, $ya);

            $vistos = array();
            foreach ($documentos as $d) {
                $clave = isset($d['clave_acceso']) ? $d['clave_acceso'] : null;
                $url   = isset($d['url_xml']) ? $d['url_xml'] : null;
                if (!$clave || !$url) continue;

                if (isset($vistos[$clave])) continue;
                $vistos[$clave] = true;

                if (isset($excluir[$clave])) {
                    $ya[] = array('clave_acceso' => $clave, 'url_xml' => $url);
                    continue;
                }

                mysqli_stmt_bind_param($st, "ss", $ruc_empresa, $clave);
                mysqli_stmt_execute($st);
                mysqli_stmt_store_result($st);

                if (mysqli_stmt_num_rows($st) > 0) {
                    $ya[] = array('clave_acceso' => $clave, 'url_xml' => $url);
                } else {
                    $nuevos[] = array('clave_acceso' => $clave, 'url_xml' => $url);
                }

                mysqli_stmt_free_result($st);
            }

            mysqli_stmt_close($st);
            return array($nuevos, $ya);
        }
    }

    /* =========================================================
     * Validador de respuesta de creación de job
     * ========================================================= */
    if (!function_exists('validar_respuesta_descarga')) {
        function validar_respuesta_descarga($resp)
        {
            $out = array(
                'ok'      => false,
                'jobId'   => null,
                'http'    => isset($resp['http_code']) ? (int)$resp['http_code'] : null,
                'mensaje' => 'Respuesta inválida del servicio',
            );

            if (!is_array($resp)) {
                $out['mensaje'] = 'No hubo respuesta del servicio';
                return $out;
            }
            if (!empty($resp['curl_error'])) {
                $out['mensaje'] = 'Error de red/cURL: ' . $resp['curl_error'];
                return $out;
            }
            if (isset($resp['response']['jobId']) && $resp['response']['jobId'] !== '') {
                $out['ok']      = true;
                $out['jobId']   = $resp['response']['jobId'];
                $out['mensaje'] = 'Solicitud aceptada: tarea creada';
                return $out;
            }
            if (isset($resp['response']['message']) && $resp['response']['message'] !== '') {
                $out['mensaje'] = $resp['response']['message'];
            } else {
                $out['mensaje'] = 'Servicio respondió pero no se obtuvo el id de la tarea';
            }
            return $out;
        }
    }

    /* =========================================================
     * Acción principal
     * ========================================================= */
    if (isset($_POST['action']) && $_POST['action'] === 'cargar_otros_periodos_async') {

        // Respuesta inmediata + cierre limpio
        $emitir_respuesta_y_salir = function ($html) {
            @ini_set('zlib.output_compression', '0');
            @ini_set('output_buffering', 'off');
            while (ob_get_level()) {
                @ob_end_flush();
            }
            ob_implicit_flush(true);
            if (!headers_sent()) {
                header('Content-Type: text/html; charset=UTF-8');
                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                header('Pragma: no-cache');
            }
            echo $html;
            echo str_repeat(" ", 4096);
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            } else {
                @flush();
            }
            exit;
        };

        /* ----- 0) Parámetros ----- */
        $tipo_comprobante = isset($_POST['tipo_documento'])       ? $_POST['tipo_documento']       : null;
        $anio             = isset($_POST['anio_descarga'])         ? (int)$_POST['anio_descarga']   : null;
        $mes              = isset($_POST['mes_descarga'])          ? (int)$_POST['mes_descarga']    : null;
        $dia_raw          = isset($_POST['dia_descarga'])          ? $_POST['dia_descarga']         : '';
        $ruc              = isset($_POST['ruc_empresa_descarga'])  ? $_POST['ruc_empresa_descarga'] : null;
        $password         = isset($_POST['clave_sri_descargas'])   ? $_POST['clave_sri_descargas']  : null;

        /* ----- 1) Validación día 0..31 ----- */
        $dia = trim((string)$dia_raw);
        if ($dia === '' || !ctype_digit($dia)) {
            $emitir_respuesta_y_salir('<div class="alert alert-warning" role="alert"><b>Dato inválido:</b> El <b>día</b> debe ser 0–31 (0 = todos).</div>');
        }
        $dia_int = (int)$dia;
        if ($dia_int < 0 || $dia_int > 31) {
            $emitir_respuesta_y_salir('<div class="alert alert-warning" role="alert"><b>Fuera de rango:</b> Día 0–31 (0 = todos).</div>');
        }

        /* ----- 2) Conexión BD ----- */
        if (!function_exists('conenta_login')) {
            $emitir_respuesta_y_salir('<div class="alert alert-danger" role="alert"><b>Error:</b> Falta conenta_login().</div>');
        }
        $con = conenta_login();
        if (!$con) {
            $emitir_respuesta_y_salir('<div class="alert alert-danger" role="alert"><b>Error:</b> No se pudo conectar a la BD.</div>');
        }

        /* ----- 3) Busca job de HOY por filtros EXACTOS ----- */
        $hoy = date('Y-m-d');
        $sqlHoy = "SELECT job_id, token, codemp, estado, creado_en
                     FROM sri_jobs_log
                    WHERE ruc_empresa = ?
                      AND tipo_comprobante = ?
                      AND anio = ?
                      AND mes  = ?
                      AND dia  = ?
                      AND DATE(creado_en) = ?
                    ORDER BY id DESC
                    LIMIT 1";

        $st = mysqli_prepare($con, $sqlHoy);
        if (!$st) {
            mysqli_close($con);
            $emitir_respuesta_y_salir('<div class="alert alert-danger" role="alert"><b>Error:</b> No se pudo preparar la consulta.</div>');
        }
        mysqli_stmt_bind_param($st, "ssiiis", $ruc, $tipo_comprobante, $anio, $mes, $dia_int, $hoy);
        mysqli_stmt_execute($st);
        mysqli_stmt_store_result($st);

        $job_id_db = $token_db = $codemp_db = $estado_db = $creado_en_db = null;
        mysqli_stmt_bind_result($st, $job_id_db, $token_db, $codemp_db, $estado_db, $creado_en_db);
        $tiene_hoy = mysqli_stmt_fetch($st) ? true : false;
        mysqli_stmt_free_result($st);
        mysqli_stmt_close($st);

        if ($tiene_hoy && !empty($job_id_db) && !empty($token_db) && !empty($codemp_db)) {
            /* ========= CASO A: Ya existe job HOY → usar queue y/o proveedor ========= */
            $jobId  = (string)$job_id_db;
            $token  = (string)$token_db;
            $codemp = (string)$codemp_db;

            // 4) Leer documentos locales de la queue
            $docs_queue_raw = obtener_docs_queue_por_job($con, (int)$jobId, $ruc);
            $docs_queue     = normalizar_items_queue($docs_queue_raw);

            if (empty($docs_queue)) {
                // Intento de PULL al proveedor para este job de HOY
                $sin_docs_hoy = false;
                $state = '';
                $count = 0;

                if (function_exists('consulta_status_job')) {
                    $resp_status = consulta_status_job($codemp, $token, $jobId);

                    if (is_array($resp_status) && !empty($resp_status['ok'])) {
                        $state = isset($resp_status['state']) ? (string)$resp_status['state'] : '';
                        $items = (isset($resp_status['results']) && is_array($resp_status['results'])) ? $resp_status['results'] : array();
                        $count = isset($resp_status['count']) ? (int)$resp_status['count'] : (is_array($items) ? count($items) : 0);
                        $raw_payload = isset($resp_status['raw'])
                            ? (is_array($resp_status['raw']) ? json_encode($resp_status['raw'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string)$resp_status['raw'])
                            : '';

                        $items_norm = normalizar_items_webhook($items);

                        if (!empty($items_norm)) {
                            // Separar nuevos vs ya registrados EN SISTEMA
                            list($items_nuevos, $items_ya) = dividir_por_existencia_en_sistema($con, $items_norm, $tipo_comprobante, $ruc);
                            $ya_previos = is_array($items_ya) ? count($items_ya) : 0;

                            // Registrar SOLO nuevos usando url_xml del webhook
                            $ok_count = $fail_count = $warn_count = $ya_count = 0;
                            $resp_fin = array();

                            if (!empty($items_nuevos) && function_exists('descarga_xml_guardado')) {
                                $resp_fin = descarga_xml_guardado($con, $items_nuevos, $ruc);
                                if (is_array($resp_fin)) {
                                    foreach ($resp_fin as $m) {
                                        $e = isset($m['estado']) ? (string)$m['estado'] : '';
                                        if ($e === '1') $ok_count++;
                                        elseif ($e === '0') $fail_count++;
                                        elseif ($e === '2') $warn_count++;
                                        elseif ($e === '3') $ya_count++;
                                    }
                                }
                            }

                            // Guardar TODO en queue
                            $insertados = $actualizados = 0;
                            $errores_ins = array();
                            if (function_exists('guardar_en_sri_webhook_queue')) {
                                $summary = guardar_en_sri_webhook_queue($con, $jobId, $state, $ruc, $items_norm, null, $raw_payload);
                                if (is_array($summary)) {
                                    $insertados   = isset($summary['insertados'])   ? (int)$summary['insertados']   : 0;
                                    $actualizados = isset($summary['actualizados']) ? (int)$summary['actualizados'] : 0;
                                    if (!empty($summary['errores'])) $errores_ins = (array)$summary['errores'];
                                }
                            }

                            // Marcar como procesadas en queue (ya existentes + exitosas)
                            if (function_exists('marca_claves_queue_como_procesadas')) {
                                $claves_proc = array();
                                foreach ($items_ya as $it) $claves_proc[] = $it['clave_acceso'];
                                if (!empty($resp_fin)) {
                                    foreach ($resp_fin as $m) {
                                        if (isset($m['estado']) && (string)$m['estado'] === '1' && isset($m['clave_acceso'])) {
                                            $claves_proc[] = $m['clave_acceso'];
                                        }
                                    }
                                }
                                if (!empty($claves_proc)) {
                                    @marca_claves_queue_como_procesadas($con, $jobId, $claves_proc, $ruc, null);
                                }
                            }

                            // Estado del job
                            @mysqli_query($con, "UPDATE sri_jobs_log SET estado='PROCESADO' WHERE job_id='" . mysqli_real_escape_string($con, $jobId) . "'");

                            mysqli_close($con);

                            $html = '<div class="alert alert-success" role="alert">' .
                                '✅ <b>Procesamiento completado.</b><br>' .
                                '<b>Estado descarga:</b> ' . htmlspecialchars($state, ENT_QUOTES, 'UTF-8') . '<br>' .
                                '<hr style="margin:6px 0">' .
                                '<b>Documentos</b>: insertados ' . (int)$insertados . ', actualizados ' . (int)$actualizados . '.<br>' .
                                '<b>Descarga</b>: ✔ ' . (int)$ok_count . ' • ⚠ ' . (int)$warn_count . ' • ✖ ' . (int)$fail_count . ' • ⟳ ' . (int)$ya_count . '<br>' .
                                '<small>Puedes volver a <b>solicitar mañana</b> para este período (se creará una nueva solicitud).</small>' .
                                '</div>';
                            if (!empty($errores_ins)) {
                                $html .= '<div class="alert alert-warning" role="alert"><b>Notas:</b> ' .
                                    htmlspecialchars(json_encode($errores_ins, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') .
                                    '</div>';
                            }
                            $emitir_respuesta_y_salir($html);
                        } else {
                            // Confirmación: COMPLETADO sin docs hoy
                            $sin_docs_hoy = (strcasecmp($state, 'COMPLETADO') === 0 && $count === 0);

                            @mysqli_query(
                                $con,
                                "UPDATE sri_jobs_log
                                   SET estado = '" . ($sin_docs_hoy ? "COMPLETADO_SIN_DOCS" : "EN_PROCESO") . "'
                                 WHERE job_id = '" . mysqli_real_escape_string($con, $jobId) . "'
                                 LIMIT 1"
                            );

                            mysqli_close($con);
                            $emitir_respuesta_y_salir(
                                '<div class="alert alert-success" role="alert">' .
                                    'ℹ️ <b>Consulta completada</b>, pero <b>hoy no hay comprobantes disponibles</b>. ' .
                                    'Por favor, vuelve a intentarlo <b>mañana</b>.' .
                                    '<br><small>Mañana puede generar una <b>nueva solicitud</b> para este período.</small>' .
                                    '</div>'
                            );
                        }
                    } else {
                        $detalle = is_array($resp_status) && isset($resp_status['error']) ? $resp_status['error'] : 'Error desconocido';
                        mysqli_close($con);
                        $emitir_respuesta_y_salir('<div class="alert alert-danger" role="alert"><b>Error consultando la descarga:</b> ' .
                            htmlspecialchars($detalle, ENT_QUOTES, 'UTF-8') . '</div>');
                    }
                } else {
                    // No hay queue y no se puede consultar → pedir intentar mañana
                    @mysqli_query($con, "UPDATE sri_jobs_log SET estado='EN_PROCESO' WHERE job_id='" . mysqli_real_escape_string($con, $jobId) . "'");
                    mysqli_close($con);
                    $emitir_respuesta_y_salir(
                        '<div class="alert alert-success" role="alert">ℹ️ No se encontraron comprobantes <b>hoy</b>. ' .
                            'Vuelve a intentarlo <b>mañana</b> (se puede crear una nueva solicitud).</div>'
                    );
                }
            }

            // 5) Tenemos docs en queue → procesar SOLO nuevos
            list($items_nuevos, $items_ya) = dividir_por_existencia_en_sistema($con, $docs_queue, $tipo_comprobante, $ruc);
            $ya_previos = is_array($items_ya) ? count($items_ya) : 0;

            $ok_count = $fail_count = $warn_count = $ya_count = 0;
            $tot_resp = 0;
            $resp_fin = array();

            if (!empty($items_nuevos) && function_exists('descarga_xml_guardado')) {
                $resp_fin = descarga_xml_guardado($con, $items_nuevos, $ruc);
                if (is_array($resp_fin)) {
                    $tot_resp = count($resp_fin);
                    foreach ($resp_fin as $m) {
                        $e = isset($m['estado']) ? (string)$m['estado'] : '';
                        if ($e === '1') $ok_count++;
                        elseif ($e === '0') $fail_count++;
                        elseif ($e === '2') $warn_count++;
                        elseif ($e === '3') $ya_count++;
                    }
                }
            }

            // Marcar como procesadas en queue (ya existentes + exitosas)
            if (function_exists('marca_claves_queue_como_procesadas')) {
                $claves_proc = array();
                foreach ($items_ya as $it) $claves_proc[] = $it['clave_acceso'];
                if (!empty($resp_fin)) {
                    foreach ($resp_fin as $m) {
                        if (isset($m['estado']) && (string)$m['estado'] === '1' && isset($m['clave_acceso'])) {
                            $claves_proc[] = $m['clave_acceso'];
                        }
                    }
                }
                if (!empty($claves_proc)) {
                    @marca_claves_queue_como_procesadas($con, $jobId, $claves_proc, $ruc, null);
                }
            }

            mysqli_close($con);

            $html = '<div class="alert alert-success" role="alert">' .
                '✅ <b>Procesamiento completado.</b><br>' .
                '<hr style="margin:6px 0">' .
                '<b>Nuevos procesados</b>: ' . (int)$tot_resp . '<br>' .
                '<b>Resultados</b>: ✔ ' . (int)$ok_count . ' • ⚠ ' . (int)$warn_count . ' • ✖ ' . (int)$fail_count . ' • ⟳ ' . (int)$ya_count . '<br>' .
                '<b>Ya registrados previamente</b>: ' . (int)$ya_previos . '<br>' .
                '<small>Puedes volver a <b>solicitar mañana</b> para este período (se puede crear una nueva solicitud).</small>' .
                '</div>';
            $emitir_respuesta_y_salir($html);
        } else {
            /* ========= CASO B: No existe job HOY → crear y registrar ========= */
            if (!function_exists('login_descargas') || !function_exists('descargas_manuales_por_fecha')) {
                mysqli_close($con);
                $emitir_respuesta_y_salir('<div class="alert alert-danger" role="alert"><b>Error:</b> Faltan login_descargas() o descargas_manuales_por_fecha().</div>');
            }

            $login_descargas = login_descargas();
            if (!is_array($login_descargas) || empty($login_descargas['codemp']) || empty($login_descargas['token'])) {
                mysqli_close($con);
                $emitir_respuesta_y_salir('<div class="alert alert-danger" role="alert"><b>Error:</b> No se obtuvieron credenciales válidas.</div>');
            }
            $codemp = $login_descargas['codemp'];
            $token  = $login_descargas['token'];

            $resp_crea = descargas_manuales_por_fecha($codemp, $token, $ruc, $password, $tipo_comprobante, $dia_int, $mes, $anio);
            $val       = validar_respuesta_descarga($resp_crea);

            if (!$val['ok']) {
                $msg = htmlspecialchars($val['mensaje'], ENT_QUOTES, 'UTF-8');
                mysqli_close($con);
                $emitir_respuesta_y_salir('<div class="alert alert-danger" role="alert"><b>No se pudo crear la solicitud.</b> ' . $msg . '</div>');
            }

            $jobId = (string)$val['jobId'];
            $now   = date('Y-m-d H:i:s');

            $sqlIns = "INSERT IGNORE INTO sri_jobs_log
                       (job_id, token, codemp, ruc_empresa, tipo_comprobante, anio, mes, dia, estado, creado_en)
                       VALUES (
                           '" . mysqli_real_escape_string($con, $jobId) . "',
                           '" . mysqli_real_escape_string($con, $token) . "',
                           '" . mysqli_real_escape_string($con, $codemp) . "',
                           '" . mysqli_real_escape_string($con, $ruc) . "',
                           '" . mysqli_real_escape_string($con, $tipo_comprobante) . "',
                           " . (int)$anio . ", " . (int)$mes . ", " . (int)$dia_int . ",
                           'CREADO', '" . $now . "'
                       )";
            @mysqli_query($con, $sqlIns);

            mysqli_close($con);

            $emitir_respuesta_y_salir(
                '<div class="alert alert-success" role="alert">✅ <b>Solicitud creada y registrada.</b><br>' .
                    'Si hoy no aparecen documentos, podrás <b>solicitar de nuevo mañana</b> para este período y se generará una nueva solicitud.</div>'
            );
        }
    }
}


//lee el xml descargado al otro servidor
function descarga_xml_guardado($con, $clavesAccesoRegistrar, $ruc_empresa)
{
    $id_usuario = 1;
    $rides_sri  = new rides_sri();
    $respuestas = array();

    // --- Helper: aplanar recursivamente hasta items con 'xmlUrl' y 'claveAcceso'
    $claves_flat = array();
    $stack = array($clavesAccesoRegistrar);

    while (!empty($stack)) {
        $node = array_pop($stack);

        if (is_array($node)) {
            // ¿Es un item válido?
            $tieneXml = (isset($node['xmlUrl']) || isset($node['url_xml']));
            $tieneCla = (isset($node['claveAcceso']) || isset($node['clave_acceso']));

            if ($tieneXml && $tieneCla) {
                $claves_flat[] = array(
                    'claveAcceso' => isset($node['claveAcceso']) ? $node['claveAcceso'] : $node['clave_acceso'],
                    'xmlUrl'      => isset($node['xmlUrl']) ? $node['xmlUrl'] : $node['url_xml'],
                );
            } else {
                // No es item → seguir explorando hijos
                foreach ($node as $child) {
                    if (is_array($child)) {
                        $stack[] = $child;
                    }
                }
            }
        }
    }

    // --- Procesar cada item (sin tocar la URL/path)
    foreach ($claves_flat as $valores) {
        if (!isset($valores['xmlUrl'])) {
            continue;
        }

        $url_final = $valores['xmlUrl']; // ← tal cual viene (absoluta o relativa)

        $object_xml = $rides_sri->lee_xml($url_final);
        if ($object_xml === false || $object_xml === null) {
            $respuestas[] = array(
                'ok'   => false,
                'url'  => $url_final,
                'clave' => $valores['claveAcceso'],
                'msg'  => 'No se pudo leer el XML'
            );
            continue;
        }

        $respuestas[] = $rides_sri->lee_archivo_xml($object_xml, $ruc_empresa, $id_usuario, $con);
    }

    return $respuestas;
}



//para ver si las claves ya estan registradas en el sistema
// Filtra y devuelve SOLO las claves que NO existen en tu BD (columna aut_sri)
function verificar_claves_registradas($con, $documentos_descargados, $tipo_comprobante, $ruc_empresa)
{
    $xml_urls = array();

    // Mapa tipo → tabla
    $tabla_por_tipo = array(
        "1" => "encabezado_compra",
        "2" => "encabezado_liquidacion",
        "3" => "encabezado_compra",
        "4" => "encabezado_compra",
        "6" => "encabezado_retencion_venta",
    );

    $td = (string)$tipo_comprobante;
    if (!isset($tabla_por_tipo[$td])) {
        return $xml_urls; // tipo inválido
    }
    $tabla = $tabla_por_tipo[$td];

    // 1) Traer set de claves a excluir por empresa
    $excluir = obtener_claves_no_descargar($con, $ruc_empresa);

    // 2) Prepared para existencia por aut_sri
    $sql = "SELECT 1 FROM $tabla WHERE aut_sri = ? LIMIT 1";
    $stmt = mysqli_prepare($con, $sql);
    if (!$stmt) {
        error_log("verificar_claves_registradas: prepare EXISTE error: " . mysqli_error($con));
        return $xml_urls;
    }

    // 3) Evitar duplicados dentro del mismo lote
    $vistos = array();

    foreach ($documentos_descargados as $documento) {
        $claveAcceso = isset($documento['clave_acceso']) ? $documento['clave_acceso']
            : (isset($documento['claveAcceso']) ? $documento['claveAcceso'] : null);

        $xmlUrl = isset($documento['url_xml']) ? $documento['url_xml']
            : (isset($documento['xmlUrl']) ? $documento['xmlUrl'] : null);

        if (!$claveAcceso || !$xmlUrl) {
            continue;
        }

        // Excluir si está en lista de no-descargar
        if (isset($excluir[$claveAcceso])) {
            continue;
        }

        // Evitar repetir chequeo en el mismo batch
        if (isset($vistos[$claveAcceso])) {
            continue;
        }
        $vistos[$claveAcceso] = true;

        // Consultar existencia en tabla destino
        mysqli_stmt_bind_param($stmt, "s", $claveAcceso);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) == 0) {
            // No existe → incluir normalizado
            $xml_urls[] = array(
                'clave_acceso' => $claveAcceso,
                'url_xml'      => $xmlUrl,
            );
        }

        mysqli_stmt_free_result($stmt);
    }

    mysqli_stmt_close($stmt);
    return $xml_urls;
}


//obtener claves registradas como no descargar ya que pueden haber sido anuladas en el sri
function obtener_claves_no_descargar($con, $ruc_empresa)
{
    $excluir = array();

    $sql = "SELECT clave_acceso FROM claves_sri_no_descargar WHERE ruc_empresa = ?";
    $stmt = mysqli_prepare($con, $sql);
    if (!$stmt) {
        error_log("obtener_claves_no_descargar: prepare error: " . mysqli_error($con));
        return $excluir;
    }

    mysqli_stmt_bind_param($stmt, "s", $ruc_empresa);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $clave);
    while (mysqli_stmt_fetch($stmt)) {
        if ($clave !== null && $clave !== '') {
            $excluir[$clave] = true;
        }
    }
    mysqli_stmt_close($stmt);

    return $excluir;
}

//para extraer el ruc de la empresa y usarlo
/* function extract_ruc_from_url($url)
{
    $path = parse_url($url, PHP_URL_PATH);
    if (!$path) return null;
    if (preg_match('/\/(\d{13})\/SRI\//', $path, $m)) {
        return $m[1];
    }
    return null;
} */


/* =========================
 * 1) LOGIN (token + codemp)
 * ========================= */
function login_descargas()
{
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://app.datapluserp.com.ec/apiv3/users/auth/login',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode([
            "email" => "cmg.ba.sas@gmail.com",
            "password" => "cmgbasas"
        ]),
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json'
        ),
    ));

    $response = curl_exec($curl);
    $curlErr  = curl_error($curl);
    curl_close($curl);

    if ($curlErr) {
        error_log("login_descargas() cURL error: {$curlErr}");
        return array(
            "token"  => isset($data["token"]) ? $data["token"] : null,
            "codemp" => (isset($data["empresasAccesibles"][0]["codemp"]) ? $data["empresasAccesibles"][0]["codemp"] : null)
        );
    }

    $data = json_decode($response, true);

    return [
        "token"  => isset($data["token"]) ? $data["token"] : null,
        "codemp" => (isset($data["empresasAccesibles"][0]["codemp"]) ? $data["empresasAccesibles"][0]["codemp"] : null)
    ];
}

/* ============================================
 * 2) POST por empresa/tipo al endpoint externo
 * ============================================ */
function descargas_manuales_por_fecha($codemp, $token, $ruc, $password, $tipo_comprobante, $dia, $mes, $anio)
{
    // ⛑️ Normalizar token al formato requerido
    $token = trim((string)$token);
    if ($token === '') {
        return [
            "ok" => false,
            "http_code" => null,
            "curl_error" => "Token vacío o nulo",
            "request" => null,
            "response" => null
        ];
    }
    $auth = (stripos($token, 'Bearer ') === 0) ? $token : ('Bearer ' . $token);

    // Normalizar día/mes/año sin ceros a la izquierda y como texto
    $dia  = (string)((int)$dia);
    $mes  = (string)((int)$mes);
    $anio = (string)((int)$anio);

    // Payload como texto
    $payload = [
        "sri_ruc"          => (string)$ruc,
        "sri_pass"         => (string)$password,
        "tipo_comprobante" => (string)$tipo_comprobante,
        "dia"              => $dia,
        "mes"              => $mes,
        "anio"             => $anio,
        "webhook_url"      => "https://camagare.com/sistema/facturacion_electronica/descargas_sri/webhookDescargasSri.php"
    ];
    $postData = json_encode($payload, JSON_UNESCAPED_SLASHES);

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://app.datapluserp.com.ec/apiv3/compras/bandejaelectronica/downloadrequest',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_HTTPHEADER => array(
            'codemp: ' . $codemp,
            'seqcoeje: null',
            'Content-Type: application/json',
            'Authorization: ' . $auth
        ),
    ));

    $response = curl_exec($curl);
    $curlErr  = curl_error($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    return [
        "ok"         => $curlErr ? false : ($httpCode >= 200 && $httpCode < 300),
        "http_code"  => $httpCode,
        "curl_error" => $curlErr ?: null,
        "request"    => $payload,
        "response"   => $response ? json_decode($response, true) : null
    ];
}


// ---- Log de resultado de la solicitud inicial (útil para auditoría)
if (!function_exists('log_descarga_result')) {
    function log_descarga_result($ruc, $tipo, $dia, $mes, $anio, $resp, $t_ms = null)
    {
        $fecha = $dia . '-' . $mes . '-' . $anio;
        $http  = isset($resp['http_code']) ? (int)$resp['http_code'] : null;
        $curl  = isset($resp['curl_error']) ? $resp['curl_error'] : null;
        $status   = isset($resp['response']['status']) ? $resp['response']['status'] : null;
        $message  = isset($resp['response']['message']) ? $resp['response']['message'] : null;
        $jobId    = isset($resp['response']['jobId']) ? $resp['response']['jobId'] : null;
        $t_str    = ($t_ms !== null) ? " | t={$t_ms}ms" : "";

        if (!empty($curl)) {
            error_log("[NET]  RUC: {$ruc} | Tipo: {$tipo} | {$fecha} | cURL: {$curl}{$t_str}");
            return false;
        }
        if ($http === null || $http < 200 || $http >= 300) {
            error_log("[HTTP] RUC: {$ruc} | Tipo: {$tipo} | {$fecha} | HTTP: {$http}{$t_str}");
            return false;
        }
        if ($status === 'error') {
            error_log("[API]  RUC: {$ruc} | Tipo: {$tipo} | {$fecha} | Msg: {$message}{$t_str}");
            return false;
        }
        if (empty($jobId)) {
            error_log("[API]  RUC: {$ruc} | Tipo: {$tipo} | {$fecha} | Sin jobId" . ($message ? " | Msg: {$message}" : "") . "{$t_str}");
            return false;
        }

        error_log("[OK]   RUC: {$ruc} | Tipo: {$tipo} | {$fecha} | HTTP: {$http} | jobId: {$jobId}{$t_str}");
        return true;
    }
}

// ---- Espera con backoff: consulta status hasta COMPLETADO (silenciosa)
if (!function_exists('esperar_job_hasta_completado_backoff')) {
    function esperar_job_hasta_completado_backoff($codemp, $token, $jobId, $timeout_s = 300, $sleep_ini = 8, $sleep_max = 30, $factor = 1.6)
    {
        $inicio = time();
        $sleep  = (int)$sleep_ini;
        $int    = 0;
        $ultimo = null;

        while ((time() - $inicio) < $timeout_s) {
            $st = consulta_status_job($codemp, $token, $jobId);
            $ultimo = $st;

            if ($st && !empty($st['ok'])) {
                $state = isset($st['state']) ? strtoupper(trim((string)$st['state'])) : '';
                if (in_array($state, array('COMPLETADO', 'COMPLETED', 'FINALIZADO', 'TERMINADO'), true)) {
                    return array('ok' => true, 'final_state' => $state, 'intentos' => $int + 1);
                }
            }
            sleep($sleep);
            $int++;
            $sleep = min($sleep_max, (int)ceil($sleep * $factor));
        }

        return array(
            'ok'          => false,
            'final_state' => ($ultimo && isset($ultimo['state'])) ? $ultimo['state'] : null,
            'intentos'    => $int
        );
    }
}

/* function descargas_automaticas_diarias($con, $codemp, $token)
{
    // Fecha AYER (usa $dia='0' para todo el mes)
    $ayer = new DateTime();
    $ayer->modify('-1 day');
    $anio = (string)((int)$ayer->format('Y'));
    $mes  = (string)((int)$ayer->format('n'));
    $dia  = 12; // (string)((int)$ayer->format('j'));
    // $dia = '0';

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
        error_log('Empresas SQL error: ' . mysqli_error($con));
        echo "❌ Error consultando empresas.\n";
        return;
    }

    $tipos = array(1, 2, 3, 4, 6);

    while ($row = mysqli_fetch_assoc($qry)) {
        $ruc  = $row['ruc'];
        $pass = $row['pass'];

        foreach ($tipos as $tipo) {
            // 1) Crear pedido
            $t0  = microtime(true);
            $res = descargas_manuales_por_fecha($codemp, $token, $ruc, $pass, (string)$tipo, $dia, $mes, $anio);
            $ms  = (int)round((microtime(true) - $t0) * 1000);

            $ok   = (!empty($res['ok'])) ? 'OK' : 'FAIL';
            $http = isset($res['http_code']) ? $res['http_code'] : 'N/A';
            $job  = (isset($res['response']) && isset($res['response']['jobId'])) ? $res['response']['jobId'] : null;

            error_log("[SOLIC] {$ok} RUC: {$ruc} | Tipo: {$tipo} | {$dia}-{$mes}-{$anio} | ms={$ms} | HTTP={$http} | jobId=" . ($job ? $job : '-'));
            echo      "[{$ok}]   RUC: {$ruc} | Tipo: {$tipo} | {$dia}-{$mes}-{$anio} | HTTP={$http} | jobId=" . ($job ? $job : '-') . "\n";

            if (!$job) {
                continue;
            }

            // ===== 2) POST-PROCESO: esperar COMPLETADO, extraer ítems e insertar en cola =====
            $espera = esperar_job_hasta_completado_silencioso($codemp, $token, $job, 20, 12);
            if (empty($espera['ok'])) {
                $final_state = isset($espera['final_state']) ? $espera['final_state'] : 'N/A';
                error_log("[WAIT] jobId={$job} no llegó a COMPLETADO | final_state=" . $final_state);
                continue;
            }

            $st = procesar_resultado_job_si_completado($codemp, $token, $job);
            if (empty($st) || empty($st['ok']) || empty($st['completado'])) {
                error_log("[STATE] jobId={$job} no completado / error estado.");
                continue;
            }

            // Extraer items con tolerancia a estructura
            $items = array();
            if (isset($st['results']) && is_array($st['results'])) {
                $items = $st['results'];
            } elseif (isset($st['raw']) && is_array($st['raw'])) {
                if (isset($st['raw']['results']) && is_array($st['raw']['results'])) {
                    $items = $st['raw']['results'];
                } elseif (isset($st['raw']['data']['results']) && is_array($st['raw']['data']['results'])) {
                    $items = $st['raw']['data']['results'];
                } elseif (isset($st['raw']['data']['items']) && is_array($st['raw']['data']['items'])) {
                    $items = $st['raw']['data']['items'];
                } elseif (isset($st['raw']['items']) && is_array($st['raw']['items'])) {
                    $items = $st['raw']['items'];
                }
            }

            // Normaliza a lista si viene objeto/array asociativo
            if (!empty($items) && array_keys($items) !== range(0, count($items) - 1)) {
                $items = array($items);
            }

            $state = isset($st['state']) ? $st['state'] : 'COMPLETADO';
            error_log("[STATE] jobId={$job} COMPLETADO | items_status=" . count($items));

            if (!empty($items)) {
                $raw_payload = isset($st['raw']) ? $st['raw'] : $st;
                $summary = guardar_en_sri_webhook_queue($con, $job, $state, $ruc, $items, (string)$tipo, $raw_payload);
                $ins = isset($summary['insertados'])   ? (int)$summary['insertados']   : 0;
                $upd = isset($summary['actualizados']) ? (int)$summary['actualizados'] : 0;
                error_log("[QUEUE] jobId={$job} ins={$ins} upd={$upd}");
            } else {
                error_log("[QUEUE] jobId={$job} sin items en status (quizá llegan por webhook).");
            }
        }
    }
} */



/* ===== 1) CONSULTA STATUS DEL JOB (PHP 5.6) ===== */
function consulta_status_job($codemp, $token, $jobId)
{
    $authHeaderValue = (stripos($token, 'Bearer ') === 0) ? $token : ('Bearer ' . $token);

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://app.datapluserp.com.ec/apiv3/compras/bandejaelectronica/statusjob/jobId/{$jobId}",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            "codemp: {$codemp}",
            "seqcoeje: null",
            "Authorization: " . $authHeaderValue
        ),
    ));

    $response = curl_exec($curl);
    $curlErr  = curl_error($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($curlErr) {
        return array("ok" => false, "error" => $curlErr, "http" => $httpCode);
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return array("ok" => false, "error" => "JSON inválido", "raw" => $response, "http" => $httpCode);
    }

    return array(
        "ok"     => ($httpCode >= 200 && $httpCode < 300) && !empty($data),
        "http"   => $httpCode,
        "state"  => isset($data["state"]) ? $data["state"] : null,
        "results" => isset($data["results"]) ? $data["results"] : array(),
        "raw"    => $data
    );
}

/**
 * Espera activa del estado del job hasta COMPLETADO.
 *
 * @param string $codemp
 * @param string $token
 * @param string $jobId
 * @param int    $intervalSec   Segundos entre consultas (5-15 recomendado)
 * @param int    $maxMinutes    Tiempo máximo total (minutos)
 * @return array { ok, final_state, intentos, last_http, detalle }
 */
function esperar_job_hasta_completado($codemp, $token, $jobId, $max_intentos = 6, $segundos = 8, $silencioso = true)
{
    $historial = array();
    $ultimo_estado = null;

    for ($i = 1; $i <= $max_intentos; $i++) {
        $st = consulta_status_job($codemp, $token, $jobId);
        $http = isset($st['http']) ? $st['http'] : null;
        $estado = isset($st['state']) ? strtoupper(trim($st['state'])) : 'DESCONOCIDO';

        $historial[] = array('intento' => $i, 'estado' => $estado, 'http' => $http);
        $ultimo_estado = $estado;

        if (!$silencioso) {
            echo "[Intento $i] Estado=$estado (HTTP=$http) ";
        }

        if (in_array($estado, array('COMPLETADO', 'COMPLETED', 'FINALIZADO', 'TERMINADO'), true)) {
            return array('ok' => true, 'final_state' => $estado, 'intentos' => $i, 'historial' => $historial);
        }

        sleep((int)$segundos);
    }

    return array('ok' => false, 'final_state' => $ultimo_estado, 'intentos' => $max_intentos, 'historial' => $historial);
}


/**
 * Procesa el resultado del job SOLO si el estado es COMPLETADO.
 * - Consulta el estado vía API
 * - Si COMPLETADO, pasa $results a procesar_resultado_job()
 * - Si no, devuelve error y NO procesa
 *
 * @return array { ok, job_id, state, procesados, salidas, detalle }
 */

/**
 * Consulta el estado del job y retorna datos para gatear el flujo.
 * - NO persiste nada en BD.
 * - Devuelve: ok, http, state, completado, results, raw.
 * Compatible PHP 5.6.
 */
function procesar_resultado_job_si_completado($codemp, $token, $job_id)
{
    $st = consulta_status_job($codemp, $token, $job_id);

    // Si no hay respuesta válida, arma retorno estándar con error
    if (!$st || !isset($st['ok']) || !$st['ok']) {
        return array(
            "ok"         => false,
            "http"       => isset($st['http']) ? $st['http'] : null,
            "state"      => isset($st['state']) ? $st['state'] : null,
            "completado" => false,
            "results"     => array(),
            "raw"        => isset($st['raw']) ? $st['raw'] : (is_array($st) ? $st : array())
        );
    }

    // Normaliza estado y evalúa "completado"
    $state      = isset($st['state']) ? $st['state'] : '';
    $state_norm = strtoupper(trim((string)$state));
    $completado = in_array($state_norm, array('COMPLETADO', 'COMPLETED', 'FINALIZADO', 'TERMINADO'), true);

    // Tomar 'results' si existe; fallback a rutas comunes dentro de 'raw'
    $results = array();
    if (isset($st['results']) && is_array($st['results'])) {
        $results = $st['results'];
    } else if (isset($st['raw']) && is_array($st['raw'])) {
        $raw = $st['raw'];
        if (isset($raw['results']) && is_array($raw['results'])) {
            $results = $raw['results'];
        } else if (isset($raw['data']) && is_array($raw['data'])) {
            if (isset($raw['data']['results']) && is_array($raw['data']['results'])) {
                $results = $raw['data']['results'];
            } else if (isset($raw['data']['items']) && is_array($raw['data']['items'])) {
                $results = $raw['data']['items'];
            }
        } else if (isset($raw['items']) && is_array($raw['items'])) {
            $results = $raw['items'];
        }
    }

    return array(
        "ok"         => (bool)$st['ok'],
        "http"       => isset($st['http']) ? $st['http'] : null,
        "state"      => $state,
        "completado" => $completado,
        "results"     => $results,                          // lista de items si viene en el status
        "raw"        => isset($st['raw']) ? $st['raw'] : $st // payload completo para auditoría
    );
}


/**
 * Inserta en sri_webhook_queue cada item del array recibido.
 * - No usa prepared statements (compat PHP 5.6).
 * - Normaliza job_id a BIGINT (solo dígitos, recorte a 18).
 * - Usa ON DUPLICATE KEY UPDATE sobre (job_id, clave_acceso).
 *
 * @param mysqli $con
 * @param mixed  $job_id        ID del job (puede venir alfanumérico; se normaliza a BIGINT).
 * @param string $state         Estado del job (ej: COMPLETADO).
 * @param string $ruc_empresa   RUC de la empresa.
 * @param array  $items         Arreglo de items (cada item con: costo, ip, tipo_evento, clave_acceso, url_xml, url_ride).
 * @param string $source_ip     (opcional) IP origen del webhook/consulta.
 * @param mixed  $raw_payload   (opcional) Payload crudo para guardar en raw_json por fila (si null, se guarda el item mismo).
 * @return array                Resumen: insertados, actualizados, errores.
 */
function guardar_en_sri_webhook_queue($con, $job_id, $state, $ruc_empresa, $items, $source_ip = null, $raw_payload = null)
{
    // Charset recomendado
    if (function_exists('mysqli_set_charset')) {
        @mysqli_set_charset($con, 'utf8mb4');
    }

    // ---- Normalizadores (sin closures para PHP 5.6)
    // BIGINT: deja solo dígitos y recorta a 18 (cabe en unsigned 64bit)
    $job_id_digits = preg_replace('/\D+/', '', (string)$job_id);
    if ($job_id_digits === '') {
        $job_id_digits = '0';
    }
    if (strlen($job_id_digits) > 18) {
        $job_id_digits = substr($job_id_digits, 0, 18);
    }
    $job_id_sql = (string)intval($job_id_digits);

    $state_sql       = is_null($state)       ? "NULL" : "'" . mysqli_real_escape_string($con, (string)$state) . "'";
    $ruc_sql         = is_null($ruc_empresa) ? "NULL" : "'" . mysqli_real_escape_string($con, (string)$ruc_empresa) . "'";
    $source_ip_sql   = is_null($source_ip)   ? "NULL" : "'" . mysqli_real_escape_string($con, (string)$source_ip) . "'";

    $ins = 0;
    $upd = 0;
    $err = array();

    if (!is_array($items)) {
        $items = array();
    }

    foreach ($items as $idx => $it) {
        // Campos del item
        $clave        = isset($it['clave_acceso']) ? $it['clave_acceso'] : null;
        if ($clave === null || $clave === '') {
            $err[] = "Fila $idx: falta clave_acceso";
            continue;
        }

        $costo        = isset($it['costo']) ? (float)$it['costo'] : null;
        $ip_evento    = isset($it['ip']) ? $it['ip'] : null;
        $tipo_evento  = isset($it['tipo_evento']) ? $it['tipo_evento'] : null;
        $url_xml      = isset($it['url_xml']) ? $it['url_xml'] : null;
        $url_ride     = isset($it['url_ride']) ? $it['url_ride'] : null;

        // Escapes
        $clave_sql       = "'" . mysqli_real_escape_string($con, (string)$clave) . "'";
        $url_xml_sql     = is_null($url_xml)     ? "NULL" : "'" . mysqli_real_escape_string($con, (string)$url_xml) . "'";
        $url_ride_sql    = is_null($url_ride)    ? "NULL" : "'" . mysqli_real_escape_string($con, (string)$url_ride) . "'";
        $tipo_evento_sql = is_null($tipo_evento) ? "NULL" : "'" . mysqli_real_escape_string($con, (string)$tipo_evento) . "'";
        $costo_sql       = is_null($costo)       ? "NULL" : sprintf('%.6F', (float)$costo);
        $ip_evento_sql   = is_null($ip_evento)   ? "NULL" : "'" . mysqli_real_escape_string($con, (string)$ip_evento) . "'";

        // raw_json: si nos pasan un payload global, lo usamos; si no, se guarda el JSON del item
        $raw_to_store = is_null($raw_payload) ? $it : $raw_payload;
        $raw_json_str = json_encode($raw_to_store, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($raw_json_str === false) {
            $raw_json_str = '{}';
        }
        $raw_json_sql = "'" . mysqli_real_escape_string($con, $raw_json_str) . "'";

        // INSERT ... ON DUPLICATE KEY UPDATE (clave única uq_job_clave: job_id, clave_acceso)
        $sql = "INSERT INTO sri_webhook_queue
            (job_id, state, ruc_empresa, clave_acceso, url_xml, url_ride, tipo_evento, costo, ip_evento, source_ip, raw_json)
            VALUES (
                $job_id_sql, $state_sql, $ruc_sql, $clave_sql, $url_xml_sql, $url_ride_sql, $tipo_evento_sql, $costo_sql, $ip_evento_sql, $source_ip_sql, $raw_json_sql
            )
            ON DUPLICATE KEY UPDATE
                state      = VALUES(state),
                ruc_empresa= VALUES(ruc_empresa),
                url_xml    = VALUES(url_xml),
                url_ride   = VALUES(url_ride),
                tipo_evento= VALUES(tipo_evento),
                costo      = VALUES(costo),
                ip_evento  = VALUES(ip_evento),
                source_ip  = VALUES(source_ip),
                raw_json   = VALUES(raw_json)";

        $ok = mysqli_query($con, $sql);
        if (!$ok) {
            $err[] = "Fila $idx: (" . mysqli_errno($con) . ") " . mysqli_error($con);
            // Descomenta si quieres ver el SQL
            // echo "<pre style='color:red'>".$sql."</pre>";
        } else {
            // Saber si fue insert o update:
            // affected_rows = 1 (insert), 2 (update por duplicate)
            $aff = mysqli_affected_rows($con);
            if ($aff === 1) $ins++;
            else if ($aff === 2) $upd++; // MariaDB/MySQL suele devolver 2 en UPDATE de duplicate
        }
    }

    return array(
        "insertados" => $ins,
        "actualizados" => $upd,
        "errores" => $err
    );
}


/**
 * Devuelve desde sri_webhook_queue SOLO las claves NO registradas para un job_id dado,
 * en formato: [ ['clave_acceso' => '...', 'url_xml' => '...'], ... ]
 *
 * Compat PHP 5.6 (sin prepared statements).
 *
 * @param mysqli     $con
 * @param mixed      $job_id                jobId del proveedor (se normaliza a BIGINT para la tabla)
 * @param string|int $tipo_comprobante      1,2,3,4,6 según tu mapeo
 * @param string     $ruc_empresa
 * @param int        $limite                máx filas del queue a revisar
 * @param bool       $solo_estado_completado si true, filtra UPPER(state) a COMPLETADO/COMPLETED/...
 * @return array
 */
function obtener_claves_queue_no_registradas_por_job($con, $job_id, $tipo_comprobante, $ruc_empresa, $limite = 500, $solo_estado_completado = true)
{
    $salida = array();

    // Mapa tipo → tabla destino
    //1 facturas de compras, 2 liquidaciones de compras, 3 nd, 4 nd, 6 retenciones de ventas 
    $tabla_por_tipo = array(
        "1" => "encabezado_compra",
        "2" => "encabezado_liquidacion",
        "3" => "encabezado_compra",
        "4" => "encabezado_compra",
        "6" => "encabezado_retencion_venta",
    );
    $td = (string)$tipo_comprobante;
    if (!isset($tabla_por_tipo[$td])) {
        return $salida;
    }
    $tabla_destino = $tabla_por_tipo[$td];

    if (function_exists('mysqli_set_charset')) {
        @mysqli_set_charset($con, 'utf8mb4');
    }

    // Normaliza job_id a BIGINT (la tabla usa BIGINT UNSIGNED)
    $job_id_digits = preg_replace('/\D+/', '', (string)$job_id);
    if ($job_id_digits === '') {
        $job_id_digits = '0';
    }
    if (strlen($job_id_digits) > 18) {
        $job_id_digits = substr($job_id_digits, 0, 18);
    }
    $job_id_sql = (string)intval($job_id_digits); // seguro para usar sin comillas

    $ruc_sql = "'" . mysqli_real_escape_string($con, (string)$ruc_empresa) . "'";
    $limite  = max(1, (int)$limite);

    // WHERE base: por RUC + job_id + no vacíos + no procesados
    $where = "ruc_empresa = $ruc_sql
              AND job_id = $job_id_sql
              AND clave_acceso IS NOT NULL AND clave_acceso <> ''
              AND url_xml IS NOT NULL AND url_xml <> ''
              AND (processed = 0 OR processed IS NULL)";
    if ($solo_estado_completado) {
        $where .= " AND UPPER(state) IN ('COMPLETADO','COMPLETED','FINALIZADO','TERMINADO')";
    }

    // Tomar candidatos del queue (dedupe por clave_acceso)
    $sql_q = "SELECT clave_acceso, url_xml
              FROM sri_webhook_queue
              WHERE $where
              GROUP BY clave_acceso
              ORDER BY created_at DESC
              LIMIT $limite";

    $q = mysqli_query($con, $sql_q);
    if (!$q) {
        error_log("obtener_claves_queue_no_registradas_por_job: SQL queue error: " . mysqli_error($con) . " | $sql_q");
        return $salida;
    }

    $candidatas = array(); // mapa clave => url_xml
    while ($row = mysqli_fetch_assoc($q)) {
        $clave = isset($row['clave_acceso']) ? (string)$row['clave_acceso'] : '';
        $url   = isset($row['url_xml']) ? (string)$row['url_xml'] : '';
        if ($clave !== '' && $url !== '') {
            $candidatas[$clave] = $url;
        }
    }
    mysqli_free_result($q);

    if (empty($candidatas)) {
        return $salida; // nada para procesar
    }

    // Excluir claves definidas como "no descargar" (si existe tu función)
    $excluir = array();
    if (function_exists('obtener_claves_no_descargar')) {
        $ex_raw = obtener_claves_no_descargar($con, $ruc_empresa);
        if (is_array($ex_raw)) {
            foreach ($ex_raw as $k => $v) {
                if (is_string($k)) $excluir[$k] = true;
                else if (is_string($v)) $excluir[$v] = true;
            }
        }
    }
    foreach ($candidatas as $clave => $_) {
        if (isset($excluir[$clave])) {
            unset($candidatas[$clave]);
        }
    }
    if (empty($candidatas)) {
        return $salida;
    }

    // Verificar cuáles YA están registradas en la tabla destino (aut_sri)
    $claves = array_keys($candidatas);
    $ya_reg = array();

    $chunkSize = 500;
    for ($i = 0; $i < count($claves); $i += $chunkSize) {
        $sub = array_slice($claves, $i, $chunkSize);
        $in_list = array();
        for ($j = 0; $j < count($sub); $j++) {
            $in_list[] = "'" . mysqli_real_escape_string($con, $sub[$j]) . "'";
        }
        if (empty($in_list)) continue;

        $sql_exist = "SELECT aut_sri FROM $tabla_destino WHERE aut_sri IN (" . implode(",", $in_list) . ")";
        $qe = mysqli_query($con, $sql_exist);
        if (!$qe) {
            error_log("obtener_claves_queue_no_registradas_por_job: SQL exist error: " . mysqli_error($con) . " | $sql_exist");
            continue; // prudencia: no añadimos/quitar por error de consulta
        }
        while ($r = mysqli_fetch_row($qe)) {
            if (isset($r[0])) {
                $ya_reg[(string)$r[0]] = true;
            }
        }
        mysqli_free_result($qe);
    }

    // Salida final solo con NO registradas
    foreach ($candidatas as $clave => $url_xml) {
        if (!isset($ya_reg[$clave])) {
            $salida[] = array(
                'clave_acceso' => $clave,
                'url_xml'      => $url_xml
            );
        }
    }

    return $salida;
}


/**
 * Marca como processed=1 en sri_webhook_queue para un job_id y un set de claves.
 * - Compat PHP 5.6 (sin prepared).
 * - Normaliza job_id a BIGINT (sólo dígitos, recorte a 18).
 * - Acepta $pendientes como lista de arrays ['clave_acceso'=>..., 'url_xml'=>...] o lista de strings.
 * - Opcional: filtra también por ruc_empresa.
 * - Opcional: setear error_msg (si se pasa null => se limpia a NULL).
 *
 * @param mysqli     $con
 * @param mixed      $job_id
 * @param array      $pendientes  (ej: [['clave_acceso'=>'...','url_xml'=>'...'], ...] o ['clave1','clave2',...])
 * @param string|nil $ruc_empresa (opcional)
 * @param string|nil $error_msg   (opcional) si null => error_msg = NULL; si string => se setea
 * @return array { ok, actualizadas, batches, errores[] }
 */
function marca_claves_queue_como_procesadas($con, $job_id, $pendientes, $ruc_empresa = null, $error_msg = null)
{
    if (function_exists('mysqli_set_charset')) {
        @mysqli_set_charset($con, 'utf8mb4');
    }

    // 1) Normaliza job_id a BIGINT (tabla usa BIGINT UNSIGNED)
    $job_id_digits = preg_replace('/\D+/', '', (string)$job_id);
    if ($job_id_digits === '') {
        $job_id_digits = '0';
    }
    if (strlen($job_id_digits) > 18) {
        $job_id_digits = substr($job_id_digits, 0, 18);
    }
    $job_id_sql = (string)intval($job_id_digits);

    // 2) Extrae claves de $pendientes (acepta array de arrays o de strings)
    $claves_map = array(); // set para deduplicar
    if (is_array($pendientes)) {
        for ($i = 0; $i < count($pendientes); $i++) {
            $it = $pendientes[$i];
            if (is_array($it) && isset($it['clave_acceso']) && $it['clave_acceso'] !== '') {
                $claves_map[(string)$it['clave_acceso']] = true;
            } else if (is_string($it) && $it !== '') {
                $claves_map[$it] = true;
            }
        }
    }
    if (empty($claves_map)) {
        return array("ok" => true, "actualizadas" => 0, "batches" => 0, "errores" => array());
    }
    $claves = array_keys($claves_map);

    // 3) WHERE base
    $where = "job_id = $job_id_sql";
    if (!is_null($ruc_empresa) && $ruc_empresa !== '') {
        $where .= " AND ruc_empresa = '" . mysqli_real_escape_string($con, (string)$ruc_empresa) . "'";
    }

    // 4) SET de actualización
    if ($error_msg === null) {
        $set_err = "error_msg = NULL";
    } else {
        $set_err = "error_msg = '" . mysqli_real_escape_string($con, (string)$error_msg) . "'";
    }
    $set_clause = "processed = 1, processed_at = NOW(), $set_err";

    // 5) Ejecutar en lotes (IN grande puede ser largo)
    $errores = array();
    $actualizadas_total = 0;
    $batches = 0;
    $chunkSize = 300; // ajusta si necesitas

    for ($i = 0; $i < count($claves); $i += $chunkSize) {
        $sub = array_slice($claves, $i, $chunkSize);
        $in_list = array();
        for ($j = 0; $j < count($sub); $j++) {
            $in_list[] = "'" . mysqli_real_escape_string($con, $sub[$j]) . "'";
        }
        if (empty($in_list)) continue;

        $sql = "UPDATE sri_webhook_queue
                SET $set_clause
                WHERE $where
                AND clave_acceso IN (" . implode(",", $in_list) . ")";

        $ok = mysqli_query($con, $sql);
        $batches++;

        if (!$ok) {
            $errores[] = "Batch $batches: (" . mysqli_errno($con) . ") " . mysqli_error($con);
            // Si quieres ver el SQL exacto en depuración:
            // echo "<pre style='color:red'>$sql</pre>";
        } else {
            // OJO: si ya estaban processed=1, affected_rows podría ser 0 y no es error.
            $actualizadas_total += mysqli_affected_rows($con);
        }
    }

    return array(
        "ok" => empty($errores),
        "actualizadas" => $actualizadas_total,
        "batches" => $batches,
        "errores" => $errores
    );
}
