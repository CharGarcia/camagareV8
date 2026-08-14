-- =============================================================================
-- Corrige la numeración de casilleros de Importaciones a la oficial del F104
-- ----------------------------------------------------------------------------
-- La migración 20260814_importaciones_activo_fijo_casilleros_iva.sql asumió
-- (mal) que la sección 500 numeraba así:
--   503/513/523 → Importaciones de BIENES (excepto activos fijos)
--   504/514/524 → Importaciones de ACTIVOS FIJOS
--
-- La numeración oficial real del Formulario 104 es:
--   503/513/523 → Importaciones de SERVICIOS y/o derechos
--   504/514/524 → Importaciones de BIENES (excluye activos fijos)
--   505/515/525 → Importaciones de ACTIVOS FIJOS
--
-- Esta migración:
-- 1) Renombra en el catálogo (sri_casilleros_etiquetas) 504→505 (activos
--    fijos) y 503→504 (bienes), en ese orden para no chocar entre sí.
-- 2) Agrega la fila oficial 503/513/523 (servicios) que faltaba en el
--    catálogo (no la usa hoy el módulo de Importaciones, pero completa la
--    sección para uso futuro).
-- 3) Remapea la configuración ya guardada por empresa
--    (empresa_casilleros_iva_sri, tipo_documento 'importacion' /
--    'importacion_activo_fijo') que apuntaba a los números viejos.
-- 4) Remapea los datos ya sincronizados (casilleros_declaracion_sri,
--    origen='importaciones') que se hubieran escrito con los números viejos.
--
-- Idempotente: cada paso solo toca filas que todavía están en el número
-- viejo, así que correrla dos veces no duplica ni revierte nada.
-- =============================================================================

BEGIN;

-- 1a) Libera 504 pasando "activos fijos" a los oficiales 505/515/525.
UPDATE sri_casilleros_etiquetas
   SET casillero_bruto = '505', casillero_neto = '515', casillero_impuesto = '525',
       descripcion = 'Importaciones de activos fijos gravados tarifa diferente de cero (con derecho a crédito tributario)',
       orden = 66, updated_at = NOW()
 WHERE eliminado = false AND seccion = '500' AND casillero_bruto = '504';

-- 1b) Libera 503 pasando "bienes" a los oficiales 504/514/524.
UPDATE sri_casilleros_etiquetas
   SET casillero_bruto = '504', casillero_neto = '514', casillero_impuesto = '524',
       descripcion = 'Importaciones de bienes gravados tarifa diferente de cero (excluye activos fijos, con derecho a crédito tributario)',
       orden = 65, updated_at = NOW()
 WHERE eliminado = false AND seccion = '500' AND casillero_bruto = '503';

-- 2) Fila oficial de servicios 503/513/523 (nueva).
INSERT INTO sri_casilleros_etiquetas
    (casillero_bruto, casillero_neto, casillero_impuesto, seccion, descripcion, orden, indent, bold, tipo, fuente_valor, editable, eliminado, created_at)
SELECT '503', '513', '523', '500',
       'Importaciones de servicios y/o derechos gravados tarifa diferente de cero (con derecho a crédito tributario)',
       64, 0, false, 'valor', 'documentos', false, false, now()
WHERE NOT EXISTS (SELECT 1 FROM sri_casilleros_etiquetas WHERE casillero_bruto = '503' AND seccion = '500' AND eliminado = false);

-- 3a) Config por empresa: 'importacion_activo_fijo' que apuntaba a 504/514/524 → 505/515/525.
UPDATE empresa_casilleros_iva_sri
   SET casillero_bruto    = CASE WHEN casillero_bruto = '504' THEN '505' ELSE casillero_bruto END,
       casillero_neto     = CASE WHEN casillero_neto = '514' THEN '515' ELSE casillero_neto END,
       casillero_impuesto = CASE WHEN casillero_impuesto = '524' THEN '525' ELSE casillero_impuesto END,
       updated_at = NOW()
 WHERE eliminado = false AND tipo_documento = 'importacion_activo_fijo'
   AND ('504' IN (casillero_bruto, casillero_neto, casillero_impuesto)
        OR '514' IN (casillero_bruto, casillero_neto, casillero_impuesto)
        OR '524' IN (casillero_bruto, casillero_neto, casillero_impuesto));

-- 3b) Config por empresa: 'importacion' que apuntaba a 503/513/523 → 504/514/524.
UPDATE empresa_casilleros_iva_sri
   SET casillero_bruto    = CASE WHEN casillero_bruto = '503' THEN '504' ELSE casillero_bruto END,
       casillero_neto     = CASE WHEN casillero_neto = '513' THEN '514' ELSE casillero_neto END,
       casillero_impuesto = CASE WHEN casillero_impuesto = '523' THEN '524' ELSE casillero_impuesto END,
       updated_at = NOW()
 WHERE eliminado = false AND tipo_documento = 'importacion'
   AND ('503' IN (casillero_bruto, casillero_neto, casillero_impuesto)
        OR '513' IN (casillero_bruto, casillero_neto, casillero_impuesto)
        OR '523' IN (casillero_bruto, casillero_neto, casillero_impuesto));

-- 4a) Datos ya sincronizados con los números viejos de "activos fijos" (504/514/524 → 505/515/525).
UPDATE casilleros_declaracion_sri SET casillero = '505', updated_at = NOW() WHERE eliminado = false AND origen = 'importaciones' AND casillero = '504';
UPDATE casilleros_declaracion_sri SET casillero = '515', updated_at = NOW() WHERE eliminado = false AND origen = 'importaciones' AND casillero = '514';
UPDATE casilleros_declaracion_sri SET casillero = '525', updated_at = NOW() WHERE eliminado = false AND origen = 'importaciones' AND casillero = '524';

-- 4b) Datos ya sincronizados con los números viejos de "bienes" (503/513/523 → 504/514/524).
UPDATE casilleros_declaracion_sri SET casillero = '504', updated_at = NOW() WHERE eliminado = false AND origen = 'importaciones' AND casillero = '503';
UPDATE casilleros_declaracion_sri SET casillero = '514', updated_at = NOW() WHERE eliminado = false AND origen = 'importaciones' AND casillero = '513';
UPDATE casilleros_declaracion_sri SET casillero = '524', updated_at = NOW() WHERE eliminado = false AND origen = 'importaciones' AND casillero = '523';

COMMIT;
