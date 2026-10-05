-- =============================================================================
-- 20261005_iva_listado_facturas_indice.sql
-- Listado de FACTURAS DE VENTA: columna IVA corregida → recrear el índice del buscador
-- -----------------------------------------------------------------------------
-- Qué hace  : recrea `idx_trgm_ventas_cabecera` con la expresión nueva del IVA
--             calculado, para que el texto libre del listado siga usando el índice.
--
-- Por qué    : la columna IVA del listado (y de su PDF/Excel) no es una columna de la
--             tabla: se deduce de la cabecera. La fórmula anterior era
--                 importe_total - total_sin_impuestos + total_descuento - ICE - propina
--             pero `total_sin_impuestos` se guarda YA NETO de descuento (así lo arma el
--             modal, la migración desde MySQL y el XML del SRI), así que sumar otra vez
--             el descuento inflaba el IVA mostrado exactamente en el valor del descuento.
--             Ejemplo: subtotal 9,00 − desc. 1,00 → total 10,35; IVA real 1,35; el listado
--             mostraba 2,35. Solo se notaba en facturas CON descuento (p. ej. las
--             001-xxx-30677 y 29348 de la empresa 1792708389001). Fórmula corregida:
--                 importe_total - total_sin_impuestos - ICE - propina
--             (la misma que ya usan Compras y el reporte de retenciones pendientes).
--             El mismo IVA calculado forma parte del texto que indexa el buscador, y
--             Postgres solo usa un índice de expresión si la expresión es IDÉNTICA a la
--             de la consulta: con el código nuevo y el índice viejo, la búsqueda sigue
--             dando lo mismo pero sin índice (lenta). Por eso se recrea.
--
-- Toca datos: NO. Solo el índice.
-- Reversible: sí (bloque comentado del final).
-- Cuándo     : en horario de baja actividad — CREATE INDEX bloquea las escrituras de
--             ventas_cabecera mientras se construye (las lecturas siguen normales).
--             Medido en local con volúmenes de producción: ~6 s (misma expresión, un
--             término menos). Variante CONCURRENTLY al final.
-- Cómo       : pgAdmin → Query Tool sobre producción → pegar todo → F5.
-- Se puede repetir sin problema.
-- Orden de despliegue: indistinto respecto del código (sin el índice la búsqueda
-- devuelve lo mismo, solo que más lenta). Recibos de venta recibió la misma corrección
-- en el código, pero su listado no tiene índice de expresión: no necesita SQL.
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

-- 2. Índice del listado, con el IVA calculado sin sumar el descuento ---------
-- La expresión sale tal cual de FacturaVentaRepository::sqlIndicesBusqueda(), que es
-- la misma que usa la consulta.
DROP INDEX IF EXISTS idx_trgm_ventas_cabecera;

CREATE INDEX idx_trgm_ventas_cabecera ON ventas_cabecera USING gin (f_unaccent(COALESCE(establecimiento, '') || '-' || COALESCE(punto_emision, '') || '-' || LPAD(COALESCE(secuencial, ''), GREATEST(9, LENGTH(COALESCE(secuencial, ''))), '0') || ' ' || COALESCE(observaciones, '') || ' ' || COALESCE(guia_remision, '') || ' ' || COALESCE(placa, '') || ' ' || COALESCE(lpad(extract(year FROM fecha_emision)::int::text, 4, '0') || '-' || lpad(extract(month FROM fecha_emision)::int::text, 2, '0') || '-' || lpad(extract(day FROM fecha_emision)::int::text, 2, '0'), '') || ' ' || COALESCE(total_sin_impuestos::text, '') || ' ' || COALESCE(total_descuento::text, '') || ' ' || COALESCE((importe_total - total_sin_impuestos - COALESCE(total_ice, 0) - COALESCE(propina, 0))::text, '') || ' ' || COALESCE(total_ice::text, '') || ' ' || COALESCE(propina::text, '') || ' ' || COALESCE(importe_total::text, '')) gin_trgm_ops);

-- 3. Estadísticas ------------------------------------------------------------
ANALYZE ventas_cabecera;

-- =============================================================================
-- COMPROBACIÓN (opcional)
-- =============================================================================
-- (a) El índice nuevo ya no suma el descuento → debe decir 'ok'
-- SELECT CASE WHEN pg_get_indexdef(c.oid) LIKE '%+ total_descuento%' THEN 'TODAVIA SUMA EL DESCUENTO'
--             ELSE 'ok' END AS estado,
--        pg_size_pretty(pg_relation_size(c.oid)) AS tamano
-- FROM pg_class c WHERE c.relname = 'idx_trgm_ventas_cabecera' AND c.relkind = 'i';
--
-- (b) Las facturas que lo motivaron: IVA que mostraba el listado (viejo), IVA corregido
--     e IVA guardado en el detalle (lo que muestra el documento). Deben coincidir las dos últimas.
-- SELECT v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial AS numero,
--        v.total_sin_impuestos AS subtotal_neto, v.total_descuento AS descuento,
--        v.importe_total AS total,
--        v.importe_total - v.total_sin_impuestos + v.total_descuento - COALESCE(v.total_ice,0) - COALESCE(v.propina,0) AS iva_listado_viejo,
--        v.importe_total - v.total_sin_impuestos - COALESCE(v.total_ice,0) - COALESCE(v.propina,0) AS iva_listado_nuevo,
--        (SELECT COALESCE(SUM(i.valor),0) FROM ventas_detalle d
--           JOIN ventas_detalle_impuestos i ON i.id_venta_detalle = d.id
--          WHERE d.id_venta = v.id AND i.codigo_impuesto = '2') AS iva_detalle
-- FROM ventas_cabecera v
-- JOIN empresas e ON e.id = v.id_empresa
-- WHERE e.ruc = '1792708389001' AND v.eliminado = false
--   AND v.secuencial::numeric IN (30677, 29348);

-- =============================================================================
-- REVERTIR (volver a la expresión anterior)
-- =============================================================================
-- DROP INDEX IF EXISTS idx_trgm_ventas_cabecera;
-- \i database/20260917b_ajuste_busqueda_facturas.sql   -- (con la expresión de esa fecha)

-- =============================================================================
-- VARIANTE SIN BLOQUEAR ESCRITURAS
-- =============================================================================
-- Enviando UNA sentencia a la vez (CONCURRENTLY no puede ir en un bloque):
--   CREATE INDEX CONCURRENTLY idx_trgm_ventas_cabecera_new ON ventas_cabecera USING gin (…misma expresión…);
--   DROP INDEX CONCURRENTLY IF EXISTS idx_trgm_ventas_cabecera;
--   ALTER INDEX idx_trgm_ventas_cabecera_new RENAME TO idx_trgm_ventas_cabecera;
