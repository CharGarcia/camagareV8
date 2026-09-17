-- ============================================================================
-- REPARACIÓN · Consignaciones de Venta editadas con salidas anteriores vigentes
-- ----------------------------------------------------------------------------
-- Problema (corregido en el código el 16-09-2026):
--   Al editar una consignación se registraba una entrada de reverso SIN costo
--   (EDICION_CONSIGNACION_VENTA) y las salidas anteriores (CONSIGNACION_VENTA)
--   quedaban vigentes junto a las nuevas. Efectos:
--     · el asiento de la consignación suma las salidas viejas y las nuevas
--       (costo duplicado);
--     · la entrada a costo 0 baja el costo promedio de esos productos.
--   El stock total NO está mal: la salida vieja y su reverso se cancelan.
--
-- Qué hace este script:
--   Anula (eliminado = true) las salidas anteriores y sus reversos, SOLO en las
--   consignaciones donde se cancelan exactamente por producto y bodega (lo mismo
--   que mostraba la columna "cuadra" del diagnóstico). Así quedan vigentes solo
--   las salidas de la última versión de cada consignación: el stock no cambia, el
--   costo promedio deja de estar diluido y el asiento puede regenerarse bien.
--   Cada movimiento anulado lleva en observaciones la marca
--   «— ANULADO (corrección edición de consignación 16-09-2026)».
--
-- Toca datos: SÍ (inventario_kardex: eliminado, deleted_at, observaciones).
-- Reversible: sí, con el bloque "REVERTIR" del final (usa la marca).
--
-- Orden recomendado:
--   1. Desplegar el código corregido (si no, cada nueva edición vuelve a generar
--      el problema; este script se puede volver a ejecutar sin riesgo). Si alguien
--      edita o elimina una de estas consignaciones entre el despliegue y este
--      script, el código nuevo ya la deja bien (anula salidas y reversos) y el
--      script simplemente deja de listarla.
--   2. Ejecutar el PASO 1 (vista previa, solo lectura) y revisar. En pgAdmin:
--      seleccionar el bloque completo, desde su WITH hasta el punto y coma, y F5.
--      (Pegar TODO el archivo y F5 ejecutaría también el PASO 2 sin revisar.)
--   3. Ejecutar el PASO 2 (corrección) de la misma forma: el bloque entero, desde
--      su WITH hasta el punto y coma del UPDATE.
--   4. Ejecutar la COMPROBACIÓN (quitarle los "-- "): no debe devolver filas.
--   5. Regenerar los asientos de las consignaciones que tienen asiento (empresa 33
--      en el diagnóstico del 16-09-2026): Contabilidad → Auditoría Contable →
--      Regenerar asientos, origen Consignaciones de venta, rango de fechas que
--      cubra esos documentos. Solo regenera períodos abiertos.
-- ============================================================================


-- ============================================================================
-- PASO 1 · VISTA PREVIA (solo lectura): consignaciones y movimientos a anular
-- ============================================================================
WITH ultimo_reverso AS (
    SELECT id_empresa, referencia_id AS id_consignacion, MAX(id) AS id_ultimo
      FROM inventario_kardex
     WHERE referencia_tipo = 'EDICION_CONSIGNACION_VENTA' AND eliminado = false
     GROUP BY id_empresa, referencia_id
),
candidatos AS (
    SELECT k.id, k.id_empresa, u.id_consignacion, k.id_producto, k.id_bodega, k.referencia_tipo,
           k.cantidad, k.costo_total
      FROM ultimo_reverso u
      JOIN inventario_kardex k
        ON k.id_empresa = u.id_empresa AND k.referencia_id = u.id_consignacion AND k.eliminado = false
       AND ((k.referencia_tipo = 'CONSIGNACION_VENTA' AND k.tipo_movimiento = 'salida' AND k.id < u.id_ultimo)
         OR  k.referencia_tipo = 'EDICION_CONSIGNACION_VENTA')
),
cuadran AS (
    SELECT id_empresa, id_consignacion
      FROM (SELECT id_empresa, id_consignacion, id_producto, id_bodega, SUM(cantidad) AS neto
              FROM candidatos GROUP BY id_empresa, id_consignacion, id_producto, id_bodega) t
     GROUP BY id_empresa, id_consignacion
    HAVING BOOL_AND(neto = 0)
)
SELECT c.id_empresa, c.id_consignacion, cv.serie || '-' || cv.secuencial AS numero, cv.fecha_emision,
       COUNT(*) FILTER (WHERE c.referencia_tipo = 'CONSIGNACION_VENTA')         AS salidas_a_anular,
       COUNT(*) FILTER (WHERE c.referencia_tipo = 'EDICION_CONSIGNACION_VENTA') AS reversos_a_anular,
       SUM(c.costo_total) FILTER (WHERE c.referencia_tipo = 'CONSIGNACION_VENTA') AS costo_de_mas_en_asiento,
       (q.id_consignacion IS NOT NULL) AS se_corrige,
       cv.id_asiento_contable IS NOT NULL AS tiene_asiento
  FROM candidatos c
  JOIN consignaciones_ventas cv ON cv.id = c.id_consignacion
  LEFT JOIN cuadran q ON q.id_empresa = c.id_empresa AND q.id_consignacion = c.id_consignacion
 GROUP BY c.id_empresa, c.id_consignacion, cv.serie, cv.secuencial, cv.fecha_emision, q.id_consignacion, cv.id_asiento_contable
 ORDER BY c.id_empresa, cv.fecha_emision;


-- ============================================================================
-- PASO 2 · CORRECCIÓN
-- ============================================================================
WITH ultimo_reverso AS (
    SELECT id_empresa, referencia_id AS id_consignacion, MAX(id) AS id_ultimo
      FROM inventario_kardex
     WHERE referencia_tipo = 'EDICION_CONSIGNACION_VENTA' AND eliminado = false
     GROUP BY id_empresa, referencia_id
),
candidatos AS (
    SELECT k.id, k.id_empresa, u.id_consignacion, k.id_producto, k.id_bodega, k.cantidad
      FROM ultimo_reverso u
      JOIN inventario_kardex k
        ON k.id_empresa = u.id_empresa AND k.referencia_id = u.id_consignacion AND k.eliminado = false
       AND ((k.referencia_tipo = 'CONSIGNACION_VENTA' AND k.tipo_movimiento = 'salida' AND k.id < u.id_ultimo)
         OR  k.referencia_tipo = 'EDICION_CONSIGNACION_VENTA')
),
cuadran AS (
    SELECT id_empresa, id_consignacion
      FROM (SELECT id_empresa, id_consignacion, id_producto, id_bodega, SUM(cantidad) AS neto
              FROM candidatos GROUP BY id_empresa, id_consignacion, id_producto, id_bodega) t
     GROUP BY id_empresa, id_consignacion
    HAVING BOOL_AND(neto = 0)
)
UPDATE inventario_kardex k
   SET eliminado     = true,
       deleted_at    = CURRENT_TIMESTAMP,
       observaciones = COALESCE(NULLIF(k.observaciones, ''), 'Movimiento')
                       || ' — ANULADO (corrección edición de consignación 16-09-2026)'
  FROM candidatos c
  JOIN cuadran q ON q.id_empresa = c.id_empresa AND q.id_consignacion = c.id_consignacion
 WHERE k.id = c.id;


-- ============================================================================
-- COMPROBACIÓN (pegar después del PASO 2): no debe devolver filas
-- ============================================================================
-- SELECT u.id_empresa, u.id_consignacion
--   FROM (SELECT id_empresa, referencia_id AS id_consignacion, MAX(id) AS id_ultimo
--           FROM inventario_kardex
--          WHERE referencia_tipo = 'EDICION_CONSIGNACION_VENTA' AND eliminado = false
--          GROUP BY id_empresa, referencia_id) u
--   JOIN inventario_kardex k
--     ON k.id_empresa = u.id_empresa AND k.referencia_id = u.id_consignacion AND k.eliminado = false
--    AND k.referencia_tipo = 'CONSIGNACION_VENTA' AND k.tipo_movimiento = 'salida' AND k.id < u.id_ultimo
--  GROUP BY u.id_empresa, u.id_consignacion;


-- ============================================================================
-- REVERTIR (solo si hiciera falta deshacer el PASO 2)
-- ============================================================================
-- UPDATE inventario_kardex
--    SET eliminado = false, deleted_at = NULL,
--        observaciones = NULLIF(REPLACE(observaciones, ' — ANULADO (corrección edición de consignación 16-09-2026)', ''), 'Movimiento')
--  WHERE eliminado = true
--    AND observaciones LIKE '%— ANULADO (corrección edición de consignación 16-09-2026)';
