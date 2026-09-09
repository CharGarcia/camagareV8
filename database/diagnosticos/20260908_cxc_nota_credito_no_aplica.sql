-- ============================================================================
-- ¿Por qué la Nota de Crédito no descuenta en Cuentas por Cobrar?
--
-- NO hay que ejecutar ninguna migración para que el cruce funcione: Cuentas por
-- Cobrar enlaza la NC con la factura EN LA PROPIA CONSULTA, comparando el número
-- NORMALIZADO a 15 dígitos (`num_doc_modificado` de la NC contra
-- EEE-PPP-SSSSSSSSS de la factura), dentro de la MISMA empresa, y exigiendo
-- además: estado <> 'anulado', eliminado = false y un `tipo_ambiente` que
-- coincida con el de la empresa (NULL se tolera).
--
-- Si el modal "Registrar Cobro" muestra Nota Crédito 0.00, alguna de esas
-- condiciones no se cumple. Este script dice cuál, en texto.
--
-- SOLO LECTURA: no modifica ni crea nada.
-- USO: cambia ÚNICAMENTE el número de factura de la primera línea y ejecuta todo.
--      El número va tal como aparece en el listado (p. ej. 001-001-000000123).
-- ============================================================================

WITH parametros AS (
    SELECT '001-001-000000123'::text AS numero_factura   -- << ÚNICO dato a cambiar
),

-- La factura, buscada por número normalizado en TODAS las empresas.
-- De aquí sale la empresa dueña: no hay que averiguar ningún id a mano.
fact AS (
    SELECT v.id, v.id_empresa, v.id_cliente, v.estado, v.eliminado,
           v.importe_total, v.tipo_ambiente,
           (CASE WHEN COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), '') LIKE '%-%-%' THEN lpad(regexp_replace(split_part(COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), ''), '-', 1), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), ''), '-', 2), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), ''), '-', 3), '[^0-9]', '', 'g'), 9, '0') ELSE regexp_replace(COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), ''), '[^0-9]', '', 'g') END) AS num_norm
    FROM ventas_cabecera v, parametros p
    WHERE v.eliminado = false
      AND (CASE WHEN COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), '') LIKE '%-%-%' THEN lpad(regexp_replace(split_part(COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), ''), '-', 1), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), ''), '-', 2), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), ''), '-', 3), '[^0-9]', '', 'g'), 9, '0') ELSE regexp_replace(COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), ''), '[^0-9]', '', 'g') END) = (CASE WHEN COALESCE(p.numero_factura, '') LIKE '%-%-%' THEN lpad(regexp_replace(split_part(COALESCE(p.numero_factura, ''), '-', 1), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE(p.numero_factura, ''), '-', 2), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE(p.numero_factura, ''), '-', 3), '[^0-9]', '', 'g'), 9, '0') ELSE regexp_replace(COALESCE(p.numero_factura, ''), '[^0-9]', '', 'g') END)
    ORDER BY v.id DESC
    LIMIT 1
),

-- Establecimientos del mismo RUC que la empresa dueña de la factura: sirve para
-- detectar la NC emitida desde OTRO establecimiento (no descuenta: el enlace
-- exige la misma empresa).
grupo AS (
    SELECT e2.id,
           COALESCE(e2.establecimiento,'')       AS establecimiento,
           CAST(e2.tipo_ambiente AS VARCHAR(1))  AS ambiente_empresa
    FROM empresas e
    JOIN empresas e2
      ON regexp_replace(COALESCE(e2.ruc,''), '[^0-9]', '', 'g')
       = regexp_replace(COALESCE(e.ruc,''),  '[^0-9]', '', 'g')
     AND e2.eliminado = false
    WHERE e.id = (SELECT id_empresa FROM fact)
),

-- Identificación del cliente de la factura (los clientes son por establecimiento:
-- se cruza por identificación para abarcar todo el grupo).
cliente AS (
    SELECT COALESCE(c.identificacion,'') AS identificacion
    FROM fact f JOIN clientes c ON c.id = f.id_cliente
),

-- TODAS las notas de crédito de ese cliente en el grupo (no solo las que
-- enlazan): así se ve también la que apunta a la factura con otro formato,
-- en otro ambiente o desde otro establecimiento.
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
       f.id_empresa                        AS id_empresa,
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
       c.id_empresa,
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
                THEN 'NO aplica: la NC se emitió desde el establecimiento ' || g.establecimiento
                     || ' (empresa ' || c.id_empresa || ') y la factura es de la empresa '
                     || (SELECT id_empresa FROM fact) || '. El enlace exige la misma empresa.'
           WHEN c.tipo_ambiente IS NOT NULL AND c.tipo_ambiente <> g.ambiente_empresa
                THEN 'NO aplica: la NC está en ambiente ' || c.tipo_ambiente
                     || ' y la empresa trabaja en ' || g.ambiente_empresa || '.'
           ELSE 'OK — esta NC SÍ debería estar descontando en Cuentas por Cobrar.'
       END
FROM candidatas c
JOIN grupo g ON g.id = c.id_empresa

ORDER BY 1, 2;

-- ============================================================================
-- Cómo leer el resultado
--
--  · Sin ninguna fila          → ese número de factura no existe (o está
--                                eliminada). Cópielo tal cual del listado.
--  · Solo la fila 1. FACTURA   → ese cliente no tiene NINGUNA nota de crédito en
--                                el grupo: la NC quedó registrada con otro
--                                cliente (revise el cliente de la NC).
--  · Filas "2." con veredicto  → ahí está el motivo exacto por el que el modal
--    que empieza en "NO aplica"  muestra Nota Crédito 0.00.
--  · Alguna fila "2." con "OK"  → la NC sí cruza; entonces el 0.00 viene de otra
--                                parte (avísame con esta salida).
-- ============================================================================


-- ============================================================================
-- CONSULTA RÁPIDA (sin parámetros): ¿cada NC cruza con alguna factura?
-- `factura_enlazada` en NULL = esa NC no está descontando en Cuentas por Cobrar.
-- ============================================================================

-- ¿Cada nota de crédito cruza con alguna factura? (sin parámetros: ejecutar tal cual)
-- Si `factura_enlazada` sale NULL, esa NC NO está descontando en Cuentas por Cobrar.
SELECT n.id_empresa,
       e.establecimiento,
       n.id                                                           AS id_nc,
       n.fecha_emision,
       CONCAT(n.establecimiento,'-',n.punto_emision,'-',n.secuencial) AS nota_credito,
       n.num_doc_modificado                                           AS apunta_a,
       n.estado,
       n.tipo_ambiente                                                AS amb_nc,
       CAST(e.tipo_ambiente AS VARCHAR(1))                            AS amb_empresa,
       n.importe_total,
       c.nombre                                                       AS cliente,
       (SELECT CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial)
          FROM ventas_cabecera v
         WHERE v.id_empresa = n.id_empresa
           AND v.eliminado  = false
           AND (CASE WHEN COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), '') LIKE '%-%-%' THEN lpad(regexp_replace(split_part(COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), ''), '-', 1), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), ''), '-', 2), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), ''), '-', 3), '[^0-9]', '', 'g'), 9, '0') ELSE regexp_replace(COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), ''), '[^0-9]', '', 'g') END) = (CASE WHEN COALESCE(n.num_doc_modificado, '') LIKE '%-%-%' THEN lpad(regexp_replace(split_part(COALESCE(n.num_doc_modificado, ''), '-', 1), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE(n.num_doc_modificado, ''), '-', 2), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE(n.num_doc_modificado, ''), '-', 3), '[^0-9]', '', 'g'), 9, '0') ELSE regexp_replace(COALESCE(n.num_doc_modificado, ''), '[^0-9]', '', 'g') END)
         LIMIT 1)                                                     AS factura_enlazada
FROM notas_credito_cabecera n
JOIN clientes c ON c.id = n.id_cliente
JOIN empresas e ON e.id = n.id_empresa
WHERE n.eliminado = false
ORDER BY n.id DESC
LIMIT 30;


-- ============================================================================
-- ¿EXISTE la factura a la que apunta la NC? (cambiar los secuenciales del IN)
-- ============================================================================

-- ¿Dónde están las facturas 002-101-000007094 y 002-101-000007100?
-- Busca por SECUENCIAL (7094 / 7100) en TODAS las empresas, incluidas las
-- eliminadas, para ver si existen con otro establecimiento, otra empresa u otro
-- estado. Sin parámetros: ejecutar tal cual.
SELECT v.id_empresa,
       e.establecimiento                                              AS estab_empresa,
       v.id                                                           AS id_factura,
       v.fecha_emision,
       CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial) AS numero,
       (CASE WHEN COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), '') LIKE '%-%-%' THEN lpad(regexp_replace(split_part(COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), ''), '-', 1), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), ''), '-', 2), '[^0-9]', '', 'g'), 3, '0') || lpad(regexp_replace(split_part(COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), ''), '-', 3), '[^0-9]', '', 'g'), 9, '0') ELSE regexp_replace(COALESCE((v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial), ''), '[^0-9]', '', 'g') END)                                                        AS num_normalizado,
       v.estado,
       v.eliminado,
       v.tipo_ambiente,
       v.importe_total,
       c.nombre                                                       AS cliente
FROM ventas_cabecera v
JOIN empresas e ON e.id = v.id_empresa
LEFT JOIN clientes c ON c.id = v.id_cliente
WHERE regexp_replace(COALESCE(v.secuencial,''), '[^0-9]', '', 'g') <> ''
  AND CAST(regexp_replace(COALESCE(v.secuencial,''), '[^0-9]', '', 'g') AS BIGINT) IN (7094, 7100)
ORDER BY v.id_empresa, v.id;
