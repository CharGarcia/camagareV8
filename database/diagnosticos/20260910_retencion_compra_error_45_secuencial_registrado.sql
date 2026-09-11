-- ============================================================================
-- Retención de compra que el SRI devuelve con "ERROR 45 - SECUENCIAL REGISTRADO"
-- aunque en el portal "no aparece" ese secuencial.
--
-- El error 45 lo da el WS de RECEPCIÓN cuando el SRI ya tiene un comprobante
-- AUTORIZADO (o en cola) con el mismo RUC + tipo (07) + establecimiento + punto
-- + secuencial, pero con OTRA clave de acceso. Las dos causas típicas:
--
--   A) La retención ya se envió antes con otra clave. Al editar un borrador
--      (p. ej. cambiar la fecha de emisión a "hoy" para poder reenviar), el
--      sistema regenera la clave de acceso con la nueva fecha y conserva el
--      secuencial. La verificación previa al envío consulta la clave NUEVA,
--      no la encuentra, envía, y el SRI responde 45 porque la clave VIEJA ya
--      está autorizada. En el portal hay que buscarla por la FECHA ORIGINAL
--      (la que está en los 8 primeros dígitos de la clave vieja, ddmmaaaa).
--
--   B) Ambiente distinto. tipo_ambiente del SRI: 1 = PRUEBAS, 2 = PRODUCCIÓN.
--      Si la clave/envío llevan 1 y se revisa el portal (que solo muestra
--      producción), el comprobante no aparece aunque el SRI de pruebas lo tenga.
--
--   C) El número ya lo usó OTRO registro que el cálculo del secuencial no ve:
--      una retención del mismo número con otro id_punto_emision, con
--      tipo_ambiente distinto (migrada, o emitida cuando la empresa estaba en
--      pruebas) o eliminada. El cálculo del siguiente secuencial solo mira los
--      documentos vivos del mismo punto y del ambiente actual de la empresa.
--      También puede venir del sistema anterior (migración): el SRI tiene el
--      comprobante, pero aquí no existe ninguna fila con ese número.
--
-- SOLO LECTURA: no modifica ni crea nada. NO hay que poner parámetros: las
-- consultas 1-3 buscan solas el error 45 en el log de envíos; la 4 y la 5
-- necesitan la empresa y el número (ver sus CTE `parametros`).
-- USO: resalte UNA consulta y presione F5.
--        1) Envíos devueltos con error 45 en los últimos 60 días (cualquier
--           tipo de comprobante), con el documento al que pertenecen.
--        2) Historial COMPLETO de envíos de esas retenciones: todas las claves
--           que han tenido. Si hay más de una clave distinta → causa A.
--        3) Otras retenciones (vivas o eliminadas) con el mismo número.
--        4) TODO lo que hay en el sistema con un número dado, sin filtrar por
--           punto, ambiente ni eliminado, y el envío al SRI de cada fila.
--        5) Cómo está numerada la serie: último número por punto/ambiente/
--           eliminado y el secuencial inicial configurado.
-- ============================================================================

-- 1) Envíos con error 45 en los últimos 60 días
SELECT l.id            AS id_log,
       l.created_at    AS fecha_envio,
       l.id_empresa,
       l.tipo_comprobante,
       l.id_comprobante,
       l.tipo_ambiente AS ambiente_envio,
       l.clave_acceso  AS clave_enviada,
       substr(l.clave_acceso, 1, 8)  AS fecha_en_clave_ddmmaaaa,
       r.establecimiento || '-' || r.punto_emision || '-' || r.secuencial AS numero_retencion,
       r.fecha_emision AS fecha_retencion_hoy,
       r.estado        AS estado_retencion,
       r.tipo_ambiente AS ambiente_retencion,
       e.tipo_ambiente AS ambiente_empresa_hoy,
       (r.clave_acceso = l.clave_acceso) AS clave_sigue_igual,
       left(l.detalle_json, 300) AS detalle_sri
FROM sri_envio_log l
LEFT JOIN retencion_compra_cabecera r
       ON l.tipo_comprobante = 'retencion_compra' AND r.id = l.id_comprobante
LEFT JOIN empresas e ON e.id = l.id_empresa
WHERE l.created_at >= CURRENT_DATE - INTERVAL '60 days'
  AND (l.detalle_json ILIKE '%SECUENCIAL REGISTRADO%'
       OR l.detalle_json ILIKE '%CLAVE ACCESO REGISTRADA%')
ORDER BY l.id DESC;


-- 2) Historial completo de envíos de las retenciones que recibieron el 45.
--    Si aparece una clave distinta a la actual con accion 'recibida',
--    'en_procesamiento' o 'autorizado', esa es la que el SRI tiene (causa A).
WITH afectadas AS (
    SELECT DISTINCT id_comprobante
    FROM sri_envio_log
    WHERE tipo_comprobante = 'retencion_compra'
      AND created_at >= CURRENT_DATE - INTERVAL '60 days'
      AND (detalle_json ILIKE '%SECUENCIAL REGISTRADO%'
           OR detalle_json ILIKE '%CLAVE ACCESO REGISTRADA%')
)
SELECT l.id_comprobante AS id_retencion,
       r.establecimiento || '-' || r.punto_emision || '-' || r.secuencial AS numero,
       l.id AS id_log, l.created_at, l.accion, l.estado_sri, l.tipo_ambiente,
       l.clave_acceso,
       substr(l.clave_acceso, 1, 8) AS fecha_en_clave_ddmmaaaa,
       (l.clave_acceso = r.clave_acceso) AS es_la_clave_actual,
       l.numero_autorizacion, l.fecha_autorizacion,
       left(l.mensaje, 120)      AS mensaje,
       left(l.detalle_json, 300) AS detalle
FROM afectadas a
JOIN sri_envio_log l
  ON l.tipo_comprobante = 'retencion_compra' AND l.id_comprobante = a.id_comprobante
LEFT JOIN retencion_compra_cabecera r ON r.id = a.id_comprobante
ORDER BY l.id_comprobante, l.id;


-- 3) Otras retenciones de la misma empresa con el mismo número (vivas o
--    eliminadas). Lo normal es una sola fila por retención afectada.
WITH afectadas AS (
    SELECT DISTINCT id_comprobante
    FROM sri_envio_log
    WHERE tipo_comprobante = 'retencion_compra'
      AND created_at >= CURRENT_DATE - INTERVAL '60 days'
      AND (detalle_json ILIKE '%SECUENCIAL REGISTRADO%'
           OR detalle_json ILIKE '%CLAVE ACCESO REGISTRADA%')
)
SELECT x.id AS id_retencion,
       x.establecimiento || '-' || x.punto_emision || '-' || x.secuencial AS numero,
       x.fecha_emision, x.estado, x.eliminado, x.tipo_ambiente,
       x.clave_acceso, x.numero_autorizacion, x.created_at,
       (x.id = a.id_comprobante) AS es_la_afectada
FROM afectadas a
JOIN retencion_compra_cabecera r ON r.id = a.id_comprobante
JOIN retencion_compra_cabecera x
  ON x.id_empresa      = r.id_empresa
 AND x.establecimiento = r.establecimiento
 AND x.punto_emision   = r.punto_emision
 AND x.secuencial      = r.secuencial
ORDER BY r.id, x.id;


-- 4) Todo lo que existe en el sistema con ese número, sin ningún filtro
--    (punto, ambiente, eliminado), y qué dice el log del SRI de cada fila.
--    Si aparece una fila con tipo_ambiente distinto de 2, con otro
--    id_punto_emision o eliminada, esa es la que ocupó el número en el SRI y
--    el cálculo del secuencial no la veía (causa C). Si no aparece NADA más
--    que la retención afectada, el número lo emitió otro sistema (el anterior).
WITH parametros AS (
    SELECT 106 AS id_empresa, '002' AS establecimiento, '101' AS punto_emision, 1450 AS secuencial
)
SELECT r.id, r.id_punto_emision, r.tipo_ambiente, r.eliminado, r.estado,
       r.establecimiento || '-' || r.punto_emision || '-' || r.secuencial AS numero,
       r.fecha_emision, r.clave_acceso, r.numero_autorizacion, r.fecha_autorizacion,
       r.created_at, r.deleted_at,
       (SELECT string_agg(l.accion || '@' || l.tipo_ambiente, ' > ' ORDER BY l.id)
          FROM sri_envio_log l
         WHERE l.tipo_comprobante = 'retencion_compra' AND l.id_comprobante = r.id) AS envios_sri
FROM retencion_compra_cabecera r
JOIN parametros p ON p.id_empresa = r.id_empresa
WHERE r.establecimiento = p.establecimiento
  AND r.punto_emision   = p.punto_emision
  AND TRIM(r.secuencial) ~ '^[0-9]+$'
  AND CAST(TRIM(r.secuencial) AS BIGINT) BETWEEN p.secuencial - 5 AND p.secuencial + 5
ORDER BY CAST(TRIM(r.secuencial) AS BIGINT), r.id;


-- 5) Cómo está numerada la serie 002-101 de retenciones de esa empresa:
--    último número y cantidad por punto / ambiente / eliminado, y el
--    secuencial inicial configurado. Si el máximo de alguna combinación que
--    NO es (ambiente 2, eliminado false) supera al número afectado, el SRI ya
--    tiene números por encima y hay que subir el secuencial inicial.
WITH parametros AS (
    SELECT 106 AS id_empresa, '002' AS establecimiento, '101' AS punto_emision
)
SELECT r.id_punto_emision, r.tipo_ambiente, r.eliminado,
       COUNT(*)                                        AS cantidad,
       MAX(CAST(TRIM(r.secuencial) AS BIGINT))         AS ultimo_numero,
       MAX(r.fecha_emision)                            AS ultima_fecha,
       SUM(CASE WHEN r.numero_autorizacion IS NOT NULL AND r.numero_autorizacion <> '' THEN 1 ELSE 0 END) AS con_autorizacion,
       (SELECT string_agg(es.tipo_documento || ': inicial ' || COALESCE(es.secuencial_inicial, 1), ' | ')
          FROM empresa_secuencial es
         WHERE es.id_punto_emision = r.id_punto_emision AND es.eliminado = false
           AND es.tipo_documento ILIKE '%retenc%')       AS config_serie
FROM retencion_compra_cabecera r
JOIN parametros p ON p.id_empresa = r.id_empresa
WHERE r.establecimiento = p.establecimiento
  AND r.punto_emision   = p.punto_emision
  AND TRIM(r.secuencial) ~ '^[0-9]+$'
GROUP BY r.id_punto_emision, r.tipo_ambiente, r.eliminado
ORDER BY ultimo_numero DESC;
