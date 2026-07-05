-- ============================================================================
-- Módulo: Facturación de Consignaciones en Ventas (modulos/facturacion-cv)
-- Despliegue completo: tablas del documento + registro en el menú.
--
-- Es un DOCUMENTO con estructura de factura (fecha, serie, secuencial propios,
-- cliente a facturar, vendedor) que agrupa líneas de UNA o VARIAS consignaciones
-- ENTREGADAS (parcial con saldo) y, en un segundo paso, GENERA la Factura de
-- Venta relacionada (numeración aparte). Se puede facturar a un cliente distinto
-- al de las consignaciones.
--
-- Flujo: se guarda como 'borrador' (editable) -> botón "Generar factura" crea la
-- factura (reingreso de inventario + factura normal) y deja el documento
-- 'facturada'. Al anular la factura, la reversión es automática ('anulada').
--
-- Reglas: multiempresa (id_empresa), eliminación lógica y auditoría.
-- Idempotente: se puede ejecutar más de una vez.
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1. Cabecera del documento de facturación de consignación
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS consignaciones_facturas (
    id                     SERIAL PRIMARY KEY,
    id_empresa             INTEGER NOT NULL,
    fecha_emision          DATE,
    serie                  VARCHAR(7),
    secuencial             VARCHAR(20),
    id_punto_emision       INTEGER,
    establecimiento        VARCHAR(3),
    punto_emision          VARCHAR(3),
    tipo_ambiente          VARCHAR(1) DEFAULT '1',
    id_cliente             INTEGER,                      -- cliente a facturar (puede diferir del de la consignación)
    id_vendedor            INTEGER,
    observaciones          TEXT,
    id_factura             INTEGER,                      -- ventas_cabecera.id (se llena al Generar factura)
    numero_factura         VARCHAR(50),
    subtotal               NUMERIC(15,6) DEFAULT 0,
    impuesto               NUMERIC(15,6) DEFAULT 0,
    total                  NUMERIC(15,6) DEFAULT 0,
    id_asiento_reingreso   INTEGER,                      -- asiento inverso (Inventario / Mercadería en Consignación)
    info_adicional         TEXT,                          -- JSON [{nombre, valor}] (info adicional del comprobante)
    estado                 VARCHAR(20) DEFAULT 'borrador', -- borrador | facturada | anulada
    created_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by             INTEGER,
    updated_by             INTEGER,
    eliminado              BOOLEAN DEFAULT FALSE,
    deleted_at             TIMESTAMP,
    deleted_by             INTEGER
);

-- Evolución para instalaciones que ya crearon la tabla en su versión anterior:
ALTER TABLE consignaciones_facturas ADD COLUMN IF NOT EXISTS fecha_emision    DATE;
ALTER TABLE consignaciones_facturas ADD COLUMN IF NOT EXISTS serie            VARCHAR(7);
ALTER TABLE consignaciones_facturas ADD COLUMN IF NOT EXISTS secuencial       VARCHAR(20);
ALTER TABLE consignaciones_facturas ADD COLUMN IF NOT EXISTS id_punto_emision INTEGER;
ALTER TABLE consignaciones_facturas ADD COLUMN IF NOT EXISTS establecimiento  VARCHAR(3);
ALTER TABLE consignaciones_facturas ADD COLUMN IF NOT EXISTS punto_emision    VARCHAR(3);
ALTER TABLE consignaciones_facturas ADD COLUMN IF NOT EXISTS tipo_ambiente    VARCHAR(1) DEFAULT '1';
ALTER TABLE consignaciones_facturas ADD COLUMN IF NOT EXISTS id_cliente       INTEGER;
ALTER TABLE consignaciones_facturas ADD COLUMN IF NOT EXISTS id_vendedor      INTEGER;
ALTER TABLE consignaciones_facturas ADD COLUMN IF NOT EXISTS observaciones    TEXT;
ALTER TABLE consignaciones_facturas ADD COLUMN IF NOT EXISTS numero_factura   VARCHAR(50);
ALTER TABLE consignaciones_facturas ADD COLUMN IF NOT EXISTS subtotal         NUMERIC(15,6) DEFAULT 0;
ALTER TABLE consignaciones_facturas ADD COLUMN IF NOT EXISTS impuesto         NUMERIC(15,6) DEFAULT 0;
ALTER TABLE consignaciones_facturas ADD COLUMN IF NOT EXISTS total            NUMERIC(15,6) DEFAULT 0;
ALTER TABLE consignaciones_facturas ADD COLUMN IF NOT EXISTS id_asiento_reingreso INTEGER;
ALTER TABLE consignaciones_facturas ADD COLUMN IF NOT EXISTS info_adicional   TEXT;

-- La cabecera del documento NO usa id_consignacion (vive en el detalle). Si una versión
-- previa creó la columna como NOT NULL, se relaja para no romper el INSERT del documento.
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns
               WHERE table_name = 'consignaciones_facturas' AND column_name = 'id_consignacion') THEN
        ALTER TABLE consignaciones_facturas ALTER COLUMN id_consignacion DROP NOT NULL;
    END IF;
END $$;

-- ---------------------------------------------------------------------------
-- 2. Detalle: cada línea proviene de una línea de consignación (varias por doc)
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
    descuento                 NUMERIC(15,6) DEFAULT 0,
    id_impuesto               INTEGER,
    porcentaje_impuesto       NUMERIC(5,2) DEFAULT 0,
    valor_impuesto            NUMERIC(15,6) DEFAULT 0,
    subtotal                  NUMERIC(15,6) DEFAULT 0,
    total                     NUMERIC(15,6) DEFAULT 0,
    id_bodega                 INTEGER,
    lote                      VARCHAR(100),
    nup                       VARCHAR(100),
    fecha_caducidad           DATE,
    created_at                TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    eliminado                 BOOLEAN DEFAULT FALSE,
    deleted_at                TIMESTAMP,
    deleted_by                INTEGER
);

ALTER TABLE consignaciones_facturas_detalles ADD COLUMN IF NOT EXISTS id_impuesto         INTEGER;
ALTER TABLE consignaciones_facturas_detalles ADD COLUMN IF NOT EXISTS porcentaje_impuesto NUMERIC(5,2) DEFAULT 0;
ALTER TABLE consignaciones_facturas_detalles ADD COLUMN IF NOT EXISTS valor_impuesto      NUMERIC(15,6) DEFAULT 0;
ALTER TABLE consignaciones_facturas_detalles ADD COLUMN IF NOT EXISTS subtotal            NUMERIC(15,6) DEFAULT 0;
ALTER TABLE consignaciones_facturas_detalles ADD COLUMN IF NOT EXISTS total               NUMERIC(15,6) DEFAULT 0;
ALTER TABLE consignaciones_facturas_detalles ADD COLUMN IF NOT EXISTS descuento           NUMERIC(15,6) DEFAULT 0;

CREATE INDEX IF NOT EXISTS idx_cons_facturas_empresa      ON consignaciones_facturas (id_empresa, eliminado);
CREATE INDEX IF NOT EXISTS idx_cons_facturas_consig       ON consignaciones_facturas (id_consignacion);
CREATE INDEX IF NOT EXISTS idx_cons_facturas_factura      ON consignaciones_facturas (id_factura);
CREATE INDEX IF NOT EXISTS idx_cons_facturas_punto        ON consignaciones_facturas (id_punto_emision, tipo_ambiente);
CREATE INDEX IF NOT EXISTS idx_cons_facturas_det_cabecera ON consignaciones_facturas_detalles (id_consignacion_factura);
CREATE INDEX IF NOT EXISTS idx_cons_facturas_det_consdet  ON consignaciones_facturas_detalles (id_consignacion_detalle);
CREATE INDEX IF NOT EXISTS idx_cons_facturas_det_consig   ON consignaciones_facturas_detalles (id_consignacion);

-- NOTA: la columna id_consignacion de la cabecera de versiones previas queda sin uso
-- (ahora la relación con la(s) consignación(es) vive en el detalle). No se elimina para
-- no romper datos existentes.

-- ---------------------------------------------------------------------------
-- 3. Registro del submódulo en el menú (ruta MVC = modulos/facturacion-cv)
-- ---------------------------------------------------------------------------
INSERT INTO submodulos_menu (nombre_submodulo, ruta, id_modulo, orden, id_icono, status)
SELECT 'Facturación de consignaciones',
       'modulos/facturacion-cv',
       s.id_modulo,
       (SELECT COALESCE(MAX(orden), 0) + 1 FROM submodulos_menu WHERE id_modulo = s.id_modulo),
       s.id_icono,
       1
FROM submodulos_menu s
WHERE s.ruta = 'modulos/consignaciones-ventas'
  AND NOT EXISTS (SELECT 1 FROM submodulos_menu WHERE ruta = 'modulos/facturacion-cv')
LIMIT 1;

INSERT INTO submodulos_menu (nombre_submodulo, ruta, id_modulo, orden, id_icono, status)
SELECT 'Facturación de consignaciones',
       'modulos/facturacion-cv',
       s.id_modulo,
       (SELECT COALESCE(MAX(orden), 0) + 1 FROM submodulos_menu WHERE id_modulo = s.id_modulo),
       s.id_icono,
       1
FROM submodulos_menu s
WHERE s.ruta = 'modulos/factura-venta'
  AND NOT EXISTS (SELECT 1 FROM submodulos_menu WHERE ruta = 'modulos/facturacion-cv')
LIMIT 1;

-- Verificación (obtener el id para asignar permisos en /config/permisos-modulos):
SELECT id, nombre_submodulo, ruta FROM submodulos_menu WHERE ruta = 'modulos/facturacion-cv';

-- IMPORTANTE: además, configurar el secuencial del tipo de documento
-- 'Facturacion consignaciones ventas' por punto de emisión en Empresa / Secuenciales.
