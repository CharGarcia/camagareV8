-- ============================================================
-- MÓDULO DE COMPRAS — Migración de Base de Datos
-- Tablas: compras_cabecera, compras_detalle, compras_detalle_impuestos,
--         compras_pagos, compras_retenciones, compras_asiento
-- Compatible con ATS SRI Ecuador
-- ============================================================

-- 1. CABECERA DEL COMPROBANTE DE COMPRA
CREATE TABLE IF NOT EXISTS compras_cabecera (
    id                      SERIAL PRIMARY KEY,
    id_empresa              INTEGER NOT NULL,
    id_proveedor            INTEGER NOT NULL,
    id_establecimiento      INTEGER,
    id_punto_emision        INTEGER,
    id_sustento_tributario  INTEGER,

    -- Datos del comprobante del proveedor (para ATS)
    tipo_comprobante        VARCHAR(5) NOT NULL DEFAULT '01',   -- 01=Factura, 03=Liquidación, 04=Nota de Venta...
    tipo_id_proveedor       VARCHAR(5),                         -- RUC, Cédula, Pasaporte (Tabla 2 SRI)
    parte_relacionada       BOOLEAN NOT NULL DEFAULT FALSE,     -- Parte relacionada SI/NO

    -- Serie y número del comprobante del proveedor
    establecimiento_prov    VARCHAR(3),                         -- Serie establecimiento proveedor
    punto_emision_prov      VARCHAR(3),                         -- Serie punto emisión proveedor
    secuencial_prov         VARCHAR(15),                        -- Número secuencial del proveedor
    numero_autorizacion     VARCHAR(49),                        -- Clave de autorización (físico 10 / electrónico 49)

    -- Fechas
    fecha_emision           DATE NOT NULL,                      -- Fecha del comprobante del proveedor
    fecha_registro          DATE NOT NULL DEFAULT CURRENT_DATE, -- Fecha de registro contable

    -- Bases imponibles (campos ATS)
    base_no_gra_iva         NUMERIC(14,2) NOT NULL DEFAULT 0,   -- Base no objeto de IVA
    base_imponible_0        NUMERIC(14,2) NOT NULL DEFAULT 0,   -- Base tarifa 0%
    base_imponible_grav     NUMERIC(14,2) NOT NULL DEFAULT 0,   -- Base gravada (tarifa diferente de 0%)
    base_imponible_exe      NUMERIC(14,2) NOT NULL DEFAULT 0,   -- Base exenta
    monto_ice               NUMERIC(14,2) NOT NULL DEFAULT 0,   -- Valor ICE
    monto_iva               NUMERIC(14,2) NOT NULL DEFAULT 0,   -- Valor IVA total

    -- Totales generales
    total_sin_impuestos     NUMERIC(14,2) NOT NULL DEFAULT 0,
    total_descuento         NUMERIC(14,2) NOT NULL DEFAULT 0,
    importe_total           NUMERIC(14,2) NOT NULL DEFAULT 0,
    moneda                  VARCHAR(10) NOT NULL DEFAULT 'DOLAR',

    -- Retenciones de IVA (campos ATS directos)
    val_ret_bien_10         NUMERIC(14,2) DEFAULT 0,
    val_ret_serv_20         NUMERIC(14,2) DEFAULT 0,
    valor_ret_bienes_30     NUMERIC(14,2) DEFAULT 0,
    val_ret_serv_50         NUMERIC(14,2) DEFAULT 0,
    valor_ret_servicios_70  NUMERIC(14,2) DEFAULT 0,
    val_ret_serv_100        NUMERIC(14,2) DEFAULT 0,

    -- Datos del comprobante de retención emitido por la empresa
    estab_retencion         VARCHAR(3),
    pto_emi_retencion       VARCHAR(3),
    sec_retencion           VARCHAR(9),
    autorizacion_retencion  VARCHAR(49),
    fecha_emision_ret       DATE,

    -- Control
    observaciones           TEXT,
    estado                  VARCHAR(20) NOT NULL DEFAULT 'borrador',  -- borrador / registrado / anulado

    -- Auditoría
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER,

    CONSTRAINT fk_compra_empresa     FOREIGN KEY (id_empresa)   REFERENCES empresas(id),
    CONSTRAINT fk_compra_proveedor   FOREIGN KEY (id_proveedor) REFERENCES proveedores(id)
);

CREATE INDEX IF NOT EXISTS idx_compras_empresa     ON compras_cabecera(id_empresa);
CREATE INDEX IF NOT EXISTS idx_compras_proveedor   ON compras_cabecera(id_proveedor);
CREATE INDEX IF NOT EXISTS idx_compras_eliminado   ON compras_cabecera(eliminado);
CREATE INDEX IF NOT EXISTS idx_compras_fecha       ON compras_cabecera(fecha_emision);
CREATE INDEX IF NOT EXISTS idx_compras_estado      ON compras_cabecera(estado);

-- 2. DETALLE DEL COMPROBANTE
CREATE TABLE IF NOT EXISTS compras_detalle (
    id                          SERIAL PRIMARY KEY,
    id_compra                   INTEGER NOT NULL,
    id_producto                 INTEGER,                        -- Opcional: referencia al catálogo de productos
    codigo_principal            VARCHAR(50),
    codigo_auxiliar             VARCHAR(50),
    descripcion                 TEXT NOT NULL,
    cantidad                    NUMERIC(14,4) NOT NULL DEFAULT 1,
    precio_unitario             NUMERIC(14,6) NOT NULL DEFAULT 0,
    descuento                   NUMERIC(14,2) NOT NULL DEFAULT 0,
    precio_total_sin_impuesto   NUMERIC(14,2) NOT NULL DEFAULT 0,

    CONSTRAINT fk_detalle_compra FOREIGN KEY (id_compra) REFERENCES compras_cabecera(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_compras_detalle_compra ON compras_detalle(id_compra);

-- 3. IMPUESTOS POR ÍTEM
CREATE TABLE IF NOT EXISTS compras_detalle_impuestos (
    id                  SERIAL PRIMARY KEY,
    id_compra_detalle   INTEGER NOT NULL,
    codigo_impuesto     VARCHAR(5) NOT NULL,    -- 2=IVA, 3=ICE, 5=ISD
    codigo_porcentaje   VARCHAR(5) NOT NULL,    -- 0=0%, 2=12%, 3=14%, 4=15%...
    tarifa              NUMERIC(5,2) NOT NULL DEFAULT 0,
    base_imponible      NUMERIC(14,2) NOT NULL DEFAULT 0,
    valor               NUMERIC(14,2) NOT NULL DEFAULT 0,

    CONSTRAINT fk_impuesto_detalle FOREIGN KEY (id_compra_detalle) REFERENCES compras_detalle(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_compras_impuestos_detalle ON compras_detalle_impuestos(id_compra_detalle);

-- 4. FORMAS DE PAGO
CREATE TABLE IF NOT EXISTS compras_pagos (
    id              SERIAL PRIMARY KEY,
    id_compra       INTEGER NOT NULL,
    forma_pago      VARCHAR(5) NOT NULL,        -- Código Tabla 1 SRI (01, 16, 17, 20...)
    total           NUMERIC(14,2) NOT NULL DEFAULT 0,
    plazo           INTEGER DEFAULT 0,
    unidad_tiempo   VARCHAR(20) DEFAULT 'dias', -- dias, meses, anios

    CONSTRAINT fk_pago_compra FOREIGN KEY (id_compra) REFERENCES compras_cabecera(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_compras_pagos_compra ON compras_pagos(id_compra);

-- 5. RETENCIONES (líneas del comprobante de retención)
CREATE TABLE IF NOT EXISTS compras_retenciones (
    id                  SERIAL PRIMARY KEY,
    id_compra           INTEGER NOT NULL,
    tipo_impuesto       VARCHAR(10) NOT NULL DEFAULT 'RENTA', -- RENTA, IVA, ISD
    id_retencion_sri    INTEGER,                -- FK opcional → retenciones_sri
    cod_ret_air         VARCHAR(10) NOT NULL,  -- Código retención (Tabla 3 SRI)
    concepto_ret        TEXT,                  -- Descripción del concepto
    base_imp_air        NUMERIC(14,2) NOT NULL DEFAULT 0,
    porcentaje_air      NUMERIC(5,2) NOT NULL DEFAULT 0,
    val_ret_air         NUMERIC(14,2) NOT NULL DEFAULT 0,

    CONSTRAINT fk_retencion_compra FOREIGN KEY (id_compra) REFERENCES compras_cabecera(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_compras_retenciones_compra ON compras_retenciones(id_compra);

-- (La tabla compras_asiento fue eliminada; el asiento contable
--  se gestionará desde un módulo de contabilidad independiente.)

-- ============================================================
-- INSERTAR SUBMÓDULO EN EL MENÚ (si no existe)
-- ============================================================
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM submodulos_menu WHERE ruta = 'modulos/compras') THEN
        INSERT INTO submodulos_menu (id_modulo, nombre_submodulo, ruta, status)
        SELECT m.id, 'Compras', 'modulos/compras', 1
        FROM modulos_menu m
        WHERE m.nombre_modulo ILIKE '%compra%' OR m.nombre_modulo ILIKE '%factura%' OR m.nombre_modulo ILIKE '%adquisicion%'
        ORDER BY m.id
        LIMIT 1;
    END IF;
END $$;
