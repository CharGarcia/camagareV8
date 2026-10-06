-- ============================================================================
-- Notas de crédito MIGRADAS que quedaron en el ambiente equivocado (pruebas)
-- ----------------------------------------------------------------------------
-- Contexto:
--   La migración de Notas de crédito tomaba el ambiente del sistema anterior
--   (encabezado_nc.ambiente: '2' = producción, cualquier otro = pruebas). El
--   sistema anterior deja ambiente = 0 en algunas NC (incluso autorizadas), así
--   que se migraban como PRUEBAS y no aparecían en el listado de una empresa en
--   producción (caso: 001-101-000002406 de ASAMED, empresa 23). El código ya
--   está corregido (usa el ambiente de la empresa); este script corrige lo YA
--   migrado sin volver a migrar.
--
-- Qué hace:
--   Pasa al ambiente de su EMPRESA las notas de crédito que vinieron de la
--   migración (migracion_mysql_map, entidad 'notas_credito') y tienen otro
--   ambiente. Solo toca tipo_ambiente, updated_at y updated_by. Las NC creadas
--   en este sistema no se tocan. Muestra las filas corregidas (RETURNING).
--   Idempotente: la 2.ª vez no encuentra nada.
--
-- Revisión previa (en producción, 2026-10-06): 10 NC en las empresas 23, 101,
-- 122 y 125 (la 003-002-000000107 de la 125 tiene dos filas en el mapa).
-- Ojo: 5 están en BORRADOR (23: 001-101-000001864 de 2023; 122 y 125). Al
-- quedar en producción se ven en el listado y podrían enviarse al SRI.
--
-- Reversible: guardar el resultado (columna id) y, si hiciera falta,
--   UPDATE notas_credito_cabecera SET tipo_ambiente = '1' WHERE id IN (...);
-- ============================================================================

UPDATE notas_credito_cabecera n
   SET tipo_ambiente = CAST(e.tipo_ambiente AS varchar),
       updated_at    = now(),
       updated_by    = 2                       -- << usuario que ejecuta (producción: 2)
  FROM empresas e
 WHERE e.id = n.id_empresa
   AND n.eliminado = false
   AND n.tipo_ambiente IS DISTINCT FROM CAST(e.tipo_ambiente AS varchar)
   AND EXISTS (SELECT 1 FROM migracion_mysql_map m
                WHERE m.id_empresa = n.id_empresa AND m.entidad = 'notas_credito' AND m.id_destino = n.id)
RETURNING n.id, n.id_empresa,
          n.establecimiento || '-' || n.punto_emision || '-' || n.secuencial AS numero,
          n.estado, n.importe_total, n.tipo_ambiente AS ambiente_nuevo;

-- Comprobación (ejecutar aparte; debe devolver 0 filas):
-- SELECT n.id_empresa, n.establecimiento || '-' || n.punto_emision || '-' || n.secuencial
--   FROM notas_credito_cabecera n JOIN empresas e ON e.id = n.id_empresa
--   JOIN migracion_mysql_map m ON m.id_empresa = n.id_empresa AND m.entidad = 'notas_credito' AND m.id_destino = n.id
--  WHERE n.eliminado = false AND n.tipo_ambiente IS DISTINCT FROM CAST(e.tipo_ambiente AS varchar);
