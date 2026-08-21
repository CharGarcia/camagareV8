-- ============================================================================
-- Módulo: Conciliación de Tarjetas  (modulos/conciliacion-tarjetas)
--
-- Cruza el ESTADO DE CUENTA que emite la procesadora (Payphone, Nuvei, el banco
-- del datáfono…) contra los cobros con tarjeta que el sistema ya tiene
-- registrados, para responder tres preguntas:
--
--   1. ¿Qué cobros se depositaron de verdad?      → línea del estado ↔ cobro
--   2. ¿Qué cobros siguen sin depositarse?        → cobro sin línea (reaparece
--      en la siguiente conciliación, con contador de días)
--   3. ¿Qué entró al banco sin estar registrado?  → línea sin cobro (se reporta
--      como diferencia; este módulo NO emite documentos)
--
-- Al cerrar, el usuario elige la FORMA DE COBRO DESTINO (el banco al que llegó
-- el dinero) y, si la empresa lleva contabilidad, se genera el asiento:
--   Debe  Banco (neto) + comisión + IVA comisión + retenciones
--   Haber Cuenta puente de la forma de cobro de tarjeta (bruto)
-- La contabilidad es OPCIONAL: si la forma de cobro no tiene cuenta contable, o
-- si apunta a una cuenta bancaria en vez de a una cuenta puente, la conciliación
-- se registra igual y el motivo queda en cabecera.asiento_omitido_motivo.
--
-- NO se crean formas de pago nuevas ni se altera empresa_formas_pago: qué se
-- liquida en diferido se resuelve por su columna `tipo`
-- (PAYPHONE / NUVEI / TARJETA), igual que ya hacen FlujoCajaRepository y
-- SaldosInicialesRepository.
--
-- Idempotente: se puede correr varias veces.
-- ============================================================================

-- 1) Configuración contable por empresa + forma de cobro ----------------------
--    Una fila por procesadora. Solo hace falta si la empresa lleva contabilidad:
--    sin fila, el módulo concilia igual y omite el asiento.
--    La CUENTA PUENTE no se guarda aquí: es empresa_formas_pago.id_cuenta_contable
--    de la forma de tarjeta, que es la que ya usa AsientoBuilderService al
--    registrar el cobro. Una sola fuente de verdad.
CREATE TABLE IF NOT EXISTS conciliacion_tarjetas_config (
    id                        SERIAL PRIMARY KEY,
    id_empresa                INTEGER      NOT NULL,
    id_forma_cobro            INTEGER      NOT NULL,   -- forma tipo PAYPHONE/NUVEI/TARJETA
    -- Cuentas del reparto (todas opcionales)
    id_cuenta_comision        INTEGER,                 -- gasto por comisión de la procesadora
    id_cuenta_iva_comision    INTEGER,                 -- IVA crédito tributario de esa comisión
    id_cuenta_retencion_ir    INTEGER,                 -- IR que retiene la procesadora
    id_cuenta_retencion_iva   INTEGER,                 -- IVA que retiene la procesadora
    -- Valores por defecto para PRECALCULAR (siempre editables al conciliar).
    -- No se guardan porcentajes de retención: se digitan del comprobante, porque
    -- dependen de la normativa vigente y del tipo de contribuyente.
    porcentaje_comision       NUMERIC(6,4) NOT NULL DEFAULT 0,
    porcentaje_iva            NUMERIC(6,4) NOT NULL DEFAULT 0,
    dias_liquidacion          SMALLINT     NOT NULL DEFAULT 2,     -- alerta de atraso
    tolerancia_diferencia     NUMERIC(10,2) NOT NULL DEFAULT 0.05, -- descuadre aceptado al cerrar
    -- Auditoría estándar
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER,
    CONSTRAINT fk_ctcfg_empresa FOREIGN KEY (id_empresa)     REFERENCES empresas(id),
    CONSTRAINT fk_ctcfg_forma   FOREIGN KEY (id_forma_cobro) REFERENCES empresa_formas_pago(id)
);

CREATE UNIQUE INDEX IF NOT EXISTS uk_ctcfg_empresa_forma
    ON conciliacion_tarjetas_config (id_empresa, id_forma_cobro)
    WHERE eliminado = false;

-- 2) Perfiles de estado de cuenta --------------------------------------------
--    El formato del archivo cambia según la procesadora y el banco, así que el
--    usuario arma un perfil por cada uno con el asistente (mismo enfoque que
--    conciliacion_perfiles del módulo Conciliación de Cobros).
--
--    `nivel` distingue los dos tipos de reporte que puede entregar un proveedor:
--      'transaccion' → una línea por cobro (cruce 1 a 1)
--      'deposito'    → el neto consolidado que llega al banco (cruce N a 1)
--
--    mapeo_columnas (JSONB) admite, según tipo_archivo:
--      EXCEL/CSV → {"fecha":0,"autorizacion":2,"referencia":3,"monto_bruto":4,
--                   "comision":5,"iva_comision":6,"retencion_ir":7,
--                   "retencion_iva":8,"otros_descuentos":9,"monto_neto":10,
--                   "descripcion":1}
--      PDF       → {"regex_linea":"...","grupos":{"fecha":1,"autorizacion":2,...}}
--    Las claves ausentes simplemente no se leen (el importador las deja en 0/NULL).
CREATE TABLE IF NOT EXISTS conciliacion_tarjetas_perfiles (
    id                  SERIAL PRIMARY KEY,
    id_empresa          INTEGER      NOT NULL,
    id_forma_cobro      INTEGER,                  -- NULL = sirve para cualquier procesadora
    nombre_perfil       VARCHAR(100) NOT NULL,
    tipo_archivo        VARCHAR(10)  NOT NULL DEFAULT 'EXCEL'
                        CHECK (tipo_archivo IN ('EXCEL','CSV','PDF')),
    nivel               VARCHAR(15)  NOT NULL DEFAULT 'transaccion'
                        CHECK (nivel IN ('transaccion','deposito')),
    fila_inicio         SMALLINT     NOT NULL DEFAULT 0,
    formato_fecha       VARCHAR(20)  NOT NULL DEFAULT 'd/m/Y',
    separador_decimal   VARCHAR(1)   NOT NULL DEFAULT '.',
    mapeo_columnas      JSONB        NOT NULL DEFAULT '{}'::jsonb,
    activo              BOOLEAN      NOT NULL DEFAULT TRUE,
    -- Auditoría estándar
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER,
    CONSTRAINT fk_ctperf_empresa FOREIGN KEY (id_empresa)     REFERENCES empresas(id),
    CONSTRAINT fk_ctperf_forma   FOREIGN KEY (id_forma_cobro) REFERENCES empresa_formas_pago(id)
);

CREATE INDEX IF NOT EXISTS idx_ctperf_empresa ON conciliacion_tarjetas_perfiles (id_empresa, eliminado);

-- 3) Cabecera: una sesión de conciliación ------------------------------------
CREATE TABLE IF NOT EXISTS conciliacion_tarjetas_cabecera (
    id                      SERIAL PRIMARY KEY,
    id_empresa              INTEGER      NOT NULL,
    secuencial              INTEGER      NOT NULL,
    numero                  VARCHAR(20)  NOT NULL,      -- CT-000001
    -- Qué se concilia y a dónde va el dinero
    id_forma_cobro          INTEGER      NOT NULL,      -- procesadora (PAYPHONE/NUVEI/TARJETA)
    id_forma_cobro_destino  INTEGER,                    -- banco elegido por el usuario (NULL hasta cerrar)
    id_perfil               INTEGER,                    -- NULL si las líneas se digitaron a mano
    -- Período conciliado
    fecha_desde             DATE,
    fecha_hasta             DATE,
    fecha_conciliacion      DATE         NOT NULL DEFAULT CURRENT_DATE,
    -- Archivo cargado
    nombre_archivo          VARCHAR(255),
    ruta_archivo            VARCHAR(255),
    tipo_archivo            VARCHAR(10),
    -- Totales (se recalculan desde las líneas y los cruces al guardar)
    total_lineas            INTEGER       NOT NULL DEFAULT 0,
    total_bruto_estado      NUMERIC(14,2) NOT NULL DEFAULT 0,  -- suma del estado de cuenta
    total_bruto_cruzado     NUMERIC(14,2) NOT NULL DEFAULT 0,  -- suma de los cobros cruzados
    total_comision          NUMERIC(14,2) NOT NULL DEFAULT 0,
    total_iva_comision      NUMERIC(14,2) NOT NULL DEFAULT 0,
    total_retencion_ir      NUMERIC(14,2) NOT NULL DEFAULT 0,
    total_retencion_iva     NUMERIC(14,2) NOT NULL DEFAULT 0,
    total_otros             NUMERIC(14,2) NOT NULL DEFAULT 0,
    total_neto              NUMERIC(14,2) NOT NULL DEFAULT 0,  -- bruto − descuentos (calculado)
    neto_depositado         NUMERIC(14,2) NOT NULL DEFAULT 0,  -- lo que realmente entró al banco
    diferencia              NUMERIC(14,2) NOT NULL DEFAULT 0,  -- neto_depositado − total_neto
    estado                  VARCHAR(20)   NOT NULL DEFAULT 'borrador'
                            CHECK (estado IN ('borrador','cerrada','anulada')),
    -- Contabilidad (opcional)
    id_asiento_contable     INTEGER,
    asiento_omitido_motivo  TEXT,        -- por qué no se generó asiento, si no se generó
    observaciones           TEXT,
    tipo_ambiente           VARCHAR(1)   NOT NULL DEFAULT '1',
    -- Auditoría estándar
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER,
    CONSTRAINT fk_ctcab_empresa  FOREIGN KEY (id_empresa)             REFERENCES empresas(id),
    CONSTRAINT fk_ctcab_forma    FOREIGN KEY (id_forma_cobro)         REFERENCES empresa_formas_pago(id),
    CONSTRAINT fk_ctcab_destino  FOREIGN KEY (id_forma_cobro_destino) REFERENCES empresa_formas_pago(id),
    CONSTRAINT fk_ctcab_perfil   FOREIGN KEY (id_perfil)              REFERENCES conciliacion_tarjetas_perfiles(id)
);

CREATE INDEX IF NOT EXISTS idx_ctcab_empresa ON conciliacion_tarjetas_cabecera (id_empresa, eliminado);
CREATE INDEX IF NOT EXISTS idx_ctcab_forma   ON conciliacion_tarjetas_cabecera (id_forma_cobro);
CREATE INDEX IF NOT EXISTS idx_ctcab_fecha   ON conciliacion_tarjetas_cabecera (fecha_conciliacion);

-- Numeración única por empresa + ambiente (el correlativo se serializa con
-- pg_advisory_xact_lock en el repository; este índice es la red de seguridad).
CREATE UNIQUE INDEX IF NOT EXISTS uk_ctcab_secuencial
    ON conciliacion_tarjetas_cabecera (id_empresa, tipo_ambiente, secuencial)
    WHERE eliminado = false;

-- 4) Líneas del estado de cuenta ---------------------------------------------
CREATE TABLE IF NOT EXISTS conciliacion_tarjetas_lineas (
    id                  SERIAL PRIMARY KEY,
    id_empresa          INTEGER      NOT NULL,
    id_cabecera         INTEGER      NOT NULL,
    fecha_movimiento    DATE,
    tipo_linea          VARCHAR(15)  NOT NULL DEFAULT 'transaccion'
                        CHECK (tipo_linea IN ('transaccion','deposito')),
    autorizacion        VARCHAR(60),      -- la llave de cruce preferida
    referencia          VARCHAR(120),
    descripcion         TEXT,
    monto_bruto         NUMERIC(14,2) NOT NULL DEFAULT 0,
    comision            NUMERIC(14,2) NOT NULL DEFAULT 0,
    iva_comision        NUMERIC(14,2) NOT NULL DEFAULT 0,
    retencion_ir        NUMERIC(14,2) NOT NULL DEFAULT 0,
    retencion_iva       NUMERIC(14,2) NOT NULL DEFAULT 0,
    otros_descuentos    NUMERIC(14,2) NOT NULL DEFAULT 0,
    monto_neto          NUMERIC(14,2) NOT NULL DEFAULT 0,
    -- 'pendiente'  → aún sin cruzar
    -- 'cruzada'    → emparejada con uno o más cobros
    -- 'sin_cobro'  → el usuario la marcó como diferencia: entró plata sin documento
    estado              VARCHAR(15)  NOT NULL DEFAULT 'pendiente'
                        CHECK (estado IN ('pendiente','cruzada','sin_cobro')),
    origen              VARCHAR(10)  NOT NULL DEFAULT 'archivo'
                        CHECK (origen IN ('archivo','manual')),
    linea_cruda         TEXT,          -- texto original, para auditar el parseo
    observaciones       TEXT,
    -- Auditoría estándar
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER,
    CONSTRAINT fk_ctlin_cab     FOREIGN KEY (id_cabecera) REFERENCES conciliacion_tarjetas_cabecera(id) ON DELETE CASCADE,
    CONSTRAINT fk_ctlin_empresa FOREIGN KEY (id_empresa)  REFERENCES empresas(id)
);

CREATE INDEX IF NOT EXISTS idx_ctlin_cab    ON conciliacion_tarjetas_lineas (id_cabecera, eliminado);
CREATE INDEX IF NOT EXISTS idx_ctlin_autor  ON conciliacion_tarjetas_lineas (id_empresa, autorizacion);
CREATE INDEX IF NOT EXISTS idx_ctlin_estado ON conciliacion_tarjetas_lineas (estado);

-- 5) Cruces: línea del estado de cuenta ↔ cobro del sistema -------------------
--    Tabla propia (y no una columna en la línea) porque un depósito consolidado
--    cubre VARIOS cobros: la relación es N a 1.
--    El cobro se identifica por ingresos_pagos.id — la línea exacta del ingreso
--    que se pagó con esa tarjeta.
CREATE TABLE IF NOT EXISTS conciliacion_tarjetas_cruces (
    id                  SERIAL PRIMARY KEY,
    id_empresa          INTEGER      NOT NULL,
    id_cabecera         INTEGER      NOT NULL,
    id_linea            INTEGER      NOT NULL,
    id_ingreso_pago     INTEGER      NOT NULL,   -- ingresos_pagos.id
    id_ingreso          INTEGER      NOT NULL,   -- ingresos_cabecera.id (denormalizado)
    monto_cruzado       NUMERIC(14,2) NOT NULL DEFAULT 0,
    origen              VARCHAR(10)  NOT NULL DEFAULT 'manual'
                        CHECK (origen IN ('auto','manual')),
    score               NUMERIC(5,2),            -- confianza de la sugerencia automática
    criterio            VARCHAR(30),             -- autorizacion | referencia | monto_fecha
    -- Auditoría estándar
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER,
    CONSTRAINT fk_ctcru_cab     FOREIGN KEY (id_cabecera) REFERENCES conciliacion_tarjetas_cabecera(id) ON DELETE CASCADE,
    CONSTRAINT fk_ctcru_linea   FOREIGN KEY (id_linea)    REFERENCES conciliacion_tarjetas_lineas(id) ON DELETE CASCADE,
    CONSTRAINT fk_ctcru_empresa FOREIGN KEY (id_empresa)  REFERENCES empresas(id)
);

CREATE INDEX IF NOT EXISTS idx_ctcru_cab   ON conciliacion_tarjetas_cruces (id_cabecera, eliminado);
CREATE INDEX IF NOT EXISTS idx_ctcru_linea ON conciliacion_tarjetas_cruces (id_linea);

-- Un cobro no puede conciliarse dos veces, ni dentro de la misma sesión ni en
-- otra. Al anular una conciliación sus cruces quedan eliminado = true y el cobro
-- vuelve a aparecer como pendiente.
CREATE UNIQUE INDEX IF NOT EXISTS uk_ctcru_ingreso_pago
    ON conciliacion_tarjetas_cruces (id_ingreso_pago)
    WHERE eliminado = false;

COMMENT ON TABLE conciliacion_tarjetas_config   IS 'Cuentas y valores por defecto para conciliar cada procesadora de tarjeta. Opcional: sin fila, el módulo concilia sin generar asiento.';
COMMENT ON TABLE conciliacion_tarjetas_perfiles IS 'Perfiles de lectura del estado de cuenta de la procesadora (Excel/CSV/PDF). El formato cambia por proveedor y banco, por eso es configurable.';
COMMENT ON TABLE conciliacion_tarjetas_cabecera IS 'Sesión de conciliación: procesadora, período, banco destino y totales del cruce.';
COMMENT ON TABLE conciliacion_tarjetas_lineas   IS 'Líneas del estado de cuenta de la procesadora, importadas o digitadas.';
COMMENT ON TABLE conciliacion_tarjetas_cruces   IS 'Emparejamiento entre una línea del estado de cuenta y el cobro (ingresos_pagos) que le corresponde. N a 1 en depósitos consolidados.';
