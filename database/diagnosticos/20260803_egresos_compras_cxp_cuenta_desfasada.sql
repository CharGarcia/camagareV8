-- Diagnóstico: egresos que pagaron facturas de COMPRA/LIQUIDACIÓN usando la cuenta VIEJA
-- (equivocada) en vez de la Cuenta por Pagar real. Contexto del bug (2026-08-03):
--
--   AsientoBuilderService::generarAsientoEgreso() debitaba, para pagos de cartera (COMPRA/
--   LIQUIDACION), la cuenta configurada en el CONCEPTO de Ingresos/Egresos
--   (empresa_opciones_ingreso_egreso.id_cuenta_contable, comportamiento='COMPRA') en vez de
--   la cuenta que REALMENTE se acreditó al registrar la compra/liquidación (asientos_tipo.codigo
--   = 'PORPAGARFACTURACOMPRA', concepto 'adquisiciones_compras', resuelta vía
--   asientos_programados). Si en una empresa ambas cuentas ya eran la misma, no hay ningún
--   desfase real (el resultado coincidía por casualidad). El fix ya corregido en el código solo
--   afecta egresos NUEVOS o REGENERADOS desde que se despliegue -- los ya guardados con la
--   cuenta vieja quedan tal cual hasta que se corrijan a mano / con Auditoría Contable.
--
-- Es de SOLO LECTURA (ningún UPDATE/DELETE). Corre las 3 consultas tal cual, sin reemplazar
-- nada -- barren TODAS las empresas de la instalación.

-- ─────────────────────────────────────────────────────────────────────────────
-- 1) Por empresa: ¿la cuenta del concepto "facturas de compras" (comportamiento=COMPRA)
--    coincide con la cuenta real de Cuentas por Pagar (PORPAGARFACTURACOMPRA)?
--    "desfasada = true" => esa empresa SÍ tiene el problema; "false" o cuentas iguales => no.
-- ─────────────────────────────────────────────────────────────────────────────
WITH cuenta_concepto AS (
    SELECT o.id_empresa, o.id AS id_concepto, o.nombre AS concepto_nombre,
           o.id_cuenta_contable AS id_cuenta_concepto
    FROM empresa_opciones_ingreso_egreso o
    WHERE o.comportamiento = 'COMPRA' AND o.aplica_egresos = TRUE AND o.eliminado = FALSE
),
cuenta_real AS (
    SELECT ap.id_empresa, ap.id_cuenta AS id_cuenta_real
    FROM asientos_programados ap
    JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo
    WHERE at.codigo = 'PORPAGARFACTURACOMPRA' AND ap.eliminado = false
)
SELECT cc.id_empresa,
       e.nombre                                            AS empresa,
       cc.concepto_nombre,
       cc.id_cuenta_concepto, pcc.codigo AS cuenta_concepto_codigo, pcc.nombre AS cuenta_concepto_nombre,
       cr.id_cuenta_real,     pcr.codigo AS cuenta_real_codigo,    pcr.nombre AS cuenta_real_nombre,
       (cc.id_cuenta_concepto IS DISTINCT FROM cr.id_cuenta_real) AS desfasada
FROM cuenta_concepto cc
LEFT JOIN cuenta_real cr   ON cr.id_empresa = cc.id_empresa
LEFT JOIN empresas e       ON e.id = cc.id_empresa
LEFT JOIN plan_cuentas pcc ON pcc.id = cc.id_cuenta_concepto
LEFT JOIN plan_cuentas pcr ON pcr.id = cr.id_cuenta_real
ORDER BY cc.id_empresa;

-- ─────────────────────────────────────────────────────────────────────────────
-- 2) Tamaño del problema por empresa: cuántos egresos y cuánto dinero quedaron debitando
--    la cuenta VIEJA (la del concepto) cuando esta es distinta de la cuenta real de CxP.
--    Esto es lo que habría que corregir (reasiento) por empresa.
-- ─────────────────────────────────────────────────────────────────────────────
WITH cuenta_concepto AS (
    SELECT o.id_empresa, o.id_cuenta_contable AS id_cuenta_concepto
    FROM empresa_opciones_ingreso_egreso o
    WHERE o.comportamiento = 'COMPRA' AND o.aplica_egresos = TRUE AND o.eliminado = FALSE
),
cuenta_real AS (
    SELECT ap.id_empresa, ap.id_cuenta AS id_cuenta_real
    FROM asientos_programados ap
    JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo
    WHERE at.codigo = 'PORPAGARFACTURACOMPRA' AND ap.eliminado = false
),
egresos_cartera AS (
    SELECT DISTINCT ed.id_egreso
    FROM egresos_detalle ed
    WHERE ed.tipo_documento IN ('COMPRA','LIQUIDACION') AND ed.eliminado = FALSE
)
SELECT ec2.id_empresa,
       e.nombre AS empresa,
       COUNT(DISTINCT ac.id)              AS asientos_afectados,
       COUNT(DISTINCT ac.id_referencia_origen) AS egresos_afectados,
       SUM(ad.debe)                       AS monto_total_mal_contabilizado,
       MIN(ac.fecha_asiento)              AS primera_fecha,
       MAX(ac.fecha_asiento)              AS ultima_fecha
FROM egresos_cartera ec
JOIN egresos_cabecera ec2               ON ec2.id = ec.id_egreso
                                        AND ec2.eliminado = false AND ec2.estado != 'anulado'
JOIN cuenta_concepto cc                 ON cc.id_empresa = ec2.id_empresa
LEFT JOIN cuenta_real cr                 ON cr.id_empresa = ec2.id_empresa
JOIN asientos_contables_cabecera ac      ON ac.modulo_origen = 'egreso'
                                        AND ac.id_referencia_origen = ec2.id
                                        AND ac.id_empresa = ec2.id_empresa
                                        AND ac.eliminado = false AND ac.estado != 'anulado'
JOIN asientos_contables_detalle ad       ON ad.id_asiento = ac.id
                                        AND ad.eliminado = false AND ad.debe > 0
                                        AND ad.id_cuenta_contable = cc.id_cuenta_concepto
LEFT JOIN empresas e                     ON e.id = ec2.id_empresa
WHERE (cr.id_cuenta_real IS NULL OR cc.id_cuenta_concepto IS DISTINCT FROM cr.id_cuenta_real)
GROUP BY ec2.id_empresa, e.nombre
ORDER BY monto_total_mal_contabilizado DESC;

-- ─────────────────────────────────────────────────────────────────────────────
-- 3) Detalle egreso por egreso (para armar la lista de reasientos/correcciones puntuales).
--    Mismo filtro que la consulta 2, sin agrupar -- una fila por egreso afectado.
-- ─────────────────────────────────────────────────────────────────────────────
WITH cuenta_concepto AS (
    SELECT o.id_empresa, o.id_cuenta_contable AS id_cuenta_concepto
    FROM empresa_opciones_ingreso_egreso o
    WHERE o.comportamiento = 'COMPRA' AND o.aplica_egresos = TRUE AND o.eliminado = FALSE
),
cuenta_real AS (
    SELECT ap.id_empresa, ap.id_cuenta AS id_cuenta_real
    FROM asientos_programados ap
    JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo
    WHERE at.codigo = 'PORPAGARFACTURACOMPRA' AND ap.eliminado = false
),
egresos_cartera AS (
    SELECT DISTINCT ed.id_egreso
    FROM egresos_detalle ed
    WHERE ed.tipo_documento IN ('COMPRA','LIQUIDACION') AND ed.eliminado = FALSE
)
SELECT ec2.id_empresa, e.nombre AS empresa,
       ec2.id AS id_egreso, ec2.numero_egreso,
       ac.id AS id_asiento, ac.fecha_asiento,
       pcc.codigo AS cuenta_usada_codigo, pcc.nombre AS cuenta_usada_nombre,
       pcr.codigo AS cuenta_correcta_codigo, pcr.nombre AS cuenta_correcta_nombre,
       ad.debe AS monto
FROM egresos_cartera ec
JOIN egresos_cabecera ec2               ON ec2.id = ec.id_egreso
                                        AND ec2.eliminado = false AND ec2.estado != 'anulado'
JOIN cuenta_concepto cc                 ON cc.id_empresa = ec2.id_empresa
LEFT JOIN cuenta_real cr                 ON cr.id_empresa = ec2.id_empresa
JOIN asientos_contables_cabecera ac      ON ac.modulo_origen = 'egreso'
                                        AND ac.id_referencia_origen = ec2.id
                                        AND ac.id_empresa = ec2.id_empresa
                                        AND ac.eliminado = false AND ac.estado != 'anulado'
JOIN asientos_contables_detalle ad       ON ad.id_asiento = ac.id
                                        AND ad.eliminado = false AND ad.debe > 0
                                        AND ad.id_cuenta_contable = cc.id_cuenta_concepto
LEFT JOIN empresas e                     ON e.id = ec2.id_empresa
LEFT JOIN plan_cuentas pcc               ON pcc.id = cc.id_cuenta_concepto
LEFT JOIN plan_cuentas pcr               ON pcr.id = cr.id_cuenta_real
WHERE (cr.id_cuenta_real IS NULL OR cc.id_cuenta_concepto IS DISTINCT FROM cr.id_cuenta_real)
ORDER BY ec2.id_empresa, ac.fecha_asiento;
