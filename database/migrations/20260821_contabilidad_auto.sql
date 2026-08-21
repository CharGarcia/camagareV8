-- ============================================================================
-- Generación automática de asientos contables por módulo
--
-- Al abrir un módulo operativo (Facturas de Venta, Compras, Ingresos…), el
-- sistema revisa en segundo plano si esa empresa tiene configuración contable
-- para ese módulo y genera los asientos que falten. Es SILENCIOSO: no muestra
-- mensajes, no bloquea la pantalla y no interrumpe al usuario.
--
-- Ver: app/Services/modulos/ContabilidadAutoService.php
--      config/contabilidad_modulos.php   (qué módulo genera qué)
--
-- Estas dos tablas existen para que ese automatismo no se vuelva un problema de
-- rendimiento en un servidor pequeño:
--
--   * contabilidad_auto_estado  → cuándo corrió por última vez cada módulo, para
--     no repetir la pasada en cada recarga de página (throttle).
--   * contabilidad_auto_fallos  → qué documentos NO se pudieron contabilizar y
--     por qué. Sin esto, un documento sin cuenta configurada (o con el período
--     cerrado) se reintentaría en cada carga de página, para siempre. Un fallo
--     se reintenta SOLO cuando cambia la configuración contable del módulo, lo
--     que se detecta comparando `hash_config`.
--
-- Idempotente: se puede correr varias veces.
-- ============================================================================

-- 1) Estado por empresa + módulo ---------------------------------------------
CREATE TABLE IF NOT EXISTS contabilidad_auto_estado (
    id                  SERIAL PRIMARY KEY,
    id_empresa          INTEGER      NOT NULL,
    modulo_clave        VARCHAR(60)  NOT NULL,   -- clave del trabajo: 'facturas_venta', 'compras'…

    ultima_corrida      TIMESTAMP    NULL,       -- cuándo terminó la última pasada
    ultimo_hash_config  VARCHAR(64)  NULL,       -- firma de la configuración contable en esa pasada
    generados           INTEGER      NOT NULL DEFAULT 0,  -- asientos creados en la última pasada
    fallidos            INTEGER      NOT NULL DEFAULT 0,  -- documentos que no se pudieron contabilizar
    quedan_pendientes   BOOLEAN      NOT NULL DEFAULT FALSE, -- se alcanzó el tope: falta trabajo

    created_at          TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMP    NULL,
    created_by          INTEGER      NULL,
    updated_by          INTEGER      NULL,
    eliminado           BOOLEAN      NOT NULL DEFAULT FALSE,
    deleted_at          TIMESTAMP    NULL,
    deleted_by          INTEGER      NULL
);

-- Una sola fila por empresa+módulo (el servicio hace UPSERT sobre esta clave).
CREATE UNIQUE INDEX IF NOT EXISTS ux_contab_auto_estado_empresa_modulo
    ON contabilidad_auto_estado (id_empresa, modulo_clave);

-- 2) Documentos que fallaron --------------------------------------------------
CREATE TABLE IF NOT EXISTS contabilidad_auto_fallos (
    id                  SERIAL PRIMARY KEY,
    id_empresa          INTEGER      NOT NULL,
    modulo_clave        VARCHAR(60)  NOT NULL,
    id_documento        INTEGER      NOT NULL,   -- id en la tabla de cabecera del módulo

    motivo              TEXT         NULL,       -- mensaje real de la excepción (o "sin asiento")
    intentos            INTEGER      NOT NULL DEFAULT 1,
    ultimo_intento      TIMESTAMP    NOT NULL DEFAULT NOW(),
    hash_config         VARCHAR(64)  NULL,       -- configuración vigente cuando falló

    created_at          TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMP    NULL,
    created_by          INTEGER      NULL,
    updated_by          INTEGER      NULL,
    eliminado           BOOLEAN      NOT NULL DEFAULT FALSE,
    deleted_at          TIMESTAMP    NULL,
    deleted_by          INTEGER      NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS ux_contab_auto_fallos_doc
    ON contabilidad_auto_fallos (id_empresa, modulo_clave, id_documento);

-- Lectura habitual: "dame los ids a excluir de este módulo cuyo hash sigue igual".
CREATE INDEX IF NOT EXISTS ix_contab_auto_fallos_modulo
    ON contabilidad_auto_fallos (id_empresa, modulo_clave, eliminado);
