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
--   2. Copia los valores que hoy tienen plan_cuentas.id_centro_costos / id_proyecto
--      como una fila "sin límite de fechas". Las columnas viejas NO se borran
--      (quedan como respaldo; el sistema deja de usarlas).
-- Toca datos: solo INSERT en la tabla nueva. No modifica plan_cuentas.
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
    CONSTRAINT chk_pcdv_alguna_dimension CHECK (id_centro_costo IS NOT NULL OR id_proyecto IS NOT NULL),
    CONSTRAINT chk_pcdv_rango CHECK (vigente_desde IS NULL OR vigente_hasta IS NULL OR vigente_hasta >= vigente_desde)
);

CREATE INDEX IF NOT EXISTS idx_pcdv_empresa_cuenta
    ON plan_cuentas_dimension_vigencia (id_empresa, id_cuenta)
    WHERE eliminado = false;

-- Valores actuales de la cuenta → una fila sin límite de fechas (solo si la cuenta aún no tiene filas).
INSERT INTO plan_cuentas_dimension_vigencia
    (id_empresa, id_cuenta, id_centro_costo, id_proyecto, vigente_desde, vigente_hasta, observacion, created_by)
SELECT pc.id_empresa, pc.id,
       cc.id, pr.id,
       NULL, NULL,
       'Migrado desde la configuración anterior de la cuenta',
       pc.created_by
FROM plan_cuentas pc
LEFT JOIN centro_costos cc ON cc.id = pc.id_centro_costos AND cc.id_empresa = pc.id_empresa AND cc.eliminado = false
LEFT JOIN proyectos     pr ON pr.id = pc.id_proyecto      AND pr.id_empresa = pc.id_empresa AND pr.eliminado = false
WHERE pc.eliminado = false
  AND (cc.id IS NOT NULL OR pr.id IS NOT NULL)
  AND NOT EXISTS (
      SELECT 1 FROM plan_cuentas_dimension_vigencia v
      WHERE v.id_cuenta = pc.id AND v.id_empresa = pc.id_empresa
  );

-- Comprobación (debe devolver una fila por cuenta que tenía centro o proyecto):
-- SELECT v.id_empresa, pc.codigo, pc.nombre, v.id_centro_costo, v.id_proyecto, v.vigente_desde, v.vigente_hasta
-- FROM plan_cuentas_dimension_vigencia v JOIN plan_cuentas pc ON pc.id = v.id_cuenta
-- WHERE v.eliminado = false ORDER BY v.id_empresa, pc.codigo;
