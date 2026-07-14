-- Diagnóstico: ¿los asientos migrados usan código NUEVO (casa) o VIEJO? Cambiar el 1 por tu empresa.

-- (A) Cuentas usadas por los asientos migrados, clasificadas por formato del código.
--     Si aparecen 'VIEJO (2 díg)' => el código convertido NO se aplicó (revisar despliegue en el servidor).
--     Si todo es 'CASA (1 díg)'   => la conversión funcionó; lo que ves con código viejo son cuentas
--     sobrantes del PLAN (no de los asientos) y se quitan con reset_contabilidad_migrada.sql (fusión).
SELECT
  CASE WHEN pc.codigo ~ '^[0-9]+\.[0-9]+\.[0-9]{2}\.' THEN 'VIEJO (2 dig nivel 3)'
       WHEN pc.codigo ~ '^[0-9]+\.[0-9]+\.[0-9]\.'    THEN 'CASA (1 dig nivel 3)'
       ELSE 'otro' END AS formato,
  COUNT(DISTINCT pc.id) AS cuentas,
  COUNT(*)              AS lineas
FROM asientos_contables_cabecera a
JOIN asientos_contables_detalle d ON d.id_asiento = a.id AND d.eliminado = false
JOIN plan_cuentas pc               ON pc.id = d.id_cuenta_contable
WHERE a.id_empresa = 1 AND a.modulo_origen = 'migracion' AND a.eliminado = false
GROUP BY 1
ORDER BY 1;

-- (B) Muestra concreta: 15 líneas de asientos migrados con su código de cuenta.
SELECT a.id AS asiento, pc.codigo, pc.nombre, d.debe, d.haber
FROM asientos_contables_cabecera a
JOIN asientos_contables_detalle d ON d.id_asiento = a.id AND d.eliminado = false
JOIN plan_cuentas pc               ON pc.id = d.id_cuenta_contable
WHERE a.id_empresa = 1 AND a.modulo_origen = 'migracion' AND a.eliminado = false
ORDER BY a.id DESC
LIMIT 15;

-- (C) Cuentas del PLAN con código viejo todavía activas (estas se ven en el balance aunque los
--     asientos ya usen las nuevas; se quitan con la fusión de reset_contabilidad_migrada.sql).
SELECT COUNT(*) AS cuentas_viejas_activas_en_el_plan
FROM plan_cuentas
WHERE id_empresa = 1 AND eliminado = false AND codigo ~ '^[0-9]+\.[0-9]+\.[0-9]{2}\.';
