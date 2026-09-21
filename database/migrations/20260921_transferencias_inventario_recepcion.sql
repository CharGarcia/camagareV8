-- ============================================================================
-- Transferencias de Inventario — envío del acta por correo y confirmación de
-- recepción por el destinatario (modulos/transferencias-inventario)
--
-- Agrega a la cabecera el estado de recepción, el token del enlace público que
-- viaja en el correo y la constancia de quién confirmó (o rechazó) lo recibido.
--
-- El movimiento de stock NO cambia: la transferencia sigue siendo de un solo
-- paso y el inventario se mueve al registrarla. La recepción es únicamente la
-- conformidad del destino, con valor de acta firmada.
--
-- Idempotente: se puede correr varias veces.
-- ============================================================================

ALTER TABLE transferencias_inventario_cabecera
    -- pendiente  = registrada, todavía no se envió el acta
    -- enviada    = acta enviada por correo, esperando la confirmación del destino
    -- recibida   = el destinatario confirmó que recibió conforme
    -- rechazada  = el destinatario no aceptó lo recibido (deja el motivo)
    ADD COLUMN IF NOT EXISTS recepcion_estado       VARCHAR(20) NOT NULL DEFAULT 'pendiente',
    -- Token secreto del enlace público del correo (sin login)
    ADD COLUMN IF NOT EXISTS recepcion_token        VARCHAR(64),
    -- Último envío: a quién y cuándo
    ADD COLUMN IF NOT EXISTS recepcion_correos      TEXT,
    ADD COLUMN IF NOT EXISTS recepcion_correo_fecha TIMESTAMP,
    -- Respuesta del destinatario
    ADD COLUMN IF NOT EXISTS recepcion_fecha        TIMESTAMP,
    ADD COLUMN IF NOT EXISTS recepcion_nombre       VARCHAR(150),
    ADD COLUMN IF NOT EXISTS recepcion_comentario   TEXT,
    ADD COLUMN IF NOT EXISTS recepcion_ip           VARCHAR(64);

-- Estados válidos (se crea solo si aún no existe la restricción)
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
         WHERE conname = 'ck_trfinv_recepcion_estado'
    ) THEN
        ALTER TABLE transferencias_inventario_cabecera
            ADD CONSTRAINT ck_trfinv_recepcion_estado
            CHECK (recepcion_estado IN ('pendiente','enviada','recibida','rechazada'));
    END IF;
END $$;

-- El token identifica una única transferencia: es la llave del enlace público.
CREATE UNIQUE INDEX IF NOT EXISTS uk_trfinv_recepcion_token
    ON transferencias_inventario_cabecera (recepcion_token)
    WHERE recepcion_token IS NOT NULL;

-- Listado filtrado por estado de recepción ("¿qué falta confirmar?")
CREATE INDEX IF NOT EXISTS idx_trfinv_recepcion_estado
    ON transferencias_inventario_cabecera (id_empresa, recepcion_estado)
    WHERE eliminado = false;

COMMENT ON COLUMN transferencias_inventario_cabecera.recepcion_estado     IS 'pendiente | enviada | recibida | rechazada. Conformidad del destino; no afecta el stock.';
COMMENT ON COLUMN transferencias_inventario_cabecera.recepcion_token      IS 'Token del enlace público /recepcion-transferencia/{token} enviado por correo.';
COMMENT ON COLUMN transferencias_inventario_cabecera.recepcion_correos    IS 'Destinatarios del último envío del acta por correo.';
COMMENT ON COLUMN transferencias_inventario_cabecera.recepcion_comentario IS 'Observación del destinatario al confirmar, o motivo cuando rechaza.';
