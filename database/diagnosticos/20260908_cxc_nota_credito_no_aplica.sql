-- ============================================================================
-- ¿Por qué la Nota de Crédito no descuenta en Cuentas por Cobrar?
--
-- Cuentas por Cobrar enlaza la NC con la factura por el NÚMERO NORMALIZADO a 15
-- dígitos (`num_doc_modificado` de la NC contra EEE-PPP-SSSSSSSSS de la factura),
-- dentro de la MISMA empresa, y exige además: estado <> 'anulado',
-- eliminado = false y un `tipo_ambiente` que coincida con el de la empresa
-- (NULL se tolera). Si el modal "Registrar Cobro" muestra Nota Crédito 0.00,
-- alguna de esas condiciones no se cumple.
--
-- Devuelve UNA sola tabla: la factura y TODAS las notas de crédito del cliente,
-- cada una con su veredicto. Es de SOLO LECTURA: no modifica nada.
--
-- USO: cambia únicamente las dos líneas de "parametros" y ejecuta todo.
-- ============================================================================

WITH parametros AS (
    SELECT '001-001-000000123'::text AS numero_factura,  -- << número de la factura
           1::int                    AS id_empresa       -- << id de la empresa activa
),

-- Establecimientos del mismo RUC: sirve para detectar la NC emitida desde otro
-- establecimiento (no descuenta: el enlace exige la misma empresa).
grupo AS (
    SELECT e2.id,
           COALESCE(e2.establecimiento,'')       AS establecimiento,
           CAST(e2.tipo_ambiente AS VARCHAR(1))  AS ambiente_empresa
    FROM empresas e
    JOIN empresas e2
      ON regexp_replace(COALESCE(e2.ruc,''), '[^0-9]', '', 'g')
       = regexp_replace(COALESCE(e.ruc,''),  '[^0-9]', '', 'g')
     AND e2.eliminado = false
    WHERE e.id = (SELECT id_empresa FROM parametros)
      AND regexp_replace(COALESCE(e.ruc,''), '[^0-9]', '', 'g') <> ''
),

-- La factura buscada, con su número ya normalizado a 15 dígitos.
fact AS (
    SELECT v.id, v.id_empresa, v.id_cliente, v.estado, v.eliminado,
           v.importe_total, v.tipo_ambiente,
           (CASE WHEN COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), '') LIKE '%-%-%' THEN lpad(regexp_replace(split_part(COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), ''), '-', 1), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), ''), '-', 2), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), ''), '-', 3), '[^0-9]', '', 'g'), 9, '0') ELSE regexp_replace(COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), ''), '[^0-9]', '', 'g') END) AS num_norm
    FROM ventas_cabecera v, parametros p
    WHERE v.id_empresa IN (SELECT id FROM grupo)
      AND v.eliminado = false
      AND (CASE WHEN COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), '') LIKE '%-%-%' THEN lpad(regexp_replace(split_part(COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), ''), '-', 1), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), ''), '-', 2), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), ''), '-', 3), '[^0-9]', '', 'g'), 9, '0') ELSE regexp_replace(COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), ''), '[^0-9]', '', 'g') END) = (CASE WHEN COALESCE(p.numero_factura, '') LIKE '%-%-%' THEN lpad(regexp_replace(split_part(COALESCE(p.numero_factura, ''), '-', 1), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE(p.numero_factura, ''), '-', 2), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE(p.numero_factura, ''), '-', 3), '[^0-9]', '', 'g'), 9, '0') ELSE regexp_replace(COALESCE(p.numero_factura, ''), '[^0-9]', '', 'g') END)
    LIMIT 1
),

-- Identificación del cliente de la factura (los clientes son por establecimiento:
-- se cruza por identificación para abarcar todo el grupo).
cliente AS (
    SELECT COALESCE(c.identificacion,'') AS identificacion
    FROM fact f JOIN clientes c ON c.id = f.id_cliente
),

-- Todas las notas de crédito de ese cliente en el grupo (no solo las que enlazan):
-- así se ve también la que apunta a la factura con otro formato o en otro ambiente.
candidatas AS (
    SELECT n.id, n.id_empresa, n.fecha_emision, n.estado, n.eliminado,
           n.tipo_ambiente, n.importe_total,
           CONCAT(n.establecimiento,'-',n.punto_emision,'-',n.secuencial) AS numero_nc,
           COALESCE(n.num_doc_modificado,'') AS num_doc_modificado,
           (CASE WHEN COALESCE(n.num_doc_modificado, '') LIKE '%-%-%' THEN lpad(regexp_replace(split_part(COALESCE(n.num_doc_modificado, ''), '-', 1), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE(n.num_doc_modificado, ''), '-', 2), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE(n.num_doc_modificado, ''), '-', 3), '[^0-9]', '', 'g'), 9, '0') ELSE regexp_replace(COALESCE(n.num_doc_modificado, ''), '[^0-9]', '', 'g') END) AS num_norm_nc
    FROM notas_credito_cabecera n
    JOIN clientes c ON c.id = n.id_cliente
    WHERE n.id_empresa IN (SELECT id FROM grupo)
      AND COALESCE(c.identificacion,'') = (SELECT identificacion FROM cliente)
)

-- ── Resultado ───────────────────────────────────────────────────────────────
SELECT '1. FACTURA'                        AS bloque,
       f.id                                AS id,
       (SELECT numero_factura FROM parametros) AS numero,
       f.num_norm                          AS num_normalizado,
       f.estado                            AS estado,
       f.eliminado                         AS eliminado,
       COALESCE(f.tipo_ambiente,'(null)')  AS ambiente_doc,
       g.ambiente_empresa                  AS ambiente_empresa,
       g.establecimiento                   AS establecimiento,
       f.importe_total                     AS importe,
       CASE WHEN f.estado NOT IN ('autorizado','autorizada')
            THEN 'La factura está en estado "' || f.estado || '": el modal de cobro solo abre facturas autorizadas.'
            ELSE 'OK — factura localizada.' END AS veredicto
FROM fact f
JOIN grupo g ON g.id = f.id_empresa

UNION ALL

SELECT '2. NOTA DE CRÉDITO',
       c.id,
       c.numero_nc,
       c.num_norm_nc,
       c.estado,
       c.eliminado,
       COALESCE(c.tipo_ambiente,'(null)'),
       g.ambiente_empresa,
       g.establecimiento,
       c.importe_total,
       CASE
           WHEN c.eliminado          THEN 'NO aplica: la NC está eliminada.'
           WHEN c.estado = 'anulado' THEN 'NO aplica: la NC está anulada.'
           WHEN c.num_norm_nc <> (SELECT num_norm FROM fact)
                THEN 'NO aplica a esta factura: apunta a "' || c.num_doc_modificado
                     || '" (normaliza a ' || c.num_norm_nc || ') y la factura es '
                     || (SELECT num_norm FROM fact) || '.'
           WHEN c.id_empresa <> (SELECT id_empresa FROM fact)
                THEN 'NO aplica: la NC es del establecimiento ' || g.establecimiento
                     || ' y la factura es de otro. El enlace exige la misma empresa.'
           WHEN c.tipo_ambiente IS NOT NULL AND c.tipo_ambiente <> g.ambiente_empresa
                THEN 'NO aplica: la NC está en ambiente ' || c.tipo_ambiente
                     || ' y la empresa trabaja en ' || g.ambiente_empresa || '.'
           ELSE 'OK — esta NC SÍ debería estar descontando en Cuentas por Cobrar.'
       END
FROM candidatas c
JOIN grupo g ON g.id = c.id_empresa

ORDER BY 1, 2;

-- ============================================================================
-- Lecturas del resultado
--
--  · Sin ninguna fila         → el número de factura o el id_empresa no existen
--                               (revisa los parámetros; el número va tal cual
--                               aparece en el listado, p. ej. 001-001-000000123).
--  · Solo la fila 1. FACTURA  → ese cliente no tiene notas de crédito en el
--                               grupo: la NC está registrada con otro cliente.
--  · Filas 2 con veredicto    → ahí está el motivo exacto por el que el modal
--    "NO aplica…"               muestra Nota Crédito 0.00.
-- ============================================================================
