-- ============================================================================
-- Estación predeterminada del restaurante
--
-- Hasta ahora la estación de una línea de comanda se resolvía así:
--   ítem del Menú → categoría del ítem → categoría del producto
-- y si nada de eso la daba, la línea quedaba SIN estación: no pasaba por
-- preparación (nacía entregada), no aparecía en el KDS y no generaba orden de
-- impresión.
--
-- Eso deja fuera a los locales que trabajan directo con el stock general, sin
-- cargar la carta en Menú. Esta columna agrega el último eslabón de la cascada:
-- una estación marcada como predeterminada recoge todo lo que no resolvió por
-- las vías anteriores. Se administra en modulos/configuracion-restaurante.
--
-- Se resuelve aquí, y NO con una columna en productos ni en categorias: el
-- local que trabaja así no quiere configurar nada por producto.
-- ============================================================================

ALTER TABLE estaciones_impresion
    ADD COLUMN IF NOT EXISTS es_predeterminada BOOLEAN NOT NULL DEFAULT FALSE;

COMMENT ON COLUMN estaciones_impresion.es_predeterminada IS 'Recoge los ítems que no resuelven estación por el Menú ni por la categoría. Una sola por empresa.';

-- Una sola predeterminada por empresa: el índice lo garantiza en la base, no
-- solo en PHP (dos pestañas abiertas podrían marcar dos a la vez).
CREATE UNIQUE INDEX IF NOT EXISTS uq_estaciones_predeterminada_empresa
    ON estaciones_impresion (id_empresa)
    WHERE es_predeterminada = true AND eliminado = false;
