-- ============================================================================
-- CxP: las Notas de Crédito / Débito de COMPRA deben restar/sumar a la factura
-- ----------------------------------------------------------------------------
-- Contexto:
--   Las NC (04) y ND (05) recibidas de proveedores se guardan en
--   compras_cabecera con tipo_comprobante '04'/'05'. El módulo de Cuentas por
--   Pagar (modulos/cuentas_por_pagar) YA las excluye del listado (solo muestra
--   '01' y liquidaciones) y ajusta el saldo de la factura afectada cruzando por
--   la columna `documento_modificado` (= establecimiento-punto-secuencial de la
--   factura, CTE nc_nd).
--
--   Bug: al importar por SRI (DocumentoAutomatedRegisterService::insertarCompra)
--   NO se guardaba `documento_modificado`, por lo que la NC/ND nunca cruzaba con
--   su factura y el saldo de CxP quedaba SIN el descuento. Ya corregido en código
--   para importaciones nuevas; este script repara las NC/ND ya cargadas.
--
-- Qué hace:
--   Rellena `documento_modificado` (y `motivo` si está vacío) para las NC/ND de
--   compra existentes, extrayendo <numDocModificado> del XML original guardado en
--   `detalle_xml`. No borra ni modifica ningún otro dato. Es idempotente: solo
--   toca filas con `documento_modificado` vacío.
--
-- Reversible: no destruye datos; en el peor caso se puede volver a NULL con:
--   UPDATE compras_cabecera SET documento_modificado = NULL
--   WHERE tipo_comprobante IN ('04','05');
-- ============================================================================

BEGIN;

-- 1) documento_modificado desde el XML (factura que modifica la NC/ND)
UPDATE compras_cabecera
SET documento_modificado = btrim(substring(detalle_xml FROM '<numDocModificado>([^<]+)</numDocModificado>')),
    updated_at           = NOW()
WHERE tipo_comprobante IN ('04', '05')
  AND eliminado = false
  AND (documento_modificado IS NULL OR btrim(documento_modificado) = '')
  AND detalle_xml IS NOT NULL
  AND detalle_xml ~ '<numDocModificado>[^<]+</numDocModificado>';

-- 2) motivo desde el XML (informativo), solo si está vacío
UPDATE compras_cabecera
SET motivo     = btrim(substring(detalle_xml FROM '<motivo>([^<]+)</motivo>')),
    updated_at = NOW()
WHERE tipo_comprobante IN ('04', '05')
  AND eliminado = false
  AND (motivo IS NULL OR btrim(motivo) = '')
  AND detalle_xml IS NOT NULL
  AND detalle_xml ~ '<motivo>[^<]+</motivo>';

COMMIT;

-- ============================================================================
-- Verificación (ejecutar aparte, no modifica nada):
--
--   -- NC/ND que YA cruzan contra una factura de compra existente:
--   SELECT nc.id, nc.tipo_comprobante, nc.documento_modificado, nc.importe_total,
--          f.id AS id_factura,
--          CONCAT(f.establecimiento_prov,'-',f.punto_emision_prov,'-',f.secuencial_prov) AS factura
--   FROM compras_cabecera nc
--   LEFT JOIN compras_cabecera f
--          ON f.id_empresa = nc.id_empresa
--         AND f.id_proveedor = nc.id_proveedor
--         AND f.tipo_comprobante = '01'
--         AND f.eliminado = false
--         AND CONCAT(f.establecimiento_prov,'-',f.punto_emision_prov,'-',f.secuencial_prov) = nc.documento_modificado
--   WHERE nc.tipo_comprobante IN ('04','05') AND nc.eliminado = false
--   ORDER BY nc.id;
--
--   -- NC/ND que quedaron SIN documento_modificado (revisar manualmente):
--   SELECT id, tipo_comprobante, importe_total, fecha_emision
--   FROM compras_cabecera
--   WHERE tipo_comprobante IN ('04','05') AND eliminado = false
--     AND (documento_modificado IS NULL OR btrim(documento_modificado) = '');
-- ============================================================================
