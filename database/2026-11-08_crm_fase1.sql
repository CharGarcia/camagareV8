-- ============================================================================
--  CRM y Redes Sociales — Fase 1 (tablas)
-- ----------------------------------------------------------------------------
--  Diseño: docs/diseno/crm-redes-fase1.md (aprobado el 08-11-2026).
--
--  Crea las tablas del embudo de ventas (embudos, etapas, orígenes, motivos de
--  pérdida, contactos, oportunidades, actividades), de la bandeja de Instagram
--  (canales, conversaciones, mensajes) y la cola de eventos del webhook de Meta.
--  Agrega proformas_cabecera.id_oportunidad.
--
--  Los datos por defecto (embudo «Ventas», orígenes, motivos) NO van aquí: los
--  siembra el sistema por empresa al activar el CRM (CrmConfiguracionService).
--  Submódulos y permisos: los registra el administrador (no van aquí).
--
--  Toca datos: no (solo crea tablas y una columna nula).
--  Reversible: sí. Para revertir, en este orden:
--      ALTER TABLE proformas_cabecera DROP COLUMN IF EXISTS id_oportunidad;
--      DROP TABLE IF EXISTS crm_eliminaciones_datos, crm_webhook_eventos, crm_actividades,
--          crm_oportunidades_etapas_log, crm_oportunidades, crm_mensajes,
--          crm_conversaciones, crm_contactos, crm_canales, crm_meta_apps, crm_motivos_perdida,
--          crm_origenes, crm_etapas, crm_embudos;
--  Idempotente: se puede ejecutar varias veces sin efecto.
--  Listo para pegar en el Query Tool de pgAdmin y ejecutar de una vez (F5).
-- ============================================================================


-- ── 1. Embudos y etapas ─────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS crm_embudos (
    id           SERIAL PRIMARY KEY,
    id_empresa   INT NOT NULL,
    nombre       VARCHAR(100) NOT NULL,
    es_defecto   BOOLEAN NOT NULL DEFAULT false,
    orden        INT NOT NULL DEFAULT 0,
    estado       VARCHAR(10) NOT NULL DEFAULT 'activo',   -- activo / inactivo
    created_at   TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at   TIMESTAMP NULL,
    created_by   INT NULL,
    updated_by   INT NULL,
    eliminado    BOOLEAN NOT NULL DEFAULT false,
    deleted_at   TIMESTAMP NULL,
    deleted_by   INT NULL
);
COMMENT ON TABLE crm_embudos IS 'CRM: embudos de venta por empresa (p. ej. Ventas, Postventa).';
CREATE INDEX IF NOT EXISTS ix_crm_embudos_empresa ON crm_embudos (id_empresa) WHERE eliminado = false;
-- Un solo embudo por defecto vigente por empresa.
CREATE UNIQUE INDEX IF NOT EXISTS ux_crm_embudos_defecto
    ON crm_embudos (id_empresa) WHERE es_defecto = true AND eliminado = false;

CREATE TABLE IF NOT EXISTS crm_etapas (
    id            SERIAL PRIMARY KEY,
    id_empresa    INT NOT NULL,
    id_embudo     INT NOT NULL REFERENCES crm_embudos(id),
    nombre        VARCHAR(100) NOT NULL,
    orden         INT NOT NULL DEFAULT 0,
    probabilidad  SMALLINT NOT NULL DEFAULT 0 CHECK (probabilidad BETWEEN 0 AND 100),
    tipo          VARCHAR(10) NOT NULL DEFAULT 'abierta'
                  CHECK (tipo IN ('abierta', 'ganada', 'perdida')),
    color         VARCHAR(20) NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at    TIMESTAMP NULL,
    created_by    INT NULL,
    updated_by    INT NULL,
    eliminado     BOOLEAN NOT NULL DEFAULT false,
    deleted_at    TIMESTAMP NULL,
    deleted_by    INT NULL
);
COMMENT ON TABLE crm_etapas IS 'CRM: etapas de cada embudo. Exactamente una ganada y una perdida por embudo.';
CREATE INDEX IF NOT EXISTS ix_crm_etapas_embudo ON crm_etapas (id_embudo, orden) WHERE eliminado = false;
-- Una sola etapa ganada y una sola perdida vigentes por embudo.
CREATE UNIQUE INDEX IF NOT EXISTS ux_crm_etapas_cierre
    ON crm_etapas (id_embudo, tipo) WHERE tipo IN ('ganada', 'perdida') AND eliminado = false;


-- ── 2. Catálogos por empresa ────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS crm_origenes (
    id           SERIAL PRIMARY KEY,
    id_empresa   INT NOT NULL,
    nombre       VARCHAR(100) NOT NULL,
    tipo_canal   VARCHAR(30) NULL,     -- instagram_dm, instagram_comentario, linkedin, whatsapp, referido, web, otro
    estado       VARCHAR(10) NOT NULL DEFAULT 'activo',
    created_at   TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at   TIMESTAMP NULL,
    created_by   INT NULL,
    updated_by   INT NULL,
    eliminado    BOOLEAN NOT NULL DEFAULT false,
    deleted_at   TIMESTAMP NULL,
    deleted_by   INT NULL
);
COMMENT ON TABLE crm_origenes IS 'CRM: de dónde llegó el contacto/oportunidad.';
CREATE INDEX IF NOT EXISTS ix_crm_origenes_empresa ON crm_origenes (id_empresa) WHERE eliminado = false;

CREATE TABLE IF NOT EXISTS crm_motivos_perdida (
    id           SERIAL PRIMARY KEY,
    id_empresa   INT NOT NULL,
    nombre       VARCHAR(150) NOT NULL,
    estado       VARCHAR(10) NOT NULL DEFAULT 'activo',
    created_at   TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at   TIMESTAMP NULL,
    created_by   INT NULL,
    updated_by   INT NULL,
    eliminado    BOOLEAN NOT NULL DEFAULT false,
    deleted_at   TIMESTAMP NULL,
    deleted_by   INT NULL
);
COMMENT ON TABLE crm_motivos_perdida IS 'CRM: motivos para marcar una oportunidad como perdida.';
CREATE INDEX IF NOT EXISTS ix_crm_motivos_perdida_empresa ON crm_motivos_perdida (id_empresa) WHERE eliminado = false;


-- ── 3. Canales conectados (Instagram) ───────────────────────────────────────

CREATE TABLE IF NOT EXISTS crm_canales (
    id               SERIAL PRIMARY KEY,
    id_empresa       INT NOT NULL,
    tipo             VARCHAR(20) NOT NULL DEFAULT 'instagram',   -- instagram (fase 2: whatsapp, messenger)
    id_externo       VARCHAR(64) NOT NULL,                       -- IG user id de la cuenta profesional
    usuario          VARCHAR(100) NULL,                          -- @usuario
    nombre           VARCHAR(150) NULL,
    token_cifrado    TEXT NULL,                                  -- CryptoHelper::encriptar()
    token_expira_at  TIMESTAMP NULL,
    permisos         JSONB NULL,                                 -- scopes concedidos
    ventana_humana   BOOLEAN NOT NULL DEFAULT false,             -- app con HUMAN_AGENT aprobado (7 días)
    config           JSONB NULL,                                 -- regla de asignación, usuarios en rotación, etc.
    estado           VARCHAR(15) NOT NULL DEFAULT 'activo'
                     CHECK (estado IN ('activo', 'desconectado', 'token_vencido')),
    created_at       TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at       TIMESTAMP NULL,
    created_by       INT NULL,
    updated_by       INT NULL,
    eliminado        BOOLEAN NOT NULL DEFAULT false,
    deleted_at       TIMESTAMP NULL,
    deleted_by       INT NULL
);
COMMENT ON TABLE crm_canales IS 'CRM: cuentas de redes conectadas por empresa. El token va cifrado y nunca se envía al navegador.';
-- Una cuenta de Instagram no puede estar conectada a dos empresas a la vez.
CREATE UNIQUE INDEX IF NOT EXISTS ux_crm_canales_externo
    ON crm_canales (tipo, id_externo) WHERE eliminado = false;
CREATE INDEX IF NOT EXISTS ix_crm_canales_empresa ON crm_canales (id_empresa) WHERE eliminado = false;


-- ── 3b. App de Meta de cada empresa ─────────────────────────────────────────
-- Cada empresa registra SU app de Meta (Configuración del CRM). Los secretos van
-- cifrados (CryptoHelper); del token de verificación del webhook se guarda además
-- su SHA-256 para encontrar la empresa en la verificación de Meta sin descifrar.
CREATE TABLE IF NOT EXISTS crm_meta_apps (
    id                    SERIAL PRIMARY KEY,
    id_empresa            INT NOT NULL,
    app_id                VARCHAR(40) NOT NULL,       -- Instagram App ID (no es secreto)
    app_secret_cifrado    TEXT NOT NULL,
    verify_token_cifrado  TEXT NOT NULL,
    verify_token_hash     CHAR(64) NOT NULL,
    estado                VARCHAR(10) NOT NULL DEFAULT 'activo' CHECK (estado IN ('activo', 'inactivo')),
    created_at            TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at            TIMESTAMP NULL,
    created_by            INT NULL,
    updated_by            INT NULL,
    eliminado             BOOLEAN NOT NULL DEFAULT false,
    deleted_at            TIMESTAMP NULL,
    deleted_by            INT NULL
);
COMMENT ON TABLE crm_meta_apps IS 'CRM: app de Meta (Instagram) de cada empresa. Secretos cifrados; nunca se envían al navegador.';
CREATE UNIQUE INDEX IF NOT EXISTS ux_crm_meta_apps_empresa ON crm_meta_apps (id_empresa) WHERE eliminado = false;
CREATE INDEX IF NOT EXISTS ix_crm_meta_apps_verify ON crm_meta_apps (verify_token_hash) WHERE eliminado = false;
CREATE INDEX IF NOT EXISTS ix_crm_meta_apps_app ON crm_meta_apps (app_id) WHERE eliminado = false;


-- ── 4. Contactos (lead o cliente) ───────────────────────────────────────────

CREATE TABLE IF NOT EXISTS crm_contactos (
    id                 SERIAL PRIMARY KEY,
    id_empresa         INT NOT NULL,
    nombre             VARCHAR(200) NOT NULL,
    empresa_contacto   VARCHAR(200) NULL,
    cargo              VARCHAR(100) NULL,
    telefono           VARCHAR(50) NULL,
    correo             VARCHAR(150) NULL,
    instagram_id       VARCHAR(64) NULL,      -- IGSID
    instagram_usuario  VARCHAR(100) NULL,
    linkedin_url       VARCHAR(300) NULL,
    id_cliente         INT NULL,              -- clientes.id al convertir
    id_whatsapp_chat   INT NULL,              -- whatsapp_chats.id (enlace, no migración)
    id_origen          INT NULL REFERENCES crm_origenes(id),
    id_responsable     INT NULL,              -- usuarios.id
    notas              TEXT NULL,
    created_at         TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at         TIMESTAMP NULL,
    created_by         INT NULL,
    updated_by         INT NULL,
    eliminado          BOOLEAN NOT NULL DEFAULT false,
    deleted_at         TIMESTAMP NULL,
    deleted_by         INT NULL
);
COMMENT ON TABLE crm_contactos IS 'CRM: personas o empresas en seguimiento. Se enlazan a clientes al ganar una oportunidad.';
CREATE INDEX IF NOT EXISTS ix_crm_contactos_empresa ON crm_contactos (id_empresa) WHERE eliminado = false;
CREATE UNIQUE INDEX IF NOT EXISTS ux_crm_contactos_instagram
    ON crm_contactos (id_empresa, instagram_id) WHERE eliminado = false AND instagram_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS ix_crm_contactos_cliente
    ON crm_contactos (id_empresa, id_cliente) WHERE eliminado = false AND id_cliente IS NOT NULL;


-- ── 5. Conversaciones y mensajes ────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS crm_conversaciones (
    id                      SERIAL PRIMARY KEY,
    id_empresa              INT NOT NULL,
    id_canal                INT NOT NULL REFERENCES crm_canales(id),
    tipo                    VARCHAR(15) NOT NULL DEFAULT 'dm' CHECK (tipo IN ('dm', 'comentario')),
    id_hilo_externo         VARCHAR(100) NOT NULL,   -- IGSID (dm) o id del comentario raíz
    id_publicacion_externa  VARCHAR(64) NULL,        -- media id del post (comentario)
    id_contacto             INT NULL REFERENCES crm_contactos(id),
    id_responsable          INT NULL,
    estado                  VARCHAR(10) NOT NULL DEFAULT 'abierta' CHECK (estado IN ('abierta', 'cerrada')),
    bot_pausado             BOOLEAN NOT NULL DEFAULT false,   -- reservado fase 2
    ultimo_mensaje          VARCHAR(200) NULL,
    ultimo_mensaje_at       TIMESTAMP NULL,
    ultima_entrada_at       TIMESTAMP NULL,          -- último mensaje del cliente (ventana 24 h)
    sin_leer                INT NOT NULL DEFAULT 0,
    created_at              TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at              TIMESTAMP NULL,
    created_by              INT NULL,
    updated_by              INT NULL,
    eliminado               BOOLEAN NOT NULL DEFAULT false,
    deleted_at              TIMESTAMP NULL,
    deleted_by              INT NULL
);
COMMENT ON TABLE crm_conversaciones IS 'CRM: hilos de mensajes directos o de comentarios por canal.';
CREATE UNIQUE INDEX IF NOT EXISTS ux_crm_conversaciones_hilo
    ON crm_conversaciones (id_empresa, id_canal, tipo, id_hilo_externo) WHERE eliminado = false;
-- Lista de la bandeja: más recientes primero.
CREATE INDEX IF NOT EXISTS ix_crm_conversaciones_bandeja
    ON crm_conversaciones (id_empresa, ultimo_mensaje_at DESC) WHERE eliminado = false;
CREATE INDEX IF NOT EXISTS ix_crm_conversaciones_contacto
    ON crm_conversaciones (id_contacto) WHERE eliminado = false;

CREATE TABLE IF NOT EXISTS crm_mensajes (
    id                SERIAL PRIMARY KEY,
    id_empresa        INT NOT NULL,
    id_conversacion   INT NOT NULL REFERENCES crm_conversaciones(id),
    direccion         VARCHAR(3) NOT NULL CHECK (direccion IN ('IN', 'OUT')),
    autor             VARCHAR(10) NOT NULL DEFAULT 'cliente' CHECK (autor IN ('cliente', 'usuario', 'bot')),
    id_usuario        INT NULL,
    tipo              VARCHAR(20) NOT NULL DEFAULT 'text',
    texto             TEXT NULL,
    adjunto_ruta      VARCHAR(300) NULL,
    id_externo        VARCHAR(150) NULL,      -- mid del mensaje / id del comentario
    estado_envio      VARCHAR(15) NULL,       -- enviando / enviado / leido / fallido
    error             TEXT NULL,
    contenido         JSONB NULL,             -- payload crudo de Meta
    fecha             TIMESTAMP NOT NULL DEFAULT NOW(),
    created_at        TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at        TIMESTAMP NULL,
    created_by        INT NULL,
    updated_by        INT NULL,
    eliminado         BOOLEAN NOT NULL DEFAULT false,
    deleted_at        TIMESTAMP NULL,
    deleted_by        INT NULL
);
-- Primera versión de este archivo la creó como CHAR(3) ('IN ' con espacio al leerla):
-- si ya se aplicó, esto la corrige; si no, no hace nada.
ALTER TABLE crm_mensajes ALTER COLUMN direccion TYPE VARCHAR(3);
COMMENT ON TABLE crm_mensajes IS 'CRM: mensajes entrantes y salientes. id_externo único evita duplicar los reintentos de Meta.';
-- Deduplicación de reintentos de Meta (INSERT ... ON CONFLICT DO NOTHING).
CREATE UNIQUE INDEX IF NOT EXISTS ux_crm_mensajes_externo
    ON crm_mensajes (id_empresa, id_externo) WHERE id_externo IS NOT NULL;
CREATE INDEX IF NOT EXISTS ix_crm_mensajes_conversacion
    ON crm_mensajes (id_conversacion, fecha) WHERE eliminado = false;


-- ── 6. Oportunidades ────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS crm_oportunidades (
    id                     SERIAL PRIMARY KEY,
    id_empresa             INT NOT NULL,
    numero                 INT NOT NULL,          -- correlativo interno por empresa (candado de empresa)
    titulo                 VARCHAR(200) NOT NULL,
    id_contacto            INT NOT NULL REFERENCES crm_contactos(id),
    id_embudo              INT NOT NULL REFERENCES crm_embudos(id),
    id_etapa               INT NOT NULL REFERENCES crm_etapas(id),
    id_responsable         INT NULL,
    id_vendedor            INT NULL,              -- vendedores.id
    valor_estimado         NUMERIC(14,2) NOT NULL DEFAULT 0,
    fecha_cierre_estimada  DATE NULL,
    id_origen              INT NULL REFERENCES crm_origenes(id),
    id_conversacion        INT NULL REFERENCES crm_conversaciones(id),
    id_proforma            INT NULL,              -- proformas_cabecera.id
    id_cliente             INT NULL,              -- clientes.id (al ganar)
    estado                 VARCHAR(10) NOT NULL DEFAULT 'abierta'
                           CHECK (estado IN ('abierta', 'ganada', 'perdida')),
    id_motivo_perdida      INT NULL REFERENCES crm_motivos_perdida(id),
    detalle_perdida        TEXT NULL,
    cerrada_at             TIMESTAMP NULL,
    etapa_desde            TIMESTAMP NOT NULL DEFAULT NOW(),
    orden_kanban           NUMERIC(20,10) NOT NULL DEFAULT 0,
    descripcion            TEXT NULL,
    created_at             TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at             TIMESTAMP NULL,
    created_by             INT NULL,
    updated_by             INT NULL,
    eliminado              BOOLEAN NOT NULL DEFAULT false,
    deleted_at             TIMESTAMP NULL,
    deleted_by             INT NULL
);
COMMENT ON TABLE crm_oportunidades IS 'CRM: oportunidades de venta en un embudo.';
CREATE UNIQUE INDEX IF NOT EXISTS ux_crm_oportunidades_numero
    ON crm_oportunidades (id_empresa, numero);
-- Kanban: tarjetas por embudo y etapa.
CREATE INDEX IF NOT EXISTS ix_crm_oportunidades_kanban
    ON crm_oportunidades (id_empresa, id_embudo, id_etapa, orden_kanban) WHERE eliminado = false;
-- Registros propios: creadas por o asignadas a un usuario.
CREATE INDEX IF NOT EXISTS ix_crm_oportunidades_responsable
    ON crm_oportunidades (id_empresa, id_responsable) WHERE eliminado = false;
CREATE INDEX IF NOT EXISTS ix_crm_oportunidades_contacto
    ON crm_oportunidades (id_contacto) WHERE eliminado = false;

CREATE TABLE IF NOT EXISTS crm_oportunidades_etapas_log (
    id                  SERIAL PRIMARY KEY,
    id_empresa          INT NOT NULL,
    id_oportunidad      INT NOT NULL REFERENCES crm_oportunidades(id),
    id_etapa_anterior   INT NULL REFERENCES crm_etapas(id),
    id_etapa_nueva      INT NOT NULL REFERENCES crm_etapas(id),
    fecha               TIMESTAMP NOT NULL DEFAULT NOW(),
    id_usuario          INT NULL
);
COMMENT ON TABLE crm_oportunidades_etapas_log IS 'CRM: historial de cambios de etapa (tiempo por etapa y conversión). Solo se inserta.';
CREATE INDEX IF NOT EXISTS ix_crm_oport_etapas_log_oport
    ON crm_oportunidades_etapas_log (id_oportunidad, fecha);
CREATE INDEX IF NOT EXISTS ix_crm_oport_etapas_log_empresa
    ON crm_oportunidades_etapas_log (id_empresa, fecha);


-- ── 7. Actividades ──────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS crm_actividades (
    id               SERIAL PRIMARY KEY,
    id_empresa       INT NOT NULL,
    id_oportunidad   INT NULL REFERENCES crm_oportunidades(id),
    id_contacto      INT NULL REFERENCES crm_contactos(id),
    tipo             VARCHAR(10) NOT NULL DEFAULT 'tarea'
                     CHECK (tipo IN ('tarea', 'llamada', 'reunion', 'nota', 'correo')),
    titulo           VARCHAR(200) NOT NULL,
    descripcion      TEXT NULL,
    vence_at         TIMESTAMP NULL,
    id_responsable   INT NULL,
    completada       BOOLEAN NOT NULL DEFAULT false,
    completada_at    TIMESTAMP NULL,
    completada_por   INT NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at       TIMESTAMP NULL,
    created_by       INT NULL,
    updated_by       INT NULL,
    eliminado        BOOLEAN NOT NULL DEFAULT false,
    deleted_at       TIMESTAMP NULL,
    deleted_by       INT NULL
);
COMMENT ON TABLE crm_actividades IS 'CRM: tareas, llamadas, reuniones y notas de una oportunidad o contacto.';
CREATE INDEX IF NOT EXISTS ix_crm_actividades_oportunidad
    ON crm_actividades (id_oportunidad) WHERE eliminado = false;
-- Aviso del navbar: pendientes por responsable ordenadas por vencimiento.
CREATE INDEX IF NOT EXISTS ix_crm_actividades_pendientes
    ON crm_actividades (id_empresa, id_responsable, vence_at)
    WHERE eliminado = false AND completada = false;


-- ── 8. Cola de eventos del webhook (técnica, sin empresa al llegar) ─────────

CREATE TABLE IF NOT EXISTS crm_webhook_eventos (
    id            BIGSERIAL PRIMARY KEY,
    recibido_at   TIMESTAMP NOT NULL DEFAULT NOW(),
    objeto        VARCHAR(40) NOT NULL,          -- instagram
    payload       JSONB NOT NULL,
    firma_ok      BOOLEAN NOT NULL DEFAULT false,
    estado        VARCHAR(12) NOT NULL DEFAULT 'pendiente'
                  CHECK (estado IN ('pendiente', 'procesado', 'error', 'ignorado')),
    intentos      SMALLINT NOT NULL DEFAULT 0,
    error         TEXT NULL,
    id_empresa    INT NULL,                      -- se conoce al procesar
    procesado_at  TIMESTAMP NULL
);
COMMENT ON TABLE crm_webhook_eventos IS 'CRM: eventos crudos recibidos de Meta. Cola de procesamiento y respaldo para reprocesar; se purga a los 30 días.';
CREATE INDEX IF NOT EXISTS ix_crm_webhook_eventos_pendientes
    ON crm_webhook_eventos (recibido_at) WHERE estado IN ('pendiente', 'error');
CREATE INDEX IF NOT EXISTS ix_crm_webhook_eventos_recibido
    ON crm_webhook_eventos (recibido_at);
-- App de Meta cuya firma validó el evento: al procesar, solo se aceptan cuentas
-- de empresas que usan ESA app (una app no puede meter eventos en otra empresa).
ALTER TABLE crm_webhook_eventos ADD COLUMN IF NOT EXISTS app_id VARCHAR(40) NULL;


-- ── 8b. Solicitudes de eliminación de datos de Meta (técnica, sin empresa) ───
-- Meta llama /instagram-datos/eliminar cuando un usuario pide borrar sus datos
-- (requisito de la revisión de la app). Se guarda el código de confirmación para
-- la página de estado. No guarda datos personales: solo el id de Meta y conteos.
CREATE TABLE IF NOT EXISTS crm_eliminaciones_datos (
    id             BIGSERIAL PRIMARY KEY,
    codigo         VARCHAR(40) NOT NULL,
    tipo           VARCHAR(20) NOT NULL CHECK (tipo IN ('eliminacion', 'desautorizacion')),
    id_externo     VARCHAR(64) NOT NULL,          -- user_id del signed_request
    estado         VARCHAR(12) NOT NULL DEFAULT 'recibida'
                   CHECK (estado IN ('recibida', 'completada', 'sin_datos', 'error')),
    detalle        JSONB NULL,                    -- conteos de lo borrado
    recibido_at    TIMESTAMP NOT NULL DEFAULT NOW(),
    completado_at  TIMESTAMP NULL
);
COMMENT ON TABLE crm_eliminaciones_datos IS 'CRM: solicitudes de eliminación / desautorización recibidas de Meta y su estado (página pública por código).';
CREATE UNIQUE INDEX IF NOT EXISTS ux_crm_eliminaciones_codigo ON crm_eliminaciones_datos (codigo);


-- ── 9. Proformas: enlace a la oportunidad ───────────────────────────────────

ALTER TABLE proformas_cabecera ADD COLUMN IF NOT EXISTS id_oportunidad INT NULL;
COMMENT ON COLUMN proformas_cabecera.id_oportunidad IS 'CRM: oportunidad desde la que se creó la proforma (crm_oportunidades.id).';
CREATE INDEX IF NOT EXISTS ix_proformas_cabecera_oportunidad
    ON proformas_cabecera (id_oportunidad) WHERE id_oportunidad IS NOT NULL;


-- Comprobación (deben salir 14 filas y la columna de proformas):
-- SELECT tablename FROM pg_tables WHERE tablename LIKE 'crm\_%' ORDER BY 1;
-- SELECT column_name FROM information_schema.columns
--  WHERE table_name = 'proformas_cabecera' AND column_name = 'id_oportunidad';
