-- =============================================================================
-- Diagnóstico (SOLO LECTURA): ¿por qué el asiento de estos ingresos / egresos
-- NO se regenera al volver a guardar el documento?
--   QUIMIROSBURG (RUC 1791400135001): egresos 002-101-202609019, -014, -010, -012
--   ASAMED       (RUC 1792708389001): ingresos 001-101-000024851, -24876, -24877, -25088
--
-- Columnas clave:
--   editado_manual = true   -> alguien editó y guardó el asiento a mano (pestaña
--                              «Asiento contable»). Las regeneraciones automáticas
--                              lo respetan y NO lo rehacen. Solución: en esa
--                              pestaña pulsar «Restaurar asiento automático».
--   periodo_cerrado = true  -> el período contable está cerrado: no se puede
--                              editar el documento ni rehacer su asiento.
--   asiento_actualizado     -> última modificación del asiento. Si es ANTERIOR a
--   documento_actualizado      la del documento, el guardado no tocó el asiento
--                              (editado a mano, período cerrado, o el servidor
--                              todavía corre el código anterior: git pull + reiniciar
--                              PHP/Apache).
-- =============================================================================

WITH docs AS (
    SELECT 'EGRESO'::text AS tipo, e.id_empresa, e.id, e.numero_egreso AS numero,
           e.fecha_emision, e.updated_at AS documento_actualizado, 'egreso'::text AS modulo
      FROM egresos_cabecera e
      JOIN empresas emp ON emp.id = e.id_empresa
     WHERE emp.ruc = '1791400135001' AND e.eliminado = false
       AND e.numero_egreso IN ('002-101-202609019', '002-101-202609014',
                               '002-101-202609010', '002-101-202609012')
    UNION ALL
    SELECT 'INGRESO', i.id_empresa, i.id, i.numero_ingreso,
           i.fecha_emision, i.updated_at, 'ingreso'
      FROM ingresos_cabecera i
      JOIN empresas emp ON emp.id = i.id_empresa
     WHERE emp.ruc = '1792708389001' AND i.eliminado = false
       AND i.numero_ingreso IN ('001-101-000024851', '001-101-000024876',
                                '001-101-000024877', '001-101-000025088')
)
SELECT d.tipo,
       d.numero,
       d.fecha_emision,
       a.id                       AS id_asiento,
       a.editado_manual,
       EXISTS (SELECT 1 FROM periodos_contables p
                WHERE p.id_empresa = d.id_empresa AND p.eliminado = false
                  AND p.status = 0
                  AND d.fecha_emision BETWEEN p.fecha_inicial AND p.fecha_final) AS periodo_cerrado,
       d.documento_actualizado,
       a.updated_at               AS asiento_actualizado,
       a.created_at               AS asiento_creado
  FROM docs d
  LEFT JOIN asientos_contables_cabecera a
         ON a.modulo_origen = d.modulo
        AND a.id_referencia_origen = d.id
        AND a.id_empresa = d.id_empresa
        AND a.eliminado = false
        AND a.estado <> 'anulado'
 ORDER BY d.tipo, d.numero;
