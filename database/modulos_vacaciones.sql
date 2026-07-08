-- ============================================================================
-- Módulo Vacaciones (Nómina) — registro de vacaciones por empleado con saldo
-- ----------------------------------------------------------------------------
-- Operativa multiempresa, eliminación lógica, auditoría. SIN tipo_ambiente
-- (interno de nómina, no comprobante SRI). Alimenta el rol de pagos mensual.
--
-- Derecho: 15 días/año; desde el 6º año de servicio +1 día por año adicional,
-- máx 30 (Art. 69 Código del Trabajo). Valor ≈ (sueldo/30) × días gozados.
-- ============================================================================

CREATE TABLE IF NOT EXISTS vacaciones (
    id            SERIAL PRIMARY KEY,
    id_empresa    INTEGER NOT NULL,
    id_empleado   INTEGER NOT NULL,
    fecha_desde   DATE NOT NULL,
    fecha_hasta   DATE NOT NULL,
    dias_gozados  NUMERIC(6,2) NOT NULL DEFAULT 0,
    dias_derecho  NUMERIC(6,2) NOT NULL DEFAULT 15,  -- derecho del periodo (snapshot)
    valor         NUMERIC(14,2) NOT NULL DEFAULT 0,
    periodo_mes   SMALLINT NOT NULL,                 -- rol mensual que alimenta
    periodo_anio  SMALLINT NOT NULL,
    afecta_rol    BOOLEAN NOT NULL DEFAULT true,     -- si su valor va al rol del periodo
    observacion   VARCHAR(255),
    estado        VARCHAR(20) NOT NULL DEFAULT 'registrado', -- registrado / pagado / anulado
    eliminado     BOOLEAN NOT NULL DEFAULT false,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by    INTEGER,
    updated_by    INTEGER,
    deleted_at    TIMESTAMP,
    deleted_by    INTEGER
);

CREATE INDEX IF NOT EXISTS idx_vacaciones_empresa  ON vacaciones (id_empresa) WHERE eliminado = false;
CREATE INDEX IF NOT EXISTS idx_vacaciones_empleado ON vacaciones (id_empresa, id_empleado) WHERE eliminado = false;
CREATE INDEX IF NOT EXISTS idx_vacaciones_periodo  ON vacaciones (id_empresa, periodo_anio, periodo_mes) WHERE eliminado = false;

-- Menú: reapuntar el submódulo "Vacaciones" (id 47) del módulo Nómina.
UPDATE submodulos_menu SET ruta = 'modulos/vacaciones', status = 1 WHERE id = 47;
