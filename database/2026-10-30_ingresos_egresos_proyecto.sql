-- ============================================================================
-- Ingresos y Egresos: proyecto del documento (modulos/ingresos, modulos/egresos)
-- ----------------------------------------------------------------------------
-- Contexto:
--   Un ingreso o egreso puede pertenecer a UN proyecto (módulo Proyectos). Se
--   elige en la cabecera del documento y, al generar el asiento contable, se
--   copia a TODAS sus líneas (asientos_contables_detalle.id_proyecto). Así el
--   documento sale en las pestañas Documentos / Avance del proyecto sin tener
--   que editar el asiento línea por línea.
--
-- Qué hace:
--   Agrega la columna id_proyecto (nullable) a ingresos_cabecera y
--   egresos_cabecera, con su índice parcial. NO modifica ni borra datos: los
--   documentos existentes quedan sin proyecto.
--
-- Orden de despliegue: aplicar este SQL ANTES de subir el código. El código
-- igual funciona sin la columna (no muestra el campo ni la usa).
--
-- Reversible: sí —
--   DROP INDEX IF EXISTS idx_ingresos_cabecera_proyecto;
--   DROP INDEX IF EXISTS idx_egresos_cabecera_proyecto;
--   ALTER TABLE ingresos_cabecera DROP COLUMN IF EXISTS id_proyecto;
--   ALTER TABLE egresos_cabecera  DROP COLUMN IF EXISTS id_proyecto;
--   (se pierde el proyecto elegido en los documentos; los asientos lo conservan)
--
-- CÓMO EJECUTARLO EN pgAdmin: pegar todo en el Query Tool y ejecutar (F5).
-- Idempotente: se puede ejecutar más de una vez.
-- ============================================================================

ALTER TABLE ingresos_cabecera ADD COLUMN IF NOT EXISTS id_proyecto INTEGER NULL REFERENCES proyectos(id);
ALTER TABLE egresos_cabecera  ADD COLUMN IF NOT EXISTS id_proyecto INTEGER NULL REFERENCES proyectos(id);

CREATE INDEX IF NOT EXISTS idx_ingresos_cabecera_proyecto
    ON ingresos_cabecera (id_empresa, id_proyecto) WHERE id_proyecto IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_egresos_cabecera_proyecto
    ON egresos_cabecera (id_empresa, id_proyecto) WHERE id_proyecto IS NOT NULL;

-- Comprobación (deben salir 2 filas):
-- SELECT table_name, column_name FROM information_schema.columns
--  WHERE table_schema = 'public' AND column_name = 'id_proyecto'
--    AND table_name IN ('ingresos_cabecera', 'egresos_cabecera');
