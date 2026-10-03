-- =====================================================================================
-- Asientos contables VIVOS cuyo documento de origen está ANULADO, ELIMINADO o ya NO EXISTE
-- =====================================================================================
-- Solo lectura. No modifica nada.
--
-- Por qué existen: hasta el 02-10-2026, Ingresos, Egresos, Traspasos, Retornos CV, Cambios de
-- Producto CV, Consignaciones y Facturación CV anulaban el asiento DESPUÉS de anular/eliminar el
-- documento y, si fallaba, el error solo quedaba en el log. Además, al ELIMINAR un ingreso
-- migrado, un retorno o un cambio emitido, el asiento nunca se anulaba. Desde ese cambio ya no
-- se generan casos nuevos; esta consulta lista los que quedaron.
--
-- Cubre los mismos orígenes que Contabilidad → Auditoría Contable + traspasos, y los asientos
-- MIGRADOS (modulo_origen = 'migracion') enlazados solo por id_asiento_contable.
-- Considera solo asientos del ambiente vigente de cada empresa (producción/pruebas).
--
-- Cómo corregir cada uno: Contabilidad → Asientos Contables → abrir el número y Anular
-- (queda en log_sistema). Para filtrar una empresa, descomente la línea marcada al final.
-- =====================================================================================
WITH docs AS (
    -- Asientos con origen nativo: asiento → documento por id_referencia_origen.
    SELECT 'factura_venta' AS modulo, a.id AS id_asiento, a.id_referencia_origen AS id_documento,
           CASE WHEN d.id IS NULL THEN 'no existe' WHEN d.eliminado THEN 'eliminado' ELSE 'anulado' END AS motivo
      FROM asientos_contables_cabecera a LEFT JOIN ventas_cabecera d ON d.id = a.id_referencia_origen
     WHERE a.modulo_origen = 'factura_venta' AND (d.id IS NULL OR d.eliminado OR LOWER(d.estado::text) IN ('anulado','anulada'))
    UNION ALL
    SELECT 'compra', a.id, a.id_referencia_origen,
           CASE WHEN d.id IS NULL THEN 'no existe' ELSE 'eliminado' END
      FROM asientos_contables_cabecera a LEFT JOIN compras_cabecera d ON d.id = a.id_referencia_origen
     WHERE a.modulo_origen = 'compra' AND (d.id IS NULL OR d.eliminado)
    UNION ALL
    SELECT 'liquidacion_compra', a.id, a.id_referencia_origen,
           CASE WHEN d.id IS NULL THEN 'no existe' WHEN d.eliminado THEN 'eliminado' ELSE 'anulado' END
      FROM asientos_contables_cabecera a LEFT JOIN liquidaciones_cabecera d ON d.id = a.id_referencia_origen
     WHERE a.modulo_origen = 'liquidacion_compra' AND (d.id IS NULL OR d.eliminado OR LOWER(d.estado::text) IN ('anulado','anulada'))
    UNION ALL
    SELECT 'nota_credito', a.id, a.id_referencia_origen,
           CASE WHEN d.id IS NULL THEN 'no existe' WHEN d.eliminado THEN 'eliminado' ELSE 'anulado' END
      FROM asientos_contables_cabecera a LEFT JOIN notas_credito_cabecera d ON d.id = a.id_referencia_origen
     WHERE a.modulo_origen = 'nota_credito' AND (d.id IS NULL OR d.eliminado OR LOWER(d.estado::text) IN ('anulado','anulada'))
    UNION ALL
    SELECT 'retencion_venta', a.id, a.id_referencia_origen,
           CASE WHEN d.id IS NULL THEN 'no existe' ELSE 'eliminado' END
      FROM asientos_contables_cabecera a LEFT JOIN retencion_venta_cabecera d ON d.id = a.id_referencia_origen
     WHERE a.modulo_origen = 'retencion_venta' AND (d.id IS NULL OR d.eliminado)
    UNION ALL
    SELECT 'retencion_compra', a.id, a.id_referencia_origen,
           CASE WHEN d.id IS NULL THEN 'no existe' WHEN d.eliminado THEN 'eliminado' ELSE 'anulado' END
      FROM asientos_contables_cabecera a LEFT JOIN retencion_compra_cabecera d ON d.id = a.id_referencia_origen
     WHERE a.modulo_origen = 'retencion_compra' AND (d.id IS NULL OR d.eliminado OR LOWER(d.estado::text) IN ('anulado','anulada'))
    UNION ALL
    SELECT 'ingreso', a.id, a.id_referencia_origen,
           CASE WHEN d.id IS NULL THEN 'no existe' WHEN d.eliminado THEN 'eliminado' ELSE 'anulado' END
      FROM asientos_contables_cabecera a LEFT JOIN ingresos_cabecera d ON d.id = a.id_referencia_origen
     WHERE a.modulo_origen = 'ingreso' AND (d.id IS NULL OR d.eliminado OR LOWER(d.estado::text) IN ('anulado','anulada'))
    UNION ALL
    SELECT 'egreso', a.id, a.id_referencia_origen,
           CASE WHEN d.id IS NULL THEN 'no existe' WHEN d.eliminado THEN 'eliminado' ELSE 'anulado' END
      FROM asientos_contables_cabecera a LEFT JOIN egresos_cabecera d ON d.id = a.id_referencia_origen
     WHERE a.modulo_origen = 'egreso' AND (d.id IS NULL OR d.eliminado OR LOWER(d.estado::text) IN ('anulado','anulada'))
    UNION ALL
    SELECT 'traspaso', a.id, a.id_referencia_origen,
           CASE WHEN d.id IS NULL THEN 'no existe' WHEN d.eliminado THEN 'eliminado' ELSE 'anulado' END
      FROM asientos_contables_cabecera a LEFT JOIN traspasos_cabecera d ON d.id = a.id_referencia_origen
     WHERE a.modulo_origen = 'traspaso' AND (d.id IS NULL OR d.eliminado OR LOWER(d.estado::text) IN ('anulado','anulada'))
    UNION ALL
    SELECT 'consignacion_venta', a.id, a.id_referencia_origen,
           CASE WHEN d.id IS NULL THEN 'no existe' WHEN d.eliminado THEN 'eliminado' ELSE 'anulado' END
      FROM asientos_contables_cabecera a LEFT JOIN consignaciones_ventas d ON d.id = a.id_referencia_origen
     WHERE a.modulo_origen = 'consignacion_venta' AND (d.id IS NULL OR d.eliminado OR LOWER(d.estado::text) IN ('anulado','anulada'))
    UNION ALL
    SELECT 'recibo_venta', a.id, a.id_referencia_origen,
           CASE WHEN d.id IS NULL THEN 'no existe' WHEN d.eliminado THEN 'eliminado' ELSE 'anulado' END
      FROM asientos_contables_cabecera a LEFT JOIN recibos_venta_cabecera d ON d.id = a.id_referencia_origen
     WHERE a.modulo_origen = 'recibo_venta' AND (d.id IS NULL OR d.eliminado OR LOWER(d.estado::text) IN ('anulado','anulada'))
    UNION ALL
    SELECT 'retorno_cv', a.id, a.id_referencia_origen,
           CASE WHEN d.id IS NULL THEN 'no existe' WHEN d.eliminado THEN 'eliminado' ELSE 'anulado' END
      FROM asientos_contables_cabecera a LEFT JOIN retornos_cv d ON d.id = a.id_referencia_origen
     WHERE a.modulo_origen = 'retorno_cv' AND (d.id IS NULL OR d.eliminado OR LOWER(d.estado::text) IN ('anulado','anulada'))
    UNION ALL
    SELECT 'cambio_producto_cv', a.id, a.id_referencia_origen,
           CASE WHEN d.id IS NULL THEN 'no existe' WHEN d.eliminado THEN 'eliminado' ELSE 'anulado' END
      FROM asientos_contables_cabecera a LEFT JOIN cambios_producto_cv d ON d.id = a.id_referencia_origen
     WHERE a.modulo_origen = 'cambio_producto_cv' AND (d.id IS NULL OR d.eliminado OR LOWER(d.estado::text) IN ('anulado','anulada'))
    UNION ALL
    SELECT 'FACTURACION_CV', a.id, a.id_referencia_origen,
           CASE WHEN d.id IS NULL THEN 'no existe' WHEN d.eliminado THEN 'eliminado' ELSE 'anulado' END
      FROM asientos_contables_cabecera a LEFT JOIN consignaciones_facturas d ON d.id = a.id_referencia_origen
     WHERE a.modulo_origen = 'FACTURACION_CV' AND (d.id IS NULL OR d.eliminado OR LOWER(d.estado::text) IN ('anulado','anulada'))
    UNION ALL
    SELECT 'nomina', a.id, a.id_referencia_origen,
           CASE WHEN d.id IS NULL THEN 'no existe' WHEN d.eliminado THEN 'eliminado' ELSE 'anulado' END
      FROM asientos_contables_cabecera a LEFT JOIN rol_cabecera d ON d.id = a.id_referencia_origen
     WHERE a.modulo_origen = 'nomina' AND (d.id IS NULL OR d.eliminado OR LOWER(d.estado::text) IN ('anulado','anulada'))

    -- Asientos MIGRADOS: no tienen modulo_origen del módulo; se enlazan por la columna del documento.
    UNION ALL
    SELECT 'ingreso (migrado)', d.id_asiento_contable, d.id,
           CASE WHEN d.eliminado THEN 'eliminado' ELSE 'anulado' END
      FROM ingresos_cabecera d
     WHERE d.id_asiento_contable IS NOT NULL AND (d.eliminado OR LOWER(d.estado::text) IN ('anulado','anulada'))
    UNION ALL
    SELECT 'egreso (migrado)', d.id_asiento_contable, d.id,
           CASE WHEN d.eliminado THEN 'eliminado' ELSE 'anulado' END
      FROM egresos_cabecera d
     WHERE d.id_asiento_contable IS NOT NULL AND (d.eliminado OR LOWER(d.estado::text) IN ('anulado','anulada'))
    UNION ALL
    SELECT 'traspaso (migrado)', d.id_asiento_contable, d.id,
           CASE WHEN d.eliminado THEN 'eliminado' ELSE 'anulado' END
      FROM traspasos_cabecera d
     WHERE d.id_asiento_contable IS NOT NULL AND (d.eliminado OR LOWER(d.estado::text) IN ('anulado','anulada'))
)
SELECT * FROM (
SELECT DISTINCT ON (a.id)
       e.id                  AS id_empresa,
       e.ruc,
       e.nombre              AS empresa,
       x.modulo,
       x.id_documento,
       x.motivo              AS documento_esta,
       a.id                  AS id_asiento,
       a.numero_comprobante,
       a.fecha_asiento,
       a.concepto,
       a.total_debe          AS monto
  FROM docs x
  JOIN asientos_contables_cabecera a ON a.id = x.id_asiento
  JOIN empresas e ON e.id = a.id_empresa
 WHERE a.eliminado = false
   AND a.estado <> 'anulado'
   AND CAST(a.tipo_ambiente AS VARCHAR(1)) = CAST(e.tipo_ambiente AS VARCHAR(1))
   AND x.id_documento IS NOT NULL
   -- AND e.ruc = '1793212004001'          -- ← descomente para una sola empresa
 ORDER BY a.id, x.modulo
) r
ORDER BY r.empresa, r.modulo, r.fecha_asiento;
