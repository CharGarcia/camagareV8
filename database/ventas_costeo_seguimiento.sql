-- ============================================================================
-- Seguimiento de costeo de ventas (Costo de Ventas / Inventario)
-- ============================================================================
-- Problema que resuelve: el asiento de Costo de Ventas se fusiona dentro del
-- mismo asiento comercial de la Factura/Recibo/NC de Venta (AsientoBuilderService).
-- Antes de esta tabla, "¿está pendiente de costeo?" se respondía con una
-- heurística SQL en SincronizadorAsientosService que solo miraba la cuenta
-- configurada a nivel GENERAL (asientos_programados.tipo_referencia = 'asientos
-- tipo') — quedaba ciega a cuentas configuradas por Cliente/Producto/Categoría/
-- Marca, que es donde de verdad se resuelve la cascada real en
-- AsientoBuilderService::repartirVentasCascada() y equivalentes. Un documento
-- podía quedar con el costo sin generar para siempre, sin ningún aviso preciso.
--
-- Esta tabla registra el RESULTADO REAL en el momento exacto en que
-- AsientoBuilderService ya resolvió la cascada completa (no una aproximación
-- posterior): si el documento necesitaba costo (kardex con costo_total > 0),
-- si el bloque terminó incluido en el asiento, cuánto, y si no se incluyó, por
-- qué. SincronizadorAsientosService pasa a preguntarle a esta tabla en vez de
-- reconstruir la heurística.
--
-- id_asiento_contable NO se guarda aquí a propósito: en el momento en que
-- AsientoBuilderService resuelve la cascada, el asiento todavía no tiene id
-- (se asigna después, al guardar). Se puede obtener siempre por
-- ventas_cabecera/recibos_venta_cabecera/notas_credito_cabecera.id_asiento_contable
-- vía id_documento — no duplicar esa referencia aquí evita que se desincronicen.
-- ============================================================================

BEGIN;

CREATE TABLE IF NOT EXISTS ventas_costeo_seguimiento (
    id                  BIGSERIAL PRIMARY KEY,
    id_empresa          INTEGER NOT NULL,
    tipo_documento      VARCHAR(30) NOT NULL,   -- 'factura_venta' | 'recibo_venta' | 'nota_credito_venta'
    id_documento        INTEGER NOT NULL,

    requiere_costo      BOOLEAN NOT NULL DEFAULT FALSE, -- el kardex reportó costo_total > 0 para este documento
    costo_generado      BOOLEAN NOT NULL DEFAULT FALSE, -- el bloque de Costo/Inventario quedó incluido en el asiento
    monto_costo         NUMERIC(14,2) NOT NULL DEFAULT 0,
    motivo_pendiente    VARCHAR(60) NULL,       -- NULL si no aplica (no requiere costo, o ya se generó);
                                                  -- 'cuenta_no_configurada' | 'bloque_incompleto_descuadrado' si sigue pendiente

    created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_by INTEGER,
    eliminado BOOLEAN DEFAULT FALSE,
    deleted_at TIMESTAMP WITHOUT TIME ZONE,
    deleted_by INTEGER,

    CONSTRAINT fk_costeo_seguimiento_empresa FOREIGN KEY (id_empresa) REFERENCES empresas(id),
    CONSTRAINT uq_costeo_seguimiento UNIQUE (id_empresa, tipo_documento, id_documento)
);

COMMENT ON TABLE ventas_costeo_seguimiento IS
    'Resultado real (no heurística) de si un documento de venta necesitaba y recibió su bloque de Costo de Ventas/Inventario. Escrita por AsientoBuilderService, leída por SincronizadorAsientosService.';

CREATE INDEX IF NOT EXISTS idx_costeo_seguimiento_pendientes
    ON ventas_costeo_seguimiento (id_empresa, tipo_documento)
    WHERE requiere_costo = TRUE AND costo_generado = FALSE AND eliminado = FALSE;

COMMIT;

-- ── VERIFICACIÓN ─────────────────────────────────────────────────────────────
SELECT table_name FROM information_schema.tables WHERE table_name = 'ventas_costeo_seguimiento';
