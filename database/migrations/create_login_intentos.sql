-- Migración: registro de intentos de inicio de sesión
-- Sostiene el freno de fuerza bruta (app/Services/LoginRateLimitService.php).
--
-- Tabla GLOBAL: sin id_empresa, porque el intento ocurre antes de saber a qué
-- empresa pertenece el usuario — e incluso puede no existir tal usuario.
-- Tampoco lleva eliminado/deleted_at: no es una tabla operativa, es un registro
-- de seguridad que se purga por antigüedad (90 días por defecto).

CREATE TABLE IF NOT EXISTS login_intentos (
    id            BIGSERIAL PRIMARY KEY,
    identificador VARCHAR(50)  NOT NULL,          -- cédula tecleada (exista o no)
    ip            VARCHAR(45)  NOT NULL,          -- IPv4 o IPv6
    exitoso       BOOLEAN      NOT NULL DEFAULT FALSE,
    user_agent    VARCHAR(500),
    created_at    TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- Las dos consultas del freno: fallos por cuenta y fallos por IP, ambos dentro
-- de una ventana de tiempo. Sin estos índices, cada login recorrería la tabla
-- entera y el inicio de sesión se volvería lento a medida que crece.
CREATE INDEX IF NOT EXISTS idx_login_intentos_identificador
    ON login_intentos (identificador, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_login_intentos_ip
    ON login_intentos (ip, created_at DESC);

-- Para la purga por antigüedad.
CREATE INDEX IF NOT EXISTS idx_login_intentos_created_at
    ON login_intentos (created_at);

COMMENT ON TABLE  login_intentos IS 'Intentos de login (exitosos y fallidos) para el bloqueo por fuerza bruta y auditoría de accesos.';
COMMENT ON COLUMN login_intentos.identificador IS 'Cédula tecleada en el intento; puede no corresponder a ningún usuario.';
COMMENT ON COLUMN login_intentos.ip IS 'REMOTE_ADDR de la petición. No se usa X-Forwarded-For porque es falsificable.';

-- ── Desbloqueo manual (por si un usuario legítimo queda fuera) ───────────────
-- Lo normal es hacerlo desde la pantalla: config/usuarios-sistema → ficha del
-- usuario → "Intentos de acceso" → Reiniciar intentos (solo nivel 3). Eso marca
-- los intentos como anulados sin perder la evidencia (ver la migración
-- 20260825_login_intentos_anulado.sql).
--
-- Como último recurso desde la BD, sustituya la cédula y ejecute:
--   DELETE FROM login_intentos WHERE identificador = '0102030405' AND exitoso = FALSE;
--
-- Ver quién está bloqueado ahora mismo:
--   SELECT identificador, COUNT(*) AS fallos, MAX(created_at) AS ultimo
--     FROM login_intentos
--    WHERE exitoso = FALSE AND created_at > NOW() - INTERVAL '15 minutes'
--    GROUP BY identificador
--   HAVING COUNT(*) >= 5
--    ORDER BY ultimo DESC;
