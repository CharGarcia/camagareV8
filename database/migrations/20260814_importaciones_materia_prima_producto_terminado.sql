-- =============================================================================
-- Importaciones: separar el asiento de inventario entre Materia Prima y
-- Producto Terminado
-- ----------------------------------------------------------------------------
-- Hoy TODO el costo nacionalizado de una importación va a una sola cuenta
-- ('INVENTARIOIMPORTACION'), sin importar si lo importado es materia prima
-- (va a producción) o producto terminado listo para la venta.
--
-- Esta migración agrega dos códigos nuevos al catálogo de asientos del
-- concepto 'adquisiciones_importacion' (mismo mecanismo que el resto de
-- filas de ese concepto, ver create_importaciones_module.sql):
--   INVENTARIOIMPORTACIONMATERIAPRIMA
--   INVENTARIOIMPORTACIONPRODUCTOTERMINADO
--
-- Son OPCIONALES: si una empresa no configura AMBAS cuentas en
-- /config/asientos-contables, AsientoBuilderService::generarAsientoImportacion()
-- sigue usando la cuenta general única 'INVENTARIOIMPORTACION' (comportamiento
-- actual, sin cambios). Solo cuando las dos están configuradas se reparte el
-- costo según importaciones_detalle.es_materia_prima (columna nueva, marcada
-- por línea de producto en el modal de Importaciones).
--
-- Idempotente, aditiva.
-- =============================================================================

BEGIN;

ALTER TABLE importaciones_detalle
    ADD COLUMN IF NOT EXISTS es_materia_prima BOOLEAN NOT NULL DEFAULT FALSE;

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, debe_haber)
SELECT 'adquisiciones_importacion', 'Inventario Materia Prima (costo nacionalizado)',
       'Costo nacionalizado de las líneas marcadas como "Materia Prima" en el detalle de productos. Solo se usa si también está configurada la cuenta "Inventario Producto Terminado"; si falta alguna de las dos, todo el costo va a la cuenta general "Inventario (costo nacionalizado)".',
       'INVENTARIOIMPORTACIONMATERIAPRIMA', 'debe'
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = 'INVENTARIOIMPORTACIONMATERIAPRIMA');

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, debe_haber)
SELECT 'adquisiciones_importacion', 'Inventario Producto Terminado (costo nacionalizado)',
       'Costo nacionalizado de las líneas NO marcadas como "Materia Prima" (productos listos para la venta). Solo se usa si también está configurada la cuenta "Inventario Materia Prima"; si falta alguna de las dos, todo el costo va a la cuenta general "Inventario (costo nacionalizado)".',
       'INVENTARIOIMPORTACIONPRODUCTOTERMINADO', 'debe'
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = 'INVENTARIOIMPORTACIONPRODUCTOTERMINADO');

COMMIT;
