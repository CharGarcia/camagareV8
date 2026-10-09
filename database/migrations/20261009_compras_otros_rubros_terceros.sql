-- ============================================================================
-- Compras: <otrosRubrosTerceros> del XML de factura del SRI (2026-10-09)
-- ============================================================================
-- Caso real: FIDEICOMISO LANDUNI 001-002-000143619 (hotel). El XML trae
--   <otrosRubrosTerceros><rubro><concepto>TASA DE PERNOCTACION</concepto>
--   <total>2.5000</total></rubro></otrosRubrosTerceros>
-- y el importe total (174.77) = subtotal 138.06 + IVA 20.71 + propina 13.50
-- + rubros de terceros 2.50. Es un nodo del esquema oficial de factura del SRI
-- (versión 2.1.0) y, a diferencia de los "valores de terceros" de las planillas
-- de luz/agua (compras_cabecera.total_terceros, que viven en <infoAdicional> y
-- quedan FUERA del importe total), este rubro está DENTRO del importe total.
--
-- Sin esta columna el sistema ignoraba el nodo: la columna IVA del listado
-- salía inflada (total − subtotal − propina), el asiento no cuadraba (Por Pagar
-- 174.77 contra Debe 172.27) y, si se editaba el detalle, el recálculo de
-- totales perdía los 2.50 del importe total.
--
-- Contabilidad: el rubro va al MISMO gasto de la compra (decisión del usuario,
-- 2026-10-09); no necesita cuenta nueva. Ver AsientoBuilderService::armarDistribucionCompras().
--
-- IDEMPOTENTE. El constructor de ComprasRepository crea la columna y la tabla
-- como red de seguridad, pero el comentario y el backfill solo vienen aquí.
-- ============================================================================

-- ── 1. Total de otros rubros de terceros (dentro del importe total) ──────────
ALTER TABLE compras_cabecera
    ADD COLUMN IF NOT EXISTS otros_rubros_terceros NUMERIC(12,2) NOT NULL DEFAULT 0;

COMMENT ON COLUMN compras_cabecera.otros_rubros_terceros IS
    'Suma de <otrosRubrosTerceros> del XML de factura del SRI (tasa de pernoctación, etc.). '
    'Está DENTRO de importe_total: importe_total = total_sin_impuestos + IVA + ICE + propina + otros_rubros_terceros. '
    'No confundir con total_terceros (planillas de luz/agua), que queda FUERA de importe_total.';

-- ── 2. Desglose por rubro (concepto + valor), tal como viene en el XML ───────
CREATE TABLE IF NOT EXISTS compras_otros_rubros (
    id        SERIAL PRIMARY KEY,
    id_compra INTEGER NOT NULL REFERENCES compras_cabecera(id) ON DELETE CASCADE,
    concepto  VARCHAR(300) NOT NULL,
    total     NUMERIC(12,2) NOT NULL DEFAULT 0
);

CREATE INDEX IF NOT EXISTS idx_compras_otros_rubros_compra ON compras_otros_rubros (id_compra);

COMMENT ON TABLE compras_otros_rubros IS
    'Detalle de <otrosRubrosTerceros><rubro> del XML de la factura de compra. Tabla hija de compras_cabecera; '
    'la suma vive en compras_cabecera.otros_rubros_terceros.';

-- ── 3. Backfill de compras electrónicas ya cargadas (OPCIONAL, revisar antes) ─
-- Control (solo lectura): compras con el nodo en su XML y la columna todavía en 0.
-- SELECT c.id, c.id_empresa, c.establecimiento_prov || '-' || c.punto_emision_prov || '-' || c.secuencial_prov AS numero,
--        c.importe_total, c.total_sin_impuestos, c.propina,
--        (SELECT ROUND(SUM(m[2]::numeric), 2)
--           FROM regexp_matches(c.detalle_xml, '<rubro>\s*<concepto>([^<]*)</concepto>\s*<total>([0-9.]+)</total>', 'g') AS m) AS rubros
--   FROM compras_cabecera c
--  WHERE c.eliminado = false
--    AND c.otros_rubros_terceros = 0
--    AND c.detalle_xml LIKE '%<otrosRubrosTerceros>%';
--
-- Aplicar (descomentar las dos sentencias; idempotente: solo toca compras en 0):
-- INSERT INTO compras_otros_rubros (id_compra, concepto, total)
-- SELECT c.id, LEFT(m[1], 300), m[2]::numeric
--   FROM compras_cabecera c,
--        regexp_matches(c.detalle_xml, '<rubro>\s*<concepto>([^<]*)</concepto>\s*<total>([0-9.]+)</total>', 'g') AS m
--  WHERE c.eliminado = false
--    AND c.otros_rubros_terceros = 0
--    AND c.detalle_xml LIKE '%<otrosRubrosTerceros>%'
--    AND NOT EXISTS (SELECT 1 FROM compras_otros_rubros r WHERE r.id_compra = c.id);
--
-- UPDATE compras_cabecera c
--    SET otros_rubros_terceros = s.total
--   FROM (SELECT id_compra, ROUND(SUM(total), 2) AS total FROM compras_otros_rubros GROUP BY id_compra) s
--  WHERE s.id_compra = c.id
--    AND c.otros_rubros_terceros = 0
--    AND s.total > 0;
