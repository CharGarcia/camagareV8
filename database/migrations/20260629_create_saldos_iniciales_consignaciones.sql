-- =============================================================
-- MÓDULO: Saldos Iniciales — Consignaciones de Venta (registro)
-- Solo registra el saldo pendiente de mercadería consignada a
-- clientes antes de migrar al sistema. NO afecta inventario ni
-- genera documentos en el módulo de consignaciones.
-- Una fila por producto consignado (puede compartir nro_documento).
-- =============================================================

CREATE TABLE IF NOT EXISTS saldos_iniciales_consignaciones (
    id               SERIAL PRIMARY KEY,
    id_empresa       INTEGER NOT NULL,
    fecha_emision    DATE NOT NULL,
    nro_documento    VARCHAR(50),
    -- Cliente (debe existir registrado)
    id_cliente       INTEGER,
    nombre_cliente   VARCHAR(255) NOT NULL,
    ruc_cliente      VARCHAR(20),
    -- Vendedor / bodega (opcionales)
    id_vendedor      INTEGER,
    nombre_vendedor  VARCHAR(255),
    id_bodega        INTEGER,
    nombre_bodega    VARCHAR(255),
    -- Producto consignado (debe existir registrado)
    id_producto      INTEGER,
    producto_codigo  VARCHAR(50),
    producto_nombre  VARCHAR(255) NOT NULL,
    cantidad         NUMERIC(14,2) NOT NULL DEFAULT 0,
    precio_unitario  NUMERIC(14,2) NOT NULL DEFAULT 0,
    total            NUMERIC(14,2) NOT NULL DEFAULT 0,
    -- Lote / trazabilidad (opcionales)
    lote             VARCHAR(50),
    fecha_caducidad  DATE,
    nup              VARCHAR(100),
    observaciones    TEXT,
    estado           VARCHAR(20) NOT NULL DEFAULT 'PENDIENTE'
                         CHECK (estado IN ('PENDIENTE','LIQUIDADO')),
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by       INTEGER,
    updated_by       INTEGER,
    eliminado        BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at       TIMESTAMP,
    deleted_by       INTEGER
);
CREATE INDEX IF NOT EXISTS idx_saldos_consig_empresa  ON saldos_iniciales_consignaciones(id_empresa, eliminado);
CREATE INDEX IF NOT EXISTS idx_saldos_consig_cliente  ON saldos_iniciales_consignaciones(id_cliente);
CREATE INDEX IF NOT EXISTS idx_saldos_consig_producto ON saldos_iniciales_consignaciones(id_producto);
