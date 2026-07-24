-- 1) Qué cuenta está HOY configurada para "IVA en Ventas" (aplica a Facturas Y Notas de
--    Crédito, comparten el mismo tipo_referencia='iva_ventas_factura'), por tarifa.
--    Sin filtrar eliminado: así se ve tanto la regla activa como cualquier versión vieja
--    dada de baja (útil para confirmar si alguien ya corrigió creando una fila nueva).
SELECT
    ap.id,
    ap.id_referencia      AS codigo_tarifa_iva,
    ap.codigo_tarifa_iva,
    ap.direccion_iva,
    ap.id_cuenta,
    pc.codigo              AS cuenta_codigo,
    pc.nombre               AS cuenta_nombre,
    ap.eliminado,
    ap.created_at,
    ap.updated_at
FROM asientos_programados ap
JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
WHERE ap.id_empresa = 8   -- <-- edita la empresa si hace falta
  AND ap.tipo_referencia = 'iva_ventas_factura'
ORDER BY ap.eliminado, ap.id_referencia, ap.id;

-- 2) Overrides por dimensión (cliente/producto/categoría/marca) para IVA en ventas,
--    por si alguno también quedó apuntando a la cuenta incorrecta.
SELECT
    ap.id,
    ap.tipo_referencia,   -- cliente/producto/categoria/marca
    ap.id_referencia,     -- id de la entidad (cliente, producto, etc.)
    ap.codigo_tarifa_iva,
    ap.direccion_iva,
    ap.id_cuenta,
    pc.codigo             AS cuenta_codigo,
    pc.nombre              AS cuenta_nombre,
    ap.eliminado
FROM asientos_programados ap
JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
WHERE ap.id_empresa = 8   -- <-- edita la empresa si hace falta
  AND ap.id_asiento_tipo = 0
  AND ap.direccion_iva = 'venta'
  AND ap.tipo_referencia IN ('cliente', 'producto', 'categoria', 'marca')
ORDER BY ap.eliminado, ap.tipo_referencia, ap.id;
