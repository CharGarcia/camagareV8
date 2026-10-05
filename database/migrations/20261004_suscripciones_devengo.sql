-- ============================================================================
-- Suscripciones: reconocimiento del ingreso por devengado (NIIF 15 / NIIF para
-- PYMES Sección 23).
--
-- Un servicio de suscripción se presta a lo largo del tiempo, así que su ingreso
-- se reconoce mes a mes y no el día en que se factura:
--   * Cobro POR ADELANTADO: lo facturado de meses futuros va a un pasivo
--     («Ingresos diferidos») y un proceso mensual lo pasa al ingreso.
--   * MES CAÍDO (vencido): al cierre del mes se provisiona el ingreso ya
--     prestado y aún no facturado (activo «Ingresos devengados por facturar»);
--     la factura posterior cancela esa provisión.
-- Solo aplica a las líneas de SERVICIO (productos.tipo_produccion = '02'); los
-- bienes se reconocen siempre al facturar. El IVA no cambia: va en la factura.
--
-- Contenido:
--   1. suscripciones: modalidad_cobro y reconocimiento (por defecto el
--      comportamiento de siempre: anticipado + al facturar).
--   2. suscripciones_pagos: período de servicio que cubre cada documento.
--   3. suscripciones_devengos: cronograma mensual (tabla operativa).
--   4. asientos_tipo (catálogo GLOBAL): sección «Suscripciones - Devengo» con
--      las dos cuentas que cada empresa asigna en Configuración Contable.
--
-- Idempotente: se puede ejecutar varias veces. Listo para pgAdmin (F5).
-- ============================================================================

BEGIN;

-- ── 1. Suscripciones ────────────────────────────────────────────────────────
ALTER TABLE suscripciones
    ADD COLUMN IF NOT EXISTS modalidad_cobro VARCHAR(10) NOT NULL DEFAULT 'anticipado';
ALTER TABLE suscripciones
    ADD COLUMN IF NOT EXISTS reconocimiento  VARCHAR(10) NOT NULL DEFAULT 'inmediato';

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'chk_suscripciones_modalidad_cobro') THEN
        ALTER TABLE suscripciones ADD CONSTRAINT chk_suscripciones_modalidad_cobro
            CHECK (modalidad_cobro IN ('anticipado', 'vencido'));
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'chk_suscripciones_reconocimiento') THEN
        ALTER TABLE suscripciones ADD CONSTRAINT chk_suscripciones_reconocimiento
            CHECK (reconocimiento IN ('inmediato', 'diferido'));
    END IF;
END $$;

COMMENT ON COLUMN suscripciones.modalidad_cobro IS
    'anticipado: el documento cubre el período que empieza en proximo_cobro. vencido (mes caído): cubre el período que termina el día anterior a proximo_cobro.';
COMMENT ON COLUMN suscripciones.reconocimiento IS
    'inmediato: el ingreso se reconoce al facturar. diferido: las líneas de servicio se reconocen mes a mes (suscripciones_devengos).';

-- ── 2. Período de servicio de cada documento generado ───────────────────────
ALTER TABLE suscripciones_pagos ADD COLUMN IF NOT EXISTS servicio_desde DATE;
ALTER TABLE suscripciones_pagos ADD COLUMN IF NOT EXISTS servicio_hasta DATE;

-- ── 3. Cronograma de devengo ────────────────────────────────────────────────
--   tipo = 'diferido'  (cobro por adelantado)
--       pendiente  → en el pasivo, falta devengar
--       devengado  → el asiento mensual ya lo pasó al ingreso
--       anulado    → nota de crédito / documento anulado o eliminado
--   tipo = 'provision' (mes caído)
--       devengado  → provisionado al cierre del mes, aún sin factura
--       facturado  → la factura del período canceló la provisión
--       anulado    → provisión reversada (no se va a facturar)
CREATE TABLE IF NOT EXISTS suscripciones_devengos (
    id                     SERIAL PRIMARY KEY,
    id_empresa             INTEGER       NOT NULL,
    id_suscripcion         INTEGER       NOT NULL REFERENCES suscripciones(id),
    id_suscripcion_pago    INTEGER       REFERENCES suscripciones_pagos(id),
    tipo                   VARCHAR(10)   NOT NULL,
    tipo_documento         VARCHAR(10),              -- 'factura' | 'recibo' (NULL en una provisión aún sin facturar)
    id_documento           INTEGER,                  -- ventas_cabecera.id | recibos_venta_cabecera.id
    id_documento_detalle   INTEGER,                  -- ventas_detalle.id | recibos_venta_detalle.id
    id_producto            INTEGER,
    descripcion            VARCHAR(300),
    periodo                DATE          NOT NULL,   -- primer día del mes que se devenga
    monto                  NUMERIC(14,2) NOT NULL,
    estado                 VARCHAR(10)   NOT NULL DEFAULT 'pendiente',
    id_cuenta_ingreso      INTEGER,                  -- cuenta de ingreso resuelta al contabilizar
    id_asiento             INTEGER,                  -- asiento de devengo o de provisión
    id_nota_credito        INTEGER,                  -- NC que anuló la porción pendiente
    devengado_at           TIMESTAMP,
    devengado_by           INTEGER,

    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN   NOT NULL DEFAULT false,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER,

    CONSTRAINT chk_susc_devengos_tipo    CHECK (tipo IN ('diferido', 'provision')),
    CONSTRAINT chk_susc_devengos_estado  CHECK (estado IN ('pendiente', 'devengado', 'facturado', 'anulado')),
    CONSTRAINT chk_susc_devengos_doc     CHECK (tipo_documento IS NULL OR tipo_documento IN ('factura', 'recibo')),
    CONSTRAINT chk_susc_devengos_monto   CHECK (monto >= 0),
    CONSTRAINT chk_susc_devengos_periodo CHECK (periodo = date_trunc('month', periodo)::date)
);

COMMENT ON TABLE suscripciones_devengos IS
    'Cronograma mensual de reconocimiento del ingreso de suscripciones (diferido por adelantado y provisión de mes caído).';

-- Proceso mensual: lo pendiente/provisionado de una empresa en un mes.
CREATE INDEX IF NOT EXISTS idx_susc_devengos_empresa_periodo
    ON suscripciones_devengos (id_empresa, periodo, estado) WHERE eliminado = false;
-- Pestaña «Devengo» de la suscripción.
CREATE INDEX IF NOT EXISTS idx_susc_devengos_suscripcion
    ON suscripciones_devengos (id_suscripcion, periodo) WHERE eliminado = false;
-- Asiento de la factura / nota de crédito / anulación: las filas de un documento.
CREATE INDEX IF NOT EXISTS idx_susc_devengos_documento
    ON suscripciones_devengos (tipo_documento, id_documento) WHERE eliminado = false;
-- Un mes diferido por línea de documento, una sola vez.
CREATE UNIQUE INDEX IF NOT EXISTS uq_susc_devengos_diferido_linea_mes
    ON suscripciones_devengos (tipo_documento, id_documento_detalle, periodo)
    WHERE eliminado = false AND tipo = 'diferido' AND estado <> 'anulado';

-- ── 4. Conceptos contables (catálogo global) ───────────────────────────────
ALTER TABLE asientos_tipo ADD COLUMN IF NOT EXISTS debe_haber VARCHAR(10) NOT NULL DEFAULT 'debe';

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, tipo_cuenta, debe_haber, eliminado, created_at)
SELECT 'suscripciones_devengo', 'Ingresos diferidos por suscripciones',
       'Pasivo del contrato: la parte de las facturas de suscripción (cobro por adelantado) que corresponde a meses que aún no se prestan. La factura la acredita y el devengo mensual la debita contra la cuenta de ingreso de cada servicio.',
       'INGRESODIFERIDOSUSCRIPCION', 'pasivo', 'haber', false, CURRENT_TIMESTAMP
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = 'INGRESODIFERIDOSUSCRIPCION');

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, tipo_cuenta, debe_haber, eliminado, created_at)
SELECT 'suscripciones_devengo', 'Ingresos devengados por facturar',
       'Activo del contrato: el servicio de suscripción ya prestado (mes caído) que todavía no se factura. El cierre del mes lo debita contra el ingreso y la factura del período lo acredita.',
       'INGRESOPORFACTURARSUSCRIPCION', 'activo', 'debe', false, CURRENT_TIMESTAMP
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = 'INGRESOPORFACTURARSUSCRIPCION');

COMMIT;

-- ── Comprobación (ejecutar aparte) ──────────────────────────────────────────
-- Deben salir 2 filas:
-- SELECT column_name, data_type, column_default FROM information_schema.columns
--  WHERE table_name = 'suscripciones' AND column_name IN ('modalidad_cobro', 'reconocimiento');
-- Deben salir 2 filas:
-- SELECT column_name FROM information_schema.columns
--  WHERE table_name = 'suscripciones_pagos' AND column_name IN ('servicio_desde', 'servicio_hasta');
-- Debe salir 1 fila:
-- SELECT to_regclass('public.suscripciones_devengos');
-- Deben salir 2 filas:
-- SELECT codigo, tipo_cuenta, debe_haber FROM asientos_tipo WHERE tipo_asiento = 'suscripciones_devengo';
