-- =============================================================================
-- 20260917d_ajuste_busqueda_pedidos.sql
-- Buscador del listado de PEDIDOS: solo lo que se ve en la tabla
-- -----------------------------------------------------------------------------
-- Qué hace  : se asegura de que exista `idx_trgm_clientes_nombre` y borra los tres
--             índices que este cambio deja sin uso en todo el sistema.
--
-- Por qué    : el cuadro de búsqueda del listado devolvía pedidos sin que se viera POR
--             QUÉ coincidían — el texto podía estar en un producto de una línea, en el
--             número de la consignación o de la factura que consumió el pedido, en la
--             identificación del cliente o en el usuario que lo registró; ninguno de esos
--             datos es columna de la tabla. Ahora el cuadro busca solo en lo que se ve:
--             número, fechas de pedido y entrega, rango horario, observaciones,
--             observaciones internas, cliente y responsable de entrega. Los productos y
--             los números de consignación o factura siguen en la pestaña "Detalles" del
--             modal de filtros —que además dice cuál coincidió—, y el RUC y el usuario en
--             sus filtros. Mismo criterio que el resto de módulos (17-09-2026).
--
-- Toca datos: NO. Solo índices.
-- Reversible: sí (bloque comentado del final).
-- Cuándo     : cuando se quiera; el CREATE de abajo normalmente ya estará hecho por el
--             archivo de Consignaciones, así que este archivo casi siempre solo borra.
-- Cómo       : pgAdmin → Query Tool sobre producción → pegar todo → F5.
-- Se puede repetir sin problema. Correr DESPUÉS de desplegar el código (mientras el
-- código viejo siga arriba, esos índices todavía se usan).
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

-- 2. Cliente por NOMBRE ------------------------------------------------------
-- El mismo índice que usa Consignaciones de Venta (20260917c): si ese archivo ya se
-- corrió, esta línea no hace nada. `idx_trgm_clientes` (nombre + identificación) NO se
-- toca: lo sigue usando Facturas de Venta.
CREATE INDEX IF NOT EXISTS idx_trgm_clientes_nombre ON clientes USING gin (f_unaccent(COALESCE(nombre, '')) gin_trgm_ops);

-- 3. Índices que quedan sin uso ----------------------------------------------
-- Servían para buscar, desde el listado, el producto de las líneas y el número de los
-- documentos que consumieron el pedido. Esa búsqueda vive ahora en la pestaña "Detalles",
-- que compara columna por columna y no puede usarlos.
--   - idx_trgm_productos: lo usaban Pedidos y Consignaciones de Venta; ninguno de los dos
--     lo usa ya. NO confundir con `idx_trgm_productos_codigos` (código, nombre, auxiliar y
--     de barras), que SÍ se sigue usando en el buscador de producto de los modales.
--   - idx_trgm_consignaciones_numero / idx_trgm_ventas_numero: solo los usaba este módulo.
-- Si algún día vuelven a hacer falta, los regenera PedidoRepository::sqlIndicesBusqueda().
DROP INDEX IF EXISTS idx_trgm_productos;
DROP INDEX IF EXISTS idx_trgm_consignaciones_numero;
DROP INDEX IF EXISTS idx_trgm_ventas_numero;

-- 4. Estadísticas ------------------------------------------------------------
ANALYZE clientes;

-- =============================================================================
-- COMPROBACIÓN (opcional) — los dos primeros deben decir 'creado' y los tres
-- últimos 'borrado'
-- =============================================================================
-- SELECT i.indice, CASE WHEN c.oid IS NULL THEN 'borrado' ELSE 'creado' END AS estado
-- FROM (VALUES ('idx_trgm_clientes_nombre'), ('idx_trgm_productos_codigos'),
--              ('idx_trgm_productos'), ('idx_trgm_consignaciones_numero'),
--              ('idx_trgm_ventas_numero')) AS i(indice)
-- LEFT JOIN pg_class c ON c.relname = i.indice AND c.relkind = 'i'
-- ORDER BY 1;

-- =============================================================================
-- REVERTIR (si se vuelve al buscador anterior)
-- =============================================================================
-- CREATE INDEX IF NOT EXISTS idx_trgm_productos ON productos USING gin (f_unaccent(COALESCE(codigo, '') || ' ' || COALESCE(nombre, '')) gin_trgm_ops);
-- CREATE INDEX IF NOT EXISTS idx_trgm_consignaciones_numero ON consignaciones_ventas USING gin (f_unaccent(COALESCE(establecimiento, '') || '-' || COALESCE(punto_emision, '') || '-' || COALESCE(secuencial, '')) gin_trgm_ops);
-- CREATE INDEX IF NOT EXISTS idx_trgm_ventas_numero ON ventas_cabecera USING gin (f_unaccent(COALESCE(establecimiento, '') || '-' || COALESCE(punto_emision, '') || '-' || COALESCE(secuencial, '')) gin_trgm_ops);
