-- =============================================================================
-- Métricas del sistema (Fase C) — resumen diario precalculado
-- -----------------------------------------------------------------------------
-- Qué hace:
--   Crea `metricas_diarias`: una fila por (día, empresa) con los conteos de ese día,
--   más una fila GLOBAL por día con id_empresa = 0 (totales de la plataforma, inicios
--   de sesión, errores, SRI y fotos del momento: empresas activas, usuarios habilitados).
--   La llena el cron (app/cron/cron_runner.php, una vez al día después de la 01:00,
--   recalculando los últimos 3 días). El historial se rellena con:
--     php app/cron/metricas_rellenar.php 2024-01-01 2026-11-04
-- Tipo de tabla: datos DERIVADOS (caché de cálculo), no registros de negocio. Recalcular
--   un día reemplaza sus filas: nunca se edita a mano y se puede reconstruir siempre
--   desde las tablas de origen. id_empresa = 0 es la fila global (no es una empresa).
-- Toca datos: no. Solo crea la tabla y su índice.
-- Idempotente: sí (IF NOT EXISTS).
-- Orden de despliegue: aplicar ANTES del código. Sin la tabla, el paso del cron se
--   salta y la pestaña Tendencias avisa que falta este SQL.
-- Revertir:
--   DROP TABLE IF EXISTS metricas_diarias;
-- =============================================================================

CREATE TABLE IF NOT EXISTS metricas_diarias (
    fecha                 DATE          NOT NULL,
    id_empresa            INTEGER       NOT NULL,          -- 0 = fila global de la plataforma
    -- Documentos de producción (autorizados / no anulados) emitidos ese día
    facturas              INTEGER       NOT NULL DEFAULT 0,
    notas_credito         INTEGER       NOT NULL DEFAULT 0,
    notas_debito          INTEGER       NOT NULL DEFAULT 0,
    retenciones           INTEGER       NOT NULL DEFAULT 0,
    guias                 INTEGER       NOT NULL DEFAULT 0,
    liquidaciones         INTEGER       NOT NULL DEFAULT 0,
    reembolsos            INTEGER       NOT NULL DEFAULT 0,
    compras               INTEGER       NOT NULL DEFAULT 0,
    recibos               INTEGER       NOT NULL DEFAULT 0,
    proformas             INTEGER       NOT NULL DEFAULT 0,
    pedidos               INTEGER       NOT NULL DEFAULT 0,
    monto_ventas          NUMERIC(16,2) NOT NULL DEFAULT 0,  -- importe total de facturas
    -- Uso (desde metricas_actividad_usuario; sin superadmins)
    usuarios_activos      INTEGER       NOT NULL DEFAULT 0,
    horas_uso             NUMERIC(10,1) NOT NULL DEFAULT 0,
    -- Solo en la fila global (id_empresa = 0)
    empresas_con_docs     INTEGER,
    empresas_con_uso      INTEGER,
    logins                INTEGER,
    usuarios_login        INTEGER,
    errores               INTEGER,
    errores_fatales       INTEGER,
    sri_autorizados       INTEGER,
    sri_rechazados        INTEGER,
    empresas_activas      INTEGER,      -- foto del día en que se calculó (NULL en días rellenados)
    usuarios_habilitados  INTEGER,      -- ídem
    calculado_at          TIMESTAMP     NOT NULL DEFAULT NOW(),
    PRIMARY KEY (fecha, id_empresa)
);

COMMENT ON TABLE metricas_diarias IS
    'Resumen diario precalculado de Métricas del sistema. Una fila por día y empresa; id_empresa = 0 es el total global. Datos derivados: se recalculan desde las tablas de origen.';

CREATE INDEX IF NOT EXISTS idx_metricas_diarias_empresa_fecha
    ON metricas_diarias (id_empresa, fecha);

-- --- Comprobación ---------------------------------------------------------------
-- SELECT to_regclass('public.metricas_diarias');               -- debe devolver el nombre
-- Al día siguiente (o tras correr el relleno):
-- SELECT * FROM metricas_diarias WHERE id_empresa = 0 ORDER BY fecha DESC LIMIT 10;
