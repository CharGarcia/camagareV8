-- =============================================================================
-- 20260917_diagnostico_tiempos_sri_hoy.sql — SOLO LECTURA
-- -----------------------------------------------------------------------------
-- Qué hace  : mide cuánto tardó el SRI en responder cada envío de comprobantes de
--             hoy desde las 07:00 (hora de Ecuador), con los registros de
--             sri_envio_log: 'enviando' → 'recibida'/'devuelta' → resultado final.
--             Vista 1: envíos por cada 15 min con segundos promedio y máximo, y
--                      cuántos se quedaron SIN RESPUESTA (colgados o cortados).
--             Vista 2: los envíos lentos (10 s o más) o sin respuesta, uno por uno.
-- Toca datos: NO.
-- Cómo      : pgAdmin → F5 → copiar el resultado.
-- =============================================================================WITH envios AS (
    SELECT en.created_at, en.id_empresa, en.tipo_comprobante, en.id_comprobante,
           resp.accion AS recepcion,
           fin.accion  AS resultado,
           extract(epoch FROM resp.created_at - en.created_at) AS seg_recepcion,
           extract(epoch FROM fin.created_at  - en.created_at) AS seg_total
    FROM sri_envio_log en
    LEFT JOIN LATERAL (
        SELECT f.accion, f.created_at FROM sri_envio_log f
        WHERE f.tipo_comprobante = en.tipo_comprobante AND f.id_comprobante = en.id_comprobante
          AND f.id > en.id AND f.created_at < en.created_at + interval '30 minutes'
        ORDER BY f.id LIMIT 1
    ) resp ON true
    LEFT JOIN LATERAL (
        SELECT f.accion, f.created_at FROM sri_envio_log f
        WHERE f.tipo_comprobante = en.tipo_comprobante AND f.id_comprobante = en.id_comprobante
          AND f.id > en.id AND f.created_at < en.created_at + interval '30 minutes'
          AND f.accion NOT IN ('enviando', 'recibida')
        ORDER BY f.id LIMIT 1
    ) fin ON true
    WHERE en.accion = 'enviando'
      AND en.created_at >= date_trunc('day', now() AT TIME ZONE 'America/Guayaquil') + interval '7 hours'
)
(SELECT '1 cada 15 min' AS vista,
        to_char(date_trunc('hour', created_at) + interval '15 min' * floor(extract(minute FROM created_at) / 15), 'HH24:MI') AS hora_ecuador,
        count(*) AS envios,
        round(avg(seg_total)::numeric, 1) AS seg_promedio,
        round(max(seg_total)::numeric, 1) AS seg_maximo,
        count(*) FILTER (WHERE recepcion IS NULL) AS sin_respuesta,
        NULL::text AS detalle
 FROM envios
 GROUP BY 2
 ORDER BY 2)
UNION ALL
(SELECT '2 lentos o sin respuesta', to_char(created_at, 'HH24:MI:SS'), 1, round(seg_total::numeric, 1), round(seg_recepcion::numeric, 1),
        (recepcion IS NULL)::int,
        'empresa ' || id_empresa || ' Â· ' || tipo_comprobante || ' ' || id_comprobante || ' Â· '
            || coalesce(recepcion, 'SIN RESPUESTA') || coalesce(' â†’ ' || resultado, '')
 FROM envios
 WHERE seg_total >= 10 OR seg_recepcion >= 10 OR recepcion IS NULL
 ORDER BY created_at
 LIMIT 40);

