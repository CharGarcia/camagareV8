-- ============================================================================
-- Facturación desde Consignaciones en Ventas (tabla puente)
-- Ruta MVC: reutiliza modulos/consignaciones-ventas (pestaña Facturación del modal)
--
-- Registra qué facturas de venta se generaron a partir de cada consignación
-- ENTREGADA y por qué cantidades, para poder calcular el saldo facturable
-- (consignado - retornado - facturado) y revertir automáticamente al anular/
-- eliminar la factura de origen.
--
-- Flujo contable/inventario (confirmado con el usuario):
--   Al facturar se REINGRESA a la bodega de origen la cantidad a facturar
--   (entrada espejo + asiento inverso Debe Inventario / Haber Mercadería en
--   Consignación) y luego la Factura de Venta opera de forma NORMAL (salida de
--   inventario + asiento de venta estándar). La factura queda ligada aquí a su
--   consignación de origen.
--
-- Reglas del sistema: multiempresa (id_empresa), eliminación lógica y auditoría.
-- ============================================================================

-- ---------------------------------------------------------------------------
-- Cabecera del vínculo: una factura generada desde UNA consignación.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS consignaciones_facturas (
    id                     SERIAL PRIMARY KEY,
    id_empresa             INTEGER NOT NULL,
    id_consignacion        INTEGER NOT NULL,
    id_factura             INTEGER,                   -- ventas_cabecera.id (nulo mientras se crea el vínculo)
    numero_factura         VARCHAR(50),
    subtotal               NUMERIC(15,6) DEFAULT 0,
    impuesto               NUMERIC(15,6) DEFAULT 0,
    total                  NUMERIC(15,6) DEFAULT 0,
    id_asiento_reingreso   INTEGER,                   -- asiento inverso (Inventario/Mercadería)
    estado                 VARCHAR(20) DEFAULT 'activa', -- activa | reversada
    created_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by             INTEGER,
    updated_by             INTEGER,
    eliminado              BOOLEAN DEFAULT FALSE,
    deleted_at             TIMESTAMP,
    deleted_by             INTEGER
);

-- ---------------------------------------------------------------------------
-- Detalle: cantidad facturada por cada línea de consignación de origen.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS consignaciones_facturas_detalles (
    id                        SERIAL PRIMARY KEY,
    id_consignacion_factura   INTEGER NOT NULL REFERENCES consignaciones_facturas(id) ON DELETE CASCADE,
    id_empresa                INTEGER NOT NULL,
    id_consignacion           INTEGER NOT NULL,
    id_consignacion_detalle   INTEGER NOT NULL,
    id_producto               INTEGER NOT NULL,
    cantidad                  NUMERIC(15,6) NOT NULL,
    precio_unitario           NUMERIC(15,6) NOT NULL DEFAULT 0,
    id_bodega                 INTEGER,
    lote                      VARCHAR(100),
    nup                       VARCHAR(100),
    fecha_caducidad           DATE,
    created_at                TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    eliminado                 BOOLEAN DEFAULT FALSE,
    deleted_at                TIMESTAMP,
    deleted_by                INTEGER
);

CREATE INDEX IF NOT EXISTS idx_cons_facturas_empresa      ON consignaciones_facturas (id_empresa, eliminado);
CREATE INDEX IF NOT EXISTS idx_cons_facturas_consig       ON consignaciones_facturas (id_consignacion);
CREATE INDEX IF NOT EXISTS idx_cons_facturas_factura      ON consignaciones_facturas (id_factura);
CREATE INDEX IF NOT EXISTS idx_cons_facturas_det_cabecera ON consignaciones_facturas_detalles (id_consignacion_factura);
CREATE INDEX IF NOT EXISTS idx_cons_facturas_det_consdet  ON consignaciones_facturas_detalles (id_consignacion_detalle);
CREATE INDEX IF NOT EXISTS idx_cons_facturas_det_consig   ON consignaciones_facturas_detalles (id_consignacion);
