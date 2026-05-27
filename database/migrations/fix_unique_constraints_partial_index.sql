-- ============================================================================
-- MIGRACIÓN: Convertir restricciones únicas a índices parciales
-- Objetivo: Permitir re-registrar documentos electrónicos que fueron
--           eliminados lógicamente (eliminado = true), tratando los
--           registros eliminados como si no existieran.
-- ============================================================================

-- ─── retencion_venta_cabecera ────────────────────────────────────────────────
ALTER TABLE retencion_venta_cabecera DROP CONSTRAINT IF EXISTS uq_ret_vta_cab_clave;
DROP INDEX IF EXISTS uq_ret_vta_cab_clave;
DROP INDEX IF EXISTS uq_ret_vta_cab_clave_active;
CREATE UNIQUE INDEX uq_ret_vta_cab_clave_active
    ON retencion_venta_cabecera (clave_acceso, id_empresa)
    WHERE eliminado = false;

-- ─── retencion_compra_cabecera ───────────────────────────────────────────────
ALTER TABLE retencion_compra_cabecera DROP CONSTRAINT IF EXISTS uq_ret_cmp_cab_clave;
DROP INDEX IF EXISTS uq_ret_cmp_cab_clave;
DROP INDEX IF EXISTS uq_ret_cmp_cab_clave_active;
CREATE UNIQUE INDEX uq_ret_cmp_cab_clave_active
    ON retencion_compra_cabecera (clave_acceso, id_empresa)
    WHERE eliminado = false;

-- ─── ventas_cabecera ─────────────────────────────────────────────────────────
ALTER TABLE ventas_cabecera DROP CONSTRAINT IF EXISTS uq_ventas_clave_acceso;
DROP INDEX IF EXISTS uq_ventas_clave_acceso;
DROP INDEX IF EXISTS uq_ventas_clave_acceso_active;
CREATE UNIQUE INDEX uq_ventas_clave_acceso_active
    ON ventas_cabecera (clave_acceso, id_empresa)
    WHERE eliminado = false;

-- ─── compras_cabecera ────────────────────────────────────────────────────────
ALTER TABLE compras_cabecera DROP CONSTRAINT IF EXISTS uq_compras_num_autorizacion;
DROP INDEX IF EXISTS uq_compras_num_autorizacion;
DROP INDEX IF EXISTS uq_compras_num_autorizacion_active;
CREATE UNIQUE INDEX uq_compras_num_autorizacion_active
    ON compras_cabecera (numero_autorizacion, id_empresa)
    WHERE eliminado = false;

-- ─── liquidaciones_cabecera ──────────────────────────────────────────────────
ALTER TABLE liquidaciones_cabecera DROP CONSTRAINT IF EXISTS uq_liq_clave_acceso;
DROP INDEX IF EXISTS uq_liq_clave_acceso;
DROP INDEX IF EXISTS uq_liq_clave_acceso_active;
CREATE UNIQUE INDEX uq_liq_clave_acceso_active
    ON liquidaciones_cabecera (clave_acceso, id_empresa)
    WHERE eliminado = false;

-- ─── notas_credito_cabecera ──────────────────────────────────────────────────
ALTER TABLE notas_credito_cabecera DROP CONSTRAINT IF EXISTS uq_nc_clave_acceso;
DROP INDEX IF EXISTS uq_nc_clave_acceso;
DROP INDEX IF EXISTS uq_nc_clave_acceso_active;
CREATE UNIQUE INDEX uq_nc_clave_acceso_active
    ON notas_credito_cabecera (clave_acceso, id_empresa)
    WHERE eliminado = false;

-- ─── nota_debito_cabecera ────────────────────────────────────────────────────
ALTER TABLE nota_debito_cabecera DROP CONSTRAINT IF EXISTS uq_nd_clave_acceso;
DROP INDEX IF EXISTS uq_nd_clave_acceso;
DROP INDEX IF EXISTS uq_nd_clave_acceso_active;
CREATE UNIQUE INDEX uq_nd_clave_acceso_active
    ON nota_debito_cabecera (clave_acceso, id_empresa)
    WHERE eliminado = false;
