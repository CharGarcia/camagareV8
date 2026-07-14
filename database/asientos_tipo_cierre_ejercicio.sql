-- =============================================================================
-- Tipo de asiento "Cierre del Ejercicio" en el catálogo global asientos_tipo.
-- La cuenta PIVOT (Resumen de Resultados / clase 7) NO se configura: el Balance la detecta
-- internamente por convención (cuentas clase 7). Aquí solo se configuran las dos cuentas de
-- PATRIMONIO donde se muestra el resultado según el signo:
--   1) Cuenta de Utilidad del Ejercicio  (resultado positivo)
--   2) Cuenta de Pérdida del Ejercicio   (resultado negativo)
-- Idempotente. asientos_tipo es GLOBAL (sin id_empresa).
-- =============================================================================

-- Retirar slots antiguos si existían (Resumen de Resultados / Resultado del Ejercicio)
UPDATE asientos_tipo SET eliminado = true, deleted_at = now()
 WHERE tipo_asiento = 'cierre_ejercicio'
   AND codigo IN ('RESUMENRESULTADOSCIERRE', 'RESULTADOEJERCICIOCIERRE')
   AND eliminado = false;

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, tipo_cuenta, debe_haber, eliminado, created_at)
SELECT 'cierre_ejercicio', 'Cuenta de Utilidad del Ejercicio',
       'Cuenta de patrimonio donde se muestra el resultado cuando es UTILIDAD (positivo).',
       'UTILIDADEJERCICIOCIERRE', 'patrimonio', 'haber', false, now()
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE tipo_asiento='cierre_ejercicio' AND codigo='UTILIDADEJERCICIOCIERRE' AND eliminado=false);

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, tipo_cuenta, debe_haber, eliminado, created_at)
SELECT 'cierre_ejercicio', 'Cuenta de Pérdida del Ejercicio',
       'Cuenta de patrimonio donde se muestra el resultado cuando es PÉRDIDA (negativo).',
       'PERDIDAEJERCICIOCIERRE', 'patrimonio', 'debe', false, now()
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE tipo_asiento='cierre_ejercicio' AND codigo='PERDIDAEJERCICIOCIERRE' AND eliminado=false);
