-- ============================================================================
-- Campo explícito de "registro completado" para usuarios.
--
-- Motivo: la columna `token` es AMBIGUA. Se usa tanto para la invitación de
-- registro (usuario que aún no completa su registro) como para la recuperación
-- de contraseña (usuario YA registrado que pidió resetear su clave). Por eso no
-- sirve para saber si un usuario está o no registrado: un usuario registrado en
-- proceso de recuperación tiene token, y aparecía como "Pendiente registro".
--
-- Solución: columna booleana `registrado`.
--   - Se queda en FALSE al crear (invitar) un usuario.
--   - Se pone en TRUE cuando el usuario COMPLETA su registro.
--   - La recuperación de contraseña NO la toca (y al completarse, la confirma).
--
-- Idempotente: se puede correr varias veces sin error.
-- ============================================================================

ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS registrado BOOLEAN NOT NULL DEFAULT FALSE;

-- Backfill (CONFIABLE, independiente del token):
-- La invitación (crearPorCorreo) inserta una cédula PLACEHOLDER = substr(md5(correo),0,15).
-- Al completar el registro, el usuario define su cédula REAL. Por lo tanto:
--   cédula = hash del correo  => NO registrado (pendiente)
--   cédula distinta           => registrado
-- Es reejecutable: recalcula el valor correcto para todos los usuarios.
-- (En PostgreSQL substr(s,1,15) = los primeros 15 caracteres, igual que PHP substr(s,0,15).)
UPDATE usuarios
   SET registrado = (COALESCE(cedula, '') <> substr(md5(COALESCE(mail, '')), 1, 15));

COMMENT ON COLUMN usuarios.registrado IS 'TRUE cuando el usuario completó su registro (definió su contraseña). Independiente de `token`, que también se usa para recuperación de contraseña.';
