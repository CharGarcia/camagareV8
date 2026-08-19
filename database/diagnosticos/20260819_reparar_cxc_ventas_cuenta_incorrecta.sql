-- Reparación del error "la Cuenta por Cobrar de las ventas apunta a una cuenta de Ingresos".
--
-- Detectado el 19-08-2026 con 20260819_cxc_ventas_cuenta_incorrecta.sql:
--   empresa  1 (CARLOS MAURICIO GARCIA REVELO): 1 regla por CLIENTE  (id 74)
--   empresa 37 (BEDOYA Y ASOCIADOS)           : 7 reglas por PRODUCTO
-- En ambos casos la cuenta puesta en el concepto "Cuenta por cobrar" es de clase 4 (Ventas),
-- que es la que correspondía al concepto "Subtotal". Como Producto/Cliente ganan a Categoría y
-- a la regla General en la cascada del motor, TODA factura que incluya esa entidad se contabilizó
-- con la cuenta de ingresos en el Debe.
--
-- ORDEN DE EJECUCIÓN: pasos 1 → 2 → 3 → 4. Los pasos 2 y 3 vienen envueltos en una transacción
-- con SELECT de control: revisar el resultado ANTES del COMMIT.

-- ═════════════════════════════════════════════════════════════════════════════
-- PASO 1 — Catálogo GLOBAL: cerrar la puerta que permitió elegir la cuenta equivocada.
-- ═════════════════════════════════════════════════════════════════════════════
-- asientos_tipo es global (sin id_empresa). Su columna tipo_cuenta es la lista de clases que el
-- buscador de cuentas ofrece para ese concepto. Los dos conceptos de cartera de ventas declaran
-- 'activo,ingreso', así que el campo "Cuenta por cobrar" ofrecía también las cuentas 4.x —
-- su espejo de compras (PORPAGARFACTURACOMPRA) siempre declaró solo 'pasivo'.

-- 1a. Ver el estado actual
SELECT id, tipo_asiento, codigo, referencia, debe_haber, tipo_cuenta
FROM asientos_tipo
WHERE codigo IN ('PORCOBRARFACTURAVENTA', 'PORCOBRARRECIBOVENTA');

-- 1b. Corregir
UPDATE asientos_tipo
SET tipo_cuenta = 'activo',
    updated_at  = NOW()
WHERE codigo IN ('PORCOBRARFACTURAVENTA', 'PORCOBRARRECIBOVENTA')
  AND tipo_cuenta <> 'activo';


-- ═════════════════════════════════════════════════════════════════════════════
-- PASO 2 — Reglas mal configuradas: mover al concepto correcto o darlas de baja.
-- ═════════════════════════════════════════════════════════════════════════════
-- Criterio: la cuenta de ingresos que se cargó en "Cuenta por cobrar" pertenece al concepto
-- "Subtotal" del mismo tipo de asiento.
--   * Si esa dimensión (cliente/producto/...) NO tiene todavía regla de Subtotal → se MUEVE
--     (cambia de concepto y conserva la cuenta: la intención del usuario se respeta).
--   * Si YA tiene regla de Subtotal → se da de BAJA lógica (sería duplicada); esa dimensión pasa
--     a heredar la Cuenta por Cobrar de Categoría/General, que ya es la correcta.

BEGIN;

-- 2a. Qué se va a tocar (revisar esta salida antes de seguir)
WITH malas AS (
    SELECT ap.id, ap.id_empresa, ap.tipo_referencia, ap.id_referencia, ap.referencia_texto,
           at.tipo_asiento, at.codigo AS concepto_actual, pc.codigo AS cuenta_codigo,
           (SELECT s.id FROM asientos_tipo s
             WHERE s.tipo_asiento = at.tipo_asiento AND s.codigo LIKE 'SUBTOTAL%'
               AND s.eliminado = false LIMIT 1) AS id_subtotal
    FROM asientos_programados ap
    JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo
    JOIN plan_cuentas pc  ON pc.id = ap.id_cuenta
    WHERE ap.eliminado = false
      AND at.codigo IN ('PORCOBRARFACTURAVENTA', 'PORCOBRARRECIBOVENTA')
      AND LEFT(pc.codigo, 1) <> '1'
)
SELECT m.*,
       EXISTS (
           SELECT 1 FROM asientos_programados x
           WHERE x.id_empresa       = m.id_empresa
             AND x.id_asiento_tipo  = m.id_subtotal
             AND x.tipo_referencia  = m.tipo_referencia
             AND x.id_referencia IS NOT DISTINCT FROM m.id_referencia
             AND x.referencia_texto IS NOT DISTINCT FROM m.referencia_texto
             AND x.eliminado = false
       ) AS ya_tiene_subtotal
FROM malas m
ORDER BY m.id_empresa, m.tipo_referencia, m.id_referencia;

-- 2b. MOVER al concepto Subtotal las que no chocan con una regla existente
UPDATE asientos_programados ap
SET id_asiento_tipo = sub.id,
    updated_at      = NOW()
FROM asientos_tipo at, asientos_tipo sub, plan_cuentas pc
WHERE at.id  = ap.id_asiento_tipo
  AND pc.id  = ap.id_cuenta
  AND ap.eliminado = false
  AND at.codigo IN ('PORCOBRARFACTURAVENTA', 'PORCOBRARRECIBOVENTA')
  AND LEFT(pc.codigo, 1) <> '1'
  AND sub.tipo_asiento = at.tipo_asiento
  AND sub.codigo LIKE 'SUBTOTAL%'
  AND sub.eliminado = false
  AND NOT EXISTS (
      SELECT 1 FROM asientos_programados x
      WHERE x.id_empresa       = ap.id_empresa
        AND x.id_asiento_tipo  = sub.id
        AND x.tipo_referencia  = ap.tipo_referencia
        AND x.id_referencia IS NOT DISTINCT FROM ap.id_referencia
        AND x.referencia_texto IS NOT DISTINCT FROM ap.referencia_texto
        AND x.eliminado = false
  );

-- 2c. BAJA LÓGICA de las que sí chocaban (ya existía la regla de Subtotal para esa dimensión).
--     Reemplazar 0 por el id del usuario que ejecuta la corrección.
UPDATE asientos_programados ap
SET eliminado  = true,
    deleted_at = NOW(),
    deleted_by = 0
FROM asientos_tipo at, plan_cuentas pc
WHERE at.id = ap.id_asiento_tipo
  AND pc.id = ap.id_cuenta
  AND ap.eliminado = false
  AND at.codigo IN ('PORCOBRARFACTURAVENTA', 'PORCOBRARRECIBOVENTA')
  AND LEFT(pc.codigo, 1) <> '1';

-- 2d. Control: debe devolver 0 filas
SELECT ap.id_empresa, at.codigo, ap.tipo_referencia, ap.id_referencia, pc.codigo
FROM asientos_programados ap
JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo
JOIN plan_cuentas pc  ON pc.id = ap.id_cuenta
WHERE ap.eliminado = false
  AND at.codigo IN ('PORCOBRARFACTURAVENTA', 'PORCOBRARRECIBOVENTA')
  AND LEFT(pc.codigo, 1) <> '1';

-- COMMIT;    -- descomentar tras revisar 2a y 2d
-- ROLLBACK;  -- si algo no cuadra


-- ═════════════════════════════════════════════════════════════════════════════
-- PASO 3 — Qué documentos quedaron mal contabilizados (para regenerar sus asientos).
-- ═════════════════════════════════════════════════════════════════════════════
-- Correr DESPUÉS del paso 2. Devuelve las facturas/recibos cuyo asiento vivo tiene, en el Debe,
-- una cuenta de ingresos etiquetada como cartera. Esa lista es la entrada del paso 4.
SELECT c.id_empresa,
       c.modulo_origen,
       c.id_referencia_origen AS id_documento,
       c.fecha_asiento,
       pc.codigo AS cuenta_mal,
       d.debe
FROM asientos_contables_cabecera c
JOIN asientos_contables_detalle d ON d.id_asiento = c.id AND d.eliminado = false AND d.debe > 0
JOIN plan_cuentas pc              ON pc.id = d.id_cuenta_contable
WHERE c.eliminado = false
  AND c.estado <> 'anulado'
  AND c.modulo_origen IN ('factura_venta', 'recibo_venta')
  AND d.referencia_detalle ILIKE '%cobrar%'
  AND LEFT(pc.codigo, 1) <> '1'
ORDER BY c.id_empresa, c.fecha_asiento;


-- ═════════════════════════════════════════════════════════════════════════════
-- PASO 4 — Regenerar los asientos afectados.
-- ═════════════════════════════════════════════════════════════════════════════
-- NO se reparan con UPDATE directo sobre asientos_contables_detalle: el asiento se vuelve a armar
-- entero desde la configuración ya corregida (cascada por línea incluida). Ejecutar en el servidor:
--
--     php database/diagnosticos/20260819_regenerar_asientos_cxc.php <id_empresa> <id_usuario> [--aplicar]
--
-- Sin --aplicar solo muestra qué haría. El script vuelve a llamar al mismo motor que usa el botón
-- Sincronizar (FacturaVentaService::procesarAsientoContablePorSincronizacion), que reemplaza el
-- asiento existente del documento en vez de crear uno nuevo.
