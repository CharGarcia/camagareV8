-- Investiga si DI-000003 y COM304061 (empresa 8) son en realidad el MISMO documento de
-- compra contabilizado DOS VECES (una por la migración, otra por el sistema en vivo), o si
-- son dos filas distintas de compras_cabecera que casualmente representan la misma factura
-- del proveedor (ingresada dos veces por error).
--
-- Ya sabemos (de la investigación anterior en esta sesión) que:
--   - id_asiento=7719, numero_comprobante='COM304061', modulo_origen='migracion'
--   - id_asiento=109,  numero_comprobante='DI-000003',  modulo_origen='compra'
--   Ambos con EXACTAMENTE los mismos montos: $178.57 subtotal + $26.79 IVA = $205.36 total.

-- 1) id_referencia_origen de cada asiento -> clave para saber si apuntan a la MISMA fila
--    de compras_cabecera o a dos filas distintas.
SELECT id, numero_comprobante, modulo_origen, id_referencia_origen, fecha_asiento, total_debe, created_at
FROM asientos_contables_cabecera
WHERE id_empresa = 8
  AND id IN (109, 7719);

-- 2) Datos de la(s) fila(s) de compras_cabecera referenciadas (puede ser 1 o 2 según el
--    resultado de arriba)
SELECT id, id_proveedor,
       establecimiento_prov || '-' || punto_emision_prov || '-' || secuencial_prov AS numero_factura_proveedor,
       fecha_emision, importe_total, id_asiento_contable,
       eliminado, created_at, created_by
FROM compras_cabecera
WHERE id_empresa = 8
  AND id IN (
      SELECT id_referencia_origen FROM asientos_contables_cabecera
      WHERE id_empresa = 8 AND id IN (109, 7719)
  );

-- 3) Si migracion_mysql_map tiene una fila para 'compras' con id_destino = la compra de
--    modulo_origen='compra' (asiento 109), eso confirmaría que esa fila SÍ vino de la
--    migración pero además generó un asiento "en vivo" por error (doble contabilización
--    del mismo registro migrado).
SELECT *
FROM migracion_mysql_map
WHERE id_empresa = 8
  AND entidad = 'compras'
  AND id_destino IN (
      SELECT id_referencia_origen FROM asientos_contables_cabecera
      WHERE id_empresa = 8 AND id IN (109, 7719)
  );
