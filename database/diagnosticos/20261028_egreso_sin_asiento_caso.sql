-- =============================================================================
-- Diagnóstico (SOLO LECTURA): ¿por qué un egreso no tiene asiento y no aparece
-- como "pendiente de generar"?
-- Caso: RUC 1793196214001, egreso 001-001-000000492.
--
-- El aviso de pendientes (SincronizadorAsientosService) toma un egreso solo si:
--   eliminado = false, id_asiento_contable IS NULL, estado <> 'ANULADO',
--   NO es un egreso INSERTADO por la migración (migracion_mysql_map, vinculado IS NOT TRUE)
--   y NO está enlazado a un asiento migrado vivo.
-- Estas consultas dicen cuál de esas condiciones lo deja fuera.
--
-- En pgAdmin ejecutar UNA consulta a la vez (seleccionar el bloque y F5). Si una
-- falla, ejecutar ROLLBACK; antes de la siguiente.
-- =============================================================================


-- 1) El egreso y el asiento al que apunta (si apunta a uno) ---------------------
SELECT e.id_empresa, e.id AS id_egreso, e.numero_egreso, e.fecha_emision, e.estado, e.eliminado,
       e.tipo_ambiente, e.monto_total, e.id_asiento_contable,
       ac.modulo_origen AS asiento_modulo, ac.estado AS asiento_estado, ac.eliminado AS asiento_eliminado,
       ac.numero_comprobante, ac.fecha_asiento, ac.total_debe, ac.total_haber
FROM empresas em
JOIN egresos_cabecera e ON e.id_empresa = em.id
LEFT JOIN asientos_contables_cabecera ac ON ac.id = e.id_asiento_contable
WHERE em.ruc = '1793196214001'
  AND e.numero_egreso = '001-001-000000492';


-- 2) ¿Lo creó la migración? y ¿el histórico migrado trae su asiento? ------------
--    vinculado = false/NULL → lo INSERTÓ la migración (se excluye del aviso a propósito).
--    asiento_historico NULL → el sistema anterior no tenía asiento para este egreso
--    (o la Contabilidad no se volvió a migrar después de que se registró).
SELECT e.id AS id_egreso, m.id_origen AS id_egreso_sistema_anterior, m.vinculado, m.created_at AS migrado_el,
       a.id_destino AS asiento_historico, ac.numero_comprobante, ac.fecha_asiento,
       ac.estado AS asiento_estado, ac.eliminado AS asiento_eliminado, ac.total_debe
FROM empresas em
JOIN egresos_cabecera e ON e.id_empresa = em.id
LEFT JOIN migracion_mysql_map m ON m.entidad = 'egresos' AND m.id_destino = e.id AND m.id_empresa = e.id_empresa
LEFT JOIN migracion_mysql_map a ON a.entidad = 'contabilidad' AND a.id_empresa = e.id_empresa
                               AND a.clave_natural = 'EGR' || m.id_origen
LEFT JOIN asientos_contables_cabecera ac ON ac.id = a.id_destino
WHERE em.ruc = '1793196214001'
  AND e.numero_egreso = '001-001-000000492';


-- 3) Cualquier asiento (vivo o eliminado) que mencione a este egreso ------------
--    Por enlace del sistema (modulo_origen 'egreso') o por la referencia del histórico.
SELECT ac.id, ac.modulo_origen, ac.tipo_comprobante, ac.numero_comprobante, ac.fecha_asiento,
       ac.estado, ac.eliminado, ac.total_debe, LEFT(ac.concepto, 80) AS concepto
FROM empresas em
JOIN egresos_cabecera e ON e.id_empresa = em.id
JOIN asientos_contables_cabecera ac ON ac.id_empresa = e.id_empresa
WHERE em.ruc = '1793196214001'
  AND e.numero_egreso = '001-001-000000492'
  AND (   (ac.modulo_origen = 'egreso' AND ac.id_referencia_origen = e.id)
       OR ac.concepto ILIKE '%' || e.numero_egreso || '%'
       OR ac.concepto ILIKE '%000000492%')
ORDER BY ac.id;
