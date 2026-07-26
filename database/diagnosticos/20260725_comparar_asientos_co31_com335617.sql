-- Compara CO-000031 vs COM335617 (empresa 8) para determinar cuál está realmente atado a la
-- compra 001-102-000000013 del proveedor 1717136574001, y cuál quedó suelto.
--
-- Criterio de "atado de verdad" (los 3 deben cumplirse):
--   a) compras_cabecera.id_asiento_contable apunta a ese asiento  -> el documento lo reconoce
--   b) asiento.id_referencia_origen apunta a una compra que EXISTE y no está eliminada
--   c) asiento.modulo_origen corresponde a la tabla real del documento ('compra')

-- 1) La compra en cuestión: a qué asiento dice ella que pertenece
SELECT c.id                AS id_compra,
       c.establecimiento_prov || '-' || c.punto_emision_prov || '-' || c.secuencial_prov AS numero_factura,
       p.identificacion    AS ruc_proveedor,
       p.razon_social,
       c.fecha_emision,
       c.importe_total,
       c.eliminado,
       c.id_asiento_contable AS asiento_al_que_apunta_la_compra
FROM compras_cabecera c
LEFT JOIN proveedores p ON p.id = c.id_proveedor AND p.id_empresa = c.id_empresa
WHERE c.id_empresa = 8
  AND c.establecimiento_prov = '001'
  AND c.punto_emision_prov  = '102'
  AND c.secuencial_prov     = '000000013';

-- 2) Veredicto lado a lado de los dos asientos
SELECT
    ac.id                    AS id_asiento,
    ac.numero_comprobante,
    ac.modulo_origen,
    ac.tipo_comprobante,
    ac.id_referencia_origen  AS apunta_a_compra_id,
    ac.fecha_asiento,
    ac.total_debe,
    ac.estado,
    ac.eliminado,
    ac.created_at,
    -- (a) ¿la compra lo reconoce como suyo?
    (c_link.id IS NOT NULL)                       AS la_compra_lo_reconoce,
    -- (b) ¿el documento al que apunta existe y está vigente?
    (c_ref.id IS NOT NULL)                        AS su_documento_existe,
    c_ref.establecimiento_prov || '-' || c_ref.punto_emision_prov || '-' || c_ref.secuencial_prov AS documento_referenciado,
    -- Veredicto final
    CASE
        WHEN c_link.id IS NOT NULL AND c_ref.id IS NOT NULL AND ac.modulo_origen = 'compra'
            THEN 'ATADO A LA COMPRA (es el bueno)'
        WHEN c_ref.id IS NULL
            THEN 'SUELTO: no apunta a ningún documento existente'
        WHEN ac.modulo_origen <> 'compra'
            THEN 'SUELTO: su modulo_origen no es una compra (' || ac.modulo_origen || ')'
        ELSE 'DUPLICADO: apunta a la compra pero la compra NO lo reconoce'
    END AS veredicto
FROM asientos_contables_cabecera ac
-- (a) la compra que declara a ESTE asiento como suyo
LEFT JOIN compras_cabecera c_link
       ON c_link.id_asiento_contable = ac.id AND c_link.id_empresa = ac.id_empresa AND c_link.eliminado = false
-- (b) el documento al que ESTE asiento dice pertenecer
LEFT JOIN compras_cabecera c_ref
       ON c_ref.id = ac.id_referencia_origen AND c_ref.id_empresa = ac.id_empresa AND c_ref.eliminado = false
WHERE ac.id_empresa = 8
  AND ac.numero_comprobante IN ('CO-000031', 'COM335617');

-- 3) Detalle contable de ambos (para comparar cuentas y montos)
SELECT ac.numero_comprobante, ad.id AS id_detalle,
       pc.codigo AS cuenta, pc.nombre AS cuenta_nombre,
       ad.debe, ad.haber, ad.referencia_detalle
FROM asientos_contables_cabecera ac
JOIN asientos_contables_detalle ad ON ad.id_asiento = ac.id AND ad.id_empresa = ac.id_empresa
LEFT JOIN plan_cuentas pc ON pc.id = ad.id_cuenta_contable AND pc.id_empresa = ac.id_empresa
WHERE ac.id_empresa = 8
  AND ac.numero_comprobante IN ('CO-000031', 'COM335617')
ORDER BY ac.numero_comprobante, ad.id;
