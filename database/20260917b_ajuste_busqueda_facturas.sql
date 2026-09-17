-- =============================================================================
-- 20260917b_ajuste_busqueda_facturas.sql
-- Buscador del listado de FACTURAS DE VENTA: sacar la clave de acceso del índice
-- -----------------------------------------------------------------------------
-- Qué hace  : recrea `idx_trgm_ventas_cabecera` sin la clave de acceso y borra
--             `idx_trgm_ventas_detalle`, que ya no usa nadie.
--
-- Por qué    : el texto libre del listado buscaba dentro de la CLAVE DE ACCESO (49
--             dígitos: fecha + RUC + serie + secuencial + un código numérico aleatorio
--             de 8). Un número de factura corto cae ahí dentro por puro azar, así que
--             el listado devolvía facturas sin ninguna coincidencia visible: buscar
--             "556605" traía facturas ajenas. Comprobado en local: "080686" devolvía
--             una factura cuyo único parecido estaba dentro de su clave, y "657400"
--             —un trozo del RUC del emisor, presente en TODAS las claves— devolvía las
--             36 facturas de la empresa. Con documentos de producción, un término de 6
--             dígitos da ~2 filas falsas y uno de 5 dígitos ~20.
--             La clave de acceso se sigue buscando con el filtro `clave:…`
--             (o `clave_acceso:…`), que compara solo esa columna.
--             De paso salen del texto libre, por la misma decisión, el usuario que
--             registró (queda el filtro `usuario:…`) y los productos del detalle (quedan
--             en la pestaña "Detalles" del modal de filtros, que sí muestra qué línea
--             coincidió). Por eso `idx_trgm_ventas_detalle` queda sin uso.
--
-- Toca datos: NO. Solo índices.
-- Reversible: sí (bloque comentado del final).
-- Cuándo     : en horario de baja actividad — CREATE INDEX bloquea las escrituras de
--             ventas_cabecera mientras se construye (las lecturas siguen normales).
--             Medido en local con volúmenes de producción: ~6 s.
-- Cómo       : pgAdmin → Query Tool sobre producción → pegar todo → F5.
-- Se puede repetir sin problema, y da igual si el archivo
-- 20260917_busqueda_trigram_facturas.sql ya se corrió o todavía no.
-- Orden de despliegue: indistinto respecto del código (sin el índice la búsqueda
-- devuelve lo mismo, solo que más lenta).
-- =============================================================================

-- 1. Extensión y función inmutable (idénticas a las de los otros archivos) ----
-- Van aquí para que este archivo funcione solo, aunque todavía no se haya corrido
-- 20260917_busqueda_trigram_facturas.sql. Si ya existen, estas tres líneas no hacen nada.
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE EXTENSION IF NOT EXISTS unaccent;

CREATE OR REPLACE FUNCTION public.f_unaccent(text)
RETURNS text
LANGUAGE sql
IMMUTABLE
PARALLEL SAFE
STRICT
AS $$ SELECT public.unaccent('public.unaccent'::regdictionary, $1) $$;

-- 2. Índice del listado, sin la clave de acceso ------------------------------
DROP INDEX IF EXISTS idx_trgm_ventas_cabecera;

-- Número, observaciones, guía de remisión, placa, fecha e importes (subtotal,
-- descuento, IVA calculado, ICE, propina y total). La expresión sale tal cual de
-- FacturaVentaRepository::sqlIndicesBusqueda(), que es la misma que usa la consulta.
CREATE INDEX idx_trgm_ventas_cabecera ON ventas_cabecera USING gin (f_unaccent(COALESCE(establecimiento, '') || '-' || COALESCE(punto_emision, '') || '-' || COALESCE(secuencial, '') || ' ' || COALESCE(observaciones, '') || ' ' || COALESCE(guia_remision, '') || ' ' || COALESCE(placa, '') || ' ' || COALESCE(lpad(extract(year FROM fecha_emision)::int::text, 4, '0') || '-' || lpad(extract(month FROM fecha_emision)::int::text, 2, '0') || '-' || lpad(extract(day FROM fecha_emision)::int::text, 2, '0'), '') || ' ' || COALESCE(total_sin_impuestos::text, '') || ' ' || COALESCE(total_descuento::text, '') || ' ' || COALESCE((importe_total - total_sin_impuestos + total_descuento - COALESCE(total_ice, 0) - COALESCE(propina, 0))::text, '') || ' ' || COALESCE(total_ice::text, '') || ' ' || COALESCE(propina::text, '') || ' ' || COALESCE(importe_total::text, '')) gin_trgm_ops);

-- 3. Índice de las líneas: ya no lo usa ninguna consulta ----------------------
-- Lo creaba el archivo anterior para buscar el producto desde el listado. Ahora esa
-- búsqueda vive en la pestaña "Detalles" del modal, que compara columna por columna y
-- no puede usar este índice. Borrarlo libera su espacio; si algún día vuelve a hacer
-- falta, lo regenera FacturaVentaRepository::sqlIndicesBusqueda().
DROP INDEX IF EXISTS idx_trgm_ventas_detalle;

-- 4. Estadísticas ------------------------------------------------------------
ANALYZE ventas_cabecera;

-- =============================================================================
-- COMPROBACIÓN (opcional)
-- =============================================================================
-- (a) El índice nuevo existe y NO menciona la clave de acceso → debe decir 'ok'
-- SELECT CASE WHEN pg_get_indexdef(c.oid) LIKE '%clave_acceso%' THEN 'TODAVIA LA TIENE'
--             ELSE 'ok' END AS estado,
--        pg_size_pretty(pg_relation_size(c.oid)) AS tamano
-- FROM pg_class c WHERE c.relname = 'idx_trgm_ventas_cabecera' AND c.relkind = 'i';
--
-- (b) El caso que lo motivó: poner abajo un número de factura de la empresa. Debe
--     devolver solo las facturas que lo tienen en el número, no las de la clave.
-- SELECT establecimiento || '-' || punto_emision || '-' || secuencial AS numero,
--        clave_acceso
-- FROM ventas_cabecera
-- WHERE id_empresa = <ID_EMPRESA> AND eliminado = false
--   AND clave_acceso LIKE '%556605%'
--   AND secuencial NOT LIKE '%556605%';   -- estas son las que ya NO deben salir

-- =============================================================================
-- REVERTIR (volver a incluir la clave de acceso y el índice de líneas)
-- =============================================================================
-- DROP INDEX IF EXISTS idx_trgm_ventas_cabecera;
-- \i database/20260917_busqueda_trigram_facturas.sql   -- (con la expresión de esa fecha)

-- =============================================================================
-- VARIANTE SIN BLOQUEAR ESCRITURAS
-- =============================================================================
-- Enviando UNA sentencia a la vez (CONCURRENTLY no puede ir en un bloque):
--   CREATE INDEX CONCURRENTLY idx_trgm_ventas_cabecera_new ON ventas_cabecera USING gin (…misma expresión…);
--   DROP INDEX CONCURRENTLY IF EXISTS idx_trgm_ventas_cabecera;
--   ALTER INDEX idx_trgm_ventas_cabecera_new RENAME TO idx_trgm_ventas_cabecera;
