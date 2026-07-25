-- ============================================================================
-- Corrección: anular los asientos de cierre GENERADOS sobre años que ya traían
--             su propio cierre desde el sistema viejo.
-- ============================================================================
-- Diagnóstico (ver 20260724_diagnostico_cierre_ejercicio.sql):
--
--   El sistema viejo SÍ cerraba el ejercicio y ese asiento se migró bien. Ej. 2023:
--       7.1.1.01.001 RESUMEN RESULTADOS            debe  222.68
--       2.1.5.02.001 IMPUESTO RENTA DEL EJERCICIO  haber  48.99
--       3.3.2.01.001 UTILIDADES EJERCICIO 2023     haber 173.69
--
--   Ese asiento DEBITA la cuenta PIVOT para llevar el resultado a patrimonio.
--   cerrarEjercicioMigrado() leyó ese saldo deudor como si fuera una pérdida sin
--   contabilizar y generó un segundo cierre:
--       3.3.1.01.002 Perdida del ejercicio         debe  222.68
--       7.1.1.01.001 RESUMEN RESULTADOS            haber 222.68
--
--   Resultado: una «Pérdida del ejercicio» que nunca existió y el patrimonio de
--   cada año subvaluado en ese importe (2023: 222.68, 2024: 239.86, 2025: 323.35
--   → 785.89 en total). El Balance cuadraba igual porque el asiento está
--   balanceado y la pérdida ficticia compensaba por accidente el resultado que
--   las cuentas 4/5/6 —abiertas— vuelven a aportar.
--
--   Al anularlos, el saldo deudor vuelve a la PIVOT y el Balance lo usa en su
--   cierre virtual, que es justo para lo que está: el resultado del ejercicio se
--   muestra en 0 (ya está repartido entre impuesto y utilidad) y el patrimonio
--   queda correcto. Comprobado con las cifras de 2023:
--       Pasivo 1.082,43 + Patrimonio 573,69 (400,00 capital + 173,69 utilidad)
--       + Resultado 0,00  =  Activo 1.656,12  ✓
--
-- El fix del código va en MigracionMysqlService::cerrarEjercicioMigrado(), que
-- ahora omite los años que ya traen cierre migrado (y el ejercicio en curso).
-- Sin ese fix, una nueva corrida de la migración volvería a crearlos.
--
-- Empresa 8, usuario 2 (producción).
-- NO hace COMMIT automático: revisar la verificación y confirmar a mano.
-- ============================================================================

BEGIN;

-- Cierres generados por la migración sobre años que YA tenían cierre propio.
CREATE TEMP TABLE cierres_ficticios AS
SELECT a.id, a.fecha_asiento, a.numero_comprobante, a.concepto, a.total_debe
FROM asientos_contables_cabecera a
WHERE a.id_empresa = 8
  AND a.eliminado = false
  AND a.modulo_origen = 'migracion'
  AND a.tipo_comprobante = 'cierre'
  AND EXISTS (
      SELECT 1
      FROM asientos_contables_cabecera v
      JOIN asientos_contables_detalle dv ON dv.id_asiento = v.id AND dv.eliminado = false
      JOIN plan_cuentas pv              ON pv.id = dv.id_cuenta_contable
      WHERE v.id_empresa   = a.id_empresa
        AND v.eliminado    = false
        AND v.modulo_origen = 'migracion'
        AND v.tipo_comprobante <> 'cierre'
        AND EXTRACT(YEAR FROM v.fecha_asiento) = EXTRACT(YEAR FROM a.fecha_asiento)
        AND pv.codigo LIKE '7%' AND pv.nivel = '5'
  );

-- 1) VISTA PREVIA — deberían salir CIERRE-2023, CIERRE-2024 y CIERRE-2025 (3 filas)
SELECT * FROM cierres_ficticios ORDER BY fecha_asiento;

-- 2) APLICAR (eliminación lógica, igual que la anulación del módulo)
UPDATE asientos_contables_cabecera
   SET eliminado = true, estado = 'anulado',
       deleted_at = now(), deleted_by = 2,
       updated_at = now(), updated_by = 2
 WHERE id IN (SELECT id FROM cierres_ficticios);

UPDATE asientos_contables_detalle
   SET eliminado = true,
       deleted_at = now(), deleted_by = 2,
       updated_at = now(), updated_by = 2
 WHERE id_asiento IN (SELECT id FROM cierres_ficticios)
   AND eliminado = false;

-- 3) VERIFICACIÓN — patrimonio por año: «Perdida del ejercicio» ya no debe aparecer
SELECT EXTRACT(YEAR FROM a.fecha_asiento)::int AS anio, pc.codigo, pc.nombre,
       ROUND(SUM(d.haber) - SUM(d.debe), 2) AS saldo
FROM asientos_contables_cabecera a
JOIN asientos_contables_detalle d ON d.id_asiento = a.id AND d.eliminado = false
JOIN plan_cuentas pc             ON pc.id = d.id_cuenta_contable AND pc.eliminado = false
WHERE a.id_empresa = 8 AND a.eliminado = false AND a.estado = 'contabilizado'
  AND pc.codigo LIKE '3%'
GROUP BY 1, 2, 3
ORDER BY 1, 2;

-- 4) VERIFICACIÓN — cuadre por año: Activo = Pasivo + Patrimonio + Resultado.
--    'descuadre' debe dar 0.00 en todos los años.
SELECT anio,
       activo, pasivo, patrimonio,
       ROUND(ingresos - costos - gastos + pivot, 2) AS resultado,
       ROUND(activo - (pasivo + patrimonio + (ingresos - costos - gastos + pivot)), 2) AS descuadre
FROM (
    SELECT EXTRACT(YEAR FROM a.fecha_asiento)::int AS anio,
           ROUND(SUM(CASE WHEN LEFT(pc.codigo,1) = '1' THEN d.debe  - d.haber ELSE 0 END), 2) AS activo,
           ROUND(SUM(CASE WHEN LEFT(pc.codigo,1) = '2' THEN d.haber - d.debe  ELSE 0 END), 2) AS pasivo,
           ROUND(SUM(CASE WHEN LEFT(pc.codigo,1) = '3' THEN d.haber - d.debe  ELSE 0 END), 2) AS patrimonio,
           ROUND(SUM(CASE WHEN LEFT(pc.codigo,1) = '4' THEN d.haber - d.debe  ELSE 0 END), 2) AS ingresos,
           ROUND(SUM(CASE WHEN LEFT(pc.codigo,1) = '5' THEN d.debe  - d.haber ELSE 0 END), 2) AS costos,
           ROUND(SUM(CASE WHEN LEFT(pc.codigo,1) = '6' THEN d.debe  - d.haber ELSE 0 END), 2) AS gastos,
           ROUND(SUM(CASE WHEN LEFT(pc.codigo,1) = '7' THEN d.haber - d.debe  ELSE 0 END), 2) AS pivot
    FROM asientos_contables_cabecera a
    JOIN asientos_contables_detalle d ON d.id_asiento = a.id AND d.eliminado = false
    JOIN plan_cuentas pc             ON pc.id = d.id_cuenta_contable AND pc.eliminado = false
    WHERE a.id_empresa = 8 AND a.eliminado = false AND a.estado = 'contabilizado'
      AND CAST(a.tipo_ambiente AS VARCHAR(1)) = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = 8)
    GROUP BY 1
) x
ORDER BY anio;

-- Si la vista previa trajo los 3 cierres y el descuadre da 0.00:  COMMIT;
-- Si algo no cuadra:                                              ROLLBACK;
