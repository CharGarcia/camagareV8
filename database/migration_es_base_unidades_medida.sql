-- ============================================================
-- Migración: Agregar columna es_base a unidades_medida
-- Fecha: 2026-04-16
--
-- es_base = true  → Esta es la unidad de referencia del tipo
--                   (p.ej. kg para Peso, m para Longitud).
--                   Solo puede haber UNA base activa por tipo+empresa.
--
-- factor_base     → Cuántas unidades base equivale 1 de esta unidad.
--                   La unidad base siempre tiene factor_base = 1.
--                   Ej: 1 lb = 0.453592 kg → factor_base = 0.453592
-- ============================================================

-- 1. Agregar la columna
ALTER TABLE unidades_medida
    ADD COLUMN IF NOT EXISTS es_base BOOLEAN NOT NULL DEFAULT FALSE;

-- 2. Índice único parcial: garantiza solo una unidad base activa
--    por combinación tipo+empresa en la base de datos.
CREATE UNIQUE INDEX IF NOT EXISTS idx_una_base_por_tipo_empresa
    ON unidades_medida (id_tipo, id_empresa)
    WHERE es_base = TRUE AND eliminado = FALSE;

-- 3. Comentario
COMMENT ON COLUMN unidades_medida.es_base IS
    'TRUE = unidad base del tipo (factor_base debe ser 1). Solo una por id_tipo + id_empresa activa.';
