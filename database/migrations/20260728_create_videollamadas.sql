-- MIGRATION: Módulo Videollamadas (Fase 1 — base del módulo)
-- ---------------------------------------------------------------------------
-- Salas de videollamada tipo Meet/Zoom integradas al ERP.
--
-- ARQUITECTURA: el módulo es dueño de las salas, los participantes, los permisos
-- y la auditoría; el MOTOR de video es intercambiable (columna `proveedor`):
--   interno → WebRTC mesh P2P, sin servidor de medios (hasta ~6 participantes)
--   jitsi/daily → proveedor externo para reuniones grandes o con grabación
-- Cambiar de motor NO requiere tocar estas tablas.
--
-- MULTIEMPRESA: todas las tablas son operativas → id_empresa + eliminado = false
-- en TODA consulta (§4 de CLAUDE.md).
--
-- NOTA sobre tipo_ambiente: estas tablas NO lo llevan. Una sala de reunión no es
-- un documento electrónico del SRI y no existe en "versión pruebas"; mismo
-- criterio que la tabla de empleados.
--
-- ES UNA MIGRACIÓN PURAMENTE ADITIVA: solo CREATE TABLE de tablas nuevas.
-- No altera ni migra ninguna tabla existente.
-- ---------------------------------------------------------------------------
BEGIN;

-- ─────────────────────────────────────────────────────────────────────────
-- 1. SALAS
-- ─────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS videollamadas_salas (
    id SERIAL PRIMARY KEY,
    id_empresa INTEGER NOT NULL,

    -- Código público de la sala (estilo "abc-defg-hij"). Es la dirección que se
    -- comparte, por eso es único a nivel global y no por empresa.
    codigo VARCHAR(40) NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    descripcion TEXT,

    -- instantanea: se abre y se usa ya | programada: tiene fecha y hora
    -- permanente: sala fija reutilizable (ej. "Sala de Soporte")
    tipo VARCHAR(20) NOT NULL DEFAULT 'instantanea',

    -- Motor de video. 'interno' = WebRTC mesh propio.
    proveedor VARCHAR(20) NOT NULL DEFAULT 'interno',
    id_externo_sala VARCHAR(200),

    fecha_inicio TIMESTAMP,
    fecha_fin TIMESTAMP,
    duracion_minutos INTEGER,

    id_anfitrion INTEGER NOT NULL,

    -- programada, en_curso, finalizada, cancelada
    estado VARCHAR(20) NOT NULL DEFAULT 'programada',

    sala_espera BOOLEAN NOT NULL DEFAULT TRUE,
    permite_invitados BOOLEAN NOT NULL DEFAULT FALSE,
    max_participantes INTEGER NOT NULL DEFAULT 6,
    grabar BOOLEAN NOT NULL DEFAULT FALSE,

    iniciada_at TIMESTAMP,
    finalizada_at TIMESTAMP,

    eliminado BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_by INTEGER,
    deleted_at TIMESTAMP,
    deleted_by INTEGER,

    CONSTRAINT fk_vc_sala_empresa FOREIGN KEY (id_empresa) REFERENCES empresas(id),
    CONSTRAINT fk_vc_sala_anfitrion FOREIGN KEY (id_anfitrion) REFERENCES usuarios(id),
    CONSTRAINT chk_vc_sala_tipo CHECK (tipo IN ('instantanea', 'programada', 'permanente')),
    CONSTRAINT chk_vc_sala_estado CHECK (estado IN ('programada', 'en_curso', 'finalizada', 'cancelada')),
    CONSTRAINT chk_vc_sala_max CHECK (max_participantes > 0 AND max_participantes <= 100)
);

-- El código es la URL pública: único entre salas vivas.
CREATE UNIQUE INDEX IF NOT EXISTS uq_vc_salas_codigo
    ON videollamadas_salas(codigo) WHERE eliminado = FALSE;
CREATE INDEX IF NOT EXISTS idx_vc_salas_empresa ON videollamadas_salas(id_empresa);
CREATE INDEX IF NOT EXISTS idx_vc_salas_estado ON videollamadas_salas(id_empresa, estado);
CREATE INDEX IF NOT EXISTS idx_vc_salas_fecha ON videollamadas_salas(fecha_inicio);
CREATE INDEX IF NOT EXISTS idx_vc_salas_anfitrion ON videollamadas_salas(id_anfitrion);
CREATE INDEX IF NOT EXISTS idx_vc_salas_created_by ON videollamadas_salas(created_by);

-- ─────────────────────────────────────────────────────────────────────────
-- 2. PARTICIPANTES
--    Un participante es un usuario del ERP (id_usuario) O un invitado externo
--    (nombre_invitado + token_acceso), nunca ambos vacíos.
-- ─────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS videollamadas_participantes (
    id SERIAL PRIMARY KEY,
    id_empresa INTEGER NOT NULL,
    id_sala INTEGER NOT NULL,

    id_usuario INTEGER,
    nombre_invitado VARCHAR(150),
    email VARCHAR(150),

    -- Token del enlace de invitación para externos (nunca para usuarios internos).
    token_acceso VARCHAR(64),

    rol VARCHAR(20) NOT NULL DEFAULT 'participante',
    estado VARCHAR(20) NOT NULL DEFAULT 'invitado',

    primera_conexion TIMESTAMP,
    ultima_conexion TIMESTAMP,
    segundos_conectado INTEGER NOT NULL DEFAULT 0,
    ip VARCHAR(45),
    user_agent TEXT,

    eliminado BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_by INTEGER,
    deleted_at TIMESTAMP,
    deleted_by INTEGER,

    CONSTRAINT fk_vc_part_empresa FOREIGN KEY (id_empresa) REFERENCES empresas(id),
    CONSTRAINT fk_vc_part_sala FOREIGN KEY (id_sala) REFERENCES videollamadas_salas(id),
    CONSTRAINT fk_vc_part_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id),
    CONSTRAINT chk_vc_part_identidad CHECK (id_usuario IS NOT NULL OR nombre_invitado IS NOT NULL),
    CONSTRAINT chk_vc_part_rol CHECK (rol IN ('anfitrion', 'moderador', 'participante')),
    CONSTRAINT chk_vc_part_estado CHECK (estado IN ('invitado', 'espera', 'admitido', 'conectado', 'desconectado', 'rechazado'))
);

-- Un usuario interno no puede estar dos veces en la misma sala.
CREATE UNIQUE INDEX IF NOT EXISTS uq_vc_part_sala_usuario
    ON videollamadas_participantes(id_sala, id_usuario)
    WHERE id_usuario IS NOT NULL AND eliminado = FALSE;
CREATE UNIQUE INDEX IF NOT EXISTS uq_vc_part_token
    ON videollamadas_participantes(token_acceso)
    WHERE token_acceso IS NOT NULL AND eliminado = FALSE;
CREATE INDEX IF NOT EXISTS idx_vc_part_sala ON videollamadas_participantes(id_sala);
CREATE INDEX IF NOT EXISTS idx_vc_part_empresa ON videollamadas_participantes(id_empresa);
CREATE INDEX IF NOT EXISTS idx_vc_part_usuario ON videollamadas_participantes(id_usuario);

-- ─────────────────────────────────────────────────────────────────────────
-- 3. EVENTOS (append-only)
--    Bitácora fina de lo que pasa dentro de la llamada. Es el insumo del
--    reporte de asistencia y del respaldo de consentimiento de grabación.
-- ─────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS videollamadas_eventos (
    id BIGSERIAL PRIMARY KEY,
    id_empresa INTEGER NOT NULL,
    id_sala INTEGER NOT NULL,
    id_participante INTEGER,

    -- entro, salio, mute, unmute, pantalla_inicio, pantalla_fin,
    -- grabacion_inicio, grabacion_fin, consentimiento_grabacion,
    -- admitido, rechazado, sala_iniciada, sala_finalizada
    tipo VARCHAR(40) NOT NULL,
    payload JSONB,

    eliminado BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_by INTEGER,
    deleted_at TIMESTAMP,
    deleted_by INTEGER,

    CONSTRAINT fk_vc_ev_empresa FOREIGN KEY (id_empresa) REFERENCES empresas(id),
    CONSTRAINT fk_vc_ev_sala FOREIGN KEY (id_sala) REFERENCES videollamadas_salas(id),
    CONSTRAINT fk_vc_ev_part FOREIGN KEY (id_participante) REFERENCES videollamadas_participantes(id)
);

CREATE INDEX IF NOT EXISTS idx_vc_ev_sala ON videollamadas_eventos(id_sala, created_at);
CREATE INDEX IF NOT EXISTS idx_vc_ev_empresa ON videollamadas_eventos(id_empresa);
CREATE INDEX IF NOT EXISTS idx_vc_ev_tipo ON videollamadas_eventos(tipo);

-- ─────────────────────────────────────────────────────────────────────────
-- 4. CONFIGURACIÓN POR EMPRESA (una fila por empresa)
--    Aquí viven los servidores STUN/TURN y el umbral para saltar al motor
--    externo. Las credenciales TURN se guardan cifradas y NUNCA se envían al
--    navegador: el service pide credenciales efímeras al proveedor.
-- ─────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS videollamadas_config (
    id SERIAL PRIMARY KEY,
    id_empresa INTEGER NOT NULL,

    proveedor_defecto VARCHAR(20) NOT NULL DEFAULT 'interno',

    -- Sobre este número de participantes se usa el proveedor externo, porque el
    -- mesh P2P deja de ser viable (cada navegador sube n-1 streams).
    umbral_proveedor_externo INTEGER NOT NULL DEFAULT 8,
    max_participantes INTEGER NOT NULL DEFAULT 6,
    duracion_max_minutos INTEGER NOT NULL DEFAULT 120,

    stun_urls TEXT DEFAULT 'stun:stun.l.google.com:19302',
    turn_urls TEXT,
    turn_usuario VARCHAR(200),
    turn_credencial TEXT,
    -- Token de API para emitir credenciales TURN efímeras (ej. Cloudflare).
    turn_api_token TEXT,
    turn_key_id VARCHAR(100),

    grabacion_habilitada BOOLEAN NOT NULL DEFAULT FALSE,
    -- local = disco del servidor | spaces = subida directa a S3/DO Spaces
    grabacion_destino VARCHAR(20) NOT NULL DEFAULT 'local',
    grabacion_retencion_dias INTEGER NOT NULL DEFAULT 30,

    eliminado BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_by INTEGER,
    deleted_at TIMESTAMP,
    deleted_by INTEGER,

    CONSTRAINT fk_vc_cfg_empresa FOREIGN KEY (id_empresa) REFERENCES empresas(id),
    CONSTRAINT chk_vc_cfg_destino CHECK (grabacion_destino IN ('local', 'spaces'))
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_vc_config_empresa
    ON videollamadas_config(id_empresa) WHERE eliminado = FALSE;

-- ─────────────────────────────────────────────────────────────────────────
-- 5. GRABACIONES (se llena en la Fase 5; la tabla se crea ya para no alterar
--    el esquema después)
-- ─────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS videollamadas_grabaciones (
    id SERIAL PRIMARY KEY,
    id_empresa INTEGER NOT NULL,
    id_sala INTEGER NOT NULL,
    id_participante_grabador INTEGER,

    nombre_archivo VARCHAR(255) NOT NULL,
    ruta TEXT NOT NULL,
    destino VARCHAR(20) NOT NULL DEFAULT 'local',
    mime VARCHAR(100),
    tamano_bytes BIGINT DEFAULT 0,
    duracion_segundos INTEGER DEFAULT 0,

    -- pendiente (subiendo), disponible, error, purgada (borrada por retención)
    estado VARCHAR(20) NOT NULL DEFAULT 'pendiente',
    expira_at TIMESTAMP,

    eliminado BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_by INTEGER,
    deleted_at TIMESTAMP,
    deleted_by INTEGER,

    CONSTRAINT fk_vc_grab_empresa FOREIGN KEY (id_empresa) REFERENCES empresas(id),
    CONSTRAINT fk_vc_grab_sala FOREIGN KEY (id_sala) REFERENCES videollamadas_salas(id),
    CONSTRAINT chk_vc_grab_destino CHECK (destino IN ('local', 'spaces')),
    CONSTRAINT chk_vc_grab_estado CHECK (estado IN ('pendiente', 'disponible', 'error', 'purgada'))
);

CREATE INDEX IF NOT EXISTS idx_vc_grab_sala ON videollamadas_grabaciones(id_sala);
CREATE INDEX IF NOT EXISTS idx_vc_grab_empresa ON videollamadas_grabaciones(id_empresa);
CREATE INDEX IF NOT EXISTS idx_vc_grab_expira ON videollamadas_grabaciones(expira_at);

COMMIT;

-- ─────────────────────────────────────────────────────────────────────────
-- PENDIENTE MANUAL (no lo ejecuta esta migración, según el flujo del proyecto):
--   1. Registrar el submódulo "Videollamadas" en submodulos_menu con
--      ruta = 'modulos/videollamadas'.
--   2. Asignar permisos en /config/permisos-modulos.
--   3. Actualizar config/modulos_mvc.php → 'modulos/videollamadas' →
--      id_submodulo con el id real que quede en submodulos_menu.
--   4. Configurar el TURN de la empresa (Fase 2). Sin TURN, entre el 10% y el
--      20% de las llamadas no logran conectar.
-- ─────────────────────────────────────────────────────────────────────────
