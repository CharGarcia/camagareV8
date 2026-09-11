-- =============================================================================
-- Índices de apoyo para el IVA por línea de la pestaña "Transacciones"
-- (fichas de Cliente y Proveedor).
--
-- QUÉ HACE: crea el índice que faltaba por la columna de detalle en las dos
--   tablas de impuestos que no lo tenían. La pestaña lee el IVA de cada línea
--   con `... WHERE id_detalle = <línea> AND codigo_impuesto = '2'`; sin índice,
--   PostgreSQL recorre la tabla entera por cada línea mostrada.
--   Las otras tres (ventas, compras y notas de crédito) ya lo tienen:
--   idx_ventas_detalle_impuestos_id_detalle, idx_compras_impuestos_detalle y
--   idx_nc_detalle_impuestos_id_detalle.
--   También acelera las consultas de esos mismos detalles en Liquidaciones de
--   Compra, Recibos de Venta, el ATS y la declaración de IVA.
--
-- ¿TOCA DATOS?  No: solo crea índices, no modifica ninguna fila.
-- ¿REVERSIBLE?  Sí, con los DROP INDEX del final (comentados).
-- CUÁNDO:       CREATE INDEX bloquea las ESCRITURAS de esa tabla mientras se
--               crea (las lecturas siguen). Son tablas pequeñas, pero conviene
--               ejecutarlo en horario de baja actividad.
-- CÓMO:         pegar todo en el Query Tool de pgAdmin y ejecutar (F5).
--               Se puede reejecutar sin error (IF NOT EXISTS).
-- =============================================================================

CREATE INDEX IF NOT EXISTS idx_liquidaciones_detalle_impuestos_detalle
    ON public.liquidaciones_detalle_impuestos (id_detalle);

CREATE INDEX IF NOT EXISTS idx_recibos_venta_detalle_impuestos_detalle
    ON public.recibos_venta_detalle_impuestos (id_recibo_detalle);

-- Comprobación (debe devolver 2 filas):
-- SELECT indexname
--   FROM pg_indexes
--  WHERE schemaname = 'public'
--    AND indexname IN ('idx_liquidaciones_detalle_impuestos_detalle',
--                      'idx_recibos_venta_detalle_impuestos_detalle');

-- Para revertir:
-- DROP INDEX IF EXISTS public.idx_liquidaciones_detalle_impuestos_detalle;
-- DROP INDEX IF EXISTS public.idx_recibos_venta_detalle_impuestos_detalle;
