-- ============================================================================
-- Módulo: Retornos de Consignaciones en Ventas (Retornos de CV)
-- Ruta MVC: modulos/retornos-cv   (submódulo ya existente en submodulos_menu, id 11)
--
-- Un retorno registra la DEVOLUCIÓN de mercadería que el cliente había recibido
-- en una o varias consignaciones de venta. Es la ENTRADA espejo de la salida que
-- generó la consignación: la mercadería vuelve a la bodega de origen de cada línea.
--
-- Reglas del sistema:
--   - Multiempresa: todas las tablas llevan id_empresa.
--   - Eliminación lógica: eliminado / deleted_at / deleted_by.
--   - Auditoría: created_at/by, updated_at/by.
--   - No genera asiento contable (es traslado de mercadería, igual que la consignación).
-- ============================================================================

-- ---------------------------------------------------------------------------
-- Cabecera: un retorno es de UN cliente y puede agrupar líneas de VARIAS
-- consignaciones (la relación con cada consignación vive en el detalle).
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS retornos_cv (
    id                      SERIAL PRIMARY KEY,
    id_empresa              INTEGER NOT NULL,
    fecha_retorno           DATE NOT NULL,
    serie                   VARCHAR(7)  NOT NULL,
    secuencial              VARCHAR(20) NOT NULL,
    id_punto_emision        INTEGER,
    establecimiento         VARCHAR(3),
    punto_emision           VARCHAR(3),
    tipo_ambiente           VARCHAR(1) DEFAULT '1',
    id_cliente              INTEGER NOT NULL,
    id_responsable_traslado INTEGER,
    punto_partida           VARCHAR(255),
    punto_llegada           VARCHAR(255),
    motivo                  VARCHAR(255),
    observaciones           TEXT,
    estado                  VARCHAR(50) DEFAULT 'Emitida',
    subtotal                NUMERIC(15,6) DEFAULT 0,
    impuesto                NUMERIC(15,6) DEFAULT 0,
    total                   NUMERIC(15,6) DEFAULT 0,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by              INTEGER,
    updated_by              INTEGER,
    eliminado               BOOLEAN DEFAULT FALSE,
    deleted_at              TIMESTAMP,
    deleted_by              INTEGER
);

-- ---------------------------------------------------------------------------
-- Detalle: cada línea copia "tal cual" los datos de la línea de consignación
-- de origen (precio, impuesto, lote, nup, caducidad, bodega) y recuerda de qué
-- consignación / línea proviene para poder calcular el saldo pendiente.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS retornos_cv_detalles (
    id                      SERIAL PRIMARY KEY,
    id_retorno              INTEGER NOT NULL REFERENCES retornos_cv(id) ON DELETE CASCADE,
    id_empresa              INTEGER NOT NULL,
    id_consignacion         INTEGER NOT NULL,
    id_consignacion_detalle INTEGER NOT NULL,
    id_producto             INTEGER NOT NULL,
    cantidad                NUMERIC(15,6) NOT NULL,
    precio_unitario         NUMERIC(15,6) NOT NULL DEFAULT 0,
    subtotal                NUMERIC(15,6) NOT NULL DEFAULT 0,
    id_impuesto             INTEGER,
    porcentaje_impuesto     NUMERIC(5,2) DEFAULT 0,
    valor_impuesto          NUMERIC(15,6) DEFAULT 0,
    total                   NUMERIC(15,6) DEFAULT 0,
    id_bodega               INTEGER,
    lote                    VARCHAR(100),
    nup                     VARCHAR(100),
    fecha_caducidad         DATE,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    eliminado               BOOLEAN DEFAULT FALSE,
    deleted_at              TIMESTAMP,
    deleted_by              INTEGER
);

CREATE INDEX IF NOT EXISTS idx_retornos_cv_empresa        ON retornos_cv (id_empresa, eliminado);
CREATE INDEX IF NOT EXISTS idx_retornos_cv_cliente        ON retornos_cv (id_cliente);
CREATE INDEX IF NOT EXISTS idx_retornos_cv_punto          ON retornos_cv (id_punto_emision, tipo_ambiente);
CREATE INDEX IF NOT EXISTS idx_retornos_cv_det_retorno    ON retornos_cv_detalles (id_retorno);
CREATE INDEX IF NOT EXISTS idx_retornos_cv_det_consig_det ON retornos_cv_detalles (id_consignacion_detalle);
CREATE INDEX IF NOT EXISTS idx_retornos_cv_det_consig     ON retornos_cv_detalles (id_consignacion);
