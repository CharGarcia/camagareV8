CREATE TABLE clientes (
    id SERIAL PRIMARY KEY,
    id_empresa INT NOT NULL,
    tipo_identificacion VARCHAR(20) NOT NULL,
    identificacion VARCHAR(50) NOT NULL,
    razon_social VARCHAR(200) NOT NULL,
    direccion VARCHAR(255) DEFAULT NULL,
    telefono VARCHAR(50) DEFAULT NULL,
    correo VARCHAR(150) DEFAULT NULL,
    estado SMALLINT DEFAULT 1 NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_clientes_empresa ON clientes(id_empresa);
