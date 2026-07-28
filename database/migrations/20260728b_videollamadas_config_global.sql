-- MIGRATION: Configuración GLOBAL de videollamadas
-- ---------------------------------------------------------------------------
-- Ejecutar DESPUÉS de 20260728_create_videollamadas.sql
--
-- POR QUÉ EXISTE ESTA TABLA
-- Los servidores STUN/TURN los contrata y paga CaMaGaRe una sola vez para todas
-- las empresas. Tenerlos únicamente en videollamadas_config (que lleva
-- id_empresa) obligaría a cargar las mismas credenciales tantas veces como
-- empresas haya, y a repetir la operación cada vez que se rote el token.
--
-- Esta tabla es configuración GLOBAL del sistema, así que NO lleva id_empresa
-- (§4 de CLAUDE.md). Una empresa puede seguir poniendo sus propios servidores en
-- videollamadas_config: lo que ella defina gana sobre lo global, y lo que deje
-- vacío se hereda de aquí.
--
-- Solo el nivel 3 (superadministrador) puede leerla o modificarla.
--
-- ES UNA MIGRACIÓN PURAMENTE ADITIVA: una tabla nueva, sin tocar nada existente.
-- ---------------------------------------------------------------------------
BEGIN;

CREATE TABLE IF NOT EXISTS videollamadas_config_global (
    id SERIAL PRIMARY KEY,

    -- Servidores que heredan todas las empresas que no definan los suyos.
    stun_urls TEXT DEFAULT 'stun:stun.l.google.com:19302',
    turn_urls TEXT,
    turn_usuario VARCHAR(200),
    turn_credencial TEXT,
    -- Credenciales efímeras (Cloudflare Realtime). Se guardan cifradas.
    turn_api_token TEXT,
    turn_key_id VARCHAR(100),

    -- Valores sugeridos para las empresas nuevas.
    max_participantes_defecto INTEGER NOT NULL DEFAULT 6,
    duracion_max_defecto INTEGER NOT NULL DEFAULT 120,

    -- Si se apaga, ninguna empresa puede sobrescribir los servidores globales.
    permite_override_empresa BOOLEAN NOT NULL DEFAULT TRUE,

    eliminado BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_by INTEGER,
    deleted_at TIMESTAMP,
    deleted_by INTEGER
);

-- Es una configuración única para todo el sistema: una sola fila viva.
-- El índice va sobre una expresión que siempre da TRUE, así que dos filas activas
-- chocarían entre sí.
CREATE UNIQUE INDEX IF NOT EXISTS uq_vc_config_global_unica
    ON videollamadas_config_global ((id IS NOT NULL)) WHERE eliminado = FALSE;

-- Fila inicial con el STUN gratuito de Google y sin TURN.
INSERT INTO videollamadas_config_global (stun_urls)
SELECT 'stun:stun.l.google.com:19302'
WHERE NOT EXISTS (SELECT 1 FROM videollamadas_config_global WHERE eliminado = FALSE);

COMMIT;

-- ─────────────────────────────────────────────────────────────────────────
-- CÓMO QUEDA LA CASCADA
--
--   Servidor efectivo de una empresa =
--       el que ella definió en videollamadas_config,
--       o el de videollamadas_config_global si lo dejó vacío.
--
--   Los límites (máximo de participantes, duración) NO cascadean: son siempre
--   de cada empresa. Los valores de esta tabla solo sirven como sugerencia al
--   crear la configuración de una empresa nueva.
-- ─────────────────────────────────────────────────────────────────────────
