-- =====================================================================
-- Plan de cuentas: mapeo ECP (Supercías) para las empresas ya existentes.
-- Ejecutar completo en pgAdmin. Idempotente: se puede correr más de una vez.
--
-- Misma regla que aplica el sistema al guardar (App\Helpers\SuperciasEcp):
--   * supercias_ecp_subcodigo = COLUMNA del ECP (componente del patrimonio).
--   * supercias_ecp_codigo    = FILA DE CAMBIOS opcional (990102, 990103, 990201-990209).
--   * Fuera de patrimonio (código que no empieza por 3) no hay ECP.
--   * En patrimonio, si falta la columna se deduce del casillero ESF (prefijo más largo).
-- =====================================================================

-- 1) Cuentas que NO son de patrimonio: sin columna ni fila ECP (limpia valores de prueba como 99/555).
UPDATE plan_cuentas
SET supercias_ecp_codigo = NULL,
    supercias_ecp_subcodigo = NULL,
    updated_at = NOW()
WHERE eliminado = false
  AND codigo NOT LIKE '3%'
  AND (COALESCE(supercias_ecp_codigo, '') <> '' OR COALESCE(supercias_ecp_subcodigo, '') <> '');

-- 2) Patrimonio: filas heredadas que no son "de cambios" equivalen a la fila por defecto → se limpian.
UPDATE plan_cuentas
SET supercias_ecp_codigo = NULL,
    updated_at = NOW()
WHERE eliminado = false
  AND codigo LIKE '3%'
  AND supercias_ecp_codigo IN ('99', '9901', '9902', '990101', '990210');

-- 3) Patrimonio nivel 5 sin columna: deducirla del ESF (la columna más larga que sea prefijo del ESF:
--    30101 → 301, 30401 → 30401, 30601 → 30601).
UPDATE plan_cuentas pc
SET supercias_ecp_subcodigo = c.col,
    updated_at = NOW()
FROM (
    SELECT p.id,
           (SELECT v.col
              FROM (VALUES ('301'),('302'),('303'),('30401'),('30402'),
                           ('30501'),('30502'),('30503'),('30504'),
                           ('30601'),('30602'),('30603'),('30604'),('30605'),('30606'),('30607'),
                           ('30701'),('30702')) AS v(col)
             WHERE p.supercias_esf LIKE v.col || '%'
             ORDER BY length(v.col) DESC
             LIMIT 1) AS col
      FROM plan_cuentas p
     WHERE p.eliminado = false
       AND p.codigo LIKE '3%'
       AND p.nivel::text = '5'
       AND COALESCE(p.supercias_ecp_subcodigo, '') = ''
       AND COALESCE(p.supercias_esf, '') <> ''
) c
WHERE pc.id = c.id
  AND c.col IS NOT NULL;

-- 4) Revisión: cuentas de patrimonio de nivel 5 que SIGUEN sin entrar al ECP.
--    'SIN COLUMNA'        → falta el ESF o el ESF no es de patrimonio; asigne ESF y columna en Plan de Cuentas.
--    'COLUMNA NO VÁLIDA'  → por ejemplo 304, 306 o 307 (grupos del ESF, no columnas del ECP);
--                           corrija el ESF al casillero de detalle (30401, 30601, 30701…) y la columna igual.
SELECT id_empresa,
       codigo,
       nombre,
       supercias_esf              AS esf,
       supercias_ecp_subcodigo    AS columna_ecp,
       supercias_ecp_codigo       AS fila_ecp,
       CASE WHEN COALESCE(supercias_ecp_subcodigo, '') = '' THEN 'SIN COLUMNA'
            ELSE 'COLUMNA NO VÁLIDA' END AS observacion
  FROM plan_cuentas
 WHERE eliminado = false
   AND codigo LIKE '3%'
   AND nivel::text = '5'
   AND (COALESCE(supercias_ecp_subcodigo, '') = ''
        OR supercias_ecp_subcodigo NOT IN ('301','302','303','30401','30402',
                                           '30501','30502','30503','30504',
                                           '30601','30602','30603','30604','30605','30606','30607',
                                           '30701','30702'))
 ORDER BY id_empresa, codigo;
