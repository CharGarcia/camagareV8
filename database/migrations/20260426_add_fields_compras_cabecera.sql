-- Migración para añadir campos adicionales al módulo de compras
ALTER TABLE compras_cabecera ADD COLUMN IF NOT EXISTS tipo_registro VARCHAR(20) DEFAULT 'fisica';
ALTER TABLE compras_cabecera ADD COLUMN IF NOT EXISTS deducible VARCHAR(50) DEFAULT 'declaracion_iva';
ALTER TABLE compras_cabecera ADD COLUMN IF NOT EXISTS documento_modificado VARCHAR(20);
ALTER TABLE compras_cabecera ADD COLUMN IF NOT EXISTS motivo VARCHAR(255);
