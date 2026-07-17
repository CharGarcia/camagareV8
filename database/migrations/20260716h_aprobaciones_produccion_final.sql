-- ============================================================
-- Aprobaciones — script CONSOLIDADO para producción (idempotente)
-- ============================================================
-- Reemplaza a los migrations incrementales 20260716*.sql de esta sesión.
-- Seguro de correr sin importar el estado actual de producción (desde cero,
-- o con cualquier parte de la bandeja ya desplegada): al terminar quedan
-- SOLO aprobaciones_tipos y aprobaciones_config (configuración), con el
-- único checkpoint 'aprobacion_compras'. La bandeja de solicitudes
-- (modulos/aprobaciones) se retiró — no vuelve a crearse.
-- ============================================================

-- 1) Quitar la bandeja de solicitudes, si llegó a crearse.
DROP TABLE IF EXISTS aprobaciones_solicitudes;

-- 2) Catálogo de tipos (checkpoints).
CREATE TABLE IF NOT EXISTS aprobaciones_tipos (
    id            SERIAL PRIMARY KEY,
    codigo        VARCHAR(60)  NOT NULL UNIQUE,
    nombre        VARCHAR(150) NOT NULL,
    descripcion   TEXT,
    modulo_ruta   VARCHAR(100),
    activo        BOOLEAN NOT NULL DEFAULT true,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 3) Configuración por empresa (sin notificar_correo: se retiró).
CREATE TABLE IF NOT EXISTS aprobaciones_config (
    id                    SERIAL PRIMARY KEY,
    id_empresa            INTEGER NOT NULL REFERENCES empresas(id),
    id_tipo               INTEGER NOT NULL REFERENCES aprobaciones_tipos(id),
    requiere_aprobacion   BOOLEAN NOT NULL DEFAULT false,
    usuarios_aprobadores  JSONB   NOT NULL DEFAULT '[]'::jsonb,
    umbral_monto          NUMERIC(14,2),
    created_by            INTEGER,
    updated_by            INTEGER,
    created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (id_empresa, id_tipo)
);

-- Por si la tabla ya existía de una corrida anterior con la columna vieja.
ALTER TABLE aprobaciones_config DROP COLUMN IF EXISTS notificar_correo;

CREATE INDEX IF NOT EXISTS idx_aprob_config_empresa ON aprobaciones_config (id_empresa);

-- 4) Catálogo final: solo 'aprobacion_compras' (quita checkpoints viejos
--    de versiones intermedias de esta sesión, si llegaron a crearse).
DELETE FROM aprobaciones_config
WHERE id_tipo IN (SELECT id FROM aprobaciones_tipos WHERE codigo IN ('registro_compras', 'pago_compras'));

DELETE FROM aprobaciones_tipos WHERE codigo IN ('registro_compras', 'pago_compras');

INSERT INTO aprobaciones_tipos (codigo, nombre, descripcion, modulo_ruta)
VALUES (
    'aprobacion_compras',
    'Aprobación de compras',
    'Revisión y aprobación de las compras ya registradas en el sistema.',
    'modulos/compras'
)
ON CONFLICT (codigo) DO NOTHING;
