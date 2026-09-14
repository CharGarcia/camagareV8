-- =====================================================================================
-- Diagnóstico (SOLO LECTURA): números repetidos en los derivados de consignaciones.
--   · retornos_cv             → Retornos de Consignaciones
--   · consignaciones_facturas → Facturación de Consignaciones
--   · cambios_producto_cv     → Cambios de Producto
--
-- Hermano de 20260914_duplicados_consignaciones_ventas.sql (consignaciones de venta).
-- Se ejecuta ANTES de 20260914_unique_secuencial_consignaciones_derivados.sql. No modifica nada.
--
-- El secuencial se compara SIN los ceros de relleno: '1' y '000000001' son el mismo número
-- para el generador (que trabaja con CAST(secuencial AS BIGINT)), aunque como texto no lo
-- parezcan.
-- =====================================================================================

-- ── 1. Duplicados dentro de la MISMA serie (mismo punto de emisión) ──────────────────
-- Son los que impiden crear los índices únicos.
WITH docs AS (
    SELECT 'retornos_cv'             AS tabla, id, id_empresa, id_punto_emision, serie, secuencial,
           COALESCE(tipo_ambiente,'1') AS amb, fecha_retorno::date AS fecha, estado, eliminado
      FROM retornos_cv
    UNION ALL
    SELECT 'consignaciones_facturas', id, id_empresa, id_punto_emision, serie, secuencial,
           COALESCE(tipo_ambiente,'1'), fecha_emision::date, estado, eliminado
      FROM consignaciones_facturas
    UNION ALL
    SELECT 'cambios_producto_cv',     id, id_empresa, id_punto_emision, serie, secuencial,
           COALESCE(tipo_ambiente,'1'), fecha_cambio::date, estado, eliminado
      FROM cambios_producto_cv
),
dup AS (
    SELECT tabla, id_empresa, id_punto_emision, amb,
           regexp_replace(TRIM(secuencial), '^0+', '') AS sec_norm
      FROM docs
     WHERE eliminado = false AND id_punto_emision IS NOT NULL
     GROUP BY 1, 2, 3, 4, 5
    HAVING COUNT(*) > 1
)
SELECT 'Duplicado en la misma serie' AS reporte,
       d.tabla, d.id_empresa, d.serie, d.secuencial, d.id_punto_emision, d.amb AS tipo_ambiente,
       d.id, d.fecha, d.estado
  FROM docs d
  JOIN dup ON dup.tabla = d.tabla
          AND dup.id_empresa = d.id_empresa
          AND dup.id_punto_emision = d.id_punto_emision
          AND dup.amb = d.amb
          AND dup.sec_norm = regexp_replace(TRIM(d.secuencial), '^0+', '')
 WHERE d.eliminado = false
 ORDER BY d.tabla, d.id_empresa, d.serie, regexp_replace(TRIM(d.secuencial), '^0+', '')::bigint, d.id;

-- ── 2. Documentos activos SIN punto de emisión (invisibles para el generador) ─────────
-- Mientras estén así, el generador puede volver a repartir sus números a documentos nuevos.
-- Se les asigna su punto con 20260827_backfill_series_documentos_migrados.sql.
SELECT 'Sin punto de emisión' AS reporte, tabla, id_empresa, serie, COUNT(*) AS documentos
  FROM (
    SELECT 'retornos_cv' AS tabla, id_empresa, serie FROM retornos_cv
     WHERE eliminado = false AND id_punto_emision IS NULL
    UNION ALL
    SELECT 'consignaciones_facturas', id_empresa, serie FROM consignaciones_facturas
     WHERE eliminado = false AND id_punto_emision IS NULL
    UNION ALL
    SELECT 'cambios_producto_cv', id_empresa, serie FROM cambios_producto_cv
     WHERE eliminado = false AND id_punto_emision IS NULL
  ) x
 GROUP BY tabla, id_empresa, serie
 ORDER BY tabla, id_empresa, serie;
