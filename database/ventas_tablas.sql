-- Tablas para el módulo de Ventas (Facturación)

-- Cabecera de Ventas
CREATE TABLE IF NOT EXISTS ventas_cabecera (
    id SERIAL PRIMARY KEY,
    id_empresa INTEGER NOT NULL,
    id_establecimiento INTEGER NOT NULL,
    id_punto_emision INTEGER NOT NULL,
    id_cliente INTEGER NOT NULL,
    id_usuario INTEGER NOT NULL, -- Creador de la factura
    
    fecha_emision DATE NOT NULL DEFAULT CURRENT_DATE,
    establecimiento VARCHAR(3) NOT NULL, -- Ej: 001
    punto_emision VARCHAR(3) NOT NULL, -- Ej: 001
    secuencial VARCHAR(9) NOT NULL, -- Ej: 000000001
    factura_numero VARCHAR(20) UNIQUE NOT NULL, -- Ej: 001-001-000000001
    
    clave_acceso VARCHAR(49), -- Generado para el SRI
    numero_autorizacion VARCHAR(50),
    fecha_autorizacion TIMESTAMP,
    guia_remision VARCHAR(20),
    
    total_sin_impuestos NUMERIC(14,2) NOT NULL DEFAULT 0,
    total_descuento NUMERIC(14,2) NOT NULL DEFAULT 0,
    importe_total NUMERIC(14,2) NOT NULL DEFAULT 0,
    propina NUMERIC(14,2) NOT NULL DEFAULT 0,
    moneda VARCHAR(10) NOT NULL DEFAULT 'DOLAR',
    estado VARCHAR(20) NOT NULL DEFAULT 'borrador', -- borrador, pendiente, autorizado, anulado, rechazado
    
    id_sustento_tributario INTEGER,
    observaciones TEXT,
    
    -- Auditoría
    created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_by INTEGER,
    eliminado BOOLEAN DEFAULT FALSE,
    deleted_at TIMESTAMP WITHOUT TIME ZONE,
    deleted_by INTEGER,
    
    CONSTRAINT fk_ventas_empresa FOREIGN KEY (id_empresa) REFERENCES empresas(id),
    CONSTRAINT fk_ventas_establecimiento FOREIGN KEY (id_establecimiento) REFERENCES empresa_establecimiento(id),
    CONSTRAINT fk_ventas_punto FOREIGN KEY (id_punto_emision) REFERENCES empresa_punto_emision(id),
    CONSTRAINT fk_ventas_cliente FOREIGN KEY (id_cliente) REFERENCES clientes(id)
);

-- Detalle de Ventas
CREATE TABLE IF NOT EXISTS ventas_detalle (
    id SERIAL PRIMARY KEY,
    id_venta INTEGER NOT NULL,
    id_producto INTEGER NOT NULL,
    id_bodega INTEGER, -- De dónde sale la mercadería
    
    codigo_principal VARCHAR(25),
    codigo_auxiliar VARCHAR(25),
    descripcion VARCHAR(300) NOT NULL,
    cantidad NUMERIC(18,6) NOT NULL DEFAULT 1,
    precio_unitario NUMERIC(18,6) NOT NULL DEFAULT 0,
    descuento NUMERIC(14,2) NOT NULL DEFAULT 0,
    precio_total_sin_impuesto NUMERIC(14,2) NOT NULL DEFAULT 0,
    
    id_inventario_kardex INTEGER, -- Relación con movimiento de inventario
    
    CONSTRAINT fk_detalle_venta FOREIGN KEY (id_venta) REFERENCES ventas_cabecera(id) ON DELETE CASCADE,
    CONSTRAINT fk_detalle_producto FOREIGN KEY (id_producto) REFERENCES productos(id),
    CONSTRAINT fk_detalle_bodega FOREIGN KEY (id_bodega) REFERENCES bodegas(id)
);

-- Impuestos por Detalle
CREATE TABLE IF NOT EXISTS ventas_detalle_impuestos (
    id SERIAL PRIMARY KEY,
    id_venta_detalle INTEGER NOT NULL,
    codigo_impuesto VARCHAR(5) NOT NULL, -- 2: IVA, 3: ICE, 5: IRBPNR
    codigo_porcentaje VARCHAR(5) NOT NULL,
    tarifa NUMERIC(5,2) NOT NULL DEFAULT 0,
    base_imponible NUMERIC(14,2) NOT NULL DEFAULT 0,
    valor NUMERIC(14,2) NOT NULL DEFAULT 0,
    
    CONSTRAINT fk_impuesto_detalle FOREIGN KEY (id_venta_detalle) REFERENCES ventas_detalle(id) ON DELETE CASCADE
);

-- Pagos de la Factura
CREATE TABLE IF NOT EXISTS ventas_pagos (
    id SERIAL PRIMARY KEY,
    id_venta INTEGER NOT NULL,
    forma_pago VARCHAR(5) NOT NULL, -- SRI codigos
    total NUMERIC(14,2) NOT NULL DEFAULT 0,
    plazo INTEGER DEFAULT 0,
    unidad_tiempo VARCHAR(20) DEFAULT 'dias',
    
    CONSTRAINT fk_pago_venta FOREIGN KEY (id_venta) REFERENCES ventas_cabecera(id) ON DELETE CASCADE
);

-- Información Adicional
CREATE TABLE IF NOT EXISTS ventas_adicional (
    id SERIAL PRIMARY KEY,
    id_venta INTEGER NOT NULL,
    nombre VARCHAR(300) NOT NULL,
    valor VARCHAR(300) NOT NULL,
    
    CONSTRAINT fk_adicional_venta FOREIGN KEY (id_venta) REFERENCES ventas_cabecera(id) ON DELETE CASCADE
);

-- Crear índices para mejorar rendimiento
CREATE INDEX IF NOT EXISTS idx_ventas_empresa ON ventas_cabecera(id_empresa);
CREATE INDEX IF NOT EXISTS idx_ventas_cliente ON ventas_cabecera(id_cliente);
CREATE INDEX IF NOT EXISTS idx_ventas_fecha ON ventas_cabecera(fecha_emision);
CREATE INDEX IF NOT EXISTS idx_ventas_eliminado ON ventas_cabecera(eliminado);
