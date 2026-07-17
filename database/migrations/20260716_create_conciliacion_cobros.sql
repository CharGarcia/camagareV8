-- ============================================================
-- Módulo: Conciliación de Cobros Bancarios (modulos/conciliacion-cobros)
-- Importa el estado de cuenta bancario (Excel/CSV o PDF) descargado del
-- banco, sugiere el cliente/factura de cada línea contra las facturas
-- pendientes de cobro (CxC) y genera Ingresos (ingresos_cabecera) en lote
-- tras la confirmación del usuario. No modifica ingresos_* existentes ni
-- ventas_cabecera: solo lee saldos pendientes y crea Ingresos nuevos con
-- la lógica ya existente de IngresoService::crear().
-- ============================================================

-- Perfil de mapeo de columnas: reutilizable por banco (no por usuario, ya
-- que el formato del extracto depende del banco, no de quién lo sube).
CREATE TABLE IF NOT EXISTS conciliacion_perfiles (
    id                 SERIAL PRIMARY KEY,
    id_empresa         INTEGER NOT NULL REFERENCES empresas(id),
    id_banco           INTEGER NULL REFERENCES bancos_ecuador(id),

    nombre_perfil      VARCHAR(150) NOT NULL,
    tipo_archivo       VARCHAR(10) NOT NULL CHECK (tipo_archivo IN ('EXCEL', 'PDF')),

    fila_inicio        SMALLINT NOT NULL DEFAULT 0,   -- filas de encabezado a saltar (solo EXCEL)
    formato_fecha      VARCHAR(20) NOT NULL DEFAULT 'd/m/Y',
    separador_decimal  VARCHAR(1) NOT NULL DEFAULT '.',
    mapeo_columnas     JSONB NOT NULL,                -- ver comentario de cabecera del módulo (§ plan)

    activo             BOOLEAN NOT NULL DEFAULT TRUE,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_by INTEGER,
    eliminado  BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at TIMESTAMP,
    deleted_by INTEGER
);

CREATE INDEX IF NOT EXISTS idx_concper_empresa ON conciliacion_perfiles(id_empresa, eliminado);

-- Cabecera de cada archivo de extracto subido.
CREATE TABLE IF NOT EXISTS conciliacion_cargas (
    id                 SERIAL PRIMARY KEY,
    id_empresa         INTEGER NOT NULL REFERENCES empresas(id),
    id_forma_pago      INTEGER NOT NULL REFERENCES empresa_formas_pago(id), -- cuenta bancaria que recibió el cobro
    id_punto_emision   INTEGER NOT NULL REFERENCES empresa_punto_emision(id), -- punto de emisión de los Ingresos a generar
    id_perfil          INTEGER NOT NULL REFERENCES conciliacion_perfiles(id),

    nombre_archivo     VARCHAR(255) NOT NULL,
    ruta_archivo       VARCHAR(500) NOT NULL,
    tipo_archivo       VARCHAR(10) NOT NULL CHECK (tipo_archivo IN ('EXCEL', 'PDF')),
    total_lineas       INTEGER NOT NULL DEFAULT 0,

    estado             VARCHAR(20) NOT NULL DEFAULT 'procesando'
        CHECK (estado IN ('procesando', 'pendiente_revision', 'completado', 'error')),
    mensaje_error      TEXT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_by INTEGER,
    eliminado  BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at TIMESTAMP,
    deleted_by INTEGER
);

CREATE INDEX IF NOT EXISTS idx_conccar_empresa ON conciliacion_cargas(id_empresa, eliminado);
CREATE INDEX IF NOT EXISTS idx_conccar_forma ON conciliacion_cargas(id_forma_pago);

-- Cada línea extraída del extracto, con su sugerencia y resultado de aplicación.
CREATE TABLE IF NOT EXISTS conciliacion_lineas (
    id                     SERIAL PRIMARY KEY,
    id_carga               INTEGER NOT NULL REFERENCES conciliacion_cargas(id),
    id_empresa             INTEGER NOT NULL REFERENCES empresas(id),

    fecha_movimiento       DATE NOT NULL,
    descripcion_original   TEXT NOT NULL,
    monto                  NUMERIC(14,2) NOT NULL,
    referencia_banco       VARCHAR(150) NULL,

    estado                 VARCHAR(20) NOT NULL DEFAULT 'SIN_MATCH'
        CHECK (estado IN ('SIN_MATCH', 'SUGERIDO', 'CONFIRMADO', 'IGNORADO', 'APLICADO', 'ERROR')),

    id_cliente_sugerido    INTEGER NULL REFERENCES clientes(id),
    score_match            NUMERIC(5,2) NULL,
    tipo_documento_sugerido VARCHAR(20) NULL CHECK (tipo_documento_sugerido IS NULL OR tipo_documento_sugerido IN ('FACTURA', 'SALDO_INICIAL', 'RECIBO')),
    id_documento_sugerido  INTEGER NULL,             -- id polimórfico (ventas_cabecera / saldos_iniciales_cxc / recibos_venta_cabecera)

    monto_aplicar          NUMERIC(14,2) NULL,       -- editable por el usuario; default = monto de la línea

    id_ingreso_generado    INTEGER NULL REFERENCES ingresos_cabecera(id),
    mensaje_error          TEXT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_by INTEGER,
    eliminado  BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at TIMESTAMP,
    deleted_by INTEGER
);

CREATE INDEX IF NOT EXISTS idx_concline_carga ON conciliacion_lineas(id_carga, eliminado);
CREATE INDEX IF NOT EXISTS idx_concline_empresa ON conciliacion_lineas(id_empresa, eliminado);
CREATE INDEX IF NOT EXISTS idx_concline_estado ON conciliacion_lineas(estado);
