-- ============================================================================
-- Declaración de IVA (F104) — corrección de fórmulas del valor a pagar
-- ----------------------------------------------------------------------------
-- Acompaña al cambio de código del 2026-09-25 en DeclaracionIvaService (el 484,
-- el 615 y el 617 ahora los calcula el sistema). Corrige tres cosas de la
-- configuración de /config/sri-casilleros-etiquetas:
--
-- 1. 615 y 617 (saldo de crédito tributario para el próximo mes): se QUITAN sus
--    fórmulas. Las que había (615 = 602, 617 = (615/615)*609) perdían crédito:
--    no sumaban el saldo del mes anterior (605/606) y dejaban el 617 en 0
--    cuando sobraban retenciones. El código ya las ignora; esto solo evita que
--    la pantalla de configuración muestre una fórmula que no se aplica.
--
-- 2. 409 y 419 (total ventas y otras operaciones): sumaban solo las ventas
--    gravadas con tarifa distinta de cero. Como el 419 es el denominador del
--    factor de proporcionalidad (563 = ventas con derecho a crédito / 419), el
--    factor salía mayor a 1 con exportaciones o ventas 0 % con derecho a
--    crédito (el 564 declaraba más crédito del real) y nunca bajaba de 1 con
--    ventas 0 % sin derecho (413/414). Ahora suman todas las ventas.
--
-- 3. Casillero 525 repetido en dos filas de ADQUISICIONES: se da de baja
--    (lógica) la fila repetida y queda la primera. Las fórmulas lo contaban una
--    sola vez; solo se veía duplicado en el formulario.
--
-- Tabla GLOBAL de configuración: no lleva id_empresa (CLAUDE.md §4).
-- Ejecutar completo en pgAdmin. Idempotente.
-- ============================================================================

BEGIN;

-- 1. 615 / 617 sin fórmula (en la columna donde esté el casillero).
UPDATE sri_casilleros_etiquetas
   SET formula_bruto = '', updated_at = CURRENT_TIMESTAMP
 WHERE eliminado = FALSE AND casillero_bruto IN ('615', '617') AND COALESCE(formula_bruto, '') <> '';

UPDATE sri_casilleros_etiquetas
   SET formula_neto = '', updated_at = CURRENT_TIMESTAMP
 WHERE eliminado = FALSE AND casillero_neto IN ('615', '617') AND COALESCE(formula_neto, '') <> '';

UPDATE sri_casilleros_etiquetas
   SET formula_impuesto = '', updated_at = CURRENT_TIMESTAMP
 WHERE eliminado = FALSE AND casillero_impuesto IN ('615', '617') AND COALESCE(formula_impuesto, '') <> '';

-- 2. Totales de ventas: todas las ventas (gravadas, 0 % con y sin derecho a crédito,
--    activos fijos y exportaciones).
UPDATE sri_casilleros_etiquetas
   SET formula_bruto = '401+402+403+404+405+406+407+408+410+425', updated_at = CURRENT_TIMESTAMP
 WHERE eliminado = FALSE AND casillero_bruto = '409'
   AND COALESCE(formula_bruto, '') <> '401+402+403+404+405+406+407+408+410+425';

UPDATE sri_casilleros_etiquetas
   SET formula_neto = '411+412+413+414+415+416+417+418+420+435', updated_at = CURRENT_TIMESTAMP
 WHERE eliminado = FALSE AND casillero_neto = '419'
   AND COALESCE(formula_neto, '') <> '411+412+413+414+415+416+417+418+420+435';

-- 3. 525 repetido: queda la fila de menor orden (y menor id); las demás, de baja.
UPDATE sri_casilleros_etiquetas x
   SET eliminado = TRUE, deleted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
 WHERE x.eliminado = FALSE
   AND x.casillero_impuesto = '525'
   AND EXISTS (
        SELECT 1 FROM sri_casilleros_etiquetas y
         WHERE y.eliminado = FALSE
           AND y.casillero_impuesto = '525'
           AND (y.orden, y.id) < (x.orden, x.id)
   );

COMMIT;

-- ----------------------------------------------------------------------------
-- Verificación: 615/617 sin fórmula, 409/419 con la nueva, un solo 525.
-- ----------------------------------------------------------------------------
SELECT seccion, orden, id,
       casillero_bruto, formula_bruto,
       casillero_neto, formula_neto,
       casillero_impuesto, formula_impuesto
  FROM sri_casilleros_etiquetas
 WHERE eliminado = FALSE
   AND ('615' IN (casillero_bruto, casillero_neto, casillero_impuesto)
     OR '617' IN (casillero_bruto, casillero_neto, casillero_impuesto)
     OR casillero_bruto = '409'
     OR casillero_neto = '419'
     OR casillero_impuesto IN ('525', '563', '564'))
 ORDER BY orden, id;
