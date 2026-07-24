-- Todas las líneas de IVA en Ventas (Facturas + Notas de Crédito) que apuntan a una
-- cuenta contable específica -- por defecto la reportada como incorrecta '2.1.3.01.001'
-- (SUELDO por pagar). Ajusta el código de cuenta si hace falta revisar otra.
--
-- Sirve para: (a) medir el alcance (cuántas facturas/NC quedaron mal, en qué rango de
-- fechas), y (b) tener a mano id_asiento_detalle + id_referencia_origen para decidir la
-- corrección (UPDATE puntual, no incluido aquí a propósito -- son documentos ya
-- contabilizados y esa corrección se prepara aparte, después de revisar este resultado).

SELECT
    acc.id_empresa,
    acc.modulo_origen,               -- 'factura_venta' o 'nota_credito'
    acc.id_referencia_origen,        -- id de la factura o de la nota de crédito
    acc.numero_comprobante,
    acc.fecha_asiento,
    acc.estado,
    acd.id             AS id_asiento_detalle,
    acd.referencia_detalle,
    acd.debe,
    acd.haber,
    pc.codigo           AS cuenta_actual_codigo,
    pc.nombre            AS cuenta_actual_nombre
FROM asientos_contables_detalle acd
JOIN asientos_contables_cabecera acc ON acc.id = acd.id_asiento AND acc.id_empresa = acd.id_empresa
JOIN plan_cuentas pc ON pc.id = acd.id_cuenta_contable AND pc.id_empresa = acd.id_empresa
WHERE acc.id_empresa = 8   -- <-- edita la empresa si hace falta
  AND acc.eliminado = false
  AND acd.eliminado = false
  AND acc.modulo_origen IN ('factura_venta', 'nota_credito')
  AND acd.referencia_detalle IN ('IVA Ventas', 'IVA Ventas (NC)')
  AND pc.codigo = '2.1.3.01.001'
ORDER BY acc.fecha_asiento, acc.id, acd.id;
