-- ============================================================================
--  Módulo: RECIBOS DE VENTA (comprobante interno, NO electrónico / NO SRI)
--  Espejo de las tablas de ventas (facturas) con estas diferencias:
--    · Sin campos SRI (clave_acceso, numero_autorizacion, fecha_autorizacion,
--      detalle_xml, estado_correo).
--    · Columna nueva: con_impuestos (TRUE = suma IVA/ICE; FALSE = total sin
--      impuestos, tal como una factura pero sin cargar IVA ni ICE).
--  Todas las tablas son OPERATIVAS: llevan id_empresa + auditoría + eliminado.
-- ============================================================================

-- ── Cabecera ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS recibos_venta_cabecera (
    id SERIAL PRIMARY KEY,
    id_empresa INTEGER NOT NULL,
    id_establecimiento INTEGER NOT NULL,
    id_punto_emision INTEGER NOT NULL,
    id_cliente INTEGER NOT NULL,
    id_usuario INTEGER NOT NULL,          -- Creador del recibo
    id_vendedor INTEGER,

    fecha_emision DATE NOT NULL DEFAULT CURRENT_DATE,
    establecimiento VARCHAR(3) NOT NULL,  -- Ej: 001
    punto_emision VARCHAR(3) NOT NULL,    -- Ej: 001
    secuencial VARCHAR(9) NOT NULL,       -- Ej: 000000001
    recibo_numero VARCHAR(20) NOT NULL,   -- Ej: 001-001-000000001

    -- Toggle "con / sin impuestos": TRUE = calcula IVA+ICE, FALSE = solo subtotal
    con_impuestos BOOLEAN NOT NULL DEFAULT TRUE,

    total_sin_impuestos NUMERIC(14,2) NOT NULL DEFAULT 0,
    total_descuento NUMERIC(14,2) NOT NULL DEFAULT 0,
    total_ice NUMERIC(14,2) NOT NULL DEFAULT 0,
    importe_total NUMERIC(14,2) NOT NULL DEFAULT 0,
    propina NUMERIC(14,2) NOT NULL DEFAULT 0,
    moneda VARCHAR(10) NOT NULL DEFAULT 'DOLAR',
    estado VARCHAR(20) NOT NULL DEFAULT 'borrador', -- borrador (editable), anulado (sin validez pero visible)

    dias_credito INTEGER NOT NULL DEFAULT 0,
    plazo VARCHAR(50),
    observaciones TEXT,

    tipo_ambiente VARCHAR(1) NOT NULL DEFAULT '1',  -- separa pruebas/producción
    id_asiento_contable INTEGER,

    -- Auditoría
    created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_by INTEGER,
    eliminado BOOLEAN DEFAULT FALSE,
    deleted_at TIMESTAMP WITHOUT TIME ZONE,
    deleted_by INTEGER,

    CONSTRAINT fk_recibos_empresa FOREIGN KEY (id_empresa) REFERENCES empresas(id),
    CONSTRAINT fk_recibos_establecimiento FOREIGN KEY (id_establecimiento) REFERENCES empresa_establecimiento(id),
    CONSTRAINT fk_recibos_punto FOREIGN KEY (id_punto_emision) REFERENCES empresa_punto_emision(id),
    CONSTRAINT fk_recibos_cliente FOREIGN KEY (id_cliente) REFERENCES clientes(id)
);

-- ── Detalle ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS recibos_venta_detalle (
    id SERIAL PRIMARY KEY,
    id_recibo INTEGER NOT NULL,
    id_producto INTEGER,
    id_bodega INTEGER,
    id_unidad_medida INTEGER,

    codigo_principal VARCHAR(25),
    codigo_auxiliar VARCHAR(25),
    descripcion VARCHAR(300) NOT NULL,
    cantidad NUMERIC(18,6) NOT NULL DEFAULT 1,
    precio_unitario NUMERIC(18,6) NOT NULL DEFAULT 0,
    descuento NUMERIC(14,2) NOT NULL DEFAULT 0,
    precio_total_sin_impuesto NUMERIC(14,2) NOT NULL DEFAULT 0,
    id_tarifa_iva INTEGER,
    casillero VARCHAR(25),
    info_adicional TEXT,

    numero_lote VARCHAR(60),
    fecha_caducidad DATE,
    nup VARCHAR(60),
    id_inventario_kardex INTEGER,

    CONSTRAINT fk_rec_detalle_recibo FOREIGN KEY (id_recibo) REFERENCES recibos_venta_cabecera(id) ON DELETE CASCADE,
    CONSTRAINT fk_rec_detalle_producto FOREIGN KEY (id_producto) REFERENCES productos(id),
    CONSTRAINT fk_rec_detalle_bodega FOREIGN KEY (id_bodega) REFERENCES bodegas(id)
);

-- ── Impuestos por detalle ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS recibos_venta_detalle_impuestos (
    id SERIAL PRIMARY KEY,
    id_recibo_detalle INTEGER NOT NULL,
    codigo_impuesto VARCHAR(5) NOT NULL,   -- 2: IVA, 3: ICE, 5: IRBPNR
    codigo_porcentaje VARCHAR(5) NOT NULL,
    tarifa NUMERIC(5,2) NOT NULL DEFAULT 0,
    base_imponible NUMERIC(14,2) NOT NULL DEFAULT 0,
    valor NUMERIC(14,2) NOT NULL DEFAULT 0,

    CONSTRAINT fk_rec_impuesto_detalle FOREIGN KEY (id_recibo_detalle) REFERENCES recibos_venta_detalle(id) ON DELETE CASCADE
);

-- ── Pagos / formas de pago ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS recibos_venta_pagos (
    id SERIAL PRIMARY KEY,
    id_recibo INTEGER NOT NULL,
    forma_pago VARCHAR(5) NOT NULL,
    total NUMERIC(14,2) NOT NULL DEFAULT 0,
    plazo INTEGER DEFAULT 0,
    unidad_tiempo VARCHAR(20) DEFAULT 'dias',

    CONSTRAINT fk_rec_pago_recibo FOREIGN KEY (id_recibo) REFERENCES recibos_venta_cabecera(id) ON DELETE CASCADE
);

-- ── Información adicional ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS recibos_venta_adicional (
    id SERIAL PRIMARY KEY,
    id_recibo INTEGER NOT NULL,
    nombre VARCHAR(300) NOT NULL,
    valor VARCHAR(300) NOT NULL,

    CONSTRAINT fk_rec_adicional_recibo FOREIGN KEY (id_recibo) REFERENCES recibos_venta_cabecera(id) ON DELETE CASCADE
);

-- ── Índices ─────────────────────────────────────────────────────────────────
CREATE INDEX IF NOT EXISTS idx_recibos_empresa   ON recibos_venta_cabecera(id_empresa);
CREATE INDEX IF NOT EXISTS idx_recibos_cliente   ON recibos_venta_cabecera(id_cliente);
CREATE INDEX IF NOT EXISTS idx_recibos_fecha     ON recibos_venta_cabecera(fecha_emision);
CREATE INDEX IF NOT EXISTS idx_recibos_eliminado ON recibos_venta_cabecera(eliminado);
CREATE INDEX IF NOT EXISTS idx_rec_detalle_recibo ON recibos_venta_detalle(id_recibo);
CREATE INDEX IF NOT EXISTS idx_rec_pagos_recibo   ON recibos_venta_pagos(id_recibo);


-- ============================================================================
--  MENÚ Y PERMISOS
--  Registra el submódulo "Recibos de venta" en el módulo de Ventas (id 308)
--  y otorga acceso total al submódulo para los administradores existentes.
--  Ajustar id_modulo / orden según el entorno si difiere.
-- ============================================================================

-- Submódulo en el menú (ruta MVC = modulos/recibo-venta).
-- Se coloca junto a "Facturas de venta", reutilizando su id_modulo e id_icono.
-- Columnas reales de submodulos_menu: nombre_submodulo, ruta, id_modulo, orden, id_icono, status.
INSERT INTO submodulos_menu (nombre_submodulo, ruta, id_modulo, orden, id_icono, status)
SELECT 'Recibos de venta',
       'modulos/recibo-venta',
       s.id_modulo,
       (SELECT COALESCE(MAX(orden), 0) + 1 FROM submodulos_menu WHERE id_modulo = s.id_modulo),
       s.id_icono,
       1
FROM submodulos_menu s
WHERE s.ruta = 'modulos/factura-venta'
  AND NOT EXISTS (SELECT 1 FROM submodulos_menu WHERE ruta = 'modulos/recibo-venta');

-- NOTA: tras ejecutar, obtener el id del submódulo recién creado:
--   SELECT id FROM submodulos_menu WHERE ruta = 'modulos/recibo-venta';
-- y registrarlo en config/modulos_mvc.php como id_submodulo.
-- Asignar permisos a los usuarios/perfiles que correspondan en /config/permisos-modulos.
