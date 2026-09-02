-- ============================================================================
--  Secuencial de Facturas de Venta: único POR AMBIENTE, no en general
-- ============================================================================
--
--  PROBLEMA
--  --------
--  El índice `uix_ventas_secuencial_activo` exigía que (empresa,
--  establecimiento, punto de emisión, secuencial) fuera único sin mirar el
--  ambiente. Pero Pruebas y Producción son numeraciones INDEPENDIENTES en el
--  SRI —el ambiente va dentro de la propia clave de acceso—, así que al pasar
--  un establecimiento a Producción la serie arranca de nuevo y vuelve a usar
--  números que ya se emitieron en Pruebas.
--
--  Resultado: al facturar salía "El número de secuencial ya existe para este
--  punto de emisión", aunque el número calculado sí estuviera libre en el
--  ambiente activo (SecuencialRepository::getSiguienteDisponible() siempre
--  filtró por tipo_ambiente; la validación de duplicados, no).
--
--  QUÉ HACE ESTE SCRIPT
--  --------------------
--  Reemplaza ese índice por uno que incluye el ambiente. Es estrictamente MENOS
--  restrictivo que el actual (agrega una columna a la clave), así que no puede
--  fallar por datos existentes: todo lo que hoy es válido lo sigue siendo.
--
--  Dentro de un mismo ambiente la unicidad se mantiene intacta: no se abre la
--  puerta a numerar dos veces la misma factura.
--
--  Va junto al cambio en FacturaVentaRepository::existeSecuencial(). Sin este
--  script, el aviso amable se convertiría en un error crudo de Postgres (23505)
--  al intentar guardar.
--
--  Ejecutar en pgAdmin (Query Tool) sobre la base del sistema.
-- ============================================================================

BEGIN;

-- 1) Verificación previa: ¿hay algo que impida el índice nuevo?
--    Debe devolver 0 filas. Si devuelve alguna, hay facturas realmente
--    duplicadas dentro del MISMO ambiente y hay que revisarlas antes de seguir
--    (no debería ocurrir: el índice actual ya lo impedía).
DO $$
DECLARE
    v_dups integer;
BEGIN
    SELECT COUNT(*) INTO v_dups FROM (
        SELECT 1
          FROM ventas_cabecera
         WHERE eliminado = false
         GROUP BY id_empresa, id_establecimiento, id_punto_emision,
                  secuencial, COALESCE(tipo_ambiente, '1')
        HAVING COUNT(*) > 1
    ) d;

    IF v_dups > 0 THEN
        RAISE EXCEPTION
            'Hay % combinación(es) de secuencial repetido dentro del mismo ambiente. Revíselas antes de aplicar este cambio.', v_dups;
    END IF;
END $$;

-- 2) Fuera el índice viejo (no distinguía ambiente).
DROP INDEX IF EXISTS uix_ventas_secuencial_activo;

-- 3) El mismo índice, ahora por ambiente.
--    COALESCE porque tipo_ambiente admite NULL en facturas anteriores a la
--    migración del SRI: esas cuentan como Pruebas ('1'), el mismo criterio que
--    usa FacturaVentaRepository::existeSecuencial().
CREATE UNIQUE INDEX uix_ventas_secuencial_activo
    ON ventas_cabecera (
        id_empresa,
        id_establecimiento,
        id_punto_emision,
        secuencial,
        COALESCE(tipo_ambiente, '1')
    )
    WHERE eliminado = false;

COMMIT;

-- ============================================================================
--  Comprobación (opcional, después del COMMIT)
-- ============================================================================
-- SELECT indexdef FROM pg_indexes WHERE indexname = 'uix_ventas_secuencial_activo';
--
-- Qué secuenciales conviven hoy en los dos ambientes de un mismo punto:
-- SELECT id_empresa, id_establecimiento, id_punto_emision,
--        COALESCE(tipo_ambiente,'1') AS ambiente,
--        COUNT(*) AS facturas, MIN(secuencial) AS desde, MAX(secuencial) AS hasta
--   FROM ventas_cabecera
--  WHERE eliminado = false
--  GROUP BY 1,2,3,4
--  ORDER BY 1,3,4;
