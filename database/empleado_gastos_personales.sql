-- ============================================================================
-- Proyección de gastos personales por empleado y año (formulario SRI-GP)
-- ----------------------------------------------------------------------------
-- Art. 43 LRTI / Art. 104 RALRTI: el empleador retiene el Impuesto a la Renta
-- proyectando la renta anual del trabajador y descontando LA PROYECCIÓN DE
-- GASTOS PERSONALES QUE EL PROPIO TRABAJADOR PRESENTA (formulario GP, en enero),
-- limitada al tope legal del año. Si el trabajador no presenta la proyección,
-- NO se aplica deducción alguna.
--
-- Hasta ahora el sistema aplicaba el tope máximo (impuesto_renta_parametros.
-- gasto_personal_maximo) a todos los empleados por igual, lo que subestimaba la
-- retención de quienes no proyectan gastos. Esta tabla guarda la proyección real
-- de cada empleado; el deducible efectivo pasa a ser:
--
--      deducible = MIN(total proyectado por el empleado, tope del año)
--
-- Tabla operativa (cuelga de empleados) => lleva id_empresa y auditoría completa.
-- No lleva tipo_ambiente: empleados es catálogo maestro por empresa.
-- ============================================================================

CREATE TABLE IF NOT EXISTS empleado_gastos_personales (
    id                       SERIAL PRIMARY KEY,
    id_empresa               INTEGER NOT NULL,
    id_empleado              INTEGER NOT NULL,
    anio                     INTEGER NOT NULL,

    -- Rubros del formulario de proyección de gastos personales (SRI-GP)
    vivienda                 NUMERIC(14,2) NOT NULL DEFAULT 0,
    salud                    NUMERIC(14,2) NOT NULL DEFAULT 0,
    educacion                NUMERIC(14,2) NOT NULL DEFAULT 0,
    alimentacion             NUMERIC(14,2) NOT NULL DEFAULT 0,
    vestimenta               NUMERIC(14,2) NOT NULL DEFAULT 0,
    turismo                  NUMERIC(14,2) NOT NULL DEFAULT 0,

    -- Suma de los rubros: es el valor que se compara contra el tope del año.
    total_proyectado         NUMERIC(14,2) NOT NULL DEFAULT 0,

    -- El tope legal se fija en canastas familiares básicas según el número de
    -- cargas familiares (7 sin cargas … 20 con 5 o más). Caso especial: el
    -- contribuyente con —o a cargo de— personas con discapacidad o enfermedad
    -- catastrófica, rara u huérfana accede a 100 canastas.
    numero_cargas_familiares INTEGER NOT NULL DEFAULT 0,
    caso_especial            BOOLEAN NOT NULL DEFAULT FALSE,
    fecha_presentacion       DATE,
    observacion              TEXT,

    -- Auditoría obligatoria
    created_at               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by               INTEGER,
    updated_by               INTEGER,
    eliminado                BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at               TIMESTAMP,
    deleted_by               INTEGER
);

-- Una sola proyección vigente por empleado y año.
CREATE UNIQUE INDEX IF NOT EXISTS uq_emp_gastos_pers_anio
    ON empleado_gastos_personales (id_empresa, id_empleado, anio)
    WHERE eliminado = FALSE;

-- Lectura masiva al generar el rol mensual (todos los empleados de un año).
CREATE INDEX IF NOT EXISTS ix_emp_gastos_pers_empresa_anio
    ON empleado_gastos_personales (id_empresa, anio)
    WHERE eliminado = FALSE;

CREATE INDEX IF NOT EXISTS ix_emp_gastos_pers_empleado
    ON empleado_gastos_personales (id_empleado)
    WHERE eliminado = FALSE;

COMMENT ON TABLE empleado_gastos_personales IS
    'Proyección anual de gastos personales presentada por el empleado (form. SRI-GP). Base de la deducción en la retención de IR en relación de dependencia.';
COMMENT ON COLUMN empleado_gastos_personales.total_proyectado IS
    'Suma de los rubros. Genera una rebaja del impuesto causado = % rebaja x MIN(total_proyectado, tope por cargas del año).';
