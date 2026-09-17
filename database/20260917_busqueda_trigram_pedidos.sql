-- =============================================================================
-- 20260917_busqueda_trigram_pedidos.sql
-- Motor de búsqueda con índices trigram — primera entrega: PEDIDOS
-- -----------------------------------------------------------------------------
-- Qué hace  : instala la extensión pg_trgm, crea la función f_unaccent(text)
--             (versión INMUTABLE de unaccent, obligatoria para poder indexar) y
--             los índices GIN que usa el buscador del listado de Pedidos.
--             Las expresiones de estos índices las genera el propio repositorio
--             (PedidoRepository::sqlIndicesBusqueda()): son EXACTAMENTE las que
--             usa la consulta, así que no se pueden desalinear.
--
-- Por qué    : hasta ahora, cada búsqueda con texto recorría todos los pedidos de
--             la empresa, y además lo hacía dos veces (un COUNT y un SELECT con el
--             mismo WHERE). Medido en producción el 17-09-2026: 4,3 s cada consulta
--             (8,7 s por búsqueda) leyendo ~21 GB. Reproducido en local con 20.000
--             pedidos: 6,7 a 8,6 s por búsqueda. Con el código nuevo + estos
--             índices, los mismos casos quedan en 0,04 a 0,13 s (50 a 200 veces
--             más rápido), devolviendo exactamente las mismas filas.
--
-- Toca datos: NO. Solo crea extensión, función e índices. No modifica ni borra filas.
-- Reversible: sí, sin pérdida de datos (ver el bloque comentado del final).
--
-- Cuándo     : en horario de baja actividad. CREATE INDEX bloquea las ESCRITURAS de
--             cada tabla mientras se construye (las lecturas siguen normales).
--             Medido en local con volúmenes parecidos a los de producción:
--             pedidos_cabecera 2,4 s · clientes 0,2 s · productos 0,2 s ·
--             consignaciones_ventas 1,9 s · ventas_cabecera 0,8 s. Espacio total
--             estimado: ~20 MB.
--             Si prefiere no bloquear escrituras ni un segundo, al final está la
--             variante CONCURRENTLY (hay que ejecutarla sentencia por sentencia).
--
-- Cómo       : pgAdmin → Query Tool sobre la base de producción → pegar todo → F5.
--             Es idempotente: se puede volver a ejecutar sin error.
--
-- Orden de despliegue: indistinto. El código nuevo detecta si f_unaccent existe;
--             mientras no exista busca como antes (mismo resultado, sin índice).
-- =============================================================================

-- 1. Extensiones -------------------------------------------------------------
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE EXTENSION IF NOT EXISTS unaccent;

-- 2. Versión INMUTABLE de unaccent -------------------------------------------
--    unaccent() es STABLE (depende del diccionario), y PostgreSQL no admite
--    funciones STABLE en un índice. Este envoltorio fija el diccionario
--    ('public.unaccent') y se declara IMMUTABLE, que es el patrón estándar.
CREATE OR REPLACE FUNCTION public.f_unaccent(text)
RETURNS text
LANGUAGE sql
IMMUTABLE
PARALLEL SAFE
STRICT
AS $$ SELECT public.unaccent('public.unaccent'::regdictionary, $1) $$;

-- 3. Índices del buscador ----------------------------------------------------
--    (generados por PedidoRepository::sqlIndicesBusqueda(); no editar a mano)

-- Pedido: número, observaciones, observaciones internas, fecha de emisión,
-- fecha de entrega y rango horario.
CREATE INDEX IF NOT EXISTS idx_trgm_pedidos_cabecera ON pedidos_cabecera USING gin (f_unaccent(COALESCE(establecimiento, '') || '-' || COALESCE(punto_emision, '') || '-' || COALESCE(secuencial, '') || ' ' || COALESCE(observaciones, '') || ' ' || COALESCE(observaciones_internas, '') || ' ' || COALESCE(lpad(extract(year FROM fecha_pedido)::int::text, 4, '0') || '-' || lpad(extract(month FROM fecha_pedido)::int::text, 2, '0') || '-' || lpad(extract(day FROM fecha_pedido)::int::text, 2, '0'), '') || ' ' || COALESCE(lpad(extract(year FROM fecha_entrega)::int::text, 4, '0') || '-' || lpad(extract(month FROM fecha_entrega)::int::text, 2, '0') || '-' || lpad(extract(day FROM fecha_entrega)::int::text, 2, '0'), '') || ' ' || COALESCE(lpad(extract(hour FROM hora_inicial_entrega)::int::text, 2, '0') || ':' || lpad(extract(minute FROM hora_inicial_entrega)::int::text, 2, '0'), '') || ' - ' || COALESCE(lpad(extract(hour FROM hora_maxima_entrega)::int::text, 2, '0') || ':' || lpad(extract(minute FROM hora_maxima_entrega)::int::text, 2, '0'), '')) gin_trgm_ops);

-- Cliente: nombre e identificación. Lo comparten TODOS los módulos que buscan por
-- cliente (facturas, proformas, consignaciones, cobros…), así que este índice se
-- crea una sola vez y sirve para los siguientes módulos que se migren.
CREATE INDEX IF NOT EXISTS idx_trgm_clientes ON clientes USING gin (f_unaccent(COALESCE(nombre, '') || ' ' || COALESCE(identificacion, '')) gin_trgm_ops);

-- Producto: código y nombre. Igual que el anterior: lo usará también el buscador
-- de productos del modal de factura, del POS y el listado de Productos.
CREATE INDEX IF NOT EXISTS idx_trgm_productos ON productos USING gin (f_unaccent(COALESCE(codigo, '') || ' ' || COALESCE(nombre, '')) gin_trgm_ops);

-- Número de la consignación de venta y de la factura que consumieron el pedido.
CREATE INDEX IF NOT EXISTS idx_trgm_consignaciones_numero ON consignaciones_ventas USING gin (f_unaccent(COALESCE(establecimiento, '') || '-' || COALESCE(punto_emision, '') || '-' || COALESCE(secuencial, '')) gin_trgm_ops);
CREATE INDEX IF NOT EXISTS idx_trgm_ventas_numero ON ventas_cabecera USING gin (f_unaccent(COALESCE(establecimiento, '') || '-' || COALESCE(punto_emision, '') || '-' || COALESCE(secuencial, '')) gin_trgm_ops);

-- 4. Índice de apoyo del enlace pedido ↔ consignación ------------------------
--    Ya venía en database/20260916_indice_consignaciones_pedido_detalle.sql; se
--    repite aquí por si ese archivo no llegó a ejecutarse. Sin él, una búsqueda en
--    Pedidos recorre la tabla de líneas de consignación entera por cada pedido:
--    en la prueba local (192.000 líneas) una sola búsqueda pasó de 8 s a más de
--    13 minutos. IF NOT EXISTS lo hace inofensivo si ya está.
CREATE INDEX IF NOT EXISTS idx_cons_ventas_det_pedido_detalle ON consignaciones_ventas_detalles (id_pedido_detalle) WHERE id_pedido_detalle IS NOT NULL;

-- 5. Estadísticas ------------------------------------------------------------
ANALYZE pedidos_cabecera;
ANALYZE clientes;
ANALYZE productos;
ANALYZE consignaciones_ventas;
ANALYZE ventas_cabecera;
ANALYZE consignaciones_ventas_detalles;

-- =============================================================================
-- COMPROBACIÓN (opcional) — deben salir 6 filas, todas con "creado"
-- =============================================================================
-- SELECT i.indice,
--        CASE WHEN c.oid IS NULL THEN 'FALTA' ELSE 'creado' END AS estado,
--        COALESCE(pg_size_pretty(pg_relation_size(c.oid)), '-') AS tamano
-- FROM (VALUES ('idx_trgm_pedidos_cabecera'), ('idx_trgm_clientes'), ('idx_trgm_productos'),
--              ('idx_trgm_consignaciones_numero'), ('idx_trgm_ventas_numero'),
--              ('idx_cons_ventas_det_pedido_detalle')) AS i(indice)
-- LEFT JOIN pg_class c ON c.relname = i.indice AND c.relkind = 'i'
-- ORDER BY 1;
--
-- Y que la función quedó: debe devolver 'Maria Garcia Perez'
-- SELECT f_unaccent('María García Pérez');

-- =============================================================================
-- REVERTIR (no hace falta para nada, pero por si acaso)
-- =============================================================================
-- DROP INDEX IF EXISTS idx_trgm_pedidos_cabecera;
-- DROP INDEX IF EXISTS idx_trgm_clientes;
-- DROP INDEX IF EXISTS idx_trgm_productos;
-- DROP INDEX IF EXISTS idx_trgm_consignaciones_numero;
-- DROP INDEX IF EXISTS idx_trgm_ventas_numero;
-- DROP FUNCTION IF EXISTS public.f_unaccent(text);
-- (la extensión pg_trgm puede quedarse; no molesta)

-- =============================================================================
-- VARIANTE SIN BLOQUEAR ESCRITURAS (opcional)
-- -----------------------------------------------------------------------------
-- CONCURRENTLY no puede ejecutarse dentro de una transacción, y pgAdmin envuelve
-- en una transacción todo lo que se manda de una vez. Para usarla hay que enviar
-- CADA sentencia por separado (seleccionarla y F5, una a una), después de haber
-- creado la extensión y la función con el bloque de arriba:
--
-- CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_trgm_pedidos_cabecera ON pedidos_cabecera USING gin (…);
-- CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_trgm_clientes ON clientes USING gin (…);
-- …
--
-- Si alguna queda en estado inválido (se interrumpió a media construcción):
-- SELECT c.relname FROM pg_class c JOIN pg_index i ON i.indexrelid = c.oid
--  WHERE NOT i.indisvalid AND c.relname LIKE 'idx_trgm_%';
-- → borrar la que salga con DROP INDEX y volver a crearla.
-- =============================================================================
