-- ============================================================================
-- Reporte de Ventas por Vendedor: índices de soporte (modulos/reporte_ventas_vendedor)
-- ----------------------------------------------------------------------------
-- Contexto:
--   El reporte se estaba poniendo lento por dos motivos, y este archivo cubre
--   el segundo:
--
--   1) La consulta agregaba TODO el detalle de la empresa (todo el histórico)
--      antes de filtrar por período. Eso se corrigió en el código
--      (ReporteVentasVendedorRepository: la CTE "docs" filtra primero y el
--      resto de CTEs se unen contra ella).
--
--   2) Las tablas de DETALLE no tienen índice por su clave foránea. PostgreSQL
--      NO crea índices automáticamente para las FK: ventas_detalle solo estaba
--      indexada por id, id_producto_variante e id_pedido_detalle, y
--      ventas_detalle_impuestos únicamente por id. Con eso, cruzar el detalle
--      con las facturas del período obliga a recorrer la tabla entera de
--      detalle, sin importar si el reporte pide un mes o cinco años.
--
--   Los índices 1 y 2 no son solo de este reporte: los usa cualquier consulta
--   que baje al detalle de una factura (abrir una factura, el RIDE, el costeo,
--   los reportes de ventas). Son los de mayor impacto del archivo.
--
-- Qué hace:
--   Crea índices. NO modifica ni borra datos. NO toca ninguna fila.
--
-- Reversible: sí, sin pérdida de datos — DROP INDEX IF EXISTS <nombre>;
--
-- ----------------------------------------------------------------------------
-- CÓMO EJECUTARLO EN pgAdmin
-- ----------------------------------------------------------------------------
--   Abrir la base en pgAdmin → clic derecho → Query Tool → pegar TODO este
--   archivo → botón de ejecutar (F5). Se ejecuta de una sola vez, no hay que ir
--   sentencia por sentencia.
--
--   IMPORTANTE — hacerlo en un momento de baja actividad (fuera de horario de
--   facturación). Mientras se construye cada índice, esa tabla NO acepta
--   escrituras: quien esté guardando una factura en ese instante se queda
--   esperando hasta que termine. Las CONSULTAS siguen funcionando normal. En
--   tablas de este tamaño suele tardar segundos, no minutos.
--
--   El "IF NOT EXISTS" hace que sea seguro volver a ejecutarlo: si un índice ya
--   está creado, lo salta sin error.
--
--   (Al final del archivo está la variante CONCURRENTLY, que no bloquea
--   escrituras pero exige psql y ejecutar cada sentencia por separado.)
-- ============================================================================

-- 1) Detalle de la factura por su cabecera -----------------------------------
--    Lo usa toda consulta que baje al detalle: el reporte (bases e impuestos),
--    abrir una factura, el RIDE, el costeo.
CREATE INDEX IF NOT EXISTS idx_ventas_detalle_id_venta
    ON ventas_detalle (id_venta);

-- 2) Impuestos por línea de detalle ------------------------------------------
--    El desglose Base 0% / Base IVA / IVA del reporte sale de aquí.
CREATE INDEX IF NOT EXISTS idx_ventas_detalle_impuestos_id_detalle
    ON ventas_detalle_impuestos (id_venta_detalle);

-- 3) y 4) Lo mismo para notas de crédito -------------------------------------
--    El reporte consulta las NC con la misma estructura cuando el tipo de
--    documento es "Solo Notas de Crédito" o "Ventas Netas (Facturas − NC)".
CREATE INDEX IF NOT EXISTS idx_notas_credito_detalle_id_nc
    ON notas_credito_detalle (id_nota_credito);

CREATE INDEX IF NOT EXISTS idx_nc_detalle_impuestos_id_detalle
    ON notas_credito_detalle_impuestos (id_nota_credito_detalle);

-- 5) Filtro principal del reporte sobre la cabecera --------------------------
--    id_empresa + tipo_ambiente + rango de fechas, que es exactamente el WHERE
--    de la CTE "docs". Hoy existen índices de UNA sola columna (id_empresa por
--    un lado, fecha_emision por otro), así que el planificador termina
--    recorriendo el índice global de fechas —de todas las empresas— y
--    descartando fila por fila.
CREATE INDEX IF NOT EXISTS idx_ventas_cabecera_empresa_ambiente_fecha
    ON ventas_cabecera (id_empresa, tipo_ambiente, fecha_emision)
    WHERE eliminado = false;

-- 6) Nota de crédito por el documento que modifica ---------------------------
--    Cruce por número de comprobante, tanto para netear la venta como para
--    descontar la NC del saldo. nota_debito_cabecera ya tenía su equivalente;
--    notas_credito_cabecera no.
CREATE INDEX IF NOT EXISTS idx_notas_credito_num_doc_modificado
    ON notas_credito_cabecera (id_empresa, num_doc_modificado)
    WHERE eliminado = false;

-- 7) Cobros aplicados a una factura ------------------------------------------
--    Primer sumando del saldo pendiente. ingresos_detalle solo estaba indexada
--    por id_ingreso, no por el documento cobrado.
CREATE INDEX IF NOT EXISTS idx_ingresos_detalle_referencia
    ON ingresos_detalle (id_referencia_documento, tipo_documento);

-- 8) Retenciones enlazadas por número de comprobante -------------------------
--    La segunda vía de enlace de una retención de venta (la primera, id_venta
--    en la cabecera, ya tiene índice).
CREATE INDEX IF NOT EXISTS idx_retencion_venta_detalle_num_doc
    ON retencion_venta_detalle (num_doc_sustento);

-- 9) Refrescar estadísticas para que el planificador use los índices nuevos ---
ANALYZE ventas_detalle;
ANALYZE ventas_detalle_impuestos;
ANALYZE ventas_cabecera;
ANALYZE notas_credito_cabecera;
ANALYZE ingresos_detalle;
ANALYZE retencion_venta_detalle;

-- ============================================================================
-- COMPROBAR QUE QUEDARON CREADOS (pegar en el Query Tool después de ejecutar)
-- ----------------------------------------------------------------------------
-- SELECT tablename, indexname
-- FROM pg_indexes
-- WHERE indexname IN (
--     'idx_ventas_detalle_id_venta',
--     'idx_ventas_detalle_impuestos_id_detalle',
--     'idx_notas_credito_detalle_id_nc',
--     'idx_nc_detalle_impuestos_id_detalle',
--     'idx_ventas_cabecera_empresa_ambiente_fecha',
--     'idx_notas_credito_num_doc_modificado',
--     'idx_ingresos_detalle_referencia',
--     'idx_retencion_venta_detalle_num_doc'
-- )
-- ORDER BY tablename, indexname;
-- Deben salir 8 filas.
-- ============================================================================

-- ============================================================================
-- VARIANTE SIN BLOQUEAR ESCRITURAS (opcional)
-- ----------------------------------------------------------------------------
-- Solo hace falta si hay que crearlos en pleno horario de trabajo.
-- CREATE INDEX CONCURRENTLY no puede correr dentro de una transacción, y
-- pgAdmin envuelve en una transacción todo lo que se ejecuta de golpe: ahí
-- habría que seleccionar y ejecutar UNA sentencia a la vez. Es más cómodo por
-- psql, una por una:
--
--   psql "$DATABASE_URL" -c "CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_ventas_detalle_id_venta ON ventas_detalle (id_venta);"
--   psql "$DATABASE_URL" -c "CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_ventas_detalle_impuestos_id_detalle ON ventas_detalle_impuestos (id_venta_detalle);"
--   psql "$DATABASE_URL" -c "CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_notas_credito_detalle_id_nc ON notas_credito_detalle (id_nota_credito);"
--   psql "$DATABASE_URL" -c "CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_nc_detalle_impuestos_id_detalle ON notas_credito_detalle_impuestos (id_nota_credito_detalle);"
--   psql "$DATABASE_URL" -c "CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_ventas_cabecera_empresa_ambiente_fecha ON ventas_cabecera (id_empresa, tipo_ambiente, fecha_emision) WHERE eliminado = false;"
--   psql "$DATABASE_URL" -c "CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_notas_credito_num_doc_modificado ON notas_credito_cabecera (id_empresa, num_doc_modificado) WHERE eliminado = false;"
--   psql "$DATABASE_URL" -c "CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_ingresos_detalle_referencia ON ingresos_detalle (id_referencia_documento, tipo_documento);"
--   psql "$DATABASE_URL" -c "CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_retencion_venta_detalle_num_doc ON retencion_venta_detalle (num_doc_sustento);"
--
-- Si un CONCURRENTLY falla a medio camino deja el índice en estado inválido; se
-- limpia con DROP INDEX IF EXISTS <nombre>; y se vuelve a lanzar.
-- ============================================================================
