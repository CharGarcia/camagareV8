-- ============================================================================
-- Precio editable en la comanda, por producto
-- Módulo: modulos/productos (ficha del producto) → modulos/comandas (salón)
--
-- Marca qué productos/servicios permiten que el mesero cambie el precio de esa
-- línea dentro de la comanda (caso típico: "envío a domicilio", cuyo valor
-- depende de la distancia). Por defecto FALSE: hasta que se marque un producto,
-- el salón se comporta exactamente igual que hoy.
--
-- Los ítems de la carta (menu_items) heredan la marca del producto que tienen
-- vinculado; un ítem de menú sin producto vinculado no permite editar precio.
--
-- Cambiar el precio en la comanda NO modifica el precio del producto ni el de
-- la carta: solo esa línea, y solo mientras no esté en una cuenta ya generada.
-- ============================================================================

ALTER TABLE productos
    ADD COLUMN IF NOT EXISTS precio_editable_comanda BOOLEAN DEFAULT false;

-- Productos ya existentes: dejarlos con el comportamiento actual (precio fijo).
UPDATE productos
   SET precio_editable_comanda = false
 WHERE precio_editable_comanda IS NULL;
