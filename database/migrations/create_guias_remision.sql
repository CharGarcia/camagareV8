-- ============================================================
-- Módulo de Guías de Remisión
-- Tablas: transportistas, guias_remision_cabecera,
--         guias_remision_detalle, guias_remision_adicional
-- ============================================================

-- 1. Transportistas
CREATE TABLE IF NOT EXISTS transportistas (
    id                  SERIAL PRIMARY KEY,
    id_empresa          INTEGER NOT NULL,
    id_usuario          INTEGER NOT NULL,
    tipo_id             VARCHAR(2)   NOT NULL DEFAULT '05', -- 04=RUC, 05=Cédula, 06=Pasaporte
    identificacion      VARCHAR(20)  NOT NULL,
    nombre              VARCHAR(300) NOT NULL,
    placa               VARCHAR(8),
    email               VARCHAR(200),
    telefono            VARCHAR(20),
    direccion           VARCHAR(300),
    estado              VARCHAR(20)  NOT NULL DEFAULT 'activo',
    created_at          TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    created_by          INTEGER,
    updated_by          INTEGER,
    eliminado           BOOLEAN DEFAULT FALSE,
    deleted_at          TIMESTAMP WITHOUT TIME ZONE,
    deleted_by          INTEGER,
    CONSTRAINT fk_transportista_empresa FOREIGN KEY (id_empresa) REFERENCES empresas(id)
);

CREATE INDEX IF NOT EXISTS idx_transportistas_empresa   ON transportistas(id_empresa);
CREATE INDEX IF NOT EXISTS idx_transportistas_eliminado ON transportistas(eliminado);

-- 2. Cabecera de guías de remisión
CREATE TABLE IF NOT EXISTS guias_remision_cabecera (
    id                              SERIAL PRIMARY KEY,
    id_empresa                      INTEGER      NOT NULL,
    id_establecimiento              INTEGER      NOT NULL,
    id_punto_emision                INTEGER      NOT NULL,
    id_cliente                      INTEGER      NOT NULL,
    id_transportista                INTEGER      NOT NULL,
    id_usuario                      INTEGER      NOT NULL,
    -- Numeración SRI
    fecha_emision                   DATE         NOT NULL DEFAULT CURRENT_DATE,
    establecimiento                 VARCHAR(3)   NOT NULL,
    punto_emision                   VARCHAR(3)   NOT NULL,
    secuencial                      VARCHAR(9)   NOT NULL,
    clave_acceso                    VARCHAR(49),
    numero_autorizacion             VARCHAR(50),
    fecha_autorizacion              TIMESTAMP WITHOUT TIME ZONE,
    -- Datos del traslado
    placa                           VARCHAR(8)   NOT NULL,
    fecha_inicio_transporte         DATE         NOT NULL,
    fecha_fin_transporte            DATE         NOT NULL,
    direccion_partida               VARCHAR(300) NOT NULL,
    direccion_destino               VARCHAR(300) NOT NULL,
    motivo_traslado                 VARCHAR(300) NOT NULL,
    ruta                            VARCHAR(300),
    -- Documento sustento (opcional)
    cod_doc_sustento                VARCHAR(2),   -- 01=Factura, 04=NC, etc.
    num_doc_sustento                VARCHAR(20),  -- Ej: 001-001-000000001
    num_autorizacion_doc_sustento   VARCHAR(50),
    fecha_emision_doc_sustento      DATE,
    -- Opcionales SRI
    doc_aduanero_unico              VARCHAR(20),
    cod_establecimiento_destino     VARCHAR(3),
    -- Control y estado
    tipo_ambiente                   VARCHAR(2)   NOT NULL DEFAULT '1',
    tipo_emision                    VARCHAR(2)   NOT NULL DEFAULT '1',
    estado                          VARCHAR(20)  NOT NULL DEFAULT 'borrador',
    estado_correo                   VARCHAR(20)  DEFAULT 'pendiente',
    observaciones                   TEXT,
    -- Auditoría
    created_at                      TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at                      TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    created_by                      INTEGER,
    updated_by                      INTEGER,
    eliminado                       BOOLEAN DEFAULT FALSE,
    deleted_at                      TIMESTAMP WITHOUT TIME ZONE,
    deleted_by                      INTEGER,
    CONSTRAINT fk_gr_empresa        FOREIGN KEY (id_empresa)        REFERENCES empresas(id),
    CONSTRAINT fk_gr_establecimiento FOREIGN KEY (id_establecimiento) REFERENCES empresa_establecimiento(id),
    CONSTRAINT fk_gr_punto          FOREIGN KEY (id_punto_emision)  REFERENCES empresa_punto_emision(id),
    CONSTRAINT fk_gr_cliente        FOREIGN KEY (id_cliente)        REFERENCES clientes(id),
    CONSTRAINT fk_gr_transportista  FOREIGN KEY (id_transportista)  REFERENCES transportistas(id)
);

CREATE INDEX IF NOT EXISTS idx_gr_empresa       ON guias_remision_cabecera(id_empresa);
CREATE INDEX IF NOT EXISTS idx_gr_eliminado     ON guias_remision_cabecera(eliminado);
CREATE INDEX IF NOT EXISTS idx_gr_fecha         ON guias_remision_cabecera(fecha_emision);
CREATE INDEX IF NOT EXISTS idx_gr_cliente       ON guias_remision_cabecera(id_cliente);
CREATE INDEX IF NOT EXISTS idx_gr_transportista ON guias_remision_cabecera(id_transportista);
CREATE INDEX IF NOT EXISTS idx_gr_punto         ON guias_remision_cabecera(id_punto_emision);
CREATE INDEX IF NOT EXISTS idx_gr_estado        ON guias_remision_cabecera(estado);

-- 3. Detalle de guías (solo productos, sin precios)
CREATE TABLE IF NOT EXISTS guias_remision_detalle (
    id               SERIAL PRIMARY KEY,
    id_guia_remision INTEGER       NOT NULL,
    id_producto      INTEGER,
    codigo_principal VARCHAR(25),
    codigo_auxiliar  VARCHAR(25),
    descripcion      VARCHAR(300)  NOT NULL,
    cantidad         NUMERIC(18,6) NOT NULL DEFAULT 1,
    CONSTRAINT fk_gr_detalle_guia    FOREIGN KEY (id_guia_remision) REFERENCES guias_remision_cabecera(id) ON DELETE CASCADE,
    CONSTRAINT fk_gr_detalle_producto FOREIGN KEY (id_producto)     REFERENCES productos(id)
);

CREATE INDEX IF NOT EXISTS idx_gr_detalle_guia ON guias_remision_detalle(id_guia_remision);

-- 4. Información adicional (igual que ventas_adicional)
CREATE TABLE IF NOT EXISTS guias_remision_adicional (
    id               SERIAL PRIMARY KEY,
    id_guia_remision INTEGER      NOT NULL,
    nombre           VARCHAR(30)  NOT NULL,
    valor            VARCHAR(300) NOT NULL,
    CONSTRAINT fk_gr_adicional_guia FOREIGN KEY (id_guia_remision) REFERENCES guias_remision_cabecera(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_gr_adicional_guia ON guias_remision_adicional(id_guia_remision);
