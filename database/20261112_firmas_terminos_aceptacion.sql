-- Firmas electrónicas: aceptación de términos y tratamiento de datos personales
-- (Ley Orgánica de Protección de Datos Personales) en el enlace del cliente.
-- Idempotente. Aplicar ANTES de desplegar el código: sin estas columnas el módulo
-- sigue funcionando igual que antes, pero el enlace no muestra la casilla.
-- No toca las firmas existentes: quedan con estas columnas vacías.

-- 1. Texto propio de la empresa. Vacío (NULL) = texto base del sistema.
ALTER TABLE firmas_electronicas_config ADD COLUMN IF NOT EXISTS terminos_texto TEXT;

-- 2. Constancia de la aceptación del cliente (se guarda al crear la solicitud).
ALTER TABLE firmas_electronicas ADD COLUMN IF NOT EXISTS terminos_aceptados_at TIMESTAMP;
ALTER TABLE firmas_electronicas ADD COLUMN IF NOT EXISTS terminos_ip           VARCHAR(45);
ALTER TABLE firmas_electronicas ADD COLUMN IF NOT EXISTS terminos_user_agent   VARCHAR(300);
-- Copia del texto exacto que el cliente aceptó (el de la empresa puede cambiar después).
ALTER TABLE firmas_electronicas ADD COLUMN IF NOT EXISTS terminos_texto        TEXT;
