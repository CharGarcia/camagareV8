-- ============================================================================
-- Módulo: Consulta / Auditoría de log_sistema (solo lectura, sección Config)
-- Fecha: 2026-07-08
-- ----------------------------------------------------------------------------
-- 1) Índices sobre log_sistema (la tabla no tenía ninguno y solo crece).
--    Críticos para que el listado con filtros no colapse en producción.
-- 2) Tarjeta en /config con enlace al módulo (idempotente).
-- ============================================================================

-- --- 1. Índices ---------------------------------------------------------------
CREATE INDEX IF NOT EXISTS idx_log_sistema_created_at    ON log_sistema (created_at DESC);
CREATE INDEX IF NOT EXISTS idx_log_sistema_empresa_fecha ON log_sistema (id_empresa, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_log_sistema_usuario       ON log_sistema (id_usuario);
CREATE INDEX IF NOT EXISTS idx_log_sistema_tabla         ON log_sistema (tabla_afectada);
CREATE INDEX IF NOT EXISTS idx_log_sistema_registro      ON log_sistema (tabla_afectada, id_registro);

-- --- 2. Tarjeta en Config (nivel_minimo = 2: administradores en adelante) -----
DO $$
DECLARE v_id INTEGER;
BEGIN
    SELECT id INTO v_id
    FROM configuracion_opciones
    WHERE nombre = 'Auditoría del sistema'
    LIMIT 1;

    IF v_id IS NULL THEN
        INSERT INTO configuracion_opciones
            (nombre, descripcion, icono, clase_color, nivel_minimo, orden, activo)
        VALUES
            ('Auditoría del sistema',
             'Consulta la bitácora de todo lo que ocurre en el sistema (solo lectura).',
             'clock-history', 'dark', 2, 90, TRUE)
        RETURNING id INTO v_id;

        INSERT INTO configuracion_opcion_enlaces
            (id_opcion, etiqueta, ruta, clase_btn, orden)
        VALUES
            (v_id, 'Ver auditoría', '/config/log-sistema', 'dark', 0);
    END IF;
END $$;
