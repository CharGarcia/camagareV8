-- ============================================================================
-- RUC Proveedor (Res. NAC-DGERCGC26-00000027) en Retención de Compra
-- ----------------------------------------------------------------------------
-- Los otros 4 comprobantes electrónicos (factura, NC, liquidación, guía) guardan
-- el campo "RUC Proveedor" como una fila real de su info adicional al CREAR el
-- documento (no se inyecta más al generar el XML/PDF), así los documentos ya
-- emitidos no lo llevan si no lo tenían. La retención de compra no tiene una
-- tabla de info adicional propia por documento, así que se guarda el valor
-- directo en su cabecera bajo el mismo esquema: se congela al crear, queda NULL
-- en las retenciones ya existentes y XmlRetencionCompraService lo toma de esta
-- columna en vez de consultar la configuración en vivo.
-- ============================================================================
BEGIN;

ALTER TABLE retencion_compra_cabecera
    ADD COLUMN IF NOT EXISTS ruc_proveedor_sistema VARCHAR(15);

COMMIT;
