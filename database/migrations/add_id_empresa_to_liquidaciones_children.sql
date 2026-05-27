-- Migración para añadir id_empresa a las tablas hijas de liquidaciones de compra
-- Siguiendo la regla de Multitenancy: "Todas las tablas operativas deben incluir id_empresa"

ALTER TABLE liquidaciones_detalle ADD COLUMN IF NOT EXISTS id_empresa INTEGER;
ALTER TABLE liquidaciones_detalle_impuestos ADD COLUMN IF NOT EXISTS id_empresa INTEGER;
ALTER TABLE liquidaciones_pagos ADD COLUMN IF NOT EXISTS id_empresa INTEGER;
ALTER TABLE liquidaciones_adicional ADD COLUMN IF NOT EXISTS id_empresa INTEGER;

-- Actualizar registros existentes (si los hay) basándose en la cabecera
UPDATE liquidaciones_detalle d SET id_empresa = c.id_empresa FROM liquidaciones_cabecera c WHERE d.id_cabecera = c.id AND d.id_empresa IS NULL;
UPDATE liquidaciones_detalle_impuestos di SET id_empresa = c.id_empresa FROM liquidaciones_detalle d JOIN liquidaciones_cabecera c ON d.id_cabecera = c.id WHERE di.id_detalle = d.id AND di.id_empresa IS NULL;
UPDATE liquidaciones_pagos p SET id_empresa = c.id_empresa FROM liquidaciones_cabecera c WHERE p.id_cabecera = c.id AND p.id_empresa IS NULL;
UPDATE liquidaciones_adicional a SET id_empresa = c.id_empresa FROM liquidaciones_cabecera c WHERE a.id_cabecera = c.id AND a.id_empresa IS NULL;
