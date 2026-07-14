-- ------------------------------------------------------------
-- Las suscripciones ahora pueden generar Factura de Venta o Recibo de Venta
-- (columna suscripciones.tipo_comprobante: 'factura' | 'recibo').
--
-- suscripciones_pagos.id_factura apunta a ventas_cabecera.id. Guardar ahí el id
-- de un recibo haría match contra una factura ajena con el mismo id (el listado
-- hace LEFT JOIN ventas_cabecera). Se separa el enlace en su propia columna.
-- ------------------------------------------------------------
ALTER TABLE suscripciones_pagos
    ADD COLUMN IF NOT EXISTS id_recibo INTEGER;   -- → recibos_venta_cabecera.id

CREATE INDEX IF NOT EXISTS idx_susc_pagos_recibo ON suscripciones_pagos (id_recibo);
