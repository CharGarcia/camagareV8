-- Backfill: sincroniza empresas.establecimiento con el código del
-- establecimiento Activo en empresa_establecimiento, para las empresas que
-- hayan quedado desactualizadas antes de la corrección de sincronización
-- automática (ver docs/manual/config/empresas-sistema.md, v1.5).
--
-- Diagnóstico previo (solo lectura) — filas que van a cambiar:
-- SELECT e.id, e.ruc, e.establecimiento AS actual_en_empresas,
--        ee.codigo AS activo_en_establecimiento
--   FROM empresas e
--   JOIN empresa_establecimiento ee
--     ON ee.id_empresa = e.id AND ee.eliminado = false AND LOWER(ee.estado) = 'activo'
--  WHERE e.eliminado = false
--    AND e.establecimiento IS DISTINCT FROM ee.codigo;

UPDATE empresas e
   SET establecimiento = ee.codigo,
       updated_at = NOW()
  FROM empresa_establecimiento ee
 WHERE ee.id_empresa = e.id
   AND ee.eliminado = false
   AND LOWER(ee.estado) = 'activo'
   AND e.eliminado = false
   AND e.establecimiento IS DISTINCT FROM ee.codigo;
