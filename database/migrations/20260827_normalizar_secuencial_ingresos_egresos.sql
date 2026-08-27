-- =====================================================================================
-- Normalizar el secuencial de ingresos y egresos al formato canónico de 9 dígitos.
--
-- POR QUÉ SEGUÍAN SALIENDO NÚMEROS REPETIDOS DESPUÉS DEL ÍNDICE ÚNICO
--   uq_ingresos_secuencial_activo / uq_egresos_secuencial_activo comparan la columna
--   `secuencial` como TEXTO. Mientras unos flujos guardaban '000000016' y otros '16',
--   Postgres los veía como valores DISTINTOS y el índice dejaba pasar el duplicado — el
--   número visible (numero_ingreso / numero_egreso) sí salía igual en ambos, porque ese
--   se arma siempre con relleno de ceros.
--
--   Los controladores de Ingresos y Egresos ya guardaban el valor formateado. Los que no:
--   los flujos automáticos que crean el documento sin pasar por el modal — cobro con
--   tarjeta al facturar (FacturaVentaService), cobro de suscripciones (SuscripcionesHandler)
--   y cualquier otro que tomara la clave 'secuencial' de SecuencialService (entero pelado)
--   en vez de 'formateado'.
--
--   El generador de números NO se equivocaba: getSecuencialesUsados() los cuenta con
--   CAST(... AS BIGINT) y veía ambos formatos igual. Quien no los veía igual era la
--   barrera que debía impedir el choque.
--
-- CONTRAPARTE EN EL CÓDIGO (desplegar ANTES de correr esto)
--   App\Helpers\SecuencialFormato::normalizar(), aplicado en IngresoRepository (insert y
--   update de cabecera, existeSecuencial) y EgresoRepository (insert, existeSecuencial).
--   Sin ese código, la base se vuelve a ensuciar con el próximo cobro automático.
--
-- ORDEN: este script va DESPUÉS de los tres del 27-08-2026 (backfill de series, reparación
-- de duplicados e índice único). Si el índice único todavía no existe, igual se puede correr.
--
-- IDEMPOTENTE: la segunda pasada no encuentra nada que normalizar.
-- =====================================================================================

BEGIN;

-- -------------------------------------------------------------------------------------
-- 1. Cuántas filas están sin normalizar (solo informativo).
-- -------------------------------------------------------------------------------------
SELECT 'ingresos_cabecera' AS tabla, COUNT(*) AS filas_sin_normalizar
  FROM ingresos_cabecera
 WHERE eliminado = false AND secuencial ~ '^[0-9]+$' AND length(secuencial) <> 9
UNION ALL
SELECT 'egresos_cabecera', COUNT(*)
  FROM egresos_cabecera
 WHERE eliminado = false AND secuencial ~ '^[0-9]+$' AND length(secuencial) <> 9;


-- -------------------------------------------------------------------------------------
-- 2. Resolver los choques que la normalización va a destapar.
--
--    Si '16' y '000000016' conviven en el mismo punto de emisión, son el MISMO número y
--    normalizar sin más violaría el índice único. Aquí se renumera al MÁS NUEVO de cada
--    grupo (el más antiguo conserva su número), mandándolo al final de la numeración de
--    ese punto. El MAX considera también los eliminados, para no reutilizar el número de
--    un documento anulado.
--
--    Se comparan por valor numérico (CAST), que es como los ve el generador.
-- -------------------------------------------------------------------------------------
WITH grupos AS (
    SELECT id,
           id_empresa,
           id_punto_emision,
           tipo_ambiente,
           establecimiento,
           punto_emision,
           ROW_NUMBER() OVER (
               PARTITION BY id_empresa, id_punto_emision, tipo_ambiente, CAST(secuencial AS BIGINT)
               ORDER BY fecha_emision ASC, id ASC
           ) AS orden
      FROM ingresos_cabecera
     WHERE eliminado = false
       AND id_punto_emision IS NOT NULL
       AND secuencial ~ '^[0-9]+$'
),
a_renumerar AS (
    SELECT g.*,
           ROW_NUMBER() OVER (PARTITION BY g.id_punto_emision ORDER BY g.id) AS pos
      FROM grupos g
     WHERE g.orden > 1
),
topes AS (
    SELECT id_punto_emision, MAX(CAST(secuencial AS BIGINT)) AS tope
      FROM ingresos_cabecera
     WHERE id_punto_emision IS NOT NULL AND secuencial ~ '^[0-9]+$'
     GROUP BY id_punto_emision
)
UPDATE ingresos_cabecera i
   SET secuencial     = lpad((t.tope + r.pos)::text, 9, '0'),
       numero_ingreso = lpad(COALESCE(r.establecimiento, '001'), 3, '0') || '-' ||
                        lpad(COALESCE(r.punto_emision, '001'), 3, '0') || '-' ||
                        lpad((t.tope + r.pos)::text, 9, '0'),
       updated_at     = CURRENT_TIMESTAMP
  FROM a_renumerar r
  JOIN topes t ON t.id_punto_emision = r.id_punto_emision
 WHERE i.id = r.id;

WITH grupos AS (
    SELECT id,
           id_empresa,
           id_punto_emision,
           tipo_ambiente,
           establecimiento,
           punto_emision,
           ROW_NUMBER() OVER (
               PARTITION BY id_empresa, id_punto_emision, tipo_ambiente, CAST(secuencial AS BIGINT)
               ORDER BY fecha_emision ASC, id ASC
           ) AS orden
      FROM egresos_cabecera
     WHERE eliminado = false
       AND id_punto_emision IS NOT NULL
       AND secuencial ~ '^[0-9]+$'
),
a_renumerar AS (
    SELECT g.*,
           ROW_NUMBER() OVER (PARTITION BY g.id_punto_emision ORDER BY g.id) AS pos
      FROM grupos g
     WHERE g.orden > 1
),
topes AS (
    SELECT id_punto_emision, MAX(CAST(secuencial AS BIGINT)) AS tope
      FROM egresos_cabecera
     WHERE id_punto_emision IS NOT NULL AND secuencial ~ '^[0-9]+$'
     GROUP BY id_punto_emision
)
UPDATE egresos_cabecera e
   SET secuencial    = lpad((t.tope + r.pos)::text, 9, '0'),
       numero_egreso = lpad(COALESCE(r.establecimiento, '001'), 3, '0') || '-' ||
                       lpad(COALESCE(r.punto_emision, '001'), 3, '0') || '-' ||
                       lpad((t.tope + r.pos)::text, 9, '0'),
       updated_at    = CURRENT_TIMESTAMP
  FROM a_renumerar r
  JOIN topes t ON t.id_punto_emision = r.id_punto_emision
 WHERE e.id = r.id;


-- -------------------------------------------------------------------------------------
-- 3. Normalizar el resto (los que ya no chocan con nadie).
--    Se recompone también el número visible, por si quedó desalineado del secuencial.
-- -------------------------------------------------------------------------------------
UPDATE ingresos_cabecera
   SET secuencial     = lpad(secuencial, 9, '0'),
       numero_ingreso = lpad(COALESCE(establecimiento, '001'), 3, '0') || '-' ||
                        lpad(COALESCE(punto_emision, '001'), 3, '0') || '-' ||
                        lpad(secuencial, 9, '0'),
       updated_at     = CURRENT_TIMESTAMP
 WHERE secuencial ~ '^[0-9]+$'
   AND length(secuencial) < 9;

UPDATE egresos_cabecera
   SET secuencial    = lpad(secuencial, 9, '0'),
       numero_egreso = lpad(COALESCE(establecimiento, '001'), 3, '0') || '-' ||
                       lpad(COALESCE(punto_emision, '001'), 3, '0') || '-' ||
                       lpad(secuencial, 9, '0'),
       updated_at    = CURRENT_TIMESTAMP
 WHERE secuencial ~ '^[0-9]+$'
   AND length(secuencial) < 9;


-- -------------------------------------------------------------------------------------
-- 4. Verificación: después de esto no debe quedar ningún número repetido por VALOR
--    (no solo por texto). Si devuelve filas, revisarlas a mano antes del COMMIT.
-- -------------------------------------------------------------------------------------
SELECT 'ingresos_cabecera' AS tabla, id_empresa, id_punto_emision, tipo_ambiente,
       CAST(secuencial AS BIGINT) AS numero, COUNT(*) AS veces
  FROM ingresos_cabecera
 WHERE eliminado = false AND id_punto_emision IS NOT NULL AND secuencial ~ '^[0-9]+$'
 GROUP BY id_empresa, id_punto_emision, tipo_ambiente, CAST(secuencial AS BIGINT)
HAVING COUNT(*) > 1
UNION ALL
SELECT 'egresos_cabecera', id_empresa, id_punto_emision, tipo_ambiente,
       CAST(secuencial AS BIGINT), COUNT(*)
  FROM egresos_cabecera
 WHERE eliminado = false AND id_punto_emision IS NOT NULL AND secuencial ~ '^[0-9]+$'
 GROUP BY id_empresa, id_punto_emision, tipo_ambiente, CAST(secuencial AS BIGINT)
HAVING COUNT(*) > 1;

-- Los secuenciales NO numéricos (si los hubiera) no se tocan; se listan para revisión.
SELECT 'ingresos_cabecera' AS tabla, id, numero_ingreso AS numero, secuencial
  FROM ingresos_cabecera
 WHERE eliminado = false AND secuencial IS NOT NULL AND secuencial !~ '^[0-9]+$'
UNION ALL
SELECT 'egresos_cabecera', id, numero_egreso, secuencial
  FROM egresos_cabecera
 WHERE eliminado = false AND secuencial IS NOT NULL AND secuencial !~ '^[0-9]+$';

COMMIT;
