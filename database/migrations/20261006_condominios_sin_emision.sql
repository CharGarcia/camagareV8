-- ============================================================================
-- Condominios: la emisión (comprobante, serie, día de cobro, vencimiento) la define cada
-- suscripción en el módulo Suscripciones, no la configuración del condominio. Este script
-- quita las columnas que sobraban. Solo hace falta si 20261005_condominios.sql se aplicó
-- ANTES del 06-10-2026 (la versión actual de ese archivo ya no las crea).
-- Idempotente. Listo para pgAdmin (F5).
-- ============================================================================
BEGIN;
ALTER TABLE condominios_config   DROP COLUMN IF EXISTS comprobante_defecto;
ALTER TABLE condominios_config   DROP COLUMN IF EXISTS id_punto_emision_defecto;
ALTER TABLE condominios_config   DROP COLUMN IF EXISTS dia_emision;
ALTER TABLE condominios_config   DROP COLUMN IF EXISTS vencimiento_tipo;
ALTER TABLE condominios_config   DROP COLUMN IF EXISTS vencimiento_valor;
ALTER TABLE condominios_config   DROP COLUMN IF EXISTS permite_vencimiento_por_unidad;
ALTER TABLE condominios_unidades DROP COLUMN IF EXISTS comprobante;
ALTER TABLE condominios_unidades DROP COLUMN IF EXISTS id_punto_emision;
ALTER TABLE condominios_unidades DROP COLUMN IF EXISTS dia_vencimiento_propio;
COMMIT;
