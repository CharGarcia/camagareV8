-- =============================================================================
-- 20260917_busqueda_trigram_facturas.sql
-- Motor de búsqueda con índices trigram — tercera entrega:
--   FACTURAS DE VENTA + BUSCADOR DE PRODUCTO de los documentos
-- -----------------------------------------------------------------------------
-- Qué hace  : crea los índices GIN que usan el buscador del listado de Facturas de
--             Venta y el buscador de producto del modal de factura / POS / compras /
--             comandas. Las expresiones las generan los propios repositorios
--             (FacturaVentaRepository / ProductoRepository ::sqlIndicesBusqueda()).
--             Repite la extensión, la función y el índice de clientes con
--             IF NOT EXISTS: da igual el orden respecto de los otros archivos.
--
-- Por qué    : medido en producción el 17-09-2026 — el listado de Facturas gastaba
--             0,49 s de media por búsqueda (3,0 s la peor) leyendo ~0,7 GB, y el
--             buscador de producto 3.280 llamadas en 15 horas a 94 MB cada una.
--             Reproducido en local: 30.000 facturas, 0,4–1,4 s → 0,05–0,21 s;
--             68.000 productos, 0,06–0,29 s → 0,003–0,06 s. Mismas filas.
--
-- Toca datos: NO. Solo crea extensión, función e índices.
-- Reversible: sí (bloque comentado del final).
-- Cuándo     : en horario de baja actividad — CREATE INDEX bloquea las escrituras de
--             cada tabla mientras se construye (las lecturas no). Medido en local con
--             volúmenes de producción: ventas_cabecera 5,7 s · productos 0,9 s.
--             Espacio: ~30 MB en total.
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

-- 2. Listado de Facturas de Venta --------------------------------------------
-- Factura: número, observaciones, guía, placa, fecha e importes (subtotal,
-- descuento, IVA calculado, ICE, propina y total).
--
-- La CLAVE DE ACCESO no está aquí a propósito (17-09-2026): son 49 dígitos, así que
-- cualquier número de factura corto caía dentro de la clave de otras facturas por azar
-- y el listado devolvía filas sin coincidencia visible. Se busca con el filtro clave:…
-- Tampoco están los productos del detalle (pestaña "Detalles" del modal de filtros) ni
-- el usuario que registró (filtro usuario:…), por la misma decisión.
-- Si esta base YA tenía el índice con la expresión anterior, hay que recrearlo:
-- ver database/20260917b_ajuste_busqueda_facturas.sql.
CREATE INDEX IF NOT EXISTS idx_trgm_ventas_cabecera ON ventas_cabecera USING gin (f_unaccent(COALESCE(establecimiento, '') || '-' || COALESCE(punto_emision, '') || '-' || LPAD(COALESCE(secuencial, ''), GREATEST(9, LENGTH(COALESCE(secuencial, ''))), '0') || ' ' || COALESCE(observaciones, '') || ' ' || COALESCE(guia_remision, '') || ' ' || COALESCE(placa, '') || ' ' || COALESCE(lpad(extract(year FROM fecha_emision)::int::text, 4, '0') || '-' || lpad(extract(month FROM fecha_emision)::int::text, 2, '0') || '-' || lpad(extract(day FROM fecha_emision)::int::text, 2, '0'), '') || ' ' || COALESCE(total_sin_impuestos::text, '') || ' ' || COALESCE(total_descuento::text, '') || ' ' || COALESCE((importe_total - total_sin_impuestos + total_descuento - COALESCE(total_ice, 0) - COALESCE(propina, 0))::text, '') || ' ' || COALESCE(total_ice::text, '') || ' ' || COALESCE(propina::text, '') || ' ' || COALESCE(importe_total::text, '')) gin_trgm_ops);

-- Cliente (lo comparten todos los módulos; si ya se creó, esta línea no hace nada).
CREATE INDEX IF NOT EXISTS idx_trgm_clientes ON clientes USING gin (f_unaccent(COALESCE(nombre, '') || ' ' || COALESCE(identificacion, '')) gin_trgm_ops);

-- 3. Buscador de producto de los documentos ----------------------------------
-- Nombre y los TRES códigos (principal, auxiliar y de barras). Es un índice distinto
-- del de `idx_trgm_productos` (solo código y nombre), que usan los listados que buscan
-- "el producto dentro del documento" (Pedidos, Consignaciones): cada módulo busca en
-- las columnas que se decidieron para él.
CREATE INDEX IF NOT EXISTS idx_trgm_productos_codigos ON productos USING gin (f_unaccent(COALESCE(codigo, '') || ' ' || COALESCE(nombre, '') || ' ' || COALESCE(codigo_auxiliar, '') || ' ' || COALESCE(codigo_barras, '')) gin_trgm_ops);

-- 4. Estadísticas ------------------------------------------------------------
ANALYZE ventas_cabecera;
ANALYZE productos;
ANALYZE clientes;

-- =============================================================================
-- COMPROBACIÓN (opcional) — deben salir 3 filas, todas con "creado"
-- =============================================================================
-- SELECT i.indice,
--        CASE WHEN c.oid IS NULL THEN 'FALTA' ELSE 'creado' END AS estado,
--        COALESCE(pg_size_pretty(pg_relation_size(c.oid)), '-') AS tamano
-- FROM (VALUES ('idx_trgm_ventas_cabecera'),
--              ('idx_trgm_clientes'), ('idx_trgm_productos_codigos')) AS i(indice)
-- LEFT JOIN pg_class c ON c.relname = i.indice AND c.relkind = 'i'
-- ORDER BY 1;

-- =============================================================================
-- REVERTIR
-- =============================================================================
-- DROP INDEX IF EXISTS idx_trgm_ventas_cabecera;
-- DROP INDEX IF EXISTS idx_trgm_productos_codigos;
-- (idx_trgm_clientes lo usan también otros módulos: no borrarlo)

-- =============================================================================
-- VARIANTE SIN BLOQUEAR ESCRITURAS: CREATE INDEX CONCURRENTLY, enviando UNA
-- sentencia a la vez (ver el archivo de Pedidos para el detalle).
-- =============================================================================
