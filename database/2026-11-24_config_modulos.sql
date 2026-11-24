-- =============================================================================
-- Personalización de módulos por empresa y por cliente (config_modulos)
-- =============================================================================
-- Una sola tabla para las opciones que cada empresa personaliza de un módulo
-- (p. ej. Consignaciones de Venta: mostrar la caducidad en el PDF/Excel).
--
--   id_cliente IS NULL      → configuración de la EMPRESA para ese módulo.
--   id_cliente IS NOT NULL  → excepción para ESE cliente.
--
-- Qué opciones existen, su tipo y su valor por defecto NO viven aquí sino en el
-- catálogo App\Helpers\ConfigModulo::CATALOGO: agregar una opción nueva no
-- requiere SQL. Sin filas, todo sale con los valores por defecto del catálogo
-- (es decir, igual que antes de esta tabla).
--
-- Se edita en Configuración de Empresa (modulos/empresa), una pestaña por módulo.
-- Ejecutar en pgAdmin ANTES de desplegar el código (el código degrada sin la
-- tabla: usa los valores por defecto y no deja guardar).
-- =============================================================================

CREATE TABLE IF NOT EXISTS config_modulos (
    id          SERIAL PRIMARY KEY,
    id_empresa  INTEGER      NOT NULL REFERENCES empresas(id),
    modulo      VARCHAR(100) NOT NULL,
    id_cliente  INTEGER      NULL REFERENCES clientes(id),
    opciones    JSONB        NOT NULL DEFAULT '{}'::jsonb,
    created_at  TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMP    NULL,
    created_by  INTEGER      NULL,
    updated_by  INTEGER      NULL,
    eliminado   BOOLEAN      NOT NULL DEFAULT false,
    deleted_at  TIMESTAMP    NULL,
    deleted_by  INTEGER      NULL
);

-- Una sola fila vigente de empresa por módulo…
CREATE UNIQUE INDEX IF NOT EXISTS ux_config_modulos_empresa
    ON config_modulos (id_empresa, modulo)
    WHERE id_cliente IS NULL AND eliminado = false;

-- …y una sola fila vigente por cliente y módulo.
CREATE UNIQUE INDEX IF NOT EXISTS ux_config_modulos_cliente
    ON config_modulos (id_empresa, modulo, id_cliente)
    WHERE id_cliente IS NOT NULL AND eliminado = false;

COMMENT ON TABLE  config_modulos            IS 'Personalización de módulos por empresa (id_cliente NULL) y excepciones por cliente. Catálogo de opciones: App\Helpers\ConfigModulo';
COMMENT ON COLUMN config_modulos.modulo     IS 'Ruta MVC del módulo, p. ej. modulos/consignaciones-ventas';
COMMENT ON COLUMN config_modulos.id_cliente IS 'NULL = configuración de la empresa; con valor = excepción para ese cliente';
COMMENT ON COLUMN config_modulos.opciones   IS 'Valores {clave: valor}; las claves y tipos válidos los define el catálogo';
