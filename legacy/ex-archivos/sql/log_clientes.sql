-- Log de cambios en clientes (crear, editar, eliminar)
-- Ejecutar en phpMyAdmin o MySQL para asegurar que la tabla esté correcta

-- Si la tabla ya existe con tu estructura, ejecuta estos ALTER (uno por uno):
-- 1. Agregar columna accion (si da error "Duplicate column", ya existe):
ALTER TABLE log_clientes ADD COLUMN accion VARCHAR(20) NOT NULL DEFAULT 'editar' COMMENT 'crear, editar, eliminar' AFTER id;
-- 2. Asegurar AUTO_INCREMENT en id (evita "Duplicate entry 0 for key PRIMARY"):
ALTER TABLE log_clientes MODIFY id INT(11) NOT NULL AUTO_INCREMENT;

-- Si la tabla NO existe, crearla completa:
CREATE TABLE IF NOT EXISTS log_clientes (
  id INT(11) NOT NULL AUTO_INCREMENT,
  accion VARCHAR(20) NOT NULL DEFAULT 'editar' COMMENT 'crear, editar, eliminar',
  id_cliente INT(11) NOT NULL,
  ruc_empresa VARCHAR(13) NOT NULL,
  nombre VARCHAR(200) NOT NULL,
  tipo_id VARCHAR(2) NOT NULL,
  ruc_cliente VARCHAR(13) NOT NULL,
  telefono VARCHAR(100) NOT NULL DEFAULT '',
  email VARCHAR(200) NOT NULL DEFAULT '',
  direccion VARCHAR(200) NOT NULL DEFAULT '',
  fecha_modificado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  plazo INT(11) NOT NULL DEFAULT 1,
  id_usuario INT(11) NOT NULL,
  provincia VARCHAR(100) NOT NULL DEFAULT '',
  ciudad VARCHAR(100) NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  KEY idx_log_clientes_fecha (fecha_modificado),
  KEY idx_log_clientes_cliente (id_cliente),
  KEY idx_log_clientes_empresa (ruc_empresa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- NOTA: El log se registra desde PHP (ClienteModel::logCliente) DESPUÉS del UPDATE.
-- Si hay un trigger en clientes que inserta en log_clientes, se crearán 2 registros.
-- Para eliminar el trigger duplicado, ejecute: php sql/drop_trigger_log_clientes.php
