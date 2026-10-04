-- ============================================================
-- Cheques posfechados: cuenta puente y asiento de cobro.
--
-- 1. Dos conceptos GLOBALES en asientos_tipo (tipo_asiento = 'cobros_pagos'),
--    configurables en Configuración Contable → Cobros y Pagos:
--      CHEQUESPOSFECHADOSPORCOBRAR (activo, Debe)  — cheques recibidos con fecha futura.
--      CHEQUESPOSFECHADOSPORPAGAR  (pasivo, Haber) — cheques emitidos con fecha futura.
--    Si la empresa no les asigna cuenta, todo sigue como antes (el cheque va a Bancos).
--    Aplican a los ingresos/egresos con fecha desde el día en que se asigna la cuenta.
--
-- 2. control_bancario_movimientos.id_asiento_cobro: el asiento que pasa el cheque de la
--    cuenta puente a Bancos al registrar su Fecha Banco en Control Bancario.
--
-- Idempotente: se puede ejecutar más de una vez.
-- ============================================================

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, tipo_cuenta, debe_haber, eliminado, created_at)
SELECT 'cobros_pagos', 'Cheques posfechados por cobrar',
       'Cheques recibidos con fecha futura, hasta que el banco los cobra',
       'CHEQUESPOSFECHADOSPORCOBRAR', 'activo', 'debe', false, CURRENT_TIMESTAMP
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = 'CHEQUESPOSFECHADOSPORCOBRAR');

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, tipo_cuenta, debe_haber, eliminado, created_at)
SELECT 'cobros_pagos', 'Cheques posfechados por pagar',
       'Cheques emitidos con fecha futura, hasta que el banco los cobra',
       'CHEQUESPOSFECHADOSPORPAGAR', 'pasivo', 'haber', false, CURRENT_TIMESTAMP
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = 'CHEQUESPOSFECHADOSPORPAGAR');

ALTER TABLE control_bancario_movimientos
    ADD COLUMN IF NOT EXISTS id_asiento_cobro BIGINT NULL;

COMMENT ON COLUMN control_bancario_movimientos.id_asiento_cobro IS
    'Asiento de cobro de un cheque posfechado (cuenta puente → Bancos), generado al registrar la Fecha Banco.';

CREATE INDEX IF NOT EXISTS idx_cbm_asiento_cobro
    ON control_bancario_movimientos (id_asiento_cobro)
    WHERE id_asiento_cobro IS NOT NULL;

-- 3. Protesto de cheques recibidos (Control Bancario → Cheques Posfechados → Protestado).
--    El ingreso se anula (la factura vuelve a quedar pendiente) y el cheque queda marcado.
ALTER TABLE ingresos_pagos ADD COLUMN IF NOT EXISTS estado_cheque VARCHAR(20) NOT NULL DEFAULT 'vigente';
ALTER TABLE ingresos_pagos ADD COLUMN IF NOT EXISTS fecha_protesto DATE NULL;
ALTER TABLE ingresos_pagos ADD COLUMN IF NOT EXISTS motivo_protesto VARCHAR(500) NULL;
ALTER TABLE ingresos_pagos ADD COLUMN IF NOT EXISTS protestado_at TIMESTAMP NULL;
ALTER TABLE ingresos_pagos ADD COLUMN IF NOT EXISTS protestado_by INTEGER NULL;

-- Verificación
SELECT id, tipo_asiento, codigo, referencia, tipo_cuenta, debe_haber
FROM asientos_tipo
WHERE codigo IN ('CHEQUESPOSFECHADOSPORCOBRAR', 'CHEQUESPOSFECHADOSPORPAGAR');
