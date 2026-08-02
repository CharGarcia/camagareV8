-- Plantillas de proforma: guardan una "foto" de detalle + info adicional + vigencia
-- para reutilizar al armar una proforma nueva más rápido. Reusa los permisos del
-- módulo Proformas (crear/eliminar); no es un módulo aparte.

CREATE TABLE IF NOT EXISTS proformas_plantillas (
    id               SERIAL PRIMARY KEY,
    id_empresa       INTEGER NOT NULL REFERENCES empresas(id),
    nombre           VARCHAR(150) NOT NULL,
    dias_vigencia    INTEGER NOT NULL DEFAULT 15,
    vigencia_unidad  VARCHAR(10) NOT NULL DEFAULT 'dias',
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by       INTEGER,
    updated_by       INTEGER,
    eliminado        BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at       TIMESTAMP,
    deleted_by       INTEGER
);

CREATE TABLE IF NOT EXISTS proformas_plantillas_detalle (
    id                          SERIAL PRIMARY KEY,
    id_plantilla                INTEGER NOT NULL REFERENCES proformas_plantillas(id) ON DELETE CASCADE,
    id_producto                 INTEGER,
    id_unidad_medida            INTEGER,
    codigo_principal            VARCHAR(50) NOT NULL DEFAULT '',
    codigo_auxiliar             VARCHAR(50),
    descripcion                 VARCHAR(500) NOT NULL,
    cantidad                    NUMERIC(14,4) NOT NULL DEFAULT 1,
    precio_unitario              NUMERIC(14,6) NOT NULL DEFAULT 0,
    descuento                   NUMERIC(14,2) NOT NULL DEFAULT 0,
    precio_total_sin_impuesto   NUMERIC(14,2) NOT NULL DEFAULT 0,
    id_tarifa_iva               INTEGER NOT NULL DEFAULT 0,
    info_adicional              TEXT,
    created_at                  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS proformas_plantillas_adicional (
    id             SERIAL PRIMARY KEY,
    id_plantilla   INTEGER NOT NULL REFERENCES proformas_plantillas(id) ON DELETE CASCADE,
    nombre         VARCHAR(150) NOT NULL,
    valor          VARCHAR(500) NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_proformas_plantillas_empresa ON proformas_plantillas(id_empresa) WHERE eliminado = FALSE;
CREATE INDEX IF NOT EXISTS idx_proformas_plantillas_detalle_plantilla ON proformas_plantillas_detalle(id_plantilla);
CREATE INDEX IF NOT EXISTS idx_proformas_plantillas_adicional_plantilla ON proformas_plantillas_adicional(id_plantilla);
