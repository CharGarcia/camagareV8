-- =============================================================================
-- Anexo de Dividendos (ADI) — Servicio de Rentas Internas del Ecuador
-- Fecha: 2026-09-08
--
-- Crea las tablas del módulo y registra el submódulo en el menú.
-- Alcance: secciones A (informante), B (información de utilidades) y
-- C (información del dividendo: beneficiarios y detalle de la distribución),
-- esquema "2020 en adelante" del catálogo oficial.
--
-- Ejecutar completo en pgAdmin sobre la base del sistema. Es idempotente:
-- se puede volver a correr sin duplicar nada.
-- =============================================================================

BEGIN;

-- ─────────────────────────────────────────────────────────────────────────────
-- 1. Cabecera del anexo: un registro por empresa + año + ambiente
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS anexo_dividendos (
    id                                  BIGSERIAL PRIMARY KEY,
    id_empresa                          INTEGER      NOT NULL,
    anio                                INTEGER      NOT NULL,
    tipo_ambiente                       VARCHAR(1)   NOT NULL DEFAULT '1',

    -- A.2 Datos del informante
    tipo_informante                     VARCHAR(2)   NOT NULL DEFAULT '01',
    tipo_id_informante                  VARCHAR(1)   NOT NULL DEFAULT 'R',
    id_informante                       VARCHAR(13)  NOT NULL DEFAULT '',
    razon_social                        VARCHAR(500) NOT NULL DEFAULT '',

    -- B. Información de utilidades
    utilidad_ejercicio                  NUMERIC(14,2) NOT NULL DEFAULT 0,
    utilidad_distribuida_distinta_reinv NUMERIC(14,2) NOT NULL DEFAULT 0,
    utilidad_reinvertida_con_derecho    NUMERIC(14,2) NOT NULL DEFAULT 0,
    utilidad_reinvertida_sin_derecho    NUMERIC(14,2) NOT NULL DEFAULT 0,
    utilidad_pagada_anticipado          NUMERIC(14,2) NOT NULL DEFAULT 0,
    utilidad_no_distribuida             NUMERIC(14,2) NOT NULL DEFAULT 0,
    utilidad_no_distrib_ejer_ant        NUMERIC(14,2) NOT NULL DEFAULT 0,
    utilidad_distrib_ejercicios_ant     NUMERIC(14,2) NOT NULL DEFAULT 0,

    -- Origen contable: cuentas del plan con las que se arma el anexo.
    -- Formato: [{"id": 123, "lado": "haber"}, ...]  (lado = columna del asiento
    -- que representa el movimiento: 'haber' para pasivo, 'debe' para patrimonio)
    cuentas_dividendos                  JSONB        NOT NULL DEFAULT '[]'::jsonb,
    cuentas_resultados_acum             JSONB        NOT NULL DEFAULT '[]'::jsonb,

    -- Salario básico unificado del año: la franja exenta del art. 39.2 LRTI
    -- (vigente desde septiembre de 2025) es de 3 SBU por persona natural
    -- residente y por sociedad que distribuye.
    sbu                                 NUMERIC(10,2) NOT NULL DEFAULT 0,

    estado                              VARCHAR(20)  NOT NULL DEFAULT 'borrador',
    observaciones                       TEXT,

    -- Auditoría obligatoria
    eliminado   BOOLEAN   NOT NULL DEFAULT false,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER
);

COMMENT ON TABLE  anexo_dividendos IS 'Cabecera del Anexo de Dividendos (ADI) del SRI: informante y sección B de utilidades, por empresa y año.';
COMMENT ON COLUMN anexo_dividendos.cuentas_dividendos IS 'Cuentas del plan que registran la distribución de dividendos, con el lado del asiento que representa el movimiento.';
COMMENT ON COLUMN anexo_dividendos.sbu IS 'Salario básico unificado del año informado; la franja exenta es 3 x SBU (art. 39.2 LRTI).';

CREATE UNIQUE INDEX IF NOT EXISTS ux_anexo_dividendos_empresa_anio
    ON anexo_dividendos (id_empresa, anio, tipo_ambiente)
    WHERE eliminado = false;

CREATE INDEX IF NOT EXISTS idx_anexo_dividendos_empresa
    ON anexo_dividendos (id_empresa, anio DESC)
    WHERE eliminado = false;

-- ─────────────────────────────────────────────────────────────────────────────
-- 2. C.1 Datos del beneficiario del dividendo distribuido
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS anexo_dividendos_beneficiario (
    id                              BIGSERIAL PRIMARY KEY,
    id_empresa                      INTEGER      NOT NULL,
    id_anexo                        BIGINT       NOT NULL REFERENCES anexo_dividendos(id) ON DELETE CASCADE,
    secuencial                      INTEGER      NOT NULL DEFAULT 1,

    tipo_id_perceptor               VARCHAR(1)   NOT NULL,          -- Tabla 1: R/C/E/P
    numero_id_perceptor             VARCHAR(13)  NOT NULL,
    nombre_beneficiario             VARCHAR(500) NOT NULL DEFAULT '', -- solo para la pantalla, no va al XML
    tipo_beneficiario               VARCHAR(2)   NOT NULL,          -- Tabla 2: 01..13
    pais_residencia                 VARCHAR(3)   NOT NULL DEFAULT '593', -- Tabla 3
    regimen_fiscal_preferente       VARCHAR(2),                     -- Tabla 4: 01 SI / 02 NO
    tipo_id_beneficiario_efectivo   VARCHAR(1),
    numero_id_beneficiario_efectivo VARCHAR(13),

    -- Trazabilidad con el tercero del sistema del que se dedujo el beneficiario
    tipo_entidad                    VARCHAR(20),                    -- cliente | proveedor | empleado
    id_entidad                      BIGINT,

    eliminado   BOOLEAN   NOT NULL DEFAULT false,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER
);

COMMENT ON TABLE anexo_dividendos_beneficiario IS 'Sección C.1 del ADI: accionista, socio o partícipe al que se le distribuyó el dividendo.';

-- Clave primaria del anexo según el catálogo del SRI (hoja CLAVE PRIMARIA):
-- tipo y número de identificación + tipo de beneficiario + país + beneficiario efectivo.
CREATE UNIQUE INDEX IF NOT EXISTS ux_anexo_div_benef_clave
    ON anexo_dividendos_beneficiario (
        id_anexo, tipo_id_perceptor, numero_id_perceptor, tipo_beneficiario,
        pais_residencia, COALESCE(numero_id_beneficiario_efectivo, '')
    )
    WHERE eliminado = false;

CREATE INDEX IF NOT EXISTS idx_anexo_div_benef_anexo
    ON anexo_dividendos_beneficiario (id_anexo, secuencial)
    WHERE eliminado = false;

-- ─────────────────────────────────────────────────────────────────────────────
-- 3. C.2 Detalle de la distribución del dividendo
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS anexo_dividendos_detalle (
    id                          BIGSERIAL PRIMARY KEY,
    id_empresa                  INTEGER       NOT NULL,
    id_anexo                    BIGINT        NOT NULL REFERENCES anexo_dividendos(id) ON DELETE CASCADE,
    id_beneficiario             BIGINT        NOT NULL REFERENCES anexo_dividendos_beneficiario(id) ON DELETE CASCADE,

    anio_genera_utilidad        INTEGER       NOT NULL,
    tipo_dividendo              VARCHAR(2)    NOT NULL,             -- Tabla 9: 01..26
    fecha_registro_contable     DATE          NOT NULL,
    monto_dividendo_distribuido NUMERIC(14,2) NOT NULL DEFAULT 0,
    ingreso_gravado             NUMERIC(14,2) NOT NULL DEFAULT 0,
    monto_retencion             NUMERIC(14,2) NOT NULL DEFAULT 0,
    dividendo_pagado            VARCHAR(2)    NOT NULL DEFAULT '02', -- Tabla 4
    isd_pagado                  NUMERIC(14,2) NOT NULL DEFAULT 0,

    -- Trazabilidad con el asiento del que se dedujo la línea
    id_asiento                  BIGINT,
    origen                      VARCHAR(20)   NOT NULL DEFAULT 'manual', -- manual | contabilidad

    eliminado   BOOLEAN   NOT NULL DEFAULT false,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER
);

COMMENT ON TABLE  anexo_dividendos_detalle IS 'Sección C.2 del ADI: cada dividendo distribuido a un beneficiario, por año de generación de la utilidad.';
COMMENT ON COLUMN anexo_dividendos_detalle.origen IS 'manual = capturado por el usuario; contabilidad = importado desde los asientos.';

CREATE INDEX IF NOT EXISTS idx_anexo_div_det_beneficiario
    ON anexo_dividendos_detalle (id_beneficiario)
    WHERE eliminado = false;

CREATE INDEX IF NOT EXISTS idx_anexo_div_det_anexo
    ON anexo_dividendos_detalle (id_anexo, anio_genera_utilidad)
    WHERE eliminado = false;

-- El SRI identifica cada dividendo por beneficiario + fecha de distribución, así
-- que la importación acumula en un solo registro todas las líneas contables del
-- mismo día al mismo accionista. El índice lo garantiza para lo importado; los
-- registros capturados a mano no se restringen aquí (las reglas del módulo los
-- advierten) para no bloquear correcciones puntuales.
CREATE UNIQUE INDEX IF NOT EXISTS ux_anexo_div_det_importado
    ON anexo_dividendos_detalle (id_anexo, id_beneficiario, fecha_registro_contable, anio_genera_utilidad)
    WHERE eliminado = false AND origen = 'contabilidad';

COMMIT;


-- =============================================================================
-- 4. Menú y permisos
--    El submódulo se registra bajo el módulo SRI (id_modulo = 8), junto al
--    Anexo ATS y las declaraciones. Tras ejecutarlo, actualizar el
--    'id_submodulo' de 'modulos/anexo-dividendos' en config/modulos_mvc.php con
--    el id que devuelva el SELECT final, y asignar permisos en
--    /config/permisos-modulos.
-- =============================================================================

BEGIN;

INSERT INTO submodulos_menu (nombre_submodulo, ruta, id_modulo, orden, id_icono, status)
SELECT 'Anexo de Dividendos', 'modulos/anexo-dividendos', 8, 0,
       (SELECT id_icono FROM submodulos_menu WHERE ruta = 'modulos/anexo-ats' LIMIT 1), 1
WHERE NOT EXISTS (
    SELECT 1 FROM submodulos_menu WHERE ruta = 'modulos/anexo-dividendos'
);

-- Acceso total para los administradores que ya tienen el Anexo ATS asignado,
-- para no dejar el módulo invisible tras el despliegue.
INSERT INTO modulos_asignados (id_usuario, id_empresa, id_modulo, id_submodulo, r, w, u, d, t)
SELECT ma.id_usuario, ma.id_empresa, ma.id_modulo,
       (SELECT id FROM submodulos_menu WHERE ruta = 'modulos/anexo-dividendos' LIMIT 1),
       ma.r, ma.w, ma.u, ma.d, ma.t
FROM modulos_asignados ma
WHERE ma.id_submodulo = (SELECT id FROM submodulos_menu WHERE ruta = 'modulos/anexo-ats' LIMIT 1)
  AND NOT EXISTS (
      SELECT 1 FROM modulos_asignados x
      WHERE x.id_usuario = ma.id_usuario
        AND x.id_empresa = ma.id_empresa
        AND x.id_submodulo = (SELECT id FROM submodulos_menu WHERE ruta = 'modulos/anexo-dividendos' LIMIT 1)
  );

COMMIT;

-- Id a copiar en config/modulos_mvc.php
SELECT id AS id_submodulo_anexo_dividendos, nombre_submodulo, ruta
FROM submodulos_menu
WHERE ruta = 'modulos/anexo-dividendos';
