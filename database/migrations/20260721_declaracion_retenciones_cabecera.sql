-- =============================================================================
-- Módulo Declaración de Retenciones (Formulario 103): guardar la declaración de
-- un período como documento + asiento contable + egreso, mismo patrón que
-- Declaración de IVA (ver database/migrations/20260721_declaracion_iva_cabecera.sql).
--
-- Diferencia de diseño respecto a IVA: el F103 es una retención (pasivo que ya
-- se reconoció documento a documento en cada retencion_compra_cabecera), NO un
-- mecanismo de crédito tributario, por lo tanto esta cabecera NO tiene
-- saldo_favor/credito_anterior_aplicado (no aplica "arrastre" aquí).
--
-- Idempotente. Multiempresa (id_empresa). Eliminación lógica.
-- =============================================================================

BEGIN;

CREATE TABLE IF NOT EXISTS declaracion_retenciones_cabecera (
    id                       SERIAL PRIMARY KEY,
    id_empresa               INTEGER NOT NULL,
    tipo_ambiente            VARCHAR(1) NOT NULL DEFAULT '1',
    periodo_anio             SMALLINT NOT NULL,
    periodo_mes              SMALLINT NOT NULL,
    fecha_desde              DATE NOT NULL,
    fecha_hasta              DATE NOT NULL,

    total_base_nacional      NUMERIC(14,2) NOT NULL DEFAULT 0,  -- casillero 349
    total_retenido_nacional  NUMERIC(14,2) NOT NULL DEFAULT 0,  -- casillero 399
    total_base_exterior      NUMERIC(14,2) NOT NULL DEFAULT 0,  -- casillero 497
    total_retenido_exterior  NUMERIC(14,2) NOT NULL DEFAULT 0,  -- casillero 498
    total_retenido           NUMERIC(14,2) NOT NULL DEFAULT 0,  -- casillero 499 (total a pagar al SRI)

    valores_casilleros       JSONB,                       -- snapshot completo de getResumenCompleto()['valores']

    estado                   VARCHAR(20) NOT NULL DEFAULT 'guardado', -- guardado / contabilizado / pagado
    id_asiento               INTEGER,
    id_egreso                INTEGER,
    observaciones            TEXT,

    eliminado                BOOLEAN NOT NULL DEFAULT false,
    created_at               TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at               TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by               INTEGER,
    updated_by               INTEGER,
    deleted_at               TIMESTAMP,
    deleted_by               INTEGER
);

-- Evita declarar dos veces el mismo período (respeta eliminación lógica).
CREATE UNIQUE INDEX IF NOT EXISTS uk_declaracion_retenciones_periodo
    ON declaracion_retenciones_cabecera (id_empresa, tipo_ambiente, periodo_anio, periodo_mes)
    WHERE eliminado = false;

CREATE INDEX IF NOT EXISTS idx_declaracion_retenciones_empresa
    ON declaracion_retenciones_cabecera (id_empresa) WHERE eliminado = false;

-- -----------------------------------------------------------------------------
-- Concepto de asiento para DECLARACIÓN DE RETENCIONES (tipo_asiento = 'declaracion_retenciones')
-- catálogo global, se configura por empresa en Contabilidad → Configuración contable.
--
-- Es una sola cuenta: consolida (al Haber) el pasivo por retención que ya se
-- reconoció documento a documento (en cuentas configurables por código SRI vía
-- 'retenciones_compra_haber'), reclasificándolo a una única cuenta de "Retenciones
-- de Renta por Pagar (declaración)" que luego se cancela con el egreso al SRI.
-- -----------------------------------------------------------------------------
INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, tipo_cuenta, debe_haber, eliminado, created_at)
SELECT 'declaracion_retenciones', 'Retenciones de Renta por Pagar (Declaración)',
       'Consolida al Haber el total de retenciones de Impuesto a la Renta del período, reclasificado desde las cuentas de retención por código SRI.',
       'RETENCIONRENTAPORPAGARDECLARACION', 'pasivo', 'haber', false, now()
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE tipo_asiento='declaracion_retenciones' AND codigo='RETENCIONRENTAPORPAGARDECLARACION' AND eliminado=false);

COMMIT;
