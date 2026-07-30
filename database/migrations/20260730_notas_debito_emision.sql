-- ============================================================================
-- MÓDULO NOTA DE DÉBITO (emisión, SRI Ecuador codDoc=05)
--
-- Las tablas nota_debito_cabecera / nota_debito_impuestos / nota_debito_motivos
-- / nota_debito_pagos ya existen en producción (usadas hoy solo para registrar
-- notas de débito RECIBIDAS de proveedores, vía DocumentoAutomatedRegisterService).
-- Esta migración las completa para soportar también la EMISIÓN (venta), con el
-- mismo patrón que notas_credito_cabecera.
--
-- Idempotente: usa IF NOT EXISTS en todo, se puede correr varias veces y sobre
-- una tabla que ya tenga parte de estas columnas sin efectos secundarios.
-- ============================================================================

-- ─── Cabecera ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS nota_debito_cabecera (
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

    cod_doc_modificado VARCHAR(2) NOT NULL DEFAULT '01', -- 01: Factura
    num_doc_modificado VARCHAR(20) NOT NULL,
    fecha_emision_docs_sustento DATE NOT NULL,

    total_sin_impuestos NUMERIC(14,2) NOT NULL DEFAULT 0,
    importe_total NUMERIC(14,2) NOT NULL DEFAULT 0,
    estado VARCHAR(20) NOT NULL DEFAULT 'borrador', -- borrador, pendiente, autorizado, anulado, rechazado
    tipo_ambiente VARCHAR(1) NOT NULL DEFAULT '1',  -- 1 pruebas, 2 producción

    observaciones TEXT,

    -- Auditoría (obligatoria en toda tabla operativa, ver CLAUDE.md §5)
    created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_by INTEGER,
    eliminado BOOLEAN DEFAULT FALSE,
    deleted_at TIMESTAMP WITHOUT TIME ZONE,
    deleted_by INTEGER,

    CONSTRAINT fk_nd_empresa FOREIGN KEY (id_empresa) REFERENCES empresas(id),
    CONSTRAINT fk_nd_establecimiento FOREIGN KEY (id_establecimiento) REFERENCES empresa_establecimiento(id),
    CONSTRAINT fk_nd_punto FOREIGN KEY (id_punto_emision) REFERENCES empresa_punto_emision(id),
    CONSTRAINT fk_nd_cliente FOREIGN KEY (id_cliente) REFERENCES clientes(id)
);

-- Columnas que pueden faltar si la tabla ya existía (flujo de recepción):
ALTER TABLE nota_debito_cabecera ADD COLUMN IF NOT EXISTS numero_autorizacion VARCHAR(50);
ALTER TABLE nota_debito_cabecera ADD COLUMN IF NOT EXISTS fecha_autorizacion TIMESTAMP;
ALTER TABLE nota_debito_cabecera ADD COLUMN IF NOT EXISTS cod_doc_modificado VARCHAR(2) NOT NULL DEFAULT '01';
ALTER TABLE nota_debito_cabecera ADD COLUMN IF NOT EXISTS observaciones TEXT;
ALTER TABLE nota_debito_cabecera ADD COLUMN IF NOT EXISTS tipo_ambiente VARCHAR(1) NOT NULL DEFAULT '1';
ALTER TABLE nota_debito_cabecera ADD COLUMN IF NOT EXISTS created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE nota_debito_cabecera ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE nota_debito_cabecera ADD COLUMN IF NOT EXISTS created_by INTEGER;
ALTER TABLE nota_debito_cabecera ADD COLUMN IF NOT EXISTS updated_by INTEGER;
ALTER TABLE nota_debito_cabecera ADD COLUMN IF NOT EXISTS eliminado BOOLEAN DEFAULT FALSE;
ALTER TABLE nota_debito_cabecera ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP WITHOUT TIME ZONE;
ALTER TABLE nota_debito_cabecera ADD COLUMN IF NOT EXISTS deleted_by INTEGER;
-- Ya usadas por el flujo de recepción / repository actual, se agregan igual por si acaso:
ALTER TABLE nota_debito_cabecera ADD COLUMN IF NOT EXISTS id_asiento_contable INTEGER;
ALTER TABLE nota_debito_cabecera ADD COLUMN IF NOT EXISTS estado_correo VARCHAR(20) DEFAULT 'pendiente';
ALTER TABLE nota_debito_cabecera ADD COLUMN IF NOT EXISTS detalle_xml TEXT;

-- Índice único de clave_acceso (idéntico patrón a
-- fix_unique_constraints_partial_index.sql, ya aplicado para esta tabla ahí;
-- se repite aquí IF NOT EXISTS para que esta migración sea autocontenida).
DROP INDEX IF EXISTS uq_nd_clave_acceso;
CREATE UNIQUE INDEX IF NOT EXISTS uq_nd_clave_acceso_active
    ON nota_debito_cabecera (clave_acceso, id_empresa)
    WHERE eliminado = false;

CREATE INDEX IF NOT EXISTS idx_nd_empresa ON nota_debito_cabecera(id_empresa);
CREATE INDEX IF NOT EXISTS idx_nd_cliente ON nota_debito_cabecera(id_cliente);
CREATE INDEX IF NOT EXISTS idx_nd_fecha ON nota_debito_cabecera(fecha_emision);
CREATE INDEX IF NOT EXISTS idx_nd_eliminado ON nota_debito_cabecera(eliminado);
CREATE INDEX IF NOT EXISTS idx_nd_num_doc_modificado ON nota_debito_cabecera(num_doc_modificado);

-- ─── Motivos (razón + valor, repetible; reemplaza el "detalle" de productos) ──
CREATE TABLE IF NOT EXISTS nota_debito_motivos (
    id SERIAL PRIMARY KEY,
    id_nota_debito INTEGER NOT NULL,
    razon VARCHAR(300) NOT NULL,
    valor NUMERIC(14,2) NOT NULL DEFAULT 0,

    CONSTRAINT fk_nd_motivo FOREIGN KEY (id_nota_debito) REFERENCES nota_debito_cabecera(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_nd_motivos_nd ON nota_debito_motivos(id_nota_debito);

-- ─── Impuestos (a nivel de cabecera, no por línea de producto) ────────────────
CREATE TABLE IF NOT EXISTS nota_debito_impuestos (
    id SERIAL PRIMARY KEY,
    id_nota_debito INTEGER NOT NULL,
    codigo_impuesto VARCHAR(5) NOT NULL,   -- 2: IVA, 3: ICE, 5: IRBPNR
    codigo_porcentaje VARCHAR(5) NOT NULL,
    tarifa NUMERIC(5,2) NOT NULL DEFAULT 0,
    base_imponible NUMERIC(14,2) NOT NULL DEFAULT 0,
    valor NUMERIC(14,2) NOT NULL DEFAULT 0,

    CONSTRAINT fk_nd_impuesto FOREIGN KEY (id_nota_debito) REFERENCES nota_debito_cabecera(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_nd_impuestos_nd ON nota_debito_impuestos(id_nota_debito);

-- ─── Pagos (forma de pago repetible, opcional según XSD) ──────────────────────
CREATE TABLE IF NOT EXISTS nota_debito_pagos (
    id SERIAL PRIMARY KEY,
    id_nota_debito INTEGER NOT NULL,
    forma_pago VARCHAR(2) NOT NULL,
    total NUMERIC(14,2) NOT NULL DEFAULT 0,
    plazo INTEGER DEFAULT 0,
    unidad_tiempo VARCHAR(10) DEFAULT 'dias',

    CONSTRAINT fk_nd_pago FOREIGN KEY (id_nota_debito) REFERENCES nota_debito_cabecera(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_nd_pagos_nd ON nota_debito_pagos(id_nota_debito);

-- ─── Información adicional (campoAdicional del XML, mismo patrón que NC) ──────
CREATE TABLE IF NOT EXISTS nota_debito_adicional (
    id SERIAL PRIMARY KEY,
    id_nota_debito INTEGER NOT NULL,
    nombre VARCHAR(300) NOT NULL,
    valor VARCHAR(500),

    CONSTRAINT fk_nd_adicional FOREIGN KEY (id_nota_debito) REFERENCES nota_debito_cabecera(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_nd_adicional_nd ON nota_debito_adicional(id_nota_debito);
