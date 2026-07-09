-- ============================================================================
-- Módulo Control de Asistencia — Configuración por empresa (paso 4)
-- ----------------------------------------------------------------------------
-- Define cómo se tratan los ATRASOS al generar Novedades desde las jornadas
-- calculadas. Operativa (multiempresa), una fila por empresa.
-- ============================================================================

CREATE TABLE IF NOT EXISTS asistencia_config (
    id                  SERIAL PRIMARY KEY,
    id_empresa          INTEGER NOT NULL REFERENCES empresas(id),

    -- 'descuento'  = novedad Descuento (2), monto = horas_atraso * (sueldo_base/240)
    -- 'dias'       = novedad Días no laborados (10), valor = fracción de día (horas_atraso/8)
    -- 'informativo'= no genera novedad por atrasos (solo se ven en Jornadas)
    atraso_modo         VARCHAR(20) NOT NULL DEFAULT 'informativo',

    eliminado   BOOLEAN NOT NULL DEFAULT FALSE,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at  TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    deleted_by  INTEGER
);

CREATE UNIQUE INDEX IF NOT EXISTS uk_asistencia_config_empresa
    ON asistencia_config (id_empresa) WHERE eliminado = FALSE;

COMMENT ON TABLE asistencia_config IS 'Configuración por empresa del módulo Control de Asistencia (tratamiento de atrasos al generar novedades)';
COMMENT ON COLUMN asistencia_config.atraso_modo IS 'informativo | descuento | dias — cómo se trasladan los atrasos al rol vía Novedades';
