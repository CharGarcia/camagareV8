-- ============================================================================
-- Fix del reconocimiento de IVA por codigoPorcentaje (no por tarifa %)
-- Fecha: 2026-07-07
--
-- CORRECCIONES EN CÓDIGO (ya desplegadas por git):
--   1) getOrCreateProductoId (DocumentoAutomatedRegisterService): resuelve
--      productos.tarifa_iva por el codigoPorcentaje del XML contra tarifa_iva.codigo,
--      en vez de comparar la tarifa numérica contra códigos.
--   2) compras.js: el <select> de IVA del modal preselecciona y guarda por el
--      codigoPorcentaje real (antes reescribía codigo_porcentaje = tarifa>0?'4':'0').
--   3) ConfiguracionContableController: el listado de reglas de IVA une por 'codigo'.
--
-- ESTE SCRIPT sanea los datos que el bug ya dejó. Hacer respaldo antes.
-- Ejecutar el PASO 1 (diagnóstico) primero; los UPDATE solo si hay filas.
-- ============================================================================


-- ----------------------------------------------------------------------------
-- PASO 1 — DIAGNÓSTICO (solo lectura)
-- ----------------------------------------------------------------------------
-- Productos con tarifa_iva inválido (0 no existe en el catálogo; o ids inactivos):
SELECT p.id, p.nombre, p.tarifa_iva
FROM productos p
LEFT JOIN tarifa_iva ti ON ti.id = p.tarifa_iva
WHERE ti.id IS NULL;

-- Filas de impuesto de COMPRA con codigoPorcentaje incoherente con su tarifa (>0):
SELECT di.codigo_porcentaje, di.tarifa, COUNT(*) AS n
FROM compras_detalle_impuestos di
WHERE di.codigo_impuesto = '2' AND di.tarifa > 0
  AND di.codigo_porcentaje NOT IN (SELECT codigo FROM tarifa_iva WHERE porcentaje_iva = di.tarifa)
GROUP BY di.codigo_porcentaje, di.tarifa;


-- ----------------------------------------------------------------------------
-- PASO 2 — SANEAR productos con tarifa_iva inválido.
-- Se intenta derivar la tarifa correcta del último detalle de VENTA del producto
-- (por su codigoPorcentaje); si el producto no tiene ventas, se deja en 15% (id 7,
-- tarifa vigente para bienes). Ajustar el fallback si el negocio lo requiere.
-- ----------------------------------------------------------------------------
UPDATE productos p
SET tarifa_iva = COALESCE(
    (SELECT ti.id
       FROM ventas_detalle vd
       JOIN ventas_detalle_impuestos vdi ON vdi.id_venta_detalle = vd.id
       JOIN tarifa_iva ti ON ti.codigo = vdi.codigo_porcentaje
      WHERE vd.id_producto = p.id
      ORDER BY vd.id DESC
      LIMIT 1),
    (SELECT id FROM tarifa_iva WHERE codigo = '4' LIMIT 1)  -- fallback: 15%
)
WHERE p.tarifa_iva NOT IN (SELECT id FROM tarifa_iva);


-- ----------------------------------------------------------------------------
-- PASO 3 — SANEAR el codigoPorcentaje incoherente en compras (solo tarifa > 0,
-- donde el % determina el código unívocamente: 5->'5', 8->'8', 13->'10', 15->'4').
-- Las filas con tarifa 0 NO se tocan: si un exento/no objeto se guardó como '0',
-- el dato original ya se perdió y no es recuperable automáticamente.
-- ----------------------------------------------------------------------------
UPDATE compras_detalle_impuestos di
SET codigo_porcentaje = (
    SELECT codigo FROM tarifa_iva
    WHERE porcentaje_iva = di.tarifa AND status = 1
    ORDER BY id LIMIT 1
)
WHERE di.codigo_impuesto = '2' AND di.tarifa > 0
  AND di.codigo_porcentaje NOT IN (SELECT codigo FROM tarifa_iva WHERE porcentaje_iva = di.tarifa);

-- Verificación: repetir el PASO 1; ambas consultas deben devolver 0 filas.
