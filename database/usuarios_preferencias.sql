-- migracion_favoritos.sql
CREATE TABLE IF NOT EXISTS usuarios_preferencias (
    id SERIAL PRIMARY KEY,
    id_usuario INT NOT NULL,
    id_empresa INT NOT NULL,
    modulo VARCHAR(100) NOT NULL,
    preferencias JSONB DEFAULT '{}'::JSONB,
    UNIQUE(id_usuario, id_empresa, modulo)
);
