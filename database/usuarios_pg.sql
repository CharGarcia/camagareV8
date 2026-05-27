-- PostgreSQL: tabla usuarios
-- Codificación de la base: UTF8

CREATE TABLE usuarios (
    id              SERIAL PRIMARY KEY,
    nombre          VARCHAR(100) NOT NULL,
    cedula          VARCHAR(15)  NOT NULL,
    password        VARCHAR(255) NOT NULL,
    nivel           SMALLINT     NOT NULL,
    mail            VARCHAR(100) NOT NULL,
    token           VARCHAR(100) NOT NULL,
    estado          SMALLINT     NOT NULL DEFAULT 1,
    fecha_agregado  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    telefono        VARCHAR(20)  NOT NULL,
    CONSTRAINT uk_usuarios_cedula UNIQUE (cedula)
);

COMMENT ON TABLE usuarios IS 'Usuarios del sistema';
COMMENT ON COLUMN usuarios.nombre IS 'nombre completo';

-- password: debe ser hash bcrypt (password_hash en PHP) o MD5 de 32 hex (legado).
-- Ejemplo en terminal: php -r "echo password_hash('TuClave', PASSWORD_DEFAULT), PHP_EOL;"
-- token: puede ser cadena vacía '' si aún no hay recuperación de clave.

-- Tras importar datos con INSERT/COPY con ids explícitos:
-- SELECT setval(pg_get_serial_sequence('usuarios', 'id'), COALESCE((SELECT MAX(id) FROM usuarios), 1));
