-- Diagnóstico (SOLO LECTURA): pagos (egresos) migrados que quedaron sin enlazar a su documento,
-- en TODAS las empresas. El pago existe, pero la compra / liquidación / rol aparece pendiente de pago.
-- Una fila por empresa y tipo de documento. No modifica nada.
--   compra_unica_encontrada : hay exactamente UNA compra viva con ese proveedor y número → se puede enlazar.
--   compra_ambigua          : hay más de una (el número se repite) → hay que decidir a mano.
--   sin_numero              : la línea no guardó el número del documento → no se puede buscar por número.
--   (lo que no cae en ninguna de las tres: no existe la compra en el sistema nuevo)
SELECT e.id, e.ruc, e.nombre, d.tipo_documento,
       COUNT(*)                     AS lineas_sin_enlace,
       COUNT(DISTINCT d.id_egreso)  AS egresos,
       SUM(d.monto_pagado)          AS total,
       COUNT(*) FILTER (WHERE d.tipo_documento = 'COMPRA' AND k.n = 1) AS compra_unica_encontrada,
       COUNT(*) FILTER (WHERE d.tipo_documento = 'COMPRA' AND k.n > 1) AS compra_ambigua,
       COUNT(*) FILTER (WHERE COALESCE(d.numero_documento, '') = '')   AS sin_numero
FROM egresos_detalle d
JOIN egresos_cabecera c    ON c.id = d.id_egreso
JOIN migracion_mysql_map m ON m.id_empresa = c.id_empresa AND m.entidad = 'egresos' AND m.id_destino = c.id
JOIN empresas e            ON e.id = c.id_empresa
LEFT JOIN LATERAL (
    SELECT COUNT(*) AS n
      FROM compras_cabecera x
     WHERE x.id_empresa = c.id_empresa AND x.eliminado = false
       AND x.id_proveedor = c.id_proveedor
       AND COALESCE(x.tipo_comprobante, '01') <> '04'
       AND d.numero_documento ~ '^[0-9]{1,3}-[0-9]{1,3}-[0-9]{1,9}$'
       AND (x.establecimiento_prov || x.punto_emision_prov || x.secuencial_prov)
           = (LPAD(SPLIT_PART(d.numero_documento, '-', 1), 3, '0')
              || LPAD(SPLIT_PART(d.numero_documento, '-', 2), 3, '0')
              || LPAD(SPLIT_PART(d.numero_documento, '-', 3), 9, '0'))
) k ON true
WHERE c.eliminado = false AND c.estado <> 'anulado' AND d.eliminado = false
  AND d.tipo_documento IN ('COMPRA', 'LIQUIDACION', 'ROL')
  AND (d.id_referencia_documento IS NULL
       OR (d.tipo_documento = 'COMPRA' AND NOT EXISTS (
               SELECT 1 FROM compras_cabecera z
                WHERE z.id = d.id_referencia_documento AND z.id_empresa = c.id_empresa AND z.eliminado = false))
       OR (d.tipo_documento = 'LIQUIDACION' AND NOT EXISTS (
               SELECT 1 FROM liquidaciones_cabecera z
                WHERE z.id = d.id_referencia_documento AND z.id_empresa = c.id_empresa AND z.eliminado = false))
       OR (d.tipo_documento = 'ROL' AND NOT EXISTS (
               SELECT 1 FROM rol_detalle z
                WHERE z.id = d.id_referencia_documento AND z.id_empresa = c.id_empresa)))
GROUP BY e.id, e.ruc, e.nombre, d.tipo_documento
ORDER BY total DESC;
