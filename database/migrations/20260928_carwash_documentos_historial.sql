-- ============================================================================
--  Car-Wash: historial de documentos de venta emitidos desde cada orden
--  (pestaña "Facturación" del modal) + índices para la pestaña "Historial".
--
--  Una orden puede tener VARIOS documentos en el tiempo: si su factura/recibo se
--  anula o elimina, la orden se libera y vuelve a facturarse; el documento anterior
--  queda aquí. La migración desde el sistema anterior (orden_mecanica →
--  registros_facturados) también llena esta tabla (origen = 'migracion').
--
--  Idempotente: se puede ejecutar más de una vez.
-- ============================================================================

CREATE TABLE IF NOT EXISTS carwash_ordenes_documentos (
    id                  SERIAL PRIMARY KEY,
    id_empresa          INTEGER NOT NULL,
    id_orden            INTEGER NOT NULL REFERENCES carwash_ordenes(id) ON DELETE CASCADE,
    tipo_documento      VARCHAR(10) NOT NULL,          -- 'FACTURA' | 'RECIBO'
    id_documento        INTEGER,                       -- ventas_cabecera.id / recibos_venta_cabecera.id (NULL si el documento del sistema anterior no se migró)
    numero_documento    VARCHAR(25),
    fecha_emision       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total               NUMERIC(14,2) NOT NULL DEFAULT 0,
    origen              VARCHAR(12) NOT NULL DEFAULT 'sistema', -- 'sistema' | 'migracion'
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by          INTEGER,
    updated_by          INTEGER,
    eliminado           BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at          TIMESTAMP,
    deleted_by          INTEGER
);

CREATE INDEX IF NOT EXISTS idx_carwash_docs_orden   ON carwash_ordenes_documentos (id_orden) WHERE eliminado = false;
CREATE INDEX IF NOT EXISTS idx_carwash_docs_empresa ON carwash_ordenes_documentos (id_empresa, tipo_documento, id_documento);

-- Historial por vehículo / cliente (pestaña Historial): órdenes más recientes primero.
CREATE INDEX IF NOT EXISTS idx_carwash_ordenes_veh_fecha ON carwash_ordenes (id_empresa, id_vehiculo, fecha_ingreso DESC) WHERE eliminado = false;
CREATE INDEX IF NOT EXISTS idx_carwash_ordenes_cli_fecha ON carwash_ordenes (id_empresa, id_cliente, fecha_ingreso DESC) WHERE eliminado = false;

-- Backfill: las órdenes ya facturadas antes de esta tabla pasan a su historial.
INSERT INTO carwash_ordenes_documentos
       (id_empresa, id_orden, tipo_documento, id_documento, numero_documento, fecha_emision, total, origen, created_by, updated_by)
SELECT o.id_empresa, o.id, o.tipo_documento, o.id_documento, o.numero_documento,
       COALESCE(o.updated_at, o.created_at, CURRENT_TIMESTAMP), o.total, 'sistema', o.updated_by, o.updated_by
  FROM carwash_ordenes o
 WHERE o.tipo_documento IS NOT NULL
   AND o.eliminado = false
   AND NOT EXISTS (SELECT 1 FROM carwash_ordenes_documentos d
                    WHERE d.id_orden = o.id AND d.tipo_documento = o.tipo_documento
                      AND d.numero_documento IS NOT DISTINCT FROM o.numero_documento);
