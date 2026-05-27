-- ============================================================
-- MÓDULO RETENCIONES EN COMPRAS — Migración de Base de Datos
-- Tablas: retencion_compra_cabecera, retencion_compra_detalle
-- Compatible con SRI Ecuador — Comprobante de Retención v1.0.0
-- codDoc SRI = 07
-- ============================================================

-- 1. CABECERA DEL COMPROBANTE DE RETENCIÓN
CREATE TABLE IF NOT EXISTS retencion_compra_cabecera (
    id                           SERIAL PRIMARY KEY,
    id_empresa                   INTEGER NOT NULL,
    id_proveedor                 INTEGER NOT NULL,   -- Sujeto retenido
    id_usuario                   INTEGER,
    id_establecimiento           INTEGER,
    id_punto_emision             INTEGER,

    -- Datos del comprobante de retención (emitido por la empresa agente de retención)
    fecha_emision                DATE NOT NULL,
    establecimiento              VARCHAR(3)  NOT NULL DEFAULT '001',
    punto_emision                VARCHAR(3)  NOT NULL DEFAULT '001',
    secuencial                   VARCHAR(9),
    clave_acceso                 VARCHAR(49),
    numero_autorizacion          VARCHAR(49),
    fecha_autorizacion           TIMESTAMP,
    tipo_ambiente                VARCHAR(1)  NOT NULL DEFAULT '1',   -- 1=Pruebas, 2=Producción
    tipo_emision                 VARCHAR(1)  NOT NULL DEFAULT '1',   -- 1=Normal
    periodo_fiscal               VARCHAR(7),                         -- MM/YYYY

    -- Documento de sustento (factura/liquidación/nota débito que genera la retención)
    tipo_doc_sustento            VARCHAR(5)  NOT NULL DEFAULT '01',  -- 01=Factura, 03=Liquidación, 05=Nota Débito
    id_compra                    INTEGER,                            -- FK → compras_cabecera (nullable)
    id_liquidacion               INTEGER,                            -- FK → liquidaciones_cabecera (nullable)
    num_doc_sustento             VARCHAR(17),                        -- Ej: "001-001-000000001"
    fecha_emision_doc_sustento   DATE,
    numero_autorizacion_sustento VARCHAR(49),

    -- Totales retenidos
    total_retenido_renta         NUMERIC(14,2) NOT NULL DEFAULT 0,
    total_retenido_iva           NUMERIC(14,2) NOT NULL DEFAULT 0,
    total_retenido               NUMERIC(14,2) NOT NULL DEFAULT 0,

    -- Estado del comprobante
    estado                       VARCHAR(20) NOT NULL DEFAULT 'borrador'
                                     CHECK (estado IN ('borrador','pendiente','autorizada','no_autorizada','anulada')),
    estado_sri                   VARCHAR(30) NOT NULL DEFAULT 'pendiente',
    estado_correo                VARCHAR(20) NOT NULL DEFAULT 'pendiente',
    mensajes_sri                 TEXT,
    xml_autorizado               TEXT,

    observaciones                TEXT,

    -- Auditoría
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN   NOT NULL DEFAULT FALSE,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER,

    CONSTRAINT fk_ret_cab_empresa   FOREIGN KEY (id_empresa)   REFERENCES empresas(id),
    CONSTRAINT fk_ret_cab_proveedor FOREIGN KEY (id_proveedor) REFERENCES proveedores(id),
    CONSTRAINT fk_ret_cab_compra    FOREIGN KEY (id_compra)    REFERENCES compras_cabecera(id) ON DELETE SET NULL,
    CONSTRAINT uq_ret_cab_clave     UNIQUE (clave_acceso)
);

CREATE INDEX IF NOT EXISTS idx_ret_cab_empresa   ON retencion_compra_cabecera(id_empresa);
CREATE INDEX IF NOT EXISTS idx_ret_cab_proveedor ON retencion_compra_cabecera(id_proveedor);
CREATE INDEX IF NOT EXISTS idx_ret_cab_estado    ON retencion_compra_cabecera(estado);
CREATE INDEX IF NOT EXISTS idx_ret_cab_fecha     ON retencion_compra_cabecera(fecha_emision);
CREATE INDEX IF NOT EXISTS idx_ret_cab_eliminado ON retencion_compra_cabecera(eliminado);
CREATE INDEX IF NOT EXISTS idx_ret_cab_compra    ON retencion_compra_cabecera(id_compra);
CREATE INDEX IF NOT EXISTS idx_ret_cab_clave     ON retencion_compra_cabecera(clave_acceso);

-- 2. DETALLE / LÍNEAS DE RETENCIÓN
CREATE TABLE IF NOT EXISTS retencion_compra_detalle (
    id                         SERIAL PRIMARY KEY,
    id_empresa                 INTEGER NOT NULL,
    id_retencion               INTEGER NOT NULL,

    -- Tipo de impuesto y código
    codigo_impuesto            VARCHAR(5)  NOT NULL,   -- 1=IR, 2=IVA, 6=ISD (código XML SRI)
    id_retencion_sri           INTEGER,                -- FK → retenciones_sri
    codigo_retencion           VARCHAR(10) NOT NULL,   -- Código tabla SRI (303, 307, 725, etc.)
    concepto                   TEXT,                   -- Descripción del concepto

    -- Valores
    base_imponible             NUMERIC(14,2) NOT NULL DEFAULT 0,
    porcentaje_retener         NUMERIC(5,2)  NOT NULL DEFAULT 0,
    valor_retenido             NUMERIC(14,2) NOT NULL DEFAULT 0,

    -- Sustento por línea (mismos que cabecera en la mayoría de casos)
    cod_doc_sustento           VARCHAR(5)  DEFAULT '01',
    num_doc_sustento           VARCHAR(17),
    fecha_emision_doc_sustento DATE,

    CONSTRAINT fk_ret_det_retencion FOREIGN KEY (id_retencion) REFERENCES retencion_compra_cabecera(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_ret_det_retencion ON retencion_compra_detalle(id_retencion);
CREATE INDEX IF NOT EXISTS idx_ret_det_empresa   ON retencion_compra_detalle(id_empresa);

-- 3. INSERTAR SUBMÓDULO EN EL MENÚ (si no existe)
DO $$
DECLARE v_id_modulo INTEGER;
BEGIN
    -- Buscar el módulo de adquisiciones/compras
    SELECT id INTO v_id_modulo
    FROM modulos_menu
    WHERE nombre_modulo ILIKE '%compra%'
       OR nombre_modulo ILIKE '%adquisicion%'
       OR nombre_modulo ILIKE '%proveedor%'
    ORDER BY id LIMIT 1;

    IF v_id_modulo IS NOT NULL THEN
        IF NOT EXISTS (SELECT 1 FROM submodulos_menu WHERE ruta = 'modulos/retenciones_compras') THEN
            INSERT INTO submodulos_menu (id_modulo, nombre_submodulo, ruta, status)
            VALUES (v_id_modulo, 'Retenciones en Compras', 'modulos/retenciones_compras', 1);
        END IF;
    END IF;
END $$;
