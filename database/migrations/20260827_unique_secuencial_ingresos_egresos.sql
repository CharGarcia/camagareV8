-- =====================================================================================
-- Blindaje: la base rechaza por sí misma un número de ingreso/egreso repetido.
--
-- Hasta ahora nada impedía guardar dos documentos con la misma serie y secuencial: la única
-- defensa era una comprobación en PHP (EgresoService::validarSecuencial) que, al no llevar
-- candado, dos peticiones simultáneas pasaban las dos. Este índice cierra esa puerta a nivel
-- de motor, igual que uix_ventas_secuencial_activo en facturas y uq_pedidos_secuencial en
-- pedidos.
--
-- ORDEN DE EJECUCIÓN (importante). Este es el ÚLTIMO de los tres scripts:
--   1. 20260827_backfill_series_documentos_migrados.sql   ← da punto de emisión a los migrados
--   2. 20260827_reparar_duplicados_ingresos_egresos.sql   ← resuelve los choques que eso destapa
--   3. este script
-- Saltarse el paso 2 hace que el CREATE INDEX falle con «Key (...) is duplicated»: al darles
-- serie a los migrados, sus números chocan con los que el sistema repartió de nuevo mientras
-- esos migrados eran invisibles para el generador de secuenciales.
--
-- Solo cubre eliminado = false: un documento borrado libera su número, que es exactamente lo
-- que hace SecuencialService al detectar huecos.
-- =====================================================================================

-- Comprobación previa: si quedan duplicados, aborta con un mensaje que dice qué hacer, en vez
-- del error de índice duplicado que no explica nada.
DO $$
DECLARE
    dup_ing INTEGER;
    dup_egr INTEGER;
BEGIN
    SELECT COUNT(*) INTO dup_ing FROM (
        SELECT 1 FROM ingresos_cabecera
         WHERE eliminado = false AND id_punto_emision IS NOT NULL
         GROUP BY id_empresa, id_punto_emision, secuencial, tipo_ambiente HAVING COUNT(*) > 1) x;

    SELECT COUNT(*) INTO dup_egr FROM (
        SELECT 1 FROM egresos_cabecera
         WHERE eliminado = false AND id_punto_emision IS NOT NULL
         GROUP BY id_empresa, id_punto_emision, secuencial, tipo_ambiente HAVING COUNT(*) > 1) x;

    IF dup_ing > 0 OR dup_egr > 0 THEN
        RAISE EXCEPTION
            'Todavía hay números repetidos (ingresos: %, egresos: %). Ejecute primero 20260827_reparar_duplicados_ingresos_egresos.sql; para ver el detalle, 20260827_diagnostico_duplicados_ingresos_egresos.sql.',
            dup_ing, dup_egr;
    END IF;
END $$;

CREATE UNIQUE INDEX IF NOT EXISTS uq_ingresos_secuencial_activo
    ON ingresos_cabecera (id_empresa, id_punto_emision, secuencial, tipo_ambiente)
 WHERE eliminado = false AND id_punto_emision IS NOT NULL;

CREATE UNIQUE INDEX IF NOT EXISTS uq_egresos_secuencial_activo
    ON egresos_cabecera (id_empresa, id_punto_emision, secuencial, tipo_ambiente)
 WHERE eliminado = false AND id_punto_emision IS NOT NULL;
