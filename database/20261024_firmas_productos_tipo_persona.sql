-- =============================================================================
-- Firmas Electrónicas — para qué tipo de persona es cada tipo de firma (producto).
-- Sin fila, la firma sirve para persona natural y para representante legal; con
-- fila, solo para el tipo indicado ('natural' | 'juridica'). Evita que el cliente
-- elija "Persona natural" con una firma de representante legal (o al revés).
-- Se configura en el engranaje del módulo, pestaña "Tipo de persona".
-- Idempotente: se puede ejecutar más de una vez.
-- =============================================================================

CREATE TABLE IF NOT EXISTS firmas_productos_tipo_persona (
    id           SERIAL PRIMARY KEY,
    id_empresa   INTEGER NOT NULL,
    id_producto  INTEGER NOT NULL,
    tipo_persona VARCHAR(10) NOT NULL CHECK (tipo_persona IN ('natural', 'juridica')),
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP,
    created_by   INTEGER,
    updated_by   INTEGER,
    eliminado    BOOLEAN NOT NULL DEFAULT false,
    deleted_at   TIMESTAMP,
    deleted_by   INTEGER
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_firmas_productos_tipo_persona
    ON firmas_productos_tipo_persona (id_empresa, id_producto)
    WHERE eliminado = false;
