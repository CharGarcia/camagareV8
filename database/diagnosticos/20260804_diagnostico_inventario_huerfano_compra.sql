-- ============================================================================
-- Diagnóstico: movimientos de inventario "huérfanos" de una compra puntual
-- ----------------------------------------------------------------------------
-- Compra: 001-001-000000272, empresa RUC 0993408339001 (id_empresa = 33).
-- Causa: antes del fix de sincronizarDetalles(), cada guardado de una compra ya
-- existente borraba y volvía a insertar TODAS sus líneas (compras_detalle) con
-- ids NUEVOS. inventario_kardex.referencia_id apunta al id de la línea que
-- existía en el momento de procesar el inventario — si la compra se volvió a
-- guardar después, ese id quedó apuntando a una fila que ya no existe.
-- Solo LECTURA. No modifica nada.
-- ============================================================================

-- 1) Confirmar la compra y su id real
SELECT id, id_empresa, tipo_comprobante, establecimiento_prov, punto_emision_prov,
       secuencial_prov, importe_total, eliminado
FROM compras_cabecera
WHERE id_empresa = 33
  AND establecimiento_prov = '001'
  AND punto_emision_prov = '001'
  AND secuencial_prov = '000000272';

-- 2) Líneas VIVAS actuales de esa compra (usa el id que salió en el paso 1)
SELECT id, id_producto, descripcion, cantidad
FROM compras_detalle
WHERE id_compra = (
    SELECT id FROM compras_cabecera
    WHERE id_empresa = 33 AND establecimiento_prov = '001'
      AND punto_emision_prov = '001' AND secuencial_prov = '000000272'
);

-- 3) Movimientos de inventario que dicen pertenecer a esta compra (por el texto
-- de observaciones que graba procesarInventarioAjax), y si su referencia_id
-- sigue viva o ya quedó huérfana.
SELECT k.id AS id_kardex, k.id_producto, p.nombre AS producto_nombre, k.cantidad,
       k.referencia_id AS id_detalle_guardado,
       EXISTS (SELECT 1 FROM compras_detalle d WHERE d.id = k.referencia_id) AS referencia_viva,
       k.observaciones, k.created_at, k.eliminado
FROM inventario_kardex k
LEFT JOIN productos p ON p.id = k.id_producto
WHERE k.referencia_tipo = 'compra'
  AND k.observaciones LIKE '%001-001-000000272%'
ORDER BY k.id;
