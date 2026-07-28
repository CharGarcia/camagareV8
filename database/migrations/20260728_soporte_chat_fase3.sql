-- ============================================================================
--  Chat de Soporte — Fase 3: respuestas rápidas
--  Fecha: 2026-07-28
-- ----------------------------------------------------------------------------
--  Ejecutar DESPUÉS de 20260728_create_soporte_chat.sql
--
--  El resto de la Fase 3 (adjuntos, calificación, aviso por correo) no necesita
--  tablas nuevas: las columnas ya existen en soporte_mensajes / soporte_config
--  desde la migración inicial.
-- ============================================================================

-- --- Respuestas rápidas del equipo de soporte ---------------------------------
-- Mismo patrón que whatsapp_respuestas_rapidas: id_usuario NULL = compartida por
-- toda la empresa; con valor = personal de ese agente.
--
-- id_empresa es la empresa del AGENTE (quien responde), no la de quien consulta:
-- son plantillas de trabajo del equipo de soporte.

CREATE TABLE IF NOT EXISTS soporte_respuestas_rapidas (
    id          SERIAL PRIMARY KEY,
    id_empresa  INTEGER      NOT NULL REFERENCES empresas(id),
    id_usuario  INTEGER      DEFAULT NULL,   -- NULL = de la empresa; valor = personal
    titulo      VARCHAR(100) NOT NULL,
    contenido   TEXT         NOT NULL,
    orden       INTEGER      NOT NULL DEFAULT 0,

    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN      NOT NULL DEFAULT FALSE,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER
);

CREATE INDEX IF NOT EXISTS idx_soporte_rr_empresa
    ON soporte_respuestas_rapidas (id_empresa, eliminado);

CREATE INDEX IF NOT EXISTS idx_soporte_rr_usuario
    ON soporte_respuestas_rapidas (id_empresa, id_usuario, eliminado);

COMMENT ON TABLE  soporte_respuestas_rapidas            IS 'Plantillas de respuesta reutilizables del equipo de soporte.';
COMMENT ON COLUMN soporte_respuestas_rapidas.id_usuario IS 'NULL = compartida por la empresa; con valor = personal de ese agente.';
COMMENT ON COLUMN soporte_respuestas_rapidas.id_empresa IS 'Empresa del AGENTE que responde, no la de quien consulta.';
