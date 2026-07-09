-- =============================================================
-- MÓDULO: Importación de Comprobantes Electrónicos (XML)
-- Lee los XML autorizados del SRI desde el servidor (carpeta por RUC),
-- los valida y los registra reutilizando DocumentoAutomatedRegisterService.
-- Acceso: solo superadministrador (nivel 3).
-- =============================================================

-- -------------------------------------------------------------
-- Lote: cada escaneo/importación de una carpeta de RUC
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS importacion_xml_lote (
    id                  SERIAL PRIMARY KEY,
    id_empresa          INTEGER NOT NULL,
    ruc                 VARCHAR(20)  NOT NULL,          -- nombre de la carpeta origen
    ruta_base           VARCHAR(500),                   -- ruta escaneada en el servidor
    tipos_seleccionados VARCHAR(50),                    -- CSV de codDoc: '01,04,07'
    fecha_desde         DATE,                           -- filtro aplicado (opcional)
    fecha_hasta         DATE,                           -- filtro aplicado (opcional)
    estado              VARCHAR(20) NOT NULL DEFAULT 'escaneado'
                            CHECK (estado IN ('escaneado','importando','completado','con_errores','cancelado')),
    total_detectados    INTEGER NOT NULL DEFAULT 0,
    total_nuevos        INTEGER NOT NULL DEFAULT 0,
    total_importados    INTEGER NOT NULL DEFAULT 0,
    total_duplicados    INTEGER NOT NULL DEFAULT 0,
    total_errores       INTEGER NOT NULL DEFAULT 0,
    observaciones       TEXT,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by          INTEGER,
    updated_by          INTEGER,
    eliminado           BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at          TIMESTAMP,
    deleted_by          INTEGER,
    CONSTRAINT fk_impxml_lote_empresa FOREIGN KEY (id_empresa) REFERENCES empresas(id)
);
CREATE INDEX IF NOT EXISTS idx_impxml_lote_empresa ON importacion_xml_lote(id_empresa, eliminado, estado);

-- -------------------------------------------------------------
-- Ítem: un renglón por archivo XML detectado en la carpeta.
-- Es el "registro de lo ya descargado": la clave_acceso ya
-- importada se detecta en escaneos futuros y se omite.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS importacion_xml_item (
    id                     SERIAL PRIMARY KEY,
    id_lote                INTEGER REFERENCES importacion_xml_lote(id),
    id_empresa             INTEGER NOT NULL,
    archivo                VARCHAR(500) NOT NULL,       -- ruta/nombre relativo del XML
    clave_acceso           VARCHAR(49),                 -- clave de acceso del comprobante
    cod_doc                VARCHAR(2),                  -- 01,03,04,05,06,07
    ruc_emisor             VARCHAR(20),
    razon_social_emisor    VARCHAR(300),
    secuencial             VARCHAR(20),
    fecha_emision          DATE,
    total                  NUMERIC(14,2),
    es_emitido             BOOLEAN,                     -- true = venta (emitido por la empresa)
    estado                 VARCHAR(20) NOT NULL DEFAULT 'pendiente'
                               CHECK (estado IN ('pendiente','importado','duplicado','error','omitido','no_autorizado')),
    sri_estado             VARCHAR(30),                 -- estado devuelto por el SRI (AUTORIZADO/NO AUTORIZADO/RECHAZADA/…) si se verificó
    mensaje                TEXT,                        -- detalle de error/omisión
    id_documento_generado  INTEGER,                     -- id del documento creado (venta/compra/...)
    tabla_documento        VARCHAR(50),                 -- tabla destino del documento
    created_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by             INTEGER,
    updated_by             INTEGER,
    eliminado              BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at             TIMESTAMP,
    deleted_by             INTEGER,
    CONSTRAINT fk_impxml_item_lote FOREIGN KEY (id_lote) REFERENCES importacion_xml_lote(id)
);
CREATE INDEX IF NOT EXISTS idx_impxml_item_lote    ON importacion_xml_item(id_lote);
CREATE INDEX IF NOT EXISTS idx_impxml_item_estado  ON importacion_xml_item(id_empresa, estado, cod_doc);
CREATE INDEX IF NOT EXISTS idx_impxml_item_clave   ON importacion_xml_item(id_empresa, clave_acceso);

-- Candado anti-reproceso: la RUTA del archivo en el servidor es única y estable,
-- y se conoce en el escaneo SIN descargar el XML. Permite ON CONFLICT DO NOTHING
-- para no re-insertar ni re-importar lo ya registrado.
CREATE UNIQUE INDEX IF NOT EXISTS uq_impxml_item_archivo
    ON importacion_xml_item(id_empresa, archivo)
    WHERE eliminado = FALSE;

-- Idempotente: actualiza tablas ya desplegadas a la versión con verificación SRI.
ALTER TABLE importacion_xml_item ADD COLUMN IF NOT EXISTS sri_estado VARCHAR(30);
ALTER TABLE importacion_xml_item DROP CONSTRAINT IF EXISTS importacion_xml_item_estado_check;
ALTER TABLE importacion_xml_item ADD CONSTRAINT importacion_xml_item_estado_check
    CHECK (estado IN ('pendiente','importado','duplicado','error','omitido','no_autorizado'));
