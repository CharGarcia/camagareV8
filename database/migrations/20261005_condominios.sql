-- ============================================================================
-- Módulo Condominios (modulos/condominios) — fase 1.
--
-- Administra las expensas de un condominio reutilizando lo que ya existe: la emisión
-- mensual la hace Suscripciones, el cobro Ingresos, la cartera Cuentas por Cobrar y el
-- presupuesto Presupuestos. Este módulo aporta las UNIDADES (inmuebles), las alícuotas,
-- el fondo de reserva y otros aportes, intereses y multas, la restricción de áreas
-- comunes y la liquidación para cobro judicial. Diseño: docs/diseno/condominios-fase1.md.
--
-- Los conceptos que se cobran (alícuota, fondo, intereses, multas, aportes) son
-- productos/servicios que el usuario crea en el módulo Productos; aquí solo se guardan
-- sus id. No se crean productos ni tipos de asiento.
--
-- Tablas (todas operativas: id_empresa + auditoría + eliminación lógica, salvo la global):
--   condominios_config              una fila por empresa; guardarla activa el módulo
--   condominios_unidades            inmuebles: departamento, local, parqueadero, bodega…
--   condominios_unidades_propietarios  historial de propietarios/arrendatarios por unidad
--   condominios_alicuotas_valores   tarifa por m² / monto a repartir que rige desde un mes
--   condominios_aportes             fondo de reserva (permanente) y otros aportes (con meta)
--   condominios_aportes_unidades    plan de cada unidad en un aporte con meta
--   condominios_cargos              cada concepto cobrado a una unidad en un período
--   condominios_multas_catalogo     multas del reglamento interno
--   condominios_restricciones_log   marcar / quitar restricción de áreas comunes
--   tasas_interes_legal             GLOBAL (sin id_empresa): tasa legal de mora por fecha
-- Columnas nuevas (nullable, no cambian el comportamiento actual):
--   suscripciones.id_unidad, suscripciones_detalle.id_cargo
--
-- Idempotente. Listo para pgAdmin (F5).
-- ============================================================================

BEGIN;

-- ─── Configuración del condominio (una fila por empresa) ─────────────────────────────
CREATE TABLE IF NOT EXISTS condominios_config (
    id                          SERIAL PRIMARY KEY,
    id_empresa                  INTEGER       NOT NULL UNIQUE,
    nombre_condominio           VARCHAR(200),
    direccion                   VARCHAR(300),
    -- Firma de la liquidación judicial (art. 13 LPH): administrador obligatorio, presidente opcional
    administrador_nombre        VARCHAR(150)  NOT NULL,
    administrador_cedula        VARCHAR(20),
    administrador_cargo         VARCHAR(100)  NOT NULL DEFAULT 'Administrador/a',
    presidente_nombre           VARCHAR(150),
    presidente_cedula           VARCHAR(20),
    -- La emisión (comprobante, serie, periodicidad, día de cobro, correo) la define cada
    -- suscripción en el módulo Suscripciones; aquí solo lo que Suscripciones no sabe.
    dias_gracia                 SMALLINT      NOT NULL DEFAULT 0,            -- tras el vencimiento, antes de cobrar interés
    -- Productos (servicios creados en el módulo Productos) con los que se factura cada concepto
    id_producto_ordinaria       INTEGER       NOT NULL,
    id_producto_fondo           INTEGER,                                     -- obligatorio si hay fondo
    id_producto_interes         INTEGER,                                     -- obligatorio si cobra intereses
    -- Alícuota ordinaria
    metodo_alicuota             VARCHAR(12)   NOT NULL DEFAULT 'porcentaje', -- porcentaje | m2 | manual
    reparto_manuales            VARCHAR(15)   NOT NULL DEFAULT 'repartir_resto', -- repartir_resto | aparte (solo con presupuesto)
    -- Fondo de reserva (aporte permanente, línea separada)
    fondo_reserva_tipo          VARCHAR(10)   NOT NULL DEFAULT 'no',         -- no | porcentaje | fijo
    fondo_reserva_valor         NUMERIC(14,2) NOT NULL DEFAULT 0,            -- % sobre la ordinaria o monto
    -- Intereses de mora (apagado por defecto)
    cobra_intereses             BOOLEAN       NOT NULL DEFAULT false,
    interes_tipo                VARCHAR(6)    NOT NULL DEFAULT 'legal',      -- legal | fijo
    interes_tasa_mensual        NUMERIC(8,4)  NOT NULL DEFAULT 0,            -- % mensual si es fijo
    interes_destino             VARCHAR(16)   NOT NULL DEFAULT 'siguiente_recibo', -- siguiente_recibo | recibo_aparte
    -- Multas (apagado por defecto)
    cobra_multas                BOOLEAN       NOT NULL DEFAULT false,
    -- Descuentos (apagados por defecto)
    pronto_pago_activo          BOOLEAN       NOT NULL DEFAULT false,
    pronto_pago_pct             NUMERIC(5,2)  NOT NULL DEFAULT 0,
    pronto_pago_dia             SMALLINT      NOT NULL DEFAULT 5,
    anticipado_activo           BOOLEAN       NOT NULL DEFAULT false,
    anticipado_pct              NUMERIC(5,2)  NOT NULL DEFAULT 0,
    anticipado_meses_min        SMALLINT      NOT NULL DEFAULT 12,
    -- Restricción de áreas comunes automática (apagada por defecto)
    restriccion_auto            BOOLEAN       NOT NULL DEFAULT false,
    restriccion_meses           SMALLINT      NOT NULL DEFAULT 3,
    -- Liquidación judicial: expensas vencidas mínimas para habilitarla
    liquidacion_min_vencidas    SMALLINT      NOT NULL DEFAULT 1,
    observaciones               TEXT,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN   NOT NULL DEFAULT false,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER,
    CONSTRAINT chk_cond_cfg_gracia      CHECK (dias_gracia BETWEEN 0 AND 60),
    CONSTRAINT chk_cond_cfg_metodo      CHECK (metodo_alicuota IN ('porcentaje', 'm2', 'manual')),
    CONSTRAINT chk_cond_cfg_reparto     CHECK (reparto_manuales IN ('repartir_resto', 'aparte')),
    CONSTRAINT chk_cond_cfg_fondo_tipo  CHECK (fondo_reserva_tipo IN ('no', 'porcentaje', 'fijo')),
    CONSTRAINT chk_cond_cfg_fondo_prod  CHECK (fondo_reserva_tipo = 'no' OR id_producto_fondo IS NOT NULL),
    CONSTRAINT chk_cond_cfg_int_tipo    CHECK (interes_tipo IN ('legal', 'fijo')),
    CONSTRAINT chk_cond_cfg_int_destino CHECK (interes_destino IN ('siguiente_recibo', 'recibo_aparte')),
    CONSTRAINT chk_cond_cfg_int_prod    CHECK (cobra_intereses = false OR id_producto_interes IS NOT NULL),
    CONSTRAINT chk_cond_cfg_pp_dia      CHECK (pronto_pago_dia BETWEEN 1 AND 28),
    CONSTRAINT chk_cond_cfg_restr_meses CHECK (restriccion_meses BETWEEN 1 AND 24)
);

-- ─── Unidades (inmuebles) ───────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS condominios_unidades (
    id                          SERIAL PRIMARY KEY,
    id_empresa                  INTEGER       NOT NULL,
    codigo                      VARCHAR(30)   NOT NULL,                      -- 'DPTO-302', 'P-12'
    nombre                      VARCHAR(120)  NOT NULL,                      -- 'Dpto 302'
    tipo                        VARCHAR(15)   NOT NULL DEFAULT 'departamento', -- departamento | local | oficina | parqueadero | bodega | casa | otro
    torre_bloque                VARCHAR(60),
    piso                        VARCHAR(20),
    area_m2                     NUMERIC(10,2) NOT NULL DEFAULT 0,
    alicuota_pct                NUMERIC(9,6)  NOT NULL DEFAULT 0,            -- % de la escritura
    -- Personas (clientes). El propietario siempre; el pagador decide a nombre de quién sale el documento
    id_propietario              INTEGER       NOT NULL,
    id_arrendatario             INTEGER,
    pagador                     VARCHAR(12)   NOT NULL DEFAULT 'propietario', -- propietario | arrendatario
    -- Cómo se calcula la cuota de esta unidad (null = regla del condominio). El comprobante,
    -- la serie y el día de cobro viven en su suscripción (módulo Suscripciones).
    metodo_alicuota             VARCHAR(12),                                 -- porcentaje | m2 | manual | null
    monto_manual                NUMERIC(14,2),                               -- si método manual
    fondo_reserva_valor_propio  NUMERIC(14,2),                               -- null = regla del condominio
    -- La suscripción que emite la expensa mensual de esta unidad (la crea y mantiene el módulo)
    id_suscripcion              INTEGER,
    -- Restricción de áreas comunes
    restringida                 BOOLEAN       NOT NULL DEFAULT false,
    restringida_desde           DATE,
    restringida_motivo          VARCHAR(300),
    restringida_por             INTEGER,
    estado                      VARCHAR(10)   NOT NULL DEFAULT 'activo',     -- activo | inactivo (inactivo no emite)
    observaciones               TEXT,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN   NOT NULL DEFAULT false,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER,
    CONSTRAINT chk_cond_uni_tipo        CHECK (tipo IN ('departamento', 'local', 'oficina', 'parqueadero', 'bodega', 'casa', 'otro')),
    CONSTRAINT chk_cond_uni_pagador     CHECK (pagador IN ('propietario', 'arrendatario')),
    CONSTRAINT chk_cond_uni_pagador_arr CHECK (pagador = 'propietario' OR id_arrendatario IS NOT NULL),
    CONSTRAINT chk_cond_uni_metodo      CHECK (metodo_alicuota IS NULL OR metodo_alicuota IN ('porcentaje', 'm2', 'manual')),
    CONSTRAINT chk_cond_uni_alicuota    CHECK (alicuota_pct >= 0 AND alicuota_pct <= 100),
    CONSTRAINT chk_cond_uni_area        CHECK (area_m2 >= 0),
    CONSTRAINT chk_cond_uni_estado      CHECK (estado IN ('activo', 'inactivo'))
);
-- Código único por empresa entre las no eliminadas (permite reutilizar el código de una eliminada)
CREATE UNIQUE INDEX IF NOT EXISTS uq_cond_uni_empresa_codigo ON condominios_unidades (id_empresa, codigo) WHERE eliminado = false;
CREATE INDEX IF NOT EXISTS idx_cond_uni_empresa      ON condominios_unidades (id_empresa, eliminado);
CREATE INDEX IF NOT EXISTS idx_cond_uni_propietario  ON condominios_unidades (id_propietario) WHERE eliminado = false;
CREATE INDEX IF NOT EXISTS idx_cond_uni_arrendatario ON condominios_unidades (id_arrendatario) WHERE eliminado = false AND id_arrendatario IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_cond_uni_suscripcion  ON condominios_unidades (id_suscripcion) WHERE id_suscripcion IS NOT NULL;

-- Historial de propietarios y arrendatarios: la deuda es del inmueble, no de la persona.
-- Cada cambio de propietario/arrendatario/pagador cierra la fila vigente (hasta) y abre otra.
CREATE TABLE IF NOT EXISTS condominios_unidades_propietarios (
    id              SERIAL PRIMARY KEY,
    id_empresa      INTEGER     NOT NULL,
    id_unidad       INTEGER     NOT NULL REFERENCES condominios_unidades(id),
    id_propietario  INTEGER     NOT NULL,
    id_arrendatario INTEGER,
    pagador         VARCHAR(12) NOT NULL DEFAULT 'propietario',
    desde           DATE        NOT NULL,
    hasta           DATE,                                                    -- null = vigente
    observacion     VARCHAR(300),
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN   NOT NULL DEFAULT false,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER,
    CONSTRAINT chk_cond_uprop_pagador CHECK (pagador IN ('propietario', 'arrendatario')),
    CONSTRAINT chk_cond_uprop_fechas  CHECK (hasta IS NULL OR hasta >= desde)
);
CREATE INDEX IF NOT EXISTS idx_cond_uprop_unidad ON condominios_unidades_propietarios (id_unidad, desde) WHERE eliminado = false;

-- ─── Valores que rigen para la alícuota ordinaria (historial por mes) ──────────────
-- La cuota de un mes se calcula con la fila vigente en ese mes (la última con vigente_desde <= mes).
-- Cambiar un valor = insertar una fila nueva; nunca se edita la anterior.
CREATE TABLE IF NOT EXISTS condominios_alicuotas_valores (
    id                      SERIAL PRIMARY KEY,
    id_empresa              INTEGER        NOT NULL,
    vigente_desde           DATE           NOT NULL,                        -- primer día de un mes
    tarifa_m2               NUMERIC(10,4)  NOT NULL DEFAULT 0,              -- método m2
    monto_a_repartir        NUMERIC(14,2)  NOT NULL DEFAULT 0,              -- método porcentaje (manual o desde presupuesto)
    id_presupuesto          INTEGER,                                        -- si la base es un presupuesto aprobado
    id_presupuesto_version  INTEGER,
    acta                    VARCHAR(120),
    observacion             TEXT,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN   NOT NULL DEFAULT false,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER,
    CONSTRAINT chk_cond_val_mes CHECK (vigente_desde = date_trunc('month', vigente_desde)::date)
);
CREATE UNIQUE INDEX IF NOT EXISTS uq_cond_val_empresa_desde ON condominios_alicuotas_valores (id_empresa, vigente_desde) WHERE eliminado = false;

-- ─── Fondo de reserva y otros aportes ──────────────────────────────────────────────
-- tipo 'permanente' = fondo de reserva (una sola fila por empresa, la administra la configuración);
-- tipo 'meta' = otros aportes: siempre con un monto total a recaudar y plazos por unidad.
CREATE TABLE IF NOT EXISTS condominios_aportes (
    id                          SERIAL PRIMARY KEY,
    id_empresa                  INTEGER       NOT NULL,
    nombre                      VARCHAR(150)  NOT NULL,                     -- nombre interno
    descripcion                 TEXT,
    id_producto                 INTEGER       NOT NULL,                     -- servicio creado en Productos; su nombre sale en el recibo
    tipo                        VARCHAR(10)   NOT NULL DEFAULT 'meta',      -- permanente | meta
    calculo                     VARCHAR(14)   NOT NULL DEFAULT 'porcentaje',-- pct_alicuota | fijo | porcentaje | m2 | manual
    valor                       NUMERIC(14,4) NOT NULL DEFAULT 0,           -- % o monto según el cálculo
    meta_total                  NUMERIC(14,2),                              -- solo tipo meta
    vigente_desde               DATE          NOT NULL,
    vigente_hasta               DATE,
    plazos_permitidos           JSONB         NOT NULL DEFAULT '[1]'::jsonb,       -- [1,6,12,24]
    recargos_plazo              JSONB         NOT NULL DEFAULT '{}'::jsonb,        -- {"12":{"tipo":"pct","valor":4},"24":{"tipo":"valor","valor":35}}
    beneficio_anticipado_tipo   VARCHAR(5),                                 -- pct | valor | null
    beneficio_anticipado_valor  NUMERIC(14,2) NOT NULL DEFAULT 0,
    estado                      VARCHAR(10)   NOT NULL DEFAULT 'activo',    -- activo | cerrado
    observaciones               TEXT,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN   NOT NULL DEFAULT false,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER,
    CONSTRAINT chk_cond_ap_tipo      CHECK (tipo IN ('permanente', 'meta')),
    CONSTRAINT chk_cond_ap_calculo   CHECK (calculo IN ('pct_alicuota', 'fijo', 'porcentaje', 'm2', 'manual')),
    CONSTRAINT chk_cond_ap_meta      CHECK (tipo = 'permanente' OR (meta_total IS NOT NULL AND meta_total > 0)),
    CONSTRAINT chk_cond_ap_benef     CHECK (beneficio_anticipado_tipo IS NULL OR beneficio_anticipado_tipo IN ('pct', 'valor')),
    CONSTRAINT chk_cond_ap_estado    CHECK (estado IN ('activo', 'cerrado')),
    CONSTRAINT chk_cond_ap_fechas    CHECK (vigente_hasta IS NULL OR vigente_hasta >= vigente_desde)
);
CREATE INDEX IF NOT EXISTS idx_cond_ap_empresa ON condominios_aportes (id_empresa, estado) WHERE eliminado = false;

-- Plan de cada unidad dentro de un aporte con meta
CREATE TABLE IF NOT EXISTS condominios_aportes_unidades (
    id                  SERIAL PRIMARY KEY,
    id_empresa          INTEGER       NOT NULL,
    id_aporte           INTEGER       NOT NULL REFERENCES condominios_aportes(id),
    id_unidad           INTEGER       NOT NULL REFERENCES condominios_unidades(id),
    monto_asignado      NUMERIC(14,2) NOT NULL DEFAULT 0,                   -- lo que le toca por su alícuota/m²/manual
    plazo_elegido       SMALLINT      NOT NULL DEFAULT 1,                   -- cuotas mensuales
    recargo_aplicado    NUMERIC(14,2) NOT NULL DEFAULT 0,
    monto_total         NUMERIC(14,2) NOT NULL DEFAULT 0,                   -- asignado + recargo
    primer_periodo      CHAR(6),                                            -- AAAAMM de la primera cuota
    estado              VARCHAR(10)   NOT NULL DEFAULT 'pendiente',         -- pendiente | en_curso | pagado | cancelado
    observacion         VARCHAR(300),
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN   NOT NULL DEFAULT false,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER,
    CONSTRAINT chk_cond_apu_plazo  CHECK (plazo_elegido BETWEEN 1 AND 120),
    CONSTRAINT chk_cond_apu_estado CHECK (estado IN ('pendiente', 'en_curso', 'pagado', 'cancelado'))
);
CREATE UNIQUE INDEX IF NOT EXISTS uq_cond_apu_aporte_unidad ON condominios_aportes_unidades (id_aporte, id_unidad) WHERE eliminado = false;
CREATE INDEX IF NOT EXISTS idx_cond_apu_unidad ON condominios_aportes_unidades (id_unidad) WHERE eliminado = false;

-- ─── Cargos: cada concepto que se cobra a una unidad en un período ─────────────────
-- Pieza central: lo que el módulo decide cobrar, independiente del documento en que salga.
-- Se vuelca como líneas en la suscripción de la unidad; al emitirse, guarda en qué documento
-- y en qué línea quedó. El enlace documento ↔ unidad vive aquí (no en las cabeceras de venta).
CREATE TABLE IF NOT EXISTS condominios_cargos (
    id                      SERIAL PRIMARY KEY,
    id_empresa              INTEGER       NOT NULL,
    id_unidad               INTEGER       NOT NULL REFERENCES condominios_unidades(id),
    periodo                 CHAR(6)       NOT NULL,                         -- AAAAMM al que corresponde
    concepto                VARCHAR(15)   NOT NULL,                         -- ordinaria | fondo_reserva | aporte | interes | multa | descuento
    id_aporte               INTEGER REFERENCES condominios_aportes(id),
    id_aporte_unidad        INTEGER REFERENCES condominios_aportes_unidades(id),
    id_multa_catalogo       INTEGER,
    id_producto             INTEGER       NOT NULL,                         -- producto elegido para el concepto
    descripcion             VARCHAR(300)  NOT NULL,                         -- texto de la línea del documento
    monto                   NUMERIC(14,2) NOT NULL DEFAULT 0,
    -- Solo intereses: para explicar el cálculo en el estado de cuenta
    interes_base            NUMERIC(14,2),
    interes_tasa_mensual    NUMERIC(8,4),
    interes_dias            INTEGER,
    interes_desde           DATE,
    interes_hasta           DATE,
    id_documento_interes    INTEGER,                                        -- documento vencido que generó el interés
    tipo_documento_interes  VARCHAR(10),                                    -- recibo | factura
    -- Dónde salió
    estado                  VARCHAR(10)   NOT NULL DEFAULT 'pendiente',     -- pendiente | emitido | anulado
    tipo_documento          VARCHAR(10),                                    -- recibo | factura
    id_documento            INTEGER,
    id_documento_detalle    INTEGER,
    fecha_emision           DATE,
    fecha_vencimiento       DATE,
    origen                  VARCHAR(12)   NOT NULL DEFAULT 'automatico',    -- automatico | manual
    observacion             VARCHAR(300),
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN   NOT NULL DEFAULT false,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER,
    CONSTRAINT chk_cond_car_periodo  CHECK (periodo ~ '^[0-9]{6}$'),
    CONSTRAINT chk_cond_car_concepto CHECK (concepto IN ('ordinaria', 'fondo_reserva', 'aporte', 'interes', 'multa', 'descuento')),
    CONSTRAINT chk_cond_car_estado   CHECK (estado IN ('pendiente', 'emitido', 'anulado')),
    CONSTRAINT chk_cond_car_tipo_doc CHECK (tipo_documento IS NULL OR tipo_documento IN ('recibo', 'factura')),
    CONSTRAINT chk_cond_car_origen   CHECK (origen IN ('automatico', 'manual'))
);
CREATE INDEX IF NOT EXISTS idx_cond_car_unidad_periodo ON condominios_cargos (id_unidad, periodo) WHERE eliminado = false;
CREATE INDEX IF NOT EXISTS idx_cond_car_empresa_periodo ON condominios_cargos (id_empresa, periodo, estado) WHERE eliminado = false;
CREATE INDEX IF NOT EXISTS idx_cond_car_documento ON condominios_cargos (tipo_documento, id_documento) WHERE id_documento IS NOT NULL;
-- Un solo cargo ordinario y un solo fondo por unidad y período (los demás conceptos pueden repetirse)
CREATE UNIQUE INDEX IF NOT EXISTS uq_cond_car_unico_mes ON condominios_cargos (id_unidad, periodo, concepto)
    WHERE eliminado = false AND estado <> 'anulado' AND concepto IN ('ordinaria', 'fondo_reserva');

-- ─── Catálogo de multas del reglamento ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS condominios_multas_catalogo (
    id          SERIAL PRIMARY KEY,
    id_empresa  INTEGER       NOT NULL,
    nombre      VARCHAR(150)  NOT NULL,
    descripcion TEXT,
    valor       NUMERIC(14,2) NOT NULL DEFAULT 0,
    id_producto INTEGER       NOT NULL,                                     -- servicio con el que se factura la multa
    estado      VARCHAR(10)   NOT NULL DEFAULT 'activo',
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN   NOT NULL DEFAULT false,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER,
    CONSTRAINT chk_cond_multa_estado CHECK (estado IN ('activo', 'inactivo'))
);
CREATE INDEX IF NOT EXISTS idx_cond_multa_empresa ON condominios_multas_catalogo (id_empresa) WHERE eliminado = false;

-- ─── Restricción de áreas comunes: bitácora ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS condominios_restricciones_log (
    id          SERIAL PRIMARY KEY,
    id_empresa  INTEGER      NOT NULL,
    id_unidad   INTEGER      NOT NULL REFERENCES condominios_unidades(id),
    accion      VARCHAR(10)  NOT NULL,                                      -- marcar | quitar
    fecha       DATE         NOT NULL,                                      -- fecha de notificación / levantamiento
    motivo      VARCHAR(300),
    automatico  BOOLEAN      NOT NULL DEFAULT false,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by  INTEGER,
    CONSTRAINT chk_cond_restr_accion CHECK (accion IN ('marcar', 'quitar'))
);
CREATE INDEX IF NOT EXISTS idx_cond_restr_unidad ON condominios_restricciones_log (id_unidad, fecha);

-- ─── Tasa legal de interés de mora (GLOBAL: sin id_empresa) ────────────────────────
-- La mantiene el superadministrador desde /config con el valor publicado (BCE). Los
-- condominios con interes_tipo = 'legal' toman la fila vigente a la fecha del cargo; la tasa
-- aplicada queda copiada en cada cargo, así un cambio posterior no altera lo ya calculado.
CREATE TABLE IF NOT EXISTS tasas_interes_legal (
    id             SERIAL PRIMARY KEY,
    vigente_desde  DATE          NOT NULL UNIQUE,
    tasa_mensual   NUMERIC(8,4)  NOT NULL,                                  -- % mensual
    tasa_anual     NUMERIC(8,4),                                            -- referencia publicada
    fuente         VARCHAR(200),
    observacion    VARCHAR(300),
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN   NOT NULL DEFAULT false,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER
);

-- ─── Columnas nuevas en tablas existentes (nullable; no cambian el comportamiento) ──
-- La suscripción de una unidad la administra Condominios: sus líneas se arman cada mes.
ALTER TABLE suscripciones         ADD COLUMN IF NOT EXISTS id_unidad INTEGER;
CREATE INDEX IF NOT EXISTS idx_susc_unidad ON suscripciones (id_unidad) WHERE id_unidad IS NOT NULL;
-- Cada línea de la suscripción sabe qué cargo la originó (para NC/anulación).
ALTER TABLE suscripciones_detalle ADD COLUMN IF NOT EXISTS id_cargo INTEGER;
CREATE INDEX IF NOT EXISTS idx_susc_det_cargo ON suscripciones_detalle (id_cargo) WHERE id_cargo IS NOT NULL;

COMMIT;
