-- ============================================================================
--  CRM y Redes Sociales — Fase 2: asistente virtual (bot con IA)
-- ----------------------------------------------------------------------------
--  Diseño: docs/diseno/crm-redes-fase2.md (aprobado el 08-11-2026).
--  Requiere antes: database/2026-11-08_crm_fase1.sql y la tabla ia_config
--  (database/migrations/20260713_create_ia_soporte.sql).
--
--  - crm_bot_config: configuración del asistente por empresa y cuenta (canal).
--  - crm_conversaciones: estado del bot en cada conversación y su cola.
--  - crm_bot_ejecuciones: una fila por llamada al modelo (tokens, costo,
--    herramientas, resultado). No guarda claves ni el prompt completo.
--  - ia_config.esfuerzo: nivel de razonamiento para proveedores que lo admiten.
--
--  Toca datos: no (crea tablas y columnas con valor por defecto).
--  Reversible: sí. Para revertir, en este orden:
--      DROP TABLE IF EXISTS crm_bot_ejecuciones, crm_bot_config;
--      ALTER TABLE crm_conversaciones DROP COLUMN IF EXISTS bot_estado,
--          DROP COLUMN IF EXISTS bot_pendiente_at, DROP COLUMN IF EXISTS bot_respuestas,
--          DROP COLUMN IF EXISTS bot_intentos, DROP COLUMN IF EXISTS bot_motivo_traspaso;
--      ALTER TABLE ia_config DROP COLUMN IF EXISTS esfuerzo;
--  Idempotente: se puede ejecutar varias veces sin efecto.
--  Listo para pegar en el Query Tool de pgAdmin y ejecutar de una vez (F5).
-- ============================================================================


-- ── 1. Configuración del asistente por cuenta ───────────────────────────────

CREATE TABLE IF NOT EXISTS crm_bot_config (
    id                     SERIAL PRIMARY KEY,
    id_empresa             INT NOT NULL,
    id_canal               INT NOT NULL REFERENCES crm_canales(id),
    activo                 BOOLEAN NOT NULL DEFAULT false,
    nombre_asistente       VARCHAR(60) NOT NULL DEFAULT 'Asistente virtual',
    presentacion           VARCHAR(500) NULL,     -- primer mensaje; vacío = uno por defecto
    tono                   VARCHAR(20) NOT NULL DEFAULT 'cercano'
                           CHECK (tono IN ('cercano', 'formal')),
    horario                VARCHAR(20) NOT NULL DEFAULT 'siempre'
                           CHECK (horario IN ('siempre', 'fuera_horario')),
    horario_atencion       JSONB NOT NULL DEFAULT '{}'::jsonb,   -- {"1":["08:00","17:00"], …} (1 = lunes)
    instrucciones          TEXT NULL,             -- lo que la empresa quiere que sepa o haga
    temas_prohibidos       TEXT NULL,
    mostrar_stock          BOOLEAN NOT NULL DEFAULT true,         -- decisión 3: sí/no, sin cantidades
    crear_oportunidades    BOOLEAN NOT NULL DEFAULT true,         -- decisión 7
    max_respuestas_conv    INT NOT NULL DEFAULT 6 CHECK (max_respuestas_conv BETWEEN 1 AND 50),
    max_respuestas_dia     INT NOT NULL DEFAULT 300 CHECK (max_respuestas_dia BETWEEN 1 AND 100000),
    created_at             TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at             TIMESTAMP NULL,
    created_by             INT NULL,
    updated_by             INT NULL,
    eliminado              BOOLEAN NOT NULL DEFAULT false,
    deleted_at             TIMESTAMP NULL,
    deleted_by             INT NULL
);
COMMENT ON TABLE crm_bot_config IS 'CRM fase 2: asistente virtual (IA) por empresa y cuenta de Instagram.';
CREATE UNIQUE INDEX IF NOT EXISTS ux_crm_bot_config_canal
    ON crm_bot_config (id_empresa, id_canal) WHERE eliminado = false;


-- ── 2. Estado del bot en cada conversación ──────────────────────────────────
--  bot_estado: activo (responde) | pausado (una persona tomó la conversación o
--  la pausó) | traspasada (el bot la pasó a una persona). bot_pausado (fase 1)
--  se mantiene como espejo de «no activo» para no romper lecturas existentes.

ALTER TABLE crm_conversaciones ADD COLUMN IF NOT EXISTS bot_estado VARCHAR(12) NOT NULL DEFAULT 'activo';
ALTER TABLE crm_conversaciones ADD COLUMN IF NOT EXISTS bot_pendiente_at TIMESTAMP NULL;   -- cola: responder desde esta hora
ALTER TABLE crm_conversaciones ADD COLUMN IF NOT EXISTS bot_respuestas INT NOT NULL DEFAULT 0;
ALTER TABLE crm_conversaciones ADD COLUMN IF NOT EXISTS bot_intentos INT NOT NULL DEFAULT 0;   -- fallos seguidos del proveedor
ALTER TABLE crm_conversaciones ADD COLUMN IF NOT EXISTS bot_motivo_traspaso VARCHAR(300) NULL;
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'ck_crm_conversaciones_bot_estado') THEN
        ALTER TABLE crm_conversaciones ADD CONSTRAINT ck_crm_conversaciones_bot_estado
            CHECK (bot_estado IN ('activo', 'pausado', 'traspasada'));
    END IF;
END $$;
-- Cola del cron: solo las que esperan respuesta.
CREATE INDEX IF NOT EXISTS ix_crm_conversaciones_bot_cola
    ON crm_conversaciones (bot_pendiente_at) WHERE bot_pendiente_at IS NOT NULL AND eliminado = false;


-- ── 3. Registro de cada llamada al modelo ───────────────────────────────────

CREATE TABLE IF NOT EXISTS crm_bot_ejecuciones (
    id                 SERIAL PRIMARY KEY,
    id_empresa         INT NOT NULL,
    id_conversacion    INT NULL REFERENCES crm_conversaciones(id),   -- null = «Probar» desde Configuración
    proveedor          VARCHAR(30) NOT NULL,
    modelo             VARCHAR(60) NOT NULL,
    tokens_entrada     INT NOT NULL DEFAULT 0,
    tokens_salida      INT NOT NULL DEFAULT 0,
    tokens_cache       INT NOT NULL DEFAULT 0,
    costo_estimado     NUMERIC(12, 6) NOT NULL DEFAULT 0,             -- USD, referencial
    herramientas       JSONB NOT NULL DEFAULT '[]'::jsonb,            -- nombres usados, sin datos
    resultado          VARCHAR(20) NOT NULL
                       CHECK (resultado IN ('enviado', 'traspaso', 'sin_respuesta', 'error', 'prueba', 'omitido')),
    detalle            VARCHAR(500) NULL,                             -- motivo de traspaso o error (sin secretos)
    duracion_ms        INT NULL,
    created_at         TIMESTAMP NOT NULL DEFAULT NOW(),
    created_by         INT NULL,
    eliminado          BOOLEAN NOT NULL DEFAULT false
);
COMMENT ON TABLE crm_bot_ejecuciones IS 'CRM fase 2: consumo y resultado de cada respuesta del asistente virtual.';
CREATE INDEX IF NOT EXISTS ix_crm_bot_ejecuciones_empresa_fecha
    ON crm_bot_ejecuciones (id_empresa, created_at);
CREATE INDEX IF NOT EXISTS ix_crm_bot_ejecuciones_conv
    ON crm_bot_ejecuciones (id_conversacion) WHERE id_conversacion IS NOT NULL;


-- ── 4. Esfuerzo de razonamiento en la configuración de IA ───────────────────
--  Solo lo usan los proveedores que lo admiten (Anthropic). Vacío = el del proveedor.

ALTER TABLE ia_config ADD COLUMN IF NOT EXISTS esfuerzo VARCHAR(10) NULL;


-- Comprobación (2 tablas; 6 columnas bot_* en conversaciones —5 nuevas más bot_pausado de la fase 1— y esfuerzo en ia_config):
-- SELECT table_name FROM information_schema.tables WHERE table_name IN ('crm_bot_config', 'crm_bot_ejecuciones');
-- SELECT table_name, column_name FROM information_schema.columns
--  WHERE (table_name = 'crm_conversaciones' AND column_name LIKE 'bot\_%')
--     OR (table_name = 'ia_config' AND column_name = 'esfuerzo');
