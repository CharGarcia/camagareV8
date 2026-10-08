-- ============================================================================
-- Egresos de NÓMINA migrados: mostrar el mes del rol en el detalle del egreso
-- ----------------------------------------------------------------------------
-- Contexto:
--   En los egresos migrados del sistema anterior, cada línea que paga un rol de
--   pagos o una quincena guardaba como número la llave técnica del sistema
--   anterior ('ROL_PAGOS22882', 'QUINCENA2722'), que la migración usa para
--   enlazarla con el rol migrado. El detalle del egreso muestra ese número, así
--   que no se veía de qué mes era el rol. Los egresos creados en este sistema
--   muestran 'Rol Mensual 11/2023' / 'Quincena 7/2026'.
--   El código ya está corregido (MigracionMysqlService::cruzarEgresosConRoles
--   pone el período al enlazar y corrige los ya enlazados); este script hace lo
--   mismo con lo YA migrado, sin volver a migrar Pagos (egresos).
--
-- Qué hace:
--   En las líneas ROL de egresos migrados (migracion_mysql_map, entidad
--   'egresos') que ya están enlazadas a su rol (id_referencia_documento) y aún
--   tienen la llave técnica como número, pone el período del rol:
--   'Rol Mensual MM/AAAA', 'Quincena MM/AAAA' o 'Semanal MM/AAAA'.
--   Las líneas NO enlazadas (su rol no se migró) conservan la llave: la pantalla
--   del egreso muestra en su lugar la descripción del sistema anterior
--   ('Rol de pagos 11-2023 NOMBRE'), que ya trae el mes.
--   Solo toca numero_documento. Idempotente (la 2.ª vez no encuentra nada).
--
-- Reversible: la llave vieja no se guarda en otra columna; para volver atrás,
-- re-ejecutar la migración de Pagos (egresos) de la empresa.
-- ============================================================================

UPDATE egresos_detalle d
   SET numero_documento = (CASE rc.tipo_rol WHEN 'MENSUAL' THEN 'Rol Mensual'
                                            WHEN 'QUINCENA' THEN 'Quincena'
                                            WHEN 'SEMANAL' THEN 'Semanal'
                                            ELSE 'Rol' END)
                          || ' ' || rc.periodo_mes || '/' || rc.periodo_anio
  FROM egresos_cabecera e, rol_detalle rd, rol_cabecera rc
 WHERE e.id = d.id_egreso
   AND d.tipo_documento = 'ROL'
   AND d.eliminado = false
   AND rd.id = d.id_referencia_documento
   AND rd.id_empresa = e.id_empresa
   AND rc.id = rd.id_rol
   AND d.numero_documento ~* '^(ROL[_ ]?PAGOS?|QUINCENA)[0-9]+$'
   AND EXISTS (SELECT 1 FROM migracion_mysql_map m
                WHERE m.id_empresa = e.id_empresa AND m.entidad = 'egresos' AND m.id_destino = e.id);

-- Comprobación (ejecutar aparte): líneas de nómina migradas que siguen con la
-- llave técnica, por empresa y si están enlazadas o no (las no enlazadas son
-- las de roles que no se migraron).
-- SELECT e.id_empresa, (d.id_referencia_documento IS NOT NULL) AS enlazada, COUNT(*)
--   FROM egresos_detalle d JOIN egresos_cabecera e ON e.id = d.id_egreso
--  WHERE d.tipo_documento = 'ROL' AND d.eliminado = false
--    AND d.numero_documento ~* '^(ROL[_ ]?PAGOS?|QUINCENA)[0-9]+$'
--  GROUP BY 1, 2 ORDER BY 1, 2;
