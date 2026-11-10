-- Firmas electrónicas: un enlace público (y su QR) por cada vendedor, para saber qué
-- negocio generó cada solicitud y pagarle su comisión.
-- Idempotente. Aplicar ANTES de desplegar el código (sin esto el módulo sigue
-- funcionando, pero sin enlaces por vendedor ni la columna Vendedor).

-- 1. Enlace fijo de cada vendedor (uno activo por vendedor).
CREATE TABLE IF NOT EXISTS firmas_enlaces_vendedor (
    id           SERIAL PRIMARY KEY,
    id_empresa   INTEGER NOT NULL,
    id_vendedor  INTEGER NOT NULL,
    codigo       CHAR(32) NOT NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP,
    created_by   INTEGER,
    updated_by   INTEGER,
    eliminado    BOOLEAN NOT NULL DEFAULT false,
    deleted_at   TIMESTAMP,
    deleted_by   INTEGER
);

-- El código identifica al enlace para siempre: uno eliminado no se reutiliza.
CREATE UNIQUE INDEX IF NOT EXISTS ux_firmas_enlaces_vendedor_codigo
    ON firmas_enlaces_vendedor (codigo);

CREATE UNIQUE INDEX IF NOT EXISTS ux_firmas_enlaces_vendedor_activo
    ON firmas_enlaces_vendedor (id_empresa, id_vendedor) WHERE eliminado = false;

-- 2. Vendedor de cada enlace enviado / solicitud.
ALTER TABLE firma_solicitudes ADD COLUMN IF NOT EXISTS id_vendedor INTEGER;

-- 3. Vendedor de cada firma (se copia de la solicitud; va a la factura).
ALTER TABLE firmas_electronicas ADD COLUMN IF NOT EXISTS id_vendedor INTEGER;

CREATE INDEX IF NOT EXISTS ix_firmas_electronicas_vendedor
    ON firmas_electronicas (id_empresa, id_vendedor) WHERE eliminado = false AND id_vendedor IS NOT NULL;
