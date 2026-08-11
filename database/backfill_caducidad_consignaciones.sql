-- ============================================================================
-- Backfill de fecha_caducidad en las CONSIGNACIONES DE VENTA migradas cuyo
-- detalle quedó sin caducidad (migradas antes del fix de vencimiento).
--
-- La fecha real de vencimiento vive en el MySQL viejo. Aquí se recupera SIN salir
-- de PostgreSQL, tomando la caducidad del MISMO LOTE del producto que ya está en el
-- kardex (inventario_kardex): el mismo lote físico tiene un único vencimiento.
--
-- Es IDEMPOTENTE y no destructivo: solo toca detalles con fecha_caducidad IS NULL,
-- de consignaciones INSERTADAS por la migración (migracion_mysql_map, no vinculadas).
-- No toca consignaciones nativas ni detalles que ya tengan caducidad.
--
-- Requisito: el Inventario (kardex) debe estar migrado (es de donde sale el lote).
-- ============================================================================

-- 1) Caducidad desde el kardex, casando por (empresa, producto, lote).
UPDATE consignaciones_ventas_detalles d
   SET fecha_caducidad = k.fc, updated_at = now()
  FROM (
        SELECT id_empresa, id_producto, numero_lote, MAX(fecha_caducidad) AS fc
          FROM inventario_kardex
         WHERE fecha_caducidad IS NOT NULL AND numero_lote IS NOT NULL AND numero_lote <> ''
         GROUP BY id_empresa, id_producto, numero_lote
       ) k
 WHERE d.fecha_caducidad IS NULL
   AND d.eliminado = false
   AND d.lote IS NOT NULL AND d.lote <> ''
   AND k.id_empresa = d.id_empresa AND k.id_producto = d.id_producto AND k.numero_lote = d.lote
   AND d.id_consignacion IN (
        SELECT id_destino FROM migracion_mysql_map
         WHERE entidad = 'consignaciones' AND vinculado IS NOT TRUE);

-- 2) OPCIONAL (regla del cero) — para los que quedaron sin lote o sin match en el
--    kardex: usar la fecha de emisión de la consignación (mismo criterio que la
--    migración). Comenta este bloque si prefieres dejarlos en NULL.
UPDATE consignaciones_ventas_detalles d
   SET fecha_caducidad = cv.fecha_emision, updated_at = now()
  FROM consignaciones_ventas cv
 WHERE d.id_consignacion = cv.id
   AND d.fecha_caducidad IS NULL
   AND d.eliminado = false
   AND d.id_consignacion IN (
        SELECT id_destino FROM migracion_mysql_map
         WHERE entidad = 'consignaciones' AND vinculado IS NOT TRUE);

-- Verificación: cuántos detalles de consignaciones migradas siguen sin caducidad.
-- SELECT COUNT(*) FROM consignaciones_ventas_detalles d
--  WHERE d.fecha_caducidad IS NULL AND d.eliminado = false
--    AND d.id_consignacion IN (SELECT id_destino FROM migracion_mysql_map WHERE entidad='consignaciones' AND vinculado IS NOT TRUE);
