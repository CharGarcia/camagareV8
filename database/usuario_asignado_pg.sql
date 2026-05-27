-- PostgreSQL: tabla usuario_asignado
-- Vincula usuarios finales (nivel 1) con sus administradores (nivel 2)

CREATE TABLE usuario_asignado (
    id              SERIAL PRIMARY KEY,
    id_usuario      INTEGER NOT NULL REFERENCES usuarios (id) ON DELETE CASCADE,
    id_adm          INTEGER NOT NULL REFERENCES usuarios (id) ON DELETE CASCADE,
    fecha_agregado  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uk_usuario_asignado_relacion UNIQUE (id_usuario, id_adm)
);

CREATE INDEX idx_usuario_asignado_usuario ON usuario_asignado (id_usuario);
CREATE INDEX idx_usuario_asignado_admin ON usuario_asignado (id_adm);

COMMENT ON TABLE usuario_asignado IS 'Relación entre usuarios y administradores que los gestionan';
