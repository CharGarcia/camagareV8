-- ============================================================================
-- Diagnóstico: ¿la limpieza de duplicados del 2026-07-07 eliminó por error
-- comprobantes FÍSICOS que en realidad eran válidos?
-- ----------------------------------------------------------------------------
-- Contexto:
--   El PASO 2 de database/migrations/20260707_fix_duplicados_sri.sql marcó
--   eliminado=true a toda fila de compras_cabecera cuyo numero_autorizacion se
--   repitiera dentro de la misma empresa, dejando solo la de menor id. Eso es
--   correcto para CLAVE DE ACCESO electrónica (49 dígitos, única por
--   documento), pero INCORRECTO para autorización FÍSICA (10 dígitos): el SRI
--   entrega una sola autorización física para todo un talonario, así que
--   varias facturas legítimas del mismo proveedor (distinto secuencial_prov)
--   comparten a propósito el mismo numero_autorizacion. Ese script las trató
--   como duplicadas y las eliminó lógicamente sin serlo.
--   (Ver database/compras_numaut_unico_solo_electronicas.sql, que corrige el
--   índice para que la unicidad aplique solo a electrónicas.)
--
-- Cómo se detectan sin ambigüedad:
--   - eliminado = true Y deleted_by IS NULL   → el borrado normal desde la UI
--     (ComprasRepository::eliminarLogico) SIEMPRE graba deleted_by; el script
--     de limpieza del 07-07 nunca lo hizo. Es la huella de ese script.
--   - Existe una fila VIVA con el mismo (id_empresa, numero_autorizacion)
--     pero secuencial_prov DISTINTO → es un documento diferente (factura
--     distinta del mismo talonario), no un duplicado real del mismo documento.
--   - numero_autorizacion NO tiene 49 dígitos → no es electrónica, es física
--     (o legado), donde la repetición es esperada.
--
-- Este script es SOLO LECTURA. No modifica nada.
-- ============================================================================

SELECT
    d.id                    AS id_eliminado,
    d.id_empresa,
    d.id_proveedor,
    d.tipo_comprobante,
    d.numero_autorizacion,
    d.establecimiento_prov, d.punto_emision_prov, d.secuencial_prov,
    d.importe_total,
    d.fecha_emision,
    d.deleted_at,
    v.id                    AS id_vivo_con_misma_autorizacion,
    v.secuencial_prov       AS secuencial_vivo
FROM compras_cabecera d
JOIN compras_cabecera v
     ON v.id_empresa = d.id_empresa
    AND v.numero_autorizacion = d.numero_autorizacion
    AND v.eliminado = false
    AND v.id <> d.id
WHERE d.eliminado = true
  AND d.deleted_by IS NULL
  AND d.numero_autorizacion IS NOT NULL AND d.numero_autorizacion <> ''
  AND length(regexp_replace(d.numero_autorizacion, '[^0-9]', '', 'g')) <> 49
  AND (d.establecimiento_prov, d.punto_emision_prov, d.secuencial_prov)
      IS DISTINCT FROM (v.establecimiento_prov, v.punto_emision_prov, v.secuencial_prov)
ORDER BY d.id_empresa, d.numero_autorizacion, d.id;

-- ----------------------------------------------------------------------------
-- Solo el conteo, si la lista de arriba es muy larga:
-- ----------------------------------------------------------------------------
-- SELECT COUNT(*) FROM ( ... la misma consulta ... ) x;

-- ----------------------------------------------------------------------------
-- Aparte, vale la pena revisar si alguna de esas facturas eliminadas por
-- error tenía un egreso o una retención asociada que también quedó huérfano:
-- ----------------------------------------------------------------------------
-- SELECT ec.id, ec.total, ec.eliminado
-- FROM egresos_cabecera ec
-- JOIN egresos_detalle ed ON ed.id_egreso = ec.id
-- WHERE ed.tipo_documento = 'COMPRA' AND ed.id_referencia IN (/* ids de arriba */);
