-- ============================================================================
-- Módulo Presupuestos (modulos/presupuestos).
--
-- Presupuesto de ingresos y gastos por cuenta contable y por mes, con alcance de toda la
-- empresa, un centro de costo o un proyecto; versiones (original + reformas) aprobadas con
-- acta; la ejecución se calcula desde los asientos contables (no se digita).
--
-- Tablas (todas operativas: id_empresa + auditoría + eliminación lógica):
--   presupuestos_rubros     nombres amigables que agrupan líneas en los informes
--   presupuestos            cabecera: período, alcance, estado, umbrales del semáforo
--   presupuestos_versiones  Original y Reformas; la vigente es la última aprobada
--   presupuestos_lineas     una cuenta contable (de detalle o de grupo) por línea de versión
--   presupuestos_valores    monto de la línea por mes
--
-- Idempotente. Listo para pgAdmin (F5).
-- ============================================================================

BEGIN;

CREATE TABLE IF NOT EXISTS presupuestos_rubros (
    id          SERIAL PRIMARY KEY,
    id_empresa  INTEGER      NOT NULL,
    nombre      VARCHAR(120) NOT NULL,
    orden       INTEGER      NOT NULL DEFAULT 0,
    estado      VARCHAR(10)  NOT NULL DEFAULT 'activo',
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN   NOT NULL DEFAULT false,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER,
    CONSTRAINT chk_presup_rubros_estado CHECK (estado IN ('activo', 'inactivo'))
);
CREATE INDEX IF NOT EXISTS idx_presup_rubros_empresa ON presupuestos_rubros (id_empresa) WHERE eliminado = false;

CREATE TABLE IF NOT EXISTS presupuestos (
    id               SERIAL PRIMARY KEY,
    id_empresa       INTEGER       NOT NULL,
    nombre           VARCHAR(150)  NOT NULL,
    tipo_periodo     VARCHAR(15)   NOT NULL DEFAULT 'anual',   -- anual | mensual | personalizado
    periodo_desde    DATE          NOT NULL,                   -- primer día del primer mes
    periodo_hasta    DATE          NOT NULL,                   -- primer día del último mes
    alcance          VARCHAR(15)   NOT NULL DEFAULT 'empresa', -- empresa | centro_costo | proyecto
    id_centro_costo  INTEGER,
    id_proyecto      INTEGER,
    estado           VARCHAR(10)   NOT NULL DEFAULT 'borrador', -- borrador | aprobado | cerrado
    base_alicuotas   BOOLEAN       NOT NULL DEFAULT false,     -- reservado para Condominios
    umbral_amarillo  NUMERIC(5,2)  NOT NULL DEFAULT 90,        -- % de ejecución desde el que se avisa
    umbral_rojo      NUMERIC(5,2)  NOT NULL DEFAULT 100,       -- % de ejecución desde el que es exceso
    observaciones    TEXT,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN   NOT NULL DEFAULT false,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER,
    CONSTRAINT chk_presup_tipo_periodo CHECK (tipo_periodo IN ('anual', 'mensual', 'personalizado')),
    CONSTRAINT chk_presup_alcance      CHECK (alcance IN ('empresa', 'centro_costo', 'proyecto')),
    CONSTRAINT chk_presup_estado       CHECK (estado IN ('borrador', 'aprobado', 'cerrado')),
    CONSTRAINT chk_presup_periodo      CHECK (periodo_desde = date_trunc('month', periodo_desde)::date
                                           AND periodo_hasta = date_trunc('month', periodo_hasta)::date
                                           AND periodo_hasta >= periodo_desde),
    CONSTRAINT chk_presup_umbrales     CHECK (umbral_amarillo > 0 AND umbral_rojo >= umbral_amarillo)
);
CREATE INDEX IF NOT EXISTS idx_presup_empresa ON presupuestos (id_empresa, eliminado);
CREATE INDEX IF NOT EXISTS idx_presup_empresa_periodo ON presupuestos (id_empresa, periodo_desde, periodo_hasta) WHERE eliminado = false;

CREATE TABLE IF NOT EXISTS presupuestos_versiones (
    id                     SERIAL PRIMARY KEY,
    id_empresa             INTEGER      NOT NULL,
    id_presupuesto         INTEGER      NOT NULL REFERENCES presupuestos(id),
    numero                 INTEGER      NOT NULL,                      -- 1 = Original, 2+ = Reformas
    nombre                 VARCHAR(60)  NOT NULL,                      -- 'Original', 'Reforma 1', …
    estado                 VARCHAR(12)  NOT NULL DEFAULT 'borrador',   -- borrador | aprobada | reemplazada
    motivo                 TEXT,                                       -- por qué se reforma
    acta                   VARCHAR(120),                               -- acta / documento de aprobación
    observacion_aprobacion TEXT,
    aprobado_at            TIMESTAMP,
    aprobado_por           INTEGER,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN   NOT NULL DEFAULT false,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER,
    CONSTRAINT chk_presup_ver_estado CHECK (estado IN ('borrador', 'aprobada', 'reemplazada'))
);
CREATE INDEX IF NOT EXISTS idx_presup_ver_presupuesto ON presupuestos_versiones (id_presupuesto, numero) WHERE eliminado = false;
-- Un solo borrador vivo por presupuesto (la reforma en edición).
CREATE UNIQUE INDEX IF NOT EXISTS uq_presup_ver_borrador
    ON presupuestos_versiones (id_presupuesto) WHERE eliminado = false AND estado = 'borrador';

CREATE TABLE IF NOT EXISTS presupuestos_lineas (
    id          SERIAL PRIMARY KEY,
    id_empresa  INTEGER NOT NULL,
    id_version  INTEGER NOT NULL REFERENCES presupuestos_versiones(id),
    id_cuenta   INTEGER NOT NULL,            -- plan_cuentas.id (detalle o grupo)
    id_rubro    INTEGER,                     -- presupuestos_rubros.id (opcional)
    orden       INTEGER NOT NULL DEFAULT 0,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN   NOT NULL DEFAULT false,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER
);
CREATE INDEX IF NOT EXISTS idx_presup_lineas_version ON presupuestos_lineas (id_version) WHERE eliminado = false;
-- La misma cuenta no se presupuesta dos veces en una versión.
CREATE UNIQUE INDEX IF NOT EXISTS uq_presup_lineas_version_cuenta
    ON presupuestos_lineas (id_version, id_cuenta) WHERE eliminado = false;

CREATE TABLE IF NOT EXISTS presupuestos_valores (
    id          SERIAL PRIMARY KEY,
    id_empresa  INTEGER       NOT NULL,
    id_linea    INTEGER       NOT NULL REFERENCES presupuestos_lineas(id) ON DELETE CASCADE,
    periodo     DATE          NOT NULL,     -- primer día del mes
    monto       NUMERIC(14,2) NOT NULL DEFAULT 0,
    CONSTRAINT chk_presup_val_periodo CHECK (periodo = date_trunc('month', periodo)::date),
    CONSTRAINT uq_presup_val_linea_periodo UNIQUE (id_linea, periodo)
);
CREATE INDEX IF NOT EXISTS idx_presup_val_linea ON presupuestos_valores (id_linea);

COMMIT;

-- ── Comprobación (ejecutar aparte) ──────────────────────────────────────────
-- Deben salir 5 filas:
-- SELECT table_name FROM information_schema.tables
--  WHERE table_schema = 'public' AND table_name LIKE 'presupuestos%' ORDER BY 1;
