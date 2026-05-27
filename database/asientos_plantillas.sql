CREATE TABLE asientos_plantillas_cabecera (
    id SERIAL PRIMARY KEY,
    id_empresa INT NOT NULL,
    modulo_origen VARCHAR(50) NOT NULL, -- ej: 'factura_venta', 'compra', 'nomina'
    nombre VARCHAR(100) NOT NULL, -- ej: 'Plantilla Estándar de Ventas'
    tipo_comprobante VARCHAR(20) NOT NULL DEFAULT 'diario', -- 'diario', 'ingreso', 'egreso'
    concepto_predeterminado VARCHAR(255), -- ej: 'Venta Fra. Nro. {numero_documento}'
    status INT DEFAULT 1,
    eliminado BOOLEAN DEFAULT false,
    created_by INT,
    updated_by INT,
    deleted_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP
);

CREATE TABLE asientos_plantillas_detalle (
    id SERIAL PRIMARY KEY,
    id_empresa INT NOT NULL,
    id_plantilla INT NOT NULL,
    tipo_linea VARCHAR(50) NOT NULL, -- Identificador de lógica: 'cuenta_cobrar', 'ingreso_venta', 'iva_venta', 'costo_venta', 'inventario', 'descuento'
    naturaleza VARCHAR(5) NOT NULL CHECK (naturaleza IN ('DEBE', 'HABER')),
    id_cuenta_defecto INT, -- Cuenta por si el origen dinámico falla o no está configurado
    origen_dinamico BOOLEAN DEFAULT true, -- Si es true, intentará buscar la cuenta en el Producto, Cliente, etc.
    agrupar_por VARCHAR(30) DEFAULT 'general', -- 'general', 'producto', 'categoria', 'marca'
    valor_origen VARCHAR(50) NOT NULL, -- De dónde toma el dinero: 'total', 'subtotal', 'total_iva', 'costo_total', 'descuento'
    eliminado BOOLEAN DEFAULT false,
    created_by INT,
    updated_by INT,
    deleted_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP,
    CONSTRAINT fk_plantilla FOREIGN KEY (id_plantilla) REFERENCES asientos_plantillas_cabecera(id) ON DELETE CASCADE
);

-- Índices
CREATE INDEX idx_plantillas_cabecera_empresa ON asientos_plantillas_cabecera(id_empresa);
CREATE INDEX idx_plantillas_detalle_plantilla ON asientos_plantillas_detalle(id_plantilla);
