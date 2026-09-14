-- =====================================================================================
-- Blindaje: la base rechaza por sí misma una consignación de venta con un número repetido.
--
-- Hasta ahora nada lo impedía. El secuencial que se guardaba era el que mandaba el navegador
-- (la vista previa que carga getSecuencialAjax al abrir el modal), sin candado ni recálculo:
-- dos modales abiertos a la vez guardaban el mismo número. El código ya no confía en ese
-- valor (ConsignacionVentaService::reservarNumero lo recalcula dentro de la transacción, con
-- el advisory lock de SecuencialService); este índice es la última línea de defensa, igual
-- que uix_ventas_secuencial_activo en facturas o uq_pedidos_secuencial en pedidos.
--
-- ORDEN DE EJECUCIÓN:
--   1. database/diagnosticos/20260914_duplicados_consignaciones_ventas.sql  ← ver qué hay
--   2. Si hay consignaciones activas SIN id_punto_emision (migradas), primero
--      database/migrations/20260827_backfill_series_documentos_migrados.sql, que les asigna
--      el punto real de su serie. Mientras estén sin punto son invisibles para el generador
--      de secuenciales y este puede repartir sus números otra vez.
--   3. Repetir el diagnóstico y resolver a mano los choques que queden (renumerar la
--      consignación más reciente al final de la serie; el número se conserva en la más
--      antigua, que es la que ya está impresa/entregada).
--   4. Este script.
--
-- El secuencial se indexa SIN los ceros de relleno: '1' y '000000001' son el mismo número
-- para el generador (que compara con CAST(secuencial AS BIGINT)) y deben serlo también aquí;
-- un índice sobre el texto crudo dejaría pasar ese duplicado. No se usa LPAD porque LPAD
-- TRUNCA lo que exceda el largo pedido.
--
-- Solo cubre eliminado = false: una consignación borrada libera su número, que es justo lo
-- que hace SecuencialService al detectar huecos. Y solo id_punto_emision IS NOT NULL: los
-- documentos sin punto no participan de la numeración por serie (ver paso 2).
-- =====================================================================================

-- Comprobación previa: si quedan duplicados, aborta con un mensaje que dice qué hacer, en vez
-- del error críptico del índice.
DO $$
DECLARE
    dups INTEGER;
BEGIN
    SELECT COUNT(*) INTO dups FROM (
        SELECT 1
          FROM consignaciones_ventas
         WHERE eliminado = false AND id_punto_emision IS NOT NULL
         GROUP BY id_empresa, id_punto_emision,
                  regexp_replace(TRIM(secuencial), '^0+', ''),
                  COALESCE(tipo_ambiente, '1')
        HAVING COUNT(*) > 1) x;

    IF dups > 0 THEN
        RAISE EXCEPTION
            'Todavía hay % número(s) de consignación repetido(s). Ejecute database/diagnosticos/20260914_duplicados_consignaciones_ventas.sql para ver cuáles y resuélvalos antes de crear el índice.',
            dups;
    END IF;
END $$;

CREATE UNIQUE INDEX IF NOT EXISTS uq_consignaciones_ventas_secuencial_activo
    ON consignaciones_ventas (
        id_empresa,
        id_punto_emision,
        regexp_replace(TRIM(secuencial), '^0+', ''),
        COALESCE(tipo_ambiente, '1')
    )
 WHERE eliminado = false AND id_punto_emision IS NOT NULL;
