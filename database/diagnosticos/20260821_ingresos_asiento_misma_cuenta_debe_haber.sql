-- Diagnostico: asientos de INGRESO generados con la MISMA cuenta en el Debe y en el Haber
-- (tipicamente la cuenta de la forma de cobro repetida en los dos lados). El asiento "cuadra"
-- --por eso se guardo sin avisar-- pero no dice nada: entra y sale de la misma cuenta.
--
-- No escribe nada: solo consulta. Se puede correr con el usuario de solo lectura.
-- Cubre TODAS las empresas a la vez (aunque el reporte venga de una, conviene ver el alcance).
--
-- Como leerlo:
--   * Consulta 1 -> EFECTO: que asientos de ingreso ya salieron con la cuenta repetida.
--   * Consulta 2 -> CAUSA principal: concepto y forma de cobro apuntando a la MISMA cuenta.
--   * Consulta 3 -> CAUSA secundaria: concepto de ingreso con cuenta de Caja/Bancos (clase 1.1).
--   * Consulta 4 -> Falsos positivos legitimos: cruces de anticipo (ver nota en esa consulta).
--   * Consulta 5 -> Alcance en dinero por empresa.
--
-- Recordatorio de como resuelve las cuentas el motor (AsientoBuilderService::generarAsientoIngreso):
--   DEBE  = forma de cobro -> COALESCE(asientos_programados[forma_cobro].id_cuenta,
--                                      empresa_formas_pago.id_cuenta_contable)
--   HABER = (a) cartera: copia el Debe del asiento de la factura/recibo cobrado, o
--           (b) cuenta del concepto -> COALESCE(asientos_programados[opcion_ingreso].id_cuenta,
--                                               empresa_opciones_ingreso_egreso.id_cuenta_contable)
--   Si (a) o (b) resuelven la misma cuenta que la forma de cobro, el asiento sale espejado.


-- ---------------------------------------------------------------------------
-- 1) EFECTO: asientos de ingreso donde una misma cuenta tiene Debe y Haber a la vez.
--    'monto_espejado' es lo que se anula solo dentro del propio asiento.
-- ---------------------------------------------------------------------------
SELECT c.id_empresa,
       e.nombre_comercial,
       c.id                             AS id_asiento,
       c.fecha_asiento,
       c.concepto,
       i.numero_ingreso,
       i.estado                         AS estado_ingreso,
       pc.codigo                        AS cuenta_codigo,
       pc.nombre                        AS cuenta_nombre,
       SUM(d.debe)                      AS total_debe,
       SUM(d.haber)                     AS total_haber,
       LEAST(SUM(d.debe), SUM(d.haber)) AS monto_espejado
FROM asientos_contables_cabecera c
JOIN asientos_contables_detalle d ON d.id_asiento = c.id AND d.eliminado = false
JOIN plan_cuentas pc              ON pc.id = d.id_cuenta_contable
JOIN empresas e                   ON e.id = c.id_empresa
LEFT JOIN ingresos_cabecera i     ON i.id = c.id_referencia_origen
WHERE c.modulo_origen = 'ingreso'
  AND c.eliminado = false
  AND c.estado <> 'anulado'
GROUP BY c.id_empresa, e.nombre_comercial, c.id, c.fecha_asiento, c.concepto,
         i.numero_ingreso, i.estado, pc.codigo, pc.nombre
HAVING SUM(d.debe) > 0 AND SUM(d.haber) > 0
ORDER BY c.id_empresa, c.fecha_asiento DESC, c.id DESC;


-- ---------------------------------------------------------------------------
-- 2) CAUSA principal: conceptos de INGRESO y formas de COBRO que resuelven la MISMA cuenta.
--    Si aqui aparece una fila, todo ingreso que combine ese concepto con esa forma va a salir
--    espejado. Se corrige cambiando la cuenta del CONCEPTO (no la de la forma de cobro).
-- ---------------------------------------------------------------------------
SELECT o.id_empresa,
       e.nombre_comercial,
       o.nombre                    AS concepto,
       o.comportamiento,
       f.nombre                    AS forma_cobro,
       pc.codigo                   AS cuenta_compartida_codigo,
       pc.nombre                   AS cuenta_compartida_nombre,
       CASE WHEN UPPER(COALESCE(o.comportamiento, '')) = 'ANTICIPO_CLIENTE'
            THEN 'legitimo (cruce de anticipo)'
            ELSE '>>> REVISAR <<<' END AS veredicto
FROM empresa_opciones_ingreso_egreso o
JOIN empresas e ON e.id = o.id_empresa
LEFT JOIN asientos_programados apo
       ON apo.id_referencia = o.id AND apo.tipo_referencia = 'opcion_ingreso'
      AND apo.id_empresa = o.id_empresa AND apo.eliminado = false
JOIN empresa_formas_pago f
       ON f.id_empresa = o.id_empresa AND f.eliminado = false AND f.activo = true
      AND (f.aplica_en = 'AMBAS' OR f.aplica_en = 'INGRESO')
LEFT JOIN asientos_programados apf
       ON apf.id_referencia = f.id AND apf.tipo_referencia = 'forma_cobro'
      AND apf.id_empresa = f.id_empresa AND apf.eliminado = false
JOIN plan_cuentas pc ON pc.id = COALESCE(apo.id_cuenta, o.id_cuenta_contable)
WHERE o.eliminado = false
  AND UPPER(o.estado) = 'ACTIVO'
  AND o.aplica_ingresos = true
  AND COALESCE(apo.id_cuenta, o.id_cuenta_contable) IS NOT NULL
  AND COALESCE(apo.id_cuenta, o.id_cuenta_contable) = COALESCE(apf.id_cuenta, f.id_cuenta_contable)
ORDER BY o.id_empresa, o.nombre, f.nombre;


-- ---------------------------------------------------------------------------
-- 3) CAUSA secundaria: concepto de INGRESO apuntando a Caja/Bancos (disponible).
--    Un concepto de ingreso debe ser de ingreso (4), pasivo (2) o cuenta por cobrar (1.1.03...),
--    nunca el disponible: ese lado ya lo pone la forma de cobro.
-- ---------------------------------------------------------------------------
SELECT o.id_empresa,
       e.nombre_comercial,
       o.nombre                    AS concepto,
       o.comportamiento,
       pc.codigo                   AS cuenta_codigo,
       pc.nombre                   AS cuenta_nombre
FROM empresa_opciones_ingreso_egreso o
JOIN empresas e ON e.id = o.id_empresa
LEFT JOIN asientos_programados apo
       ON apo.id_referencia = o.id AND apo.tipo_referencia = 'opcion_ingreso'
      AND apo.id_empresa = o.id_empresa AND apo.eliminado = false
JOIN plan_cuentas pc ON pc.id = COALESCE(apo.id_cuenta, o.id_cuenta_contable)
WHERE o.eliminado = false
  AND UPPER(o.estado) = 'ACTIVO'
  AND o.aplica_ingresos = true
  AND LEFT(pc.codigo, 6) IN ('1.1.01', '1.1.02')
ORDER BY o.id_empresa, o.nombre;


-- ---------------------------------------------------------------------------
-- 4) Falsos positivos legitimos: ingresos de ANTICIPO cobrados con una forma de anticipo.
--    Ahi la cuenta repetida es intencional (ConfiguracionContableController::
--    propagarCuentaAnticipoAFormas propaga la cuenta del concepto a la forma de anticipo).
--    Sirve para descontarlos de la consulta 1 antes de sacar conclusiones.
-- ---------------------------------------------------------------------------
SELECT c.id_empresa,
       COUNT(DISTINCT c.id) AS asientos_anticipo
FROM asientos_contables_cabecera c
JOIN ingresos_cabecera i               ON i.id = c.id_referencia_origen
JOIN empresa_opciones_ingreso_egreso o ON o.id = i.id_ingreso_concepto
WHERE c.modulo_origen = 'ingreso'
  AND c.eliminado = false
  AND c.estado <> 'anulado'
  AND UPPER(COALESCE(o.comportamiento, '')) = 'ANTICIPO_CLIENTE'
GROUP BY c.id_empresa
ORDER BY c.id_empresa;


-- ---------------------------------------------------------------------------
-- 5) ALCANCE en dinero por empresa (cuanto valor esta espejado dentro de asientos de ingreso).
-- ---------------------------------------------------------------------------
SELECT x.id_empresa,
       e.nombre_comercial,
       COUNT(DISTINCT x.id_asiento) AS asientos_afectados,
       SUM(x.monto_espejado)        AS monto_espejado_total
FROM (
    SELECT c.id_empresa,
           c.id AS id_asiento,
           LEAST(SUM(d.debe), SUM(d.haber)) AS monto_espejado
    FROM asientos_contables_cabecera c
    JOIN asientos_contables_detalle d ON d.id_asiento = c.id AND d.eliminado = false
    WHERE c.modulo_origen = 'ingreso'
      AND c.eliminado = false
      AND c.estado <> 'anulado'
    GROUP BY c.id_empresa, c.id, d.id_cuenta_contable
    HAVING SUM(d.debe) > 0 AND SUM(d.haber) > 0
) x
JOIN empresas e ON e.id = x.id_empresa
GROUP BY x.id_empresa, e.nombre_comercial
ORDER BY monto_espejado_total DESC;
