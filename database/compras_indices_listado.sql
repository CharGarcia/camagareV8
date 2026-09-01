-- ============================================================================
-- Compras: índices para el listado paginado (modulos/compras)
-- ----------------------------------------------------------------------------
-- Contexto:
--   El listado de Compras se estaba poniendo lento al crecer la tabla. Junto a
--   los arreglos en ComprasRepository::getListado() (dejar de traer detalle_xml
--   en cada fila, aligerar el COUNT y desempatar el ORDER BY por id), faltaba
--   el soporte de índices: compras_cabecera solo tenía índices de UNA columna
--   (id_empresa, eliminado, fecha_emision por separado), así que para
--
--       WHERE id_empresa = ? AND eliminado = false AND tipo_ambiente = ?
--       ORDER BY fecha_emision DESC, id DESC
--       LIMIT 20 OFFSET ?
--
--   el planificador recorría idx_compras_fecha —que es GLOBAL, de todas las
--   empresas— hacia atrás, descartando fila por fila las que no eran de la
--   empresa/ambiente actual. Ese plan se degrada linealmente con el tamaño
--   total de la tabla: cuantas más compras tengan las demás empresas, más lento
--   el listado de cada una.
--
-- Qué hace:
--   Crea dos índices. NO modifica ni borra datos.
--
-- Reversible: sí, sin pérdida de datos —
--   DROP INDEX IF EXISTS idx_compras_listado;
--   DROP INDEX IF EXISTS idx_compras_nc_documento_modificado;
--
-- CÓMO EJECUTARLO:
--   Los CREATE INDEX van CONCURRENTLY para no bloquear las escrituras sobre
--   compras_cabecera mientras se construyen. Eso obliga a ejecutarlos FUERA de
--   una transacción: correr este archivo con psql tal cual (sin -1 / --single-
--   transaction), o pegar cada sentencia por separado. Si sale
--   "CREATE INDEX CONCURRENTLY cannot run inside a transaction block", es que
--   quedó envuelto en un BEGIN.
--
--   psql "$DATABASE_URL" -f database/compras_indices_listado.sql
--
--   Si un CONCURRENTLY falla a medio camino deja el índice en estado inválido;
--   se limpia con DROP INDEX IF EXISTS <nombre>; y se vuelve a lanzar.
-- ============================================================================

-- 1) Índice principal del listado ------------------------------------------
--    Cubre, en este orden: el filtro multiempresa (id_empresa), el filtro de
--    ambiente (tipo_ambiente) y el orden por defecto (fecha_emision DESC,
--    id DESC). El WHERE parcial sobre eliminado = false deja fuera del índice
--    las compras borradas lógicamente, que el listado nunca muestra: el índice
--    ocupa menos y no hay que volver a comprobar la condición.
--    El id al final sirve al desempate del ORDER BY y permite resolver la
--    paginación entera desde el índice.
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_compras_listado
    ON compras_cabecera (id_empresa, tipo_ambiente, fecha_emision DESC, id DESC)
    WHERE eliminado = false;

-- 2) Índice para el saldo por notas de crédito ------------------------------
--    Cada fila del listado calcula su saldo restando las NC del proveedor con
--    la subconsulta total_nc, que cruza por documento_modificado. Sin índice,
--    esa subconsulta se resuelve con un bitmap scan por id_proveedor y filtra
--    el resto a mano, una vez por cada fila de la página.
--    Es un índice parcial y muy pequeño: solo entran las NC (tipo '04') vivas,
--    que son una fracción mínima de la tabla.
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_compras_nc_documento_modificado
    ON compras_cabecera (id_empresa, id_proveedor, documento_modificado)
    WHERE tipo_comprobante = '04' AND eliminado = false;

-- 3) Refrescar estadísticas para que el planificador use los índices nuevos --
ANALYZE compras_cabecera;
