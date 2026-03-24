-- Migración: Agregar columnas de cuentas contables al modal de clientes
-- Ejecutar si desea usar Cuenta por cobrar y Cuenta de ingreso en clientes
-- Fecha: 2025-03-03

ALTER TABLE clientes ADD COLUMN id_cuenta_cobrar INT DEFAULT NULL;
ALTER TABLE clientes ADD COLUMN id_cuenta_ingreso INT DEFAULT NULL;
