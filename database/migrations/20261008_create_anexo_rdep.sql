-- ============================================================================
-- Anexo RDEP — Retenciones en la fuente bajo relación de dependencia (SRI).
-- Módulo modulos/anexo-rdep. Esquema oficial: app/Services/Xml/xsd/rdep.xsd
-- (Esquema RDEP 2023, vigente); ficha técnica y catálogo 2024 del SRI.
-- ----------------------------------------------------------------------------
-- Una cabecera por empresa y ejercicio (anexo anual) y una fila por trabajador
-- con TODOS los campos que exige el XML, en el mismo orden lógico de la ficha:
-- datos del trabajador, ingresos, gastos/deducciones/exoneraciones y resumen
-- impositivo. La fila se arma desde la nómina (roles mensuales, décimos,
-- utilidades, gastos personales) y el usuario la completa a mano donde la
-- nómina no tiene el dato (otros empleadores, impuesto asumido, discapacidad…).
-- Los campos editados a mano se anotan en `campos_manuales` para no pisarlos al
-- volver a importar.
-- Operativa multiempresa, eliminación lógica y auditoría. SIN tipo_ambiente
-- (igual que el ADI y los décimos: no es comprobante electrónico).
-- ============================================================================

CREATE TABLE IF NOT EXISTS anexo_rdep (
    id                  SERIAL PRIMARY KEY,
    id_empresa          INTEGER NOT NULL,
    anio                SMALLINT NOT NULL,                     -- ejercicio informado (enero a diciembre)
    -- Cabecera del XML
    num_ruc             VARCHAR(13) NOT NULL DEFAULT '',
    razon_social        VARCHAR(300) NOT NULL DEFAULT '',
    tipo_empleador      VARCHAR(13) NOT NULL DEFAULT 'PRIVADO_MIXTO', -- PRIVADO_MIXTO / PUBLICO
    ente_seg_social     VARCHAR(12) NOT NULL DEFAULT 'IESS',          -- IESS / ISSFA_ISSPOL
    -- Parámetros del ejercicio con los que se recalcula el resumen impositivo
    fraccion_basica     NUMERIC(14,2) NOT NULL DEFAULT 0,  -- fracción básica desgravada del año (tabla de IR)
    canasta_basica      NUMERIC(10,2) NOT NULL DEFAULT 0,  -- canasta familiar básica (enero del ejercicio)
    porcentaje_rebaja   NUMERIC(5,2)  NOT NULL DEFAULT 18, -- rebaja por gastos personales
    ipceg               NUMERIC(6,3)  NOT NULL DEFAULT 1.803, -- índice de precios Galápagos
    factores_canastas   JSONB NOT NULL DEFAULT '{"0": 7, "1": 9, "2": 11, "3": 14, "4": 17, "5": 20, "especial": 100}'::jsonb,
    -- Totales informativos
    total_trabajadores  INTEGER NOT NULL DEFAULT 0,
    total_ingresos      NUMERIC(14,2) NOT NULL DEFAULT 0,  -- Σ ingGravConEsteEmpl
    total_retenido      NUMERIC(14,2) NOT NULL DEFAULT 0,  -- Σ valRet
    total_graves        INTEGER NOT NULL DEFAULT 0,        -- validaciones graves pendientes (última revisión)
    total_leves         INTEGER NOT NULL DEFAULT 0,
    estado              VARCHAR(20) NOT NULL DEFAULT 'borrador', -- borrador / generado
    archivo_xml         VARCHAR(80),
    generado_at         TIMESTAMP,
    importado_at        TIMESTAMP,
    observaciones       TEXT,
    eliminado           BOOLEAN NOT NULL DEFAULT false,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by          INTEGER,
    updated_by          INTEGER,
    deleted_at          TIMESTAMP,
    deleted_by          INTEGER
);

CREATE UNIQUE INDEX IF NOT EXISTS uk_anexo_rdep_periodo
    ON anexo_rdep (id_empresa, anio) WHERE eliminado = false;
CREATE INDEX IF NOT EXISTS idx_anexo_rdep_empresa
    ON anexo_rdep (id_empresa) WHERE eliminado = false;

CREATE TABLE IF NOT EXISTS anexo_rdep_detalle (
    id                  SERIAL PRIMARY KEY,
    id_anexo            INTEGER NOT NULL REFERENCES anexo_rdep(id) ON DELETE CASCADE,
    id_empresa          INTEGER NOT NULL,
    id_empleado         INTEGER,                               -- NULL si se añadió a mano (sin ficha)
    -- ── Datos del trabajador (nodo <empleado>) ──
    tip_id_ret          VARCHAR(1)  NOT NULL DEFAULT 'C',      -- C cédula / P pasaporte / E id. tributaria del exterior
    id_ret              VARCHAR(13) NOT NULL DEFAULT '',
    apellidos           VARCHAR(100) NOT NULL DEFAULT '',
    nombres             VARCHAR(100) NOT NULL DEFAULT '',
    estab               VARCHAR(3)  NOT NULL DEFAULT '001',    -- establecimiento del RUC donde trabaja
    residencia          VARCHAR(2)  NOT NULL DEFAULT '01',     -- 01 local / 02 exterior
    pais_residencia     VARCHAR(3)  NOT NULL DEFAULT '593',
    aplica_convenio     VARCHAR(2)  NOT NULL DEFAULT 'NA',     -- NA / SI / NO
    tipo_discap         VARCHAR(2)  NOT NULL DEFAULT '01',     -- 01 no aplica / 02 con discapacidad / 03 sustituto
    porcentaje_discap   SMALLINT    NOT NULL DEFAULT 0,
    tip_id_discap       VARCHAR(1)  NOT NULL DEFAULT 'N',      -- N / C / P / E
    id_discap           VARCHAR(13) NOT NULL DEFAULT '999',
    ben_galapagos       VARCHAR(2)  NOT NULL DEFAULT 'NO',     -- SI / NO
    enf_catastro        VARCHAR(2)  NOT NULL DEFAULT 'NO',     -- SI / NO (con o a cargo de discapacidad / enf. catastrófica)
    num_cargas          SMALLINT    NOT NULL DEFAULT 0,        -- 0..5 (5 = cinco o más)
    tercera_edad        BOOLEAN     NOT NULL DEFAULT false,    -- 65 años o más al cierre del ejercicio (calculado, editable)
    fecha_nacimiento    DATE,
    -- ── Ingresos ──
    suel_sal            NUMERIC(14,2) NOT NULL DEFAULT 0,  -- sueldos y otros gravados (materia gravada IESS)
    sob_suel            NUMERIC(14,2) NOT NULL DEFAULT 0,  -- otros ingresos gravados (no materia gravada IESS)
    part_util           NUMERIC(14,2) NOT NULL DEFAULT 0,  -- participación de utilidades pagada en el año
    int_grab_gen        NUMERIC(14,2) NOT NULL DEFAULT 0,  -- ingresos gravados con otros empleadores
    imp_rent_empl       NUMERIC(14,2) NOT NULL DEFAULT 0,  -- IR asumido por este empleador (ingreso)
    decim_ter           NUMERIC(14,2) NOT NULL DEFAULT 0,
    decim_cuar          NUMERIC(14,2) NOT NULL DEFAULT 0,
    fondo_reserva       NUMERIC(14,2) NOT NULL DEFAULT 0,
    salario_digno       NUMERIC(14,2) NOT NULL DEFAULT 0,
    otros_ing_no_grav   NUMERIC(14,2) NOT NULL DEFAULT 0,  -- otros ingresos que no constituyen renta gravada
    ing_grav_este_empl  NUMERIC(14,2) NOT NULL DEFAULT 0,  -- calculado
    -- ── Gastos, deducciones y exoneraciones ──
    sis_sal_net         SMALLINT      NOT NULL DEFAULT 1,  -- 1 sin salario neto / 2 con
    apo_per_iess        NUMERIC(14,2) NOT NULL DEFAULT 0,
    apor_per_iess_otros NUMERIC(14,2) NOT NULL DEFAULT 0,
    deduc_vivienda      NUMERIC(14,2) NOT NULL DEFAULT 0,
    deduc_salud         NUMERIC(14,2) NOT NULL DEFAULT 0,
    deduc_educ          NUMERIC(14,2) NOT NULL DEFAULT 0,  -- educación, arte y cultura
    deduc_aliment       NUMERIC(14,2) NOT NULL DEFAULT 0,
    deduc_vestim        NUMERIC(14,2) NOT NULL DEFAULT 0,
    deduc_turismo       NUMERIC(14,2) NOT NULL DEFAULT 0,
    exo_discap          NUMERIC(14,2) NOT NULL DEFAULT 0,  -- calculado
    exo_ter_ed          NUMERIC(14,2) NOT NULL DEFAULT 0,  -- calculado
    -- ── Resumen impositivo ──
    bas_imp             NUMERIC(14,2) NOT NULL DEFAULT 0,  -- calculado
    imp_rent_caus       NUMERIC(14,2) NOT NULL DEFAULT 0,  -- calculado
    rebaja_gastos       NUMERIC(14,2) NOT NULL DEFAULT 0,  -- calculado
    imp_rent_rebaja     NUMERIC(14,2) NOT NULL DEFAULT 0,  -- calculado: impuesto tras la rebaja
    val_ret_otros       NUMERIC(14,2) NOT NULL DEFAULT 0,  -- retenido/asumido por otros empleadores
    val_imp_asu_este    NUMERIC(14,2) NOT NULL DEFAULT 0,  -- asumido por este empleador
    val_ret             NUMERIC(14,2) NOT NULL DEFAULT 0,  -- retenido al trabajador (nómina)
    -- ── Control ──
    campos_manuales     JSONB NOT NULL DEFAULT '[]'::jsonb, -- nombres de columnas editadas a mano (no se pisan al reimportar)
    graves              JSONB NOT NULL DEFAULT '[]'::jsonb, -- mensajes de la última validación
    leves               JSONB NOT NULL DEFAULT '[]'::jsonb,
    observaciones       TEXT,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by          INTEGER,
    updated_by          INTEGER
);

CREATE INDEX IF NOT EXISTS idx_anexo_rdep_detalle_anexo ON anexo_rdep_detalle (id_anexo);
CREATE INDEX IF NOT EXISTS idx_anexo_rdep_detalle_emp ON anexo_rdep_detalle (id_empresa, id_empleado);

-- Menú (bajo el módulo SRI, junto al Anexo ATS y al ADI) y permisos: quien ya
-- tiene asignado el Anexo ATS recibe los mismos permisos sobre el RDEP.
BEGIN;

INSERT INTO submodulos_menu (nombre_submodulo, ruta, id_modulo, orden, id_icono, status)
SELECT 'Anexo RDEP', 'modulos/anexo-rdep',
       COALESCE((SELECT id_modulo FROM submodulos_menu WHERE ruta = 'modulos/anexo-ats' LIMIT 1), 8),
       0,
       (SELECT id_icono FROM submodulos_menu WHERE ruta = 'modulos/anexo-ats' LIMIT 1),
       1
WHERE NOT EXISTS (SELECT 1 FROM submodulos_menu WHERE ruta = 'modulos/anexo-rdep');

INSERT INTO modulos_asignados (id_usuario, id_empresa, id_modulo, id_submodulo, r, w, u, d, t)
SELECT ma.id_usuario, ma.id_empresa, ma.id_modulo,
       (SELECT id FROM submodulos_menu WHERE ruta = 'modulos/anexo-rdep' LIMIT 1),
       ma.r, ma.w, ma.u, ma.d, ma.t
FROM modulos_asignados ma
WHERE ma.id_submodulo = (SELECT id FROM submodulos_menu WHERE ruta = 'modulos/anexo-ats' LIMIT 1)
  AND NOT EXISTS (
      SELECT 1 FROM modulos_asignados x
      WHERE x.id_usuario = ma.id_usuario
        AND x.id_empresa = ma.id_empresa
        AND x.id_submodulo = (SELECT id FROM submodulos_menu WHERE ruta = 'modulos/anexo-rdep' LIMIT 1)
  );

COMMIT;

-- Verificación (config/modulos_mvc.php lleva id_submodulo = 0: resuelve por ruta)
SELECT id AS id_submodulo_anexo_rdep, nombre_submodulo, ruta, id_modulo
FROM submodulos_menu
WHERE ruta = 'modulos/anexo-rdep';
