-- Módulo de Citas - CaMaGaRe
-- Fecha: 2026-05-25

-- ─── TABLAS OPERATIVAS ────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS citas_tipos (
    id                   SERIAL PRIMARY KEY,
    id_empresa           INTEGER NOT NULL,
    nombre               VARCHAR(150) NOT NULL,
    descripcion          TEXT,
    duracion_minutos     INTEGER NOT NULL DEFAULT 30,
    precio               NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    requiere_pago        BOOLEAN DEFAULT FALSE,
    tipo_pago            VARCHAR(20) DEFAULT 'sin_pago', -- sin_pago, total, anticipo
    anticipo_porcentaje  NUMERIC(5,2),
    color                VARCHAR(7) DEFAULT '#0d6efd',
    status               SMALLINT DEFAULT 1,
    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by           INTEGER NOT NULL,
    updated_by           INTEGER,
    eliminado            BOOLEAN DEFAULT FALSE,
    deleted_at           TIMESTAMP,
    deleted_by           INTEGER
);
CREATE INDEX IF NOT EXISTS idx_citas_tipos_empresa ON citas_tipos(id_empresa, eliminado);
COMMENT ON TABLE citas_tipos IS 'Tipos/servicios de cita configurables por empresa';

CREATE TABLE IF NOT EXISTS citas_recursos (
    id           SERIAL PRIMARY KEY,
    id_empresa   INTEGER NOT NULL,
    nombre       VARCHAR(150) NOT NULL,
    tipo         VARCHAR(50) NOT NULL DEFAULT 'persona', -- persona, sala, equipo
    descripcion  TEXT,
    status       SMALLINT DEFAULT 1,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by   INTEGER NOT NULL,
    updated_by   INTEGER,
    eliminado    BOOLEAN DEFAULT FALSE,
    deleted_at   TIMESTAMP,
    deleted_by   INTEGER
);
CREATE INDEX IF NOT EXISTS idx_citas_recursos_empresa ON citas_recursos(id_empresa, eliminado);
COMMENT ON TABLE citas_recursos IS 'Recursos (personas, salas, equipos) que atienden citas por empresa';

CREATE TABLE IF NOT EXISTS citas_horarios (
    id           SERIAL PRIMARY KEY,
    id_empresa   INTEGER NOT NULL,
    id_recurso   INTEGER REFERENCES citas_recursos(id),  -- NULL = horario general de empresa
    dia_semana   SMALLINT NOT NULL CHECK (dia_semana BETWEEN 1 AND 7), -- 1=Lunes..7=Domingo
    hora_inicio  TIME NOT NULL,
    hora_fin     TIME NOT NULL,
    status       BOOLEAN DEFAULT TRUE,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by   INTEGER NOT NULL,
    updated_by   INTEGER,
    eliminado    BOOLEAN DEFAULT FALSE,
    deleted_at   TIMESTAMP,
    deleted_by   INTEGER
);
CREATE INDEX IF NOT EXISTS idx_citas_horarios_empresa ON citas_horarios(id_empresa, eliminado);
COMMENT ON TABLE citas_horarios IS 'Bloques de disponibilidad horaria por empresa o recurso';

CREATE TABLE IF NOT EXISTS citas_config_portal (
    id                      SERIAL PRIMARY KEY,
    id_empresa              INTEGER NOT NULL UNIQUE,
    slug                    VARCHAR(100) NOT NULL UNIQUE,
    titulo                  VARCHAR(200),
    mensaje_bienvenida      TEXT,
    color_primario          VARCHAR(7) DEFAULT '#0d6efd',
    activo                  BOOLEAN DEFAULT FALSE,
    requiere_confirmacion   BOOLEAN DEFAULT FALSE,
    max_dias_anticipacion   INTEGER DEFAULT 30,
    min_horas_anticipacion  INTEGER DEFAULT 2,
    permite_pagos_online    BOOLEAN DEFAULT FALSE,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by              INTEGER NOT NULL,
    updated_by              INTEGER,
    eliminado               BOOLEAN DEFAULT FALSE,
    deleted_at              TIMESTAMP,
    deleted_by              INTEGER
);
COMMENT ON TABLE citas_config_portal IS 'Configuración del portal público de reservas por empresa';

CREATE TABLE IF NOT EXISTS citas (
    id            SERIAL PRIMARY KEY,
    id_empresa    INTEGER NOT NULL,
    id_tipo_cita  INTEGER NOT NULL REFERENCES citas_tipos(id),
    id_recurso    INTEGER REFERENCES citas_recursos(id),
    id_cliente    INTEGER,
    titulo        VARCHAR(200),
    fecha_inicio  TIMESTAMP NOT NULL,
    fecha_fin     TIMESTAMP NOT NULL,
    estado        VARCHAR(50) NOT NULL DEFAULT 'pendiente', -- pendiente, confirmada, en_curso, completada, cancelada, no_asistio
    notas         TEXT,
    origen        VARCHAR(50) DEFAULT 'interno', -- interno, portal, whatsapp
    token_acceso  VARCHAR(100) UNIQUE,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by    INTEGER NOT NULL,
    updated_by    INTEGER,
    eliminado     BOOLEAN DEFAULT FALSE,
    deleted_at    TIMESTAMP,
    deleted_by    INTEGER
);
CREATE INDEX IF NOT EXISTS idx_citas_empresa ON citas(id_empresa, eliminado);
CREATE INDEX IF NOT EXISTS idx_citas_fecha ON citas(id_empresa, fecha_inicio, fecha_fin);
COMMENT ON TABLE citas IS 'Tabla principal de citas médicas/servicio';

CREATE TABLE IF NOT EXISTS citas_clientes_externos (
    id                  SERIAL PRIMARY KEY,
    id_empresa          INTEGER NOT NULL,
    nombres             VARCHAR(150) NOT NULL,
    apellidos           VARCHAR(150),
    email               VARCHAR(150),
    telefono            VARCHAR(50),
    identificacion      VARCHAR(50),
    id_cliente_sistema  INTEGER,  -- vinculado a clientes si existe en el sistema
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    eliminado           BOOLEAN DEFAULT FALSE,
    deleted_at          TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_citas_clientes_ext_empresa ON citas_clientes_externos(id_empresa, eliminado);
COMMENT ON TABLE citas_clientes_externos IS 'Clientes que reservan desde el portal público';

CREATE TABLE IF NOT EXISTS citas_pagos (
    id                  SERIAL PRIMARY KEY,
    id_empresa          INTEGER NOT NULL,
    id_cita             INTEGER NOT NULL REFERENCES citas(id),
    monto               NUMERIC(10,2) NOT NULL,
    tipo_pago           VARCHAR(50) NOT NULL DEFAULT 'total', -- total, anticipo
    gateway             VARCHAR(50) DEFAULT 'sitio',          -- stripe, paypal, transferencia, sitio
    referencia_externa  VARCHAR(200),
    estado              VARCHAR(50) NOT NULL DEFAULT 'pendiente', -- pendiente, completado, fallido, reembolsado
    datos_gateway       JSONB,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by          INTEGER NOT NULL,
    updated_by          INTEGER,
    eliminado           BOOLEAN DEFAULT FALSE,
    deleted_at          TIMESTAMP,
    deleted_by          INTEGER
);
CREATE INDEX IF NOT EXISTS idx_citas_pagos_empresa ON citas_pagos(id_empresa, eliminado);
CREATE INDEX IF NOT EXISTS idx_citas_pagos_cita ON citas_pagos(id_cita);
COMMENT ON TABLE citas_pagos IS 'Registro de pagos vinculados a citas';

-- ─── SUBMODULOS EN MENÚ (id_modulo = 14 = Citas) ─────────────────────────────

SELECT setval('submodulos_menu_id_seq', (SELECT MAX(id) FROM submodulos_menu), true);

INSERT INTO submodulos_menu (nombre_submodulo, ruta, id_modulo, orden, id_icono, status) VALUES
('Configuración',    'modulos/citas-configuracion', 14, 1, 47, 1),
('Agenda',           'modulos/citas-agenda',        14, 2, 47, 1),
('Portal Reservas',  'modulos/citas-portal',        14, 3, 47, 1),
('Pagos',            'modulos/citas-pagos',         14, 4, 47, 1);

-- Verificar IDs asignados (ejecutar luego del INSERT para actualizar modulos_mvc.php):
-- SELECT id, nombre_submodulo, ruta FROM submodulos_menu WHERE id_modulo = 14 ORDER BY orden;
