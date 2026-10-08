-- ============================================================================
-- Ficha del empleado: «Participa en utilidades» y «% de discapacidad».
-- ----------------------------------------------------------------------------
-- participa_utilidades: false excluye al empleado del reparto del 15% (módulo
--   Utilidades): no recibe y sus días no cuentan en el total, así el 10% y el
--   5% se reparten solo entre quienes tienen derecho (p. ej. el dueño o el
--   representante legal nombrado por mandato, que no están en relación de
--   dependencia). Por defecto true: nadie queda fuera sin marcarlo.
-- porcentaje_discapacidad: grado de discapacidad del carné (0 a 100). Completa a
--   la columna `discapacidad` (booleana, creada por el módulo Décimo Cuarto, que
--   hasta ahora solo se marcaba en la grilla de los décimos). Lo usa el Anexo
--   RDEP: condición 02 y exoneración por discapacidad (60/70/80/100% de dos
--   fracciones básicas según el grado, desde el 30%).
-- Idempotente: se puede ejecutar más de una vez.
-- ============================================================================

ALTER TABLE empleados
    ADD COLUMN IF NOT EXISTS discapacidad BOOLEAN NOT NULL DEFAULT false,
    ADD COLUMN IF NOT EXISTS participa_utilidades BOOLEAN NOT NULL DEFAULT true,
    ADD COLUMN IF NOT EXISTS porcentaje_discapacidad SMALLINT NOT NULL DEFAULT 0;

COMMENT ON COLUMN empleados.participa_utilidades IS
    'false = no participa en el reparto del 15% de utilidades (dueño, representante legal por mandato, etc.).';
COMMENT ON COLUMN empleados.porcentaje_discapacidad IS
    'Grado de discapacidad del carné (0-100). Lo usa el Anexo RDEP (condición y exoneración por discapacidad).';

-- Verificación: deben salir 3 filas
SELECT column_name, data_type, column_default
FROM information_schema.columns
WHERE table_name = 'empleados'
  AND column_name IN ('discapacidad', 'participa_utilidades', 'porcentaje_discapacidad')
ORDER BY column_name;
