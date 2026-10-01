-- =============================================================================
-- Diagnóstico (SOLO LECTURA): ¿por qué estas 4 facturas de ASAMED IMPLANT
-- (empresa 23, RUC 1792708389001) no tienen asiento contable propio?
--   001-101-000031034, 001-101-000030757, 001-101-000031117, 001-101-000028611
--
-- La sincronización solo contabiliza facturas en estado 'autorizado' o
-- 'contabilizado', que no sean migradas del sistema anterior y con el módulo
-- Facturas de Venta encendido en «Módulos que contabilizan». Las columnas de
-- abajo dicen cuál de esas condiciones falla en cada una:
--   estado                 -> debe ser autorizado / contabilizado
--   migrada                -> true = vino de la migración: no lleva asiento propio
--   modulo_contabiliza     -> false = Facturas de Venta apagado para la empresa
--   asientos_eliminados    -> tuvo asiento pero se anuló/eliminó
--   id_asiento_contable    -> enlace guardado en la factura (NULL = nunca se enlazó)
-- Si todo está bien (autorizado, no migrada, módulo encendido) y aun así no hay
-- asiento, el asiento FALLÓ al generarse: abrir Facturas de Venta (o sincronizar
-- desde Estados Financieros) muestra el motivo en el aviso de asientos pendientes.
-- =============================================================================

SELECT v.id,
       v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial AS factura,
       v.fecha_emision,
       v.importe_total,
       v.estado,
       v.tipo_ambiente,
       v.id_asiento_contable,
       EXISTS (SELECT 1 FROM migracion_mysql_map mm
                WHERE mm.entidad = 'facturas' AND mm.id_destino = v.id
                  AND mm.vinculado IS NOT TRUE)                                   AS migrada,
       NOT EXISTS (SELECT 1 FROM contabilidad_modulos_empresa cm
                    WHERE cm.id_empresa = v.id_empresa AND cm.modulo_clave = 'facturas_venta'
                      AND cm.eliminado = false AND cm.contabiliza = false)          AS modulo_contabiliza,
       (SELECT COUNT(*) FROM asientos_contables_cabecera a
         WHERE a.modulo_origen = 'factura_venta' AND a.id_referencia_origen = v.id
           AND a.id_empresa = v.id_empresa
           AND (a.eliminado = true OR a.estado = 'anulado'))                         AS asientos_eliminados,
       v.created_at,
       v.updated_at
  FROM ventas_cabecera v
  JOIN empresas emp ON emp.id = v.id_empresa
 WHERE emp.ruc = '1792708389001'
   AND v.eliminado = false
   AND v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial IN (
         '001-101-000031034', '001-101-000030757', '001-101-000031117', '001-101-000028611')
 ORDER BY v.fecha_emision;
