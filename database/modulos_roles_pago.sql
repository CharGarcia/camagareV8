-- ============================================================================
-- Módulo Roles de Pago (Nómina) — corridas semanal / quincena / mensual
-- ----------------------------------------------------------------------------
-- Un módulo unificado con tipo_rol (SEMANAL/QUINCENA/MENSUAL). Operativa
-- multiempresa, eliminación lógica y auditoría. SIN tipo_ambiente (nómina
-- interna, no comprobante SRI). El mensual netea lo pagado en semanas/quincenas.
-- ============================================================================

-- 1. Cabecera de la corrida (una por empresa+tipo+período)
CREATE TABLE IF NOT EXISTS rol_cabecera (
    id                    SERIAL PRIMARY KEY,
    id_empresa            INTEGER NOT NULL,
    tipo_rol              VARCHAR(10) NOT NULL,        -- MENSUAL / QUINCENA / SEMANAL
    periodo_anio          SMALLINT NOT NULL,
    periodo_mes           SMALLINT NOT NULL,
    numero_periodo        SMALLINT NOT NULL DEFAULT 0, -- semana (1-5) / quincena (1-2) / 0 mensual
    fecha_desde           DATE,
    fecha_hasta           DATE,
    fecha_pago            DATE,
    descripcion           VARCHAR(150),
    estado                VARCHAR(20) NOT NULL DEFAULT 'borrador', -- borrador/generado/pagado/contabilizado/anulado
    total_ingresos        NUMERIC(14,2) NOT NULL DEFAULT 0,
    total_egresos         NUMERIC(14,2) NOT NULL DEFAULT 0,
    total_neto            NUMERIC(14,2) NOT NULL DEFAULT 0,
    total_aporte_patronal NUMERIC(14,2) NOT NULL DEFAULT 0,
    id_asiento            INTEGER,
    eliminado             BOOLEAN NOT NULL DEFAULT false,
    created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by            INTEGER,
    updated_by            INTEGER,
    deleted_at            TIMESTAMP,
    deleted_by            INTEGER
);

-- Evita dos corridas iguales (respeta eliminación lógica).
CREATE UNIQUE INDEX IF NOT EXISTS uk_rol_cabecera_periodo
    ON rol_cabecera (id_empresa, tipo_rol, periodo_anio, periodo_mes, numero_periodo)
    WHERE eliminado = false;
CREATE INDEX IF NOT EXISTS idx_rol_cabecera_empresa ON rol_cabecera (id_empresa) WHERE eliminado = false;

-- 2. Detalle: una línea por empleado en la corrida (totales por empleado)
CREATE TABLE IF NOT EXISTS rol_detalle (
    id               SERIAL PRIMARY KEY,
    id_rol           INTEGER NOT NULL REFERENCES rol_cabecera(id) ON DELETE CASCADE,
    id_empresa       INTEGER NOT NULL,
    id_empleado      INTEGER NOT NULL,
    dias_trabajados  NUMERIC(6,2) NOT NULL DEFAULT 30,
    sueldo_base      NUMERIC(14,2) NOT NULL DEFAULT 0,
    total_ingresos   NUMERIC(14,2) NOT NULL DEFAULT 0,
    total_egresos    NUMERIC(14,2) NOT NULL DEFAULT 0,
    aporte_iess      NUMERIC(14,2) NOT NULL DEFAULT 0, -- personal (incluido en egresos)
    aporte_patronal  NUMERIC(14,2) NOT NULL DEFAULT 0, -- informativo (asiento)
    neto             NUMERIC(14,2) NOT NULL DEFAULT 0,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_rol_detalle_rol ON rol_detalle (id_rol);
CREATE INDEX IF NOT EXISTS idx_rol_detalle_empleado ON rol_detalle (id_empresa, id_empleado);

-- 3. Desglose auditable: conceptos (ingresos/egresos) por línea de empleado
CREATE TABLE IF NOT EXISTS rol_detalle_rubro (
    id           SERIAL PRIMARY KEY,
    id_detalle   INTEGER NOT NULL REFERENCES rol_detalle(id) ON DELETE CASCADE,
    id_empresa   INTEGER NOT NULL,
    tipo         VARCHAR(10) NOT NULL,   -- ingreso / egreso
    concepto     VARCHAR(120) NOT NULL,
    codigo       VARCHAR(10),            -- tipo_codigo de novedad, etc.
    origen       VARCHAR(20) NOT NULL DEFAULT 'novedad', -- sueldo/rubro_fijo/novedad/iess/fondos/decimo/neteo
    valor        NUMERIC(14,2) NOT NULL DEFAULT 0,
    aporta_iess  BOOLEAN NOT NULL DEFAULT false,
    id_novedad   INTEGER,                -- trazabilidad a novedades
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_rol_detalle_rubro_det ON rol_detalle_rubro (id_detalle);

-- Menú: reutilizar el submódulo "Roles de pagos" (id 172) del módulo Nómina (313),
-- apuntándolo a la ruta MVC del nuevo módulo unificado.
UPDATE submodulos_menu
   SET ruta = 'modulos/roles-pago', status = 1
 WHERE id = 172;
