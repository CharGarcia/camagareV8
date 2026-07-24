-- Investiga por qué la factura 001-102-000000255 (empresa 8) tiene 3 asientos contables.
-- Trae TODO el historial (sin filtrar eliminado/estado) para ver si son duplicados activos
-- reales, o si 1-2 quedaron anulados/eliminados por una reversión anterior.

-- 0) Datos básicos de la factura
SELECT id, id_cliente, importe_total, id_asiento_contable, fecha_emision, estado
FROM ventas_cabecera
WHERE id_empresa = 8
  AND CONCAT(establecimiento, '-', punto_emision, '-', secuencial) = '001-102-000000255';

-- 1) Los 3 asientos + su detalle completo
WITH venta AS (
    SELECT id
    FROM ventas_cabecera
    WHERE id_empresa = 8
      AND CONCAT(establecimiento, '-', punto_emision, '-', secuencial) = '001-102-000000255'
),
asientos AS (
    SELECT ac.*
    FROM asientos_contables_cabecera ac
    JOIN venta v ON v.id = ac.id_referencia_origen
    WHERE ac.modulo_origen = 'factura_venta'
      AND ac.id_empresa = 8
)
SELECT
    a.id                 AS id_asiento,
    a.numero_comprobante,
    a.fecha_asiento,
    a.estado             AS estado_asiento,
    a.eliminado          AS asiento_eliminado,
    a.concepto,
    a.total_debe,
    a.total_haber,
    a.created_at,
    a.created_by,
    a.updated_at,
    a.updated_by,
    d.id                 AS id_detalle,
    d.debe,
    d.haber,
    d.referencia_detalle,
    d.documento_referencia,
    d.eliminado          AS detalle_eliminado,
    pc.codigo            AS cuenta_codigo,
    pc.nombre            AS cuenta_nombre
FROM asientos a
JOIN asientos_contables_detalle d ON d.id_asiento = a.id
LEFT JOIN plan_cuentas pc ON pc.id = d.id_cuenta_contable
ORDER BY a.id, d.id;
