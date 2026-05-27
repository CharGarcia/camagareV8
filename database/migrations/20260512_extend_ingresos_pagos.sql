-- Extender ingresos_pagos para detalles de operaciones bancarias
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='ingresos_pagos' AND column_name='tipo_operacion_bancaria') THEN
        ALTER TABLE ingresos_pagos ADD COLUMN tipo_operacion_bancaria VARCHAR(20) DEFAULT NULL; -- DEPOSITO, TRANSFERENCIA, CHEQUE
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='ingresos_pagos' AND column_name='numero_cheque') THEN
        ALTER TABLE ingresos_pagos ADD COLUMN numero_cheque VARCHAR(50) DEFAULT NULL;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='ingresos_pagos' AND column_name='fecha_cobro') THEN
        ALTER TABLE ingresos_pagos ADD COLUMN fecha_cobro DATE DEFAULT NULL;
    END IF;
END $$;
