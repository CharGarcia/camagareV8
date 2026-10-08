-- ============================================================================
-- Corrige la FK de id_responsable_traslado (08-10-2026)
--
-- En algún servidor se creó a mano la FK
--   consignaciones_ventas_id_responsable_traslado_fkey → transportistas(id)
-- pero el sistema guarda ahí el id de responsables_traslado (catálogo
-- "Responsables de Traslado"); transportistas es el catálogo de Guías de
-- Remisión. Resultado: SQLSTATE 23503 al guardar una consignación.
--
-- Se elimina cualquier FK de esas columnas que apunte a transportistas y se
-- crea la correcta hacia responsables_traslado como NOT VALID (valida solo
-- lo nuevo; no falla por datos migrados antiguos). Idempotente.
-- ============================================================================

DO $$
DECLARE
    r RECORD;
BEGIN
    -- 1. Quitar FKs equivocadas (→ transportistas) en las columnas de responsable
    FOR r IN
        SELECT c.conrelid::regclass AS tabla, c.conname
          FROM pg_constraint c
          JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = ANY (c.conkey)
         WHERE c.contype = 'f'
           AND c.confrelid = 'transportistas'::regclass
           AND c.conrelid IN ('consignaciones_ventas'::regclass, 'pedidos_cabecera'::regclass,
                              'retornos_cv'::regclass, 'cambios_producto_cv'::regclass)
           AND a.attname IN ('id_responsable_traslado', 'id_responsable_entrega')
    LOOP
        EXECUTE format('ALTER TABLE %s DROP CONSTRAINT %I', r.tabla, r.conname);
        RAISE NOTICE 'Eliminada FK % en %', r.conname, r.tabla;
    END LOOP;

    -- 2. FK correcta en consignaciones_ventas (solo si no existe)
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
         WHERE conrelid = 'consignaciones_ventas'::regclass
           AND conname  = 'fk_cv_responsable_traslado'
    ) THEN
        ALTER TABLE consignaciones_ventas
            ADD CONSTRAINT fk_cv_responsable_traslado
            FOREIGN KEY (id_responsable_traslado) REFERENCES responsables_traslado(id)
            NOT VALID;
    END IF;
END $$;
