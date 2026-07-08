-- ============================================================================
-- Módulo Empleados — Unicidad de identificación respetando eliminación lógica
-- ----------------------------------------------------------------------------
-- Problema: la restricción UNIQUE (identificacion, id_empresa) era TOTAL, por
-- lo que un empleado eliminado lógicamente (eliminado = true) seguía bloqueando
-- volver a registrar esa misma cédula/identificación en la empresa.
--
-- Solución: índice UNIQUE PARCIAL que solo aplica a registros NO eliminados,
-- de modo que la unicidad convive con la eliminación lógica del sistema.
-- (Mismo criterio que las validaciones de negocio en EmpleadoRepository::existsByIdentificacion.)
-- ============================================================================

BEGIN;

-- 1. Eliminar la restricción/índice UNIQUE total anterior.
ALTER TABLE empleados DROP CONSTRAINT IF EXISTS uk_empleado_identificacion_empresa;
DROP INDEX IF EXISTS uk_empleado_identificacion_empresa;

-- 2. Crear índice UNIQUE parcial: solo empleados vivos.
CREATE UNIQUE INDEX IF NOT EXISTS uk_empleado_identificacion_empresa
    ON empleados (identificacion, id_empresa)
    WHERE eliminado = false;

COMMIT;
