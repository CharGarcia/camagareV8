-- =====================================================================================
-- Blindaje: la base rechaza por sí misma dos retenciones de compra sobre el MISMO
-- documento de sustento del MISMO proveedor.
--
-- Hasta ahora la única defensa era una comprobación en PHP
-- (RetencionCompraService::validarUnicidadDocSustento). Al no llevar candado, dos
-- peticiones simultáneas la pasan las dos y quedan dos retenciones sobre la misma
-- factura — que es exactamente lo que el SRI no admite. Este índice cierra esa puerta
-- a nivel de motor, igual que uq_ingresos_secuencial_activo en ingresos/egresos.
--
-- La clave es **proveedor + tipo + número**, no solo el número: cada proveedor numera
-- sus facturas por su cuenta, así que la misma "001-001-000000123" de dos proveedores
-- distintos son dos documentos distintos y ambas se pueden retener.
--
-- ORDEN DE EJECUCIÓN:
--   1. 20260831_diagnostico_duplicados_retencion_doc_sustento.sql  ← ¿hay choques?
--   2. resolver los choques que aparezcan (anular o eliminar la retención sobrante)
--   3. este script
--
-- Qué queda FUERA del índice, igual que en la validación de la aplicación:
--   · retenciones eliminadas (eliminado = true) y anuladas → liberan el documento;
--   · documentos sin número o sin tipo de sustento (datos incompletos de migraciones);
--   · el ambiente (pruebas / producción) forma parte de la clave: las pruebas no
--     estorban a producción, igual que no aparecen en el listado.
-- =====================================================================================

-- Comprobación previa: si quedan duplicados, aborta con un mensaje que dice qué hacer,
-- en vez del error de índice duplicado, que no explica nada.
DO $$
DECLARE
    dup INTEGER;
BEGIN
    SELECT COUNT(*) INTO dup FROM (
        SELECT 1
          FROM retencion_compra_cabecera
         WHERE eliminado = false
           AND COALESCE(estado, '') <> 'anulada'
           AND id_proveedor IS NOT NULL
           AND COALESCE(num_doc_sustento, '')  <> ''
           AND COALESCE(tipo_doc_sustento, '') <> ''
         GROUP BY id_empresa, id_proveedor, tipo_doc_sustento, num_doc_sustento,
                  COALESCE(tipo_ambiente, '1')
        HAVING COUNT(*) > 1
    ) x;

    IF dup > 0 THEN
        RAISE EXCEPTION
            'Hay % documento(s) de sustento con más de una retención viva del mismo proveedor. Ejecute 20260831_diagnostico_duplicados_retencion_doc_sustento.sql para ver cuáles y resuélvalos antes de crear el índice.',
            dup;
    END IF;
END $$;

CREATE UNIQUE INDEX IF NOT EXISTS uq_retencion_compra_doc_sustento_activo
    ON retencion_compra_cabecera (
           id_empresa,
           id_proveedor,
           tipo_doc_sustento,
           num_doc_sustento,
           COALESCE(tipo_ambiente, '1')
       )
 WHERE eliminado = false
   AND COALESCE(estado, '') <> 'anulada'
   AND id_proveedor IS NOT NULL
   AND COALESCE(num_doc_sustento, '')  <> ''
   AND COALESCE(tipo_doc_sustento, '') <> '';

COMMENT ON INDEX uq_retencion_compra_doc_sustento_activo IS
    'Una sola retención viva por proveedor + tipo + número de documento de sustento, dentro del mismo ambiente. Respalda RetencionCompraService::validarUnicidadDocSustento.';

-- Para revertir:
--   DROP INDEX IF EXISTS uq_retencion_compra_doc_sustento_activo;
