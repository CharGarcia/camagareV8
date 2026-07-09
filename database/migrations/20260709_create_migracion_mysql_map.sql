-- =============================================================
-- Módulo "Migrar desde base anterior (MySQL)"
-- Mapa de correspondencia ID viejo (MySQL) -> ID nuevo (PostgreSQL) por entidad.
-- Es la pieza central del ETL: los documentos referencian ids viejos
-- (id_cliente, id_producto, id_proveedor...) y aquí se resuelven a los nuevos.
-- =============================================================
CREATE TABLE IF NOT EXISTS migracion_mysql_map (
    id             SERIAL PRIMARY KEY,
    id_empresa     INTEGER      NOT NULL,
    entidad        VARCHAR(30)  NOT NULL,   -- clientes, productos, proveedores, facturas, ...
    id_origen      BIGINT       NOT NULL,   -- id en la BD MySQL vieja
    id_destino     INTEGER      NOT NULL,   -- id en la BD nueva
    clave_natural  VARCHAR(120),            -- identificación/código para referencia
    vinculado      BOOLEAN      NOT NULL DEFAULT FALSE, -- true = se enlazó a uno ya existente (no insertado)
    created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by     INTEGER
);
-- Un id de origen por entidad y empresa (idempotencia / anti-reproceso).
CREATE UNIQUE INDEX IF NOT EXISTS uq_migmap_origen
    ON migracion_mysql_map(id_empresa, entidad, id_origen);
CREATE INDEX IF NOT EXISTS idx_migmap_destino
    ON migracion_mysql_map(id_empresa, entidad, id_destino);
