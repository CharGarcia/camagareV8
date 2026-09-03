-- Proformas: pestaña "Condiciones" (texto enriquecido en HTML).
-- No se imprime en la proforma: se genera como un PDF anexo aparte que se
-- descarga desde el modal y se adjunta al correo junto a la proforma.
-- Las plantillas también lo guardan para precargarlo al usarlas.
ALTER TABLE proformas_cabecera   ADD COLUMN IF NOT EXISTS condiciones_html TEXT;
ALTER TABLE proformas_plantillas ADD COLUMN IF NOT EXISTS condiciones_html TEXT;
