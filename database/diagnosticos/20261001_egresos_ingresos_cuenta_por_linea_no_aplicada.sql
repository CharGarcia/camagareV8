-- =============================================================================
-- Diagnóstico (SOLO LECTURA): ingresos / egresos cuyo asiento NO usa la cuenta
-- contable elegida a mano en sus líneas de "Otros conceptos".
--
-- Causa (corregida 2026-10-01 en AsientoBuilderService::contrapartidaPorCuenta):
-- la cuenta por línea se guarda en egresos_detalle / ingresos_detalle
-- .id_cuenta_contable, pero el generador no la leía. Solo la tomaba del modal
-- al guardar; en los caminos que regeneran el asiento SIN el modal (anular un
-- cheque, sincronización de asientos / Estados Financieros) la buscaba en el
-- asiento anterior por descripción y, si no la encontraba (varias líneas con la
-- misma cuenta, o el asiento no existía), usaba la cuenta del CONCEPTO — en un
-- egreso que además paga compras, Cuentas por Pagar.
--
-- Qué lista: cada documento con al menos una línea manual cuya cuenta elegida
-- NO aparece en el lado contrapartida de su asiento vigente (Debe en egresos,
-- Haber en ingresos). Para corregirlos basta con regenerar su asiento: abrir el
-- documento y guardarlo, o regenerarlo desde Auditoría Contable.
--
-- Uso: pegar en pgAdmin y ejecutar. Cambiar el id_empresa en las dos CTE
-- (o quitar ese filtro para revisar todas las empresas).
-- =============================================================================

WITH egresos_afectados AS (
    SELECT 'EGRESO'::text                AS tipo,
           e.id_empresa,
           e.id                          AS id_documento,
           e.fecha_emision,
           d.descripcion,
           d.monto_pagado                AS monto,
           pc.codigo || ' - ' || pc.nombre AS cuenta_elegida,
           a.id                          AS id_asiento
      FROM egresos_cabecera e
      JOIN egresos_detalle d
        ON d.id_egreso = e.id
       AND d.eliminado = false
       AND d.tipo_documento = 'MANUAL'
       AND d.id_cuenta_contable IS NOT NULL
       AND d.monto_pagado > 0
      JOIN plan_cuentas pc ON pc.id = d.id_cuenta_contable
      JOIN asientos_contables_cabecera a
        ON a.modulo_origen = 'egreso'
       AND a.id_referencia_origen = e.id
       AND a.id_empresa = e.id_empresa
       AND a.eliminado = false
       AND a.estado <> 'anulado'
     WHERE e.eliminado = false
       AND e.id_empresa = 1          -- <== cambiar
       AND NOT EXISTS (
             SELECT 1
               FROM asientos_contables_detalle ad
              WHERE ad.id_asiento = a.id
                AND ad.eliminado = false
                AND ad.debe > 0
                AND ad.id_cuenta_contable = d.id_cuenta_contable
           )
),
ingresos_afectados AS (
    SELECT 'INGRESO'::text               AS tipo,
           i.id_empresa,
           i.id                          AS id_documento,
           i.fecha_emision,
           d.descripcion,
           d.monto_cobrado               AS monto,
           pc.codigo || ' - ' || pc.nombre AS cuenta_elegida,
           a.id                          AS id_asiento
      FROM ingresos_cabecera i
      JOIN ingresos_detalle d
        ON d.id_ingreso = i.id
       AND d.tipo_documento = 'OTRO'
       AND d.id_cuenta_contable IS NOT NULL
       AND d.monto_cobrado > 0
      JOIN plan_cuentas pc ON pc.id = d.id_cuenta_contable
      JOIN asientos_contables_cabecera a
        ON a.modulo_origen = 'ingreso'
       AND a.id_referencia_origen = i.id
       AND a.id_empresa = i.id_empresa
       AND a.eliminado = false
       AND a.estado <> 'anulado'
     WHERE i.eliminado = false
       AND i.id_empresa = 1          -- <== cambiar
       AND NOT EXISTS (
             SELECT 1
               FROM asientos_contables_detalle ad
              WHERE ad.id_asiento = a.id
                AND ad.eliminado = false
                AND ad.haber > 0
                AND ad.id_cuenta_contable = d.id_cuenta_contable
           )
)
SELECT * FROM egresos_afectados
UNION ALL
SELECT * FROM ingresos_afectados
ORDER BY tipo, fecha_emision DESC, id_documento DESC;
