-- =====================================================================================
-- Compras recibidas del SRI: base imponible de IVA inflada en las líneas del detalle
-- -------------------------------------------------------------------------------------
-- Contexto:
--   Algunos emisores (SERVIENTREGA, p. ej.) ponen como baseImponible del IVA de cada
--   línea el precio ANTES del descuento, aunque el IVA lo calculan sobre el subtotal
--   neto y la cabecera del comprobante declara la base correcta. El SRI lo autoriza
--   (valida la cabecera). DocumentoAutomatedRegisterService::insertarCompra() guardaba
--   esa base tal cual en compras_detalle_impuestos, y de ahí la leen la Declaración de
--   IVA (casilleros de base), el ATS (baseImpGrav) y el Reporte de Compras. El valor
--   del IVA, el asiento y las cuentas por pagar NO se ven afectados.
--   Ya corregido en código para las cargas nuevas (resolverAjusteBasesIva); este
--   script repara lo que ya estaba cargado.
--
--   Diagnóstico en producción (16-09-2026): 1 documento — NC 225-021-000066109 de
--   SERVIENTREGA ECUADOR S.A., empresa 31, base guardada 5.20, base correcta 4.94.
--
-- Qué hace (se ejecuta completo con F5 en el Query Tool de pgAdmin):
--   PASO 1  Identifica las líneas de IVA a corregir. Mismo criterio que el diagnóstico:
--           compras electrónicas (factura, liquidación o nota de crédito) cuya suma de
--           bases de IVA no cuadra con el subtotal de la cabecera, pero la suma de los
--           subtotales de sus líneas (+ ICE) sí cuadra.
--   PASO 2  Agrega una observación a cada compra corregida explicando el cambio.
--   PASO 3  Corrige base_imponible de esas líneas al subtotal de la línea (+ ICE).
--           No toca el valor del IVA ni ningún otro dato.
--   PASO 4  Lista lo corregido (base anterior y nueva de cada línea).
--
-- Toca datos : SÍ (compras_detalle_impuestos.base_imponible y compras_cabecera.observaciones).
-- Idempotente: SÍ. Una segunda ejecución no encuentra nada y el PASO 4 sale vacío.
-- Reversible : SÍ. Guardar el resultado del PASO 4; para revertir una línea:
--                UPDATE compras_detalle_impuestos SET base_imponible = <base_anterior>
--                 WHERE id = <id_impuesto>;
--              y quitar la observación agregada desde el modal de Compras.
--
-- Después de ejecutarlo:
--   - El ATS y el Reporte de Compras leen el dato en vivo: ya salen con la base correcta.
--   - Declaración de IVA: sus casilleros se regeneran con el botón "Recalcular desde
--     documentos" del periodo (julio 2026 en la empresa 31). Una declaración ya
--     presentada no cambia sola; el IVA declarado era correcto, solo difiere la base.
-- =====================================================================================

BEGIN;

-- ── PASO 1: líneas a corregir ─────────────────────────────────────────────────────────
DROP TABLE IF EXISTS pg_temp._fix_base_iva;

CREATE TEMP TABLE _fix_base_iva AS
WITH lineas AS (
    SELECT c.id             AS id_compra,
           i.id             AS id_impuesto,
           i.base_imponible AS base_anterior,
           ROUND(d.precio_total_sin_impuesto
                 + COALESCE((SELECT SUM(i3.valor)
                               FROM compras_detalle_impuestos i3
                              WHERE i3.id_compra_detalle = d.id
                                AND i3.codigo_impuesto = '3'), 0), 2) AS base_nueva,
           ROUND(c.total_sin_impuestos + COALESCE(c.total_ice, 0), 2) AS base_cabecera
      FROM compras_cabecera c
      JOIN compras_detalle d           ON d.id_compra = c.id
      JOIN compras_detalle_impuestos i ON i.id_compra_detalle = d.id
                                      AND i.codigo_impuesto = '2'
     WHERE c.eliminado = false
       AND c.tipo_registro = 'electronico'
       AND c.tipo_comprobante IN ('01', '03', '04')
), docs AS (
    SELECT id_compra,
           SUM(base_anterior) AS base_guardada,
           MAX(base_cabecera) AS base_cabecera
      FROM lineas
     GROUP BY id_compra
    HAVING ABS(SUM(base_anterior) - MAX(base_cabecera)) >  0.01
       AND ABS(SUM(base_nueva)    - MAX(base_cabecera)) <= 0.01
)
SELECT l.id_compra,
       l.id_impuesto,
       l.base_anterior,
       l.base_nueva,
       x.base_guardada,
       x.base_cabecera
  FROM lineas l
  JOIN docs x ON x.id_compra = l.id_compra
 WHERE ABS(l.base_anterior - l.base_nueva) > 0.01;

-- ── PASO 2: observación en la compra ──────────────────────────────────────────────────
UPDATE compras_cabecera c
   SET observaciones = CONCAT_WS(' | ',
           NULLIF(BTRIM(c.observaciones), ''),
           'XML del SRI inconsistente: la base imponible de IVA del detalle sumaba '
             || TO_CHAR(f.base_guardada, 'FM999999990.00')
             || ' y la cabecera declara '
             || TO_CHAR(f.base_cabecera, 'FM999999990.00')
             || '. Cada línea se corrigió con su subtotal sin impuestos como base (corrección de datos del 16-09-2026).'),
       updated_at = NOW()
  FROM (SELECT DISTINCT id_compra, base_guardada, base_cabecera FROM _fix_base_iva) f
 WHERE c.id = f.id_compra;

-- ── PASO 3: base imponible de las líneas ──────────────────────────────────────────────
UPDATE compras_detalle_impuestos i
   SET base_imponible = f.base_nueva
  FROM _fix_base_iva f
 WHERE i.id = f.id_impuesto;

COMMIT;

-- ── PASO 4: lo corregido (en la primera ejecución debe salir 1 fila) ──────────────────
SELECT c.id_empresa,
       f.id_compra,
       f.id_impuesto,
       c.establecimiento_prov || '-' || c.punto_emision_prov || '-' || c.secuencial_prov AS numero,
       p.razon_social AS proveedor,
       f.base_anterior,
       f.base_nueva
  FROM _fix_base_iva f
  JOIN compras_cabecera c ON c.id = f.id_compra
  JOIN proveedores p      ON p.id = c.id_proveedor
 ORDER BY f.id_compra, f.id_impuesto;

-- =====================================================================================
-- Comprobación (opcional, solo lectura): volver a ejecutar el SELECT de diagnóstico;
-- ya no debe devolver filas. O revisar la NC directamente:
--
--   SELECT i.id, i.codigo_porcentaje, i.base_imponible, i.valor, c.observaciones
--     FROM compras_cabecera c
--     JOIN compras_detalle d           ON d.id_compra = c.id
--     JOIN compras_detalle_impuestos i ON i.id_compra_detalle = d.id
--    WHERE c.id = 120380;
--   -- Debe salir base_imponible 4.94, valor 0.74 y la observación de la corrección.
-- =====================================================================================
