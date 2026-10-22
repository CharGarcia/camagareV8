-- =============================================================================
-- Usuario Propietario (protección del dueño del sistema)
-- =============================================================================
-- Qué hace:
--   Agrega a `usuarios` la marca `es_propietario`. El usuario marcado:
--     1. Solo puede ser editado, desactivado, eliminado, cambiado de nivel o
--        recibir una contraseña provisional por otro Propietario.
--     2. Es el único que puede otorgar o quitar el nivel 3 (superadministrador).
--     3. Es el único que puede gestionar a otros superadministradores; un
--        superadministrador que no es Propietario puede verlos, no modificarlos.
--   Mientras ningún usuario esté marcado, el sistema funciona igual que antes.
--   La marca NO se puede poner ni quitar desde la pantalla: solo con SQL.
--
-- Toca datos: sí, solo el UPDATE del paso 2 (marca al dueño).
-- Reversible: sí.
--   UPDATE usuarios SET es_propietario = false;   -- desactiva las reglas
--   ALTER TABLE usuarios DROP COLUMN IF EXISTS es_propietario;
--
-- Ejecutar completo en el Query Tool de pgAdmin (F5). Es idempotente.
-- =============================================================================

-- 1. Columna
ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS es_propietario boolean NOT NULL DEFAULT false;

COMMENT ON COLUMN usuarios.es_propietario IS
    'Dueño del sistema: solo otro propietario lo gestiona; solo un propietario otorga el nivel 3 y gestiona a otros superadministradores. Se marca solo por SQL.';

-- 2. Marcar al propietario (cambiar el correo si corresponde a otra cuenta).
--    Solo se marca un superadministrador activo.
UPDATE usuarios
   SET es_propietario = true
 WHERE LOWER(mail) = LOWER('cmg.ba.sas@gmail.com')
   AND nivel = 3
   AND estado = 1
   AND eliminado = false
   AND es_propietario = false;

-- Comprobación (debe salir 1 fila: tu usuario, nivel 3, activo):
-- SELECT id, nombre, cedula, mail, nivel, estado
--   FROM usuarios
--  WHERE es_propietario = true AND eliminado = false;
