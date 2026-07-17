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

-- Backfill: todo usuario que NO tiene un token pendiente ya completó su registro
-- (o nunca tuvo invitación). Los que tienen token quedan como pendientes.
UPDATE usuarios
   SET registrado = TRUE
 WHERE COALESCE(token, '') = ''
   AND registrado = FALSE;

COMMENT ON COLUMN usuarios.registrado IS 'TRUE cuando el usuario completó su registro (definió su contraseña). Independiente de `token`, que también se usa para recuperación de contraseña.';
