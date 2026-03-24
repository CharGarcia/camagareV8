-- Tabla correos_config - Configuraciones de correo por propósito
-- Propósitos: recuperar_password, notificaciones, cobros, soporte, etc.
-- Ejecute este script en su base de datos antes de usar el módulo.

CREATE TABLE IF NOT EXISTS correos_config (
    id_correo_config INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(64) NOT NULL UNIQUE COMMENT 'Identificador único: recuperar_password, notificaciones, cobros, etc.',
    nombre VARCHAR(128) NOT NULL COMMENT 'Etiqueta legible',
    email VARCHAR(255) NOT NULL COMMENT 'Correo remitente',
    nombre_remitente VARCHAR(128) DEFAULT '' COMMENT 'Nombre que aparece como remitente',
    host_smtp VARCHAR(255) NOT NULL DEFAULT 'smtp.gmail.com',
    puerto_smtp SMALLINT UNSIGNED NOT NULL DEFAULT 587,
    usuario_smtp VARCHAR(255) DEFAULT '',
    password_smtp VARCHAR(255) DEFAULT '' COMMENT 'Contraseña SMTP (considerar cifrado en producción)',
    encryption VARCHAR(16) DEFAULT 'tls' COMMENT 'tls, ssl o vacío (none)',
    status TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=activo, 0=inactivo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_codigo (codigo),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
