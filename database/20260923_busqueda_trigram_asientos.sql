-- =============================================================================
-- 20260923_busqueda_trigram_asientos.sql
-- Motor de búsqueda con índices trigram — ASIENTOS CONTABLES (listado)
-- -----------------------------------------------------------------------------
-- Qué hace  : crea los dos índices GIN que usa el buscador del listado de
--             /modulos/asientos_contables. Las expresiones salen de
--             AsientoContableRepository::sqlIndicesBusqueda(), que es la misma
--             declaración que usa la consulta.
--             Repite la extensión y la función con IF NOT EXISTS / OR REPLACE:
--             da igual si ya se corrieron los archivos 20260917_busqueda_trigram_*.
--
-- Por qué    : el texto libre del listado se evaluaba fila por fila con unaccent()
--             (no indexable). Peor aún, las referencias de las líneas iban en un
--             EXISTS que PostgreSQL convierte en un recorrido de TODA la tabla
--             asientos_contables_detalle, de todas las empresas, quitándole las
--             tildes a cada línea, una vez por palabra buscada.
--
-- Toca datos: NO. Solo crea extensión, función e índices.
-- Reversible: sí (bloque comentado del final).
-- Cuándo     : en horario de baja actividad — CREATE INDEX bloquea las escrituras de
--             cada tabla mientras se construye (las lecturas no). Medido en local con
--             300.000 asientos y 1.200.000 líneas: ~23 s los dos.
-- Cómo       : pgAdmin → Query Tool sobre producción → pegar todo → F5.
-- Orden de despliegue: indistinto respecto del código (sin los índices la búsqueda
-- devuelve lo mismo, solo que sin acelerar).
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

-- 2. Asiento: comprobante, concepto, observaciones, fecha (Y-m-d y d-m-Y) y total
CREATE INDEX IF NOT EXISTS idx_trgm_asientos_cabecera ON asientos_contables_cabecera USING gin (f_unaccent(COALESCE(numero_comprobante, '') || ' ' || COALESCE(concepto, '') || ' ' || COALESCE(observaciones, '') || ' ' || COALESCE(lpad(extract(year FROM fecha_asiento)::int::text, 4, '0') || '-' || lpad(extract(month FROM fecha_asiento)::int::text, 2, '0') || '-' || lpad(extract(day FROM fecha_asiento)::int::text, 2, '0'), '') || ' ' || COALESCE(lpad(extract(day FROM fecha_asiento)::int::text, 2, '0') || '-' || lpad(extract(month FROM fecha_asiento)::int::text, 2, '0') || '-' || lpad(extract(year FROM fecha_asiento)::int::text, 4, '0'), '') || ' ' || COALESCE(total_debe::text, '')) gin_trgm_ops);

-- 3. Líneas: documento y referencia (Egreso 001-..., Factura ...)
CREATE INDEX IF NOT EXISTS idx_trgm_asientos_det_ref ON asientos_contables_detalle USING gin (f_unaccent(COALESCE(documento_referencia, '') || ' ' || COALESCE(referencia_detalle, '')) gin_trgm_ops);

-- 4. Estadísticas ------------------------------------------------------------
ANALYZE asientos_contables_cabecera;
ANALYZE asientos_contables_detalle;

-- =============================================================================
-- COMPROBACIÓN (opcional) — deben salir 2 filas, ambas con "creado"
-- =============================================================================
-- SELECT i.indice,
--        CASE WHEN c.oid IS NULL THEN 'FALTA' ELSE 'creado' END AS estado,
--        COALESCE(pg_size_pretty(pg_relation_size(c.oid)), '-') AS tamano
-- FROM (VALUES ('idx_trgm_asientos_cabecera'), ('idx_trgm_asientos_det_ref')) AS i(indice)
-- LEFT JOIN pg_class c ON c.relname = i.indice AND c.relkind = 'i'
-- ORDER BY 1;

-- =============================================================================
-- REVERTIR
-- =============================================================================
-- DROP INDEX IF EXISTS idx_trgm_asientos_cabecera;
-- DROP INDEX IF EXISTS idx_trgm_asientos_det_ref;
