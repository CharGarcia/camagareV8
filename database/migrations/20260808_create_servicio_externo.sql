-- ============================================================================
-- Módulo: Servicio Externo (mantenimiento de equipos en el sitio del cliente)
-- Ruta MVC: modulos/servicio-externo
--
-- Registra una orden de servicio realizado FUERA del local (visita al sitio del
-- cliente para mantenimiento/reparación de un equipo), con líneas de repuestos
-- (de bodega) y mano de obra/servicio (libre). Desde la orden se puede generar
-- luego el documento de venta (Factura electrónica SRI o Recibo de Venta),
-- enlazándolo por id_documento. Modelado sobre carwash_ordenes.
--
-- Reglas del sistema:
--   - Multiempresa: todas las tablas llevan id_empresa.
--   - Eliminación lógica: eliminado / deleted_at / deleted_by.
--   - Auditoría: created_at/by, updated_at/by. El responsable del trabajo es el
--     mismo usuario que registra la orden (created_by); no hay campo de técnico
--     separado.
--   - Sin catálogo de equipos ni evidencia fotográfica/firma: el equipo se
--     describe en texto libre por orden y el trabajo se registra como texto.
--   - No genera asiento contable propio: el asiento lo produce el documento de
--     venta (Factura/Recibo) al emitirse, reutilizando su propio flujo.
--   - numero_orden usa el sistema de secuenciales por punto de emisión (mismas
--     reglas que un recibo de venta): establecimiento-punto-secuencial. El tipo
--     de documento 'Ordenes servicio externo' se mapea en SecuencialRepository.
-- ============================================================================

-- ---------------------------------------------------------------------------
-- Cabecera: una orden es de UN cliente. El equipo se guarda como snapshot de
-- texto libre (sin catálogo propio) y la dirección donde se hizo el servicio.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS servicioexterno_ordenes (
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
    id_cliente          INTEGER NOT NULL,
    id_bodega           INTEGER,                    -- bodega de donde se toman los repuestos
    -- equipo atendido (texto libre, sin catálogo)
    equipo_descripcion  VARCHAR(300) NOT NULL,
    equipo_marca        VARCHAR(100),
    equipo_modelo       VARCHAR(100),
    equipo_serie        VARCHAR(100),
    -- ubicación del servicio
    direccion_servicio  VARCHAR(300),
    -- tiempos y estado operativo
    fecha_servicio      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_finalizacion  TIMESTAMP,
    estado              VARCHAR(20) NOT NULL DEFAULT 'borrador', -- borrador|facturado|anulado
    -- trabajo realizado y notas
    descripcion_trabajo TEXT,
    observaciones       TEXT,
    info_adicional      JSONB,                      -- [{nombre, valor}] campos extra (estilo factura)
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
-- Detalle: repuestos (de bodega) y mano de obra/servicio (libre) usados en el
-- servicio. id_bodega se usa al facturar para descargar stock de los productos
-- inventariables.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS servicioexterno_ordenes_detalle (
    id                  SERIAL PRIMARY KEY,
    id_orden            INTEGER NOT NULL REFERENCES servicioexterno_ordenes(id) ON DELETE CASCADE,
    id_empresa          INTEGER NOT NULL,
    id_producto         INTEGER,                    -- null si es servicio/mano de obra libre
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

CREATE INDEX IF NOT EXISTS idx_servext_ordenes_empresa   ON servicioexterno_ordenes (id_empresa, eliminado);
CREATE INDEX IF NOT EXISTS idx_servext_ordenes_estado    ON servicioexterno_ordenes (id_empresa, estado, eliminado);
CREATE INDEX IF NOT EXISTS idx_servext_ordenes_cliente   ON servicioexterno_ordenes (id_cliente);
CREATE INDEX IF NOT EXISTS idx_servext_ordenes_punto     ON servicioexterno_ordenes (id_punto_emision, tipo_ambiente);
CREATE INDEX IF NOT EXISTS idx_servext_det_orden         ON servicioexterno_ordenes_detalle (id_orden);

-- Unicidad del secuencial por empresa + punto de emisión + ambiente (como recibo de venta).
CREATE UNIQUE INDEX IF NOT EXISTS uq_servext_secuencial ON servicioexterno_ordenes (id_empresa, id_punto_emision, secuencial, tipo_ambiente) WHERE eliminado = false;
