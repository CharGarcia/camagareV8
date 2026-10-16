-- =============================================================================
-- Asientos de nómina DUPLICADOS en roles migrados del sistema anterior
-- (2026-10-15 · caso empresa RUC 1718513557001)
--
-- Qué pasó: la migración trae dos cosas del sistema viejo para el mismo mes:
--   a) el ROL (rol_cabecera, registrado en migracion_mysql_map con entidad 'roles_pago'), y
--   b) su asiento ROL_PAGOS del diario (asientos_contables_cabecera con
--      modulo_origen = 'migracion' y tipo_comprobante = 'nomina'), sin enlazar al rol.
-- El rol entraba con id_asiento NULL, así que el sincronizador de asientos le generaba
-- además un asiento propio (modulo_origen = 'nomina'; uno por empleado si la empresa tiene
-- cuentas por empleado). Resultado: la nómina del mes contabilizada dos veces, el asiento
-- general migrado + los individuales generados.
--
-- El código ya no lo repite (SincronizadorAsientosService excluye los roles migrados y
-- RolAsientoService se niega a contabilizarlos). Este script limpia lo ya generado.
--
-- PASO 1: diagnóstico (solo lectura).  PASO 2: limpieza.  PASO 3: revertir.
-- La empresa se elige por RUC en la CTE "par" de cada paso.
-- NO se toca rol_cabecera.updated_at (el sincronizador compara contra él).
-- =============================================================================


-- -----------------------------------------------------------------------------
-- PASO 1 · DIAGNÓSTICO (solo lectura). Un renglón por rol MENSUAL del período con
-- asiento de nómina en cualquiera de las dos formas.
--   origen_rol            : MIGRADO (insertado por la migración) / NATIVO o VINCULADO.
--   neto_rol              : total a pagar del rol.
--   debe_rol_sin_prov     : ingresos + aporte patronal del rol (el asiento generado suma
--                           además las provisiones, por eso puede ser algo mayor).
--   asientos_generados    : asientos vivos con modulo_origen = 'nomina' de ese rol.
--   asientos_migrados_mes : asientos vivos del diario viejo (ROL_PAGOS) en el mes del rol.
--   diagnostico           : DUPLICADO cuando hay de los dos.
-- -----------------------------------------------------------------------------
WITH par AS (SELECT id AS id_empresa FROM empresas WHERE ruc = '1718513557001' AND eliminado = false),
roles AS (
    SELECT rc.id, rc.id_empresa, rc.periodo_anio, rc.periodo_mes, rc.estado, rc.id_asiento,
           rc.total_neto,
           CASE WHEN EXISTS (SELECT 1 FROM migracion_mysql_map mm
                              WHERE mm.entidad = 'roles_pago' AND mm.id_destino = rc.id
                                AND mm.id_empresa = rc.id_empresa AND mm.vinculado IS NOT TRUE)
                THEN 'MIGRADO'
                WHEN EXISTS (SELECT 1 FROM migracion_mysql_map mm
                              WHERE mm.entidad = 'roles_pago' AND mm.id_destino = rc.id
                                AND mm.id_empresa = rc.id_empresa AND mm.vinculado)
                THEN 'VINCULADO' ELSE 'NATIVO' END AS origen_rol,
           (SELECT ROUND(SUM(rd.total_ingresos + rd.aporte_patronal), 2) FROM rol_detalle rd WHERE rd.id_rol = rc.id) AS debe_rol_sin_prov
      FROM rol_cabecera rc JOIN par ON par.id_empresa = rc.id_empresa
     WHERE rc.eliminado = false AND rc.tipo_rol = 'MENSUAL' AND rc.estado <> 'anulado'
),
gen AS (
    SELECT a.id_referencia_origen AS id_rol, COUNT(*) AS n, ROUND(SUM(a.total_debe), 2) AS debe,
           string_agg(a.id::text, ',' ORDER BY a.id) AS ids
      FROM asientos_contables_cabecera a JOIN par ON par.id_empresa = a.id_empresa
     WHERE a.modulo_origen = 'nomina' AND a.eliminado = false AND a.estado <> 'anulado'
     GROUP BY a.id_referencia_origen
),
mig AS (
    SELECT r.id AS id_rol, COUNT(a.id) AS n, ROUND(SUM(a.total_debe), 2) AS debe,
           string_agg(a.numero_comprobante, ',' ORDER BY a.id) AS numeros
      FROM roles r
      JOIN asientos_contables_cabecera a
        ON a.id_empresa = r.id_empresa AND a.eliminado = false AND a.estado <> 'anulado'
       AND a.modulo_origen = 'migracion' AND a.tipo_comprobante = 'nomina'
       AND a.fecha_asiento >= make_date(r.periodo_anio, r.periodo_mes, 1)
       AND a.fecha_asiento <  make_date(r.periodo_anio, r.periodo_mes, 1) + INTERVAL '1 month'
     GROUP BY r.id
)
SELECT r.id AS id_rol, r.periodo_mes || '/' || r.periodo_anio AS periodo, r.origen_rol, r.estado,
       r.total_neto AS neto_rol, r.debe_rol_sin_prov,
       COALESCE(g.n, 0) AS asientos_generados, g.debe AS debe_generados, g.ids AS ids_generados,
       COALESCE(m.n, 0) AS asientos_migrados_mes, m.debe AS debe_migrados, m.numeros AS numeros_migrados,
       CASE WHEN COALESCE(g.n, 0) > 0 AND COALESCE(m.n, 0) > 0 THEN 'DUPLICADO'
            WHEN COALESCE(g.n, 0) = 0 AND COALESCE(m.n, 0) = 0 THEN 'SIN ASIENTO'
            ELSE 'ok' END AS diagnostico
  FROM roles r
  LEFT JOIN gen g ON g.id_rol = r.id
  LEFT JOIN mig m ON m.id_rol = r.id
 ORDER BY r.periodo_anio, r.periodo_mes;


-- -----------------------------------------------------------------------------
-- PASO 2 · LIMPIEZA. Solo para roles MIGRADOS (no toca nativos ni vinculados) que tienen
-- asiento migrado en su mes Y asiento(s) generados: elimina (lógico) los GENERADOS, deja el
-- histórico migrado intacto, desvincula el rol (id_asiento = NULL) y lo devuelve de
-- 'contabilizado' a 'pagado' (estado con que lo dejó la migración). Un renglón por rol en
-- log_sistema (accion 'ANULAR_ASIENTO_NOMINA_DUP_MIG'), con los ids para revertir.
-- Los DUPLICADO de roles NATIVOS/VINCULADOS del PASO 1 se revisan a mano (hay que decidir
-- cuál de los dos asientos manda).
-- Correr dentro de BEGIN; … revisar … COMMIT;
-- -----------------------------------------------------------------------------
/*
BEGIN;
WITH par AS (SELECT id AS id_empresa, 1 AS id_usuario   -- <<< usuario que firma la limpieza
               FROM empresas WHERE ruc = '1718513557001' AND eliminado = false),
objetivo AS (
    SELECT rc.id AS id_rol, rc.id_empresa, rc.estado, rc.id_asiento, par.id_usuario,
           array_agg(a.id ORDER BY a.id) AS ids_asiento
      FROM rol_cabecera rc
      JOIN par ON par.id_empresa = rc.id_empresa
      JOIN migracion_mysql_map mm ON mm.entidad = 'roles_pago' AND mm.id_destino = rc.id
                                 AND mm.id_empresa = rc.id_empresa AND mm.vinculado IS NOT TRUE
      JOIN asientos_contables_cabecera a ON a.id_empresa = rc.id_empresa AND a.modulo_origen = 'nomina'
                                        AND a.id_referencia_origen = rc.id
                                        AND a.eliminado = false AND a.estado <> 'anulado'
     WHERE rc.eliminado = false AND rc.tipo_rol = 'MENSUAL'
       AND EXISTS (SELECT 1 FROM asientos_contables_cabecera am
                    WHERE am.id_empresa = rc.id_empresa AND am.eliminado = false AND am.estado <> 'anulado'
                      AND am.modulo_origen = 'migracion' AND am.tipo_comprobante = 'nomina'
                      AND am.fecha_asiento >= make_date(rc.periodo_anio, rc.periodo_mes, 1)
                      AND am.fecha_asiento <  make_date(rc.periodo_anio, rc.periodo_mes, 1) + INTERVAL '1 month')
     GROUP BY rc.id, rc.id_empresa, rc.estado, rc.id_asiento, par.id_usuario
),
det AS (
    UPDATE asientos_contables_detalle d
       SET eliminado = true, deleted_at = now(), deleted_by = o.id_usuario
      FROM objetivo o
     WHERE d.id_asiento = ANY (o.ids_asiento) AND d.eliminado = false
    RETURNING d.id
),
cab AS (
    UPDATE asientos_contables_cabecera a
       SET eliminado = true, deleted_at = now(), deleted_by = o.id_usuario
      FROM objetivo o
     WHERE a.id = ANY (o.ids_asiento) AND a.modulo_origen = 'nomina'   -- blindado: nunca 'migracion'
    RETURNING a.id
),
rol AS (
    UPDATE rol_cabecera rc
       SET id_asiento = NULL,
           estado = CASE WHEN rc.estado = 'contabilizado' THEN 'pagado' ELSE rc.estado END
      FROM objetivo o
     WHERE rc.id = o.id_rol
    RETURNING rc.id
)
INSERT INTO log_sistema (id_usuario, id_empresa, accion, tabla_afectada, id_registro,
                         datos_anteriores, datos_nuevos, ip_usuario, user_agent, created_at)
SELECT o.id_usuario, o.id_empresa, 'ANULAR_ASIENTO_NOMINA_DUP_MIG', 'rol_cabecera', o.id_rol,
       jsonb_build_object('estado', o.estado, 'id_asiento', o.id_asiento, 'ids_asiento', to_jsonb(o.ids_asiento)),
       jsonb_build_object('estado', CASE WHEN o.estado = 'contabilizado' THEN 'pagado' ELSE o.estado END,
                          'id_asiento', NULL,
                          'motivo', 'asientos generados sobre rol migrado; la nómina del mes ya está en el asiento migrado'),
       'sql', 'Limpieza asientos de nómina duplicados 2026-10-15', now()
  FROM objetivo o;
-- Revisar con el PASO 1 (los roles limpiados deben quedar 'ok') y luego:
-- COMMIT;   (o ROLLBACK;)
*/


-- -----------------------------------------------------------------------------
-- PASO 3 · REVERTIR el PASO 2, a partir de log_sistema.
-- -----------------------------------------------------------------------------
/*
BEGIN;
WITH lg AS (
    SELECT l.id_registro AS id_rol, l.datos_anteriores AS ant
      FROM log_sistema l
     WHERE l.accion = 'ANULAR_ASIENTO_NOMINA_DUP_MIG'
       AND l.id_empresa = (SELECT id FROM empresas WHERE ruc = '1718513557001' AND eliminado = false)
),
ids AS (SELECT lg.id_rol, (jsonb_array_elements_text(lg.ant->'ids_asiento'))::int AS id_asiento FROM lg),
c AS (UPDATE asientos_contables_cabecera a SET eliminado = false, deleted_at = NULL, deleted_by = NULL
        FROM ids WHERE a.id = ids.id_asiento RETURNING a.id),
-- Solo las líneas que borró el PASO 2 (mismo deleted_at que su cabecera), no las que ya
-- estaban eliminadas de regeneraciones anteriores.
d AS (UPDATE asientos_contables_detalle d SET eliminado = false, deleted_at = NULL, deleted_by = NULL
        FROM ids JOIN asientos_contables_cabecera a ON a.id = ids.id_asiento
       WHERE d.id_asiento = ids.id_asiento AND d.deleted_at = a.deleted_at RETURNING d.id)
UPDATE rol_cabecera rc
   SET id_asiento = NULLIF(lg.ant->>'id_asiento', '')::int, estado = lg.ant->>'estado'
  FROM lg WHERE rc.id = lg.id_rol;
-- COMMIT;
*/
