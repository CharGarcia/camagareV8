-- ============================================================================
-- Módulo: Cambios de productos (`modulos/cambio-producto-cv`)
-- Submódulo ya existente en submodulos_menu (id 10). Modelado sobre Retornos CV.
--
-- Un CAMBIO DE PRODUCTOS registra, en un solo documento y por cliente:
--   - lo que el cliente DEVUELVE (líneas tipo_linea='devolucion') → ENTRADA de
--     inventario. Su origen es una línea de FACTURA de venta (ventas_detalle) o
--     una línea de ENTREGA de un cambio anterior (encadenado). Se controla el saldo.
--   - lo que el cliente RECIBE a cambio (líneas tipo_linea='entrega') → SALIDA de
--     inventario. Producto de catálogo, con bodega elegible.
--
-- La diferencia de valor (entregado − devuelto) es solo INFORMATIVA (no genera CXC).
-- Inventario y asiento contable (a costo) van ligados al estado 'Emitida'.
--
-- Reglas del sistema:
--   - Multiempresa: todas las tablas llevan id_empresa.
--   - Eliminación lógica: eliminado / deleted_at / deleted_by.
--   - Auditoría: created_at/by, updated_at/by.
-- ============================================================================

-- ---------------------------------------------------------------------------
-- Cabecera: un cambio es de UN cliente; agrupa líneas de devolución y de entrega.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cambios_producto_cv (
    id                      SERIAL PRIMARY KEY,
    id_empresa              INTEGER NOT NULL,
    fecha_cambio            DATE NOT NULL,
    serie                   VARCHAR(7)  NOT NULL,
    secuencial              VARCHAR(20) NOT NULL,
    id_punto_emision        INTEGER,
    establecimiento         VARCHAR(3),
    punto_emision           VARCHAR(3),
    tipo_ambiente           VARCHAR(1) DEFAULT '1',
    id_cliente              INTEGER NOT NULL,
    id_responsable_traslado INTEGER,
    motivo                  VARCHAR(255),
    observaciones           TEXT,
    estado                  VARCHAR(50) DEFAULT 'Emitida',
    subtotal_devuelto       NUMERIC(15,6) DEFAULT 0,
    subtotal_entregado      NUMERIC(15,6) DEFAULT 0,
    diferencia              NUMERIC(15,6) DEFAULT 0,
    id_asiento_contable     INTEGER NULL,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by              INTEGER,
    updated_by              INTEGER,
    eliminado               BOOLEAN DEFAULT FALSE,
    deleted_at              TIMESTAMP,
    deleted_by              INTEGER
);

-- ---------------------------------------------------------------------------
-- Detalle: ambas colecciones en una tabla, diferenciadas por tipo_linea.
--   tipo_linea = 'devolucion' → ENTRADA. origen_tipo ('FACTURA'|'CAMBIO'),
--       id_origen = id_venta o id_cambio previo; id_origen_detalle = id de la
--       línea de origen (ventas_detalle.id o cambios_producto_cv_detalles.id).
--   tipo_linea = 'entrega'    → SALIDA. origen_tipo NULL (producto de catálogo).
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cambios_producto_cv_detalles (
    id                      SERIAL PRIMARY KEY,
    id_cambio               INTEGER NOT NULL REFERENCES cambios_producto_cv(id) ON DELETE CASCADE,
    id_empresa              INTEGER NOT NULL,
    tipo_linea              VARCHAR(12) NOT NULL,          -- 'devolucion' | 'entrega'
    origen_tipo             VARCHAR(10),                   -- 'FACTURA' | 'CAMBIO' | NULL
    id_origen               INTEGER,                       -- id_venta | id_cambio (previo)
    id_origen_detalle       INTEGER,                       -- ventas_detalle.id | cambios_producto_cv_detalles.id
    id_producto             INTEGER NOT NULL,
    cantidad                NUMERIC(15,6) NOT NULL,
    precio_unitario         NUMERIC(15,6) NOT NULL DEFAULT 0,
    subtotal                NUMERIC(15,6) NOT NULL DEFAULT 0,
    id_impuesto             INTEGER,
    porcentaje_impuesto     NUMERIC(5,2) DEFAULT 0,
    valor_impuesto          NUMERIC(15,6) DEFAULT 0,
    total                   NUMERIC(15,6) DEFAULT 0,
    id_bodega               INTEGER,
    lote                    VARCHAR(100),
    nup                     VARCHAR(100),
    fecha_caducidad         DATE,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    eliminado               BOOLEAN DEFAULT FALSE,
    deleted_at              TIMESTAMP,
    deleted_by              INTEGER
);

CREATE INDEX IF NOT EXISTS idx_cambios_pcv_empresa      ON cambios_producto_cv (id_empresa, eliminado);
CREATE INDEX IF NOT EXISTS idx_cambios_pcv_cliente      ON cambios_producto_cv (id_cliente);
CREATE INDEX IF NOT EXISTS idx_cambios_pcv_punto        ON cambios_producto_cv (id_punto_emision, tipo_ambiente);
CREATE INDEX IF NOT EXISTS idx_cambios_pcv_det_cambio   ON cambios_producto_cv_detalles (id_cambio);
CREATE INDEX IF NOT EXISTS idx_cambios_pcv_det_origen   ON cambios_producto_cv_detalles (origen_tipo, id_origen_detalle);
CREATE INDEX IF NOT EXISTS idx_cambios_pcv_det_tipo     ON cambios_producto_cv_detalles (id_cambio, tipo_linea);
