-- =============================================================================
-- Cuentas que asignó el botón "Configurar cuentas sugeridas" (Configuración Contable).
--
-- El botón se retiró el 17-09-2026 porque ponía cuentas equivocadas: tomaba cada cuenta del
-- plan de cuentas modelo buscándola SOLO POR CÓDIGO en el plan que la empresa ya tenía
-- (migrado del sistema anterior o cargado por Excel), y en esos planes el mismo código suele
-- ser otra cuenta. Ejemplo visto en la base de desarrollo: el concepto "Descuento en el recibo
-- de venta" quedó con 4.1.1.02.001 "Ventas de Mercadería 0%". Quitar el botón no deshace lo
-- que ya grabó; este script lo lista.
--
-- Toca datos: NO. Solo lectura; se puede ejecutar las veces que haga falta.
--
-- Cómo reconoce lo que grabó el botón: todo se escribió en la misma transacción que su registro
-- en log_sistema (accion = 'CONFIGURACION SUGERIDA'), así que comparte la hora y el usuario de
-- ese registro:
--   asientos_programados             created_at / created_by  (tipos de asiento e IVA por tarifa)
--   empresa_formas_pago              updated_at / updated_by  (Efectivo, Anticipos)
--   empresa_opciones_ingreso_egreso  updated_at / updated_by, o created_at / created_by cuando la
--                                    opción la creó el botón (SRI, IESS)
-- Una forma u opción que se editó después ya no comparte la hora: se lista igual si su cuenta
-- actual tiene el código que puso el botón, con origen = 'probable'.
--
-- Columnas para decidir:
--   revision  REVISAR · nombre distinto  el nombre de la cuenta en el plan de la empresa no es
--                                        el del plan modelo: lo más probable es que sea otra cuenta.
--             REVISAR · cuenta de grupo  la cuenta tiene subcuentas; no debería recibir movimientos.
--             coincide con el modelo     mismo nombre; en principio está bien.
--             ya no se usa               la regla se eliminó después.
--   estado    sigue igual | modificada después | eliminada después | creada por el botón
--
-- Uso en pgAdmin: ejecutar todo (F5). Para ver una sola empresa, cambiar v_emp (0 = todas).
-- =============================================================================

WITH p AS (
    SELECT 0::int AS v_emp                      -- <<< AJUSTAR: id de la empresa (0 = todas)
),
usos AS (
    SELECT l.created_at AS ts, l.id_empresa, l.id_usuario
      FROM log_sistema l
     CROSS JOIN p
     WHERE l.accion = 'CONFIGURACION SUGERIDA'
       AND (p.v_emp = 0 OR l.id_empresa = p.v_emp)
),
-- Lo que el botón pretendía asignar: copia de PlanCuentaService (mapeos del plan modelo,
-- CUENTAS_FORMAS_PAGO y CUENTAS_OPCIONES), iguales desde el 18-08-2026 hasta su retiro.
modelo (clave, codigo_modelo, nombre_modelo) AS (
    VALUES
        -- Tipos de asiento (asientos_tipo.codigo)
        ('PORCOBRARFACTURAVENTA',                        '1.1.2.01.001',  'Cuentas por cobrar clientes'),
        ('PORCOBRARRECIBOVENTA',                         '1.1.2.01.001',  'Cuentas por cobrar clientes'),
        ('ANTICIPOSDESCUENTOSNOMINA',                    '1.1.2.03.001',  'Anticipos a Empleados'),
        ('INVENTARIOFACTURAVENTA',                       '1.1.3.01.001',  'Mercadería para la Venta'),
        ('INVENTARIORECIBOVENTA',                        '1.1.3.01.001',  'Mercadería para la Venta'),
        ('INVENTARIOFACTURACOMPRA',                      '1.1.3.01.001',  'Mercadería para la Venta'),
        ('PORPAGARFACTURACOMPRA',                        '2.1.1.01.001',  'Cuentas por pagar proveedores'),
        ('IESSPORPAGARNOMINA',                           '2.1.2.01.001',  'IESS por Pagar'),
        ('ICEFACTURAVENTA',                              '2.1.3.04.001',  'ICE por Pagar'),
        ('ICERECIBOVENTA',                               '2.1.3.04.001',  'ICE por Pagar'),
        ('DESAHUCIOPORPAGARNOMINA',                      '2.1.4.02.002',  'Desahucio por Pagar'),
        ('SUELDOSPORPAGARNOMINA',                        '2.1.4.03.001',  'Sueldos por Pagar'),
        ('DECIMOTERCEROPORPAGARNOMINA',                  '2.1.4.03.002',  'Décimo Tercer Sueldo por Pagar'),
        ('DECIMOCUARTOPORPAGARNOMINA',                   '2.1.4.03.003',  'Décimo Cuarto Sueldo por Pagar'),
        ('VACACIONESPORPAGARNOMINA',                     '2.1.4.03.004',  'Vacaciones por Pagar'),
        ('FONDOSRESERVAPORPAGARNOMINA',                  '2.1.4.03.005',  'Fondos de Reserva por Pagar'),
        ('UTILIDADEJERCICIOCIERRE',                      '3.3.1.01.001',  'Utilidad del Ejercicio'),
        ('PERDIDAEJERCICIOCIERRE',                       '3.3.1.01.002',  'Pérdida del Ejercicio'),
        ('SUBTOTALFACTURAVENTA',                         '4.1.1.01.001',  'VENTAS DEL NEGOCIO'),
        ('SUBTOTALRECIBOVENTA',                          '4.1.1.01.001',  'VENTAS DEL NEGOCIO'),
        ('DESCUENTOFACTURAVENTA',                        '4.1.1.02.001',  'Descuento en Ventas'),
        ('DESCUENTORECIBOVENTA',                         '4.1.1.02.001',  'Descuento en Ventas'),
        ('PROPINAFACTURAVENTA',                          '4.1.1.03.001',  'Propina en Ventas'),
        ('PROPINARECIBOVENTA',                           '4.1.1.03.001',  'Propina en Ventas'),
        ('COSTOFACTURAVENTA',                            '5.1.1.01.001',  'Costo de Mercadería'),
        ('COSTORECIBOVENTA',                             '5.1.1.01.001',  'Costo de Mercadería'),
        ('DESCUENTOFACTURACOMPRA',                       '5.1.1.02.001',  'Descuento en Compras'),
        ('GASTOSUELDOSNOMINA',                           '5.2.1.01.001',  'Sueldos y salarios'),
        ('GASTODECIMOTERCERONOMINA',                     '5.2.1.01.002',  'Décimo Tercer Sueldo'),
        ('GASTODECIMOCUARTONOMINA',                      '5.2.1.01.003',  'Décimo Cuarto Sueldo'),
        ('GASTOVACACIONESNOMINA',                        '5.2.1.01.004',  'Vacaciones'),
        ('GASTOFONDOSRESERVANOMINA',                     '5.2.1.01.005',  'Fondos de Reserva'),
        ('GASTOAPORTEPATRONALNOMINA',                    '5.2.1.01.006',  'Aporte Patronal al IESS'),
        ('GASTODESAHUCIONOMINA',                         '5.2.1.01.007',  'Desahucio'),
        ('SUBTOTALFACTURACOMPRA',                        '5.2.1.05.004',  'Compras y Gastos Generales'),
        ('ICEFACTURACOMPRA',                             '5.2.1.05.005',  'ICE en Compras'),
        ('PROPINAFACTURACOMPRA',                         '5.2.1.05.006',  'Propina en Compras'),
        ('AJUSTEREDONDEOVENTA',                          '5.2.2.01.004',  'Ajuste por redondeo'),
        ('AJUSTEREDONDEORECIBOVENTA',                    '5.2.2.01.004',  'Ajuste por redondeo'),
        ('AJUSTEREDONDEOCOMPRA',                         '5.2.2.01.004',  'Ajuste por redondeo'),
        -- IVA por tarifa (tipo_referencia:porcentaje)
        ('iva_compras_factura:15',                       '1.1.4.01.001',  'Iva en Compras 15%'),
        ('iva_compras_factura:5',                        '1.1.4.01.002',  'Iva en Compras 5%'),
        ('iva_ventas_factura:15',                        '2.1.3.01.001',  'Iva en Ventas 15%'),
        ('iva_recibos_venta:15',                         '2.1.3.01.001',  'Iva en Ventas 15%'),
        ('iva_ventas_factura:5',                         '2.1.3.01.003',  'Iva en Ventas 5%'),
        ('iva_recibos_venta:5',                          '2.1.3.01.003',  'Iva en Ventas 5%'),
        -- Formas de cobro/pago (FORMA:tipo:aplica_en:NOMBRE)
        ('FORMA:EFECTIVO:AMBAS:EFECTIVO',                '1.1.1.01.002',  'Caja General'),
        ('FORMA:ANTICIPO:INGRESO:ANTICIPOS CLIENTES',    '2.1.1.02.001',  'Anticipo clientes'),
        ('FORMA:ANTICIPO:EGRESO:ANTICIPOS PROVEEDORES',  '1.1.2.02.001',  'Anticipo proveedores'),
        -- Opciones de ingreso/egreso (OPCION:comportamiento, o OPCION:NOMBRE en las GENERAL)
        ('OPCION:ANTICIPO_CLIENTE',                      '2.1.1.02.001',  'Anticipo clientes'),
        ('OPCION:ANTICIPO_PROVEEDOR',                    '1.1.2.02.001',  'Anticipo proveedores'),
        ('OPCION:SRI',                                   '2.1.3.01.002',  'SRI por pagar'),
        ('OPCION:IESS',                                  '2.1.2.01.001',  'IESS por Pagar')
),
asignaciones AS (
    -- Tipos de asiento e IVA por tarifa
    SELECT u.ts, u.id_empresa, u.id_usuario,
           CASE WHEN ap.id_asiento_tipo = 0 THEN 'IVA por tarifa' ELSE 'Tipo de asiento' END AS donde,
           CASE WHEN ap.id_asiento_tipo = 0
                THEN CASE ap.tipo_referencia
                         WHEN 'iva_ventas_factura'  THEN 'IVA en ventas con factura '
                         WHEN 'iva_compras_factura' THEN 'IVA en compras '
                         WHEN 'iva_recibos_venta'   THEN 'IVA en recibos de venta '
                         ELSE ap.tipo_referencia || ' '
                     END || COALESCE(t.tarifa, 'tarifa ' || ap.id_referencia)
                ELSE COALESCE(at.tipo_asiento || ' · ' || at.referencia, 'tipo de asiento ' || ap.id_asiento_tipo)
           END AS concepto,
           CASE WHEN ap.id_asiento_tipo = 0 THEN ap.tipo_referencia || ':' || t.porcentaje_iva
                ELSE at.codigo
           END AS clave,
           ap.id_cuenta,
           CASE WHEN ap.eliminado THEN 'eliminada después'
                WHEN COALESCE(ap.updated_at, ap.created_at) > ap.created_at + interval '2 seconds' THEN 'modificada después'
                ELSE 'sigue igual'
           END AS estado,
           'confirmado'::text AS origen,
           'asientos_programados'::text AS tabla,
           ap.id AS id_registro
      FROM usos u
      JOIN asientos_programados ap
        ON ap.id_empresa = u.id_empresa
       AND ap.created_by = u.id_usuario
       AND ap.created_at BETWEEN u.ts - interval '2 seconds' AND u.ts + interval '2 seconds'
      LEFT JOIN asientos_tipo at ON ap.id_asiento_tipo > 0 AND at.id = ap.id_asiento_tipo
      LEFT JOIN tarifa_iva t     ON ap.id_asiento_tipo = 0 AND CAST(t.codigo AS INTEGER) = ap.id_referencia

    UNION ALL

    -- Formas de cobro/pago
    SELECT u.ts, u.id_empresa, u.id_usuario,
           'Forma de cobro/pago',
           f.nombre || ' (' || f.aplica_en || ')',
           'FORMA:' || f.tipo || ':' || f.aplica_en || ':' || UPPER(TRIM(f.nombre)),
           f.id_cuenta_contable,
           CASE WHEN f.eliminado THEN 'eliminada después'
                WHEN f.updated_at > u.ts + interval '2 seconds' THEN 'modificada después'
                ELSE 'sigue igual'
           END,
           CASE WHEN f.updated_by = u.id_usuario
                 AND f.updated_at BETWEEN u.ts - interval '2 seconds' AND u.ts + interval '2 seconds'
                THEN 'confirmado' ELSE 'probable'
           END,
           'empresa_formas_pago',
           f.id
      FROM usos u
      JOIN empresa_formas_pago f
        ON f.id_empresa = u.id_empresa
       AND f.id_cuenta_contable IS NOT NULL
       AND COALESCE(f.created_at, u.ts) <= u.ts + interval '2 seconds'
       AND (f.tipo, f.aplica_en, UPPER(TRIM(f.nombre))) IN (('EFECTIVO', 'AMBAS',   'EFECTIVO'),
                                                          ('ANTICIPO', 'INGRESO', 'ANTICIPOS CLIENTES'),
                                                          ('ANTICIPO', 'EGRESO',  'ANTICIPOS PROVEEDORES'))

    UNION ALL

    -- Opciones de ingreso/egreso
    SELECT u.ts, u.id_empresa, u.id_usuario,
           'Opción de ingreso/egreso',
           o.nombre || ' (' || o.comportamiento || ')',
           CASE WHEN UPPER(o.comportamiento) IN ('ANTICIPO_CLIENTE', 'ANTICIPO_PROVEEDOR')
                THEN 'OPCION:' || UPPER(o.comportamiento)
                ELSE 'OPCION:' || UPPER(TRIM(o.nombre))
           END,
           o.id_cuenta_contable,
           CASE WHEN o.eliminado THEN 'eliminada después'
                WHEN o.updated_at > u.ts + interval '2 seconds' THEN 'modificada después'
                WHEN o.created_by = u.id_usuario
                 AND o.created_at BETWEEN u.ts - interval '2 seconds' AND u.ts + interval '2 seconds'
                THEN 'creada por el botón'
                ELSE 'sigue igual'
           END,
           CASE WHEN (o.updated_by = u.id_usuario AND o.updated_at BETWEEN u.ts - interval '2 seconds' AND u.ts + interval '2 seconds')
                  OR (o.created_by = u.id_usuario AND o.created_at BETWEEN u.ts - interval '2 seconds' AND u.ts + interval '2 seconds')
                THEN 'confirmado' ELSE 'probable'
           END,
           'empresa_opciones_ingreso_egreso',
           o.id
      FROM usos u
      JOIN empresa_opciones_ingreso_egreso o
        ON o.id_empresa = u.id_empresa
       AND o.id_cuenta_contable IS NOT NULL
       AND COALESCE(o.created_at, u.ts) <= u.ts + interval '2 seconds'
       AND (UPPER(o.comportamiento) IN ('ANTICIPO_CLIENTE', 'ANTICIPO_PROVEEDOR')
            OR UPPER(TRIM(o.nombre)) IN ('SRI', 'IESS'))
),
revisadas AS (
    SELECT a.*,
           pc.codigo AS cuenta_codigo,
           pc.nombre AS cuenta_nombre,
           m.codigo_modelo,
           m.nombre_modelo,
           CASE
               WHEN a.estado = 'eliminada después' THEN 'ya no se usa'
               WHEN m.clave IS NULL THEN 'REVISAR · concepto fuera del modelo'
               WHEN pc.id IS NULL   THEN 'REVISAR · la cuenta ya no existe en el plan'
               WHEN EXISTS (SELECT 1
                              FROM plan_cuentas h
                             WHERE h.id_empresa = a.id_empresa
                               AND h.eliminado = false
                               AND h.codigo LIKE pc.codigo || '.%') THEN 'REVISAR · cuenta de grupo'
               WHEN regexp_replace(lower(translate(pc.nombre,       'áéíóúüñÁÉÍÓÚÜÑ', 'aeiouunAEIOUUN')), '[^a-z0-9]', '', 'g')
                 <> regexp_replace(lower(translate(m.nombre_modelo, 'áéíóúüñÁÉÍÓÚÜÑ', 'aeiouunAEIOUUN')), '[^a-z0-9]', '', 'g')
                    THEN 'REVISAR · nombre distinto'
               ELSE 'coincide con el modelo'
           END AS revision
      FROM asignaciones a
      LEFT JOIN modelo m        ON m.clave = a.clave
      LEFT JOIN plan_cuentas pc ON pc.id = a.id_cuenta AND pc.eliminado = false
)
SELECT r.id_empresa,
       e.nombre                                     AS empresa,
       to_char(r.ts, 'DD-MM-YYYY HH24:MI:SS')       AS uso_del_boton,
       us.nombre                                    AS usuario,
       r.revision,
       r.donde,
       r.concepto,
       r.cuenta_codigo || ' - ' || r.cuenta_nombre  AS cuenta_asignada,
       r.codigo_modelo || ' - ' || r.nombre_modelo  AS cuenta_del_modelo,
       r.estado,
       r.origen,
       r.tabla,
       r.id_registro
  FROM revisadas r
  LEFT JOIN empresas e  ON e.id  = r.id_empresa
  LEFT JOIN usuarios us ON us.id = r.id_usuario
 WHERE r.origen = 'confirmado'
    OR (r.estado <> 'eliminada después' AND r.cuenta_codigo = r.codigo_modelo)
 ORDER BY r.id_empresa, r.ts, (r.revision LIKE 'REVISAR%') DESC, r.donde, r.concepto;
