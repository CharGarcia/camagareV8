-- ============================================================================
-- Catálogo global de Formatos de Transferencia Bancaria (config/transferencia-formatos)
-- ----------------------------------------------------------------------------
-- Reemplaza el resuelve-por-nombre-de-banco hardcodeado en
-- TransferenciaFormatterFactory por un catálogo editable (nivel 3): cada fila
-- define el tipo de archivo (xlsx/csv/txt delimitado/txt ancho fijo) y las
-- columnas del layout (JSONB `campos`). `clase_formatter` queda como
-- escape hatch genérico para un formato futuro que no se pueda expresar solo
-- con columnas — no se usa para ningún banco existente por ahora; todos los
-- bancos (incluido Produbanco) se configuran desde cero por columnas en la UI.
--
-- Tabla global (sin id_empresa): todas las empresas ven el mismo catálogo,
-- igual que bancos_ecuador / impuesto_renta_tramos.
-- ============================================================================

CREATE TABLE IF NOT EXISTS transferencia_formatos (
    id                  SERIAL PRIMARY KEY,
    id_banco            INT NULL REFERENCES bancos_ecuador(id), -- NULL = formato genérico, no atado a un banco
    nombre              VARCHAR(150) NOT NULL,
    descripcion         TEXT NULL,
    tipo_archivo        VARCHAR(20) NOT NULL, -- 'xlsx' | 'csv' | 'txt_delimitado' | 'txt_ancho_fijo'
    delimitador         VARCHAR(3) NULL,      -- solo csv/txt_delimitado
    incluye_encabezado  BOOLEAN NOT NULL DEFAULT true,
    nombre_hoja         VARCHAR(100) NULL,    -- solo xlsx
    campos              JSONB NOT NULL DEFAULT '[]', -- array ordenado de definiciones de columna
    clase_formatter     VARCHAR(255) NULL,    -- si está seteado, ignora `campos` y usa esta clase PHP
    estado              VARCHAR(20) NOT NULL DEFAULT 'activo', -- activo | inactivo
    eliminado           BOOLEAN NOT NULL DEFAULT false,
    deleted_at          TIMESTAMP NULL,
    deleted_by          INT NULL REFERENCES usuarios(id),
    created_by          INT NULL REFERENCES usuarios(id),
    updated_by          INT NULL REFERENCES usuarios(id),
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NULL
);

CREATE INDEX IF NOT EXISTS idx_transferencia_formatos_banco
    ON transferencia_formatos(id_banco) WHERE eliminado = false;

CREATE INDEX IF NOT EXISTS idx_transferencia_formatos_estado
    ON transferencia_formatos(estado) WHERE eliminado = false;

-- Nueva referencia del lote al formato elegido del catálogo. Se conserva
-- id_banco_formato (no se toca ni se elimina) para no romper datos ya
-- guardados en lotes existentes; el código nuevo usa solo la columna nueva.
ALTER TABLE transferencias_lotes
    ADD COLUMN IF NOT EXISTS id_formato_transferencia INT NULL REFERENCES transferencia_formatos(id);

-- ─── Seed: único formato pre-cargado, usado como red de seguridad cuando un
-- lote no tiene formato asignado (ver TransferenciaFormatterFactory). El resto
-- de formatos (incluido Produbanco) se configuran desde cero en la UI nueva,
-- por columnas — no se migra la clase PHP existente.
INSERT INTO transferencia_formatos (nombre, tipo_archivo, clase_formatter, estado)
SELECT 'Genérico (Excel)', 'xlsx', 'App\Services\modulos\Transferencias\Formatters\TransferenciaFormatoGenericoExcel', 'activo'
WHERE NOT EXISTS (
    SELECT 1 FROM transferencia_formatos WHERE clase_formatter = 'App\Services\modulos\Transferencias\Formatters\TransferenciaFormatoGenericoExcel'
);
