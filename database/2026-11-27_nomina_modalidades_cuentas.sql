-- ============================================================================
-- Nómina: contabilidad por forma de pago de cada beneficio + modalidades
--         guardadas en el rol
-- ----------------------------------------------------------------------------
-- Contexto:
--   1) El asiento y las provisiones del rol leían la forma de pago de los
--      beneficios (Fondos de Reserva, Décimo Tercero, Décimo Cuarto) desde la
--      ficha ACTUAL del empleado. Si se cambiaba la pestaña Laboral, al
--      recalcularse un rol de un mes anterior cambiaban sus provisiones y su
--      asiento. Ahora cada línea del rol guarda cómo se pagaba al generarse.
--   2) Lo pagado en el rol (fondos de reserva "En Rol Mensual", décimos
--      "Mensualiza") se mezclaba en "Ingresos No Gravados", y los fondos de
--      reserva por "Planilla IESS" caían en el mismo pasivo que los acumulados.
--      Se agregan cuatro conceptos OPCIONALES en Configuración Contable → Nómina:
--        FONDOSRESERVAROLNOMINA           Fondos de Reserva pagados en rol   gasto   Debe
--        DECIMOTERCEROROLNOMINA           Décimo Tercero mensualizado        gasto   Debe
--        DECIMOCUARTOROLNOMINA            Décimo Cuarto mensualizado         gasto   Debe
--        FONDOSRESERVAIESSPORPAGARNOMINA  Fondos de Reserva por Pagar IESS   pasivo  Haber
--      Sin cuenta, cada uno usa la del concepto de siempre:
--        pagados en rol / mensualizados → Gasto del beneficio → Ingresos No Gravados
--        Fondos de Reserva por Pagar IESS → Fondos de Reserva por Pagar
--
-- Qué hace:
--   1) Agrega a rol_detalle: decimo_tercero_modo, decimo_cuarto_modo,
--      fondos_reserva_modo (texto, NULL = usar la ficha del empleado).
--   2) Rellena esas columnas en las líneas YA generadas con la ficha actual
--      de cada empleado (así sus números no cambian), corrigiendo solo lo que el
--      propio rol prueba: si la línea pagó un décimo en el rol, ese décimo era
--      "mensualiza"; si pagó fondos de reserva en el rol y la ficha dice
--      "No se paga" o "Planilla IESS", era "rol".
--   3) Inserta los 4 conceptos en asientos_tipo (catálogo GLOBAL, sin id_empresa)
--      y actualiza la descripción de "Ingresos No Gravados".
--   NO toca asientos ya generados, cuentas ya configuradas ni empleados.
--
-- Orden: ejecutar ESTE SQL antes de desplegar el código (el código funciona
--   igual sin él: no guarda las modalidades y lee la ficha, como antes).
--
-- Reversible: sí.
--   ALTER TABLE rol_detalle DROP COLUMN IF EXISTS decimo_tercero_modo,
--                           DROP COLUMN IF EXISTS decimo_cuarto_modo,
--                           DROP COLUMN IF EXISTS fondos_reserva_modo;
--   UPDATE asientos_tipo SET eliminado = true
--   WHERE codigo IN ('FONDOSRESERVAROLNOMINA', 'DECIMOTERCEROROLNOMINA',
--                    'DECIMOCUARTOROLNOMINA', 'FONDOSRESERVAIESSPORPAGARNOMINA');
--
-- Idempotente: se puede ejecutar varias veces sin duplicar nada (el relleno solo
--   toca líneas que todavía no tienen la modalidad guardada).
-- ----------------------------------------------------------------------------
-- CÓMO EJECUTARLO EN pgAdmin
--   Query Tool → pegar TODO el archivo → ejecutar (F5). El relleno recorre
--   rol_detalle una vez; en bases grandes puede tardar unos segundos y bloquea
--   la escritura de roles mientras tanto: mejor en horario de poca actividad.
-- ============================================================================

BEGIN;

-- 1) Columnas de modalidad en la línea del rol ------------------------------
ALTER TABLE rol_detalle ADD COLUMN IF NOT EXISTS decimo_tercero_modo VARCHAR(20);
ALTER TABLE rol_detalle ADD COLUMN IF NOT EXISTS decimo_cuarto_modo  VARCHAR(20);
ALTER TABLE rol_detalle ADD COLUMN IF NOT EXISTS fondos_reserva_modo VARCHAR(20);

COMMENT ON COLUMN rol_detalle.decimo_tercero_modo IS 'Cómo se pagaba el 13º al generar la línea (acumula/mensualiza/no_recibe). NULL = usar la ficha del empleado.';
COMMENT ON COLUMN rol_detalle.decimo_cuarto_modo  IS 'Cómo se pagaba el 14º al generar la línea (acumula/mensualiza/no_recibe). NULL = usar la ficha del empleado.';
COMMENT ON COLUMN rol_detalle.fondos_reserva_modo IS 'Cómo se pagaban los fondos de reserva al generar la línea (no_se_paga/rol/desde_anio/planilla). NULL = usar la ficha del empleado.';

-- 2) Relleno de las líneas ya generadas -------------------------------------
UPDATE rol_detalle d
SET decimo_tercero_modo = CASE
        WHEN EXISTS (SELECT 1 FROM rol_detalle_rubro r
                     WHERE r.id_detalle = d.id AND r.tipo = 'ingreso' AND r.origen = 'decimo'
                       AND r.concepto ILIKE '%tercero%') THEN 'mensualiza'
        ELSE NULLIF(TRIM(e.decimo_tercero), '')
    END,
    decimo_cuarto_modo = CASE
        WHEN EXISTS (SELECT 1 FROM rol_detalle_rubro r
                     WHERE r.id_detalle = d.id AND r.tipo = 'ingreso' AND r.origen = 'decimo'
                       AND r.concepto ILIKE '%cuarto%') THEN 'mensualiza'
        ELSE NULLIF(TRIM(e.decimo_cuarto), '')
    END,
    fondos_reserva_modo = CASE
        WHEN COALESCE(TRIM(e.fondos_reserva), '') IN ('', 'no_se_paga', 'planilla')
         AND EXISTS (SELECT 1 FROM rol_detalle_rubro r
                     WHERE r.id_detalle = d.id AND r.tipo = 'ingreso' AND r.origen = 'fondos') THEN 'rol'
        ELSE NULLIF(TRIM(e.fondos_reserva), '')
    END
FROM empleados e
WHERE e.id = d.id_empleado
  AND d.decimo_tercero_modo IS NULL
  AND d.decimo_cuarto_modo IS NULL
  AND d.fondos_reserva_modo IS NULL;

-- 3) Conceptos de nómina por forma de pago ----------------------------------
INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, tipo_cuenta, debe_haber)
SELECT v.tipo_asiento, v.referencia, v.detalle, v.codigo, v.tipo_cuenta, v.debe_haber
FROM (VALUES
    ('nomina', 'Fondos de Reserva pagados en rol',
     'Fondos de reserva que se pagan en el rol mensual (empleado con «En Rol Mensual», o «a partir del año» una vez cumplido). Opcional: sin cuenta usa Gasto Fondos de Reserva y, si tampoco tiene, Ingresos No Gravados.',
     'FONDOSRESERVAROLNOMINA', 'gasto', 'debe'),
    ('nomina', 'Décimo Tercero mensualizado',
     'Décimo tercero que se paga cada mes en el rol (empleado con «Mensualiza»). Opcional: sin cuenta usa Gasto Décimo Tercero y, si tampoco tiene, Ingresos No Gravados.',
     'DECIMOTERCEROROLNOMINA', 'gasto', 'debe'),
    ('nomina', 'Décimo Cuarto mensualizado',
     'Décimo cuarto que se paga cada mes en el rol (empleado con «Mensualiza»). Opcional: sin cuenta usa Gasto Décimo Cuarto y, si tampoco tiene, Ingresos No Gravados.',
     'DECIMOCUARTOROLNOMINA', 'gasto', 'debe'),
    ('nomina', 'Fondos de Reserva por Pagar IESS',
     'Provisión de fondos de reserva de los empleados con «Planilla IESS» (la empresa los paga directo al IESS). Opcional: sin cuenta usa Fondos de Reserva por Pagar.',
     'FONDOSRESERVAIESSPORPAGARNOMINA', 'pasivo', 'haber')
) AS v(tipo_asiento, referencia, detalle, codigo, tipo_cuenta, debe_haber)
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = v.codigo);

UPDATE asientos_tipo
SET detalle = 'Otros ingresos del rol que NO aportan a IESS (otros ingresos, rubros fijos no gravados). También recibe los fondos de reserva y décimos pagados en el rol cuando ni su concepto propio ni el Gasto del beneficio tienen cuenta.'
WHERE tipo_asiento = 'nomina' AND codigo = 'INGRESOSNOGRAVADOSNOMINA' AND eliminado = false;

COMMIT;

-- ============================================================================
-- COMPROBAR QUE QUEDÓ APLICADO (pegar en el Query Tool después de ejecutar)
-- ----------------------------------------------------------------------------
-- SELECT codigo, referencia, tipo_cuenta, debe_haber
-- FROM asientos_tipo
-- WHERE codigo IN ('FONDOSRESERVAROLNOMINA', 'DECIMOTERCEROROLNOMINA',
--                  'DECIMOCUARTOROLNOMINA', 'FONDOSRESERVAIESSPORPAGARNOMINA')
--   AND eliminado = false;
-- Deben salir 4 filas.
--
-- SELECT COUNT(*) AS lineas_sin_modalidad
-- FROM rol_detalle
-- WHERE fondos_reserva_modo IS NULL AND decimo_tercero_modo IS NULL AND decimo_cuarto_modo IS NULL;
-- Debe salir 0 (o solo líneas de empleados sin ninguna opción cargada en su ficha).
-- ============================================================================
