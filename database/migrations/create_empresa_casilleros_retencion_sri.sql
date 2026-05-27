-- Crear tabla para casilleros de retenciones SRI por empresa
CREATE TABLE IF NOT EXISTS empresa_casilleros_retencion_sri (
    id                  SERIAL PRIMARY KEY,
    id_empresa          INTEGER NOT NULL,
    id_retencion        INTEGER NOT NULL,   -- FK a retenciones_sri.id
    casillero_compras   VARCHAR(20) DEFAULT '',
    casillero_ventas    VARCHAR(20) DEFAULT '',
    eliminado           BOOLEAN NOT NULL DEFAULT false,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at          TIMESTAMP,
    created_by          INTEGER,
    updated_by          INTEGER,
    deleted_by          INTEGER,
    UNIQUE (id_empresa, id_retencion)
);
