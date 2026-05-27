-- =============================================================
-- Módulo: Firmas Electrónicas
-- Tablas: firmas_electronicas, firmas_electronicas_adjuntos
-- =============================================================

CREATE TABLE IF NOT EXISTS firmas_electronicas (
    id               SERIAL PRIMARY KEY,
    id_empresa       INTEGER NOT NULL,
    id_usuario       INTEGER NOT NULL,

    -- Tipo de firma (producto con categoría "firmas")
    id_producto      INTEGER,
    nombre_producto  VARCHAR(200),

    -- Identificación
    tipo_identificacion  VARCHAR(20)  NOT NULL DEFAULT 'cedula',  -- 'cedula', 'pasaporte'
    numero_identificacion VARCHAR(50) NOT NULL,
    codigo_dactilar      VARCHAR(30),

    -- Datos personales
    nombres          VARCHAR(100) NOT NULL,
    apellidos        VARCHAR(100) NOT NULL,
    fecha_nacimiento DATE,
    telefono         VARCHAR(20),
    correo           VARCHAR(150),
    nacionalidad     VARCHAR(80),
    sexo             VARCHAR(10),       -- 'hombre', 'mujer'
    direccion        VARCHAR(255),

    -- Ubicación
    cod_prov         VARCHAR(10),
    cod_ciudad       VARCHAR(10),

    -- Pago
    tipo_pago        VARCHAR(20),       -- 'transferencia', 'tarjeta'
    estado_pago      VARCHAR(20) DEFAULT 'pendiente',  -- 'pendiente', 'confirmado', 'rechazado'

    -- Estado del trámite
    estado           VARCHAR(30) DEFAULT 'pendiente',  -- 'pendiente','en_proceso','emitida','cancelada'
    fecha_caducidad  DATE,                             -- fecha de vencimiento de la firma
    observaciones    TEXT,

    -- Auditoría
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP,
    created_by   INTEGER,
    updated_by   INTEGER,
    eliminado    BOOLEAN DEFAULT FALSE,
    deleted_at   TIMESTAMP,
    deleted_by   INTEGER
);

CREATE INDEX IF NOT EXISTS idx_firmas_empresa    ON firmas_electronicas (id_empresa, eliminado);
CREATE INDEX IF NOT EXISTS idx_firmas_estado     ON firmas_electronicas (id_empresa, estado, eliminado);
CREATE INDEX IF NOT EXISTS idx_firmas_identificacion ON firmas_electronicas (numero_identificacion);

-- -------------------------------------------------------------
-- Adjuntos por firma electrónica
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS firmas_electronicas_adjuntos (
    id              SERIAL PRIMARY KEY,
    id_firma        INTEGER NOT NULL REFERENCES firmas_electronicas(id) ON DELETE CASCADE,
    id_empresa      INTEGER NOT NULL,

    -- 'cedula_frontal', 'cedula_posterior', 'comprobante_transferencia', 'otro'
    tipo            VARCHAR(50) NOT NULL DEFAULT 'otro',
    nombre_original VARCHAR(255),
    nombre_archivo  VARCHAR(255) NOT NULL,
    ruta_relativa   VARCHAR(500) NOT NULL,
    mime_type       VARCHAR(100),
    tamano_bytes    BIGINT,

    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by  INTEGER,
    eliminado   BOOLEAN DEFAULT FALSE,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER
);

CREATE INDEX IF NOT EXISTS idx_firmas_adjuntos_firma    ON firmas_electronicas_adjuntos (id_firma, eliminado);
CREATE INDEX IF NOT EXISTS idx_firmas_adjuntos_empresa  ON firmas_electronicas_adjuntos (id_empresa, eliminado);

-- -------------------------------------------------------------
-- Registrar submódulo en menú (ajustar id_modulo según el módulo padre de empresa)
-- Ejecutar SOLO si ya existe el módulo padre en modulos_menu.
-- Ajusta el id_modulo al que corresponda en tu instalación.
-- -------------------------------------------------------------
-- INSERT INTO submodulos_menu (id_modulo, nombre, ruta, icono, orden, status)
-- VALUES (
--     (SELECT id FROM modulos_menu WHERE nombre ILIKE '%empresa%' LIMIT 1),
--     'Firmas Electrónicas',
--     'modulos/firmas_electronicas',
--     'bi bi-pen',
--     50,
--     true
-- );
