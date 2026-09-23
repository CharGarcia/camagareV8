-- ============================================================================
-- Corrige asientos de COMPRA migrados desde MySQL con el IVA multiplicado x1000
-- ----------------------------------------------------------------------------
-- Origen: el sistema viejo guardó en detalle_diario_contable el IVA x1000
-- (p. ej. COM377787: IVA 37303.20 en vez de 37.30; CxP 37551.89 en vez de 285.99).
-- La migración copia debe/haber tal cual, así que el error llegó a PostgreSQL.
-- El documento (compras_cabecera / compras_detalle_impuestos) está bien.
--
-- Detección: asientos 'migracion' COM* cuyo IVA / (total - IVA) cae entre 40 y 160
-- (tarifa aparente 4000 %..16000 %, es decir 5 %..15 % multiplicado por 1000).
-- Los casos raros que no siguen ese patrón NO se tocan (revisión manual).
--
-- Corrección por asiento:
--   - Cada línea de IVA (cuenta con 'IVA' en el nombre y sin 'RET') se divide para 1000.
--   - La línea de contrapartida (la no-IVA con el importe total) se reduce en la
--     misma diferencia, así el asiento sigue cuadrado.
--   - total_debe / total_haber de la cabecera se recalculan desde el detalle.
--
-- OJO: si se vuelve a correr la migración de contabilidad sobre estas fechas, el
-- migrador reconstruye el detalle desde la base vieja y el error regresa.
--
-- Uso: correr por partes. 1) PREVIEW. 2) Bloque de corrección dentro de BEGIN,
-- revisar la verificación final y recién ahí COMMIT (o ROLLBACK).
-- ============================================================================

-- ── 1. PREVIEW (solo lectura) ───────────────────────────────────────────────
WITH lineas AS (
    SELECT a.id AS id_asiento, a.id_empresa, a.numero_comprobante, a.fecha_asiento,
           d.debe, d.haber,
           (pc.nombre ILIKE '%IVA%' AND pc.nombre NOT ILIKE '%RET%') AS es_iva
    FROM asientos_contables_cabecera a
    JOIN asientos_contables_detalle d ON d.id_asiento = a.id AND d.eliminado = false
    JOIN plan_cuentas pc ON pc.id = d.id_cuenta_contable
    WHERE a.modulo_origen = 'migracion' AND a.eliminado = false
      AND a.numero_comprobante LIKE 'COM%'
),
agg AS (
    SELECT id_asiento, id_empresa, numero_comprobante, fecha_asiento,
           SUM(debe + haber) FILTER (WHERE es_iva) AS iva,
           GREATEST(SUM(debe), SUM(haber))         AS total
    FROM lineas GROUP BY 1,2,3,4
)
SELECT e.ruc, e.nombre, g.id_asiento, g.numero_comprobante, g.fecha_asiento,
       g.iva, g.total, g.total - g.iva AS base,
       ROUND(g.iva / (g.total - g.iva) * 100, 1)  AS tarifa_aparente_pct,
       ROUND(g.iva / 1000, 2)                      AS iva_corregido,
       ROUND(g.total - g.iva + g.iva / 1000, 2)    AS total_corregido
FROM agg g
JOIN empresas e ON e.id = g.id_empresa
WHERE g.iva > 0 AND (g.total - g.iva) > 0.01
  AND g.iva / (g.total - g.iva) BETWEEN 40 AND 160
ORDER BY e.ruc, g.fecha_asiento;


-- ── 2. CORRECCIÓN ───────────────────────────────────────────────────────────
BEGIN;

CREATE TEMP TABLE tmp_iva_x1000 ON COMMIT DROP AS
WITH lineas AS (
    SELECT a.id AS id_asiento, d.debe, d.haber,
           (pc.nombre ILIKE '%IVA%' AND pc.nombre NOT ILIKE '%RET%') AS es_iva
    FROM asientos_contables_cabecera a
    JOIN asientos_contables_detalle d ON d.id_asiento = a.id AND d.eliminado = false
    JOIN plan_cuentas pc ON pc.id = d.id_cuenta_contable
    WHERE a.modulo_origen = 'migracion' AND a.eliminado = false
      AND a.numero_comprobante LIKE 'COM%'
),
agg AS (
    SELECT id_asiento,
           SUM(debe + haber) FILTER (WHERE es_iva) AS iva,
           GREATEST(SUM(debe), SUM(haber))         AS total
    FROM lineas GROUP BY 1
)
SELECT id_asiento, iva, total,
       iva - (SELECT SUM(ROUND((d.debe + d.haber) / 1000, 2))
                FROM asientos_contables_detalle d
                JOIN plan_cuentas pc ON pc.id = d.id_cuenta_contable
               WHERE d.id_asiento = agg.id_asiento AND d.eliminado = false
                 AND pc.nombre ILIKE '%IVA%' AND pc.nombre NOT ILIKE '%RET%') AS delta
FROM agg
WHERE iva > 0 AND (total - iva) > 0.01
  AND iva / (total - iva) BETWEEN 40 AND 160;

SELECT COUNT(*) AS asientos_a_corregir FROM tmp_iva_x1000;

-- 2a. Contrapartida (línea no-IVA con el importe total): se reduce en delta.
--     Se hace ANTES de tocar el IVA para seguir reconociéndola por su importe.
UPDATE asientos_contables_detalle d
   SET debe  = CASE WHEN d.debe  > 0 THEN d.debe  - t.delta ELSE d.debe  END,
       haber = CASE WHEN d.haber > 0 THEN d.haber - t.delta ELSE d.haber END,
       updated_at = now()
  FROM tmp_iva_x1000 t, plan_cuentas pc
 WHERE d.id_asiento = t.id_asiento AND d.eliminado = false
   AND pc.id = d.id_cuenta_contable
   AND NOT (pc.nombre ILIKE '%IVA%' AND pc.nombre NOT ILIKE '%RET%')
   AND (d.debe + d.haber) = t.total;

-- 2b. Líneas de IVA: / 1000.
UPDATE asientos_contables_detalle d
   SET debe  = ROUND(d.debe  / 1000, 2),
       haber = ROUND(d.haber / 1000, 2),
       updated_at = now()
  FROM tmp_iva_x1000 t, plan_cuentas pc
 WHERE d.id_asiento = t.id_asiento AND d.eliminado = false
   AND pc.id = d.id_cuenta_contable
   AND pc.nombre ILIKE '%IVA%' AND pc.nombre NOT ILIKE '%RET%';

-- 2c. Totales de cabecera desde el detalle.
UPDATE asientos_contables_cabecera a
   SET total_debe  = s.td,
       total_haber = s.th,
       updated_at  = now()
  FROM (SELECT d.id_asiento, SUM(d.debe) td, SUM(d.haber) th
          FROM asientos_contables_detalle d
          JOIN tmp_iva_x1000 t ON t.id_asiento = d.id_asiento
         WHERE d.eliminado = false
         GROUP BY d.id_asiento) s
 WHERE a.id = s.id_asiento;

-- 2d. VERIFICACIÓN: todos deben salir cuadrados y con tarifa normal (5..15 %).
SELECT a.id, a.numero_comprobante, a.total_debe, a.total_haber,
       a.total_debe - a.total_haber AS descuadre,
       ROUND(t.iva / 1000 / NULLIF(a.total_debe - t.iva / 1000, 0) * 100, 1) AS tarifa_pct
  FROM asientos_contables_cabecera a
  JOIN tmp_iva_x1000 t ON t.id_asiento = a.id
 ORDER BY ABS(a.total_debe - a.total_haber) DESC;

-- Si todo cuadra (descuadre = 0) → COMMIT;  si no → ROLLBACK;
-- COMMIT;
