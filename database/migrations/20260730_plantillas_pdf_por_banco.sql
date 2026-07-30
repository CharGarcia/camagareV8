-- Permite tener una plantilla de cheque distinta por cada banco (además de la
-- plantilla genérica de la empresa, con id_banco NULL, usada como respaldo).
ALTER TABLE plantillas_pdf ADD COLUMN IF NOT EXISTS id_banco INTEGER NULL REFERENCES bancos_ecuador(id);

CREATE INDEX IF NOT EXISTS idx_plantillas_pdf_activa_banco
    ON plantillas_pdf(id_empresa, tipo_documento, id_banco, es_activa);
