-- =============================================================================
-- 20260917_busqueda_trigram_consignaciones.sql
-- Motor de búsqueda con índices trigram — segunda entrega: CONSIGNACIONES DE VENTA
-- -----------------------------------------------------------------------------
-- Qué hace  : crea los índices GIN que usa el buscador del listado de
--             Consignaciones de Venta. Las expresiones las genera el propio
--             repositorio (ConsignacionVentaRepository::sqlIndicesBusqueda()):
--             son EXACTAMENTE las que usa la consulta.
--             Incluye otra vez la extensión, la función y los índices de clientes
--             y productos (con IF NOT EXISTS) por si este archivo se ejecuta antes
--             que el de Pedidos: da igual el orden y se puede repetir.
--
-- Por qué    : el listado ya hace una sola consulta y busca por conjuntos, pero sin
--             índices cada conjunto recorre su tabla entera (26.000 clientes,
--             68.000 productos, 233.000 líneas de consignación…) y el número, las
--             observaciones y los puntos se comparan consignación por consignación.
--             Medido en producción el 17-09-2026: 2,0 s de media por búsqueda
--             leyendo ~12 GB. Reproducido en local con 50.000 consignaciones:
--             0,72 a 1,76 s. Con estos índices, los mismos casos quedan en
--             centésimas de segundo, devolviendo exactamente las mismas filas.
--
-- Toca datos: NO. Solo crea extensión, función e índices.
-- Reversible: sí (ver el bloque comentado del final).
-- Cuándo     : en horario de baja actividad — CREATE INDEX bloquea las escrituras
--             de cada tabla mientras se construye (las lecturas siguen normales).
-- Cómo       : pgAdmin → Query Tool sobre producción → pegar todo → F5.
-- Orden de despliegue: indistinto respecto del código.
-- =============================================================================

-- 1. Extensión y función inmutable (idénticas a las del archivo de Pedidos) ----
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE EXTENSION IF NOT EXISTS unaccent;

CREATE OR REPLACE FUNCTION public.f_unaccent(text)
RETURNS text
LANGUAGE sql
IMMUTABLE
PARALLEL SAFE
STRICT
AS $$ SELECT public.unaccent('public.unaccent'::regdictionary, $1) $$;

-- 2. Índices del buscador de Consignaciones de Venta -------------------------
--    (generados por ConsignacionVentaRepository::sqlIndicesBusqueda(); no editar a mano)

-- Consignación: número (serie-secuencial), observaciones, punto de partida,
-- punto de llegada, fecha (en los dos formatos en que se puede escribir) y total.
CREATE INDEX IF NOT EXISTS idx_trgm_consignaciones_ventas ON consignaciones_ventas USING gin (f_unaccent(COALESCE(serie, '') || '-' || COALESCE(secuencial, '') || ' ' || COALESCE(establecimiento, '') || '-' || COALESCE(punto_emision, '') || '-' || LPAD(COALESCE(secuencial, ''), GREATEST(9, LENGTH(COALESCE(secuencial, ''))), '0') || ' ' || COALESCE(observaciones, '') || ' ' || COALESCE(lpad(extract(day FROM fecha_emision)::int::text, 2, '0') || '-' || lpad(extract(month FROM fecha_emision)::int::text, 2, '0') || '-' || lpad(extract(year FROM fecha_emision)::int::text, 4, '0'), '') || ' ' || COALESCE(lpad(extract(year FROM fecha_emision)::int::text, 4, '0') || '-' || lpad(extract(month FROM fecha_emision)::int::text, 2, '0') || '-' || lpad(extract(day FROM fecha_emision)::int::text, 2, '0'), '')) gin_trgm_ops);

-- Cliente y producto: los comparten todos los módulos (si ya se creó con el
-- archivo de Pedidos, esta línea no hace nada).
CREATE INDEX IF NOT EXISTS idx_trgm_clientes ON clientes USING gin (f_unaccent(COALESCE(nombre, '') || ' ' || COALESCE(identificacion, '')) gin_trgm_ops);
CREATE INDEX IF NOT EXISTS idx_trgm_productos ON productos USING gin (f_unaccent(COALESCE(codigo, '') || ' ' || COALESCE(nombre, '')) gin_trgm_ops);

-- Lote y NUP de las líneas consignadas.
CREATE INDEX IF NOT EXISTS idx_trgm_cons_det_lote_nup ON consignaciones_ventas_detalles USING gin (f_unaccent(COALESCE(lote, '') || ' ' || COALESCE(nup, '')) gin_trgm_ops);

-- Números de los documentos relacionados: facturaciones, retornos y cambios.
CREATE INDEX IF NOT EXISTS idx_trgm_consignaciones_facturas ON consignaciones_facturas USING gin (f_unaccent(COALESCE(serie, '') || '-' || COALESCE(secuencial, '') || ' ' || COALESCE(numero_factura, '')) gin_trgm_ops);
CREATE INDEX IF NOT EXISTS idx_trgm_retornos_numero ON retornos_cv USING gin (f_unaccent(COALESCE(serie, '') || '-' || COALESCE(secuencial, '')) gin_trgm_ops);
CREATE INDEX IF NOT EXISTS idx_trgm_cambios_numero ON cambios_producto_cv USING gin (f_unaccent(COALESCE(serie, '') || '-' || COALESCE(secuencial, '')) gin_trgm_ops);

-- 3. Estadísticas ------------------------------------------------------------
ANALYZE consignaciones_ventas;
ANALYZE consignaciones_ventas_detalles;
ANALYZE consignaciones_facturas;
ANALYZE retornos_cv;
ANALYZE cambios_producto_cv;
ANALYZE clientes;
ANALYZE productos;

-- =============================================================================
-- COMPROBACIÓN (opcional) — deben salir 7 filas, todas con "creado"
-- =============================================================================
-- SELECT i.indice,
--        CASE WHEN c.oid IS NULL THEN 'FALTA' ELSE 'creado' END AS estado,
--        COALESCE(pg_size_pretty(pg_relation_size(c.oid)), '-') AS tamano
-- FROM (VALUES ('idx_trgm_consignaciones_ventas'), ('idx_trgm_clientes'), ('idx_trgm_productos'),
--              ('idx_trgm_cons_det_lote_nup'), ('idx_trgm_consignaciones_facturas'),
--              ('idx_trgm_retornos_numero'), ('idx_trgm_cambios_numero')) AS i(indice)
-- LEFT JOIN pg_class c ON c.relname = i.indice AND c.relkind = 'i'
-- ORDER BY 1;

-- =============================================================================
-- REVERTIR
-- =============================================================================
-- DROP INDEX IF EXISTS idx_trgm_consignaciones_ventas;
-- DROP INDEX IF EXISTS idx_trgm_cons_det_lote_nup;
-- DROP INDEX IF EXISTS idx_trgm_consignaciones_facturas;
-- DROP INDEX IF EXISTS idx_trgm_retornos_numero;
-- DROP INDEX IF EXISTS idx_trgm_cambios_numero;
-- (idx_trgm_clientes e idx_trgm_productos los usan también otros módulos: no borrarlos
--  si ya se migró Pedidos)

-- =============================================================================
-- VARIANTE SIN BLOQUEAR ESCRITURAS: igual que en el archivo de Pedidos —
-- CREATE INDEX CONCURRENTLY, enviando UNA sentencia a la vez.
-- =============================================================================
