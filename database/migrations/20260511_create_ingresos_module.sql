-- ============================================================
-- MÓDULO DE INGRESOS — Migración de Base de Datos
-- Tablas: empresa_formas_cobro, empresa_ingreso_conceptos, 
--         ingresos_cabecera, ingresos_detalle, ingresos_pagos
-- ============================================================

-- 1. CATÁLOGOS CONFIGURABLES POR EMPRESA (Preparación para futuros módulos)
CREATE TABLE IF NOT EXISTS empresa_formas_cobro (
    id SERIAL PRIMARY KEY,
    id_empresa INTEGER NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    activo BOOLEAN DEFAULT TRUE,
    -- Auditoría
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_by INTEGER,
    eliminado BOOLEAN DEFAULT FALSE,
    deleted_at TIMESTAMP,
    deleted_by INTEGER,

    CONSTRAINT fk_forma_cobro_empresa FOREIGN KEY (id_empresa) REFERENCES empresas(id)
);

CREATE TABLE IF NOT EXISTS empresa_ingreso_conceptos (
    id SERIAL PRIMARY KEY,
    id_empresa INTEGER NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    activo BOOLEAN DEFAULT TRUE,
    -- Auditoría
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_by INTEGER,
    eliminado BOOLEAN DEFAULT FALSE,
    deleted_at TIMESTAMP,
    deleted_by INTEGER,

    CONSTRAINT fk_concepto_ingreso_empresa FOREIGN KEY (id_empresa) REFERENCES empresas(id)
);

-- 2. CABECERA DE INGRESOS
CREATE TABLE IF NOT EXISTS ingresos_cabecera (
    id SERIAL PRIMARY KEY,
    id_empresa INTEGER NOT NULL,
    id_establecimiento INTEGER,
    id_punto_emision INTEGER,
    id_cliente INTEGER, -- Opcional, dependiendo del tipo de ingreso
    id_usuario INTEGER NOT NULL, -- Creador del ingreso
    
    fecha_emision DATE NOT NULL DEFAULT CURRENT_DATE,
    establecimiento VARCHAR(3),
    punto_emision VARCHAR(3),
    secuencial VARCHAR(9) NOT NULL,
    numero_ingreso VARCHAR(20) NOT NULL, -- Formato 001-001-000000001

    tipo_ingreso VARCHAR(30) NOT NULL, -- 'FACTURA_VENTA', 'RECIBO_VENTA', 'OTRO'
    id_ingreso_concepto INTEGER, -- Si tipo = 'OTRO', apunta al catálogo
    
    monto_total NUMERIC(14,2) NOT NULL DEFAULT 0,
    observaciones TEXT,
    estado VARCHAR(20) NOT NULL DEFAULT 'registrado', -- borrador, registrado, anulado
    
    -- Auditoría
    created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_by INTEGER,
    eliminado BOOLEAN DEFAULT FALSE,
    deleted_at TIMESTAMP WITHOUT TIME ZONE,
    deleted_by INTEGER,
    
    CONSTRAINT fk_ingreso_empresa FOREIGN KEY (id_empresa) REFERENCES empresas(id),
    CONSTRAINT fk_ingreso_cliente FOREIGN KEY (id_cliente) REFERENCES clientes(id),
    CONSTRAINT fk_ingreso_concepto FOREIGN KEY (id_ingreso_concepto) REFERENCES empresa_ingreso_conceptos(id),
    CONSTRAINT fk_ingreso_establecimiento FOREIGN KEY (id_establecimiento) REFERENCES empresa_establecimiento(id),
    CONSTRAINT fk_ingreso_punto FOREIGN KEY (id_punto_emision) REFERENCES empresa_punto_emision(id)
);

CREATE INDEX IF NOT EXISTS idx_ingresos_cab_empresa ON ingresos_cabecera(id_empresa);
CREATE INDEX IF NOT EXISTS idx_ingresos_cab_cliente ON ingresos_cabecera(id_cliente);
CREATE INDEX IF NOT EXISTS idx_ingresos_cab_eliminado ON ingresos_cabecera(eliminado);
CREATE INDEX IF NOT EXISTS idx_ingresos_cab_fecha ON ingresos_cabecera(fecha_emision);

-- 3. DETALLE DE INGRESOS (Relación con Facturas, Recibos u Otros conceptos)
CREATE TABLE IF NOT EXISTS ingresos_detalle (
    id SERIAL PRIMARY KEY,
    id_ingreso INTEGER NOT NULL,
    tipo_documento VARCHAR(30) NOT NULL, -- 'FACTURA', 'RECIBO', 'DIRECTO'
    id_referencia_documento INTEGER, -- Opcional: ID de la factura o recibo
    numero_documento VARCHAR(50), -- Para históricos y referencias rápidas
    descripcion TEXT,
    monto_documento NUMERIC(14,2) NOT NULL DEFAULT 0,
    saldo_anterior NUMERIC(14,2) NOT NULL DEFAULT 0,
    monto_cobrado NUMERIC(14,2) NOT NULL DEFAULT 0,
    saldo_actual NUMERIC(14,2) NOT NULL DEFAULT 0,

    CONSTRAINT fk_detalle_ingreso_cabecera FOREIGN KEY (id_ingreso) REFERENCES ingresos_cabecera(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_ingresos_det_cabecera ON ingresos_detalle(id_ingreso);

-- 4. FORMAS DE COBRO APLICADAS EN EL INGRESO
CREATE TABLE IF NOT EXISTS ingresos_pagos (
    id SERIAL PRIMARY KEY,
    id_ingreso INTEGER NOT NULL,
    id_forma_cobro INTEGER NOT NULL,
    monto NUMERIC(14,2) NOT NULL DEFAULT 0,
    referencia VARCHAR(100), -- Ej: Nro cheque, comprobante transferencia
    observaciones TEXT,

    CONSTRAINT fk_pago_ingreso_cabecera FOREIGN KEY (id_ingreso) REFERENCES ingresos_cabecera(id) ON DELETE CASCADE,
    CONSTRAINT fk_pago_forma_cobro FOREIGN KEY (id_forma_cobro) REFERENCES empresa_formas_cobro(id)
);

CREATE INDEX IF NOT EXISTS idx_ingresos_pagos_cabecera ON ingresos_pagos(id_ingreso);

-- 5. INSERTAR VALORES POR DEFECTO PARA EMPRESAS EXISTENTES (Si hay)
-- En un escenario productivo, un seeder o routine correría esto.
-- Para desarrollo, insertamos unos básicos para la empresa ID 1 (si existe) o generalizamos
DO $$
DECLARE
    emp_id INT;
BEGIN
    FOR emp_id IN SELECT id FROM empresas LOOP
        -- Formas de cobro por defecto por empresa
        IF NOT EXISTS (SELECT 1 FROM empresa_formas_cobro WHERE id_empresa = emp_id AND nombre = 'EFECTIVO') THEN
            INSERT INTO empresa_formas_cobro (id_empresa, nombre) VALUES (emp_id, 'EFECTIVO');
        END IF;
        IF NOT EXISTS (SELECT 1 FROM empresa_formas_cobro WHERE id_empresa = emp_id AND nombre = 'TRANSFERENCIA BANCARIA') THEN
            INSERT INTO empresa_formas_cobro (id_empresa, nombre) VALUES (emp_id, 'TRANSFERENCIA BANCARIA');
        END IF;
        IF NOT EXISTS (SELECT 1 FROM empresa_formas_cobro WHERE id_empresa = emp_id AND nombre = 'CHEQUE') THEN
            INSERT INTO empresa_formas_cobro (id_empresa, nombre) VALUES (emp_id, 'CHEQUE');
        END IF;
        IF NOT EXISTS (SELECT 1 FROM empresa_formas_cobro WHERE id_empresa = emp_id AND nombre = 'TARJETA DE CRÉDITO/DÉBITO') THEN
            INSERT INTO empresa_formas_cobro (id_empresa, nombre) VALUES (emp_id, 'TARJETA DE CRÉDITO/DÉBITO');
        END IF;
        
        -- Conceptos por defecto por empresa
        IF NOT EXISTS (SELECT 1 FROM empresa_ingreso_conceptos WHERE id_empresa = emp_id AND nombre = 'PRÉSTAMO') THEN
            INSERT INTO empresa_ingreso_conceptos (id_empresa, nombre) VALUES (emp_id, 'PRÉSTAMO');
        END IF;
        IF NOT EXISTS (SELECT 1 FROM empresa_ingreso_conceptos WHERE id_empresa = emp_id AND nombre = 'ANTICIPOS') THEN
            INSERT INTO empresa_ingreso_conceptos (id_empresa, nombre) VALUES (emp_id, 'ANTICIPOS');
        END IF;
        IF NOT EXISTS (SELECT 1 FROM empresa_ingreso_conceptos WHERE id_empresa = emp_id AND nombre = 'OTROS INGRESOS') THEN
            INSERT INTO empresa_ingreso_conceptos (id_empresa, nombre) VALUES (emp_id, 'OTROS INGRESOS');
        END IF;
    END LOOP;
END $$;

-- 6. INSERTAR SUBMÓDULO EN EL MENÚ (si no existe)
DO $$
DECLARE v_id_modulo INTEGER;
BEGIN
    -- Intentar buscar el módulo de "Ventas" para anclar Ingresos.
    SELECT id INTO v_id_modulo
    FROM modulos_menu
    WHERE nombre_modulo ILIKE '%venta%'
    ORDER BY id LIMIT 1;

    -- Si no existe "Ventas", buscar uno de tesorería o similar.
    IF v_id_modulo IS NULL THEN
        SELECT id INTO v_id_modulo
        FROM modulos_menu
        WHERE nombre_modulo ILIKE '%caja%' OR nombre_modulo ILIKE '%tesore%'
        ORDER BY id LIMIT 1;
    END IF;

    -- Si existe un módulo padre, insertar el submódulo.
    IF v_id_modulo IS NOT NULL THEN
        IF NOT EXISTS (SELECT 1 FROM submodulos_menu WHERE ruta = 'modulos/ingresos') THEN
            INSERT INTO submodulos_menu (id_modulo, nombre_submodulo, ruta, status, icono_submodulo)
            VALUES (v_id_modulo, 'Ingresos / Cobros', 'modulos/ingresos', 1, 'bi bi-wallet2');
        END IF;
    END IF;
END $$;
