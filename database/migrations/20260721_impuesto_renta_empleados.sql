-- ============================================================
-- MOTOR DE IMPUESTO A LA RENTA (RETENCIÓN EN LA FUENTE) — EMPLEADOS
-- Relación de dependencia — alimenta el casillero 302/352 del Formulario 103.
-- Catálogos GLOBALES (no llevan id_empresa): son parámetros nacionales del SRI,
-- iguales para todas las empresas y se actualizan una vez al año.
-- ============================================================

-- Tabla de tramos del Impuesto a la Renta (fracción básica / % sobre excedente).
-- Se entrega VACÍA a propósito: el usuario debe cargar los valores oficiales
-- vigentes (resolución del SRI del año correspondiente) antes de usarla.
CREATE TABLE IF NOT EXISTS impuesto_renta_tramos (
    id                        SERIAL PRIMARY KEY,
    anio                      SMALLINT NOT NULL,
    orden                     SMALLINT NOT NULL,
    fraccion_basica           NUMERIC(14,2) NOT NULL DEFAULT 0,  -- límite inferior del tramo
    exceso_hasta              NUMERIC(14,2),                      -- límite superior; NULL = "en adelante" (último tramo)
    impuesto_fraccion_basica  NUMERIC(14,2) NOT NULL DEFAULT 0,
    porcentaje_excedente      NUMERIC(5,2)  NOT NULL DEFAULT 0,
    eliminado                 BOOLEAN NOT NULL DEFAULT false,
    created_at                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by                INTEGER,
    updated_by                INTEGER,
    UNIQUE (anio, orden)
);

-- Parámetros anuales adicionales (por ahora: tope de gasto personal deducible).
-- También vacía; si un año no tiene fila, el sistema NO aplica deducción por
-- gasto personal (retención más alta que la real) hasta que se cargue.
CREATE TABLE IF NOT EXISTS impuesto_renta_parametros (
    anio                   SMALLINT PRIMARY KEY,
    gasto_personal_maximo  NUMERIC(14,2) NOT NULL DEFAULT 0,
    updated_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by             INTEGER
);

-- Retención de IR calculada para la línea del rol (mensual). Análoga a aporte_iess.
ALTER TABLE rol_detalle ADD COLUMN IF NOT EXISTS retencion_renta NUMERIC(14,2) NOT NULL DEFAULT 0;

-- Empleado: permite excluir del cálculo automático de IR (casos especiales:
-- ya declara por su cuenta, exento, etc.).
ALTER TABLE empleados ADD COLUMN IF NOT EXISTS excluir_calculo_ir BOOLEAN NOT NULL DEFAULT false;

COMMENT ON TABLE impuesto_renta_tramos IS 'Tabla anual de tramos de Impuesto a la Renta (fracción básica/excedente) publicada por el SRI. Catálogo global, cargar manualmente cada año.';
COMMENT ON TABLE impuesto_renta_parametros IS 'Parámetros anuales del cálculo de IR (tope de gasto personal deducible). Catálogo global, cargar manualmente cada año.';
COMMENT ON COLUMN rol_detalle.retencion_renta IS 'Impuesto a la Renta retenido en la fuente (relación de dependencia) para esta línea del rol mensual.';
COMMENT ON COLUMN empleados.excluir_calculo_ir IS 'Si es true, el motor de rol de pagos no calcula retención de IR para este empleado.';
