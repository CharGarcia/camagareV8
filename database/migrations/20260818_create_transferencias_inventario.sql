-- ============================================================================
-- Módulo: Transferencias de Inventario  (modulos/transferencias-inventario)
--
-- Mueve stock entre bodegas de la misma empresa. Si las bodegas pertenecen a
-- establecimientos distintos (mismo RUC), la transferencia queda marcada como
-- "entre establecimientos" y habilita la generación opcional de una Guía de
-- Remisión.
--
-- Es un documento de UN SOLO PASO: al registrarlo salen las unidades de la
-- bodega origen y entran en la de destino dentro de la misma transacción (dos
-- filas de inventario_kardex con tipo_movimiento = 'transferencia').
--
-- Idempotente: se puede correr varias veces.
-- ============================================================================

-- 1) Las bodegas pasan a conocer su establecimiento ---------------------------
--    Nullable: una bodega sin establecimiento sigue funcionando igual que hoy
--    (solo que sus transferencias no se consideran interestablecimiento).
ALTER TABLE bodegas
    ADD COLUMN IF NOT EXISTS id_establecimiento INTEGER;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.table_constraints
         WHERE constraint_name = 'fk_bodegas_establecimiento'
    ) THEN
        ALTER TABLE bodegas
            ADD CONSTRAINT fk_bodegas_establecimiento
            FOREIGN KEY (id_establecimiento) REFERENCES empresa_establecimiento(id);
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_bodegas_establecimiento ON bodegas (id_establecimiento);

--    Backfill: las bodegas existentes se atribuyen a la MATRIZ de su empresa
--    (establecimiento de menor código), igual que se hizo con los documentos en
--    database/backfill_id_establecimiento_matriz.sql. Solo toca las que están en
--    NULL, así que no pisa asignaciones hechas a mano.
UPDATE bodegas b
   SET id_establecimiento = (
        SELECT ee.id FROM empresa_establecimiento ee
         WHERE ee.id_empresa = b.id_empresa AND ee.eliminado = false
         ORDER BY ee.codigo ASC LIMIT 1)
 WHERE b.id_establecimiento IS NULL
   AND b.eliminado = false;

-- 2) El Kardex debe aceptar tipo_movimiento = 'transferencia' -----------------
--    Está en el CREATE TABLE original (database/inventario_tablas.sql), pero
--    hay bases donde el CHECK quedó sin ese valor. Se recrea solo si hace falta.
DO $$
DECLARE
    v_def  text;
    v_name text;
BEGIN
    SELECT c.conname, pg_get_constraintdef(c.oid) INTO v_name, v_def
      FROM pg_constraint c
      JOIN pg_class t ON t.oid = c.conrelid
     WHERE t.relname = 'inventario_kardex'
       AND c.contype = 'c'
       AND pg_get_constraintdef(c.oid) ILIKE '%tipo_movimiento%'
     LIMIT 1;

    IF v_def IS NOT NULL AND v_def NOT ILIKE '%transferencia%' THEN
        EXECUTE format('ALTER TABLE inventario_kardex DROP CONSTRAINT %I', v_name);
        EXECUTE 'ALTER TABLE inventario_kardex ADD CONSTRAINT inventario_kardex_tipo_movimiento_check
                 CHECK (tipo_movimiento IN (''entrada'',''salida'',''ajuste'',''transferencia''))';
    END IF;
END $$;

-- 3) Cabecera ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS transferencias_inventario_cabecera (
    id                          SERIAL PRIMARY KEY,
    id_empresa                  INTEGER      NOT NULL,
    secuencial                  INTEGER      NOT NULL,
    numero                      VARCHAR(20)  NOT NULL,   -- TRF-000001
    fecha_transferencia         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- Origen / destino
    id_bodega_origen            INTEGER      NOT NULL,
    id_bodega_destino           INTEGER      NOT NULL,
    id_establecimiento_origen   INTEGER,
    id_establecimiento_destino  INTEGER,
    entre_establecimientos      BOOLEAN      NOT NULL DEFAULT FALSE,
    -- Responsables (texto libre: quien despacha y quien recibe físicamente)
    responsable_envia           VARCHAR(150),
    responsable_recibe          VARCHAR(150),
    observaciones               TEXT,
    -- Totales denormalizados (para el listado, sin recorrer el detalle)
    total_items                 NUMERIC(18,6) NOT NULL DEFAULT 0,
    total_costo                 NUMERIC(14,2) NOT NULL DEFAULT 0,
    estado                      VARCHAR(20)   NOT NULL DEFAULT 'registrada'
                                CHECK (estado IN ('registrada','anulada')),
    -- Guía de remisión emitida a partir de esta transferencia (opcional)
    id_guia_remision            INTEGER,
    tipo_ambiente               VARCHAR(1)    NOT NULL DEFAULT '1',
    -- Auditoría estándar
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER,
    CONSTRAINT fk_trfinv_empresa     FOREIGN KEY (id_empresa)        REFERENCES empresas(id),
    CONSTRAINT fk_trfinv_bod_origen  FOREIGN KEY (id_bodega_origen)  REFERENCES bodegas(id),
    CONSTRAINT fk_trfinv_bod_destino FOREIGN KEY (id_bodega_destino) REFERENCES bodegas(id),
    CONSTRAINT ck_trfinv_bodegas_distintas CHECK (id_bodega_origen <> id_bodega_destino)
);

CREATE INDEX IF NOT EXISTS idx_trfinv_empresa ON transferencias_inventario_cabecera (id_empresa, eliminado);
CREATE INDEX IF NOT EXISTS idx_trfinv_fecha   ON transferencias_inventario_cabecera (fecha_transferencia);
CREATE INDEX IF NOT EXISTS idx_trfinv_bodegas ON transferencias_inventario_cabecera (id_bodega_origen, id_bodega_destino);

-- Numeración única por empresa + ambiente (el correlativo se serializa con
-- pg_advisory_xact_lock en el repository; este índice es la red de seguridad).
CREATE UNIQUE INDEX IF NOT EXISTS uk_trfinv_secuencial
    ON transferencias_inventario_cabecera (id_empresa, tipo_ambiente, secuencial)
    WHERE eliminado = false;

-- 4) Detalle -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS transferencias_inventario_detalle (
    id                  SERIAL PRIMARY KEY,
    id_empresa          INTEGER       NOT NULL,
    id_transferencia    INTEGER       NOT NULL,
    id_producto         INTEGER       NOT NULL,
    id_medida           INTEGER,
    cantidad            NUMERIC(18,6) NOT NULL,
    costo_unitario      NUMERIC(18,6) NOT NULL DEFAULT 0,
    costo_total         NUMERIC(14,2) NOT NULL DEFAULT 0,
    -- Trazabilidad: viaja igual que en el kardex
    numero_lote         VARCHAR(100),
    fecha_caducidad     DATE,
    nup                 VARCHAR(100),
    observaciones       TEXT,
    -- Movimientos de kardex generados por esta línea
    id_kardex_salida    INTEGER,
    id_kardex_entrada   INTEGER,
    -- Auditoría estándar
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER,
    CONSTRAINT fk_trfinvdet_cab      FOREIGN KEY (id_transferencia) REFERENCES transferencias_inventario_cabecera(id) ON DELETE CASCADE,
    CONSTRAINT fk_trfinvdet_producto FOREIGN KEY (id_producto)      REFERENCES productos(id)
);

CREATE INDEX IF NOT EXISTS idx_trfinvdet_cab      ON transferencias_inventario_detalle (id_transferencia);
CREATE INDEX IF NOT EXISTS idx_trfinvdet_producto ON transferencias_inventario_detalle (id_producto);

COMMENT ON TABLE transferencias_inventario_cabecera IS 'Transferencias de inventario entre bodegas (y entre establecimientos del mismo RUC). Un paso: salida y entrada en la misma transacción.';
COMMENT ON TABLE transferencias_inventario_detalle  IS 'Líneas de la transferencia: producto, cantidad, lote/caducidad/serie y los movimientos de kardex que generó.';
