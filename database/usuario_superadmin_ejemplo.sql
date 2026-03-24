-- Ejemplo: insertar solo un SuperAdmin (nivel 3) en MySQL.
-- La aplicación permite iniciar sesión sin empresa asignada solo si nivel = 3.
-- Luego cree empresas en: Configuración → Empresas del sistema (al crear la primera, se le asigna automáticamente).

-- 1) Generar hash de contraseña (ejecutar en consola del servidor):
--    php -r "echo password_hash('SU_CLAVE_AQUI', PASSWORD_DEFAULT);"
-- 2) Sustituir EL_HASH_BCRYPT abajo y ajustar cédula, nombre, mail.

INSERT INTO usuarios (nombre, cedula, password, nivel, estado, mail)
VALUES (
    'Administrador',
    '0000000000',
    'EL_HASH_BCRYPT',
    3,
    1,
    'admin@ejemplo.com'
);

-- Comprobar:
-- SELECT id, nombre, cedula, nivel, estado FROM usuarios WHERE nivel = 3;
