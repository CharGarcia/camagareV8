-- =============================================================================
-- Corrige la duplicación de casilleros introducida por las migraciones
-- 20260722/23/24: producción YA tenía una sección completa "LIQUIDACIÓN DEL IVA"
-- (480-499, columna casillero_impuesto) y "RESUMEN IMPOSITIVO" (601-625, ídem),
-- creada antes de estos cambios. Las migraciones anteriores se basaron en un
-- catálogo local mucho más simple y crearon '400_LIQ'/'600_CRED' como secciones
-- redundantes con los mismos códigos.
--
-- Esta migración:
-- 1) Elimina (lógico) los duplicados que se crearon/reactivaron esta semana.
-- 2) Limpia fórmulas rotas en la sección real "LIQUIDACIÓN DEL IVA" (480/481/484
--    tenían la fórmula '429' pegada por error, lo que borraría cualquier valor
--    que el usuario escriba en cada recálculo) y marca editable=true donde debe.
-- 3) Agrega el casillero 486 (que no existía en ningún lado) dentro de la
--    sección real, usando casillero_impuesto como las demás filas de esa sección.
--
-- Idempotente en la medida de lo posible (usa IDs específicos de esta producción,
-- confirmados por consulta directa — no reejecutar en otra base sin adaptar los IDs).
-- =============================================================================

BEGIN;

-- 1) Eliminar (lógico) los duplicados creados/reactivados por 20260722/23/24.
UPDATE sri_casilleros_etiquetas SET eliminado = true, deleted_at = now()
 WHERE id IN (
     125,                    -- 482 duplicado en '400_LIQ'
     121, 122, 123, 124,     -- 605/606/615/617 duplicados en '600_CRED'
     23, 24, 26, 27, 28, 29  -- 480/481/483/484/485/499 duplicados en '400_LIQ'
 );

-- 2) Limpiar fórmulas rotas y marcar editable en la sección real "LIQUIDACIÓN DEL IVA".
UPDATE sri_casilleros_etiquetas SET formula_impuesto = NULL, editable = true, updated_at = now()
 WHERE id = 55; -- 480: transferencias a contado (editable, sin fórmula — se autocalcula en el navegador)

UPDATE sri_casilleros_etiquetas SET formula_impuesto = NULL, editable = true, updated_at = now()
 WHERE id = 56; -- 481: transferencias a crédito (editable, sin fórmula)

UPDATE sri_casilleros_etiquetas SET formula_impuesto = NULL, editable = true, updated_at = now()
 WHERE id = 60; -- 484: impuesto a liquidar este mes (editable, sin fórmula)

-- 482 (id 57, fórmula '429'), 483 (id 59, sin fórmula), 485 (id 61, '482-484') y
-- 499 (id 58, '483+484') ya están correctos: no se tocan.

-- 3) Agregar 486 dentro de la sección real (no existía en ningún lado).
INSERT INTO sri_casilleros_etiquetas (casillero_impuesto, seccion, descripcion, orden, indent, bold, tipo, fuente_valor, editable, eliminado, created_at)
SELECT '486', 'LIQUIDACIÓN DEL IVA', 'Mes en que se paga el IVA de transferencias a crédito de este mes', 22, 0, false, 'valor', 'documentos', true, false, now()
WHERE NOT EXISTS (SELECT 1 FROM sri_casilleros_etiquetas WHERE casillero_impuesto = '486' AND eliminado = false);

COMMIT;
