-- =============================================================================
-- Diagnóstico: "El balance no cuadra con el principio de partida doble"
-- (Estados Financieros → Estado de Situación Financiera)
--
-- SOLO LECTURA. No modifica nada.
--
-- Por qué cuadra (o no) el balance:
--   EstadosFinancierosRepository::getSaldos() suma debe/haber de las líneas de
--   asientos 'contabilizado', no eliminados, del ambiente activo de la empresa y
--   dentro del rango de fechas del reporte, agrupadas por cuenta de plan_cuentas.
--   Con esa regla:
--       Activos − (Pasivo + Patrimonio + Resultado)  =  Σ (debe − haber)
--   de las líneas que el reporte SÍ cuenta (clases 1-6 y la clase 7 de nivel 5).
--   Si cada asiento cuadra, eso da 0. La diferencia sale de:
--     (1) asientos cuyas LÍNEAS no cuadran (aunque la cabecera diga que sí), o
--     (2) líneas que el reporte NO cuenta: cuenta inexistente/eliminada/de otra
--         empresa, o de una clase que no es 1-6 (p. ej. 7 de nivel < 5, 8, 9, 0).
--   Diferencia del reporte = Σ(1) − Σ(2)   (consulta 4 lo comprueba).
--
-- Uso: cambiar RUC y fechas en el bloque "p" de CADA consulta (mismo rango que
-- se usó en el reporte). Ejecutar en psql / pgAdmin sobre producción.
--
-- En pgAdmin, ejecutar UNA consulta a la vez (seleccionar el bloque y F5). Si una
-- falla, la transacción queda abortada y las siguientes responden 25P02
-- ("current transaction is aborted"): ejecutar ROLLBACK; y volver a intentar.
-- =============================================================================


-- 0) Empresa(s) con ese RUC (un RUC puede tener varios establecimientos) --------
SELECT id, nombre, nombre_comercial, tipo_ambiente, estado, eliminado
FROM empresas
WHERE ruc = '1711548931001';


-- 1) Asientos cuyas LÍNEAS no cuadran (causa más común) --------------------------
--    También muestra si la cabecera (total_debe/total_haber) difiere de sus líneas.
WITH p AS (
    SELECT e.id AS id_empresa, CAST(e.tipo_ambiente AS VARCHAR(1)) AS amb,
           DATE '2026-01-01' AS desde, DATE '2026-12-31' AS hasta      -- ← rango del reporte
    FROM empresas e
    WHERE e.ruc = '1711548931001' AND e.eliminado = false               -- ← RUC
)
SELECT ac.id_empresa, ac.id AS id_asiento, ac.fecha_asiento::date AS fecha, ac.tipo_comprobante,
       ac.numero_comprobante, ac.modulo_origen, ac.id_referencia_origen,
       LEFT(ac.concepto, 80) AS concepto,
       ac.total_debe  AS cab_debe,  SUM(ad.debe)  AS lin_debe,
       ac.total_haber AS cab_haber, SUM(ad.haber) AS lin_haber,
       ROUND(SUM(ad.debe) - SUM(ad.haber), 2) AS descuadre_lineas,
       SUM(ROUND(SUM(ad.debe) - SUM(ad.haber), 2)) OVER (PARTITION BY ac.id_empresa) AS suma_descuadres
FROM p
JOIN asientos_contables_cabecera ac
     ON ac.id_empresa = p.id_empresa AND ac.eliminado = false
    AND ac.estado = 'contabilizado' AND CAST(ac.tipo_ambiente AS VARCHAR(1)) = p.amb
    AND ac.fecha_asiento BETWEEN p.desde AND p.hasta + TIME '23:59:59'
JOIN asientos_contables_detalle ad ON ad.id_asiento = ac.id AND ad.eliminado = false
GROUP BY ac.id
HAVING ROUND(SUM(ad.debe) - SUM(ad.haber), 2) <> 0
ORDER BY ABS(SUM(ad.debe) - SUM(ad.haber)) DESC;


-- 2) Líneas que el balance NO cuenta (cuenta inválida o clase fuera de 1-6) ------
WITH p AS (
    SELECT e.id AS id_empresa, CAST(e.tipo_ambiente AS VARCHAR(1)) AS amb,
           DATE '2026-01-01' AS desde, DATE '2026-12-31' AS hasta      -- ← rango del reporte
    FROM empresas e
    WHERE e.ruc = '1711548931001' AND e.eliminado = false               -- ← RUC
)
SELECT ac.id_empresa, ac.id AS id_asiento, ac.fecha_asiento::date AS fecha, ac.numero_comprobante,
       ac.modulo_origen, ad.id AS id_linea, ad.id_cuenta_contable,
       pc.codigo, pc.nombre, pc.nivel, pc.id_empresa AS empresa_de_la_cuenta,
       pc.eliminado AS cuenta_eliminada,
       CASE
           WHEN pc.id IS NULL                      THEN 'cuenta no existe'
           WHEN pc.eliminado                       THEN 'cuenta eliminada'
           WHEN pc.id_empresa <> p.id_empresa      THEN 'cuenta de otra empresa'
           WHEN LEFT(pc.codigo, 1) = '7' AND CAST(pc.nivel AS VARCHAR) <> '5' THEN 'clase 7 no es nivel 5'
           ELSE 'clase ' || LEFT(pc.codigo, 1) || ' no entra al balance'
       END AS motivo,
       ad.debe, ad.haber,
       SUM(ad.debe - ad.haber) OVER (PARTITION BY ac.id_empresa) AS suma_excluida
FROM p
JOIN asientos_contables_cabecera ac
     ON ac.id_empresa = p.id_empresa AND ac.eliminado = false
    AND ac.estado = 'contabilizado' AND CAST(ac.tipo_ambiente AS VARCHAR(1)) = p.amb
    AND ac.fecha_asiento BETWEEN p.desde AND p.hasta + TIME '23:59:59'
JOIN asientos_contables_detalle ad ON ad.id_asiento = ac.id AND ad.eliminado = false
LEFT JOIN plan_cuentas pc ON pc.id = ad.id_cuenta_contable
WHERE pc.id IS NULL
   OR pc.eliminado
   OR pc.id_empresa <> p.id_empresa
   OR LEFT(pc.codigo, 1) NOT IN ('1','2','3','4','5','6','7')
   OR (LEFT(pc.codigo, 1) = '7' AND CAST(pc.nivel AS VARCHAR) <> '5')
ORDER BY ac.fecha_asiento, ac.id;


-- 3) Asientos con líneas ELIMINADAS (una línea borrada deja el asiento cojo) -----
WITH p AS (
    SELECT e.id AS id_empresa, CAST(e.tipo_ambiente AS VARCHAR(1)) AS amb,
           DATE '2026-01-01' AS desde, DATE '2026-12-31' AS hasta      -- ← rango del reporte
    FROM empresas e
    WHERE e.ruc = '1711548931001' AND e.eliminado = false               -- ← RUC
)
SELECT ac.id_empresa, ac.id AS id_asiento, ac.fecha_asiento::date AS fecha, ac.numero_comprobante,
       ac.modulo_origen, ad.id AS id_linea, ad.debe, ad.haber
FROM p
JOIN asientos_contables_cabecera ac
     ON ac.id_empresa = p.id_empresa AND ac.eliminado = false
    AND ac.estado = 'contabilizado' AND CAST(ac.tipo_ambiente AS VARCHAR(1)) = p.amb
    AND ac.fecha_asiento BETWEEN p.desde AND p.hasta + TIME '23:59:59'
JOIN asientos_contables_detalle ad ON ad.id_asiento = ac.id AND ad.eliminado = true
ORDER BY ac.fecha_asiento, ac.id;


-- 4) Comprobación: debe dar la misma diferencia que el aviso del reporte ---------
--    diferencia_esperada = Σ descuadres (1) − Σ líneas excluidas (2)
WITH p AS (
    SELECT e.id AS id_empresa, CAST(e.tipo_ambiente AS VARCHAR(1)) AS amb,
           DATE '2026-01-01' AS desde, DATE '2026-12-31' AS hasta      -- ← rango del reporte
    FROM empresas e
    WHERE e.ruc = '1711548931001' AND e.eliminado = false               -- ← RUC
),
lineas AS (
    SELECT ac.id_empresa, ad.debe - ad.haber AS neto,
           (pc.id IS NOT NULL AND NOT pc.eliminado AND pc.id_empresa = p.id_empresa
            AND (LEFT(pc.codigo, 1) IN ('1','2','3','4','5','6')
                 OR (LEFT(pc.codigo, 1) = '7' AND CAST(pc.nivel AS VARCHAR) = '5'))) AS contada
    FROM p
    JOIN asientos_contables_cabecera ac
         ON ac.id_empresa = p.id_empresa AND ac.eliminado = false
        AND ac.estado = 'contabilizado' AND CAST(ac.tipo_ambiente AS VARCHAR(1)) = p.amb
        AND ac.fecha_asiento BETWEEN p.desde AND p.hasta + TIME '23:59:59'
    JOIN asientos_contables_detalle ad ON ad.id_asiento = ac.id AND ad.eliminado = false
    LEFT JOIN plan_cuentas pc ON pc.id = ad.id_cuenta_contable
)
SELECT id_empresa,
       ROUND(SUM(neto), 2)                                AS descuadre_total_asientos,
       ROUND(SUM(neto) FILTER (WHERE NOT contada), 2)     AS neto_lineas_excluidas,
       ROUND(SUM(neto) FILTER (WHERE contada), 2)         AS diferencia_esperada_del_balance
FROM lineas
GROUP BY id_empresa
ORDER BY id_empresa;
