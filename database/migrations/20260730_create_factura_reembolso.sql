-- ============================================================================
-- MÓDULO FACTURA DE REEMBOLSO (SRI codDoc=01, clasificación ATS código 41
-- "Comprobante de venta emitido por reembolso")
--
-- Documento SEPARADO de Facturas de Venta: la empresa actúa como intermediario,
-- paga gastos a proveedores terceros en nombre del cliente y se los re-factura
-- sin que sea ingreso propio ni IVA propio. Mismo codDoc SRI (01-Factura), pero
-- con su propio secuencial/serie (ver app/repositories/SecuencialRepository.php
-- DOCUMENT_MAP) y con el bloque XML <reembolsos> + los 4 campos agregados de
-- infoFactura (codDocReembolso=41, totalComprobantesReembolso,
-- totalBaseImponibleReembolso, totalImpuestoReembolso).
--
-- Idempotente: CREATE TABLE IF NOT EXISTS puro (tablas 100% nuevas).
-- ============================================================================

-- ─── Cabecera ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS factura_reembolso_cabecera (
    id SERIAL PRIMARY KEY,
    id_empresa INTEGER NOT NULL,
    id_establecimiento INTEGER NOT NULL,
    id_punto_emision INTEGER NOT NULL,
    id_cliente INTEGER NOT NULL,
    id_usuario INTEGER NOT NULL,

    fecha_emision DATE NOT NULL DEFAULT CURRENT_DATE,
    establecimiento VARCHAR(3) NOT NULL,
    punto_emision VARCHAR(3) NOT NULL,
    secuencial VARCHAR(9) NOT NULL,
    clave_acceso VARCHAR(49),
    numero_autorizacion VARCHAR(50),
    fecha_autorizacion TIMESTAMP,
    tipo_emision VARCHAR(1) NOT NULL DEFAULT '1',
    tipo_ambiente VARCHAR(1) NOT NULL DEFAULT '1',

    total_sin_impuestos NUMERIC(14,2) NOT NULL DEFAULT 0,
    total_descuento NUMERIC(14,2) NOT NULL DEFAULT 0,
    importe_total NUMERIC(14,2) NOT NULL DEFAULT 0,
    propina NUMERIC(14,2) NOT NULL DEFAULT 0,
    moneda VARCHAR(10) NOT NULL DEFAULT 'DOLAR',

    -- Bloque ATS 41 (obligatorio por la Ficha Técnica SRI cuando codDocReembolso=41):
    -- calculados por el Service desde factura_reembolso_terceros, no editables a mano.
    cod_doc_reembolso VARCHAR(2) NOT NULL DEFAULT '41',
    total_comprobantes_reembolso NUMERIC(14,2) NOT NULL DEFAULT 0,   -- = base + impuesto reembolso
    total_base_imponible_reembolso NUMERIC(14,2) NOT NULL DEFAULT 0,
    total_impuesto_reembolso NUMERIC(14,2) NOT NULL DEFAULT 0,

    estado VARCHAR(20) NOT NULL DEFAULT 'borrador', -- borrador, autorizado, no_autorizado, anulado, rechazado
    estado_correo VARCHAR(20) DEFAULT 'pendiente',
    detalle_xml TEXT,
    id_asiento_contable INTEGER,

    observaciones TEXT,

    -- Auditoría estándar (CLAUDE.md §5)
    created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_by INTEGER,
    eliminado BOOLEAN DEFAULT FALSE,
    deleted_at TIMESTAMP WITHOUT TIME ZONE,
    deleted_by INTEGER,

    CONSTRAINT fk_fr_empresa FOREIGN KEY (id_empresa) REFERENCES empresas(id),
    CONSTRAINT fk_fr_establecimiento FOREIGN KEY (id_establecimiento) REFERENCES empresa_establecimiento(id),
    CONSTRAINT fk_fr_punto FOREIGN KEY (id_punto_emision) REFERENCES empresa_punto_emision(id),
    CONSTRAINT fk_fr_cliente FOREIGN KEY (id_cliente) REFERENCES clientes(id)
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_fr_clave_acceso_active
    ON factura_reembolso_cabecera (clave_acceso, id_empresa)
    WHERE eliminado = false;

CREATE INDEX IF NOT EXISTS idx_fr_empresa   ON factura_reembolso_cabecera(id_empresa);
CREATE INDEX IF NOT EXISTS idx_fr_cliente   ON factura_reembolso_cabecera(id_cliente);
CREATE INDEX IF NOT EXISTS idx_fr_fecha     ON factura_reembolso_cabecera(fecha_emision);
CREATE INDEX IF NOT EXISTS idx_fr_eliminado ON factura_reembolso_cabecera(eliminado);
CREATE INDEX IF NOT EXISTS idx_fr_estado    ON factura_reembolso_cabecera(estado);

-- ─── Detalle (líneas libres: SIN id_producto/id_bodega/lotes — no hay inventario) ──
CREATE TABLE IF NOT EXISTS factura_reembolso_detalle (
    id SERIAL PRIMARY KEY,
    id_factura_reembolso INTEGER NOT NULL,

    descripcion VARCHAR(300) NOT NULL,
    cantidad NUMERIC(18,6) NOT NULL DEFAULT 1,
    precio_unitario NUMERIC(18,6) NOT NULL DEFAULT 0,
    descuento NUMERIC(14,2) NOT NULL DEFAULT 0,
    precio_total_sin_impuesto NUMERIC(14,2) NOT NULL DEFAULT 0,

    -- true = línea de gasto reembolsado (IVA "No objeto de impuesto", código 6);
    -- false = línea de honorario/servicio propio de la empresa (IVA normal).
    es_reembolso BOOLEAN NOT NULL DEFAULT TRUE,

    CONSTRAINT fk_fr_detalle FOREIGN KEY (id_factura_reembolso) REFERENCES factura_reembolso_cabecera(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_fr_detalle_fr ON factura_reembolso_detalle(id_factura_reembolso);

CREATE TABLE IF NOT EXISTS factura_reembolso_detalle_impuestos (
    id SERIAL PRIMARY KEY,
    id_factura_reembolso_detalle INTEGER NOT NULL,
    codigo_impuesto VARCHAR(5) NOT NULL,
    codigo_porcentaje VARCHAR(5) NOT NULL,
    tarifa NUMERIC(5,2) NOT NULL DEFAULT 0,
    base_imponible NUMERIC(14,2) NOT NULL DEFAULT 0,
    valor NUMERIC(14,2) NOT NULL DEFAULT 0,

    CONSTRAINT fk_fr_detalle_impuesto FOREIGN KEY (id_factura_reembolso_detalle) REFERENCES factura_reembolso_detalle(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_fr_detalle_impuestos_det ON factura_reembolso_detalle_impuestos(id_factura_reembolso_detalle);

-- ─── Terceros reembolsados (bloque SRI <reembolsos><reembolsoDetalle>) ─────────
-- Orden de columnas espejo del XML (ver XmlFacturaReembolsoService::buildReembolsos).
CREATE TABLE IF NOT EXISTS factura_reembolso_terceros (
    id SERIAL PRIMARY KEY,
    id_factura_reembolso INTEGER NOT NULL,
    id_compra INTEGER, -- vínculo opcional con la compra ya registrada de la que se toman los datos
    orden INTEGER NOT NULL DEFAULT 0,

    tipo_identificacion_proveedor_reembolso VARCHAR(2) NOT NULL,   -- 04-08
    identificacion_proveedor_reembolso VARCHAR(20) NOT NULL,
    razon_social_proveedor_reembolso VARCHAR(300),                 -- solo BD/UI/PDF, NO va al XML
    cod_pais_pago_proveedor_reembolso VARCHAR(3),                  -- opcional
    tipo_proveedor_reembolso VARCHAR(2) NOT NULL,                  -- 01 servicios profesionales | 02 gasto
    cod_doc_reembolso VARCHAR(2) NOT NULL DEFAULT '01',            -- tipo doc del PROVEEDOR tercero (Tabla 3 SRI)
    estab_doc_reembolso VARCHAR(3) NOT NULL,
    pto_emi_doc_reembolso VARCHAR(3) NOT NULL,
    secuencial_doc_reembolso VARCHAR(9) NOT NULL,
    fecha_emision_doc_reembolso DATE NOT NULL,
    numero_autorizacion_doc_reemb VARCHAR(49) NOT NULL,

    base_imponible_total NUMERIC(14,2) NOT NULL DEFAULT 0,
    impuesto_total NUMERIC(14,2) NOT NULL DEFAULT 0,

    CONSTRAINT fk_fr_tercero FOREIGN KEY (id_factura_reembolso) REFERENCES factura_reembolso_cabecera(id) ON DELETE CASCADE,
    CONSTRAINT fk_fr_tercero_compra FOREIGN KEY (id_compra) REFERENCES compras_cabecera(id)
);
CREATE INDEX IF NOT EXISTS idx_fr_terceros_fr     ON factura_reembolso_terceros(id_factura_reembolso);
CREATE INDEX IF NOT EXISTS idx_fr_terceros_compra ON factura_reembolso_terceros(id_compra);

CREATE TABLE IF NOT EXISTS factura_reembolso_terceros_impuestos (
    id SERIAL PRIMARY KEY,
    id_factura_reembolso_tercero INTEGER NOT NULL,
    codigo_impuesto VARCHAR(5) NOT NULL,
    codigo_porcentaje VARCHAR(5) NOT NULL,
    tarifa NUMERIC(5,2) NOT NULL DEFAULT 0,
    base_imponible NUMERIC(14,2) NOT NULL DEFAULT 0,
    valor NUMERIC(14,2) NOT NULL DEFAULT 0,

    CONSTRAINT fk_fr_tercero_impuesto FOREIGN KEY (id_factura_reembolso_tercero) REFERENCES factura_reembolso_terceros(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_fr_terceros_impuestos_t ON factura_reembolso_terceros_impuestos(id_factura_reembolso_tercero);

-- ─── Pagos ──────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS factura_reembolso_pagos (
    id SERIAL PRIMARY KEY,
    id_factura_reembolso INTEGER NOT NULL,
    forma_pago VARCHAR(5) NOT NULL,
    total NUMERIC(14,2) NOT NULL DEFAULT 0,
    plazo INTEGER DEFAULT 0,
    unidad_tiempo VARCHAR(20) DEFAULT 'dias',

    CONSTRAINT fk_fr_pago FOREIGN KEY (id_factura_reembolso) REFERENCES factura_reembolso_cabecera(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_fr_pagos_fr ON factura_reembolso_pagos(id_factura_reembolso);

-- ─── Información adicional ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS factura_reembolso_adicional (
    id SERIAL PRIMARY KEY,
    id_factura_reembolso INTEGER NOT NULL,
    nombre VARCHAR(300) NOT NULL,
    valor VARCHAR(500),

    CONSTRAINT fk_fr_adicional FOREIGN KEY (id_factura_reembolso) REFERENCES factura_reembolso_cabecera(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_fr_adicional_fr ON factura_reembolso_adicional(id_factura_reembolso);
