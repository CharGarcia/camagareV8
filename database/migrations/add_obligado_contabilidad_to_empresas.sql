-- Agrega campo obligado_contabilidad a la tabla empresas
-- Valor por defecto: NO (no obligado a llevar contabilidad)
ALTER TABLE empresas
    ADD COLUMN IF NOT EXISTS obligado_contabilidad VARCHAR(2) NOT NULL DEFAULT 'NO';
