-- MIGRATION: Módulo Índices Financieros
-- Dos niveles configurables por empresa:
--   1) Clasificación Corriente/No Corriente de cuentas de Activo y Pasivo
--      (lo único que el sistema no puede inferir solo por prefijo de código).
--   2) Grupos de cuentas ad-hoc + índices con fórmula propia (árbol JSON,
--      evaluado por IndicesFinancierosService::evaluarFormula — sin eval()).
-- No confundir con App\Services\SuperciasEvaluatorService (motor de fórmulas
-- de texto plano +/- para reportes regulatorios ESF/ERI/ECP): son motores
-- independientes con propósitos distintos.
-- -----------------------------------------------------
BEGIN;

-- 1. CLASIFICACIÓN DE CUENTAS (Nivel 1): una fila por cuenta clasificada.
--    Cuentas de clase 1/2 sin fila aquí se muestran como "sin clasificar"
--    en la pantalla de configuración del módulo.
CREATE TABLE IF NOT EXISTS indices_financieros_grupo_cuentas (
    id SERIAL PRIMARY KEY,
    id_empresa INTEGER NOT NULL,
    id_cuenta INTEGER NOT NULL,

    grupo VARCHAR(30) NOT NULL, -- ACTIVO_CORRIENTE | ACTIVO_NO_CORRIENTE | PASIVO_CORRIENTE | PASIVO_NO_CORRIENTE

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_by INTEGER,
    eliminado BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at TIMESTAMP,
    deleted_by INTEGER,

    CONSTRAINT fk_ifgc_empresa FOREIGN KEY (id_empresa) REFERENCES empresas(id),
    CONSTRAINT fk_ifgc_cuenta FOREIGN KEY (id_cuenta) REFERENCES plan_cuentas(id),
    CONSTRAINT chk_ifgc_grupo CHECK (grupo IN ('ACTIVO_CORRIENTE', 'ACTIVO_NO_CORRIENTE', 'PASIVO_CORRIENTE', 'PASIVO_NO_CORRIENTE'))
);

CREATE UNIQUE INDEX IF NOT EXISTS uk_ifgc_cuenta
    ON indices_financieros_grupo_cuentas (id_empresa, id_cuenta) WHERE eliminado = FALSE;
CREATE INDEX IF NOT EXISTS idx_ifgc_empresa
    ON indices_financieros_grupo_cuentas (id_empresa) WHERE eliminado = FALSE;

-- 2. GRUPOS PERSONALIZADOS (Nivel 2): agrupaciones ad-hoc de cuentas que el
--    usuario arma para sus propios índices (distintos de los 4 fijos de arriba).
CREATE TABLE IF NOT EXISTS indices_financieros_grupos (
    id SERIAL PRIMARY KEY,
    id_empresa INTEGER NOT NULL,

    codigo VARCHAR(50) NOT NULL, -- slug interno, referenciado desde formula como {"grupo":"codigo"}
    nombre VARCHAR(150) NOT NULL,
    descripcion VARCHAR(255),

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_by INTEGER,
    eliminado BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at TIMESTAMP,
    deleted_by INTEGER,

    CONSTRAINT fk_ifg_empresa FOREIGN KEY (id_empresa) REFERENCES empresas(id)
);

CREATE UNIQUE INDEX IF NOT EXISTS uk_ifg_codigo
    ON indices_financieros_grupos (id_empresa, codigo) WHERE eliminado = FALSE;
CREATE INDEX IF NOT EXISTS idx_ifg_empresa
    ON indices_financieros_grupos (id_empresa) WHERE eliminado = FALSE;

-- 3. DETALLE DE GRUPO PERSONALIZADO: cuentas que integran un grupo (N:M).
CREATE TABLE IF NOT EXISTS indices_financieros_grupo_detalle (
    id SERIAL PRIMARY KEY,
    id_empresa INTEGER NOT NULL,
    id_grupo INTEGER NOT NULL,
    id_cuenta INTEGER NOT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_by INTEGER,
    eliminado BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at TIMESTAMP,
    deleted_by INTEGER,

    CONSTRAINT fk_ifgd_empresa FOREIGN KEY (id_empresa) REFERENCES empresas(id),
    CONSTRAINT fk_ifgd_grupo FOREIGN KEY (id_grupo) REFERENCES indices_financieros_grupos(id),
    CONSTRAINT fk_ifgd_cuenta FOREIGN KEY (id_cuenta) REFERENCES plan_cuentas(id)
);

CREATE UNIQUE INDEX IF NOT EXISTS uk_ifgd_grupo_cuenta
    ON indices_financieros_grupo_detalle (id_grupo, id_cuenta) WHERE eliminado = FALSE;
CREATE INDEX IF NOT EXISTS idx_ifgd_empresa
    ON indices_financieros_grupo_detalle (id_empresa) WHERE eliminado = FALSE;

-- 4. CATÁLOGO DE ÍNDICES (estándar sembrados + personalizados del usuario).
--    formula: árbol binario JSON, ej. {"op":"/","left":{"grupo":"ACTIVO_CORRIENTE"},"right":{"grupo":"PASIVO_CORRIENTE"}}
--    Hojas soportadas: {"grupo":"SLUG"} | {"fuente":"CXC_SALDO|CXP_SALDO|INVENTARIO_VALOR|ACTIVO_FIJO_NETO|VENTAS|COMPRAS|ACTIVO_TOTAL|PASIVO_TOTAL|PATRIMONIO|INGRESOS|COSTOS|GASTOS|UTILIDAD_NETA"} | {"const": numero}
CREATE TABLE IF NOT EXISTS indices_financieros_indices (
    id SERIAL PRIMARY KEY,
    id_empresa INTEGER NOT NULL,

    codigo VARCHAR(50) NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    categoria VARCHAR(20) NOT NULL, -- liquidez | endeudamiento | rentabilidad | actividad
    tipo VARCHAR(20) NOT NULL DEFAULT 'personalizado', -- estandar | personalizado
    unidad VARCHAR(20) NOT NULL DEFAULT 'razon', -- razon | porcentaje | dias | monto
    formula JSONB NOT NULL,
    descripcion TEXT,
    orden INTEGER NOT NULL DEFAULT 0,
    activo BOOLEAN NOT NULL DEFAULT TRUE,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_by INTEGER,
    eliminado BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at TIMESTAMP,
    deleted_by INTEGER,

    CONSTRAINT fk_ifi_empresa FOREIGN KEY (id_empresa) REFERENCES empresas(id),
    CONSTRAINT chk_ifi_categoria CHECK (categoria IN ('liquidez', 'endeudamiento', 'rentabilidad', 'actividad')),
    CONSTRAINT chk_ifi_tipo CHECK (tipo IN ('estandar', 'personalizado')),
    CONSTRAINT chk_ifi_unidad CHECK (unidad IN ('razon', 'porcentaje', 'dias', 'monto'))
);

CREATE UNIQUE INDEX IF NOT EXISTS uk_ifi_codigo
    ON indices_financieros_indices (id_empresa, codigo) WHERE eliminado = FALSE;
CREATE INDEX IF NOT EXISTS idx_ifi_empresa
    ON indices_financieros_indices (id_empresa, categoria) WHERE eliminado = FALSE;

COMMIT;

-- ─────────────────────────────────────────────────────────────────────────
-- PENDIENTE MANUAL (no lo ejecuta esta migración, según flujo del proyecto):
--   1. Crear el submódulo "Índices Financieros" (ruta 'modulos/indices-financieros')
--      en submodulos_menu, bajo el módulo padre contable correspondiente.
--   2. Asignar permisos en /config/permisos-modulos.
--   3. Actualizar config/modulos_mvc.php con el id_submodulo real.
-- ─────────────────────────────────────────────────────────────────────────
