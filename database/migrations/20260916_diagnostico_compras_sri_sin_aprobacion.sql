-- =====================================================================================
-- DIAGNÓSTICO (solo lectura) — Compras del SRI que debieron quedar pendientes de aprobación
-- -------------------------------------------------------------------------------------
-- Contexto:
--   Desde el 19-08-2026 (commit c5f75278) DocumentoAutomatedRegisterService::insertarCompra()
--   repetía la clave 'estado' en el INSERT de la cabecera y la segunda ('registrado') pisaba a
--   la primera. En empresas con la aprobación de compras activa, las facturas (01) y
--   liquidaciones (03) cargadas desde el SRI se grabaron como 'registrado', pero igual se les
--   generó token de aprobación, se envió el correo a los aprobadores y se omitió su pago
--   automático. El enlace del correo no sirve con ellas: exige que la compra esté pendiente.
--
--   Lista esas compras con lo necesario para decidir qué hacer con cada una: si ya tiene
--   asiento, pagos, retención o inventario procesado, ya se trató como compra aprobada.
--
-- Toca datos: NO (solo SELECT).
-- =====================================================================================
SELECT e.id                                   AS id_empresa,
       e.ruc                                  AS ruc_empresa,
       e.nombre                               AS empresa,
       c.id                                   AS id_compra,
       CASE c.tipo_comprobante WHEN '01' THEN 'Factura'
                               WHEN '03' THEN 'Liquidación de compra'
                               ELSE c.tipo_comprobante END AS tipo,
       c.establecimiento_prov || '-' || c.punto_emision_prov || '-' || c.secuencial_prov AS numero,
       c.fecha_emision,
       p.identificacion                       AS ruc_proveedor,
       p.razon_social                         AS proveedor,
       c.importe_total                        AS total,
       c.created_at                           AS cargada_el,
       (c.id_asiento_contable IS NOT NULL)    AS tiene_asiento,
       COALESCE((SELECT SUM(ed.monto_pagado)
                   FROM egresos_detalle ed
                   JOIN egresos_cabecera ec ON ec.id = ed.id_egreso
                  WHERE ed.tipo_documento = 'COMPRA'
                    AND ed.id_referencia_documento = c.id
                    AND ed.eliminado = false
                    AND ec.eliminado = false
                    AND ec.estado <> 'anulado'), 0) AS total_pagado,
       EXISTS (SELECT 1 FROM retencion_compra_cabecera r
                WHERE r.id_compra = c.id AND r.eliminado = false)         AS tiene_retencion,
       EXISTS (SELECT 1 FROM inventario_kardex k
                WHERE k.id_empresa = c.id_empresa AND k.referencia_tipo = 'compra'
                  AND k.referencia_id = c.id AND k.eliminado = false)     AS inventario_procesado
  FROM compras_cabecera c
  JOIN empresas e    ON e.id = c.id_empresa
  JOIN proveedores p ON p.id = c.id_proveedor
 WHERE c.token_aprobacion IS NOT NULL
   AND c.estado = 'registrado'
   AND c.eliminado = false
 ORDER BY e.id, c.fecha_emision, c.id;
