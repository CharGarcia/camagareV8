-- ============================================================================
-- Resumen Diario: apertura y cierre de caja por día (y por usuario)
-- ============================================================================
-- QUÉ HACE    Crea dos tablas operativas (por empresa) que usa la opción
--             «Caja: Cierre de caja» del Resumen Diario (modulos/reporte_resumen_diario):
--             - caja_cierres_diarios: una caja por empresa + día + alcance. El alcance
--               es un usuario (id_usuario: lo que registró ese usuario) o toda la
--               empresa (id_usuario NULL). Estado abierta | cerrada.
--             - caja_cierres_diarios_detalle: una fila por forma de pago con el saldo
--               inicial (al abrir) y, al cerrar, ingresos, egresos, traslados, saldo
--               esperado, contado (arqueo) y diferencia.
--             No toca caja_saldos_apertura, caja_traslados ni caja_sesiones (POS).
--             No generan asientos contables ni bloquean Ingresos/Egresos.
-- TOCA DATOS  No. Tablas nuevas, vacías.
-- ORDEN       Aplicar ANTES de desplegar el código. Si el código llega primero, el
--             resumen sigue igual y la opción «Cierre de caja» avisa que falta este SQL.
-- REVERSIBLE  Sí:  DROP TABLE IF EXISTS caja_cierres_diarios_detalle;
--                  DROP TABLE IF EXISTS caja_cierres_diarios;
-- USO         pgAdmin → Query Tool → F5. Idempotente.
-- ============================================================================

CREATE TABLE IF NOT EXISTS caja_cierres_diarios (
    id                      SERIAL PRIMARY KEY,
    id_empresa              INTEGER       NOT NULL REFERENCES empresas(id),
    fecha                   DATE          NOT NULL,
    id_usuario              INTEGER       REFERENCES usuarios(id),   -- NULL = toda la empresa
    estado                  VARCHAR(20)   NOT NULL DEFAULT 'abierta',
    fecha_apertura          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    abierta_por             INTEGER       REFERENCES usuarios(id),
    observaciones_apertura  VARCHAR(300),
    fecha_cierre            TIMESTAMP,
    cerrada_por             INTEGER       REFERENCES usuarios(id),
    observaciones_cierre    VARCHAR(500),
    total_inicial           NUMERIC(14,2) NOT NULL DEFAULT 0,
    total_esperado          NUMERIC(14,2),
    total_contado           NUMERIC(14,2),
    total_diferencia        NUMERIC(14,2),
    created_at              TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP,
    created_by              INTEGER,
    updated_by              INTEGER,
    eliminado               BOOLEAN       NOT NULL DEFAULT false,
    deleted_at              TIMESTAMP,
    deleted_by              INTEGER,
    CONSTRAINT ck_caja_cierres_diarios_estado CHECK (estado IN ('abierta', 'cerrada'))
);

-- Una sola caja por empresa + día + alcance (usuario o toda la empresa).
CREATE UNIQUE INDEX IF NOT EXISTS ux_caja_cierres_diarios_alcance
    ON caja_cierres_diarios (id_empresa, fecha, COALESCE(id_usuario, 0)) WHERE eliminado = false;

CREATE INDEX IF NOT EXISTS ix_caja_cierres_diarios_empresa_fecha
    ON caja_cierres_diarios (id_empresa, fecha) WHERE eliminado = false;

CREATE TABLE IF NOT EXISTS caja_cierres_diarios_detalle (
    id             SERIAL PRIMARY KEY,
    id_cierre      INTEGER       NOT NULL REFERENCES caja_cierres_diarios(id),
    id_empresa     INTEGER       NOT NULL REFERENCES empresas(id),
    id_forma_pago  INTEGER       REFERENCES empresa_formas_pago(id),  -- NULL = sin forma de pago registrada
    forma          VARCHAR(150)  NOT NULL DEFAULT '',                 -- nombre al momento del cierre
    saldo_inicial  NUMERIC(14,2) NOT NULL DEFAULT 0,
    ingresos       NUMERIC(14,2),
    egresos        NUMERIC(14,2),
    traslados      NUMERIC(14,2),
    esperado       NUMERIC(14,2),
    contado        NUMERIC(14,2),
    diferencia     NUMERIC(14,2),
    created_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP,
    created_by     INTEGER,
    updated_by     INTEGER,
    eliminado      BOOLEAN       NOT NULL DEFAULT false,
    deleted_at     TIMESTAMP,
    deleted_by     INTEGER
);

CREATE INDEX IF NOT EXISTS ix_caja_cierres_diarios_detalle_cierre
    ON caja_cierres_diarios_detalle (id_cierre) WHERE eliminado = false;

-- Comprobación (deben salir 2 filas):
-- SELECT table_name FROM information_schema.tables
--  WHERE table_schema = 'public' AND table_name IN ('caja_cierres_diarios', 'caja_cierres_diarios_detalle');
