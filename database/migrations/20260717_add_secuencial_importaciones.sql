-- ============================================================
-- Ajusta importaciones_cabecera a la numeración consecutiva estándar
-- (establecimiento-punto de emisión-secuencial vía SecuencialService),
-- igual patrón que Órdenes de Compra / Traspasos.
--
-- La tabla ya se había desplegado con la versión anterior (columna
-- `numero` simple, MAX+1 por empresa). Este script la migra al esquema
-- actual de create_importaciones_module.sql. Sin filas existentes al
-- momento de escribir esto, por lo que no requiere backfill de datos.
-- Idempotente: puede ejecutarse más de una vez sin error.
-- ============================================================

BEGIN;

DROP INDEX IF EXISTS uq_importaciones_numero;

ALTER TABLE importaciones_cabecera DROP COLUMN IF EXISTS numero;

ALTER TABLE importaciones_cabecera
    ADD COLUMN IF NOT EXISTS id_establecimiento INTEGER,
    ADD COLUMN IF NOT EXISTS id_punto_emision   INTEGER,
    ADD COLUMN IF NOT EXISTS establecimiento    VARCHAR(3),
    ADD COLUMN IF NOT EXISTS punto_emision      VARCHAR(3),
    ADD COLUMN IF NOT EXISTS secuencial         VARCHAR(9),
    ADD COLUMN IF NOT EXISTS tipo_ambiente      VARCHAR(1);

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'importaciones_cabecera' AND column_name = 'numero_importacion'
    ) THEN
        ALTER TABLE importaciones_cabecera
            ADD COLUMN numero_importacion VARCHAR(20)
            GENERATED ALWAYS AS (establecimiento || '-' || punto_emision || '-' || secuencial) STORED;
    END IF;
END $$;

-- Sin filas existentes: seguro exigir NOT NULL directamente.
ALTER TABLE importaciones_cabecera
    ALTER COLUMN id_establecimiento SET NOT NULL,
    ALTER COLUMN id_punto_emision   SET NOT NULL,
    ALTER COLUMN establecimiento    SET NOT NULL,
    ALTER COLUMN punto_emision      SET NOT NULL,
    ALTER COLUMN secuencial         SET NOT NULL;

DO $$ BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_importacion_establecimiento' AND conrelid = 'importaciones_cabecera'::regclass
    ) THEN
        ALTER TABLE importaciones_cabecera
        ADD CONSTRAINT fk_importacion_establecimiento FOREIGN KEY (id_establecimiento) REFERENCES empresa_establecimiento(id);
    END IF;
END $$;

DO $$ BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_importacion_punto_emision' AND conrelid = 'importaciones_cabecera'::regclass
    ) THEN
        ALTER TABLE importaciones_cabecera
        ADD CONSTRAINT fk_importacion_punto_emision FOREIGN KEY (id_punto_emision) REFERENCES empresa_punto_emision(id);
    END IF;
END $$;

DROP INDEX IF EXISTS uq_importaciones_secuencial;
CREATE UNIQUE INDEX uq_importaciones_secuencial
    ON importaciones_cabecera(id_punto_emision, secuencial, tipo_ambiente) WHERE eliminado = false;

COMMIT;
