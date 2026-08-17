-- ------------------------------------------------------------
-- La cantidad del detalle de compras estaba en NUMERIC(14,4), lo que
-- truncaba silenciosamente el 5° y 6° decimal que el SRI sí permite en
-- <cantidad> de los XML de compra. precio_unitario ya está en NUMERIC(14,6);
-- se amplía cantidad a la misma escala usada en ventas_detalle (NUMERIC(18,6))
-- para que los documentos descargados del SRI queden guardados exactamente
-- como llegan en el XML.
-- ------------------------------------------------------------
ALTER TABLE compras_detalle
    ALTER COLUMN cantidad TYPE NUMERIC(18,6);
