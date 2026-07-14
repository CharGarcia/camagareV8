-- Diagnóstico: por qué el balance sigue mostrando cuentas con código viejo tras limpiar.
-- Cambiar el 1 por tu id de empresa en las 4 consultas.

-- (1) ¿Quedaron asientos de la migración? (deberían ser 0 tras limpiar)
SELECT 'asientos por origen' AS que, modulo_origen, COUNT(*) AS n
FROM asientos_contables_cabecera
WHERE id_empresa = 1 AND eliminado = false
GROUP BY modulo_origen
ORDER BY n DESC;

-- (2) Cuentas con CÓDIGO VIEJO (3er segmento de 2 dígitos) todavía ACTIVAS (eliminado=false)
SELECT 'cuentas viejas activas' AS que, COUNT(*) AS n
FROM plan_cuentas
WHERE id_empresa = 1 AND eliminado = false
  AND codigo ~ '^[0-9]+\.[0-9]+\.[0-9]{2}\.';

-- (3) De esas cuentas viejas activas, ¿tienen MOVIMIENTOS y de qué asientos vienen?
--     (si aparecen con movimientos de 'migracion' => la limpieza no borró esos asientos)
SELECT a.modulo_origen, COUNT(DISTINCT pc.id) AS cuentas_viejas_con_mov, COUNT(*) AS lineas
FROM plan_cuentas pc
JOIN asientos_contables_detalle d ON d.id_cuenta_contable = pc.id AND d.eliminado = false
JOIN asientos_contables_cabecera a ON a.id = d.id_asiento AND a.eliminado = false
WHERE pc.id_empresa = 1 AND pc.eliminado = false
  AND pc.codigo ~ '^[0-9]+\.[0-9]+\.[0-9]{2}\.'
GROUP BY a.modulo_origen
ORDER BY lineas DESC;

-- (4) ¿Por qué NO se soft-borraron? cuáles cuentas viejas activas están amarradas a config
--     (asientos_programados) o aún tienen movimientos.
SELECT pc.codigo, pc.nombre,
       EXISTS (SELECT 1 FROM asientos_programados ap WHERE ap.id_cuenta = pc.id) AS en_config,
       EXISTS (SELECT 1 FROM asientos_contables_detalle d
                 JOIN asientos_contables_cabecera a ON a.id = d.id_asiento AND a.eliminado = false
                WHERE d.id_cuenta_contable = pc.id AND d.eliminado = false)          AS con_movimiento
FROM plan_cuentas pc
WHERE pc.id_empresa = 1 AND pc.eliminado = false
  AND pc.codigo ~ '^[0-9]+\.[0-9]+\.[0-9]{2}\.'
ORDER BY pc.codigo
LIMIT 30;
