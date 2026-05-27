-- ============================================================
-- MÓDULO RETENCIONES EN VENTAS — Migración de Base de Datos
-- Tablas: retencion_venta_cabecera, retencion_venta_detalle
-- Retenciones que los clientes nos hacen a nosotros
-- No se autorizan en el SRI (las emite el cliente)
-- ============================================================

-- 1. CABECERA DE LA RETENCIÓN RECIBIDA
CREATE TABLE IF NOT EXISTS retencion_venta_cabecera (
    id                  SERIAL PRIMARY KEY,
    id_empresa          INTEGER NOT NULL,
    id_cliente          INTEGER NOT NULL,   -- Cliente que emitió la retención
    id_venta            INTEGER,            -- FK → ventas_cabecera (nullable)

    -- Datos del comprobante emitido por el cliente
    fecha_emision       DATE        NOT NULL,
    establecimiento     VARCHAR(3)  NOT NULL DEFAULT '001',
    punto_emision       VARCHAR(3)  NOT NULL DEFAULT '001',
    secuencial          VARCHAR(9)  NOT NULL,
    clave_acceso        VARCHAR(49),        -- Clave del cliente (referencial)
    periodo_fiscal      VARCHAR(7)  NOT NULL,  -- MM/YYYY

    -- Totales retenidos
    total_isd           NUMERIC(14,2) NOT NULL DEFAULT 0,
    total_iva           NUMERIC(14,2) NOT NULL DEFAULT 0,
    total_renta         NUMERIC(14,2) NOT NULL DEFAULT 0,

    -- Origen del registro
    origen              VARCHAR(20) NOT NULL DEFAULT 'manual'
                            CHECK (origen IN ('manual', 'electronico')),

    -- XML completo cuando viene de documento electrónico
    detalle_xml         TEXT,

    -- Auditoría
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by  INTEGER   NOT NULL,
    updated_by  INTEGER   NOT NULL,
    eliminado   BOOLEAN   NOT NULL DEFAULT FALSE,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER,

    CONSTRAINT fk_ret_vta_cab_empresa  FOREIGN KEY (id_empresa) REFERENCES empresas(id),
    CONSTRAINT fk_ret_vta_cab_cliente  FOREIGN KEY (id_cliente) REFERENCES clientes(id),
    CONSTRAINT fk_ret_vta_cab_venta    FOREIGN KEY (id_venta)   REFERENCES ventas_cabecera(id) ON DELETE SET NULL,
    CONSTRAINT uq_ret_vta_cab_clave    UNIQUE (clave_acceso)
);

CREATE INDEX IF NOT EXISTS idx_ret_vta_cab_empresa    ON retencion_venta_cabecera(id_empresa);
CREATE INDEX IF NOT EXISTS idx_ret_vta_cab_cliente    ON retencion_venta_cabecera(id_cliente);
CREATE INDEX IF NOT EXISTS idx_ret_vta_cab_venta      ON retencion_venta_cabecera(id_venta);
CREATE INDEX IF NOT EXISTS idx_ret_vta_cab_fecha      ON retencion_venta_cabecera(fecha_emision);
CREATE INDEX IF NOT EXISTS idx_ret_vta_cab_eliminado  ON retencion_venta_cabecera(eliminado);
CREATE INDEX IF NOT EXISTS idx_ret_vta_cab_origen     ON retencion_venta_cabecera(origen);
CREATE INDEX IF NOT EXISTS idx_ret_vta_cab_clave      ON retencion_venta_cabecera(clave_acceso);

-- 2. DETALLE / LÍNEAS DE RETENCIÓN
CREATE TABLE IF NOT EXISTS retencion_venta_detalle (
    id                         SERIAL PRIMARY KEY,
    id_retencion               INTEGER NOT NULL,

    -- Documento de sustento (referencia a comprobantes_autorizados.codigo_comprobante)
    cod_doc_sustento           VARCHAR(17) NOT NULL,
    fecha_emision_doc_sustento DATE        NOT NULL,

    -- Tipo de impuesto y código
    codigo_impuesto            VARCHAR(5)  NOT NULL,   -- 1=IR, 2=IVA, 6=ISD
    codigo_retencion           VARCHAR(10) NOT NULL,   -- Código SRI (303, 725, etc.)

    -- Valores
    base_imponible             NUMERIC(14,2) NOT NULL DEFAULT 0,
    porcentaje_retencion       NUMERIC(5,2)  NOT NULL DEFAULT 0,
    valor_retenido             NUMERIC(14,2) NOT NULL DEFAULT 0,

    CONSTRAINT fk_ret_vta_det_retencion FOREIGN KEY (id_retencion) REFERENCES retencion_venta_cabecera(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_ret_vta_det_retencion ON retencion_venta_detalle(id_retencion);

-- 3. INSERTAR SUBMÓDULO EN EL MENÚ (si no existe)
DO $$
DECLARE v_id_modulo INTEGER;
BEGIN
    SELECT id INTO v_id_modulo
    FROM modulos_menu
    WHERE nombre_modulo ILIKE '%venta%'
       OR nombre_modulo ILIKE '%factura%'
    ORDER BY id LIMIT 1;

    IF v_id_modulo IS NOT NULL THEN
        IF NOT EXISTS (SELECT 1 FROM submodulos_menu WHERE ruta = 'modulos/retenciones_ventas') THEN
            INSERT INTO submodulos_menu (id_modulo, nombre_submodulo, ruta, status)
            VALUES (v_id_modulo, 'Retenciones en Ventas', 'modulos/retenciones_ventas', 1);
        END IF;
    END IF;
END $$;
