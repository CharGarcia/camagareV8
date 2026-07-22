-- =============================================================================
-- Migración: Módulo de Cotización de Publicidad
-- Fecha: 2026-07-22
-- Descripción: Tablas para cotizaciones de servicios de publicidad (agencia),
--              con comisión de agencia, categorías propias, costos por proveedor
--              (utilidad) y conversión a Factura de Venta real.
-- =============================================================================

-- ─── CATÁLOGO DE CATEGORÍAS DE SERVICIO (propio del módulo, por empresa) ─────
CREATE TABLE IF NOT EXISTS cotizacion_publicidad_categorias (
    id          SERIAL PRIMARY KEY,
    id_empresa  INTEGER NOT NULL,
    nombre      VARCHAR(150) NOT NULL,
    status      BOOLEAN NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER
);

COMMENT ON TABLE cotizacion_publicidad_categorias IS 'Categorías/tipos de servicio publicitario (Radio, TV, Vía pública, Digital, etc.), definidas por empresa';

CREATE INDEX IF NOT EXISTS idx_cotpub_categorias_empresa ON cotizacion_publicidad_categorias(id_empresa);

-- ─── TABLA CABECERA ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS cotizacion_publicidad_cabecera (
    id                    SERIAL PRIMARY KEY,
    id_empresa            INTEGER NOT NULL,
    id_cliente            INTEGER NOT NULL,
    id_vendedor           INTEGER,
    id_usuario            INTEGER NOT NULL,
    contacto              VARCHAR(200),
    fecha_emision         DATE NOT NULL,
    proyecto              VARCHAR(300),
    numero                INTEGER NOT NULL,
    version               INTEGER NOT NULL DEFAULT 1,
    anio                  INTEGER GENERATED ALWAYS AS (EXTRACT(YEAR FROM fecha_emision)::INTEGER) STORED,
    presupuesto           NUMERIC(14,2) NOT NULL DEFAULT 0,
    id_tarifa_iva         INTEGER NOT NULL DEFAULT 0,
    comision              NUMERIC(5,2) NOT NULL DEFAULT 17,
    observaciones         TEXT,
    estado                VARCHAR(20) NOT NULL DEFAULT 'borrador',
    total_sin_impuestos   NUMERIC(14,2) NOT NULL DEFAULT 0,
    total_comision        NUMERIC(14,2) NOT NULL DEFAULT 0,
    total_iva             NUMERIC(14,2) NOT NULL DEFAULT 0,
    importe_total         NUMERIC(14,2) NOT NULL DEFAULT 0,
    moneda                VARCHAR(10) NOT NULL DEFAULT 'DOLAR',
    id_factura_convertida INTEGER,
    fecha_convertida      TIMESTAMP,
    created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by            INTEGER,
    updated_by            INTEGER,
    eliminado             BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at            TIMESTAMP,
    deleted_by            INTEGER
);

COMMENT ON TABLE cotizacion_publicidad_cabecera IS 'Cabecera de cotizaciones de servicios de publicidad (comisión de agencia + IVA, sin autorización SRI)';
COMMENT ON COLUMN cotizacion_publicidad_cabecera.estado IS 'borrador | aprobada | rechazada | convertida | anulada';
COMMENT ON COLUMN cotizacion_publicidad_cabecera.numero IS 'Numeración por cliente + año + versión (no correlativo global)';
COMMENT ON COLUMN cotizacion_publicidad_cabecera.version IS 'Nueva versión = fila nueva con mismo numero, version+1 (clon de una cotización existente)';
COMMENT ON COLUMN cotizacion_publicidad_cabecera.comision IS 'Porcentaje de comisión de agencia, aplicado sobre el subtotal antes del IVA';
COMMENT ON COLUMN cotizacion_publicidad_cabecera.id_factura_convertida IS 'FK a ventas_cabecera cuando se convierte a factura';

-- ─── TABLA DETALLE ───────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS cotizacion_publicidad_detalle (
    id                          SERIAL PRIMARY KEY,
    id_cotizacion               INTEGER NOT NULL REFERENCES cotizacion_publicidad_cabecera(id),
    id_categoria                INTEGER REFERENCES cotizacion_publicidad_categorias(id),
    descripcion                 VARCHAR(500) NOT NULL,
    precio_unitario             NUMERIC(14,6) NOT NULL DEFAULT 0,
    ciudades                    INTEGER NOT NULL DEFAULT 1,
    dias                        INTEGER NOT NULL DEFAULT 1,
    cantidad                    NUMERIC(14,4) NOT NULL DEFAULT 1,
    precio_total_sin_impuesto   NUMERIC(14,2) NOT NULL DEFAULT 0,
    id_proveedor                INTEGER,
    id_compra                   INTEGER,
    factura_proveedor           VARCHAR(50),
    valor_costo                 NUMERIC(14,2) NOT NULL DEFAULT 0,
    observacion_costo           VARCHAR(300),
    created_at                  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

COMMENT ON TABLE cotizacion_publicidad_detalle IS 'Líneas de servicio de la cotización. ciudades/dias son informativos (no multiplican el precio)';
COMMENT ON COLUMN cotizacion_publicidad_detalle.precio_total_sin_impuesto IS 'precio_unitario * cantidad (ciudades y dias no afectan el cálculo)';
COMMENT ON COLUMN cotizacion_publicidad_detalle.id_proveedor IS 'Proveedor real del costo (módulo de costos/utilidad), asignado manualmente';

-- Si esta migración ya se había ejecutado antes de agregar id_compra, el
-- CREATE TABLE de arriba fue un no-op y la columna no se creó: se agrega aquí
-- de forma explícita e idempotente. Nota: superada por la tabla
-- cotizacion_publicidad_costos (abajo) — se deja sin usar, no se elimina.
ALTER TABLE cotizacion_publicidad_detalle ADD COLUMN IF NOT EXISTS id_compra INTEGER;

-- ─── TABLA DE COSTOS (N proveedores/facturas por línea de cotización) ────────
-- Reemplaza a las columnas id_proveedor/id_compra/factura_proveedor/valor_costo/
-- observacion_costo de cotizacion_publicidad_detalle (que quedan sin usar, no se
-- eliminan): una misma línea cotizada puede tener costos de varios proveedores.
CREATE TABLE IF NOT EXISTS cotizacion_publicidad_costos (
    id                 SERIAL PRIMARY KEY,
    id_detalle         INTEGER NOT NULL REFERENCES cotizacion_publicidad_detalle(id),
    id_proveedor       INTEGER,
    id_compra          INTEGER,
    factura_proveedor  VARCHAR(50),
    valor_costo        NUMERIC(14,2) NOT NULL DEFAULT 0,
    observacion_costo  VARCHAR(300),
    created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

COMMENT ON TABLE cotizacion_publicidad_costos IS 'Costos reales por proveedor de cada línea cotizada (N:1 con cotizacion_publicidad_detalle) — permite varios proveedores/facturas sobre la misma línea';
COMMENT ON COLUMN cotizacion_publicidad_costos.id_compra IS 'FK lógica a compras_cabecera cuando el costo se tomó de una factura de compra ya registrada (NULL si se ingresó a mano)';

CREATE INDEX IF NOT EXISTS idx_cotpub_costos_detalle    ON cotizacion_publicidad_costos(id_detalle);
CREATE INDEX IF NOT EXISTS idx_cotpub_costos_proveedor  ON cotizacion_publicidad_costos(id_proveedor);

-- ─── ÍNDICES ─────────────────────────────────────────────────────────────────
CREATE INDEX IF NOT EXISTS idx_cotpub_empresa    ON cotizacion_publicidad_cabecera(id_empresa);
CREATE INDEX IF NOT EXISTS idx_cotpub_cliente    ON cotizacion_publicidad_cabecera(id_cliente);
CREATE INDEX IF NOT EXISTS idx_cotpub_estado     ON cotizacion_publicidad_cabecera(estado);
CREATE INDEX IF NOT EXISTS idx_cotpub_fecha      ON cotizacion_publicidad_cabecera(fecha_emision);
CREATE INDEX IF NOT EXISTS idx_cotpub_eliminado  ON cotizacion_publicidad_cabecera(eliminado);
CREATE UNIQUE INDEX IF NOT EXISTS uq_cotpub_numero ON cotizacion_publicidad_cabecera(id_empresa, id_cliente, numero, version, anio) WHERE eliminado = false;

CREATE INDEX IF NOT EXISTS idx_cotpub_det_cabecera ON cotizacion_publicidad_detalle(id_cotizacion);
CREATE INDEX IF NOT EXISTS idx_cotpub_det_proveedor ON cotizacion_publicidad_detalle(id_proveedor);

-- ─── VÍNCULO CON FACTURAS DE VENTA (misma factura puede listarse desde el origen) ───
ALTER TABLE ventas_cabecera ADD COLUMN IF NOT EXISTS id_cotizacion_publicidad INTEGER;

COMMENT ON COLUMN ventas_cabecera.id_cotizacion_publicidad IS 'FK lógica a cotizacion_publicidad_cabecera cuando la factura se generó desde una cotización de publicidad';

CREATE INDEX IF NOT EXISTS idx_ventas_id_cotizacion_publicidad ON ventas_cabecera(id_cotizacion_publicidad);

-- =============================================================================
-- NOTA: el registro del submódulo en submodulos_menu y los permisos en
-- modulos_asignados NO se incluyen en esta migración — se gestionan manualmente
-- (ver /config/permisos-modulos). Valores sugeridos:
--   nombre = 'Cotización de Publicidad'
--   ruta   = 'modulos/cotizacion-publicidad'
--   icono  = 'bi bi-megaphone'
-- =============================================================================
