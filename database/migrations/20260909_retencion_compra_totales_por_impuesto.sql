-- ============================================================================
-- Retenciones en compras — desglose del total retenido por impuesto
--
-- La cabecera solo guardaba total_retenido: el reparto entre renta, IVA e ISD
-- vivía únicamente en el detalle y cada consulta que lo necesitaba tenía que
-- recalcularlo (ReporteRetencionesController lo hace con sumarPorImpuesto()).
-- La migración original del módulo declaraba total_retenido_renta y
-- total_retenido_iva, pero el INSERT nunca las escribió y en las bases reales
-- esas columnas ni siquiera llegaron a crearse.
--
-- Se crean las tres y se rellenan desde el detalle. El código las escribe solo
-- si existen, así que este script se puede ejecutar cuando convenga: sin él el
-- módulo sigue funcionando igual que hasta ahora.
--
-- Idempotente: se puede ejecutar varias veces.
-- ============================================================================

BEGIN;

ALTER TABLE retencion_compra_cabecera
    ADD COLUMN IF NOT EXISTS total_retenido_renta NUMERIC(14,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS total_retenido_iva   NUMERIC(14,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS total_retenido_isd   NUMERIC(14,2) NOT NULL DEFAULT 0;

COMMENT ON COLUMN retencion_compra_cabecera.total_retenido_renta
    IS 'Total retenido de renta; se calcula desde retencion_compra_detalle (codigo_impuesto 1 / RENTA).';
COMMENT ON COLUMN retencion_compra_cabecera.total_retenido_iva
    IS 'Total retenido de IVA; se calcula desde retencion_compra_detalle (codigo_impuesto 2 / IVA).';
COMMENT ON COLUMN retencion_compra_cabecera.total_retenido_isd
    IS 'Total retenido de ISD; se calcula desde retencion_compra_detalle (codigo_impuesto 6 / ISD).';

-- Relleno de las retenciones ya registradas. El código del impuesto convive en
-- formato numérico del SRI ('1','2','6') y literal ('RENTA','IVA','ISD') según
-- si el documento se capturó a mano o se importó: se aceptan ambos. El ISD llega
-- además como '3' en importaciones antiguas.
UPDATE retencion_compra_cabecera c
SET total_retenido_renta = t.renta,
    total_retenido_iva   = t.iva,
    total_retenido_isd   = t.isd
FROM (
    SELECT d.id_retencion,
           ROUND(COALESCE(SUM(d.valor_retenido) FILTER (
               WHERE UPPER(COALESCE(d.codigo_impuesto, '')) IN ('1', 'RENTA')), 0), 2)      AS renta,
           ROUND(COALESCE(SUM(d.valor_retenido) FILTER (
               WHERE UPPER(COALESCE(d.codigo_impuesto, '')) IN ('2', 'IVA')), 0), 2)        AS iva,
           ROUND(COALESCE(SUM(d.valor_retenido) FILTER (
               WHERE UPPER(COALESCE(d.codigo_impuesto, '')) IN ('3', '6', 'ISD')), 0), 2)   AS isd
    FROM retencion_compra_detalle d
    GROUP BY d.id_retencion
) t
WHERE t.id_retencion = c.id
  AND (c.total_retenido_renta IS DISTINCT FROM t.renta
    OR c.total_retenido_iva   IS DISTINCT FROM t.iva
    OR c.total_retenido_isd   IS DISTINCT FROM t.isd);

COMMIT;

-- ---------------------------------------------------------------------------
-- Comprobación posterior (opcional): retenciones cuyo desglose no suma el total
-- de la cabecera. Lo esperado es 0 filas.
--
-- Si devuelve alguna, no es un fallo de este script: el desglose se calcula desde
-- `valor_retenido` del detalle, así que salen las retenciones antiguas cuyo
-- detalle tiene base y porcentaje pero el valor sin calcular (0). En la base de
-- desarrollo hay un caso así, ya eliminado. Revíselas una a una antes de tocar
-- nada: el total de la cabecera puede ser el correcto y lo que falta es el
-- detalle, o al revés.
--
-- SELECT c.id,
--        c.establecimiento || '-' || c.punto_emision || '-' || c.secuencial AS numero,
--        c.fecha_emision, c.estado,
--        c.total_retenido, c.total_retenido_renta, c.total_retenido_iva, c.total_retenido_isd
-- FROM retencion_compra_cabecera c
-- WHERE c.eliminado = false
--   AND ROUND(c.total_retenido_renta + c.total_retenido_iva + c.total_retenido_isd, 2)
--       IS DISTINCT FROM ROUND(c.total_retenido, 2)
-- ORDER BY c.fecha_emision;
