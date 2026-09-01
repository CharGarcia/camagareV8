-- ============================================================================
-- Órdenes de Compra: notas por línea del detalle
-- ============================================================================
-- El modal siempre tuvo un campo "Notas" en cada línea del detalle y el
-- formulario lo enviaba al servidor, pero la tabla no tenía dónde guardarlo:
-- la nota se perdía en silencio al guardar (sin error), y al reabrir la orden
-- el campo aparecía vacío.
--
-- La nota es una instrucción para el proveedor sobre ESA línea ("entregar en
-- cajas de 12", "color azul", "sin etiqueta"), así que se guarda junto al ítem
-- y sale impresa en el PDF/Excel y en la pantalla de aprobación del proveedor.
--
-- 200 caracteres = el mismo maxlength que ya tenía el input del modal.
-- ============================================================================

ALTER TABLE ordenes_compra_detalle
    ADD COLUMN IF NOT EXISTS notas VARCHAR(200) DEFAULT NULL;
