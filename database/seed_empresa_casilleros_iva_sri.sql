-- =====================================================================
-- Seed: empresa_casilleros_iva_sri (mapeo tarifa -> casillero form 104)
-- Configuración de la EMPRESA 1, exportada desde la BD local el 10-06-2026.
--
-- USO EN EL SERVIDOR:
--   1. Verificar si la empresa 1 ya tiene configuración:
--        SELECT COUNT(*) FROM empresa_casilleros_iva_sri
--        WHERE id_empresa = 1 AND eliminado = false;
--   2. Si devuelve 0, ejecutar este archivo completo.
--      Si ya tiene filas, NO ejecutar (se duplicaría la configuración).
-- =====================================================================

CREATE TABLE IF NOT EXISTS empresa_casilleros_iva_sri (
    id SERIAL PRIMARY KEY,
    id_empresa INTEGER NOT NULL,
    codigo INTEGER NOT NULL,
    tipo_documento VARCHAR(50) NOT NULL,
    casillero_bruto VARCHAR(10),
    casillero_neto VARCHAR(10),
    casillero_impuesto VARCHAR(10),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_by INTEGER,
    eliminado BOOLEAN DEFAULT FALSE,
    deleted_at TIMESTAMP,
    deleted_by INTEGER
);

INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 1, 'factura_compra', '507', '517', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 2, 'factura_compra', '', '', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 4, 'factura_compra', '', '', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 5, 'factura_compra', '532', '542', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 6, 'factura_compra', '', '', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 7, 'factura_compra', '500', '510', '520', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 8, 'factura_compra', '540', '550', '560', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 10, 'factura_compra', '', '', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 11, 'factura_compra', '530', '533', '534', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 1, 'factura_venta', '403', '413', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 2, 'factura_venta', '', '', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 4, 'factura_venta', '', '', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 5, 'factura_venta', '431', '441', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 6, 'factura_venta', '', '', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 7, 'factura_venta', '401', '411', '421', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 8, 'factura_venta', '425', '435', '445', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 10, 'factura_venta', '', '', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 11, 'factura_venta', '410', '420', '430', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 1, 'liquidacion_compra', '507', '517', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 2, 'liquidacion_compra', '', '', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 4, 'liquidacion_compra', '', '', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 5, 'liquidacion_compra', '532', '542', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 6, 'liquidacion_compra', '', '', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 7, 'liquidacion_compra', '500', '510', '520', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 8, 'liquidacion_compra', '', '', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 10, 'liquidacion_compra', '', '', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 11, 'liquidacion_compra', '', '', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 1, 'nota_credito_compra', '', '517', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 2, 'nota_credito_compra', '', '', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 4, 'nota_credito_compra', '', '', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 5, 'nota_credito_compra', '', '542', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 6, 'nota_credito_compra', '', '', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 7, 'nota_credito_compra', '', '510', '520', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 8, 'nota_credito_compra', '', '550', '560', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 10, 'nota_credito_compra', '', '', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 11, 'nota_credito_compra', '', '533', '534', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 1, 'nota_credito_venta', '', '413', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 2, 'nota_credito_venta', '', '', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 4, 'nota_credito_venta', '', '', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 5, 'nota_credito_venta', '', '441', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 6, 'nota_credito_venta', '', '', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 7, 'nota_credito_venta', '', '411', '421', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 8, 'nota_credito_venta', '', '435', '445', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 10, 'nota_credito_venta', '', '', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 11, 'nota_credito_venta', '', '420', '430', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 1, 'nota_debito_compra', '', '', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 2, 'nota_debito_compra', '', '', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 4, 'nota_debito_compra', '', '', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 5, 'nota_debito_compra', '', '', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 6, 'nota_debito_compra', '', '', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 7, 'nota_debito_compra', '', '', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 8, 'nota_debito_compra', '', '', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 10, 'nota_debito_compra', '', '', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 11, 'nota_debito_compra', '', '', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 1, 'nota_venta_compra', '', '', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 2, 'nota_venta_compra', '', '', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 4, 'nota_venta_compra', '', '', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 5, 'nota_venta_compra', '508', '518', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 6, 'nota_venta_compra', '', '', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 7, 'nota_venta_compra', '', '', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 8, 'nota_venta_compra', '', '', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 10, 'nota_venta_compra', '', '', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 11, 'nota_venta_compra', '', '', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 1, 'retencion_iva', '', '', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 145, 'retencion_iva', '725', '609', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 146, 'retencion_iva', '723', '609', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 147, 'retencion_iva', '727', '609', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 148, 'retencion_iva', '729', '609', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 149, 'retencion_iva', '731', '609', '', 1, 1);
INSERT INTO empresa_casilleros_iva_sri (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by) VALUES (1, 287, 'retencion_iva', '721', '609', '', 1, 1);
