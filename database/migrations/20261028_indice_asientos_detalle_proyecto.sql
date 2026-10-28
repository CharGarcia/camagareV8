-- ============================================================================
-- Índice para las pestañas Documentos / Avance de Proyectos y el % de ejecución
-- del listado de proyectos (DimensionMovimientoRepository).
--
-- El índice existente idx_asientos_det_cc_proy (id_empresa, id_centro_costo, id_proyecto)
-- empieza por el centro de costo: sirve a Centros de costo, pero no a una búsqueda solo
-- por proyecto. Este es parcial (solo las líneas con proyecto), así que es pequeño.
--
-- Idempotente. Listo para pgAdmin. Revertir:
--   DROP INDEX IF EXISTS idx_asientos_det_proyecto;
-- ============================================================================

-- 1. (Opcional) Ver si ya hay uno equivalente con otro nombre (IF NOT EXISTS solo mira el nombre):
-- SELECT indexname, indexdef FROM pg_indexes
--  WHERE tablename = 'asientos_contables_detalle' AND indexdef ILIKE '%id_proyecto%';

CREATE INDEX IF NOT EXISTS idx_asientos_det_proyecto
    ON asientos_contables_detalle (id_empresa, id_proyecto)
    WHERE id_proyecto IS NOT NULL;

ANALYZE asientos_contables_detalle;
