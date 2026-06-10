-- =====================================================================
-- Seed: sri_casilleros_etiquetas (estructura del formulario 104 SRI)
-- Tabla GLOBAL (sin id_empresa). Exportada desde la BD local el 10-06-2026.
--
-- USO EN EL SERVIDOR:
--   1. Verificar si la tabla ya tiene datos:
--        SELECT COUNT(*) FROM sri_casilleros_etiquetas WHERE eliminado = false;
--   2. Si la tabla no existe o devuelve 0, ejecutar este archivo completo.
--      Si ya tiene filas, NO ejecutar los INSERT (se duplicarían).
-- =====================================================================

CREATE TABLE IF NOT EXISTS sri_casilleros_etiquetas (
    id SERIAL PRIMARY KEY,
    casillero_bruto VARCHAR(10) NOT NULL,
    casillero_neto VARCHAR(10),
    casillero_impuesto VARCHAR(10),
    seccion VARCHAR(100) NOT NULL,
    descripcion TEXT NOT NULL,
    orden INTEGER NOT NULL,
    indent INTEGER DEFAULT 0,
    bold BOOLEAN DEFAULT FALSE,
    tipo VARCHAR(50) DEFAULT 'valor',
    formula_bruto VARCHAR(255),
    formula_neto VARCHAR(255),
    formula_impuesto VARCHAR(255),
    eliminado BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_by INTEGER,
    deleted_at TIMESTAMP,
    deleted_by INTEGER
);

INSERT INTO sri_casilleros_etiquetas (casillero_bruto, seccion, descripcion, orden, indent, bold, tipo, eliminado, formula_bruto, casillero_neto, casillero_impuesto, formula_neto, formula_impuesto) VALUES ('401', '400', 'Ventas locales (excluye activos fijos) gravadas tarifa diferente de cero', 10, 0, false, 'valor', false, NULL, '411', '421', NULL, NULL);
INSERT INTO sri_casilleros_etiquetas (casillero_bruto, seccion, descripcion, orden, indent, bold, tipo, eliminado, formula_bruto, casillero_neto, casillero_impuesto, formula_neto, formula_impuesto) VALUES ('402', '400', 'Ventas locales de activos fijos gravadas tarifa diferente de cero', 20, 0, false, 'valor', false, NULL, '412', '422', NULL, NULL);
INSERT INTO sri_casilleros_etiquetas (casillero_bruto, seccion, descripcion, orden, indent, bold, tipo, eliminado, formula_bruto, casillero_neto, casillero_impuesto, formula_neto, formula_impuesto) VALUES ('403', '400', 'Ventas locales gravadas tarifa 0% que NO dan derecho a crédito tributario', 30, 0, false, 'valor', false, NULL, '413', NULL, NULL, NULL);
INSERT INTO sri_casilleros_etiquetas (casillero_bruto, seccion, descripcion, orden, indent, bold, tipo, eliminado, formula_bruto, casillero_neto, casillero_impuesto, formula_neto, formula_impuesto) VALUES ('405', '400', 'Ventas locales gravadas tarifa 0% que DAN derecho a crédito tributario', 40, 0, false, 'valor', false, NULL, '415', NULL, NULL, NULL);
INSERT INTO sri_casilleros_etiquetas (casillero_bruto, seccion, descripcion, orden, indent, bold, tipo, eliminado, formula_bruto, casillero_neto, casillero_impuesto, formula_neto, formula_impuesto) VALUES ('407', '400', 'Exportaciones de bienes', 50, 0, false, 'valor', false, NULL, '417', '423', NULL, NULL);
INSERT INTO sri_casilleros_etiquetas (casillero_bruto, seccion, descripcion, orden, indent, bold, tipo, eliminado, formula_bruto, casillero_neto, casillero_impuesto, formula_neto, formula_impuesto) VALUES ('408', '400', 'Exportaciones de servicios y/o derechos', 60, 0, false, 'valor', false, NULL, '418', '424', NULL, NULL);
INSERT INTO sri_casilleros_etiquetas (casillero_bruto, seccion, descripcion, orden, indent, bold, tipo, eliminado, formula_bruto, casillero_neto, casillero_impuesto, formula_neto, formula_impuesto) VALUES ('431', '400', 'Transferencias no objeto o exentas de IVA', 70, 0, false, 'valor', false, NULL, '441', NULL, NULL, NULL);
INSERT INTO sri_casilleros_etiquetas (casillero_bruto, seccion, descripcion, orden, indent, bold, tipo, eliminado, formula_bruto, casillero_neto, casillero_impuesto, formula_neto, formula_impuesto) VALUES ('409', '400', 'Ventas locales de activos fijos gravadas con tarifa diferente de cero (Régimen Simplificado)', 80, 0, false, 'valor', false, NULL, '419', NULL, NULL, NULL);
INSERT INTO sri_casilleros_etiquetas (casillero_bruto, seccion, descripcion, orden, indent, bold, tipo, eliminado, formula_bruto, casillero_neto, casillero_impuesto, formula_neto, formula_impuesto) VALUES ('442', '400', 'Notas de crédito por compensar el próximo mes', 90, 0, false, 'valor', false, NULL, NULL, '443', NULL, NULL);
INSERT INTO sri_casilleros_etiquetas (casillero_bruto, seccion, descripcion, orden, indent, bold, tipo, eliminado, formula_bruto, casillero_neto, casillero_impuesto, formula_neto, formula_impuesto) VALUES ('111', '400_INF', 'Total comprobantes de venta emitidos', 10, 0, false, 'valor', false, NULL, NULL, NULL, NULL, NULL);
INSERT INTO sri_casilleros_etiquetas (casillero_bruto, seccion, descripcion, orden, indent, bold, tipo, eliminado, formula_bruto, casillero_neto, casillero_impuesto, formula_neto, formula_impuesto) VALUES ('113', '400_INF', 'Total comprobantes de venta anulados', 20, 0, false, 'valor', false, NULL, NULL, NULL, NULL, NULL);
INSERT INTO sri_casilleros_etiquetas (casillero_bruto, seccion, descripcion, orden, indent, bold, tipo, eliminado, formula_bruto, casillero_neto, casillero_impuesto, formula_neto, formula_impuesto) VALUES ('115', '400_INF', 'Total comprobantes de retención emitidos', 30, 0, false, 'valor', false, NULL, NULL, NULL, NULL, NULL);
INSERT INTO sri_casilleros_etiquetas (casillero_bruto, seccion, descripcion, orden, indent, bold, tipo, eliminado, formula_bruto, casillero_neto, casillero_impuesto, formula_neto, formula_impuesto) VALUES ('117', '400_INF', 'Total comprobantes de retención anulados', 40, 0, false, 'valor', false, NULL, NULL, NULL, NULL, NULL);
INSERT INTO sri_casilleros_etiquetas (casillero_bruto, seccion, descripcion, orden, indent, bold, tipo, eliminado, formula_bruto, casillero_neto, casillero_impuesto, formula_neto, formula_impuesto) VALUES ('119', '400_INF', 'Total notas de crédito emitidas', 50, 0, false, 'valor', false, NULL, NULL, NULL, NULL, NULL);
INSERT INTO sri_casilleros_etiquetas (casillero_bruto, seccion, descripcion, orden, indent, bold, tipo, eliminado, formula_bruto, casillero_neto, casillero_impuesto, formula_neto, formula_impuesto) VALUES ('121', '400_INF', 'Total notas de crédito anuladas', 60, 0, false, 'valor', false, NULL, NULL, NULL, NULL, NULL);
INSERT INTO sri_casilleros_etiquetas (casillero_bruto, seccion, descripcion, orden, indent, bold, tipo, eliminado, formula_bruto, casillero_neto, casillero_impuesto, formula_neto, formula_impuesto) VALUES ('123', '400_INF', 'Total notas de débito emitidas', 70, 0, false, 'valor', false, NULL, NULL, NULL, NULL, NULL);
INSERT INTO sri_casilleros_etiquetas (casillero_bruto, seccion, descripcion, orden, indent, bold, tipo, eliminado, formula_bruto, casillero_neto, casillero_impuesto, formula_neto, formula_impuesto) VALUES ('125', '400_INF', 'Total notas de débito anuladas', 80, 0, false, 'valor', false, NULL, NULL, NULL, NULL, NULL);
INSERT INTO sri_casilleros_etiquetas (casillero_bruto, seccion, descripcion, orden, indent, bold, tipo, eliminado, formula_bruto, casillero_neto, casillero_impuesto, formula_neto, formula_impuesto) VALUES ('127', '400_INF', 'Total guías de remisión emitidas', 90, 0, false, 'valor', false, NULL, NULL, NULL, NULL, NULL);
INSERT INTO sri_casilleros_etiquetas (casillero_bruto, seccion, descripcion, orden, indent, bold, tipo, eliminado, formula_bruto, casillero_neto, casillero_impuesto, formula_neto, formula_impuesto) VALUES ('129', '400_INF', 'Total guías de remisión anuladas', 100, 0, false, 'valor', false, NULL, NULL, NULL, NULL, NULL);
INSERT INTO sri_casilleros_etiquetas (casillero_bruto, seccion, descripcion, orden, indent, bold, tipo, eliminado, formula_bruto, casillero_neto, casillero_impuesto, formula_neto, formula_impuesto) VALUES ('480', '400_LIQ', 'Total transferencias gravadas tarifa diferente de cero a contado este mes', 100, 0, false, 'valor', false, NULL, NULL, NULL, NULL, NULL);
INSERT INTO sri_casilleros_etiquetas (casillero_bruto, seccion, descripcion, orden, indent, bold, tipo, eliminado, formula_bruto, casillero_neto, casillero_impuesto, formula_neto, formula_impuesto) VALUES ('481', '400_LIQ', 'Total transferencias gravadas tarifa diferente de cero a crédito este mes', 110, 0, false, 'valor', false, NULL, NULL, NULL, NULL, NULL);
INSERT INTO sri_casilleros_etiquetas (casillero_bruto, seccion, descripcion, orden, indent, bold, tipo, eliminado, formula_bruto, casillero_neto, casillero_impuesto, formula_neto, formula_impuesto) VALUES ('429', '400_LIQ', 'Total impuesto generado', 120, 0, false, 'valor', false, NULL, NULL, NULL, NULL, NULL);
INSERT INTO sri_casilleros_etiquetas (casillero_bruto, seccion, descripcion, orden, indent, bold, tipo, eliminado, formula_bruto, casillero_neto, casillero_impuesto, formula_neto, formula_impuesto) VALUES ('483', '400_LIQ', 'Impuesto a liquidar del mes anterior', 130, 0, false, 'valor', false, NULL, NULL, NULL, NULL, NULL);
INSERT INTO sri_casilleros_etiquetas (casillero_bruto, seccion, descripcion, orden, indent, bold, tipo, eliminado, formula_bruto, casillero_neto, casillero_impuesto, formula_neto, formula_impuesto) VALUES ('484', '400_LIQ', 'Impuesto a liquidar en este mes', 140, 0, false, 'valor', false, NULL, NULL, NULL, NULL, NULL);
INSERT INTO sri_casilleros_etiquetas (casillero_bruto, seccion, descripcion, orden, indent, bold, tipo, eliminado, formula_bruto, casillero_neto, casillero_impuesto, formula_neto, formula_impuesto) VALUES ('485', '400_LIQ', 'Impuesto a liquidar en el próximo mes', 150, 0, false, 'valor', false, NULL, NULL, NULL, NULL, NULL);
INSERT INTO sri_casilleros_etiquetas (casillero_bruto, seccion, descripcion, orden, indent, bold, tipo, eliminado, formula_bruto, casillero_neto, casillero_impuesto, formula_neto, formula_impuesto) VALUES ('499', '400_LIQ', 'TOTAL IMPUESTO A LIQUIDAR EN ESTE MES', 160, 0, false, 'valor', false, NULL, NULL, NULL, NULL, NULL);
INSERT INTO sri_casilleros_etiquetas (casillero_bruto, seccion, descripcion, orden, indent, bold, tipo, eliminado, formula_bruto, casillero_neto, casillero_impuesto, formula_neto, formula_impuesto) VALUES ('500', '500', 'Adquisiciones y pagos (excluye activos fijos) gravados tarifa diferente de cero (con derecho a crédito tributario)', 10, 0, false, 'valor', false, NULL, '510', '520', NULL, NULL);
INSERT INTO sri_casilleros_etiquetas (casillero_bruto, seccion, descripcion, orden, indent, bold, tipo, eliminado, formula_bruto, casillero_neto, casillero_impuesto, formula_neto, formula_impuesto) VALUES ('501', '500', 'Adquisiciones locales de activos fijos gravados tarifa diferente de cero (con derecho a crédito tributario)', 20, 0, false, 'valor', false, NULL, '511', '521', NULL, NULL);
INSERT INTO sri_casilleros_etiquetas (casillero_bruto, seccion, descripcion, orden, indent, bold, tipo, eliminado, formula_bruto, casillero_neto, casillero_impuesto, formula_neto, formula_impuesto) VALUES ('502', '500', 'Otras adquisiciones y pagos gravados tarifa diferente de cero (sin derecho a crédito tributario)', 30, 0, false, 'valor', false, NULL, '512', '522', NULL, NULL);
INSERT INTO sri_casilleros_etiquetas (casillero_bruto, seccion, descripcion, orden, indent, bold, tipo, eliminado, formula_bruto, casillero_neto, casillero_impuesto, formula_neto, formula_impuesto) VALUES ('507', '500', 'Adquisiciones y pagos (incluye activos fijos) gravados tarifa 0%', 40, 0, false, 'valor', false, NULL, '517', NULL, NULL, NULL);
INSERT INTO sri_casilleros_etiquetas (casillero_bruto, seccion, descripcion, orden, indent, bold, tipo, eliminado, formula_bruto, casillero_neto, casillero_impuesto, formula_neto, formula_impuesto) VALUES ('531', '500', 'Adquisiciones exentas o no objeto de IVA', 50, 0, false, 'valor', false, NULL, '541', NULL, NULL, NULL);
INSERT INTO sri_casilleros_etiquetas (casillero_bruto, seccion, descripcion, orden, indent, bold, tipo, eliminado, formula_bruto, casillero_neto, casillero_impuesto, formula_neto, formula_impuesto) VALUES ('544', '500', 'Notas de crédito por compensar el próximo mes', 60, 0, false, 'valor', false, NULL, NULL, '545', NULL, NULL);
