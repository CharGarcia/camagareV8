-- Plantillas de documentos PDF (por empresa y tipo de documento)
CREATE TABLE IF NOT EXISTS plantillas_pdf (
    id              SERIAL PRIMARY KEY,
    id_empresa      INTEGER NOT NULL REFERENCES empresas(id),
    tipo_documento  VARCHAR(50) NOT NULL,   -- 'factura_venta', 'nota_credito', etc.
    nombre          VARCHAR(150) NOT NULL,
    descripcion     TEXT,
    configuracion   JSONB NOT NULL DEFAULT '{"pagina":{"formato":"A4","orientacion":"P","margenTop":10,"margenLeft":10,"margenRight":10},"elementos":[]}',
    es_activa       BOOLEAN NOT NULL DEFAULT false,
    estado          VARCHAR(20) NOT NULL DEFAULT 'borrador', -- borrador, activo
    eliminado       BOOLEAN NOT NULL DEFAULT false,
    deleted_at      TIMESTAMP,
    deleted_by      INTEGER,
    created_at      TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMP NOT NULL DEFAULT NOW(),
    created_by      INTEGER,
    updated_by      INTEGER
);

CREATE INDEX IF NOT EXISTS idx_plantillas_pdf_empresa     ON plantillas_pdf(id_empresa);
CREATE INDEX IF NOT EXISTS idx_plantillas_pdf_tipo        ON plantillas_pdf(tipo_documento);
CREATE INDEX IF NOT EXISTS idx_plantillas_pdf_activa      ON plantillas_pdf(id_empresa, tipo_documento, es_activa);
CREATE INDEX IF NOT EXISTS idx_plantillas_pdf_eliminado   ON plantillas_pdf(eliminado);
