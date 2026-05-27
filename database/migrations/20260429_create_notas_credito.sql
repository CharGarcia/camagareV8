-- Tablas para el módulo de Notas de Crédito

-- Cabecera de Notas de Crédito
CREATE TABLE IF NOT EXISTS notas_credito_cabecera (
    id SERIAL PRIMARY KEY,
    id_empresa INTEGER NOT NULL,
    id_establecimiento INTEGER NOT NULL,
    id_punto_emision INTEGER NOT NULL,
    id_cliente INTEGER NOT NULL,
    id_usuario INTEGER NOT NULL, -- Creador de la NC
    
    fecha_emision DATE NOT NULL DEFAULT CURRENT_DATE,
    establecimiento VARCHAR(3) NOT NULL, -- Ej: 001
    punto_emision VARCHAR(3) NOT NULL, -- Ej: 001
    secuencial VARCHAR(9) NOT NULL, -- Ej: 000000001
    clave_acceso VARCHAR(49), -- Generado para el SRI
    numero_autorizacion VARCHAR(50),
    fecha_autorizacion TIMESTAMP,
    
    -- Documento Modificado (Referencia)
    cod_doc_modificado VARCHAR(2) NOT NULL DEFAULT '01', -- 01: Factura
    num_doc_modificado VARCHAR(20) NOT NULL, -- Ej: 001-001-000000001
    fecha_emision_docs_sustento DATE NOT NULL,
    motivo VARCHAR(300) NOT NULL,
    
    total_sin_impuestos NUMERIC(14,2) NOT NULL DEFAULT 0,
    total_descuento NUMERIC(14,2) NOT NULL DEFAULT 0,
    importe_total NUMERIC(14,2) NOT NULL DEFAULT 0,
    estado VARCHAR(20) NOT NULL DEFAULT 'borrador', -- borrador, pendiente, autorizado, anulado, rechazado
    
    observaciones TEXT,
    
    -- Auditoría
    created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_by INTEGER,
    eliminado BOOLEAN DEFAULT FALSE,
    deleted_at TIMESTAMP WITHOUT TIME ZONE,
    deleted_by INTEGER,
    
    CONSTRAINT fk_nc_empresa FOREIGN KEY (id_empresa) REFERENCES empresas(id),
    CONSTRAINT fk_nc_establecimiento FOREIGN KEY (id_establecimiento) REFERENCES empresa_establecimiento(id),
    CONSTRAINT fk_nc_punto FOREIGN KEY (id_punto_emision) REFERENCES empresa_punto_emision(id),
    CONSTRAINT fk_nc_cliente FOREIGN KEY (id_cliente) REFERENCES clientes(id)
);

-- Detalle de Notas de Crédito
CREATE TABLE IF NOT EXISTS notas_credito_detalle (
    id SERIAL PRIMARY KEY,
    id_nota_credito INTEGER NOT NULL,
    id_producto INTEGER,
    
    codigo_principal VARCHAR(25),
    codigo_auxiliar VARCHAR(25),
    descripcion VARCHAR(300) NOT NULL,
    cantidad NUMERIC(18,6) NOT NULL DEFAULT 1,
    precio_unitario NUMERIC(18,6) NOT NULL DEFAULT 0,
    descuento NUMERIC(14,2) NOT NULL DEFAULT 0,
    precio_total_sin_impuesto NUMERIC(14,2) NOT NULL DEFAULT 0,
    
    CONSTRAINT fk_detalle_nc FOREIGN KEY (id_nota_credito) REFERENCES notas_credito_cabecera(id) ON DELETE CASCADE,
    CONSTRAINT fk_detalle_nc_producto FOREIGN KEY (id_producto) REFERENCES productos(id)
);

-- Impuestos por Detalle de NC
CREATE TABLE IF NOT EXISTS notas_credito_detalle_impuestos (
    id SERIAL PRIMARY KEY,
    id_nota_credito_detalle INTEGER NOT NULL,
    codigo_impuesto VARCHAR(5) NOT NULL, -- 2: IVA, 3: ICE, 5: IRBPNR
    codigo_porcentaje VARCHAR(5) NOT NULL,
    tarifa NUMERIC(5,2) NOT NULL DEFAULT 0,
    base_imponible NUMERIC(14,2) NOT NULL DEFAULT 0,
    valor NUMERIC(14,2) NOT NULL DEFAULT 0,
    
    CONSTRAINT fk_impuesto_detalle_nc FOREIGN KEY (id_nota_credito_detalle) REFERENCES notas_credito_detalle(id) ON DELETE CASCADE
);

-- Crear índices
CREATE INDEX IF NOT EXISTS idx_nc_empresa ON notas_credito_cabecera(id_empresa);
CREATE INDEX IF NOT EXISTS idx_nc_cliente ON notas_credito_cabecera(id_cliente);
CREATE INDEX IF NOT EXISTS idx_nc_fecha ON notas_credito_cabecera(fecha_emision);
CREATE INDEX IF NOT EXISTS idx_nc_eliminado ON notas_credito_cabecera(eliminado);
