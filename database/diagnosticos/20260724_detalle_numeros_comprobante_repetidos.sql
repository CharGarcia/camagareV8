-- Detalle completo de TODAS las cabeceras involucradas en cualquier numero_comprobante
-- repetido (activo), en TODAS las empresas. Distingue automáticamente dos casos:
--
--  a) 'DUPLICADO REAL (mismo documento)': dentro del mismo numero_comprobante, hay filas
--     con el MISMO (modulo_origen, id_referencia_origen) -> el MISMO documento generó 2
--     asientos activos. Viola "un asiento por documento": hay que revisar el flujo de
--     guardado/regeneración de ese módulo específico.
--
--  b) 'Solo choque de numero_comprobante': cada fila del grupo tiene un (modulo_origen,
--     id_referencia_origen) distinto -> son documentos DISTINTOS que por un bug de
--     numeración (generarNumeroComprobante(), ya corregido para nuevos comprobantes)
--     terminaron con el mismo número visible. No hay riesgo contable real (cada uno
--     tiene sus propias cuentas correctas), pero el número mostrado es ambiguo.
--
-- Solo lectura. Si quieres acotar a una empresa, agrega "AND id_empresa = N" en el WITH.

WITH repetidos AS (
    SELECT id_empresa, numero_comprobante
    FROM asientos_contables_cabecera
    WHERE eliminado = false AND estado <> 'anulado'
    GROUP BY id_empresa, numero_comprobante
    HAVING COUNT(*) > 1
),
filas AS (
    SELECT
        ac.id_empresa,
        ac.numero_comprobante,
        ac.id             AS id_asiento,
        ac.modulo_origen,
        ac.id_referencia_origen,
        ac.tipo_comprobante,
        ac.estado,
        ac.fecha_asiento,
        ac.total_debe,
        ac.total_haber,
        ac.created_at,
        ac.created_by,
        COUNT(*) OVER (PARTITION BY ac.id_empresa, ac.numero_comprobante, ac.modulo_origen, ac.id_referencia_origen)
            AS mismo_doc_veces
    FROM asientos_contables_cabecera ac
    JOIN repetidos r
         ON r.id_empresa = ac.id_empresa AND r.numero_comprobante = ac.numero_comprobante
    WHERE ac.eliminado = false AND ac.estado <> 'anulado'
)
SELECT
    id_empresa, numero_comprobante, id_asiento, modulo_origen, id_referencia_origen,
    tipo_comprobante, estado, fecha_asiento, total_debe, total_haber, created_at, created_by,
    CASE WHEN bool_or(mismo_doc_veces > 1) OVER (PARTITION BY id_empresa, numero_comprobante)
         THEN 'DUPLICADO REAL (mismo documento)'
         ELSE 'Solo choque de numero_comprobante'
    END AS diagnostico
FROM filas
ORDER BY diagnostico DESC, id_empresa, numero_comprobante, id_asiento;
