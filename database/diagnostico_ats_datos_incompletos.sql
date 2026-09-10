-- ============================================================================
-- Diagnóstico ATS — datos que el SRI rechaza al cargar el anexo
--
-- Solo LECTURA: no modifica nada. Pegar en pgAdmin y ejecutar de una sola vez.
-- Editar únicamente el bloque `parametros` (RUC y período a revisar).
--
-- Cubre los cuatro rechazos típicos:
--   1. Compras/liquidaciones sin código de sustento tributario.
--   2. Sustento tributario no permitido para ese tipo de comprobante.
--   3. Proveedor con pasaporte / identificación del exterior sin tipo de
--      empresa o sin razón social (el SRI exige tipoProv y denoProv).
--   4. Clientes con un tipo de identificación que el ATS no reconoce en Ventas.
-- ============================================================================

WITH parametros AS (
    SELECT '1790012345001'::varchar AS ruc,     -- ← RUC de la empresa
           DATE '2026-02-01'        AS desde,   -- ← primer día del período
           DATE '2026-02-28'        AS hasta    -- ← último día del período
),
empresas_ruc AS (
    SELECT e.id
    FROM empresas e, parametros p
    WHERE e.ruc = p.ruc AND e.eliminado = false
)

-- 1 y 2 — sustento tributario de compras y liquidaciones
SELECT '1-2. Sustento'                  AS revision,
       c.id                             AS id_documento,
       'compra'                         AS origen,
       c.tipo_comprobante,
       c.establecimiento_prov || '-' || c.punto_emision_prov || '-' || c.secuencial_prov AS serie,
       c.fecha_emision,
       pr.identificacion                AS proveedor,
       pr.razon_social,
       COALESCE(st.codigo, '(sin sustento)') AS sustento,
       CASE
           WHEN st.codigo IS NULL THEN 'Sin sustento tributario: el ATS asume uno permitido para el tipo'
           ELSE 'Sustento ' || st.codigo || ' no permitido para comprobantes tipo ' || c.tipo_comprobante
       END                              AS problema
FROM compras_cabecera c
JOIN proveedores pr              ON pr.id = c.id_proveedor
LEFT JOIN sustento_tributario st ON st.id = c.id_sustento_tributario
CROSS JOIN parametros p
WHERE c.id_empresa IN (SELECT id FROM empresas_ruc)
  AND c.eliminado = false
  AND c.fecha_emision BETWEEN p.desde AND p.hasta
  AND (st.codigo IS NULL
       OR NOT (c.tipo_comprobante = ANY(string_to_array(st.tipo_comprobante, ','))))

UNION ALL

SELECT '1-2. Sustento',
       l.id,
       'liquidacion',
       '03',
       l.establecimiento || '-' || l.punto_emision || '-' || l.secuencial,
       l.fecha_emision,
       pr.identificacion,
       pr.razon_social,
       COALESCE(st.codigo, '(sin sustento)'),
       CASE
           WHEN st.codigo IS NULL THEN 'Sin sustento tributario: el ATS asume uno permitido para el tipo'
           ELSE 'Sustento ' || st.codigo || ' no permitido para liquidaciones (tipo 03)'
       END
FROM liquidaciones_cabecera l
JOIN proveedores pr              ON pr.id = l.id_proveedor
LEFT JOIN sustento_tributario st ON st.id = l.id_sustento_tributario
CROSS JOIN parametros p
WHERE l.id_empresa IN (SELECT id FROM empresas_ruc)
  AND l.eliminado = false
  AND l.fecha_emision BETWEEN p.desde AND p.hasta
  AND (st.codigo IS NULL
       OR NOT ('03' = ANY(string_to_array(st.tipo_comprobante, ','))))

UNION ALL

-- 3 — proveedores con pasaporte / identificación del exterior incompletos
SELECT DISTINCT
       '3. Proveedor exterior',
       pr.id,
       'proveedor',
       c.tipo_comprobante,
       c.establecimiento_prov || '-' || c.punto_emision_prov || '-' || c.secuencial_prov,
       c.fecha_emision,
       pr.identificacion,
       pr.razon_social,
       pr.tipo_id_proveedor,
       CASE
           WHEN COALESCE(TRIM(pr.razon_social), '') = '' THEN 'Falta la razón o denominación social (denoProv)'
           ELSE 'Falta el tipo de empresa en la ficha del proveedor (tipoProv)'
       END
FROM compras_cabecera c
JOIN proveedores pr ON pr.id = c.id_proveedor
CROSS JOIN parametros p
WHERE c.id_empresa IN (SELECT id FROM empresas_ruc)
  AND c.eliminado = false
  AND c.fecha_emision BETWEEN p.desde AND p.hasta
  AND pr.tipo_id_proveedor IN ('06', '08', '03')   -- pasaporte / identificación del exterior
  AND (pr.tipo_empresa IS NULL OR COALESCE(TRIM(pr.razon_social), '') = '')

UNION ALL

-- 4 — clientes con tipo de identificación fuera del catálogo de Ventas del ATS
SELECT DISTINCT
       '4. Tipo id. cliente',
       cl.id,
       'cliente',
       '18',
       v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial,
       v.fecha_emision,
       cl.identificacion,
       cl.nombre,
       cl.tipo_id,
       'tipo_id ' || COALESCE(cl.tipo_id, '(vacío)')
           || ' no corresponde al catálogo de Ventas (04 RUC, 05 cédula, 06 pasaporte, '
           || '07 consumidor final, 08 identificación del exterior)'
FROM ventas_cabecera v
JOIN clientes cl ON cl.id = v.id_cliente
CROSS JOIN parametros p
WHERE v.id_empresa IN (SELECT id FROM empresas_ruc)
  AND v.eliminado = false
  AND v.estado = 'autorizado'
  AND v.fecha_emision BETWEEN p.desde AND p.hasta
  AND COALESCE(cl.tipo_id, '') NOT IN ('04', '05', '06', '07', '08')

ORDER BY 1, 6, 5;
