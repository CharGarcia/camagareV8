-- Actualización de la tabla 'empleados' para reflejar el nuevo esquema de pagos anticipados.

-- 1. Eliminar la columna 'forma_pago'
ALTER TABLE empleados DROP COLUMN IF EXISTS forma_pago;

-- 2. Asegurar que existan las columnas para los montos de anticipos (semanal y quincenal)
ALTER TABLE empleados 
    ADD COLUMN valor_semanal DECIMAL(10, 2) DEFAULT 0.00;

-- Nota: 'valor_quincena' ya fue agregada en la migración anterior.
