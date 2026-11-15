-- ============================================================================
-- Cotizador de Planes — plan SEMESTRAL.
--
-- Ejecutar DESPUÉS de 2026-11-13_cotizador_planes.sql. Sin este SQL la pantalla
-- del cotizador avisa que falta instalarlo.
--
-- Regla (aprobada 15-11-2026): semestral = precio anual ÷ 2 × (1 + recargo
-- semestral), recargo inicial 10 %. El descuento por establecimiento adicional
-- sigue aplicando SOLO en el plan anual.
--
-- Idempotente: se puede ejecutar más de una vez.
-- ============================================================================

ALTER TABLE cotizador_planes_config
    ADD COLUMN IF NOT EXISTS recargo_semestral_pct        NUMERIC(6,2) NOT NULL DEFAULT 10;
ALTER TABLE cotizador_planes_config
    ADD COLUMN IF NOT EXISTS id_producto_resumen_semestral INTEGER NULL;   -- p. ej. «Plan semestral CaMaGaRe»

ALTER TABLE cotizaciones_planes
    ADD COLUMN IF NOT EXISTS total_semestral NUMERIC(14,2) NOT NULL DEFAULT 0;

-- La modalidad acepta ahora 'semestral'.
ALTER TABLE cotizaciones_planes DROP CONSTRAINT IF EXISTS ck_cot_modalidad;
ALTER TABLE cotizaciones_planes
    ADD CONSTRAINT ck_cot_modalidad CHECK (modalidad IN ('anual', 'semestral', 'mensual'));
