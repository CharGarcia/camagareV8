-- Configuración de Kushki por empresa (multitenancy).
-- Cada empresa del sistema tiene sus propias credenciales.
CREATE TABLE IF NOT EXISTS kushki_config (
    id          SERIAL PRIMARY KEY,
    id_empresa  INTEGER      NOT NULL UNIQUE,
    public_key  VARCHAR(300) NOT NULL,
    private_key VARCHAR(300) NOT NULL,
    ambiente    VARCHAR(20)  NOT NULL DEFAULT 'uat',   -- 'uat' | 'production'
    moneda      VARCHAR(3)   NOT NULL DEFAULT 'USD',
    activo      BOOLEAN      NOT NULL DEFAULT true,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN      NOT NULL DEFAULT false
);

-- Registrar el submódulo en el menú (módulo Configuración id=310, icono 65 = engranaje)
INSERT INTO submodulos_menu (nombre_submodulo, ruta, id_modulo, orden, id_icono, status)
VALUES ('Configuración Kushki', 'modulos/configuracion-kushki', 310, 2, 65, 1)
ON CONFLICT DO NOTHING;
