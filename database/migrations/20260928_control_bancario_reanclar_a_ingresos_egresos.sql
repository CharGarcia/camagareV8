-- ============================================================================
-- Control Bancario deja de leer los asientos contables (28-09-2026)
-- ----------------------------------------------------------------------------
-- Desde este cambio, el detalle de TODAS las cuentas bancarias sale de los cobros
-- y pagos registrados en Ingresos y Egresos (ingresos_pagos / egresos_pagos), no
-- del mayor contable. Las anotaciones del módulo (Fecha Banco de un cheque, tipo,
-- observación) que se hicieron sobre una LÍNEA DE ASIENTO dejarían de verse.
--
-- Este script las re-ancla al cobro/pago de origen (origen_tipo / origen_id), con
-- el mismo criterio con que el módulo enlazaba el asiento a su pago: el primer
-- cobro/pago del ingreso/egreso cuya forma usa la misma cuenta contable de la línea.
--
-- - Solo toca anotaciones vigentes ancladas a un asiento de INGRESOS/EGRESOS.
-- - No toca las que ya tengan una anotación propia en ese mismo cobro/pago.
-- - Las de asientos sin ingreso/egreso detrás no se tocan (ya no se muestran).
-- - Idempotente: se puede ejecutar más de una vez.
--
-- CÓMO EJECUTARLO EN pgAdmin: primero el SELECT de vista previa (paso 1) para ver
-- qué se movería; luego todo el bloque BEGIN … COMMIT (paso 2).
-- ============================================================================

-- ── Paso 1: vista previa (solo lectura) ─────────────────────────────────────
WITH destino AS (
    SELECT cbm.id AS id_cbm, cbm.fecha_banco, cbm.tipo_transaccion,
           CASE WHEN UPPER(ac.tipo_comprobante) = 'INGRESOS' THEN 'ingreso' ELSE 'egreso' END AS origen_tipo,
           COALESCE(
               (SELECT ip.id FROM ingresos_pagos ip
                  JOIN empresa_formas_pago fp ON fp.id = ip.id_forma_cobro
                 WHERE UPPER(ac.tipo_comprobante) = 'INGRESOS' AND ip.id_ingreso = ac.id_referencia_origen
                   AND fp.id_cuenta_contable = ad.id_cuenta_contable
                 ORDER BY ip.id LIMIT 1),
               (SELECT ep.id FROM egresos_pagos ep
                  JOIN empresa_formas_pago fp ON fp.id = ep.id_forma_pago
                 WHERE UPPER(ac.tipo_comprobante) = 'EGRESOS' AND ep.id_egreso = ac.id_referencia_origen
                   AND fp.id_cuenta_contable = ad.id_cuenta_contable AND ep.eliminado = FALSE
                 ORDER BY ep.id LIMIT 1)
           ) AS origen_id,
           ac.numero_comprobante
    FROM control_bancario_movimientos cbm
    JOIN asientos_contables_detalle ad ON ad.id = cbm.id_asiento_detalle
    JOIN asientos_contables_cabecera ac ON ac.id = ad.id_asiento
    WHERE cbm.eliminado = FALSE AND cbm.origen_tipo IS NULL
      AND UPPER(ac.tipo_comprobante) IN ('INGRESOS', 'EGRESOS')
)
SELECT d.*,
       EXISTS (SELECT 1 FROM control_bancario_movimientos o
               WHERE o.origen_tipo = d.origen_tipo AND o.origen_id = d.origen_id) AS ya_tiene_anotacion
FROM destino d
ORDER BY d.id_cbm;

-- ── Paso 2: re-anclar ───────────────────────────────────────────────────────
BEGIN;

WITH destino AS (
    SELECT cbm.id AS id_cbm,
           CASE WHEN UPPER(ac.tipo_comprobante) = 'INGRESOS' THEN 'ingreso' ELSE 'egreso' END AS origen_tipo,
           COALESCE(
               (SELECT ip.id FROM ingresos_pagos ip
                  JOIN empresa_formas_pago fp ON fp.id = ip.id_forma_cobro
                 WHERE UPPER(ac.tipo_comprobante) = 'INGRESOS' AND ip.id_ingreso = ac.id_referencia_origen
                   AND fp.id_cuenta_contable = ad.id_cuenta_contable
                 ORDER BY ip.id LIMIT 1),
               (SELECT ep.id FROM egresos_pagos ep
                  JOIN empresa_formas_pago fp ON fp.id = ep.id_forma_pago
                 WHERE UPPER(ac.tipo_comprobante) = 'EGRESOS' AND ep.id_egreso = ac.id_referencia_origen
                   AND fp.id_cuenta_contable = ad.id_cuenta_contable AND ep.eliminado = FALSE
                 ORDER BY ep.id LIMIT 1)
           ) AS origen_id
    FROM control_bancario_movimientos cbm
    JOIN asientos_contables_detalle ad ON ad.id = cbm.id_asiento_detalle
    JOIN asientos_contables_cabecera ac ON ac.id = ad.id_asiento
    WHERE cbm.eliminado = FALSE AND cbm.origen_tipo IS NULL
      AND UPPER(ac.tipo_comprobante) IN ('INGRESOS', 'EGRESOS')
),
validos AS (
    -- Una sola anotación por cobro/pago (índice único ux_cbm_origen): si dos apuntan al
    -- mismo, se mueve la más reciente y la otra queda como estaba.
    SELECT DISTINCT ON (d.origen_tipo, d.origen_id) d.*,
           CASE d.origen_tipo
               WHEN 'ingreso' THEN (SELECT id_forma_cobro FROM ingresos_pagos WHERE id = d.origen_id)
               ELSE (SELECT id_forma_pago FROM egresos_pagos WHERE id = d.origen_id)
           END AS id_forma
    FROM destino d
    WHERE d.origen_id IS NOT NULL
      AND NOT EXISTS (SELECT 1 FROM control_bancario_movimientos o
                      WHERE o.origen_tipo = d.origen_tipo AND o.origen_id = d.origen_id)
    ORDER BY d.origen_tipo, d.origen_id, d.id_cbm DESC
)
UPDATE control_bancario_movimientos cbm
   SET origen_tipo        = v.origen_tipo,
       origen_id          = v.origen_id,
       id_forma_pago      = v.id_forma,
       id_asiento_detalle = NULL,
       updated_at         = now()
  FROM validos v
 WHERE cbm.id = v.id_cbm;

COMMIT;
