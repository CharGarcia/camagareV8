-- ============================================================================
-- Diagnóstico: asiento de cierre del ejercicio vs. lo que ve el Balance
-- ============================================================================
-- Contexto: la migración NO trae el asiento de cierre del sistema viejo
-- (migrarContabilidad exige `codigo_unico <> ''` y los cierres no tienen
-- documento asociado), así que lo REGENERA aquí: cerrarEjercicioMigrado()
-- salda las cuentas PIVOT (clase 7, nivel 5) contra la cuenta de Utilidad /
-- Pérdida configurada en Configuración Contable → «Cierre del Ejercicio».
--
-- El problema: ese cálculo usa filtros DISTINTOS a los del Balance General
-- (EstadosFinancierosRepository::getSaldos):
--
--                        Balance                     Cierre migrado
--   eliminado            eliminado = false           eliminado = false     (igual)
--   estado               = 'contabilizado'           SIN FILTRO   <-- (1)
--   ambiente             = ambiente de la empresa    SIN FILTRO   <-- (2)
--   cuenta del plan      pc.eliminado = false        SIN FILTRO   <-- (3)
--
-- (1) es el caso más frecuente: anular un asiento desde el módulo de Asientos
--     Contables solo hace UPDATE estado='anulado' y DEJA eliminado = false
--     (AsientoContableRepository::updateEstado). El Balance lo ignora, el
--     cierre lo suma.
--
-- Efecto: el cierre traslada a patrimonio un importe distinto al real, y la
-- clase 7 NO queda en cero para el Balance; esa sobra se suma al resultado
-- del ejercicio. De ahí la diferencia de 2023.
--
-- Empresa de producción = 8. Si se corre en otra empresa, reemplazar los 8
-- por el id correspondiente (Buscar y reemplazar "= 8" → "= <id>").
-- SOLO LECTURA: ninguna consulta modifica datos.
-- Sin meta-comandos de psql: funciona igual en pgAdmin, DBeaver o psql.
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1) Cuentas PIVOT (clase 7) y su nivel.
--    El cierre y el Balance SOLO miran nivel = '5'. Una cuenta clase 7 con
--    movimiento y nivel distinto no se cierra NI se suma al resultado: queda
--    atrapada y descuadra el balance.
-- ----------------------------------------------------------------------------
SELECT pc.codigo, pc.nombre, pc.nivel, pc.eliminado,
       COUNT(d.id)                       AS lineas,
       ROUND(SUM(COALESCE(d.haber,0) - COALESCE(d.debe,0)), 2) AS saldo
FROM plan_cuentas pc
LEFT JOIN asientos_contables_detalle d ON d.id_cuenta_contable = pc.id AND d.eliminado = false
LEFT JOIN asientos_contables_cabecera a ON a.id = d.id_asiento AND a.eliminado = false
WHERE pc.id_empresa = 8 AND pc.codigo LIKE '7%'
GROUP BY pc.codigo, pc.nombre, pc.nivel, pc.eliminado
ORDER BY pc.codigo;

-- ----------------------------------------------------------------------------
-- 2) LA CONSULTA CLAVE — saldo de la clase 7 por año con los dos criterios.
--    'diferencia' es exactamente lo que el asiento de cierre se llevó de más
--    (o de menos) a la cuenta de patrimonio.
-- ----------------------------------------------------------------------------
WITH mov AS (
    SELECT EXTRACT(YEAR FROM a.fecha_asiento)::int AS anio,
           d.debe, d.haber, a.estado,
           CAST(a.tipo_ambiente AS VARCHAR(1)) AS amb,
           pc.eliminado AS cuenta_eliminada
    FROM asientos_contables_cabecera a
    JOIN asientos_contables_detalle d ON d.id_asiento = a.id AND d.eliminado = false
    JOIN plan_cuentas pc            ON pc.id = d.id_cuenta_contable
    WHERE a.id_empresa = 8 AND a.eliminado = false
      AND pc.id_empresa = 8 AND pc.codigo LIKE '7%' AND pc.nivel = '5'
)
SELECT anio,
       ROUND(SUM(haber - debe), 2) AS saldo_criterio_cierre,
       ROUND(SUM(CASE WHEN estado = 'contabilizado'
                       AND amb = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = 8)
                       AND cuenta_eliminada = false
                      THEN haber - debe ELSE 0 END), 2) AS saldo_criterio_balance,
       ROUND(SUM(haber - debe)
           - SUM(CASE WHEN estado = 'contabilizado'
                       AND amb = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = 8)
                       AND cuenta_eliminada = false
                      THEN haber - debe ELSE 0 END), 2) AS diferencia
FROM mov
GROUP BY anio
ORDER BY anio;

-- ----------------------------------------------------------------------------
-- 3) Los asientos que explican esa diferencia, uno por uno.
--    (los que tocan clase 7 y el Balance NO cuenta)
-- ----------------------------------------------------------------------------
SELECT EXTRACT(YEAR FROM a.fecha_asiento)::int AS anio,
       a.id, a.fecha_asiento, a.numero_comprobante, a.tipo_comprobante,
       a.estado, a.tipo_ambiente, a.modulo_origen,
       pc.codigo AS cuenta, pc.eliminado AS cuenta_eliminada,
       d.debe, d.haber,
       CASE WHEN a.estado <> 'contabilizado'                    THEN 'estado <> contabilizado'
            WHEN CAST(a.tipo_ambiente AS VARCHAR(1))
                 <> (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = 8)
                                                                THEN 'otro ambiente'
            WHEN pc.eliminado                                    THEN 'cuenta eliminada del plan'
       END AS motivo
FROM asientos_contables_cabecera a
JOIN asientos_contables_detalle d ON d.id_asiento = a.id AND d.eliminado = false
JOIN plan_cuentas pc             ON pc.id = d.id_cuenta_contable
WHERE a.id_empresa = 8 AND a.eliminado = false
  AND pc.id_empresa = 8 AND pc.codigo LIKE '7%' AND pc.nivel = '5'
  AND (a.estado <> 'contabilizado'
       OR CAST(a.tipo_ambiente AS VARCHAR(1))
          <> (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = 8)
       OR pc.eliminado)
ORDER BY anio, a.id;

-- ----------------------------------------------------------------------------
-- 4) Asientos de cierre ya generados (los crea la migración, uno por año).
-- ----------------------------------------------------------------------------
SELECT a.id, a.fecha_asiento, a.numero_comprobante, a.concepto,
       a.total_debe, a.total_haber, a.estado, a.tipo_ambiente, a.modulo_origen
FROM asientos_contables_cabecera a
WHERE a.id_empresa = 8 AND a.eliminado = false
  AND (a.tipo_comprobante = 'cierre' OR a.numero_comprobante LIKE 'CIERRE-%')
ORDER BY a.fecha_asiento;

-- Detalle de esos cierres
SELECT a.numero_comprobante, pc.codigo, pc.nombre, d.debe, d.haber
FROM asientos_contables_cabecera a
JOIN asientos_contables_detalle d ON d.id_asiento = a.id AND d.eliminado = false
JOIN plan_cuentas pc             ON pc.id = d.id_cuenta_contable
WHERE a.id_empresa = 8 AND a.eliminado = false
  AND (a.tipo_comprobante = 'cierre' OR a.numero_comprobante LIKE 'CIERRE-%')
ORDER BY a.fecha_asiento, pc.codigo;

-- ----------------------------------------------------------------------------
-- 5) Contexto: resultado de cada año por clase de cuenta, con el criterio del
--    Balance. Sirve para ver si el resultado de 2023 vive en 4/5/6, en la
--    clase 7, o (mal) repartido en ambos.
-- ----------------------------------------------------------------------------
SELECT EXTRACT(YEAR FROM a.fecha_asiento)::int AS anio,
       LEFT(pc.codigo, 1) AS clase,
       ROUND(SUM(d.haber) - SUM(d.debe), 2) AS saldo
FROM asientos_contables_cabecera a
JOIN asientos_contables_detalle d ON d.id_asiento = a.id AND d.eliminado = false
JOIN plan_cuentas pc             ON pc.id = d.id_cuenta_contable AND pc.eliminado = false
WHERE a.id_empresa = 8 AND a.eliminado = false
  AND a.estado = 'contabilizado'
  AND CAST(a.tipo_ambiente AS VARCHAR(1)) = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = 8)
GROUP BY 1, 2
ORDER BY 1, 2;

-- ----------------------------------------------------------------------------
-- 6) ¿Hay períodos contables cerrados? Si 2023 está cerrado (status = 0), la
--    regeneración masiva de asientos NO lo tocará: es la protección correcta
--    antes de regenerar la contabilidad.
-- ----------------------------------------------------------------------------
SELECT id, nombre, fecha_inicial, fecha_final,
       status, CASE WHEN status = 0 THEN 'CERRADO' ELSE 'abierto' END AS situacion
FROM periodos_contables
WHERE id_empresa = 8 AND eliminado = false
ORDER BY fecha_inicial;
