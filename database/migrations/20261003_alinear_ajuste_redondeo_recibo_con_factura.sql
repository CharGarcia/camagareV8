-- ============================================================
-- Alinea el concepto «Ajuste por redondeo» de Recibos de Venta
-- (AJUSTEREDONDEORECIBOVENTA) con el de Facturas de Venta
-- (AJUSTEREDONDEOVENTA): mismo lado (debe_haber) y mismos tipos
-- de cuenta permitidos (tipo_cuenta).
--
-- Motivo: en producción AJUSTEREDONDEOVENTA se cambió a 'haber' /
-- 'ingreso,gasto', pero el catálogo de recibos (migración
-- 20260715_create_recibos_venta_asientos_tipo.sql) se creó con el
-- valor anterior ('debe' / 'ingreso,costo,gasto'). Configuración
-- Contable agrupa por debe_haber, así que en Recibos el ajuste salía
-- en el Debe y en Facturas en el Haber.
--
-- No afecta asientos: el ajuste por redondeo va al lado que cuadra
-- (AsientoBuilderService::aplicarAjusteRedondeo). asientos_tipo es
-- tabla GLOBAL (sin id_empresa).
-- Idempotente: copia los valores de la factura; no hace nada si ya
-- coinciden.
-- ============================================================

UPDATE asientos_tipo r
SET debe_haber  = f.debe_haber,
    tipo_cuenta = f.tipo_cuenta
FROM asientos_tipo f
WHERE r.codigo = 'AJUSTEREDONDEORECIBOVENTA'
  AND f.codigo = 'AJUSTEREDONDEOVENTA'
  AND (r.debe_haber IS DISTINCT FROM f.debe_haber
       OR r.tipo_cuenta IS DISTINCT FROM f.tipo_cuenta);

-- Verificación: las dos filas deben quedar iguales en debe_haber y tipo_cuenta.
SELECT id, tipo_asiento, codigo, referencia, debe_haber, tipo_cuenta
FROM asientos_tipo
WHERE codigo IN ('AJUSTEREDONDEOVENTA', 'AJUSTEREDONDEORECIBOVENTA')
ORDER BY tipo_asiento;
