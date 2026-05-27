-- ============================================================
-- Módulo: Tareas y Obligaciones
-- Tablas globales (sin id_empresa)
-- ============================================================

-- 1. Catálogo de Obligaciones
CREATE TABLE IF NOT EXISTS cat_obligaciones (
    id           SERIAL PRIMARY KEY,
    nombre       VARCHAR(200) NOT NULL,
    descripcion  TEXT,
    status       SMALLINT    NOT NULL DEFAULT 1,
    eliminado    BOOLEAN     NOT NULL DEFAULT FALSE,
    created_at   TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMPTZ,
    created_by   INT REFERENCES usuarios(id) ON DELETE SET NULL,
    updated_by   INT REFERENCES usuarios(id) ON DELETE SET NULL,
    deleted_at   TIMESTAMPTZ,
    deleted_by   INT REFERENCES usuarios(id) ON DELETE SET NULL
);

COMMENT ON TABLE cat_obligaciones IS 'Catálogo global de tipos/nombres de obligaciones (Declaración IVA, Anexo ICE, etc.)';

-- Índices
CREATE INDEX IF NOT EXISTS idx_cat_obligaciones_status    ON cat_obligaciones(status);
CREATE INDEX IF NOT EXISTS idx_cat_obligaciones_eliminado ON cat_obligaciones(eliminado);

-- 2. Tareas
CREATE TABLE IF NOT EXISTS tareas (
    id                  SERIAL PRIMARY KEY,
    id_obligacion       INT REFERENCES cat_obligaciones(id) ON DELETE RESTRICT,
    id_cliente          INT REFERENCES clientes(id) ON DELETE SET NULL,
    cliente_nombre      VARCHAR(200) NOT NULL,
    cliente_correo      VARCHAR(200) NOT NULL,
    periodicidad        VARCHAR(30)  NOT NULL,
    -- mensual | trimestral | semestral | anual | dos_anios | tres_anios
    fecha_tarea         DATE         NOT NULL,
    estado              VARCHAR(40)  NOT NULL DEFAULT 'por_realizar',
    -- por_realizar | realizada_continua | realizada_finalizada | vencida | cancelada
    notas               TEXT,
    resumen             TEXT,
    motivo_cancelacion  TEXT,
    archivada           BOOLEAN      NOT NULL DEFAULT FALSE,
    id_tarea_origen     INT REFERENCES tareas(id) ON DELETE SET NULL,
    eliminado           BOOLEAN      NOT NULL DEFAULT FALSE,
    created_at          TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMPTZ,
    created_by          INT REFERENCES usuarios(id) ON DELETE SET NULL,
    updated_by          INT REFERENCES usuarios(id) ON DELETE SET NULL,
    deleted_at          TIMESTAMPTZ,
    deleted_by          INT REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT chk_tareas_periodicidad CHECK (
        periodicidad IN ('mensual','trimestral','semestral','anual','dos_anios','tres_anios')
    ),
    CONSTRAINT chk_tareas_estado CHECK (
        estado IN ('por_realizar','realizada_continua','realizada_finalizada','vencida','cancelada')
    )
);

COMMENT ON TABLE tareas IS 'Tareas asignadas a clientes con responsables y periodicidad';

CREATE INDEX IF NOT EXISTS idx_tareas_estado          ON tareas(estado);
CREATE INDEX IF NOT EXISTS idx_tareas_eliminado       ON tareas(eliminado);
CREATE INDEX IF NOT EXISTS idx_tareas_archivada       ON tareas(archivada);
CREATE INDEX IF NOT EXISTS idx_tareas_fecha           ON tareas(fecha_tarea);
CREATE INDEX IF NOT EXISTS idx_tareas_id_obligacion   ON tareas(id_obligacion);
CREATE INDEX IF NOT EXISTS idx_tareas_id_tarea_origen ON tareas(id_tarea_origen);

-- 3. Responsables de Tareas
CREATE TABLE IF NOT EXISTS tareas_responsables (
    id          SERIAL PRIMARY KEY,
    id_tarea    INT NOT NULL REFERENCES tareas(id) ON DELETE CASCADE,
    id_usuario  INT NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_tarea_responsable UNIQUE (id_tarea, id_usuario)
);

COMMENT ON TABLE tareas_responsables IS 'Usuarios responsables de cada tarea';

CREATE INDEX IF NOT EXISTS idx_tareas_responsables_tarea   ON tareas_responsables(id_tarea);
CREATE INDEX IF NOT EXISTS idx_tareas_responsables_usuario ON tareas_responsables(id_usuario);

-- 4. Adjuntos de Tareas
CREATE TABLE IF NOT EXISTS tareas_adjuntos (
    id              SERIAL PRIMARY KEY,
    id_tarea        INT          NOT NULL REFERENCES tareas(id) ON DELETE CASCADE,
    nombre_archivo  VARCHAR(300) NOT NULL,
    ruta_archivo    TEXT         NOT NULL,
    tipo_mime       VARCHAR(100),
    tamanio         BIGINT,
    eliminado       BOOLEAN      NOT NULL DEFAULT FALSE,
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by      INT REFERENCES usuarios(id) ON DELETE SET NULL,
    deleted_at      TIMESTAMPTZ,
    deleted_by      INT REFERENCES usuarios(id) ON DELETE SET NULL
);

COMMENT ON TABLE tareas_adjuntos IS 'Archivos adjuntos de tareas realizadas';

CREATE INDEX IF NOT EXISTS idx_tareas_adjuntos_tarea    ON tareas_adjuntos(id_tarea);
CREATE INDEX IF NOT EXISTS idx_tareas_adjuntos_eliminado ON tareas_adjuntos(eliminado);

-- ============================================================
-- Datos iniciales: obligaciones comunes en Ecuador
-- ============================================================
INSERT INTO cat_obligaciones (nombre, descripcion, status, eliminado)
VALUES
    ('Declaración de IVA Mensual',       'Formulario 104 - SRI',                        1, FALSE),
    ('Declaración de IVA Semestral',     'Formulario 104A - SRI (personas naturales)',   1, FALSE),
    ('Anexo ICE',                        'Impuesto a Consumos Especiales - SRI',         1, FALSE),
    ('Declaración Impuesto a la Renta',  'Formulario 101/102 - SRI',                    1, FALSE),
    ('Anexo Transaccional Simplificado', 'ATS - SRI',                                   1, FALSE),
    ('Declaración Retenciones en Fuente','Formulario 103 - SRI',                        1, FALSE),
    ('RDEP - Relación Dependencia',      'Anexo de empleados en relación de dependencia',1, FALSE),
    ('Roles de Pago Mensual',            'Liquidación mensual de nómina',                1, FALSE),
    ('Pago IESS Mensual',                'Aportes patronales y personales al IESS',      1, FALSE),
    ('Estados Financieros',              'Balance General y Estado de Resultados',       1, FALSE)
ON CONFLICT DO NOTHING;

-- ============================================================
-- Tabla propia de clientes para tareas (independiente de empresas)
-- ============================================================
CREATE TABLE IF NOT EXISTS clientes_tareas (
    id          SERIAL PRIMARY KEY,
    ruc         VARCHAR(20),
    nombre      VARCHAR(200) NOT NULL,
    correo      VARCHAR(200) NOT NULL,
    telefono    VARCHAR(30),
    eliminado   BOOLEAN     NOT NULL DEFAULT FALSE,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMPTZ,
    created_by  INT REFERENCES usuarios(id) ON DELETE SET NULL,
    updated_by  INT REFERENCES usuarios(id) ON DELETE SET NULL,
    deleted_at  TIMESTAMPTZ,
    deleted_by  INT REFERENCES usuarios(id) ON DELETE SET NULL
);

COMMENT ON TABLE clientes_tareas IS 'Clientes propios del módulo de tareas (sin relación con empresa)';

CREATE INDEX IF NOT EXISTS idx_clientes_tareas_nombre    ON clientes_tareas(nombre);
CREATE INDEX IF NOT EXISTS idx_clientes_tareas_ruc       ON clientes_tareas(ruc);
CREATE INDEX IF NOT EXISTS idx_clientes_tareas_eliminado ON clientes_tareas(eliminado);
