-- ============================================================================
-- Cotizador de Planes (modulos/cotizador-planes) — fase 1.
--
-- El vendedor arma el precio del sistema según el RUC, los establecimientos,
-- los módulos y los usuarios del cliente, y genera una Proforma enlazada al CRM.
-- Diseño: docs/diseno/cotizador-planes-fase1.md · Manual: docs/manual/modulos/cotizador-planes.md
--
-- Todas las tablas son OPERATIVAS (llevan id_empresa: cada empresa que vende
-- tiene su propio catálogo de precios) y tienen los campos de auditoría y la
-- eliminación lógica de CLAUDE.md §5.
--
-- Idempotente: se puede ejecutar más de una vez.
-- Después de ejecutarlo: registrar el submódulo en submodulos_menu
-- (ruta = 'modulos/cotizador-planes'), actualizar su id en config/modulos_mvc.php
-- y asignar permisos en /config/permisos-modulos.
-- ============================================================================

-- ─── 1) Parámetros por empresa (una fila por empresa) ───────────────────────
CREATE TABLE IF NOT EXISTS cotizador_planes_config (
    id                              SERIAL PRIMARY KEY,
    id_empresa                      INTEGER       NOT NULL,
    recargo_mensual_pct             NUMERIC(6,2)  NOT NULL DEFAULT 20,
    descuento_establecimiento_pct   NUMERIC(6,2)  NOT NULL DEFAULT 20,
    usuarios_por_establecimiento    INTEGER       NOT NULL DEFAULT 3,
    precio_usuario_adicional_anual  NUMERIC(12,2) NOT NULL DEFAULT 20,
    permitir_descuento_vendedor     BOOLEAN       NOT NULL DEFAULT false,
    descuento_vendedor_max_pct      NUMERIC(6,2)  NOT NULL DEFAULT 0,
    id_producto_resumen_anual       INTEGER       NULL,   -- p. ej. «Plan anual CaMaGaRe»
    id_producto_resumen_mensual     INTEGER       NULL,   -- p. ej. «Plan mensual CaMaGaRe»
    dias_vigencia                   INTEGER       NOT NULL DEFAULT 15,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP NULL,
    created_by   INTEGER   NULL,
    updated_by   INTEGER   NULL,
    eliminado    BOOLEAN   NOT NULL DEFAULT false,
    deleted_at   TIMESTAMP NULL,
    deleted_by   INTEGER   NULL
);
CREATE UNIQUE INDEX IF NOT EXISTS uq_cotizador_planes_config_empresa
    ON cotizador_planes_config (id_empresa) WHERE eliminado = false;

-- ─── 2) Catálogo de precios ──────────────────────────────────────────────────
-- tipo = 'modulo'  → un submódulo del menú (id_submodulo + ruta, copiados al guardar).
-- tipo = 'servicio'→ concepto libre (Implementación, Migración, Capacitación…).
-- cobro = 'recurrente' (anual / mensual) | 'unico' (una sola vez).
-- aplica_descuento_establecimiento: entra en el 20 % de los establecimientos
--   adicionales (módulos «base»). En false: adicional a precio completo.
-- por_defecto: entra solo en cada establecimiento nuevo de la cotización.
CREATE TABLE IF NOT EXISTS cotizador_planes_items (
    id                                SERIAL PRIMARY KEY,
    id_empresa                        INTEGER       NOT NULL,
    tipo                              VARCHAR(10)   NOT NULL DEFAULT 'modulo',
    id_submodulo                      INTEGER       NULL,
    ruta                              VARCHAR(150)  NULL,
    grupo                             VARCHAR(100)  NULL,   -- módulo del menú (Ventas, Compras…)
    nombre                            VARCHAR(150)  NOT NULL,
    descripcion                       TEXT          NULL,
    precio_anual                      NUMERIC(12,2) NOT NULL DEFAULT 0,
    cobro                             VARCHAR(12)   NOT NULL DEFAULT 'recurrente',
    aplica_descuento_establecimiento  BOOLEAN       NOT NULL DEFAULT true,
    por_defecto                       BOOLEAN       NOT NULL DEFAULT false,
    id_producto                       INTEGER       NULL,   -- solo servicios únicos: producto de su línea en la proforma
    orden                             INTEGER       NOT NULL DEFAULT 0,
    estado                            VARCHAR(10)   NOT NULL DEFAULT 'activo',
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP NULL,
    created_by   INTEGER   NULL,
    updated_by   INTEGER   NULL,
    eliminado    BOOLEAN   NOT NULL DEFAULT false,
    deleted_at   TIMESTAMP NULL,
    deleted_by   INTEGER   NULL,
    CONSTRAINT ck_cot_items_tipo  CHECK (tipo IN ('modulo', 'servicio')),
    CONSTRAINT ck_cot_items_cobro CHECK (cobro IN ('recurrente', 'unico')),
    CONSTRAINT ck_cot_items_estado CHECK (estado IN ('activo', 'inactivo')),
    CONSTRAINT ck_cot_items_precio CHECK (precio_anual >= 0)
);
CREATE INDEX IF NOT EXISTS idx_cot_items_empresa
    ON cotizador_planes_items (id_empresa) WHERE eliminado = false;
-- Un submódulo aparece una sola vez en el catálogo de cada empresa.
CREATE UNIQUE INDEX IF NOT EXISTS uq_cot_items_submodulo
    ON cotizador_planes_items (id_empresa, id_submodulo) WHERE eliminado = false AND id_submodulo IS NOT NULL;

-- ─── 3) Dependencias: id_item requiere id_item_requerido ─────────────────────
CREATE TABLE IF NOT EXISTS cotizador_planes_items_dependencias (
    id                  SERIAL PRIMARY KEY,
    id_empresa          INTEGER NOT NULL,
    id_item             INTEGER NOT NULL REFERENCES cotizador_planes_items (id),
    id_item_requerido   INTEGER NOT NULL REFERENCES cotizador_planes_items (id),
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP NULL,
    created_by   INTEGER   NULL,
    updated_by   INTEGER   NULL,
    eliminado    BOOLEAN   NOT NULL DEFAULT false,
    deleted_at   TIMESTAMP NULL,
    deleted_by   INTEGER   NULL,
    CONSTRAINT ck_cot_dep_distinto CHECK (id_item <> id_item_requerido)
);
CREATE UNIQUE INDEX IF NOT EXISTS uq_cot_dep
    ON cotizador_planes_items_dependencias (id_empresa, id_item, id_item_requerido) WHERE eliminado = false;

-- ─── 4) Cotizaciones ─────────────────────────────────────────────────────────
-- estado: borrador | proforma (ya generó su proforma) | anulada
-- modalidad: anual | mensual (lo que va a la proforma; la pantalla muestra ambas)
CREATE TABLE IF NOT EXISTS cotizaciones_planes (
    id                       SERIAL PRIMARY KEY,
    id_empresa               INTEGER       NOT NULL,
    numero                   INTEGER       NOT NULL,
    fecha                    DATE          NOT NULL DEFAULT CURRENT_DATE,
    identificacion           VARCHAR(13)   NOT NULL,
    razon_social             VARCHAR(300)  NOT NULL,
    nombre_comercial         VARCHAR(300)  NULL,
    tipo_contribuyente       VARCHAR(20)   NULL,   -- persona_natural | sociedad
    obligado_contabilidad    BOOLEAN       NULL,
    correo                   VARCHAR(255)  NULL,
    telefono                 VARCHAR(50)   NULL,
    direccion                VARCHAR(500)  NULL,
    cod_prov                 VARCHAR(10)   NULL,
    cod_ciudad               VARCHAR(10)   NULL,
    contacto_nombre          VARCHAR(200)  NULL,
    usuarios                 INTEGER       NOT NULL DEFAULT 3,
    modalidad                VARCHAR(10)   NOT NULL DEFAULT 'anual',
    descuento_vendedor_pct   NUMERIC(6,2)  NOT NULL DEFAULT 0,
    total_anual              NUMERIC(14,2) NOT NULL DEFAULT 0,   -- recurrente anual (con descuentos)
    total_mensual            NUMERIC(14,2) NOT NULL DEFAULT 0,   -- recurrente mensual (con descuentos)
    total_unico              NUMERIC(14,2) NOT NULL DEFAULT 0,   -- servicios de una sola vez
    parametros               JSONB         NULL,   -- copia de los parámetros usados en el cálculo
    observaciones            TEXT          NULL,
    id_cliente               INTEGER       NULL,
    id_contacto              INTEGER       NULL,   -- crm_contactos
    id_oportunidad           INTEGER       NULL,   -- crm_oportunidades
    id_proforma              INTEGER       NULL,   -- proformas_cabecera
    id_vendedor              INTEGER       NULL,
    estado                   VARCHAR(12)   NOT NULL DEFAULT 'borrador',
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP NULL,
    created_by   INTEGER   NULL,
    updated_by   INTEGER   NULL,
    eliminado    BOOLEAN   NOT NULL DEFAULT false,
    deleted_at   TIMESTAMP NULL,
    deleted_by   INTEGER   NULL,
    CONSTRAINT ck_cot_modalidad CHECK (modalidad IN ('anual', 'mensual')),
    CONSTRAINT ck_cot_estado    CHECK (estado IN ('borrador', 'proforma', 'anulada')),
    CONSTRAINT ck_cot_usuarios  CHECK (usuarios >= 1)
);
CREATE INDEX IF NOT EXISTS idx_cotizaciones_planes_empresa
    ON cotizaciones_planes (id_empresa, created_at DESC) WHERE eliminado = false;
CREATE UNIQUE INDEX IF NOT EXISTS uq_cotizaciones_planes_numero
    ON cotizaciones_planes (id_empresa, numero);

CREATE TABLE IF NOT EXISTS cotizaciones_planes_establecimientos (
    id               SERIAL PRIMARY KEY,
    id_empresa       INTEGER      NOT NULL,
    id_cotizacion    INTEGER      NOT NULL REFERENCES cotizaciones_planes (id),
    codigo           VARCHAR(3)   NOT NULL,
    nombre           VARCHAR(300) NULL,
    direccion        VARCHAR(500) NULL,
    es_principal     BOOLEAN      NOT NULL DEFAULT false,
    orden            INTEGER      NOT NULL DEFAULT 0,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP NULL,
    created_by   INTEGER   NULL,
    updated_by   INTEGER   NULL,
    eliminado    BOOLEAN   NOT NULL DEFAULT false,
    deleted_at   TIMESTAMP NULL,
    deleted_by   INTEGER   NULL
);
CREATE INDEX IF NOT EXISTS idx_cot_estab_cotizacion
    ON cotizaciones_planes_establecimientos (id_cotizacion) WHERE eliminado = false;

-- Precio y reglas COPIADOS del catálogo al guardar: un cambio posterior del
-- catálogo no altera una cotización ya hecha.
CREATE TABLE IF NOT EXISTS cotizaciones_planes_detalle (
    id                                SERIAL PRIMARY KEY,
    id_empresa                        INTEGER       NOT NULL,
    id_cotizacion                     INTEGER       NOT NULL REFERENCES cotizaciones_planes (id),
    id_establecimiento                INTEGER       NOT NULL REFERENCES cotizaciones_planes_establecimientos (id),
    id_item                           INTEGER       NOT NULL,
    nombre                            VARCHAR(150)  NOT NULL,
    grupo                             VARCHAR(100)  NULL,
    tipo                              VARCHAR(10)   NOT NULL,
    cobro                             VARCHAR(12)   NOT NULL,
    precio_anual                      NUMERIC(12,2) NOT NULL DEFAULT 0,
    aplica_descuento_establecimiento  BOOLEAN       NOT NULL DEFAULT true,
    por_dependencia                   BOOLEAN       NOT NULL DEFAULT false,
    id_producto                       INTEGER       NULL,
    orden                             INTEGER       NOT NULL DEFAULT 0,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP NULL,
    created_by   INTEGER   NULL,
    updated_by   INTEGER   NULL,
    eliminado    BOOLEAN   NOT NULL DEFAULT false,
    deleted_at   TIMESTAMP NULL,
    deleted_by   INTEGER   NULL
);
CREATE INDEX IF NOT EXISTS idx_cot_det_cotizacion
    ON cotizaciones_planes_detalle (id_cotizacion) WHERE eliminado = false;

COMMENT ON TABLE cotizador_planes_config IS 'Cotizador de Planes: parámetros de precio por empresa (recargo mensual, descuento por establecimiento, usuarios incluidos, etc.).';
COMMENT ON TABLE cotizador_planes_items IS 'Cotizador de Planes: catálogo de precios (módulos del menú y servicios) por empresa.';
COMMENT ON TABLE cotizador_planes_items_dependencias IS 'Cotizador de Planes: un ítem requiere a otro; al cotizar se agrega solo.';
COMMENT ON TABLE cotizaciones_planes IS 'Cotizador de Planes: cotizaciones hechas por los vendedores.';
