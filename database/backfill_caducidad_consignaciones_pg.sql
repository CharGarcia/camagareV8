-- ============================================================================
-- Backfill de fecha_caducidad en el DETALLE de consignaciones de venta migradas.
-- 100% PostgreSQL (correr desde pgAdmin). Todas las empresas.
--
-- Recupera la caducidad desde el KARDEX (inventario_kardex): el movimiento de
-- salida por consignación quedó ENLAZADO a su consignación (referencia_tipo =
-- 'CONSIGNACION_VENTA', referencia_id = id de la consignación) y trae fecha_caducidad.
-- Así se casa por (consignación + producto) SIN depender del lote —que es lo que
-- fallaba cuando el lote nuevo no coincidía con el viejo.
--
-- REQUISITO: el Inventario debe estar migrado CON el enlace de consignación
-- (referencia_id poblado). Si migraste Inventario antes de ese fix, re-migra
-- Inventario primero (el reconcile enlaza), o usa el script PHP
-- database/backfill_caducidad_consignaciones.php (lee el dato del sistema viejo).
--
-- IDEMPOTENTE y no destructivo: solo rellena filas con fecha_caducidad IS NULL.
-- ============================================================================

-- 1) Desde el enlace kardex→consignación (por producto). Lote-independiente.
--    MAX() cubre el caso raro de un mismo producto con varios lotes en la consignación.
UPDATE consignaciones_ventas_detalles d
   SET fecha_caducidad = sub.fc, updated_at = now()
  FROM (
        SELECT k.referencia_id AS id_consignacion, k.id_producto, MAX(k.fecha_caducidad) AS fc
          FROM inventario_kardex k
         WHERE k.referencia_tipo = 'CONSIGNACION_VENTA'
           AND k.referencia_id IS NOT NULL
           AND k.fecha_caducidad IS NOT NULL
         GROUP BY k.referencia_id, k.id_producto
       ) sub
 WHERE d.id_consignacion = sub.id_consignacion
   AND d.id_producto     = sub.id_producto
   AND d.fecha_caducidad IS NULL
   AND d.eliminado = false;

-- 2) Respaldo por LOTE (para líneas sin enlace kardex pero cuyo lote sí coincide).
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
   AND k.id_empresa = d.id_empresa AND k.id_producto = d.id_producto AND k.numero_lote = d.lote;

-- 3) OPCIONAL (regla del cero): las que quedaron sin match → fecha de emisión de la
--    consignación. Solo detalles de consignaciones INSERTADAS por la migración.
--    Comenta este bloque si prefieres dejarlas en NULL.
UPDATE consignaciones_ventas_detalles d
   SET fecha_caducidad = cv.fecha_emision, updated_at = now()
  FROM consignaciones_ventas cv
 WHERE d.id_consignacion = cv.id
   AND d.fecha_caducidad IS NULL
   AND d.eliminado = false
   AND d.id_consignacion IN (
        SELECT id_destino FROM migracion_mysql_map
         WHERE entidad = 'consignaciones' AND vinculado IS NOT TRUE);

-- Verificación:
-- SELECT COUNT(*) FROM consignaciones_ventas_detalles WHERE fecha_caducidad IS NULL AND eliminado = false;
