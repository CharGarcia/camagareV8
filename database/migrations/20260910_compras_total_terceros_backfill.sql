-- ============================================================================
-- Valores de terceros en compras: columna + backfill de las planillas cargadas
-- Fecha: 2026-09-10
--
-- CONTEXTO
--   Las planillas de luz y agua recaudan por cuenta de terceros rubros que NO
--   forman parte del <importeTotal> del comprobante (contribución bomberos, tasa
--   de recolección de basura…), pero que sí se pagan en la misma transferencia.
--   Se totalizan en compras_cabecera.total_terceros y suman al SALDO POR PAGAR,
--   nunca a importe_total (que es el valor declarado al SRI, base del ATS y de
--   la declaración de IVA).
--
--   Las compras registradas ANTES del 27-08-2026 quedaron con total_terceros = 0,
--   así que su saldo sale corto en esos centavos. Este script rellena ese valor
--   reproduciendo en SQL el detector App\Helpers\RubrosTerceros:
--     · toma los campos de compras_adicional cuyo valor es numérico,
--     · si el emisor declara un campo TOTAL … TERCEROS, ese total manda,
--     · si no lo declara, suma los rubros reconocidos por su nombre.
--
-- IDEMPOTENTE y NO destructivo: solo escribe compras con total_terceros = 0 y un
-- total detectado mayor que cero. Nunca toca importe_total.
--
-- CÓMO EJECUTARLO EN pgAdmin
--   Ejecute el PASO 1, luego el PASO 2 (revisar qué se va a actualizar), luego el
--   PASO 3 y por último el PASO 4. También puede ejecutar el archivo completo de
--   una sola vez: solo verá el resultado de la última consulta.
-- ============================================================================


-- ── PASO 1 · Columna (idempotente; ya venía en 20260827_compras_total_terceros.sql)
ALTER TABLE compras_cabecera
    ADD COLUMN IF NOT EXISTS total_terceros NUMERIC(12,2) NOT NULL DEFAULT 0;

COMMENT ON COLUMN compras_cabecera.total_terceros IS
    'Valores recaudados por cuenta de terceros (bomberos, tasa de basura…) declarados en infoAdicional. Se suman al pago y al saldo de CxP, NO al importe_total ni a las bases de IVA/ATS.';


-- ── PASO 2 · Control (solo lectura): qué compras ganarían un total_terceros
WITH campos AS (
    SELECT a.id_compra,
           regexp_replace(
               upper(translate(btrim(a.nombre), 'áéíóúüñÁÉÍÓÚÜÑ', 'aeiouunAEIOUUN')),
               '\s+', ' ', 'g'
           ) AS nombre_norm,
           CASE
               WHEN btrim(a.valor) ~ '^-?\d{1,3}(\.\d{3})*,\d{1,2}$'
                    THEN replace(replace(btrim(a.valor), '.', ''), ',', '.')
               WHEN btrim(a.valor) ~ '^-?\d+,\d{1,2}$'
                    THEN replace(btrim(a.valor), ',', '.')
               ELSE btrim(a.valor)
           END AS valor_norm
    FROM compras_adicional a
),
montos AS (
    SELECT id_compra, nombre_norm,
           CASE WHEN valor_norm ~ '^-?\d+(\.\d+)?$' THEN round(valor_norm::numeric, 2) END AS monto
    FROM campos
),
clasificados AS (
    SELECT id_compra, monto,
           -- El TOTAL que declara el emisor no es un rubro más: es la suma ya hecha
           (nombre_norm LIKE '%TOTAL FORMA DE PAGO TERCEROS%'
         OR nombre_norm LIKE '%TOTAL TERCEROS%'
         OR nombre_norm LIKE '%TOTAL VALORES DE TERCEROS%'
         OR nombre_norm LIKE '%TOTAL RECAUDACION TERCEROS%')          AS es_total,
           (nombre_norm LIKE '%BOMBERO%'
         OR nombre_norm LIKE '%BASURA%'
         OR nombre_norm LIKE '%RECOLECCION%'
         OR nombre_norm LIKE '%SEGURIDAD CIUDADANA%'
         OR nombre_norm LIKE '%ALUMBRADO PUBLICO GENERAL%'
         OR nombre_norm LIKE '%TERCEROS%')                            AS es_rubro
    FROM montos
    WHERE monto IS NOT NULL
),
terceros AS (
    SELECT id_compra,
           MAX(monto) FILTER (WHERE es_total)                               AS total_declarado,
           COALESCE(SUM(monto) FILTER (WHERE es_rubro AND NOT es_total), 0) AS suma_rubros
    FROM clasificados
    GROUP BY id_compra
)
SELECT c.id_empresa,
       c.id                                                                              AS id_compra,
       CONCAT(c.establecimiento_prov,'-',c.punto_emision_prov,'-',c.secuencial_prov)      AS numero,
       c.fecha_emision,
       c.importe_total                                                                   AS declarado_sri,
       COALESCE(t.total_declarado, t.suma_rubros)                                        AS terceros,
       c.importe_total + COALESCE(t.total_declarado, t.suma_rubros)                      AS total_a_pagar
FROM compras_cabecera c
JOIN terceros t ON t.id_compra = c.id
WHERE c.eliminado = false
  AND COALESCE(c.total_terceros, 0) = 0
  AND COALESCE(t.total_declarado, t.suma_rubros) > 0
  -- AND c.id_empresa = 8        -- descomentar para limitar a una empresa
ORDER BY c.id_empresa, c.id;


-- ── PASO 3 · Backfill (escribe). Mismo criterio que el control de arriba.
WITH campos AS (
    SELECT a.id_compra,
           regexp_replace(
               upper(translate(btrim(a.nombre), 'áéíóúüñÁÉÍÓÚÜÑ', 'aeiouunAEIOUUN')),
               '\s+', ' ', 'g'
           ) AS nombre_norm,
           CASE
               WHEN btrim(a.valor) ~ '^-?\d{1,3}(\.\d{3})*,\d{1,2}$'
                    THEN replace(replace(btrim(a.valor), '.', ''), ',', '.')
               WHEN btrim(a.valor) ~ '^-?\d+,\d{1,2}$'
                    THEN replace(btrim(a.valor), ',', '.')
               ELSE btrim(a.valor)
           END AS valor_norm
    FROM compras_adicional a
),
montos AS (
    SELECT id_compra, nombre_norm,
           CASE WHEN valor_norm ~ '^-?\d+(\.\d+)?$' THEN round(valor_norm::numeric, 2) END AS monto
    FROM campos
),
clasificados AS (
    SELECT id_compra, monto,
           (nombre_norm LIKE '%TOTAL FORMA DE PAGO TERCEROS%'
         OR nombre_norm LIKE '%TOTAL TERCEROS%'
         OR nombre_norm LIKE '%TOTAL VALORES DE TERCEROS%'
         OR nombre_norm LIKE '%TOTAL RECAUDACION TERCEROS%')          AS es_total,
           (nombre_norm LIKE '%BOMBERO%'
         OR nombre_norm LIKE '%BASURA%'
         OR nombre_norm LIKE '%RECOLECCION%'
         OR nombre_norm LIKE '%SEGURIDAD CIUDADANA%'
         OR nombre_norm LIKE '%ALUMBRADO PUBLICO GENERAL%'
         OR nombre_norm LIKE '%TERCEROS%')                            AS es_rubro
    FROM montos
    WHERE monto IS NOT NULL
),
terceros AS (
    SELECT id_compra,
           MAX(monto) FILTER (WHERE es_total)                               AS total_declarado,
           COALESCE(SUM(monto) FILTER (WHERE es_rubro AND NOT es_total), 0) AS suma_rubros
    FROM clasificados
    GROUP BY id_compra
)
UPDATE compras_cabecera c
SET total_terceros = COALESCE(t.total_declarado, t.suma_rubros)
FROM terceros t
WHERE t.id_compra = c.id
  AND c.eliminado = false
  AND COALESCE(c.total_terceros, 0) = 0
  AND COALESCE(t.total_declarado, t.suma_rubros) > 0;
  -- AND c.id_empresa = 8        -- descomentar para limitar a una empresa


-- ── PASO 4 · Verificación: compras que quedaron con valores de terceros
SELECT c.id_empresa,
       COUNT(*)                                   AS compras_con_terceros,
       SUM(c.total_terceros)                      AS total_terceros,
       MIN(c.fecha_emision)                       AS desde,
       MAX(c.fecha_emision)                       AS hasta
FROM compras_cabecera c
WHERE c.eliminado = false
  AND COALESCE(c.total_terceros, 0) > 0
GROUP BY c.id_empresa
ORDER BY c.id_empresa;
