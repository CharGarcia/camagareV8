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
--   B) Ambiente distinto. La clave/envío llevan tipo_ambiente 2 (pruebas) y
--      se está revisando el portal de producción (o al revés).
--
-- SOLO LECTURA: no modifica ni crea nada. NO hay que poner parámetros: las
-- consultas buscan solas el error 45 en el log de envíos.
-- USO: resalte UNA consulta y presione F5.
--        1) Envíos devueltos con error 45 en los últimos 60 días (cualquier
--           tipo de comprobante), con el documento al que pertenecen.
--        2) Historial COMPLETO de envíos de esas retenciones: todas las claves
--           que han tenido. Si hay más de una clave distinta → causa A.
--        3) Otras retenciones (vivas o eliminadas) con el mismo número.
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
