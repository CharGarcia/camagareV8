-- =============================================================================
-- Diagnóstico (SOLO LECTURA): asientos de ventas / compras cuya CARTERA no
-- coincide con el total del documento ("Cartera del asiento: X · diferencia: Y").
--
-- La "cartera del asiento" es la suma de las líneas cuya cuenta está HOY
-- configurada como Cuenta por Cobrar (Ventas con Factura / Recibos de Venta) o
-- Cuenta por Pagar (Adquisiciones de Compras), en el lado que corresponde (Debe
-- en ventas, Haber en compras). Mismo criterio que la pestaña «Asiento contable»
-- y Auditoría Contable (AsientoContableRules::evaluarCuadreDocumento).
--
-- Cómo leer cada fila:
--   total_documento      importe total del documento
--   cartera_asiento      lo que el asiento lleva en cuentas de cartera configuradas
--   diferencia           total_documento - cartera_asiento
--   lado_total           TODO lo que el asiento lleva en ese lado (Debe en ventas)
--   otras_cuentas_lado   cuentas de ese lado que NO son cartera, con su monto.
--                        AHÍ ESTÁ LA CAUSA, por ejemplo:
--                          · una CxC/CxP vieja que ya no está en la configuración
--                            (la cuenta se cambió después de generar el asiento);
--                          · Caja/Banco o un anticipo en lugar de la CxC;
--                          · si lado_total = total_documento, el asiento sí refleja
--                            el documento y la diferencia es solo de clasificación.
--   situacion            la causa probable, ya clasificada.
--   doc_actualizado /    si el documento se modificó DESPUÉS que el asiento y la
--   asiento_actualizado  situación es «no refleja el documento», el asiento se quedó viejo.
--   editado_manual       true = el asiento se editó a mano (no se regenera solo)
--
-- Uso: cambiar el RUC en la marca "<== cambiar" (o quitar ese filtro para todas
-- las empresas) y ejecutar en pgAdmin.
-- =============================================================================

WITH empresa AS (
    SELECT id FROM empresas
     WHERE ruc = '1791400135001'            -- <== cambiar
),
docs AS (
    SELECT 'factura_venta'::text AS modulo, 'Factura de venta'::text AS tipo, v.id, v.id_empresa,
           concat_ws('-', v.establecimiento, v.punto_emision, v.secuencial) AS numero,
           v.fecha_emision, v.importe_total AS total, 'debe'::text AS lado, 'PORCOBRARFACTURAVENTA'::text AS slot, v.updated_at AS doc_actualizado
      FROM ventas_cabecera v
     WHERE v.id_empresa IN (SELECT id FROM empresa) AND v.eliminado = false
    UNION ALL
    SELECT 'recibo_venta', 'Recibo de venta', r.id, r.id_empresa,
           concat_ws('-', r.establecimiento, r.punto_emision, r.secuencial),
           r.fecha_emision, r.importe_total, 'debe', 'PORCOBRARRECIBOVENTA', r.updated_at
      FROM recibos_venta_cabecera r
     WHERE r.id_empresa IN (SELECT id FROM empresa) AND r.eliminado = false
    UNION ALL
    SELECT 'compra', 'Compra', c.id, c.id_empresa,
           concat_ws('-', c.establecimiento_prov, c.punto_emision_prov, c.secuencial_prov),
           c.fecha_emision, c.importe_total, 'haber', 'PORPAGARFACTURACOMPRA', c.updated_at
      FROM compras_cabecera c
     WHERE c.id_empresa IN (SELECT id FROM empresa) AND c.eliminado = false
    UNION ALL
    SELECT 'liquidacion_compra', 'Liquidación de compra', l.id, l.id_empresa,
           concat_ws('-', l.establecimiento, l.punto_emision, l.secuencial),
           l.fecha_emision, l.importe_total, 'haber', 'PORPAGARFACTURACOMPRA', l.updated_at
      FROM liquidaciones_cabecera l
     WHERE l.id_empresa IN (SELECT id FROM empresa) AND l.eliminado = false
),
-- Cuentas configuradas HOY para cada slot de cartera, en cualquier nivel de la cascada.
cartera AS (
    SELECT DISTINCT ap.id_empresa, at.codigo AS slot, ap.id_cuenta
      FROM asientos_programados ap
      JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo
     WHERE ap.eliminado = false
       AND ap.id_cuenta IS NOT NULL
       AND at.codigo IN ('PORCOBRARFACTURAVENTA', 'PORCOBRARRECIBOVENTA', 'PORPAGARFACTURACOMPRA')
       AND ap.id_empresa IN (SELECT id FROM empresa)
),
lineas AS (
    SELECT d.modulo, d.id AS id_doc, a.id AS id_asiento, a.numero_comprobante, a.editado_manual, a.updated_at AS asiento_actualizado,
           ad.id_cuenta_contable,
           CASE WHEN d.lado = 'debe' THEN ad.debe ELSE ad.haber END AS monto_lado,
           EXISTS (SELECT 1 FROM cartera k
                    WHERE k.id_empresa = d.id_empresa AND k.slot = d.slot
                      AND k.id_cuenta = ad.id_cuenta_contable)       AS es_cartera
      FROM docs d
      JOIN asientos_contables_cabecera a
        ON a.modulo_origen = d.modulo
       AND a.id_referencia_origen = d.id
       AND a.id_empresa = d.id_empresa
       AND a.eliminado = false
       AND a.estado <> 'anulado'
      JOIN asientos_contables_detalle ad ON ad.id_asiento = a.id AND ad.eliminado = false
),
resumen AS (
    SELECT l.modulo, l.id_doc, l.id_asiento, l.numero_comprobante, l.editado_manual, l.asiento_actualizado,
           ROUND(SUM(l.monto_lado) FILTER (WHERE l.es_cartera), 2)       AS cartera_asiento,
           ROUND(SUM(l.monto_lado), 2)                                    AS lado_total,
           string_agg(pc.codigo || ' ' || pc.nombre || ' = ' || ROUND(l.monto_lado, 2)::text, ' | ')
               FILTER (WHERE NOT l.es_cartera AND l.monto_lado > 0)       AS otras_cuentas_lado
      FROM lineas l
      LEFT JOIN plan_cuentas pc ON pc.id = l.id_cuenta_contable
     GROUP BY l.modulo, l.id_doc, l.id_asiento, l.numero_comprobante, l.editado_manual, l.asiento_actualizado
)
SELECT d.tipo,
       d.numero,
       d.fecha_emision,
       r.numero_comprobante                                   AS asiento,
       ROUND(d.total, 2)                                      AS total_documento,
       COALESCE(r.cartera_asiento, 0)                         AS cartera_asiento,
       ROUND(d.total - COALESCE(r.cartera_asiento, 0), 2)     AS diferencia,
       r.lado_total,
       r.otras_cuentas_lado,
       CASE
         WHEN r.editado_manual THEN 'Asiento editado a mano: ya no sigue al documento'
         WHEN ABS(d.total - r.lado_total) <= 0.03 AND COALESCE(r.cartera_asiento, 0) = 0
              THEN 'Clasificación: la cuenta usada no está configurada HOY como cartera (el asiento sí refleja el documento)'
         WHEN ABS(d.total - r.lado_total) <= 0.03
              THEN 'Clasificación: parte de la cartera está en una cuenta que HOY no es de cartera (el asiento sí refleja el documento)'
         WHEN r.lado_total < d.total
              THEN 'El asiento NO refleja el documento completo (cambió después o no se regeneró)'
         ELSE 'El asiento lleva MÁS que el documento en ese lado: revisar'
       END AS situacion,
       d.doc_actualizado,
       r.asiento_actualizado,
       r.editado_manual,
       r.id_asiento
  FROM docs d
  JOIN resumen r ON r.modulo = d.modulo AND r.id_doc = d.id
 WHERE d.total > 0
   AND ABS(d.total - COALESCE(r.cartera_asiento, 0)) > 0.03
 ORDER BY ABS(d.total - COALESCE(r.cartera_asiento, 0)) DESC, d.fecha_emision DESC;
