-- =====================================================================================
-- Blindaje: la base rechaza por sí misma un número repetido en los derivados de
-- consignaciones.
--   · retornos_cv             → Retornos de Consignaciones
--   · consignaciones_facturas → Facturación de Consignaciones
--   · cambios_producto_cv     → Cambios de Producto
--
-- Hermano de 20260914_unique_secuencial_consignaciones_ventas.sql. Los tres módulos
-- guardaban el secuencial que mandaba el navegador (la vista previa del modal), así que dos
-- formularios abiertos a la vez tomaban el mismo número; el código ya no confía en ese valor
-- (reservarNumero() lo recalcula dentro de la transacción, con el advisory lock de
-- SecuencialService) y estos índices son la última línea de defensa.
--
-- ORDEN DE EJECUCIÓN:
--   1. database/diagnosticos/20260914_duplicados_consignaciones_derivados.sql  ← ver qué hay
--   2. Si hay documentos activos SIN id_punto_emision (migrados), primero
--      database/migrations/20260827_backfill_series_documentos_migrados.sql: mientras estén
--      sin punto son invisibles para el generador y este puede repartir sus números otra vez.
--   3. Repetir el diagnóstico y resolver a mano los choques que queden (renumerar el
--      documento más reciente al final de la serie; el número se conserva en el más antiguo,
--      que es el que ya está impreso/entregado).
--   4. Este script.
--
-- El secuencial se indexa SIN los ceros de relleno: '1' y '000000001' son el mismo número
-- para el generador (que compara con CAST(secuencial AS BIGINT)) y deben serlo también aquí.
-- No se usa LPAD porque LPAD TRUNCA lo que exceda el largo pedido.
--
-- Solo cubren eliminado = false (un documento borrado libera su número, que es lo que hace
-- SecuencialService al detectar huecos) y id_punto_emision IS NOT NULL (ver paso 2).
-- =====================================================================================

-- Comprobación previa: si quedan duplicados, aborta con un mensaje que dice qué hacer, en vez
-- del error críptico del índice.
DO $$
DECLARE
    dup_ret INTEGER;
    dup_fac INTEGER;
    dup_cam INTEGER;
BEGIN
    SELECT COUNT(*) INTO dup_ret FROM (
        SELECT 1 FROM retornos_cv
         WHERE eliminado = false AND id_punto_emision IS NOT NULL
         GROUP BY id_empresa, id_punto_emision, regexp_replace(TRIM(secuencial),'^0+',''), COALESCE(tipo_ambiente,'1')
        HAVING COUNT(*) > 1) x;

    SELECT COUNT(*) INTO dup_fac FROM (
        SELECT 1 FROM consignaciones_facturas
         WHERE eliminado = false AND id_punto_emision IS NOT NULL
         GROUP BY id_empresa, id_punto_emision, regexp_replace(TRIM(secuencial),'^0+',''), COALESCE(tipo_ambiente,'1')
        HAVING COUNT(*) > 1) x;

    SELECT COUNT(*) INTO dup_cam FROM (
        SELECT 1 FROM cambios_producto_cv
         WHERE eliminado = false AND id_punto_emision IS NOT NULL
         GROUP BY id_empresa, id_punto_emision, regexp_replace(TRIM(secuencial),'^0+',''), COALESCE(tipo_ambiente,'1')
        HAVING COUNT(*) > 1) x;

    IF dup_ret > 0 OR dup_fac > 0 OR dup_cam > 0 THEN
        RAISE EXCEPTION
            'Todavía hay números repetidos (retornos: %, facturación de consignaciones: %, cambios de producto: %). Ejecute database/diagnosticos/20260914_duplicados_consignaciones_derivados.sql para ver cuáles y resuélvalos antes de crear los índices.',
            dup_ret, dup_fac, dup_cam;
    END IF;
END $$;

CREATE UNIQUE INDEX IF NOT EXISTS uq_retornos_cv_secuencial_activo
    ON retornos_cv (
        id_empresa,
        id_punto_emision,
        regexp_replace(TRIM(secuencial), '^0+', ''),
        COALESCE(tipo_ambiente, '1')
    )
 WHERE eliminado = false AND id_punto_emision IS NOT NULL;

CREATE UNIQUE INDEX IF NOT EXISTS uq_consignaciones_facturas_secuencial_activo
    ON consignaciones_facturas (
        id_empresa,
        id_punto_emision,
        regexp_replace(TRIM(secuencial), '^0+', ''),
        COALESCE(tipo_ambiente, '1')
    )
 WHERE eliminado = false AND id_punto_emision IS NOT NULL;

CREATE UNIQUE INDEX IF NOT EXISTS uq_cambios_producto_cv_secuencial_activo
    ON cambios_producto_cv (
        id_empresa,
        id_punto_emision,
        regexp_replace(TRIM(secuencial), '^0+', ''),
        COALESCE(tipo_ambiente, '1')
    )
 WHERE eliminado = false AND id_punto_emision IS NOT NULL;
