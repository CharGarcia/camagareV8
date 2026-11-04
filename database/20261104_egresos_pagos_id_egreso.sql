-- =============================================================================
-- Índice para el aviso de asientos pendientes (Estados Financieros, Asientos,
-- Balance de Comprobación, Mayores) y la generación automática de Egresos.
--
-- Desde el 04-11-2026 un egreso sin asiento solo cuenta como pendiente si tiene
-- al menos un pago vigente con monto (sin pagos, o con todos los cheques
-- anulados, no hay valor que contabilizar). Para saberlo busca, por cada egreso
-- sin asiento:
--     egresos_pagos  WHERE id_egreso = …
-- ingresos_pagos ya tiene idx_ingresos_pagos_cabecera (id_ingreso); egresos_pagos
-- no tenía índice por id_egreso y esa búsqueda recorría toda la tabla una vez
-- por egreso.
--
-- Idempotente. CONCURRENTLY no bloquea las escrituras; en pgAdmin ejecutar esta
-- sentencia SOLA (no admite ir dentro de una transacción).
-- =============================================================================

CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_egresos_pagos_id_egreso
    ON egresos_pagos (id_egreso);
