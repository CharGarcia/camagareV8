-- ========================================================
-- TABLA: asientos_contables_cabecera
-- ========================================================
CREATE TABLE asientos_contables_cabecera (
    id BIGSERIAL PRIMARY KEY,
    id_empresa INTEGER NOT NULL,
    
    fecha_asiento DATE NOT NULL,
    tipo_comprobante VARCHAR(50) NOT NULL, -- Ej: 'diario', 'ingreso', 'egreso', 'apertura', 'cierre'
    numero_comprobante VARCHAR(50) NOT NULL, -- Ej: 'DI-000001'
    concepto TEXT NOT NULL,
    estado VARCHAR(50) DEFAULT 'borrador', -- 'borrador', 'contabilizado', 'anulado'
    
    modulo_origen VARCHAR(50) DEFAULT 'manual', -- Ej: 'factura_venta', 'compra', 'nomina', 'manual'
    id_referencia_origen BIGINT, -- ID del registro en el módulo origen
    
    total_debe NUMERIC(15,2) DEFAULT 0.00,
    total_haber NUMERIC(15,2) DEFAULT 0.00,
    
    observaciones TEXT,
    
    -- Campos de auditoría (Eliminación Lógica)
    eliminado BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_by INTEGER,
    deleted_at TIMESTAMP,
    deleted_by INTEGER
);

-- Índices de búsqueda
CREATE INDEX idx_asientos_cab_empresa ON asientos_contables_cabecera(id_empresa);
CREATE INDEX idx_asientos_cab_origen ON asientos_contables_cabecera(id_empresa, modulo_origen, id_referencia_origen);
CREATE INDEX idx_asientos_cab_fecha ON asientos_contables_cabecera(id_empresa, fecha_asiento);
CREATE INDEX idx_asientos_cab_estado ON asientos_contables_cabecera(id_empresa, estado) WHERE eliminado = false;


-- ========================================================
-- TABLA: asientos_contables_detalle
-- ========================================================
CREATE TABLE asientos_contables_detalle (
    id BIGSERIAL PRIMARY KEY,
    id_empresa INTEGER NOT NULL,
    id_asiento BIGINT NOT NULL REFERENCES asientos_contables_cabecera(id) ON DELETE CASCADE,
    
    id_cuenta_contable INTEGER NOT NULL, -- FK a la tabla del Plan de Cuentas
    id_centro_costo INTEGER, -- FK a centros de costo
    id_proyecto INTEGER, -- FK a proyectos
    
    debe NUMERIC(15,2) DEFAULT 0.00,
    haber NUMERIC(15,2) DEFAULT 0.00,
    
    referencia_detalle VARCHAR(255), -- Glosa específica de la línea
    documento_referencia VARCHAR(100), -- Ej: Nro de Factura, Nro de Cheque
    
    id_entidad BIGINT, -- ID genérico del Proveedor, Cliente o Empleado
    tipo_entidad VARCHAR(50), -- 'proveedor', 'cliente', 'empleado' (Polimórfico)
    
    -- Campos de auditoría
    eliminado BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_by INTEGER,
    deleted_at TIMESTAMP,
    deleted_by INTEGER
);

-- Índices de búsqueda
CREATE INDEX idx_asientos_det_asiento ON asientos_contables_detalle(id_asiento);
CREATE INDEX idx_asientos_det_cuenta ON asientos_contables_detalle(id_empresa, id_cuenta_contable);
CREATE INDEX idx_asientos_det_cc_proy ON asientos_contables_detalle(id_empresa, id_centro_costo, id_proyecto);
CREATE INDEX idx_asientos_det_entidad ON asientos_contables_detalle(tipo_entidad, id_entidad);

-- ========================================================
-- Opcional: Submódulo y Permisos (Si lo tienes automatizado)
-- ========================================================
-- INSERT INTO submodulos (id_modulo, nombre, ruta_mvc, icono, orden, status, modulo_general) 
-- VALUES (...);
