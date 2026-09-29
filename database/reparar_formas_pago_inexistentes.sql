-- =====================================================================================
-- Reparar ingresos/egresos cuyos pagos apuntan a una FORMA DE COBRO/PAGO QUE YA NO EXISTE
-- =====================================================================================
-- Síntoma: el aviso de asientos pendientes dice «Algunos Egresos usan una forma de
-- cobro/pago que ya no existe». El armado del asiento descarta ese pago (INNER JOIN a
-- empresa_formas_pago), no queda dinero que contabilizar y el documento nunca tiene asiento.
--
-- Qué hace:   cambia la forma de pago de ESOS pagos (solo los que apuntan a un id que no
--             existe) por la forma que usted indique en el PASO 2. No toca montos, cheques,
--             referencias ni ningún pago con una forma válida.
-- Toca datos: sí (egresos_pagos.id_forma_pago / ingresos_pagos.id_forma_cobro).
-- Reversible: no se puede volver al id viejo: la llave foránea fk_egresopago_formapago no
--             deja apuntar a una forma que no existe (estas filas son anteriores a la llave).
--             Cada fila cambiada se copia a respaldo_formas_pago_reparadas; si eligió mal la
--             forma nueva, vea «CORREGIR UNA ELECCIÓN» al final.
-- Auditoría:  este cambio NO queda en log_sistema. Si necesita trazabilidad por documento,
--             corrija desde la pantalla de Egresos/Ingresos en lugar de usar este script.
-- Después:    Contabilidad → Asientos Contables → aceptar «Generar ahora». El asiento no se
--             genera solo al abrir Egresos: esos documentos quedaron marcados como fallidos.
--
-- Uso en pgAdmin: ejecute el PASO 1 (solo lectura), anote a qué forma debe ir cada id
-- viejo, complete el PASO 2 y ejecute el PASO 2 + PASO 3 juntos (F5).
-- =====================================================================================


-- -------------------------------------------------------------------------------------
-- PASO 1 — DIAGNÓSTICO (solo lectura). Ejecútelo solo (seleccione el bloque y F5).
-- -------------------------------------------------------------------------------------
-- 1a. Pagos afectados, agrupados por empresa y forma inexistente.
SELECT 'EGRESO' AS documento, e.id_empresa, emp.nombre AS empresa,
       p.id_forma_pago AS id_forma_inexistente,
       COUNT(*) AS pagos, SUM(p.monto) AS monto_total,
       STRING_AGG(e.numero_egreso, ', ' ORDER BY e.numero_egreso) AS documentos
  FROM egresos_pagos p
  JOIN egresos_cabecera e ON e.id = p.id_egreso AND e.eliminado = false
  LEFT JOIN empresas emp ON emp.id = e.id_empresa
 WHERE p.eliminado = false
   AND NOT EXISTS (SELECT 1 FROM empresa_formas_pago f WHERE f.id = p.id_forma_pago)
 GROUP BY e.id_empresa, emp.nombre, p.id_forma_pago
UNION ALL
SELECT 'INGRESO', i.id_empresa, emp.nombre,
       p.id_forma_cobro,
       COUNT(*), SUM(p.monto),
       STRING_AGG(i.numero_ingreso, ', ' ORDER BY i.numero_ingreso)
  FROM ingresos_pagos p
  JOIN ingresos_cabecera i ON i.id = p.id_ingreso AND i.eliminado = false
  LEFT JOIN empresas emp ON emp.id = i.id_empresa
 WHERE NOT EXISTS (SELECT 1 FROM empresa_formas_pago f WHERE f.id = p.id_forma_cobro)
 GROUP BY i.id_empresa, emp.nombre, p.id_forma_cobro
 ORDER BY 2, 1, 4;

-- 1b. Formas de pago vigentes de cada empresa afectada (para elegir la nueva).
SELECT f.id_empresa, f.id AS id_forma, f.nombre, f.tipo, f.aplica_en, f.numero_cuenta, f.activo
  FROM empresa_formas_pago f
 WHERE f.eliminado = false
   AND f.id_empresa IN (
        SELECT e.id_empresa FROM egresos_pagos p JOIN egresos_cabecera e ON e.id = p.id_egreso
         WHERE p.eliminado = false AND NOT EXISTS (SELECT 1 FROM empresa_formas_pago x WHERE x.id = p.id_forma_pago)
        UNION
        SELECT i.id_empresa FROM ingresos_pagos p JOIN ingresos_cabecera i ON i.id = p.id_ingreso
         WHERE NOT EXISTS (SELECT 1 FROM empresa_formas_pago x WHERE x.id = p.id_forma_cobro))
 ORDER BY f.id_empresa, f.nombre;


-- -------------------------------------------------------------------------------------
-- PASO 2 — CORRECCIÓN. Complete la tabla de equivalencias y ejecute PASO 2 + PASO 3.
-- -------------------------------------------------------------------------------------
-- Una fila por (empresa, id viejo) con la forma nueva elegida en el PASO 1b.
-- Ejemplo: (8, 3, 57) = en la empresa 8, los pagos con la forma 3 (inexistente) pasan a la 57.
-- Si la forma nueva no es de esa empresa o está eliminada, esa fila se ignora.

CREATE TABLE IF NOT EXISTS respaldo_formas_pago_reparadas (
    tabla          varchar(20)  NOT NULL,
    id_pago        integer      NOT NULL,
    id_documento   integer      NOT NULL,
    id_forma_vieja integer      NOT NULL,
    id_forma_nueva integer      NOT NULL,
    reparado_at    timestamp    NOT NULL DEFAULT now()
);

WITH equivalencias (id_empresa, id_forma_vieja, id_forma_nueva) AS (
    VALUES
        (0, 0, 0)   -- ← REEMPLAZAR: (id_empresa, id_forma_inexistente, id_forma_nueva), una por línea separadas por coma
),
validas AS (
    SELECT q.* FROM equivalencias q
      JOIN empresa_formas_pago f ON f.id = q.id_forma_nueva
                                AND f.id_empresa = q.id_empresa
                                AND f.eliminado = false
     WHERE NOT EXISTS (SELECT 1 FROM empresa_formas_pago x WHERE x.id = q.id_forma_vieja)
),
respaldo_egr AS (
    INSERT INTO respaldo_formas_pago_reparadas (tabla, id_pago, id_documento, id_forma_vieja, id_forma_nueva)
    SELECT 'egresos_pagos', p.id, p.id_egreso, p.id_forma_pago, v.id_forma_nueva
      FROM egresos_pagos p
      JOIN egresos_cabecera e ON e.id = p.id_egreso
      JOIN validas v ON v.id_empresa = e.id_empresa AND v.id_forma_vieja = p.id_forma_pago
     WHERE p.eliminado = false
    RETURNING id_pago, id_forma_nueva
),
respaldo_ing AS (
    INSERT INTO respaldo_formas_pago_reparadas (tabla, id_pago, id_documento, id_forma_vieja, id_forma_nueva)
    SELECT 'ingresos_pagos', p.id, p.id_ingreso, p.id_forma_cobro, v.id_forma_nueva
      FROM ingresos_pagos p
      JOIN ingresos_cabecera i ON i.id = p.id_ingreso
      JOIN validas v ON v.id_empresa = i.id_empresa AND v.id_forma_vieja = p.id_forma_cobro
    RETURNING id_pago, id_forma_nueva
),
upd_egr AS (
    UPDATE egresos_pagos p SET id_forma_pago = r.id_forma_nueva
      FROM respaldo_egr r WHERE p.id = r.id_pago
    RETURNING p.id
),
upd_ing AS (
    UPDATE ingresos_pagos p SET id_forma_cobro = r.id_forma_nueva
      FROM respaldo_ing r WHERE p.id = r.id_pago
    RETURNING p.id
)
SELECT (SELECT COUNT(*) FROM upd_egr) AS pagos_egreso_corregidos,
       (SELECT COUNT(*) FROM upd_ing) AS pagos_ingreso_corregidos;


-- -------------------------------------------------------------------------------------
-- PASO 3 — COMPROBACIÓN: debe salir 0 en las empresas que corrigió.
-- -------------------------------------------------------------------------------------
-- SELECT e.id_empresa, COUNT(*) AS pagos_con_forma_inexistente
--   FROM egresos_pagos p JOIN egresos_cabecera e ON e.id = p.id_egreso AND e.eliminado = false
--  WHERE p.eliminado = false
--    AND NOT EXISTS (SELECT 1 FROM empresa_formas_pago f WHERE f.id = p.id_forma_pago)
--  GROUP BY e.id_empresa;


-- -------------------------------------------------------------------------------------
-- CORREGIR UNA ELECCIÓN (si a los pagos de una forma vieja les asignó la forma equivocada)
-- -------------------------------------------------------------------------------------
-- Cambie 0 por: id_forma_vieja (la inexistente), id de la forma asignada por error y la correcta.
-- Si el asiento ya se generó con la forma equivocada, anúlelo en Asientos Contables y vuelva a
-- generar (o corrija el egreso desde la pantalla, que regenera su asiento).
-- UPDATE egresos_pagos p SET id_forma_pago = 0 /* forma correcta */
--   FROM respaldo_formas_pago_reparadas r
--  WHERE r.tabla = 'egresos_pagos' AND p.id = r.id_pago
--    AND r.id_forma_vieja = 0 /* forma inexistente */ AND p.id_forma_pago = 0 /* forma asignada por error */;
