-- ============================================================================
-- Gastos personales: de DEDUCCIÓN de la base a REBAJA del impuesto causado
-- ----------------------------------------------------------------------------
-- El sistema restaba los gastos personales de la base imponible. La normativa
-- vigente los trata como una REBAJA del impuesto causado:
--
--   base imponible anual = ingreso gravado anual − aporte IESS personal
--   impuesto causado     = tabla de tramos (impuesto_renta_tramos)
--   tope gastos          = canasta familiar básica x factor según cargas familiares
--   rebaja               = % rebaja x MIN(gastos proyectados, tope gastos)
--   impuesto anual       = MAX(0, impuesto causado − rebaja)
--
-- El tope ya no es un valor único por año: depende del número de cargas
-- familiares de cada trabajador. Por eso `gasto_personal_maximo` deja de usarse
-- en el cálculo (se conserva la columna como referencia histórica) y se
-- parametriza la canasta básica, el porcentaje de rebaja y los factores.
--
-- Factores (canastas familiares básicas) por número de cargas:
--   0 → 7 | 1 → 9 | 2 → 11 | 3 → 14 | 4 → 17 | 5 o más → 20
--   caso especial (discapacidad / enfermedad catastrófica, rara u huérfana) → 100
-- ============================================================================

ALTER TABLE impuesto_renta_parametros
    ADD COLUMN IF NOT EXISTS canasta_basica    NUMERIC(14,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS porcentaje_rebaja NUMERIC(5,2)  NOT NULL DEFAULT 18,
    ADD COLUMN IF NOT EXISTS factores_canastas JSONB         NOT NULL
        DEFAULT '{"0":7,"1":9,"2":11,"3":14,"4":17,"5":20,"especial":100}'::jsonb;

COMMENT ON COLUMN impuesto_renta_parametros.canasta_basica IS
    'Canasta familiar básica del año (enero). Base del tope de gastos personales.';
COMMENT ON COLUMN impuesto_renta_parametros.porcentaje_rebaja IS
    'Porcentaje del gasto personal que se rebaja del impuesto causado (18% vigente).';
COMMENT ON COLUMN impuesto_renta_parametros.factores_canastas IS
    'Número de canastas básicas según cargas familiares. Clave "especial" = discapacidad / enfermedad catastrófica.';
COMMENT ON COLUMN impuesto_renta_parametros.gasto_personal_maximo IS
    'OBSOLETO: el tope ahora se calcula como canasta_basica x factor por cargas. Se conserva como referencia histórica.';

-- Por si empleado_gastos_personales.sql se desplegó antes de incorporar la columna.
ALTER TABLE empleado_gastos_personales
    ADD COLUMN IF NOT EXISTS caso_especial BOOLEAN NOT NULL DEFAULT FALSE;

-- ---------------------------------------------------------------------------
-- Parámetros 2026: canasta familiar básica de enero 2026 = USD 821,80
-- Topes resultantes: 0 cargas 5.752,60 · 1 → 7.396,20 · 2 → 9.039,80
--                    3 → 11.505,20 · 4 → 13.970,60 · 5+ → 16.436,00
--                    caso especial (100 canastas) → 82.180,00
-- ---------------------------------------------------------------------------
INSERT INTO impuesto_renta_parametros (anio, gasto_personal_maximo, canasta_basica, porcentaje_rebaja)
VALUES (2026, 5752.60, 821.80, 18)
ON CONFLICT (anio) DO UPDATE
    SET canasta_basica    = EXCLUDED.canasta_basica,
        porcentaje_rebaja = EXCLUDED.porcentaje_rebaja,
        updated_at        = CURRENT_TIMESTAMP;
