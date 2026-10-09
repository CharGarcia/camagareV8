-- =============================================================================
-- Cierre del Ejercicio (modulos/cierre_ejercicio)
--
-- Qué hace:
--   1) Crea la tabla operativa `cierre_ejercicio`: un registro por año cerrado, con
--      el asiento de CIERRE (31-12) y el de APERTURA (01-01 del año siguiente) que
--      generó el módulo, los totales y los períodos contables que bloqueó.
--   2) Agrega al tipo de asiento global 'cierre_ejercicio' (Configuración Contable)
--      dos cuentas nuevas: Utilidades Acumuladas y Pérdidas Acumuladas, a las que
--      la apertura traslada el resultado del año que se cierra.
--
-- Toca datos: NO modifica registros existentes. Solo crea la tabla e inserta dos
-- filas en el catálogo global `asientos_tipo` (si no existen).
-- Idempotente: se puede ejecutar varias veces.
-- Reversible:
--   DROP TABLE IF EXISTS cierre_ejercicio;
--   UPDATE asientos_tipo SET eliminado = true, deleted_at = now()
--    WHERE tipo_asiento = 'cierre_ejercicio'
--      AND codigo IN ('UTILIDADESACUMULADASCIERRE', 'PERDIDASACUMULADASCIERRE');
--
-- Listo para pegar en pgAdmin (Query Tool) y ejecutar de una sola vez (F5).
-- =============================================================================

CREATE TABLE IF NOT EXISTS cierre_ejercicio (
    id                   SERIAL PRIMARY KEY,
    id_empresa           INTEGER       NOT NULL,
    anio                 INTEGER       NOT NULL,
    fecha_cierre         DATE          NOT NULL,          -- 31-12 del año cerrado
    fecha_apertura       DATE          NOT NULL,          -- 01-01 del año siguiente
    saldos_desde         DATE          NULL,              -- desde dónde se acumularon los saldos de balance (NULL = todo el histórico)
    id_asiento_cierre    INTEGER       NULL,
    id_asiento_apertura  INTEGER       NULL,
    resultado            NUMERIC(18,2) NOT NULL DEFAULT 0, -- utilidad (+) o pérdida (-) del año
    resultado_anterior   NUMERIC(18,2) NOT NULL DEFAULT 0, -- resultados de años anteriores que no se habían cerrado
    total_activos        NUMERIC(18,2) NOT NULL DEFAULT 0,
    total_pasivos        NUMERIC(18,2) NOT NULL DEFAULT 0,
    total_patrimonio     NUMERIC(18,2) NOT NULL DEFAULT 0,
    periodos             JSONB         NULL,              -- períodos contables creados/cerrados (para devolverlos al revertir)
    estado               VARCHAR(20)   NOT NULL DEFAULT 'vigente', -- vigente | revertido
    observaciones        TEXT          NULL,
    motivo_reversion     TEXT          NULL,
    revertido_at         TIMESTAMP     NULL,
    revertido_by         INTEGER       NULL,
    created_at           TIMESTAMP     NOT NULL DEFAULT now(),
    updated_at           TIMESTAMP     NULL,
    created_by           INTEGER       NULL,
    updated_by           INTEGER       NULL,
    eliminado            BOOLEAN       NOT NULL DEFAULT false,
    deleted_at           TIMESTAMP     NULL,
    deleted_by           INTEGER       NULL
);

-- Un solo cierre vigente por empresa y año (el revertido queda como historial).
CREATE UNIQUE INDEX IF NOT EXISTS uq_cierre_ejercicio_vigente
    ON cierre_ejercicio (id_empresa, anio)
    WHERE eliminado = false AND estado = 'vigente';

CREATE INDEX IF NOT EXISTS idx_cierre_ejercicio_empresa
    ON cierre_ejercicio (id_empresa, anio DESC)
    WHERE eliminado = false;

-- Cuentas de resultados acumulados (Configuración Contable → Cierre del Ejercicio)
INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, tipo_cuenta, debe_haber, eliminado, created_at)
SELECT 'cierre_ejercicio', 'Cuenta de Utilidades Acumuladas',
       'Cuenta de patrimonio a la que la apertura del año siguiente traslada la UTILIDAD del año cerrado.',
       'UTILIDADESACUMULADASCIERRE', 'patrimonio', 'haber', false, now()
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE tipo_asiento = 'cierre_ejercicio' AND codigo = 'UTILIDADESACUMULADASCIERRE' AND eliminado = false);

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, tipo_cuenta, debe_haber, eliminado, created_at)
SELECT 'cierre_ejercicio', 'Cuenta de Pérdidas Acumuladas',
       'Cuenta de patrimonio a la que la apertura del año siguiente traslada la PÉRDIDA del año cerrado.',
       'PERDIDASACUMULADASCIERRE', 'patrimonio', 'debe', false, now()
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE tipo_asiento = 'cierre_ejercicio' AND codigo = 'PERDIDASACUMULADASCIERRE' AND eliminado = false);

-- Comprobación (deben salir 1 fila con la tabla y 4 cuentas del tipo cierre_ejercicio):
-- SELECT to_regclass('public.cierre_ejercicio') AS tabla;
-- SELECT codigo, referencia FROM asientos_tipo WHERE tipo_asiento = 'cierre_ejercicio' AND eliminado = false ORDER BY id;
