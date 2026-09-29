-- ============================================================================
-- Módulo Alumnos: portal de representantes (QR general del colegio).
--
-- Qué hace:
--   1) alumnos_portal: un enlace/QR por empresa (token), que se puede regenerar
--      (invalida el anterior) y activar/desactivar.
--   2) alumnos_portal_codigos: códigos de verificación de 6 dígitos que se envían
--      al correo del representante. Un código verificado habilita UN solo guardado
--      (se marca usado); para volver a actualizar hay que pedir otro.
--   3) alumnos.origen: 'sistema' (por defecto) o 'portal' (registrado por el
--      representante desde el QR).
-- No modifica ni borra datos existentes.
-- Idempotente: se puede ejecutar más de una vez.
-- Reversible:
--   DROP TABLE IF EXISTS alumnos_portal_codigos;
--   DROP TABLE IF EXISTS alumnos_portal;
--   ALTER TABLE alumnos DROP COLUMN IF EXISTS origen;
-- Orden de despliegue: ejecutar ANTES de desplegar el código. Sin este script el
--   botón «Portal de representantes» avisa que falta y el enlace público no abre.
--
-- Ejecutar en pgAdmin (Query Tool, F5) sobre la base del sistema.
-- ============================================================================

BEGIN;

ALTER TABLE alumnos ADD COLUMN IF NOT EXISTS origen VARCHAR(20) NOT NULL DEFAULT 'sistema';

CREATE TABLE IF NOT EXISTS alumnos_portal (
    id              SERIAL PRIMARY KEY,
    id_empresa      INTEGER NOT NULL,
    token           VARCHAR(64) NOT NULL,
    activo          BOOLEAN NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by      INTEGER,
    updated_by      INTEGER,
    eliminado       BOOLEAN DEFAULT FALSE,
    deleted_at      TIMESTAMP,
    deleted_by      INTEGER
);
CREATE UNIQUE INDEX IF NOT EXISTS uq_alumnos_portal_token ON alumnos_portal (token);
CREATE UNIQUE INDEX IF NOT EXISTS uq_alumnos_portal_empresa ON alumnos_portal (id_empresa) WHERE eliminado = false;

CREATE TABLE IF NOT EXISTS alumnos_portal_codigos (
    id              SERIAL PRIMARY KEY,
    id_empresa      INTEGER NOT NULL,
    identificacion  VARCHAR(20) NOT NULL,       -- cédula/RUC/pasaporte con que se identificó
    id_cliente      INTEGER,                    -- NULL = representante nuevo (registro)
    correo          VARCHAR(150) NOT NULL,      -- a dónde se envió el código
    codigo_hash     VARCHAR(255) NOT NULL,      -- password_hash del código (nunca en claro)
    expira_at       TIMESTAMP NOT NULL,
    intentos        SMALLINT NOT NULL DEFAULT 0,
    sesion          VARCHAR(64),                -- ticket de un solo uso tras verificar
    verificado_at   TIMESTAMP,
    usado_at        TIMESTAMP,                  -- se guardó: ya no sirve
    ip              VARCHAR(45),
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    eliminado       BOOLEAN DEFAULT FALSE
);
CREATE INDEX IF NOT EXISTS idx_alumnos_portal_codigos_ident ON alumnos_portal_codigos (id_empresa, identificacion, created_at);
CREATE INDEX IF NOT EXISTS idx_alumnos_portal_codigos_ip    ON alumnos_portal_codigos (ip, created_at);
CREATE UNIQUE INDEX IF NOT EXISTS uq_alumnos_portal_codigos_sesion ON alumnos_portal_codigos (sesion) WHERE sesion IS NOT NULL;

COMMIT;

-- Comprobación (debe salir 1 fila con tres valores no nulos):
-- SELECT to_regclass('public.alumnos_portal') AS portal,
--        to_regclass('public.alumnos_portal_codigos') AS codigos,
--        (SELECT column_name FROM information_schema.columns WHERE table_name = 'alumnos' AND column_name = 'origen') AS origen;
