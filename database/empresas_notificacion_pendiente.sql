-- Marca de "notificación pendiente" para las empresas registradas por la
-- migración desde el sistema anterior (MySQL).
--
-- La migración de empresas (/config/migrar-mysql → "Registrar empresas del
-- sistema anterior") crea la empresa + su usuario administrador SIN enviar
-- ningún correo, y la deja marcada con notificacion_pendiente = true. Cuando el
-- superadmin edita y GUARDA la empresa por primera vez, el sistema envía la
-- invitación de registro del usuario y los documentos legales, y limpia la marca.
--
-- El código también aplica este ALTER de forma automática (ADD COLUMN IF NOT
-- EXISTS) al usar la migración o al actualizar una empresa; este archivo existe
-- para el despliegue manual y como registro del cambio.

ALTER TABLE empresas
    ADD COLUMN IF NOT EXISTS notificacion_pendiente boolean NOT NULL DEFAULT false;

COMMENT ON COLUMN empresas.notificacion_pendiente IS
    'true = empresa registrada por la migración; al actualizarla por primera vez se envían la invitación del usuario admin y los documentos legales, y se limpia la marca.';
