-- ============================================================
-- Factura de Reembolso RECIBIDA (código ATS 41) en el módulo Compras
-- ============================================================
-- Cuando la empresa RECIBE una factura código 01 con <codDocReembolso>41,
-- el XML trae un bloque <reembolsos><reembolsoDetalle> con los proveedores
-- terceros que el emisor (intermediario) pagó en su nombre. Antes de esta
-- migración esa información se perdía al importar/registrar la compra
-- (solo se guardaba como una compra normal). Estas tablas espejan
-- factura_reembolso_terceros/_impuestos (lado emisión) pero para el lado
-- receptor, colgando de compras_cabecera.
-- ============================================================

-- 1. Totales agregados del bloque <reembolsos> en la propia cabecera de compra.
ALTER TABLE compras_cabecera
    ADD COLUMN IF NOT EXISTS cod_doc_reembolso              VARCHAR(2),
    ADD COLUMN IF NOT EXISTS total_comprobantes_reembolso   NUMERIC(14,2),
    ADD COLUMN IF NOT EXISTS total_base_imponible_reembolso NUMERIC(14,2),
    ADD COLUMN IF NOT EXISTS total_impuesto_reembolso       NUMERIC(14,2);

-- 2. Terceros reembolsados (uno por cada <reembolsoDetalle> del XML recibido).
CREATE TABLE IF NOT EXISTS compras_reembolso_terceros (
    id                                      SERIAL PRIMARY KEY,
    id_compra                               INTEGER NOT NULL,

    tipo_identificacion_proveedor_reembolso VARCHAR(2)  NOT NULL,
    identificacion_proveedor_reembolso      VARCHAR(20) NOT NULL,
    razon_social_proveedor_reembolso        VARCHAR(300),           -- No va en el XML; solo referencia/UI
    cod_pais_pago_proveedor_reembolso       VARCHAR(3),
    tipo_proveedor_reembolso                VARCHAR(2)  NOT NULL DEFAULT '02',
    cod_doc_reembolso                       VARCHAR(2)  NOT NULL DEFAULT '01',  -- tipo doc del PROVEEDOR tercero
    estab_doc_reembolso                     VARCHAR(3),
    pto_emi_doc_reembolso                   VARCHAR(3),
    secuencial_doc_reembolso                VARCHAR(9),
    fecha_emision_doc_reembolso             DATE,
    numero_autorizacion_doc_reemb           VARCHAR(49),

    base_imponible_total                    NUMERIC(14,2) NOT NULL DEFAULT 0,  -- suma de sus impuestos
    impuesto_total                          NUMERIC(14,2) NOT NULL DEFAULT 0,

    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER,

    CONSTRAINT fk_compra_reembolso_tercero_compra FOREIGN KEY (id_compra) REFERENCES compras_cabecera(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_compras_reembolso_terceros_compra ON compras_reembolso_terceros(id_compra);

-- 3. Impuestos por tercero (varias tarifas posibles por documento reembolsado).
CREATE TABLE IF NOT EXISTS compras_reembolso_terceros_impuestos (
    id                       SERIAL PRIMARY KEY,
    id_compra_tercero        INTEGER NOT NULL,
    codigo_impuesto          VARCHAR(5) NOT NULL,
    codigo_porcentaje        VARCHAR(5) NOT NULL,
    tarifa                   NUMERIC(5,2) NOT NULL DEFAULT 0,
    base_imponible           NUMERIC(14,2) NOT NULL DEFAULT 0,
    valor                    NUMERIC(14,2) NOT NULL DEFAULT 0,

    CONSTRAINT fk_compras_reembolso_tercero_impuesto FOREIGN KEY (id_compra_tercero) REFERENCES compras_reembolso_terceros(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_compras_reembolso_terceros_impuestos_tercero ON compras_reembolso_terceros_impuestos(id_compra_tercero);
