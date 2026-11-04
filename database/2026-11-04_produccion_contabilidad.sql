-- ============================================================================
-- Producción — cuentas contables en Configuración Contable
--
-- Qué hace:   agrega a asientos_tipo los 6 conceptos del tipo de asiento «Producción»
--             (tipo_asiento = 'produccion'). Con eso aparece la sección «Producción» en
--             Configuración Contable, SOLO para las empresas que usan el módulo (las demás
--             no la ven: AsientoProgramadoService::tiposAsientoSinUso()).
--             Además copia, como reglas generales, las cuentas que una empresa ya hubiera
--             guardado en la Configuración de Producción (solo existía en la BD local).
-- Toca datos: solo INSERT en asientos_tipo (catálogo global) y en asientos_programados
--             (únicamente para empresas con produccion_configuracion). Ningún UPDATE ni
--             DELETE sobre filas existentes.
-- Requiere:   database/2026-11-03_produccion.sql aplicado antes.
-- Idempotente: sí.
-- Reversible: sí (solo si no se generaron asientos con estas cuentas):
--   DELETE FROM asientos_programados WHERE id_asiento_tipo IN (SELECT id FROM asientos_tipo WHERE tipo_asiento = 'produccion');
--   DELETE FROM asientos_tipo WHERE tipo_asiento = 'produccion';
-- Ejecutar en pgAdmin (Query Tool) de una sola vez (F5).
-- ============================================================================

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, tipo_cuenta, debe_haber)
SELECT 'produccion', 'Inventario de materia prima',
       'Sale con cada consumo de insumos de una orden de producción. Si el insumo tiene su propia cuenta de inventario en la ficha del producto, se usa esa.',
       'PROD_MATERIA_PRIMA', 'activo', 'haber'
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = 'PROD_MATERIA_PRIMA');

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, tipo_cuenta, debe_haber)
SELECT 'produccion', 'Productos en proceso',
       'Acumula el costo de los insumos consumidos mientras la orden está abierta; se salda al cerrarla.',
       'PROD_EN_PROCESO', 'activo', 'debe'
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = 'PROD_EN_PROCESO');

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, tipo_cuenta, debe_haber)
SELECT 'produccion', 'Inventario de producto terminado',
       'Entra al cerrar la orden, con el costo real de lo producido (principal, coproductos y subproductos). Si el producto tiene su propia cuenta de inventario en su ficha, se usa esa.',
       'PROD_TERMINADO', 'activo', 'debe'
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = 'PROD_TERMINADO');

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, tipo_cuenta, debe_haber)
SELECT 'produccion', 'Mano de obra aplicada',
       'Contrapartida de la mano de obra que se suma al costo de la orden. Solo es obligatoria si las fórmulas tienen mano de obra.',
       'PROD_MANO_OBRA', NULL, 'haber'
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = 'PROD_MANO_OBRA');

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, tipo_cuenta, debe_haber)
SELECT 'produccion', 'Costos indirectos aplicados',
       'Contrapartida de los costos indirectos y de máquina que se suman al costo de la orden. Solo es obligatoria si las fórmulas los tienen.',
       'PROD_CIF', NULL, 'haber'
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = 'PROD_CIF');

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, tipo_cuenta, debe_haber)
SELECT 'produccion', 'Merma anormal',
       'Gasto por la merma anormal registrada al cerrar una orden. Solo es obligatoria si se registra merma anormal.',
       'PROD_MERMA', 'gasto', 'debe'
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = 'PROD_MERMA');

-- Cuentas ya guardadas en la Configuración de Producción → reglas generales (si no existe ya una).
INSERT INTO asientos_programados (id_empresa, id_usuario, id_asiento_tipo, id_cuenta, tipo_referencia, id_referencia, created_by, updated_by)
SELECT c.id_empresa, COALESCE(c.updated_by, c.created_by), at.id, v.id_cuenta, 'asientos tipo', at.id, c.updated_by, c.updated_by
  FROM produccion_configuracion c
 CROSS JOIN LATERAL (VALUES
        ('PROD_MATERIA_PRIMA', c.id_cuenta_materia_prima),
        ('PROD_EN_PROCESO',    c.id_cuenta_en_proceso),
        ('PROD_TERMINADO',     c.id_cuenta_terminado),
        ('PROD_MANO_OBRA',     c.id_cuenta_mano_obra),
        ('PROD_CIF',           c.id_cuenta_cif),
        ('PROD_MERMA',         c.id_cuenta_merma)) AS v(codigo, id_cuenta)
  JOIN asientos_tipo at ON at.codigo = v.codigo AND at.eliminado = false
 WHERE c.eliminado = false AND v.id_cuenta IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM asientos_programados ap
                    WHERE ap.id_empresa = c.id_empresa AND ap.id_asiento_tipo = at.id
                      AND ap.id_referencia = at.id AND ap.eliminado = false);

-- ── Comprobación (deben salir 6 filas) ───────────────────────────────────────
-- SELECT codigo, referencia, debe_haber FROM asientos_tipo WHERE tipo_asiento = 'produccion' ORDER BY codigo;
