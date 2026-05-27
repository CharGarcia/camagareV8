-- Nueva tabla para los responsables de traslado (entregas de pedidos)
CREATE TABLE IF NOT EXISTS responsables_traslado (
    id SERIAL PRIMARY KEY,
    id_empresa INTEGER NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    identificacion VARCHAR(20),
    telefono VARCHAR(20),
    estado VARCHAR(20) DEFAULT 'activo',
    created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_by INTEGER,
    eliminado BOOLEAN DEFAULT FALSE,
    deleted_at TIMESTAMP WITHOUT TIME ZONE,
    deleted_by INTEGER,
    CONSTRAINT fk_responsable_empresa FOREIGN KEY (id_empresa) REFERENCES empresas(id)
);

ALTER TABLE pedidos_cabecera DROP COLUMN IF EXISTS responsable_entrega;
ALTER TABLE pedidos_cabecera ADD COLUMN IF NOT EXISTS id_responsable_entrega INTEGER;
