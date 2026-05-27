-- MIGRATION: Create Egresos Module Architecture
-- -----------------------------------------------------
BEGIN;

-- 1. Catálogo de Conceptos de Egreso (Por Empresa)
CREATE TABLE IF NOT EXISTS egresos_conceptos (
    id SERIAL PRIMARY KEY,
    id_empresa INTEGER NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT,
    id_cuenta_contable INTEGER DEFAULT NULL, -- Para integración contable futura
    estado VARCHAR(20) DEFAULT 'activo',
    eliminado BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_by INTEGER,
    deleted_at TIMESTAMP,
    deleted_by INTEGER
);

-- 2. Cabecera de Egresos (Egresos de Caja/Banco)
CREATE TABLE IF NOT EXISTS egresos_cabecera (
    id SERIAL PRIMARY KEY,
    id_empresa INTEGER NOT NULL,
    fecha_emision DATE NOT NULL,
    numero_egreso VARCHAR(50) NOT NULL, -- Generado por secuenciales
    id_punto_emision INTEGER,
    establecimiento VARCHAR(5),
    punto_emision VARCHAR(5),
    secuencial VARCHAR(9),
    
    tipo_egreso VARCHAR(30) NOT NULL, -- COMPRA, LIQUIDACION, ROL, QUINCENA, PRESTAMO, OTRO
    tipo_sujeto VARCHAR(20) NOT NULL, -- PROVEEDOR, EMPLEADO, OTRO
    
    -- Referencia al sujeto
    id_proveedor INTEGER,
    id_empleado INTEGER,
    
    id_egreso_concepto INTEGER, -- Solo aplica si es tipo 'OTRO'
    
    monto_total NUMERIC(18,6) NOT NULL DEFAULT 0,
    observaciones TEXT,
    
    estado VARCHAR(20) DEFAULT 'registrado', -- registrado, anulado
    eliminado BOOLEAN DEFAULT FALSE,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_by INTEGER,
    deleted_at TIMESTAMP,
    deleted_by INTEGER,
    
    CONSTRAINT fk_egreso_empresa FOREIGN KEY (id_empresa) REFERENCES empresas(id)
);

-- 3. Detalle de Egresos (Documentos cancelados)
CREATE TABLE IF NOT EXISTS egresos_detalle (
    id SERIAL PRIMARY KEY,
    id_egreso INTEGER NOT NULL,
    
    tipo_documento VARCHAR(50) NOT NULL, -- COMPRA, LIQUIDACION, ROL, MANUAL
    id_referencia_documento INTEGER,    -- ID de la factura de compra, liquidacion, etc
    numero_documento VARCHAR(50),       -- Informativo
    
    descripcion TEXT,                   -- Para tipo MANUAL u OTRO
    
    monto_documento NUMERIC(18,6) NOT NULL DEFAULT 0,
    saldo_anterior NUMERIC(18,6) NOT NULL DEFAULT 0,
    monto_pagado NUMERIC(18,6) NOT NULL DEFAULT 0,
    saldo_actual NUMERIC(18,6) NOT NULL DEFAULT 0,
    
    eliminado BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_egresodet_cabecera FOREIGN KEY (id_egreso) REFERENCES egresos_cabecera(id)
);

-- 4. Formas de Pago del Egreso
CREATE TABLE IF NOT EXISTS egresos_pagos (
    id SERIAL PRIMARY KEY,
    id_egreso INTEGER NOT NULL,
    id_forma_pago INTEGER NOT NULL,     -- Ref a empresas_formas_pagos (la creamos en Ingresos como formas_cobro, homologamos o usamos la misma tabla si es genérica. Asumiré una tabla compartida o propia si es necesario. Para consistencia usaré empresas_formas_pagos que debe existir en la db para egresos)
    
    monto NUMERIC(18,6) NOT NULL DEFAULT 0,
    referencia VARCHAR(100),            -- N# Cheque, Transf, etc.
    
    eliminado BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_egresopago_cabecera FOREIGN KEY (id_egreso) REFERENCES egresos_cabecera(id)
);

-- Índices de Búsqueda
CREATE INDEX idx_egresos_empresa ON egresos_cabecera(id_empresa);
CREATE INDEX idx_egresos_fecha ON egresos_cabecera(fecha_emision);
CREATE INDEX idx_egresos_num ON egresos_cabecera(numero_egreso);

-- Seed Inicial de Conceptos si no existen registros
INSERT INTO egresos_conceptos (id_empresa, nombre, descripcion, estado)
SELECT id, 'Pago de Servicios', 'Concepto general para pago de servicios básicos.', 'activo' FROM empresas;

INSERT INTO egresos_conceptos (id_empresa, nombre, descripcion, estado)
SELECT id, 'Gasto Administrativo', 'Gastos operativos de oficina.', 'activo' FROM empresas;

-- Registrar Submódulo en el Menú Principal (Schema Nativo)
DO $$
DECLARE
    v_id_modulo INTEGER;
    v_orden INTEGER;
BEGIN
    -- Buscar el módulo padre oficial 
    SELECT id INTO v_id_modulo 
    FROM modulos_menu 
    WHERE nombre_modulo ILIKE '%Tesorería%' 
       OR nombre_modulo ILIKE '%Contabilidad%' 
       OR nombre_modulo ILIKE '%Ventas%' 
    ORDER BY id DESC LIMIT 1;
    
    IF v_id_modulo IS NOT NULL THEN
        SELECT COALESCE(MAX(orden), 0) + 1 INTO v_orden FROM submodulos_menu WHERE id_modulo = v_id_modulo;
        
        IF NOT EXISTS (SELECT 1 FROM submodulos_menu WHERE ruta = 'modulos/egresos') THEN
            -- Icono 54 = fa-money-bill
            INSERT INTO submodulos_menu (nombre_submodulo, ruta, id_modulo, orden, id_icono, status)
            VALUES ('Egresos', 'modulos/egresos', v_id_modulo, v_orden, 54, 1);
        END IF;
    END IF;
END $$;

COMMIT;
