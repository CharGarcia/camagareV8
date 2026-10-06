-- ============================================================================
-- Condominios: reajuste masivo de cuotas de las suscripciones (Configuración de condominios →
-- pestaña «Reajuste de cuotas»). Un reajuste cambia el valor de un concepto (producto) en muchas
-- suscripciones a la vez: monto fijo, aumento %, o según el inmueble (valores que rigen). Puede
-- aplicarse en el acto o quedar PROGRAMADO para una fecha (p. ej. 1 de enero): el cron fijo
-- diario lo aplica ese día. Las filas (suscripción → valor nuevo) quedan guardadas en jsonb como
-- constancia de lo que se cambió.
-- Operativa: id_empresa + auditoría + eliminación lógica. Idempotente. Listo para pgAdmin (F5).
-- ============================================================================
BEGIN;

CREATE TABLE IF NOT EXISTS condominios_reajustes (
    id                   SERIAL PRIMARY KEY,
    id_empresa           INTEGER       NOT NULL,
    descripcion          VARCHAR(200)  NOT NULL,                     -- «Reajuste 2027, acta N.º 5»
    id_producto          INTEGER       NOT NULL,                     -- concepto que se reajusta (producto)
    forma                VARCHAR(12)   NOT NULL,                     -- fijo | porcentaje | inmueble
    parametro            NUMERIC(14,4) NOT NULL DEFAULT 0,           -- monto fijo o % de aumento
    incluir_sin_inmueble BOOLEAN       NOT NULL DEFAULT false,       -- también suscripciones sin inmueble
    fecha_aplicar        DATE          NOT NULL,                     -- hoy = en el acto; futura = programado
    estado               VARCHAR(10)   NOT NULL DEFAULT 'pendiente', -- pendiente | aplicado | cancelado | error
    filas                JSONB         NOT NULL DEFAULT '[]'::jsonb, -- [{id_suscripcion, id_detalle, id_unidad, cliente, inmueble, actual, nuevo}]
    total_filas          INTEGER       NOT NULL DEFAULT 0,
    suma_actual          NUMERIC(14,2) NOT NULL DEFAULT 0,
    suma_nueva           NUMERIC(14,2) NOT NULL DEFAULT 0,
    aplicado_at          TIMESTAMP,
    aplicado_por         INTEGER,
    resultado            TEXT,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN   NOT NULL DEFAULT false,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER,
    CONSTRAINT chk_cond_reaj_forma  CHECK (forma IN ('fijo', 'porcentaje', 'inmueble')),
    CONSTRAINT chk_cond_reaj_estado CHECK (estado IN ('pendiente', 'aplicado', 'cancelado', 'error'))
);
CREATE INDEX IF NOT EXISTS idx_cond_reaj_empresa   ON condominios_reajustes (id_empresa, estado) WHERE eliminado = false;
CREATE INDEX IF NOT EXISTS idx_cond_reaj_pendiente ON condominios_reajustes (fecha_aplicar) WHERE eliminado = false AND estado = 'pendiente';

COMMIT;
