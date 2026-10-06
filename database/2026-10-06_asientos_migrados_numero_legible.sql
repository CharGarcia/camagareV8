-- =============================================================================
-- Asientos migrados desde MySQL: número de comprobante legible para los asientos
-- MANUALES del sistema anterior (DIARIO, BALANCE_INICIAL).
--
-- El sistema viejo asignaba a esos asientos un codigo_unico aleatorio de 20
-- caracteres ('sJCe1fMeygv5Xdx8STC3'), que la migración copiaba tal cual como
-- numero_comprobante. En Mayores / Asientos ese código no identifica nada.
-- Se reemplaza por TIPO-id_diario (DIARIO-682949, APERTURA-1234): el id es el
-- que tenía el asiento en el sistema viejo (migracion_mysql_map.id_origen), así
-- que sigue siendo rastreable.
--
-- Los asientos de documentos (FAC145065, EGR67320, RETVEN65096...) NO se tocan:
-- su código ya es legible y es el que muestran otras pantallas de verificación.
--
-- No se usa el prefijo corto del numerador nuevo ('DI-', 'AP-'):
-- AsientoContableRepository::generarNumeroComprobante() toma el mayor 'DI-NNN'
-- de la empresa y seguiría contando desde el id viejo.
--
-- Mismo criterio que MigracionMysqlService::migrarContabilidad() (migración y
-- re-migración ya graban el número nuevo); este SQL solo corrige lo ya migrado.
-- Idempotente: la segunda corrida no encuentra filas.
-- Ejecutar en pgAdmin, una sola vez, en cada instalación.
-- =============================================================================

BEGIN;

-- Vista previa (opcional): cuántos asientos cambian por empresa.
-- SELECT ac.id_empresa, COUNT(*)
--   FROM asientos_contables_cabecera ac
--   JOIN migracion_mysql_map m ON m.entidad = 'contabilidad' AND m.id_destino = ac.id AND m.id_empresa = ac.id_empresa
--  WHERE ac.modulo_origen = 'migracion' AND ac.numero_comprobante !~ '^[A-Z]+[0-9]+$'
--  GROUP BY ac.id_empresa;

UPDATE asientos_contables_cabecera ac
   SET numero_comprobante = LEFT(
           CASE UPPER(ac.tipo_comprobante)
               WHEN 'BALANCE_INICIAL'     THEN 'APERTURA'
               WHEN 'COMPRAS_SERVICIOS'   THEN 'COMPRAS'
               WHEN 'RECIBOS'             THEN 'VENTAS'
               WHEN 'NC_VENTAS'           THEN 'VENTAS'
               WHEN 'ROL_PAGOS'           THEN 'NOMINA'
               ELSE UPPER(ac.tipo_comprobante)
           END || '-' || m.id_origen, 50),
       updated_at = now()
  FROM migracion_mysql_map m
 WHERE m.entidad   = 'contabilidad'
   AND m.id_destino = ac.id
   AND m.id_empresa = ac.id_empresa
   AND ac.modulo_origen = 'migracion'
   AND ac.numero_comprobante !~ '^[A-Z]+[0-9]+$'       -- código de documento (FAC145065): se conserva
   AND ac.numero_comprobante !~ '^[A-Z_]+-[0-9]+$';    -- ya renumerado (DIARIO-682949): idempotencia

COMMIT;
