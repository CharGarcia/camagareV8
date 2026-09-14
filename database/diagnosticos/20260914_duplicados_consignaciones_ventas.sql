-- =====================================================================================
-- Diagnóstico (SOLO LECTURA): números de consignación de venta repetidos.
--
-- Se ejecuta ANTES de 20260914_unique_secuencial_consignaciones_ventas.sql para saber si
-- hay choques históricos y de qué tipo son. No modifica nada.
--
-- El secuencial se compara SIN los ceros de relleno: '1' y '000000001' son el mismo número
-- para el generador (que trabaja con CAST(secuencial AS BIGINT)), aunque como texto no lo
-- parezcan. La columna `origen` dice si cada consignación es nativa o migrada del sistema
-- anterior — un grupo MIXTO (migrado + nativo) significa que el migrado estaba invisible
-- para el generador por no tener id_punto_emision, y el sistema repartió su número otra vez.
-- Ese caso lo resuelve database/migrations/20260827_backfill_series_documentos_migrados.sql.
-- =====================================================================================

-- ── 1. Duplicados dentro de la MISMA serie (mismo punto de emisión) ──────────────────
-- Son los que impiden crear el índice único.
WITH dup AS (
    SELECT id_empresa, id_punto_emision, COALESCE(tipo_ambiente, '1') AS amb,
           regexp_replace(TRIM(secuencial), '^0+', '') AS sec_norm
      FROM consignaciones_ventas
     WHERE eliminado = false AND id_punto_emision IS NOT NULL
     GROUP BY 1, 2, 3, 4
    HAVING COUNT(*) > 1
)
SELECT 'Duplicado en la misma serie' AS reporte,
       cv.id_empresa, cv.serie, cv.secuencial, cv.id_punto_emision,
       COALESCE(cv.tipo_ambiente, '1') AS tipo_ambiente,
       cv.id, cv.fecha_emision, cv.estado, cv.total,
       CASE WHEN mm.id IS NULL THEN 'nativo'
            WHEN mm.vinculado THEN 'nativo (enlazado por migración)'
            ELSE 'migrado' END AS origen,
       cv.created_at, cv.created_by
  FROM consignaciones_ventas cv
  JOIN dup d ON d.id_empresa = cv.id_empresa
            AND d.id_punto_emision = cv.id_punto_emision
            AND d.amb = COALESCE(cv.tipo_ambiente, '1')
            AND d.sec_norm = regexp_replace(TRIM(cv.secuencial), '^0+', '')
  LEFT JOIN migracion_mysql_map mm
         ON mm.id_empresa = cv.id_empresa AND mm.entidad = 'consignaciones' AND mm.id_destino = cv.id
 WHERE cv.eliminado = false
 ORDER BY cv.id_empresa, cv.serie, regexp_replace(TRIM(cv.secuencial), '^0+', '')::bigint, cv.id;

-- ── 2. Duplicados por SERIE DE TEXTO (incluye los que aún no tienen punto de emisión) ─
-- El índice único no los ve (su id_punto_emision es NULL) pero el usuario sí: en pantalla
-- aparecen dos consignaciones con el mismo "001-001-000000010". Se corrigen dándoles su
-- punto de emisión con 20260827_backfill_series_documentos_migrados.sql y repitiendo este
-- diagnóstico: los que sigan chocando pasan a ser del caso 1.
SELECT 'Duplicado por serie (ver punto de emisión)' AS reporte,
       cv.id_empresa, cv.serie,
       regexp_replace(TRIM(cv.secuencial), '^0+', '') AS secuencial_normalizado,
       COUNT(*) AS veces,
       COUNT(*) FILTER (WHERE cv.id_punto_emision IS NULL) AS sin_punto_emision,
       STRING_AGG(cv.id::text, ', ' ORDER BY cv.id) AS ids
  FROM consignaciones_ventas cv
 WHERE cv.eliminado = false
 GROUP BY 1, 2, 3, 4
HAVING COUNT(*) > 1
 ORDER BY cv.id_empresa, cv.serie, 3;

-- ── 3. Consignaciones activas SIN punto de emisión (invisibles para el generador) ─────
-- Mientras estén así, el generador puede volver a repartir sus números a documentos nuevos.
SELECT 'Sin punto de emisión' AS reporte,
       id_empresa, serie, COUNT(*) AS consignaciones,
       MIN(fecha_emision) AS desde, MAX(fecha_emision) AS hasta
  FROM consignaciones_ventas
 WHERE eliminado = false AND id_punto_emision IS NULL
 GROUP BY id_empresa, serie
 ORDER BY id_empresa, serie;
