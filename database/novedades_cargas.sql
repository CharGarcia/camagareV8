-- ============================================================================
-- Novedades (Nómina) — Cargas masivas por plantilla Excel
-- ----------------------------------------------------------------------------
-- QUÉ HACE: crea la tabla `novedades_cargas` (una fila por importación de
--           plantilla) y agrega `novedades.id_carga` para saber a qué carga
--           pertenece cada novedad. Con eso el módulo puede REVERTIR una carga
--           completa (eliminación lógica de todas sus novedades) siempre que
--           ninguna haya sido usada todavía (rol pagado / anticipo o préstamo
--           ya desembolsado por egreso).
--
-- TOCA DATOS: no. Solo agrega estructura; las novedades existentes quedan con
--             id_carga NULL (cargas anteriores a este cambio no se pueden
--             revertir en bloque; se eliminan una a una desde el listado).
--
-- REVERSIBLE: sí →
--   DROP INDEX IF EXISTS idx_novedades_carga;
--   ALTER TABLE novedades DROP COLUMN IF EXISTS id_carga;
--   DROP TABLE IF EXISTS novedades_cargas;
--
-- ORDEN: ejecutar este SQL ANTES de desplegar el código. Si no se ejecuta, la
--        importación sigue funcionando (degrada sin registro de carga).
-- ============================================================================

CREATE TABLE IF NOT EXISTS novedades_cargas (
    id             SERIAL PRIMARY KEY,
    id_empresa     INTEGER      NOT NULL,
    archivo        VARCHAR(255),                          -- nombre del archivo subido
    total_filas    INTEGER      NOT NULL DEFAULT 0,       -- filas con datos en la plantilla
    creadas        INTEGER      NOT NULL DEFAULT 0,       -- novedades efectivamente creadas
    errores        INTEGER      NOT NULL DEFAULT 0,       -- filas rechazadas
    estado         VARCHAR(20)  NOT NULL DEFAULT 'activo', -- activo / revertido
    observacion    TEXT,
    tipo_ambiente  VARCHAR(1)   NOT NULL DEFAULT '1',     -- 1 pruebas / 2 producción
    eliminado      BOOLEAN      NOT NULL DEFAULT false,
    created_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    created_by     INTEGER,
    updated_by     INTEGER,
    deleted_at     TIMESTAMP,
    deleted_by     INTEGER
);

CREATE INDEX IF NOT EXISTS idx_novedades_cargas_empresa
    ON novedades_cargas (id_empresa, tipo_ambiente) WHERE eliminado = false;

ALTER TABLE novedades
    ADD COLUMN IF NOT EXISTS id_carga INTEGER;

CREATE INDEX IF NOT EXISTS idx_novedades_carga
    ON novedades (id_carga) WHERE id_carga IS NOT NULL;

-- ── Comprobación (ejecutar aparte; debe devolver 1 fila con las 3 columnas) ──
-- SELECT
--     (SELECT COUNT(*) FROM information_schema.tables
--       WHERE table_name = 'novedades_cargas')                                   AS tabla_cargas,
--     (SELECT COUNT(*) FROM information_schema.columns
--       WHERE table_name = 'novedades' AND column_name = 'id_carga')             AS col_id_carga,
--     (SELECT COUNT(*) FROM pg_indexes WHERE indexname = 'idx_novedades_carga')  AS idx_carga;
