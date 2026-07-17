-- Normaliza la ruta de imagen de productos: debe ser RELATIVA al directorio público.
-- Algunos registros se guardaron con el prefijo 'public/' (p. ej.
-- 'public/uploads/productos/xxx.jpeg'), lo que producía una URL con /public/public/
-- y la imagen no cargaba. Se elimina ese prefijo.

UPDATE productos
SET imagen = regexp_replace(imagen, '^/*public/', '')
WHERE imagen IS NOT NULL
  AND imagen LIKE 'public/%';
