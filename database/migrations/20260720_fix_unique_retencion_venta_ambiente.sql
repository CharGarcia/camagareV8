-- =============================================================
-- Retenciones de Venta — deduplicación por AMBIENTE
--
-- Problema: al importar desde el SRI, un comprobante que ya existía en el
-- OTRO ambiente (pruebas '1' / producción '2') bloqueaba el registro:
--   a) las validaciones PHP (existeClaveAcceso / existeNumero) no filtraban
--      por tipo_ambiente  → corregido en el código, y
--   b) el índice único no incluía tipo_ambiente → PostgreSQL rechazaba el
--      INSERT con 23505 aunque PHP ya lo dejara pasar.
--
-- Este script arregla (b): la unicidad pasa a ser por
-- (id_empresa, tipo_ambiente, clave_acceso), solo sobre registros vivos.
-- Es MÁS permisivo que el índice anterior, así que no puede fallar por datos
-- existentes. Los registros eliminados (eliminado = true) siguen sin bloquear.
-- =============================================================

-- Constraint global antigua (UNIQUE (clave_acceso)) de la DDL original:
-- bloqueaba incluso entre empresas y registros eliminados.
ALTER TABLE retencion_venta_cabecera DROP CONSTRAINT IF EXISTS uq_ret_vta_cab_clave;

-- Índice parcial anterior, sin tipo_ambiente.
DROP INDEX IF EXISTS uq_ret_vta_cab_clave_active;

CREATE UNIQUE INDEX IF NOT EXISTS uq_ret_vta_cab_clave_active
    ON retencion_venta_cabecera (id_empresa, tipo_ambiente, clave_acceso)
    WHERE eliminado = false AND clave_acceso IS NOT NULL;
