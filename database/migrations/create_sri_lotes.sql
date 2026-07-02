-- ============================================================================
-- Módulo: Envío en lote de comprobantes electrónicos al SRI
-- ----------------------------------------------------------------------------
-- Cola persistida para enviar comprobantes (facturas, notas de crédito,
-- retenciones de compra y liquidaciones de compra) al SRI en segundo plano
-- mediante un worker CLI (scripts/procesar_lote_sri.php).
--
-- Tablas operativas (multiempresa): llevan id_empresa, auditoría y eliminado.
-- ============================================================================

-- ── Cabecera del lote ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS sri_lotes (
    id              BIGSERIAL   PRIMARY KEY,
    id_empresa      INTEGER     NOT NULL,
    -- pendiente | procesando | completado | completado_con_errores | cancelado
    estado          VARCHAR(30) NOT NULL DEFAULT 'pendiente',
    tipo_ambiente   VARCHAR(1)  NOT NULL DEFAULT '1',   -- 1=Pruebas, 2=Producción
    total           INTEGER     NOT NULL DEFAULT 0,
    procesados      INTEGER     NOT NULL DEFAULT 0,
    exitosos        INTEGER     NOT NULL DEFAULT 0,
    fallidos        INTEGER     NOT NULL DEFAULT 0,
    filtros_json    TEXT,                               -- filtros usados (traza)
    iniciado_at     TIMESTAMP,
    finalizado_at   TIMESTAMP,
    -- Auditoría estándar
    created_at      TIMESTAMP   NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMP   NOT NULL DEFAULT NOW(),
    created_by      INTEGER,
    updated_by      INTEGER,
    eliminado       BOOLEAN     NOT NULL DEFAULT FALSE,
    deleted_at      TIMESTAMP,
    deleted_by      INTEGER
);

CREATE INDEX IF NOT EXISTS idx_sri_lotes_empresa_estado
    ON sri_lotes (id_empresa, estado) WHERE eliminado = FALSE;

COMMENT ON TABLE  sri_lotes IS 'Lotes de envío de comprobantes al SRI (procesados en segundo plano).';
COMMENT ON COLUMN sri_lotes.estado IS 'pendiente | procesando | completado | completado_con_errores | cancelado';

-- ── Ítems del lote (un comprobante por fila) ────────────────────────────────
CREATE TABLE IF NOT EXISTS sri_lote_items (
    id                  BIGSERIAL   PRIMARY KEY,
    id_lote             BIGINT      NOT NULL REFERENCES sri_lotes(id) ON DELETE CASCADE,
    id_empresa          INTEGER     NOT NULL,
    -- factura_venta | nota_credito | retencion_compra | liquidacion_compra
    tipo_comprobante    VARCHAR(30) NOT NULL,
    id_comprobante      INTEGER     NOT NULL,
    numero              VARCHAR(40),                    -- 001-001-000000123 (display)
    fecha_emision       DATE,
    -- pendiente | procesando | autorizado | devuelto | no_autorizado | error
    estado              VARCHAR(20) NOT NULL DEFAULT 'pendiente',
    mensaje             TEXT,
    numero_autorizacion VARCHAR(60),
    intentos            INTEGER     NOT NULL DEFAULT 0,
    processed_at        TIMESTAMP,
    created_at          TIMESTAMP   NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_sri_lote_items_lote_estado
    ON sri_lote_items (id_lote, estado);

COMMENT ON TABLE  sri_lote_items IS 'Comprobantes que componen un lote de envío al SRI.';
COMMENT ON COLUMN sri_lote_items.estado IS 'pendiente | procesando | autorizado | devuelto | no_autorizado | error';
