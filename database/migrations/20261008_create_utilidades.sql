-- ============================================================================
-- Módulo Utilidades (Nómina) — participación de los trabajadores en las
-- utilidades (15%, Art. 97 Código del Trabajo) + informe al Ministerio del
-- Trabajo + pago por Egresos → Nómina + asiento del 31 de diciembre.
-- ----------------------------------------------------------------------------
-- Reparto (Reglamento para el pago y legalización de utilidades):
--   · 10% entre todos los trabajadores y ex trabajadores del año, proporcional a
--     los días laborados (base 360, mismos empleado_periodos que los décimos).
--   · 5% proporcional a días × cargas familiares (empleados.cargas_familiares).
--     Si nadie tiene cargas, ese 5% se reparte como el 10%.
--   · Tope por trabajador: 24 SBU del año; el excedente va al IESS (régimen de
--     prestaciones solidarias), no al empleado.
-- El PAGO no se hace desde aquí: cada fila aparece como documento pendiente en
-- Egresos → Nómina (tipo_documento = 'UTILIDADES'), igual que el décimo cuarto.
-- Ese egreso debita la cuenta "Participación Trabajadores por Pagar"
-- (PARTICIPACIONTRABAJADORESPORPAGARNOMINA) que el botón Contabilizar del módulo
-- acredita en el asiento del 31 de diciembre del ejercicio.
-- Operativa multiempresa, eliminación lógica y auditoría. SIN tipo_ambiente
-- (igual que los décimos: no es comprobante SRI).
-- ============================================================================

-- 1. Cabecera: una corrida por empresa + ejercicio fiscal
CREATE TABLE IF NOT EXISTS utilidades_cabecera (
    id                   SERIAL PRIMARY KEY,
    id_empresa           INTEGER NOT NULL,
    anio                 SMALLINT NOT NULL,               -- ejercicio que generó la utilidad
    fecha_desde          DATE NOT NULL,                   -- 1-ene del ejercicio
    fecha_hasta          DATE NOT NULL,                   -- 31-dic del ejercicio
    fecha_limite_pago    DATE NOT NULL,                   -- 15-abr del año siguiente
    fecha_emision        DATE NOT NULL DEFAULT CURRENT_DATE, -- día del cálculo (la usa Egresos para validar la fecha de pago)
    utilidad_liquida     NUMERIC(14,2) NOT NULL DEFAULT 0, -- utilidad contable antes de participación e impuesto (informativo)
    monto_repartir       NUMERIC(14,2) NOT NULL DEFAULT 0, -- el 15% a repartir (editable)
    monto_10             NUMERIC(14,2) NOT NULL DEFAULT 0, -- 10/15 del monto a repartir
    monto_5              NUMERIC(14,2) NOT NULL DEFAULT 0, -- 5/15 del monto a repartir
    sbu_aplicado         NUMERIC(10,2) NOT NULL DEFAULT 0,
    tope_trabajador      NUMERIC(14,2) NOT NULL DEFAULT 0, -- 24 × SBU
    total_empleados      INTEGER NOT NULL DEFAULT 0,
    total_dias           INTEGER NOT NULL DEFAULT 0,
    total_cargas         INTEGER NOT NULL DEFAULT 0,       -- Σ cargas de los empleados con días
    total_valor          NUMERIC(14,2) NOT NULL DEFAULT 0, -- Σ a pagar a los trabajadores (tras el tope)
    total_excedente      NUMERIC(14,2) NOT NULL DEFAULT 0, -- Σ sobre el tope (va al IESS)
    estado               VARCHAR(20) NOT NULL DEFAULT 'borrador', -- borrador/calculado/contabilizado (anulado = eliminado true)
    id_asiento           INTEGER,
    eliminado            BOOLEAN NOT NULL DEFAULT false,
    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by           INTEGER,
    updated_by           INTEGER,
    deleted_at           TIMESTAMP,
    deleted_by           INTEGER
);

CREATE UNIQUE INDEX IF NOT EXISTS uk_utilidades_periodo
    ON utilidades_cabecera (id_empresa, anio)
    WHERE eliminado = false;
CREATE INDEX IF NOT EXISTS idx_utilidades_cabecera_empresa
    ON utilidades_cabecera (id_empresa) WHERE eliminado = false;

-- 2. Detalle: una fila por trabajador (activos y salidos durante el año), con
--    snapshot de los datos que exige el informe del Ministerio.
CREATE TABLE IF NOT EXISTS utilidades_detalle (
    id                SERIAL PRIMARY KEY,
    id_cabecera       INTEGER NOT NULL REFERENCES utilidades_cabecera(id) ON DELETE CASCADE,
    id_empresa        INTEGER NOT NULL,
    id_empleado       INTEGER NOT NULL,
    identificacion    VARCHAR(25) NOT NULL,
    nombres           VARCHAR(150) NOT NULL,
    apellidos         VARCHAR(150) NOT NULL,
    sexo              CHAR(1),
    codigo_ocupacion  VARCHAR(20),
    activo            BOOLEAN NOT NULL DEFAULT true,     -- false = ex trabajador (salió durante el año)
    dias_laborados    SMALLINT NOT NULL DEFAULT 0,
    cargas_familiares SMALLINT NOT NULL DEFAULT 0,       -- snapshot de empleados.cargas_familiares, editable
    valor_10          NUMERIC(12,2) NOT NULL DEFAULT 0,  -- participación por tiempo
    valor_5           NUMERIC(12,2) NOT NULL DEFAULT 0,  -- participación por cargas
    valor_bruto       NUMERIC(12,2) NOT NULL DEFAULT 0,  -- valor_10 + valor_5
    excedente         NUMERIC(12,2) NOT NULL DEFAULT 0,  -- lo que supera el tope de 24 SBU
    valor             NUMERIC(12,2) NOT NULL DEFAULT 0,  -- a pagar al trabajador = valor_bruto − excedente
    valor_retencion   NUMERIC(10,2) NOT NULL DEFAULT 0,  -- retención judicial (editable, 0 por defecto)
    tipo_pago         VARCHAR(2) NOT NULL DEFAULT 'P',   -- P/A/RP/RA (mismos códigos que los décimos; VARCHAR, no CHAR: CHAR rellena 'A ' con espacio)
    discapacidad      BOOLEAN NOT NULL DEFAULT false,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by        INTEGER,
    updated_by        INTEGER
);

CREATE INDEX IF NOT EXISTS idx_utilidades_detalle_cab ON utilidades_detalle (id_cabecera);
CREATE INDEX IF NOT EXISTS idx_utilidades_detalle_emp ON utilidades_detalle (id_empresa, id_empleado);

-- 3. Conceptos contables (catálogo global, sección Nómina de Configuración
--    Contable). El gasto se reconoce al 31-dic del ejercicio con el botón
--    Contabilizar; el pasivo lo cancela el egreso que paga a cada trabajador.
INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, tipo_cuenta, debe_haber)
SELECT v.tipo_asiento, v.referencia, v.detalle, v.codigo, v.tipo_cuenta, v.debe_haber
FROM (VALUES
    ('nomina', 'Gasto Participación Trabajadores',    'Gasto por el 15% de participación de los trabajadores en las utilidades (asiento del 31-dic).', 'GASTOPARTICIPACIONTRABAJADORESNOMINA',    'gasto',  'debe'),
    ('nomina', 'Participación Trabajadores por Pagar', 'Participación de los trabajadores en las utilidades por pagar (15%).',                          'PARTICIPACIONTRABAJADORESPORPAGARNOMINA', 'pasivo', 'haber')
) AS v(tipo_asiento, referencia, detalle, codigo, tipo_cuenta, debe_haber)
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = v.codigo);

-- 4. Menú (bajo el módulo Nómina, el mismo del Décimo Cuarto) y permisos: quien
--    ya tiene asignado Décimo Cuarto recibe los mismos permisos sobre Utilidades,
--    para no dejar el módulo invisible tras el despliegue.
BEGIN;

INSERT INTO submodulos_menu (nombre_submodulo, ruta, id_modulo, orden, id_icono, status)
SELECT 'Utilidades', 'modulos/utilidades',
       COALESCE((SELECT id_modulo FROM submodulos_menu WHERE ruta = 'modulos/decimo-cuarto' LIMIT 1), 313),
       0,
       (SELECT id_icono FROM submodulos_menu WHERE ruta = 'modulos/decimo-cuarto' LIMIT 1),
       1
WHERE NOT EXISTS (SELECT 1 FROM submodulos_menu WHERE ruta = 'modulos/utilidades');

INSERT INTO modulos_asignados (id_usuario, id_empresa, id_modulo, id_submodulo, r, w, u, d, t)
SELECT ma.id_usuario, ma.id_empresa, ma.id_modulo,
       (SELECT id FROM submodulos_menu WHERE ruta = 'modulos/utilidades' LIMIT 1),
       ma.r, ma.w, ma.u, ma.d, ma.t
FROM modulos_asignados ma
WHERE ma.id_submodulo = (SELECT id FROM submodulos_menu WHERE ruta = 'modulos/decimo-cuarto' LIMIT 1)
  AND NOT EXISTS (
      SELECT 1 FROM modulos_asignados x
      WHERE x.id_usuario = ma.id_usuario
        AND x.id_empresa = ma.id_empresa
        AND x.id_submodulo = (SELECT id FROM submodulos_menu WHERE ruta = 'modulos/utilidades' LIMIT 1)
  );

COMMIT;

-- Verificación (config/modulos_mvc.php lleva id_submodulo = 0: resuelve por ruta)
SELECT id AS id_submodulo_utilidades, nombre_submodulo, ruta, id_modulo
FROM submodulos_menu
WHERE ruta = 'modulos/utilidades';
