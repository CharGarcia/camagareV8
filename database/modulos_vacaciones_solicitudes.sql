-- ============================================================================
--  Solicitudes de vacaciones  (módulo modulos/vacaciones)
--
--  Flujo: Recursos Humanos envía al empleado un enlace por correo -> el empleado
--  llena el formulario desde ese enlace (público, SIN login) -> la solicitud
--  queda pendiente -> se aprueba o se rechaza desde la pestaña Vacaciones de su
--  ficha (o desde la bandeja del módulo Vacaciones). Al aprobarla se crea la
--  vacación en `vacaciones` y queda enlazada aquí (id_vacacion).
--
--  El enlace es de UN SOLO USO y caduca (expira_at): una vez enviado el
--  formulario, token_usado = true y el enlace deja de servir.
--
--  Sin tipo_ambiente, a propósito (mismo criterio que vacaciones_periodos): la
--  solicitud es historia del empleado, no un documento electrónico, y debe
--  valer igual en pruebas y en producción.
--
--  Idempotente: se puede ejecutar varias veces sin efecto.
-- ============================================================================

CREATE TABLE IF NOT EXISTS vacaciones_solicitudes (
    id                  SERIAL PRIMARY KEY,
    id_empresa          INTEGER      NOT NULL,
    id_empleado         INTEGER      NOT NULL,

    -- Enlace enviado al empleado (un solo uso, con caducidad)
    token               VARCHAR(64),
    correo_destino      VARCHAR(150),
    enviado_at          TIMESTAMP,
    expira_at           TIMESTAMP,
    token_usado         BOOLEAN      NOT NULL DEFAULT false,

    -- Lo que llena el empleado desde el enlace
    fecha_desde         DATE,
    fecha_hasta         DATE,
    dias_solicitados    NUMERIC(6,2) DEFAULT 0,
    motivo              VARCHAR(500),
    contacto            VARCHAR(150),
    solicitado_at       TIMESTAMP,
    solicitud_ip        VARCHAR(45),

    -- Resolución
    estado              VARCHAR(20)  NOT NULL DEFAULT 'enviada'
                        CHECK (estado IN ('enviada', 'pendiente', 'aprobada', 'rechazada', 'cancelada')),
    resuelto_by         INTEGER,
    resuelto_at         TIMESTAMP,
    comentario          VARCHAR(500),
    id_vacacion         INTEGER,

    -- Auditoría estándar
    created_at          TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    created_by          INTEGER,
    updated_by          INTEGER,
    eliminado           BOOLEAN      NOT NULL DEFAULT false,
    deleted_at          TIMESTAMP,
    deleted_by          INTEGER
);

-- El token identifica el enlace: único mientras exista.
CREATE UNIQUE INDEX IF NOT EXISTS uq_vac_solicitudes_token
    ON vacaciones_solicitudes (token) WHERE token IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_vac_solicitudes_empresa
    ON vacaciones_solicitudes (id_empresa) WHERE eliminado = false;

CREATE INDEX IF NOT EXISTS idx_vac_solicitudes_empleado
    ON vacaciones_solicitudes (id_empresa, id_empleado) WHERE eliminado = false;

-- Bandeja de pendientes del módulo Vacaciones.
CREATE INDEX IF NOT EXISTS idx_vac_solicitudes_estado
    ON vacaciones_solicitudes (id_empresa, estado) WHERE eliminado = false;

COMMENT ON TABLE  vacaciones_solicitudes                  IS 'Solicitudes de vacaciones que el empleado envía desde un enlace recibido por correo';
COMMENT ON COLUMN vacaciones_solicitudes.token            IS 'Token del enlace público (un solo uso); NULL cuando ya se consumió o se canceló';
COMMENT ON COLUMN vacaciones_solicitudes.expira_at        IS 'Fecha/hora hasta la que sirve el enlace';
COMMENT ON COLUMN vacaciones_solicitudes.token_usado      IS 'true cuando el empleado ya envió el formulario con ese enlace';
COMMENT ON COLUMN vacaciones_solicitudes.estado           IS 'enviada (esperando al empleado) | pendiente (esperando aprobación) | aprobada | rechazada | cancelada';
COMMENT ON COLUMN vacaciones_solicitudes.id_vacacion      IS 'Vacación creada al aprobar la solicitud (vacaciones.id)';
COMMENT ON COLUMN vacaciones_solicitudes.comentario       IS 'Comentario de quien aprueba o motivo del rechazo';
COMMENT ON COLUMN vacaciones_solicitudes.contacto         IS 'Teléfono o correo de contacto del empleado durante sus vacaciones';
