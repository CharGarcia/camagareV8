-- Agregar campo costo a la tabla productos
ALTER TABLE productos ADD COLUMN IF NOT EXISTS costo_producto NUMERIC(18,6) DEFAULT 0;

-- Tabla para componentes de productos
CREATE TABLE IF NOT EXISTS productos_componentes (
    id SERIAL PRIMARY KEY,
    id_empresa INTEGER NOT NULL,
    id_producto_padre INTEGER NOT NULL,
    id_producto_hijo INTEGER NOT NULL,
    cantidad NUMERIC(18,6) NOT NULL DEFAULT 1,
    id_medida INTEGER, -- Medida específica para el componente en este producto
    
    -- Auditoría
    created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_by INTEGER,
    eliminado BOOLEAN DEFAULT FALSE,
    deleted_at TIMESTAMP WITHOUT TIME ZONE,
    deleted_by INTEGER,
    
    CONSTRAINT fk_comp_empresa FOREIGN KEY (id_empresa) REFERENCES empresas(id),
    CONSTRAINT fk_comp_padre FOREIGN KEY (id_producto_padre) REFERENCES productos(id),
    CONSTRAINT fk_comp_hijo FOREIGN KEY (id_producto_hijo) REFERENCES productos(id),
    CONSTRAINT fk_comp_medida FOREIGN KEY (id_medida) REFERENCES unidades_medida(id)
);

-- Tabla para variantes de productos (Nombre y Valor)
CREATE TABLE IF NOT EXISTS productos_variantes (
    id SERIAL PRIMARY KEY,
    id_empresa INTEGER NOT NULL,
    id_producto INTEGER NOT NULL,
    nombre VARCHAR(100) NOT NULL, -- Ej: Color, Talla, Sabor
    valor VARCHAR(200) NOT NULL, -- Ej: Rojo, XL, Vainilla
    
    -- Auditoría
    created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_by INTEGER,
    eliminado BOOLEAN DEFAULT FALSE,
    deleted_at TIMESTAMP WITHOUT TIME ZONE,
    deleted_by INTEGER,
    
    CONSTRAINT fk_var_empresa FOREIGN KEY (id_empresa) REFERENCES empresas(id),
    CONSTRAINT fk_var_producto FOREIGN KEY (id_producto) REFERENCES productos(id)
);

-- Índices
CREATE INDEX IF NOT EXISTS idx_comp_padre ON productos_componentes(id_producto_padre);
CREATE INDEX IF NOT EXISTS idx_comp_empresa ON productos_componentes(id_empresa);
CREATE INDEX IF NOT EXISTS idx_comp_eliminado ON productos_componentes(eliminado);

CREATE INDEX IF NOT EXISTS idx_var_producto ON productos_variantes(id_producto);
CREATE INDEX IF NOT EXISTS idx_var_empresa ON productos_variantes(id_empresa);
CREATE INDEX IF NOT EXISTS idx_var_eliminado ON productos_variantes(eliminado);
