-- ============================================================================
-- Módulo Alumnos: se elimina el campo "Código de alumno".
--
-- El campo dejó de existir en el formulario, el listado y las exportaciones
-- (PDF/Excel), y el repositorio ya no lo lee ni lo escribe. Este script solo
-- limpia la base: la aplicación funciona igual con o sin la columna, así que
-- puede ejecutarse cuando se quiera (o no ejecutarse, si se prefiere conservar
-- los códigos históricos).
--
-- ATENCIÓN: DROP COLUMN borra definitivamente los códigos ya guardados. Antes
-- de ejecutar, revisar si hay datos que valga la pena conservar:
--
--   SELECT id_empresa, COUNT(*) AS con_codigo
--   FROM alumnos
--   WHERE eliminado = false AND codigo_alumno IS NOT NULL
--   GROUP BY id_empresa;
--
-- Ejecutar en pgAdmin sobre la base del sistema.
-- ============================================================================

BEGIN;

-- 1) Índice único parcial que dependía de la columna.
DROP INDEX IF EXISTS uq_alumnos_codigo;

-- 2) La columna.
ALTER TABLE alumnos DROP COLUMN IF EXISTS codigo_alumno;

COMMIT;
