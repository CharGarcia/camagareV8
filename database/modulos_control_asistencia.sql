-- ============================================================================
-- Módulo Control de Asistencia / Personal — Fase 1 (esquema base)
-- ----------------------------------------------------------------------------
-- Modelo: el QR pertenece al PUNTO DE SERVICIO (no al empleado). El empleado
-- se identifica con su credencial personal (token) desde su celular, escanea
-- el QR del punto y se registra la marcación (entrada/salida/break) con selfie
-- y GPS. Caso guía: guardias de seguridad que rotan por distintos puntos.
--
-- Operativas (multiempresa): id_empresa + auditoría + eliminación lógica.
-- Toda consulta operativa debe filtrar por id_empresa AND eliminado = false.
-- Cambios de BD se entregan como SQL; el usuario los sube al repo y despliega.
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1) Biometría / credencial personal del empleado
--    Separada de "empleados" porque es dato sensible (LOPDP) con su propio
--    ciclo de vida y consentimiento. descriptor_facial queda listo para Fase 3.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS empleados_biometria (
    id                SERIAL PRIMARY KEY,
    id_empresa        INTEGER NOT NULL REFERENCES empresas(id),
    id_empleado       INTEGER NOT NULL REFERENCES empleados(id),

    qr_token          VARCHAR(64) NOT NULL,          -- credencial personal (enlace/PWA)
    dispositivo_id    VARCHAR(128),                  -- huella del celular vinculado (1er uso)
    consentimiento_at TIMESTAMP,                     -- consentimiento biométrico (LOPDP)
    descriptor_facial JSONB,                         -- vector face-api.js (Fase 3), nullable
    activo            BOOLEAN NOT NULL DEFAULT TRUE,

    eliminado   BOOLEAN NOT NULL DEFAULT FALSE,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at  TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    deleted_by  INTEGER
);

-- Un token único a nivel global (identifica al empleado sin login).
CREATE UNIQUE INDEX IF NOT EXISTS uk_biometria_qr_token
    ON empleados_biometria (qr_token);
-- Un registro de biometría vigente por empleado.
CREATE UNIQUE INDEX IF NOT EXISTS uk_biometria_empleado
    ON empleados_biometria (id_empleado) WHERE eliminado = FALSE;
CREATE INDEX IF NOT EXISTS idx_biometria_empresa
    ON empleados_biometria (id_empresa) WHERE eliminado = FALSE;

COMMENT ON TABLE empleados_biometria IS 'Credencial personal (QR token) y biometría facial del empleado para marcación';
COMMENT ON COLUMN empleados_biometria.qr_token IS 'Token personal que identifica al empleado desde su celular (PWA)';
COMMENT ON COLUMN empleados_biometria.descriptor_facial IS 'Vector 128-float face-api.js (Fase 3), no se almacena la foto';

-- ----------------------------------------------------------------------------
-- 2) Puntos de servicio (dueños del QR de ubicación)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS asistencia_puntos (
    id           SERIAL PRIMARY KEY,
    id_empresa   INTEGER NOT NULL REFERENCES empresas(id),

    nombre       VARCHAR(150) NOT NULL,              -- p. ej. "Garita Norte - Cliente X"
    direccion    TEXT,
    latitud      NUMERIC(10,7),                      -- coordenada oficial del punto
    longitud     NUMERIC(10,7),
    radio_m      INTEGER NOT NULL DEFAULT 150,       -- tolerancia de geocerca (metros)
    exige_gps    BOOLEAN NOT NULL DEFAULT TRUE,      -- cruzar GPS del celular vs. punto
    qr_token     VARCHAR(64) NOT NULL,               -- token del QR físico del punto
    qr_rotativo  BOOLEAN NOT NULL DEFAULT FALSE,     -- QR dinámico (Fase avanzada)
    estado       VARCHAR(20) NOT NULL DEFAULT 'activo', -- activo / inactivo

    eliminado   BOOLEAN NOT NULL DEFAULT FALSE,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at  TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    deleted_by  INTEGER
);

CREATE UNIQUE INDEX IF NOT EXISTS uk_puntos_qr_token
    ON asistencia_puntos (qr_token);
CREATE INDEX IF NOT EXISTS idx_puntos_empresa
    ON asistencia_puntos (id_empresa) WHERE eliminado = FALSE;

COMMENT ON TABLE asistencia_puntos IS 'Puntos de servicio; cada uno posee el QR físico que el empleado escanea';
COMMENT ON COLUMN asistencia_puntos.qr_token IS 'Token del QR pegado en el sitio (identifica la ubicación)';

-- ----------------------------------------------------------------------------
-- 3) Horarios / turnos de la empresa
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS asistencia_horarios (
    id            SERIAL PRIMARY KEY,
    id_empresa    INTEGER NOT NULL REFERENCES empresas(id),

    nombre        VARCHAR(100) NOT NULL,             -- "Diurno 8h", "Nocturno guardia 12h"
    hora_entrada  TIME NOT NULL,
    hora_salida   TIME NOT NULL,
    cruza_medianoche BOOLEAN NOT NULL DEFAULT FALSE, -- turno nocturno que pasa de día
    tolerancia_min   INTEGER NOT NULL DEFAULT 5,     -- minutos antes de contar atraso
    horas_jornada    NUMERIC(5,2) NOT NULL DEFAULT 8,-- horas esperadas
    dias_semana      VARCHAR(20) NOT NULL DEFAULT '1,2,3,4,5', -- 1=lun .. 7=dom
    estado        VARCHAR(20) NOT NULL DEFAULT 'activo',

    eliminado   BOOLEAN NOT NULL DEFAULT FALSE,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at  TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    deleted_by  INTEGER
);

CREATE INDEX IF NOT EXISTS idx_horarios_empresa
    ON asistencia_horarios (id_empresa) WHERE eliminado = FALSE;

COMMENT ON TABLE asistencia_horarios IS 'Definición de turnos/jornadas por empresa';

-- ----------------------------------------------------------------------------
-- 4) Asignación de horario (y punto) al empleado, con vigencia
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS asistencia_empleado_horario (
    id           SERIAL PRIMARY KEY,
    id_empresa   INTEGER NOT NULL REFERENCES empresas(id),
    id_empleado  INTEGER NOT NULL REFERENCES empleados(id),
    id_horario   INTEGER NOT NULL REFERENCES asistencia_horarios(id),
    id_punto     INTEGER REFERENCES asistencia_puntos(id), -- punto asignado (opcional)

    vigente_desde DATE NOT NULL,
    vigente_hasta DATE,                               -- NULL = vigente

    eliminado   BOOLEAN NOT NULL DEFAULT FALSE,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at  TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    deleted_by  INTEGER
);

CREATE INDEX IF NOT EXISTS idx_emp_horario_empresa
    ON asistencia_empleado_horario (id_empresa) WHERE eliminado = FALSE;
CREATE INDEX IF NOT EXISTS idx_emp_horario_empleado
    ON asistencia_empleado_horario (id_empleado);

COMMENT ON TABLE asistencia_empleado_horario IS 'Turno y punto de servicio asignados a un empleado por período de vigencia';

-- ----------------------------------------------------------------------------
-- 5) Marcaciones (registro crudo)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS asistencia_marcaciones (
    id           SERIAL PRIMARY KEY,
    id_empresa   INTEGER NOT NULL REFERENCES empresas(id),
    id_empleado  INTEGER NOT NULL REFERENCES empleados(id),
    id_punto     INTEGER REFERENCES asistencia_puntos(id),   -- punto donde marcó

    fecha_hora   TIMESTAMP NOT NULL,                 -- momento de la marca (servidor)
    tipo         VARCHAR(15) NOT NULL,               -- entrada / salida / inicio_break / fin_break
    metodo       VARCHAR(15) NOT NULL DEFAULT 'qr_punto', -- qr_punto / geo / manual / facial
    latitud      NUMERIC(10,7),                      -- GPS del celular al marcar
    longitud     NUMERIC(10,7),
    distancia_m  INTEGER,                            -- distancia calculada al punto (anti-fraude)
    selfie_path  VARCHAR(255),                       -- evidencia (storage/)
    confianza    NUMERIC(5,2),                       -- score facial (Fase 3)
    dispositivo_id VARCHAR(128),                     -- huella del celular
    estado       VARCHAR(20) NOT NULL DEFAULT 'valida', -- valida / sospechosa / anulada
    observacion  TEXT,

    eliminado   BOOLEAN NOT NULL DEFAULT FALSE,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at  TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    deleted_by  INTEGER
);

CREATE INDEX IF NOT EXISTS idx_marcaciones_empresa
    ON asistencia_marcaciones (id_empresa) WHERE eliminado = FALSE;
CREATE INDEX IF NOT EXISTS idx_marcaciones_empleado_fecha
    ON asistencia_marcaciones (id_empleado, fecha_hora);
CREATE INDEX IF NOT EXISTS idx_marcaciones_punto
    ON asistencia_marcaciones (id_punto);

COMMENT ON TABLE asistencia_marcaciones IS 'Marcaciones crudas: quién + punto + hora + método + evidencia GPS/selfie';
COMMENT ON COLUMN asistencia_marcaciones.distancia_m IS 'Distancia del GPS del celular al punto; alimenta la validación anti-fraude';

-- ----------------------------------------------------------------------------
-- 6) Jornadas (consolidado calculado por empleado/día)
--    Lo produce el motor de jornadas (JornadaService) a partir de las
--    marcaciones; es lo que alimenta al rol vía Novedades.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS asistencia_jornadas (
    id            SERIAL PRIMARY KEY,
    id_empresa    INTEGER NOT NULL REFERENCES empresas(id),
    id_empleado   INTEGER NOT NULL REFERENCES empleados(id),
    id_punto      INTEGER REFERENCES asistencia_puntos(id),
    id_horario    INTEGER REFERENCES asistencia_horarios(id),

    fecha            DATE NOT NULL,
    primera_entrada  TIMESTAMP,
    ultima_salida    TIMESTAMP,
    horas_trabajadas NUMERIC(6,2) NOT NULL DEFAULT 0,
    atraso_min       INTEGER NOT NULL DEFAULT 0,
    extra_min        INTEGER NOT NULL DEFAULT 0,
    estado           VARCHAR(20) NOT NULL DEFAULT 'incompleta', -- completa / incompleta / falta / permiso
    id_novedad       INTEGER,                        -- novedad generada al rol (Fase 2), nullable
    observacion      TEXT,

    eliminado   BOOLEAN NOT NULL DEFAULT FALSE,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at  TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    deleted_by  INTEGER
);

-- Una jornada por empleado/día vigente.
CREATE UNIQUE INDEX IF NOT EXISTS uk_jornada_empleado_fecha
    ON asistencia_jornadas (id_empleado, fecha) WHERE eliminado = FALSE;
CREATE INDEX IF NOT EXISTS idx_jornadas_empresa_fecha
    ON asistencia_jornadas (id_empresa, fecha) WHERE eliminado = FALSE;

COMMENT ON TABLE asistencia_jornadas IS 'Consolidado diario por empleado (horas, atrasos, extras); insumo del rol vía Novedades';

-- ----------------------------------------------------------------------------
-- Menú/permisos: registrar el submódulo "Control de Asistencia" en
-- submodulos_menu con ruta = 'modulos/control-asistencia' y asignar permisos
-- en modulos_asignados. (Se define el id de submódulo al integrar el menú;
-- ver config/modulos_mvc.php y /config/permisos-modulos.)
-- ============================================================================
