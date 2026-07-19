-- MIGRATION: Módulo Activos Fijos
-- Alta de activos (desde línea de factura de compra o manual) + depreciación
-- en línea recta contabilizada en lote mensual (ver AsientoBuilderService::
-- generarAsientoAltaActivoFijo / generarAsientoDepreciacionLote).
-- -----------------------------------------------------
BEGIN;

-- 1. CATEGORÍAS (catálogo operativo por empresa: % de depreciación anual y
--    las 3 cuentas contables — activo, depreciación acumulada, gasto).
CREATE TABLE IF NOT EXISTS activos_fijos_categorias (
    id SERIAL PRIMARY KEY,
    id_empresa INTEGER NOT NULL,

    nombre VARCHAR(150) NOT NULL,
    porcentaje_depreciacion_anual NUMERIC(5,2) NOT NULL DEFAULT 0,
    id_cuenta_activo INTEGER NOT NULL,
    id_cuenta_depreciacion_acumulada INTEGER NOT NULL,
    id_cuenta_gasto_depreciacion INTEGER NOT NULL,
    estado BOOLEAN NOT NULL DEFAULT TRUE,
    observaciones VARCHAR(255),

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_by INTEGER,
    eliminado BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at TIMESTAMP,
    deleted_by INTEGER,

    CONSTRAINT fk_afc_empresa FOREIGN KEY (id_empresa) REFERENCES empresas(id),
    CONSTRAINT fk_afc_cuenta_activo FOREIGN KEY (id_cuenta_activo) REFERENCES plan_cuentas(id),
    CONSTRAINT fk_afc_cuenta_dep_acum FOREIGN KEY (id_cuenta_depreciacion_acumulada) REFERENCES plan_cuentas(id),
    CONSTRAINT fk_afc_cuenta_gasto FOREIGN KEY (id_cuenta_gasto_depreciacion) REFERENCES plan_cuentas(id),
    CONSTRAINT chk_afc_porcentaje CHECK (porcentaje_depreciacion_anual >= 0 AND porcentaje_depreciacion_anual <= 100)
);

CREATE UNIQUE INDEX IF NOT EXISTS uk_afc_nombre
    ON activos_fijos_categorias (id_empresa, nombre) WHERE eliminado = FALSE;
CREATE INDEX IF NOT EXISTS idx_afc_empresa
    ON activos_fijos_categorias (id_empresa) WHERE eliminado = FALSE;

-- 2. ACTIVOS (cabecera del activo fijo: origen compra/manual, valores y
--    estado de depreciación denormalizado para lectura rápida).
CREATE TABLE IF NOT EXISTS activos_fijos (
    id SERIAL PRIMARY KEY,
    id_empresa INTEGER NOT NULL,
    id_categoria INTEGER NOT NULL,

    codigo VARCHAR(30),
    nombre VARCHAR(200) NOT NULL,
    descripcion TEXT,

    origen VARCHAR(10) NOT NULL DEFAULT 'manual', -- 'compra' | 'manual'
    id_compra INTEGER,
    id_compra_detalle INTEGER,
    id_proveedor INTEGER,
    proveedor_texto VARCHAR(200), -- solo cuando origen='manual' y no hay id_proveedor

    fecha_adquisicion DATE NOT NULL,
    fecha_inicio_depreciacion DATE NOT NULL,

    valor_adquisicion NUMERIC(14,2) NOT NULL,
    valor_residual NUMERIC(14,2) NOT NULL DEFAULT 0,
    porcentaje_depreciacion_anual NUMERIC(5,2) NOT NULL, -- congelado desde la categoría al momento del alta
    valor_depreciable NUMERIC(14,2) NOT NULL,             -- valor_adquisicion - valor_residual
    meses_vida_util INTEGER NOT NULL DEFAULT 0,

    depreciacion_acumulada NUMERIC(14,2) NOT NULL DEFAULT 0,
    valor_en_libros NUMERIC(14,2) NOT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'activo', -- activo | depreciado_total

    id_cuenta_contrapartida_alta INTEGER, -- override opcional (solo origen=manual)
    id_asiento_alta INTEGER,              -- referencia lógica a asientos_contables_cabecera (solo origen=manual)

    observaciones TEXT,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_by INTEGER,
    eliminado BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at TIMESTAMP,
    deleted_by INTEGER,

    CONSTRAINT fk_af_empresa FOREIGN KEY (id_empresa) REFERENCES empresas(id),
    CONSTRAINT fk_af_categoria FOREIGN KEY (id_categoria) REFERENCES activos_fijos_categorias(id),
    CONSTRAINT fk_af_compra FOREIGN KEY (id_compra) REFERENCES compras_cabecera(id),
    CONSTRAINT fk_af_compra_detalle FOREIGN KEY (id_compra_detalle) REFERENCES compras_detalle(id),
    CONSTRAINT fk_af_proveedor FOREIGN KEY (id_proveedor) REFERENCES proveedores(id),
    CONSTRAINT fk_af_cuenta_contrapartida FOREIGN KEY (id_cuenta_contrapartida_alta) REFERENCES plan_cuentas(id),
    CONSTRAINT chk_af_origen CHECK (
        (origen = 'compra' AND id_compra_detalle IS NOT NULL) OR
        (origen = 'manual' AND id_compra_detalle IS NULL)
    ),
    CONSTRAINT chk_af_residual CHECK (valor_residual >= 0 AND valor_residual <= valor_adquisicion)
);

CREATE INDEX IF NOT EXISTS idx_af_empresa   ON activos_fijos (id_empresa) WHERE eliminado = FALSE;
CREATE INDEX IF NOT EXISTS idx_af_categoria ON activos_fijos (id_categoria) WHERE eliminado = FALSE;
CREATE INDEX IF NOT EXISTS idx_af_estado    ON activos_fijos (id_empresa, estado) WHERE eliminado = FALSE;
-- Una línea de factura de compra solo puede convertirse en UN activo fijo.
CREATE UNIQUE INDEX IF NOT EXISTS uk_af_compra_detalle
    ON activos_fijos (id_compra_detalle) WHERE eliminado = FALSE AND id_compra_detalle IS NOT NULL;

-- 3. LOTES (cabecera de cada corrida mensual de depreciación — control de
--    idempotencia: no se puede generar dos veces el mismo período/empresa).
CREATE TABLE IF NOT EXISTS activos_fijos_lotes (
    id SERIAL PRIMARY KEY,
    id_empresa INTEGER NOT NULL,

    periodo_anio SMALLINT NOT NULL,
    periodo_mes SMALLINT NOT NULL,
    fecha_generacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    cantidad_activos INTEGER NOT NULL DEFAULT 0,
    total_depreciado NUMERIC(14,2) NOT NULL DEFAULT 0,
    estado VARCHAR(15) NOT NULL DEFAULT 'contabilizado', -- contabilizado | anulado
    id_asiento_contable INTEGER, -- referencia lógica a asientos_contables_cabecera

    observaciones TEXT,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_by INTEGER,
    eliminado BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at TIMESTAMP,
    deleted_by INTEGER,

    CONSTRAINT fk_afl_empresa FOREIGN KEY (id_empresa) REFERENCES empresas(id),
    CONSTRAINT chk_afl_mes CHECK (periodo_mes BETWEEN 1 AND 12)
);

CREATE UNIQUE INDEX IF NOT EXISTS uk_afl_periodo
    ON activos_fijos_lotes (id_empresa, periodo_anio, periodo_mes) WHERE eliminado = FALSE;
CREATE INDEX IF NOT EXISTS idx_afl_empresa ON activos_fijos_lotes (id_empresa) WHERE eliminado = FALSE;

-- 4. DEPRECIACIONES (historial mensual por activo, detalle del lote).
CREATE TABLE IF NOT EXISTS activos_fijos_depreciaciones (
    id SERIAL PRIMARY KEY,
    id_empresa INTEGER NOT NULL,
    id_activo INTEGER NOT NULL,
    id_lote INTEGER NOT NULL,

    periodo_anio SMALLINT NOT NULL,
    periodo_mes SMALLINT NOT NULL,
    valor_depreciado NUMERIC(14,2) NOT NULL,
    depreciacion_acumulada_after NUMERIC(14,2) NOT NULL,
    valor_libros_after NUMERIC(14,2) NOT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_by INTEGER,
    eliminado BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at TIMESTAMP,
    deleted_by INTEGER,

    CONSTRAINT fk_afd_empresa FOREIGN KEY (id_empresa) REFERENCES empresas(id),
    CONSTRAINT fk_afd_activo FOREIGN KEY (id_activo) REFERENCES activos_fijos(id),
    CONSTRAINT fk_afd_lote FOREIGN KEY (id_lote) REFERENCES activos_fijos_lotes(id) ON DELETE CASCADE
);

-- Un activo no puede depreciarse dos veces en el mismo período.
CREATE UNIQUE INDEX IF NOT EXISTS uk_afd_periodo
    ON activos_fijos_depreciaciones (id_activo, periodo_anio, periodo_mes) WHERE eliminado = FALSE;
CREATE INDEX IF NOT EXISTS idx_afd_lote   ON activos_fijos_depreciaciones (id_lote);
CREATE INDEX IF NOT EXISTS idx_afd_activo ON activos_fijos_depreciaciones (id_activo);

COMMIT;

-- ─────────────────────────────────────────────────────────────────────────
-- PENDIENTE MANUAL (no lo ejecuta esta migración, según flujo del proyecto):
--   1. Crear los submódulos "Activos Fijos" (ruta 'modulos/activos-fijos') y
--      "Categorías de Activos Fijos" (ruta 'modulos/activos-fijos-categorias')
--      en submodulos_menu, bajo el módulo padre contable correspondiente.
--   2. Asignar permisos en /config/permisos-modulos.
--   3. Actualizar config/modulos_mvc.php con los id_submodulo reales.
-- ─────────────────────────────────────────────────────────────────────────
