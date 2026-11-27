-- ============================================================================
-- Novedades (Nómina) — Préstamo Empresa con tabla de cuotas
-- ----------------------------------------------------------------------------
-- QUÉ HACE:
--   1. Crea `prestamos_empleado`: la cabecera de un Préstamo Empresa registrado
--      desde el modal Nueva Novedad (modo «Préstamo empresa»): empleado, monto,
--      N° de cuotas, mes/año de la primera cuota, cómo se desembolsa y los campos
--      de interés (previstos para más adelante; hoy se guardan en 0).
--   2. Crea `prestamos_empleado_cuotas`: la tabla de amortización (una fila por
--      cuota: mes y año en que se cobra, capital, interés, valor, si ya estaba
--      cobrada fuera del sistema y la novedad que la descuenta en el rol).
--   3. Agrega `novedades.id_prestamo`: cada cuota por cobrar sigue siendo una
--      novedad de tipo 9 (Préstamo Empresa), así el rol, el asiento y Egresos →
--      Nómina funcionan igual que siempre; con esta columna se sabe a qué
--      préstamo pertenece y se puede eliminar el préstamo completo.
--
-- TOCA DATOS: no. Solo agrega estructura; las novedades existentes quedan con
--             id_prestamo NULL.
--
-- REVERSIBLE: sí →
--   DROP INDEX IF EXISTS idx_novedades_prestamo;
--   ALTER TABLE novedades DROP COLUMN IF EXISTS id_prestamo;
--   DROP TABLE IF EXISTS prestamos_empleado_cuotas;
--   DROP TABLE IF EXISTS prestamos_empleado;
--
-- ORDEN: ejecutar en pgAdmin ANTES de desplegar el código. Sin este SQL el resto
--        del módulo sigue funcionando; solo el modo «Préstamo empresa» avisa que
--        falta aplicarlo. Es idempotente.
-- ============================================================================

CREATE TABLE IF NOT EXISTS prestamos_empleado (
    id             SERIAL PRIMARY KEY,
    id_empresa     INTEGER       NOT NULL,
    id_empleado    INTEGER       NOT NULL,
    fecha          DATE          NOT NULL,
    monto          NUMERIC(14,2) NOT NULL,                   -- capital prestado
    tasa_interes   NUMERIC(7,4)  NOT NULL DEFAULT 0,         -- previsto: % de interés
    valor_interes  NUMERIC(14,2) NOT NULL DEFAULT 0,         -- previsto: interés total
    total          NUMERIC(14,2) NOT NULL,                   -- monto + interés (lo que se cobra)
    num_cuotas     INTEGER       NOT NULL,
    mes_inicio     INTEGER       NOT NULL,                   -- mes de la primera cuota
    anio_inicio    INTEGER       NOT NULL,                   -- año de la primera cuota
    aplica_en      VARCHAR(20)   NOT NULL DEFAULT 'rol',     -- rol / quincena / semanal
    desembolso     VARCHAR(20)   NOT NULL DEFAULT 'por_desembolsar', -- por_desembolsar / entregado
    observacion    TEXT,
    estado         VARCHAR(20)   NOT NULL DEFAULT 'activo',
    tipo_ambiente  VARCHAR(1)    NOT NULL DEFAULT '1',       -- 1 pruebas / 2 producción
    eliminado      BOOLEAN       NOT NULL DEFAULT false,
    created_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    created_by     INTEGER,
    updated_by     INTEGER,
    deleted_at     TIMESTAMP,
    deleted_by     INTEGER
);

CREATE INDEX IF NOT EXISTS idx_prestamos_empleado_emp
    ON prestamos_empleado (id_empresa, id_empleado) WHERE eliminado = false;

CREATE TABLE IF NOT EXISTS prestamos_empleado_cuotas (
    id             SERIAL PRIMARY KEY,
    id_empresa     INTEGER       NOT NULL,
    id_prestamo    INTEGER       NOT NULL REFERENCES prestamos_empleado (id),
    numero         INTEGER       NOT NULL,                   -- 1..num_cuotas
    periodo_mes    INTEGER       NOT NULL,
    periodo_anio   INTEGER       NOT NULL,
    capital        NUMERIC(14,2) NOT NULL,
    interes        NUMERIC(14,2) NOT NULL DEFAULT 0,
    valor          NUMERIC(14,2) NOT NULL,                   -- capital + interés
    cobrada_fuera  BOOLEAN       NOT NULL DEFAULT false,     -- ya cobrada antes del sistema: sin novedad
    id_novedad     INTEGER,                                  -- novedad tipo 9 que la descuenta
    eliminado      BOOLEAN       NOT NULL DEFAULT false,
    created_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    created_by     INTEGER,
    updated_by     INTEGER,
    deleted_at     TIMESTAMP,
    deleted_by     INTEGER
);

CREATE INDEX IF NOT EXISTS idx_prestamos_cuotas_prestamo
    ON prestamos_empleado_cuotas (id_prestamo) WHERE eliminado = false;

ALTER TABLE novedades
    ADD COLUMN IF NOT EXISTS id_prestamo INTEGER;

CREATE INDEX IF NOT EXISTS idx_novedades_prestamo
    ON novedades (id_prestamo) WHERE id_prestamo IS NOT NULL;

-- ── Comprobación (ejecutar aparte; debe devolver 1 fila con 1, 1, 1) ──
-- SELECT
--     (SELECT COUNT(*) FROM information_schema.tables  WHERE table_name = 'prestamos_empleado')        AS tabla_prestamos,
--     (SELECT COUNT(*) FROM information_schema.tables  WHERE table_name = 'prestamos_empleado_cuotas') AS tabla_cuotas,
--     (SELECT COUNT(*) FROM information_schema.columns WHERE table_name = 'novedades' AND column_name = 'id_prestamo') AS col_id_prestamo;
