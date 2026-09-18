-- =============================================================================
-- 20260917c_ajuste_busqueda_consignaciones.sql
-- Buscador del listado de CONSIGNACIONES DE VENTA: solo lo que se ve en la tabla
-- -----------------------------------------------------------------------------
-- Qué hace  : recrea `idx_trgm_consignaciones_ventas` sin el punto de partida, el punto
--             de llegada ni el total; crea `idx_trgm_clientes_nombre`; y borra los
--             índices que este cambio deja sin uso.
--
-- Por qué    : el cuadro de búsqueda del listado devolvía consignaciones sin que se
--             viera POR QUÉ coincidían — el texto podía estar en un producto de una
--             línea, en un lote o NUP, en el número de una facturación, de un retorno o
--             de un cambio de producto, en el punto de partida o llegada, en el total, en
--             la identificación del cliente, en el responsable de traslado o en el
--             usuario que registró; ninguno de esos datos es columna de la tabla. Ahora
--             el cuadro busca solo en lo que se ve: número, observaciones, fecha, cliente
--             y asesor. Todo lo demás sigue disponible: los productos, lotes, NUP y los
--             documentos relacionados en la pestaña "Detalles" del modal de filtros
--             —que además dice cuál coincidió—, y el resto en sus filtros (`ruc:`,
--             `partida:`, `llegada:`, `total:`, `responsable:` y el selector de usuario).
--             Mismo criterio que ya se aplicó a Facturas de Venta, Compras, notas de
--             crédito y débito, retenciones, guías, liquidaciones y reembolsos.
--
-- Toca datos: NO. Solo índices.
-- Reversible: sí (bloque comentado del final).
-- Cuándo     : en horario de baja actividad — CREATE INDEX bloquea las escrituras de la
--             tabla mientras se construye (las lecturas siguen normales).
-- Cómo       : pgAdmin → Query Tool sobre producción → pegar todo → F5.
-- Se puede repetir sin problema, y da igual si 20260917_busqueda_trigram_consignaciones.sql
-- ya se corrió o todavía no.
-- Orden de despliegue: indistinto respecto del código (sin los índices la búsqueda
-- devuelve lo mismo, solo que más lenta).
-- =============================================================================

-- 1. Extensión y función inmutable (idénticas a las de los otros archivos) ----
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE EXTENSION IF NOT EXISTS unaccent;

CREATE OR REPLACE FUNCTION public.f_unaccent(text)
RETURNS text
LANGUAGE sql
IMMUTABLE
PARALLEL SAFE
STRICT
AS $$ SELECT public.unaccent('public.unaccent'::regdictionary, $1) $$;

-- 2. Cabecera de la consignación: número, observaciones y fecha --------------
-- (las dos formas de escribirla: 17-09-2026 y 2026-09-17)
DROP INDEX IF EXISTS idx_trgm_consignaciones_ventas;

CREATE INDEX idx_trgm_consignaciones_ventas ON consignaciones_ventas USING gin (f_unaccent(COALESCE(serie, '') || '-' || COALESCE(secuencial, '') || ' ' || COALESCE(establecimiento, '') || '-' || COALESCE(punto_emision, '') || '-' || LPAD(COALESCE(secuencial, ''), GREATEST(9, LENGTH(COALESCE(secuencial, ''))), '0') || ' ' || COALESCE(observaciones, '') || ' ' || COALESCE(lpad(extract(day FROM fecha_emision)::int::text, 2, '0') || '-' || lpad(extract(month FROM fecha_emision)::int::text, 2, '0') || '-' || lpad(extract(year FROM fecha_emision)::int::text, 4, '0'), '') || ' ' || COALESCE(lpad(extract(year FROM fecha_emision)::int::text, 4, '0') || '-' || lpad(extract(month FROM fecha_emision)::int::text, 2, '0') || '-' || lpad(extract(day FROM fecha_emision)::int::text, 2, '0'), '')) gin_trgm_ops);

-- 3. Cliente por NOMBRE -------------------------------------------------------
-- El listado muestra el nombre, no la identificación. El índice compartido
-- `idx_trgm_clientes` indexa "nombre + identificación" y un GIN trigram solo sirve para
-- la MISMA expresión, así que hace falta este, sobre el nombre solo. `idx_trgm_clientes`
-- NO se toca: lo siguen usando Facturas de Venta y Pedidos.
CREATE INDEX IF NOT EXISTS idx_trgm_clientes_nombre ON clientes USING gin (f_unaccent(COALESCE(nombre, '')) gin_trgm_ops);

-- 4. Índices que este cambio deja sin uso ------------------------------------
-- Los creó el archivo anterior para buscar, desde el listado, el lote/NUP de las líneas y
-- el número de los documentos relacionados. Esa búsqueda vive ahora en la pestaña
-- "Detalles", que compara columna por columna y no puede usarlos. Borrarlos libera su
-- espacio; si algún día vuelven a hacer falta, los regenera
-- ConsignacionVentaRepository::sqlIndicesBusqueda().
-- `idx_trgm_productos` NO se borra: lo usa también el listado de Pedidos.
DROP INDEX IF EXISTS idx_trgm_cons_det_lote_nup;
DROP INDEX IF EXISTS idx_trgm_consignaciones_facturas;
DROP INDEX IF EXISTS idx_trgm_retornos_numero;
DROP INDEX IF EXISTS idx_trgm_cambios_numero;

-- 5. Estadísticas ------------------------------------------------------------
ANALYZE consignaciones_ventas;
ANALYZE clientes;

-- =============================================================================
-- COMPROBACIÓN (opcional)
-- =============================================================================
-- (a) El índice de la cabecera ya no menciona los puntos ni el total → 'ok'
-- SELECT CASE WHEN pg_get_indexdef(c.oid) LIKE '%punto_%' OR pg_get_indexdef(c.oid) LIKE '%total%'
--             THEN 'TODAVIA LOS TIENE' ELSE 'ok' END AS estado,
--        pg_size_pretty(pg_relation_size(c.oid)) AS tamano
-- FROM pg_class c WHERE c.relname = 'idx_trgm_consignaciones_ventas' AND c.relkind = 'i';
--
-- (b) Los dos índices que deben existir
-- SELECT i.indice, CASE WHEN c.oid IS NULL THEN 'FALTA' ELSE 'creado' END AS estado
-- FROM (VALUES ('idx_trgm_consignaciones_ventas'), ('idx_trgm_clientes_nombre'),
--              ('idx_trgm_clientes'), ('idx_trgm_productos')) AS i(indice)
-- LEFT JOIN pg_class c ON c.relname = i.indice AND c.relkind = 'i'
-- ORDER BY 1;

-- =============================================================================
-- REVERTIR (volver a buscar todo desde el listado)
-- =============================================================================
-- DROP INDEX IF EXISTS idx_trgm_consignaciones_ventas;
-- \i database/20260917_busqueda_trigram_consignaciones.sql   -- (con la expresión de esa fecha)

-- =============================================================================
-- VARIANTE SIN BLOQUEAR ESCRITURAS
-- =============================================================================
-- Enviando UNA sentencia a la vez (CONCURRENTLY no puede ir en un bloque):
--   CREATE INDEX CONCURRENTLY idx_trgm_consignaciones_ventas_new ON consignaciones_ventas USING gin (…misma expresión…);
--   DROP INDEX CONCURRENTLY IF EXISTS idx_trgm_consignaciones_ventas;
--   ALTER INDEX idx_trgm_consignaciones_ventas_new RENAME TO idx_trgm_consignaciones_ventas;
