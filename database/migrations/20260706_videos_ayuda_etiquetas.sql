-- ============================================================================
-- Videos de Ayuda — Etiquetas / palabras clave por video (para la búsqueda)
--
-- Texto libre separado por comas (ej: "vender, venta, cobrar, cliente"). El
-- buscador del visor las incluye junto al título/categoría/descripción, así el
-- admin puede agregar sinónimos y términos que la gente usaría al buscar.
-- ============================================================================

ALTER TABLE videos_ayuda ADD COLUMN IF NOT EXISTS etiquetas TEXT;
