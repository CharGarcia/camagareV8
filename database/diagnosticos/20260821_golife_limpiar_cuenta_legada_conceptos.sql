-- GOLIFE (empresa 24) - Paso 1 de 2: limpiar la cuenta legada de los conceptos que toman su
-- cuenta del propio modulo (FACTURA_VENTA, RECIBO_VENTA, COMPRA, LIQUIDACION, ROL).
--
-- POR QUE: esos conceptos no deben tener cuenta propia. La pantalla de Configuracion Contable
-- ni los muestra ni deja asignarles una, pero los 5 tienen guardada 1.1.1.02.001 BANCO
-- BOLIVARIANO --la misma cuenta de las formas de cobro-- en los DOS sitios donde el motor
-- podria leerla:
--     a) empresa_opciones_ingreso_egreso.id_cuenta_contable  (columna legada)
--     b) asientos_programados con tipo_referencia opcion_ingreso / opcion_egreso
-- Ese valor es el que produjo 14 asientos de ingreso con el banco en el Debe Y en el Haber.
--
-- ORDEN: ejecutar DESPUES de configurar la regla General PORCOBRARFACTURAVENTA y
-- PORPAGARFACTURACOMPRA en GOLIFE (hoy solo existen a nivel categoria), y ANTES de regenerar
-- los asientos con 20260821_golife_regenerar_asientos_ingreso.php.
--
-- PARA ENSAYAR SIN ESCRIBIR: cambie el COMMIT del final por ROLLBACK.


-- ---------------------------------------------------------------------------
-- 0) ANTES: como esta hoy (guardar esta salida por si hay que revertir).
-- ---------------------------------------------------------------------------
SELECT o.id            AS id_concepto,
       o.nombre,
       o.comportamiento,
       o.id_cuenta_contable,
       pc.codigo       AS cuenta_legada,
       ap.id           AS id_regla_programada,
       pcp.codigo      AS cuenta_regla_programada
FROM empresa_opciones_ingreso_egreso o
LEFT JOIN plan_cuentas pc ON pc.id = o.id_cuenta_contable
LEFT JOIN asientos_programados ap
       ON ap.id_referencia = o.id
      AND ap.tipo_referencia IN ('opcion_ingreso', 'opcion_egreso')
      AND ap.id_empresa = o.id_empresa
      AND ap.eliminado = false
LEFT JOIN plan_cuentas pcp ON pcp.id = ap.id_cuenta
WHERE o.id_empresa = 24
  AND o.eliminado = false
  AND UPPER(o.comportamiento) IN ('FACTURA_VENTA', 'RECIBO_VENTA', 'COMPRA', 'LIQUIDACION', 'ROL')
ORDER BY o.nombre;


BEGIN;

-- ---------------------------------------------------------------------------
-- 1) Quitar la cuenta legada del concepto.
-- ---------------------------------------------------------------------------
UPDATE empresa_opciones_ingreso_egreso
   SET id_cuenta_contable = NULL,
       updated_at         = CURRENT_TIMESTAMP
 WHERE id_empresa = 24
   AND eliminado = false
   AND UPPER(comportamiento) IN ('FACTURA_VENTA', 'RECIBO_VENTA', 'COMPRA', 'LIQUIDACION', 'ROL')
   AND id_cuenta_contable IS NOT NULL;


-- ---------------------------------------------------------------------------
-- 2) Eliminar logicamente la regla programada equivalente (mismo criterio: eliminacion
--    logica, nunca DELETE fisico).
-- ---------------------------------------------------------------------------
UPDATE asientos_programados ap
   SET eliminado  = true,
       deleted_at = CURRENT_TIMESTAMP,
       updated_at = CURRENT_TIMESTAMP
  FROM empresa_opciones_ingreso_egreso o
 WHERE ap.id_referencia = o.id
   AND ap.tipo_referencia IN ('opcion_ingreso', 'opcion_egreso')
   AND ap.id_empresa = 24
   AND ap.eliminado = false
   AND o.id_empresa = 24
   AND o.eliminado = false
   AND UPPER(o.comportamiento) IN ('FACTURA_VENTA', 'RECIBO_VENTA', 'COMPRA', 'LIQUIDACION', 'ROL');


-- ---------------------------------------------------------------------------
-- 3) DESPUES: verificacion. Las 5 filas deben quedar con cuenta_legada y
--    cuenta_regla_programada en NULL. Si no, NO confirme: cambie COMMIT por ROLLBACK.
-- ---------------------------------------------------------------------------
SELECT o.id            AS id_concepto,
       o.nombre,
       o.comportamiento,
       pc.codigo       AS cuenta_legada,
       pcp.codigo      AS cuenta_regla_programada
FROM empresa_opciones_ingreso_egreso o
LEFT JOIN plan_cuentas pc ON pc.id = o.id_cuenta_contable
LEFT JOIN asientos_programados ap
       ON ap.id_referencia = o.id
      AND ap.tipo_referencia IN ('opcion_ingreso', 'opcion_egreso')
      AND ap.id_empresa = o.id_empresa
      AND ap.eliminado = false
LEFT JOIN plan_cuentas pcp ON pcp.id = ap.id_cuenta
WHERE o.id_empresa = 24
  AND o.eliminado = false
  AND UPPER(o.comportamiento) IN ('FACTURA_VENTA', 'RECIBO_VENTA', 'COMPRA', 'LIQUIDACION', 'ROL')
ORDER BY o.nombre;


COMMIT;
