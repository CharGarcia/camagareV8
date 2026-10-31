-- ============================================================================
-- Liquidaciones de compra: bloque <pagoExterior> del Anexo Transaccional (ATS)
-- ----------------------------------------------------------------------------
-- Contexto:
--   La liquidación de compra (codDoc 03) también se emite para pagar servicios
--   prestados por no residentes (proveedor con identificación del exterior, 08).
--   Ese pago va al ATS como pago al exterior, con cuatro datos:
--     pagoLocExt          01 = pago local, 02 = pago al exterior
--     paisEfecPago        código de país del SRI (NA si el pago es local)
--     aplicConvDobTrib    SI/NO — convenio de doble tributación (NA si local)
--     pagExtSujRetNorLeg  SI/NO — sujeto a retención según norma legal (NA si local)
--
--   Hasta ahora el generador del ATS emitía siempre "01" + NA para las
--   liquidaciones porque no existían los campos. Mismas columnas que
--   compras_cabecera (database/compras_pago_exterior.sql).
--
-- Qué hace:
--   Agrega cuatro columnas nullable a liquidaciones_cabecera. `pago_loc_ext` nace
--   en '01' (pago local), que es lo que el ATS venía reportando: las liquidaciones
--   ya registradas generan el mismo anexo que antes.
--
-- Idempotente: usa IF NOT EXISTS; se puede volver a ejecutar sin efecto.
--
-- Después de aplicarlo:
--   Revisar las liquidaciones a proveedores del exterior ya registradas (consulta
--   del final) y marcarlas como pago al exterior en la subpestaña «Pago al
--   exterior» del modal, si aún están en borrador.
--
-- Reversible:
--   ALTER TABLE liquidaciones_cabecera
--     DROP COLUMN IF EXISTS pago_loc_ext,
--     DROP COLUMN IF EXISTS cod_pais_pago,
--     DROP COLUMN IF EXISTS aplic_conv_dob_trib,
--     DROP COLUMN IF EXISTS pag_ext_suj_ret_nor_leg;
-- ============================================================================

BEGIN;

ALTER TABLE liquidaciones_cabecera
    ADD COLUMN IF NOT EXISTS pago_loc_ext            VARCHAR(2) DEFAULT '01',
    ADD COLUMN IF NOT EXISTS cod_pais_pago           VARCHAR(3),
    ADD COLUMN IF NOT EXISTS aplic_conv_dob_trib     VARCHAR(2),
    ADD COLUMN IF NOT EXISTS pag_ext_suj_ret_nor_leg VARCHAR(2);

COMMENT ON COLUMN liquidaciones_cabecera.pago_loc_ext IS
    'ATS <pagoExterior>: 01 pago local, 02 pago al exterior';
COMMENT ON COLUMN liquidaciones_cabecera.cod_pais_pago IS
    'ATS paisEfecPago: código de país del SRI (App\Helpers\CatalogoPaisesSri). NULL si el pago es local';
COMMENT ON COLUMN liquidaciones_cabecera.aplic_conv_dob_trib IS
    'ATS aplicConvDobTrib: SI/NO — convenio de doble tributación. NULL si el pago es local';
COMMENT ON COLUMN liquidaciones_cabecera.pag_ext_suj_ret_nor_leg IS
    'ATS pagExtSujRetNorLeg: SI/NO — pago al exterior sujeto a retención. NULL si el pago es local';

-- Las filas creadas antes del ALTER quedan en NULL; el DEFAULT solo aplica a las
-- nuevas. Se igualan a '01' (pago local) para que el ATS siga emitiendo lo mismo.
UPDATE liquidaciones_cabecera
SET pago_loc_ext = '01'
WHERE pago_loc_ext IS NULL;

COMMIT;

-- ── Liquidaciones a proveedores del exterior declaradas como pago local ─────
SELECT l.id,
       l.id_empresa,
       l.establecimiento || '-' || l.punto_emision || '-' || l.secuencial AS numero,
       l.fecha_emision,
       l.estado,
       p.identificacion AS proveedor,
       p.razon_social,
       l.pago_loc_ext
FROM liquidaciones_cabecera l
JOIN proveedores p ON p.id = l.id_proveedor
WHERE l.eliminado = false
  AND COALESCE(l.pago_loc_ext, '01') = '01'
  AND p.tipo_id_proveedor IN ('06', '08')
ORDER BY l.fecha_emision DESC, l.id DESC;
