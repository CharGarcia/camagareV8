-- =====================================================================================
-- Conciliación de Cobros: vínculo entre las partes de un mismo depósito.
--
-- Cuando una línea del extracto se reparte entre varios documentos (lupa → varios
-- documentos marcados), se divide en una línea por documento. Todas guardan aquí el id de la
-- línea original, y al generar los ingresos se agrupan: UN ingreso por cliente con todos sus
-- documentos y UN solo pago por el total, para que coincida con el depósito del banco.
--
-- NULL = línea que no viene de un reparto (se genera sola, como siempre).
--
-- Idempotente. Aplicar ANTES de desplegar el código que la usa.
-- =====================================================================================

ALTER TABLE conciliacion_lineas
    ADD COLUMN IF NOT EXISTS id_linea_origen INTEGER NULL;

COMMENT ON COLUMN conciliacion_lineas.id_linea_origen IS
    'Línea original del extracto de la que salió esta parte al repartir un depósito entre varios documentos. Las partes confirmadas del mismo origen y cliente se cobran en un solo ingreso.';

CREATE INDEX IF NOT EXISTS idx_conciliacion_lineas_origen
    ON conciliacion_lineas (id_linea_origen)
 WHERE id_linea_origen IS NOT NULL;
