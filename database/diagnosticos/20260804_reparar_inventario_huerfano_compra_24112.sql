-- ============================================================================
-- Reparación puntual: compra 24112 (001-001-000000272, id_empresa=33)
-- ----------------------------------------------------------------------------
-- Diagnóstico confirmado:
--   - compras_detalle 88249/88250 (líneas VIVAS actuales) quedaron SIN
--     id_producto: el sistema, con el vínculo a inventario ya roto desde antes
--     del fix, no sabía que ya tenían inventario procesado y dejó quitar la
--     vinculación.
--   - inventario_kardex 3299 (48, producto 13206) y 3300 (2, producto 13214)
--     son los movimientos REALES ya registrados en stock, pero con
--     referencia_id apuntando a los ids viejos 88166/88167 (ya no existen).
--   - La descripción de cada línea coincide 1 a 1 con el producto de su
--     movimiento de kardex (mismo texto), así que el cruce es inequívoco:
--       88249 "...40 GR ESTERIL AZUL"        ↔ producto 13206 (kardex 3299)
--       88250 "...75 GR NEGRO, ESTERIL"      ↔ producto 13214 (kardex 3300)
--   - Las cantidades coinciden exactamente (48.0000 y 2.0000), no hay
--     descuadre de saldo pendiente.
-- ============================================================================

BEGIN;

-- 1) Restaurar la vinculación de producto en las líneas actuales
UPDATE compras_detalle SET id_producto = 13206 WHERE id = 88249;
UPDATE compras_detalle SET id_producto = 13214 WHERE id = 88250;

-- 2) Reconectar los movimientos de inventario a la línea VIVA correcta
UPDATE inventario_kardex SET referencia_id = 88249, updated_at = NOW() WHERE id = 3299;
UPDATE inventario_kardex SET referencia_id = 88250, updated_at = NOW() WHERE id = 3300;

-- 3) Verificación — debe mostrar los productos ya vinculados y las
-- referencias ya apuntando a 88249/88250:
SELECT id, id_producto FROM compras_detalle WHERE id_compra = 24112;
SELECT id, referencia_id, id_producto, cantidad FROM inventario_kardex WHERE id IN (3299, 3300);

-- Si todo se ve bien: COMMIT;
-- Si algo no cuadra: ROLLBACK;
