-- Marca de asiento editado a mano.
--
-- Los módulos operativos (compras, ingresos, egresos, …) REGENERAN el asiento del
-- documento desde AsientoBuilderService cada vez que el documento se guarda. Desde que
-- la pestaña «Asiento contable» del modal permite corregir el asiento a mano, esa
-- regeneración pisaría la corrección sin avisar.
--
-- Con esta columna: todo asiento guardado desde una pantalla (el modal del Libro Diario o
-- la pestaña del documento) queda marcado, y los services de los módulos lo respetan —
-- no lo vuelven a armar. Se limpia con «Restaurar asiento automático», que regenera el
-- asiento desde las reglas contables.
ALTER TABLE asientos_contables_cabecera
    ADD COLUMN IF NOT EXISTS editado_manual BOOLEAN NOT NULL DEFAULT false;

COMMENT ON COLUMN asientos_contables_cabecera.editado_manual IS
    'true = el asiento se editó a mano; los módulos operativos no lo regeneran desde el builder.';
