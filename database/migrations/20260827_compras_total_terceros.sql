-- ============================================================================
-- Valores de terceros en facturas de servicios básicos (compras)
-- Fecha: 2026-08-27
--
-- CONTEXTO
--   Las planillas de luz y agua recaudan por cuenta de terceros rubros que NO
--   forman parte del <importeTotal> del comprobante ni de las bases de IVA:
--   contribución al Cuerpo de Bomberos, tasa de recolección de basura, etc.
--   Cada distribuidora los publica como campos libres de <infoAdicional>.
--
--   Ejemplo real (Empresa Eléctrica Quito, factura 001-999-134822969):
--     importeTotal .............................. 66.59
--     CONTRIBUCION BOMBEROS ......................  2.41
--     TASA RECOLECCION BASURA ....................  0.00
--     -> lo que realmente se paga en ventanilla ... 69.00
--
--   Hasta ahora esos campos se guardaban solo como texto en compras_adicional,
--   así que el saldo por pagar quedaba 2.41 corto en cada planilla.
--
-- QUÉ HACE ESTE SCRIPT
--   Agrega compras_cabecera.total_terceros: el monto recaudado para terceros que
--   se suma al pago pero NO al importe_total (que debe seguir siendo el valor
--   declarado al SRI, base del ATS y de la declaración de IVA).
--
--   El desglose no necesita tabla propia: sigue viviendo en compras_adicional y
--   se reconstruye con App\Helpers\RubrosTerceros::detectar().
--
-- IDEMPOTENTE: se puede ejecutar varias veces sin efecto.
-- ============================================================================

ALTER TABLE compras_cabecera
    ADD COLUMN IF NOT EXISTS total_terceros NUMERIC(12,2) NOT NULL DEFAULT 0;

COMMENT ON COLUMN compras_cabecera.total_terceros IS
    'Valores recaudados por cuenta de terceros (bomberos, tasa de basura…) declarados en infoAdicional. Se suman al pago y al saldo de CxP, NO al importe_total ni a las bases de IVA/ATS.';


-- ----------------------------------------------------------------------------
-- BACKFILL (opcional) — recalcula las compras electrónicas ya cargadas.
--
-- Reproduce en SQL el detector de App\Helpers\RubrosTerceros:
--   · toma los campos de compras_adicional cuyo valor es numérico,
--   · descarta los que son el TOTAL ya declarado por el emisor (si no, se
--     contaría dos veces), y
--   · suma los que nombran un rubro de terceros.
-- Ejecutar el SELECT de control primero para ver a cuántas compras afecta.
-- ----------------------------------------------------------------------------

-- Control (solo lectura): compras que ganarían un total_terceros > 0.
-- SELECT c.id, c.secuencial_prov, c.importe_total, SUM(a.valor::numeric) AS terceros
-- FROM compras_cabecera c
-- JOIN compras_adicional a ON a.id_compra = c.id
-- WHERE c.eliminado = false
--   AND a.valor ~ '^\s*-?\d+([.,]\d{1,2})?\s*$'
--   AND upper(translate(a.nombre, 'áéíóúÁÉÍÓÚ', 'aeiouAEIOU')) NOT LIKE 'TOTAL%TERCEROS%'
--   AND (upper(translate(a.nombre, 'áéíóúÁÉÍÓÚ', 'aeiouAEIOU')) LIKE '%BOMBERO%'
--     OR upper(translate(a.nombre, 'áéíóúÁÉÍÓÚ', 'aeiouAEIOU')) LIKE '%BASURA%'
--     OR upper(translate(a.nombre, 'áéíóúÁÉÍÓÚ', 'aeiouAEIOU')) LIKE '%RECOLECCION%')
-- GROUP BY c.id, c.secuencial_prov, c.importe_total
-- HAVING SUM(a.valor::numeric) > 0
-- ORDER BY c.id;

-- Aplicación del backfill (descomentar tras revisar el control):
-- UPDATE compras_cabecera c
-- SET total_terceros = s.terceros
-- FROM (
--     SELECT a.id_compra, SUM(replace(a.valor, ',', '.')::numeric) AS terceros
--     FROM compras_adicional a
--     WHERE a.valor ~ '^\s*-?\d+([.,]\d{1,2})?\s*$'
--       AND upper(translate(a.nombre, 'áéíóúÁÉÍÓÚ', 'aeiouAEIOU')) NOT LIKE 'TOTAL%TERCEROS%'
--       AND (upper(translate(a.nombre, 'áéíóúÁÉÍÓÚ', 'aeiouAEIOU')) LIKE '%BOMBERO%'
--         OR upper(translate(a.nombre, 'áéíóúÁÉÍÓÚ', 'aeiouAEIOU')) LIKE '%BASURA%'
--         OR upper(translate(a.nombre, 'áéíóúÁÉÍÓÚ', 'aeiouAEIOU')) LIKE '%RECOLECCION%')
--     GROUP BY a.id_compra
-- ) s
-- WHERE s.id_compra = c.id
--   AND c.eliminado = false
--   AND c.total_terceros = 0
--   AND s.terceros > 0;
