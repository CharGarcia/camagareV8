-- =============================================================================
-- Compras: rubro del gasto personal (vivienda, salud, educación, alimentación,
-- vestimenta, turismo) para las compras marcadas Deducible = «Gasto personal».
--
-- Qué hace : agrega la columna compras_cabecera.rubro_gasto_personal (texto corto,
--            NULL = sin rubro) y un índice parcial para los reportes que agrupan
--            por rubro (Declaración de Renta, Anexo de Gastos Personales).
-- Toca datos: NO (no modifica filas existentes; las compras antiguas quedan sin
--            rubro y se muestran como «Sin rubro» hasta que se clasifiquen).
-- Reversible: sí → ALTER TABLE compras_cabecera DROP COLUMN rubro_gasto_personal;
-- Idempotente: sí (IF NOT EXISTS). Listo para pegar en pgAdmin y ejecutar (F5).
-- =============================================================================

ALTER TABLE compras_cabecera
    ADD COLUMN IF NOT EXISTS rubro_gasto_personal VARCHAR(20) NULL;

COMMENT ON COLUMN compras_cabecera.rubro_gasto_personal IS
    'Rubro SRI del gasto personal (vivienda, salud, educacion, alimentacion, vestimenta, turismo). Solo aplica cuando deducible = gasto_personal.';

-- Índice parcial: solo las compras de gasto personal, que es lo que agrupan los reportes.
CREATE INDEX IF NOT EXISTS idx_compras_rubro_gasto_personal
    ON compras_cabecera (id_empresa, fecha_emision, rubro_gasto_personal)
    WHERE deducible = 'gasto_personal' AND eliminado = false;

-- Comprobación (debe salir 1 fila con la columna):
-- SELECT column_name, data_type, character_maximum_length
-- FROM information_schema.columns
-- WHERE table_name = 'compras_cabecera' AND column_name = 'rubro_gasto_personal';
