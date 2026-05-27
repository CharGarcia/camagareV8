-- Expansión del módulo de empleados para incluir información laboral, histórica y rubros fijos.

-- 1. Actualización de la tabla 'empleados'
ALTER TABLE empleados 
    ADD COLUMN fondos_reserva VARCHAR(20) DEFAULT 'no_se_paga', -- 'rol', 'planilla', 'no_se_paga'
    ADD COLUMN aporta_iess BOOLEAN DEFAULT TRUE,
    ADD COLUMN decimo_tercero VARCHAR(20) DEFAULT 'acumula', -- 'mensualiza', 'acumula'
    ADD COLUMN decimo_cuarto VARCHAR(20) DEFAULT 'acumula', -- 'mensualiza', 'acumula'
    ADD COLUMN aporte_personal DECIMAL(10, 4) DEFAULT 9.45,
    ADD COLUMN aporte_patronal DECIMAL(10, 4) DEFAULT 11.15,
    ADD COLUMN sueldo_base DECIMAL(10, 2) DEFAULT 0.00,
    ADD COLUMN valor_quincena DECIMAL(10, 2) DEFAULT 0.00,
    ADD COLUMN forma_pago VARCHAR(20) DEFAULT 'mensual', -- 'semanal', 'quincenal', 'mensual'
    ADD COLUMN region VARCHAR(20) DEFAULT 'costa', -- 'costa', 'sierra', 'oriente', 'insular'
    ADD COLUMN cargo VARCHAR(100),
    ADD COLUMN lugar_trabajo VARCHAR(200),
    ADD COLUMN horario_trabajo VARCHAR(100),
    ADD COLUMN departamento VARCHAR(100),
    ADD COLUMN codigo_sectorial_iess VARCHAR(50);

-- 2. Tabla historial de periodos laborales
CREATE TABLE IF NOT EXISTS empleado_periodos (
    id SERIAL PRIMARY KEY,
    id_empleado INTEGER NOT NULL REFERENCES empleados(id),
    id_empresa INTEGER NOT NULL,
    fecha_ingreso DATE NOT NULL,
    fecha_salida DATE,
    motivo_salida TEXT,
    eliminado BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_by INTEGER,
    deleted_at TIMESTAMP,
    deleted_by INTEGER
);

-- 3. Tabla rubros fijos (ingresos y descuentos)
CREATE TABLE IF NOT EXISTS empleado_rubros_fijos (
    id SERIAL PRIMARY KEY,
    id_empleado INTEGER NOT NULL REFERENCES empleados(id),
    id_empresa INTEGER NOT NULL,
    tipo VARCHAR(20) NOT NULL, -- 'ingreso', 'descuento'
    nombre VARCHAR(100) NOT NULL,
    valor DECIMAL(10, 2) DEFAULT 0.00,
    aporta_iess BOOLEAN DEFAULT FALSE,
    frecuencia VARCHAR(20) DEFAULT 'mensual',
    eliminado BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_by INTEGER,
    deleted_at TIMESTAMP,
    deleted_by INTEGER
);

-- Índices para mejorar rendimiento
CREATE INDEX idx_periodos_empleado ON empleado_periodos(id_empleado, id_empresa);
CREATE INDEX idx_rubros_empleado ON empleado_rubros_fijos(id_empleado, id_empresa);
