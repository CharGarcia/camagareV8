-- ============================================================================
-- Módulo: Servicio Car-Wash (Órdenes de lavado de vehículos)
-- Ruta MVC: modulos/car-wash
--
-- Registra el ingreso de un vehículo al lavadero, los servicios y productos que
-- se le realizan, las novedades encontradas al recibirlo y la fecha de la próxima
-- cita sugerida. Desde la orden se puede generar luego el documento de venta
-- (Factura electrónica SRI o Recibo de Venta), enlazándolo por id_documento.
--
-- Reglas del sistema:
--   - Multiempresa: todas las tablas llevan id_empresa.
--   - Eliminación lógica: eliminado / deleted_at / deleted_by.
--   - Auditoría: created_at/by, updated_at/by.
--   - No genera asiento contable propio: el asiento lo produce el documento de
--     venta (Factura/Recibo) al emitirse, reutilizando su propio flujo.
--   - numero_orden usa el sistema de secuenciales por punto de emisión (mismas
--     reglas que un recibo de venta): establecimiento-punto-secuencial. El tipo
--     de documento 'Ordenes car-wash' se mapea en SecuencialRepository.
-- ============================================================================

-- ---------------------------------------------------------------------------
-- Cabecera: una orden es de UN vehículo y UN cliente. Guarda un snapshot de los
-- datos del vehículo al momento del ingreso (por si el vehículo cambia después).
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS carwash_ordenes (
    id                  SERIAL PRIMARY KEY,
    id_empresa          INTEGER NOT NULL,
    -- numeración con reglas de secuencial (como recibo de venta)
    id_establecimiento  INTEGER,
    id_punto_emision    INTEGER,
    establecimiento     VARCHAR(3),
    punto_emision       VARCHAR(3),
    secuencial          VARCHAR(20),
    tipo_ambiente       VARCHAR(1) DEFAULT '1',
    numero_orden        VARCHAR(25) NOT NULL,       -- establecimiento-punto-secuencial
    id_vehiculo         INTEGER NOT NULL,
    id_cliente          INTEGER,                    -- opcional al registrar; obligatorio al facturar
    id_bodega           INTEGER,                    -- bodega de donde se toma el inventario al facturar
    -- snapshot del vehículo al ingresar
    placa               VARCHAR(20),
    marca               VARCHAR(100),
    modelo              VARCHAR(100),
    kilometraje         INTEGER,
    nivel_combustible   VARCHAR(10),                -- E, 1/4, 1/2, 3/4, F (opcional)
    -- tiempos y estado operativo
    fecha_ingreso       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_entrega       TIMESTAMP,
    estado              VARCHAR(20) NOT NULL DEFAULT 'ingresado', -- ingresado|en_proceso|terminado|facturado|anulado
    -- novedades y notas
    novedades_texto     TEXT,
    observaciones       TEXT,
    info_adicional      JSONB,                      -- [{nombre, valor}] campos extra (estilo factura)
    proxima_cita        DATE,
    id_automatizacion   INTEGER,                    -- aviso de la próxima cita (opcional)
    -- totales calculados de los detalles
    subtotal            NUMERIC(14,2) NOT NULL DEFAULT 0,
    descuento           NUMERIC(14,2) NOT NULL DEFAULT 0,
    iva                 NUMERIC(14,2) NOT NULL DEFAULT 0,
    total               NUMERIC(14,2) NOT NULL DEFAULT 0,
    -- documento de venta generado desde la orden
    tipo_documento      VARCHAR(10),                -- 'FACTURA' | 'RECIBO'
    id_documento        INTEGER,                    -- id en ventas_cabecera o recibos_venta_cabecera
    numero_documento    VARCHAR(20),
    -- auditoría estándar
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by          INTEGER,
    updated_by          INTEGER,
    eliminado           BOOLEAN DEFAULT FALSE,
    deleted_at          TIMESTAMP,
    deleted_by          INTEGER
);

-- ---------------------------------------------------------------------------
-- Detalle: servicios y productos que se realizan al vehículo. Un servicio puede
-- ser "libre" (escrito al vuelo, no catalogado). id_bodega se usa al facturar
-- para descargar stock de los productos inventariables.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS carwash_ordenes_detalle (
    id                  SERIAL PRIMARY KEY,
    id_orden            INTEGER NOT NULL REFERENCES carwash_ordenes(id) ON DELETE CASCADE,
    id_empresa          INTEGER NOT NULL,
    id_producto         INTEGER,                    -- null si es servicio libre
    tipo_linea          VARCHAR(10) NOT NULL DEFAULT 'servicio', -- 'servicio' | 'producto'
    es_libre            BOOLEAN NOT NULL DEFAULT FALSE,
    descripcion         VARCHAR(300) NOT NULL,
    id_bodega           INTEGER,
    cantidad            NUMERIC(18,6) NOT NULL DEFAULT 1,
    precio_unitario     NUMERIC(18,6) NOT NULL DEFAULT 0,
    descuento           NUMERIC(14,2) NOT NULL DEFAULT 0,
    porcentaje_iva      NUMERIC(5,2) NOT NULL DEFAULT 0,
    valor_iva           NUMERIC(14,2) NOT NULL DEFAULT 0,
    total_linea         NUMERIC(14,2) NOT NULL DEFAULT 0,
    id_tarifa_iva       INTEGER,                    -- referencia a tarifa_iva (para armar impuestos al facturar)
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    eliminado           BOOLEAN DEFAULT FALSE
);

-- ---------------------------------------------------------------------------
-- Novedades: checklist de novedades encontradas al recibir el vehículo.
-- Una fila por novedad (amigable en tablet: agregar/quitar rápido).
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS carwash_ordenes_novedades (
    id                  SERIAL PRIMARY KEY,
    id_orden            INTEGER NOT NULL REFERENCES carwash_ordenes(id) ON DELETE CASCADE,
    id_empresa          INTEGER NOT NULL,
    descripcion         VARCHAR(300) NOT NULL,
    severidad           VARCHAR(10) DEFAULT 'leve',  -- leve | media | grave
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    eliminado           BOOLEAN DEFAULT FALSE
);

CREATE INDEX IF NOT EXISTS idx_carwash_ordenes_empresa   ON carwash_ordenes (id_empresa, eliminado);
CREATE INDEX IF NOT EXISTS idx_carwash_ordenes_estado    ON carwash_ordenes (id_empresa, estado, eliminado);
CREATE INDEX IF NOT EXISTS idx_carwash_ordenes_vehiculo  ON carwash_ordenes (id_vehiculo);
CREATE INDEX IF NOT EXISTS idx_carwash_ordenes_cliente   ON carwash_ordenes (id_cliente);
CREATE INDEX IF NOT EXISTS idx_carwash_ordenes_punto     ON carwash_ordenes (id_punto_emision, tipo_ambiente);
CREATE INDEX IF NOT EXISTS idx_carwash_det_orden         ON carwash_ordenes_detalle (id_orden);
CREATE INDEX IF NOT EXISTS idx_carwash_nov_orden         ON carwash_ordenes_novedades (id_orden);

-- Unicidad del secuencial por empresa + punto de emisión + ambiente (como recibo de venta).
CREATE UNIQUE INDEX IF NOT EXISTS uq_carwash_secuencial ON carwash_ordenes (id_empresa, id_punto_emision, secuencial, tipo_ambiente) WHERE eliminado = false;
