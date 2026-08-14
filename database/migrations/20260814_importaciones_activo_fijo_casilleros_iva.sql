-- =============================================================================
-- Importaciones → Declaración de IVA: casilleros oficiales 503/513/523 y
-- 504/514/524, y clasificación de líneas por "activo fijo"
-- ----------------------------------------------------------------------------
-- La migración 20260723_importaciones_declaracion_iva.sql agregó al catálogo
-- de casilleros oficiales (sri_casilleros_etiquetas) un par inventado 504
-- (solo Bruto, nunca se llenaba) / 505 (solo Impuesto) para "Importaciones".
-- Esos números NO son los oficiales del Formulario 104: el patrón real de la
-- sección 500 es bruto/neto(+10)/impuesto(+20) por fila (500/510/520,
-- 501/511/521, 502/512/522...), y las dos filas de Importaciones son:
--   503/513/523 → Importaciones de bienes gravados tarifa diferente de cero
--                 (EXCEPTO activos fijos), con derecho a crédito tributario
--   504/514/524 → Importaciones de ACTIVOS FIJOS gravados tarifa diferente
--                 de cero, con derecho a crédito tributario
--
-- Esta migración:
-- 1) Agrega importaciones_detalle.es_activo_fijo (flag por línea de producto,
--    default false) para poder repartir el IVA de aduana entre las dos filas
--    a prorrata del valor FOB de cada grupo (ver
--    ImportacionesService::sincronizarCasilleros()).
-- 2) Da de baja (soft-delete) el par inventado 504/505 y lo reemplaza por las
--    dos filas oficiales completas (bruto+neto+impuesto) 503/513/523 y
--    504/514/524.
-- 3) Corrige la configuración ya guardada por empresa: cualquier
--    empresa_casilleros_iva_sri de tipo_documento='importacion' que apuntara
--    al casillero de impuesto inventado '505' pasa a apuntar al oficial
--    '523'. La fila nueva 'importacion_activo_fijo' (524) queda sin
--    configurar: cada empresa debe completarla en Empresa → Form 104 IVA →
--    Importaciones de Activos Fijos (o con "Carga Rápida").
--
-- Idempotente, no destructiva (solo soft-delete + UPDATE dirigido).
-- =============================================================================

BEGIN;

ALTER TABLE importaciones_detalle
    ADD COLUMN IF NOT EXISTS es_activo_fijo BOOLEAN NOT NULL DEFAULT FALSE;

-- Baja del par inventado 504 (bruto informativo, nunca se llenó) / 505 (impuesto).
UPDATE sri_casilleros_etiquetas
   SET eliminado = true, deleted_at = NOW()
 WHERE eliminado = false
   AND seccion = '500'
   AND (
        (casillero_bruto = '504' AND descripcion LIKE 'Importaciones de bienes gravados tarifa diferente de cero (base referencial)%')
     OR (casillero_bruto = '505' AND descripcion LIKE 'Importaciones de bienes gravados tarifa diferente de cero (IVA%')
   );

-- Fila oficial 503/513/523: Importaciones (excepto activos fijos).
INSERT INTO sri_casilleros_etiquetas
    (casillero_bruto, casillero_neto, casillero_impuesto, seccion, descripcion, orden, indent, bold, tipo, fuente_valor, eliminado, created_at)
SELECT '503', '513', '523', '500',
       'Importaciones de bienes gravados tarifa diferente de cero (excepto activos fijos, con derecho a crédito tributario)',
       65, 0, false, 'valor', 'documentos', false, now()
WHERE NOT EXISTS (SELECT 1 FROM sri_casilleros_etiquetas WHERE casillero_bruto = '503' AND seccion = '500' AND eliminado = false);

-- Fila oficial 504/514/524: Importaciones de activos fijos.
INSERT INTO sri_casilleros_etiquetas
    (casillero_bruto, casillero_neto, casillero_impuesto, seccion, descripcion, orden, indent, bold, tipo, fuente_valor, eliminado, created_at)
SELECT '504', '514', '524', '500',
       'Importaciones de activos fijos gravados tarifa diferente de cero (con derecho a crédito tributario)',
       66, 0, false, 'valor', 'documentos', false, now()
WHERE NOT EXISTS (SELECT 1 FROM sri_casilleros_etiquetas WHERE casillero_bruto = '504' AND seccion = '500' AND eliminado = false);

-- Corrige configuraciones por empresa que apuntaban al casillero inventado '505'.
UPDATE empresa_casilleros_iva_sri
   SET casillero_impuesto = '523', updated_at = NOW()
 WHERE eliminado = false
   AND tipo_documento = 'importacion'
   AND casillero_impuesto = '505';

COMMIT;
