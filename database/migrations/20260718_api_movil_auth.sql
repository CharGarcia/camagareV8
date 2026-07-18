-- App móvil (Fase 0): canal de sesión, refresh tokens de API y vínculo usuario-responsable de traslado.
-- Deploy manual: subir y ejecutar contra la BD de producción cuando corresponda.

-- 1) Distingue sesiones web vs móvil para que un usuario pueda tener una de cada canal
--    simultáneamente (SesionActivaRepository/Service ya filtran por canal en el código).
ALTER TABLE sesiones_activas ADD COLUMN IF NOT EXISTS canal VARCHAR(10) NOT NULL DEFAULT 'web';

-- 2) Refresh tokens de la API móvil (rotativos, revocables). Solo se guarda el hash.
--    session_token: ata el refresh token a LA sesión activa (sesiones_activas) vigente
--    al emitirlo. Si esa sesión es desplazada por un login con force_login desde otro
--    dispositivo, el refresh debe fallar (no basta con "haya alguna sesión activa del
--    canal"), o el dispositivo desplazado podría seguir renovando su acceso.
CREATE TABLE IF NOT EXISTS api_refresh_tokens (
    id             SERIAL PRIMARY KEY,
    id_usuario     INTEGER NOT NULL REFERENCES usuarios(id),
    token_hash     VARCHAR(128) NOT NULL UNIQUE,
    session_token  VARCHAR(64),
    dispositivo_id VARCHAR(128),
    canal          VARCHAR(10) NOT NULL DEFAULT 'movil',
    ip             VARCHAR(45),
    user_agent     TEXT,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at     TIMESTAMP NOT NULL,
    last_used_at   TIMESTAMP,
    revoked        BOOLEAN NOT NULL DEFAULT FALSE,
    revoked_at     TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_api_refresh_tokens_usuario
    ON api_refresh_tokens (id_usuario)
    WHERE revoked = FALSE;

-- 3) Vínculo usuario (login) <-> responsable de traslado (catálogo de Pedidos/Consignaciones).
--    Tabla independiente: no modifica 'usuarios' ni 'responsables_traslado'.
CREATE TABLE IF NOT EXISTS usuarios_responsables_traslado (
    id                       SERIAL PRIMARY KEY,
    id_empresa               INTEGER NOT NULL REFERENCES empresas(id),
    id_usuario               INTEGER NOT NULL REFERENCES usuarios(id),
    id_responsable_traslado  INTEGER NOT NULL REFERENCES responsables_traslado(id),
    created_at               TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at               TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by               INTEGER,
    updated_by               INTEGER,
    eliminado                BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at                TIMESTAMP,
    deleted_by                INTEGER,
    UNIQUE (id_empresa, id_usuario, id_responsable_traslado)
);
CREATE INDEX IF NOT EXISTS idx_usu_resp_traslado_usuario
    ON usuarios_responsables_traslado (id_usuario)
    WHERE eliminado = FALSE;
