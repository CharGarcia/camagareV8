-- ============================================================
-- Migración: Agregar columnas SRI a liquidaciones_cabecera
-- Fecha: 2026-05-05
-- ============================================================

ALTER TABLE liquidaciones_cabecera
    ADD COLUMN IF NOT EXISTS clave_acceso         VARCHAR(49),
    ADD COLUMN IF NOT EXISTS tipo_ambiente         VARCHAR(2)  NOT NULL DEFAULT '1',
    ADD COLUMN IF NOT EXISTS tipo_emision          VARCHAR(1)  NOT NULL DEFAULT '1',
    ADD COLUMN IF NOT EXISTS numero_autorizacion   VARCHAR(50),
    ADD COLUMN IF NOT EXISTS fecha_autorizacion    TIMESTAMP,
    ADD COLUMN IF NOT EXISTS fecha_envio_sri       TIMESTAMP,
    ADD COLUMN IF NOT EXISTS estado_sri            VARCHAR(30),
    ADD COLUMN IF NOT EXISTS mensajes_sri          TEXT,
    ADD COLUMN IF NOT EXISTS xml_autorizado        TEXT;

-- Índice para búsqueda por clave de acceso
CREATE UNIQUE INDEX IF NOT EXISTS idx_liq_clave_acceso
    ON liquidaciones_cabecera(clave_acceso)
    WHERE clave_acceso IS NOT NULL;

-- Comentario descriptivo
COMMENT ON COLUMN liquidaciones_cabecera.clave_acceso       IS 'Clave de acceso de 49 dígitos generada para el SRI';
COMMENT ON COLUMN liquidaciones_cabecera.tipo_ambiente       IS '1=Pruebas, 2=Producción';
COMMENT ON COLUMN liquidaciones_cabecera.tipo_emision        IS '1=Normal, 2=Emisión offline';
COMMENT ON COLUMN liquidaciones_cabecera.numero_autorizacion IS 'Número de autorización devuelto por el SRI';
COMMENT ON COLUMN liquidaciones_cabecera.fecha_autorizacion  IS 'Fecha y hora de autorización del SRI';
COMMENT ON COLUMN liquidaciones_cabecera.estado_sri          IS 'Estado SRI: enviando, recibida, autorizado, devuelta, no_autorizado, error';
COMMENT ON COLUMN liquidaciones_cabecera.mensajes_sri        IS 'Mensajes y errores del SRI en formato JSON';
COMMENT ON COLUMN liquidaciones_cabecera.xml_autorizado      IS 'XML firmado y autorizado por el SRI';
