-- ============================================================================
-- Módulo: Registro y consulta de ERRORES del sistema (solo lectura, /config)
-- Fecha: 2026-07-27
-- ----------------------------------------------------------------------------
-- Bitácora técnica de errores (excepciones capturadas + fatales no capturados).
-- Es append-only, como log_sistema: NO lleva eliminación lógica.
-- id_empresa / id_usuario son NULLABLE: muchos errores ocurren sin sesión.
-- La tarjeta de consulta es EXCLUSIVA de nivel 3 (superadmin): nivel_minimo = 3.
-- ============================================================================

-- --- 1. Tabla -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS errores_sistema (
    id           SERIAL PRIMARY KEY,
    id_empresa   INTEGER,                         -- nullable (errores sin empresa/sesión)
    id_usuario   INTEGER,                         -- nullable
    tipo         VARCHAR(20)  NOT NULL DEFAULT 'excepcion', -- excepcion | fatal | manual
    clase        VARCHAR(255),                    -- clase de la excepción o tipo de error
    mensaje      TEXT         NOT NULL,
    sql_state    VARCHAR(12),                     -- SQLSTATE cuando es error de BD (PDO)
    archivo      TEXT,
    linea        INTEGER,
    ruta         VARCHAR(255),                    -- módulo/controlador donde ocurrió
    accion       VARCHAR(150),                    -- operación en curso (contexto)
    url          TEXT,                            -- URL de la petición
    metodo_http  VARCHAR(10),
    ip_usuario   VARCHAR(50),
    user_agent   TEXT,
    traza        TEXT,                            -- stack trace completo
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- --- 2. Índices (la tabla solo crece; consulta por fecha/empresa/tipo) ---------
CREATE INDEX IF NOT EXISTS idx_errores_sistema_created_at    ON errores_sistema (created_at DESC);
CREATE INDEX IF NOT EXISTS idx_errores_sistema_empresa_fecha ON errores_sistema (id_empresa, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_errores_sistema_tipo          ON errores_sistema (tipo);
CREATE INDEX IF NOT EXISTS idx_errores_sistema_sqlstate      ON errores_sistema (sql_state);
CREATE INDEX IF NOT EXISTS idx_errores_sistema_usuario       ON errores_sistema (id_usuario);

-- --- 3. Tarjeta en /config (nivel_minimo = 3: solo superadministrador) ---------
DO $$
DECLARE v_id INTEGER;
BEGIN
    SELECT id INTO v_id
    FROM configuracion_opciones
    WHERE nombre = 'Errores del sistema'
    LIMIT 1;

    IF v_id IS NULL THEN
        INSERT INTO configuracion_opciones
            (nombre, descripcion, icono, clase_color, nivel_minimo, orden, activo)
        VALUES
            ('Errores del sistema',
             'Registro técnico de errores del sistema para diagnóstico (solo lectura).',
             'bug', 'danger', 3, 91, TRUE)
        RETURNING id INTO v_id;

        INSERT INTO configuracion_opcion_enlaces
            (id_opcion, etiqueta, ruta, clase_btn, orden)
        VALUES
            (v_id, 'Ver errores', '/config/errores-sistema', 'danger', 0);
    END IF;
END $$;
