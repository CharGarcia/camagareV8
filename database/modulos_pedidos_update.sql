-- Actualización de la tabla pedidos_cabecera para el módulo de pedidos
ALTER TABLE pedidos_cabecera 
    ADD COLUMN IF NOT EXISTS estado VARCHAR(50) DEFAULT 'Pendiente',
    ADD COLUMN IF NOT EXISTS observaciones_internas TEXT,
    ADD COLUMN IF NOT EXISTS fecha_entrega DATE,
    ADD COLUMN IF NOT EXISTS hora_inicial_entrega TIME,
    ADD COLUMN IF NOT EXISTS hora_maxima_entrega TIME,
    ADD COLUMN IF NOT EXISTS responsable_entrega VARCHAR(255);
