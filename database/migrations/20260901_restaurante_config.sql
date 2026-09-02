-- ============================================================================
-- Configuración del restaurante por empresa
--
-- Nace con un solo ajuste —el ancho del papel de la tirilla— pero como tabla y
-- no como columna suelta: el módulo Configuración Restaurante va a seguir
-- creciendo, y así lo que venga después se agrega aquí sin tocar tablas ajenas.
--
-- El ancho NO fija el tamaño de la página (eso lo manda el driver de la
-- impresora, ver app/views/partials/tirilla_estilos.php): ajusta el tamaño de
-- letra, para que una tirilla de 58 mm no salga con la letra pensada para 80.
-- ============================================================================

CREATE TABLE IF NOT EXISTS restaurante_config (
    id                   SERIAL PRIMARY KEY,
    id_empresa           INTEGER  NOT NULL,
    ancho_papel_tirilla  SMALLINT NOT NULL DEFAULT 80,
    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by           INTEGER,
    updated_by           INTEGER,
    eliminado            BOOLEAN DEFAULT FALSE,
    deleted_at           TIMESTAMP,
    deleted_by           INTEGER,
    CONSTRAINT chk_restaurante_config_ancho CHECK (ancho_papel_tirilla IN (58, 80))
);

COMMENT ON TABLE  restaurante_config                     IS 'Configuración del salón por empresa (modulos/configuracion-restaurante).';
COMMENT ON COLUMN restaurante_config.ancho_papel_tirilla IS 'Ancho del papel de la tirilla de cuenta/factura en mm (58 u 80). Ajusta el tamaño de letra, no el tamaño de página.';

-- Una sola fila de configuración por empresa.
CREATE UNIQUE INDEX IF NOT EXISTS uq_restaurante_config_empresa
    ON restaurante_config (id_empresa)
    WHERE eliminado = false;
