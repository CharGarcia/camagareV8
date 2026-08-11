-- MIGRATION: Módulo "Configuración de Balances Consolidados"
-- Permite mapear manualmente qué cuenta de plan_cuentas de cada establecimiento
-- (fila de `empresas`) representa el MISMO concepto contable, para que Estados
-- Financieros / Balance de Comprobación (y en el futuro otros reportes) puedan
-- mostrar un consolidado por RUC sin asumir que los códigos de cuenta coinciden
-- entre establecimientos (no coinciden de forma confiable: ver docs/manual).
--
-- Mismo patrón que indices_financieros_grupos/_grupo_detalle (grupo + detalle N:M),
-- pero cruzando id_empresa distintas del mismo RUC en vez de una sola empresa.
-- El mapeo es 100% manual — el sistema NUNCA sugiere ni auto-completa equivalencias
-- por código de cuenta (ver justificación en el manual del módulo).
-- -----------------------------------------------------
BEGIN;

-- 1. GRUPOS: un grupo = un concepto contable consolidado (ej. "Caja General").
CREATE TABLE IF NOT EXISTS consolidacion_grupos (
    id SERIAL PRIMARY KEY,
    ruc VARCHAR(20) NOT NULL,
    id_empresa_matriz INTEGER NOT NULL,

    nombre VARCHAR(150) NOT NULL,
    tipo VARCHAR(20) NOT NULL, -- ACTIVO | PASIVO | PATRIMONIO | INGRESO | COSTO | GASTO
    orden INTEGER NOT NULL DEFAULT 0,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_by INTEGER,
    eliminado BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at TIMESTAMP,
    deleted_by INTEGER,

    CONSTRAINT fk_cg_empresa_matriz FOREIGN KEY (id_empresa_matriz) REFERENCES empresas(id),
    CONSTRAINT chk_cg_tipo CHECK (tipo IN ('ACTIVO', 'PASIVO', 'PATRIMONIO', 'INGRESO', 'COSTO', 'GASTO'))
);

CREATE INDEX IF NOT EXISTS idx_cg_ruc ON consolidacion_grupos (ruc) WHERE eliminado = FALSE;

-- 2. DETALLE: qué cuenta de cada empresa integra el grupo (N:M).
--    Una empresa aporta a lo sumo UNA cuenta por grupo, y una cuenta no puede
--    estar en más de un grupo (ambigüedad: ¿a qué concepto consolidado pertenece?).
CREATE TABLE IF NOT EXISTS consolidacion_grupos_cuentas (
    id SERIAL PRIMARY KEY,
    id_grupo INTEGER NOT NULL,
    id_empresa INTEGER NOT NULL,
    id_cuenta INTEGER NOT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_by INTEGER,
    eliminado BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at TIMESTAMP,
    deleted_by INTEGER,

    CONSTRAINT fk_cgc_grupo FOREIGN KEY (id_grupo) REFERENCES consolidacion_grupos(id),
    CONSTRAINT fk_cgc_empresa FOREIGN KEY (id_empresa) REFERENCES empresas(id),
    CONSTRAINT fk_cgc_cuenta FOREIGN KEY (id_cuenta) REFERENCES plan_cuentas(id)
);

CREATE UNIQUE INDEX IF NOT EXISTS uk_cgc_grupo_empresa
    ON consolidacion_grupos_cuentas (id_grupo, id_empresa) WHERE eliminado = FALSE;
CREATE UNIQUE INDEX IF NOT EXISTS uk_cgc_cuenta
    ON consolidacion_grupos_cuentas (id_cuenta) WHERE eliminado = FALSE;
CREATE INDEX IF NOT EXISTS idx_cgc_grupo ON consolidacion_grupos_cuentas (id_grupo) WHERE eliminado = FALSE;
CREATE INDEX IF NOT EXISTS idx_cgc_empresa ON consolidacion_grupos_cuentas (id_empresa) WHERE eliminado = FALSE;

COMMIT;

-- ─────────────────────────────────────────────────────────────────────────
-- PENDIENTE MANUAL (no lo ejecuta esta migración, según flujo del proyecto):
--   1. Crear el submódulo "Balances Consolidados" (ruta 'modulos/balances-consolidados')
--      en submodulos_menu, bajo Contabilidad.
--   2. Asignar permisos en /config/permisos-modulos.
--   3. Actualizar config/modulos_mvc.php con el id_submodulo real.
-- ─────────────────────────────────────────────────────────────────────────
