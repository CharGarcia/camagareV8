-- ============================================================================
-- Módulo Producción — fase 1 (motor genérico de producción)
-- Diseño: docs/diseno/produccion-fase1.md
--
-- Qué hace:   crea las tablas produccion_* (configuración, fórmulas, órdenes y sus
--             líneas, recursos, movimientos, costos imputados, calidad, centros de
--             trabajo) con sus índices. El modelo es completo desde la fase 1: las
--             tablas de etapas, controles, calidad y costos imputados quedan creadas
--             aunque su pantalla llegue en fases siguientes.
-- Toca datos: NO. Solo CREATE TABLE / CREATE INDEX IF NOT EXISTS. Ningún ALTER,
--             UPDATE ni DELETE sobre tablas existentes (§10.4 del diseño).
-- Menú y permisos: NO se crean aquí (los registra el usuario).
-- Idempotente: sí, se puede ejecutar varias veces.
-- Reversible: sí. Para revertir (solo si el módulo no se usó):
--   DROP TABLE IF EXISTS produccion_ordenes_calidad, produccion_ordenes_costos,
--     produccion_ordenes_movimientos, produccion_ordenes_recursos,
--     produccion_ordenes_lineas, produccion_ordenes, produccion_formulas_controles,
--     produccion_formulas_recursos, produccion_formulas_lineas,
--     produccion_formulas_etapas, produccion_formulas, produccion_centros_trabajo,
--     produccion_configuracion;
-- Ejecutar en pgAdmin (Query Tool) de una sola vez (F5).
-- ============================================================================

-- ── Configuración por empresa (una fila por empresa) ─────────────────────────
CREATE TABLE IF NOT EXISTS produccion_configuracion (
    id                      SERIAL PRIMARY KEY,
    id_empresa              INTEGER NOT NULL REFERENCES empresas(id),
    id_bodega_insumos       INTEGER REFERENCES bodegas(id),
    id_bodega_terminado     INTEGER REFERENCES bodegas(id),
    id_bodega_cuarentena    INTEGER REFERENCES bodegas(id),
    permitir_sin_stock      BOOLEAN NOT NULL DEFAULT false,
    exigir_lote_insumos     BOOLEAN NOT NULL DEFAULT false,
    usar_cuarentena         BOOLEAN NOT NULL DEFAULT false,
    metodo_reparto          VARCHAR(20) NOT NULL DEFAULT 'valor_venta',   -- valor_venta | unidades | porcentaje
    contabilizar            BOOLEAN NOT NULL DEFAULT false,
    id_cuenta_materia_prima INTEGER REFERENCES plan_cuentas(id),
    id_cuenta_en_proceso    INTEGER REFERENCES plan_cuentas(id),
    id_cuenta_terminado     INTEGER REFERENCES plan_cuentas(id),
    id_cuenta_mano_obra     INTEGER REFERENCES plan_cuentas(id),
    id_cuenta_cif           INTEGER REFERENCES plan_cuentas(id),
    id_cuenta_merma         INTEGER REFERENCES plan_cuentas(id),
    created_at  TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN NOT NULL DEFAULT false,
    deleted_at  TIMESTAMP WITHOUT TIME ZONE,
    deleted_by  INTEGER
);
CREATE UNIQUE INDEX IF NOT EXISTS ux_prod_config_empresa
    ON produccion_configuracion (id_empresa) WHERE eliminado = false;

-- ── Centros de trabajo (líneas, máquinas, áreas) — pantalla en fase 3 ────────
CREATE TABLE IF NOT EXISTS produccion_centros_trabajo (
    id           SERIAL PRIMARY KEY,
    id_empresa   INTEGER NOT NULL REFERENCES empresas(id),
    codigo       VARCHAR(30),
    nombre       VARCHAR(150) NOT NULL,
    tarifa_hora  NUMERIC(18,6) NOT NULL DEFAULT 0,
    estado       VARCHAR(15) NOT NULL DEFAULT 'activo',
    created_at  TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN NOT NULL DEFAULT false,
    deleted_at  TIMESTAMP WITHOUT TIME ZONE,
    deleted_by  INTEGER
);
CREATE INDEX IF NOT EXISTS ix_prod_ct_empresa ON produccion_centros_trabajo (id_empresa) WHERE eliminado = false;

-- ── Fórmulas (lista de materiales / receta / plantilla) ──────────────────────
CREATE TABLE IF NOT EXISTS produccion_formulas (
    id              SERIAL PRIMARY KEY,
    id_empresa      INTEGER NOT NULL REFERENCES empresas(id),
    codigo          VARCHAR(30) NOT NULL,
    nombre          VARCHAR(200) NOT NULL,
    version         INTEGER NOT NULL DEFAULT 1,
    id_producto     INTEGER NOT NULL REFERENCES productos(id),          -- salida principal
    cantidad_base   NUMERIC(18,6) NOT NULL DEFAULT 1,                   -- lo que rinde la fórmula
    vida_util_dias  INTEGER,
    metodo_reparto  VARCHAR(20),                                         -- NULL = el de la configuración
    instrucciones   TEXT,
    observaciones   TEXT,
    estado          VARCHAR(15) NOT NULL DEFAULT 'borrador',             -- borrador | vigente | inactiva
    created_at  TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN NOT NULL DEFAULT false,
    deleted_at  TIMESTAMP WITHOUT TIME ZONE,
    deleted_by  INTEGER
);
CREATE INDEX IF NOT EXISTS ix_prod_formulas_empresa ON produccion_formulas (id_empresa) WHERE eliminado = false;
CREATE INDEX IF NOT EXISTS ix_prod_formulas_producto ON produccion_formulas (id_empresa, id_producto) WHERE eliminado = false;
-- Una sola versión vigente por producto principal.
CREATE UNIQUE INDEX IF NOT EXISTS ux_prod_formula_vigente
    ON produccion_formulas (id_empresa, id_producto) WHERE estado = 'vigente' AND eliminado = false;

CREATE TABLE IF NOT EXISTS produccion_formulas_etapas (
    id                   SERIAL PRIMARY KEY,
    id_empresa           INTEGER NOT NULL REFERENCES empresas(id),
    id_formula           INTEGER NOT NULL REFERENCES produccion_formulas(id),
    orden                INTEGER NOT NULL DEFAULT 1,
    nombre               VARCHAR(150) NOT NULL,
    id_centro_trabajo    INTEGER REFERENCES produccion_centros_trabajo(id),
    instrucciones        TEXT,
    tiempo_estandar_min  NUMERIC(18,4),
    created_at  TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN NOT NULL DEFAULT false,
    deleted_at  TIMESTAMP WITHOUT TIME ZONE,
    deleted_by  INTEGER
);
CREATE INDEX IF NOT EXISTS ix_prod_fetapas_formula ON produccion_formulas_etapas (id_formula) WHERE eliminado = false;

-- Entradas (insumos) y salidas (principal, coproducto, subproducto, desperdicio).
CREATE TABLE IF NOT EXISTS produccion_formulas_lineas (
    id                     SERIAL PRIMARY KEY,
    id_empresa             INTEGER NOT NULL REFERENCES empresas(id),
    id_formula             INTEGER NOT NULL REFERENCES produccion_formulas(id),
    rol                    VARCHAR(15) NOT NULL,            -- insumo | principal | coproducto | subproducto | desperdicio
    id_producto            INTEGER REFERENCES productos(id),  -- NULL solo en desperdicio sin producto
    descripcion            VARCHAR(200),
    modo_cantidad          VARCHAR(12) NOT NULL DEFAULT 'proporcional',  -- proporcional | porcentaje | fija
    cantidad               NUMERIC(18,6) NOT NULL DEFAULT 0,
    merma_pct              NUMERIC(9,4) NOT NULL DEFAULT 0,
    id_etapa               INTEGER REFERENCES produccion_formulas_etapas(id),
    exige_lote             BOOLEAN NOT NULL DEFAULT false,
    reparto_pct            NUMERIC(9,4),                     -- reparto fijo del costo conjunto
    valor_neto_realizable  NUMERIC(18,6),                    -- subproductos: valor por unidad
    orden                  INTEGER NOT NULL DEFAULT 0,
    observaciones          VARCHAR(300),
    created_at  TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN NOT NULL DEFAULT false,
    deleted_at  TIMESTAMP WITHOUT TIME ZONE,
    deleted_by  INTEGER,
    CONSTRAINT ck_prod_flinea_rol  CHECK (rol IN ('insumo','principal','coproducto','subproducto','desperdicio')),
    CONSTRAINT ck_prod_flinea_modo CHECK (modo_cantidad IN ('proporcional','porcentaje','fija'))
);
CREATE INDEX IF NOT EXISTS ix_prod_flineas_formula ON produccion_formulas_lineas (id_formula) WHERE eliminado = false;
CREATE INDEX IF NOT EXISTS ix_prod_flineas_producto ON produccion_formulas_lineas (id_empresa, id_producto) WHERE eliminado = false;

-- Mano de obra, máquina y costos indirectos.
CREATE TABLE IF NOT EXISTS produccion_formulas_recursos (
    id           SERIAL PRIMARY KEY,
    id_empresa   INTEGER NOT NULL REFERENCES empresas(id),
    id_formula   INTEGER NOT NULL REFERENCES produccion_formulas(id),
    id_etapa     INTEGER REFERENCES produccion_formulas_etapas(id),
    tipo         VARCHAR(10) NOT NULL,                     -- mo | maquina | cif
    descripcion  VARCHAR(150),
    base         VARCHAR(10) NOT NULL DEFAULT 'unidad',    -- unidad | lote | hora | pct_mp
    cantidad     NUMERIC(18,6) NOT NULL DEFAULT 1,
    tarifa       NUMERIC(18,6) NOT NULL DEFAULT 0,
    orden        INTEGER NOT NULL DEFAULT 0,
    created_at  TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN NOT NULL DEFAULT false,
    deleted_at  TIMESTAMP WITHOUT TIME ZONE,
    deleted_by  INTEGER,
    CONSTRAINT ck_prod_frec_tipo CHECK (tipo IN ('mo','maquina','cif')),
    CONSTRAINT ck_prod_frec_base CHECK (base IN ('unidad','lote','hora','pct_mp'))
);
CREATE INDEX IF NOT EXISTS ix_prod_frecursos_formula ON produccion_formulas_recursos (id_formula) WHERE eliminado = false;

-- Parámetros de calidad (pantalla en fase 2).
CREATE TABLE IF NOT EXISTS produccion_formulas_controles (
    id           SERIAL PRIMARY KEY,
    id_empresa   INTEGER NOT NULL REFERENCES empresas(id),
    id_formula   INTEGER NOT NULL REFERENCES produccion_formulas(id),
    id_etapa     INTEGER REFERENCES produccion_formulas_etapas(id),
    nombre       VARCHAR(150) NOT NULL,
    unidad       VARCHAR(30),
    minimo       NUMERIC(18,6),
    maximo       NUMERIC(18,6),
    obligatorio  BOOLEAN NOT NULL DEFAULT true,
    orden        INTEGER NOT NULL DEFAULT 0,
    created_at  TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN NOT NULL DEFAULT false,
    deleted_at  TIMESTAMP WITHOUT TIME ZONE,
    deleted_by  INTEGER
);
CREATE INDEX IF NOT EXISTS ix_prod_fcontroles_formula ON produccion_formulas_controles (id_formula) WHERE eliminado = false;

-- ── Órdenes de producción ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS produccion_ordenes (
    id                    SERIAL PRIMARY KEY,
    id_empresa            INTEGER NOT NULL REFERENCES empresas(id),
    secuencial            INTEGER NOT NULL,
    numero                VARCHAR(20) NOT NULL,
    tipo                  VARCHAR(12) NOT NULL DEFAULT 'orden',   -- rapida | orden | por_pedido | ciclo | maquila
    id_formula            INTEGER REFERENCES produccion_formulas(id),
    version_formula       INTEGER,
    id_producto           INTEGER REFERENCES productos(id),       -- salida principal
    cantidad_planificada  NUMERIC(18,6) NOT NULL DEFAULT 0,
    cantidad_producida    NUMERIC(18,6) NOT NULL DEFAULT 0,
    cantidad_merma        NUMERIC(18,6) NOT NULL DEFAULT 0,       -- merma anormal del principal
    id_bodega_insumos     INTEGER REFERENCES bodegas(id),
    id_bodega_destino     INTEGER REFERENCES bodegas(id),
    id_establecimiento    INTEGER,
    fecha_inicio          DATE NOT NULL DEFAULT CURRENT_DATE,
    fecha_fin             DATE,
    lote                  VARCHAR(50),
    fecha_caducidad       DATE,
    id_cliente            INTEGER,
    id_pedido             INTEGER,
    id_centro_costo       INTEGER,
    id_proyecto           INTEGER,
    responsable           VARCHAR(150),
    es_maquila            BOOLEAN NOT NULL DEFAULT false,
    metodo_reparto        VARCHAR(20),
    costo_materiales      NUMERIC(18,2) NOT NULL DEFAULT 0,
    costo_mano_obra       NUMERIC(18,2) NOT NULL DEFAULT 0,
    costo_cif             NUMERIC(18,2) NOT NULL DEFAULT 0,
    costo_otros           NUMERIC(18,2) NOT NULL DEFAULT 0,
    valor_subproductos    NUMERIC(18,2) NOT NULL DEFAULT 0,
    costo_merma           NUMERIC(18,2) NOT NULL DEFAULT 0,
    costo_total           NUMERIC(18,2) NOT NULL DEFAULT 0,       -- lo que entra al inventario
    estado                VARCHAR(12) NOT NULL DEFAULT 'borrador', -- borrador | liberada | en_proceso | cerrada | anulada
    tipo_ambiente         VARCHAR(1) NOT NULL DEFAULT '1',
    id_asiento_cierre     INTEGER,
    asiento_aviso         TEXT,
    observaciones         TEXT,
    created_at  TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN NOT NULL DEFAULT false,
    deleted_at  TIMESTAMP WITHOUT TIME ZONE,
    deleted_by  INTEGER
);
-- El número no se reutiliza nunca (incluye eliminadas y anuladas).
CREATE UNIQUE INDEX IF NOT EXISTS ux_prod_ordenes_secuencial
    ON produccion_ordenes (id_empresa, tipo_ambiente, secuencial);
CREATE INDEX IF NOT EXISTS ix_prod_ordenes_listado
    ON produccion_ordenes (id_empresa, tipo_ambiente, fecha_inicio DESC) WHERE eliminado = false;
CREATE INDEX IF NOT EXISTS ix_prod_ordenes_producto
    ON produccion_ordenes (id_empresa, id_producto) WHERE eliminado = false;

-- Copia escalada de la fórmula (teórico) + lo real consumido / producido.
CREATE TABLE IF NOT EXISTS produccion_ordenes_lineas (
    id                     SERIAL PRIMARY KEY,
    id_empresa             INTEGER NOT NULL REFERENCES empresas(id),
    id_orden               INTEGER NOT NULL REFERENCES produccion_ordenes(id),
    rol                    VARCHAR(15) NOT NULL,
    id_producto            INTEGER REFERENCES productos(id),
    descripcion            VARCHAR(200),
    cantidad_teorica       NUMERIC(18,6) NOT NULL DEFAULT 0,
    cantidad_real          NUMERIC(18,6) NOT NULL DEFAULT 0,
    merma_pct              NUMERIC(9,4) NOT NULL DEFAULT 0,
    exige_lote             BOOLEAN NOT NULL DEFAULT false,
    reparto_pct            NUMERIC(9,4),
    valor_neto_realizable  NUMERIC(18,6),
    costo_real             NUMERIC(18,2) NOT NULL DEFAULT 0,
    costo_unitario         NUMERIC(18,6) NOT NULL DEFAULT 0,
    fuera_formula          BOOLEAN NOT NULL DEFAULT false,
    orden                  INTEGER NOT NULL DEFAULT 0,
    created_at  TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN NOT NULL DEFAULT false,
    deleted_at  TIMESTAMP WITHOUT TIME ZONE,
    deleted_by  INTEGER,
    CONSTRAINT ck_prod_olinea_rol CHECK (rol IN ('insumo','principal','coproducto','subproducto','desperdicio'))
);
CREATE INDEX IF NOT EXISTS ix_prod_olineas_orden ON produccion_ordenes_lineas (id_orden) WHERE eliminado = false;

CREATE TABLE IF NOT EXISTS produccion_ordenes_recursos (
    id           SERIAL PRIMARY KEY,
    id_empresa   INTEGER NOT NULL REFERENCES empresas(id),
    id_orden     INTEGER NOT NULL REFERENCES produccion_ordenes(id),
    tipo         VARCHAR(10) NOT NULL,
    descripcion  VARCHAR(150),
    base         VARCHAR(10) NOT NULL DEFAULT 'unidad',
    cantidad     NUMERIC(18,6) NOT NULL DEFAULT 1,
    tarifa       NUMERIC(18,6) NOT NULL DEFAULT 0,
    importe      NUMERIC(18,2) NOT NULL DEFAULT 0,
    orden        INTEGER NOT NULL DEFAULT 0,
    created_at  TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN NOT NULL DEFAULT false,
    deleted_at  TIMESTAMP WITHOUT TIME ZONE,
    deleted_by  INTEGER,
    CONSTRAINT ck_prod_orec_tipo CHECK (tipo IN ('mo','maquina','cif')),
    CONSTRAINT ck_prod_orec_base CHECK (base IN ('unidad','lote','hora','pct_mp'))
);
CREATE INDEX IF NOT EXISTS ix_prod_orecursos_orden ON produccion_ordenes_recursos (id_orden) WHERE eliminado = false;

-- Cada consumo y cada entrega real (fuente de la trazabilidad por lote).
CREATE TABLE IF NOT EXISTS produccion_ordenes_movimientos (
    id               SERIAL PRIMARY KEY,
    id_empresa       INTEGER NOT NULL REFERENCES empresas(id),
    id_orden         INTEGER NOT NULL REFERENCES produccion_ordenes(id),
    id_linea         INTEGER REFERENCES produccion_ordenes_lineas(id),
    grupo            INTEGER NOT NULL DEFAULT 1,      -- una acción de consumo/cierre = un grupo
    tipo             VARCHAR(10) NOT NULL,            -- consumo | entrega
    id_producto      INTEGER NOT NULL REFERENCES productos(id),
    id_bodega        INTEGER NOT NULL REFERENCES bodegas(id),
    cantidad         NUMERIC(18,6) NOT NULL,
    numero_lote      VARCHAR(50),
    fecha_caducidad  DATE,
    peso_inicial     NUMERIC(18,6),
    peso_final       NUMERIC(18,6),
    costo_unitario   NUMERIC(18,6) NOT NULL DEFAULT 0,
    costo_total      NUMERIC(18,2) NOT NULL DEFAULT 0,
    id_kardex        INTEGER,
    id_asiento       INTEGER,
    fecha            DATE NOT NULL DEFAULT CURRENT_DATE,
    observaciones    VARCHAR(300),
    created_at  TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN NOT NULL DEFAULT false,
    deleted_at  TIMESTAMP WITHOUT TIME ZONE,
    deleted_by  INTEGER,
    CONSTRAINT ck_prod_omov_tipo CHECK (tipo IN ('consumo','entrega'))
);
CREATE INDEX IF NOT EXISTS ix_prod_omov_orden ON produccion_ordenes_movimientos (id_orden) WHERE eliminado = false;
CREATE INDEX IF NOT EXISTS ix_prod_omov_lote ON produccion_ordenes_movimientos (id_empresa, id_producto, numero_lote) WHERE eliminado = false;

-- Costos imputados desde Compras / Egresos / servicios (pantalla en fase 2).
CREATE TABLE IF NOT EXISTS produccion_ordenes_costos (
    id            SERIAL PRIMARY KEY,
    id_empresa    INTEGER NOT NULL REFERENCES empresas(id),
    id_orden      INTEGER NOT NULL REFERENCES produccion_ordenes(id),
    origen        VARCHAR(15) NOT NULL DEFAULT 'manual',   -- compra | egreso | servicio | nomina | manual
    id_documento  INTEGER,
    descripcion   VARCHAR(200),
    importe       NUMERIC(18,2) NOT NULL DEFAULT 0,
    fecha         DATE NOT NULL DEFAULT CURRENT_DATE,
    created_at  TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN NOT NULL DEFAULT false,
    deleted_at  TIMESTAMP WITHOUT TIME ZONE,
    deleted_by  INTEGER
);
CREATE INDEX IF NOT EXISTS ix_prod_ocostos_orden ON produccion_ordenes_costos (id_orden) WHERE eliminado = false;

-- Resultados de calidad por lote (pantalla en fase 2).
CREATE TABLE IF NOT EXISTS produccion_ordenes_calidad (
    id           SERIAL PRIMARY KEY,
    id_empresa   INTEGER NOT NULL REFERENCES empresas(id),
    id_orden     INTEGER NOT NULL REFERENCES produccion_ordenes(id),
    id_control   INTEGER REFERENCES produccion_formulas_controles(id),
    nombre       VARCHAR(150) NOT NULL,
    valor        VARCHAR(100),
    conforme     BOOLEAN,
    decision     VARCHAR(12),                  -- aprobado | rechazado (fila resumen del lote)
    observaciones VARCHAR(300),
    created_at  TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN NOT NULL DEFAULT false,
    deleted_at  TIMESTAMP WITHOUT TIME ZONE,
    deleted_by  INTEGER
);
CREATE INDEX IF NOT EXISTS ix_prod_ocalidad_orden ON produccion_ordenes_calidad (id_orden) WHERE eliminado = false;

-- ── Comprobación (deben salir 13 filas) ──────────────────────────────────────
-- SELECT table_name FROM information_schema.tables
--  WHERE table_name LIKE 'produccion\_%' ORDER BY table_name;
