-- ============================================================================
-- Aviso de cheques posfechados en el navbar: índices de soporte
-- ----------------------------------------------------------------------------
-- Contexto:
--   El navbar consulta cada 30 s (por empresa, con caché) los cheques
--   posfechados por cobrar: ContadoresNavbarRepository::getChequesPosfechados().
--   La consulta filtra los cobros/pagos por su fecha de cheque (fecha_cobro),
--   hasta hoy + 5 días. Desde el 28-09-2026 no tiene tope hacia atrás (se
--   avisan TODOS los posfechados sin Fecha Banco) y las transferencias/depósitos
--   /débitos bancarios también llevan fecha_cobro (= fecha de emisión), así que
--   estos índices ya no acotan mucho esa consulta; siguen sirviendo a las
--   búsquedas por rango de fecha de cobro (p. ej. Impresión de Cheques).
--
--   ingresos_pagos y egresos_pagos solo estaban indexadas por id y por su
--   cabecera. Son parciales (solo filas con fecha_cobro).
--
--   La comprobación de "ya cobrado" (Fecha Banco) ya tiene índice:
--   ux_cbm_origen y control_bancario_movimientos_id_asiento_detalle_key.
--
-- Qué hace:
--   Crea 2 índices. NO modifica ni borra datos. NO toca ninguna fila.
--
-- Reversible: sí, sin pérdida de datos — DROP INDEX IF EXISTS <nombre>;
--
-- ----------------------------------------------------------------------------
-- CÓMO EJECUTARLO EN pgAdmin
-- ----------------------------------------------------------------------------
--   Abrir la base en pgAdmin → clic derecho → Query Tool → pegar TODO este
--   archivo → botón de ejecutar (F5). Se ejecuta de una sola vez.
--
--   Hacerlo en un momento de baja actividad: mientras se construye cada índice,
--   esa tabla NO acepta escrituras (quien esté guardando un ingreso/egreso en
--   ese instante espera a que termine). Las consultas siguen normales. En tablas
--   de este tamaño suele tardar segundos.
--
--   Antes, si se quiere, comprobar que no exista ya un índice equivalente con
--   otro nombre (IF NOT EXISTS solo compara el nombre):
--
--     SELECT tablename, indexname, indexdef FROM pg_indexes
--     WHERE tablename IN ('ingresos_pagos', 'egresos_pagos')
--       AND indexdef ILIKE '%fecha_cobro%';
-- ============================================================================

CREATE INDEX IF NOT EXISTS idx_ingresos_pagos_fecha_cobro
    ON ingresos_pagos (fecha_cobro)
    WHERE fecha_cobro IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_egresos_pagos_fecha_cobro
    ON egresos_pagos (fecha_cobro)
    WHERE fecha_cobro IS NOT NULL;

ANALYZE ingresos_pagos;
ANALYZE egresos_pagos;
