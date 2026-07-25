-- MIGRATION: Activos Fijos — las cuentas contables pasan de la CATEGORÍA al ACTIVO
-- La categoría queda solo como catálogo de agrupación + % de depreciación anual;
-- las 3 cuentas (Activo, Depreciación Acumulada y Gasto por Depreciación) ahora se
-- configuran en cada activo fijo (ver AsientoBuilderService::generarAsientoAltaActivoFijo
-- y ::generarAsientoDepreciacionLote).
--
-- Idempotente: se puede ejecutar más de una vez y también sobre una base donde
-- 20260719_create_activos_fijos_module.sql aún no se había desplegado.
-- -----------------------------------------------------
BEGIN;

-- 1. Nuevas columnas en activos_fijos (nullable en este paso para poder migrar datos).
ALTER TABLE activos_fijos ADD COLUMN IF NOT EXISTS id_cuenta_activo INTEGER;
ALTER TABLE activos_fijos ADD COLUMN IF NOT EXISTS id_cuenta_depreciacion_acumulada INTEGER;
ALTER TABLE activos_fijos ADD COLUMN IF NOT EXISTS id_cuenta_gasto_depreciacion INTEGER;

-- 2. Backfill: cada activo hereda las cuentas que tenía su categoría
--    (solo si la categoría todavía conserva esas columnas).
DO $mig$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'activos_fijos_categorias' AND column_name = 'id_cuenta_activo'
    ) THEN
        EXECUTE $sql$
            UPDATE activos_fijos a
               SET id_cuenta_activo                 = COALESCE(a.id_cuenta_activo, cat.id_cuenta_activo),
                   id_cuenta_depreciacion_acumulada = COALESCE(a.id_cuenta_depreciacion_acumulada, cat.id_cuenta_depreciacion_acumulada),
                   id_cuenta_gasto_depreciacion     = COALESCE(a.id_cuenta_gasto_depreciacion, cat.id_cuenta_gasto_depreciacion)
              FROM activos_fijos_categorias cat
             WHERE a.id_categoria = cat.id
        $sql$;
    END IF;
END
$mig$;

-- 3. Claves foráneas al plan de cuentas.
DO $mig$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_af_cuenta_activo') THEN
        ALTER TABLE activos_fijos ADD CONSTRAINT fk_af_cuenta_activo
            FOREIGN KEY (id_cuenta_activo) REFERENCES plan_cuentas(id);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_af_cuenta_dep_acum') THEN
        ALTER TABLE activos_fijos ADD CONSTRAINT fk_af_cuenta_dep_acum
            FOREIGN KEY (id_cuenta_depreciacion_acumulada) REFERENCES plan_cuentas(id);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_af_cuenta_gasto') THEN
        ALTER TABLE activos_fijos ADD CONSTRAINT fk_af_cuenta_gasto
            FOREIGN KEY (id_cuenta_gasto_depreciacion) REFERENCES plan_cuentas(id);
    END IF;
END
$mig$;

-- 4. NOT NULL solo si ningún activo quedó sin cuentas (si quedó alguno, la migración
--    no falla: las columnas siguen nullable y se avisa para completarlas a mano).
DO $mig$
DECLARE
    v_pendientes INTEGER;
BEGIN
    SELECT COUNT(*) INTO v_pendientes
      FROM activos_fijos
     WHERE id_cuenta_activo IS NULL
        OR id_cuenta_depreciacion_acumulada IS NULL
        OR id_cuenta_gasto_depreciacion IS NULL;

    IF v_pendientes = 0 THEN
        ALTER TABLE activos_fijos ALTER COLUMN id_cuenta_activo SET NOT NULL;
        ALTER TABLE activos_fijos ALTER COLUMN id_cuenta_depreciacion_acumulada SET NOT NULL;
        ALTER TABLE activos_fijos ALTER COLUMN id_cuenta_gasto_depreciacion SET NOT NULL;
    ELSE
        RAISE NOTICE 'Activos Fijos: % activo(s) sin cuentas contables; las columnas quedan NULLABLE. Complételas desde el módulo y vuelva a ejecutar esta migración.', v_pendientes;
    END IF;
END
$mig$;

-- 5. La categoría deja de manejar cuentas contables.
ALTER TABLE activos_fijos_categorias DROP COLUMN IF EXISTS id_cuenta_activo;
ALTER TABLE activos_fijos_categorias DROP COLUMN IF EXISTS id_cuenta_depreciacion_acumulada;
ALTER TABLE activos_fijos_categorias DROP COLUMN IF EXISTS id_cuenta_gasto_depreciacion;

COMMIT;
