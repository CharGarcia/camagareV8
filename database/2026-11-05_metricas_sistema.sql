-- =============================================================================
-- Métricas del sistema (Fase A) — tarjeta en /config + índices de apoyo
-- -----------------------------------------------------------------------------
-- Qué hace:
--   1. Crea la tarjeta "Métricas del sistema" en /config (nivel_minimo = 3: solo
--      superadministrador) con su enlace a /config/metricas-sistema.
--   2. Agrega índices por fecha en sesiones_activas y sri_envio_log, que hoy no
--      tienen y que las métricas consultan por rango de fechas.
-- Toca datos: solo inserta la tarjeta (configuracion_opciones / _enlaces). No
--   modifica registros existentes.
-- Idempotente: sí (IF NOT EXISTS / comprobación previa). Se puede reejecutar.
-- Bloqueo: CREATE INDEX bloquea ESCRITURAS de la tabla mientras se crea (no las
--   lecturas). sesiones_activas se escribe en cada login: ejecutar en horario de
--   baja actividad.
-- Revertir:
--   DELETE FROM configuracion_opcion_enlaces WHERE ruta = '/config/metricas-sistema';
--   DELETE FROM configuracion_opciones WHERE nombre = 'Métricas del sistema';
--   DROP INDEX IF EXISTS idx_sesiones_activas_created_at;
--   DROP INDEX IF EXISTS idx_sesiones_activas_ultima_actividad;
--   DROP INDEX IF EXISTS idx_sri_envio_log_created_at;
-- =============================================================================

-- --- 1. Tarjeta en /config ----------------------------------------------------
DO $$
DECLARE v_id INTEGER;
BEGIN
    SELECT id INTO v_id
    FROM configuracion_opciones
    WHERE nombre = 'Métricas del sistema'
    LIMIT 1;

    IF v_id IS NULL THEN
        INSERT INTO configuracion_opciones
            (nombre, descripcion, icono, clase_color, nivel_minimo, orden, activo)
        VALUES
            ('Métricas del sistema',
             'Usuarios conectados y activos, empresas con movimiento, documentos emitidos, productos más vendidos, SRI y errores de toda la plataforma.',
             'graph-up-arrow', 'primary', 3, 39, TRUE)
        RETURNING id INTO v_id;

        INSERT INTO configuracion_opcion_enlaces
            (id_opcion, etiqueta, ruta, clase_btn, orden)
        VALUES
            (v_id, 'Ver métricas', '/config/metricas-sistema', 'primary', 0);
    END IF;
END $$;

-- --- 2. Índices por fecha -----------------------------------------------------
CREATE INDEX IF NOT EXISTS idx_sesiones_activas_created_at
    ON sesiones_activas (created_at);

CREATE INDEX IF NOT EXISTS idx_sesiones_activas_ultima_actividad
    ON sesiones_activas (ultima_actividad)
    WHERE activa = TRUE;

CREATE INDEX IF NOT EXISTS idx_sri_envio_log_created_at
    ON sri_envio_log (created_at);

-- --- Comprobación (debe salir 1 fila y luego 3 filas) -------------------------
-- SELECT o.id, o.nombre, e.ruta FROM configuracion_opciones o
--   JOIN configuracion_opcion_enlaces e ON e.id_opcion = o.id
--  WHERE o.nombre = 'Métricas del sistema';
-- SELECT indexname FROM pg_indexes
--  WHERE indexname IN ('idx_sesiones_activas_created_at',
--                      'idx_sesiones_activas_ultima_actividad',
--                      'idx_sri_envio_log_created_at');
