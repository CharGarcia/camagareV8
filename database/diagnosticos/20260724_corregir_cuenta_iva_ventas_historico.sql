-- Corrige el histórico: líneas de "IVA Ventas" (Facturas) e "IVA Ventas (NC)" (Notas de
-- Crédito) que quedaron apuntando a la cuenta incorrecta 2.1.3.01.001 (SUELDO por pagar),
-- generadas ANTES de que se corrigiera la regla en Configuración Contable (21-jul-2026).
-- La cuenta correcta es 2.1.5.03.002 (15% IVA VENTAS) -- confirmada como la que usa HOY la
-- regla activa de asientos_programados (id=24, tipo_referencia='iva_ventas_factura').
--
-- No cambia total_debe/total_haber de la cabecera (solo cambia A QUÉ CUENTA apunta la línea,
-- no el monto), así que los asientos siguen cuadrados exactamente igual que antes.
--
-- Ejecutar dentro de una transacción para poder revisar antes de confirmar.

BEGIN;

-- 1) Vista previa: cuántas líneas se van a tocar (debe coincidir con lo que ya viste)
SELECT COUNT(*) AS lineas_a_corregir
FROM asientos_contables_detalle acd
JOIN asientos_contables_cabecera acc ON acc.id = acd.id_asiento AND acc.id_empresa = acd.id_empresa
JOIN plan_cuentas pc ON pc.id = acd.id_cuenta_contable AND pc.id_empresa = acd.id_empresa
WHERE acc.id_empresa = 8
  AND acc.eliminado = false
  AND acd.eliminado = false
  AND acc.modulo_origen IN ('factura_venta', 'nota_credito')
  AND acd.referencia_detalle IN ('IVA Ventas', 'IVA Ventas (NC)')
  AND pc.codigo = '2.1.3.01.001';

-- 2) La corrección
UPDATE asientos_contables_detalle acd
SET id_cuenta_contable = 2108,   -- 2.1.5.03.002 · 15% IVA VENTAS
    updated_at = CURRENT_TIMESTAMP,
    updated_by = 2               -- <-- ajusta al id de usuario que aplica la corrección
FROM asientos_contables_cabecera acc
WHERE acc.id = acd.id_asiento
  AND acc.id_empresa = acd.id_empresa
  AND acc.id_empresa = 8
  AND acc.eliminado = false
  AND acd.eliminado = false
  AND acc.modulo_origen IN ('factura_venta', 'nota_credito')
  AND acd.referencia_detalle IN ('IVA Ventas', 'IVA Ventas (NC)')
  AND acd.id_cuenta_contable = (
        SELECT pc.id FROM plan_cuentas pc
        WHERE pc.id_empresa = 8 AND pc.codigo = '2.1.3.01.001'
  );

-- 3) Verificación: ya no debe quedar ninguna línea de IVA Ventas con la cuenta vieja
SELECT COUNT(*) AS deberia_ser_cero
FROM asientos_contables_detalle acd
JOIN asientos_contables_cabecera acc ON acc.id = acd.id_asiento AND acc.id_empresa = acd.id_empresa
JOIN plan_cuentas pc ON pc.id = acd.id_cuenta_contable AND pc.id_empresa = acd.id_empresa
WHERE acc.id_empresa = 8
  AND acc.eliminado = false
  AND acd.eliminado = false
  AND acc.modulo_origen IN ('factura_venta', 'nota_credito')
  AND acd.referencia_detalle IN ('IVA Ventas', 'IVA Ventas (NC)')
  AND pc.codigo = '2.1.3.01.001';

-- Si el resultado del paso 3 es 0 y todo se ve bien: COMMIT;
-- Si algo no cuadra: ROLLBACK;
