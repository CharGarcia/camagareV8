-- Actualización de la tabla pedidos_cabecera para soportar serie y secuencial
ALTER TABLE pedidos_cabecera 
    ADD COLUMN IF NOT EXISTS id_establecimiento INTEGER,
    ADD COLUMN IF NOT EXISTS id_punto_emision INTEGER,
    ADD COLUMN IF NOT EXISTS establecimiento VARCHAR(3),
    ADD COLUMN IF NOT EXISTS punto_emision VARCHAR(3),
    ADD COLUMN IF NOT EXISTS secuencial VARCHAR(9);
