-- =====================================================================================
-- Compras: notas de débito (tipo 05) cuyo único impuesto de IVA quedó con base 0.00
-- aunque el comprobante tiene total sin impuestos > 0.
--
-- QUÉ HACE
--   Algunos emisores declaran en el XML de la nota de débito el impuesto con
--   <baseImponible>0.00</baseImponible> (p. ej. intereses por mora, código 6 = no objeto
--   de IVA) aunque <totalSinImpuestos> sea 625.21. El SRI lo autoriza igual y el sistema
--   lo guardó tal cual, pero en el ATS la nota sale con las cuatro bases en 0,00 y el SRI
--   la rechaza: "al menos una base (baseNoGraIva, baseImponible, baseImpGrav o
--   baseImpExe) debe ser mayor a 0.00".
--   Este script toma las notas de débito con UN solo impuesto de IVA (código 2) con base
--   0.00 y valor 0.00, y total sin impuestos > 0, y pone como base ese total (es el único
--   valor que cuadra con el comprobante). Deja constancia en las observaciones de la
--   compra. Es el mismo criterio que desde hoy aplica el registro automático
--   (App\Helpers\NotaDebitoXmlHelper).
--   Si una nota tiene varios impuestos de IVA, o el impuesto trae valor > 0, NO se toca:
--   esas se revisan a mano en Compras.
--
-- ORDEN: ejecutar DESPUÉS de 2026-10-06_compras_nota_debito_detalle_desde_xml.sql (ese
--   crea el detalle de las notas que no tenían ninguno; este corrige la base de las que
--   sí lo tienen, incluidas las recién creadas).
-- TOCA DATOS: sí (UPDATE compras_detalle_impuestos.base_imponible y
--   compras_cabecera.observaciones). Idempotente: una vez corregida la base ya no es 0.
-- REVERSIBLE: sí. Las filas afectadas salen en el resultado (RETURNING); para revertir,
--   volver a poner base_imponible = 0 en esos ids de compras_detalle_impuestos.
-- CÓMO: pegar en el Query Tool de pgAdmin y ejecutar (F5). La salida lista las compras
--   corregidas (id, número, base aplicada).
-- =====================================================================================

WITH candidatas AS (
    SELECT di.id      AS id_impuesto,
           c.id       AS id_compra,
           c.establecimiento_prov || '-' || c.punto_emision_prov || '-' || c.secuencial_prov AS numero,
           di.codigo_porcentaje,
           ROUND(c.total_sin_impuestos, 2) AS base_nueva
      FROM compras_cabecera c
      JOIN compras_detalle d             ON d.id_compra = c.id
      JOIN compras_detalle_impuestos di  ON di.id_compra_detalle = d.id
     WHERE c.tipo_comprobante = '05'
       AND c.eliminado = false
       AND c.total_sin_impuestos > 0
       AND di.codigo_impuesto = '2'
       AND di.base_imponible = 0
       AND di.valor = 0
       -- Único impuesto de IVA de toda la compra (caso inequívoco).
       AND NOT EXISTS (
            SELECT 1
              FROM compras_detalle d2
              JOIN compras_detalle_impuestos di2 ON di2.id_compra_detalle = d2.id
             WHERE d2.id_compra = c.id
               AND di2.codigo_impuesto = '2'
               AND di2.id <> di.id
       )
),
imp AS (
    UPDATE compras_detalle_impuestos di
       SET base_imponible = k.base_nueva
      FROM candidatas k
     WHERE di.id = k.id_impuesto
    RETURNING k.id_compra, k.numero, k.codigo_porcentaje, k.base_nueva
),
cab AS (
    UPDATE compras_cabecera c
       SET observaciones = CONCAT_WS(' | ', NULLIF(BTRIM(c.observaciones), ''),
               'XML del SRI inconsistente: el impuesto de IVA de la nota de débito venía con base 0.00 '
               || 'y el total sin impuestos es ' || TO_CHAR(i.base_nueva, 'FM999999990.00')
               || '. La base se corrigió con ese total (código ' || i.codigo_porcentaje
               || '), que es el que cuadra con el valor del comprobante.'),
           updated_at = NOW()
      FROM imp i
     WHERE c.id = i.id_compra
    RETURNING c.id
)
SELECT i.id_compra, i.numero, i.codigo_porcentaje, i.base_nueva
  FROM imp i
 ORDER BY i.id_compra;

-- -------------------------------------------------------------------------------------
-- COMPROBACIÓN (opcional): notas de débito que siguen con todas sus bases de IVA en 0.
-- Deben ser 0 filas; si queda alguna, abrirla en Compras y completar el IVA a mano.
-- -------------------------------------------------------------------------------------
-- SELECT c.id, c.establecimiento_prov || '-' || c.punto_emision_prov || '-' || c.secuencial_prov AS numero,
--        c.fecha_emision, c.total_sin_impuestos, c.importe_total
--   FROM compras_cabecera c
--  WHERE c.tipo_comprobante = '05' AND c.eliminado = false
--    AND NOT EXISTS (SELECT 1 FROM compras_detalle d
--                      JOIN compras_detalle_impuestos di ON di.id_compra_detalle = d.id
--                     WHERE d.id_compra = c.id AND di.codigo_impuesto = '2' AND di.base_imponible > 0)
--  ORDER BY c.fecha_emision;
