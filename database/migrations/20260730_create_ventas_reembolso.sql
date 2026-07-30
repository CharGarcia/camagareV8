-- Factura de Reembolso (SRI): tabla de detalle vinculada a ventas_cabecera.
-- Mismo patrón que ventas_pagos/ventas_detalle_impuestos: tablas hijas sin
-- id_empresa/eliminado propios (heredan el contexto de ventas_cabecera y se
-- borran en cascada con ella).

-- Cabecera de cada reembolsoDetalle (documento del proveedor tercero pagado
-- por la empresa en nombre del cliente).
CREATE TABLE IF NOT EXISTS ventas_reembolso_detalle (
    id                              SERIAL PRIMARY KEY,
    id_venta                        INTEGER NOT NULL,
    id_compra                       INTEGER, -- Compra ya registrada de la que se toman los datos (opcional)

    tipo_identificacion_proveedor   VARCHAR(2)  NOT NULL, -- 04 RUC, 05 cédula, 06 pasaporte...
    identificacion_proveedor        VARCHAR(20) NOT NULL,
    razon_social_proveedor          VARCHAR(300),          -- Solo para mostrar en UI/PDF, no va al XML
    cod_pais_pago_proveedor         VARCHAR(3),             -- Solo si el proveedor es del exterior
    tipo_proveedor                  VARCHAR(2)  NOT NULL,   -- 01 servicios profesionales, 02 gasto

    cod_doc_reembolso               VARCHAR(3)  NOT NULL,   -- Catálogo de tipo de comprobante SRI (01, 03, 04...)
    estab_doc_reembolso             VARCHAR(3)  NOT NULL,
    pto_emi_doc_reembolso           VARCHAR(3)  NOT NULL,
    secuencial_doc_reembolso        VARCHAR(9)  NOT NULL,
    fecha_emision_doc_reembolso     DATE        NOT NULL,
    numero_autorizacion_doc_reemb   VARCHAR(49) NOT NULL,

    created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,

    CONSTRAINT fk_reembolso_venta   FOREIGN KEY (id_venta)   REFERENCES ventas_cabecera(id)  ON DELETE CASCADE,
    CONSTRAINT fk_reembolso_compra  FOREIGN KEY (id_compra)  REFERENCES compras_cabecera(id)
);

CREATE INDEX IF NOT EXISTS idx_reembolso_venta  ON ventas_reembolso_detalle(id_venta);
CREATE INDEX IF NOT EXISTS idx_reembolso_compra ON ventas_reembolso_detalle(id_compra);

-- Detalle de impuestos (base + impuesto) del documento del proveedor, por
-- código/tarifa — mismo shape que ventas_detalle_impuestos.
CREATE TABLE IF NOT EXISTS ventas_reembolso_impuestos (
    id                  SERIAL PRIMARY KEY,
    id_reembolso        INTEGER NOT NULL,
    codigo_impuesto     VARCHAR(5) NOT NULL,  -- 2: IVA, 3: ICE, 5: IRBPNR
    codigo_porcentaje   VARCHAR(5) NOT NULL,
    tarifa              NUMERIC(5,2)  NOT NULL DEFAULT 0,
    base_imponible      NUMERIC(14,2) NOT NULL DEFAULT 0,
    valor               NUMERIC(14,2) NOT NULL DEFAULT 0,

    CONSTRAINT fk_reembolso_impuesto FOREIGN KEY (id_reembolso) REFERENCES ventas_reembolso_detalle(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_reembolso_impuestos_reembolso ON ventas_reembolso_impuestos(id_reembolso);
