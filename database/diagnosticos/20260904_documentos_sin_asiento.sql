-- Documentos operativos SIN asiento contable efectivo, migrados o no. Incluye tres casos:
--   1) id_asiento_contable vacío (nunca se generó o nunca se enlazó),
--   2) el asiento enlazado está eliminado (soft-delete),
--   3) el asiento enlazado está anulado.
-- Columna origen: nativo / migrado / nativo (enlazado por migración). Columna situacion: qué hacer.
--
-- Uso en pgAdmin: cambiar el id de empresa en la primera línea y ejecutar todo. Solo lectura.
-- Si la empresa no tiene migración, la tabla migracion_mysql_map puede no existir: en ese
-- caso borrar las líneas LEFT JOIN migracion_mysql_map y la columna origen.

WITH p AS (SELECT 33::int AS id_empresa),
docs AS (
    SELECT 'Facturas de Venta' AS modulo, d.id, d.establecimiento||'-'||d.punto_emision||'-'||d.secuencial AS numero, d.fecha_emision::date AS fecha, d.estado AS estado, d.importe_total AS total, d.eliminado,
           CASE WHEN mm.id IS NULL THEN 'nativo' WHEN mm.vinculado THEN 'nativo (enlazado por migración)' ELSE 'migrado' END AS origen,
           d.id_asiento_contable, a.estado AS asiento_estado, a.eliminado AS asiento_eliminado
      FROM p
      JOIN ventas_cabecera d ON d.id_empresa = p.id_empresa
      LEFT JOIN migracion_mysql_map mm ON mm.id_empresa = p.id_empresa AND mm.entidad = 'facturas' AND mm.id_destino = d.id
      LEFT JOIN asientos_contables_cabecera a ON a.id = d.id_asiento_contable
     WHERE d.id_asiento_contable IS NULL OR a.id IS NULL OR a.eliminado = true OR a.estado = 'anulado'
  UNION ALL
    SELECT 'Recibos de Venta' AS modulo, d.id, d.establecimiento||'-'||d.punto_emision||'-'||d.secuencial AS numero, d.fecha_emision::date AS fecha, d.estado AS estado, d.importe_total AS total, d.eliminado,
           CASE WHEN mm.id IS NULL THEN 'nativo' WHEN mm.vinculado THEN 'nativo (enlazado por migración)' ELSE 'migrado' END AS origen,
           d.id_asiento_contable, a.estado AS asiento_estado, a.eliminado AS asiento_eliminado
      FROM p
      JOIN recibos_venta_cabecera d ON d.id_empresa = p.id_empresa
      LEFT JOIN migracion_mysql_map mm ON mm.id_empresa = p.id_empresa AND mm.entidad = 'recibos' AND mm.id_destino = d.id
      LEFT JOIN asientos_contables_cabecera a ON a.id = d.id_asiento_contable
     WHERE d.id_asiento_contable IS NULL OR a.id IS NULL OR a.eliminado = true OR a.estado = 'anulado'
  UNION ALL
    SELECT 'Facturas de Compra' AS modulo, d.id, d.establecimiento_prov||'-'||d.punto_emision_prov||'-'||d.secuencial_prov AS numero, d.fecha_emision::date AS fecha, d.estado AS estado, d.importe_total AS total, d.eliminado,
           CASE WHEN mm.id IS NULL THEN 'nativo' WHEN mm.vinculado THEN 'nativo (enlazado por migración)' ELSE 'migrado' END AS origen,
           d.id_asiento_contable, a.estado AS asiento_estado, a.eliminado AS asiento_eliminado
      FROM p
      JOIN compras_cabecera d ON d.id_empresa = p.id_empresa
      LEFT JOIN migracion_mysql_map mm ON mm.id_empresa = p.id_empresa AND mm.entidad = 'compras' AND mm.id_destino = d.id
      LEFT JOIN asientos_contables_cabecera a ON a.id = d.id_asiento_contable
     WHERE d.id_asiento_contable IS NULL OR a.id IS NULL OR a.eliminado = true OR a.estado = 'anulado'
  UNION ALL
    SELECT 'Liquidaciones de Compra' AS modulo, d.id, d.establecimiento||'-'||d.punto_emision||'-'||d.secuencial AS numero, d.fecha_emision::date AS fecha, d.estado AS estado, d.importe_total AS total, d.eliminado,
           CASE WHEN mm.id IS NULL THEN 'nativo' WHEN mm.vinculado THEN 'nativo (enlazado por migración)' ELSE 'migrado' END AS origen,
           d.id_asiento_contable, a.estado AS asiento_estado, a.eliminado AS asiento_eliminado
      FROM p
      JOIN liquidaciones_cabecera d ON d.id_empresa = p.id_empresa
      LEFT JOIN migracion_mysql_map mm ON mm.id_empresa = p.id_empresa AND mm.entidad = 'liquidaciones' AND mm.id_destino = d.id
      LEFT JOIN asientos_contables_cabecera a ON a.id = d.id_asiento_contable
     WHERE d.id_asiento_contable IS NULL OR a.id IS NULL OR a.eliminado = true OR a.estado = 'anulado'
  UNION ALL
    SELECT 'Notas de Crédito' AS modulo, d.id, d.establecimiento||'-'||d.punto_emision||'-'||d.secuencial AS numero, d.fecha_emision::date AS fecha, d.estado AS estado, d.importe_total AS total, d.eliminado,
           CASE WHEN mm.id IS NULL THEN 'nativo' WHEN mm.vinculado THEN 'nativo (enlazado por migración)' ELSE 'migrado' END AS origen,
           d.id_asiento_contable, a.estado AS asiento_estado, a.eliminado AS asiento_eliminado
      FROM p
      JOIN notas_credito_cabecera d ON d.id_empresa = p.id_empresa
      LEFT JOIN migracion_mysql_map mm ON mm.id_empresa = p.id_empresa AND mm.entidad = 'notas_credito' AND mm.id_destino = d.id
      LEFT JOIN asientos_contables_cabecera a ON a.id = d.id_asiento_contable
     WHERE d.id_asiento_contable IS NULL OR a.id IS NULL OR a.eliminado = true OR a.estado = 'anulado'
  UNION ALL
    SELECT 'Retenciones en Ventas' AS modulo, d.id, d.establecimiento||'-'||d.punto_emision||'-'||d.secuencial AS numero, d.fecha_emision::date AS fecha, NULL::text AS estado, NULL::numeric AS total, d.eliminado,
           CASE WHEN mm.id IS NULL THEN 'nativo' WHEN mm.vinculado THEN 'nativo (enlazado por migración)' ELSE 'migrado' END AS origen,
           d.id_asiento_contable, a.estado AS asiento_estado, a.eliminado AS asiento_eliminado
      FROM p
      JOIN retencion_venta_cabecera d ON d.id_empresa = p.id_empresa
      LEFT JOIN migracion_mysql_map mm ON mm.id_empresa = p.id_empresa AND mm.entidad = 'retenciones_venta' AND mm.id_destino = d.id
      LEFT JOIN asientos_contables_cabecera a ON a.id = d.id_asiento_contable
     WHERE d.id_asiento_contable IS NULL OR a.id IS NULL OR a.eliminado = true OR a.estado = 'anulado'
  UNION ALL
    SELECT 'Retenciones en Compras' AS modulo, d.id, d.establecimiento||'-'||d.punto_emision||'-'||d.secuencial AS numero, d.fecha_emision::date AS fecha, d.estado AS estado, NULL::numeric AS total, d.eliminado,
           CASE WHEN mm.id IS NULL THEN 'nativo' WHEN mm.vinculado THEN 'nativo (enlazado por migración)' ELSE 'migrado' END AS origen,
           d.id_asiento_contable, a.estado AS asiento_estado, a.eliminado AS asiento_eliminado
      FROM p
      JOIN retencion_compra_cabecera d ON d.id_empresa = p.id_empresa
      LEFT JOIN migracion_mysql_map mm ON mm.id_empresa = p.id_empresa AND mm.entidad = 'retenciones_compra' AND mm.id_destino = d.id
      LEFT JOIN asientos_contables_cabecera a ON a.id = d.id_asiento_contable
     WHERE d.id_asiento_contable IS NULL OR a.id IS NULL OR a.eliminado = true OR a.estado = 'anulado'
  UNION ALL
    SELECT 'Ingresos' AS modulo, d.id, d.numero_ingreso::text AS numero, d.fecha_emision::date AS fecha, d.estado AS estado, NULL::numeric AS total, d.eliminado,
           CASE WHEN mm.id IS NULL THEN 'nativo' WHEN mm.vinculado THEN 'nativo (enlazado por migración)' ELSE 'migrado' END AS origen,
           d.id_asiento_contable, a.estado AS asiento_estado, a.eliminado AS asiento_eliminado
      FROM p
      JOIN ingresos_cabecera d ON d.id_empresa = p.id_empresa
      LEFT JOIN migracion_mysql_map mm ON mm.id_empresa = p.id_empresa AND mm.entidad = 'ingresos' AND mm.id_destino = d.id
      LEFT JOIN asientos_contables_cabecera a ON a.id = d.id_asiento_contable
     WHERE d.id_asiento_contable IS NULL OR a.id IS NULL OR a.eliminado = true OR a.estado = 'anulado'
  UNION ALL
    SELECT 'Egresos' AS modulo, d.id, d.numero_egreso::text AS numero, d.fecha_emision::date AS fecha, d.estado AS estado, NULL::numeric AS total, d.eliminado,
           CASE WHEN mm.id IS NULL THEN 'nativo' WHEN mm.vinculado THEN 'nativo (enlazado por migración)' ELSE 'migrado' END AS origen,
           d.id_asiento_contable, a.estado AS asiento_estado, a.eliminado AS asiento_eliminado
      FROM p
      JOIN egresos_cabecera d ON d.id_empresa = p.id_empresa
      LEFT JOIN migracion_mysql_map mm ON mm.id_empresa = p.id_empresa AND mm.entidad = 'egresos' AND mm.id_destino = d.id
      LEFT JOIN asientos_contables_cabecera a ON a.id = d.id_asiento_contable
     WHERE d.id_asiento_contable IS NULL OR a.id IS NULL OR a.eliminado = true OR a.estado = 'anulado'
  UNION ALL
    SELECT 'Consignaciones en Ventas' AS modulo, d.id, d.serie||'-'||d.secuencial AS numero, d.fecha_emision::date AS fecha, d.estado AS estado, d.total AS total, d.eliminado,
           CASE WHEN mm.id IS NULL THEN 'nativo' WHEN mm.vinculado THEN 'nativo (enlazado por migración)' ELSE 'migrado' END AS origen,
           d.id_asiento_contable, a.estado AS asiento_estado, a.eliminado AS asiento_eliminado
      FROM p
      JOIN consignaciones_ventas d ON d.id_empresa = p.id_empresa
      LEFT JOIN migracion_mysql_map mm ON mm.id_empresa = p.id_empresa AND mm.entidad = 'consignaciones' AND mm.id_destino = d.id
      LEFT JOIN asientos_contables_cabecera a ON a.id = d.id_asiento_contable
     WHERE d.id_asiento_contable IS NULL OR a.id IS NULL OR a.eliminado = true OR a.estado = 'anulado'
)
SELECT modulo, id, numero, fecha, estado, total, eliminado, origen,
       id_asiento_contable,
       CASE WHEN id_asiento_contable IS NULL THEN 'sin asiento'
            WHEN asiento_eliminado THEN 'asiento eliminado'
            WHEN asiento_estado = 'anulado' THEN 'asiento anulado'
            ELSE 'asiento inexistente (enlace roto)' END AS asiento,
       CASE
         WHEN eliminado THEN 'documento eliminado: no necesita asiento'
         WHEN UPPER(TRIM(COALESCE(estado,''))) IN ('ANULADO','ANULADA','RECHAZADA','PENDIENTE_APROBACION','BORRADOR') THEN 'estado sin efecto contable: no necesita asiento'
         WHEN origen = 'migrado' AND modulo = 'Consignaciones en Ventas' THEN 'consignación migrada: el sistema anterior no las contabilizaba, no necesita asiento'
         WHEN origen = 'migrado' THEN 'migrado sin asiento: re-migrar contabilidad y luego «Generar asientos a los migrados»'
         ELSE 'pendiente: lo genera la sincronización de Asientos Contables (si falla, revisar el aviso)'
       END AS situacion
  FROM docs
 ORDER BY modulo, fecha, id;

-- Resumen (ejecutar aparte, repitiendo el CTE de arriba hasta el paréntesis de cierre de docs):
-- SELECT modulo, origen,
--        CASE WHEN eliminado THEN 'eliminado' ELSE COALESCE(estado,'(sin estado)') END AS estado,
--        COUNT(*) AS documentos, SUM(total) AS total
--   FROM docs GROUP BY 1,2,3 ORDER BY 1,2,3;
