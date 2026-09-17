-- ============================================================================
-- DIAGNÓSTICO PREVIO AL DESPLIEGUE · «La facturación afecta al inventario»
-- ----------------------------------------------------------------------------
-- Qué cambia con la corrección del 16-09-2026:
--   Las opciones de Empresa → Facturación se guardan como texto 'true'/'false', y
--   InventarioService las evaluaba como si fueran booleanos de PHP, donde la cadena
--   'false' es verdadera. Resultado: con «La facturación afecta al inventario»
--   APAGADA, las facturas, recibos, órdenes de car-wash/taller/servicio externo
--   descontaban stock igual; y con «Obligatorio usar Lotes» apagado, los lotes se
--   trataban como obligatorios.
--
--   Tras desplegar la corrección, en los establecimientos con la opción apagada:
--     · las ventas DEJAN de descontar stock (y las notas de crédito de devolverlo);
--     · con lotes no obligatorios, la línea sin lote toma el lote que vence primero
--       y el stock se valida contra el total del producto, no contra el lote.
--
--   Una empresa que trabaja con inventario pero tiene la opción apagada (las
--   empresas nuevas se crean con 'false') notaría que sus ventas ya no descuentan
--   stock. Esta consulta las lista para decidir ANTES de subir el código.
--
-- Toca datos: NO (solo SELECT). El bloque del final, comentado, sí actualiza.
--
-- Cómo usarlo en pgAdmin: Query Tool → pegar TODO → F5 (muestra la consulta 1).
-- ============================================================================


-- ============================================================================
-- 1) ESTABLECIMIENTOS CON LA OPCIÓN APAGADA QUE HOY SÍ MUEVEN INVENTARIO
--    (movimientos de venta en los últimos 90 días). Si la empresa trabaja con
--    inventario —compras_90d > 0 es la señal más clara—, conviene activarle la
--    opción antes de desplegar (bloque 3). Si no, se deja apagada: la corrección
--    simplemente deja de generar movimientos que no quería.
--    Los establecimientos con la opción en NULL no cambian (ya no movían stock).
-- ============================================================================
WITH mov AS (
    SELECT d.id_establecimiento, 'factura' AS tipo, COUNT(DISTINCT k.referencia_id) AS documentos, MAX(k.fecha_movimiento) AS ultimo
      FROM inventario_kardex k
      JOIN ventas_cabecera d ON d.id = k.referencia_id AND d.id_empresa = k.id_empresa
     WHERE k.referencia_tipo = 'factura_venta' AND k.eliminado = false
       AND k.fecha_movimiento >= CURRENT_DATE - INTERVAL '90 days'
     GROUP BY d.id_establecimiento
    UNION ALL
    SELECT d.id_establecimiento, 'recibo', COUNT(DISTINCT k.referencia_id), MAX(k.fecha_movimiento)
      FROM inventario_kardex k
      JOIN recibos_venta_cabecera d ON d.id = k.referencia_id AND d.id_empresa = k.id_empresa
     WHERE k.referencia_tipo = 'recibo_venta' AND k.eliminado = false
       AND k.fecha_movimiento >= CURRENT_DATE - INTERVAL '90 days'
     GROUP BY d.id_establecimiento
    UNION ALL
    SELECT d.id_establecimiento, 'nota_credito', COUNT(DISTINCT k.referencia_id), MAX(k.fecha_movimiento)
      FROM inventario_kardex k
      JOIN notas_credito_cabecera d ON d.id = k.referencia_id AND d.id_empresa = k.id_empresa
     WHERE k.referencia_tipo = 'nota_credito' AND k.eliminado = false
       AND k.fecha_movimiento >= CURRENT_DATE - INTERVAL '90 days'
     GROUP BY d.id_establecimiento
),
otros AS (
    -- Órdenes de car-wash, taller y servicio externo (por empresa)
    SELECT id_empresa, COUNT(DISTINCT referencia_tipo || ':' || referencia_id) AS ordenes
      FROM inventario_kardex
     WHERE referencia_tipo IN ('carwash_orden', 'taller_orden', 'servicioexterno_orden') AND eliminado = false
       AND fecha_movimiento >= CURRENT_DATE - INTERVAL '90 days'
     GROUP BY id_empresa
),
compras AS (
    SELECT id_empresa, COUNT(*) AS movimientos
      FROM inventario_kardex
     WHERE referencia_tipo IN ('compra', 'compra_item', 'importacion') AND eliminado = false
       AND fecha_movimiento >= CURRENT_DATE - INTERVAL '90 days'
     GROUP BY id_empresa
)
SELECT e.id AS id_empresa, e.nombre AS empresa, e.ruc,
       ee.id AS id_establecimiento, ee.codigo, ee.nombre AS establecimiento,
       ee.facturacion_inventario, ee.obligatorio_lotes,
       COALESCE(SUM(m.documentos) FILTER (WHERE m.tipo = 'factura'), 0)      AS facturas_90d,
       COALESCE(SUM(m.documentos) FILTER (WHERE m.tipo = 'recibo'), 0)       AS recibos_90d,
       COALESCE(SUM(m.documentos) FILTER (WHERE m.tipo = 'nota_credito'), 0) AS notas_credito_90d,
       COALESCE(MAX(o.ordenes), 0)                                           AS ordenes_servicio_90d,
       COALESCE(MAX(c.movimientos), 0)                                       AS compras_90d,
       MAX(m.ultimo)                                                         AS ultimo_movimiento_venta
  FROM empresa_establecimiento ee
  JOIN empresas e ON e.id = ee.id_empresa
  LEFT JOIN mov m     ON m.id_establecimiento = ee.id
  LEFT JOIN otros o   ON o.id_empresa = ee.id_empresa
  LEFT JOIN compras c ON c.id_empresa = ee.id_empresa
 WHERE ee.eliminado = false
   AND ee.facturacion_inventario IS NOT NULL
   AND LOWER(TRIM(ee.facturacion_inventario)) NOT IN ('true', 't', '1')
 GROUP BY e.id, e.nombre, e.ruc, ee.id, ee.codigo, ee.nombre, ee.facturacion_inventario, ee.obligatorio_lotes
HAVING COALESCE(SUM(m.documentos), 0) > 0 OR COALESCE(MAX(o.ordenes), 0) > 0
 ORDER BY compras_90d DESC, facturas_90d DESC, e.nombre;


-- ============================================================================
-- 2) LOTES NO OBLIGATORIOS con inventario activo: aquí cambia qué lote queda en la
--    salida (el que vence primero, en vez de ninguno) y cómo se valida el stock
--    (total del producto). lineas_con_lote_90d > 0 indica que ya venden con lotes.
--    Informativo: no requiere acción salvo que la empresa quiera exigir el lote.
-- ============================================================================
-- SELECT e.id AS id_empresa, e.nombre AS empresa, ee.id AS id_establecimiento, ee.nombre AS establecimiento,
--        ee.obligatorio_lotes,
--        COUNT(k.id) FILTER (WHERE k.numero_lote IS NOT NULL AND k.numero_lote <> '') AS lineas_con_lote_90d,
--        COUNT(k.id) AS lineas_venta_90d
--   FROM empresa_establecimiento ee
--   JOIN empresas e ON e.id = ee.id_empresa
--   JOIN ventas_cabecera v ON v.id_establecimiento = ee.id AND v.id_empresa = ee.id_empresa
--   JOIN inventario_kardex k ON k.referencia_tipo = 'factura_venta' AND k.referencia_id = v.id
--                           AND k.id_empresa = v.id_empresa AND k.eliminado = false
--                           AND k.fecha_movimiento >= CURRENT_DATE - INTERVAL '90 days'
--  WHERE ee.eliminado = false
--    AND LOWER(TRIM(COALESCE(ee.facturacion_inventario, ''))) IN ('true', 't', '1')
--    AND ee.obligatorio_lotes IS NOT NULL
--    AND LOWER(TRIM(ee.obligatorio_lotes)) NOT IN ('true', 't', '1')
--  GROUP BY e.id, e.nombre, ee.id, ee.nombre, ee.obligatorio_lotes
--  ORDER BY lineas_con_lote_90d DESC;


-- ============================================================================
-- 3) OPCIONAL · Activar la opción en los establecimientos que SÍ trabajan con
--    inventario, para que al desplegar sigan descontando stock como hasta hoy.
--    Poner los id_establecimiento elegidos de la consulta 1. Toca datos.
--    Reversible: volver a poner 'false' en esos mismos ids.
-- ============================================================================
-- UPDATE empresa_establecimiento
--    SET facturacion_inventario = 'true', updated_at = CURRENT_TIMESTAMP
--  WHERE id IN (/* id_establecimiento, id_establecimiento, ... */)
--    AND LOWER(TRIM(COALESCE(facturacion_inventario, ''))) NOT IN ('true', 't', '1');
--
-- Comprobación (deben salir con 'true'):
-- SELECT id, id_empresa, nombre, facturacion_inventario FROM empresa_establecimiento WHERE id IN (/* mismos ids */);
