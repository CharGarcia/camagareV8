-- ============================================================
-- Migración: Servicio de Secuenciales Inteligente
-- Descripción: Añade columna secuencial_inicial a empresa_secuencial
--              y crea índice único para evitar duplicados por punto+tipo
-- ============================================================

-- 1. Agregar columna secuencial_inicial (número desde el cual se empieza a emitir)
--    numero_secuencial pasa a ser informativo / de configuración base
ALTER TABLE empresa_secuencial 
    ADD COLUMN IF NOT EXISTS secuencial_inicial BIGINT DEFAULT 1;

-- 2. Sincronizar initial con el valor actual de numero_secuencial 
--    para registros existentes que aún no lo tengan
UPDATE empresa_secuencial 
SET secuencial_inicial = numero_secuencial 
WHERE secuencial_inicial IS NULL OR secuencial_inicial = 1;

-- 3. Índice único para evitar registros duplicados por punto + tipo de documento
CREATE UNIQUE INDEX IF NOT EXISTS uq_secuencial_punto_tipo 
ON empresa_secuencial (id_punto_emision, tipo_documento) 
WHERE eliminado = false;

-- 4. Índice en ventas_cabecera para búsquedas rápidas de secuenciales usados
CREATE INDEX IF NOT EXISTS idx_ventas_punto_secuencial 
ON ventas_cabecera (id_punto_emision, secuencial) 
WHERE eliminado = false;
