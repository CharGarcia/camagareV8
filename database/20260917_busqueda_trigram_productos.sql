-- =============================================================================
-- 20260917_busqueda_trigram_productos.sql
-- Motor de búsqueda con índices trigram — cuarta entrega: LISTADO DE PRODUCTOS
-- -----------------------------------------------------------------------------
-- Qué hace  : crea los índices GIN que usa la búsqueda del listado del módulo
--             Productos (la "amplia": además de los códigos y la descripción, mira
--             categoría, marca, medida, ubicación, importes, variantes y códigos de
--             proveedor). Las expresiones las genera el propio repositorio
--             (ProductoRepository::sqlIndicesBusqueda()).
--             Repite con IF NOT EXISTS la extensión, la función y el índice de
--             productos del archivo de Facturas: da igual el orden y se puede
--             reejecutar.
--
-- Por qué    : el listado armaba, POR CADA producto del catálogo, el texto de sus
--             variantes y de sus códigos de proveedor con subconsultas, y lo hacía
--             dos veces (un COUNT y un SELECT con el mismo WHERE). Medido en local
--             con 68.000 productos (13.670 variantes y 3.407 homologaciones):
--             **33 a 35 segundos** por búsqueda. Con el código nuevo y estos índices,
--             los mismos casos quedan por debajo de medio segundo, devolviendo
--             exactamente las mismas filas.
--
-- Toca datos: NO. Solo crea extensión, función e índices.
-- Reversible: sí (bloque comentado del final).
-- Cuándo     : en horario de baja actividad — CREATE INDEX bloquea las escrituras de
--             cada tabla mientras se construye (las lecturas no).
-- Cómo       : pgAdmin → Query Tool sobre producción → pegar todo → F5.
-- =============================================================================

-- 1. Extensión y función inmutable (idénticas a las de los otros archivos) -----
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE EXTENSION IF NOT EXISTS unaccent;

CREATE OR REPLACE FUNCTION public.f_unaccent(text)
RETURNS text
LANGUAGE sql
IMMUTABLE
PARALLEL SAFE
STRICT
AS $$ SELECT public.unaccent('public.unaccent'::regdictionary, $1) $$;

-- 2. Índices del buscador del listado de Productos ---------------------------
-- Código, descripción, código auxiliar y código de barras (el mismo índice que usa
-- el buscador de producto de facturas, POS, compras y comandas).
CREATE INDEX IF NOT EXISTS idx_trgm_productos_codigos ON productos USING gin (f_unaccent(COALESCE(codigo, '') || ' ' || COALESCE(nombre, '') || ' ' || COALESCE(codigo_auxiliar, '') || ' ' || COALESCE(codigo_barras, '')) gin_trgm_ops);

-- Variantes (nombre y valor) y códigos con que factura cada proveedor.
CREATE INDEX IF NOT EXISTS idx_trgm_productos_variantes ON productos_variantes USING gin (f_unaccent(COALESCE(nombre, '') || ' ' || COALESCE(valor, '')) gin_trgm_ops);
CREATE INDEX IF NOT EXISTS idx_trgm_productos_homologacion ON productos_homologacion USING gin (f_unaccent(COALESCE(codigo_proveedor, '')) gin_trgm_ops);

-- 3. Estadísticas ------------------------------------------------------------
ANALYZE productos;
ANALYZE productos_variantes;
ANALYZE productos_homologacion;

-- =============================================================================
-- COMPROBACIÓN (opcional) — deben salir 3 filas, todas con "creado"
-- =============================================================================
-- SELECT i.indice,
--        CASE WHEN c.oid IS NULL THEN 'FALTA' ELSE 'creado' END AS estado,
--        COALESCE(pg_size_pretty(pg_relation_size(c.oid)), '-') AS tamano
-- FROM (VALUES ('idx_trgm_productos_codigos'), ('idx_trgm_productos_variantes'),
--              ('idx_trgm_productos_homologacion')) AS i(indice)
-- LEFT JOIN pg_class c ON c.relname = i.indice AND c.relkind = 'i'
-- ORDER BY 1;

-- =============================================================================
-- REVERTIR
-- =============================================================================
-- DROP INDEX IF EXISTS idx_trgm_productos_variantes;
-- DROP INDEX IF EXISTS idx_trgm_productos_homologacion;
-- (idx_trgm_productos_codigos lo usa también el buscador de los documentos)
-- =============================================================================
