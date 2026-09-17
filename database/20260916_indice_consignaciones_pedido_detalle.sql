-- ============================================================================
-- Consignaciones de Venta ↔ Pedidos: índice por línea de pedido
-- (consignaciones_ventas_detalles.id_pedido_detalle)
-- ----------------------------------------------------------------------------
-- Contexto:
--   Guardar, editar o eliminar una consignación recalcula el estado (Pendiente /
--   Procesado) de los pedidos cuyas líneas consume. Para eso suma lo consignado
--   por cada línea de pedido, buscando en consignaciones_ventas_detalles por
--   id_pedido_detalle — columna que NO tenía índice, así que cada búsqueda
--   recorría la tabla de detalles completa (de todas las empresas).
--
--   La causa principal de la lentitud ya se corrigió en el código
--   (ConsignacionVentaService::reconciliarPedidosAfectados): antes recorría TODOS
--   los pedidos de la empresa en cada guardado —medido en local con 3.000 pedidos
--   enlazados: 140 s por guardado; ahora 0,4 s—. Este índice es el complemento:
--   cuando la consignación sí trae líneas de pedido, la suma deja de depender del
--   tamaño de la tabla (medido en local: 7,8 ms sin índice → 0,17 ms con índice,
--   empresa con 20.000 líneas de consignación). El código funciona igual con o
--   sin este índice: el orden de despliegue no importa.
--
-- Qué hace:
--   Crea UN índice parcial (solo las líneas que vienen de un pedido, que suelen ser
--   una fracción de la tabla). NO modifica ni borra datos.
--
-- Reversible: sí, sin pérdida de datos —
--   DROP INDEX IF EXISTS idx_cons_ventas_det_pedido_detalle;
--
-- Mismo patrón que idx_ventas_detalle_pedido_detalle
-- (database/agregar_id_pedido_detalle_ventas.sql).
--
-- ----------------------------------------------------------------------------
-- CÓMO EJECUTARLO EN pgAdmin
-- ----------------------------------------------------------------------------
--   1) Ejecutar primero el PASO 1 (solo lectura) y mirar el resultado.
--   2) Ejecutar el PASO 2 (se puede pegar todo y F5).
--
--   Hacerlo en un momento de baja actividad: mientras se construye el índice, la
--   tabla de detalles de consignaciones NO acepta escrituras (quien guarde una
--   consignación en ese instante espera a que termine). Las consultas siguen
--   funcionando normal. Con un solo índice parcial suele tardar segundos.
--
--   El "IF NOT EXISTS" hace que sea seguro volver a ejecutarlo.
--
--   (Al final está la variante CONCURRENTLY, que no bloquea escrituras.)
-- ============================================================================


-- ============================================================================
-- PASO 1 · DIAGNÓSTICO PREVIO (solo lectura)
-- ----------------------------------------------------------------------------
-- IF NOT EXISTS compara solo el NOMBRE. Si aquí aparece un índice cuya primera
-- columna ya es id_pedido_detalle (con otro nombre), NO ejecutar el PASO 2.
-- Lo esperado (medido en local el 16-09-2026): ningún índice por esa columna.
-- ============================================================================
SELECT t.relname                                      AS tabla,
       c.relname                                      AS indice,
       a.attname                                      AS primera_columna,
       i.indisvalid                                   AS valido,
       pg_size_pretty(pg_relation_size(i.indexrelid)) AS tamano,
       pg_get_indexdef(i.indexrelid)                  AS definicion
FROM pg_index i
JOIN pg_class     c ON c.oid = i.indexrelid
JOIN pg_class     t ON t.oid = i.indrelid
JOIN pg_namespace n ON n.oid = t.relnamespace
JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = i.indkey[0]
WHERE n.nspname = 'public'
  AND t.relname = 'consignaciones_ventas_detalles'
ORDER BY c.relname;


-- ============================================================================
-- PASO 2 · CREAR EL ÍNDICE
-- ============================================================================
CREATE INDEX IF NOT EXISTS idx_cons_ventas_det_pedido_detalle
    ON consignaciones_ventas_detalles (id_pedido_detalle)
    WHERE id_pedido_detalle IS NOT NULL;

ANALYZE consignaciones_ventas_detalles;


-- ============================================================================
-- COMPROBAR QUE QUEDÓ CREADO (pegar en el Query Tool después de ejecutar)
-- ----------------------------------------------------------------------------
-- SELECT c.relname AS indice, i.indisvalid AS valido
-- FROM pg_index i
-- JOIN pg_class c ON c.oid = i.indexrelid
-- WHERE c.relname = 'idx_cons_ventas_det_pedido_detalle';
-- Debe salir 1 fila con valido = true.
-- ============================================================================

-- ============================================================================
-- VARIANTE SIN BLOQUEAR ESCRITURAS (opcional)
-- ----------------------------------------------------------------------------
-- Solo hace falta si hay que crearlo en pleno horario de trabajo.
-- CREATE INDEX CONCURRENTLY no puede correr dentro de una transacción, y
-- PostgreSQL ejecuta en una transacción implícita todo lo que se envía de golpe
-- (aunque sea desde pgAdmin con Auto commit). Seleccionar SOLO esta sentencia
-- (sin el comentario) y ejecutarla sola:
--
--   CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_cons_ventas_det_pedido_detalle ON consignaciones_ventas_detalles (id_pedido_detalle) WHERE id_pedido_detalle IS NOT NULL;
--
-- Si un CONCURRENTLY falla a medio camino deja el índice en estado inválido
-- (valido = false en la comprobación); se limpia con
--   DROP INDEX IF EXISTS idx_cons_ventas_det_pedido_detalle;
-- y se vuelve a lanzar.
-- ============================================================================
