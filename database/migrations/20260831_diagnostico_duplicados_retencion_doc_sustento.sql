-- =====================================================================================
-- DIAGNÓSTICO (solo lectura): retenciones de compra que repiten el documento de sustento
-- del MISMO proveedor.
--
-- Ejecute esto ANTES de 20260831_unique_retencion_compra_doc_sustento.sql. Si devuelve
-- filas, el CREATE UNIQUE INDEX fallará con «Key (...) is duplicated»: hay que resolver
-- esos choques a mano (anular o eliminar la retención sobrante) antes de blindar la tabla.
--
-- No modifica nada. Se puede ejecutar en producción sin riesgo.
-- =====================================================================================

-- 1) Resumen: cuántos grupos duplicados hay y a qué empresas pertenecen.
SELECT r.id_empresa,
       e.nombre_comercial,
       COUNT(*) AS grupos_duplicados,
       SUM(r.repetidas) AS retenciones_implicadas
  FROM (
        SELECT id_empresa,
               id_proveedor,
               tipo_doc_sustento,
               num_doc_sustento,
               COALESCE(tipo_ambiente, '1') AS ambiente,
               COUNT(*) AS repetidas
          FROM retencion_compra_cabecera
         WHERE eliminado = false
           AND COALESCE(estado, '') <> 'anulada'
           AND id_proveedor IS NOT NULL
           AND COALESCE(num_doc_sustento, '')  <> ''
           AND COALESCE(tipo_doc_sustento, '') <> ''
         GROUP BY 1, 2, 3, 4, 5
        HAVING COUNT(*) > 1
       ) r
  LEFT JOIN empresas e ON e.id = r.id_empresa
 GROUP BY 1, 2
 ORDER BY 3 DESC;

-- 2) Detalle: cada retención implicada, para decidir cuál se conserva.
--    Criterio habitual: se conserva la AUTORIZADA (o la más antigua) y se elimina o anula
--    el borrador sobrante. Nunca borre físicamente: use eliminado = true / estado 'anulada'
--    desde la aplicación, para que quede el rastro en log_sistema.
SELECT r.id_empresa,
       p.razon_social                                   AS proveedor,
       p.identificacion                                 AS ruc_proveedor,
       r.tipo_doc_sustento,
       r.num_doc_sustento,
       COALESCE(r.tipo_ambiente, '1')                   AS ambiente,
       r.id                                             AS id_retencion,
       r.establecimiento || '-' || r.punto_emision || '-' || r.secuencial AS numero_retencion,
       to_char(r.fecha_emision, 'DD-MM-YYYY')           AS fecha_emision,
       r.estado,
       r.total_retenido,
       r.created_at
  FROM retencion_compra_cabecera r
  LEFT JOIN proveedores p ON p.id = r.id_proveedor
 WHERE r.eliminado = false
   AND COALESCE(r.estado, '') <> 'anulada'
   AND r.id_proveedor IS NOT NULL
   AND COALESCE(r.num_doc_sustento, '')  <> ''
   AND COALESCE(r.tipo_doc_sustento, '') <> ''
   AND (r.id_empresa, r.id_proveedor, r.tipo_doc_sustento, r.num_doc_sustento, COALESCE(r.tipo_ambiente, '1')) IN (
        SELECT id_empresa, id_proveedor, tipo_doc_sustento, num_doc_sustento, COALESCE(tipo_ambiente, '1')
          FROM retencion_compra_cabecera
         WHERE eliminado = false
           AND COALESCE(estado, '') <> 'anulada'
           AND id_proveedor IS NOT NULL
           AND COALESCE(num_doc_sustento, '')  <> ''
           AND COALESCE(tipo_doc_sustento, '') <> ''
         GROUP BY 1, 2, 3, 4, 5
        HAVING COUNT(*) > 1
       )
 ORDER BY r.id_empresa, r.id_proveedor, r.num_doc_sustento, r.id;
