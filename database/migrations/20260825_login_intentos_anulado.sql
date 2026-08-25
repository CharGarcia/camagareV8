-- Migración: reinicio de intentos de acceso desde la ficha del usuario
--
-- Antes, el único desbloqueo era un DELETE manual sobre login_intentos (ver el
-- comentario al pie de create_login_intentos.sql). Eso borra evidencia: la misma
-- tabla alimenta la pestaña de auditoría de accesos. En su lugar, el reinicio
-- marca los intentos fallidos como ANULADOS: dejan de contar para el bloqueo,
-- pero siguen visibles en la auditoría junto con quién los reinició y cuándo.
--
-- Tabla GLOBAL (sin id_empresa): el intento ocurre antes de saber a qué empresa
-- pertenece el usuario.

ALTER TABLE login_intentos
    ADD COLUMN IF NOT EXISTS anulado    BOOLEAN   NOT NULL DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS anulado_at TIMESTAMP NULL,
    ADD COLUMN IF NOT EXISTS anulado_por INTEGER  NULL;

COMMENT ON COLUMN login_intentos.anulado     IS 'TRUE si un superadministrador reinició los intentos: la fila ya no cuenta para el bloqueo por fuerza bruta.';
COMMENT ON COLUMN login_intentos.anulado_at  IS 'Momento en que se reiniciaron los intentos.';
COMMENT ON COLUMN login_intentos.anulado_por IS 'usuarios.id del superadministrador que reinició los intentos.';

-- El freno cuenta SOLO fallos no anulados dentro de una ventana de minutos.
-- Índices parciales para que esa cuenta siga siendo un acceso por índice.
CREATE INDEX IF NOT EXISTS idx_login_intentos_ident_vigentes
    ON login_intentos (identificador, created_at DESC)
    WHERE exitoso = FALSE AND anulado = FALSE;

CREATE INDEX IF NOT EXISTS idx_login_intentos_ip_vigentes
    ON login_intentos (ip, created_at DESC)
    WHERE exitoso = FALSE AND anulado = FALSE;
