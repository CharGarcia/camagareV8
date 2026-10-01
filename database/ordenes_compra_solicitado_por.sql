-- ============================================================================
-- Órdenes de Compra: campo "Solicitado por"
-- ============================================================================
-- Qué hace : agrega la columna ordenes_compra.solicitado_por (texto libre con
--            el nombre de quien pidió la compra). Se escribe en el modal y se
--            imprime en el PDF en el bloque de firmas (Solicitado por /
--            Revisado por / Aprobado por).
-- Datos    : no toca datos existentes (las órdenes previas quedan en NULL).
-- Reversible: sí → ALTER TABLE ordenes_compra DROP COLUMN IF EXISTS solicitado_por;
--
-- El código degrada solo si la columna aún no existe (no guarda el campo y el
-- PDF deja la línea en blanco), así que el orden SQL → deploy no es crítico.
--
-- 150 caracteres = el mismo maxlength del input del modal.
-- Listo para pegar y ejecutar en pgAdmin (idempotente).
-- ============================================================================

ALTER TABLE ordenes_compra
    ADD COLUMN IF NOT EXISTS solicitado_por VARCHAR(150) DEFAULT NULL;

-- Comprobación (debe salir 1 fila):
-- SELECT column_name, data_type, character_maximum_length
--   FROM information_schema.columns
--  WHERE table_schema = 'public' AND table_name = 'ordenes_compra' AND column_name = 'solicitado_por';
