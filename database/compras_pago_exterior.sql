-- ============================================================================
-- Compras: bloque <pagoExterior> del Anexo Transaccional (ATS)
-- ----------------------------------------------------------------------------
-- Contexto:
--   El ATS exige, en cada compra, un bloque <pagoExterior> con cuatro datos:
--     pagoLocExt          01 = pago local, 02 = pago al exterior
--     paisEfecPago        código de país del SRI (NA si el pago es local)
--     aplicConvDobTrib    SI/NO — se aplica convenio de doble tributación (NA si local)
--     pagExtSujRetNorLeg  SI/NO — el pago al exterior está sujeto a retención (NA si local)
--
--   Hasta ahora el generador emitía siempre "01" + NA porque no existían los
--   campos en la base. Eso es correcto para una compra local, pero NO para un
--   comprobante emitido en el exterior (tipo 15), que por definición es un pago
--   al exterior y el SRI rechaza si se declara como local.
--
-- Qué hace:
--   Agrega cuatro columnas nullable a compras_cabecera. `pago_loc_ext` nace en
--   '01' (pago local), que es exactamente lo que el ATS venía reportando, así
--   que las compras ya registradas generan el mismo XML que antes.
--
-- Idempotente: usa IF NOT EXISTS; se puede volver a ejecutar sin efecto.
--
-- Después de aplicarlo:
--   Revisar las compras con comprobante emitido en el exterior ya registradas y
--   marcarlas como pago al exterior desde el módulo Compras (pestaña "Pago al
--   exterior"). La consulta del final las lista.
--
-- Reversible:
--   ALTER TABLE compras_cabecera
--     DROP COLUMN IF EXISTS pago_loc_ext,
--     DROP COLUMN IF EXISTS cod_pais_pago,
--     DROP COLUMN IF EXISTS aplic_conv_dob_trib,
--     DROP COLUMN IF EXISTS pag_ext_suj_ret_nor_leg;
-- ============================================================================

BEGIN;

ALTER TABLE compras_cabecera
    ADD COLUMN IF NOT EXISTS pago_loc_ext            VARCHAR(2) DEFAULT '01',
    ADD COLUMN IF NOT EXISTS cod_pais_pago           VARCHAR(3),
    ADD COLUMN IF NOT EXISTS aplic_conv_dob_trib     VARCHAR(2),
    ADD COLUMN IF NOT EXISTS pag_ext_suj_ret_nor_leg VARCHAR(2);

COMMENT ON COLUMN compras_cabecera.pago_loc_ext IS
    'ATS <pagoExterior>: 01 pago local, 02 pago al exterior';
COMMENT ON COLUMN compras_cabecera.cod_pais_pago IS
    'ATS paisEfecPago: código de país del SRI (App\Helpers\CatalogoPaisesSri). NULL si el pago es local';
COMMENT ON COLUMN compras_cabecera.aplic_conv_dob_trib IS
    'ATS aplicConvDobTrib: SI/NO — convenio de doble tributación. NULL si el pago es local';
COMMENT ON COLUMN compras_cabecera.pag_ext_suj_ret_nor_leg IS
    'ATS pagExtSujRetNorLeg: SI/NO — pago al exterior sujeto a retención. NULL si el pago es local';

-- Las filas creadas antes del ALTER quedan en NULL; el DEFAULT solo aplica a las
-- nuevas. Se igualan a '01' (pago local) para que el ATS siga emitiendo lo mismo.
UPDATE compras_cabecera
SET pago_loc_ext = '01'
WHERE pago_loc_ext IS NULL;

COMMIT;

-- ── Compras que probablemente haya que marcar como pago al exterior ──────────
-- (comprobantes emitidos en el exterior, o proveedor con identificación del
--  exterior/pasaporte, que hoy están declarados como pago local)
SELECT c.id,
       c.id_empresa,
       c.tipo_comprobante,
       c.establecimiento_prov || '-' || c.punto_emision_prov || '-' || c.secuencial_prov AS serie,
       c.fecha_emision,
       p.identificacion AS proveedor,
       p.razon_social,
       c.pago_loc_ext
FROM compras_cabecera c
JOIN proveedores p ON p.id = c.id_proveedor
WHERE c.eliminado = false
  AND COALESCE(c.pago_loc_ext, '01') = '01'
  AND (c.tipo_comprobante = '15' OR p.tipo_id_proveedor IN ('06', '08', '03'))
ORDER BY c.fecha_emision DESC, c.id DESC;
