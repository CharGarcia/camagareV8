-- =============================================================================
-- Índice para el aviso de asientos pendientes (Estados Financieros, Asientos,
-- Balance de Comprobación, Mayores).
--
-- Desde el 28-10-2026 el aviso vuelve a tomar los documentos migrados que el
-- sistema anterior nunca contabilizó. Para saberlo busca, por cada documento
-- migrado sin asiento, si el diario histórico trae su asiento:
--     migracion_mysql_map  WHERE id_empresa = … AND entidad = 'contabilidad'
--                            AND clave_natural = 'EGR' || id_origen
-- Sin este índice esa búsqueda recorre todo el mapa de la contabilidad (miles
-- de filas por empresa) una vez por documento, y el aviso tarda.
--
-- Idempotente. CONCURRENTLY no bloquea las escrituras de la migración; en
-- pgAdmin ejecutar esta sentencia SOLA (no admite ir dentro de una transacción).
-- =============================================================================

CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_migmap_clave_natural
    ON migracion_mysql_map (id_empresa, entidad, clave_natural);
