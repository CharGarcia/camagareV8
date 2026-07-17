-- ============================================================
-- MÓDULO DE IMPORTACIONES — Migración de Base de Datos
-- Proceso de nacionalización y costeo de importaciones: agrupa el
-- proveedor exterior, los productos en términos FOB, los gastos de
-- nacionalización (arancel, FODINFA, IVA, ISD, agente afianzado, etc.)
-- y/o facturas locales ya registradas en Compras/Liquidación de Compra,
-- prorratea el costo entre las líneas y las carga al kardex.
--
-- No es un comprobante SRI (la DAI se tramita en ECUAPASS, fuera del
-- sistema): numeración interna propia, sin XML ni clave de acceso.
--
--   importaciones_cabecera         → proceso de importación (proveedor exterior,
--                                     bodega destino, criterio de prorrateo, totales)
--   importaciones_detalle          → líneas de producto en términos FOB
--   importaciones_gastos           → rubros que componen el landed cost
--                                     (manuales de la DAI o vinculados a Compras/Liquidación)
--   importaciones_factura_exterior → facturas del proveedor exterior (puede haber varias)
-- ============================================================

-- 1. CABECERA DEL PROCESO DE IMPORTACIÓN
CREATE TABLE IF NOT EXISTS importaciones_cabecera (
    id                              SERIAL PRIMARY KEY,
    id_empresa                      INTEGER NOT NULL,
    numero                          INTEGER NOT NULL,              -- correlativo interno por empresa (no SRI)
    referencia_dai                  VARCHAR(50),                   -- se completa cuando SENAE asigna la DAI

    id_proveedor                    INTEGER NOT NULL,               -- proveedor exterior (proveedores.tipo_id_proveedor = '08')
    id_agente_afianzado             INTEGER,                        -- agente de aduana local (proveedores, opcional)
    id_bodega_destino               INTEGER NOT NULL,

    incoterm                        VARCHAR(10),                    -- FOB, CFR, CIF, EXW...
    fecha_embarque                  DATE,
    fecha_llegada                   DATE,
    fecha_nacionalizacion           DATE,

    criterio_prorrateo              VARCHAR(20) NOT NULL DEFAULT 'fob'
                                     CHECK (criterio_prorrateo IN ('fob', 'peso', 'volumen', 'cantidad')),
    estado                          VARCHAR(20) NOT NULL DEFAULT 'borrador'
                                     CHECK (estado IN ('borrador', 'en_transito', 'nacionalizada', 'cerrada', 'anulada')),

    -- Totales calculados (se recalculan al guardar/prorratear, ver ImportacionesService)
    subtotal_fob                    NUMERIC(14,2) NOT NULL DEFAULT 0,
    total_gastos_capitalizables     NUMERIC(14,2) NOT NULL DEFAULT 0,  -- flete, seguro, arancel, agente, etc. (prorrateable = true)
    total_iva                       NUMERIC(14,2) NOT NULL DEFAULT 0,  -- crédito tributario, NO se capitaliza
    total_isd                       NUMERIC(14,2) NOT NULL DEFAULT 0,  -- gasto financiero, NO se capitaliza
    total_otros_gastos              NUMERIC(14,2) NOT NULL DEFAULT 0,  -- gastos manuales no prorrateables y distintos de IVA/ISD
    costo_total_nacionalizado       NUMERIC(14,2) NOT NULL DEFAULT 0,  -- landed cost total capitalizado al inventario

    id_asiento_contable             INTEGER,

    observaciones                   TEXT,

    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER,

    CONSTRAINT fk_importacion_empresa    FOREIGN KEY (id_empresa)          REFERENCES empresas(id),
    CONSTRAINT fk_importacion_proveedor  FOREIGN KEY (id_proveedor)        REFERENCES proveedores(id),
    CONSTRAINT fk_importacion_agente     FOREIGN KEY (id_agente_afianzado) REFERENCES proveedores(id),
    CONSTRAINT fk_importacion_bodega     FOREIGN KEY (id_bodega_destino)   REFERENCES bodegas(id)
);

CREATE INDEX IF NOT EXISTS idx_importaciones_empresa   ON importaciones_cabecera(id_empresa);
CREATE INDEX IF NOT EXISTS idx_importaciones_eliminado ON importaciones_cabecera(eliminado);
CREATE INDEX IF NOT EXISTS idx_importaciones_estado    ON importaciones_cabecera(estado);
CREATE INDEX IF NOT EXISTS idx_importaciones_proveedor ON importaciones_cabecera(id_proveedor);
CREATE UNIQUE INDEX IF NOT EXISTS uq_importaciones_numero ON importaciones_cabecera(id_empresa, numero) WHERE eliminado = false;

-- 2. FACTURAS DEL PROVEEDOR EXTERIOR (una importación puede consolidar varias)
CREATE TABLE IF NOT EXISTS importaciones_factura_exterior (
    id              SERIAL PRIMARY KEY,
    id_importacion  INTEGER NOT NULL,
    id_proveedor    INTEGER NOT NULL,

    numero_factura  VARCHAR(50),          -- referencia comercial, texto libre (no formato SRI)
    fecha_factura   DATE,
    monto_usd       NUMERIC(14,2) NOT NULL DEFAULT 0,
    forma_pago      VARCHAR(50),
    plazo_dias      INTEGER NOT NULL DEFAULT 0,

    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER,

    CONSTRAINT fk_facturaext_importacion FOREIGN KEY (id_importacion) REFERENCES importaciones_cabecera(id) ON DELETE CASCADE,
    CONSTRAINT fk_facturaext_proveedor   FOREIGN KEY (id_proveedor)   REFERENCES proveedores(id)
);

CREATE INDEX IF NOT EXISTS idx_facturaext_importacion ON importaciones_factura_exterior(id_importacion);

-- 3. LÍNEAS DE PRODUCTO (en términos FOB)
CREATE TABLE IF NOT EXISTS importaciones_detalle (
    id                              SERIAL PRIMARY KEY,
    id_importacion                  INTEGER NOT NULL,
    id_factura_exterior             INTEGER,                        -- de cuál factura del proveedor proviene (si hay varias)
    id_producto                     INTEGER,                        -- opcional: nullable si aún no se homologa
    codigo_producto_raw             VARCHAR(100),                   -- código tal cual venía en la carga masiva
    descripcion                     TEXT,

    cantidad                        NUMERIC(14,4) NOT NULL DEFAULT 0,
    id_medida                       INTEGER,
    precio_unitario_fob             NUMERIC(14,6) NOT NULL DEFAULT 0,
    precio_total_fob                NUMERIC(14,2) NOT NULL DEFAULT 0,

    peso_kg                         NUMERIC(14,4) NOT NULL DEFAULT 0,   -- base alternativa de prorrateo
    volumen_m3                      NUMERIC(14,4) NOT NULL DEFAULT 0,   -- base alternativa de prorrateo

    costo_unitario_nacionalizado    NUMERIC(14,6) NOT NULL DEFAULT 0,   -- resultado del prorrateo
    costo_total_nacionalizado       NUMERIC(14,2) NOT NULL DEFAULT 0,

    numero_lote                     VARCHAR(100),
    fecha_caducidad                 DATE,
    nup                             VARCHAR(100),
    id_bodega                       INTEGER,                        -- override de la bodega destino de la cabecera
    id_kardex                       INTEGER,                        -- movimiento generado al procesar inventario (para reversión)

    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER,

    CONSTRAINT fk_impdetalle_importacion FOREIGN KEY (id_importacion)      REFERENCES importaciones_cabecera(id) ON DELETE CASCADE,
    CONSTRAINT fk_impdetalle_facturaext  FOREIGN KEY (id_factura_exterior) REFERENCES importaciones_factura_exterior(id),
    CONSTRAINT fk_impdetalle_producto    FOREIGN KEY (id_producto)         REFERENCES productos(id),
    CONSTRAINT fk_impdetalle_bodega      FOREIGN KEY (id_bodega)           REFERENCES bodegas(id)
);

CREATE INDEX IF NOT EXISTS idx_impdetalle_importacion ON importaciones_detalle(id_importacion);
CREATE INDEX IF NOT EXISTS idx_impdetalle_producto     ON importaciones_detalle(id_producto);

-- 4. GASTOS DE NACIONALIZACIÓN (componen el landed cost)
CREATE TABLE IF NOT EXISTS importaciones_gastos (
    id                  SERIAL PRIMARY KEY,
    id_importacion      INTEGER NOT NULL,

    tipo_gasto          VARCHAR(30) NOT NULL
                         CHECK (tipo_gasto IN (
                             'arancel_ad_valorem', 'fodinfa', 'iva_importacion', 'isd',
                             'flete_internacional', 'seguro', 'agente_afianzado',
                             'almacenaje', 'transporte_interno', 'otro'
                         )),
    origen              VARCHAR(20) NOT NULL DEFAULT 'dai_manual'
                         CHECK (origen IN ('dai_manual', 'compra_vinculada', 'liquidacion_vinculada')),

    -- Solo cuando origen es vinculado: referencia al documento ya contabilizado en Compras/Liquidación
    -- (no se duplica el monto, solo se referencia para el prorrateo del costo).
    id_compra              INTEGER,
    id_liquidacion_compra  INTEGER,

    descripcion         VARCHAR(255),
    monto               NUMERIC(14,2) NOT NULL DEFAULT 0,
    prorrateable        BOOLEAN NOT NULL DEFAULT TRUE,   -- IVA/ISD se insertan con false por defecto (crédito tributario / gasto financiero)

    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER,

    CONSTRAINT fk_impgasto_importacion FOREIGN KEY (id_importacion)     REFERENCES importaciones_cabecera(id) ON DELETE CASCADE,
    CONSTRAINT fk_impgasto_compra      FOREIGN KEY (id_compra)          REFERENCES compras_cabecera(id),
    CONSTRAINT fk_impgasto_liquidacion FOREIGN KEY (id_liquidacion_compra) REFERENCES liquidaciones_cabecera(id)
);

CREATE INDEX IF NOT EXISTS idx_impgasto_importacion ON importaciones_gastos(id_importacion);

-- ============================================================
-- ASIENTOS CONTABLES — catálogo del concepto 'adquisiciones_importacion'
-- (mismo mecanismo que otros conceptos: se mapea la cuenta real por
-- empresa desde /config, tabla asientos_programados con tipo_referencia
-- = tipo_asiento). Ver App\Services\modulos\AsientoBuilderService::generarAsientoImportacion().
-- ============================================================
ALTER TABLE asientos_tipo ADD COLUMN IF NOT EXISTS debe_haber VARCHAR(10) NOT NULL DEFAULT 'debe';

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, debe_haber)
SELECT 'adquisiciones_importacion', 'Inventario (costo nacionalizado)',
       'FOB del proveedor exterior + gastos de nacionalización capitalizables (flete, seguro, arancel, agente afianzado, etc.).',
       'INVENTARIOIMPORTACION', 'debe'
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = 'INVENTARIOIMPORTACION');

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, debe_haber)
SELECT 'adquisiciones_importacion', 'IVA Crédito Tributario (importación)',
       'IVA pagado en la Declaración Aduanera de Importación, recuperable como crédito tributario.',
       'IVAIMPORTACION', 'debe'
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = 'IVAIMPORTACION');

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, debe_haber)
SELECT 'adquisiciones_importacion', 'ISD (gasto financiero)',
       'Impuesto a la Salida de Divisas pagado por el giro al proveedor exterior.',
       'ISDIMPORTACION', 'debe'
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = 'ISDIMPORTACION');

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, debe_haber)
SELECT 'adquisiciones_importacion', 'Otros gastos de importación (no capitalizables)',
       'Rubros manuales de la DAI marcados como no prorrateables y distintos de IVA/ISD.',
       'OTROSGASTOSIMPORTACION', 'debe'
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = 'OTROSGASTOSIMPORTACION');

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, debe_haber)
SELECT 'adquisiciones_importacion', 'Cuentas por Pagar Proveedor Exterior',
       'Saldo pendiente con el proveedor del exterior (facturas comerciales de la importación).',
       'PORPAGARPROVEEDOREXTERIOR', 'haber'
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = 'PORPAGARPROVEEDOREXTERIOR');

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, debe_haber)
SELECT 'adquisiciones_importacion', 'Cuentas por Pagar Tributos Aduaneros',
       'Saldo pendiente por los rubros manuales de la DAI (arancel, FODINFA, IVA, ISD, agente afianzado, etc.).',
       'PORPAGARTRIBUTOSADUANEROS', 'haber'
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = 'PORPAGARTRIBUTOSADUANEROS');

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, debe_haber)
SELECT 'adquisiciones_importacion', 'Reclasificación gastos ya facturados (Compras/Liquidación)',
       'Contrapartida de los gastos de importación que ya se registraron como Compra/Liquidación de Compra (con su propio gasto y CxP) y se reclasifican al costo del inventario.',
       'RECLASIFICACIONGASTOIMPORTACION', 'haber'
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = 'RECLASIFICACIONGASTOIMPORTACION');

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, debe_haber)
SELECT 'adquisiciones_importacion', 'Ajuste por redondeo',
       'Absorbe descuadres de centavos del asiento de importación (opcional, solo si aparece un descuadre menor a 3 centavos).',
       'REDONDEOIMPORTACION', 'debe'
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = 'REDONDEOIMPORTACION');

-- ============================================================
-- NOTA: el registro del submódulo en submodulos_menu / permisos en
-- modulos_asignados se hace manualmente (no vía script), según el
-- flujo estándar del proyecto.
-- ============================================================
