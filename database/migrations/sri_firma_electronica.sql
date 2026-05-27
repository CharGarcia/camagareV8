-- ============================================================
-- Migración: Firma electrónica y seguimiento SRI
-- Sistema de comprobantes electrónicos Ecuador
-- ============================================================

-- ── Tabla de certificados de firma electrónica por empresa ──
CREATE TABLE IF NOT EXISTS empresa_firma_electronica (
    id                  SERIAL PRIMARY KEY,
    id_empresa          INTEGER NOT NULL REFERENCES empresas(id),
    nombre_propietario  VARCHAR(255),           -- Nombre del titular del certificado
    archivo_path        TEXT NOT NULL,           -- Ruta relativa a storage/firmas/
    p12_password        TEXT NOT NULL,           -- Contraseña del .p12 (encriptar en producción)
    vigente_desde       DATE,
    vigente_hasta       DATE,
    estado              VARCHAR(20) DEFAULT 'activo' CHECK (estado IN ('activo', 'inactivo')),
    -- Auditoría estándar
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by          INTEGER,
    updated_by          INTEGER,
    eliminado           BOOLEAN DEFAULT FALSE,
    deleted_at          TIMESTAMP,
    deleted_by          INTEGER,
    CONSTRAINT uq_firma_empresa_activa UNIQUE (id_empresa, estado)
        DEFERRABLE INITIALLY DEFERRED
);

COMMENT ON TABLE empresa_firma_electronica IS
    'Certificados digitales .p12 por empresa para firma XAdES-BES';
COMMENT ON COLUMN empresa_firma_electronica.archivo_path IS
    'Ruta relativa a {MVC_ROOT}/storage/firmas/  Ej: 1/certificado.p12';
COMMENT ON COLUMN empresa_firma_electronica.p12_password IS
    'Contraseña del PKCS12. En producción debe encriptarse con AES-256.';

-- ── Columnas de seguimiento SRI en ventas_cabecera ──────────
ALTER TABLE ventas_cabecera
    ADD COLUMN IF NOT EXISTS estado_sri          VARCHAR(20) DEFAULT 'pendiente',
    ADD COLUMN IF NOT EXISTS fecha_envio_sri     TIMESTAMP,
    ADD COLUMN IF NOT EXISTS fecha_autorizacion  TIMESTAMP,
    ADD COLUMN IF NOT EXISTS xml_autorizado      TEXT,
    ADD COLUMN IF NOT EXISTS mensajes_sri        TEXT;

-- Índice para el CRON (busca pendientes rápidamente)
CREATE INDEX IF NOT EXISTS idx_ventas_cabecera_estado_sri
    ON ventas_cabecera (estado_sri)
    WHERE eliminado = FALSE;

COMMENT ON COLUMN ventas_cabecera.estado_sri IS
    'pendiente | enviando | recibida | autorizado | no_autorizado | devuelta | en_procesamiento | error';
COMMENT ON COLUMN ventas_cabecera.xml_autorizado IS
    'XML completo devuelto por el SRI una vez autorizado';
COMMENT ON COLUMN ventas_cabecera.mensajes_sri IS
    'JSON con errores/advertencias devueltos por el SRI';

-- ── Directorio de almacenamiento (crear vía PHP, no SQL) ────
-- mkdir storage/firmas/{id_empresa}/ con permisos 0750
