-- ============================================================================
-- Presentación de ítems en factura (PDF + XML SRI)
-- Módulo: modulos/empresa → pestaña Facturación
--
-- factura_agrupar_items:
--   'no'   → una línea por cada detalle (comportamiento actual, por defecto)
--   'lote' → fusiona líneas del mismo producto que comparten número de lote
--   'nup'  → fusiona líneas del mismo producto que comparten NUP/serie
--
-- La fusión solo ocurre si además coinciden precio unitario, unidad de medida
-- e impuestos; de lo contrario las líneas quedan separadas para que la suma
-- del comprobante siga siendo exacta frente al SRI.
--
-- factura_item_mostrar_*: anexan el dato a la DESCRIPCIÓN del ítem, tanto en
-- el PDF como en el XML (el comprobante electrónico debe decir lo mismo que la
-- representación impresa).
-- ============================================================================

ALTER TABLE empresa_establecimiento
    ADD COLUMN IF NOT EXISTS factura_agrupar_items          VARCHAR(20) DEFAULT 'no',
    ADD COLUMN IF NOT EXISTS factura_item_mostrar_unidad    VARCHAR(10) DEFAULT 'false',
    ADD COLUMN IF NOT EXISTS factura_item_mostrar_lote      VARCHAR(10) DEFAULT 'false',
    ADD COLUMN IF NOT EXISTS factura_item_mostrar_caducidad VARCHAR(10) DEFAULT 'false',
    ADD COLUMN IF NOT EXISTS factura_item_mostrar_nup       VARCHAR(10) DEFAULT 'false';

-- Establecimientos ya existentes: dejarlos con el comportamiento actual.
UPDATE empresa_establecimiento
   SET factura_agrupar_items          = COALESCE(factura_agrupar_items,          'no'),
       factura_item_mostrar_unidad    = COALESCE(factura_item_mostrar_unidad,    'false'),
       factura_item_mostrar_lote      = COALESCE(factura_item_mostrar_lote,      'false'),
       factura_item_mostrar_caducidad = COALESCE(factura_item_mostrar_caducidad, 'false'),
       factura_item_mostrar_nup       = COALESCE(factura_item_mostrar_nup,       'false');
