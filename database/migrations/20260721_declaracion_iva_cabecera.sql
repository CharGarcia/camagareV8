-- =============================================================================
-- Módulo Declaración de IVA: guardar la declaración de un período como documento
-- (hasta ahora el módulo solo calculaba en vivo, sin persistir nada).
--
-- 1) declaracion_iva_cabecera: una fila por período declarado (mensual o
--    semestral), con los valores consolidados, el arrastre del saldo a favor
--    del período anterior, y los enlaces al asiento contable y al egreso.
-- 2) Seed en asientos_tipo (catálogo global) del concepto 'declaracion_iva'
--    con las 5 cuentas configurables, siguiendo el mismo patrón que
--    seed_asientos_tipo_nomina.sql / asientos_tipo_cierre_ejercicio.sql.
--
-- Idempotente. Multiempresa (id_empresa). Eliminación lógica.
-- =============================================================================

BEGIN;

CREATE TABLE IF NOT EXISTS declaracion_iva_cabecera (
    id                        SERIAL PRIMARY KEY,
    id_empresa                INTEGER NOT NULL,
    tipo_ambiente             VARCHAR(1) NOT NULL DEFAULT '1',
    tipo_periodo              VARCHAR(10) NOT NULL,        -- mensual / semestral
    periodo_anio              SMALLINT NOT NULL,
    periodo_valor             SMALLINT NOT NULL,           -- mes 1-12 (mensual) o semestre 1-2 (semestral)
    fecha_desde               DATE NOT NULL,
    fecha_hasta               DATE NOT NULL,

    iva_ventas                NUMERIC(14,2) NOT NULL DEFAULT 0,
    notas_credito_venta       NUMERIC(14,2) NOT NULL DEFAULT 0,
    credito_tributario_compras NUMERIC(14,2) NOT NULL DEFAULT 0,
    notas_credito_compra      NUMERIC(14,2) NOT NULL DEFAULT 0,
    retenciones_iva           NUMERIC(14,2) NOT NULL DEFAULT 0,
    credito_anterior_aplicado NUMERIC(14,2) NOT NULL DEFAULT 0,  -- arrastre del saldo a favor del período previo
    iva_a_pagar               NUMERIC(14,2) NOT NULL DEFAULT 0,
    saldo_favor               NUMERIC(14,2) NOT NULL DEFAULT 0,  -- saldo a favor de ESTE período, a arrastrar al siguiente

    valores_casilleros        JSONB,                       -- snapshot completo de getResumenCompleto()['valores']

    estado                    VARCHAR(20) NOT NULL DEFAULT 'guardado', -- guardado / contabilizado / pagado
    id_asiento                INTEGER,
    id_egreso                 INTEGER,
    observaciones             TEXT,

    eliminado                 BOOLEAN NOT NULL DEFAULT false,
    created_at                TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at                TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by                INTEGER,
    updated_by                INTEGER,
    deleted_at                TIMESTAMP,
    deleted_by                INTEGER
);

-- Evita declarar dos veces el mismo período (respeta eliminación lógica).
CREATE UNIQUE INDEX IF NOT EXISTS uk_declaracion_iva_periodo
    ON declaracion_iva_cabecera (id_empresa, tipo_ambiente, tipo_periodo, periodo_anio, periodo_valor)
    WHERE eliminado = false;

CREATE INDEX IF NOT EXISTS idx_declaracion_iva_empresa
    ON declaracion_iva_cabecera (id_empresa) WHERE eliminado = false;

-- -----------------------------------------------------------------------------
-- Conceptos de asiento para DECLARACIÓN DE IVA (tipo_asiento = 'declaracion_iva')
-- catálogo global, se configuran por empresa en Contabilidad → Configuración
-- contable, igual que 'nomina' o 'cierre_ejercicio'.
-- -----------------------------------------------------------------------------
INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, tipo_cuenta, debe_haber, eliminado, created_at)
SELECT 'declaracion_iva', 'IVA en Ventas',
       'Cancela (al Debe) el IVA cobrado en ventas acumulado del período.',
       'IVAVENTASDECLARACION', 'pasivo', 'debe', false, now()
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE tipo_asiento='declaracion_iva' AND codigo='IVAVENTASDECLARACION' AND eliminado=false);

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, tipo_cuenta, debe_haber, eliminado, created_at)
SELECT 'declaracion_iva', 'Crédito Tributario Compras',
       'Cancela (al Haber) el crédito tributario de IVA en compras acumulado del período.',
       'IVACOMPRASDECLARACION', 'activo', 'haber', false, now()
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE tipo_asiento='declaracion_iva' AND codigo='IVACOMPRASDECLARACION' AND eliminado=false);

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, tipo_cuenta, debe_haber, eliminado, created_at)
SELECT 'declaracion_iva', 'Retenciones de IVA Recibidas',
       'Cancela (al Haber) las retenciones de IVA que le hicieron en ventas, acumuladas del período.',
       'RETENCIONIVADECLARACION', 'activo', 'haber', false, now()
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE tipo_asiento='declaracion_iva' AND codigo='RETENCIONIVADECLARACION' AND eliminado=false);

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, tipo_cuenta, debe_haber, eliminado, created_at)
SELECT 'declaracion_iva', 'IVA por Pagar',
       'IVA por pagar resultante de la declaración (se abona al Haber cuando hay valor a pagar).',
       'IVAPORPAGARDECLARACION', 'pasivo', 'haber', false, now()
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE tipo_asiento='declaracion_iva' AND codigo='IVAPORPAGARDECLARACION' AND eliminado=false);

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, tipo_cuenta, debe_haber, eliminado, created_at)
SELECT 'declaracion_iva', 'Crédito Tributario a Favor',
       'Saldo a favor de la declaración, a arrastrar al siguiente período (se registra al Debe).',
       'CREDITOTRIBUTARIOFAVORDECLARACION', 'activo', 'debe', false, now()
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE tipo_asiento='declaracion_iva' AND codigo='CREDITOTRIBUTARIOFAVORDECLARACION' AND eliminado=false);

COMMIT;
