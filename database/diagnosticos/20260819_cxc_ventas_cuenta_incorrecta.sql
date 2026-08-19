-- Diagnóstico: la línea de "Cuenta por Cobrar" de los asientos de VENTAS quedó apuntando a una
-- cuenta que no es de activo (típicamente la cuenta de INGRESOS / Ventas).
--
-- No escribe nada: solo consulta. Correr con el usuario de solo lectura si se prefiere.
-- Cubre TODAS las empresas a la vez (el problema apareció en una, pero puede haber más).
--
-- Cómo leerlo:
--   * Consultas 1 y 2 → CAUSA (qué cuenta está configurada hoy en cada nivel de la cascada).
--   * Consultas 3 y 4 → EFECTO (qué asientos ya se generaron con la cuenta equivocada).
--   * Consulta 5      → configuración legado invisible para el motor (tipo_referencia='general').
--   * Consulta 6      → alcance en dinero por empresa.
--
-- Recordatorio de cómo resuelve la cuenta el motor (AsientoBuilderService):
--   Cuenta por Cobrar = regla del slot PORCOBRARFACTURAVENTA / PORCOBRARRECIBOVENTA, resuelta en
--   cascada: Cliente > (si el cliente no tiene reglas) Producto > Categoría > Marca >
--   Tipo de producción > General. Cualquiera de esos niveles puede traer la cuenta equivocada.


-- ─────────────────────────────────────────────────────────────────────────────
-- 1) TODAS las reglas del slot de Cuenta por Cobrar (ventas y recibos), en todos los niveles.
--    'clase_cuenta' debe ser 1 (activo). Si dice 4, 5 o 2 → ahí está el error de configuración.
-- ─────────────────────────────────────────────────────────────────────────────
SELECT e.id                        AS id_empresa,
       e.nombre_comercial,
       at.tipo_asiento,
       at.codigo                   AS concepto,
       ap.tipo_referencia          AS nivel,          -- 'asientos tipo'/tipo_asiento = General
       ap.id_referencia,
       COALESCE(cli.nombre, pr.nombre, cat.nombre, mar.nombre, '') AS nivel_nombre,
       pc.codigo                   AS cuenta_codigo,
       pc.nombre                   AS cuenta_nombre,
       LEFT(pc.codigo, 1)          AS clase_cuenta,
       CASE WHEN LEFT(pc.codigo, 1) = '1' THEN 'ok' ELSE '>>> REVISAR <<<' END AS veredicto
FROM asientos_programados ap
JOIN asientos_tipo at   ON at.id = ap.id_asiento_tipo
JOIN empresas e         ON e.id  = ap.id_empresa
LEFT JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
LEFT JOIN clientes   cli ON ap.tipo_referencia = 'cliente'   AND cli.id = ap.id_referencia
LEFT JOIN productos  pr  ON ap.tipo_referencia = 'producto'  AND pr.id  = ap.id_referencia
LEFT JOIN categorias cat ON ap.tipo_referencia = 'categoria' AND cat.id = ap.id_referencia
LEFT JOIN marcas     mar ON ap.tipo_referencia = 'marca'     AND mar.id = ap.id_referencia
WHERE ap.eliminado = false
  AND at.codigo IN ('PORCOBRARFACTURAVENTA', 'PORCOBRARRECIBOVENTA')
ORDER BY veredicto DESC, e.id, at.codigo, ap.tipo_referencia;


-- ─────────────────────────────────────────────────────────────────────────────
-- 2) Barrido general: TODO concepto de ventas cuya cuenta no cuadra con su naturaleza esperada.
--    (CxC e Inventario → clase 1; IVA/ICE → clase 2; Subtotal/Descuento/Propina → clase 4;
--     Costo → clase 5). Detecta también el caso inverso (Subtotal apuntando a la CxC, etc.).
-- ─────────────────────────────────────────────────────────────────────────────
SELECT ap.id_empresa,
       e.nombre_comercial,
       at.tipo_asiento,
       at.codigo        AS concepto,
       ap.tipo_referencia AS nivel,
       ap.id_referencia,
       pc.codigo        AS cuenta_codigo,
       pc.nombre        AS cuenta_nombre,
       CASE
           WHEN at.codigo LIKE 'PORCOBRAR%'  THEN '1'
           WHEN at.codigo LIKE 'INVENTARIO%' THEN '1'
           WHEN at.codigo LIKE 'IVA%'        THEN '2'
           WHEN at.codigo LIKE 'ICE%'        THEN '2'
           WHEN at.codigo LIKE 'SUBTOTAL%'   THEN '4'
           WHEN at.codigo LIKE 'DESCUENTO%'  THEN '4'
           WHEN at.codigo LIKE 'PROPINA%'    THEN '4'
           WHEN at.codigo LIKE 'COSTO%'      THEN '5'
       END AS clase_esperada,
       LEFT(pc.codigo, 1) AS clase_real
FROM asientos_programados ap
JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo
JOIN empresas e       ON e.id  = ap.id_empresa
JOIN plan_cuentas pc  ON pc.id = ap.id_cuenta
WHERE ap.eliminado = false
  AND at.tipo_asiento IN ('ventas_factura', 'recibos_venta')
  AND LEFT(pc.codigo, 1) <> CASE
           WHEN at.codigo LIKE 'PORCOBRAR%'  THEN '1'
           WHEN at.codigo LIKE 'INVENTARIO%' THEN '1'
           WHEN at.codigo LIKE 'IVA%'        THEN '2'
           WHEN at.codigo LIKE 'ICE%'        THEN '2'
           WHEN at.codigo LIKE 'SUBTOTAL%'   THEN '4'
           WHEN at.codigo LIKE 'DESCUENTO%'  THEN '4'
           WHEN at.codigo LIKE 'PROPINA%'    THEN '4'
           WHEN at.codigo LIKE 'COSTO%'      THEN '5'
           ELSE LEFT(pc.codigo, 1)
       END
ORDER BY ap.id_empresa, at.codigo;


-- ─────────────────────────────────────────────────────────────────────────────
-- 3) EFECTO: asientos de venta ya generados cuya línea de Cuenta por Cobrar NO es de activo.
--    (Se identifica la línea por su referencia; el builder la etiqueta 'Cuenta por Cobrar'
--     o 'Cuenta por Cobrar · por línea' / '· propina'.)
-- ─────────────────────────────────────────────────────────────────────────────
SELECT c.id_empresa,
       c.modulo_origen,
       c.id_referencia_origen,
       c.fecha_asiento,
       c.estado,
       d.referencia_detalle,
       pc.codigo AS cuenta_codigo,
       pc.nombre AS cuenta_nombre,
       d.debe
FROM asientos_contables_cabecera c
JOIN asientos_contables_detalle d ON d.id_asiento = c.id AND d.eliminado = false
JOIN plan_cuentas pc              ON pc.id = d.id_cuenta_contable
WHERE c.eliminado = false
  AND c.estado <> 'anulado'
  AND c.modulo_origen IN ('factura_venta', 'recibo_venta')
  AND d.debe > 0
  AND d.referencia_detalle ILIKE '%cobrar%'
  AND LEFT(pc.codigo, 1) <> '1'
ORDER BY c.id_empresa, c.fecha_asiento DESC;


-- ─────────────────────────────────────────────────────────────────────────────
-- 4) Variante del efecto que no depende del texto de la referencia: asientos de venta donde
--    NINGUNA línea del Debe usa la cuenta que hoy está configurada como Cuenta por Cobrar.
--    Sirve para las empresas donde la referencia venga vacía o distinta.
-- ─────────────────────────────────────────────────────────────────────────────
WITH cta_cxc AS (
    SELECT ap.id_empresa, ap.id_cuenta
    FROM asientos_programados ap
    JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo
    WHERE ap.eliminado = false
      AND at.codigo = 'PORCOBRARFACTURAVENTA'
      AND ap.id_referencia = at.id
      AND (ap.tipo_referencia = 'asientos tipo' OR ap.tipo_referencia = at.tipo_asiento)
)
SELECT c.id_empresa,
       c.id_referencia_origen AS id_venta,
       c.fecha_asiento,
       c.total_debe,
       string_agg(DISTINCT pc.codigo || ' ' || pc.nombre, ' | ') AS cuentas_en_debe
FROM asientos_contables_cabecera c
JOIN asientos_contables_detalle d ON d.id_asiento = c.id AND d.eliminado = false AND d.debe > 0
JOIN plan_cuentas pc              ON pc.id = d.id_cuenta_contable
LEFT JOIN cta_cxc x               ON x.id_empresa = c.id_empresa
WHERE c.eliminado = false
  AND c.estado <> 'anulado'
  AND c.modulo_origen = 'factura_venta'
GROUP BY c.id_empresa, c.id, c.id_referencia_origen, c.fecha_asiento, c.total_debe
HAVING NOT bool_or(d.id_cuenta_contable = (SELECT id_cuenta FROM cta_cxc y WHERE y.id_empresa = c.id_empresa LIMIT 1))
ORDER BY c.id_empresa, c.fecha_asiento DESC;


-- ─────────────────────────────────────────────────────────────────────────────
-- 5) Configuración legado que el motor NO lee: filas con tipo_referencia='general' (o con
--    id_referencia distinto del id del asiento_tipo). El lector exige
--    id_referencia = asientos_tipo.id  Y  tipo_referencia IN ('asientos tipo', tipo_asiento).
--    Una empresa que solo tenga estas filas se comporta como si NO tuviera cuentas configuradas.
-- ─────────────────────────────────────────────────────────────────────────────
SELECT ap.id_empresa,
       e.nombre_comercial,
       at.tipo_asiento,
       at.codigo AS concepto,
       ap.tipo_referencia,
       ap.id_referencia,
       at.id     AS id_asiento_tipo_esperado,
       pc.codigo AS cuenta_codigo,
       pc.nombre AS cuenta_nombre
FROM asientos_programados ap
JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo
JOIN empresas e       ON e.id  = ap.id_empresa
LEFT JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
WHERE ap.eliminado = false
  AND ap.id_asiento_tipo > 0
  AND ap.tipo_referencia NOT IN ('cliente','proveedor','producto','categoria','marca','tipo_produccion','item_compra','empleado')
  AND (ap.id_referencia IS DISTINCT FROM at.id
       OR ap.tipo_referencia NOT IN ('asientos tipo', at.tipo_asiento))
ORDER BY ap.id_empresa, at.tipo_asiento, at.codigo;


-- ─────────────────────────────────────────────────────────────────────────────
-- 6) Alcance en dinero: cuánto se cargó a cuentas que no son de activo en el Debe de los
--    asientos de venta, por empresa y cuenta.
-- ─────────────────────────────────────────────────────────────────────────────
SELECT c.id_empresa,
       pc.codigo,
       pc.nombre,
       count(*)      AS lineas,
       SUM(d.debe)   AS total_debe
FROM asientos_contables_cabecera c
JOIN asientos_contables_detalle d ON d.id_asiento = c.id AND d.eliminado = false AND d.debe > 0
JOIN plan_cuentas pc              ON pc.id = d.id_cuenta_contable
WHERE c.eliminado = false
  AND c.estado <> 'anulado'
  AND c.modulo_origen IN ('factura_venta', 'recibo_venta')
  AND d.referencia_detalle ILIKE '%cobrar%'
GROUP BY c.id_empresa, pc.codigo, pc.nombre
ORDER BY c.id_empresa, total_debe DESC;
