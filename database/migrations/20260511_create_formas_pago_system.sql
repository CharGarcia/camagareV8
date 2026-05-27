-- ==========================================================
-- MÓDULO DE FORMAS DE PAGO - Evolución Arquitectónica
-- Consolida Formas de Cobro y Formas de Pago
-- ==========================================================
BEGIN;

-- 1. Homologar y Renombrar Tabla Existente si existe para no perder datos
DO $$
BEGIN
    IF EXISTS (SELECT FROM pg_tables WHERE schemaname = 'public' AND tablename = 'empresa_formas_cobro') 
       AND NOT EXISTS (SELECT FROM pg_tables WHERE schemaname = 'public' AND tablename = 'empresa_formas_pago') THEN
        ALTER TABLE empresa_formas_cobro RENAME TO empresa_formas_pago;
    END IF;
END $$;

-- 2. Crear Tabla si no existe en absoluto
CREATE TABLE IF NOT EXISTS empresa_formas_pago (
    id SERIAL PRIMARY KEY,
    id_empresa INTEGER NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    activo BOOLEAN DEFAULT TRUE,
    eliminado BOOLEAN DEFAULT FALSE,
    
    -- Auditoría
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_by INTEGER,
    deleted_at TIMESTAMP,
    deleted_by INTEGER,

    CONSTRAINT fk_formapago_empresa FOREIGN KEY (id_empresa) REFERENCES empresas(id)
);

-- 3. Agregar Campos Avanzados a la Tabla Unificada
DO $$
BEGIN
    -- Agregar TIPO (EFECTIVO, BANCO, TARJETA, CHEQUE, OTRO)
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='empresa_formas_pago' AND column_name='tipo') THEN
        ALTER TABLE empresa_formas_pago ADD COLUMN tipo VARCHAR(30) DEFAULT 'EFECTIVO';
    END IF;

    -- Agregar APLICA EN (INGRESO, EGRESO, AMBAS)
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='empresa_formas_pago' AND column_name='aplica_en') THEN
        ALTER TABLE empresa_formas_pago ADD COLUMN aplica_en VARCHAR(20) DEFAULT 'AMBAS';
    END IF;

    -- Agregar Relación con Bancos
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='empresa_formas_pago' AND column_name='id_banco') THEN
        ALTER TABLE empresa_formas_pago ADD COLUMN id_banco INTEGER DEFAULT NULL;
        ALTER TABLE empresa_formas_pago ADD CONSTRAINT fk_formapago_banco FOREIGN KEY (id_banco) REFERENCES bancos_ecuador(id);
    END IF;

    -- Agregar Tipo Cuenta (AHORROS, CORRIENTE, VIRTUAL)
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='empresa_formas_pago' AND column_name='tipo_cuenta') THEN
        ALTER TABLE empresa_formas_pago ADD COLUMN tipo_cuenta VARCHAR(30) DEFAULT NULL;
    END IF;

    -- Agregar Número Cuenta o Tarjeta
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='empresa_formas_pago' AND column_name='numero_cuenta') THEN
        ALTER TABLE empresa_formas_pago ADD COLUMN numero_cuenta VARCHAR(50) DEFAULT NULL;
    END IF;

    -- Agregar Relación Contable Opcional
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='empresa_formas_pago' AND column_name='id_cuenta_contable') THEN
        ALTER TABLE empresa_formas_pago ADD COLUMN id_cuenta_contable INTEGER DEFAULT NULL;
        -- La tabla plan_cuentas existe
        ALTER TABLE empresa_formas_pago ADD CONSTRAINT fk_formapago_cuenta FOREIGN KEY (id_cuenta_contable) REFERENCES plan_cuentas(id);
    END IF;
END $$;

-- 4. Actualizar Restricciones Externas si existen
DO $$
BEGIN
    -- Validar Egreso Pagos
    IF EXISTS (SELECT FROM pg_tables WHERE schemaname = 'public' AND tablename = 'egresos_pagos') THEN
        IF NOT EXISTS (SELECT 1 FROM information_schema.table_constraints WHERE constraint_name='fk_egresopago_formapago' AND table_name='egresos_pagos') THEN
            ALTER TABLE egresos_pagos ADD CONSTRAINT fk_egresopago_formapago FOREIGN KEY (id_forma_pago) REFERENCES empresa_formas_pago(id);
        END IF;
    END IF;
END $$;

-- 5. Migración inicial de Datos: Normalizar registros antiguos (opcional pero recomendado)
UPDATE empresa_formas_pago SET tipo = 'EFECTIVO', aplica_en = 'AMBAS' WHERE tipo IS NULL OR tipo = 'EFECTIVO';
UPDATE empresa_formas_pago SET tipo = 'BANCO' WHERE nombre ILIKE '%TRANSFERENCIA%' OR nombre ILIKE '%BANCO%';
UPDATE empresa_formas_pago SET tipo = 'CHEQUE' WHERE nombre ILIKE '%CHEQUE%';
UPDATE empresa_formas_pago SET tipo = 'TARJETA' WHERE nombre ILIKE '%TARJETA%';

-- 6. Inserción de Menú en Tesorería / Configuración (Schema Nativo)
DO $$
DECLARE
    v_id_padre INTEGER;
    v_orden INTEGER;
BEGIN
    -- Buscar módulo padre oficial según dump real de modulos_menu
    SELECT id INTO v_id_padre 
    FROM modulos_menu 
    WHERE nombre_modulo ILIKE '%Tesorería%' 
       OR nombre_modulo ILIKE '%Configuración%' 
       OR nombre_modulo ILIKE '%Contabilidad%' 
    ORDER BY id DESC LIMIT 1;
    
    IF v_id_padre IS NOT NULL THEN
        -- Obtener orden consecutivo
        SELECT COALESCE(MAX(orden), 0) + 1 INTO v_orden FROM submodulos_menu WHERE id_modulo = v_id_padre;
        
        -- Validar existencia por ruta real del sistema
        IF NOT EXISTS (SELECT 1 FROM submodulos_menu WHERE ruta = 'modulos/formas_cobros_pagos') THEN
            -- Usamos id_icono = 56 (fa-credit-card) real de la DB
            INSERT INTO submodulos_menu (nombre_submodulo, ruta, id_modulo, orden, id_icono, status)
            VALUES ('Formas de Pago', 'modulos/formas_cobros_pagos', v_id_padre, v_orden, 56, 1);
        END IF;
    END IF;
END $$;

COMMIT;
