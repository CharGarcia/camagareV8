-- =====================================================================================
-- Plan de cuentas: DIAGNÓSTICO de códigos y niveles fuera del formato de la casa
-- -------------------------------------------------------------------------------------
-- Formato correcto (el mismo del plan modelo y de la importación desde Excel):
--   Nivel 1  N              1
--   Nivel 2  N.N            1.1
--   Nivel 3  N.N.N          1.1.1          (sin ceros a la izquierda: 1.1.1, NO 1.1.01)
--   Nivel 4  N.N.N.NN       1.1.1.01       (2 dígitos)
--   Nivel 5  N.N.N.NN.NNN   1.1.1.01.001   (3 dígitos)
--   nivel = número de segmentos del código. Máximo 5 niveles.
--
-- SOLO LECTURA: no modifica nada. Pegar en pgAdmin y ejecutar (F5).
-- Columnas:
--   codigo_correcto  = código normalizado (niveles 1-3 sin relleno, 4 a 2 dígitos, 5 a 3).
--   problema         = qué está mal.
--   choca_con_id     = id de OTRA cuenta activa de la misma empresa que ya tiene
--                      codigo_correcto (si hay, no basta con renombrar: hay que fusionar).
--   movimientos      = líneas de asiento activas que usan la cuenta.
-- =====================================================================================
WITH c AS (
    SELECT pc.id, pc.id_empresa, pc.codigo, pc.nivel, pc.nombre,
           string_to_array(pc.codigo, '.') AS seg
    FROM plan_cuentas pc
    WHERE pc.eliminado = false
),
n AS (
    SELECT c.*,
           array_length(seg, 1) AS segs,
           c.codigo ~ '^[0-9]+(\.[0-9]+)*$' AS numerico,
           (SELECT string_agg(
                       CASE WHEN s.i <= 3 THEN (s.v::bigint)::text
                            WHEN s.i = 4  THEN lpad((s.v::bigint)::text, 2, '0')
                            WHEN s.i = 5  THEN lpad((s.v::bigint)::text, 3, '0')
                            ELSE s.v END, '.' ORDER BY s.i)
              FROM unnest(c.seg) WITH ORDINALITY AS s(v, i)
             WHERE c.codigo ~ '^[0-9]+(\.[0-9]+)*$') AS codigo_correcto
    FROM c
)
SELECT n.id_empresa,
       e.nombre AS empresa,
       n.id,
       n.codigo,
       n.nivel,
       n.nombre,
       n.codigo_correcto,
       n.segs AS nivel_correcto,
       concat_ws(' | ',
           CASE WHEN NOT n.numerico                    THEN 'código no numérico / con espacios' END,
           CASE WHEN n.segs > 5                        THEN 'más de 5 niveles' END,
           CASE WHEN n.nivel IS DISTINCT FROM n.segs::text THEN 'nivel no coincide con el código' END,
           CASE WHEN n.numerico AND n.codigo <> n.codigo_correcto THEN 'dígitos fuera de formato' END
       ) AS problema,
       (SELECT o.id FROM plan_cuentas o
         WHERE o.id_empresa = n.id_empresa AND o.eliminado = false
           AND o.id <> n.id AND o.codigo = n.codigo_correcto
         LIMIT 1) AS choca_con_id,
       (SELECT count(*) FROM asientos_contables_detalle d
         WHERE d.id_cuenta_contable = n.id AND d.id_empresa = n.id_empresa AND d.eliminado = false) AS movimientos
FROM n
LEFT JOIN empresas e ON e.id = n.id_empresa
WHERE NOT n.numerico
   OR n.segs > 5
   OR n.nivel IS DISTINCT FROM n.segs::text
   OR n.codigo <> n.codigo_correcto
ORDER BY n.id_empresa, n.codigo;

-- Resumen por empresa (descomentar):
-- ... mismo WITH ... SELECT id_empresa, count(*) FROM (<consulta de arriba>) x GROUP BY 1 ORDER BY 2 DESC;

-- Además: códigos DUPLICADOS activos en la misma empresa (no hay índice único que lo impida)
-- SELECT id_empresa, codigo, count(*), string_agg(id::text || ' ' || nombre, ' / ')
--   FROM plan_cuentas WHERE eliminado = false
--  GROUP BY id_empresa, codigo HAVING count(*) > 1 ORDER BY 1, 2;
