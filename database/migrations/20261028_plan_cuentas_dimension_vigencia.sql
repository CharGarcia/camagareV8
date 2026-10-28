-- =====================================================================
-- Plan de Cuentas: centro de costo / proyecto SUGERIDO por periodo
-- =====================================================================
-- Qué hace:
--   1. Crea la tabla operativa plan_cuentas_dimension_vigencia: para cada cuenta,
--      qué centro de costo y/o proyecto se sugiere en los asientos según la fecha
--      (vigente_desde .. vigente_hasta; NULL = sin límite por ese lado).
--      Una misma cuenta puede tener una fila por periodo (2026 → Proyecto A,
--      2027 → Proyecto B) sin tocar los asientos ya registrados: lo que cuenta en
--      los reportes sigue siendo el centro/proyecto guardado en cada línea del asiento.
--      Cada fila es de UN tipo: o centro de costo o proyecto. Los periodos de centro
--      y los de proyecto son independientes (pestañas separadas en la ficha de la cuenta).
--   2. Copia los valores que hoy tienen plan_cuentas.id_centro_costos / id_proyecto
--      como filas "sin límite de fechas" (una de centro y otra de proyecto). Las
--      columnas viejas NO se borran (quedan como respaldo; el sistema deja de usarlas).
-- Toca datos: solo la tabla nueva. No modifica plan_cuentas.
-- Idempotente: se puede volver a ejecutar sin duplicar filas.
-- Reversible: DROP TABLE IF EXISTS plan_cuentas_dimension_vigencia;
-- Ejecutar ANTES de desplegar el código (el código degrada si la tabla no existe).
-- =====================================================================

CREATE TABLE IF NOT EXISTS plan_cuentas_dimension_vigencia (
    id               SERIAL PRIMARY KEY,
    id_empresa       INTEGER NOT NULL,
    id_cuenta        INTEGER NOT NULL REFERENCES plan_cuentas(id),
    id_centro_costo  INTEGER NULL REFERENCES centro_costos(id),
    id_proyecto      INTEGER NULL REFERENCES proyectos(id),
    vigente_desde    DATE NULL,
    vigente_hasta    DATE NULL,
    observacion      VARCHAR(250) NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP NULL,
    created_by       INTEGER NULL,
    updated_by       INTEGER NULL,
    eliminado        BOOLEAN NOT NULL DEFAULT false,
    deleted_at       TIMESTAMP NULL,
    deleted_by       INTEGER NULL,
    CONSTRAINT chk_pcdv_una_dimension CHECK ((id_centro_costo IS NULL) <> (id_proyecto IS NULL)),
    CONSTRAINT chk_pcdv_rango CHECK (vigente_desde IS NULL OR vigente_hasta IS NULL OR vigente_hasta >= vigente_desde)
);

CREATE INDEX IF NOT EXISTS idx_pcdv_empresa_cuenta
    ON plan_cuentas_dimension_vigencia (id_empresa, id_cuenta)
    WHERE eliminado = false;

-- Si la tabla ya existía con la versión anterior (una fila podía llevar centro Y proyecto):
-- separa esas filas en dos y cambia la restricción. Sin filas así no hace nada.
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'chk_pcdv_alguna_dimension') THEN
        INSERT INTO plan_cuentas_dimension_vigencia
            (id_empresa, id_cuenta, id_centro_costo, id_proyecto, vigente_desde, vigente_hasta, observacion,
             created_at, created_by, eliminado, deleted_at, deleted_by)
        SELECT id_empresa, id_cuenta, NULL, id_proyecto, vigente_desde, vigente_hasta, observacion,
               created_at, created_by, eliminado, deleted_at, deleted_by
        FROM plan_cuentas_dimension_vigencia
        WHERE id_centro_costo IS NOT NULL AND id_proyecto IS NOT NULL;

        UPDATE plan_cuentas_dimension_vigencia SET id_proyecto = NULL
        WHERE id_centro_costo IS NOT NULL AND id_proyecto IS NOT NULL;

        ALTER TABLE plan_cuentas_dimension_vigencia DROP CONSTRAINT chk_pcdv_alguna_dimension;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'chk_pcdv_una_dimension') THEN
        ALTER TABLE plan_cuentas_dimension_vigencia
            ADD CONSTRAINT chk_pcdv_una_dimension CHECK ((id_centro_costo IS NULL) <> (id_proyecto IS NULL));
    END IF;
END $$;

-- Centro de costo actual de la cuenta → periodo sin límite (si la cuenta aún no tiene periodos de centro).
INSERT INTO plan_cuentas_dimension_vigencia
    (id_empresa, id_cuenta, id_centro_costo, id_proyecto, vigente_desde, vigente_hasta, observacion, created_by)
SELECT pc.id_empresa, pc.id, cc.id, NULL, NULL, NULL,
       'Migrado desde la configuración anterior de la cuenta', pc.created_by
FROM plan_cuentas pc
JOIN centro_costos cc ON cc.id = pc.id_centro_costos AND cc.id_empresa = pc.id_empresa AND cc.eliminado = false
WHERE pc.eliminado = false
  AND NOT EXISTS (
      SELECT 1 FROM plan_cuentas_dimension_vigencia v
      WHERE v.id_cuenta = pc.id AND v.id_empresa = pc.id_empresa AND v.id_centro_costo IS NOT NULL
  );

-- Proyecto actual de la cuenta → periodo sin límite (si la cuenta aún no tiene periodos de proyecto).
INSERT INTO plan_cuentas_dimension_vigencia
    (id_empresa, id_cuenta, id_centro_costo, id_proyecto, vigente_desde, vigente_hasta, observacion, created_by)
SELECT pc.id_empresa, pc.id, NULL, pr.id, NULL, NULL,
       'Migrado desde la configuración anterior de la cuenta', pc.created_by
FROM plan_cuentas pc
JOIN proyectos pr ON pr.id = pc.id_proyecto AND pr.id_empresa = pc.id_empresa AND pr.eliminado = false
WHERE pc.eliminado = false
  AND NOT EXISTS (
      SELECT 1 FROM plan_cuentas_dimension_vigencia v
      WHERE v.id_cuenta = pc.id AND v.id_empresa = pc.id_empresa AND v.id_proyecto IS NOT NULL
  );

-- Comprobación (una fila por cada centro y cada proyecto que tenían las cuentas):
-- SELECT v.id_empresa, pc.codigo, pc.nombre, v.id_centro_costo, v.id_proyecto, v.vigente_desde, v.vigente_hasta
-- FROM plan_cuentas_dimension_vigencia v JOIN plan_cuentas pc ON pc.id = v.id_cuenta
-- WHERE v.eliminado = false ORDER BY v.id_empresa, pc.codigo;
