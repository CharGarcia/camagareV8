-- ============================================================================
-- Módulo Novedades — campo "Afecta a" (a qué pago aplica la novedad)
-- ----------------------------------------------------------------------------
-- Valores: 'semanal' (pago semanal), 'quincena', 'rol' (rol de pagos mensual).
-- Por defecto 'rol'.
-- ============================================================================

ALTER TABLE novedades
    ADD COLUMN IF NOT EXISTS aplica_en VARCHAR(20) NOT NULL DEFAULT 'rol';
