-- =============================================================================
-- Firmas Electrónicas — vigencia de cada tipo de firma (producto), para calcular
-- la fecha de caducidad. Sin fila, la vigencia se deduce del nombre del producto
-- ("1 año", "2 años", "30 días"…); esta tabla guarda el ajuste manual.
-- Idempotente: se puede ejecutar más de una vez.
-- =============================================================================

CREATE TABLE IF NOT EXISTS firmas_productos_vigencia (
    id          SERIAL PRIMARY KEY,
    id_empresa  INTEGER NOT NULL,
    id_producto INTEGER NOT NULL,
    cantidad    INTEGER NOT NULL CHECK (cantidad > 0),
    unidad      VARCHAR(10) NOT NULL CHECK (unidad IN ('dias', 'meses', 'anios')),
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN NOT NULL DEFAULT false,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_firmas_productos_vigencia
    ON firmas_productos_vigencia (id_empresa, id_producto)
    WHERE eliminado = false;
