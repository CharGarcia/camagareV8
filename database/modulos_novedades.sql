-- ============================================================================
-- Módulo Novedades (Nómina) — registro de novedades por empleado
-- ----------------------------------------------------------------------------
-- Operativa (multiempresa), eliminación lógica y auditoría estándar.
-- NO lleva tipo_ambiente (es registro interno de nómina, no comprobante SRI).
-- Los catálogos de tipos y motivos de salida son fijos en código
-- (App\models\CatalogoNovedades).
-- ============================================================================

CREATE TABLE IF NOT EXISTS novedades (
    id             SERIAL PRIMARY KEY,
    id_empresa     INTEGER NOT NULL,
    id_empleado    INTEGER NOT NULL,
    tipo_codigo    VARCHAR(5)  NOT NULL,          -- 1..10, 14 (catálogo en código)
    tipo_nombre    VARCHAR(60) NOT NULL,          -- nombre del tipo (denormalizado)
    fecha          DATE        NOT NULL,          -- fecha de la novedad
    periodo_mes    SMALLINT    NOT NULL,          -- 1..12 (rol de pagos)
    periodo_anio   SMALLINT    NOT NULL,          -- año del rol
    valor          NUMERIC(14,2) NOT NULL DEFAULT 0, -- monto / horas / días según tipo
    motivo_codigo  VARCHAR(2),                    -- solo Aviso de salida (T,V,B,R,S,D,I,F,A)
    motivo_nombre  VARCHAR(120),
    observacion    TEXT,
    estado         VARCHAR(20) NOT NULL DEFAULT 'activo', -- activo / anulado
    eliminado      BOOLEAN     NOT NULL DEFAULT false,
    created_at     TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    created_by     INTEGER,
    updated_by     INTEGER,
    deleted_at     TIMESTAMP,
    deleted_by     INTEGER
);

CREATE INDEX IF NOT EXISTS idx_novedades_empresa  ON novedades (id_empresa) WHERE eliminado = false;
CREATE INDEX IF NOT EXISTS idx_novedades_empleado ON novedades (id_empleado);
CREATE INDEX IF NOT EXISTS idx_novedades_periodo  ON novedades (id_empresa, periodo_anio, periodo_mes);

-- Menú: reutilizar el submódulo "Novedades" (id 170) del módulo Nómina (313),
-- apuntándolo a la ruta MVC del nuevo módulo.
UPDATE submodulos_menu
   SET ruta = 'modulos/novedades', status = 1
 WHERE id = 170;
