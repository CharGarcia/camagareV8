-- ============================================================================
-- Consignaciones de venta: clave de formulario para no guardar dos veces
-- ============================================================================
-- QUÉ HACE    Agrega consignaciones_ventas.token_guardado y un índice único por
--             empresa. El modal manda una clave por cada formulario de consignación
--             nueva; si llegan dos guardados con la misma clave (doble clic, o un
--             reintento después de "Error al guardar" cuando el servidor sí había
--             guardado), el servidor devuelve la consignación ya creada en vez de
--             crear otra.
-- TOCA DATOS  No. Solo estructura; las consignaciones existentes quedan con NULL.
-- BLOQUEO     El CREATE INDEX bloquea escrituras en consignaciones_ventas mientras
--             se construye (segundos). Ejecutar en horario de baja actividad.
-- ORDEN       Aplicar ANTES de desplegar el código. Si el código llega primero no
--             se rompe: sin la columna, guarda como antes (sin esta protección).
-- REVERSIBLE  Sí:
--               DROP INDEX IF EXISTS uq_consignaciones_ventas_token_guardado;
--               ALTER TABLE consignaciones_ventas DROP COLUMN IF EXISTS token_guardado;
-- USO         pgAdmin → Query Tool → F5. Idempotente: se puede volver a ejecutar.
-- ============================================================================

ALTER TABLE consignaciones_ventas
    ADD COLUMN IF NOT EXISTS token_guardado VARCHAR(64);

CREATE UNIQUE INDEX IF NOT EXISTS uq_consignaciones_ventas_token_guardado
    ON consignaciones_ventas (id_empresa, token_guardado)
 WHERE token_guardado IS NOT NULL;

-- COMPROBACIÓN (debe devolver 2 filas: la columna y el índice)
-- SELECT 'columna' AS objeto, column_name AS nombre
--   FROM information_schema.columns
--  WHERE table_name = 'consignaciones_ventas' AND column_name = 'token_guardado'
-- UNION ALL
-- SELECT 'indice', indexname
--   FROM pg_indexes
--  WHERE tablename = 'consignaciones_ventas' AND indexname = 'uq_consignaciones_ventas_token_guardado';
