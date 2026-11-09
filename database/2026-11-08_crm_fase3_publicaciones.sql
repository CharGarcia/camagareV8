-- ============================================================================
--  CRM y Redes Sociales — Fase 3: publicaciones en Instagram y LinkedIn y métricas
-- ----------------------------------------------------------------------------
--  Diseño: docs/diseno/crm-redes-fase3.md (aprobado el 08-11-2026).
--  Requiere antes: database/2026-11-08_crm_fase1.sql.
--
--  - crm_linkedin_apps: app de LinkedIn de cada empresa (como crm_meta_apps).
--  - crm_canales: las cuentas de LinkedIn se guardan con tipo 'linkedin_perfil'
--    o 'linkedin_pagina' (no cambia la tabla; solo se documenta).
--  - crm_medios: biblioteca de imágenes y videos de la empresa.
--  - crm_publicaciones, crm_publicaciones_medios, crm_publicaciones_destinos:
--    la publicación, sus medios en orden y una fila por red/cuenta destino.
--  - crm_publicaciones_metricas: foto de las métricas de cada destino por corte.
--  - crm_seguidores_diarios: seguidores de cada cuenta por día.
--  - crm_publicaciones_config: aprobación previa y tope diario por empresa.
--
--  Toca datos: no (solo crea tablas).
--  Reversible: sí. Para revertir, en este orden:
--      DROP TABLE IF EXISTS crm_seguidores_diarios, crm_publicaciones_metricas,
--          crm_publicaciones_destinos, crm_publicaciones_medios, crm_publicaciones,
--          crm_medios, crm_publicaciones_config, crm_linkedin_apps;
--  Idempotente: se puede ejecutar varias veces sin efecto.
--  Listo para pegar en el Query Tool de pgAdmin y ejecutar de una vez (F5).
-- ============================================================================


-- ── 1. App de LinkedIn de cada empresa ──────────────────────────────────────

CREATE TABLE IF NOT EXISTS crm_linkedin_apps (
    id                     SERIAL PRIMARY KEY,
    id_empresa             INT NOT NULL,
    client_id              VARCHAR(60) NOT NULL,       -- no es secreto
    client_secret_cifrado  TEXT NOT NULL,              -- CryptoHelper::encriptar()
    paginas_aprobadas      BOOLEAN NOT NULL DEFAULT false,   -- la app tiene Community Management API (páginas)
    estado                 VARCHAR(10) NOT NULL DEFAULT 'activo' CHECK (estado IN ('activo', 'inactivo')),
    created_at             TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at             TIMESTAMP NULL,
    created_by             INT NULL,
    updated_by             INT NULL,
    eliminado              BOOLEAN NOT NULL DEFAULT false,
    deleted_at             TIMESTAMP NULL,
    deleted_by             INT NULL
);
COMMENT ON TABLE crm_linkedin_apps IS 'CRM fase 3: app de LinkedIn de cada empresa. El secreto va cifrado y nunca se envía al navegador.';
CREATE UNIQUE INDEX IF NOT EXISTS ux_crm_linkedin_apps_empresa ON crm_linkedin_apps (id_empresa) WHERE eliminado = false;

COMMENT ON COLUMN crm_canales.tipo IS 'instagram | linkedin_perfil | linkedin_pagina';


-- ── 2. Configuración de publicaciones por empresa ───────────────────────────

CREATE TABLE IF NOT EXISTS crm_publicaciones_config (
    id                   SERIAL PRIMARY KEY,
    id_empresa           INT NOT NULL,
    requiere_aprobacion  BOOLEAN NOT NULL DEFAULT false,
    max_publicaciones_dia INT NOT NULL DEFAULT 25 CHECK (max_publicaciones_dia BETWEEN 1 AND 100),
    created_at           TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at           TIMESTAMP NULL,
    created_by           INT NULL,
    updated_by           INT NULL,
    eliminado            BOOLEAN NOT NULL DEFAULT false,
    deleted_at           TIMESTAMP NULL,
    deleted_by           INT NULL
);
COMMENT ON TABLE crm_publicaciones_config IS 'CRM fase 3: aprobación previa y tope diario de publicaciones por empresa.';
CREATE UNIQUE INDEX IF NOT EXISTS ux_crm_publicaciones_config_empresa ON crm_publicaciones_config (id_empresa) WHERE eliminado = false;


-- ── 3. Biblioteca de medios ─────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS crm_medios (
    id               SERIAL PRIMARY KEY,
    id_empresa       INT NOT NULL,
    tipo             VARCHAR(10) NOT NULL CHECK (tipo IN ('imagen', 'video')),
    ruta             VARCHAR(300) NOT NULL,          -- relativa a storage/ (crm_media/{empresa}/pub/…)
    nombre_original  VARCHAR(200) NULL,
    mime             VARCHAR(60) NOT NULL,
    bytes            BIGINT NOT NULL DEFAULT 0,
    ancho            INT NULL,
    alto             INT NULL,
    duracion_seg     NUMERIC(8, 2) NULL,             -- videos
    texto_alternativo VARCHAR(1000) NULL,
    es_ia            BOOLEAN NOT NULL DEFAULT false,  -- la imagen/video la generó una IA (sugiere la etiqueta de Instagram)
    ultimo_uso_at    TIMESTAMP NULL,                 -- para purgar los que no se usan (90 días)
    created_at       TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at       TIMESTAMP NULL,
    created_by       INT NULL,
    updated_by       INT NULL,
    eliminado        BOOLEAN NOT NULL DEFAULT false,
    deleted_at       TIMESTAMP NULL,
    deleted_by       INT NULL
);
COMMENT ON TABLE crm_medios IS 'CRM fase 3: imágenes (JPEG) y videos de la empresa para publicar. Archivos en storage/, no públicos.';
CREATE INDEX IF NOT EXISTS ix_crm_medios_empresa ON crm_medios (id_empresa, created_at DESC) WHERE eliminado = false;


-- ── 4. Publicaciones ────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS crm_publicaciones (
    id               SERIAL PRIMARY KEY,
    id_empresa       INT NOT NULL,
    titulo           VARCHAR(150) NOT NULL,          -- nombre interno (no se publica)
    texto            TEXT NULL,                      -- texto base; cada destino puede tener el suyo
    estado           VARCHAR(15) NOT NULL DEFAULT 'borrador'
                     CHECK (estado IN ('borrador', 'en_revision', 'aprobada', 'programada', 'publicando',
                                       'publicada', 'parcial', 'con_error', 'cancelada')),
    programada_at    TIMESTAMP NULL,                 -- null = publicar ahora (al aprobar/programar)
    publicada_at     TIMESTAMP NULL,
    es_contenido_ia  BOOLEAN NOT NULL DEFAULT false, -- etiqueta «hecho con IA» de Instagram
    aprobada_por     INT NULL,
    aprobada_at      TIMESTAMP NULL,
    id_responsable   INT NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at       TIMESTAMP NULL,
    created_by       INT NULL,
    updated_by       INT NULL,
    eliminado        BOOLEAN NOT NULL DEFAULT false,
    deleted_at       TIMESTAMP NULL,
    deleted_by       INT NULL
);
COMMENT ON TABLE crm_publicaciones IS 'CRM fase 3: publicaciones para redes. Eliminar aquí no borra lo ya publicado en la red.';
CREATE INDEX IF NOT EXISTS ix_crm_publicaciones_empresa ON crm_publicaciones (id_empresa, programada_at) WHERE eliminado = false;

CREATE TABLE IF NOT EXISTS crm_publicaciones_medios (
    id                SERIAL PRIMARY KEY,
    id_empresa        INT NOT NULL,
    id_publicacion    INT NOT NULL REFERENCES crm_publicaciones(id),
    id_medio          INT NOT NULL REFERENCES crm_medios(id),
    orden             INT NOT NULL DEFAULT 0,
    created_at        TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at        TIMESTAMP NULL,
    created_by        INT NULL,
    updated_by        INT NULL,
    eliminado         BOOLEAN NOT NULL DEFAULT false,
    deleted_at        TIMESTAMP NULL,
    deleted_by        INT NULL
);
CREATE INDEX IF NOT EXISTS ix_crm_publicaciones_medios_pub ON crm_publicaciones_medios (id_publicacion, orden) WHERE eliminado = false;

CREATE TABLE IF NOT EXISTS crm_publicaciones_destinos (
    id                SERIAL PRIMARY KEY,
    id_empresa        INT NOT NULL,
    id_publicacion    INT NOT NULL REFERENCES crm_publicaciones(id),
    id_canal          INT NOT NULL REFERENCES crm_canales(id),
    formato           VARCHAR(12) NOT NULL CHECK (formato IN ('imagen', 'carrusel', 'reel', 'texto', 'documento')),
    texto             TEXT NULL,                      -- texto propio de esta red (null = el de la publicación)
    estado            VARCHAR(12) NOT NULL DEFAULT 'pendiente'
                      CHECK (estado IN ('pendiente', 'preparando', 'publicando', 'publicada', 'con_error', 'cancelada')),
    intentos          INT NOT NULL DEFAULT 0,
    siguiente_intento_at TIMESTAMP NULL,
    contenedor_externo VARCHAR(64) NULL,               -- Instagram: contenedor principal creado, aún sin publicar
    contenedores      JSONB NULL,                      -- Instagram carrusel: {"hijos": [...]} ya creados
    id_externo        VARCHAR(120) NULL,               -- id de la publicación en la red
    enlace            VARCHAR(400) NULL,               -- permalink
    error             VARCHAR(500) NULL,
    publicada_at      TIMESTAMP NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at        TIMESTAMP NULL,
    created_by        INT NULL,
    updated_by        INT NULL,
    eliminado         BOOLEAN NOT NULL DEFAULT false,
    deleted_at        TIMESTAMP NULL,
    deleted_by        INT NULL
);
-- Por si se aplicó una versión anterior de este archivo sin la columna.
ALTER TABLE crm_publicaciones_destinos ADD COLUMN IF NOT EXISTS contenedores JSONB NULL;
COMMENT ON TABLE crm_publicaciones_destinos IS 'CRM fase 3: una fila por red/cuenta en que sale la publicación, con su estado propio.';
CREATE INDEX IF NOT EXISTS ix_crm_publicaciones_destinos_pub ON crm_publicaciones_destinos (id_publicacion) WHERE eliminado = false;
-- Cola del cron: lo pendiente o en reintento.
CREATE INDEX IF NOT EXISTS ix_crm_publicaciones_destinos_cola
    ON crm_publicaciones_destinos (estado, siguiente_intento_at) WHERE eliminado = false AND estado IN ('pendiente', 'preparando');
-- Atribución: conversaciones de comentarios → publicación (id_publicacion_externa = id_externo).
CREATE INDEX IF NOT EXISTS ix_crm_publicaciones_destinos_externo
    ON crm_publicaciones_destinos (id_empresa, id_externo) WHERE id_externo IS NOT NULL;


-- ── 5. Métricas ─────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS crm_publicaciones_metricas (
    id                SERIAL PRIMARY KEY,
    id_empresa        INT NOT NULL,
    id_destino        INT NOT NULL REFERENCES crm_publicaciones_destinos(id),
    corte             VARCHAR(4) NOT NULL CHECK (corte IN ('24h', '7d', '30d')),
    alcance           INT NULL,
    vistas            INT NULL,
    me_gusta          INT NULL,
    comentarios       INT NULL,
    guardados         INT NULL,
    compartidos       INT NULL,
    clics             INT NULL,                       -- LinkedIn
    crudo             JSONB NOT NULL DEFAULT '{}'::jsonb,   -- respuesta de la red, para no perder métricas nuevas
    tomada_at         TIMESTAMP NOT NULL DEFAULT NOW(),
    created_at        TIMESTAMP NOT NULL DEFAULT NOW(),
    eliminado         BOOLEAN NOT NULL DEFAULT false
);
COMMENT ON TABLE crm_publicaciones_metricas IS 'CRM fase 3: métricas de cada destino a las 24 h, 7 días y 30 días de publicado.';
CREATE UNIQUE INDEX IF NOT EXISTS ux_crm_publicaciones_metricas_corte
    ON crm_publicaciones_metricas (id_destino, corte) WHERE eliminado = false;

CREATE TABLE IF NOT EXISTS crm_seguidores_diarios (
    id            SERIAL PRIMARY KEY,
    id_empresa    INT NOT NULL,
    id_canal      INT NOT NULL REFERENCES crm_canales(id),
    fecha         DATE NOT NULL,
    seguidores    INT NOT NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT NOW(),
    eliminado     BOOLEAN NOT NULL DEFAULT false
);
COMMENT ON TABLE crm_seguidores_diarios IS 'CRM fase 3: seguidores de cada cuenta por día.';
CREATE UNIQUE INDEX IF NOT EXISTS ux_crm_seguidores_diarios ON crm_seguidores_diarios (id_canal, fecha) WHERE eliminado = false;


-- Comprobación (deben salir 8 tablas):
-- SELECT table_name FROM information_schema.tables
--  WHERE table_name IN ('crm_linkedin_apps', 'crm_publicaciones_config', 'crm_medios', 'crm_publicaciones',
--                       'crm_publicaciones_medios', 'crm_publicaciones_destinos', 'crm_publicaciones_metricas',
--                       'crm_seguidores_diarios')
--  ORDER BY table_name;
